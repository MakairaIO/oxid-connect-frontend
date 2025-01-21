<?php

namespace Makaira\OxidConnect\Oxid\Core\Cache\DynamicContent;

use Makaira\OxidConnect\Service\FilterProvider;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use Throwable;

use function in_array;

class ContentCache extends ContentCache_parent
{
    private const FILTERABLE_CONTROLLER = ['alist', 'manufacturerlist', 'search'];

    public function isViewCacheable($sViewName)
    {
        if (in_array($sViewName, self::FILTERABLE_CONTROLLER, true)) {
            try {
                $activeFilter = ContainerFacade::get(FilterProvider::class)->getActiveFilter();
                if (0 < count($activeFilter)) {
                    return false;
                }
            } catch (Throwable) {
            }
        }

        return parent::isViewCacheable($sViewName);
    }
}
