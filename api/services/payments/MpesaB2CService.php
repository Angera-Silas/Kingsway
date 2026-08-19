<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use Exception;

/**
 * MpesaB2CService
 *
 * M-Pesa Business-to-Customer (B2C) disbursements — staff salaries,
 * supplier payments and refunds.
 *
 * Rebuilt against the official Daraja spec:
 *   POST /mpesa/b2c/v1/paymentrequest
 * and the live KingsWayAcademy schema. Every initiation is recorded into
 * mpesa_transactions (transaction_type B2C) and disbursement_transactions so
 * the Result callback can be matched and applied.
 */
class MpesaB2CService
{
    /** @var PDO|null */
    private $db;

    /** @var MpesaApiClient */
    private $client;

    public function __construct()
    {
        $this->client = new MpesaApiClient();
    }

    private function getDb(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getInstance()->getConnection();
        }
        return $this->db;
    }

    /**
     * Send a B2C payment.
     *
     * @param array $data {
     *   phone: 254XXXXXXXXX (required),
     *   amount: float (required),
     *   command_id: BusinessPayment|SalaryPayment|PromotionPayment (default BusinessPayment),
     *   remarks: string,
     *   occasion: string,
     *   recipient_id / recipient_name / payslip_id / payroll_id / disbursement_type:
     *     optional metadata recorded for callback matching.
     * }
     * @return array {status, message, transaction_ref, originator_conversation_id, response}
     */
    public function sendPayment($data)
    {
        try {
            $idempotency = trim((string) ($data['idempotency_reference'] ?? ''));
            if ($idempotency !== '') {
                $existing = $this->getDb()->prepare('SELECT status,transaction_ref,request_id,result_description FROM disbursement_transactions WHERE idempotency_reference=? LIMIT 1');
                $existing->execute([$idempotency]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                if ($row) return ['status' => in_array($row['status'], ['pending','completed'], true) ? 'pending' : $row['status'], 'message' => 'Existing idempotent disbursement reused.', 'transaction_ref' => $row['transaction_ref'], 'request_id' => $row['request_id']];
            }
            $phone = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));
            if (strlen($phone) === 9) {
                $phone = '254' . $phone;
            } elseif (strlen($phone) === 10 && $phone[0] === '0') {
                $phone = '254' . substr($phone, 1);
            }
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                throw new Exception('Invalid phone number. Use 254XXXXXXXXX');
            }

            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than zero');
            }

            $commandId = $data['command_id'] ?? 'BusinessPayment';
            $remarks = $data['remarks'] ?? 'Payment';
            $occasion = $data['occasion'] ?? '';

            $payload = [
                'InitiatorName'     => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'         => $commandId,
                'Amount'            => (int) $amount,
                'PartyA'            => $this->client->getShortcode(),
                'PartyB'            => $phone,
                'Remarks'           => $remarks,
                'QueueTimeOutURL'   => $this->callbackUrl('/api/payments/mpesa-b2c-timeout'),
                'ResultURL'         => $this->callbackUrl('/api/payments/mpesa-b2c-callback'),
                'Occasion'          => $occasion,
            ];

            $response = $this->client->post('/mpesa/b2c/v1/paymentrequest', $payload);

            $originatorId = $response['OriginatorConversationID'] ?? null;
            $conversationId = $response['ConversationID'] ?? null;
            $responseCode = $response['ResponseCode'] ?? '1';

            $this->recordDisbursement($data, $phone, $amount, $commandId, $originatorId, $conversationId, $payload, $response);

            if ($responseCode !== '0') {
                throw new Exception($response['ResponseDescription'] ?? 'B2C request failed');
            }

            return [
                'status' => 'success',
                'message' => 'Payment request sent successfully',
                'transaction_ref' => $conversationId,
                'originator_conversation_id' => $originatorId,
                'response' => $response,
            ];
        } catch (Exception $e) {
            error_log('[MpesaB2CService] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'transaction_ref' => null,
                'originator_conversation_id' => null,
                'response' => null,
            ];
        }
    }

    /**
     * Ask M-Pesa for the paybill account balance. The actual balance arrives
     * asynchronously via the Result callback; this only submits the request and
     * returns whether it was accepted. Callers must not treat this as a
     * synchronous balance figure.
     */
    public function checkAccountBalance()
    {
        try {
            $payload = [
                'Initiator'          => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'          => 'AccountBalance',
                'PartyA'             => $this->client->getShortcode(),
                'IdentifierType'     => 4,
                'Remarks'            => 'Balance check before disbursement',
                'QueueTimeOutURL'    => $this->callbackUrl('/api/payments/mpesa-result'),
                'ResultURL'          => $this->callbackUrl('/api/payments/mpesa-result'),
            ];
            $response = $this->client->post('/mpesa/accountbalance/v1/query', $payload);
            return ($response['ResponseCode'] ?? '1') === '0';
        } catch (Exception $e) {
            error_log('[MpesaB2CService] balance check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record the B2C initiation for callback matching.
     */
    private function recordDisbursement(array $data, string $phone, float $amount, string $commandId, $originatorId, $conversationId, array $requestPayload, array $response): void
    {
        try {
            $this->getDb()->prepare(
                "INSERT INTO disbursement_transactions
                    (disbursement_type, payroll_id, payslip_id, refund_request_id, recipient_id, recipient_name,
                     payment_purpose, source_financial_account_id, idempotency_reference, amount, phone_number, channel, conversation_id, originator_conversation_id,
                     transaction_ref, status, result_description, callback_data, created_at)
                 VALUES (:dtype, :payroll_id, :payslip_id, :refund_request_id, :recipient_id, :recipient_name,
                         :purpose, :source_account_id, :idempotency_reference, :amount, :phone, 'mpesa_b2c', :conv, :originator,
                         :txref, 'pending', :desc, :callback, NOW())"
            )->execute([
                'dtype'       => $data['disbursement_type'] ?? 'salary',
                'payroll_id'  => $data['payroll_id'] ?? null,
                'payslip_id'  => $data['payslip_id'] ?? null,
                'refund_request_id' => $data['refund_request_id'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'purpose' => $data['payment_purpose'] ?? ($data['disbursement_type'] ?? 'operations'),
                'source_account_id' => $data['source_financial_account_id'] ?? null,
                'idempotency_reference' => $data['idempotency_reference'] ?? null,
                'amount'      => $amount,
                'phone'       => $phone,
                'conv'        => $conversationId,
                'originator'  => $originatorId,
                'txref'       => $conversationId,
                'desc'        => $response['ResponseDescription'] ?? null,
                'callback'    => json_encode($response),
            ]);

            $this->getDb()->prepare(
                "INSERT INTO mpesa_transactions
                    (mpesa_code, student_id, amount, transaction_date, phone_number,
                     bill_ref_number, status, transaction_type, raw_callback, webhook_data, created_at)
                 VALUES (:code, NULL, :amount, NOW(), :phone, :billref, 'pending', 'B2C', :raw, :webhook, NOW())
                 ON DUPLICATE KEY UPDATE webhook_data = VALUES(webhook_data)"
            )->execute([
                'code'    => 'B2C-' . ($originatorId ?: bin2hex(random_bytes(6))),
                'amount'  => $amount,
                'phone'   => $phone,
                'billref' => $data['recipient_name'] ?? 'B2C-' . $commandId,
                'raw'     => json_encode($requestPayload),
                'webhook' => json_encode($response),
            ]);
        } catch (Exception $e) {
            error_log('[MpesaB2CService] recordDisbursement error: ' . $e->getMessage());
        }
    }

    private function callbackUrl(string $endpoint): string
    {
        $base = defined('MPESA_CALLBACK_BASE_URL') && MPESA_CALLBACK_BASE_URL !== ''
            ? MPESA_CALLBACK_BASE_URL
            : (defined('BASE_URL') ? BASE_URL : '');
        return $base !== '' ? $base . $endpoint : $endpoint;
    }
}
