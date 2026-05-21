<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['edit'] = 1; // force UPDATE
$_POST = [
    'name' => 'Test Item',
    'quantity' => '2',
    'status' => 'Used',
];
$_FILES = [
    'images' => [
        'name' => [''],
        'type' => [''],
        'tmp_name' => [''],
        'error' => [UPLOAD_ERR_NO_FILE],
        'size' => [0],
    ]
];

ob_start();
try {
    include 'add_item.php';
    echo "SUCCESS\n";
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
