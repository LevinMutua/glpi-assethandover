<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search_assets':
        $q = trim($_GET['q'] ?? '');
        echo json_encode(PluginAssethandoverHandover::searchAssets($q));
        break;

    case 'search_users':
        $q = trim($_GET['q'] ?? '');
        echo json_encode(PluginAssethandoverHandover::getUsers($q));
        break;

        case 'user_detail':
        global $DB;
        $users_id = (int)($_GET['users_id'] ?? 0);
        $detail   = PluginAssethandoverHandover::getUserDetail($users_id);
        if (!is_array($detail)) { $detail = []; }

        // Manager = the user's supervisor in GLPI
        $detail['manager_name'] = '';
        if ($users_id > 0) {
            $_mres = $DB->doQuery("
                SELECT CONCAT(COALESCE(s.firstname,''), ' ', COALESCE(s.realname,'')) AS n,
                       s.name AS login
                FROM glpi_users u
                LEFT JOIN glpi_users s ON s.id = u.users_id_supervisor
                WHERE u.id = $users_id
            ");
            if ($_mres && ($_mrow = $_mres->fetch_assoc())) {
                $mgr = trim($_mrow['n'] ?? '');
                if ($mgr === '') { $mgr = $_mrow['login'] ?? ''; } // fallback to login name
                $detail['manager_name'] = $mgr;
            }
        }
        echo json_encode($detail);
        break;

    case 'asset_history':
        $serial   = trim($_GET['serial']   ?? '');
        $assetnum = trim($_GET['assetnum'] ?? '');
        echo json_encode(PluginAssethandoverHandover::getAssetHistory($serial, $assetnum));
        break;

    case 'handover_for_return':
        $handover_id = (int)($_GET['handover_id'] ?? 0);
        $data = PluginAssethandoverHandover::getHandoverForReturn($handover_id);
        echo json_encode($data ?: ['error' => 'Not found']);
        break;

    case 'check_asset_active':
        $asset_id      = (int)($_GET['asset_id']      ?? 0);
        $asset_itemtype= trim($_GET['asset_itemtype']  ?? '');
        $serial_number = trim($_GET['serial_number']   ?? '');
        $asset_number  = trim($_GET['asset_number']    ?? '');
        $exclude_id    = (int)($_GET['exclude_id']     ?? 0);
        $conflict = PluginAssethandoverHandover::checkAssetActive(
            $asset_id, $asset_itemtype, $serial_number, $asset_number, $exclude_id
        );
        echo json_encode($conflict ?: null);
        break;

    case 'quick_view':
        $id = (int)($_GET['id'] ?? 0);
        $data = PluginAssethandoverHandover::getQuickView($id);
        echo json_encode($data ?: ['error' => 'Not found']);
        break;

    case 'user_records':
        $users_id = (int)($_GET['users_id'] ?? 0);
        if (!$users_id) { echo json_encode([]); break; }
        global $DB;
        // GLPI 11: complex SQL with subqueries must use doQuery(), not request()
        $records = [];
        $_sql = "
            SELECT h.id, h.request_type, h.handover_date, h.return_date,
                   h.department, h.assigned_by, h.checked_by, h.date_creation,
                   CASE
                     WHEN h.request_type = 'return' THEN 'returned'
                     WHEN EXISTS (
                       SELECT 1 FROM glpi_plugin_assethandover_handovers r
                       WHERE r.from_handover_id = h.id AND r.request_type = 'return' AND r.is_deleted = 0
                     ) THEN 'returned'
                     ELSE 'active'
                   END AS status,
                   (SELECT GROUP_CONCAT(i.asset_name SEPARATOR ', ')
                    FROM glpi_plugin_assethandover_items i
                    WHERE i.handovers_id = h.id) AS asset_names,
                   (SELECT COUNT(*) FROM glpi_plugin_assethandover_items i
                    WHERE i.handovers_id = h.id) AS asset_count
            FROM glpi_plugin_assethandover_handovers h
            WHERE h.users_id = $users_id AND h.is_deleted = 0
            ORDER BY h.date_creation DESC
        ";
        $_res = $DB->doQuery($_sql);
        if ($_res) { while ($row = $_res->fetch_assoc()) $records[] = $row; }
        echo json_encode($records);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
exit;
