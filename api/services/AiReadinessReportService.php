<?php

declare(strict_types=1);

namespace App\API\Services;

/** Evidence-oriented readiness view; never labels partial work production-ready. */
final class AiReadinessReportService
{
    public function __construct(private ?AiCapabilityMatrix $matrix = null) {}

    public function report(): array
    {
        $rows = ($this->matrix ?: new AiCapabilityMatrix())->all();
        $workflowById = [];
        foreach (AiWorkflowRegistry::all() as $workflow) {
            $workflowById[(string) ($workflow['id'] ?? '')] = $workflow;
        }
        $counts = ['total' => count($rows), 'implemented' => 0, 'partial' => 0, 'planned' => 0];
        $missing = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'planned');
            if (isset($counts[$status])) $counts[$status]++;
            if ($status !== 'implemented') {
                $workflows = [];
                foreach ((array) ($row['workflow_ids'] ?? []) as $workflowId) {
                    $workflow = $workflowById[(string) $workflowId] ?? null;
                    $workflows[] = [
                        'id' => (string) $workflowId,
                        'status' => (string) ($workflow['status'] ?? 'unregistered'),
                    ];
                }
                $missing[] = [
                    'id' => (string) $row['id'],
                    'domain' => (string) $row['domain'],
                    'status' => $status,
                    'workflow_statuses' => $workflows,
                    'unimplemented_workflows' => array_values(array_map(
                        static fn(array $workflow): string => $workflow['id'],
                        array_filter($workflows, static fn(array $workflow): bool => $workflow['status'] !== 'implemented')
                    )),
                    'evidence_status' => 'required_not_inferred_from_registry',
                    'next' => $this->nextStep($row, $workflows),
                ];
            }
        }
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'readiness_status' => ($counts['partial'] === 0 && $counts['planned'] === 0) ? 'candidate_for_release_review' : 'not_production_ready',
            'counts' => $counts,
            'implemented_scope' => array_values(array_map(static fn(array $row): string => (string) $row['id'], array_filter($rows, static fn(array $row): bool => ($row['status'] ?? '') === 'implemented'))),
            'missing_scope' => $missing,
            'release_gates' => [
                'real_provider_execution' => 'must_be_proven_per_adapter',
                'authorization_and_row_scope' => 'must_pass_allowed_and_denied_tests',
                'queue_retry_and_failure' => 'must_pass_worker_and_failure_tests',
                'draft_approval_audit' => 'must_have_persistence_and_audit_evidence',
                'ui_acceptance' => 'must_be_verified_per_role_and_workspace',
                'production_provider_and_host_test' => 'must_be_run_in_target_environment',
            ],
        ];
    }

    private function nextStep(array $row, array $workflows): string
    {
        if (($row['status'] ?? '') === 'planned') return 'Implement deterministic adapter, authorization, queue path, UI entry point, provider execution and tests.';
        if ($workflows === []) return 'Register the capability workflows, then verify authorization, data scope, provider/queue lifecycle, audit evidence and UI acceptance.';
        foreach ($workflows as $workflow) {
            if ($workflow['status'] !== 'implemented') {
                return 'Complete the listed workflow contracts, then verify capability-level authorization, provider/queue lifecycle, audit evidence and UI acceptance.';
            }
        }
        return 'Complete real adapter integration, role/row-scope denial tests, provider execution, persistence, audit evidence and UI acceptance.';
    }
}
