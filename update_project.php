<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }

$name = trim($_POST['project_name'] ?? '');
if ($name === '') { echo json_encode(['ok' => false, 'msg' => 'Project name is required']); exit; }

$projectCode = trim((string)($_POST['project_code'] ?? '')) ?: null;
$benefitId = isset($_POST['benefit_id']) && $_POST['benefit_id'] !== '' ? (int)$_POST['benefit_id'] : null;
$categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
$statusId = isset($_POST['status_id']) && $_POST['status_id'] !== '' ? (int)$_POST['status_id'] : null;
$responsiblePersonId = isset($_POST['responsible_person_id']) && $_POST['responsible_person_id'] !== '' ? (int)$_POST['responsible_person_id'] : null;
$priorityId = isset($_POST['priority_id']) && $_POST['priority_id'] !== '' ? (int)$_POST['priority_id'] : null;
$ranking = isset($_POST['ranking']) && $_POST['ranking'] !== '' ? (int)$_POST['ranking'] : null;
$startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$monthStart = $startDate ? date('M', strtotime($startDate)) : null;
$targetUnit = isset($_POST['target_unit']) && $_POST['target_unit'] !== '' ? (int)$_POST['target_unit'] : 0;
$completedUnit = isset($_POST['completed_unit']) && $_POST['completed_unit'] !== '' ? (int)$_POST['completed_unit'] : 0;

$hasOrderLinkInput = array_key_exists('project_order_link', $_POST);
$projectOrderLink = trim((string)($_POST['project_order_link'] ?? ''));
if ($hasOrderLinkInput && $projectOrderLink !== '' && !filter_var($projectOrderLink, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'msg' => 'Link Project Order Document tidak valid. Gunakan format http:// atau https://']);
    exit;
}
$hasOrderLinkCol = false;
if ($hasOrderLinkInput) {
    try {
        $hasOrderLinkCol = (bool)$pdo->query("SHOW COLUMNS FROM `projects` LIKE 'project_order_link'")->fetch();
    } catch (Exception $e) {
        $hasOrderLinkCol = false;
    }
}

try {
    $pdo->beginTransaction();

    if ($projectCode !== null) {
        $checkCode = $pdo->prepare('SELECT id FROM projects WHERE project_code = ? AND id <> ? LIMIT 1');
        $checkCode->execute([$projectCode, $id]);
        if ($checkCode->fetchColumn()) {
            throw new RuntimeException('Project code sudah digunakan oleh proyek lain');
        }
    }

    $updateSql = "UPDATE projects SET project_name=?, project_code=?, benefit_id=?, category_id=?, status_id=?, responsible_person_id=?, priority_id=?, ranking=?, start_date=?, month_start=?, target_unit=?, completed_unit=?";
    $updateParams = [$name, $projectCode, $benefitId, $categoryId, $statusId, $responsiblePersonId, $priorityId, $ranking, $startDate, $monthStart, $targetUnit, $completedUnit];
    if ($hasOrderLinkInput && $hasOrderLinkCol) {
        $updateSql .= ", project_order_link=?";
        $updateParams[] = $projectOrderLink === '' ? null : $projectOrderLink;
    }
    $updateSql .= " WHERE id=?";
    $updateParams[] = $id;
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($updateParams);

    // Update Development Analysis (only if data is provided)
    $devProblems = $_POST['dev_problem'] ?? [];
    if (isset($_POST['dev_problem'])) {
        $pdo->prepare("DELETE FROM project_development_analysis WHERE project_id = ?")->execute([$id]);
    }
    $devExisting = $_POST['dev_existing'] ?? [];
    $devExpected = $_POST['dev_expected'] ?? [];
    $devGap = $_POST['dev_gap'] ?? [];
    $devAction = $_POST['dev_action'] ?? [];

    if (!empty($devProblems)) {
        $stmtDev = $pdo->prepare("INSERT INTO project_development_analysis (project_id, row_order, problem_overview, existing_condition, expected_condition, gap, action) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($devProblems); $i++) {
            if (array_filter([
                trim($devProblems[$i] ?? ''), trim($devExisting[$i] ?? ''),
                trim($devExpected[$i] ?? ''), trim($devGap[$i] ?? ''), trim($devAction[$i] ?? '')
            ], function ($value) { return $value !== ''; })) {
                $stmtDev->execute([
                    $id, $i + 1,
                    trim($devProblems[$i] ?? ''), trim($devExisting[$i] ?? ''),
                    trim($devExpected[$i] ?? ''), trim($devGap[$i] ?? ''), trim($devAction[$i] ?? '')
                ]);
            }
        }
    }

    // Update Action Analysis (only if data is provided)
    $actAction = $_POST['act_action'] ?? [];
    if (isset($_POST['act_action'])) {
        $pdo->prepare("DELETE FROM project_action_analysis WHERE project_id = ?")->execute([$id]);
    }
    $actStrengths = $_POST['act_strengths'] ?? [];
    $actWeaknesses = $_POST['act_weaknesses'] ?? [];
    $actOpportunities = $_POST['act_opportunities'] ?? [];
    $actThreats = $_POST['act_threats'] ?? [];

    if (!empty($actAction)) {
        $stmtAct = $pdo->prepare("INSERT INTO project_action_analysis (project_id, row_order, action, strengths, weaknesses, opportunities, threats) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($actAction); $i++) {
            if (array_filter([
                trim($actAction[$i] ?? ''), trim($actStrengths[$i] ?? ''),
                trim($actWeaknesses[$i] ?? ''), trim($actOpportunities[$i] ?? ''), trim($actThreats[$i] ?? '')
            ], function ($value) { return $value !== ''; })) {
                $stmtAct->execute([
                    $id, $i + 1,
                    trim($actAction[$i] ?? ''), trim($actStrengths[$i] ?? ''),
                    trim($actWeaknesses[$i] ?? ''), trim($actOpportunities[$i] ?? ''), trim($actThreats[$i] ?? '')
                ]);
            }
        }
    }

    // Update Planned Price (only if data is provided)
    $priceRequirement = $_POST['price_requirement'] ?? [];
    if (isset($_POST['price_requirement'])) {
        $pdo->prepare("DELETE FROM project_planned_price WHERE project_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cost_breakdown WHERE project_id = ?")->execute([$id]);
    }
    $pricePrNumber = $_POST['price_pr_number'] ?? [];
    $priceQuantity = $_POST['price_quantity'] ?? [];
    $priceUnit = $_POST['price_unit'] ?? [];
    $priceUnitPrice = $_POST['price_unit_price'] ?? [];
    $priceTotalCost = $_POST['price_total_cost'] ?? [];

    if (!empty($priceRequirement)) {
        $stmtPrice = $pdo->prepare("INSERT INTO project_planned_price (project_id, row_order, requirement, pr_number, quantity, unit, unit_price, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtCb = $pdo->prepare("INSERT INTO cost_breakdown (project_id, item_description, unit, planned_price, planned_quantity, planned_cost, row_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($priceRequirement); $i++) {
            if (!empty(trim($priceRequirement[$i] ?? ''))) {
                $req = trim($priceRequirement[$i] ?? '');
                $qty = (float)($priceQuantity[$i] ?? 0);
                $unit = trim($priceUnit[$i] ?? '');
                $up = (float)($priceUnitPrice[$i] ?? 0);
                $tc = (float)($priceTotalCost[$i] ?? 0);
                $stmtPrice->execute([
                    $id, $i + 1,
                    $req, trim($pricePrNumber[$i] ?? ''),
                    $qty, $unit, $up, $tc
                ]);
                $stmtCb->execute([
                    $id, $req, $unit, $up, $qty, $tc ?: ($up * $qty), $i + 1
                ]);
            }
        }
    }

    // Update Planning Timeline (only if data is provided)
    $planTimeline = $_POST['plan_timeline'] ?? [];
    if (isset($_POST['plan_timeline'])) {
        $pdo->prepare("DELETE FROM project_planning_timeline WHERE project_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_activities WHERE project_id = ? AND row_order < 0")->execute([$id]);
    }
    $planMilestoneId = $_POST['plan_milestone_id'] ?? [];
    $planMilestone = $_POST['plan_milestone'] ?? [];
    $planPic = $_POST['plan_pic'] ?? [];
    $planStartDate = $_POST['plan_start_date'] ?? [];
    $planDuration = $_POST['plan_duration'] ?? [];

    if (!empty($planTimeline)) {
        $stmtPlan = $pdo->prepare("INSERT INTO project_planning_timeline (project_id, row_order, timeline, milestone, pic, start_date, estimated_duration) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtActivity = $pdo->prepare("INSERT INTO project_activities (project_id, milestone_id, pic, date, timeline, remarks, estimated_duration, row_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtMilestone = $pdo->prepare("SELECT id FROM ref_milestone WHERE name = ? LIMIT 1");
        for ($i = 0; $i < count($planTimeline); $i++) {
            if (!empty(trim($planTimeline[$i] ?? ''))) {
                $milestoneName = trim($planMilestone[$i] ?? '');
                $stmtMilestone->execute([$milestoneName]);
                $milestoneId = $stmtMilestone->fetchColumn() ?: null;
                if (!$milestoneId) {
                    throw new RuntimeException('Milestone wajib dipilih untuk setiap Planning Timeline');
                }
                $planDate = !empty($planStartDate[$i]) ? $planStartDate[$i] : null;
                $duration = trim($planDuration[$i] ?? '');
                $stmtPlan->execute([
                    $id, $i + 1,
                    trim($planTimeline[$i] ?? ''), $milestoneName,
                    trim($planPic[$i] ?? '') ?: null,
                    $planDate, $duration
                ]);
                $stmtActivity->execute([
                    $id, $milestoneId, trim($planPic[$i] ?? '') ?: null,
                    $planDate, trim($planTimeline[$i] ?? ''), null,
                    is_numeric($duration) ? (int)$duration : null, -($i + 1)
                ]);
            }
        }
    }

    $pdo->commit();
    logActivity('update', 'project', 'Proyek diperbarui: ' . $name, $id);
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update_project error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Gagal memperbarui proyek: ' . $e->getMessage()]);
}
