<?php
namespace App\API\Modules\transport;

use PDO;

class StopManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for stops — transport_stops uses `sequence` + `location` (no lat/lng columns)
    public function createStop($data)
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('name is required');
        }
        $sql = "INSERT INTO transport_stops (name, route_id, sequence, location, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['route_id'] ?? null,
            $data['stop_order'] ?? $data['sequence'] ?? 0,
            $this->serializeLocation($data),
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateStop($id, $data)
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('name is required');
        }
        $sql = "UPDATE transport_stops SET name=?, route_id=?, sequence=?, location=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['route_id'] ?? null,
            $data['stop_order'] ?? $data['sequence'] ?? 0,
            $this->serializeLocation($data),
            $data['status'] ?? 'active',
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    private function serializeLocation($data)
    {
        if (!empty($data['location'])) {
            return $data['location'];
        }
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            return $data['latitude'] . ',' . $data['longitude'];
        }
        return null;
    }
    public function deactivateStop($id)
    {
        $stmt = $this->db->prepare("UPDATE transport_stops SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteStop($id)
    {
        $stmt = $this->db->prepare("DELETE FROM transport_stops WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getStop($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllStops()
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
