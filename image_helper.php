<?php
/**
 * image_helper.php — Centralised image processing for uploads.
 *
 * process_image($tmp_path, $upload_dir, $base_name)
 *   Reads a raw uploaded image (JPEG/PNG/WebP/GIF), resizes it to two
 *   versions and saves them to $upload_dir:
 *     full_<base_name>.jpg   — max 1200px on longest side, JPEG q85
 *     thumb_<base_name>.jpg  — max 400px  on longest side, JPEG q80
 *
 *   Returns an array ['full' => 'path/to/full_*.jpg',
 *                     'thumb'=> 'path/to/thumb_*.jpg']
 *   or false on failure.
 */
function process_image(string $tmp_path, string $upload_dir, string $base_name): array|false
{
    // Raise memory before any GD work (large images need it)
    @ini_set('memory_limit', '512M');

    // ── Read source image ────────────────────────────────────────────────────
    $info = @getimagesize($tmp_path);
    if (!$info) return false;

    // imagecreatefromstring() auto-detects format and handles CMYK, progressive
    // JPEG, and format variants that the explicit loaders sometimes reject.
    $raw = @file_get_contents($tmp_path);
    if ($raw === false || $raw === '') return false;

    $src = @imagecreatefromstring($raw);
    unset($raw); // free string buffer immediately
    if (!$src) return false;

    $orig_w = imagesx($src);
    $orig_h = imagesy($src);

    // ── Helper: resize + save as JPEG ────────────────────────────────────────
    $resize_save = function(
        \GdImage $source,
        int $orig_w, int $orig_h,
        int $max_px,
        string $dest_path,
        int $quality
    ): bool {
        // Scale down only — never enlarge
        if ($orig_w <= $max_px && $orig_h <= $max_px) {
            $new_w = $orig_w;
            $new_h = $orig_h;
        } elseif ($orig_w >= $orig_h) {
            $new_w = $max_px;
            $new_h = (int)round($orig_h * ($max_px / $orig_w));
        } else {
            $new_h = $max_px;
            $new_w = (int)round($orig_w * ($max_px / $orig_h));
        }

        $dst = imagecreatetruecolor($new_w, $new_h);

        // Preserve transparency for PNG/WebP sources
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        imagecopyresampled($dst, $source, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        $ok = imagejpeg($dst, $dest_path, $quality);
        return $ok;
    };

    // ── Ensure upload dir exists ─────────────────────────────────────────────
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $full_path  = $upload_dir . 'full_'  . $base_name . '.jpg';
    $thumb_path = $upload_dir . 'thumb_' . $base_name . '.jpg';

    $ok_full  = $resize_save($src, $orig_w, $orig_h, 1200, $full_path,  85);
    $ok_thumb = $resize_save($src, $orig_w, $orig_h,  400, $thumb_path, 80);

    if (!$ok_full || !$ok_thumb) return false;

    return ['full' => $full_path, 'thumb' => $thumb_path];
}

/**
 * derive_thumb(string $full_path): string
 *   Given a 'full_*.jpg' path, return the corresponding 'thumb_*.jpg' path.
 *   Falls back to $full_path if thumb doesn't exist (legacy images).
 */
function derive_thumb(string $full_path): string
{
    $thumb = preg_replace('#(^|/)full_#', '$1thumb_', $full_path);
    return file_exists($thumb) ? $thumb : $full_path;
}
