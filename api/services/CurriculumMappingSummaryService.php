<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;

/** Builds a bounded summary of configured CBC curriculum mapping only. */
final class CurriculumMappingSummaryService
{
    /** Reject failed/incomplete source reads instead of turning them into zero metrics. */
    public function aiPayloadFromResponse(array $response): array
    {
        $data = $response['data'] ?? null;
        if (($response['status'] ?? '') !== 'success'
            || (int) ($response['code'] ?? 500) >= 400
            || !is_array($data)
            || !isset($data['learning_areas'])
            || !is_array($data['learning_areas'])) {
            throw new DomainException('Curriculum mapping data is unavailable for this class.', 422);
        }
        return $this->aiPayload($data['learning_areas']);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,string> */
    public function aiPayload(array $rows): array
    {
        $configured = 0;
        $mapped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $configured++;
            if ((int) ($row['strand_count'] ?? 0) > 0 || (int) ($row['sub_strand_count'] ?? 0) > 0) {
                $mapped++;
            }
        }

        return [
            'report_date' => date('Y-m-d'),
            'configured_learning_area_count' => (string) $configured,
            'mapped_learning_area_count' => (string) $mapped,
            'unmapped_learning_area_count' => (string) max(0, $configured - $mapped),
            'mapping_rate' => $configured > 0 ? (string) round(($mapped / $configured) * 100, 2) : '0',
            'follow_up_intent' => 'Review configured CBC learning-area mapping completeness only. These figures do not measure schemes, lesson plans, teaching delivery, learner progress, or overdue work.',
        ];
    }
}
