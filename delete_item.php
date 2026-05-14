<?php
/**
 * delete_item.php — Image-aware Delete (Phase 2 / 3)
 * Deletes physical images from /uploads before removing DB record.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { http_response_code(403); exit; }

if (!isset($_GET['id'])) { header('Location: dashboard.php'); exit; }

$id = (int)$_GET['id'];

// Fetch image paths before deleting the record
$stmt = $pdo->prepare("SELECT image_paths FROM inventory WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row && $row['image_paths']) {
    $paths = json_decode($row['image_paths'], true) ?: [];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

// Delete the DB record
$stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
$stmt->execute([$id]);

header('Location: dashboard.php');
exit;
