<?php

namespace App\API\Services;

use App\Database\Database;
use InvalidArgumentException;
use PDO;

/**
 * Student clearance: every learner who LEAVES in an academic year
 * (graduated, transferred, withdrawn) is cleared by each department before
 * the leaving certificate and other certificates are issued.
 *
 * Checks (student_clearances.clearance_type): finance, library, uniform,
 * property (mandatory) and academic (records; not mandatory). Each is
 * pending, cleared or blocked, with a note and who checked it.
 *
 * Finance follows the learner's fee account for the year they left: nothing
 * owed and no overpayment clears it; a balance stays pending until it is
 * paid, and an overpayment stays pending until it is refunded or transferred.
 * The amount is shown on the check.
 *
 * A learner can be cleared once (the rows carry transfer_request_id NULL;
 * transfer requests keep their own rows).
 */
class StudentClearanceService
{
    public const CHECKS = [
        'finance' => ['label' => 'Finance', 'mandatory' => true],
        'library' => ['label' => 'Library', 'mandatory' => true],
        'uniform' => ['label' => 'Uniform', 'mandatory' => true],
        'property' => ['label' => 'School property', 'mandatory' => true],
        'academic' => ['label' => 'Academic records', 'mandatory' => false],
    ];
    private const LEAVING = "('graduated','transferred','withdrawn')";

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * The learners who left in the year, with their outcome, final fee
     * position and clearance progress.
     */
    public function leavers(int $yearId, array $filters = []): array
    {
        $year = $this->year($yearId);
        $where = "lp.academic_year_id = ? AND lp.enrollment_status IN " . self::LEAVING;
        $params = [$yearId];
        if (!empty($filters['outcome']) && in_array($filters['outcome'], ['graduated', 'transferred', 'withdrawn'], true)) {
            $where .= ' AND lp.enrollment_status = ?';
            $params[] = $filters['outcome'];
        }
        $rows = $this->rows(
            "SELECT lp.student_id, lp.enrollment_id, lp.enrollment_status, lp.class_name, lp.stream_name
             FROM " . ReadReplicaService::qualifiedRef('learner_placement_history') . " lp
             WHERE {$where}",
            $params
        );
        if (!$rows) {
            return ['year' => $year, 'learners' => [], 'checks' => self::CHECKS, 'summary' => $this->summarize([])];
        }
        $ids = array_map(static fn ($r) => (int) $r['student_id'], $rows);
        $in = implode(',', $ids);

        $people = [];
        foreach ($this->rows("SELECT student_id, admission_no, full_name FROM " . ReadReplicaService::qualifiedRef('person_directory') . " WHERE student_id IN ({$in})") as $p) {
            $people[(int) $p['student_id']] = $p;
        }
        $balances = $this->balances($ids, $yearId);
        $checks = $this->checks($ids);

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r['student_id'];
            $name = trim((string) ($people[$sid]['full_name'] ?? ''));
            $adm = (string) ($people[$sid]['admission_no'] ?? '');
            if ($search !== '' && !str_contains(mb_strtolower($name . ' ' . $adm), $search)) continue;
            $out[] = $this->learnerRow($r, $name, $adm, $balances[$sid] ?? 0.0, $checks[$sid] ?? [], $year);
        }
        $state = (string) ($filters['state'] ?? '');
        if ($state !== '') {
            $out = array_values(array_filter($out, static fn ($l) => $l['state'] === $state));
        }
        usort($out, static fn ($a, $b) => [$a['state_order'], $a['class'], $a['name']] <=> [$b['state_order'], $b['class'], $b['name']]);
        return ['year' => $year, 'learners' => $out, 'checks' => self::CHECKS, 'summary' => $this->summarize($out)];
    }

    /** One learner's clearance (for the detail panel and the certificate). */
    public function learner(int $studentId, int $yearId): array
    {
        foreach ($this->leavers($yearId)['learners'] as $l) {
            if ($l['student_id'] === $studentId) return $l;
        }
        throw new InvalidArgumentException('This learner did not leave in the selected year.', 404);
    }

    /**
     * Open the clearance: one row per check, finance set from the account.
     * Re-running refreshes the finance check from the current balance.
     */
    public function start(int $studentId, int $yearId, ?int $userId): array
    {
        $learner = $this->learner($studentId, $yearId);
        $existing = $this->checks([$studentId])[$studentId] ?? [];
        $insert = $this->db->prepare(
            "INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status, amount_outstanding, notes, checked_by, checked_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?)"
        );
        $this->db->beginTransaction();
        try {
            foreach (array_keys(self::CHECKS) as $type) {
                if ($type === 'finance') {
                    [$status, $amount, $note] = $this->financeCheck($learner['balance']);
                    if (isset($existing[$type])) {
                        // keep a manual decision on an account that no longer owes
                        if ($existing[$type]['status'] !== 'cleared' || $status !== 'cleared') {
                            $this->db->prepare(
                                "UPDATE student_clearances SET status = ?, amount_outstanding = ?, notes = ?, checked_by = ?, checked_at = ?
                                 WHERE id = ?"
                            )->execute([$status, $amount, $note, $status === 'cleared' ? $userId : null, $status === 'cleared' ? date('Y-m-d H:i:s') : null, $existing[$type]['id']]);
                        }
                        continue;
                    }
                    $insert->execute([$studentId, $type, $status, $amount, $note, $status === 'cleared' ? $userId : null, $status === 'cleared' ? date('Y-m-d H:i:s') : null]);
                    continue;
                }
                if (!isset($existing[$type])) {
                    $insert->execute([$studentId, $type, 'pending', 0, null, null, null]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->learner($studentId, $yearId);
    }

    /** Record one department's decision: cleared, blocked (with a reason) or pending. */
    public function decide(int $studentId, int $yearId, string $type, string $status, ?string $note, ?int $userId): array
    {
        if (!isset(self::CHECKS[$type])) throw new InvalidArgumentException('Unknown clearance check.', 422);
        if (!in_array($status, ['pending', 'cleared', 'blocked'], true)) throw new InvalidArgumentException('Unknown clearance status.', 422);
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
        if ($status === 'blocked' && ($note === null || $note === '')) {
            throw new InvalidArgumentException('Give the reason the learner cannot be cleared.', 422);
        }
        $learner = $this->learner($studentId, $yearId);
        if ($type === 'finance' && $status === 'cleared' && abs((float) $learner['balance']) >= 0.01 && ($note === null || $note === '')) {
            throw new InvalidArgumentException('The fee account is not settled. Record how it was settled (paid, refunded, transferred, written off) to clear finance.', 422);
        }
        if (!isset($this->checks([$studentId])[$studentId][$type])) {
            $this->start($studentId, $yearId, $userId);
        }
        $this->db->prepare(
            "UPDATE student_clearances SET status = ?, notes = ?, checked_by = ?, checked_at = NOW()
             WHERE student_id = ? AND transfer_request_id IS NULL AND clearance_type = ?"
        )->execute([$status, $note, $userId, $studentId, $type]);
        return $this->learner($studentId, $yearId);
    }

    private function learnerRow(array $r, string $name, string $adm, float $balance, array $checks, array $year): array
    {
        $items = [];
        $mandatoryOpen = 0;
        $blocked = 0;
        foreach (self::CHECKS as $type => $def) {
            $c = $checks[$type] ?? null;
            $status = $c['status'] ?? 'not_started';
            if ($def['mandatory'] && $status !== 'cleared') $mandatoryOpen++;
            if ($status === 'blocked') $blocked++;
            $items[] = [
                'type' => $type,
                'label' => $def['label'],
                'mandatory' => $def['mandatory'],
                'status' => $status,
                'note' => $c['notes'] ?? null,
                'amount' => isset($c['amount_outstanding']) ? (float) $c['amount_outstanding'] : null,
                'checked_at' => $c['checked_at'] ?? null,
            ];
        }
        $started = (bool) $checks;
        $state = !$started ? 'not_started' : ($blocked ? 'blocked' : ($mandatoryOpen ? 'in_progress' : 'cleared'));
        $order = ['blocked' => 0, 'in_progress' => 1, 'not_started' => 2, 'cleared' => 3][$state];
        $status = (string) $r['enrollment_status'];
        $calendar = (int) substr((string) $year['start_date'], 0, 4);
        $outcome = $status === 'graduated' ? "Graduated, class of {$calendar}" : ucfirst($status);
        $money = number_format(abs($balance), 2);
        return [
            'student_id' => (int) $r['student_id'],
            'name' => $name !== '' ? $name : 'Learner ' . (int) $r['student_id'],
            'admission_no' => $adm,
            'class' => trim($r['class_name'] . ' ' . ($r['stream_name'] ?? '')),
            'outcome' => $outcome,
            'outcome_status' => $status,
            'balance' => round($balance, 2),
            'balance_label' => $balance > 0 ? "Owes KES {$money}" : ($balance < 0 ? "Overpaid KES {$money}" : 'Account settled'),
            'checks' => $items,
            'state' => $state,
            'state_order' => $order,
            'certificate_ready' => $state === 'cleared',
        ];
    }

    private function financeCheck(float $balance): array
    {
        $money = number_format(abs($balance), 2);
        if ($balance >= 0.01) return ['pending', round($balance, 2), "Balance of KES {$money} to be paid"];
        if ($balance <= -0.01) return ['pending', round($balance, 2), "Overpayment of KES {$money} to be refunded or transferred"];
        return ['cleared', 0, 'Fee account settled'];
    }

    /** Final fee position of each learner for the year they left (the year's balance; balances are never summed across years). */
    private function balances(array $ids, int $yearId): array
    {
        $out = [];
        foreach ($this->rows(
            "SELECT student_id, SUM(current_balance) AS balance FROM " . ReadReplicaService::qualifiedRef('fee_status_summary') . "
             WHERE academic_year_id = ? AND student_id IN (" . implode(',', array_map('intval', $ids)) . ") GROUP BY student_id",
            [$yearId]
        ) as $b) {
            $out[(int) $b['student_id']] = (float) $b['balance'];
        }
        return $out;
    }

    /** [student][type] => row, live (the page writes and re-reads at once). */
    private function checks(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $out = [];
        foreach ($this->rows(
            "SELECT id, student_id, clearance_type, status, amount_outstanding, notes, checked_at
             FROM student_clearances
             WHERE transfer_request_id IS NULL AND student_id IN (" . implode(',', $ids) . ")
             ORDER BY id"
        ) as $c) {
            $out[(int) $c['student_id']][$c['clearance_type']] = $c;
        }
        return $out;
    }

    private function summarize(array $learners): array
    {
        $s = ['leavers' => count($learners), 'not_started' => 0, 'in_progress' => 0, 'blocked' => 0, 'cleared' => 0, 'owing' => 0.0, 'overpaid' => 0.0];
        foreach ($learners as $l) {
            $s[$l['state']]++;
            if ($l['balance'] > 0) $s['owing'] += $l['balance'];
            if ($l['balance'] < 0) $s['overpaid'] += -$l['balance'];
        }
        $s['owing'] = round($s['owing'], 2);
        $s['overpaid'] = round($s['overpaid'], 2);
        return $s;
    }

    private function year(int $yearId): array
    {
        $row = $this->rows('SELECT id, year_code, start_date, end_date FROM academic_years WHERE id = ?', [$yearId])[0] ?? null;
        if (!$row) throw new InvalidArgumentException('Academic year not found.', 404);
        return ['id' => (int) $row['id'], 'code' => $row['year_code'], 'start_date' => $row['start_date'], 'class_of' => (int) substr((string) $row['start_date'], 0, 4)];
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
