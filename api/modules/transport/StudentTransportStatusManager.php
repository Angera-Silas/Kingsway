<?php
namespace App\API\Modules\transport;

use PDO;
use Exception;

class StudentTransportStatusManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // Check assignment and payment status for a student for a given month/year
    public function checkStatus($studentId, $month, $year)
    {
        $stmt = $this->db->prepare("CALL sp_check_student_transport_status(?, ?, ?)");
        $stmt->execute([$studentId, $month, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // For QR scan: get current status (student, route, stop, driver, vehicle, payment)
    public function getCurrentStatus($studentId)
    {
        $now = new \DateTime();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');
        return $this->getFullStatus($studentId, $month, $year);
    }

    // Get full status for QR or manifest (student, route, stop, driver, vehicle, payment)
    public function getFullStatus($studentId, $month, $year)
    {
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT s.id AS student_id, p.first_name, p.last_name, s.admission_no,
                   a.route_id, r.name AS route_name, a.stop_id, st.name AS stop_name,
                   d.id AS driver_id, dp.first_name AS driver_first_name, dp.last_name AS driver_last_name, dp.phone AS driver_phone,
                   v.id AS vehicle_id, v.registration_number AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   a.month, a.year, a.status AS assignment_status,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM transport_monthly_bills b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?) AS payment_amount,
                   (SELECT b.payment_status FROM transport_monthly_bills b
                     WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?
                     LIMIT 1) AS payment_status
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            JOIN persons p ON p.id = s.person_id
            JOIN transport_routes r ON a.route_id = r.id
            JOIN transport_stops st ON a.stop_id = st.id
            LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = r.id AND tvr.status = 'active'
            LEFT JOIN transport_vehicles v ON v.id = tvr.vehicle_id
            LEFT JOIN staff d ON d.id = v.driver_id AND d.position = 'Driver'
            LEFT JOIN persons dp ON dp.id = d.person_id
            WHERE a.student_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $studentId, $month, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get route manifest (all students, stops, driver, vehicle, payment status)
    public function getRouteManifest($routeId, $month, $year)
    {
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT s.id AS student_id, p.first_name, p.last_name, s.admission_no,
                   a.stop_id, st.name AS stop_name,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM transport_monthly_bills b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?) AS payment_amount,
                   (SELECT b.payment_status FROM transport_monthly_bills b
                     WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?
                     LIMIT 1) AS payment_status
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            JOIN persons p ON p.id = s.person_id
            JOIN transport_stops st ON a.stop_id = st.id
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            ORDER BY st.name, s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get payment/arrears/credit summary for a student
    public function getStudentSummary($studentId)
    {
        $sql = "
            SELECT COALESCE(SUM(p.amount), 0) AS total_paid,
                   (SELECT COALESCE(SUM(expected_amount), 0)
                      FROM student_transport_assignments WHERE student_id = ? AND status = 'active') AS total_expected,
                   COALESCE(SUM(p.amount), 0) - (SELECT COALESCE(SUM(expected_amount), 0)
                      FROM student_transport_assignments WHERE student_id = ? AND status = 'active') AS balance
            FROM transport_monthly_bills b
            JOIN transport_bill_payments p ON p.bill_id = b.id
            WHERE b.student_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $studentId, $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get summary for all students on a route
    public function getRouteSummary($routeId, $month, $year)
    {
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT a.student_id, p.first_name, p.last_name, s.admission_no,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM transport_monthly_bills b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?) AS total_paid,
                   a.expected_amount,
                   (a.expected_amount - (SELECT COALESCE(SUM(bp.amount), 0) FROM transport_monthly_bills b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?)) AS balance
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            JOIN persons p ON p.id = s.person_id
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            ORDER BY s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
