<?php

namespace Makaira\OxidConnect\HttpClient;

use JsonException;
use Makaira\HttpClient;

use function array_replace;
use function json_encode;

class Json extends HttpClient
{
    private HttpClient $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param       $method
     * @param       $url
     * @param       $body
     * @param array $headers
     *
     * @return HttpClient\Response
     * @throws JsonException
     */
    public function request($method, $url, $body = null, array $headers = []): HttpClient\Response
    {
        $body      = json_encode($body, JSON_THROW_ON_ERROR);
        $headers[] = "Content-Type: application/json; charset=UTF-8";

        return $this->httpClient->request($method, $url, $body, $headers);
    }
}
