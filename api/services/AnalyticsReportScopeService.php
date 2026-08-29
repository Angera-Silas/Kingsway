<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Validates report filters and intersects requested scope with staff assignments.
 */
final class AnalyticsReportScopeService
{
    private PDO $db;
    private TeacherScopeService $teacherScope;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->teacherScope = new TeacherScopeService($db);
    }

    public function apply(array $definition, array $user, array $requestedParameters): array
    {
        $parameters = $this->allowlistedParameters(
            $requestedParameters,
            $definition['allowed_filters'] ?? [],
            $definition['default_filters'] ?? []
        );
        $this->assertRequiredFilters($parameters, $definition['required_filters'] ?? []);

        $scopeTypes = $definition['scope_types'] ?? [];
        if (in_array('school', $scopeTypes, true)) {
            return [
                'parameters' => $parameters,
                'scope' => ['type' => 'school'],
                'warnings' => [],
            ];
        }

        if (in_array('class', $scopeTypes, true) || in_array('learning_area', $scopeTypes, true)) {
            return $this->applyTeacherScope($definition, $user, $parameters, $scopeTypes);
        }

        if (in_array('own', $scopeTypes, true)) {
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            if (!$userId) {
                throw new RuntimeException('Authenticated user scope could not be resolved.', 403);
            }
            $parameters['user_id'] = (int) $userId;
            return [
                'parameters' => $parameters,
                'scope' => ['type' => 'own', 'user_id' => (int) $userId],
                'warnings' => [],
            ];
        }

        throw new RuntimeException('The report scope type is not implemented safely.', 403);
    }

    private function applyTeacherScope(array $definition, array $user, array $parameters, array $scopeTypes): array
    {
        $yearId = $this->positiveInt($parameters['academic_year_id'] ?? $parameters['year_id'] ?? null);
        $termId = $this->positiveInt($parameters['term_id'] ?? $parameters['academic_year_term_id'] ?? null);
        $scope = $this->teacherScope->forUser($user, $yearId, $termId);
        if (empty($scope['is_teacher']) || empty($scope['visible_stream_ids'])) {
            throw new RuntimeException('No active teaching assignment is available for this report.', 403);
        }

        $pairs = $scope['class_stream_pairs'] ?? [];
        if ($pairs === []) {
            throw new RuntimeException('No assigned class stream is available for this report.', 403);
        }
        $classIds = array_values(array_unique(array_map(static function (array $pair): int {
            return (int) $pair['class_id'];
        }, $pairs)));
        $classTeacherPairs = $scope['class_teacher_pairs'] ?? [];
        $defaultPairs = $classTeacherPairs !== [] ? $classTeacherPairs : $pairs;
        $defaultClassIds = array_values(array_unique(array_map(static function (array $pair): int {
            return (int) $pair['class_id'];
        }, $defaultPairs)));

        $classId = $this->positiveInt($parameters['class_id'] ?? null);
        if (!$classId) {
            if (count($defaultClassIds) !== 1) {
                throw new RuntimeException('Select one of your assigned classes to run this report.', 422);
            }
            $classId = $defaultClassIds[0];
            $parameters['class_id'] = $classId;
        }
        if (!in_array($classId, $classIds, true)) {
            throw new RuntimeException('The selected class is outside your teaching assignment.', 403);
        }

        $classPairs = array_values(array_filter($pairs, static function (array $pair) use ($classId): bool {
            return (int) $pair['class_id'] === $classId;
        }));
        $streamIds = array_values(array_unique(array_map(static function (array $pair): int {
            return (int) $pair['stream_id'];
        }, $classPairs)));
        $defaultClassPairs = array_values(array_filter($defaultPairs, static function (array $pair) use ($classId): bool {
            return (int) $pair['class_id'] === $classId;
        }));
        $defaultStreamIds = array_values(array_unique(array_map(static function (array $pair): int {
            return (int) $pair['stream_id'];
        }, $defaultClassPairs)));
        $streamId = $this->positiveInt($parameters['stream_id'] ?? null);
        $supportsStream = in_array('stream_id', $definition['allowed_filters'] ?? [], true);
        if (!$streamId) {
            if (count($defaultStreamIds) === 1) {
                $streamId = $defaultStreamIds[0];
                $parameters['stream_id'] = $streamId;
            } elseif ($supportsStream) {
                throw new RuntimeException('Select one of your assigned streams to prevent cross-stream disclosure.', 422);
            } else {
                throw new RuntimeException('This report cannot yet enforce your stream-level scope safely.', 403);
            }
        }
        if (!in_array($streamId, $streamIds, true)) {
            throw new RuntimeException('The selected stream is outside your teaching assignment.', 403);
        }

        $effective = [
            'type' => 'class',
            'academic_year_id' => $scope['academic_year_id'] ?? null,
            'academic_year_term_id' => $scope['academic_year_term_id'] ?? null,
            'class_id' => $classId,
            'stream_id' => $streamId,
            'staff_id' => $scope['staff_id'] ?? null,
        ];

        if (in_array('learning_area', $scopeTypes, true)) {
            $areaId = $this->positiveInt($parameters['learning_area_id'] ?? null);
            $allowedAreas = [];
            foreach (($scope['subject_assignments'] ?? []) as $assignment) {
                if ((int) $assignment['stream_id'] === $streamId) {
                    $allowedAreas[] = (int) $assignment['learning_area_id'];
                }
            }
            $allowedAreas = array_values(array_unique($allowedAreas));
            if (!$areaId || !in_array($areaId, $allowedAreas, true)) {
                throw new RuntimeException('Select a learning area assigned to you for this stream.', 403);
            }
            $parameters['learning_area_id'] = $areaId;
            $effective['type'] = 'learning_area';
            $effective['learning_area_id'] = $areaId;
        }

        return ['parameters' => $parameters, 'scope' => $effective, 'warnings' => []];
    }

    private function allowlistedParameters(array $requested, array $allowed, array $defaults): array
    {
        $parameters = [];
        foreach ($defaults as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $parameters[$key] = $value;
            }
        }
        foreach ($allowed as $key) {
            if (!is_string($key) || !array_key_exists($key, $requested)) {
                continue;
            }
            $parameters[$key] = $this->validateValue($key, $requested[$key]);
        }
        return $parameters;
    }

    private function validateValue(string $key, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (substr($key, -3) === '_id') {
            $id = $this->positiveInt($value);
            if (!$id) {
                throw new RuntimeException('Invalid identifier supplied for ' . $key . '.', 422);
            }
            return $id;
        }
        if ($key === 'year') {
            $year = (string) $value;
            if (!preg_match('/^\d{4}$/', $year)) {
                throw new RuntimeException('Invalid reporting year.', 422);
            }
            return (int) $year;
        }
        if (in_array($key, ['date', 'date_from', 'date_to'], true)) {
            $date = (string) $value;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new RuntimeException('Invalid date supplied for ' . $key . '.', 422);
            }
            return $date;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || strlen($value) > 160) {
            throw new RuntimeException('Invalid report filter value for ' . $key . '.', 422);
        }
        return trim($value);
    }

    private function assertRequiredFilters(array $parameters, array $required): void
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $parameters) || $parameters[$key] === null || $parameters[$key] === '') {
                throw new RuntimeException('Required report filter is missing: ' . $key . '.', 422);
            }
        }
    }

    private function positiveInt($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }
}
