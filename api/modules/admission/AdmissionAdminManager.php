<?php

namespace App\API\Modules\admission;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * Admission Admin Manager
 *
 * Owns every data read, mutation, scope/permission decision and workflow
 * orchestration for the admission module so the AdmissionController stays a
 * thin endpoint exposer (no direct DB access, no business decisions).
 *
 * Live-schema mapping (verified against KingsWayAcademy):
 *   - `routes` (absent)            → `routes_registry`
 *   - `class_streams` (absent)     → `academic_year_classes` + `academic_year_class_streams` + `streams`
 *   - `parents.email/phone_*`      → `persons` (parents holds only person_id/occupation/address/status)
 *   - `classes.status/capacity`    → dropped (columns absent); capacity lives on `streams`
 *   - placement classes student counts → `student_academic_enrollments` by stream
 *   - workflow stages/permissions  → `workflow_definitions`/`workflow_stages`/`workflow_stage_permissions`
 *
 * Guard primitives take a user-context array built by the controller from the
 * JWT payload (no DB): `user_id`, `role_ids`, `role_names`, `permission_codes`,
 * `effective_permissions`, `email`.
 */
class AdmissionAdminManager extends BaseAPI
{
    private const PERMISSIONS = [
        'view_any' => [
            'admission_view',
            'admission_applications_view_all',
            'admission_applications_view_own',
            'admission_applications_view',
        ],
        'view_all' => [
            'admission_applications_view_all',
        ],
        'view_own' => [
            'admission_applications_view_own',
        ],
        'submit_application' => [
            'admission_applications_create',
            'admission_applications_submit',
            'admission_manage',
        ],
        'review_application' => [
            'admission_manage',
        ],
        'upload_document' => [
            'admission_manage',
            'admission_documents_upload',
            'admission_documents_create',
            'admission_applications_upload',
        ],
        'verify_document' => [
            'admission_manage',
            'admission_documents_verify',
            'admission_documents_approve',
            'admission_documents_validate',
            'admission_applications_verify',
        ],
        'schedule_interview' => [
            'admission_manage',
            'admission_interviews_schedule',
            'admission_applications_schedule',
        ],
        'record_interview' => [
            'admission_manage',
            'admission_interviews_create',
            'admission_interviews_edit',
            'admission_interviews_approve',
            'admission_interviews_verify',
        ],
        'check_class_space' => [
            'admission_manage',
            'admission_applications_verify',
        ],
        'admit_student' => [
            'admission_approve',
            'admission_applications_approve_final',
        ],
        'create_provisional_student' => [
            'admission_approve',
            'admission_applications_create',
        ],
        'record_payment' => [
            'admission_payments_create',
            'admission_fee_payments_record',
            'admission_payments_record',
            'admission_applications_validate',
        ],
        'generate_id_card' => [
            'admission_manage',
            'admission_documents_create',
        ],
        'final_approval' => [
            'admission_approve',
            'admission_applications_approve_final',
        ],
        'complete_enrollment' => [
            'admission_enrollment_complete',
            'admission_applications_approve_final',
        ],
        'confirm_enrollment' => [
            'admission_enrollment_confirm',
        ],
    ];

    private const ACTION_STAGE_RULES = [
        'review_application' => ['application_received', 'application_review'],
        'upload_document' => ['application_review', 'documents_upload', 'documents_verification'],
        'verify_document' => ['documents_upload', 'documents_verification'],
        'check_class_space' => ['documents_verification', 'class_space_check'],
        'schedule_interview' => ['class_space_check', 'interview_scheduling'],
        'record_interview' => ['interview_scheduling', 'interview_results'],
        'placement_offer' => ['admission_decision', 'fees_payment'],
        'admit_student' => ['interview_results', 'admission_decision', 'class_space_check'],
        'create_provisional_student' => ['provisional_student_creation'],
        'record_payment' => ['fees_payment', 'student_id_generation'],
        'generate_id_card' => ['student_id_generation', 'final_approval'],
        'final_approval' => ['final_approval', 'enrollment'],
        'complete_enrollment' => ['enrollment'],
        'confirm_enrollment' => ['enrolled', 'director_confirmation'],
    ];

    private const VALID_TRANSITIONS = [
        'application_received' => ['application_review', 'rejected'],
        'application_review' => ['documents_upload', 'rejected'],
        'documents_upload' => ['documents_verification', 'rejected'],
        'documents_verification' => ['class_space_check', 'documents_upload', 'rejected'],
        'class_space_check' => ['interview_scheduling', 'rejected'],
        'interview_scheduling' => ['interview_results', 'cancelled'],
        'interview_results' => ['admission_decision', 'rejected'],
        'admission_decision' => ['provisional_student_creation', 'rejected'],
        'provisional_student_creation' => ['fees_payment', 'rejected'],
        'fees_payment' => ['student_id_generation', 'cancelled'],
        'student_id_generation' => ['final_approval', 'rejected'],
        'final_approval' => ['enrollment', 'rejected'],
        'enrollment' => ['enrolled'],
        'rejected' => [],
        'enrolled' => [],
    ];

    private const ADMISSION_ROUTE_NAMES = [
        'manage_students_admissions',
        'admissions/director_admissions',
        'admissions/enrollment_confirmations',
    ];

    private AdmissionPolicy $policy;
    private AdmissionPaymentService $paymentService;
    private ?AdmissionStageAuthorization $stageAuthorization = null;
    private ?StudentAdmissionWorkflow $workflow = null;

    private bool $resolvedWorkflowId = false;
    private int $workflowId = 0;

    private bool $resolvedWorkflowStages = false;
    private array $workflowStageConfig = [];

    private bool $resolvedAdmissionRouteAccess = false;
    private bool $admissionRouteAccess = false;
    private int $admissionRouteAccessUserId = 0;

    private bool $resolvedAdmissionsRouteRoleAliases = false;
    private array $admissionsRouteRoleAliases = [];

    private array $parentIdCache = [];

    public function __construct()
    {
        parent::__construct('admission');
        $this->policy = new AdmissionPolicy();
        $this->paymentService = new AdmissionPaymentService($this->db);
    }

    public function workflow(): StudentAdmissionWorkflow
    {
        if ($this->workflow === null) {
            $this->workflow = new StudentAdmissionWorkflow();
        }

        return $this->workflow;
    }

    public function paymentService(): AdmissionPaymentService
    {
        return $this->paymentService;
    }

    // ========================================================================
    // ENDPOINT OPERATIONS (return BaseAPI response arrays)
    // ========================================================================

    public function getPending(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);

            $stmt = $this->db->query(
                "SELECT COUNT(*) as total_pending
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.status NOT IN ('enrolled', 'cancelled'){$scopeFilter}"
            );
            $countRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalPending = (int) ($countRow['total_pending'] ?? 0);

            $rows = $this->db->query(
                "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, aa.status, aa.created_at as admission_date, wi.current_stage
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.status NOT IN ('enrolled', 'cancelled'){$scopeFilter}
                 ORDER BY aa.created_at DESC
                 LIMIT 8"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $recentAdmissions = [];
            foreach ($rows as $row) {
                $recentAdmissions[] = [
                    'id' => $row['id'],
                    'application_no' => $row['application_no'] ?? null,
                    'applicant_name' => $row['applicant_name'] ?? 'Unknown',
                    'grade_applying_for' => $row['grade_applying_for'] ?? null,
                    'current_stage' => $row['current_stage'] ?? null,
                    'admission_date' => $row['admission_date'],
                    'status' => $row['status'] ?? 'submitted',
                ];
            }

            return $this->successResponse([
                'total_pending' => $totalPending,
                'recent' => $recentAdmissions,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Pending admissions retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getQueues(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);
            $stageMatrix = $this->getStageAuthorization()->getStageMatrix(
                $this->ctxRoleIds($ctx),
                $this->ctxPermissionCodes($ctx)
            );
            $hasAdmissionOversight = $this->hasAdmissionRouteAccess($ctx)
                && $this->ctxHasRole([3, 5, 6], ['Director', 'Headteacher', 'Deputy Head - Academic'], $ctx);
            $canViewStage = static function (array $stages) use ($stageMatrix, $hasAdmissionOversight): bool {
                foreach ($stages as $stage) {
                    if (!empty($stageMatrix[$stage]['can_view']) || $hasAdmissionOversight) {
                        return true;
                    }
                }

                return false;
            };
            $canViewReview = $canViewStage(['application_received', 'application_review']);
            $canViewDocuments = $canViewStage(['documents_upload', 'documents_verification']);
            $canViewSpace = $canViewStage(['class_space_check']);
            $canViewInterview = $canViewStage(['interview_scheduling', 'interview_results']);
            $canViewDecision = $canViewStage(['admission_decision', 'provisional_student_creation']);
            $canViewPlacement = $canViewStage(['placement_offer']);
            $canViewPayment = $canViewStage(['fees_payment']);
            $canViewId = $canViewStage(['student_id_generation']);
            $canViewFinalApproval = $canViewStage(['final_approval']);
            $canViewEnrollment = $canViewStage(['enrollment']);
            $canReview = $this->canProcessAdmissionActionForStage('review_application', 'application_review', $ctx)
                || $this->canProcessAdmissionActionForStage('review_application', 'application_received', $ctx);
            $canUploadDocuments = $this->canProcessAdmissionActionForStage('upload_document', 'application_review', $ctx)
                || $this->canProcessAdmissionActionForStage('upload_document', 'documents_upload', $ctx);
            $canVerifyDocuments = $this->canProcessAdmissionActionForStage('verify_document', 'documents_upload', $ctx)
                || $this->canProcessAdmissionActionForStage('verify_document', 'documents_verification', $ctx);
            $canCheckSpace = $this->canProcessAdmissionActionForStage('check_class_space', 'documents_verification', $ctx)
                || $this->canProcessAdmissionActionForStage('check_class_space', 'class_space_check', $ctx);
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'class_space_check', $ctx)
                || $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling', $ctx);
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_scheduling', $ctx)
                || $this->canProcessAdmissionActionForStage('record_interview', 'interview_results', $ctx);
            $canAdmit = $this->canProcessAdmissionActionForStage('admit_student', 'interview_results', $ctx)
                || $this->canProcessAdmissionActionForStage('admit_student', 'admission_decision', $ctx)
                || $this->canProcessAdmissionActionForStage('admit_student', 'class_space_check', $ctx);
            $canCreateProvisional = $this->canProcessAdmissionActionForStage('create_provisional_student', 'provisional_student_creation', $ctx);
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fees_payment', $ctx)
                || $this->canProcessAdmissionActionForStage('record_payment', 'student_id_generation', $ctx);
            $canGenerateId = $this->canProcessAdmissionActionForStage('generate_id_card', 'student_id_generation', $ctx)
                || $this->canProcessAdmissionActionForStage('generate_id_card', 'final_approval', $ctx);
            $canFinalApproval = $this->canProcessAdmissionActionForStage('final_approval', 'final_approval', $ctx)
                || $this->canProcessAdmissionActionForStage('final_approval', 'enrollment', $ctx);
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'enrollment', $ctx);

            $queues = [
                'review_pending' => [],
                'documents_pending' => [],
                'space_check_pending' => [],
                'interview_pending' => [],
                'decision_pending' => [],
                'placement_pending' => [],
                'payment_pending' => [],
                'id_generation_pending' => [],
                'final_approval_pending' => [],
                'enrollment_pending' => [],
                'completed' => [],
            ];

            $baseSelect = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.application_source, aa.updated_at,
                           pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                           wi.current_stage, wi.data_json,
                           aa.workflow_data_json,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN persons pp ON pp.id = p.person_id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id";

            $compactSelect = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN persons pp ON pp.id = p.person_id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id";

            if ($canViewReview || $canReview) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage IN ('application_received', 'application_review')
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['review_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewDocuments || $canUploadDocuments || $canVerifyDocuments) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage IN ('documents_upload', 'documents_verification')
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['documents_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewSpace || $canCheckSpace) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage = 'class_space_check'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['space_check_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewInterview || $canScheduleInterview || $canRecordInterview) {
                $interviewStageFilters = [];
                if ($canViewInterview) {
                    $interviewStageFilters[] = "wi.current_stage IN ('interview_scheduling', 'interview_results')";
                } else {
                    if ($canScheduleInterview) {
                        $interviewStageFilters[] = "wi.current_stage = 'interview_scheduling'";
                    }
                    if ($canRecordInterview) {
                        $interviewStageFilters[] = "wi.current_stage = 'interview_results'";
                    }
                }

                $interviewStageSql = empty($interviewStageFilters)
                    ? '1 = 0'
                    : implode(' OR ', $interviewStageFilters);

                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE ({$interviewStageSql})
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['interview_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewDecision || $canAdmit || $canCreateProvisional) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage IN ('admission_decision', 'provisional_student_creation')
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['decision_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewPlacement || $hasAdmissionOversight) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'placement_offer'
                       AND aa.status NOT IN ('placement_offered', 'fees_pending', 'enrolled', 'cancelled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['placement_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewPayment || $canRecordPayment) {
                $stmt = $this->db->query(
                    "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                            aa.status, aa.created_at,
                            pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                            wi.current_stage, wi.data_json,
                            JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.total_fees')) as total_fees,
                            JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.assigned_class_id')) as assigned_class_id
                     FROM admission_applications aa
                     LEFT JOIN parents p ON aa.parent_id = p.id
                     LEFT JOIN persons pp ON pp.id = p.person_id
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage = 'fees_payment'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['payment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewId || $canGenerateId) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'student_id_generation'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['id_generation_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewFinalApproval || $canFinalApproval) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'final_approval'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['final_approval_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewEnrollment || $canCompleteEnrollment) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'enrollment'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['enrollment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            $stmt = $this->db->query(
                "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                        aa.status, aa.created_at, aa.enrolled_student_id, aa.application_source,
                        pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                        wi.current_stage, wi.data_json
                 FROM admission_applications aa
                 LEFT JOIN parents p ON aa.parent_id = p.id
                 LEFT JOIN persons pp ON pp.id = p.person_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE wi.current_stage = 'enrolled'
                 {$scopeFilter}
                 ORDER BY aa.created_at DESC"
            );
            $queues['completed'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);

            $summary = [
                'review_pending' => count($queues['review_pending']),
                'documents_pending' => count($queues['documents_pending']),
                'space_check_pending' => count($queues['space_check_pending']),
                'interview_pending' => count($queues['interview_pending']),
                'decision_pending' => count($queues['decision_pending']),
                'payment_pending' => count($queues['payment_pending']),
                'id_generation_pending' => count($queues['id_generation_pending']),
                'final_approval_pending' => count($queues['final_approval_pending']),
                'enrollment_pending' => count($queues['enrollment_pending']),
                'completed' => count($queues['completed']),
                'total_pending' => count($queues['review_pending']) + count($queues['documents_pending'])
                    + count($queues['space_check_pending']) + count($queues['interview_pending'])
                    + count($queues['decision_pending']) + count($queues['payment_pending'])
                    + count($queues['id_generation_pending']) + count($queues['final_approval_pending'])
                    + count($queues['enrollment_pending']),
            ];

            return $this->successResponse([
                'queues' => $queues,
                'summary' => $summary,
                'allowed_tabs' => [
                    'review_pending' => $canReview,
                    'documents_pending' => ($canUploadDocuments || $canVerifyDocuments),
                    'space_check_pending' => $canCheckSpace,
                    'interview_pending' => ($canScheduleInterview || $canRecordInterview),
                    'decision_pending' => ($canAdmit || $canCreateProvisional),
                    'payment_pending' => $canRecordPayment,
                    'id_generation_pending' => $canGenerateId,
                    'final_approval_pending' => $canFinalApproval,
                    'enrollment_pending' => $canCompleteEnrollment,
                    'completed' => true,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Workflow queues retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getApplication(int $id, array $ctx): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*,
                        pp.first_name as parent_first_name, pp.last_name as parent_last_name,
                        pp.phone as phone_1, pp.phone as phone_2, pp.email as parent_email,
                        wi.id as workflow_instance_id, wi.current_stage, wi.status as workflow_status, wi.data_json,
                        wi.started_by, wi.started_at
                 FROM admission_applications aa
                 LEFT JOIN parents p ON aa.parent_id = p.id
                 LEFT JOIN persons pp ON pp.id = p.person_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([(int) $id]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->errorResponse('Application not found', 404);
            }

            if (!$this->canViewApplicationRecord($application, $ctx)) {
                return $this->errorResponse('You do not have access to this admission application', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT ad.*,
                        mf.filename as media_filename,
                        mf.original_name as media_original_name,
                        mf.file_type as media_file_type,
                        mf.context as media_context,
                        mf.entity_id as media_entity_id,
                        mf.album_id as media_album_id
                 FROM admission_documents ad
                 LEFT JOIN media_files mf
                   ON ad.document_path REGEXP '^[0-9]+$'
                  AND mf.id = CAST(ad.document_path AS UNSIGNED)
                 WHERE ad.application_id = ?
                 ORDER BY ad.is_mandatory DESC, ad.document_type"
            );
            $stmt->execute([(int) $id]);
            $documents = $this->normalizeAdmissionDocuments($stmt->fetchAll(\PDO::FETCH_ASSOC));

            $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
            $workflowData = $this->syncWorkflowIdentityData($workflowData, $application);

            $availableActions = $this->getAvailableActions($application['current_stage'], $application['status'], $ctx);
            $stageMeta = $this->getCurrentStageMetadata($application['current_stage']);
            $currentStageCode = $this->normalizeStageCode($application['current_stage']) ?? $this->inferStageFromApplication($application);
            $currentStageRequiredRole = $stageMeta['required_role'] ?? null;

            return $this->successResponse([
                'application' => $application,
                'documents' => $documents,
                'workflow_data' => $workflowData,
                'available_actions' => $availableActions,
                'stage_metadata' => [
                    'current_stage' => $currentStageCode,
                    'display_name' => $stageMeta['name'] ?? null,
                    'required_role' => $currentStageRequiredRole,
                    'user_matches_required_role' => $this->userMatchesRequiredRole($currentStageRequiredRole, $ctx),
                    'allowed_transitions' => $this->getAllowedTransitionsForStage($currentStageCode),
                ],
            ], 'Application details retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getPlacementClasses(): array
    {
        try {
            $rows = $this->db->query(
                "SELECT c.id,
                        c.name,
                        COALESCE(s.capacity, 0) AS capacity,
                        COUNT(sae.id) AS student_count
                 FROM classes c
                 LEFT JOIN academic_year_classes ayc ON ayc.class_id = c.id AND ayc.status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id AND aycs.status = 'active'
                 LEFT JOIN streams s ON s.id = aycs.stream_id
                 LEFT JOIN student_academic_enrollments sae
                   ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active'
                 GROUP BY c.id, c.name, s.capacity
                 ORDER BY c.name ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $classes = array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'capacity' => isset($row['capacity']) ? (int) $row['capacity'] : null,
                    'student_count' => (int) ($row['student_count'] ?? 0),
                ];
            }, $rows ?: []);

            return $this->successResponse(['classes' => $classes], 'Placement classes retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getStats(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);

            $stats = [];

            $stmt = $this->db->query(
                "SELECT COUNT(*) as total
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}"
            );
            $stats['total_applications'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->query(
                "SELECT aa.status, COUNT(*) as count
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}
                 GROUP BY aa.status"
            );
            $stats['by_status'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $stmt = $this->db->query(
                "SELECT grade_applying_for, COUNT(*) as count
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}
                 GROUP BY grade_applying_for"
            );
            $stats['by_grade'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $stmt = $this->db->query(
                "SELECT COUNT(*)
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY){$scopeFilter}"
            );
            $stats['this_week'] = (int) $stmt->fetchColumn();

            $stats['enrolled'] = (int) ($stats['by_status']['enrolled'] ?? 0);
            $stats['pending'] = $stats['total_applications'] - $stats['enrolled'] - (int) ($stats['by_status']['cancelled'] ?? 0);

            return $this->successResponse($stats, 'Admission statistics retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getPlacementTests(?int $id): array
    {
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare(
                    "SELECT apt.*,
                            aa.applicant_name,
                            aa.application_no,
                            aa.grade_applying_for
                     FROM admission_placement_tests apt
                     JOIN admission_applications aa ON aa.id = apt.application_id
                     WHERE apt.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$id]);
                $test = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$test) {
                    return $this->errorResponse('Placement test not found', 404);
                }

                return $this->successResponse($test, 'Placement test retrieved');
            }

            $stmt = $this->db->prepare(
                "SELECT apt.*,
                        aa.applicant_name,
                        aa.application_no,
                        aa.grade_applying_for
                 FROM admission_placement_tests apt
                 JOIN admission_applications aa ON aa.id = apt.application_id
                 ORDER BY apt.created_at DESC"
            );
            $stmt->execute();
            $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->successResponse($tests, 'Placement tests retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function createPlacementTest(array $data, array $ctx): array
    {
        try {
            $applicationId = (int) ($data['application_id'] ?? 0);
            if ($applicationId <= 0) {
                return $this->errorResponse('application_id is required', 400);
            }

            $application = $this->getApplicationScopeRecord($applicationId);
            if (!$application) {
                return $this->errorResponse('Application not found', 404);
            }
            if (!$this->canViewApplicationRecord($application, $ctx)) {
                return $this->errorResponse('You do not have access to this admission application', 403);
            }

            $testDate = trim((string) ($data['test_date'] ?? ''));
            if ($testDate === '') {
                return $this->errorResponse('test_date is required', 400);
            }

            $testCode = trim((string) ($data['test_code'] ?? ''));
            if ($testCode === '') {
                $testCode = 'PT-' . $application['application_no'];
            }

            $subjectArea = trim((string) ($data['subject_area'] ?? 'General'));
            $maxScore = (float) ($data['max_score'] ?? 100);
            if ($maxScore <= 0) {
                $maxScore = 100;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO admission_placement_tests (
                    application_id, test_code, test_date, subject_area, max_score, recorded_by
                 ) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $applicationId,
                $testCode,
                $testDate,
                $subjectArea,
                $maxScore,
                $this->ctxUserId($ctx),
            ]);

            $testId = (int) $this->db->lastInsertId();

            return $this->successResponse(
                ['id' => $testId, 'test_code' => $testCode, 'application_id' => $applicationId],
                'Placement test created successfully',
                201
            );
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function recordPlacementTestResult(int $id, array $data, array $ctx): array
    {
        try {
            $score = isset($data['score']) ? (float) $data['score'] : null;
            if ($score === null || $score < 0) {
                return $this->errorResponse('score is required and must be a non-negative number', 400);
            }

            $maxScore = (float) ($data['max_score'] ?? 100);
            if ($maxScore <= 0) {
                $maxScore = 100;
            }

            $percentage = isset($data['percentage']) ? (int) $data['percentage'] : (int) round(($score / $maxScore) * 100);

            $recommendation = strtolower(trim((string) ($data['recommendation'] ?? '')));
            if ($recommendation !== '' && !in_array($recommendation, ['promote', 'retain', 'conditional'], true)) {
                return $this->errorResponse('recommendation must be one of: promote, retain, conditional', 400);
            }
            $recommendation = $recommendation !== '' ? $recommendation : null;

            $remarks = trim((string) ($data['remarks'] ?? ($data['recommended_class'] ?? '')));
            $remarks = $remarks !== '' ? $remarks : null;

            $stmt = $this->db->prepare(
                "UPDATE admission_placement_tests
                 SET score = ?, max_score = ?, percentage = ?, recommendation = ?, remarks = ?, recorded_by = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $score,
                $maxScore,
                $percentage,
                $recommendation,
                $remarks,
                $this->ctxUserId($ctx),
                (int) $id,
            ]);

            if ($stmt->rowCount() === 0) {
                return $this->errorResponse('Placement test not found', 404);
            }

            return $this->successResponse(['id' => (int) $id], 'Placement test results recorded successfully');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getNotifications(array $ctx): array
    {
        try {
            $roleIds = $this->ctxRoleIds($ctx);
            $notifications = [
                'pending_tasks' => [],
                'total_count' => 0,
                'role' => !empty($roleIds) ? $roleIds[0] : null,
            ];

            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);
            $canUploadDocuments = $this->canProcessAdmissionActionForStage('upload_document', 'application', $ctx)
                || $this->canProcessAdmissionActionForStage('upload_document', 'document_verification', $ctx);
            $canVerifyDocuments = $this->canProcessAdmissionActionForStage('verify_document', 'document_verification', $ctx);
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling', $ctx);
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_assessment', $ctx);
            $canPlacement = $this->canProcessAdmissionActionForStage('placement_offer', 'placement_offer', $ctx);
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fee_payment', $ctx);
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'enrollment', $ctx);

            if ($canUploadDocuments || $canVerifyDocuments) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE aa.status IN ('submitted', 'documents_pending'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'documents_pending',
                        'label' => $canVerifyDocuments ? 'Documents to Verify' : 'Documents to Upload',
                        'count' => $count,
                        'icon' => 'bi-file-earmark-text',
                        'color' => 'warning',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=documents_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canScheduleInterview || $canRecordInterview) {
                $interviewStageFilters = [];
                if ($canScheduleInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_scheduling'";
                }
                if ($canRecordInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_assessment'";
                }

                $interviewStageSql = empty($interviewStageFilters)
                    ? '1 = 0'
                    : implode(' OR ', $interviewStageFilters);

                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE ({$interviewStageSql})
                       AND aa.status NOT IN ('cancelled', 'enrolled'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'interview_pending',
                        'label' => 'Interviews Pending',
                        'count' => $count,
                        'icon' => 'bi-calendar-event',
                        'color' => 'info',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=interview_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canPlacement) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage = 'placement_offer'
                       AND aa.status NOT IN ('placement_offered', 'fees_pending', 'enrolled', 'cancelled')
                     {$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'placement_pending',
                        'label' => 'Placements to Generate',
                        'count' => $count,
                        'icon' => 'bi-check-circle',
                        'color' => 'primary',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=placement_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canRecordPayment) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE aa.status IN ('placement_offered', 'fees_pending'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'payment_pending',
                        'label' => 'Payments to Record',
                        'count' => $count,
                        'icon' => 'bi-cash-stack',
                        'color' => 'success',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=payment_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canCompleteEnrollment) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*) FROM admission_applications aa
                     JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage = 'enrollment' AND aa.status != 'enrolled'{$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'enrollment_pending',
                        'label' => 'Enrollments to Complete',
                        'count' => $count,
                        'icon' => 'bi-person-check',
                        'color' => 'dark',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=enrollment_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            return $this->successResponse($notifications, 'Notifications retrieved');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function checkClassSpaceAvailability(int $applicationId, array $ctx): array
    {
        try {
            $stmt = $this->db->prepare("CALL sp_check_class_space_availability(?, ?)");
            $stmt->execute([$applicationId, $this->ctxUserId($ctx)]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->successResponse(['space_check' => $result], 'Class space availability checked');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function advanceWorkflowStage(array $data, array $ctx): array
    {
        try {
            $applicationId = (int) ($data['application_id'] ?? 0);
            $toStage = $data['to_stage'] ?? null;
            $action = $data['action'] ?? 'workflow_advance';
            $notes = $data['notes'] ?? null;
            $workflowUpdates = $data['workflow_updates'] ?? null;

            if ($applicationId <= 0 || !$toStage) {
                return $this->errorResponse('Application ID and target stage are required', 400);
            }

            if (!$this->isValidWorkflowTransition($applicationId, $toStage)) {
                return $this->errorResponse('Invalid workflow transition for current stage', 400);
            }

            $stmt = $this->db->prepare("CALL sp_advance_admission_workflow_stage(?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $applicationId,
                $toStage,
                $action,
                $this->ctxUserId($ctx),
                $notes,
                $workflowUpdates,
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->successResponse([
                'workflow_instance_id' => $result['workflow_instance_id'] ?? null,
                'from_stage' => $result['from_stage'] ?? null,
                'to_stage' => $result['to_stage'] ?? null,
            ], 'Workflow stage advanced successfully');
        } catch (Exception $e) {
            error_log('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getStageMatrix(array $ctx): array
    {
        $matrix = $this->getStageAuthorization()->getStageMatrix(
            $this->ctxRoleIds($ctx),
            $this->ctxPermissionCodes($ctx)
        );

        $allowedTabs = [
            'documents_pending' => !empty($matrix['application']['can_view']) || !empty($matrix['document_verification']['can_view']),
            'interview_pending' => !empty($matrix['interview_scheduling']['can_view']) || !empty($matrix['interview_assessment']['can_view']),
            'placement_pending' => !empty($matrix['placement_offer']['can_view']),
            'payment_pending' => !empty($matrix['fee_payment']['can_view']),
            'enrollment_pending' => !empty($matrix['enrollment']['can_view']),
            'director_confirmation_pending' => !empty($matrix['director_confirmation']['can_view']),
        ];

        return $this->successResponse([
            'workflow' => 'student_admission',
            'stages' => array_values($matrix),
            'allowed_tabs' => $allowedTabs,
        ], 'Admission stage matrix retrieved');
    }

    public function getPaymentsForApplication(int $applicationId): array
    {
        return $this->successResponse([
            'payments' => $this->paymentService->getPaymentsForApplication($applicationId),
            'total_recorded' => $this->paymentService->getTotalRecorded($applicationId),
        ], 'Admission payments retrieved');
    }

    // ========================================================================
    // GUARD PRIMITIVES (used by the controller before delegating writes)
    // ========================================================================

    public function getApplicationScopeRecord(int $applicationId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $application ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getApplicationScopeRecordByDocument(int $documentId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                 FROM admission_documents ad
                 JOIN admission_applications aa ON aa.id = ad.application_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE ad.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$documentId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $application ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function canViewApplicationRecord(array $application, array $ctx): bool
    {
        if (
            $this->hasAnyAdmissionPermission('view_all', $ctx)
            || $this->ctxHasAnyPermission(['admission_view'], $ctx)
            || $this->hasAdmissionRouteAccess($ctx)
        ) {
            return true;
        }

        $applicationParentId = (int) ($application['parent_id'] ?? 0);

        if ($this->isParentLinkedToApplication($applicationParentId, $ctx)) {
            return true;
        }

        if (!$this->hasAnyAdmissionPermission('view_own', $ctx)) {
            return false;
        }

        $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
        $userId = $this->ctxUserId($ctx);
        $candidateOwnerIds = [
            (int) ($workflowData['assigned_to'] ?? 0),
            (int) ($workflowData['assigned_user_id'] ?? 0),
            (int) ($workflowData['created_by'] ?? 0),
            (int) ($workflowData['submitted_by'] ?? 0),
            (int) ($application['started_by'] ?? 0),
        ];

        return in_array($userId, $candidateOwnerIds, true);
    }

    public function canProcessAdmissionActionForApplication(string $actionGroup, array $application, array $ctx): bool
    {
        if (!$this->hasAnyAdmissionPermission($actionGroup, $ctx)) {
            return false;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return false;
        }

        return $this->canProcessAdmissionActionForStage($actionGroup, $currentStage, $ctx);
    }

    public function canProcessAdmissionActionForStage(string $actionGroup, ?string $stageCode, array $ctx): bool
    {
        if (!$this->hasAnyAdmissionPermission($actionGroup, $ctx)) {
            return false;
        }

        $stageCode = $this->normalizeStageCode($stageCode);
        if (!$stageCode) {
            return false;
        }

        $expectedStages = self::ACTION_STAGE_RULES[$actionGroup] ?? [];
        if (empty($expectedStages)) {
            return false;
        }

        $expectedNormalized = array_values(array_filter(array_map([$this, 'normalizeStageCode'], $expectedStages)));
        if (!in_array($stageCode, $expectedNormalized, true)) {
            return false;
        }

        if ($this->canActViaStagePermissions($actionGroup, $stageCode, $ctx)) {
            return true;
        }

        $requiredRole = $this->getStageRequiredRole($stageCode);
        if (!$requiredRole) {
            return $this->hasAnyAdmissionPermission($actionGroup, $ctx) || $this->hasAdmissionRouteAccess($ctx);
        }

        if ($this->userMatchesRequiredRole($requiredRole, $ctx)) {
            return true;
        }

        if ($this->hasAnyAdmissionPermission($actionGroup, $ctx) && $this->canBypassAdmissionStageRole($ctx)) {
            return true;
        }

        return false;
    }

    public function getAvailableActions(?string $currentStage, string $status, array $ctx): array
    {
        $actions = [];

        if (!$this->hasAnyAdmissionPermission('view_any', $ctx)) {
            return $actions;
        }

        if ($status === 'cancelled') {
            return [];
        }
        if ($status === 'enrolled') {
            $normalizedStage = $this->normalizeStageCode($currentStage);
            if ($normalizedStage !== 'director_confirmation') {
                return [];
            }
        }

        $normalizedStage = $this->normalizeStageCode($currentStage) ?? $this->inferStageFromApplication(['status' => $status]);
        $requiredRole = $this->getStageRequiredRole($normalizedStage);
        if (!$this->userMatchesRequiredRole($requiredRole, $ctx) && !$this->canBypassAdmissionStageRole($ctx)) {
            return [];
        }

        switch ($normalizedStage) {
            case 'application_received':
            case 'application_review':
                if ($this->canProcessAdmissionActionForStage('review_application', $normalizedStage, $ctx)) {
                    $actions[] = 'review-application';
                }
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage, $ctx)) {
                    $actions[] = 'upload-documents';
                }
                break;
            case 'documents_upload':
            case 'documents_verification':
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage, $ctx)) {
                    $actions[] = 'upload-documents';
                }
                if ($this->canProcessAdmissionActionForStage('verify_document', $normalizedStage, $ctx)) {
                    $actions[] = 'verify-documents';
                }
                break;
            case 'class_space_check':
                if ($this->canProcessAdmissionActionForStage('check_class_space', $normalizedStage, $ctx)) {
                    $actions[] = 'check-class-space';
                }
                break;
            case 'interview_scheduling':
                if ($this->canProcessAdmissionActionForStage('schedule_interview', $normalizedStage, $ctx)) {
                    $actions = ['schedule-interview'];
                }
                break;
            case 'interview_results':
                if ($this->canProcessAdmissionActionForStage('record_interview', $normalizedStage, $ctx)) {
                    $actions = ['record-interview'];
                }
                if ($this->canProcessAdmissionActionForStage('admit_student', $normalizedStage, $ctx)) {
                    $actions[] = 'admit-student';
                }
                break;
            case 'admission_decision':
                if ($this->canProcessAdmissionActionForStage('admit_student', $normalizedStage, $ctx)) {
                    $actions = ['admit-student'];
                }
                break;
            case 'provisional_student_creation':
                if ($this->canProcessAdmissionActionForStage('create_provisional_student', $normalizedStage, $ctx)) {
                    $actions = ['create-provisional-student'];
                }
                break;
            case 'fees_payment':
            case 'student_id_generation':
                if ($this->canProcessAdmissionActionForStage('record_payment', $normalizedStage, $ctx)) {
                    $actions[] = 'record-payment';
                }
                if ($this->canProcessAdmissionActionForStage('generate_id_card', $normalizedStage, $ctx)) {
                    $actions[] = 'generate-id-card';
                }
                break;
            case 'final_approval':
                if ($this->canProcessAdmissionActionForStage('final_approval', $normalizedStage, $ctx)) {
                    $actions = ['final-approval'];
                }
                break;
            case 'enrollment':
                if ($this->canProcessAdmissionActionForStage('complete_enrollment', $normalizedStage, $ctx)) {
                    $actions = ['complete-enrollment'];
                }
                break;
            case 'enrolled':
            case 'director_confirmation':
                if ($this->canProcessAdmissionActionForStage('confirm_enrollment', $normalizedStage, $ctx)) {
                    $actions = ['confirm-enrollment'];
                }
                break;
            default:
                $actions = [];
        }

        return $actions;
    }

    /**
     * @return array|null An error response array when the action is not allowed, or null when allowed.
     */
    public function ensureApplicationActionAllowed(array $application, string $actionGroup): ?array
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        if (in_array($status, ['cancelled', 'enrolled'], true)) {
            return $this->errorResponse('This application can no longer be modified in its current status.', 409);
        }

        $expectedStages = self::ACTION_STAGE_RULES[$actionGroup] ?? [];
        if (empty($expectedStages)) {
            return null;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return $this->errorResponse('Workflow stage is not available for this application.', 409);
        }

        $expectedNormalized = array_map([$this, 'normalizeStageCode'], $expectedStages);
        if (!in_array($currentStage, $expectedNormalized, true)) {
            $stageMeta = $this->getCurrentStageMetadata($currentStage);
            $stageLabel = $stageMeta['name'] ?? str_replace('_', ' ', $currentStage);

            return $this->errorResponse("Action is not allowed at workflow stage '{$stageLabel}'.", 409);
        }

        return null;
    }

    public function hasAnyAdmissionPermission(string $group, array $ctx): bool
    {
        $permissionCodes = self::PERMISSIONS[$group] ?? [];
        $hasPermission = !empty($permissionCodes) && $this->ctxHasAnyPermission($permissionCodes, $ctx);

        if ($hasPermission) {
            return true;
        }

        if ($this->admissionRoleCanProcessGroup($group, $ctx)) {
            return true;
        }

        if ($group === 'view_any') {
            return $this->hasAdmissionRouteAccess($ctx);
        }

        return false;
    }

    /**
     * Normalise legacy payment field aliases into the canonical workflow names.
     */
    public function normalizePaymentData(array $paymentData): array
    {
        if (isset($paymentData['amount_paid']) && !isset($paymentData['amount'])) {
            $paymentData['amount'] = $paymentData['amount_paid'];
        }
        if (isset($paymentData['payment_method']) && !isset($paymentData['method'])) {
            $paymentData['method'] = $paymentData['payment_method'];
        }
        if (isset($paymentData['transaction_reference']) && !isset($paymentData['reference'])) {
            $paymentData['reference'] = $paymentData['transaction_reference'];
        }

        return $paymentData;
    }

    /**
     * Normalise interview score/result: the frontend may send `result` (pass|fail)
     * with an optional `score`. The workflow uses `score >= 70` to determine qualification.
     */
    public function normalizeInterviewAssessment(array $assessmentData): array
    {
        if (!isset($assessmentData['score']) || $assessmentData['score'] === '' || $assessmentData['score'] === null) {
            $resultFlag = strtolower((string) ($assessmentData['result'] ?? ''));
            $assessmentData['score'] = ($resultFlag === 'passed') ? 70 : 0;
        } else {
            $assessmentData['score'] = (int) $assessmentData['score'];
        }

        return $assessmentData;
    }

    // ========================================================================
    // INTERNAL HELPERS
    // ========================================================================

    private function attachQueueActions(array $records, array $ctx): array
    {
        foreach ($records as &$record) {
            $currentStage = $record['current_stage'] ?? null;
            $status = $record['status'] ?? null;
            if (($this->normalizeStageCode($currentStage) === 'director_confirmation' || $status === 'enrolled')
                && empty($record['director_confirmed_at'])
                && $this->canProcessAdmissionActionForStage('confirm_enrollment', 'director_confirmation', $ctx)
            ) {
                $record['available_actions'] = ['confirm-enrollment'];
                continue;
            }

            $record['available_actions'] = $this->getAvailableActions($currentStage, $status, $ctx);
        }
        unset($record);

        return $records;
    }

    private function admissionRoleCanProcessGroup(string $group, array $ctx): bool
    {
        $schoolAdminGroups = [
            'review_application',
            'upload_document',
            'verify_document',
            'check_class_space',
            'schedule_interview',
            'record_interview',
            'admit_student',
            'create_provisional_student',
            'record_payment',
            'generate_id_card',
            'final_approval',
            'complete_enrollment',
        ];

        if (in_array($group, $schoolAdminGroups, true) && $this->ctxHasRole([4], ['School Administrator'], $ctx)) {
            return true;
        }

        if ($group === 'verify_document' && $this->ctxHasRole([5, 6], ['Headteacher', 'Deputy Head - Academic'], $ctx)) {
            return true;
        }

        return false;
    }

    private function canBypassAdmissionStageRole(array $ctx): bool
    {
        return $this->ctxHasAnyPermission(['*'], $ctx);
    }

    private function canActViaStagePermissions(string $actionGroup, string $stageCode, array $ctx): bool
    {
        $permissionCandidates = self::PERMISSIONS[$actionGroup] ?? [];
        if (empty($permissionCandidates)) {
            return false;
        }

        return $this->getStageAuthorization()->canAct(
            $stageCode,
            $permissionCandidates,
            $this->ctxRoleIds($ctx),
            $this->ctxPermissionCodes($ctx)
        );
    }

    private function getStageAuthorization(): AdmissionStageAuthorization
    {
        if ($this->stageAuthorization === null) {
            $this->stageAuthorization = new AdmissionStageAuthorization($this->db, $this->getStudentAdmissionWorkflowId());
        }

        return $this->stageAuthorization;
    }

    private function getStudentAdmissionWorkflowId(): int
    {
        if ($this->resolvedWorkflowId) {
            return $this->workflowId;
        }

        $this->resolvedWorkflowId = true;
        $this->workflowId = 0;

        try {
            $stmt = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = 'student_admission' LIMIT 1");
            $stmt->execute();
            $this->workflowId = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            $this->workflowId = 0;
        }

        return $this->workflowId;
    }

    private function getWorkflowStageConfig(): array
    {
        if ($this->resolvedWorkflowStages) {
            return $this->workflowStageConfig;
        }

        $this->resolvedWorkflowStages = true;
        $this->workflowStageConfig = [];

        try {
            $rows = $this->db->query(
                "SELECT ws.code, ws.name, ws.required_role, ws.allowed_transitions, ws.sequence
                 FROM workflow_stages ws
                 JOIN workflow_definitions wd ON wd.id = ws.workflow_id
                 WHERE wd.code = 'student_admission'
                   AND ws.is_active = 1
                 ORDER BY ws.sequence ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $code = $this->normalizeStageCode($row['code'] ?? null);
                if (!$code) {
                    continue;
                }

                $allowedTransitions = [];
                if (!empty($row['allowed_transitions'])) {
                    $decoded = json_decode($row['allowed_transitions'], true);
                    if (is_array($decoded)) {
                        $allowedTransitions = array_values(array_filter(array_map([$this, 'normalizeStageCode'], $decoded)));
                    }
                }

                $this->workflowStageConfig[$code] = [
                    'code' => $code,
                    'name' => $row['name'] ?? null,
                    'required_role' => $row['required_role'] ?? null,
                    'allowed_transitions' => $allowedTransitions,
                    'sequence' => (int) ($row['sequence'] ?? 0),
                ];
            }
        } catch (Exception $e) {
            $this->workflowStageConfig = [];
        }

        return $this->workflowStageConfig;
    }

    private function getCurrentStageMetadata(?string $stageCode): array
    {
        $stageCode = $this->normalizeStageCode($stageCode);
        if (!$stageCode) {
            return [];
        }

        $config = $this->getWorkflowStageConfig();

        return $config[$stageCode] ?? [];
    }

    private function getAllowedTransitionsForStage(?string $stageCode): array
    {
        $meta = $this->getCurrentStageMetadata($stageCode);

        return $meta['allowed_transitions'] ?? [];
    }

    private function getStageRequiredRole(?string $stageCode): ?string
    {
        $meta = $this->getCurrentStageMetadata($stageCode);

        return $meta['required_role'] ?? null;
    }

    private function hasAdmissionRouteAccess(array $ctx): bool
    {
        $userId = $this->ctxUserId($ctx);
        $roleIds = $this->ctxRoleIds($ctx);

        if ($this->resolvedAdmissionRouteAccess && $this->admissionRouteAccessUserId === $userId) {
            return $this->admissionRouteAccess;
        }

        $this->resolvedAdmissionRouteAccess = true;
        $this->admissionRouteAccessUserId = $userId;
        $this->admissionRouteAccess = false;

        if ($userId <= 0 && empty($roleIds)) {
            return false;
        }

        try {
            $routeNames = self::ADMISSION_ROUTE_NAMES;
            $routePlaceholders = implode(',', array_fill(0, count($routeNames), '?'));

            if ($userId > 0) {
                $stmt = $this->db->prepare(
                    "SELECT ur.is_allowed
                     FROM user_routes ur
                     JOIN routes_registry r ON r.id = ur.route_id
                     WHERE ur.user_id = ?
                       AND r.name IN ({$routePlaceholders})
                       AND r.is_active = 1
                       AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                     ORDER BY ur.is_allowed DESC
                     LIMIT 1"
                );
                $stmt->execute(array_merge([$userId], $routeNames));
                $override = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($override) {
                    $this->admissionRouteAccess = (bool) ($override['is_allowed'] ?? false);

                    return $this->admissionRouteAccess;
                }
            }

            if (empty($roleIds)) {
                return false;
            }

            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT 1
                 FROM role_routes rr
                 JOIN routes_registry r ON r.id = rr.route_id
                 WHERE rr.is_allowed = 1
                   AND r.is_active = 1
                   AND r.name IN ({$routePlaceholders})
                   AND rr.role_id IN ({$placeholders})
                 LIMIT 1"
            );
            $params = array_merge($routeNames, array_map('intval', $roleIds));
            $stmt->execute($params);
            $this->admissionRouteAccess = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $this->admissionRouteAccess = false;
        }

        return $this->admissionRouteAccess;
    }

    private function getAdmissionsRouteRoleAliases(): array
    {
        if ($this->resolvedAdmissionsRouteRoleAliases) {
            return $this->admissionsRouteRoleAliases;
        }

        $this->resolvedAdmissionsRouteRoleAliases = true;
        $this->admissionsRouteRoleAliases = [];

        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT rl.name
                 FROM role_routes rr
                 JOIN routes_registry rt ON rt.id = rr.route_id
                 JOIN roles rl ON rl.id = rr.role_id
                 WHERE rr.is_allowed = 1
                   AND rt.is_active = 1
                   AND rt.name = ?"
            );
            $stmt->execute(['manage_students_admissions']);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $aliases = [];
            foreach ($rows as $row) {
                $alias = $this->normalizeRoleAlias($row['name'] ?? null);
                if ($alias) {
                    $aliases[] = $alias;
                }
            }

            $this->admissionsRouteRoleAliases = array_values(array_unique($aliases));
        } catch (Exception $e) {
            $this->admissionsRouteRoleAliases = [];
        }

        return $this->admissionsRouteRoleAliases;
    }

    private function userMatchesRequiredRole(?string $requiredRole, array $ctx): bool
    {
        if (!$requiredRole) {
            return true;
        }

        $required = $this->normalizeRoleAlias($requiredRole);
        if (!$required) {
            return true;
        }

        $roleNames = array_map([$this, 'normalizeRoleAlias'], $this->ctxRoleNames($ctx));
        foreach ($roleNames as $alias) {
            if ($alias !== null && $alias === $required) {
                return true;
            }
        }

        if ($required === 'parent') {
            return $this->getCurrentUserParentId($ctx) !== null;
        }

        if ($required === 'registrar') {
            $admissionsRoleAliases = $this->getAdmissionsRouteRoleAliases();
            if (empty($admissionsRoleAliases)) {
                return false;
            }

            foreach ($roleNames as $alias) {
                if (!$alias) {
                    continue;
                }
                if ($alias === 'headteacher' || $alias === 'headmaster' || $alias === 'headmistress') {
                    continue;
                }
                if (in_array($alias, $admissionsRoleAliases, true)) {
                    return true;
                }
            }

            return false;
        }

        if ($required === 'headteacher') {
            foreach ($roleNames as $alias) {
                if (in_array($alias, ['headteacher', 'headmaster', 'headmistress'], true)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function getCurrentUserParentId(array $ctx): ?int
    {
        $userId = $this->ctxUserId($ctx);
        if (array_key_exists($userId, $this->parentIdCache)) {
            return $this->parentIdCache[$userId];
        }

        $this->parentIdCache[$userId] = null;

        $email = strtolower(trim((string) ($ctx['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT p.id
                 FROM parents p
                 JOIN persons pp ON pp.id = p.person_id
                 WHERE LOWER(TRIM(COALESCE(pp.email, ''))) = ?
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            $this->parentIdCache[$userId] = $id ? (int) $id : null;
        } catch (Exception $e) {
            $this->parentIdCache[$userId] = null;
        }

        return $this->parentIdCache[$userId];
    }

    private function buildScopeFilter(string $applicationAlias, string $workflowAlias, array $ctx): string
    {
        if (
            $this->hasAnyAdmissionPermission('view_all', $ctx)
            || $this->ctxHasAnyPermission(['admission_view'], $ctx)
            || $this->hasAdmissionRouteAccess($ctx)
        ) {
            return '';
        }

        if (!$this->hasAnyAdmissionPermission('view_own', $ctx)) {
            return ' AND 1 = 0 ';
        }

        $userId = $this->ctxUserId($ctx);
        $parentScopeSql = $this->buildParentScopeSql($applicationAlias, $ctx);

        return " AND (
            CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_to')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_user_id')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.created_by')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.submitted_by')) AS UNSIGNED) = {$userId}
            OR {$workflowAlias}.started_by = {$userId}
            {$parentScopeSql}
        ) ";
    }

    private function buildParentScopeSql(string $applicationAlias, array $ctx): string
    {
        $parentId = $this->getCurrentUserParentId($ctx);
        if (!$parentId) {
            return '';
        }

        return " OR {$applicationAlias}.parent_id = {$parentId}";
    }

    private function isParentLinkedToApplication(int $applicationParentId, array $ctx): bool
    {
        if ($applicationParentId <= 0) {
            return false;
        }

        $parentId = $this->getCurrentUserParentId($ctx);
        if (!$parentId) {
            return false;
        }

        return $applicationParentId === $parentId;
    }

    private function isValidWorkflowTransition(int $applicationId, string $toStage): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT current_stage
                 FROM workflow_instances
                 WHERE reference_type = 'admission_application' AND reference_id = ?"
            );
            $stmt->execute([$applicationId]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$current) {
                return true;
            }

            $currentStage = $current['current_stage'];
            $validTransitions = self::VALID_TRANSITIONS;

            return in_array($toStage, $validTransitions[$currentStage] ?? [], true);
        } catch (Exception $e) {
            return false;
        }
    }

    private function normalizeStageCode(?string $stageCode): ?string
    {
        if ($stageCode === null) {
            return null;
        }

        $stageCode = strtolower(trim($stageCode));
        if ($stageCode === '') {
            return null;
        }

        if ($stageCode === 'application_submission') {
            return 'application_received';
        }

        $legacyMap = [
            'application' => 'application_review',
            'document_verification' => 'documents_verification',
            'class_capacity_check' => 'class_space_check',
            'interview_assessment' => 'interview_results',
            'placement_offer' => 'admission_decision',
            'fee_payment' => 'fees_payment',
            'enrollment_confirmation' => 'enrollment',
            'director_confirmation' => 'enrolled',
        ];

        if (isset($legacyMap[$stageCode])) {
            return $legacyMap[$stageCode];
        }

        return $stageCode;
    }

    private function inferStageFromApplication(array $application): ?string
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        switch ($status) {
            case 'submitted':
                return 'application_review';
            case 'documents_pending':
                return 'documents_verification';
            case 'documents_verified':
                return $this->policy->requiresInterview((string) ($application['grade_applying_for'] ?? ''))
                    ? 'interview_scheduling'
                    : 'admission_decision';
            case 'placement_offered':
            case 'fees_pending':
                return 'fees_payment';
            case 'enrolled':
                return 'enrolled';
            default:
                return null;
        }
    }

    private function normalizeRoleAlias(?string $roleName): ?string
    {
        if ($roleName === null) {
            return null;
        }

        $normalized = strtolower(trim($roleName));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function syncWorkflowIdentityData(array $workflowData, array $application): array
    {
        $workflowData['application_no'] = $application['application_no'] ?? ($workflowData['application_no'] ?? null);
        $workflowData['applicant_name'] = $application['applicant_name'] ?? ($workflowData['applicant_name'] ?? null);
        $workflowData['grade'] = $application['grade_applying_for'] ?? ($workflowData['grade'] ?? null);

        return $workflowData;
    }

    private function normalizeAdmissionDocuments(array $documents): array
    {
        return array_map(function (array $document): array {
            $fileUrl = $this->resolveAdmissionDocumentUrl($document);
            $document['file_url'] = $fileUrl;
            $document['download_url'] = $fileUrl;
            $document['display_name'] = $document['media_original_name']
                ?: basename((string) ($fileUrl ?: $document['document_path'] ?? ''));

            return $document;
        }, $documents);
    }

    private function resolveAdmissionDocumentUrl(array $document): ?string
    {
        $path = trim((string) ($document['document_path'] ?? ''));
        if ($path !== '' && !ctype_digit($path)) {
            return $path;
        }

        if (empty($document['media_filename']) || empty($document['media_context'])) {
            return $path !== '' ? $path : null;
        }

        return $this->managedMediaUrl(
            (string) $document['media_context'],
            $document['media_entity_id'] ?? null,
            (string) $document['media_filename'],
            $document['media_album_id'] ?? null
        );
    }

    private function buildAppUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . rtrim($appBase, '/') . '/' . ltrim($path, '/');
    }

    // ------------------------------------------------------------------------
    // User-context helpers
    // ------------------------------------------------------------------------

    private function ctxUserId(array $ctx): int
    {
        return (int) ($ctx['user_id'] ?? 0);
    }

    private function ctxRoleIds(array $ctx): array
    {
        $ids = $ctx['role_ids'] ?? [];

        return array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
    }

    private function ctxRoleNames(array $ctx): array
    {
        return array_values(array_filter($ctx['role_names'] ?? [], 'is_string'));
    }

    private function ctxPermissionCodes(array $ctx): array
    {
        return array_values(array_unique(array_filter($ctx['permission_codes'] ?? [])));
    }

    private function ctxHasRole(array $roleIds, array $roleNames, array $ctx): bool
    {
        $userRoleIds = $this->ctxRoleIds($ctx);
        foreach ($roleIds as $rid) {
            if (in_array((int) $rid, $userRoleIds, true)) {
                return true;
            }
        }

        $userRoleNames = array_map('strtolower', $this->ctxRoleNames($ctx));
        foreach ($roleNames as $rname) {
            if (in_array(strtolower($rname), $userRoleNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function ctxHasAnyPermission(array $permissionCodes, array $ctx): bool
    {
        $effective = array_merge($this->ctxPermissionCodes($ctx), $ctx['effective_permissions'] ?? []);

        foreach ($permissionCodes as $code) {
            if (in_array($code, $effective, true)) {
                return true;
            }
        }

        return in_array('*', $effective, true);
    }
}
