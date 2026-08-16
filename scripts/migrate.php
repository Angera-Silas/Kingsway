<?php
/**
 * Migration CLI
 *
 * This CLI NEVER applies the whole migrations directory. You must name the
 * exact migration file(s) to run, e.g.:
 *
 *   php scripts/migrate.php up 042_relocate_functional_state_and_drop_log_tables.sql
 *
 * Because several migrations (036, 042, ...) use DELIMITER-based procedure /
 * trigger bodies, this tool applies files through the mysql CLI client rather
 * than splitting on ';'. Use run_kingsway_migrations.sh for the same behaviour.
 *
 * Usage:
 *   php scripts/migrate.php status
 *   php scripts/migrate.php up <file.sql ...>
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config_development.php';

$command = $argv[1] ?? 'status';
$files = array_slice($argv, 2);

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
            echo "\nUnapplied files on disk (never apply these without explicit review):\n";
            foreach ($migrator->getPending() as $m) {
                echo "  {$m['filename']}\n";
            }
            break;
        case 'up':
            if (empty($files)) {
                echo "ERROR: you must name the exact migration file(s) to apply.\n";
                echo "Usage: php scripts/migrate.php up <file.sql ...>\n";
                echo "This CLI refuses to apply the whole migrations directory.\n";
                exit(1);
            }
            $results = $migrator->applyFiles($files);
            foreach ($results as $r) {
                echo ($r['status'] === 'applied' ? '+' : '!') . " {$r['filename']} \u{2014} {$r['status']}" . ($r['duration_ms'] ? " ({$r['duration_ms']}ms)" : "") . "\n";
                if ($r['status'] === 'failed') {
                    echo "  Error: {$r['error']}\n";
                    exit(1);
                }
            }
            break;
        default:
            echo "Usage: php scripts/migrate.php [status|up <file.sql ...>]\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
