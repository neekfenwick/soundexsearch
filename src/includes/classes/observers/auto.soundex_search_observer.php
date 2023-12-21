<?php
/**
 * Part of the Soundex Search plugin by neekfenwick, provided under GPL 3.0 license.
 * Copyright (C) 2023, Nick Fenwick. All rights reserved.
 */

class zcObserverSoundexSearchObserver extends base
{
    public $dbg = false;

    public function __construct()
    {
        global $messageStack;
        // If we are missing our config (probably because the init script was not run by visiting admin), fail gracefully.
        if (!defined('SOUNDEX_SEARCH_MATCH_PRODUCTS_NAME') || !defined('SOUNDEX_SEARCH_MATCH_PRODUCTS_DESCRIPTION') ||
            !defined('SOUNDEX_SEARCH_MATCH_PRODUCTS_MODEL') || !defined('SOUNDEX_SEARCH_MATCH_MANUFACTURERS_NAME') ||
            !defined('SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_KEYWORDS') || !defined('SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_DESCRIPTION')) {
            $messageStack->add('header', SOUNDEX_SEARCH_CONFIG_ERROR, 'error');
            return;
        }

        $this->attach($this, array(
            'NOTIFY_SEARCH_FROM_STRING',
            'NOTIFY_SEARCH_WHERE_STRING'
        ));
    }


    public function notify_search_from_string(&$callingClass, $notifier, $readonly_from_str, &$from_str)
    {

        if (SOUNDEX_SEARCH_MATCH_PRODUCTS_NAME == 'false' && SOUNDEX_SEARCH_MATCH_PRODUCTS_DESCRIPTION == 'false') {
            return;
        }
        // Add the join for the soundex lookup table to the FROM clause
        $from_str .= "\nLEFT OUTER JOIN " . TABLE_SOUNDEX_LOOKUP . " sl ON sl.id = p.products_id";

    }

    public function notify_search_where_string(&$callingClass, $notifier, $keywords, &$where_str, &$keyword_search_fields)
    {
        // 1.5.8a and earlier do not have SearchOptions.
        if (method_exists($callingClass, 'getSearchOptions')) {
            $searchOptions = $callingClass->getSearchOptions();
            $search_in_description = $searchOptions->search_in_description ?? false;
        } else {
            // 1.5.8 support, assume we are called from a GET request.
            $search_in_description = $_GET['search_in_description'] ?? false;
        }

        // Modify the built WHERE clause so the fields we know are mapped in the lookup table are matched
        // Look for eg. pd.products_name LIKE '%anything%'
        // Replace with pns.word_soundex = SOUNDEX('anything')
        // This is highly dependent on the exact names used in the Search class SQL building.
        if (SOUNDEX_SEARCH_MATCH_PRODUCTS_NAME == 'Yes') {
            $where_str = preg_replace("/pd\.products_name LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'products_description' AND sl.column_name = 'products_name' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
        if ($search_in_description && SOUNDEX_SEARCH_MATCH_PRODUCTS_DESCRIPTION == 'Yes') {
            $where_str = preg_replace("/pd\.products_description LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'products_description' AND sl.column_name = 'products_description' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
        if (SOUNDEX_SEARCH_MATCH_PRODUCTS_MODEL == 'Yes') {
            $where_str = preg_replace("/p\.products_model LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'products' AND sl.column_name = 'products_model' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
        if (SOUNDEX_SEARCH_MATCH_MANUFACTURERS_NAME == 'Yes') {
            $where_str = preg_replace("/m\.manufacturers_name LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'manufacturers' AND sl.column_name = 'manufacturers_name' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
        if (ADVANCED_SEARCH_INCLUDE_METATAGS == 'true' && SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_KEYWORDS == 'Yes') {
            $where_str = preg_replace("/mtpd\.metatags_keywords LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'meta_tags_products_description' AND sl.column_name = 'metatags_keywords' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
        if (ADVANCED_SEARCH_INCLUDE_METATAGS == 'true' && SOUNDEX_SEARCH_MATCH_METATAGS_PRODUCTS_DESCRIPTION == 'Yes') {
            $where_str = preg_replace("/mtpd.metatags_description LIKE '%([^%]*)%'/", "\\0 OR (
                sl.table_name = 'meta_tags_products_description' AND sl.column_name = 'metatags_description' AND sl.word_soundex = SOUNDEX('\\1')
            )", $where_str);
        }
    }
}
