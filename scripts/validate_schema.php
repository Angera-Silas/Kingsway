<?php

declare(strict_types=1);

/**
 * Read-only validation of the live database contract used by core modules.
 *
 * Usage:
 *   MYSQL_PWD='...' /opt/lampp/bin/php scripts/validate_schema.php
 *
 * The validator never creates, updates, or deletes database objects or data.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;

Config::init();
$db = Database::getInstance()->getConnection();

$required = [
    'users' => ['id', 'username', 'status'],
    'roles' => ['id', 'name'],
    'students' => ['id', 'admission_no'],
    'staff' => ['id', 'staff_no'],
    'academic_years' => ['id'],
    'academic_terms' => ['id', 'academic_year_id'],
    'assessments' => ['id', 'class_id', 'subject_id', 'term_id', 'max_marks', 'status'],
    'assessment_results' => ['assessment_id', 'student_id', 'marks_obtained', 'grade', 'points'],
    'payment_transactions' => ['id', 'student_id'],
    'payment_allocations_detailed' => ['payment_transaction_id'],
];

$missing = [];

foreach ($required as $table => $columns) {
    $tableStmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $tableStmt->execute([$table]);

    if ((int) $tableStmt->fetchColumn() !== 1) {
        $missing[] = "table {$table}";
        continue;
    }

    $columnStmt = $db->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $columnStmt->execute([$table]);
    $available = array_flip($columnStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($columns as $column) {
        if (!isset($available[$column])) {
            $missing[] = "column {$table}.{$column}";
        }
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Schema contract validation failed:\n");
    foreach ($missing as $item) {
        fwrite(STDERR, " - {$item}\n");
    }
    exit(1);
}

echo "Schema contract validation passed for " . count($required) . " core tables.\n";
