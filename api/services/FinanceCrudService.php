<?php
namespace App\API\Services;

use Exception;
use PDO;

/**
 * FinanceCrudService — extracted from FinanceController lines 1490-2246.
 * Encapsulates raw SQL for expenses, petty cash, budgets, adjustments,
 * waivers, credit notes, salary advances, and payment matching.
 */
final class FinanceCrudService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ==================== EXPENSES ====================

    public function createExpense(array $d, int $userId): array
    {
        $expNo = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $this->db->prepare(
            "INSERT INTO expenses (expense_number, category_id, description, amount, expense_date,
                payment_method, reference_number, vendor_id, receipt_number,
                budget_line_item_id, department_id, academic_year, term, notes, attachment_path,
                status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW())"
        )->execute([
            $expNo, $d['category_id'] ?? null, $d['description'], $d['amount'], $d['expense_date'],
            $d['payment_method'] ?? 'cash', $d['reference_number'] ?? null, $d['vendor_id'] ?? null,
            $d['receipt_number'] ?? null, $d['budget_line_item_id'] ?? null,
            $d['department_id'] ?? null, $d['academic_year'] ?? date('Y'), $d['term'] ?? null,
            $d['notes'] ?? null, $d['attachment_path'] ?? null, $userId,
        ]);
        return ['id' => $this->db->lastInsertId(), 'expense_number' => $expNo];
    }

    public function updateExpense(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        $allowed = ['category_id','description','amount','expense_date','payment_method',
                    'reference_number','vendor_id','receipt_number',
                    'budget_line_item_id','department_id','academic_year','term','notes'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $params[] = $data[$f]; }
        }
        $fields[] = 'updated_at=NOW()';
        $params[] = $id;
        $this->db->prepare("UPDATE expenses SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
    }

    public function setExpenseStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE expenses SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $id]);
    }

    public function softDeleteExpense(int $id): void
    {
        $this->db->prepare("UPDATE expenses SET deleted_at=NOW() WHERE id=?")->execute([$id]);
    }

    public function listExpenseCategories(): array
    {
        return $this->db->query("SELECT * FROM expense_categories WHERE status='active' ORDER BY type, name")->fetchAll() ?: [];
    }

    // ==================== PETTY CASH ====================

    public function getPettyCashFund(int $fundId): ?array
    {
        $r = $this->db->prepare(
            "SELECT f.*,
                    f.opening_balance
                        + COALESCE((SELECT SUM(t.amount) FROM petty_cash_transactions t
                                    WHERE t.fund_id = f.id AND t.type = 'top_up'), 0)
                        - COALESCE((SELECT SUM(t.amount) FROM petty_cash_transactions t
                                    WHERE t.fund_id = f.id AND t.type = 'expense'), 0)
                        AS current_balance
             FROM petty_cash_funds f WHERE f.id=?"
        );
        $r->execute([$fundId]);
        return $r->fetch() ?: null;
    }

    public function listPettyCashTransactions(int $fundId, array $filters): array
    {
        $where = ['fund_id = ?'];
        $params = [$fundId];
        if (!empty($filters['type']))        { $where[] = 'type=?';              $params[] = $filters['type']; }
        if (!empty($filters['date_from']))   { $where[] = 'transaction_date>=?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'transaction_date<=?'; $params[] = $filters['date_to']; }
        if (!empty($filters['category_id'])) { $where[] = 'category_id=?';       $params[] = $filters['category_id']; }

        $txns = $this->db->prepare(
            "SELECT t.*, ec.name AS category_name, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS recorded_by_name
             FROM petty_cash_transactions t
             LEFT JOIN expense_categories ec ON ec.id = t.category_id
             LEFT JOIN users u ON u.id = t.recorded_by
             LEFT JOIN persons up ON up.id = u.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY transaction_date DESC, id DESC LIMIT 200"
        );
        $txns->execute($params);

        $stats = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type='expense' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS expenses_this_month,
                    COALESCE(SUM(CASE WHEN type='top_up' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS topups_this_month
             FROM petty_cash_transactions WHERE fund_id=?"
        );
        $stats->execute([$fundId]);

        return ['transactions' => $txns->fetchAll() ?: [], 'stats' => $stats->fetch() ?: []];
    }

    public function createPettyCashTransaction(array $d, int $fundId, int $userId): float
    {
        $fund = $this->getPettyCashFund($fundId);
        $balanceAfter = ($d['type'] === 'expense')
            ? $fund['current_balance'] - $d['amount']
            : $fund['current_balance'] + $d['amount'];

        $this->db->prepare(
            "INSERT INTO petty_cash_transactions (fund_id,type,category_id,description,amount,balance_after,
              transaction_date,receipt_number,vendor_id,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $fundId, $d['type'], $d['category_id'] ?? null, $d['description'],
            $d['amount'], $balanceAfter,
            $d['transaction_date'] ?? date('Y-m-d'),
            $d['receipt_number'] ?? null, $d['vendor_id'] ?? null,
            $d['notes'] ?? null, $userId
        ]);
        return $balanceAfter;
    }

    // ==================== CASH RECONCILIATION ====================

    public function getCashReconciliationByDate(string $date): ?array
    {
        $r = $this->db->prepare(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name,
                    COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN persons up ON up.id = u.person_id
             LEFT JOIN users a ON a.id = s.approved_by
             LEFT JOIN persons ap ON ap.id = a.person_id
             WHERE s.reconciliation_date=?"
        );
        $r->execute([$date]);
        return $r->fetch() ?: null;
    }

    public function getCashReconciliationById(int $id): ?array
    {
        $r = $this->db->prepare(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name,
                    COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN persons up ON up.id = u.person_id
             LEFT JOIN users a ON a.id = s.approved_by
             LEFT JOIN persons ap ON ap.id = a.person_id
             WHERE s.id=?"
        );
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    public function listCashReconciliationSessions(): array
    {
        return $this->db->query(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN persons up ON up.id = u.person_id
             ORDER BY s.reconciliation_date DESC LIMIT 60"
        )->fetchAll() ?: [];
    }

    public function upsertCashReconciliation(array $d, int $userId): array
    {
        $r = $this->db->prepare("SELECT id FROM cash_reconciliation_sessions WHERE reconciliation_date=? AND cashier_id=?");
        $r->execute([$d['reconciliation_date'], $userId]);
        $existing = $r->fetch();

        if ($existing) {
            $this->db->prepare(
                "UPDATE cash_reconciliation_sessions SET physical_cash_count=?, variance_reason=?, notes=?, status='draft' WHERE id=?"
            )->execute([$d['physical_cash_count'], $d['variance_reason'] ?? null, $d['notes'] ?? null, $existing['id']]);
            return ['id' => $existing['id']];
        }

        $this->db->prepare(
            "INSERT INTO cash_reconciliation_sessions (reconciliation_date,system_cash_total,physical_cash_count,variance_reason,cashier_id,notes,status)
             VALUES (?,?,?,?,?,?,'draft')"
        )->execute([$d['reconciliation_date'], $d['system_cash_total'], $d['physical_cash_count'], $d['variance_reason'] ?? null, $userId, $d['notes'] ?? null]);
        return ['id' => $this->db->lastInsertId()];
    }

    // ==================== ADJUSTMENTS ====================

    public function listAdjustments(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'fcn.student_id=?'; $params[] = $filters['student_id']; }

        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number AS reference, fcn.credit_number AS adjustment_number,
                    fcn.credit_reason AS type, fcn.credit_reason AS adjustment_type,
                    fcn.credit_amount AS amount, fcn.notes, fcn.academic_year, fcn.term_id,
                    fcn.applied_amount, fcn.remaining_amount, fcn.applied_at, fcn.expiry_date,
                    fcn.status AS credit_status, fcn.created_at, fcn.updated_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS student_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS requested_by,
                    COALESCE(CONCAT(ap.first_name,' ',ap.last_name), a.username) AS approved_by
             FROM fee_credit_notes fcn
             LEFT JOIN students s ON s.id = fcn.student_id
             LEFT JOIN persons sp ON sp.id = s.person_id
             LEFT JOIN users u ON u.id = fcn.created_by
             LEFT JOIN persons up ON up.id = u.person_id
             LEFT JOIN users a ON a.id = fcn.approved_by
             LEFT JOIN persons ap ON ap.id = a.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY fcn.created_at DESC LIMIT 200"
        );
        $rows->execute($params);

        $items = array_map([$this, 'decodeAdjustmentStatus'], $rows->fetchAll() ?: []);

        if (!empty($filters['status'])) {
            $items = array_values(array_filter($items, function ($r) use ($filters) {
                return strcasecmp($r['status'], $filters['status']) === 0;
            }));
        }

        $pending  = array_values(array_filter($items, fn($r) => $r['status'] === 'pending'));
        $approved = array_values(array_filter($items, fn($r) => $r['status'] === 'approved'));
        $rejected = array_values(array_filter($items, fn($r) => $r['status'] === 'rejected'));
        $applied  = array_values(array_filter($items, fn($r) => $r['status'] === 'applied'));

        $stats = [
            'pending_count'   => count($pending),
            'pending_amount'  => (float) array_sum(array_column($pending, 'amount')),
            'approved_this_month' => count(array_filter($approved, function ($r) {
                return isset($r['updated_at']) && substr($r['updated_at'], 0, 7) === date('Y-m');
            })),
            'total_applied'   => (float) array_sum(array_column($applied, 'amount')),
            'rejected_count'  => count($rejected),
        ];

        return ['adjustments' => $items, 'stats' => $stats];
    }

    public function createAdjustment(array $d, int $userId): array
    {
        $adjNo = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $reason = trim((string)($d['reason'] ?? ''));
        $this->db->prepare(
            "INSERT INTO fee_credit_notes (credit_number, student_id, academic_year, term_id,
              source_transaction_id, credit_amount, credit_reason, expiry_date, notes, created_by)
             VALUES (?,?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL 2 YEAR),?,?)"
        )->execute([
            $adjNo,
            !empty($d['student_id']) ? (int) $d['student_id'] : 0,
            $d['academic_year'] ?? date('Y'),
            $d['term'] ?? $d['term_id'] ?? null,
            $d['reference_payment_id'] ?? null,
            abs((float) ($d['amount'] ?? 0)),
            $this->mapAdjustmentTypeToCreditReason($d['adjustment_type'] ?? $d['type'] ?? 'correction'),
            '[status:pending] ' . $reason,
            $userId
        ]);
        return ['id' => $this->db->lastInsertId(), 'adjustment_number' => $adjNo];
    }

    public function setAdjustmentStatus(int $id, string $status, int $userId, ?string $rejectionReason = null): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) return;

        $current = $this->db->prepare("SELECT notes FROM fee_credit_notes WHERE id=?");
        $current->execute([$id]);
        $row = $current->fetch();
        if (!$row) return;

        $notes = $this->stripAdjustmentMarker((string) ($row['notes'] ?? ''));
        if ($status === 'approved') {
            $this->db->prepare(
                "UPDATE fee_credit_notes SET status='available', approved_by=?, notes=?, updated_at=NOW() WHERE id=?"
            )->execute([$userId, '[status:approved] ' . $notes, $id]);
        } else {
            $notes = ($rejectionReason !== null && $rejectionReason !== '') ? $rejectionReason : $notes;
            $this->db->prepare(
                "UPDATE fee_credit_notes SET status='cancelled', notes=?, updated_at=NOW() WHERE id=?"
            )->execute(['[status:rejected] ' . $notes, $id]);
        }
    }

    private function mapAdjustmentTypeToCreditReason(?string $type): string
    {
        $map = [
            'fee_waiver'         => 'waiver_excess',
            'discount'           => 'fee_reduction',
            'correction'         => 'error_correction',
            'overpayment_refund' => 'overpayment',
            'write_off'          => 'fee_reduction',
            'refund'             => 'refund',
            'fee_reduction'      => 'fee_reduction',
            'sponsorship_adjustment' => 'sponsorship_adjustment',
            'error_correction'   => 'error_correction',
            'waiver_excess'      => 'waiver_excess',
            'overpayment'        => 'overpayment',
        ];
        return $map[strtolower((string) $type)] ?? 'fee_reduction';
    }

    private function decodeAdjustmentStatus(array $row): array
    {
        $notes = (string) ($row['notes'] ?? '');
        if (preg_match('/^\[status:(pending|approved|rejected|applied)\](.*)$/s', $notes, $m)) {
            $row['status'] = $m[1];
            $row['reason'] = trim($m[2]);
            $row['notes']  = trim($m[2]);
        } elseif (in_array($row['credit_status'] ?? null, ['partially_applied', 'fully_applied'], true) || !empty($row['applied_at'])) {
            $row['status'] = 'applied';
            $row['reason'] = $notes;
        } elseif (!empty($row['approved_by'])) {
            $row['status'] = 'approved';
            $row['reason'] = $notes;
        } else {
            $row['status'] = 'pending';
            $row['reason'] = $notes;
        }
        return $row;
    }

    private function stripAdjustmentMarker(string $notes): string
    {
        return trim(preg_replace('/^\[status:(pending|approved|rejected|applied)\]\s*/', '', $notes) ?? $notes);
    }

    // ==================== EXCEPTION REPORTS ====================

    public function listExceptionReports(array $filters): array
    {
        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number AS reference, fcn.credit_reason AS exception_type,
                    fcn.credit_amount AS amount, fcn.notes, fcn.credit_reason AS type,
                    fcn.created_at AS detected_at, fcn.updated_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS affected_party,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS resolved_by_name
             FROM fee_credit_notes fcn
             LEFT JOIN students s ON s.id = fcn.student_id
             LEFT JOIN persons sp ON sp.id = s.person_id
             LEFT JOIN users u ON u.id = fcn.approved_by
             LEFT JOIN persons up ON up.id = u.person_id
             WHERE fcn.notes LIKE '[status:pending]%' OR fcn.notes LIKE '[status:rejected]%'
             ORDER BY fcn.created_at DESC LIMIT 200"
        );
        $rows->execute();

        $exceptions = [];
        foreach ($rows->fetchAll() ?: [] as $row) {
            $notes = (string) ($row['notes'] ?? '');
            $pending = strpos($notes, '[status:pending]') === 0;
            $amount = (float) $row['amount'];
            $exceptions[] = [
                'id'            => $row['id'],
                'reference'     => $row['reference'],
                'exception_type'=> ucwords(str_replace('_', ' ', $row['exception_type'])) . ' Adjustment',
                'type'          => $row['type'],
                'severity'      => $pending ? ($amount >= 50000 ? 'high' : 'medium') : 'low',
                'description'   => $this->stripAdjustmentMarker($notes),
                'amount'        => $amount,
                'affected_party'=> $row['affected_party'],
                'detected_at'   => $row['detected_at'],
                'status'        => $pending ? 'open' : 'dismissed',
                'resolved_by_name' => $row['resolved_by_name'],
            ];
        }

        if (!empty($filters['status'])) {
            $exceptions = array_values(array_filter($exceptions, function ($e) use ($filters) {
                return strcasecmp($e['status'], $filters['status']) === 0;
            }));
        }

        $stats = [
            'total'         => count($exceptions),
            'open_count'    => count(array_filter($exceptions, fn($e) => $e['status'] === 'open')),
            'critical_count'=> count(array_filter($exceptions, fn($e) => $e['severity'] === 'critical')),
            'high_count'    => count(array_filter($exceptions, fn($e) => $e['severity'] === 'high')),
        ];

        return ['exceptions' => $exceptions, 'stats' => $stats];
    }

    public function updateExceptionStatus(int $id, string $status, int $userId, ?string $notes = null): void
    {
        $status = strtolower($status);
        if ($status === 'resolved') {
            $this->setAdjustmentStatus($id, 'approved', $userId);
        } elseif ($status === 'dismissed') {
            $this->setAdjustmentStatus($id, 'rejected', $userId, $notes);
        }
    }

    // ==================== BUDGETS ====================

    public function listBudgets(): array
    {
        return $this->db->query(
            "SELECT v.budget_id AS id, v.budget_name AS name, v.academic_year, v.term,
                    v.total_amount, v.budget_status AS status,
                    COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS created_by_name,
                    v.total_spent, v.total_allocated, v.total_committed, v.utilization_pct
             FROM vw_budget_utilization v
             LEFT JOIN budgets b ON b.id = v.budget_id
             LEFT JOIN users u ON u.id = b.created_by
             LEFT JOIN persons up ON up.id = u.person_id
             ORDER BY v.academic_year DESC, v.term"
        )->fetchAll() ?: [];
    }

    public function getBudget(int $id): ?array
    {
        $budget = $this->db->prepare("SELECT * FROM budgets WHERE id=?");
        $budget->execute([$id]);
        $b = $budget->fetch();
        if (!$b) return null;

        $lines = $this->db->prepare(
            "SELECT bl.*, ec.name AS category_name FROM budget_line_items bl
             LEFT JOIN expense_categories ec ON ec.id = bl.category_id WHERE bl.budget_id=?"
        );
        $lines->execute([$id]);
        return ['budget' => $b, 'line_items' => $lines->fetchAll() ?: []];
    }

    public function createBudget(array $d, int $userId): int
    {
        $this->db->prepare(
            "INSERT INTO budgets (name, academic_year, term, total_amount, description, status, created_by)
             VALUES (?,?,?,?,?,'draft',?)"
        )->execute([$d['name'], $d['academic_year'], $d['term'] ?? null, $d['total_amount'] ?? 0, $d['description'] ?? null, $userId]);
        $budgetId = $this->db->lastInsertId();

        if (!empty($d['line_items']) && is_array($d['line_items'])) {
            $li = $this->db->prepare("INSERT INTO budget_line_items (budget_id, category_id, description, allocated_amount) VALUES (?,?,?,?)");
            foreach ($d['line_items'] as $item) {
                $li->execute([$budgetId, $item['category_id'] ?? null, $item['description'] ?? null, $item['allocated_amount'] ?? 0]);
            }
        }
        return $budgetId;
    }

    public function updateBudgetStatus(int $id, string $status, int $userId): void
    {
        $extra = '';
        $extraParams = [];
        if ($status === 'submitted') { $extra = ', submitted_by=?, submitted_at=NOW()'; $extraParams = [$userId]; }
        if ($status === 'approved')  { $extra = ', approved_by=?, approved_at=NOW()';   $extraParams = [$userId]; }
        if ($status === 'active')    { $extra = ', activated_at=NOW()'; }
        $this->db->prepare("UPDATE budgets SET status=?$extra, updated_at=NOW() WHERE id=?")->execute(array_merge([$status], $extraParams, [$id]));
    }

    public function updateBudget(int $id, array $d): void
    {
        $this->db->prepare("UPDATE budgets SET name=?, total_amount=?, description=?, updated_at=NOW() WHERE id=?")
            ->execute([$d['name'] ?? '', $d['total_amount'] ?? 0, $d['description'] ?? null, $id]);
    }

    // ==================== FEE WAIVERS ====================

    public function listFeeWaivers(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id']))    { $where[] = 'fdw.student_id=?';    $params[] = $filters['student_id']; }
        if (!empty($filters['status']))        { $where[] = 'fdw.status=?';        $params[] = $filters['status']; }
        if (!empty($filters['academic_year'])) { $where[] = 'fdw.academic_year=?'; $params[] = $filters['academic_year']; }

        $rows = $this->db->prepare(
            "SELECT fdw.*, COALESCE(CONCAT(sp.first_name,' ',sp.last_name), '—') AS student_name,
                    s.admission_no, c.name AS class_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS approved_by_name
             FROM fee_discounts_waivers fdw
             JOIN students s ON s.id = fdw.student_id
             LEFT JOIN persons sp ON sp.id = s.person_id
             LEFT JOIN student_academic_enrollments sae ON sae.student_id = fdw.student_id AND sae.enrollment_status = 'active'
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN users u ON u.id = fdw.approved_by
             LEFT JOIN persons up ON up.id = u.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY fdw.created_at DESC"
        );
        $rows->execute($params);

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total, COUNT(CASE WHEN status='active' THEN 1 END) AS active_count,
                    COALESCE(SUM(CASE WHEN status='active' THEN discount_value END),0) AS total_waived
             FROM fee_discounts_waivers"
        )->fetch();

        return ['waivers' => $rows->fetchAll() ?: [], 'stats' => $stats ?: []];
    }

    public function createFeeWaiver(array $d, int $userId): int
    {
        $this->db->prepare(
            "INSERT INTO fee_discounts_waivers (student_id, student_fee_obligation_id, discount_type, discount_value,
              discount_percentage, reason, academic_year, term_id, approved_by, approved_date, status, valid_until)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),'active',?)"
        )->execute([
            $d['student_id'], $d['obligation_id'] ?? null,
            $d['discount_type'], $d['discount_value'],
            $d['discount_percentage'] ?? null, $d['reason'],
            $d['academic_year'] ?? date('Y'), $d['term_id'] ?? null,
            $userId, $d['valid_until'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    // ==================== SPONSORED STUDENTS ====================

    public function listSponsoredStudents(): array
    {
        return $this->db->query(
            "SELECT v.id, v.admission_no AS admission_number, v.student_name, v.class_name,
                    v.is_sponsored, v.sponsor_name, v.sponsor_type, v.sponsor_waiver_percentage,
                    v.total_fees_due AS total_fees, v.total_paid, v.current_balance AS outstanding_balance,
                    v.total_waived
             FROM vw_sponsored_students_status v
             ORDER BY v.sponsor_waiver_percentage DESC"
        )->fetchAll() ?: [];
    }

    // ==================== FEE CREDIT NOTES ====================

    public function listFeeCredits(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'fcn.student_id = ?'; $params[] = $filters['student_id']; }
        if (!empty($filters['status']))     { $where[] = 'fcn.status = ?';     $params[] = $filters['status']; }

        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number, fcn.academic_year,
                    fcn.credit_amount, fcn.applied_amount, fcn.remaining_amount,
                    fcn.credit_reason, fcn.status, fcn.expiry_date, fcn.created_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS student_name, s.admission_no,
                    t.name AS term_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS created_by_name
             FROM fee_credit_notes fcn
             JOIN students s ON s.id = fcn.student_id
             LEFT JOIN persons sp ON sp.id = s.person_id
             LEFT JOIN terms t ON t.id = fcn.term_id
             LEFT JOIN users u ON u.id = fcn.created_by
             LEFT JOIN persons up ON up.id = u.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY fcn.created_at DESC"
        );
        $rows->execute($params);
        $all = $rows->fetchAll() ?: [];

        $stats = [
            'total_credits'   => array_sum(array_column($all, 'credit_amount')),
            'total_available' => array_sum(array_column(array_filter($all, fn($r) => in_array($r['status'], ['available','partially_applied'])), 'remaining_amount')),
            'total_applied'   => array_sum(array_column($all, 'applied_amount')),
        ];
        return ['credits' => $all, 'stats' => $stats];
    }

    public function createFeeCredit(array $d, int $userId): array
    {
        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $this->db->prepare(
            "INSERT INTO fee_credit_notes
             (credit_number, student_id, academic_year, term_id, source_transaction_id,
              credit_amount, credit_reason, expiry_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?, ?)"
        )->execute([
            $creditNum, $d['student_id'], $d['academic_year'] ?? date('Y'),
            $d['term_id'] ?? null, $d['source_transaction_id'] ?? null,
            $d['credit_amount'], $d['credit_reason'] ?? 'overpayment',
            $d['notes'] ?? null, $userId
        ]);
        return ['credit_number' => $creditNum, 'id' => $this->db->lastInsertId()];
    }

    public function applyFeeCredit(int $id, float $applyAmount, array $d): float
    {
        $this->db->prepare(
            "UPDATE fee_credit_notes
             SET applied_amount = applied_amount + ?,
                 applied_to_year = ?, applied_to_term_id = ?, applied_at = NOW(),
                 status = CASE WHEN (applied_amount + ?) >= credit_amount THEN 'fully_applied' ELSE 'partially_applied' END
             WHERE id = ?"
        )->execute([$applyAmount, $d['to_year'] ?? date('Y'), $d['to_term_id'] ?? null, $applyAmount, $id]);
        return $applyAmount;
    }

    public function refundFeeCredit(int $id): void
    {
        $this->db->prepare("UPDATE fee_credit_notes SET status = 'refunded' WHERE id = ?")->execute([$id]);
    }

    public function getFeeCredit(int $id): ?array
    {
        $r = $this->db->prepare("SELECT * FROM fee_credit_notes WHERE id = ?");
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    // ==================== SALARY ADVANCES ====================

    public function listSalaryAdvances(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['staff_id'])) { $where[] = 'sa.staff_id = ?'; $params[] = $filters['staff_id']; }
        if (!empty($filters['status']))   { $where[] = 'sa.status = ?';   $params[] = $filters['status']; }

        $rows = $this->db->prepare(
            "SELECT sa.id, sa.advance_number, sa.requested_amount, sa.approved_amount,
                    sa.request_date, sa.deduction_schedule, sa.deduction_start_month,
                    sa.amount_per_deduction, sa.amount_deducted, sa.balance_remaining,
                    sa.status, sa.approval_date, sa.reason,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), '—') AS staff_name, s.staff_no AS employee_number,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS approved_by_name
             FROM staff_salary_advances sa
             JOIN staff s ON s.id = sa.staff_id
             LEFT JOIN persons sp ON sp.id = s.person_id
             LEFT JOIN users u ON u.id = sa.approved_by
             LEFT JOIN persons up ON up.id = u.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sa.request_date DESC"
        );
        $rows->execute($params);
        $all = $rows->fetchAll() ?: [];

        $stats = [
            'total_advances'    => count($all),
            'total_issued'      => array_sum(array_column(array_filter($all, fn($r) => $r['approved_amount']), 'approved_amount')),
            'total_outstanding' => array_sum(array_column(array_filter($all, fn($r) => $r['status'] === 'active'), 'balance_remaining')),
            'pending_approval'  => count(array_filter($all, fn($r) => $r['status'] === 'pending')),
        ];
        return ['advances' => $all, 'stats' => $stats];
    }

    public function getActiveAdvanceBalance(int $staffId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(balance_remaining),0) FROM staff_salary_advances WHERE staff_id = ? AND status = 'active'"
        );
        $stmt->execute([$staffId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (float) $value : 0.0;
    }

    public function createSalaryAdvance(array $d): int
    {
        $advNum = 'ADV-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $this->db->prepare(
            "INSERT INTO staff_salary_advances
             (advance_number, staff_id, requested_amount, request_date, reason, deduction_schedule, status)
             VALUES (?, ?, ?, CURDATE(), ?, ?, 'pending')"
        )->execute([$advNum, $d['staff_id'], $d['requested_amount'], $d['reason'] ?? null, $d['deduction_schedule'] ?? 'single_month']);
        return $this->db->lastInsertId();
    }

    public function approveSalaryAdvance(int $id, float $approved, float $perDed, string $start, int $userId): void
    {
        $advance = $this->getSalaryAdvance($id);
        $this->db->prepare(
            "UPDATE staff_salary_advances
             SET status = 'active', approved_amount = ?, amount_per_deduction = ?,
                 deduction_start_month = ?, balance_remaining = ?, approved_by = ?, approval_date = NOW()
             WHERE id = ?"
        )->execute([$approved, $perDed, $start, $approved, $userId, $id]);
        $this->notifyAdvanceStatus($advance, 'approved', $userId, null);
    }

    public function rejectSalaryAdvance(int $id, ?string $reason): void
    {
        $advance = $this->getSalaryAdvance($id);
        $this->db->prepare("UPDATE staff_salary_advances SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
        $this->notifyAdvanceStatus($advance, 'rejected', 0, $reason);
    }

    public function recordSalaryAdvanceDeduction(int $id, float $amt, float $newBalance, string $newStatus): void
    {
        $this->db->prepare(
            "UPDATE staff_salary_advances SET amount_deducted = amount_deducted + ?, balance_remaining = ?, status = ? WHERE id = ?"
        )->execute([$amt, $newBalance, $newStatus, $id]);
    }

    public function getSalaryAdvance(int $id): ?array
    {
        $r = $this->db->prepare("SELECT * FROM staff_salary_advances WHERE id = ?");
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    /**
     * Notify the staff member whose salary advance was approved/rejected.
     */
    private function notifyAdvanceStatus(?array $advance, string $decision, int $actorUserId, ?string $reason): void
    {
        if (!$advance || empty($advance['staff_id'])) {
            return;
        }
        try {
            $service = new NotificationService($this->db);
            $recipients = $service->userIdsForStaff([(int) $advance['staff_id']]);
            if (empty($recipients)) {
                return;
            }
            $actor = $actorUserId > 0 ? ($service->userName($actorUserId) ?: 'the approver') : 'the approver';
            $num = $advance['advance_number'] ?? ('#' . (int) $advance['id']);
            $label = 'salary advance ' . $num;
            $title = $decision === 'approved' ? 'Salary advance approved' : 'Salary advance declined';
            $message = $decision === 'approved'
                ? NotificationService::approvedText($label, $actor)
                : NotificationService::deniedText($label, $actor, (string) ($reason ?? ''));
            $service->push($recipients, 'salary_advance', $title, $message, 'medium');
        } catch (Exception $e) {
            error_log('[FinanceCrudService] Notification push failed: ' . $e->getMessage());
        }
    }

    public function getStaffBasicSalary(int $staffId): float
    {
        $stmt = $this->db->prepare("SELECT salary FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (float) $value : 0.0;
    }

    // ==================== UNMATCHED PAYMENTS ====================

    public function listUnmatchedPayments(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $rows = $this->db->prepare(
            "SELECT mt.id AS transaction_id, mt.mpesa_code AS reference,
                    mt.amount, mt.transaction_date, 'mpesa' AS source,
                    TRIM(CONCAT(COALESCE(mt.first_name,''), ' ', COALESCE(mt.last_name,''))) AS payer_name,
                    mt.status
                FROM mpesa_transactions mt
                LEFT JOIN payments pt ON mt.mpesa_code = pt.reference COLLATE utf8mb4_general_ci
                WHERE pt.reference IS NULL
                  AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'processed'))
                ORDER BY mt.transaction_date DESC
                LIMIT ? OFFSET ?"
        );
        $rows->execute([$limit, $offset]);

        $total = (int) $this->db->query(
            "SELECT COUNT(*)
             FROM mpesa_transactions mt
             LEFT JOIN payments pt ON mt.mpesa_code = pt.reference COLLATE utf8mb4_general_ci
             WHERE pt.reference IS NULL
               AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'processed'))"
        )->fetchColumn();

        return ['data' => $rows->fetchAll() ?: [], 'total' => $total];
    }

    public function matchPayment(int $paymentId, ?int $studentId, ?int $obligationId): void
    {
        $this->db->prepare(
            "UPDATE mpesa_transactions SET status = 'reconciled', reconciled_at = NOW(), student_id = ? WHERE id = ?"
        )->execute([$studentId, $paymentId]);
    }
}
