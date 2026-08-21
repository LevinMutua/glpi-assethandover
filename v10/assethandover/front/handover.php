<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkLoginUser();
if (!Session::haveRight('plugin_assethandover', READ)) {
    Html::displayRightError();
    exit;
}

Html::header('Asset Handover & Return', '', 'assets', 'PluginAssethandoverHandover');

$tab        = $_GET['tab']       ?? 'all';
$search     = $_GET['search']    ?? '';
$orderby    = $_GET['orderby']   ?? 'h.date_creation';
$order_dir  = $_GET['order_dir'] ?? 'DESC';
$limit      = (int)($_GET['limit'] ?? 25);
$page       = (int)($_GET['page']  ?? 1);
$offset     = ($page - 1) * $limit;
$deleted    = ($tab === 'trash') ? 1 : 0;
$type_filter = ($tab === 'handover' || $tab === 'return') ? $tab : 'all';

$result = PluginAssethandoverHandover::getList([
    'type' => $type_filter, 'search' => $search,
    'orderby' => $orderby, 'order_dir' => $order_dir,
    'limit' => $limit, 'offset' => $offset, 'deleted' => $deleted,
]);
$rows        = $result['rows'];
$total       = $result['total'];
$total_pages = max(1, (int)ceil($total / $limit));

global $DB;
$res = $DB->query("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE is_deleted=0");
$cnt_all = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;
$res = $DB->query("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE is_deleted=0 AND request_type='handover'");
$cnt_handover = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;
$res = $DB->query("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE is_deleted=0 AND request_type='return'");
$cnt_return = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;
$res = $DB->query("SELECT COUNT(*) AS c FROM glpi_plugin_assethandover_handovers WHERE is_deleted=1");
$cnt_trash = $res ? (int)$DB->fetchAssoc($res)['c'] : 0;

$_sort_base = "handover.php?tab={$tab}&search=" . urlencode($search) . "&limit={$limit}&page={$page}";

// User History tab data
$user_history_rows = [];
if ($tab === 'users') {
    $uh_search = $_GET['search'] ?? '';
    $uh_sql_where = '';
    if ($uh_search !== '') {
        $s = $DB->escape($uh_search);
        $uh_sql_where = "AND (u.firstname LIKE '%$s%' OR u.realname LIKE '%$s%' OR h.department LIKE '%$s%')";
    }
    $uh_sql = "
        SELECT
            u.id AS users_id,
            CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS fullname,
            MAX(h.department)   AS department,
            MAX(h.position)     AS position,
            MAX(h.employee_id)  AS employee_id,
            COUNT(DISTINCT h.id) AS total_handovers,
            SUM(CASE WHEN h.request_type='handover' THEN 1 ELSE 0 END) AS handover_count,
            SUM(CASE WHEN h.request_type='return'   THEN 1 ELSE 0 END) AS return_count,
            SUM(CASE
                WHEN h.request_type='handover' AND NOT EXISTS (
                    SELECT 1 FROM glpi_plugin_assethandover_handovers r
                    WHERE r.from_handover_id = h.id AND r.request_type='return' AND r.is_deleted=0
                ) THEN (SELECT COUNT(*) FROM glpi_plugin_assethandover_items i WHERE i.handovers_id=h.id)
                ELSE 0
            END) AS active_assets,
            MAX(h.date_creation) AS last_activity
        FROM glpi_plugin_assethandover_handovers h
        JOIN glpi_users u ON u.id = h.users_id
        WHERE h.is_deleted = 0 $uh_sql_where
        GROUP BY u.id
        ORDER BY last_activity DESC
    ";
    $uh_res = $DB->query($uh_sql);
    if ($uh_res) {
        while ($row = $DB->fetchAssoc($uh_res)) {
            $user_history_rows[] = $row;
        }
    }
}
$cnt_users = 0;
$res = $DB->query("SELECT COUNT(DISTINCT users_id) AS c FROM glpi_plugin_assethandover_handovers WHERE is_deleted=0");
if ($res) $cnt_users = (int)$DB->fetchAssoc($res)['c'];

if (!function_exists('ahSortUrl')) {
    function ahSortUrl($col, $c, $d, $b) {
        return $b . "&orderby=" . urlencode($col) . "&order_dir=" . ($c===$col && $d==='ASC' ? 'DESC' : 'ASC');
    }
    function ahSortIcon($col, $c, $d) {
        if ($c !== $col) return '<i class="fas fa-sort" style="opacity:.25;font-size:10px"></i>';
        return $d === 'ASC' ? '<i class="fas fa-sort-up" style="font-size:10px"></i>' : '<i class="fas fa-sort-down" style="font-size:10px"></i>';
    }
}

$ajax_url = Plugin::getWebDir('assethandover') . '/front/handover.ajax.php';
?>
<style>
.ahl * { box-sizing:border-box; }
.ahl { padding:20px 16px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
.ahl-topbar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px; }
.ahl-title  { font-size:18px;font-weight:700;color:#1a3c6e;display:flex;align-items:center;gap:8px;margin:0; }
.ahl-btns   { display:flex;gap:8px;flex-wrap:wrap; }
.ahl-btn    { display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s; }
.ahl-btn-handover { background:#1a3c6e;color:#fff; }
.ahl-btn-handover:hover { background:#15305a;color:#fff; }
.ahl-btn-return   { background:#16a34a;color:#fff; }
.ahl-btn-return:hover { background:#138a40;color:#fff; }

.ahl-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:visible; }
.ahl-tabs { display:flex;align-items:center;gap:2px;padding:12px 16px 0;border-bottom:1px solid #e5e7eb;background:#f9fafb;border-radius:10px 10px 0 0; }
.ahl-tab  { display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:6px 6px 0 0;font-size:12px;font-weight:600;cursor:pointer;border:1px solid transparent;border-bottom:none;text-decoration:none;color:#6b7280;background:transparent;margin-bottom:-1px;transition:all .15s; }
.ahl-tab:hover { color:#1a3c6e;background:#fff; }
.ahl-tab.active { color:#1a3c6e;background:#fff;border-color:#e5e7eb;border-bottom-color:#fff; }
.ahl-tab .cnt { display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#e5e7eb;color:#374151;border-radius:10px;font-size:10px;font-weight:700; }
.ahl-tab.active .cnt { background:#1a3c6e;color:#fff; }
.ahl-tab.tab-trash .cnt { background:#fee2e2;color:#dc2626; }
.ahl-tab.tab-trash.active .cnt { background:#dc2626;color:#fff; }
.ahl-tab.tab-users .cnt { background:#e0f2fe;color:#0369a1; }
.ahl-tab.tab-users.active .cnt { background:#0369a1;color:#fff; }
.ahl-tab.tab-users.active { color:#0369a1; border-color:#e5e7eb; border-bottom-color:#fff; }
.ahl-toolbar { display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap; }
.ahl-search-wrap { position:relative;flex:0 0 260px; }
.ahl-search-wrap i { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px; }
.ahl-search-input { width:100%;padding:6px 10px 6px 30px;font-size:12px;border:1.5px solid #d1d5db;border-radius:6px;outline:none;transition:border-color .15s; }
.ahl-search-input:focus { border-color:#1a3c6e; }
.ahl-search-btn { padding:6px 12px;border-radius:6px;font-size:12px;border:1.5px solid #d1d5db;background:#fff;cursor:pointer;color:#374151;transition:all .15s; }
.ahl-search-btn:hover { border-color:#1a3c6e;color:#1a3c6e; }
.ahl-clear-btn { padding:6px 10px;border-radius:6px;font-size:12px;border:1.5px solid #fca5a5;background:#fff;cursor:pointer;color:#dc2626;text-decoration:none;display:inline-flex;align-items:center;gap:4px; }
.ahl-rows-wrap { margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;color:#6b7280;font-weight:500; }
.ahl-rows-select { padding:5px 8px;border-radius:6px;font-size:12px;border:1.5px solid #d1d5db;outline:none; }
.ahl-export-btn { display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;color:#15803d;border:1.5px solid #86efac;background:#f0fdf4;text-decoration:none;transition:all .15s; }
.ahl-export-btn:hover { background:#dcfce7; }
.ahl-table-wrap { overflow-x:auto; }
.ahl-table { width:100%;border-collapse:collapse;font-size:12px; }
.ahl-table thead tr { background:#f8fafc; }
.ahl-table thead th { padding:9px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;border-bottom:2px solid #e5e7eb;white-space:nowrap; }
.ahl-table thead th a { color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px; }
.ahl-table thead th a:hover { color:#1a3c6e; }
.ahl-table tbody tr { border-bottom:1px solid #f3f4f6;transition:background .1s;cursor:pointer; }
.ahl-table tbody tr:hover { background:#f0f4ff; }
.ahl-table tbody td { padding:10px 12px;vertical-align:middle;color:#374151; }
.ahl-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700; }
.ahl-badge-handover { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
.ahl-badge-return   { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.ahl-badge-active   { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
.ahl-badge-returned { background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0; }
.ahl-badge-assets   { background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px; }
.ahl-user-link { font-weight:600;color:#1a3c6e;text-decoration:none; }
.ahl-user-link:hover { text-decoration:underline; }
.ahl-actions { display:flex;gap:4px;align-items:center; }
.ahl-action-btn { width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer;border:1.5px solid;text-decoration:none;transition:all .15s; }
.ahl-action-btn:hover { transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.12); }
.ahl-btn-edit    { background:#fff;color:#374151;border-color:#d1d5db; }
.ahl-btn-edit:hover    { background:#f3f4f6;color:#1a3c6e;border-color:#1a3c6e; }
.ahl-btn-print   { background:#fff;color:#0369a1;border-color:#bae6fd; }
.ahl-btn-print:hover   { background:#f0f9ff; }
.ahl-btn-restore { background:#fff;color:#16a34a;border-color:#bbf7d0; }
.ahl-btn-restore:hover { background:#f0fdf4; }
.ahl-btn-delete  { background:#fff;color:#dc2626;border-color:#fca5a5; }
.ahl-btn-delete:hover  { background:#fef2f2; }
.ahl-btn-return-from  { background:#fff;color:#16a34a;border-color:#bbf7d0; }
.ahl-btn-return-from:hover { background:#f0fdf4; }
.ahl-empty { text-align:center;padding:50px 20px;color:#9ca3af; }
.ahl-empty i { font-size:36px;display:block;margin-bottom:10px;opacity:.3; }
.ahl-pagination { display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #f3f4f6;font-size:12px;color:#6b7280;flex-wrap:wrap;gap:8px; }
.ahl-pages { display:flex;gap:3px; }
.ahl-page-btn { min-width:28px;height:28px;padding:0 6px;border-radius:6px;border:1.5px solid #e5e7eb;background:#fff;color:#374151;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:500;transition:all .15s; }
.ahl-page-btn:hover { border-color:#1a3c6e;color:#1a3c6e; }
.ahl-page-btn.active { background:#1a3c6e;color:#fff;border-color:#1a3c6e; }
.ahl-bulk-bar { display:none;align-items:center;gap:10px;padding:10px 16px;background:#eff6ff;border-top:1px solid #bfdbfe;font-size:12px;flex-wrap:wrap; }
.ahl-bulk-bar .cnt-label { font-weight:700;color:#1d4ed8; }
.ahl-bulk-btn { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid;background:#fff;transition:all .15s; }
.ahl-bulk-btn-restore { color:#16a34a;border-color:#bbf7d0; }
.ahl-bulk-btn-purge   { color:#dc2626;border-color:#fca5a5; }
.ahl-bulk-btn-delete  { color:#dc2626;border-color:#fca5a5; }

/* ── User History Panel ── */
.up-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:9998; }
.up-panel {
    position:fixed;top:0;right:-520px;width:500px;height:100vh;
    background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,.12);
    z-index:9999;transition:right .25s ease;overflow-y:auto;
    display:flex;flex-direction:column;
}
.up-panel.open { right:0; }
.up-header { padding:14px 18px;background:#0369a1;color:#fff;display:flex;align-items:center;gap:10px;flex-shrink:0; }
.up-header h3 { margin:0;font-size:14px;font-weight:700;flex:1; }
.up-close { width:26px;height:26px;border-radius:5px;background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px; }
.up-user-info { padding:14px 18px;border-bottom:1px solid #f3f4f6; }
.up-stats { display:flex;gap:8px;padding:12px 18px;border-bottom:1px solid #f3f4f6;background:#f9fafb; }
.up-stat { flex:1;text-align:center;padding:8px;background:#fff;border:1px solid #e5e7eb;border-radius:7px; }
.up-stat-num { font-size:18px;font-weight:700;line-height:1; }
.up-stat-lbl { font-size:9px;color:#6b7280;text-transform:uppercase;letter-spacing:.3px;margin-top:3px; }
.up-records { flex:1;overflow-y:auto; }
.up-record-row {
    display:flex;align-items:stretch;border-bottom:1px solid #f3f4f6;
    cursor:pointer;transition:background .1s;text-decoration:none;color:inherit;
}
.up-record-row:hover { background:#f8faff; }
.up-record-type { flex-shrink:0;width:6px; }
.up-record-body { flex:1;padding:10px 14px;min-width:0; }
.up-record-head { display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap; }
.up-record-id   { font-size:12px;font-weight:700;color:#374151; }
.up-record-assets { font-size:11px;color:#6b7280;margin-top:2px; white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.up-record-right { flex-shrink:0;padding:10px 14px;display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:4px; }
.up-loading { text-align:center;padding:40px;color:#9ca3af;font-size:13px; }

/* ── Quick View Panel (#6) ── */
.qv-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:9998; }
.qv-panel {
    position:fixed;top:0;right:-480px;width:460px;height:100vh;
    background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,.12);
    z-index:9999;transition:right .25s ease;overflow-y:auto;
    display:flex;flex-direction:column;
}
.qv-panel.open { right:0; }
.qv-header { padding:14px 18px;background:#1a3c6e;color:#fff;display:flex;align-items:center;gap:10px;flex-shrink:0; }
.qv-header.return-header { background:#16a34a; }
.qv-header h3 { margin:0;font-size:14px;font-weight:700;flex:1; }
.qv-close { width:26px;height:26px;border-radius:5px;background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px; }
.qv-body { padding:16px 18px;flex:1; }
.qv-user { display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #f3f4f6; }
.qv-avatar { width:40px;height:40px;border-radius:50%;background:#eff6ff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0; }
.qv-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px; }
.qv-field label { font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px; }
.qv-field span  { font-size:12px;color:#111827;font-weight:500; }
.qv-section-title { font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid #f3f4f6; }
.qv-asset-row { display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f9fafb;border:1px solid #f3f4f6;border-radius:7px;margin-bottom:6px; }
.qv-asset-icon { width:28px;height:28px;border-radius:6px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.qv-asset-icon i { font-size:11px;color:#1d4ed8; }
.qv-asset-body { flex:1;min-width:0; }
.qv-asset-name { font-size:12px;font-weight:600;color:#111827; }
.qv-asset-meta { font-size:10px;color:#6b7280;margin-top:1px; }
.qv-actions { padding:14px 18px;border-top:1px solid #f3f4f6;display:flex;gap:8px;flex-shrink:0; }
.qv-btn { flex:1;padding:8px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;transition:all .15s; }
.qv-btn-edit   { background:#fff;color:#374151;border-color:#d1d5db; }
.qv-btn-edit:hover { background:#f3f4f6; }
.qv-btn-print  { background:#fff;color:#0369a1;border-color:#bae6fd; }
.qv-btn-print:hover { background:#f0f9ff; }
.qv-btn-return { background:#16a34a;color:#fff;border-color:#16a34a; }
.qv-btn-return:hover { background:#138a40; }
.qv-loading { text-align:center;padding:40px;color:#9ca3af;font-size:13px; }
</style>

<div class="ahl">
  <div class="ahl-topbar">
    <h1 class="ahl-title"><i class="fas fa-exchange-alt"></i> Asset Handover &amp; Return</h1>
    <div class="ahl-btns">
      <?php if ($tab !== 'users' && Session::haveRight('plugin_assethandover', CREATE)): ?>
      <a href="handover.form.php?request_type=handover" class="ahl-btn ahl-btn-handover">
        <i class="fas fa-share"></i> New Handover
      </a>
      <a href="handover.form.php?request_type=return" class="ahl-btn ahl-btn-return">
        <i class="fas fa-undo"></i> New Return
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ahl-card">
    <!-- Tabs -->
    <div class="ahl-tabs">
      <?php
      $tabs_def = [
        'all'      => ['label'=>'All',          'count'=>$cnt_all,      'extra'=>''],
        'handover' => ['label'=>'Handover',     'count'=>$cnt_handover, 'extra'=>''],
        'return'   => ['label'=>'Return',       'count'=>$cnt_return,   'extra'=>''],
        'users'    => ['label'=>'User History', 'count'=>$cnt_users,    'extra'=>' tab-users'],
        'trash'    => ['label'=>'Trash',        'count'=>$cnt_trash,    'extra'=>' tab-trash'],
      ];
      foreach ($tabs_def as $tkey => $tdef):
          $active = ($tab === $tkey) ? 'active' : '';
      ?>
      <a class="ahl-tab <?php echo $active . $tdef['extra']; ?>"
         href="?tab=<?php echo $tkey; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>">
        <?php if ($tkey==='trash'): ?><i class="fas fa-trash" style="font-size:10px"></i><?php endif; ?>
        <?php echo $tdef['label']; ?> <span class="cnt"><?php echo $tdef['count']; ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <?php if ($tab !== 'users'): ?>
    <form method="get" action="handover.php">
      <input type="hidden" name="tab"       value="<?php echo htmlspecialchars($tab); ?>">
      <input type="hidden" name="orderby"   value="<?php echo htmlspecialchars($orderby); ?>">
      <input type="hidden" name="order_dir" value="<?php echo htmlspecialchars($order_dir); ?>">
      <input type="hidden" name="limit"     value="<?php echo $limit; ?>">
      <div class="ahl-toolbar">
        <div class="ahl-search-wrap">
          <i class=""></i>
          <input type="text" name="search" class="ahl-search-input"
                 value="<?php echo htmlspecialchars($search); ?>" placeholder="Search user, department...">
        </div>
        <button type="submit" class="ahl-search-btn"><i class="fas fa-search"></i> Search</button>
        <?php if ($search): ?>
        <a href="?tab=<?php echo $tab; ?>&limit=<?php echo $limit; ?>" class="ahl-clear-btn">
          <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
        <div class="ahl-rows-wrap">
          Rows:
          <select class="ahl-rows-select"
                  onchange="window.location='?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&limit='+this.value">
            <?php foreach([10,25,50,100] as $lv): ?>
            <option value="<?php echo $lv; ?>" <?php echo ($lv==$limit)?'selected':''; ?>><?php echo $lv; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <a href="handover.export.php?type=<?php echo urlencode($type_filter); ?>&search=<?php echo urlencode($search); ?>"
           class="ahl-export-btn"><i class="fas fa-file-csv"></i> Export CSV</a>
      </div>
    </form>
    <?php endif; ?>

    <!-- Table -->
    <?php if ($tab !== 'users'): ?>
    <div class="ahl-table-wrap">
    <table class="ahl-table">
      <thead>
        <tr>
          <th style="width:36px;padding-left:16px"><input type="checkbox" id="chk_all"></th>
          <th><a href="<?php echo ahSortUrl('h.request_type',$orderby,$order_dir,$_sort_base); ?>">Type <?php echo ahSortIcon('h.request_type',$orderby,$order_dir); ?></a></th>
          <th><a href="<?php echo ahSortUrl('u.realname',$orderby,$order_dir,$_sort_base); ?>">User <?php echo ahSortIcon('u.realname',$orderby,$order_dir); ?></a></th>
          <th><a href="<?php echo ahSortUrl('h.department',$orderby,$order_dir,$_sort_base); ?>">Department <?php echo ahSortIcon('h.department',$orderby,$order_dir); ?></a></th>
          <th>Assets</th>
          <th>Status</th>
          <th><a href="<?php echo ahSortUrl('h.handover_date',$orderby,$order_dir,$_sort_base); ?>">Date <?php echo ahSortIcon('h.handover_date',$orderby,$order_dir); ?></a></th>
          <th>Assigned / Checked By</th>
          <th><a href="<?php echo ahSortUrl('h.date_creation',$orderby,$order_dir,$_sort_base); ?>">Created <?php echo ahSortIcon('h.date_creation',$orderby,$order_dir); ?></a></th>
          <th onclick="event.stopPropagation()">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="10">
          <div class="ahl-empty">
            <i class="fas fa-box-open"></i>
            <p>No records found<?php echo $search ? ' for "<strong>'.htmlspecialchars($search).'</strong>"' : ''; ?>.</p>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row):
          $is_ret = $row['request_type'] === 'return';
          $badge  = $is_ret
              ? '<span class="ahl-badge ahl-badge-return"><i class="fas fa-undo" style="font-size:9px"></i> Return</span>'
              : '<span class="ahl-badge ahl-badge-handover"><i class="fas fa-share" style="font-size:9px"></i> Handover</span>';
          $staff  = $is_ret ? $row['checked_by'] : $row['assigned_by'];
          $date   = $is_ret ? $row['return_date'] : $row['handover_date'];
          $status = $row['status'] ?? 'active';
          if ($is_ret) {
              $status_badge = '<span class="ahl-badge ahl-badge-returned"><i class="fas fa-check-circle" style="font-size:9px"></i> Returned</span>';
          } elseif ($status === 'returned') {
              $status_badge = '<span class="ahl-badge ahl-badge-returned"><i class="fas fa-check-circle" style="font-size:9px"></i> Returned</span>';
          } else {
              $status_badge = '<span class="ahl-badge ahl-badge-active"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>';
          }
        ?>
        <tr data-id="<?php echo $row['id']; ?>" onclick="openQuickView(<?php echo $row['id']; ?>, this)">
          <td style="padding-left:16px" onclick="event.stopPropagation()">
            <input type="checkbox" class="row-chk" value="<?php echo $row['id']; ?>">
          </td>
          <td><?php echo $badge; ?></td>
          <td>
            <div style="font-weight:600;color:#1a3c6e"><?php echo htmlspecialchars($row['user_fullname'] ?: '—'); ?></div>
            <?php if ($row['position']): ?>
            <div style="font-size:10px;color:#9ca3af"><?php echo htmlspecialchars($row['position']); ?></div>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($row['department'] ?: '—'); ?></td>
          <td><span class="ahl-badge-assets"><?php echo (int)$row['asset_count']; ?> asset(s)</span></td>
          <td><?php echo $status_badge; ?></td>
          <td><?php echo htmlspecialchars($date ?: '—'); ?></td>
          <td><?php echo htmlspecialchars($staff ?: '—'); ?></td>
          <td style="color:#9ca3af;font-size:11px"><?php echo htmlspecialchars(substr($row['date_creation'],0,16)); ?></td>
          <td onclick="event.stopPropagation()">
            <div class="ahl-actions">
              <a href="handover.form.php?id=<?php echo $row['id']; ?>" class="ahl-action-btn ahl-btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="handover.pdf.php?id=<?php echo $row['id']; ?>" target="_blank" class="ahl-action-btn ahl-btn-print" title="Print Slip"><i class="fas fa-print"></i></a>
              <?php if (!$is_ret && $status !== 'returned' && $tab !== 'trash'): ?>
              <a href="handover.form.php?request_type=return&from_handover_id=<?php echo $row['id']; ?>"
                 class="ahl-action-btn ahl-btn-return-from" title="Create Return"><i class="fas fa-undo"></i></a>
              <?php endif; ?>
              <?php if ($tab === 'trash'): ?>
                <button class="ahl-action-btn ahl-btn-restore" onclick="doAction('restore',<?php echo $row['id']; ?>)" title="Restore"><i class="fas fa-undo"></i></button>
                <button class="ahl-action-btn ahl-btn-delete"  onclick="doAction('purge',<?php echo $row['id']; ?>)"   title="Delete Permanently"><i class="fas fa-trash"></i></button>
              <?php else: ?>
                <button class="ahl-action-btn ahl-btn-delete"  onclick="doAction('delete',<?php echo $row['id']; ?>)"  title="Move to Trash"><i class="fas fa-trash"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
    </div>

    </div><!-- /table-wrap -->
    <?php endif; ?>

    <!-- ── User History tab content ── -->
    <?php if ($tab === 'users'): ?>

    <!-- Search bar for users tab -->
    <form method="get" action="handover.php">
      <input type="hidden" name="tab" value="users">
      <div class="ahl-toolbar">
        <div class="ahl-search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="ahl-search-input"
                 value="<?php echo htmlspecialchars($search); ?>"
                 placeholder="Search user, department...">
        </div>
        <button type="submit" class="ahl-search-btn"><i class="fas fa-search"></i> Search</button>
        <?php if ($search): ?>
        <a href="?tab=users" class="ahl-clear-btn"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
        <div style="margin-left:auto;font-size:12px;color:#6b7280">
          <?php echo count($user_history_rows); ?> user(s) found
        </div>
      </div>
    </form>

    <div style="padding:0">
      <table class="ahl-table">
        <thead>
          <tr>
            <th style="padding-left:16px">User</th>
            <th>Department</th>
            <th>Position</th>
            <th>Employee ID</th>
            <th style="text-align:center">Active Assets</th>
            <th style="text-align:center">Handovers</th>
            <th style="text-align:center">Returns</th>
            <th style="text-align:center">Total Records</th>
            <th>Last Activity</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($user_history_rows)): ?>
          <tr><td colspan="9">
            <div class="ahl-empty"><i class="fas fa-users"></i><p>No user history found.</p></div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($user_history_rows as $ur):
            $initials = strtoupper(
                substr($ur['fullname'],0,1) .
                (strpos($ur['fullname'],' ')!==false ? substr($ur['fullname'],strpos($ur['fullname'],' ')+1,1) : '')
            );
            $active = (int)$ur['active_assets'];
          ?>
          <tr class="uh-row" style="cursor:pointer"
              data-users-id="<?php echo (int)$ur['users_id']; ?>"
              data-fullname="<?php echo htmlspecialchars($ur['fullname']); ?>"
              data-initials="<?php echo htmlspecialchars($initials); ?>"
              data-department="<?php echo htmlspecialchars($ur['department'] ?: ''); ?>"
              data-position="<?php echo htmlspecialchars($ur['position'] ?: ''); ?>"
              data-employee-id="<?php echo htmlspecialchars($ur['employee_id'] ?: ''); ?>"
              data-active="<?php echo $active; ?>"
              onclick="openUserPanel(this)">
            <td style="padding-left:16px">
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                  <?php echo htmlspecialchars($initials); ?>
                </div>
                <div>
                  <div style="font-weight:600;color:#1a3c6e;font-size:12px"><?php echo htmlspecialchars($ur['fullname']); ?></div>
                  <?php if ($ur['employee_id']): ?>
                  <div style="font-size:10px;color:#9ca3af"><?php echo htmlspecialchars($ur['employee_id']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><?php echo htmlspecialchars($ur['department'] ?: '—'); ?></td>
            <td style="font-size:11px;color:#6b7280"><?php echo htmlspecialchars($ur['position'] ?: '—'); ?></td>
            <td style="font-size:11px;color:#6b7280"><?php echo htmlspecialchars($ur['employee_id'] ?: '—'); ?></td>
            <td style="text-align:center">
              <?php if ($active > 0): ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">
                <i class="fas fa-circle" style="font-size:7px"></i> <?php echo $active; ?>
              </span>
              <?php else: ?>
              <span style="font-size:11px;color:#9ca3af">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8"><?php echo (int)$ur['handover_count']; ?></span>
            </td>
            <td style="text-align:center">
              <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#f0fdf4;color:#15803d"><?php echo (int)$ur['return_count']; ?></span>
            </td>
            <td style="text-align:center">
              <span style="font-size:12px;font-weight:600;color:#374151"><?php echo (int)$ur['total_handovers']; ?></span>
            </td>
            <td style="font-size:11px;color:#6b7280"><?php echo htmlspecialchars(substr($ur['last_activity'],0,16)); ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Bulk bar -->
    <div id="bulk-bar" class="ahl-bulk-bar">
      <span class="cnt-label"><span id="sel-count">0</span> selected</span>
      <?php if ($tab === 'trash'): ?>
      <button class="ahl-bulk-btn ahl-bulk-btn-restore" onclick="bulkAction('restore')"><i class="fas fa-undo"></i> Restore</button>
      <button class="ahl-bulk-btn ahl-bulk-btn-purge"   onclick="bulkAction('purge')"><i class="fas fa-trash"></i> Delete Permanently</button>
      <?php else: ?>
      <button class="ahl-bulk-btn ahl-bulk-btn-delete"  onclick="bulkAction('delete')"><i class="fas fa-trash"></i> Move to Trash</button>
      <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="ahl-pagination">
      <span>Showing <strong><?php echo $offset+1; ?></strong>–<strong><?php echo min($offset+$limit,$total); ?></strong> of <strong><?php echo $total; ?></strong> records</span>
      <?php if ($total_pages > 1): ?>
      <div class="ahl-pages">
        <?php for ($p=1; $p<=$total_pages; $p++): ?>
        <a class="ahl-page-btn <?php echo ($p==$page)?'active':''; ?>"
           href="?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&orderby=<?php echo urlencode($orderby); ?>&order_dir=<?php echo $order_dir; ?>&limit=<?php echo $limit; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- User History Panel -->
<div class="up-overlay" id="up-overlay" onclick="closeUserPanel()"></div>
<div class="up-panel" id="up-panel">
  <div class="up-header">
    <div id="up-avatar" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0"></div>
    <h3 id="up-name">User History</h3>
    <button class="up-close" onclick="closeUserPanel()"><i class="fas fa-times"></i></button>
  </div>
  <div class="up-user-info" id="up-user-info"></div>
  <div class="up-stats" id="up-stats"></div>
  <div class="up-records" id="up-records">
    <div class="up-loading"><i class="fas fa-spinner fa-spin"></i><br>Loading records...</div>
  </div>
</div>

<!-- Quick View Panel (#6) -->
<div class="qv-overlay" id="qv-overlay" onclick="closeQuickView()"></div>
<div class="qv-panel" id="qv-panel">
  <div class="qv-header" id="qv-header">
    <h3 id="qv-title">Record details</h3>
    <button class="qv-close" onclick="closeQuickView()"><i class="fas fa-times"></i></button>
  </div>
  <div class="qv-body" id="qv-body">
    <div class="qv-loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>
  </div>
  <div class="qv-actions" id="qv-actions"></div>
</div>

<form id="action-form" method="post" action="handover.action.php">
  <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
  <input type="hidden" name="action"       id="f-action">
  <input type="hidden" name="id"           id="f-id">
  <input type="hidden" name="ids"          id="f-ids">
  <input type="hidden" name="redirect_tab" value="<?php echo htmlspecialchars($tab); ?>">
</form>

<script>
const AH_AJAX = '<?php echo $ajax_url; ?>';
const AH_BASE = '<?php echo Plugin::getWebDir('assethandover'); ?>/front/';

// ── Checkbox / bulk ──
const _chkAll = document.getElementById('chk_all');
if (_chkAll) {
    _chkAll.addEventListener('change', function() {
        document.querySelectorAll('.row-chk').forEach(c => c.checked = this.checked);
        updateBulkBar();
    });
    document.querySelectorAll('.row-chk').forEach(c => c.addEventListener('change', updateBulkBar));
}
function updateBulkBar() {
    const sel = document.querySelectorAll('.row-chk:checked');
    document.getElementById('bulk-bar').style.display = sel.length > 0 ? 'flex' : 'none';
    document.getElementById('sel-count').textContent = sel.length;
}
function doAction(action, id) {
    if (action === 'purge'  && !confirm('Permanently delete this record?')) return;
    if (action === 'delete' && !confirm('Move to trash?')) return;
    document.getElementById('f-action').value = action;
    document.getElementById('f-id').value     = id;
    document.getElementById('action-form').submit();
}
function bulkAction(action) {
    const ids = [...document.querySelectorAll('.row-chk:checked')].map(c => c.value);
    if (!ids.length) return;
    if (action === 'purge'  && !confirm('Permanently delete ' + ids.length + ' record(s)?')) return;
    if (action === 'delete' && !confirm('Move ' + ids.length + ' record(s) to trash?')) return;
    document.getElementById('f-action').value = 'bulk_' + action;
    document.getElementById('f-ids').value    = ids.join(',');
    document.getElementById('action-form').submit();
}

// ── Quick View Panel (#6) ──
let _qvActiveRow = null;
function openQuickView(id, row) {
    if (_qvActiveRow) _qvActiveRow.style.background = '';
    _qvActiveRow = row;
    row.style.background = '#e8f0ff';

    document.getElementById('qv-overlay').style.display = 'block';
    document.getElementById('qv-panel').classList.add('open');
    document.getElementById('qv-title').textContent = 'Record #' + id;
    document.getElementById('qv-body').innerHTML = '<div class="qv-loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
    document.getElementById('qv-actions').innerHTML = '';

    fetch(AH_AJAX + '?action=quick_view&id=' + id)
        .then(r => r.json())
        .then(d => renderQuickView(d))
        .catch(() => {
            document.getElementById('qv-body').innerHTML = '<div class="qv-loading">Failed to load.</div>';
        });
}
function closeQuickView() {
    document.getElementById('qv-overlay').style.display = 'none';
    document.getElementById('qv-panel').classList.remove('open');
    if (_qvActiveRow) { _qvActiveRow.style.background = ''; _qvActiveRow = null; }
}
function renderQuickView(d) {
    if (!d || d.error) { document.getElementById('qv-body').innerHTML = '<div class="qv-loading">Not found.</div>'; return; }
    const isRet = d.request_type === 'return';
    const hdr   = document.getElementById('qv-header');
    hdr.className = 'qv-header' + (isRet ? ' return-header' : '');
    document.getElementById('qv-title').textContent = (isRet ? 'Return' : 'Handover') + ' #' + d.id;

    const initials = (d.user_fullname || '??').split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase();
    const staff = isRet ? (d.checked_by||'—') : (d.assigned_by||'—');
    const date  = isRet ? (d.return_date||'—') : (d.handover_date||'—');

    let assetsHtml = '';
    (d.items || []).forEach(item => {
        const cond = isRet ? (item.condition_return||'') : (item.condition_issue||'');
        const condColor = cond==='Damaged'||cond==='Missing' ? '#dc2626' : (cond==='Good'||cond==='New' ? '#16a34a' : '#6b7280');
        // Map itemtype to GLPI front page
        const qvTypeMap = {
            'Computer':'computer','Monitor':'monitor','Printer':'printer',
            'Phone':'phone','Peripheral':'peripheral','NetworkEquipment':'networkequipment'
        };
        const qvGlpiType = qvTypeMap[item.asset_itemtype] || 'computer';
        const qvGlpiRoot = '<?php global $CFG_GLPI; echo rtrim($CFG_GLPI["root_doc"],"/"); ?>';
        const qvGlpiLink = item.asset_id > 0
            ? `<a href="${qvGlpiRoot}/front/${qvGlpiType}.form.php?id=${item.asset_id}" target="_blank"
                  style="font-size:10px;color:#1d4ed8;text-decoration:none" title="Open in GLPI">
                 <i class="fas fa-external-link-alt"></i>
               </a>`
            : '';

        assetsHtml += `
        <div class="qv-asset-row">
          <div class="qv-asset-icon"><i class="fas fa-laptop"></i></div>
          <div class="qv-asset-body">
            <div class="qv-asset-name">${esc(item.asset_name||'—')}</div>
            <div class="qv-asset-meta">
              ${item.serial_number ? 'S/N: '+esc(item.serial_number)+' · ' : ''}
              ${item.asset_number  ? 'Asset#: '+esc(item.asset_number)  : ''}
            </div>
          </div>
          ${cond ? `<span style="font-size:10px;font-weight:600;color:${condColor}">${esc(cond)}</span>` : ''}
          ${qvGlpiLink}
        </div>`;
    });
    if (!d.items || !d.items.length) assetsHtml = '<div style="font-size:12px;color:#9ca3af;text-align:center;padding:12px">No assets</div>';

    document.getElementById('qv-body').innerHTML = `
      <div class="qv-user">
        <div class="qv-avatar">${esc(initials)}</div>
        <div>
          <div style="font-size:14px;font-weight:700">${esc(d.user_fullname||'—')}</div>
          <div style="font-size:11px;color:#6b7280">${esc(d.position||'')} ${d.employee_id ? '· '+esc(d.employee_id) : ''}</div>
        </div>
      </div>
      <div class="qv-grid">
        <div class="qv-field"><label>Department</label><span>${esc(d.department||'—')}</span></div>
        <div class="qv-field"><label>Unit</label><span>${esc(d.unit||'—')}</span></div>
        <div class="qv-field"><label>${isRet ? 'Return date' : 'Handover date'}</label><span>${esc(date)}</span></div>
        <div class="qv-field"><label>${isRet ? 'Checked by' : 'Assigned by'}</label><span>${esc(staff)}</span></div>
        ${d.manager_name ? `<div class="qv-field"><label>Manager</label><span>${esc(d.manager_name)}</span></div>` : ''}
        ${d.remark ? `<div class="qv-field" style="grid-column:span 2"><label>Remark</label><span>${esc(d.remark)}</span></div>` : ''}
      </div>
      <div class="qv-section-title">Assets (${(d.items||[]).length})</div>
      ${assetsHtml}
    `;

    let actHtml = `<a href="${AH_BASE}handover.form.php?id=${d.id}" class="qv-btn qv-btn-edit"><i class="fas fa-edit"></i> Edit</a>`;
    actHtml    += `<a href="${AH_BASE}handover.pdf.php?id=${d.id}" target="_blank" class="qv-btn qv-btn-print"><i class="fas fa-print"></i> Print</a>`;
    if (!isRet) {
        actHtml += `<a href="${AH_BASE}handover.form.php?request_type=return&from_handover_id=${d.id}" class="qv-btn qv-btn-return"><i class="fas fa-undo"></i> Create Return</a>`;
    }
    document.getElementById('qv-actions').innerHTML = actHtml;
}
function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeQuickView(); closeUserPanel(); } });

// ── User History Panel ──
let _upActiveRow = null;

function openUserPanel(row) {
    if (_upActiveRow) _upActiveRow.style.background = '';
    _upActiveRow = row;
    row.style.background = '#e0f2fe';

    const d = row.dataset;
    const initials   = d.initials   || '?';
    const fullname   = d.fullname   || '—';
    const department = d.department || '—';
    const position   = d.position   || '—';
    const employeeId = d.employeeId || '—';
    const active     = parseInt(d.active || 0);
    const usersId    = d.usersId;

    // Header
    document.getElementById('up-avatar').textContent = initials;
    document.getElementById('up-name').textContent   = fullname;

    // User info block
    document.getElementById('up-user-info').innerHTML = `
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;height:44px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0">${esc(initials)}</div>
          <div>
            <div style="font-size:15px;font-weight:700;color:#111827">${esc(fullname)}</div>
            <div style="font-size:11px;color:#6b7280;margin-top:2px">
              ${position !== '—' ? esc(position)+' &nbsp;·&nbsp; ' : ''}${esc(department)}
              ${employeeId !== '—' ? ' &nbsp;·&nbsp; '+esc(employeeId) : ''}
            </div>
          </div>
        </div>`;

    // Stats placeholder (will fill after fetch)
    document.getElementById('up-stats').innerHTML = '';
    document.getElementById('up-records').innerHTML = '<div class="up-loading"><i class="fas fa-spinner fa-spin"></i><br>Loading records...</div>';

    // Show panel
    document.getElementById('up-overlay').style.display = 'block';
    document.getElementById('up-panel').classList.add('open');

    // Fetch records
    fetch(AH_AJAX + '?action=user_records&users_id=' + encodeURIComponent(usersId))
        .then(r => r.json())
        .then(records => {
            const total     = records.length;
            const handovers = records.filter(r => r.request_type === 'handover').length;
            const returns   = records.filter(r => r.request_type === 'return').length;

            // Stats
            document.getElementById('up-stats').innerHTML = `
                <div class="up-stat">
                  <div class="up-stat-num">${total}</div>
                  <div class="up-stat-lbl">Total</div>
                </div>
                <div class="up-stat">
                  <div class="up-stat-num" style="color:#1d4ed8">${handovers}</div>
                  <div class="up-stat-lbl">Handovers</div>
                </div>
                <div class="up-stat">
                  <div class="up-stat-num" style="color:#15803d">${returns}</div>
                  <div class="up-stat-lbl">Returns</div>
                </div>
                <div class="up-stat">
                  <div class="up-stat-num" style="color:${active > 0 ? '#1d4ed8' : '#9ca3af'}">${active}</div>
                  <div class="up-stat-lbl">Active</div>
                </div>`;

            if (!records.length) {
                document.getElementById('up-records').innerHTML = '<div class="up-loading">No records found.</div>';
                return;
            }

            // Records list
            let html = '';
            records.forEach(r => {
                const isRet    = r.request_type === 'return';
                const isActive = r.status === 'active';
                const typeColor = isRet ? '#16a34a' : '#1a3c6e';
                const statusBg  = isRet ? '#f0fdf4' : (isActive ? '#eff6ff' : '#f0fdf4');
                const statusTxt = isRet ? 'Returned' : (isActive ? 'Active' : 'Returned');
                const statusColor = isActive && !isRet ? '#1d4ed8' : '#15803d';
                const date  = isRet ? (r.return_date||'—') : (r.handover_date||'—');
                const staff = isRet ? (r.checked_by||'—') : (r.assigned_by||'—');
                const typeLbl = isRet ? 'Return' : 'Handover';

                html += `
                <a href="${AH_BASE}handover.form.php?id=${r.id}" target="_blank" class="up-record-row" onclick="event.stopPropagation()">
                  <div class="up-record-type" style="background:${typeColor}"></div>
                  <div class="up-record-body">
                    <div class="up-record-head">
                      <span class="up-record-id">#${r.id}</span>
                      <span style="display:inline-flex;align-items:center;gap:2px;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600;background:${isRet?'#f0fdf4':'#eff6ff'};color:${typeColor}">
                        <i class="fas ${isRet?'fa-undo':'fa-share'}" style="font-size:8px"></i> ${typeLbl}
                      </span>
                      <span style="padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600;background:${statusBg};color:${statusColor}">${statusTxt}</span>
                    </div>
                    <div class="up-record-assets">
                      <i class="fas fa-laptop" style="font-size:9px;color:#9ca3af;margin-right:3px"></i>
                      ${r.asset_names ? esc(r.asset_names) : '<span style="color:#9ca3af">No assets</span>'}
                      ${r.asset_count > 0 ? '<span style="color:#9ca3af;margin-left:4px">('+r.asset_count+')</span>' : ''}
                    </div>
                  </div>
                  <div class="up-record-right">
                    <div style="font-size:11px;font-weight:600;color:#374151">${esc(date)}</div>
                    <div style="font-size:10px;color:#9ca3af">${esc(staff)}</div>
                    <div style="font-size:10px;color:#1a3c6e;font-weight:600;margin-top:2px">
                      View <i class="fas fa-external-link-alt" style="font-size:8px"></i>
                    </div>
                  </div>
                </a>`;
            });

            document.getElementById('up-records').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('up-records').innerHTML = '<div class="up-loading">Could not load records.</div>';
        });
}

function closeUserPanel() {
    document.getElementById('up-overlay').style.display = 'none';
    document.getElementById('up-panel').classList.remove('open');
    if (_upActiveRow) { _upActiveRow.style.background = ''; _upActiveRow = null; }
}
</script>

<?php Html::footer(); ?>
