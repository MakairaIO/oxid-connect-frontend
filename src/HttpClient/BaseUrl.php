<?php

namespace Makaira\OxidConnect\HttpClient;

use Makaira\HttpClient;

class BaseUrl extends HttpClient
{
    public function __construct(private string $baseUrl, private HttpClient $httpClient)
    {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function request($method, $url, $body = null, array $headers = []): HttpClient\Response
    {
        $url = sprintf('%s/%s', $this->baseUrl, ltrim($url, '/'));

        return $this->httpClient->request($method, $url, $body, $headers);
    }
}
