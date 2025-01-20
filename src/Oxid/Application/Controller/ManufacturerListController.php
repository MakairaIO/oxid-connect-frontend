<?php

namespace Makaira\OxidConnect\Oxid\Application\Controller;

use Makaira\Constraints;
use Makaira\OxidConnect\Core\RequestHandler;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Service\FilterProvider;
use Makaira\Query;
use OxidEsales\Eshop\Application\Model\ArticleList;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\Manufacturer;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Throwable;

use function array_filter;
use function parse_url;
use function substr;

use const PHP_URL_HOST;
use const VIEW_INDEXSTATE_NOINDEXFOLLOW;

class ManufacturerListController extends ManufacturerListController_parent
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

    public function getAddSeoUrlParams(): string
    {
        $this->cookieHelper->savePageNumberToCookie();

        return parent::getAddSeoUrlParams();
    }

    public function getAddUrlParams(): string
    {
        $this->cookieHelper->savePageNumberToCookie();

        return parent::getAddUrlParams();
    }

    public function getArticleList(): array|ArticleList
    {
        if (null !== $this->_aArticleList) {
            return $this->_aArticleList;
        }

        if (!$this->moduleSettingService->getBoolean('makaira_connect_activate_listing')) {
            return parent::getArticleList();
        }

        $this->_aArticleList = [];

        $manufacturer = $this->getActManufacturer();
        if (!$manufacturer instanceof Manufacturer ||
            !$this->getManufacturerTree() ||
            ($manufacturer->getId() === 'root') ||
            !$manufacturer->getIsVisible()) {
            return $this->_aArticleList;
        }

        try {
            $productList = $this->makairaLoadArticles($manufacturer);
            $this->addTplParam('isMakairaSearchEnabled', true);

            if (count($productList) > 0) {
                $this->_aArticleList = $productList;
            }

            return $this->_aArticleList;
        } catch (Throwable $t) {
            Registry::getLogger()->error((string) $t);
            return parent::getArticleList();
        }
    }

    protected function makairaLoadArticles(Manufacturer $manufacturer): ArticleList
    {
        $requestHandler = ContainerFacade::get(RequestHandler::class);

        $limit   = (int) $this->oxidSettingsService->getConfigParameter('iNrofCatArticles');
        $limit   = $limit ?: 1;
        $offset  = $limit * $this->getRequestPageNr();
        $sorting = $this->getSorting($this->getSortIdent()) ?? [];

        $constraints  = [
            Constraints::SHOP         => $this->oxidSettingsService->getShopId(),
            Constraints::LANGUAGE     => $this->oxidSettingsService->getLanguageAbbreviation(),
            Constraints::USE_STOCK    => $this->oxidSettingsService->get('blUseStock'),
            Constraints::MANUFACTURER => $manufacturer->getId(),
        ];
        $aggregations = array_filter(
            $this->getAggregationProvider()->getActiveFilter(),
            static fn(mixed $value) => $value || ("0" === $value),
        );

        $query = new Query([
            'isSearch'     => false,
            'constraints'  => $constraints,
            'aggregations' => $aggregations,
            'sorting'      => $requestHandler->sanitizeSorting($sorting),
            'count'        => $limit,
            'offset'       => $offset,
        ]);

        $productList = $requestHandler->getProductsFromMakaira($query);

        $this->_iAllArtCnt = $requestHandler->getProductCount($query);
        $this->_iCntPages  = round($this->_iAllArtCnt / $limit + 0.49);

        return $productList;
    }

    public function getAggregationProvider(): FilterProvider
    {
        if (static::$aggregationProvider === null) {
            static::$aggregationProvider = ContainerFacade::get(FilterProvider::class);
        }

        return static::$aggregationProvider;
    }

    public function getLinkWithCategory(string $url = ''): string
    {
        $manufacturer = $this->getActManufacturer();
        if (!$manufacturer instanceof Manufacturer) {
            return $url;
        }

        $result   = $manufacturer->getLink();
        $category = $this->getActCategory();
        if ($category instanceof Category && !$url) {
            $url = $category->getUrl();
        }

        if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            $url = parse_url($url, PHP_URL_HOST);
            $url = substr($url, 1);
        }

        return $result . $url;
    }

    public function noIndex()
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
        $this->oxidSettingsService->redirect($redirectUrl);
    }

    public function resetMakairaFilter(): void
    {
        $this->cookieHelper->setCookie('manufacturer', $this->getManufacturerId());
    }

    protected function addPageNrParam($sUrl, $iPage, $iLang = null)
    {
        $baseLink = parent::addPageNrParam($sUrl, $iPage, $iLang);

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
