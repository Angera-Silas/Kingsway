<?php
namespace App\API\Controllers;

use App\API\Modules\finance\FinanceAPI;
use App\API\Modules\finance\PaymentReconciliationAPI;
use App\API\Modules\finance\ExpenseManager;
use App\API\Modules\finance\AllowanceTemplateAPI;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\FinanceCrudService;
use RuntimeException;
use Exception;
use App\Database\Database;

/**
 * FinanceController - REST endpoints for all finance operations
 * Handles fees, payments, payrolls, budgets, expenses, and financial reporting
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */

class FinanceController extends BaseController
{
    private FinanceAPI $api;
    private ExpenseManager $expenseManager;
    private AllowanceTemplateAPI $allowanceTemplateApi;
    private $staffAccess;
    private FinanceCrudService $crud;

    public function __construct() {
        parent::__construct();
        $this->api = new FinanceAPI();
        $this->expenseManager = new ExpenseManager();
        $this->allowanceTemplateApi = new AllowanceTemplateAPI();
        $this->staffAccess = new StaffDomainAccessService($this->user);
        $this->crud = new FinanceCrudService(Database::getInstance()->getConnection());
    }

    public function index()
    {
        return $this->success(['message' => 'Finance API is running']);
    }

    private function requirePayrollPermission(string $permission, array $roles = []): ?array
    {
        try {
            $this->staffAccess->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) { error_log('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->serverError('An internal error occurred.'); }
    }

    private function validatePayrollPayloadEligibility(array $payload): ?array
    {
        $staffIds = [];
        if (!empty($payload['staff_id'])) $staffIds[] = (int)$payload['staff_id'];
        foreach ((array)($payload['staff_ids'] ?? []) as $sid) $staffIds[] = (int)$sid;
        foreach ((array)($payload['staff'] ?? $payload['records'] ?? $payload['payroll_items'] ?? []) as $row) {
            if (is_array($row) && !empty($row['staff_id'])) $staffIds[] = (int)$row['staff_id'];
        }
        foreach (array_unique(array_filter($staffIds)) as $staffId) {
            try { $this->staffAccess->assertPayrollEligible($staffId); }
            catch (RuntimeException $e) { error_log('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }
        }
        return null;
    }

    /**
     * Guard: Director role (3) or any finance approval permission required.
     * Returns a forbidden response when access is denied, null when granted.
     */
    private function requireApprovalAccess(string $action = 'perform this approval'): ?array
    {
        if ($this->staffAccess->allows('staff.payroll.approve', ['director']) ||
            $this->userHasAny(['finance_approve', 'payroll_approve', 'budget_approve',
                               'fee_structure_approve', 'expense_approve', 'finance.approve'],
                              [3], ['director'])) {
            return null;
        }
        return $this->forbidden("Insufficient permissions to $action");
    }

    // ========================================
    // SECTION X: Department Budget Workflows
    // ========================================

    /**
     * POST /api/finance/department-budgets/propose
     * Department submits a budget proposal
     */
    public function postDepartmentBudgetsPropose($id = null, $data = [], $segments = [])
    {
        $result = $this->api->proposeDepartmentBudget($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/proposals
     * View all department budget proposals (optionally filter by department/status)
     */
    public function getDepartmentBudgetsProposals($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listDepartmentBudgetProposals($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/approve
     * Approve or reject a department budget proposal.
     * Accepts: proposal_id (or budget_id alias), status (default: approved), reviewed_by
     */
    public function postDepartmentBudgetsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve department budgets')) return $denied;
        $proposalId = $data['proposal_id'] ?? $data['budget_id'] ?? null;
        if (!$proposalId) {
            return $this->badRequest('proposal_id (or budget_id) is required');
        }
        $status     = $data['status']      ?? 'approved';
        $reviewedBy = $data['reviewed_by'] ?? $this->getUserId();
        $result = $this->api->updateDepartmentBudgetProposalStatus($proposalId, $status, $reviewedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/allocate
     * Allocate funds to a department budget
     */
    public function postDepartmentBudgetsAllocate($id = null, $data = [], $segments = [])
    {
        // Expecting: $data['department_id'], $data['amount'], $data['allocated_by']
        $departmentId = $data['department_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $allocatedBy = $data['allocated_by'] ?? $this->getUserId();
        if (!$departmentId || !$amount) {
            return $this->badRequest('department_id and amount are required');
        }
        $result = $this->api->allocateDepartmentBudget($departmentId, $amount, $allocatedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/request-funds
     * Department requests funds from allocated budget
     */
    public function postDepartmentBudgetsRequestFunds($id = null, $data = [], $segments = [])
    {
        $result = $this->api->requestDepartmentFunds($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/summary
     * Quick summary of department budget utilization
     */
    public function getDepartmentBudgetsSummary($id = null, $data = [], $segments = [])
    {
        $departmentId = $_GET['department_id'] ?? $data['department_id'] ?? $id ?? null;
        // department_id is optional — null returns all departments
        $result = $this->api->getDepartmentBudgetSummary($departmentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/approve
     */
    public function postExpensesApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        $result = $this->expenseManager->approveExpense(
            $expenseId,
            $this->getUserId(),
            $data['notes'] ?? $data['comments'] ?? null
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/reject
     */
    public function postExpensesReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        if (empty($data['reason'])) {
            return $this->badRequest('reason is required when rejecting an expense');
        }
        $result = $this->expenseManager->rejectExpense($expenseId, $this->getUserId(), $data['reason']);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/finance - List all finance records
     * GET /api/finance/{id} - Get single finance record
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance - Create new finance record
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/{id} - Update finance record
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for update');
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/{id} - Delete finance record
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Payroll Operations
    // ========================================

    /**
     * GET /api/finance/payrolls — alias for list
     */
    public function getPayrolls($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        return $this->getPayrollsList($id, $data, $segments);
    }

    /**
     * GET /api/finance/payrolls/list
     */
    public function getPayrollsList($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->listPayrolls($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/get
     */
    public function getPayrollsGet($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/staff-payments
     */
    public function getPayrollsStaffPayments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->listStaffPayments($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/create-draft
     */
    public function postPayrollsCreateDraft($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->createPayrollDraft($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/calculate
     */
    public function postPayrollsCalculate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->calculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/recalculate
     */
    public function postPayrollsRecalculate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->recalculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/verify
     */
    public function postPayrollsVerify($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->verifyPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/approve
     */
    public function postPayrollsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve payroll')) return $denied;
        $payrollId = $data['payroll_id'] ?? $data['id'] ?? $id;
        $approvedBy = $data['user_id'] ?? $this->getUserId();
        $result = $this->api->approvePayroll($payrollId, $approvedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/reject
     */
    public function postPayrollsReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject payroll')) return $denied;
        $data['user_id'] = $data['user_id'] ?? $this->getUserId();
        $result = $this->api->rejectPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/process
     */
    public function postPayrollsProcess($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','accountant'])) return $denied;
        $result = $this->api->processPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/disburse
     */
    public function postPayrollsDisburse($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','accountant'])) return $denied;
        $result = $this->api->disbursePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/{id}/cancel
     */
    public function postPayrollsCancel($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->cancelPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/status
     */
    public function getPayrollsStatus($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayrollStatus($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/staff-payments/get
     */
    public function getPayrollsStaffPaymentsGet($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getStaffPayments($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/summary
     */
    public function getPayrollsSummary($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getPayrollSummary($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/history
     */
    public function getPayrollsHistory($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getPayrollHistory($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2B: Enhanced Payroll with Children Fees
    // ========================================

    /**
     * GET /api/finance/staff-for-payroll
     * Get list of staff available for payroll processing with children count
     */
    public function getStaffForPayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getStaffForPayroll();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/staff-payroll-details?staff_id=X
     * Get detailed staff info including children and fee balances
     */
    public function getStaffPayrollDetails($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? $id ?? null;

        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $result = $this->api->getStaffPayrollDetails($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/bulk-payroll-preview?month=X&year=Y
     * Prepare bulk payroll preview using configured salary, allowances and deductions
     */
    public function getBulkPayrollPreview($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');
        $result = $this->api->getBulkPayrollPreview($month, $year);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/process-bulk-payroll
     * Process multiple staff payroll records as one backend request
     */
    public function postProcessBulkPayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $payload = $data ?: $this->getRequestData();
        $result = $this->api->processBulkPayroll($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/approve-payroll
     * Director approval before accountant payment release
     */
    public function postApprovePayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.approve', ['director'])) return $denied;
        $payload = $data ?: $this->getRequestData();
        $payrollId = $payload['payroll_id'] ?? null;
        if (!$payrollId) {
            return $this->badRequest('Payroll ID required');
        }
        $approvedBy = $payload['approved_by'] ?? null;
        $result = $this->api->approvePayroll($payrollId, $approvedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/process-payroll-with-deductions
     * Process payroll including children school fee deductions
     */
    public function postProcessPayrollWithDeductions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->processPayrollWithDeductions($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/detailed-payslip?payroll_id=X
     * Get detailed payslip with children fee deductions breakdown
     */
    public function getDetailedPayslip($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $payrollId = $_GET['payroll_id'] ?? $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $result = $this->api->getDetailedPayslip($payrollId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-stats?month=X&year=Y
     * Get payroll statistics for dashboard
     */
    public function getPayrollStats($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');

        $result = $this->api->getPayrollStats($month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-list
     * Get filtered payroll records
     */
    public function getPayrollList($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $filters = array_merge($_GET, $data);
        $result = $this->api->getPayrollList($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/mark-payroll-paid
     * Mark payroll as paid and record children fee payments
     */
    public function postMarkPayrollPaid($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','accountant'])) return $denied;
        $payrollId = $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $paymentRef = $data['payment_reference'] ?? '';
        $paymentMode = $data['payment_mode'] ?? 'bank';
        $result = $this->api->markPayrollPaid($payrollId, $paymentRef, $paymentMode);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2C: Allowance Templates
    // ========================================

    /**
     * GET /api/finance/allowance-templates
     * List all allowance templates
     */
    public function getAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->allowanceTemplateApi->list($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/allowance-templates/{id}
     * Get a single allowance template
     */
    public function getAllowanceTemplatesGet($id = null, $data = [], $segments = [])
    {
        $templateId = $id ?? $data['id'] ?? null;
        if (!$templateId) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->get($templateId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/allowance-templates
     * Create a new allowance template
     */
    public function postAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        $result = $this->allowanceTemplateApi->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/allowance-templates/{id}
     * Update an allowance template
     */
    public function putAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/allowance-templates/{id}
     * Deactivate an allowance template
     */
    public function deleteAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->delete($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/allowance-templates/{id}/applicable-staff
     * Preview which staff match a template's criteria
     */
    public function getAllowanceTemplatesApplicableStaff($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->getApplicableStaff($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/allowance-templates/{id}/apply
     * Apply a template to matching staff, bulk-creating staff_allowances rows
     */
    public function postAllowanceTemplatesApply($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $staffIds = $data['staff_ids'] ?? null;
        $result = $this->allowanceTemplateApi->applyToStaff($id, $staffIds);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2D: Fee Invoice Generation
    // ========================================

    /**
     * POST /api/finance/fee-invoices/generate
     */
    public function postFeeInvoicesGenerate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoice($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-invoices/generate-batch
     */
    public function postFeeInvoicesGenerateBatch($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoicesBatch($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-invoices/get?student_id=X
     */
    public function getFeeInvoicesGet($id = null, $data = [], $segments = [])
    {
        $params = array_merge($_GET, $data);
        $result = $this->api->getFeeInvoice($params);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Payment & Receipt Operations
    // ========================================

    /**
     * POST /api/finance/payments/generate-receipt
     */
    public function postPaymentsGenerateReceipt($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? $id ?? null;
        
        if ($paymentId === null) {
            return $this->badRequest('Payment ID is required');
        }
        
        $result = $this->api->generateReceipt($paymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/generate-payslip
     */
    public function postPaymentsGeneratePayslip($id = null, $data = [], $segments = [])
    {
        $staffPaymentId = $data['staff_payment_id'] ?? $id ?? null;
        
        if ($staffPaymentId === null) {
            return $this->badRequest('Staff payment ID is required');
        }
        
        $result = $this->api->generatePayslip($staffPaymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/send-notification
     */
    public function postPaymentsSendNotification($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? null;
        $recipient = $data['recipient'] ?? null;
        $method = $data['method'] ?? 'email';

        if ($paymentId === null || $recipient === null) {
            return $this->badRequest('Payment ID and recipient are required');
        }

        $result = $this->api->sendPaymentNotification($paymentId, $recipient, $method);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Fee Structure Operations
    // ========================================

    /**
     * GET /api/finance/fee-types-list
     */
    public function getFeeTypesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listFeeTypes();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/student-types-list
     */
    public function getStudentTypesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listStudentTypes();
        return $this->handleResponse($result);
    }

    /**
        
     * POST /api/finance/fees/create-annual-structure
     */
    public function postFeesCreateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/review-structure
     */
    public function postFeesReviewStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/approve-structure
     */
    public function postFeesApproveStructure($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve fee structures')) return $denied;
        $data['approved_by'] = $data['approved_by'] ?? $this->getUserId();
        $result = $this->api->approveFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/activate-structure
     */
    public function postFeesActivateStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->activateFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/rollover-structure
     */
    public function postFeesRolloverStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->rolloverFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/update-annual-structure
     */
    public function postFeesUpdateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/delete-annual-structure
     */
    public function postFeesDeleteAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/term-breakdown
     */
    public function getFeesTermBreakdown($id = null, $data = [], $segments = [])
    {
        $academicYear = $_GET['academic_year'] ?? $data['academic_year'] ?? null;
        $term = $_GET['term'] ?? $data['term'] ?? null;

        if ($academicYear === null || $term === null) {
            return $this->badRequest('Academic year and term are required');
        }

        $result = $this->api->getTermBreakdown($academicYear, $term);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/pending-reviews
     */
    public function getFeesPendingReviews($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPendingReviews();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/annual-summary
     */
    public function getFeesAnnualSummary($id = null, $data = [], $segments = [])
    {
        $academicYear = $_GET['academic_year'] ?? $data['academic_year'] ?? null;
        
        if ($academicYear === null) {
            return $this->badRequest('Academic year is required');
        }

        $result = $this->api->getAnnualFeeSummary($academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/list
     * Get fee structures with permission-aware filtering
     */
    public function getFeeStructuresList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 20;

        $result = $this->api->listFeeStructures($filters, $page, $limit);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/{id}
     * Get a specific fee structure with details
     */
    public function getFeeStructuresGet($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->getFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures
     * Create a new fee structure
     */
    public function postFeesStructures($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/fee-structures/{id}
     * Update a fee structure
     */
    public function putFeeStructures($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->updateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/fee-structures/{id}
     * Delete a fee structure
     */
    public function deleteFeeStructures($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->deleteFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures/{id}/duplicate
     * Duplicate a fee structure for a new academic year
     */
    public function postFeeStructuresDuplicate($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->duplicateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Student Payment History & Fee Statement
    // ========================================

    /**
     * GET /api/finance/students/payment-history
     */
    public function getStudentsPaymentHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        $academicYear = $data['academic_year'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentPaymentHistory($studentId, $academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/payment-status
     * List student payment status with filters
     */
    public function getStudentsPaymentStatus($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET ?? [], $data ?? []);

        if ($id !== null) {
            $filters['student_id'] = $id;
        }

        $result = $this->api->listStudentPaymentStatus($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/fee-statement/{id}
     * Get complete fee statement for a student
     */
    public function getStudentsFeeStatement($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        $academicYear = $data['academic_year'] ?? $_GET['academic_year'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        // If no academic year provided, get current one
        if ($academicYear === null) {
            $academicYear = $this->getCurrentAcademicYear();
        }

        $result = $this->api->handleCustomGet($studentId, 'statement', ['academic_year' => $academicYear]);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/balance/{id}
     * Get current fee balance for a student
     */
    public function getStudentsBalance($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->handleCustomGet($studentId, 'balance', []);
        return $this->handleResponse($result);
    }

    /**
     * Helper: Get current academic year
     */
    private function getCurrentAcademicYear()
    {
        return $this->api->getCurrentAcademicYearCode();
    }

    // ========================================
    // SECTION 6: Reporting Operations
    // ========================================

    /**
     * GET /api/finance/reports — summary of available reports + recent totals
     */
    public function getReports($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getFinancialSummaryReport();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/reports/generate-payroll
     */
    public function postReportsGeneratePayroll($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generatePayrollReport($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/reports/compare-yearly-collections
     */
    public function getReportsCompareYearlyCollections($id = null, $data = [], $segments = [])
    {
        $year1 = $data['year1'] ?? null;
        $year2 = $data['year2'] ?? null;
        
        if ($year1 === null || $year2 === null) {
            return $this->badRequest('Both years are required for comparison');
        }
        
        $result = $this->api->compareYearlyCollections($year1, $year2);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Helper Methods
    // ========================================

    /**
     * GET /api/finance/reconciliation/unreconciled
     * Wrapper to list unreconciled transactions for accountant dashboard
     */
    public function getReconciliationUnreconciled($id = null, $data = [], $segments = [])
    {
        // Require authentication + permission
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (
            !$this->userHasAny(
                ['finance.reconcile', 'finance_reconcile', 'finance.view', 'finance_view'],
                [10],
                ['accountant', 'finance', 'admin']
            )
        ) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $recon = new PaymentReconciliationAPI();
            $result = $recon->listUnreconciled($data);
            return $this->handleResponse($result);
        } catch (Exception $e) {
            error_log('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            // Check if result is from formatResponse (has 'code' and 'status' keys)
            if (isset($result['code']) && isset($result['status'])) {
                $code = $result['code'];
                $message = $result['message'] ?? 'Operation completed';
                $data = $result['data'] ?? null;

                // Route based on HTTP status code
                if ($code >= 200 && $code < 300) {
                    return $this->success($data, $message);
                } elseif ($code === 404) {
                    return $this->notFound($message);
                } elseif ($code === 401) {
                    return $this->unauthorized($message);
                } elseif ($code === 403) {
                    return $this->forbidden($message);
                } elseif ($code >= 500) {
                    return $this->serverError($message);
                } else {
                    return $this->badRequest($message);
                }
            }

            // Legacy format with 'success' key
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $message = $result['error'] ?? $result['message'] ?? 'Operation failed';
                    if (stripos($message, 'not found') !== false) {
                        return $this->notFound($message);
                    }
                    return $this->badRequest($message);
                }
            }
            
            return $this->success($result);
        }

        return $this->success($result);
    }

    // ========================================
    // SECTION 8: Fee Bundle Workflow
    // ========================================

    /**
     * POST /api/finance/fees-bundle-submit
     * Accountant submits a fee structure bundle for director review
     */
    public function postFeesBundleSubmit($id = null, $data = [], $segments = [])
    {
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['submitted_by'] = $userId;
        $result = $this->api->submitFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-review/{id}
     * Finance manager reviews a submitted bundle
     */
    public function postFeesBundleReview($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['reviewed_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->reviewFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-approve/{id}
     * Director approves or rejects a fee structure bundle.
     * On approval, automatically generates student_fee_obligations for all affected students.
     */
    public function postFeesBundleApprove($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['approved_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->approveFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees-bundle-list
     * List all fee structure bundles with status, for director review queue
     * Query params: status, academic_year, term_id, level_id, page, limit
     */
    public function getFeesBundleList($id = null, $data = [], $segments = [])
    {
        $filters = [
            'status'        => $data['status'] ?? $_GET['status'] ?? null,
            'academic_year' => $data['academic_year'] ?? $_GET['academic_year'] ?? null,
            'term_id'       => $data['term_id'] ?? $_GET['term_id'] ?? null,
            'level_id'      => $data['level_id'] ?? $_GET['level_id'] ?? null,
            'page'          => (int)($data['page'] ?? $_GET['page'] ?? 1),
            'limit'         => (int)($data['limit'] ?? $_GET['limit'] ?? 20),
        ];
        $result = $this->api->getFeeStructureBundles($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-activate-generate-obligations
     * Manually trigger obligation generation for an approved bundle
     */
    public function postFeesActivateGenerateObligations($id = null, $data = [], $segments = [])
    {
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->activateAndGenerateObligations(
            $data['level_id'], $data['academic_year'], $data['term_id'], $data['student_type_id'], $userId
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-create-bundle
     * Create (or re-create) a grade-range fee structure bundle.
     * Body: academic_year, grade_range {from_id,to_id}, student_type_ids[],
     *       items { CODE: { termN: { studentTypeId: amount } } }
     */
    public function postFeesCreateBundle($id = null, $data = [], $segments = [])
    {
        if (empty($data['academic_year']) || empty($data['grade_range']) || empty($data['items'])) {
            return $this->badRequest('academic_year, grade_range and items are required');
        }
        if (empty($data['student_type_ids'])) {
            return $this->badRequest('At least one student_type_id is required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['created_by'] = $userId;
        $result = $this->api->createFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees-bundle-grid
     * Read an existing grade-range bundle as a tabular grid (for edit/view).
     * Query: academic_year, from_id, to_id, student_type_ids[]
     */
    public function getFeesBundleGrid($id = null, $data = [], $segments = [])
    {
        $data = array_merge($_GET, $data);
        $gradeRange = [
            'from_id' => $data['from_id'] ?? $data['grade_range']['from_id'] ?? null,
            'to_id' => $data['to_id'] ?? $data['grade_range']['to_id'] ?? null,
        ];
        if (empty($data['academic_year']) || empty($gradeRange['from_id']) || empty($gradeRange['to_id'])) {
            return $this->badRequest('academic_year, from_id and to_id are required');
        }
        $result = $this->api->getFeeStructureBundleGrid([
            'academic_year' => $data['academic_year'],
            'grade_range' => $gradeRange,
            'student_type_ids' => $data['student_type_ids'] ?? null,
        ]);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Student Billing History
    // ========================================

    /**
     * GET /api/finance/students-billing-history/{id}
     * Full billing history for a student across all years and terms
     */
    public function getStudentsBillingHistory($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        $result = $this->api->getStudentBillingHistory((int)$id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/class-billing-report/{id}
     * Class-level billing report — all students, their balances and payment status
     * Query params: academic_year_id (required), term_id (optional)
     */
    public function getClassBillingReport($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('class_id required');
        $academicYearId = $data['academic_year_id'] ?? $_GET['academic_year_id'] ?? null;
        if (!$academicYearId) return $this->badRequest('academic_year_id required');
        $termId = $data['term_id'] ?? $_GET['term_id'] ?? null;
        $result = $this->api->getClassBillingReport((int)$id, (int)$academicYearId, $termId ? (int)$termId : null);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 10: Expense Management
    // ========================================

    /** GET /api/finance/expenses — list all expenses with filters */
    public function getExpenses($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $result = $this->api->getExpenseDetailed((int)$id);
            if (($result['code'] ?? 200) >= 400) {
                return $this->notFound('Expense not found');
            }
            $row = $result['data'] ?? null;
            return $row ? $this->success($row) : $this->notFound('Expense not found');
        }

        $result = $this->api->listExpensesWithStats($data);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/expenses — create expense */
    public function postExpenses($id = null, $data = [], $segments = [])
    {
        if (empty($data['description']) || empty($data['amount']) || empty($data['expense_date'])) {
            return $this->badRequest('description, amount, expense_date are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->createExpense($data, $userId);
        return $this->success($result, 'Expense recorded successfully');
    }

    /** PUT /api/finance/expenses/{id} — update or change status */
    public function putExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');

        if (isset($data['status'])) {
            if ($data['status'] === 'approved') return $this->postExpensesApprove($id, $data, $segments);
            if ($data['status'] === 'rejected')  return $this->postExpensesReject($id, $data, $segments);
            if ($data['status'] === 'pending_approval') {
                $this->crud->setExpenseStatus($id, 'pending_approval');
                return $this->success(null, 'Expense submitted for approval');
            }
        }

        if (empty($data)) return $this->badRequest('Nothing to update');
        $this->crud->updateExpense((int)$id, $data);
        return $this->success(null, 'Expense updated');
    }

    /** DELETE /api/finance/expenses/{id} — soft delete */
    public function deleteExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');
        $this->crud->softDeleteExpense((int)$id);
        return $this->success(null, 'Expense deleted');
    }

    /** GET /api/finance/expense-categories — list all expense categories */
    public function getExpenseCategories($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listExpenseCategories());
    }

    // ========================================
    // SECTION 11: Petty Cash
    // ========================================

    /** GET /api/finance/petty-cash — list transactions + fund summary */
    public function getPettyCash($id = null, $data = [], $segments = [])
    {
        $fundId = $data['fund_id'] ?? 1;
        $fund = $this->crud->getPettyCashFund($fundId);
        if (!$fund) return $this->notFound('Petty cash fund not found');
        $result = $this->crud->listPettyCashTransactions($fundId, $data);
        return $this->success(['fund' => $fund, 'transactions' => $result['transactions'], 'stats' => $result['stats']]);
    }

    /** POST /api/finance/petty-cash — record a petty cash transaction */
    public function postPettyCash($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['description'])) {
            return $this->badRequest('type, amount, description are required');
        }
        $fundId = $data['fund_id'] ?? 1;
        $fund = $this->crud->getPettyCashFund($fundId);
        if (!$fund) return $this->notFound('Petty cash fund not found');
        if ($data['type'] === 'expense' && $fund['current_balance'] - $data['amount'] < 0) {
            return $this->badRequest('Insufficient petty cash balance');
        }
        $userId = $this->getUserId();
        $balanceAfter = $this->crud->createPettyCashTransaction($data, $fundId, $userId);
        return $this->success(['balance_after' => $balanceAfter], 'Petty cash transaction recorded');
    }

    // ========================================
    // SECTION 12: Cash Reconciliation
    // ========================================

    /** GET /api/finance/cash-reconciliation — list sessions or get one by date or id */
    public function getCashReconciliation($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->success($this->crud->getCashReconciliationById($id));
        }
        if (!empty($data['date'])) {
            $session = $this->crud->getCashReconciliationByDate($data['date']);
            return $this->success($session ?: null);
        }
        return $this->success($this->crud->listCashReconciliationSessions());
    }

    /** POST /api/finance/cash-reconciliation — submit a daily cash count */
    public function postCashReconciliation($id = null, $data = [], $segments = [])
    {
        if (empty($data['reconciliation_date']) || !isset($data['system_cash_total']) || !isset($data['physical_cash_count'])) {
            return $this->badRequest('reconciliation_date, system_cash_total, physical_cash_count are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->upsertCashReconciliation($data, $userId);
        return $this->success($result, isset($result['id']) ? 'Cash reconciliation submitted' : 'Reconciliation updated');
    }

    // ========================================
    // SECTION 13: Financial Adjustments
    // ========================================

    /** GET /api/finance/adjustments — list all adjustments */
    public function getAdjustments($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listAdjustments($data));
    }

    /** POST /api/finance/adjustments — create adjustment */
    public function postAdjustments($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['reason'])) {
            return $this->badRequest('type, amount, reason are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->createAdjustment($data, $userId);
        return $this->success($result, 'Adjustment submitted');
    }

    /** PUT /api/finance/adjustments/{id} — approve/reject/apply */
    public function putAdjustments($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Adjustment ID required');
        $userId = $this->getUserId();
        $status = $data['status'] ?? null;
        if ($status === 'approved') {
            $this->crud->setAdjustmentStatus((int)$id, 'approved', $userId);
            return $this->success(null, 'Adjustment approved');
        }
        if ($status === 'rejected') {
            if (empty($data['rejection_reason'])) return $this->badRequest('rejection_reason required');
            $this->crud->setAdjustmentStatus((int)$id, 'rejected', $userId, $data['rejection_reason']);
            return $this->success(null, 'Adjustment rejected');
        }
        return $this->badRequest('Unknown status: '.$status);
    }

    // ========================================
    // SECTION 14: Exception Reports
    // ========================================

    /** GET /api/finance/exception-reports — list flagged exceptions */
    public function getExceptionReports($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listExceptionReports($data));
    }

    /** PUT /api/finance/exception-reports/{id} — update status */
    public function putExceptionReports($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Exception ID required');
        $userId = $this->getUserId();
        $this->crud->updateExceptionStatus((int)$id, $data['status'] ?? 'under_review', $userId, $data['resolution_notes'] ?? null);
        return $this->success(null, 'Exception status updated');
    }

    // ========================================
    // SECTION 15: Budgets CRUD
    // ========================================

    /** GET /api/finance/budgets — list all budgets */
    public function getBudgets($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $result = $this->crud->getBudget((int)$id);
            if (!$result) return $this->notFound('Budget not found');
            return $this->success($result);
        }
        return $this->success($this->crud->listBudgets());
    }

    /** POST /api/finance/budgets — create budget */
    public function postBudgets($id = null, $data = [], $segments = [])
    {
        if (empty($data['name']) || empty($data['academic_year'])) {
            return $this->badRequest('name and academic_year are required');
        }
        $userId = $this->getUserId();
        $budgetId = $this->crud->createBudget($data, $userId);
        return $this->success(['id' => $budgetId], 'Budget created');
    }

    /** PUT /api/finance/budgets/{id} — update or approve/submit budget */
    public function putBudgets($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Budget ID required');
        $userId = $this->getUserId();
        $status = $data['status'] ?? null;
        if ($status) {
            $this->crud->updateBudgetStatus((int)$id, $status, $userId);
            return $this->success(null, 'Budget status updated to '.$status);
        }
        $this->crud->updateBudget((int)$id, $data);
        return $this->success(null, 'Budget updated');
    }

    // ========================================
    // SECTION 16: Fee Waivers / Discounts
    // ========================================

    /** GET /api/finance/fee-waivers — list all discounts/waivers */
    public function getFeeWaivers($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listFeeWaivers($data));
    }

    /** POST /api/finance/fee-waivers — create waiver/discount */
    public function postFeeWaivers($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || empty($data['discount_type']) || !isset($data['discount_value'])) {
            return $this->badRequest('student_id, discount_type, discount_value are required');
        }
        if (empty($data['reason'])) return $this->badRequest('reason is required');
        $userId = $this->getUserId();
        $id = $this->crud->createFeeWaiver($data, $userId);
        return $this->success(['id' => $id], 'Waiver created successfully');
    }

    // ========================================
    // SECTION 17: Sponsored Students
    // ========================================

    /** GET /api/finance/sponsored-students — list sponsored students */
    public function getSponsoredStudents($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listSponsoredStudents());
    }

    // ==================== FEE CREDIT NOTES ====================

    public function getFeeCredits($id = null, $data = [], $segments = [])
    {
        $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
        $allowedStatuses = ['available', 'partially_applied', 'applied', 'expired'];
        $status = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : null;
        $filters = ['student_id' => $studentId, 'status' => $status];
        return $this->success($this->crud->listFeeCredits($filters));
    }

    public function postFeeCredits($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || empty($data['credit_amount'])) {
            return $this->badRequest('student_id and credit_amount are required');
        }
        $userId = $this->user['id'] ?? null;
        $result = $this->crud->createFeeCredit($data, $userId);
        return $this->success($result, 201);
    }

    public function putFeeCredits($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Credit note id is required');
        }
        $action = $data['action'] ?? 'apply';
        $credit = $this->crud->getFeeCredit((int)$id);
        if (!$credit) return $this->error('Credit note not found', 404);

        if ($action === 'refund') {
            $this->crud->refundFeeCredit((int)$id);
            return $this->success(['refunded' => true]);
        }

        $applyAmount = min((float)($data['apply_amount'] ?? 0), (float)$credit['remaining_amount']);
        if ($applyAmount <= 0) {
            return $this->badRequest('apply_amount must be greater than zero');
        }
        $this->crud->applyFeeCredit((int)$id, $applyAmount, $data);
        return $this->success(['applied' => $applyAmount]);
    }

    // ==================== SALARY ADVANCES ====================

    public function getSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;
        $allowedStatuses = ['pending', 'approved', 'rejected', 'active', 'fully_deducted'];
        $status = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : null;
        $filters = ['staff_id' => $staffId, 'status' => $status];
        return $this->success($this->crud->listSalaryAdvances($filters));
    }

    public function postSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $amount  = $data['requested_amount'] ?? null;
        if (!$staffId || !$amount) {
            return $this->badRequest('staff_id and requested_amount are required');
        }

        $existing = (float)$this->crud->getActiveAdvanceBalance((int)$staffId);
        $salary = (float)$this->crud->getStaffBasicSalary((int)$staffId);
        if ($salary > 0 && ($existing + (float)$amount) > $salary) {
            return $this->error(
                "Advance exceeds limit. Active balance: KES " . number_format($existing, 2) .
                ". Max (1 month salary): KES " . number_format($salary, 2), 422
            );
        }

        $advId = $this->crud->createSalaryAdvance($data);
        return $this->success(['id' => $advId], 201);
    }

    public function putSalaryAdvances($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Advance id is required');
        }
        $action = $data['action'] ?? null;
        $userId = $this->user['id'] ?? null;
        $advance = $this->crud->getSalaryAdvance((int)$id);
        if (!$advance) return $this->error('Advance not found', 404);

        if ($action === 'approve') {
            $approved = $data['approved_amount'] ?? $advance['requested_amount'];
            $months   = ['single_month' => 1, 'two_months' => 2, 'three_months' => 3][$advance['deduction_schedule']] ?? 1;
            $perDed   = round($approved / $months, 2);
            $start    = $data['deduction_start_month'] ?? date('Y-m-01', strtotime('first day of next month'));
            $this->crud->approveSalaryAdvance((int)$id, $approved, $perDed, $start, $userId);
            return $this->success(['approved' => true, 'per_deduction' => $perDed]);
        }
        if ($action === 'reject') {
            $this->crud->rejectSalaryAdvance((int)$id, $data['reason'] ?? null);
            return $this->success(['rejected' => true]);
        }
        if ($action === 'record_deduction') {
            $amt        = min((float)($data['amount'] ?? $advance['amount_per_deduction']), (float)$advance['balance_remaining']);
            $newBalance = max(0, (float)$advance['balance_remaining'] - $amt);
            $newStatus  = $newBalance <= 0 ? 'fully_deducted' : 'active';
            $this->crud->recordSalaryAdvanceDeduction((int)$id, $amt, $newBalance, $newStatus);
            return $this->success(['deducted' => $amt, 'remaining' => $newBalance]);
        }
        return $this->badRequest('Unknown action');
    }

    /**
     * GET /api/finance/unmatched-payments
     */
    public function getUnmatchedPayments($id = null, $data = [], $segments = [])
    {
        try {
            $page  = max(1, (int) ($_GET['page']  ?? $data['page']  ?? 1));
            $limit = max(1, min(200, (int) ($_GET['limit'] ?? $data['limit'] ?? 50)));
            $result = $this->crud->listUnmatchedPayments($page, $limit);
            return $this->success([
                'data'        => $result['data'],
                'total'       => $result['total'],
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => (int) ceil($result['total'] / $limit),
            ]);
        } catch (\Exception $e) {
            error_log('getUnmatchedPayments: ' . $e->getMessage());
            error_log('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /**
     * POST /api/finance/unmatched-payments/match
     */
    public function postUnmatchedPaymentsMatch($id = null, $data = [], $segments = [])
    {
        $paymentId  = $data['payment_id']  ?? null;
        $studentId  = $data['student_id']  ?? null;
        $obligationId = $data['obligation_id'] ?? null;

        if (!$paymentId) {
            return $this->badRequest('payment_id is required');
        }

        try {
            $this->crud->matchPayment((int)$paymentId, $studentId ? (int)$studentId : null, $obligationId ? (int)$obligationId : null);
            return $this->success(null, 'Payment matched successfully');
        } catch (\Exception $e) {
            error_log('postUnmatchedPaymentsMatch: ' . $e->getMessage());
            error_log('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }
}
