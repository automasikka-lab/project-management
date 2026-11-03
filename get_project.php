<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Data not found']); exit; }
    
    // Get Development Analysis
    $row['development_analysis'] = [];
    try {
        $stmtDev = $pdo->prepare("SELECT * FROM project_development_analysis WHERE project_id = ? ORDER BY row_order ASC");
        $stmtDev->execute([$id]);
        $row['development_analysis'] = $stmtDev->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    
    // Get Action Analysis
    $row['action_analysis'] = [];
    try {
        $stmtAct = $pdo->prepare("SELECT * FROM project_action_analysis WHERE project_id = ? ORDER BY row_order ASC");
        $stmtAct->execute([$id]);
        $row['action_analysis'] = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    
    // Get Planned Price
    $row['planned_price'] = [];
    try {
        $stmtPrice = $pdo->prepare("SELECT * FROM project_planned_price WHERE project_id = ? ORDER BY row_order ASC");
        $stmtPrice->execute([$id]);
        $row['planned_price'] = $stmtPrice->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    
    // Get Planning Timeline
    $row['planning_timeline'] = [];
    try {
        $stmtPlan = $pdo->prepare("SELECT * FROM project_planning_timeline WHERE project_id = ? ORDER BY row_order ASC");
        $stmtPlan->execute([$id]);
        $row['planning_timeline'] = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // Older databases may contain the planning rows only in Activity Timeline.
    // Read only rows marked by the project save flow; manual activities stay separate.
    if (empty($row['planning_timeline'])) {
        try {
            $stmtPlanActivity = $pdo->prepare(
                "SELECT pa.timeline, rm.name AS milestone, pa.pic, pa.date AS start_date,
                        pa.estimated_duration, ABS(pa.row_order) AS row_order
                 FROM project_activities pa
                 LEFT JOIN ref_milestone rm ON rm.id = pa.milestone_id
                 WHERE pa.project_id = ? AND pa.row_order < 0
                 ORDER BY pa.row_order DESC"
            );
            $stmtPlanActivity->execute([$id]);
            $row['planning_timeline'] = $stmtPlanActivity->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    // Preserve legacy SWOT values when the normalized analysis tables are empty.
    if (empty($row['development_analysis'])) {
        $legacyDevelopment = trim((string)($row['problem_overview'] ?? '')) !== ''
            || trim((string)($row['existing_condition'] ?? '')) !== ''
            || trim((string)($row['expected_condition'] ?? '')) !== ''
            || trim((string)($row['gap'] ?? '')) !== ''
            || trim((string)($row['action_plan'] ?? '')) !== '';
        if ($legacyDevelopment) {
            $row['development_analysis'] = [[
                'problem_overview' => $row['problem_overview'] ?? '',
                'existing_condition' => $row['existing_condition'] ?? '',
                'expected_condition' => $row['expected_condition'] ?? '',
                'gap' => $row['gap'] ?? '',
                'action' => $row['action_plan'] ?? ''
            ]];
        }
    }
    if (empty($row['action_analysis'])) {
        $legacyAction = trim((string)($row['action_taken'] ?? '')) !== ''
            || trim((string)($row['strengths'] ?? '')) !== ''
            || trim((string)($row['weaknesses'] ?? '')) !== ''
            || trim((string)($row['opportunities'] ?? '')) !== ''
            || trim((string)($row['threats'] ?? '')) !== '';
        if ($legacyAction) {
            $row['action_analysis'] = [[
                'action' => $row['action_taken'] ?? '',
                'strengths' => $row['strengths'] ?? '',
                'weaknesses' => $row['weaknesses'] ?? '',
                'opportunities' => $row['opportunities'] ?? '',
                'threats' => $row['threats'] ?? ''
            ]];
        }
    }
    
    echo json_encode(['ok' => true, 'data' => $row]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal memuat data proyek']);
}
