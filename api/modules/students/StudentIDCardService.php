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

    // TODO: Delegate to StudentIDCardService
    public function postIdCardGenerateLegacy($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to generate student ID cards')) { return $auth; }
        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        return $controller->handleResponse($this->api->generateStudentIDCard((int) $studentId));
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardGenerateClass($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to generate class ID cards')) { return $auth; }
        $classId = $id ?? $data['class_id'] ?? null;
        if (!$classId) { return $controller->badRequest('Class ID is required'); }
        $streamId = $data['stream_id'] ?? null;
        return $controller->handleResponse($this->api->generateClassIDCards((int) $classId, $streamId ? (int) $streamId : null));
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardGet($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view student ID card details')) { return $auth; }
        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        return $controller->handleResponse($this->api->getIdCardPayload((int) $studentId));
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardStatisticsGet($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(array_merge(self::VIEW_PERMS, self::EXPORT_PERMS), 'Insufficient permission to view student ID card statistics')) { return $auth; }
        return $controller->handleResponse($this->api->getIdCardStatistics($data));
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardMeta($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID card metadata')) { return $auth; }
        $result = $this->studentService->getIdCardMeta($controller->getUser());
        return $controller->success($result, 'ID card metadata loaded');
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCards($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID cards')) { return $auth; }
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $result = $this->studentService->getIdCards($controller->getUser(), $filters);
        return $controller->success($result, 'ID cards loaded');
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardDetails($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID card details')) { return $auth; }
        $studentId = $id ?? $segments[0] ?? null;
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        $result = $this->studentService->getIdCardDetails((int) $studentId);
        if (!$result) { return $controller->notFound('Student ID card not found'); }
        return $controller->success($result, 'ID card details loaded');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardMarkPrinted($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to mark ID card as printed')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->markCardPrinted((int) $cardId, $controller->getUserId());
        return $controller->success($result, 'Card marked as printed');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardMarkLost($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to mark ID card as lost')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->markCardLost((int) $cardId, $controller->getUserId());
        return $controller->success($result, 'Card marked as lost');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardsPrint($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to print student ID cards')) { return $auth; }
        $studentIds = $data['student_ids'] ?? [];
        if (!is_array($studentIds) || $studentIds === []) { return $controller->badRequest('Select at least one student before printing.'); }
        $printerMode = $data['printer_mode'] ?? 'a4_pdf';
        $side = strtolower((string) ($data['side'] ?? 'both'));
        if (!in_array($side, ['front', 'back', 'both'], true)) { return $controller->badRequest('Card side must be front, back or both.'); }
        $result = $this->api->generateBulkIDCardsPDF($studentIds, $printerMode, $side !== 'back', $side !== 'front');
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardGenerateBulk($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to bulk generate ID cards')) { return $auth; }
        $studentIds = $data['student_ids'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) { return $controller->badRequest('Student IDs array is required'); }
        $result = $this->studentService->generateIdCardsBulk($studentIds, $controller->getUserId());
        if (!empty($data['generate_qr'])) {
            $result['qr_generated'] = 0; $result['qr_errors'] = [];
            foreach ($studentIds as $studentId) {
                $qrResult = $this->api->generateQRCodeEnhanced((int) $studentId);
                if (($qrResult['status'] ?? false) === true || ($qrResult['success'] ?? false) === true) { $result['qr_generated']++; }
                else { $result['qr_errors'][(int) $studentId] = $qrResult['message'] ?? 'Failed to generate student QR code'; }
            }
        }
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardGenerateBulkPdf($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to generate bulk ID card PDFs')) { return $auth; }
        $studentIds = $data['student_ids'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) { return $controller->badRequest('Student IDs array is required'); }
        $result = $this->api->generateBulkIDCardsPDF($studentIds, $data['print_mode'] ?? 'a4_sheet', $data['include_front'] ?? true, $data['include_back'] ?? true);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardPrintSingle($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to print ID cards')) { return $auth; }
        $studentId = $data['student_id'] ?? ($segments[0] ?? null);
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        $result = $this->api->generatePrintableSingle((int) $studentId, $data['side'] ?? 'both', $data['print_mode'] ?? 'direct_card', 'pdf');
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardGenerateQr($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to generate QR codes')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->generateCardQrCode((int) $cardId, $controller->getUserId());
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardMarkIssued($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to mark cards as issued')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->markCardIssued((int) $cardId, $controller->getUserId());
        return $controller->success($result, 'Card marked as issued');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardRenew($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to renew ID cards')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->renewCard((int) $cardId, $controller->getUserId());
        return $controller->success($result, 'Card renewed');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardReplace($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to replace ID cards')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->replaceCard((int) $cardId, $data['reason'] ?? 'other', $controller->getUserId());
        return $controller->success($result, 'Card replaced');
    }

    // TODO: Delegate to StudentIDCardService
    public function postIdCardRevoke($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::GENERATE_PERMS, 'Insufficient permission to revoke ID cards')) { return $auth; }
        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) { return $controller->badRequest('Card ID is required'); }
        $result = $this->studentService->revokeCard((int) $cardId, $data['reason'] ?? null, $controller->getUserId());
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardHistory($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view ID card history')) { return $auth; }
        $studentId = $id ?? $segments[0] ?? null;
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        $result = $this->studentService->getCardHistory((int) $studentId);
        return $controller->success($result, 'Card history loaded');
    }

    // TODO: Delegate to StudentIDCardService
    public function getIdCardVerify($id, $data, $segments, BaseController $controller) {
        $cardNumber = $id ?? $segments[0] ?? null;
        if (!$cardNumber) { return $controller->badRequest('Card number is required'); }
        $result = $this->studentService->verifyCard($cardNumber);
        if (!$result) { return $controller->notFound('Card not found or invalid'); }
        return $controller->success($result, 'Card verified');
    }
}
