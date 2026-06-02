<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Makaira\OxidConnect\Http\Request;

interface MiddlewareInterface
{
    public function apply(Request $request): Request;
}
