<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAssethandoverProfile extends CommonDBTM {

    static $rightname = 'plugin_assethandover';

    static function addDefaultProfileInfos($profiles_id, $rights) {
        global $DB;

        $existing = $DB->query("
            SELECT id FROM glpi_plugin_assethandover_profiles
            WHERE profiles_id = $profiles_id
        ");

        if ($DB->numrows($existing) == 0) {
            $DB->insert('glpi_plugin_assethandover_profiles', [
                'profiles_id' => $profiles_id,
                'rights'      => $rights,
            ]);
        }

        // Also insert into glpi_profilerights if not present
        $check = $DB->query("
            SELECT id FROM glpi_profilerights
            WHERE profiles_id = $profiles_id AND name = 'plugin_assethandover'
        ");
        if ($DB->numrows($check) == 0) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => $profiles_id,
                'name'        => 'plugin_assethandover',
                'rights'      => $rights,
            ]);
        }
    }

    static function createFirstAccess($profiles_id) {
        self::addDefaultProfileInfos($profiles_id, READ | UPDATE | CREATE | DELETE | PURGE);
    }

    static function removeRightsFromSession() {
        if (isset($_SESSION['glpiactiveprofile']['plugin_assethandover'])) {
            unset($_SESSION['glpiactiveprofile']['plugin_assethandover']);
        }
    }
}
