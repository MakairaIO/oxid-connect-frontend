<?php

namespace Makaira\OxidConnect\Core;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Makaira\Connect\Exception;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Constraints;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Oxid\Core\ViewConfig as MakairaViewConfig;
use Makaira\OxidConnect\Service\ABTestingProvider;
use Makaira\OxidConnect\Service\FilterProvider;
use Makaira\OxidConnect\Service\SearchHandler;
use Makaira\OxidConnect\Tracking\Generator;
use Makaira\OxidConnect\Utils\CategoryInheritance;
use Makaira\OxidConnect\Utils\OperationalIntelligence;
use Makaira\Query;
use Makaira\Result;
use OxidEsales\Eshop\Application\Model\ArticleList;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ViewConfig as OxidViewConfig;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use stdClass;

use function array_map;
use function array_search;
use function in_array;
use function is_bool;
use function is_string;
use function json_decode;
use function preg_replace;
use function stripos;
use function strtolower;
use function strtoupper;
use function time;
use function trim;

class RequestHandler
{
    /**
     * @var array<Result>
     */
    protected array $result = [];

    /**
     * @var array<string, Result>
     */
    protected ?array $additionalResults = null;

    private FilterProvider $aggregationProvider;

    private SearchHandler $searchHandler;

    public function __construct()
    {
        $this->aggregationProvider = ContainerFacade::get(FilterProvider::class);
        $this->searchHandler       = ContainerFacade::get(SearchHandler::class);
    }

    public function deletePageNumberCookie(): void
    {
        Registry::getUtilsServer()->setOxCookie('makairaPageNumber', '', time() - 3600);
    }

    public function getAdditionalResults(): ?array
    {
        if (null !== $this->additionalResults) {
            return $this->additionalResults;
        }

        $filteredArray = array_filter(
            $this->result,
            static fn($result, $type) => ('product' !== $type) && ($result->count > 0),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->additionalResults = $filteredArray;

        return $this->additionalResults;
    }

    public function getAggregations(): array
    {
        return $this->aggregationProvider->getAggregations();
    }

    public function getPageNumber(int $pageNumber = 0): int
    {
        if (!$pageNumber) {
            return (int) Registry::getUtilsServer()->getOxCookie('makairaPageNumber');
        }

        return $pageNumber;
    }

    /**
     * @param Query $query
     *
     * @return int
     * @throws Exception
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    public function getProductCount(Query $query): int
    {
        if (!isset($this->result['product'])) {
            $this->result = $this->searchHandler->search($query);
        }

        return $this->result['product']->total ?? 0;
    }

    /**
     * @param Query $query
     *
     * @return ArticleList
     * @throws JsonException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Exception
     * @throws UnexpectedValueException
     * @throws LanguageNotFoundException
     */
    public function getProductsFromMakaira(Query $query): ArticleList
    {
        $moduleSettings          = ContainerFacade::get(ModuleSettings::class);
        $operationalIntelligence = ContainerFacade::get(OperationalIntelligence::class);
        $operationalIntelligence->apply($query);

        $unmodifiedQuery = clone($query);

        $categoryInheritance = ContainerFacade::get(CategoryInheritance::class);

        $categoryInheritance->applyToAggregation($query);

        $personalizationType = null;
        if ($moduleSettings->getBoolean('makaira_connect_use_econda')) {
            if (isset($_COOKIE['mak_econda_session'])) {
                $econdaData = json_decode($_COOKIE['mak_econda_session'], false, 512, JSON_THROW_ON_ERROR);
            } else {
                $econdaData = [];
                // First request has no emvid yet, we need to create dummy data to get already reordered results by econda
                $oxidViewConfig = Registry::get(OxidViewConfig::class);
                if ($oxidViewConfig instanceof MakairaViewConfig) {
                    $econdaData['timestamp'] = (new DateTime('NOW'))->format(DateTimeInterface::ATOM);
                    $econdaData['emvid']     = 'first_request_dummy_id';
                    $econdaData['aid']       = $oxidViewConfig->getEcondaClientKey();
                }
            }

            $personalizationType                                   = 'econda';
            $query->constraints[Constraints::PERSONALIZATION_TYPE] = $personalizationType;
            $query->constraints[Constraints::PERSONALIZATION_DATA] = $econdaData;
        } elseif ($moduleSettings->getBoolean('makaira_connect_use_odoscope')) {
            $personalizationType                                   = 'odoscope';
            $query->constraints[Constraints::PERSONALIZATION_TYPE] = $personalizationType;

            $token  = $moduleSettings->getString('makaira_connect_odoscope_token');
            $siteId = $moduleSettings->getString('makaira_connect_odoscope_siteid');

            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $userIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $userIp = preg_replace('/,.*$/', '', $userIp);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $userIp = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $userIp = $_SERVER['REMOTE_ADDR'];
            }
            if (is_string($userIp)) {
                $userIp = preg_replace('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', '$1.$2.*.*', $userIp);
            } else {
                $userIp = '';
            }

            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $userAgent = is_string($userAgent) ? $userAgent : '';

            $userRef = $_SERVER['HTTP_REFERER'] ?? '';
            $userRef = is_string($userRef) ? $userRef : '';

            $query->constraints[Constraints::PERSONALIZATION_DATA] = [
                'token'     => $token,
                'siteid'    => $siteId,
                'osccookie' => $_COOKIE["osc-{$token}"],
                'uip'       => $userIp,
                'uas'       => $userAgent,
                'ref'       => $userRef,
            ];
        }

        // Hook for request modification
        $this->modifyRequest($query);

        $searchHandler = ContainerFacade::get(SearchHandler::class);

        try {
            $requestExperiments = json_decode($_COOKIE[Cookies::EXPERIMENTS], true, 512, JSON_THROW_ON_ERROR);
            if ($requestExperiments) {
                $query->constraints[Constraints::AB_EXPERIMENTS] = $requestExperiments;
            }
        } catch (JsonException) {
        }

        $this->result = $searchHandler->search($query);

        if ('odoscope' === $personalizationType) {
            if (isset($this->result['personalization']['oscCookie'])) {
                $cookieValue = $this->result['personalization']['oscCookie'];
                ContainerFacade::get(Cookies::class)->setCookie(
                    "osc-{$token}",
                    $cookieValue,
                    Registry::getUtilsDate()->getTime() + 86400,
                );
            }

            if (isset($this->result['personalization']['oscTrackingGroup'])) {
                $odoscopeTracking['group'] = $this->result['personalization']['oscTrackingGroup'];
            } else {
                $odoscopeTracking['group'] = 'ERROR';
            }

            if (isset($this->result['personalization']['oscTrackingData'])) {
                $odoscopeTracking['data'] = $this->result['personalization']['oscTrackingData'];
            } else {
                $odoscopeTracking['data'] = $token;
            }

            Registry::get(Generator::class)->setOdoscopeData($odoscopeTracking);
        }

        $productResult = $this->result['product'] ?? new stdClass();

        // hook to map ids
        $productIds = $this->mapResultIDs($productResult->items ?? []);

        // Hook for result modification
        $this->afterSearchRequest($productIds);

        $oxArticleList = $this->loadProducts($productIds, $productResult);

        $aggregations = $this->postProcessAggregations($productResult->aggregations ?? [], $query, $unmodifiedQuery);
        $this->aggregationProvider->setAggregations($aggregations);

        $responseExperiments = $this->result['experiments'] ?? [];

        $oxidViewConfig = Registry::get(OxidViewConfig::class);
        if ($oxidViewConfig instanceof MakairaViewConfig) {
            $experiments = [];
            foreach ($responseExperiments as $responseExperiment) {
                $experiments[$responseExperiment['experiment']] = $responseExperiment['variation'];
            }
            ContainerFacade::get(ABTestingProvider::class)->setExperiments($experiments);
        }

        return $oxArticleList;
    }

    protected function modifyRequest(Query $query): void
    {
    }

    /**
     * @param array<object> $items
     *
     * @return array
     */
    public function mapResultIDs(array $items): array
    {
        return array_map(static fn($item) => $item->fields['id'], $items);
    }

    /**
     * @param array<string> $productIds
     *
     * @return void
     */
    public function afterSearchRequest(array &$productIds = []): void
    {
    }

    public function loadProducts(array $productIds): ArticleList
    {
        $productList = oxNew(ArticleList::class);
        $productList->loadIds($productIds);
        $productList->sortByIds($productIds);

        return $productList;
    }

    /**
     * @param array $aggregations
     * @param Query $query
     * @param Query $unmodifiedQuery
     *
     * @return array
     */
    protected function postProcessAggregations(array $aggregations, Query $query, Query $unmodifiedQuery): array
    {
        foreach ($aggregations as $aggregation) {
            switch ($aggregation->type) {
                case 'range_slider_custom_1':
                    // fallthrough intentional
                case 'range_slider_custom_2':
                    // fallthrough intentional
                case 'range_slider':
                case 'range_slider_price':
                    $suffix = 'range_slider_price' === $aggregation->type ? '_price' : '';

                    // Equal min and max values are not allowed
                    if ($aggregation->min === $aggregation->max) {
                        unset($aggregations[$aggregation->key]);
                        continue 2;
                    }
                    $aggregationFromKey = "{$aggregation->key}_from{$suffix}";
                    $aggregationToKey   = "{$aggregation->key}_to{$suffix}";
                    $aggregationHasFrom = isset($query->aggregations[$aggregationFromKey]);
                    $aggregationHasTo   = isset($query->aggregations[$aggregationToKey]);

                    if ($aggregationHasFrom || $aggregationHasTo) {
                        $aggregations[$aggregation->key]->selectedValues['from'] = $aggregationHasFrom ?
                            $query->aggregations[$aggregationFromKey] :
                            $aggregation->min;
                        $aggregations[$aggregation->key]->selectedValues['to']   = $aggregationHasTo ?
                            $query->aggregations[$aggregationToKey] :
                            $aggregation->max;
                    }

                    break;
                case 'categorytree':
                    $aggregations[$aggregation->key]->selectedValues =
                        isset($query->aggregations[$aggregation->key]) ?
                            $unmodifiedQuery->aggregations[$aggregation->key] : [];

                    $this->mapCategoryTitle(
                        $aggregations[$aggregation->key]->values,
                        $aggregations[$aggregation->key]->selectedValues,
                    );

                    break;
                default:
                    $aggregations[$aggregation->key]->values         = array_map(
                        static function ($value) use ($aggregation, $query) {
                            $valueObject           = new stdClass();
                            $valueObject->key      = $value['key'];
                            $valueObject->count    = $value['count'];
                            $valueObject->selected = false;
                            if (isset($query->aggregations[$aggregation->key])) {
                                $valueObject->selected = in_array(
                                    strtolower($valueObject->key),
                                    array_map(
                                        static fn($element) => is_bool($element) ? $element : strtolower($element),
                                        (array) $query->aggregations[$aggregation->key],
                                    ),
                                    true,
                                );
                            }

                            return $valueObject;
                        },
                        $aggregation->values,
                    );
                    $aggregations[$aggregation->key]->selectedValues = $query->aggregations[$aggregation->key] ?? [];
            }
        }

        return $aggregations;
    }

    public function mapCategoryTitle(&$categories, &$selectedCategories): void
    {
        if ($categories && $selectedCategories) {
            foreach ($categories as &$cat) {
                $key = array_search($cat['key'], (array) $selectedCategories, true);
                if (false !== $key) {
                    $cat['selected']          = true;
                    $selectedCategories[$key] = $cat['title'];
                } else {
                    $cat['selected'] = false;
                }
                if ($cat['subtree']) {
                    $this->mapCategoryTitle($cat['subtree'], $selectedCategories);
                }
            }
        }
    }

    public function sanitizeSorting(array $sorting): array
    {
        if (empty($sorting)) {
            return [];
        }

        ['sortby' => $sortField, 'sortdir' => $sortDirection] = $sorting;
        $sortField = preg_replace("/^([^.]+\.)?(.*)$/", "$2", trim($sortField));

        if ($sortField === 'none') {
            return [];
        }

        if (0 === stripos($sortField, 'OX')) {
            $sortField = strtoupper($sortField);
        }

        return [$sortField => $sortDirection];
    }
}
