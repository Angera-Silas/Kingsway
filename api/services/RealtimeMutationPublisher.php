<?php

namespace App\API\Services;

/**
 * Publishes privacy-safe cache invalidations after successful API mutations.
 * It deliberately never copies controller response data into the event.
 */
class RealtimeMutationPublisher
{
    /** @var string[] */
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string[] Endpoints with their own internal/credential semantics. */
    private const EXCLUDED_CONTROLLERS = ['auth', 'twofactor', 'realtime'];

    /**
     * POST routes that only read (controller => resources). They change no
     * school data, so announcing them made every open staff page re-fetch
     * whenever anyone opened a page, asked the assistant or sent telemetry.
     * Only list a route here when it provably writes nothing a page shows.
     *
     * @var array<string,string[]>
     */
    private const READ_ONLY_POSTS = [
        'system' => ['client-log'],
        'node' => ['view'],
        'dashboard' => ['agent-suggest', 'agent-assist', 'agent-assist-stream'],
        'reports' => ['nlq'],
        'import' => ['preview'],
        'automation' => ['exam-document-preview'],
        'communications' => ['templates-preview', 'templates-estimate-sms-cost'],
        'staff' => ['payroll-eligibility-validate'],
        // Builds a temporary download file; changes no school records.
        'download' => ['export'],
    ];

    /** @var array<string,string[]> */
    private const DOMAIN_TARGETS = [
        'academic' => ['academic', 'assessments', 'dashboard_headteacher', 'dashboard_academic'],
        'admission' => ['admissions', 'students', 'dashboard_school_admin'],
        'attendance' => ['attendance', 'dashboard_headteacher', 'dashboard_academic'],
        'boarding' => ['boarding', 'attendance', 'dashboard_boarding'],
        'catering' => ['catering', 'inventory', 'dashboard_catering'],
        'communications' => ['communications', 'notifications'],
        'finance' => ['finance', 'payments', 'dashboard_finance'],
        'inventory' => ['inventory', 'dashboard_inventory'],
        'parent-portal' => ['parent_portal'],
        'reports' => ['reports'],
        'staff' => ['staff', 'dashboard_staff'],
        'students' => ['students', 'attendance', 'dashboard_school_admin'],
        'system' => ['system', 'dashboard_system_admin'],
        'transport' => ['transport', 'dashboard_transport'],
    ];

    public static function publish(string $httpMethod, string $controller, ?string $resource, array $result): void
    {
        if (!self::shouldPublish($httpMethod, $controller, $resource, $result)) {
            return;
        }
        $httpMethod = strtoupper($httpMethod);
        $controller = strtolower(trim($controller));

        $domain = self::domainFor($controller);
        $targets = array_values(array_unique(array_merge(
            [$domain],
            self::DOMAIN_TARGETS[$domain] ?? []
        )));
        $eventName = self::eventName($resource);

        try {
            // Mirror the mutation to connected browsers over the Node SSE
            // gateway, the only realtime transport. RealtimeGatewayPublisher is
            // fire-and-forget, dormant until configured, and swallows failures:
            // a realtime outage never turns a successful school operation into
            // an HTTP failure. Payload keys are the gateway's allowlisted change
            // descriptors; never record bodies. Scope 'all' reaches staff
            // 'all'-channel holders; parents (family channels) never inherit it.
            RealtimeGatewayPublisher::publish(
                'DATA_CHANGED',
                'all',
                [
                    'domain' => $domain,
                    'action' => $eventName,
                    'targets' => array_values(array_unique($targets)),
                    'method' => $httpMethod,
                ]
            );
        } catch (\Throwable $e) {
            // Realtime is an acceleration layer. It must never roll back or
            // turn a successful school operation into an HTTP failure.
            \App\API\Services\Logger::legacyError('Realtime mutation publish failed: ' . $e->getMessage());
        }
    }

    /**
     * Whether a finished request is a real browser-initiated change that open
     * staff pages should hear about.
     */
    public static function shouldPublish(string $httpMethod, string $controller, ?string $resource, array $result): bool
    {
        $httpMethod = strtoupper($httpMethod);
        $controller = strtolower(trim($controller));
        return in_array($httpMethod, self::MUTATION_METHODS, true)
            && !in_array($controller, self::EXCLUDED_CONTROLLERS, true)
            && !self::isReadOnlyPost($controller, $resource)
            && !self::isWorkerRequest()
            && self::wasSuccessful($result);
    }

    private static function isReadOnlyPost(string $controller, ?string $resource): bool
    {
        $resource = strtolower(trim((string) $resource));
        return in_array($resource, self::READ_ONLY_POSTS[$controller] ?? [], true);
    }

    /**
     * Ticker jobs (python_platform/schedules.yaml `kind: http`) POST here every
     * few seconds whether or not they have work. When one does change data, the
     * source-table triggers make the read-model refresh announce it once the
     * replica is fresh, so announcing the POST itself is only noise. Header
     * presence is enough: a caller forging it only silences its own event.
     */
    private static function isWorkerRequest(): bool
    {
        return trim((string) ($_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '')) !== '';
    }

    private static function wasSuccessful(array $result): bool
    {
        if (array_key_exists('success', $result)) {
            return $result['success'] === true;
        }
        if (isset($result['status'])) {
            return strtolower((string) $result['status']) === 'success';
        }
        $code = isset($result['code']) ? (int) $result['code'] : 200;
        return $code >= 200 && $code < 400;
    }

    private static function domainFor(string $controller): string
    {
        $aliases = [
            'payments' => 'finance',
            'student' => 'students',
            'admissions' => 'admission',
            'communication' => 'communications',
            'users' => 'system',
        ];
        return $aliases[$controller] ?? preg_replace('/[^a-z0-9_-]/', '', $controller);
    }

    private static function eventName(?string $resource): string
    {
        $resource = strtolower(trim((string) $resource));
        $resource = preg_replace('/[^a-z0-9_-]/', '', $resource);
        return ($resource !== '' ? $resource : 'resource') . '_changed';
    }
}
