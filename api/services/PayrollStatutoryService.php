<?php
declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * Stores the payroll-period decision for each employee statutory item.
 *
 * The payslip amount columns remain the authoritative calculated values. This
 * service stores the explicit on/off decision that explains why a statutory
 * amount is zero, so a later report or reviewer can distinguish "not applied"
 * from "calculated as zero".
 */
final class PayrollStatutoryService
{
    public const DEFAULT_OPTIONS = [
        'paye' => false,
        'nssf' => false,
        'shif' => false,
        'housing_levy' => false,
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    /**
     * Normalise a browser/API selection. Every item defaults to disabled.
     */
    public static function normaliseOptions($options): array
    {
        $options = is_array($options) ? $options : [];
        $result = self::DEFAULT_OPTIONS;

        $keys = [
            'paye' => ['paye', 'paye_enabled', 'apply_paye'],
            'nssf' => ['nssf', 'nssf_enabled', 'apply_nssf'],
            'shif' => ['shif', 'shif_enabled', 'apply_shif', 'nhif', 'nhif_enabled'],
            'housing_levy' => ['housing_levy', 'housing_levy_enabled', 'apply_housing_levy'],
        ];

        foreach ($keys as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $options)) {
                    $result[$canonical] = self::toBool($options[$alias]);
                    break;
                }
            }
        }

        return $result;
    }

    public function optionsForPayslip(int $payslipId): array
    {
        $stmt = $this->db->prepare(
            "SELECT paye_enabled, nssf_enabled, shif_enabled, housing_levy_enabled
             FROM payroll_statutory_options WHERE payslip_id = ? LIMIT 1"
        );
        $stmt->execute([$payslipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return self::DEFAULT_OPTIONS;

        return [
            'paye' => (bool) $row['paye_enabled'],
            'nssf' => (bool) $row['nssf_enabled'],
            'shif' => (bool) $row['shif_enabled'],
            'housing_levy' => (bool) $row['housing_levy_enabled'],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $payslips
     * @return array<int,array<string,bool>>
     */
    public function optionsForPayslips(array $payslips): array
    {
        $ids = [];
        foreach ($payslips as $payslip) {
            $id = (int) ($payslip['id'] ?? $payslip['payslip_id'] ?? 0);
            if ($id > 0) $ids[$id] = $id;
        }
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT payslip_id, paye_enabled, nssf_enabled, shif_enabled, housing_levy_enabled
             FROM payroll_statutory_options WHERE payslip_id IN ($placeholders)"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byPayslip = [];
        foreach ($rows as $row) {
            $byPayslip[(int) $row['payslip_id']] = [
                'paye' => (bool) $row['paye_enabled'],
                'nssf' => (bool) $row['nssf_enabled'],
                'shif' => (bool) $row['shif_enabled'],
                'housing_levy' => (bool) $row['housing_levy_enabled'],
            ];
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $byPayslip[$id] ?? self::DEFAULT_OPTIONS;
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $payslip
     * @param array<string,mixed> $options
     * @return array<string,bool>
     */
    public function saveForPayslip(array $payslip, array $options, int $actorUserId = 0): array
    {
        $payslipId = (int) ($payslip['id'] ?? $payslip['payslip_id'] ?? 0);
        $staffId = (int) ($payslip['staff_id'] ?? 0);
        $month = (int) ($payslip['payroll_month'] ?? 0);
        $year = (int) ($payslip['payroll_year'] ?? 0);
        $scope = (string) ($payslip['data_scope'] ?? 'live');
        if ($payslipId <= 0 || $staffId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            throw new \InvalidArgumentException('A valid payslip is required to save statutory options.');
        }

        $normalised = self::normaliseOptions($options);
        $stmt = $this->db->prepare(
            "INSERT INTO payroll_statutory_options
                (payslip_id, staff_id, payroll_month, payroll_year, data_scope,
                 paye_enabled, nssf_enabled, shif_enabled, housing_levy_enabled,
                 selected_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                staff_id = VALUES(staff_id), payroll_month = VALUES(payroll_month),
                payroll_year = VALUES(payroll_year), data_scope = VALUES(data_scope),
                paye_enabled = VALUES(paye_enabled), nssf_enabled = VALUES(nssf_enabled),
                shif_enabled = VALUES(shif_enabled), housing_levy_enabled = VALUES(housing_levy_enabled),
                selected_by = VALUES(selected_by), updated_at = NOW()"
        );
        $stmt->execute([
            $payslipId,
            $staffId,
            $month,
            $year,
            in_array($scope, ['live', 'test'], true) ? $scope : 'live',
            (int) $normalised['paye'],
            (int) $normalised['nssf'],
            (int) $normalised['shif'],
            (int) $normalised['housing_levy'],
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $normalised;
    }

    private static function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return (float) $value !== 0.0;
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) return true;
        }
        return false;
    }
}
