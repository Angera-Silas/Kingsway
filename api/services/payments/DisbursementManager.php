<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use Exception;

/**
 * DisbursementManager
 *
 * Coordinates all outgoing payments (staff salaries, supplier payments,
 * refunds) across M-Pesa B2C, bank transfer and cash.
 *
 * Rebuilt against the live KingsWayAcademy schema:
 *  - payroll_runs / payslips / staff / persons
 *  - disbursement_transactions (callback matching)
 *  - mpesa_transactions (transaction_type B2C)
 *
 * Every initiation is recorded so the M-Pesa B2C Result callback and KCB
 * transfer callback can be matched back to the payslip and applied.
 */
class DisbursementManager
{
    /** @var PDO */
    private $db;

    /** @var MpesaB2CService */
    private $mpesaB2C;

    /** @var KcbFundsTransferService */
    private $kcbTransfer;

    /** @var MpesaPaymentService */
    private $mpesaPayment;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->mpesaB2C = new MpesaB2CService();
        $this->kcbTransfer = new KcbFundsTransferService();
        $this->mpesaPayment = new MpesaPaymentService();
    }

    /**
     * Process payroll disbursement — called once a payroll is approved.
     */
    public function processPayrollDisbursement($payrollId, $approvedBy)
    {
        try {
            $payroll = $this->db->prepare(
                "SELECT * FROM payroll_runs WHERE id = ? AND status = 'approved' LIMIT 1"
            );
            $payroll->execute([$payrollId]);
            $payrollRow = $payroll->fetch(PDO::FETCH_ASSOC);

            if (!$payrollRow) {
                throw new Exception("Payroll not found or not approved");
            }
            if ($payrollRow['status'] === 'processing') {
                throw new Exception("Payroll disbursement already in progress");
            }

            $staffPayments = $this->db->prepare(
                "SELECT ps.*, p.first_name, p.last_name, p.phone AS phone_number,
                        st.bank_account AS bank_account_number, st.bank_name,
                        ps.payment_method
                 FROM payslips ps
                 JOIN staff st ON ps.staff_id = st.id
                 JOIN persons p ON p.id = st.person_id
                 WHERE ps.payroll_month = ? AND ps.payroll_year = ?
                   AND ps.payslip_status = 'approved'
                   AND ps.payment_status IN ('pending', 'failed')"
            );
            $staffPayments->execute([$payrollRow['month'], $payrollRow['year']]);
            $staffPayments = $staffPayments->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staffPayments)) {
                throw new Exception("No pending payments found for this payroll");
            }

            $totalAmount = array_sum(array_column($staffPayments, 'net_salary'));

            if (!$this->verifyAvailableBalance($totalAmount)) {
                throw new Exception(
                    "Insufficient balance to process payroll. Required: KES " . number_format($totalAmount, 2)
                );
            }

            $this->db->prepare(
                "UPDATE payroll_runs SET status = 'processing' WHERE id = ?"
            )->execute([$payrollId]);

            $results = $this->processBulkDisbursements($staffPayments, $payrollId);

            $this->updatePayrollDisbursementStatus($payrollId, $results);

            return [
                'success' => true,
                'payroll_id' => $payrollId,
                'total_staff' => count($staffPayments),
                'total_amount' => $totalAmount,
                'results' => $results,
            ];
        } catch (Exception $e) {
            error_log('[DisbursementManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Process bulk disbursements (loop through staff).
     */
    private function processBulkDisbursements($staffPayments, $payrollId)
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'pending' => 0,
            'details' => [],
        ];

        foreach ($staffPayments as $payment) {
            try {
                $result = $this->processSingleDisbursement($payment, $payrollId);

                if ($result['status'] === 'success') {
                    $results['successful']++;
                } elseif ($result['status'] === 'pending') {
                    $results['pending']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                    'amount' => $payment['net_salary'],
                    'method' => $payment['payment_method'],
                    'status' => $result['status'],
                    'transaction_ref' => $result['transaction_ref'] ?? null,
                    'message' => $result['message'] ?? '',
                ];
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                    'amount' => $payment['net_salary'],
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                $this->logError("Failed to disburse to staff {$payment['staff_id']}: " . $e->getMessage());
            }

            usleep(500000);
        }

        return $results;
    }

    /**
     * Process a single staff payment by payment method.
     */
    private function processSingleDisbursement($payment, $payrollId = null)
    {
        $method = strtolower((string) ($payment['payment_method'] ?? 'bank'));

        switch ($method) {
            case 'mpesa':
            case 'm-pesa':
            case 'mobile_money':
                return $this->disburseMpesa($payment, $payrollId);

            case 'bank':
            case 'bank_transfer':
                return $this->disburseBank($payment, $payrollId);

            case 'cash':
            case 'check':
                return $this->disburseCash($payment);

            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }

    /**
     * Disburse via M-Pesa B2C.
     */
    private function disburseMpesa($payment, $payrollId = null)
    {
        $phone = $this->formatPhoneNumber((string) ($payment['phone_number'] ?? ''));
        if (!$phone) {
            throw new Exception("Invalid phone number for staff {$payment['staff_id']}");
        }

        $result = $this->mpesaB2C->sendPayment([
            'phone' => $phone,
            'amount' => (float) $payment['net_salary'],
            'command_id' => 'SalaryPayment',
            'remarks' => 'Salary payment for ' . date('F Y'),
            'occasion' => 'Staff Salary',
            'recipient_id' => $payment['staff_id'],
            'recipient_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
            'payroll_id' => $payrollId,
            'payslip_id' => $payment['id'],
            'disbursement_type' => 'salary',
        ]);

        $status = $result['status'] === 'success' ? 'processing' : 'failed';
        $this->db->prepare(
            "UPDATE payslips
             SET payment_status = ?, payment_reference = ?, paid_at = IF(? = 'processing', NOW(), paid_at),
                 notes = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $status,
            $result['transaction_ref'] ?? null,
            $status,
            json_encode($result),
            $payment['id'],
        ]);

        return $result;
    }

    /**
     * Disburse via KCB bank transfer.
     */
    private function disburseBank($payment, $payrollId = null)
    {
        if (empty($payment['bank_account_number']) || empty($payment['bank_name'])) {
            throw new Exception("Missing bank details for staff {$payment['staff_id']}");
        }

        $result = $this->kcbTransfer->transferFunds([
            'account_number' => $payment['bank_account_number'],
            'bank_name' => $payment['bank_name'],
            'amount' => (float) $payment['net_salary'],
            'narration' => 'Salary payment for ' . date('F Y'),
            'beneficiary_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
            'recipient_id' => $payment['staff_id'],
            'payroll_id' => $payrollId,
            'payslip_id' => $payment['id'],
            'disbursement_type' => 'salary',
        ]);

        $status = $result['status'] === 'success' ? 'processing' : 'failed';
        $this->db->prepare(
            "UPDATE payslips
             SET payment_status = ?, payment_reference = ?, paid_at = IF(? = 'processing', NOW(), paid_at),
                 notes = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $status,
            $result['transaction_ref'] ?? null,
            $status,
            json_encode($result),
            $payment['id'],
        ]);

        return $result;
    }

    /**
     * Mark as cash/cheque payment (manual collection).
     */
    private function disburseCash($payment)
    {
        $this->db->prepare(
            "UPDATE payslips SET payment_status = 'pending', updated_at = NOW() WHERE id = ?"
        )->execute([$payment['id']]);

        return [
            'status' => 'pending',
            'message' => 'Marked for manual collection',
            'transaction_ref' => null,
        ];
    }

    /**
     * Retry a failed payment.
     */
    public function retryFailedPayment($staffPaymentId)
    {
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name, p.phone AS phone_number,
                    st.bank_account AS bank_account_number, st.bank_name, ps.payment_method
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             WHERE ps.id = ? AND ps.payment_status = 'failed'"
        );
        $stmt->execute([$staffPaymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Payment not found or not in failed status");
        }

        $this->db->prepare(
            "UPDATE payslips SET notes = CONCAT(COALESCE(notes, ''), '\n[RETRY] ', NOW()), updated_at = NOW() WHERE id = ?"
        )->execute([$staffPaymentId]);

        return $this->processSingleDisbursement($payment);
    }

    /**
     * Best-effort balance gate. The M-Pesa account balance is delivered
     * asynchronously (AccountBalance API result callback); we only hard-block
     * when a recorded balance result exists and is below the required amount.
     * When no balance record is available we log a warning and proceed —
     * disbursements are still reconciled by the B2C callback.
     */
    private function verifyAvailableBalance($requiredAmount)
    {
        try {
            // Latest M-Pesa result callbacks are mirrored into the file-based
            // payments log (previously payment_webhooks_log, which was dropped).
            $latestBalance = null;
            foreach (\App\API\Includes\FileLogger::recent('payments', 200) as $entry) {
                if (($entry['source'] ?? null) !== 'mpesa_result') {
                    continue;
                }
                $decoded = $entry['webhook_data'] ?? null;
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (!is_array($decoded)) {
                    continue;
                }
                $params = $decoded['Result']['ResultParameters']['ResultParameter'] ?? [];
                foreach ($params as $param) {
                    if (($param['Key'] ?? '') === 'AccountBalance') {
                        $parts = explode('&', (string) $param['Value']);
                        $latestBalance = (float) ($parts[0] ?? 0);
                        break 2;
                    }
                }
            }

            if ($latestBalance === null) {
                error_log('[DisbursementManager] Balance not yet known (async result). Proceeding with disbursement.');
                return true;
            }
            return $latestBalance >= $requiredAmount;
        } catch (Exception $e) {
            error_log('[DisbursementManager] balance check error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Update the payroll status after disbursement.
     * payroll_runs.status enum: draft | processing | approved | paid.
     */
    private function updatePayrollDisbursementStatus($payrollId, $results)
    {
        if ($results['failed'] === 0) {
            $status = 'paid';
        } else {
            $status = 'processing';
        }

        $this->db->prepare(
            "UPDATE payroll_runs SET status = ? WHERE id = ?"
        )->execute([$status, $payrollId]);
    }

    /**
     * Disbursement report for a payroll.
     */
    public function getDisbursementReport($payrollId)
    {
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name, st.staff_no AS employee_number,
                    ps.payment_method, ps.payment_status AS status
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             WHERE ps.payroll_month = (SELECT month FROM payroll_runs WHERE id = ?)
               AND ps.payroll_year = (SELECT year FROM payroll_runs WHERE id = ?)
             ORDER BY ps.payment_status DESC, p.last_name ASC"
        );
        $stmt->execute([$payrollId, $payrollId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Failed payments for retry.
     */
    public function getFailedPayments($payrollId)
    {
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             WHERE ps.payroll_month = (SELECT month FROM payroll_runs WHERE id = ?)
               AND ps.payroll_year = (SELECT year FROM payroll_runs WHERE id = ?)
               AND ps.payment_status = 'failed'"
        );
        $stmt->execute([$payrollId, $payrollId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Format a phone number to 254XXXXXXXXX, or null if invalid.
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (strlen($phone) === 10 && $phone[0] === '0') {
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            return '254' . $phone;
        } elseif (strlen($phone) === 12 && strpos($phone, '254') === 0) {
            return $phone;
        }

        return null;
    }

    private function logError($message)
    {
        $logFile = __DIR__ . '/../../../logs/disbursement_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $logFile);
    }
}
