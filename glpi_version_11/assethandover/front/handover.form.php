<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkLoginUser();
if (!Session::haveRight('plugin_assethandover', READ)) {
    Html::displayRightError();
    exit;
}

$id           = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$request_type = $_GET['request_type'] ?? 'handover';
$handover     = null;

if ($id > 0) {
    $handover = PluginAssethandoverHandover::getHandover($id);
    if (!$handover) { Html::displayErrorAndDie('Record not found', true); }
    $request_type = $handover['request_type'];
}

$from_handover_id = (int)($_GET['from_handover_id'] ?? 0);
$source_handover  = null;

// If creating return from a handover, pre-fill from source
if ($from_handover_id > 0 && $id === 0 && $request_type === 'return') {
    $source_handover = PluginAssethandoverHandover::getHandoverForReturn($from_handover_id);
    if ($source_handover) {
        // Pre-fill handover data into $handover array
        $handover = $source_handover;
        $handover['id']               = 0;
        $handover['request_type']     = 'return';
        $handover['from_handover_id'] = $from_handover_id;
        $handover['return_date']      = date('Y-m-d');
        $handover['handover_date']    = null;
        // Keep items — they'll pre-fill with asset data, condition fields blank
    }
}

$is_edit   = ($id > 0);
$is_return = ($request_type === 'return');
$title     = $is_return ? 'Asset Return Form' : 'Asset Handover Form';
$accent    = $is_return ? '#16a34a' : '#1a3c6e';
$accent_lt = $is_return ? '#f0fdf4' : '#eff6ff';

// Auto-fill Assigned By from logged-in user
global $DB;
$login_uid  = (int)Session::getLoginUserID();
// GLPI 11: use doQuery() for raw SQL; request() only accepts array criteria
$login_name = '';
$_ln_res = $DB->doQuery("SELECT CONCAT(COALESCE(firstname,''),' ',COALESCE(realname,'')) AS n FROM glpi_users WHERE id=$login_uid");
if ($_ln_res) { $login_name = trim($_ln_res->fetch_assoc()['n'] ?? ''); }
// Only auto-fill on new records
$autofill_assigned = ($id === 0 && !$is_return && empty($handover['assigned_by'])) ? $login_name : ($handover['assigned_by'] ?? '');
$autofill_checked  = ($id === 0 && $is_return  && empty($handover['checked_by']))  ? $login_name : ($handover['checked_by']  ?? '');

$success = $_SESSION['hw_success'] ?? '';
$error   = $_SESSION['hw_error']   ?? '';
unset($_SESSION['hw_success'], $_SESSION['hw_error']);

Html::header($title, '', 'assets', 'PluginAssethandoverHandover');
?>

<style>
/* ── Reset & base ── */
.ahf-wrap * { box-sizing: border-box; }
.ahf-wrap {
    max-width: 1200px; margin: 0 auto; padding: 20px 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ── Breadcrumb ── */
.ahf-breadcrumb { font-size: 12px; color: #6b7280; margin-bottom: 16px; display:flex; align-items:center; gap:6px; }
.ahf-breadcrumb a { color: #1a3c6e; text-decoration:none; font-weight:500; }
.ahf-breadcrumb a:hover { text-decoration:underline; }
.ahf-breadcrumb .sep { color: #d1d5db; }

/* ── Card ── */
.ahf-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
}

/* ── Card header ── */
.ahf-card-header {
    background: <?php echo $accent; ?>;
    padding: 18px 28px;
    display: flex; align-items: center; gap: 14px;
    border-radius: 14px 14px 0 0;
}
.ahf-card-header .icon-wrap {
    width: 40px; height: 40px; border-radius: 10px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #fff; flex-shrink: 0;
}
.ahf-card-header h2 { margin:0; font-size:17px; font-weight:700; color:#fff; letter-spacing:.2px; }
.ahf-card-header .record-badge {
    margin-left: auto;
    background: rgba(255,255,255,.22);
    color: #fff; font-size: 11px;
    padding: 4px 12px; border-radius: 20px;
    font-weight: 600; letter-spacing:.2px;
}

/* ── Card body ── */
.ahf-card-body { padding: 28px; overflow: visible; }

/* ── Section ── */
.ahf-section { margin-bottom: 28px; overflow: visible; }
.ahf-section-header {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 16px; padding-bottom: 10px;
    border-bottom: 2px solid <?php echo $accent; ?>;
}
.ahf-section-header .section-icon {
    width: 30px; height: 30px; border-radius: 8px;
    background: <?php echo $accent_lt; ?>;
    color: <?php echo $accent; ?>;
    display: flex; align-items:center; justify-content:center;
    font-size: 14px; flex-shrink:0;
}
.ahf-section-header h3 {
    margin: 0; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .7px;
    color: <?php echo $accent; ?>;
}

/* ── Grid ── */
.ahf-grid { display: grid; gap: 14px 18px; overflow: visible; }
.ahf-grid-3 { grid-template-columns: repeat(3, 1fr); }
.ahf-grid-4 { grid-template-columns: repeat(4, 1fr); }
.ahf-col-2  { grid-column: span 2; }
.ahf-col-3  { grid-column: span 3; }
.ahf-col-4  { grid-column: span 4; }
@media(max-width: 900px) {
    .ahf-grid-3, .ahf-grid-4 { grid-template-columns: 1fr 1fr; }
    .ahf-col-2, .ahf-col-3, .ahf-col-4 { grid-column: span 2; }
}
@media(max-width: 580px) {
    .ahf-grid-3, .ahf-grid-4 { grid-template-columns: 1fr; }
    .ahf-col-2, .ahf-col-3, .ahf-col-4 { grid-column: span 1; }
}

/* ── Field ── */
.ahf-field { overflow: visible; }
.ahf-label {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700;
    color: #374151; margin-bottom: 5px;
    text-transform: uppercase; letter-spacing:.4px;
}
.ahf-label .req { color: #ef4444; }
/* Removed auto/manual tags per requirement */
.ahf-input, .ahf-select {
    display: block; width: 100%;
    padding: 9px 12px; font-size: 13px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px; color: #111827;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
}
.ahf-input:focus, .ahf-select:focus {
    border-color: <?php echo $accent; ?>;
    box-shadow: 0 0 0 3px <?php echo $accent_lt; ?>;
}
.ahf-input[readonly] {
    background: #f9fafb; color: #6b7280; cursor: default;
    border-color: #f3f4f6;
}
.ahf-input::placeholder { color: #c0c8d0; }
/* Input with left icon */
.ahf-input-wrap { position: relative; }

/* ── Autocomplete dropdown ── */
.ahf-ac-wrap { position: relative; overflow: visible; }
.ahf-ac-list {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0;
    background: #fff; border: 1.5px solid #d1d5db;
    border-radius: 8px; box-shadow: 0 8px 28px rgba(0,0,0,.18);
    z-index: 99999; max-height: 320px; overflow-y: auto; display: none;
    min-width: 280px;
}
.ahf-ac-item {
    padding: 10px 14px; cursor: pointer;
    border-bottom: 1px solid #f3f4f6; font-size: 13px;
    line-height: 1.4;
}
.ahf-ac-item:last-child { border-bottom:none; }
.ahf-ac-item:hover { background: <?php echo $accent_lt; ?>; }
.ahf-ac-item .ac-name { font-weight: 600; color: #111827; }
.ahf-ac-item .ac-sub  { font-size: 11px; color: #6b7280; margin-top: 1px; }
.ahf-ac-item .ac-badge {
    display: inline-block; font-size: 10px; padding: 1px 6px;
    border-radius: 10px; background: <?php echo $accent_lt; ?>;
    color: <?php echo $accent; ?>; font-weight: 600; margin-left: 6px;
}

/* ── Asset history timeline ── */
.aht-timeline { margin-top: 8px; }
.aht-event {
    display: flex; gap: 12px; padding: 10px 0;
    border-bottom: 1px solid #f3f4f6; font-size: 12px;
}
.aht-event:last-child { border-bottom: none; }
.aht-dot-wrap {
    display: flex; flex-direction: column; align-items: center;
    flex-shrink: 0; padding-top: 2px;
}
.aht-dot {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; flex-shrink: 0; font-weight: 700;
}
.aht-dot-handover { background: #eff6ff; color: #1d4ed8; border: 2px solid #bfdbfe; }
.aht-dot-return   { background: #f0fdf4; color: #16a34a; border: 2px solid #bbf7d0; }
.aht-line { width: 2px; flex: 1; background: #e5e7eb; margin-top: 4px; }
.aht-body { flex: 1; min-width: 0; }
.aht-title { font-weight: 700; color: #111827; margin-bottom: 2px; }
.aht-title a { color: #1a3c6e; text-decoration: none; }
.aht-title a:hover { text-decoration: underline; }
.aht-meta  { color: #6b7280; font-size: 11px; line-height: 1.5; }
.aht-badge {
    display: inline-block; padding: 1px 7px; border-radius: 10px;
    font-size: 10px; font-weight: 700; margin-left: 4px;
}
.aht-badge-handover { background: #eff6ff; color: #1d4ed8; }
.aht-badge-return   { background: #f0fdf4; color: #16a34a; }
.aht-badge-good     { background: #dcfce7; color: #15803d; }
.aht-badge-damaged  { background: #fee2e2; color: #dc2626; }
.aht-badge-missing  { background: #fee2e2; color: #dc2626; }
.aht-empty { text-align:center; padding:16px; color:#9ca3af; font-size:12px; }

/* ── Asset records table ── */
.ahf-table-wrap { overflow-x: auto; border-radius: 8px; border: 1.5px solid #e5e7eb; }
.ahf-table {
    width: 100%; border-collapse: collapse;
    font-size: 12px; min-width: 980px;
}
.ahf-table thead tr {
    background: <?php echo $accent; ?>;
}
.ahf-table thead th {
    padding: 9px 10px; color: #fff;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    white-space: nowrap; border: none;
}
.ahf-table thead th:first-child { border-radius: 0; width: 36px; text-align:center; }
.ahf-table tbody tr { transition: background .1s; }
.ahf-table tbody tr:nth-child(even) { background: #f9fafb; }
.ahf-table tbody tr:hover { background: <?php echo $accent_lt; ?>; }
.ahf-table tbody td {
    padding: 5px 6px; vertical-align: middle;
    border-bottom: 1px solid #f3f4f6;
}
.ahf-table tbody tr:last-child td { border-bottom: none; }
.ahf-table .td-num { text-align:center; color:#9ca3af; font-weight:700; font-size:12px; width:36px; }

.ahf-table input.ahf-input, .ahf-table select.ahf-select {
    padding: 5px 8px; font-size: 12px; border-radius: 5px;
}

/* ── Add row button — prominent primary style ── */
.ahf-add-row-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; font-size: 13px; font-weight: 700;
    color: #fff; background: <?php echo $accent; ?>;
    border: none; border-radius: 8px; cursor: pointer;
    text-decoration: none; transition: all .15s;
    margin-left: 12px; box-shadow: 0 2px 8px <?php echo $accent; ?>40;
    letter-spacing: .2px;
}
.ahf-add-row-btn:hover {
    opacity: .88; transform: translateY(-1px);
    box-shadow: 0 4px 14px <?php echo $accent; ?>50;
}

/* ── Delete row button ── */
.ahf-del-btn {
    width: 26px; height: 26px; border-radius: 5px;
    background: #fee2e2; border: 1px solid #fca5a5;
    color: #dc2626; cursor: pointer; font-size: 12px;
    display: inline-flex; align-items:center; justify-content:center;
    transition: all .15s;
}
.ahf-del-btn:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

/* ── Empty state ── */
.ahf-empty {
    text-align: center; padding: 28px 20px; color: #9ca3af;
    font-size: 13px; border: 1.5px dashed #e5e7eb;
    border-radius: 8px; margin-top: 0;
}
.ahf-empty i { font-size: 24px; display:block; margin-bottom:6px; opacity:.4; }

/* ── Confirm checkbox — prominent redesign ── */
.ahf-confirm-box {
    margin-top: 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
}
.ahf-confirm-box.confirmed {
    border-color: <?php echo $accent; ?>;
    box-shadow: 0 0 0 3px <?php echo $accent_lt; ?>;
}
.ahf-confirm-label {
    display: flex; align-items: center; gap: 16px;
    padding: 16px 20px; cursor: pointer; margin: 0;
    background: #fff; transition: background .15s; user-select: none;
}
.ahf-confirm-label:hover { background: <?php echo $accent_lt; ?>; }
.ahf-confirm-check-wrap { flex-shrink: 0; position: relative; }
.ahf-confirm-check-wrap input[type=checkbox] {
    position: absolute; opacity: 0; width: 0; height: 0;
}
.ahf-confirm-icon {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; transition: all .2s;
    color: #d1d5db;
}
.ahf-confirm-box.confirmed .ahf-confirm-icon {
    color: <?php echo $accent; ?>;
}
.ahf-confirm-text { flex: 1; }
.ahf-confirm-title {
    font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 2px;
}
.ahf-confirm-sub {
    font-size: 12px; color: #6b7280; line-height: 1.4;
}

/* ── Remark textarea ── */
.ahf-textarea {
    display:block; width:100%; padding: 8px 10px; font-size:13px;
    border: 1.5px solid #d1d5db; border-radius:6px; color:#111827;
    resize: vertical; min-height: 60px; outline:none;
    transition: border-color .15s, box-shadow .15s;
}
.ahf-textarea:focus { border-color: <?php echo $accent; ?>; box-shadow: 0 0 0 3px <?php echo $accent_lt; ?>; }
.ahf-textarea::placeholder { color: #9ca3af; }

/* ── Action bar ── */
.ahf-actions {
    display: flex; gap: 8px; justify-content: flex-end;
    flex-wrap: wrap; margin-top: 20px;
    padding-top: 16px; border-top: 1px solid #f3f4f6;
}
.ahf-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 6px; font-size: 13px;
    font-weight: 600; cursor: pointer; border: 1.5px solid transparent;
    text-decoration: none; transition: all .15s;
}
.ahf-btn-primary   { background: <?php echo $accent; ?>; color:#fff; border-color: <?php echo $accent; ?>; }
.ahf-btn-primary:hover { opacity: .88; }
.ahf-btn-secondary { background:#fff; color:#374151; border-color:#d1d5db; }
.ahf-btn-secondary:hover { background:#f9fafb; }
.ahf-btn-info      { background:#fff; color:#0369a1; border-color:#bae6fd; }
.ahf-btn-info:hover { background:#f0f9ff; }

/* ── Flash alerts ── */
.ahf-alert {
    padding: 10px 14px; border-radius: 7px; margin-bottom: 14px;
    font-size: 13px; display:flex; align-items:center; gap:8px;
}
.ahf-alert-success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.ahf-alert-danger  { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }

/* ── Asset search modal ── */
.ahf-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 99990;
    align-items: center; justify-content: center;
}
.ahf-modal-overlay.open { display: flex; }
.ahf-modal {
    background: #fff; border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    width: 680px; max-width: 95vw;
    max-height: 80vh; display: flex; flex-direction: column;
    overflow: hidden; animation: modalIn .18s ease;
}
@keyframes modalIn {
    from { opacity:0; transform:translateY(-16px) scale(.97); }
    to   { opacity:1; transform:translateY(0)    scale(1);    }
}
.ahf-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.ahf-modal-header h4 { margin:0; font-size:14px; font-weight:700; color:#111827; }
.ahf-modal-close {
    width:28px; height:28px; border-radius:6px; border:none;
    background:#f3f4f6; cursor:pointer; font-size:14px; color:#6b7280;
    display:flex; align-items:center; justify-content:center;
    transition:all .15s;
}
.ahf-modal-close:hover { background:#fee2e2; color:#dc2626; }
.ahf-modal-search {
    padding: 12px 20px; border-bottom: 1px solid #f3f4f6; flex-shrink:0;
}
.ahf-modal-search-wrap { position:relative; display:flex; align-items:center; gap:8px; }
.ahf-modal-search-wrap .srch-icon {
    flex-shrink:0; color:#9ca3af; font-size:14px;
}
.ahf-modal-search-input {
    flex:1; width:100%; padding:8px 12px; font-size:13px;
    border:1.5px solid #d1d5db; border-radius:7px; outline:none;
    transition:border-color .15s;
}
.ahf-modal-search-input:focus { border-color:#1a3c6e; }
.ahf-modal-body { overflow-y:auto; flex:1; }
.ahf-modal-hint {
    text-align:center; padding:30px; color:#9ca3af; font-size:13px;
}
.ahf-modal-hint i { display:block; font-size:28px; margin-bottom:8px; opacity:.4; }
.ahf-modal-result {
    padding: 10px 20px; border-bottom:1px solid #f3f4f6;
    cursor:pointer; transition:background .1s; display:flex; align-items:center; gap:12px;
}
.ahf-modal-result:hover { background:#eff6ff; }
.ahf-modal-result .res-icon {
    width:34px; height:34px; border-radius:8px;
    background:#eff6ff; color:#1d4ed8;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; flex-shrink:0;
}
.ahf-modal-result .res-body { flex:1; min-width:0; }
.ahf-modal-result .res-name { font-weight:700; font-size:13px; color:#111827; }
.ahf-modal-result .res-type { font-size:10px; font-weight:700; color:#1d4ed8; background:#eff6ff; border-radius:10px; padding:1px 7px; margin-left:6px; }
.ahf-modal-result .res-meta { font-size:11px; color:#6b7280; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ahf-modal-loading { text-align:center; padding:30px; color:#9ca3af; font-size:13px; }

</style>

<div class="ahf-wrap">

  <!-- Breadcrumb -->
  <div class="ahf-breadcrumb">
    <a href="handover.php"><i class="fas fa-exchange-alt"></i> Asset Handover &amp; Return</a>
    <span class="sep">/</span>
    <span><?php echo $is_edit ? ($is_return ? 'Return' : 'Handover') . " #$id" : "New $title"; ?></span>
  </div>

  <?php if ($success): ?>
  <div class="ahf-alert ahf-alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="ahf-alert ahf-alert-danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($source_handover && $id === 0): ?>
  <div class="ahf-alert" style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;margin-bottom:14px;padding:10px 14px;border-radius:7px;font-size:13px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-link"></i>
    Pre-filled from <strong>Handover #<?php echo $from_handover_id; ?></strong>
    &nbsp;(<a href="handover.form.php?id=<?php echo $from_handover_id; ?>" style="color:#15803d">view source</a>).
    All assets pre-loaded — fill in Return Status &amp; Condition for each asset.
  </div>
  <?php endif; ?>

  <div class="ahf-card">

    <!-- Header -->
    <div class="ahf-card-header">
      <div class="icon-wrap">
        <i class="fas <?php echo $is_return ? 'fa-undo' : 'fa-share'; ?>"></i>
      </div>
      <h2><?php echo $title; ?></h2>
      <?php if ($is_edit): ?>
      <span class="record-badge">Record #<?php echo $id; ?></span>
      <?php endif; ?>
    </div>

    <div class="ahf-card-body">
    <form method="post" action="handover.save.php" id="hw-form">
      <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
      <input type="hidden" name="id"               value="<?php echo $id; ?>">
      <input type="hidden" name="request_type"     value="<?php echo htmlspecialchars($request_type); ?>">
      <input type="hidden" name="from_handover_id" value="<?php echo (int)($handover['from_handover_id'] ?? $from_handover_id); ?>">

      <!-- ── Section: User Information ── -->
      <div class="ahf-section">
        <div class="ahf-section-header">
          <div class="section-icon"><i class="fas fa-user"></i></div>
          <h3>User Information</h3>
        </div>

        <div class="ahf-grid ahf-grid-4">

          <!-- User search (spans 2) -->
          <div class="ahf-field ahf-col-2">
            <label class="ahf-label">User <span class="req">*</span></label>
            <div class="ahf-ac-wrap">
              <input type="text" id="user_search" class="ahf-input"
                     placeholder="Type name to search..."
                     value="<?php echo htmlspecialchars($handover['user_fullname'] ?? ''); ?>"
                     autocomplete="off">
              <input type="hidden" name="users_id" id="users_id"
                     value="<?php echo (int)($handover['users_id'] ?? 0); ?>">
              <div id="user_dropdown" class="ahf-ac-list"></div>
            </div>
          </div>

          <!-- Date -->
          <div class="ahf-field">
            <label class="ahf-label"><?php echo $is_return ? 'Return Date' : 'Handover Date'; ?></label>
            <?php if (!$is_return): ?>
            <input type="date" name="handover_date" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['handover_date'] ?? date('Y-m-d')); ?>">
            <?php else: ?>
            <input type="date" name="return_date" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['return_date'] ?? date('Y-m-d')); ?>">
            <?php endif; ?>
          </div>

          <!-- Gender -->
          <div class="ahf-field">
            <label class="ahf-label">Gender </label>
            <select name="gender" class="ahf-select">
              <option value="">— Select —</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?php echo $g; ?>" <?php echo ($handover['gender'] ?? '') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Employee ID -->
          <div class="ahf-field">
            <label class="ahf-label">Employee ID </label>
            <input type="text" name="employee_id" id="employee_id_field" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['employee_id'] ?? ''); ?>"
                   placeholder="From Administrative Number">
          </div>

          <!-- Position -->
          <div class="ahf-field ahf-col-2">
            <label class="ahf-label">Position </label>
            <input type="text" name="position" id="position_field" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['position'] ?? ''); ?>"
                   placeholder="Pulled from user Title">
          </div>

          <!-- Department -->
          <div class="ahf-field">
            <label class="ahf-label">Department </label>
            <input type="text" name="department" id="department_field" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['department'] ?? ''); ?>"
                   placeholder="Auto-filled">
          </div>

          <!-- Unit -->
          <div class="ahf-field">
            <label class="ahf-label">Unit </label>
            <input type="text" name="unit" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['unit'] ?? ''); ?>"
                   placeholder="Fill manually">
          </div>

          <!-- Manager Name (auto-filled from the user's supervisor in GLPI) -->
          <div class="ahf-field ahf-col-2">
            <label class="ahf-label">Manager's Name </label>
            <input type="text" name="manager_name" id="manager_field" class="ahf-input"
                   value="<?php echo htmlspecialchars($handover['manager_name'] ?? ''); ?>"
                   placeholder="Auto-filled">
          </div>

          <!-- Assigned / Checked By — auto-filled from logged-in user -->
          <div class="ahf-field ahf-col-2">
            <label class="ahf-label"><?php echo $is_return ? 'Checked By (IT)' : 'Assigned By (IT)'; ?></label>
            <?php if (!$is_return): ?>
            <input type="text" name="assigned_by" class="ahf-input"
                   value="<?php echo htmlspecialchars($autofill_assigned); ?>"
                   placeholder="IT staff name">
            <?php else: ?>
            <input type="text" name="checked_by" class="ahf-input"
                   value="<?php echo htmlspecialchars($autofill_checked); ?>"
                   placeholder="IT staff name">
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- ── Section: Asset Records ── -->
      <div class="ahf-section">
        <div class="ahf-section-header">
          <div class="section-icon"><i class="fas fa-laptop"></i></div>
          <h3>Asset Records</h3>
          <button type="button" onclick="addAssetRow()" class="ahf-add-row-btn">
            <i class="fas fa-plus-circle"></i> Add Asset
          </button>
        </div>

        <div id="asset-table-wrap">
        <div class="ahf-table-wrap">
        <table class="ahf-table" id="asset-table">
          <thead>
            <tr>
              <th>#</th>
              <th style="min-width:180px">Asset Name <span style="color:#fca5a5">*</span></th>
              <th style="min-width:200px">Description <span style="font-weight:400;opacity:.7;font-size:9px">(MFR | MODEL)</span></th>
              <th style="width:130px">Serial Number</th>
              <th style="width:120px">Asset Number</th>
              <th style="width:105px">Asset Type</th>
              <?php if (!$is_return): ?>
              <th style="width:110px">Condition</th>
              <?php else: ?>
              <th style="width:120px">Return Status</th>
              <th style="width:110px">Condition</th>
              <?php endif; ?>
              <th style="min-width:120px">Remarks</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody id="asset-tbody"></tbody>
        </table>
        </div>
        </div>

        <div id="no-assets-msg" class="ahf-empty" style="display:none">
          <i class="fas fa-box-open"></i>
          No assets added yet. Click <strong>+ Add Row</strong> to begin.
        </div>
      </div>

      <!-- ── Asset History (shown when editing) ── -->
      <?php if ($is_edit): ?>
      <div class="ahf-section" id="history-section">
        <div class="ahf-section-header">
          <div class="section-icon" style="background:#fef9c3;color:#854d0e"><i class="fas fa-history"></i></div>
          <h3 style="color:#854d0e">Asset History</h3>
          <span id="history-loading" style="margin-left:8px;font-size:11px;color:#9ca3af">
            <i class="fas fa-spinner fa-spin"></i> Loading...
          </span>
        </div>
        <div id="history-container">
          <!-- Populated by JS per asset row -->
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Overall Remark ── -->
      <div class="ahf-section" style="margin-bottom:14px">
        <label class="ahf-label" style="margin-bottom:5px">Overall Remark</label>
        <textarea name="remark" class="ahf-textarea"
                  placeholder="Optional overall notes..."><?php echo htmlspecialchars($handover['remark'] ?? ''); ?></textarea>
      </div>

      <!-- ── Confirmation (handover only) ── -->
      <?php if (!$is_return): ?>
      <div class="ahf-confirm-box" id="confirm-box">
        <label class="ahf-confirm-label" id="confirm-label">
          <div class="ahf-confirm-check-wrap">
            <input type="checkbox" name="user_confirmed" value="1" id="confirm_cb"
                   <?php echo !empty($handover['user_confirmed']) ? 'checked' : ''; ?>
                   onchange="updateConfirmStyle()">
            <div class="ahf-confirm-icon" id="confirm-icon">
              <i class="fas fa-<?php echo !empty($handover['user_confirmed']) ? 'check-circle' : 'circle'; ?>" id="confirm-fa"></i>
            </div>
          </div>
          <div class="ahf-confirm-text">
            <div class="ahf-confirm-title">Confirm Receipt</div>
            <div class="ahf-confirm-sub">User has received all listed assets in good condition and accepts responsibility</div>
          </div>
        </label>
      </div>
      <?php endif; ?>

      <!-- ── Actions ── -->
      <div class="ahf-actions">
        <a href="handover.php?tab=<?php echo $is_return?'return':'handover'; ?>" class="ahf-btn ahf-btn-secondary">
          <i class="fas fa-times"></i> Cancel
        </a>
        <?php if ($is_edit): ?>
        <a href="handover.pdf.php?id=<?php echo $id; ?>" target="_blank" class="ahf-btn ahf-btn-info">
          <i class="fas fa-print"></i> Print Slip
        </a>
        <?php endif; ?>
        <button type="submit" class="ahf-btn ahf-btn-primary">
          <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Record' : 'Save Record'; ?>
        </button>
      </div>

    </form>
    </div><!-- /card-body -->
  </div><!-- /card -->
</div><!-- /wrap -->

<!-- ── Asset Search Modal ── -->
<div id="asset-modal" class="ahf-modal-overlay" onclick="if(event.target===this)closeAssetModal()">
  <div class="ahf-modal">
    <div class="ahf-modal-header">
      <h4><i class="fas fa-laptop" style="margin-right:8px;color:#1a3c6e"></i>Select Asset</h4>
      <button type="button" class="ahf-modal-close" onclick="closeAssetModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ahf-modal-search">
      <div class="ahf-modal-search-wrap">
        <i class="fas fa-search srch-icon"></i>
        <input type="text" id="modal-search-input" class="ahf-modal-search-input"
               placeholder="Search by asset name, serial number, or asset code...">
      </div>
    </div>
    <div class="ahf-modal-body" id="modal-results">
      <div class="ahf-modal-hint">
        <i class="fas fa-search"></i>
        Type at least 2 characters to search assets
      </div>
    </div>
  </div>
</div>

<script>
const IS_RETURN     = <?php echo $is_return ? 'true' : 'false'; ?>;
const ACCENT_COLOR  = '<?php echo $accent; ?>';
const AJAX_URL      = '<?php echo Plugin::getWebDir('assethandover'); ?>/front/handover.ajax.php';
let rowIndex        = 0;
const existingItems = <?php echo json_encode($handover['items'] ?? []); ?>;

// ── Build one asset row ──
function buildRow(idx, item) {
    item = item || {};
    const ci = item.condition_issue  || '';
    const cr = item.condition_return || '';
    const rs = item.return_status    || '';

    let statusCols = '';
    if (!IS_RETURN) {
        statusCols = `
        <td>
          <select name="items[${idx}][condition_issue]" class="ahf-select">
            <option value="">—</option>
            <option value="New"  ${ci==='New'  ?'selected':''}>New</option>
            <option value="Good" ${ci==='Good' ?'selected':''}>Good</option>
            <option value="Used" ${ci==='Used' ?'selected':''}>Used</option>
          </select>
        </td>`;
    } else {
        statusCols = `
        <td>
          <select name="items[${idx}][return_status]" class="ahf-select">
            <option value="">—</option>
            <option value="Returned"     ${rs==='Returned'     ?'selected':''}>Returned</option>
            <option value="Not Returned" ${rs==='Not Returned' ?'selected':''}>Not Returned</option>
          </select>
        </td>
        <td>
          <select name="items[${idx}][condition_return]" class="ahf-select">
            <option value="">—</option>
            <option value="Good"    ${cr==='Good'    ?'selected':''}>Good</option>
            <option value="Damaged" ${cr==='Damaged' ?'selected':''}>Damaged</option>
            <option value="Missing" ${cr==='Missing' ?'selected':''}>Missing</option>
          </select>
        </td>`;
    }

    return `
    <tr id="row-${idx}" data-idx="${idx}">
      <td class="td-num"><span class="row-num"></span></td>
      <td>
        <div style="display:flex;gap:5px;align-items:center">
          <input type="text" class="ahf-input f-asset-display" data-idx="${idx}"
                 value="${esc(item.asset_name||'')}"
                 placeholder="Click 🔍 to select asset"
                 readonly style="cursor:pointer;flex:1;background:#f9fafb"
                 onclick="openAssetModal(${idx})">
          <button type="button" onclick="openAssetModal(${idx})"
                  style="flex-shrink:0;width:30px;height:32px;border-radius:6px;border:1.5px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center"
                  title="Search asset">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <input type="hidden" name="items[${idx}][asset_id]"      class="f-asset-id"      value="${item.asset_id||''}">
        <input type="hidden" name="items[${idx}][asset_itemtype]" class="f-asset-itemtype" value="${esc(item.asset_itemtype||'')}">
        <input type="hidden" name="items[${idx}][asset_name]"     class="f-asset-name"     value="${esc(item.asset_name||'')}">
      </td>
      <td>
        <input type="text" name="items[${idx}][description]" class="ahf-input f-description"
               value="${esc(item.description||'')}" placeholder="Mfr | Model">
      </td>
      <td>
        <input type="text" name="items[${idx}][serial_number]" class="ahf-input f-serial"
               value="${esc(item.serial_number||item.serial||'')}" placeholder="Auto-filled">
      </td>
      <td>
        <input type="text" name="items[${idx}][asset_number]" class="ahf-input f-asset-number"
               value="${esc(item.asset_number||'')}" placeholder="Auto-filled">
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:4px">
          <input type="text" name="items[${idx}][asset_type]" class="ahf-input f-asset-type"
                 value="${esc(item.asset_type||'')}" placeholder="Auto" readonly style="flex:1">
          <a class="f-glpi-link" href="#" target="_blank"
             title="Open in GLPI" style="display:none;color:#1d4ed8;font-size:12px;text-decoration:none;flex-shrink:0">
            <i class="fas fa-external-link-alt"></i>
          </a>
        </div>
      </td>
      ${statusCols}
      <td>
        <input type="text" name="items[${idx}][item_remark]" class="ahf-input"
               value="${esc(item.item_remark||'')}" placeholder="Optional">
      </td>
      <td style="text-align:center">
        <button type="button" class="ahf-del-btn" onclick="removeRow(${idx})" title="Remove">
          <i class="fas fa-times"></i>
        </button>
      </td>
    </tr>`;
}

function addAssetRow(item) {
    const tbody = document.getElementById('asset-tbody');
    const tmp   = document.createElement('tbody');
    tmp.innerHTML = buildRow(rowIndex, item);
    tbody.appendChild(tmp.querySelector('tr'));
    rowIndex++;
    reNumber();
    toggleEmpty();
}

function removeRow(idx) {
    const r = document.getElementById('row-' + idx);
    if (r) { r.style.transition='opacity .2s'; r.style.opacity='0'; setTimeout(()=>{r.remove();reNumber();toggleEmpty();},180); }
}

function reNumber() {
    document.querySelectorAll('#asset-tbody tr').forEach((tr, i) => {
        const s = tr.querySelector('.row-num');
        if (s) s.textContent = i + 1;
    });
}

function toggleEmpty() {
    const n = document.querySelectorAll('#asset-tbody tr').length;
    document.getElementById('no-assets-msg').style.display    = n === 0 ? 'block' : 'none';
    document.getElementById('asset-table-wrap').style.display = n === 0 ? 'none'  : 'block';
}

// ── Asset Modal ──
let _modalTargetIdx = null;
let _modalTimer     = null;

function openAssetModal(idx) {
    _modalTargetIdx = idx;
    const modal = document.getElementById('asset-modal');
    modal.classList.add('open');
    const inp = document.getElementById('modal-search-input');
    inp.value = '';
    document.getElementById('modal-results').innerHTML = `
        <div class="ahf-modal-hint">
          <i class="fas fa-search"></i>
          Type at least 2 characters to search assets
        </div>`;
    setTimeout(() => inp.focus(), 80);
    document.body.style.overflow = 'hidden';
}

function closeAssetModal() {
    document.getElementById('asset-modal').classList.remove('open');
    document.body.style.overflow = '';
    _modalTargetIdx = null;
}

function fillAsset(idx, d) {
    const row = document.getElementById('row-' + idx);
    if (!row) return;
    row.querySelector('.f-asset-display').value   = d.name;
    row.querySelector('.f-asset-id').value        = d.id;
    row.querySelector('.f-asset-itemtype').value  = d.itemtype;
    row.querySelector('.f-asset-name').value      = d.name;
    row.querySelector('.f-description').value     = d.desc;
    row.querySelector('.f-serial').value          = d.serial;
    row.querySelector('.f-asset-number').value    = d.assetnum;
    row.querySelector('.f-asset-type').value      = d.atype;
    // #2 — GLPI linkback (use GLPI root from PHP so path is always correct)
    const link = row.querySelector('.f-glpi-link');
    if (link && d.id > 0 && d.itemtype) {
        const typeMap = {
            'Computer':'computer','Monitor':'monitor','Printer':'printer',
            'Phone':'phone','Peripheral':'peripheral','NetworkEquipment':'networkequipment'
        };
        const glpiType = typeMap[d.itemtype] || 'computer';
        const glpiRoot = '<?php global $CFG_GLPI; echo rtrim($CFG_GLPI["root_doc"],"/"); ?>';
        link.href = glpiRoot + '/front/' + glpiType + '.form.php?id=' + d.id;
        link.style.display = 'inline-block';
    } else if (link) { link.style.display = 'none'; }
    // #5 — Duplicate asset guard
    checkDuplicateAsset(idx, d.id, d.itemtype, d.serial, d.assetnum);
}

// Icon map for asset types
function assetIcon(type) {
    const t = (type||'').toLowerCase();
    if (t.includes('computer') || t.includes('laptop')) return 'fa-laptop';
    if (t.includes('monitor') || t.includes('screen'))  return 'fa-desktop';
    if (t.includes('phone'))                             return 'fa-mobile-alt';
    if (t.includes('printer'))                           return 'fa-print';
    if (t.includes('network'))                           return 'fa-network-wired';
    return 'fa-box';
}

const _modalSearchInp = document.getElementById('modal-search-input');
if (_modalSearchInp) _modalSearchInp.addEventListener('input', function() {
    clearTimeout(_modalTimer);
    const q = this.value.trim();
    const body = document.getElementById('modal-results');
    if (q.length < 2) {
        body.innerHTML = `<div class="ahf-modal-hint"><i class="fas fa-search"></i>Type at least 2 characters</div>`;
        return;
    }
    body.innerHTML = `<div class="ahf-modal-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>`;
    _modalTimer = setTimeout(() => {
        fetch(AJAX_URL + '?action=search_assets&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    body.innerHTML = `<div class="ahf-modal-hint"><i class="fas fa-box-open"></i>No assets found for "${esc(q)}"</div>`;
                    return;
                }
                body.innerHTML = data.map(a => `
                    <div class="ahf-modal-result"
                         data-id="${a.id}" data-itemtype="${esc(a.itemtype)}"
                         data-name="${esc(a.asset_name)}" data-atype="${esc(a.asset_type)}"
                         data-desc="${esc(a.description)}"
                         data-serial="${esc(a.serial||'')}"
                         data-assetnum="${esc(a.asset_number||'')}">
                      <div class="res-icon"><i class="fas ${assetIcon(a.itemtype)}"></i></div>
                      <div class="res-body">
                        <div class="res-name">
                          ${esc(a.asset_name)}
                          <span class="res-type">${esc(a.asset_type)}</span>
                        </div>
                        <div class="res-meta">
                          ${a.serial ? 'S/N: '+esc(a.serial) : ''}
                          ${a.asset_number ? ' &nbsp;|&nbsp; Asset#: '+esc(a.asset_number) : ''}
                          ${a.description ? ' &nbsp;|&nbsp; '+esc(a.description) : ''}
                        </div>
                      </div>
                    </div>`).join('');
                body.querySelectorAll('.ahf-modal-result').forEach(el => {
                    el.addEventListener('click', function() {
                        fillAsset(_modalTargetIdx, this.dataset);
                        closeAssetModal();
                    });
                });
            })
            .catch(() => {
                body.innerHTML = `<div class="ahf-modal-hint"><i class="fas fa-exclamation-circle"></i>Search error, please try again.</div>`;
            });
    }, 300);
});

// ESC key closes modal
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAssetModal(); });

// ── User autocomplete ──
(function() {
    const inp  = document.getElementById('user_search');
    const hid  = document.getElementById('users_id');
    const list = document.getElementById('user_dropdown');
    let t;

    inp.addEventListener('input', function() {
        clearTimeout(t);
        hid.value = 0;
        const q = this.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        t = setTimeout(() => {
            fetch(AJAX_URL + '?action=search_users&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) { list.style.display = 'none'; return; }
                    list.innerHTML = data.map(u => `
                        <div class="ahf-ac-item" data-id="${u.id}" data-name="${esc(u.fullname)}"
                             data-pos="${esc(u.position)}" data-emp="${esc(u.employee_id)}">
                          <span class="ac-name">${esc(u.fullname)}</span>
                          <div class="ac-sub">${esc(u.username)}
                            ${u.position    ? ' | '+esc(u.position)    : ''}
                            ${u.employee_id ? ' | '+esc(u.employee_id) : ''}
                          </div>
                        </div>`).join('');
                    list.style.display = 'block';
                    list.querySelectorAll('.ahf-ac-item').forEach(it => {
                        it.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            inp.value = this.dataset.name;
                            hid.value = this.dataset.id;
                            list.style.display = 'none';
                            if (this.dataset.pos) document.getElementById('position_field').value    = this.dataset.pos;
                            if (this.dataset.emp) document.getElementById('employee_id_field').value = this.dataset.emp;
                            fetch(AJAX_URL + '?action=user_detail&users_id=' + this.dataset.id)
                                .then(r => r.json())
                                .then(d => {
                                    if (d.department)   document.getElementById('department_field').value = d.department;
                                    // Manager = user's supervisor in GLPI (overwrites on each user selection)
                                    if (d.manager_name) document.getElementById('manager_field').value    = d.manager_name;
                                    if (d.position   && !document.getElementById('position_field').value)
                                        document.getElementById('position_field').value = d.position;
                                    if (d.employee_id && !document.getElementById('employee_id_field').value)
                                        document.getElementById('employee_id_field').value = d.employee_id;;
                                });
                        });
                    });
                });
        }, 280);
    });
    inp.addEventListener('blur', () => setTimeout(() => list.style.display = 'none', 200));
})();

// #5 — Duplicate asset guard
const _dupWarnings = {};
function checkDuplicateAsset(idx, assetId, itemtype, serial, assetnum) {
    if (!assetId && !serial && !assetnum) return;
    const currentId = <?php echo $id > 0 ? $id : 0; ?>;
    const params = new URLSearchParams({
        action: 'check_asset_active',
        asset_id: assetId || 0,
        asset_itemtype: itemtype || '',
        serial_number: serial || '',
        asset_number: assetnum || '',
        exclude_id: currentId
    });
    fetch(AJAX_URL + '?' + params)
        .then(r => r.json())
        .then(conflict => {
            removeDupWarning(idx);
            if (conflict && conflict.id) {
                showDupWarning(idx, conflict);
            }
        });
}
function showDupWarning(idx, conflict) {
    const row = document.getElementById('row-' + idx);
    if (!row) return;
    // Insert as a <tr> (valid inside <tbody>) spanning all columns
    const colCount = row.querySelectorAll('td').length;
    const warn = document.createElement('tr');
    warn.className = 'dup-warn';
    warn.dataset.idx = idx;
    warn.innerHTML = `<td colspan="${colCount}" style="padding:0">
        <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:#fef2f2;border-top:1px solid #fca5a5;font-size:11px;color:#991b1b">
          <i class="fas fa-exclamation-circle" style="color:#dc2626;flex-shrink:0"></i>
          <span>
            <strong>Asset already issued</strong> — currently with
            <strong>${esc(conflict.user_fullname)}</strong>
            ${conflict.handover_date ? 'since '+esc(conflict.handover_date) : ''}.
            <a href="handover.form.php?id=${conflict.id}" target="_blank"
               style="color:#991b1b;font-weight:700;margin-left:4px">View #${conflict.id} →</a>
          </span>
        </div>
      </td>`;
    row.after(warn);
    _dupWarnings[idx] = warn;
}
function removeDupWarning(idx) {
    if (_dupWarnings[idx]) {
        _dupWarnings[idx].remove();
        delete _dupWarnings[idx];
    }
}

// ── Confirmation checkbox style ──
function updateConfirmStyle() {
    const cb   = document.getElementById('confirm_cb');
    const box  = document.getElementById('confirm-box');
    const icon = document.getElementById('confirm-fa');
    if (!cb || !box || !icon) return;
    if (cb.checked) {
        box.classList.add('confirmed');
        icon.className = 'fas fa-check-circle';
    } else {
        box.classList.remove('confirmed');
        icon.className = 'fas fa-circle';
    }
}
// Init on load
document.addEventListener('DOMContentLoaded', updateConfirmStyle);

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Validation ──
document.getElementById('hw-form').addEventListener('submit', function(e) {
    if (!document.getElementById('users_id').value || document.getElementById('users_id').value === '0') {
        e.preventDefault();
        alert('Please select a user.');
        document.getElementById('user_search').focus();
        return;
    }
    document.querySelectorAll('#asset-tbody tr').forEach(row => {
        const vis = row.querySelector('.f-asset-display');
        const hid = row.querySelector('.f-asset-name');
        if (vis && hid && !hid.value) hid.value = vis.value;
    });
});

// ── Init ──
existingItems.forEach(item => addAssetRow(item));
if (existingItems.length === 0) addAssetRow();
toggleEmpty();

// ── Asset History Timeline ──
<?php if ($is_edit): ?>
(function loadAssetHistory() {
    const container = document.getElementById('history-container');
    const loading   = document.getElementById('history-loading');
    if (!container) return;

    // Collect all serial numbers and asset numbers from existing items
    const serials   = <?php echo json_encode(array_filter(array_column($handover['items'] ?? [], 'serial_number'))); ?>;
    const assetNums = <?php echo json_encode(array_filter(array_column($handover['items'] ?? [], 'asset_number'))); ?>;

    if (!serials.length && !assetNums.length) {
        loading.style.display = 'none';
        container.innerHTML = '<div class="aht-empty"><i class="fas fa-info-circle"></i> No serial/asset numbers to trace history for.</div>';
        return;
    }

    // Fetch history for each unique identifier
    const seen    = new Set();
    const fetches = [];
    serials.forEach(s => { if (s && !seen.has('s:'+s)) { seen.add('s:'+s); fetches.push({serial:s, assetnum:''}); } });
    assetNums.forEach(n => { if (n && !seen.has('n:'+n)) { seen.add('n:'+n); fetches.push({serial:'', assetnum:n}); } });

    Promise.all(fetches.map(f =>
        fetch(AJAX_URL + '?action=asset_history&serial=' + encodeURIComponent(f.serial) + '&assetnum=' + encodeURIComponent(f.assetnum))
            .then(r => r.json())
    )).then(results => {
        loading.style.display = 'none';

        // Flatten and deduplicate events by handover_id
        const allEvents = [];
        const seenIds   = new Set();
        results.forEach(events => {
            events.forEach(ev => {
                const key = ev.handover_id + ':' + ev.serial_number + ':' + ev.asset_number;
                if (!seenIds.has(key)) { seenIds.add(key); allEvents.push(ev); }
            });
        });

        if (!allEvents.length) {
            container.innerHTML = '<div class="aht-empty">No previous history found for these assets.</div>';
            return;
        }

        // Sort by date
        allEvents.sort((a,b) => a.date_creation.localeCompare(b.date_creation));

        // Group by asset (serial_number or asset_number)
        const byAsset = {};
        allEvents.forEach(ev => {
            const key = ev.serial_number || ev.asset_number || ev.asset_name;
            if (!byAsset[key]) byAsset[key] = [];
            byAsset[key].push(ev);
        });

        // Build table: one block per asset, rows = events
        const glpiRoot = '<?php global $CFG_GLPI; echo rtrim($CFG_GLPI["root_doc"],"/"); ?>';
        const typeMap  = {
            'Computer':'computer','Monitor':'monitor','Printer':'printer',
            'Phone':'phone','Peripheral':'peripheral','NetworkEquipment':'networkequipment'
        };
        const currentId = <?php echo $id; ?>;

        let html = '';
        for (const [assetKey, events] of Object.entries(byAsset)) {
            const first      = events[0];
            const assetName  = first.asset_name      || assetKey;
            const assetSN    = first.serial_number   || '';
            const assetAN    = first.asset_number    || '';
            const assetId    = parseInt(first.asset_id || 0);
            const itemtype   = first.asset_itemtype  || 'Computer';
            const glpiType   = typeMap[itemtype]     || 'computer';

            // Asset name — link to GLPI if we have the ID
            const assetLink = assetId > 0
                ? `<a href="${glpiRoot}/front/${glpiType}.form.php?id=${assetId}" target="_blank"
                      style="font-weight:700;color:#1a3c6e;text-decoration:none;font-size:13px">
                     <i class="fas fa-laptop" style="font-size:11px;margin-right:4px;opacity:.6"></i>${esc(assetName)}
                     <i class="fas fa-external-link-alt" style="font-size:9px;margin-left:3px;opacity:.5"></i>
                   </a>`
                : `<span style="font-weight:700;color:#1a3c6e;font-size:13px">
                     <i class="fas fa-laptop" style="font-size:11px;margin-right:4px;opacity:.6"></i>${esc(assetName)}
                   </span>`;

            const subMeta = [
                assetSN ? `S/N: <strong>${esc(assetSN)}</strong>` : '',
                assetAN ? `Asset#: <strong>${esc(assetAN)}</strong>` : ''
            ].filter(Boolean).join(' &nbsp;·&nbsp; ');

            html += `
            <div style="margin-bottom:18px;border:1px solid #e5e7eb;border-radius:9px;overflow:hidden">
              <!-- Asset header -->
              <div style="padding:10px 14px;background:#f8faff;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px">
                <div style="flex:1">${assetLink}${subMeta ? '<div style="font-size:11px;color:#6b7280;margin-top:2px">'+subMeta+'</div>' : ''}</div>
                <span style="font-size:11px;color:#9ca3af">${events.length} event${events.length>1?'s':''}</span>
              </div>
              <!-- Events table -->
              <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                  <tr style="background:#f3f4f6">
                    <th style="padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;border-bottom:1px solid #e5e7eb">Type</th>
                    <th style="padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e5e7eb">Staff (Receiver / Returner)</th>
                    <th style="padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e5e7eb">Updated By</th>
                    <th style="padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;border-bottom:1px solid #e5e7eb">Date &amp; Time</th>
                    <th style="padding:7px 12px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e5e7eb">Record</th>
                  </tr>
                </thead>
                <tbody>`;

            events.forEach((ev, idx) => {
                const isHandover  = ev.request_type === 'handover';
                const isCurrent   = ev.handover_id == currentId;
                const staffName   = esc(ev.user_fullname || '—');
                const updatedBy   = esc(isHandover ? (ev.assigned_by||'—') : (ev.checked_by||'—'));
                const dateRaw     = (isHandover ? ev.handover_date : ev.return_date) || ev.date_creation.substring(0,10);
                const timeRaw     = ev.date_creation ? ev.date_creation.substring(11,16) : '';
                const rowBg       = isCurrent ? '#fffbeb' : (idx % 2 === 1 ? '#fafafa' : '#fff');
                const typeBadge   = isHandover
                    ? `<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe"><i class="fas fa-share" style="font-size:8px"></i> Handover</span>`
                    : `<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0"><i class="fas fa-undo" style="font-size:8px"></i> Return</span>`;
                const currentMark = isCurrent
                    ? `<span style="font-size:9px;color:#d97706;font-weight:700;margin-left:4px">◀ current</span>` : '';

                html += `
                  <tr style="background:${rowBg};border-bottom:1px solid #f3f4f6">
                    <td style="padding:9px 12px;white-space:nowrap">${typeBadge}${currentMark}</td>
                    <td style="padding:9px 12px">
                      <span style="font-weight:600;color:#111827">${staffName}</span>
                    </td>
                    <td style="padding:9px 12px;color:#374151">${updatedBy}</td>
                    <td style="padding:9px 12px;white-space:nowrap;color:#374151">
                      <span style="font-weight:500">${esc(dateRaw)}</span>
                      ${timeRaw ? '<span style="color:#9ca3af;font-size:11px;margin-left:4px"><i class="fas fa-clock" style="font-size:9px"></i> '+esc(timeRaw)+'</span>' : ''}
                    </td>
                    <td style="padding:9px 12px;text-align:center">
                      <a href="handover.form.php?id=${ev.handover_id}"
                         style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;color:#1a3c6e;text-decoration:none;padding:2px 8px;border:1px solid #bfdbfe;border-radius:4px;background:#eff6ff;white-space:nowrap">
                        <i class="fas fa-external-link-alt" style="font-size:8px"></i> #${ev.handover_id}
                      </a>
                    </td>
                  </tr>`;
            });

            html += `</tbody></table></div>`;
        }

        container.innerHTML = html;
    }).catch(() => {
        loading.style.display = 'none';
        container.innerHTML = '<div class="aht-empty">Could not load history.</div>';
    });
})();
<?php endif; ?>
</script>

<?php Html::footer(); ?>
