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

$activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
if ($activityId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid activity ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM project_activities WHERE id = :id");
    $stmt->execute([':id' => $activityId]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        logActivity('delete', 'activity', 'Activity dihapus', $activityId);
        echo json_encode(['ok' => true, 'msg' => 'Activity deleted successfully']);
    } else {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Activity not found']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Gagal menghapus activity']);
}
?>
