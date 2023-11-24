<?php
/**
 * Part of the Soundex Search plugin by neekfenwick, provided under GPL 3.0 license.
 * Copyright (C) 2023, Nick Fenwick. All rights reserved.
 */

require('includes/application_top.php');

use function Zencart\SoundexSearch\{procNameForTableColumn, triggerName};

// Remove the config group and config entries
$db->Execute('DELETE FROM ' . TABLE_CONFIGURATION_GROUP . ' WHERE configuration_group_title = \'Soundex Search Settings\';');
$messageStack->add('Configuration group removed OK', 'success');

$db->Execute('DELETE FROM ' . TABLE_ADMIN_PAGES . ' WHERE page_key = \'configSoundexSearch\';');
$messageStack->add('Admin page removed OK', 'success');

foreach ($configMap as $key => $entry) {
    $db->Execute('DELETE FROM ' . TABLE_CONFIGURATION . " WHERE configuration_key = '$key';");
    $messageStack->add("Configuration key $key removed OK", 'success');
}

// Remove the triggers, then the procedures
foreach ($dbEntityMap as $info) {

    $proc_name = procNameForTableColumn($info['table_name'], $info['column_name']);
    $trigger_name = triggerName($info['trigger_name']);

    $db->Execute("DROP TRIGGER {$trigger_name};");
    $messageStack->add("Trigger $trigger_name removed OK", 'success');
    $db->Execute("DROP PROCEDURE {$proc_name};");
    $messageStack->add("Procedure $proc_name removed OK", 'success');
}

$db->Execute("DROP PROCEDURE soundex_search_init_for_value;");
$messageStack->add("Procedure soundex_search_init_for_value removed OK", 'success');

// Remove the lookup table
$db->Execute('DROP TABLE ' . TABLE_SOUNDEX_LOOKUP);
$messageStack->add("Table " . TABLE_SOUNDEX_LOOKUP . " removed OK", 'success');

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
  </head>
  <body>
    <!-- header //-->
    <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
    <!-- header_eof //-->
    <div class="container-fluid">
      <h1 class="pageHeading">Soundex Uninstallation</h1>
      <p>If you can see this message, uninstallation progress should be reported in the message stack above.</p>
      <p>If all went well, it should be safe to delete the Soundex Search files from your shop now.</p>
    </div>
  </body>
</html>