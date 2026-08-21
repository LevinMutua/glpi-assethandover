<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkLoginUser();
if (!Session::haveRight('plugin_assethandover', READ)) {
    Html::displayRightError();
    exit;
}

global $DB;

$type   = $_GET['type']   ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE
$where = "WHERE h.is_deleted = 0";
if ($type === 'handover') $where .= " AND h.request_type = 'handover'";
if ($type === 'return')   $where .= " AND h.request_type = 'return'";
if ($search !== '') {
    $s = $DB->escape($search);
    $where .= " AND (u.firstname LIKE '%$s%' OR u.realname LIKE '%$s%' OR h.department LIKE '%$s%')";
}

// Fetch all handovers + items in one go
$sql = "
    SELECT
        h.id,
        h.request_type,
        CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,'')) AS user_fullname,
        h.employee_id,
        h.position,
        h.gender,
        h.department,
        h.unit,
        h.manager_name,
        h.assigned_by,
        h.checked_by,
        h.handover_date,
        h.return_date,
        h.remark,
        h.user_confirmed,
        h.date_creation,
        i.asset_name,
        i.asset_type,
        i.serial_number,
        i.asset_number,
        i.description,
        i.condition_issue,
        i.condition_return,
        i.return_status,
        i.item_remark
    FROM glpi_plugin_assethandover_handovers h
    LEFT JOIN glpi_users u ON u.id = h.users_id
    LEFT JOIN glpi_plugin_assethandover_items i ON i.handovers_id = h.id
    $where
    ORDER BY h.date_creation DESC, h.id ASC, i.sort_order ASC
";

$res  = $DB->query($sql);
$data = [];
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $data[] = $row;
    }
}

// Output CSV with UTF-8 BOM for Excel
$filename = 'asset_handover_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'Record ID', 'Type',
    'Full Name', 'Employee ID', 'Position', 'Gender',
    'Department', 'Unit', 'Manager Name',
    'Handover Date', 'Return Date',
    'Assigned By', 'Checked By',
    'Asset Name', 'Asset Type',
    'Serial Number', 'Asset Number', 'Description',
    'Condition (Issue)', 'Return Status', 'Condition (Return)',
    'Asset Remark', 'User Confirmed', 'Overall Remark',
    'Created At'
]);

foreach ($data as $row) {
    fputcsv($out, [
        $row['id'],
        ucfirst($row['request_type']),
        $row['user_fullname'],
        $row['employee_id'],
        $row['position'],
        $row['gender'],
        $row['department'],
        $row['unit'],
        $row['manager_name'],
        $row['handover_date'],
        $row['return_date'],
        $row['assigned_by'],
        $row['checked_by'],
        $row['asset_name'],
        $row['asset_type'],
        $row['serial_number'],
        $row['asset_number'],
        $row['description'],
        $row['condition_issue'],
        $row['return_status'],
        $row['condition_return'],
        $row['item_remark'],
        $row['user_confirmed'] ? 'Yes' : 'No',
        $row['remark'],
        substr($row['date_creation'], 0, 16),
    ]);
}

fclose($out);
exit;
