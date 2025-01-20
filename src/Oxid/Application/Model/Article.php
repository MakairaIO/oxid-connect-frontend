<?php

namespace Makaira\OxidConnect\Oxid\Application\Model;

use Makaira\OxidConnect\Helper\OxidSettingsInterface;
use OxidEsales\Eshop\Application\Model\ArticleList as OxidArticleList;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use Psr\Log\LoggerInterface;
use Throwable;

class Article extends Article_parent
{
    public function getSimilarProducts(): null|OxidArticleList|ArticleList
    {
        $oxidSettingsService = ContainerFacade::get(OxidSettingsInterface::class);

        if (!$oxidSettingsService->get('bl_perfLoadSimilar')) {
            return null;
        }

        try {
            $similarList = oxNew(OxidArticleList::class);
            if ($similarList instanceof ArticleList) {
                $similarList->loadSimilarProducts($this->getId());
            }
            return $similarList;
        } catch (Throwable $t) {
            ContainerFacade::get(LoggerInterface::class)->error((string) $t);

            // Required for IDEs because of a wrong DocBlock in the OXID class.
            /** @var null|OxidArticleList $parent */
            $parent = parent::getSimilarProducts();

            return $parent;
        }
    }
}
