<?php

namespace Makaira\OxidConnect\HttpClient;

use JsonException;
use Makaira\HttpClient;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function json_encode;
use function str_starts_with;

class Caching extends HttpClient
{
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache)
    {
    }

    /**
     * @param       $method
     * @param       $url
     * @param       $body
     * @param array $headers
     *
     * @return HttpClient\Response|mixed|string
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function request($method, $url, $body = null, array $headers = []): mixed
    {
        return $this->cache->get(
            $this->cacheKey($method, $url, $body, $headers),
            fn(ItemInterface $item) => $this->httpClient->request($method, $url, $body, $headers),
        );
    }

    /**
     * @throws JsonException
     */
    private function cacheKey(string $method, string $url, mixed $body, array $headers): string
    {
        if (!is_scalar($body)) {
            $body = json_encode($body, JSON_THROW_ON_ERROR);
        }

        return md5(
            sprintf(
                '%s:%s:%s:%s:',
                $method,
                $url,
                $body,
                json_encode($headers, JSON_THROW_ON_ERROR),
            ),
        );
    }
}
