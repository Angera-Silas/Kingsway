<?php
namespace App\API\Services\payments;

use PDO;
use RuntimeException;

/** Creates and submits approved parent overpayment refunds. */
class ParentRefundService
{
    private $db;
    private $mpesa;
    private $kcb;
    private $financialAccounts;

    public function __construct(PDO $db, ?MpesaB2CService $mpesa = null, ?KcbFundsTransferService $kcb = null)
    {
        $this->db = $db;
        $this->mpesa = $mpesa ?: new MpesaB2CService();
        $this->kcb = $kcb ?: new KcbFundsTransferService();
        $this->financialAccounts = new FinancialAccountService($this->db);
    }

    public function createRequest(int $creditId, int $userId, array $data): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, sp.parent_id FROM fee_credit_notes c
             JOIN student_parents sp ON sp.student_id = c.student_id
             WHERE c.id = ? AND c.status IN ('available','partially_applied')
             ORDER BY sp.is_primary_contact DESC, sp.parent_id ASC LIMIT 1"
        );
        $stmt->execute([$creditId]);
        $credit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$credit) throw new RuntimeException('Available fee credit or primary parent not found.');
        $amount = (float) ($data['amount'] ?? $credit['remaining_amount']);
        if ($amount <= 0 || $amount > (float) $credit['remaining_amount'] + 0.01) throw new RuntimeException('Refund amount exceeds the available credit.');
        $accountId = (int) ($data['parent_payment_account_id'] ?? 0);
        $account = $this->verifiedAccount((int) $credit['parent_id'], $accountId);
        if (!$account) throw new RuntimeException('Select a verified parent payment account.');
        $channel = $account['provider'] === 'mpesa' ? 'mpesa_b2c' : 'kcb_bank';
        $sourceChannel = $channel === 'mpesa_b2c' ? 'mpesa_b2c' : 'buni_transfer';
        $source = $this->financialAccounts->requireFor((int) ($data['source_financial_account_id'] ?? 0), 'refunds', $sourceChannel, true, $userId);
        $insert = $this->db->prepare("INSERT INTO parent_refund_requests (fee_credit_note_id, parent_id, parent_payment_account_id, source_financial_account_id, amount, reason, channel, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?)");
        $insert->execute([$creditId, $credit['parent_id'], $accountId, (int) $source['id'], $amount, $data['reason'] ?? 'Approved parent overpayment refund', $channel, $userId]);
        return ['id' => (int) $this->db->lastInsertId(), 'amount' => $amount, 'status' => 'pending_approval'];
    }

    public function submit(int $requestId, int $userId): array
    {
        $stmt = $this->db->prepare("SELECT r.*, a.provider, a.phone_number, a.bank_name, a.bank_code, a.account_name, a.account_number, sf.account_identifier AS source_account_identifier FROM parent_refund_requests r JOIN parent_payment_accounts a ON a.id = r.parent_payment_account_id JOIN school_financial_accounts sf ON sf.id = r.source_financial_account_id WHERE r.id = ? AND r.status = 'approved' AND a.active = 1 AND a.verification_status = 'verified' AND sf.status='active' LIMIT 1");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new RuntimeException('Refund must be approved and have a verified active destination.');
        $this->db->prepare("UPDATE parent_refund_requests SET status = 'processing' WHERE id = ?")->execute([$requestId]);
        if ($request['provider'] === 'mpesa') {
            $result = $this->mpesa->sendPayment(['phone' => $request['phone_number'], 'amount' => (float) $request['amount'], 'command_id' => 'BusinessPayment', 'remarks' => 'Parent fee refund', 'occasion' => 'Fee refund', 'recipient_id' => $request['parent_id'], 'recipient_name' => $request['account_name'], 'disbursement_type' => 'refund', 'payment_purpose' => 'refunds', 'source_financial_account_id' => (int) $request['source_financial_account_id'], 'idempotency_reference' => 'REFUND-' . $requestId, 'refund_request_id' => $requestId]);
            $find = $this->db->prepare("SELECT id FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? ORDER BY id DESC LIMIT 1");
            $find->execute([$result['transaction_ref'] ?? null, $result['originator_conversation_id'] ?? null]);
            $disbursementId = (int) ($find->fetchColumn() ?: 0);
            $this->db->prepare("UPDATE parent_refund_requests SET provider_reference = ?, disbursement_id = ? WHERE id = ?")->execute([$result['transaction_ref'] ?? null, $disbursementId ?: null, $requestId]);
            return ['status' => in_array($result['status'] ?? '', ['success','pending'], true) ? 'processing' : 'failed', 'provider_reference' => $result['transaction_ref'] ?? null];
        }
        $reference = strtoupper(substr('REF' . bin2hex(random_bytes(5)), 0, 12));
        $insert = $this->db->prepare("INSERT INTO disbursement_transactions (disbursement_type, payment_purpose, refund_request_id, source_financial_account_id, idempotency_reference, recipient_id, recipient_name, amount, account_number, bank_code, bank_name, channel, status) VALUES ('refund', 'refunds', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'kcb_bank', 'pending')");
        $insert->execute([$requestId, (int) $request['source_financial_account_id'], 'REFUND-' . $requestId, $request['parent_id'], $request['account_name'], $request['amount'], $request['account_number'], $request['bank_code'], $request['bank_name']]);
        $disbursementId = (int) $this->db->lastInsertId();
        $result = $this->kcb->transferFunds(['account_number' => $request['account_number'], 'bank_name' => $request['bank_name'], 'bank_code' => $request['bank_code'], 'amount' => (float) $request['amount'], 'narration' => 'Parent fee refund', 'beneficiary_name' => $request['account_name'], 'transaction_reference' => $reference, 'debit_account_number' => $request['source_account_identifier'], 'source_financial_account_id' => (int) $request['source_financial_account_id'], 'idempotency_reference' => 'REFUND-' . $requestId]);
        $accepted = in_array($result['status'] ?? '', ['success','pending'], true);
        $this->db->prepare("UPDATE disbursement_transactions SET request_id = ?, transaction_ref = ?, status = ?, result_description = ?, callback_data = ? WHERE id = ?")->execute([$result['request_id'] ?? null, $result['transaction_ref'] ?? $reference, $accepted ? 'pending' : 'failed', $result['message'] ?? null, json_encode($result), $disbursementId]);
        $this->db->prepare("UPDATE parent_refund_requests SET status = ?, provider_reference = ?, disbursement_id = ? WHERE id = ?")->execute([$accepted ? 'processing' : 'failed', $result['transaction_ref'] ?? $reference, $disbursementId, $requestId]);
        return ['status' => $accepted ? 'processing' : 'failed', 'provider_reference' => $result['transaction_ref'] ?? $reference];
    }

    private function verifiedAccount(int $parentId, int $accountId): ?array
    {
        $sql = "SELECT * FROM parent_payment_accounts WHERE parent_id = ? AND active = 1 AND verification_status = 'verified'";
        $params = [$parentId];
        if ($accountId) { $sql .= ' AND id = ?'; $params[] = $accountId; } else $sql .= ' ORDER BY is_primary DESC, id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
