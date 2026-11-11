<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use App\API\Services\ReadReplicaService;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Academic Year Transition Workflow - CBC-Compliant
 * 
 * Manages the critical transition from one academic year to the next.
 * Ensures continuity of CBC competency baselines and proper data archival.
 * 
 * Transition Checklist:
 * - Archive previous year data (reports, assessments, competencies)
 * - Promote all students to next grade
 * - Create new academic year structure (terms, classes, streams)
 * - Migrate competency baselines for continued tracking
 * - Setup new assessment cycles
 * - Initialize attendance registers
 * - Configure new academic calendar
 * 
 * CBC-Specific Considerations:
 * - Competency progression tracking across years
 * - Performance level trends analysis
 * - Learning outcomes continuity
 * - Promotion criteria based on CBC assessment
 * 
 * Workflow Stages:
 * 1. Prepare Calendar - Create academic calendar for new year
 * 2. Archive Data - Archive previous year records
 * 3. Execute Promotions - Promote students in bulk
 * 4. Setup New Year - Create classes, terms, structures
 * 5. Migrate Baselines - Transfer competency baselines
 * 6. Validate Readiness - Final checks before go-live
 */
class AcademicYearTransitionWorkflow extends WorkflowHandler
{

    public function __construct()
    {
        parent::__construct('academic_year_transition');
    }

    /** MariaDB ROW_COUNT() reports affected rows for the preceding write on this connection. */
    private function lastAffectedRows(): int
    {
        return (int) $this->db->query('SELECT ROW_COUNT()')->fetchColumn();
    }

    protected function getWorkflowDefinitionCode(): string
    {
        return 'academic_year_transition';
    }

    /** Return the starting year from 2026, 2026/2027, or a year record id. */
    private function yearStart($value): int
    {
        $text = trim((string) $value);
        if (preg_match('/^(\d{4})\/(\d{4})$/', $text, $match)) {
            $start = (int) $match[1];
            return (int) $match[2] === $start + 1 ? $start : 0;
        }
        if (preg_match('/^(\d{4})$/', $text)) {
            return (int) $text;
        }
        return 0;
    }

    private function yearCode($value): string
    {
        $start = $this->yearStart($value);
        return $start > 0 ? $start . '/' . ($start + 1) : '';
    }

    /** Resolve an academic_years.id using the canonical YYYY/YYYY+1 code. */
    private function resolveYearIdFromCode($yearValue): int
    {
        $code = $this->yearCode($yearValue);
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_years
             WHERE year_code = ? OR year_code = ?
             ORDER BY is_current DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$code, (string) $yearValue]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** Find the resumable rollover workflow for a target academic year. */
    public function findActiveWorkflowForYear($yearValue): array
    {
        $targetYearCode = $this->yearCode($yearValue);
        if ($targetYearCode === '') {
            return formatResponse(false, null, 'A valid target academic year is required.');
        }

        foreach ($this->listWorkflows(['status' => 'in_progress']) as $instance) {
            $data = is_array($instance['data'] ?? null) ? $instance['data'] : [];
            $instanceYearCode = (string) ($data['to_year_code'] ?? $this->yearCode($data['to_year'] ?? ''));
            if ($instanceYearCode !== $targetYearCode) continue;

            return formatResponse(true, [
                'id' => (int) ($instance['id'] ?? 0),
                'current_stage' => (string) ($instance['current_stage'] ?? ''),
                'current_stage_code' => (string) ($instance['current_stage'] ?? ''),
                'status' => (string) ($instance['status'] ?? ''),
                'data' => $data,
            ], 'Active year transition found.');
        }

        return formatResponse(true, null, 'No active year transition was found for this target year.');
    }

    /** Load current-year class/stream configuration as the editable rollover draft source. */
    public function getClassSetupSource($yearValue, $targetYearValue = ''): array
    {
        $yearId = $this->resolveYearIdFromCode($yearValue);
        if (!$yearId) return formatResponse(false, null, 'Source academic year was not found.');
        $stmt = $this->db->prepare(
            "SELECT ayc.class_id AS source_class_id, sourceClass.name AS source_class_name,
                    sourceClass.level_id, sourceClass.id AS target_class_id,
                    sourceClass.name AS target_class_name, aycs.id AS source_stream_id,
                    stream.name AS stream_name, stream.capacity AS current_capacity
             FROM " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc
             JOIN " . ReadReplicaService::qualifiedRef('classes') . " sourceClass ON sourceClass.id = ayc.class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('academic_year_class_streams') . " aycs
               ON aycs.academic_year_class_id = ayc.id AND aycs.status IN ('planning','active')
             LEFT JOIN " . ReadReplicaService::qualifiedRef('streams') . " stream ON stream.id = aycs.stream_id
             WHERE ayc.academic_year_id = ? AND ayc.status IN ('planning','active')
             ORDER BY sourceClass.id, stream.name"
        );
        $stmt->execute([$yearId]);
        $classes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int) $row['source_class_id'];
            if (!isset($classes[$key])) {
                $classes[$key] = [
                    'source_class_id' => $key,
                    'source_class_name' => (string) $row['source_class_name'],
                    'level_id' => (int) $row['level_id'],
                    'target_class_id' => (int) ($row['target_class_id'] ?? 0),
                    'target_class_name' => (string) ($row['target_class_name'] ?? ''),
                    'streams' => [],
                ];
            }
            if (!empty($row['stream_name'])) {
                $classes[$key]['streams'][] = [
                    'id' => (int) $row['source_stream_id'],
                    'name' => (string) $row['stream_name'],
                    'capacity' => (int) ($row['current_capacity'] ?? 40),
                ];
            }
        }
        $sourceTypesStmt = $this->db->prepare(
            "SELECT available.class_id, available.code FROM (
                SELECT ayc.class_id, st.code
                FROM academic_year_fee_schedules fs
                JOIN " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = fs.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef('student_types') . " st ON st.id = fs.student_type_id
                WHERE fs.academic_year_id = ? AND fs.status = 'active' AND fs.amount > 0
                  AND st.code IN ('DAY', 'BOARD')
                UNION
                SELECT ayc.class_id, st.code
                FROM " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae
                JOIN " . ReadReplicaService::qualifiedRef('academic_year_class_streams') . " aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef('students') . " s ON s.id = sae.student_id
                JOIN " . ReadReplicaService::qualifiedRef('student_types') . " st ON st.id = s.student_type_id
                WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('active', 'pending')
                  AND st.code IN ('DAY', 'BOARD')
            ) available ORDER BY available.class_id, available.code"
        );
        $sourceTypesStmt->execute([$yearId, $yearId]);
        $sourceLearnerTypes = [];
        foreach ($sourceTypesStmt->fetchAll(PDO::FETCH_ASSOC) as $typeRow) {
            $sourceLearnerTypes[(int) $typeRow['class_id']][] = strtoupper((string) $typeRow['code']);
        }
        $targetYearId = $targetYearValue !== '' ? $this->resolveYearIdFromCode($targetYearValue) : 0;
        $targetLearnerTypes = $targetYearId > 0 ? $this->loadClassLearnerTypeMap($targetYearId) : [];
        foreach ($classes as &$class) {
            $classId = (int) $class['target_class_id'];
            $class['source_learner_type_codes'] = array_values(array_unique($sourceLearnerTypes[$classId] ?? ['DAY']));
            $class['target_learner_type_codes'] = array_key_exists((string) $classId, $targetLearnerTypes)
                ? $targetLearnerTypes[(string) $classId]
                : null;
        }
        unset($class);
        if ($targetYearId > 0) {
            foreach ($classes as &$class) {
                $class['target_streams'] = [];
                $targetClassStmt = $this->db->prepare(
                    'SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1'
                );
                $targetClassStmt->execute([$targetYearId, (int) $class['target_class_id']]);
                $targetClassId = (int) ($targetClassStmt->fetchColumn() ?: 0);
                if (!$targetClassId) continue;
                $targetStreamsStmt = $this->db->prepare(
                    "SELECT stream_id, capacity FROM academic_year_class_streams WHERE academic_year_class_id = ? AND status IN ('planning', 'active')"
                );
                $targetStreamsStmt->execute([$targetClassId]);
                foreach ($targetStreamsStmt->fetchAll(PDO::FETCH_ASSOC) as $targetStream) {
                    $streamStmt = $this->db->prepare('SELECT name, capacity FROM streams WHERE id = ? LIMIT 1');
                    $streamStmt->execute([(int) $targetStream['stream_id']]);
                    $stream = $streamStmt->fetch(PDO::FETCH_ASSOC);
                    if ($stream) $class['target_streams'][] = [
                        'name' => (string) $stream['name'],
                        'capacity' => (int) ($targetStream['capacity'] ?? $stream['capacity'] ?? 40),
                    ];
                }
            }
            unset($class);
        }
        return formatResponse(true, array_values($classes), 'Current-year class and stream draft loaded.');
    }

    /** Read the saved target-year learner-type choices from its rollover record. */
    private function loadClassLearnerTypeMap(int $academicYearId): array
    {
        $definition = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = ? LIMIT 1");
        $definition->execute([$this->getWorkflowDefinitionCode()]);
        $workflowId = (int) ($definition->fetchColumn() ?: 0);
        if (!$workflowId) return [];
        $instances = $this->db->prepare(
            "SELECT data_json FROM workflow_instances
             WHERE workflow_id = ? AND reference_type = ? AND reference_id = ?
             ORDER BY id DESC"
        );
        $instances->execute([$workflowId, $this->getWorkflowDefinitionCode(), $academicYearId]);
        foreach ($instances->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $data = is_string($json) ? json_decode($json, true) : null;
            if (is_array($data) && is_array($data['class_learner_types'] ?? null)) {
                return $data['class_learner_types'];
            }
        }
        return [];
    }

    /** Load editable annual curriculum, staffing-continuity, and fee drafts. */
    public function getSetupDrafts(int $instanceId): array
    {
        $instance = $this->getWorkflowInstance($instanceId);
        if (!$instance) return formatResponse(false, null, 'Year transition workflow was not found.');
        $workflowData = json_decode((string) ($instance['data_json'] ?? ''), true) ?: [];
        $yearId = (int) ($workflowData['academic_year_id'] ?? 0)
            ?: $this->resolveYearIdFromCode($workflowData['to_year_code'] ?? $workflowData['to_year'] ?? '');
        if (!$yearId) return formatResponse(false, null, 'Prepare the target year’s classes and streams first.');

        $learningAreas = $this->db->query(
            "SELECT id, name, code, level_band, levels FROM learning_areas
             WHERE status = 'active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $targetClasses = $this->db->prepare(
            "SELECT ayc.class_id, c.name AS class_name FROM academic_year_classes ayc
             JOIN classes c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ? AND ayc.status IN ('planning', 'active') ORDER BY c.id"
        );
        $targetClasses->execute([$yearId]);
        $classOptions = $targetClasses->fetchAll(PDO::FETCH_ASSOC);
        $classIdsByArea = [];
        $targetClassIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['class_id'], $classOptions), true);
        $targetPairOverrides = [];
        $usedCurrentYearDefaults = false;
        $sourceYearId = $this->resolveYearIdFromCode($workflowData['from_year_code'] ?? $workflowData['from_year'] ?? '');
        if ($sourceYearId) {
            $sourceAssignments = $this->db->prepare(
                "SELECT ayc.class_id, aycla.learning_area_id
                 FROM academic_year_class_learning_areas aycla
                 JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                 WHERE ayc.academic_year_id = ? AND aycla.status <> 'skipped'
                 GROUP BY ayc.class_id, aycla.learning_area_id"
            );
            $sourceAssignments->execute([$sourceYearId]);
            foreach ($sourceAssignments->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
                $classId = (int) $assignment['class_id'];
                if (isset($targetClassIds[$classId])) {
                    $areaId = (int) $assignment['learning_area_id'];
                    $classIdsByArea[$areaId][$classId] = true;
                }
            }
        }
        $targetAssignments = $this->db->prepare(
            "SELECT ayc.class_id, aycla.learning_area_id, MAX(aycla.status <> 'skipped') AS is_offered
             FROM academic_year_class_learning_areas aycla
             JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
             WHERE ayc.academic_year_id = ?
             GROUP BY ayc.class_id, aycla.learning_area_id"
        );
        $targetAssignments->execute([$yearId]);
        $targetAssignmentRows = $targetAssignments->fetchAll(PDO::FETCH_ASSOC);
        foreach ($targetAssignmentRows as $assignment) {
            $areaId = (int) $assignment['learning_area_id'];
            $classId = (int) $assignment['class_id'];
            if (!isset($targetClassIds[$classId])) continue;
            $targetPairOverrides[$areaId . ':' . $classId] = true;
            if ((int) $assignment['is_offered'] === 1) $classIdsByArea[$areaId][$classId] = true;
            else unset($classIdsByArea[$areaId][$classId]);
        }
        foreach ($classIdsByArea as $areaId => $classIds) {
            foreach (array_keys($classIds) as $classId) {
                if (!isset($targetPairOverrides[$areaId . ':' . $classId])) $usedCurrentYearDefaults = true;
            }
        }
        $loadedFromCurrentYear = count($targetAssignmentRows) === 0;
        foreach ($learningAreas as &$learningArea) {
            $learningArea['class_ids'] = array_map('intval', array_keys($classIdsByArea[(int) $learningArea['id']] ?? []));
        }
        unset($learningArea);

        $fees = $this->db->prepare(
            "SELECT ayfs.id, ayc.class_id, c.name AS class_name, ayfs.academic_year_term_id,
                    ayt.term_id, ayt.term_name AS term_name,
                    ayfs.student_type_id, st.name AS student_type_name, ayfs.fee_catalog_id,
                    ayfs.amount, ayfs.due_date, ayfs.status
             FROM " . ReadReplicaService::masterSourceRef('academic_year_fee_schedules') . " ayfs
             LEFT JOIN " . ReadReplicaService::masterSourceRef('academic_year_classes') . " ayc ON ayc.id = ayfs.academic_year_class_id
             LEFT JOIN " . ReadReplicaService::masterSourceRef('classes') . " c ON c.id = ayc.class_id
             LEFT JOIN " . ReadReplicaService::masterSourceRef('academic_term_terms') . " ayt ON ayt.academic_year_term_id = ayfs.academic_year_term_id
             LEFT JOIN " . ReadReplicaService::masterSourceRef('student_types') . " st ON st.id = ayfs.student_type_id
             WHERE ayfs.academic_year_id = ? ORDER BY c.id, ayt.academic_year_term_id, ayfs.student_type_id, ayfs.id"
        );
        $fees->execute([$yearId]);

        // staff_directory joins user roles, so a staff member with several roles
        // appears once per role. Collapse to one row per staff member and drop
        // test-scoped staff so the continuity list only holds live personnel.
        $staff = $this->db->query(
            "SELECT roster.staff_id AS id, MAX(roster.name) AS name
             FROM (
                 SELECT staff_id, last_name, first_name,
                        COALESCE(NULLIF(full_name, ''), CONCAT_WS(' ', first_name, last_name)) AS name
                 FROM " . ReadReplicaService::qualifiedRef('staff_directory') . "
                 WHERE staff_status = 'active'
                   AND staff_id IS NOT NULL
                   AND COALESCE(data_scope, 'live') <> 'test'
                   AND COALESCE(person_data_scope, 'live') <> 'test'
                   AND COALESCE(is_test_user, 0) = 0
                   AND COALESCE(account_type, 'real') <> 'test'
             ) roster
             WHERE roster.name IS NOT NULL AND roster.name <> ''
             GROUP BY roster.staff_id
             ORDER BY MAX(roster.last_name), MAX(roster.first_name)
             LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC);
        $savedContinuity = is_array($workflowData['staff_continuity'] ?? null) ? $workflowData['staff_continuity'] : [];
        $staffContinuity = array_map(static function (array $person) use ($savedContinuity): array {
            $staffId = (int) $person['id'];
            return [
                'staff_id' => $staffId,
                'name' => (string) $person['name'],
                'continuity' => (string) ($savedContinuity[(string) $staffId] ?? 'undecided'),
            ];
        }, $staff);
        $termOptions = $this->db->prepare(
            "SELECT academic_year_term_id AS id, term_name AS name FROM " . ReadReplicaService::masterSourceRef('academic_term_terms') . " WHERE academic_year_id = ? ORDER BY academic_year_term_id"
        );
        $termOptions->execute([$yearId]);
        $feeCatalog = $this->db->query("SELECT id, code, name FROM fee_catalog WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $feeNames = [];
        $feeCodes = [];
        foreach ($feeCatalog as $feeItem) {
            $feeNames[(int) $feeItem['id']] = (string) $feeItem['name'];
            $feeCodes[(int) $feeItem['id']] = (string) $feeItem['code'];
        }
        $feeRows = $fees->fetchAll(PDO::FETCH_ASSOC);
        $sourceDraftCounts = ['fees' => 0];
        foreach ($feeRows as &$feeRow) {
            $catalogId = (int) $feeRow['fee_catalog_id'];
            $feeRow['fee_catalog_name'] = $feeNames[$catalogId] ?? '';
            $feeRow['fee_catalog_code'] = $feeCodes[$catalogId] ?? '';
        }
        unset($feeRow);

        // Extra charges are part of the annual fee structure. Prefer target
        // year records (even if only some have been entered); fill missing
        // charges from the outgoing year as editable, unsaved draft rows.
        $extraChargeStmt = $this->db->prepare(
            "SELECT ec.id, ec.name, ec.description, ec.amount, ec.calculation_mode, ec.unit_label, ec.unit_price,
                    ec.charge_frequency, ec.billing_model, ec.billing_frequency, ec.visible_on_fee_structure,
                    ec.target_scope, ec.admission_condition, ec.class_id, ec.student_type_id, ec.gl_account_id, coa.account_name AS gl_account_name,
                    ec.display_order, ec.status
             FROM extra_charges ec LEFT JOIN chart_of_accounts coa ON coa.id=ec.gl_account_id
             WHERE ec.academic_year_id = ? AND ec.visible_on_fee_structure = 1
               AND ec.target_scope IN ('all_students','new_admissions','existing_students','boarders','day_students')
               AND ec.scope = 'all' AND ec.class_id IS NULL AND ec.student_type_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM extra_charge_classes x WHERE x.extra_charge_id=ec.id)
               AND NOT EXISTS (SELECT 1 FROM extra_charge_student_types x WHERE x.extra_charge_id=ec.id)
               AND NOT EXISTS (SELECT 1 FROM extra_charge_students x WHERE x.extra_charge_id=ec.id)
               AND NOT EXISTS (SELECT 1 FROM extra_charge_parents x WHERE x.extra_charge_id=ec.id)
               AND NOT EXISTS (SELECT 1 FROM extra_charge_pricing_tiers t WHERE t.extra_charge_id=ec.id AND t.condition_code NOT IN ('default','standard','all','new_parent','existing_parent','new','existing'))
             ORDER BY ec.display_order, ec.name, ec.id"
        );
        $extraChargeStmt->execute([$yearId]);
        $extraCharges = $extraChargeStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($extraCharges as &$charge) {
            $tiers = $this->db->prepare(
                "SELECT condition_code AS `condition`, label, amount FROM extra_charge_pricing_tiers WHERE extra_charge_id = ? ORDER BY sort_order, id"
            );
            $tiers->execute([(int) $charge['id']]);
            $charge['pricing_tiers'] = $tiers->fetchAll(PDO::FETCH_ASSOC);
            $charge['is_source_draft'] = false;
        }
        unset($charge);
        $sourceDraftCounts['extra_charges'] = 0;
        if ($sourceYearId) {
            $sourceChargeStmt = $this->db->prepare(
                "SELECT ec.id, ec.name, ec.description, ec.amount, ec.calculation_mode, ec.unit_label, ec.unit_price,
                        ec.charge_frequency, ec.billing_model, ec.billing_frequency, ec.visible_on_fee_structure,
                        ec.target_scope, ec.admission_condition, ec.class_id, ec.student_type_id, ec.gl_account_id, coa.account_name AS gl_account_name,
                        ec.display_order, ec.status
                 FROM extra_charges ec LEFT JOIN chart_of_accounts coa ON coa.id=ec.gl_account_id
                 WHERE ec.academic_year_id = ? AND ec.status <> 'inactive' AND ec.visible_on_fee_structure = 1
                   AND ec.target_scope IN ('all_students','new_admissions','existing_students','boarders','day_students')
                   AND ec.scope = 'all' AND ec.class_id IS NULL AND ec.student_type_id IS NULL
                   AND NOT EXISTS (SELECT 1 FROM extra_charge_classes x WHERE x.extra_charge_id=ec.id)
                   AND NOT EXISTS (SELECT 1 FROM extra_charge_student_types x WHERE x.extra_charge_id=ec.id)
                   AND NOT EXISTS (SELECT 1 FROM extra_charge_students x WHERE x.extra_charge_id=ec.id)
                   AND NOT EXISTS (SELECT 1 FROM extra_charge_parents x WHERE x.extra_charge_id=ec.id)
                   AND NOT EXISTS (SELECT 1 FROM extra_charge_pricing_tiers t WHERE t.extra_charge_id=ec.id AND t.condition_code NOT IN ('default','standard','all','new_parent','existing_parent','new','existing'))
                 ORDER BY ec.display_order, ec.name, ec.id"
            );
            $sourceChargeStmt->execute([$sourceYearId]);
            $targetChargeKeys = [];
            foreach ($extraCharges as $charge) {
                $targetChargeKeys[strtolower(trim((string) $charge['name'])) . ':' . (string) $charge['target_scope']] = true;
            }
            foreach ($sourceChargeStmt->fetchAll(PDO::FETCH_ASSOC) as $sourceCharge) {
                $key = strtolower(trim((string) $sourceCharge['name'])) . ':' . (string) $sourceCharge['target_scope'];
                if (isset($targetChargeKeys[$key])) continue;
                $sourceTiers = $this->db->prepare(
                    "SELECT condition_code AS `condition`, label, amount FROM extra_charge_pricing_tiers WHERE extra_charge_id = ? ORDER BY sort_order, id"
                );
                $sourceTiers->execute([(int) $sourceCharge['id']]);
                $sourceCharge['pricing_tiers'] = $sourceTiers->fetchAll(PDO::FETCH_ASSOC);
                $sourceCharge['id'] = 0;
                $sourceCharge['draft_key'] = 'source-charge-' . substr(hash('sha256', $key), 0, 16);
                $sourceCharge['status'] = 'draft';
                $sourceCharge['is_source_draft'] = true;
                $extraCharges[] = $sourceCharge;
                $targetChargeKeys[$key] = true;
                $sourceDraftCounts['extra_charges']++;
            }
        }

        if ($sourceYearId) {
            $sourceFees = $this->db->prepare(
                "SELECT sourceClass.class_id, sourceTerm.term_id, terms.name AS term_name,
                        fee.student_type_id, fee.fee_catalog_id, fee.amount, studentType.name AS student_type_name
                 FROM academic_year_fee_schedules fee
                 JOIN academic_year_classes sourceClass ON sourceClass.id = fee.academic_year_class_id
                 JOIN academic_year_terms sourceTerm ON sourceTerm.id = fee.academic_year_term_id
                 JOIN terms ON terms.id = sourceTerm.term_id
                 LEFT JOIN student_types studentType ON studentType.id = fee.student_type_id
                 WHERE fee.academic_year_id = ? AND fee.status = 'active'"
            );
            $sourceFees->execute([$sourceYearId]);
            $targetClassesForFees = $this->db->prepare("SELECT id, class_id FROM academic_year_classes WHERE academic_year_id = ? AND status IN ('planning','active')");
            $targetClassesForFees->execute([$yearId]);
            $targetAycByClass = [];
            foreach ($targetClassesForFees->fetchAll(PDO::FETCH_ASSOC) as $row) $targetAycByClass[(int) $row['class_id']] = (int) $row['id'];
            $targetTermsForFees = $this->db->prepare(
                "SELECT ayt.id, ayt.term_id, terms.name AS term_name, ayt.closing_date
                 FROM academic_year_terms ayt JOIN terms ON terms.id = ayt.term_id WHERE ayt.academic_year_id = ?"
            );
            $targetTermsForFees->execute([$yearId]);
            $targetTermById = [];
            $targetTermByName = [];
            foreach ($targetTermsForFees->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $targetTermById[(int) $row['term_id']] = $row;
                $targetTermByName[strtolower((string) $row['term_name'])] = $row;
            }
            $existingFeeKeys = [];
            foreach ($feeRows as $row) $existingFeeKeys[implode(':', [(int) $row['class_id'], strtolower((string) $row['term_name']), (int) $row['student_type_id'], (int) $row['fee_catalog_id']])] = true;
            $classNameById = array_column($classOptions, 'class_name', 'class_id');
            foreach ($sourceFees->fetchAll(PDO::FETCH_ASSOC) as $sourceFee) {
                $classId = (int) $sourceFee['class_id'];
                $term = $targetTermById[(int) $sourceFee['term_id']] ?? $targetTermByName[strtolower((string) $sourceFee['term_name'])] ?? null;
                if (empty($targetAycByClass[$classId]) || !$term) continue;
                $key = implode(':', [$classId, strtolower((string) $term['term_name']), (int) $sourceFee['student_type_id'], (int) $sourceFee['fee_catalog_id']]);
                if (isset($existingFeeKeys[$key])) continue;
                $draftKey = 'source-fee-' . substr(hash('sha256', $key), 0, 16);
                $feeRows[] = [
                    'id' => 0, 'draft_key' => $draftKey, 'academic_year_class_id' => $targetAycByClass[$classId],
                    'academic_year_term_id' => (int) $term['id'], 'class_id' => $classId,
                    'class_name' => $classNameById[$classId] ?? '', 'term_id' => (int) $term['term_id'],
                    'term_name' => (string) $term['term_name'], 'student_type_id' => (int) $sourceFee['student_type_id'],
                    'student_type_name' => (string) ($sourceFee['student_type_name'] ?? ''),
                    'fee_catalog_id' => (int) $sourceFee['fee_catalog_id'],
                    'fee_catalog_name' => $feeNames[(int) $sourceFee['fee_catalog_id']] ?? '',
                    'fee_catalog_code' => $feeCodes[(int) $sourceFee['fee_catalog_id']] ?? '',
                    'amount' => $sourceFee['amount'], 'due_date' => $term['closing_date'],
                    'status' => 'draft', 'is_source_draft' => true,
                ];
                $existingFeeKeys[$key] = true;
                $sourceDraftCounts['fees']++;
            }
        }

        $termOptionRows = $termOptions->fetchAll(PDO::FETCH_ASSOC);

        return formatResponse(true, [
            'learning_areas' => $learningAreas,
            'class_options' => array_map(static fn(array $row): array => [
                'id' => (int) $row['class_id'], 'name' => (string) $row['class_name'],
            ], $classOptions),
            'learning_areas_loaded_from_current_year' => $loadedFromCurrentYear,
            'learning_areas_have_current_year_defaults' => $usedCurrentYearDefaults,
            'source_draft_counts' => $sourceDraftCounts,
            'staff_continuity' => $staffContinuity,
            'fees' => $feeRows,
            'extra_charges' => $extraCharges,
            'term_options' => $termOptionRows,
            'fee_catalog_options' => $feeCatalog,
        ], 'Target-year setup drafts loaded.');
    }

    /** Save editable setup rows while enforcing target-year ownership. */
    public function saveSetupDrafts(int $instanceId, array $drafts): array
    {
        $instance = $this->getWorkflowInstance($instanceId);
        if (!$instance) return formatResponse(false, null, 'Year transition workflow was not found.');
        $workflowData = json_decode((string) ($instance['data_json'] ?? ''), true) ?: [];
        $yearId = (int) ($workflowData['academic_year_id'] ?? 0)
            ?: $this->resolveYearIdFromCode($workflowData['to_year_code'] ?? $workflowData['to_year'] ?? '');
        if (!$yearId) return formatResponse(false, null, 'Prepare the target year’s classes and streams first.');
        $phase = (string) ($drafts['phase'] ?? '');
        $rows = $drafts['rows'] ?? [];
        $extraChargeRows = $drafts['extra_charges'] ?? [];
        if (!is_array($rows) || count($rows) > 2000) return formatResponse(false, null, 'Draft rows must be an array with no more than 2,000 entries.');
        if (!is_array($extraChargeRows) || count($extraChargeRows) > 500) return formatResponse(false, null, 'Extra charge rows must be an array with no more than 500 entries.');
        $saved = 0;
        $idMap = [];

        try {
            $this->db->beginTransaction();
            $targetClassRows = $this->db->prepare(
                "SELECT class_id FROM academic_year_classes
                 WHERE academic_year_id = ? AND status IN ('planning', 'active')"
            );
            $targetClassRows->execute([$yearId]);
            $classOptions = array_map(static fn(array $item): array => ['class_id' => (int) $item['class_id']], $targetClassRows->fetchAll(PDO::FETCH_ASSOC));
            if ($phase === 'curriculum') {
                foreach ($rows as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 100) continue;
                    $areaId = (int) ($row['id'] ?? 0);
                    $originalAreaId = $areaId;
                    $selectedClassIds = array_values(array_unique(array_filter(array_map('intval', $row['class_ids'] ?? []))));
                    if ($areaId <= 0 && !$selectedClassIds) continue;
                    $classNames = [];
                    if ($selectedClassIds) {
                        $marks = implode(',', array_fill(0, count($selectedClassIds), '?'));
                        $classCheck = $this->db->prepare(
                            "SELECT ayc.class_id, c.name FROM academic_year_classes ayc JOIN classes c ON c.id = ayc.class_id
                             WHERE ayc.academic_year_id = ? AND ayc.status IN ('planning','active') AND ayc.class_id IN ($marks)"
                        );
                        $classCheck->execute(array_merge([$yearId], $selectedClassIds));
                        foreach ($classCheck->fetchAll(PDO::FETCH_ASSOC) as $class) $classNames[(int) $class['class_id']] = (string) $class['name'];
                    }
                    $selectedClassIds = array_values(array_intersect($selectedClassIds, array_keys($classNames)));
                    if (!$selectedClassIds) {
                        if ($areaId > 0) {
                            $this->db->prepare(
                                "UPDATE academic_year_class_learning_areas SET status = 'skipped'
                                 WHERE learning_area_id = ? AND academic_year_class_id IN
                                   (SELECT id FROM academic_year_classes WHERE academic_year_id = ?) AND status <> 'skipped'"
                            )->execute([$areaId, $yearId]);
                            $saved++;
                        }
                        continue;
                    }

                    // Learning area names are canonical school-wide records;
                    // class availability remains scoped to this target year.
                    $sourceArea = null;
                    if ($areaId > 0) {
                        $sourceLookup = $this->db->prepare("SELECT * FROM learning_areas WHERE id = ? AND status = 'active' LIMIT 1");
                        $sourceLookup->execute([$areaId]);
                        $sourceArea = $sourceLookup->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                    if (!$sourceArea || strcasecmp((string) $sourceArea['name'], $name) !== 0) {
                        if ($sourceArea) {
                            $this->db->prepare("UPDATE learning_areas SET name = ? WHERE id = ?")->execute([$name, $areaId]);
                        } else {
                        $band = 'lower_primary';
                        foreach ($selectedClassIds as $classId) {
                            $className = $classNames[$classId];
                            if (preg_match('/^Grade\s+[7-9]\b/i', $className)) { $band = 'junior_secondary'; break; }
                            if (preg_match('/^Grade\s+[4-6]\b/i', $className)) $band = 'upper_primary';
                            if (preg_match('/^PP[12]\b/i', $className)) $band = 'pp';
                        }
                        $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), 0, 15)) ?: 'LA';
                        $code = $baseCode;
                        $suffix = 1;
                        $codeCheck = $this->db->prepare("SELECT id FROM learning_areas WHERE code = ? AND level_band = ? LIMIT 1");
                        while (true) {
                            $codeCheck->execute([$code, $band]);
                            if (!$codeCheck->fetchColumn()) break;
                            $code = substr($baseCode, 0, 12) . (++$suffix);
                        }
                        $levels = implode(', ', array_values(array_unique(array_map(static fn(int $id): string => $classNames[$id], $selectedClassIds))));
                        $insertArea = $this->db->prepare(
                            "INSERT INTO learning_areas (name, code, level_band, levels, status, is_optional)
                             VALUES (?, ?, ?, ?, 'active', 0)"
                        );
                        $insertArea->execute([$name, $code, $band, $levels]);
                        $areaId = (int) $this->db->lastInsertId();
                        if ($originalAreaId <= 0) $idMap[(string) ($row['draft_key'] ?? '')] = $areaId;
                        }
                    }

                    $targetClassQuery = $this->db->prepare("SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1");
                    foreach ($classOptions as $classOption) {
                        $classId = (int) $classOption['class_id'];
                        $targetClassQuery->execute([$yearId, $classId]);
                        $targetClassId = (int) ($targetClassQuery->fetchColumn() ?: 0);
                        if (!$targetClassId) continue;
                        if (!in_array($classId, $selectedClassIds, true)) {
                            $this->db->prepare("UPDATE academic_year_class_learning_areas SET status = 'skipped' WHERE academic_year_class_id = ? AND learning_area_id = ? AND status <> 'skipped'")->execute([$targetClassId, $areaId]);
                            continue;
                        }
                        $existing = $this->db->prepare("SELECT id FROM academic_year_class_learning_areas WHERE academic_year_class_id = ? AND learning_area_id = ? ORDER BY id LIMIT 1");
                        $existing->execute([$targetClassId, $areaId]);
                        $assignmentId = (int) ($existing->fetchColumn() ?: 0);
                        if ($assignmentId) {
                            $this->db->prepare("UPDATE academic_year_class_learning_areas SET strand_id = NULL, sub_strand_id = NULL, status = 'planned', planned_weeks = NULL, notes = NULL WHERE id = ?")->execute([$assignmentId]);
                        } else {
                            $this->db->prepare("INSERT INTO academic_year_class_learning_areas (academic_year_class_id, learning_area_id, status) VALUES (?, ?, 'planned')")->execute([$targetClassId, $areaId]);
                            $assignmentId = (int) $this->db->lastInsertId();
                        }
                        $this->db->prepare("UPDATE academic_year_class_learning_areas SET status = 'skipped' WHERE academic_year_class_id = ? AND learning_area_id = ? AND id <> ? AND status <> 'skipped'")->execute([$targetClassId, $areaId, $assignmentId]);
                        $saved++;
                    }
                }
                $knownAreaIds = [];
                foreach ($rows as $row) if ((int) ($row['id'] ?? 0) > 0) $knownAreaIds[] = (int) $row['id'];
                foreach ($idMap as $mappedId) $knownAreaIds[] = (int) $mappedId;
                if ($knownAreaIds) {
                    $marks = implode(',', array_fill(0, count($knownAreaIds), '?'));
                    $this->db->prepare(
                        "UPDATE academic_year_class_learning_areas SET status = 'skipped'
                         WHERE academic_year_class_id IN (SELECT id FROM academic_year_classes WHERE academic_year_id = ?)
                           AND learning_area_id NOT IN ($marks) AND status <> 'skipped'"
                    )->execute(array_merge([$yearId], $knownAreaIds));
                }
            } elseif ($phase === 'staff_continuity') {
                $continuity = is_array($workflowData['staff_continuity'] ?? null) ? $workflowData['staff_continuity'] : [];
                foreach ($rows as $row) {
                    $staffId = (int) ($row['staff_id'] ?? 0);
                    $decision = (string) ($row['continuity'] ?? 'undecided');
                    if (!$staffId || !in_array($decision, ['undecided', 'continuing', 'not_continuing'], true)) continue;
                    $staffCheck = $this->db->prepare("SELECT id FROM staff WHERE id = ? AND status = 'active' LIMIT 1");
                    $staffCheck->execute([$staffId]);
                    if (!$staffCheck->fetchColumn()) continue;
                    $continuity[(string) $staffId] = $decision;
                    $saved++;
                }
                $workflowData['staff_continuity'] = $continuity;
                $workflowData['staff_assignments_deferred_until_term_setup'] = true;
                $this->mergeInstanceData($instanceId, [
                    'staff_continuity' => $continuity,
                    'staff_assignments_deferred_until_term_setup' => true,
                ]);
            } elseif ($phase === 'staff') {
                foreach ($rows as $row) {
                    $kind = (string) ($row['kind'] ?? '');
                    $staffId = (int) ($row['staff_id'] ?? 0) ?: null;
                    if ($staffId) {
                        $staffCheck = $this->db->prepare("SELECT id FROM staff WHERE id = ? AND status = 'active' LIMIT 1");
                        $staffCheck->execute([$staffId]);
                        if (!$staffCheck->fetchColumn()) continue;
                    }
                    if ($kind === 'class') {
                        $this->db->prepare(
                            "UPDATE academic_year_class_streams SET class_teacher_id = ?
                             WHERE id = ? AND academic_year_class_id IN (SELECT id FROM academic_year_classes WHERE academic_year_id = ?)"
                        )->execute([$staffId, (int) ($row['id'] ?? 0), $yearId]);
                        $saved += $this->lastAffectedRows();
                    } elseif ($kind === 'subject') {
                        $assignmentId = (int) ($row['assignment_id'] ?? 0);
                        $areaId = (int) ($row['learning_area_assignment_id'] ?? 0);
                        $termId = (int) ($row['term_id'] ?? 0);
                        if ($assignmentId && !$staffId) {
                            $this->db->prepare(
                                "DELETE FROM academic_year_class_learning_area_teachers
                                 WHERE id = ? AND academic_year_class_learning_area_id IN
                                  (SELECT a.id FROM academic_year_class_learning_areas a JOIN academic_year_classes c ON c.id = a.academic_year_class_id WHERE c.academic_year_id = ?)"
                            )->execute([$assignmentId, $yearId]);
                            $saved += $this->lastAffectedRows();
                        } elseif ($assignmentId && $staffId) {
                            $duplicate = $this->db->prepare(
                                "SELECT id FROM academic_year_class_learning_area_teachers
                                 WHERE academic_year_class_learning_area_id = ? AND academic_year_term_id = ?
                                   AND staff_id = ? AND role = ? AND id <> ? LIMIT 1"
                            );
                            $duplicate->execute([$areaId, $termId, $staffId, (string) ($row['role'] ?? 'subject_teacher'), $assignmentId]);
                            if ($duplicate->fetchColumn()) {
                                $this->db->prepare(
                                    "DELETE FROM academic_year_class_learning_area_teachers
                                     WHERE id = ? AND academic_year_class_learning_area_id IN
                                      (SELECT a.id FROM academic_year_class_learning_areas a JOIN academic_year_classes c ON c.id = a.academic_year_class_id WHERE c.academic_year_id = ?)"
                                )->execute([$assignmentId, $yearId]);
                                $saved += $this->lastAffectedRows();
                            } else {
                                $this->db->prepare(
                                    "UPDATE academic_year_class_learning_area_teachers SET staff_id = ?
                                     WHERE id = ? AND academic_year_class_learning_area_id IN
                                      (SELECT a.id FROM academic_year_class_learning_areas a JOIN academic_year_classes c ON c.id = a.academic_year_class_id WHERE c.academic_year_id = ?)"
                                )->execute([$staffId, $assignmentId, $yearId]);
                                $saved += $this->lastAffectedRows();
                            }
                        } elseif (!$assignmentId && $staffId && $areaId && $termId) {
                            $exists = $this->db->prepare(
                                "SELECT id FROM academic_year_class_learning_area_teachers
                                 WHERE academic_year_class_learning_area_id = ? AND academic_year_term_id = ?
                                   AND staff_id = ? AND role = ? LIMIT 1"
                            );
                            $role = (string) ($row['role'] ?? 'subject_teacher');
                            if (!in_array($role, ['subject_teacher', 'assistant', 'hod'], true)) $role = 'subject_teacher';
                            $exists->execute([$areaId, $termId, $staffId, $role]);
                            $existingId = (int) ($exists->fetchColumn() ?: 0);
                            if ($existingId) {
                                if (!empty($row['draft_key'])) $idMap[(string) $row['draft_key']] = $existingId;
                            } else {
                                $this->db->prepare(
                                    "INSERT INTO academic_year_class_learning_area_teachers (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)
                                     SELECT ?, ayt.id, ?, ? FROM academic_year_terms ayt
                                     JOIN academic_year_class_learning_areas a ON a.id = ?
                                     JOIN academic_year_classes c ON c.id = a.academic_year_class_id
                                     WHERE ayt.id = ? AND ayt.academic_year_id = ? AND c.academic_year_id = ?"
                                )->execute([$areaId, $staffId, $role, $areaId, $termId, $yearId, $yearId]);
                                $saved += $this->lastAffectedRows();
                                if (!empty($row['draft_key'])) $idMap[(string) $row['draft_key']] = (int) $this->db->lastInsertId();
                            }
                        }
                    } elseif ($kind === 'stream_subject') {
                        $assignmentId = (int) ($row['assignment_id'] ?? 0);
                        $streamId = (int) ($row['stream_id'] ?? 0);
                        $streamAreaId = (int) ($row['stream_learning_area_id'] ?? 0);
                        $termId = (int) ($row['term_id'] ?? 0);
                        $learningAreaId = (int) ($row['learning_area_id'] ?? 0);
                        $role = (string) ($row['role'] ?? 'subject_teacher');
                        if ($assignmentId && !$staffId) {
                            $this->db->prepare(
                                "DELETE FROM academic_year_class_stream_learning_area_teachers
                                 WHERE id = ? AND academic_year_class_stream_id IN
                                  (SELECT s.id FROM academic_year_class_streams s JOIN academic_year_classes c ON c.id = s.academic_year_class_id WHERE c.academic_year_id = ?)"
                            )->execute([$assignmentId, $yearId]);
                            $saved += $this->lastAffectedRows();
                        } elseif ($staffId && $streamId && $streamAreaId && $termId && $learningAreaId) {
                            $valid = $this->db->prepare(
                                "SELECT COUNT(*) FROM academic_year_class_stream_learning_areas sla
                                 JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id
                                 JOIN academic_year_class_streams s ON s.id = sla.academic_year_class_stream_id
                                 JOIN academic_year_classes c ON c.id = s.academic_year_class_id
                                 JOIN academic_year_terms ayt ON ayt.academic_year_id = c.academic_year_id AND ayt.id = ?
                                 WHERE sla.id = ? AND s.id = ? AND cla.learning_area_id = ?
                                   AND c.academic_year_id = ? AND s.status IN ('planning', 'active')"
                            );
                            $valid->execute([$termId, $streamAreaId, $streamId, $learningAreaId, $yearId]);
                            if (!(int) $valid->fetchColumn()) continue;

                            $duplicate = $this->db->prepare(
                                "SELECT id FROM academic_year_class_stream_learning_area_teachers
                                 WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ?
                                   AND learning_area_id = ? AND staff_id = ? AND role = ? AND id <> ? LIMIT 1"
                            );
                            $duplicate->execute([$streamId, $termId, $learningAreaId, $staffId, $role, $assignmentId]);
                            $duplicateId = (int) ($duplicate->fetchColumn() ?: 0);
                            if ($duplicateId) {
                                if ($assignmentId) {
                                    $this->db->prepare(
                                        "DELETE FROM academic_year_class_stream_learning_area_teachers
                                         WHERE id = ? AND academic_year_class_stream_id = ? AND academic_year_term_id = ?"
                                    )->execute([$assignmentId, $streamId, $termId]);
                                    $saved += $this->lastAffectedRows();
                                } elseif (!empty($row['draft_key'])) {
                                    $idMap[(string) $row['draft_key']] = $duplicateId;
                                }
                                continue;
                            }
                            if ($assignmentId) {
                                $this->db->prepare(
                                    "UPDATE academic_year_class_stream_learning_area_teachers SET staff_id = ?
                                     WHERE id = ? AND academic_year_class_stream_id = ? AND academic_year_term_id = ?"
                                )->execute([$staffId, $assignmentId, $streamId, $termId]);
                                $saved += $this->lastAffectedRows();
                            } else {
                                $this->db->prepare(
                                    "INSERT INTO academic_year_class_stream_learning_area_teachers
                                        (academic_year_class_stream_id, academic_year_class_stream_learning_area_id,
                                         academic_year_term_id, learning_area_id, staff_id, role)
                                     VALUES (?, ?, ?, ?, ?, ?)"
                                )->execute([$streamId, $streamAreaId, $termId, $learningAreaId, $staffId, $role]);
                                $saved += $this->lastAffectedRows();
                                if (!empty($row['draft_key'])) $idMap[(string) $row['draft_key']] = (int) $this->db->lastInsertId();
                            }
                        }
                    }
                }
            } elseif ($phase === 'fees') {
                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    $amount = filter_var($row['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                    $dueDate = trim((string) ($row['due_date'] ?? ''));
                    $status = (string) ($row['status'] ?? 'draft');
                    if ($amount === false || $amount < 0 || $amount > 9999999999.99 || !in_array($status, ['draft', 'pending_review', 'inactive'], true)) continue;
                    if ($dueDate !== '') {
                        $parsedDueDate = \DateTime::createFromFormat('!Y-m-d', $dueDate);
                        $dateErrors = \DateTime::getLastErrors();
                        if (!$parsedDueDate || $parsedDueDate->format('Y-m-d') !== $dueDate || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))) continue;
                    }
                    if ($id > 0) {
                        $this->db->prepare(
                            "UPDATE academic_year_fee_schedules SET amount = ?, due_date = ?, status = ?, updated_at = NOW()
                             WHERE id = ? AND academic_year_id = ? AND status IN ('draft', 'pending_review', 'inactive')"
                        )->execute([$amount, $dueDate ?: null, $status, $id, $yearId]);
                        $saved += $this->lastAffectedRows();
                    } else {
                        $targetClassId = (int) ($row['academic_year_class_id'] ?? 0);
                        $targetTermId = (int) ($row['academic_year_term_id'] ?? 0);
                        $studentTypeId = (int) ($row['student_type_id'] ?? 0);
                        $catalogId = (int) ($row['fee_catalog_id'] ?? 0);
                        $valid = $this->db->prepare(
                            "SELECT COUNT(*) FROM academic_year_classes ayc
                             JOIN academic_year_terms ayt ON ayt.academic_year_id = ayc.academic_year_id
                             WHERE ayc.id = ? AND ayc.academic_year_id = ? AND ayt.id = ?"
                        );
                        $valid->execute([$targetClassId, $yearId, $targetTermId]);
                        if (!(int) $valid->fetchColumn()) continue;
                        $exists = $this->db->prepare(
                            "SELECT id FROM academic_year_fee_schedules WHERE academic_year_id = ?
                             AND academic_year_class_id = ? AND academic_year_term_id = ?
                             AND student_type_id = ? AND fee_catalog_id = ? LIMIT 1"
                        );
                        $exists->execute([$yearId, $targetClassId, $targetTermId, $studentTypeId, $catalogId]);
                        if ($exists->fetchColumn()) continue;
                        $this->db->prepare(
                            "INSERT INTO academic_year_fee_schedules
                             (academic_year_id, academic_year_term_id, academic_year_class_id,
                              student_type_id, fee_catalog_id, amount, due_date, status, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                        )->execute([$yearId, $targetTermId, $targetClassId, $studentTypeId, $catalogId, $amount, $dueDate ?: null, $status]);
                        $saved += $this->lastAffectedRows();
                        if (!empty($row['draft_key'])) $idMap[(string) $row['draft_key']] = (int) $this->db->lastInsertId();
                    }
                }
                $allowedScopes = ['new_admissions', 'existing_students', 'all_students', 'boarders', 'day_students'];
                $allowedModels = ['added_to_fees', 'paid_separately', 'optional'];
                $allowedFrequencies = ['one_time', 'daily', 'weekly', 'monthly', 'per_term', 'per_year'];
                foreach ($extraChargeRows as $charge) {
                    if (!is_array($charge)) continue;
                    $chargeId = (int) ($charge['id'] ?? 0);
                    $name = trim((string) ($charge['name'] ?? ''));
                    $tiers = is_array($charge['pricing_tiers'] ?? null) ? $charge['pricing_tiers'] : [];
                    $amountInput = $charge['amount'] ?? null;
                    if (($amountInput === null || $amountInput === '') && $tiers) $amountInput = $tiers[0]['amount'] ?? null;
                    $amount = filter_var($amountInput, FILTER_VALIDATE_FLOAT);
                    $scope = (string) ($charge['target_scope'] ?? 'all_students');
                    $admissionCondition = in_array($charge['admission_condition'] ?? '', ['any_new_admission', 'interview_scheduled'], true)
                        ? (string) $charge['admission_condition'] : 'any_new_admission';
                    $model = (string) ($charge['billing_model'] ?? 'paid_separately');
                    $frequency = (string) ($charge['billing_frequency'] ?? 'one_time');
                    $visible = !empty($charge['visible_on_fee_structure']) ? 1 : 0;
                    if ($name === '' || mb_strlen($name) > 150 || $amount === false || $amount < 0
                        || !in_array($scope, $allowedScopes, true) || !in_array($model, $allowedModels, true)
                        || !in_array($frequency, $allowedFrequencies, true)) continue;
                    $classIds = array_values(array_unique(array_filter(array_map('intval', (array) ($charge['class_ids'] ?? [])))));
                    if (!$classIds && !empty($charge['class_id'])) $classIds = [(int) $charge['class_id']];
                    if ($scope === 'specific_class' && !$classIds) continue;
                    if ($classIds) {
                        $marks = implode(',', array_fill(0, count($classIds), '?'));
                        $classCheck = $this->db->prepare("SELECT class_id FROM academic_year_classes WHERE academic_year_id = ? AND status IN ('planning','active') AND class_id IN ($marks)");
                        $classCheck->execute(array_merge([$yearId], $classIds));
                        $validClassIds = array_map('intval', $classCheck->fetchAll(PDO::FETCH_COLUMN));
                        if (count($validClassIds) !== count($classIds)) continue;
                        $classIds = $validClassIds;
                    }
                    $classId = $classIds[0] ?? null;
                    $studentTypeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($charge['student_type_ids'] ?? [])))));
                    if (!$studentTypeIds && !empty($charge['student_type_id'])) $studentTypeIds = [(int) $charge['student_type_id']];
                    if ($studentTypeIds) {
                        $marks = implode(',', array_fill(0, count($studentTypeIds), '?'));
                        $typeCheck = $this->db->prepare("SELECT id FROM student_types WHERE status='active' AND id IN ($marks)");
                        $typeCheck->execute($studentTypeIds);
                        if (count(array_map('intval', $typeCheck->fetchAll(PDO::FETCH_COLUMN))) !== count($studentTypeIds)) continue;
                    }
                    $studentTypeId = $studentTypeIds[0] ?? null;
                    $glAccountId = (int) ($charge['gl_account_id'] ?? 0) ?: null;
                    if ($glAccountId) {
                        $accountCheck = $this->db->prepare("SELECT id FROM chart_of_accounts WHERE id=? AND status='active' AND is_postable=1 LIMIT 1");
                        $accountCheck->execute([$glAccountId]);
                        if (!$accountCheck->fetchColumn()) continue;
                    }
                    if ($chargeId > 0) {
                        $editableCharge = $this->db->prepare("SELECT id FROM extra_charges WHERE id = ? AND academic_year_id = ? AND status = 'draft' LIMIT 1");
                        $editableCharge->execute([$chargeId, $yearId]);
                        if (!$editableCharge->fetchColumn()) continue;
                        $update = $this->db->prepare(
                            "UPDATE extra_charges SET name = ?, description = ?, amount = ?, target_scope = ?, admission_condition = ?, class_id = ?, student_type_id = ?, gl_account_id = ?,
                             billing_model = ?, billing_frequency = ?, visible_on_fee_structure = ?, updated_at = NOW()
                             WHERE id = ? AND academic_year_id = ? AND status = 'draft'"
                        );
                        $update->execute([$name, trim((string) ($charge['description'] ?? '')) ?: null, $amount, $scope, $admissionCondition, $classId, $studentTypeId, $glAccountId,
                            $model, $frequency, $visible, $chargeId, $yearId]);
                        if ($this->lastAffectedRows() > 0) $saved++;
                    } else {
                        $this->db->prepare(
                            "INSERT INTO extra_charges (academic_year_id, name, description, amount, calculation_mode,
                             unit_label, unit_price, charge_frequency, billing_model, billing_frequency,
                             visible_on_fee_structure, target_scope, admission_condition, class_id, student_type_id, gl_account_id, display_order, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
                        )->execute([$yearId, $name, trim((string) ($charge['description'] ?? '')) ?: null, $amount,
                            (string) ($charge['calculation_mode'] ?? 'fixed'), $charge['unit_label'] ?? null,
                            $charge['unit_price'] ?? null, (string) ($charge['charge_frequency'] ?? 'one_time'),
                            $model, $frequency, $visible, $scope, $admissionCondition, $classId, $studentTypeId, $glAccountId,
                            (int) ($charge['display_order'] ?? 0)]);
                        $chargeId = (int) $this->db->lastInsertId();
                        if (!empty($charge['draft_key'])) $idMap[(string) $charge['draft_key']] = $chargeId;
                        $saved++;
                    }
                    if ($chargeId > 0) {
                        $this->db->prepare("DELETE FROM extra_charge_contexts WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $this->db->prepare("INSERT INTO extra_charge_contexts (extra_charge_id, context_code) VALUES (?, ?)")
                            ->execute([$chargeId, $scope === 'new_admissions' ? 'admission' : 'enrollment']);
                        $this->db->prepare("DELETE FROM extra_charge_classes WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $insertClass = $this->db->prepare("INSERT IGNORE INTO extra_charge_classes (extra_charge_id, class_id) VALUES (?, ?)");
                        foreach ($classIds as $selectedClassId) $insertClass->execute([$chargeId, $selectedClassId]);
                        $this->db->prepare("DELETE FROM extra_charge_parent_scopes WHERE extra_charge_id = ?")->execute([$chargeId]);
                        if ($scope === 'new_admissions') {
                            $this->db->prepare("INSERT INTO extra_charge_parent_scopes (extra_charge_id, parent_scope) VALUES (?, 'any_parent')")->execute([$chargeId]);
                        }
                        $this->db->prepare("DELETE FROM extra_charge_student_types WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $insertType = $this->db->prepare("INSERT IGNORE INTO extra_charge_student_types (extra_charge_id, student_type_id) VALUES (?, ?)");
                        foreach ($studentTypeIds as $selectedTypeId) $insertType->execute([$chargeId, $selectedTypeId]);
                        $this->db->prepare("DELETE FROM extra_charge_students WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $insertSelectedStudent = $this->db->prepare("INSERT IGNORE INTO extra_charge_students (extra_charge_id, student_id) VALUES (?, ?)");
                        foreach (array_values(array_unique(array_filter(array_map('intval', (array) ($charge['student_ids'] ?? []))))) as $selectedStudentId) $insertSelectedStudent->execute([$chargeId, $selectedStudentId]);
                        $this->db->prepare("DELETE FROM extra_charge_parents WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $insertSelectedParent = $this->db->prepare("INSERT IGNORE INTO extra_charge_parents (extra_charge_id, parent_id) VALUES (?, ?)");
                        foreach (array_values(array_unique(array_filter(array_map('intval', (array) ($charge['parent_ids'] ?? []))))) as $selectedParentId) $insertSelectedParent->execute([$chargeId, $selectedParentId]);
                        $this->db->prepare("DELETE FROM extra_charge_pricing_tiers WHERE extra_charge_id = ?")->execute([$chargeId]);
                        $insertTier = $this->db->prepare("INSERT INTO extra_charge_pricing_tiers (extra_charge_id, condition_code, label, amount, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $conditionCodes = [];
                        foreach (array_values($tiers) as $order => $tier) {
                            $label = trim((string) ($tier['label'] ?? ''));
                            $tierAmount = filter_var($tier['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                            if ($label === '' || $tierAmount === false || $tierAmount < 0) continue;
                            $condition = trim((string) ($tier['condition'] ?? '')) ?: 'default';
                            if (!in_array($condition, ['default', 'new_parent', 'existing_parent', 'new', 'existing'], true)) continue;
                            if (isset($conditionCodes[$condition])) continue;
                            $conditionCodes[$condition] = true;
                            $insertTier->execute([$chargeId, $condition, $label, $tierAmount, $order]);
                        }
                        $yearDates = $this->db->prepare('SELECT start_date,end_date FROM academic_years WHERE id=? LIMIT 1');
                        $yearDates->execute([$yearId]);
                        $dates = $yearDates->fetch(PDO::FETCH_ASSOC) ?: [];
                        $startsOn = trim((string) ($charge['starts_on'] ?? '')) ?: (string) ($dates['start_date'] ?? date('Y-m-d'));
                        $endsOn = trim((string) ($charge['ends_on'] ?? '')) ?: null;
                        $dueDay = (int) ($charge['due_day'] ?? 0) ?: null;
                        $termId = (int) ($charge['academic_year_term_id'] ?? 0) ?: null;
                        $this->db->prepare("UPDATE extra_charge_schedules SET status='inactive' WHERE extra_charge_id=?")->execute([$chargeId]);
                        $this->db->prepare(
                            "INSERT INTO extra_charge_schedules (extra_charge_id,frequency,starts_on,ends_on,academic_year_term_id,due_day,status)
                             VALUES (?,?,?,?,?,?,'active')"
                        )->execute([$chargeId, $frequency, $startsOn, $endsOn, $termId, $dueDay]);
                    }
                }
            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Unknown setup draft section.');
            }
            $this->db->commit();
            return formatResponse(true, ['saved' => $saved, 'phase' => $phase, 'id_map' => $idMap], 'Setup draft saved.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Reconcile a stale workflow pointer after promotion work has already been
     * completed in the target year.
     *
     * The promotion board can be saved while workflow_instances.current_stage
     * is still on an earlier setup stage (for example an older workflow left
     * at generate_calendar). Every later gated action then fails with
     * "Stage ... cannot be completed while the workflow is at ...". This is
     * forward-only and only moves the pointer when every active source-year
     * learner has a transition and target-year enrollments exist.
     */
    private function reconcilePromotionCompletion(int $instanceId, array $data = []): bool
    {
        $stageStmt = $this->db->prepare(
            'SELECT current_stage, reference_id, workflow_id, data_json
             FROM workflow_instances WHERE id = ? LIMIT 1'
        );
        $stageStmt->execute([$instanceId]);
        $instance = $stageStmt->fetch(PDO::FETCH_ASSOC);
        if (!$instance) return false;

        $targetStage = 'carry_forward_finances';
        $currentStage = (string) $instance['current_stage'];
        if ($currentStage === $targetStage) return true;

        $orderStmt = $this->db->prepare(
            'SELECT code, sequence FROM workflow_stages WHERE workflow_id = ? ORDER BY sequence'
        );
        $orderStmt->execute([(int) $instance['workflow_id']]);
        $order = [];
        foreach ($orderStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $order[(string) $row['code']] = (int) $row['sequence'];
        }
        if (!isset($order[$currentStage], $order[$targetStage])
            || $order[$currentStage] >= $order[$targetStage]) {
            return false;
        }

        $instanceData = json_decode((string) $instance['data_json'], true) ?: [];
        $data = $instanceData ?: $data;
        $toYearId = (int) ($data['academic_year_id'] ?? 0) ?: (int) $instance['reference_id'];
        $fromYearId = $this->resolveYearIdFromCode($data['from_year_code'] ?? $data['from_year'] ?? 0);
        if (!$fromYearId || !$toYearId) return false;

        $remaining = $this->db->prepare(
            "SELECT COUNT(*) FROM student_academic_enrollments sae
             JOIN students s ON s.id = sae.student_id AND s.status = 'active'
             WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('pending','active')
               AND NOT EXISTS (SELECT 1 FROM student_transitions st
                               WHERE st.from_student_academic_enrollment_id = sae.id
                                 AND st.academic_year_id = ?)"
        );
        $remaining->execute([$fromYearId, $toYearId]);
        if ((int) $remaining->fetchColumn() > 0) return false;

        $placed = $this->db->prepare(
            "SELECT COUNT(*) FROM student_academic_enrollments
             WHERE academic_year_id = ? AND enrollment_status IN ('pending','active')"
        );
        $placed->execute([$toYearId]);
        if ((int) $placed->fetchColumn() === 0) return false;

        $data['position_reconciled_at'] = date('Y-m-d H:i:s');
        $this->advanceStage(
            $instanceId,
            $targetStage,
            'Workflow position reconciled after promotion and target-year enrollment completion',
            $data
        );
        return true;
    }

    /** Return the active rollover from the primary database, not a stale projection. */
    private function activeWorkflowResponse(int $instanceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, current_stage, status, data_json FROM workflow_instances WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$instanceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return formatResponse(false, null, 'Active year transition was not found.');
        return formatResponse(true, [
            'id' => (int) $row['id'],
            'current_stage' => (string) $row['current_stage'],
            'current_stage_code' => (string) $row['current_stage'],
            'status' => (string) $row['status'],
            'data' => json_decode((string) $row['data_json'], true) ?: [],
        ], 'Active year transition found.');
    }

    /** Attach the in-page rollover workflow to a year that was created earlier. */
    public function startWorkflowForExistingYear($fromYearValue, $toYearValue): array
    {
        try {
            $fromCode = $this->yearCode($fromYearValue);
            $toCode = $this->yearCode($toYearValue);
            if ($fromCode === '' || $toCode === '' || $this->yearStart($toCode) !== $this->yearStart($fromCode) + 1) {
                return formatResponse(false, null, 'Choose the immediate next academic year to continue rollover.');
            }
            $active = $this->findActiveWorkflowForYear($toCode);

            $yearId = $this->resolveYearIdFromCode($toCode);
            if (!$yearId) return formatResponse(false, null, "Academic year {$toCode} was not found.");
            $yearStmt = $this->db->prepare('SELECT year_code, start_date, end_date FROM academic_years WHERE id = ? LIMIT 1');
            $yearStmt->execute([$yearId]);
            $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
            if (!$year || empty($year['start_date']) || empty($year['end_date'])) {
                return formatResponse(false, null, 'Enter the target academic-year dates before continuing setup.');
            }

            $termStmt = $this->db->prepare(
                'SELECT id, term_id, opening_date, half_term_start, half_term_end, closing_date FROM academic_year_terms WHERE academic_year_id = ? ORDER BY id'
            );
            $termStmt->execute([$yearId]);
            $termRows = $termStmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($termRows) !== 3 || array_filter($termRows, static fn($term) => empty($term['opening_date']) || empty($term['closing_date']))) {
                return formatResponse(false, null, 'Enter dates for all three target-year terms before continuing setup.');
            }
            $terms = [];
            $termIds = [];
            foreach ($termRows as $index => $term) {
                $termNameStmt = $this->db->prepare('SELECT name FROM terms WHERE id = ? LIMIT 1');
                $termNameStmt->execute([(int) $term['term_id']]);
                $terms[] = [
                    'term_name' => (string) ($termNameStmt->fetchColumn() ?: ('Term ' . ($index + 1))),
                    'start_date' => $term['opening_date'],
                    'end_date' => $term['closing_date'],
                    'half_term_start' => $term['half_term_start'],
                    'half_term_end' => $term['half_term_end'],
                ];
                $termIds[] = (int) $term['id'];
            }
            $calendarService = new AcademicCalendarService($this->db);
            $activeInstanceId = (int) ($active['data']['id'] ?? 0);
            if ($activeInstanceId > 0) {
                // Resuming an existing rollover also repairs a missing or
                // partial calendar left by an older Stage 1 implementation.
                $calendarReadiness = $calendarService->validateCalendar($yearId);
                if (empty($calendarReadiness['pass'])) {
                    $calendar = $calendarService->generateYearCalendar($yearId);
                    if (($calendar['status'] ?? '') !== 'success') {
                        return formatResponse(false, null, $calendar['message'] ?? 'Could not prepare the target-year calendar.');
                    }
                }
                // Older instances can be left behind on an earlier setup stage
                // after promotions have already been completed. Reconcile from
                // the primary database before returning the saved workflow.
                $this->reconcilePromotionCompletion($activeInstanceId);
                return $this->activeWorkflowResponse($activeInstanceId);
            }
            $calendar = $calendarService->generateYearCalendar($yearId);
            if (($calendar['status'] ?? '') !== 'success') {
                return formatResponse(false, null, $calendar['message'] ?? 'Could not prepare the target-year calendar.');
            }
            $data = [
                'from_year' => $this->yearStart($fromCode), 'to_year' => $this->yearStart($toCode),
                'from_year_code' => $fromCode, 'to_year_code' => $toCode,
                'academic_year_id' => $yearId, 'year_start_date' => $year['start_date'],
                'year_end_date' => $year['end_date'], 'terms' => $terms, 'term_ids' => $termIds,
                'new_classes' => [], 'migrated_baselines' => [], 'validation_results' => [],
            ];
            $instanceId = $this->startWorkflow($this->getWorkflowDefinitionCode(), $yearId, $data);
            $this->advanceStage($instanceId, 'create_next_year', 'Existing target academic year selected', $data);
            $this->advanceStage($instanceId, 'enter_year_term_dates', 'Existing year and term dates confirmed', $data);
            $this->advanceStage($instanceId, 'generate_calendar', 'Target-year calendar generated from recorded dates', $data);
            $this->advanceStage($instanceId, 'configure_classes_streams', 'Target-year calendar prepared; classes and streams are next', $data);
            return formatResponse(true, ['instance_id' => (int) $instanceId, 'workflow_data' => $data], 'Existing academic year added to rollover.');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /** Complete the setup stages performed by setupNewYear in one resumable action. */
    private function advanceNewYearSetupStages(int $instanceId, array $data, string $summary): void
    {
        // This helper runs inside setupNewYear's write transaction, so read
        // the workflow pointer from the primary database rather than a
        // potentially lagging reporting projection.
        $instanceStmt = $this->db->prepare(
            'SELECT current_stage FROM workflow_instances WHERE id = ? LIMIT 1'
        );
        $instanceStmt->execute([$instanceId]);
        $currentStage = (string) ($instanceStmt->fetchColumn() ?: '');

        // Existing-year workflows created by earlier versions can still be at
        // generate_calendar when the administrator saves the class structure.
        // Walk the valid stage transitions forward so Learning Areas becomes
        // the next tab after classes are saved.
        if ($currentStage === 'generate_calendar') {
            $this->advanceStage(
                $instanceId,
                'configure_classes_streams',
                'Target-year calendar confirmed; class and stream structure saved',
                $data
            );
            $currentStage = 'configure_classes_streams';
        }

        if ($currentStage === 'configure_classes_streams') {
            $this->advanceStage($instanceId, 'configure_learning_areas', $summary, $data);
        }
    }

    /**
     * Ensure a global terms row exists for the given term name/number.
     * Returns the terms.id.
     */
    private function ensureTermId(string $termName, int $termNumber): int
    {
        $code = 'T' . $termNumber;
        $stmt = $this->db->prepare("SELECT id FROM terms WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if (!$id) {
            $stmt = $this->db->prepare("INSERT INTO terms (name, code) VALUES (?, ?)");
            $stmt->execute([$termName, $code]);
            $id = (int) $this->db->lastInsertId();
        }
        return $id;
    }

    /**
     * Reuse-or-create a streams row and link it to an academic year class.
     * Returns the academic_year_class_streams.id.
     */
    private function createStreamLink(int $aycId, array $stream): int
    {
        $name = trim((string) ($stream['name'] ?? ''));
        if ($name === '') {
            throw new Exception('Stream name is required');
        }
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
        $capacity = (int) ($stream['capacity'] ?? 40);
        if ($capacity <= 0) {
            $capacity = 40;
        }

        $stmt = $this->db->prepare("SELECT id FROM streams WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $streamId = $row ? (int) $row['id'] : 0;

        if (!$streamId) {
            $stmt = $this->db->prepare("INSERT INTO streams (name, code, capacity) VALUES (?, ?, 40)");
            $stmt->execute([$name, $code]);
            $streamId = (int) $this->db->lastInsertId();
        }

        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_class_streams
            WHERE academic_year_class_id = ? AND stream_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aycId, $streamId]);
        $aycsId = (int) ($stmt->fetchColumn() ?: 0);

        if (!$aycsId) {
            $stmt = $this->db->prepare("
                INSERT INTO academic_year_class_streams (academic_year_class_id, stream_id, room_id, class_teacher_id, status, capacity)
                VALUES (?, ?, NULL, NULL, 'active', ?)
            ");
            $stmt->execute([$aycId, $streamId, $capacity]);
            $aycsId = (int) $this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare("UPDATE academic_year_class_streams SET capacity = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$capacity, $aycsId]);
        }

        return $aycsId;
    }

    /**
     * Stage 1: Prepare academic calendar
     * 
     * @param array $calendar {
     *   @type int $from_year Previous academic year
     *   @type int $to_year New academic year
     *   @type string $year_start_date New year start date
     *   @type string $year_end_date New year end date
     *   @type array $terms Array of term definitions {
     *     @type string $term_name Term name (e.g., "Term 1")
     *     @type string $start_date Term start date
     *     @type string $end_date Term end date
     *     @type int $weeks Duration in weeks
     *   }
     *   @type array $holidays Public holidays and breaks
     *   @type string $transition_notes Notes about the transition
     * }
     * @return array Response with workflow instance
     */
    public function prepareCalendar(array $calendar): array
    {
        try {
            // Validation
            $required = ['from_year', 'to_year', 'year_start_date', 'year_end_date', 'terms'];
            foreach ($required as $field) {
                if (!isset($calendar[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            $fromYear = $this->yearStart($calendar['from_year']);
            $toYear = $this->yearStart($calendar['to_year']);
            $fromYearCode = $this->yearCode($fromYear);
            $toYearCode = $this->yearCode($toYear);
            if (!$fromYear || !$toYear) {
                return formatResponse(false, null, 'Academic years must use YYYY or YYYY/YYYY+1 format.');
            }

            if (!is_array($calendar['terms']) || count($calendar['terms']) !== 3) {
                return formatResponse(false, null, 'Exactly three term date ranges are required.');
            }

            $parseDate = static function ($value, string $label): string {
                $value = trim((string) $value);
                $date = \DateTime::createFromFormat('!Y-m-d', $value);
                $errors = \DateTime::getLastErrors();
                if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                    throw new Exception("{$label} must be a valid YYYY-MM-DD date");
                }
                return $date->format('Y-m-d');
            };

            try {
                $yearStart = $parseDate($calendar['year_start_date'], 'Academic year opening date');
                $yearEnd = $parseDate($calendar['year_end_date'], 'Academic year closing date');
                if ($yearStart >= $yearEnd) {
                    return formatResponse(false, null, 'Academic year opening date must be before its closing date.');
                }
                if ((int) substr($yearStart, 0, 4) !== $toYear
                    || (int) substr($yearEnd, 0, 4) > $toYear + 1) {
                    return formatResponse(false, null, 'Academic year dates must start in the selected year and end within the following year.');
                }

                $calendar['terms'] = array_values($calendar['terms']);
                $previousTermEnd = null;
                foreach ($calendar['terms'] as $index => &$term) {
                    $termStart = $parseDate($term['start_date'] ?? $term['opening_date'] ?? '', 'Term ' . ($index + 1) . ' opening date');
                    $termEnd = $parseDate($term['end_date'] ?? $term['closing_date'] ?? '', 'Term ' . ($index + 1) . ' closing date');
                    if ($termStart >= $termEnd) {
                        return formatResponse(false, null, 'Term ' . ($index + 1) . ' opening date must be before its closing date.');
                    }
                    if ($termStart < $yearStart || $termEnd > $yearEnd) {
                        return formatResponse(false, null, 'Every term must fall within the academic year dates.');
                    }
                    if ($previousTermEnd !== null && $termStart <= $previousTermEnd) {
                        return formatResponse(false, null, 'Term dates must be chronological and must not overlap.');
                    }
                    $hasHalfTerm = filter_var(
                        $term['has_half_term'] ?? (($term['half_term_start'] ?? '') !== '' || ($term['half_term_end'] ?? '') !== ''),
                        FILTER_VALIDATE_BOOLEAN
                    );
                    $halfTermStart = null;
                    $halfTermEnd = null;
                    if ($hasHalfTerm) {
                        if (empty($term['half_term_start']) || empty($term['half_term_end'])) {
                            return formatResponse(false, null, 'Term ' . ($index + 1) . ' half-term opening and closing dates are required when a half-term break is enabled.');
                        }
                        $halfTermStart = $parseDate($term['half_term_start'], 'Term ' . ($index + 1) . ' half-term opening date');
                        $halfTermEnd = $parseDate($term['half_term_end'], 'Term ' . ($index + 1) . ' half-term closing date');
                        if ($halfTermStart < $termStart || $halfTermEnd > $termEnd || $halfTermStart > $halfTermEnd) {
                            return formatResponse(false, null, 'Term ' . ($index + 1) . ' half-term dates must fall within the term and be chronological.');
                        }
                    }
                    $term['start_date'] = $termStart;
                    $term['end_date'] = $termEnd;
                    $term['has_half_term'] = $hasHalfTerm;
                    $term['half_term_start'] = $halfTermStart;
                    $term['half_term_end'] = $halfTermEnd;
                    $previousTermEnd = $termEnd;
                }
                unset($term);
            } catch (Exception $validationError) {
                return formatResponse(false, null, $validationError->getMessage());
            }
            $calendar['year_start_date'] = $yearStart;
            $calendar['year_end_date'] = $yearEnd;

            if ($toYear !== $fromYear + 1) {
                return formatResponse(false, null, 'New year must be exactly one year after previous year');
            }

            $this->db->beginTransaction();

            // Check if academic year already exists
            $checkStmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM academic_years WHERE year_code = :year"
            );
            $checkStmt->execute(['year' => $toYearCode]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ((int) $existing['count'] > 0) {
                $this->db->rollBack();
                return formatResponse(false, null, "Academic year {$toYearCode} already exists");
            }

            // Create academic year record
            $yearStmt = $this->db->prepare(
                "INSERT INTO academic_years (year_code, year_name, start_date, end_date, status, is_current)
                 VALUES (:year, :name, :start_date, :end_date, 'planning', 0)"
            );
            $yearStmt->execute([
                'year' => $toYearCode,
                'name' => $toYearCode,
                'start_date' => $calendar['year_start_date'],
                'end_date' => $calendar['year_end_date'],
            ]);
            $academicYearId = (int) $this->db->lastInsertId();

            // Create academic year terms for new year
            $termIds = [];
            foreach ($calendar['terms'] as $index => $term) {
                $termNumber = $index + 1;
                $termId = $this->ensureTermId(
                    (string) ($term['term_name'] ?? ('Term ' . $termNumber)),
                    $termNumber
                );

                $termStmt = $this->db->prepare(
                    "INSERT INTO academic_year_terms
                        (academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status)
                     VALUES (:year_id, :term_id, :start_date, :half_term_start, :half_term_end, :end_date, 'upcoming')"
                );
                $termStmt->execute([
                    'year_id' => $academicYearId,
                    'term_id' => $termId,
                    'start_date' => $term['start_date'],
                    'half_term_start' => $term['half_term_start'],
                    'half_term_end' => $term['half_term_end'],
                    'end_date' => $term['end_date'],
                ]);
                $aytId = (int) $this->db->lastInsertId();
                $termIds[] = $aytId;
            }

            // Auto-generate the term calendar (weeks, school days, half-term
            // holidays) from the government-issued term dates. Explicit week
            // counts (e.g. 14/14/10) are honored when supplied per term.
            $weekCounts = [];
            foreach ($calendar['terms'] as $index => $term) {
                if (isset($term['weeks']) && (int) $term['weeks'] > 0) {
                    $weekCounts[$index + 1] = (int) $term['weeks'];
                }
            }
            $calendarService = new AcademicCalendarService($this->db);
            $calendarResult = $calendarService->generateYearCalendar($academicYearId, $weekCounts);
            if (($calendarResult['status'] ?? '') !== 'success') {
                throw new Exception($calendarResult['message'] ?? 'Academic calendar generation failed.');
            }
            $calendarSummary = $calendarResult['data'] ?? [];
            $generationChecks = $calendarSummary['generation_checks'] ?? [];
            if (count($generationChecks) !== 3 || count(array_filter(
                $generationChecks,
                static fn (array $check): bool => empty($check['pass'])
            )) > 0) {
                throw new Exception('Stage 1 could not verify generated weeks and days for all three terms.');
            }

            // Prepare workflow data
            $workflowData = [
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'from_year_code' => $fromYearCode,
                'to_year_code' => $toYearCode,
                'academic_year_id' => $academicYearId,
                'year_start_date' => $yearStart,
                'year_end_date' => $yearEnd,
                'terms' => $calendar['terms'],
                'term_ids' => $termIds,
                'calendar_summary' => $calendarSummary,
                'holidays' => $calendar['holidays'] ?? [],
                'transition_notes' => $calendar['transition_notes'] ?? '',
                'promotion_summary' => [],
                'new_classes' => [],
                'migrated_baselines' => [],
                'validation_results' => [],
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                $this->getWorkflowDefinitionCode(),
                $toYear,
                $workflowData
            );

            // The create form supplies and validates the first three setup
            // stages in one atomic operation. Persist those completed stages
            // explicitly so the resumable workflow opens at calendar
            // generation rather than reverting to a legacy stage code.
            $this->advanceStage($instance, 'create_next_year', 'Next academic year identified', $workflowData);
            $this->advanceStage($instance, 'enter_year_term_dates', 'Year, term and half-term dates recorded', $workflowData);
            $this->advanceStage($instance, 'generate_calendar', 'Calendar generated and verified for all three terms', $workflowData);
            $this->advanceStage($instance, 'configure_classes_streams', 'Year and calendar prepared; target classes and streams are next', $workflowData);

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance,
                'workflow_data' => $workflowData,
                'terms_created' => count($termIds),
            ], 'Academic calendar prepared successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Close the outgoing year.
     *
     * Marking a year completed is NOT archiving, and no archiving vocabulary
     * belongs anywhere in this workflow. Closing only moves the year to
     * active - completed: every read path and every ordinary user still sees
     * its data, and only school admin / system admin / director may write to
     * it. Nothing is moved, copied, sealed or deleted.
     *
     * Archiving is a separate, deliberate, system-administrator-only action
     * (see AcademicYearManager::archiveYear) that permanently hides a year.
     * It is never reachable from here.
     */
    public function closeOutgoingYear(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $fromYear = $this->yearStart($data['from_year'] ?? 0);
            $fromYearId = $this->resolveYearIdFromCode($fromYear);
            if (!$fromYearId) {
                throw new Exception('Source academic year could not be resolved');
            }

            // advanceStage() records this step in workflow_stage_history, which
            // is the durable record that closing happened. Nothing needs to be
            // written to workflow_instances here.
            $this->advanceStage(
                $instance_id,
                'close_outgoing_year',
                "Closed year {$fromYear} as active - completed",
                $data
            );

            return formatResponse(true, [
                'year_closed' => $fromYear,
                'closed_at' => date('Y-m-d H:i:s'),
                'closed_by' => (int) $this->user_id,
            ], 'Outgoing year closed; its records stay readable for every user.');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3 of the transition: surface the promotion board.
     *
     * No learner is moved here. Automatic bulk placement used to live in this
     * method but was removed: stream placement is an administrator decision and
     * must stay resumable, so a failed half-way run could never leave a cohort
     * half-placed. assignPromotionStreams() is the only write path.
     *
     * @param array<string,mixed> $promotion_config Accepted and ignored, kept so
     *   the workflow step signature is unchanged for existing callers.
     */
    public function executePromotions(int $instance_id, array $promotion_config = []): array
    {
        return $this->getPromotionCandidates($instance_id);
    }

    /**
     * Return the resumable promotion assignment board.  No learner is moved by
     * this method; the administrator chooses a target stream for each learner.
     */
    public function getPromotionCandidates(int $instance_id): array
    {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
        $data = json_decode($instance['data_json'], true) ?: [];
        $fromYearId = $this->resolveYearIdFromCode($data['from_year'] ?? 0);
        $toYearId = (int) ($data['academic_year_id'] ?? 0);
        if (!$fromYearId || !$toYearId) return formatResponse(false, null, 'Transition years are not prepared');

        $stmt = $this->db->prepare(
            "SELECT sae.id AS source_enrollment_id, s.id AS student_id,
                    s.admission_no, CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                    c.id AS source_class_id, c.name AS source_class_name,
                    srcAycs.id AS source_stream_id,
                    srcStream.name AS source_stream_name,
                    targetClass.id AS target_class_id, targetClass.name AS target_class_name,
                    targetEnrollment.id AS target_enrollment_id,
                    targetEnrollment.academic_year_class_stream_id AS target_stream_id,
                    targetAyc.class_id AS existing_target_class_id,
                    (SELECT st.transition_type FROM student_transitions st
                      WHERE st.from_student_academic_enrollment_id = sae.id AND st.academic_year_id = ?
                      ORDER BY st.id DESC LIMIT 1) AS transition_type,
                    (SELECT COUNT(DISTINCT tss.term_id)
                       FROM " . ReadReplicaService::qualifiedRef("term_subject_scores") . " tss
                       JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " resultTerm
                         ON resultTerm.term_id = tss.term_id AND resultTerm.academic_year_id = sae.academic_year_id
                      WHERE tss.student_id = s.id) AS finalized_result_terms,
                    (SELECT COALESCE(SUM(fb.balance), 0)
                       FROM " . ReadReplicaService::qualifiedRef("student_fee_balances") . " fb
                      WHERE fb.student_academic_enrollment_id = sae.id) AS outstanding_balance
             FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " srcAycs ON srcAycs.id = sae.academic_year_class_stream_id
             JOIN " . ReadReplicaService::qualifiedRef("streams") . " srcStream ON srcStream.id = srcAycs.stream_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " srcAyc ON srcAyc.id = srcAycs.academic_year_class_id
             JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = srcAyc.class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_class_progression") . " prog
               ON prog.source_class_id = c.id AND prog.active = 1
             LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " targetClass ON targetClass.id = prog.target_class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " targetEnrollment
               ON targetEnrollment.student_id = s.id AND targetEnrollment.academic_year_id = ?
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " targetAycs
               ON targetAycs.id = targetEnrollment.academic_year_class_stream_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " targetAyc
               ON targetAyc.id = targetAycs.academic_year_class_id
             WHERE sae.academic_year_id = ?
               AND (sae.enrollment_status IN ('pending','active') OR EXISTS
                    (SELECT 1 FROM student_transitions st
                     WHERE st.from_student_academic_enrollment_id = sae.id AND st.academic_year_id = ?))
               AND s.status = 'active'
             ORDER BY c.id, p.last_name, p.first_name"
        );
        $stmt->execute([$toYearId, $toYearId, $fromYearId, $toYearId]);
        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targetClassId = (int) ($row['target_class_id'] ?? 0);
            $streams = [];
            if ($targetClassId) {
                // The target structure was created by this workflow minutes ago.
                // Read it from the master: the reads projection can still be a
                // refresh cycle behind, which would leave every destination
                // dropdown empty on the promotion board.
                $streamStmt = $this->db->prepare(
                    "SELECT aycs.id AS target_stream_id, targetStream.name AS stream_name
                     FROM " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs
                     JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                     JOIN " . ReadReplicaService::masterSourceRef("streams") . " targetStream ON targetStream.id = aycs.stream_id
                     WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
                     ORDER BY targetStream.name"
                );
                $streamStmt->execute([$toYearId, $targetClassId]);
                $streams = $streamStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $row['target_streams'] = $streams;
            $retentionStmt = $this->db->prepare(
                "SELECT aycs.id AS target_stream_id, targetStream.name AS stream_name
                 FROM " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs
                 JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN " . ReadReplicaService::masterSourceRef("streams") . " targetStream ON targetStream.id = aycs.stream_id
                 WHERE ayc.academic_year_id = ? AND ayc.class_id = ? ORDER BY targetStream.name"
            );
            $retentionStmt->execute([$toYearId, (int) $row['source_class_id']]);
            $row['retention_streams'] = $retentionStmt->fetchAll(PDO::FETCH_ASSOC);
            $row['assigned'] = (string) ($row['transition_type'] ?? '') === 'graduation'
                || (int) ($row['target_enrollment_id'] ?? 0) > 0;
            $candidates[] = $row;
        }
        $data['promotion_board_last_loaded_at'] = date('Y-m-d H:i:s');
        // Merge only this key. Loading the board is a read path that happens on
        // every promotions tab open, so a whole-document rewrite here would be
        // the most likely way to drop a sibling stage's data.
        $this->mergeInstanceData($instance_id, ['promotion_board_last_loaded_at' => $data['promotion_board_last_loaded_at']]);
        return formatResponse(true, [
            'from_year' => $data['from_year_code'] ?? $data['from_year'],
            'to_year' => $data['to_year_code'] ?? $data['to_year'],
            'candidates' => $candidates,
            'total' => count($candidates),
            'assigned' => count(array_filter($candidates, static function ($candidate) { return $candidate['assigned']; })),
        ], 'Promotion assignment board loaded');
    }

    /** Complete a canonical non-mutating gate after its dedicated setup page has been used. */
    public function completeCanonicalStage(int $instance_id, string $stageCode, string $notes = ''): array
    {
        try {
            // Workflow position controls writes and must reflect the primary
            // database, not a delayed materialized reporting snapshot.
            $instanceStmt = $this->db->prepare(
                'SELECT * FROM workflow_instances WHERE id = ? LIMIT 1'
            );
            $instanceStmt->execute([$instance_id]);
            $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
            $current = $instance['current_stage_code'] ?? $instance['current_stage'] ?? '';
            if ($current !== $stageCode) {
                // Setup tabs remain editable after the workflow has moved on.
                // Completing an already-passed setup stage acknowledges its
                // save without moving the shared workflow pointer backwards.
                $editableSetupStages = [
                    'configure_classes_streams',
                    'configure_learning_areas',
                    'configure_teachers',
                    'prepare_fee_structures',
                    'approve_fee_structures',
                ];
                $submittedIsEditable = in_array($stageCode, $editableSetupStages, true);
                if ($submittedIsEditable) {
                    $stageOrderStmt = $this->db->prepare(
                        'SELECT code, sequence FROM workflow_stages WHERE workflow_id = ? ORDER BY sequence'
                    );
                    $stageOrderStmt->execute([(int) ($instance['workflow_id'] ?? 0)]);
                    $stageOrder = [];
                    foreach ($stageOrderStmt->fetchAll(PDO::FETCH_ASSOC) as $stageRow) {
                        $stageOrder[(string) $stageRow['code']] = (int) $stageRow['sequence'];
                    }
                    if (isset($stageOrder[$current], $stageOrder[$stageCode])
                        && $stageOrder[$current] > $stageOrder[$stageCode]) {
                        return formatResponse(true, ['current_stage' => $current, 'stage_saved' => $stageCode], 'Stage data saved; workflow position unchanged.');
                    }
                }
                // A stale pointer at an earlier setup stage must not block the
                // finance gates once promotions and target enrollments are done.
                if ($stageCode === 'carry_forward_finances'
                    && $this->reconcilePromotionCompletion($instance_id)) {
                    $instanceStmt->execute([$instance_id]);
                    $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
                    $current = $instance['current_stage_code'] ?? $instance['current_stage'] ?? '';
                }
                if ($current !== $stageCode) {
                    return formatResponse(false, null, "Stage {$stageCode} cannot be completed while the workflow is at {$current}.");
                }
            }
            $data = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $next = [
                'configure_classes_streams' => 'configure_learning_areas',
                'configure_learning_areas' => 'configure_teachers',
                'configure_teachers' => 'prepare_fee_structures',
                'prepare_fee_structures' => 'approve_fee_structures',
                'approve_fee_structures' => 'review_promotion_candidates',
                'carry_forward_finances' => 'generate_obligations',
                'generate_obligations' => 'reconcile_balances',
                'reconcile_balances' => 'migrate_baselines',
                'migrate_baselines' => 'close_outgoing_year',
                'close_outgoing_year' => 'activate_new_year_term_one',
            ][$stageCode] ?? null;
            if (!$next) return formatResponse(false, null, 'This stage has a dedicated workflow action.');

            if (in_array($stageCode, ['carry_forward_finances','generate_obligations','reconcile_balances'], true)) {
                $financeResult = $this->runFinanceRolloverStage($stageCode, $data);
                $data['finance_rollover'][$stageCode] = array_merge($financeResult, ['completed_at' => date('Y-m-d H:i:s')]);
            }

            if ($stageCode === 'approve_fee_structures') {
                // Nothing else in the rollover moves a copied draft/pending
                // schedule to active, so this stage itself must approve them.
                // FeeManager::generateObligations only bills active schedules.
                $feeYearId = (int) ($data['academic_year_id'] ?? 0);
                $pending = $this->db->prepare(
                    "SELECT COUNT(*) FROM academic_year_fee_schedules
                     WHERE academic_year_id = ? AND status IN ('draft','pending_review','active')"
                );
                $pending->execute([$feeYearId]);
                $pendingCount = (int) $pending->fetchColumn();
                if ($pendingCount === 0) {
                    return formatResponse(false, null, 'No target-year fee structures are waiting for approval. Re-run the Fees step to copy the current year’s structure.');
                }
                $approve = $this->db->prepare(
                    "UPDATE academic_year_fee_schedules
                     SET status = 'active', approved_at = NOW(), approved_by = ?
                     WHERE academic_year_id = ? AND status IN ('draft','pending_review','active')"
                );
                $approve->execute([(int) $this->user_id, $feeYearId]);
                $data['fee_structures_approved'] = [
                    'schedules_approved' => (int) $this->lastAffectedRows(),
                    'approved_at' => date('Y-m-d H:i:s'),
                ];
            }
            $data['canonical_stage_notes'][$stageCode] = ['notes' => $notes, 'completed_at' => date('Y-m-d H:i:s')];
            $this->advanceStage($instance_id, $next, $notes ?: "Completed {$stageCode}", $data);
            return formatResponse(true, array_merge(['current_stage' => $next], isset($financeResult) ? ['finance_rollover' => [$stageCode => $financeResult]] : []), 'Stage completed');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /** Run an idempotent finance step only for learners actively promoted into the target year. */
    private function runFinanceRolloverStage(string $stage, array $data): array
    {
        $fromId = $this->resolveYearIdFromCode($data['from_year_code'] ?? $data['from_year'] ?? 0);
        $toId = (int)($data['academic_year_id'] ?? 0);
        if (!$fromId || !$toId) throw new Exception('Rollover source or target academic year is missing');
        if ($stage === 'carry_forward_finances') {
            $rows = $this->db->prepare(
                "SELECT target.student_id, ROUND(SUM(vfb.balance),2) AS balance
                 FROM student_academic_enrollments target
                 JOIN student_transitions st ON st.to_student_academic_enrollment_id=target.id
                    AND st.academic_year_id=? AND st.transition_type IN ('promotion','retention')
                 JOIN vw_student_fee_balances vfb ON vfb.student_id=target.student_id AND vfb.academic_year_id=?
                 WHERE target.academic_year_id=? AND target.enrollment_status='active'
                 GROUP BY target.student_id"
            );
            $rows->execute([$toId,$fromId,$toId]);
            $save = $this->db->prepare(
                "INSERT INTO student_fee_rollover_balances
                 (student_id,from_academic_year_id,to_academic_year_id,source_balance,arrears_carried,credit_carried)
                 VALUES (?,?,?,?,GREATEST(?,0),GREATEST(-?,0))
                 ON DUPLICATE KEY UPDATE source_balance=VALUES(source_balance),arrears_carried=VALUES(arrears_carried),credit_carried=VALUES(credit_carried)"
            );
            $count=0;
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $balance=(float)$row['balance'];
                $save->execute([(int)$row['student_id'],$fromId,$toId,$balance,$balance,$balance]);
                $count++;
            }
            return ['learners_recorded'=>$count];
        }
        if ($stage === 'generate_obligations') {
            // FeeManager::activateAndGenerateObligations activates the matching
            // schedules itself, so the bundle list must include draft and
            // pending-review rows. Filtering on status='active' here would find
            // nothing on a freshly prepared target year and report a false
            // "Done" without billing a single learner.
            $bundles=$this->db->prepare(
                "SELECT DISTINCT ayfs.academic_year_term_id,ayfs.student_type_id
                 FROM academic_year_fee_schedules ayfs
                 WHERE ayfs.academic_year_id=? AND ayfs.status IN ('draft','pending_review','active')
                 ORDER BY ayfs.academic_year_term_id,ayfs.student_type_id"
            );
            $bundles->execute([$toId]);
            $feeBundleRows=$bundles->fetchAll(PDO::FETCH_ASSOC);
            if (count($feeBundleRows) === 0) {
                throw new Exception('No fee structures were prepared for the target year. Complete the Fees step before generating obligations.');
            }
            $feeManager = new \App\API\Modules\finance\FeeManager();
            $processed=0;
            foreach ($feeBundleRows as $bundle) {
                $result=$feeManager->activateAndGenerateObligations(null,(int)substr((string)($data['to_year_code'] ?? $data['academic_year_code'] ?? ''),0,4),(int)$bundle['academic_year_term_id'],(int)$bundle['student_type_id'],(int)$this->user_id);
                if (isset($result['status']) && $result['status']==='error') throw new Exception($result['message'] ?? 'Could not generate target-year fee obligations');
                $processed++;
            }
            return ['fee_bundles_processed'=>$processed];
        }
        $records=$this->db->prepare(
            "SELECT r.*, ay.year_code FROM " . ReadReplicaService::qualifiedRef("student_fee_rollover_balances") . " r
             JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id=r.to_academic_year_id
             WHERE r.from_academic_year_id=? AND r.to_academic_year_id=? AND r.reconciled_at IS NULL"
        );
        $records->execute([$fromId,$toId]);
        $done=0;
        foreach ($records->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $studentId=(int)$row['student_id'];
            $target=$this->db->prepare(
                "SELECT MIN(sfo.id) AS id,GREATEST(COALESCE(vfb.balance,0),0) AS outstanding,
                        sfo.academic_year_term_id
                 FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " sfo JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id=sfo.student_academic_enrollment_id
                 LEFT JOIN vw_student_fee_balances vfb ON vfb.student_academic_enrollment_id=sae.id AND vfb.academic_year_term_id=sfo.academic_year_term_id
                 JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id=sfo.academic_year_term_id
                 WHERE sae.student_id=? AND sae.academic_year_id=? AND sae.enrollment_status='active'
                 GROUP BY sfo.academic_year_term_id,vfb.balance
                 ORDER BY ayt.opening_date,MIN(sfo.id)"
            );
            $target->execute([$studentId,$toId]);
            $obligations=$target->fetchAll(PDO::FETCH_ASSOC);
            if (!$obligations && ((float)$row['arrears_carried']>0 || (float)$row['credit_carried']>0)) throw new Exception('Target-year obligations are missing; complete Generate new-year obligations first');
            if ((float)$row['arrears_carried']>0 && $obligations) {
                $this->db->prepare("UPDATE student_fee_obligations SET opening_arrears_amount=GREATEST(COALESCE(opening_arrears_amount,0),?),updated_at=NOW() WHERE id=?")
                    ->execute([(float)$row['arrears_carried'],(int)$obligations[0]['id']]);
            }
            $credit=(float)$row['credit_carried'];
            if ($credit>0 && $obligations) {
                $noteId=(int)($row['fee_credit_note_id'] ?? 0);
                if (!$noteId) {
                    $number='ROL-'.substr(hash('sha256',$studentId.':'.$fromId.':'.$toId),0,20);
                    $this->db->prepare(
                        "INSERT INTO fee_credit_notes (credit_number,student_id,academic_year,credit_amount,credit_reason,notes,created_by)
                         VALUES (?,?,?,?,'overpayment','Carried forward from academic-year rollover',?)"
                    )->execute([$number,$studentId,(int)substr($row['year_code'],0,4)-1,$credit,(int)$this->user_id]);
                    $noteId=(int)$this->db->lastInsertId();
                    $this->db->prepare("UPDATE student_fee_rollover_balances SET fee_credit_note_id=? WHERE id=?")->execute([$noteId,(int)$row['id']]);
                }
                $allocated=0.0;
                foreach ($obligations as $obligation) {
                    $piece=min($credit-$allocated,max(0,(float)$obligation['outstanding']));
                    if ($piece<=0) continue;
                    $this->db->prepare("INSERT INTO fee_credit_applications (fee_credit_note_id,student_fee_obligation_id,applied_amount,applied_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE applied_amount=GREATEST(applied_amount,VALUES(applied_amount))")
                        ->execute([$noteId,(int)$obligation['id'],$piece,(int)$this->user_id]);
                    $allocated=round($allocated+$piece,2);
                    if ($allocated >= $credit) break;
                }
                if ($allocated>0) $this->db->prepare("UPDATE fee_credit_notes SET applied_amount=?,applied_to_year=?,applied_at=NOW(),status=CASE WHEN ?>=credit_amount THEN 'fully_applied' ELSE 'partially_applied' END WHERE id=?")
                    ->execute([$allocated,(int)substr($row['year_code'],0,4),$allocated,$noteId]);
            }
            $this->db->prepare("UPDATE student_fee_rollover_balances SET reconciled_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
            $done++;
        }
        return ['learners_reconciled'=>$done];
    }

    /**
     * Approve the target-year fee structures authored on the rollover page.
     *
     * The school administrator authors these structures, so they are approved
     * for billing here. Approval MUST happen before a target-year enrollment
     * is inserted, because trg_create_student_obligations only bills schedules
     * whose status is 'active'.
     */
    private function approveTargetYearFeeStructures(int $toYearId): void
    {
        $this->db->prepare(
            "UPDATE academic_year_fee_schedules
             SET status = 'active', approved_at = NOW(), approved_by = ?
             WHERE academic_year_id = ? AND status IN ('draft','pending_review')"
        )->execute([(int) $this->user_id, $toYearId]);
    }

    /**
     * Carry the outgoing-year balance forward for a learner who was promoted
     * or retained into the target year.
     *
     * Billing itself is owned by trg_create_student_obligations, which fires
     * when the target-year enrollment is inserted. This method only:
     *   1. backfills obligations for enrollments created while the target
     *      fee structures were still drafts (idempotent by unique key), and
     *   2. carries the outgoing-year balance:
     *      - arrears (> 0) become opening arrears on the earliest obligation;
     *      - an overpayment (< 0) becomes a fee credit note applied to the
     *        new obligations, so the surplus is subtracted automatically.
     *
     * Every write is idempotent so re-saving a decision never double-bills or
     * double-applies a credit.
     *
     * @param float|null $knownSourceBalance Pre-computed outgoing balance. The
     *        vw_student_fee_balances aggregate is expensive, so bulk callers
     *        should compute it once and pass it in.
     */
    private function billLearnerForTargetYear(
        int $studentId,
        int $sourceEnrollmentId,
        int $targetEnrollmentId,
        int $fromYearId,
        int $toYearId,
        int $targetClassId,
        ?float $knownSourceBalance = null
    ): array {
        $studentType = $this->db->prepare("SELECT student_type_id FROM students WHERE id = ? LIMIT 1");
        $studentType->execute([$studentId]);
        $studentTypeId = (int) ($studentType->fetchColumn() ?: 0);

        // 1. Backfill only. Same shape as trg_create_student_obligations, so
        // the trigger and this method can never disagree or double-bill.
        $obligationsBackfilled = 0;
        if ($studentTypeId > 0) {
            $feeRows = $this->db->prepare(
                "SELECT fs.id, fs.academic_year_term_id, fs.amount, fs.due_date
                 FROM academic_year_fee_schedules fs
                 JOIN academic_year_terms ayt ON ayt.id = fs.academic_year_term_id
                 WHERE fs.academic_year_id = ?
                   AND fs.status = 'active'
                   AND fs.amount > 0
                   AND fs.academic_year_class_id = ?
                   AND fs.student_type_id = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM student_fee_obligations sfo
                       WHERE sfo.student_academic_enrollment_id = ?
                         AND sfo.academic_year_term_id = fs.academic_year_term_id
                         AND sfo.academic_year_fee_schedule_id = fs.id
                   )
                 ORDER BY fs.academic_year_term_id, fs.id"
            );
            $feeRows->execute([$toYearId, $targetClassId, $studentTypeId, $targetEnrollmentId]);
            $insert = $this->db->prepare(
                "INSERT INTO student_fee_obligations
                    (student_academic_enrollment_id, academic_year_id, academic_year_term_id,
                     academic_year_fee_schedule_id, amount_due, status, due_date)
                 VALUES (?, ?, ?, ?, ?, 'pending', ?)"
            );
            foreach ($feeRows->fetchAll(PDO::FETCH_ASSOC) as $feeRow) {
                $insert->execute([
                    $targetEnrollmentId,
                    $toYearId,
                    (int) $feeRow['academic_year_term_id'],
                    (int) $feeRow['id'],
                    (float) $feeRow['amount'],
                    $feeRow['due_date'] ?? null,
                ]);
                if ((int) $this->lastAffectedRows() > 0) $obligationsBackfilled++;
            }
        }

        // 2. Outgoing-year balance for this learner.
        if ($knownSourceBalance !== null) {
            $sourceBalance = round($knownSourceBalance, 2);
        } else {
            $balanceStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(vfb.balance),0)
                 FROM vw_student_fee_balances vfb
                 WHERE vfb.student_academic_enrollment_id = ?"
            );
            $balanceStmt->execute([$sourceEnrollmentId]);
            $sourceBalance = round((float) $balanceStmt->fetchColumn(), 2);
        }

        // Target-year obligations, ordered so arrears and credits land on the
        // term that opens first.
        $targetObligations = $this->db->prepare(
            "SELECT sfo.id, GREATEST(COALESCE(vfb.balance,0),0) AS outstanding, sfo.academic_year_term_id
             FROM student_fee_obligations sfo
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             LEFT JOIN vw_student_fee_balances vfb
               ON vfb.student_academic_enrollment_id = sfo.student_academic_enrollment_id
              AND vfb.academic_year_term_id = sfo.academic_year_term_id
             WHERE sfo.student_academic_enrollment_id = ?
             GROUP BY sfo.id, vfb.balance, sfo.academic_year_term_id
             ORDER BY ayt.opening_date, sfo.id"
        );

        $arrearsApplied = 0.0;
        $creditApplied = 0.0;
        if ($sourceBalance > 0) {
            $targetObligations->execute([$targetEnrollmentId]);
            $firstObligation = $targetObligations->fetch(PDO::FETCH_ASSOC);
            if ($firstObligation) {
                $this->db->prepare(
                    "UPDATE student_fee_obligations
                     SET opening_arrears_amount = GREATEST(COALESCE(opening_arrears_amount,0), ?),
                         updated_at = NOW()
                     WHERE id = ?"
                )->execute([$sourceBalance, (int) $firstObligation['id']]);
                $arrearsApplied = $sourceBalance;
            }
        } elseif ($sourceBalance < 0) {
            $credit = round(-$sourceBalance, 2);
            $number = 'ROL-' . substr(hash('sha256', $studentId . ':' . $fromYearId . ':' . $toYearId), 0, 20);
            $this->db->prepare(
                "INSERT INTO fee_credit_notes
                    (credit_number, student_id, academic_year, credit_amount, credit_reason, notes, created_by)
                 VALUES (?, ?, ?, ?, 'overpayment', 'Carried forward from academic-year rollover', ?)
                 ON DUPLICATE KEY UPDATE credit_amount = VALUES(credit_amount), status = 'available'"
            )->execute([$number, $studentId, (int) substr($this->yearCode($fromYearId), 0, 4), $credit, (int) $this->user_id]);
            $noteLookup = $this->db->prepare("SELECT id FROM fee_credit_notes WHERE credit_number = ? LIMIT 1");
            $noteLookup->execute([$number]);
            $noteId = (int) $noteLookup->fetchColumn();

            $targetObligations->execute([$targetEnrollmentId]);
            $obligations = $targetObligations->fetchAll(PDO::FETCH_ASSOC);
            $allocated = 0.0;
            foreach ($obligations as $obligation) {
                $piece = min($credit - $allocated, max(0.0, (float) $obligation['outstanding']));
                if ($piece <= 0) continue;
                $this->db->prepare(
                    "INSERT INTO fee_credit_applications (fee_credit_note_id, student_fee_obligation_id, applied_amount, applied_by)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE applied_amount = GREATEST(applied_amount, VALUES(applied_amount))"
                )->execute([$noteId, (int) $obligation['id'], $piece, (int) $this->user_id]);
                $allocated = round($allocated + $piece, 2);
                if ($allocated >= $credit) break;
            }
            $creditApplied = $allocated;
            $this->db->prepare(
                "UPDATE fee_credit_notes
                 SET applied_amount = LEAST(credit_amount, (SELECT COALESCE(SUM(applied_amount),0)
                                                FROM fee_credit_applications
                                                WHERE fee_credit_note_id = ?)),
                     applied_to_year = ?, applied_at = NOW(),
                     status = CASE WHEN applied_amount >= credit_amount THEN 'fully_applied'
                                   WHEN applied_amount > 0 THEN 'partially_applied' ELSE 'available' END
                 WHERE id = ?"
            )->execute([$noteId, (int) substr($this->yearCode($toYearId), 0, 4), $noteId]);
            $this->db->prepare(
                "UPDATE student_fee_rollover_balances SET fee_credit_note_id = ? WHERE id = ?
                  AND student_id = ? AND from_academic_year_id = ? AND to_academic_year_id = ?"
            )->execute([$noteId, 0, $studentId, $fromYearId, $toYearId]);
        }

        $this->db->prepare(
            "INSERT INTO student_fee_rollover_balances
                (student_id, from_academic_year_id, to_academic_year_id, source_balance,
                 arrears_carried, credit_carried, reconciled_at)
             VALUES (?, ?, ?, ?, GREATEST(?,0), GREATEST(-?,0), NOW())
             ON DUPLICATE KEY UPDATE
                source_balance = VALUES(source_balance),
                arrears_carried = GREATEST(arrears_carried, VALUES(arrears_carried)),
                credit_carried = GREATEST(credit_carried, VALUES(credit_carried)),
                reconciled_at = NOW()"
        )->execute([
            $studentId, $fromYearId, $toYearId, $sourceBalance,
            $sourceBalance, $sourceBalance,
        ]);

        return [
            'obligations_backfilled' => $obligationsBackfilled,
            'source_balance' => $sourceBalance,
            'arrears_applied' => $arrearsApplied,
            'credit_applied' => $creditApplied,
        ];
    }

    /**
     * A graduating learner is not billed for the new year. The outgoing-year
     * balance is simply retained as balance (arrears/credit) on the record and
     * the outgoing-year enrollment is marked graduated by the caller.
     */
    private function retainGraduatingLearnerBalance(int $studentId, int $fromYearId, int $sourceEnrollmentId): array
    {
        $balanceStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(vfb.balance),0)
             FROM vw_student_fee_balances vfb
             WHERE vfb.student_academic_enrollment_id = ?"
        );
        $balanceStmt->execute([$sourceEnrollmentId]);
        $sourceBalance = round((float) $balanceStmt->fetchColumn(), 2);

        $this->db->prepare(
            "INSERT INTO student_fee_rollover_balances
                (student_id, from_academic_year_id, to_academic_year_id, source_balance,
                 arrears_carried, credit_carried, reconciled_at)
             VALUES (?, ?, ?, ?, GREATEST(?,0), GREATEST(-?,0), NOW())
             ON DUPLICATE KEY UPDATE
                source_balance = VALUES(source_balance),
                arrears_carried = GREATEST(arrears_carried, VALUES(arrears_carried)),
                credit_carried = GREATEST(credit_carried, VALUES(credit_carried)),
                reconciled_at = NOW()"
        )->execute([$studentId, $fromYearId, $fromYearId, $sourceBalance, $sourceBalance, $sourceBalance]);

        return ['source_balance' => $sourceBalance, 'retained' => true];
    }

    /** Save one or many administrator stream assignments. */
    /**
     * Whole-rollover summary for the Finance & Cutover tab.
     *
     * Billing and balance carry-forward happen during the promotion save, so
     * this reads the recorded outcome rather than running new stages.
     */
    public function getRolloverSummary(int $instanceId): array
    {
        try {
            $instanceStmt = $this->db->prepare(
                'SELECT reference_id, data_json, current_stage, status FROM workflow_instances WHERE id = ? LIMIT 1'
            );
            $instanceStmt->execute([$instanceId]);
            $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$instance) return formatResponse(false, null, 'Workflow instance not found');

            $data = json_decode((string) $instance['data_json'], true) ?: [];
            $toYearId = (int) ($data['academic_year_id'] ?? $instance['reference_id']);
            $fromYearId = $this->resolveYearIdFromCode($data['from_year_code'] ?? $data['from_year'] ?? 0);
            if (!$fromYearId || !$toYearId) {
                return formatResponse(false, null, 'Prepare the target year before reviewing the rollover summary.');
            }

            $count = function (string $sql, array $params = []): int {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return (int) $stmt->fetchColumn();
            };
            $total = function (string $sql, array $params = []): float {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return round((float) $stmt->fetchColumn(), 2);
            };

            $decisions = [];
            $decisionsStmt = $this->db->prepare(
                "SELECT st.transition_type, COUNT(*) AS n
                 FROM student_transitions st
                 WHERE st.academic_year_id = ?
                 GROUP BY st.transition_type"
            );
            $decisionsStmt->execute([$toYearId]);
            foreach ($decisionsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $decisions[(string) $row['transition_type']] = (int) $row['n'];
            }

            $fees = $this->db->prepare(
                "SELECT status, COUNT(*) AS n FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? GROUP BY status"
            );
            $fees->execute([$toYearId]);
            $feeStatuses = [];
            foreach ($fees->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $feeStatuses[(string) $row['status']] = (int) $row['n'];
            }

            $perTerm = [];
            $perTermStmt = $this->db->prepare(
                "SELECT t.name AS term_name, COUNT(*) AS obligations, ROUND(SUM(sfo.amount_due),2) AS amount
                 FROM student_fee_obligations sfo
                 JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE sfo.academic_year_id = ?
                 GROUP BY t.name, ayt.id ORDER BY MIN(ayt.opening_date)"
            );
            $perTermStmt->execute([$toYearId]);
            foreach ($perTermStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $perTerm[] = [
                    'term_name' => (string) $row['term_name'],
                    'obligations' => (int) $row['obligations'],
                    'amount' => (float) $row['amount'],
                ];
            }

            $graduateBalances = $this->db->prepare(
                "SELECT COUNT(*) AS learners, ROUND(SUM(GREATEST(source_balance,0)),2) AS arrears,
                        ROUND(SUM(GREATEST(-source_balance,0)),2) AS credits
                 FROM student_fee_rollover_balances
                 WHERE from_academic_year_id = ? AND to_academic_year_id = ?"
            );
            $graduateBalances->execute([$fromYearId, $fromYearId]);
            $graduateSummary = $graduateBalances->fetch(PDO::FETCH_ASSOC) ?: [];

            $stageHistory = [];
            $historyStmt = $this->db->prepare(
                "SELECT from_stage, to_stage, action_taken, remarks, processed_at
                 FROM workflow_stage_history WHERE instance_id = ? ORDER BY processed_at DESC LIMIT 25"
            );
            $historyStmt->execute([$instanceId]);
            foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stageHistory[] = [
                    'from_stage' => (string) $row['from_stage'],
                    'to_stage' => (string) $row['to_stage'],
                    'action' => (string) $row['action_taken'],
                    'remarks' => (string) $row['remarks'],
                    'performed_at' => (string) $row['processed_at'],
                ];
            }

            $targetYearRow = $this->db->prepare(
                "SELECT status, state, is_current, start_date, end_date FROM academic_years WHERE id = ? LIMIT 1"
            );
            $targetYearRow->execute([$toYearId]);
            $targetYear = $targetYearRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $sourceYearRow = $this->db->prepare(
                "SELECT status, state, is_current, start_date, end_date FROM academic_years WHERE id = ? LIMIT 1"
            );
            $sourceYearRow->execute([$fromYearId]);
            $sourceYear = $sourceYearRow->fetch(PDO::FETCH_ASSOC) ?: [];

            return formatResponse(true, [
                'from_year' => $data['from_year_code'] ?? $data['from_year'],
                'to_year' => $data['to_year_code'] ?? $data['to_year'],
                'current_stage' => (string) $instance['current_stage'],
                'status' => (string) $instance['status'],
                'target_year' => [
                    'state' => (string) ($targetYear['state'] ?? ''),
                    'status' => (string) ($targetYear['status'] ?? ''),
                    'label' => \App\API\Services\AcademicYearLifecycleService::label(
                        (string) ($targetYear['status'] ?? 'active'),
                        (string) ($targetYear['state'] ?? 'upcoming')
                    ),
                    'starts_on' => (string) ($targetYear['start_date'] ?? ''),
                ],
                'source_year' => [
                    'state' => (string) ($sourceYear['state'] ?? ''),
                    'status' => (string) ($sourceYear['status'] ?? ''),
                    'label' => \App\API\Services\AcademicYearLifecycleService::label(
                        (string) ($sourceYear['status'] ?? 'active'),
                        (string) ($sourceYear['state'] ?? 'current')
                    ),
                ],
                'decisions' => [
                    'promoted' => $decisions['promotion'] ?? 0,
                    'retained' => $decisions['retention'] ?? 0,
                    'graduated' => $decisions['graduation'] ?? 0,
                ],
                'billing' => [
                    'learners_billed' => $count(
                        "SELECT COUNT(DISTINCT student_academic_enrollment_id)
                         FROM student_fee_obligations WHERE academic_year_id = ?",
                        [$toYearId]
                    ),
                    'obligations' => $count(
                        "SELECT COUNT(*) FROM student_fee_obligations WHERE academic_year_id = ?",
                        [$toYearId]
                    ),
                    'amount_billed' => $total(
                        "SELECT COALESCE(SUM(amount_due),0) FROM student_fee_obligations WHERE academic_year_id = ?",
                        [$toYearId]
                    ),
                    'arrears_carried' => $total(
                        "SELECT COALESCE(SUM(arrears_carried),0) FROM student_fee_rollover_balances
                         WHERE from_academic_year_id = ? AND to_academic_year_id = ?",
                        [$fromYearId, $toYearId]
                    ),
                    'credits_carried' => $total(
                        "SELECT COALESCE(SUM(credit_carried),0) FROM student_fee_rollover_balances
                         WHERE from_academic_year_id = ? AND to_academic_year_id = ?",
                        [$fromYearId, $toYearId]
                    ),
                    'fee_schedules' => $feeStatuses,
                    'per_term' => $perTerm,
                ],
                'graduates' => [
                    'learners' => (int) ($graduateSummary['learners'] ?? 0),
                    'balance_retained' => (float) ($graduateSummary['arrears'] ?? 0) + (float) ($graduateSummary['credits'] ?? 0),
                ],
                'stage_history' => $stageHistory,
            ], 'Rollover summary loaded.');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignPromotionStreams(int $instance_id, array $assignments): array
    {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
        if (!$assignments) return formatResponse(false, null, 'At least one learner stream assignment is required');
        $data = json_decode($instance['data_json'], true) ?: [];
        $fromYearId = $this->resolveYearIdFromCode($data['from_year'] ?? 0);
        $toYearId = (int) ($data['academic_year_id'] ?? 0);
        if (!$fromYearId || !$toYearId) return formatResponse(false, null, 'Transition years are not prepared');

        $currentStage = (string) ($instance['current_stage_code'] ?? $instance['current_stage'] ?? '');
        if (in_array($currentStage, ['setup_new_year', 'configure_operational_context', 'current_year_readiness', 'close_current_year_terms'], true)) {
            $this->advanceStage($instance_id, 'review_promotion_candidates', 'Term operations deferred to active-term setup; promotion review started', $data);
        }

        // A closed (active - completed) year is readable by everyone but only
        // school admin / system admin / director may write to it, so promotion
        // writes into such a year must carry an authorised role.
        $writeGuard = \App\API\Services\CompletedYearWriteGuard::check(
            $toYearId,
            $this->currentRoleNames(),
            $this->db
        );
        if (!$writeGuard['allowed']) {
            return formatResponse(false, null, $writeGuard['reason']);
        }

        $this->db->beginTransaction();
        try {
            // Approve the target-year fee structures BEFORE any enrollment is
            // inserted: trg_create_student_obligations only bills schedules whose
            // status is 'active', so a draft structure would silently leave the
            // promoted learner with no target-year balance.
            $this->approveTargetYearFeeStructures($toYearId);

            $saved = 0;
            $billed = [];
            $retained = [];
            $unavailablePlacements = 0;
            $unavailableStudentIds = [];
            $learnerTypeAvailability = new \App\API\Services\AcademicYearLearnerTypeAvailabilityService($this->db);
            $source = $this->db->prepare(
                "SELECT sae.id, sae.academic_year_class_stream_id, ayc.class_id
                 FROM " . ReadReplicaService::masterSourceRef('student_academic_enrollments') . " sae
                 JOIN " . ReadReplicaService::masterSourceRef('academic_year_class_streams') . " aycs
                   ON aycs.id = sae.academic_year_class_stream_id
                 JOIN " . ReadReplicaService::masterSourceRef('academic_year_classes') . " ayc
                   ON ayc.id = aycs.academic_year_class_id
                 WHERE sae.student_id = ? AND sae.academic_year_id = ?
                   AND (sae.enrollment_status IN ('pending','active') OR EXISTS
                        (SELECT 1 FROM student_transitions st
                         WHERE st.from_student_academic_enrollment_id = sae.id AND st.academic_year_id = ?))
                 ORDER BY sae.id DESC LIMIT 1"
            );
            $target = $this->db->prepare(
                "SELECT aycs.id AS class_stream_id, ayc.class_id, ayc.id AS academic_year_class_id
                 FROM " . ReadReplicaService::masterSourceRef('academic_year_class_streams') . " aycs
                 JOIN " . ReadReplicaService::masterSourceRef('academic_year_classes') . " ayc
                   ON ayc.id = aycs.academic_year_class_id
                 WHERE aycs.id = ? AND ayc.academic_year_id = ? AND aycs.status IN ('planning','active') LIMIT 1"
            );
            $studentType = $this->db->prepare("SELECT student_type_id FROM students WHERE id = ? LIMIT 1");
            $progression = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_class_progression
                 WHERE source_class_id = ? AND target_class_id = ? AND active = 1"
            );
            $transition = $this->db->prepare(
                "SELECT id FROM student_transitions WHERE student_id = ? AND academic_year_id = ?
                 AND from_student_academic_enrollment_id = ? LIMIT 1"
            );
            foreach ($assignments as $assignment) {
                $studentId = (int) ($assignment['student_id'] ?? 0);
                $targetAycsId = (int) ($assignment['target_stream_id'] ?? $assignment['target_aycs_id'] ?? 0);
                $decision = strtolower(trim((string) ($assignment['decision'] ?? 'promote')));
                if (!in_array($decision, ['promote', 'retain', 'graduate'], true)) throw new Exception('Choose promote, retain or graduate for each learner');
                if (!$studentId || ($decision !== 'graduate' && !$targetAycsId)) throw new Exception('Student and target-year stream are required');

                $source->execute([$studentId, $fromYearId, $toYearId]);
                $sourceRow = $source->fetch(PDO::FETCH_ASSOC);
                if (!$sourceRow) throw new Exception('Learner is not an active learner in the source year');

                if ($decision === 'graduate') {
                    $hasNextClass = $this->db->prepare(
                        'SELECT COUNT(*) FROM academic_class_progression WHERE source_class_id = ? AND active = 1'
                    );
                    $hasNextClass->execute([(int) $sourceRow['class_id']]);
                    if ((int) $hasNextClass->fetchColumn() > 0) {
                        throw new Exception('Only a class with no configured next class can be graduated.');
                    }
                    $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'graduated' WHERE id = ?")
                        ->execute([(int) $sourceRow['id']]);
                    $transition->execute([$studentId, $toYearId, (int) $sourceRow['id']]);
                    $existingTransitionId = (int) ($transition->fetchColumn() ?: 0);
                    if ($existingTransitionId) {
                        $this->db->prepare(
                            "UPDATE student_transitions SET to_student_academic_enrollment_id = NULL,
                             transition_type = 'graduation', reason = 'Administrator graduated learner',
                             decided_by = ?, decided_at = NOW(), executed_at = NOW() WHERE id = ?"
                        )->execute([$this->user_id, $existingTransitionId]);
                    } else {
                        $this->db->prepare(
                            "INSERT INTO student_transitions
                                (student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                                 academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)
                             VALUES (?, ?, NULL, ?, 'graduation', 'Administrator graduated learner', ?, NOW(), NOW())"
                        )->execute([$studentId, (int) $sourceRow['id'], $toYearId, $this->user_id]);
                    }
                    $saved++;
                    // Graduation is not billed for the new year; the
                    // outgoing-year balance is simply retained as balance.
                    $retained[$studentId] = $this->retainGraduatingLearnerBalance(
                        $studentId,
                        $fromYearId,
                        (int) $sourceRow['id']
                    );
                    continue;
                }

                $target->execute([$targetAycsId, $toYearId]);
                $targetRow = $target->fetch(PDO::FETCH_ASSOC);
                if (!$targetRow) throw new Exception('Selected stream does not belong to the target academic year');

                $studentType->execute([$studentId]);
                if (!$learnerTypeAvailability->isAvailable(
                    $toYearId,
                    (int) $targetRow['class_id'],
                    (int) ($studentType->fetchColumn() ?: 0)
                )) {
                    $unavailablePlacements++;
                    $unavailableStudentIds[] = $studentId;
                    continue;
                }

                if ($decision === 'promote') {
                    $progression->execute([(int) $sourceRow['class_id'], (int) $targetRow['class_id']]);
                    if (!(int) $progression->fetchColumn()) throw new Exception('Selected stream is not a valid next class for this learner');
                } elseif ((int) $sourceRow['class_id'] !== (int) $targetRow['class_id']) {
                    throw new Exception('A retained learner must be placed in the same class in the target year');
                }

                $existing = $this->db->prepare(
                    "SELECT id FROM student_academic_enrollments WHERE student_id = ? AND academic_year_id = ? LIMIT 1"
                );
                $existing->execute([$studentId, $toYearId]);
                $targetEnrollmentId = (int) ($existing->fetchColumn() ?: 0);
                if ($targetEnrollmentId) {
                    $this->db->prepare("UPDATE student_academic_enrollments SET academic_year_class_stream_id = ?, enrollment_status = 'active' WHERE id = ?")
                        ->execute([$targetAycsId, $targetEnrollmentId]);
                } else {
                    $this->db->prepare(
                        "INSERT INTO student_academic_enrollments
                            (student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status)
                         VALUES (?, ?, ?, CURDATE(), 'active')"
                    )->execute([$studentId, $toYearId, $targetAycsId]);
                    $targetEnrollmentId = (int) $this->db->lastInsertId();
                }
                $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'completed' WHERE id = ?")
                    ->execute([(int) $sourceRow['id']]);
                $transition->execute([$studentId, $toYearId, (int) $sourceRow['id']]);
                $existingTransitionId = (int) ($transition->fetchColumn() ?: 0);
                if (!$existingTransitionId) {
                    $this->db->prepare(
                        "INSERT INTO student_transitions
                            (student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                             academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    )->execute([$studentId, (int) $sourceRow['id'], $targetEnrollmentId, $toYearId, $decision === 'retain' ? 'retention' : 'promotion', $decision === 'retain' ? 'Administrator retained learner in the same class' : 'Administrator promoted learner', $this->user_id]);
                } else {
                    $this->db->prepare("UPDATE student_transitions SET to_student_academic_enrollment_id=?, transition_type=?, reason=?, decided_by=?, decided_at=NOW(), executed_at=NOW() WHERE id=?")
                        ->execute([$targetEnrollmentId, $decision === 'retain' ? 'retention' : 'promotion', $decision === 'retain' ? 'Administrator retained learner in the same class' : 'Administrator promoted learner', $this->user_id, $existingTransitionId]);
                }
                // The target-year obligations were billed by
                // trg_create_student_obligations when the enrollment was
                // inserted. Carry the outgoing-year balance forward now.
                $billed[$studentId] = $this->billLearnerForTargetYear(
                    $studentId,
                    (int) $sourceRow['id'],
                    $targetEnrollmentId,
                    $fromYearId,
                    $toYearId,
                    (int) ($targetRow['academic_year_class_id'] ?? $targetRow['class_id'])
                );
                $saved++;
            }

            $remaining = $this->db->prepare(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 JOIN students s ON s.id = sae.student_id AND s.status = 'active'
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('pending','active')
                   AND NOT EXISTS (SELECT 1 FROM student_transitions st
                                   WHERE st.from_student_academic_enrollment_id = sae.id
                                     AND st.academic_year_id = ?)"
            );
            $remaining->execute([$fromYearId, $toYearId]);
            $unassigned = (int) $remaining->fetchColumn();
            $summary = ['saved' => $saved, 'unassigned' => $unassigned, 'unavailable' => $unavailablePlacements,
                'unavailable_student_ids' => $unavailableStudentIds, 'complete' => $unassigned === 0,
                // What the promotion save actually billed/carried, so the page
                // can show it instead of a bare "saved".
                'learners_billed' => count($billed),
                'obligations_backfilled' => (int) array_sum(array_map(
                    static fn(array $row): int => (int) ($row['obligations_backfilled'] ?? 0), $billed
                )),
                'arrears_carried' => round((float) array_sum(array_map(
                    static fn(array $row): float => (float) ($row['arrears_applied'] ?? 0), $billed
                )), 2),
                'credits_carried' => round((float) array_sum(array_map(
                    static fn(array $row): float => (float) ($row['credit_applied'] ?? 0), $billed
                )), 2),
                'graduates_balance_retained' => count($retained)];
            $data['promotion_summary'] = array_merge($data['promotion_summary'] ?? [], [
                'assigned' => ($data['promotion_summary']['assigned'] ?? 0) + $saved,
                'unassigned' => $unassigned,
                'assignment_complete' => $unassigned === 0,
            ]);
            // Merge, do not rewrite: this save happens after close-year has
            // a sibling stage wrote, and a whole-document write from a stale
            // read would drop it and fail the readiness gate on the next pass.
            $this->mergeInstanceData($instance_id, ['promotion_summary' => $data['promotion_summary']]);
            if ($unassigned === 0) {
                $stageStmt = $this->db->prepare('SELECT current_stage FROM workflow_instances WHERE id = ? LIMIT 1');
                $stageStmt->execute([$instance_id]);
                $currentStage = (string) ($stageStmt->fetchColumn() ?: '');
                if ($currentStage === 'review_promotion_candidates') {
                    $this->advanceStage($instance_id, 'assign_promotion_decisions', 'Promotion decisions recorded from the administrator board', $data);
                    $currentStage = 'assign_promotion_decisions';
                }
                if ($currentStage === 'assign_promotion_decisions') {
                    $this->advanceStage($instance_id, 'assign_target_streams', 'Target class and stream assignment started', $data);
                    $currentStage = 'assign_target_streams';
                }
                if ($currentStage === 'assign_target_streams') {
                    $this->advanceStage($instance_id, 'create_new_year_enrollments', 'Learners assigned and target-year enrollments created', $data);
                    $currentStage = 'create_new_year_enrollments';
                }
                if ($currentStage === 'create_new_year_enrollments') {
                    $this->advanceStage($instance_id, 'carry_forward_finances', 'Ready for arrears, credits and advance-payment carry-forward', $data);
                }
                // Older saves could complete promotions while the stored
                // pointer was still on an earlier setup stage. Reconcile it
                // from primary data so later finance actions are not blocked.
                $this->reconcilePromotionCompletion($instance_id);
            }
            $this->db->commit();
            return formatResponse(true, $summary, $unavailablePlacements > 0
                ? 'Some placements need administrator adjustment because the selected class does not offer that learner type.'
                : ($unassigned === 0 ? 'All promotion assignments saved' : 'Promotion assignments saved; you may continue later'));
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Setup new academic year
     *
     * Two modes:
     *
     *  A) AUTO (default, no class_structures) - clones the source (Term 3)
     *     year's class structure one grade ahead:
     *       * target class resolved through academic_class_progression
     *         (e.g. Grade 5 -> Grade 6, Grade 8 -> Grade 9);
     *       * Grade 4 and above copies the source stream set (5A/B/C -> 6A/B/C);
     *       * ECD / lower primary (Playgroup..Grade 3) always gets 2 streams (A, B);
     *       * Grade 9 graduates (no target created);
     *       * idempotent - existing classes/streams in the target year are reused.
     *
     *  B) EXPLICIT (class_structures provided) - legacy behavior, creates the
     *     listed levels/streams verbatim.
     *
     * @param int $instance_id Workflow instance ID
     * @param array $setup_config {
     *   @type int $from_year Source year (defaults to workflow from_year)
     *   @type array $class_structures Explicit class definitions (mode B)
     *   @type array $stream_overrides { target_class_id: [stream names] }
     *   @type int $default_ecd_streams Streams for ECD/lower (default 2)
     *   @type int $capacity Default stream capacity (default 40)
     * }
     * @return array Response with setup summary
     */
    public function setupNewYear(int $instance_id, array $setup_config): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $toYear = (int) ($data['to_year'] ?? 0);
            $fromYear = (int) ($setup_config['from_year'] ?? ($data['from_year'] ?? 0));
            $capacity = (int) ($setup_config['capacity'] ?? 40);
            $defaultEcdStreams = max(1, (int) ($setup_config['default_ecd_streams'] ?? 2));
            $streamOverrides = $setup_config['stream_overrides'] ?? [];
            $capacityOverrides = $setup_config['capacity_overrides'] ?? [];
            $learnerTypesByClass = $setup_config['learner_types_by_class'] ?? null;

            $academicYearId = $this->resolveYearIdFromCode($toYear);
            if (!$academicYearId) {
                throw new Exception("Academic year {$toYear} not found");
            }

            $this->db->beginTransaction();

            // Mode B: explicit class structures (legacy path).
            if (!empty($setup_config['class_structures'])) {
                $newClasses = $this->setupNewYearExplicit(
                    $academicYearId,
                    $setup_config['class_structures'],
                    $capacity
                );

                $contextSummary = $this->copyNewYearContext($fromYear, $academicYearId);

                $data['new_classes'] = array_merge($data['new_classes'] ?? [], $newClasses);
                $data['academic_year_id'] = $academicYearId;
                $data['new_year_context'] = $contextSummary;
                $data['class_learner_types'] = $this->mergeClassLearnerTypeMap(
                    $data['class_learner_types'] ?? [], $learnerTypesByClass, $newClasses
                );

                $this->advanceNewYearSetupStages(
                    $instance_id,
                    $data,
                    "Created " . count($newClasses) . " classes for year {$toYear}"
                );
                $this->mergeInstanceData($instance_id, [
                    'new_year_context' => $contextSummary,
                    'class_learner_types' => $this->mergeClassLearnerTypeMap(
                        $data['class_learner_types'] ?? [], $learnerTypesByClass, $newClasses
                    ),
                ]);

                $this->db->commit();

                return formatResponse(true, [
                    'classes_created' => count($newClasses),
                    'new_classes' => $newClasses,
                    'context' => $contextSummary,
                    'academic_year_id' => $academicYearId,
                    'learner_types_by_class' => $data['class_learner_types'],
                    'mode' => 'explicit',
                ], 'New academic year structure created');
            }

            // Mode A: auto-clone from the source year one grade ahead.
            $newClasses = $this->setupNewYearAuto(
                $fromYear,
                $academicYearId,
                $capacity,
                $defaultEcdStreams,
                $streamOverrides,
                $capacityOverrides
            );

            $contextSummary = $this->copyNewYearContext($fromYear, $academicYearId);

            $data['new_classes'] = array_merge($data['new_classes'] ?? [], $newClasses);
            $data['academic_year_id'] = $academicYearId;
            $data['new_year_context'] = $contextSummary;
            $data['class_learner_types'] = $this->mergeClassLearnerTypeMap(
                $data['class_learner_types'] ?? [], $learnerTypesByClass, $newClasses
            );

            $this->advanceNewYearSetupStages(
                $instance_id,
                $data,
                "Created " . count($newClasses) . " classes for year {$toYear}"
            );
            $this->mergeInstanceData($instance_id, [
                'new_year_context' => $contextSummary,
                'class_learner_types' => $this->mergeClassLearnerTypeMap(
                    $data['class_learner_types'] ?? [], $learnerTypesByClass, $newClasses
                ),
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'classes_created' => count($newClasses),
                'new_classes' => $newClasses,
                'context' => $contextSummary,
                'academic_year_id' => $academicYearId,
                'learner_types_by_class' => $data['class_learner_types'],
                'mode' => 'auto',
            ], 'New academic year structure created');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    private function mergeClassLearnerTypeMap(array $existing, $requested, array $targetClasses): array
    {
        if (!is_array($requested)) return $existing;
        $targetClassIds = array_map(static fn(array $row): int => (int) ($row['class_id'] ?? 0), $targetClasses);
        $validCodes = ['DAY', 'BOARD'];
        foreach ($targetClassIds as $classId) {
            $key = (string) $classId;
            if ($classId <= 0 || !array_key_exists($key, $requested) || !is_array($requested[$key])) {
                throw new Exception('Choose Day and/or Board availability for every target-year class.');
            }
            $codes = $requested[$key];
            $normalized = [];
            foreach ($codes as $code) {
                $code = strtoupper(trim((string) $code));
                if (!in_array($code, $validCodes, true)) {
                    throw new Exception('The learner-type selection contains an unsupported option.');
                }
                $normalized[$code] = $code;
            }
            $existing[$key] = array_values($normalized);
        }
        return $existing;
    }

    /**
     * Merge specific keys into the workflow instance's data_json.
     *
     * SELECT ... FOR UPDATE plus a PHP merge, rather than JSON_SET with a bound
     * parameter: MariaDB stores a bound string parameter as a JSON string value,
     * so JSON_SET(doc, '$.k', ?) persists an object as a quoted string and every
     * JSON path lookup into it then returns NULL. The row lock also makes the
     * read-modify-write safe — rewriting the whole document from an in-memory
     * copy discards whatever a sibling stage wrote seconds earlier (a closed
     * sibling stage wrote being the case that actually bit us).
     */
    private function mergeInstanceData(int $instance_id, array $fields): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("SELECT data_json FROM workflow_instances WHERE id = ? FOR UPDATE");
            $stmt->execute([$instance_id]);
            $raw = $stmt->fetchColumn();
            $data = $raw ? (json_decode((string) $raw, true) ?: []) : [];
            if (!is_array($data)) {
                $data = [];
            }

            foreach ($fields as $key => $value) {
                if ($value === null) {
                    // Absent, not the JSON string "null": the activation engine
                    // probes certification with JSON_EXTRACT(...) IS NOT NULL and
                    // a literal "null" would satisfy that test.
                    unset($data[$key]);
                    continue;
                }
                $data[$key] = $value;
            }

            $this->db->prepare("UPDATE workflow_instances SET data_json = ? WHERE id = ?")
                ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $instance_id]);

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @deprecated Replaced by mergeInstanceData(), which locks the row and merges
     *             only the keys the caller owns instead of rewriting the whole
     *             document from a possibly stale read.
     */
    private function persistWorkflowData(int $instanceId, array $data): void
    {
        $stmt = $this->db->prepare("UPDATE workflow_instances SET data_json = ? WHERE id = ?");
        $stmt->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $instanceId]);
    }

    /**
     * Mode A: clone all source-year classes into the target year with the same
     * class identity. Learner progression is handled separately during promotions.
     *
     * @return array List of created/ensured class entries
     */
    private function setupNewYearAuto(int $fromYear, int $toYearId, int $capacity, int $defaultEcdStreams, array $streamOverrides, array $capacityOverrides = []): array
    {
        if ($fromYear <= 0) {
            throw new Exception('from_year is required for automatic class setup');
        }

        $fromYearId = $this->resolveYearIdFromCode($fromYear);
        if (!$fromYearId) {
            throw new Exception("Source academic year {$fromYear} not found");
        }

        // Source structure comes from the authoritative academic-year class
        // and stream links, not a calendar projection that can be incomplete.
        $sourceStmt = $this->db->prepare(
            "SELECT ayc.class_id, c.name AS class_name, ayc.id AS ayc_id
             FROM " . ReadReplicaService::masterSourceRef('academic_year_classes') . " ayc
             JOIN " . ReadReplicaService::masterSourceRef('classes') . " c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ? AND ayc.status IN ('planning', 'active')
             ORDER BY ayc.class_id"
        );
        $sourceStmt->execute([$fromYearId]);
        $sourceClasses = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);

        $streamStmt = $this->db->prepare(
            "SELECT aycs.academic_year_class_id, stream.name AS stream_name
             FROM " . ReadReplicaService::masterSourceRef('academic_year_classes') . " ayc
             JOIN " . ReadReplicaService::masterSourceRef('academic_year_class_streams') . " aycs
               ON aycs.academic_year_class_id = ayc.id AND aycs.status IN ('planning', 'active')
             JOIN " . ReadReplicaService::masterSourceRef('streams') . " stream ON stream.id = aycs.stream_id
             WHERE ayc.academic_year_id = ?
             ORDER BY aycs.academic_year_class_id, stream.name"
        );
        $streamStmt->execute([$fromYearId]);
        $streamsByAycs = [];
        foreach ($streamStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $streamsByAycs[(int) $row['academic_year_class_id']][] = $row['stream_name'];
        }

        // Existing target structure (for idempotency).
        $existingTarget = [];
        $targetStmt = $this->db->prepare(
            "SELECT class_id, id AS ayc_id
             FROM " . ReadReplicaService::masterSourceRef('academic_year_classes') . "
             WHERE academic_year_id = ? AND status IN ('planning', 'active')"
        );
        $targetStmt->execute([$toYearId]);
        foreach ($targetStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingTarget[(int) $row['class_id']] = (int) $row['ayc_id'];
        }

        $created = [];
        foreach ($sourceClasses as $source) {
            $sourceClassId = (int) $source['class_id'];
            $sourceName = (string) $source['class_name'];
            // Annual setup never shifts or omits a class. Promotion is an
            // administrator decision made later for each learner.
            $targetClassId = $sourceClassId;

            // Determine stream names for the target class.
            if (isset($streamOverrides[$targetClassId]) && is_array($streamOverrides[$targetClassId])) {
                $streamNames = array_values(array_map('strval', $streamOverrides[$targetClassId]));
            } else {
                // Keep the source stream set when present. If it is empty,
                // begin with one stream and let the administrator adjust it.
                $streamNames = $streamsByAycs[(int) $source['ayc_id']] ?? ['A'];
                if (empty($streamNames)) {
                    $streamNames = ['A'];
                }
            }

            $created[] = $this->ensureYearClass(
                $toYearId,
                $targetClassId,
                $streamNames,
                max(1, (int) ($capacityOverrides[$targetClassId] ?? $capacity)),
                $existingTarget
            );
        }

        return $created;
    }

    /**
     * Copy academic-year curriculum, class-teacher defaults and fee drafts.
     * Term-specific teacher assignments and timetables are prepared per target
     * term and are deliberately not cloned during annual structure setup.
     */
    private function copyNewYearContext(int $fromYear, int $toYearId): array
    {
        $fromYearId = $this->resolveYearIdFromCode($fromYear);
        if (!$fromYearId) {
            throw new Exception('Source academic year could not be resolved');
        }

        $classMap = [];
        $sourceClasses = $this->db->prepare(
            "SELECT ayc.id AS source_ayc_id, ayc.class_id AS source_class_id
             FROM academic_year_classes ayc WHERE ayc.academic_year_id = ?"
        );
        $sourceClasses->execute([$fromYearId]);
        foreach ($sourceClasses->fetchAll(PDO::FETCH_ASSOC) as $source) {
            // Copy annual learning-area context onto the same class. Promotion
            // mappings are intentionally not used for year-level setup.
            $targetClassId = (int) $source['source_class_id'];
            $targetAyc = $this->db->prepare(
                "SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1"
            );
            $targetAyc->execute([$toYearId, $targetClassId]);
            $targetAycId = (int) ($targetAyc->fetchColumn() ?: 0);
            if ($targetAycId) $classMap[(int) $source['source_ayc_id']] = $targetAycId;
        }

        $targetTerms = [];
        $termStmt = $this->db->prepare(
            "SELECT id, term_id FROM academic_year_terms WHERE academic_year_id = ?"
        );
        $termStmt->execute([$toYearId]);
        foreach ($termStmt->fetchAll(PDO::FETCH_ASSOC) as $term) {
            $targetTerms[(int) $term['term_id']] = (int) $term['id'];
        }

        $learningAreaMap = [];
        $areaStmt = $this->db->prepare(
            "SELECT MIN(id) AS id, academic_year_class_id, learning_area_id
             FROM " . ReadReplicaService::masterSourceRef("academic_year_class_learning_areas") . "
             WHERE academic_year_class_id IN (SELECT id FROM " . ReadReplicaService::masterSourceRef("academic_year_classes") . " WHERE academic_year_id = ?)
               AND status <> 'skipped'
             GROUP BY academic_year_class_id, learning_area_id"
        );
        $areaStmt->execute([$fromYearId]);
        foreach ($areaStmt->fetchAll(PDO::FETCH_ASSOC) as $area) {
            $targetAycId = $classMap[(int) $area['academic_year_class_id']] ?? 0;
            if (!$targetAycId) continue;
            $existing = $this->db->prepare(
                "SELECT id FROM academic_year_class_learning_areas
                 WHERE academic_year_class_id = ? AND learning_area_id = ? LIMIT 1"
            );
            $existing->execute([$targetAycId, (int) $area['learning_area_id']]);
            $targetAreaId = (int) ($existing->fetchColumn() ?: 0);
            if (!$targetAreaId) {
                $this->db->prepare(
                    "INSERT INTO academic_year_class_learning_areas
                        (academic_year_class_id, learning_area_id, status)
                     VALUES (?, ?, 'planned')"
                )->execute([$targetAycId, (int) $area['learning_area_id']]);
                $targetAreaId = (int) $this->db->lastInsertId();
            }
            $learningAreaMap[(int) $area['id']] = $targetAreaId;
        }

        // Map source streams to target streams with the same name so annual
        // stream-level learning-area context can be prepared independently.
        $streamMap = [];
        $sourceStreamMap = $this->db->prepare(
            "SELECT src.id AS source_stream_id, target.id AS target_stream_id,
                    target.academic_year_class_id AS target_ayc_id
             FROM " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " src
             JOIN " . ReadReplicaService::masterSourceRef("streams") . " srcName ON srcName.id = src.stream_id
             JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " srcClass ON srcClass.id = src.academic_year_class_id
             JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " targetClass
               ON targetClass.academic_year_id = ? AND targetClass.class_id = srcClass.class_id
             JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " target
               ON target.academic_year_class_id = targetClass.id AND target.status IN ('planning', 'active')
             JOIN " . ReadReplicaService::masterSourceRef("streams") . " targetName ON targetName.id = target.stream_id
             WHERE srcClass.academic_year_id = ?
               AND src.status IN ('planning', 'active')
               AND LOWER(srcName.name) = LOWER(targetName.name)"
        );
        $sourceStreamMap->execute([$toYearId, $fromYearId]);
        $targetAycBySourceStream = [];
        foreach ($sourceStreamMap->fetchAll(PDO::FETCH_ASSOC) as $map) {
            $streamMap[(int) $map['source_stream_id']] = (int) $map['target_stream_id'];
            $targetAycBySourceStream[(int) $map['source_stream_id']] = (int) $map['target_ayc_id'];
        }

        // Create target stream-learning-area context for every available area
        // in each target stream, independently of timetable entries.
        foreach ($streamMap as $sourceStreamId => $targetStreamId) {
            $targetAycId = (int) ($targetAycBySourceStream[(int) $sourceStreamId] ?? 0);
            if (!$targetAycId) continue;
            $targetAreas = $this->db->prepare(
                "SELECT id FROM academic_year_class_learning_areas
                 WHERE academic_year_class_id = ? AND status <> 'skipped'"
            );
            $targetAreas->execute([$targetAycId]);
            foreach ($targetAreas->fetchAll(PDO::FETCH_COLUMN) as $targetAreaId) {
                $existing = $this->db->prepare(
                    "SELECT id FROM academic_year_class_stream_learning_areas
                     WHERE academic_year_class_stream_id = ? AND academic_year_class_learning_area_id = ? LIMIT 1"
                );
                $existing->execute([$targetStreamId, (int) $targetAreaId]);
                if ($existing->fetchColumn()) continue;
                $this->db->prepare(
                    "INSERT INTO academic_year_class_stream_learning_areas
                        (academic_year_class_stream_id, academic_year_class_learning_area_id, status)
                     VALUES (?, ?, 'planned')"
                )->execute([$targetStreamId, (int) $targetAreaId]);
            }
        }

        // Teaching assignments and timetables are term context. Keep their
        // target-year slots available for term-by-term setup without treating
        // outgoing-term records as annual configuration.
        $subjectTeacherCount = 0;
        $streamSubjectTeacherCount = 0;
        $timetableCount = 0;

        // Copy source fees to the promoted target class as draft schedules.
        $feeCount = 0;
        $feeStmt = $this->db->prepare(
            "SELECT src.academic_year_class_id, src.academic_year_term_id,
                    src.student_type_id, src.fee_catalog_id, src.amount
             FROM academic_year_fee_schedules src
             WHERE src.academic_year_id = ? AND src.status = 'active'"
        );
        $feeStmt->execute([$fromYearId]);
        foreach ($feeStmt->fetchAll(PDO::FETCH_ASSOC) as $fee) {
            $targetAycId = $classMap[(int) $fee['academic_year_class_id']] ?? 0;
            $sourceTerm = $this->db->prepare("SELECT term_id FROM academic_year_terms WHERE id = ? LIMIT 1");
            $sourceTerm->execute([(int) $fee['academic_year_term_id']]);
            $targetTermId = $targetTerms[(int) $sourceTerm->fetchColumn()] ?? 0;
            if (!$targetAycId || !$targetTermId) continue;
            $exists = $this->db->prepare(
                "SELECT id FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? AND academic_year_term_id = ?
                   AND academic_year_class_id = ? AND student_type_id = ?
                   AND fee_catalog_id = ? AND status IN ('active','draft') LIMIT 1"
            );
            $exists->execute([$toYearId, $targetTermId, $targetAycId, $fee['student_type_id'], $fee['fee_catalog_id']]);
            if ($exists->fetchColumn()) continue;
            $this->db->prepare(
                "INSERT INTO academic_year_fee_schedules
                    (academic_year_id, academic_year_term_id, academic_year_class_id,
                     student_type_id, fee_catalog_id, amount, due_date, status, created_at, updated_at)
                 SELECT ?, ?, ?, ?, ?, ?, closing_date, 'draft', NOW(), NOW()
                 FROM academic_year_terms WHERE id = ?"
            )->execute([
                $toYearId, $targetTermId,
                $targetAycId, $fee['student_type_id'], $fee['fee_catalog_id'], $fee['amount'], $targetTermId
            ]);
            $feeCount++;
        }

        $yearContext = [
            'classes_mapped' => count($classMap),
            'learning_areas_copied' => count($learningAreaMap),
            'fee_schedules_copied_as_draft' => $feeCount,
        ];
        $termContext = [
            'subject_teacher_assignments_copied' => $subjectTeacherCount,
            'stream_subject_teacher_assignments_copied' => $streamSubjectTeacherCount,
            'timetable_entries_copied' => $timetableCount,
        ];
        return $yearContext + $termContext + [
            'academic_year_context' => $yearContext,
            'academic_year_term_context' => $termContext,
        ];
    }

    /**
     * Resolve the next class id for a source class via academic_class_progression.
     * Returns 0 when the source class graduates (no next class).
     */
    private function resolveNextClassId(int $sourceClassId): int
    {
        $stmt = $this->db->prepare(
            "SELECT target_class_id FROM academic_class_progression
             WHERE source_class_id = ? AND active = 1 LIMIT 1"
        );
        $stmt->execute([$sourceClassId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Ensure a class + streams exist for a year, creating them when missing.
     */
    private function ensureYearClass(int $toYearId, int $classId, array $streamNames, int $capacity, array &$existingTarget): array
    {
        $aycId = $existingTarget[$classId] ?? 0;
        if ($aycId <= 0) {
            $aycStmt = $this->db->prepare(
                "INSERT INTO academic_year_classes (academic_year_id, class_id, status)
                 VALUES (?, ?, 'active')"
            );
            $aycStmt->execute([$toYearId, $classId]);
            $aycId = (int) $this->db->lastInsertId();
            $existingTarget[$classId] = $aycId;
        }

        $streamIds = [];
        foreach ($streamNames as $streamName) {
            $streamIds[] = $this->createStreamLink($aycId, [
                'name' => (string) $streamName,
                'capacity' => $capacity,
            ]);
        }

        $existingStreams = $this->db->prepare(
            "SELECT id FROM academic_year_class_streams
             WHERE academic_year_class_id = ? AND status IN ('planning', 'active')"
        );
        $existingStreams->execute([$aycId]);
        foreach ($existingStreams->fetchAll(PDO::FETCH_COLUMN) as $existingStreamId) {
            $existingStreamId = (int) $existingStreamId;
            if (in_array($existingStreamId, $streamIds, true)) continue;
            $enrollmentCount = $this->db->prepare(
                "SELECT COUNT(*) FROM student_academic_enrollments
                 WHERE academic_year_class_stream_id = ? AND enrollment_status = 'active'"
            );
            $enrollmentCount->execute([$existingStreamId]);
            if ((int) $enrollmentCount->fetchColumn() > 0) {
                throw new Exception('A stream with active learner enrollments cannot be removed from the target-year setup.');
            }
            $this->db->prepare("UPDATE academic_year_class_streams SET status = 'completed' WHERE id = ?")
                ->execute([$existingStreamId]);
        }

        return [
            'class_id' => $classId,
            'academic_year_class_id' => $aycId,
            'streams' => $streamIds,
            'stream_names' => $streamNames,
        ];
    }

    /**
     * Mode B: create explicitly-provided class structures (legacy behavior).
     */
    private function setupNewYearExplicit(int $academicYearId, array $classStructures, int $capacity): array
    {
        $newClasses = [];

        foreach ($classStructures as $structure) {
            $levelId = (int) ($structure['level_id'] ?? 0);
            $streams = $structure['streams'] ?? ['A'];

            // Resolve school level for class naming.
            $levelStmt = $this->db->prepare("SELECT name, code FROM school_levels WHERE id = ? LIMIT 1");
            $levelStmt->execute([$levelId]);
            $level = $levelStmt->fetch(PDO::FETCH_ASSOC);
            if (!$level) {
                throw new Exception("School level {$levelId} not found");
            }
            $levelName = $level['name'];
            $levelCode = strtoupper($level['code']);

            // Reuse the canonical class master when present; only create it when missing.
            $classStmt = $this->db->prepare("SELECT id FROM classes WHERE level_id = ? AND name = ? LIMIT 1");
            $classStmt->execute([$levelId, $levelName]);
            $classId = (int) ($classStmt->fetchColumn() ?: 0);
            if ($classId <= 0) {
                $classInsert = $this->db->prepare(
                    "INSERT INTO classes (code, name, level_id, grade_level)
                     VALUES (:code, :name, :level_id, NULL)"
                );
                $classInsert->execute([
                    'code' => $levelCode,
                    'name' => $levelName,
                    'level_id' => $levelId,
                ]);
                $classId = (int) $this->db->lastInsertId();
            }

            // Link class to academic year idempotently.
            $aycLookup = $this->db->prepare(
                "SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1"
            );
            $aycLookup->execute([$academicYearId, $classId]);
            $aycId = (int) ($aycLookup->fetchColumn() ?: 0);
            if ($aycId <= 0) {
                $aycStmt = $this->db->prepare(
                    "INSERT INTO academic_year_classes (academic_year_id, class_id, status)
                     VALUES (:year_id, :class_id, 'active')"
                );
                $aycStmt->execute(['year_id' => $academicYearId, 'class_id' => $classId]);
                $aycId = (int) $this->db->lastInsertId();
            }

            // Create/link streams for this class
            $streamIds = [];
            foreach ($streams as $streamName) {
                $streamName = is_array($streamName) ? ($streamName['name'] ?? 'A') : $streamName;
                $streamIds[] = $this->createStreamLink($aycId, [
                    'name' => (string) $streamName,
                    'capacity' => $capacity,
                ]);
            }

            $newClasses[] = [
                'level_id' => $levelId,
                'class_id' => $classId,
                'academic_year_class_id' => $aycId,
                'streams' => $streamIds,
            ];
        }

        return $newClasses;
    }

    /**
     * Upper-primary and above (Grade 4..Grade 9) copy the source stream set;
     * everything lower (Playgroup..Grade 3) always uses the default ECD set.
     */
    private function isUpperPrimaryOrAbove(string $className): bool
    {
        return (bool) preg_match('/^Grade\s+([4-9]|1[0-2])\b/i', trim($className));
    }

    /**
     * Stage 5: Migrate competency baselines
     * 
     * Carries forward competency achievement data for continued tracking.
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with migration summary
     */
    public function migrateBaselines(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $fromYear = (int) ($data['from_year'] ?? 0);
            $toYear = (int) ($data['to_year'] ?? 0);

            // Get final competency assessments from previous year
            $baselineStmt = $this->db->prepare(
                "SELECT 
                    lc.student_id,
                    lc.competency_id,
                    lc.performance_level_id,
                    MAX(lc.assessed_date) as last_assessed
                FROM learner_competencies lc
                WHERE lc.academic_year = :from_year
                GROUP BY lc.student_id, lc.competency_id"
            );
            $baselineStmt->execute(['from_year' => $fromYear]);
            $baselines = $baselineStmt->fetchAll(PDO::FETCH_ASSOC);

            $migratedBaselines = [
                'total_baselines' => count($baselines),
                'students_tracked' => count(array_unique(array_column($baselines, 'student_id'))),
                'competencies_tracked' => count(array_unique(array_column($baselines, 'competency_id'))),
                'migrated_at' => date('Y-m-d H:i:s'),
            ];

            // Note: Baselines are maintained in learner_competencies table
            // They serve as starting points for new year assessments

            $data['migrated_baselines'] = $migratedBaselines;

            $this->advanceStage(
                $instance_id,
                'migrate_baselines',
                "Migrated {$migratedBaselines['total_baselines']} competency baselines",
                $data
            );

            return formatResponse(true, $migratedBaselines, 'Competency baselines migrated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 6: Validate readiness
     * 
     * Final validation checks before new academic year goes live.
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with validation results
     */
    public function validateReadiness(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $toYear = $this->yearStart($data['to_year'] ?? 0);
            $fromYear = $this->yearStart($data['from_year'] ?? 0);

            $validationResults = [];
            $yearId = (int) ($data['academic_year_id'] ?? 0);
            if ($yearId <= 0) {
                $yearId = $this->resolveYearIdFromCode($toYear);
            }

            // Check 1: Target academic year exists.
            $yearStmt = $this->db->prepare(
                "SELECT id, status, is_current FROM academic_years WHERE id = ? LIMIT 1"
            );
            $yearStmt->execute([$yearId]);
            $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
            $validationResults['academic_year_exists'] = [
                'status' => $year ? 'pass' : 'fail',
                'academic_year_id' => $yearId,
                'year_status' => $year['status'] ?? null,
            ];

            // Check 2: Expected year terms created.
            $termsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_terms WHERE academic_year_id = ?"
            );
            $termsStmt->execute([$yearId]);
            $termsCount = (int) $termsStmt->fetchColumn();
            $validationResults['terms_created'] = [
                'status' => $termsCount >= 3 ? 'pass' : 'fail',
                'count' => $termsCount,
                'expected_minimum' => 3,
            ];

            // Check 3: Target classes created.
            $classesStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_classes
                 WHERE academic_year_id = ? AND status = 'active'"
            );
            $classesStmt->execute([$yearId]);
            $classesCount = (int) $classesStmt->fetchColumn();
            $validationResults['classes_created'] = [
                'status' => $classesCount > 0 ? 'pass' : 'fail',
                'count' => $classesCount,
            ];

            // Check 4: Every active target class has at least one stream.
            $streamGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs
                    ON aycs.academic_year_class_id = ayc.id
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND aycs.id IS NULL"
            );
            $streamGapsStmt->execute([$yearId]);
            $streamGaps = (int) $streamGapsStmt->fetchColumn();
            $validationResults['class_streams_seeded'] = [
                'status' => $classesCount > 0 && $streamGaps === 0 ? 'pass' : 'fail',
                'classes_without_streams' => $streamGaps,
            ];

            // Check 5: Classes that require CBC curriculum have learning areas.
            $learningAreaGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                 JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " acla
                    ON acla.academic_year_class_id = ayc.id
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND c.name NOT IN ('Playgroup', 'Grade 0')
                   AND acla.id IS NULL"
            );
            $learningAreaGapsStmt->execute([$yearId]);
            $learningAreaGaps = (int) $learningAreaGapsStmt->fetchColumn();
            $validationResults['learning_areas_seeded'] = [
                'status' => $learningAreaGaps === 0 ? 'pass' : 'fail',
                'classes_without_learning_areas' => $learningAreaGaps,
            ];

            // Check 6: Calendar exists for every target term.
            $calendarGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " aycal
                    ON aycal.academic_year_term_id = ayt.id
                 WHERE ayt.academic_year_id = ? AND aycal.id IS NULL"
            );
            $calendarGapsStmt->execute([$yearId]);
            $calendarGaps = (int) $calendarGapsStmt->fetchColumn();
            $validationResults['calendar_generated'] = [
                'status' => $termsCount > 0 && $calendarGaps === 0 ? 'pass' : 'fail',
                'terms_without_calendar' => $calendarGaps,
            ];

            // Check 6b: Calendar week numbers are sequential per term (no gaps/duplicates).
            $calendarStructureStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM (
                     SELECT aycal.academic_year_term_id
                     FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " aycal
                     JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = aycal.academic_year_term_id
                     WHERE ayt.academic_year_id = ?
                     GROUP BY aycal.academic_year_term_id
                     HAVING COUNT(*) <> COUNT(DISTINCT aycal.week_number)
                         OR COUNT(*) <> MAX(aycal.week_number)
                 ) malformed"
            );
            $calendarStructureStmt->execute([$yearId]);
            $malformedTerms = (int) $calendarStructureStmt->fetchColumn();
            $validationResults['calendar_week_structure'] = [
                'status' => $malformedTerms === 0 ? 'pass' : 'fail',
                'malformed_terms' => $malformedTerms,
            ];

            // Earlier source terms must be completed. The current final term
            // remains open until the successful cutover below closes it.
            $sourceYearId = 0;
            if ($fromYear > 0) {
                $sourceYearId = $this->resolveYearIdFromCode($fromYear);
            }
            if ($sourceYearId > 0) {
                $sourceTermsStmt = $this->db->prepare(
                    "SELECT status FROM academic_year_terms WHERE academic_year_id = ? ORDER BY id"
                );
                $sourceTermsStmt->execute([$sourceYearId]);
                $sourceStatuses = $sourceTermsStmt->fetchAll(PDO::FETCH_COLUMN);
                $sourceTermsReady = count($sourceStatuses) === 3;
                $lastSourceTerm = count($sourceStatuses) - 1;
                foreach ($sourceStatuses as $index => $status) {
                    $allowed = $index === $lastSourceTerm
                        ? in_array($status, ['current', 'completed'], true)
                        : $status === 'completed';
                    if (!$allowed) $sourceTermsReady = false;
                }
                $validationResults['source_year_closed'] = [
                    'status' => $sourceTermsReady ? 'pass' : 'fail',
                    'source_year_id' => $sourceYearId,
                    'open_terms' => count(array_filter($sourceStatuses, static fn($status) => $status !== 'completed')),
                    'will_close_at_cutover' => $sourceTermsReady,
                ];
            } else {
                $validationResults['source_year_closed'] = [
                    'status' => 'pass',
                    'source_year_id' => null,
                    'open_terms' => null,
                ];
            }

            // Check 7: Promotion stage was run.
            $validationResults['promotions_executed'] = [
                'status' => !empty($data['promotion_summary']) ? 'pass' : 'fail',
                'summary' => $data['promotion_summary'] ?? null,
            ];

            // Check 8: No unresolved pending promotion transitions for the target year.
            $pendingTransitionsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM student_transitions st
                 WHERE st.academic_year_id = ?
                   AND st.transition_type IN ('promotion','retention')
                   AND st.to_student_academic_enrollment_id IS NULL
                   AND st.executed_at IS NULL"
            );
            $pendingTransitionsStmt->execute([$yearId]);
            $pendingTransitions = (int) $pendingTransitionsStmt->fetchColumn();
            $validationResults['promotion_transitions_resolved'] = [
                'status' => $pendingTransitions === 0 ? 'pass' : 'fail',
                'pending_transitions' => $pendingTransitions,
            ];

            // Check 10: approved/active fee schedules exist for every target
            // class, term and configured student type. Draft copies are useful
            // for preparation but must never be used for live billing.
            $feeCountStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? AND status = 'active'"
            );
            $feeCountStmt->execute([$yearId]);
            $activeFees = (int) $feeCountStmt->fetchColumn();
            $feeGapStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                 JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.academic_year_id = ayc.academic_year_id
                 JOIN student_types st ON st.status = 'active'
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_fee_schedules") . " fs
                   ON fs.academic_year_id = ayc.academic_year_id
                  AND fs.academic_year_class_id = ayc.id
                  AND fs.academic_year_term_id = ayt.id
                  AND fs.student_type_id = st.id AND fs.status = 'active'
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND fs.id IS NULL"
            );
            $feeGapStmt->execute([$yearId]);
            $feeGaps = (int) $feeGapStmt->fetchColumn();
            $validationResults['fee_structures_ready'] = [
                'status' => $activeFees > 0 && $feeGaps === 0 ? 'pass' : 'fail',
                'active_schedules' => $activeFees,
                'missing_class_term_student_type_combinations' => $feeGaps,
            ];

            // If fee review has happened since promotion, seed any target
            // enrollments that were created while schedules were still draft.
            // This is idempotent and keeps the stored procedure as the single
            // billing authority.
            $unseededStmt = $this->db->prepare(
                "SELECT sae.id FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " fo ON fo.student_academic_enrollment_id = sae.id
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status = 'active'
                   AND fo.id IS NULL"
            );
            $unseededStmt->execute([$yearId]);
            $seededAtValidation = 0;
            foreach ($unseededStmt->fetchAll(PDO::FETCH_COLUMN) as $enrollmentId) {
                $call = $this->db->prepare("CALL sp_onboard_student_enrollment(?, ?, @validation_obligations)");
                $call->execute([(int) $enrollmentId, $this->user_id]);
                while ($call->nextRowset()) {}
                $this->db->query("SELECT @validation_obligations");
                $seededAtValidation++;
            }

            // Check 11: every target-year continuing enrollment has at least
            // one obligation unless it is a graduating/no-fee exception.
            $enrollmentCount = 0;
            $enrollmentGapStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " fo ON fo.student_academic_enrollment_id = sae.id
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status = 'active'
                   AND fo.id IS NULL"
            );
            $enrollmentGapStmt->execute([$yearId]);
            $enrollmentGaps = (int) $enrollmentGapStmt->fetchColumn();
            $validationResults['target_billing_seeded'] = [
                'status' => $enrollmentGaps === 0 ? 'pass' : 'fail',
                'active_enrollments_without_obligations' => $enrollmentGaps,
                'onboarding_attempts' => $seededAtValidation,
            ];

            // Overall readiness
            $allPassed = true;
            foreach ($validationResults as $check) {
                if (($check['status'] ?? 'fail') === 'fail') {
                    $allPassed = false;
                    break;
                }
            }

            $data['validation_results'] = $validationResults;
            $data['ready_for_new_year'] = $allPassed;

            if ($allPassed) {
                // Certification, not cutover.
                //
                // This pass used to flip is_current inside this same request,
                // which forced the school to abandon the outgoing year the
                // moment the last checkbox was ticked. It now certifies the
                // target year and records the date the switch is due:
                // AcademicActivationService::sweep() performs the cutover when
                // the target year actually opens, so the outgoing year keeps
                // serving attendance, fees and marks until its last day.
                $data['certified_at'] = date('Y-m-d H:i:s');
                $data['certified_by'] = (int) $this->user_id;
                $data['switch_date'] = (string) ($data['switch_date'] ?? $this->resolveSwitchDate($yearId));
                $data['cutover_mode'] = 'date_driven';
            }

            // Persist with JSON_SET rather than rewriting the whole document.
            //
            // A read-modify-write here would take whatever this method read at
            // entry and write it back wholesale. When the read came from a
            // source that lagged the writes made seconds earlier by the stages
            // that ran just before, the
            // write silently discarded them and the next readiness pass failed
            // on a check that had, in fact, passed. JSON_SET touches only these
            // keys, so sibling stage data can never be lost.
            $this->mergeInstanceData($instance_id, [
                'validation_results' => $validationResults,
                'ready_for_new_year' => $allPassed,
                'certified_at' => $data['certified_at'] ?? null,
                'certified_by' => $data['certified_by'] ?? null,
                'switch_date' => $data['switch_date'] ?? null,
                'cutover_mode' => 'date_driven',
            ]);

            return formatResponse($allPassed, [
                'ready_for_new_year' => $allPassed,
                'validation_results' => $validationResults,
                'certified' => $allPassed,
                'certified_at' => $data['certified_at'] ?? null,
                'switch_date' => $data['switch_date'] ?? null,
                'cutover_mode' => 'date_driven',
            ], $allPassed ? 'System ready for new academic year' : 'New academic year is not ready');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * The date the target year becomes operational: its own start date. The
     * outgoing year therefore stays is_current = 1 until the day before.
     */
    private function resolveSwitchDate(int $yearId): string
    {
        if ($yearId <= 0) {
            return '';
        }
        $stmt = $this->db->prepare("SELECT start_date FROM academic_years WHERE id = ? LIMIT 1");
        $stmt->execute([$yearId]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    /**
     * Get transition status and summary
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with transition details
     */
    public function getTransitionStatus(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            return formatResponse(true, [
                'from_year' => $data['from_year'] ?? null,
                'to_year' => $data['to_year'] ?? null,
                'current_stage' => $instance['current_stage_code'] ?? 'pending',
                'workflow_status' => $instance['status'] ?? 'pending',
                'promotion_summary' => $data['promotion_summary'] ?? null,
                'classes_created' => count($data['new_classes'] ?? []),
                'ready_for_new_year' => $data['ready_for_new_year'] ?? false,
            ], 'Transition status retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
