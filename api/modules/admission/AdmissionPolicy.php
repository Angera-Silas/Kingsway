<?php
namespace App\API\Modules\admission;

class AdmissionPolicy
{
    private const INTERVIEW_GRADES = ['Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6'];

    public function normalizeGrade(string $grade): string
    {
        $normalized = strtolower(trim($grade));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);

        if ($normalized === 'ecd' || $normalized === 'playgroup' || $normalized === 'playground') {
            return 'Playground';
        }

        if ($normalized === 'pp1') {
            return 'PP1';
        }

        if ($normalized === 'pp2') {
            return 'PP2';
        }

        if (preg_match('/^grade([1-9])$/', $normalized, $matches)) {
            return 'Grade' . $matches[1];
        }

        return trim($grade);
    }

    public function requiresInterview(string $grade): bool
    {
        return in_array($this->normalizeGrade($grade), self::INTERVIEW_GRADES, true);
    }

    public function getRequiredDocuments(string $grade, string $category = 'standard', bool $isExistingParent = false): array
    {
        $normalized = $this->normalizeGrade($grade);
        $gradeNumber = (int) preg_replace('/\D/', '', $normalized);
        $isUpper = $gradeNumber >= 4 && $gradeNumber <= 9;

        $docs = [
            'birth_certificate' => ['mandatory' => true, 'label' => 'Birth Certificate'],
            'passport_photo' => ['mandatory' => true, 'label' => 'Passport Photo'],
        ];

        if ($isUpper) {
            $docs['previous_school_report'] = ['mandatory' => true, 'label' => 'Previous School Report'];
            $docs['medical_records'] = ['mandatory' => true, 'label' => 'Health / Medical Records'];
        } else {
            $docs['immunization_card'] = ['mandatory' => true, 'label' => 'Immunization Card'];
        }

        if (!$isExistingParent) {
            $docs['parent_id'] = ['mandatory' => true, 'label' => 'Parent/Guardian National ID'];
        }

        if ($this->requiresInterview($normalized)) {
            $docs['progress_report'] = ['mandatory' => false, 'label' => 'Latest Progress Report'];
            $docs['leaving_certificate'] = ['mandatory' => false, 'label' => 'Leaving Certificate from Previous School'];
        }

        return $docs;
    }

    public function resolveApplicationSource(array $data): string
    {
        $source = strtolower(trim((string) ($data['application_source'] ?? $data['application_channel'] ?? 'physical')));
        return in_array($source, ['online', 'physical'], true) ? $source : 'physical';
    }

    public function resolveAdmissionCategory(array $data): string
    {
        $category = strtolower(trim((string) ($data['admission_category'] ?? $data['intake_type'] ?? 'standard')));
        $category = str_replace(['-', ' '], '_', $category);

        $aliases = [
            'regular' => 'standard',
            'nursery_term1' => 'nursery_term_1',
            'nursery_term_1' => 'nursery_term_1',
            'nursery_term3' => 'nursery_term_3',
            'nursery_term_3' => 'nursery_term_3',
        ];

        return $aliases[$category] ?? 'standard';
    }

    public function resolveTargetTermId(array $data): ?int
    {
        $termId = $data['target_term_id'] ?? $data['intake_term_id'] ?? null;
        if ($termId !== null && $termId !== '') {
            return (int) $termId;
        }

        $category = $this->resolveAdmissionCategory($data);
        if ($category === 'nursery_term_1') {
            return 1;
        }
        if ($category === 'nursery_term_3') {
            return 3;
        }

        return null;
    }

    public function describeInterviewPolicy(string $grade): string
    {
        return $this->requiresInterview($grade)
            ? 'Grade 2-6 applicants require interview assessment.'
            : 'This grade proceeds to placement after document verification.';
    }

    public function getPolicyPayload(): array
    {
        return [
            'interview_required_grades' => self::INTERVIEW_GRADES,
            'application_sources' => ['online', 'physical'],
            'admission_categories' => ['standard', 'nursery_term_1', 'nursery_term_3'],
            'payment_rule' => [
                'minimum_to_enroll' => 0.01,
                'description' => 'Any positive admission payment allows enrollment.',
            ],
        ];
    }
}
