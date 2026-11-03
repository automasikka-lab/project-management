<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }

$project = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
$project->execute([$id]);
if (!$project->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Project not found']); exit; }

try {
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM project_activities WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_planning_timeline WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_development_analysis WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_action_analysis WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_planned_price WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cost_breakdown WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

    $pdo->commit();
    logActivity('delete', 'project', 'Proyek dihapus', $id);
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete_project error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Gagal menghapus proyek: ' . $e->getMessage()]);
}
