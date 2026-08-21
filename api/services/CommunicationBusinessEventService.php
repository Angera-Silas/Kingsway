<?php

namespace App\API\Services;

use PDO;

/** Creates idempotent business-event records used by communication jobs. */
class CommunicationBusinessEventService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getOrCreate(string $eventCode, string $eventKey, ?string $occurredAt = null, ?int $createdBy = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO communication_business_events (event_code, event_key, occurred_at, created_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([$eventCode, $eventKey, $occurredAt ?: date('Y-m-d H:i:s'), $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    public function linkExamWorkflow(int $eventId, int $workflowInstanceId): void
    {
        $this->db->prepare("INSERT IGNORE INTO communication_event_exam_workflows (event_id, workflow_instance_id) VALUES (?, ?)")
            ->execute([$eventId, $workflowInstanceId]);
    }

    public function linkSchoolEvent(int $eventId, int $schoolEventId): void
    {
        $this->db->prepare("INSERT IGNORE INTO communication_event_school_events (event_id, school_event_id) VALUES (?, ?)")
            ->execute([$eventId, $schoolEventId]);
    }

    public function linkFeeStudent(int $eventId, int $studentId, string $window): void
    {
        $this->db->prepare("INSERT IGNORE INTO communication_event_fee_students (event_id, student_id, reminder_window) VALUES (?, ?, ?)")
            ->execute([$eventId, $studentId, $window]);
    }

    public function linkInquiry(int $eventId, int $inquiryId): void
    {
        $this->db->prepare("INSERT IGNORE INTO communication_event_inquiries (event_id, inquiry_id) VALUES (?, ?)")
            ->execute([$eventId, $inquiryId]);
    }

    public function markProcessed(int $eventId): void
    {
        $this->db->prepare("UPDATE communication_business_events SET status = 'processed' WHERE id = ?")->execute([$eventId]);
    }
}
