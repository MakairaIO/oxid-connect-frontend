<?php

namespace Makaira\OxidConnect\HttpClient;

use JsonException;
use Makaira\HttpClient;

use function array_change_key_case;
use function is_string;

class SortFieldMapping extends HttpClient
{
    public function __construct(private HttpClient $httpClient, private array $fieldMapping)
    {
        $this->fieldMapping = array_change_key_case($this->fieldMapping, CASE_LOWER);
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed  $body
     * @param array  $headers
     *
     * @return HttpClient\Response
     * @throws JsonException
     */
    public function request($method, $url, $body = null, array $headers = [])
    {
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

        return $this->httpClient->request($method, $url, $body, $headers);
    }
}
