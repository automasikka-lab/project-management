<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

$projectId = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');
$value = trim((string)($_POST['value'] ?? ''));

$allowed = ['project_order_link'];
if ($projectId <= 0 || !in_array($field, $allowed, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'msg' => 'Link tidak valid. Gunakan format http:// atau https://']);
    exit;
}

if (strlen($value) > 500) {
    echo json_encode(['ok' => false, 'msg' => 'Link terlalu panjang (maks 500 karakter)']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `projects` SET `$field` = ? WHERE `id` = ?");
    $stmt->execute([$value === '' ? null : $value, $projectId]);

    logActivity('update', 'project', 'Update ' . $field . ' project #' . $projectId, $projectId, null, $value);
    echo json_encode(['ok' => true, 'value' => $value]);
} catch (Exception $e) {
    error_log('update_doc_link error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan. Pastikan migration sql/migration.sql sudah dijalankan.']);
}
