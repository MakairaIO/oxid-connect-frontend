<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Makaira\OxidConnect\Http\Request;

class MiddlewareStack implements MiddlewareInterface, MiddlewareStackInterface
{
    /**
     * @var array<MiddlewareInterface>
     */
    private array $stack = [];

    public function addMiddleware(MiddlewareInterface $middleware, int $priority = 0): void
    {
        $this->stack[$priority][] = $middleware;
    }

    public function apply(Request $request): Request
    {
        $stack = $this->sortStack();

        foreach ($stack as $middlewares) {
            foreach ($middlewares as $middleware) {
                $request = $middleware->apply($request);
            }
        }

        return $request;
    }

    private function sortStack(): array
    {
        ksort($this->stack);

        return $this->stack;
    }
}
