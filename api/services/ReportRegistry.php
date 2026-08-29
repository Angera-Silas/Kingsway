<?php
namespace App\API\Services;

final class ReportRegistry
{
    public static function all(): array
    {
        static $reports;
        return $reports ??= (require dirname(__DIR__, 2) . '/config/report_analytics.php');
    }

    public static function route(string $route): ?array
    {
        foreach (self::all() as $report) if ($report['route'] === $route) return $report;
        return null;
    }

    public static function roleCanAccess(string $route, int $roleId): ?bool
    {
        $report = self::route($route);
        return $report === null ? null : in_array($roleId, $report['roles'], true);
    }

    public static function forRoles(array $roleIds): array
    {
        $roleIds = array_map('intval', $roleIds);
        return array_values(array_filter(self::all(), static fn(array $report): bool => (bool) array_intersect($roleIds, $report['roles'])));
    }
}
