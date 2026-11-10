<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\API\Services\SharedCache;
use App\API\Modules\reports\ReportsAPI;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Governed NLQ ("talk to your data") engine.
 *
 * The provider is used ONLY to parse the staff member's natural-language
 * question into a constrained report intent. Execution is deterministic and
 * always routed through the governed ReportsAPI with the caller's own role and
 * row-level scope. The model never receives learner/staff records, never sees
 * row data, and never receives or produces SQL — it only selects a report code
 * and filters from the catalogue already authorized for that user.
 *
 * Security boundary: every provider-supplied `report_code` and filter key is
 * re-validated against the server-side accessible catalogue. Anything not
 * explicitly authorized is discarded; an empty authorized catalogue returns
 * "cannot_answer" without any provider call.
 */
class NlqQueryService
{
    public const WORKFLOW = 'system.nlq_query';

    private const MAX_QUESTION_CHARS = 500;
    private const MAX_REPORTS = 60;
    private const MAX_ROWS = 25;
    private const MAX_FILTER_ITEMS = 10;

    /** @var AgentEngineClient */
    private $bridge;

    /** @var callable|null fn(array $user): array */
    private $catalogueProvider;

    /** @var callable|null fn(string $code, array $params, array $user, string $requestId): array */
    private $executor;

    /** @var SharedCache|null Injected cache; null disables intent caching. */
    private ?SharedCache $cache;

    /** @var bool Set once we decide to build a default cache. */
    private bool $cacheResolved = false;

    public function __construct(
        ?AgentEngineClient $bridge = null,
        ?callable $catalogueProvider = null,
        ?callable $executor = null,
        ?SharedCache $cache = null
    ) {
        $this->cache = $cache;
        // Intent classification belongs to the reasoning engine. PHP keeps the
        // catalogue, the permissions and the execution; the engine only picks
        // a catalogue code, and PHP re-validates it before anything runs.
        $this->bridge = $bridge ?: new PythonAiBridge();
        $this->catalogueProvider = $catalogueProvider;
        $this->executor = $executor;
    }

    /**
     * @param array $context authenticated envelope:
     *        user_id, roles, permissions, effective_permissions, request_id, audience
     */
    public function ask(PDO $pdo, array $context, string $question): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $audience = (string) ($context['audience'] ?? 'staff');
        if ($audience !== 'staff') {
            throw new DomainException('This assistant only serves authenticated staff.', 403);
        }

        // The user-supplied portion passes through the audience policy first.
        $clean = PythonAiBridge::minimize(self::WORKFLOW, [
            'question' => $question,
            'audience' => $audience,
        ]);

        $user = $this->normalizeUser($context);
        $catalogue = $this->authorizedCatalogue($pdo, $user);
        if ($catalogue === []) {
            $this->journal($context, $question, null, 'no_reports', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'no_reports',
                'message' => 'No governed report is available to your role for this question.',
            ];
        }

        // Intent parsing is a pure function of the question and the caller's
        // authorized catalogue, so it is cached per (operator, question,
        // catalogue fingerprint). Staff ask repeated questions constantly
        // during a term, and this removes both the provider call and its
        // ~10s latency from every repeat.
        $fingerprint = substr(
            hash('sha256', json_encode([
                'q' => $this->normalizeQuestion((string) $clean['question']),
                'c' => $this->catalogueFingerprint($catalogue),
            ])),
            0,
            32
        );
        $cache = $this->intentCache();

        try {
            $cachedIntent = $cache !== null ? $cache->get($this->cacheKey($user, $fingerprint)) : null;
            $hit = is_array($cachedIntent) && $cachedIntent !== [];
            $intent = $hit
                ? $cachedIntent
                : $this->parseIntent($clean['question'], (string) $clean['audience'], $catalogue);
            if (!$hit && $cache !== null) {
                // Short-lived: the catalogue can change under a deploy.
                $cache->set($this->cacheKey($user, $fingerprint), $intent, 900);
            }
        } catch (\RuntimeException $e) {
            $this->journal($context, $question, null, 'unavailable', 0);
            return [
                'status' => 'unavailable',
                'reason' => 'provider_unavailable',
                'message' => 'The assistant is not available right now. Use the governed reports directly.',
            ];
        }

        if (($intent['cannot_answer'] ?? true) === true || trim((string) ($intent['report_code'] ?? '')) === '') {
            $this->journal($context, $question, null, 'cannot_answer', 0);
            $clarification = $this->boundedText((string) ($intent['clarification'] ?? ''), 300);
            return [
                'status' => 'cannot_answer',
                'reason' => 'unsupported',
                'message' => $clarification !== ''
                    ? $clarification
                    : 'That question is outside the governed reports available to you.',
            ];
        }

        $byCode = [];
        foreach ($catalogue as $report) {
            $byCode[(string) $report['code']] = $report;
        }
        $code = $this->resolveReportCode(
            strtoupper(trim((string) $intent['report_code'])),
            $question,
            $catalogue
        );
        if ($code === '' || !isset($byCode[$code])) {
            $this->journal($context, $question, null, 'report_not_allowed', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'report_not_allowed',
                'message' => 'That question maps to a report you are not authorized to run.',
            ];
        }

        $report = $byCode[$code];
        $validation = $this->validateFilters($intent['filters'] ?? [], $report);
        if ($validation['missing'] !== []) {
            $this->journal($context, $question, null, 'clarification', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'missing_filters',
                'message' => 'More detail is needed to run this report: ' . implode(', ', $validation['missing']) . '.',
            ];
        }

        $requestId = (string) ($context['request_id'] ?? '');
        try {
            $result = $this->execute($pdo, $code, $validation['filters'], $user, $requestId);
        } catch (RuntimeException | DomainException $e) {
            FileLogger::write('ai_generation', [
                'event' => 'nlq_execution_rejected',
                'report_code' => $code,
                'error_class' => get_class($e),
                'error_message' => $this->boundedText($e->getMessage(), 300),
                'error_code' => $e->getCode(),
                'user_id' => (int) ($user['user_id'] ?? 0),
                'role_ids' => $user['role_ids'] ?? [],
                'request_id' => $requestId,
            ], 'error');
            $this->journal($context, $question, $code, 'execution_rejected', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'execution_rejected',
                // A broken report is not an authorization problem. Saying "within
                // your authorized scope" sent a fully-authorized school
                // administrator off to ask for permissions they already had, so
                // the message now separates the two: only a genuine permission
                // denial mentions scope.
                'message' => str_contains(strtolower($e->getMessage()), 'authorized') || str_contains(strtolower($e->getMessage()), 'permission')
                    ? 'That report could not be generated within your authorized scope.'
                    : 'That report could not be produced just now. It may need a projection refresh, or a filter to be set.',
            ];
        }

        $answer = $this->buildAnswer($result, $validation['filters']);
        $this->journal($context, $question, $code, 'answered', (int) $answer['row_count']);

        return [
            'status' => 'answered',
            'audience' => 'staff',
            'intent' => [
                'report_code' => (string) $answer['report_code'],
                'filters' => $validation['filters'],
                'confidence' => max(0.0, min(1.0, (float) ($intent['confidence'] ?? 0))),
            ],
            'explanation' => $this->boundedText((string) ($intent['explanation'] ?? ''), 600),
            'answer' => $answer,
        ];
    }

    /**
     * Compute a governed aggregation (count, sum, avg, min, max) over
     * authorized report data.
     *
     * @param array $context authenticated envelope
     * @param string $question natural-language question
     * @param string $aggType one of: count, sum, avg, min, max
     * @return array<string,mixed>
     */
    public function aggregate(PDO $pdo, array $context, string $question, string $aggType = 'count'): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $aggType = strtolower(trim($aggType));
        if (!in_array($aggType, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            $aggType = 'count';
        }

        // Same authorization as ask()
        $clean = PythonAiBridge::minimize(self::WORKFLOW, [
            'question' => $question,
            'audience' => 'staff',
        ]);
        $user = $this->normalizeUser($context);
        $catalogue = $this->authorizedCatalogue($pdo, $user);
        if ($catalogue === []) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'no_reports',
                'message' => 'No governed report is available to your role for this aggregation.',
            ];
        }

        // Parse intent with aggregation hint
        $fingerprint = substr(
            hash('sha256', json_encode([
                'q' => $this->normalizeQuestion((string) $clean['question']) . " agg:$aggType",
                'c' => $this->catalogueFingerprint($catalogue),
            ])),
            0,
            32
        );
        $cache = $this->intentCache();
        try {
            $cachedIntent = $cache !== null ? $cache->get($this->cacheKey($user, $fingerprint)) : null;
            $hit = is_array($cachedIntent) && $cachedIntent !== [];
            $intent = $hit
                ? $cachedIntent
                : $this->parseIntent($clean['question'], (string) $clean['audience'], $catalogue);
            if (!$hit && $cache !== null) {
                $cache->set($this->cacheKey($user, $fingerprint), $intent, 900);
            }
        } catch (\RuntimeException $e) {
            return [
                'status' => 'unavailable',
                'reason' => 'provider_unavailable',
                'message' => 'The assistant is not available right now. Use the governed reports directly.',
            ];
        }

        if (($intent['cannot_answer'] ?? true) === true || trim((string) ($intent['report_code'] ?? '')) === '') {
            return [
                'status' => 'cannot_answer',
                'reason' => 'unsupported',
                'message' => 'That question is outside the governed reports available to you.',
            ];
        }

        $byCode = [];
        foreach ($catalogue as $report) {
            $byCode[(string) $report['code']] = $report;
        }
        $code = $this->resolveReportCode(
            strtoupper(trim((string) $intent['report_code'])),
            $question,
            $catalogue
        );
        if ($code === '' || !isset($byCode[$code])) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'report_not_allowed',
                'message' => 'That question maps to a report you are not authorized to run.',
            ];
        }

        $report = $byCode[$code];
        $validation = $this->validateFilters($intent['filters'] ?? [], $report);
        if ($validation['missing'] !== []) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'missing_filters',
                'message' => 'More detail is needed to run this report: ' . implode(', ', $validation['missing']) . '.',
            ];
        }

        $requestId = (string) ($context['request_id'] ?? '');
        try {
            $result = $this->execute($pdo, $code, $validation['filters'], $user, $requestId);
        } catch (RuntimeException | DomainException $e) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'execution_rejected',
                'message' => 'That report could not be generated within your authorized scope.',
            ];
        }

        // Build aggregation from the result rows
        $rows = $result['rows'] ?? [];
        $rowCount = count($rows);

        $aggregation = $this->computeAggregation($rows, $aggType);

        return [
            'status' => 'answered',
            'audience' => 'staff',
            'intent' => [
                'report_code' => $code,
                'filters' => $validation['filters'],
                'agg_type' => $aggType,
                'confidence' => max(0.0, min(1.0, (float) ($intent['confidence'] ?? 0))),
            ],
            'explanation' => $this->boundedText((string) ($intent['explanation'] ?? ''), 600),
            'answer' => [
                'report_code' => $code,
                'row_count' => $rowCount,
                'aggregation' => $aggregation,
                'as_of' => gmdate('c'),
                'effective_scope' => $this->effectiveScope($result['effective_scope'] ?? []),
            ],
        ];
    }

    /**
     * Generate a chart specification from governed report data.
     *
     * @param array $context authenticated envelope
     * @param string $question natural-language question
     * @param string $chartType one of: bar, line, pie, doughnut, area
     * @return array<string,mixed>
     */
    public function generateChart(PDO $pdo, array $context, string $question, string $chartType = 'bar'): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $chartType = strtolower(trim($chartType));
        if (!in_array($chartType, ['bar', 'line', 'pie', 'doughnut', 'area'], true)) {
            $chartType = 'bar';
        }

        // Same authorization flow
        $clean = PythonAiBridge::minimize(self::WORKFLOW, [
            'question' => $question,
            'audience' => 'staff',
        ]);
        $user = $this->normalizeUser($context);
        $catalogue = $this->authorizedCatalogue($pdo, $user);
        if ($catalogue === []) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'no_reports',
                'message' => 'No governed report is available to your role for chart generation.',
            ];
        }

        // Parse intent with chart hint
        $fingerprint = substr(
            hash('sha256', json_encode([
                'q' => $this->normalizeQuestion((string) $clean['question']) . " chart:$chartType",
                'c' => $this->catalogueFingerprint($catalogue),
            ])),
            0,
            32
        );
        $cache = $this->intentCache();
        try {
            $cachedIntent = $cache !== null ? $cache->get($this->cacheKey($user, $fingerprint)) : null;
            $hit = is_array($cachedIntent) && $cachedIntent !== [];
            $intent = $hit
                ? $cachedIntent
                : $this->parseIntent($clean['question'], (string) $clean['audience'], $catalogue);
            if (!$hit && $cache !== null) {
                $cache->set($this->cacheKey($user, $fingerprint), $intent, 900);
            }
        } catch (\RuntimeException $e) {
            return [
                'status' => 'unavailable',
                'reason' => 'provider_unavailable',
                'message' => 'The assistant is not available right now. Use the governed reports directly.',
            ];
        }

        if (($intent['cannot_answer'] ?? true) === true || trim((string) ($intent['report_code'] ?? '')) === '') {
            return [
                'status' => 'cannot_answer',
                'reason' => 'unsupported',
                'message' => 'That question is outside the governed reports available to you.',
            ];
        }

        $byCode = [];
        foreach ($catalogue as $report) {
            $byCode[(string) $report['code']] = $report;
        }
        $code = $this->resolveReportCode(
            strtoupper(trim((string) $intent['report_code'])),
            $question,
            $catalogue
        );
        if ($code === '' || !isset($byCode[$code])) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'report_not_allowed',
                'message' => 'That question maps to a report you are not authorized to run.',
            ];
        }

        $report = $byCode[$code];
        $validation = $this->validateFilters($intent['filters'] ?? [], $report);
        if ($validation['missing'] !== []) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'missing_filters',
                'message' => 'More detail is needed to run this report: ' . implode(', ', $validation['missing']) . '.',
            ];
        }

        $requestId = (string) ($context['request_id'] ?? '');
        try {
            $result = $this->execute($pdo, $code, $validation['filters'], $user, $requestId);
        } catch (RuntimeException | DomainException $e) {
            return [
                'status' => 'cannot_answer',
                'reason' => 'execution_rejected',
                'message' => 'That report could not be generated within your authorized scope.',
            ];
        }

        // A chart only needs a small preview. Reports may contain many rows;
        // never return every label/value through the assistant response.
        $rows = is_array($result['rows'] ?? null) ? array_values($result['rows']) : [];
        $rowCount = (int) ($result['row_count'] ?? count($rows));
        $previewRows = array_slice($rows, 0, self::MAX_ROWS);
        $chartSpec = $this->buildChartSpec($previewRows, $chartType, $validation['filters']);

        return [
            'status' => 'answered',
            'audience' => 'staff',
            'intent' => [
                'report_code' => $code,
                'filters' => $validation['filters'],
                'chart_type' => $chartType,
                'confidence' => max(0.0, min(1.0, (float) ($intent['confidence'] ?? 0))),
            ],
            'explanation' => $this->boundedText((string) ($intent['explanation'] ?? ''), 600),
            'answer' => [
                'report_code' => $code,
                'row_count' => $rowCount,
                'preview_limited' => $rowCount > count($previewRows),
                'chart' => $chartSpec,
                'as_of' => gmdate('c'),
                'effective_scope' => $this->effectiveScope($result['effective_scope'] ?? []),
            ],
        ];
    }

    /**
     * The catalogue the caller may execute. Only authorized, executable
     * reports are ever assembled into a provider prompt.
     */
    private function authorizedCatalogue(PDO $pdo, array $user): array
    {
        $provider = $this->catalogueProvider
            ?: static fn(array $u): array => (new AnalyticsReportRegistryService($pdo))->listCatalogue($u);
        $definitions = $provider($user);
        if (!is_array($definitions)) {
            return [];
        }

        $catalogue = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if (empty($definition['capabilities']['execute'])) {
                continue;
            }
            $code = strtoupper(trim((string) ($definition['code'] ?? '')));
            if ($code === '' || preg_match('/^[A-Z][A-Z0-9_]{2,99}$/', $code) !== 1) {
                continue;
            }
            $catalogue[] = [
                'code' => $code,
                'title' => $this->boundedText((string) ($definition['title'] ?? ''), 160),
                'description' => $this->boundedText((string) ($definition['description'] ?? ''), 200),
                'domain' => $this->boundedText((string) ($definition['domain'] ?? ''), 60),
                'grain' => $this->boundedText((string) ($definition['grain'] ?? ''), 60),
                'allowed_filters' => $this->stringList($definition['allowed_filters'] ?? [], 40),
                'required_filters' => $this->stringList($definition['required_filters'] ?? [], 20),
                'default_filters' => is_array($definition['default_filters'] ?? null)
                    ? $definition['default_filters']
                    : [],
            ];
            if (count($catalogue) >= self::MAX_REPORTS) {
                break;
            }
        }
        return $catalogue;
    }

    /**
     * Recover from a provider-intent code that is not in the authorized
     * catalogue. The model may name a plausible but nonexistent report; the
     * server still owns the decision and only resolves to a report the
     * operator is allowed to run.
     *
     * @param array<int,array<string,mixed>> $catalogue
     */
    private function resolveReportCode(string $code, string $question, array $catalogue): string
    {
        $available = [];
        foreach ($catalogue as $report) {
            $candidate = strtoupper(trim((string) ($report['code'] ?? '')));
            if ($candidate !== '') {
                $available[$candidate] = $report;
            }
        }
        if ($code !== '' && isset($available[$code])) {
            return $code;
        }

        $signals = [
            'ENR_CURRENT_BY_CLASS' => ['enrol', 'learner', 'student', 'headcount', 'class', 'stream', 'roll'],
            'ENR_MONTHLY_TREND' => ['trend', 'monthly', 'growth', 'over time'],
            'ADM_CONVERSION' => ['admission', 'applicant', 'application', 'conversion', 'funnel', 'intake', 'waiting list'],
            'ADM_ENROLLED_COHORT' => ['cohort', 'admission', 'enrolled'],
            'ATT_CLASS_TERM_RATE' => ['attendance', 'absent', 'present', 'register', 'roll call', 'late'],
            'CAT_FOOD_CONSUMPTION' => ['food', 'catering', 'meal', 'consumption', 'kitchen'],
            'CBC_CLASS_ASSESSMENT_SUMMARY' => ['cbc', 'assessment', 'marks', 'score', 'performance', 'exam', 'result'],
            'CBC_RUBRIC_DISTRIBUTION' => ['rubric', 'distribution', 'strand', 'outcome'],
            'DISC_INCIDENT_TREND' => ['discipline', 'incident', 'behaviour', 'behavior', 'trend'],
            'FIN_FEE_SUMMARY_CLASS' => ['fee', 'fees', 'invoice', 'billing', 'collection'],
            'FIN_ARREARS_CLASS' => ['arrears', 'balance', 'owing', 'debt', 'outstanding'],
            'COMMS_DELIVERY_SUMMARY' => ['communication', 'sms', 'message', 'delivery', 'email'],
            'INV_STOCK_LEVELS' => ['inventory', 'stock', 'item', 'supply', 'store'],
            'HR_STAFF_ATTENDANCE_RATE' => ['staff', 'teacher', 'employee', 'attendance', 'hr'],
        ];

        $text = ' ' . preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($question)) . ' ';
        $words = preg_split('/\s+/', trim(mb_strtolower($question))) ?: [];
        $scores = [];
        foreach ($available as $candidate => $report) {
            $score = 0;
            foreach ($signals[$candidate] ?? [] as $keyword) {
                if (str_contains($text, ' ' . $keyword)) {
                    $score += 3;
                }
            }
            $haystack = ' ' . mb_strtolower(
                (string) ($report['title'] ?? '') . ' ' .
                (string) ($report['description'] ?? '') . ' ' .
                (string) ($report['domain'] ?? '')
            ) . ' ';
            foreach ($words as $word) {
                if (mb_strlen($word) >= 4 && str_contains($haystack, $word)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$candidate] = $score;
            }
        }
        if ($scores === []) {
            return '';
        }
        arsort($scores);
        $best = (string) array_key_first($scores);
        $top = (int) $scores[$best];
        if ($top < 3) {
            return '';
        }
        $runnerUp = 0;
        foreach ($scores as $candidate => $score) {
            if ($candidate !== $best) {
                $runnerUp = (int) $score;
                break;
            }
        }
        // Ambiguous questions stay unsupported instead of guessing.
        if ($runnerUp === $top) {
            return '';
        }
        return $best;
    }

    /**
     * Canonical question form for cache lookup: case, spacing, and trailing
     * punctuation are not meaningful differences for staff rephrasing the same
     * question, so they must not force a second intent parse.
     */
    private function normalizeQuestion(string $question): string
    {
        $question = mb_strtolower(trim($question));
        $question = (string) preg_replace('/[^a-z0-9]+/', ' ', $question);
        return trim($question);
    }

    /** @param array<string,mixed> $user */
    private function cacheKey(array $user, string $fingerprint): string
    {
        // Keyed by operator: an intent is only ever reused for the same
        // authorized principal, never across staff.
        return 'nlq_intent:v2:' . (int) ($user['user_id'] ?? 0) . ':' . $fingerprint;
    }

    /**
     * Stable fingerprint of the authorized catalogue, so a cache entry is
     * never reused after a permission or report-definition change.
     *
     * @param array<int,array<string,mixed>> $catalogue
     */
    private function catalogueFingerprint(array $catalogue): string
    {
        $definitions = [];
        foreach ($catalogue as $report) {
            $definitions[] = [
                'code' => (string) ($report['code'] ?? ''),
                'title' => (string) ($report['title'] ?? ''),
                'description' => (string) ($report['description'] ?? ''),
                'domain' => (string) ($report['domain'] ?? ''),
                'grain' => (string) ($report['grain'] ?? ''),
                'allowed_filters' => array_values($report['allowed_filters'] ?? []),
                'required_filters' => array_values($report['required_filters'] ?? []),
                'default_filters' => $report['default_filters'] ?? [],
            ];
        }
        usort($definitions, static fn(array $left, array $right): int => strcmp($left['code'], $right['code']));
        return substr(hash('sha256', (string) json_encode($definitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    private function intentCache(): ?SharedCache
    {
        if (!$this->cacheResolved) {
            $this->cacheResolved = true;
            if ($this->cache === null) {
                try {
                    $this->cache = new SharedCache();
                } catch (\Throwable) {
                    $this->cache = null;
                }
            }
        }
        return $this->cache;
    }

    private function parseIntent(string $question, string $audience, array $catalogue): array
    {
        // First, try deterministic keyword-based routing for common queries.
        // This avoids model hallucinations on high-frequency questions.
        $deterministic = $this->resolveIntentDeterministic($question, $catalogue);
        if ($deterministic !== null) {
            $reportCode = strtoupper((string) ($deterministic['report_code'] ?? ''));
            $report = null;
            foreach ($catalogue as $candidate) {
                if (strtoupper((string) ($candidate['code'] ?? '')) === $reportCode) {
                    $report = $candidate;
                    break;
                }
            }
            $allowed = (array) ($report['allowed_filters'] ?? []);
            $filters = (array) ($deterministic['filters'] ?? []);
            $mentionsUnresolvedTerm = in_array('term_id', $allowed, true)
                && preg_match('/\bterm\b/i', $question) === 1
                && !array_key_exists('term_id', $filters);
            if (!$mentionsUnresolvedTerm) {
                return $deterministic;
            }
            // “Term 1” is an ordinal while term_id is a database identifier.
            // Never guess that the ordinal equals the identifier; let the
            // constrained intent parser resolve it against the allowed report.
        }

        $intent = $this->classifyIntent($question, $audience, $catalogue);
        if (!is_array($intent)) {
            throw new \RuntimeException('AI provider returned an unusable intent.');
        }
        $intent['cannot_answer'] = !empty($intent['cannot_answer']);
        return $intent;
    }

    /**
     * Classify the question into a report intent using the AI engine.
     *
     * The engine receives only the catalogue PHP already authorized for this
     * operator and returns one catalogue code plus a small filter map. It
     * never sees school data and never runs a governed tool, so this hop
     * cannot cycle back into the data layer.
     *
     * @param array<int,array<string,mixed>> $catalogue
     * @return array<string,mixed>|null
     */
    private function classifyIntent(string $question, string $audience, array $catalogue): ?array
    {
        try {
            $intent = $this->bridge->call('/api/agents/intent', [
                'question' => $question,
                'audience' => $audience,
                'reports' => $catalogue,
            ], 'POST', [], 20);
        } catch (\Throwable $e) {
            FileLogger::write('ai_generation', [
                'type' => 'nlq_intent_unavailable',
                'error_class' => get_class($e),
                'message' => $this->boundedText($e->getMessage(), 200),
            ], 'error');
            throw new \RuntimeException('AI provider returned an unusable intent.');
        }
        if (!is_array($intent)) {
            return null;
        }
        return $intent;
    }

    /**
     * Deterministic intent resolution for high-frequency questions.
     * Returns an intent array or null if no confident match.
     * This is a "fine-tuned" rule-based classifier covering all 14 reports.
     */
    private function resolveIntentDeterministic(string $question, array $catalogue): ?array
    {
        $q = strtolower($question);
        $byCode = [];
        foreach ($catalogue as $report) {
            $byCode[strtoupper($report['code'])] = $report;
        }

        $filters = $this->extractFilters($q, $byCode);

        // ============================================================
        // ENROLLMENT DOMAIN (4 reports)
        // ============================================================

        // ENR_CURRENT_BY_CLASS: current enrollment by class/stream
        // Keywords: enrollment, learners, students, pupils, headcount, roll, register
        // Context: current, now, this term, active, present, today
        if (preg_match(
            '/\b(enroll|enrolled|enrolment|enrollment|learner|student|pupil|headcount|roll|register)\b.*\b(current|this term|now|active|present|today|as of)\b|\b(current|this term|now|active|present|today|as of)\b.*\b(enroll|enrolled|enrolment|enrollment|learner|student|pupil|headcount|roll|register)\b|\b(how many|count|number|total)\b.*\b(enroll|enrolled|learner|student|pupil)\b/',
            $q
        )) {
            if (isset($byCode['ENR_CURRENT_BY_CLASS'])) {
                return ['report_code' => 'ENR_CURRENT_BY_CLASS', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // Boarder-specific enrollment (uses ENR_CURRENT_BY_CLASS)
        if (preg_match(
            '/\b(boarder|boarding|boarders|dorm|dormitory|hostel)\b.*\b(enroll|enrolled|enrolment|enrollment|count|how many|number)\b|\b(enroll|enrolled|enrolment|enrollment|count|how many|number)\b.*\b(boarder|boarding|boarders|dorm|dormitory|hostel)\b/',
            $q
        )) {
            if (isset($byCode['ENR_CURRENT_BY_CLASS'])) {
                $boarderFilters = $filters;
                $boarderFilters['student_type'] = ['BOARD'];
                return ['report_code' => 'ENR_CURRENT_BY_CLASS', 'filters' => $boarderFilters, 'cannot_answer' => false];
            }
        }

        // ENR_MONTHLY_TREND: enrollment trend over time
        // Keywords: trend, monthly, month, over time, history, growth, seasonality
        if (preg_match(
            '/\b(enroll|enrolment|enrollment|admission|admit)\b.*\b(trend|monthly|month|over time|history|growth|seasonality|pattern)\b|\b(trend|monthly|month|over time|history|growth|seasonality|pattern)\b.*\b(enroll|enrolment|enrollment|admission|admit)\b/',
            $q
        )) {
            if (isset($byCode['ENR_MONTHLY_TREND'])) {
                return ['report_code' => 'ENR_MONTHLY_TREND', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // ADMISSIONS DOMAIN (2 reports)
        // ============================================================

        // ADM_CONVERSION: admission funnel/conversion rate
        if (preg_match(
            '/\b(admission|admit|applied|applicant|application)\b.*\b(conversion|rate|funnel|yield|convert)\b|\b(conversion|rate|funnel|yield|convert)\b.*\b(admission|admit|applied|applicant|application)\b/',
            $q
        )) {
            if (isset($byCode['ADM_CONVERSION'])) {
                return ['report_code' => 'ADM_CONVERSION', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ADM_ENROLLED_COHORT: enrolled admissions by cohort/year/grade
        if (preg_match(
            '/\b(admission|admit|enrolled|admitted|cohort)\b.*\b(cohort|class|year|grade|batch|intake)\b|\b(cohort|class|year|grade|batch|intake)\b.*\b(admission|admit|enrolled|admitted)\b/',
            $q
        )) {
            if (isset($byCode['ADM_ENROLLED_COHORT'])) {
                return ['report_code' => 'ADM_ENROLLED_COHORT', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // ATTENDANCE DOMAIN (1 report)
        // ============================================================

        // ATT_CLASS_TERM_RATE: class attendance rate by term
        // Exclude staff/teacher attendance (handled by HR_STAFF_ATTENDANCE_RATE)
        if (preg_match(
            '/\b(attendance|present|absent|absentee)\b.*\b(class|stream|term|rate|percentage|ratio)\b|\b(class|stream|term)\b.*\b(attendance|present|absent|absentee)\b.*\b(rate|percentage|ratio)\b/',
            $q
        ) && !preg_match('/\b(staff|teacher|employee)\b/', $q)) {
            if (isset($byCode['ATT_CLASS_TERM_RATE'])) {
                return ['report_code' => 'ATT_CLASS_TERM_RATE', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // FINANCE DOMAIN (2 reports)
        // ============================================================

        // FIN_ARREARS_CLASS: fee arrears/outstanding by class
        if (preg_match(
            '/\b(arrears|outstanding|balance|overdue|unpaid|owed)\b.*\b(class|stream|grade|class)\b/',
            $q
        )) {
            if (isset($byCode['FIN_ARREARS_CLASS'])) {
                return ['report_code' => 'FIN_ARREARS_CLASS', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // FIN_FEE_SUMMARY_CLASS: fee summary by class
        if (preg_match(
            '/\b(fee|fees|payment|billing|invoice)\b.*\b(summary|total|overview|breakdown)\b.*\b(class|stream|grade)\b|\b(class|stream|grade)\b.*\b(fee|fees|payment|billing)\b.*\b(summary|total|overview|breakdown)\b/',
            $q
        )) {
            if (isset($byCode['FIN_FEE_SUMMARY_CLASS'])) {
                return ['report_code' => 'FIN_FEE_SUMMARY_CLASS', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // DISCIPLINE DOMAIN (1 report)
        // ============================================================

        // DISC_INCIDENT_TREND: discipline incidents over time
        if (preg_match(
            '/\b(discipline|incident|behaviour|behavior|misconduct|offense)\b.*\b(trend|month|monthly|over time|history|pattern)\b|\b(trend|monthly|month)\b.*\b(discipline|incident|behaviour|behavior)\b/',
            $q
        )) {
            if (isset($byCode['DISC_INCIDENT_TREND'])) {
                return ['report_code' => 'DISC_INCIDENT_TREND', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // STAFF/HR DOMAIN (1 report)
        // ============================================================

        // HR_STAFF_ATTENDANCE_RATE: staff attendance rate
        if (preg_match(
            '/\b(staff|teacher|employee)\b.*\b(attendance|present|absent)\b.*\b(rate|percentage|term)\b|\b(staff|teacher)\b.*\b(attendance rate|absenteeism)\b/',
            $q
        )) {
            if (isset($byCode['HR_STAFF_ATTENDANCE_RATE'])) {
                return ['report_code' => 'HR_STAFF_ATTENDANCE_RATE', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // INVENTORY DOMAIN (2 reports)
        // ============================================================

        // INV_STOCK_LEVELS: current stock levels
        if (preg_match(
            '/\b(stock|inventory|supply|supplies)\b.*\b(level|current|on hand|available|quantity)\b|\b(current|level|on hand)\b.*\b(stock|inventory)\b/',
            $q
        )) {
            if (isset($byCode['INV_STOCK_LEVELS'])) {
                return ['report_code' => 'INV_STOCK_LEVELS', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // CAT_FOOD_CONSUMPTION: food/meal consumption trend
        if (preg_match(
            '/\b(food|meal|nutrition|diet)\b.*\b(consumption|trend|usage|waste|served|eaten)\b|\b(consumption|waste|served)\b.*\b(food|meal|nutrition)\b/',
            $q
        )) {
            if (isset($byCode['CAT_FOOD_CONSUMPTION'])) {
                return ['report_code' => 'CAT_FOOD_CONSUMPTION', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // COMMUNICATIONS DOMAIN (1 report)
        // ============================================================

        // COMMS_DELIVERY_SUMMARY: message delivery outcomes
        if (preg_match(
            '/\b(message|sms|email|notification|communication)\b.*\b(delivery|sent|failed|bounce|delivered|received)\b|\b(delivery|sent|failed)\b.*\b(message|sms|email|notification)\b/',
            $q
        )) {
            if (isset($byCode['COMMS_DELIVERY_SUMMARY'])) {
                return ['report_code' => 'COMMS_DELIVERY_SUMMARY', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // ============================================================
        // CBC ASSESSMENT DOMAIN (2 reports)
        // ============================================================

        // CBC_CLASS_ASSESSMENT_SUMMARY: CBC assessment summary by class
        if (preg_match(
            '/\b(cbc|competency|competencies|curriculum)\b.*\b(assessment|summary|class|stream|outcome|result)\b|\b(assessment|result|outcome)\b.*\b(cbc|competency|competencies)\b.*\b(class|stream)\b/',
            $q
        )) {
            if (isset($byCode['CBC_CLASS_ASSESSMENT_SUMMARY'])) {
                return ['report_code' => 'CBC_CLASS_ASSESSMENT_SUMMARY', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        // CBC_RUBRIC_DISTRIBUTION: rubric band distribution
        if (preg_match(
            '/\b(cbc|competency|competencies|rubric)\b.*\b(rubric|distribution|band|ee|me|ae|be|exceed|meet|approach|below)\b|\b(rubric|distribution|band)\b.*\b(cbc|competency|competencies)\b/',
            $q
        )) {
            if (isset($byCode['CBC_RUBRIC_DISTRIBUTION'])) {
                return ['report_code' => 'CBC_RUBRIC_DISTRIBUTION', 'filters' => $filters, 'cannot_answer' => false];
            }
        }

        return null;
    }

    /**
     * Extract filters from natural language question.
     */
    private function extractFilters(string $q, array $byCode): array
    {
        $filters = [];

        // Year filter: "2024", "2025", "year 2024", "academic year 2024"
        if (preg_match('/\b(20\d{2})\b/', $q, $m)) {
            $year = (int)$m[1];
            if ($year >= 2020 && $year <= 2030) {
                $filters['year'] = $year;
            }
        }

        // Class/Grade: "grade 1", "grade 2", "class 3", "pp1", "pp2", "playgroup"
        if (preg_match('/\b(grade|class|pp|playgroup)\s*(\d+|1|2|3|4|5|6|7|8|9)\b/i', $q, $m)) {
            $gradeMap = ['pp1' => 'PP1', 'pp2' => 'PP2', 'playgroup' => 'Playgroup'];
            $key = strtolower($m[1] . $m[2]);
            $filters['class_id'] = $gradeMap[$key] ?? $m[2];
        }

        // Stream: "stream a", "stream b", "a stream", "b stream"
        if (preg_match('/\bstream\s*([ab])\b|([ab])\s*stream\b/i', $q, $m)) {
            $filters['stream_id'] = strtoupper($m[1] ?? $m[2]);
        }

        return array_filter($filters);
    }

    /**
     * @return array{filters:array,missing:array}
     */
    private function validateFilters($rawFilters, array $report): array
    {
        if (!is_array($rawFilters)) {
            $rawFilters = [];
        }
        $allowed = $report['allowed_filters'] ?? [];
        $filters = [];
        foreach ($rawFilters as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            if (is_array($value)) {
                $items = [];
                foreach (array_slice($value, 0, self::MAX_FILTER_ITEMS) as $item) {
                    if (!is_scalar($item)) {
                        continue 2;
                    }
                    $items[] = $this->boundedText((string) $item, 120);
                }
                if ($items !== []) {
                    $filters[$key] = $items;
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $v = $this->boundedText((string) $value, 120);
            if ($key === 'year' && !preg_match('/^\d{4}$/', $v)) {
                continue;
            }
            $semantic = ['current', 'this year', 'this term', 'latest', 'now', 'active'];
            if (in_array(strtolower($v), $semantic, true)) {
                continue;
            }
            $filters[$key] = $v;
        }

        $missing = [];
        foreach (($report['required_filters'] ?? []) as $required) {
            $required = (string) $required;
            if (!array_key_exists($required, $filters)) {
                $missing[] = $required;
            }
        }
        return ['filters' => $filters, 'missing' => $missing];
    }

    private function execute(PDO $pdo, string $code, array $filters, array $user, string $requestId): array
    {
        $executor = $this->executor
            ?: static fn(string $c, array $params, array $u, string $rid): array => (new ReportsAPI())->executeGoverned($c, $params, $u, $rid);
        return $executor($code, $filters, $user, $requestId);
    }

    private function buildAnswer(array $result, array $filters): array
    {
        $rows = is_array($result['rows'] ?? null) ? array_values($result['rows']) : [];
        $summary = $result['summary'] ?? [];
        return [
            'report_code' => (string) ($result['report']['code'] ?? ''),
            'report_title' => $this->boundedText((string) ($result['report']['title'] ?? ''), 200),
            'decision_purpose' => $this->boundedText((string) ($result['report']['decision_purpose'] ?? ''), 300),
            // The grain states what one row IS (a class, a term, a cohort).
            // The engine needs it to decide whether a row may leave PHP.
            'grain' => $this->boundedText((string) ($result['report']['grain'] ?? ''), 60),
            'as_of' => (string) ($result['as_of'] ?? ''),
            'freshness_minutes' => (int) ($result['report']['data_currency']['freshness_minutes'] ?? 0),
            'filters' => is_array($result['filters'] ?? null) ? $result['filters'] : $filters,
            'row_count' => (int) ($result['row_count'] ?? count($rows)),
            'summary' => $this->boundSummary($summary),
            'columns' => array_slice(is_array($result['columns'] ?? null) ? $result['columns'] : [], 0, 20),
            'rows' => array_slice($rows, 0, self::MAX_ROWS),
            'preview_limited' => count($rows) > self::MAX_ROWS,
            'warnings' => array_slice($this->stringList($result['warnings'] ?? [], 10), 0, 10),
            'run_id' => isset($result['run']['id']) ? (int) $result['run']['id'] : null,
            // Preserve the server-resolved scope as provenance. The report
            // executor is authoritative; requested filters alone do not tell
            // the caller which rows were actually in scope.
            'effective_scope' => $this->effectiveScope($result['effective_scope'] ?? []),
        ];
    }

    /** Keep only the stable, non-sensitive identifiers in the scope contract. */
    private function effectiveScope($scope): array
    {
        if (!is_array($scope)) {
            return [];
        }
        $allowed = [
            'type',
            'academic_year_id',
            'academic_year_term_id',
            'class_id',
            'stream_id',
            'learning_area_id',
            'user_id',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $scope)) {
                continue;
            }
            $value = $scope[$key];
            if ($key === 'type' && is_string($value)) {
                $value = mb_substr(trim($value), 0, 40);
            } elseif (is_numeric($value) && (int) $value > 0) {
                $value = (int) $value;
            } else {
                continue;
            }
            $clean[$key] = $value;
        }
        return $clean;
    }

    private function boundSummary($summary): array
    {
        if (!is_array($summary)) {
            return [];
        }
        $out = [];
        $count = 0;
        foreach ($summary as $key => $value) {
            if ($count >= 40) {
                break;
            }
            $key = $this->boundedText((string) $key, 80);
            if ($key === '') {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? $this->boundedText($value, 300) : $value;
                $count++;
                continue;
            }
            if (is_array($value) && count($value) <= 40) {
                $inner = [];
                foreach ($value as $innerKey => $innerValue) {
                    if (!is_scalar($innerValue) && $innerValue !== null) {
                        continue;
                    }
                    $inner[$this->boundedText((string) $innerKey, 80)] = is_string($innerValue)
                        ? $this->boundedText($innerValue, 300)
                        : $innerValue;
                }
                $out[$key] = $inner;
                $count++;
            }
        }
        return $out;
    }

    private function normalizeUser(array $context): array
    {
        $permissions = array_values(array_map('strval', (array) ($context['effective_permissions'] ?? $context['permissions'] ?? [])));
        return [
            'user_id' => (int) ($context['user_id'] ?? 0),
            'roles' => (array) ($context['roles'] ?? []),
            'role_ids' => array_values(array_map('intval', (array) ($context['role_ids'] ?? []))),
            'role_names' => array_values(array_map('strval', (array) ($context['role_names'] ?? []))),
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
        ];
    }

    private function stringList($value, int $max): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach (array_slice($value, 0, $max) as $item) {
            if (is_scalar($item)) {
                $out[] = $this->boundedText((string) $item, 120);
            }
        }
        return $out;
    }

    private function boundedText(string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        return mb_substr(trim($value), 0, $max);
    }

    private function journal(array $context, string $question, ?string $reportCode, string $outcome, int $rowCount): void
    {
        try {
            FileLogger::write('ai_generation', [
                'type' => 'nlq_query',
                'operator_id' => (int) ($context['user_id'] ?? 0),
                'question_hash' => hash('sha256', $question),
                'audience' => 'staff',
                'report_code' => $reportCode,
                'outcome' => $outcome,
                'row_count' => $rowCount,
            ]);
        } catch (\Throwable $e) {
            // Journaling must never break an answer.
        }
    }

    /**
     * Compute an aggregation from report rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param string $aggType one of: count, sum, avg, min, max
     * @return array<string,mixed>
     */
    private function computeAggregation(array $rows, string $aggType): array
    {
        $values = [];
        if ($rows !== []) {
            // Try to find a numeric column to aggregate
            $firstRow = $rows[0];
            foreach ($firstRow as $key => $value) {
                if (is_numeric($value)) {
                    foreach ($rows as $row) {
                        if (isset($row[$key]) && is_numeric($row[$key])) {
                            $values[] = (float) $row[$key];
                        }
                    }
                    break;
                }
            }
            // If no numeric column found, count rows
            if ($values === []) {
                $values = array_fill(0, count($rows), 1.0);
            }
        }

        $result = [];
        switch ($aggType) {
            case 'count':
                $result['type'] = 'count';
                $result['value'] = (int) count($rows);
                break;
            case 'sum':
                $result['type'] = 'sum';
                $result['value'] = array_sum($values);
                break;
            case 'avg':
                $result['type'] = 'avg';
                $result['value'] = $values !== [] ? array_sum($values) / count($values) : 0;
                break;
            case 'min':
                $result['type'] = 'min';
                $result['value'] = $values !== [] ? min($values) : 0;
                break;
            case 'max':
                $result['type'] = 'max';
                $result['value'] = $values !== [] ? max($values) : 0;
                break;
            default:
                $result['type'] = 'count';
                $result['value'] = (int) count($rows);
        }
        $result['row_count'] = (int) count($rows);
        return $result;
    }

    /**
     * Build a chart specification from report rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param string $chartType one of: bar, line, pie, doughnut, area
     * @param array $filters applied filters
     * @return array<string,mixed>
     */
    private function buildChartSpec(array $rows, string $chartType, array $filters): array
    {
        if ($rows === []) {
            return [
                'type' => $chartType,
                'labels' => [],
                'datasets' => [],
                'title' => 'No data available for chart',
            ];
        }

        // Find label column (first non-numeric) and value column (first numeric)
        $labelKey = null;
        $valueKey = null;
        $firstRow = $rows[0];
        foreach ($firstRow as $key => $value) {
            if (is_numeric($value)) {
                $valueKey = $key;
            } elseif ($labelKey === null && is_string($value)) {
                $labelKey = $key;
            }
        }

        // Fallback: use first column as label, second as value
        $keys = array_keys($firstRow);
        if ($labelKey === null) $labelKey = $keys[0] ?? 'label';
        if ($valueKey === null) $valueKey = $keys[1] ?? $keys[0] ?? 'value';

        $labels = [];
        $data = [];
        foreach ($rows as $row) {
            $labels[] = $this->boundedText((string) ($row[$labelKey] ?? ''), 120);
            $numericValue = $row[$valueKey] ?? 0;
            $number = is_numeric($numericValue) ? (float) $numericValue : 0.0;
            $data[] = is_finite($number) ? $number : null;
        }

        $dataset = [
            'label' => ucfirst($valueKey),
            'data' => $data,
        ];

        // Add colors for visual appeal
        $colors = [
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
        ];
        if ($chartType === 'pie' || $chartType === 'doughnut') {
            $dataset['backgroundColor'] = array_slice($colors, 0, count($labels));
        } else {
            $dataset['backgroundColor'] = $colors[0];
            $dataset['borderColor'] = str_replace('0.8', '1', $colors[0]);
        }

        $title = 'Chart';
        if ($chartType === 'pie' || $chartType === 'doughnut') {
            $title = 'Distribution';
        } elseif ($chartType === 'line' || $chartType === 'area') {
            $title = 'Trend';
        } else {
            $title = 'Comparison';
        }

        return [
            'type' => $chartType,
            'labels' => $labels,
            'datasets' => [$dataset],
            'title' => $title,
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['display' => $chartType !== 'pie' && $chartType !== 'doughnut'],
                    'title' => ['display' => true, 'text' => $title],
                ],
                'scales' => ($chartType === 'pie' || $chartType === 'doughnut') ? [] : [
                    'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => ucfirst($valueKey)]],
                    'x' => ['title' => ['display' => true, 'text' => ucfirst($labelKey)]],
                ],
            ],
        ];
    }
}
