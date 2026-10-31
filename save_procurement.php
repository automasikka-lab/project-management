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
$status = trim($_POST['procurement_status'] ?? '');

if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

$valid = ['', 'PR Pending', 'Quotation Pending', 'PO Pending'];
if (!in_array($status, $valid)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid status']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE projects SET procurement_status = :ps WHERE id = :id");
    $stmt->execute([':ps' => $status !== '' ? $status : null, ':id' => $projectId]);
    echo json_encode(['ok' => true, 'msg' => 'Procurement status updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Gagal memperbarui status procurement']);
}
?>
