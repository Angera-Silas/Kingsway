<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;

/** Resolves timetable selectors into server-authorized teacher assignments. */
final class TimetablePlanningCandidateService
{
    private const MAX_SELECTORS = 60;
    private const SOURCES = [
        'terms' => 'academic_year_terms',
        'calendar' => 'academic_calendar',
        'stream_areas' => 'academic_year_class_stream_learning_areas_detailed',
        'time_slots' => 'time_slots',
    ];

    /**
     * Optional callables and source refs are dependency seams for isolated tests.
     * Production calls always use the canonical scope and specialization services.
     *
     * @param null|callable(array,int,int):array $scopeResolver
     * @param null|callable(int,int,int):array $eligibleTeacherResolver
     * @param array<string,string> $sourceRefs
     */
    public function __construct(
        private PDO $db,
        private $scopeResolver = null,
        private $eligibleTeacherResolver = null,
        private array $sourceRefs = []
    ) {
        foreach ($sourceRefs as $key => $source) {
            if (!isset(self::SOURCES[$key]) || !preg_match('/^[A-Za-z0-9_`.]+$/', $source)) {
                throw new DomainException('Invalid timetable candidate source configuration.', 500);
            }
        }
    }

    /**
     * Selectors contain stream/area/day/slot only. Teacher IDs are derived from
     * approved qualifications and never accepted from the browser.
     *
     * @param list<array<string,mixed>> $selectors
     * @return list<array{academic_year_class_stream_id:int,learning_area_id:int,teacher_id:int,day_of_week:int,time_slot_id:int}>
     */
    public function resolve(
        array $user,
        int $academicYearId,
        int $academicYearTermId,
        array $selectors,
        bool $leadership
    ): array {
        if ($academicYearId < 1 || $academicYearTermId < 1) {
            throw new DomainException('Select an academic year and term before planning.', 422);
        }
        if (count($selectors) > self::MAX_SELECTORS) {
            throw new DomainException('Timetable selections exceed the safe limit.', 422);
        }
        if ($selectors === []) return [];

        $term = $this->one(
            'SELECT id, academic_year_id FROM ' . $this->source('terms') . ' WHERE id = ? LIMIT 1',
            [$academicYearTermId]
        );
        if (!$term || (int) $term['academic_year_id'] !== $academicYearId) {
            throw new DomainException('The selected term does not belong to the selected academic year.', 422);
        }

        $scope = null;
        if (!$leadership) {
            $scope = $this->scope($user, $academicYearId, $academicYearTermId);
        }

        $authorized = [];
        $selectorKeys = [];
        foreach ($selectors as $selector) {
            if (!is_array($selector)) {
                throw new DomainException('Each timetable selection must be an object.', 422);
            }
            $allowed = ['academic_year_class_stream_id', 'learning_area_id', 'day_of_week', 'time_slot_id'];
            if (array_diff(array_keys($selector), $allowed) !== []) {
                throw new DomainException('Timetable selections may not specify a teacher or other assignment fields.', 422);
            }
            $streamId = (int) ($selector['academic_year_class_stream_id'] ?? 0);
            $areaId = (int) ($selector['learning_area_id'] ?? 0);
            $day = (int) ($selector['day_of_week'] ?? 0);
            $slotId = (int) ($selector['time_slot_id'] ?? 0);
            if ($streamId < 1 || $areaId < 1 || $day < 1 || $day > 7 || $slotId < 1) {
                throw new DomainException('Each timetable selection needs a valid stream, learning area, day, and lesson slot.', 422);
            }
            $selectorKey = implode(':', [$streamId, $areaId, $day, $slotId]);
            if (isset($selectorKeys[$selectorKey])) continue;
            $selectorKeys[$selectorKey] = true;

            $stream = $this->one(
                'SELECT class_stream_id, academic_year_id, academic_year_term_id, class_stream_status '
                . 'FROM ' . $this->source('calendar')
                . ' WHERE class_stream_id = ? AND academic_year_id = ? AND academic_year_term_id = ? LIMIT 1',
                [$streamId, $academicYearId, $academicYearTermId]
            );
            if (!$stream || !in_array((string) $stream['class_stream_status'], ['planning', 'active'], true)) {
                throw new DomainException('A selected class stream is not active for this academic year and term.', 422);
            }
            if ($scope !== null && !$this->scopeAllows($scope, $streamId, $areaId)) {
                throw new DomainException('A selected class stream or learning area is outside your teaching scope.', 403);
            }

            $mapping = $this->one(
                'SELECT id, status FROM ' . $this->source('stream_areas')
                . ' WHERE academic_year_class_stream_id = ? AND learning_area_id = ? LIMIT 1',
                [$streamId, $areaId]
            );
            if (!$mapping || !in_array((string) $mapping['status'], ['planned', 'active', 'in_progress'], true)) {
                throw new DomainException('A selected learning area is not scheduled for this class stream.', 422);
            }

            $slot = $this->one(
                'SELECT id FROM ' . $this->source('time_slots')
                . " WHERE id = ? AND is_active = 1 AND slot_type = 'lesson' LIMIT 1",
                [$slotId]
            );
            if (!$slot) {
                throw new DomainException('A selected time slot is not an active lesson period.', 422);
            }

            $teachers = $this->eligibleTeachers($areaId, $academicYearId, $streamId);
            if ($teachers === []) {
                throw new DomainException('No teacher has approved qualifications for a selected learning area and stream.', 422);
            }
            foreach ($teachers as $teacher) {
                $teacherId = (int) (is_array($teacher) ? ($teacher['teacher_id'] ?? 0) : $teacher);
                if ($teacherId < 1) continue;
                $candidate = [
                    'academic_year_class_stream_id' => $streamId,
                    'learning_area_id' => $areaId,
                    'teacher_id' => $teacherId,
                    'day_of_week' => $day,
                    'time_slot_id' => $slotId,
                ];
                $authorized[TimetableAssignmentCandidateValidator::candidateKey($candidate)] = $candidate;
                if (count($authorized) > self::MAX_SELECTORS) {
                    throw new DomainException('Eligible timetable candidates exceed the safe limit. Narrow the selected streams, days, or periods.', 422);
                }
            }
        }
        if ($authorized === []) {
            throw new DomainException('No eligible teacher candidates were found for the selected timetable scope.', 422);
        }
        return array_values($authorized);
    }

    private function scope(array $user, int $yearId, int $termId): array
    {
        if (is_callable($this->scopeResolver)) {
            return ($this->scopeResolver)($user, $yearId, $termId);
        }
        return (new TeacherScopeService($this->db))->forUser($user, $yearId, $termId);
    }

    private function eligibleTeachers(int $areaId, int $yearId, int $streamId): array
    {
        if (is_callable($this->eligibleTeacherResolver)) {
            return ($this->eligibleTeacherResolver)($areaId, $yearId, $streamId);
        }
        return (new TeacherSpecializationService($this->db))->eligibleTeachers($areaId, $yearId, $streamId);
    }

    private function scopeAllows(array $scope, int $streamId, int $areaId): bool
    {
        if (in_array($streamId, array_map('intval', (array) ($scope['class_teacher_stream_ids'] ?? [])), true)) {
            return true;
        }
        foreach ((array) ($scope['subject_assignments'] ?? []) as $assignment) {
            if (is_array($assignment)
                && (int) ($assignment['stream_id'] ?? 0) === $streamId
                && (int) ($assignment['learning_area_id'] ?? 0) === $areaId) {
                return true;
            }
        }
        return false;
    }

    private function one(string $sql, array $params): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function source(string $key): string
    {
        if (isset($this->sourceRefs[$key])) return $this->sourceRefs[$key];
        return ReadReplicaService::masterSourceRef(self::SOURCES[$key]);
    }
}
