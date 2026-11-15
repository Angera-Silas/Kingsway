<?php
namespace App\API\Modules\reports;

use App\API\Services\ReadReplicaService;
use App\API\Includes\BaseAPI;

class AdmissionsReportManager extends BaseAPI
{
    public function getAdmissionStats($filters = [])
    {
        // Total admissions by year, class, gender — from admission_applications
        // (the normalized source of record: gender + grade applying for + academic year).
        try {
            $where = ["a.status = 'enrolled'"];
            $params = [];
            if (!empty($filters['academic_year_id'])) {
                $where[] = 'a.academic_year_id = ?';
                $params[] = (int) $filters['academic_year_id'];
            } elseif (!empty($filters['year'])) {
                $where[] = 'a.academic_year = ?';
                $params[] = $filters['year'];
            }
            if (!empty($filters['class_id'])) {
                $gradeName = $this->resolveClassName((int) $filters['class_id']);
                if ($gradeName !== null) {
                    $where[] = 'a.grade_applying_for = ?';
                    $params[] = $gradeName;
                }
            }
            $sql = "SELECT
                        a.academic_year AS year,
                        a.academic_year_id,
                        a.grade_applying_for AS class_name,
                        a.gender,
                        COUNT(*) AS total
                    FROM admission_applications a
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY a.academic_year, a.academic_year_id, a.grade_applying_for, a.gender
                    ORDER BY year DESC, class_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getConversionRates($filters = [])
    {
        // Conversion rate from applicants to admitted students — admission_applications
        // carries enrolled_student_id once the applicant is onboarded.
        try {
            $where = ["a.status <> 'cancelled'"];
            $params = [];
            if (!empty($filters['academic_year_id'])) { $where[] = 'a.academic_year_id = ?'; $params[] = (int) $filters['academic_year_id']; }
            elseif (!empty($filters['academic_year'])) { $where[] = 'a.academic_year = ?'; $params[] = $filters['academic_year']; }
            if (!empty($filters['grade'])) { $where[] = 'a.grade_applying_for = ?'; $params[] = $filters['grade']; }
            $sql = "SELECT
                        COUNT(DISTINCT a.id) AS total_applicants,
                        COUNT(DISTINCT a.enrolled_student_id) AS total_admitted,
                        ROUND(
                            COUNT(DISTINCT a.enrolled_student_id) / NULLIF(COUNT(DISTINCT a.id), 0) * 100,
                            2
                        ) AS conversion_rate
                    FROM admission_applications a
                    WHERE " . implode(' AND ', $where);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Never report a failure as "0 applicants": that reads as real
            // data and the assistant built conclusions on it. Let the governed
            // executor report the report as unavailable.
            \App\API\Services\Logger::legacyError('[AdmissionsReportManager] conversion rates failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAlumniStats($filters = [])
    {
        // Alumni count by graduation year and gender — graduations are recorded as
        // transition_type='graduation' rows in student_transitions.
        try {
            // A learner graduates IN the year of the final-class enrolment they
            // completed (the outgoing year), not the date the transition ran:
            // graduation_year 2026 = the "graduating class of 2026"
            // (academic year 2026/2027).
            $where = ["st.transition_type = 'graduation'"];
            $params = [];
            if (!empty($filters['academic_year_id'])) {
                $where[] = 'gay.id = ?';
                $params[] = (int) $filters['academic_year_id'];
            } elseif (!empty($filters['graduation_year'])) {
                $where[] = 'YEAR(gay.start_date) = ?';
                $params[] = (int) $filters['graduation_year'];
            }
            $sql = "SELECT
                        YEAR(gay.start_date) AS graduation_year,
                        gay.id AS academic_year_id,
                        gay.year_code AS academic_year,
                        p.gender,
                        COUNT(*) AS alumni_count
                    FROM " . ReadReplicaService::qualifiedRef("student_transitions") . " st
                    JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = st.student_id
                    JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = st.from_student_academic_enrollment_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " gay ON gay.id = COALESCE(sae.academic_year_id, st.academic_year_id)
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY gay.id, gay.year_code, graduation_year, p.gender
                    ORDER BY graduation_year DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function resolveClassName(int $classId): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT name FROM classes WHERE id = ?");
            $stmt->execute([$classId]);
            $name = $stmt->fetchColumn();
            return $name === false ? null : (string) $name;
        } catch (\Exception $e) {
            return null;
        }
    }
}
