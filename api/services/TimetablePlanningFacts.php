<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * The facts the timetable planning assistant answers from.
 *
 * Built from the same teaching load the draft solver uses, so the assistant
 * and the Generate button can never disagree about what is ready. Everything
 * is aggregate: class and learning-area names, counts and totals. Teachers
 * appear only as counts and loads, never by name or id.
 */
final class TimetablePlanningFacts
{
    private const MAX_ITEMS = 20;

    public function __construct(private PDO $db, private ?TeachingLoadResolver $resolver = null)
    {
    }

    /**
     * @return array{period_count:string, lessons_ready:string, stream_count:string,
     *               lessons_to_place:string, stream_loads:list<string>,
     *               setup_gaps:list<string>, teacher_load:list<string>}
     */
    public function build(int $academicYearId, int $academicYearTermId, string $scope): array
    {
        $resolver = $this->resolver ?? new TeachingLoadResolver($this->db);
        $load = $resolver->resolve($academicYearId, $academicYearTermId);
        $periods = (int) ($load['lesson_periods_per_week'] ?? 0);

        $streams = array_values(array_filter(
            (array) ($load['streams'] ?? []),
            static fn(array $s): bool => self::inScope((string) ($s['class_name'] ?? ''), $scope)
        ));
        $streamIds = array_fill_keys(array_map(static fn(array $s): int => (int) $s['academic_year_class_stream_id'], $streams), true);
        $lessons = array_values(array_filter(
            (array) ($load['lessons'] ?? []),
            static fn(array $l): bool => isset($streamIds[(int) ($l['academic_year_class_stream_id'] ?? 0)])
        ));

        // Per stream: lessons set against the periods in a week.
        $setPerStream = [];
        $toPlace = 0;
        $gaps = [];
        foreach ($lessons as $lesson) {
            $streamId = (int) $lesson['academic_year_class_stream_id'];
            $perWeek = (int) ($lesson['lessons_per_week'] ?? 0);
            $setPerStream[$streamId] = ($setPerStream[$streamId] ?? 0) + $perWeek;
            $problem = (string) ($lesson['problem'] ?? '');
            if ($problem === '' && $perWeek > 0) {
                $toPlace += $perWeek;
            } elseif ($problem !== '') {
                $area = (string) ($lesson['learning_area_name'] ?? '') ?: 'A learning area';
                $gaps[$area . '|' . $problem] = ($gaps[$area . '|' . $problem] ?? 0) + 1;
            }
        }
        $streamLines = [];
        foreach ($streams as $stream) {
            $id = (int) $stream['academic_year_class_stream_id'];
            $set = $setPerStream[$id] ?? 0;
            $name = trim(($stream['class_name'] ?? '') . ' ' . ($stream['stream_name'] ?? ''));
            $streamLines[] = ['over' => $set - $periods, 'text' => $set > $periods
                ? sprintf('%s: %d lessons a week set, %d more than the %d periods', $name, $set, $set - $periods, $periods)
                : sprintf('%s: %d lessons a week set of %d periods (%d free)', $name, $set, $periods, $periods - $set)];
        }
        // Over-full streams first, then the emptiest: the lines worth reading.
        usort($streamLines, static fn(array $a, array $b): int => $b['over'] <=> $a['over']);
        if (count($streamLines) > self::MAX_ITEMS) {
            $streamLines = array_merge(
                array_slice($streamLines, 0, intdiv(self::MAX_ITEMS, 2)),
                array_slice($streamLines, -intdiv(self::MAX_ITEMS, 2))
            );
        }

        $labels = [
            'lessons_not_set' => 'weekly lessons not set',
            'no_teacher' => 'no subject teacher allocated',
            'several_teachers' => 'more than one subject teacher allocated',
        ];
        arsort($gaps);
        $gapLines = [];
        foreach (array_slice($gaps, 0, self::MAX_ITEMS, true) as $key => $count) {
            [$area, $problem] = explode('|', $key, 2);
            $gapLines[] = sprintf('%s: %s (%d stream%s)', $area, $labels[$problem] ?? $problem, $count, $count === 1 ? '' : 's');
        }

        return [
            'period_count' => (string) $periods,
            'lessons_ready' => !empty($load['lessons_ready']) ? 'yes' : 'no: the lessons-per-week update is not applied',
            'stream_count' => (string) count($streams),
            'lessons_to_place' => (string) $toPlace,
            'stream_loads' => array_map(static fn(array $l): string => $l['text'], $streamLines),
            'setup_gaps' => $gapLines,
            'teacher_load' => $this->teacherLoad($resolver, $academicYearId, $academicYearTermId, $periods),
        ];
    }

    /** Anonymous teacher load: counts and totals only. */
    private function teacherLoad(TeachingLoadResolver $resolver, int $yearId, int $termId, int $periods): array
    {
        try {
            $teachers = (array) ($resolver->workload($yearId, $termId)['teachers'] ?? []);
        } catch (\Throwable $e) {
            return [];
        }
        $loads = array_map(static fn(array $t): int => (int) ($t['lessons_per_week'] ?? 0), $teachers);
        if ($loads === []) {
            return ['No teacher has a teaching allocation yet.'];
        }
        $over = array_values(array_filter($loads, static fn(int $n): bool => $periods > 0 && $n > $periods));
        $lines = [sprintf(
            '%d teachers allocated; lessons a week per teacher range from %d to %d (average %d).',
            count($loads), min($loads), max($loads), (int) round(array_sum($loads) / count($loads))
        )];
        if ($over !== []) {
            $lines[] = sprintf(
                '%d teacher%s %s more lessons than the %d weekly periods (highest %d); a draft cannot place all of them.',
                count($over), count($over) === 1 ? '' : 's', count($over) === 1 ? 'has' : 'have', $periods, max($over)
            );
        }
        return $lines;
    }

    /** Same split as the Generate dialog: lower primary is Playgroup to Grade 3. */
    public static function inScope(string $className, string $scope): bool
    {
        if ($scope === 'whole_school') {
            return true;
        }
        $lower = (bool) preg_match('/^(play ?group|pp ?[12]\b|grade ?[1-3]\b)/i', trim($className));
        return $scope === 'lower_primary' ? $lower : !$lower;
    }
}
