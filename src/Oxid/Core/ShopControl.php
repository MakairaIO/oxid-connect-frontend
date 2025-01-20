<?php

namespace Makaira\OxidConnect\Oxid\Core;

use Exception;
use JsonException;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Service\ABTestingProvider;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;

use function random_int;

class ShopControl extends ShopControl_parent
{
    /**
     * @return void
     * @throws JsonException
     * @throws Exception
     */
    protected function runOnce(): void
    {
        parent::runOnce();

        $moduleSettings = ContainerFacade::get(ModuleSettings::class);

        $enableLocalSelection = $moduleSettings->getBoolean('makaira_ab_testing_local_group_select');

        if (!$enableLocalSelection) {
            return;
        }

        $abTestingProvider  = ContainerFacade::get(ABTestingProvider::class);
        $currentExperiments = $abTestingProvider->getExperiments();

        if (!$currentExperiments) {
            $variation = 'original';

            if (random_int(0, 1)) {
                $variation = $moduleSettings->getString('makaira_ab_testing_local_group_variation');
            }

            $experimentsCookie = [
                [
                    'experiment' => $moduleSettings->getString('makaira_ab_testing_local_group_id'),
                    'variation'  => $variation,
                ],
            ];
            $abTestingProvider->setExperiments($experimentsCookie);
        }
    }
}
