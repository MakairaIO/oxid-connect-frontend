<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 * Version:    1.0
 * Author:     Jens Richter <richter@marmalade.de>
 * Author URI: http://www.marmalade.de
 */

namespace Makaira\OxidConnect\Service;

use JsonException;
use Makaira\Aggregation;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\HttpClient;
use Makaira\OxidConnect\Utils\ConnectVersion;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

use function count;
use function implode;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class SearchHandler extends AbstractHandler
{
    /**
     * @var array<int>
     */
    private static ?array $maxItems = null;

    public function __construct(
        HttpClient $httpClient,
        private ModuleSettingServiceInterface $connectSettings,
        private ConnectVersion $connectVersion,
    ) {
        parent::__construct($httpClient);
    }

    /**
     * @param Query $query
     *
     * @return array<Result>
     * @throws ConnectException
     * @throws UnexpectedValueException
     * @throws JsonException
     */
    public function search(Query $query): array
    {
        $query->searchPhrase = htmlspecialchars_decode($query->searchPhrase, ENT_QUOTES);
        $query->apiVersion   = $this->connectVersion->getVersionNumber();
        $body                = json_encode($query, JSON_THROW_ON_ERROR);
        $headers             = [
            "Content-Type: application/json; charset=UTF-8",
        ];

        $response = $this->httpClient->request('POST', '/search/', $body, $headers);

        try {
            $apiResult = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConnectException(
                sprintf('Invalid response from Makaira [HTTP %u]. %s', $response->status, $response->body),
                0,
                $e,
            );
        }

        $apiResult = $this->checkResponse($apiResult, $response);

        if (null === self::$maxItems) {
            self::$maxItems = [
                'category'     => $this->connectSettings->getInteger('makaira_search_results_category'),
                'links'        => $this->connectSettings->getInteger('makaira_search_results_links'),
                'manufacturer' => $this->connectSettings->getInteger('makaira_search_results_manufacturer'),
                'product'      => $this->connectSettings->getInteger('makaira_search_results_product'),
                'suggestion'   => $this->connectSettings->getInteger('makaira_search_results_suggestion'),
            ];
        }

        $result = [];

        foreach ($apiResult as $documentType => $data) {
            if (isset($data['items']) || !isset($data['aggregations'])) {
                $result[$documentType] = $this->parseResult($data, self::$maxItems[$documentType] ?? -1);
            }
        }

        return array_filter($result, static fn(Result $result) => $result->total > 0);
    }

    /**
     * @param mixed               $apiResult
     * @param HttpClient\Response $response
     *
     * @return mixed
     * @throws ConnectException
     * @throws UnexpectedValueException
     */
    public function checkResponse(mixed $apiResult, HttpClient\Response $response): mixed
    {
        if ($response->status >= 400 || (isset($apiResult['ok']) && $apiResult['ok'] === false)) {
            $messageParts      = [];
            $messagePartValues = [];

            if (isset($apiResult['errorId'])) {
                $messageParts[]      = '[Error-ID: %s]';
                $messagePartValues[] = $apiResult['errorId'];
            }

            if (isset($apiResult['message'])) {
                $messageParts[]      = '%s';
                $messagePartValues[] = $apiResult['message'];
            }

            throw new ConnectException(
                sprintf(
                    "[HTTP %u] Error response from Makaira: " . implode(' ', $messageParts),
                    $response->status,
                    ...$messagePartValues,
                ),
            );
        }

        if (!isset($apiResult['product'])) {
            throw new UnexpectedValueException("Product results missing");
        }

        return $apiResult;
    }

    /**
     * @param mixed $data
     * @param int   $maxItems
     *
     * @return Result
     */
    private function parseResult(array $data, int $maxItems = -1): Result
    {
        $data = array_replace(['items' =>  [], 'aggregations' => []], $data);

        $items = $data['items'];

        $data['items'] = [];
        foreach ($items as $key => $item) {
            if (-1 === $maxItems || count($data['items']) < $maxItems) {
                $data['items'][$key] = new ResultItem($item);
            }
        }
        $data['count'] = count($data['items']);

        foreach ($data['aggregations'] as $key => $item) {
            $data['aggregations'][$key] = new Aggregation($item);
        }

        return new Result($data);
    }
}
