<?php
/**
 * Fee Structure Header — school header + document title.
 * Reusable across ALL fee structure variants.
 *
 * Expected variables:
 *   string $schoolName, $schoolAddress, $schoolPhone, $schoolMotto, $schoolLogo
 *   string $documentTitle   — e.g. "2026 SCHOOL FEE STRUCTURE"
 *   string $documentSubtitle — e.g. "PRIMARY & JUNIOR SCHOOL" or "DAY SCHOLAR FEE STRUCTURE"
 */
$sName    = htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL');
$sAddress = htmlspecialchars($schoolAddress ?? '');
$sPhone   = htmlspecialchars($schoolPhone ?? '');
$sMotto   = htmlspecialchars($schoolMotto ?? 'In God We Soar');
$sLogo    = $schoolLogo ?? '';
$docTitle = htmlspecialchars($documentTitle ?? 'FEE STRUCTURE');
$docSub   = htmlspecialchars($documentSubtitle ?? '');
$initials = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', $schoolName ?? 'KWPS'))));
?>
<header class="fs-header">
    <div class="row align-items-center">
        <div class="col-md-2 text-center">
            <div class="fs-logo-frame">
                <?php if ($sLogo): ?>
                    <img src="<?= htmlspecialchars($sLogo) ?>" alt="<?= $sName ?> Logo">
                <?php else: ?>
                    <div style="width:120px;height:120px;border-radius:50%;background:var(--fs-green-dark);color:white;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;"><?= htmlspecialchars($initials) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-10 text-center text-md-start">
            <div class="fs-school-name"><?= $sName ?></div>
            <?php if ($sAddress): ?>
                <div class="fs-school-meta"><i class="bi bi-geo-alt-fill"></i><?= $sAddress ?></div>
            <?php endif; ?>
            <?php if ($sPhone): ?>
                <div class="fs-school-meta"><i class="bi bi-telephone-fill"></i>TEL: <?= $sPhone ?></div>
            <?php endif; ?>
            <div class="fs-motto"><?= $sMotto ?></div>
        </div>
    </div>
    <div class="fs-header-rule"></div>
</header>

<section class="fs-title-block">
    <h1><?= $docTitle ?></h1>
    <?php if ($docSub): ?>
        <div class="fs-subtitle"><?= $docSub ?></div>
    <?php endif; ?>
</section>
