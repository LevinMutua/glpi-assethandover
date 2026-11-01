<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAssethandoverProfile extends CommonDBTM {

    public static string $rightname = 'plugin_assethandover';

    static function addDefaultProfileInfos($profiles_id, $rights) {
        global $DB;

        // Also insert into glpi_profilerights if not present
        $right_count = 0;
        foreach ($DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => $profiles_id, 'name' => 'plugin_assethandover'],
            'LIMIT' => 1,
        ]) as $row) {
            $right_count++;
        }

        if ($right_count == 0) {
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
