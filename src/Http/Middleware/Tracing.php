<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Http\Request;

class Tracing implements MiddlewareInterface
{
    public const TRACING_HEADER_NAME = 'X-Makaira-Trace';

    public function __construct(private OxidSettingsInterface $oxidHelper)
    {
    }

    public function apply(Request $request): Request
    {
        if ($debugTrace = $this->oxidHelper->getRequest()->getRequestParameter('mak_debug')) {
            return $request->withHeader(self::TRACING_HEADER_NAME, $debugTrace);
        }

        return $request;
    }
}
