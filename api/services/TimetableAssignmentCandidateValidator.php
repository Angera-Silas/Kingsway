<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;

/** Ensures AI schedule proposals are grounded in a server-authorized candidate set. */
final class TimetableAssignmentCandidateValidator
{
    public static function assertAuthorized(array $assignments, array $candidates): void
    {
        if ($assignments === []) return;
        if ($candidates === []) {
            throw new DomainException('AI timetable assignments require server-authorized candidate evidence.', 502);
        }

        $candidateKeys = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) continue;
            $candidateKeys[self::key($candidate)] = true;
        }
        foreach ($assignments as $assignment) {
            if (!is_array($assignment) || !isset($candidateKeys[self::key($assignment)])) {
                throw new DomainException('AI timetable assignments must use only supplied authorized candidates.', 502);
            }
        }
    }

    private static function key(array $tuple): string
    {
        return implode(':', array_map(static fn(string $field): int => (int) ($tuple[$field] ?? 0), [
            'academic_year_class_stream_id', 'learning_area_id', 'teacher_id', 'day_of_week', 'time_slot_id',
        ]));
    }
}
