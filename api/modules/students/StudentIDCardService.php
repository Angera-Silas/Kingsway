<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Controllers\BaseController;

class StudentIDCardService
{
    private const VIEW_PERMS = ['students_qr_view', 'students_qr_view_all', 'students_qr_view_own', 'students_view', 'students_view_all', 'students_view_own'];
    private const GENERATE_PERMS = ['students_qr_generate', 'students_qr_create', 'students_generate', 'students_print', 'students_edit', 'students_create'];
    private const EXPORT_PERMS = ['students_qr_download', 'students_qr_export', 'students_export', 'students_print'];

    private StudentsAPI $api;
    private StudentService $studentService;

    public function __construct(StudentsAPI $api, StudentService $studentService)
    {
        $this->api = $api;
        $this->studentService = $studentService;
    }

    public function postIdCardGenerate($id, $data, $segments, BaseController $controller)
    {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to generate ID cards')) {
            return $auth;
        }

        $studentId = $data['student_id'] ?? null;
        if (!$studentId) {
            return $controller->badRequest('Student ID is required');
        }

        $result = $this->studentService->generateIdCard((int) $studentId, $controller->getUserId());
        if (($result['success'] ?? false) && !empty($data['generate_qr'])) {
            $qrResult = $this->api->generateQRCodeEnhanced((int) $studentId);
            if (($qrResult['status'] ?? false) === true) {
                $result['qr_code_path'] = $qrResult['data']['qr_code_path'] ?? null;
            }
        }

        return $controller->handleResponse($result);
    }

    
    
    
    
    public function getIdCardMeta($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID card metadata')) { return $auth; }
        $result = $this->studentService->getIdCardMeta($controller->getUser());
        return $controller->success($result, 'ID card metadata loaded');
    }

    public function getIdCards($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID cards')) { return $auth; }
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $result = $this->studentService->getIdCards($controller->getUser(), $filters);
        return $controller->success($result, 'ID cards loaded');
    }

    public function getIdCardDetails($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID card details')) { return $auth; }
        $studentId = $id ?? $segments[0] ?? null;
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        $result = $this->studentService->getIdCardDetails((int) $studentId);
        if (!$result) { return $controller->notFound('Student ID card not found'); }
        return $controller->success($result, 'ID card details loaded');
    }

    
    
    
    
    
    
    
    
    
    
    
    
    }
