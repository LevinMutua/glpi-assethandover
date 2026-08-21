<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAssethandoverHandover extends CommonDBTM {

    static $rightname = 'plugin_assethandover';

    static function getMenuName() {
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

    static function canCreate() {
        return Session::haveRight(self::$rightname, CREATE);
    }

    static function canView() {
        return Session::haveRight(self::$rightname, READ);
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
            $s = $DB->escape($search);
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
        $res  = $DB->query($sql);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $rows[] = $row;
            }
        }

        $total = 0;
        $cres  = $DB->query($count_sql);
        if ($cres) {
            $crow  = $DB->fetchAssoc($cres);
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
        $res      = $DB->query($sql);
        $handover = $res ? $DB->fetchAssoc($res) : null;
        if (!$handover) return null;

        $isql = "
            SELECT i.*
            FROM glpi_plugin_assethandover_items i
            WHERE i.handovers_id = $id
            ORDER BY i.sort_order ASC, i.id ASC
        ";
        $ires  = $DB->query($isql);
        $items = [];
        if ($ires) {
            while ($item = $DB->fetchAssoc($ires)) {
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
        $department   = $DB->escape($data['department']  ?? '');
        $unit         = $DB->escape($data['unit']        ?? '');
        $position     = $DB->escape($data['position']    ?? '');
        $gender       = $DB->escape($data['gender']      ?? '');
        $employee_id  = $DB->escape($data['employee_id'] ?? '');
        $manager_name = $DB->escape($data['manager_name'] ?? '');
        $assigned_by  = $DB->escape($data['assigned_by'] ?? '');
        $checked_by   = $DB->escape($data['checked_by']  ?? '');
        $remark       = $DB->escape($data['remark']      ?? '');
        $user_confirmed = (int)($data['user_confirmed'] ?? 0);
        $handover_date  = !empty($data['handover_date']) ? "'" . $DB->escape($data['handover_date']) . "'" : 'NULL';
        $return_date    = !empty($data['return_date'])   ? "'" . $DB->escape($data['return_date'])   . "'" : 'NULL';
        $from_handover_id = (int)($data['from_handover_id'] ?? 0);
        $fh_val           = $from_handover_id > 0 ? $from_handover_id : 'NULL';
        $created_by       = (int)Session::getLoginUserID();

        if ($id > 0) {
            $DB->queryOrDie("
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
            $DB->queryOrDie("
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
        $DB->queryOrDie("DELETE FROM glpi_plugin_assethandover_items WHERE handovers_id = $id", "Cannot clear items");

        $items = $data['items'] ?? [];
        foreach ($items as $idx => $item) {
            $asset_id        = (int)($item['asset_id']        ?? 0);
            $asset_itemtype  = $DB->escape($item['asset_itemtype'] ?? '');
            $asset_name      = $DB->escape($item['asset_name']     ?? '');
            $asset_type      = $DB->escape($item['asset_type']     ?? '');
            $description     = $DB->escape($item['description']    ?? '');
            $serial_number   = $DB->escape($item['serial_number']  ?? '');
            $asset_number    = $DB->escape($item['asset_number']   ?? '');
            $condition_issue = $DB->escape($item['condition_issue'] ?? '');
            $condition_return= $DB->escape($item['condition_return']?? '');
            $return_status   = $DB->escape($item['return_status']  ?? '');
            $item_remark     = $DB->escape($item['item_remark']    ?? '');
            $sort_order      = (int)$idx;

            $ci_val  = $condition_issue  ? "'$condition_issue'"  : 'NULL';
            $cr_val  = $condition_return ? "'$condition_return'" : 'NULL';
            $rs_val  = $return_status    ? "'$return_status'"    : 'NULL';
            $aid_val = $asset_id > 0     ? $asset_id             : 'NULL';

            $DB->queryOrDie("
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
    // Delete / Restore / Purge  (prefixed to avoid CommonDBTM conflict)
    // -------------------------------------------------------------------------
    static function ahDelete($id) {
        global $DB;
        $id = (int)$id;
        $DB->queryOrDie("UPDATE glpi_plugin_assethandover_handovers SET is_deleted = 1 WHERE id = $id");
        return true;
    }

    static function ahRestore($id) {
        global $DB;
        $id = (int)$id;
        $DB->queryOrDie("UPDATE glpi_plugin_assethandover_handovers SET is_deleted = 0 WHERE id = $id");
        return true;
    }

    static function ahPurge($id) {
        global $DB;
        $id = (int)$id;
        $DB->queryOrDie("DELETE FROM glpi_plugin_assethandover_items WHERE handovers_id = $id");
        $DB->queryOrDie("DELETE FROM glpi_plugin_assethandover_handovers WHERE id = $id");
        return true;
    }

    // -------------------------------------------------------------------------
    // Search ALL GLPI asset types
    // Covers: Computer, Monitor, Printer, Phone, Peripheral, NetworkEquipment,
    //         Software, Cartridge, Consumable, PassiveDCEquipment, etc.
    // -------------------------------------------------------------------------
    static function searchAssets($q) {
        global $DB;
        $q = $DB->escape($q);

        // [table, itemtype, type_table, type_fk, model_table, model_fk]
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
            $res = $DB->query($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $row['description'] = self::buildDescription($row);
                    // serial and asset_number already in row as 'serial' and 'asset_number'
                    $results[] = $row;
                }
            }
        }

        usort($results, function($a, $b) { return strcmp($a['asset_name'], $b['asset_name']); });
        return array_slice($results, 0, 30);
    }

    static function buildDescription($row) {
        // Description = Manufacturer | Model only (S/N and Asset# are separate columns)
        $parts = [];
        if (!empty($row['manufacturer'])) $parts[] = $row['manufacturer'];
        if (!empty($row['model']))        $parts[] = $row['model'];
        return implode(' | ', $parts);
    }

    // -------------------------------------------------------------------------
    // Get users with title (position) and registration_number (employee_id)
    // -------------------------------------------------------------------------
    static function getUsers($q = '') {
        global $DB;
        $q = $DB->escape($q);
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
        $res   = $DB->query($sql);
        $users = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $users[] = $row;
            }
        }
        return $users;
    }

    // -------------------------------------------------------------------------
    // Get user detail for auto-fill (position, employee_id, department)
    // -------------------------------------------------------------------------
    static function getUserDetail($users_id) {
        global $DB;
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
        $res = $DB->query($sql);
        return $res ? ($DB->fetchAssoc($res) ?? []) : [];
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
    // Asset history: all handover/return events for a given asset
    // by serial_number or asset_number
    // -------------------------------------------------------------------------
    static function getAssetHistory($serial_number = '', $asset_number = '') {
        global $DB;

        if (empty($serial_number) && empty($asset_number)) return [];

        $conditions = [];
        if (!empty($serial_number)) {
            $s = $DB->escape($serial_number);
            $conditions[] = "i.serial_number = '$s'";
        }
        if (!empty($asset_number)) {
            $n = $DB->escape($asset_number);
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

        $res     = $DB->query($sql);
        $history = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $history[] = $row;
            }
        }
        return $history;
    }

    // -------------------------------------------------------------------------
    // Get list of handovers for "create return from" dropdown
    // Only returns active (non-deleted) handover-type records
    // -------------------------------------------------------------------------
    static function getHandoverList() {
        global $DB;
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
        $res  = $DB->query($sql);
        $list = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $list[] = $row;
            }
        }
        return $list;
    }

    // -------------------------------------------------------------------------
    // #5 — Check if asset is already in an active handover (duplicate guard)
    // -------------------------------------------------------------------------
    static function checkAssetActive($asset_id, $asset_itemtype, $serial_number, $asset_number, $exclude_id = 0) {
        global $DB;
        $exclude_id = (int)$exclude_id;

        $conditions = [];
        if ($asset_id > 0) {
            $conditions[] = "(i.asset_id = $asset_id AND i.asset_itemtype = '" . $DB->escape($asset_itemtype) . "')";
        }
        if (!empty($serial_number)) {
            $s = $DB->escape($serial_number);
            $conditions[] = "i.serial_number = '$s'";
        }
        if (!empty($asset_number)) {
            $n = $DB->escape($asset_number);
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
        $res = $DB->query($sql);
        return ($res && $DB->numrows($res) > 0) ? $DB->fetchAssoc($res) : null;
    }

    // -------------------------------------------------------------------------
    // #6 — Get full record for quick view panel (list view)
    // -------------------------------------------------------------------------
    static function getQuickView($id) {
        return self::getHandover($id);
    }

    // -------------------------------------------------------------------------
    // #7 — Activity log: write entry
    // -------------------------------------------------------------------------
    static function logActivity($handovers_id, $action, $field = '', $old_value = '', $new_value = '', $note = '') {
        global $DB;
        // Guard: table may not exist yet if plugin not reinstalled after update
        if (!$DB->tableExists('glpi_plugin_assethandover_logs')) return;
        $handovers_id = (int)$handovers_id;
        $users_id  = (int)Session::getLoginUserID();
        $un_res    = $DB->query("SELECT CONCAT(COALESCE(firstname,''),' ',COALESCE(realname,'')) AS n FROM glpi_users WHERE id=$users_id");
        $un_row    = $un_res ? $DB->fetchAssoc($un_res) : null;
        $user_name = $DB->escape(trim($un_row['n'] ?? 'User #'.$users_id));
        $action       = $DB->escape($action);
        $field        = $DB->escape($field);
        $old_value    = $DB->escape($old_value);
        $new_value    = $DB->escape($new_value);
        $note         = $DB->escape($note);
        $DB->query("
            INSERT INTO glpi_plugin_assethandover_logs
                (handovers_id, users_id, user_name, action, field, old_value, new_value, note, date_log)
            VALUES
                ($handovers_id, $users_id, '$user_name', '$action', '$field', '$old_value', '$new_value', '$note', NOW())
        ");
    }

    // -------------------------------------------------------------------------
    // #7 — Activity log: read entries for a handover
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
        $res  = $DB->query($sql);
        $logs = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $logs[] = $row;
            }
        }
        return $logs;
    }

    // -------------------------------------------------------------------------
    // #4 — Per-user asset summary
    // -------------------------------------------------------------------------
    static function getUserAssets($users_id) {
        global $DB;
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
        $res    = $DB->query($sql);
        $assets = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $assets[] = $row;
            }
        }
        return $assets;
    }

    // -------------------------------------------------------------------------
    // #8 — Dashboard stats
    // -------------------------------------------------------------------------
    static function getDashboardStats() {
        global $DB;
        $stats = [];

        // Total handovers
        $res = $DB->query("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE request_type='handover' AND is_deleted=0");
        $stats['total_handovers'] = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;

        // Active (no matching return)
        $res = $DB->query("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers h
            WHERE h.request_type='handover' AND h.is_deleted=0
              AND NOT EXISTS (
                SELECT 1 FROM glpi_plugin_assethandover_handovers r
                WHERE r.from_handover_id = h.id AND r.request_type='return' AND r.is_deleted=0
              )
        ");
        $stats['active'] = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;

        // Returned
        $stats['returned'] = $stats['total_handovers'] - $stats['active'];

        // Total assets handed out
        $res = $DB->query("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='handover' AND h.is_deleted=0
        ");
        $stats['total_assets'] = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;

        // Damaged assets (returned as damaged)
        $res = $DB->query("
            SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='return' AND h.is_deleted=0 AND i.condition_return='Damaged'
        ");
        $stats['damaged'] = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;

        // By department (active handovers)
        $res = $DB->query("
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
            while ($row = $DB->fetchAssoc($res)) {
                $stats['by_dept'][] = $row;
            }
        }

        // Monthly handovers last 6 months
        $res = $DB->query("
            SELECT DATE_FORMAT(handover_date,'%Y-%m') AS month, COUNT(*) AS cnt
            FROM glpi_plugin_assethandover_handovers
            WHERE request_type='handover' AND is_deleted=0
              AND handover_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month ORDER BY month ASC
        ");
        $stats['monthly'] = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $stats['monthly'][] = $row;
            }
        }

        // Asset types distribution
        $res = $DB->query("
            SELECT i.asset_type, COUNT(*) AS cnt
            FROM glpi_plugin_assethandover_items i
            JOIN glpi_plugin_assethandover_handovers h ON h.id = i.handovers_id
            WHERE h.request_type='handover' AND h.is_deleted=0 AND i.asset_type != ''
            GROUP BY i.asset_type ORDER BY cnt DESC LIMIT 6
        ");
        $stats['by_type'] = [];
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $stats['by_type'][] = $row;
            }
        }

        return $stats;
    }

}
