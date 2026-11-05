<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Shared extra-charge resolver. Charge definitions and pricing are stored in
 * normalized relations; admission and enrollment workflows use this service
 * so the amount shown and the amount enforced come from one source.
 */
final class ExtraChargeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function resolveAdmissionObligations(int $applicationId): array
    {
        $application = $this->application($applicationId);
        if (!$application) {
            throw new RuntimeException('Admission application not found.');
        }

        $yearId = null;
        if (!empty($application['target_term_id'])) {
            $termStmt = $this->db->prepare('SELECT academic_year_id FROM academic_year_terms WHERE id=? LIMIT 1');
            $termStmt->execute([(int) $application['target_term_id']]);
            $yearId = (int) ($termStmt->fetchColumn() ?: 0) ?: null;
        }
        $yearId = $yearId ?: $this->academicYearId((string) $application['academic_year']);
        if (!$yearId) {
            throw new RuntimeException('Academic year for the application was not found.');
        }

        $parentId = (int) ($application['parent_id'] ?? 0);
        $studentId = (int) ($application['enrolled_student_id'] ?? 0);
        $existingParent = false;
        if ($parentId > 0) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM student_parents WHERE parent_id=? AND (?=0 OR student_id<>?) LIMIT 1'
            );
            $stmt->execute([$parentId, $studentId, $studentId]);
            $existingParent = (bool) $stmt->fetchColumn();
        }

        $charges = $this->db->prepare(
            "SELECT ec.*
             FROM extra_charges ec
             JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
             WHERE ec.academic_year_id=? AND ec.status='active'
               AND ec.target_scope='new_admissions'
               AND ec.billing_model='paid_separately'
               AND ec.billing_frequency='one_time'
             ORDER BY ec.display_order, ec.id"
        );
        $charges->execute([$yearId]);

        $result = [];
        foreach ($charges->fetchAll(PDO::FETCH_ASSOC) as $charge) {
            if (!$this->admissionChargeApplies($charge, $application, $parentId, $studentId)) continue;
            $tier = $this->resolveAdmissionTier((int) $charge['id'], $application, $existingParent);
            $amount = $tier ? (float) $tier['amount'] : (float) $charge['amount'];

            $existing = $this->db->prepare(
                'SELECT * FROM extra_charge_application_obligations WHERE application_id=? AND extra_charge_id=? FOR UPDATE'
            );
            $existing->execute([$applicationId, (int) $charge['id']]);
            $obligation = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$obligation) {
                $insert = $this->db->prepare(
                    'INSERT INTO extra_charge_application_obligations
                     (application_id,extra_charge_id,pricing_tier_id,amount_due)
                     VALUES (?,?,?,?)'
                );
                $insert->execute([$applicationId, (int) $charge['id'], $tier['id'] ?? null, $amount]);
                $obligation = [
                    'id' => (int) $this->db->lastInsertId(),
                    'application_id' => $applicationId,
                    'extra_charge_id' => (int) $charge['id'],
                    'amount_due' => $amount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                ];
            }
            $obligation['name'] = $charge['name'];
            $obligation['gl_account_id'] = $charge['gl_account_id'];
            $obligation['existing_parent'] = $existingParent ? 1 : 0;
            $result[] = $obligation;
        }

        return $result;
    }

    public function admissionTotalDue(int $applicationId): float
    {
        $obligations = $this->resolveAdmissionObligations($applicationId);
        $total = 0.0;
        $relief = $this->admissionFinancialRelief($applicationId);
        $registrationType = $relief['registration_fee_waiver_type'] ?? 'none';
        $registrationValue = (float) ($relief['registration_fee_waiver_value'] ?? 0);
        $registrationRemaining = $registrationType === 'fixed' ? $registrationValue : 0.0;
        foreach ($obligations as $obligation) {
            $balance = max(0, (float) $obligation['amount_due']
                - (float) ($obligation['amount_paid'] ?? 0)
                - (float) ($obligation['amount_waived'] ?? 0));
            $waiver = $registrationType === 'full'
                ? $balance
                : ($registrationType === 'percentage'
                    ? $balance * $registrationValue / 100
                    : ($registrationType === 'fixed' ? min($balance, max(0, $registrationRemaining)) : 0));
            if ($registrationType === 'fixed') $registrationRemaining = max(0, $registrationRemaining - $waiver);
            $total += max(0, $balance - $waiver);
        }
        return round($total, 2);
    }

    /** Resolve the durable financial decision attached to an admission exception. */
    public function admissionFinancialRelief(int $applicationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT waiver_type, waiver_amount, waiver_details
             FROM admission_stage_skip_audit
             WHERE application_id=? AND waiver_type IS NOT NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $details = json_decode((string) ($row['waiver_details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $type = trim((string) ($row['waiver_type'] ?? ($details['waiver_type'] ?? '')));
        $registrationType = (string) ($details['registration_fee_waiver_type'] ?? 'none');
        $registrationValue = (float) ($details['registration_fee_waiver_value'] ?? 0);
        $registration = !empty($details['registration_fee_waived']);
        $schoolType = (string) ($details['school_fee_waiver_type'] ?? 'none');
        $schoolValue = (float) ($details['school_fee_waiver_value'] ?? 0);

        // Backward-compatible interpretation of previously saved exceptions.
        if ($type === 'registration_fee_waiver') $registration = true;
        if (in_array($type, ['full_waiver', 'sponsored'], true)) {
            $registration = true;
            if ($schoolType === 'none') $schoolType = 'full';
        }
        if ($registration && $registrationType === 'none') $registrationType = 'full';
        if (!in_array($schoolType, ['none', 'full', 'percentage', 'fixed'], true)) $schoolType = 'none';
        if ($schoolType === 'percentage') $schoolValue = min(100, max(0, $schoolValue));
        if ($schoolType === 'fixed') $schoolValue = max(0, $schoolValue);
        if (!in_array($registrationType, ['none', 'full', 'percentage', 'fixed'], true)) $registrationType = 'none';
        if ($registrationType === 'percentage') $registrationValue = min(100, max(0, $registrationValue));
        if ($registrationType === 'fixed') $registrationValue = max(0, $registrationValue);
        $registration = $registrationType !== 'none';

        return [
            'waiver_type' => $type ?: null,
            'registration_fee_waived' => $registration,
            'registration_fee_waiver_type' => $registrationType,
            'registration_fee_waiver_value' => $registrationValue,
            'school_fee_waiver_type' => $schoolType,
            'school_fee_waiver_value' => $schoolValue,
            'reason' => (string) ($details['reason'] ?? ''),
        ];
    }

    /** Shared financial-record projection for an enrolled learner. */
    public function studentFinancialRelief(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT aa.id AS application_id, aa.application_no, aa.academic_year,
                    a.waiver_type, a.waiver_amount, a.waiver_details, a.skipped_at, a.skipped_by
               FROM " . ReadReplicaService::qualifiedRef("admission_applications") . "
               JOIN admission_stage_skip_audit a
                 ON a.id = (SELECT a2.id FROM admission_stage_skip_audit a2
                              WHERE a2.application_id = aa.id AND a2.waiver_type IS NOT NULL
                              ORDER BY a2.id DESC LIMIT 1)
              WHERE aa.enrolled_student_id = ? ORDER BY aa.id DESC LIMIT 1"
        );
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $details = json_decode((string) ($row['waiver_details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $registrationType = (string) ($details['registration_fee_waiver_type'] ?? 'none');
        $schoolType = (string) ($details['school_fee_waiver_type'] ?? 'none');
        if ($row['waiver_type'] === 'registration_fee_waiver') $registrationType = 'full';
        if (in_array($row['waiver_type'], ['full_waiver', 'sponsored'], true)) {
            if ($registrationType === 'none') $registrationType = 'full';
            if ($schoolType === 'none' && $row['waiver_type'] === 'full_waiver') $schoolType = 'full';
        }
        $schoolAmount = $this->db->prepare(
            "SELECT COALESCE(SUM(sponsored_waiver_amount), 0)
               FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations_enrolled") . "
              WHERE student_id = ?"
        );
        $schoolAmount->execute([$studentId]);
        $registrationAmount = $this->db->prepare(
            "SELECT COALESCE(SUM(eao.amount_waived), 0)
               FROM extra_charge_application_obligations eao
              WHERE eao.application_id = ?"
        );
        $registrationAmount->execute([(int) $row['application_id']]);
        $row['registration_fee_waiver_type'] = $registrationType;
        $row['registration_fee_waiver_value'] = (float) ($details['registration_fee_waiver_value'] ?? 0);
        $row['school_fee_waiver_type'] = $schoolType;
        $row['school_fee_waiver_value'] = (float) ($details['school_fee_waiver_value'] ?? 0);
        $row['registration_fee_waived'] = $registrationType !== 'none';
        $row['school_fee_waived'] = $schoolType !== 'none';
        $row['registration_fee_waived_amount'] = (float) ($registrationAmount->fetchColumn() ?: 0);
        $row['school_fee_waived_amount'] = (float) ($schoolAmount->fetchColumn() ?: 0);
        $row['reason_code'] = $details['reason_code'] ?? null;
        $row['reason'] = $details['reason'] ?? null;
        $row['authorized_at'] = $details['authorized_at'] ?? $row['skipped_at'];
        $row['authorized_by'] = $details['authorized_by'] ?? $row['skipped_by'];
        unset($row['waiver_details']);
        return $row;
    }

    /** Apply the stored admission relief to generated learner obligations. */
    public function applyAdmissionFinancialRelief(int $applicationId, ?int $enrollmentId = null): array
    {
        $relief = $this->admissionFinancialRelief($applicationId);
        if (!$relief['registration_fee_waived'] && $relief['school_fee_waiver_type'] === 'none') return $relief;

        $this->resolveAdmissionObligations($applicationId);
        if ($relief['registration_fee_waived']) {
            $registrationRows = $this->db->prepare(
                "SELECT id, amount_due, amount_paid, amount_waived, status
                   FROM extra_charge_application_obligations
                  WHERE application_id=? AND status IN ('pending','partial')
                  ORDER BY id FOR UPDATE"
            );
            $registrationRows->execute([$applicationId]);
            $remaining = ($relief['registration_fee_waiver_type'] ?? 'full') === 'fixed'
                ? (float) ($relief['registration_fee_waiver_value'] ?? 0) : 0.0;
            foreach ($registrationRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $due = (float) $row['amount_due'];
                $paid = (float) $row['amount_paid'];
                $alreadyWaived = (float) ($row['amount_waived'] ?? 0);
                $balance = max(0, $due - $paid - $alreadyWaived);
                $type = $relief['registration_fee_waiver_type'] ?? 'full';
                $waiver = $type === 'full' ? $balance : ($type === 'percentage' ? $balance * (float) $relief['registration_fee_waiver_value'] / 100 : min($balance, max(0, $remaining)));
                if ($type === 'fixed') $remaining = max(0, $remaining - $waiver);
                $newWaived = min(max(0, $due - $paid), $alreadyWaived + $waiver);
                $status = ($paid + $newWaived) >= $due
                    ? ($paid > 0 ? 'paid' : 'waived')
                    : (($paid + $newWaived) > 0 ? 'partial' : $row['status']);
                $this->db->prepare("UPDATE extra_charge_application_obligations SET amount_waived=?, status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$newWaived, $status, (int) $row['id']]);
            }
        }

        if ($enrollmentId && $relief['school_fee_waiver_type'] !== 'none') {
            $rows = $this->db->prepare(
                "SELECT id, amount_due, sponsored_waiver_amount, status
                 FROM student_fee_obligations
                 WHERE student_academic_enrollment_id=? ORDER BY academic_year_term_id, id FOR UPDATE"
            );
            $rows->execute([$enrollmentId]);
            $remaining = $relief['school_fee_waiver_type'] === 'fixed' ? $relief['school_fee_waiver_value'] : 0.0;
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $due = (float) $row['amount_due'];
                $waiver = $relief['school_fee_waiver_type'] === 'full'
                    ? $due
                    : ($relief['school_fee_waiver_type'] === 'percentage'
                        ? $due * $relief['school_fee_waiver_value'] / 100
                        : min($due, max(0, $remaining)));
                $waiver = round(min($due, max((float) $row['sponsored_waiver_amount'], $waiver)), 2);
                if ($relief['school_fee_waiver_type'] === 'fixed') $remaining = max(0, $remaining - $waiver);
                $status = $waiver >= $due ? 'paid' : ($waiver > 0 && $row['status'] === 'pending' ? 'partial' : $row['status']);
                $this->db->prepare(
                    "UPDATE student_fee_obligations
                     SET is_sponsored=1, sponsored_waiver_amount=?, status=?, updated_at=NOW()
                     WHERE id=?"
                )->execute([$waiver, $status, (int) $row['id']]);
            }
        }
        return $relief;
    }

    public function allocateAdmissionPayment(int $applicationId, int $paymentId, float $amount): void
    {
        $stmt = $this->db->prepare(
            'SELECT id,amount_due,amount_paid FROM extra_charge_application_obligations
             WHERE application_id=? AND status IN (\'pending\',\'partial\') ORDER BY id FOR UPDATE'
        );
        $stmt->execute([$applicationId]);
        $remaining = round($amount, 2);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $obligation) {
            if ($remaining <= 0) break;
            $balance = max(0, (float) $obligation['amount_due'] - (float) $obligation['amount_paid']);
            $allocated = min($remaining, $balance);
            if ($allocated <= 0) continue;
            $this->db->prepare(
                'INSERT INTO admission_payment_allocations(admission_payment_id,application_obligation_id,amount) VALUES(?,?,?)'
            )->execute([$paymentId, (int) $obligation['id'], $allocated]);
            $newPaid = round((float) $obligation['amount_paid'] + $allocated, 2);
            $status = $newPaid >= (float) $obligation['amount_due'] ? 'paid' : 'partial';
            $this->db->prepare(
                'UPDATE extra_charge_application_obligations SET amount_paid=?,status=? WHERE id=?'
            )->execute([$newPaid, $status, (int) $obligation['id']]);
            $remaining = round($remaining - $allocated, 2);
        }
    }

    /** Generate the current billable occurrence for an enrolled learner.
     * Recurring occurrences are regenerated by the scheduled billing command;
     * the unique key makes onboarding and rollover idempotent. */
    public function generateEnrollmentObligations(int $enrollmentId): int
    {
        $context = $this->db->prepare(
            "SELECT sae.student_id, sae.academic_year_id, s.student_type_id,
                    ayc.class_id, s.admission_no
             FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id=sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id=sae.academic_year_class_stream_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id=aycs.academic_year_class_id
             WHERE sae.id=? LIMIT 1"
        );
        $context->execute([$enrollmentId]);
        $row = $context->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Enrollment not found.');

        $parentStmt = $this->db->prepare('SELECT sp.parent_id FROM student_parents sp WHERE sp.student_id=? ORDER BY sp.is_primary_contact DESC,sp.parent_id');
        $parentStmt->execute([(int) $row['student_id']]);
        $parentIds = array_map('intval', $parentStmt->fetchAll(PDO::FETCH_COLUMN));
        $parentId = $parentIds[0] ?? 0;
        $existingParent = false;
        foreach ($parentIds as $candidateParentId) {
            $siblings = $this->db->prepare('SELECT 1 FROM student_parents WHERE parent_id=? AND student_id<>? LIMIT 1');
            $siblings->execute([$candidateParentId, (int) $row['student_id']]);
            if ($siblings->fetchColumn()) { $existingParent = true; break; }
        }
        $priorEnrollment = $this->db->prepare('SELECT 1 FROM student_academic_enrollments WHERE student_id=? AND academic_year_id<? LIMIT 1');
        $priorEnrollment->execute([(int) $row['student_id'], (int) $row['academic_year_id']]);
        $isNewStudent = !(bool) $priorEnrollment->fetchColumn();

        $charges = $this->db->prepare(
            "SELECT ec.*, sch.id AS schedule_id, sch.frequency AS schedule_frequency,
                    sch.starts_on, sch.ends_on, sch.academic_year_term_id, sch.due_day
             FROM extra_charge_schedules sch
             JOIN extra_charges ec ON ec.id=sch.extra_charge_id
             JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='enrollment'
             WHERE ec.academic_year_id=? AND ec.status='active'
               AND ec.billing_model IN ('added_to_fees','paid_separately')
               AND sch.status='active' AND sch.starts_on<=COALESCE(sch.ends_on,'9999-12-31')
             ORDER BY sch.starts_on, sch.id"
        );
        $charges->execute([(int) $row['academic_year_id']]);
        $inserted = 0;
        foreach ($charges->fetchAll(PDO::FETCH_ASSOC) as $charge) {
            if (!$this->enrollmentChargeApplies($charge, (int) $row['class_id'], (int) $row['student_type_id'], $isNewStudent, (int) $row['student_id'], $parentId)) continue;
            if (($charge['billing_model'] ?? '') === 'optional') continue;
            $tier = $this->resolveEnrollmentTier((int) $charge['id'], (int) $row['class_id'], (int) $row['student_type_id'], $existingParent, $isNewStudent);
            $unitPrice = (float) ($tier['amount'] ?? (($charge['calculation_mode'] ?? 'fixed') === 'per_unit'
                ? ($charge['unit_price'] ?? 0) : ($charge['amount'] ?? 0)));
            if ($unitPrice < 0) continue;
            $termId = (int) ($charge['academic_year_term_id'] ?? 0) ?: null;
            if ($termId === null) {
                $firstTerm = $this->db->prepare('SELECT id FROM academic_year_terms WHERE academic_year_id=? ORDER BY term_id,id LIMIT 1');
                $firstTerm->execute([(int) $row['academic_year_id']]);
                $termId = (int) ($firstTerm->fetchColumn() ?: 0) ?: null;
            }
            $dueDate = (string) $charge['starts_on'];
            if ($charge['schedule_frequency'] === 'per_term' && $termId) {
                $term = $this->db->prepare('SELECT opening_date FROM academic_year_terms WHERE id=? LIMIT 1');
                $term->execute([$termId]);
                $dueDate = (string) ($term->fetchColumn() ?: $dueDate);
            } elseif (!empty($charge['due_day']) && in_array($charge['schedule_frequency'], ['monthly','per_year'], true)) {
                $day = min(31, max(1, (int) $charge['due_day']));
                $dueDate = date('Y-m-', strtotime($dueDate)) . str_pad((string) min($day, (int) date('t', strtotime($dueDate))), 2, '0', STR_PAD_LEFT);
            }
            $quantity = 1.0;
            $insert = $this->db->prepare(
                "INSERT IGNORE INTO extra_charge_student_obligations
                 (student_academic_enrollment_id,schedule_id,academic_year_term_id,due_date,quantity,unit_price,amount_due,pricing_tier_id)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $insert->execute([$enrollmentId, (int) $charge['schedule_id'], $termId, $dueDate, $quantity,
                $unitPrice, round($unitPrice * $quantity, 2), $tier['id'] ?? null]);
            $inserted += $insert->rowCount();
        }
        return $inserted;
    }

    /** Allocate a confirmed learner payment to the oldest applicable extra-charge obligations. */
    public function allocateStudentPayment(int $paymentId): array
    {
        $paymentStmt = $this->db->prepare("SELECT id,student_id,amount,status,payment_purpose FROM payments WHERE id=? FOR UPDATE");
        $paymentStmt->execute([$paymentId]);
        $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment || !in_array($payment['status'], ['confirmed','completed','success'], true)
            || !in_array((string) ($payment['payment_purpose'] ?? 'fees'), ['fees','extra_charge'], true)) {
            return ['allocated' => 0.0, 'rows' => []];
        }
        $enrollmentStmt = $this->db->prepare("SELECT id FROM student_academic_enrollments WHERE student_id=? AND enrollment_status='active' ORDER BY academic_year_id DESC,id DESC LIMIT 1 FOR UPDATE");
        $enrollmentStmt->execute([(int) $payment['student_id']]);
        $enrollmentId = (int) ($enrollmentStmt->fetchColumn() ?: 0);
        if (!$enrollmentId) return ['allocated' => 0.0, 'rows' => []];
        $this->generateEnrollmentObligations($enrollmentId);
        $existingStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM extra_charge_student_payment_allocations WHERE payment_id=? AND status='allocated'");
        $existingStmt->execute([$paymentId]);
        $remaining = round(max(0, (float) $payment['amount'] - (float) $existingStmt->fetchColumn()), 2);
        if ($remaining <= 0) return ['allocated' => 0.0, 'rows' => []];
        $rowsStmt = $this->db->prepare(
            "SELECT eco.id,eco.amount_due,eco.amount_paid,eco.amount_waived,eco.status,
                    ec.gl_account_id,coa.account_code,ec.name
             FROM extra_charge_student_obligations eco
             JOIN extra_charge_schedules sch ON sch.id=eco.schedule_id
             JOIN extra_charges ec ON ec.id=sch.extra_charge_id
             LEFT JOIN chart_of_accounts coa ON coa.id=ec.gl_account_id
             WHERE eco.student_academic_enrollment_id=? AND eco.status IN ('pending','partial')
               AND ec.billing_model IN ('added_to_fees','paid_separately')
             ORDER BY eco.due_date,eco.id FOR UPDATE"
        );
        $rowsStmt->execute([$enrollmentId]);
        $allocatedRows = [];
        foreach ($rowsStmt->fetchAll(PDO::FETCH_ASSOC) as $obligation) {
            if ($remaining <= 0) break;
            if (trim((string) ($obligation['account_code'] ?? '')) === '') {
                \App\API\Includes\FileLogger::write('finance', [
                    'type' => 'configuration',
                    'action' => 'extra_charge_missing_income_account',
                    'obligation_id' => (int) $obligation['id'],
                ]);
                continue;
            }
            $balance = max(0, round((float) $obligation['amount_due'] - (float) $obligation['amount_paid'] - (float) $obligation['amount_waived'], 2));
            $amount = min($remaining, $balance);
            if ($amount <= 0) continue;
            $this->db->prepare("INSERT INTO extra_charge_student_payment_allocations(payment_id,obligation_id,amount,status) VALUES(?,?,?,'allocated')")
                ->execute([$paymentId, (int) $obligation['id'], $amount]);
            $paid = round((float) $obligation['amount_paid'] + $amount, 2);
            $status = ($paid + (float) $obligation['amount_waived']) >= (float) $obligation['amount_due'] ? 'paid' : 'partial';
            $this->db->prepare('UPDATE extra_charge_student_obligations SET amount_paid=?,status=?,updated_at=NOW() WHERE id=?')
                ->execute([$paid, $status, (int) $obligation['id']]);
            $allocatedRows[] = [
                'obligation_id' => (int) $obligation['id'],
                'amount' => $amount,
                'account_code' => (string) ($obligation['account_code'] ?? ''),
                'description' => (string) $obligation['name'],
            ];
            $remaining = round($remaining - $amount, 2);
        }
        return ['allocated' => round(array_sum(array_column($allocatedRows, 'amount')), 2), 'rows' => $allocatedRows];
    }

    /** Restore obligation balances if the source payment is reversed. */
    public function reverseStudentPaymentAllocations(int $paymentId): void
    {
        $stmt = $this->db->prepare("SELECT obligation_id,amount FROM extra_charge_student_payment_allocations WHERE payment_id=? AND status='allocated' FOR UPDATE");
        $stmt->execute([$paymentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $allocation) {
            $obligation = $this->db->prepare('SELECT amount_due,amount_paid,amount_waived FROM extra_charge_student_obligations WHERE id=? FOR UPDATE');
            $obligation->execute([(int) $allocation['obligation_id']]);
            $row = $obligation->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $paid = max(0, round((float) $row['amount_paid'] - (float) $allocation['amount'], 2));
            $status = ($paid + (float) $row['amount_waived']) >= (float) $row['amount_due'] ? 'paid'
                : (($paid + (float) $row['amount_waived']) > 0 ? 'partial' : 'pending');
            $this->db->prepare('UPDATE extra_charge_student_obligations SET amount_paid=?,status=?,updated_at=NOW() WHERE id=?')
                ->execute([$paid, $status, (int) $allocation['obligation_id']]);
        }
        $this->db->prepare("UPDATE extra_charge_student_payment_allocations SET status='reversed',reversed_at=NOW() WHERE payment_id=? AND status='allocated'")->execute([$paymentId]);
    }

    private function admissionChargeApplies(array $charge, array $application, int $parentId, int $studentId): bool
    {
        $chargeId = (int) $charge['id'];
        $applicationId = (int) $application['id'];
        if (($charge['admission_condition'] ?? 'any_new_admission') === 'interview_scheduled') {
            $interview = $this->db->prepare("SELECT 1 FROM admission_interviews WHERE application_id=? AND status IN ('scheduled','rescheduled') LIMIT 1");
            $interview->execute([$applicationId]);
            if (!$interview->fetchColumn()) return false;
        }
        if ($this->hasChargeStudents($chargeId) && (!$studentId || !$this->chargeIncludesId('extra_charge_students', 'student_id', $chargeId, $studentId))) return false;
        if ($this->hasChargeParents($chargeId) && (!$parentId || !$this->chargeIncludesId('extra_charge_parents', 'parent_id', $chargeId, $parentId))) return false;
        if ($this->hasChargeClasses($chargeId)) {
            $classStmt = $this->db->prepare('SELECT c.name FROM extra_charge_classes xcc JOIN classes c ON c.id=xcc.class_id WHERE xcc.extra_charge_id=?');
            $classStmt->execute([$chargeId]);
            $grade = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($application['grade_applying_for'] ?? '')));
            if ($grade === 'playground') $grade = 'playgroup';
            $matches = false;
            foreach ($classStmt->fetchAll(PDO::FETCH_COLUMN) as $className) {
                $configured = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $className));
                if ($configured === 'playground') $configured = 'playgroup';
                if ($configured === $grade) { $matches = true; break; }
            }
            if (!$matches) return false;
        }
        if ($this->hasChargeStudentTypes($chargeId)) {
            if (empty($application['student_type_code'])) return false;
            $typeStmt = $this->db->prepare('SELECT 1 FROM extra_charge_student_types xst JOIN student_types st ON st.id=xst.student_type_id WHERE xst.extra_charge_id=? AND UPPER(st.code)=UPPER(?) LIMIT 1');
            $typeStmt->execute([$chargeId, (string) $application['student_type_code']]);
            if (!$typeStmt->fetchColumn()) return false;
        }
        return true;
    }

    private function resolveAdmissionTier(int $chargeId, array $application, bool $existingParent): ?array
    {
        $classId = 0;
        $grade = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($application['grade_applying_for'] ?? '')));
        if ($grade === 'playground') $grade = 'playgroup';
        $classes = $this->db->query('SELECT id,name FROM classes')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($classes as $class) {
            $candidate = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $class['name']));
            if ($candidate === 'playground') $candidate = 'playgroup';
            if ($candidate === $grade) { $classId = (int) $class['id']; break; }
        }
        $typeCode = strtolower((string) ($application['student_type_code'] ?? ''));
        $stmt = $this->db->prepare('SELECT id,condition_code,label,amount,sort_order FROM extra_charge_pricing_tiers WHERE extra_charge_id=? ORDER BY sort_order,id');
        $stmt->execute([$chargeId]);
        $matches = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tier) {
            $condition = strtolower(trim((string) $tier['condition_code']));
            $match = match (true) {
                in_array($condition, ['default','standard','all'], true) => true,
                in_array($condition, ['new_parent','new'], true) => !$existingParent,
                in_array($condition, ['existing_parent','existing'], true) => $existingParent,
                $condition === 'new_student' => true,
                preg_match('/^class[:_](\d+)$/', $condition, $m) === 1 => $classId > 0 && (int) $m[1] === $classId,
                preg_match('/^student_type[:_]([a-z0-9_-]+)$/', $condition, $m) === 1 => $typeCode !== '' && strtolower($m[1]) === $typeCode,
                default => false,
            };
            if (!$match) continue;
            $tier['_specificity'] = str_starts_with($condition, 'class') ? 4
                : (str_starts_with($condition, 'student_type') ? 3
                    : (str_contains($condition, 'parent') || str_contains($condition, 'student') || in_array($condition, ['new','existing'], true) ? 2 : 1));
            $matches[] = $tier;
        }
        usort($matches, static fn(array $a, array $b): int => ($b['_specificity'] <=> $a['_specificity']) ?: ((int) $a['sort_order'] <=> (int) $b['sort_order']));
        return $matches[0] ?? null;
    }

    private function enrollmentChargeApplies(array $charge, int $classId, int $studentTypeId, bool $isNewStudent, int $studentId, int $parentId): bool
    {
        $scope = (string) ($charge['target_scope'] ?? 'all_students');
        if ($scope === 'new_admissions') return false;
        if ($scope === 'existing_students' && $isNewStudent) return false;
        if ($this->hasChargeStudents((int) $charge['id']) && !$this->chargeIncludesId('extra_charge_students', 'student_id', (int) $charge['id'], $studentId)) return false;
        if ($this->hasChargeParents((int) $charge['id']) && !$this->studentHasAssignedParent((int) $charge['id'], $studentId)) return false;
        if ($scope === 'boarders' || $scope === 'day_students') {
            $type = $this->db->prepare('SELECT code FROM student_types WHERE id=? LIMIT 1');
            $type->execute([$studentTypeId]);
            $code = strtoupper((string) $type->fetchColumn());
            if (($scope === 'boarders' && $code !== 'BOARD') || ($scope === 'day_students' && $code !== 'DAY')) return false;
        }
        if ($scope === 'specific_class' || $this->hasChargeClasses((int) $charge['id'])) {
            $classes = $this->db->prepare('SELECT 1 FROM extra_charge_classes WHERE extra_charge_id=? AND class_id=? LIMIT 1');
            $classes->execute([(int) $charge['id'], $classId]);
            if (!$classes->fetchColumn()) return false;
        }
        if ($this->hasChargeStudentTypes((int) $charge['id'])) {
            $types = $this->db->prepare('SELECT 1 FROM extra_charge_student_types WHERE extra_charge_id=? AND student_type_id=? LIMIT 1');
            $types->execute([(int) $charge['id'], $studentTypeId]);
            if (!$types->fetchColumn()) return false;
        }
        return true;
    }

    private function resolveEnrollmentTier(int $chargeId, int $classId, int $studentTypeId, bool $existingParent, bool $isNewStudent): ?array
    {
        $stmt = $this->db->prepare('SELECT id,condition_code,label,amount,sort_order FROM extra_charge_pricing_tiers WHERE extra_charge_id=? ORDER BY sort_order,id');
        $stmt->execute([$chargeId]);
        $typeStmt = $this->db->prepare('SELECT code FROM student_types WHERE id=? LIMIT 1');
        $typeStmt->execute([$studentTypeId]);
        $typeCode = strtolower((string) $typeStmt->fetchColumn());
        $matches = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tier) {
            $condition = strtolower(trim((string) $tier['condition_code']));
            $match = match (true) {
                in_array($condition, ['default','standard','all'], true) => 1,
                in_array($condition, ['existing_parent','existing'], true) => $existingParent,
                in_array($condition, ['new_parent','new'], true) => !$existingParent,
                in_array($condition, ['new_student'], true) => $isNewStudent,
                in_array($condition, ['existing_student'], true) => !$isNewStudent,
                preg_match('/^class[:_](\d+)$/', $condition, $m) === 1 => (int) $m[1] === $classId,
                preg_match('/^student_type[:_]([a-z0-9_-]+)$/', $condition, $m) === 1 => strtolower($m[1]) === $typeCode,
                default => false,
            };
            if ($match) {
                $specificity = str_starts_with($condition, 'class') ? 4
                    : (str_starts_with($condition, 'student_type') ? 3
                        : (str_contains($condition, 'parent') || str_contains($condition, 'student') ? 2 : 1));
                $tier['_specificity'] = $specificity;
                $matches[] = $tier;
            }
        }
        usort($matches, static fn(array $a, array $b): int => ($b['_specificity'] <=> $a['_specificity']) ?: ((int) $a['sort_order'] <=> (int) $b['sort_order']));
        return $matches[0] ?? null;
    }

    private function hasChargeClasses(int $chargeId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM extra_charge_classes WHERE extra_charge_id=? LIMIT 1');
        $stmt->execute([$chargeId]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasChargeStudentTypes(int $chargeId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM extra_charge_student_types WHERE extra_charge_id=? LIMIT 1');
        $stmt->execute([$chargeId]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasChargeStudents(int $chargeId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM extra_charge_students WHERE extra_charge_id=? LIMIT 1');
        $stmt->execute([$chargeId]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasChargeParents(int $chargeId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM extra_charge_parents WHERE extra_charge_id=? LIMIT 1');
        $stmt->execute([$chargeId]);
        return (bool) $stmt->fetchColumn();
    }

    private function chargeIncludesId(string $table, string $column, int $chargeId, int $relatedId): bool
    {
        if (!in_array([$table, $column], [['extra_charge_students', 'student_id'], ['extra_charge_parents', 'parent_id']], true)) return false;
        $stmt = $this->db->prepare("SELECT 1 FROM {$table} WHERE extra_charge_id=? AND {$column}=? LIMIT 1");
        $stmt->execute([$chargeId, $relatedId]);
        return (bool) $stmt->fetchColumn();
    }

    private function studentHasAssignedParent(int $chargeId, int $studentId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM extra_charge_parents ecp JOIN student_parents sp ON sp.parent_id=ecp.parent_id WHERE ecp.extra_charge_id=? AND sp.student_id=? LIMIT 1');
        $stmt->execute([$chargeId, $studentId]);
        return (bool) $stmt->fetchColumn();
    }

    private function application(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admission_applications WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function academicYearId(string $value): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM academic_years WHERE year_code=? OR YEAR(start_date)=? OR id=? LIMIT 1'
        );
        $numeric = (int) $value;
        $stmt->execute([$value, $numeric, $numeric]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}
