<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\DataScopeService;
use App\API\Services\FinancialPostingCoordinator;
use App\API\Services\PayrollChildFeeTransferService;
use App\API\Services\ReadReplicaService;
use App\Database\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Authoritative payroll payout-plan and payout-ledger service.
 *
 * A payout plan is a set of immutable-after-release legs. Each leg has its own
 * method, source financial account, amount, reference and status. Provider
 * transactions are linked to a leg so callbacks can settle only that leg and
 * the employee payslip is marked paid only after every active leg is complete.
 */
final class PayrollPayoutService
{
    private PDO $db;
    private FinancialAccountService $accounts;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
        $this->accounts = new FinancialAccountService($this->db);
    }

    public static function normaliseMethod($method): string
    {
        $method = strtolower(trim((string) $method));
        $method = str_replace(['-', ' '], '_', $method);
        if (in_array($method, ['mpesa', 'm_pesa', 'mobile_money', 'mpesa_b2c', 'm_pesa_b2c', 'safaricom_b2c'], true)) {
            return 'mobile_money';
        }
        if (in_array($method, ['bank', 'bank_transfer', 'kcb_bank', 'kcb_buni_transfer', 'buni_transfer'], true)) {
            return 'bank';
        }
        if (in_array($method, ['cash', 'check', 'cheque'], true)) {
            return 'cash';
        }
        throw new InvalidArgumentException('Unsupported payroll payout method: ' . $method);
    }

    public static function methodLabel(string $method): string
    {
        return [
            'bank' => 'Bank transfer',
            'cash' => 'Cash',
            'mobile_money' => 'M-Pesa',
            'mpesa_b2c' => 'M-Pesa',
            'safaricom_b2c' => 'M-Pesa',
            'kcb_bank' => 'Bank transfer',
            'kcb_buni_transfer' => 'Bank transfer',
            'bank_transfer' => 'Bank transfer',
            'buni_transfer' => 'Bank transfer',
        ][$method] ?? ucwords(str_replace('_', ' ', $method));
    }

    /**
     * Save/replace the payout plan for one or more payslips in a payroll run.
     *
     * Planning is a scoped configuration action. It deliberately does not
     * require account-level disbursement permission; that permission is checked
     * at the live release boundary immediately before money is sent.
     *
     * @return array{payroll_id:int,month:int,year:int,allocations:array<int,array<string,mixed>>}
     */
    public function savePlans(int $payrollReference, array $allocations, int $actorUserId): array
    {
        $period = $this->resolvePayrollReference($payrollReference);
        if ((string) $period['status'] !== 'approved') {
            throw new RuntimeException('Payout allocations can be saved only after the payroll run is approved.');
        }
        if (!$allocations) {
            throw new RuntimeException('At least one payout allocation is required.');
        }

        $normalisedAllocations = $this->normaliseAllocationList($allocations);
        $this->db->beginTransaction();
        try {
            $saved = [];
            foreach ($normalisedAllocations as $allocation) {
                $saved[] = $this->savePlanForPayslip($period, $allocation, $actorUserId);
            }
            $this->db->commit();
            return [
                'payroll_id' => (int) $period['run_id'],
                'month' => (int) $period['month'],
                'year' => (int) $period['year'],
                'allocations' => $saved,
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function plansForPayroll(int $payrollReference): array
    {
        $period = $this->resolvePayrollReference($payrollReference);
        [$scopeSql, $scopeParams] = DataScopeService::predicateFor('payroll_payout_legs', 'pl');
        $stmt = $this->db->prepare(
            "SELECT pl.* FROM payroll_payout_legs pl
             WHERE pl.payroll_id = ? AND $scopeSql
             ORDER BY pl.staff_id, pl.sequence_no, pl.id"
        );
        $stmt->execute(array_merge([(int) $period['run_id']], $scopeParams));
        return $this->decorateLegs($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * User-facing payout plan read. The current workspace boundary is applied.
     *
     * @return array<int,array<string,mixed>>
     */
    public function plansForPayslip(int $payslipId): array
    {
        [$scopeSql, $scopeParams] = DataScopeService::predicateFor('payroll_payout_legs', 'pl');
        $stmt = $this->db->prepare(
            "SELECT pl.* FROM payroll_payout_legs pl
             WHERE pl.payslip_id = ? AND $scopeSql
             ORDER BY pl.sequence_no, pl.id"
        );
        $stmt->execute(array_merge([$payslipId], $scopeParams));
        return $this->decorateLegs($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Exact-leg lookup used by callbacks. It is intentionally unscoped because
     * provider callbacks do not carry a browser workspace context.
     */
    public function legById(int $legId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payroll_payout_legs WHERE id = ? LIMIT 1");
        $stmt->execute([$legId]);
        $leg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$leg) return null;
        $decorated = $this->decorateLegs([$leg]);
        return $decorated[0] ?? null;
    }

    /**
     * Browser-facing leg lookup. Provider callbacks use the unscoped lookup
     * above because they do not carry a user's workspace context.
     */
    private function scopedLegById(int $legId): ?array
    {
        [$scopeSql, $scopeParams] = DataScopeService::predicateFor('payroll_payout_legs', 'pl');
        $stmt = $this->db->prepare(
            "SELECT pl.* FROM payroll_payout_legs pl
             WHERE pl.id = ? AND $scopeSql LIMIT 1"
        );
        $stmt->execute(array_merge([$legId], $scopeParams));
        $leg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$leg) return null;
        $decorated = $this->decorateLegs([$leg]);
        return $decorated[0] ?? null;
    }

    /**
     * Create a single plan leg for a legacy payslip that has no mixed plan yet.
     * This keeps old one-account payrolls on the same auditable callback path.
     *
     * @param array<string,mixed> $payment
     * @return array<int,array<string,mixed>>
     */
    public function ensureLegacyLeg(array $payment, int $actorUserId, string $reference = ''): array
    {
        $payslipId = (int) ($payment['id'] ?? $payment['payslip_id'] ?? 0);
        if ($payslipId <= 0) return [];

        $existing = $this->allLegsForPayslip($payslipId);
        foreach ($existing as $leg) {
            if ((string) ($leg['status'] ?? '') !== 'cancelled') {
                return $this->decorateLegs([$leg]);
            }
        }

        $method = self::normaliseMethod($payment['payment_method'] ?? 'bank');
        $sourceAccountId = (int) ($payment['_source_financial_account_id'] ?? $payment['source_financial_account_id'] ?? 0);
        $amount = $this->toCents($payment['_payout_amount'] ?? $payment['net_salary'] ?? 0);
        if ($sourceAccountId <= 0 || $amount <= 0) {
            throw new RuntimeException('A legacy payout leg requires a source financial account and a positive amount.');
        }

        $payslip = $this->payslip($payslipId);
        if (!$payslip) throw new RuntimeException('Payslip not found.');
        $period = $this->periodForPayslip($payslip);
        $reference = trim($reference) !== ''
            ? trim($reference)
            : sprintf('PAY-%d-%02d-%d', (int) $period['year'], (int) $period['month'], (int) ($payslip['staff_id'] ?? 0));

        // Adopt an already-recorded legacy provider transaction before creating
        // a new provider attempt. The foreign key, rather than a rewritten
        // provider idempotency key, is the authoritative link.
        $legacyTx = $this->latestTransactionForPayslip($payslipId);
        $sequence = 1;
        $cancelled = null;
        foreach ($existing as $leg) {
            if ((string) ($leg['status'] ?? '') === 'cancelled' && (int) ($leg['sequence_no'] ?? 0) >= $sequence) {
                $sequence = (int) $leg['sequence_no'];
                $cancelled = $leg;
            }
        }

        if ($cancelled) {
            $legId = (int) $cancelled['id'];
            $this->db->prepare(
                "UPDATE payroll_payout_legs
                 SET payroll_id = ?, method = ?, source_financial_account_id = ?, amount = ?, reference = ?,
                     status = 'planned', provider_reference = NULL, notes = NULL,
                     completed_at = NULL, failed_at = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([
                (int) $period['run_id'],
                $method,
                $sourceAccountId,
                $amount / 100,
                $reference,
                $legId,
            ]);
        } else {
            $legId = $this->insertLeg(
                $payslip,
                $period,
                $sequence,
                $method,
                $sourceAccountId,
                $amount / 100,
                $reference,
                $actorUserId,
                'planned'
            );
        }

        if ($legacyTx) {
            $this->db->prepare(
                "UPDATE disbursement_transactions
                 SET payroll_payout_leg_id = ?
                 WHERE id = ? AND (payroll_payout_leg_id IS NULL OR payroll_payout_leg_id = ?)"
            )->execute([$legId, (int) $legacyTx['id'], $legId]);
            if (($legacyTx['provider_reference'] ?? '') !== '') {
                $this->markSubmission(
                    $legId,
                    $this->legStatusFromTransaction((string) ($legacyTx['status'] ?? 'pending')),
                    (string) $legacyTx['provider_reference']
                );
            }
        }

        return $this->plansForPayslip($payslipId);
    }

    /**
     * Update a leg after a provider attempt. Completed and cancelled legs are
     * terminal; a failed leg may enter a new retry attempt.
     */
    public function markSubmission(int $legId, string $status, ?string $providerReference = null, ?string $description = null): void
    {
        $normalisedStatus = in_array($status, ['planned', 'pending', 'processing', 'completed', 'failed', 'cancelled'], true)
            ? $status
            : 'pending';
        $stmt = $this->db->prepare(
            "UPDATE payroll_payout_legs
             SET status = ?,
                 provider_reference = COALESCE(?, provider_reference),
                 notes = COALESCE(?, notes),
                 completed_at = IF(? = 'completed', COALESCE(completed_at, NOW()), completed_at),
                 failed_at = IF(? = 'failed', COALESCE(failed_at, NOW()), NULL),
                 updated_at = NOW()
             WHERE id = ? AND status NOT IN ('completed', 'cancelled')"
        );
        $stmt->execute([
            $normalisedStatus,
            $providerReference,
            $description,
            $normalisedStatus,
            $normalisedStatus,
            $legId,
        ]);
    }

    /**
     * Apply a provider transaction outcome to its linked payout leg.
     *
     * @return array{leg_id:int|null,fully_paid:bool}
     */
    public function applyDisbursementOutcome(int $disbursementId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, payroll_payout_leg_id, status, transaction_ref, transaction_id,
                    result_description, payslip_id
             FROM disbursement_transactions WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$disbursementId]);
        $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$disbursement) return ['leg_id' => null, 'fully_paid' => false];

        $legId = (int) ($disbursement['payroll_payout_leg_id'] ?? 0);
        if ($legId <= 0) return ['leg_id' => null, 'fully_paid' => false];

        // A late callback for an older attempt must not overwrite the state of
        // a newer retry. The older transaction remains auditable in the ledger.
        $latestStmt = $this->db->prepare(
            "SELECT id FROM disbursement_transactions
             WHERE payroll_payout_leg_id = ? ORDER BY id DESC LIMIT 1"
        );
        $latestStmt->execute([$legId]);
        $latestId = (int) ($latestStmt->fetchColumn() ?: 0);
        if ($latestId === (int) $disbursement['id']) {
            $legStatus = $this->legStatusFromTransaction((string) ($disbursement['status'] ?? 'pending'));
            $providerReference = $disbursement['transaction_ref'] ?: ($disbursement['transaction_id'] ?: null);
            $this->markSubmission(
                $legId,
                $legStatus,
                $providerReference,
                $disbursement['result_description'] ?? null
            );
        }

        $fullyPaid = false;
        if (!empty($disbursement['payslip_id'])) {
            $fullyPaid = $this->syncPayslipFromLegs((int) $disbursement['payslip_id']);
        }
        return ['leg_id' => $legId, 'fully_paid' => $fullyPaid];
    }

    /**
     * Recalculate the aggregate payslip/run status from all linked legs.
     */
    public function syncPayslipFromLegs(int $payslipId): bool
    {
        $legs = array_values(array_filter(
            $this->allLegsForPayslip($payslipId),
            static fn(array $leg): bool => (string) ($leg['status'] ?? '') !== 'cancelled'
        ));
        if (!$legs) return false;

        $statuses = array_map(static fn(array $leg): string => (string) ($leg['status'] ?? 'planned'), $legs);
        $allCompleted = !in_array(false, array_map(static fn(string $status): bool => $status === 'completed', $statuses), true);
        $anyFailed = in_array('failed', $statuses, true);
        $anyInFlight = in_array('planned', $statuses, true)
            || in_array('pending', $statuses, true)
            || in_array('processing', $statuses, true);

        $payslip = $this->payslip($payslipId);
        if (!$payslip) return false;

        if ($allCompleted) {
            $references = [];
            foreach ($legs as $leg) {
                $reference = trim((string) ($leg['provider_reference'] ?? $leg['reference'] ?? ''));
                if ($reference !== '') $references[] = $reference;
            }
            $paymentReference = substr(implode(', ', array_slice($references, 0, 5)), 0, 100);
            $this->db->prepare(
                "UPDATE payslips
                 SET payslip_status = 'paid', payment_status = 'paid', paid_at = COALESCE(paid_at, NOW()),
                     payment_reference = ?, updated_at = NOW()
                 WHERE id = ? AND payslip_status <> 'cancelled'"
            )->execute([$paymentReference ?: null, $payslipId]);
        } elseif ($anyInFlight) {
            $this->db->prepare(
                "UPDATE payslips SET payment_status = 'processing', updated_at = NOW()
                 WHERE id = ? AND payment_status <> 'paid' AND payslip_status <> 'cancelled'"
            )->execute([$payslipId]);
        } elseif ($anyFailed) {
            $this->db->prepare(
                "UPDATE payslips SET payment_status = 'failed', updated_at = NOW()
                 WHERE id = ? AND payment_status <> 'paid' AND payslip_status <> 'cancelled'"
            )->execute([$payslipId]);
        } else {
            $this->db->prepare(
                "UPDATE payslips SET payment_status = 'pending', updated_at = NOW()
                 WHERE id = ? AND payment_status <> 'paid' AND payslip_status <> 'cancelled'"
            )->execute([$payslipId]);
        }

        $this->syncPayrollRun((int) $payslip['payroll_month'], (int) $payslip['payroll_year'], (string) $payslip['data_scope']);
        return $allCompleted;
    }

    /**
     * Release every active leg of the selected approved payslips. Each leg is
     * independently idempotent and independently recoverable.
     *
     * @return array<string,mixed>
     */
    public function releasePayroll(int $payrollRunId, int $actorUserId, array $selectedPayslipIds = []): array
    {
        DataScopeService::requireLiveExternalAction('Payroll payout release');
        if ($payrollRunId <= 0) throw new RuntimeException('Payroll run ID is required.');

        [$runScopeSql, $runScopeParams] = DataScopeService::predicateFor('payroll_runs', 'pr');
        $runStmt = $this->db->prepare(
            "SELECT pr.id, pr.month, pr.year, pr.status, pr.data_scope
             FROM payroll_runs pr
             WHERE pr.id = ? AND pr.data_scope = 'live' AND $runScopeSql LIMIT 1"
        );
        $runStmt->execute(array_merge([$payrollRunId], $runScopeParams));
        $run = $runStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$run) throw new RuntimeException('Payroll run not found.');
        if (!in_array((string) $run['status'], ['approved', 'processing'], true)) {
            throw new RuntimeException('The payroll run must be approved before payment can be released.');
        }

        $selectedPayslipIds = array_values(array_unique(array_filter(array_map('intval', $selectedPayslipIds))));
        [$payslipScopeSql, $payslipScopeParams] = DataScopeService::predicateFor('payslips', 'ps');
        $sql = "SELECT ps.id, ps.staff_id, ps.net_salary, ps.payslip_status, ps.payment_status
                 FROM payslips ps
                 WHERE ps.payroll_month = ? AND ps.payroll_year = ? AND ps.data_scope = 'live'
                   AND $payslipScopeSql";
        $params = array_merge([(int) $run['month'], (int) $run['year']], $payslipScopeParams);
        if ($selectedPayslipIds) {
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($selectedPayslipIds), '?')) . ')';
            $params = array_merge($params, $selectedPayslipIds);
        }
        $sql .= " ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$payslips) throw new RuntimeException('No payroll records are ready for release.');

        // Preflight the complete selected batch before sending any money. This
        // prevents a missing plan, account permission, recipient detail, or
        // cash receipt from leaving a partially released batch behind.
        foreach ($payslips as $payslip) {
            if ((string) $payslip['payslip_status'] === 'paid') continue;
            if ((string) $payslip['payslip_status'] !== 'approved') {
                throw new RuntimeException('Only approved payslips can be released.');
            }
            $legs = array_values(array_filter(
                $this->allLegsForPayslip((int) $payslip['id']),
                static fn(array $leg): bool => (string) ($leg['status'] ?? '') !== 'cancelled'
            ));
            if (!$legs) {
                throw new RuntimeException('Save a complete payout plan before releasing this payslip.');
            }
            $recipient = $this->recipientForStaff((int) $payslip['staff_id']);
            foreach ($legs as $leg) {
                if (in_array((string) ($leg['status'] ?? ''), ['completed', 'failed'], true)) continue;
                $method = (string) $leg['method'];
                $account = $this->accounts->requireFor(
                    (int) $leg['source_financial_account_id'],
                    'payroll',
                    $this->financialChannel($method),
                    true,
                    $actorUserId
                );
                $this->assertAccountSupportsChannel($account, $method);
                $this->assertRecipientReady($method, $recipient);
                if ($method === 'cash' && trim((string) ($leg['reference'] ?? '')) === '') {
                    throw new RuntimeException('Cash payout legs require a receipt or cheque reference.');
                }
            }
        }

        $this->db->prepare(
            "UPDATE payroll_runs SET status = 'processing', workflow = 'processing'
             WHERE id = ? AND status <> 'paid'"
        )->execute([$payrollRunId]);

        $results = [];
        $totals = ['released' => 0, 'pending' => 0, 'failed' => 0, 'completed' => 0];
        foreach ($payslips as $payslip) {
            $payslipId = (int) $payslip['id'];
            if ((string) $payslip['payslip_status'] === 'paid') {
                $totals['completed']++;
                $results[] = [
                    'payslip_id' => $payslipId,
                    'status' => 'completed',
                    'message' => 'Payslip was already paid.',
                ];
                continue;
            }
            if ((string) $payslip['payslip_status'] !== 'approved') {
                throw new RuntimeException('Only approved payslips can be released.');
            }

            $legs = array_values(array_filter(
                $this->allLegsForPayslip($payslipId),
                static fn(array $leg): bool => (string) ($leg['status'] ?? '') !== 'cancelled'
            ));
            if (!$legs) {
                throw new RuntimeException('Save a complete payout plan before releasing this payslip.');
            }

            $legResults = [];
            foreach ($legs as $leg) {
                $legResults[] = $this->releaseLeg($leg, $actorUserId);
            }
            $fullyPaid = $this->syncPayslipFromLegs($payslipId);
            foreach ($legResults as $legResult) {
                $status = (string) ($legResult['status'] ?? 'pending');
                if ($status === 'completed') $totals['completed']++;
                elseif ($status === 'failed') $totals['failed']++;
                elseif ($status === 'released') $totals['released']++;
                else $totals['pending']++;
            }
            $results[] = [
                'payslip_id' => $payslipId,
                'staff_id' => (int) $payslip['staff_id'],
                'fully_paid' => $fullyPaid,
                'legs' => $legResults,
            ];
        }

        return [
            'payroll_id' => (int) $run['id'],
            'payroll_run_id' => (int) $run['id'],
            'month' => (int) $run['month'],
            'year' => (int) $run['year'],
            'status' => 'processing',
            'summary' => $totals,
            'results' => $results,
        ];
    }

    /**
     * Confirm receipt of a cash leg. The transaction and journal are idempotent.
     */
    public function confirmCashLeg(int $legId, string $reference, int $actorUserId): array
    {
        DataScopeService::requireLiveExternalAction('Cash payroll confirmation');
        $reference = trim($reference);
        if ($reference === '') throw new RuntimeException('A cash receipt or cheque reference is required.');

        $leg = $this->scopedLegById($legId);
        if (!$leg) throw new RuntimeException('Payout leg not found.');
        if ((string) $leg['data_scope'] !== 'live') throw new RuntimeException('Cash confirmation is restricted to the live workspace.');
        if ((string) $leg['method'] !== 'cash') throw new RuntimeException('Only cash payout legs can be confirmed manually.');
        if ((string) $leg['status'] === 'completed') {
            return [
                'leg_id' => $legId,
                'status' => 'completed',
                'fully_paid' => $this->syncPayslipFromLegs((int) $leg['payslip_id']),
            ];
        }
        if ((string) $leg['status'] !== 'pending') {
            throw new RuntimeException('Release the cash payout before confirming its receipt.');
        }

        $account = $this->accounts->requireFor(
            (int) $leg['source_financial_account_id'],
            'payroll',
            $this->financialChannel((string) $leg['method']),
            true,
            $actorUserId
        );
        $this->assertAccountSupportsChannel($account, (string) $leg['method']);

        $payslip = $this->payslip((int) $leg['payslip_id']);
        if (!$payslip) throw new RuntimeException('Payslip not found for payout leg.');
        $recipient = $this->recipientForStaff((int) $leg['staff_id']);
        $fullyPaid = false;

        $this->db->beginTransaction();
        try {
            $lockedLeg = $this->db->prepare("SELECT * FROM payroll_payout_legs WHERE id = ? FOR UPDATE");
            $lockedLeg->execute([$legId]);
            $locked = $lockedLeg->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$locked) throw new RuntimeException('Payout leg not found.');
            if ((string) $locked['status'] === 'completed') {
                $this->db->commit();
                return [
                    'leg_id' => $legId,
                    'status' => 'completed',
                    'fully_paid' => $this->syncPayslipFromLegs((int) $locked['payslip_id']),
                ];
            }
            if ((string) $locked['status'] === 'cancelled') throw new RuntimeException('A cancelled payout leg cannot be completed.');

            $tx = $this->db->prepare(
                "SELECT * FROM disbursement_transactions
                 WHERE payroll_payout_leg_id = ? AND channel = 'cash'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $tx->execute([$legId]);
            $transaction = $tx->fetch(PDO::FETCH_ASSOC);
            if ($transaction && (string) $transaction['status'] === 'completed') {
                $this->db->prepare(
                    "UPDATE payroll_payout_legs
                     SET status = 'completed', provider_reference = ?, completed_at = COALESCE(completed_at, NOW()),
                         updated_at = NOW()
                     WHERE id = ?"
                )->execute([$reference, $legId]);
                $fullyPaid = $this->syncPayslipFromLegs((int) $locked['payslip_id']);
                $this->db->commit();
                return [
                    'leg_id' => $legId,
                    'status' => 'completed',
                    'fully_paid' => $fullyPaid,
                ];
            }

            if (!$transaction) {
                $insert = $this->db->prepare(
                    "INSERT INTO disbursement_transactions
                        (disbursement_type, payment_purpose, source_financial_account_id, payroll_id, payslip_id,
                         payroll_payout_leg_id, recipient_id, recipient_type, recipient_name, amount, currency,
                         idempotency_reference, channel, status, reconciliation_status, transaction_ref,
                         result_description, callback_data, submitted_by, approved_by, approved_at, released_at,
                         created_at, completed_at)
                     VALUES ('salary', 'payroll', ?, ?, ?, ?, ?, 'staff', ?, ?, 'KES', ?, 'cash',
                             'completed', 'confirmed_success', ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())"
                );
                $insert->execute([
                    (int) $locked['source_financial_account_id'],
                    (int) ($locked['payroll_id'] ?: 0) ?: null,
                    (int) $locked['payslip_id'],
                    $legId,
                    (int) $locked['staff_id'],
                    $recipient['name'],
                    (float) $locked['amount'],
                    (string) ($locked['idempotency_reference'] ?: $this->cashIdempotencyReference($legId)),
                    $reference,
                    'Cash payroll receipt confirmed manually',
                    json_encode(['confirmed_by' => $actorUserId, 'reference' => $reference, 'confirmed_at' => date('c')]),
                    $actorUserId > 0 ? $actorUserId : null,
                    $actorUserId > 0 ? $actorUserId : null,
                ]);
                $transactionId = (int) $this->db->lastInsertId();
            } else {
                $transactionId = (int) $transaction['id'];
                $this->db->prepare(
                    "UPDATE disbursement_transactions
                     SET status = 'completed', reconciliation_status = 'confirmed_success', transaction_ref = ?,
                         result_description = ?, callback_data = ?, completed_at = NOW(), failed_at = NULL,
                         approved_by = COALESCE(approved_by, ?), approved_at = COALESCE(approved_at, NOW())
                     WHERE id = ?"
                )->execute([
                    $reference,
                    'Cash payroll receipt confirmed manually',
                    json_encode(['confirmed_by' => $actorUserId, 'reference' => $reference, 'confirmed_at' => date('c')]),
                    $actorUserId > 0 ? $actorUserId : null,
                    $transactionId,
                ]);
            }

            $this->db->prepare(
                "UPDATE payroll_payout_legs
                 SET status = 'completed', reference = ?, provider_reference = ?, notes = ?,
                     completed_at = COALESCE(completed_at, NOW()), failed_at = NULL, updated_at = NOW()
                 WHERE id = ? AND status <> 'cancelled'"
            )->execute([$reference, $reference, 'Cash receipt confirmed manually', $legId]);

            (new FinancialPostingCoordinator($this->db))->postDisbursement(
                'disbursement',
                $transactionId,
                (int) $locked['source_financial_account_id'],
                'payroll',
                number_format((float) $locked['amount'], 2, '.', ''),
                $actorUserId
            );

            $fullyPaid = $this->syncPayslipFromLegs((int) $locked['payslip_id']);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        if ($fullyPaid) {
            (new PayrollChildFeeTransferService($this->db))->postForPayslip((int) $leg['payslip_id']);
        }

        return ['leg_id' => $legId, 'status' => 'completed', 'fully_paid' => $fullyPaid];
    }

    /**
     * Create a new provider attempt for one confirmed failed payout leg.
     */
    public function retryLeg(int $legId, int $actorUserId): array
    {
        DataScopeService::requireLiveExternalAction('Payroll payout retry');
        $leg = $this->scopedLegById($legId);
        if (!$leg) throw new RuntimeException('Payout leg not found.');
        if ((string) $leg['data_scope'] !== 'live') throw new RuntimeException('Payout retry is restricted to the live workspace.');
        $method = (string) $leg['method'];
        if ($method === 'cash') {
            throw new RuntimeException('Confirm the cash receipt instead of retrying a cash leg.');
        }
        if ((string) $leg['status'] !== 'failed') {
            throw new RuntimeException('Only a confirmed failed payout leg can be retried.');
        }

        $latest = $this->latestTransactionForLeg($legId);
        if (!$latest || (string) $latest['status'] !== 'failed') {
            throw new RuntimeException('The previous provider attempt did not confirm a failure.');
        }
        if ((string) ($latest['reconciliation_status'] ?? '') !== 'confirmed_failure') {
            throw new RuntimeException('Reconcile the previous attempt before retrying it.');
        }

        $account = $this->accounts->requireFor(
            (int) $leg['source_financial_account_id'],
            'payroll',
            $this->financialChannel($method),
            true,
            $actorUserId
        );
        $this->assertAccountSupportsChannel($account, $method);
        $recipient = $this->recipientForStaff((int) $leg['staff_id']);

        if ($method === 'bank') {
            $result = (new KcbTransferReconciliationService($this->db, new KcbFundsTransferService()))
                ->retry((int) $latest['id'], $actorUserId);
            $newAttempt = $this->latestTransactionForLeg($legId);
            $status = (string) ($newAttempt['status'] ?? 'pending');
            if ($status === 'completed') {
                $this->markSubmission($legId, 'completed', $newAttempt['transaction_ref'] ?? null);
            } elseif ($status === 'failed') {
                $this->markSubmission($legId, 'failed', $newAttempt['transaction_ref'] ?? null, $newAttempt['result_description'] ?? null);
            } else {
                $this->markSubmission($legId, 'pending', $newAttempt['transaction_ref'] ?? null, $newAttempt['result_description'] ?? null);
            }
            $fullyPaid = $this->syncPayslipFromLegs((int) $leg['payslip_id']);
            return [
                'leg_id' => $legId,
                'status' => $status,
                'fully_paid' => $fullyPaid,
                'result' => $result,
            ];
        }

        $attempt = $this->nextAttemptNumber($legId);
        $idempotency = $this->retryIdempotencyReference($legId, $attempt);
        $response = (new MpesaB2CService($this->db))->sendPayment([
            'phone' => $recipient['phone'],
            'amount' => (float) $leg['amount'],
            'command_id' => 'SalaryPayment',
            'remarks' => 'Salary retry ' . $this->periodLabel($leg),
            'occasion' => 'Staff Salary',
            'recipient_id' => (int) $leg['staff_id'],
            'recipient_type' => 'staff',
            'recipient_name' => $recipient['name'],
            'payroll_id' => (int) ($leg['payroll_id'] ?: 0) ?: null,
            'payslip_id' => (int) $leg['payslip_id'],
            'payroll_payout_leg_id' => $legId,
            'disbursement_type' => 'salary',
            'payment_purpose' => 'payroll',
            'source_financial_account_id' => (int) $leg['source_financial_account_id'],
            'idempotency_reference' => $idempotency,
            'originator_conversation_id' => $this->originatorConversationId($legId, $attempt, true),
            'actor_user_id' => $actorUserId,
        ]);

        $newAttempt = $this->latestTransactionForLeg($legId);
        $responseStatus = (string) ($response['status'] ?? 'error');
        if ($responseStatus === 'success') {
            $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? null);
        } elseif ($responseStatus === 'pending') {
            $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? null, $response['message'] ?? null);
        } elseif ($newAttempt && (string) $newAttempt['status'] === 'failed') {
            $this->markSubmission($legId, 'failed', $newAttempt['transaction_ref'] ?? null, $newAttempt['result_description'] ?? null);
        } else {
            $this->markSubmission($legId, 'failed', null, $response['message'] ?? 'M-Pesa retry could not be submitted.');
        }
        $fullyPaid = $this->syncPayslipFromLegs((int) $leg['payslip_id']);
        return [
            'leg_id' => $legId,
            'status' => (string) ($newAttempt['status'] ?? $responseStatus),
            'fully_paid' => $fullyPaid,
            'result' => $response,
        ];
    }

    /**
     * Return every provider transaction and every not-yet-transacted payout leg
     * for a month. Cancelled plan revisions without a transaction are omitted.
     */
    public function monthlyLedger(int $month, int $year): array
    {
        [$legScopeSql, $legScopeParams] = DataScopeService::predicateFor('payroll_payout_legs', 'pl');
        $legStmt = $this->db->prepare(
            "SELECT pl.* FROM payroll_payout_legs pl
             WHERE pl.payroll_month = ? AND pl.payroll_year = ? AND $legScopeSql
             ORDER BY pl.staff_id, pl.sequence_no, pl.id"
        );
        $legStmt->execute(array_merge([$month, $year], $legScopeParams));
        $legs = $this->decorateLegs($legStmt->fetchAll(PDO::FETCH_ASSOC));

        [$payScopeSql, $payScopeParams] = DataScopeService::predicateFor('payslips', 'ps');
        [$runScopeSql, $runScopeParams] = DataScopeService::predicateFor('payroll_runs', 'pr');
        $payslipRef = ReadReplicaService::qualifiedRef('payslips');
        $runRef = ReadReplicaService::qualifiedRef('payroll_runs');
        $txStmt = $this->db->prepare(
            "SELECT dt.* FROM disbursement_transactions dt
             LEFT JOIN {$payslipRef} ps ON ps.id = dt.payslip_id
             LEFT JOIN {$runRef} pr ON pr.id = dt.payroll_id
             WHERE (
                    (ps.payroll_month = ? AND ps.payroll_year = ? AND $payScopeSql)
                    OR
                    (pr.month = ? AND pr.year = ? AND $runScopeSql)
                  )
               AND (dt.disbursement_type = 'salary' OR dt.payment_purpose = 'payroll')
             ORDER BY dt.id"
        );
        $txStmt->execute(array_merge(
            [$month, $year],
            $payScopeParams,
            [$month, $year],
            $runScopeParams
        ));
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

        $transactionsByLeg = [];
        foreach ($transactions as $transaction) {
            $legId = (int) ($transaction['payroll_payout_leg_id'] ?? 0);
            if ($legId > 0) $transactionsByLeg[$legId][] = $transaction;
        }

        $ledger = [];
        $representedTransactions = [];
        foreach ($legs as $leg) {
            $legTransactions = $transactionsByLeg[(int) $leg['id']] ?? [];
            if (!$legTransactions && (string) $leg['status'] === 'cancelled') continue;
            if (!$legTransactions) {
                $ledger[] = $this->ledgerRowFromLeg($leg);
                continue;
            }
            foreach ($legTransactions as $attempt => $transaction) {
                $representedTransactions[(int) $transaction['id']] = true;
                $ledger[] = $this->ledgerRowFromTransaction($leg, $transaction, $attempt + 1);
            }
        }

        // Legacy payouts had no payout-leg row. Keep every such provider
        // transaction in the monthly roll instead of silently dropping it.
        foreach ($transactions as $transaction) {
            if (isset($representedTransactions[(int) $transaction['id']])) continue;
            if ((int) ($transaction['payroll_payout_leg_id'] ?? 0) > 0) continue;
            $ledger[] = $this->legacyLedgerRow($transaction, $month, $year);
        }

        return $ledger;
    }

    /**
     * @param array<string,mixed> $allocation
     * @return array<string,mixed>
     */
    private function savePlanForPayslip(array $period, array $allocation, int $actorUserId): array
    {
        $payslipId = (int) ($allocation['payslip_id'] ?? 0);
        $legs = is_array($allocation['legs'] ?? null) ? array_values($allocation['legs']) : [];
        if ($payslipId <= 0 || !$legs) {
            throw new RuntimeException('Each payout allocation requires a payslip and at least one leg.');
        }
        if (count($legs) > 20) {
            throw new RuntimeException('A payslip cannot have more than 20 payout legs.');
        }

        $payslipStmt = $this->db->prepare("SELECT * FROM payslips WHERE id = ? LIMIT 1 FOR UPDATE");
        $payslipStmt->execute([$payslipId]);
        $payslip = $payslipStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$payslip) throw new RuntimeException('Payslip not found.');
        if ((int) $payslip['payroll_month'] !== (int) $period['month'] || (int) $payslip['payroll_year'] !== (int) $period['year']) {
            throw new RuntimeException('The selected payslip does not belong to this payroll period.');
        }
        if ((string) $payslip['data_scope'] !== (string) $period['data_scope']) {
            throw new RuntimeException('Cross-workspace payout allocation is not permitted.');
        }
        if ((string) $payslip['payslip_status'] !== 'approved') {
            throw new RuntimeException('Only approved payslips can receive a payout plan.');
        }

        $existing = $this->allLegsForPayslip($payslipId);
        foreach ($existing as $leg) {
            if (!in_array((string) $leg['status'], ['planned', 'cancelled'], true)) {
                throw new RuntimeException('Payout allocations cannot be changed after a leg has been submitted.');
            }
        }

        $netSalaryCents = $this->toCents($payslip['net_salary']);
        if ($netSalaryCents <= 0) throw new RuntimeException('A payout plan requires a positive net salary.');
        $recipient = $this->recipientForStaff((int) $payslip['staff_id']);
        $normalisedLegs = [];
        $totalCents = 0;

        foreach ($legs as $index => $leg) {
            $method = self::normaliseMethod($leg['method'] ?? '');
            $amountCents = $this->toCents($leg['amount'] ?? 0);
            $sourceAccountId = (int) ($leg['source_financial_account_id'] ?? $leg['source_account_id'] ?? 0);
            $reference = trim((string) ($leg['reference'] ?? ''));
            if ($amountCents <= 0) throw new RuntimeException('Every payout leg must have a positive amount.');
            if ($sourceAccountId <= 0) throw new RuntimeException('Every payout leg must have a source financial account.');
            if ($method === 'cash' && $reference === '') {
                throw new RuntimeException('Cash payout legs require a receipt or cheque reference.');
            }
            if ($method === 'mobile_money' && $amountCents % 100 !== 0) {
                throw new RuntimeException('M-Pesa payout legs must be whole KES; allocate the cents to a bank or cash leg.');
            }

            $channel = $this->financialChannel($method);
            $account = $this->accounts->requireFor($sourceAccountId, 'payroll', $channel, false, null);
            $this->assertAccountSupportsChannel($account, $method);
            $this->assertRecipientReady($method, $recipient);

            if ($reference === '') {
                $reference = sprintf(
                    'PAY-%d-%02d-%d-%d',
                    (int) $payslip['payroll_year'],
                    (int) $payslip['payroll_month'],
                    (int) $payslip['staff_id'],
                    $index + 1
                );
            }
            if (mb_strlen($reference) > 150) {
                throw new RuntimeException('A payout leg reference cannot exceed 150 characters.');
            }

            $totalCents += $amountCents;
            $normalisedLegs[] = [
                'sequence_no' => $index + 1,
                'method' => $method,
                'source_financial_account_id' => $sourceAccountId,
                'amount' => $amountCents / 100,
                'reference' => $reference,
            ];
        }

        if ($totalCents !== $netSalaryCents) {
            throw new RuntimeException(sprintf(
                'Payout legs for staff %d total KES %.2f; the net salary is KES %.2f.',
                (int) $payslip['staff_id'],
                $totalCents / 100,
                $netSalaryCents / 100
            ));
        }

        $existingBySequence = [];
        foreach ($existing as $leg) {
            if (in_array((string) $leg['status'], ['planned', 'cancelled'], true)) {
                $existingBySequence[(int) $leg['sequence_no']] = $leg;
            }
        }

        foreach ($normalisedLegs as $leg) {
            $sequence = (int) $leg['sequence_no'];
            if (isset($existingBySequence[$sequence])) {
                $current = $existingBySequence[$sequence];
                $this->db->prepare(
                    "UPDATE payroll_payout_legs
                     SET method = ?, source_financial_account_id = ?, amount = ?, reference = ?,
                         status = 'planned', provider_reference = NULL, notes = NULL,
                         completed_at = NULL, failed_at = NULL, updated_at = NOW()
                     WHERE id = ?"
                )->execute([
                    $leg['method'],
                    $leg['source_financial_account_id'],
                    $leg['amount'],
                    $leg['reference'],
                    (int) $current['id'],
                ]);
            } else {
                $this->insertLeg(
                    $payslip,
                    $period,
                    $sequence,
                    $leg['method'],
                    $leg['source_financial_account_id'],
                    $leg['amount'],
                    $leg['reference'],
                    $actorUserId,
                    'planned'
                );
            }
        }

        foreach ($existingBySequence as $sequence => $leg) {
            if ($sequence > count($normalisedLegs)) {
                $this->db->prepare(
                    "UPDATE payroll_payout_legs
                     SET status = 'cancelled', notes = 'Superseded before release', updated_at = NOW()
                     WHERE id = ?"
                )->execute([(int) $leg['id']]);
            }
        }

        $methods = array_unique(array_column($normalisedLegs, 'method'));
        $accounts = array_unique(array_column($normalisedLegs, 'source_financial_account_id'));
        if (count($methods) === 1) {
            $this->db->prepare("UPDATE payslips SET payment_method = ?, updated_at = NOW() WHERE id = ?")
                ->execute([reset($methods), $payslipId]);
        } else {
            $this->db->prepare("UPDATE payslips SET payment_method = NULL, updated_at = NOW() WHERE id = ?")
                ->execute([$payslipId]);
        }
        if (count($accounts) === 1) {
            $this->db->prepare("UPDATE payslips SET source_financial_account_id = ?, updated_at = NOW() WHERE id = ?")
                ->execute([reset($accounts), $payslipId]);
        } else {
            $this->db->prepare("UPDATE payslips SET source_financial_account_id = NULL, updated_at = NOW() WHERE id = ?")
                ->execute([$payslipId]);
        }

        return [
            'payslip_id' => $payslipId,
            'staff_id' => (int) $payslip['staff_id'],
            'staff_name' => $recipient['name'],
            'net_salary' => $netSalaryCents / 100,
            'allocated_amount' => $totalCents / 100,
            'legs' => $this->allLegsForPayslip($payslipId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function releaseLeg(array $leg, int $actorUserId): array
    {
        $legId = (int) $leg['id'];
        $status = (string) ($leg['status'] ?? 'planned');
        if ($status === 'completed' || $status === 'cancelled') {
            return ['leg_id' => $legId, 'status' => $status, 'message' => 'Leg already final.'];
        }
        if ($status === 'failed') {
            return ['leg_id' => $legId, 'status' => 'failed', 'message' => 'This leg requires an explicit retry.'];
        }

        $method = (string) $leg['method'];
        $account = $this->accounts->requireFor(
            (int) $leg['source_financial_account_id'],
            'payroll',
            $this->financialChannel($method),
            true,
            $actorUserId
        );
        $this->assertAccountSupportsChannel($account, $method);
        $recipient = $this->recipientForStaff((int) $leg['staff_id']);
        $this->assertRecipientReady($method, $recipient);

        if ($method === 'cash') {
            if (trim((string) ($leg['reference'] ?? '')) === '') {
                throw new RuntimeException('Cash payout legs require a receipt or cheque reference.');
            }
            $this->db->prepare(
                "UPDATE payroll_payout_legs
                 SET status = 'pending', notes = 'Awaiting manual cash confirmation', updated_at = NOW()
                 WHERE id = ? AND status = 'planned'"
            )->execute([$legId]);
            return ['leg_id' => $legId, 'status' => 'pending', 'message' => 'Awaiting cash confirmation.'];
        }

        $latest = $this->latestTransactionForLeg($legId);
        if ($latest && in_array((string) $latest['status'], ['pending', 'completed'], true)) {
            return [
                'leg_id' => $legId,
                'status' => (string) $latest['status'],
                'transaction_id' => (int) $latest['id'],
                'message' => 'Existing provider attempt reused.',
            ];
        }
        if ($latest && (string) $latest['status'] === 'failed') {
            $this->markSubmission($legId, 'failed', $latest['transaction_ref'] ?? null, $latest['result_description'] ?? null);
            return ['leg_id' => $legId, 'status' => 'failed', 'message' => 'The previous attempt confirmed a failure.'];
        }

        if ($method === 'bank') {
            return $this->releaseBankLeg($leg, $account, $recipient, $actorUserId);
        }
        return $this->releaseMobileMoneyLeg($leg, $account, $recipient, $actorUserId);
    }

    /**
     * @return array<string,mixed>
     */
    private function releaseBankLeg(array $leg, array $account, array $recipient, int $actorUserId): array
    {
        $legId = (int) $leg['id'];
        $attempt = $this->nextAttemptNumber($legId);
        $idempotency = $this->attemptIdempotencyReference($legId, $attempt);
        $providerReference = $this->bankTransactionReference($legId, $attempt);

        $insert = $this->db->prepare(
            "INSERT INTO disbursement_transactions
                (disbursement_type, payment_purpose, source_financial_account_id, payroll_id, payslip_id,
                 payroll_payout_leg_id, recipient_id, recipient_type, recipient_name, amount, currency,
                 idempotency_reference, account_number, bank_name, channel, status, reconciliation_status,
                 submitted_by, approved_by, approved_at, released_at, next_status_inquiry_at, created_at)
             VALUES ('salary', 'payroll', ?, ?, ?, ?, ?, 'staff', ?, ?, 'KES', ?, ?, ?, 'kcb_bank',
                     'pending', 'awaiting_callback', ?, ?, NOW(), NOW(),
                     DATE_ADD(NOW(), INTERVAL 2 MINUTE), NOW())"
        );
        try {
            $insert->execute([
                (int) $leg['source_financial_account_id'],
                (int) ($leg['payroll_id'] ?: 0) ?: null,
                (int) $leg['payslip_id'],
                $legId,
                (int) $leg['staff_id'],
                $recipient['name'],
                (float) $leg['amount'],
                $idempotency,
                $recipient['bank_account'],
                $recipient['bank_name'],
                $actorUserId > 0 ? $actorUserId : null,
                $actorUserId > 0 ? $actorUserId : null,
            ]);
            $disbursementId = (int) $this->db->lastInsertId();
        } catch (\PDOException $error) {
            $existing = $this->db->prepare(
                "SELECT id FROM disbursement_transactions WHERE idempotency_reference = ? LIMIT 1"
            );
            $existing->execute([$idempotency]);
            $existingId = (int) ($existing->fetchColumn() ?: 0);
            if ($existingId > 0) {
                return [
                    'leg_id' => $legId,
                    'status' => 'pending',
                    'transaction_id' => $existingId,
                    'message' => 'Existing provider attempt reused.',
                ];
            }
            throw $error;
        }

        $response = (new KcbFundsTransferService())->transferFunds([
            'account_number' => $recipient['bank_account'],
            'bank_name' => $recipient['bank_name'],
            'amount' => (float) $leg['amount'],
            'narration' => 'Salary ' . $this->periodLabel($leg),
            'beneficiary_name' => $recipient['name'],
            'transaction_reference' => $providerReference,
            'debit_account_number' => $account['account_identifier'],
            'source_financial_account_id' => (int) $account['id'],
            'idempotency_reference' => $idempotency,
        ]);

        $accepted = in_array((string) ($response['status'] ?? ''), ['success', 'pending'], true);
        if ($accepted) {
            $this->db->prepare(
                "UPDATE disbursement_transactions
                 SET request_id = ?, transaction_ref = ?, status = 'pending',
                     reconciliation_status = 'awaiting_callback', provider_status = ?,
                     result_description = ?, callback_data = ?,
                     next_status_inquiry_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE)
                 WHERE id = ?"
            )->execute([
                $response['request_id'] ?? null,
                $response['transaction_ref'] ?? $providerReference,
                'SUBMITTED',
                $response['message'] ?? null,
                json_encode($response),
                $disbursementId,
            ]);
            $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? $providerReference);
            return [
                'leg_id' => $legId,
                'status' => 'pending',
                'transaction_id' => $disbursementId,
                'message' => 'Bank transfer submitted.',
            ];
        }

        // A transport/submission error may be ambiguous. Keep the attempt
        // pending for reconciliation and never unlock an automatic retry.
        $this->db->prepare(
            "UPDATE disbursement_transactions
             SET request_id = ?, transaction_ref = ?, status = 'pending',
                 reconciliation_status = 'manual_review', provider_status = 'SUBMISSION_UNCERTAIN',
                 result_description = ?, callback_data = ?, next_status_inquiry_at = NULL
             WHERE id = ?"
        )->execute([
            $response['request_id'] ?? null,
            $response['transaction_ref'] ?? $providerReference,
            $response['message'] ?? 'KCB submission outcome requires reconciliation.',
            json_encode($response),
            $disbursementId,
        ]);
        $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? $providerReference, $response['message'] ?? null);
        return [
            'leg_id' => $legId,
            'status' => 'pending',
            'transaction_id' => $disbursementId,
            'message' => 'KCB submission requires reconciliation.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function releaseMobileMoneyLeg(array $leg, array $account, array $recipient, int $actorUserId): array
    {
        $legId = (int) $leg['id'];
        $attempt = $this->nextAttemptNumber($legId);
        $idempotency = $this->attemptIdempotencyReference($legId, $attempt);
        $response = (new MpesaB2CService($this->db))->sendPayment([
            'phone' => $recipient['phone'],
            'amount' => (float) $leg['amount'],
            'command_id' => 'SalaryPayment',
            'remarks' => 'Salary ' . $this->periodLabel($leg),
            'occasion' => 'Staff Salary',
            'recipient_id' => (int) $leg['staff_id'],
            'recipient_type' => 'staff',
            'recipient_name' => $recipient['name'],
            'payroll_id' => (int) ($leg['payroll_id'] ?: 0) ?: null,
            'payslip_id' => (int) $leg['payslip_id'],
            'payroll_payout_leg_id' => $legId,
            'disbursement_type' => 'salary',
            'payment_purpose' => 'payroll',
            'source_financial_account_id' => (int) $account['id'],
            'idempotency_reference' => $idempotency,
            'originator_conversation_id' => $this->originatorConversationId($legId, $attempt, false),
            'actor_user_id' => $actorUserId,
        ]);

        $status = (string) ($response['status'] ?? 'error');
        $latest = $this->latestTransactionForLeg($legId);
        if ($latest && (string) ($latest['status'] ?? '') === 'completed') {
            $this->markSubmission($legId, 'completed', $latest['transaction_ref'] ?? ($latest['transaction_id'] ?? null));
            return [
                'leg_id' => $legId,
                'status' => 'completed',
                'transaction_id' => (int) $latest['id'],
                'message' => 'M-Pesa payment was already completed.',
            ];
        }
        if ($status === 'success') {
            $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? null);
            return [
                'leg_id' => $legId,
                'status' => 'pending',
                'transaction_id' => (int) ($latest['id'] ?? 0) ?: null,
                'message' => 'M-Pesa payment request accepted.',
            ];
        }
        if ($status === 'pending') {
            $this->markSubmission($legId, 'pending', $response['transaction_ref'] ?? null, $response['message'] ?? null);
            return [
                'leg_id' => $legId,
                'status' => 'pending',
                'transaction_id' => (int) ($latest['id'] ?? 0) ?: null,
                'message' => $response['message'] ?? 'M-Pesa outcome requires reconciliation.',
            ];
        }
        if ($latest && (string) $latest['status'] === 'failed') {
            $this->markSubmission($legId, 'failed', $latest['transaction_ref'] ?? null, $latest['result_description'] ?? null);
            return [
                'leg_id' => $legId,
                'status' => 'failed',
                'transaction_id' => (int) $latest['id'],
                'message' => $latest['result_description'] ?? 'M-Pesa rejected the payment request.',
            ];
        }

        $this->markSubmission($legId, 'failed', null, $response['message'] ?? 'M-Pesa payment could not be submitted.');
        return [
            'leg_id' => $legId,
            'status' => 'failed',
            'message' => $response['message'] ?? 'M-Pesa payment could not be submitted.',
        ];
    }

    private function insertLeg(
        array $payslip,
        array $period,
        int $sequence,
        string $method,
        int $sourceAccountId,
        float $amount,
        string $reference,
        int $actorUserId,
        string $status
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO payroll_payout_legs
                (payslip_id, payroll_id, staff_id, payroll_month, payroll_year, data_scope, sequence_no, method,
                 source_financial_account_id, amount, reference, idempotency_reference,
                 status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            (int) $payslip['id'],
            (int) $period['run_id'],
            (int) $payslip['staff_id'],
            (int) $period['month'],
            (int) $period['year'],
            (string) $payslip['data_scope'],
            $sequence,
            $method,
            $sourceAccountId,
            $amount,
            $reference,
            $this->legIdempotencyReference((int) $payslip['id'], $sequence),
            $status,
            $actorUserId > 0 ? $actorUserId : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function normaliseAllocationList(array $allocations): array
    {
        $result = [];
        foreach ($allocations as $key => $allocation) {
            if (!is_array($allocation)) continue;
            if (!isset($allocation['payslip_id']) && is_numeric($key)) {
                $allocation['payslip_id'] = (int) $key;
            }
            if (!isset($allocation['legs']) && isset($allocation['payout_legs'])) {
                $allocation['legs'] = $allocation['payout_legs'];
            }
            $result[] = $allocation;
        }
        return $result;
    }

    /**
     * @return array{run_id:int,month:int,year:int,status:string,data_scope:string}
     */
    private function resolvePayrollReference(int $reference): array
    {
        if ($reference <= 0) throw new RuntimeException('Payroll reference is required.');
        [$scopeSql, $scopeParams] = DataScopeService::predicateFor('payroll_runs', 'pr');
        $stmt = $this->db->prepare(
            "SELECT pr.id, pr.month, pr.year, pr.status, pr.data_scope
             FROM payroll_runs pr
             WHERE pr.id = ? AND $scopeSql LIMIT 1"
        );
        $stmt->execute(array_merge([$reference], $scopeParams));
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new RuntimeException('Payroll run not found.');

        return [
            'run_id' => (int) $run['id'],
            'month' => (int) $run['month'],
            'year' => (int) $run['year'],
            'status' => (string) $run['status'],
            'data_scope' => (string) $run['data_scope'],
        ];
    }

    /**
     * @return array{run_id:int,month:int,year:int,status:string,data_scope:string}
     */
    private function periodForPayslip(array $payslip): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, month, year, status, data_scope FROM payroll_runs
             WHERE month = ? AND year = ? AND data_scope = ? LIMIT 1"
        );
        $stmt->execute([
            (int) $payslip['payroll_month'],
            (int) $payslip['payroll_year'],
            (string) $payslip['data_scope'],
        ]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new RuntimeException('Payroll run not found for this payslip.');
        return [
            'run_id' => (int) $run['id'],
            'month' => (int) $run['month'],
            'year' => (int) $run['year'],
            'status' => (string) $run['status'],
            'data_scope' => (string) $run['data_scope'],
        ];
    }

    private function payslip(int $payslipId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payslips WHERE id = ? LIMIT 1");
        $stmt->execute([$payslipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function allLegsForPayslip(int $payslipId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM payroll_payout_legs WHERE payslip_id = ? ORDER BY sequence_no, id"
        );
        $stmt->execute([$payslipId]);
        return $this->decorateLegs($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function latestTransactionForPayslip(int $payslipId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, status, source_financial_account_id, amount, channel,
                    transaction_ref, transaction_id, payroll_payout_leg_id
             FROM disbursement_transactions
             WHERE payslip_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$payslipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['provider_reference'] = $row['transaction_ref'] ?: ($row['transaction_id'] ?: null);
        return $row;
    }

    private function latestTransactionForLeg(int $legId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM disbursement_transactions
             WHERE payroll_payout_leg_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$legId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function nextAttemptNumber(int $legId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM disbursement_transactions WHERE payroll_payout_leg_id = ?"
        );
        $stmt->execute([$legId]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function legStatusFromTransaction(string $status): string
    {
        return match ($status) {
            'completed' => 'completed',
            'failed', 'cancelled' => 'failed',
            'timeout' => 'pending',
            'pending' => 'processing',
            default => 'pending',
        };
    }

    /**
     * @return array{name:string,phone:string,bank_name:string,bank_account:string}
     */
    private function recipientForStaff(int $staffId): array
    {
        [$scopeSql, $scopeParams] = DataScopeService::predicateFor('staff', 's');
        $stmt = $this->db->prepare(
            "SELECT s.person_id, p.first_name, p.last_name, p.phone,
                    spp.bank_name, spp.bank_account, spp.mpesa_phone
             FROM staff s
             LEFT JOIN persons p ON p.id = s.person_id
             LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
             WHERE s.id = ? AND $scopeSql LIMIT 1"
        );
        $stmt->execute(array_merge([$staffId], $scopeParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
            'phone' => trim((string) ($row['mpesa_phone'] ?? $row['phone'] ?? '')),
            'bank_name' => trim((string) ($row['bank_name'] ?? '')),
            'bank_account' => trim((string) ($row['bank_account'] ?? '')),
        ];
    }

    private function assertRecipientReady(string $method, array $recipient): void
    {
        if ($method === 'bank' && ($recipient['bank_name'] === '' || $recipient['bank_account'] === '')) {
            throw new RuntimeException('Bank payout legs require the employee bank name and account number.');
        }
        if ($method === 'mobile_money' && ($recipient['phone'] === '' || !$this->validKenyanPhone($recipient['phone']))) {
            throw new RuntimeException('M-Pesa payout legs require a valid employee M-Pesa phone number.');
        }
    }

    private function validKenyanPhone(string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        return (strlen($digits) === 9 && $digits[0] !== '0')
            || (strlen($digits) === 10 && $digits[0] === '0')
            || (strlen($digits) === 12 && str_starts_with($digits, '254'));
    }

    private function financialChannel(string $method): string
    {
        return [
            'bank' => 'kcb_buni_transfer',
            'cash' => 'cash',
            'mobile_money' => 'safaricom_b2c',
        ][$method] ?? $method;
    }

    private function assertAccountSupportsChannel(array $account, string $method): void
    {
        $accountId = (int) ($account['id'] ?? 0);
        $channelStmt = $this->db->prepare(
            "SELECT channel_id FROM school_financial_account_channels WHERE financial_account_id = ?"
        );
        $channelStmt->execute([$accountId]);
        $channelIds = array_map('intval', $channelStmt->fetchAll(PDO::FETCH_COLUMN));
        $codes = [];
        if ($channelIds) {
            $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
            $codeStmt = $this->db->prepare("SELECT code FROM financial_channels WHERE id IN ($placeholders)");
            $codeStmt->execute($channelIds);
            $codes = array_map('strval', $codeStmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $allowed = [
            'bank' => ['kcb_buni_transfer', 'bank_transfer', 'buni_transfer'],
            'cash' => ['cash'],
            'mobile_money' => ['safaricom_b2c', 'mpesa_b2c'],
        ][$method] ?? [];

        if ($codes) {
            if (!array_intersect($codes, $allowed)) {
                throw new RuntimeException('The selected source account is not configured for this payout method.');
            }
            return;
        }

        // Older manually verified accounts predate explicit channel rows. Keep
        // those usable only when their own metadata clearly supports the rail.
        $kind = strtolower((string) ($account['account_kind'] ?? ''));
        $provider = strtolower((string) ($account['provider_code'] ?? ''));
        $identifier = trim((string) ($account['account_identifier'] ?? ''));
        if ($identifier === '') {
            throw new RuntimeException('The selected source account has no usable account identifier.');
        }
        if ($method === 'bank') {
            if ((str_contains($kind, 'bank') || str_contains($provider, 'kcb'))
                && trim((string) ($account['bank_name'] ?? '')) !== '') return;
        } elseif ($method === 'mobile_money') {
            if (str_contains($provider, 'safaricom')
                || str_contains($provider, 'mpesa')
                || str_contains($kind, 'mobile')
                || str_contains($kind, 'mpesa')) return;
        } elseif ($method === 'cash') {
            if (str_contains($kind, 'cash') || str_contains($kind, 'petty')) return;
        }
        throw new RuntimeException('The selected source account is not configured for this payout method.');
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     * @return array<int,array<string,mixed>>
     */
    private function decorateLegs(array $legs): array
    {
        if (!$legs) return [];
        $accountIds = [];
        $staffIds = [];
        foreach ($legs as $leg) {
            $accountIds[(int) $leg['source_financial_account_id']] = (int) $leg['source_financial_account_id'];
            $staffIds[(int) $leg['staff_id']] = (int) $leg['staff_id'];
        }
        $accounts = [];
        if ($accountIds) {
            $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, account_name, account_identifier, bank_name
                 FROM school_financial_accounts WHERE id IN ($placeholders)"
            );
            $stmt->execute(array_values($accountIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
                $accounts[(int) $account['id']] = $account;
            }
        }

        $staff = [];
        if ($staffIds) {
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $directory = ReadReplicaService::qualifiedRef('staff_directory');
            $stmt = $this->db->prepare(
                "SELECT DISTINCT staff_id, staff_no, full_name
                 FROM $directory WHERE staff_id IN ($placeholders)"
            );
            $stmt->execute(array_values($staffIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $person) {
                $staff[(int) $person['staff_id']] = $person;
            }
        }

        foreach ($legs as &$leg) {
            $account = $accounts[(int) $leg['source_financial_account_id']] ?? [];
            $person = $staff[(int) $leg['staff_id']] ?? [];
            $leg['method_label'] = self::methodLabel((string) $leg['method']);
            $leg['source_account_name'] = $account['account_name'] ?? null;
            $leg['source_account_identifier'] = $account['account_identifier'] ?? null;
            $leg['source_account_bank'] = $account['bank_name'] ?? null;
            $leg['staff_name'] = $person['full_name'] ?? null;
            $leg['staff_no'] = $person['staff_no'] ?? null;
        }
        unset($leg);
        return $legs;
    }

    private function syncPayrollRun(int $month, int $year, string $scope): void
    {
        $stmt = $this->db->prepare(
            "SELECT id, payment_status FROM payslips
             WHERE payroll_month = ? AND payroll_year = ? AND data_scope = ?"
        );
        $stmt->execute([$month, $year, $scope]);
        $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$payslips) return;
        $allPaid = true;
        foreach ($payslips as $payslip) {
            $allPaid = $allPaid && (string) $payslip['payment_status'] === 'paid';
        }
        if ($allPaid) {
            $this->db->prepare(
                "UPDATE payroll_runs SET status = 'paid', workflow = 'completed'
                 WHERE month = ? AND year = ? AND data_scope = ?"
            )->execute([$month, $year, $scope]);
            return;
        }
        $this->db->prepare(
            "UPDATE payroll_runs SET status = 'processing', workflow = 'processing'
             WHERE month = ? AND year = ? AND data_scope = ? AND status <> 'paid'"
        )->execute([$month, $year, $scope]);
    }

    private function legIdempotencyReference(int $payslipId, int $sequence): string
    {
        return sprintf('PAYROLL-PAYSLIP-%d-LEG-%d', $payslipId, $sequence);
    }

    private function attemptIdempotencyReference(int $legId, int $attempt): string
    {
        return sprintf('PAYROLL-LEG-%d-ATTEMPT-%d', $legId, $attempt);
    }

    private function retryIdempotencyReference(int $legId, int $attempt): string
    {
        return sprintf('PAYROLL-LEG-%d-RETRY-%d', $legId, $attempt);
    }

    private function cashIdempotencyReference(int $legId): string
    {
        return sprintf('PAYROLL-LEG-%d-CASH', $legId);
    }

    private function originatorConversationId(int $legId, int $attempt, bool $retry): string
    {
        $prefix = $retry ? 'R' : 'A';
        return substr('KWL' . strtoupper(base_convert((string) $legId, 10, 36)) . $prefix . $attempt, 0, 20);
    }

    private function bankTransactionReference(int $legId, int $attempt): string
    {
        return substr('KW' . strtoupper(base_convert((string) $legId, 10, 36)) . 'A' . $attempt, 0, 12);
    }

    private function periodLabel(array $leg): string
    {
        return sprintf('%04d-%02d', (int) $leg['payroll_year'], (int) $leg['payroll_month']);
    }

    private function toCents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @return array<string,mixed>
     */
    private function ledgerRowFromLeg(array $leg): array
    {
        return [
            'payout_leg_id' => (int) $leg['id'],
            'payslip_id' => (int) $leg['payslip_id'],
            'payroll_id' => (int) ($leg['payroll_id'] ?? 0) ?: null,
            'staff_id' => (int) $leg['staff_id'],
            'staff_name' => $leg['staff_name'] ?? null,
            'staff_no' => $leg['staff_no'] ?? null,
            'payroll_month' => (int) $leg['payroll_month'],
            'payroll_year' => (int) $leg['payroll_year'],
            'method' => (string) $leg['method'],
            'method_label' => (string) $leg['method_label'],
            'source_financial_account_id' => (int) $leg['source_financial_account_id'],
            'source_account_name' => $leg['source_account_name'] ?? null,
            'source_account_identifier' => $leg['source_account_identifier'] ?? null,
            'amount' => (float) $leg['amount'],
            'reference' => $leg['reference'] ?? null,
            'provider_reference' => $leg['provider_reference'] ?? null,
            'status' => (string) $leg['status'],
            'transaction_status' => null,
            'reconciliation_status' => null,
            'attempt_no' => 0,
            'transaction_id' => null,
            'created_at' => $leg['created_at'] ?? null,
            'completed_at' => $leg['completed_at'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function ledgerRowFromTransaction(array $leg, array $transaction, int $attemptNo): array
    {
        $row = $this->ledgerRowFromLeg($leg);
        $row['amount'] = (float) $transaction['amount'];
        $row['provider_reference'] = $transaction['transaction_ref'] ?: ($transaction['transaction_id'] ?: null);
        $row['status'] = (string) $transaction['status'];
        $row['transaction_status'] = (string) $transaction['status'];
        $row['reconciliation_status'] = $transaction['reconciliation_status'] ?? null;
        $row['provider_status'] = $transaction['provider_status'] ?? null;
        $row['attempt_no'] = $attemptNo;
        $row['transaction_id'] = (int) $transaction['id'];
        $row['transaction_reference'] = $transaction['idempotency_reference'] ?? null;
        $row['created_at'] = $transaction['created_at'] ?? null;
        $row['completed_at'] = $transaction['completed_at'] ?? null;
        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function legacyLedgerRow(array $transaction, int $month, int $year): array
    {
        $sourceAccount = null;
        $accountId = (int) ($transaction['source_financial_account_id'] ?? 0);
        if ($accountId > 0) {
            $stmt = $this->db->prepare(
                "SELECT account_name, account_identifier FROM school_financial_accounts WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$accountId]);
            $sourceAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $channel = (string) ($transaction['channel'] ?? '');
        $method = match ($channel) {
            'mpesa_b2c' => 'mobile_money',
            'kcb_bank' => 'bank',
            'cash' => 'cash',
            default => $channel,
        };
        return [
            'payout_leg_id' => null,
            'payslip_id' => (int) ($transaction['payslip_id'] ?? 0) ?: null,
            'payroll_id' => (int) ($transaction['payroll_id'] ?? 0) ?: null,
            'staff_id' => (int) ($transaction['recipient_id'] ?? 0) ?: null,
            'staff_name' => $transaction['recipient_name'] ?? null,
            'staff_no' => null,
            'payroll_month' => $month,
            'payroll_year' => $year,
            'method' => $method,
            'method_label' => self::methodLabel($method),
            'source_financial_account_id' => $accountId ?: null,
            'source_account_name' => $sourceAccount['account_name'] ?? null,
            'source_account_identifier' => $sourceAccount['account_identifier'] ?? null,
            'amount' => (float) $transaction['amount'],
            'reference' => $transaction['idempotency_reference'] ?? null,
            'provider_reference' => $transaction['transaction_ref'] ?: ($transaction['transaction_id'] ?: null),
            'status' => (string) ($transaction['status'] ?? 'pending'),
            'transaction_status' => (string) ($transaction['status'] ?? 'pending'),
            'reconciliation_status' => $transaction['reconciliation_status'] ?? null,
            'provider_status' => $transaction['provider_status'] ?? null,
            'attempt_no' => 1,
            'transaction_id' => (int) $transaction['id'],
            'transaction_reference' => $transaction['idempotency_reference'] ?? null,
            'created_at' => $transaction['created_at'] ?? null,
            'completed_at' => $transaction['completed_at'] ?? null,
        ];
    }
}
