<?php
namespace App\API\Controllers;

use App\API\Modules\boarding\BoardingManager;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use App\API\Services\Logger;
use DomainException;
use Exception;

/**
 * BoardingController
 * Handles boarding/hostel management endpoints.
 *
 * GET    /api/boarding                    → getStats()
 * GET    /api/boarding/stats              → getStats()
 * GET    /api/boarding/occupancy          → getOccupancy()
 * GET    /api/boarding/dormitories        → getDormitories()
 * POST   /api/boarding/dormitories        → postDormitories()
 * PUT    /api/boarding/dormitories/{id}   → putDormitories()
 * DELETE /api/boarding/dormitories/{id}   → deleteDormitories()
 * GET    /api/boarding/students           → getStudents()
 * GET    /api/boarding/roll-call          → getRollCall()
 * POST   /api/boarding/roll-call          → postRollCall()
 * GET    /api/boarding/exeats             → getExeats()
 * POST   /api/boarding/exeats             → postExeats()
 * PUT    /api/boarding/exeats/{id}        → putExeats()  (approve/reject)
 * GET    /api/boarding/activity           → getActivity()
 */
class BoardingController extends BaseController
{
    private BoardingManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = $this->contract('App\API\Modules\boarding\BoardingManager');
    }

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats();
    }
    public function getStats($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        return $this->handleApiResponse($this->manager->getStats());
    }
    public function getOccupancy($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        return $this->handleApiResponse($this->manager->getOccupancy());
    }

    /** POST /api/boarding/ai-exception-summary-queue */
    public function postAiExceptionSummaryQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiBoarding()) return $this->forbidden('Boarding AI assistance is not available for your account');
        try {
            $dateFrom = (string) ($data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days')));
            $dateTo = (string) ($data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d'));
            if (!$this->isValidAiDateRange($dateFrom, $dateTo)) {
                return $this->badRequest('Choose a valid date range of no more than 367 calendar days.');
            }
            $inputResponse = $this->manager->getAiExceptionSummaryInput($dateFrom, $dateTo);
            if (($inputResponse['success'] ?? false) !== true) return $this->handleApiResponse($inputResponse);
            $input = is_array($inputResponse['data'] ?? null) ? $inputResponse['data'] : [];
            $queued = $this->contract(AiDraftService::class)->queue(
                'boarding.exception_summary', $this->aiBoardingContext(), $input,
                ['subject_type' => 'boarding_exception_summary', 'scope' => 'authorized_boarding_operations']
            );
            return $this->accepted($queued, 'Boarding exception summary queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (\Throwable $e) {
            Logger::error('errors', 'Boarding AI exception summary could not be queued', [
                'exception_class' => get_class($e),
                'workflow_id' => 'boarding.exception_summary',
                'request_id' => (string) ($_SERVER['REQUEST_ID'] ?? $this->requestId),
            ]);
            return $this->serverError('Boarding assistance is temporarily unavailable');
        }
    }

    public function getAiExceptionSummaries($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiBoarding()) return $this->forbidden('Boarding review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'boarding'), 'scope' => $review ? 'review' : 'own'], 'Boarding AI summaries retrieved');
    }

    public function postAiExceptionSummaryApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiBoarding()) return $this->forbidden('Boarding review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'boarding.exception_summary');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Boarding summary approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiBoardingContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique((array) ($this->user['effective_permissions'] ?? $this->user['permissions'] ?? []))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }
    private function canUseAiBoarding(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('boarding.exception_summary', $this->aiBoardingContext()); return true; } catch (DomainException $e) { return false; }
    }
    private function canApproveAiBoarding(): bool
    {
        return $this->userHasAny(['attendance_boarding_approve', 'attendance_boarding_edit'], [3, 4, 5, 10], ['headteacher', 'school administrator', 'director', 'boarding master']);
    }

    public function getDormitories($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        return $this->handleApiResponse($this->manager->listDormitories());
    }

    public function postDormitories($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_edit')) return $denied;
        return $this->handleApiResponse($this->manager->createDormitory($data));
    }

    public function putDormitories($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_edit')) return $denied;
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->updateDormitory($dormId, $data));
    }

    public function deleteDormitories($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_edit')) return $denied;
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->deleteDormitory($dormId));
    }

    public function getStudents($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        $dormId = $_GET['dormitory_id'] ?? $data['dormitory_id'] ?? null;
        $search = $_GET['search'] ?? $data['search'] ?? '';
        $academicYearId = $_GET['academic_year_id'] ?? $data['academic_year_id'] ?? null;
        return $this->handleApiResponse($this->manager->getStudents($dormId, $search, $academicYearId));
    }
    public function getRollCall($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        $date = $_GET['date'] ?? $data['date'] ?? date('Y-m-d');
        $dateFrom = $_GET['date_from'] ?? $data['date_from'] ?? $date;
        $dateTo = $_GET['date_to'] ?? $data['date_to'] ?? $date;
        if (!$this->isValidAiDateRange((string) $dateFrom, (string) $dateTo)) return $this->badRequest('Choose a valid date range of no more than 367 calendar days.');
        return $this->handleApiResponse($this->manager->getRollCall($date, $dateFrom, $dateTo));
    }

    private function isValidAiDateRange(string $from, string $to): bool
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$start || !$end || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return false;
        if ($start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $end < $start) return false;
        return $end->diff($start)->days <= 366;
    }

    public function postRollCall($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_edit')) return $denied;
        $markedBy = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleApiResponse($this->manager->markRollCall($data, $markedBy));
    }

    public function getExeats($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        $status = $_GET['status'] ?? $data['status'] ?? '';
        return $this->handleApiResponse($this->manager->getExeats(
            $status,
            $_GET['date_from'] ?? $data['date_from'] ?? null,
            $_GET['date_to'] ?? $data['date_to'] ?? null
        ));
    }

    public function postExeats($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_create', ['system administrator', 'school administrator', 'headteacher', 'boarding master'])) return $denied;
        return $this->handleApiResponse($this->manager->createExeat($data));
    }

    public function putExeats($id = null, $data = [], $segments = [])
    {
        $exeatId = (int) ($id ?? $data['id'] ?? 0);
        $action = $data['action'] ?? $segments[0] ?? 'approve';
        $approval = in_array(strtolower((string) $action), ['approve', 'reject'], true);
        $permission = $approval ? 'attendance_boarding_approve' : 'attendance_boarding_edit';
        $roles = $approval
            ? ['system administrator', 'school administrator', 'headteacher', 'director']
            : ['system administrator', 'school administrator', 'headteacher', 'boarding master'];
        if ($denied = $this->guardBoarding($permission, $roles)) return $denied;
        return $this->handleApiResponse($this->manager->updateExeat($exeatId, $action));
    }

    public function getActivity($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardBoarding('attendance_boarding_view')) return $denied;
        return $this->handleApiResponse($this->manager->getActivity());
    }

    /** Apply the same permission and named-role fallback used by attendance APIs. */
    private function guardBoarding(string $permission, array $roles = ['system administrator', 'school administrator', 'headteacher', 'director', 'boarding master'])
    {
        if ($this->userHasAny([$permission], [], $roles)) return null;
        return $this->forbidden('You are not permitted to access this boarding operation.');
    }
}
