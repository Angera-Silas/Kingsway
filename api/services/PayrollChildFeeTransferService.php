<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;
use PDO;
use Throwable;

/**
 * Posts the employee-authorised school-fee portion of a fully settled salary.
 *
 * Salary disbursement and fee allocation remain separate operations. The
 * service is idempotent by receipt number and can safely retry a transfer that
 * failed after the employee's complete salary was settled.
 */
final class PayrollChildFeeTransferService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    /**
     * Post every pending transfer and retry any failed transfer for a paid
     * payslip.
     *
     * @return array{posted:int,failed:int,attempted:int,message:string}
     */
    public function postForPayslip(int $payslipId): array
    {
        $payslip = $this->payslip($payslipId);
        if (!$payslip) {
            return ['posted' => 0, 'failed' => 0, 'attempted' => 0, 'message' => 'Payslip not found'];
        }
        if ((string) ($payslip['payment_status'] ?? '') !== 'paid') {
            return [
                'posted' => 0,
                'failed' => 0,
                'attempted' => 0,
                'message' => 'Salary is not fully settled',
            ];
        }

        return $this->postTransfers($payslipId, ['pending', 'failed']);
    }

    /**
     * Explicit retry entry point for fee transfers that previously failed.
     *
     * @return array{posted:int,failed:int,attempted:int,message:string}
     */
    public function retryFailedForPayslip(int $payslipId): array
    {
        $payslip = $this->payslip($payslipId);
        if (!$payslip) {
            return ['posted' => 0, 'failed' => 0, 'attempted' => 0, 'message' => 'Payslip not found'];
        }
        if ((string) ($payslip['payment_status'] ?? '') !== 'paid') {
            return [
                'posted' => 0,
                'failed' => 0,
                'attempted' => 0,
                'message' => 'Salary is not fully settled',
            ];
        }

        return $this->postTransfers($payslipId, ['failed']);
    }

    /**
     * @param array<int,string> $statuses
     * @return array{posted:int,failed:int,attempted:int,message:string}
     */
    private function postTransfers(int $payslipId, array $statuses): array
    {
        $statuses = array_values(array_unique(array_filter($statuses)));
        if (!$statuses) {
            return ['posted' => 0, 'failed' => 0, 'attempted' => 0, 'message' => 'No transfer states selected'];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $candidateStmt = $this->db->prepare(
            "SELECT id FROM payroll_child_fee_transfers
             WHERE payslip_id = ? AND status IN ($placeholders)
             ORDER BY id"
        );
        $candidateStmt->execute(array_merge([$payslipId], $statuses));
        $candidateIds = array_map('intval', $candidateStmt->fetchAll(PDO::FETCH_COLUMN));

        $posted = 0;
        $failed = 0;
        foreach ($candidateIds as $transferId) {
            if ($this->postTransfer($transferId)) {
                $posted++;
            } else {
                $failed++;
            }
        }

        return [
            'posted' => $posted,
            'failed' => $failed,
            'attempted' => count($candidateIds),
            'message' => $failed > 0
                ? 'Some child-fee transfers could not be posted and remain available for retry.'
                : 'Child-fee transfers are up to date.',
        ];
    }

    private function postTransfer(int $transferId): bool
    {
        // Begin the transaction before taking the row lock. MariaDB otherwise
        // ends the lock as soon as the statement finishes, which allowed two
        // callbacks to process the same transfer concurrently.
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                "SELECT id, payslip_id, student_id, amount, receipt_no, status
                 FROM payroll_child_fee_transfers
                 WHERE id = ? AND status IN ('pending','failed')
                 FOR UPDATE"
            );
            $lock->execute([$transferId]);
            $transfer = $lock->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$transfer) {
                $this->db->commit();
                return false;
            }

            $receiptNo = trim((string) ($transfer['receipt_no'] ?? ''));
            if ($receiptNo === '') {
                throw new \RuntimeException('The payroll child-fee transfer has no receipt number.');
            }

            $existingPayment = $this->db->prepare(
                'SELECT id FROM payments WHERE receipt_no = ? LIMIT 1'
            );
            $existingPayment->execute([$receiptNo]);
            $existingPaymentId = (int) ($existingPayment->fetchColumn() ?: 0);
            if ($existingPaymentId > 0) {
                $this->db->prepare(
                    "UPDATE payroll_child_fee_transfers
                     SET status='posted', payment_id=?, posted_at=COALESCE(posted_at,NOW()),
                         failure_reason=NULL, updated_at=NOW()
                     WHERE id=? AND status IN ('pending','failed')"
                )->execute([$existingPaymentId, $transferId]);
                $this->db->commit();
                return true;
            }

            $parentId = $this->primaryGuardianId((int) $transfer['student_id']);
            $this->db->prepare(
                "UPDATE payroll_child_fee_transfers
                 SET status='pending', failure_reason=NULL, updated_at=NOW()
                 WHERE id=?"
            )->execute([$transferId]);

            $procedure = $this->db->prepare(
                'CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $procedure->execute([
                (int) $transfer['student_id'],
                $parentId,
                (float) $transfer['amount'],
                'other',
                $receiptNo,
                $receiptNo,
                1,
                date('Y-m-d H:i:s'),
                'Employee-authorised school-fee deduction from fully settled salary',
            ]);
            $procedure->closeCursor();

            $payment = $this->db->prepare(
                'SELECT id FROM payments WHERE receipt_no = ? LIMIT 1'
            );
            $payment->execute([$receiptNo]);
            $paymentId = (int) ($payment->fetchColumn() ?: 0);
            if ($paymentId <= 0) {
                throw new \RuntimeException('The child-fee payment procedure did not create a receipt.');
            }

            $this->db->prepare(
                "UPDATE payroll_child_fee_transfers
                 SET status='posted', payment_id=?, posted_at=NOW(), failure_reason=NULL,
                     updated_at=NOW()
                 WHERE id=? AND status='pending'"
            )->execute([$paymentId, $transferId]);
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();

            // Record the failure outside the rolled-back transaction so the
            // operator can see it and retry it safely after salary settlement.
            $this->db->prepare(
                "UPDATE payroll_child_fee_transfers
                 SET status='failed', failure_reason=?, updated_at=NOW()
                 WHERE id=? AND status IN ('pending','failed')"
            )->execute([substr($error->getMessage(), 0, 500), $transferId]);
            \App\API\Services\Logger::legacyError(
                '[PayrollChildFeeTransferService] ' . $error->getMessage()
            );
            return false;
        }
    }

    private function primaryGuardianId(int $studentId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT parent_id
             FROM student_parents
             WHERE student_id = ?
             ORDER BY is_primary_contact DESC, parent_id ASC
             LIMIT 1"
        );
        $stmt->execute([$studentId]);
        $parentId = (int) ($stmt->fetchColumn() ?: 0);
        return $parentId > 0 ? $parentId : null;
    }

    private function payslip(int $payslipId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, staff_id, payroll_month, payroll_year, payment_status
             FROM payslips WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$payslipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
