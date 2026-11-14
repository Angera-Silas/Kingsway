<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Creates the extra-charge obligations that have fallen due.
 *
 * This used to be the MariaDB event ev_generate_due_extra_charge_obligations,
 * which only runs while the server's event_scheduler is ON. Scheduling now
 * lives in python_platform/schedules.yaml (job extra_charge_obligations), so
 * the work runs wherever the ticker runs. The procedure uses INSERT IGNORE on
 * the obligation's unique key, so running it more often than daily is safe.
 */
final class ExtraChargeObligationService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{created:int} */
    public function generateDue(): array
    {
        $before = (int) $this->db->query('SELECT COUNT(*) FROM extra_charge_student_obligations')->fetchColumn();
        $stmt = $this->db->query('CALL sp_generate_due_extra_charge_obligations()');
        if ($stmt !== false) {
            $stmt->closeCursor();
        }
        $after = (int) $this->db->query('SELECT COUNT(*) FROM extra_charge_student_obligations')->fetchColumn();
        return ['created' => max(0, $after - $before)];
    }
}
