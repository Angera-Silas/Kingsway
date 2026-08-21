<?php
declare(strict_types=1);

namespace App\API\Modules\transport;

use PDO;
use RuntimeException;
use App\API\Services\FinancialPostingCoordinator;

/**
 * Transport access is based on date-bounded entitlements, not on a monthly
 * subscription flag. School fees never participate in these calculations.
 */
class StudentTransportEntitlementManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureMonthlyEntitlement(int $studentId, int $assignmentId, int $routeId, string $month, float $amount, ?int $userId = null): int
    {
        $start = date('Y-m-01', strtotime($month));
        $end = date('Y-m-t', strtotime($start));
        $periodId = $this->ensurePeriod('month', $start, $end, null, date('F Y', strtotime($start)), $userId);
        $stmt = $this->db->prepare(
            "INSERT INTO student_transport_entitlements
                (student_id, assignment_id, route_id, period_id, amount_due, source_type, created_by)
             VALUES (?, ?, ?, ?, ?, 'subscription', ?)
             ON DUPLICATE KEY UPDATE amount_due = VALUES(amount_due), entitlement_status='active'"
        );
        $stmt->execute([$studentId, $assignmentId ?: null, $routeId, $periodId, $amount, $userId]);
        if ($stmt->rowCount() > 0) return (int)$this->db->lastInsertId();
        $lookup = $this->db->prepare("SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=? LIMIT 1");
        $lookup->execute([$studentId, $routeId, $periodId]);
        return (int)$lookup->fetchColumn();
    }

    public function createEntitlement(array $data, int $userId): array
    {
        $studentId = (int)($data['student_id'] ?? 0);
        $routeId = (int)($data['route_id'] ?? 0);
        $type = strtolower(trim((string)($data['period_type'] ?? '')));
        $start = (string)($data['period_start'] ?? '');
        $end = (string)($data['period_end'] ?? '');
        $amount = (float)($data['amount_due'] ?? 0);
        if (!$studentId || !$routeId || !$start || !$end || $amount < 0) throw new RuntimeException('student_id, route_id, dates and amount_due are required');
        if (!in_array($type, ['day','week','month','term','year','custom'], true)) throw new RuntimeException('Invalid transport entitlement period');
        if ($end < $start) throw new RuntimeException('period_end must not be before period_start');

        $assignment = $this->db->prepare("SELECT id FROM student_transport_assignments WHERE student_id=? AND route_id=? AND status IN ('active','suspended') ORDER BY id DESC LIMIT 1");
        $assignment->execute([$studentId, $routeId]);
        $assignmentId = (int)($assignment->fetchColumn() ?: 0);
        $periodId = $this->ensurePeriod($type, $start, $end, !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null, $data['label'] ?? null, $userId);
        $stmt = $this->db->prepare(
            "INSERT INTO student_transport_entitlements
                (student_id, assignment_id, route_id, period_id, amount_due, source_type, created_by)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount_due=VALUES(amount_due), entitlement_status='active'"
        );
        $source = in_array(($data['source_type'] ?? 'prepaid'), ['subscription','prepaid','bursary','waiver','override'], true) ? $data['source_type'] : 'prepaid';
        $stmt->execute([$studentId, $assignmentId, $routeId, $periodId, $amount, $source, $userId]);
        $id = (int)$this->db->lastInsertId();
        if (!$id) {
            $q = $this->db->prepare("SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=? LIMIT 1");
            $q->execute([$studentId, $routeId, $periodId]);
            $id = (int)$q->fetchColumn();
        }
        return $this->getEntitlement($id);
    }

    /**
     * Enrol a learner for transport in one transaction. The administrator
     * chooses the route, pickup/dropoff points, exact dates, period type, and
     * agreed amount. No distance or automatic tariff is assumed.
     */
    public function enrollStudent(array $data, int $userId): array
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $routeId = (int) ($data['route_id'] ?? 0);
        $pickupStopId = (int) ($data['pickup_stop_id'] ?? $data['stop_id'] ?? 0);
        $dropoffStopId = (int) ($data['dropoff_stop_id'] ?? $data['stop_id'] ?? 0);
        $type = strtolower(trim((string) ($data['period_type'] ?? '')));
        $start = (string) ($data['period_start'] ?? '');
        $end = (string) ($data['period_end'] ?? '');
        $amount = (float) ($data['amount_due'] ?? 0);

        if (!$studentId || !$routeId || !$pickupStopId || !$dropoffStopId || !$start || !$end) {
            throw new RuntimeException('student_id, route_id, pickup/dropoff stops, period dates and amount_due are required');
        }
        if ($amount < 0 || $end < $start) throw new RuntimeException('Invalid transport amount or date range');
        if (!in_array($type, ['day', 'week', 'month', 'term', 'year', 'custom'], true)) {
            throw new RuntimeException('Invalid transport period type');
        }

        $stopCheck = $this->db->prepare(
            'SELECT COUNT(*) FROM transport_stops WHERE route_id=? AND id IN (?,?) AND status=\'active\''
        );
        $stopCheck->execute([$routeId, $pickupStopId, $dropoffStopId]);
        if ((int) $stopCheck->fetchColumn() !== ($pickupStopId === $dropoffStopId ? 1 : 2)) {
            throw new RuntimeException('Pickup and dropoff points must belong to the selected route and be active');
        }

        $this->db->beginTransaction();
        try {
            $periodId = $this->ensurePeriod(
                $type,
                $start,
                $end,
                !empty($data['academic_year_term_id']) ? (int) $data['academic_year_term_id'] : null,
                $data['label'] ?? null,
                $userId
            );

            $month = (int) date('n', strtotime($start));
            $year = (int) date('Y', strtotime($start));
            $assignment = $this->db->prepare(
                'SELECT id FROM student_transport_assignments WHERE student_id=? AND month=? AND year=? ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $assignment->execute([$studentId, $month, $year]);
            $assignmentId = (int) ($assignment->fetchColumn() ?: 0);
            if ($assignmentId) {
                $this->db->prepare(
                    "UPDATE student_transport_assignments
                     SET route_id=?, stop_id=?, pickup_stop_id=?, dropoff_stop_id=?,
                         expected_amount=?, assignment_date=?, assigned_by=?, notes=?, status='active'
                     WHERE id=?"
                )->execute([$routeId, $pickupStopId, $pickupStopId, $dropoffStopId, $amount, $start, $userId, $data['notes'] ?? null, $assignmentId]);
            } else {
                $insertAssignment = $this->db->prepare(
                    "INSERT INTO student_transport_assignments
                     (student_id,route_id,stop_id,pickup_stop_id,dropoff_stop_id,assignment_date,assigned_by,notes,month,year,expected_amount,status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,'active')"
                );
                $insertAssignment->execute([$studentId, $routeId, $pickupStopId, $pickupStopId, $dropoffStopId, $start, $userId, $data['notes'] ?? null, $month, $year, $amount]);
                $assignmentId = (int) $this->db->lastInsertId();
            }

            $entitlement = $this->db->prepare(
                "INSERT INTO student_transport_entitlements
                 (student_id,assignment_id,route_id,period_id,amount_due,source_type,created_by)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE assignment_id=VALUES(assignment_id), amount_due=VALUES(amount_due), entitlement_status='active'"
            );
            $source = in_array(($data['source_type'] ?? 'subscription'), ['subscription','prepaid','bursary','waiver','override'], true)
                ? $data['source_type'] : 'subscription';
            $entitlement->execute([$studentId, $assignmentId, $routeId, $periodId, $amount, $source, $userId]);
            $id = (int) $this->db->lastInsertId();
            if (!$id) {
                $lookup = $this->db->prepare('SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=?');
                $lookup->execute([$studentId, $routeId, $periodId]);
                $id = (int) $lookup->fetchColumn();
            }
            $this->db->commit();
            return $this->getEntitlement($id) + ['assignment_id' => $assignmentId, 'pickup_stop_id' => $pickupStopId, 'dropoff_stop_id' => $dropoffStopId];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function recordPayment(int $entitlementId, array $data, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT e.*, p.period_start, p.period_end
                 FROM student_transport_entitlements e JOIN transport_entitlement_periods p ON p.id=e.period_id
                 WHERE e.id=? AND e.entitlement_status='active' FOR UPDATE"
            );
            $stmt->execute([$entitlementId]);
            $e = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$e) throw new RuntimeException('Transport entitlement not found');
            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) throw new RuntimeException('Payment amount must be positive');
            $method = strtolower((string)($data['payment_method'] ?? 'cash'));
            if (in_array($method, ['mpesa', 'daraja_mpesa', 'buni_mpesa'], true) && empty($data['verified_provider_callback'])) {
                throw new RuntimeException('Mobile-money transport payments must be allocated from a verified provider confirmation');
            }
            $paidStmt = $this->db->prepare("SELECT COALESCE(SUM(a.amount),0) FROM transport_entitlement_payment_allocations a JOIN transport_entitlement_payments p ON p.id=a.payment_id AND p.payment_status='confirmed' WHERE a.entitlement_id=?");
            $paidStmt->execute([$entitlementId]);
            $remaining = max(0, (float)$e['amount_due'] - (float)$paidStmt->fetchColumn());
            if ($amount > $remaining && $remaining > 0 && empty($data['allow_credit'])) throw new RuntimeException('Payment exceeds this entitlement balance');
            $ref = trim((string)($data['provider_reference'] ?? '')) ?: null;
            $insert = $this->db->prepare("INSERT INTO transport_entitlement_payments (student_id,financial_account_id,amount,payment_method,provider_name,provider_reference,payment_status,payment_date,received_by,notes) VALUES (?,?,?,?,?,?,'confirmed',?,?,?)");
            $insert->execute([(int)$e['student_id'], (int)($data['financial_account_id'] ?? 0) ?: null, $amount, $method, $data['provider_name'] ?? 'manual', $ref, $data['payment_date'] ?? date('Y-m-d'), $userId ?: null, $data['notes'] ?? null]);
            $paymentId = (int)$this->db->lastInsertId();
            $allocation = $this->db->prepare("INSERT INTO transport_entitlement_payment_allocations (payment_id,entitlement_id,amount) VALUES (?,?,?)");
            $allocation->execute([$paymentId, $entitlementId, $amount]);
            if (!empty($data['financial_account_id'])) {
                (new FinancialPostingCoordinator($this->db))->postIncoming(
                    'transport_entitlement_payment',
                    $paymentId,
                    (int) $data['financial_account_id'],
                    'transport',
                    number_format($amount, 2, '.', ''),
                    $userId,
                    $ref
                );
            }
            $this->db->commit();
            return ['payment_id'=>$paymentId, 'entitlement_id'=>$entitlementId, 'amount'=>$amount, 'remaining_balance'=>max(0,$remaining-$amount), 'period_start'=>$e['period_start'], 'period_end'=>$e['period_end']];
        } catch (\Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function getAccess(int $studentId, int $routeId, string $date): array
    {
        $override = $this->db->prepare("SELECT id FROM transport_access_overrides WHERE student_id=? AND route_id=? AND status='active' AND valid_from<=? AND valid_until>=? LIMIT 1");
        $at = $date . ' ' . date('H:i:s');
        $override->execute([$studentId, $routeId, $at, $at]);
        if ($override->fetchColumn()) return ['decision'=>'authorized_override','payment_status'=>'waived','balance'=>0,'period_type'=>'override'];

        $stmt = $this->db->prepare(
            "SELECT e.id, e.amount_due, e.source_type, p.period_type, p.period_start, p.period_end,
                    COALESCE(SUM(CASE WHEN tp.payment_status='confirmed' THEN a.amount ELSE 0 END),0) paid
             FROM student_transport_entitlements e
             JOIN transport_entitlement_periods p ON p.id=e.period_id
             LEFT JOIN transport_entitlement_payment_allocations a ON a.entitlement_id=e.id
             LEFT JOIN transport_entitlement_payments tp ON tp.id=a.payment_id
             WHERE e.student_id=? AND e.route_id=? AND e.entitlement_status='active'
               AND p.status='open' AND p.period_start<=? AND p.period_end>=?
             GROUP BY e.id, e.amount_due, e.source_type, p.period_type, p.period_start, p.period_end
             ORDER BY DATEDIFF(p.period_end,p.period_start) DESC, e.id DESC"
        );
        $stmt->execute([$studentId, $routeId, $date, $date]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $partial = null;
        foreach ($candidates as $row) {
            $due = (float)$row['amount_due']; $paid = (float)$row['paid'];
            if (in_array($row['source_type'], ['waiver', 'bursary'], true) || $due <= 0 || $paid >= $due) return ['decision'=>'approved','payment_status'=>in_array($row['source_type'], ['waiver', 'bursary'], true) ? $row['source_type'] : ($due<=0?'waived':'paid'),'balance'=>max(0,$due-$paid),'period_type'=>$row['period_type'],'period_start'=>$row['period_start'],'period_end'=>$row['period_end'],'entitlement_id'=>(int)$row['id']];
            // A partially paid annual entitlement must not block a fully paid
            // month/term entitlement that also covers this date.
            if ($partial === null) $partial = ['decision'=>'deny_unpaid','payment_status'=>$paid>0?'partial':'unpaid','balance'=>max(0,$due-$paid),'period_type'=>$row['period_type'],'period_start'=>$row['period_start'],'period_end'=>$row['period_end'],'entitlement_id'=>(int)$row['id']];
        }
        return $partial ?: ['decision'=>'deny_no_entitlement','payment_status'=>'unpaid','balance'=>null,'period_type'=>null];
    }

    public function getEntitlement(int $id): array
    {
        $stmt=$this->db->prepare("SELECT e.*,p.period_type,p.period_start,p.period_end,p.label FROM student_transport_entitlements e JOIN transport_entitlement_periods p ON p.id=e.period_id WHERE e.id=?"); $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function ensurePeriod(string $type, string $start, string $end, ?int $termId, ?string $label, ?int $userId): int
    {
        $stmt=$this->db->prepare("INSERT INTO transport_entitlement_periods (period_type,period_start,period_end,academic_year_term_id,label,created_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), label=COALESCE(VALUES(label),label)"); $stmt->execute([$type,$start,$end,$termId,$label,$userId]); return (int)$this->db->lastInsertId();
    }
}
