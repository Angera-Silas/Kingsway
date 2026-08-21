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

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumMapOutcomes($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        $result = $this->api->mapCurriculumOutcomes($data['instance_id'] ?? null, $data['mappings'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumCreateScheme($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        $result = $this->api->createCurriculumScheme($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumReviewAndApprove($id, $data, $segments, BaseController $controller) {
        if (!$controller->userHasAny(['academic_approve', 'curriculum_approve', 'academic_manage'], [1, 3], ['director', 'principal'])) { return $controller->forbidden('You do not have permission to approve curriculum changes'); }
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $action = strtolower($data['action'] ?? ($data['decision'] ?? 'approve'));
        $review = array_merge($data, ['approved' => ($action === 'approve'), 'feedback' => $data['comments'] ?? ($data['feedback'] ?? [])]);
        $result = $this->api->reviewAndApproveCurriculum($instanceId, $review);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnitsCreate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        $result = $this->api->createCurriculumUnit($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnits($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        $result = $this->api->createCurriculumUnit($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listCurriculumUnits($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnits($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listCurriculumUnits($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsGet($id, $data, $segments, BaseController $controller) {
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required'); }
        $result = $this->api->getCurriculumUnit($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnitsUpdate($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for update'); }
        $result = $this->api->updateCurriculumUnit($id, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnits($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for update'); }
        $result = $this->api->updateCurriculumUnit($id, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnitsDelete($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for deletion'); }
        $result = $this->api->deleteCurriculumUnit($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnits($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Curriculum unit ID is required for deletion'); }
        $result = $this->api->deleteCurriculumUnit($id);
        return $controller->handleResponse($result);
    }
}
