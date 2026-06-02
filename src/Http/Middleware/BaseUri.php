<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Makaira\OxidConnect\Http\Request;

use function ltrim;
use function rtrim;
use function sprintf;

class BaseUri implements MiddlewareInterface
{
    public function __construct(private string $baseUri)
    {
        $this->baseUri = rtrim($this->baseUri, '/');
    }

    public function apply(Request $request): Request
    {
        $uri = sprintf('%s/%s', $this->baseUri, ltrim($request->getUri(), '/'));

        return $request->withUri($uri);
    }
}
