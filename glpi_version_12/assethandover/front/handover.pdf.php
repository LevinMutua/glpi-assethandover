<?php
// GLPI 12 boots plugin front scripts via its front controller.

Session::checkLoginUser();
if (!Session::haveRight('plugin_assethandover', READ)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Missing ID');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

$html = PluginAssethandoverPdf::generate($id);

// Inject into <head>: viewport, print header/footer removal, auto-print
$inject = '
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<style>
  /* Remove browser-added URL, date, page number from print */
  @page {
    size: A4 landscape;
    margin: 0;
    /* These are non-standard but supported in Chrome/Edge */
    @top-left   { content: ""; }
    @top-center { content: ""; }
    @top-right  { content: ""; }
    @bottom-left   { content: ""; }
    @bottom-center { content: ""; }
    @bottom-right  { content: ""; }
  }
  /* Hide the print toolbar banner that some browsers add */
  #print-hint { display:none; }
  @media print {
    #print-hint { display:none !important; }
  }
</style>
<script>
window.addEventListener("load", function() {
    setTimeout(function() { window.print(); }, 400);
});
</script>
';

$html = str_replace('</head>', $inject . '</head>', $html);

// Also add a small hint for the user before print dialog
$hint = '
<div id="print-hint" style="background:#1a3c6e;color:#fff;padding:8px 16px;font-size:12px;font-family:Arial,sans-serif;display:flex;align-items:center;gap:12px">
  <span style="font-weight:700">Print tip:</span>
  In the print dialog, set <strong>Margins = None</strong> and uncheck <strong>Headers and footers</strong> to remove the URL and page number.
  <button onclick="window.print()" style="margin-left:auto;padding:5px 14px;background:#fff;color:#1a3c6e;border:none;border-radius:4px;font-weight:700;cursor:pointer">Print</button>
  <button onclick="window.close()" style="padding:5px 14px;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:4px;cursor:pointer">Close</button>
</div>
';

$html = str_replace('<body>', '<body>' . $hint, $html);

echo $html;
exit;
