<?php
/**
 * Migration CLI
 * Usage: php scripts/migrate.php [status|up|fresh]
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config_development.php';

$command = $argv[1] ?? 'up';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $migrator = new \App\API\Services\MigrationService($pdo);

    switch ($command) {
        case 'status':
            $applied = $migrator->getStatus();
            echo "Applied migrations:\n";
            foreach ($applied as $m) {
                echo "  [{$m['applied_at']}] {$m['filename']} ({$m['duration_ms']}ms)\n";
            }
            $pending = $migrator->getPending();
            echo "\nPending migrations:\n";
            if (empty($pending)) {
                echo "  (none)\n";
            } else {
                foreach ($pending as $m) {
                    echo "  {$m['filename']}\n";
                }
            }
            break;
        case 'up':
            $results = $migrator->migrate();
            foreach ($results as $r) {
                echo ($r['status'] === 'applied' ? '+' : '!') . " {$r['filename']} \u{2014} {$r['status']}" . ($r['duration_ms'] ? " ({$r['duration_ms']}ms)" : "") . "\n";
                if ($r['status'] === 'failed') {
                    echo "  Error: {$r['error']}\n";
                    exit(1);
                }
            }
            break;
        case 'fresh':
            $pdo->exec("DROP TABLE IF EXISTS migrations");
            $results = $migrator->migrate();
            foreach ($results as $r) {
                echo ($r['status'] === 'applied' ? '+' : '!') . " {$r['filename']} \u{2014} {$r['status']}\n";
            }
            break;
        default:
            echo "Usage: php scripts/migrate.php [status|up|fresh]\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
