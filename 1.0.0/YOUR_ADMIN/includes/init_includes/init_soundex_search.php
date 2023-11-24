<?php
/**
 * Part of the Soundex Search plugin by neekfenwick, provided under GPL 3.0 license.
 * Copyright (C) 2023, Nick Fenwick. All rights reserved.
 */

namespace Zencart\SoundexSearch;

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (empty($_SESSION['admin_id'])) {
    return;
}

$version_release_date = SOUNDEX_SEARCH_CURRENT_VERSION . ' (' . SOUNDEX_SEARCH_CURRENT_UPDATE_DATE . ')';

function checkSoundexSearchConfig()
{
    global $db, $messageStack, $configMap;

    $configuration_group_id = null;
    $configurationGroupTitle = 'Soundex Search Settings';
    $configuration = $db->Execute("SELECT configuration_group_id
        FROM " . TABLE_CONFIGURATION_GROUP . "
        WHERE configuration_group_title = '$configurationGroupTitle' LIMIT 1");
    if ($configuration->EOF) {
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
                        (configuration_group_title, configuration_group_description, sort_order, visible)
                        VALUES ('$configurationGroupTitle', '$configurationGroupTitle', 1, 1);");
        $configuration_group_id = $db->Insert_ID();
        $db->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = $configuration_group_id WHERE configuration_group_id = $configuration_group_id");
    } else {
        $configuration_group_id = $configuration->fields['configuration_group_id'];
    }

    $rs = $db->Execute('SELECT COUNT(*) AS cc FROM ' . TABLE_ADMIN_PAGES . ' WHERE page_key = \'configSoundexSearch\';');
    if ((int)$rs->fields['cc'] === 0) {
        zen_db_perform(TABLE_ADMIN_PAGES, [
            'page_key' => 'configSoundexSearch',
            'language_key' => 'BOX_CONFIGURATION_SOUNDEX_SEARCH',
            'main_page' => 'FILENAME_CONFIGURATION',
            'page_params' => "gID=$configuration_group_id",
            'menu_key' => 'configuration',
            'display_on_menu' => 'Y',
            'sort_order' => $configuration_group_id
        ]);
    }
    // $db->Execute("INSERT INTO " . TABLE_ADMIN_PAGES . "
    //     (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order)
    //     VALUES('configSoundexSearch', 'BOX_CONFIGURATION_SOUNDEX_SEARCH', 'FILENAME_CONFIGURATION', 'gID=$configuration_group_id', 'configuration', 'Y', $configuration_group_id");

    // Create our boolean configuration entries for each field we match on.
    $sort_order = 100;
    foreach ($configMap as $configKey => $info) {
        if (!defined($configKey)) {
            $configTitle = "Soundex Search: Match {$info['name']}";
            zen_db_perform(TABLE_CONFIGURATION, [
                'configuration_title' => $configTitle,
                'configuration_key' => $configKey,
                'configuration_value' => $info['default'],
                'configuration_description' => "Should the match be performed against {$info['name']}?  (Default: <b>{$info['default']}</b>)",
                'configuration_group_id' => $configuration_group_id,
                'sort_order' => $sort_order,
                'date_added' => 'now()',
                'use_function' => null,
                'set_function' => 'zen_cfg_select_option(array(\'Yes\', \'No\'),'
            ]);
            $sort_order += 5;
            $messageStack->add(SOUNDEX_SEARCH_NAME . " added configuration item for $configTitle.", 'info');
        }
    }
}

/**
 * Try creating dummy entities before we attempt creating the full set of required entities.
 *
 * @param \queryFactory $db
 * @param string $desc
 * @return void
 */
function checkSoundexSearchDBPermissions() {
    global $db;
    $oldDieOnErrors = $db->dieOnErrors;
    $db->dieOnErrors = false;

    // Can we create tables?
    $db->Execute('CREATE TABLE tmpSS ( id INT );');
    checkSoundexSearchErrors($db, "Checking if we have CREATE TABLE permission.");
    $db->Execute('DROP TABLE tmpSS;');

    // Can we create procedures?
    $db->Execute("CREATE OR REPLACE PROCEDURE tmpSS (
        IN table_name VARCHAR(100), IN field_name VARCHAR(100), IN each_id INT, IN in_value VARCHAR(200))
    BEGIN
        -- Do nothing
    END;");
    checkSoundexSearchErrors($db, "Checking if we have CREATE PROCEDURE permission.");
    $db->Execute('DROP PROCEDURE tmpSS;');

    // Can we create triggers?
    $db->Execute("CREATE OR REPLACE TRIGGER tmpSS
    AFTER UPDATE
    ON " . TABLE_PRODUCTS . " FOR EACH ROW
    BEGIN
        -- Do nothing
    END");
    checkSoundexSearchErrors($db, "Checking if we have CREATE TRIGGER permission.");
    $db->Execute('DROP TRIGGER tmpSS;');

    $db->dieOnErrors = $oldDieOnErrors;
}

/**
 * Check queryFactory for errors and throw if any.
 *
 * @param \queryFactory $db
 * @param string $desc
 * @return void
 */
function checkSoundexSearchErrors($db, $desc) {
    global $version_release_date;
    if ($db->error_number > 0) {
        throw new \Exception("Error during Soundex Search $version_release_date intialisation: $desc - " .
            "({$db->error_number}): {$db->error_text}");
    }
}

function createSoundexSearchDBEntities() {
    global $db, $messageStack, $dbEntityMap;
    // Could set $db->dieOnErrors to false...
    checkSoundexSearchDBPermissions();
    $oldDieOnErrors = $db->dieOnErrors;
    $db->dieOnErrors = false;

    // Create the main lookup table that searches are performed against.
    $db->Execute('CREATE TABLE ' . TABLE_SOUNDEX_LOOKUP . ' (
        id int NOT NULL,
        table_name varchar(100) NOT NULL,
        column_name varchar(100) NOT NULL,
        word varchar(200) NOT NULL,
        word_soundex VARCHAR(200) NOT NULL,
        PRIMARY KEY (id, table_name, column_name, word_soundex)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    checkSoundexSearchErrors($db, "Creating TABLE " . TABLE_SOUNDEX_LOOKUP);

    // Create a procedure that can take any string, split it around whitespace, and insert
    // into the lookup table, linking each table and column's word to its SOUNDEX equivalent.
    // if (!procedure_exists('soundex_search_init_for_value')) {
    $db->Execute("CREATE OR REPLACE PROCEDURE soundex_search_init_for_value (
        IN table_name VARCHAR(100), IN field_name VARCHAR(100), IN each_id INT, IN in_value VARCHAR(200))
    BEGIN
        DECLARE str_idx int default 0;
        DECLARE last_str_idx int default 0;
        DECLARE each_word VARCHAR(200);
        DECLARE debug_count int default 0;
        -- Sanitise inputs.
        SET in_value = REGEXP_REPLACE(TRIM(REGEXP_REPLACE(in_value, '\r|\n', '')), '<[^>]*>+', '');
        -- Split the name around spaces so we get each word.
        SET str_idx = 1;
        -- While we have not iterated off the end of the string...
        WHILE str_idx < CHAR_LENGTH(in_value) AND debug_count < 20 DO
            SET last_str_idx = str_idx;
            SET str_idx = LOCATE(' ', in_value, last_str_idx);
            -- The final word in the string will return 0 because ' ' was not found.
            IF str_idx = 0 THEN
                SET str_idx = CHAR_LENGTH(in_value) + 1;
            END IF;
            -- Only bother with words at least 3 chars long.
            IF (str_idx - last_str_idx) > 2 THEN
                -- Insert each word found into table, ON DUPLICATE skips dups, composite PRIMARY KEY is UNIQUE.
                SET each_word = SUBSTRING(in_value, last_str_idx, str_idx - last_str_idx);
                INSERT INTO soundex_lookup (id, table_name, column_name, word, word_soundex)
                    VALUES (each_id, table_name, field_name, each_word, SOUNDEX(each_word))
                    ON DUPLICATE KEY UPDATE id = id;
            END IF;
            IF str_idx <> 0 THEN
                SET str_idx = str_idx + 1;
            END IF;
        END WHILE;
    END");
    checkSoundexSearchErrors($db, "Creating PROCEDURE soundex_search_init_for_value");

    /**
     * Store Procedures.
     * Note that while we can write a single stored procedure that dynamically builds a view
     * using the table and column names passed in, this cannot be called from the trigger
     * ("Dynamic SQL is not allowed in stored function or trigger") so we create multiple procedures
     * that differ only in the names used.
     * trigger_name used because concatenating the table name and column results in 1059 "Identifier name 'foo' is too long"
     */

    foreach ($dbEntityMap as $info) {

        // $proc_name = "soundex_init_{$info['table_name']}_{$info['column_name']}";
        // $trigger_name = "soundex_{$info['trigger_name']}_update";
        $proc_name = procNameForTableColumn($info['table_name'], $info['column_name']);
        $trigger_name = triggerName($info['trigger_name']);

        // Create a procedure customised for a specific table and column.
        // Note: We cannot begin a transaction in this procedure because it will be called from a trigger.
        // When calling for a mass update, wrap it in your own transaction before calling, otherwise
        // the many INSERT statements it executes will be extremely slow.
        $db->Execute("CREATE OR REPLACE PROCEDURE {$proc_name} (IN only_id INT)
            BEGIN
                DECLARE ch_done INT DEFAULT 0;
                DECLARE each_field_value VARCHAR(200);
                DECLARE each_id int default 0;
                DECLARE select_values CURSOR FOR SELECT {$info['key_name']}, {$info['column_name']} FROM {$info['table_name']}
                    WHERE {$info['key_name']} = CASE WHEN only_id > 0 THEN only_id ELSE {$info['key_name']} END
                    ORDER BY {$info['key_name']};
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET ch_done = 1;
                -- START TRANSACTION;
                OPEN select_values;
                IF(ch_done <> 1) THEN
                    DELETE FROM soundex_lookup WHERE table_name = '{$info['table_name']}' AND column_name = '{$info['column_name']}'
                    AND id = CASE WHEN only_id > 0 THEN only_id ELSE id END;
                    read_loop: LOOP
                        FETCH select_values INTO each_id, each_field_value;
                        if ch_done = 1 then
                            LEAVE read_loop;
                        END IF;
                        CALL soundex_search_init_for_value('{$info['table_name']}', '{$info['column_name']}', each_id, each_field_value);
                    END LOOP;
                END IF;
                CLOSE select_values;
                -- COMMIT;
            END");
        checkSoundexSearchErrors($db, "Creating PROCEDURE {$proc_name}");

        // Create a trigger so when the field we're concerned with changes, all the stored SOUNDEX values are updated.
        $db->Execute("CREATE OR REPLACE TRIGGER {$trigger_name}
            AFTER UPDATE
            ON {$info['table_name']} FOR EACH ROW
            BEGIN
                CALL init_{$info['table_name']}_{$info['column_name']}_soundex (
                    NEW.{$info['key_name']}
                );
            END");
        checkSoundexSearchErrors($db, "Creating TRIGGER {$trigger_name}");

        // Initialise the lookup table for this field name.
        $db->Execute("START TRANSACTION;");
        $db->Execute("CALL {$proc_name}(0);");
        $db->Execute("COMMIT;");

    }

    $db->dieOnErrors = $oldDieOnErrors;
    $messageStack->add(sprintf(\SOUNDEX_INSTALL_SUCCESS, SOUNDEX_SEARCH_NAME), 'info');
}

checkSoundexSearchConfig();

// If the main lookup table doesn't exist, re-create all database entities.
if (!$sniffer->table_exists(TABLE_SOUNDEX_LOOKUP)) {
    try {
        createSoundexSearchDBEntities();
    } catch (\Exception $ex) {
        $messageStack->add(sprintf(\SOUNDEX_INSTALL_ERROR, $ex->getMessage()));
    }
}
