<?php

$sLangName = "English";

$aLang = [
    'charset' => 'UTF-8',

    'SHOP_MODULE_GROUP_SETTINGS'                       => 'Settings',
    'SHOP_MODULE_makaira_connect_secret'               => 'Shared Secret',
    'SHOP_MODULE_makaira_application_url'              => 'Makaira Url',
    'SHOP_MODULE_makaira_connect_load_limit'           => 'Load Limitation',
    'HELP_SHOP_MODULE_makaira_connect_load_limit'      => 'Common value: Half of the number of CPU cores on this machine (1 core => .5; 4 cores => 2)',
    'SHOP_MODULE_makaira_instance'                     => 'Instance',
    'SHOP_MODULE_makaira_connect_timeout'              => 'Timeout for Makaira requests',
    'SHOP_MODULE_makaira_connect_activate_search'      => 'Activate for search',
    'SHOP_MODULE_makaira_connect_activate_listing'     => 'Activate for category',
    'SHOP_MODULE_makaira_connect_category_inheritance' => 'Parent category contains all products from child categories',
    'SHOP_MODULE_makaira_connect_categorytree_id'      => 'ID of Categorytree filter (if exists)',
    'SHOP_MODULE_makaira_connect_seofilter'            => 'Generate SEO url for filtered category and manufacturer pages',
    'SHOP_MODULE_makaira_connect_url_param'            => 'Name of the URL parameters used for filters',

    'SHOP_MODULE_GROUP_OPERATIONAL_INTELLIGENCE'    => 'Personalization',
    'SHOP_MODULE_makaira_connect_use_econda'        => 'Use Econda Support',
    'HELP_SHOP_MODULE_makaira_connect_use_econda'   => 'Please enter your Econda access data to sort the search results by Econda.',
    'SHOP_MODULE_makaira_connect_econda_aid'        => 'Econda Account ID',
    'SHOP_MODULE_makaira_connect_econda_cid'        => 'Econda Container ID',
    'SHOP_MODULE_makaira_connect_use_odoscope'      => 'Use Odoscope Support',
    'HELP_SHOP_MODULE_makaira_connect_use_odoscope' => 'Please enter your Odoscope access data to sort the search results by Odoscope.',
    'SHOP_MODULE_makaira_connect_odoscope_siteid'   => 'Odoscope Site ID',
    'SHOP_MODULE_makaira_connect_odoscope_token'    => 'Odoscope Endpoint Token',

    'SHOP_MODULE_GROUP_IMPORTFIELDSANDATTS'            => 'Import Attributes & Fields',
    'SHOP_MODULE_makaira_field_blacklist_product'      => '<b>Products</b>: blacklisted fields',
    'SHOP_MODULE_makaira_field_blacklist_category'     => '<b>Category</b>: blacklisted fields',
    'SHOP_MODULE_makaira_field_blacklist_manufacturer' => '<b>manufacturer</b>: blacklisted fields',
    'SHOP_MODULE_makaira_attribute_as_int'             => 'Import Attributes as <b>Integer</b> (OXID Liste)',
    'SHOP_MODULE_makaira_attribute_as_float'           => 'Import Attributes as <b>Float</b> (OXID Liste)',

    'makaira_connect_iframe' => 'Makaira Configuration',

    'SHOP_MODULE_GROUP_RECOMMENDATION'                       => 'Recommendations',
    'SHOP_MODULE_makaira_recommendation_accessories'         => 'Activate for accessories',
    'SHOP_MODULE_makaira_recommendation_accessory_id'        => 'Recommendation Ident for accessories',
    'SHOP_MODULE_makaira_recommendation_cross_selling'       => 'Activate for cross-selling',
    'SHOP_MODULE_makaira_recommendation_cross_selling_id'    => 'Recommendation Ident for cross-selling',
    'SHOP_MODULE_makaira_recommendation_similar_products'    => 'Activate for similar products',
    'SHOP_MODULE_makaira_recommendation_similar_products_id' => 'Recommendation Ident for similar products',

    'SHOP_MODULE_GROUP_makaira_search_results'        => 'Searchresults',
    'SHOP_MODULE_makaira_search_results_category'     => 'max. Categories',
    'SHOP_MODULE_makaira_search_results_links'        => 'max. Links',
    'SHOP_MODULE_makaira_search_results_manufacturer' => 'max. Manufacturer',
    'SHOP_MODULE_makaira_search_results_product'      => 'max. Products',
    'SHOP_MODULE_makaira_search_results_suggestion'   => 'max. Suggestions',

    'SHOP_MODULE_makaira_tracking_page_id'  => 'Tracking Page-ID',

    'SHOP_MODULE_makaira_cookie_banner_enabled'      => 'Enable cooke banner',
    'HELP_SHOP_MODULE_makaira_cookie_banner_enabled' => '<strong>If you disable the cookie banner, you must ensure ' .
        'the legal security of your store!</strong>',

    'SHOP_MODULE_GROUP_TRACKING_PRIVACY'               => 'Tracking & data protection',

    'SHOP_MODULE_GROUP_makaira_ab_testing'  => 'A/B-Experiments',
    'SHOP_MODULE_makaira_ab_testing_local_group_select' => 'Activate Connect-driven A/B-Experiment',
    'HELP_SHOP_MODULE_makaira_ab_testing_local_group_select' => '<strong>Caution:</strong> This option is only suitable in some rare cases. Its always recommended to let Makaira drive the A/B-Experiment! With this option there is only a 50/50 traffic split available.',
    'SHOP_MODULE_makaira_ab_testing_local_group_id' => 'Experiment ID',
    'SHOP_MODULE_makaira_ab_testing_local_group_variation' => 'Variation ID (B-Variant, not original)',
];
