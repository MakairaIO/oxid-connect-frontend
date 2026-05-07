<?php

namespace Makaira\OxidConnect\Http\Middleware;

interface MiddlewareStackInterface extends MiddlewareInterface
{
    public function addMiddleware(MiddlewareInterface $middleware, int $priority): void;
}
