<?php
namespace App\API\Modules\communications;

use PDO;

/**
 * Internal staff communication manager: handles requests, announcements, and forum discussions for staff.
 */
class InternalCommManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Staff requests (leave, IT, maintenance, etc.)
    public function createRequest($data)
    {
        $sql = "INSERT INTO internal_conversations (title, conversation_type, created_by, is_locked) VALUES (:title, :conversation_type, :created_by, 0)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['subject'] ?? $data['title'] ?? 'Request',
            ':conversation_type' => $data['type'] ?? $data['conversation_type'] ?? 'one_on_one',
            ':created_by' => $data['requested_by'] ?? $data['created_by'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }

    public function getRequest($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_conversations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRequest($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        if (isset($data['subject']) || isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['subject'] ?? $data['title'];
        }
        if (isset($data['conversation_type']) || isset($data['type'])) {
            $fields[] = "conversation_type = :conversation_type";
            $params[':conversation_type'] = $data['conversation_type'] ?? $data['type'];
        }
        if (!$fields) {
            return false;
        }
        $sql = "UPDATE internal_conversations SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteRequest($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_conversations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listRequests($filters = [])
    {
        $sql = "SELECT * FROM internal_conversations WHERE 1=1";
        $params = [];
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Internal announcements
    public function createAnnouncement($data)
    {
        $sql = "INSERT INTO internal_messages (sender_id, subject, message_body, message_type, status, created_at) VALUES (:sender_id, :subject, :message_body, 'announcement', 'sent', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $data['created_by'] ?? 1,
            ':subject' => $data['title'] ?? '',
            ':message_body' => $data['message'] ?? ''
        ]);
        return $this->db->lastInsertId();
    }

    public function getAnnouncement($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_messages WHERE id = ? AND message_type = 'announcement'");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAnnouncement($id, $data)
    {
        $sql = "UPDATE internal_messages SET subject = :subject, message_body = :message_body, updated_at = NOW() WHERE id = :id AND message_type = 'announcement'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':subject' => $data['title'] ?? null,
            ':message_body' => $data['message'] ?? null,
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteAnnouncement($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_messages WHERE id = ? AND message_type = 'announcement'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listAnnouncements($filters = [])
    {
        $sql = "SELECT * FROM internal_messages WHERE message_type = 'announcement'";
        $params = [];
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Forum topics/discussions - mapped to live forum_threads (forum_type='internal')
    public function createForumTopic($data)
    {
        $sql = "INSERT INTO forum_threads (title, created_by, forum_type, status, created_at, updated_at) VALUES (:title, :created_by, 'internal', 'open', NOW(), NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumTopic($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_threads WHERE id = ? AND forum_type = 'internal'");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumTopic($id, $data)
    {
        $sql = "UPDATE forum_threads SET title = :title, updated_at = NOW() WHERE id = :id AND forum_type = 'internal'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumTopic($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_threads WHERE id = ? AND forum_type = 'internal'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumTopics($filters = [])
    {
        $sql = "SELECT * FROM forum_threads WHERE forum_type = 'internal'";
        $params = [];
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createForumPost($topicId, $data)
    {
        $sql = "INSERT INTO forum_posts (thread_id, author_id, author_type, body, reply_to_id, created_at) VALUES (:thread_id, :author_id, 'user', :body, NULL, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':thread_id' => $topicId,
            ':author_id' => $data['created_by'],
            ':body' => $data['content']
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumPost($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumPost($id, $data)
    {
        $sql = "UPDATE forum_posts SET body = :body WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':body' => $data['content'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumPost($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumPosts($topicId)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC");
        $stmt->execute([$topicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
