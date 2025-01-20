<?php

namespace Makaira\OxidConnect\Exception;

use Throwable;

class ModelNotFoundException extends Exception
{
    public function __construct(string $id, string $modelClass, int $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf("Can't find %s with ID %s", $modelClass, $id), $code, $previous);
    }
}
