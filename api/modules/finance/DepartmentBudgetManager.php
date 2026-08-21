<?php
namespace App\API\Modules\finance;

use PDO;
use Exception;

class DepartmentBudgetManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Submit a new budget proposal -> budgets
    public function submitProposal($data)
    {
        $sql = "INSERT INTO budgets (name, academic_year, term, total_amount, description, status, created_by, submitted_by, submitted_at)
                VALUES (?, ?, ?, ?, ?, 'submitted', ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['title'] ?? 'Department budget proposal',
            $data['academic_year'] ?? date('Y'),
            isset($data['term']) && is_numeric($data['term']) ? (int) $data['term'] : null,
            $data['amount_requested'] ?? 0,
            $this->withDepartmentMarker($data),
            $data['created_by'] ?? 0,
            $data['created_by'] ?? 0
        ]);
        return $this->db->lastInsertId();
    }

    // List proposals (optionally filter by status)
    public function listProposals($filters = [])
    {
        $sql = "SELECT id, name AS title, description, total_amount AS amount_requested,
                       academic_year, term, status, created_by, created_at, reviewed_at
                FROM budgets WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $this->mapProposalStatus($filters['status']);
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve or reject a proposal
    public function updateProposalStatus($proposalId, $status, $reviewedBy)
    {
        $sql = "UPDATE budgets SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->mapProposalStatus($status), $reviewedBy, $proposalId]);
        return $stmt->rowCount();
    }

    // Allocate funds to a department account -> approved budget
    public function allocateFunds($departmentId, $amount, $allocatedBy)
    {
        $sql = "INSERT INTO budgets (name, academic_year, total_amount, description, status, approved_by, approved_at, created_by, submitted_by, submitted_at)
                VALUES (?, ?, ?, ?, 'approved', ?, NOW(), ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'Department allocation',
            date('Y'),
            $amount,
            '[dept_id:' . (int) $departmentId . '] Allocated funds',
            $allocatedBy,
            $allocatedBy,
            $allocatedBy
        ]);
        return $this->db->lastInsertId();
    }

    // Request funds (loan/overdraft) -> budget amendment
    public function requestFund($data)
    {
        $budgetId = $data['budget_id'] ?? $this->ensureDepartmentBudget($data);
        $sql = "INSERT INTO budget_amendments (budget_id, line_item_id, amendment_type, amount_change, reason, status, requested_by)
                VALUES (?, NULL, 'supplementary', ?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $budgetId,
            $data['amount'] ?? 0,
            $data['reason'] ?? null,
            $data['requested_by'] ?? 0
        ]);
        return $this->db->lastInsertId();
    }

    // List fund requests (budget amendments of type supplementary)
    public function listFundRequests($filters = [])
    {
        $sql = "SELECT id, budget_id, amount_change AS amount, reason, status, requested_by, created_at, approved_at, rejection_reason
                FROM budget_amendments WHERE amendment_type = 'supplementary'";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve/reject fund request
    public function updateFundRequestStatus($requestId, $status, $reviewedBy)
    {
        $sql = "UPDATE budget_amendments SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $reviewedBy, $requestId]);
        return $stmt->rowCount();
    }

    private function mapProposalStatus($status)
    {
        // budgets.status has no 'pending' — proposals are 'submitted' until reviewed
        return strtolower($status) === 'pending' ? 'submitted' : $status;
    }

    private function withDepartmentMarker($data)
    {
        $description = $data['description'] ?? '';
        if (!empty($data['department_id'])) {
            $description = '[dept_id:' . (int) $data['department_id'] . '] ' . $description;
        }
        return $description;
    }

    private function ensureDepartmentBudget($data)
    {
        $sql = "INSERT INTO budgets (name, academic_year, total_amount, description, status, created_by, submitted_by, submitted_at)
                VALUES (?, ?, ?, ?, 'submitted', ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'Department fund request',
            $data['academic_year'] ?? date('Y'),
            $data['amount'] ?? 0,
            $this->withDepartmentMarker($data),
            $data['requested_by'] ?? 0,
            $data['requested_by'] ?? 0
        ]);
        return $this->db->lastInsertId();
    }
}
