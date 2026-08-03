<?php
namespace App\API\Modules\schedules;

use App\API\Includes\BaseAPI;
use App\API\Modules\schedules\SchedulesManager;
use App\API\Modules\schedules\SchedulesWorkflow;
use App\API\Modules\schedules\TermHolidayManager;
use App\API\Modules\schedules\TermHolidayWorkflow;
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

    public function __construct() {
        parent::__construct('schedules');
        $this->manager = new SchedulesManager($this->db);
        $this->workflow = new SchedulesWorkflow();
        $this->termHolidayManager = new TermHolidayManager($this->db);
        $this->termHolidayWorkflow = new TermHolidayWorkflow();
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

    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE title LIKE ? OR description LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "SELECT COUNT(*) FROM schedules $where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "SELECT * FROM schedules $where ORDER BY $sort $order LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'schedules' => $schedules,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function get($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse('Schedule not found', 404);
            }

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function create($data) {
        try {
            $required = ['title', 'start_date', 'end_date', 'type'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO schedules (
                    title,
                    description,
                    start_date,
                    end_date,
                    type,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['type'],
                $data['status'] ?? 'active'
            ]);

            $id = $this->db->lastInsertId();

            return successResponse(['id' => $id, 'message' => 'Schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Schedule not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = ['title', 'description', 'start_date', 'end_date', 'type', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return successResponse(['message' => 'Schedule updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['status' => 'error', 'message' => 'Schedule not found'], 404);
            }

            return successResponse(['message' => 'Schedule deleted successfully']);
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

            // Filter by term
            if (!empty($params['term_id'])) {
                $conditions[] = "es.term_id = ?";
                $bindings[] = $params['term_id'];
            }

            // Filter by academic year
            if (!empty($params['academic_year_id'])) {
                $conditions[] = "es.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            // Filter by class
            if (!empty($params['class_id'])) {
                $conditions[] = "es.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $conditions[] = "es.status = ?";
                $bindings[] = $params['status'];
            } else {
                $conditions[] = "es.status NOT IN ('cancelled')";
            }

            // Filter by exam type
            if (!empty($params['exam_type'])) {
                $conditions[] = "es.exam_type = ?";
                $bindings[] = $params['exam_type'];
            }

            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $sql = "
                SELECT 
                    es.id,
                    es.term_id,
                    es.academic_year_id,
                    es.class_id,
                    c.name AS class_name,
                    es.subject_id,
                    COALESCE(cu.name, '') AS subject_name,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.duration_minutes,
                    es.room_id,
                    r.name AS room_name,
                    es.venue,
                    es.invigilator_id,
                    CONCAT(inv.first_name, ' ', inv.last_name) AS invigilator_name,
                    es.supervisor_id,
                    CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                    es.notes,
                    es.status,
                    es.created_at,
                    es.updated_at,
                    at2.term_number AS term_number,
                    ay.year_code AS academic_year
                FROM exam_schedules es
                JOIN classes c ON es.class_id = c.id
                LEFT JOIN curriculum_units cu ON es.subject_id = cu.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                LEFT JOIN academic_terms at2 ON es.term_id = at2.id
                LEFT JOIN academic_years ay ON es.academic_year_id = ay.id
                {$whereClause}
                ORDER BY es.exam_date ASC, es.start_time ASC
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
                SELECT 
                    es.id,
                    es.term_id,
                    es.academic_year_id,
                    es.class_id,
                    c.name AS class_name,
                    es.subject_id,
                    COALESCE(cu.name, '') AS subject_name,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.duration_minutes,
                    es.room_id,
                    r.name AS room_name,
                    es.venue,
                    es.invigilator_id,
                    CONCAT(inv.first_name, ' ', inv.last_name) AS invigilator_name,
                    es.supervisor_id,
                    CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                    es.notes,
                    es.status,
                    es.created_at,
                    es.updated_at
                FROM exam_schedules es
                JOIN classes c ON es.class_id = c.id
                LEFT JOIN curriculum_units cu ON es.subject_id = cu.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                WHERE es.id = ?
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

            $sql = "
                INSERT INTO exam_schedules (
                    term_id,
                    academic_year_id,
                    class_id,
                    subject_id,
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['term_id'] ?? null,
                $data['academic_year_id'] ?? null,
                $data['class_id'],
                $data['subject_id'],
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
                $data['created_by'] ?? null,
                $data['status'] ?? 'scheduled'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Exam schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateExamSchedule($id, $data) {
        try {
            // Build dynamic UPDATE
            $fields = [];
            $values = [];

            $allowedFields = [
                'term_id', 'academic_year_id', 'class_id', 'subject_id',
                'exam_name', 'exam_type', 'exam_date', 'start_time', 'end_time',
                'duration_minutes', 'room_id', 'venue', 'invigilator_id',
                'supervisor_id', 'notes', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
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
            $sql = "
                SELECT 
                    e.*,
                    r.name as room_name,
                    CONCAT(o.first_name, ' ', o.last_name) as organizer_name
                FROM events e
                LEFT JOIN rooms r ON e.room_id = r.id
                LEFT JOIN staff o ON e.organizer_id = o.id
                WHERE e.status = 'active'
                ORDER BY e.start_date, e.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($events);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEvent($data) {
        try {
            $required = ['name', 'start_date', 'end_date', 'organizer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO events (
                    name,
                    description,
                    start_date,
                    end_date,
                    start_time,
                    end_time,
                    room_id,
                    organizer_id,
                    type,
                    participants,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['room_id'] ?? null,
                $data['organizer_id'],
                $data['type'] ?? 'general',
                json_encode($data['participants'] ?? []),
                'active'
            ]);

            $eventId = $this->db->lastInsertId();

            return successResponse(['id' => $eventId, 'message' => 'Event created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getActivitySchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    ac.name as activity_name,
                    r.name as room_name,
                    CONCAT(s.first_name, ' ', s.last_name) as supervisor_name
                FROM activity_schedules a
                JOIN activities ac ON a.activity_id = ac.id
                LEFT JOIN rooms r ON a.room_id = r.id
                LEFT JOIN staff s ON a.supervisor_id = s.id
                WHERE a.status = 'active'
                ORDER BY a.day_of_week, a.start_time
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
            $required = ['activity_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO activity_schedules (
                    activity_id,
                    room_id,
                    supervisor_id,
                    day_of_week,
                    start_time,
                    end_time,
                    max_participants,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['room_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['max_participants'] ?? null,
                'active'
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

    public function getScheduledReports($params = []) {
        try {
            $sql = "
                SELECT 
                    sr.*,
                    CONCAT(s.first_name, ' ', s.last_name) as recipient_name
                FROM scheduled_reports sr
                LEFT JOIN staff s ON sr.recipient_id = s.id
                WHERE sr.status = 'active'
                ORDER BY sr.frequency, sr.next_run
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($reports);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createScheduledReport($data) {
        try {
            $required = ['name', 'report_type', 'frequency', 'recipient_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO scheduled_reports (
                    name,
                    description,
                    report_type,
                    parameters,
                    frequency,
                    next_run,
                    recipient_id,
                    format,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            // Calculate next run based on frequency
            $nextRun = new DateTime();
            switch ($data['frequency']) {
                case 'daily':
                    $nextRun->modify('+1 day');
                    break;
                case 'weekly':
                    $nextRun->modify('next monday');
                    break;
                case 'monthly':
                    $nextRun->modify('first day of next month');
                    break;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['report_type'],
                json_encode($data['parameters'] ?? []),
                $data['frequency'],
                $nextRun->format('Y-m-d H:i:s'),
                $data['recipient_id'],
                $data['format'] ?? 'pdf',
                'active'
            ]);

            $reportId = $this->db->lastInsertId();

            return successResponse(['id' => $reportId, 'message' => 'Scheduled report created successfully'], 201);
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
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM route_schedules rs
                JOIN transport_routes r ON rs.route_id = r.id
                LEFT JOIN transport_vehicles v ON rs.vehicle_id = v.id
                LEFT JOIN staff d ON rs.driver_id = d.id
                LEFT JOIN transport_assignments ta ON r.id = ta.route_id
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
                    pickup_time,
                    dropoff_time,
                    notes,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['route_id'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null,
                $data['day_of_week'],
                $data['pickup_time'],
                $data['dropoff_time'],
                $data['notes'] ?? null,
                'active'
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
