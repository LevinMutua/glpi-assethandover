<?php
define('PLUGIN_ASSETHANDOVER_VERSION',  '1.1.0');
define('PLUGIN_ASSETHANDOVER_MIN_GLPI', '11.0.0');
define('PLUGIN_ASSETHANDOVER_MAX_GLPI', '11.0.99');

function plugin_version_assethandover() {
    return [
        'name'         => 'Asset Handover & Return',
        'version'      => PLUGIN_ASSETHANDOVER_VERSION,
        'author'       => 'Piseth SEANG',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ASSETHANDOVER_MIN_GLPI,
                'max' => PLUGIN_ASSETHANDOVER_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_assethandover_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_ASSETHANDOVER_MIN_GLPI, 'lt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_ASSETHANDOVER_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_assethandover_check_config($verbose = false) {
    return true;
}

function plugin_init_assethandover() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['assethandover'] = true;

    // GLPI 11: Plugin::registerClass() is removed — use autoloading via PSR-4 or
    // direct class_alias / require. For simple plugins the classes are already
    // loaded via GLPI's autoloader as long as they follow the naming convention.
    // We keep addtabon for Profile via the hook system.
    $PLUGIN_HOOKS['profile_in_config']['assethandover'] = true;

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_assethandover', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['assethandover'] = [
                'assets' => 'PluginAssethandoverHandover',
            ];
        }
    }
}
