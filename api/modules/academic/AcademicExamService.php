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

    // TODO: Delegate to AcademicExamService
    public function postExamsCreateSchedule($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess()) { return $guard; }
        $result = $this->api->createExamSchedule($data['instance_id'] ?? null, $data['schedule_entries'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsSubmitQuestions($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->submitQuestionPaper($data['instance_id'] ?? null, $data['subject_id'] ?? null, $data['paper_data'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsPrepareLogistics($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess()) { return $guard; }
        $result = $this->api->prepareExamLogistics($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsConduct($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->conductExamination($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsAssignMarking($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess()) { return $guard; }
        $result = $this->api->assignExamMarking($data['instance_id'] ?? null, $data['assignments'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsRecordMarks($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->recordExamMarks($data['instance_id'] ?? null, $data['marks_data'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsVerifyMarks($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->verifyExamMarks($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsModerateMarks($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->moderateExamMarks($data['instance_id'] ?? null, $data['moderation_data'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsCompileResults($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->compileExamResults($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamsApproveResults($id, $data, $segments, BaseController $controller) {
        if (!$controller->userHasAny(['academic_approve', 'academic_manage'], [1, 3], ['director', 'principal'])) { return $controller->forbidden('You do not have permission to approve exam results'); }
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $approved = isset($data['action']) ? (strtolower($data['action']) === 'approve') : (bool) ($data['approved'] ?? true);
        $remarks = $data['comments'] ?? ($data['remarks'] ?? '');
        $result = $this->api->approveExamResults($instanceId, $approved, $remarks);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function getExamSchedule($id, $data, $segments, BaseController $controller) {
        if ($id !== null) { $result = $this->api->getExamScheduleById($id); }
        else { $result = $this->api->listExamSchedules($data); }
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postExamSchedule($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->createExamScheduleEntry($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function putExamSchedule($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Exam schedule ID is required for update'); }
        $result = $this->api->updateExamScheduleEntry($id, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function deleteExamSchedule($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage'])) { return $guard; }
        if ($id === null) { return $controller->badRequest('Exam schedule ID is required for deletion'); }
        $result = $this->api->deleteExamScheduleEntry($id);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postAssessmentsStartWorkflow($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->startAssessmentWorkflow($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postAssessmentsCreateItems($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->createAssessmentItems($data['instance_id'] ?? null, $data['items'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postAssessmentsAdminister($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->administerAssessment($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postAssessmentsMarkAndGrade($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) { return $guard; }
        $result = $this->api->markAndGradeAssessment($data['instance_id'] ?? null, $data['grading_data'] ?? [], $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function postAssessmentsAnalyzeResults($id, $data, $segments, BaseController $controller) {
        if ($guard = $controller->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) { return $guard; }
        $result = $this->api->analyzeAssessmentResults($data['instance_id'] ?? null, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function getAssessmentsList($id, $data, $segments, BaseController $controller) {
        $result = $this->api->listAssessments($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AcademicExamService
    public function getAssessmentTypes($id, $data, $segments, BaseController $controller) {
        $result = $this->api->getAssessmentTypes($data);
        return $controller->handleResponse($result);
    }
}
