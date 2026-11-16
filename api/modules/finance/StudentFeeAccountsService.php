<?php

namespace App\API\Modules\finance;

use App\API\Services\ReadReplicaService;
use App\API\Services\YearScope;
use PDO;

/**
 * Student fee accounts: ONE row per learner for ONE academic year.
 *
 * The fee summary holds a row per learner per term; listing it directly
 * showed every learner three times. Here each learner is one row with the
 * year's totals and a column per term of THAT year (academic_year_terms.id,
 * never the term number alone). Choosing a term scopes the figures and the
 * status to that term. Balances are never summed across years: a year's
 * first term already carries the balance brought forward.
 *
 * Status of the scope: credit (balance < 0), paid (nothing owed), partial
 * (something paid, something owed), pending (nothing paid yet).
 */
class StudentFeeAccountsService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(array $filters): array
    {
        $scope = (new YearScope($this->db))->fromRequest($filters, YearScope::TERM_OPTIONAL);
        $yearId = $scope['year_id'];
        $termId = $scope['year_term_id'];
        $terms = $this->terms($yearId);
        $source = ReadReplicaService::qualifiedRef('fee_status_summary');
        // What makes up each term's bill: the balance brought forward from
        // the previous year and the scholarship (sponsorship) amount, per
        // learner and term. "Expected" = new fees + brought forward;
        // "waived" = waivers + scholarships.
        $parts = "(SELECT sae.student_id, sfo.academic_year_term_id,
                          SUM(COALESCE(sfo.opening_arrears_amount, 0)) AS brought_forward,
                          SUM(COALESCE(sfo.sponsored_waiver_amount, 0)) AS sponsored
                   FROM " . ReadReplicaService::qualifiedRef('student_fee_obligations') . " sfo
                   JOIN " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae ON sae.id = sfo.student_academic_enrollment_id
                   WHERE sfo.academic_year_id = " . (int) $yearId . "
                   GROUP BY sae.student_id, sfo.academic_year_term_id)";
        // Credit applied to each term, split: last year's overpayment carried
        // forward (rollover credit notes, ROL-...) and every other credit.
        $credits = "(SELECT sae.student_id, sfo.academic_year_term_id,
                            SUM(CASE WHEN n.credit_number LIKE 'ROL-%' THEN fca.applied_amount ELSE 0 END) AS credit_bf
                     FROM fee_credit_applications fca
                     JOIN " . ReadReplicaService::qualifiedRef('fee_credit_notes') . " n ON n.id = fca.fee_credit_note_id
                     JOIN " . ReadReplicaService::qualifiedRef('student_fee_obligations') . " sfo ON sfo.id = fca.student_fee_obligation_id
                     JOIN " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae ON sae.id = sfo.student_academic_enrollment_id
                     WHERE sfo.academic_year_id = " . (int) $yearId . "
                     GROUP BY sae.student_id, sfo.academic_year_term_id)";

        $where = ['f.academic_year_id = ?'];
        $params = [$yearId];
        if (!empty($filters['class_id']) && ctype_digit((string) $filters['class_id'])) {
            $where[] = 'f.class_id = ?';
            $params[] = (int) $filters['class_id'];
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(f.admission_no LIKE ? OR f.student_name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Per-term columns of this year, and the figures of the scope
        // (the chosen term, or the whole year).
        $termCols = [];
        foreach ($terms as $t) {
            $id = (int) $t['academic_year_term_id'];
            $termCols[] = "SUM(CASE WHEN f.academic_year_term_id = {$id} THEN f.total_due ELSE 0 END) AS t{$id}_due,
                           SUM(CASE WHEN f.academic_year_term_id = {$id} THEN f.total_paid ELSE 0 END) AS t{$id}_paid,
                           SUM(CASE WHEN f.academic_year_term_id = {$id} THEN f.current_balance ELSE 0 END) AS t{$id}_balance";
        }
        $in = $termId ? "f.academic_year_term_id = " . (int) $termId : '1=1';
        $scopeCols = "SUM(CASE WHEN {$in} THEN f.total_due ELSE 0 END) AS total_due,
                      SUM(CASE WHEN {$in} THEN f.total_paid ELSE 0 END) AS total_paid,
                      SUM(CASE WHEN {$in} THEN f.total_waived ELSE 0 END) AS total_waived,
                      SUM(CASE WHEN {$in} THEN f.total_credited ELSE 0 END) AS total_credited,
                      SUM(CASE WHEN {$in} THEN f.current_balance ELSE 0 END) AS current_balance,
                      SUM(CASE WHEN {$in} AND f.current_balance < 0 THEN -f.current_balance ELSE 0 END) AS total_overpaid,
                      SUM(CASE WHEN {$in} THEN COALESCE(o.brought_forward, 0) ELSE 0 END) AS brought_forward,
                      SUM(CASE WHEN {$in} THEN COALESCE(o.sponsored, 0) ELSE 0 END) AS scholarship,
                      SUM(CASE WHEN {$in} THEN COALESCE(cr.credit_bf, 0) ELSE 0 END) AS credit_bf";
        $grouped = "SELECT f.student_id AS id, MAX(f.admission_no) AS admission_no, MAX(f.student_name) AS student_name,
                           MAX(f.class_id) AS class_id, MAX(f.class_name) AS class_name, MAX(f.student_type) AS student_type,
                           {$scopeCols}" . ($termCols ? ', ' . implode(', ', $termCols) : '') . "
                    FROM {$source} f
                    LEFT JOIN {$parts} o ON o.student_id = f.student_id AND o.academic_year_term_id = f.academic_year_term_id
                    LEFT JOIN {$credits} cr ON cr.student_id = f.student_id AND cr.academic_year_term_id = f.academic_year_term_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY f.student_id";
        $status = "CASE WHEN g.current_balance < 0 OR g.total_overpaid > 0 AND g.current_balance <= 0 THEN 'credit'
                        WHEN g.current_balance = 0 THEN 'paid'
                        WHEN g.total_paid > 0 THEN 'partial'
                        ELSE 'pending' END";

        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        $having = '';
        $outerParams = $params;
        if (in_array($statusFilter, ['credit', 'paid', 'partial', 'pending'], true)) {
            $having = " WHERE {$status} = ?";
            $outerParams[] = $statusFilter;
        }

        $totals = $this->row(
            "SELECT COUNT(*) AS learners, COALESCE(SUM(g.total_due),0) AS total_due, COALESCE(SUM(g.total_paid),0) AS total_paid,
                    COALESCE(SUM(g.total_waived),0) AS total_waived, COALESCE(SUM(g.total_credited),0) AS total_credited,
                    COALESCE(SUM(g.current_balance),0) AS total_balance,
                    COALESCE(SUM(GREATEST(g.current_balance,0)),0) AS total_owed,
                    COALESCE(SUM(g.total_overpaid),0) AS total_overpaid,
                    COALESCE(SUM(g.brought_forward),0) AS brought_forward,
                    COALESCE(SUM(g.scholarship),0) AS scholarship,
                    COALESCE(SUM(g.scholarship > 0),0) AS learners_sponsored,
                    COALESCE(SUM(g.credit_bf),0) AS credit_bf,
                    COALESCE(SUM(g.current_balance > 0),0) AS learners_owing,
                    COALESCE(SUM(g.current_balance < 0),0) AS learners_in_credit,
                    COALESCE(SUM(g.current_balance = 0),0) AS learners_cleared,
                    COALESCE(SUM(g.total_overpaid > 0),0) AS learners_overpaid,
                    COALESCE(SUM(g.total_waived > 0),0) AS learners_waived
             FROM ({$grouped}) g{$having}",
            $outerParams
        );

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 25)));
        $rows = $this->rows(
            "SELECT g.*, {$status} AS payment_status FROM ({$grouped}) g{$having}
             ORDER BY g.class_id, g.student_name LIMIT {$limit} OFFSET " . (($page - 1) * $limit),
            $outerParams
        );
        foreach ($rows as &$r) {
            $byTerm = [];
            foreach ($terms as $t) {
                $id = (int) $t['academic_year_term_id'];
                $byTerm[$id] = [
                    'due' => (float) $r["t{$id}_due"],
                    'paid' => (float) $r["t{$id}_paid"],
                    'balance' => (float) $r["t{$id}_balance"],
                ];
                unset($r["t{$id}_due"], $r["t{$id}_paid"], $r["t{$id}_balance"]);
            }
            $r['terms'] = $byTerm;
            foreach (['total_due', 'total_paid', 'total_waived', 'total_credited', 'current_balance', 'total_overpaid', 'brought_forward', 'scholarship', 'credit_bf'] as $k) {
                $r[$k] = (float) $r[$k];
            }
            // The account, line by line: brought forward + new fees
            // - scholarship - waiver - credit notes - paid = balance.
            $r['new_fees'] = round($r['total_due'] - $r['brought_forward'], 2);
            $r['waiver'] = round($r['total_waived'] - $r['scholarship'], 2);
            $r['is_sponsored'] = $r['scholarship'] > 0 ? 1 : 0;
            // Opening position from last year: arrears owed less overpayment
            // carried as credit; other credits are adjustments this year.
            $r['other_credits'] = round($r['total_credited'] - $r['credit_bf'], 2);
            $r['opening_balance'] = round($r['brought_forward'] - $r['credit_bf'], 2);
            // Money received: the view allocates payments up to each term's
            // bill; anything paid beyond the year's bill is the overpayment
            // (the negative balance), so it is added back here.
            $r['amount_received'] = round($r['total_paid'] + $r['total_overpaid'], 2);
            $r['id'] = (int) $r['id'];
        }
        unset($r);
        $rows = $this->withLeaving($rows, $yearId);

        // Term-by-term position of the whole year (same class/search filter).
        // With an account filter (e.g. "in credit"), the terms describe the
        // same learners as the line above, not the whole school.
        $termWhere = implode(' AND ', $where);
        $termParams = $params;
        if ($having !== '') {
            $termWhere .= " AND f.student_id IN (SELECT g.id FROM ({$grouped}) g{$having})";
            $termParams = array_merge($params, $outerParams);
        }
        $termTotals = [];
        foreach ($this->rows(
            "SELECT f.academic_year_term_id, SUM(f.total_due) AS total_due, SUM(f.total_paid) AS total_paid, SUM(f.current_balance) AS total_balance
             FROM {$source} f WHERE {$termWhere} GROUP BY f.academic_year_term_id",
            $termParams
        ) as $t) {
            $termTotals[(int) $t['academic_year_term_id']] = $t;
        }
        $annual = ['total_due' => 0.0, 'total_paid' => 0.0, 'total_balance' => 0.0];
        $termSummary = [];
        foreach ($terms as $t) {
            $x = $termTotals[(int) $t['academic_year_term_id']] ?? ['total_due' => 0, 'total_paid' => 0, 'total_balance' => 0];
            foreach ($annual as $k => $_) $annual[$k] += (float) $x[$k];
            $termSummary[] = $t + [
                'total_due' => (float) $x['total_due'],
                'total_paid' => (float) $x['total_paid'],
                'total_balance' => (float) $x['total_balance'],
                'collection_rate' => $this->rate((float) $x['total_paid'], (float) $x['total_due']),
            ];
        }
        $annual['collection_rate'] = $this->rate($annual['total_paid'], $annual['total_due']);
        $current = $scope['year']['is_current'] ? $this->currentTerm($terms) : null;
        $selected = $termId ? array_values(array_filter($terms, static fn ($t) => (int) $t['academic_year_term_id'] === $termId))[0] ?? null : null;

        return [
            'year' => ['id' => $yearId, 'code' => $scope['year']['year_code'], 'is_current' => (int) $scope['year']['is_current'] === 1],
            'terms' => $terms,
            'academic_year_term_id' => $termId,
            'items' => $rows,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => (int) $totals['learners']],
            'summary' => [
                'academic_year' => $scope['year']['year_code'],
                'period_label' => $selected ? $selected['label'] : 'Whole year',
                'learners' => (int) $totals['learners'],
                'total_due' => (float) $totals['total_due'],
                'total_paid' => (float) $totals['total_paid'],
                'total_waived' => (float) $totals['total_waived'],
                'total_credited' => (float) $totals['total_credited'],
                'total_balance' => (float) $totals['total_balance'],
                'total_owed' => (float) $totals['total_owed'],
                'total_overpaid' => (float) $totals['total_overpaid'],
                'learners_overpaid' => (int) $totals['learners_overpaid'],
                'learners_waived' => (int) $totals['learners_waived'],
                'brought_forward' => (float) $totals['brought_forward'],
                'new_fees' => round((float) $totals['total_due'] - (float) $totals['brought_forward'], 2),
                'scholarship' => (float) $totals['scholarship'],
                'amount_received' => round((float) $totals['total_paid'] + (float) $totals['total_overpaid'], 2),
                'credit_bf' => (float) $totals['credit_bf'],
                'other_credits' => round((float) $totals['total_credited'] - (float) $totals['credit_bf'], 2),
                'opening_balance' => round((float) $totals['brought_forward'] - (float) $totals['credit_bf'], 2),
                'learners_owing' => (int) $totals['learners_owing'],
                'learners_in_credit' => (int) $totals['learners_in_credit'],
                'learners_cleared' => (int) $totals['learners_cleared'],
                'learners_sponsored' => (int) $totals['learners_sponsored'],
                'waiver' => round((float) $totals['total_waived'] - (float) $totals['scholarship'], 2),
                'collection_rate' => $this->rate((float) $totals['total_paid'], (float) $totals['total_due']),
                'terms' => $termSummary,
                'annual' => $annual,
                'current_term_number' => $current ? $current['term_number'] : null,
                'has_current_term' => $current !== null,
            ],
        ];
    }

    /**
     * Learners who LEFT in this year (graduated, transferred, withdrawn):
     * their final balance is settled at student clearance, before the
     * leaving certificate and other certificates are issued. Adds
     * `leaving` = {status, label, clearance} to those rows.
     */
    private function withLeaving(array $rows, int $yearId): array
    {
        $ids = array_values(array_filter(array_map(static fn ($r) => (int) $r['id'], $rows)));
        if (!$ids) return $rows;
        $left = [];
        foreach ($this->rows(
            "SELECT student_id, enrollment_status FROM " . ReadReplicaService::qualifiedRef('learner_placement_history') . "
             WHERE academic_year_id = ? AND enrollment_status IN ('graduated','transferred','withdrawn')
               AND student_id IN (" . implode(',', $ids) . ")",
            [$yearId]
        ) as $p) {
            $left[(int) $p['student_id']] = $p['enrollment_status'];
        }
        if (!$left) return $rows;
        $clearance = [];
        try {
            foreach ($this->rows(
                "SELECT student_id, COUNT(*) AS items, SUM(status = 'cleared') AS cleared
                 FROM student_clearances WHERE student_id IN (" . implode(',', array_keys($left)) . ") GROUP BY student_id"
            ) as $c) {
                $clearance[(int) $c['student_id']] = ((int) $c['items'] > 0 && (int) $c['items'] === (int) $c['cleared']) ? 'cleared' : 'in_progress';
            }
        } catch (\Throwable $ignored) {
            // no clearance records yet
        }
        $year = $this->rows('SELECT YEAR(start_date) AS y FROM academic_years WHERE id = ?', [$yearId])[0]['y'] ?? '';
        foreach ($rows as &$r) {
            $status = $left[(int) $r['id']] ?? null;
            if (!$status) continue;
            $bal = (float) $r['current_balance'];
            $how = $status === 'graduated' ? 'Graduated' . ($year ? " (class of {$year})" : '') : ucfirst($status);
            $money = number_format(abs($bal), 2);
            $r['leaving'] = [
                'status' => $status,
                'label' => $bal > 0 ? "{$how} owing KES {$money}" : ($bal < 0 ? "{$how} with an overpayment of KES {$money}" : "{$how}, account cleared"),
                'to_settle' => $bal != 0.0,
                'clearance' => $clearance[(int) $r['id']] ?? 'not_started',
            ];
        }
        unset($r);
        return $rows;
    }

    private function terms(int $yearId): array
    {
        $rows = $this->rows(
            'SELECT id, term_id, opening_date, closing_date FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . '
             WHERE academic_year_id = ? ORDER BY opening_date, id',
            [$yearId]
        );
        return array_map(static fn ($t) => [
            'academic_year_term_id' => (int) $t['id'],
            'term_number' => (int) $t['term_id'],
            'label' => 'Term ' . (int) $t['term_id'],
            'opening_date' => $t['opening_date'],
            'closing_date' => $t['closing_date'],
        ], $rows);
    }

    private function currentTerm(array $terms): ?array
    {
        $today = date('Y-m-d');
        foreach ($terms as $t) {
            if ($t['opening_date'] <= $today && $today <= $t['closing_date']) return $t;
        }
        return null;
    }

    private function rate(float $paid, float $due): float
    {
        return $due > 0 ? round($paid * 100 / $due, 2) : 0.0;
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function row(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
