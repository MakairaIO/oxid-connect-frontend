<?php

namespace Makaira\OxidConnect\Oxid\Application\Controller;

use Exception;
use Makaira\OxidConnect\Core\Autosuggester;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererInterface;

use function header;
use function oxNew;

class Autosuggestion extends FrontendController
{
    public function __construct()
    {
        parent::__construct();

        $suggester = ContainerFacade::get(Autosuggester::class);
        $renderer  = ContainerFacade::get(TemplateRendererInterface::class);

        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: text/html');

        $searchPhrase = Registry::getRequest()->getRequestParameter('term');

        try {
            // get search term
            $searchResult = $suggester->search($searchPhrase);

            echo $renderer->renderTemplate(
                '@makaira_oxid-connect-full/autosuggest/autosuggest.html.twig',
                [
                    'oViewConf'    => oxNew(ViewConfig::class),
                    'result'       => $searchResult,
                    'searchPhrase' => $searchPhrase,
                ],
            );
        } catch (Exception $e) {
            echo $e;
        }

        exit();
    }
}
