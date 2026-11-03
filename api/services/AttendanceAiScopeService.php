<?php

namespace App\API\Services;

use PDO;

/** Rechecks the attendance stream scope recorded when an AI review was queued. */
final class AttendanceAiScopeService
{
    private const LEADERSHIP_ROLES = [
        'system administrator', 'director', 'school administrator', 'headteacher',
        'deputy head - academic', 'deputy head - discipline',
    ];

    public function canAccessLatenessScope(
        PDO $pdo,
        int $userId,
        array $roleNames,
        bool $wasRestricted,
        array $queuedStreamIds
    ): bool {
        if ($userId < 1) return false;
        $isLeader = (bool) array_intersect(
            self::LEADERSHIP_ROLES,
            array_map(static fn($role): string => strtolower(trim((string) $role)), $roleNames)
        );
        if (!$wasRestricted) return $isLeader;
        if ($isLeader) return true;

        $queuedStreamIds = array_values(array_unique(array_filter(array_map('intval', $queuedStreamIds), static fn(int $id): bool => $id > 0)));
        if ($queuedStreamIds === []) return false;

        $staff = $pdo->prepare(
            'SELECT staff_id FROM ' . ReadReplicaService::masterSourceRef('staff_directory')
            . " WHERE user_id = ? AND staff_status = 'active' LIMIT 1"
        );
        $staff->execute([$userId]);
        $staffId = (int) $staff->fetchColumn();
        if ($staffId < 1) return false;

        $yearId = (int) ($pdo->query(
            "SELECT id FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1"
        )->fetchColumn() ?: 0);
        if ($yearId < 1) return false;

        $assignments = $pdo->prepare(
            'SELECT DISTINCT v.academic_year_class_stream_id AS stream_id '
            . 'FROM vw_teacher_effective_stream_learning_areas v '
            . 'JOIN ' . ReadReplicaService::qualifiedRef('academic_year_class_streams') . ' aycs ON aycs.id = v.academic_year_class_stream_id '
            . 'JOIN ' . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id "
            . "WHERE v.staff_id = ? AND v.academic_year_id = ? AND aycs.status IN ('planning','active') AND ayc.status IN ('planning','active')"
        );
        $assignments->execute([$staffId, $yearId]);
        $currentStreamIds = array_values(array_unique(array_map('intval', array_column($assignments->fetchAll(PDO::FETCH_ASSOC) ?: [], 'stream_id'))));
        return array_diff($queuedStreamIds, $currentStreamIds) === [];
    }
}
