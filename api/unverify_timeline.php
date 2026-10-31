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

    $actRow = $pdo->prepare("SELECT project_id FROM project_activities WHERE id = :id AND verified = 1");
    $actRow->execute([':id' => $activityId]);
    $actRow = $actRow->fetch();

    $stmt = $pdo->prepare("UPDATE project_activities SET verified = 0, verified_date = NULL WHERE id = :id AND verified = 1");
    $stmt->execute([':id' => $activityId]);
    if ($stmt->rowCount() > 0) {
        if ($actRow) {
            $pid = (int)$actRow['project_id'];

            $closeStmt = $pdo->prepare("SELECT id FROM ref_status WHERE name = 'Close Finish' LIMIT 1");
            $closeStmt->execute();
            $closeFinishId = $closeStmt->fetchColumn();

            if ($closeFinishId) {
                $curStmt = $pdo->prepare("SELECT status_id FROM projects WHERE id = :pid");
                $curStmt->execute([':pid' => $pid]);
                $currentStatus = $curStmt->fetchColumn();

                if ((int)$currentStatus === (int)$closeFinishId) {
                    $milestoneCheck = $pdo->prepare("
                        SELECT milestone_id, 
                            COUNT(*) as total, 
                            SUM(verified = 1) as verified_count
                        FROM project_activities 
                        WHERE project_id = :pid
                        GROUP BY milestone_id
                    ");
                    $milestoneCheck->execute([':pid' => $pid]);
                    $milestoneCheck = $milestoneCheck->fetchAll();
                    $allComplete = true;
                    foreach ($milestoneCheck as $mc) {
                        if ((int)$mc['total'] > 0 && (int)$mc['verified_count'] < (int)$mc['total']) {
                            $allComplete = false;
                            break;
                        }
                    }
                    if (!$allComplete) {
                        $openStmt = $pdo->prepare("SELECT id FROM ref_status WHERE name = 'Open Expansion' LIMIT 1");
                        $openStmt->execute();
                        $openExpansionId = $openStmt->fetchColumn();
                        $newStatus = $openExpansionId ?: 1;
                        $upd = $pdo->prepare("UPDATE projects SET status_id = ?, end_date = NULL WHERE id = ?");
                        $upd->execute([$newStatus, $pid]);
                    }
                }
            }
        }
        $pdo->commit();
        echo json_encode(['ok' => true]);
    } else {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Activity not found or not verified']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Gagal membatalkan verifikasi']);
}
?>
