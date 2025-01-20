<?php

namespace Makaira\OxidConnect\Oxid\Application\Controller;

use JetBrains\PhpStorm\NoReturn;
use Makaira\OxidConnect\Helper\ModuleSettings;
use Makaira\OxidConnect\Oxid\Core\Config;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;

use function header;
use function method_exists;

class Econda extends FrontendController
{
    private ModuleSettings $moduleSettings;

    #[NoReturn]
    public function __construct()
    {
        parent::__construct();

        $this->moduleSettings = ContainerFacade::get(ModuleSettings::class);
        if ($this->moduleSettings->getBoolean('makaira_connect_use_econda')) {
            $this->tryMakairaEcondaId();
            $this->tryThemeParameter();
            $this->tryOxidEcondaPersonalisation();
        }

        $this->sendResponse();
    }

    #[NoReturn]
    private function sendResponse(string $content = ''): void
    {
        header('Content-type: text/html');
        header('Expires: Mon, 04 Sep 2017 03:35:00 GMT');
        header('Cache-Control: no-cache, must-revalidate');

        echo $content;

        exit();
    }

    private function tryMakairaEcondaId(): void
    {
        $econdaAccountId = $this->moduleSettings->getString('makaira_connect_econda_aid');

        if ($econdaAccountId) {
            $this->sendResponse($econdaAccountId);
        }
    }

    private function tryOxidEcondaPersonalisation(): void
    {
        $oxidViewConfig = $this->getViewConfig();

        if (method_exists($oxidViewConfig, 'oePersonalizationGetAccountId')) {
            $econdaAccountId = $oxidViewConfig->oePersonalizationGetAccountId();

            if ($econdaAccountId) {
                $this->sendResponse($econdaAccountId);
            }
        }
    }

    private function tryThemeParameter(): void
    {
        $oxidConfig     = Registry::getConfig();
        $oxidViewConfig = $this->getViewConfig();

        if ($oxidConfig instanceof Config) {
            $oxidConfig->setEcondaThemeParam();
            $econdaAccountId = $oxidViewConfig->getViewThemeParam('sEcondaRecommendationsAID');

            if ($econdaAccountId) {
                $this->sendResponse($econdaAccountId);
            }
        }
    }
}
