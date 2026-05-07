<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Makaira\OxidConnect\Http\Request;

use function sprintf;

class Instance implements MiddlewareInterface
{
    public const INSTANCE_HEADER_NAME = 'X-Makaira-Instance';

    public function __construct(private string $instance)
    {
    }

    public function apply(Request $request): Request
    {
        return $request->withHeader(self::INSTANCE_HEADER_NAME, $this->instance);
    }
}
