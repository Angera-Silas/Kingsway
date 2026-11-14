<?php

declare(strict_types=1);

namespace App\API\Services;

/**
 * Places a week's lessons into the school day's lesson periods.
 *
 * Pure and deterministic (no database): the same input always gives the same
 * timetable. Hard rules, never broken:
 *   - a stream has at most one lesson in a period;
 *   - a teacher teaches at most one stream in a period.
 * Soft preferences, used to choose between legal periods:
 *   - spread a learning area across the week (at most one lesson of it a day
 *     while lessons <= days, then as evenly as possible);
 *   - keep each stream's days evenly filled;
 *   - spread a teacher's lessons across the week.
 *
 * Lessons are placed most-constrained first (busiest teacher, then most
 * lessons). When no legal period is left for a lesson, one repair step tries
 * to move a lesson of the same teacher out of the way. Lessons that still
 * cannot be placed are returned as unplaced, never forced.
 */
final class TimetableDraftSolver
{
    /**
     * @param list<array{stream:int, area:int, teacher:int, count:int}> $requirements
     * @param list<int> $days    day numbers (1 = Monday)
     * @param list<int> $periods lesson time-slot ids in day order
     * @return array{entries: list<array{stream:int, area:int, teacher:int, day:int, period:int}>,
     *               unplaced: list<array{stream:int, area:int, teacher:int, missing:int}>}
     */
    public function solve(array $requirements, array $days, array $periods): array
    {
        $days = array_values($days);
        $periods = array_values($periods);
        $cells = [];
        foreach ($days as $d) foreach ($periods as $p) $cells[] = [$d, $p];

        // Expand to single lessons, most constrained first.
        $teacherLoad = [];
        foreach ($requirements as $r) $teacherLoad[$r['teacher']] = ($teacherLoad[$r['teacher']] ?? 0) + max(0, (int) $r['count']);
        usort($requirements, static fn($a, $b) =>
            [$teacherLoad[$b['teacher']], $b['count'], $a['stream'], $a['area']]
            <=> [$teacherLoad[$a['teacher']], $a['count'], $b['stream'], $b['area']]);

        $this->streamAt = [];
        $this->teacherAt = [];
        $this->areaDay = [];
        $this->streamDay = [];
        $this->teacherDay = [];
        $this->placed = [];
        $this->days = $days;
        $this->periodCount = max(1, count($periods));

        $unplaced = [];
        // Round-robin over requirements so one area cannot take every good
        // period before the others get any.
        $remaining = [];
        foreach ($requirements as $i => $r) $remaining[$i] = max(0, (int) $r['count']);
        $progress = true;
        while ($progress) {
            $progress = false;
            foreach ($requirements as $i => $r) {
                if ($remaining[$i] <= 0) continue;
                $remaining[$i]--;
                $progress = true;
                $lesson = ['stream' => (int) $r['stream'], 'area' => (int) $r['area'], 'teacher' => (int) $r['teacher'], 'count' => (int) $r['count']];
                if (!$this->placeBest($lesson, $cells) && !$this->repair($lesson, $cells)) {
                    $key = $lesson['stream'] . ':' . $lesson['area'];
                    $unplaced[$key] ??= ['stream' => $lesson['stream'], 'area' => $lesson['area'], 'teacher' => $lesson['teacher'], 'missing' => 0];
                    $unplaced[$key]['missing']++;
                }
            }
        }

        $entries = [];
        foreach ($this->placed as $p) {
            $entries[] = ['stream' => $p['stream'], 'area' => $p['area'], 'teacher' => $p['teacher'], 'day' => $p['day'], 'period' => $p['period']];
        }
        usort($entries, static fn($a, $b) => [$a['stream'], $a['day'], array_search($a['period'], $periods, true)]
            <=> [$b['stream'], $b['day'], array_search($b['period'], $periods, true)]);
        return ['entries' => $entries, 'unplaced' => array_values($unplaced)];
    }

    /** @var array<string,int> "stream:day:period" -> placement index */
    private array $streamAt = [];
    /** @var array<string,int> "teacher:day:period" -> placement index */
    private array $teacherAt = [];
    /** @var array<string,int> "stream:area:day" -> lessons */
    private array $areaDay = [];
    /** @var array<string,int> "stream:day" -> lessons */
    private array $streamDay = [];
    /** @var array<string,int> "teacher:day" -> lessons */
    private array $teacherDay = [];
    /** @var array<int, array{stream:int, area:int, teacher:int, count:int, day:int, period:int}> */
    private array $placed = [];
    private array $days = [];
    private int $periodCount = 1;

    private function free(array $lesson, int $day, int $period): bool
    {
        return !isset($this->streamAt["{$lesson['stream']}:$day:$period"])
            && !isset($this->teacherAt["{$lesson['teacher']}:$day:$period"]);
    }

    private function cost(array $lesson, int $day, int $periodIndex): int
    {
        $perDayCap = (int) ceil($lesson['count'] / max(1, count($this->days)));
        $sameArea = $this->areaDay["{$lesson['stream']}:{$lesson['area']}:$day"] ?? 0;
        $cost = $sameArea >= $perDayCap ? 1000 * ($sameArea - $perDayCap + 1) : 0;
        $cost += 10 * ($this->streamDay["{$lesson['stream']}:$day"] ?? 0);
        $cost += 4 * ($this->teacherDay["{$lesson['teacher']}:$day"] ?? 0);
        return $cost + $periodIndex;
    }

    private function placeBest(array $lesson, array $cells): bool
    {
        $best = null;
        $bestCost = PHP_INT_MAX;
        foreach ($cells as $i => [$day, $period]) {
            if (!$this->free($lesson, $day, $period)) continue;
            $cost = $this->cost($lesson, $day, $i % $this->periodCount);
            if ($cost < $bestCost) {
                $bestCost = $cost;
                $best = [$day, $period];
            }
        }
        if ($best === null) return false;
        $this->put($lesson, $best[0], $best[1]);
        return true;
    }

    /**
     * The stream has free periods but the teacher is busy in all of them.
     * Try moving one of the teacher's lessons (in another stream) to a period
     * where both that stream and the teacher are free, then take its place.
     */
    private function repair(array $lesson, array $cells): bool
    {
        foreach ($cells as [$day, $period]) {
            if (isset($this->streamAt["{$lesson['stream']}:$day:$period"])) continue;
            $blocking = $this->teacherAt["{$lesson['teacher']}:$day:$period"] ?? null;
            if ($blocking === null) continue;
            $other = $this->placed[$blocking];
            $this->remove($blocking);
            foreach ($cells as [$d2, $p2]) {
                if ($d2 === $day && $p2 === $period) continue;
                if ($this->free($other, $d2, $p2) && $this->free($lesson, $day, $period)) {
                    $this->put($other, $d2, $p2);
                    $this->put($lesson, $day, $period);
                    return true;
                }
            }
            $this->put($other, $other['day'], $other['period']);
        }
        // The teacher is free in some periods, but the stream is busy in all
        // of them: move that stream lesson (another teacher's) elsewhere.
        foreach ($cells as [$day, $period]) {
            if (isset($this->teacherAt["{$lesson['teacher']}:$day:$period"])) continue;
            $blocking = $this->streamAt["{$lesson['stream']}:$day:$period"] ?? null;
            if ($blocking === null) continue;
            $other = $this->placed[$blocking];
            $this->remove($blocking);
            foreach ($cells as [$d2, $p2]) {
                if ($d2 === $day && $p2 === $period) continue;
                if ($this->free($other, $d2, $p2) && $this->free($lesson, $day, $period)) {
                    $this->put($other, $d2, $p2);
                    $this->put($lesson, $day, $period);
                    return true;
                }
            }
            $this->put($other, $other['day'], $other['period']);
        }
        return false;
    }

    private function put(array $lesson, int $day, int $period): void
    {
        $lesson['day'] = $day;
        $lesson['period'] = $period;
        $this->placed[] = $lesson;
        $index = array_key_last($this->placed);
        $this->streamAt["{$lesson['stream']}:$day:$period"] = $index;
        $this->teacherAt["{$lesson['teacher']}:$day:$period"] = $index;
        $this->bump("{$lesson['stream']}:{$lesson['area']}:$day", $this->areaDay, 1);
        $this->bump("{$lesson['stream']}:$day", $this->streamDay, 1);
        $this->bump("{$lesson['teacher']}:$day", $this->teacherDay, 1);
    }

    private function remove(int $index): void
    {
        $l = $this->placed[$index];
        unset($this->placed[$index], $this->streamAt["{$l['stream']}:{$l['day']}:{$l['period']}"], $this->teacherAt["{$l['teacher']}:{$l['day']}:{$l['period']}"]);
        $this->bump("{$l['stream']}:{$l['area']}:{$l['day']}", $this->areaDay, -1);
        $this->bump("{$l['stream']}:{$l['day']}", $this->streamDay, -1);
        $this->bump("{$l['teacher']}:{$l['day']}", $this->teacherDay, -1);
    }

    private function bump(string $key, array &$map, int $by): void
    {
        $map[$key] = ($map[$key] ?? 0) + $by;
        if ($map[$key] <= 0) unset($map[$key]);
    }
}
