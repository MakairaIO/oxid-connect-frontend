<?php

namespace Makaira\OxidConnect\Tracking;

use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Oxid\Application\Controller\ArticleListController;
use OxidEsales\Eshop\Application\Component\Widget\ArticleDetails;
use OxidEsales\Eshop\Application\Controller\SearchController;
use OxidEsales\Eshop\Application\Controller\ThankYouController;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

use function array_merge;
use function is_callable;
use function ltrim;
use function method_exists;
use function oxNew;
use function preg_replace;
use function ucfirst;
use function urlencode;

class Generator
{
    private const TRACKER_URL = 'https://piwik.makaira.io';

    private static ?array $odoscopeTracking = null;

    private ModuleSettingServiceInterface $connectSettings;

    public function __construct()
    {
        $this->connectSettings = ContainerFacade::get(ModuleSettings::class);
    }

    public function generate(string $oxidControllerClass): array
    {
        $siteId = $this->connectSettings->getString('makaira_tracking_page_id');

        if ($siteId->isEmpty()) {
            return [];
        }

        $childTrackingData = null;
        $normalizedClass   = $this->normalize($oxidControllerClass);
        $methodName        = "generateFor{$normalizedClass}";

        if (is_callable([$this, $methodName]) && method_exists($this, $methodName)) {
            $childTrackingData = $this->{$methodName}();
        }

        if (Registry::getSession()->getBasket()?->isNewItemAdded()) {
            $childTrackingData = $this->generateForBasket();
        }
        if (null === $childTrackingData) {
            $childTrackingData = [['trackPageView']];
        }

        $trackingData = [
            $childTrackingData,
            [
                ['enableLinkTracking'],
                ['setTrackerUrl', "{$this->getTrackerUrl()}/piwik.php"],
                ['setSiteId', $siteId],
            ],
            $this->getCustomTrackingData(),
        ];

        if (null !== static::$odoscopeTracking) {
            $trackingData[] = [
                ['trackEvent', 'odoscope', static::$odoscopeTracking['group'], static::$odoscopeTracking['data']],
            ];
        }

        $oxidViewConfig = Registry::get(ViewConfig::class);
        if ($oxidViewConfig instanceof makaira_connect_oxviewconfig) {
            foreach ($oxidViewConfig->getExperiments() as $experiment => $variation) {
                $trackingData[] = [['trackEvent', 'abtesting', $experiment, $variation]];
            }
        }

        return array_merge(...$trackingData);
    }

    /**
     * Normalizes OXIDs controller class name for the method call.
     *
     * @param string $className OXIDs controller class name.
     *
     * @return string
     */
    protected function normalize(string $className): string
    {
        if (str_starts_with($className, 'ox')) {
            $className = preg_replace('/^ox/', '', $className);
        }

        return ucfirst($className);
    }

    /**
     * Generates tracking data for OXIDs "basket" controller or if a new item was added to the cart.
     *
     * @return array
     */
    protected function generateForBasket(): array
    {
        $cartData = [];

        if (($cart = Registry::getSession()->getBasket()) instanceof Basket) {
            $cartData = $this->createCartTrackingData($cart);

            $cartData[] = ['trackEcommerceCartUpdate', $cart?->getPrice()?->getBruttoPrice()];
        }

        return $cartData;
    }

    /**
     * Creates tracking data from the shopping cart. Used for cart updates and the order.
     *
     * @param Basket $cart
     *
     * @return array
     */
    protected function createCartTrackingData(Basket $cart): array
    {
        $cartData = [];
        foreach ($cart->getContents() as $cartItem) {
            $product  = $cartItem->getArticle();
            $category = $product->getCategory();

            $cartData[] = [
                'addEcommerceItem',
                $product->oxarticles__oxartnum->value,
                $cartItem->getTitle(),
                $category->oxcategories__oxtitle->value,
                $cartItem->getUnitPrice()->getBruttoPrice(),
                $cartItem->getAmount(),
            ];
        }

        return $cartData;
    }

    public function getTrackerUrl(): string
    {
        return self::TRACKER_URL;
    }

    /**
     * Hook to add custom data to Piwik/Matomo tracking.
     *
     * @return array
     */
    protected function getCustomTrackingData(): array
    {
        return [];
    }

    protected function generateForSearch(): array
    {
        $trackingData   = [];
        $oxidController = Registry::getConfig()->getTopActiveView();

        if ($oxidController instanceof SearchController) {
            $trackingData = [
                [
                    'trackSiteSearch',
                    $oxidController->getSearchParamForHtml(),
                    false,
                    $oxidController->getArticleCount(),
                ],
            ];
        }

        return $trackingData;
    }

    protected function generateForThankyou(): array
    {
        $cartData       = [];
        $oxidController = Registry::getConfig()->getTopActiveView();

        if ($oxidController instanceof ThankYouController) {
            $cart  = $oxidController->getBasket();
            $order = $oxidController->getOrder();
            if ($cart instanceof Basket && $order instanceof Order) {
                $cartData   = $this->createCartTrackingData($cart);
                $cartData[] = [
                    'trackEcommerceOrder',
                    $order->oxorder__oxordernr->value,
                    $order->getTotalOrderSum(),
                    $cart->getDiscountedProductsBruttoPrice(),
                    ($order->oxorder__oxartvatprice1->value + $order->oxorder__oxartvatprice2->value),
                    ($order->getOrderDeliveryPrice()?->getBruttoPrice() +
                     $order->getOrderPaymentPrice()?->getBruttoPrice() +
                     $order->getOrderWrappingPrice()?->getBruttoPrice()),
                    $order->oxorder__oxdiscount->value,
                ];
            }
        }

        return $cartData;
    }

    protected function generateForUBase(): array
    {
        $queryString = $_SERVER['QUERY_STRING'] ? "?{$_SERVER['QUERY_STRING']}" : '';
        $url         = urlencode(ltrim($_SERVER['REQUEST_URI'], '/') . $queryString);
        $referer     = urlencode($_SERVER['HTTP_REFERER']);

        return [
            [
                'setDocumentTitle',
                "404/URL = {$url}/From = {$referer}",
            ],
        ];

    }
    protected function generateForDetails(): array
    {
        $oxidDetailsWidget = oxNew(ArticleDetails::class);
        $product  = $oxidDetailsWidget->getProduct();
        $category = $product->getCategory();

        return [
            [
                'setEcommerceView',
                $product->oxarticles__oxartnum->value,
                $product->oxarticles__oxtitle->value,
                $category?->oxcategories__oxtitle->value,
            ],
            ['trackPageView']
        ];
    }

    protected function generateForAlist(): array
    {
        $oxidController = Registry::getConfig()->getTopActiveView();

        if (!$oxidController instanceof ArticleListController) {
            $oxidController = Registry::get(ArticleListController::class);
        }

        return [
            ['setEcommerceView', false, false, $oxidController->getTitle()],
            ['trackPageView']
        ];
    }

    public function setOdoscopeData(?array $odoscopeData): void
    {
        static::$odoscopeTracking = $odoscopeData;
    }
}
