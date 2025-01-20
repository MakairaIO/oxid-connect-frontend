<?php

namespace Makaira\OxidConnect\Helper;

use OxidEsales\Eshop\Core\Request;
use stdClass;

interface OxidSettingsInterface
{
    public function getShopId(): ?int;

    public function get(string $name, ?int $shopId): mixed;

    public function getConfigParameter(string $name, mixed $default = null): mixed;

    public function getRequest(): Request;

    public function seoIsActive(): bool;

    public function redirect(string $url, bool $replace = false, int $status = 302): void;

    public function getCurrentArticleId(): string;

    public function getCurrentCategoryId(): string;

    public function getCurrentSearchParam(): string;

    public function getCurrentManufacturerId(): string;

    public function getCurrentViewClassName(): string;

    public function getCurrentCurrency(): stdClass;

    public function getLanguageAbbreviation(): string;
}
