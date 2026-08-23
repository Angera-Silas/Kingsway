<?php
$mpesa = $paymentMpesa ?? []; 
$bank = $paymentBank ?? []; 
$charges = $otherCharges ?? [];
$notes = $importantNotes ?? []; 
$hasMpesa = !empty($mpesa['paybill']); 
$hasBank = !empty($bank['bank']);
?>
<section class="fs-lower-area">
    <table class="fs-lower-grid">
        <tr>
            <!-- OTHER CHARGES -->
            <td class="fs-charges-cell">
                <div class="fs-card">
                    <div class="fs-card-header">&#128196; OTHER CHARGES</div>
                    <div class="fs-card-body">
                        <?php if ($charges): foreach ($charges as $charge): ?>
                            <table class="fs-charge-table">
                                <tr>
                                    <td class="fs-charge-name"><?= htmlspecialchars($charge['name'] ?? '') ?>:</td>
                                    <td style="width:100%;"><div class="fs-charge-dots"></div></td>
                                    <td class="fs-charge-amount">KSh <?= number_format((float) ($charge['amount'] ?? 0)) ?></td>
                                </tr>
                            </table>
                        <?php endforeach; else: ?>
                            <div style="text-align:center; color:#777; padding-top:2mm;">No additional charges</div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- PAYMENT OPTIONS -->
            <td class="fs-payments-cell">
                <div class="fs-card fs-card--navy">
                    <div class="fs-card-header">&#128179; PAYMENT OPTIONS</div>
                    <div class="fs-card-body">
                        <table class="fs-pay-table">
                            <tr>
                                <?php if ($hasMpesa): ?>
                                <td class="fs-pay-box-cell">
                                    <div class="fs-pay-box">
                                        <div class="fs-pay-title">LIPA KARO</div>
                                        <div class="fs-pay-label">PAYBILL NO.</div>
                                        <div class="fs-pay-value"><?= htmlspecialchars($mpesa['paybill']) ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NO.</div>
                                        <div class="fs-pay-value"><?= htmlspecialchars($mpesa['account'] ?? '') ?></div>
                                    </div>
                                </td>
                                <?php endif; ?>

                                <?php if ($hasBank): ?>
                                <td class="fs-pay-box-cell">
                                    <div class="fs-pay-box">
                                        <div class="fs-pay-title fs-pay-title--bank">&#127963; BANK PAYMENT</div>
                                        <div class="fs-pay-label">BANK:</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($bank['bank']) ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NAME:</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($bank['account_name'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NO.</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($bank['account_no'] ?? '') ?></div>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

   <!-- IMPORTANT INFORMATION -->
    <?php if ($notes): ?>
    <div class="fs-important-box">
        <table class="fs-important-table">
            <tr>
                <td class="fs-important-label-cell">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="fs-important-text">
                                IMPORTANT<br>INFORMATION
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="fs-important-list-cell">
                    <ul>
                        <?php foreach ($notes as $note): ?>
                            <li><?= htmlspecialchars($note) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
</section>