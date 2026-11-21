<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use Throwable;

/**
 * Deterministic, permission-scoped sections for the proactive workspace
 * briefing. This is the "co-worker eyes" layer: it reads only what the
 * recorded operator is already entitled to see, emits counts and short labels
 * (never row detail), and never calls a model.
 *
 * PHP owns authorization and data access here; Python only turns these facts
 * into a readable briefing. A section the operator cannot access is omitted
 * entirely rather than returned as an empty or redacted placeholder.
 */
final class WorkspaceBriefingScanner
{
    private const MAX_ITEMS = 3;

    /** @var null|callable():array<string,mixed> injectable transport signals (tests) */
    private $transportSignals;

    /**
     * @param null|callable():array<string,mixed> $transportSignals
     */
    public function __construct(?callable $transportSignals = null)
    {
        $this->transportSignals = $transportSignals;
    }

    /** Advisory workflows whose pending-review count is safe to surface. */
    private const REVIEW_WORKFLOWS = [
        'transport.operations_summary',
        'attendance.exception_summary',
        'boarding.exception_summary',
        'inventory.replenishment_review',
        'catering.consumption_review',
        'maintenance.facilities_review',
        'health.welfare_review',
        'curriculum.kicd_change_interpretation',
    ];

    /**
     * @param list<string> $permissions CURRENT server-resolved permissions
     * @return array<string,mixed>
     */
    public function scan(PDO $pdo, int $userId, array $permissions, string $route): array
    {
        $sections = [
            'schedule' => $this->schedule($pdo, $permissions),
            'attention' => $this->attention($pdo, $userId, $permissions),
            'notifications' => $this->notifications($pdo, $userId),
        ];
        // The UI renders each section's `items`, so deduplicate there too —
        // repeated reminders must not appear twice in the coworker card.
        foreach ($sections as &$section) {
            if (isset($section['items']) && is_array($section['items'])) {
                $section['items'] = array_values(array_unique($section['items']));
            }
        }
        unset($section);

        $lines = [];
        foreach ($sections as $name => $section) {
            foreach ((array) ($section['lines'] ?? []) as $line) {
                $line = trim((string) $line);
                if ($line !== '') $lines[] = "{$name}: {$line}";
            }
        }
        // Repeated reminders are common; the briefing must not repeat itself.
        $lines = array_values(array_unique($lines));

        return [
            'status' => 'ok',
            'route' => mb_substr($route, 0, 120),
            'as_of' => gmdate('Y-m-d\TH:i:s\Z'),
            'date' => date('Y-m-d'),
            'sections' => $sections,
            'lines' => array_slice($lines, 0, 12),
            'pending_review_count' => (int) ($sections['attention']['pending_review_count'] ?? 0),
            'unread_notification_count' => (int) ($sections['notifications']['unread_count'] ?? 0),
        ];
    }

    /** Today's school events and exams the operator may see. */
    private function schedule(PDO $pdo, array $permissions): array
    {
        $items = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT TIME(start_at) AS starts, title, type
                   FROM school_events
                  WHERE DATE(start_at) = CURDATE()
                    AND status IN ('upcoming', 'ongoing')
                  ORDER BY start_at ASC LIMIT " . self::MAX_ITEMS
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = sprintf(
                    '%s %s (%s)',
                    (string) ($row['starts'] ?? ''),
                    mb_substr((string) ($row['title'] ?? ''), 0, 80),
                    mb_substr((string) ($row['type'] ?? ''), 0, 30)
                );
            }
        } catch (Throwable $e) {
            return ['status' => 'unavailable', 'items' => [], 'lines' => []];
        }

        $examCount = 0;
        if ($this->hasAny($permissions, ['academic_view', 'assessments_view', 'transport_manage'])) {
            try {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM vw_upcoming_exam_schedules WHERE exam_date = CURDATE()"
                );
                $examCount = max(0, (int) $stmt->fetchColumn());
            } catch (Throwable $e) {
                $examCount = 0;
            }
        }

        $lines = $items;
        if ($examCount > 0) $lines[] = "{$examCount} exam(s) scheduled today.";
        if ($lines === []) $lines[] = 'No scheduled school events or exams recorded for today.';

        return [
            'status' => 'ok',
            'items' => $items,
            'exam_count' => $examCount,
            'lines' => array_slice($lines, 0, self::MAX_ITEMS + 1),
        ];
    }

    /** Needs-attention items, each gated by the matching permission. */
    private function attention(PDO $pdo, int $userId, array $permissions): array
    {
        $items = [];
        $pendingReview = 0;

        if ($this->hasAny($permissions, ['transport_view', 'transport_manage'])) {
            try {
                if ($this->transportSignals !== null) {
                    $signals = ($this->transportSignals)();
                } else {
                    $response = (new \App\API\Modules\transport\TransportAPI())->getAiOperationsSummaryInput();
                    $signals = ($response['success'] ?? false) === true
                        ? (is_array($response['data'] ?? null) ? $response['data'] : [])
                        : [];
                }
                if ($signals !== []) {
                    foreach ([
                        'overdue_service_count' => 'vehicle(s) overdue for service',
                        'expired_insurance_count' => 'vehicle(s) with expired insurance',
                        'routes_without_vehicle_count' => 'route(s) without a vehicle',
                    ] as $key => $label) {
                        $count = max(0, (int) ($signals[$key] ?? 0));
                        if ($count > 0) $items[] = "{$count} active {$label}.";
                    }
                }
            } catch (Throwable $e) {
                // A failed optional signal must not break the whole briefing.
            }
        }

        if ($this->hasAny($permissions, ['transport_all_permissions', 'transport_manage', 'academics_approve', 'academic_view'])) {
            try {
                $review = (new DraftRepository())->pendingReviewSummary($pdo, $userId, self::REVIEW_WORKFLOWS);
                $pendingReview = max(0, (int) ($review['count'] ?? 0));
                if ($pendingReview > 0) $items[] = "{$pendingReview} advisory item(s) awaiting staff review.";
            } catch (Throwable $e) {
                $pendingReview = 0;
            }
        }

        // System administrators: the 24/7 log monitor's findings.
        if ($this->hasAny($permissions, ['system_admin', 'system_view', 'system_all_permissions'])) {
            try {
                $monitor = (new SystemLogMonitorService())->status();
                if (!$monitor['running']) {
                    $items[] = 'The system log monitor has not run in the last 2 minutes; check the ticker.';
                }
                $since = time() - 86400;
                $counts = ['critical' => 0, 'error' => 0];
                $titles = [];
                foreach ($monitor['recent'] as $finding) {
                    if (strtotime((string) $finding['at']) < $since || !isset($counts[$finding['severity']])) continue;
                    $counts[$finding['severity']] += (int) $finding['count'];
                    if ($finding['severity'] === 'critical') $titles[$finding['title']] = true;
                }
                if ($counts['critical'] > 0) {
                    $items[] = "{$counts['critical']} critical system event(s) in the last 24 h: " . implode(', ', array_slice(array_keys($titles), 0, 3)) . '.';
                }
                if ($counts['error'] > 0) {
                    $items[] = "{$counts['error']} system error(s) or security warning(s) logged in the last 24 h.";
                }
            } catch (Throwable $e) {
                // Optional signal.
            }
        }

        if ($items === []) $items[] = 'No attention items from the current authorized signals.';

        return [
            'status' => 'ok',
            'items' => array_slice($items, 0, self::MAX_ITEMS + 1),
            'pending_review_count' => $pendingReview,
            'lines' => array_slice($items, 0, self::MAX_ITEMS + 1),
        ];
    }

    /** The operator's own unread notifications and messages only. */
    private function notifications(PDO $pdo, int $userId): array
    {
        $items = [];
        $unread = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT type, title, priority
                   FROM notifications
                  WHERE user_id = ? AND read_status = 'unread'
                  ORDER BY CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, created_at DESC
                  LIMIT " . self::MAX_ITEMS
            );
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = sprintf(
                    '%s: %s',
                    mb_substr((string) ($row['type'] ?? 'notice'), 0, 30),
                    mb_substr((string) ($row['title'] ?? ''), 0, 80)
                );
            }
            $count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 'unread'");
            $count->execute([$userId]);
            $unread = max(0, (int) $count->fetchColumn());
        } catch (Throwable $e) {
            return ['status' => 'unavailable', 'items' => [], 'unread_count' => 0, 'lines' => []];
        }

        try {
            $messages = $pdo->prepare(
                "SELECT COUNT(*) FROM message_read_status WHERE recipient_id = ? AND read_at IS NULL"
            );
            $messages->execute([$userId]);
            $messageCount = max(0, (int) $messages->fetchColumn());
            if ($messageCount > 0) $items[] = "{$messageCount} unread internal message(s).";
        } catch (Throwable $e) {
            // Message reading is an optional signal.
        }

        // Duplicate reminders are common; collapse them before computing the
        // "more unread" remainder so the figure matches what we actually show.
        $items = array_values(array_unique($items));
        if ($unread > count($items)) $items[] = ($unread - count($items)) . ' more unread notification(s).';
        if ($items === []) $items[] = 'No unread notifications or messages.';

        return [
            'status' => 'ok',
            'items' => array_slice($items, 0, self::MAX_ITEMS + 1),
            'unread_count' => $unread,
            'lines' => array_slice($items, 0, self::MAX_ITEMS + 1),
        ];
    }

    /** @param list<string> $permissions */
    private function hasAny(array $permissions, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $permissions, true)) return true;
        }
        return false;
    }
}
