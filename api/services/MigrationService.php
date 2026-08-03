<?php
namespace App\API\Services;

use PDO;

class MigrationService
{
    private PDO $db;
    private string $migrationsDir;

    public function __construct(PDO $db, string $migrationsDir = null)
    {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir ?: dirname(__DIR__, 2) . '/database/migrations';
        $this->ensureMigrationsTable();
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

    public function migrate(): array
    {
        $results = [];
        foreach ($this->getPending() as $migration) {
            $start = microtime(true);
            $sql = file_get_contents($migration['path']);
            $statements = explode(';', $sql);
            $this->db->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt)) {
                        $this->db->exec($stmt);
                    }
                }
                $duration = (int)((microtime(true) - $start) * 1000);
                $this->db->exec("INSERT INTO migrations (filename, checksum, duration_ms) VALUES ("
                    . $this->db->quote($migration['filename']) . ", "
                    . $this->db->quote($migration['checksum']) . ", $duration)");
                $this->db->commit();
                $results[] = ['filename' => $migration['filename'], 'status' => 'applied', 'duration_ms' => $duration];
            } catch (\Exception $e) {
                $this->db->rollBack();
                $results[] = ['filename' => $migration['filename'], 'status' => 'failed', 'error' => $e->getMessage()];
                break;
            }
        }
        return $results;
    }

    public function getStatus(): array
    {
        $stmt = $this->db->query("SELECT filename, checksum, applied_at, duration_ms FROM migrations ORDER BY applied_at");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
