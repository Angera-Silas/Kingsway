<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Standard journal recipes shared by incoming and outgoing workflows. */
final class FinancialPostingCoordinator
{
    private PDO $db;
    private AccountingPostingService $journals;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->journals = new AccountingPostingService($db);
    }

    public function postIncoming(string $sourceType, int $sourceId, int $financialAccountId, string $purpose, string $amount, int $actorUserId = 0, ?string $reference = null): array
    {
        $source = $this->sourceAccount($financialAccountId);
        $receivable = ['fees' => '120001', 'transport' => '120002', 'uniforms' => '120003'][$purpose] ?? null;
        if (!$receivable) throw new RuntimeException('No incoming ledger recipe exists for purpose: ' . $purpose);
        $result = $this->journals->post($sourceType, $sourceId, 'incoming_' . $purpose, 'Incoming ' . $purpose . ($reference ? ' ' . $reference : ''), [
            ['account_code' => $source['ledger_code'], 'debit' => $amount, 'description' => 'Money received into ' . $source['account_name']],
            ['account_code' => $receivable, 'credit' => $amount, 'description' => ucfirst($purpose) . ' receivable settlement'],
        ], $actorUserId);
        if ($purpose === 'fees' && $sourceType === 'payment') {
            $this->postAllocatedExtraCharges($sourceId, $actorUserId, $reference);
        }
        $this->announceIncoming($result, $purpose);
        return $result;
    }

    /** Reclassify the charge portion of an incoming fee payment to each
     * extra charge's configured income account. Cash is debited only by the
     * original incoming-fee journal, so this entry moves the receivable credit
     * without duplicating the bank/cash debit. */
    private function postAllocatedExtraCharges(int $paymentId, int $actorUserId = 0, ?string $reference = null): void
    {
        $stmt = $this->db->prepare(
            "SELECT coa.account_code, ec.name, SUM(alloc.amount) AS amount
             FROM extra_charge_student_payment_allocations alloc
             JOIN extra_charge_student_obligations eco ON eco.id=alloc.obligation_id
             JOIN extra_charge_schedules sch ON sch.id=eco.schedule_id
             JOIN extra_charges ec ON ec.id=sch.extra_charge_id
             JOIN chart_of_accounts coa ON coa.id=ec.gl_account_id
             WHERE alloc.payment_id=? AND alloc.status='allocated'
               AND coa.status='active' AND coa.is_postable=1
             GROUP BY coa.account_code,ec.id,ec.name
             ORDER BY coa.account_code,ec.name"
        );
        $stmt->execute([$paymentId]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$allocations) return;

        $lines = [];
        $total = 0.0;
        foreach ($allocations as $allocation) {
            $amount = round((float) $allocation['amount'], 2);
            if ($amount <= 0 || (string) $allocation['account_code'] === '120001') continue;
            $total += $amount;
            $lines[] = [
                'account_code' => (string) $allocation['account_code'],
                'credit' => number_format($amount, 2, '.', ''),
                'description' => 'Extra charge income: ' . (string) $allocation['name'],
            ];
        }
        if ($total <= 0) return;
        array_unshift($lines, [
            'account_code' => '120001',
            'debit' => number_format($total, 2, '.', ''),
            'description' => 'Reclassify extra-charge portion from fees receivable',
        ]);
        $this->journals->post(
            'payment_extra_charge_allocation',
            $paymentId,
            'extra_charge_reclassification',
            'Extra-charge allocation reclassification' . ($reference ? ' ' . $reference : ''),
            $lines,
            $actorUserId
        );
    }

    /** Post a charge payment to the configured income accounts, preserving
     * the allocation split when one payment settles multiple charges. */
    public function postIncomingToChargeAccounts(string $sourceType, int $sourceId, int $financialAccountId, array $allocations, int $actorUserId = 0, ?string $reference = null): array
    {
        $source = $this->sourceAccount($financialAccountId);
        // Build the debit line only after the allocation total is known.
        // A zero/zero journal line is invalid by design in
        // AccountingPostingService and used to make admission extra-charge
        // payments fail before they could be posted.
        $lines = [];
        $total = 0.0;
        foreach ($allocations as $allocation) {
            $amount = round((float) ($allocation['amount'] ?? 0), 2);
            if ($amount <= 0) continue;
            $account = trim((string) ($allocation['account_code'] ?? '120001'));
            $lines[] = ['account_code' => $account, 'credit' => number_format($amount, 2, '.', ''), 'description' => (string) ($allocation['description'] ?? 'Extra charge income')];
            $total += $amount;
        }
        if ($total <= 0) throw new RuntimeException('No charge allocation is available for accounting.');
        array_unshift($lines, [
            'account_code' => $source['ledger_code'],
            'debit' => number_format($total, 2, '.', ''),
            'description' => 'Money received into ' . $source['account_name'],
        ]);
        $result = $this->journals->post($sourceType, $sourceId, 'incoming_extra_charge', 'Incoming extra charge' . ($reference ? ' ' . $reference : ''), $lines, $actorUserId);
        $this->announceIncoming($result, 'extra_charge');
        return $result;
    }

    /**
     * Push a PAYMENT_ALERT for a fresh verified posting. Only a genuine
     * 'posted' outcome alerts; the idempotent 'already_posted' replay of an
     * ambiguous provider callback must not re-announce money that finance
     * already saw. The publisher is failure-proof and never throws, so this
     * can never break the money path.
     */
    private function announceIncoming(array $result, string $purpose): void
    {
        if (($result['status'] ?? '') !== 'posted') {
            return;
        }
        try {
            PaymentAlertPublisher::posted((int) ($result['journal_batch_id'] ?? 0), $purpose);
        } catch (\Throwable $error) {
            // Belt-and-braces: RealtimeGatewayPublisher never throws, but a
            // future refactor must not be able to fail a posting either.
            \App\API\Includes\FileLogger::write('realtime', [
                'event' => 'gateway.payment_alert_failed',
                'exception' => get_class($error),
            ]);
        }
    }

    public function postDisbursement(string $sourceType, int $sourceId, int $financialAccountId, string $purpose, string $amount, int $actorUserId = 0): array
    {
        $source = $this->sourceAccount($financialAccountId);
        $accountCode = ['payroll' => '510001', 'suppliers' => '520001', 'statutory' => '210011', 'refunds' => '210001', 'operations' => '520001'][$purpose] ?? null;
        if (!$accountCode) throw new RuntimeException('No disbursement ledger recipe exists for purpose: ' . $purpose);
        return $this->journals->post($sourceType, $sourceId, 'outgoing_' . $purpose, 'Outgoing ' . $purpose, [
            ['account_code' => $accountCode, 'debit' => $amount, 'description' => ucfirst($purpose) . ' expense/liability'],
            ['account_code' => $source['ledger_code'], 'credit' => $amount, 'description' => 'Payment from ' . $source['account_name']],
        ], $actorUserId);
    }

    private function sourceAccount(int $id): array
    {
        // Ledger posting is a strong-consistency decision on the money path: a
        // stale read-replica projection here would mean confirmed money is
        // never journalled. Both tables are small master lookups keyed by id.
        $s = $this->db->prepare("SELECT a.id,a.account_name,c.account_code AS ledger_code FROM " . ReadReplicaService::masterRef("school_financial_accounts") . " a JOIN " . ReadReplicaService::masterRef("chart_of_accounts") . " c ON c.id=a.ledger_account_id WHERE a.id=? AND a.status='active' LIMIT 1");
        $s->execute([$id]);
        $account = $s->fetch(PDO::FETCH_ASSOC);
        if (!$account || !$account['ledger_code']) throw new RuntimeException('Financial account is not active or has no ledger mapping.');
        return $account;
    }
}
