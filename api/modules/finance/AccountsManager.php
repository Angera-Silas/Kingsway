<?php

namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * AccountsManager
 *
 * Bank account and bank transaction business logic.
 */
class AccountsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('finance');
    }

    /**
     * List active bank accounts, falling back to bank transactions when none are defined.
     */
    public function listBankAccounts()
    {
        try {
            $stmt = $this->db->query('SELECT id, name, account_no, bank_name, is_active FROM bank_accounts WHERE is_active = 1 ORDER BY name');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if (empty($rows)) {
                $stmt = $this->db->query('SELECT DISTINCT bank_name AS name, account_number AS account_no FROM bank_transactions WHERE bank_name IS NOT NULL ORDER BY bank_name');
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }

            return formatResponse(true, ['bank_accounts' => $rows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new bank account.
     */
    public function createBankAccount($data)
    {
        try {
            $name = $data['name'] ?? null;
            $accountNo = $data['account_no'] ?? null;

            if (!$name || !$accountNo) {
                return formatResponse(false, null, 'Missing required fields');
            }

            $stmt = $this->db->prepare('INSERT INTO bank_accounts (name, account_no, bank_name, is_active, created_at) VALUES (?, ?, ?, 1, NOW())');
            $stmt->execute([$name, $accountNo, $data['bank'] ?? $data['bank_name'] ?? null]);

            return formatResponse(true, ['id' => $this->db->lastInsertId()], 'Bank account created');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * List bank transactions, optionally filtered by account number or bank name.
     */
    public function listBankTransactions($bankId = null)
    {
        try {
            if ($bankId) {
                $stmt = $this->db->prepare('SELECT * FROM bank_transactions WHERE account_number = ? OR bank_name = ? ORDER BY transaction_date DESC LIMIT 500');
                $stmt->execute([$bankId, $bankId]);
            } else {
                $stmt = $this->db->query('SELECT * FROM bank_transactions ORDER BY transaction_date DESC LIMIT 500');
            }
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return formatResponse(true, ['transactions' => $rows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a manual bank transaction entry.
     */
    public function createBankTransaction($data)
    {
        try {
            $amount = $data['amount'] ?? null;
            if ($amount === null || !is_numeric($amount)) {
                return formatResponse(false, null, 'A valid amount is required');
            }

            $transactionRef = $data['reference'] ?? $data['transaction_ref'] ?? 'BT-' . date('YmdHis');
            $bankName = $data['bank_name'] ?? null;
            $accountNumber = $data['account_number'] ?? null;
            $status = $data['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'processed', 'failed'], true)) {
                $status = 'pending';
            }

            if (!empty($data['account_id'])) {
                $stmt = $this->db->prepare('SELECT bank_name, account_no FROM bank_accounts WHERE id = ? LIMIT 1');
                $stmt->execute([(int) $data['account_id']]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($account) {
                    $bankName = $bankName ?? $account['bank_name'];
                    $accountNumber = $accountNumber ?? $account['account_no'];
                }
            }

            $stmt = $this->db->prepare(
                'INSERT INTO bank_transactions
                    (transaction_ref, amount, transaction_date, bank_name, account_number, narration,
                     sender_name, sender_account, source_type, status, reconciled, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "manual_entry", ?, 0, NOW())'
            );
            $stmt->execute([
                $transactionRef,
                $amount,
                $data['date'] ?? $data['transaction_date'] ?? date('Y-m-d H:i:s'),
                $bankName,
                $accountNumber,
                $data['description'] ?? $data['narration'] ?? null,
                $data['sender_name'] ?? null,
                $data['sender_account'] ?? null,
                $status,
            ]);

            return formatResponse(true, ['id' => $this->db->lastInsertId()], 'Bank transaction recorded');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update a manual bank transaction, or mark it as reconciled.
     */
    public function updateBankTransaction($id, $data)
    {
        try {
            $id = (int) $id;

            if (!empty($data['reconciled'])) {
                // Only flip the reconciled flag. Do NOT set status='processed':
                // trg_bank_payment_processed fires on status='processed' with a
                // student_id and would credit the student fee balance a second time.
                $stmt = $this->db->prepare('UPDATE bank_transactions SET reconciled = 1, reconciled_at = NOW() WHERE id = ?');
                $stmt->execute([$id]);
                return formatResponse(true, ['id' => $id], 'Transaction reconciled');
            }

            $stmt = $this->db->prepare(
                'UPDATE bank_transactions
                    SET transaction_ref = ?, amount = ?, transaction_date = ?, bank_name = ?,
                        account_number = ?, narration = ?, sender_name = ?, sender_account = ?
                  WHERE id = ? AND source_type = "manual_entry"'
            );
            $stmt->execute([
                $data['reference'] ?? $data['transaction_ref'] ?? null,
                $data['amount'] ?? null,
                $data['date'] ?? $data['transaction_date'] ?? null,
                $data['bank_name'] ?? null,
                $data['account_number'] ?? null,
                $data['description'] ?? $data['narration'] ?? null,
                $data['sender_name'] ?? null,
                $data['sender_account'] ?? null,
                $id,
            ]);

            if ($stmt->rowCount() === 0) {
                return formatResponse(false, null, 'Transaction not found or not editable');
            }

            return formatResponse(true, ['id' => $id], 'Transaction updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete a manual bank transaction entry.
     */
    public function deleteBankTransaction($id)
    {
        try {
            $id = (int) $id;
            $stmt = $this->db->prepare('DELETE FROM bank_transactions WHERE id = ? AND source_type = "manual_entry"');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return formatResponse(false, null, 'Transaction not found or not deletable');
            }

            return formatResponse(true, ['id' => $id], 'Transaction deleted');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
