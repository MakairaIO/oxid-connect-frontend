<?php

namespace Makaira\OxidConnect\Http;

use JsonException;
use Makaira\Exception as BaseException;
use Makaira\Exceptions\TimeoutException;
use Makaira\HttpClient\Response;
use Makaira\OxidConnect\Http\Middleware\MiddlewareStackInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function curl_setopt_array;
use function explode;
use function fclose;
use function fopen;
use function is_scalar;
use function json_encode;
use function ltrim;
use function md5;
use function sprintf;
use function stream_get_contents;
use function strlen;
use function strtolower;
use function trim;

use const CURL_HTTP_VERSION_1_1;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_ENCODING;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_NOSIGNAL;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_URL;
use const CURLOPT_WRITEHEADER;
use const JSON_THROW_ON_ERROR;

class Client implements ClientInterface
{
    /**
     * @param int $timeout        Timeout in milliseconds
     * @param int $connectTimeout Connect timeout in milliseconds
     */
    public function __construct(
        private MiddlewareStackInterface $middlewareStack,
        private int $timeout = 2000,
        private int $connectTimeout = 500,
        private ?CacheItemPoolInterface $cache = null,
    ) {
    }

    /**
     * @throws BaseException
     * @throws JsonException
     * @throws TimeoutException
     * @throws InvalidArgumentException
     */
    public function request(Request $request): Response
    {
        $request   = $this->middlewareStack->apply($request);
        $cacheItem = null;
        if (null !== $this->cache) {
            $cacheItem = $this->cache->getItem($this->cacheKey($request));
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $response = $this->doRequest($request);

        if ($cacheItem instanceof CacheItemInterface) {
            $cacheItem->set($response);
            $this->cache->save($cacheItem);
        }

        return $response;
    }

    /**
     * @param $responseHeaders
     * @param $response
     */
    private function parseResponseHeaders($responseHeaders, $response): void
    {
        $response->headers = [];
        $rawHeader         = trim($responseHeaders);

        foreach (explode("\r\n", $rawHeader) as $line) {
            if ($line === '') {
                continue;
            }

            if (!empty($line) && !str_starts_with($line, 'HTTP/')) {
                $headerKeyValue = explode(':', $line, 2);
                if (!empty($headerKeyValue)) {
                    if (isset($headerKeyValue[1])) {
                        $response->headers[strtolower($headerKeyValue[0])] = ltrim($headerKeyValue[1]);
                    } else {
                        $response->headers[strtolower($headerKeyValue[0])] = '';
                    }
                }
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function cacheKey(Request $request): string
    {
        $body = $request->getBody();

        if (!is_scalar($body)) {
            $body = json_encode($body, JSON_THROW_ON_ERROR);
        }

        return md5(
            sprintf(
                '%s:%s:%s:%s:',
                $request->getMethod(),
                $request->getUri(),
                $body,
                json_encode($request->getHeaders(), JSON_THROW_ON_ERROR),
            ),
        );
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws BaseException
     * @throws TimeoutException
     */
    private function doRequest(Request $request): Response
    {
        $ch           = curl_init();
        $headerBuffer = fopen('php://memory', 'wb+');

        $body = (string) $request->getBody();
        if (0 < strlen($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $url = $request->getUri();

        $headers = $this->buildHeaders($request->getHeaders());

        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL               => $url,
                CURLOPT_CUSTOMREQUEST     => $request->getMethod(),
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_ENCODING          => '',
                CURLOPT_HTTPHEADER        => $headers,
                CURLOPT_WRITEHEADER       => $headerBuffer,
                CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_1_1,
                CURLOPT_FOLLOWLOCATION    => true,
                CURLOPT_MAXREDIRS         => 3,
                CURLOPT_NOSIGNAL          => 1,
                CURLOPT_TIMEOUT_MS        => $this->timeout < 10 ? $this->timeout * 1000 : $this->timeout,
                CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeout < 10 ? $this->connectTimeout * 1000 : $this->connectTimeout,
            ],
        );

        $curlResponse = curl_exec($ch);

        if (false === $curlResponse) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            fclose($headerBuffer);

            if (28 === $errno) {
                throw new TimeoutException("Connection to server '{$url}' timed out: " . $error);
            }

            throw new BaseException(
                "Could not connect to server '{$url}': " . $error,
            );
        }

        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        $responseHeaders = stream_get_contents($headerBuffer, offset: 0);
        fclose($headerBuffer);

        $response       = new Response();
        $response->body = $curlResponse;

        $this->parseResponseHeaders($responseHeaders, $response);
        $response->status    = $curlInfo['http_code'];
        $response->totalTime = $curlInfo['total_time'];

        return $response;
    }

    private function buildHeaders(array $formattedHeaders): array
    {
        $headers = [];
        foreach ($formattedHeaders as $name => $value) {
            $headers[] = sprintf('%s: %s', $name, $value);
        }

        return $headers;
    }
}
