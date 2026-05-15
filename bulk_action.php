<?php
/**
 * bulk_action.php — Bulk Action Handler
 * Accepts POST with: action, ids[], and action-specific fields.
 * Actions: set_category | set_status | set_location | delete | export_csv
 */
require 'db.php';
require 'image_helper.php';
session_start();

if (!isset($_SESSION['authenticated'])) {
    header('Location: index.php'); exit;
}

$action = $_POST['bulk_op'] ?? '';
$ids    = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));

if (empty($ids)) {
    header('Location: dashboard.php?bulk_error=no_selection'); exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

// ── CSV Export (no DB write needed) ────────────────────────────────────────
if ($action === 'export_csv') {
    $stmt = $pdo->prepare(
        "SELECT name, model, category, quantity, status, location, specs, purchase_price, notes
         FROM inventory WHERE id IN ($placeholders) ORDER BY category, name"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Model','Category','Quantity','Status','Location','Specs','Price','Notes'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'], $r['model'], $r['category'], $r['quantity'],
            $r['status'], $r['location'], $r['specs'], $r['purchase_price'], $r['notes'],
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// ── Category change ─────────────────────────────────────────────────────────
if ($action === 'set_category') {
    $value = trim($_POST['value'] ?? '');
    if ($value === '') { header('Location: dashboard.php?bulk_error=empty_value'); exit; }
    $stmt = $pdo->prepare("UPDATE inventory SET category = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$value], $ids));
    $count = $stmt->rowCount();
    header("Location: dashboard.php?bulk_ok=category&count=$count"); exit;
}

// ── Status change ───────────────────────────────────────────────────────────
if ($action === 'set_status') {
    $value = $_POST['value'] ?? '';
    if (!in_array($value, ['New','Used','Refurbished'], true)) {
        header('Location: dashboard.php?bulk_error=invalid_status'); exit;
    }
    $stmt = $pdo->prepare("UPDATE inventory SET status = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$value], $ids));
    $count = $stmt->rowCount();
    header("Location: dashboard.php?bulk_ok=status&count=$count"); exit;
}

// ── Location change ─────────────────────────────────────────────────────────
if ($action === 'set_location') {
    $value = trim($_POST['value'] ?? '');
    $stmt  = $pdo->prepare("UPDATE inventory SET location = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$value ?: null], $ids));
    $count = $stmt->rowCount();
    header("Location: dashboard.php?bulk_ok=location&count=$count"); exit;
}

// ── Delete ──────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    // Fetch image paths first so we can remove files
    $stmt = $pdo->prepare("SELECT image_paths FROM inventory WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $paths = json_decode($row['image_paths'] ?? '[]', true) ?: [];
        foreach ($paths as $full_path) {
            if (file_exists($full_path)) @unlink($full_path);
            $thumb = derive_thumb($full_path);
            if ($thumb !== $full_path && file_exists($thumb)) @unlink($thumb);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $count = $stmt->rowCount();
    header("Location: dashboard.php?bulk_ok=delete&count=$count"); exit;
}

header('Location: dashboard.php?bulk_error=unknown_action'); exit;
