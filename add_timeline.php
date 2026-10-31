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
$date = $_POST['date'] ?? date('Y-m-d');
$pic = trim($_POST['pic'] ?? '');
$timeline = trim($_POST['timeline'] ?? '');
$prNumber = trim($_POST['pr_number'] ?? '');
$poNumber = trim($_POST['po_number'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$estimatedDuration = isset($_POST['estimated_duration']) ? (int)$_POST['estimated_duration'] : null;

if ($projectId <= 0 || $milestoneId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Incomplete data']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO project_activities (project_id, milestone_id, pic, date, timeline, pr_number, po_number, remarks, estimated_duration, row_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([
        $projectId,
        $milestoneId,
        $pic ?: null,
        $date,
        $timeline ?: null,
        $prNumber ?: null,
        $poNumber ?: null,
        $remarks ?: null,
        $estimatedDuration
    ]);
    
    logActivity('create', 'timeline', 'Timeline baru ditambahkan', $projectId);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menambahkan timeline']);
}
