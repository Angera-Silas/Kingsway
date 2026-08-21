<?php
namespace App\API\Modules\communications;

use PDO;

class ParentPortalMessageManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Parent-portal messages - mapped to live communications (type='internal') +
    // communication_recipients (legacy parent_portal_messages/replies retired)
    private function mapStatus($status): string
    {
        // communications.status enum: draft, sent, scheduled, failed
        switch ($status) {
            case 'sent':
                return 'sent';
            case 'scheduled':
            case 'approved':
                return 'scheduled';
            case 'failed':
                return 'failed';
            case 'archived':
            case 'pending_review':
            case 'draft':
            default:
                return 'draft';
        }
    }

    public function createMessage($data)
    {
        $parentId = $data['parent_id'] ?? 1;
        $recipientId = $data['recipient_id'] ?? $data['parent_id'] ?? $parentId;
        $senderId = $data['sender_id'] ?? $data['created_by'] ?? 1;
        $status = $this->mapStatus($data['status'] ?? 'sent');

        $signature = json_encode([
            'recipient_id' => (int) $recipientId,
            'recipient_type' => $data['recipient_type'] ?? 'parent',
            'sender_type' => $data['sender_type'] ?? 'school',
        ]);

        $sql = "INSERT INTO communications (type, subject, body, sender_id, status, priority, sender_signature, created_at)
                VALUES ('internal', :subject, :body, :sender_id, :status, 'medium', :sender_signature, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':subject' => $data['subject'] ?? 'No subject',
            ':body' => $data['body'] ?? $data['message'] ?? '',
            ':sender_id' => $senderId,
            ':status' => $status,
            ':sender_signature' => $signature
        ]);
        $communicationId = (int) $this->db->lastInsertId();

        $recipientStatus = $status === 'sent' ? 'delivered' : 'pending';
        $stmt2 = $this->db->prepare(
            "INSERT INTO communication_recipients (communication_id, recipient_id, status, delivered_at) VALUES (:communication_id, :recipient_id, :status, :delivered_at)"
        );
        $stmt2->execute([
            ':communication_id' => $communicationId,
            ':recipient_id' => (int) $recipientId,
            ':status' => $recipientStatus,
            ':delivered_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null
        ]);

        return $communicationId;
    }

    public function getMessage($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM communications WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row = $this->decorateMessage($row);
        }
        return $row;
    }

    public function updateMessage($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['subject', 'body'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (isset($data['message']) && !isset($data['body'])) {
            $fields[] = "body = :body";
            $params[':body'] = $data['message'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $this->mapStatus($data['status']);
        }
        if (!$fields) {
            return false;
        }
        $sql = "UPDATE communications SET " . implode(", ", $fields) . " WHERE id = :id AND type = 'internal'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteMessage($id)
    {
        $this->db->prepare("DELETE FROM communication_recipients WHERE communication_id = ?")->execute([$id]);
        $stmt = $this->db->prepare("DELETE FROM communications WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listMessages($filters = [])
    {
        $sql = "SELECT * FROM communications WHERE type = 'internal'";
        $params = [];
        if (isset($filters['parent_id'])) {
            // recipient linkage lives in sender_signature JSON (legacy parent_id)
            $sql .= " AND sender_signature LIKE :parent_ref";
            $params[':parent_ref'] = '%"recipient_id":' . (int) $filters['parent_id'] . '%';
        }
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $this->mapStatus($filters['status']);
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row = $this->decorateMessage($row);
        }
        return $rows;
    }

    // Workflow methods - communications.status enum: draft/sent/scheduled/failed
    public function submitForReview($id)
    {
        $stmt = $this->db->prepare("UPDATE communications SET status = 'draft' WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        $this->audit('parent_message_submitted', $id, null);
        return $stmt->rowCount() > 0;
    }

    public function approveMessage($id, $reviewerId)
    {
        $stmt = $this->db->prepare("UPDATE communications SET status = 'scheduled' WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        $this->audit('parent_message_approved', $id, $reviewerId);
        return $stmt->rowCount() > 0;
    }

    public function sendMessage($id)
    {
        $stmt = $this->db->prepare("UPDATE communications SET status = 'sent' WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function archiveMessage($id)
    {
        $stmt = $this->db->prepare("UPDATE communications SET status = 'draft' WHERE id = ? AND type = 'internal'");
        $stmt->execute([$id]);
        $this->audit('parent_message_archived', $id, null);
        return $stmt->rowCount() > 0;
    }

    public function replyToMessage($id, $replyData)
    {
        $original = $this->getMessage($id);
        $subject = $original ? ('Re: ' . ($original['subject'] ?? 'No subject')) : 'Re: message';

        $sql = "INSERT INTO communications (type, subject, body, sender_id, status, priority, sender_signature, created_at)
                VALUES ('internal', :subject, :body, :sender_id, 'sent', 'normal', :sender_signature, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':subject' => $subject,
            ':body' => $replyData['reply'] ?? '',
            ':sender_id' => $replyData['replied_by'] ?? 1,
            ':sender_signature' => json_encode(['reply_to' => (int) $id])
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getThread($id)
    {
        $message = $this->getMessage($id);
        if (!$message) {
            return null;
        }
        $stmt2 = $this->db->prepare("SELECT * FROM communications WHERE type = 'internal' AND sender_signature LIKE :ref ORDER BY created_at ASC");
        $stmt2->execute([':ref' => '%"reply_to":' . (int) $id . '%']);
        $replies = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($replies as &$reply) {
            $reply = $this->decorateMessage($reply);
        }
        $message['replies'] = $replies;
        return $message;
    }

    private function decorateMessage(array $row): array
    {
        $meta = json_decode($row['sender_signature'] ?? '', true);
        $row['parent_id'] = $meta['recipient_id'] ?? null;
        $row['recipient_id'] = $meta['recipient_id'] ?? null;
        $row['recipient_type'] = $meta['recipient_type'] ?? 'parent';
        $row['sender_type'] = $meta['sender_type'] ?? 'school';
        $row['reply_to'] = $meta['reply_to'] ?? null;
        return $row;
    }

    private function audit(string $action, int $id, $userId): void
    {
        \App\API\Includes\FileLogger::write('audit', [
            'type' => 'audit',
            'action' => $action,
            'entity' => 'communication',
            'entity_id' => $id,
            'user_id' => $userId,
            'details' => null,
            'status' => 'success',
        ]);
    }
}
