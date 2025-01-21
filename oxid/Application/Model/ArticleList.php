<?php

namespace Makaira\OxidConnect\Oxid\Application\Model;

use JsonException;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\FeatureNotAvailableException;
use Makaira\Connect\Exceptions\UnexpectedValueException as ConnectUnexpectedValueException;
use Makaira\Constraints;
use Makaira\OxidConnect\Exception\ModelNotFoundException;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Helper\OxidModels;
use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use Makaira\OxidConnect\Service\RecommendationHandler;
use Makaira\RecommendationQuery;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function microtime;

class ArticleList extends ArticleList_parent
{
    public const RECOMMENDATION_TYPE_CROSS_SELLING    = 'cross-selling';
    public const RECOMMENDATION_TYPE_ACCESSORIES      = 'accessories';
    public const RECOMMENDATION_TYPE_SIMILAR_PRODUCTS = 'similar-products';

    private static array $productCache = [];

    private ModuleSettingServiceInterface $moduleSettingService;

    private OxidSettingsInterface $oxidSettingsService;

    private OxidModels $oxidModels;

    public function __construct($sObjectName = null)
    {
        $this->moduleSettingService = ContainerFacade::get(ModuleSettings::class);
        $this->oxidSettingsService  = ContainerFacade::get(OxidSettingsInterface::class);
        $this->oxidModels           = ContainerFacade::get(OxidModels::class);

        parent::__construct($sObjectName);
    }

    public function loadArticleAccessoires($sArticleId): void
    {
        $recommendationId = (string) $this->moduleSettingService->getString('makaira_recommendation_accessory_id');

        if (empty($recommendationId) ||
            !$this->moduleSettingService->getBoolean('makaira_recommendation_accessories')
        ) {
            parent::loadArticleAccessoires($sArticleId);

            return;
        }

        try {
            $this->fetchFromMakaira(
                self::RECOMMENDATION_TYPE_ACCESSORIES,
                $recommendationId,
                $sArticleId,
                $this->oxidSettingsService->get('iNrofCrossellArticles'),
            );
        } catch (FeatureNotAvailableException $e) {
            $this->moduleSettingService->setBoolean('makaira_recommendation_accessories', false);
            parent::loadArticleCrossSell($sArticleId);
        } catch (Throwable $e) {
            ContainerFacade::get(LoggerInterface::class)->error((string) $e);
            parent::loadArticleCrossSell($sArticleId);
        }
    }

    public function loadArticleCrossSell($sArticleId): void
    {
        $recommendationId = (string) $this->moduleSettingService->getString('makaira_recommendation_cross_selling_id');
        if (
            empty($recommendationId) ||
            !$this->moduleSettingService->getBoolean('makaira_recommendation_cross_selling')
        ) {
            parent::loadArticleCrossSell($sArticleId);
        }

        try {
            $this->fetchFromMakaira(
                self::RECOMMENDATION_TYPE_ACCESSORIES,
                $recommendationId,
                $sArticleId,
                $this->oxidSettingsService->get('iNrofCrossellArticles'),
            );
        } catch (FeatureNotAvailableException $e) {
            $this->moduleSettingService->setBoolean('makaira_recommendation_cross_selling', false);
            parent::loadArticleCrossSell($sArticleId);
        } catch (Throwable $e) {
            parent::loadArticleCrossSell($sArticleId);
        }
    }

    /**
     * @param string $sArticleId
     *
     * @return void
     * @throws ConnectException
     * @throws ConnectUnexpectedValueException
     * @throws FeatureNotAvailableException
     * @throws JsonException
     * @throws ModelNotFoundException
     */
    public function loadSimilarProducts(string $sArticleId): void
    {
        $recommendationId = (string) $this->moduleSettingService->getString(
            'makaira_recommendation_similar_products_id',
        );

        if (empty($recommendationId) ||
            !$this->moduleSettingService->getBoolean('makaira_recommendation_similar_products')
        ) {
            return;
        }

        try {
            $this->fetchFromMakaira(
                self::RECOMMENDATION_TYPE_SIMILAR_PRODUCTS,
                $recommendationId,
                $sArticleId,
                $this->oxidSettingsService->get('iNrofCrossellArticles'),
            );
        } catch (FeatureNotAvailableException $e) {
            $this->moduleSettingService->setBoolean('makaira_recommendation_similar_products', false);
            throw $e;
        }
    }

    /**
     * @param string $recommendationType
     * @param string $recommendationId
     * @param string $productId
     * @param int    $count
     *
     * @return void
     * @throws FeatureNotAvailableException
     * @throws ModelNotFoundException
     * @throws JsonException
     * @throws ConnectException
     * @throws ConnectUnexpectedValueException
     */
    protected function fetchFromMakaira(
        string $recommendationType,
        string $recommendationId,
        string $productId,
        int $count = 50,
    ): void {
        $product     = $this->getProduct($productId);
        $categoryId  = $product->getCategory()?->getId() ?? $this->oxidSettingsService->getCurrentCategoryId();
        $attributes  = $this->getAttributeBoosts($product);
        $constraints = [
            Constraints::SHOP      => $this->oxidSettingsService->getShopId(),
            Constraints::LANGUAGE  => $this->oxidSettingsService->getLanguageAbbreviation(),
            Constraints::USE_STOCK => $this->oxidSettingsService->get('blUseStock'),
        ];

        $query = new RecommendationQuery([
            'recommendationId' => $recommendationId,
            'productId'        => $productId,
            'requestId'        => hash('sha256', microtime(true)),
            'count'            => $count,
            'categoryId'       => $categoryId,
            'attributes'       => $attributes,
            'constraints'      => $constraints,
        ]);

        $priceRange   = $this->getPriceRange($recommendationType, $product);
        $productPrice = $product->getPrice()->getPrice();

        if ($priceRange['min'] >= 0.0) {
            $query->priceRangeMin = $productPrice * $priceRange['min'];
        }

        if ($priceRange['max'] >= 0.0) {
            $query->priceRangeMax = $productPrice * $priceRange['max'];
        }

        $recommendationHandler = ContainerFacade::get(RecommendationHandler::class);

        $result = $recommendationHandler->recommendation($query);

        $productIds = array_column($result->items, 'id');

        $this->loadIds($productIds);
        $this->sortByIds($productIds);
    }

    /**
     * @param string $productId
     *
     * @return Article
     * @throws ModelNotFoundException
     */
    protected function getProduct(string $productId): Article
    {
        if (!isset(self::$productCache[$productId])) {
            self::$productCache[$productId] = $this->oxidModels->getArticle($productId);
        }

        return self::$productCache[$productId];
    }

    protected function getAttributeBoosts(Article $product): array
    {
        return [];
    }

    /**
     * @param string  $type
     * @param Article $product
     *
     * @return array<string, float>
     */
    protected function getPriceRange(string $type, Article $product): array
    {
        return match ($type) {
            self::RECOMMENDATION_TYPE_ACCESSORIES      => ['min' => 0.7, 'max' => '1.9'],
            self::RECOMMENDATION_TYPE_CROSS_SELLING    => ['min' => 0.9, 'max' => -1],
            self::RECOMMENDATION_TYPE_SIMILAR_PRODUCTS => ['min' => 0.8, 'max' => 1.6],
            default                                    => [],
        };
    }
}
