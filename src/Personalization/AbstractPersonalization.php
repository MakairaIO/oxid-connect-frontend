<?php

namespace Makaira\OxidConnect\Personalization;

abstract class AbstractPersonalization
{

    public function __construct(private array $data = [])
    {
    }

    public function getData(): array
    {
        return $this->data;
    }

    abstract public function getType(): string;
}
