#!/usr/bin/env php
<?php
/**
 * Generate ROUTE_ROLES mapping from role_sidebars.php.
 * Usage: php scripts/generate_route_roles.php
 */
$sidebars = require __DIR__ . '/../config/role_sidebars.php';

$routeRoles = [];

foreach ($sidebars as $roleId => $items) {
    foreach ($items as $item) {
        // Top-level item with a URL
        if (!empty($item['url']) && is_string($item['url'])) {
            $url = $item['url'];
            if (!isset($routeRoles[$url])) {
                $routeRoles[$url] = [];
            }
            if (!in_array($roleId, $routeRoles[$url])) {
                $routeRoles[$url][] = $roleId;
            }
        }

        // Subitems
        if (!empty($item['subitems']) && is_array($item['subitems'])) {
            foreach ($item['subitems'] as $sub) {
                if (!empty($sub['url']) && is_string($sub['url'])) {
                    $url = $sub['url'];
                    if (!isset($routeRoles[$url])) {
                        $routeRoles[$url] = [];
                    }
                    if (!in_array($roleId, $routeRoles[$url])) {
                        $routeRoles[$url][] = $roleId;
                    }
                }
            }
        }
    }
}

ksort($routeRoles);

echo "// Auto-generated from role_sidebars.php — do not edit manually\n";
echo "// Run: php scripts/generate_route_roles.php\n";
echo "private const ROUTE_ROLES = [\n";
foreach ($routeRoles as $url => $roles) {
    sort($roles);
    $roleStr = implode(', ', $roles);
    $count = count($roles);
    echo "    '{$url}' => [{$roleStr}],  // {$count} role(s)\n";
}
echo "];\n";
echo "\n// Total: " . count($routeRoles) . " unique routes\n";

// Summary stats
$shared = array_filter($routeRoles, fn($roles) => count($roles) > 1);
$single = array_filter($routeRoles, fn($roles) => count($roles) === 1);
echo "// Routes in 1 role only: " . count($single) . "\n";
echo "// Routes shared by 2+ roles: " . count($shared) . "\n";
$multi = array_filter($routeRoles, fn($roles) => count($roles) >= 5);
echo "// Routes shared by 5+ roles: " . count($multi) . "\n";
