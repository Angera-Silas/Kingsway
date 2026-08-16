<?php
namespace App\API\Modules\schedules;

use App\API\Includes\BaseAPI;
use App\API\Modules\schedules\SchedulesManager;
use App\API\Modules\schedules\SchedulesWorkflow;
use App\API\Modules\schedules\TermHolidayManager;
use App\API\Modules\schedules\TermHolidayWorkflow;
use App\API\Services\CalendarSyncService;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use function App\API\Includes\dayNameToNumber;
use PDO;
use Exception;
use DateTime;

class SchedulesAPI extends BaseAPI {
    private SchedulesManager $manager;
    private SchedulesWorkflow $workflow;
    private $termHolidayManager;
    private $termHolidayWorkflow;
    private CalendarSyncService $calendarSync;

    public function __construct() {
        parent::__construct('schedules');
        $this->manager = new SchedulesManager($this->db);
        $this->workflow = new SchedulesWorkflow();
        $this->termHolidayManager = new TermHolidayManager($this->db);
        $this->termHolidayWorkflow = new TermHolidayWorkflow();
        $this->calendarSync = new CalendarSyncService($this->db);
        // (Instantiate other workflow handlers as needed)
    }

    // =============================
    // Role-Specific Schedule Coordination Methods
    // =============================

    // TEACHING STAFF: Get timetable for a teacher
    public function getTeacherSchedule($teacherId, $termId = null)
    {
        try {
            $result = $this->manager->getTeacherSchedule($teacherId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // SUBJECT SPECIALIST: Get teaching load for a subject
    public function getSubjectTeachingLoad($subjectId, $termId = null)
    {
        try {
            $result = $this->manager->getSubjectTeachingLoad($subjectId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ACTIVITIES COORDINATOR: Get all activity schedules
    public function getAllActivitySchedules($filters = [])
    {
        try {
            $result = $this->manager->getAllActivitySchedules($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($driverId, $termId = null)
    {
        try {
            $result = $this->manager->getDriverSchedule($driverId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // NON-TEACHING STAFF: Get duty schedules
    public function getStaffDutySchedule($staffId, $termId = null)
    {
        try {
            $result = $this->manager->getStaffDutySchedule($staffId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ADMIN: Get master schedule (all classes, activities, events, transport)
    public function getMasterSchedule($filters = [])
    {
        try {
            $result = $this->manager->getMasterSchedule($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ANALYTICS: Get schedule analytics (utilization, conflicts, compliance)
    public function getScheduleAnalytics($filters = [])
    {
        try {
            $result = $this->manager->getScheduleAnalytics($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getWeeklyLessonStats()
    {
        try {
            $startDate = new \DateTime('monday this week');
            $endDate = new \DateTime('sunday this week');

            $days = [];
            $counts = [];

            for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                $dayName = $date->format('D');
                $dayNum = (int) $date->format('N');
                $days[] = $dayName;

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) as total FROM vw_timetable_entries WHERE day_of_week = ? AND status = 'scheduled'"
                );
                $stmt->execute([$dayNum]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $counts[] = $row['total'] ?? 0;
            }

            $totalWeekly = array_sum($counts);
            $dailyAverage = count($counts) > 0 ? round($totalWeekly / count($counts), 1) : 0;

            return successResponse([
                'days' => $days,
                'data' => $counts,
                'total_weekly' => $totalWeekly,
                'daily_average' => $dailyAverage,
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $endDate->format('Y-m-d'),
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)

    public function getStudentSchedules($studentId, $termId = null)
    {
        try {
            $result = $this->termHolidayManager->getStudentSchedules($studentId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStaffSchedules($staffId, $termId = null)
    {
        try {
            $result = $this->termHolidayManager->getStaffSchedules($staffId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAdminTermOverview($termId)
    {
        try {
            $result = $this->termHolidayManager->getAdminTermOverview($termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Term & Holiday Workflow Endpoints
    // =============================

    public function defineTermDates($data)
    {
        try {
            $result = $this->termHolidayWorkflow->defineTermDates($data);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function reviewTermDates($instanceId)
    {
        try {
            $result = $this->termHolidayWorkflow->reviewTermDates($instanceId);
            // (Add similar endpoints for ExamsWorkflow, EventsWorkflow, etc. as needed)
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }


    public function checkResourceAvailability($resourceType, $resourceId, $start, $end)
    {
        try {
            $available = $this->manager->checkResourceAvailability($resourceType, $resourceId, $start, $end);
            return successResponse(['available' => $available]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function findOptimalSchedule($entityType, $entityId, $constraints = [])
    {
        try {
            $slots = $this->manager->findOptimalSchedule($entityType, $entityId, $constraints);
            return successResponse(['slots' => $slots]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function detectScheduleConflicts($entityType, $entityId, $proposedSchedule)
    {
        try {
            $conflicts = $this->manager->detectScheduleConflicts($entityType, $entityId, $proposedSchedule);
            return successResponse(['conflicts' => $conflicts]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function generateMasterSchedule($scope, $filters = [])
    {
        try {
            $schedule = $this->manager->generateMasterSchedule($scope, $filters);
            return successResponse(['schedule' => $schedule]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function validateScheduleCompliance($scheduleId)
    {
        try {
            $compliant = $this->manager->validateScheduleCompliance($scheduleId);
            return successResponse(['compliant' => $compliant]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Scheduling Workflow Methods
    // =============================

    public function startSchedulingWorkflow($data)
    {
        try {
            // Expecting $data to contain reference_type, reference_id, and optionally initial_data
            if (!isset($data['reference_type']) || !isset($data['reference_id'])) {
                return errorResponse('Missing required workflow parameters: reference_type, reference_id');
            }
            $reference_type = $data['reference_type'];
            $reference_id = $data['reference_id'];
            $initial_data = isset($data['initial_data']) ? $data['initial_data'] : [];
            $result = $this->workflow->startWorkflow($reference_type, $reference_id, $initial_data);
            return successResponse(['workflow' => $result]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function advanceSchedulingWorkflow($workflowId, $action, $data = [])
    {
        try {
            // No advanceWorkflow method in SchedulesWorkflow or WorkflowHandler; return error
            return errorResponse('advanceWorkflow is not implemented for SchedulesWorkflow.');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchedulingWorkflowStatus($workflowId)
    {
        try {
            $status = $this->workflow->getWorkflowStatus($workflowId);
            return successResponse(['workflow_status' => $status]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listSchedulingWorkflows($filters = [])
    {
        try {
            $workflows = $this->workflow->listWorkflows($filters);
            return successResponse(['workflows' => $workflows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTimetable($params = []) {
        try {
            $where = ["cs.status = 'scheduled'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = "cs.class_id = ?";
                $bindings[] = (int) $params['class_id'];
            }
            if (!empty($params['teacher_id'])) {
                $where[] = "cs.teacher_id = ?";
                $bindings[] = (int) $params['teacher_id'];
            }
            if (!empty($params['academic_year_id'])) {
                $where[] = "cs.academic_year_id = ?";
                $bindings[] = (int) $params['academic_year_id'];
            }
            $termId = $params['academic_year_term_id'] ?? $params['term_id'] ?? null;
            if (!empty($termId)) {
                $where[] = "cs.academic_year_term_id = ?";
                $bindings[] = (int) $termId;
            }
            if (!empty($params['academic_year_class_stream_id'])) {
                $where[] = "cs.academic_year_class_stream_id = ?";
                $bindings[] = (int) $params['academic_year_class_stream_id'];
            }
            if (!empty($params['day_of_week'])) {
                $dayNum = dayNameToNumber($params['day_of_week']);
                $where[] = "cs.day_of_week = ?";
                $bindings[] = $dayNum ?? $params['day_of_week'];
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT *
                FROM vw_timetable_entries cs
                WHERE $whereSql
                ORDER BY cs.day_of_week, cs.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($timetable);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createTimetableEntry($data) {
        try {
            $required = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            // Map 'day' shorthand to 'day_of_week' if needed
            if (empty($data['day_of_week']) && !empty($data['day'])) {
                $data['day_of_week'] = $data['day'];
            }

            $dayNum = dayNameToNumber($data['day_of_week']);
            if ($dayNum === null) {
                return errorResponse('Invalid day_of_week', 400);
            }

            $classStreamId = $this->resolveClassStreamId(
                (int) $data['class_id'],
                (int) ($data['academic_year_id'] ?? 0)
            );
            if ($classStreamId <= 0) {
                return errorResponse('No active class-stream found for the given class and academic year', 400);
            }

            $termId = $this->resolveAcademicYearTermId(
                (int) ($data['term_id'] ?? 0),
                (int) ($data['academic_year_id'] ?? 0)
            );
            if ($termId <= 0) {
                return errorResponse('No active term found for the given academic year', 400);
            }

            $learningAreaId = $this->resolveLearningAreaId((int) $data['subject_id']);
            if ($learningAreaId <= 0) {
                return errorResponse('Invalid subject', 400);
            }

            $timeSlotId = $this->resolveTimeSlotId(
                $data['time_slot_id'] ?? null,
                $data['start_time'],
                $data['end_time']
            );
            if ($timeSlotId === null) {
                return errorResponse('No time slot matches the given start/end time', 400);
            }

            // Check for teacher conflict
            $conflictSql = "
                SELECT cs.id, cs.class_name, cs.start_time, cs.end_time
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($conflictSql);
            $stmt->execute([(int) $data['teacher_id'], $dayNum, $termId, $data['end_time'], $data['start_time']]);
            $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacherConflict) {
                return errorResponse("Teacher already scheduled for {$teacherConflict['class_name']} at {$teacherConflict['start_time']}-{$teacherConflict['end_time']}", 409);
            }

            // Check for class conflict (same class, same time slot)
            $classSql = "
                SELECT cs.id FROM vw_timetable_entries cs
                WHERE cs.academic_year_class_stream_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classStreamId, $dayNum, $termId, $data['end_time'], $data['start_time']]);
            if ($stmt->fetch()) {
                return errorResponse("This class already has a lesson at this time slot", 409);
            }

            // Check for room conflict using the class stream's assigned room
            $roomStmt = $this->db->prepare("SELECT room_id FROM academic_year_class_streams WHERE id = ?");
            $roomStmt->execute([$classStreamId]);
            $roomId = (int) $roomStmt->fetchColumn();
            if ($roomId > 0) {
                $roomSql = "
                    SELECT cs.id, cs.class_name FROM vw_timetable_entries cs
                    WHERE cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                    AND cs.academic_year_term_id = ?
                    AND cs.start_time < ? AND cs.end_time > ?
                ";
                $stmt = $this->db->prepare($roomSql);
                $stmt->execute([$roomId, $dayNum, $termId, $data['end_time'], $data['start_time']]);
                $roomConflict = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($roomConflict) {
                    return errorResponse("Room already booked by {$roomConflict['class_name']} at this time", 409);
                }
            }

            $entryId = (int) $this->db->query("SELECT COALESCE(MAX(id),0)+1 FROM timetable_entries")->fetchColumn();

            $sql = "
                INSERT INTO timetable_entries (
                    id, academic_year_class_stream_id, academic_year_term_id, day_of_week,
                    time_slot_id, learning_area_id, teacher_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $entryId,
                $classStreamId,
                $termId,
                $dayNum,
                $timeSlotId,
                $learningAreaId,
                (int) $data['teacher_id']
            ]);

            return successResponse(['id' => $entryId, 'message' => 'Timetable entry created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateTimetableEntry($id, $data) {
        try {
            if (!$id) {
                return errorResponse('Timetable entry ID is required', 400);
            }

            // Check entry exists
            $stmt = $this->db->prepare("SELECT id FROM timetable_entries WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Timetable entry not found', 404);
            }

            $updates = [];
            $params = [];

            if (isset($data['class_id']) || isset($data['academic_year_class_stream_id'])) {
                $classStreamId = $this->resolveClassStreamId(
                    (int) ($data['class_id'] ?? 0),
                    (int) ($data['academic_year_id'] ?? 0)
                );
                if ($classStreamId <= 0) {
                    return errorResponse('No active class-stream found for the given class and academic year', 400);
                }
                $updates[] = "academic_year_class_stream_id = ?";
                $params[] = $classStreamId;
            }

            if (isset($data['subject_id']) || isset($data['learning_area_id'])) {
                $learningAreaId = $this->resolveLearningAreaId((int) ($data['subject_id'] ?? $data['learning_area_id']));
                if ($learningAreaId <= 0) {
                    return errorResponse('Invalid subject', 400);
                }
                $updates[] = "learning_area_id = ?";
                $params[] = $learningAreaId;
            }

            if (isset($data['teacher_id'])) {
                $updates[] = "teacher_id = ?";
                $params[] = (int) $data['teacher_id'];
            }

            if (isset($data['day_of_week']) || isset($data['day'])) {
                $dayNum = dayNameToNumber($data['day_of_week'] ?? $data['day']);
                if ($dayNum === null) {
                    return errorResponse('Invalid day_of_week', 400);
                }
                $updates[] = "day_of_week = ?";
                $params[] = $dayNum;
            }

            if (isset($data['start_time']) || isset($data['end_time']) || isset($data['time_slot_id'])) {
                $timeSlotId = $this->resolveTimeSlotId(
                    $data['time_slot_id'] ?? null,
                    $data['start_time'] ?? null,
                    $data['end_time'] ?? null
                );
                if ($timeSlotId === null) {
                    return errorResponse('No time slot matches the given start/end time', 400);
                }
                $updates[] = "time_slot_id = ?";
                $params[] = $timeSlotId;
            }

            if (isset($data['term_id']) || isset($data['academic_year_term_id'])) {
                $termId = $this->resolveAcademicYearTermId(
                    (int) ($data['term_id'] ?? $data['academic_year_term_id']),
                    (int) ($data['academic_year_id'] ?? 0)
                );
                if ($termId <= 0) {
                    return errorResponse('No active term found for the given academic year', 400);
                }
                $updates[] = "academic_year_term_id = ?";
                $params[] = $termId;
            }

            if (isset($data['status'])) {
                $status = $data['status'];
                if (!in_array($status, ['scheduled', 'cancelled', 'rescheduled'], true)) {
                    return errorResponse('Invalid status', 400);
                }
                $updates[] = "status = ?";
                $params[] = $status;
            }

            if (empty($updates)) {
                return errorResponse('No fields to update', 400);
            }

            // Build the effective post-update row so overlap checks can run against it
            $stmt = $this->db->prepare("SELECT * FROM timetable_entries WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $newStream = (int) ($current['academic_year_class_stream_id'] ?? 0);
            $newTerm = (int) ($current['academic_year_term_id'] ?? 0);
            $newDay = (int) ($current['day_of_week'] ?? 0);
            $newSlot = (int) ($current['time_slot_id'] ?? 0);
            $newTeacher = (int) ($current['teacher_id'] ?? 0);

            foreach ($updates as $i => $field) {
                switch ($field) {
                    case "academic_year_class_stream_id = ?":
                        $newStream = (int) $params[$i];
                        break;
                    case "academic_year_term_id = ?":
                        $newTerm = (int) $params[$i];
                        break;
                    case "day_of_week = ?":
                        $newDay = (int) $params[$i];
                        break;
                    case "time_slot_id = ?":
                        $newSlot = (int) $params[$i];
                        break;
                    case "teacher_id = ?":
                        $newTeacher = (int) $params[$i];
                        break;
                }
            }

            $times = $this->db->prepare("SELECT start_time, end_time FROM time_slots WHERE id = ?");
            $times->execute([$newSlot]);
            $timeRange = $times->fetch(PDO::FETCH_ASSOC);
            if (!$timeRange) {
                return errorResponse('Time slot not found', 400);
            }

            $conflictSql = "
                SELECT cs.id, cs.class_name, cs.start_time, cs.end_time
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ? AND cs.id <> ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($conflictSql);
            $stmt->execute([$newTeacher, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
            $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacherConflict) {
                return errorResponse("Teacher already scheduled for {$teacherConflict['class_name']} at {$teacherConflict['start_time']}-{$teacherConflict['end_time']}", 409);
            }

            $classSql = "
                SELECT cs.id FROM vw_timetable_entries cs
                WHERE cs.academic_year_class_stream_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ? AND cs.id <> ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$newStream, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
            if ($stmt->fetch()) {
                return errorResponse("This class already has a lesson at this time slot", 409);
            }

            $roomStmt = $this->db->prepare("SELECT room_id FROM academic_year_class_streams WHERE id = ?");
            $roomStmt->execute([$newStream]);
            $roomId = (int) $roomStmt->fetchColumn();
            if ($roomId > 0) {
                $roomSql = "
                    SELECT cs.id FROM vw_timetable_entries cs
                    WHERE cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                    AND cs.academic_year_term_id = ? AND cs.id <> ?
                    AND cs.start_time < ? AND cs.end_time > ?
                ";
                $stmt = $this->db->prepare($roomSql);
                $stmt->execute([$roomId, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
                if ($stmt->fetch()) {
                    return errorResponse("Room already booked at this time", 409);
                }
            }

            $params[] = $id;
            $sql = "UPDATE timetable_entries SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return successResponse(['message' => 'Timetable entry updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteTimetableEntry($id = null, $data = []) {
        try {
            // Support delete by ID or by day/time/class combo
            if ($id) {
                $stmt = $this->db->prepare("DELETE FROM timetable_entries WHERE id = ?");
                $stmt->execute([$id]);
            } elseif (!empty($data['class_id']) && !empty($data['day']) && !empty($data['start_time'])) {
                $day = $data['day_of_week'] ?? $data['day'];
                $dayNum = dayNameToNumber($day);
                if ($dayNum === null) {
                    return errorResponse('Invalid day_of_week', 400);
                }
                $classStreamId = $this->resolveClassStreamId(
                    (int) $data['class_id'],
                    (int) ($data['academic_year_id'] ?? 0)
                );
                $stmt = $this->db->prepare(
                    "DELETE FROM timetable_entries
                     WHERE academic_year_class_stream_id = ? AND day_of_week = ? AND time_slot_id = (
                         SELECT id FROM time_slots WHERE start_time = ? ORDER BY period_number LIMIT 1
                     )"
                );
                $stmt->execute([$classStreamId, $dayNum, $data['start_time']]);
            } else {
                return errorResponse('Entry ID or class_id + day + start_time required', 400);
            }

            if ($stmt->rowCount() === 0) {
                return errorResponse('Timetable entry not found', 404);
            }

            return successResponse(['message' => 'Timetable entry deleted successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function checkTimetableConflicts($params = []) {
        try {
            $conflicts = [];

            // Check teacher double-booking
            $sql = "
                SELECT
                    cs1.id as schedule_id_1, cs2.id as schedule_id_2,
                    cs1.day_of_week, cs1.start_time, cs1.end_time,
                    cs1.teacher_name,
                    cs1.class_name as class_1, cs2.class_name as class_2,
                    'teacher_overlap' as conflict_type
                FROM vw_timetable_entries cs1
                JOIN vw_timetable_entries cs2 ON cs1.teacher_id = cs2.teacher_id
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.academic_year_term_id = cs2.academic_year_term_id
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                WHERE cs1.status = 'scheduled' AND cs2.status = 'scheduled'
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $teacherConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($teacherConflicts as $c) {
                $c['description'] = "{$c['teacher_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on day {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
                $conflicts[] = $c;
            }

            // Check room double-booking
            $sql = "
                SELECT
                    cs1.id as schedule_id_1, cs2.id as schedule_id_2,
                    cs1.day_of_week, cs1.start_time, cs1.end_time,
                    cs1.room_name,
                    cs1.class_name as class_1, cs2.class_name as class_2,
                    'room_overlap' as conflict_type
                FROM vw_timetable_entries cs1
                JOIN vw_timetable_entries cs2 ON cs1.room_id = cs2.room_id
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.academic_year_term_id = cs2.academic_year_term_id
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                WHERE cs1.status = 'scheduled' AND cs2.status = 'scheduled'
                AND cs1.room_id IS NOT NULL
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roomConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($roomConflicts as $c) {
                $c['description'] = "{$c['room_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on day {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
                $conflicts[] = $c;
            }

            return successResponse([
                'conflicts' => $conflicts,
                'total' => count($conflicts),
                'has_conflicts' => count($conflicts) > 0
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function reportTimetableConflict($data) {
        try {
            $required = ['description'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Please describe the conflict'], 400);
            }

            $sql = "
                INSERT INTO timetable_conflicts (
                    reported_by, conflict_type, description,
                    day_of_week, time_slot, schedule_id_1, schedule_id_2, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'reported')
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['reported_by'] ?? 0,
                $data['conflict_type'] ?? 'other',
                $data['description'],
                $data['day_of_week'] ?? null,
                $data['time_slot'] ?? null,
                $data['schedule_id_1'] ?? null,
                $data['schedule_id_2'] ?? null
            ]);

            return successResponse(['id' => $this->db->lastInsertId(), 'message' => 'Conflict reported successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTimeSlots() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY period_number ASC");
            $stmt->execute();
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return successResponse($slots);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getExamSchedule($params = []) {
        try {
            $conditions = [];
            $bindings = [];

            // Filter by term (academic_year_terms.id)
            if (!empty($params['term_id'])) {
                $conditions[] = "term_id = ?";
                $bindings[] = $params['term_id'];
            }

            // Filter by academic year
            if (!empty($params['academic_year_id'])) {
                $conditions[] = "academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            // Filter by class
            if (!empty($params['class_id'])) {
                $conditions[] = "class_id = ?";
                $bindings[] = $params['class_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $conditions[] = "status = ?";
                $bindings[] = $params['status'];
            }

            // Filter by exam type
            if (!empty($params['exam_type'])) {
                $conditions[] = "exam_type = ?";
                $bindings[] = $params['exam_type'];
            }

            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // vw_upcoming_exam_schedules joins exam_schedules -> academic_year_class_streams
            // -> academic_year_classes -> classes, learning_areas, rooms and staff->persons,
            // exposing the legacy-friendly aliases term_id/academic_year_id/class_id/subject_id.
            $sql = "
                SELECT *
                FROM vw_upcoming_exam_schedules
                {$whereClause}
                ORDER BY exam_date ASC, start_time ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getExamScheduleById($id) {
        try {
            $sql = "
                SELECT *
                FROM vw_upcoming_exam_schedules
                WHERE id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse(['message' => 'Exam schedule not found'], 404);
            }

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createExamSchedule($data) {
        try {
            $required = ['class_id', 'subject_id', 'exam_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $academicYearId = (int) ($data['academic_year_id'] ?? $this->getCurrentAcademicYearId());
            $sql = "
                INSERT INTO exam_schedules (
                    academic_year_class_stream_id,
                    academic_year_term_id,
                    learning_area_id,
                    exam_name,
                    exam_type,
                    exam_date,
                    start_time,
                    end_time,
                    duration_minutes,
                    room_id,
                    venue,
                    invigilator_id,
                    supervisor_id,
                    notes,
                    created_by,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->resolveClassStreamId((int) ($data['class_id'] ?? 0), $academicYearId),
                $this->resolveAcademicYearTermId((int) ($data['term_id'] ?? 0), $academicYearId),
                $this->resolveLearningAreaId((int) ($data['subject_id'] ?? 0)),
                $data['exam_name'] ?? null,
                $data['exam_type'] ?? null,
                $data['exam_date'],
                $data['start_time'],
                $data['end_time'],
                $data['duration_minutes'] ?? null,
                $data['room_id'] ?? null,
                $data['venue'] ?? null,
                $data['invigilator_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? $this->user_id,
                $data['status'] ?? 'scheduled'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Exam schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Bulk-generate exam schedules for every active class stream x active learning
     * area in a term. Delegates to sp_create_exam_schedule.
     */
    public function bulkGenerateExamSchedule($data) {
        try {
            $required = ['term_id', 'exam_type', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $stmt = $this->db->prepare('CALL sp_create_exam_schedule(:term_id, :exam_type, :start_date, :end_date, :created_by)');
            $stmt->execute([
                'term_id' => $data['term_id'],
                'exam_type' => $data['exam_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'created_by' => $data['created_by'] ?? $this->user_id
            ]);

            return successResponse(['message' => 'Exam schedules generated successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateExamSchedule($id, $data) {
        try {
            // Build dynamic UPDATE mapped to live columns
            $fields = [];
            $values = [];

            $columnMap = [
                'term_id' => 'academic_year_term_id',
                'class_id' => 'academic_year_class_stream_id',
                'subject_id' => 'learning_area_id',
                'exam_name' => 'exam_name',
                'exam_type' => 'exam_type',
                'exam_date' => 'exam_date',
                'start_time' => 'start_time',
                'end_time' => 'end_time',
                'duration_minutes' => 'duration_minutes',
                'room_id' => 'room_id',
                'venue' => 'venue',
                'invigilator_id' => 'invigilator_id',
                'supervisor_id' => 'supervisor_id',
                'notes' => 'notes',
                'status' => 'status'
            ];

            $academicYearId = (int) ($data['academic_year_id'] ?? $this->getCurrentAcademicYearId());

            foreach ($columnMap as $inputField => $column) {
                if (!array_key_exists($inputField, $data) || $data[$inputField] === null) {
                    continue;
                }
                $value = $data[$inputField];
                if ($inputField === 'class_id') {
                    $value = $this->resolveClassStreamId((int) $value, $academicYearId);
                } elseif ($inputField === 'subject_id') {
                    $value = $this->resolveLearningAreaId((int) $value);
                } elseif ($inputField === 'term_id') {
                    $value = $this->resolveAcademicYearTermId((int) $value, $academicYearId);
                }
                $fields[] = "{$column} = ?";
                $values[] = $value;
            }

            if (empty($fields)) {
                return errorResponse(['message' => 'No valid fields to update'], 400);
            }

            $values[] = $id;
            $sql = "UPDATE exam_schedules SET " . implode(', ', $fields) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['message' => 'Exam schedule not found or no changes made'], 404);
            }

            return successResponse(['id' => $id, 'message' => 'Exam schedule updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteExamSchedule($id) {
        try {
            // Soft delete - set status to cancelled
            $stmt = $this->db->prepare("UPDATE exam_schedules SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['message' => 'Exam schedule not found'], 404);
            }

            return successResponse(['message' => 'Exam schedule cancelled successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getEvents($params = []) {
        try {
            $events = $this->calendarSync->getUnifiedEvents();

            if (!empty($params['type'])) {
                $type = strtolower($params['type']);
                $events = array_values(array_filter($events, function ($e) use ($type) {
                    return strtolower($e['type']) === $type;
                }));
            }

            return successResponse($events);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEvent($data) {
        try {
            $required = ['name', 'start_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $startAt = $data['start_date'] . ' ' . ($data['start_time'] ?? '00:00:00');
            $endAt = !empty($data['end_date'])
                ? $data['end_date'] . ' ' . ($data['end_time'] ?? '23:59:59')
                : $startAt;

            $nextIdStmt = $this->db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM school_events");
            $nextIdStmt->execute();
            $eventId = (int)$nextIdStmt->fetchColumn();

            $sql = "
                INSERT INTO school_events (
                    id,
                    title,
                    description,
                    start_at,
                    end_at,
                    type,
                    location,
                    status,
                    source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manual')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $eventId,
                $data['name'],
                $data['description'] ?? null,
                $startAt,
                $endAt,
                $data['type'] ?? 'general',
                $data['location'] ?? null,
                $data['status'] ?? 'upcoming'
            ]);

            $link = $this->calendarSync->applyEventToCalendar($eventId, $data);

            return successResponse([
                'id' => $eventId,
                'calendar_day_id' => $link['calendar_day'],
                'message' => 'Event created successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateEvent($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM school_events WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse(['message' => 'Event not found'], 404);
            }

            $fields = [];
            $values = [];

            $columnMap = [
                'name' => 'title',
                'title' => 'title',
                'description' => 'description',
                'type' => 'type',
                'location' => 'location',
                'status' => 'status'
            ];

            foreach ($columnMap as $inputField => $column) {
                if (array_key_exists($inputField, $data) && $data[$inputField] !== null) {
                    $fields[] = "{$column} = ?";
                    $values[] = $data[$inputField];
                }
            }

            if (array_key_exists('start_date', $data) && $data['start_date'] !== null) {
                $fields[] = "start_at = ?";
                $values[] = $data['start_date'] . ' ' . ($data['start_time'] ?? '00:00:00');
            } elseif (array_key_exists('start_time', $data) && $data['start_time'] !== null) {
                $fields[] = "start_at = CONCAT(DATE_FORMAT(start_at, '%Y-%m-%d '), ?)";
                $values[] = $data['start_time'];
            }

            if (array_key_exists('end_date', $data) && $data['end_date'] !== null) {
                $fields[] = "end_at = ?";
                $values[] = $data['end_date'] . ' ' . ($data['end_time'] ?? '23:59:59');
            } elseif (array_key_exists('end_time', $data) && $data['end_time'] !== null) {
                $fields[] = "end_at = CONCAT(DATE_FORMAT(end_at, '%Y-%m-%d '), ?)";
                $values[] = $data['end_time'];
            }

            if (empty($fields)) {
                return errorResponse(['message' => 'No valid fields to update'], 400);
            }

            $values[] = $id;
            $sql = "UPDATE school_events SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $link = $this->calendarSync->applyEventToCalendar((int) $id, $data);

            return successResponse(['id' => $id, 'calendar_day_id' => $link['calendar_day'], 'message' => 'Event updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteEvent($id) {
        try {
            $result = $this->calendarSync->handleEventDelete((int) $id);

            if ($result['calendar_day'] === null) {
                $stmt = $this->db->prepare("UPDATE school_events SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$id]);

                if ($stmt->rowCount() === 0) {
                    return errorResponse(['message' => 'Event not found'], 404);
                }
            }

            return successResponse(['message' => 'Event deleted successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Full calendar <-> events reconciliation (called on events page load).
     */
    public function syncEvents($params = []) {
        try {
            $synced = $this->calendarSync->syncAcademicYear(null);
            $normalized = $this->calendarSync->normalizeStatuses();

            return successResponse([
                'status' => 'success',
                'message' => 'Calendar and events are now in sync',
                'synced' => $synced,
                'normalized' => $normalized
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Mark the whole Mon-Fri week containing the given date as exam week.
     */
    public function markExamWeek($data) {
        try {
            $anchor = $data['date'] ?? $data['start_date'] ?? null;
            if (!$anchor) {
                return errorResponse(['message' => 'A date within the exam week is required'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
                return errorResponse(['message' => 'Invalid date format'], 400);
            }

            $result = $this->calendarSync->markExamWeek($anchor);

            return successResponse([
                'status' => 'success',
                'message' => $result['message'],
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Holiday Registry (UI-managed)
    // school_holidays is the single master list of ALL holidays (national,
    // religious - Idd moves with the moon, inter-term April/August/December,
    // school). sp_generate_year_calendar reads it and materializes holidays
    // onto academic_year_calendar_days, so editing a holiday here and clicking
    // "Apply to Calendar" updates the whole year calendar + events.
    // =============================

    public function getHolidays($params = []) {
        try {
            $where = '1=1';
            $bindings = [];
            if (!empty($params['year'])) {
                $year = (int) $params['year'];
                $where .= ' AND (YEAR(start_date) = ? OR YEAR(end_date) = ?)';
                $bindings = [$year, $year];
            }
            if (isset($params['active']) && $params['active'] !== '') {
                $where .= ' AND is_active = ?';
                $bindings[] = (int) $params['active'];
            }
            if (!empty($params['holiday_type'])) {
                $where .= ' AND holiday_type = ?';
                $bindings[] = $params['holiday_type'];
            }

            $stmt = $this->db->prepare(
                "SELECT id, name, holiday_type, start_date, end_date, description, is_active, created_at, updated_at
                 FROM school_holidays WHERE $where ORDER BY start_date, name"
            );
            $stmt->execute($bindings);

            return successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createHoliday($data) {
        try {
            $name = trim((string) ($data['name'] ?? ''));
            $start = $data['start_date'] ?? null;
            $end = $data['end_date'] ?? $start;
            if ($name === '' || empty($start)) {
                return errorResponse('Holiday name and start date are required', 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $end)) {
                return errorResponse('Invalid date format (expected YYYY-MM-DD)', 400);
            }
            if (strcmp((string) $end, (string) $start) < 0) {
                return errorResponse('End date cannot be before start date', 400);
            }
            $type = $data['holiday_type'] ?? 'school';
            if (!in_array($type, ['national', 'religious', 'inter_term', 'school'], true)) {
                return errorResponse('Invalid holiday type', 400);
            }

            $id = (int) $this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM school_holidays")->fetchColumn();
            $stmt = $this->db->prepare(
                "INSERT INTO school_holidays (id, name, holiday_type, start_date, end_date, description, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $id,
                $name,
                $type,
                $start,
                $end,
                $data['description'] ?? null,
                isset($data['is_active']) ? (int) $data['is_active'] : 1,
            ]);

            return successResponse(['id' => $id, 'message' => 'Holiday created'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateHoliday($id, $data) {
        try {
            $id = (int) $id;
            $fields = [
                'name' => 'name',
                'holiday_type' => 'holiday_type',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'description' => 'description',
                'is_active' => 'is_active',
            ];
            $sets = [];
            $values = [];
            foreach ($fields as $from => $col) {
                if (array_key_exists($from, $data) && $data[$from] !== null) {
                    $sets[] = "$col = ?";
                    $values[] = $data[$from];
                }
            }
            if (empty($sets)) {
                return errorResponse('No fields to update', 400);
            }
            if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data)) {
                $row = $this->db->prepare("SELECT start_date, end_date FROM school_holidays WHERE id = ?");
                $row->execute([$id]);
                $cur = $row->fetch(PDO::FETCH_ASSOC);
                if (!$cur) {
                    return errorResponse('Holiday not found', 404);
                }
                $start = $data['start_date'] ?? $cur['start_date'];
                $end = $data['end_date'] ?? $cur['end_date'];
                if (strcmp((string) $end, (string) $start) < 0) {
                    return errorResponse('End date cannot be before start date', 400);
                }
            }

            $values[] = $id;
            $stmt = $this->db->prepare("UPDATE school_holidays SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($values);
            if ($stmt->rowCount() === 0) {
                // rowCount is 0 both when the row is missing and when no column
                // changed; treat a missing row as an error.
                $check = $this->db->prepare("SELECT id FROM school_holidays WHERE id = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    return errorResponse('Holiday not found', 404);
                }
            }

            return successResponse(['id' => $id, 'message' => 'Holiday updated']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteHoliday($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM school_holidays WHERE id = ?");
            $stmt->execute([(int) $id]);
            if ($stmt->rowCount() === 0) {
                return errorResponse('Holiday not found', 404);
            }
            return successResponse(['message' => 'Holiday deleted']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Re-apply the holiday registry to the year calendar (regenerates the
     * calendar and re-syncs events). Called after holiday edits.
     */
    public function applyHolidays($params = []) {
        try {
            $yearId = (int) ($params['academic_year_id'] ?? 0);
            if (!$yearId) {
                $yearId = (int) $this->db->query(
                    "SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
                )->fetchColumn();
            }
            if (!$yearId) {
                return errorResponse('No academic year found', 404);
            }

            require_once __DIR__ . '/../academic/AcademicCalendarService.php';
            $calendarService = new \App\API\Modules\academic\AcademicCalendarService($this->db);
            $result = $calendarService->generateYearCalendar($yearId);
            $this->calendarSync->syncAcademicYear($yearId);

            return successResponse([
                'message' => 'Holidays applied to the calendar',
                'calendar' => $result['data'] ?? null,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getActivitySchedule($params = []) {
        try {
            $sql = "
                SELECT
                    a.id,
                    a.activity_id,
                    ac.title AS activity_name,
                    a.day_of_week,
                    a.schedule_date,
                    a.start_time,
                    a.end_time,
                    a.venue
                FROM activity_schedule a
                JOIN activities ac ON a.activity_id = ac.id
                ORDER BY a.schedule_date, a.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createActivitySchedule($data) {
        try {
            $required = ['activity_id', 'schedule_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO activity_schedule (
                    activity_id,
                    day_of_week,
                    schedule_date,
                    start_time,
                    end_time,
                    venue
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['day_of_week'] ?? date('l', strtotime($data['schedule_date'])),
                $data['schedule_date'],
                $data['start_time'],
                $data['end_time'],
                $data['venue'] ?? null
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Activity schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getRooms($params = []) {
        try {
            $sql = "
                SELECT 
                    r.*,
                    COUNT(DISTINCT vte.id) as timetable_count,
                    COUNT(DISTINCT es.id) as exam_count
                FROM rooms r
                LEFT JOIN vw_timetable_entries vte ON r.id = vte.room_id AND vte.status = 'scheduled'
                LEFT JOIN exam_schedules es ON r.id = es.room_id
                    AND es.status IN ('scheduled', 'upcoming', 'in_progress')
                WHERE r.status IN ('active', 'maintenance')
                GROUP BY r.id
                ORDER BY r.building, r.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rooms as &$room) {
                $room['active_bookings'] = (int) ($room['timetable_count'] ?? 0) + (int) ($room['exam_count'] ?? 0);
            }
            unset($room);

            return successResponse($rooms);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRoom($data) {
        try {
            $required = ['name', 'capacity'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO rooms (
                    name,
                    code,
                    building,
                    floor,
                    capacity,
                    type,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['code'] ?? null,
                $data['building'] ?? null,
                $data['floor'] ?? null,
                $data['capacity'],
                $data['type'] ?? 'classroom',
                'active'
            ]);

            $roomId = $this->db->lastInsertId();

            return successResponse(['id' => $roomId, 'message' => 'Room created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getRouteSchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    rs.*,
                    r.name as route_name,
                    v.registration_number,
                    CONCAT(dp.first_name, ' ', dp.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM route_schedules rs
                JOIN transport_routes r ON rs.route_id = r.id
                LEFT JOIN transport_vehicles v ON rs.vehicle_id = v.id
                LEFT JOIN staff d ON rs.driver_id = d.id
                LEFT JOIN persons dp ON dp.id = d.person_id
                LEFT JOIN student_transport_assignments ta ON r.id = ta.route_id AND ta.status = 'active'
                WHERE rs.status = 'active'
                GROUP BY rs.id
                ORDER BY rs.day_of_week, rs.pickup_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRouteSchedule($data) {
        try {
            $required = ['route_id', 'day_of_week', 'pickup_time', 'dropoff_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO route_schedules (
                    route_id,
                    vehicle_id,
                    driver_id,
                    day_of_week,
                    direction,
                    departure_time,
                    pickup_time,
                    dropoff_time,
                    notes,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['route_id'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null,
                $data['day_of_week'],
                $data['direction'] ?? 'pickup',
                $data['departure_time'] ?? $data['pickup_time'],
                $data['pickup_time'],
                $data['dropoff_time'],
                $data['notes'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Route schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Resolve the current academic year id (fallback when one is not provided).
     */
    private function getCurrentAcademicYearId(): int
    {
        $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn();
        if ($yearId <= 0) {
            $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
        return $yearId;
    }

    /**
     * Resolve a legacy `class_id` to the live `academic_year_class_streams.id`.
     */
    private function resolveClassStreamId(int $classId, int $academicYearId = 0): int
    {
        if ($classId <= 0) {
            return 0;
        }
        if ($academicYearId <= 0) {
            $academicYearId = $this->getCurrentAcademicYearId();
        }
        if ($academicYearId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT aycs.id
             FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
             ORDER BY aycs.id LIMIT 1"
        );
        $stmt->execute([$academicYearId, $classId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `term_id` (academic_year_terms.id or terms.id) to
     * academic_year_terms.id, defaulting to the current term of the year.
     */
    private function resolveAcademicYearTermId(int $termId, int $academicYearId = 0): int
    {
        if ($academicYearId <= 0) {
            $academicYearId = $this->getCurrentAcademicYearId();
        }
        if ($academicYearId <= 0) {
            return 0;
        }
        if ($termId > 0) {
            $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE id = ? AND academic_year_id = ? LIMIT 1");
            $stmt->execute([$termId, $academicYearId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
            $stmt = $this->db->prepare("SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.academic_year_id = ? AND ayt.term_id = ? LIMIT 1");
            $stmt->execute([$academicYearId, $termId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'current' LIMIT 1");
        $stmt->execute([$academicYearId]);
        $found = (int) $stmt->fetchColumn();
        if ($found > 0) {
            return $found;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'upcoming' ORDER BY opening_date LIMIT 1");
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `subject_id` (strand.id or learning_areas.id) to learning_areas.id.
     */
    private function resolveLearningAreaId(int $subjectId): int
    {
        if ($subjectId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT learning_area_id FROM strands WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        $learningAreaId = (int) $stmt->fetchColumn();
        if ($learningAreaId > 0) {
            return $learningAreaId;
        }
        $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a time_slot by explicit id, or by matching start/end time strings.
     */
    private function resolveTimeSlotId($timeSlotId, $startTime = null, $endTime = null): ?int
    {
        if (!empty($timeSlotId) && is_numeric($timeSlotId)) {
            $stmt = $this->db->prepare("SELECT id FROM time_slots WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([(int) $timeSlotId]);
            if ($stmt->fetchColumn()) {
                return (int) $timeSlotId;
            }
        }
        if ($startTime !== null && $startTime !== '') {
            $sql = "SELECT id FROM time_slots WHERE is_active = 1 AND start_time = ?";
            $params = [$startTime];
            if ($endTime !== null && $endTime !== '') {
                $sql .= " AND end_time = ?";
                $params[] = $endTime;
            }
            $sql .= " ORDER BY period_number LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        return null;
    }
}
