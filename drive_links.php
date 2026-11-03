<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$allowedLinkTypes = ['proposal', 'project_order'];
$linkType = $_POST['link_type'] ?? $_GET['link_type'] ?? 'proposal';
if (!in_array($linkType, $allowedLinkTypes, true)) {
    $linkType = 'proposal';
}

// Pastikan kolom link_type ada (bila database belum kena migration.sql).
$ltColCheck = qRows($pdo, "SHOW COLUMNS FROM `project_drive_links` LIKE 'link_type'");
if (empty($ltColCheck)) {
    $pdo->exec("ALTER TABLE `project_drive_links` ADD COLUMN `link_type` VARCHAR(20) NOT NULL DEFAULT 'proposal' AFTER `label`");
}

switch ($action) {
    case 'get':
        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($projectId <= 0) { echo json_encode(['ok' => false, 'error' => 'Invalid project_id']); exit; }
        $links = qRows(
            $pdo,
            "SELECT id, drive_url, label, link_type
             FROM project_drive_links
             WHERE project_id = :pid AND link_type = :lt
             ORDER BY id ASC",
            [':pid' => $projectId, ':lt' => $linkType]
        );
        echo json_encode(['ok' => true, 'links' => $links]);
        break;

    case 'save':
        requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only
        $projectId = (int)($_POST['project_id'] ?? 0);
        $urls = $_POST['urls'] ?? [];
        $labels = $_POST['labels'] ?? [];
        if ($projectId <= 0) { echo json_encode(['ok' => false, 'error' => 'Invalid project_id']); exit; }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM project_drive_links WHERE project_id = ? AND link_type = ?")
                ->execute([$projectId, $linkType]);
            $stmt = $pdo->prepare("INSERT INTO project_drive_links (project_id, drive_url, label, link_type) VALUES (?, ?, ?, ?)");
            $saved = 0;
            foreach ($urls as $i => $url) {
                $url = trim($url);
                if ($url === '') continue;
                $label = trim($labels[$i] ?? '');
                $stmt->execute([$projectId, $url, $label, $linkType]);
                $saved++;
            }
            $pdo->commit();
            echo json_encode(['ok' => true, 'saved' => $saved, 'link_type' => $linkType]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
}
