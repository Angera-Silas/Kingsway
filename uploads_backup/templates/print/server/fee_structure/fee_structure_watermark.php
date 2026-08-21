<?php
/**
 * Fee Structure Watermark — transparent school logo centered behind content.
 * Reusable across ALL fee structure variants.
 *
 * Expected variables:
 *   string $schoolLogo — path to school logo image
 *   string $schoolName — school name (used as alt text)
 */
$sLogo = $schoolLogo ?? '';
$sName = $schoolName ?? 'KINGSWAY PREPARATORY SCHOOL';
if (empty($sLogo)) return;
?>
<div class="fs-watermark">
    <img src="<?= htmlspecialchars($sLogo) ?>" alt="<?= htmlspecialchars($sName) ?> Logo">
</div>
