<?php
namespace App\API\Modules\transport;

use PDO;

class RouteManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for routes
    public function createRoute($data)
    {
        $name = trim((string)($data['name'] ?? $data['route_name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? $data['route_code'] ?? '')));
        $start = trim((string)($data['start_point'] ?? $data['start'] ?? ''));
        $end = trim((string)($data['end_point'] ?? $data['end'] ?? ''));
        if ($name === '' || $code === '' || $start === '' || $end === '') {
            throw new \InvalidArgumentException('name, code, start_point and end_point are required');
        }
        $fee = (float)($data['fee'] ?? 0);
        $capacity = (int)($data['max_capacity'] ?? $data['capacity'] ?? 0);
        if ($fee < 0 || $capacity < 1) throw new \InvalidArgumentException('fee must be zero or greater and capacity must be at least 1');
        $exists = $this->db->prepare('SELECT id FROM transport_routes WHERE code=? LIMIT 1'); $exists->execute([$code]);
        if ($exists->fetchColumn()) throw new \InvalidArgumentException('A route with this code already exists');
        $sql = "INSERT INTO transport_routes (name, code, description, start_point, end_point, fee, morning_departure, afternoon_departure, estimated_duration, max_capacity, current_capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $name,
            $code,
            $data['description'] ?? null,
            $start,
            $end,
            $fee,
            $data['morning_departure'] ?? $data['am_pickup'] ?? '06:30:00',
            $data['afternoon_departure'] ?? $data['pm_dropoff'] ?? '16:30:00',
            (int)($data['estimated_duration'] ?? 60),
            $capacity,
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateRoute($id, $data)
    {
        $name = trim((string)($data['name'] ?? $data['route_name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? $data['route_code'] ?? '')));
        $start = trim((string)($data['start_point'] ?? $data['start'] ?? ''));
        $end = trim((string)($data['end_point'] ?? $data['end'] ?? ''));
        if ($name === '' || $code === '' || $start === '' || $end === '') throw new \InvalidArgumentException('name, code, start_point and end_point are required');
        $fee = (float)($data['fee'] ?? 0); $capacity = (int)($data['max_capacity'] ?? $data['capacity'] ?? 0);
        if ($fee < 0 || $capacity < 1) throw new \InvalidArgumentException('fee must be zero or greater and capacity must be at least 1');
        $exists = $this->db->prepare('SELECT id FROM transport_routes WHERE code=? AND id<>? LIMIT 1'); $exists->execute([$code, $id]);
        if ($exists->fetchColumn()) throw new \InvalidArgumentException('A route with this code already exists');
        $sql = "UPDATE transport_routes SET name=?, code=?, description=?, start_point=?, end_point=?, fee=?, morning_departure=?, afternoon_departure=?, estimated_duration=?, max_capacity=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $name, $code,
            $data['description'] ?? null,
            $start, $end, $fee,
            $data['morning_departure'] ?? $data['am_pickup'] ?? '06:30:00',
            $data['afternoon_departure'] ?? $data['pm_dropoff'] ?? '16:30:00',
            (int)($data['estimated_duration'] ?? 60), $capacity,
            $data['status'] ?? 'active',
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateRoute($id)
    {
        $stmt = $this->db->prepare("UPDATE transport_routes SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteRoute($id)
    {
        $check = $this->db->prepare("SELECT COUNT(*) FROM student_transport_assignments WHERE route_id=? AND status IN ('active','suspended')");
        $check->execute([$id]);
        if ((int)$check->fetchColumn() > 0) throw new \RuntimeException('This route has active student assignments. Deactivate it instead of deleting it.');
        $stmt = $this->db->prepare("DELETE FROM transport_routes WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getRoute($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_routes WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllRoutes()
    {
        $stmt = $this->db->prepare("SELECT r.*, COUNT(DISTINCT ts.id) AS stop_count,
                    COUNT(DISTINCT CASE WHEN a.status IN ('active','suspended') THEN a.student_id END) AS student_count,
                    v.id AS vehicle_id, v.registration_number AS vehicle_registration,
                    v.status AS vehicle_status, s.id AS driver_id,
                    CONCAT(p.first_name, ' ', p.last_name) AS driver_name
             FROM transport_routes r
             LEFT JOIN transport_stops ts ON ts.route_id=r.id AND ts.status='active'
             LEFT JOIN student_transport_assignments a ON a.route_id=r.id
             LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id=r.id AND tvr.status='active'
             LEFT JOIN transport_vehicles v ON v.id=tvr.vehicle_id
             LEFT JOIN staff s ON s.id=v.driver_id
             LEFT JOIN persons p ON p.id=s.person_id
             GROUP BY r.id, v.id, s.id, p.first_name, p.last_name
             ORDER BY r.name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Assign stops to route, set order, geolocation (geolocation serialized into `location`)
    public function assignStopToRoute($routeId, $stopId, $order, $lat, $lng)
    {
        $location = (!empty($lat) && !empty($lng)) ? $lat . ',' . $lng : null;
        $sql = "UPDATE transport_stops SET route_id=?, sequence=?, location=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId, $order, $location, $stopId]);
        return $stmt->rowCount() > 0;
    }
    // Get stops for a route (ordered)
    public function getStopsForRoute($routeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops WHERE route_id=? ORDER BY sequence ASC");
        $stmt->execute([$routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
