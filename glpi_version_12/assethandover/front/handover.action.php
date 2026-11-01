<?php
// GLPI 12 boots plugin front scripts via its front controller.

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$action       = $_POST['action']       ?? '';
$id           = (int)($_POST['id']    ?? 0);
$ids_raw      = $_POST['ids']          ?? '';
$redirect_tab = $_POST['redirect_tab'] ?? 'all';

$required_right = match ($action) {
    'delete', 'bulk_delete' => DELETE,
    'restore', 'bulk_restore' => DELETE,
    'purge', 'bulk_purge' => PURGE,
    default => null,
};
if ($required_right === null) {
    http_response_code(400);
    exit('Invalid action');
}
if (!Session::haveRight('plugin_assethandover', $required_right)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    exit;
}

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
