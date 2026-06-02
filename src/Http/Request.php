<?php

namespace Makaira\OxidConnect\Http;

class Request
{
    public function __construct(
        private string $method,
        private string $uri,
        private mixed $body = null,
        private array $headers = [],
    ) {
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function withHeader(string $name, string|int|float $value): self
    {
        $new                 = clone $this;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withBody($body): self
    {
        $new       = clone $this;
        $new->body = $body;

        return $new;
    }

    public function withMethod(string $method): self
    {
        $new         = clone $this;
        $new->method = $method;

        return $new;
    }

    public function withUri(string $uri): self
    {
        $new      = clone $this;
        $new->uri = $uri;

        return $new;
    }

    public function withHeaders(array $headers): self
    {
        $new          = clone $this;
        $newHeaders   = $this->headers + $headers;
        $new->headers = $newHeaders;

        return $new;
    }
}
