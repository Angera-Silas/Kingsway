<?php
namespace App\API\Modules\communications;

use PDO;

class ContactDirectoryManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for contact_directory
    public function createContact($data)
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('name is required');
        }
        $sql = "INSERT INTO contact_directory (name, phone, email, contact_type, department_id, role, notes, created_at) VALUES (:name, :phone, :email, :contact_type, :department_id, :role, :notes, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':contact_type' => $data['type'] ?? $data['contact_type'] ?? 'external',
            ':department_id' => $this->resolveDepartmentId($data['department'] ?? null),
            ':role' => $data['role'] ?? null,
            ':notes' => $data['notes'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function getContact($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM contact_directory WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateContact($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'phone', 'email', 'contact_type', 'role', 'notes'] as $col) {
            if (isset($data[$col]) || (isset($data['type']) && $col === 'contact_type')) {
                $fields[] = "$col = :$col";
                if ($col === 'contact_type') {
                    $params[":$col"] = $data['type'] ?? $data['contact_type'] ?? null;
                } else {
                    $params[":$col"] = $data[$col] ?? null;
                }
            }
        }
        if (isset($data['department'])) {
            $departmentId = $this->resolveDepartmentId($data['department']);
            $fields[] = "department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }
        if (!$fields) {
            return false;
        }
        $sql = "UPDATE contact_directory SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteContact($id)
    {
        $stmt = $this->db->prepare("DELETE FROM contact_directory WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listContacts($filters = [])
    {
        $sql = "SELECT * FROM contact_directory WHERE 1=1";
        $params = [];
        if (isset($filters['type']) || isset($filters['contact_type'])) {
            $sql .= " AND contact_type = :contact_type";
            $params[':contact_type'] = $filters['type'] ?? $filters['contact_type'];
        }
        if (isset($filters['department']) && $filters['department'] !== '') {
            $departmentId = $this->resolveDepartmentId($filters['department']);
            $sql .= " AND department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }
        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approval workflow - contact_directory has no status/approval columns;
    // approval actions are recorded in audit_logs.
    public function submitForReview($id)
    {
        $contact = $this->getContact($id);
        if (!$contact) {
            return false;
        }
        $this->audit('contact_submitted', $id, null);
        return true;
    }

    public function approveContact($id, $adminId)
    {
        $contact = $this->getContact($id);
        if (!$contact) {
            return false;
        }
        $this->audit('contact_approved', $id, $adminId);
        return true;
    }

    public function rejectContact($id, $adminId)
    {
        $contact = $this->getContact($id);
        if (!$contact) {
            return false;
        }
        $this->audit('contact_rejected', $id, $adminId);
        return true;
    }

    private function audit(string $action, int $id, $adminId): void
    {
        \App\API\Includes\FileLogger::write('audit', [
            'type' => 'audit',
            'action' => $action,
            'entity' => 'contact_directory',
            'entity_id' => $id,
            'user_id' => $adminId,
            'details' => null,
            'status' => 'success',
        ]);
    }

    private function resolveDepartmentId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $stmt = $this->db->prepare("SELECT id FROM departments WHERE name = ? OR code = ? LIMIT 1");
        $stmt->execute([$value, $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }
}
