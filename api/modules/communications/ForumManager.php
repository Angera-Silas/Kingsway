<?php
namespace App\API\Modules\communications;

use PDO;

class ForumManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for forum_threads
    public function createThread($data)
    {
        // Map 'subject' to 'title' if provided
        $title = $data['title'] ?? $data['subject'] ?? null;
        if (!$title) {
            throw new \InvalidArgumentException("Thread title is required");
        }
        $sql = "INSERT INTO forum_threads (title, created_by, status, created_at) VALUES (:title, :created_by, 'open', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getThread($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_threads WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateThread($id, $data)
    {
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('title is required');
        }
        $sql = "UPDATE forum_threads SET title = :title, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteThread($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_threads WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listThreads($filters = [])
    {
        $sql = "SELECT * FROM forum_threads WHERE 1=1";
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

    // CRUD for forum_posts
    public function createPost($data)
    {
        $sql = "INSERT INTO forum_posts (thread_id, author_id, author_type, body, reply_to_id, created_at) VALUES (:thread_id, :author_id, 'user', :body, NULL, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':thread_id' => $data['thread_id'],
            ':author_id' => $data['created_by'],
            ':body' => $data['content']
        ]);
        return $this->db->lastInsertId();
    }

    public function getPost($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePost($id, $data)
    {
        $sql = "UPDATE forum_posts SET body = :body WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':body' => $data['content'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deletePost($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listPosts($threadId)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Moderation workflow
    public function moderateThread($id, $action, $moderatorId)
    {
        $validStatuses = ['open', 'closed', 'archived'];
        if (!in_array($action, $validStatuses, true)) {
            return false;
        }
        $sql = "UPDATE forum_threads SET status = :action WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':action' => $action,
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function moderatePost($id, $action, $moderatorId)
    {
        // forum_posts has no moderation status column; hide/delete actions remove the post
        if (in_array($action, ['hidden', 'deleted', 'delete', 'hide'], true)) {
            $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        }
        return false;
    }
}
