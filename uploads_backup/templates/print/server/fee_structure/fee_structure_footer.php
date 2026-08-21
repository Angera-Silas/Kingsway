<?php
/**
 * Fee Structure Footer — payment options + important notes + school footer.
 * Reusable across ALL fee structure variants.
 *
 * Expected variables:
 *   array  $otherCharges     — [ ['name'=>'Registration Fee','amount'=>1000], ... ]
 *   array  $paymentMpesa     — [ 'paybill'=>'522123', 'account'=>'20210K' ]
 *   array  $paymentBank      — [ 'bank'=>'...', 'account_name'=>'...', 'account_no'=>'...' ]
 *   array  $importantNotes   — [ 'Cash payment is not accepted.', ... ]
 *   string $schoolMotto      — for the footer
 *   string $generatedAt      — print timestamp
 */
$sMotto   = htmlspecialchars($schoolMotto ?? 'In God We Soar');
$mpesa    = $paymentMpesa ?? [];
$bank     = $paymentBank ?? [];
$charges  = $otherCharges ?? [];
$notes    = $importantNotes ?? [];
$genAt    = $generatedAt ?? date('d M Y H:i');
$hasMpesa = !empty($mpesa['paybill']);
$hasBank  = !empty($bank['bank']);
?>

<!-- Other Charges + Payment Options -->
<div class="row g-3 mt-1">

    <!-- Other Charges -->
    <div class="col-lg-4">
        <div class="fs-info-card">
            <div class="fs-info-card-header"><i class="bi bi-receipt-cutoff"></i>OTHER CHARGES</div>
            <div class="fs-info-card-body">
                <?php if ($charges): ?>
                    <?php foreach ($charges as $ch): ?>
                        <div class="fs-charge">
                            <span class="fs-charge-name"><?= htmlspecialchars($ch['name'] ?? '') ?></span>
                            <span class="fs-charge-amount">KSh <?= number_format((float)($ch['amount'] ?? 0)) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted text-center py-2">No additional charges</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Options -->
    <div class="col-lg-8">
        <div class="fs-info-card">
            <div class="fs-info-card-header"><i class="bi bi-credit-card-fill"></i>PAYMENT OPTIONS</div>
            <div class="fs-info-card-body">
                <div class="row g-3">
                    <?php if ($hasMpesa): ?>
                    <div class="col-md-5">
                        <div class="fs-pay-box">
                            <div class="fs-pay-icon"><i class="bi bi-phone-fill"></i></div>
                            <div class="fs-pay-title">LIPA KAMA M-PESA</div>
                            <div class="fs-pay-label">PAYBILL NO.</div>
                            <div class="fs-pay-value"><?= htmlspecialchars($mpesa['paybill'] ?? '') ?></div>
                            <div class="fs-pay-divider"></div>
                            <div class="fs-pay-label">ACCOUNT NO.</div>
                            <div class="fs-pay-value"><?= htmlspecialchars($mpesa['account'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasBank): ?>
                    <div class="<?= $hasMpesa ? 'col-md-7' : 'col-md-12' ?>">
                        <div class="fs-pay-box">
                            <div class="fs-pay-icon"><i class="bi bi-bank2"></i></div>
                            <div class="fs-pay-title">BANK PAYMENT</div>
                            <div class="fs-pay-label">BANK</div>
                            <div class="fs-pay-value fs-pay-value--sm"><?= htmlspecialchars($bank['bank'] ?? '') ?></div>
                            <div class="fs-pay-divider"></div>
                            <div class="fs-pay-label">ACCOUNT NAME</div>
                            <div class="fs-pay-value fs-pay-value--sm"><?= htmlspecialchars($bank['account_name'] ?? '') ?></div>
                            <div class="fs-pay-divider"></div>
                            <div class="fs-pay-label">ACCOUNT NO.</div>
                            <div class="fs-pay-value"><?= htmlspecialchars($bank['account_no'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!$hasMpesa && !$hasBank): ?>
                    <div class="col-12 text-center text-muted py-2">Payment details not configured</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Important Information -->
<?php if ($notes): ?>
<div class="fs-important">
    <div class="fs-important-header"><i class="bi bi-exclamation-triangle-fill"></i>IMPORTANT INFORMATION</div>
    <ul class="fs-important-list">
        <?php foreach ($notes as $note): ?>
            <li><?= htmlspecialchars($note) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- School Footer -->
<footer class="fs-footer">
    <div class="fs-footer-motto"><?= $sMotto ?></div>
    <div style="font-size:11px;opacity:0.7;margin-top:4px;">Printed: <?= htmlspecialchars($genAt) ?></div>
</footer>
