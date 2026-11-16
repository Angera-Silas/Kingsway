<?php

namespace App\API\Services;

use App\Database\Database;
use InvalidArgumentException;
use PDO;

/**
 * Exit documents of a learner who leaves the school, issued after clearance:
 *   - SCHOOL LEAVING CERTIFICATE for graduates, graduating Grade 9 learners
 *     and withdrawn learners (the Kenya Primary School Leaving Certificate
 *     layout, in Grades: KNEC issues result slips, not certificates, for
 *     KPSEA and KJSEA, so this is the learner's record of completion);
 *   - TRANSFER LETTER for a learner moving to another school (addressed to
 *     the receiving head teacher, with the learner's NEMIS/UPI and assessment
 *     numbers, for the Sub-County Director of Education's endorsement).
 *
 * learner_exit_documents is the register: one document per learner, year and
 * type, with a serial number and the head teacher's report, so a reprint is
 * identical to the original.
 */
class LearnerExitDocumentService
{
    private PDO $db;
    private StudentClearanceService $clearance;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->clearance = new StudentClearanceService($this->db);
    }

    /** What the issue form shows: the learner, stored document and defaults. */
    public function context(int $studentId, int $yearId): array
    {
        $learner = $this->clearance->learner($studentId, $yearId);
        $document = $this->storedDocument($studentId, $yearId, $learner['document_type']);
        return [
            'learner' => $learner,
            'facts' => $this->facts($studentId, $yearId, $learner),
            'document' => $document,
            'defaults' => [
                'leaving_date' => $document['leaving_date'] ?? $this->defaultLeavingDate($learner, $yearId),
                'academic_report' => $document['academic_report'] ?? '',
                'conduct' => $document['conduct'] ?? '',
                'co_curricular' => $document['co_curricular'] ?? '',
            ],
        ];
    }

    /**
     * Issue (or reprint) the learner's exit document after clearance and
     * return the PDF. The head teacher's report and the receiving school are
     * saved with it.
     *
     * @return array{serial_no:string, path:string, document_type:string}
     */
    public function issue(int $studentId, int $yearId, array $input, ?int $userId): array
    {
        $learner = $this->clearance->learner($studentId, $yearId);
        if (!$learner['certificate_ready']) {
            throw new InvalidArgumentException('Every mandatory clearance check must be cleared before the ' . strtolower($learner['document_label']) . ' is issued.', 422);
        }
        $type = $learner['document_type'];
        $text = static fn ($v, int $max = 2000): ?string => ($v = trim((string) ($v ?? ''))) === '' ? null : mb_substr($v, 0, $max);
        $academic = $text($input['academic_report'] ?? null);
        $conduct = $text($input['conduct'] ?? null);
        $coCurricular = $text($input['co_curricular'] ?? null);
        if ($academic === null || $conduct === null) {
            throw new InvalidArgumentException("Write the head teacher's report on the learner's academic ability and conduct.", 422);
        }
        $leavingDate = !empty($input['leaving_date']) ? date('Y-m-d', strtotime((string) $input['leaving_date'])) : $this->defaultLeavingDate($learner, $yearId);

        if ($type === 'transfer_letter') {
            $school = $text($input['destination_school'] ?? ($learner['destination']['school'] ?? null), 200);
            if ($school === null) {
                throw new InvalidArgumentException('Give the receiving school for the transfer letter.', 422);
            }
            $this->db->prepare(
                "UPDATE student_transitions
                    SET destination_school = ?, destination_school_code = ?, destination_county = ?, destination_sub_county = ?,
                        reason = COALESCE(?, reason)
                  WHERE id = ?"
            )->execute([
                $school,
                $text($input['destination_school_code'] ?? ($learner['destination']['school_code'] ?? null), 40),
                $text($input['destination_county'] ?? ($learner['destination']['county'] ?? null), 100),
                $text($input['destination_sub_county'] ?? ($learner['destination']['sub_county'] ?? null), 100),
                $text($input['reason'] ?? null, 500),
                (int) $learner['transfer_id'],
            ]);
        }

        $this->db->beginTransaction();
        try {
            $existing = $this->storedDocument($studentId, $yearId, $type);
            if ($existing) {
                $this->db->prepare(
                    "UPDATE learner_exit_documents
                        SET academic_report = ?, conduct = ?, co_curricular = ?, leaving_date = ?,
                            print_count = print_count + 1, last_printed_at = NOW()
                      WHERE id = ?"
                )->execute([$academic, $conduct, $coCurricular, $leavingDate, (int) $existing['id']]);
                $serial = (string) $existing['serial_no'];
            } else {
                $serial = $this->nextSerial($type, $yearId);
                $this->db->prepare(
                    "INSERT INTO learner_exit_documents
                        (student_id, academic_year_id, student_transition_id, document_type, serial_no,
                         academic_report, conduct, co_curricular, leaving_date, issued_by, print_count, last_printed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
                )->execute([$studentId, $yearId, $learner['transfer_id'], $type, $serial, $academic, $conduct, $coCurricular, $leavingDate, $userId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $context = $this->context($studentId, $yearId);
        $path = (new PrintService())->printLearnerExitDocument($type, $context);
        return ['serial_no' => $serial, 'path' => $path, 'document_type' => $type];
    }

    /**
     * Complete a transfer once the learner is cleared and the transfer letter
     * is issued: the enrolment the learner leaves from becomes transferred
     * (earlier years stay as they are), the learner's status becomes
     * transferred, and the NEMIS release is recorded.
     */
    public function completeTransfer(int $studentId, int $yearId, ?string $nemisReleasedAt, ?int $userId): array
    {
        $learner = $this->clearance->learner($studentId, $yearId);
        if ($learner['outcome_key'] !== 'transferring') {
            throw new InvalidArgumentException('This learner has no open transfer request.', 422);
        }
        if (!$learner['can_complete_transfer']) {
            throw new InvalidArgumentException('Clear every mandatory check and issue the transfer letter before completing the transfer.', 422);
        }
        $released = $nemisReleasedAt ? date('Y-m-d', strtotime($nemisReleasedAt)) : null;
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE student_transitions
                    SET executed_at = COALESCE(executed_at, NOW()), decided_by = COALESCE(decided_by, ?),
                        nemis_released_at = ?, nemis_released_by = ?
                  WHERE id = ? AND transition_type = 'transfer'"
            )->execute([$userId, $released, $released ? $userId : null, (int) $learner['transfer_id']]);
            $this->db->prepare(
                "UPDATE student_academic_enrollments SET enrollment_status = 'transferred'
                  WHERE student_id = ? AND enrollment_status IN ('active', 'pending')
                    AND academic_year_id >= ?"
            )->execute([$studentId, $yearId]);
            $this->db->prepare("UPDATE students SET status = 'transferred' WHERE id = ?")->execute([$studentId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->clearance->learner($studentId, $yearId);
    }

    // ------------------------------------------------------------------

    /** Everything the documents print about the learner. */
    private function facts(int $studentId, int $yearId, array $learner): array
    {
        $person = $this->one(
            "SELECT full_name, first_name, middle_name, last_name, dob, gender, admission_no, admission_date
               FROM " . ReadReplicaService::qualifiedRef('person_directory') . " WHERE student_id = ? LIMIT 1",
            [$studentId]
        ) ?? [];
        $student = $this->one('SELECT nemis_number, assessment_number, admission_date FROM students WHERE id = ?', [$studentId]) ?? [];
        $history = ReadReplicaService::qualifiedRef('learner_placement_history');
        $first = $this->one(
            "SELECT lp.class_name, lp.academic_year_id FROM {$history} lp WHERE lp.student_id = ?
              ORDER BY lp.academic_year_id ASC, lp.enrollment_id ASC LIMIT 1",
            [$studentId]
        ) ?? [];
        $firstYear = !empty($first['academic_year_id'])
            ? $this->one('SELECT year_code, start_date FROM academic_years WHERE id = ?', [(int) $first['academic_year_id']])
            : null;
        $leaving = $this->one(
            "SELECT lp.class_name, lp.stream_name FROM {$history} lp WHERE lp.enrollment_id = ? LIMIT 1",
            [(int) $learner['enrollment_id']]
        ) ?? [];
        $year = $this->one('SELECT year_code, start_date, end_date FROM academic_years WHERE id = ?', [$yearId]) ?? [];
        $exams = $this->rows(
            "SELECT exam_type, exam_year, assessment_number, overall_level, total_points, pathway
               FROM national_exam_candidates WHERE student_id = ? ORDER BY exam_year, exam_type",
            [$studentId]
        );
        $admissionDate = $student['admission_date'] ?? $person['admission_date'] ?? null;
        return [
            'full_name' => trim((string) ($person['full_name'] ?? $learner['name'])),
            'admission_no' => (string) ($person['admission_no'] ?? $learner['admission_no']),
            'upi' => (string) ($student['nemis_number'] ?? ''),
            'assessment_number' => (string) ($student['assessment_number'] ?? ''),
            'dob' => $person['dob'] ?? null,
            'gender' => ucfirst((string) ($person['gender'] ?? '')),
            'admission_date' => $admissionDate,
            'admission_year' => $firstYear['year_code'] ?? null,
            'admission_class' => (string) ($first['class_name'] ?? ''),
            'leaving_class' => (string) ($leaving['class_name'] ?? ''),
            'leaving_stream' => (string) ($leaving['stream_name'] ?? ''),
            'academic_year' => (string) ($year['year_code'] ?? ''),
            'national_assessments' => $exams,
        ];
    }

    /** Graduates leave when the year's last term closes; transfers on their expected date or today. */
    private function defaultLeavingDate(array $learner, int $yearId): string
    {
        if (in_array($learner['outcome_key'], ['transferring', 'transferred'], true)) {
            return (string) ($learner['destination']['expected_leaving_date'] ?? '') ?: date('Y-m-d');
        }
        $closing = $this->one('SELECT MAX(closing_date) AS d FROM academic_year_terms WHERE academic_year_id = ?', [$yearId])['d'] ?? null;
        return $closing ?: date('Y-m-d');
    }

    private function storedDocument(int $studentId, int $yearId, string $type): ?array
    {
        return $this->one(
            'SELECT * FROM learner_exit_documents WHERE student_id = ? AND academic_year_id = ? AND document_type = ? LIMIT 1',
            [$studentId, $yearId, $type]
        );
    }

    /** LC/2026/0001 (leaving certificate) or TL/2026/0001 (transfer letter): per type and year. */
    private function nextSerial(string $type, int $yearId): string
    {
        $prefix = $type === 'transfer_letter' ? 'TL' : 'LC';
        $start = (string) ($this->one('SELECT start_date FROM academic_years WHERE id = ?', [$yearId])['start_date'] ?? date('Y-m-d'));
        $base = $prefix . '/' . substr($start, 0, 4) . '/';
        $last = $this->one(
            'SELECT serial_no FROM learner_exit_documents WHERE serial_no LIKE ? ORDER BY serial_no DESC LIMIT 1 FOR UPDATE',
            [$base . '%']
        );
        $next = $last ? ((int) substr((string) $last['serial_no'], strlen($base))) + 1 : 1;
        return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
