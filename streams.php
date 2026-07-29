<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Database;

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($classId > 0) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id, stream_name FROM class_streams WHERE class_id = :cid AND status = 'active' ORDER BY stream_name ASC");
    $stmt->execute([':cid' => $classId]);
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['streams' => $streams]);
} else {
    echo json_encode(['streams' => []]);
}
