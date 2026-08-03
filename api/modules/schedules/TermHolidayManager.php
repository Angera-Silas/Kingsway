<?php
namespace App\API\Modules\schedules;

use Exception;
use PDO;

/**
 * Term & Holiday Manager
 * Handles CRUD and coordination logic for academic terms and holidays
 */
class TermHolidayManager
{

    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)
    public function getStudentSchedules($studentId, $termId = null)
    {
        $params = ['student_id' => $studentId];
        $sql = "SELECT
                    cs.*
                FROM vw_timetable_entries cs
                WHERE cs.class_id = (
                    SELECT ayc.class_id
                    FROM student_academic_enrollments se
                    JOIN academic_year_class_streams aycs ON aycs.id = se.academic_year_class_stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    WHERE se.student_id = :student_id
                      AND se.enrollment_status = 'active'
                    ORDER BY se.id DESC
                    LIMIT 1
                )
                  AND cs.status = 'scheduled'";
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $holidaysSql = "SELECT d.id, d.date, cdt.code AS day_type, cdt.name AS day_type_name, d.title, d.description, ayt.term_id AS term_id
                        FROM academic_year_calendar_days d
                        JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                        JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                        LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                        WHERE cdt.code IN ('public_holiday', 'school_holiday', 'holiday')";
        $holidayParams = [];
        if ($termId) {
            $holidaysSql .= " AND ac.academic_year_term_id = :term_id";
            $holidayParams['term_id'] = $termId;
        }
        $holidaysSql .= " ORDER BY d.date ASC";
        $holidayStmt = $this->db->prepare($holidaysSql);
        $holidayStmt->execute($holidayParams);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'schedules' => $schedules,
            'holidays' => $holidays
        ];
    }

    // STAFF: Get all schedules relevant to a staff member (teaching, invigilation, events, holidays)
    public function getStaffSchedules($staffId, $termId = null)
    {
        $params = ['staff_id' => $staffId];
        $sql = "SELECT
                    cs.*
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = :staff_id
                  AND cs.status = 'scheduled'";
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $teaching = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $holidaySql = "SELECT d.id, d.date, cdt.code AS day_type, cdt.name AS day_type_name, d.title, d.description, ayt.term_id AS term_id
                       FROM academic_year_calendar_days d
                       JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                       JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                       LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                       WHERE cdt.code IN ('public_holiday', 'school_holiday', 'holiday')";
        $holidayParams = [];
        if ($termId) {
            $holidaySql .= " AND ac.academic_year_term_id = :term_id";
            $holidayParams['term_id'] = $termId;
        }
        $holidaySql .= " ORDER BY d.date ASC";
        $holidayStmt = $this->db->prepare($holidaySql);
        $holidayStmt->execute($holidayParams);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'teaching' => $teaching,
            'holidays' => $holidays
        ];
    }

    // ADMIN: Get a full overview of terms, holidays, and all schedules for a given term
    public function getAdminTermOverview($termId)
    {
        // Term details
        $stmt = $this->db->prepare("
            SELECT ayt.*, t.name AS term_name, t.code AS term_code, ay.year_code AS academic_year
            FROM academic_year_terms ayt
            LEFT JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
            WHERE ayt.id = :term_id
        ");
        $stmt->execute(['term_id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);

        // Holiday/special-day calendar entries
        $stmt = $this->db->prepare("
            SELECT d.id, d.date, cdt.code AS day_type, cdt.name AS day_type_name, d.title, d.description
            FROM academic_year_calendar_days d
            JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
            JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
            WHERE ac.academic_year_term_id = :term_id
              AND cdt.code IN ('public_holiday', 'school_holiday', 'holiday', 'special_event')
            ORDER BY d.date ASC
        ");
        $stmt->execute(['term_id' => $termId]);
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // All class schedules
        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                cs.class_name,
                cs.subject_name,
                cs.room_name,
                cs.teacher_name
            FROM vw_timetable_entries cs
            WHERE cs.academic_year_term_id = :term_id
              AND cs.status = 'scheduled'
            ORDER BY cs.day_of_week, cs.start_time
        ");
        $stmt->execute(['term_id' => $termId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Term-linked activity/exam counts for quick admin view
        $activityStmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM activity_schedule asch
            JOIN academic_year_terms ayt ON ayt.id = :term_id
            WHERE asch.schedule_date BETWEEN ayt.opening_date AND ayt.closing_date
        ");
        $activityStmt->execute(['term_id' => $termId]);
        $activitiesCount = (int) ($activityStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $examStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM exam_schedules WHERE term_id = :term_id");
        $examStmt->execute(['term_id' => $termId]);
        $examsCount = (int) ($examStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'term' => $term,
            'holidays' => $holidays,
            'schedules' => $schedules,
            'summary' => [
                'total_schedules' => count($schedules),
                'activities_count' => $activitiesCount,
                'exams_count' => $examsCount,
            ],
        ];
    }
    // Get detailed info for a term, including holidays, workflow status, and related schedules
    public function getTermDetails($termId)
    {
        $sql = "SELECT ayt.*, t.name AS term_name, t.code AS term_code, w.status as workflow_status
                FROM academic_year_terms ayt
                LEFT JOIN terms t ON t.id = ayt.term_id
                LEFT JOIN workflow_instances w ON w.reference_id = ayt.id AND w.workflow_id = 'term_holiday_scheduling'
                WHERE ayt.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_holidays'] = $stmt->fetchColumn();
        $stmt = $this->db->query("SELECT COUNT(*) as active_terms FROM academic_year_terms WHERE status = 'current'");
        $stats['active_terms'] = $stmt->fetchColumn();
        $stmt = $this->db->query("SELECT COUNT(*) as inactive_terms FROM academic_year_terms WHERE status != 'current'");
        $stats['inactive_terms'] = $stmt->fetchColumn();
        return $stats;
    }

    // Cross-link: get all classes/events scheduled in a term
    public function getTermClassesEvents($termId)
    {
        $result = [];
        $stmt = $this->db->prepare("SELECT * FROM vw_timetable_entries WHERE academic_year_term_id = :term_id AND status = 'scheduled'");
        $stmt->execute(['term_id' => $termId]);
        $result['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Example: events table
        if ($this->db->query("SHOW TABLES LIKE 'events'")) {
            $stmt = $this->db->prepare("SELECT * FROM events WHERE term_id = :term_id");
            $stmt->execute(['term_id' => $termId]);
            $result['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // Create a new academic term
    public function createTerm($data)
    {
        $sql = "INSERT INTO academic_terms (name, start_date, end_date, year, status) VALUES (:name, :start_date, :end_date, :year, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    // Update an academic term
    public function updateTerm($termId, $data)
    {
        $sql = "UPDATE academic_terms SET name = :name, start_date = :start_date, end_date = :end_date, year = :year, status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $termId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return true;
    }

    // Delete an academic term
    public function deleteTerm($termId)
    {
        $sql = "DELETE FROM academic_terms WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $termId]);
        return true;
    }

    // List all academic terms
    public function listTerms($year = null)
    {
        $sql = "SELECT * FROM academic_terms";
        $params = [];
        if ($year) {
            $sql .= " WHERE year = :year";
            $params['year'] = $year;
        }
        $sql .= " ORDER BY start_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create a new holiday
    public function createHoliday($data)
    {
        $sql = "INSERT INTO holidays (name, start_date, end_date, year, status) VALUES (:name, :start_date, :end_date, :year, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    // Update a holiday
    public function updateHoliday($holidayId, $data)
    {
        $sql = "UPDATE holidays SET name = :name, start_date = :start_date, end_date = :end_date, year = :year, status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $holidayId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return true;
    }

    // Delete a holiday
    public function deleteHoliday($holidayId)
    {
        $sql = "DELETE FROM holidays WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $holidayId]);
        return true;
    }

    // List all holidays
    public function listHolidays($year = null)
    {
        $sql = "SELECT * FROM holidays";
        $params = [];
        if ($year) {
            $sql .= " WHERE year = :year";
            $params['year'] = $year;
        }
        $sql .= " ORDER BY start_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
