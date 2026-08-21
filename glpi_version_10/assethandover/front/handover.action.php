<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkLoginUser();

if (!Session::haveRight('plugin_assethandover', DELETE) && !Session::haveRight('plugin_assethandover', PURGE)) {
    Html::displayRightError();
    exit;
}

$action       = $_POST['action']       ?? '';
$id           = (int)($_POST['id']    ?? 0);
$ids_raw      = $_POST['ids']          ?? '';
$redirect_tab = $_POST['redirect_tab'] ?? 'all';

$ids = [];
if ($ids_raw) {
    $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
}
if ($id > 0) $ids[] = $id;

foreach ($ids as $rid) {
    switch ($action) {
        case 'delete':
        case 'bulk_delete':
            PluginAssethandoverHandover::ahDelete($rid);
            break;
        case 'restore':
        case 'bulk_restore':
            PluginAssethandoverHandover::ahRestore($rid);
            break;
        case 'purge':
        case 'bulk_purge':
            PluginAssethandoverHandover::ahPurge($rid);
            break;
    }
}

Html::redirect('handover.php?tab=' . urlencode($redirect_tab));
exit;
