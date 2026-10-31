<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, rs.name AS status_name, u.name AS user_name, rp.name AS responsible_name, rp.name AS responsible_person, pr.name AS priority_name
        FROM projects p
        LEFT JOIN ref_status rs ON p.status_id = rs.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN users rp ON p.responsible_person_id = rp.id
        LEFT JOIN ref_priority pr ON p.priority_id = pr.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo json_encode(['ok' => false, 'msg' => 'Project not found']);
        exit;
    }

    $templatePath = __DIR__ . '/../00 FM-ENG-005 Project Order.xlsx';
    if (!file_exists($templatePath)) {
        echo json_encode(['ok' => false, 'msg' => 'Template file not found']);
        exit;
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('Master UPDATE');
    if (!$sheet) {
        $sheet = $spreadsheet->getActiveSheet();
    }

    $isUrgent = in_array(strtolower($project['priority_name'] ?? ''), ['given priority', 'urgent']);

    $sheet->setCellValue('C9', $project['project_code'] ?? '');
    $sheet->setCellValue('C12', $project['project_name'] ?? '');
    $sheet->setCellValue('C15', $project['user_name'] ?: ($project['responsible_person'] ?? '-'));
    if ($isUrgent) {
        $sheet->setCellValue('O18', 'V');
        $sheet->setCellValue('AB18', '');
    } else {
        $sheet->setCellValue('O18', '');
        $sheet->setCellValue('AB18', 'V');
    }

    $background = $project['problem_overview'] ?? '';
    $sheet->setCellValue('C24', $background);

    $descriptionParts = [];
    if (!empty($project['existing_condition'])) $descriptionParts[] = "Existing Condition:\n" . $project['existing_condition'];
    if (!empty($project['expected_condition'])) $descriptionParts[] = "Expected Condition:\n" . $project['expected_condition'];
    if (!empty($project['gap'])) $descriptionParts[] = "Gap:\n" . $project['gap'];
    if (!empty($project['action_plan'])) $descriptionParts[] = "Action Plan:\n" . $project['action_plan'];
    if (!empty($project['action_taken'])) $descriptionParts[] = "Action Taken:\n" . $project['action_taken'];
    $sheet->setCellValue('C39', implode("\n\n", $descriptionParts));

    $sheet->setCellValue('BA9', $project['project_code'] ?? '');
    $sheet->setCellValue('BA12', $project['project_name'] ?? '');
    $sheet->setCellValue('BA15', $project['user_name'] ?: ($project['responsible_person'] ?? '-'));
    if ($isUrgent) {
        $sheet->setCellValue('BM18', 'V');
        $sheet->setCellValue('BZ18', '');
    } else {
        $sheet->setCellValue('BM18', '');
        $sheet->setCellValue('BZ18', 'V');
    }
    $sheet->setCellValue('BA24', $background);
    $sheet->setCellValue('BA39', implode("\n\n", $descriptionParts));

    $tmpFile = tempnam(sys_get_temp_dir(), 'project_order_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tmpFile);

    $filename = 'Project_Order_' . ($project['project_name'] ?: $id) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Export failed: ' . $e->getMessage()]);
}
