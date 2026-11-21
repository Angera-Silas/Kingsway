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

    public function __construct(?PDO $db = null, ?MpesaApiClient $client = null)
    {
        $this->db = $db;
        $this->client = $client ?: new MpesaApiClient();
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
        $reservedTransactionId = 0;
        $originatorId = trim((string) ($data['originator_conversation_id'] ?? ''));
        $mpesaCode = '';

        try {
            $idempotency = trim((string) ($data['idempotency_reference'] ?? ''));
            if ($idempotency !== '') {
                $existing = $this->getDb()->prepare(
                    'SELECT id,status,transaction_ref,request_id,result_description,payroll_payout_leg_id
                     FROM disbursement_transactions WHERE idempotency_reference=? LIMIT 1'
                );
                $existing->execute([$idempotency]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $status = (string) ($row['status'] ?? 'pending');
                    return [
                        'status' => $status === 'completed' ? 'success' : ($status === 'failed' ? 'error' : 'pending'),
                        'message' => 'Existing idempotent disbursement reused.',
                        'transaction_ref' => $row['transaction_ref'],
                        'request_id' => $row['request_id'],
                        'payroll_payout_leg_id' => $row['payroll_payout_leg_id'] ?: null,
                        'response' => null,
                    ];
                }
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
            if ($originatorId === '') {
                $originatorId = 'KWA' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
            }
            $originatorId = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $originatorId), 0, 20);
            $partyA = $this->client->getShortcode();
            $sourceAccountId = (int)($data['source_financial_account_id'] ?? $data['source_account_id'] ?? 0);
            if ($sourceAccountId > 0) {
                $source = $this->getDb()->prepare("SELECT account_identifier,provider_id FROM school_financial_accounts WHERE id=? AND status='active' LIMIT 1");
                $source->execute([$sourceAccountId]);
                $sourceRow = $source->fetch(PDO::FETCH_ASSOC);
                if (!$sourceRow || trim((string)$sourceRow['account_identifier']) === '') {
                    throw new Exception('Selected M-Pesa source account is inactive or has no identifier.');
                }
                $partyA = trim((string)$sourceRow['account_identifier']);
            }

            $payload = [
                'OriginatorConversationID' => $originatorId,
                'InitiatorName'     => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'         => $commandId,
                'Amount'            => (int) $amount,
                'PartyA'            => $partyA,
                'PartyB'            => $phone,
                'Remarks'           => $remarks,
                'QueueTimeOutURL'   => $this->callbackUrl('/api/payments/mpesa-b2c-timeout'),
                'ResultURL'         => $this->callbackUrl('/api/payments/mpesa-b2c-callback'),
                'Occasion'          => $occasion,
            ];

            // Reserve exactly one linked disbursement row before contacting
            // Safaricom. The durable originator ID lets every callback find the
            // attempt even when the provider response is delayed or ambiguous.
            [$reservedTransactionId, $created] = $this->reserveDisbursement(
                $data,
                $phone,
                $amount,
                $originatorId,
                $payload
            );
            if (!$created) {
                $existing = $this->getDb()->prepare(
                    'SELECT status,transaction_ref,request_id,result_description,payroll_payout_leg_id
                     FROM disbursement_transactions WHERE id=? LIMIT 1'
                );
                $existing->execute([$reservedTransactionId]);
                $row = $existing->fetch(PDO::FETCH_ASSOC) ?: [];
                $status = (string) ($row['status'] ?? 'pending');
                return [
                    'status' => $status === 'completed' ? 'success' : ($status === 'failed' ? 'error' : 'pending'),
                    'message' => 'Existing idempotent disbursement reused.',
                    'transaction_ref' => $row['transaction_ref'] ?? null,
                    'request_id' => $row['request_id'] ?? null,
                    'payroll_payout_leg_id' => $row['payroll_payout_leg_id'] ?? null,
                    'response' => null,
                ];
            }

            $mpesaCode = 'B2C-' . $originatorId;
            $this->recordMpesaAttempt($mpesaCode, $amount, $phone, $data, $payload);

            try {
                $response = $this->client->post('/mpesa/b2c/v3/paymentrequest', $payload);
            } catch (Exception $providerError) {
                // The request may have reached Safaricom even when the HTTP
                // response was lost. Keep the attempt pending and require
                // reconciliation instead of creating a second payment.
                $this->getDb()->prepare(
                    "UPDATE disbursement_transactions
                     SET status='pending', reconciliation_status='manual_review',
                         provider_status='SUBMISSION_UNCERTAIN', result_description=?,
                         callback_data=?, next_status_inquiry_at=NULL
                     WHERE id=? AND status NOT IN ('completed','failed','cancelled')"
                )->execute([
                    'M-Pesa submission outcome requires reconciliation: ' . $providerError->getMessage(),
                    json_encode(['request' => $payload, 'error' => $providerError->getMessage()]),
                    $reservedTransactionId,
                ]);
                if ($mpesaCode !== '') {
                    $this->getDb()->prepare(
                        "UPDATE mpesa_transactions SET status='pending', webhook_data=? WHERE mpesa_code=?"
                    )->execute([
                        json_encode(['status' => 'submission_uncertain', 'error' => $providerError->getMessage()]),
                        $mpesaCode,
                    ]);
                }

                \App\API\Services\Logger::legacyError('[MpesaB2CService] submission uncertain: ' . $providerError->getMessage());
                return [
                    'status' => 'pending',
                    'message' => 'M-Pesa payment outcome requires reconciliation.',
                    'transaction_ref' => null,
                    'originator_conversation_id' => $originatorId,
                    'response' => null,
                ];
            }

            $response = is_array($response) ? $response : [];
            $responseOriginatorId = $response['OriginatorConversationID'] ?? $originatorId;
            $conversationId = $response['ConversationID'] ?? null;
            $responseCode = (string) ($response['ResponseCode'] ?? '1');

            if ($responseCode !== '0') {
                $description = (string)($response['ResponseDescription'] ?? $response['errorMessage'] ?? 'B2C request rejected by provider');
                $this->getDb()->prepare(
                    "UPDATE disbursement_transactions
                     SET status='failed', reconciliation_status='confirmed_failure',
                         provider_status=?, result_description=?, callback_data=?,
                         failed_at=NOW()
                     WHERE id=? AND status NOT IN ('completed','cancelled')"
                )->execute([
                    $responseCode,
                    $description,
                    json_encode($response),
                    $reservedTransactionId,
                ]);
                if ($mpesaCode !== '') {
                    $this->getDb()->prepare(
                        "UPDATE mpesa_transactions SET status='failed', webhook_data=? WHERE mpesa_code=?"
                    )->execute([json_encode($response), $mpesaCode]);
                }

                return [
                    'status' => 'error',
                    'message' => $description,
                    'transaction_ref' => null,
                    'originator_conversation_id' => $responseOriginatorId,
                    'response' => $response,
                ];
            }

            $this->getDb()->prepare(
                "UPDATE disbursement_transactions
                 SET status='pending', reconciliation_status='awaiting_callback',
                     provider_status=?, conversation_id=?, originator_conversation_id=?,
                     transaction_ref=?, result_description=?, callback_data=?
                 WHERE id=? AND status NOT IN ('completed','failed','cancelled')"
            )->execute([
                $responseCode,
                $conversationId,
                $responseOriginatorId,
                $conversationId,
                $response['ResponseDescription'] ?? null,
                json_encode($response),
                $reservedTransactionId,
            ]);
            if ($mpesaCode !== '') {
                $this->getDb()->prepare(
                    "UPDATE mpesa_transactions SET status='pending', webhook_data=? WHERE mpesa_code=?"
                )->execute([json_encode($response), $mpesaCode]);
            }

            return [
                'status' => 'success',
                'message' => 'Payment request sent successfully',
                'transaction_ref' => $conversationId,
                'originator_conversation_id' => $responseOriginatorId,
                'callback_urls' => [
                    'queue_timeout_url' => $payload['QueueTimeOutURL'],
                    'result_url' => $payload['ResultURL'],
                ],
                'response' => $response,
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaB2CService] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if ($reservedTransactionId > 0) {
                try {
                    $this->getDb()->prepare(
                        "UPDATE disbursement_transactions
                         SET status='pending', reconciliation_status='manual_review',
                             provider_status='SUBMISSION_UNCERTAIN', result_description=?
                         WHERE id=? AND status NOT IN ('completed','failed','cancelled')"
                    )->execute([$e->getMessage(), $reservedTransactionId]);
                } catch (Exception $ignored) {
                    // Preserve the original failure. The reserved row remains
                    // visible for reconciliation even if this update also fails.
                }
                return [
                    'status' => 'pending',
                    'message' => 'M-Pesa payment outcome requires reconciliation.',
                    'transaction_ref' => null,
                    'originator_conversation_id' => $originatorId ?: null,
                    'response' => null,
                ];
            }
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
            \App\API\Services\Logger::legacyError('[MpesaB2CService] balance check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reserve the durable disbursement row before the provider call.
     *
     * @return array{0:int,1:bool} transaction id and whether this call created it
     */
    private function reserveDisbursement(
        array $data,
        string $phone,
        float $amount,
        string $originatorId,
        array $requestPayload
    ): array {
        $idempotency = trim((string) ($data['idempotency_reference'] ?? ''));
        $actorUserId = (int) ($data['actor_user_id'] ?? 0);
        $insert = $this->getDb()->prepare(
            "INSERT INTO disbursement_transactions
                (disbursement_type, payment_purpose, source_financial_account_id,
                 payroll_id, payslip_id, payroll_payout_leg_id,
                 recipient_id, recipient_type, recipient_name, amount, currency,
                 idempotency_reference, phone_number, channel,
                 originator_conversation_id, status, reconciliation_status,
                 provider_status, result_description, callback_data,
                 submitted_by, approved_by, approved_at, released_at, created_at)
             VALUES (:disbursement_type, :payment_purpose, :source_account_id,
                     :payroll_id, :payslip_id, :payout_leg_id,
                     :recipient_id, :recipient_type, :recipient_name, :amount, 'KES',
                     :idempotency_reference, :phone_number, 'mpesa_b2c',
                     :originator_conversation_id, 'pending', 'awaiting_callback',
                     'RESERVED', :result_description, :callback_data,
                     :actor_user_id, :actor_user_id, NOW(), NOW(), NOW())"
        );

        try {
            $insert->execute([
                'disbursement_type' => $data['disbursement_type'] ?? 'salary',
                'payment_purpose' => $data['payment_purpose'] ?? ($data['disbursement_type'] ?? 'operations'),
                'source_account_id' => $data['source_financial_account_id'] ?? null,
                'payroll_id' => $data['payroll_id'] ?? null,
                'payslip_id' => $data['payslip_id'] ?? null,
                'payout_leg_id' => $data['payroll_payout_leg_id'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null,
                'recipient_type' => $data['recipient_type']
                    ?? (($data['disbursement_type'] ?? '') === 'refund' ? 'parent' : 'staff'),
                'recipient_name' => $data['recipient_name'] ?? null,
                'amount' => $amount,
                'idempotency_reference' => $idempotency !== '' ? $idempotency : null,
                'phone_number' => $phone,
                'originator_conversation_id' => $originatorId,
                'result_description' => 'Payment request reserved before provider submission.',
                'callback_data' => json_encode($requestPayload),
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ]);
            return [(int) $this->getDb()->lastInsertId(), true];
        } catch (\PDOException $error) {
            if ($idempotency !== '') {
                $existing = $this->getDb()->prepare(
                    'SELECT id FROM disbursement_transactions WHERE idempotency_reference=? LIMIT 1'
                );
                $existing->execute([$idempotency]);
                $existingId = (int) ($existing->fetchColumn() ?: 0);
                if ($existingId > 0) return [$existingId, false];
            }
            throw $error;
        }
    }

    /**
     * Keep the provider-side B2C mirror in step with the reserved attempt.
     */
    private function recordMpesaAttempt(
        string $mpesaCode,
        float $amount,
        string $phone,
        array $data,
        array $requestPayload
    ): void {
        try {
            $this->getDb()->prepare(
                "INSERT INTO mpesa_transactions
                    (mpesa_code, student_id, amount, transaction_date, phone_number,
                     bill_ref_number, status, transaction_type, raw_callback, webhook_data, created_at)
                 VALUES (:code, NULL, :amount, NOW(), :phone, :billref, 'pending', 'B2C', :raw, :webhook, NOW())
                 ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount), phone_number = VALUES(phone_number),
                    raw_callback = VALUES(raw_callback), webhook_data = VALUES(webhook_data)"
            )->execute([
                'code' => $mpesaCode,
                'amount' => $amount,
                'phone' => $phone,
                'billref' => $data['recipient_name'] ?? 'B2C payroll',
                'raw' => json_encode($requestPayload),
                'webhook' => json_encode(['status' => 'reserved']),
            ]);
        } catch (Exception $error) {
            // The disbursement row remains the callback and accounting
            // authority. A mirror failure must not create another attempt.
            \App\API\Services\Logger::legacyError('[MpesaB2CService] recordMpesaAttempt error: ' . $error->getMessage());
        }
    }

    private function callbackUrl(string $endpoint): string
    {
        $base = defined('MPESA_CALLBACK_BASE_URL') && MPESA_CALLBACK_BASE_URL !== ''
            ? MPESA_CALLBACK_BASE_URL
            : (defined('BASE_URL') ? BASE_URL : '');
        $url = $base !== '' ? $base . $endpoint : $endpoint;
        if (!preg_match('#^https://#i', $url)) {
            throw new Exception('M-Pesa B2C callback URLs must use HTTPS and be publicly reachable.');
        }
        return $url;
    }
}
