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

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$milestoneId = isset($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : 0;
$milestoneName = $_POST['milestone_name'] ?? '';

if ($projectId <= 0 || $milestoneId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Incomplete data']);
    exit;
}

try {
    $picStmt = $pdo->prepare("SELECT pic FROM projects WHERE id = ?");
    $picStmt->execute([$projectId]);
    $pic = $picStmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO project_activities (project_id, milestone_id, pic, date, timeline, remarks, row_order) VALUES (?, ?, ?, CURDATE(), ?, ?, 0)");
    $stmt->execute([
        $projectId,
        $milestoneId,
        $pic ?: null,
        'VERIFIED: ' . $milestoneName,
        'Milestone successfully verified'
    ]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal memverifikasi milestone']);
}
