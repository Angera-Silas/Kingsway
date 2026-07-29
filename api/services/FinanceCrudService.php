<?php
namespace App\API\Services;

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
                payment_method, reference_number, vendor_id, vendor_name, receipt_number,
                budget_line_item_id, department_id, academic_year, term, notes, attachment_path,
                status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW())"
        )->execute([
            $expNo, $d['category_id'] ?? null, $d['description'], $d['amount'], $d['expense_date'],
            $d['payment_method'] ?? 'cash', $d['reference_number'] ?? null, $d['vendor_id'] ?? null,
            $d['vendor_name'] ?? null, $d['receipt_number'] ?? null, $d['budget_line_item_id'] ?? null,
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
                    'reference_number','vendor_id','vendor_name','receipt_number',
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
        $r = $this->db->prepare("SELECT * FROM petty_cash_funds WHERE id=?");
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
            "SELECT t.*, ec.name AS category_name, CONCAT(u.first_name, ' ', u.last_name) AS recorded_by_name
             FROM petty_cash_transactions t
             LEFT JOIN expense_categories ec ON ec.id = t.category_id
             LEFT JOIN users u ON u.id = t.recorded_by
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
              transaction_date,receipt_number,vendor_name,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $fundId, $d['type'], $d['category_id'] ?? null, $d['description'],
            $d['amount'], $balanceAfter,
            $d['transaction_date'] ?? date('Y-m-d'),
            $d['receipt_number'] ?? null, $d['vendor_name'] ?? null,
            $d['notes'] ?? null, $userId
        ]);
        return $balanceAfter;
    }

    // ==================== CASH RECONCILIATION ====================

    public function getCashReconciliationByDate(string $date): ?array
    {
        $r = $this->db->prepare(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS cashier_name, CONCAT(a.first_name, ' ', a.last_name) AS approved_by_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN users a ON a.id = s.approved_by
             WHERE s.reconciliation_date=?"
        );
        $r->execute([$date]);
        return $r->fetch() ?: null;
    }

    public function listCashReconciliationSessions(): array
    {
        return $this->db->query(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS cashier_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
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
        if (!empty($filters['status']))     { $where[] = 'fa.status=?';     $params[] = $filters['status']; }
        if (!empty($filters['student_id'])) { $where[] = 'fa.student_id=?'; $params[] = $filters['student_id']; }

        $rows = $this->db->prepare(
            "SELECT fa.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name, CONCAT(a.first_name, ' ', a.last_name) AS approved_by_name
             FROM financial_adjustments fa
             LEFT JOIN students s ON s.id = fa.student_id
             LEFT JOIN users u ON u.id = fa.requested_by
             LEFT JOIN users a ON a.id = fa.approved_by
             WHERE " . implode(' AND ', $where) . " ORDER BY fa.created_at DESC LIMIT 200"
        );
        $rows->execute($params);

        $stats = $this->db->query(
            "SELECT COUNT(CASE WHEN status='pending' THEN 1 END) AS pending_count,
                    COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0) AS pending_amount,
                    COUNT(CASE WHEN status='approved' AND MONTH(approved_at)=MONTH(CURDATE()) THEN 1 END) AS approved_this_month,
                    COALESCE(SUM(CASE WHEN status='applied' THEN amount END),0) AS total_applied,
                    COUNT(CASE WHEN status='rejected' THEN 1 END) AS rejected_count
             FROM financial_adjustments"
        )->fetch();

        return ['adjustments' => $rows->fetchAll() ?: [], 'stats' => $stats ?: []];
    }

    public function createAdjustment(array $d, int $userId): array
    {
        $adjNo = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $this->db->prepare(
            "INSERT INTO financial_adjustments (adjustment_number,type,student_id,amount,reason,
              reference_payment_id,academic_year,term,notes,status,requested_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,'pending',?,NOW())"
        )->execute([
            $adjNo, $d['type'], $d['student_id'] ?? null, $d['amount'], $d['reason'],
            $d['reference_payment_id'] ?? null, $d['academic_year'] ?? date('Y'),
            $d['term'] ?? null, $d['notes'] ?? null, $userId
        ]);
        return ['id' => $this->db->lastInsertId(), 'adjustment_number' => $adjNo];
    }

    public function setAdjustmentStatus(int $id, string $status, int $userId, ?string $rejectionReason = null): void
    {
        if ($status === 'approved') {
            $this->db->prepare("UPDATE financial_adjustments SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$userId, $id]);
        } elseif ($status === 'rejected') {
            $this->db->prepare("UPDATE financial_adjustments SET status='rejected', rejected_by=?, rejected_at=NOW(), rejection_reason=? WHERE id=?")->execute([$userId, $rejectionReason, $id]);
        }
    }

    // ==================== EXCEPTION REPORTS ====================

    public function listExceptionReports(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status']))   { $where[] = 'status=?';   $params[] = $filters['status']; }
        if (!empty($filters['severity'])) { $where[] = 'severity=?'; $params[] = $filters['severity']; }

        $rows = $this->db->prepare(
            "SELECT fe.*, CONCAT(u.first_name, ' ', u.last_name) AS resolved_by_name
             FROM finance_exceptions fe
             LEFT JOIN users u ON u.id = fe.resolved_by
             WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(severity,'critical','high','medium','low'), created_at DESC LIMIT 200"
        );
        $rows->execute($params);

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COUNT(CASE WHEN status='open' THEN 1 END) AS open_count,
                    COUNT(CASE WHEN severity='critical' AND status='open' THEN 1 END) AS critical_count,
                    COUNT(CASE WHEN severity='high'     AND status='open' THEN 1 END) AS high_count
             FROM finance_exceptions WHERE status != 'dismissed'"
        )->fetch();

        return ['exceptions' => $rows->fetchAll() ?: [], 'stats' => $stats ?: []];
    }

    public function updateExceptionStatus(int $id, string $status, int $userId, ?string $notes = null): void
    {
        $this->db->prepare(
            "UPDATE finance_exceptions SET status=?, resolved_by=?, resolved_at=NOW(), resolution_notes=? WHERE id=?"
        )->execute([$status, $userId, $notes, $id]);
    }

    // ==================== BUDGETS ====================

    public function listBudgets(): array
    {
        return $this->db->query(
            "SELECT v.budget_id AS id, v.budget_name AS name, v.academic_year, v.term,
                    v.total_amount, v.budget_status AS status,
                    CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
                    v.total_spent, v.total_allocated, v.total_committed, v.utilization_pct
             FROM vw_budget_utilization v
             LEFT JOIN budgets b ON b.id = v.budget_id
             LEFT JOIN users u ON u.id = b.created_by
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
            "SELECT fdw.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    s.admission_number, c.name AS class_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS approved_by_name
             FROM fee_discounts_waivers fdw
             JOIN students s ON s.id = fdw.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN users u ON u.id = fdw.approved_by
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
            "SELECT s.id, s.admission_number, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    s.is_sponsored, s.sponsor_name, s.sponsor_type, s.sponsor_waiver_percentage,
                    c.name AS class_name,
                    COALESCE(SUM(o.amount_due),0) AS total_fees,
                    COALESCE(SUM(o.amount_waived),0) AS total_waived,
                    COALESCE(SUM(o.amount_paid),0) AS total_paid,
                    COALESCE(SUM(o.balance),0) AS outstanding_balance
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN student_fee_obligations o ON o.student_id = s.id
                   AND o.academic_year = YEAR(CURDATE())
             WHERE s.is_sponsored = 1 AND s.status = 'active'
             GROUP BY s.id ORDER BY s.last_name, s.first_name"
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
                    CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                    t.name AS term_name, u.name AS created_by_name
             FROM fee_credit_notes fcn
             JOIN students s ON s.id = fcn.student_id
             LEFT JOIN academic_terms t ON t.id = fcn.term_id
             LEFT JOIN users u ON u.id = fcn.created_by
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

        if (!empty($d['obligation_id'])) {
            $this->db->prepare("UPDATE student_fee_obligations SET amount_waived = amount_waived + ? WHERE id = ?")
                ->execute([$applyAmount, $d['obligation_id']]);
        }
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
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.employee_number,
                    u.name AS approved_by_name
             FROM staff_salary_advances sa
             JOIN staff s ON s.id = sa.staff_id
             LEFT JOIN users u ON u.id = sa.approved_by
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
        return (float) $this->db->prepare(
            "SELECT COALESCE(SUM(balance_remaining),0) FROM staff_salary_advances WHERE staff_id = ? AND status = 'active'"
        )->execute([$staffId]) ? $this->db->prepare("SELECT COALESCE(SUM(balance_remaining),0) FROM staff_salary_advances WHERE staff_id = ? AND status = 'active'")->execute([$staffId]) : 0;
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
        $this->db->prepare(
            "UPDATE staff_salary_advances
             SET status = 'active', approved_amount = ?, amount_per_deduction = ?,
                 deduction_start_month = ?, balance_remaining = ?, approved_by = ?, approval_date = NOW()
             WHERE id = ?"
        )->execute([$approved, $perDed, $start, $approved, $userId, $id]);
    }

    public function rejectSalaryAdvance(int $id, ?string $reason): void
    {
        $this->db->prepare("UPDATE staff_salary_advances SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
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

    public function getStaffBasicSalary(int $staffId): float
    {
        return (float) ($this->db->prepare("SELECT basic_salary FROM staff WHERE id = ?")->execute([$staffId])
            ? $this->db->query("SELECT basic_salary FROM staff WHERE id = {$staffId}")->fetchColumn()
            : 0);
    }

    // ==================== UNMATCHED PAYMENTS ====================

    public function listUnmatchedPayments(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $rows = $this->db->prepare(
            "SELECT p.id, p.receipt_number, p.reference_number,
                    p.amount, p.payment_date, p.payment_method,
                    p.status, p.notes,
                    CONCAT(u.first_name, ' ', u.last_name) AS payer_name,
                    u.email AS payer_email
                FROM payments p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE p.status IN ('unmatched', 'pending')
                  AND p.deleted_at IS NULL
                ORDER BY p.payment_date DESC
                LIMIT ? OFFSET ?"
        );
        $rows->execute([$limit, $offset]);

        $total = (int) $this->db->query(
            "SELECT COUNT(*) FROM payments WHERE status IN ('unmatched','pending') AND deleted_at IS NULL"
        )->fetchColumn();

        return ['data' => $rows->fetchAll() ?: [], 'total' => $total];
    }

    public function matchPayment(int $paymentId, ?int $studentId, ?int $obligationId): void
    {
        $this->db->prepare(
            "UPDATE payments SET status = 'matched', student_id = ?, obligation_id = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$studentId, $obligationId, $paymentId]);
    }
}
