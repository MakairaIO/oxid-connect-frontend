<?php

namespace Makaira\OxidConnect\Service;

use JsonException;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\FeatureNotAvailableException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Exception;
use Makaira\Exceptions\TimeoutException;
use Makaira\OxidConnect\Http\Client;
use Makaira\OxidConnect\Http\Request;
use Makaira\OxidConnect\Utils\OperationalIntelligence;
use Makaira\RecommendationQuery;
use Makaira\Result;
use Makaira\ResultItem;
use Psr\Cache\InvalidArgumentException;

use function array_map;
use function array_replace;
use function json_decode;

class RecommendationHandler extends AbstractHandler
{
    public function __construct(private OperationalIntelligence $operationalIntelligence, Client $httpClient)
    {
        parent::__construct($httpClient);
    }

    /**
     * @param RecommendationQuery $query
     *
     * @return Result
     * @throws ConnectException
     * @throws FeatureNotAvailableException
     * @throws JsonException
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws TimeoutException
     * @throws InvalidArgumentException
     */
    public function recommendation(RecommendationQuery $query): Result
    {
        $this->operationalIntelligence->apply($query);
        $response  = $this->httpClient->request(new Request('POST', '/recommendation', $query));
        $apiResult = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        if (402 === $response->status) {
            throw new FeatureNotAvailableException("Feature 'recommendations' is not available");
        }
        if (isset($apiResult['ok']) && $apiResult['ok'] === false) {
            throw new ConnectException("Error in makaira: {$apiResult['message']}");
        }

        if (!isset($apiResult['items'])) {
            throw new UnexpectedValueException("Item results missing");
        }

        return $this->parseResult($apiResult);
    }

    private function parseResult(array $data): Result
    {
        $result = ['items' => [], 'count' => 0, 'total' => 0, 'offset' => 0];

        if (isset($data['items'])) {
            $result = array_replace($result, $data);

            $result['items'] = array_map(
                static fn($item) => new ResultItem($item),
                $data['items'],
            );
        }

        return new Result($result);
    }
}
