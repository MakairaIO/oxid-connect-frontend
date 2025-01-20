<?php

namespace Makaira\OxidConnect\Helper;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\ViewConfig;
use stdClass;

use function oxNew;

class OxidSettings implements OxidSettingsInterface
{
    private static array $settingsCache = [];

    public function __construct(private Config $config)
    {
    }

    public static function create(): static
    {
        return new static(Registry::getConfig());
    }

    public function getShopId(): ?int
    {
        return $this->config->getShopId();
    }

    /**
     * @param string   $name
     * @param int|null $shopId
     *
     * @return mixed
     */
    public function get(string $name, ?int $shopId = null): mixed
    {
        $shopId ??= $this->config->getShopId();
        $cacheKey = sprintf('%s-%u', $name, $shopId);

        if (!isset(self::$settingsCache[$cacheKey])) {
            self::$settingsCache[$cacheKey] = $this->config->getShopConfVar($name, $shopId);
        }

        return self::$settingsCache[$cacheKey];
    }

    public function getConfigParameter(string $name, mixed $default = null): mixed
    {
        return $this->config->getConfigParam($name, $default);
    }

    public function getRequest(): Request
    {
        return Registry::getRequest();
    }

    public function seoIsActive(): bool
    {
        return (bool) Registry::getUtils()->seoIsActive();
    }

    public function redirect(string $url, bool $replace = false, int $status = 302): void
    {
        Registry::getUtils()->redirect($url, $replace, $status);
    }

    public function getCurrentArticleId(): string
    {
        return oxNew(ViewConfig::class)->getActArticleId();
    }

    public function getCurrentCategoryId(): string
    {
        return oxNew(ViewConfig::class)->getActCatId();
    }

    public function getCurrentSearchParam(): string
    {
        return oxNew(ViewConfig::class)->getActSearchParam();
    }

    public function getCurrentManufacturerId(): string
    {
        return oxNew(ViewConfig::class)->getActManufacturerId();
    }

    public function getCurrentViewClassName(): string
    {
        return oxNew(ViewConfig::class)->getActiveClassName();
    }

    public function getCurrentCurrency(): stdClass
    {
        return (object) $this->config->getActShopCurrencyObject();
    }

    /**
     * @return string
     * @throws LanguageNotFoundException
     */
    public function getLanguageAbbreviation(): string
    {
        return Registry::getLang()->getLanguageAbbr();
    }
}
