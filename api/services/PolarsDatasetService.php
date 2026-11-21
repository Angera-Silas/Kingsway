<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use Throwable;

/**
 * Permission-scoped dataset releases for the governed Polars tool.
 *
 * The AI engine never opens a database connection. Instead it asks PHP for one
 * of these NAMED datasets; PHP re-resolves the operator's current permissions,
 * runs a fixed, allowlisted aggregate query against the KingsWayReads read
 * replica, and returns bounded aggregate rows. Those rows are what Polars may
 * analyse - there is no path for a prompt to supply its own rows or SQL.
 *
 * Hard rules honoured here:
 *   - SQL lives only in api/services/* (this file).
 *   - Every cross-table/aggregate read uses ReadReplicaService::qualifiedRef()
 *     so it resolves to the managed KingsWayReads projection.
 *   - Only aggregate grains leave the service: no learner, staff or guardian
 *     row is ever released, so a child's record cannot reach the model.
 */
final class PolarsDatasetService
{
    /** Fixed, allowlisted datasets. The model may pick a name, never a query. */
    private const DATASETS = [
        'attendance_by_day' => [
            'permission' => 'attendance_view',
            'all_permission' => 'attendance_all_permissions',
            'projection' => 'student_attendance_summary',
            'sql' => 'SELECT date, status, COUNT(*) AS count FROM {ref} GROUP BY date, status ORDER BY date DESC LIMIT 200',
            'description' => 'Daily attendance status counts (present, absent, late) for the current scope.',
        ],
        'attendance_by_class' => [
            'permission' => 'attendance_view',
            'all_permission' => 'attendance_all_permissions',
            'projection' => 'student_attendance_summary',
            'sql' => 'SELECT class_name, status, COUNT(*) AS count FROM {ref} GROUP BY class_name, status ORDER BY class_name LIMIT 200',
            'description' => 'Attendance status counts per class for the current scope.',
        ],
        'fee_balance_by_status' => [
            'permission' => 'finance_fees_view',
            'all_permission' => 'finance_all_permissions',
            'projection' => 'student_fee_balances',
            'sql' => 'SELECT payment_status, COUNT(*) AS learners, SUM(balance) AS total_balance, AVG(days_overdue) AS avg_days_overdue FROM {ref} GROUP BY payment_status ORDER BY payment_status LIMIT 50',
            'description' => 'Fee balance totals and overdue days by payment status (aggregates only).',
        ],
        'fleet_by_status' => [
            'permission' => 'transport_view',
            'all_permission' => 'transport_all_permissions',
            'projection' => 'vehicles',
            'sql' => 'SELECT status, COUNT(*) AS vehicles, SUM(capacity) AS total_capacity, '
                . 'SUM(CASE WHEN service_due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_service, '
                . 'SUM(CASE WHEN insurance_expiry < CURDATE() THEN 1 ELSE 0 END) AS expired_insurance '
                . 'FROM {ref} GROUP BY status ORDER BY status LIMIT 50',
            'description' => 'Transport fleet counts, capacity and maintenance signals by status.',
        ],
        'classes_by_year' => [
            'permission' => 'academic_view',
            'all_permission' => 'academics_all_permissions',
            'projection' => 'academic_year_classes',
            'sql' => 'SELECT academic_year_id, COUNT(*) AS classes FROM {ref} GROUP BY academic_year_id ORDER BY academic_year_id DESC LIMIT 50',
            'description' => 'Number of classes per academic year.',
        ],
    ];

    /** @var PDO|null */
    private $connection;

    /** @var callable(string):string */
    private $refResolver;

    /**
     * @param PDO|null $connection injected for tests; defaults to the app DB
     * @param callable(string):string|null $refResolver projection -> qualified ref
     */
    public function __construct(?PDO $connection = null, ?callable $refResolver = null)
    {
        $this->connection = $connection;
        $this->refResolver = $refResolver ?? static fn (string $projection): string =>
            ReadReplicaService::qualifiedRef($projection);
    }

    private function pdo(): PDO
    {
        return $this->connection ?: Database::getInstance()->getConnection();
    }

    /**
     * Datasets this operator may use, with their descriptions. Names only -
     * no SQL or projection detail ever leaves PHP.
     *
     * @param list<string> $permissions
     * @return list<array{name:string,description:string}>
     */
    public function datasets(array $permissions): array
    {
        $out = [];
        foreach (self::DATASETS as $name => $definition) {
            if ($this->allowed($permissions, $definition)) {
                $out[] = ['name' => $name, 'description' => $definition['description']];
            }
        }
        return $out;
    }

    /**
     * Release bounded aggregate rows for one named dataset.
     *
     * @param list<string> $permissions
     * @return array<string,mixed>
     */
    public function rows(string $dataset, array $permissions): array
    {
        $dataset = strtolower(trim($dataset));
        if (!isset(self::DATASETS[$dataset])) {
            return [
                'status' => 'unknown_dataset',
                'message' => 'choose a dataset from those authorized for you',
                'available' => array_column($this->datasets($permissions), 'name'),
            ];
        }
        $definition = self::DATASETS[$dataset];
        if (!$this->allowed($permissions, $definition)) {
            return [
                'status' => 'not_authorized',
                'message' => 'this workspace does not grant access to that dataset',
                'available' => array_column($this->datasets($permissions), 'name'),
            ];
        }

        try {
            $ref = ($this->refResolver)($definition['projection']);
            $sql = str_replace('{ref}', $ref, $definition['sql']);
            $stmt = $this->pdo()->query($sql);
            $rows = $this->sanitise($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []);
            if ($rows === []) {
                return [
                    'status' => 'ok',
                    'dataset' => $dataset,
                    'columns' => [],
                    'rows' => [],
                    'row_count' => 0,
                    'granularity' => 'aggregate',
                    'note' => 'No aggregate rows were available for this dataset.',
                ];
            }
            return [
                'status' => 'ok',
                'dataset' => $dataset,
                'columns' => array_keys($rows[0]),
                'rows' => $rows,
                'row_count' => count($rows),
                'granularity' => 'aggregate',
                'note' => 'Read from the permission-scoped read replica; aggregate rows only, no individual records.',
            ];
        } catch (Throwable $e) {
            Logger::legacyError('[PolarsDatasetService] ' . $dataset . ' failed: ' . $e->getMessage());
            return [
                'status' => 'unavailable',
                'message' => 'that dataset could not be read right now',
            ];
        }
    }

    /** @param list<string> $permissions */
    private function allowed(array $permissions, array $definition): bool
    {
        $held = array_map('strval', $permissions);
        return in_array('*', $held, true)
            || in_array($definition['permission'], $held, true)
            || in_array($definition['all_permission'], $held, true);
    }

    /**
     * Scalars only, strings bounded. The queries are fixed aggregates, but this
     * is a final guard so no unexpected value type crosses the boundary.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function sanitise(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $kept = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || $value !== null && !is_scalar($value)) {
                    continue;
                }
                $kept[$column] = is_string($value) ? mb_substr($value, 0, 120) : $value;
            }
            if ($kept !== []) {
                $clean[] = $kept;
            }
        }
        return $clean;
    }
}
