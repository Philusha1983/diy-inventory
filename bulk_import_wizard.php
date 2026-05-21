<?php
/**
 * bulk_import_wizard.php — Group images in browser, then AI-identify each group.
 * State held in JS; each group POSTed via fetch to bulk_import_worker.php.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Image Wizard — DIY Lab</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --bg:#0a0a0f; --surface:rgba(255,255,255,.04); --border:rgba(255,255,255,.08);
            --purple:#8b5cf6; --green:#4ade80; --red:#f87171; --yellow:#fbbf24;
            --text:#e2e8f0; --muted:#64748b; }
    body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; padding-bottom:80px; }
    .top-bar { display:flex; align-items:center; gap:16px; padding:18px 28px;
               background:rgba(255,255,255,.02); border-bottom:1px solid var(--border);
               position:sticky; top:0; z-index:50; backdrop-filter:blur(12px); }
    .top-bar a { color:var(--muted); text-decoration:none; font-size:.85rem; }
    .top-bar h1 { font-size:1.1rem; font-weight:600; }
    .container { max-width:960px; margin:0 auto; padding:32px 20px; }
    /* Drop zone */
    .drop-zone { border:2px dashed rgba(139,92,246,.4); border-radius:20px; padding:48px 24px;
                 text-align:center; cursor:pointer; transition:all .25s; margin-bottom:24px; }
    .drop-zone.drag { border-color:var(--purple); background:rgba(139,92,246,.07); }
    .drop-zone .icon { font-size:2.5rem; margin-bottom:10px; }
    .drop-zone p { color:var(--muted); font-size:.875rem; }
    /* Group cards */
    .groups { display:flex; flex-direction:column; gap:16px; }
    .group-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px; }
    .group-card.processing { border-color:rgba(139,92,246,.5); }
    .group-card.done       { border-color:rgba(74,222,128,.3); }
    .group-card.failed     { border-color:rgba(248,113,113,.3); }
    .group-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
    .group-num { width:28px; height:28px; border-radius:8px; background:rgba(139,92,246,.2);
                 color:#c4b5fd; font-size:.8rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .group-name-input { flex:1; background:rgba(255,255,255,.06); border:1px solid var(--border);
                        border-radius:8px; padding:6px 12px; color:var(--text); font-size:.85rem;
                        font-family:inherit; outline:none; }
    .group-name-input:focus { border-color:rgba(139,92,246,.5); }
    .group-name-input::placeholder { color:var(--muted); }
    .btn-remove-group { background:none; border:none; color:var(--muted); cursor:pointer; font-size:1rem;
                        padding:4px 8px; border-radius:6px; transition:all .15s; }
    .btn-remove-group:hover { color:var(--red); background:rgba(248,113,113,.1); }
    /* Thumbnails */
    .thumbs { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start; }
    .thumb-wrap { position:relative; }
    .thumb-wrap img { width:72px; height:72px; object-fit:cover; border-radius:8px;
                      border:1px solid var(--border); display:block; }
    .thumb-del { position:absolute; top:-6px; right:-6px; width:18px; height:18px;
                 background:#f87171; border-radius:50%; border:none; cursor:pointer;
                 color:#fff; font-size:.65rem; display:flex; align-items:center; justify-content:center; }
    .thumb-add { width:72px; height:72px; border:2px dashed rgba(139,92,246,.35); border-radius:8px;
                 cursor:pointer; display:flex; flex-direction:column; align-items:center;
                 justify-content:center; gap:4px; color:var(--muted); font-size:.65rem;
                 transition:all .15s; }
    .thumb-add:hover { border-color:var(--purple); background:rgba(139,92,246,.08); color:#c4b5fd; }
    .thumb-add-icon { font-size:1.4rem; line-height:1; }
    /* Group status */
    .group-status { font-size:.78rem; margin-top:10px; padding:7px 12px; border-radius:8px;
                    background:rgba(255,255,255,.03); color:var(--muted); }
    .group-status.ok   { background:rgba(74,222,128,.08); color:var(--green); }
    .group-status.err  { background:rgba(248,113,113,.08); color:var(--red); }
    .group-status.proc { background:rgba(139,92,246,.08); color:#c4b5fd; }
    /* Buttons */
    .btn { border:none; border-radius:12px; cursor:pointer; font-family:inherit;
           font-size:.9rem; font-weight:600; padding:11px 26px; transition:all .2s; }
    .btn-primary   { background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
    .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(124,58,237,.35); }
    .btn-secondary { background:var(--surface); border:1px solid var(--border); color:var(--text); }
    .btn-secondary:hover { border-color:rgba(139,92,246,.4); }
    .btn:disabled { opacity:.45; cursor:not-allowed; transform:none !important; }
    /* Bottom action bar */
    .action-bar { position:fixed; bottom:0; left:0; right:0; background:rgba(10,10,15,.95);
                  border-top:1px solid var(--border); backdrop-filter:blur(16px);
                  padding:14px 24px; display:flex; align-items:center; gap:16px; z-index:40; }
    .action-bar .summary { font-size:.82rem; color:var(--muted); }
    /* Progress */
    .prog-wrap { background:rgba(255,255,255,.06); border-radius:99px; height:6px; flex:1; overflow:hidden; }
    .prog-fill  { height:100%; background:linear-gradient(90deg,#7c3aed,#a78bfa); width:0; transition:width .4s; }
    /* Hidden file input */
    #file-input { display:none; }
  </style>
  <script src="assets/i18n.js"></script>
</head>
<body>
<div class="top-bar">
  <a href="bulk_import.php" data-i18n-text="bulk_import_wizard.import_hub">← Import Hub</a>
  <h1 data-i18n-text="bulk_import_wizard.image_group_wizard">🧙 Image Group Wizard</h1>
</div>
<div class="container">

  <!-- Drop zone -->
  <div class="drop-zone" id="main-drop" onclick="triggerAdd()">
    <div class="icon">🖼️</div>
    <p style="font-weight:600;font-size:1rem;color:var(--text);margin-bottom:6px;" data-i18n-text="bulk_import_wizard.drop_photos_here_or_click_to_s">Drop photos here or click to select</p>
    <p data-i18n-text="bulk_import_wizard.each_batch_you_drop_becomes_a_">Each batch you drop becomes a new component group. Drop multiple batches for multiple components.</p>
  </div>

  <input type="file" id="file-input" multiple accept="image/*">

  <!-- Groups list -->
  <div class="groups" id="groups-list"></div>

  <!-- Add group manually -->
  <div style="margin-top:16px;" id="add-group-btn-wrap">
    <button class="btn btn-secondary" onclick="addGroup([], 'Component ' + (groups.length+1))" data-i18n-text="bulk_import_wizard.add_another_component_group">
      + Add another component group
    </button>
  </div>

</div>

<!-- Fixed bottom action bar -->
<div class="action-bar" id="action-bar" style="display:none;">
  <div class="summary" id="bar-summary"></div>
  <div class="prog-wrap"><div class="prog-fill" id="prog-fill"></div></div>
  <button class="btn btn-primary" id="btn-run" onclick="runImport()" data-i18n-text="bulk_import_wizard.import_all_via_ai">🤖 Import All via AI</button>
  <button class="btn btn-secondary" onclick="clearAll()" data-i18n-text="bulk_import_wizard.clear">✕ Clear</button>
</div>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
let groups = []; // [{id, name, files: [{dataUrl, file}]}]
let nextId = 1;
let importing = false;

// ── Drop zone ─────────────────────────────────────────────────────────────────
const mainDrop = document.getElementById('main-drop');
mainDrop.addEventListener('dragover', e => { e.preventDefault(); mainDrop.classList.add('drag'); });
mainDrop.addEventListener('dragleave', () => mainDrop.classList.remove('drag'));
mainDrop.addEventListener('drop', e => {
  e.preventDefault(); mainDrop.classList.remove('drag');
  const files = [...e.dataTransfer.files].filter(f => f.type.startsWith('image/'));
  if (files.length) addGroup(files);
});

// File input (for manual add to existing group or from click)
let pendingGroupId = null;
document.getElementById('file-input').addEventListener('change', function() {
  const files = [...this.files].filter(f => f.type.startsWith('image/'));
  if (!files.length) return;
  if (pendingGroupId !== null) {
    addImagesToGroup(pendingGroupId, files);
  } else {
    addGroup(files);
  }
  this.value = '';
  pendingGroupId = null;
});

function triggerAdd(groupId = null) {
  pendingGroupId = groupId;
  document.getElementById('file-input').click();
}

// ── Group management ──────────────────────────────────────────────────────────
function addGroup(files, name = '') {
  const id = nextId++;
  const groupImages = [];
  const group = { id, name: name || ('Component ' + id), images: groupImages, status: 'pending', itemId: null };
  groups.push(group);

  const card = buildGroupCard(group);
  document.getElementById('groups-list').appendChild(card);

  // Load images
  files.forEach(f => readImageFile(f, id));

  updateActionBar();
  card.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function addImagesToGroup(groupId, files) {
  files.forEach(f => readImageFile(f, groupId));
}

function readImageFile(file, groupId) {
  const reader = new FileReader();
  reader.onload = e => {
    const group = groups.find(g => g.id === groupId);
    if (!group) return;
    group.images.push({ dataUrl: e.target.result, name: file.name });
    renderGroupImages(groupId);
    updateActionBar();
  };
  reader.readAsDataURL(file);
}

function removeGroup(id) {
  groups = groups.filter(g => g.id !== id);
  document.getElementById('group-' + id)?.remove();
  updateActionBar();
}

function removeImage(groupId, imgIdx) {
  const group = groups.find(g => g.id === groupId);
  if (!group) return;
  group.images.splice(imgIdx, 1);
  renderGroupImages(groupId);
  updateActionBar();
}

// ── Card rendering ────────────────────────────────────────────────────────────
function buildGroupCard(group) {
  const card = document.createElement('div');
  card.className = 'group-card';
  card.id = 'group-' + group.id;
  card.innerHTML = `
    <div class="group-header">
      <div class="group-num">#${group.id}</div>
      <div style="flex:1;min-width:0;">
        <input class="group-name-input" type="text" placeholder="Name (optional — AI will identify from photos)"
               value="${escHtml(group.name.startsWith('Component') ? '' : group.name)}"
               oninput="updateGroupName(${group.id}, this.value)">
        <div style="font-size:.65rem;color:var(--muted);margin-top:3px;padding-left:2px;">
          Leave blank to let AI identify · Add a hint to improve accuracy
        </div>
      </div>
      <button class="btn-remove-group" onclick="removeGroup(${group.id})" title="Remove group">✕</button>
    </div>
    <div class="thumbs" id="thumbs-${group.id}"></div>
    <div class="group-status" id="status-${group.id}">⏳ Waiting — drag images above or click + to add photos</div>
  `;
  return card;
}

function renderGroupImages(groupId) {
  const group = groups.find(g => g.id === groupId);
  if (!group) return;
  const wrap = document.getElementById('thumbs-' + groupId);
  if (!wrap) return;
  wrap.innerHTML = '';

  group.images.forEach((img, idx) => {
    const tw = document.createElement('div');
    tw.className = 'thumb-wrap';
    tw.innerHTML = `
      <img src="${img.dataUrl}" alt="${escHtml(img.name)}">
      <button class="thumb-del" onclick="removeImage(${groupId}, ${idx})" title="Remove image">✕</button>
    `;
    wrap.appendChild(tw);
  });

  // "Add more" button
  const addBtn = document.createElement('div');
  addBtn.className = 'thumb-add';
  addBtn.innerHTML = '<div class="thumb-add-icon">+</div><div>Add</div>';
  addBtn.onclick = () => { pendingGroupId = groupId; document.getElementById('file-input').click(); };
  wrap.appendChild(addBtn);

  // Update status
  const statusEl = document.getElementById('status-' + groupId);
  if (statusEl && group.status === 'pending') {
    statusEl.textContent = group.images.length
      ? `📸 ${group.images.length} photo${group.images.length !== 1 ? 's' : ''} — ready to import`
      : '⏳ No photos yet — add at least one image';
    statusEl.className = 'group-status';
  }
}

function updateGroupName(id, name) {
  const g = groups.find(g => g.id === id);
  if (g) g.name = name;
}

function updateActionBar() {
  const bar = document.getElementById('action-bar');
  const total = groups.length;
  const withImages = groups.filter(g => g.images.length > 0).length;

  if (total === 0) { bar.style.display = 'none'; return; }
  bar.style.display = 'flex';

  const done    = groups.filter(g => g.status === 'done').length;
  const failed  = groups.filter(g => g.status === 'failed').length;
  document.getElementById('bar-summary').textContent =
    `${total} group${total !== 1 ? 's' : ''} · ${withImages} with images · ${done} imported · ${failed} failed`;
  document.getElementById('prog-fill').style.width =
    total > 0 ? Math.round(((done + failed) / total) * 100) + '%' : '0%';
  document.getElementById('btn-run').disabled = importing || withImages === 0;
}

function clearAll() {
  if (importing) return;
  if (!confirm('Clear all groups?')) return;
  groups = [];
  document.getElementById('groups-list').innerHTML = '';
  updateActionBar();
}

// ── AI Import ─────────────────────────────────────────────────────────────────
async function runImport() {
  if (importing) return;
  const toImport = groups.filter(g => g.images.length > 0 && g.status === 'pending');
  if (!toImport.length) return;

  importing = true;
  document.getElementById('btn-run').disabled = true;

  for (const group of toImport) {
    setGroupStatus(group.id, 'proc', '🤖 Sending to AI…');

    try {
      // Build FormData with images + optional name hint
      const fd = new FormData();
      fd.append('action', 'wizard_identify');
      fd.append('name_hint', group.name || '');

      for (let i = 0; i < group.images.length; i++) {
        // Convert dataUrl to Blob
        const res  = await fetch(group.images[i].dataUrl);
        const blob = await res.blob();
        fd.append('images[]', blob, group.images[i].name);
      }

      const resp = await fetch('bulk_import_wizard_worker.php', { method:'POST', body: fd });
      const data = await resp.json();

      if (data.status === 'ok' || data.status === 'ai_fail') {
        group.status = 'done';
        group.itemId = data.item_id;
        const card = document.getElementById('group-' + group.id);
        card.classList.add('done');
        const viewLink = data.item_id
          ? ` <a href="item_details.php?id=${data.item_id}" target="_blank" style="color:#c4b5fd;text-decoration:none;font-weight:600;">View →</a>`
          : '';
        setGroupStatus(group.id, 'ok', `✅ ${data.name || group.name}${data.category ? ' · ' + data.category : ''}${viewLink}`);
      } else {
        group.status = 'failed';
        document.getElementById('group-' + group.id).classList.add('failed');
        setGroupStatus(group.id, 'err', '❌ ' + (data.message || 'Import failed'));
      }
    } catch (e) {
      group.status = 'failed';
      document.getElementById('group-' + group.id).classList.add('failed');
      setGroupStatus(group.id, 'err', '❌ Network error: ' + e.message);
    }

    updateActionBar();
    await sleep(3000); // Rate limit buffer between groups
  }

  importing = false;
  document.getElementById('btn-run').disabled = false;
  updateActionBar();
}

function setGroupStatus(id, cls, html) {
  const el = document.getElementById('status-' + id);
  if (!el) return;
  el.className = 'group-status' + (cls ? ' ' + cls : '');
  el.innerHTML = html;
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const sleep = ms => new Promise(r => setTimeout(r, ms));
</script>
  <script>localizationController.init();</script>
</body>
</html>
