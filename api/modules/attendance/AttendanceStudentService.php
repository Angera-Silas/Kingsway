<?php

namespace App\API\Modules\attendance;

use App\API\Controllers\BaseController;

class AttendanceStudentService
{
    private AttendanceAPI $api;

    public function __construct(AttendanceAPI $api)
    {
        $this->api = $api;
    }

    public function getStudentHistory($id, $data, $segments, BaseController $controller)
    {
        $studentId = $id ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceHistory($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentSummary($id, $data, $segments, BaseController $controller) {
        $studentId = $id ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceSummary($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getClassAttendance($id, $data, $segments, BaseController $controller) {
        $classId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getClassAttendance($classId, $termId, $yearId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentPercentage($id, $data, $segments, BaseController $controller) {
        $studentId = $id;
        try {
            $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
            $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days FROM student_attendance WHERE student_id = ?";
            $params = [$studentId];
            if ($termId) { $sql .= " AND term_id = ?"; $params[] = $termId; }
            $result = $controller->getDb()->query($sql, $params);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            $total = (int) ($row['total_days'] ?? 0);
            $present = (int) ($row['present_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 2) : 0;
            return $controller->success(['student_id' => $studentId, 'total_days' => $total, 'present_days' => $present, 'percentage' => $percentage, 'term_id' => $termId], 'Attendance percentage calculated');
        } catch (\Exception $e) {
            error_log('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function getChronicStudentAbsentees($id, $data, $segments, BaseController $controller) {
        $classId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentsByClass($id, $data, $segments, BaseController $controller) {
        try {
            $streamId = $id ?? $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!$streamId) { return $controller->badRequest('Missing stream_id'); }
            $scope = $controller->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) { return $controller->forbidden('You are not allowed to access this class attendance register'); }
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $query = "SELECT s.id, s.admission_no, s.first_name, s.last_name, st.name as student_type, st.code as student_type_code, sa.id as attendance_id, sa.status as stored_status, sa.absence_reason, CASE WHEN sa.absence_reason = 'permission' THEN 'permission' ELSE sa.status END as attendance_status, CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as has_permission, spt.code as permission_type_code, spt.name as permission_type, sp.reason as permission_reason FROM students s LEFT JOIN student_types st ON s.student_type_id = st.id LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ? LEFT JOIN student_permissions sp ON sp.student_id = s.id AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved' LEFT JOIN student_permission_types spt ON spt.id = sp.permission_type_id WHERE s.stream_id = ? AND s.status = 'active' ORDER BY s.last_name, s.first_name";
            $result = $controller->getDb()->query($query, [$date, $date, $streamId]);
            $students = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $controller->success($students, 'Students retrieved successfully');
        } catch (\Exception $e) {
            error_log('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function postMarkBulk($id, $data, $segments, BaseController $controller) {
        try {
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $sessionId = $data['session_id'] ?? null;
            $registerType = $data['register_type'] ?? 'class';
            if (!$streamId) { return $controller->badRequest('Stream ID is required'); }
            if (empty($attendance)) { return $controller->badRequest('Attendance data is required'); }
            $scope = $controller->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) { return $controller->forbidden('You are not allowed to mark attendance for this class'); }
            $termInfo = $controller->_resolveTermForDate($date);
            $termId = $termInfo['term_id'] ?? null;
            $yearId = $termInfo['year_id'] ?? null;
            $markedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $created = 0; $updated = 0;
            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $status = strtolower((string)($record['status'] ?? 'present'));
                $reason = $record['absence_reason'] ?? null;
                if (!$studentId) continue;
                if (!in_array($status, ['present', 'absent', 'late'], true)) $status = 'present';
                $existing = $controller->getDb()->query("SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND register_type = ? AND (session_id = ? OR (session_id IS NULL AND ? IS NULL))", [$studentId, $date, $registerType, $sessionId, $sessionId])->fetch(\PDO::FETCH_ASSOC);
                if ($existing) {
                    $controller->getDb()->query("UPDATE student_attendance SET status = ?, absence_reason = ?, marked_by = ?, updated_at = NOW() WHERE id = ?", [$status, $reason, $markedBy, $existing['id']]);
                    $updated++;
                } else {
                    $controller->getDb()->query("INSERT INTO student_attendance (student_id, date, academic_year_id, term_id, register_type, session_id, status, absence_reason, marked_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", [$studentId, $date, $yearId, $termId, $registerType, $sessionId, $status, $reason, $markedBy]);
                    $created++;
                }
            }
            return $controller->success(['created' => $created, 'updated' => $updated, 'total' => $created + $updated, 'date' => $date, 'stream_id' => $streamId], 'Attendance marked successfully');
        } catch (\Exception $e) {
            error_log('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentHistoryByYear($id, $data, $segments, BaseController $controller) {
        $studentId = $id ?? ($segments[0] ?? null);
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        try {
            $rows = $controller->getDb()->query(
                "SELECT sa.academic_year_id, ay.year_code, ay.year_name, sa.term_id, at2.term_number, at2.name AS term_name, sa.class_id, c.name AS class_name, sa.register_type, sa.date, sa.status, sa.absence_reason, sa.session_id, ass.name AS session_name, ass.session_type FROM student_attendance sa LEFT JOIN academic_years ay ON ay.id = sa.academic_year_id LEFT JOIN academic_terms at2 ON at2.id = sa.term_id LEFT JOIN classes c ON c.id = sa.class_id LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id WHERE sa.student_id = ? ORDER BY sa.date ASC, sa.session_id ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rows as $r) {
                $yk = $r['year_code'] ?? 'unknown'; $tk = $r['term_id'] ?? 0; $rt = $r['register_type'];
                if (!isset($grouped[$yk])) $grouped[$yk] = ['year_name' => $r['year_name'], 'year_code' => $r['year_code'], 'terms' => []];
                if (!isset($grouped[$yk]['terms'][$tk])) $grouped[$yk]['terms'][$tk] = ['term_name' => $r['term_name'], 'term_number' => $r['term_number'], 'class_name' => $r['class_name'], 'records' => [], 'summary' => ['class' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0], 'boarding' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0]]];
                $grouped[$yk]['terms'][$tk]['records'][] = $r;
                if (isset($grouped[$yk]['terms'][$tk]['summary'][$rt])) { $grouped[$yk]['terms'][$tk]['summary'][$rt][$r['status'] ?? 'absent']++; $grouped[$yk]['terms'][$tk]['summary'][$rt]['total']++; }
            }
            foreach ($grouped as &$y) { ksort($y['terms']); $y['terms'] = array_values($y['terms']); }
            return $controller->success(['student_id' => $studentId, 'by_year' => array_values($grouped), 'total_rows' => count($rows)]);
        } catch (\Exception $e) {
            error_log('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }
}
