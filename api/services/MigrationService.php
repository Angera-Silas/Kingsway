<?php
namespace App\API\Services;

use PDO;

class MigrationService
{
    private PDO $db;
    private string $migrationsDir;

    public function __construct(PDO $db, ?string $migrationsDir = null)
    {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir ?: dirname(__DIR__, 2) . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Resolve the MySQL client binary.
     *
     * The stack is system Apache + system MariaDB (XAMPP was removed), so the
     * client that ships with the OS package is the one that exists. A packaged
     * XAMPP path is deliberately NOT a fallback any more: it would make the
     * migration runner fail on every clean install with a misleading
     * "mysql client not executable" error instead of finding the real client.
     *
     * An explicit MYSQL_BIN still overrides everything (CI, odd PATHs).
     */
    private static function mysqlBinary(): string
    {
        $env = getenv('MYSQL_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }

        foreach (['/usr/bin/mysql', '/usr/local/bin/mysql'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = @shell_exec('command -v mysql 2>/dev/null');
        return is_string($which) && trim($which) !== '' ? trim($which) : '/usr/bin/mysql';
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            checksum VARCHAR(64) NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            duration_ms INT DEFAULT 0
        ) ENGINE=InnoDB");
    }

    public function getPending(): array
    {
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        $applied = $this->db->query("SELECT filename, checksum FROM migrations")->fetchAll(PDO::FETCH_KEY_PAIR);

        $pending = [];
        foreach ($files as $file) {
            $basename = basename($file);
            $checksum = md5_file($file);
            if (!isset($applied[$basename])) {
                $pending[] = ['filename' => $basename, 'path' => $file, 'checksum' => $checksum];
            } elseif ($applied[$basename] !== $checksum) {
                $pending[] = ['filename' => $basename, 'path' => $file, 'checksum' => $checksum, 'modified' => true];
            }
        }
        return $pending;
    }

    /**
     * Cross-check the ledger against the files on disk AND the live schema.
     *
     * The ledger alone is not trustworthy: some migration files used to insert
     * their own ledger row, so a file run with errors ignored could be recorded
     * as applied while its CREATE TABLE never happened. For every file this
     * reports whether it is recorded, whether its checksum still matches, and
     * which tables/views it creates that do not exist (ignoring objects a
     * migration later drops).
     *
     * @return array{summary: array<string,int>, files: list<array<string,mixed>>, orphan_ledger_rows: list<string>}
     */
    public function verify(): array
    {
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files);
        $ledger = $this->db->query("SELECT filename, checksum FROM migrations")->fetchAll(PDO::FETCH_KEY_PAIR);
        $schema = (string) $this->db->query("SELECT DATABASE()")->fetchColumn();

        $existing = [];
        foreach ($this->db->query("SELECT LOWER(table_schema), LOWER(table_name) FROM information_schema.tables")->fetchAll(PDO::FETCH_NUM) as [$s, $t]) {
            $existing[$s . '.' . $t] = true;
        }

        $parsed = [];
        $dropped = [];
        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);
            $parsed[$file] = $sql;
            foreach (self::objectNames($sql, '/\bDROP\s+(?:TABLE|VIEW)\s+(?:IF\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?(?:\s*,\s*(?:`?\w+`?\.)?`?\w+`?)*)/i', $schema) as $name) {
                $dropped[$name] = true;
            }
        }

        $summary = ['files' => 0, 'verified' => 0, 'unrecorded' => 0, 'checksum_drift' => 0, 'missing_objects' => 0, 'self_ledgering' => 0];
        $rows = [];
        foreach ($parsed as $file => $sql) {
            $basename = basename($file);
            $created = self::objectNames($sql, '/\bCREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?(?:TABLE|VIEW)\s+(?:IF\s+NOT\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?)/i', $schema);
            $missing = array_values(array_filter($created, fn ($n) => !isset($existing[$n]) && !isset($dropped[$n])));
            $recorded = isset($ledger[$basename]);
            $checksumOk = $recorded && hash_equals((string) $ledger[$basename], (string) md5_file($file));
            $selfLedgering = (bool) preg_match('/INSERT\s+INTO\s+`?migrations`?/i', $sql);

            $summary['files']++;
            if (!$recorded) $summary['unrecorded']++;
            if ($recorded && !$checksumOk) $summary['checksum_drift']++;
            if ($missing) $summary['missing_objects']++;
            if ($selfLedgering) $summary['self_ledgering']++;
            if ($recorded && $checksumOk && !$missing && !$selfLedgering) $summary['verified']++;

            $rows[] = [
                'filename' => $basename,
                'recorded' => $recorded,
                'checksum_ok' => $checksumOk,
                'missing_objects' => $missing,
                'self_ledgering' => $selfLedgering,
            ];
        }

        $onDisk = array_flip(array_map('basename', $files));
        $orphans = array_values(array_filter(array_keys($ledger), fn ($f) => !isset($onDisk[$f])));
        sort($orphans);

        return ['summary' => $summary, 'files' => $rows, 'orphan_ledger_rows' => $orphans];
    }

    /**
     * @return list<string> lower-case "schema.object" names captured by $pattern
     */
    private static function objectNames(string $sql, string $pattern, string $defaultSchema): array
    {
        // Strip comments so documented examples are not mistaken for DDL.
        $sql = preg_replace(['~/\*.*?\*/~s', '~^\s*(--|#).*$~m'], '', $sql) ?? $sql;
        if (!preg_match_all($pattern, $sql, $m)) {
            return [];
        }
        $names = [];
        foreach ($m[1] as $list) {
            foreach (explode(',', $list) as $ref) {
                $ref = strtolower(str_replace('`', '', trim($ref)));
                if ($ref === '') continue;
                $names[] = str_contains($ref, '.') ? $ref : strtolower($defaultSchema) . '.' . $ref;
            }
        }
        return array_values(array_unique($names));
    }

    public function getStatus(): array
    {
        $stmt = $this->db->query("SELECT filename, checksum, applied_at, duration_ms FROM migrations ORDER BY applied_at");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apply ONLY the explicitly named migration files.
     *
     * This deliberately refuses to apply the whole migrations directory. Each
     * file is executed through the mysql CLI client (not a ';' split, which
     * breaks DELIMITER-based procedure/trigger bodies). Files already recorded
     * in the ledger are skipped unless $force is true. Successful applies are
     * recorded in the migrations ledger.
     *
     * @param array $filenames Exact file basenames, e.g. ['042_....sql']
     * @param bool  $force     Re-run files that are already in the ledger
     * @return array<int, array{filename:string,status:string,error?:string,duration_ms?:int}>
     */
    public function applyFiles(array $filenames, bool $force = false): array
    {
        if (empty($filenames)) {
            throw new \InvalidArgumentException('At least one migration filename is required');
        }

        $mysql = self::mysqlBinary();
        if (!is_executable($mysql)) {
            throw new \RuntimeException("mysql client not executable: {$mysql}");
        }

        $applied = $this->db->query("SELECT filename, checksum FROM migrations")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $results = [];

        foreach ($filenames as $filename) {
            $basename = basename((string) $filename);
            if (strpos($basename, '.sql') !== strlen($basename) - 4) {
                $results[] = ['filename' => $basename, 'status' => 'failed', 'error' => 'Not a .sql migration file'];
                break;
            }
            $path = $this->migrationsDir . '/' . $basename;
            if (!is_file($path)) {
                $results[] = ['filename' => $basename, 'status' => 'failed', 'error' => 'Migration file not found'];
                break;
            }
            if (isset($applied[$basename]) && !$force) {
                $results[] = ['filename' => $basename, 'status' => 'skipped', 'duration_ms' => 0];
                continue;
            }

            $checksum = md5_file($path);
            $start = microtime(true);

            // DB_NAME from config/.env; no built-in name (local and production differ).
            $dbname = \App\Database\ConnectionManager::schemaFor(\App\Database\ConnectionManager::NS_MASTER);
            $user = defined('DB_USER') ? DB_USER : 'root';
            $pass = defined('DB_PASS') ? DB_PASS : '';
            $host = defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1';
            $port = defined('DB_PORT') ? (string) DB_PORT : '3306';

            // A migration must not write its own ledger row: if it runs with
            // errors the ledger would claim it applied. The runner records it.
            if (preg_match('/INSERT\s+INTO\s+`?migrations`?/i', (string) file_get_contents($path))) {
                $results[] = ['filename' => $basename, 'status' => 'failed', 'error' => 'Migration inserts its own ledger row; remove that block and let the runner record it'];
                break;
            }

            // Password goes through MYSQL_PWD so it never appears in the process list.
            $command = 'MYSQL_PWD=' . escapeshellarg($pass) . ' ' . escapeshellcmd($mysql)
                . ' --batch -h ' . escapeshellarg($host) . ' -P ' . escapeshellarg($port)
                . ' -u ' . escapeshellarg($user) . ' ' . escapeshellarg($dbname)
                . ' < ' . escapeshellarg($path) . ' 2>&1';
            $outputLines = [];
            exec($command, $outputLines, $exitCode);

            if ($exitCode !== 0) {
                $results[] = [
                    'filename' => $basename,
                    'status' => 'failed',
                    'error' => implode(' | ', array_slice($outputLines, 0, 8)),
                ];
                break;
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->db->exec(
                "INSERT INTO migrations (filename, checksum, applied_at, duration_ms) VALUES ("
                . $this->db->quote($basename) . ', '
                . $this->db->quote($checksum) . ', NOW(), ' . $duration . ')'
                . " ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = NOW(), duration_ms = VALUES(duration_ms)"
            );
            $results[] = ['filename' => $basename, 'status' => 'applied', 'duration_ms' => $duration];
        }

        return $results;
    }

    /**
     * Evidence that a file's effects are already in the live schema: every
     * table/view it creates exists (or a later file drops it) and every column
     * it adds with ALTER TABLE ... ADD [COLUMN] exists.
     *
     * @return list<string> what is missing; empty means the evidence holds
     */
    public function missingEffects(string $basename): array
    {
        $path = $this->migrationsDir . '/' . basename($basename);
        if (!is_file($path)) {
            return ['file not found'];
        }
        $sql = (string) file_get_contents($path);
        $schema = strtolower((string) $this->db->query('SELECT DATABASE()')->fetchColumn());
        $missing = [];
        $checked = 0;

        $tables = [];
        foreach ($this->db->query('SELECT LOWER(table_schema), LOWER(table_name) FROM information_schema.tables')->fetchAll(PDO::FETCH_NUM) as [$sc, $t]) {
            $tables[$sc . '.' . $t] = true;
        }
        $dropped = [];
        foreach (glob($this->migrationsDir . '/*.sql') ?: [] as $file) {
            foreach (self::objectNames((string) file_get_contents($file), '/\bDROP\s+(?:TABLE|VIEW)\s+(?:IF\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?(?:\s*,\s*(?:`?\w+`?\.)?`?\w+`?)*)/i', $schema) as $n) {
                $dropped[$n] = true;
            }
        }
        foreach (self::objectNames($sql, '/\bCREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?(?:TABLE|VIEW)\s+(?:IF\s+NOT\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?)/i', $schema) as $n) {
            $checked++;
            if (!isset($tables[$n]) && !isset($dropped[$n])) {
                $missing[] = $n;
            }
        }
        // Tables/views this file drops must be gone (unless re-created by it).
        $createdHere = array_flip(self::objectNames($sql, '/\bCREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?(?:TABLE|VIEW)\s+(?:IF\s+NOT\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?)/i', $schema));
        foreach (self::objectNames($sql, '/\bDROP\s+(?:TABLE|VIEW)\s+(?:IF\s+EXISTS\s+)?((?:`?\w+`?\.)?`?\w+`?(?:\s*,\s*(?:`?\w+`?\.)?`?\w+`?)*)/i', $schema) as $n) {
            if (isset($createdHere[$n])) {
                continue;
            }
            $checked++;
            if (isset($tables[$n])) {
                $missing[] = "still present: {$n}";
            }
        }

        $clean = preg_replace(['~/\*.*?\*/~s', '~^\s*(--|#).*$~m'], '', $sql) ?? $sql;
        if (preg_match_all('/\bALTER\s+TABLE\s+((?:`?\w+`?\.)?`?\w+`?)\s+(.*?);/is', $clean, $alters, PREG_SET_ORDER)) {
            $colStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE LOWER(table_schema) = ? AND LOWER(table_name) = ? AND LOWER(column_name) = ?'
            );
            foreach ($alters as [, $tableRef, $body]) {
                $ref = strtolower(str_replace('`', '', $tableRef));
                [$sc, $table] = str_contains($ref, '.') ? explode('.', $ref, 2) : [$schema, $ref];
                if (isset($dropped[$sc . '.' . $table])) {
                    continue;
                }
                if (!preg_match_all('/\bADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s+(?!KEY\b|INDEX\b|UNIQUE\b|CONSTRAINT\b|PRIMARY\b|FOREIGN\b|FULLTEXT\b|SPATIAL\b)\w/i', $body, $cols)) {
                    continue;
                }
                foreach ($cols[1] as $col) {
                    $c = strtolower($col);
                    if (in_array($c, ['key', 'index', 'unique', 'constraint', 'primary', 'foreign', 'fulltext', 'spatial', 'partition'], true)) {
                        continue;
                    }
                    $checked++;
                    $colStmt->execute([$sc, $table, $c]);
                    if ((int) $colStmt->fetchColumn() === 0) {
                        $missing[] = "{$sc}.{$table}.{$c}";
                    }
                }
            }
        }
        if ($checked === 0) {
            // Routine bodies, data fixes and index changes leave nothing this
            // check can see; such a file needs a person to confirm it.
            $missing[] = 'nothing checkable (routines/data/indexes only): confirm by hand';
        }
        return array_values(array_unique($missing));
    }

    /**
     * Record files as already applied WITHOUT running them. Refused for any
     * file whose effects are not present (see missingEffects) or that is
     * already recorded. duration_ms = -1 marks a baselined row.
     *
     * @return list<array{filename:string,status:string,error?:string}>
     */
    public function baselineFiles(array $filenames, bool $confirmed = false): array
    {
        $ledger = $this->db->query('SELECT filename, checksum FROM migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $results = [];
        $insert = $this->db->prepare('INSERT INTO migrations (filename, checksum, applied_at, duration_ms) VALUES (?, ?, NOW(), -1)');
        foreach ($filenames as $filename) {
            $basename = basename((string) $filename);
            if (isset($ledger[$basename])) {
                $results[] = ['filename' => $basename, 'status' => 'skipped', 'error' => 'already recorded'];
                continue;
            }
            $missing = $this->missingEffects($basename);
            if ($confirmed) {
                // A person checked the data/routine effects; real gaps still refuse.
                $missing = array_values(array_filter($missing, static fn(string $m): bool => !str_starts_with($m, 'nothing checkable')));
            }
            if ($missing) {
                $results[] = ['filename' => $basename, 'status' => 'refused', 'error' => 'not in schema: ' . implode(', ', array_slice($missing, 0, 6))];
                continue;
            }
            $insert->execute([$basename, md5_file($this->migrationsDir . '/' . $basename)]);
            $results[] = ['filename' => $basename, 'status' => 'baselined'];
        }
        return $results;
    }

    /**
     * Re-record the checksum of an applied file that was edited afterwards.
     * Only for recorded files whose effects are present; it never runs SQL.
     *
     * @return list<array{filename:string,status:string,error?:string}>
     */
    public function acceptDrift(array $filenames): array
    {
        $ledger = $this->db->query('SELECT filename, checksum FROM migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $update = $this->db->prepare('UPDATE migrations SET checksum = ? WHERE filename = ?');
        $results = [];
        foreach ($filenames as $filename) {
            $basename = basename((string) $filename);
            $path = $this->migrationsDir . '/' . $basename;
            if (!isset($ledger[$basename]) || !is_file($path)) {
                $results[] = ['filename' => $basename, 'status' => 'refused', 'error' => 'not recorded or file missing'];
                continue;
            }
            $missing = $this->missingEffects($basename);
            if ($missing) {
                $results[] = ['filename' => $basename, 'status' => 'refused', 'error' => 'not in schema: ' . implode(', ', array_slice($missing, 0, 6))];
                continue;
            }
            $update->execute([md5_file($path), $basename]);
            $results[] = ['filename' => $basename, 'status' => 'accepted'];
        }
        return $results;
    }
}
