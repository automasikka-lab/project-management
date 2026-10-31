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
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo json_encode(['ok' => false, 'msg' => 'Project not found']);
        exit;
    }

    $statusName = '';
    if (!empty($project['status_id'])) {
        $s = $pdo->prepare("SELECT name FROM ref_status WHERE id = ?");
        $s->execute([$project['status_id']]);
        $statusName = $s->fetchColumn() ?: '';
    }
    $priorityName = '';
    if (!empty($project['priority_id'])) {
        $s = $pdo->prepare("SELECT name FROM ref_priority WHERE id = ?");
        $s->execute([$project['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }
    $userName = '';
    if (!empty($project['user_id'])) {
        $s = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $s->execute([$project['user_id']]);
        $userName = $s->fetchColumn() ?: '';
    }
    $responsibleName = '';
    if (!empty($project['responsible_person_id'])) {
        $s = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $s->execute([$project['responsible_person_id']]);
        $responsibleName = $s->fetchColumn() ?: '';
    }

    $costBreakdown = [];
    $stmtCB = $pdo->prepare("SELECT * FROM cost_breakdown WHERE project_id = ? ORDER BY row_order ASC");
    $stmtCB->execute([$id]);
    $costBreakdown = $stmtCB->fetchAll(PDO::FETCH_ASSOC);

    $planningTimeline = [];
    $stmtPT = $pdo->prepare("SELECT * FROM project_planning_timeline WHERE project_id = ? ORDER BY row_order ASC");
    $stmtPT->execute([$id]);
    $planningTimeline = $stmtPT->fetchAll(PDO::FETCH_ASSOC);

    if (empty($planningTimeline)) {
        $stmtPT2 = $pdo->prepare(
            "SELECT pa.timeline, rm.name AS milestone_name, pa.pic, pa.date AS start_date,
                    pa.estimated_duration, ABS(pa.row_order) AS row_order
             FROM project_activities pa
             LEFT JOIN ref_milestone rm ON rm.id = pa.milestone_id
             WHERE pa.project_id = ? AND (pa.row_order < 0 OR rm.name IN ('Project Desain', 'Budgeting', 'Timeline Desain'))
             ORDER BY ABS(pa.row_order) ASC, pa.date ASC"
        );
        $stmtPT2->execute([$id]);
        $planningTimeline = $stmtPT2->fetchAll(PDO::FETCH_ASSOC);
    }

    $activities = [];
    $stmtAct = $pdo->prepare(
        "SELECT pa.*, COALESCE(rm.name, 'Other') AS timeline_name
         FROM project_activities pa
         LEFT JOIN ref_milestone rm ON pa.milestone_id = rm.id
         WHERE pa.project_id = ?
         ORDER BY COALESCE(rm.sort_order, 999) ASC, pa.row_order ASC, pa.date ASC"
    );
    $stmtAct->execute([$id]);
    $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $templatePath = __DIR__ . '/../00 MASTER LONG PROPOSAL.xlsx';
    if (!file_exists($templatePath)) {
        echo json_encode(['ok' => false, 'msg' => 'Template file not found']);
        exit;
    }

    $spreadsheet = IOFactory::load($templatePath);

    $sheetCover = $spreadsheet->getSheetByName('Cover');
    if ($sheetCover) {
        $sheetCover->setCellValue('A41', "\nJudul Project: " . ($project['project_name'] ?? '') . "\n\nTujuan Project: " . ($project['problem_overview'] ?? ''));
        $sheetCover->setCellValue('C41', "Nama Mill: " . ($responsibleName ?: '-'));
    }

    $sheetIsi = $spreadsheet->getSheetByName('Isi');
    if ($sheetIsi) {
        $sheetIsi->setCellValue('B4', $project['problem_overview'] ?? '');
        $sheetIsi->setCellValue('B7', ($project['existing_condition'] ?? '') . "\n\n" . ($project['expected_condition'] ?? '') . "\n\n" . ($project['gap'] ?? ''));

        $sheetIsi->setCellValue('C13', $project['problem_overview'] ?? '');
        $sheetIsi->setCellValue('D13', $project['existing_condition'] ?? '');
        $sheetIsi->setCellValue('F13', $project['expected_condition'] ?? '');
        $sheetIsi->setCellValue('G13', $project['gap'] ?? '');
        $sheetIsi->setCellValue('H13', $project['action_plan'] ?? '');

        $sheetIsi->setCellValue('C18', $project['action_taken'] ?? '');
        $sheetIsi->setCellValue('D18', $project['strengths'] ?? '');
        $sheetIsi->setCellValue('F18', $project['weaknesses'] ?? '');
        $sheetIsi->setCellValue('G18', $project['opportunities'] ?? '');
        $sheetIsi->setCellValue('H18', $project['threats'] ?? '');

        $rabItems = !empty($costBreakdown) ? $costBreakdown : [];
        foreach ($rabItems as $i => $item) {
            $row = 26 + $i;
            if ($row > 33) break;
            $desc = $item['item_description'] ?? '';
            if (trim($desc) === '' || $desc === '-') continue;
            $qty = $item['planned_quantity'] ?? 0;
            $unit = $item['unit'] ?? '';
            $price = $item['planned_price'] ?? 0;
            $cost = $item['planned_cost'] ?? ($qty * $price);
            $sheetIsi->setCellValue('C' . $row, $desc);
            $sheetIsi->setCellValue('D' . $row, $qty);
            $sheetIsi->setCellValue('E' . $row, $unit);
            $sheetIsi->setCellValue('H' . $row, $cost);
        }

        $sheetIsi->setCellValue('D34', '=SUM(H26:H33)');

        foreach ($planningTimeline as $i => $tl) {
            $row = 78 + $i;
            if ($row > 80) break;
            $sheetIsi->setCellValue('C' . $row, $tl['timeline'] ?? '');
            $sheetIsi->setCellValue('D' . $row, $tl['estimated_duration'] ?? '');
            $startDate = $tl['start_date'] ?? null;
            if ($startDate) {
                $durasi = (int)($tl['estimated_duration'] ?? 0);
                $sheetIsi->setCellValue('F' . $row, date('d M Y', strtotime($startDate)));
                $sheetIsi->setCellValue('G' . $row, $durasi > 0
                    ? date('d M Y', strtotime($startDate . ' + ' . $durasi . ' days'))
                    : date('d M Y', strtotime($startDate)));
            }
            $sheetIsi->setCellValue('H' . $row, $tl['pic'] ?? '');
        }
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'long_proposal_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tmpFile);

    $filename = 'Long_Proposal_' . ($project['project_name'] ?: $id) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Export failed: ' . $e->getMessage()]);
}
