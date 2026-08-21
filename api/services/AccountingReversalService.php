<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Creates immutable reversing journals; posted source lines are never edited. */
class AccountingReversalService
{
    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    public function reverse(int $batchId, string $reason, int $userId): array
    {
        if ($batchId <= 0 || trim($reason) === '') throw new RuntimeException('A journal batch and reversal reason are required.');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare("SELECT id,status,currency,description,created_by FROM accounting_journal_batches WHERE id=? FOR UPDATE");
            $q->execute([$batchId]); $batch = $q->fetch(PDO::FETCH_ASSOC);
            if (!$batch || $batch['status'] !== 'posted') throw new RuntimeException('Only a posted journal can be reversed.');
            $check = $this->db->prepare('SELECT id FROM accounting_reversals WHERE original_journal_batch_id=? UNION SELECT id FROM accounting_reversal_reasons WHERE original_journal_batch_id=?');
            $check->execute([$batchId, $batchId]);
            if ($check->fetchColumn()) throw new RuntimeException('This journal already has a reversal request.');
            $lines = $this->db->prepare('SELECT chart_account_id,description,debit_amount,credit_amount FROM accounting_journal_lines WHERE journal_batch_id=? ORDER BY line_number');
            $lines->execute([$batchId]); $rows = $lines->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) throw new RuntimeException('Journal has no lines.');
            $correlation = sprintf('%s-%s', $batchId, bin2hex(random_bytes(8)));
            $this->db->prepare("INSERT INTO accounting_journal_batches(batch_number,accounting_period_id,batch_type,status,currency,description,correlation_id,created_by,posted_by,posted_at) SELECT ?,accounting_period_id,'reversal','posted',currency,?,?,?, ?,NOW() FROM accounting_journal_batches WHERE id=?")
                ->execute(['REV-'.$batchId.'-'.date('YmdHis'), $correlation, 'Reversal: '.$reason, $userId, $userId, $batchId]);
            $reversalId = (int)$this->db->lastInsertId();
            $insert = $this->db->prepare('INSERT INTO accounting_journal_lines(journal_batch_id,line_number,chart_account_id,description,debit_amount,credit_amount,entity_type,entity_id) VALUES(?,?,?,?,?,?,?,?)');
            $n=1; foreach ($rows as $line) $insert->execute([$reversalId,$n++,(int)$line['chart_account_id'],$line['description'], $line['credit_amount'], $line['debit_amount'],'reversal',$batchId]);
            $this->db->prepare('INSERT INTO accounting_reversals(original_journal_batch_id,reversal_journal_batch_id,reason,created_by) VALUES(?,?,?,?)')->execute([$batchId,$reversalId,$reason,$userId]);
            $this->db->prepare("UPDATE accounting_journal_batches SET status='reversed' WHERE id=?")->execute([$batchId]);
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,reason,after_state) VALUES(?,?,?,?,?,?)')->execute([$userId,'reversed','accounting_journal_batch',$batchId,$reason,json_encode(['reversal_batch_id'=>$reversalId])]);
            $this->db->commit(); return ['original_batch_id'=>$batchId,'reversal_batch_id'=>$reversalId,'status'=>'posted'];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
