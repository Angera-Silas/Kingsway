<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Controllers\BaseController;

class StudentPromotionService
{
    private const PROMOTE_PERMS = ['students_generate', 'students_edit'];
    private const VIEW_PERMS = ['students_view', 'students_view_all', 'students_view_own', 'students_edit', 'students_create', 'students_delete', 'students_fees_view', 'students_parents_view', 'finance_view'];

    private StudentsAPI $api;
    private PromotionManager $promotionManager;

    public function __construct(StudentsAPI $api, PromotionManager $promotionManager)
    {
        $this->api = $api;
        $this->promotionManager = $promotionManager;
    }

    public function postPromotionSingle($id, $data, $segments, BaseController $controller)
    {
        if ($auth = $controller->authorizeStudents(self::PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->promoteSingleStudent($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function postPromotionMultiple($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PROMOTE_PERMS, 'Insufficient permission to promote students')) { return $auth; }
        $result = $this->api->promoteMultipleStudents($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function postPromotionEntireClass($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PROMOTE_PERMS, 'Insufficient permission to promote students')) { return $auth; }
        $result = $this->api->promoteEntireClass($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function postPromotionMultipleClasses($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PROMOTE_PERMS, 'Insufficient permission to promote students')) { return $auth; }
        $result = $this->api->promoteMultipleClasses($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function postPromotionGraduateGrade9($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PROMOTE_PERMS, 'Insufficient permission to graduate students')) { return $auth; }
        $result = $this->api->graduateGrade9Students($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function getPromotionBatches($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view promotion batches')) { return $auth; }
        $result = $this->api->getPromotionBatches($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function getPromotionHistory($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view promotion history')) { return $auth; }
        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) { return $controller->badRequest('Student ID is required'); }
        $result = $this->api->getPromotionHistory($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentPromotionService
    public function getPromotionMetaV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        return $controller->success($this->promotionManager->getPromotionMeta());
    }

    // TODO: Delegate to StudentPromotionService
    public function getPromotionCandidatesV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        try {
            return $controller->success($this->promotionManager->getPromotionCandidates(array_merge($_GET, $data)));
        } catch (\Exception $e) {
            error_log('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->badRequest('An internal error occurred.');
        }
    }

    // TODO: Delegate to StudentPromotionService
    public function postPromotionExecuteV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        try {
            $userId = (int)($controller->getUser()['id'] ?? $controller->getUser()['user_id'] ?? 0);
            if ($userId <= 0) { return $controller->unauthorized('Authenticated user ID could not be resolved'); }
            return $controller->success($this->promotionManager->executePromotionV2($data, $userId));
        } catch (\Exception $e) {
            error_log('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->badRequest('An internal error occurred.');
        }
    }
}
