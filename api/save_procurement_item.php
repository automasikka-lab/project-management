<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

$projectId  = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
$item       = trim($_POST['item'] ?? '');
$prNumber   = trim($_POST['pr_number'] ?? '');
$status     = trim($_POST['status'] ?? '');

if ($projectId <= 0 || $activityId < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid project / activity ID']);
    exit;
}
// Nama item hanya untuk logging — tidak disimpan (kolom ITEM tampil dari Budgeting — Assessment).

// Nilai status yang diizinkan — harus sinkron dengan $procStatusList di project_detail.php.
$validStatus = ['', 'Pending PR', 'Pending PO', 'Unapproved PR', 'Unapproved PO', 'PR Approved', 'PO Approved', 'Vendor', 'Received', 'Pending Vendor'];
if (!in_array($status, $validStatus, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid status']);
    exit;
}
// Data lama 'Pending Vendor' dinormalisasi menjadi 'Vendor'.
if ($status === 'Pending Vendor') $status = 'Vendor';

try {
    if ($activityId === 0) {
        // Baris item Budgeting belum punya activity Procurement → buat baru (milestone 5).
        // timeline diisi nama item agar konsisten; nama tampilan tetap dari Budgeting.
        $ins = $pdo->prepare("INSERT INTO project_activities (project_id, milestone_id, timeline, pr_number, procurement_status, remarks, verified)
                               VALUES (:pid, 5, :item, :pr, :ps, NULL, 0)");
        $ins->execute([
            ':pid' => $projectId,
            ':item' => $item,
            ':pr'   => $prNumber,
            ':ps'   => $status !== '' ? $status : null,
        ]);
        $activityId = (int)$pdo->lastInsertId();
        if ($activityId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Gagal membuat item procurement']);
            exit;
        }
    } else {
        // Field No PO sudah dihapus dari form, sehingga po_number TIDAK ikut diubah (data existing tetap).
        // Kolom ITEM di card Procurement diambil dari Budgeting — Assessment, sehingga
        // timeline (activity) TIDAK ditimpa dari form — hanya No PR & status yang diubah.
        $stmt = $pdo->prepare("UPDATE project_activities SET pr_number = :pr, procurement_status = :ps
                               WHERE id = :id AND project_id = :pid AND milestone_id = 5");
        $stmt->execute([
            ':pr'   => $prNumber,
            ':ps'   => $status !== '' ? $status : null,
            ':id'   => $activityId,
            ':pid'  => $projectId
        ]);

        if ($stmt->rowCount() === 0) {
            // Cek apakah barisnya benar-benar ada (bisa saja tidak ada perubahan sama sekali).
            $chk = $pdo->prepare("SELECT id FROM project_activities WHERE id = :id AND project_id = :pid AND milestone_id = 5");
            $chk->execute([':id' => $activityId, ':pid' => $projectId]);
            if (!$chk->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Item not found']);
                exit;
            }
        }
    }

    $sel = $pdo->prepare("SELECT id, timeline, pr_number, po_number, procurement_status, pic, date, remarks
                          FROM project_activities WHERE id = :id AND project_id = :pid");
    $sel->execute([':id' => $activityId, ':pid' => $projectId]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Item not found']);
        exit;
    }

    logActivity('update', 'procurement', 'Item procurement diperbarui: ' . $item, $projectId);
    echo json_encode(['ok' => true, 'msg' => 'Item updated', 'item' => [
        'activity_id'       => (int)$row['id'],
        'item'              => (string)($row['timeline'] ?? ''),
        'pr_number'         => (string)($row['pr_number'] ?? ''),
        'po_number'         => (string)($row['po_number'] ?? ''),
        'procurement_status'=> (string)($row['procurement_status'] ?? ''),
        'status'            => (string)($row['procurement_status'] ?? ''),
        'pic'               => (string)($row['pic'] ?? ''),
        'date'              => (string)($row['date'] ?? ''),
        'remarks'           => (string)($row['remarks'] ?? ''),
    ]]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan item procurement']);
}
?>
