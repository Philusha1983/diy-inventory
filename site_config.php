<?php
/**
 * site_config.php — Shared site personalization loader
 * Include AFTER db.php (requires $pdo to be available).
 * Provides $site_name, $site_tagline, $site_mini_tagline, $site_logo_url
 * with sensible defaults so the app works even before first Settings save.
 */

if (!isset($pdo)) {
    // Safety: if for any reason db.php wasn't loaded yet, bail gracefully
    $site_name         = 'DIY Lab';
    $site_tagline      = 'Inventory & AI Orchestrator';
    $site_mini_tagline = 'Inventory System';
    $site_logo_url     = '';
    return;
}

// Only fetch if not already loaded (avoid double-query on pages like settings.php
// that already fetch all settings into $settings[])
if (!isset($site_name)) {
    if (isset($settings) && is_array($settings)) {
        // Reuse already-fetched settings array
        $site_name         = $settings['lab_name']         ?? 'DIY Lab';
        $site_tagline      = $settings['lab_tagline']      ?? 'Inventory & AI Orchestrator';
        $site_mini_tagline = $settings['lab_mini_tagline'] ?? 'Inventory System';
        $site_logo_url     = $settings['lab_logo_url']     ?? '';
    } else {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('lab_name','lab_tagline','lab_mini_tagline','lab_logo_url')");
        $sc   = [];
        foreach ($stmt->fetchAll() as $r) $sc[$r['setting_key']] = $r['setting_value'];
        $site_name         = $sc['lab_name']         ?? 'DIY Lab';
        $site_tagline      = $sc['lab_tagline']      ?? 'Inventory & AI Orchestrator';
        $site_mini_tagline = $sc['lab_mini_tagline'] ?? 'Inventory System';
        $site_logo_url     = $sc['lab_logo_url']     ?? '';
    }
}
