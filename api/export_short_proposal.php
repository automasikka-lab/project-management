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
    $benefitName = '';
    if (!empty($project['benefit_id'])) {
        $s = $pdo->prepare("SELECT name FROM ref_benefit WHERE id = ?");
        $s->execute([$project['benefit_id']]);
        $benefitName = $s->fetchColumn() ?: '';
    }
    $categoryName = '';
    if (!empty($project['category_id'])) {
        $s = $pdo->prepare("SELECT name FROM ref_category WHERE id = ?");
        $s->execute([$project['category_id']]);
        $categoryName = $s->fetchColumn() ?: '';
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

    $templatePath = __DIR__ . '/../00 MASTER SHORT PROPOSAL.xlsx';
    if (!file_exists($templatePath)) {
        echo json_encode(['ok' => false, 'msg' => 'Template file not found']);
        exit;
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('MASTER');
    if (!$sheet) {
        echo json_encode(['ok' => false, 'msg' => 'Sheet MASTER not found']);
        exit;
    }

    // Header: label di D4:D11, value di E4:E11
    $sheet->setCellValue('E4', date('d M Y', strtotime($project['created_at'])));
    $sheet->setCellValue('E5', $project['project_code'] ?? '');
    $sheet->setCellValue('E6', $project['project_name'] ?? '');
    $sheet->setCellValue('E7', $responsibleName ?: '-');
    $sheet->setCellValue('E8', $priorityName ?: '-');
    $sheet->setCellValue('E9', $benefitName ?: '-');
    $sheet->setCellValue('E10', $categoryName ?: '-');
    $sheet->setCellValue('E11', $statusName ?: '-');
    $sheet->getStyle('E11')->getFont()->setBold(true)->setSize(16);

    // Prioritas: bold number yang dipilih
    $priorityMap = [
        'given priority' => 1,
        'scheduled' => 3,
        'delegated' => 2,
        'pending' => 4,
    ];
    $priorityNum = $priorityMap[strtolower($priorityName)] ?? 0;
    if ($priorityNum > 0) {
        $col = chr(68 + $priorityNum);
        $sheet->getStyle($col . '9')->getFont()->setBold(true)->setSize(20);
    }

    // Status: bold Open atau Close
    $isOpen = in_array(strtolower($statusName), ['open expansion', 'open research', 'open']);
    $sheet->getStyle('E15')->getFont()->setBold($isOpen);
    $sheet->getStyle('F15')->getFont()->setBold(!$isOpen);

    // 1.1 Development Analysis: baris 16, kolom D-H
    $sheet->setCellValue('D16', $project['problem_overview'] ?? '');
    $sheet->setCellValue('E16', $project['existing_condition'] ?? '');
    $sheet->setCellValue('F16', $project['expected_condition'] ?? '');
    $sheet->setCellValue('G16', $project['gap'] ?? '');
    $sheet->setCellValue('H16', $project['action_plan'] ?? '');

    // 1.2 Action Analysis: baris 20, kolom D-H
    $sheet->setCellValue('D20', $project['action_taken'] ?? '');
    $sheet->setCellValue('E20', $project['strengths'] ?? '');
    $sheet->setCellValue('F20', $project['weaknesses'] ?? '');
    $sheet->setCellValue('G20', $project['opportunities'] ?? '');
    $sheet->setCellValue('H20', $project['threats'] ?? '');

    // 2.1 Budget Plan: baris 25-34, kolom D=Item, E=PR Number, F=Quantity Plan, G=Price Plan, H=Cost Plan
    $rabItems = !empty($costBreakdown) ? $costBreakdown : [];
    foreach ($rabItems as $i => $item) {
        $row = 25 + $i;
        if ($row > 34) break;
        $desc = $item['item_description'] ?? '';
        if (trim($desc) === '' || $desc === '-') continue;
        $pr = $item['pr_number'] ?? '';
        $qty = $item['planned_quantity'] ?? 0;
        $unit = $item['unit'] ?? '';
        $price = $item['planned_price'] ?? 0;
        $cost = $item['planned_cost'] ?? ($qty * $price);
        $sheet->setCellValue('D' . $row, $desc);
        $sheet->setCellValue('E' . $row, $pr);
        $sheet->setCellValue('F' . $row, (float)$qty);
        $sheet->setCellValue('G' . $row, (float)$price);
        $sheet->setCellValue('H' . $row, (float)$cost);
    }

    // Total RAB di E36 (sudah ada formula di template, overwrite dengan SUM)
    $sheet->setCellValue('E36', '=SUM(H25:H35)');

    // 2.2 Planning Timeline: baris 40-44, kolom D=Timeline, E=Plan Duration, F=Start, G=End, H=PIC
    foreach ($planningTimeline as $i => $tl) {
        $row = 40 + $i;
        if ($row > 44) break;
        $sheet->setCellValue('D' . $row, $tl['timeline'] ?? '');
        $durasi = $tl['estimated_duration'] ?? '';
        $sheet->setCellValue('E' . $row, $durasi ? $durasi . ' HARI' : '');
        $startDate = $tl['start_date'] ?? null;
        if ($startDate) {
            $durasiInt = (int)$durasi;
            $sheet->setCellValue('F' . $row, date('d M Y', strtotime($startDate)));
            $sheet->setCellValue('G' . $row, $durasiInt > 0
                ? date('d M Y', strtotime($startDate . ' + ' . $durasiInt . ' days'))
                : date('d M Y', strtotime($startDate)));
        }
        $sheet->setCellValue('H' . $row, $tl['pic'] ?? '');
    }

    // 3.1 Implementation Timeline: baris 51-60, kolom D=Timeline, E=Actual Duration, F=Start, G=End, H=PIC
    // Bangun lookup start_date dari planning_timeline
    $planningStartMap = [];
    foreach ($planningTimeline as $ptl) {
        $tlName = trim($ptl['timeline'] ?? '');
        if ($tlName !== '' && !isset($planningStartMap[$tlName])) {
            $planningStartMap[$tlName] = $ptl['start_date'] ?? null;
        }
    }
    $execActivities = array_filter($activities, fn($a) => (int)($a['row_order'] ?? 0) >= 0);
    $execActivities = array_values($execActivities);
    foreach ($execActivities as $i => $act) {
        $row = 51 + $i;
        if ($row > 60) break;
        $sheet->setCellValue('D' . $row, $act['timeline'] ?? $act['timeline_name'] ?? '');

        // Cari start_date dari planning_timeline berdasarkan nama timeline
        $actTimeline = trim($act['timeline'] ?? '');
        $startDate = $planningStartMap[$actTimeline] ?? $act['date'] ?? null;
        $verifiedDate = $act['verified_date'] ?? null;

        // Actual Duration = selisih hari antara verified_date dan start_date
        if ($startDate && $verifiedDate) {
            $diff = (int)round((strtotime($verifiedDate) - strtotime($startDate)) / 86400);
            $sheet->setCellValue('E' . $row, $diff > 0 ? $diff : 1);
        } else {
            $sheet->setCellValue('E' . $row, $act['estimated_duration'] ?? '');
        }

        // Start dari planning_timeline
        if ($startDate) {
            $sheet->setCellValue('F' . $row, date('d M Y', strtotime($startDate)));
        }

        // End dari verified_date
        if ($verifiedDate) {
            $sheet->setCellValue('G' . $row, date('d M Y', strtotime($verifiedDate)));
        }

        $sheet->setCellValue('H' . $row, $act['pic'] ?? '');
    }

    // 3.2 Implementation Data: foto Before & After
    // Before: placeholder (diisi user setelah download)
    // After: link gambar dari projects.image_path
    if (!empty($project['image_path'])) {
        $imageUrl = $project['image_path'];
        $sheet->setCellValue('C68', $imageUrl);
        $sheet->getCell('C68')->getHyperlink()->setUrl($imageUrl);
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'short_proposal_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tmpFile);

    $filename = 'Short_Proposal_' . ($project['project_name'] ?: $id) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Export failed: ' . $e->getMessage()]);
}
