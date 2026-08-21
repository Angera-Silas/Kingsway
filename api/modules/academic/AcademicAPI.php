<?php
namespace App\API\Modules\academic;
use App\API\Modules\academic\ExaminationWorkflow;
use App\API\Modules\academic\StudentPromotionWorkflow;
use App\API\Modules\academic\AcademicAssessmentWorkflow;
use App\API\Modules\academic\ReportGenerationWorkflow;
use App\API\Modules\academic\LibraryManagementWorkflow;
use App\API\Modules\academic\CurriculumPlanningWorkflow;
use App\API\Modules\academic\AcademicYearTransitionWorkflow;

use App\API\Includes\BaseAPI;
use App\API\Services\CalendarSyncService;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use PDO;
use Exception;

class AcademicAPI extends BaseAPI
{
    private $examinationWorkflow;
    private $promotionWorkflow;
    private $assessmentWorkflow;
    private $reportWorkflow;
    private $libraryWorkflow;
    private $curriculumWorkflow;
    private $yearTransitionWorkflow;
    private $termTransitionService;

    private const STAFF_TYPE_TEACHING = 3;

    public function __construct()
    {
        parent::__construct('academic');

        // Initialize all workflows (each workflow now has its own constructor that sets workflow_code)
        $this->examinationWorkflow = new ExaminationWorkflow();
        $this->promotionWorkflow = new StudentPromotionWorkflow();
        $this->assessmentWorkflow = new AcademicAssessmentWorkflow();
        $this->reportWorkflow = new ReportGenerationWorkflow();
        $this->libraryWorkflow = new LibraryManagementWorkflow();
        $this->curriculumWorkflow = new CurriculumPlanningWorkflow();
        $this->yearTransitionWorkflow = new AcademicYearTransitionWorkflow();
        require_once __DIR__ . '/TermTransitionService.php';
        $this->termTransitionService = new TermTransitionService($this->db, $this->getCurrentUserId());
    }
    
    private function getCurrentStaffId(): ?int
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1");
        $stmt->execute([(int) $userId]);
        $staffId = $stmt->fetchColumn();

        return $staffId ? (int) $staffId : null;
    }

    // ========================================================================
    // WORKFLOW METHODS - Examination (11-Stage Workflow)
    // Maps to actual ExaminationWorkflow methods
    // ========================================================================

    // Stage 1: Planning
    public function startExaminationWorkflow($data)
    {
        return $this->examinationWorkflow->planExamination($data);
    }

    // Stage 2: Schedule Creation
    public function createExamSchedule($instanceId, $scheduleEntries)
    {
        return $this->examinationWorkflow->createSchedule($instanceId, $scheduleEntries);
    }

    // Stage 3: Question Paper Submission
    public function submitQuestionPaper($instanceId, $assessmentId, $file)
    {
        return $this->examinationWorkflow->submitQuestionPaper($instanceId, $assessmentId, $file);
    }

    // Stage 4: Exam Logistics Preparation
    public function prepareExamLogistics($instanceId, $logisticsData)
    {
        return $this->examinationWorkflow->prepareLogistics($instanceId, $logisticsData);
    }

    // Stage 5: Exam Administration/Conduct
    public function conductExamination($instanceId, $assessmentId, $conductData = [])
    {
        return $this->examinationWorkflow->conductExamination($instanceId, $assessmentId, $conductData);
    }

    // Stage 6: Marking Assignment
    public function assignExamMarking($instanceId, $assignments)
    {
        return $this->examinationWorkflow->assignMarking($instanceId, $assignments);
    }

    // Stage 7: Marks Recording
    public function recordExamMarks($instanceId, $assessmentId, $marksData)
    {
        return $this->examinationWorkflow->recordMarks($instanceId, $assessmentId, $marksData);
    }

    // Stage 8: Marks Verification
    public function verifyExamMarks($instanceId, $assessmentId, $verified = true, $corrections = [])
    {
        return $this->examinationWorkflow->verifyMarks($instanceId, $assessmentId, $verified, $corrections);
    }

    // Stage 9: Marks Moderation
    public function moderateExamMarks($instanceId, $moderationNotes = '', $applyScaling = false)
    {
        return $this->examinationWorkflow->moderateMarks($instanceId, $moderationNotes, $applyScaling);
    }

    // Stage 10: Results Compilation
    public function compileExamResults($instanceId)
    {
        return $this->examinationWorkflow->compileResults($instanceId);
    }

    // Stage 11: Results Approval
    public function approveExamResults($instanceId, $approved = true, $remarks = '')
    {
        return $this->examinationWorkflow->approveResults($instanceId, $approved, $remarks);
    }

    // Additional: Competency & Core Values Recording
    public function recordCompetencyEvidence($instanceId, $competencyId, $studentEntries, $evidenceDate, $notes = null)
    {
        return $this->examinationWorkflow->recordCompetencyEvidence($instanceId, $competencyId, $studentEntries, $evidenceDate, $notes);
    }

    public function recordCoreValueEvidence($instanceId, $valueId, $studentEntries)
    {
        return $this->examinationWorkflow->recordCoreValueEvidence($instanceId, $valueId, $studentEntries);
    }

    // Dashboard/Reporting
    public function getCompetencyDashboard($studentId, $termId = null, $academicYear = null)
    {
        if (empty($studentId)) {
            return [
                'status' => 'error',
                'message' => 'Missing required student_id',
                'code' => 400
            ];
        }
        return $this->examinationWorkflow->getCompetencyDashboard($studentId, $termId, $academicYear);
    }

    // ========================================================================
    // WORKFLOW METHODS - Student Promotion
    // ========================================================================

    public function startPromotionWorkflow($data)
    {
        return $this->promotionWorkflow->defineCriteria($data);
    }

    public function identifyPromotionCandidates($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->identifyCandidates($instanceId, $data);
    }

    public function validatePromotionEligibility($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->validateEligibility($instanceId, $data);
    }

    public function executePromotions($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->executePromotion($instanceId, $data);
    }

    public function generatePromotionReports($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->generateReports($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Academic Assessment
    // ========================================================================

    public function startAssessmentWorkflow($data)
    {
        // Use direct assessment record creation for production compatibility.
        // The workflow engine tables for academic assessment may be unavailable in some deployments.
        return $this->createAssessmentRecord($data);
    }

    public function createAssessmentItems($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->createItems($instanceId, $data);
    }

    public function administerAssessment($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->administerAssessment($instanceId, $data);
    }

    public function markAndGradeAssessment($instanceId, $data)
    {
        // Fallback: direct grading mode without workflow instance.
        if (empty($instanceId)) {
            if (!empty($data['assessment_id'])) {
                return $this->saveAssessmentResults([
                    'assessment_id' => $data['assessment_id'],
                    'marks' => $data['grading_data'] ?? $data['marks'] ?? $data,
                    'is_final' => (bool) ($data['is_final'] ?? true),
                    'marked_by' => $data['marked_by'] ?? null,
                ]);
            }
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id or assessment_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->markAndGrade($instanceId, $data);
    }

    public function analyzeAssessmentResults($instanceId, $data)
    {
        if (empty($instanceId)) {
            return $this->getResultsAnalysis($data);
        }
        return $this->assessmentWorkflow->analyzeResults($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Report Generation
    // ========================================================================

    public function startReportWorkflow($data)
    {
        $scope = is_array($data) ? $data : [];
        if (empty($scope['report_type'])) {
            $scope['report_type'] = 'end_of_term';
        }
        if (empty($scope['academic_year']) && !empty($scope['academic_year_id'])) {
            $scope['academic_year'] = $scope['academic_year_id'];
        }

        return successResponse([
            'instance_id' => null,
            'mode' => 'direct',
            'scope' => [
                'term_id' => $scope['term_id'] ?? null,
                'academic_year_id' => $scope['academic_year_id'] ?? $scope['academic_year'] ?? null,
                'class_id' => $scope['class_id'] ?? null,
                'student_ids' => $scope['student_ids'] ?? [],
                'report_type' => $scope['report_type'],
            ],
        ]);
    }

    public function compileReportData($instanceId, $data)
    {
        if (!empty($instanceId)) {
            $result = $this->reportWorkflow->compileData($instanceId, $data);
            if (!$this->isWorkflowUnavailableResult($result)) {
                return $result;
            }
        }

        $students = $this->resolveReportStudents($data);
        return successResponse([
            'instance_id' => $instanceId ?: null,
            'mode' => 'direct',
            'compiled_count' => count($students),
            'students' => $students,
        ]);
    }

    public function generateStudentReports($instanceId, $data)
    {
        if (!empty($instanceId)) {
            $result = $this->reportWorkflow->generateReports($instanceId, $data);
            if (!$this->isWorkflowUnavailableResult($result)) {
                return $result;
            }
        }

        $students = $this->resolveReportStudents($data);
        $generated = [];
        foreach ($students as $student) {
            $generated[] = [
                'student_id' => (int) $student['id'],
                'admission_no' => $student['admission_no'] ?? null,
                'student_name' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
                'class_name' => $student['class_name'] ?? null,
                'stream_name' => $student['stream_name'] ?? null,
                'overall_percentage' => isset($student['overall_percentage']) ? (float) $student['overall_percentage'] : null,
                'cbc_grade' => $this->deriveGradeFromPercentage($student['overall_percentage'] ?? null),
                'status' => 'generated',
            ];
        }

        return successResponse([
            'instance_id' => $instanceId ?: null,
            'mode' => 'direct',
            'generated_count' => count($generated),
            'reports' => $generated,
        ]);
    }

    public function reviewAndApproveReports($instanceId, $data)
    {
        if (empty($instanceId)) {
            throw new \InvalidArgumentException('instance_id is required.');
        }
        return $this->reportWorkflow->reviewAndApprove($instanceId, $data);
    }

    public function distributeReports($instanceId, $data)
    {
        if (empty($instanceId)) {
            throw new \InvalidArgumentException('instance_id is required.');
        }
        return $this->reportWorkflow->distributeReports($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Library Management
    // ========================================================================

    public function startLibraryWorkflow($data)
    {
        return $this->libraryWorkflow->acquisitionRequest($data);
    }

    public function reviewLibraryRequest($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->reviewAndApprove($instanceId, $data);
    }

    public function catalogLibraryResources($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->catalogResources($instanceId, $data);
    }

    public function distributeAndTrackResources($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->distributeAndTrack($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Curriculum Planning
    // ========================================================================

    public function startCurriculumWorkflow($data)
    {
        return $this->curriculumWorkflow->reviewFramework($data);
    }

    public function mapCurriculumOutcomes($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->mapOutcomes($instanceId, $data);
    }

    public function createCurriculumScheme($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->createScheme($instanceId, $data);
    }

    public function reviewAndApproveCurriculum($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->reviewAndApprove($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Academic Year Transition
    // ========================================================================

    public function startYearTransitionWorkflow($data)
    {
        return $this->yearTransitionWorkflow->prepareCalendar($data);
    }

    public function archiveAcademicData($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->archiveData($instanceId, $data);
    }

    public function executeYearPromotions($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->executePromotions($instanceId, $data);
    }

    public function getYearPromotionCandidates($instanceId)
    {
        if (empty($instanceId)) return ['status' => 'error', 'message' => 'Missing year transition instance_id', 'code' => 400];
        return $this->yearTransitionWorkflow->getPromotionCandidates((int) $instanceId);
    }

    public function assignYearPromotionStreams($instanceId, $data)
    {
        if (empty($instanceId)) return ['status' => 'error', 'message' => 'Missing year transition instance_id', 'code' => 400];
        return $this->yearTransitionWorkflow->assignPromotionStreams((int) $instanceId, $data['assignments'] ?? []);
    }

    public function completeYearTransitionStage($instanceId, $data)
    {
        if (empty($instanceId)) return ['status' => 'error', 'message' => 'Missing year transition instance_id', 'code' => 400];
        return $this->yearTransitionWorkflow->completeCanonicalStage(
            (int) $instanceId,
            (string) ($data['stage_code'] ?? ''),
            (string) ($data['notes'] ?? '')
        );
    }

    public function setupNewAcademicYear($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        $response = $this->yearTransitionWorkflow->setupNewYear($instanceId, $data);

        // Auto-seed the new year's class learning-area coverage so CBC
        // planning data is available immediately after class creation.
        if (isset($response['status'], $response['data']) && $response['status'] === 'success') {
            $yearId = (int) ($response['data']['academic_year_id'] ?? 0);

            if ($yearId <= 0) {
                $stmt = $this->db->prepare(
                    "SELECT data_json FROM workflow_instances WHERE id = ?"
                );
                $stmt->execute([$instanceId]);
                $instanceData = json_decode((string) $stmt->fetchColumn(), true) ?: [];
                $yearId = (int) ($instanceData['academic_year_id'] ?? 0);
            }

            if ($yearId > 0) {
                require_once __DIR__ . '/LearningAreaSetupService.php';
                $learningAreaService = new LearningAreaSetupService($this->db);
                $response['data']['learning_area_setup'] = $learningAreaService->seedForYear($yearId);
            }
        }

        return $response;
    }

    /**
     * Seed (or rebuild) the learning-area coverage for an academic year's classes.
     */
    public function seedAcademicLearningAreas($data)
    {
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId <= 0) {
            return ['status' => 'error', 'message' => 'academic_year_id is required', 'code' => 400];
        }

        require_once __DIR__ . '/LearningAreaSetupService.php';
        $learningAreaService = new LearningAreaSetupService($this->db);
        $summary = $learningAreaService->seedForYear($yearId);

        return ['status' => 'success', 'message' => 'Learning areas seeded for academic year', 'code' => 200, 'data' => $summary];
    }

    /**
     * Return the learning-area coverage for a specific class.
     */
    public function getClassLearningAreaCoverage($data)
    {
        $aycId = (int) ($data['academic_year_class_id'] ?? 0);
        if ($aycId <= 0) {
            return ['status' => 'error', 'message' => 'academic_year_class_id is required', 'code' => 400];
        }

        require_once __DIR__ . '/LearningAreaSetupService.php';
        $learningAreaService = new LearningAreaSetupService($this->db);
        $coverage = $learningAreaService->getClassCoverage($aycId);

        return ['status' => 'success', 'message' => 'Learning area coverage retrieved', 'code' => 200, 'data' => ['academic_year_class_id' => $aycId, 'learning_areas' => $coverage]];
    }

    public function migrateCompetencyBaselines($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->migrateBaselines($instanceId, $data);
    }

    public function validateYearReadiness($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->validateReadiness($instanceId, $data);
    }

    public function getTermTransitionContext($data = [])
    {
        return $this->termTransitionService->getContext(is_array($data) ? $data : []);
    }

    public function executeTermTransition($data)
    {
        if (!is_array($data)) {
            return [
                'status' => 'error',
                'message' => 'Invalid term transition payload',
                'code' => 400
            ];
        }
        return $this->termTransitionService->execute($data);
    }

    // ========================================================================
    // WORKFLOW STATUS AND MANAGEMENT
    // ========================================================================

    public function getWorkflowStatus($workflowType, $instanceId)
    {
        $workflow = null;
        switch ($workflowType) {
            case 'examination':
                $workflow = $this->examinationWorkflow;
                break;
            case 'promotion':
                $workflow = $this->promotionWorkflow;
                break;
            case 'assessment':
                $workflow = $this->assessmentWorkflow;
                break;
            case 'report':
                $workflow = $this->reportWorkflow;
                break;
            case 'library':
                $workflow = $this->libraryWorkflow;
                break;
            case 'curriculum':
                $workflow = $this->curriculumWorkflow;
                break;
            case 'year-transition':
                $workflow = $this->yearTransitionWorkflow;
                break;
            default:
                throw new Exception('Invalid workflow type');
        }

        return $workflow->getWorkflowInstance($instanceId);
    }

    // List all learning areas with pagination and search
    public function list($params = [])
    {
        try {
            [$search, $sort, $order] = $this->getSearchParams();

            $allowedSortColumns = ['id', 'name', 'code', 'created_at', 'updated_at'];
            $sort = in_array($sort, $allowedSortColumns, true) ? $sort : 'id';

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE name LIKE ? OR code LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get all results without pagination
            $sql = "SELECT * FROM learning_areas $where ORDER BY $sort $order";
            $stmt = $this->db->prepare($sql);

            if (!empty($bindings)) {
                $stmt->execute($bindings);
            } else {
                $stmt->execute();
            }

            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed learning areas');

            // Return just the data array - successResponse will wrap it properly
            return successResponse($subjects);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single learning area
    public function get($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                return errorResponse('Learning area not found');
            }

            $this->logAction('read', $id, "Retrieved learning area details: {$subject['name']}");

            return successResponse($subject);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new learning area
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['name', 'code'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "INSERT INTO learning_areas (name, code, description, status) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['code'],
                $data['description'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $subjectId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $subjectId, "Created new learning area: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area created successfully',
                'data' => ['id' => $subjectId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update learning area
    public function update($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if learning area exists
            $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Learning area not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['name', 'code', 'description', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE learning_areas SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated learning area details");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete learning area (soft delete)
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE learning_areas SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Learning area not found');
            }

            $this->logAction('delete', $id, "Deactivated learning area");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area deactivated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints - Redirect to new methods
    public function handleCustomGet($id, $action, $params)
    {
        try {
            $result = null;
            switch ($action) {
                case 'teachers':
                    // Use new method: getSubjectTeachers() instead of old getAssignedTeachers()
                    $result = $this->getSubjectTeachers($id);
                    break;
                case 'classes':
                    // Get classes where this subject is taught via timetable_entries
                    $result = $this->getSubjectClasses($id);
                    break;
                case 'assessments':
                    // Get assessments for this curriculum unit
                    $result = $this->getSubjectAssessments($id, $params);
                    break;
                case 'calendar-events':
                    $result = $this->getCalendarEvents($params);
                    break;
                case 'unified-events':
                    $result = $this->getUnifiedCalendarEvents($params);
                    break;
                case 'parent-meetings':
                    $result = $this->getParentMeetings($params);
                    break;
                default:
                    $result = errorResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
                    break;
            }
            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom POST endpoints - Redirect to workflows or new methods
    public function handleCustomPost($id, $action, $data)
    {
        switch ($action) {
            case 'assign-teacher':
                // Create a schedule entry instead of using phantom teacher_subjects table
                // This assigns a teacher to a subject for a specific class via timetable
                $data['subject_id'] = $id;
                if (empty($data['day_of_week']) || empty($data['start_time']) || empty($data['end_time'])) {
                    return errorResponse('To assign a teacher, you must create a timetable entry. Required: class_id, teacher_id, day_of_week, start_time, end_time');
                }
                return $this->createClassSchedule($data);

            case 'create-assessment':
                // Use the assessment workflow for proper assessment creation (Stage 1: Plan Assessment)
                $data['subject_id'] = $id;
                return $this->assessmentWorkflow->planAssessment($data);

            case 'create-calendar-event':
                return $this->createCalendarEvent($data);

            case 'schedule-meeting':
                return $this->scheduleParentMeeting($data);

            case 'cancel-meeting':
                return $this->cancelParentMeeting($data);

            default:
                return errorResponse('Invalid action');
        }
    }

    public function getCalendarEvents($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['academic_year_id'])) {
                $where[] = 'ayt.academic_year_id = ?';
                $bindings[] = (int) $params['academic_year_id'];
            }
            if (!empty($params['term_id'])) {
                $where[] = 'ayt.term_id = ?';
                $bindings[] = (int) $params['term_id'];
            }

            $sql = "
                SELECT
                    acd.id,
                    acd.title,
                    acd.title AS event_name,
                    acd.description,
                    acd.date,
                    acd.date AS start_date,
                    acd.date AS end_date,
                    COALESCE(cdt.code, 'school_day') AS day_type,
                    CASE
                        WHEN COALESCE(cdt.code, 'school_day') IN ('public_holiday', 'school_holiday', 'holiday') THEN 'holiday'
                        WHEN COALESCE(cdt.code, 'school_day') = 'exam_day' THEN 'exam'
                        WHEN COALESCE(cdt.code, 'school_day') = 'special_event' THEN 'event'
                        ELSE 'academic'
                    END AS type,
                    CASE
                        WHEN COALESCE(cdt.code, 'school_day') IN ('public_holiday', 'school_holiday', 'holiday') THEN 'holiday'
                        WHEN COALESCE(cdt.code, 'school_day') = 'exam_day' THEN 'exam'
                        WHEN COALESCE(cdt.code, 'school_day') = 'special_event' THEN 'event'
                        ELSE 'academic'
                    END AS event_type,
                    CASE
                        WHEN COALESCE(cdt.code, 'school_day') IN ('public_holiday', 'school_holiday', 'holiday') THEN 'holiday'
                        WHEN COALESCE(cdt.code, 'school_day') = 'exam_day' THEN 'exam'
                        ELSE 'academic'
                    END AS category,
                    ayt.academic_year_id,
                    ayt.term_id
                FROM academic_year_calendar_days acd
                LEFT JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id
                LEFT JOIN academic_year_calendar ac ON ac.id = acd.academic_year_calendar_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY acd.date ASC, acd.id ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            return successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Unified calendar events for the read-only calendar page (headteacher /
     * deputy). Returns merged logical events (one row per event, spanning its
     * full start -> end range), filterable by year/term/week/type/search, plus
     * the current-year context for building the filter controls.
     */
    public function getUnifiedCalendarEvents(array $params = [])
    {
        try {
            $calendarSync = new CalendarSyncService($this->db);
            $events = $calendarSync->getUnifiedEvents(false);

            $yearId = !empty($params['academic_year_id']) ? (int) $params['academic_year_id'] : null;
            $termId = !empty($params['term_id']) ? (int) $params['term_id'] : null;
            $weekNo = isset($params['week_number']) && $params['week_number'] !== '' ? (int) $params['week_number'] : null;
            $type = isset($params['type']) ? trim((string) $params['type']) : '';
            $search = isset($params['search']) ? trim((string) $params['search']) : '';
            $scope = isset($params['scope']) ? trim((string) $params['scope']) : 'current_term';

            $current = $this->currentCalendarContext();

            if ($termId === null && $yearId !== null) {
                $termIds = $this->termIdsForYear($yearId);
                if ($termIds) {
                    $events = array_values(array_filter($events, function ($ev) use ($termIds) {
                        return $ev['term_id'] !== null && in_array($ev['term_id'], $termIds, true);
                    }));
                }
            }

            if ($termId === null && $yearId === null && $scope === 'current_term' && $current['current_term_id'] !== null) {
                $termId = $current['current_term_id'];
            }

            if ($termId !== null) {
                $events = array_values(array_filter($events, function ($ev) use ($termId) {
                    return $ev['term_id'] !== null && (int) $ev['term_id'] === $termId;
                }));
            }

            if ($weekNo !== null) {
                $events = array_values(array_filter($events, function ($ev) use ($weekNo) {
                    return $ev['week_number'] !== null && (int) $ev['week_number'] === $weekNo;
                }));
            }

            if ($type !== '' && $type !== 'all') {
                $events = array_values(array_filter($events, function ($ev) use ($type) {
                    return ($ev['type'] ?? '') === $type;
                }));
            }

            if ($scope === 'upcoming') {
                $events = array_values(array_filter($events, function ($ev) use ($current) {
                    return ($ev['start_date'] ?? '') >= $current['today'];
                }));
            }

            if ($search !== '') {
                $needle = mb_strtolower($search);
                $events = array_values(array_filter($events, function ($ev) use ($needle) {
                    return mb_strpos(mb_strtolower($ev['title'] ?? ''), $needle) !== false
                        || mb_strpos(mb_strtolower($ev['description'] ?? ''), $needle) !== false;
                }));
            }

            return successResponse([
                'events' => $events,
                'total' => count($events),
                'context' => $current,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    /**
     * Current year / term context used to pre-fill calendar filters.
     */
    private function currentCalendarContext(): array
    {
        $context = [
            'today' => date('Y-m-d'),
            'year_id' => null,
            'year_name' => null,
            'current_term_id' => null,
            'current_term_name' => null,
            'terms' => [],
            'weeks' => [],
        ];

        $year = $this->db->prepare(
            "SELECT id, year_name, year_code FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
        );
        $year->execute();
        $yearRow = $year->fetch(PDO::FETCH_ASSOC);
        if (!$yearRow) {
            return $context;
        }
        $context['year_id'] = (int) $yearRow['id'];
        $context['year_name'] = $yearRow['year_name'] ?: $yearRow['year_code'];
        $termsStmt = $this->db->prepare(
            "SELECT ayt.id, t.name AS term_name
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY ayt.term_id ASC"
        );
        $termsStmt->execute([$context['year_id']]);
        $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($terms as $t) {
            $context['terms'][] = ['id' => (int) $t['id'], 'name' => $t['term_name']];
        }

        $cur = $this->db->prepare(
            "SELECT ayt.id, t.name AS term_name
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ? AND CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date
             ORDER BY ayt.term_id ASC LIMIT 1"
        );
        $cur->execute([$context['year_id']]);
        $curRow = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$curRow) {
            $cur = $this->db->prepare(
                "SELECT ayt.id, t.name AS term_name
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE ayt.academic_year_id = ? AND ayt.opening_date >= CURDATE()
                 ORDER BY ayt.opening_date ASC LIMIT 1"
            );
            $cur->execute([$context['year_id']]);
            $curRow = $cur->fetch(PDO::FETCH_ASSOC);
        }
        if ($curRow) {
            $context['current_term_id'] = (int) $curRow['id'];
            $context['current_term_name'] = $curRow['term_name'];
        }

        $weeksStmt = $this->db->prepare(
            "SELECT DISTINCT ac.week_number
             FROM academic_year_calendar ac
             JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY ac.week_number ASC"
        );
        $weeksStmt->execute([$context['year_id']]);
        $context['weeks'] = array_map('intval', $weeksStmt->fetchAll(PDO::FETCH_COLUMN));

        return $context;
    }

    private function termIdsForYear(int $yearId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ?");
        $stmt->execute([$yearId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function createCalendarEvent($data)
    {
        try {
            $title = trim((string) ($data['title'] ?? $data['event_name'] ?? ''));
            $date = $data['date'] ?? $data['start_date'] ?? null;
            if ($title === '' || empty($date)) {
                return errorResponse('title and date are required', 400);
            }

            $dayType = $data['day_type'] ?? $data['event_type'] ?? $data['type'] ?? 'special_event';
            if ($dayType === 'holiday') $dayType = 'school_holiday';
            if ($dayType === 'weekend') $dayType = 'holiday';
            $allowedTypes = ['school_day', 'half_day', 'exam_day', 'special_event', 'holiday', 'public_holiday', 'school_holiday'];
            if (!in_array($dayType, $allowedTypes, true)) {
                $dayType = 'special_event';
            }
            $dayTypeId = $this->queryScalar(
                "SELECT id FROM calendar_day_types WHERE code = ?",
                [$dayType]
            );
            if (!$dayTypeId) {
                $dayTypeId = (int) $this->db->query(
                    "SELECT id FROM calendar_day_types WHERE code = 'special_event'"
                )->fetchColumn();
            }

            $termId = !empty($data['term_id']) ? (int) $data['term_id'] : null;
            if (!$termId) {
                $termId = (int) $this->db->query(
                    "SELECT id FROM academic_year_terms WHERE status = 'current' ORDER BY id DESC LIMIT 1"
                )->fetchColumn();
            }
            if (!$termId) {
                return errorResponse('term_id is required to create a calendar event');
            }

            $calendarId = (int) $this->queryScalar(
                "SELECT ac.id FROM academic_year_calendar ac
                 WHERE ac.academic_year_term_id = ? AND ? BETWEEN ac.week_start AND ac.week_end
                 ORDER BY ac.week_number LIMIT 1",
                [$termId, $date]
            );
            if (!$calendarId) {
                $calendarId = (int) $this->queryScalar(
                    "SELECT ac.id FROM academic_year_calendar ac WHERE ac.academic_year_term_id = ? ORDER BY ac.week_number LIMIT 1",
                    [$termId]
                );
            }
            if (!$calendarId) {
                $term = $this->queryRow(
                    "SELECT opening_date, closing_date FROM academic_year_terms WHERE id = ?",
                    [$termId]
                );
                $this->runQuery(
                    "INSERT INTO academic_year_calendar (academic_year_term_id, week_number, week_start, week_end) VALUES (?, 1, ?, ?)",
                    [$termId, $term['opening_date'] ?: $date, $term['closing_date'] ?: $date]
                );
                $calendarId = (int) $this->db->lastInsertId();
            }

            $stmt = $this->db->prepare("
                INSERT INTO academic_year_calendar_days (
                    academic_year_calendar_id, date, calendar_day_type_id, title, description
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    calendar_day_type_id = VALUES(calendar_day_type_id),
                    title = VALUES(title),
                    description = VALUES(description)
            ");
            $stmt->execute([
                $calendarId,
                $date,
                $dayTypeId,
                $title,
                $data['description'] ?? null,
            ]);

            $dayId = (int) $this->queryScalar(
                "SELECT id FROM academic_year_calendar_days WHERE academic_year_calendar_id = ? AND date = ?",
                [$calendarId, $date]
            );

            require_once __DIR__ . '/../../services/CalendarSyncService.php';
            $sync = new CalendarSyncService($this->db);
            if ($dayId) {
                $sync->syncDay($dayId);
            } else {
                $sync->syncAcademicYear(null);
            }

            return successResponse([
                'id' => $dayId ?: $this->db->lastInsertId(),
                'calendar_day_id' => $dayId ?: null,
                'message' => 'Calendar event saved successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET handler: list parent meetings
     */
    private function getParentMeetings($params)
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['status'])) {
                $where[] = 'pm.status = ?';
                $bindings[] = $params['status'];
            }
            if (!empty($params['class_id'])) {
                $where[] = '1=1';
            }

            $sql = "
                SELECT
                    pm.id,
                    pm.title,
                    DATE(pm.start_at) AS meeting_date,
                    DATE(pm.start_at) AS date,
                    TIME(pm.start_at) AS start_time,
                    TIME(pm.start_at) AS time,
                    pm.location AS venue,
                    pm.description AS purpose,
                    pm.description,
                    pm.type,
                    CASE
                        WHEN pm.status = 'cancelled' THEN 'cancelled'
                        WHEN pm.status = 'past' THEN 'completed'
                        ELSE 'scheduled'
                    END AS status,
                    CAST(NULL AS UNSIGNED) AS attendance_count,
                    CAST(NULL AS UNSIGNED) AS class_id,
                    CAST(NULL AS UNSIGNED) AS parent_id,
                    CAST(NULL AS UNSIGNED) AS student_id,
                    pm.created_at,
                    pm.updated_at,
                    CAST(NULL AS CHAR) AS organizer,
                    CAST(NULL AS CHAR) AS class_name,
                    CAST(NULL AS CHAR) AS parent_name,
                    CAST(NULL AS CHAR) AS student_name
                FROM school_events pm
                WHERE pm.type IN ('parent_meeting', 'Meeting')
                  AND " . implode(' AND ', $where) . "
                ORDER BY pm.start_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => $meetings
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST handler: schedule a new parent meeting
     */
    private function scheduleParentMeeting($data)
    {
        try {
            $userId = $this->getCurrentUserId();
            $title = $data['title'] ?? $data['agenda'] ?? 'Untitled Meeting';
            $meetingDate = $data['meeting_date'] ?? $data['date'] ?? null;
            $startTime = $data['start_time'] ?? $data['time'] ?? null;
            $venue = $data['venue'] ?? $data['location'] ?? null;
            $classId = !empty($data['class_id']) ? (int) $data['class_id'] : null;
            $description = $data['description'] ?? null;
            $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
            $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
            $purpose = $data['purpose'] ?? $title;

            if (empty($meetingDate)) {
                return errorResponse('Meeting date is required');
            }
            if (empty($startTime)) {
                $startTime = '08:00:00';
            }
            $startAt = $meetingDate . ' ' . $startTime;

            $nextEventId = (int) $this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM school_events")->fetchColumn();
            $stmt = $this->db->prepare("
                INSERT INTO school_events
                    (id, title, description, type, location, start_at, end_at, status)
                VALUES (?, ?, ?, 'parent_meeting', ?, ?, NULL, 'upcoming')
            ");
            $stmt->execute([
                $nextEventId,
                $title,
                $description ?: $purpose,
                $venue,
                $startAt,
            ]);

            $meetingId = $nextEventId;
            $this->queueParentMeetingInvitations($meetingId, [
                'title' => $title,
                'meeting_date' => $meetingDate,
                'start_time' => $startTime,
                'venue' => $venue,
                'description' => $description ?: $purpose,
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'class_id' => $classId,
            ]);

            return successResponse([
                'status' => 'success',
                'message' => 'Meeting scheduled successfully',
                'id' => $meetingId,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function queueParentMeetingInvitations(int $meetingId, array $meeting): void
    {
        $eventService = new \App\API\Services\CommunicationBusinessEventService($this->db);
        $eventId = $eventService->getOrCreate('parent_event_invitation', (string) $meetingId, $meeting['meeting_date'] . ' ' . $meeting['start_time'], $this->getCurrentUserId());
        $eventService->linkSchoolEvent($eventId, $meetingId);
        $targets = [];
        if (!empty($meeting['student_id'])) {
            $targets[] = ['kind' => 'student', 'id' => (int) $meeting['student_id']];
        } elseif (!empty($meeting['parent_id'])) {
            $targets[] = ['kind' => 'parent', 'id' => (int) $meeting['parent_id']];
        } elseif (!empty($meeting['class_id'])) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT sae.student_id
                   FROM student_academic_enrollments sae
                   JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                   JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                  WHERE ayc.class_id = ? AND sae.status = 'active'"
            );
            $stmt->execute([(int) $meeting['class_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
                $targets[] = ['kind' => 'student', 'id' => (int) $studentId];
            }
        }
        if (!$targets) return;

        $date = date('D, d M Y', strtotime($meeting['meeting_date']));
        $eventVars = [
            'event_title' => $meeting['title'],
            'event_date' => $date,
            'event_time' => $meeting['start_time'],
            'event_venue' => $meeting['venue'] ?: 'School campus',
            'event_description' => $meeting['description'] ?: '',
        ];
        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        foreach ([7, 3, 1] as $daysBefore) {
            $when = date('Y-m-d H:i:s', strtotime($meeting['meeting_date'] . ' ' . $meeting['start_time'] . " -{$daysBefore} days"));
            if ($when < date('Y-m-d H:i:s')) $when = date('Y-m-d H:i:s');
            foreach ($targets as $target) {
                foreach (['sms', 'whatsapp', 'email'] as $channel) {
                    try {
                        $options = [
                            'scheduled_at' => $when,
                            'purpose' => 'parent_event',
                            'business_event_id' => $eventId,
                            'sender_id' => $this->getCurrentUserId() ?: 1,
                            'subject' => $meeting['title'],
                        ];
                        if ($target['kind'] === 'student') {
                            $platform->queueForStudentParents($target['id'], $channel, 'parent_event', $eventVars, $options);
                        } else {
                            $platform->queueForParent($target['id'], $channel, 'parent_event', $eventVars, $options);
                        }
                    } catch (Exception $e) {
                        error_log('[AcademicAPI] Parent meeting communication queue failed: ' . $e->getMessage());
                    }
                }
            }
        }
        $eventService->markProcessed($eventId);
    }

    /**
     * POST handler: cancel a parent meeting
     */
    private function cancelParentMeeting($data)
    {
        try {
            $meetingId = $data['meeting_id'] ?? $data['id'] ?? null;
            if (empty($meetingId)) {
                return errorResponse('Meeting ID is required');
            }

            $stmt = $this->db->prepare("UPDATE school_events SET status = 'cancelled' WHERE id = ? AND type IN ('parent_meeting', 'Meeting')");
            $stmt->execute([(int) $meetingId]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Meeting not found');
            }

            return successResponse([
                'status' => 'success',
                'message' => 'Meeting cancelled successfully',
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Helper method: Get classes where a subject is taught
    private function getSubjectClasses($subjectId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    c.id as class_id,
                    c.name as class_name,
                    c.grade_level,
                    COUNT(DISTINCT te.id) as schedule_count,
                    GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as teachers
                FROM timetable_entries te
                JOIN academic_year_class_streams aycs ON te.academic_year_class_stream_id = aycs.id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN staff ON te.teacher_id = staff.id
                LEFT JOIN persons p ON p.id = staff.person_id
                WHERE te.learning_area_id = ? AND te.status = 'scheduled'
                GROUP BY c.id
                ORDER BY c.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => $classes
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Helper method: Get assessments for a curriculum unit/subject
    private function getSubjectAssessments($subjectId, $params)
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = "WHERE a.learning_area_id = ?";
            $bindings = [$subjectId];

            if (!empty($params['term_id'])) {
                $where .= " AND a.academic_year_term_id = ?";
                $bindings[] = $params['term_id'];
            }

            if (!empty($params['class_id'])) {
                $where .= " AND a.academic_year_class_stream_id = ?";
                $bindings[] = $params['class_id'];
            }

            if (!empty($params['status'])) {
                $where .= " AND a.status = ?";
                $bindings[] = $params['status'];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM assessments a
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    a.*,
                    c.name as class_name,
                    la.name as subject_name,
                    t.name as term_name,
                    CONCAT(creator_p.first_name, ' ', creator_p.last_name) as created_by_name,
                    COUNT(ar.id) as total_submissions,
                    AVG(ar.marks_obtained) as average_marks
                FROM assessments a
                JOIN academic_year_class_streams aycs ON a.academic_year_class_stream_id = aycs.id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN learning_areas la ON a.learning_area_id = la.id
                JOIN academic_year_terms ayt ON a.academic_year_term_id = ayt.id
                JOIN terms t ON ayt.term_id = t.id
                JOIN staff creator ON a.assigned_by = creator.id
                JOIN persons creator_p ON creator_p.id = creator.person_id
                LEFT JOIN assessment_results ar ON a.id = ar.assessment_id
                $where
                GROUP BY a.id
                ORDER BY a.assessment_date DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => [
                    'assessments' => $assessments,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // DEPRECATED OLD HELPER METHODS - NOW USING WORKFLOWS AND NEW METHODS
    // These have been replaced by proper implementations using actual DB schema
    // ========================================================================
    // OLD: getAssignedTeachers() - NOW USE: getSubjectTeachers()
    // OLD: getAssignedClasses() - NOW USE: getSubjectClasses() 
    // OLD: getAssessments() - NOW USE: getSubjectAssessments()
    // OLD: assignTeacher() - NOW USE: createClassSchedule() with teacher assignment
    // OLD: createAssessment() - NOW USE: assessmentWorkflow->createAssessment()

    public function getLessonPlans($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            // Build WHERE clause
            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['teacher_id'])) {
                $where[] = "lp.teacher_id = ?";
                $bindings[] = $params['teacher_id'];
            }

            if (!empty($params['class_id'])) {
                $where[] = "ayc.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            if (!empty($params['stream_id'])) {
                $where[] = "EXISTS (SELECT 1 FROM academic_year_class_streams aycs WHERE aycs.academic_year_class_id = ayc.id AND aycs.stream_id = ?)";
                $bindings[] = $params['stream_id'];
            }

            if (!empty($params['learning_area_id'])) {
                $where[] = "lt.learning_area_id = ?";
                $bindings[] = $params['learning_area_id'];
            }

            if (!empty($params['status'])) {
                $where[] = "lp.status = ?";
                $bindings[] = $params['status'];
            }

            if (!empty($params['from_date'])) {
                $where[] = "aycd.date >= ?";
                $bindings[] = $params['from_date'];
            }

            if (!empty($params['to_date'])) {
                $where[] = "aycd.date <= ?";
                $bindings[] = $params['to_date'];
            }

            // Term and academic year filtering (via calendar day / class link)
            if (!empty($params['term_id'])) {
                $where[] = "ayt.id = ?";
                $bindings[] = $params['term_id'];
            }

            if (!empty($params['academic_year_id'])) {
                $where[] = "ayc.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            $whereClause = implode(' AND ', $where);

            $joins = "
                FROM lesson_plans lp
                JOIN lesson_templates lt ON lt.id = lp.lesson_template_id
                LEFT JOIN academic_year_class_learning_areas acla ON acla.id = lp.academic_year_class_learning_area_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = acla.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                LEFT JOIN academic_year_calendar aycal ON aycal.id = aycd.academic_year_calendar_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = aycal.academic_year_term_id
                LEFT JOIN staff s ON lp.teacher_id = s.id
                LEFT JOIN persons tp ON tp.id = s.person_id
                LEFT JOIN staff appr ON lp.approved_by = appr.id
                LEFT JOIN persons ap ON ap.id = appr.person_id
            ";

            // Get total count
            $countSql = "SELECT COUNT(*) $joins WHERE $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    lp.*,
                    lt.title AS title,
                    lt.title AS topic,
                    lt.learning_area_id,
                    la.name AS subject_name,
                    lt.strand_id AS unit_id,
                    lt.sub_strand_id AS topic_id,
                    lt.duration,
                    lt.activities AS content,
                    lt.activities AS activities,
                    lt.resources,
                    lt.assessment,
                    lt.homework,
                    ayc.class_id,
                    c.name AS class_name,
                    aycd.date AS lesson_date,
                    aycd.date AS date,
                    ayt.id AS term_id,
                    ayc.academic_year_id,
                    CONCAT(tp.first_name, ' ', tp.last_name) AS teacher_name,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS approved_by_name
                $joins
                LEFT JOIN learning_areas la ON la.id = lt.learning_area_id
                WHERE $whereClause
                ORDER BY aycd.date DESC, lp.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed lesson plans');

            return successResponse([
                'status' => 'success',
                'data' => [
                    'lesson_plans' => $plans,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonPlan($data)
    {
        try {
            $normalized = $data;
            $normalized['title'] = $data['title'] ?? $data['topic'] ?? null;
            $normalized['learning_area_id'] = $data['learning_area_id'] ?? $data['subject_id'] ?? null;
            $normalized['class_id'] = $data['class_id'] ?? null;
            $normalized['date'] = $data['date'] ?? $data['lesson_date'] ?? null;

            $required = ['title', 'learning_area_id', 'class_id', 'date'];
            $missing = $this->validateRequired($normalized, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $activities = $data['activities'] ?? $data['content'] ?? null;
            if (!empty($data['objectives'])) {
                $activities = $activities ? $data['objectives'] . "\n\n" . $activities : $data['objectives'];
            }

            $status = 'draft';
            if (($data['status'] ?? '') === 'approved') {
                $status = 'approved';
            }

            // Create the lesson template holding the lesson content
            $stmt = $this->db->prepare("
                INSERT INTO lesson_templates (
                    learning_area_id,
                    strand_id,
                    sub_strand_id,
                    title,
                    duration,
                    activities,
                    resources,
                    assessment,
                    homework,
                    created_by,
                    is_shared,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute([
                (int) $normalized['learning_area_id'],
                $data['strand_id'] ?? $data['unit_id'] ?? null,
                $data['sub_strand_id'] ?? $data['topic_id'] ?? null,
                $normalized['title'],
                $data['duration'] ?? 40,
                $activities,
                $data['resources'] ?? null,
                $data['assessment'] ?? null,
                $data['homework'] ?? null,
                $data['created_by'] ?? $this->getCurrentUserId(),
                $status === 'approved' ? 'approved' : 'draft'
            ]);

            $templateId = $this->db->lastInsertId();

            // Resolve the class-learning-area link for the current academic year
            $ayclaId = $this->resolveAyclaId((int) $normalized['class_id'], (int) $normalized['learning_area_id']);
            if (!$ayclaId) {
                $this->db->rollBack();
                return errorResponse('No active academic year class learning area found for the selected class and subject', 400);
            }

            // Resolve the calendar day for the lesson date
            $calendarDayId = $this->resolveCalendarDayId($normalized['date']);
            if (!$calendarDayId) {
                $this->db->rollBack();
                return errorResponse('The selected date is not part of the active academic year calendar', 400);
            }

            $approvedBy = null;
            if ($status === 'approved') {
                $approvedBy = $data['approved_by'] ?? $this->getCurrentStaffId();
            }

            $sql = "
                INSERT INTO lesson_plans (
                    lesson_template_id,
                    academic_year_class_learning_area_id,
                    academic_year_calendar_day_id,
                    teacher_id,
                    status,
                    approved_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $templateId,
                $ayclaId,
                $calendarDayId,
                $data['teacher_id'] ?? $this->getCurrentStaffId(),
                $status,
                $approvedBy
            ]);

            $planId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $planId, "Created lesson plan: {$normalized['title']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan created successfully',
                'data' => ['id' => $planId]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Resolve the academic_year_class_learning_areas row for a class + learning area
     * in the most recent active academic year.
     */
    private function resolveAyclaId($classId, $learningAreaId)
    {
        $stmt = $this->db->prepare("
            SELECT acla.id
            FROM academic_year_class_learning_areas acla
            JOIN academic_year_classes ayc ON ayc.id = acla.academic_year_class_id
            WHERE ayc.class_id = ? AND acla.learning_area_id = ? AND ayc.status = 'active'
            ORDER BY ayc.academic_year_id DESC
            LIMIT 1
        ");
        $stmt->execute([$classId, $learningAreaId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve the academic_year_calendar_days row for a given date.
     */
    private function resolveCalendarDayId($date)
    {
        $stmt = $this->db->prepare("
            SELECT acyd.id
            FROM academic_year_calendar_days acyd
            WHERE acyd.date = ?
            ORDER BY acyd.id DESC
            LIMIT 1
        ");
        $stmt->execute([$date]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function getCurriculumUnits($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            // Build WHERE clause
            $where = "WHERE st.status = 'active'";
            $bindings = [];

            if (!empty($search)) {
                $where .= " AND (st.name LIKE ? OR la.name LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Filter by learning area if specified
            if (!empty($params['learning_area_id'])) {
                $where .= " AND st.learning_area_id = ?";
                $bindings[] = $params['learning_area_id'];
            }

            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT st.id)
                FROM strands st
                JOIN learning_areas la ON st.learning_area_id = la.id
                $where
            ";
            $stmt = $this->db->prepare($countSql);
            if (!empty($bindings)) {
                $stmt->execute($bindings);
            } else {
                $stmt->execute();
            }
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    st.*,
                    la.name as learning_area_name,
                    la.code as learning_area_code,
                    COUNT(DISTINCT sst.id) as topic_count
                FROM strands st
                JOIN learning_areas la ON st.learning_area_id = la.id
                LEFT JOIN sub_strands sst ON st.id = sst.strand_id AND sst.status = 'active'
                $where
                GROUP BY st.id
                ORDER BY st.sort_order ASC, st.name ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            if (!empty($bindings)) {
                $stmt->execute(array_merge($bindings, [$limit, $offset]));
            } else {
                $stmt->execute([$limit, $offset]);
            }
            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed curriculum units');

            return successResponse([
                'status' => 'success',
                'data' => [
                    'units' => $units,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createCurriculumUnit($data)
    {
        try {
            $required = ['learning_area_id', 'name'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get next sort_order if not provided
            if (!isset($data['order_sequence']) && !isset($data['sort_order'])) {
                $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM strands WHERE learning_area_id = ?");
                $stmt->execute([$data['learning_area_id']]);
                $data['order_sequence'] = $stmt->fetchColumn();
            }
            $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) ($data['order_sequence'] ?? 1);

            $sql = "
                INSERT INTO strands (
                    learning_area_id,
                    grade_level,
                    code,
                    name,
                    description,
                    sort_order,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['grade_level'] ?? '',
                $data['code'] ?? null,
                $data['name'],
                $data['description'] ?? null,
                $sortOrder,
                $data['status'] ?? 'active'
            ]);

            $unitId = $this->db->lastInsertId();

            // Add topics if provided
            if (!empty($data['topics'])) {
                $sql = "
                    INSERT INTO sub_strands (
                        strand_id,
                        grade_level,
                        code,
                        name,
                        description,
                        sort_order,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['topics'] as $index => $topic) {
                    $stmt->execute([
                        $unitId,
                        $data['grade_level'] ?? '',
                        $topic['code'] ?? null,
                        $topic['name'],
                        $topic['description'] ?? null,
                        $topic['order_sequence'] ?? ($index + 1),
                        $topic['status'] ?? 'active'
                    ]);
                }
            }

            $this->db->commit();
            $this->logAction('create', $unitId, "Created curriculum unit: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit created successfully',
                'data' => ['id' => $unitId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getAcademicTerms($params = [])
    {
        try {
            // Get academic terms with related information
            $sql = "
                SELECT 
                    ayt.id,
                    ayt.academic_year_id AS year,
                    ayt.term_id,
                    t.name AS name,
                    t.code AS term_number,
                    ayt.opening_date AS start_date,
                    ayt.closing_date AS end_date,
                    ayt.opening_date,
                    ayt.half_term_start,
                    ayt.half_term_end,
                    ayt.closing_date,
                    ayt.status,
                    ay.year_name,
                    ay.year_code,
                    COUNT(DISTINCT ayc.id) as active_classes,
                    (SELECT COUNT(*) FROM academic_year_calendar c
                     WHERE c.academic_year_term_id = ayt.id) AS weeks
                FROM academic_year_terms ayt
                JOIN terms t ON t.id = ayt.term_id
                LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                LEFT JOIN academic_year_classes ayc ON ayc.academic_year_id = ayt.academic_year_id AND ayc.status = 'active'
                GROUP BY ayt.id, t.id, ay.id
                ORDER BY ayt.opening_date DESC, t.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($terms);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Alias for getAcademicTerms - matches frontend API route /academic/terms-list
     */
    public function getTermsList($params = [])
    {
        return $this->getAcademicTerms($params);
    }

    /**
     * List active school levels - matches /academic/levels-list
     */
    public function getLevelsList($params = [])
    {
        try {
            $sql = "
                SELECT id, name, code, description, status
                FROM school_levels
                WHERE status = 'active'
                ORDER BY id ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($levels);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createAcademicTerm($data)
    {
        try {
            $required = ['name', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $termNumber = isset($data['term_number']) ? (int) $data['term_number'] : 1;
            $termCode = 'T' . $termNumber;

            // Resolve (or create) the terms master row; academic_year_terms holds the year instance.
            $termId = $this->db->prepare('SELECT id FROM terms WHERE code = ?');
            $termId->execute([$termCode]);
            $masterTermId = $termId->fetchColumn();
            if (!$masterTermId) {
                $this->db->prepare('INSERT INTO terms (name, code) VALUES (?, ?)')
                    ->execute([$data['name'] ?: ('Term ' . $termNumber), $termCode]);
                $masterTermId = (int) $this->db->lastInsertId();
            }

            if (!empty($data['academic_year_id'])) {
                $yearId = (int) $data['academic_year_id'];
            } else {
                $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
            }
            if (!$yearId) {
                return errorResponse('academic_year_id is required to create an academic term');
            }

            $hasUpdatedAt = $this->columnExists('academic_year_terms', 'updated_at');

            $insertCols = 'academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status';
            if ($hasUpdatedAt) {
                $insertCols .= ', updated_at';
            }

            $stmt = $this->db->prepare("
                INSERT INTO academic_year_terms (
                    $insertCols
                ) VALUES (?, ?, ?, ?, ?, ?, 'upcoming'" . ($hasUpdatedAt ? ', CURRENT_TIMESTAMP' : '') . ")
                ON DUPLICATE KEY UPDATE
                    opening_date = VALUES(opening_date),
                    half_term_start = VALUES(half_term_start),
                    half_term_end = VALUES(half_term_end),
                    closing_date = VALUES(closing_date),
                    status = VALUES(status)" . ($hasUpdatedAt ? ", updated_at = VALUES(updated_at)" : "") . "
            ");
            $stmt->execute([
                $yearId,
                $masterTermId,
                $data['start_date'],
                $data['half_term_start'] ?? null,
                $data['half_term_end'] ?? null,
                $data['end_date'],
            ]);

            $termId = (int) $this->db->lastInsertId();
            if (!$termId) {
                $termId = (int) $this->queryScalar(
                    "SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND term_id = ?",
                    [$yearId, $masterTermId]
                );
            }

            // Set updated_at if column exists
            if ($this->columnExists('academic_year_terms', 'updated_at')) {
                $stmt = $this->db->prepare("UPDATE academic_year_terms SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$termId]);
            }

            // Give the new term its calendar week grid (derived from dates).
            require_once __DIR__ . '/AcademicCalendarService.php';
            $calendarService = new AcademicCalendarService($this->db);
            $calendarService->generateYearCalendar($yearId);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic term created successfully',
                'data' => ['id' => $termId]
            ],201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== ACADEMIC YEARS MANAGEMENT ====================

    public function getAcademicYears($params = [])
    {
        try {
            require_once __DIR__ . '/AcademicYearManager.php';
            $yearManager = new AcademicYearManager($this->db);

            $years = $yearManager->getAllYears($params ?? []);

            return successResponse($years);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Alias for getAcademicYears - matches frontend API route /academic/years/list
     */
    public function getYearsList($params = [])
    {
        return $this->getAcademicYears($params);
    }

    public function getAcademicYear($id)
    {
        try {
            if (!$id) {
                return errorResponse('Academic year ID is required', 400);
            }

            require_once __DIR__ . '/AcademicYearManager.php';
            $yearManager = new AcademicYearManager($this->db);
            $year = $yearManager->getAcademicYear((int) $id);

            if (!$year) {
                return errorResponse('Academic year not found', 404);
            }

            return successResponse($year);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getCurrentAcademicYear($params = [])
    {
        try {
            require_once __DIR__ . '/AcademicYearManager.php';
            $yearManager = new AcademicYearManager($this->db);

            $year = $yearManager->getCurrentAcademicYear();

            if (!$year) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'No current academic year found'
                ], 404);
            }

            return successResponse($year);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createAcademicYear($data)
    {
        try {
            require_once __DIR__ . '/AcademicYearManager.php';
            $yearManager = new AcademicYearManager($this->db);

            $year = $yearManager->createAcademicYear($data);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic year created successfully',
                'data' => $year
            ], 201);
        } catch (\InvalidArgumentException $e) {
            error_log('[AcademicAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateAcademicYear($yearId, $data)
    {
        try {
            if (!$yearId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Academic year ID is required'
                ], 400);
            }

            // Keep both labels canonical and derive them from the opening
            // year whenever the opening date is supplied or changed.
            $startDate = $data['start_date'] ?? null;
            if (!$startDate) {
                $yearStmt = $this->db->prepare('SELECT start_date FROM academic_years WHERE id = ? LIMIT 1');
                $yearStmt->execute([(int) $yearId]);
                $startDate = $yearStmt->fetchColumn();
            }
            if ($startDate) {
                $startYear = (int) date('Y', strtotime($startDate));
                $canonicalYear = $startYear . '/' . ($startYear + 1);
                $data['year_code'] = $canonicalYear;
                $data['year_name'] = $canonicalYear;
            }

            $sql = "UPDATE academic_years SET ";
            $fields = [];
            $values = [];

            $allowedFields = [
                'year_code',
                'year_name',
                'start_date',
                'end_date',
                'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'No fields to update'
                ], 400);
            }

            $sql .= implode(', ', $fields);
            $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $values[] = $yearId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic year updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteAcademicYear($yearId)
    {
        try {
            if (!$yearId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Academic year ID is required'
                ], 400);
            }

            // Check if year is current
            $stmt = $this->db->prepare("SELECT is_current FROM academic_years WHERE id = ?");
            $stmt->execute([$yearId]);
            $year = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($year && $year['is_current']) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Cannot delete current academic year'
                ], 400);
            }

            $stmt = $this->db->prepare("DELETE FROM academic_years WHERE id = ?");
            $stmt->execute([$yearId]);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic year deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function setCurrentAcademicYear($yearId)
    {
        try {
            if (!$yearId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Academic year ID is required'
                ], 400);
            }

            require_once __DIR__ . '/AcademicYearManager.php';
            $yearManager = new AcademicYearManager($this->db);

            $yearManager->setCurrentYear($yearId);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic year set as current successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== ACADEMIC CALENDAR MANAGEMENT ====================

    /**
     * Generate (or regenerate) the term calendar for an academic year.
     *
     * Body: { year_id, week_counts: {1: 14, 2: 14, 3: 10} } - week_counts optional.
     */
    public function generateAcademicCalendar($yearId, $weekCounts = [])
    {
        try {
            require_once __DIR__ . '/AcademicCalendarService.php';
            $calendarService = new AcademicCalendarService($this->db);
            $result = $calendarService->generateYearCalendar((int) $yearId, is_array($weekCounts) ? $weekCounts : []);

            require_once __DIR__ . '/../../services/CalendarSyncService.php';
            $sync = new CalendarSyncService($this->db);
            $sync->syncAcademicYear((int) $yearId);

            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get the generated calendar summary for an academic year.
     */
    public function getAcademicCalendar($yearId)
    {
        try {
            require_once __DIR__ . '/AcademicCalendarService.php';
            $calendarService = new AcademicCalendarService($this->db);
            $calendar = $calendarService->getCalendar((int) $yearId);
            return successResponse($calendar);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Readiness check for the academic calendar of a year.
     */
    public function validateAcademicCalendar($yearId)
    {
        try {
            require_once __DIR__ . '/AcademicCalendarService.php';
            $calendarService = new AcademicCalendarService($this->db);
            return successResponse($calendarService->validateCalendar((int) $yearId));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== ACADEMIC TERMS MANAGEMENT ====================

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function updateAcademicTerm($termId, $data)
    {
        try {
            if (!$termId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Academic term ID is required'
                ], 400);
            }

            $aytSets = [];
            $aytValues = [];
            $termFields = ['start_date' => 'opening_date', 'end_date' => 'closing_date', 'status' => 'status'];
            $termFields += ['half_term_start' => 'half_term_start', 'half_term_end' => 'half_term_end'];

            foreach ($termFields as $from => $to) {
                // array_key_exists (not isset) so an explicit null/empty value
                // clears the column - e.g. dropping half-term entirely.
                if (array_key_exists($from, $data)) {
                    $aytSets[] = "$to = ?";
                    $aytValues[] = ($data[$from] !== null && $data[$from] !== '') ? $data[$from] : null;
                }
            }

            if (!empty($data['name'])) {
                $stmt = $this->db->prepare(
                    "UPDATE terms t JOIN academic_year_terms ayt ON ayt.term_id = t.id SET t.name = ? WHERE ayt.id = ?"
                );
                $stmt->execute([$data['name'], $termId]);
            }

            if (empty($aytSets)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'No fields to update'
                ], 400);
            }

            $sql = "UPDATE academic_year_terms SET " . implode(', ', $aytSets);
            
            // Add updated_at only if column exists
            if ($this->columnExists('academic_year_terms', 'updated_at')) {
                $sql .= ", updated_at = CURRENT_TIMESTAMP";
            }
            
            $sql .= " WHERE id = ?";
            $aytValues[] = $termId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($aytValues);

            // Changing term dates changes the school calendar - regenerate the
            // weeks/days for the owning academic year automatically.
            $yearId = (int) $this->queryScalar(
                "SELECT academic_year_id FROM academic_year_terms WHERE id = ?",
                [$termId]
            );
            if ($yearId > 0) {
                require_once __DIR__ . '/AcademicCalendarService.php';
                $calendarService = new AcademicCalendarService($this->db);
                $calendarService->generateYearCalendar($yearId);

                require_once __DIR__ . '/../../services/CalendarSyncService.php';
                $sync = new CalendarSyncService($this->db);
                $sync->syncAcademicYear($yearId);
            }

            return successResponse([
                'status' => 'success',
                'message' => 'Academic term updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteAcademicTerm($termId)
    {
        try {
            if (!$termId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Academic term ID is required'
                ], 400);
            }

            // Check if term is current
            $stmt = $this->db->prepare("SELECT status FROM academic_year_terms WHERE id = ?");
            $stmt->execute([$termId]);
            $term = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($term && $term['status'] === 'current') {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Cannot delete current academic term'
                ], 400);
            }

            $stmt = $this->db->prepare("DELETE FROM academic_year_terms WHERE id = ?");
            $stmt->execute([$termId]);

            return successResponse([
                'status' => 'success',
                'message' => 'Academic term deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchemeOfWork($params = [])
    {
        try {
            $id = is_scalar($params) ? (int) $params : (int) ($params['id'] ?? 0);
            $filters = is_array($params) ? $params : [];
            $where = ['1=1'];
            $bindings = [];

            if ($id > 0) {
                $where[] = 'sw.id = ?';
                $bindings[] = $id;
            }
            if (!empty($filters['class_id'])) {
                $where[] = 'ayc.class_id = ?';
                $bindings[] = (int) $filters['class_id'];
            }
            if (!empty($filters['subject_id'])) {
                $where[] = 'st.learning_area_id = ?';
                $bindings[] = (int) $filters['subject_id'];
            }
            if (!empty($filters['term_id'])) {
                $where[] = 'ayt.id = ?';
                $bindings[] = (int) $filters['term_id'];
            } elseif (!empty($filters['term'])) {
                $where[] = 'SUBSTRING(t.code, 2) = ?';
                $bindings[] = (string) (int) $filters['term'];
            }
            if (!empty($filters['academic_year_id'])) {
                $where[] = 'ayc.academic_year_id = ?';
                $bindings[] = (int) $filters['academic_year_id'];
            }
            if (!empty($filters['status'])) {
                $where[] = 'sw.status = ?';
                $bindings[] = $filters['status'];
            }

            $sql = "
                SELECT 
                    sw.id,
                    st.id as scheme_template_id,
                    st.learning_area_id,
                    st.learning_area_id as subject_id,
                    la.name as subject_name,
                    la.name as learning_area_name,
                    ayc.class_id as class_id,
                    c.name as class_name,
                    sw.teacher_id,
                    CONCAT(sp.first_name, ' ', sp.last_name) as teacher_name,
                    ayt.id as term_id,
                    SUBSTRING(t.code, 2) as term_number,
                    ac.week_number,
                    ac.week_number as topic_count,
                    st.title,
                    st.activities,
                    st.resources,
                    st.assessment_methods,
                    sw.status,
                    sw.approved_by,
                    ayc.academic_year_id
                FROM schemes_of_work sw
                JOIN scheme_templates st ON st.id = sw.scheme_template_id
                LEFT JOIN learning_areas la ON la.id = st.learning_area_id
                LEFT JOIN academic_year_class_learning_areas aycla ON aycla.id = sw.academic_year_class_learning_area_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_calendar ac ON ac.id = sw.academic_year_calendar_week_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                LEFT JOIN terms t ON t.id = ayt.term_id
                LEFT JOIN staff s ON s.id = sw.teacher_id
                LEFT JOIN persons sp ON sp.id = s.person_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY ayt.id, ac.week_number, sw.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($id > 0) {
                return !empty($schemes)
                    ? successResponse($schemes[0])
                    : errorResponse('Scheme of work not found', 404);
            }

            return successResponse([
                'schemes' => $schemes,
                'summary' => [
                    'total' => count($schemes),
                    'approved' => count(array_filter($schemes, fn($s) => ($s['status'] ?? '') === 'approved')),
                    'pending' => count(array_filter($schemes, fn($s) => in_array(($s['status'] ?? ''), ['draft', 'submitted'], true))),
                    'overdue' => 0,
                ],
                'pagination' => [
                    'page' => 1,
                    'limit' => count($schemes),
                    'total' => count($schemes),
                    'total_pages' => count($schemes) > 0 ? 1 : 0,
                ],
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSchemeOfWork($data)
    {
        try {
            $data = $this->normalizeSchemeOfWorkPayload($data);
            $required = ['learning_area_id', 'class_id', 'teacher_id', 'term_id', 'week_number', 'title'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $templateId = $this->ensureSchemeTemplate($data);

            $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : $this->resolveCurrentAcademicYearId();
            $ayClassId = $this->resolveAcademicYearClassId($data['class_id'], $academicYearId);
            $ayclaId = $ayClassId > 0 ? $this->resolveClassLearningAreaId($ayClassId, $data['learning_area_id']) : null;
            $weekId = $this->resolveCalendarWeekId($data['term_id'], $data['week_number']);

            $sql = "
                INSERT INTO schemes_of_work (
                    scheme_template_id,
                    academic_year_class_learning_area_id,
                    academic_year_calendar_week_id,
                    teacher_id,
                    status
                ) VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $templateId,
                $ayclaId,
                $weekId,
                $data['teacher_id'],
                $this->normalizeSchemeStatus($data['status'] ?? 'draft'),
            ]);

            $schemeId = $this->db->lastInsertId();

            return successResponse([
                'status' => 'success',
                'message' => 'Scheme of work created successfully',
                'data' => ['id' => $schemeId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function generateSchemeOfWork($data)
    {
        try {
            $learningAreaId = (int)($data['learning_area_id'] ?? 0);
            $classId = (int)($data['class_id'] ?? 0);

            if (!$learningAreaId || !$classId) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'learning_area_id and class_id are required'
                ], 400);
            }

            $areaStmt = $this->db->prepare("SELECT id, name FROM learning_areas WHERE id = ? AND status = 'active'");
            $areaStmt->execute([(int) $learningAreaId]);
            $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
            if (!$area) {
                return errorResponse('The selected learning area does not exist or is inactive', 400);
            }

            // Resolve the class grade so strands are scoped to the KICD grade level
            $classStmt = $this->db->prepare("SELECT grade_level FROM classes WHERE id = ?");
            $classStmt->execute([(int) $classId]);
            $gradeLevel = (string) ($classStmt->fetchColumn() ?: '');

            $teacherId = null;
            $userId = $data['created_by'] ?? $this->getCurrentUserId();
            if ($userId) {
                $staffStmt = $this->db->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1");
                $staffStmt->execute([(int) $userId]);
                $teacherId = (int) ($staffStmt->fetchColumn() ?: 0);
                if (!$teacherId) {
                    $staffStmt = $this->db->prepare("SELECT id FROM staff WHERE id = ? LIMIT 1");
                    $staffStmt->execute([(int) $userId]);
                    $teacherId = (int) ($staffStmt->fetchColumn() ?: 0);
                }
            }

            // Resolve term
            $termId = (int)($data['term_id'] ?? 0);
            $termNumber = isset($data['term_number']) ? (int) $data['term_number'] : null;
            $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;

            if (!$termId && $termNumber) {
                $termId = $this->resolveAcademicYearTermId($academicYearId, $termNumber);
            }
            if (!$termId) {
                $stmt = $this->db->prepare("
                    SELECT ayt.id, SUBSTRING(t.code, 2) AS term_number, ayt.academic_year_id
                    FROM academic_year_terms ayt
                    JOIN terms t ON t.id = ayt.term_id
                    WHERE ayt.status = 'current'
                    ORDER BY ayt.id DESC LIMIT 1
                ");
                $stmt->execute();
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($current) {
                    $termId = (int) $current['id'];
                    $termNumber = (int) $current['term_number'];
                    if (!$academicYearId) $academicYearId = (int) $current['academic_year_id'];
                }
            }
            if (!$termId) {
                return errorResponse('A valid term is required to generate a scheme of work', 400);
            }

            // Load strands (optionally filtered), sub-strands and outcomes
            $strandIds = [];
            if (!empty($data['strand_id'])) {
                $strandIds = array_map('intval', (array) $data['strand_id']);
            } elseif (!empty($data['sub_strand_ids'])) {
                $subStrandIds = array_map('intval', (array) $data['sub_strand_ids']);
                if ($subStrandIds) {
                    $in = implode(',', array_fill(0, count($subStrandIds), '?'));
                    $stmt = $this->db->prepare("SELECT DISTINCT strand_id FROM sub_strands WHERE id IN ({$in}) AND status='active'");
                    $stmt->execute($subStrandIds);
                    $strandIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                }
            }

            $strandSql = "SELECT s.id, s.name FROM strands s WHERE s.learning_area_id = ? AND s.status = 'active'";            $strandParams = [(int) $learningAreaId];
            if ($gradeLevel !== '') {
                $strandSql .= " AND s.grade_level = ?";
                $strandParams[] = $gradeLevel;
            }
            if ($strandIds) {
                $in = implode(',', array_fill(0, count($strandIds), '?'));
                $strandSql .= " AND s.id IN ({$in})";
                $strandParams = array_merge($strandParams, $strandIds);
            }
            $strandSql .= " ORDER BY s.sort_order, s.id";
            $stmt = $this->db->prepare($strandSql);
            $stmt->execute($strandParams);
            $strands = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$strands) {
                return errorResponse('No active strands found for the selected learning area', 404);
            }

            // Resolve class context once for the year instance rows
            if (!$academicYearId) {
                $academicYearId = $this->resolveCurrentAcademicYearId();
            }
            $ayClassId = $this->resolveAcademicYearClassId($classId, $academicYearId);
            $ayclaId = $ayClassId > 0 ? $this->resolveClassLearningAreaId($ayClassId, $learningAreaId) : null;

            // Existing top week number for this term to continue numbering
            $weekStmt = $this->db->prepare(
                "SELECT COALESCE(MAX(week_number), 0) + 1 FROM academic_year_calendar
                 WHERE academic_year_term_id = ?"
            );
            $weekStmt->execute([(int) $termId]);
            $startWeek = (int) ($weekStmt->fetchColumn() ?: 1);
            if ($startWeek < 1) $startWeek = 1;

            $created = [];
            $inserted = 0;
            $skipped = 0;
            $this->db->beginTransaction();

            foreach ($strands as $strand) {
                $subSql = "SELECT id, name, description, variant FROM sub_strands WHERE strand_id = ? AND status = 'active'";
                $subParams = [(int) $strand['id']];
                if (!empty($data['sub_strand_ids'])) {
                    $subStrandIds = array_map('intval', (array) $data['sub_strand_ids']);
                    if ($subStrandIds) {
                        $in = implode(',', array_fill(0, count($subStrandIds), '?'));
                        $subSql .= " AND id IN ({$in})";
                        $subParams = array_merge($subParams, $subStrandIds);
                    }
                }
                $subSql .= " ORDER BY sort_order, id";
                $stmt = $this->db->prepare($subSql);
                $stmt->execute($subParams);
                $subStrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($subStrands as $sub) {
                    $outcomeStmt = $this->db->prepare(
                        "SELECT outcome, grade_level FROM learning_outcomes WHERE sub_strand_id = ? ORDER BY id"
                    );
                    $outcomeStmt->execute([(int) $sub['id']]);
                    $outcomes = $outcomeStmt->fetchAll(PDO::FETCH_ASSOC);

                    $title = $area['name'] . ': ' . $strand['name'];
                    if ($sub['name'] && $sub['name'] !== $strand['name']) {
                        $title .= ' - ' . $sub['name'];
                    }
                    if (!empty($sub['variant'])) {
                        $title .= ' (' . $sub['variant'] . ')';
                    }

                    $templateId = $this->ensureSchemeTemplate([
                        'learning_area_id' => (int) $learningAreaId,
                        'strand_id' => (int) $strand['id'],
                        'sub_strand_id' => (int) $sub['id'],
                        'title' => $title,
                        'created_by' => $userId,
                    ]);

                    $weekId = $this->resolveCalendarWeekId($termId, $startWeek);

                    $dupStmt = $this->db->prepare(
                        "SELECT id FROM schemes_of_work
                         WHERE scheme_template_id = ? AND (academic_year_class_learning_area_id <=> ?) AND (academic_year_calendar_week_id <=> ?)
                         LIMIT 1"
                    );
                    $dupStmt->execute([$templateId, $ayclaId, $weekId]);
                    if ($dupStmt->fetchColumn()) {
                        $skipped++;
                        continue;
                    }

                    $insertStmt = $this->db->prepare(
                        "INSERT INTO schemes_of_work (
                            scheme_template_id, academic_year_class_learning_area_id, academic_year_calendar_week_id, teacher_id, status
                        ) VALUES (?, ?, ?, ?, 'draft')"
                    );
                    $insertStmt->execute([$templateId, $ayclaId, $weekId, $teacherId ?: null]);
                    $created[] = (int) $this->db->lastInsertId();
                    $inserted++;
                    $startWeek++;
                }
            }

            $this->db->commit();

            $summary = "Generated {$inserted} scheme of work " . ($inserted === 1 ? 'entry' : 'entries');
            if ($skipped > 0) {
                $summary .= ", skipped {$skipped} existing";
            }

            return successResponse([
                'status' => 'success',
                'message' => $summary,
                'data' => [
                    'created_ids' => $created,
                    'created_count' => $inserted,
                    'skipped_count' => $skipped,
                ]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function updateSchemeOfWork($id, $data)
    {
        try {
            if (!$id) {
                return errorResponse('Scheme of work ID is required', 400);
            }

            $data = $this->normalizeSchemeOfWorkPayload($data, false);

            $stmt = $this->db->prepare("
                SELECT sw.scheme_template_id, sw.academic_year_class_learning_area_id, sw.academic_year_calendar_week_id,
                       st.learning_area_id, st.learning_area_id AS subject_id
                FROM schemes_of_work sw
                JOIN scheme_templates st ON st.id = sw.scheme_template_id
                WHERE sw.id = ?
            ");
            $stmt->execute([(int) $id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return errorResponse('Scheme of work not found', 404);
            }

            $fields = [];
            $values = [];

            if (array_key_exists('teacher_id', $data)) {
                $fields[] = 'teacher_id = ?';
                $values[] = $data['teacher_id'] ?? null;
            }
            if (array_key_exists('status', $data)) {
                $fields[] = 'status = ?';
                $values[] = $this->normalizeSchemeStatus($data['status']);
            }
            if (array_key_exists('class_id', $data) || array_key_exists('learning_area_id', $data)) {
                $learningAreaId = !empty($data['learning_area_id']) ? (int) $data['learning_area_id'] : (int) ($existing['learning_area_id'] ?? 0);
                $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : $this->resolveCurrentAcademicYearId();
                $ayClassId = $this->resolveAcademicYearClassId($data['class_id'] ?? 0, $academicYearId);
                $ayclaId = $ayClassId > 0 ? $this->resolveClassLearningAreaId($ayClassId, $learningAreaId) : null;
                $fields[] = 'academic_year_class_learning_area_id = ?';
                $values[] = $ayclaId;
            }
            if (array_key_exists('term_id', $data) || array_key_exists('week_number', $data)) {
                $termId = !empty($data['term_id']) ? (int) $data['term_id'] : 0;
                $weekNumber = array_key_exists('week_number', $data) ? $data['week_number'] : null;
                $fields[] = 'academic_year_calendar_week_id = ?';
                $values[] = $this->resolveCalendarWeekId($termId, $weekNumber);
            }

            $templateFields = [];
            $templateValues = [];
            foreach (['title', 'activities', 'resources', 'assessment_methods'] as $field) {
                if (array_key_exists($field, $data)) {
                    $templateFields[] = "{$field} = ?";
                    $templateValues[] = $data[$field];
                }
            }
            if ($templateFields) {
                $templateValues[] = (int) $existing['scheme_template_id'];
                $this->db->prepare("UPDATE scheme_templates SET " . implode(', ', $templateFields) . " WHERE id = ?")
                    ->execute($templateValues);
            }

            if (empty($fields)) {
                return errorResponse('No valid fields to update', 400);
            }

            $values[] = $id;
            $stmt = $this->db->prepare("UPDATE schemes_of_work SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);

            return successResponse(['id' => (int) $id, 'message' => 'Scheme of work updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function approveSchemeOfWork($id, $data = [])
    {
        try {
            if (!$id) {
                return errorResponse('Scheme of work ID is required', 400);
            }

            $stmt = $this->db->prepare("
                UPDATE schemes_of_work
                SET status = 'approved', approved_by = ?
                WHERE id = ?
            ");
            $approvedBy = $data['approved_by'] ?? $this->getCurrentStaffId();
            $stmt->execute([$approvedBy, $id]);

            return successResponse(['id' => (int) $id, 'message' => 'Scheme of work approved']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function rejectSchemeOfWork($id, $data = [])
    {
        try {
            if (!$id) {
                return errorResponse('Scheme of work ID is required', 400);
            }

            $stmt = $this->db->prepare("
                UPDATE schemes_of_work
                SET status = 'draft', approved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['rejected_by'] ?? $this->getCurrentStaffId(),
                $id
            ]);

            return successResponse(['id' => (int) $id, 'message' => 'Scheme of work rejected']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteSchemeOfWork($id)
    {
        try {
            if (!$id) {
                return errorResponse('Scheme of work ID is required', 400);
            }

            $stmt = $this->db->prepare("DELETE FROM schemes_of_work WHERE id = ?");
            $stmt->execute([$id]);

            return successResponse(['id' => (int) $id, 'message' => 'Scheme of work deleted']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function normalizeSchemeOfWorkPayload(array $data, bool $forCreate = true): array
    {
        if (!empty($data['subject_id']) && empty($data['learning_area_id'])) {
            $data['learning_area_id'] = (int) $data['subject_id'];
        }
        if (!empty($data['learning_area_id']) && empty($data['subject_id'])) {
            $data['subject_id'] = (int) $data['learning_area_id'];
        }
        if (!empty($data['topics']) && empty($data['learning_outcomes'])) {
            $data['learning_outcomes'] = $data['topics'];
        }
        if (!empty($data['notes']) && empty($data['description'])) {
            $data['description'] = $data['notes'];
        }
        if (!empty($data['term']) && empty($data['term_id'])) {
            $termNumber = (int) $data['term'];
            $data['term_number'] = $termNumber;
            if (!empty($data['academic_year_id'])) {
                $termId = $this->resolveAcademicYearTermId((int) $data['academic_year_id'], $termNumber);
                if ($termId > 0) {
                    $data['term_id'] = $termId;
                }
            }
        }
        if ($forCreate && empty($data['week_number'])) {
            $data['week_number'] = 1;
        }
        if (!empty($data['learning_area_id']) && empty($data['subject_name'])) {
            $stmt = $this->db->prepare("SELECT name FROM learning_areas WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $data['learning_area_id']]);
            $data['subject_name'] = $stmt->fetchColumn() ?: null;
        }
        if ($forCreate && empty($data['teacher_id'])) {
            $userId = $data['created_by'] ?? $this->getCurrentUserId();
            if ($userId) {
                $stmt = $this->db->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1");
                $stmt->execute([(int) $userId]);
                $staffId = (int) ($stmt->fetchColumn() ?: 0);
                if ($staffId > 0) {
                    $data['teacher_id'] = $staffId;
                }
            }
        }
        if (isset($data['status']) && $data['status'] !== '') {
            $data['status'] = $this->normalizeSchemeStatus($data['status']);
        }

        return $data;
    }

    /**
     * Clamp a scheme status to the live schemes_of_work/scheme_templates enum
     * ('draft', 'approved', 'archived').
     */
    private function normalizeSchemeStatus($status)
    {
        $status = (string) $status;
        if ($status === '' || $status === null) {
            return 'draft';
        }
        $status = strtolower($status);
        return in_array($status, ['draft', 'approved', 'archived'], true) ? $status : 'draft';
    }

    /**
     * Resolve a classes.id (optionally scoped to an academic year) to the
     * matching academic_year_classes row id.
     */
    private function resolveAcademicYearClassId($classId, $academicYearId = null)
    {
        $classId = (int) $classId;
        if ($classId <= 0) {
            return 0;
        }
        if (empty($academicYearId)) {
            $academicYearId = $this->resolveCurrentAcademicYearId();
        }
        if (empty($academicYearId)) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1");
        $stmt->execute([(int) $academicYearId, $classId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve the academic_year_class_learning_areas row id for a class + learning area.
     */
    private function resolveClassLearningAreaId($ayClassId, $learningAreaId)
    {
        if (empty($ayClassId) || empty($learningAreaId)) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_class_learning_areas
             WHERE academic_year_class_id = ? AND learning_area_id = ?
             LIMIT 1"
        );
        $stmt->execute([(int) $ayClassId, (int) $learningAreaId]);
        return (int) ($stmt->fetchColumn() ?: 0) ?: null;
    }

    /**
     * Resolve the academic_year_calendar week row id for an academic term + week number.
     */
    private function resolveCalendarWeekId($termId, $weekNumber)
    {
        if (empty($termId) || $weekNumber === null || $weekNumber === '' || (int) $weekNumber <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_calendar
             WHERE academic_year_term_id = ? AND week_number = ?
             LIMIT 1"
        );
        $stmt->execute([(int) $termId, (int) $weekNumber]);
        return (int) ($stmt->fetchColumn() ?: 0) ?: null;
    }

    /**
     * Get (or create) the scheme_templates content row for a scheme payload.
     * Reuses an existing template with the same (learning_area, strand, sub_strand, title).
     */
    private function ensureSchemeTemplate(array $data)
    {
        $learningAreaId = (int) ($data['learning_area_id'] ?? 0);
        $strandId = (isset($data['strand_id']) && $data['strand_id'] !== '') ? (int) $data['strand_id'] : null;
        $subStrandId = (isset($data['sub_strand_id']) && $data['sub_strand_id'] !== '') ? (int) $data['sub_strand_id'] : null;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Untitled';
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM scheme_templates
             WHERE learning_area_id = ? AND (strand_id <=> ?) AND (sub_strand_id <=> ?) AND title = ?
             LIMIT 1"
        );
        $stmt->execute([$learningAreaId, $strandId, $subStrandId, $title]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO scheme_templates (
                learning_area_id, strand_id, sub_strand_id, title,
                activities, resources, assessment_methods, created_by, is_shared, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'draft')"
        );
        $stmt->execute([
            $learningAreaId,
            $strandId,
            $subStrandId,
            $title,
            $data['activities'] ?? null,
            $data['resources'] ?? null,
            $data['assessment_methods'] ?? null,
            $data['created_by'] ?? $this->getCurrentUserId(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getLessonObservations($params = [])
    {
        try {
            $sql = "
                SELECT 
                    lo.*,
                    CONCAT(tp.first_name, ' ', tp.last_name) as teacher_name,
                    CONCAT(op.first_name, ' ', op.last_name) as observer_name,
                    la.name as learning_area_name,
                    c.name as class_name
                FROM lesson_observations lo
                JOIN staff t ON lo.teacher_id = t.id
                JOIN persons tp ON tp.id = t.person_id
                JOIN staff o ON lo.observer_id = o.id
                JOIN persons op ON op.id = o.person_id
                JOIN learning_areas la ON lo.learning_area_id = la.id
                JOIN classes c ON lo.class_id = c.id
                ORDER BY lo.observation_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($observations);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonObservation($data)
    {
        try {
            $required = ['teacher_id', 'observer_id', 'learning_area_id', 'class_id', 'observation_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO lesson_observations (
                    teacher_id,
                    observer_id,
                    learning_area_id,
                    class_id,
                    observation_date,
                    strengths,
                    areas_for_improvement,
                    recommendations,
                    rating,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['teacher_id'],
                $data['observer_id'],
                $data['learning_area_id'],
                $data['class_id'],
                $data['observation_date'],
                json_encode($data['strengths'] ?? []),
                json_encode($data['areas_for_improvement'] ?? []),
                json_encode($data['recommendations'] ?? []),
                $data['rating'] ?? null,
                'completed'
            ]);

            $observationId = $this->db->lastInsertId();

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson observation created successfully',
                'data' => ['id' => $observationId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // CURRICULUM UNITS CRUD (Additional Methods)
    // ========================================================================

    public function getCurriculumUnit($id)
    {
        try {
            $sql = "
                SELECT 
                    st.*,
                    la.name as learning_area_name,
                    la.code as learning_area_code
                FROM strands st
                JOIN learning_areas la ON st.learning_area_id = la.id
                WHERE st.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return errorResponse('Curriculum unit not found');
            }

            // Get topics for this unit
            $sql = "SELECT * FROM sub_strands WHERE strand_id = ? AND status = 'active' ORDER BY sort_order";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $unit['topics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', $id, "Retrieved curriculum unit: {$unit['name']}");

            return successResponse($unit);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listCurriculumUnits($params = [])
    {
        try {
            $sql = "
                SELECT 
                    st.*,
                    la.name as learning_area_name,
                    la.code as learning_area_code
                FROM strands st
                JOIN learning_areas la ON st.learning_area_id = la.id
                WHERE 1=1
            ";
            $conditions = [];
            $queryParams = [];

            if (isset($params['learning_area_id']) && $params['learning_area_id'] !== '') {
                $conditions[] = "st.learning_area_id = ?";
                $queryParams[] = (int) $params['learning_area_id'];
            }

            if (isset($params['status']) && in_array($params['status'], ['active', 'inactive'])) {
                $conditions[] = "st.status = ?";
                $queryParams[] = $params['status'];
            }

            if (!empty($params['search'])) {
                $conditions[] = "(st.name LIKE ? OR st.description LIKE ? OR la.name LIKE ?)";
                $like = '%' . $params['search'] . '%';
                $queryParams[] = $like;
                $queryParams[] = $like;
                $queryParams[] = $like;
            }

            if (!empty($conditions)) {
                $sql .= " AND " . implode(' AND ', $conditions);
            }

            $allowedSorts = ['name', 'description', 'learning_area_name', 'sort_order', 'created_at'];
            $sort = (isset($params['sort']) && in_array($params['sort'], $allowedSorts)) ? $params['sort'] : 'sort_order';
            $dir = (isset($params['dir']) && strtolower($params['dir']) === 'desc') ? 'DESC' : 'ASC';
            $sql .= " ORDER BY la.name ASC, {$sort} {$dir}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($queryParams);
            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($units);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateCurriculumUnit($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if unit exists
            $stmt = $this->db->prepare("SELECT id, name FROM strands WHERE id = ?");
            $stmt->execute([$id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return errorResponse('Curriculum unit not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['learning_area_id', 'grade_level', 'code', 'name', 'description', 'sort_order', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            if (isset($data['order_sequence']) && !isset($data['sort_order'])) {
                $updates[] = 'sort_order = ?';
                $params[] = (int) $data['order_sequence'];
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE strands SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated curriculum unit: {$unit['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteCurriculumUnit($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE strands SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Curriculum unit not found');
            }

            $this->logAction('delete', $id, "Soft deleted curriculum unit");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // UNIT TOPICS CRUD
    // ========================================================================

    public function listUnitTopics($unitId = null, $params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = "WHERE 1=1";
            $bindings = [];

            if ($unitId !== null) {
                $where .= " AND ut.strand_id = ?";
                $bindings[] = $unitId;
            }

            if (!empty($params['status'])) {
                $where .= " AND ut.status = ?";
                $bindings[] = $params['status'];
            } else {
                $where .= " AND ut.status = 'active'";
            }

            // Get total count
            $countSql = "SELECT COUNT(*) FROM sub_strands ut $where";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    ut.*,
                    st.name as unit_name
                FROM sub_strands ut
                JOIN strands st ON ut.strand_id = st.id
                $where
                ORDER BY ut.sort_order ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => [
                    'topics' => $topics,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getUnitTopic($id)
    {
        try {
            $sql = "
                SELECT 
                    ut.*,
                    st.name as unit_name,
                    st.learning_area_id,
                    la.name as learning_area_name
                FROM sub_strands ut
                JOIN strands st ON ut.strand_id = st.id
                JOIN learning_areas la ON st.learning_area_id = la.id
                WHERE ut.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $topic = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$topic) {
                return errorResponse('Unit topic not found');
            }

            return successResponse($topic);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createUnitTopic($data)
    {
        try {
            $required = ['unit_id', 'name'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get next sort_order if not provided
            if (!isset($data['order_sequence']) && !isset($data['sort_order'])) {
                $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM sub_strands WHERE strand_id = ?");
                $stmt->execute([$data['unit_id']]);
                $data['order_sequence'] = $stmt->fetchColumn();
            }
            $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) ($data['order_sequence'] ?? 1);

            $gradeLevel = '';
            $gradeStmt = $this->db->prepare("SELECT grade_level FROM strands WHERE id = ?");
            $gradeStmt->execute([$data['unit_id']]);
            $gradeLevel = (string) $gradeStmt->fetchColumn();

            $sql = "
                INSERT INTO sub_strands (
                    strand_id,
                    grade_level,
                    code,
                    name,
                    description,
                    sort_order,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['unit_id'],
                $gradeLevel,
                $data['code'] ?? null,
                $data['name'],
                $data['description'] ?? null,
                $sortOrder,
                $data['status'] ?? 'active'
            ]);

            $topicId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $topicId, "Created unit topic: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic created successfully',
                'data' => ['id' => $topicId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function updateUnitTopic($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if topic exists
            $stmt = $this->db->prepare("SELECT id FROM sub_strands WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Unit topic not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['strand_id', 'grade_level', 'code', 'name', 'description', 'sort_order', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            if (isset($data['unit_id']) && !isset($data['strand_id'])) {
                $updates[] = 'strand_id = ?';
                $params[] = $data['unit_id'];
            }
            if (isset($data['order_sequence']) && !isset($data['sort_order'])) {
                $updates[] = 'sort_order = ?';
                $params[] = (int) $data['order_sequence'];
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE sub_strands SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated unit topic");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteUnitTopic($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE sub_strands SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Unit topic not found');
            }

            $this->logAction('delete', $id, "Soft deleted unit topic");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // EXAM SCHEDULE DIRECT CRUD (for exam_schedule.js frontend)
    // These are separate from the workflow-based exam methods above
    // ========================================================================

    /**
     * List exam schedules with filtering, pagination, and summary
     */
    public function listExamSchedules($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['term_id'])) {
                $where[] = "es.academic_year_term_id = ?";
                $bindings[] = $params['term_id'];
            }
            if (!empty($params['term'])) {
                $where[] = "es.academic_year_term_id = ?";
                $bindings[] = $params['term'];
            }
            if (!empty($params['academic_year_id'])) {
                $where[] = "ayt.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }
            if (!empty($params['class_id'])) {
                $where[] = "es.academic_year_class_stream_id = ?";
                $bindings[] = $params['class_id'];
            }
            if (!empty($params['subject_id'])) {
                $where[] = "es.learning_area_id = ?";
                $bindings[] = $params['subject_id'];
            }
            if (!empty($params['status'])) {
                $where[] = "es.status = ?";
                $bindings[] = $params['status'];
            }
            if (!empty($params['exam_type'])) {
                $where[] = "es.exam_type = ?";
                $bindings[] = $params['exam_type'];
            }

            // Exclude cancelled by default
            $where[] = "es.status != 'cancelled'";

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "SELECT COUNT(*) FROM exam_schedules es WHERE {$whereClause}";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = (int) $stmt->fetchColumn();

            // Get summary counts
            $summarySql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN es.status = 'upcoming' OR es.status = 'scheduled' THEN 1 ELSE 0 END) as upcoming,
                    SUM(CASE WHEN es.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM exam_schedules es
                WHERE {$whereClause}
            ";
            $stmt = $this->db->prepare($summarySql);
            $stmt->execute($bindings);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get paginated data
            $sql = "
                SELECT 
                    es.id,
                    es.academic_year_term_id AS term_id,
                    ayt.academic_year_id,
                    es.academic_year_class_stream_id AS class_id,
                    c.name AS class_name,
                    es.learning_area_id AS subject_id,
                    COALESCE(la.name, '') AS subject_name,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.duration_minutes AS duration,
                    es.room_id,
                    r.name AS room_name,
                    es.venue,
                    es.invigilator_id,
                    CONCAT(inv_p.first_name, ' ', inv_p.last_name) AS invigilator_name,
                    es.supervisor_id,
                    CONCAT(sup_p.first_name, ' ', sup_p.last_name) AS supervisor_name,
                    es.notes,
                    es.status,
                    es.created_at,
                    es.updated_at
                FROM exam_schedules es
                LEFT JOIN academic_year_terms ayt ON ayt.id = es.academic_year_term_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = es.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN learning_areas la ON es.learning_area_id = la.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN persons inv_p ON inv_p.id = inv.person_id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                LEFT JOIN persons sup_p ON sup_p.id = sup.person_id
                WHERE {$whereClause}
                ORDER BY es.exam_date ASC, es.start_time ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed exam schedules');

            return successResponse([
                'exams' => $exams,
                'summary' => [
                    'total' => (int) ($summary['total'] ?? 0),
                    'upcoming' => (int) ($summary['upcoming'] ?? 0),
                    'in_progress' => (int) ($summary['in_progress'] ?? 0),
                    'completed' => (int) ($summary['completed'] ?? 0),
                ],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get a single exam schedule by ID
     */
    public function getExamScheduleById($id)
    {
        try {
            $sql = "
                SELECT 
                    es.*,
                    es.academic_year_term_id AS term_id,
                    ayt.academic_year_id,
                    es.academic_year_class_stream_id AS class_id,
                    c.name AS class_name,
                    COALESCE(la.name, '') AS subject_name,
                    r.name AS room_name,
                    CONCAT(inv_p.first_name, ' ', inv_p.last_name) AS invigilator_name,
                    CONCAT(sup_p.first_name, ' ', sup_p.last_name) AS supervisor_name
                FROM exam_schedules es
                LEFT JOIN academic_year_terms ayt ON ayt.id = es.academic_year_term_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = es.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN learning_areas la ON es.learning_area_id = la.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN persons inv_p ON inv_p.id = inv.person_id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                LEFT JOIN persons sup_p ON sup_p.id = sup.person_id
                WHERE es.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$exam) {
                return errorResponse('Exam schedule not found');
            }

            return successResponse($exam);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new exam schedule entry
     */
    public function createExamScheduleEntry($data)
    {
        try {
            $required = ['class_id', 'subject_id', 'exam_date', 'start_time', 'end_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return errorResponse("Missing required field: {$field}");
                }
            }

            $sql = "
                INSERT INTO exam_schedules (
                    academic_year_term_id, academic_year_class_stream_id, learning_area_id,
                    exam_name, exam_type, exam_date, start_time, end_time,
                    duration_minutes, room_id, venue, invigilator_id,
                    supervisor_id, notes, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['term_id'] ?? $data['term'] ?? null,
                $data['class_id'],
                $data['subject_id'],
                $data['exam_name'] ?? null,
                $data['exam_type'] ?? null,
                $data['exam_date'],
                $data['start_time'],
                $data['end_time'] ?? null,
                $data['duration'] ?? $data['duration_minutes'] ?? null,
                $data['room_id'] ?? null,
                $data['venue'] ?? null,
                $data['invigilator_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
                $data['status'] ?? 'scheduled',
            ]);

            $id = $this->db->lastInsertId();
            $this->logAction('create', $id, "Created exam schedule: " . ($data['exam_name'] ?? 'N/A'));

            return successResponse([
                'id' => $id,
                'message' => 'Exam schedule created successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update an existing exam schedule entry
     */
    public function updateExamScheduleEntry($id, $data)
    {
        try {
            // Check exists
            $stmt = $this->db->prepare("SELECT id, status FROM exam_schedules WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return errorResponse('Exam schedule not found');
            }

            $fields = [];
            $values = [];

            $allowed = [
                'academic_year_term_id',
                'academic_year_class_stream_id',
                'learning_area_id',
                'exam_name',
                'exam_type',
                'exam_date',
                'start_time',
                'end_time',
                'duration_minutes',
                'room_id',
                'venue',
                'invigilator_id',
                'supervisor_id',
                'notes',
                'status'
            ];

            // Map frontend field names to DB columns
            if (isset($data['duration']) && !isset($data['duration_minutes'])) {
                $data['duration_minutes'] = $data['duration'];
            }
            if (isset($data['term_id']) && !isset($data['academic_year_term_id'])) {
                $data['academic_year_term_id'] = $data['term_id'];
            }
            if (isset($data['term']) && !isset($data['academic_year_term_id'])) {
                $data['academic_year_term_id'] = $data['term'];
            }
            if (isset($data['class_id']) && !isset($data['academic_year_class_stream_id'])) {
                $data['academic_year_class_stream_id'] = $data['class_id'];
            }
            if (isset($data['subject_id']) && !isset($data['learning_area_id'])) {
                $data['learning_area_id'] = $data['subject_id'];
            }

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return errorResponse('No valid fields to update');
            }

            $values[] = $id;
            $sql = "UPDATE exam_schedules SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->logAction('update', $id, "Updated exam schedule");

            return successResponse([
                'id' => $id,
                'message' => 'Exam schedule updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete (soft) an exam schedule entry
     */
    public function deleteExamScheduleEntry($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM exam_schedules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Exam schedule not found');
            }

            // Soft delete
            $stmt = $this->db->prepare("UPDATE exam_schedules SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Soft deleted exam schedule");

            return successResponse([
                'message' => 'Exam schedule deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // SUPERVISION ROSTER CRUD (for supervision_roster.js frontend)
    // Backed by the supervision_rosters table (exam_schedule_id + staff_id + role).
    // ========================================================================

    /**
     * List supervision roster entries with filtering, pagination, and summary.
     */
    public function listSupervisionRosters($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['term'])) {
                $where[] = "es.academic_year_term_id = ?";
                $bindings[] = $params['term'];
            }
            if (!empty($params['start_date'])) {
                $where[] = "sr.date >= ?";
                $bindings[] = $params['start_date'];
            }
            if (!empty($params['end_date'])) {
                $where[] = "sr.date <= ?";
                $bindings[] = $params['end_date'];
            }
            if (!empty($params['search'])) {
                $where[] = "(CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR es.exam_name LIKE ?)";
                $bindings[] = "%{$params['search']}%";
                $bindings[] = "%{$params['search']}%";
            }
            if (!empty($params['status'])) {
                $where[] = "sr.status = ?";
                $bindings[] = $params['status'];
            }

            $whereClause = implode(' AND ', $where);

            $countSql = "SELECT COUNT(*) FROM supervision_rosters sr
                         LEFT JOIN exam_schedules es ON es.id = sr.exam_schedule_id
                         LEFT JOIN staff st ON st.id = sr.staff_id
                         LEFT JOIN persons p ON p.id = st.person_id
                         WHERE {$whereClause}";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = (int) $stmt->fetchColumn();

            $sql = "
                SELECT
                    sr.id,
                    sr.exam_schedule_id,
                    sr.staff_id,
                    sr.role,
                    sr.date AS supervision_date,
                    sr.time_slot_id,
                    sr.room_id AS venue,
                    sr.notes,
                    sr.status,
                    sr.created_at,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.venue AS exam_venue,
                    es.academic_year_term_id AS term_id,
                    es.status AS exam_status,
                    CONCAT(p.first_name, ' ', p.last_name) AS supervisor_name,
                    ts.label AS time_slot_label,
                    CONCAT(ts.start_time, '-', ts.end_time) AS time_range
                FROM supervision_rosters sr
                LEFT JOIN exam_schedules es ON es.id = sr.exam_schedule_id
                LEFT JOIN staff st ON st.id = sr.staff_id
                LEFT JOIN persons p ON p.id = st.person_id
                LEFT JOIN time_slots ts ON ts.id = sr.time_slot_id
                WHERE {$whereClause}
                ORDER BY sr.date ASC, es.start_time ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed supervision roster');

            return successResponse([
                'roster' => $roster,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get a single supervision roster entry by ID.
     */
    public function getSupervisionRosterById($id)
    {
        try {
            $sql = "
                SELECT
                    sr.id,
                    sr.exam_schedule_id,
                    sr.staff_id,
                    sr.role,
                    sr.date AS supervision_date,
                    sr.time_slot_id,
                    sr.room_id AS venue,
                    sr.notes,
                    sr.status,
                    sr.created_at,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.venue AS exam_venue,
                    es.academic_year_term_id AS term_id,
                    es.status AS exam_status,
                    CONCAT(p.first_name, ' ', p.last_name) AS supervisor_name
                FROM supervision_rosters sr
                LEFT JOIN exam_schedules es ON es.id = sr.exam_schedule_id
                LEFT JOIN staff st ON st.id = sr.staff_id
                LEFT JOIN persons p ON p.id = st.person_id
                WHERE sr.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$entry) {
                return errorResponse('Supervision roster entry not found');
            }

            return successResponse($entry);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a supervision roster entry.
     */
    public function createSupervisionRoster($data)
    {
        try {
            $required = ['exam_schedule_id', 'staff_id', 'role'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return errorResponse("Missing required field: {$field}");
                }
            }

            $status = $data['status'] ?? 'assigned';
            if (!in_array($status, ['assigned', 'confirmed', 'completed'], true)) {
                $status = 'assigned';
            }

            // Inherit date / venue from the exam schedule when not provided.
            $date = $data['date'] ?? null;
            $room = $data['room_id'] ?? null;
            if ($date === null || $room === null) {
                $stmt = $this->db->prepare(
                    "SELECT exam_date, venue FROM exam_schedules WHERE id = ?"
                );
                $stmt->execute([$data['exam_schedule_id']]);
                $exam = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($date === null) {
                    $date = $exam['exam_date'] ?? null;
                }
                if ($room === null) {
                    $room = $exam['venue'] ?? null;
                }
            }

            $sql = "
                INSERT INTO supervision_rosters (
                    exam_schedule_id, staff_id, role, date, time_slot_id,
                    room_id, notes, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['exam_schedule_id'],
                $data['staff_id'],
                $data['role'],
                $date,
                $data['time_slot_id'] ?? null,
                $room,
                $data['notes'] ?? null,
                $status,
            ]);

            $id = $this->db->lastInsertId();
            $this->logAction('create', $id, "Created supervision roster entry");

            return successResponse([
                'id' => $id,
                'message' => 'Supervision roster entry created successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update a supervision roster entry.
     */
    public function updateSupervisionRoster($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM supervision_rosters WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Supervision roster entry not found');
            }

            $fields = [];
            $values = [];

            $allowed = [
                'exam_schedule_id',
                'staff_id',
                'role',
                'date',
                'time_slot_id',
                'room_id',
                'notes',
                'status'
            ];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return errorResponse('No valid fields to update');
            }

            $values[] = $id;
            $sql = "UPDATE supervision_rosters SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->logAction('update', $id, "Updated supervision roster entry");

            return successResponse([
                'id' => $id,
                'message' => 'Supervision roster entry updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete a supervision roster entry.
     */
    public function deleteSupervisionRoster($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM supervision_rosters WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Supervision roster entry not found');
            }

            $stmt = $this->db->prepare("DELETE FROM supervision_rosters WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Deleted supervision roster entry");

            return successResponse([
                'message' => 'Supervision roster entry deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Auto-generate supervision roster entries for the current term's upcoming
     * exam schedules that do not yet have a supervisor assigned.
     */
    public function autoGenerateSupervisionRoster($data)
    {
        try {
            $termId = $data['term_id'] ?? null;
            if (empty($termId)) {
                $stmt = $this->db->query(
                    "SELECT ayt.id FROM academic_year_terms ayt
                     JOIN academic_years ay ON ay.id = ayt.academic_year_id
                     WHERE ay.is_current = 1 AND ayt.status = 'current'
                     LIMIT 1"
                );
                $termId = $stmt->fetchColumn();
            }
            if (empty($termId)) {
                return errorResponse('No current term found to generate a roster for');
            }

            // Upcoming/scheduled exams in the term with no roster row yet and a supervisor.
            $sql = "
                SELECT es.id, es.exam_date, es.venue, es.supervisor_id
                FROM exam_schedules es
                WHERE es.academic_year_term_id = ?
                  AND es.status IN ('scheduled', 'upcoming')
                  AND es.supervisor_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM supervision_rosters sr
                      WHERE sr.exam_schedule_id = es.id
                  )
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$termId]);
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $created = 0;
            $insert = $this->db->prepare(
                "INSERT INTO supervision_rosters (
                    exam_schedule_id, staff_id, role, date, room_id, status
                 ) VALUES (?, ?, 'supervisor', ?, ?, 'assigned')"
            );
            foreach ($exams as $exam) {
                $insert->execute([
                    $exam['id'],
                    $exam['supervisor_id'],
                    $exam['exam_date'],
                    $exam['venue'],
                ]);
                $created++;
            }

            if ($created > 0) {
                $this->logAction('create', null, "Auto-generated {$created} supervision roster entries");
            }

            return successResponse([
                'created' => $created,
                'message' => $created > 0
                    ? "Created {$created} supervision roster entries"
                    : 'All scheduled exams already have supervision assigned'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // LESSON PLANS CRUD (Additional Methods)
    // ========================================================================

    public function getLessonPlan($id)
    {
        try {
            $sql = "
                SELECT 
                    lp.*,
                    lt.title AS title,
                    lt.title AS topic,
                    lt.learning_area_id,
                    la.name AS subject_name,
                    lt.strand_id AS unit_id,
                    lt.sub_strand_id AS topic_id,
                    lt.duration,
                    lt.activities AS content,
                    lt.activities AS activities,
                    lt.resources,
                    lt.assessment,
                    lt.homework,
                    ayc.class_id,
                    c.name AS class_name,
                    aycd.date AS lesson_date,
                    aycd.date AS date,
                    ayt.id AS term_id,
                    ayc.academic_year_id,
                    CONCAT(tp.first_name, ' ', tp.last_name) AS teacher_name,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS approved_by_name
                FROM lesson_plans lp
                JOIN lesson_templates lt ON lt.id = lp.lesson_template_id
                LEFT JOIN learning_areas la ON la.id = lt.learning_area_id
                LEFT JOIN academic_year_class_learning_areas acla ON acla.id = lp.academic_year_class_learning_area_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = acla.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                LEFT JOIN academic_year_calendar aycal ON aycal.id = aycd.academic_year_calendar_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = aycal.academic_year_term_id
                LEFT JOIN staff s ON lp.teacher_id = s.id
                LEFT JOIN persons tp ON tp.id = s.person_id
                LEFT JOIN staff appr ON lp.approved_by = appr.id
                LEFT JOIN persons ap ON ap.id = appr.person_id
                WHERE lp.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            return successResponse($plan);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateLessonPlan($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists
            $stmt = $this->db->prepare("SELECT id, status, lesson_template_id, academic_year_class_learning_area_id, academic_year_calendar_day_id FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            // Prevent editing approved plans
            if ($plan['status'] === 'approved' && !isset($data['allow_edit_approved'])) {
                return errorResponse('Cannot edit approved lesson plan');
            }

            $title = $data['title'] ?? $data['topic'] ?? null;
            $learningAreaId = $data['learning_area_id'] ?? $data['subject_id'] ?? null;
            $classId = $data['class_id'] ?? null;
            $date = $data['date'] ?? $data['lesson_date'] ?? null;

            // Update the linked lesson template (content)
            $templateUpdates = [];
            $templateParams = [];
            if ($title !== null) {
                $templateUpdates[] = 'title = ?';
                $templateParams[] = $title;
            }
            if ($learningAreaId !== null) {
                $templateUpdates[] = 'learning_area_id = ?';
                $templateParams[] = (int) $learningAreaId;
            }
            foreach (['strand_id', 'sub_strand_id', 'duration', 'resources', 'assessment', 'homework'] as $field) {
                if (array_key_exists($field, $data)) {
                    $templateUpdates[] = "$field = ?";
                    $templateParams[] = $data[$field];
                }
            }
            if (array_key_exists('content', $data) || array_key_exists('activities', $data) || array_key_exists('objectives', $data)) {
                $activities = $data['activities'] ?? $data['content'] ?? null;
                if (!empty($data['objectives'])) {
                    $objStmt = $this->db->prepare("SELECT activities FROM lesson_templates WHERE id = ?");
                    $objStmt->execute([$plan['lesson_template_id']]);
                    $existingActivities = $objStmt->fetchColumn();
                    $baseActivities = $activities ?? $existingActivities;
                    $activities = $baseActivities ? $data['objectives'] . "\n\n" . $baseActivities : $data['objectives'];
                }
                $templateUpdates[] = 'activities = ?';
                $templateParams[] = $activities;
            }
            if (!empty($templateUpdates)) {
                $templateParams[] = $plan['lesson_template_id'];
                $stmt = $this->db->prepare("UPDATE lesson_templates SET " . implode(', ', $templateUpdates) . " WHERE id = ?");
                $stmt->execute($templateParams);
            }

            // Update the lesson plan links
            $planUpdates = [];
            $planParams = [];
            if (array_key_exists('teacher_id', $data)) {
                $planUpdates[] = 'teacher_id = ?';
                $planParams[] = $data['teacher_id'];
            }
            if ($classId !== null && $learningAreaId !== null) {
                $ayclaId = $this->resolveAyclaId((int) $classId, (int) $learningAreaId);
                if ($ayclaId) {
                    $planUpdates[] = 'academic_year_class_learning_area_id = ?';
                    $planParams[] = $ayclaId;
                }
            }
            if ($date !== null) {
                $calendarDayId = $this->resolveCalendarDayId($date);
                if ($calendarDayId) {
                    $planUpdates[] = 'academic_year_calendar_day_id = ?';
                    $planParams[] = $calendarDayId;
                }
            }
            if (!empty($planUpdates)) {
                $planParams[] = $id;
                $stmt = $this->db->prepare("UPDATE lesson_plans SET " . implode(', ', $planUpdates) . " WHERE id = ?");
                $stmt->execute($planParams);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan updated successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function deleteLessonPlan($id)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists and status
            $stmt = $this->db->prepare("SELECT status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            // Prevent deleting approved plans
            if ($plan['status'] === 'approved') {
                return errorResponse('Cannot delete approved lesson plan');
            }

            // Soft delete by archiving
            $stmt = $this->db->prepare("UPDATE lesson_plans SET status = 'archived' WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            $this->logAction('delete', $id, "Archived lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan deleted successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function approveLessonPlan($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists
            $stmt = $this->db->prepare("SELECT id, status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            if ($plan['status'] !== 'draft') {
                return errorResponse('Only draft lesson plans can be approved');
            }

            $approverId = is_array($data) ? ($data['approved_by'] ?? $this->getCurrentStaffId()) : $this->getCurrentStaffId();

            $sql = "UPDATE lesson_plans SET status = 'approved', approved_by = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$approverId, $id]);

            $this->db->commit();
            $this->logAction('update', $id, "Approved lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan approved successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Reject a lesson plan (returns it to draft for revision).
     */
    public function rejectLessonPlan($id, $data = [])
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT id, status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            if (!in_array($plan['status'], ['draft', 'approved'])) {
                return errorResponse('Lesson plan cannot be rejected in its current state');
            }

            $sql = "UPDATE lesson_plans SET status = 'draft', approved_by = NULL WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $this->db->commit();
            $this->logAction('update', $id, "Rejected lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan rejected (returned to draft)'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Submit a draft lesson plan for review.
     */
    public function submitLessonPlan($id, $data = [])
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT id, status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            if (!in_array($plan['status'], ['draft'])) {
                return errorResponse('Only draft lesson plans can be submitted for review');
            }

            // The new schema has no 'submitted' status; drafts are the review queue.
            $this->db->commit();
            $this->logAction('update', $id, "Submitted lesson plan for review");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan submitted for review'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // TIMETABLE (CLASS SCHEDULES) CRUD
    // ========================================================================

    public function listClassSchedules($classId = null, $params = [])
    {
        try {
            $where = ["te.status = 'scheduled'"];
            $bindings = [];

            if ($classId !== null) {
                $where[] = "ayc.class_id = ?";
                $bindings[] = $classId;
            }

            if (!empty($params['teacher_id'])) {
                $where[] = "te.teacher_id = ?";
                $bindings[] = $params['teacher_id'];
            }

            if (!empty($params['day_of_week'])) {
                $where[] = "te.day_of_week = ?";
                $bindings[] = $this->normalizeDayOfWeek($params['day_of_week']);
            }

            if (!empty($params['stream_id'])) {
                $where[] = "aycs.stream_id = ?";
                $bindings[] = $params['stream_id'];
            }

            if (!empty($params['term_id'])) {
                $where[] = "te.academic_year_term_id = ?";
                $bindings[] = $params['term_id'];
            }

            if (!empty($params['academic_year_id'])) {
                $where[] = "ayt.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    te.id,
                    te.academic_year_class_stream_id,
                    aycs.stream_id,
                    st.name AS stream_name,
                    ayc.class_id,
                    c.name AS class_name,
                    te.academic_year_term_id AS term_id,
                    ayt.academic_year_id,
                    te.day_of_week,
                    ts.period_number,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_type,
                    te.time_slot_id,
                    te.learning_area_id AS subject_id,
                    COALESCE(la.name, '') AS subject_name,
                    COALESCE(la.name, '') AS learning_area_name,
                    te.teacher_id,
                    CONCAT(p.first_name, ' ', p.last_name) AS teacher_name,
                    aycs.room_id,
                    r.name AS room_name,
                    te.status,
                    te.created_at,
                    te.updated_at
                FROM timetable_entries te
                JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                JOIN academic_year_terms ayt ON ayt.id = te.academic_year_term_id
                JOIN time_slots ts ON ts.id = te.time_slot_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN learning_areas la ON la.id = te.learning_area_id
                LEFT JOIN rooms r ON r.id = aycs.room_id
                LEFT JOIN staff s ON te.teacher_id = s.id
                LEFT JOIN persons p ON p.id = s.person_id
                WHERE $whereClause
                ORDER BY 
                    FIELD(te.day_of_week, 1, 2, 3, 4, 5, 6, 7),
                    ts.period_number ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($schedules as &$schedule) {
                $schedule['day_name'] = $this->dayNameOf($schedule['day_of_week']);
            }

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getClassSchedule($id)
    {
        try {
            $sql = "
                SELECT 
                    te.id,
                    te.academic_year_class_stream_id,
                    aycs.stream_id,
                    st.name AS stream_name,
                    ayc.class_id,
                    c.name AS class_name,
                    te.academic_year_term_id AS term_id,
                    ayt.academic_year_id,
                    te.day_of_week,
                    ts.period_number,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_type,
                    te.time_slot_id,
                    te.learning_area_id AS subject_id,
                    COALESCE(la.name, '') AS subject_name,
                    COALESCE(la.name, '') AS learning_area_name,
                    te.teacher_id,
                    CONCAT(p.first_name, ' ', p.last_name) AS teacher_name,
                    aycs.room_id,
                    r.name AS room_name,
                    te.status,
                    te.created_at,
                    te.updated_at
                FROM timetable_entries te
                JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                JOIN academic_year_terms ayt ON ayt.id = te.academic_year_term_id
                JOIN time_slots ts ON ts.id = te.time_slot_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN learning_areas la ON la.id = te.learning_area_id
                LEFT JOIN rooms r ON r.id = aycs.room_id
                LEFT JOIN staff s ON te.teacher_id = s.id
                LEFT JOIN persons p ON p.id = s.person_id
                WHERE te.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse('Class schedule not found');
            }

            $schedule['day_name'] = $this->dayNameOf($schedule['day_of_week']);

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createClassSchedule($data)
    {
        try {
            $classId = $data['class_id'] ?? $data['grade_class_id'] ?? null;
            $streamId = $data['stream_id'] ?? null;
            $day = $this->normalizeDayOfWeek($data['day_of_week'] ?? null);

            if (!$classId || $day === null) {
                return errorResponse('class_id and day_of_week are required', 400);
            }

            $aycsId = $this->resolveAycsId(
                (int) $classId,
                $streamId !== null ? (int) $streamId : null,
                !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null
            );
            if (!$aycsId) {
                return errorResponse('No active academic year class stream found for the selected class', 400);
            }

            $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : 0;
            if (!$academicYearId) {
                $stmt = $this->db->prepare("
                    SELECT ayc.academic_year_id
                    FROM academic_year_class_streams aycs
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    WHERE aycs.id = ?
                ");
                $stmt->execute([$aycsId]);
                $academicYearId = (int) ($stmt->fetchColumn() ?: 0);
            }

            $termId = $data['term_id'] ?? $data['term'] ?? null;
            if (!$termId) {
                $termId = $this->resolveAyTermId($academicYearId);
            }
            if (!$termId) {
                return errorResponse('Unable to determine the academic year term', 400);
            }

            $timeSlotId = $data['time_slot_id'] ?? null;
            if (!$timeSlotId) {
                $timeSlotId = $this->resolveTimeSlotId(
                    $data['start_time'] ?? null,
                    $data['end_time'] ?? null,
                    isset($data['period_number']) ? (int) $data['period_number'] : null
                );
                if (!$timeSlotId) {
                    return errorResponse('No matching time slot found. Provide time_slot_id or a matching start_time/end_time', 400);
                }
            }

            $this->db->beginTransaction();

            // Check for conflicts
            $conflict = $this->checkScheduleConflict([
                'academic_year_class_stream_id' => $aycsId,
                'academic_year_term_id' => $termId,
                'day_of_week' => $day,
                'time_slot_id' => $timeSlotId,
                'teacher_id' => $data['teacher_id'] ?? null,
            ]);
            if ($conflict !== null) {
                $this->db->rollBack();
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Schedule conflict detected',
                    'conflict' => $conflict
                ], 409);
            }

            $sql = "
                INSERT INTO timetable_entries (
                    academic_year_class_stream_id,
                    academic_year_term_id,
                    day_of_week,
                    time_slot_id,
                    learning_area_id,
                    teacher_id,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $aycsId,
                $termId,
                $day,
                $timeSlotId,
                $data['subject_id'] ?? $data['learning_area_id'] ?? null,
                $data['teacher_id'] ?? null,
                $data['status'] ?? 'scheduled'
            ]);

            $scheduleId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $scheduleId, "Created class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function updateClassSchedule($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if schedule exists
            $stmt = $this->db->prepare("SELECT * FROM timetable_entries WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                return errorResponse('Class schedule not found');
            }

            $day = isset($data['day_of_week']) ? $this->normalizeDayOfWeek($data['day_of_week']) : (int) $existing['day_of_week'];

            $timeSlotChanged = isset($data['time_slot_id']) || isset($data['start_time']) || isset($data['end_time']) || isset($data['period_number']);
            $timeSlotId = $data['time_slot_id'] ?? null;
            if ($timeSlotChanged && !$timeSlotId) {
                $timeSlotId = $this->resolveTimeSlotId(
                    $data['start_time'] ?? null,
                    $data['end_time'] ?? null,
                    isset($data['period_number']) ? (int) $data['period_number'] : null
                );
                if (!$timeSlotId) {
                    $this->db->rollBack();
                    return errorResponse('No matching time slot found. Provide time_slot_id or a matching start_time/end_time', 400);
                }
            }

            $streamChanged = isset($data['class_id']) || isset($data['stream_id']);
            $aycsId = null;
            if ($streamChanged) {
                $classId = $data['class_id'] ?? null;
                $streamId = $data['stream_id'] ?? null;
                if ($classId) {
                    $aycsId = $this->resolveAycsId(
                        (int) $classId,
                        $streamId !== null ? (int) $streamId : null
                    );
                } elseif ($streamId) {
                    $stmt = $this->db->prepare("SELECT aycs.id FROM academic_year_class_streams aycs WHERE aycs.stream_id = ? ORDER BY aycs.id DESC LIMIT 1");
                    $stmt->execute([$streamId]);
                    $aycsId = (int) ($stmt->fetchColumn() ?: 0);
                }
                if (!$aycsId) {
                    $this->db->rollBack();
                    return errorResponse('No active academic year class stream found for the selected class', 400);
                }
            }

            $checkData = [
                'academic_year_class_stream_id' => $aycsId ?: (int) $existing['academic_year_class_stream_id'],
                'academic_year_term_id' => $data['term_id'] ?? $existing['academic_year_term_id'],
                'day_of_week' => $day,
                'time_slot_id' => $timeSlotChanged ? $timeSlotId : (int) $existing['time_slot_id'],
                'teacher_id' => $data['teacher_id'] ?? $existing['teacher_id'],
                'exclude_id' => $id,
            ];

            // Check for conflicts
            $conflict = $this->checkScheduleConflict($checkData);
            if ($conflict !== null) {
                $this->db->rollBack();
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Schedule conflict detected',
                    'conflict' => $conflict
                ], 409);
            }

            // Build update query
            $updates = [];
            $params = [];

            if ($streamChanged) {
                $updates[] = 'academic_year_class_stream_id = ?';
                $params[] = $aycsId;
            }
            if (isset($data['day_of_week'])) {
                $updates[] = 'day_of_week = ?';
                $params[] = $day;
            }
            if ($timeSlotChanged) {
                $updates[] = 'time_slot_id = ?';
                $params[] = $timeSlotId;
            }
            if (array_key_exists('teacher_id', $data)) {
                $updates[] = 'teacher_id = ?';
                $params[] = $data['teacher_id'];
            }
            if (array_key_exists('learning_area_id', $data)) {
                $updates[] = 'learning_area_id = ?';
                $params[] = $data['learning_area_id'];
            } elseif (array_key_exists('subject_id', $data)) {
                $updates[] = 'learning_area_id = ?';
                $params[] = $data['subject_id'];
            }
            if (array_key_exists('term_id', $data) || array_key_exists('term', $data)) {
                $updates[] = 'academic_year_term_id = ?';
                $params[] = $data['term_id'] ?? $data['term'];
            }
            if (array_key_exists('status', $data)) {
                $updates[] = 'status = ?';
                $params[] = $data['status'];
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE timetable_entries SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule updated successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function deleteClassSchedule($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE timetable_entries SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Class schedule not found');
            }

            $this->logAction('delete', $id, "Cancelled class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTeacherSchedule($teacherId)
    {
        try {
            $sql = "
                SELECT 
                    te.id,
                    te.academic_year_class_stream_id,
                    aycs.stream_id,
                    st.name AS stream_name,
                    ayc.class_id,
                    c.name AS class_name,
                    te.day_of_week,
                    ts.period_number,
                    ts.start_time,
                    ts.end_time,
                    te.learning_area_id AS subject_id,
                    COALESCE(la.name, '') AS subject_name,
                    te.teacher_id,
                    aycs.room_id,
                    r.name AS room_name,
                    te.status
                FROM timetable_entries te
                JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                JOIN time_slots ts ON ts.id = te.time_slot_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN learning_areas la ON la.id = te.learning_area_id
                LEFT JOIN rooms r ON r.id = aycs.room_id
                WHERE te.teacher_id = ? AND te.status = 'scheduled'
                ORDER BY 
                    FIELD(te.day_of_week, 1, 2, 3, 4, 5, 6, 7),
                    ts.period_number ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($schedules as &$schedule) {
                $schedule['day_name'] = $this->dayNameOf($schedule['day_of_week']);
            }

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function checkScheduleConflict($data)
    {
        try {
            $streamId = $data['academic_year_class_stream_id'] ?? null;
            $termId = $data['academic_year_term_id'] ?? $data['term_id'] ?? null;
            $day = $data['day_of_week'] ?? null;
            $timeSlotId = $data['time_slot_id'] ?? null;
            $teacherId = $data['teacher_id'] ?? null;
            $excludeId = $data['exclude_id'] ?? null;

            if ($day === null || !$timeSlotId) {
                return null;
            }

            // Check teacher conflict
            if (!empty($teacherId)) {
                $sql = "
                    SELECT id, academic_year_class_stream_id AS class_id
                    FROM timetable_entries
                    WHERE teacher_id = ?
                      AND day_of_week = ?
                      AND time_slot_id = ?
                      AND status = 'scheduled'
                ";

                $params = [$teacherId, $day, $timeSlotId];

                if (!empty($termId)) {
                    $sql .= " AND academic_year_term_id = ?";
                    $params[] = $termId;
                }

                if (!empty($excludeId)) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeId;
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    return ['type' => 'teacher', 'schedule_id' => $conflict['id']];
                }
            }

            // Check class stream conflict
            if (!empty($streamId)) {
                $sql = "
                    SELECT id
                    FROM timetable_entries
                    WHERE academic_year_class_stream_id = ?
                      AND day_of_week = ?
                      AND time_slot_id = ?
                      AND status = 'scheduled'
                ";

                $params = [$streamId, $day, $timeSlotId];

                if (!empty($termId)) {
                    $sql .= " AND academic_year_term_id = ?";
                    $params[] = $termId;
                }

                if (!empty($excludeId)) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeId;
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    return ['type' => 'stream', 'schedule_id' => $conflict['id']];
                }
            }

            return null; // No conflict
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Map a day-of-week value (numeric 1-7 or an English day name) to its numeric index.
     */
    private function normalizeDayOfWeek($day)
    {
        if ($day === null) {
            return null;
        }
        if (is_numeric($day)) {
            return (int) $day;
        }
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];
        $key = strtolower(trim((string) $day));
        return $days[$key] ?? null;
    }

    /**
     * Convert a numeric day-of-week index (1-7) to its English name.
     */
    private function dayNameOf($num)
    {
        $names = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];
        return $names[(int) $num] ?? null;
    }

    /**
     * Resolve the academic_year_class_streams row for a class (and optional stream) in an active year.
     */
    private function resolveAycsId($classId, $streamId = null, $academicYearId = null)
    {
        $sql = "
            SELECT aycs.id
            FROM academic_year_class_streams aycs
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE ayc.class_id = ? AND ayc.status = 'active'
        ";
        $params = [$classId];
        if ($streamId !== null) {
            $sql .= " AND aycs.stream_id = ?";
            $params[] = $streamId;
        }
        if ($academicYearId !== null) {
            $sql .= " AND ayc.academic_year_id = ?";
            $params[] = $academicYearId;
        }
        $sql .= " ORDER BY ayc.academic_year_id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve the time_slots row matching start/end time and optional period number.
     */
    private function resolveTimeSlotId($startTime, $endTime = null, $periodNumber = null)
    {
        if (!$startTime) {
            return 0;
        }
        $sql = "SELECT id FROM time_slots WHERE start_time = ? AND is_active = 1";
        $params = [$startTime];
        if ($endTime !== null) {
            $sql .= " AND end_time = ?";
            $params[] = $endTime;
        }
        if ($periodNumber !== null) {
            $sql .= " AND period_number = ?";
            $params[] = $periodNumber;
        }
        $sql .= " ORDER BY id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve the active/current academic year term for a given academic year.
     */
    private function resolveAyTermId($academicYearId)
    {
        if (!$academicYearId) {
            return 0;
        }
        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_terms
            WHERE academic_year_id = ?
            ORDER BY FIELD(status, 'current', 'upcoming', 'completed'), opening_date ASC
            LIMIT 1
        ");
        $stmt->execute([$academicYearId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    // ========================================================================
    // TEACHER ASSIGNMENT METHODS
    // ========================================================================

    public function getTeacherSubjects($teacherId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    te.learning_area_id as subject_id,
                    la.name as subject_name,
                    la.id as learning_area_id,
                    la.name as learning_area_name,
                    COUNT(DISTINCT ayc.class_id) as class_count
                FROM timetable_entries te
                LEFT JOIN learning_areas la ON te.learning_area_id = la.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE te.teacher_id = ? AND te.status = 'scheduled'
                GROUP BY te.learning_area_id, la.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($subjects);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSubjectTeachers($subjectId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    s.id as teacher_id,
                    CONCAT(p.first_name, ' ', p.last_name) as teacher_name,
                    p.email,
                    p.phone,
                    COUNT(DISTINCT ayc.class_id) as class_count
                FROM timetable_entries te
                JOIN staff s ON te.teacher_id = s.id
                LEFT JOIN persons p ON p.id = s.person_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE te.learning_area_id = ? AND te.status = 'scheduled'
                GROUP BY s.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($teachers);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // CLASS STREAMS METHODS
    // ========================================================================

    public function listClassStreams($classId = null, $params = [])
    {
        try {
            $where = ["aycs.status = 'active'"];
            $bindings = [];

            if (!empty($params['status'])) {
                $where = ["aycs.status = ?"];
                $bindings[] = $params['status'];
            }

            if (!empty($classId)) {
                $where[] = "ayc.class_id = ?";
                $bindings[] = (int) $classId;
            }

            if (!empty($params['class_id']) && empty($classId)) {
                $where[] = "ayc.class_id = ?";
                $bindings[] = (int) $params['class_id'];
            }

            if (!empty($params['academic_year_id'])) {
                $where[] = "ayc.academic_year_id = ?";
                $bindings[] = (int) $params['academic_year_id'];
            }

            if (!empty($params['teacher_id'])) {
                $where[] = "aycs.class_teacher_id = ?";
                $bindings[] = (int) $params['teacher_id'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    aycs.id as id,
                    aycs.academic_year_class_id,
                    ayc.class_id,
                    ayc.academic_year_id,
                    aycs.stream_id,
                    st.name AS stream_name,
                    st.code AS stream_code,
                    st.capacity,
                    aycs.room_id,
                    r.name AS room_name,
                    aycs.class_teacher_id AS teacher_id,
                    CONCAT(p.first_name, ' ', p.last_name) as teacher_name,
                    aycs.status,
                    c.name as class_name,
                    sl.name as level_name,
                    COUNT(DISTINCT sae.id) as student_count
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                JOIN streams st ON aycs.stream_id = st.id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN staff s ON aycs.class_teacher_id = s.id
                LEFT JOIN persons p ON p.id = s.person_id
                LEFT JOIN rooms r ON aycs.room_id = r.id
                LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active'
                WHERE {$whereClause}
                GROUP BY aycs.id
                ORDER BY ayc.academic_year_id DESC, c.name, st.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($streams);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignClassTeacher($streamId, $teacherId)
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE academic_year_class_streams SET class_teacher_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId, $streamId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return errorResponse('Class stream not found');
            }

            $this->db->commit();
            $this->logAction('update', $streamId, "Assigned teacher to class stream");

            return successResponse([
                'status' => 'success',
                'message' => 'Teacher assigned successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getTeacherClasses($teacherId)
    {
        try {
            // Get from academic_year_class_streams (class teacher)
            $sql1 = "
                SELECT DISTINCT
                    ayc.class_id,
                    c.name as class_name,
                    st.name AS stream_name,
                    'class_teacher' as role
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                JOIN streams st ON aycs.stream_id = st.id
                WHERE aycs.class_teacher_id = ? AND aycs.status = 'active'
            ";

            $stmt = $this->db->prepare($sql1);
            $stmt->execute([$teacherId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get from timetable_entries (subject teacher)
            $sql2 = "
                SELECT DISTINCT
                    ayc.class_id,
                    c.name as class_name,
                    st.name AS stream_name,
                    'subject_teacher' as role
                FROM timetable_entries te
                JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                JOIN streams st ON aycs.stream_id = st.id
                WHERE te.teacher_id = ? AND te.status = 'scheduled'
            ";

            $stmt = $this->db->prepare($sql2);
            $stmt->execute([$teacherId]);
            $subjectClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Merge results
            $allClasses = array_merge($classes, $subjectClasses);

            return successResponse($allClasses);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listTeachers($params = [])
    {
        try {
            $where = ["s.status = 'active'"];
            $bindings = [];

            if (!empty($params['search'])) {
                $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR s.staff_no LIKE ?)";
                $search = '%' . trim((string) $params['search']) . '%';
                $bindings[] = $search;
                $bindings[] = $search;
                $bindings[] = $search;
            }

            // Teaching staff + leadership that handles academic assignments
            $where[] = "(s.staff_type_id = 1 OR LOWER(s.position) REGEXP 'teacher|head|academic|deputy')";
            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT
                    s.id,
                    s.staff_no,
                    p.first_name,
                    p.last_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                    s.position,
                    s.staff_type_id,
                    st.name AS staff_type_name
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_types st ON st.id = s.staff_type_id
                WHERE {$whereClause}
                ORDER BY p.first_name, p.last_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            return successResponse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== CLASS MANAGEMENT ====================
    // 
    // IMPORTANT: This section leverages several database features:
    //
    // TRIGGERS:
    // - `trg_auto_create_default_stream`: Auto-creates default stream when class is created
    // - `trg_manage_default_stream_on_insert`: Auto-deactivates default stream when custom streams are added
    // - `trg_manage_default_stream_on_delete`: Auto-reactivates default stream when all custom streams are removed
    // - `trg_validate_class_capacity`: Validates that stream capacity is not exceeded when adding students
    //
    // VIEWS:
    // - `vw_active_students_per_class`: Aggregates active student counts per class/stream
    // - `vw_upcoming_class_schedules`: Shows upcoming class schedules (timetable)
    //
    // STORED PROCEDURES (available but not used in basic CRUD):
    // - `sp_generate_student_report`: Comprehensive student report generation
    //
    // EVENTS:
    // - Events emitted to `system_events` table for frontend real-time updates
    //
    // ==========================================================================

    /**
     * List all classes with optional filtering
     * Uses view `vw_active_students_per_class` for student counts
     */
    public function listClasses($params = [])
    {
        try {
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            $offset = ($page - 1) * $limit;

            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['level_id'])) {
                $where[] = 'c.level_id = ?';
                $bindings[] = $params['level_id'];
            }

            if (!empty($params['academic_year_id'])) {
                $where[] = 'ayc.academic_year_id = ?';
                $bindings[] = $params['academic_year_id'];
            } elseif (!empty($params['academic_year'])) {
                $where[] = 'ay.year_code = ?';
                $bindings[] = $params['academic_year'];
            }

            if (!empty($params['status'])) {
                $where[] = 'ayc.status = ?';
                $bindings[] = $params['status'];
            } else {
                $where[] = "ayc.status = 'active'";
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    c.id,
                    c.code,
                    c.name,
                    c.level_id,
                    c.grade_level,
                    sl.name as level_name,
                    sl.code as level_code,
                    ayc.id AS academic_year_class_id,
                    ayc.academic_year_id,
                    ay.year_code AS academic_year,
                    ayc.status,
                    GROUP_CONCAT(DISTINCT st.name ORDER BY st.name SEPARATOR ', ') AS stream_names,
                    COUNT(DISTINCT aycs.id) as stream_count,
                    COUNT(DISTINCT sae.id) as student_count
                FROM classes c
                JOIN academic_year_classes ayc ON ayc.class_id = c.id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id AND aycs.status = 'active'
                LEFT JOIN streams st ON aycs.stream_id = st.id
                LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active' AND sae.student_id IN (SELECT id FROM students)
                WHERE {$whereClause}
                GROUP BY c.id, ayc.id
                ORDER BY ayc.academic_year_id DESC, FIELD(c.grade_level, 'Playgroup', 'PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'), c.name
                LIMIT ? OFFSET ?
            ";

            $bindings[] = $limit;
            $bindings[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT c.id) as total
                FROM classes c
                JOIN academic_year_classes ayc ON ayc.class_id = c.id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                WHERE {$whereClause}
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute(array_slice($bindings, 0, count($bindings) - 2));
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Return just the data array - successResponse will wrap it properly
            return successResponse($classes);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stream-level class capacity for capacity dashboards.
     */
    public function getClassCapacity($params = [])
    {
        try {
            $where = ["aycs.status = 'active'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = 'ayc.class_id = ?';
                $bindings[] = (int) $params['class_id'];
            }

            if (!empty($params['academic_year_id'])) {
                $where[] = 'ayc.academic_year_id = ?';
                $bindings[] = (int) $params['academic_year_id'];
            } else {
                $where[] = "ay.is_current = 1";
            }

            $sql = "
                SELECT
                    ayc.class_id AS class_id,
                    c.name AS class_name,
                    aycs.id AS stream_id,
                    st.name AS stream_name,
                    st.capacity AS capacity,
                    COUNT(DISTINCT sae.student_id) AS enrolled,
                    COUNT(DISTINCT sae.student_id) AS student_count,
                    GREATEST(st.capacity - COUNT(DISTINCT sae.student_id), 0) AS available,
                    CASE
                        WHEN st.capacity > 0
                        THEN ROUND((COUNT(DISTINCT sae.student_id) / st.capacity) * 100)
                        ELSE 0
                    END AS utilization,
                    aycs.status,
                    sl.name AS level_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS teacher_name
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                JOIN classes c ON c.id = ayc.class_id
                JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN school_levels sl ON sl.id = c.level_id
                LEFT JOIN staff s ON s.id = aycs.class_teacher_id
                LEFT JOIN persons p ON p.id = s.person_id
                LEFT JOIN student_academic_enrollments sae
                       ON sae.academic_year_class_stream_id = aycs.id
                      AND sae.enrollment_status = 'active'
                      AND sae.student_id IN (SELECT id FROM students)
                WHERE " . implode(' AND ', $where) . "
                GROUP BY aycs.id
                ORDER BY ayc.academic_year_id DESC, sl.code, c.name, st.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($rows);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create an assessment record directly (non-workflow mode).
     */
    public function createAssessmentRecord($data = [])
    {
        try {
            $required = ['title', 'subject_id', 'class_id', 'term_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return errorResponse([
                        'status' => 'error',
                        'message' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $title = trim((string) $data['title']);
            $subjectId = (int) $data['subject_id'];
            $classId = (int) $data['class_id'];
            $termId = (int) $data['term_id'];
            $maxMarks = (float) ($data['max_marks'] ?? $data['total_marks'] ?? 100);
            if ($maxMarks <= 0) {
                $maxMarks = 100;
            }
            $assessmentDate = !empty($data['assessment_date']) ? $data['assessment_date'] : date('Y-m-d');
            $assignedBy = (int) ($data['assigned_by'] ?? $this->user_id ?? 1);
            $status = $data['status'] ?? 'pending_submission';

            $assessmentTypeId = null;
            if (!empty($data['assessment_type_id'])) {
                $assessmentTypeId = (int) $data['assessment_type_id'];
            } elseif (!empty($data['assessment_type'])) {
                $typeStmt = $this->db->prepare("
                    SELECT id
                    FROM assessment_types
                    WHERE LOWER(name) = LOWER(?)
                    LIMIT 1
                ");
                $typeStmt->execute([(string) $data['assessment_type']]);
                $typeId = $typeStmt->fetchColumn();
                $assessmentTypeId = $typeId ? (int) $typeId : null;
                if (empty($assessmentTypeId)) {
                    $lookupStmt = $this->db->query("SELECT id, name, is_formative, is_summative FROM assessment_types WHERE status='active'");
                    $types = $lookupStmt->fetchAll(PDO::FETCH_ASSOC);
                    $input = strtolower((string) $data['assessment_type']);
                    foreach ($types as $type) {
                        $name = strtolower((string) ($type['name'] ?? ''));
                        if ($name === $input) {
                            $assessmentTypeId = (int) $type['id'];
                            break;
                        }
                        if (
                            in_array($input, ['quiz', 'assignment', 'class_activity', 'practical', 'project', 'oral_test'], true)
                            && (int) ($type['is_formative'] ?? 0) === 1
                        ) {
                            $assessmentTypeId = (int) $type['id'];
                            break;
                        }
                        if (
                            in_array($input, ['midterm', 'endterm', 'mock'], true)
                            && (int) ($type['is_summative'] ?? 0) === 1
                        ) {
                            $assessmentTypeId = (int) $type['id'];
                            break;
                        }
                    }
                }
            }

            $classStreamId = $this->resolveAcademicYearClassStreamId($classId);

            $insert = $this->db->prepare("
                INSERT INTO assessments (
                    academic_year_class_stream_id,
                    learning_area_id,
                    academic_year_term_id,
                    title,
                    max_marks,
                    assessment_date,
                    assigned_by,
                    status,
                    assessment_type_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insert->execute([
                $classStreamId,
                $subjectId,
                $termId,
                $title,
                $maxMarks,
                $assessmentDate,
                $assignedBy,
                $status,
                $assessmentTypeId,
            ]);

            $assessmentId = (int) $this->db->lastInsertId();
            $this->logAction('create', $assessmentId, 'Created assessment record');

            return successResponse([
                'assessment_id' => $assessmentId,
                'instance_id' => null,
                'mode' => 'direct',
                'status' => $status,
            ], 'Assessment created successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Save assessment marks directly to assessment_results.
     */
    public function saveAssessmentResults($data = [])
    {
        try {
            $assessmentId = (int) ($data['assessment_id'] ?? 0);
            $marks = $data['marks'] ?? [];
            $isFinal = (bool) ($data['is_final'] ?? true);
            $responderId = (int) ($data['marked_by'] ?? $this->user_id ?? 1);

            if ($assessmentId <= 0) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'assessment_id is required'
                ], 400);
            }
            if (!is_array($marks) || empty($marks)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'marks array is required'
                ], 400);
            }

            $assessmentStmt = $this->db->prepare("SELECT id, max_marks FROM assessments WHERE id = ? LIMIT 1");
            $assessmentStmt->execute([$assessmentId]);
            $assessment = $assessmentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$assessment) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Assessment not found'
                ], 404);
            }
            $maxMarks = max(1.0, (float) ($assessment['max_marks'] ?? 100));

            $this->db->beginTransaction();

            $upsert = $this->db->prepare("
                INSERT INTO assessment_results (
                    assessment_id,
                    student_academic_enrollment_id,
                    marks_obtained,
                    grade,
                    points,
                    remarks,
                    submitted_at,
                    is_submitted,
                    is_approved,
                    responder_type,
                    responder_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'teacher', ?)
                ON DUPLICATE KEY UPDATE
                    marks_obtained = VALUES(marks_obtained),
                    grade = VALUES(grade),
                    points = VALUES(points),
                    remarks = VALUES(remarks),
                    submitted_at = VALUES(submitted_at),
                    is_submitted = VALUES(is_submitted),
                    responder_type = 'teacher',
                    responder_id = VALUES(responder_id)
            ");

            $saved = 0;
            foreach ($marks as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $enrollmentId = $this->resolveStudentEnrollmentId($studentId);
                if ($enrollmentId <= 0) {
                    continue;
                }

                $rawScore = $row['score_obtained'] ?? $row['marks_obtained'] ?? $row['marks'] ?? $row['score'] ?? null;
                if ($rawScore === null || $rawScore === '') {
                    continue;
                }

                $score = (float) $rawScore;
                if ($score < 0) {
                    $score = 0;
                }
                if ($score > $maxMarks) {
                    $score = $maxMarks;
                }

                $percentage = $maxMarks > 0 ? ($score / $maxMarks) * 100 : 0;
                $grade = $this->deriveGradeFromPercentage($percentage);
                $points = $grade === 'EE' ? 4.0 : ($grade === 'ME' ? 3.0 : ($grade === 'AE' ? 2.0 : 1.0));
                $submittedAt = $isFinal ? date('Y-m-d H:i:s') : null;
                $isSubmitted = $isFinal ? 1 : 0;

                $upsert->execute([
                    $assessmentId,
                    $enrollmentId,
                    $score,
                    $grade,
                    $points,
                    $row['remarks'] ?? '',
                    $submittedAt,
                    $isSubmitted,
                    0,
                    $responderId,
                ]);

                $saved++;
            }

            $assessmentStatus = $isFinal ? 'submitted' : 'pending_submission';
            $statusStmt = $this->db->prepare("UPDATE assessments SET status = ? WHERE id = ?");
            $statusStmt->execute([$assessmentStatus, $assessmentId]);

            $summaryStmt = $this->db->prepare("
                SELECT
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) AS submitted_rows
                FROM assessment_results
                WHERE assessment_id = ?
            ");
            $summaryStmt->execute([$assessmentId]);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_rows' => 0, 'submitted_rows' => 0];

            $this->db->commit();

            return successResponse([
                'assessment_id' => $assessmentId,
                'saved_count' => $saved,
                'summary' => [
                    'total_rows' => (int) ($summary['total_rows'] ?? 0),
                    'submitted_rows' => (int) ($summary['submitted_rows'] ?? 0),
                ],
                'status' => $assessmentStatus,
            ], $isFinal ? 'Assessment results submitted successfully' : 'Assessment draft saved successfully');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * List grading results for assessment/report pages.
     * Route: GET /api/academic/grading-results
     */
    /**
     * Resolve the current academic year id (is_current = 1, else latest).
     */
    private function resolveCurrentAcademicYearId()
    {
        static $cached = null;
        if ($cached === null) {
            $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1");
            $cached = (int) ($stmt->fetchColumn() ?: 0);
            if ($cached === 0) {
                $stmt = $this->db->query("SELECT MAX(id) FROM academic_years");
                $cached = (int) ($stmt->fetchColumn() ?: 0);
            }
        }
        return $cached;
    }

    /**
     * Resolve the academic_year_terms row id for an academic year + term number
     * (term numbers map to terms.code 'T1'..'Tn').
     */
    private function resolveAcademicYearTermId($academicYearId, $termNumber)
    {
        if (empty($academicYearId) || $termNumber === null || $termNumber === '') {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT ayt.id
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
               AND SUBSTRING(t.code, 2) = ?
             ORDER BY ayt.id DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $academicYearId, (string) $termNumber]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve an input class id to an academic_year_class_streams row id.
     * Accepts either a stream id (used directly) or a classes.id resolved
     * through the most recent academic year.
     */
    private function resolveAcademicYearClassStreamId($classId)
    {
        $classId = (int) $classId;
        if ($classId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_class_streams WHERE id = ? LIMIT 1");
        $stmt->execute([$classId]);
        if ($streamId = $stmt->fetchColumn()) {
            return (int) $streamId;
        }
        $stmt = $this->db->prepare("
            SELECT aycs.id
            FROM academic_year_classes ayc
            JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
            WHERE ayc.class_id = ?
            ORDER BY ayc.academic_year_id DESC, aycs.id
            LIMIT 1
        ");
        $stmt->execute([$classId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve a student id to the current student_academic_enrollments row id.
     */
    private function resolveStudentEnrollmentId($studentId)
    {
        $studentId = (int) $studentId;
        if ($studentId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("
            SELECT id
            FROM student_academic_enrollments
            WHERE student_id = ? AND enrollment_status IN ('active', 'pending')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Per-assessment grade rows for grade/marks entry flows (assessment_results).
     */
    private function getAssessmentGradingResults($assessmentId, $page, $limit)
    {
        $offset = ($page - 1) * $limit;

        $countSql = "SELECT COUNT(*) AS total FROM assessment_results ar WHERE ar.assessment_id = ?";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute([$assessmentId]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "
            SELECT
                s.id AS student_id,
                s.admission_no,
                p.first_name,
                p.middle_name,
                p.last_name,
                c.id AS class_id,
                c.name AS class_name,
                st.name AS stream_name,
                a.learning_area_id AS subject_id,
                COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject_name,
                ar.marks_obtained AS marks,
                ar.grade,
                ar.remarks,
                ar.submitted_at AS updated_at
            FROM assessment_results ar
            JOIN assessments a ON a.id = ar.assessment_id
            JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
            JOIN students s ON s.id = sae.student_id
            JOIN persons p ON p.id = s.person_id
            JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN streams st ON st.id = aycs.stream_id
            LEFT JOIN learning_areas la ON la.id = a.learning_area_id
            WHERE ar.assessment_id = ?
            ORDER BY p.first_name, p.last_name
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assessmentId, $limit, $offset]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return successResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ]
        ]);
    }

    public function getGradingResults($params = [])
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            // Grade/marks entry flows request per-assessment rows.
            if (!empty($params['assessment_id'])) {
                return $this->getAssessmentGradingResults((int) $params['assessment_id'], $page, $limit);
            }

            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = "(ayc.class_id = ? OR aycs.id = ?)";
                $cid = (int) $params['class_id'];
                array_push($bindings, $cid, $cid);
            }
            if (!empty($params['term_id'])) {
                $where[] = "tss.term_id = (SELECT term_id FROM academic_year_terms WHERE id = ?)";
                $bindings[] = (int) $params['term_id'];
            }
            if (!empty($params['subject_id'])) {
                $where[] = "tss.subject_id = ?";
                $bindings[] = (int) $params['subject_id'];
            }

            $whereClause = implode(' AND ', $where);
            $currentYearId = $this->resolveCurrentAcademicYearId();

            $countSql = "
                SELECT COUNT(*) AS total
                FROM term_subject_scores tss
                JOIN students s ON s.id = tss.student_id
                JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id AND ayt.academic_year_id = {$currentYearId}
                JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.academic_year_id = {$currentYearId} AND sae.enrollment_status IN ('pending', 'active')
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                WHERE {$whereClause}
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($bindings);
            $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    c.id AS class_id,
                    c.name AS class_name,
                    st.name AS stream_name,
                    tss.subject_id,
                    COALESCE(la.name, CONCAT('Subject ', tss.subject_id)) AS subject_name,
                    ROUND(tss.formative_percentage, 2) AS formative_pct,
                    ROUND(tss.summative_percentage, 2) AS summative_pct,
                    ROUND(tss.overall_percentage, 2) AS overall_pct,
                    UPPER(LEFT(COALESCE(tss.overall_grade, ''), 2)) AS cbc_grade
                FROM term_subject_scores tss
                JOIN students s ON s.id = tss.student_id
                JOIN persons p ON p.id = s.person_id
                JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id AND ayt.academic_year_id = {$currentYearId}
                JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.academic_year_id = {$currentYearId} AND sae.enrollment_status IN ('pending', 'active')
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN learning_areas la ON la.id = tss.subject_id
                WHERE {$whereClause}
                ORDER BY c.name, p.first_name, p.last_name, subject_name
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback when term_subject_scores is not populated yet.
            if (empty($items)) {
                $fallbackWhere = ["1=1"];
                $fallbackBindings = [];

                if (!empty($params['class_id'])) {
                    $fallbackWhere[] = "(ayc.class_id = ? OR aycs.id = ?)";
                    $cid = (int) $params['class_id'];
                    array_push($fallbackBindings, $cid, $cid);
                }
                if (!empty($params['term_id'])) {
                    $fallbackWhere[] = "a.academic_year_term_id = ?";
                    $fallbackBindings[] = (int) $params['term_id'];
                }
                if (!empty($params['subject_id'])) {
                    $fallbackWhere[] = "a.learning_area_id = ?";
                    $fallbackBindings[] = (int) $params['subject_id'];
                }

                $fallbackWhereClause = implode(' AND ', $fallbackWhere);

                $fallbackSql = "
                    SELECT
                        s.id AS student_id,
                        s.admission_no,
                        p.first_name,
                        p.middle_name,
                        p.last_name,
                        c.id AS class_id,
                        c.name AS class_name,
                        st.name AS stream_name,
                        a.learning_area_id AS subject_id,
                        COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject_name,
                        NULL AS formative_pct,
                        NULL AS summative_pct,
                        ROUND(AVG(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END), 2) AS overall_pct,
                        UPPER(LEFT(COALESCE(ar.grade, ''), 2)) AS cbc_grade
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    JOIN students s ON s.id = sae.student_id
                    JOIN persons p ON p.id = s.person_id
                    JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                    WHERE {$fallbackWhereClause}
                    GROUP BY s.id, a.learning_area_id
                    ORDER BY c.name, p.first_name, p.last_name, subject_name
                    LIMIT ? OFFSET ?
                ";
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute(array_merge($fallbackBindings, [$limit, $offset]));
                $items = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);

                $fallbackCountSql = "
                    SELECT COUNT(DISTINCT s.id, a.learning_area_id) AS total
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    JOIN students s ON s.id = sae.student_id
                    JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    WHERE {$fallbackWhereClause}
                ";
                $fallbackCountStmt = $this->db->prepare($fallbackCountSql);
                $fallbackCountStmt->execute($fallbackBindings);
                $total = (int) ($fallbackCountStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            }

            return successResponse([
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Aggregate class + subject analysis for assessment reporting.
     * Route: GET /api/academic/results-analysis
     */
    public function getResultsAnalysis($params = [])
    {
        try {
            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['term_id'])) {
                $where[] = "tss.term_id = (SELECT term_id FROM academic_year_terms WHERE id = ?)";
                $bindings[] = (int) $params['term_id'];
            }
            if (!empty($params['class_id'])) {
                $where[] = "(ayc.class_id = ? OR aycs.id = ?)";
                $cid = (int) $params['class_id'];
                array_push($bindings, $cid, $cid);
            }
            if (!empty($params['subject_id'])) {
                $where[] = "tss.subject_id = ?";
                $bindings[] = (int) $params['subject_id'];
            }

            $whereClause = implode(' AND ', $where);
            $currentYearId = $this->resolveCurrentAcademicYearId();

            $scoreJoins = "
                JOIN students s ON s.id = tss.student_id
                JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id AND ayt.academic_year_id = {$currentYearId}
                JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.academic_year_id = {$currentYearId} AND sae.enrollment_status IN ('pending', 'active')
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN school_levels sl ON sl.id = c.level_id
            ";

            $classSql = "
                SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    sl.name AS level_name,
                    COUNT(DISTINCT tss.student_id) AS students_assessed,
                    ROUND(AVG(tss.overall_percentage), 2) AS average_overall,
                    SUM(CASE WHEN tss.overall_percentage >= 80 THEN 1 ELSE 0 END) AS ee_count,
                    SUM(CASE WHEN tss.overall_percentage >= 50 AND tss.overall_percentage < 80 THEN 1 ELSE 0 END) AS me_count,
                    SUM(CASE WHEN tss.overall_percentage >= 25 AND tss.overall_percentage < 50 THEN 1 ELSE 0 END) AS ae_count,
                    SUM(CASE WHEN tss.overall_percentage < 25 THEN 1 ELSE 0 END) AS be_count,
                    ROUND(
                        (SUM(CASE WHEN tss.overall_percentage >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(tss.id), 0)) * 100,
                        2
                    ) AS pass_rate
                FROM term_subject_scores tss
                {$scoreJoins}
                WHERE {$whereClause}
                GROUP BY c.id, c.name, sl.name
                ORDER BY c.name
            ";
            $classStmt = $this->db->prepare($classSql);
            $classStmt->execute($bindings);
            $classMetrics = $classStmt->fetchAll(PDO::FETCH_ASSOC);

            $subjectSql = "
                SELECT
                    tss.subject_id,
                    COALESCE(la.name, CONCAT('Subject ', tss.subject_id)) AS subject_name,
                    GROUP_CONCAT(DISTINCT sl.name ORDER BY sl.name SEPARATOR ', ') AS level_name,
                    COUNT(DISTINCT tss.student_id) AS students_assessed,
                    ROUND(AVG(tss.formative_percentage), 2) AS avg_formative_pct,
                    ROUND(AVG(tss.summative_percentage), 2) AS avg_summative_pct,
                    ROUND(AVG(tss.overall_percentage), 2) AS avg_overall_pct,
                    SUM(CASE WHEN tss.overall_percentage >= 80 THEN 1 ELSE 0 END) AS ee_count,
                    SUM(CASE WHEN tss.overall_percentage >= 50 AND tss.overall_percentage < 80 THEN 1 ELSE 0 END) AS me_count,
                    SUM(CASE WHEN tss.overall_percentage >= 25 AND tss.overall_percentage < 50 THEN 1 ELSE 0 END) AS ae_count,
                    SUM(CASE WHEN tss.overall_percentage < 25 THEN 1 ELSE 0 END) AS be_count,
                    ROUND(
                        (SUM(CASE WHEN tss.overall_percentage >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(tss.id), 0)) * 100,
                        2
                    ) AS pass_rate
                FROM term_subject_scores tss
                {$scoreJoins}
                LEFT JOIN learning_areas la ON la.id = tss.subject_id
                WHERE {$whereClause}
                GROUP BY tss.subject_id, subject_name
                ORDER BY subject_name
            ";
            $subjectStmt = $this->db->prepare($subjectSql);
            $subjectStmt->execute($bindings);
            $subjectMetrics = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

            $source = 'term_subject_scores';

            // Fallback to assessment_results if rollup table has no records.
            if (empty($classMetrics) && empty($subjectMetrics)) {
                $source = 'assessment_results';
                $fallbackWhere = ["1=1"];
                $fallbackBindings = [];

                if (!empty($params['term_id'])) {
                    $fallbackWhere[] = "a.academic_year_term_id = ?";
                    $fallbackBindings[] = (int) $params['term_id'];
                }
                if (!empty($params['class_id'])) {
                    $fallbackWhere[] = "(ayc.class_id = ? OR aycs.id = ?)";
                    $cid = (int) $params['class_id'];
                    array_push($fallbackBindings, $cid, $cid);
                }
                if (!empty($params['subject_id'])) {
                    $fallbackWhere[] = "a.learning_area_id = ?";
                    $fallbackBindings[] = (int) $params['subject_id'];
                }
                $fallbackWhereClause = implode(' AND ', $fallbackWhere);

                $resultJoins = "
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    JOIN students s ON s.id = sae.student_id
                    JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN school_levels sl ON sl.id = c.level_id
                ";

                $classFallbackSql = "
                    SELECT
                        c.id AS class_id,
                        c.name AS class_name,
                        sl.name AS level_name,
                        COUNT(DISTINCT ar.student_academic_enrollment_id) AS students_assessed,
                        ROUND(AVG(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END), 2) AS average_overall,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 80) THEN 1 ELSE 0 END) AS ee_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50 AND (ar.marks_obtained / a.max_marks) * 100 < 80) THEN 1 ELSE 0 END) AS me_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 25 AND (ar.marks_obtained / a.max_marks) * 100 < 50) THEN 1 ELSE 0 END) AS ae_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 < 25) THEN 1 ELSE 0 END) AS be_count,
                        ROUND(
                            (SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50) THEN 1 ELSE 0 END) / NULLIF(COUNT(ar.id), 0)) * 100,
                            2
                        ) AS pass_rate
                    FROM assessment_results ar
                    {$resultJoins}
                    WHERE {$fallbackWhereClause}
                    GROUP BY c.id, c.name, sl.name
                    ORDER BY c.name
                ";
                $classFallbackStmt = $this->db->prepare($classFallbackSql);
                $classFallbackStmt->execute($fallbackBindings);
                $classMetrics = $classFallbackStmt->fetchAll(PDO::FETCH_ASSOC);

                $subjectFallbackSql = "
                    SELECT
                        a.learning_area_id AS subject_id,
                        COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject_name,
                        GROUP_CONCAT(DISTINCT sl.name ORDER BY sl.name SEPARATOR ', ') AS level_name,
                        COUNT(DISTINCT ar.student_academic_enrollment_id) AS students_assessed,
                        ROUND(AVG(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END), 2) AS avg_formative_pct,
                        NULL AS avg_summative_pct,
                        ROUND(AVG(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END), 2) AS avg_overall_pct,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 80) THEN 1 ELSE 0 END) AS ee_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50 AND (ar.marks_obtained / a.max_marks) * 100 < 80) THEN 1 ELSE 0 END) AS me_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 25 AND (ar.marks_obtained / a.max_marks) * 100 < 50) THEN 1 ELSE 0 END) AS ae_count,
                        SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 < 25) THEN 1 ELSE 0 END) AS be_count,
                        ROUND(
                            (SUM(CASE WHEN (a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50) THEN 1 ELSE 0 END) / NULLIF(COUNT(ar.id), 0)) * 100,
                            2
                        ) AS pass_rate
                    FROM assessment_results ar
                    {$resultJoins}
                    LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                    WHERE {$fallbackWhereClause}
                    GROUP BY a.learning_area_id, subject_name
                    ORDER BY subject_name
                ";
                $subjectFallbackStmt = $this->db->prepare($subjectFallbackSql);
                $subjectFallbackStmt->execute($fallbackBindings);
                $subjectMetrics = $subjectFallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return successResponse([
                'source' => $source,
                'class_metrics' => $classMetrics,
                'subject_metrics' => $subjectMetrics,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * List assessments with class/term/subject context and submission metrics.
     * Route: GET /api/academic/assessments-list
     */
    public function getAssessmentsList($params = [])
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = "(ayc.class_id = ? OR aycs.id = ?)";
                $cid = (int) $params['class_id'];
                array_push($bindings, $cid, $cid);
            }

            $termId = !empty($params['term_id'])
                ? (int) $params['term_id']
                : (!empty($params['term']) && ctype_digit((string) $params['term']) ? (int) $params['term'] : null);
            if ($termId !== null) {
                $where[] = "a.academic_year_term_id = ?";
                $bindings[] = $termId;
            }

            if (!empty($params['subject_id'])) {
                $where[] = "a.learning_area_id = ?";
                $bindings[] = (int) $params['subject_id'];
            }

            if (!empty($params['status'])) {
                $where[] = "a.status = ?";
                $bindings[] = $params['status'];
            }

            if (!empty($params['assessment_type_id'])) {
                $where[] = "a.assessment_type_id = ?";
                $bindings[] = (int) $params['assessment_type_id'];
            }

            $whereClause = implode(' AND ', $where);

            $countSql = "
                SELECT COUNT(*) AS total
                FROM assessments a
                JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                WHERE {$whereClause}
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($bindings);
            $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            $sql = "
                SELECT
                    a.id,
                    a.academic_year_class_stream_id,
                    a.academic_year_term_id,
                    a.learning_area_id AS subject_id,
                    ayc.class_id,
                    a.title,
                    a.max_marks,
                    a.assessment_date,
                    a.status,
                    a.assessment_type_id,
                    c.name AS class_name,
                    st.name AS stream_name,
                    COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject_name,
                    COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS learning_area_name,
                    t.name AS term_name,
                    t.code AS term_code,
                    SUBSTRING(t.code, 2) AS term_number,
                    atp.name AS assessment_type,
                    COUNT(DISTINCT CASE WHEN ar.is_submitted = 1 THEN ar.id END) AS graded_count,
                    COUNT(DISTINCT CASE WHEN ar.is_submitted = 1 THEN ar.id END) AS submitted_count,
                    COUNT(DISTINCT sae.id) AS total_students,
                    ROUND(
                        AVG(
                            CASE
                                WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100
                                ELSE NULL
                            END
                        ),
                        2
                    ) AS average_percentage
                FROM assessments a
                JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                LEFT JOIN terms t ON t.id = ayt.term_id
                LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                LEFT JOIN assessment_types atp ON atp.id = a.assessment_type_id
                LEFT JOIN assessment_results ar ON ar.assessment_id = a.id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.academic_year_class_stream_id = a.academic_year_class_stream_id
                   AND sae.enrollment_status IN ('pending', 'active')
                WHERE {$whereClause}
                GROUP BY a.id
                ORDER BY a.assessment_date DESC, a.id DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get subject-level results for a student in a term.
     * Route: GET /api/academic/student-results?student_id={id}&term_id={id}
     */
    public function getStudentResults($params = [])
    {
        try {
            $studentId = (int) ($params['student_id'] ?? 0);
            if ($studentId <= 0) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'student_id is required'
                ], 400);
            }

            $termId = isset($params['term_id']) && $params['term_id'] !== ''
                ? (int) $params['term_id']
                : null;

            $yearId = $this->resolveCurrentAcademicYearId();
            if ($termId !== null) {
                $yearStmt = $this->db->prepare("SELECT academic_year_id FROM academic_year_terms WHERE id = ? LIMIT 1");
                $yearStmt->execute([$termId]);
                $resolvedYear = (int) ($yearStmt->fetchColumn() ?: 0);
                if ($resolvedYear > 0) {
                    $yearId = $resolvedYear;
                }
            }
            $yearValue = null;
            if ($yearId > 0) {
                $yearValueStmt = $this->db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
                $yearValueStmt->execute([$yearId]);
                $yearValue = (int) ($yearValueStmt->fetchColumn() ?: 0);
            }

            $termNumber = null;
            if ($termId !== null) {
                $termStmt = $this->db->prepare(
                    "SELECT SUBSTRING(t.code, 2) AS term_number
                     FROM academic_year_terms ayt
                     LEFT JOIN terms t ON t.id = ayt.term_id
                     WHERE ayt.id = ? LIMIT 1"
                );
                $termStmt->execute([$termId]);
                $termNumber = (int) ($termStmt->fetchColumn() ?: 0);
            }

            $attendanceJoin = "";
            $attendanceBindings = [];
            if ($termNumber !== null) {
                $attendanceJoin = "
                    LEFT JOIN (
                        SELECT
                            student_id,
                            MAX(attendance_rate_pct) AS attendance_percentage,
                            MAX(present_marks) AS days_present,
                            MAX(days_marked - present_marks) AS days_absent
                        FROM vw_student_attendance_analytics
                        WHERE academic_year = ?
                          AND term_number = ?
                        GROUP BY student_id
                    ) att ON att.student_id = s.id
                ";
                array_push($attendanceBindings, $yearValue, $termNumber);
            } else {
                $attendanceJoin = "
                    LEFT JOIN (
                        SELECT
                            student_id,
                            ROUND(SUM(present_marks) * 100.0 / NULLIF(SUM(days_marked), 0), 2) AS attendance_percentage,
                            SUM(present_marks) AS days_present,
                            SUM(days_marked - present_marks) AS days_absent
                        FROM vw_student_attendance_analytics
                        WHERE academic_year = ?
                        GROUP BY student_id
                    ) att ON att.student_id = s.id
                ";
                $attendanceBindings[] = $yearValue;
            }

            $studentSql = "
                SELECT
                    s.id,
                    s.admission_no,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    p.gender,
                    p.photo_url,
                    st.name AS stream_name,
                    c.name AS class_name,
                    ans.term1_score AS term1_average,
                    ans.term2_score AS term2_average,
                    ans.term3_score AS term3_average,
                    ans.annual_score AS year_average,
                    ans.annual_grade AS overall_grade,
                    ans.annual_rank AS class_rank,
                    NULL AS stream_rank,
                    att.attendance_percentage,
                    att.days_present,
                    att.days_absent
                FROM students s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id
                   AND sae.academic_year_id = {$yearId}
                   AND sae.enrollment_status IN ('pending', 'active')
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN annual_scores ans
                    ON ans.student_id = s.id
                   AND ans.academic_year = ?
                {$attendanceJoin}
                WHERE s.id = ?
                LIMIT 1
            ";
            $studentBindings = array_merge([$yearValue], $attendanceBindings, [$studentId]);
            $studentStmt = $this->db->prepare($studentSql);
            $studentStmt->execute($studentBindings);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Student not found'
                ], 404);
            }

            $subjects = [];
            $classAverageJoin = '';
            $classAverageBindings = [];
            if ($termId !== null) {
                $classAverageJoin = "
                    LEFT JOIN (
                        SELECT
                            t2.subject_id,
                            sae2.academic_year_class_stream_id AS aycs_id,
                            ROUND(AVG(t2.overall_percentage), 2) AS class_average
                        FROM term_subject_scores t2
                        JOIN academic_year_terms ayt2
                            ON ayt2.term_id = t2.term_id AND ayt2.academic_year_id = {$yearId}
                        JOIN student_academic_enrollments sae2
                            ON sae2.student_id = t2.student_id AND sae2.academic_year_id = {$yearId}
                        WHERE t2.term_id = (SELECT term_id FROM academic_year_terms WHERE id = ?)
                        GROUP BY t2.subject_id, sae2.academic_year_class_stream_id
                    ) class_subject_avg
                        ON class_subject_avg.subject_id = tss.subject_id
                       AND class_subject_avg.aycs_id = sae.academic_year_class_stream_id
                ";
                $classAverageBindings[] = $termId;
            }

            if ($termId !== null) {
                $scoresSql = "
                    SELECT
                        tss.subject_id,
                        COALESCE(la.name, CONCAT('Subject ', tss.subject_id)) AS subject_name,
                        ROUND(tss.formative_percentage, 2) AS formative_percentage,
                        ROUND(tss.summative_percentage, 2) AS summative_percentage,
                        ROUND(tss.overall_percentage, 2) AS percentage,
                        ROUND(tss.overall_score, 2) AS score,
                        tss.overall_grade AS grade,
                        tss.assessment_count,
                        class_subject_avg.class_average
                    FROM term_subject_scores tss
                    LEFT JOIN learning_areas la ON la.id = tss.subject_id
                    JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id AND ayt.academic_year_id = {$yearId}
                    JOIN student_academic_enrollments sae
                        ON sae.student_id = tss.student_id AND sae.academic_year_id = {$yearId}
                    {$classAverageJoin}
                    WHERE tss.student_id = ?
                      AND tss.term_id = (SELECT term_id FROM academic_year_terms WHERE id = ?)
                    ORDER BY subject_name ASC
                ";
                $scoresStmt = $this->db->prepare($scoresSql);
                $scoresStmt->execute(array_merge($classAverageBindings, [$studentId, $termId]));
                $subjects = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Fallback: derive per-subject percentages from assessment_results if term rollups are unavailable.
            if (empty($subjects)) {
                $fallbackSql = "
                    SELECT
                        a.learning_area_id AS subject_id,
                        COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject_name,
                        NULL AS formative_percentage,
                        NULL AS summative_percentage,
                        ROUND(
                            AVG(
                                CASE
                                    WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100
                                    ELSE NULL
                                END
                            ),
                            2
                        ) AS percentage,
                        ROUND(AVG(ar.marks_obtained), 2) AS score,
                        COUNT(ar.id) AS assessment_count,
                        NULL AS class_average
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                    WHERE sae.student_id = ?
                      AND sae.academic_year_id = ?
                ";
                $fallbackBindings = [$studentId, $yearId];
                if ($termId !== null) {
                    $fallbackSql .= " AND a.academic_year_term_id = ?";
                    $fallbackBindings[] = $termId;
                }
                $fallbackSql .= " GROUP BY a.learning_area_id ORDER BY subject_name ASC";

                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute($fallbackBindings);
                $subjects = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $subjectPercentages = [];
            foreach ($subjects as $row) {
                if (isset($row['percentage']) && $row['percentage'] !== null) {
                    $subjectPercentages[] = (float) $row['percentage'];
                }
            }

            $derivedAverage = !empty($subjectPercentages)
                ? round(array_sum($subjectPercentages) / count($subjectPercentages), 2)
                : null;

            $termAverage = null;
            if ($termNumber === 1 && $student['term1_average'] !== null) {
                $termAverage = (float) $student['term1_average'];
            } elseif ($termNumber === 2 && $student['term2_average'] !== null) {
                $termAverage = (float) $student['term2_average'];
            } elseif ($termNumber === 3 && $student['term3_average'] !== null) {
                $termAverage = (float) $student['term3_average'];
            }

            $overallPercentage = $termAverage
                ?? ($student['year_average'] !== null ? (float) $student['year_average'] : null)
                ?? $derivedAverage;
            $overallGrade = $student['overall_grade'] ?? $this->deriveGradeFromPercentage($overallPercentage);

            return successResponse([
                'student' => $student,
                'subjects' => $subjects,
                'summary' => [
                    'percentage' => $overallPercentage,
                    'grade' => $overallGrade,
                    'class_rank' => $student['class_rank'] !== null ? (int) $student['class_rank'] : null,
                    'stream_rank' => $student['stream_rank'] !== null ? (int) $student['stream_rank'] : null,
                    'attendance_percentage' => $student['attendance_percentage'] !== null ? (float) $student['attendance_percentage'] : null,
                    'days_present' => isset($student['days_present']) ? (int) $student['days_present'] : null,
                    'days_absent' => isset($student['days_absent']) ? (int) $student['days_absent'] : null
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get overview rows for student performance screens.
     * Supports students, class, stream, and school view modes.
     * Route: GET /api/academic/performance-overview
     */
    public function getPerformanceOverview($params = [])
    {
        try {
            $viewMode = strtolower((string) ($params['view_mode'] ?? 'students'));
            if (!in_array($viewMode, ['students', 'class', 'stream', 'school'], true)) {
                $viewMode = 'students';
            }

            $page = max(1, (int) ($params['page'] ?? 1));
            $limit = max(1, min(500, (int) ($params['limit'] ?? 250)));
            $offset = ($page - 1) * $limit;

            $yearId = null;
            foreach (['academic_year_id', 'academic_year', 'year_id'] as $key) {
                if (!empty($params[$key]) && ctype_digit((string) $params[$key])) {
                    $yearId = (int) $params[$key];
                    break;
                }
            }

            if ($yearId === null) {
                $yearStmt = $this->db->prepare(
                    "SELECT id
                     FROM academic_years
                     WHERE is_current = 1 OR status = 'active'
                     ORDER BY is_current DESC, start_date DESC, id DESC
                     LIMIT 1"
                );
                $yearStmt->execute();
                $resolvedYear = $yearStmt->fetchColumn();
                $yearId = $resolvedYear ? (int) $resolvedYear : null;
            }

            $termId = !empty($params['term_id']) && ctype_digit((string) $params['term_id'])
                ? (int) $params['term_id']
                : null;

            $yearValue = null;
            if ($yearId !== null && $yearId > 0) {
                $yearValueStmt = $this->db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
                $yearValueStmt->execute([$yearId]);
                $yearValue = (int) ($yearValueStmt->fetchColumn() ?: 0);
            }

            $termNumber = null;
            if ($termId !== null) {
                $termStmt = $this->db->prepare(
                    "SELECT SUBSTRING(t.code, 2) AS term_number
                     FROM academic_year_terms ayt
                     LEFT JOIN terms t ON t.id = ayt.term_id
                     WHERE ayt.id = ? LIMIT 1"
                );
                $termStmt->execute([$termId]);
                $termNumber = (int) ($termStmt->fetchColumn() ?: 0);
            }

            $termJoin = '';
            $termBindings = [];
            $termAverageExpr = 'NULL';
            $termGradeExpr = 'NULL';
            if ($termId !== null) {
                $termJoin = "
                    LEFT JOIN (
                        SELECT
                            t2.student_id,
                            ROUND(AVG(t2.overall_percentage), 2) AS term_average,
                            MAX(t2.overall_grade) AS term_grade
                        FROM term_subject_scores t2
                        JOIN academic_year_terms ayt2
                            ON ayt2.term_id = t2.term_id AND ayt2.academic_year_id = {$yearId}
                        WHERE ayt2.id = ?
                        GROUP BY t2.student_id
                    ) term_scores ON term_scores.student_id = s.id
                ";
                $termBindings[] = $termId;
                $termAverageExpr = 'term_scores.term_average';
                $termGradeExpr = 'term_scores.term_grade';
            }

            $feeJoin = "
                LEFT JOIN (
                    SELECT
                        student_id,
                        COALESCE(SUM(balance), 0) AS balance
                    FROM vw_student_fee_balances
                    WHERE academic_year_id = ?
                    GROUP BY student_id
                ) fee_summary ON fee_summary.student_id = s.id
            ";
            $feeBindings = [$yearId];

            $attendanceJoin = '';
            $attendanceBindings = [];
            if ($termNumber !== null) {
                $attendanceJoin = "
                    LEFT JOIN (
                        SELECT
                            student_id,
                            MAX(attendance_rate_pct) AS attendance_rate,
                            MAX(present_marks) AS days_present,
                            MAX(days_marked - present_marks) AS days_absent
                        FROM vw_student_attendance_analytics
                        WHERE academic_year = ?
                          AND term_number = ?
                        GROUP BY student_id
                    ) att ON att.student_id = s.id
                ";
                array_push($attendanceBindings, $yearValue, $termNumber);
            } else {
                $attendanceJoin = "
                    LEFT JOIN (
                        SELECT
                            student_id,
                            ROUND(SUM(present_marks) * 100.0 / NULLIF(SUM(days_marked), 0), 2) AS attendance_rate,
                            SUM(present_marks) AS days_present,
                            SUM(days_marked - present_marks) AS days_absent
                        FROM vw_student_attendance_analytics
                        WHERE academic_year = ?
                        GROUP BY student_id
                    ) att ON att.student_id = s.id
                ";
                $attendanceBindings[] = $yearValue;
            }

            $search = trim((string) ($params['search'] ?? ''));
            $gender = trim((string) ($params['gender'] ?? ''));

            $baseWhere = ["s.status = 'active'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $baseWhere[] = "(ayc.class_id = ? OR aycs.id = ?)";
                $cid = (int) $params['class_id'];
                array_push($bindings, $cid, $cid);
            }

            if (!empty($params['stream_id'])) {
                $baseWhere[] = 'aycs.stream_id = ?';
                $bindings[] = (int) $params['stream_id'];
            }

            if ($gender !== '') {
                $baseWhere[] = 'p.gender = ?';
                $bindings[] = $gender;
            }

            if ($search !== '') {
                $baseWhere[] = "(s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term);
            }

            $whereClause = 'WHERE ' . implode(' AND ', $baseWhere);

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                    p.gender,
                    p.photo_url,
                    c.id AS class_id,
                    c.name AS class_name,
                    st.id AS stream_id,
                    st.name AS stream_name,
                    COALESCE({$termAverageExpr}, ans.annual_score) AS average_score,
                    COALESCE({$termGradeExpr}, ans.annual_grade) AS grade,
                    COALESCE(att.attendance_rate, 0) AS attendance_rate,
                    ans.annual_rank AS position,
                    COALESCE(fee_summary.balance, 0) AS fee_balance,
                    COALESCE(att.days_present, 0) AS days_present,
                    COALESCE(att.days_absent, 0) AS days_absent
                FROM students s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN (
                    SELECT sae2.*
                    FROM student_academic_enrollments sae2
                    JOIN (
                        SELECT student_id, MAX(id) AS mid
                        FROM student_academic_enrollments
                        WHERE academic_year_id = {$yearId}
                          AND enrollment_status IN ('pending', 'active')
                        GROUP BY student_id
                    ) latest ON latest.mid = sae2.id
                ) sae ON sae.student_id = s.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN annual_scores ans
                    ON ans.student_id = s.id
                   AND ans.academic_year = ?
                {$attendanceJoin}
                {$termJoin}
                {$feeJoin}
                {$whereClause}
                ORDER BY c.name ASC, st.name ASC, p.last_name ASC, p.first_name ASC
                LIMIT ? OFFSET ?
            ";

            $queryBindings = array_merge($attendanceBindings, $termBindings, [$yearValue], $feeBindings, $bindings, [$limit, $offset]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($queryBindings);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $rows = [];
            if ($viewMode === 'students') {
                foreach ($students as $student) {
                    $average = isset($student['average_score']) && $student['average_score'] !== null
                        ? round((float) $student['average_score'], 2)
                        : null;

                    $rows[] = [
                        'student_id' => (int) $student['student_id'],
                        'admission_no' => $student['admission_no'],
                        'full_name' => $student['full_name'],
                        'first_name' => $student['first_name'],
                        'last_name' => $student['last_name'],
                        'gender' => $student['gender'],
                        'class_id' => $student['class_id'],
                        'class_name' => $student['class_name'],
                        'stream_id' => $student['stream_id'],
                        'stream_name' => $student['stream_name'],
                        'average_score' => $average,
                        'grade' => $student['grade'] ?: $this->deriveGradeFromPercentage($average),
                        'attendance_rate' => isset($student['attendance_rate']) ? round((float) $student['attendance_rate'], 2) : 0,
                        'position' => $student['position'],
                        'fee_balance' => isset($student['fee_balance']) ? (float) $student['fee_balance'] : 0,
                    ];
                }
            } elseif ($viewMode === 'class') {
                $groups = [];
                foreach ($students as $student) {
                    $key = (string) ($student['class_id'] ?? $student['class_name'] ?? 'Unknown');
                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'class_id' => $student['class_id'],
                            'class_name' => $student['class_name'] ?: 'Unknown',
                            'total_students' => 0,
                            'total_score' => 0,
                            'score_count' => 0,
                            'attendance_total' => 0,
                            'attendance_count' => 0,
                            'top_student' => null,
                            'lowest_student' => null,
                        ];
                    }

                    $group = &$groups[$key];
                    $average = isset($student['average_score']) ? (float) $student['average_score'] : null;
                    $group['total_students']++;

                    if ($average !== null) {
                        $group['total_score'] += $average;
                        $group['score_count']++;
                        $group['attendance_total'] += (float) ($student['attendance_rate'] ?? 0);
                        $group['attendance_count']++;

                        if (!$group['top_student'] || $average > (float) ($group['top_student']['average_score'] ?? -1)) {
                            $group['top_student'] = $student;
                        }
                        if (!$group['lowest_student'] || $average < (float) ($group['lowest_student']['average_score'] ?? 101)) {
                            $group['lowest_student'] = $student;
                        }
                    }
                    unset($group);
                }

                foreach ($groups as $group) {
                    $rows[] = [
                        'class_id' => $group['class_id'],
                        'class_name' => $group['class_name'],
                        'total_students' => $group['total_students'],
                        'average_score' => $group['score_count'] ? round($group['total_score'] / $group['score_count'], 2) : 0,
                        'attendance_rate' => $group['attendance_count'] ? round($group['attendance_total'] / $group['attendance_count'], 2) : 0,
                        'top_student' => $group['top_student']['full_name'] ?? '-',
                        'lowest_student' => $group['lowest_student']['full_name'] ?? '-',
                    ];
                }
            } elseif ($viewMode === 'stream') {
                $groups = [];
                foreach ($students as $student) {
                    $key = (string) ($student['stream_id'] ?? $student['stream_name'] ?? 'Unknown');
                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'class_name' => $student['class_name'] ?: 'Unknown',
                            'stream_id' => $student['stream_id'],
                            'stream_name' => $student['stream_name'] ?: 'Unknown',
                            'total_students' => 0,
                            'total_score' => 0,
                            'score_count' => 0,
                            'attendance_total' => 0,
                            'attendance_count' => 0,
                            'top_student' => null,
                        ];
                    }

                    $group = &$groups[$key];
                    $average = isset($student['average_score']) ? (float) $student['average_score'] : null;
                    $group['total_students']++;

                    if ($average !== null) {
                        $group['total_score'] += $average;
                        $group['score_count']++;
                        $group['attendance_total'] += (float) ($student['attendance_rate'] ?? 0);
                        $group['attendance_count']++;

                        if (!$group['top_student'] || $average > (float) ($group['top_student']['average_score'] ?? -1)) {
                            $group['top_student'] = $student;
                        }
                    }
                    unset($group);
                }

                foreach ($groups as $group) {
                    $rows[] = [
                        'class_name' => $group['class_name'],
                        'stream_id' => $group['stream_id'],
                        'stream_name' => $group['stream_name'],
                        'total_students' => $group['total_students'],
                        'average_score' => $group['score_count'] ? round($group['total_score'] / $group['score_count'], 2) : 0,
                        'attendance_rate' => $group['attendance_count'] ? round($group['attendance_total'] / $group['attendance_count'], 2) : 0,
                        'top_student' => $group['top_student']['full_name'] ?? '-',
                    ];
                }
            } else {
                $totalStudents = count($students);
                $averageScores = [];
                $attendanceScores = [];
                $topStudent = null;
                $topScore = null;
                $bestGroup = null;
                $bestGroupScore = null;

                foreach ($students as $student) {
                    $average = isset($student['average_score']) ? (float) $student['average_score'] : null;
                    if ($average !== null) {
                        $averageScores[] = $average;
                        $attendanceScores[] = (float) ($student['attendance_rate'] ?? 0);

                        if ($topStudent === null || $average > $topScore) {
                            $topStudent = $student;
                            $topScore = $average;
                        }

                        $groupName = $student['class_name'] ?: $student['stream_name'] ?: 'Unknown';
                        if ($bestGroupScore === null || $average > $bestGroupScore) {
                            $bestGroup = $groupName;
                            $bestGroupScore = $average;
                        }
                    }
                }

                $rows[] = [
                    'scope' => 'Whole School',
                    'total_students' => $totalStudents,
                    'average_score' => $averageScores ? round(array_sum($averageScores) / count($averageScores), 2) : 0,
                    'attendance_rate' => $attendanceScores ? round(array_sum($attendanceScores) / count($attendanceScores), 2) : 0,
                    'top_class' => $bestGroup ?: '-',
                    'top_student' => $topStudent['full_name'] ?? '-',
                ];
            }

            $summary = [
                'total_students' => $viewMode === 'students'
                    ? count($rows)
                    : array_sum(array_map(static fn($row) => (int) ($row['total_students'] ?? 0), $rows)),
                'average_score' => 0,
                'top_student' => '-',
                'best_group' => '-',
            ];

            $summaryScores = [];
            foreach ($rows as $row) {
                if (isset($row['average_score']) && $row['average_score'] !== null) {
                    $summaryScores[] = (float) $row['average_score'];
                }
            }

            if (!empty($summaryScores)) {
                $summary['average_score'] = round(array_sum($summaryScores) / count($summaryScores), 2);
            }

            if ($viewMode === 'students') {
                $top = null;
                foreach ($rows as $row) {
                    if ($top === null || (float) ($row['average_score'] ?? 0) > (float) ($top['average_score'] ?? 0)) {
                        $top = $row;
                    }
                }
                if ($top) {
                    $summary['top_student'] = $top['full_name'] ?? '-';
                    $summary['best_group'] = $top['class_name'] ?? $top['stream_name'] ?? '-';
                }
            } elseif ($viewMode === 'class' || $viewMode === 'stream') {
                $top = null;
                foreach ($rows as $row) {
                    if ($top === null || (float) ($row['average_score'] ?? 0) > (float) ($top['average_score'] ?? 0)) {
                        $top = $row;
                    }
                }
                if ($top) {
                    $summary['top_student'] = $top['top_student'] ?? '-';
                    $summary['best_group'] = $viewMode === 'class'
                        ? ($top['class_name'] ?? '-')
                        : (($top['class_name'] ?? '-') . ' / ' . ($top['stream_name'] ?? '-'));
                }
            } else {
                $summary['top_student'] = $rows[0]['top_student'] ?? '-';
                $summary['best_group'] = $rows[0]['top_class'] ?? '-';
            }

            return successResponse([
                'view_mode' => $viewMode,
                'academic_year_id' => $yearId,
                'term_id' => $termId,
                'rows' => $rows,
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function isWorkflowUnavailableResult($result): bool
    {
        if (!is_array($result)) {
            return false;
        }

        $isSuccess = (($result['status'] ?? null) === 'success')
            || (($result['success'] ?? null) === true);
        if ($isSuccess) {
            return false;
        }

        $message = strtolower((string) ($result['message'] ?? ''));
        if ($message === '') {
            return false;
        }

        $keywords = [
            'workflow definition',
            'workflow instance',
            'workflow',
            'no starting stage',
            'invalid workflow',
            'stage',
        ];

        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve students for direct report generation mode.
     */
    private function resolveReportStudents($data = []): array
    {
        $academicYearId = $data['academic_year_id'] ?? $data['academic_year'] ?? null;
        if (empty($academicYearId)) {
            $yearStmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1");
            $academicYearId = $yearStmt->fetchColumn() ?: null;
        }
        $academicYearId = $academicYearId ? (int) $academicYearId : $this->resolveCurrentAcademicYearId();

        $yearValue = null;
        if ($academicYearId > 0) {
            $yearValueStmt = $this->db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
            $yearValueStmt->execute([$academicYearId]);
            $yearValue = (int) ($yearValueStmt->fetchColumn() ?: 0);
        }

        $where = ["s.status = 'active'"];
        $bindings = [];

        if (!empty($data['class_id'])) {
            $where[] = "(ayc.class_id = ? OR aycs.id = ?)";
            $cid = (int) $data['class_id'];
            array_push($bindings, $cid, $cid);
        }

        if (!empty($data['student_ids']) && is_array($data['student_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $data['student_ids'])));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "s.id IN ({$placeholders})";
                $bindings = array_merge($bindings, $ids);
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT
                s.id,
                s.admission_no,
                p.first_name,
                p.middle_name,
                p.last_name,
                c.name AS class_name,
                st.name AS stream_name,
                COALESCE(ans.annual_percentage, ans.annual_score, NULL) AS overall_percentage,
                ans.annual_grade AS overall_grade
            FROM students s
            JOIN persons p ON p.id = s.person_id
            LEFT JOIN student_academic_enrollments sae
                ON sae.student_id = s.id
               AND sae.academic_year_id = {$academicYearId}
               AND sae.enrollment_status IN ('pending', 'active')
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN streams st ON st.id = aycs.stream_id
            LEFT JOIN annual_scores ans
                ON ans.student_id = s.id
               AND ans.academic_year = ?
            WHERE {$whereClause}
            ORDER BY c.name, p.first_name, p.last_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($bindings, [$yearValue]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function deriveGradeFromPercentage($percentage)
    {
        if ($percentage === null || $percentage === '') {
            return null;
        }

        $value = (float) $percentage;
        if ($value >= 80) {
            return 'EE';
        }
        if ($value >= 50) {
            return 'ME';
        }
        if ($value >= 25) {
            return 'AE';
        }
        return 'BE';
    }

    /**
     * Get single class with detailed information
     */
    public function getClass($id)
    {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.code,
                    c.name,
                    c.level_id,
                    c.grade_level,
                    sl.name as level_name,
                    sl.code as level_code,
                    ayc.id AS academic_year_class_id,
                    ayc.academic_year_id,
                    ay.year_code AS academic_year,
                    ayc.status
                FROM classes c
                JOIN academic_year_classes ayc ON ayc.class_id = c.id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                WHERE c.id = ?
                ORDER BY ayc.academic_year_id DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Get streams
            $streamsResult = $this->listClassStreams($id);
            $class['streams'] = is_array($streamsResult) ? ($streamsResult['data'] ?? []) : [];

            // Get student count through active streams
            $countSql = "
                SELECT COUNT(DISTINCT sae.student_id) as total
                FROM student_academic_enrollments sae
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE ayc.class_id = ? AND sae.enrollment_status = 'active'
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute([$id]);
            $class['student_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return successResponse($class);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new class (linked to an academic year) with its streams.
     */
    public function createClass($data)
    {
        try {
            $this->db->beginTransaction();

            $name = trim((string) ($data['name'] ?? ''));
            $levelId = $data['level_id'] ?? null;
            if ($name === '' || !$levelId) {
                throw new \InvalidArgumentException("Missing required field: name or level_id");
            }

            $academicYearId = $data['academic_year_id'] ?? null;
            if (!$academicYearId && !empty($data['academic_year'])) {
                $stmt = $this->db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
                $stmt->execute([$data['academic_year']]);
                $academicYearId = (int) ($stmt->fetchColumn() ?: 0);
            }
            if (!$academicYearId) {
                $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
                $academicYearId = (int) ($stmt->fetchColumn() ?: 0);
            }
            if (!$academicYearId) {
                throw new Exception('Unable to determine the academic year');
            }

            $code = $data['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));

            // Check for duplicate class name in the same academic year
            $checkSql = "SELECT 1 FROM classes c JOIN academic_year_classes ayc ON ayc.class_id = c.id WHERE c.name = ? AND ayc.academic_year_id = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$name, $academicYearId]);
            if ($stmt->fetch()) {
                throw new Exception("Class '{$name}' already exists for the academic year");
            }

            $sql = "INSERT INTO classes (code, name, level_id, grade_level) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$code, $name, $levelId, $data['grade_level'] ?? null]);
            $classId = $this->db->lastInsertId();

            $status = (isset($data['status']) && in_array($data['status'], ['planning', 'active', 'completed'], true)) ? $data['status'] : 'active';
            $stmt = $this->db->prepare("INSERT INTO academic_year_classes (academic_year_id, class_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$academicYearId, $classId, $status]);
            $aycId = $this->db->lastInsertId();

            // Create streams (default 'A' when none specified)
            $streams = $data['streams'] ?? [];
            if (empty($streams)) {
                $streams = [['name' => 'A', 'capacity' => $data['capacity'] ?? 40]];
            }
            foreach ($streams as $stream) {
                $this->createStreamLink($aycId, $stream);
            }

            $this->db->commit();

            $this->logAction('create', $classId, "Class created: {$name}");

            $this->emitEvent('class_created', [
                'class_id' => $classId,
                'class_name' => $name,
                'academic_year_id' => $academicYearId
            ]);

            return successResponse([
                'status' => 'success',
                'message' => 'Class created successfully',
                'data' => ['id' => $classId, 'academic_year_class_id' => $aycId]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Update class details.
     */
    public function updateClass($id, $data)
    {
        try {
            // Check if class exists
            $checkSql = "SELECT id FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Class not found');
            }

            $updates = [];
            $bindings = [];

            foreach (['name', 'code', 'level_id', 'grade_level'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $bindings[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $bindings[] = $id;
                $sql = "UPDATE classes SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($bindings);
            }

            // Update academic year class status
            if (!empty($data['status'])) {
                $stmt = $this->db->prepare("
                    UPDATE academic_year_classes ayc
                    SET ayc.status = ?
                    WHERE ayc.class_id = ?
                ");
                $stmt->execute([$data['status'], $id]);
            }

            $this->logAction('update', $id, "Class ID {$id} updated");

            return successResponse(null, 'Class updated successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete a class (soft delete by closing its academic year classes).
     */
    public function deleteClass($id)
    {
        try {
            // Check if class exists
            $checkSql = "SELECT name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Check if class has active students via stream linkage
            $studentCheckSql = "
                SELECT COUNT(DISTINCT sae.student_id) as count
                FROM student_academic_enrollments sae
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE ayc.class_id = ? AND sae.enrollment_status = 'active'
            ";
            $stmt = $this->db->prepare($studentCheckSql);
            $stmt->execute([$id]);
            $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($studentCount > 0) {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Cannot delete class with {$studentCount} active students. Please transfer students first."
                ], 400);
            }

            // Soft delete - close the class for all academic years
            $sql = "UPDATE academic_year_classes SET status = 'completed' WHERE class_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Class deleted: {$class['name']}");

            return successResponse(null, 'Class deleted successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Assign room to a class's streams.
     */
    public function assignRoom($classId, $roomId)
    {
        try {
            // Verify class exists
            $classSql = "SELECT id, name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Verify room exists and is available
            $roomSql = "SELECT id, name, code, capacity, status FROM rooms WHERE id = ?";
            $stmt = $this->db->prepare($roomSql);
            $stmt->execute([$roomId]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                return errorResponse('Room not found');
            }

            if ($room['status'] !== 'active') {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Room {$room['name']} is currently {$room['status']} and cannot be assigned"
                ], 400);
            }

            // Assign room to all active streams of the class
            $updateSql = "
                UPDATE academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                SET aycs.room_id = ?
                WHERE ayc.class_id = ? AND aycs.status = 'active'
            ";
            $stmt = $this->db->prepare($updateSql);
            $stmt->execute([$roomId, $classId]);

            $this->logAction('update', $classId, "Room {$room['name']} assigned to class {$class['name']}");

            return successResponse([
                'status' => 'success',
                'message' => "Room {$room['name']} assigned to class {$class['name']} successfully",
                'data' => [
                    'class_id' => $classId,
                    'room_id' => $roomId,
                    'room_name' => $room['name']
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create (or reuse) a streams row and link it to an academic_year_classes row.
     * Returns the academic_year_class_streams id.
     */
    private function createStreamLink($aycId, $stream)
    {
        $name = trim((string) ($stream['name'] ?? $stream['stream_name'] ?? ''));
        if ($name === '') {
            throw new Exception('Stream name is required');
        }

        $code = trim((string) ($stream['code'] ?? ''));
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
        }

        $capacity = (int) ($stream['capacity'] ?? 40);
        if ($capacity <= 0) {
            $capacity = 40;
        }

        // Reuse an existing streams row with the same name
        $stmt = $this->db->prepare("SELECT id, capacity FROM streams WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $streamId = $row ? (int) $row['id'] : 0;

        if (!$streamId) {
            $stmt = $this->db->prepare("INSERT INTO streams (name, code, capacity) VALUES (?, ?, ?)");
            $stmt->execute([$name, $code, $capacity]);
            $streamId = (int) $this->db->lastInsertId();
        } elseif (isset($stream['capacity']) && (int) $stream['capacity'] > 0 && (int) $row['capacity'] !== $capacity) {
            $stmt = $this->db->prepare("UPDATE streams SET code = ?, capacity = ? WHERE id = ?");
            $stmt->execute([$code, $capacity, $streamId]);
        }

        // Link the stream to the academic year class
        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_class_streams
            WHERE academic_year_class_id = ? AND stream_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aycId, $streamId]);
        $aycsId = (int) ($stmt->fetchColumn() ?: 0);

        if (!$aycsId) {
            $status = (isset($stream['status']) && in_array($stream['status'], ['planning', 'active', 'completed'], true))
                ? $stream['status'] : 'active';
            $stmt = $this->db->prepare("
                INSERT INTO academic_year_class_streams (academic_year_class_id, stream_id, room_id, class_teacher_id, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $aycId,
                $streamId,
                !empty($stream['room_id']) ? (int) $stream['room_id'] : null,
                !empty($stream['teacher_id']) ? (int) $stream['teacher_id'] : null,
                $status
            ]);
            $aycsId = (int) $this->db->lastInsertId();
        }

        return $aycsId;
    }

    /**
     * Auto-create streams based on student count
     * Creates streams dynamically when students exceed class capacity
     */
    public function autoCreateStreams($classId, $studentCount = null)
    {
        try {
            $this->db->beginTransaction();

            // Get class details
            $classSql = "SELECT id, name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                $this->db->rollBack();
                return errorResponse('Class not found');
            }

            // Resolve the active academic_year_classes row
            $aycSql = "
                SELECT id
                FROM academic_year_classes
                WHERE class_id = ? AND status IN ('planning', 'active')
                ORDER BY academic_year_id DESC
                LIMIT 1
            ";
            $stmt = $this->db->prepare($aycSql);
            $stmt->execute([$classId]);
            $aycId = (int) ($stmt->fetchColumn() ?: 0);

            if (!$aycId) {
                $this->db->rollBack();
                return errorResponse('No active academic year registration for this class');
            }

            // Get current student count if not provided
            if ($studentCount === null) {
                $countSql = "
                    SELECT COUNT(DISTINCT sae.student_id)
                    FROM student_academic_enrollments sae
                    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    WHERE aycs.academic_year_class_id = ? AND sae.enrollment_status = 'active'
                ";
                $stmt = $this->db->prepare($countSql);
                $stmt->execute([$aycId]);
                $studentCount = (int) ($stmt->fetchColumn() ?: 0);
            }

            $classCapacity = 40;

            // Determine number of streams needed
            if ($studentCount <= $classCapacity) {
                $this->db->commit();
                return successResponse([
                    'message' => 'Single stream is sufficient for current student count',
                    'data' => ['streams_created' => 0, 'student_count' => $studentCount]
                ]);
            }

            // Calculate number of streams needed
            $streamsNeeded = (int) ceil($studentCount / $classCapacity);

            // Get existing active streams
            $existingSql = "
                SELECT COUNT(*)
                FROM academic_year_class_streams
                WHERE academic_year_class_id = ? AND status = 'active'
            ";
            $stmt = $this->db->prepare($existingSql);
            $stmt->execute([$aycId]);
            $existingCount = (int) ($stmt->fetchColumn() ?: 0);

            $streamsToCreate = max(0, $streamsNeeded - $existingCount);

            if ($streamsToCreate > 0) {
                $streamNames = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $createdCount = 0;

                for ($i = $existingCount; $i < $streamsNeeded && $i < count($streamNames); $i++) {
                    $streamName = $streamNames[$i];

                    // Check if stream already exists for this academic year class
                    $checkSql = "
                        SELECT aycs.id
                        FROM academic_year_class_streams aycs
                        JOIN streams s ON s.id = aycs.stream_id
                        WHERE aycs.academic_year_class_id = ? AND s.name = ?
                        LIMIT 1
                    ";
                    $stmt = $this->db->prepare($checkSql);
                    $stmt->execute([$aycId, $streamName]);

                    if (!$stmt->fetch()) {
                        $this->createStreamLink($aycId, [
                            'name' => $streamName,
                            'capacity' => $classCapacity,
                            'status' => 'active'
                        ]);
                        $createdCount++;
                    }
                }

                $this->db->commit();

                $this->logAction('streams_auto_created', "Auto-created {$createdCount} streams for class {$class['name']}", [
                    'class_id' => $classId,
                    'streams_created' => $createdCount,
                    'student_count' => $studentCount
                ]);

                // Emit event for frontend updates
                $this->emitEvent('streams_created', [
                    'class_id' => $classId,
                    'streams_created' => $createdCount,
                    'total_streams' => $streamsNeeded
                ]);

                return successResponse([
                    'message' => "{$createdCount} stream(s) created to accommodate {$studentCount} students",
                    'data' => [
                        'streams_created' => $createdCount,
                        'total_streams' => $streamsNeeded,
                        'student_count' => $studentCount
                    ]
                ]);
            }

            $this->db->commit();
            return successResponse([
                'status' => 'success',
                'message' => 'Sufficient streams already exist',
                'data' => ['streams_created' => 0, 'existing_streams' => $existingCount]
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Create a new stream for a class
     * Links a streams row to the class's academic year registration.
     */
    public function createStream($classId, $data)
    {
        try {
            $classId = $classId ?? ($data['class_id'] ?? null);
            if (!$classId) {
                return errorResponse('Class ID is required', 400);
            }
            $this->db->beginTransaction();

            // Verify class exists
            $classSql = "SELECT id, name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                $this->db->rollBack();
                return errorResponse('Class not found');
            }

            // Resolve the academic_year_classes row (current year by default)
            $aycSql = "
                SELECT id
                FROM academic_year_classes
                WHERE class_id = ?
            ";
            $aycParams = [$classId];
            if (!empty($data['academic_year_id'])) {
                $aycSql .= " AND academic_year_id = ?";
                $aycParams[] = (int) $data['academic_year_id'];
            } else {
                $aycSql .= " AND status IN ('planning', 'active')";
            }
            $aycSql .= " ORDER BY academic_year_id DESC LIMIT 1";
            $stmt = $this->db->prepare($aycSql);
            $stmt->execute($aycParams);
            $aycId = (int) ($stmt->fetchColumn() ?: 0);

            if (!$aycId) {
                $this->db->rollBack();
                return errorResponse('No academic year registration found for this class');
            }

            // Validate required fields
            $streamName = trim((string) ($data['stream_name'] ?? $data['name'] ?? ''));
            if ($streamName === '') {
                $this->db->rollBack();
                return errorResponse('Stream name is required');
            }

            $capacity = (int) ($data['capacity'] ?? 0);
            if ($capacity <= 0) {
                $this->db->rollBack();
                return errorResponse('Capacity is required');
            }

            // Check for duplicate stream name for this academic year class
            $checkSql = "
                SELECT aycs.id
                FROM academic_year_class_streams aycs
                JOIN streams s ON s.id = aycs.stream_id
                WHERE aycs.academic_year_class_id = ? AND s.name = ?
                LIMIT 1
            ";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$aycId, $streamName]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                return errorResponse([
                    'status' => 'error',
                    'message' => "Stream '{$streamName}' already exists for this class"
                ], 400);
            }

            $aycsId = $this->createStreamLink($aycId, [
                'name' => $streamName,
                'capacity' => $capacity,
                'teacher_id' => !empty($data['teacher_id']) ? (int) $data['teacher_id'] : null,
                'room_id' => !empty($data['room_id']) ? (int) $data['room_id'] : null,
                'status' => in_array(($data['status'] ?? 'active'), ['planning', 'active', 'completed'], true) ? $data['status'] : 'active'
            ]);

            $this->logAction('stream_created', "Stream {$streamName} created for class {$class['name']}", [
                'class_id' => $classId,
                'stream_id' => $aycsId
            ]);

            // Emit event for frontend updates
            $this->emitEvent('stream_created', [
                'class_id' => $classId,
                'stream_id' => $aycsId,
                'stream_name' => $streamName
            ]);

            $this->db->commit();

            return successResponse([
                'status' => 'success',
                'message' => 'Stream created successfully',
                'data' => ['id' => $aycsId]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function getStream($id)
    {
        try {
            if (empty($id)) {
                return errorResponse('Stream ID is required', 400);
            }

            $sql = "
                SELECT
                    aycs.id as id,
                    aycs.academic_year_class_id,
                    ayc.class_id,
                    ayc.academic_year_id,
                    ay.year_code as academic_year,
                    aycs.stream_id,
                    s.name as stream_name,
                    s.code as stream_code,
                    s.capacity,
                    aycs.room_id,
                    r.name as room_name,
                    aycs.class_teacher_id as teacher_id,
                    CONCAT(p.first_name, ' ', p.last_name) as teacher_name,
                    aycs.status,
                    c.name as class_name,
                    c.code as class_code,
                    COUNT(DISTINCT sae.id) as student_count
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                JOIN streams s ON aycs.stream_id = s.id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                LEFT JOIN rooms r ON aycs.room_id = r.id
                LEFT JOIN staff st ON aycs.class_teacher_id = st.id
                LEFT JOIN persons p ON p.id = st.person_id
                LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active'
                WHERE aycs.id = ?
                GROUP BY aycs.id
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stream) {
                return errorResponse('Stream not found', 404);
            }

            return successResponse($stream);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateStream($id, $data)
    {
        try {
            if (empty($id)) {
                return errorResponse('Stream ID is required', 400);
            }

            // Load the academic_year_class_streams link row
            $linkSql = "
                SELECT aycs.*, s.name as stream_name, s.code, s.capacity
                FROM academic_year_class_streams aycs
                JOIN streams s ON s.id = aycs.stream_id
                WHERE aycs.id = ?
                LIMIT 1
            ";
            $stmt = $this->db->prepare($linkSql);
            $stmt->execute([(int) $id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return errorResponse('Stream not found', 404);
            }

            $streamId = (int) $existing['stream_id'];
            $aycId = (int) $existing['academic_year_class_id'];

            $updates = [];
            $bindings = [];

            if (array_key_exists('stream_name', $data) || array_key_exists('name', $data)) {
                $streamName = trim((string) ($data['stream_name'] ?? $data['name'] ?? ''));
                if ($streamName === '') {
                    return errorResponse('Stream name cannot be empty', 400);
                }

                $checkStmt = $this->db->prepare("
                    SELECT s.id
                    FROM streams s
                    JOIN academic_year_class_streams aycs ON aycs.stream_id = s.id
                    WHERE aycs.academic_year_class_id = ? AND s.name = ? AND aycs.id != ?
                    LIMIT 1
                ");
                $checkStmt->execute([$aycId, $streamName, (int) $id]);
                if ($checkStmt->fetch()) {
                    return errorResponse("Stream '{$streamName}' already exists for this class", 400);
                }

                $updates[] = 'name = ?';
                $bindings[] = $streamName;
            }

            if (array_key_exists('code', $data)) {
                $code = trim((string) $data['code']);
                if ($code === '') {
                    return errorResponse('Stream code cannot be empty', 400);
                }
                $updates[] = 'code = ?';
                $bindings[] = $code;
            }

            if (array_key_exists('capacity', $data)) {
                $capacity = (int) $data['capacity'];
                if ($capacity <= 0) {
                    return errorResponse('Capacity must be greater than zero', 400);
                }
                $updates[] = 'capacity = ?';
                $bindings[] = $capacity;
            }

            if (array_key_exists('teacher_id', $data)) {
                $teacherId = !empty($data['teacher_id']) ? (int) $data['teacher_id'] : null;
                $stmt = $this->db->prepare("UPDATE academic_year_class_streams SET class_teacher_id = ? WHERE id = ?");
                $stmt->execute([$teacherId, (int) $id]);
            }

            if (array_key_exists('room_id', $data)) {
                $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
                $stmt = $this->db->prepare("UPDATE academic_year_class_streams SET room_id = ? WHERE id = ?");
                $stmt->execute([$roomId, (int) $id]);
            }

            if (!empty($data['status']) && in_array($data['status'], ['planning', 'active', 'completed'], true)) {
                $stmt = $this->db->prepare("UPDATE academic_year_class_streams SET status = ? WHERE id = ?");
                $stmt->execute([$data['status'], (int) $id]);
            }

            if (!empty($updates)) {
                $bindings[] = $streamId;
                $sql = 'UPDATE streams SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($bindings);
            }

            $this->logAction('stream_updated', "Stream {$id} updated", ['stream_id' => (int) $id]);

            return successResponse([
                'status' => 'success',
                'message' => 'Stream updated successfully',
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteStream($id)
    {
        try {
            if (empty($id)) {
                return errorResponse('Stream ID is required', 400);
            }

            $this->db->beginTransaction();

            $linkSql = "
                SELECT aycs.id, aycs.academic_year_class_id, s.name as stream_name
                FROM academic_year_class_streams aycs
                JOIN streams s ON s.id = aycs.stream_id
                WHERE aycs.id = ?
                LIMIT 1
            ";
            $stmt = $this->db->prepare($linkSql);
            $stmt->execute([(int) $id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stream) {
                $this->db->rollBack();
                return errorResponse('Stream not found', 404);
            }

            $countStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM student_academic_enrollments
                WHERE academic_year_class_stream_id = ? AND enrollment_status = 'active'
            ");
            $countStmt->execute([(int) $id]);
            $activeStudents = (int) $countStmt->fetchColumn();

            if ($activeStudents > 0) {
                $this->db->rollBack();
                return errorResponse("Cannot delete stream with {$activeStudents} active students. Reassign students first.", 400);
            }

            // Soft delete to preserve history references
            $delStmt = $this->db->prepare("UPDATE academic_year_class_streams SET status = 'completed' WHERE id = ?");
            $delStmt->execute([(int) $id]);

            // Ensure each class retains at least one active stream.
            $activeStreamCountStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM academic_year_class_streams
                WHERE academic_year_class_id = ? AND status = 'active'
            ");
            $activeStreamCountStmt->execute([(int) $stream['academic_year_class_id']]);
            $remainingActive = (int) $activeStreamCountStmt->fetchColumn();

            if ($remainingActive === 0) {
                $reactivateDefaultStmt = $this->db->prepare("
                    UPDATE academic_year_class_streams
                    SET status = 'active'
                    WHERE academic_year_class_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $reactivateDefaultStmt->execute([(int) $stream['academic_year_class_id']]);
            }

            $this->logAction('stream_deleted', "Stream {$stream['stream_name']} deactivated", ['stream_id' => (int) $id]);
            $this->db->commit();

            return successResponse([
                'status' => 'success',
                'message' => 'Stream removed successfully',
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * ROUTE ALIASES - Match frontend API.academic method names to backend methods
     * These ensure URL patterns like /academic/classes-list map to the correct methods
     */

    /**
     * Alias for listClasses - matches /academic/classes-list
     */
    public function getClassesList($params = [])
    {
        return $this->listClasses($params);
    }

    /**
     * List all streams across all classes - matches /academic/streams-list
     */
    public function getStreamsList($params = [])
    {
        return $this->listClassStreams($params['class_id'] ?? null, $params);
    }

    /**
     * List all learning areas (subjects) - matches /academic/learning-areas/list
     */
    public function getLearningAreasList($params = [])
    {
        try {
            $sql = "
                SELECT 
                    *
                FROM learning_areas
                WHERE status = 'active'
                ORDER BY name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($subjects);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
