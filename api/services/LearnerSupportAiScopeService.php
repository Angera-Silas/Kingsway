<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\students\StudentInsightsService;
use App\API\Modules\students\StudentService;
use DomainException;
use PDO;

/** Rebuilds learner-support AI context from the operator's live student scope. */
final class LearnerSupportAiScopeService
{
    /** @var callable(PDO,array,string,array):array|null */
    private $inputBuilder;

    public function __construct(?callable $inputBuilder = null)
    {
        $this->inputBuilder = $inputBuilder;
    }

    public static function sanitizeFilters(array $filters): array
    {
        $clean = [];
        foreach (['academic_year_id', 'term_id', 'class_id', 'stream_id'] as $key) {
            if (isset($filters[$key]) && is_scalar($filters[$key])) {
                $value = (int) $filters[$key];
                if ($value > 0) $clean[$key] = $value;
            }
        }
        if (isset($filters['gender']) && is_scalar($filters['gender'])) {
            $gender = trim((string) $filters['gender']);
            if (in_array($gender, ['female', 'male'], true)) $clean['gender'] = $gender;
        }
        return $clean;
    }

    public function buildInput(
        PDO $pdo,
        int $userId,
        array $permissions,
        array $roles,
        string $requestedContext,
        array $filters
    ): array {
        if ($userId < 1 || $requestedContext === '') {
            throw new DomainException('The learner-support scope is no longer valid.', 403);
        }
        $user = [
            'user_id' => $userId,
            'effective_permissions' => array_values(array_unique(array_map('strval', $permissions))),
            'permissions' => array_values(array_unique(array_map('strval', $permissions))),
            'roles' => $roles,
        ];
        $filters = self::sanitizeFilters($filters);

        if ($this->inputBuilder !== null) {
            return (array) call_user_func($this->inputBuilder, $pdo, $user, $requestedContext, $filters);
        }

        $students = new StudentService($pdo);
        $resolved = $students->resolveContext($user, $requestedContext);
        if (empty($resolved['allowed']) || empty($resolved['context'])) {
            throw new DomainException('The learner-support scope is no longer within your access.', 403);
        }
        return (new StudentInsightsService($pdo, $students))->buildSupportPlanningInput(
            $user,
            (string) $resolved['context'],
            $filters
        );
    }
}
