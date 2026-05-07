<?php

namespace Makaira\OxidConnect\Http\Middleware;

use JsonException;
use Makaira\OxidConnect\Http\Request;

use const JSON_THROW_ON_ERROR;

class Json implements MiddlewareInterface
{
    /**
     * @param Request $request
     *
     * @return Request
     * @throws JsonException
     */
    public function apply(Request $request): Request
    {
        return $request
            ->withBody(json_encode($request->getBody(), JSON_THROW_ON_ERROR))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
