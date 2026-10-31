<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    $count = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    echo json_encode(['ok' => true, 'count' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'count' => 0, 'msg' => 'Gagal mengambil data']);
}
