<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can\'t access this file directly");
}

class PluginAssethandoverPdf {

    /**
     * Company logo as a data URI. Looks for pics/bio-logo.(png|jpg|jpeg|svg)
     * in the plugin folder. Returns '' when no file is found.
     */
    static function getLogoDataUri() {
        $dir   = dirname(__DIR__) . '/pics/';
        $types = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
        ];
        foreach ($types as $ext => $mime) {
            $file = $dir . 'bio-logo.' . $ext;
            if (is_readable($file)) {
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
            }
        }
        return '';
    }

    static function generate($handover_id) {
        $handover = PluginAssethandoverHandover::getHandover((int)$handover_id);
        if (!$handover) die("Record not found.");

        $is_return  = $handover['request_type'] === 'return';
        $form_title = $is_return ? 'IT INVENTORY RETURN FORM' : 'IT INVENTORY HANDOVER FORM';
        $date_val   = $is_return ? ($handover['return_date']   ?: date('Y-m-d'))
                                 : ($handover['handover_date'] ?: date('Y-m-d'));
        $staff_label = $is_return ? 'Checked By' : 'Prepared by';
        $staff_name  = $is_return ? ($handover['checked_by']  ?? '')
                                  : ($handover['assigned_by'] ?? '');

        $fullname  = trim($handover['user_fullname'] ?? '');
        $parts     = explode(' ', $fullname, 2);
        $firstname = $parts[0] ?? '';
        $lastname  = $parts[1] ?? '';

        $logo      = self::getLogoDataUri();
        $logo_html = $logo
            ? '<img src="' . $logo . '" alt="bio">'
            : '<span class="logo-text">bio</span>';
        ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($form_title); ?> #<?php echo (int)$handover_id; ?></title>
<style>
  :root {
    --brand-red: #E1251B;
    --brand-yellow: #FFDD00;
    --brand-green: #00953B;
    --brand-blue: #106CAD;
    --brand-blue-dark: #0B4C7A;
    --brand-tint: #EAF3FA;
    --line: #cfe2f2;
    --text-dark: #1d2b3a;
  }

  /* Force print background colors in all browsers */
  * {
    box-sizing: border-box; margin: 0; padding: 0;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }

  @page { size: A4 landscape; margin: 0; }

  html {
    font-family: Arial, sans-serif;
    font-size: 11pt;
    color: var(--text-dark);
    background: #fff;
    width: 100%;
  }
  body { padding: 0; }

  /* ── Branded header ── */
  .brand-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 4mm 14mm;
    background: linear-gradient(90deg, var(--brand-blue-dark), var(--brand-blue));
    border-bottom: 3px solid var(--brand-yellow);
    color: #fff;
  }
  .brand-logo {
    background: #fff; border-radius: 6pt; padding: 4pt 10pt;
    height: 38pt; min-width: 80pt;
    display: flex; align-items: center; justify-content: center;
  }
  .brand-logo img { max-height: 30pt; width: auto; display: block; }
  .brand-logo .logo-text { font-size: 22pt; font-weight: 800; color: var(--brand-blue-dark); }
  .brand-title { flex: 1; text-align: center; font-size: 16pt; font-weight: 700; letter-spacing: .5px; }
  .brand-meta  { min-width: 80pt; text-align: right; font-size: 9pt; line-height: 1.5; }

  .brand-stripe, .foot-stripe {
    height: 5px;
    background: linear-gradient(90deg,
      var(--brand-red) 0 25%, var(--brand-yellow) 25% 50%,
      var(--brand-green) 50% 75%, var(--brand-blue) 75% 100%);
  }
  .foot-stripe { position: fixed; left: 0; right: 0; bottom: 0; }

  .content { padding: 6mm 14mm 10mm; }

  /* ── Info grid ── */
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
  .info-table td {
    border: 0.5px solid var(--line);
    padding: 3pt 7pt;
    font-size: 10pt;
    white-space: nowrap;
  }
  .info-table tr:nth-child(odd) td { background: var(--brand-tint) !important; }
  .info-table .lbl { font-weight: 700; color: var(--brand-blue-dark); }

  /* ── Section label ── */
  .section-lbl {
    font-weight: 700; font-size: 10pt; color: var(--brand-blue-dark);
    border-left: 4pt solid var(--brand-red); padding-left: 6pt;
    margin-bottom: 6pt;
  }

  /* ── Asset table ── */
  .asset-table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
  .asset-table th {
    background-color: var(--brand-blue) !important;
    color: #fff !important;
    border: 0.5px solid var(--brand-blue-dark);
    padding: 4pt 6pt;
    text-align: center;
    font-size: 9pt;
    font-weight: 700;
    letter-spacing: .3px;
  }
  .asset-table td {
    border: 0.5px solid var(--line);
    padding: 5pt 6pt;
    vertical-align: top;
    font-size: 10pt;
  }
  .asset-table tbody tr:nth-child(even) td { background: var(--brand-tint) !important; }
  .asset-table td.c-no  { text-align: center; width: 22pt; }
  .asset-table td.c-qty { text-align: center; width: 28pt; }
  .asset-table td.c-sn  { width: 90pt; text-align: center; font-size: 9pt; word-break: break-all; }
  .asset-table td.c-ac  { width: 90pt; text-align: center; font-size: 9pt; }
  .asset-table td.c-rs  { width: 60pt; text-align: center; font-size: 9pt; }
  .asset-table td.c-cn  { width: 55pt; text-align: center; font-size: 9pt; }
  .asset-table td.c-rem { width: 60pt; font-size: 9pt; }
  .asset-table .empty-row td { height: 1pt; }

  /* ── Badges ── */
  .badge { display: inline-block; padding: 1pt 5pt; border-radius: 3pt; font-size: 8pt; font-weight: 700; }
  .b-new     { background: #dbeafe !important; color: var(--brand-blue-dark) !important; }
  .b-good    { background: #d9f2e3 !important; color: var(--brand-green) !important; }
  .b-used    { background: #fff6bf !important; color: #7a6200 !important; }
  .b-damaged { background: #fde1df !important; color: var(--brand-red) !important; }
  .b-missing { background: #fde1df !important; color: var(--brand-red) !important; }

  /* ── Checkboxes ── */
  .check-section { margin-bottom: 10pt; page-break-inside: avoid; }
  .check-row { display: flex; align-items: flex-start; gap: 7pt; margin-bottom: 5pt; font-size: 9pt; }
  .check-box { width: 11pt; height: 11pt; border: 1.5px solid var(--brand-blue-dark); flex-shrink: 0; margin-top: 1pt; display: inline-block; }

  /* ── Signatures ── */
  .sig-row   { display: flex; gap: 40pt; margin-top: 18pt; page-break-inside: avoid; }
  .sig-block { flex: 1; }
  .sig-title { font-weight: 700; font-size: 10pt; color: var(--brand-blue-dark); margin-bottom: 18pt; }
  .sig-line  { border-top: 1px solid var(--brand-blue-dark); margin-bottom: 4pt; }
  .sig-field { font-size: 10pt; margin-bottom: 3pt; }
  .sig-field .lbl { font-weight: 700; color: var(--brand-blue-dark); }

  .remark-box {
    margin-top: 8pt; margin-bottom: 10pt; padding: 5pt 9pt;
    background: #fffbe0 !important; border-left: 3pt solid var(--brand-yellow);
    font-size: 9.5pt;
  }
</style>
</head>
<body>

<div class="brand-header">
  <div class="brand-logo"><?php echo $logo_html; ?></div>
  <div class="brand-title"><?php echo htmlspecialchars($form_title); ?></div>
  <div class="brand-meta">Ref #<?php echo (int)$handover_id; ?><br><?php echo htmlspecialchars($date_val); ?></div>
</div>
<div class="brand-stripe"></div>

<div class="content">

<table class="info-table">
  <tr>
    <td style="width:25%"><span class="lbl">First name :</span> <?php echo htmlspecialchars($firstname); ?></td>
    <td style="width:20%"><span class="lbl">Last name :</span> <?php echo htmlspecialchars($lastname); ?></td>
    <td style="width:15%"><span class="lbl">Gender :</span> <?php echo htmlspecialchars($handover['gender'] ? substr($handover['gender'],0,1) : ''); ?></td>
    <td style="width:40%"><span class="lbl">Employee ID :</span> <?php echo htmlspecialchars($handover['employee_id'] ?? ''); ?></td>
  </tr>
  <tr>
    <td><span class="lbl">Position :</span> <?php echo htmlspecialchars($handover['position'] ?? ''); ?></td>
    <td><span class="lbl">Unit :</span> <?php echo htmlspecialchars($handover['unit'] ?? ''); ?></td>
    <td colspan="2"><span class="lbl">Department :</span> <?php echo htmlspecialchars($handover['department'] ?? ''); ?></td>
  </tr>
  <tr>
    <td colspan="2"><span class="lbl">Manager's name :</span> <?php echo htmlspecialchars($handover['manager_name'] ?? ''); ?></td>
    <td colspan="2"><span class="lbl">Location :</span></td>
  </tr>
</table>

<div class="section-lbl">Accessories detail :</div>
<table class="asset-table">
  <thead>
    <tr>
      <th>No.</th>
      <th>Description</th>
      <th>QTY</th>
      <th>Serial Number</th>
      <th>Asset Code</th>
      <?php if ($is_return): ?><th>Status</th><?php endif; ?>
      <th>Condition</th>
      <th>Remark</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $items    = $handover['items'];
  $min_rows = max(3, count($items));
  for ($i = 0; $i < $min_rows; $i++):
      $item = $items[$i] ?? null;

      $desc_html = '';
      if ($item) {
          if (!empty($item['asset_name'])) $desc_html .= '<strong>'.htmlspecialchars($item['asset_name']).'</strong><br>';
          if (!empty($item['asset_type'])) $desc_html .= htmlspecialchars($item['asset_type']).'<br>';
          if (!empty($item['description'])) {
              foreach (explode(' | ', $item['description']) as $dp) {
                  if (trim($dp)) $desc_html .= htmlspecialchars(trim($dp)).'<br>';
              }
          }
      }

      $cond_key  = $is_return ? ($item['condition_return'] ?? '') : ($item['condition_issue'] ?? '');
      $cond_html = $cond_key ? '<span class="badge b-'.strtolower($cond_key).'">'.htmlspecialchars($cond_key).'</span>' : '';
  ?>
  <tr <?php echo $item ? '' : 'class="empty-row"'; ?>>
    <td class="c-no"><?php echo $i + 1; ?></td>
    <td><?php echo $desc_html; ?></td>
    <td class="c-qty"><?php echo $item ? '1' : ''; ?></td>
    <td class="c-sn"><?php echo $item ? htmlspecialchars($item['serial_number'] ?? '') : ''; ?></td>
    <td class="c-ac"><?php echo $item ? htmlspecialchars($item['asset_number']   ?? '') : ''; ?></td>
    <?php if ($is_return): ?>
    <td class="c-rs"><?php echo $item && !empty($item['return_status']) ? htmlspecialchars($item['return_status']) : ''; ?></td>
    <?php endif; ?>
    <td class="c-cn"><?php echo $cond_html; ?></td>
    <td class="c-rem"><?php echo $item ? htmlspecialchars($item['item_remark'] ?? '') : ''; ?></td>
  </tr>
  <?php endfor; ?>
  </tbody>
</table>

<?php if (!empty($handover['remark'])): ?>
<div class="remark-box"><strong>Remark:</strong> <?php echo nl2br(htmlspecialchars($handover['remark'])); ?></div>
<?php endif; ?>

<div class="check-section">
  <div class="check-row"><span class="check-box"></span><span>I have thoroughly inspected and verified that it is in good condition and fully functional</span></div>
  <div class="check-row"><span class="check-box"></span><span>I am fully responsible for any lost or damages. Any loss or damage due to negligence will result in replacement or repair costs borne by the receiver.</span></div>
</div>

<div class="sig-row">
  <div class="sig-block">
    <div class="sig-title"><?php echo htmlspecialchars($staff_label); ?> :</div>
    <div style="height:30px"></div>
    <div class="sig-line"></div>
    <div class="sig-field"><span class="lbl">Name :</span> <?php echo htmlspecialchars($staff_name); ?></div>
    <div class="sig-field"><span class="lbl">Date :</span> <?php echo htmlspecialchars($date_val); ?></div>
  </div>
  <div class="sig-block">
    <div class="sig-title">Received By :</div>
    <div style="height:30px"></div>
    <div class="sig-line"></div>
    <div class="sig-field"><span class="lbl">Name :</span> <?php echo htmlspecialchars($fullname); ?></div>
    <div class="sig-field"><span class="lbl">Date :</span></div>
  </div>
</div>

</div>
<div class="foot-stripe"></div>

</body></html>
<?php
        return ob_get_clean();
    }
}
EOF
php -l pdf.class.php
