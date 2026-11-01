<?php
// GLPI 12 boots plugin front scripts via its front controller.

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$id = (int)($_POST['id'] ?? 0);
$required_right = $id > 0 ? UPDATE : CREATE;
if (!Session::haveRight('plugin_assethandover', $required_right)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    exit;
}

$request_type = $_POST['request_type'] ?? 'handover';

// Build items array — skip rows with no asset name
$items = [];
if (isset($_POST['items']) && is_array($_POST['items'])) {
    foreach ($_POST['items'] as $item) {
        $name = trim($item['asset_name'] ?? '');
        if ($name === '' && empty($item['asset_id'])) continue;
        $items[] = $item;
    }
}

$data = [
    'id'            => $id,
    'request_type'  => $request_type,
    'users_id'      => (int)($_POST['users_id']   ?? 0),
    'department'    => $_POST['department']  ?? '',
    'unit'          => $_POST['unit']        ?? '',
    'position'      => $_POST['position']    ?? '',
    'gender'        => $_POST['gender']      ?? '',
    'employee_id'   => $_POST['employee_id'] ?? '',
    'manager_name'     => $_POST['manager_name']     ?? '',
    'from_handover_id'  => (int)($_POST['from_handover_id'] ?? 0),
    'assigned_by'   => $_POST['assigned_by'] ?? '',
    'checked_by'    => $_POST['checked_by']  ?? '',
    'remark'        => $_POST['remark']      ?? '',
    'user_confirmed'=> isset($_POST['user_confirmed']) ? 1 : 0,
    'handover_date' => $_POST['handover_date'] ?? '',
    'return_date'   => $_POST['return_date']   ?? '',
    'items'         => $items,
];

if (empty($data['users_id'])) {
    $_SESSION['hw_error'] = 'User is required.';
    Html::redirect($id > 0 ? "handover.form.php?id=$id" : "handover.form.php?request_type=$request_type");
    exit;
}

$saved_id = PluginAssethandoverHandover::saveHandover($data);

// #7 — Log activity with asset names and record type
$asset_names = [];
foreach ($items as $item) {
    $n = trim($item['asset_name'] ?? '');
    if ($n !== '') $asset_names[] = $n;
}
$assets_str = !empty($asset_names) ? implode(', ', $asset_names) : '';
$type_label = ucfirst($request_type); // Handover / Return

if ($id > 0) {
    PluginAssethandoverHandover::logActivity($saved_id, 'update', $type_label, '', $assets_str, 'Record updated');
} else {
    PluginAssethandoverHandover::logActivity($saved_id, 'create', $type_label, '', $assets_str, 'Record created');
}

$_SESSION['hw_success'] = $id > 0 ? 'Record updated successfully.' : 'Record saved successfully.';
Html::redirect('handover.form.php?id=' . $saved_id);
exit;
