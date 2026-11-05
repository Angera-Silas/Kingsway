<?php
namespace App\API\Services;

use PDO;

/** Reads learner-type eligibility saved with the academic-year transition. */
final class AcademicYearLearnerTypeAvailabilityService
{
    private array $yearPolicies = [];
    private array $typeCodes = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function isAvailable(int $academicYearId, int $classId, int $studentTypeId): bool
    {
        if ($academicYearId <= 0 || $classId <= 0 || $studentTypeId <= 0) return true;

        $policy = $this->policyForYear($academicYearId);
        $classPolicies = $policy['class_learner_types'] ?? null;
        if (!is_array($classPolicies) || !array_key_exists((string) $classId, $classPolicies)) {
            // Academic years created before this setting remain unrestricted.
            return true;
        }

        $typeCode = $this->typeCode($studentTypeId);
        if ($typeCode === '') return false;
        $allowed = $classPolicies[(string) $classId];
        return is_array($allowed) && in_array($typeCode, $allowed, true);
    }

    public function assertAvailable(int $academicYearId, int $classId, int $studentTypeId): void
    {
        if ($this->isAvailable($academicYearId, $classId, $studentTypeId)) return;

        $typeCode = $this->typeCode($studentTypeId);
        $label = $typeCode === 'BOARD' ? 'Full Boarder' : ($typeCode === 'DAY' ? 'Day Student' : $typeCode);
        throw new \DomainException("{$label} placement is not offered for this class in the selected academic year.");
    }

    private function policyForYear(int $academicYearId): array
    {
        if (array_key_exists($academicYearId, $this->yearPolicies)) return $this->yearPolicies[$academicYearId];

        $definition = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = ? LIMIT 1");
        $definition->execute(['academic_year_transition']);
        $workflowId = (int) ($definition->fetchColumn() ?: 0);
        if (!$workflowId) return $this->yearPolicies[$academicYearId] = [];

        $instances = $this->db->prepare(
            "SELECT data_json FROM workflow_instances
             WHERE workflow_id = ? AND reference_type = ? AND reference_id = ?
             ORDER BY id DESC"
        );
        $instances->execute([$workflowId, 'academic_year_transition', $academicYearId]);
        foreach ($instances->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $data = is_string($json) ? json_decode($json, true) : null;
            if (is_array($data) && is_array($data['class_learner_types'] ?? null)) {
                return $this->yearPolicies[$academicYearId] = $data;
            }
        }
        return $this->yearPolicies[$academicYearId] = [];
    }

    private function typeCode(int $studentTypeId): string
    {
        if (array_key_exists($studentTypeId, $this->typeCodes)) return $this->typeCodes[$studentTypeId];
        $stmt = $this->db->prepare("SELECT code FROM student_types WHERE id = ? LIMIT 1");
        $stmt->execute([$studentTypeId]);
        return $this->typeCodes[$studentTypeId] = strtoupper(trim((string) ($stmt->fetchColumn() ?: '')));
    }
}
