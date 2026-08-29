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

    public function syncRouteStops(int $routeId, array $stops): array
    {
        if ($routeId <= 0) throw new \InvalidArgumentException('route_id is required');
        $this->db->beginTransaction();
        try {
            $kept = [];
            foreach (array_values($stops) as $index => $stop) {
                $name = trim((string)($stop['name'] ?? ''));
                if ($name === '') throw new \InvalidArgumentException('Every pickup/drop-off point requires a name');
                $values = [$name, $index + 1, $stop['location'] ?? null, $stop['arrival_time'] ?: null, $stop['departure_time'] ?: null];
                $id = (int)($stop['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $this->db->prepare("UPDATE transport_stops SET name=?,sequence=?,location=?,arrival_time=?,departure_time=?,status='active' WHERE id=? AND route_id=?");
                    $stmt->execute([...$values, $id, $routeId]);
                    $check = $this->db->prepare('SELECT 1 FROM transport_stops WHERE id=? AND route_id=?');
                    $check->execute([$id, $routeId]);
                    if (!$check->fetchColumn()) throw new \InvalidArgumentException('A pickup/drop-off point does not belong to this route');
                    $kept[] = $id;
                } else {
                    $stmt = $this->db->prepare("INSERT INTO transport_stops (route_id,name,sequence,location,arrival_time,departure_time,status) VALUES (?,?,?,?,?,?,'active')");
                    $stmt->execute([$routeId, ...$values]);
                    $kept[] = (int)$this->db->lastInsertId();
                }
            }
            if ($kept) {
                $marks = implode(',', array_fill(0, count($kept), '?'));
                $this->db->prepare("UPDATE transport_stops SET status='inactive' WHERE route_id=? AND id NOT IN ($marks)")->execute([$routeId, ...$kept]);
            } else {
                $this->db->prepare("UPDATE transport_stops SET status='inactive' WHERE route_id=?")->execute([$routeId]);
            }
            $this->db->commit();
            return $this->getStopsForRoute($routeId);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
