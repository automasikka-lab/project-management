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

$action = $_POST['action'] ?? '';
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

try {
    if ($action === 'add' || $action === 'edit') {
        $cbId = isset($_POST['cb_id']) ? (int)$_POST['cb_id'] : 0;
        $itemDescription = trim($_POST['item_description'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $plannedPrice = !empty($_POST['planned_price']) ? (float)$_POST['planned_price'] : null;
        $actualPrice = !empty($_POST['actual_price']) ? (float)$_POST['actual_price'] : null;
        $plannedQty = !empty($_POST['planned_quantity']) ? (int)$_POST['planned_quantity'] : null;
        $actualQty = !empty($_POST['actual_quantity']) ? (int)$_POST['actual_quantity'] : null;

        if ($itemDescription === '') {
            echo json_encode(['ok' => false, 'msg' => 'Item name is required']);
            exit;
        }

        $plannedCost = ($plannedPrice !== null && $plannedQty !== null) ? $plannedPrice * $plannedQty : null;
        $actualCost = ($actualPrice !== null && $actualQty !== null) ? $actualPrice * $actualQty : null;

        if ($action === 'add') {
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(row_order),0) FROM cost_breakdown WHERE project_id = :pid");
            $maxOrder->execute([':pid' => $projectId]);
            $nextOrder = $maxOrder->fetchColumn() + 1;

            $stmt = $pdo->prepare("INSERT INTO cost_breakdown (project_id, item_description, unit, planned_price, actual_price, planned_quantity, actual_quantity, planned_cost, actual_cost, row_order) VALUES (:pid, :item, :unit, :pp, :ap, :pq, :aq, :pc, :ac, :ro)");
            $stmt->execute([
                ':pid' => $projectId,
                ':item' => $itemDescription,
                ':unit' => $unit,
                ':pp' => $plannedPrice,
                ':ap' => $actualPrice,
                ':pq' => $plannedQty,
                ':aq' => $actualQty,
                ':pc' => $plannedCost,
                ':ac' => $actualCost,
                ':ro' => $nextOrder
            ]);
            $newId = $pdo->lastInsertId();
            logActivity('create', 'cost_breakdown', 'Item cost breakdown ditambahkan', $projectId);
            echo json_encode(['ok' => true, 'msg' => 'Item added', 'id' => $newId]);
        } else {
            if ($cbId <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'Invalid item ID']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE cost_breakdown SET item_description = :item, unit = :unit, planned_price = :pp, actual_price = :ap, planned_quantity = :pq, actual_quantity = :aq, planned_cost = :pc, actual_cost = :ac WHERE id = :id AND project_id = :pid");
            $stmt->execute([
                ':item' => $itemDescription,
                ':unit' => $unit,
                ':pp' => $plannedPrice,
                ':ap' => $actualPrice,
                ':pq' => $plannedQty,
                ':aq' => $actualQty,
                ':pc' => $plannedCost,
                ':ac' => $actualCost,
                ':id' => $cbId,
                ':pid' => $projectId
            ]);
            if ($stmt->rowCount() > 0) {
                logActivity('update', 'cost_breakdown', 'Item cost breakdown diperbarui', $projectId);
                echo json_encode(['ok' => true, 'msg' => 'Item updated']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Item not found or no changes made']);
            }
        }
    } elseif ($action === 'delete') {
        $cbId = isset($_POST['cb_id']) ? (int)$_POST['cb_id'] : 0;
        if ($cbId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid item ID']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM cost_breakdown WHERE id = :id AND project_id = :pid");
        $stmt->execute([':id' => $cbId, ':pid' => $projectId]);
        if ($stmt->rowCount() > 0) {
            logActivity('delete', 'cost_breakdown', 'Item cost breakdown dihapus', $projectId);
            echo json_encode(['ok' => true, 'msg' => 'Item deleted']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Item not found']);
        }
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan data']);
}
?>
