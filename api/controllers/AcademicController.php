<?php

namespace App\API\Controllers;

use App\API\Modules\academic\AcademicAPI;
use App\API\Modules\academic\AcademicExamService;
use App\API\Modules\academic\AcademicReportService;
use App\API\Modules\academic\AcademicCurriculumService;
use App\API\Modules\academic\AcademicYearService;
use App\API\Services\DirectorAnalyticsService;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\StaffTeachingAssignmentService;
use App\API\Modules\system\MediaManager;
use App\Database\Database;
use RuntimeException;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use Exception;

/**
 * AcademicController
 *
 * Explicit REST endpoints for Academic Management. This controller exposes a
 * large collection of explicit methods that map to academic workflows and
 * CRUD operations via a consistent routing convention. It delegates business
 * logic to App\API\Modules\academic\AcademicAPI and adapts HTTP-style calls
 * into API method calls.
 *
 * Routing & Method Conventions
 * - Base CRUD endpoints:
 *     - index()                          -> GET  /api/academic
 *     - get($id = null, $data = [], ...) -> GET  /api/academic         (list)
 *     - get($id, ...)                    -> GET  /api/academic/{id}    (retrieve)
 *     - post($id = null, $data = [], ...) -> POST /api/academic        (create)
 *     - put($id, $data, ...)             -> PUT  /api/academic/{id}   (update)
 *     - delete($id, $data, ...)          -> DELETE /api/academic/{id} (delete)
 *
 * - Router call signature used by controller methods:
 *     methodName($id, $data, $segments)
 *   where:
 *     - $id       : optional resource id (from URL segment)
 *     - $data     : associative array of request payload / query params
 *     - $segments : remaining URL segments for nested routing (array)
 *
 * Nested routing & naming
 * - Nested POST/GET requests are routed through routeNestedPost / routeNestedGet.
 * - URL segments are converted from kebab-case or snake_case to camelCase
 *   using toCamelCase().
 * - Controller method names follow the pattern:
 *     <httpVerb><Resource><Action>
 *   Examples:
 *     - POST /api/academic/exams/start-workflow  -> postExamsStartWorkflow(...)
 *     - POST /api/academic/promotions/execute    -> postPromotionsExecute(...)
 * - When an $id is present in the URL it is merged into $data['id'] before
 *   invoking the target method.
 *
 * Data & common parameters
 * - Many workflow endpoints expect (or optionally accept) common keys in $data:
 *     - instance_id    : academic instance / school context
 *     - term_id        : academic term identifier
 *     - exam_type      : type/category of exam
 *     - schedule_entries, schedule_entries[] etc.
 *     - subject_id, student_id, competency_id, core_value_id
 *     - assignments, marks_data, moderation_data, grading_data
 *     - promotion_data, year_config, resources, mappings, items
 *     - filters, params, action (for custom endpoints)
 *
 * Example grouped workflows (representative)
 * - Examination workflow: startExaminationWorkflow, createExamSchedule,
 *   submitQuestionPaper, prepareExamLogistics, conductExamination,
 *   assignExamMarking, recordExamMarks, verifyExamMarks, moderateExamMarks,
 *   compileExamResults, approveExamResults.
 *
 * - Promotion workflow: startPromotionWorkflow, identifyPromotionCandidates,
 *   validatePromotionEligibility, executePromotions, generatePromotionReports.
 *
 * - Assessment workflow: startAssessmentWorkflow, createAssessmentItems,
 *   administerAssessment, markAndGradeAssessment, analyzeAssessmentResults.
 *
 * - Reporting, Library, Curriculum, Year Transition and other domain-specific
 *   workflows follow similar naming and usage patterns.
 *
 * Response handling
 * - handleResponse($result) normalizes API return values and maps them to the
 *   controller's success/badRequest responses:
 *     - If $result is an array and contains 'success' (boolean), it is used to
 *       determine success vs failure; 'data' and 'message' fields are honored.
 *     - If $result is an array and contains 'status' ('success'/'error'), it
 *       is similarly honored.
 *     - If $result is a plain array (without the above keys) it is returned as
 *       success payload.
 *     - Non-array results are wrapped as ['result' => $result].
 *
 * Error handling & validation notes
 * - put() and delete() require an $id; otherwise a badRequest response is
 *   returned.
 * - routeNestedPost / routeNestedGet return notFound() when the computed
 *   controller method does not exist.
 *
 * BaseController integration
 * - This controller relies on BaseController helper methods for HTTP responses:
 *     - success($data, $message = null)
 *     - badRequest($message = null, $data = [])
 *     - notFound($message = null)
 *
 * Extension points
 * - Add new workflow endpoints by:
 *     1) implementing the corresponding method on AcademicAPI, and
 *     2) adding a controller wrapper method following the <verb><Resource><Action>
 *        naming convention or letting nested routing invoke it.
 *
 * Notes
 * - This docblock summarizes the controller's routing and expected payload
 *   conventions; refer to specific endpoint method docblocks (or AcademicAPI)
 *   for more precise parameter contracts and return schemas for each action.
 */

class AcademicController extends BaseController
{
    private $staffAccess;
    private $teachingAssignments;
    private $api;
    private $contextService;
    private $cohortProjectionService;
    private AcademicExamService $examService;
    private AcademicReportService $reportService;
    private AcademicCurriculumService $curriculumService;
    private AcademicYearService $yearService;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AcademicAPI();
        $this->staffAccess = new StaffDomainAccessService($this->user);
        $this->teachingAssignments = new StaffTeachingAssignmentService();

        // Initialize Academic Context Service
        require_once __DIR__ . '/../services/AcademicContextService.php';
        $this->contextService = new \App\API\Services\AcademicContextService();

        // Initialize Cohort Projection Service (Admission Stage 5)
        require_once __DIR__ . '/../modules/academic/AcademicCohortProjectionService.php';
        $this->cohortProjectionService = new \App\API\Modules\academic\AcademicCohortProjectionService();
        $this->examService = new AcademicExamService($this->api);
        $this->reportService = new AcademicReportService($this->api);
        $this->curriculumService = new AcademicCurriculumService($this->api);
        $this->yearService = new AcademicYearService($this->api);
    }

    public function index()
    {
        // GET /api/academic/index - API health/info endpoint
        return $this->success([
            'message' => 'Academic API is running',
            'endpoints' => [
                'list' => '/api/academic (GET)',
                'create' => '/api/academic (POST)',
                'update' => '/api/academic/{id} (PUT)',
                'delete' => '/api/academic/{id} (DELETE)',
                'context' => '/api/academic/context (GET)'
            ],
            'health' => 'ok',
            'timestamp' => date('c')
        ]);
    }

    /**
     * GET /api/academic/context - Get current academic context
     * Returns current academic year, term, and operational status
     */
    public function getContext()
    {
        try {
            $context = $this->contextService->getCurrentContext();
            return $this->success($context);
        } catch (Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /**
     * GET /api/academics/kpis
     * Director/CEO-only: Academic performance KPIs.
     * NOTE: routes under /api/academics/* dispatch to AcademicController, so this
     * method lives here (not DashboardController) to be reachable from the router.
     * Router builds the method name "getKpis" from the "kpis" resource segment.
     */
    public function getKpis($id = null, $data = [], $segments = [])
    {
        if (!$this->hasRoleId(3)) {
            return $this->forbidden('Director access only');
        }
        try {
            $analytics = new DirectorAnalyticsService();
            $kpis = $analytics->getAcademicKPIs();

            return $this->success([
                'kpis' => $kpis
            ], 'Academic KPIs retrieved');

        } catch (Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academics/performance-matrix
     * Director/CEO-only: Performance heatmap data.
     * Router builds "getPerformanceMatrix" from the "performance-matrix" resource.
     */
    public function getPerformanceMatrix($id = null, $data = [], $segments = [])
    {
        if (!$this->hasRoleId(3)) {
            return $this->forbidden('Director access only');
        }
        try {
            $analytics = new DirectorAnalyticsService();
            $matrix = $analytics->getPerformanceMatrix();

            return $this->success([
                'data' => $matrix
            ], 'Performance matrix retrieved');

        } catch (Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }


    // ==================== ADMISSION STAGE 5: COHORT CAPACITY PROJECTION ====
    // GET /api/academic/cohort-capacity
    //     ?target_academic_year_id=7&target_term_id=19&target_class_id=4
    //     [&target_stream_id=12][&applied_academic_year=2027]
    // Period-aware, cohort-aware capacity projection for a target
    // class/stream in a (possibly future) academic year.
    public function getCohortCapacity($id = null, $data = [], $segments = [])
    {
        $targetYearId = isset($data['target_academic_year_id'])
            ? (int) $data['target_academic_year_id'] : null;
        $targetTermId = isset($data['target_term_id']) && $data['target_term_id'] !== ''
            ? (int) $data['target_term_id'] : null;
        $targetClassId = isset($data['target_class_id'])
            ? (int) $data['target_class_id'] : null;
        $targetStreamId = isset($data['target_stream_id']) && $data['target_stream_id'] !== ''
            ? (int) $data['target_stream_id'] : null;
        $appliedYear = isset($data['applied_academic_year']) && $data['applied_academic_year'] !== ''
            ? (int) $data['applied_academic_year'] : null;

        if (!$targetYearId || !$targetClassId) {
            error_log('[AcademicController] getCohortProjection: target year/class required');
            return $this->error('Target academic year and class are required.');
        }

        $result = $this->cohortProjectionService->projectClassCapacity(
            $targetYearId,
            $targetTermId,
            $targetClassId,
            $targetStreamId,
            $appliedYear
        );
        return $this->handleResponse($result);
    }

    // GET /api/academic/cohort-projection?application_id=8
    // Projects capacity for a specific admission application.
    public function getCohortProjection($id = null, $data = [], $segments = [])
    {
        $applicationId = isset($data['application_id'])
            ? (int) $data['application_id'] : null;
        if (!$applicationId) {
            error_log('[AcademicController] getCohortProjection: application_id required');
            return $this->error('application_id is required.');
        }
        $result = $this->cohortProjectionService->projectCapacityForApplication($applicationId);
        return $this->handleResponse($result);
    }

    // ==================== TEACHING RESOURCES ====================
    // GET /api/academic/resources?type=material|past_paper[&class_id=&subject_id=&term_id=&q=]
    // Unified listing across teaching_materials and past_papers (all statuses).
    public function getResources($id = null, $data = [], $segments = [])
    {
        $type = isset($data['type']) ? $data['type'] : 'material';
        $db = \App\Database\Database::getInstance();

        $where = [];
        $params = [];
        $classId = isset($data['class_id']) && $data['class_id'] !== '' ? (int) $data['class_id'] : null;
        $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;
        $termId = isset($data['term_id']) && $data['term_id'] !== '' ? (int) $data['term_id'] : null;
        $q = isset($data['q']) && trim($data['q']) !== '' ? trim($data['q']) : null;

        try {
            if ($type === 'past_paper') {
                if ($termId) { $where[] = 'p.term_id = ?'; $params[] = $termId; }
                if ($subjectId) { $where[] = 'p.subject_id = ?'; $params[] = $subjectId; }
                if ($q) { $where[] = 'p.title LIKE ?'; $params[] = "%{$q}%"; }
                $sql = "SELECT p.id, 'past_paper' AS type, p.title, p.description,
                                p.subject_id, p.learning_area_id, p.exam_year, p.exam_type,
                                p.term_id, NULL AS class_id, p.file_name, p.file_type,
                                p.file_size, p.file_path, p.status, p.download_count,
                                p.created_at,
                                la.name AS learning_area, la.name AS subject_name
                        FROM past_papers p
                        LEFT JOIN learning_areas la ON la.id = p.learning_area_id";
            } else {
                if ($classId) { $where[] = 'm.class_id = ?'; $params[] = $classId; }
                if ($termId) { $where[] = 'm.term_id = ?'; $params[] = $termId; }
                if ($subjectId) { $where[] = 'm.subject_id = ?'; $params[] = $subjectId; }
                if ($q) { $where[] = 'm.title LIKE ?'; $params[] = "%{$q}%"; }
                $sql = "SELECT m.id, 'material' AS type, m.title, m.description,
                                m.subject_id, m.learning_area_id, NULL AS exam_year, m.resource_type AS exam_type,
                                m.term_id, m.class_id, m.file_name, m.file_type,
                                m.file_size, m.file_path, m.status, m.download_count,
                                m.created_at,
                                la.name AS learning_area, la.name AS subject_name,
                                c.name AS class_name,
                                CONCAT(s.first_name, ' ', s.last_name) AS uploaded_by_name
                        FROM teaching_materials m
                        LEFT JOIN learning_areas la ON la.id = m.learning_area_id
                        LEFT JOIN classes c ON c.id = m.class_id
                        LEFT JOIN staff s ON s.id = m.teacher_id";
            }
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 200';

            $rows = $db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    // POST /api/academic/resources  (multipart FormData upload to teaching_materials)
    // Fields: file, title, subject_id, class, type, term, description
    public function postResources($id = null, $data = [], $segments = [])
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error_log('[AcademicController] postResources: no valid file uploaded');
            return $this->error('No file uploaded or upload failed.');
        }
        $f = $_FILES['file'];
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            error_log('[AcademicController] postResources: title required');
            return $this->error('A title is required.');
        }
        $allowedExt = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','jpg','jpeg','png','gif','mp4','mp3','zip'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            error_log('[AcademicController] postResources: disallowed extension ' . $ext);
            return $this->error('File type not allowed.');
        }

        // UPLOAD_PATH may be an absolute path or a relative one; normalize to
        // absolute against the application base so mkdir is CWD-independent.
        $base = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../../uploads';
        if (!preg_match('#^(/|\\\\|[A-Za-z]:\\\\)#', $base)) {
            $base = dirname(__DIR__, 2) . '/' . $base;
        }
        $destDir = $base . '/teaching_materials';
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            return $this->error('Could not create upload directory: ' . $destDir);
        }
        $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
        $destPath = $destDir . '/' . $safeName;
        if (!move_uploaded_file($f['tmp_name'], $destPath)) {
            error_log('[AcademicController] postResources: move_uploaded_file failed to ' . $destPath);
            return $this->error('Could not store the uploaded file.');
        }

        $relPath = 'uploads/teaching_materials/' . $safeName;
        $db = \App\Database\Database::getInstance();
        $userId = $this->user['id'] ?? null;

        // Resolve the uploading teacher (staff row) from the auth user.
        // teacher_id is nullable: non-staff uploaders (admins, system/test
        // accounts) have no staff row, and that is a legitimate state.
        $teacherId = null;
        if ($userId) {
            $t = $db->query('SELECT id FROM staff WHERE user_id = ?', [$userId])->fetch(\PDO::FETCH_ASSOC);
            $teacherId = $t['id'] ?? null;
        }

        // The frontend "type" is a pedagogical category (Worksheet, Notes,
        // Past Paper, Presentation, Other) but resource_type is a media-kind
        // enum (document|presentation|video|audio|image|other). Normalize at
        // the API boundary; unrecognized values fall back to 'document'.
        $typeMap = [
            'worksheet'    => 'document',
            'notes'        => 'document',
            'past paper'   => 'document',
            'presentation' => 'presentation',
            'other'        => 'other',
        ];
        $resourceType = $typeMap[strtolower(trim($_POST['type'] ?? ''))] ?? 'document';

        $db->query(
            "INSERT INTO teaching_materials
                (title, description, subject_id, learning_area_id, teacher_id, class_id,
                 term_id, file_path, file_name, file_type, file_size, resource_type, status, academic_year_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL)",
            [
                $title,
                trim($_POST['description'] ?? ''),
                !empty($_POST['subject_id']) ? (int) $_POST['subject_id'] : null,
                null,
                $teacherId,
                !empty($_POST['class']) ? (int) $_POST['class'] : null,
                !empty($_POST['term']) ? (int) $_POST['term'] : null,
                $relPath,
                $f['name'],
                $f['type'] ?: $ext,
                $f['size'],
                $resourceType,
            ]
        );

        return $this->success(['id' => $db->lastInsertId()], 'Resource uploaded successfully.');
    }

    // GET /api/academic/resources/{id}/download  — serve the file (browser must hit this directly)
    public function getResourcesDownload($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            error_log('[AcademicController] getResourcesDownload: resource id required');
            return $this->error('Resource id is required.');
        }
        $db = \App\Database\Database::getInstance();
        $row = $db->query(
            "SELECT id, file_path, file_name, file_type FROM teaching_materials WHERE id = ?
             UNION ALL
             SELECT id, file_path, file_name, file_type FROM past_papers WHERE id = ?",
            [$id, $id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['file_path'])) {
            return $this->error('Resource not found.', 404);
        }
        $abs = (strpos($row['file_path'], '/') === 0)
            ? $row['file_path']
            : __DIR__ . '/../../' . $row['file_path'];
        if (!is_file($abs)) {
            return $this->error('File is missing on the server.', 404);
        }

        // Track the download (increment whichever table owns the row).
        $db->query(
            "UPDATE teaching_materials SET download_count = download_count + 1 WHERE id = ?",
            [$id]
        );
        $db->query(
            "UPDATE past_papers SET download_count = download_count + 1 WHERE id = ?",
            [$id]
        );

        header('Content-Type: ' . ($row['file_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($row['file_name'] ?: 'download') . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }


    // Router calls methods with: methodName($id, $data, $segments)

    /**
     * GET /api/academic - List all academic records
     * Called as: get(null, $data, [])
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            // GET /api/academic/{id} - Get specific record
            $result = $this->api->get($id, $data);
            return $this->handleResponse($result);
        } else {
            // GET /api/academic - List all records
            $result = $this->api->list($data);
            return $this->handleResponse($result);
        }
    }

    /**
     * GET /api/academic/levels-list - List active school levels
     */
    public function getLevelsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLevelsList($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic - Create new academic record
     * Called as: post(null, $data, [])
     */
    public function post($id = null, $data = [], $segments = [])
    {
        // Merge id into data if provided in URL
        if ($id !== null) {
            $data['id'] = $id;
        }

        // Check for nested resource routing
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }

        // Default: create new record
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/{id} - Update academic record
     * Called as: put($id, $data, [])
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required for update operation');
        }

        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/{id} - Delete academic record
     * Called as: delete($id, $data, [])
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required for delete operation');
        }

        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Route nested POST requests to specific workflow methods
     * Example: POST /api/academic/exams/start-workflow
     * Called with: routeNestedPost('exams', null, $data, ['start-workflow'])
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        // Convert kebab-case to camelCase for method lookup
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        // Build method name: post + Resource + Action
        // Example: 'exams' + 'startWorkflow' = 'postExamsStartWorkflow'
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        // Check if method exists
        if (method_exists($this, $methodName)) {
            // Merge ID into data if provided
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to specific methods
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
     * Convert kebab-case or snake_case to camelCase
     * Examples: 'start-workflow' -> 'startWorkflow', 'user_profile' -> 'userProfile'
     */
    private function toCamelCase($string)
    {
        // Replace both - and _ with spaces, then ucwords, then remove spaces
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    /**
     * Handle API response and format appropriately
     */
    public function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    return $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful');
                }

                $message = $result['message'] ?? 'Operation failed';
                $data = $result['data'] ?? [];
                $code = (int) ($result['code'] ?? 400);

                if ($code === 401) {
                    return $this->unauthorized($message);
                }
                if ($code === 403) {
                    return $this->forbidden($message);
                }
                if ($code === 404) {
                    return $this->notFound($message);
                }
                if ($code >= 500) {
                    return $this->serverError($message, $data);
                }

                return $this->badRequest($message, is_array($data) ? $data : []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }
    public function requireAcademicWorkflowAccess(array $permissions = ['academic_manage', 'academic_approve'])
    {
        $aliases = [];
        foreach ($permissions as $permission) {
            $aliases[] = $permission;
            if (strpos($permission, 'academic_') === 0) {
                $aliases[] = 'academics_' . substr($permission, strlen('academic_'));
            }
        }

        if (!$this->userHasAny(array_values(array_unique($aliases)), [1, 3, 4, 5, 6], ['system admin', 'director', 'principal', 'headteacher', 'deputy head - academic'])) {
            return $this->forbidden('You do not have permission to perform this academic workflow action');
        }

        return null;
    }

    // ==================== EXAMINATION WORKFLOW ====================
    // URLs: POST /api/academic/exams/start-workflow
    //       POST /api/academic/exams/create-schedule
    // Router calls: postExams($id, $data, ['start-workflow'])

    /**
     * POST /api/academic/exams/start-workflow - Start examination workflow
     * Called as: postExamsStartWorkflow(null, $data, [])
     */
    public function postExamsStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->examService->postExamsStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/exams/create-schedule - Create exam schedule
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsCreateSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->createExamSchedule(
            $data['instance_id'] ?? null,
            $data['schedule_entries'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/submit-questions - Submit question papers
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsSubmitQuestions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->submitQuestionPaper(
            $data['instance_id'] ?? null,
            $data['subject_id'] ?? null,
            $data['paper_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/prepare-logistics - Prepare exam logistics
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsPrepareLogistics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->prepareExamLogistics($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/conduct - Conduct examination
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsConduct($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->conductExamination($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/assign-marking - Assign exam marking
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsAssignMarking($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->assignExamMarking(
            $data['instance_id'] ?? null,
            $data['assignments'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/record-marks - Record exam marks
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsRecordMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->recordExamMarks(
            $data['instance_id'] ?? null,
            $data['marks_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/verify-marks - Verify exam marks
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsVerifyMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->verifyExamMarks($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/moderate-marks - Moderate exam marks
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsModerateMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->moderateExamMarks(
            $data['instance_id'] ?? null,
            $data['moderation_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/compile-results - Compile exam results
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsCompileResults($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->compileExamResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/approve-results - Approve exam results (Director/academic_approve)
     * Body: { instance_id, approved (bool, default true), comments }
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsApproveResults($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_approve', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to approve exam results');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $approved = isset($data['action'])
            ? (strtolower($data['action']) === 'approve')
            : (bool) ($data['approved'] ?? true);
        $remarks = $data['comments'] ?? ($data['remarks'] ?? '');

        $result = $this->api->approveExamResults($instanceId, $approved, $remarks);
        return $this->handleResponse($result);
    }

    // ==================== EXAM SCHEDULE DIRECT CRUD ====================
    // URLs: GET/POST/PUT/DELETE /api/academic/exam-schedule
    // Used by: js/pages/exam_schedule.js

    /**
     * GET /api/academic/exam-schedule - List exam schedules with filters
     * GET /api/academic/exam-schedule/{id} - Get single exam schedule
     * Router calls: getExamSchedule($id, $data, $segments)
     */
    public function getExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $result = $this->api->getExamScheduleById($id);
        } else {
            $result = $this->api->listExamSchedules($data);
        }
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exam-schedule - Create new exam schedule
     * Router calls: postExamSchedule(null, $data, $segments)
     */
    public function postExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->createExamScheduleEntry($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/exam-schedule/{id} - Update exam schedule
     * Router calls: putExamSchedule($id, $data, $segments)
     */
    public function putExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        if ($id === null) {
            return $this->badRequest('Exam schedule ID is required for update');
        }
        $result = $this->api->updateExamScheduleEntry($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/exam-schedule/{id} - Delete exam schedule
     * Router calls: deleteExamSchedule($id, $data, $segments)
     */
    public function deleteExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage'])) {
            return $guard;
        }

        if ($id === null) {
            return $this->badRequest('Exam schedule ID is required for deletion');
        }
        $result = $this->api->deleteExamScheduleEntry($id);
        return $this->handleResponse($result);
    }

    // ==================== PROMOTION WORKFLOW ====================

    /**
     * POST /api/academic/promotions/start-workflow - Start promotion workflow
     */
    public function postPromotionsStartWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startPromotionWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/identify-candidates - Identify promotion candidates
     */
    public function postPromotionsIdentifyCandidates($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $result = $this->api->identifyPromotionCandidates($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/validate-eligibility - Validate promotion eligibility
     */
    public function postPromotionsValidateEligibility($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $result = $this->api->validatePromotionEligibility($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/execute - Execute promotions (Director or students_promote)
     * Body: { instance_id, apply_immediately (bool), effective_date (optional) }
     */
    public function postPromotionsExecute($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['students_promote', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to execute student promotions');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $result = $this->api->executePromotions($instanceId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/generate-reports - Generate promotion reports
     */
    public function postPromotionsGenerateReports($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $result = $this->api->generatePromotionReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ASSESSMENT WORKFLOW ====================

    /**
     * POST /api/academic/assessments/start-workflow - Start assessment workflow
     */
    public function postAssessmentsStartWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startAssessmentWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/create-items - Create assessment items
     */
    public function postAssessmentsCreateItems($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->createAssessmentItems(
            $data['instance_id'] ?? null,
            $data['items'] ?? $data['assessment_items'] ?? []
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/administer - Record assessment administration
     */
    public function postAssessmentsAdminister($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->administerAssessment(
            $data['instance_id'] ?? null,
            $data['administration_data'] ?? $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/mark-and-grade
     * Supports both:
     * - Workflow mode (instance_id + grading_data)
     * - Direct mode (assessment_id + grading_data/marks) fallback
     */
    public function postAssessmentsMarkAndGrade($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $instanceId = $data['instance_id'] ?? null;
        $assessmentId = $data['assessment_id'] ?? null;
        $gradingData = $data['grading_data'] ?? $data['marks_data'] ?? $data['marks'] ?? [];

        // Prefer direct mode when no workflow instance is provided.
        if (empty($instanceId) && !empty($assessmentId)) {
            $result = $this->api->saveAssessmentResults([
                'assessment_id' => (int) $assessmentId,
                'marks' => $gradingData,
                'is_final' => (bool) ($data['is_final'] ?? true),
                'marked_by' => $data['marked_by'] ?? null,
            ]);
            return $this->handleResponse($result);
        }

        $result = $this->api->markAndGradeAssessment($instanceId, $gradingData, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/analyze-results - Analyze assessment results
     */
    public function postAssessmentsAnalyzeResults($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->analyzeAssessmentResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== REPORT WORKFLOW ====================

    /**
     * POST /api/academic/reports/start-workflow - Start report workflow
     */
    public function postReportsStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->reportService->postReportsStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/reports/compile-data - Compile report data
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsCompileData($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->compileReportData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/generate-student-reports - Generate student reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsGenerateStudentReports($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->generateStudentReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/review-and-approve - Review and approve reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsReviewAndApprove($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->reviewAndApproveReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/distribute - Distribute reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsDistribute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->distributeReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== LIBRARY WORKFLOW ====================

    /**
     * POST /api/academic/library/start-workflow - Start library workflow
     */
    // (Removed duplicate, correct version already defined above)

    /**
     * POST /api/academic/library/review-request - Review library request
     */
    public function postLibraryReviewRequest($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->reviewLibraryRequest($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/catalog-resources - Catalog library resources
     */
    public function postLibraryCatalogResources($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->catalogLibraryResources($data['instance_id'] ?? null, $data['resources'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/distribute-and-track - Distribute and track resources
     */
    public function postLibraryDistributeAndTrack($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->distributeAndTrackResources($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM WORKFLOW ====================

    /**
     * POST /api/academic/curriculum/start-workflow - Start curriculum workflow
     */
    public function postCurriculumStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->postCurriculumStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/curriculum/map-outcomes - Map curriculum outcomes
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumMapOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) {
            return $guard;
        }

        $result = $this->api->mapCurriculumOutcomes($data['instance_id'] ?? null, $data['mappings'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/create-scheme - Create curriculum scheme
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumCreateScheme($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) {
            return $guard;
        }

        // Assuming createCurriculumScheme expects ($instanceId, $data)
        $result = $this->api->createCurriculumScheme($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/review-and-approve - Review and approve curriculum (Director only)
     * Body: { instance_id, action (approve|reject), comments }
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumReviewAndApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_approve', 'curriculum_approve', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to approve curriculum changes');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $action = strtolower($data['action'] ?? ($data['decision'] ?? 'approve'));
        $review = array_merge($data, [
            'approved' => ($action === 'approve'),
            'feedback' => $data['comments'] ?? ($data['feedback'] ?? []),
        ]);

        $result = $this->api->reviewAndApproveCurriculum($instanceId, $review);
        return $this->handleResponse($result);
    }

    // ==================== YEAR TRANSITION WORKFLOW ====================

    /**
     * POST /api/academic/year-transition/start-workflow - Start year transition workflow (Director only)
     * Body: { from_year, to_year, year_start_date, year_end_date, terms[] }
     */
    public function postYearTransitionStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->yearService->postYearTransitionStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/year-transition/archive-data - Archive academic data
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionArchiveData($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->archiveAcademicData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/execute-promotions - Execute year promotions
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionExecutePromotions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'students_promote', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->executeYearPromotions($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/setup-new-year - Setup new academic year (Director only)
     * Body: { instance_id, year_id (optional), class_structures[], clone_subjects, clone_staff_assignments }
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionSetupNewYear($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin']
        )) {
            return $this->forbidden('Only Director or System Admin can setup new academic year');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $yearConfig = $data['year_config'] ?? $data;
        $result = $this->api->setupNewAcademicYear($instanceId, $yearConfig);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/migrate-competency-baselines - Migrate competency baselines
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionMigrateCompetencyBaselines($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->migrateCompetencyBaselines($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/validate-readiness - Validate year readiness
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionValidateReadiness($id = null, $data = [], $segments = [])
    {
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC CALENDAR ====================

    /**
     * POST /api/academic/calendar/generate - Generate/regenerate term calendar
     * Body: { year_id, week_counts: {1:14, 2:14, 3:10} } (week_counts optional)
     */
    public function postCalendarGenerate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $yearId = $data['year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->generateAcademicCalendar((int) $yearId, $data['week_counts'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/calendar/get/{year_id} - Get generated calendar summary
     */
    public function getCalendarGet($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? ($data['year_id'] ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->getAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/calendar/list - Get calendar summary for a year
     * Query: ?year_id= (defaults to current year)
     */
    public function getCalendarList($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? ($data['year_id'] ?? null);
        if (!$yearId) {
            $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
            $yearId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if (!$yearId) {
            return $this->badRequest('No academic year found');
        }

        $result = $this->api->getAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/calendar/validate - Readiness check for the calendar
     * Body: { year_id }
     */
    public function postCalendarValidate($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->validateAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    // ==================== LEARNING AREAS ====================

    /**
     * POST /api/academic/learning-areas/seed - Seed per-class CBC learning areas
     * Body: { academic_year_id }
     */
    public function postLearningAreasSeed($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $yearId = $data['academic_year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('academic_year_id is required');
        }

        $result = $this->api->seedAcademicLearningAreas(['academic_year_id' => (int) $yearId]);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/learning-areas/coverage/{ayc_id} - Class curriculum coverage
     */
    public function getLearningAreasCoverage($id = null, $data = [], $segments = [])
    {
        $aycId = $id ?? ($data['academic_year_class_id'] ?? null);
        if (!$aycId) {
            return $this->badRequest('academic_year_class_id is required');
        }

        $result = $this->api->getClassLearningAreaCoverage(['academic_year_class_id' => (int) $aycId]);
        return $this->handleResponse($result);
    }

    // ==================== COMPETENCY & CORE VALUES ====================

    /**
     * POST /api/academic/competency/record-evidence - Record competency evidence
     */
    public function postCompetencyRecordEvidence($id = null, $data = [], $segments = [])
    {
        // Assuming recordCompetencyEvidence expects ($studentId, $competencyId, $evidence, $data)
        $result = $this->api->recordCompetencyEvidence(
            $data['student_id'] ?? null,
            $data['competency_id'] ?? null,
            $data['evidence'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/competency/record-core-value-evidence - Record core value evidence
     */
    public function postCompetencyRecordCoreValueEvidence($id = null, $data = [], $segments = [])
    {
        // Assuming recordCoreValueEvidence expects ($studentId, $coreValueId, $data)
        $result = $this->api->recordCoreValueEvidence(
            $data['student_id'] ?? null,
            $data['core_value_id'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/competency/dashboard - Get competency dashboard
     */
    public function getCompetencyDashboard($id = null, $data = [], $segments = [])
    {
        $studentId = isset($data['student_id']) ? $data['student_id'] : null;
        $termId = isset($data['term_id']) ? $data['term_id'] : null;
        $result = $this->api->getCompetencyDashboard($studentId, $termId);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC YEARS ====================

    /**
     * GET /api/academic/years/list - Get all academic years
     */
    // TODO: Delegate to AcademicYearService
    public function getYearsList($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsList($id, $data, $segments, $this);
    }

    /**
     * GET /api/academic/years/current - Get current academic year
     */
    // TODO: Delegate to AcademicYearService
    public function getYearsCurrent($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsCurrent($id, $data, $segments, $this);
    }

    /**
     * GET /api/academic/years/get/{id} - Get academic year by ID
     */
    // TODO: Delegate to AcademicYearService
    public function getYearsGet($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsGet($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/years/create - Create academic year
     */
    // TODO: Delegate to AcademicYearService
    public function postYearsCreate($id = null, $data = [], $segments = [])
    {
        return $this->yearService->postYearsCreate($id, $data, $segments, $this);
    }

    /**
     * PUT /api/academic/years/update/{id} - Update academic year
     */
    // TODO: Delegate to AcademicYearService
    public function putYearsUpdate($id = null, $data = [], $segments = [])
    {
        return $this->yearService->putYearsUpdate($id, $data, $segments, $this);
    }

    /**
     * DELETE /api/academic/years/delete/{id} - Delete academic year
     */
    // TODO: Delegate to AcademicYearService
    public function deleteYearsDelete($id = null, $data = [], $segments = [])
    {
        return $this->yearService->deleteYearsDelete($id, $data, $segments, $this);
    }

    /**
     * PUT /api/academic/years/set-current - Set year as current (Director/System Admin only)
     * Accepts year_id from URL segment (/set-current/5) or from request body (year_id or id)
     */
    // TODO: Delegate to AcademicYearService
    public function putYearsSetCurrent($id = null, $data = [], $segments = [])
    {
        // Only Director (role_id=3) or System Admin (role_id=1) may change the current year
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin', 'systemadmin']
        )) {
            return $this->forbidden('Only Director or System Admin can set the current academic year');
        }

        $yearId = $id ?? ($data['year_id'] ?? ($data['id'] ?? null));
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->setCurrentAcademicYear((int) $yearId);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC TERMS ====================

    /**
     * POST /api/academic/terms/create - Create academic term
     */
    public function postTermsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAcademicTerm($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/terms/list - Get all academic terms
     */
    public function getTermsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAcademicTerms($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/terms/update/{id} - Update academic term
     */
    public function putTermsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAcademicTerm($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/timetable-stats?term_id= - Timetable coverage for a term
     * (backed by the live timetable_entries table, unlike /schedules/timetable-get).
     */
    public function getTimetableStats($id = null, $data = [], $segments = [])
    {
        try {
            $termId = (int)($data['term_id'] ?? 0);
            $where = '';
            $bindings = [];
            if ($termId > 0) {
                $where = 'WHERE te.academic_year_term_id = ?';
                $bindings[] = $termId;
            }

            $stmt = $this->db->query(
                "SELECT te.id, te.academic_year_class_stream_id, te.academic_year_term_id,
                        te.day_of_week, te.time_slot_id, te.teacher_id,
                        ayc.class_id, c.name AS class_name
                 FROM timetable_entries te
                 JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 {$where}
                 ORDER BY te.day_of_week, te.time_slot_id",
                $bindings
            );
            $slots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $classes = count(array_unique(array_map(function ($s) {
                return (int)$s['class_id'];
            }, $slots)));
            $teachers = count(array_unique(array_filter(array_map(function ($s) {
                return (int)$s['teacher_id'];
            }, $slots))));

            return $this->success([
                'slots' => $slots,
                'class_count' => $classes,
                'teacher_count' => $teachers,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] getTimetableStats: ' . $e->getMessage());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/term-transition/context - Normalized term transition context
     */
    public function getTermTransitionContext($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getTermTransitionContext($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/term-transition/execute - Atomic term transition
     */
    public function postTermTransitionExecute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->executeTermTransition($data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/terms/delete/{id} - Delete academic term
     */
    public function deleteTermsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAcademicTerm($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/terms - Bare alias of getTermsList.
     * term_dates.js calls the bare `/academic/terms?academic_year_id=` route.
     */
    public function getTerms($id = null, $data = [], $segments = [])
    {
        return $this->getTermsList($id, $data, $segments);
    }

    /**
     * POST /api/academic/terms - Bare alias of postTermsCreate.
     * term_dates.js POSTs a new term to the bare `/academic/terms` route.
     */
    public function postTerms($id = null, $data = [], $segments = [])
    {
        return $this->postTermsCreate($id, $data, $segments);
    }

    /**
     * PUT /api/academic/terms/{id} - Bare alias of putTermsUpdate.
     * term_dates.js PUTs updates to `/academic/terms/{id}`.
     */
    public function putTerms($id = null, $data = [], $segments = [])
    {
        return $this->putTermsUpdate($id, $data, $segments);
    }

    // ==================== LEARNING AREAS (SUBJECTS) ====================

    /**
     * GET /api/academic/learning-areas/list - List all learning areas
     */
    public function getLearningAreasList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLearningAreasList($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/learning-areas - Alias for learning-areas-list.
     * Router convention: /academic/learning-areas → getLearningAreas().
     */
    public function getLearningAreas($id = null, $data = [], $segments = [])
    {
        return $this->getLearningAreasList($id, $data, $segments);
    }

    /**
     * GET /api/academic/subjects-list - Alias for learning-areas-list.
     * The frontend consistently calls subjects "subjects" while the data model
     * and API name them "learning_areas". This adapter keeps the UI contract
     * stable without re-pointing 15+ call sites, and resolves the slug that the
     * router previously fell through to the generic get() (subjects-list -> getSubjectList).
     */
    public function getSubjectsList($id = null, $data = [], $segments = [])
    {
        return $this->getLearningAreasList($id, $data, $segments);
    }

    public function getSubjects($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->getLearningAreasGet($id, $data, $segments);
        }

        return $this->getLearningAreasList($id, $data, $segments);
    }

    /**
     * GET /api/academic/learning-areas/get/{id} - Get specific learning area
     */
    public function getLearningAreasGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->get($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/learning-areas/create - Create learning area
     */
    public function postLearningAreasCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    public function postSubjects($id = null, $data = [], $segments = [])
    {
        return $this->postLearningAreasCreate($id, $data, $segments);
    }

    /**
     * PUT /api/academic/learning-areas/update/{id} - Update learning area
     */
    public function putLearningAreasUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    public function putSubjects($id = null, $data = [], $segments = [])
    {
        return $this->putLearningAreasUpdate($id, $data, $segments);
    }

    /**
     * DELETE /api/academic/learning-areas/delete/{id} - Delete learning area
     */
    public function deleteLearningAreasDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    public function deleteSubjects($id = null, $data = [], $segments = [])
    {
        return $this->deleteLearningAreasDelete($id, $data, $segments);
    }

    // ==================== CLASS MANAGEMENT ====================

    /**
     * POST /api/academic/classes/create - Create class
     */
    public function postClassesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createClass($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/list - List all classes
     */
    public function getClassesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listClasses($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/class-capacity - Stream-level capacity and enrollment
     */
    public function getClassCapacity($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClassCapacity($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/assessments-list - List assessments with submission stats
     */
    public function getAssessmentsList($id = null, $data = [], $segments = [])
    {
        try {
            $where  = ['1=1'];
            $params = [];
            if (!empty($_GET['class_id']))           { $where[] = 'a.academic_year_class_stream_id=:cid'; $params[':cid']  = (int)$_GET['class_id']; }
            if (!empty($_GET['term_id']))             { $where[] = 'a.academic_year_term_id=:tid';      $params[':tid']  = (int)$_GET['term_id']; }
            if (!empty($_GET['subject_id']))          { $where[] = 'a.learning_area_id=:sid';   $params[':sid']  = (int)$_GET['subject_id']; }
            if (!empty($_GET['status']))              { $where[] = 'a.status=:st';        $params[':st']   = $_GET['status']; }
            if (!empty($_GET['assessment_type_id'])) { $where[] = 'a.assessment_type_id=:atid'; $params[':atid'] = (int)$_GET['assessment_type_id']; }

            $stmt = $this->db->query(
                "SELECT a.id, a.academic_year_class_stream_id, a.academic_year_term_id, a.learning_area_id, a.title, a.max_marks,
                        a.assessment_date, a.status, a.assessment_type_id,
                        c.name  AS class_name, sn.name AS stream_name,
                        la.name AS learning_area_name, la.code AS learning_area_code,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        t.name  AS term_name, t.code AS term_number,
                        COUNT(DISTINCT fs.student_id) AS graded_count,
                        COUNT(DISTINCT sae.student_id) AS total_students,
                        ROUND(AVG(fs.percentage), 2)  AS average_pct
                 FROM assessments a
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN formative_scores fs ON fs.assessment_id = a.id
                 LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = a.academic_year_class_stream_id
                        AND sae.enrollment_status IN ('active','completed')
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY a.id
                 ORDER BY a.assessment_date DESC, a.id DESC
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/grading-results - List student grading rows with filters
     */
    public function getGradingResults($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getGradingResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/results-analysis - Aggregate class/subject performance metrics
     */
    public function getResultsAnalysis($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getResultsAnalysis($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/performance-overview - Performance overview for student/class/stream/school views
     */
    public function getPerformanceOverview($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPerformanceOverview($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/student-results - Get result summary for one student
     */
    public function getStudentResults($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['student_id'] = $id;
        }
        $result = $this->api->getStudentResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/report-cards/download/{student_id}
     * Route compatibility endpoint for report card download payload.
     * Returns normalized student-results data consumable by frontend exporters.
     */
    public function getReportCardsDownload($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['student_id'] = (int) $id;
        }

        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }

        $result = $this->api->getStudentResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/report-cards - Full report card payload for one student.
     * Accepts student_id (+ optional term_id) via query params. Returns normalized
     * subject rows, attendance, averages and grades consumable by the report-card
     * print/export flow. Mirrors getReportCardsDownload but sources ids from data.
     */
    public function getReportCards($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['student_id'] = (int) $id;
        }

        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }

        $result = $this->api->getStudentResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/get/{id} - Get specific class
     */
    public function getClassesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClass($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/classes/update/{id} - Update class
     */
    public function putClassesUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateClass($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/classes/delete/{id} - Delete class
     */
    public function deleteClassesDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteClass($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/assign-teacher - Assign class teacher
     */
    public function postClassesAssignTeacher($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignClassTeacher($data['class_id'] ?? null, $data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/auto-create-streams - Auto-create class streams
     */
    public function postClassesAutoCreateStreams($id = null, $data = [], $segments = [])
    {
        $result = $this->api->autoCreateStreams($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CLASS STREAMS ====================

    /**
     * POST /api/academic/streams/create - Create stream
     */
    public function postStreamsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createStream($data['class_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/streams/list - List class streams
     */
    public function getStreamsList($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? $id ?? null;
        $result = $this->api->listClassStreams($classId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/streams/get/{id} - Get specific stream
     */
    public function getStreamsGet($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->getStream($streamId);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/streams/update/{id} - Update class stream
     */
    public function putStreamsUpdate($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->updateStream($streamId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/streams/delete/{id} - Delete/deactivate stream
     */
    public function deleteStreamsDelete($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->deleteStream($streamId);
        return $this->handleResponse($result);
    }

    // ==================== CLASS STREAMS (bare slug /academic/class-streams) ====================
    // manage_classes.js calls the BARE slug with CRUD verbs (no /list, /create sub-segments).
    // Router maps: GET -> getClassStreams, POST -> postClassStreams, PUT -> putClassStreams,
    // DELETE -> deleteClassStreams. These differ from the streams/* handlers (getStreamsList, etc.)
    // — the "streams" slug is a separate alias. Reuse the same AcademicAPI methods to avoid drift.

    public function getClassStreams($id = null, $data = [], $segments = [])
    {
        $classId = !empty($_GET['class_id']) ? (int) $_GET['class_id'] : ($data['class_id'] ?? $id ?? null);
        return $this->handleResponse($this->api->listClassStreams($classId));
    }

    public function postClassStreams($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? null;
        return $this->handleResponse($this->api->createStream($classId, $data));
    }

    public function putClassStreams($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        return $this->handleResponse($this->api->updateStream($streamId, $data));
    }

    public function deleteClassStreams($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        return $this->handleResponse($this->api->deleteStream($streamId));
    }

    // ==================== CLASS SCHEDULES ====================

    /**
     * POST /api/academic/schedules/create - Create class schedule
     */
    public function postSchedulesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createClassSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/list - List class schedules
     */
    public function getSchedulesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listClassSchedules($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/get/{id} - Get specific schedule
     */
    public function getSchedulesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClassSchedule($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/schedules/update/{id} - Update schedule
     */
    public function putSchedulesUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateClassSchedule($id ?? ($data['id'] ?? null), $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/schedules/delete/{id} - Delete schedule
     */
    public function deleteSchedulesDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteClassSchedule($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/schedules/assign-room - Assign room to schedule
     */
    public function postSchedulesAssignRoom($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignRoom($data['schedule_id'] ?? null, $data['room_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM UNITS ====================

    /**
     * POST /api/academic/curriculum-units/create - Create curriculum unit
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnitsCreate($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->postCurriculumUnitsCreate($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->postCurriculumUnitsCreate($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsList($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->getCurriculumUnitsList($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnits($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->getCurriculumUnitsGet($id, $data, $segments);
        }

        return $this->getCurriculumUnitsList($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsGet($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->getCurriculumUnitsGet($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnitsUpdate($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->putCurriculumUnitsUpdate($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->putCurriculumUnitsUpdate($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnitsDelete($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->deleteCurriculumUnitsDelete($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->deleteCurriculumUnitsDelete($id, $data, $segments);
    }

    // ==================== UNIT TOPICS ====================

    /**
     * POST /api/academic/topics/create - Create unit topic
     */
    public function postTopicsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createUnitTopic($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/list - List unit topics
     */
    public function getTopicsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listUnitTopics($data['unit_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/get/{id} - Get specific topic
     */
    public function getTopicsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getUnitTopic($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/topics/update/{id} - Update topic
     */
    public function putTopicsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateUnitTopic($id ?? ($data['id'] ?? null), $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/topics/delete/{id} - Delete topic
     */
    public function deleteTopicsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteUnitTopic($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    // ==================== LESSON PLANS ====================

    /**
     * POST /api/academic/lesson-plans/create - Create lesson plan
     */
    public function postLessonPlansCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createLessonPlan($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/list - List lesson plans
     * Passes full query params so getLessonPlans can filter by
     * teacher_id, class_id, status, term_id, academic_year_id, etc.
     */
    public function getLessonPlansList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonPlans($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/get/{id} - Get specific lesson plan
     */
    public function getLessonPlansGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonPlan($id ?? $data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/update/{id} - Update lesson plan
     */
    public function putLessonPlansUpdate($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['id'] ?? null;
        $result = $this->api->updateLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/lesson-plans/delete/{id} - Delete lesson plan
     */
    public function deleteLessonPlansDelete($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['id'] ?? null;
        $result = $this->api->deleteLessonPlan($planId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/approve - Approve lesson plan
     */
    public function postLessonPlansApprove($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->approveLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/reject - Reject lesson plan
     */
    public function postLessonPlansReject($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->rejectLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/submit - Submit lesson plan for review
     */
    public function postLessonPlansSubmit($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->submitLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/approval - List plans pending approval (for headteacher)
     */
    public function getLessonPlansApproval($id = null, $data = [], $segments = [])
    {
        // Default to 'submitted' status for the approval queue
        if (empty($data['status'])) {
            $data['status'] = 'submitted';
        }
        $result = $this->api->getLessonPlans($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/review/{id} - Submit review (approve/reject) for a lesson plan
     */
    public function putLessonPlansReview($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['plan_id'] ?? null;
        $status = $data['status'] ?? null;

        if ($status === 'approved') {
            $result = $this->api->approveLessonPlan($planId, $data);
        } elseif ($status === 'rejected') {
            $result = $this->api->rejectLessonPlan($planId, $data);
        } else {
            return $this->handleResponse(errorResponse('Invalid review status. Must be "approved" or "rejected"', 400));
        }
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/bulk-approve - Bulk approve multiple plans
     */
    public function putLessonPlansBulkApprove($id = null, $data = [], $segments = [])
    {
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return $this->handleResponse(errorResponse('No plan IDs provided', 400));
        }

        $results = ['approved' => 0, 'failed' => 0, 'errors' => []];
        foreach ($ids as $planId) {
            $result = $this->api->approveLessonPlan($planId, $data);
            if (isset($result['status']) && $result['status'] === 'success') {
                $results['approved']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Plan #{$planId}: " . ($result['message'] ?? 'Unknown error');
            }
        }

        return $this->handleResponse(successResponse([
            'message' => "{$results['approved']} plans approved, {$results['failed']} failed",
            'data' => $results
        ]));
    }

    // ==================== LESSON OBSERVATIONS ====================

    /**
     * POST /api/academic/lesson-observations/create - Create lesson observation
     */
    public function postLessonObservationsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createLessonObservation($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-observations/list - List lesson observations
     */
    public function getLessonObservationsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonObservations($data['filters'] ?? []);
        return $this->handleResponse($result);
    }

    // ==================== SCHEME OF WORK ====================

    /**
     * POST /api/academic/scheme-of-work/create - Create scheme of work
     */
    public function postSchemeOfWorkCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createSchemeOfWork($data);
        return $this->handleResponse($result);
    }

    public function postSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $data['created_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->postSchemeOfWorkCreate($id, $data, $segments);
    }

    /**
     * POST /api/academic/schemes-of-work/generate
     * Auto-generate weekly scheme-of-work entries from the seeded CBC curriculum
     * (strands -> sub-strands -> learning outcomes) for a selected learning area/class/term.
     */
    public function postSchemesOfWorkGenerate($id = null, $data = [], $segments = [])
    {
        $data['created_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->generateSchemeOfWork($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/scheme-of-work/get/{id} - Get scheme of work
     */
    public function getSchemeOfWorkGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSchemeOfWork($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    public function getSchemesOfWork($id = null, $data = [], $segments = [])
    {
        return $this->getSchemeOfWorkGet($id, $data, $segments);
    }

    public function putSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $action = $segments[0] ?? null;
        if ($action === 'approve') {
            $result = $this->api->approveSchemeOfWork($id, $data);
        } elseif ($action === 'reject') {
            $result = $this->api->rejectSchemeOfWork($id, $data);
        } else {
            $result = $this->api->updateSchemeOfWork($id, $data);
        }

        return $this->handleResponse($result);
    }

    public function postSchemesOfWorkApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->approveSchemeOfWork($id, $data);
        return $this->handleResponse($result);
    }

    public function putSchemesOfWorkApprove($id = null, $data = [], $segments = [])
    {
        return $this->postSchemesOfWorkApprove($id, $data, $segments);
    }

    public function postSchemesOfWorkReject($id = null, $data = [], $segments = [])
    {
        $result = $this->api->rejectSchemeOfWork($id, $data);
        return $this->handleResponse($result);
    }

    public function putSchemesOfWorkReject($id = null, $data = [], $segments = [])
    {
        return $this->postSchemesOfWorkReject($id, $data, $segments);
    }

    public function deleteSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteSchemeOfWork($id);
        return $this->handleResponse($result);
    }

    // ==================== TEACHER OPERATIONS ====================

    /**
     * GET /api/academic/teachers/classes - Get teacher's classes
     */
    public function getTeachersClasses($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherClasses($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/subjects - Get teacher's subjects
     */
    public function getTeachersSubjects($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherSubjects($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/schedule - Get teacher's schedule
     */
    public function getTeachersSchedule($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherSchedule($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/list - List available teaching staff
     */
    public function getTeachersList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listTeachers($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/subjects/teachers - Get subject teachers
     */
    public function getSubjectsTeachers($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSubjectTeachers($data['subject_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/subject-teachers - Bare alias of getSubjectsTeachers.
     * Subject pages call the kebab `subject-teachers?subject_id=` route.
     */
    public function getSubjectTeachers($id = null, $data = [], $segments = [])
    {
        return $this->getSubjectsTeachers($id, $data, $segments);
    }

    // ==================== WORKFLOW STATUS ====================

    /**
     * GET /api/academic/workflow/status - Get workflow status
     */
    public function getWorkflowStatus($id = null, $data = [], $segments = [])
    {
        $workflowType = $data['workflow_type'] ?? $data['type'] ?? null;
        $instanceId = $data['instance_id'] ?? null;
        if (empty($workflowType) || empty($instanceId)) {
            return $this->badRequest('workflow_type and instance_id are required');
        }
        $result = $this->api->getWorkflowStatus($workflowType, $instanceId);
        return $this->handleResponse($result);
    }

    // ==================== CUSTOM OPERATIONS ====================

    /**
     * GET /api/academic/custom - Handle custom GET operations
     */
    public function getCustom($id = null, $data = [], $segments = [])
    {
        $result = $this->api->handleCustomGet(
            $data['id'] ?? $id,
            $data['action'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/custom - Handle custom POST operations
     */
    public function postCustom($id = null, $data = [], $segments = [])
    {
        $result = $this->api->handleCustomPost(
            $data['id'] ?? $id,
            $data['action'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    // ==================== CBC: FORMATIVE ASSESSMENTS ====================

    /**
     * GET  /api/academic/formative-assessments         → list formative assessments
     * POST /api/academic/formative-assessments         → create formative assessment
     */
    public function getFormativeAssessments($id = null, $data = [], $segments = [])
    {
        try {
            $db     = $this->db;
            $where  = ["a.assessment_type_id IS NOT NULL"];
            $params = [];

            // Join to assessment_types and filter is_formative=1
            $where[] = "at.is_formative = 1";

            if (!empty($_GET['class_id']))    { $where[] = "a.academic_year_class_stream_id=:cid";     $params[':cid'] = (int)$_GET['class_id']; }
            if (!empty($_GET['subject_id']))  { $where[] = "a.learning_area_id=:sid";   $params[':sid'] = (int)$_GET['subject_id']; }
            if (!empty($_GET['term_id']))     { $where[] = "a.academic_year_term_id=:tid";      $params[':tid'] = (int)$_GET['term_id']; }
            if (!empty($_GET['type_id']))     { $where[] = "a.assessment_type_id=:atid"; $params[':atid'] = (int)$_GET['type_id']; }

            $stmt = $db->query(
                "SELECT a.*,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        c.name AS class_name,
                        t.name AS term_name,
                        CONCAT(p.first_name,' ',p.last_name) AS assigned_by_name
                 FROM assessments a
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN staff st ON st.id = a.assigned_by
                 LEFT JOIN persons p ON p.id = st.person_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date DESC
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    public function postFormativeAssessments($id = null, $data = [], $segments = [])
    {
        try {
            $required = ['class_id','subject_id','term_id','title','assessment_type_id','max_marks'];
            foreach ($required as $f) {
                if (empty($data[$f])) return $this->badRequest("$f is required");
            }
            // Verify type is formative
            $typeCheck = $this->db->query("SELECT is_formative FROM assessment_types WHERE id=:id LIMIT 1", [':id' => (int)$data['assessment_type_id']]);
            $type = $typeCheck->fetch(\PDO::FETCH_ASSOC);
            if (!$type || !$type['is_formative']) return $this->badRequest('assessment_type_id must refer to a formative type');

            $staffId = $this->user['staff_id'] ?? null;
            if (!$staffId && !empty($this->user['id'])) {
                $staffId = $this->db->query(
                    "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = :uid LIMIT 1",
                    [':uid' => (int)$this->user['id']]
                )->fetchColumn();
            }
            $staffId = $staffId ? (int)$staffId : null;
            $this->db->query(
                "INSERT INTO assessments
                    (academic_year_class_stream_id, learning_area_id, academic_year_term_id, title, max_marks, assessment_date, assigned_by, assessment_type_id, status)
                 VALUES
                    (:cid, :sid, :tid, :title, :marks, :dt, :aby, :atid, 'pending_submission')",
                [
                    ':cid'   => (int)$data['class_id'],
                    ':sid'   => (int)$data['subject_id'],
                    ':tid'   => (int)$data['term_id'],
                    ':title' => trim($data['title']),
                    ':marks' => (float)$data['max_marks'],
                    ':dt'    => $data['assessment_date'] ?? date('Y-m-d'),
                    ':aby'   => $staffId,
                    ':atid'  => (int)$data['assessment_type_id'],
                ]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Formative assessment created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/academic/formative-assessments/{id}/marks → bulk mark entry
     */
    /**
     * GET /api/academic/formative-assessment-marks?assessment_id=X
     * Returns all students for the assessment's class with their existing scores (or null).
     */
    public function getFormativeAssessmentMarks($id = null, $data = [], $segments = [])
    {
        try {
            $id = $id ?? (int)($_GET['assessment_id'] ?? 0);
            if (!$id) return $this->badRequest('assessment_id is required');

            // Get assessment + class info
            $aStmt = $this->db->query(
                "SELECT a.id, a.academic_year_class_stream_id, a.max_marks, a.title,
                        c.name AS class_name
                 FROM assessments a
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE a.id=:id LIMIT 1",
                [':id' => (int)$id]
            );
            $assessment = $aStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$assessment) return $this->notFound('Assessment not found');

            // Get all active students enrolled in the assessment's class stream
            $sStmt = $this->db->query(
                "SELECT s.id AS student_id, p.first_name, p.last_name, s.admission_no,
                        fs.score, fs.max_score, fs.percentage, fs.cbc_grade, fs.remarks
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_class_stream_id = :aycs
                      AND sae.enrollment_status = 'active'
                 LEFT JOIN formative_scores fs ON fs.student_id = s.id AND fs.assessment_id = :aid
                 WHERE s.status = 'active'
                 ORDER BY p.last_name, p.first_name",
                [':aycs' => (int)$assessment['academic_year_class_stream_id'], ':aid' => (int)$id]
            );
            return $this->success($sStmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/academic/formative-assessment-marks
     * Bulk upsert marks for an assessment. Payload: { assessment_id, marks: [{student_id, score, remarks}] }
     */
    public function postFormativeAssessmentMarks($id = null, $data = [], $segments = [])
    {
        try {
            $assessmentId = $id ?? (int)($data['assessment_id'] ?? 0);
            if (!$assessmentId) return $this->badRequest('assessment_id is required');
            $id = $assessmentId;
            // Accept both 'marks' and 'scores' keys for compatibility
            $scores = $data['marks'] ?? $data['scores'] ?? [];
            if (empty($scores)) return $this->badRequest('marks array is required');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            // Get max_marks for this assessment
            $maxStmt = $this->db->query("SELECT max_marks FROM assessments WHERE id=:id LIMIT 1", [':id' => (int)$id]);
            $asmnt = $maxStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$asmnt) return $this->notFound('Assessment not found');
            $maxMarks = (float)$asmnt['max_marks'];

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO formative_scores (assessment_id, student_id, score, max_score, remarks, entered_by)
                 VALUES (:aid, :sid, :score, :max, :rmk, :eby)
                 ON DUPLICATE KEY UPDATE score=:score, max_score=:max, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($scores as $entry) {
                $ins->execute([
                    ':aid'   => (int)$id,
                    ':sid'   => (int)$entry['student_id'],
                    ':score' => min((float)($entry['marks_obtained'] ?? $entry['score'] ?? 0), $maxMarks),
                    ':max'   => $maxMarks,
                    ':rmk'   => $entry['remarks'] ?? null,
                    ':eby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($scores)], 'Marks saved successfully');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/formative-summary?class_id=&subject_id=&term_id=&strand_id=&sub_strand_id=&group_by=
     *
     * Returns per-student formative averages grouped by learning area, strand,
     * or sub-strand. `group_by` accepts 'learning_area' (default), 'strand',
     * or 'sub_strand'. Passing strand_id/sub_strand_id forces the matching
     * grouping level so CBC per-strand/sub-strand reporting is supported.
     */
    public function getFormativeSummary($id = null, $data = [], $segments = [])
    {
        try {
            $classId    = (int)($_GET['class_id']     ?? 0);
            $subjectId  = (int)($_GET['subject_id']   ?? 0);
            $termId     = (int)($_GET['term_id']      ?? 0);
            $strandId   = (int)($_GET['strand_id']    ?? 0);
            $subStrandId= (int)($_GET['sub_strand_id'] ?? 0);
            $groupBy    = $_GET['group_by'] ?? 'learning_area';
            if (!$classId || !$termId) return $this->success([], 'No filters selected — specify class_id and term_id');

            $mode = in_array($groupBy, ['learning_area', 'strand', 'sub_strand'], true) ? $groupBy : 'learning_area';
            if ($subStrandId)  $mode = 'sub_strand';
            elseif ($strandId) $mode = 'strand';

            $params = [':tid' => $termId, ':cid' => $classId, ':sid1' => $subjectId, ':sid2' => $subjectId];

            $filters = '';
            if ($strandId)    { $filters .= ' AND st.id = :stid';    $params[':stid'] = $strandId; }
            if ($subStrandId) { $filters .= ' AND ss.id = :ssid';    $params[':ssid'] = $subStrandId; }

            $gradeCase = "CASE
                            WHEN AVG(fs.percentage) >= 75 THEN 'EE'
                            WHEN AVG(fs.percentage) >= 60 THEN 'ME'
                            WHEN AVG(fs.percentage) >= 40 THEN 'AE'
                            ELSE 'BE'
                         END AS formative_grade";

            if ($mode === 'sub_strand') {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        st.id AS strand_id, st.name AS strand_name,
                        ss.id AS sub_strand_id, ss.name AS sub_strand_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, st.id, ss.id';
                $order  = 's.last_name, la.name, st.sort_order, ss.sort_order';
            } elseif ($mode === 'strand') {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        st.id AS strand_id, st.name AS strand_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, st.id';
                $order  = 's.last_name, la.name, st.sort_order';
            } else {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, la.id';
                $order  = 's.last_name, la.name';
            }

            $stmt = $this->db->query(
                "SELECT
                    s.id AS student_id,
                    CONCAT(p.first_name,' ',p.last_name) AS student_name,
                    s.admission_no,
                    $select
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_class_stream_id = :cid
                      AND sae.enrollment_status = 'active'
                 JOIN formative_scores fs ON fs.student_id = s.id
                 JOIN assessments a ON a.id = fs.assessment_id AND a.academic_year_term_id = :tid
                 JOIN assessment_types at ON at.id = a.assessment_type_id AND at.is_formative = 1
                 JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.id = a.sub_strand_id
                 LEFT JOIN strands st ON st.id = a.strand_id
                 WHERE (:sid1 = 0 OR la.id = :sid2)
                   AND s.status = 'active'
                   $filters
                 GROUP BY $group
                 ORDER BY $order",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: ASSESSMENT TYPES ====================

    /**
     * GET /api/academic/assessment-tools → list all CBC assessment tools
     */
    public function getAssessmentTools($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query(
                "SELECT at.id, at.tool_name, at.tool_code, at.description, at.assessment_type_id, at.learning_area_id, at.grade_level,
                        a_type.name AS assessment_type_name, la.name AS learning_area_name
                 FROM assessment_tools at
                 LEFT JOIN assessment_type_classifications a_type ON a_type.id = at.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = at.learning_area_id
                 WHERE at.status = 'active'
                 ORDER BY at.tool_name"
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/assessment-tools */
    public function postAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        try {
            $name = trim((string)($data['tool_name'] ?? ''));
            $typeId = (int)($data['assessment_type_id'] ?? 0);
            $areaId = (int)($data['learning_area_id'] ?? 0);
            if ($name === '' || !$typeId || !$areaId) {
                return $this->badRequest('tool_name, assessment_type_id, and learning_area_id are required');
            }

            $type = $this->db->query(
                "SELECT id FROM assessment_type_classifications WHERE id = :id AND status = 'active'",
                [':id' => $typeId]
            )->fetch(\PDO::FETCH_ASSOC);
            $area = $this->db->query(
                "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                [':id' => $areaId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$type || !$area) return $this->badRequest('Assessment type or learning area is invalid');

            $createdBy = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            if (!$createdBy) return $this->unauthorized('Authenticated user is required');

            $this->db->query(
                "INSERT INTO assessment_tools
                    (tool_name, tool_code, description, assessment_type_id, learning_area_id,
                     grade_level, competencies_assessed, created_by, status)
                 VALUES (:name, :code, :description, :type_id, :area_id, :grade, :competencies, :created_by, 'active')",
                [
                    ':name' => $name,
                    ':code' => trim((string)($data['tool_code'] ?? '')) ?: null,
                    ':description' => trim((string)($data['description'] ?? '')) ?: null,
                    ':type_id' => $typeId,
                    ':area_id' => $areaId,
                    ':grade' => trim((string)($data['grade_level'] ?? '')) ?: null,
                    ':competencies' => $data['competencies_assessed'] ?? null,
                    ':created_by' => $createdBy,
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Assessment tool created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/assessment-tools/{id} */
    public function putAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Assessment tool ID is required');
        try {
            $fields = [];
            $params = [':id' => (int)$id];
            foreach (['tool_name', 'tool_code', 'description', 'grade_level', 'competencies_assessed', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field] === '' ? null : $data[$field];
                }
            }
            foreach (['assessment_type_id', 'learning_area_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $value = (int)$data[$field];
                    $table = $field === 'assessment_type_id' ? 'assessment_type_classifications' : 'learning_areas';
                    $valid = $this->db->query(
                        "SELECT id FROM $table WHERE id = :id AND status = 'active'",
                        [':id' => $value]
                    )->fetch(\PDO::FETCH_ASSOC);
                    if (!$valid) return $this->badRequest("Invalid $field");
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            if (!$fields) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE assessment_tools SET " . implode(', ', $fields) . " WHERE id = :id", $params);
            return $this->success(['id' => (int)$id], 'Assessment tool updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/assessment-tools/{id} */
    public function deleteAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Assessment tool ID is required');
        try {
            // Preserve historical rubrics by archiving tools instead of deleting them.
            $this->db->query(
                "UPDATE assessment_tools SET status = 'archived' WHERE id = :id",
                [':id' => (int)$id]
            );
            return $this->success(['id' => (int)$id], 'Assessment tool archived');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/assessment-types → list all CBC assessment types
     */
    public function getAssessmentTypes($id = null, $data = [], $segments = [])
    {
        try {
            $filter = $_GET['filter'] ?? 'all'; // all | formative | summative | national
            $where  = ["status='active'"];
            if ($filter === 'formative')  $where[] = "is_formative=1";
            if ($filter === 'summative')  $where[] = "is_summative=1";
            if ($filter === 'national')   $where[] = "name IN ('KNEC Grade 3 Assessment','KPSEA','KJSEA')";

            $stmt = $this->db->query("SELECT * FROM assessment_types WHERE " . implode(' AND ', $where) . " ORDER BY is_formative DESC, name");
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** GET /api/academic/assessment-classifications */
    public function getAssessmentClassifications($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query(
                "SELECT id, code, name, description, is_national, is_knec_managed, grade_applicable
                 FROM assessment_type_classifications
                 WHERE status = 'active'
                 ORDER BY id"
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/core-competencies-list → CBC 8 core competencies from DB
     */
    public function getCoreCompetenciesList($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query("SELECT id, code, name, description FROM core_competencies WHERE status='active' ORDER BY sort_order, id");
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/core-competencies — Router-friendly alias.
     */
    public function getCoreCompetencies($id = null, $data = [], $segments = [])
    {
        return $this->getCoreCompetenciesList($id, $data, $segments);
    }

    /**
     * GET /api/academic/core-values-list — CBC core values from DB
     */
    public function getCoreValuesList($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query("SELECT id, code, name, description FROM core_values WHERE status='active' ORDER BY sort_order, id");
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/core-values — Router-friendly alias.
     */
    public function getCoreValues($id = null, $data = [], $segments = [])
    {
        return $this->getCoreValuesList($id, $data, $segments);
    }

    // ==================== CBC: COMPETENCY RATINGS ====================

    /**
     * GET  /api/academic/competency-ratings?class_id=&term_id=&student_id=
     * POST /api/academic/competency-ratings  → bulk upsert
     */
    public function getCompetencyRatings($id = null, $data = [], $segments = [])
    {
        try {
            $termId    = (int)($_GET['term_id']    ?? 0);
            $classId   = (int)($_GET['class_id']   ?? 0);
            $studentId = (int)($_GET['student_id'] ?? 0);
            if (!$termId) return $this->badRequest('term_id is required');

            $where  = ['lc.term_id = :tid'];
            $params = [':tid' => $termId];
            if ($studentId) { $where[] = 'lc.student_id=:sid'; $params[':sid'] = $studentId; }
            elseif ($classId) {
                $where[] = "lc.student_id IN (SELECT sae.student_id FROM student_academic_enrollments sae
                              WHERE sae.academic_year_class_stream_id = :cid AND sae.enrollment_status = 'active')";
                $params[':cid'] = $classId;
            }

            $stmt = $this->db->query(
                "SELECT lc.*,
                        cc.code AS competency_code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 JOIN students s ON s.id = lc.student_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, cc.sort_order",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    public function postCompetencyRatings($id = null, $data = [], $segments = [])
    {
        try {
            $ratings = $data['ratings'] ?? []; // [{student_id, competency_id, level_code, evidence, notes}]
            $termId  = (int)($data['term_id'] ?? 0);
            $acadYear = $data['academic_year'] ?? date('Y');
            if (!$termId || empty($ratings)) return $this->badRequest('term_id and ratings are required');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            // Map level_code to performance_level_id
            $lvlStmt = $this->db->query("SELECT id, code FROM performance_levels_cbc");
            $lvlMap  = [];
            foreach ($lvlStmt->fetchAll(\PDO::FETCH_ASSOC) as $lv) $lvlMap[$lv['code']] = $lv['id'];

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO learner_competencies
                    (student_id, competency_id, academic_year, term_id, performance_level_id, evidence, teacher_notes, assessed_by, assessed_date)
                 VALUES (:sid, :cid, :yr, :tid, :lvl, :ev, :notes, :aby, CURDATE())
                 ON DUPLICATE KEY UPDATE performance_level_id=:lvl, evidence=:ev, teacher_notes=:notes, assessed_by=:aby, updated_at=NOW()"
            );
            foreach ($ratings as $r) {
                $levelId = $lvlMap[$r['level_code'] ?? ''] ?? null;
                $ins->execute([
                    ':sid'   => (int)$r['student_id'],
                    ':cid'   => (int)$r['competency_id'],
                    ':yr'    => $acadYear,
                    ':tid'   => $termId,
                    ':lvl'   => $levelId,
                    ':ev'    => $r['evidence']     ?? null,
                    ':notes' => $r['notes']        ?? null,
                    ':aby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($ratings)], 'Competency ratings saved');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: NATIONAL EXAMS ====================

    /**
     * GET  /api/academic/national-exams?exam_type=KPSEA_G6&exam_year=2024
     * POST /api/academic/national-exams → enter results
     */
    public function getNationalExams($id = null, $data = [], $segments = [])
    {
        try {
            $where  = ['1=1'];
            $params = [];
            foreach (['exam_type','exam_year'] as $f) {
                if (!empty($_GET[$f])) { $where[] = "ne.$f=:$f"; $params[":$f"] = $_GET[$f]; }
            }
            if (!empty($_GET['student_id'])) { $where[] = 'ne.student_id=:sid'; $params[':sid'] = (int)$_GET['student_id']; }
            if (!empty($_GET['class_id'])) {
                $where[] = "ne.student_id IN (SELECT sae.student_id FROM student_academic_enrollments sae
                              WHERE sae.academic_year_class_stream_id = :cid AND sae.enrollment_status = 'active')";
                $params[':cid'] = (int)$_GET['class_id'];
            }

            $stmt = $this->db->query(
                "SELECT ne.*,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no,
                        la.name AS learning_area_name
                 FROM national_exam_results ne
                 JOIN students s ON s.id = ne.student_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN learning_areas la ON la.id = ne.learning_area_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, ne.learning_area_id",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    public function postNationalExams($id = null, $data = [], $segments = [])
    {
        try {
            $results  = $data['results'] ?? []; // [{student_id, learning_area_id, score, max_score, raw_grade, points, pathway}]
            $examType = $data['exam_type'] ?? '';
            $examYear = (int)($data['exam_year'] ?? date('Y'));
            if (!$examType || empty($results)) return $this->badRequest('exam_type and results are required');

            $validTypes = ['KNEC_G3','KPSEA_G6','KJSEA_G9'];
            if (!in_array($examType, $validTypes)) return $this->badRequest('Invalid exam_type');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO national_exam_results
                    (student_id, exam_type, exam_year, learning_area_id, score, max_score, percentage,
                     cbc_grade, raw_grade, points, pathway, remarks, entered_by, academic_year_id)
                 VALUES (:sid, :et, :ey, :la, :sc, :mx, :pct, :cg, :rg, :pt, :pw, :rmk, :eby, :ayid)
                 ON DUPLICATE KEY UPDATE
                    score=:sc, max_score=:mx, percentage=:pct, cbc_grade=:cg,
                    raw_grade=:rg, points=:pt, pathway=:pw, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($results as $r) {
                $score   = (float)($r['score']     ?? 0);
                $max     = (float)($r['max_score'] ?? 100);
                $pct     = $max > 0 ? round(($score / $max) * 100, 2) : 0;
                $grade   = $pct >= 75 ? 'EE' : ($pct >= 60 ? 'ME' : ($pct >= 40 ? 'AE' : 'BE'));
                $ins->execute([
                    ':sid'  => (int)$r['student_id'],
                    ':et'   => $examType,
                    ':ey'   => $examYear,
                    ':la'   => (int)$r['learning_area_id'],
                    ':sc'   => $score,
                    ':mx'   => $max,
                    ':pct'  => $pct,
                    ':cg'   => $grade,
                    ':rg'   => $r['raw_grade']  ?? null,
                    ':pt'   => !empty($r['points']) ? (float)$r['points'] : null,
                    ':pw'   => $r['pathway']    ?? null,
                    ':rmk'  => $r['remarks']    ?? null,
                    ':eby'  => $userId,
                    ':ayid' => !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($results)], 'National exam results saved');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: STRANDS ====================

    /**
     * GET /api/academic/strands?learning_area_id=X
     */
    public function getStrands($id = null, $data = [], $segments = [])
    {
        try {
            $laId  = (int)($_GET['learning_area_id'] ?? 0);
            $where = $laId ? 'WHERE learning_area_id=:la' : '';
            $stmt  = $this->db->query(
                "SELECT s.id, s.code, s.name, s.level_range, s.sort_order,
                        la.id AS learning_area_id, la.name AS learning_area_name
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 $where
                 ORDER BY s.sort_order, s.id",
                $laId ? [':la' => $laId] : []
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/strands */
    public function postStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        try {
            if (empty($data['learning_area_id']) || empty($data['name'])) {
                return $this->badRequest('learning_area_id and name are required');
            }
            $code = $data['code'] ?? '';
            if (!$code) {
                $prefix = $this->db->query(
                    "SELECT code FROM learning_areas WHERE id=:id", [':id' => (int)$data['learning_area_id']]
                )->fetchColumn();
                $cnt = $this->db->query(
                    "SELECT COUNT(*) FROM strands WHERE learning_area_id=:laid",
                    [':laid' => (int)$data['learning_area_id']]
                )->fetchColumn();
                $code = ($prefix ?: 'LA') . '-S' . (($cnt ?: 0) + 1);
            }
            $this->db->query(
                "INSERT INTO strands (learning_area_id, code, name, description, level_range, sort_order, status)
                 VALUES (:laid, :code, :name, :desc, :lr, :sort, :status)",
                [
                    ':laid' => (int)$data['learning_area_id'],
                    ':code' => $code,
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':lr' => $data['level_range'] ?? null,
                    ':sort' => (int)($data['sort_order'] ?? 1),
                    ':status' => $data['status'] ?? 'active',
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Strand created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/strands/{id} */
    public function putStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Strand ID is required');
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['learning_area_id', 'code', 'name', 'description', 'level_range', 'sort_order', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['learning_area_id','sort_order']) ? (int)$data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE strands SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Strand updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/strands/{id} */
    public function deleteStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Strand ID is required');
        try {
            $this->db->query("DELETE FROM strands WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Strand deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: CLASS STUDENTS ====================

    /**
     * GET /api/academic/class-students?class_id=X
     * Returns active enrolled students for a class.
     */
    public function getClassStudents($id = null, $data = [], $segments = [])
    {
        try {
            // Accept class_id from the resource id, query string, or request body
            // so the endpoint works whether callers pass it as ?class_id= or in the JSON body.
            $classId = (int)($id ?: ($data['class_id'] ?? ($_GET['class_id'] ?? 0)));
            if (!$classId) return $this->badRequest('class_id is required');

            $stmt = $this->db->query(
                "SELECT DISTINCT s.id, p.first_name, p.last_name, s.admission_no,
                        sae.academic_year_class_stream_id AS stream_id
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                    AND sae.academic_year_class_stream_id = :cid
                    AND sae.enrollment_status IN ('active','completed')
                 WHERE s.status = 'active'
                 ORDER BY p.last_name, p.first_name",
                [':cid' => $classId]
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: COMPUTE TERM SCORES ====================

    /**
     * POST /api/academic/compute-term-scores
     * Computes formative/summative aggregates from formative_scores → term_subject_scores.
     * Body: { class_id, term_id, subject_id? }  OR  { assessment_id }
     */
    public function postComputeTermScores($id = null, $data = [], $segments = [])
    {
        try {
            $classId   = (int)($data['class_id']   ?? 0);
            $termId    = (int)($data['term_id']     ?? 0);
            $subjectId = (int)($data['subject_id']  ?? 0);
            $asmtId    = (int)($data['assessment_id'] ?? 0);

            // If only assessment_id given, derive class/term/subject from it
            if ($asmtId && (!$classId || !$termId)) {
                $r = $this->db->query(
                    "SELECT academic_year_class_stream_id AS class_id, academic_year_term_id AS term_id,
                            learning_area_id AS subject_id FROM assessments WHERE id=:id LIMIT 1",
                    [':id' => $asmtId]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$r) return $this->notFound('Assessment not found');
                $classId   = $classId   ?: (int)$r['class_id'];
                $termId    = $termId    ?: (int)$r['term_id'];
                $subjectId = $subjectId ?: (int)$r['subject_id'];
            }
            if (!$classId || !$termId) return $this->badRequest('class_id and term_id are required');

            // Build list of (student_id, subject_id) pairs to compute
            $where  = ['a.academic_year_class_stream_id=:cid', 'a.academic_year_term_id=:tid'];
            $params = [':cid' => $classId, ':tid' => $termId];
            if ($subjectId) { $where[] = 'a.learning_area_id=:sid'; $params[':sid'] = $subjectId; }

            $scoreSourceSql = "
                SELECT fs.assessment_id, fs.student_id, fs.score, fs.max_score, fs.id AS score_id
                FROM formative_scores fs
                UNION ALL
                SELECT ar.assessment_id, sae.student_id, ar.marks_obtained AS score, a2.max_marks AS max_score, ar.id AS score_id
                FROM assessment_results ar
                JOIN assessments a2 ON a2.id = ar.assessment_id
                JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM formative_scores fs2
                    WHERE fs2.assessment_id = ar.assessment_id
                      AND fs2.student_id = sae.student_id
                )
            ";

            $rows = $this->db->query(
                "SELECT DISTINCT scored.student_id, a.learning_area_id AS subject_id
                 FROM ({$scoreSourceSql}) scored
                 JOIN assessments a ON a.id = scored.assessment_id
                 LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                 WHERE " . implode(' AND ', $where),
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) return $this->success(['computed' => 0], 'No scored assessments found for these filters');

            // Aggregate per student per subject
            $combos = [];
            foreach ($rows as $r) {
                $key = $r['student_id'] . '_' . $r['subject_id'];
                $combos[$key] = ['student_id' => (int)$r['student_id'], 'subject_id' => (int)$r['subject_id']];
            }

            $upsert = $this->db->getConnection()->prepare(
                "INSERT INTO term_subject_scores
                    (student_id, term_id, subject_id,
                     formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                     summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                     overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at)
                 VALUES
                    (:sid, :tid, :subid,
                     :ft, :fm, :fp, :fg, :fc,
                     :st, :sm, :sp, :sg, :sc,
                     :ov, :op, :og, :opts, :ac, NOW())
	                 ON DUPLICATE KEY UPDATE
	                     formative_total=VALUES(formative_total),
	                     formative_max=VALUES(formative_max),
	                     formative_percentage=VALUES(formative_percentage),
	                     formative_grade=VALUES(formative_grade),
	                     formative_count=VALUES(formative_count),
	                     summative_total=VALUES(summative_total),
	                     summative_max=VALUES(summative_max),
	                     summative_percentage=VALUES(summative_percentage),
	                     summative_grade=VALUES(summative_grade),
	                     summative_count=VALUES(summative_count),
	                     overall_score=VALUES(overall_score),
	                     overall_percentage=VALUES(overall_percentage),
	                     overall_grade=VALUES(overall_grade),
	                     overall_points=VALUES(overall_points),
	                     assessment_count=VALUES(assessment_count),
	                     calculated_at=NOW()"
            );

            $computed = 0;
            foreach ($combos as $combo) {
                $stu  = $combo['student_id'];
                $subj = $combo['subject_id'];

                $agg = $this->db->query(
                    "SELECT
                        SUM(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN scored.score ELSE 0 END)     AS ft,
                        SUM(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN scored.max_score ELSE 0 END) AS fm,
                        COUNT(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN 1 END)                     AS fc,
                        SUM(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN scored.score ELSE 0 END)     AS st,
                        SUM(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN scored.max_score ELSE 0 END) AS sm,
                        COUNT(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN 1 END)                     AS sc,
                        COUNT(scored.score_id) AS ac
                     FROM ({$scoreSourceSql}) scored
                     JOIN assessments a ON a.id = scored.assessment_id
                        AND a.academic_year_term_id=:tid AND a.learning_area_id=:subid
                     LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                     WHERE scored.student_id=:stu",
                    [':tid' => $termId, ':subid' => $subj, ':stu' => $stu]
                )->fetch(\PDO::FETCH_ASSOC);

                $ft = (float)($agg['ft'] ?? 0);
                $fm = (float)($agg['fm'] ?? 0);
                $fc = (int)  ($agg['fc'] ?? 0);
                $fp = $fm > 0 ? round(($ft / $fm) * 100, 2) : 0;
                $fg = $fp >= 75 ? 'EE' : ($fp >= 60 ? 'ME' : ($fp >= 40 ? 'AE' : 'BE'));

                $st = (float)($agg['st'] ?? 0);
                $sm = (float)($agg['sm'] ?? 0);
                $sc = (int)  ($agg['sc'] ?? 0);
                $sp = $sm > 0 ? round(($st / $sm) * 100, 2) : 0;
                $sg = $sp >= 75 ? 'EE' : ($sp >= 60 ? 'ME' : ($sp >= 40 ? 'AE' : 'BE'));

                // CBC: 40% formative + 60% summative
                $op = round(($fp * 0.4) + ($sp * 0.6), 2);
                $og = $op >= 75 ? 'EE' : ($op >= 60 ? 'ME' : ($op >= 40 ? 'AE' : 'BE'));
                $opts = $og === 'EE' ? 4.0 : ($og === 'ME' ? 3.0 : ($og === 'AE' ? 2.0 : 1.0));
                $ov = round(($ft + $st), 2);

                $upsert->execute([
                    ':sid'   => $stu,  ':tid' => $termId, ':subid' => $subj,
                    ':ft'    => $ft,   ':fm'  => $fm,  ':fp' => $fp,  ':fg' => $fg,  ':fc' => $fc,
                    ':st'    => $st,   ':sm'  => $sm,  ':sp' => $sp,  ':sg' => $sg,  ':sc' => $sc,
                    ':ov'    => $ov,   ':op'  => $op,  ':og' => $og,  ':opts' => $opts,
                    ':ac'    => (int)($agg['ac'] ?? 0),
                ]);
                $computed++;
            }
            return $this->success(['computed' => $computed], "$computed student-subject scores recomputed");
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: REPORT CARD DATA ====================

    /**
     * GET /api/academic/report-card-data/{student_id}?term_id=
     * Consolidated CBC report card: term_subject_scores + competency ratings + attendance + values.
     */
    public function getReportCardData($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = $id ?? (int)($_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');
            $termId    = (int)($_GET['term_id'] ?? 0);

            // Student info (current enrollment → class/stream)
            $student = $this->db->query(
                "SELECT v.student_id AS id, v.admission_no,
                        p.first_name, p.last_name,
                        v.class_name, v.stream_name
                 FROM vw_current_enrollments v
                 JOIN students s ON s.id = v.student_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE v.student_id=:id AND v.enrollment_status='active'
                 LIMIT 1",
                [':id' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$student) return $this->notFound('Student not found');

            // Term info
            $termWhere  = $termId ? 'WHERE ayt.id=:tid LIMIT 1' : "WHERE ayt.status='current' LIMIT 1";
            $termParams = $termId ? [':tid' => $termId] : [];
            $term = $this->db->query(
                "SELECT ayt.id, t.name, t.code AS term_code, ay.year_code
                   FROM academic_year_terms ayt
                   JOIN terms t ON t.id = ayt.term_id
                   JOIN academic_years ay ON ay.id = ayt.academic_year_id
                   $termWhere",
                $termParams
            )->fetch(\PDO::FETCH_ASSOC);
            $resolvedTermId = $term ? (int)$term['id'] : $termId;

            // Subject scores
            $scores = $this->db->query(
                "SELECT tss.*,
                        la.name AS subject_name, la.code AS subject_code
                 FROM term_subject_scores tss
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id=:sid AND tss.term_id=:tid
                 ORDER BY la.name",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Core competency ratings
            $competencies = $this->db->query(
                "SELECT lc.competency_id, lc.performance_level_id, lc.evidence, lc.teacher_notes,
                        cc.code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 WHERE lc.student_id=:sid AND lc.term_id=:tid",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Core values
            $values = $this->db->query(
                "SELECT sv.value_id, sv.evidence,
                        cv.name AS value_name
                 FROM learner_values_acquisition sv
                 JOIN core_values cv ON cv.id = sv.value_id
                 WHERE sv.student_id=:sid AND sv.term_id=:tid",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance summary
            $attendance = $this->db->query(
                "SELECT
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS days_present,
                    SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) AS days_absent,
                    SUM(CASE WHEN status='late'    THEN 1 ELSE 0 END) AS days_late
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sae.student_id=:sid
                   AND sae.academic_year_id = (SELECT academic_year_id FROM academic_year_terms WHERE id=:tid)",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: STUDENT GROWTH ====================

    /**
     * GET /api/academic/student-assessment-history?student_id=X&term_id=&subject_id=
     * Returns all graded assessments for a student with their scores.
     */
    public function getStudentAssessmentHistory($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $where  = ['fs.student_id=:sid'];
            $params = [':sid' => $studentId];
            if (!empty($_GET['term_id']))    { $where[] = 'a.academic_year_term_id=:tid'; $params[':tid']  = (int)$_GET['term_id']; }
            if (!empty($_GET['subject_id'])) { $where[] = 'a.learning_area_id=:sub';     $params[':sub']  = (int)$_GET['subject_id']; }

            $stmt = $this->db->query(
                "SELECT a.id AS assessment_id, a.title, a.assessment_date, a.max_marks,
                        fs.score, fs.percentage, fs.cbc_grade,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        t.name AS term_name, ayt.id AS term_id, ay.year_code
                 FROM formative_scores fs
                 JOIN assessments a       ON a.id  = fs.assessment_id
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t  ON t.id  = ayt.term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date ASC, a.id ASC",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/student-growth-trend?student_id=X&learning_area_id=Y
     * Returns per-term average scores for a student in a learning area (for charting).
     */
    public function getStudentGrowthTrend($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($_GET['student_id']       ?? 0);
            $laId      = (int)($_GET['learning_area_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $where  = ['tss.student_id=:sid'];
            $params = [':sid' => $studentId];
            if ($laId) { $where[] = 'tss.subject_id=:la'; $params[':la'] = $laId; }

            $stmt = $this->db->query(
                "SELECT t.id AS term_id, t.name AS term_name, t.code AS term_number, ay.year_code AS year,
                        la.id AS subject_id, la.name AS subject_name,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade, tss.overall_points
                 FROM term_subject_scores tss
                 JOIN student_academic_enrollments sae ON sae.student_id = tss.student_id
                 JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                      AND ayt.academic_year_id = sae.academic_year_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY ay.year_code ASC, t.id ASC, la.name ASC",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== HELPERS ====================

    // ==================== STUDENT TIMELINE ====================

    /**
     * GET /api/academic/student-timeline/{student_id}
     * Full academic, finance, discipline, attendance history across all years.
     */
    public function getStudentTimeline($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($segments[0] ?? null);
        if (!$studentId) {
            error_log('[AcademicController] getStudentTimeline: student id required');
            return $this->error('Student id is required.');
        }

        try {
            $db = $this->db;

            // Core student record
            $student = $db->query(
                "SELECT s.id, s.admission_no,
                        p.first_name, p.middle_name, p.last_name,
                        p.dob AS date_of_birth, p.gender, s.admission_date, s.status,
                        (SELECT COUNT(*) FROM student_fee_obligations sfo
                          WHERE sfo.student_academic_enrollment_id = sae.id AND sfo.is_sponsored = 1) > 0 AS is_sponsored,
                        NULL AS sponsor_name,
                        'obligation' AS sponsor_type,
                        (SELECT COALESCE(MAX(sfo2.sponsored_waiver_amount), 0) FROM student_fee_obligations sfo2
                          WHERE sfo2.student_academic_enrollment_id = sae.id AND sfo2.is_sponsored = 1) AS sponsor_waiver_percentage,
                        p.photo_url, s.nemis_number,
                        c.name AS current_class, sn.name AS current_stream,
                        st.name AS student_type
                 FROM students s
                 LEFT JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
                      AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE s.id = ?",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$student) return $this->error('Student not found', 404);

            // Academic history per year
            $academics = $db->query(
                "SELECT ay.id AS academic_year_id, ay.year_code, ay.year_name,
                        c.name AS class_name, sn.name AS stream_name,
                        yav.avg_pct AS year_average,
                        NULL AS term1_average, NULL AS term2_average, NULL AS term3_average,
                        NULL AS overall_grade, NULL AS class_rank,
                        NULL AS attendance_percentage, NULL AS days_present, NULL AS days_absent,
                        tr.transition_type AS promotion_status,
                        pc.name AS promoted_to_class,
                        NULL AS teacher_comments, NULL AS head_teacher_comments,
                        sae.enrolled_on
                 FROM student_academic_enrollments sae
                 JOIN academic_years ay ON ay.id = sae.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN student_transitions tr ON tr.student_id = sae.student_id
                      AND tr.from_student_academic_enrollment_id = sae.id
                 LEFT JOIN student_academic_enrollments to_sae ON to_sae.id = tr.to_student_academic_enrollment_id
                 LEFT JOIN academic_year_class_streams to_aycs ON to_aycs.id = to_sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes to_ayc ON to_ayc.id = to_aycs.academic_year_class_id
                 LEFT JOIN classes pc ON pc.id = to_ayc.class_id
                 LEFT JOIN (
                     SELECT tss.student_id, ayt.academic_year_id, ROUND(AVG(tss.overall_percentage), 2) AS avg_pct
                     FROM term_subject_scores tss
                     JOIN student_academic_enrollments sae2 ON sae2.student_id = tss.student_id
                     JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                          AND ayt.academic_year_id = sae2.academic_year_id
                     GROUP BY tss.student_id, ayt.academic_year_id
                 ) yav ON yav.student_id = sae.student_id AND yav.academic_year_id = sae.academic_year_id
                 WHERE sae.student_id = ?
                 ORDER BY ay.start_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Term-level subject scores per year
            $subjectScores = $db->query(
                "SELECT ayt.academic_year_id, ay.year_code, t.code AS term_number, t.name AS term_name,
                        la.name AS subject_name, la.code AS subject_code,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade
                 FROM term_subject_scores tss
                 JOIN student_academic_enrollments sae ON sae.student_id = tss.student_id
                 JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                      AND ayt.academic_year_id = sae.academic_year_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id = ?
                 ORDER BY ay.start_date ASC, t.id ASC, la.name ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Payment / finance history
            $payments = $db->query(
                "SELECT p.payment_date, p.amount AS amount_paid, p.method AS payment_method,
                        p.receipt_no, p.reference AS reference_no, p.status
                 FROM payments p
                 WHERE p.student_id = ?
                 ORDER BY p.payment_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Outstanding fee balances per term (derived via the fee-balance view)
            $feeBalanceSummary = $db->query(
                "SELECT COALESCE(SUM(fb.amount_due), 0) AS amount_due,
                        COALESCE(SUM(fb.amount_paid), 0) AS amount_paid,
                        COALESCE(SUM(fb.balance), 0) AS balance
                 FROM vw_student_fee_balances fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.student_id = ?",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);

            // Per-fee fee obligations breakdown
            $feeObligations = $db->query(
                "SELECT ay.year_code AS academic_year, t.code AS term_number, t.name AS term_name,
                        fc.name AS fee_name,
                        o.amount_due, o.status AS payment_status
                 FROM student_fee_obligations o
                 JOIN student_academic_enrollments sae ON sae.id = o.student_academic_enrollment_id
                 JOIN academic_years ay ON ay.id = o.academic_year_id
                 JOIN academic_year_terms ayt ON ayt.id = o.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_year_fee_schedules fsd ON fsd.id = o.academic_year_fee_schedule_id
                 JOIN fee_catalog fc ON fc.id = fsd.fee_catalog_id
                 WHERE sae.student_id = ?
                 ORDER BY ay.year_code ASC, t.code ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Discipline history
            $discipline = $db->query(
                "SELECT di.incident_date, di.type AS incident_type, di.severity,
                        di.description, di.action_taken, di.status,
                        ay.year_code AS academic_year,
                        t.code AS term_number
                 FROM discipline_incidents di
                 JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = di.academic_year_term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE sae.student_id = ?
                 ORDER BY di.incident_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance summary per year
            $attendance = $db->query(
                "SELECT ay.year_code AS academic_year,
                        COUNT(CASE WHEN sa.status = 'present' THEN 1 END) AS days_present,
                        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) AS days_absent,
                        COUNT(CASE WHEN sa.status = 'late' THEN 1 END) AS days_late,
                        COUNT(sa.id) AS total_recorded
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 JOIN academic_years ay ON ay.id = sae.academic_year_id
                 WHERE sae.student_id = ?
                 GROUP BY ay.id
                 ORDER BY ay.start_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Fee credit notes
            $creditNotes = $db->query(
                "SELECT credit_number, academic_year, credit_amount, credit_reason,
                        status, applied_amount, remaining_amount, created_at
                 FROM fee_credit_notes
                 WHERE student_id = ? ORDER BY academic_year ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Transfer history
            $transfers = $db->query(
                "SELECT st.id AS request_number, st.decided_at AS request_date,
                        st.transition_type AS transfer_type, st.reason,
                        sc.status, sc.amount_outstanding AS fee_balance_at_request,
                        st.executed_at AS completed_at
                 FROM student_transitions st
                 LEFT JOIN student_clearances sc ON sc.student_id = st.student_id
                      AND sc.clearance_type = 'finance'
                 WHERE st.student_id = ?
                 ORDER BY st.executed_at ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Summary stats
            $totalPaid = $feeBalanceSummary['amount_paid'];
            $totalOwed = $feeBalanceSummary['amount_due'];
            $totalOutstanding = $feeBalanceSummary['balance'];

            return $this->success([
                'student'        => $student,
                'academics'      => $academics,
                'subject_scores' => $subjectScores,
                'payments'       => $payments,
                'fee_obligations' => $feeObligations,
                'discipline'     => $discipline,
                'attendance'     => $attendance,
                'credit_notes'   => $creditNotes,
                'transfers'      => $transfers,
                'summary' => [
                    'years_enrolled'   => count($academics),
                    'total_fees_billed' => $totalOwed,
                    'total_fees_paid'  => $totalPaid,
                    'current_balance'  => $totalOutstanding,
                    'discipline_cases' => count($discipline),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/staff-timeline/{staff_id}
     */
    public function getStaffTimeline($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($segments[0] ?? null);
        if (!$staffId && isset($data['staff_id'])) $staffId = $data['staff_id'];
        if (!$staffId) {
            error_log('[AcademicController] getStaffTimeline: staff id required');
            return $this->error('Staff id is required.');
        }

        try {
            $db = $this->db;

            $staff = $db->query(
                "SELECT s.id, s.staff_no,
                        p.first_name, p.last_name,
                        p.email AS email,
                        p.phone, p.gender, p.dob AS date_of_birth, s.employment_date, s.status AS employment_status,
                        s.salary AS basic_salary, p.photo_url,
                        d.name AS department_name, sc.category_name AS staff_category,
                        s.position AS position_title
                 FROM staff s
                 LEFT JOIN persons p ON p.id = s.person_id
                 LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                 LEFT JOIN departments d ON d.id = sda.department_id
                 LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                 WHERE s.id = ?",
                [$staffId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$staff) return $this->error('Staff not found', 404);

            $assignments = $db->query(
                "SELECT ay.year_code AS academic_year, c.name AS class_name,
                        NULL AS stream_name, aclat.role, la.name AS subject_name,
                        NULL AS status, NULL AS start_date, NULL AS end_date
                 FROM academic_year_class_learning_area_teachers aclat
                 JOIN academic_year_class_learning_areas aycl ON aycl.id = aclat.academic_year_class_learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN learning_areas la ON la.id = aycl.learning_area_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = aclat.academic_year_term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE aclat.staff_id = ?
                 ORDER BY ay.start_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $promotions = $db->query(
                "SELECT sa.position AS to_position, sa.position AS from_position,
                        sa.salary AS to_salary, sa.salary AS from_salary,
                        sa.employment_date AS effective_date, sa.status,
                        d.name AS to_department, NULL AS from_department
                 FROM staff_appointments sa
                 LEFT JOIN departments d ON d.id = sa.department_id
                 WHERE sa.created_staff_id = ?
                 ORDER BY sa.employment_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $payrollHistory = $db->query(
                "SELECT CONCAT(payroll_year, '-', LPAD(payroll_month, 2, '0')) AS payroll_month,
                        basic_salary,
                        allowances_total AS allowances,
                        COALESCE(paye_tax,0)+COALESCE(nssf_contribution,0)+COALESCE(nhif_contribution,0)+
                        COALESCE(loan_deduction,0)+COALESCE(child_fees_deduction,0)+COALESCE(sacco_deduction,0)+
                        COALESCE(housing_levy,0)+COALESCE(salary_advance_deduction,0)+COALESCE(other_deductions_total,0) AS total_deductions,
                        paye_tax, nssf_contribution AS nssf_deduction, nhif_contribution AS nhif_deduction,
                        net_salary, payslip_status AS status, payment_date
                 FROM payslips
                 WHERE staff_id = ?
                 ORDER BY payroll_year ASC, payroll_month ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $advances = $db->query(
                "SELECT advance_number, requested_amount, approved_amount,
                        request_date, deduction_schedule, amount_deducted, balance_remaining, status
                 FROM staff_salary_advances
                 WHERE staff_id = ? ORDER BY request_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $leaves = $db->query(
                "SELECT leave_type, start_date, end_date, days_requested, reason, status
                 FROM staff_leaves WHERE staff_id = ? ORDER BY start_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $performance = $db->query(
                "SELECT period AS review_period, rating AS overall_rating,
                        notes AS strengths, notes AS areas_for_improvement,
                        NULL AS performance_grade, NULL AS recommendations, NULL AS action_plan,
                        status, review_date
                 FROM performance_reviews
                 WHERE staff_id = ?
                 ORDER BY review_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'staff'       => $staff,
                'assignments' => $assignments,
                'promotions'  => $promotions,
                'payroll'     => $payrollHistory,
                'advances'    => $advances,
                'leaves'      => $leaves,
                'performance' => $performance,
                'summary' => [
                    'years_of_service'   => count(array_unique(array_column($assignments, 'academic_year'))),
                    'total_promotions'   => count($promotions),
                    'leave_days_taken'   => array_sum(array_column($leaves, 'days_requested')),
                    'active_advance'     => count(array_filter($advances, fn($a) => $a['status'] === 'active')),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== TRANSFER REQUESTS ====================

    /**
     * GET /api/academic/transfer-requests
     * POST /api/academic/transfer-requests
     */
    public function getTransferRequests($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $row = $this->db->query(
                    "SELECT tr.id, tr.id AS request_number, tr.decided_at AS request_date,
                            tr.transition_type AS transfer_type, NULL AS destination_school,
                            CONCAT(p.first_name,' ',p.last_name) AS student_name,
                            s.admission_no, c.name AS class_name,
                            sc.checked_by AS requested_by, sc.checked_by AS approved_by,
                            tr.executed_at AS approval_date
                     FROM student_transitions tr
                     JOIN students s ON s.id = tr.student_id
                     JOIN persons p ON p.id = s.person_id
                     LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                          AND sae.academic_year_id = tr.academic_year_id
                     LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                     LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     LEFT JOIN classes c ON c.id = ayc.class_id
                     LEFT JOIN student_clearances sc ON sc.transfer_request_id = tr.id
                          AND sc.clearance_type = 'finance'
                     WHERE tr.id = ?",
                    [$id]
                )->fetch(\PDO::FETCH_ASSOC);

                // also get clearances
                $clearances = $this->db->query(
                    "SELECT sc.*, p.first_name AS checked_by_name
                     FROM student_clearances sc
                     LEFT JOIN users u ON u.id = sc.checked_by
                     LEFT JOIN persons p ON p.id = u.person_id
                     WHERE sc.transfer_request_id = ?",
                    [$id]
                )->fetchAll(\PDO::FETCH_ASSOC);

                return $this->success(['request' => $row, 'clearances' => $clearances]);
            }

            $rows = $this->db->query(
                "SELECT tr.id, tr.id AS request_number, tr.decided_at AS request_date,
                        tr.transition_type AS transfer_type, NULL AS destination_school,
                        CASE WHEN SUM(sc.status = 'blocked') > 0 THEN 'blocked'
                             WHEN COUNT(sc.id) > 0 AND SUM(sc.status = 'cleared') = COUNT(sc.id) THEN 'fully_cleared'
                             ELSE 'pending' END AS clearance_status,
                        CASE WHEN tr.executed_at IS NOT NULL THEN 'approved' ELSE 'pending' END AS status,
                        NULL AS fee_balance_at_request,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no, c.name AS class_name
                 FROM student_transitions tr
                 JOIN students s ON s.id = tr.student_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_id = tr.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN student_clearances sc ON sc.transfer_request_id = tr.id
                 WHERE tr.transition_type = 'transfer'
                 GROUP BY tr.id
                 ORDER BY tr.id DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($rows);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    public function postTransferRequests($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? null;
        if (!$studentId) {
            error_log('[AcademicController] postTransferRequests: student_id required');
            return $this->error('student_id is required.');
        }

        try {
            $db = $this->db;

            // GUARD: Check for outstanding fees before allowing transfer
            $feeCheck = $db->query(
                "SELECT COALESCE(SUM(fb.balance),0) AS outstanding
                 FROM vw_student_fee_balances fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.student_id = ?
                   AND sae.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);

            $outstanding = (float)($feeCheck['outstanding'] ?? 0);

            // Log the business rule check
            if ($outstanding > 0) {
                $db->query(
                    "INSERT INTO audit_logs
                     (action, entity, entity_id, user_id, ip_address, user_agent, details, status)
                     VALUES ('TRANS_FEE_BLOCK', 'student', ?, ?, ?, ?, ?, 'blocked')",
                    [
                        $studentId,
                        $this->user['id'] ?? null,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'Student has outstanding fees — transfer blocked | ' .
                            json_encode(['outstanding' => $outstanding, 'student_id' => $studentId]),
                    ]
                );

                return $this->error(
                    "Cannot initiate transfer: student has outstanding fees of KES " .
                    number_format($outstanding, 2) .
                    ". Fees must be paid or waived before transfer can proceed.",
                    422
                );
            }

            $db->query(
                "INSERT INTO student_transitions
                 (student_id, academic_year_id, transition_type, reason, decided_by, decided_at)
                 SELECT ?, ay.id, ?, ?, ?, NOW()
                 FROM academic_years ay WHERE ay.is_current = 1 LIMIT 1",
                [
                    $studentId,
                    $data['transfer_type'] ?? 'transfer',
                    $data['reason'] ?? null,
                    $this->user['id'] ?? null,
                ]
            );
            $requestId = $db->lastInsertId();

            // Auto-create clearance items
            foreach (['finance', 'library', 'uniform', 'property', 'academic'] as $type) {
                $db->query(
                    "INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status)
                     VALUES (?, ?, ?, 'pending')",
                    [$studentId, $requestId, $type]
                );
            }

            return $this->success(['request_id' => $requestId, 'request_number' => $requestId], 201);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * PUT /api/academic/transfer-requests/{id}
     * Update clearance status or approve/reject transfer
     */
    public function putTransferRequests($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            error_log('[AcademicController] putTransferRequests: transfer request id required');
            return $this->error('Transfer request id is required.');
        }
        $action = $data['action'] ?? null;

        try {
            $db = $this->db;

            if ($action === 'update_clearance') {
                $db->query(
                    "UPDATE student_clearances SET status = ?, checked_by = ?, checked_at = NOW(),
                            amount_outstanding = ?, notes = ?
                     WHERE transfer_request_id = ? AND clearance_type = ?",
                    [
                        $data['status'],
                        $this->user['id'] ?? null,
                        $data['amount_outstanding'] ?? 0,
                        $data['notes'] ?? null,
                        $id,
                        $data['clearance_type'],
                    ]
                );

                // Check if all clearances are done
                $pending = $db->query(
                    "SELECT COUNT(*) FROM student_clearances
                     WHERE transfer_request_id = ? AND status != 'cleared'",
                    [$id]
                )->fetchColumn();

                $blocked = $db->query(
                    "SELECT COUNT(*) FROM student_clearances
                     WHERE transfer_request_id = ? AND status = 'blocked'",
                    [$id]
                )->fetchColumn();

                if ($blocked > 0 || $pending == 0) {
                    // New schema has no per-transition clearance_status column;
                    // clearance progress is fully derived from student_clearances rows.
                }

                return $this->success(['updated' => true]);
            }

            if ($action === 'approve') {
                $db->query(
                    "UPDATE student_transitions SET decided_by = ?, decided_at = NOW(), executed_at = NOW() WHERE id = ?",
                    [$this->user['id'] ?? null, $id]
                );
                // Update student status
                $req = $db->query("SELECT student_id FROM student_transitions WHERE id = ?", [$id])->fetch();
                if ($req) {
                    $db->query("UPDATE students SET status = 'transferred' WHERE id = ?", [$req['student_id']]);
                }
                return $this->success(['approved' => true]);
            }

            if ($action === 'reject') {
                $db->query(
                    "UPDATE student_transitions SET reason = CONCAT(COALESCE(reason, ''), ' | REJECTED: ', ?), decided_by = ? WHERE id = ?",
                    [$data['reason'] ?? null, $this->user['id'] ?? null, $id]
                );
                return $this->success(['rejected' => true]);
            }

            error_log('[AcademicController] putTransferRequests: invalid action ' . ($action ?? 'null'));
            return $this->error('Invalid action. Expected approve or reject.');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== YEAR-END ROLLOVER ====================

    /**
     * GET /api/academic/year-rollover-status
     * Returns the current state of the rollover checklist.
     */
    // TODO: Delegate to AcademicYearService
    public function getYearRolloverStatus($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            $currentYear = $db->query(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$currentYear) {
                error_log('[AcademicController] getYearRolloverStatus: no current academic year set');
                return $this->error('No current academic year is set.');
            }

            // Check each prerequisite
            $termsStatus = $db->query(
                "SELECT t.code AS term_number, t.name, ayt.status FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE ayt.academic_year_id = ? ORDER BY t.id",
                [$currentYear['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $pendingResults = $db->query(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 WHERE sae.academic_year_id = ? AND NOT EXISTS (
                     SELECT 1 FROM term_subject_scores tss
                     JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                          AND ayt.academic_year_id = sae.academic_year_id
                     WHERE tss.student_id = sae.student_id
                 )",
                [$currentYear['id']]
            )->fetchColumn();

            $pendingPromotions = $db->query(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 WHERE sae.academic_year_id = ? AND NOT EXISTS (
                     SELECT 1 FROM student_transitions tr
                     WHERE tr.student_id = sae.student_id
                       AND tr.from_student_academic_enrollment_id = sae.id
                 )",
                [$currentYear['id']]
            )->fetchColumn();

            $outstandingFees = $db->query(
                "SELECT COUNT(DISTINCT fb.student_id) FROM vw_student_fee_balances fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.academic_year_id = ? AND fb.balance > 0",
                [$currentYear['id']]
            )->fetchColumn();

            $rolloverLog = $db->query(
                "SELECT step, status, students_promoted, students_retained, fee_balances_carried,
                        credit_notes_created, performed_at
                 FROM academic_year_rollover_log
                 WHERE from_year_id = ?
                 ORDER BY performed_at DESC LIMIT 20",
                [$currentYear['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $allTermsComplete = !array_filter($termsStatus, fn($t) => $t['status'] !== 'completed');

            return $this->success([
                'current_year'        => $currentYear,
                'terms'               => $termsStatus,
                'all_terms_complete'  => $allTermsComplete,
                'pending_results'     => (int)$pendingResults,
                'pending_promotions'  => (int)$pendingPromotions,
                'students_with_fees'  => (int)$outstandingFees,
                'ready_for_rollover'  => $allTermsComplete && $pendingResults == 0 && $pendingPromotions == 0,
                'rollover_log'        => $rolloverLog,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/academic/year-rollover
     * Executes one step of the year-end rollover process.
     * Body: { step: 'fee_carryover' | 'staff_reassignment' | 'create_new_year' | ... }
     */
    // TODO: Delegate to AcademicYearService
    public function postYearRollover($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $step = $data['step'] ?? null;
        if (!$step) {
            return $this->error('Missing required step parameter.');
        }

        try {
            $db = $this->db;
            $userId = $this->user['id'] ?? null;

            $currentYear = $db->query(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$currentYear) {
                error_log('[AcademicController] postYearRollover: no current academic year set');
                return $this->error('No current academic year is set.');
            }

            $rolloverRef = 'ROL-' . date('Ymd');
            $result = ['step' => $step, 'status' => 'completed'];

            if ($step === 'fee_carryover') {
                // For each student with outstanding balance, record carryover (as audit entry —
                // student_fee_carryover retired in the 3NF schema; balances are derived).
                // For students with surplus (credit), create fee_credit_notes.
                $students = $db->query(
                    "SELECT sae.student_id,
                            SUM(fb.balance) AS outstanding,
                            SUM(CASE WHEN fb.balance < 0 THEN ABS(fb.balance) ELSE 0 END) AS surplus
                     FROM vw_student_fee_balances fb
                     JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                     WHERE sae.academic_year_id = ?
                     GROUP BY sae.student_id",
                    [$currentYear['id']]
                )->fetchAll(\PDO::FETCH_ASSOC);

                $carried = 0; $credits = 0;
                foreach ($students as $s) {
                    if ((float)$s['outstanding'] > 0) {
                        // Record carryover
                        $db->query(
                            "INSERT INTO audit_logs
                             (action, entity, entity_id, user_id, ip_address, user_agent, details, status)
                             VALUES ('FEE_CARRYOVER', 'student', ?, ?, ?, ?, ?, 'completed')",
                            [$s['student_id'], $userId, $_SERVER['REMOTE_ADDR'] ?? null,
                             $_SERVER['HTTP_USER_AGENT'] ?? null,
                             'Year-end carryover of KES ' . $s['outstanding']]
                        );
                        $carried++;
                    }
                    if ((float)$s['surplus'] > 0) {
                        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad($credits + 1, 4, '0', STR_PAD_LEFT);
                        $db->query(
                            "INSERT INTO fee_credit_notes
                             (credit_number, student_id, academic_year, credit_amount, credit_reason, expiry_date, created_by)
                             VALUES (?, ?, ?, ?, 'overpayment', DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?)",
                            [$creditNum, $s['student_id'], $currentYear['year_code'], $s['surplus'], $userId]
                        );
                        $credits++;
                    }
                }
                $result['fee_balances_carried'] = $carried;
                $result['credit_notes_created'] = $credits;

            } elseif ($step === 'staff_reassignment') {
                // Count active teaching assignments in the new year (admin adjusts class/stream after)
                $count = $db->query(
                    "SELECT COUNT(*) FROM academic_year_class_learning_area_teachers aclat
                     JOIN academic_year_class_learning_areas aycl ON aycl.id = aclat.academic_year_class_learning_area_id
                     JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                     WHERE ayc.academic_year_id = ?",
                    [$currentYear['id']]
                )->fetchColumn();
                $result['staff_to_reassign'] = (int)$count;
                $result['note'] = 'Use Manage Staff → Class Assignments to confirm new year assignments';

            } elseif ($step === 'create_new_year') {
                $newYearCode = (int)$currentYear['year_code'] + 1;
                // Create new academic year
                $existing = $db->query("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch();
                if ($existing) {
                    $result['note'] = "Academic year $newYearCode already exists";
                    $result['new_year_id'] = $existing['id'];
                } else {
                    $newYearId = (int)$db->query("SELECT COALESCE(MAX(id),0)+1 FROM academic_years")->fetchColumn();
                    $db->query(
                        "INSERT INTO academic_years (id, year_code, year_name, start_date, end_date, status)
                         VALUES (?, ?, ?, ?, ?, 'planning')",
                        [
                            $newYearId,
                            $newYearCode,
                            "$newYearCode Academic Year",
                            "$newYearCode-01-06",
                            "$newYearCode-11-28",
                        ]
                    );

                    // Create 3 academic_year_terms against the shared terms (T1/T2/T3)
                    $terms = [
                        [1, "$newYearCode-01-06", "$newYearCode-04-04"],
                        [2, "$newYearCode-04-28", "$newYearCode-08-01"],
                        [3, "$newYearCode-08-25", "$newYearCode-11-28"],
                    ];
                    foreach ($terms as [$termNo, $start, $end]) {
                        $aytId = (int)$db->query("SELECT COALESCE(MAX(id),0)+1 FROM academic_year_terms")->fetchColumn();
                        $db->query(
                            "INSERT INTO academic_year_terms (id, academic_year_id, term_id, status, opening_date, closing_date)
                             SELECT ?, ?, t.id, 'upcoming', ?, ?
                             FROM terms t WHERE t.code = ?",
                            [$aytId, $newYearId, $start, $end, "T$termNo"]
                        );
                    }
                    $result['new_year_id'] = $newYearId;
                    $result['new_year_code'] = $newYearCode;
                    $result['terms_created'] = 3;
                }

            } elseif ($step === 'archive_old_year') {
                $db->query(
                    "UPDATE academic_years SET status = 'archived', is_current = 0 WHERE id = ?",
                    [$currentYear['id']]
                );
                $db->query(
                    "INSERT INTO academic_year_archives
                     (academic_year, status, closure_initiated_by, closure_date)
                     VALUES (?, 'archived', ?, NOW())
                     ON DUPLICATE KEY UPDATE status = 'archived', archived_at = NOW()",
                    [$currentYear['year_code'], $userId]
                );
                $result['archived_year'] = $currentYear['year_code'];

            } elseif ($step === 'activate_new_year') {
                $newYearCode = (int)$currentYear['year_code'] + 1;
                $newYear = $db->query("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch();
                if (!$newYear) {
                    error_log('[AcademicController] postYearRollover activate_new_year: next year not found');
                    return $this->error('Next academic year does not exist; run create_new_year first.');
                }

                $db->query("UPDATE academic_years SET is_current = 0");
                $db->query(
                    "UPDATE academic_years SET is_current = 1, status = 'active' WHERE id = ?",
                    [$newYear['id']]
                );
                $db->query(
                    "UPDATE academic_year_terms SET status = 'current'
                     WHERE academic_year_id = ? AND term_id = (SELECT id FROM terms WHERE code = 'T1')",
                    [$newYear['id']]
                );
                $result['activated_year'] = $newYearCode;
            }

            // Log the rollover step
            $db->query(
                "INSERT INTO academic_year_rollover_log
                 (rollover_id, from_year_id, step, status, fee_balances_carried,
                  credit_notes_created, staff_reassigned, performed_by)
                 VALUES (?, ?, ?, 'completed', ?, ?, ?, ?)",
                [
                    $rolloverRef,
                    $currentYear['id'],
                    $step,
                    $result['fee_balances_carried'] ?? 0,
                    $result['credit_notes_created'] ?? 0,
                    $result['staff_to_reassign'] ?? 0,
                    $userId,
                ]
            );

            return $this->success($result);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // =========================================================================
    // DEPUTY HEADTEACHER — shared "My Teaching Today" panel
    // GET /api/academic/my-teaching-today
    // Returns the current user's class assignment, today's lessons, attendance,
    // and pending lesson plans — same data shown on both deputy dashboards.
    // =========================================================================
    public function getMyTeachingToday($id = null, $data = [], $segments = [])
    {
        try {
            $userId  = $this->user['user_id'] ?? null;
            $today   = date('Y-m-d');
            $dayName = date('l'); // Monday … Sunday

            // Resolve current staff record
            $staff = $this->db->query(
                "SELECT s.id AS staff_id, p.first_name, p.last_name
                 FROM staff s
                 JOIN users u ON u.person_id = s.person_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE u.id = ? LIMIT 1",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$staff) {
                return $this->success([
                    'class_name' => null, 'my_students' => 0,
                    'my_attendance_rate' => null, 'my_lessons_today' => 0,
                    'my_pending_plans' => 0, 'today_schedule' => [],
                ]);
            }

            $staffId = $staff['staff_id'];

            // Resolve current term
            $term = $this->db->query(
                "SELECT ayt.id, t.name, ayt.academic_year_id
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);

            $termId = $term['id'] ?? null;

            // Class assignment — is the deputy a class teacher for a stream?
            $classAssign = $this->db->query(
                "SELECT aycs.id AS stream_id, sn.name AS stream_name, c.name AS class_name, c.id AS class_id
                 FROM academic_year_class_streams aycs
                 JOIN streams sn ON sn.id = aycs.stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 WHERE aycs.class_teacher_id = ? AND ayc.academic_year_id = ?
                 LIMIT 1",
                [$staffId, $term['academic_year_id'] ?? 0]
            )->fetch(\PDO::FETCH_ASSOC);

            $streamId = $classAssign['stream_id'] ?? null;

            // Student count in assigned class
            $myStudents = 0;
            if ($streamId) {
                $myStudents = (int)$this->db->query(
                    "SELECT COUNT(*) FROM student_academic_enrollments
                     WHERE academic_year_class_stream_id = ? AND enrollment_status = 'active'",
                    [$streamId]
                )->fetchColumn();
            }

            // Today's attendance for this class
            $myAttendanceRate = null;
            $myPresent = 0;
            $myAbsent  = 0;
            if ($streamId && $myStudents > 0) {
                $attRow = $this->db->query(
                    "SELECT
                       SUM(sa.status = 'present') AS present_count,
                       SUM(sa.status = 'absent')  AS absent_count
                     FROM student_attendance sa
                     JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                     WHERE sae.academic_year_class_stream_id = ?
                       AND sa.date = ?",
                    [$streamId, $today]
                )->fetch(\PDO::FETCH_ASSOC);

                $myPresent = (int)($attRow['present_count'] ?? 0);
                $myAbsent  = (int)($attRow['absent_count'] ?? 0);
                if ($myStudents > 0) {
                    $myAttendanceRate = round(($myPresent / $myStudents) * 100);
                }
            }

            // Today's teaching schedule (from timetable)
            $todaySchedule = $this->db->query(
                "SELECT ts.start_time, ts.end_time, la.name AS subject, c.name AS class_name
                 FROM timetable_entries te
                 JOIN time_slots ts ON ts.id = te.time_slot_id
                 JOIN learning_areas la ON la.id = te.learning_area_id
                 JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 WHERE te.teacher_id = ? AND te.day_of_week = ? AND te.academic_year_term_id = ?
                 ORDER BY ts.start_time",
                [$staffId, $dayName, $termId ?? 0]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $schedule = array_map(function ($row) {
                return [
                    'time'       => substr($row['start_time'] ?? '', 0, 5) . '–' . substr($row['end_time'] ?? '', 0, 5),
                    'subject'    => $row['subject'],
                    'class_name' => $row['class_name'],
                ];
            }, $todaySchedule);

            // Pending lesson plans (draft, not yet approved)
            $pendingPlans = (int)$this->db->query(
                "SELECT COUNT(*) FROM lesson_plans lp
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc ON ayc.id = aycd.academic_year_calendar_id
                 WHERE lp.teacher_id = ? AND lp.status = 'draft'
                   AND (ayc.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)",
                [$staffId, $termId ?? 0]
            )->fetchColumn();

            return $this->success([
                'class_name'         => $classAssign['class_name'] ?? null,
                'stream_name'        => $classAssign['stream_name'] ?? null,
                'my_students'        => $myStudents,
                'my_attendance_rate' => $myAttendanceRate,
                'my_present'         => $myPresent,
                'my_absent'          => $myAbsent,
                'my_lessons_today'   => count($schedule),
                'my_pending_plans'   => $pendingPlans,
                'today_schedule'     => $schedule,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // =========================================================================
    // DEPUTY HEAD (ACADEMIC) — admin summary
    // GET /api/academic/deputy-academic-summary
    // =========================================================================
    public function getDeputyAcademicSummary($id = null, $data = [], $segments = [])
    {
        try {
            $term = $this->db->query(
                "SELECT ayt.id, ayt.academic_year_id, t.name
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId   = $term['id'] ?? 0;
            $yearId   = $term['academic_year_id'] ?? 0;
            $today    = date('Y-m-d');

            // Pending admissions
            $pendingAdm = (int)$this->db->query(
                "SELECT COUNT(*) FROM admission_applications WHERE status IN ('pending','reviewing')"
            )->fetchColumn();

            // Lesson plans pending deputy review
            $lpPending = (int)$this->db->query(
                "SELECT COUNT(*) FROM lesson_plans lp
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc ON ayc.id = aycd.academic_year_calendar_id
                 WHERE lp.status = 'draft' AND (ayc.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)",
                [$termId]
            )->fetchColumn();

            // Exams scheduled this term
            $examsScheduled = (int)$this->db->query(
                "SELECT COUNT(*) FROM exam_schedules WHERE academic_year_term_id = ?", [$termId]
            )->fetchColumn();

            // Teachers who haven't submitted results yet
            $gradingPending = (int)$this->db->query(
                "SELECT COUNT(DISTINCT aclat.staff_id)
                 FROM academic_year_class_learning_area_teachers aclat
                 WHERE aclat.academic_year_term_id = ?
                   AND NOT EXISTS (
                     SELECT 1 FROM assessments a
                     JOIN formative_scores fs ON fs.assessment_id = a.id
                     WHERE a.academic_year_term_id = aclat.academic_year_term_id
                       AND a.assigned_by = aclat.staff_id
                   )",
                [$termId, $termId]
            )->fetchColumn();

            // Active timetable entries
            $activeTimetables = (int)$this->db->query(
                "SELECT COUNT(*) FROM timetable_entries WHERE academic_year_term_id = ?", [$termId]
            )->fetchColumn();

            // School attendance today
            $attRow = $this->db->query(
                "SELECT SUM(status = 'present') AS p, SUM(status = 'absent') AS a,
                        COUNT(*) AS total
                 FROM student_attendance WHERE date = ?",
                [$today]
            )->fetch(\PDO::FETCH_ASSOC);
            $present = (int)($attRow['p'] ?? 0);
            $absent  = (int)($attRow['a'] ?? 0);
            $total   = (int)($attRow['total'] ?? 0);
            $attPct  = $total > 0 ? round(($present / $total) * 100) : null;

            // Attendance trend (last 7 days)
            $attTrend = $this->db->query(
                "SELECT date, ROUND(AVG(status = 'present') * 100) AS pct
                 FROM student_attendance
                 WHERE date >= DATE_SUB(?, INTERVAL 7 DAY)
                 GROUP BY date ORDER BY date",
                [$today]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Class performance (avg score per class this term)
            $classPerf = $this->db->query(
                "SELECT c.name AS class_name, ROUND(AVG(fs.percentage), 1) AS avg_score
                 FROM formative_scores fs
                 JOIN assessments a ON a.id = fs.assessment_id AND a.academic_year_term_id = ?
                 JOIN student_academic_enrollments sae ON sae.student_id = fs.student_id AND sae.academic_year_id = ?
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 GROUP BY c.id ORDER BY c.name LIMIT 12",
                [$termId, $yearId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Pending admissions table (first 10)
            $admRows = $this->db->query(
                "SELECT applicant_name AS name,
                        grade_applying_for AS class, DATE(created_at) AS date, status
                 FROM admission_applications WHERE status IN ('pending','reviewing')
                 ORDER BY created_at DESC LIMIT 10"
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Lesson plans pending review table (first 10)
            $lpRows = $this->db->query(
                "SELECT lp.id,
                        CONCAT(p.first_name,' ',p.last_name) AS teacher_name,
                        c.name AS class_name,
                        la.name AS subject,
                        NULL AS week_label
                 FROM lesson_plans lp
                 JOIN staff s ON s.id = lp.teacher_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN academic_year_class_learning_areas aycl ON aycl.id = lp.academic_year_class_learning_area_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = aycl.learning_area_id
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc2 ON ayc2.id = aycd.academic_year_calendar_id
                 WHERE lp.status = 'draft' AND (ayc2.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)
                 ORDER BY lp.created_at ASC LIMIT 10",
                [$termId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Upcoming events (next 5)
            $events = $this->db->query(
                "SELECT title, DATE(start_at) AS date FROM school_events
                 WHERE start_at >= CURDATE() ORDER BY start_at LIMIT 5"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'cards' => [
                    'pending_admissions'          => ['count' => $pendingAdm, 'details' => 'Awaiting placement'],
                    'lesson_plans_pending_review' => $lpPending,
                    'exams_scheduled'             => $examsScheduled,
                    'grading_pending'             => $gradingPending,
                    'active_timetables'           => $activeTimetables,
                    'school_attendance'           => $attPct,
                    'present'                     => $present,
                    'absent'                      => $absent,
                ],
                'charts' => [
                    'attendance_trend' => [
                        'labels' => array_column($attTrend, 'date'),
                        'values' => array_column($attTrend, 'pct'),
                    ],
                    'class_performance' => [
                        'labels' => array_column($classPerf, 'class_name'),
                        'values' => array_column($classPerf, 'avg_score'),
                    ],
                ],
                'tables' => [
                    'pending_admissions'   => $admRows,
                    'lesson_plans_pending' => $lpRows,
                    'upcoming_events'      => $events,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // =========================================================================
    // DEPUTY HEAD (DISCIPLINE) — admin summary
    // GET /api/academic/deputy-discipline-summary
    // =========================================================================
    public function getDeputyDisciplineSummary($id = null, $data = [], $segments = [])
    {
        try {
            $term = $this->db->query(
                "SELECT ayt.id, ayt.academic_year_id
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $term['id'] ?? 0;
            $yearId = $term['academic_year_id'] ?? 0;
            $today  = date('Y-m-d');

            // Open discipline cases
            $openCases = (int)$this->db->query(
                "SELECT COUNT(*) FROM discipline_incidents WHERE status = 'pending'"
            )->fetchColumn();

            // Suspensions this term
            $suspensions = (int)$this->db->query(
                "SELECT COUNT(*) FROM discipline_incidents
                 WHERE action_taken LIKE '%suspend%' AND academic_year_term_id = ?",
                [$termId]
            )->fetchColumn();

            // Chronic absenteeism / truancy (students absent > 5 days this year)
            $truancy = (int)$this->db->query(
                "SELECT COUNT(DISTINCT sae.student_id) FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sa.status = 'absent' AND sae.academic_year_id = ?
                 GROUP BY sae.student_id HAVING COUNT(*) > 5",
                [$yearId]
            )->fetchColumn();

            // Pending parent meetings
            $parentMeetings = (int)$this->db->query(
                "SELECT COUNT(*) FROM school_events WHERE status = 'scheduled' AND start_at >= CURDATE()"
            )->fetchColumn();

            // Open counseling referrals
            $counselingReferrals = (int)$this->db->query(
                "SELECT COUNT(*) FROM student_counseling_sessions"
            )->fetchColumn();

            // School attendance today
            $attRow = $this->db->query(
                "SELECT SUM(status = 'present') AS p, SUM(status = 'absent') AS a, COUNT(*) AS t
                 FROM student_attendance WHERE date = ?",
                [$today]
            )->fetch(\PDO::FETCH_ASSOC);
            $present = (int)($attRow['p'] ?? 0);
            $absent  = (int)($attRow['a'] ?? 0);
            $total   = (int)($attRow['t'] ?? 0);
            $attPct  = $total > 0 ? round(($present / $total) * 100) : null;

            // Discipline trend (cases per week, last 8 weeks)
            $discTrend = $this->db->query(
                "SELECT YEARWEEK(created_at, 1) AS yw, COUNT(*) AS cases
                 FROM discipline_incidents
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
                 GROUP BY yw ORDER BY yw"
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance trend (last 7 days)
            $attTrend = $this->db->query(
                "SELECT date,
                        ROUND(AVG(status = 'present') * 100) AS present_pct,
                        ROUND(AVG(status = 'absent') * 100) AS absent_pct
                 FROM student_attendance
                 WHERE date >= DATE_SUB(?, INTERVAL 7 DAY)
                 GROUP BY date ORDER BY date",
                [$today]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Open discipline cases table (first 10)
            $caseRows = $this->db->query(
                "SELECT CONCAT(p.first_name,' ',p.last_name) AS student,
                        c.name AS class, di.type AS issue,
                        DATE(di.incident_date) AS date, di.status
                 FROM discipline_incidents di
                 JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
                 JOIN students st ON st.id = sae.student_id
                 JOIN persons p ON p.id = st.person_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE di.status = 'pending' AND sae.academic_year_id = ?
                 ORDER BY di.incident_date DESC LIMIT 10",
                [$yearId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Pending parent meetings (next 5)
            $meetingRows = $this->db->query(
                "SELECT DATE(pm.start_at) AS meeting_date,
                        NULL AS parent_name, NULL AS student_name, pm.title AS reason
                 FROM school_events pm
                 WHERE pm.status = 'scheduled' AND pm.start_at >= CURDATE()
                 ORDER BY pm.start_at LIMIT 5"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $events = $this->db->query(
                "SELECT title, DATE(start_at) AS date FROM school_events
                 WHERE start_at >= CURDATE() ORDER BY start_at LIMIT 5"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'cards' => [
                    'open_cases'                => $openCases,
                    'suspensions_this_term'     => $suspensions,
                    'truancy_cases'             => $truancy,
                    'parent_meetings_pending'   => $parentMeetings,
                    'counseling_referrals_open' => $counselingReferrals,
                    'school_attendance'         => $attPct,
                    'present'                   => $present,
                    'absent'                    => $absent,
                ],
                'charts' => [
                    'discipline_trend' => [
                        'labels' => array_map(fn($r) => 'Wk ' . substr($r['yw'], -2), $discTrend),
                        'values' => array_column($discTrend, 'cases'),
                    ],
                    'attendance_trend' => [
                        'labels'  => array_column($attTrend, 'date'),
                        'present' => array_column($attTrend, 'present_pct'),
                        'absent'  => array_column($attTrend, 'absent_pct'),
                    ],
                ],
                'tables' => [
                    'discipline_cases' => $caseRows,
                    'parent_meetings'  => $meetingRows,
                    'upcoming_events'  => $events,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    // ========================================================================
    // STAFF TEACHING ASSIGNMENTS — CHECKPOINT 2
    // ========================================================================
    private function guardTeachingAssignments(string $permission = 'staff.teaching_assignments.manage')
    {
        try {
            $roles = $permission === 'staff.teaching_assignments.view'
                ? ['system administrator','school administrator','director','headteacher','deputy head - academic','class teacher','subject teacher']
                : ['system administrator','school administrator','headteacher','deputy head - academic'];
            $this->staffAccess->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** GET /api/academic/class-teachers or /{id} */
    public function getClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments('staff.teaching_assignments.view')) return $denied;
        try {
            if ($id !== null) {
                $row = $this->teachingAssignments->getClassTeacher((int)$id);
                return $row ? $this->success($row) : $this->notFound('Class teacher assignment not found');
            }
            return $this->success($this->teachingAssignments->listClassTeachers(array_merge($_GET, $data)));
        } catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Throwable $e) {
            return $this->serverError('Failed to load class teacher assignments', 'An internal error occurred.');
        }
    }

    /** POST /api/academic/class-teachers */
    public function postClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        try {
            $newId = $this->teachingAssignments->saveClassTeacher($data, null, $this->staffAccess->userId());
            $this->staffAccess->audit('create_class_teacher_assignment', 'staff_class_assignment', $newId, null, $data);
            return $this->created(['id'=>$newId], 'Class teacher assigned');
        } catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Throwable $e) {
            return $this->serverError('Failed to assign class teacher', 'An internal error occurred.');
        }
    }

    /** PUT /api/academic/class-teachers/{id} */
    public function putClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if (!$id) return $this->badRequest('Assignment ID is required');
        try {
            $before = $this->teachingAssignments->getClassTeacher((int)$id);
            $this->teachingAssignments->saveClassTeacher($data, (int)$id, $this->staffAccess->userId());
            $this->staffAccess->audit('update_class_teacher_assignment', 'staff_class_assignment', (int)$id, $before, $data);
            return $this->success(['id'=>(int)$id], 'Class teacher assignment updated');
        } catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** DELETE /api/academic/class-teachers/{id} */
    public function deleteClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if (!$id) return $this->badRequest('Assignment ID is required');
        $before = $this->teachingAssignments->getClassTeacher((int)$id);
        $this->teachingAssignments->remove((int)$id);
        $this->staffAccess->audit('remove_class_teacher_assignment', 'staff_class_assignment', (int)$id, $before, ['status'=>'completed']);
        return $this->success(null, 'Class teacher assignment removed');
    }

    /** GET /api/academic/subject-assignments or /{id} */
    public function getSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments('staff.teaching_assignments.view')) return $denied;
        try {
            if ($id !== null) {
                $row = $this->teachingAssignments->getSubjectAssignment((int)$id);
                return $row ? $this->success($row) : $this->notFound('Subject assignment not found');
            }
            return $this->success($this->teachingAssignments->listSubjectAssignments(array_merge($_GET, $data)));
        } catch (\Throwable $e) {
            return $this->serverError('Failed to load subject assignments', 'An internal error occurred.');
        }
    }

    /** POST /api/academic/subject-assignments */
    public function postSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        try {
            $newId=$this->teachingAssignments->saveSubjectAssignment($data,null,$this->staffAccess->userId());
            $this->staffAccess->audit('create_subject_assignment','staff_class_assignment',$newId,null,$data);
            return $this->created(['id'=>$newId],'Subject assignment created');
        } catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** PUT /api/academic/subject-assignments/{id} */
    public function putSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if(!$id)return $this->badRequest('Assignment ID is required');
        try{$before=$this->teachingAssignments->getSubjectAssignment((int)$id);$this->teachingAssignments->saveSubjectAssignment($data,(int)$id,$this->staffAccess->userId());$this->staffAccess->audit('update_subject_assignment','staff_class_assignment',(int)$id,$before,$data);return $this->success(['id'=>(int)$id],'Subject assignment updated');}
        catch (RuntimeException $e) { error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** DELETE /api/academic/subject-assignments/{id} */
    public function deleteSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if(!$id)return $this->badRequest('Assignment ID is required');
        $before=$this->teachingAssignments->getSubjectAssignment((int)$id);$this->teachingAssignments->remove((int)$id);$this->staffAccess->audit('remove_subject_assignment','staff_class_assignment',(int)$id,$before,['status'=>'completed']);return $this->success(null,'Subject assignment removed');
    }

    // ==================== CBC: SUB-STRANDS ====================

    /**
     * GET /api/academic/sub-strands?strand_id=X
     * Get sub-strands, optionally filtered by strand_id. If numeric ID in URL, return single.
     */
    public function getSubStrands($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->query(
                    "SELECT ss.*, s.name AS strand_name, s.code AS strand_code
                     FROM sub_strands ss
                     LEFT JOIN strands s ON s.id = ss.strand_id
                     WHERE ss.id = :id",
                    [':id' => $id]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? $this->success($row) : $this->notFound('Sub-strand not found');
            }
            $strandId = (int)($_GET['strand_id'] ?? 0);
            $where = $strandId ? 'WHERE ss.strand_id=:sid' : '';
            $stmt = $this->db->query(
                "SELECT ss.*, s.name AS strand_name, s.code AS strand_code
                 FROM sub_strands ss
                 LEFT JOIN strands s ON s.id = ss.strand_id
                 $where
                 ORDER BY s.sort_order, ss.sort_order, ss.id",
                $strandId ? [':sid' => $strandId] : []
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/sub-strands */
    public function postSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        try {
            if (empty($data['strand_id']) || empty($data['name'])) {
                return $this->badRequest('strand_id and name are required');
            }
            $strand = $this->db->query(
                "SELECT id FROM strands WHERE id = :id AND status = 'active'",
                [':id' => (int)$data['strand_id']]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$strand) return $this->badRequest('The selected strand does not exist or is inactive');
            $code = $data['code'] ?? '';
            if (!$code && !empty($data['strand_id'])) {
                $s = $this->db->query("SELECT code FROM strands WHERE id=:id", [':id'=>(int)$data['strand_id']])->fetch(\PDO::FETCH_ASSOC);
                $cnt = $this->db->query("SELECT COUNT(*) AS c FROM sub_strands WHERE strand_id=:sid", [':sid'=>(int)$data['strand_id']])->fetch(\PDO::FETCH_ASSOC);
                $code = ($s['code'] ?? 'S') . '-SS' . (($cnt['c'] ?? 0) + 1);
            }
            $stmt = $this->db->query(
                "INSERT INTO sub_strands (strand_id, code, name, description, sort_order, status)
                 VALUES (:sid, :code, :name, :desc, :sort, :status)",
                [
                    ':sid' => (int)$data['strand_id'],
                    ':code' => $code,
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':sort' => (int)($data['sort_order'] ?? 1),
                    ':status' => $data['status'] ?? 'active',
                ]
            );
            $newId = $this->db->lastInsertId();
            return $this->success(['id' => (int)$newId], 'Sub-strand created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/sub-strands/{id} */
    public function putSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Sub-strand ID is required');
        try {
            $fields = [];
            $params = [':id' => $id];
            if (array_key_exists('strand_id', $data)) {
                $strand = $this->db->query(
                    "SELECT id FROM strands WHERE id = :id AND status = 'active'",
                    [':id' => (int)$data['strand_id']]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$strand) return $this->badRequest('The selected strand does not exist or is inactive');
            }
            foreach (['strand_id', 'code', 'name', 'description', 'sort_order', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = $col === 'strand_id' || $col === 'sort_order' ? (int)$data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE sub_strands SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Sub-strand updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/sub-strands/{id} */
    public function deleteSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Sub-strand ID is required');
        try {
            $this->db->query("DELETE FROM sub_strands WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Sub-strand deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/sub-strands/bulk — auto-populate orphaned strands with default sub-strands */
    public function postSubStrandsBulk($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;

        // Curriculum records must come from an authoritative syllabus import.
        // Never fabricate sub-strands from strand names: that creates data which
        // looks complete but cannot support valid outcomes or assessment mapping.
        return $this->badRequest(
            'Bulk sub-strand creation is disabled. Import approved CBC curriculum data instead.'
        );
    }

    // ==================== CBC: LEARNING OUTCOMES ====================

    /**
     * GET /api/academic/learning-outcomes?sub_strand_id=X&strand_id=X&learning_area_id=X
     */
    public function getLearningOutcomes($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->query(
                    "SELECT lo.*, la.name AS learning_area_name
                     FROM learning_outcomes lo
                     LEFT JOIN learning_areas la ON la.id = lo.learning_area_id
                     WHERE lo.id = :id",
                    [':id' => $id]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? $this->success($row) : $this->notFound('Learning outcome not found');
            }
            $conds = [];
            $params = [];
            if (!empty($_GET['sub_strand_id'])) { $conds[] = 'lo.sub_strand_id=:ssid'; $params[':ssid'] = (int)$_GET['sub_strand_id']; }
            if (!empty($_GET['strand_id'])) { $conds[] = 's.id=:stid'; $params[':stid'] = (int)$_GET['strand_id']; }
            if (!empty($_GET['learning_area_id'])) { $conds[] = 'lo.learning_area_id=:laid'; $params[':laid'] = (int)$_GET['learning_area_id']; }
            if (!empty($_GET['grade_level'])) { $conds[] = 'lo.grade_level=:gl'; $params[':gl'] = $_GET['grade_level']; }
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            $stmt = $this->db->query(
                "SELECT lo.*, la.name AS learning_area_name, ss.name AS sub_strand_name
                 FROM learning_outcomes lo
                 LEFT JOIN learning_areas la ON la.id = lo.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.id = lo.sub_strand_id
                 LEFT JOIN strands s ON s.id = ss.strand_id
                 $where
                 ORDER BY la.name, lo.id",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/learning-outcomes */
    public function postLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        try {
            if (empty($data['learning_area_id']) || empty($data['outcome']) || empty($data['grade_level'])) {
                return $this->badRequest('learning_area_id, outcome, and grade_level are required');
            }
            $area = $this->db->query(
                "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                [':id' => (int)$data['learning_area_id']]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$area) return $this->badRequest('The selected learning area does not exist or is inactive');
            if (!empty($data['sub_strand_id'])) {
                $subStrand = $this->db->query(
                    "SELECT ss.id FROM sub_strands ss
                     JOIN strands s ON s.id = ss.strand_id
                     WHERE ss.id = :id AND s.learning_area_id = :area_id AND ss.status = 'active'",
                    [':id' => (int)$data['sub_strand_id'], ':area_id' => (int)$data['learning_area_id']]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$subStrand) return $this->badRequest('The selected sub-strand does not belong to the learning area');
            }
            $stmt = $this->db->query(
                "INSERT INTO learning_outcomes (learning_area_id, sub_strand_id, outcome, grade_level)
                 VALUES (:laid, :ssid, :outcome, :gl)",
                [
                    ':laid' => (int)$data['learning_area_id'],
                    ':ssid' => !empty($data['sub_strand_id']) ? (int)$data['sub_strand_id'] : null,
                    ':outcome' => $data['outcome'],
                    ':gl' => $data['grade_level'],
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Learning outcome created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/learning-outcomes/{id} */
    public function putLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Learning outcome ID is required');
        try {
            if (array_key_exists('learning_area_id', $data)) {
                $area = $this->db->query(
                    "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                    [':id' => (int)$data['learning_area_id']]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$area) return $this->badRequest('The selected learning area does not exist or is inactive');
            }
            if (!empty($data['sub_strand_id'])) {
                $areaId = (int)($data['learning_area_id'] ?? 0);
                if (!$areaId) {
                    $areaId = (int)$this->db->query(
                        "SELECT learning_area_id FROM learning_outcomes WHERE id = :id",
                        [':id' => (int)$id]
                    )->fetchColumn();
                }
                $subStrand = $this->db->query(
                    "SELECT ss.id FROM sub_strands ss
                     JOIN strands s ON s.id = ss.strand_id
                     WHERE ss.id = :id AND s.learning_area_id = :area_id AND ss.status = 'active'",
                    [':id' => (int)$data['sub_strand_id'], ':area_id' => $areaId]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$subStrand) return $this->badRequest('The selected sub-strand does not belong to the learning area');
            }
            $fields = [];
            $params = [':id' => $id];
            foreach (['learning_area_id', 'sub_strand_id', 'outcome', 'grade_level'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['learning_area_id', 'sub_strand_id']) && $data[$col] !== null ? (int)$data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE learning_outcomes SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Learning outcome updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/learning-outcomes/{id} */
    public function deleteLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Learning outcome ID is required');
        try {
            $this->db->query("DELETE FROM learning_outcomes WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Learning outcome deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: ASSESSMENT RUBRICS ====================

    /** GET /api/academic/assessment-rubrics?tool_id=X */
    public function getAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->query(
                    "SELECT ar.*, at.tool_name
                     FROM assessment_rubrics ar
                     LEFT JOIN assessment_tools at ON at.id = ar.tool_id
                     WHERE ar.id = :id",
                    [':id' => $id]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? $this->success($row) : $this->notFound('Assessment rubric not found');
            }
            $toolId = (int)($_GET['tool_id'] ?? 0);
            $where = $toolId ? 'WHERE ar.tool_id=:tid' : '';
            $stmt = $this->db->query(
                "SELECT ar.*, at.tool_name
                 FROM assessment_rubrics ar
                 LEFT JOIN assessment_tools at ON at.id = ar.tool_id
                 $where
                 ORDER BY ar.sort_order, ar.id",
                $toolId ? [':tid' => $toolId] : []
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/assessment-rubrics */
    public function postAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        try {
            if (empty($data['tool_id']) || empty($data['criteria_name'])) {
                return $this->badRequest('tool_id and criteria_name are required');
            }
            $tool = $this->db->query(
                "SELECT id FROM assessment_tools WHERE id = :id AND status = 'active'",
                [':id' => (int)$data['tool_id']]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$tool) return $this->badRequest('The selected assessment tool does not exist or is inactive');
            $stmt = $this->db->query(
                "INSERT INTO assessment_rubrics (tool_id, criteria_name, level_1_descriptor, level_2_descriptor, level_3_descriptor, level_4_descriptor, points_per_level, sort_order)
                 VALUES (:tid, :cn, :l1, :l2, :l3, :l4, :pts, :sort)",
                [
                    ':tid' => (int)$data['tool_id'],
                    ':cn' => $data['criteria_name'],
                    ':l1' => $data['level_1_descriptor'] ?? null,
                    ':l2' => $data['level_2_descriptor'] ?? null,
                    ':l3' => $data['level_3_descriptor'] ?? null,
                    ':l4' => $data['level_4_descriptor'] ?? null,
                    ':pts' => (int)($data['points_per_level'] ?? 0),
                    ':sort' => (int)($data['sort_order'] ?? 1),
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Rubric created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/assessment-rubrics/{id} */
    public function putAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Rubric ID is required');
        try {
            if (array_key_exists('tool_id', $data)) {
                $tool = $this->db->query(
                    "SELECT id FROM assessment_tools WHERE id = :id AND status = 'active'",
                    [':id' => (int)$data['tool_id']]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$tool) return $this->badRequest('The selected assessment tool does not exist or is inactive');
            }
            $fields = [];
            $params = [':id' => $id];
            foreach (['tool_id', 'criteria_name', 'level_1_descriptor', 'level_2_descriptor', 'level_3_descriptor', 'level_4_descriptor', 'points_per_level', 'sort_order'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['tool_id', 'points_per_level', 'sort_order']) ? (int)$data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE assessment_rubrics SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Rubric updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/assessment-rubrics/{id} */
    public function deleteAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Rubric ID is required');
        try {
            $this->db->query("DELETE FROM assessment_rubrics WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Rubric deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: GRADING SCALE (DB-DRIVEN) ====================
    // Single source of truth for grade boundaries lives in `grading_scales`
    // (the scale header) and `grade_rules` (per-band rows: min/max marks,
    // grade code, points, performance level, description). No thresholds are
    // hardcoded in the frontend; all pages resolve grades from these rows.

    /** GET /api/academic/grading-scale|/grading-scale/{id} - Fetch a grading scale + its grade rules */
    public function getGradingScale($id = null, $data = [], $segments = [])
    {
        try {
            if (isset($_GET['all'])) {
                $scales = $this->db->query(
                    "SELECT * FROM grading_scales ORDER BY (status='active') DESC, id"
                )->fetchAll(\PDO::FETCH_ASSOC);
                $result = [];
                foreach ($scales as $sc) {
                    $rules = $this->db->query(
                        "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order
                         FROM grade_rules
                         WHERE scale_id=:sid
                         ORDER BY sort_order, min_mark DESC",
                        [':sid' => $sc['id']]
                    )->fetchAll(\PDO::FETCH_ASSOC);
                    $result[] = ['scale' => $sc, 'rules' => $rules];
                }
                return $this->success($result);
            }
            if ($id) {
                $scale = $this->db->query(
                    "SELECT * FROM grading_scales WHERE id=:id",
                    [':id' => $id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$scale) return $this->notFound('Grading scale not found');
            } else {
                $scale = $this->db->query(
                    "SELECT * FROM grading_scales WHERE status='active' ORDER BY id LIMIT 1"
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$scale) return $this->success(['scale' => null, 'rules' => []]);
            }
            $rules = $this->db->query(
                "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order
                 FROM grade_rules
                 WHERE scale_id=:sid
                 ORDER BY sort_order, min_mark DESC",
                [':sid' => $scale['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success(['scale' => $scale, 'rules' => $rules]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/grading-scale - Create a grading scale */
    public function postGradingScale($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (empty($data['name'])) return $this->badRequest('Scale name is required');
        try {
            $stmt = $this->db->query(
                "INSERT INTO grading_scales (name, description, min_mark, max_mark, status)
                 VALUES (:name, :desc, :min, :max, :status)",
                [
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':min' => (float)($data['min_mark'] ?? 0),
                    ':max' => (float)($data['max_mark'] ?? 100),
                    ':status' => in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active',
                ]
            );
            $newId = (int)$this->db->lastInsertId();
            if (($data['status'] ?? 'active') === 'active') {
                $this->db->query("UPDATE grading_scales SET status='inactive' WHERE status='active' AND id<>:id", [':id' => $newId]);
            }
            return $this->success(['id' => $newId], 'Grading scale created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/grading-scale/{id} - Update a grading scale */
    public function putGradingScale($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Scale ID is required');
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['name', 'description', 'min_mark', 'max_mark', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    if ($col === 'name') $params[":$col"] = $data[$col];
                    elseif ($col === 'description') $params[":$col"] = $data[$col];
                    elseif ($col === 'status') $params[":$col"] = in_array($data[$col], ['active', 'inactive']) ? $data[$col] : 'active';
                    else $params[":$col"] = (float)$data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE grading_scales SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            if (($data['status'] ?? '') === 'active') {
                $this->db->query("UPDATE grading_scales SET status='inactive' WHERE status='active' AND id<>:id", [':id' => $id]);
            }
            return $this->success(['id' => (int)$id], 'Grading scale updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/grade-rules - Create a grade rule (range → grade) */
    public function postGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        $required = ['scale_id', 'grade_code', 'grade_name', 'min_mark', 'max_mark'];
        foreach ($required as $k) {
            if (empty($data[$k])) return $this->badRequest("{$k} is required");
        }
        try {
            $scale = $this->db->query("SELECT id FROM grading_scales WHERE id=:id", [':id' => (int)$data['scale_id']])->fetch(\PDO::FETCH_ASSOC);
            if (!$scale) return $this->badRequest('The selected grading scale does not exist');
            $stmt = $this->db->query(
                "INSERT INTO grade_rules (scale_id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order)
                 VALUES (:sid, :code, :name, :min, :max, :points, :level, :desc, :sort)",
                [
                    ':sid' => (int)$data['scale_id'],
                    ':code' => strtoupper($data['grade_code']),
                    ':name' => $data['grade_name'],
                    ':min' => (float)$data['min_mark'],
                    ':max' => (float)$data['max_mark'],
                    ':points' => (float)($data['grade_points'] ?? 0),
                    ':level' => $data['performance_level'] ?? '',
                    ':desc' => $data['description'] ?? null,
                    ':sort' => (int)($data['sort_order'] ?? 1),
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Grade rule created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/grade-rules/{id} - Update a grade rule */
    public function putGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Grade rule ID is required');
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['scale_id', 'grade_code', 'grade_name', 'min_mark', 'max_mark', 'grade_points', 'performance_level', 'description', 'sort_order'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    if ($col === 'grade_code') $params[":$col"] = strtoupper($data[$col]);
                    elseif (in_array($col, ['scale_id', 'sort_order'])) $params[":$col"] = (int)$data[$col];
                    elseif (in_array($col, ['min_mark', 'max_mark', 'grade_points'])) $params[":$col"] = (float)$data[$col];
                    else $params[":$col"] = $data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE grade_rules SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Grade rule updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/grade-rules/{id} - Delete a grade rule */
    public function deleteGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Grade rule ID is required');
        try {
            $this->db->query("DELETE FROM grade_rules WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Grade rule deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: STRAND-COMPETENCY CROSSWALK ====================

    /** GET /api/academic/strand-competencies?strand_id=X&competency_id=X */
    public function getStrandCompetencies($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->query(
                    "SELECT sc.*, s.name AS strand_name, cc.name AS competency_name
                     FROM strand_competency sc
                     LEFT JOIN strands s ON s.id = sc.strand_id
                     LEFT JOIN core_competencies cc ON cc.id = sc.competency_id
                     WHERE sc.id = :id",
                    [':id' => $id]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? $this->success($row) : $this->notFound('Strand-competency mapping not found');
            }
            $conds = [];
            $params = [];
            if (!empty($_GET['strand_id'])) { $conds[] = 'sc.strand_id=:sid'; $params[':sid'] = (int)$_GET['strand_id']; }
            if (!empty($_GET['competency_id'])) { $conds[] = 'sc.competency_id=:cid'; $params[':cid'] = (int)$_GET['competency_id']; }
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            $stmt = $this->db->query(
                "SELECT sc.*, s.name AS strand_name, cc.name AS competency_name
                 FROM strand_competency sc
                 LEFT JOIN strands s ON s.id = sc.strand_id
                 LEFT JOIN core_competencies cc ON cc.id = sc.competency_id
                 $where
                 ORDER BY s.name, cc.name",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/strand-competencies */
    public function postStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        try {
            if (empty($data['strand_id']) || empty($data['competency_id'])) {
                return $this->badRequest('strand_id and competency_id are required');
            }
            $this->db->query(
                "INSERT INTO strand_competency (strand_id, competency_id, weight)
                 VALUES (:sid, :cid, :w)
                 ON DUPLICATE KEY UPDATE weight=VALUES(weight)",
                [
                    ':sid' => (int)$data['strand_id'],
                    ':cid' => (int)$data['competency_id'],
                    ':w' => (float)($data['weight'] ?? 1.00),
                ]
            );
            return $this->success(['id' => (int)$this->db->lastInsertId()], 'Mapping created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** PUT /api/academic/strand-competencies/{id} */
    public function putStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Mapping ID is required');
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['strand_id', 'competency_id', 'weight'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = $col === 'weight' ? (float)$data[$col] : (int)$data[$col];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update');
            $this->db->query("UPDATE strand_competency SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->success(['id' => (int)$id], 'Mapping updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/academic/strand-competencies/{id} */
    public function deleteStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Mapping ID is required');
        try {
            $this->db->query("DELETE FROM strand_competency WHERE id=:id", [':id' => $id]);
            return $this->success(null, 'Mapping deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== CBC: CURRICULUM TREE ====================

    /**
     * GET /api/academic/curriculum-tree?learning_area_id=X&strand_id=X
     * Returns the full CBC curriculum tree: learning areas -> strands -> sub-strands -> learning outcomes
     */
    public function getCurriculumTree($id = null, $data = [], $segments = [])
    {
        try {
            $laWhere = '';
            $laParams = [];
            if (!empty($_GET['learning_area_id'])) {
                $laWhere = 'WHERE la.id=:laid';
                $laParams[':laid'] = (int)$_GET['learning_area_id'];
            }
            $areas = $this->db->query(
                "SELECT la.id, la.code, la.name FROM learning_areas la $laWhere ORDER BY la.name",
                $laParams
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($areas as &$area) {
                $sWhere = '';
                $sParams = [];
                $sWhere = 'WHERE s.learning_area_id=:laid';
                $sParams[':laid'] = $area['id'];
                if (!empty($_GET['grade_level'])) {
                    $sWhere .= ' AND s.grade_level=:grade';
                    $sParams[':grade'] = $_GET['grade_level'];
                }
                if (!empty($_GET['strand_id'])) {
                    $sWhere .= ' AND s.id=:sid';
                    $sParams[':sid'] = (int)$_GET['strand_id'];
                }
                $strands = $this->db->query(
                    "SELECT s.id, s.code, s.name, s.grade_level, s.variant, s.source_subject, s.level_range, s.sort_order
                     FROM strands s $sWhere ORDER BY s.sort_order, s.id",
                    $sParams
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($strands as &$strand) {
                    $subStrands = $this->db->query(
                        "SELECT ss.id, ss.code, ss.name, ss.sort_order
                         FROM sub_strands ss WHERE ss.strand_id=:sid AND ss.status='active'
                         ORDER BY ss.sort_order, ss.id",
                        [':sid' => $strand['id']]
                    )->fetchAll(\PDO::FETCH_ASSOC);

                    foreach ($subStrands as &$ss) {
                        $los = $this->db->query(
                            "SELECT lo.id, lo.outcome, lo.grade_level
                             FROM learning_outcomes lo WHERE lo.sub_strand_id=:ssid
                             ORDER BY lo.id",
                            [':ssid' => $ss['id']]
                        )->fetchAll(\PDO::FETCH_ASSOC);
                        $ss['learning_outcomes'] = $los;
                    }

                    $competencies = $this->db->query(
                        "SELECT sc.id, cc.id AS competency_id, cc.name AS competency_name, sc.weight
                         FROM strand_competency sc
                         JOIN core_competencies cc ON cc.id = sc.competency_id
                         WHERE sc.strand_id=:sid ORDER BY cc.name",
                        [':sid' => $strand['id']]
                    )->fetchAll(\PDO::FETCH_ASSOC);
                    $strand['sub_strands'] = $subStrands;
                    $strand['competencies'] = $competencies;
                }
                $area['strands'] = $strands;
            }
            return $this->success($areas);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== EXAM MODERATION ====================

    /**
     * GET /api/academic/pending-moderation?class_id=X&subject_id=X
     * Returns assessments with results submitted but not yet approved (pending moderation).
     */
    public function getPendingModeration($id = null, $data = [], $segments = [])
    {
        try {
            $conds = ["ar.is_submitted=1", "ar.is_approved=0"];
            $params = [];
            if (!empty($_GET['class_id'])) { $conds[] = 'a.academic_year_class_stream_id=:cid'; $params[':cid'] = (int)$_GET['class_id']; }
            if (!empty($_GET['subject_id'])) { $conds[] = 'a.learning_area_id=:sid'; $params[':sid'] = (int)$_GET['subject_id']; }
            if (!empty($_GET['term_id'])) { $conds[] = 'a.academic_year_term_id=:tid'; $params[':tid'] = (int)$_GET['term_id']; }
            $where = 'WHERE ' . implode(' AND ', $conds);

            $stmt = $this->db->query(
                "SELECT a.id AS assessment_id, a.title, a.max_marks, a.assessment_date, a.status,
                        a.academic_year_class_stream_id AS class_id, a.learning_area_id AS subject_id, a.academic_year_term_id AS term_id,
                        c.name AS class_name, la.name AS subject_name, t.name AS term_name,
                        COUNT(ar.id) AS total_students,
                        SUM(CASE WHEN ar.is_approved=1 THEN 1 ELSE 0 END) AS approved_count,
                        AVG(ar.marks_obtained) AS avg_mark
                 FROM assessments a
                 JOIN assessment_results ar ON ar.assessment_id = a.id
                 JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 $where
                 GROUP BY a.id
                 ORDER BY a.assessment_date DESC, a.id",
                $params
            );
            $assessments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($assessments as &$ass) {
                $results = $this->db->query(
                    "SELECT ar.id AS result_id, sae.student_id, ar.marks_obtained, ar.grade, ar.points, ar.is_approved, ar.remarks,
                            CONCAT(p.first_name, ' ', p.last_name) AS student_name, s.admission_no
                     FROM assessment_results ar
                     JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                     JOIN students s ON s.id = sae.student_id
                     JOIN persons p ON p.id = s.person_id
                     WHERE ar.assessment_id = :aid AND ar.is_submitted=1
                     ORDER BY p.first_name",
                    [':aid' => $ass['assessment_id']]
                )->fetchAll(\PDO::FETCH_ASSOC);
                $ass['results'] = $results;
                $ass['pending_count'] = (int)$ass['total_students'] - (int)$ass['approved_count'];
            }

            return $this->success($assessments);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/approve-assessment — approve individual assessment results */
    public function postApproveAssessment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        try {
            $assessmentId = (int)($data['assessment_id'] ?? 0);
            $studentId = isset($data['student_id']) ? (int)$data['student_id'] : null;
            if (!$assessmentId) return $this->badRequest('assessment_id is required');

            if ($studentId) {
                $this->db->query(
                    "UPDATE assessment_results ar
                     JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                     SET ar.is_approved=1
                     WHERE ar.assessment_id=:aid AND sae.student_id=:sid",
                    [':aid' => $assessmentId, ':sid' => $studentId]
                );
                return $this->success(null, 'Result approved');
            }
            $remarks = $data['remarks'] ?? '';
            $result = $this->api->moderateExamMarks($assessmentId, $remarks, false);
            return $this->handleResponse($result);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/reject-assessment — reject individual result */
    public function postRejectAssessment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        try {
            $assessmentId = (int)($data['assessment_id'] ?? 0);
            $studentId = (int)($data['student_id'] ?? 0);
            $reason = $data['reason'] ?? '';
            if (!$assessmentId || !$studentId) return $this->badRequest('assessment_id and student_id are required');

            $this->db->query(
                "UPDATE assessment_results SET is_approved=0, remarks=:reason WHERE assessment_id=:aid AND student_id=:sid",
                [':aid' => $assessmentId, ':sid' => $studentId, ':reason' => $reason]
            );
            return $this->success(null, 'Result rejected');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== LEGACY CURRICULUM ENDPOINT ====================

    /**
     * GET /api/academic/curriculum — backward-compatible flat curriculum list
     * Returns strands + sub-strands in flat format for curriculum_cbc.js.
     */
    public function getCurriculum($id = null, $data = [], $segments = [])
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;

            $conds = [];
            $params = [];
            if (!empty($_GET['learning_area'])) {
                $conds[] = 'la.name LIKE :la';
                $params[':la'] = '%' . $_GET['learning_area'] . '%';
            }
            if (!empty($_GET['strand'])) {
                $conds[] = 's.name LIKE :st';
                $params[':st'] = '%' . $_GET['strand'] . '%';
            }
            if (!empty($_GET['grade_level'])) {
                $conds[] = 's.grade_level = :gl';
                $params[':gl'] = $_GET['grade_level'];
            }
            if (!empty($_GET['search'])) {
                $conds[] = '(s.name LIKE :q OR ss.name LIKE :q2 OR la.name LIKE :q3)';
                $params[':q'] = '%' . $_GET['search'] . '%';
                $params[':q2'] = '%' . $_GET['search'] . '%';
                $params[':q3'] = '%' . $_GET['search'] . '%';
            }
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

            $countStmt = $this->db->query(
                "SELECT COUNT(*) AS total FROM strands s
                 LEFT JOIN sub_strands ss ON ss.strand_id = s.id
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 $where",
                $params
            );
            $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->query(
                "SELECT s.id, s.code AS strand_code, s.name AS strand, s.grade_level AS grade_level,
                        la.name AS learning_area, ss.name AS sub_strand, ss.code AS sub_strand_code
                 FROM strands s
                 LEFT JOIN sub_strands ss ON ss.strand_id = s.id
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 $where
                 ORDER BY la.name, s.sort_order, s.id, ss.sort_order
                 LIMIT $limit OFFSET $offset",
                $params
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'data' => $rows,
                'curriculum' => $rows,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->success(['data' => [], 'curriculum' => [], 'total' => 0, 'pagination' => ['page' => 1, 'limit' => 15, 'total' => 0]]);
        }
    }

    // ==================== PORTFOLIO MANAGEMENT ====================

    /**
     * GET /api/academic/portfolio/all/{studentId}
     * Returns cumulative portfolio data across ALL years for print/PDF.
     * Response: { student, portfolios[], artifacts[], competencySummary[],
     *             valuesSummary[], teacherFeedback, yearRange, totalArtifacts }
     */
    public function getPortfolioAll($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = $id ? (int)$id : (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('Student ID is required');

            // Student info
            $st = $this->db->query(
                "SELECT s.id, p.first_name, p.middle_name, p.last_name, s.admission_no, p.photo_url,
                        c.name AS class_name, st.name AS stream_name
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE s.id = :sid",
                [':sid' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$st) return $this->notFound('Student not found');

            // All portfolios
            $portfolios = $this->db->query(
                "SELECT * FROM portfolios WHERE student_id = :sid ORDER BY academic_year DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // All artifacts across all portfolios — LEFT JOIN competencies + values
            $artifacts = $this->db->query(
                "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name,
                        p.academic_year
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                 LEFT JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Competency summary
            $compSummary = $this->db->query(
                "SELECT cc.name AS competency_name,
                        COUNT(pa.id) AS artifact_count,
                        ROUND(AVG(pa.rating), 1) AS avg_rating,
                        MAX(pa.rating) AS highest_rating
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_competencies cc ON cc.id = pa.competency_id
                 WHERE p.student_id = :sid AND pa.competency_id IS NOT NULL
                 GROUP BY cc.id, cc.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Values summary
            $valsSummary = $this->db->query(
                "SELECT cv.name AS value_name, COUNT(pa.id) AS artifact_count
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid AND pa.value_id IS NOT NULL
                 GROUP BY cv.id, cv.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Aggregated teacher feedback
            $fbRows = $this->db->query(
                "SELECT pa.teacher_feedback
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 WHERE p.student_id = :sid
                   AND pa.teacher_feedback IS NOT NULL
                   AND pa.teacher_feedback != ''
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $teacherFeedback = implode("\n---\n", array_column($fbRows, 'teacher_feedback'));

            // Year range
            $years = array_values(array_unique(array_filter(array_column($artifacts, 'academic_year'))));
            sort($years);
            $yearRange = $years
                ? (count($years) > 1 ? min($years) . ' \u2013 ' . max($years) : (string) $years[0])
                : (string) date('Y');

            return $this->success([
                'student' => $st,
                'portfolios' => $portfolios,
                'artifacts' => $artifacts,
                'competencySummary' => $compSummary,
                'valuesSummary' => $valsSummary,
                'teacherFeedback' => $teacherFeedback,
                'yearRange' => $yearRange,
                'totalArtifacts' => count($artifacts),
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academic/portfolio/list — List portfolios for a student or class
     * Query params: student_id, class_id, stream_id, status
     */
    public function getPortfolioList($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
            $classId = (int)($data['class_id'] ?? $_GET['class_id'] ?? 0);
            $status = $data['status'] ?? $_GET['status'] ?? '';

            $conds = [];
            $params = [];
            if ($studentId) {
                $conds[] = 'p.student_id = :sid';
                $params[':sid'] = $studentId;
            }
            if ($status) {
                $conds[] = 'p.status = :st';
                $params[':st'] = $status;
            }

            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            if ($classId) {
                $sql = "
                    SELECT p.*, pn.first_name, pn.middle_name, pn.last_name, s.admission_no,
                           c.name AS class_name, st.name AS stream_name,
                           (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                    FROM portfolios p
                    JOIN students s ON s.id = p.student_id
                    JOIN persons pn ON pn.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    WHERE ayc.class_id = :cid $where
                    ORDER BY p.last_updated DESC
                ";
                $params[':cid'] = $classId;
            } else {
                $sql = "
                    SELECT p.*, pn.first_name, pn.middle_name, pn.last_name, s.admission_no,
                           c.name AS class_name, st.name AS stream_name,
                           (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                    FROM portfolios p
                    JOIN students s ON s.id = p.student_id
                    JOIN persons pn ON pn.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    $where
                    ORDER BY p.last_updated DESC
                ";
            }

            $stmt = $this->db->query($sql, $params);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $this->success($rows);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->success([]);
        }
    }

    /**
     * GET /api/academic/portfolio/get/{studentId} — Get portfolio + artifacts for a student
     */
    public function getPortfolioGet($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = $id ? (int)$id : (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $portfolio = $this->db->query("
                SELECT p.*,
                       (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                FROM portfolios p
                WHERE p.student_id = :sid AND p.status = 'active'
                ORDER BY p.created_date DESC
                LIMIT 1
            ", [':sid' => $studentId])->fetch(\PDO::FETCH_ASSOC);

            $artifacts = [];
            if ($portfolio) {
                $artifacts = $this->db->query("
                    SELECT pa.*, cc.name AS competency_name, cv.name AS value_name
                    FROM portfolio_artifacts pa
                    LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                    LEFT JOIN core_values cv ON cv.id = pa.value_id
                    WHERE pa.portfolio_id = :pid
                    ORDER BY pa.upload_date DESC
                ", [':pid' => $portfolio['id']])->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $this->success([
                'portfolio' => $portfolio,
                'artifacts' => $artifacts,
            ]);
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->success(['portfolio' => null, 'artifacts' => []]);
        }
    }

    /**
     * POST /api/academic/portfolio/create — Create a portfolio for a student
     */
    public function postPortfolioCreate($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            $title = trim($data['title'] ?? '');
            $academicYear = (int)($data['academic_year'] ?? date('Y'));
            $type = $data['portfolio_type'] ?? 'digital';
            $description = trim($data['description'] ?? '');

            if (!$studentId || !$title) {
                return $this->badRequest('student_id and title are required');
            }

            $this->db->query(
                "INSERT INTO portfolios (student_id, academic_year, portfolio_type, title, description, created_date, last_updated, status, created_at, updated_at)
                 VALUES (:sid, :ay, :pt, :title, :desc, CURDATE(), CURDATE(), 'active', NOW(), NOW())",
                [':sid' => $studentId, ':ay' => $academicYear, ':pt' => $type, ':title' => $title, ':desc' => $description]
            );

            return $this->success(['id' => $this->db->lastInsertId()], 'Portfolio created');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/academic/portfolio/artifact-add — Add artifact to portfolio
     * Optional multipart `file` field stores evidence via MediaManager under
     * UPLOAD_PATH/students/portfolios/{student_id}/, linked back to the
     * portfolio_artifacts row via media_files.id.
     */
    public function postPortfolioArtifactAdd($id = null, $data = [], $segments = [])
    {
        try {
            $portfolioId = (int)($data['portfolio_id'] ?? 0);
            $title = trim($data['artifact_title'] ?? '');
            $type = $data['artifact_type'] ?? 'other';
            $description = trim($data['description'] ?? '');
            $competencyId = !empty($data['competency_id']) ? (int)$data['competency_id'] : null;
            $valueId = !empty($data['value_id']) ? (int)$data['value_id'] : null;
            $reflection = trim($data['learner_reflection'] ?? '');
            $feedback = trim($data['teacher_feedback'] ?? '');
            $rating = isset($data['rating']) && $data['rating'] !== '' ? (float)$data['rating'] : null;

            if (!$portfolioId || !$title) {
                return $this->badRequest('portfolio_id and artifact_title are required');
            }

            $portfolio = $this->db->query(
                "SELECT id, student_id FROM portfolios WHERE id = :pid",
                [':pid' => $portfolioId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$portfolio) {
                return $this->notFound('Portfolio not found');
            }

            $filePath = null;
            $mediaId = null;
            if (!empty($_FILES['file']) && is_array($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $media = new MediaManager(Database::getInstance()->getConnection());
                    $mediaId = $media->upload(
                        $_FILES['file'],
                        'students/portfolios',
                        (int)$portfolio['student_id'],
                        null,
                        $this->user['user_id'] ?? $this->user['id'] ?? null,
                        $title,
                        'portfolio artifact',
                        $title
                    );
                    $filePath = $media->getFileUrl($mediaId);
                } catch (\Throwable $uploadError) {
                    error_log('[AcademicController] Artifact upload failed: ' . $uploadError->getMessage());
                    return $this->badRequest('File could not be uploaded. Check the file type and size.');
                }
            }

            $this->db->query(
                "INSERT INTO portfolio_artifacts (portfolio_id, artifact_title, artifact_type, description, competency_id, value_id, learner_reflection, teacher_feedback, rating, file_path, media_id, upload_date, created_at)
                 VALUES (:pid, :title, :type, :desc, :cid, :vid, :ref, :fb, :rating, :fp, :mid, CURDATE(), NOW())",
                [':pid' => $portfolioId, ':title' => $title, ':type' => $type, ':desc' => $description,
                 ':cid' => $competencyId, ':vid' => $valueId, ':ref' => $reflection, ':fb' => $feedback,
                 ':rating' => $rating, ':fp' => $filePath, ':mid' => $mediaId]
            );

            $newId = $this->db->lastInsertId();
            $this->db->query("UPDATE portfolios SET last_updated = CURDATE(), updated_at = NOW() WHERE id = :pid", [':pid' => $portfolioId]);

            return $this->success(['id' => $newId], 'Artifact added');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * PUT /api/academic/portfolio/artifact-update — Update artifact metadata
     * (JSON body). File replacement is handled separately by
     * POST /api/academic/portfolio/artifact-file-replace so multipart uploads
     * keep using the POST path that PHP parses natively.
     */
    public function putPortfolioArtifactUpdate($id = null, $data = [], $segments = [])
    {
        try {
            $artifactId = (int)($data['id'] ?? $id ?? 0);
            if (!$artifactId) return $this->badRequest('artifact id is required');

            $sets = [];
            $params = [':id' => $artifactId];
            foreach (['artifact_title', 'artifact_type', 'description', 'learner_reflection', 'teacher_feedback'] as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $data[$f];
                }
            }
            foreach (['competency_id', 'value_id'] as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = !empty($data[$f]) ? (int)$data[$f] : null;
                }
            }
            if (array_key_exists('rating', $data)) {
                $sets[] = 'rating = :rating';
                $params[':rating'] = ($data['rating'] !== null && $data['rating'] !== '')
                    ? (float)$data['rating']
                    : null;
            }

            if (empty($sets)) return $this->badRequest('No fields to update');

            $sql = "UPDATE portfolio_artifacts SET " . implode(', ', $sets) . " WHERE id = :id";
            $this->db->query($sql, $params);

            return $this->success(null, 'Artifact updated');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/academic/portfolio/artifact-file-replace — Replace an artifact's
     * evidence file (multipart: id + file). Uploads the new file via MediaManager
     * under UPLOAD_PATH/students/portfolios/{student_id}/, updates the artifact's
     * file_path/media_id, then removes the previous media record and file.
     */
    public function postPortfolioArtifactFileReplace($id = null, $data = [], $segments = [])
    {
        try {
            $artifactId = (int)($data['id'] ?? $id ?? 0);
            if (!$artifactId) return $this->badRequest('artifact id is required');

            $art = $this->db->query(
                "SELECT pa.id, pa.portfolio_id, p.student_id, pa.media_id, pa.artifact_title FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id WHERE pa.id = :id",
                [':id' => $artifactId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$art) return $this->notFound('Artifact not found');

            if (empty($_FILES['file']) || !is_array($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return $this->badRequest('A replacement file is required');
            }

            $oldMediaId = !empty($art['media_id']) ? (int)$art['media_id'] : null;
            $preferredName = trim($data['artifact_title'] ?? '') !== ''
                ? $data['artifact_title']
                : ($art['artifact_title'] ?? 'artifact');

            try {
                $media = new MediaManager(Database::getInstance()->getConnection());
                $newMediaId = $media->upload(
                    $_FILES['file'],
                    'students/portfolios',
                    (int)$art['student_id'],
                    null,
                    $this->user['user_id'] ?? $this->user['id'] ?? null,
                    $preferredName,
                    'portfolio artifact',
                    $preferredName
                );
                $fileUrl = $media->getFileUrl($newMediaId);

                $this->db->query(
                    "UPDATE portfolio_artifacts SET file_path = :fp, media_id = :mid WHERE id = :id",
                    [':fp' => $fileUrl, ':mid' => $newMediaId, ':id' => $artifactId]
                );

                if ($oldMediaId) {
                    try {
                        $media->deleteMedia($oldMediaId);
                    } catch (\Throwable $cleanupError) {
                        error_log('[AcademicController] Old artifact media cleanup failed: ' . $cleanupError->getMessage());
                    }
                }

                return $this->success(['media_id' => $newMediaId, 'file_path' => $fileUrl], 'Artifact file replaced');
            } catch (\Throwable $uploadError) {
                error_log('[AcademicController] Artifact upload failed: ' . $uploadError->getMessage());
                return $this->badRequest('File could not be uploaded. Check the file type and size.');
            }
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * DELETE /api/academic/portfolio/artifact-delete/{id} — Delete artifact
     * Removes the DB row plus its linked media_files record and stored file.
     */
    public function deletePortfolioArtifactDelete($id = null, $data = [], $segments = [])
    {
        try {
            $artifactId = $id ? (int)$id : (int)($data['id'] ?? 0);
            if (!$artifactId) return $this->badRequest('artifact id is required');

            $art = $this->db->query(
                "SELECT portfolio_id, media_id FROM portfolio_artifacts WHERE id = :id",
                [':id' => $artifactId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$art) return $this->notFound('Artifact not found');

            $mediaId = !empty($art['media_id']) ? (int)$art['media_id'] : null;

            $this->db->query("DELETE FROM portfolio_artifacts WHERE id = :id", [':id' => $artifactId]);
            $this->db->query("UPDATE portfolios SET last_updated = CURDATE(), updated_at = NOW() WHERE id = :pid", [':pid' => $art['portfolio_id']]);

            if ($mediaId) {
                try {
                    (new MediaManager(Database::getInstance()->getConnection()))->deleteMedia($mediaId);
                } catch (\Throwable $deleteError) {
                    error_log('[AcademicController] Artifact media cleanup failed: ' . $deleteError->getMessage());
                }
            }

            return $this->success(null, 'Artifact deleted');
        } catch (\Exception $e) {
            error_log('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }
}
