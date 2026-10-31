<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$projectId = isset($input['project_id']) ? (int)$input['project_id'] : 0;
$newRanking = isset($input['ranking']) ? (int)$input['ranking'] : null;

if ($projectId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

try {
    $givenPriorityId = $pdo->query("SELECT id FROM ref_priority WHERE name = 'Given Priority' LIMIT 1")->fetchColumn();

    if ($newRanking !== null && $newRanking <= 10 && $givenPriorityId) {
        $stmt = $pdo->prepare("UPDATE projects SET ranking = ?, priority_id = ? WHERE id = ?");
        $stmt->execute([$newRanking, $givenPriorityId, $projectId]);
        echo json_encode(['ok' => true, 'priority_updated' => true]);
    } else {
        $stmt = $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?");
        $stmt->execute([$newRanking, $projectId]);
        echo json_encode(['ok' => true, 'priority_updated' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
