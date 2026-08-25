<?php
/**
 * Fee Structure Watermark — transparent school logo centered behind content.
 * Reusable across ALL fee structure variants.
 *
 * Expected variables:
 *   string $schoolLogo — path to school logo image
 *   string $schoolName — school name (used as alt text)
 */
$sLogo = trim((string) ($schoolLogo ?? ''));
$sName = $schoolName ?? 'KINGSWAY PREPARATORY SCHOOL';
$candidates = [];

// Resolve local upload paths to a data URI. Dompdf cannot reliably resolve
// relative application URLs when this standalone document is rendered.
if ($sLogo !== '' && !preg_match('/^(data:|https?:\\/\\/)/i', $sLogo)) {
    if (is_file($sLogo)) {
        $candidates[] = $sLogo;
    }
    $candidates[] = dirname(__DIR__, 4) . '/' . ltrim($sLogo, '/\\');
    if (defined('UPLOAD_PATH')) {
        $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/' . ltrim($sLogo, '/\\');
    }
    $sLogo = '';
}
if ($sLogo === '') {
    if (defined('UPLOAD_PATH')) {
        $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/school_assets/official_school_logo.png';
    }
    $candidates[] = dirname(__DIR__, 4) . '/uploads/school_assets/official_school_logo.png';
}
foreach (array_unique($candidates) as $path) {
    if (is_file($path) && is_readable($path)) {
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
        $bytes = file_get_contents($path);
        if ($bytes !== false) {
            $sLogo = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            break;
        }
    }
}
if (empty($sLogo)) return;
?>
<div class="fs-watermark">
    <img src="<?= htmlspecialchars($sLogo) ?>" alt="<?= htmlspecialchars($sName) ?> Logo">
</div>
