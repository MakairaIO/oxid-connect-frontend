<?php

namespace Makaira\OxidConnect\Oxid\Application\Controller;

use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Makaira\Connect\Exception;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Constraints;
use Makaira\OxidConnect\Core\RequestHandler;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Helper\OxidSettings;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Service\FilterProvider;
use Makaira\OxidConnect\Utils\CategoryInheritance;
use Makaira\Query;
use OxidEsales\Eshop\Application\Model\ArticleList;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_filter;
use function oxNew;
use function round;
use function str_starts_with;

use const VIEW_INDEXSTATE_NOINDEXFOLLOW;

class ArticleListController extends ArticleListController_parent
{
    private const LOAD_STATE_INITIAL = 0;

    private const LOAD_STATE_MAKAIRA_SUCCESS = 1;

    private const LOAD_STATE_MAKAIRA_FAILURE = 2;


    private static ?FilterProvider $aggregationProvider = null;

    private ModuleSettingServiceInterface $moduleSettingService;

    private OxidSettingsInterface $oxidSettingsService;

    private Cookies $cookieHelper;

    private int $articleListLoadState = self::LOAD_STATE_INITIAL;

    private bool $makairaEnabled = false;

    private ?ArticleList $makairaSearchResult = null;

    public function __construct()
    {
        $this->moduleSettingService = ContainerFacade::get(ModuleSettings::class);
        $this->oxidSettingsService  = ContainerFacade::get(OxidSettingsInterface::class);
        $this->cookieHelper         = ContainerFacade::get(Cookies::class);

        parent::__construct();
    }

    public function getAddSeoUrlParams(): string
    {
        $this->cookieHelper->savePageNumberToCookie();

        return parent::getAddSeoUrlParams();
    }

    public function getAddUrlParams(): ?string
    {
        $this->cookieHelper->savePageNumberToCookie();

        return parent::getAddUrlParams();
    }

    public function getArticleList(): ArticleList|array|null
    {
        if (null !== $this->_aArticleList || $this->articleListLoadState !== self::LOAD_STATE_INITIAL) {
            return $this->_aArticleList;
        }

        if ($this->articleListLoadState === self::LOAD_STATE_MAKAIRA_FAILURE ||
            !$this->moduleSettingService->getBoolean('makaira_connect_activate_listing')
        ) {
            return $this->_aArticleList = parent::getArticleList();
        }

        $this->articleListLoadState = self::LOAD_STATE_MAKAIRA_FAILURE;

        if ($oCategory = $this->getActiveCategory()) {
            try {
                // load products from makaira
                $aArticleList = $this->makairaLoadArticles($oCategory);
                if (count($aArticleList)) {
                    $this->_aArticleList = $aArticleList;
                }
                $this->articleListLoadState = self::LOAD_STATE_MAKAIRA_SUCCESS;
                $this->makairaEnabled       = true;
            } catch (Throwable $t) {
                // Ignore errors, but put them into the log file.
                ContainerFacade::get(LoggerInterface::class)->error((string) $t);

                return $this->_aArticleList = parent::getArticleList();
            }
        }

        return $this->_aArticleList;
    }

    /**
     * @param Category $category
     *
     * @return ArticleList
     * @throws DBALDriverException
     * @throws DBALException
     * @throws LanguageNotFoundException
     * @throws JsonException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    protected function makairaLoadArticles(Category $category): ArticleList
    {
        if ($category->isPriceCategory()) {
            return $this->loadArticles($category);
        }

        if ($this->makairaSearchResult instanceof ArticleList) {
            return $this->makairaSearchResult;
        }

        $requestHandler = oxNew(RequestHandler::class);

        $limit   = (int) $this->oxidSettingsService->getConfigParameter('iNrofCatArticles');
        $limit   = $limit ?: 1;
        $offset  = $limit * $this->getRequestPageNr();
        $sorting = $this->getSorting($this->getSortIdent()) ?? [];

        $categoryInheritance = ContainerFacade::get(CategoryInheritance::class);

        $query = new Query();

        $query->isSearch = false;

        $query->constraints = array_filter(
            [
                Constraints::SHOP      => $this->oxidSettingsService->getShopId(),
                Constraints::LANGUAGE  => $this->oxidSettingsService->getLanguageAbbreviation(),
                Constraints::USE_STOCK => $this->oxidSettingsService->getConfigParameter('blUseStock'),
                Constraints::CATEGORY  => $categoryInheritance->buildCategoryInheritance($category->getId()),
            ],
        );

        $query->aggregations = array_filter(
            $this->getAggregationProvider()->getActiveFilter(),
            static fn(mixed $value) => $value || ("0" === $value),
        );

        $query->sorting = $requestHandler->sanitizeSorting($sorting);

        $query->count  = $limit;
        $query->offset = $offset;

        $oArtList = $requestHandler->getProductsFromMakaira($query);

        $this->_iAllArtCnt = $requestHandler->getProductCount($query);

        $this->_iCntPages = round($this->_iAllArtCnt / $limit + 0.49);

        $this->makairaSearchResult = $oArtList;

        $this->modifyViewData($requestHandler);

        return $oArtList;
    }

    public function getAggregationProvider(): FilterProvider
    {
        if (static::$aggregationProvider === null) {
            static::$aggregationProvider = ContainerFacade::get(FilterProvider::class);
        }

        return static::$aggregationProvider;
    }

    protected function modifyViewData($requestHelper): void
    {
    }

    public function getAttributes(): bool|array|null
    {
        if (!$this->moduleSettingService->getBoolean('makaira_connect_activate_listing')) {
            return parent::getAttributes();
        }

        return false;
    }

    public function getLinkWithCategory(bool $useCategory = false): string
    {
        $result   = $this->getActiveCategory()->getLink();
        $request  = Registry::getRequest();
        $category = oxNew(Category::class);
        $url      = '';

        if (!$useCategory && $category->load($request->getRequestParameter('marm_cat'))) {
            $url = $category->getLink();
        }

        if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            $url = parse_url($url, PHP_URL_HOST);
            $url = substr($url, 1);
        }

        return $result . $url;
    }

    public function isMakairaEnabled(): bool
    {
        return $this->makairaEnabled;
    }

    public function noIndex(): int
    {
        $viewState = parent::noIndex();

        $aggregationProvider = $this->getAggregationProvider();
        if ($aggregationProvider->hasAggregation()) {
            return $this->_iViewIndexState = VIEW_INDEXSTATE_NOINDEXFOLLOW;
        }

        return $viewState;
    }

    public function redirectMakairaFilter(): void
    {
        $redirectUrl = $this->getAggregationProvider()->createRedirectUrl($this->getActiveCategory()->getLink());
        ContainerFacade::get(OxidSettings::class)->redirect($redirectUrl);
    }

    protected function addPageNrParam($url, $currentPage, $languageId = null): string
    {
        $baseLink = parent::addPageNrParam($url, $currentPage, $languageId);

        if (!Registry::getUtils()->seoIsActive()) {
            return $baseLink;
        }

        $aggregationProvider = $this->getAggregationProvider();
        if ($aggregationProvider->hasAggregation()) {
            return $baseLink;
        }

        return $aggregationProvider->createSeoUrl($baseLink, $aggregationProvider->getAggregations());
    }

    public function getAggregations(): array
    {
        return $this->getAggregationProvider()->getAggregations();
    }
}
