<?php
namespace App\API\Modules\payments;
use App\API\Modules\communications\CommunicationsAPI;
/**
 * PaymentsAPI - Handles all payment webhook logic for bank, mpesa, etc.
 * All methods return associative arrays for controller use.
 */
namespace App\API\Modules\payments;

use App\API\Services\payments\BankPaymentWebhook;
use App\API\Services\payments\MpesaPaymentService;
use App\API\Services\payments\MpesaB2CService;
use Exception;
use App\API\Includes\BaseAPI;
use \App\API\Modules\communications\CommunicationsAPI;

class PaymentsAPI extends BaseAPI
{


    private $commAPI;

    public function __construct()
    {
        parent::__construct('payments');
        $this->commAPI = new CommunicationsAPI();
    }

    /**
     * Process M-Pesa B2C Result Callback
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processMpesaB2CCallback(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_b2c_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW B2C CALLBACK:\n" . json_encode($callbackData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$callbackData || !isset($callbackData['Result'])) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid B2C result data'
                ];
            }
            $result = $callbackData['Result'];
            $resultCode = $result['ResultCode'] ?? 1;
            $resultDesc = $result['ResultDesc'] ?? 'Unknown error';
            $originatorConversationID = $result['OriginatorConversationID'] ?? null;
            $conversationID = $result['ConversationID'] ?? null;
            $transactionID = $result['TransactionID'] ?? null;
            $parameters = [];
            if (isset($result['ResultParameters']['ResultParameter'])) {
                foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                    $parameters[$param['Key']] = $param['Value'];
                }
            }
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name, payslip_id FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? LIMIT 1");
            $stmt->execute([$conversationID, $originatorConversationID]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN B2C CALLBACK: ConversationID=$conversationID, OriginatorID=$originatorConversationID\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            if ($resultCode == 0) {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'completed', transaction_ref = ?, transaction_id = ?, completed_at = NOW(), result_description = ?, callback_data = ? WHERE id = ?");
                $stmt->execute([
                    $transactionID,
                    $transactionID,
                    $resultDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                if (!empty($disbursement['payslip_id'])) {
                    $stmt = $this->db->prepare("UPDATE payslips SET payment_status = 'paid', payment_reference = ?, paid_at = NOW(), notes = CONCAT(COALESCE(notes, ''), '\nB2C success: ', ?) WHERE id = ?");
                    $stmt->execute([$transactionID, $resultDesc, $disbursement['payslip_id']]);
                }
                $stmt = $this->db->prepare(
                    "UPDATE mpesa_transactions
                     SET status = 'processed', mpesa_code = ?, webhook_data = ?, raw_callback = ?
                     WHERE transaction_type = 'B2C' AND status = 'pending'
                       AND amount = ? AND (bill_ref_number = ? OR bill_ref_number LIKE 'B2C-%')
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$transactionID, json_encode($callbackData), json_encode($callbackData), $disbursement['amount'], $disbursement['recipient_name']]);
                $logEntry = "[{$this->timestamp}] B2C SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Ref: $transactionID\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                $this->logPaymentWebhook('mpesa_b2c', $callbackData, 'processed', $disbursement, $transactionID);
            } else {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'failed', transaction_ref = ?, result_description = ?, callback_data = ?, failed_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $transactionID,
                    $resultDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                if (!empty($disbursement['payslip_id'])) {
                    $stmt = $this->db->prepare("UPDATE payslips SET payment_status = 'failed', notes = CONCAT(COALESCE(notes, ''), '\nB2C failed: ', ?) WHERE id = ?");
                    $stmt->execute([$resultDesc, $disbursement['payslip_id']]);
                }
                $logEntry = "[{$this->timestamp}] B2C FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $resultDesc\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                $this->logPaymentWebhook('mpesa_b2c', $callbackData, 'failed', $disbursement, null);
            }
            $this->db->commit();
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'B2C callback processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            (new \App\API\Services\UploadService())->writeFile($logFile, $errorEntry, FILE_APPEND);
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }
    }

    /**
     * Process M-Pesa B2C Timeout Callback
     * @param array $timeoutData
     * @param array $headers
     * @return array
     */
    public function processMpesaB2CTimeout(array $timeoutData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_b2c_timeouts.log';
        $logEntry = "[{$this->timestamp}] RAW B2C TIMEOUT:\n" . json_encode($timeoutData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$timeoutData || !isset($timeoutData['Result'])) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid timeout data'
                ];
            }
            $result = $timeoutData['Result'];
            // $resultCode = $result['ResultCode'] ?? 1; // Unused variable removed
            $resultDesc = $result['ResultDesc'] ?? 'Request timed out';
            $conversationID = $result['ConversationID'] ?? null;
            $originatorConversationID = $result['OriginatorConversationID'] ?? null;
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name, payslip_id, retry_count FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? LIMIT 1");
            $stmt->execute([$conversationID, $originatorConversationID]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN B2C TIMEOUT: ConversationID=$conversationID, OriginatorID=$originatorConversationID\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'timeout', result_description = ?, callback_data = ?, failed_at = NOW(), retry_count = retry_count + 1 WHERE id = ?");
            $stmt->execute([
                $resultDesc,
                json_encode($timeoutData),
                $disbursement['id']
            ]);
            if ($disbursement['disbursement_type'] === 'salary') {
                $stmt = $this->db->prepare("UPDATE payslips SET payment_status = 'failed', notes = CONCAT(COALESCE(notes, ''), '\nB2C timeout: ', ?) WHERE id = ?");
                $stmt->execute([$resultDesc, $disbursement['payslip_id']]);
            } elseif ($disbursement['disbursement_type'] === 'supplier') {
                // supplier disbursements have no payslip; tracked in disbursement_transactions only
            }
            $this->db->commit();
            $logEntry = "[{$this->timestamp}] B2C TIMEOUT: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Marked as timeout\n";
            (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'B2C timeout processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            (new \App\API\Services\UploadService())->writeFile($logFile, $errorEntry, FILE_APPEND);
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }
    }

    /**
     * Process M-Pesa C2B Confirmation
     * @param array $confirmationData
     * @param array $headers
     * @return array
     */
    public function processMpesaC2BConfirmation(array $confirmationData, array $headers)
    {
        $logFileRaw = $this->logDir . '/mpesa_c2b_confirmation_raw.log';
        $logFile = $this->logDir . '/mpesa_c2b_confirmation.log';
        @(new \App\API\Services\UploadService())->writeFile($logFileRaw, $this->timestamp . " - RAW REQUEST:\n" . json_encode($confirmationData) . "\n\n", FILE_APPEND);

        try {
            // Log parsed data
            @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - PARSED DATA:\n" . print_r($confirmationData, true) . "\n\n", FILE_APPEND);

            // Extract payment details from Safaricom callback
            $mpesaCode = $confirmationData['TransID'] ?? '';
            $admissionNumber = $confirmationData['BillRefNumber'] ?? '';
            $amount = floatval($confirmationData['TransAmount'] ?? 0);
            $phoneNumber = $confirmationData['MSISDN'] ?? '';
            $transTime = $confirmationData['TransTime'] ?? '';
            $firstName = $confirmationData['FirstName'] ?? '';
            $middleName = $confirmationData['MiddleName'] ?? '';
            $lastName = $confirmationData['LastName'] ?? '';
            $orgBalance = $confirmationData['OrgAccountBalance'] ?? '';
            $thirdPartyTransId = $confirmationData['ThirdPartyTransID'] ?? '';

            // Validate required fields
            if (empty($mpesaCode) || empty($admissionNumber) || $amount <= 0) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Missing required fields\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Missing required fields'
                ];
            }

            // Format transaction date
            $transDateTime = \DateTime::createFromFormat('YmdHis', $transTime);
            $transDateFormatted = $transDateTime ? $transDateTime->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

            // Look up student by admission number (names live on persons)
            $stmt = $this->db->prepare("SELECT s.id, p.first_name, p.last_name, s.status FROM students s JOIN persons p ON p.id = s.person_id WHERE s.admission_no = :admission_no LIMIT 1");
            $stmt->execute(['admission_no' => $admissionNumber]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$student) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Student not found: {$admissionNumber}\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Student not found'
                ];
            }
            
            if (!in_array($student['status'], ['active', 'enrolled'])) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Student not active: {$admissionNumber} (status: {$student['status']})\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Student account not active'
                ];
            }

            $studentId = $student['id'];

            // FIX: HIGH - Validate payment amount against outstanding balance
            if ($amount <= 0) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Invalid payment amount: {$amount}\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid payment amount'
                ];
            }

            $outstandingBalance = $this->getStudentOutstandingBalance($studentId);
            if ($outstandingBalance === false) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Could not calculate outstanding balance for student {$studentId}\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Could not validate student balance'
                ];
            }

            // Allow payment if it matches or is less than balance (allow 10% overpayment tolerance)
            $maxAllowed = $outstandingBalance * 1.1;
            if ($amount > $maxAllowed && $outstandingBalance > 0) {
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: Payment {$amount} exceeds outstanding balance {$outstandingBalance}\n", FILE_APPEND);
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Payment amount exceeds outstanding balance'
                ];
            }

            // FIX: CRITICAL - Use explicit transaction with row-level locking to prevent race condition
            // This atomically checks for duplicate AND inserts if not found
            $this->db->beginTransaction();
            try {
                // Lock the row for update - if another request is processing the same code, it waits
                $lockStmt = $this->db->prepare("
                    SELECT id FROM mpesa_transactions 
                    WHERE mpesa_code = :mpesa_code 
                    LIMIT 1 
                    FOR UPDATE
                ");
                $lockStmt->execute(['mpesa_code' => $mpesaCode]);
                $existingTx = $lockStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existingTx) {
                    // Already processed - safe to return as duplicate
                    @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - DUPLICATE: Transaction {$mpesaCode} already exists\n", FILE_APPEND);
                    $this->db->commit();
                    return [
                        'ResultCode' => 0,
                        'ResultDesc' => 'Confirmation received successfully (already processed)'
                    ];
                }

                // Record M-Pesa transaction with enhanced fields
                $insertMpesa = $this->db->prepare("
                    INSERT INTO mpesa_transactions 
                    (mpesa_code, student_id, amount, transaction_date, phone_number, 
                     first_name, middle_name, last_name, org_account_balance, 
                     bill_ref_number, third_party_trans_id, status, transaction_type, raw_callback, created_at)
                    VALUES (:mpesa_code, :student_id, :amount, :trans_date, :phone,
                            :first_name, :middle_name, :last_name, :org_balance,
                            :bill_ref, :third_party_id, 'processed', 'C2B', :raw_callback, NOW())
                ");
                $insertMpesa->execute([
                    'mpesa_code' => $mpesaCode,
                    'student_id' => $studentId,
                    'amount' => $amount,
                    'trans_date' => $transDateFormatted,
                    'phone' => $phoneNumber,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'org_balance' => $orgBalance,
                    'bill_ref' => $admissionNumber,
                    'third_party_id' => $thirdPartyTransId,
                    'raw_callback' => json_encode($confirmationData)
                ]);

                $mpesaTxId = $this->db->lastInsertId();
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - M-Pesa TX recorded (ID: {$mpesaTxId})\n", FILE_APPEND);

                // Get parent_id for this student
                $parentStmt = $this->db->prepare("SELECT parent_id FROM student_parents WHERE student_id = :student_id LIMIT 1");
                $parentStmt->execute(['student_id' => $studentId]);
                $parentRow = $parentStmt->fetch(\PDO::FETCH_ASSOC);
                $parentId = $parentRow ? $parentRow['parent_id'] : null;

                // Generate receipt number
                $receiptNo = 'MPESA-' . $mpesaCode;

                // System user for automated payments
                $systemUserId = 1;

                // Process payment using stored procedure sp_process_student_payment
                // Parameters: p_student_id, p_parent_id, p_amount_paid, p_payment_method, p_reference_no, 
                //             p_receipt_no, p_received_by, p_payment_date, p_notes
                $spStmt = $this->db->prepare("CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $spStmt->execute([
                    $studentId,
                    $parentId,
                    $amount,
                    'mpesa',  // payment_method ENUM value
                    $mpesaCode,
                    $receiptNo,
                    $systemUserId,
                    $transDateFormatted,
                    "M-Pesa C2B payment from {$firstName} {$middleName} {$lastName} (Phone: {$phoneNumber}). OrgBalance: {$orgBalance}"
                ]);
                $spStmt->closeCursor();

                // Log webhook
                \App\API\Includes\FileLogger::write('payments', [
                    'type' => 'webhook',
                    'source' => 'mpesa_c2b_confirmation',
                    'webhook_data' => [
                        'mpesa_code' => $mpesaCode,
                        'admission_no' => $admissionNumber,
                        'student_id' => $studentId,
                        'mpesa_tx_id' => $mpesaTxId,
                        'amount' => $amount,
                        'phone' => $phoneNumber,
                        'trans_time' => $transDateFormatted,
                        'payer_name' => trim("{$firstName} {$middleName} {$lastName}"),
                        'org_balance' => $orgBalance,
                    ],
                    'status' => 'processed',
                ]);

                $this->db->commit();

                try {
                    (new MpesaPaymentService())->sendPaymentConfirmationSms((int) $mpesaTxId);
                } catch (\Exception $e) {
                    error_log('[PaymentsAPI] C2B payment SMS: ' . $e->getMessage());
                }

                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - CONFIRMATION SUCCESS: {$mpesaCode}, Student: {$admissionNumber} (ID: {$studentId}), Amount: {$amount}\n", FILE_APPEND);

                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Confirmation received successfully'
                ];

            } catch (\Exception $transactionError) {
                $this->db->rollBack();
                throw $transactionError;
            }

        } catch (\PDOException $e) {
            // FIX: MEDIUM - Improved error handling: distinguish between expected and unexpected errors
            if (strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
                // Duplicate key - already processed
                @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - DUPLICATE via exception: {$mpesaCode}\n", FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Confirmation received successfully (already processed)'
                ];
            }
            
            // Database connection error or other critical issue
            @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - PDO ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            error_log("M-Pesa C2B PDO Error: " . $e->getMessage());
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Database error processing payment'
            ];
        } catch (\Exception $e) {
            // FIX: MEDIUM - Better logging and error messages
            @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            error_log("M-Pesa C2B Error: " . $e->getMessage());
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }
    }

    /**
     * Process M-Pesa C2B Validation Callback.
     * Safaricom calls this BEFORE completing an incoming C2B payment. A
     * ResultCode of 0 accepts the transaction; anything else rejects it.
     * @param array $validationData
     * @param array $headers
     * @return array
     */
    public function processMpesaC2BValidation(array $validationData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_c2b_validation.log';
        @(new \App\API\Services\UploadService())->writeFile($logFile, $this->timestamp . " - RAW VALIDATION REQUEST:\n" . json_encode($validationData) . "\n\n", FILE_APPEND);

        try {
            $service = new MpesaPaymentService();
            return $service->validateC2BPayment($validationData);
        } catch (\Exception $e) {
            error_log('[PaymentsAPI] C2B validation error: ' . $e->getMessage());
            return [
                'ResultCode' => 'C2B00011',
                'ResultDesc' => 'System error'
            ];
        }
    }
    /**

     * Process M-Pesa STK Push Result Callback (Body.stkCallback).
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processMpesaStkCallback(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_stk_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW STK CALLBACK:\n" . json_encode($callbackData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);

        try {
            $service = new MpesaPaymentService();
            $result = $service->processCallback($callbackData);

            return $result['success']
                ? ['ResultCode' => 0, 'ResultDesc' => 'Success']
                : ['ResultCode' => 1, 'ResultDesc' => 'Invalid STK callback payload'];
        } catch (\Exception $e) {
            error_log('[PaymentsAPI] STK callback error: ' . $e->getMessage());
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal server error'];
        }
    }

    /**
     * Process a generic M-Pesa Result callback (Transaction Status, Account
     * Balance, Reversal, B2B). Records the payload for audit and attempts to
     * reconcile any matching disbursement_transactions row.
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processMpesaResult(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_result_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW M-PESA RESULT:\n" . json_encode($callbackData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);

        try {
            $result = $callbackData['Result'] ?? $callbackData;
            $resultCode = $result['ResultCode'] ?? null;
            $resultDesc = $result['ResultDesc'] ?? '';
            $originator = $result['OriginatorConversationID'] ?? null;
            $conversation = $result['ConversationID'] ?? null;

            if ($originator || $conversation) {
                $stmt = $this->db->prepare(
                    "UPDATE disbursement_transactions
                     SET status = IF(? = 0, 'completed', 'failed'),
                         result_description = ?,
                         transaction_ref = COALESCE(transaction_ref, ?),
                         callback_data = ?,
                         completed_at = IF(? = 0, NOW(), completed_at),
                         failed_at = IF(? = 0, failed_at, NOW())
                     WHERE originator_conversation_id = ? OR conversation_id = ?"
                );
                $stmt->execute([
                    $resultCode, $resultDesc, $conversation, json_encode($callbackData),
                    $resultCode, $resultCode, $originator, $conversation,
                ]);
            }

            $source = 'mpesa_result';
            \App\API\Includes\FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => $source,
                'webhook_data' => $callbackData,
                'status' => (int) $resultCode === 0 ? 'processed' : 'failed',
                'ip' => $headers['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            ]);

            return ['ResultCode' => 0, 'ResultDesc' => 'Result processed successfully'];
        } catch (\Exception $e) {
            error_log('[PaymentsAPI] mpesa-result error: ' . $e->getMessage());
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal server error'];
        }
    }

    /**
     * Process KCB Validation
     * @param array $validationData
     * @param array $headers
     * @return array
     */
    public function processKcbValidation(array $validationData, array $headers)
    {
        $logFileRaw = $this->logDir . '/kcb_validation_raw.log';
        $logFile = $this->logDir . '/kcb_validation.log';
        $logFileErr = $this->logDir . '/kcb_validation_errors.log';
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        (new \App\API\Services\UploadService())->writeFile(
            $logFileRaw,
            $this->timestamp . " - RAW REQUEST:\n" .
            "Signature: {$signature}\n" .
            "Body: " . json_encode($validationData) . "\n\n",
            FILE_APPEND
        );
        try {
            if (!$validationData || !is_array($validationData)) {
                throw new \Exception("Invalid or missing JSON data");
            }
            (new \App\API\Services\UploadService())->writeFile(
                $logFile,
                $this->timestamp . " - PARSED DATA:\n" . print_r($validationData, true) . "\n\n",
                FILE_APPEND
            );
            $requestId = $validationData['requestId'] ?? '';
            $customerReference = $validationData['customerReference'] ?? '';
            $organizationReference = $validationData['organizationReference'] ?? '';
            if (empty($customerReference)) {
                (new \App\API\Services\UploadService())->writeFile(
                    $logFile,
                    $this->timestamp . " - REJECTED: Empty customer reference\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => 'Customer reference (admission number) is required',
                    'CustomerName' => '',
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            $stmt = $this->db->prepare("SELECT s.id, s.admission_no, CONCAT(p.first_name, ' ', p.last_name) as full_name, s.status, COALESCE((SELECT SUM(v.balance) FROM vw_student_fee_balances v WHERE v.student_id = s.id), 0) as current_balance FROM students s JOIN persons p ON p.id = s.person_id WHERE s.admission_no = :admission_no LIMIT 1");
            $stmt->execute(['admission_no' => $customerReference]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                (new \App\API\Services\UploadService())->writeFile(
                    $logFile,
                    $this->timestamp . " - REJECTED: Admission number '{$customerReference}' not found\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => "Admission number {$customerReference} not found. Please verify and try again.",
                    'CustomerName' => '',
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            if (!in_array($student['status'], ['active', 'enrolled'])) {
                (new \App\API\Services\UploadService())->writeFile(
                    $logFile,
                    $this->timestamp . " - REJECTED: Student '{$customerReference}' status is '{$student['status']}'\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => "Student account {$customerReference} is {$student['status']}. Please contact school administration.",
                    'CustomerName' => $student['full_name'],
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            $response = [
                'transactionID' => $requestId,
                'statusCode' => '0',
                'statusMessage' => 'Success',
                'CustomerName' => $student['full_name'],
                'billAmount' => number_format($student['current_balance'], 2, '.', ''),
                'currency' => 'KES',
                'billType' => 'PARTIAL',
                'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
            ];
            \App\API\Includes\FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => 'kcb_validation',
                'webhook_data' => [
                    'request_id' => $requestId,
                    'customer_reference' => $customerReference,
                    'organization_reference' => $organizationReference,
                    'student_id' => $student['id'],
                    'student_name' => $student['full_name'],
                    'current_balance' => $student['current_balance'],
                    'validation_result' => 'accepted',
                ],
                'status' => 'validated',
            ]);
            (new \App\API\Services\UploadService())->writeFile(
                $logFile,
                $this->timestamp . " - ACCEPTED: Student '{$customerReference}' - {$student['full_name']}, Balance: {$student['current_balance']}, RequestID: {$requestId}\n\n",
                FILE_APPEND
            );
            return $response;
        } catch (\Exception $e) {
            (new \App\API\Services\UploadService())->writeFile(
                $logFileErr,
                $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
            return [
                'transactionID' => $validationData['requestId'] ?? 'UNKNOWN',
                'statusCode' => '1',
                'statusMessage' => 'System error. Please try again later.',
                'CustomerName' => '',
                'billAmount' => '0.00',
                'currency' => 'KES',
                'billType' => 'PARTIAL',
                'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
            ];
        }

    }
    /**
     * Process KCB Bank Transfer Callback
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processKcbTransferCallback(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/kcb_transfer_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW KCB TRANSFER CALLBACK:\nHeaders: " . json_encode($headers) . "\nBody: " . json_encode($callbackData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
        try {
            if (!$callbackData || !is_array($callbackData)) {
                return [
                    'statusCode' => '1',
                    'statusMessage' => 'Invalid JSON data'
                ];
            }
            $transactionRef = $callbackData['transactionReference'] ?? null;
            $requestId = $callbackData['requestId'] ?? null;
            $amount = $callbackData['transactionAmount'] ?? 0;
            $status = $callbackData['status'] ?? 'UNKNOWN';
            $statusDesc = $callbackData['statusDescription'] ?? '';
            // $creditAccount = $callbackData['creditAccountNumber'] ?? null; // Unused variable removed
            // $creditAccountName = $callbackData['creditAccountName'] ?? null; // Unused variable removed
            // $debitAccount = $callbackData['debitAccountNumber'] ?? null; // Unused variable removed
            $charges = $callbackData['charges'] ?? 0;
            // $narration = $callbackData['narration'] ?? ''; // Unused variable removed
            // $transactionTimestamp = $callbackData['timestamp'] ?? null; // Unused variable removed
            if (!$requestId || !$amount) {
                return [
                    'statusCode' => '1',
                    'statusMessage' => 'Missing required fields'
                ];
            }
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, account_number, recipient_name, status FROM disbursement_transactions WHERE request_id = ? OR transaction_ref = ? LIMIT 1");
            $stmt->execute([$requestId, $transactionRef]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN KCB TRANSFER: RequestID=$requestId, TransactionRef=$transactionRef\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                return [
                    'statusCode' => '0',
                    'statusMessage' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            if (strtoupper($status) === 'SUCCESS') {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'completed', transaction_ref = ?, transaction_id = ?, completed_at = NOW(), result_description = ?, callback_data = ?, bank_charges = ? WHERE id = ?");
                $stmt->execute([
                    $transactionRef,
                    $transactionRef,
                    $statusDesc,
                    json_encode($callbackData),
                    $charges,
                    $disbursement['id']
                ]);
                // Staff/supplier payment rows live in disbursement_transactions
                // (already updated above); no separate staff_payments/supplier_payments row exists.
                $logEntry = "[{$this->timestamp}] KCB TRANSFER SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Ref: $transactionRef\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                $this->sendTransferNotification($disbursement, $transactionRef, 'completed', $charges);
            } else {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'failed', transaction_ref = ?, result_description = ?, callback_data = ?, failed_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $transactionRef,
                    $statusDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                // See success path — no legacy staff_payments/supplier_payments rows.
                $logEntry = "[{$this->timestamp}] KCB TRANSFER FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $statusDesc\n";
                (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);
                $this->sendTransferNotification($disbursement, $transactionRef, 'failed', 0, $statusDesc);
            }
            $this->db->commit();
            return [
                'transactionID' => $disbursement['id'],
                'statusCode' => '0',
                'statusMessage' => 'Transfer notification processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            (new \App\API\Services\UploadService())->writeFile($logFile, $errorEntry, FILE_APPEND);
            return [
                'statusCode' => '1',
                'statusMessage' => 'Internal server error'
            ];
        }
    }

    /**
     * Send transfer notification to recipient (private helper)
     */
    private function sendTransferNotification($disbursement, $transactionRef, $status, $charges = 0, $error = null)
    {
        try {

            $phoneNumber = null;
            $email = null;
            $recipientName = null;
            if ($disbursement['disbursement_type'] === 'salary') {
                $stmt = $this->db->prepare("SELECT p.phone AS phone_number, p.email, p.first_name, p.last_name FROM staff s JOIN persons p ON p.id = s.person_id WHERE s.id = ?");
                $stmt->execute([$disbursement['recipient_id']]);
                $contact = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($contact) {
                    $phoneNumber = $contact['phone_number'];
                    $email = $contact['email'];
                    $recipientName = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
                }
            }
            if (!$phoneNumber && !$email) {
                return;
            }
            // Prepare variables for SMS/email
            $variables = [
                'recipient_name' => $recipientName,
                'amount' => number_format($disbursement['amount'], 2),
                'net_amount' => number_format($disbursement['amount'] - $charges, 2),
                'charges' => number_format($charges, 2),
                'reference' => $transactionRef,
                'account' => $disbursement['account_number'],
                'status' => $status,
                'error' => $error,
            ];
            $category = ($status === 'completed') ? 'salary_payment_success' : 'salary_payment_failed';
            // Send SMS
            if ($phoneNumber) {
                $this->commAPI->sendTemplateSMS([$phoneNumber], $variables, $category, 'sms');
            }
            // Send Email
            if ($email) {
                $subject = ($status === 'completed') ? 'Salary Payment Completed' : 'Salary Payment Failed';
                $body = [
                    'recipient_name' => $recipientName,
                    'amount' => number_format($disbursement['amount'], 2),
                    'net_amount' => number_format($disbursement['amount'] - $charges, 2),
                    'charges' => number_format($charges, 2),
                    'reference' => $transactionRef,
                    'account' => $disbursement['account_number'],
                    'status' => $status,
                    'error' => $error,
                ];
                $this->commAPI->sendEmail([$email], $subject, $body);
            }
        } catch (\Exception $e) {
            error_log("Failed to send transfer notification: " . $e->getMessage());
        }
    }
    /**
     * Process KCB Bank Payment Notification
     * @param array $notificationData
     * @param array $headers
     * @return array
     */
    public function processKcbNotification(array $notificationData, array $headers)
    {
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        (new \App\API\Services\UploadService())->writeFile(
            $this->logDir . '/kcb_notification_raw.log',
            $this->timestamp . " - RAW REQUEST:\n" .
            "Signature: {$signature}\n" .
            "Body: " . json_encode($notificationData) . "\n\n",
            FILE_APPEND
        );
        try {
            if (!$notificationData || !is_array($notificationData)) {
                throw new \Exception("Invalid or missing JSON data");
            }
            (new \App\API\Services\UploadService())->writeFile(
                $this->logDir . '/kcb_notification.log',
                $this->timestamp . " - PARSED DATA:\n" . print_r($notificationData, true) . "\n\n",
                FILE_APPEND
            );
            $transactionReference = $notificationData['transactionReference'] ?? '';
            $requestId = $notificationData['requestId'] ?? '';
            $customerReference = $notificationData['customerReference'] ?? '';
            $transactionAmount = floatval($notificationData['transactionAmount'] ?? 0);
            $customerName = $notificationData['customerName'] ?? '';
            $customerMobile = $notificationData['customerMobileNumber'] ?? '';
            $narration = $notificationData['narration'] ?? '';
            $timestamp = $notificationData['timestamp'] ?? '';
            // $currency = $notificationData['currency'] ?? 'KES'; // Unused variable removed
            $channelCode = $notificationData['channelCode'] ?? '';
            $orgShortCode = $notificationData['organizationShortCode'] ?? '';
            $balance = $notificationData['balance'] ?? '';
            if (empty($transactionReference) || empty($customerReference) || $transactionAmount <= 0) {
                throw new \Exception("Missing required fields: TransRef={$transactionReference}, CustRef={$customerReference}, Amount={$transactionAmount}");
            }
            $this->db->beginTransaction();
            try {
                $studentQuery = "SELECT s.id, p.first_name, p.last_name, s.status FROM students s JOIN persons p ON p.id = s.person_id WHERE s.admission_no = :admission_no LIMIT 1";
                $stmt = $this->db->prepare($studentQuery);
                $stmt->execute(['admission_no' => $customerReference]);
                $student = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$student) {
                    throw new \Exception("Student not found: Admission number {$customerReference}");
                }
                $studentId = $student['id'];
                $duplicateCheck = "SELECT id, status FROM bank_transactions WHERE transaction_ref = :transaction_ref LIMIT 1";
                $dupStmt = $this->db->prepare($duplicateCheck);
                $dupStmt->execute(['transaction_ref' => $transactionReference]);
                $existing = $dupStmt->fetch(\PDO::FETCH_ASSOC);
                if ($existing) {
                    $this->db->rollback();
                    (new \App\API\Services\UploadService())->writeFile(
                        $this->logDir . '/kcb_notification.log',
                        $this->timestamp . " - DUPLICATE: Transaction {$transactionReference} already processed (ID: {$existing['id']})\n\n",
                        FILE_APPEND
                    );
                    return [
                        'transactionID' => $requestId,
                        'statusCode' => '0',
                        'statusMessage' => 'Notification received successfully (already processed)'
                    ];
                }
                $transDateTime = \DateTime::createFromFormat('YmdHis', $timestamp);
                if (!$transDateTime) {
                    $transDateTime = new \DateTime();
                }
                $transDateFormatted = $transDateTime->format('Y-m-d H:i:s');
                // NOTE: status is 'recorded' (not 'processed') because
                // sp_process_student_payment already credited the balance.
                // Using 'processed' would fire trg_bank_payment_processed
                // and double-credit the student.
                $insertQuery = "INSERT INTO bank_transactions (transaction_ref, student_id, amount, transaction_date, bank_name, account_number, narration, status, webhook_data, created_at) VALUES (:transaction_ref, :student_id, :amount, :transaction_date, 'KCB Bank', :account_number, :narration, 'recorded', :webhook_data, NOW())";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->execute([
                    'transaction_ref' => $transactionReference,
                    'student_id' => $studentId,
                    'amount' => $transactionAmount,
                    'transaction_date' => $transDateFormatted,
                    'account_number' => $customerMobile,
                    'narration' => $narration,
                    'webhook_data' => json_encode($notificationData)
                ]);
                $bankTransactionId = $this->db->lastInsertId();

                // Get parent_id for this student (for fee allocation)
                $parentStmt = $this->db->prepare("SELECT parent_id FROM student_parents WHERE student_id = ? LIMIT 1");
                $parentStmt->execute([$studentId]);
                $parentRow = $parentStmt->fetch(\PDO::FETCH_ASSOC);
                $parentId = $parentRow ? $parentRow['parent_id'] : null;

                // Use sp_process_student_payment to properly allocate payment to fee obligations
                $receiptNo = 'KCB-' . $transactionReference;
                $notes = "KCB Bank payment from {$customerName} (Mobile: {$customerMobile}). {$narration}";

                $spStmt = $this->db->prepare("CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $spStmt->execute([
                    $studentId,
                    $parentId,
                    $transactionAmount,
                    'bank_transfer',
                    $transactionReference,
                    $receiptNo,
                    1, // received_by = system (1)
                    $transDateFormatted,
                    $notes
                ]);
                $spStmt->closeCursor();
                \App\API\Includes\FileLogger::write('payments', [
                    'type' => 'webhook',
                    'source' => 'kcb_bank',
                    'webhook_data' => [
                        'transaction_ref' => $transactionReference,
                        'request_id' => $requestId,
                        'customer_reference' => $customerReference,
                        'student_id' => $studentId,
                        'amount' => $transactionAmount,
                        'customer_mobile' => $customerMobile,
                        'customer_name' => $customerName,
                        'transaction_time' => $transDateFormatted,
                        'channel_code' => $channelCode,
                        'org_short_code' => $orgShortCode,
                        'balance' => $balance,
                        'bank_transaction_id' => $bankTransactionId,
                        'signature' => substr($signature, 0, 50) . '...',
                    ],
                    'status' => 'processed',
                ]);
                $this->db->commit();
                (new \App\API\Services\UploadService())->writeFile(
                    $this->logDir . '/kcb_notification.log',
                    $this->timestamp . " - SUCCESS: KCB {$transactionReference}, Student {$customerReference} ({$student['first_name']} {$student['last_name']}), Amount: KES {$transactionAmount}, Mobile: {$customerMobile}\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '0',
                    'statusMessage' => 'Notification received successfully'
                ];
            } catch (\Exception $e) {
                $this->db->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            (new \App\API\Services\UploadService())->writeFile(
                $this->logDir . '/kcb_notification_errors.log',
                $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
            // Return success to KCB to avoid retries, but log for manual processing
            return [
                'transactionID' => $notificationData['requestId'] ?? 'UNKNOWN',
                'statusCode' => '0',
                'statusMessage' => 'Received. Processing offline.'
            ];
        }
    }
    /**
     * Process Bank Payment Webhook
     * @param array $webhookData
     * @param array $headers
     * @return array
     */
    public function processBankWebhook(array $webhookData, array $headers)
    {
        $logFile = $this->logDir . '/bank_webhooks_raw.log';
        $logEntry = "[{$this->timestamp}] RAW WEBHOOK:\nHeaders: " . json_encode($headers) . "\nBody: " . json_encode($webhookData) . "\n\n";
        (new \App\API\Services\UploadService())->writeFile($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$webhookData) {
                return [
                    'status' => false,
                    'message' => 'Invalid JSON data'
                ];
            }
            $bankService = new BankPaymentWebhook();
            $bankName = $headers['X-Bank-Name'] ?? $webhookData['bank'] ?? $webhookData['bank_name'] ?? 'Generic Bank';

            // Use BankPaymentWebhook's flexible extractors to handle various field names
            $accountRef = $this->extractAccountNumber($webhookData);
            $narration = strtolower($webhookData['narration'] ?? '');
            $handled = false;
            $result = null;

            // If we got an account reference, try to process it as a bank payment
            if ($accountRef) {
                // Check account reference type and route accordingly
                if (preg_match('/^\d{5,}$/', $accountRef)) {
                    // Numeric account (5+ digits)
                    $result = (strtoupper($bankName) === 'KCB')
                        ? $bankService->processKCBPayment($webhookData)
                        : $bankService->processGenericBankPayment($webhookData, $bankName);
                    $handled = true;
                } elseif (preg_match('/^ADM\d+$/i', $accountRef)) {
                    // Admission number (ADM001, ADM102, etc.) - student school fees
                    $result = (strtoupper($bankName) === 'KCB')
                        ? $bankService->processKCBPayment($webhookData)
                        : $bankService->processGenericBankPayment($webhookData, $bankName);
                    $handled = true;
                } elseif (stripos($accountRef, 'TRP') === 0 || strpos($narration, 'transport') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Transport payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'PAY') === 0 || strpos($narration, 'payroll') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Payroll payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'DEPT') === 0 || strpos($narration, 'department') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Department payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'CHQ') === 0 || strpos($narration, 'cheque') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Cheque payment processing not yet implemented.'
                    ];
                    $handled = true;
                }
            }

            if (!$handled) {
                $result = [
                    'status' => false,
                    'message' => 'Unknown or unsupported payment type.'
                ];
            }
            if ($result['status']) {
                return [
                    'status' => true,
                    'message' => 'Payment processed successfully',
                    'data' => $result['data'] ?? null
                ];
            } else {
                return [
                    'status' => false,
                    'message' => $result['message']
                ];
            }
        } catch (\Exception $e) {
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n\n";
            (new \App\API\Services\UploadService())->writeFile($logFile, $errorEntry, FILE_APPEND);
            return [
                'status' => false,
                'message' => 'Internal server error'
            ];
        }
    }

    /**
     * Extract account number from payment data (flexible field names)
     */
    private function extractAccountNumber($data)
    {
        $fields = ['account_number', 'account_ref', 'reference', 'customer_ref', 'bill_ref'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return null;
    }

    /**
     * Append a webhook audit entry to payment_webhooks_log.
     * @param string $source
     * @param array $data
     * @param string $status
     * @param array|null $disbursement
     * @param string|null $transactionRef
     */
    private function logPaymentWebhook(string $source, array $data, string $status, ?array $disbursement = null, ?string $transactionRef = null): void
    {
        try {
            $enriched = $data;
            if ($disbursement) {
                $enriched['_disbursement_id'] = $disbursement['id'] ?? null;
                $enriched['_recipient'] = $disbursement['recipient_name'] ?? null;
                $enriched['_amount'] = $disbursement['amount'] ?? null;
                $enriched['_payslip_id'] = $disbursement['payslip_id'] ?? null;
            }
            if ($transactionRef) {
                $enriched['_transaction_ref'] = $transactionRef;
            }
            \App\API\Includes\FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => $source,
                'webhook_data' => $enriched,
                'status' => $status,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            ]);
        } catch (\Exception $e) {
            error_log('[PaymentsAPI] logPaymentWebhook error: ' . $e->getMessage());
        }
    }

    /**
     * FIX: HIGH - Get student's outstanding balance for payment validation
     * @param int $studentId Student ID
     * @return float|false Outstanding balance or false if error
     */
    private function getStudentOutstandingBalance($studentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(balance), 0) as outstanding
                FROM vw_student_fee_balances
                WHERE student_id = ? AND balance > 0
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? floatval($result['outstanding']) : 0;
        } catch (\Exception $e) {
            error_log("Error calculating outstanding balance: " . $e->getMessage());
            return false;
        }
    }

    // ==================== RECONCILIATION & ANALYTICS (live schema) ====================
    // payment_transactions is retired; live payments table maps:
    //   payment_method -> method, amount_paid -> amount, reference_no -> reference,
    //   payment_date -> payment_date (datetime), status -> status.

    public function getRevenueSources()
    {
        try {
            $rows = $this->db->query(
                "SELECT method AS source, SUM(amount) AS total
                 FROM payments
                 WHERE status = 'confirmed'
                 GROUP BY method
                 ORDER BY total DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse([
                'sources' => $rows,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Revenue sources breakdown');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * List confirmed cash collections on a given day.
     * Combines fee payments (payments) and transport payments
     * (transport_bill_payments) so the daily cash reconciliation totals the
     * full cash drawer.
     */
    public function getCollections(array $filters = [])
    {
        try {
            $date = $filters['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $this->errorResponse('Invalid date format.', 400);
            }

            $stmt = $this->db->prepare(
                "SELECT p.id, p.receipt_no, p.amount, p.payment_date, p.reference, p.notes,
                        COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), 'Walk-in') AS student_name,
                        'fees' AS source
                 FROM payments p
                 LEFT JOIN students s ON s.id = p.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 WHERE p.method = 'cash' AND p.status = 'confirmed' AND DATE(p.payment_date) = ?
                 UNION ALL
                 SELECT tbp.id, tbp.transaction_id, tbp.amount,
                        CONCAT(tbp.payment_date, ' 00:00:00'),
                        tbp.transaction_id, tbp.notes,
                        COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), 'Walk-in'),
                        'transport'
                 FROM transport_bill_payments tbp
                 LEFT JOIN transport_monthly_bills tmb ON tmb.id = tbp.bill_id
                 LEFT JOIN students s ON s.id = tmb.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 WHERE tbp.payment_method = 'cash' AND tbp.payment_date = ?
                 ORDER BY payment_date DESC"
            );
            $stmt->execute([$date, $date]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse([
                'collections' => $rows,
                'total' => (float) array_sum(array_column($rows, 'amount')),
                'date' => $date,
            ], 'Cash collections retrieved');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getFeeStats()
    {
        try {
            $monthly = $this->db->query(
                "SELECT COALESCE(SUM(amount), 0) AS monthly_collected
                 FROM payments
                 WHERE status = 'confirmed'
                   AND YEAR(payment_date) = YEAR(NOW())
                   AND MONTH(payment_date) = MONTH(NOW())"
            )->fetch(\PDO::FETCH_ASSOC);
            $monthlyCollected = (float) ($monthly['monthly_collected'] ?? 0);

            $overdue = $this->db->query(
                "SELECT COUNT(DISTINCT e.student_id) AS overdue_count
                 FROM student_fee_obligations sfo
                 JOIN student_academic_enrollments e ON e.id = sfo.student_academic_enrollment_id
                 WHERE sfo.due_date < NOW()
                   AND COALESCE((SELECT MAX(l.balance) FROM vw_student_fee_ledger l
                                 WHERE l.student_academic_enrollment_id = sfo.student_academic_enrollment_id), 0) > 0"
            )->fetch(\PDO::FETCH_ASSOC);
            $overdueCount = (int) ($overdue['overdue_count'] ?? 0);

            $expected = $this->db->query(
                "SELECT COALESCE(SUM(amount_due), 0) AS total_expected FROM student_fee_obligations"
            )->fetch(\PDO::FETCH_ASSOC);
            $totalExpected = (float) ($expected['total_expected'] ?? 0);

            $collected = $this->db->query(
                "SELECT COALESCE(SUM(amount), 0) AS amount_collected
                 FROM payments
                 WHERE status = 'confirmed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetch(\PDO::FETCH_ASSOC);
            $amountCollected = (float) ($collected['amount_collected'] ?? 0);

            $outstanding = $this->db->query(
                "SELECT COALESCE(SUM(l.balance), 0) AS outstanding
                 FROM vw_student_fee_ledger l"
            )->fetch(\PDO::FETCH_ASSOC);
            $outstandingTotal = (float) ($outstanding['outstanding'] ?? 0);

            $percentage = $totalExpected > 0 ? round(($amountCollected / $totalExpected) * 100, 2) : 0;

            return $this->successResponse([
                'monthly_collected' => $monthlyCollected,
                'amount' => $amountCollected,
                'percentage' => (float) $percentage,
                'outstanding' => $outstandingTotal,
                'total_expected' => $totalExpected,
                'overdue_count' => $overdueCount,
                'period_days' => 30,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Fees collection statistics');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCollectionTrends()
    {
        try {
            $monthly = $this->db->query(
                "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month,
                        DATE_FORMAT(payment_date, '%b') AS month_label,
                        SUM(amount) AS collected,
                        COUNT(DISTINCT student_id) AS students_paid
                 FROM payments
                 WHERE status = 'confirmed'
                   AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                 ORDER BY month ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $expected = $this->db->query(
                "SELECT COALESCE(SUM(amount_due), 0) AS total_expected
                 FROM student_fee_obligations
                 WHERE status IN ('pending', 'partial', 'arrears')"
            )->fetch(\PDO::FETCH_ASSOC);
            $totalExpected = (float) ($expected['total_expected'] ?? 0);
            $monthlyTarget = $totalExpected / 12;

            $chartData = [];
            foreach ($monthly as $m) {
                $chartData[] = [
                    'month' => $m['month_label'] ?? substr($m['month'], 5),
                    'collected' => (float) ($m['collected'] ?? 0),
                    'target' => $monthlyTarget,
                    'students_paid' => (int) ($m['students_paid'] ?? 0),
                ];
            }

            $totalCollected = count($chartData) > 0 ? array_sum(array_column($chartData, 'collected')) : 0;
            $totalTarget = $monthlyTarget * count($chartData);
            $collectionRate = $totalTarget > 0 ? round(($totalCollected / $totalTarget) * 100, 2) : 0;

            return $this->successResponse([
                'chart_data' => $chartData,
                'summary' => [
                    'collected' => (float) $totalCollected,
                    'target' => (float) $totalTarget,
                    'collection_rate' => (float) $collectionRate,
                    'period' => '12 months',
                    'month_target' => (float) $monthlyTarget,
                ],
            ], 'Collection trends retrieved');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getUnmatchedMpesa()
    {
        try {
            $rows = $this->db->query(
                "SELECT mt.*
                 FROM mpesa_transactions mt
                 LEFT JOIN payments pt ON mt.mpesa_code = pt.reference COLLATE utf8mb4_general_ci
                 WHERE pt.reference IS NULL
                   AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'processed', 'matched'))
                 ORDER BY mt.transaction_date DESC
                 LIMIT 200"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['transactions' => $rows]);
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function importMpesa($txns = [])
    {
        if (!is_array($txns) || count($txns) === 0) {
            return $this->errorResponse('No transactions provided for import', 400);
        }
        $inserted = 0;
        try {
            $this->db->beginTransaction();
            $insertSql = "INSERT INTO mpesa_transactions (mpesa_code, amount, phone_number, transaction_date, status, raw_callback, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())";

            foreach ($txns as $t) {
                $code = $t['mpesa_code'] ?? $t['trans_id'] ?? null;
                if (!$code) {
                    continue;
                }
                $chk = $this->db->prepare('SELECT id FROM mpesa_transactions WHERE mpesa_code = ? LIMIT 1');
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    continue;
                }
                $stmt = $this->db->prepare($insertSql);
                $stmt->execute([
                    $code,
                    $t['amount'] ?? 0,
                    $t['phone_number'] ?? $t['phone'] ?? $t['msisdn'] ?? null,
                    $t['transaction_date'] ?? ($t['date'] ?? date('Y-m-d H:i:s')),
                    json_encode($t),
                ]);
                $inserted++;
            }
            $this->db->commit();
            return $this->successResponse(['imported' => $inserted], 'Import completed');
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function reconcileMpesa($mpesaId, $data = [], $userId = null)
    {
        try {
            $studentId = $data['student_id'] ?? null;
            $notes = $data['notes'] ?? 'Quick reconcile from dashboard';

            $stmt = $this->db->prepare('SELECT * FROM mpesa_transactions WHERE id = ? LIMIT 1');
            $stmt->execute([$mpesaId]);
            $mp = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$mp) {
                return $this->errorResponse('MPESA transaction not found', 404);
            }

            if (!$studentId && !empty($mp['student_id'])) {
                $studentId = $mp['student_id'];
            }

            $this->db->beginTransaction();

            $amount = $mp['amount'] ?? $mp['amt'] ?? 0;
            $mpesaCode = $mp['mpesa_code'] ?? $mp['trans_id'] ?? $mp['code'] ?? null;
            $transactionDate = $mp['transaction_date'] ?? ($mp['created_at'] ?? date('Y-m-d H:i:s'));
            $phoneNumber = $mp['phone_number'] ?? $mp['msisdn'] ?? '';
            $payerName = trim(($mp['first_name'] ?? '') . ' ' . ($mp['middle_name'] ?? '') . ' ' . ($mp['last_name'] ?? ''));

            $paymentId = null;
            $feeAllocated = false;

            if ($studentId) {
                $parentStmt = $this->db->prepare("SELECT parent_id FROM student_parents WHERE student_id = ? LIMIT 1");
                $parentStmt->execute([$studentId]);
                $parentId = $parentStmt->fetchColumn() ?: null;

                $receiptNo = 'MPESA-' . $mpesaCode;
                $receivedBy = $userId ?? $this->user_id ?? 1;

                $fullNotes = $notes;
                if ($payerName || $phoneNumber) {
                    $fullNotes .= " | Payer: {$payerName} (Phone: {$phoneNumber})";
                }

                $spStmt = $this->db->prepare("CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $spStmt->execute([
                    $studentId, $parentId, $amount, 'mpesa', $mpesaCode, $receiptNo, $receivedBy, $transactionDate, $fullNotes,
                ]);
                $spStmt->closeCursor();

                $ptStmt = $this->db->prepare(
                    "SELECT id FROM payments WHERE reference = ? ORDER BY id DESC LIMIT 1"
                );
                $ptStmt->execute([$mpesaCode]);
                $paymentId = $ptStmt->fetchColumn() ?: null;
                $feeAllocated = true;
            } else {
                $details = json_encode($mp);
                $insStmt = $this->db->prepare(
                    "INSERT INTO school_transactions (student_id, financial_period_id, source, reference, amount, transaction_date, status, details, created_at)
                     VALUES (?, NULL, 'mpesa', ?, ?, ?, 'confirmed', ?, NOW())"
                );
                $insStmt->execute([null, $mpesaCode, $amount, $transactionDate, $details]);
                $paymentId = $this->db->lastInsertId();
            }

            $updStmt = $this->db->prepare("UPDATE mpesa_transactions SET status = 'reconciled', reconciled_at = NOW(), student_id = ? WHERE id = ?");
            $updStmt->execute([$studentId, $mpesaId]);

            $this->db->commit();

            return $this->successResponse([
                'payment_id' => $paymentId,
                'student_id' => $studentId,
                'amount' => $amount,
                'fee_allocated' => $feeAllocated,
            ], $feeAllocated
                ? 'Payment reconciled and allocated to student fees'
                : 'Transaction recorded (no student linked - fees not updated)');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getMpesaReconcileHistory($mpesaId)
    {
        try {
            $stmt = $this->db->prepare('SELECT mpesa_code FROM mpesa_transactions WHERE id = ? LIMIT 1');
            $stmt->execute([$mpesaId]);
            $mp = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$mp) {
                return $this->errorResponse('MPESA transaction not found', 404);
            }
            $code = $mp['mpesa_code'];

            $rows = $this->db->prepare(
                "SELECT pr.*, u.username AS reconciled_by_name, st.reference AS school_reference, st.transaction_date AS school_transaction_date
                 FROM payment_reconciliations pr
                 JOIN school_transactions st ON pr.transaction_id = st.id
                 LEFT JOIN users u ON pr.reconciled_by = u.id
                 WHERE st.source = 'mpesa' AND st.reference = ?
                 ORDER BY pr.reconciled_at DESC"
            );
            $rows->execute([$code]);

            return $this->successResponse(['history' => $rows->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function lookupByPhone($phone)
    {
        try {
            $normalizedPhone = $this->normalizePhoneNumber($phone);

            $results = [];
            $existingStudentIds = [];

            // 1. Via parents -> persons.phone -> student_parents -> students -> class chain
            $sql1 = "
                SELECT DISTINCT
                    s.id AS student_id,
                    s.admission_no,
                    pp.first_name,
                    pp.last_name,
                    e.academic_year_class_stream_id AS stream_id,
                    ayc.class_id,
                    c.name AS class_name,
                    st.name AS stream_name,
                    p.id AS parent_id,
                    parent_person.first_name AS parent_first_name,
                    parent_person.last_name AS parent_last_name,
                    parent_person.phone AS parent_phone,
                    sp.relationship,
                    'parent_record' AS match_source
                FROM persons parent_person
                JOIN parents p ON p.person_id = parent_person.id
                JOIN student_parents sp ON p.id = sp.parent_id
                JOIN students s ON sp.student_id = s.id
                JOIN persons pp ON pp.id = s.person_id
                LEFT JOIN student_academic_enrollments e ON e.student_id = s.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = e.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                WHERE (REPLACE(REPLACE(REPLACE(parent_person.phone, '+', ''), ' ', ''), '-', '') LIKE ?
                       OR parent_person.phone LIKE ?)
                  AND s.status = 'active'
            ";
            $phone254 = '254' . substr($normalizedPhone, -9);
            $phone07 = '0' . substr($normalizedPhone, -9);

            $stmt1 = $this->db->prepare($sql1);
            $stmt1->execute(['%' . $phone254 . '%', '%' . $phone . '%']);
            $parentMatches = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($parentMatches as $match) {
                $results[] = $match;
                $existingStudentIds[] = $match['student_id'];
            }

            // 2. M-Pesa history for previously linked phones
            $sql2 = "
                SELECT DISTINCT
                    s.id AS student_id,
                    s.admission_no,
                    pp.first_name,
                    pp.last_name,
                    e.academic_year_class_stream_id AS stream_id,
                    ayc.class_id,
                    c.name AS class_name,
                    st.name AS stream_name,
                    NULL AS parent_id,
                    m.first_name AS parent_first_name,
                    m.last_name AS parent_last_name,
                    m.phone_number AS parent_phone,
                    'M-Pesa payer' AS relationship,
                    'mpesa_history' AS match_source,
                    COUNT(*) AS payment_count,
                    MAX(m.transaction_date) AS last_payment_date,
                    SUM(m.amount) AS total_paid
                FROM mpesa_transactions m
                JOIN students s ON m.student_id = s.id
                JOIN persons pp ON pp.id = s.person_id
                LEFT JOIN student_academic_enrollments e ON e.student_id = s.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = e.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                WHERE (REPLACE(REPLACE(REPLACE(m.phone_number, '+', ''), ' ', ''), '-', '') LIKE ?
                       OR m.phone_number LIKE ?)
                  AND m.student_id IS NOT NULL
                  AND s.status = 'active'
                GROUP BY s.id, s.admission_no, pp.first_name, pp.last_name, e.academic_year_class_stream_id,
                         ayc.class_id, c.name, st.name,
                         m.first_name, m.last_name, m.phone_number
                ORDER BY payment_count DESC
            ";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['%' . $phone254 . '%', '%' . $phone . '%']);
            $mpesaMatches = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($mpesaMatches as $match) {
                if (!in_array($match['student_id'], $existingStudentIds)) {
                    $results[] = $match;
                }
            }

            return $this->successResponse([
                'phone_searched' => $phone,
                'normalized_phone' => $normalizedPhone,
                'students' => $results,
                'count' => count($results),
            ], count($results) . ' student(s) found for phone ' . $phone);
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function linkStudent($mpesaId, $studentId, $userId = null)
    {
        try {
            $checkMpesa = $this->db->prepare('SELECT id, mpesa_code, student_id, amount, status FROM mpesa_transactions WHERE id = ? LIMIT 1');
            $checkMpesa->execute([$mpesaId]);
            $mpesa = $checkMpesa->fetch(\PDO::FETCH_ASSOC);
            if (!$mpesa) {
                return $this->errorResponse('M-Pesa transaction not found', 404);
            }

            $checkStudent = $this->db->prepare(
                "SELECT s.id, s.admission_no, p.first_name, p.last_name
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ? AND s.status = 'active' LIMIT 1"
            );
            $checkStudent->execute([$studentId]);
            $student = $checkStudent->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->errorResponse('Student not found or not active', 404);
            }

            $upd = $this->db->prepare('UPDATE mpesa_transactions SET student_id = ?, bill_ref_number = ? WHERE id = ?');
            $upd->execute([$studentId, $student['admission_no'], $mpesaId]);

            return $this->successResponse([
                'mpesa_id' => $mpesaId,
                'mpesa_code' => $mpesa['mpesa_code'],
                'student_id' => $studentId,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'admission_no' => $student['admission_no'],
            ], 'Student linked successfully');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // =========================================================================
    // OUTBOUND M-PESA API TRIGGERS
    // These wrap the rebuilt Daraja services so every API is drivable from the
    // app in both sandbox and production. Async APIs return the acceptance and
    // their real results land on the webhooks below, audited in
    // payment_webhooks_log / disbursement_transactions (see getMpesaResults).
    // =========================================================================

    public function triggerStkPush(array $data): array
    {
        $service = new MpesaPaymentService();
        $result = $service->initiateSTKPush(
            $data['admission_no'] ?? $data['bill_ref_number'] ?? $data['account_reference'] ?? '',
            $data['phone'] ?? $data['phone_number'] ?? '',
            (float) ($data['amount'] ?? 0),
            $data['description'] ?? 'School Fees Payment'
        );
        return $result;
    }

    public function triggerStkQuery(array $data): array
    {
        $service = new MpesaPaymentService();
        return $service->queryTransactionStatus(
            $data['checkout_request_id'] ?? '',
            $data['phone'] ?? null
        );
    }

    public function triggerC2BRegister(array $data): array
    {
        $base = defined('MPESA_CALLBACK_BASE_URL') && MPESA_CALLBACK_BASE_URL !== ''
            ? MPESA_CALLBACK_BASE_URL
            : (defined('BASE_URL') ? BASE_URL : '');
        $service = new MpesaPaymentService();
        return $service->registerC2BUrls(
            $base . '/api/payments/c2b-validation',
            $base . '/api/payments/c2b-confirmation',
            $data['response_type'] ?? 'Completed'
        );
    }

    public function triggerC2BSimulate(array $data): array
    {
        if (!defined('MPESA_ENVIRONMENT') || MPESA_ENVIRONMENT !== 'sandbox') {
            return $this->errorResponse('C2B Simulate is a sandbox-only API and is not available in production.', 400);
        }
        $service = new MpesaPaymentService();
        return $service->simulateC2B(
            (float) ($data['amount'] ?? 0),
            $data['phone'] ?? $data['msisdn'] ?? '',
            $data['bill_ref_number'] ?? $data['account_reference'] ?? '',
            $data['command_id'] ?? 'CustomerPayBillOnline'
        );
    }

    public function triggerTransactionStatus(array $data): array
    {
        $service = new MpesaPaymentService();
        return $service->queryOfficialTransactionStatus(
            $data['transaction_id'] ?? $data['mpesa_code'] ?? '',
            $data['remarks'] ?? 'status query',
            $data['occasion'] ?? 'status'
        );
    }

    public function triggerAccountBalance(): array
    {
        $service = new MpesaPaymentService();
        return $service->queryAccountBalance();
    }

    public function triggerReversal(array $data): array
    {
        $service = new MpesaPaymentService();
        return $service->requestReversal(
            $data['transaction_id'] ?? $data['mpesa_code'] ?? '',
            (float) ($data['amount'] ?? 0),
            $data['receiver_party'] ?? $data['phone'] ?? '',
            $data['remarks'] ?? 'reversal',
            $data['occasion'] ?? 'reversal'
        );
    }

    public function triggerQR(array $data): array
    {
        $service = new MpesaPaymentService();
        return $service->generateDynamicQR(
            $data['merchant_name'] ?? (defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Academy'),
            $data['ref_no'] ?? $data['bill_ref_number'] ?? '',
            (float) ($data['amount'] ?? 0),
            $data['cpi'] ?? (defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '174379'),
            $data['trx_code'] ?? 'PB',
            $data['merchant_id'] ?? '',
            $data['size'] ?? '200'
        );
    }

    public function triggerB2B(array $data): array
    {
        $service = new MpesaPaymentService();
        return $service->b2bRemitTax(
            (float) ($data['amount'] ?? 0),
            $data['receiver_shortcode'] ?? '',
            $data['account_reference'] ?? $data['bill_ref_number'] ?? '',
            $data['remarks'] ?? 'B2B payment'
        );
    }

    public function triggerB2C(array $data): array
    {
        $service = new MpesaB2CService();
        $result = $service->sendPayment([
            'phone'      => $data['phone'] ?? $data['phone_number'] ?? '',
            'amount'     => (float) ($data['amount'] ?? 0),
            'command_id' => $data['command_id'] ?? 'BusinessPayment',
            'remarks'    => $data['remarks'] ?? 'Payment',
            'occasion'   => $data['occasion'] ?? '',
        ]);

        if (($result['status'] ?? '') !== 'success') {
            return $this->errorResponse(
                $result['message'] ?? 'B2C payment failed',
                400
            );
        }

        return $this->successResponse(
            [
                'transaction_ref' => $result['transaction_ref'] ?? null,
                'originator_conversation_id' => $result['originator_conversation_id'] ?? null,
                'response' => $result['response'] ?? null,
            ],
            $result['message'] ?? 'B2C payment submitted'
        );
    }

    /**
     * Extract the results of async M-Pesa APIs (B2C, transaction status,
     * account balance, reversal, B2B) plus recent ledger rows. Async results
     * are mirrored to the file-based payments log with the matching
     * disbursement_transactions / mpesa_transactions rows.
     */
    public function getMpesaResults(array $filters = []): array
    {
        try {
            $limit = min(max((int) ($filters['limit'] ?? 10), 1), 50);

            $webhooks = array_map(function (array $entry, int $i) {
                return [
                    'id' => $i,
                    'source' => $entry['source'] ?? null,
                    'status' => $entry['status'] ?? null,
                    'webhook_data' => $entry['webhook_data'] ?? null,
                    'created_at' => $entry['timestamp'] ?? null,
                ];
            }, array_values(array_filter(
                \App\API\Includes\FileLogger::recent('payments', 200),
                static function (array $entry): bool {
                    $source = $entry['source'] ?? null;
                    return in_array($source, ['mpesa_result', 'mpesa_b2c', 'payment_sms'], true);
                }
            )), range(1, $limit));

            $disbursements = $this->db->query(
                "SELECT id, channel, status, phone_number, amount,
                        transaction_ref, transaction_id,
                        conversation_id, originator_conversation_id, request_id,
                        callback_data, created_at
                 FROM disbursement_transactions
                 ORDER BY id DESC LIMIT {$limit}"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $transactions = $this->db->query(
                "SELECT id, mpesa_code, amount, phone_number, bill_ref_number,
                        status, transaction_type, created_at
                 FROM mpesa_transactions
                 ORDER BY id DESC LIMIT {$limit}"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $webhooks = array_slice($webhooks, 0, $limit);

            return $this->successResponse([
                'webhooks'        => $webhooks,
                'disbursements'   => $disbursements,
                'transactions'    => $transactions,
            ], 'M-Pesa results retrieved');
        } catch (Exception $e) {
            error_log('[PaymentsAPI] getMpesaResults error: ' . $e->getMessage());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }
}
