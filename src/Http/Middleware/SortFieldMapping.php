<?php

namespace Makaira\OxidConnect\Http\Middleware;

use JsonException;
use Makaira\OxidConnect\Http\Request;

use function array_change_key_case;

use function is_string;
use function json_decode;
use function json_encode;
use function strtolower;

use const CASE_LOWER;
use const JSON_THROW_ON_ERROR;

class SortFieldMapping implements MiddlewareInterface
{
    public function __construct(private array $fieldMapping)
    {
        $this->fieldMapping = array_change_key_case($this->fieldMapping, CASE_LOWER);
    }

    /**
     * @throws JsonException
     */
    public function apply(Request $request): Request
    {
        $body = $request->getBody();
        $wasJson = false;
        if (is_string($body)) {
            try {
                $body    = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
                $wasJson = true;
            } catch (JsonException) {
            }
        }

        $newSorting = [];

        foreach ($body->sorting as $field => $direction) {
            $lowerCaseField = strtolower($field);
            $newSorting[$this->fieldMapping[$lowerCaseField] ?? $field] = $direction;
        }

        $body->sorting = $newSorting;

        if ($wasJson) {
            $body = json_encode($body, JSON_THROW_ON_ERROR);
        }

        return $request->withBody($body);
    }
}
