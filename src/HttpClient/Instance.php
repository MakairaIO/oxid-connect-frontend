<?php

namespace Makaira\OxidConnect\HttpClient;

use Makaira\HttpClient;

class Instance extends HttpClient
{
    public function __construct(private string $instance, private HttpClient $httpClient)
    {
    }

    public function request($method, $url, $body = null, array $headers = []): HttpClient\Response
    {
        $headers[] = sprintf('X-Makaira-Instance: %s', $this->instance);

        return $this->httpClient->request($method, $url, $body, $headers);
    }

}
