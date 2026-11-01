<?php
define('PLUGIN_ASSETHANDOVER_VERSION',  '1.2.0');
define('PLUGIN_ASSETHANDOVER_MIN_GLPI', '12.0.0');

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
            ],
            'php' => ['min' => '8.3.0'],
        ],
    ];
}

function plugin_assethandover_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_ASSETHANDOVER_MIN_GLPI, 'lt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_ASSETHANDOVER_MIN_GLPI;
        return false;
    }
    if (version_compare(PHP_VERSION, '8.3.0', 'lt')) {
        echo 'This plugin requires PHP >= 8.3';
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

    // Legacy plugin classes under inc/ are autoloaded by GLPI.
    // Rights are stored in glpi_profilerights on install.

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_assethandover', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['assethandover'] = [
                'assets' => 'PluginAssethandoverHandover',
            ];
        }
    }
}
