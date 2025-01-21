<?php

use Makaira\OxidConnect\Oxid\Application\Component as ModuleComponent;
use Makaira\OxidConnect\Oxid\Application\Controller as ModuleController;
use Makaira\OxidConnect\Oxid\Application\Model as ModuleModel;
use Makaira\OxidConnect\Oxid\Core as ModuleCore;
use OxidEsales\Eshop\Application\Component as OxidComponent;
use OxidEsales\Eshop\Application\Controller as OxidController;
use OxidEsales\Eshop\Application\Model as OxidModel;
use OxidEsales\Eshop\Core as OxidCore;
use OxidEsales\Facts\Edition\EditionSelector;


$sMetadataVersion = '2.1';

$aModule = [
    'id'          => 'makaira_oxid-connect-frontend',
    'title'       => 'Makaira Connect',
    'thumbnail'   => 'admin/makaira.jpg',
    'version'     => '0.1.0',
    'author'      => 'Makaira GmbH',
    'url'         => 'https://makaira.io/',
    'email'       => 'support@makaira.io',
    'description' => [
        'de' => 'Dies stellt die verbleibende Funktionalität bereit, die von dem alten OXID Connect bereitgestellt wurde.',
        'en' => 'This provides the remaining functionality provided by the old OXID Connect.',
    ],
    'extend'      => [
        /* Controllers */
        OxidController\ArticleListController::class      => ModuleController\ArticleListController::class,
        OxidController\SearchController::class           => ModuleController\SearchController::class,
        OxidController\ManufacturerListController::class => ModuleController\ManufacturerListController::class,
        /* Core */
        OxidCore\Config::class                           => ModuleCore\Config::class,
        OxidCore\ViewConfig::class                       => ModuleCore\ViewConfig::class,
        OxidCore\ShopControl::class                      => ModuleCore\ShopControl::class,
        OxidCore\SeoDecoder::class                       => ModuleCore\SeoDecoder::class,
        /* Models */
        OxidModel\ArticleList::class                     => ModuleModel\ArticleList::class,
        OxidModel\Article::class                         => ModuleModel\Article::class,
        /* components */
        OxidComponent\Locator::class                     => ModuleComponent\Locator::class,
    ],
    'controllers' => [
        'makaira_connect_autosuggest' => ModuleController\Autosuggestion::class,
        'makaira_connect_econda'      => ModuleController\Econda::class,
    ],
    'settings'    => [
        ['name' => 'makaira_connect_timeout', 'group' => 'SETTINGS', 'type' => 'num', 'value' => 2],
        ['name' => 'makaira_application_url', 'group' => 'SETTINGS', 'type' => 'str', 'value' => ''],
        ['name' => 'makaira_instance', 'group' => 'SETTINGS', 'type' => 'str', 'value' => 'live'],
        ['name' => 'makaira_connect_activate_search', 'group' => 'SETTINGS', 'type' => 'bool', 'value' => false],
        ['name' => 'makaira_connect_activate_listing', 'group' => 'SETTINGS', 'type' => 'bool', 'value' => false],
        ['name' => 'makaira_connect_category_inheritance', 'group' => 'SETTINGS', 'type' => 'bool', 'value' => false],
        ['name' => 'makaira_connect_categorytree_id', 'group' => 'SETTINGS', 'type' => 'str', 'value' => ''],
        ['name' => 'makaira_connect_seofilter', 'group' => 'SETTINGS', 'type' => 'bool', 'value' => false],
        [
            'name'  => 'makaira_connect_use_econda',
            'group' => 'OPERATIONAL_INTELLIGENCE',
            'type'  => 'bool',
            'value' => false,
        ],
        ['name' => 'makaira_connect_econda_aid', 'group' => 'OPERATIONAL_INTELLIGENCE', 'type' => 'str', 'value' => ''],
        ['name' => 'makaira_connect_econda_cid', 'group' => 'OPERATIONAL_INTELLIGENCE', 'type' => 'str', 'value' => ''],
        ['name' => 'makaira_connect_url_param', 'group' => 'SETTINGS', 'type' => 'str', 'value' => 'makairaFilter'],
        [
            'group' => 'TRACKING_PRIVACY',
            'name'  => 'makaira_tracking_page_id',
            'type'  => 'str',
            'value' => '',
        ],
        [
            'group' => 'TRACKING_PRIVACY',
            'name'  => 'makaira_cookie_banner_enabled',
            'type'  => 'bool',
            'value' => true,
        ],
        [
            'name'  => 'makaira_connect_use_odoscope',
            'group' => 'OPERATIONAL_INTELLIGENCE',
            'type'  => 'bool',
            'value' => 0,
        ],
        [
            'name'  => 'makaira_connect_odoscope_siteid',
            'group' => 'OPERATIONAL_INTELLIGENCE',
            'type'  => 'str',
            'value' => '',
        ],
        [
            'name'  => 'makaira_connect_odoscope_token',
            'group' => 'OPERATIONAL_INTELLIGENCE',
            'type'  => 'str',
            'value' => '',
        ],
        [
            'name'  => 'makaira_recommendation_accessories',
            'group' => 'RECOMMENDATION',
            'type'  => 'bool',
            'value' => 0,
        ],
        [
            'name'  => 'makaira_recommendation_accessory_id',
            'group' => 'RECOMMENDATION',
            'type'  => 'str',
        ],
        [
            'name'  => 'makaira_recommendation_cross_selling',
            'group' => 'RECOMMENDATION',
            'type'  => 'bool',
            'value' => 0,
        ],
        [
            'name'  => 'makaira_recommendation_cross_selling_id',
            'group' => 'RECOMMENDATION',
            'type'  => 'str',
        ],
        [
            'name'  => 'makaira_recommendation_similar_products',
            'group' => 'RECOMMENDATION',
            'type'  => 'bool',
            'value' => 0,
        ],
        [
            'name'  => 'makaira_recommendation_similar_products_id',
            'group' => 'RECOMMENDATION',
            'type'  => 'str',
        ],
        [
            'group' => 'makaira_search_results',
            'name'  => 'makaira_search_results_category',
            'type'  => 'num',
            'value' => -1,
        ],
        [
            'group' => 'makaira_search_results',
            'name'  => 'makaira_search_results_links',
            'type'  => 'num',
            'value' => -1,
        ],
        [
            'group' => 'makaira_search_results',
            'name'  => 'makaira_search_results_manufacturer',
            'type'  => 'num',
            'value' => -1,
        ],
        [
            'group' => 'makaira_search_results',
            'name'  => 'makaira_search_results_product',
            'type'  => 'num',
            'value' => -1,
        ],
        [
            'group' => 'makaira_search_results',
            'name'  => 'makaira_search_results_suggestion',
            'type'  => 'num',
            'value' => -1,
        ],
        [
            'group' => 'makaira_ab_testing',
            'name'  => 'makaira_ab_testing_local_group_select',
            'type'  => 'bool',
            'value' => false,
        ],
        [
            'group' => 'makaira_ab_testing',
            'name'  => 'makaira_ab_testing_local_group_id',
            'type'  => 'str',
            'value' => '',
        ],
        [
            'group' => 'makaira_ab_testing',
            'name'  => 'makaira_ab_testing_local_group_variation',
            'type'  => 'str',
            'value' => '',
        ],
    ],
];

$es = new EditionSelector();
if ($es->isEnterprise()) {
    $aModule['extend'][OxidCore\Cache\DynamicContent\ContentCache::class] = ModuleCore\Cache\DynamicContent\ContentCache::class;
}
