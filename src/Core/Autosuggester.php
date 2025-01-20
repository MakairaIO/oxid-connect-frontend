<?php

namespace Makaira\OxidConnect\Core;

use DateTimeImmutable;
use JsonException;
use Makaira\Connect\Exception;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Constraints;
use Makaira\OxidConnect\Exception\ModelNotFoundException;
use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\OxidModels;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Personalization\AbstractPersonalization;
use Makaira\OxidConnect\Personalization\Econda;
use Makaira\OxidConnect\Personalization\Odooscope;
use Makaira\OxidConnect\Service\SearchHandler;
use Makaira\OxidConnect\Utils\OperationalIntelligence;
use Makaira\Query;
use Makaira\Result;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

/**
 * Class makaira_connect_autosuggester
 */
class Autosuggester
{
    public function __construct(
        private Language $language,
        private OperationalIntelligence $operationalIntelligence,
        private SearchHandler $searchHandler,
        private OxidSettingsInterface $oxidHelper,
        private ModuleSettingServiceInterface $connectSettings,
        private Cookies $cookies,
        private OxidModels $oxidModels,
    ) {
    }

    /**
     * Search for search term and build json response
     *
     * @param string $searchPhrase
     *
     * @return array
     * @throws JsonException
     * @throws Exception
     * @throws UnexpectedValueException
     * @SuppressWarnings(CyclomaticComplexity)
     * @SuppressWarnings(NPathComplexity)
     * @SuppressWarnings(ExcessiveMethodLength)
     */
    public function search(string $searchPhrase = ""): array
    {
        $query                     = new Query();
        $query->enableAggregations = false;
        $query->isSearch           = true;
        $query->searchPhrase       = $searchPhrase;
        $query->count              = 7;
        $query->fields             = $this->getFieldsForResults();

        $query->constraints = array_filter(
            [
                Constraints::SHOP      => $this->oxidHelper->getShopId(),
                Constraints::LANGUAGE  => $this->oxidHelper->getLanguageAbbreviation(),
                Constraints::USE_STOCK => $this->oxidHelper->get('blUseStock'),
            ],
        );

        $personalization = null;
        if (isset($_COOKIE['mak_econda_session']) && $this->connectSettings->getBoolean('makaira_connect_use_econda')) {
            $personalization = new Econda(
                json_decode(
                    $_COOKIE['mak_econda_session'],
                    false,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );
        }

        if ($this->connectSettings->getBoolean('makaira_connect_use_odoscope')) {
            $token  = $this->connectSettings->getString('makaira_connect_odoscope_token');
            $siteId = $this->connectSettings->getString('makaira_connect_odoscope_siteid');

            $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $userIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $userIp = preg_replace('/,.*$/', '', $userIp);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $userIp = $_SERVER['HTTP_CLIENT_IP'];
            }

            if ($userIp) {
                $userIp = preg_replace('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', '$1.$2.*.*', $userIp);
            } else {
                $userIp = '';
            }

            $personalization = new Odooscope(
                $token,
                [
                    'siteid' => $siteId,
                    'uip'    => $userIp,
                    'uas'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ref'    => $_SERVER['HTTP_REFERER'] ?? '',
                ],
            );
        }

        if ($personalization instanceof AbstractPersonalization) {
            $query->constraints[Constraints::PERSONALIZATION_TYPE] = $personalization->getType();
            $query->constraints[Constraints::PERSONALIZATION_DATA] = $personalization->getData();
        }

        $this->operationalIntelligence->apply($query);

        // Hook for request modification
        $this->modifyRequest($query);

        $result = $this->searchHandler->search($query);

        if (
            $personalization instanceof Odooscope &&
            isset($this->result['personalization']['oscCookie'])
        ) {
            $cookieValue = $this->result['personalization']['oscCookie'];
            $this->cookies->setCookie(
                $personalization->getCookieName(),
                $cookieValue,
                (new DateTimeImmutable('now + 1 day'))->getTimestamp(),
            );
        }

        // Hook for result modification
        $this->afterSearchRequest($result);

        // get product results
        $aProducts = [];
        foreach ($result['product']->items as $document) {
            $aProducts[] = $this->loadProductItem($document);
        }
        // filter out empty values
        $aProducts = array_filter($aProducts);

        // get category results
        $aCategories = [];
        if ($result['category']) {
            foreach ($result['category']->items as $document) {
                $aCategories[] = $this->prepareCategoryItem($document);
            }
        }
        // filter out empty values
        $aCategories = array_filter($aCategories);

        // get manufacturer results
        $aManufacturers = [];
        if ($result['manufacturer']) {
            foreach ($result['manufacturer']->items as $document) {
                $aManufacturers[] = $this->prepareManufacturerItem($document);
            }
        }
        // filter out empty values
        $aManufacturers = array_filter($aManufacturers);

        // get searchable links results
        $aLinks = [];
        if ($result['links']) {
            foreach ($result['links']->items as $document) {
                $aLinks[] = $this->prepareLinkItem($document);
            }
        }
        // filter out empty values
        $aLinks = array_filter($aLinks);

        // get suggestion results
        $aSuggestions = [];
        if ($result['suggestion']) {
            foreach ($result['suggestion']->items as $document) {
                $aSuggestions[] = $this->prepareSuggestionItem($document);
            }
        }
        // filter out empty values
        $aSuggestions = array_filter($aSuggestions);

        return [
            'count'         => count($aProducts),
            'products'      => $aProducts,
            'productCount'  => $result['product']->total,
            'categories'    => $aCategories,
            'manufacturers' => $aManufacturers,
            'links'         => $aLinks,
            'suggestions'   => $aSuggestions,
        ];
    }

    /**
     * Getter method for resulting fields
     *
     * @return array
     */
    protected function getFieldsForResults(): array
    {
        return ['id', 'title', 'OXVARSELECT'];
    }

    /**
     * @param Query $query
     */
    public function modifyRequest(Query $query): void
    {
    }

    /**
     * @param array<Result> $result
     */
    public function afterSearchRequest(array &$result): void
    {
    }

    /**
     * Prepare the data based on an oxArticleObject
     *
     * @param object $doc
     *
     * @return array
     */
    protected function loadProductItem(object $doc): array
    {
        try {
            $product = $this->oxidModels->getArticle($doc->id);

            return $this->prepareProductItem($doc, $product);
        } catch (ModelNotFoundException) {
            return [];
        }
    }

    /**
     * data preparation hook
     *
     * @param object  $doc
     * @param Article $product
     *
     * @return array
     */
    protected function prepareProductItem(object $doc, Article $product): array
    {
        $title = $doc->fields['title'];
        if (!empty($doc->fields['oxvarselect'])) {
            $title .= ' | ' . $doc->fields['oxvarselect'];
        }

        return [
            'label'     => $title,
            'value'     => $title,
            'link'      => $product->getMainLink(),
            'image'     => $product->getIconUrl(1),
            'thumbnail' => $product->getThumbnailUrl(),
            'price'     => $this->preparePrice($product->getPrice()),
            'uvp'       => $this->preparePrice($product->getTPrice()),
            'type'      => 'product',
            'category'  => $this->language->translateString("MAKAIRA_CONNECT_AUTOSUGGEST_CATEGORY_PRODUCTS"),
        ];
    }

    /**
     * Helper method to format prices for auto-suggest
     */
    protected function preparePrice(?Price $price): array
    {
        if ($price instanceof Price) {
            return [
                'brutto' => number_format($price->getBruttoPrice(), 2, ',', ''),
                'netto'  => number_format($price->getNettoPrice(), 2, ',', ''),
            ];
        }

        return ['brutto' => 0, 'netto' => 0];
    }

    protected function prepareCategoryItem(object $doc): array
    {
        if (empty($doc->fields['category_title'])) {
            return [];
        }

        try {
            $category = $this->oxidModels->getCategory($doc->id);

            return [
                'label' => $doc->fields['category_title'],
                'link'  => $category->getLink(),
            ];
        } catch (ModelNotFoundException) {
            return [];
        }
    }

    protected function prepareManufacturerItem(object $doc): array
    {
        if (empty($doc->fields['manufacturer_title'])) {
            return [];
        }

        try {
            $manufacturer = $this->oxidModels->getManufacturer($doc->id);

            return [
                'label' => $doc->fields['manufacturer_title'],
                'link'  => $manufacturer->getLink(),
                'icon'  => $manufacturer->getIconUrl(),
            ];
        } catch (ModelNotFoundException) {
            return [];
        }
    }

    protected function prepareLinkItem(object $doc): array
    {
        if (empty($doc->fields['title'])) {
            return [];
        }

        return [
            'label' => $doc->fields['title'],
            'link'  => $doc->fields['url'],
        ];
    }

    protected function prepareSuggestionItem(object $doc): array
    {
        if (empty($doc->fields['title'])) {
            return [];
        }

        return [
            'label' => $doc->fields['title'],
        ];
    }
}
