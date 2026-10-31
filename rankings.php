<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Read operations - no auth required
if (in_array($action, ['list', 'unranked'])) {
    switch ($action) {
    case 'list':
        $rows = $pdo->query("SELECT p.id, p.project_name, p.ranking, rs.name as status_name, rp.name as priority_name, p.pic FROM projects p LEFT JOIN ref_status rs ON p.status_id=rs.id LEFT JOIN ref_priority rp ON p.priority_id=rp.id WHERE p.ranking IS NOT NULL ORDER BY p.ranking ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;
    case 'unranked':
        $rows = $pdo->query("SELECT p.id, p.project_name, rs.name as status_name, rp.name as priority_name, p.pic FROM projects p LEFT JOIN ref_status rs ON p.status_id=rs.id LEFT JOIN ref_priority rp ON p.priority_id=rp.id WHERE p.ranking IS NULL ORDER BY p.project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;
    }
    exit;
}

// Write operations - require admin auth
startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

switch ($action) {

case 'list':
    $rows = $pdo->query("SELECT p.id, p.project_name, p.ranking, rs.name as status_name, rp.name as priority_name, p.pic FROM projects p LEFT JOIN ref_status rs ON p.status_id=rs.id LEFT JOIN ref_priority rp ON p.priority_id=rp.id WHERE p.ranking IS NOT NULL ORDER BY p.ranking ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $rows]);
    break;

case 'unranked':
    $rows = $pdo->query("SELECT p.id, p.project_name, rs.name as status_name, rp.name as priority_name, p.pic FROM projects p LEFT JOIN ref_status rs ON p.status_id=rs.id LEFT JOIN ref_priority rp ON p.priority_id=rp.id WHERE p.ranking IS NULL ORDER BY p.project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $rows]);
    break;

case 'save':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $ranking = isset($_POST['ranking']) && $_POST['ranking'] !== '' ? (int)$_POST['ranking'] : null;

    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }

    try {
        $stmt = $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?");
        $stmt->execute([$ranking, $id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan ranking']);
    }
    break;

case 'reorder':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

    $order = $_POST['order'] ?? [];
    if (!is_array($order)) { echo json_encode(['ok' => false, 'msg' => 'Invalid order data']); exit; }

    try {
        $rank = 1;
        foreach ($order as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?");
                $stmt->execute([$rank, $id]);
                $rank++;
            }
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal mengurutkan ranking']);
    }
    break;

case 'reorder_all':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

    try {
        $projects = $pdo->query("SELECT id FROM projects ORDER BY CASE WHEN ranking IS NULL THEN 1 ELSE 0 END ASC, ranking ASC, start_date ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $rank = 1;
        foreach ($projects as $pid) {
            $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?")->execute([$rank, $pid]);
            $rank++;
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal mengurutkan ranking']);
    }
    break;

case 'remove':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }

    try {
        $stmt = $pdo->prepare("UPDATE projects SET ranking = NULL WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal menghapus ranking']);
    }
    break;

case 'shift':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $dir = $_POST['direction'] ?? '';
    if ($id <= 0 || !in_array($dir, ['up','down'])) { echo json_encode(['ok' => false, 'msg' => 'Invalid params']); exit; }

    try {
        $current = $pdo->prepare("SELECT ranking FROM projects WHERE id = ?");
        $current->execute([$id]);
        $curRank = $current->fetchColumn();

        if ($curRank === null || $curRank === false) { echo json_encode(['ok' => false, 'msg' => 'Project not ranked']); exit; }

        if ($dir === 'up') {
            $swap = $pdo->prepare("SELECT id FROM projects WHERE ranking = ? - 1");
            $swap->execute([(int)$curRank]);
            $swapId = $swap->fetchColumn();
            if ($swapId) {
                $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?")->execute([(int)$curRank, $swapId]);
                $pdo->prepare("UPDATE projects SET ranking = ? - 1 WHERE id = ?")->execute([(int)$curRank, $id]);
            }
        } else {
            $swap = $pdo->prepare("SELECT id FROM projects WHERE ranking = ? + 1");
            $swap->execute([(int)$curRank]);
            $swapId = $swap->fetchColumn();
            if ($swapId) {
                $pdo->prepare("UPDATE projects SET ranking = ? WHERE id = ?")->execute([(int)$curRank, $swapId]);
                $pdo->prepare("UPDATE projects SET ranking = ? + 1 WHERE id = ?")->execute([(int)$curRank, $id]);
            }
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal menggeser ranking']);
    }
    break;

default:
    echo json_encode(['ok' => false, 'msg' => 'Invalid action']);
}
