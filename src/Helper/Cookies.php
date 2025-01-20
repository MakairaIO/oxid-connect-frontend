<?php

namespace Makaira\OxidConnect\Helper;

use JsonException;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Registry;
use Throwable;

use function base64_encode;
use function json_encode;
use function sprintf;
use function time;

class Cookies
{
    public const EXPERIMENTS = 'mak_experiments';

    public function __construct(
        private string $filterParameterName,
        private bool $cookieBannerEnabled,
        private Language $language
    ) {
    }

    public function loadMakairaFilterFromCookie(): array
    {
        try {
            $cookieContent = Registry::getUtilsServer()->getOxCookie(
                sprintf('%s_%s', $this->filterParameterName, $this->language->getLanguageAbbr()),
            );

            return (array) json_decode(base64_decode($cookieContent), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param $cookieFilter
     *
     * @return void
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function saveMakairaFilterToCookie($cookieFilter): void
    {
        Registry::getUtilsServer()->setOxCookie(
            sprintf('%s_%s', $this->filterParameterName, $this->language->getLanguageAbbr()),
            base64_encode(json_encode($cookieFilter, JSON_THROW_ON_ERROR)),
        );
    }

    public function savePageNumberToCookie(): void
    {
        Registry::getUtilsServer()
            ->setOxCookie('makairaPageNumber', Registry::getRequest()->getRequestParameter('pgNr'));
    }

    public function deletePageNumberCookie(): void
    {
        Registry::getUtilsServer()->setOxCookie('makairaPageNumber', '', time() - 3600);
    }

    public function setCookie(
        string $name,
        string $value = "",
        int $expire = 0,
        string $path = '/',
        ?string $domain = null,
        bool $saveToSession = true,
        bool $secure = false,
        bool $httpOnly = true,

    ): bool {
        if ($this->cookiesAccepted()) {
            return Registry::getUtilsServer()->setOxCookie(
                $name,
                $value,
                $expire,
                $path,
                $domain,
                $saveToSession,
                $secure,
                $httpOnly,
            );
        }

        return false;
    }

    public function getExperiments(): array
    {
        try {
            $cookieContent = $this->getCookie(self::EXPERIMENTS);

            return (array) json_decode($cookieContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param array $experiments
     *
     * @return void
     * @throws JsonException
     */
    public function setExperiments(array $experiments): void
    {
        $this->setCookie(self::EXPERIMENTS, json_encode($experiments, JSON_THROW_ON_ERROR), time() + 180 * 86400);
        $_COOKIE[self::EXPERIMENTS] = $experiments;
    }

    public function getCookie(string $name): string|int|float|array|null
    {
        return Registry::getUtilsServer()->getOxCookie($name);
    }

    public function cookiesAccepted(): bool
    {
        if (!$this->cookieBannerEnabled) {
            return true;
        }

        if (isset($_COOKIE['cookie-consent'])) {
            return 'accept' === $_COOKIE['cookie-consent'];
        }

        return false;
    }

    public function cookieBannerEnabled(): bool
    {
        return $this->cookieBannerEnabled;
    }
}
