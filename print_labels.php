<?php
/**
 * print_labels.php — Print QR label sheets for inventory components.
 * Usage:
 *   ?id=42           → single item label
 *   ?ids=1,2,3,4     → bulk label sheet
 *   ?loc=Box+3       → all items at a location
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$id  = isset($_GET['id'])  ? (int)$_GET['id'] : 0;
$ids = $_GET['ids'] ?? '';
$loc = $_GET['loc']  ?? '';

$items = [];

if ($id) {
    $s = $pdo->prepare("SELECT id,name,model,category,quantity,location FROM inventory WHERE id=?");
    $s->execute([$id]);
    $items = $s->fetchAll();
} elseif ($ids) {
    $id_arr = array_filter(array_map('intval', explode(',', $ids)));
    if ($id_arr) {
        $ph = implode(',', array_fill(0, count($id_arr), '?'));
        $s  = $pdo->prepare("SELECT id,name,model,category,quantity,location FROM inventory WHERE id IN ($ph) ORDER BY location,name");
        $s->execute($id_arr);
        $items = $s->fetchAll();
    }
} elseif ($loc) {
    $s = $pdo->prepare("SELECT id,name,model,category,quantity,location FROM inventory WHERE location=? ORDER BY name");
    $s->execute([$loc]);
    $items = $s->fetchAll();
}

if (empty($items)) { echo '<p>No items found. <a href="dashboard.php">Back</a></p>'; exit; }

$label_size = $_GET['size'] ?? 'medium'; // small|medium|large
$sizes = [
    'small'  => ['w'=>'38mm','h'=>'21mm','qr'=>80,  'font'=>'7px'],
    'medium' => ['w'=>'63mm','h'=>'38mm','qr'=>110, 'font'=>'9px'],
    'large'  => ['w'=>'99mm','h'=>'57mm','qr'=>150, 'font'=>'11px'],
];
$sz = $sizes[$label_size] ?? $sizes['medium'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Print Labels — DIY Lab</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    /* ── Screen controls ─────────────────────────────────────────────────────── */
    @media screen {
      body { font-family: system-ui, sans-serif; background:#0f0f1e; color:#e2e8f0; padding:1rem; }
      .controls { position:sticky; top:0; z-index:10; background:#0f0f1e; padding:1rem 0; margin-bottom:1rem;
                  border-bottom:1px solid rgba(255,255,255,.08); display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
      .ctrl-btn { padding:.5rem 1rem; border-radius:10px; border:1px solid rgba(124,58,237,.4);
                  background:transparent; color:#c4b5fd; cursor:pointer; font-size:.85rem; font-weight:600; transition:all .15s; }
      .ctrl-btn:hover { background:rgba(124,58,237,.15); }
      .ctrl-btn.primary { background:linear-gradient(135deg,#7c3aed,#06b6d4); border-color:transparent; color:#fff; }
      .ctrl-btn.primary:hover { opacity:.9; }
      .size-label { color:#94a3b8; font-size:.8rem; }
      .label-grid { display:flex; flex-wrap:wrap; gap:8px; }
    }
    /* ── Print layout ────────────────────────────────────────────────────────── */
    @media print {
      * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      body { margin:0; padding:4mm; background:#fff; }
      .controls { display:none !important; }
      .label-grid { display:flex; flex-wrap:wrap; gap:4mm; }
    }
    /* ── Label card ──────────────────────────────────────────────────────────── */
    .label-card {
      width:<?= $sz['w'] ?>; height:<?= $sz['h'] ?>;
      border:1px solid #d1d5db; border-radius:4px;
      display:flex; align-items:center; gap:3mm;
      padding:2mm; background:#fff; color:#111;
      box-sizing:border-box; overflow:hidden; page-break-inside:avoid;
      flex-shrink:0;
    }
    .label-qr { flex-shrink:0; }
    .label-info { min-width:0; flex:1; display:flex; flex-direction:column; justify-content:center; }
    .label-name { font-size:<?= $sz['font'] ?>; font-weight:700; line-height:1.2;
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .label-model{ font-size:<?= $sz['font'] ?>; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .label-meta { font-size:<?= $sz['font'] ?>; color:#777; margin-top:1mm; }
    .label-loc  { font-size:<?= $sz['font'] ?>; color:#374151; font-weight:600; margin-top:1mm;
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .label-url  { font-size:6px; color:#9ca3af; margin-top:1.5mm;
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                  <?= $label_size === 'small' ? 'display:none;' : '' ?> }
    /* screen preview border */
    @media screen {
      .label-card { box-shadow:0 2px 12px rgba(0,0,0,.35); }
    }
  </style>
  <script src="assets/i18n.js"></script>
</head>
<body>

<div class="controls">
  <button class="ctrl-btn primary" onclick="window.print()" data-i18n-text="print_labels.print">🖨️ Print</button>
  <button class="ctrl-btn" onclick="history.back()" data-i18n-text="print_labels.back">← Back</button>
  <span class="size-label" data-i18n-text="print_labels.label_size">Label size:</span>
  <button class="ctrl-btn" onclick="setSize('small')"  <?= $label_size==='small'  ? 'style="border-color:#7c3aed"' : '' ?> data-i18n-text="print_labels.small">Small (38×21)</button>
  <button class="ctrl-btn" onclick="setSize('medium')" <?= $label_size==='medium' ? 'style="border-color:#7c3aed"' : '' ?> data-i18n-text="print_labels.medium">Medium (63×38)</button>
  <button class="ctrl-btn" onclick="setSize('large')"  <?= $label_size==='large'  ? 'style="border-color:#7c3aed"' : '' ?> data-i18n-text="print_labels.large">Large (99×57)</button>
  <span class="size-label"><?= count($items) ?> <span data-i18n-text="print_labels.labels_count_word">labels</span></span>
</div>

<div class="label-grid" id="label-grid">
  <?php foreach ($items as $item): ?>
  <div class="label-card">
    <div class="label-qr" id="qr-<?= $item['id'] ?>"></div>
    <div class="label-info">
      <div class="label-name"><?= htmlspecialchars($item['name']) ?></div>
      <?php if ($item['model']): ?>
      <div class="label-model"><?= htmlspecialchars($item['model']) ?></div>
      <?php endif; ?>
      <div class="label-meta">Qty: <?= (int)$item['quantity'] ?> · <?= htmlspecialchars($item['category']) ?></div>
      <?php if ($item['location']): ?>
      <div class="label-loc">📍 <?= htmlspecialchars($item['location']) ?></div>
      <?php endif; ?>
      <div class="label-url" id="url-<?= $item['id'] ?>"></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
const origin = window.location.origin;
// Pass full item data so QR text is built client-side (includes name, model etc.)
const items = <?= json_encode(array_map(fn($i) => [
    'id'       => $i['id'],
    'name'     => $i['name'],
    'model'    => $i['model'] ?? '',
    'category' => $i['category'],
    'quantity' => (int)$i['quantity'],
    'location' => $i['location'] ?? '',
], $items)) ?>;
const qrSize = <?= $sz['qr'] ?>;

items.forEach(item => {
  const url = `${origin}/item_details.php?id=${item.id}`;

  // Self-contained text payload — readable offline from any QR scanner.
  // URL is included at the bottom so online users can tap/visit it.
  const lines = [
    item.name,
    item.model   ? `Model: ${item.model}`       : null,
    `Category: ${item.category}`,
    `Qty: ${item.quantity}`,
    item.location ? `Location: ${item.location}` : null,
    '---',
    url,
  ].filter(Boolean).join('\n');

  new QRCode(document.getElementById('qr-' + item.id), {
    text: lines, width: qrSize, height: qrSize,
    colorDark:'#111111', colorLight:'#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });

  // Show URL as tiny text on the label (medium/large only — hidden via CSS for small)
  const urlEl = document.getElementById('url-' + item.id);
  if (urlEl) urlEl.textContent = url;
});


function setSize(sz) {
  const u = new URL(window.location.href);
  u.searchParams.set('size', sz);
  window.location.href = u.toString();
}
</script>
  <script>localizationController.init();</script>
</body>
</html>
