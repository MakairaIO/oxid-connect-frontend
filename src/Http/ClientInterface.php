<?php

namespace Makaira\OxidConnect\Http;

use Makaira\HttpClient\Response;

interface ClientInterface
{
    public function request(Request $request): Response;
}
