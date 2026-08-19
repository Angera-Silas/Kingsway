<?php

namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\payments\ReferenceNormalizer;

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

    public function listFinancialAccounts(): array
    {
        $stmt = $this->db->query("SELECT a.*, k.code account_kind, p.code provider_code, c.account_code ledger_code,
            GROUP_CONCAT(DISTINCT fp.code ORDER BY fp.code SEPARATOR ',') purposes,
            GROUP_CONCAT(DISTINCT fc.code ORDER BY fc.code SEPARATOR ',') channels
            FROM school_financial_accounts a
            JOIN financial_account_kinds k ON k.id=a.account_kind_id
            LEFT JOIN payment_providers p ON p.id=a.provider_id
            LEFT JOIN chart_of_accounts c ON c.id=a.ledger_account_id
            LEFT JOIN school_financial_account_purposes ap ON ap.financial_account_id=a.id
            LEFT JOIN financial_account_purposes fp ON fp.id=ap.purpose_id
            LEFT JOIN school_financial_account_channels ac ON ac.financial_account_id=a.id
            LEFT JOIN financial_channels fc ON fc.id=ac.channel_id
            GROUP BY a.id ORDER BY a.account_name");
        return formatResponse(true, ['accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function financialAccountSetupOptions(): array
    {
        return formatResponse(true, [
            'kinds' => $this->db->query('SELECT code,name FROM financial_account_kinds ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'purposes' => $this->db->query('SELECT code,name FROM financial_account_purposes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'channels' => $this->db->query('SELECT code,name FROM financial_channels ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'providers' => $this->db->query("SELECT code,display_name,environment FROM payment_providers WHERE active=1 ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC),
            'ledger_accounts' => $this->db->query("SELECT account_code,account_name FROM chart_of_accounts WHERE status='active' AND is_postable=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $this->db->query("SELECT id,name FROM roles WHERE id NOT IN (2) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function updateFinancialAccount(int $id, array $data, int $userId): array
    {
        $name=trim((string)($data['account_name']??'')); $identifier=trim((string)($data['account_identifier']??''));
        $purposes=array_values(array_filter((array)($data['purposes']??[]))); $channels=array_values(array_filter((array)($data['channels']??[])));
        if ($id<=0 || $name==='' || $identifier==='' || !$purposes || !$channels) return formatResponse(false,null,'Account name, identifier, purposes and channels are required');
        try {
            $this->db->beginTransaction();
            $old=$this->db->prepare('SELECT * FROM school_financial_accounts WHERE id=? FOR UPDATE'); $old->execute([$id]); $before=$old->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new Exception('Financial account not found');
            $ledger=$this->db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active' AND is_postable=1"); $ledger->execute([(string)($data['ledger_code']??$before['ledger_account_id'])]);
            $ledgerId=(int)$ledger->fetchColumn(); if(!$ledgerId) throw new Exception('A valid postable ledger account is required');
            $this->db->prepare('UPDATE school_financial_accounts SET account_name=?,account_identifier=?,normalized_account_identifier=?,bank_name=?,currency=?,ledger_account_id=? WHERE id=?')
                ->execute([$name,$identifier,(new ReferenceNormalizer())->accountIdentifier($identifier),$data['bank_name']??$before['bank_name'],$data['currency']??$before['currency'],$ledgerId,$id]);
            $this->db->prepare('DELETE FROM school_financial_account_purposes WHERE financial_account_id=?')->execute([$id]);
            $p=$this->db->prepare('SELECT id FROM financial_account_purposes WHERE code=?'); $pi=$this->db->prepare('INSERT INTO school_financial_account_purposes(financial_account_id,purpose_id) VALUES(?,?)');
            foreach($purposes as $code){$p->execute([$code]);$pid=(int)$p->fetchColumn();if(!$pid)throw new Exception('Unknown account purpose: '.$code);$pi->execute([$id,$pid]);}
            $this->db->prepare('DELETE FROM school_financial_account_channels WHERE financial_account_id=?')->execute([$id]);
            $c=$this->db->prepare('SELECT id FROM financial_channels WHERE code=?'); $ci=$this->db->prepare('INSERT INTO school_financial_account_channels(financial_account_id,channel_id) VALUES(?,?)');
            foreach($channels as $code){$c->execute([$code]);$cid=(int)$c->fetchColumn();if(!$cid)throw new Exception('Unknown financial channel: '.$code);$ci->execute([$id,$cid]);}
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,before_state,after_state) VALUES(?,?,?,?,?,?)')->execute([$userId,'updated','school_financial_account',$id,json_encode($before),json_encode($data)]);
            $this->db->commit(); return formatResponse(true,['id'=>$id],'Financial account updated; verification status unchanged.');
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return $this->handleException($e);}
    }

    public function financialAccountPermissions(int $id): array
    {
        $s=$this->db->prepare('SELECT p.id AS role_id,p.name,COALESCE(ap.can_receive,0) can_receive,COALESCE(ap.can_disburse,0) can_disburse FROM roles p LEFT JOIN school_financial_account_permissions ap ON ap.role_id=p.id AND ap.financial_account_id=? WHERE p.id NOT IN (2) ORDER BY p.name');
        $s->execute([$id]);
        return formatResponse(true,['permissions'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function createFinancialAccount(array $data, int $userId): array
    {
        $name=trim((string)($data['account_name']??'')); $kind=trim((string)($data['account_kind']??''));
        $identifier=trim((string)($data['account_identifier']??'')); $purposes=array_values(array_filter((array)($data['purposes']??[])));
        $channels=array_values(array_filter((array)($data['channels']??[])));
        if ($name===''||$kind===''||$identifier===''||!$purposes||!$channels) return formatResponse(false,null,'account_name, account_kind, account_identifier, purposes and channels are required');
        $normalizer=new ReferenceNormalizer(); $normalized=$normalizer->accountIdentifier($identifier);
        $this->db->beginTransaction();
        try {
            $kindStmt=$this->db->prepare('SELECT id FROM financial_account_kinds WHERE code=?');$kindStmt->execute([$kind]);$kindId=(int)$kindStmt->fetchColumn();
            if(!$kindId)throw new Exception('Unknown financial account kind');
            $providerId=null;if(!empty($data['provider_code'])){$s=$this->db->prepare('SELECT id FROM payment_providers WHERE code=? AND active=1 ORDER BY id DESC LIMIT 1');$s->execute([(string)$data['provider_code']]);$providerId=(int)$s->fetchColumn()?:null;}
            $ledgerId=null;if(!empty($data['ledger_code'])){$s=$this->db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active' AND is_postable=1");$s->execute([(string)$data['ledger_code']]);$ledgerId=(int)$s->fetchColumn()?:null;}
            if(!$ledgerId)throw new Exception('A valid postable ledger account is required');
            $i=$this->db->prepare("INSERT INTO school_financial_accounts(account_name,account_kind_id,provider_id,ledger_account_id,account_identifier,normalized_account_identifier,bank_name,currency,status,created_at) VALUES(?,?,?,?,?,?,?,?,'pending_verification',NOW())");
            $i->execute([$name,$kindId,$providerId,$ledgerId,$identifier,$normalized,$data['bank_name']??null,$data['currency']??'KES']);$id=(int)$this->db->lastInsertId();
            $p=$this->db->prepare('SELECT id FROM financial_account_purposes WHERE code=?');$pi=$this->db->prepare('INSERT INTO school_financial_account_purposes(financial_account_id,purpose_id) VALUES(?,?)');
            foreach($purposes as $code){$p->execute([(string)$code]);$purposeId=(int)$p->fetchColumn();if(!$purposeId)throw new Exception('Unknown account purpose: '.$code);$pi->execute([$id,$purposeId]);}
            $c=$this->db->prepare('SELECT id FROM financial_channels WHERE code=?');$ci=$this->db->prepare('INSERT INTO school_financial_account_channels(financial_account_id,channel_id) VALUES(?,?)');
            foreach($channels as $code){$c->execute([(string)$code]);$channelId=(int)$c->fetchColumn();if(!$channelId)throw new Exception('Unknown financial channel: '.$code);$ci->execute([$id,$channelId]);}
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,after_state) VALUES(?,?,?,?,?)')->execute([$userId,'created','school_financial_account',$id,json_encode(['name'=>$name,'kind'=>$kind,'purposes'=>$purposes,'channels'=>$channels])]);
            $this->db->commit();return formatResponse(true,['id'=>$id],'Financial account created and awaiting verification');
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return $this->handleException($e);}
    }

    public function verifyFinancialAccount(int $id, int $userId, string $status = 'active'): array
    {
        if (!in_array($status, ['active', 'suspended', 'closed'], true)) {
            return formatResponse(false, null, 'Invalid financial account status.');
        }
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('SELECT * FROM school_financial_accounts WHERE id = ? FOR UPDATE');
            $q->execute([$id]);
            $account = $q->fetch(PDO::FETCH_ASSOC);
            if (!$account) throw new Exception('Financial account not found.');
            $u = $this->db->prepare("UPDATE school_financial_accounts SET status=?, verified_by=?, verified_at=NOW() WHERE id=?");
            $u->execute([$status, $userId, $id]);
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,before_state,after_state) VALUES(?,?,?,?,?,?)')
                ->execute([$userId, 'status_changed', 'school_financial_account', $id, json_encode($account), json_encode(['status' => $status])]);
            $this->db->commit();
            return formatResponse(true, ['id' => $id, 'status' => $status], 'Financial account status updated.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function setFinancialAccountPermissions(int $id, array $permissions, int $userId): array
    {
        try {
            $this->db->beginTransaction();
            $check = $this->db->prepare('SELECT id FROM school_financial_accounts WHERE id=?');
            $check->execute([$id]);
            if (!$check->fetchColumn()) throw new Exception('Financial account not found.');
            $this->db->prepare('DELETE FROM school_financial_account_permissions WHERE financial_account_id=?')->execute([$id]);
            $insert = $this->db->prepare('INSERT INTO school_financial_account_permissions(financial_account_id,role_id,can_receive,can_disburse) VALUES(?,?,?,?)');
            foreach ($permissions as $permission) {
                $roleId = (int)($permission['role_id'] ?? 0);
                if (!$roleId) throw new Exception('Every account permission requires a role_id.');
                $insert->execute([$id, $roleId, !empty($permission['can_receive']) ? 1 : 0, !empty($permission['can_disburse']) ? 1 : 0]);
            }
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,after_state) VALUES(?,?,?,?,?)')
                ->execute([$userId, 'permissions_replaced', 'school_financial_account', $id, json_encode($permissions)]);
            $this->db->commit();
            return formatResponse(true, ['id' => $id], 'Financial account permissions updated.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
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
