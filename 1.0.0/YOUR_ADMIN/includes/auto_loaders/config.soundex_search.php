<?php
/**
 * Part of the Soundex Search plugin by neekfenwick, provided under GPL 3.0 license.
 * Copyright (C) 2023, Nick Fenwick. All rights reserved.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[200][] = [
    'autoType'  => 'init_script',
    'loadFile'  => 'init_soundex_search.php'
];
