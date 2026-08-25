<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Resolves the effective academic scope of teaching staff.
 *
 * Class responsibility gives full visibility of an assigned stream. Subject
 * responsibility gives learning-area visibility across assigned streams.
 * Stream-specific assignments are preferred; legacy class-level assignments
 * remain a deliberate fallback and therefore apply to every active stream in
 * that class.
 */
final class TeacherScopeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function forUser(array $user, ?int $academicYearId = null, ?int $termId = null): array
    {
        $staffId = $this->staffId($user);
        $yearId = $academicYearId ?: $this->currentYearId();
        $termId = $termId ?: $this->currentTermId($yearId);
        $empty = [
            'staff_id' => $staffId,
            'academic_year_id' => $yearId,
            'academic_year_term_id' => $termId,
            'class_stream_ids' => [],
            'subject_stream_ids' => [],
            'subject_assignments' => [],
            'visible_stream_ids' => [],
            'class_teacher_stream_ids' => [],
            'class_stream_pairs' => [],
            'subject_stream_pairs' => [],
            'is_teacher' => false,
        ];
        if (!$staffId || !$yearId || !$termId) return $empty;

        $scope = $empty;
        $scope['is_teacher'] = true;
        $class = $this->db->prepare(
            "SELECT aycs.id FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND aycs.class_teacher_id = ?
               AND aycs.status IN ('planning','active')"
        );
        $class->execute([$yearId, $staffId]);
        $scope['class_teacher_stream_ids'] = array_map('intval', $class->fetchAll(PDO::FETCH_COLUMN));
        $scope['class_stream_ids'] = $scope['class_teacher_stream_ids'];

        $legacy = $this->db->prepare(
            "SELECT DISTINCT aycs.id AS stream_id, aycla.learning_area_id, t.role
             FROM academic_year_class_learning_area_teachers t
             JOIN academic_year_class_learning_areas aycla ON aycla.id = t.academic_year_class_learning_area_id
             JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
             JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
             WHERE t.staff_id = ? AND t.academic_year_term_id = ?
               AND ayc.academic_year_id = ? AND aycs.status IN ('planning','active')
               AND t.role IN ('subject_teacher','assistant','hod')"
        );
        $legacy->execute([$staffId, $termId, $yearId]);
        $legacyRows = $legacy->fetchAll(PDO::FETCH_ASSOC);
        $specificRows = [];
        $overrideKeys = [];

        // The migration may not have been applied yet in an older installation.
        try {
            $specific = $this->db->prepare(
                "SELECT x.academic_year_class_stream_id AS stream_id,
                        cla.learning_area_id, x.role
                 FROM academic_year_class_stream_learning_area_teachers x
                 JOIN academic_year_class_streams aycs ON aycs.id = x.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN academic_year_class_stream_learning_areas sla ON sla.id = x.academic_year_class_stream_learning_area_id
                 JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id
                 WHERE x.staff_id = ? AND x.academic_year_term_id = ?
                   AND ayc.academic_year_id = ? AND x.status = 'active'
                   AND aycs.status IN ('planning','active')"
            );
            $specific->execute([$staffId, $termId, $yearId]);
            $specificRows = $specific->fetchAll(PDO::FETCH_ASSOC);
            $overrides = $this->db->prepare(
                "SELECT DISTINCT x.academic_year_class_stream_id AS stream_id, cla.learning_area_id
                 FROM academic_year_class_stream_learning_area_teachers x
                 JOIN academic_year_class_streams aycs ON aycs.id = x.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN academic_year_class_stream_learning_areas sla ON sla.id = x.academic_year_class_stream_learning_area_id
                 JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id
                 WHERE x.academic_year_term_id = ? AND ayc.academic_year_id = ? AND x.status = 'active'"
            );
            $overrides->execute([$termId, $yearId]);
            foreach ($overrides->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $overrideKeys[(int)$row['stream_id'] . ':' . (int)$row['learning_area_id']] = true;
            }
        } catch (\Throwable $ignored) {
            // Backward-compatible fallback until migration 151 is installed.
        }

        // A stream-specific assignment overrides a broad class-level
        // assignment for the same stream and learning area. This prevents a
        // teacher from retaining access after a parallel stream is explicitly
        // assigned to another specialist.
        $rows = array_values(array_filter($legacyRows, static function (array $row) use ($overrideKeys): bool {
            return !isset($overrideKeys[(int)$row['stream_id'] . ':' . (int)$row['learning_area_id']]);
        }));
        $rows = array_merge($rows, $specificRows);

        foreach ($rows as $row) {
            $streamId = (int)$row['stream_id'];
            $areaId = (int)$row['learning_area_id'];
            if ($streamId <= 0 || $areaId <= 0) continue;
            $scope['subject_stream_ids'][$streamId] = true;
            $scope['subject_assignments'][] = [
                'stream_id' => $streamId,
                'learning_area_id' => $areaId,
                'role' => (string)$row['role'],
            ];
        }
        $scope['subject_stream_ids'] = array_map('intval', array_keys($scope['subject_stream_ids']));
        $scope['visible_stream_ids'] = array_values(array_unique(array_merge(
            $scope['class_stream_ids'], $scope['subject_stream_ids']
        )));
        if ($scope['visible_stream_ids']) {
            $marks = implode(',', array_fill(0, count($scope['visible_stream_ids']), '?'));
            $pair = $this->db->prepare(
                "SELECT aycs.id, ayc.class_id, aycs.stream_id
                 FROM academic_year_class_streams aycs
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 WHERE aycs.id IN ($marks)"
            );
            $pair->execute($scope['visible_stream_ids']);
            foreach ($pair->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pairValue = [
                    'class_id' => (int)$row['class_id'],
                    'stream_id' => (int)$row['stream_id'],
                ];
                $scope['class_stream_pairs'][] = $pairValue;
                if (in_array((int)$row['id'], $scope['subject_stream_ids'], true)) {
                    $scope['subject_stream_pairs'][] = $pairValue;
                }
            }
        }
        return $scope;
    }

    private function staffId(array $user): ?int
    {
        if (!empty($user['staff_id'])) return (int)$user['staff_id'];
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        if (!$userId) return null;
        $stmt = $this->db->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? AND s.status = 'active' LIMIT 1");
        $stmt->execute([(int)$userId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function currentYearId(): ?int
    {
        $id = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1")->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function currentTermId(?int $yearId): ?int
    {
        if (!$yearId) return null;
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? ORDER BY (status = 'current') DESC, id DESC LIMIT 1");
        $stmt->execute([$yearId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }
}
