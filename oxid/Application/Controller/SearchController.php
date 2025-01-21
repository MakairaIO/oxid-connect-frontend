<?php

namespace Makaira\OxidConnect\Oxid\Application\Controller;

use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Makaira\Connect\Exception as MakairaException;
use Makaira\Connect\Exceptions\UnexpectedValueException as MakairayUnexpectedValueException;
use Makaira\Constraints;
use Makaira\OxidConnect\Core\RequestHandler;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Helper\OxidSettings;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Service\FilterProvider;
use Makaira\Query;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Throwable;

use function max;
use function oxNew;
use function rawurldecode;
use function startProfile;
use function stopProfile;
use function strtolower;

class SearchController extends SearchController_parent
{
    private static ?FilterProvider $aggregationProvider = null;

    private ModuleSettingServiceInterface $moduleSettingService;

    private OxidSettingsInterface $oxidSettingsService;

    private Cookies $cookieHelper;

    public function __construct()
    {
        $this->moduleSettingService = ContainerFacade::get(ModuleSettings::class);
        $this->oxidSettingsService  = ContainerFacade::get(OxidSettingsInterface::class);
        $this->cookieHelper         = ContainerFacade::get(Cookies::class);

        parent::__construct();
    }

    public function getAddUrlParams(): string
    {
        $this->cookieHelper->savePageNumberToCookie();

        return parent::getAddUrlParams();
    }

    public function getSortIdent(): string
    {
        return 'search';
    }

    /**
     * @return false|void
     */
    public function init()
    {
        if (!$this->moduleSettingService->getBoolean('makaira_connect_activate_search')) {
            return parent::init();
        }

        startProfile('searchView');

        try {
            // Skip base initialisation, because it will trigger the database search!
            FrontendController::init();
            if ('resetmakairafilter' === strtolower($this->getFncName())) {
                $this->getAggregationProvider()->resetAggregation(
                    'search',
                    $this->getViewConfig()->getActSearchParam(),
                );
            }

            if ('redirectmakairafilter' === strtolower($this->getFncName())) {
                $this->redirectMakairaFilter();
            }

            $this->makairaInitSearch();
            $this->addTplParam('isMakairaSearchEnabled', true);
        } catch (Throwable $t) {
            Registry::getLogger()->error((string) $t);
            parent::init();
        }

        stopProfile('searchView');
    }

    public function getAggregationProvider(): FilterProvider
    {
        if (static::$aggregationProvider === null) {
            static::$aggregationProvider = ContainerFacade::get(FilterProvider::class);
        }

        return static::$aggregationProvider;
    }

    public function redirectMakairaFilter(): void
    {
        $redirectUrl = $this->getAggregationProvider()->createRedirectUrl($this->getActiveCategory()->getLink());
        ContainerFacade::get(OxidSettings::class)->redirect($redirectUrl);
    }

    /**
     * @return void
     * @throws DBALDriverException
     * @throws DBALException
     * @throws JsonException
     * @throws MakairaException
     * @throws MakairayUnexpectedValueException
     * @throws LanguageNotFoundException
     */
    protected function makairaInitSearch(): void
    {
        $oxidRequest = Registry::getRequest();
        $constraints = [
            Constraints::SHOP         => $this->oxidSettingsService->getShopId(),
            Constraints::LANGUAGE     => $this->oxidSettingsService->getLanguageAbbreviation(),
            Constraints::USE_STOCK    => $this->oxidSettingsService->get('blUseStock'),
            Constraints::CATEGORY     => rawurldecode($oxidRequest->getRequestParameter('searchcnid')),
            Constraints::MANUFACTURER => rawurldecode($oxidRequest->getRequestParameter('searchmanufacturer')),
            Constraints::VENDOR       => rawurldecode($oxidRequest->getRequestParameter('searchvendor')),
        ];

        $productCount = $this->oxidSettingsService->get('iNrofCatArticles');
        $productCount = (int) ($productCount ?? 10);

        $requestHandler = oxNew(RequestHandler::class);

        $query = new Query([
            'searchPhrase' => $oxidRequest->getRequestParameter('searchparam'),
            'isSearch'     => true,
            'constraints'  => array_filter($constraints),
            'aggregations' => $this->getAggregationProvider()->getAggregations(),
            'sorting'      => $requestHandler->sanitizeSorting((array) $this->getSorting('search')),
            'count'        => $productCount,
            'offset'       => $productCount * max(0, (int) $oxidRequest->getRequestParameter('pgNr', 0)),
        ]);

        $this->_blEmptySearch = false;
        if ($this->isSearchEmpty($query)) {
            $this->_aArticleList  = null;
            $this->_blEmptySearch = true;

            return;
        }

        $filterProvider = ContainerFacade::get(FilterProvider::class);
        $productList    = $requestHandler->getProductsFromMakaira($query);
//        $filter = $filterProvider->getAggregations();
        $additionalResults = $requestHandler->getAdditionalResults();

        $this->checkForSearchRedirect($additionalResults);

        $isPaginated = $oxidRequest->getRequestParameter('pgNr') !== null;
        if (!$isPaginated && empty($filterProvider) && count($productList) === 1) {
            Registry::getUtils()->redirect($productList->current()?->getLink(), false, 302);
        }

        $this->_aArticleList = null;
        $this->_iAllArtCnt   = 0;
        if (count($productList) > 0) {
            $this->_iAllArtCnt   = $requestHandler->getProductCount($query);
            $this->_aArticleList = $productList;
        }

        $this->_iCntPages = round($this->_iAllArtCnt / $productCount + 0.49);

        foreach ((array) $additionalResults as $type => $results) {
            $this->_aViewData["{$type}_results"] = $results;
        }

        $this->modifyViewData($this->_aViewData, $requestHandler);
    }

    public function getAggregations(): array
    {
        return $this->getAggregationProvider()->getAggregations();
    }

    protected function isSearchEmpty(Query $query): bool
    {
        return empty($query->searchPhrase) && empty($query->aggregations);
    }

    private function checkForSearchRedirect(array $additionalResults): void
    {
        $redirects = isset($additionalResults['searchredirect']) ? $additionalResults['searchredirect']->items : [];

        if (count($redirects) > 0) {
            $targetUrl = $redirects[0]->fields['targetUrl'];

            if ($targetUrl) {
                Registry::getUtils()->redirect($targetUrl, false, 302);
            }
        }
    }

    protected function modifyViewData(&$viewData, $requestHandler): void
    {
    }
}
