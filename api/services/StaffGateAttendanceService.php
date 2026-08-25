<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;

/** Converts trusted QR/barcode/fingerprint gateway events into staff attendance. */
class StaffGateAttendanceService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        date_default_timezone_set('Africa/Nairobi');
    }

    public function record(array $data): array
    {
        $deviceCode = trim((string)($data['device_code'] ?? ''));
        $eventId = trim((string)($data['provider_event_id'] ?? $data['event_id'] ?? ''));
        $credentialType = strtolower(trim((string)($data['credential_type'] ?? 'qr')));
        $credential = trim((string)($data['credential_reference'] ?? $data['card_number'] ?? $data['credential'] ?? ''));
        $captured = $data['captured_at'] ?? date('Y-m-d H:i:s');
        if ($deviceCode === '' || $eventId === '' || $credential === '') throw new \InvalidArgumentException('device_code, provider_event_id and credential_reference are required');
        if (!in_array($credentialType, ['qr','barcode','fingerprint','other'], true)) throw new \InvalidArgumentException('Unsupported credential type');

        $device = $this->db->prepare("SELECT * FROM staff_gate_devices WHERE device_code=? AND status='active' LIMIT 1");
        $device->execute([$deviceCode]); $deviceRow = $device->fetch(PDO::FETCH_ASSOC);
        if (!$deviceRow) throw new \RuntimeException('Gate device is not active');
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
        try {
            $this->db->prepare("INSERT INTO staff_gate_events (provider_event_id,device_id,credential_type,credential_reference,captured_at,processing_status,payload_hash) VALUES (?,?,?,?,?,'rejected',?)")
                ->execute([$eventId, (int)$deviceRow['id'], $credentialType, $credential, $captured, $hash]);
            $eventDbId = (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['status'=>'duplicate','provider_event_id'=>$eventId];
            throw $e;
        }

        $staffId = $this->resolveStaff($credentialType, $credential);
        if (!$staffId) return $this->reject($eventDbId, 'Credential is not linked to an active staff member');
        $date = date('Y-m-d', strtotime($captured)); $time = date('H:i:s', strtotime($captured));
        $year = $this->db->prepare("SELECT academic_year_id FROM academic_year_terms WHERE ? BETWEEN opening_date AND closing_date ORDER BY academic_year_id DESC LIMIT 1");
        $year->execute([$date]);
        $yearId = $year->fetchColumn() ?: null;
        $open = $this->db->prepare("SELECT id FROM staff_attendance WHERE staff_id=? AND date=? AND check_in IS NOT NULL AND check_out IS NULL ORDER BY id DESC LIMIT 1");
        $open->execute([$staffId, $date]); $openId = $open->fetchColumn();
        $explicit = $data['event_type'] ?? null;
        if ($explicit && !in_array($explicit, ['check_in','check_out'], true)) return $this->reject($eventDbId, 'Invalid event_type');
        $type = $explicit ?: ($openId ? 'check_out' : 'check_in');
        if ($type === 'check_in' && !$openId) {
            $this->db->prepare("INSERT INTO staff_attendance (staff_id,date,academic_year_id,status,check_in,marked_by,notes) VALUES (?,?,?,'present',?,NULL,?)")
                ->execute([$staffId, $date, $yearId, $time, 'Gate ' . $deviceCode . ' ' . $credentialType]);
        } elseif ($type === 'check_out' && $openId) {
            $this->db->prepare("UPDATE staff_attendance SET check_out=?, updated_at=NOW() WHERE id=?")->execute([$time, $openId]);
        } else return $this->reject($eventDbId, 'Event does not match the current open attendance state');
        $this->db->prepare("UPDATE staff_gate_events SET staff_id=?,event_type=?,processing_status='processed' WHERE id=?")->execute([$staffId, $type, $eventDbId]);
        $this->db->prepare("UPDATE staff_gate_devices SET last_seen_at=NOW() WHERE id=?")->execute([(int)$deviceRow['id']]);
        return ['status'=>'processed','provider_event_id'=>$eventId,'staff_id'=>(int)$staffId,'event_type'=>$type,'captured_at'=>$captured];
    }

    private function resolveStaff(string $type, string $credential): ?int
    {
        if ($type === 'fingerprint') {
            $stmt = $this->db->prepare("SELECT staff_id FROM staff_biometric_credentials WHERE provider_reference=? AND status='active' LIMIT 1");
            try { $stmt->execute([$credential]); $id = $stmt->fetchColumn(); return $id ? (int)$id : null; } catch (\PDOException $e) { return null; }
        }
        $stmt = $this->db->prepare("SELECT staff_id FROM staff_id_cards WHERE card_number=? AND status IN ('generated','issued') AND (expires_at IS NULL OR expires_at >= CURDATE()) LIMIT 1");
        $stmt->execute([$credential]); $id = $stmt->fetchColumn(); return $id ? (int)$id : null;
    }

    private function reject(int $eventId, string $reason): array
    {
        $this->db->prepare("UPDATE staff_gate_events SET processing_status='rejected',rejection_reason=? WHERE id=?")->execute([$reason, $eventId]);
        return ['status'=>'rejected','reason'=>$reason];
    }
}
