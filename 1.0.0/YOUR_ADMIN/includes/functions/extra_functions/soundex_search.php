<?php
/**
 * Part of the Soundex Search plugin by neekfenwick, provided under GPL 3.0 license.
 * Copyright (C) 2023, Nick Fenwick. All rights reserved.
 */

namespace Zencart\SoundexSearch;

$dbEntityMap = [
    [ 'table_name' => 'products_description', 'key_name' => 'products_id', 'column_name' => 'products_name', 'trigger_name' => 'prod_name' ],
    [ 'table_name' => 'products_description', 'key_name' => 'products_id', 'column_name' => 'products_description', 'trigger_name' => 'prod_desc' ],
    [ 'table_name' => 'products', 'key_name' => 'products_id', 'column_name' => 'products_model', 'trigger_name' => 'prod_model' ],
    [ 'table_name' => 'manufacturers', 'key_name' => 'manufacturers_id', 'column_name' => 'manufacturers_name', 'trigger_name' => 'prod_manufacturer' ],
    [ 'table_name' => 'meta_tags_products_description', 'key_name' => 'products_id', 'column_name' => 'metatags_keywords', 'trigger_name' => 'meta_keywords' ],
    [ 'table_name' => 'meta_tags_products_description', 'key_name' => 'products_id', 'column_name' => 'metatags_description', 'trigger_name' => 'meta_desc' ]
];

$configMap = [
    'SOUNDEX_SEARCH_MATCH_PRODUCTS_NAME' => [
        'name' => 'Products Name',
        'default' => 'Yes'
    ],
    'SOUNDEX_SEARCH_MATCH_PRODUCTS_DESCRIPTION' => [
        'name' => 'Products Description',
        'default' => 'No'
    ],
    'SOUNDEX_SEARCH_MATCH_PRODUCTS_MODEL' => [
        'name' => 'Products Model',
        'default' => 'No'
    ],
    'SOUNDEX_SEARCH_MATCH_MANUFACTURERS_NAME' => [
        'name' => 'Manufacturers Name',
        'default' => 'No'
    ],
    'SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_KEYWORDS' => [
        'name' => 'Metatags Products Keywords',
        'default' => 'No'
    ],
    'SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_DESCRIPTION' => [
        'name' => 'Metatags Products Description',
        'default' => 'No'
    ]
];

function procNameForTableColumn($table_name, $column_name) {
    return "soundex_init_{$table_name}_{$column_name}";
}
function triggerName($name) {
    return "soundex_{$name}_update";
}
