<?php

namespace Makaira\OxidConnect\Oxid\Core;

use Makaira\OxidConnect\Helper\Cookies;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Service\FilterProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;

use function round;

class ViewConfig extends ViewConfig_parent
{
    private const ECONDA_LOADER_URL = "https://d35ojb8dweouoy.cloudfront.net/loader/loader.js";

    private static ?string $econdaClientKey = null;

    private static ?string $econdaContainerId = null;

    protected ?array $activeFilter = null;

    public function getEcondaClientKey(): string
    {
        if (null === self::$econdaClientKey) {
            ContainerFacade::get(ModuleSettings::class)->getString('makaira_connect_econda_aid');
        }

        return self::$econdaClientKey;
    }

    public function getEcondaContainerId(): string
    {
        if (null === self::$econdaClientKey) {
            ContainerFacade::get(ModuleSettings::class)->getString('makaira_connect_econda_cid');
        }

        return self::$econdaClientKey;
    }

    public function getEcondaLoaderUrl(): string
    {
        return self::ECONDA_LOADER_URL;
    }

    public function getAggregationFilter(): array
    {
        return ContainerFacade::get(FilterProvider::class)->getActiveFilter();
    }

    public function getFilterParamName(): string
    {
        return ContainerFacade::get(FilterProvider::class)->getFilterParameterName();
    }

    public function toCurrency($value): float
    {
        $currency = Registry::getConfig()->getActShopCurrencyObject();

        return round($value * $currency->rate, 2);
    }

    public function fromCurrency($value): float
    {
        $currency = Registry::getConfig()->getActShopCurrencyObject();

        return round($value / $currency->rate, 2);
    }

    public function isCookieBannerActive(): bool
    {
        return ContainerFacade::get(Cookies::class)->cookieBannerEnabled();
    }
}
