<?php

// Part of the Soundex Search plugin, provided under GPL 2.0 license by neekfenwick
// Copyright (C) 2013-2023, Nick Fenwick. All rights reserved.

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[200][] = [
    'autoType'  => 'init_script',
    'loadFile'  => 'init_soundex_search.php'
];
