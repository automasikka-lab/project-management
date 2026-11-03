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

$name = trim($_POST['name'] ?? '');
$position = trim($_POST['position'] ?? '');
$roleType = trim($_POST['role_type'] ?? 'PIC');

if ($name === '') {
    echo json_encode(['ok' => false, 'msg' => 'Name is required']);
    exit;
}

$validRoles = ['PIC', 'Responsible Person', 'Responsible Person / PIC'];
if (!in_array($roleType, $validRoles)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid role type']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, position, role_type) VALUES (?, ?, ?)");
    $stmt->execute([$name, $position ?: null, $roleType]);
    $userId = $pdo->lastInsertId();
    logActivity('create', 'user', 'Pengguna baru ditambahkan: ' . $name, $userId);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan pengguna']);
}
