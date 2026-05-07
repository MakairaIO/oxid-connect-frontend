<?php

namespace Makaira\OxidConnect\Oxid\Core;

use JsonException;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Service\FilterProvider;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;

use function explode;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function urldecode;

class SeoDecoder extends SeoDecoder_parent
{
    /**
     * @param $seoUrl
     *
     * @return array|false
     * @throws JsonException
     * @throws LanguageNotFoundException
     */
    public function decodeUrl($seoUrl): array|false
    {
        if (!str_contains($seoUrl, '_')) {
            return parent::decodeUrl($seoUrl);
        }

        preg_match_all("#([^_]*/)([^/]*_[^/]*)#", $seoUrl, $filterMatches);
        if (!isset($filterMatches[2])) {
            return parent::decodeUrl($seoUrl);
        }

        $moduleSettings = ContainerFacade::get(ModuleSettings::class);

        if (!$moduleSettings->getBoolean('makaira_connect_seofilter')) {
            return parent::decodeUrl($seoUrl);
        }

        $pageNumber = '';
        if (preg_match('#.*/(\d+)$#', rtrim($seoUrl, '/'), $pageMatches)) {
            $pageNumber = $pageMatches[1] . '/';
        }

        $filter = [];
        foreach ($filterMatches[2] as $filterMatch) {
            $parts = explode('_', $filterMatch);
            $value = urldecode(array_pop($parts));
            $key = implode('_', $parts);

            $value = str_replace('---', '/', $value);
            if (str_ends_with($key, '_from') || str_ends_with($key, '_to')) {
                $filter[$key] = $value;
            } else {
                $filter[$key][] = $value;
            }
        }

        $seoUrl = $filterMatches[1][0];

        $decodedUrl = parent::decodeUrl($seoUrl);
        $filterProvider = ContainerFacade::get(FilterProvider::class);
        $filterProvider->buildCookieFilter(
            $decodedUrl['cl'],
            $decodedUrl['cnid'] ?? $decodedUrl['mnid'] ?? '',
            $filter
        );

        return $decodedUrl;
    }
}
