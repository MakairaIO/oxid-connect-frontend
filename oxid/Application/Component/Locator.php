<?php

namespace Makaira\OxidConnect\Oxid\Application\Component;

use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Makaira\Constraints;
use Makaira\OxidConnect\Core\RequestHandler;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Service\FilterProvider;
use Makaira\Query;
use OxidEsales\Eshop\Application\Controller\MoreDetailsController;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\CategoryList;
use OxidEsales\Eshop\Application\Model\Manufacturer;
use OxidEsales\Eshop\Application\Model\SeoEncoderCategory;
use OxidEsales\Eshop\Application\Model\SeoEncoderManufacturer;
use OxidEsales\Eshop\Core\Contract\IUrl;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use Throwable;

use function array_merge;
use function rawurldecode;
use function rawurlencode;

class Locator extends Locator_parent
{
    private const URL_SEARCH_PARAMETER = ['searchcnid', 'searchvendor', 'searchmanufacturer'];

    /**
     * @param $oCurrArticle
     * @param $oLocatorTarget
     *
     * @return void
     * @throws DBALDriverException
     * @throws DBALException
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function setLocatorData($oCurrArticle, $oLocatorTarget): void
    {
        $oxidSettings   = ContainerFacade::get(OxidSettingsInterface::class);
        $moduleSettings = ContainerFacade::get(ModuleSettings::class);
        $filterProvider = ContainerFacade::get(FilterProvider::class);
        $cookiesHelper  = ContainerFacade::get(Cookies::class);

        $oxidRequest = $oxidSettings->getRequest();

        if ($oLocatorTarget instanceof MoreDetailsController) {
            if ('list' === $this->_sType) {
                $categoryId     = $oxidSettings->getCurrentCategoryId();
                $activeCategory = $oLocatorTarget->getActiveCategory();

                if ($activeCategory && ($categoryId !== $activeCategory->getId()) && $this->useCategoryInheritance()) {
                    $categoryTree = oxNew(CategoryList::class);
                    $categoryTree->buildTree($categoryId);
                    $oLocatorTarget->setCategoryTree($categoryTree);
                }

                if ($categoryTree = $oLocatorTarget->getCategoryTree()) {
                    $oLocatorTarget->setCatTreePath($categoryTree->getPath());
                }
            }

            if ('manufacturer' === $this->_sType && $manufacturerTree = $oLocatorTarget->getManufacturerTree()) {
                $oLocatorTarget->setCatTreePath($manufacturerTree);
            }

            return;
        }

        $requestHandler = oxNew(RequestHandler::class);
        $page           = $requestHandler->getPageNumber($oLocatorTarget->getActPage());
        $addParams      = '';
        $query          = new Query(['isSearch' => false]);

        $constraints = [
            Constraints::SHOP      => $oxidSettings->getShopId(),
            Constraints::LANGUAGE  => $oxidSettings->getLanguageAbbreviation(),
            Constraints::USE_STOCK => $oxidSettings->get('blUseStock'),
        ];

        switch ($this->_sType) {
            case 'list':
                if (!$moduleSettings->getBoolean('makaira_connect_activate_listing')) {
                    parent::setLocatorData($oCurrArticle, $oLocatorTarget);
                    return;
                }

                $locatorObject = $oLocatorTarget->getActiveCategory();
                if (!$locatorObject) {
                    return;
                }
                $constraints[Constraints::CATEGORY] = $this->getInheritedCategoryIds($locatorObject);
                break;
            case 'search':
                if (!$moduleSettings->getBoolean('makaira_connect_activate_search')) {
                    parent::setLocatorData($oCurrArticle, $oLocatorTarget);
                    return;
                }
                $query->isSearch     = true;
                $query->searchPhrase = $oxidRequest->getRequestParameter('searchparam', true);
                $locatorObject       = $oLocatorTarget->getActSearch();
                if (!$locatorObject) {
                    return;
                }

                $addParams = $this->getSearchAddParams();
                break;
            case 'manufacturer':
                if (!$moduleSettings->getBoolean('makaira_connect_activate_listing')) {
                    parent::setLocatorData($oCurrArticle, $oLocatorTarget);
                    return;
                }
                $locatorObject = $oLocatorTarget->getActManufacturer();
                if (!$locatorObject) {
                    return;
                }
                $constraints[Constraints::MANUFACTURER] = $locatorObject->getId();
                break;
            default:
                parent::setLocatorData($oCurrArticle, $oLocatorTarget);
                return;
        }

        $query->enableAggregations = false;

        $sorting = [];
        if ($oLocatorTarget->showSorting()) {
            $sorting = (array) $oLocatorTarget->getSorting($oLocatorTarget->getSortIdent());
        }

        $query->sorting = $requestHandler->sanitizeSorting($sorting);

        $productsPerPage = (int) $oxidSettings->get('iNrofCatArticles');
        $limit           = $productsPerPage + 1;
        $offset          = 0;

        if ($page > 0) {
            $offset = ($page * $productsPerPage) - 1;
            $limit++;
        }

        $query->count        = $limit;
        $query->offset       = $offset;
        $query->constraints  = $constraints;
        $query->aggregations = $filterProvider->getActiveFilter();

        try {
            $idList = $requestHandler->getProductsFromMakaira($query);

            $locatorObject->iCntOfProd = $requestHandler->getProductCount($query);

            $position = $this->getProductPos($oCurrArticle, $idList, $oLocatorTarget);

            if ($page > 0) {
                $position--;
                $offset++;
            }

            if (1 > $position) {
                $cookiesHelper->deletePageNumberCookie();
                $page          = 0;
                $offset        = 0;
                $query->count  = $productsPerPage + 1;
                $query->offset = $offset;
                $idList        = $requestHandler->getProductsFromMakaira($query);
                $position      = $this->getProductPos($oCurrArticle, $idList, $oLocatorTarget);
            }

            $seoActive = $oxidSettings->seoIsActive();

            if ($locatorObject instanceof Category) {
                $this->setCategoryToListLink($locatorObject, $page, $seoActive);
            }

            if ($locatorObject instanceof Manufacturer) {
                if (!$seoActive) {
                    $addParams = sprintf('listtype=manufacturer&amp;mnid=%s', rawurlencode($locatorObject->getId()));
                }
                $this->setManufacturerToListLink($locatorObject, $page, $seoActive);
            }

            if ('search' === $this->_sType) {
                $pageNr                    = $this->getPageNumber($page);
                $params                    = $pageNr . ($addParams ? '&amp;' : '') . $addParams;
                $locatorObject->toListLink = $this->makeLink($locatorObject->link, $params);
            }

            $locatorObject->iProductPos = $position + $offset;

            $this->setPrevNextLinks($locatorObject, $page, $productsPerPage, $position, $addParams);

            $oLocatorTarget->setActiveCategory($locatorObject);
        } catch (Throwable $e) {
            Registry::getLogger()->error((string) $e);
        }
    }

    private function useCategoryInheritance(): bool
    {
        return ContainerFacade::get(ModuleSettings::class)->getBoolean('makaira_connect_category_inheritance');
    }

    /**
     * @param Category $category
     *
     * @return array
     * @throws DBALDriverException
     * @throws DBALException
     */
    private function getInheritedCategoryIds(Category $category): array
    {
        $categoryIds = (array) $category->getId();

        if ($this->useCategoryInheritance()) {
            $queryBuilderFactory = ContainerFacade::get(QueryBuilderFactoryInterface::class);

            $qb = $queryBuilderFactory->create();
            $qb
                ->select('OXID')
                ->from('oxcategories')
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->eq('OXROOTID', ':rootId'),
                        $qb->expr()->gt('OXLEFT', ':left'),
                        $qb->expr()->lt('OXRIGHT', ':right'),
                    ),
                )
                ->setParameter('rootId', $category->oxcategories__oxrootid->value)
                ->setParameter('left', $category->oxcategories__oxleft->value)
                ->setParameter('right', $category->oxcategories__oxright->value);
            $result      = $qb->execute();
            $categoryIds = array_merge($categoryIds, $result->fetchFirstColumn());
        }

        return $categoryIds;
    }

    /**
     * @return string
     */
    private function getSearchAddParams(): string
    {
        $oxidRequest = ContainerFacade::get(OxidSettingsInterface::class)->getRequest();

        $urlParameters = [
            sprintf('searchparam=%s', rawurlencode($oxidRequest->getRequestParameter('searchparam'))),
            'listtype=search',
        ];

        foreach (self::URL_SEARCH_PARAMETER as $param) {
            if ($value = $oxidRequest->getRequestParameter($param)) {
                $urlParameters[] = sprintf('%s=%s', $param, rawurldecode($value));
            }
        }

        return implode('&amp;', $urlParameters);
    }

    /**
     * @param Category $category
     * @param int      $page
     * @param bool     $seoActive
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    private function setCategoryToListLink(Category $category, int $page, bool $seoActive): void
    {
        $this->setToListLink(
            [ContainerFacade::get(SeoEncoderCategory::class), 'getCategoryPageUrl'],
            $category,
            $page,
            $seoActive,
        );
    }

    /**
     * @param callable $seoEncode
     * @param IUrl     $oxidModel
     * @param int      $page
     * @param bool     $seoActive
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    private function setToListLink(callable $seoEncode, IUrl $oxidModel, int $page, bool $seoActive): void
    {
        if (!$page || !$seoActive) {
            $pageUrl = $this->makeLink($oxidModel->getLink(), $this->getPageNumber($page));
        }

        if ($seoActive) {
            if ($page) {
                $pageUrl = $seoEncode($oxidModel, $page);
            }

            $filterProvider = ContainerFacade::get(FilterProvider::class);
            $pageUrl        = $filterProvider->createSeoUrl($pageUrl, $filterProvider->getActiveFilter());
        }

        $oxidModel->toListLink = $pageUrl;
    }

    /**
     * @param Manufacturer $manufacturer
     * @param int          $page
     * @param bool         $seoActive
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    private function setManufacturerToListLink(Manufacturer $manufacturer, int $page, bool $seoActive): void
    {
        $this->setToListLink(
            [ContainerFacade::get(SeoEncoderManufacturer::class), 'getManufacturerPageUrl'],
            $manufacturer,
            $page,
            $seoActive,
        );
    }

    /**
     * @param object $locatorObject
     * @param int    $page
     * @param int    $categoryProductCount
     * @param int    $position
     * @param string $searchParams
     *
     * @return void
     */
    private function setPrevNextLinks(
        object $locatorObject,
        int $page,
        int $categoryProductCount,
        int $position,
        string $searchParams = '',
    ): void {
        $locatorObject->nextProductLink = null;
        $locatorObject->prevProductLink = null;

        $pageNr     = $this->getPageNumber($page);
        $pageNrPrev = $pageNrNext = $pageNr;

        if ($position === $categoryProductCount) {
            $pageNrNext = $this->getPageNumber($page + 1);
        }

        if ($position === 1) {
            $pageNrPrev = $this->getPageNumber($page - 1);
        }

        if ($searchParams) {
            $pageNrNext .= ($pageNrNext ? '&amp;' : '') . $searchParams;
            $pageNrPrev .= ($pageNrPrev ? '&amp;' : '') . $searchParams;
        }

        if ($this->_oNextProduct) {
            $locatorObject->nextProductLink = $this->makeLink($this->_oNextProduct->getLink(), $pageNrNext);
        }

        if ($this->_oBackProduct) {
            $locatorObject->prevProductLink = $this->makeLink($this->_oBackProduct->getLink(), $pageNrPrev);
        }
    }
}
