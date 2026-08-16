<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Modules\website\WebsiteManager;

/**
 * PublicController - Unauthenticated write endpoints for the public website
 * forms (job applications, contact inquiries, admission applications,
 * newsletter subscriptions). The pages never touch the DB or filesystem; they
 * POST here and the manager owns every write.
 *
 * Routes (all public by design):
 *   POST /api/public/job-applications
 *   POST /api/public/inquiries
 *   POST /api/public/applications
 *   POST /api/public/subscribers
 */
class PublicController extends BaseAPI
{
    private WebsiteManager $manager;

    public function __construct()
    {
        parent::__construct('public');
        $this->manager = new WebsiteManager();
    }

    public function postJobApplications($id = null, $data = [], $segments = [])
    {
        foreach (['apply_first_name', 'apply_last_name', 'apply_email', 'apply_phone'] as $field) {
            if (trim($data[$field] ?? '') === '') {
                return $this->errorResponse('Please fill in all required fields.', 422);
            }
        }
        if (!filter_var(trim($data['apply_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Please enter a valid email address.', 422);
        }
        $cvFile = $_FILES['apply_cv'] ?? [];
        if (empty($cvFile['name']) || ($cvFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->errorResponse('Please upload your CV (PDF/DOC).', 422);
        }
        return $this->manager->createJobApplication($data, $cvFile);
    }

    public function postInquiries($id = null, $data = [], $segments = [])
    {
        $name = trim($data['cf_name'] ?? '');
        $email = filter_var(trim($data['cf_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $message = trim($data['cf_message'] ?? '');
        if ($name === '' || !$email || $message === '') {
            return $this->errorResponse('Please fill in your name, email and message.', 422);
        }
        return $this->manager->createInquiry($data);
    }

    public function postApplications($id = null, $data = [], $segments = [])
    {
        $childName = trim($data['child_name'] ?? '');
        $parentName = trim($data['parent_name'] ?? '');
        $phone = trim($data['parent_phone'] ?? '');
        $grade = trim($data['grade_applying'] ?? '');
        $startTerm = trim($data['preferred_start'] ?? '');

        if ($childName === '' || $parentName === '' || $phone === '' || $grade === '') {
            return $this->errorResponse('Please fill in all required fields.', 422);
        }

        if ($startTerm !== '') {
            $validTokens = $this->manager->openTermTokens();
            if (!in_array($startTerm, $validTokens, true)) {
                return $this->errorResponse('Selected start term is not open for applications.', 422);
            }
        }

        $validGrades = ['PP1', 'PP2', 'Playgroup', 'Grade 1', 'Grade 2', 'Grade 3',
                        'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'];
        if (!in_array($grade, $validGrades, true)) {
            return $this->errorResponse('Selected grade is not open for applications.', 422);
        }

        $mappedFiles = [];
        $fileMap = [
            'birth_certificate'      => 'doc_birth_certificate',
            'passport_photo'         => 'doc_passport_photo',
            'parent_id'              => 'doc_parent_id',
            'previous_school_report' => 'doc_previous_school_report',
            'immunization_card'      => 'doc_immunization_card',
            'progress_report'        => 'doc_progress_report',
            'leaving_certificate'    => 'doc_leaving_certificate',
            'transfer_letter'        => 'doc_transfer_letter',
            'medical_records'        => 'doc_medical_records',
            'other'                  => 'doc_other',
        ];
        foreach ($fileMap as $docType => $field) {
            if (isset($_FILES[$field])) {
                $mappedFiles[$docType] = $_FILES[$field];
            }
        }

        // Single unified submission path for ALL channels: the admin panel and
        // the public website both land in StudentAdmissionWorkflow::submitApplication.
        $payload = [
            'applicant_name'       => $childName,
            'date_of_birth'        => trim($data['child_dob'] ?? ''),
            'gender'               => trim($data['child_gender'] ?? ''),
            'grade_applying_for'   => $grade,
            'previous_school'      => trim($data['child_prev_school'] ?? ''),
            'application_source'   => 'online',
            'parent_name'          => $parentName,
            'parent_national_id'   => trim($data['parent_id'] ?? ''),
            'parent_phone'         => $phone,
            'parent_email'         => filter_var(trim($data['parent_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
            'parent_address'       => trim($data['parent_address'] ?? ''),
            'special_needs'        => trim($data['special_needs'] ?? ''),
        ];

        $workflow = new \App\API\Modules\admission\StudentAdmissionWorkflow();
        $result = $workflow->submitApplication($payload, $mappedFiles);

        if (($result['code'] ?? 0) < 400) {
            $response = $this->successResponse(
                ['ref' => $result['data']['ref'] ?? $result['data']['application_no'] ?? '', 'application_no' => $result['data']['application_no'] ?? ''],
                $result['message'] ?? 'Application received!',
                $result['code'] ?? 201
            );
            // The public form page reads json.ref at top level, so mirror it there.
            $response['ref'] = $result['data']['ref'] ?? $result['data']['application_no'] ?? '';
            $response['application_no'] = $result['data']['application_no'] ?? $response['ref'];
            return $response;
        }
        return $this->errorResponse(
            $result['message'] ?? 'Submission failed. Please try again.',
            $result['code'] ?? 422
        );
    }

    public function postSubscribers($id = null, $data = [], $segments = [])
    {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return $this->errorResponse('Please enter a valid email address.', 422);
        }
        return $this->manager->createSubscriber($email, trim($data['name'] ?? ''));
    }
}
