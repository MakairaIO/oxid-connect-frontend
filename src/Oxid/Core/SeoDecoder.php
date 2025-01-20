<?php

namespace Makaira\OxidConnect\Oxid\Core;

use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Service\FilterProvider;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;

use function explode;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function str_contains;
use function urldecode;

class SeoDecoder extends SeoDecoder_parent
{
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
        foreach ($filterMatches[1] as $filterMatch) {
            $parts = explode('_', $filterMatch);
            $value = urldecode(array_pop($parts));
            $key = implode('_', $parts);

            $value = str_replace('---', '/', $value);
            $filter[$key][] = (array) $value;
        }

        $filter = array_map(static fn ($values) => array_merge(...$values), $filter);

        $seoUrl = $filterMatches[1][0];

        $decodedUrl = parent::decodeUrl($seoUrl);
        $filterProvider = ContainerFacade::get(FilterProvider::class);
        $filterProvider->buildCookieFilter($decodedUrl['cl'], $decodedUrl['cnid'] ?? $decodedUrl['mnid'] ?? '', $filter);

        return $decodedUrl;
    }
}
