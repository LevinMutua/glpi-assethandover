<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAssethandoverHandover extends CommonDBTM {

    static $rightname = 'plugin_assethandover';

    static function getMenuName(): string {
        return 'Asset Handover';
    }

    static function getMenuContent() {
        if (!Session::haveRight(self::$rightname, READ)) {
            return false;
        }
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = Plugin::getWebDir('assethandover') . '/front/handover.php';
        $menu['icon']  = 'fas fa-exchange-alt';
        $menu['links']['search'] = Plugin::getWebDir('assethandover') . '/front/handover.php';
        $menu['links']['add']    = Plugin::getWebDir('assethandover') . '/front/handover.form.php?request_type=handover';
        return $menu;
    }

    static function canCreate(): bool {
        return Session::haveRight(self::$rightname, CREATE);
    }

    static function canView(): bool {
        return Session::haveRight(self::$rightname, READ);
    }

    // -------------------------------------------------------------------------
    // GLPI 11 helper: replaces $DB->escape() which is removed in GLPI 11.
    // Uses PDO-style quoting via the underlying connection.
    // -------------------------------------------------------------------------
    private static function esc($value) {
        global $DB;
        // DBmysql::escape() is removed in GLPI 11; use quote() and strip outer quotes.
        if (method_exists($DB, 'escape')) {
            return $DB->escape($value); // GLPI 10 fallback (should not be reached here)
        }
        // GLPI 11: $DB->connection is a mysqli; use real_escape_string.
        return $DB->connection->real_escape_string($value);
    }

    // -------------------------------------------------------------------------
    // GLPI 11 helper: replaces $DB->fetchAssoc() which is removed in GLPI 11.
    // -------------------------------------------------------------------------
    private static function fetchOne($res) {
        global $DB;
        if (method_exists($DB, 'fetchAssoc')) {
            return $DB->fetchAssoc($res); // GLPI 10 fallback
        }
        return $res->fetch_assoc(); // GLPI 11: mysqli_result
    }

    // -------------------------------------------------------------------------
    // GLPI 11 helper: replaces $DB->query() / queryOrDie() with doQuery() / doQueryOrDie().
    // -------------------------------------------------------------------------
    private static function doQuery($sql) {
        global $DB;
        if (method_exists($DB, 'doQuery')) {
            return $DB->doQuery($sql);
        }
        return $DB->query($sql); // GLPI 10 fallback
    }

    private static function doQueryOrDie($sql, $msg = '') {
        global $DB;
        if (method_exists($DB, 'doQueryOrDie')) {
            return $DB->doQueryOrDie($sql, $msg);
        }
        return $DB->queryOrDie($sql, $msg); // GLPI 10 fallback
    }

    // -------------------------------------------------------------------------
    // List view
    // -------------------------------------------------------------------------
    static function getList($params = []) {
        global $DB;

        $type      = $params['type']      ?? 'all';
        $search    = $params['search']    ?? '';
        $orderby   = $params['orderby']   ?? 'h.date_creation';
        $order_dir = strtoupper($params['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit     = (int)($params['limit']  ?? 25);
        $offset    = (int)($params['offset'] ?? 0);
        $deleted   = (int)($params['deleted'] ?? 0);

        $where = "WHERE h.is_deleted = $deleted";

        if ($type === 'handover') {
            $where .= " AND h.request_type = 'handover'";
        } elseif ($type === 'return') {
            $where .= " AND h.request_type = 'return'";
        }

        if ($search !== '') {
            $s = self::esc($search);
            $where .= " AND (u.firstname LIKE '%$s%' OR u.realname LIKE '%$s%'
                          OR h.department LIKE '%$s%' OR h.unit LIKE '%$s%'
                          OR h.assigned_by LIKE '%$s%' OR h.checked_by LIKE '%$s%')";
        }

        $allowed_orders = [
            'h.date_creation','h.handover_date','h.return_date',
            'h.request_type','h.department','u.realname'
        ];
        if (!in_array($orderby, $allowed_orders)) {
            $orderby = 'h.date_creation';
        }

        $sql = "
            SELECT h.*,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS user_fullname,
                   (SELECT COUNT(*) FROM glpi_plugin_assethandover_items i WHERE i.handovers_id = h.id) AS asset_count,
                   CASE
                     WHEN h.request_type = 'return' THEN 'returned'
                     WHEN EXISTS (
                       SELECT 1 FROM glpi_plugin_assethandover_handovers r
                       WHERE r.from_handover_id = h.id AND r.request_type = 'return' AND r.is_deleted = 0
                     ) THEN 'returned'
                     ELSE 'active'
                   END AS status
            FROM glpi_plugin_assethandover_handovers h
            LEFT JOIN glpi_users u ON u.id = h.users_id
            $where
            ORDER BY $orderby $order_dir
            LIMIT $limit OFFSET $offset
        ";

        $count_sql = "
            SELECT COUNT(*) AS total
            FROM glpi_plugin_assethandover_handovers h
            LEFT JOIN glpi_users u ON u.id = h.users_id
            $where
        ";

        $rows = [];
        $res  = self::doQuery($sql);
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $rows[] = $row;
            }
        }

        $total = 0;
        $cres  = self::doQuery($count_sql);
        if ($cres) {
            $crow  = self::fetchOne($cres);
            $total = (int)$crow['total'];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    // -------------------------------------------------------------------------
    // Get single handover with items
    // -------------------------------------------------------------------------
    static function getHandover($id) {
        global $DB;
        $id = (int)$id;

        $sql = "
            SELECT h.*,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS user_fullname
            FROM glpi_plugin_assethandover_handovers h
            LEFT JOIN glpi_users u ON u.id = h.users_id
            WHERE h.id = $id
        ";
        $res      = self::doQuery($sql);
        $handover = $res ? self::fetchOne($res) : null;
        if (!$handover) return null;

        $isql = "
            SELECT i.*
            FROM glpi_plugin_assethandover_items i
            WHERE i.handovers_id = $id
            ORDER BY i.sort_order ASC, i.id ASC
        ";
        $ires  = self::doQuery($isql);
        $items = [];
        if ($ires) {
            while ($item = self::fetchOne($ires)) {
                $items[] = $item;
            }
        }
        $handover['items'] = $items;

        return $handover;
    }

    // -------------------------------------------------------------------------
    // Save handover
    // -------------------------------------------------------------------------
    static function saveHandover($data) {
        global $DB;

        $id           = (int)($data['id'] ?? 0);
        $request_type = in_array($data['request_type'] ?? '', ['handover','return']) ? $data['request_type'] : 'handover';
        $users_id     = (int)($data['users_id'] ?? 0);
        $department   = self::esc($data['department']  ?? '');
        $unit         = self::esc($data['unit']        ?? '');
        $position     = self::esc($data['position']    ?? '');
        $gender       = self::esc($data['gender']      ?? '');
        $employee_id  = self::esc($data['employee_id'] ?? '');
        $manager_name = self::esc($data['manager_name'] ?? '');
        $assigned_by  = self::esc($data['assigned_by'] ?? '');
        $checked_by   = self::esc($data['checked_by']  ?? '');
        $remark       = self::esc($data['remark']      ?? '');
        $user_confirmed = (int)($data['user_confirmed'] ?? 0);
        $handover_date  = !empty($data['handover_date']) ? "'" . self::esc($data['handover_date']) . "'" : 'NULL';
        $return_date    = !empty($data['return_date'])   ? "'" . self::esc($data['return_date'])   . "'" : 'NULL';
        $from_handover_id = (int)($data['from_handover_id'] ?? 0);
        $fh_val           = $from_handover_id > 0 ? $from_handover_id : 'NULL';
        $created_by       = (int)Session::getLoginUserID();

        if ($id > 0) {
            self::doQueryOrDie("
                UPDATE glpi_plugin_assethandover_handovers SET
                    request_type   = '$request_type',
                    users_id       = $users_id,
                    department     = '$department',
                    unit           = '$unit',
                    position       = '$position',
                    gender         = '$gender',
                    employee_id    = '$employee_id',
                    manager_name   = '$manager_name',
                    assigned_by    = '$assigned_by',
                    checked_by     = '$checked_by',
                    remark         = '$remark',
                    user_confirmed = $user_confirmed,
                    handover_date  = $handover_date,
                    return_date    = $return_date
                WHERE id = $id
            ", "Cannot update handover");
        } else {
            self::doQueryOrDie("
                INSERT INTO glpi_plugin_assethandover_handovers
                    (request_type, users_id, department, unit, position, gender, employee_id, manager_name,
                     from_handover_id, assigned_by, checked_by, remark, user_confirmed, handover_date, return_date, created_by)
                VALUES
                    ('$request_type', $users_id, '$department', '$unit', '$position', '$gender', '$employee_id',
                     '$manager_name', $fh_val, '$assigned_by', '$checked_by', '$remark', $user_confirmed, $handover_date, $return_date, $created_by)
            ", "Cannot insert handover");
            $id = $DB->insertId();
        }

        // Re-save items
        self::doQueryOrDie("DELETE FROM glpi_plugin_assethandover_items WHERE handovers_id = $id", "Cannot clear items");

        $items = $data['items'] ?? [];
        foreach ($items as $idx => $item) {
            $asset_id        = (int)($item['asset_id']        ?? 0);
            $asset_itemtype  = self::esc($item['asset_itemtype'] ?? '');
            $asset_name      = self::esc($item['asset_name']     ?? '');
            $asset_type      = self::esc($item['asset_type']     ?? '');
            $description     = self::esc($item['description']    ?? '');
            $serial_number   = self::esc($item['serial_number']  ?? '');
            $asset_number    = self::esc($item['asset_number']   ?? '');
            $condition_issue = self::esc($item['condition_issue'] ?? '');
            $condition_return= self::esc($item['condition_return']?? '');
            $return_status   = self::esc($item['return_status']  ?? '');
            $item_remark     = self::esc($item['item_remark']    ?? '');
            $sort_order      = (int)$idx;

            $ci_val  = $condition_issue  ? "'$condition_issue'"  : 'NULL';
            $cr_val  = $condition_return ? "'$condition_return'" : 'NULL';
            $rs_val  = $return_status    ? "'$return_status'"    : 'NULL';
            $aid_val = $asset_id > 0     ? $asset_id             : 'NULL';

            self::doQueryOrDie("
                INSERT INTO glpi_plugin_assethandover_items
                    (handovers_id, asset_id, asset_itemtype, asset_name, asset_type,
                     serial_number, asset_number, description, condition_issue, condition_return, return_status, item_remark, sort_order)
                VALUES
                    ($id, $aid_val, '$asset_itemtype', '$asset_name', '$asset_type',
                     '$serial_number', '$asset_number', '$description', $ci_val, $cr_val, $rs_val, '$item_remark', $sort_order)
            ", "Cannot insert handover item");
        }

        return $id;
    }

    // -------------------------------------------------------------------------
    // Delete / Restore / Purge
    // -------------------------------------------------------------------------
    static function ahDelete($id) {
        $id = (int)$id;
        self::doQueryOrDie("UPDATE glpi_plugin_assethandover_handovers SET is_deleted = 1 WHERE id = $id");
        return true;
    }

    static function ahRestore($id) {
        $id = (int)$id;
        self::doQueryOrDie("UPDATE glpi_plugin_assethandover_handovers SET is_deleted = 0 WHERE id = $id");
        return true;
    }

    static function ahPurge($id) {
        $id = (int)$id;
        self::doQueryOrDie("DELETE FROM glpi_plugin_assethandover_items WHERE handovers_id = $id");
        self::doQueryOrDie("DELETE FROM glpi_plugin_assethandover_handovers WHERE id = $id");
        return true;
    }

    // -------------------------------------------------------------------------
    // Search ALL GLPI asset types
    // -------------------------------------------------------------------------
    static function searchAssets($q) {
        $q = self::esc($q);

        $asset_tables = [
            ['glpi_computers',         'Computer',         'glpi_computertypes',         'computertypes_id',         'glpi_computermodels',         'computermodels_id'],
            ['glpi_monitors',          'Monitor',          'glpi_monitortypes',          'monitortypes_id',          'glpi_monitormodels',          'monitormodels_id'],
            ['glpi_printers',          'Printer',          'glpi_printertypes',          'printertypes_id',          'glpi_printermodels',          'printermodels_id'],
            ['glpi_phones',            'Phone',            'glpi_phonetypes',            'phonetypes_id',            'glpi_phonemodels',            'phonemodels_id'],
            ['glpi_peripherals',       'Peripheral',       'glpi_peripheraltypes',       'peripheraltypes_id',       'glpi_peripheralmodels',       'peripheralmodels_id'],
            ['glpi_networkequipments', 'NetworkEquipment', 'glpi_networkequipmenttypes', 'networkequipmenttypes_id', 'glpi_networkequipmentmodels', 'networkequipmentmodels_id'],
        ];

        $results = [];
        foreach ($asset_tables as $def) {
            list($table, $itemtype, $type_table, $type_fk, $model_table, $model_fk) = $def;
            $sql = "
                SELECT a.id,
                       '$itemtype'                          AS itemtype,
                       COALESCE(a.name,'')                  AS asset_name,
                       COALESCE(atype.name,'$itemtype')     AS asset_type,
                       COALESCE(a.serial,'')                AS serial,
                       COALESCE(a.otherserial,'')           AS asset_number,
                       COALESCE(mfr.name,'')                AS manufacturer,
                       COALESCE(mdl.name,'')                AS model
                FROM $table a
                LEFT JOIN $type_table   atype ON atype.id = a.$type_fk
                LEFT JOIN glpi_manufacturers mfr ON mfr.id = a.manufacturers_id
                LEFT JOIN $model_table  mdl   ON mdl.id   = a.$model_fk
                WHERE a.is_deleted = 0
                  AND a.is_template = 0
                  AND (a.name LIKE '%$q%' OR a.otherserial LIKE '%$q%' OR a.serial LIKE '%$q%')
                ORDER BY a.name ASC
                LIMIT 8
            ";
            $res = self::doQuery($sql);
            if ($res) {
                while ($row = self::fetchOne($res)) {
                    $row['description'] = self::buildDescription($row);
                    $results[] = $row;
                }
            }
        }

        usort($results, function($a, $b) { return strcmp($a['asset_name'], $b['asset_name']); });
        return array_slice($results, 0, 30);
    }

    static function buildDescription($row) {
        $parts = [];
        if (!empty($row['manufacturer'])) $parts[] = $row['manufacturer'];
        if (!empty($row['model']))        $parts[] = $row['model'];
        return implode(' | ', $parts);
    }

    // -------------------------------------------------------------------------
    // Get users
    // -------------------------------------------------------------------------
    static function getUsers($q = '') {
        $q = self::esc($q);
        $where = $q ? "AND (u.firstname LIKE '%$q%' OR u.realname LIKE '%$q%' OR u.name LIKE '%$q%')" : '';
        $sql = "
            SELECT u.id,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS fullname,
                   u.name                        AS username,
                   COALESCE(ut.name,'')           AS position,
                   COALESCE(u.registration_number,'') AS employee_id
            FROM glpi_users u
            LEFT JOIN glpi_usertitles ut ON ut.id = u.usertitles_id
            WHERE u.is_deleted = 0 AND u.is_active = 1 $where
            ORDER BY u.realname ASC
            LIMIT 50
        ";
        $res   = self::doQuery($sql);
        $users = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $users[] = $row;
            }
        }
        return $users;
    }

    // -------------------------------------------------------------------------
    // Get user detail for auto-fill
    // -------------------------------------------------------------------------
    static function getUserDetail($users_id) {
        $users_id = (int)$users_id;
        $sql = "
            SELECT COALESCE(uc.name,'')  AS department,
                   COALESCE(ut.name,'')  AS position,
                   COALESCE(u.registration_number,'') AS employee_id
            FROM glpi_users u
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            LEFT JOIN glpi_usertitles     ut ON ut.id = u.usertitles_id
            WHERE u.id = $users_id
        ";
        $res = self::doQuery($sql);
        return $res ? (self::fetchOne($res) ?? []) : [];
    }

    // -------------------------------------------------------------------------
    // Get a handover by ID for prefilling a return form
    // -------------------------------------------------------------------------
    static function getHandoverForReturn($handover_id) {
        $handover = self::getHandover((int)$handover_id);
        if (!$handover || $handover['request_type'] !== 'handover') return null;
        return $handover;
    }

    // -------------------------------------------------------------------------
    // Asset history
    // -------------------------------------------------------------------------
    static function getAssetHistory($serial_number = '', $asset_number = '') {
        if (empty($serial_number) && empty($asset_number)) return [];

        $conditions = [];
        if (!empty($serial_number)) {
            $s = self::esc($serial_number);
            $conditions[] = "i.serial_number = '$s'";
        }
        if (!empty($asset_number)) {
            $n = self::esc($asset_number);
            $conditions[] = "i.asset_number = '$n'";
        }
        $where_items = '(' . implode(' OR ', $conditions) . ')';

        $sql = "
            SELECT
                h.id            AS handover_id,
                h.request_type,
                h.handover_date,
                h.return_date,
                h.from_handover_id,
                CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS user_fullname,
                h.department,
                h.position,
                h.assigned_by,
                h.checked_by,
                h.date_creation,
                i.asset_name,
                i.asset_id,
                i.asset_itemtype,
                i.serial_number,
                i.asset_number,
                i.condition_issue,
                i.condition_return,
                i.return_status
            FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            LEFT JOIN glpi_users u ON u.id = h.users_id
            WHERE $where_items
              AND h.is_deleted = 0
            ORDER BY h.date_creation ASC
        ";

        $res     = self::doQuery($sql);
        $history = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $history[] = $row;
            }
        }
        return $history;
    }

    // -------------------------------------------------------------------------
    // Get handover list for return dropdown
    // -------------------------------------------------------------------------
    static function getHandoverList() {
        $sql = "
            SELECT h.id,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS user_fullname,
                   h.handover_date,
                   h.department,
                   (SELECT COUNT(*) FROM glpi_plugin_assethandover_items i WHERE i.handovers_id = h.id) AS asset_count,
                   CASE
                     WHEN h.request_type = 'return' THEN 'returned'
                     WHEN EXISTS (
                       SELECT 1 FROM glpi_plugin_assethandover_handovers r
                       WHERE r.from_handover_id = h.id AND r.request_type = 'return' AND r.is_deleted = 0
                     ) THEN 'returned'
                     ELSE 'active'
                   END AS status
            FROM glpi_plugin_assethandover_handovers h
            LEFT JOIN glpi_users u ON u.id = h.users_id
            WHERE h.request_type = 'handover' AND h.is_deleted = 0
            ORDER BY h.date_creation DESC
        ";
        $res  = self::doQuery($sql);
        $list = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $list[] = $row;
            }
        }
        return $list;
    }

    // -------------------------------------------------------------------------
    // Check if asset is already in an active handover
    // -------------------------------------------------------------------------
    static function checkAssetActive($asset_id, $asset_itemtype, $serial_number, $asset_number, $exclude_id = 0) {
        $exclude_id = (int)$exclude_id;

        $conditions = [];
        if ($asset_id > 0) {
            $conditions[] = "(i.asset_id = $asset_id AND i.asset_itemtype = '" . self::esc($asset_itemtype) . "')";
        }
        if (!empty($serial_number)) {
            $s = self::esc($serial_number);
            $conditions[] = "i.serial_number = '$s'";
        }
        if (!empty($asset_number)) {
            $n = self::esc($asset_number);
            $conditions[] = "i.asset_number = '$n'";
        }
        if (empty($conditions)) return null;

        $where_items = implode(' OR ', $conditions);
        $exclude_sql = $exclude_id > 0 ? "AND h.id != $exclude_id" : "";

        $sql = "
            SELECT h.id, h.handover_date,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS user_fullname,
                   i.asset_name
            FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            LEFT JOIN glpi_users u ON u.id = h.users_id
            WHERE ($where_items)
              AND h.request_type = 'handover'
              AND h.is_deleted = 0
              $exclude_sql
              AND NOT EXISTS (
                SELECT 1 FROM glpi_plugin_assethandover_handovers r
                WHERE r.from_handover_id = h.id AND r.request_type = 'return' AND r.is_deleted = 0
              )
            LIMIT 1
        ";
        $res = self::doQuery($sql);
        if (!$res) return null;
        // GLPI 11: numrows() is removed — use mysqli_num_rows or check fetchOne
        $row = self::fetchOne($res);
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Quick view panel
    // -------------------------------------------------------------------------
    static function getQuickView($id) {
        return self::getHandover($id);
    }

    // -------------------------------------------------------------------------
    // Activity log: write entry
    // -------------------------------------------------------------------------
    static function logActivity($handovers_id, $action, $field = '', $old_value = '', $new_value = '', $note = '') {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_assethandover_logs')) return;
        $handovers_id = (int)$handovers_id;
        $users_id  = (int)Session::getLoginUserID();
        $un_res    = self::doQuery("SELECT CONCAT(COALESCE(firstname,''),' ',COALESCE(realname,'')) AS n FROM glpi_users WHERE id=$users_id");
        $un_row    = $un_res ? self::fetchOne($un_res) : null;
        $user_name = self::esc(trim($un_row['n'] ?? 'User #'.$users_id));
        $action    = self::esc($action);
        $field     = self::esc($field);
        $old_value = self::esc($old_value);
        $new_value = self::esc($new_value);
        $note      = self::esc($note);
        self::doQuery("
            INSERT INTO glpi_plugin_assethandover_logs
                (handovers_id, users_id, user_name, action, field, old_value, new_value, note, date_log)
            VALUES
                ($handovers_id, $users_id, '$user_name', '$action', '$field', '$old_value', '$new_value', '$note', NOW())
        ");
    }

    // -------------------------------------------------------------------------
    // Activity log: read entries
    // -------------------------------------------------------------------------
    static function getActivityLog($handovers_id) {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_assethandover_logs')) return [];
        $handovers_id = (int)$handovers_id;
        $sql = "
            SELECT l.*, u.firstname, u.realname
            FROM glpi_plugin_assethandover_logs l
            LEFT JOIN glpi_users u ON u.id = l.users_id
            WHERE l.handovers_id = $handovers_id
            ORDER BY l.date_log ASC
        ";
        $res  = self::doQuery($sql);
        $logs = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $logs[] = $row;
            }
        }
        return $logs;
    }

    // -------------------------------------------------------------------------
    // Per-user asset summary
    // -------------------------------------------------------------------------
    static function getUserAssets($users_id) {
        $users_id = (int)$users_id;
        $sql = "
            SELECT i.*,
                   h.id AS handover_id, h.handover_date, h.assigned_by, h.department,
                   CASE
                     WHEN EXISTS (
                       SELECT 1 FROM glpi_plugin_assethandover_handovers r
                       WHERE r.from_handover_id = h.id AND r.request_type = 'return' AND r.is_deleted = 0
                     ) THEN 'returned'
                     ELSE 'active'
                   END AS status
            FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.users_id = $users_id
              AND h.request_type = 'handover'
              AND h.is_deleted = 0
            ORDER BY h.handover_date DESC
        ";
        $res    = self::doQuery($sql);
        $assets = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $assets[] = $row;
            }
        }
        return $assets;
    }

    // -------------------------------------------------------------------------
    // Dashboard stats
    // -------------------------------------------------------------------------
    static function getDashboardStats() {
        $stats = [];

        $res = self::doQuery("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE request_type='handover' AND is_deleted=0");
        $stats['total_handovers'] = $res ? (int)(self::fetchOne($res)['c']) : 0;

        $res = self::doQuery("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers h
            WHERE h.request_type='handover' AND h.is_deleted=0
              AND NOT EXISTS (
                SELECT 1 FROM glpi_plugin_assethandover_handovers r
                WHERE r.from_handover_id = h.id AND r.request_type='return' AND r.is_deleted=0
              )
        ");
        $stats['active'] = $res ? (int)(self::fetchOne($res)['c']) : 0;
        $stats['returned'] = $stats['total_handovers'] - $stats['active'];

        $res = self::doQuery("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='handover' AND h.is_deleted=0
        ");
        $stats['total_assets'] = $res ? (int)(self::fetchOne($res)['c']) : 0;

        $res = self::doQuery("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='return' AND h.is_deleted=0 AND i.condition_return='Damaged'
        ");
        $stats['damaged'] = $res ? (int)(self::fetchOne($res)['c']) : 0;

        $res = self::doQuery("
            SELECT h.department, COUNT(*) AS cnt
            FROM glpi_plugin_assethandover_handovers h
            WHERE h.request_type='handover' AND h.is_deleted=0 AND h.department != ''
              AND NOT EXISTS (
                SELECT 1 FROM glpi_plugin_assethandover_handovers r
                WHERE r.from_handover_id = h.id AND r.request_type='return' AND r.is_deleted=0
              )
            GROUP BY h.department
            ORDER BY cnt DESC
            LIMIT 8
        ");
        $stats['by_dept'] = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $stats['by_dept'][] = $row;
            }
        }

        $res = self::doQuery("
            SELECT DATE_FORMAT(handover_date,'%Y-%m') AS month, COUNT(*) AS cnt
            FROM glpi_plugin_assethandover_handovers
            WHERE request_type='handover' AND is_deleted=0
              AND handover_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month ORDER BY month ASC
        ");
        $stats['monthly'] = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $stats['monthly'][] = $row;
            }
        }

        $res = self::doQuery("
            SELECT i.asset_type, COUNT(*) AS cnt
            FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='handover' AND h.is_deleted=0 AND i.asset_type != ''
            GROUP BY i.asset_type ORDER BY cnt DESC LIMIT 6
        ");
        $stats['by_type'] = [];
        if ($res) {
            while ($row = self::fetchOne($res)) {
                $stats['by_type'][] = $row;
            }
        }

        return $stats;
    }

}
