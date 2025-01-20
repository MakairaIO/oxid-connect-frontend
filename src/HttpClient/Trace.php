<?php

namespace Makaira\OxidConnect\HttpClient;

use Makaira\HttpClient;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;

class Trace extends HttpClient
{
    public function __construct(private OxidSettingsInterface $oxidHelper, private HttpClient $httpClient)
    {

    }
    public function request($method, $url, $body = null, array $headers = []): HttpClient\Response
    {
        if ($debugTrace = $this->oxidHelper->getRequest()->getRequestParameter('mak_debug')) {
            $headers[] = "X-Makaira-Trace: {$debugTrace}";
        }

        return $this->httpClient->request($method, $url, $body, $headers);
    }
}
