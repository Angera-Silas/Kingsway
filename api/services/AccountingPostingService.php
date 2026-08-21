<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Single entry point for posting financial journals.
 * Operational modules create source documents; this service creates the
 * immutable accounting representation exactly once.
 */
final class AccountingPostingService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<int,array{account_code:string,debit?:string|float|int,credit?:string|float|int,description?:string,entity_type?:string,entity_id?:int}> $lines
     */
    public function post(
        string $sourceType,
        int $sourceId,
        string $batchType,
        string $description,
        array $lines,
        ?int $actorUserId = null,
        ?string $correlationId = null,
        ?string $periodCode = null
    ): array {
        if ($sourceType === '' || $sourceId <= 0 || $batchType === '' || $description === '') {
            throw new RuntimeException('Accounting source, batch type and description are required.');
        }
        if (!$lines) {
            throw new RuntimeException('A journal must contain at least two lines.');
        }

        $normalised = [];
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $code = trim((string) ($line['account_code'] ?? ''));
            $debit = $this->cents($line['debit'] ?? 0);
            $credit = $this->cents($line['credit'] ?? 0);
            if ($code === '' || (($debit > 0) === ($credit > 0))) {
                throw new RuntimeException('Each journal line must contain exactly one positive debit or credit.');
            }
            $debits += $debit;
            $credits += $credit;
            $normalised[] = [
                'account_code' => $code,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
                'entity_type' => $line['entity_type'] ?? null,
                'entity_id' => isset($line['entity_id']) ? (int) $line['entity_id'] : null,
            ];
        }
        if ($debits !== $credits) {
            throw new RuntimeException('Journal is not balanced. Debits and credits must be equal.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare('SELECT j.id,j.batch_number,j.status FROM accounting_source_links l JOIN accounting_journal_batches j ON j.id=l.journal_batch_id WHERE l.source_type=? AND l.source_id=? FOR UPDATE');
            $existing->execute([$sourceType, $sourceId]);
            $already = $existing->fetch(PDO::FETCH_ASSOC);
            if ($already) {
                if ($ownsTransaction) $this->db->commit();
                return ['status' => 'already_posted'] + $already;
            }

            $period = $this->lockPeriod($periodCode);
            $accountIds = [];
            $accountQuery = $this->db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active' AND is_postable=1 LIMIT 1");
            foreach ($normalised as $line) {
                $accountQuery->execute([$line['account_code']]);
                $id = $accountQuery->fetchColumn();
                if (!$id) throw new RuntimeException('Unknown or non-postable chart account: ' . $line['account_code']);
                $accountIds[] = (int) $id;
            }

            $correlationId = $correlationId ?: $this->uuid();
            $batchNumber = 'JNL-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $insertBatch = $this->db->prepare('INSERT INTO accounting_journal_batches (batch_number,accounting_period_id,batch_type,status,currency,description,correlation_id,created_by) VALUES (?,?,?,\'draft\',\'KES\',?,?,?)');
            $insertBatch->execute([$batchNumber, $period['id'], $batchType, $description, $correlationId, $actorUserId]);
            $batchId = (int) $this->db->lastInsertId();

            $insertLine = $this->db->prepare('INSERT INTO accounting_journal_lines (journal_batch_id,line_number,chart_account_id,description,debit_amount,credit_amount,entity_type,entity_id) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($normalised as $index => $line) {
                $insertLine->execute([$batchId, $index + 1, $accountIds[$index], $line['description'], $line['debit'] / 100, $line['credit'] / 100, $line['entity_type'], $line['entity_id']]);
            }
            $this->db->prepare('INSERT INTO accounting_source_links (journal_batch_id,source_type,source_id,source_reference) VALUES (?,?,?,?)')->execute([$batchId, $sourceType, $sourceId, $description]);
            $this->db->prepare("UPDATE accounting_journal_batches SET status='posted',posted_by=?,posted_at=NOW() WHERE id=? AND status='draft'")->execute([$actorUserId, $batchId]);
            $this->db->prepare('INSERT INTO accounting_audit_events (actor_user_id,action,entity_type,entity_id,correlation_id,after_state) VALUES (?,?,?,?,?,?)')->execute([$actorUserId, 'posted', 'journal_batch', $batchId, $correlationId, json_encode(['source_type' => $sourceType, 'source_id' => $sourceId, 'debits' => $debits / 100, 'credits' => $credits / 100])]);
            if ($ownsTransaction) $this->db->commit();
            return ['status' => 'posted', 'journal_batch_id' => $batchId, 'batch_number' => $batchNumber, 'correlation_id' => $correlationId];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function lockPeriod(?string $periodCode): array
    {
        $periodCode = $periodCode ?: date('Y-m');
        $s = $this->db->prepare('SELECT * FROM accounting_periods WHERE period_code=? FOR UPDATE');
        $s->execute([$periodCode]);
        $period = $s->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            $start = date('Y-m-01');
            $end = date('Y-m-t');
            $this->db->prepare("INSERT INTO accounting_periods (period_code,starts_on,ends_on,status) VALUES (?,?,?,'open')")->execute([$periodCode, $start, $end]);
            $s->execute([$periodCode]);
            $period = $s->fetch(PDO::FETCH_ASSOC);
        }
        if (!$period || $period['status'] !== 'open') throw new RuntimeException('Accounting period is not open.');
        return $period;
    }

    private function cents($value): int
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) throw new RuntimeException('Invalid monetary amount.');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
