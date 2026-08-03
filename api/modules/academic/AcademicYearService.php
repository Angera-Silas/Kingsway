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
        if (!$controller->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin']
        )) {
            return $controller->forbidden('Only Director or System Admin can start year transition workflows');
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startYearTransitionWorkflow($payload);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearTransitionArchiveData($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        $result = $this->api->archiveAcademicData($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearTransitionExecutePromotions($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'students_promote', 'system_admin'])) { return $guard; }
        $result = $this->api->executeYearPromotions($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearTransitionSetupNewYear($id, $data, $segments, BaseController $controller) {
        if (!$controller->userHasAny(['academic_year_manage', 'system_admin'], [1, 3], ['director', 'system admin'])) { return $controller->forbidden('Only Director or System Admin can setup new academic year'); }
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $yearConfig = $data['year_config'] ?? $data;
        $result = $this->api->setupNewAcademicYear($instanceId, $yearConfig);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearTransitionMigrateCompetencyBaselines($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        $result = $this->api->migrateCompetencyBaselines($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearTransitionValidateReadiness($id, $data, $segments, BaseController $controller) {
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function getYearsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listAcademicYears($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function getYearsCurrent($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getCurrentAcademicYear();
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function getYearsGet($id, $data, $segments, BaseController $controller) {
        if ($id === null) { return $controller->badRequest('Academic year ID is required'); }
        $result = $this->api->getAcademicYear($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearsCreate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        $result = $this->api->createAcademicYear($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function putYearsUpdate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Academic year ID is required for update'); }
        $result = $this->api->updateAcademicYear($id, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function deleteYearsDelete($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Academic year ID is required for deletion'); }
        $result = $this->api->deleteAcademicYear($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function putYearsSetCurrent($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Academic year ID is required'); }
        $result = $this->api->setCurrentAcademicYear($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function getYearRolloverStatus($id, $data, $segments, BaseController $controller) {
        $yearId = $id ?? $data['year_id'] ?? null;
        if ($yearId === null) { return $controller->badRequest('Year ID is required'); }
        $result = $this->api->getYearRolloverStatus($yearId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicYearService
    public function postYearRollover($id, $data, $segments, BaseController $controller) {
        if (!$controller->userHasAny(['academic_year_manage', 'system_admin'], [1, 3], ['director', 'system admin'])) { return $controller->forbidden('Only Director or System Admin can execute year rollover'); }
        $result = $this->api->executeYearRollover($data);
        return $controller->handleResponse($result);
    }
}
