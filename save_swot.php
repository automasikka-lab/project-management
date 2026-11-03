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

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

$fields = ['problem_overview','existing_condition','expected_condition','gap','action_plan','action_taken','strengths','weaknesses','opportunities','threats'];

// Build UPDATE query
$setClauses = [];
$params = [':id' => $projectId];
foreach ($fields as $f) {
    $val = $_POST[$f] ?? null;
    $setClauses[] = "$f = :$f";
    $params[":$f"] = $val !== null && $val !== '' ? $val : null;
}

$sql = "UPDATE projects SET " . implode(', ', $setClauses) . " WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    logActivity('update', 'swot', 'Data SWOT disimpan', $projectId);
    echo json_encode(['ok' => true, 'msg' => 'Data saved successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan data']);
}
?>
