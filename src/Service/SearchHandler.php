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
use Makaira\Exception;
use Makaira\Exceptions\TimeoutException;
use Makaira\HttpClient\Response;
use Makaira\OxidConnect\Http\Client;
use Makaira\OxidConnect\Http\Request;
use Makaira\OxidConnect\Utils\ConnectVersion;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

use Psr\Cache\InvalidArgumentException;

use function count;
use function implode;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class SearchHandler extends AbstractHandler
{
    /**
     * @var array<int>
     */
    private static ?array $maxItems = null;

    public function __construct(
        Client $httpClient,
        private ModuleSettingServiceInterface $connectSettings,
        private ConnectVersion $connectVersion,
    ) {
        parent::__construct($httpClient);
    }

    /**
     * @return array<Result>
     * @throws ConnectException
     * @throws JsonException
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws TimeoutException
     * @throws InvalidArgumentException
     */
    public function search(Query $query): array
    {
        $query->searchPhrase = htmlspecialchars_decode($query->searchPhrase, ENT_QUOTES);
        $query->apiVersion   = $this->connectVersion->getVersionNumber();

        $response = $this->httpClient->request(new Request('POST', '/search/', $query));

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
     * @throws ConnectException
     * @throws UnexpectedValueException
     */
    public function checkResponse(mixed $apiResult, Response $response): mixed
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

        $items = (array) $data['items'];

        $data['items'] = [];
        foreach ($items as $key => $item) {
            if (-1 === $maxItems || count($data['items']) < $maxItems) {
                $data['items'][$key] = new ResultItem($item);
            }
        }
        $data['count'] = count($data['items']);

        foreach ((array) $data['aggregations'] as $key => $item) {
            $data['aggregations'][$key] = new Aggregation($item);
        }

        return new Result($data);
    }
}
