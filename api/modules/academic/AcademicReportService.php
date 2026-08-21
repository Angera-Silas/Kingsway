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

    // TODO: Delegate to AcademicReportService
    public function postReportsCompileData($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->compileReportData($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicReportService
    public function postReportsGenerateStudentReports($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->generateStudentReports($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicReportService
    public function postReportsReviewAndApprove($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->reviewAndApproveReports($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicReportService
    public function postReportsDistribute($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->distributeReports($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicReportService
    public function getReportCardsDownload($id, $data, $segments, BaseController $controller) {
        $result = $this->api->downloadReportCards($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicReportService
    public function getReportCardData($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getReportCardData($data);
        return $controller->handleResponse($result);
    }
}
