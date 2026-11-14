<?php

namespace App\API\Modules\academic;

use App\API\Controllers\BaseController;

class AcademicReportService
{
    private AcademicAPI $api;

    public function __construct(AcademicAPI $api)
    {
        $this->api = $api;
    }

    public function postReportsStartWorkflow($id, $data, $segments, BaseController $controller)
    {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->startReportWorkflow($data);
        return $controller->handleResponse($result);
    }

    
    
    
    
    
    public function getReportCardData($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getReportCardData($data);
        return $controller->handleResponse($result);
    }
}
