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

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
if ($projectId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT image_path FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();

    if ($row && $row['image_path']) {
        $imageFile = __DIR__ . '/../' . parse_url($row['image_path'], PHP_URL_PATH);
        if (file_exists($imageFile)) {
            unlink($imageFile);
        }
    }

    $stmt = $pdo->prepare("UPDATE projects SET image_path = NULL WHERE id = ?");
    $stmt->execute([$projectId]);

    logActivity('delete', 'image', 'Gambar proyek dihapus', $projectId);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menghapus gambar.']);
}
