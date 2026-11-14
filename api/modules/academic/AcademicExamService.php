<?php

namespace App\API\Modules\academic;

use App\API\Controllers\BaseController;

class AcademicExamService
{
    private AcademicAPI $api;

    public function __construct(AcademicAPI $api)
    {
        $this->api = $api;
    }

    public function postExamsStartWorkflow($id, $data, $segments, BaseController $controller)
    {
        if ($guard = $controller->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->startExaminationWorkflow($data);
        return $controller->handleResponse($result);
    }

    
    
    
    
    
    
    
    
    
    
    public function getExamSchedule($id, $data, $segments, BaseController $controller) {
        if ($id !== null) { $result = $this->api->getExamScheduleById($id); }
        else { $result = $this->api->listExamSchedules($data); }
        return $controller->handleResponse($result);
    }

    
    
    
    
    
    
    
    
    public function getAssessmentsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listAssessments($data);
        return $controller->handleResponse($result);
    }

    public function getAssessmentTypes($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getAssessmentTypes($data);
        return $controller->handleResponse($result);
    }
}
