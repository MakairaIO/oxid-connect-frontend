<?php

namespace Makaira\OxidConnect\Service;

use JsonException;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\OxidSettings;
use Makaira\OxidConnect\Utils\Currency;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

use function array_filter;
use function explode;
use function http_build_query;
use function implode;
use function is_array;
use function parse_url;
use function preg_match;
use function rtrim;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strrpos;
use function substr;
use function urlencode;

class FilterProvider
{
    private static ?array $aggregations = null;

    protected array $generatedFilterUrl = [];

    private string $filterParameterName;

    private bool $enableSeoFilter;

    private ?array $activeFilter = null;

    public function __construct(
        private ModuleSettingServiceInterface $connectSettings,
        private Cookies $cookieHelper,
        private OxidSettings $oxidHelper,
        private Currency $currency,
    ) {
        $this->filterParameterName = $this->connectSettings->getString('makaira_connect_url_param');
        $this->enableSeoFilter     = $this->connectSettings->getBoolean('makaira_connect_seofilter') &&
                                     $this->oxidHelper->seoIsActive();
    }

    public function createRedirectUrl(string $baseUrl, bool $useSeoFilter = true): string
    {
        $this->loadAggregations();

        if ($useSeoFilter && $this->enableSeoFilter) {
            return $this->createSeoUrl($baseUrl, $this->getActiveFilter());
        }

        return $this->createFilterUrl($baseUrl, $this->getActiveFilter());
    }

    private function loadAggregations(): void
    {
        if (self::$aggregations === null) {
            static::$aggregations = $this->cookieHelper->loadMakairaFilterFromCookie();
        }
    }

    /**
     * @param string $baseUrl
     * @param array  $filterParams
     *
     * @return string
     */
    public function createSeoUrl(string $baseUrl, array $filterParams): string
    {
        if (isset($this->generatedFilterUrl[$baseUrl])) {
            return $this->generatedFilterUrl[$baseUrl];
        }

        if (empty($filterParams)) {
            return $baseUrl;
        }

        $useSeoFilter = $this->connectSettings->getBoolean('makaira_connect_seofilter');
        if (!$useSeoFilter) {
            $this->generatedFilterUrl[$baseUrl] = $baseUrl;
            return $this->generatedFilterUrl[$baseUrl];
        }

        $path = [];
        foreach ($filterParams as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $path[] = "{$key}_" . $this->encodeFilterValue($item);
                }
            } else {
                $path[] = "{$key}_" . $this->encodeFilterValue($value);
            }
        }
        $filterString = implode('/', $path);

        $parsedUrl = parse_url($baseUrl);

        $path       = rtrim($parsedUrl['path'], '/');
        $pageNumber = '';
        if (preg_match('#(.*)/(\d+)$#', $path, $matches)) {
            $path       = $matches[1];
            $pageNumber = $matches[2] . '/';
        }
        $path = implode('/', [$path, $filterString, $pageNumber]);

        $query = $parsedUrl['query'] ? "?{$parsedUrl['query']}" : "";

        $this->generatedFilterUrl[$baseUrl] = "{$parsedUrl['scheme']}://{$parsedUrl['host']}{$path}{$query}";

        return $this->generatedFilterUrl[$baseUrl];
    }

    /**
     * Encode filter value for use in SEO URLs.
     *
     * @param string $value
     *
     * @return string
     */
    protected function encodeFilterValue(string $value): string
    {
        return urlencode(str_replace('/', '---', $value));
    }

    /**
     * @return array
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function getActiveFilter(): array
    {
        if (null !== $this->activeFilter) {
            return $this->activeFilter;
        }

        $type = $this->mapOxidClass($this->oxidHelper->getCurrentViewClassName());
        $id   = match ($type) {
            'category'     => $this->oxidHelper->getCurrentCategoryId(),
            'manufacturer' => $this->oxidHelper->getCurrentManufacturerId(),
            'search'       => $this->oxidHelper->getCurrentSearchParam(),
            'details'      => $this->oxidHelper->getCurrentArticleId(),
        };


        $request        = $this->oxidHelper->getRequest();
        $requestFilter  = (array) $request->getRequestParameter($this->filterParameterName, []);
        $isFilterAction = (bool) $request->getRequestParameter('isFilterAction');

        if ($isFilterAction || !empty($requestFilter)) {
            $requestFilter = $this->filterRangeValues($requestFilter, $isFilterAction);
            $this->buildCookieFilter($type, $id, $requestFilter);

            $this->activeFilter = $requestFilter;

            return $this->activeFilter;
        }

        $this->loadAggregations();
        if (empty(static::$aggregations)) {
            return [];
        }

        $this->activeFilter = static::$aggregations[$type][$id] ?? [];

        return $this->activeFilter;
    }

    /**
     * @param array $filterParams
     * @param bool  $recalculatePrices
     *
     * @return array
     */
    private function filterRangeValues(array $filterParams, bool $recalculatePrices): array
    {
        foreach ($filterParams as $key => $value) {
            if (false !== ($pos = strrpos($key, '_to'))) {
                $maxKey = substr($key, 0, $pos) . '_rangemax';
                if (isset($filterParams[$maxKey]) && $value === $filterParams[$maxKey]) {
                    unset($filterParams[$key]);
                    continue;
                }
            }
            if (false !== ($pos = strrpos($key, '_from'))) {
                $minKey = substr($key, 0, $pos) . '_rangemin';
                if (isset($filterParams[$minKey]) && $value === $filterParams[$minKey]) {
                    unset($filterParams[$key]);
                }
            }
        }

        $filteredFilterParams = [];
        foreach ($filterParams as $key => $value) {
            if (str_ends_with($key, '_rangemin') || str_ends_with($key, '_rangemax')) {
                continue;
            }
            if ($recalculatePrices) {
                if (str_ends_with($key, '_from_price') || str_ends_with($key, '_to_price')) {
                    $value = $this->currency->fromCurrency($value);
                }
            }
            $filteredFilterParams[$key] = $value;
        }

        return $filteredFilterParams;
    }

    /**
     * @param string $className
     * @param string $id
     * @param array  $requestFilter
     *
     * @return array
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function buildCookieFilter(string $className, string $id, array $requestFilter): array
    {
        $cookieFilter = [];

        $type = $this->mapOxidClass($className);

        $cookieFilter[$type][$id] = $requestFilter;

        $this->cookieHelper->saveMakairaFilterToCookie($cookieFilter);

        return $cookieFilter;
    }

    private function mapOxidClass(string $className): string
    {
        return match ($className) {
            'alist'            => 'category',
            'manufacturerlist' => 'manufacturer',
            default            => $className,
        };
    }

    public function createFilterUrl(string $baseUrl, array $filterParams): string
    {
        if (isset($this->generatedFilterUrl[$baseUrl])) {
            return $this->generatedFilterUrl[$baseUrl];
        }

        $params      = [$this->filterParameterName => $filterParams];
        $filterQuery = http_build_query($params);

        $parsedUrl = parse_url($baseUrl);

        $path = rtrim($parsedUrl['path'], '/') . '/';

        $query = '';
        if ('' !== $parsedUrl['query']) {
            $queryArray = explode('&amp;', $parsedUrl['query']);
            $queryArray = array_filter(
                $queryArray,
                static fn($part) => !str_starts_with($part, 'fnc=redirectmakaira'),
            );
            $query      = implode('&', $queryArray);
        }

        if ('' !== $filterQuery) {
            $query = $query ? "{$query}&{$filterQuery}" : $filterQuery;
        }

        if ('' !== $query) {
            $query = "?{$query}";
        }

        $port = '';
        if (isset($parsedUrl['scheme'], $parsedUrl['port'])) {
            $port = match ("{$parsedUrl['scheme']}-{$parsedUrl['port']}") {
                'http-80', 'https-443' => ":{$parsedUrl['port']}",
                default                => '',
            };
        }

        $this->generatedFilterUrl[$baseUrl] = "{$parsedUrl['scheme']}://{$parsedUrl['host']}{$port}{$path}{$query}";

        return $this->generatedFilterUrl[$baseUrl];
    }

    public function getAggregations(): array
    {
        $this->loadAggregations();

        return static::$aggregations;
    }

    public function getFilterParameterName(): string
    {
        return $this->filterParameterName;
    }

    public function hasAggregation(): bool
    {
        $this->loadAggregations();

        return count(static::$aggregations) > 0;
    }

    /**
     * @param string $type
     * @param string $ident
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function resetAggregation(string $type, string $ident): void
    {
        $this->loadAggregations();

        unset(static::$aggregations[$type][$ident]);

        $this->cookieHelper->saveMakairaFilterToCookie(static::$aggregations);
    }

    /**
     * @param array $aggregations
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function setAggregations(array $aggregations): void
    {
        static::$aggregations = $aggregations;
        $this->cookieHelper->saveMakairaFilterToCookie($aggregations);
    }
}
