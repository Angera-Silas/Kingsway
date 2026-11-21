<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\website\WebsiteManager;
use App\Database\Database;
use PDO;

/**
 * Public school facts for the website assistant, read from public sources only.
 *
 * Every fact here is something the school publishes or would print on a
 * brochure: the approved fee structure, the CBC curriculum offered, whether
 * school is open today and when it closes or reopens, upcoming events,
 * published news and open admission windows. Nothing is read about a person:
 * no learner, guardian, staff, balance or result row can enter this corpus.
 *
 * Events, news and admission windows come from the same WebsiteManager methods
 * the public website already renders, so the assistant never says more than
 * the site does. Fees use only ACTIVE (approved) schedules; drafts and
 * pending-review schedules are excluded.
 */
final class PublicSchoolFactsService
{
    private ?PDO $pdo;
    private ?AcademicPeriodService $periods;

    public function __construct(?PDO $pdo = null, ?AcademicPeriodService $periods = null)
    {
        $this->pdo = $pdo;
        $this->periods = $periods;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getInstance()->getConnection();
    }

    private function periods(): AcademicPeriodService
    {
        return $this->periods ??= new AcademicPeriodService($this->db());
    }

    /**
     * Corpus entries ({id, title, content}) for the public assistant. Each
     * section is independent: one failing source never empties the others.
     *
     * @return list<array{id:string,title:string,content:string}>
     */
    public function corpus(WebsiteManager $manager): array
    {
        $out = [];
        foreach ([
            fn () => $this->schoolStatus(),
            fn () => $this->feeStructure(),
            fn () => $this->curriculum(),
            fn () => $this->upcomingEvents($manager),
            fn () => $this->news($manager),
            fn () => $this->openAdmissions($manager),
        ] as $section) {
            try {
                foreach ($section() as $item) {
                    if (trim($item['content']) !== '') {
                        $out[] = [
                            'id' => mb_substr($item['id'], 0, 100),
                            'title' => mb_substr($item['title'], 0, 200),
                            'content' => mb_substr($item['content'], 0, 4000),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Logger::legacyError('[PublicSchoolFactsService] section unavailable: ' . $e->getMessage());
            }
        }
        return $out;
    }

    /** Is school open today, when does it close, when does it reopen. */
    public function schoolStatus(): array
    {
        $snapshot = $this->periods()->snapshot();
        $today = (string) $snapshot['today'];
        $current = $snapshot['current_period'] ?? null;
        $next = $snapshot['next_period'] ?? null;
        $lines = ['Today is ' . self::day($today) . '.'];
        if (!$current) {
            $lines[] = 'The school calendar for this date has not been published yet. Please contact the school office for opening dates.';
        } elseif ($current['kind'] === AcademicPeriodService::KIND_HOLIDAY) {
            $lines[] = "The school is closed for the {$current['name']} (" . self::day($current['start_date']) . ' to ' . self::day($current['end_date']) . ').';
            if ($next) {
                $lines[] = "Schools reopen for {$next['label']} on " . self::day($next['start_date']) . '.';
            }
        } elseif ($current['kind'] === AcademicPeriodService::KIND_HALF_TERM) {
            $lines[] = "Learners are on {$current['name']} break until " . self::day($current['end_date'])
                . '. Classes resume on ' . self::day(date('Y-m-d', strtotime($current['end_date'] . ' +1 day'))) . '.';
        } else {
            $lines[] = "The school is open: {$current['label']} runs from " . self::day($current['start_date'])
                . ' and closes on ' . self::day($current['end_date']) . '.';
            if (!empty($current['half_term_start']) && !empty($current['half_term_end']) && $current['half_term_end'] >= $today) {
                $lines[] = 'Half term break: ' . self::day((string) $current['half_term_start']) . ' to ' . self::day((string) $current['half_term_end']) . '.';
            }
            if ($next && $next['kind'] === AcademicPeriodService::KIND_HOLIDAY) {
                $reopen = $this->periods()->termInSessionOrNext(date('Y-m-d', strtotime($next['end_date'] . ' +1 day')));
                $lines[] = "The {$next['name']} follows" . ($reopen
                    ? '; schools reopen on ' . self::day((string) $reopen['opening_date']) . '.'
                    : '.');
            }
        }
        return [[
            'id' => 'public_school_status',
            'title' => 'Is the school open? Opening and closing dates',
            'content' => implode(' ', $lines),
        ]];
    }

    /** Approved fee structure for the year being planned, per grade, learner type and term. */
    public function feeStructure(): array
    {
        [$yearId] = $this->periods()->planningTerm();
        if ($yearId <= 0) {
            return [];
        }
        // Single-table reads (Hard Rule 1), joined here: the data is small.
        $schedules = $this->rows(
            'SELECT academic_year_class_id, student_type_id, academic_year_term_id, fee_catalog_id, amount
               FROM ' . ReadReplicaService::qualifiedRef('academic_year_fee_schedules') . "
              WHERE academic_year_id = ? AND status = 'active'",
            [$yearId]
        );
        $yearClasses = $this->map('SELECT id, class_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_classes') . ' WHERE academic_year_id = ?', [$yearId], 'class_id');
        $classes = $this->map('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('classes'), [], 'name');
        $types = $this->map('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('student_types'), [], 'name');
        $yearTerms = $this->map('SELECT id, term_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' WHERE academic_year_id = ?', [$yearId], 'term_id');
        $termNames = $this->map('SELECT id, name FROM terms', [], 'name');
        $catalog = $this->map('SELECT id, name FROM fee_catalog', [], 'name');

        $grouped = [];
        $order = [];
        foreach ($schedules as $row) {
            $classId = (int) ($yearClasses[(int) $row['academic_year_class_id']] ?? 0);
            $class = (string) ($classes[$classId] ?? '');
            if ($class === '') {
                continue;
            }
            $order[$class] = $classId;
            $type = trim((string) ($types[(int) $row['student_type_id']] ?? '')) ?: 'All learners';
            $termNo = (int) ($yearTerms[(int) $row['academic_year_term_id']] ?? 0);
            $term = $termNo > 0 ? (string) ($termNames[$termNo] ?? "Term {$termNo}") : 'Per year';
            $item = trim((string) ($catalog[(int) $row['fee_catalog_id']] ?? '')) ?: 'School fees';
            $grouped[$class][$type][$term][$item] = ($grouped[$class][$type][$term][$item] ?? 0.0) + (float) $row['amount'];
        }
        uksort($grouped, static fn ($a, $b) => $order[$a] <=> $order[$b]);
        foreach ($grouped as &$typesOfClass) {
            ksort($typesOfClass);
            foreach ($typesOfClass as &$terms) {
                ksort($terms);
                foreach ($terms as &$items) {
                    $items = array_map(null, array_keys($items), array_values($items));
                }
                unset($items);
            }
            unset($terms);
        }
        unset($typesOfClass);
        $year = $this->yearName($yearId);
        $out = [];
        foreach ($grouped as $class => $types) {
            $lines = [];
            foreach ($types as $type => $terms) {
                $parts = [];
                foreach ($terms as $term => $items) {
                    $total = array_sum(array_column($items, 1));
                    // A zero schedule means "not offered" (e.g. no pre-primary
                    // boarding); publishing "KES 0" would read as free.
                    if ($total <= 0) {
                        continue;
                    }
                    $detail = implode(', ', array_map(static fn ($i) => $i[0] . ' ' . self::kes($i[1]), $items));
                    $parts[] = "{$term}: " . self::kes($total) . " ({$detail})";
                }
                if ($parts !== []) {
                    $lines[] = "{$type} — " . implode('; ', $parts) . '.';
                }
            }
            if ($lines === []) {
                continue;
            }
            $out[] = [
                'id' => 'public_fees_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($class)),
                'title' => "Fee structure {$year}: {$class}",
                'content' => "Approved school fees for {$class} in {$year}. " . implode(' ', $lines)
                    . ' Fees are per learner. Contact the admissions or accounts office for payment options.',
            ];
        }
        return $out;
    }

    /** The CBC levels and learning areas offered. */
    public function curriculum(): array
    {
        $yearId = (int) ($this->db()->query(
            'SELECT id FROM ' . ReadReplicaService::qualifiedRef('academic_years') . ' ORDER BY is_current DESC, start_date DESC LIMIT 1'
        )->fetchColumn() ?: 0);
        $byClass = [];
        if ($yearId > 0) {
            // Single-table reads (Hard Rule 1), joined here.
            $yearClasses = $this->map('SELECT id, class_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_classes') . ' WHERE academic_year_id = ?', [$yearId], 'class_id');
            $classes = $this->map('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('classes'), [], 'name');
            $areas = $this->map('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('learning_areas') . " WHERE status = 'active'", [], 'name');
            $ids = array_keys($yearClasses);
            $links = $ids === [] ? [] : $this->rows(
                'SELECT DISTINCT academic_year_class_id, learning_area_id FROM '
                . ReadReplicaService::qualifiedRef('academic_year_class_learning_areas')
                . ' WHERE academic_year_class_id IN (' . implode(',', array_map('intval', $ids)) . ')',
                []
            );
            $order = [];
            foreach ($links as $link) {
                $classId = (int) ($yearClasses[(int) $link['academic_year_class_id']] ?? 0);
                $class = (string) ($classes[$classId] ?? '');
                $area = (string) ($areas[(int) $link['learning_area_id']] ?? '');
                if ($class === '' || $area === '') {
                    continue;
                }
                $order[$class] = $classId;
                $byClass[$class][] = $area;
            }
            uksort($byClass, static fn ($a, $b) => $order[$a] <=> $order[$b]);
            foreach ($byClass as &$list) {
                $list = array_values(array_unique($list));
                sort($list);
            }
            unset($list);
        }
        $out = [[
            'id' => 'public_curriculum_overview',
            'title' => 'Curriculum offered (CBC)',
            'content' => 'Kingsway Preparatory School follows Kenya\'s Competency-Based Curriculum (CBC/CBE) '
                . 'from Playgroup to Grade 9: Pre-Primary (Playgroup, PP1, PP2), Lower Primary (Grades 1-3), '
                . 'Upper Primary (Grades 4-6) and Junior School (Grades 7-9). Day and boarding places are offered. '
                . 'As a Seventh-day Adventist school, spiritual development is part of every learner\'s programme.',
        ]];
        if ($byClass !== []) {
            $lines = [];
            foreach ($byClass as $class => $areas) {
                $lines[] = "{$class}: " . implode(', ', array_values(array_unique($areas))) . '.';
            }
            $out[] = [
                'id' => 'public_curriculum_learning_areas',
                'title' => 'Learning areas offered per grade',
                'content' => implode("\n", $lines),
            ];
        }
        return $out;
    }

    public function upcomingEvents(WebsiteManager $manager): array
    {
        $items = array_slice(self::items($manager->getEvents(null, ['upcoming' => '1'])), 0, 12);
        if ($items === []) {
            return [];
        }
        $lines = [];
        foreach ($items as $event) {
            $start = substr((string) ($event['start_at'] ?? ''), 0, 10);
            $end = substr((string) ($event['end_at'] ?? ''), 0, 10);
            $when = $start !== '' ? self::day($start) : '';
            if ($end !== '' && $end !== $start) {
                $when .= ' to ' . self::day($end);
            }
            $lines[] = '- ' . trim((string) ($event['title'] ?? 'Event')) . ($when !== '' ? " ({$when})" : '')
                . (!empty($event['location']) ? ', ' . $event['location'] : '');
        }
        return [[
            'id' => 'public_upcoming_events',
            'title' => 'Upcoming school events',
            'content' => "Upcoming events:\n" . implode("\n", $lines),
        ]];
    }

    public function news(WebsiteManager $manager): array
    {
        $out = [];
        foreach (array_slice(self::items($manager->getNews(null, ['status' => 'published', 'limit' => 8])), 0, 8) as $article) {
            $summary = trim(strip_tags((string) ($article['excerpt'] ?? ''))) ?: mb_substr(trim(strip_tags((string) ($article['content'] ?? ''))), 0, 400);
            $out[] = [
                'id' => 'public_news_' . (int) ($article['id'] ?? 0),
                'title' => 'School news: ' . trim((string) ($article['title'] ?? '')),
                'content' => trim((string) ($article['title'] ?? '')) . ' (' . self::day(substr((string) ($article['created_at'] ?? ''), 0, 10)) . '). ' . $summary,
            ];
        }
        return $out;
    }

    public function openAdmissions(WebsiteManager $manager): array
    {
        $windows = self::items($manager->getOpenTerms());
        if ($windows === []) {
            return [[
                'id' => 'public_admissions_open',
                'title' => 'Admissions: open intakes',
                'content' => 'No admission window is open online right now. Families can still contact the admissions office or submit an enquiry on the website.',
            ]];
        }
        $lines = [];
        foreach ($windows as $w) {
            $close = substr((string) ($w['application_close_at'] ?? ''), 0, 10);
            $lines[] = '- ' . trim(($w['admission_window_label'] ?? '') ?: (($w['name'] ?? 'Term') . ' ' . ($w['year_name'] ?? '')))
                . (!empty($w['start_date']) ? ', term opens ' . self::day((string) $w['start_date']) : '')
                . ($close !== '' ? ', applications close ' . self::day($close) : '')
                . (!empty($w['eligible_grades']) ? ', grades: ' . (is_string($w['eligible_grades']) ? $w['eligible_grades'] : implode(', ', (array) $w['eligible_grades'])) : '');
        }
        return [[
            'id' => 'public_admissions_open',
            'title' => 'Admissions: open intakes',
            'content' => "Admissions are open. Apply online on the Admissions page.\n" . implode("\n", $lines)
                . "\nGrades 4 to 9 applicants attend an interview.",
        ]];
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** id => one column, from a single-table read. */
    private function map(string $sql, array $params, string $column): array
    {
        $out = [];
        foreach ($this->rows($sql, $params) as $row) {
            $out[(int) $row['id']] = $row[$column];
        }
        return $out;
    }

    private function yearName(int $yearId): string
    {
        $stmt = $this->db()->prepare('SELECT year_name FROM ' . ReadReplicaService::qualifiedRef('academic_years') . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$yearId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    /** Items from a WebsiteManager/ApiResponse envelope. */
    private static function items($response): array
    {
        $data = is_array($response) ? ($response['data'] ?? $response) : [];
        $items = is_array($data) ? ($data['items'] ?? []) : [];
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private static function day(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('l j F Y', $ts) : $date;
    }

    private static function kes(float $amount): string
    {
        return 'KES ' . number_format($amount, 0);
    }
}
