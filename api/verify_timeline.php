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

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE project_activities SET verified = 1, verified_date = :now WHERE id = :id AND verified = 0");
    $stmt->execute([':now' => $now, ':id' => $activityId]);
    
    if ($stmt->rowCount() > 0) {
        $actRow = $pdo->prepare("SELECT project_id FROM project_activities WHERE id = :id");
        $actRow->execute([':id' => $activityId]);
        $actRow = $actRow->fetch();
        if ($actRow) {
            $pid = (int)$actRow['project_id'];
            $milestoneCheck = $pdo->prepare("
                SELECT pa.milestone_id, 
                    COUNT(*) as total, 
                    SUM(pa.verified = 1) as verified_count
                FROM project_activities pa
                WHERE pa.project_id = :pid
                GROUP BY pa.milestone_id
            ");
            $milestoneCheck->execute([':pid' => $pid]);
            $milestoneCheck = $milestoneCheck->fetchAll();
            $allComplete = count($milestoneCheck) > 0;
            foreach ($milestoneCheck as $mc) {
                if ((int)$mc['total'] > 0 && (int)$mc['verified_count'] < (int)$mc['total']) {
                    $allComplete = false;
                    break;
                }
            }
            if ($allComplete) {
                $closeFinishStmt = $pdo->prepare("SELECT id FROM ref_status WHERE name = 'Close Finish' LIMIT 1");
                $closeFinishStmt->execute();
                $closeFinishId = $closeFinishStmt->fetchColumn();
                if ($closeFinishId) {
                    $upd = $pdo->prepare("UPDATE projects SET status_id = ?, end_date = ? WHERE id = ?");
                    $upd->execute([$closeFinishId, $now, $pid]);
                }
            }
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'Timeline verified successfully']);
    } else {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Activity not found or already verified']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Gagal memverifikasi timeline']);
}
?>
