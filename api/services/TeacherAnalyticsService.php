<?php
namespace App\API\Services;

use App\Database\Database;
use Exception;

class TeacherAnalyticsService
{
    protected $db;
    protected $userId;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

    public function getMyClass()
    {
        // Find teacher's assigned class
        $sql = "SELECT c.name as class_name, c.grade_level as form, s.name as stream,
                       COUNT(DISTINCT sae.id) as student_count
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN streams s ON aycs.stream_id = s.id
                LEFT JOIN student_academic_enrollments sae ON aycs.id = sae.academic_year_class_stream_id
                  AND sae.enrollment_status = 'active'
                WHERE aycs.class_teacher_id = ?
                GROUP BY aycs.id
                LIMIT 1";
        $stmt = $this->db->query($sql, [$this->userId]);
        $classData = $stmt->fetch();
        if (!$classData) {
            return null;
        }
        return [
            'total_students' => (int) ($classData['student_count'] ?? 0),
            'class_name' => $classData['name'] ?? '',
            'form' => $classData['form'] ?? '',
            'stream' => $classData['stream'] ?? ''
        ];
    }

    public function getMyAttendanceToday()
    {
        // Query DB for today's attendance for this teacher's class
        $sql = "SELECT 
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
                    IFNULL(ROUND(100 * SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id),0)),0) as percentage
                FROM student_attendance a
                JOIN student_academic_enrollments sae ON a.student_academic_enrollment_id = sae.id
                JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                WHERE aycs.class_teacher_id = ? AND a.date = CURDATE()";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'present' => (int) ($row['present'] ?? 0),
            'absent' => (int) ($row['absent'] ?? 0),
            'on_leave' => (int) ($row['on_leave'] ?? 0),
            'percentage' => (int) ($row['percentage'] ?? 0)
        ];
    }
}
