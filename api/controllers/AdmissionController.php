<?php
namespace App\API\Controllers;

use App\API\Modules\admission\AdmissionAdminManager;
use App\API\Modules\admission\AdmissionPolicy;
use Exception;

/**
 * AdmissionController — thin endpoint exposer.
 *
 * All data access, workflow orchestration, scope/permission decisions and
 * SQL now live in AdmissionAdminManager / StudentAdmissionWorkflow /
 * AdmissionPolicy / AdmissionStageAuthorization / AdmissionPaymentService.
 * This controller only: reads input, validates required fields, runs the
 * RBAC gates, delegates, and formats the response via handleApiResponse().
 *
 * Live-schema notes (KingsWayAcademy):
 *   - `routes` → `routes_registry`, `class_streams` → `academic_year_class_streams`,
 *     `parents.email/phone_*` → `persons` (see AdmissionAdminManager docblock).
 */
class AdmissionController extends BaseController
{
    private AdmissionAdminManager $admin;
    private AdmissionPolicy $policy;

    public function __construct() {
        parent::__construct();
        $this->admin = new AdmissionAdminManager();
        $this->policy = new AdmissionPolicy();
    }

    public function index()
    {
        return $this->success(['message' => 'Admission API is running']);
    }

    /**
     * GET /api/admissions/pending - Get pending admissions for dashboard
     */
    public function getPending($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view pending admissions');
        }

        return $this->handleApiResponse($this->admin->getPending($this->buildAdmissionContext()));
    }

    // 1. Application Submission
    public function postSubmitApplication($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('submit_application')) {
            return $this->forbidden('Insufficient permission to submit admission applications');
        }

        return $this->handleApiResponse($this->admin->workflow()->submitApplication($data));
    }

    // 2. Document Upload
    public function postUploadDocument($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $document_type = $data['document_type'] ?? null;
        $file = $_FILES['document'] ?? $_FILES['file'] ?? ($data['file'] ?? null);

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        if (!$document_type) {
            return $this->badRequest('document_type is required');
        }

        if (!$file) {
            return $this->badRequest('document file is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'upload_document', 'upload admission documents', 'Insufficient permission to upload admission documents');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->uploadDocument($application_id, $document_type, $file));
    }

    // 3. Document Verification
    public function postVerifyDocument($id = null, $data = [], $segments = [])
    {
        $document_id = $data['document_id'] ?? $id;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? '';

        if (!$document_id) {
            return $this->badRequest('document_id is required');
        }
        if (!in_array($status, ['verified', 'rejected'], true)) {
            return $this->badRequest('status must be either verified or rejected');
        }

        $application = $this->admin->getApplicationScopeRecordByDocument((int) $document_id);
        if (!$application) {
            return $this->notFound('Document or application not found');
        }

        $guard = $this->guardApplication($application, 'verify_document', 'verify admission documents', 'Insufficient permission to verify admission documents');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->verifyDocument($document_id, $status, $notes));
    }

    // 4. Interview Scheduling
    public function postScheduleInterview($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $interview_date = $data['interview_date'] ?? null;
        $interview_time = $data['interview_time'] ?? null;
        $venue = $data['venue'] ?? 'Main Office';

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'schedule_interview', 'schedule admission interviews', 'Insufficient permission to schedule admission interviews');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->scheduleInterview($application_id, $interview_date, $interview_time, $venue));
    }

    // 5. Interview Assessment
    public function postRecordInterviewResults($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assessment_data = $data['assessment_data'] ?? $data;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'record_interview', 'record interview results', 'Insufficient permission to record interview results');
        if ($guard !== null) {
            return $guard;
        }

        $assessment_data = $this->admin->normalizeInterviewAssessment($assessment_data);

        return $this->handleApiResponse($this->admin->workflow()->recordInterviewResults($application_id, $assessment_data));
    }

    // 6. Placement Offer
    public function postGeneratePlacementOffer($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assigned_class_id = $data['assigned_class_id'] ?? null;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'placement_offer', 'generate placement offers', 'Insufficient permission to generate placement offers');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->generatePlacementOffer($application_id, $assigned_class_id));
    }

    // 7. Fee Payment
    public function postRecordFeePayment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $payment_data = $this->admin->normalizePaymentData($data['payment_data'] ?? $data);

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        if (!isset($payment_data['amount']) || $payment_data['amount'] === '') {
            return $this->badRequest('amount is required');
        }
        if (empty($payment_data['method'])) {
            return $this->badRequest('payment method is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'record_payment', 'record admission fee payments', 'Insufficient permission to record admission fee payments');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->recordFeePayment($application_id, $payment_data));
    }

    // 8. Enrollment
    public function postCompleteEnrollment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'complete_enrollment', 'complete enrollment', 'Insufficient permission to complete enrollment');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->completeEnrollment($application_id));
    }

    public function getPolicy($id = null, $data = [], $segments = [])
    {
        return $this->success($this->policy->getPolicyPayload(), 'Admission policy retrieved');
    }

    public function getStageMatrix($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission stages');
        }

        return $this->handleApiResponse($this->admin->getStageMatrix($this->buildAdmissionContext()));
    }

    public function getPayments($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission payments');
        }

        return $this->handleApiResponse($this->admin->getPaymentsForApplication((int) $applicationId));
    }

    public function postConfirmEnrollment($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($data['application_id'] ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAdmissionPermission('confirm_enrollment')) {
            return $this->forbidden('Insufficient permission to confirm enrollment');
        }

        return $this->handleApiResponse($this->admin->workflow()->confirmEnrollment((int) $applicationId, (string) ($data['notes'] ?? '')));
    }

    /**
     * POST /api/admission/check-class-space/{id} - Record the class-space decision.
     * The read-only availability is GET getCheckClassSpace; this persists the
     * registrar/director decision (available vs. blocked) via the workflow proc.
     */
    public function postCheckClassSpace($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'check_class_space', 'check class space', 'Insufficient permission to check class space');
        if ($guard !== null) {
            return $guard;
        }

        $available = !empty($data['available']) && $data['available'] !== 'false';
        $notes = $data['notes'] ?? null;

        return $this->handleApiResponse($this->admin->workflow()->checkClassSpace($applicationId, (bool) $available, $notes ? (string) $notes : null));
    }

    /**
     * POST /api/admission/admit-student/{id} - Director/Headteacher admits the student.
     */
    public function postAdmitStudent($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'admit_student', 'admit student', 'Insufficient permission to admit student');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->admitStudent($applicationId));
    }

    /**
     * POST /api/admission/generate-student-id-card/{id} - Generate the ID card.
     */
    public function postGenerateStudentIdCard($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'generate_id_card', 'generate student ID card', 'Insufficient permission to generate student ID card');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->generateStudentIdCard($applicationId));
    }

    /**
     * POST /api/admission/final-approval/{id} - Final approval before enrollment.
     */
    public function postFinalApproval($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'final_approval', 'grant final approval', 'Insufficient permission to grant final approval');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->finalApproval($applicationId));
    }

    /**
     * GET /api/admission/check-class-space/{id} - Check class space availability for an application
     */
    public function getCheckClassSpace($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to check class space');
        }

        return $this->handleApiResponse($this->admin->checkClassSpaceAvailability($applicationId, $this->buildAdmissionContext()));
    }

    /**
     * POST /api/admission/advance-workflow-stage - Advance workflow stage with proper validation
     */
    public function postAdvanceWorkflowStage($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->admin->advanceWorkflowStage($data, $this->buildAdmissionContext()));
    }

    /**
     * POST /api/admission/create-provisional-student/{id} - Create provisional student record
     */
    public function postCreateProvisionalStudent($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        if (!$this->hasAdmissionPermission('submit_application')) {
            return $this->forbidden('Insufficient permission to create student record');
        }

        // createProvisionalStudent() already advances the workflow instance to
        // fees_payment and writes the student linkage, so no second
        // sp_advance_admission_workflow_stage call is needed here.
        return $this->handleApiResponse($this->admin->workflow()->createProvisionalStudent($applicationId));
    }

    /**
     * GET /api/admission/queues - Get workflow queues by stage for role-based views
     */
    public function getQueues($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admissions queues');
        }

        return $this->handleApiResponse($this->admin->getQueues($this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/application/{id} - Get single application details with full workflow status
     */
    public function getApplication($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission applications');
        }

        if (!$id) {
            return $this->badRequest('Application ID is required');
        }

        return $this->handleApiResponse($this->admin->getApplication((int) $id, $this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/placement-classes - Get active classes for placement offers
     */
    public function getPlacementClasses($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view placement classes');
        }

        return $this->handleApiResponse($this->admin->getPlacementClasses());
    }

    /**
     * GET /api/admission/stats - Get admission statistics for dashboards
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission statistics');
        }

        return $this->handleApiResponse($this->admin->getStats($this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/placement-tests - List placement tests (optionally /{id})
     * Live: admission_placement_tests joined with admission_applications.
     */
    public function getPlacementTests($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view placement tests');
        }

        return $this->handleApiResponse($this->admin->getPlacementTests($id !== null ? (int) $id : null));
    }

    /**
     * POST /api/admission/placement-test - Create a placement test for an application
     * Body: { application_id, test_date, test_code?, subject_area?, max_score? }
     */
    public function postPlacementTest($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to create placement tests');
        }

        return $this->handleApiResponse($this->admin->createPlacementTest($data, $this->buildAdmissionContext()));
    }

    /**
     * PUT /api/admission/placement-test/{id} - Record results for a placement test
     * Body: { score, max_score?, percentage?, recommendation?, remarks? }
     */
    public function putPlacementTest($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to record placement test results');
        }

        if ($id === null) {
            return $this->badRequest('Placement test ID is required');
        }

        return $this->handleApiResponse($this->admin->recordPlacementTestResult((int) $id, $data, $this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/notifications - Get role-specific admission notifications for dashboards
     */
    public function getNotifications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission notifications');
        }

        return $this->handleApiResponse($this->admin->getNotifications($this->buildAdmissionContext()));
    }

    // ========================================================================
    // PRIVATE HELPERS (RBAC gates only — no SQL, no business logic)
    // ========================================================================

    private function hasAdmissionPermission(string $group): bool
    {
        return $this->admin->hasAnyAdmissionPermission($group, $this->buildAdmissionContext());
    }

    /**
     * Load the application scope record then run the full guard stack.
     *
     * @return mixed null when allowed, otherwise a BaseController error response
     */
    private function guardApplicationAction(int $applicationId, string $actionGroup, string $denyMessage, string $permissionDenyMessage)
    {
        $application = $this->admin->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }

        return $this->guardApplication($application, $actionGroup, $denyMessage, $permissionDenyMessage);
    }

    /**
     * @return mixed null when allowed, otherwise a BaseController error response
     */
    private function guardApplication(array $application, string $actionGroup, string $denyMessage, string $permissionDenyMessage)
    {
        $ctx = $this->buildAdmissionContext();

        if (!$this->admin->canViewApplicationRecord($application, $ctx)) {
            return $this->forbidden('You do not have access to this admission application');
        }

        if (!$this->admin->canProcessAdmissionActionForApplication($actionGroup, $application, $ctx)) {
            return $this->forbidden($permissionDenyMessage);
        }

        $actionGuard = $this->admin->ensureApplicationActionAllowed($application, $actionGroup);
        if ($actionGuard !== null) {
            return $this->handleApiResponse($actionGuard);
        }

        return null;
    }

    private function buildAdmissionContext(): array
    {
        return [
            'user_id' => (int) ($this->getUserId() ?? 0),
            'role_ids' => $this->getCurrentUserRoleIds(),
            'role_names' => $this->getUserRoleNames(),
            'permission_codes' => $this->getCurrentUserPermissionCodes(),
            'effective_permissions' => is_array($this->user['effective_permissions'] ?? null) ? $this->user['effective_permissions'] : [],
            'email' => $this->user['email'] ?? null,
        ];
    }

    private function getCurrentUserRoleIds(): array
    {
        $roleIds = $this->getUserRoleIds();
        if (!empty($roleIds)) {
            return array_values(array_unique(array_map('intval', $roleIds)));
        }

        if (isset($this->user['role_ids']) && is_array($this->user['role_ids'])) {
            return array_values(array_unique(array_map('intval', $this->user['role_ids'])));
        }
        if (!empty($this->user['role_id'])) {
            return [(int) $this->user['role_id']];
        }

        return [];
    }

    private function getCurrentUserPermissionCodes(): array
    {
        $permissions = [];
        foreach (['permissions', 'effective_permissions'] as $key) {
            $source = $this->user[$key] ?? [];
            if (is_array($source)) {
                foreach ($source as $permission) {
                    $permissions[] = is_array($permission) ? (string) ($permission['code'] ?? $permission['name'] ?? '') : (string) $permission;
                }
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }
}
