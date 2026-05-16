<?php
/**
 * bulk_import.php — Bulk import from "bulk import/" folder.
 * Scans subfolders, passes the list to JS, then JS drives sequential
 * AJAX calls to bulk_import_worker.php (one per folder).
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) {
    header('Location: index.php'); exit;
}

// ── Scan bulk import folder ───────────────────────────────────────────────────
$import_base = __DIR__ . '/bulk import';
$folders     = [];

if (is_dir($import_base)) {
    foreach (scandir($import_base) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (!is_dir($import_base . '/' . $entry)) continue;

        // Count images
        $imgs = glob($import_base . '/' . $entry . '/image_*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);

        // Parse description.txt for display
        $desc_file = $import_base . '/' . $entry . '/description.txt';
        $name      = $entry;
        if (file_exists($desc_file)) {
            $desc = file_get_contents($desc_file);
            if (preg_match('/^Product Name\s*:\s*(.+)/mi', $desc, $m)) {
                $name = trim($m[1]);
            }
        }

        $folders[] = [
            'folder' => $entry,
            'name'   => $name,
            'images' => count($imgs ?? []),
        ];
    }
}

$total = count($folders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bulk Import — DIY Lab</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0a0f; --surface: rgba(255,255,255,.04);
      --border: rgba(255,255,255,.08); --purple: #8b5cf6;
      --purple-light: #a78bfa; --green: #4ade80; --red: #f87171;
      --yellow: #fbbf24; --text: #e2e8f0; --muted: #64748b;
    }
    body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif;
           min-height: 100vh; padding: 0 0 60px; }

    /* ── Header ── */
    .top-bar { display: flex; align-items: center; gap: 16px; padding: 20px 28px;
               background: rgba(255,255,255,.02); border-bottom: 1px solid var(--border);
               position: sticky; top: 0; z-index: 50; backdrop-filter: blur(12px); }
    .top-bar a { color: var(--muted); text-decoration: none; font-size: .85rem;
                 display: flex; align-items: center; gap: 6px; transition: color .2s; }
    .top-bar a:hover { color: var(--text); }
    .top-bar h1 { font-size: 1.1rem; font-weight: 600; }
    .badge { background: var(--purple); color: #fff; border-radius: 99px;
             padding: 2px 10px; font-size: .75rem; font-weight: 600; }

    .container { max-width: 960px; margin: 0 auto; padding: 32px 24px; }

    /* ── Stats row ── */
    .stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 28px; }
    .stat { background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            padding: 18px; text-align: center; }
    .stat-num { font-size: 2rem; font-weight: 700; line-height: 1; }
    .stat-label { font-size: .75rem; color: var(--muted); margin-top: 4px; }
    .stat.done .stat-num   { color: var(--green); }
    .stat.failed .stat-num { color: var(--red); }
    .stat.skip .stat-num   { color: var(--yellow); }

    /* ── Progress bar ── */
    .progress-wrap { background: rgba(255,255,255,.06); border-radius: 99px;
                     height: 10px; margin-bottom: 24px; overflow: hidden; }
    .progress-bar  { height: 100%; border-radius: 99px; width: 0%;
                     background: linear-gradient(90deg, #7c3aed, #a78bfa);
                     transition: width .4s ease; }

    /* ── Controls ── */
    .controls { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
    .btn { border: none; border-radius: 12px; cursor: pointer; font-family: inherit;
           font-size: .9rem; font-weight: 600; padding: 12px 28px; transition: all .2s; }
    .btn-start  { background: linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
    .btn-start:hover  { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(124,58,237,.4); }
    .btn-pause  { background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.25);
                  color: var(--yellow); }
    .btn-pause:hover  { background: rgba(251,191,36,.2); }
    .btn:disabled { opacity: .45; cursor: not-allowed; transform: none !important; }
    .status-text { font-size: .85rem; color: var(--muted); }

    /* ── Rate limit info ── */
    .rate-info { font-size: .8rem; color: var(--muted); display: flex; align-items: center; gap: 8px; }
    .rate-info label { white-space: nowrap; }
    .rate-info input[type=range] { width: 120px; accent-color: var(--purple); }
    .rate-val { color: var(--purple-light); font-weight: 600; min-width: 30px; }

    /* ── Item list ── */
    .item-list { display: flex; flex-direction: column; gap: 8px; }
    .item-card {
      display: grid;
      grid-template-columns: 32px 1fr auto;
      align-items: center;
      gap: 12px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 16px;
      transition: border-color .2s;
    }
    .item-card.active  { border-color: var(--purple); background: rgba(139,92,246,.06); }
    .item-card.success { border-color: rgba(74,222,128,.25); }
    .item-card.failed  { border-color: rgba(248,113,113,.25); }
    .item-card.skipped { border-color: rgba(251,191,36,.2); }
    .item-card.rate-limited { border-color: rgba(251,191,36,.4); background: rgba(251,191,36,.05); }

    .item-icon { font-size: 1.2rem; text-align: center; line-height: 1; }
    .item-body {}
    .item-name { font-size: .85rem; font-weight: 500; line-height: 1.4;
                 white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 620px;}
    .item-meta { font-size: .72rem; color: var(--muted); margin-top: 3px;
                 font-family: 'JetBrains Mono', monospace; }
    .item-link { font-size: .78rem; color: var(--purple-light); text-decoration: none;
                 padding: 4px 10px; border: 1px solid rgba(139,92,246,.3); border-radius: 8px;
                 white-space: nowrap; transition: all .2s; }
    .item-link:hover { background: rgba(139,92,246,.15); }

    /* ── Summary banner ── */
    .summary { background: linear-gradient(135deg,rgba(74,222,128,.08),rgba(139,92,246,.08));
               border: 1px solid rgba(74,222,128,.2); border-radius: 20px;
               padding: 28px 32px; margin-bottom: 28px; display: none; }
    .summary h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 8px; }
    .summary p  { color: var(--muted); font-size: .9rem; }
    .summary a.cta { display: inline-block; margin-top: 16px;
                     background: linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff;
                     padding: 12px 28px; border-radius: 12px; text-decoration: none;
                     font-weight: 600; transition: transform .2s; }
    .summary a.cta:hover { transform: translateY(-1px); }
  </style>
</head>
<body>

<!-- ── Top bar ──────────────────────────────────────────────────────────────── -->
<div class="top-bar">
  <a href="dashboard.php">← Dashboard</a>
  <h1>📦 Bulk Import</h1>
  <span class="badge"><?= $total ?> items</span>
</div>

<div class="container">

  <!-- ── Stats ── -->
  <div class="stats">
    <div class="stat">
      <div class="stat-num" id="s-total"><?= $total ?></div>
      <div class="stat-label">Total</div>
    </div>
    <div class="stat done">
      <div class="stat-num" id="s-done">0</div>
      <div class="stat-label">Imported</div>
    </div>
    <div class="stat skip">
      <div class="stat-num" id="s-skip">0</div>
      <div class="stat-label">Skipped</div>
    </div>
    <div class="stat failed">
      <div class="stat-num" id="s-fail">0</div>
      <div class="stat-label">Failed</div>
    </div>
  </div>

  <!-- ── Progress bar ── -->
  <div class="progress-wrap">
    <div class="progress-bar" id="progress-bar"></div>
  </div>

  <!-- ── Controls ── -->
  <div class="controls">
    <button class="btn btn-start" id="btn-start" onclick="startImport()">▶ Start Import</button>
    <button class="btn btn-pause" id="btn-pause" onclick="pauseImport()" style="display:none">⏸ Pause</button>
    <div class="status-text" id="status-text">Ready — <?= $total ?> items queued</div>
    <div style="flex:1"></div>
    <div class="rate-info">
      <label>Delay between calls:</label>
      <input type="range" id="delay-slider" min="1" max="10" value="4" oninput="document.getElementById('delay-val').textContent=this.value+'s'">
      <span class="rate-val" id="delay-val">4s</span>
      <span style="color:var(--muted);font-size:.72rem">(≤15 RPM free tier)</span>
    </div>
  </div>

  <!-- ── Summary (shown after completion) ── -->
  <div class="summary" id="summary">
    <h2>🎉 Import complete!</h2>
    <p id="summary-text"></p>
    <a href="dashboard.php" class="cta">View Inventory →</a>
  </div>

  <!-- ── Item list ── -->
  <div class="item-list" id="item-list">
    <?php foreach ($folders as $i => $f): ?>
    <div class="item-card" id="card-<?= $i ?>">
      <div class="item-icon" id="icon-<?= $i ?>">⏳</div>
      <div class="item-body">
        <div class="item-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
        <div class="item-meta" id="meta-<?= $i ?>">📁 <?= $f['images'] ?> image<?= $f['images']!==1?'s':'' ?> · pending</div>
      </div>
      <div id="link-<?= $i ?>"></div>
    </div>
    <?php endforeach; ?>
  </div>

</div><!-- /container -->

<script>
// ── Data passed from PHP ───────────────────────────────────────────────────────
const FOLDERS = <?= json_encode(array_values($folders)) ?>;
const TOTAL   = FOLDERS.length;

let current  = 0;
let running  = false;
let paused   = false;
let cntDone  = 0, cntSkip = 0, cntFail = 0;

// ── Controls ──────────────────────────────────────────────────────────────────
function startImport() {
  document.getElementById('btn-start').style.display = 'none';
  document.getElementById('btn-pause').style.display = '';
  running = true; paused = false;
  runNext();
}

function pauseImport() {
  paused = !paused;
  const btn = document.getElementById('btn-pause');
  if (paused) {
    btn.textContent = '▶ Resume';
    btn.style.background = 'rgba(139,92,246,.15)';
    btn.style.color = '#a78bfa';
    setStatus('⏸ Paused after current item…');
  } else {
    btn.textContent = '⏸ Pause';
    btn.style.background = '';
    btn.style.color = '';
    setStatus('▶ Resuming…');
    runNext();
  }
}

function setStatus(msg) {
  document.getElementById('status-text').textContent = msg;
}

function updateStats() {
  document.getElementById('s-done').textContent = cntDone;
  document.getElementById('s-skip').textContent = cntSkip;
  document.getElementById('s-fail').textContent = cntFail;
  const pct = Math.round((current / TOTAL) * 100);
  document.getElementById('progress-bar').style.width = pct + '%';
}

// ── Main loop ─────────────────────────────────────────────────────────────────
async function runNext() {
  if (!running || paused) return;
  if (current >= TOTAL) { finishImport(); return; }

  const item = FOLDERS[current];
  const idx  = current;
  current++;

  setCardActive(idx);
  setStatus(`Processing ${current}/${TOTAL}: ${item.name.substring(0,60)}…`);

  try {
    const res  = await fetch('bulk_import_worker.php', {
      method:  'POST',
      headers: {'Content-Type': 'application/json'},
      body:    JSON.stringify({ folder: item.folder }),
    });
    const data = await res.json();
    handleResult(idx, item, data);
  } catch(e) {
    handleResult(idx, item, { status: 'error', message: e.message });
  }

  updateStats();

  // Rate-limit delay
  const delay = parseInt(document.getElementById('delay-slider').value, 10) * 1000;
  await sleep(delay);
  runNext();
}

function handleResult(idx, item, data) {
  const card = document.getElementById('card-' + idx);
  const meta = document.getElementById('meta-' + idx);
  const icon = document.getElementById('icon-' + idx);
  const link = document.getElementById('link-' + idx);

  card.classList.remove('active');

  if (data.status === 'ok' || data.status === 'ai_fail') {
    cntDone++;
    card.classList.add('success');
    icon.textContent = data.status === 'ok' ? '✅' : '⚠️';
    const catTag = data.category ? ` · ${data.category}` : '';
    const imgTag = data.images   ? ` · ${data.images} img` : '';
    const warn   = data.message  ? ` · ⚠ ${data.message.substring(0,60)}` : '';
    meta.innerHTML = `<span style="color:var(--green)">${data.name || item.name}</span>${catTag}${imgTag}${warn}`;
    if (data.item_id) {
      link.innerHTML = `<a href="item_details.php?id=${data.item_id}" class="item-link" target="_blank">View →</a>`;
    }
  } else if (data.status === 'skipped') {
    cntSkip++;
    card.classList.add('skipped');
    icon.textContent = '⏭';
    meta.innerHTML = `<span style="color:var(--yellow)">${data.message || 'Already in inventory'}</span>`;
    if (data.item_id) {
      link.innerHTML = `<a href="item_details.php?id=${data.item_id}" class="item-link" target="_blank">View →</a>`;
    }
  } else if (data.status === 'rate_limit') {
    // Back off — re-queue current item and pause for 30s
    cntFail++;
    card.classList.add('rate-limited');
    icon.textContent = '⏱';
    meta.innerHTML = `<span style="color:var(--yellow)">Rate limited — will retry after cooldown</span>`;
    // Re-queue by decrementing pointer
    current--;
    cntFail--;
    paused = true;
    setStatus('⏱ Rate limit hit — pausing 30s then auto-resuming…');
    setTimeout(() => {
      if (running && paused) {
        paused = false;
        document.getElementById('btn-pause').textContent = '⏸ Pause';
        runNext();
      }
    }, 30000);
  } else {
    cntFail++;
    card.classList.add('failed');
    icon.textContent = '❌';
    meta.innerHTML = `<span style="color:var(--red)">${data.message || 'Unknown error'}</span>`;
  }
}

function setCardActive(idx) {
  // Scroll into view
  const card = document.getElementById('card-' + idx);
  card.classList.add('active');
  document.getElementById('icon-' + idx).textContent = '🔄';
  document.getElementById('meta-' + idx).textContent = '🤖 Asking AI…';
  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function finishImport() {
  running = false;
  document.getElementById('btn-pause').style.display = 'none';
  document.getElementById('btn-start').style.display = '';
  document.getElementById('btn-start').textContent   = '🔄 Re-run (skips existing)';
  document.getElementById('btn-start').onclick       = () => { current=0; cntDone=cntSkip=cntFail=0; updateStats(); startImport(); };
  setStatus(`✅ Done — ${cntDone} imported, ${cntSkip} skipped, ${cntFail} failed`);

  const summary = document.getElementById('summary');
  document.getElementById('summary-text').textContent =
    `${cntDone} items imported (${cntSkip} already existed, ${cntFail} failed). ` +
    `Head to the dashboard to review and enrich them.`;
  summary.style.display = 'block';
  summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
  updateStats();
}

const sleep = ms => new Promise(r => setTimeout(r, ms));
</script>
</body>
</html>
