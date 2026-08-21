<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

use App\Database\Database;

$db = Database::getInstance()->getConnection();
$sql = "SELECT sp.*,
               CONCAT(p.first_name, ' ', p.last_name) AS student_name,
               s.admission_no,
               c.name AS class_name,
               stm.name AS stream_name,
               st.name AS student_type,
               st.code AS student_type_code,
               spt.name AS permission_type_name, spt.code AS permission_type_code,
               spt.applies_to,
               COALESCE(
                   CONCAT(approver_p.first_name, ' ', approver_p.last_name),
                   approver_user.username
               ) AS approved_by_name
        FROM student_permissions sp
        JOIN students s ON sp.student_id = s.id
        LEFT JOIN persons p ON p.id = s.person_id
        LEFT JOIN student_types st ON st.id = s.student_type_id
        JOIN student_permission_types spt ON sp.permission_type_id = spt.id
        LEFT JOIN users approver_user ON sp.approved_by = approver_user.id
        LEFT JOIN staff approver_staff ON approver_staff.person_id = approver_user.person_id
        LEFT JOIN persons approver_p ON approver_p.id = approver_staff.person_id
        WHERE 1=1
        ORDER BY sp.created_at DESC LIMIT 250";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([]);
    echo "OK rows=" . count($stmt->fetchAll(\PDO::FETCH_ASSOC)) . "\n";
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
