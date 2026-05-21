<?php
/**
 * DIY Lab Inventory System - Distribution Packaging Script
 * Generates a clean production distribution ZIP of the application.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via the CLI (command line interface).\n");
}

$zipName = 'diy-inventory.zip';
$sourceDir = __DIR__;

// Remove existing zip if it exists
if (file_exists($zipName)) {
    unlink($zipName);
}

// 1. Files and directories to exclude
$excludes = [
    // Version control and CI
    '.git',
    '.github',
    '.gitignore',
    
    // Dependencies
    'node_modules',
    
    // Testing and Dev configs
    'tests',
    'package.json',
    'package-lock.json',
    'tailwind.config.js',
    'contrast_audit.js',
    'cookies.txt',
    
    // Production generated / sensitive files
    'config.php',
    'inventory.db',
    'install/install_debug.log',
    
    // The zip itself
    $zipName,
    
    // Developer temp files
    '.DS_Store',
    'thumbs.db'
];

// Helper function to check if a path matches any exclude pattern
function isExcluded($relativePath, $excludes) {
    // Normalize path separators to forward slash
    $path = str_replace('\\', '/', $relativePath);
    
    foreach ($excludes as $exclude) {
        $exclude = str_replace('\\', '/', $exclude);
        
        // Exact match or folder match (e.g. "tests" matches "tests/" and "tests/file.txt")
        if ($path === $exclude || 
            strpos($path, $exclude . '/') === 0 || 
            basename($path) === $exclude) {
            return true;
        }
    }
    return false;
}

if (!class_exists('ZipArchive')) {
    die("Error: The PHP Zip extension (ZipArchive class) is not installed. Package aborted.\n");
}

$zip = new ZipArchive();
if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Error: Could not create or open zip file: $zipName\n");
}

echo "Packaging DIY Lab Inventory System into '$zipName'...\n";

// 2. Iterate directory recursively
$filesCount = 0;
$dirIterator = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

foreach ($iterator as $fileInfo) {
    $filePath = $fileInfo->getPathname();
    
    // Get relative path
    $relative = substr($filePath, strlen($sourceDir) + 1);
    
    // Skip empty values or current dir ref
    if ($relative === false || $relative === '') {
        continue;
    }
    
    // Check if path is in excludes
    if (isExcluded($relative, $excludes)) {
        continue;
    }
    
    // Skip any files or subdirectories inside the uploads directory during traversal
    // (the empty uploads directory structure is explicitly added at the end)
    if (strpos($relative, 'uploads/') === 0) {
        continue;
    }
    
    if ($fileInfo->isDir()) {
        // Add directory to zip
        $zip->addEmptyDir($relative);
    } else {
        // Add file to zip
        $zip->addFile($filePath, $relative);
        $filesCount++;
        echo "  [+] Added: $relative\n";
    }
}

// 3. Ensure empty uploads directory structure is preserved in zip
$uploadsDir = 'uploads';
$uploadsLogoDir = 'uploads/logo';
if (!isExcluded($uploadsDir, $excludes)) {
    $zip->addEmptyDir($uploadsDir);
    echo "  [+] Preserved folder: $uploadsDir\n";
}
if (!isExcluded($uploadsLogoDir, $excludes)) {
    $zip->addEmptyDir($uploadsLogoDir);
    echo "  [+] Preserved folder: $uploadsLogoDir\n";
}

$zip->close();

if (file_exists($zipName)) {
    $size = filesize($zipName);
    $sizeFormatted = round($size / 1024 / 1024, 2);
    echo "\nSuccess! Packed $filesCount files into '$zipName' ($sizeFormatted MB).\n";
} else {
    echo "\nError: Zip file creation failed.\n";
}
