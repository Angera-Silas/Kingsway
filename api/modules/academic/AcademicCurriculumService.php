<?php

namespace App\API\Modules\academic;

use App\API\Controllers\BaseController;

class AcademicCurriculumService
{
    private AcademicAPI $api;

    public function __construct(AcademicAPI $api)
    {
        $this->api = $api;
    }

    public function postCurriculumStartWorkflow($id, $data, $segments, BaseController $controller)
    {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) {
            return $guard;
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startCurriculumWorkflow($payload);
        return $controller->handleResponse($result);
    }

    
    
    
    public function postCurriculumUnitsCreate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        $result = $this->api->createCurriculumUnit($data);
        return $controller->handleResponse($result);
    }

    
    public function getCurriculumUnitsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listCurriculumUnits($data);
        return $controller->handleResponse($result);
    }

    
    public function getCurriculumUnitsGet($id, $data, $segments, BaseController $controller) {
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required'); }
        $result = $this->api->getCurriculumUnit($id);
        return $controller->handleResponse($result);
    }

    public function putCurriculumUnitsUpdate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for update'); }
        $result = $this->api->updateCurriculumUnit($id, $data);
        return $controller->handleResponse($result);
    }

    
    public function deleteCurriculumUnitsDelete($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for deletion'); }
        $result = $this->api->deleteCurriculumUnit($id);
        return $controller->handleResponse($result);
    }

    }
