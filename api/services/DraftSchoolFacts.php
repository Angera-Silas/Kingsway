<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Public facts every AI draft may use: the school's name and location, today's
 * date and the current academic year and term. Without them a model writes
 * "[School Name]" and guesses the year. No personal data.
 */
final class DraftSchoolFacts
{
    private const DEFAULT_NAME = 'Kingsway Preparatory School';

    /**
     * @return array{name:string, location:string, today:string, academic_year:string, term:string, term_dates:string}
     */
    public static function current(PDO $pdo): array
    {
        if (!defined('SCHOOL_NAME') && function_exists('App\\Config\\loadSchoolIdentity')) {
            try {
                \App\Config\loadSchoolIdentity($pdo);
            } catch (\Throwable $e) {
                // Facts are optional context; the draft still runs without them.
            }
        }
        try {
            $context = (array) (new AcademicContextService())->getCurrentContext();
        } catch (\Throwable $e) {
            $context = [];
        }
        $termDates = '';
        if (!empty($context['term_start_date']) && !empty($context['term_end_date'])) {
            $termDates = $context['term_start_date'] . ' to ' . $context['term_end_date'];
        }
        // The identity loader stores a bare "School" when the profile has no name.
        $name = defined('SCHOOL_NAME') ? trim((string) constant('SCHOOL_NAME')) : '';
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        return [
            'name' => in_array($name, ['', 'School'], true) ? self::DEFAULT_NAME : $name,
            'location' => defined('SCHOOL_ADDRESS') ? mb_substr((string) constant('SCHOOL_ADDRESS'), 0, 120) : '',
            'today' => $today->format('l j F Y'),
            'academic_year' => (string) ($context['current_year'] ?? ''),
            'term' => (string) ($context['current_term'] ?? ''),
            'term_dates' => $termDates,
        ];
    }
}
