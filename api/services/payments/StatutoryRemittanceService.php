<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use RuntimeException;

/** Submits an approved statutory remittance and tracks its final callback. */
class StatutoryRemittanceService
{
    private $db;
    private $kcb;
    private $financialAccounts;

    public function __construct(?PDO $db = null, ?KcbFundsTransferService $kcb = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
        $this->kcb = $kcb ?: new KcbFundsTransferService();
        $this->financialAccounts = new FinancialAccountService($this->db);
    }

    public function initiate(int $remittanceId, int $userId, array $data): array
    {
        $stmt = $this->db->prepare("SELECT * FROM statutory_remittances WHERE id = ? LIMIT 1");
        $stmt->execute([$remittanceId]);
        $remittance = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$remittance) throw new RuntimeException('Statutory remittance not found.');

        $accountStmt = $this->db->prepare("SELECT * FROM statutory_agency_accounts WHERE id = ? AND agency = ? AND active = 1 LIMIT 1");
        $accountStmt->execute([(int) ($data['agency_account_id'] ?? 0), $remittance['agency']]);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new RuntimeException('A matching active agency payment account is required.');
        $source = $this->financialAccounts->requireFor((int) ($data['source_financial_account_id'] ?? 0), 'statutory', 'buni_transfer', true, $userId);

        $amount = (float) ($data['amount'] ?? ((float) $remittance['total_deducted'] - (float) $remittance['amount_remitted']));
        if ($amount <= 0) throw new RuntimeException('No outstanding statutory amount remains.');
        $reference = strtoupper(substr((string) ($data['payment_reference'] ?? ('TAX' . $remittance['agency'] . date('ym') . bin2hex(random_bytes(2)))), 0, 12));
        $idempotency = 'STAT-' . $remittanceId . '-' . sha1($reference);

        $providerId = $this->db->prepare("SELECT id FROM payment_providers WHERE code = 'kcb_buni' AND environment = ? LIMIT 1");
        $providerId->execute([defined('KCB_ENVIRONMENT') ? KCB_ENVIRONMENT : 'sandbox']);
        $provider = $providerId->fetchColumn() ?: null;
        $attempt = $this->db->prepare("INSERT INTO statutory_remittance_attempts (remittance_id, agency_account_id, source_financial_account_id, provider_id, idempotency_key, amount, channel, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'kcb_bank', 'created', ?)");
        $attempt->execute([$remittanceId, $account['id'], (int) $source['id'], $provider, $idempotency, $amount, $userId]);
        $attemptId = (int) $this->db->lastInsertId();

        $disbursement = $this->db->prepare("INSERT INTO disbursement_transactions (disbursement_type, payment_purpose, statutory_remittance_attempt_id, source_financial_account_id, idempotency_reference, recipient_name, amount, account_number, bank_code, bank_name, channel, status) VALUES ('other', 'statutory', ?, ?, ?, ?, ?, ?, ?, ?, 'kcb_bank', 'pending')");
        $disbursement->execute([$attemptId, (int) $source['id'], $idempotency, $account['account_name'], $amount, $account['account_number'], $account['bank_code'], $account['bank_name']]);
        $disbursementId = (int) $this->db->lastInsertId();

        $result = $this->kcb->transferFunds([
            'account_number' => $account['account_number'],
            'bank_name' => $account['bank_name'],
            'bank_code' => $account['bank_code'],
            'amount' => $amount,
            'narration' => substr($remittance['agency'] . ' statutory remittance ' . $reference, 0, 35),
            'beneficiary_name' => $account['account_name'],
            'transaction_reference' => $reference,
            'debit_account_number' => $source['account_identifier'],
            'source_financial_account_id' => (int) $source['id'],
            'idempotency_reference' => $idempotency,
        ]);
        $accepted = in_array($result['status'] ?? '', ['success', 'pending'], true);
        $this->db->prepare("UPDATE statutory_remittance_attempts SET status = ?, provider_reference = ?, request_payload = ?, response_payload = ? WHERE id = ?")
            ->execute([$accepted ? 'pending' : 'failed', $result['transaction_ref'] ?? $reference, json_encode($data), json_encode($result), $attemptId]);
        $this->db->prepare("UPDATE disbursement_transactions SET request_id = ?, transaction_ref = ?, status = ?, reconciliation_status = ?, next_status_inquiry_at = IF(? = 'pending', DATE_ADD(NOW(), INTERVAL 2 MINUTE), NULL), callback_data = ? WHERE id = ?")
            ->execute([$result['request_id'] ?? null, $result['transaction_ref'] ?? $reference, $accepted ? 'pending' : 'failed', $accepted ? 'awaiting_callback' : 'manual_review', $accepted ? 'pending' : 'failed', json_encode($result), $disbursementId]);
        if (!$accepted) (new KcbTransferReconciliationService($this->db, $this->kcb))->flagSubmissionException($disbursementId, (string) ($result['message'] ?? 'Submission failed.'));

        return ['status' => $accepted ? 'pending' : 'failed', 'attempt_id' => $attemptId, 'transaction_ref' => $result['transaction_ref'] ?? $reference];
    }
}
