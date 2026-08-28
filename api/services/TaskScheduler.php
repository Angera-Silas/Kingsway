<?php

namespace App\API\Services;

/**
 * TaskScheduler - Laravel "artisan schedule" style cron evaluator, framework-free.
 *
 * Given the current local minute, decide whether a 5-field cron expression
 * (minute hour day-of-month month day-of-week) matches, so a single DirectAdmin
 * cron can drive many recurring jobs without dozens of server crons.
 *
 * Supports: * , - / and numeric fields, mirroring standard crontab (Vixie-style)
 * semantics including the 0|7 Sunday equivalence for day-of-week.
 */
class TaskScheduler
{
    /**
     * Whether a cron expression matches the given time.
     *
     * @param string $expression Five-field crontab expression, e.g. "every 5
     *                           minutes" as "min-of-hour 5" step notation.
     * @param int    $when       Unix timestamp (defaults to now).
     */
    public static function due(string $expression, ?int $when = null): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            return false;
        }
        $when = $when ?? time();
        $fields = [
            (int) date('i', $when),
            (int) date('G', $when),
            (int) date('j', $when),
            (int) date('n', $when),
            (int) date('w', $when),
        ];

        for ($i = 0; $i < 5; $i++) {
            if (!self::fieldMatches($parts[$i], $fields[$i], $i)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single cron field against a value.
     */
    private static function fieldMatches(string $field, int $value, int $index): bool
    {
        // Comma-separated list: any sub-pattern matching counts.
        foreach (explode(',', $field) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (self::stepMatches($item, $value, $index)) {
                return true;
            }
        }
        return false;
    }

    private static function stepMatches(string $item, int $value, int $index): bool
    {
        $range = $item;
        $step = 1;
        if (strpos($item, '/') !== false) {
            list($range, $stepPart) = explode('/', $item, 2);
            $step = max(1, (int) $stepPart);
        }

        list($min, $max) = self::rangeBounds($range, $index);

        if ($step > 1) {
            // Step applies from the range start.
            if ($value < $min || $value > $max) {
                return false;
            }
            return (($value - $min) % $step) === 0;
        }
        return $value >= $min && $value <= $max;
    }

    private static function rangeBounds(string $range, int $index): array
    {
        if ($range === '*') {
            return [self::fieldMin($index), self::fieldMax($index)];
        }
        if (strpos($range, '-') !== false) {
            list($a, $b) = array_map('intval', explode('-', $range, 2));
            return [$a, $b];
        }
        $v = (int) $range;
        return [$v, $v];
    }

    private static function fieldMin(int $index): int
    {
        return [0, 0, 1, 1, 0][$index];
    }

    private static function fieldMax(int $index): int
    {
        return [59, 23, 31, 12, 6][$index];
    }
}
