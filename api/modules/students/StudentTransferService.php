<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Controllers\BaseController;

class StudentTransferService
{
    private const TRANSFER_PERMS = ['students_transfers_create', 'students_transfers_edit', 'students_transfers_submit', 'students_transfers_approve', 'students_transfers_view', 'students_edit'];
    private const VIEW_PERMS = ['students_view', 'students_view_all', 'students_view_own', 'students_edit', 'students_create', 'students_delete', 'students_fees_view', 'students_parents_view', 'finance_view'];

    private StudentsAPI $api;

    public function __construct(StudentsAPI $api)
    {
        $this->api = $api;
    }

    public function postTransferStartWorkflow($id, $data, $segments, BaseController $controller)
    {
        if ($auth = $controller->authorizeStudents(self::TRANSFER_PERMS, 'Insufficient permission to initiate student transfers')) {
            return $auth;
        }

        $result = $this->api->startTransferWorkflow($data);
        return $controller->handleResponse($result);
    }

    
    
    
    public function getTransferWorkflowStatus($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view transfer status')) { return $auth; }
        $instanceId = $data['instance_id'] ?? $id ?? null;
        if ($instanceId === null) { return $controller->badRequest('Instance ID is required'); }
        $result = $this->api->getTransferWorkflowStatus($instanceId);
        return $controller->handleResponse($result);
    }

    public function getTransferHistory($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::VIEW_PERMS, 'Insufficient permission to view transfer history')) { return $auth; }
        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) { return $controller->badRequest('Student ID is required'); }
        $result = $this->api->getTransferHistory($studentId);
        return $controller->handleResponse($result);
    }
}
