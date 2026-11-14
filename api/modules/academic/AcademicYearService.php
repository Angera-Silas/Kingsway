<?php

namespace App\API\Modules\academic;

use App\API\Controllers\BaseController;

class AcademicYearService
{
    private AcademicAPI $api;

    public function __construct(AcademicAPI $api)
    {
        $this->api = $api;
    }

    public function postYearTransitionStartWorkflow($id, $data, $segments, BaseController $controller)
    {
        $payload = is_array($data) ? $data : [];
        $result = $this->api->startYearTransitionWorkflow($payload);
        return $controller->handleResponse($result);
    }

    
    
    
    
    public function getYearsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getYearsList($data);
        return $controller->handleResponse($result);
    }

    public function getYearsCurrent($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getCurrentAcademicYear();
        return $controller->handleResponse($result);
    }

    public function getYearsGet($id, $data, $segments, BaseController $controller) {
        if ($id === null) { return $controller->badRequest('Academic year ID is required'); }
        $result = $this->api->getAcademicYear($id);
        return $controller->handleResponse($result);
    }

    public function postYearsCreate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        $result = $this->api->createAcademicYear($data);
        return $controller->handleResponse($result);
    }

    public function putYearsUpdate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Academic year ID is required for update'); }
        $result = $this->api->updateAcademicYear($id, $data);
        return $controller->handleResponse($result);
    }

    public function deleteYearsDelete($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Academic year ID is required for deletion'); }
        $result = $this->api->deleteAcademicYear($id);
        return $controller->handleResponse($result);
    }

    
    public function getYearRolloverStatus($id, $data, $segments, BaseController $controller) {
        $yearId = $id ?? $data['year_id'] ?? null;
        if ($yearId === null) { return $controller->badRequest('Year ID is required'); }
        $result = $this->api->getYearRolloverStatus($yearId);
        return $controller->handleResponse($result);
    }

    public function postYearRollover($id, $data, $segments, BaseController $controller) {
        if (!$controller->userHasAny(['academic_year_manage', 'system_admin'], [1, 3], ['director', 'system admin'])) { return $controller->forbidden('Only Director or System Admin can execute year rollover'); }
        $result = $this->api->executeYearRollover($data);
        return $controller->handleResponse($result);
    }
}
