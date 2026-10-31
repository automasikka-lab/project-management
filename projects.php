<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Projects';
$page_icon = 'folder';
$isAdmin = isAdminLoggedIn();
include 'includes/header.php';

$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$rp = $_GET['rp'] ?? '';
$month = $_GET['month'] ?? '';
$priority = $_GET['priority'] ?? '';
$benefit = $_GET['benefit'] ?? '';
$search = $_GET['search'] ?? '';
$stagnant = $_GET['stagnant'] ?? '';
$expansion = $_GET['expansion'] ?? '';
$research = $_GET['research'] ?? '';
$pendingDays = $_GET['pending_days'] ?? '';
$budgetStatus = $_GET['budget_status'] ?? '';
$procurementUnverified = $_GET['procurement_unverified'] ?? '';
$sort = $_GET['sort'] ?? 'ranking_asc';
$minBudget = $_GET['min_budget'] ?? '';
$maxBudget = $_GET['max_budget'] ?? '';
$minCost = $_GET['min_cost'] ?? '';
$maxCost = $_GET['max_cost'] ?? '';

// Normalize multi-select filters (accept array or comma-separated string)
function normalizeMulti($val) {
    if (is_array($val)) {
        return array_values(array_filter(array_map('trim', $val), fn($v) => $v !== ''));
    }
    if (is_string($val) && $val !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $val)), fn($v) => $v !== ''));
    }
    return [];
}
$statusArr = normalizeMulti($status);
$categoryArr = normalizeMulti($category);
$rpArr = normalizeMulti($rp);
$priorityArr = normalizeMulti($priority);
$benefitArr = normalizeMulti($benefit);
$pendingDaysArr = normalizeMulti($pendingDays);
$budgetStatusArr = normalizeMulti($budgetStatus);

// Check if 'verified' column exists in project_activities
$hasVerified = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM project_activities LIKE 'verified'")->fetch();
    $hasVerified = !empty($colCheck);
} catch (Exception $e) {
    $hasVerified = false;
}

$params = [];
if ($hasVerified) {
    $sql = "SELECT v.*,
        (SELECT rs.name FROM ref_status rs INNER JOIN projects pp ON pp.status_id = rs.id WHERE pp.id = v.id) as real_status_name,
        (SELECT rp.name FROM ref_priority rp INNER JOIN projects pp ON pp.priority_id = rp.id WHERE pp.id = v.id) as real_priority_name,
        (SELECT u.name FROM users u INNER JOIN projects pp ON pp.responsible_person_id = u.id WHERE pp.id = v.id) as real_rp_name,
        IFNULL(la.cnt, 0) as launching_verified,
        IFNULL(m1.tot, 0) as ms_1_total, IFNULL(m1.ver, 0) as ms_1,
        IFNULL(m2.tot, 0) as ms_2_total, IFNULL(m2.ver, 0) as ms_2,
        IFNULL(m3.tot, 0) as ms_3_total, IFNULL(m3.ver, 0) as ms_3,
        IFNULL(m4.tot, 0) as ms_4_total, IFNULL(m4.ver, 0) as ms_4,
        IFNULL(m5.tot, 0) as ms_5_total, IFNULL(m5.ver, 0) as ms_5,
        IFNULL(m8.tot, 0) as ms_8_total, IFNULL(m8.ver, 0) as ms_8,
        IFNULL(m9.tot, 0) as ms_9_total, IFNULL(m9.ver, 0) as ms_9,
        IFNULL(m10.tot, 0) as ms_10_total, IFNULL(m10.ver, 0) as ms_10,
        (SELECT pa.timeline FROM project_activities pa WHERE pa.project_id = v.id AND pa.verified = 1 AND pa.verified_date IS NOT NULL ORDER BY pa.verified_date DESC, pa.id DESC LIMIT 1) as last_activity_timeline,
        (SELECT pa.pic FROM project_activities pa WHERE pa.project_id = v.id AND pa.verified = 1 AND pa.verified_date IS NOT NULL ORDER BY pa.verified_date DESC, pa.id DESC LIMIT 1) as last_activity_pic,
        (SELECT pa.verified_date FROM project_activities pa WHERE pa.project_id = v.id AND pa.verified = 1 AND pa.verified_date IS NOT NULL ORDER BY pa.verified_date DESC, pa.id DESC LIMIT 1) as last_activity_date,
        v.end_date as computed_end_date,
        (SELECT SUM(DATEDIFF(pa.verified_date, pa.date)) FROM project_activities pa WHERE pa.project_id = v.id AND pa.verified = 1 AND pa.verified_date IS NOT NULL AND pa.date IS NOT NULL) as computed_duration,
        (SELECT SUM(pa.estimated_duration) FROM project_activities pa WHERE pa.project_id = v.id AND pa.estimated_duration IS NOT NULL) as computed_est_duration,
        COALESCE((SELECT SUM(cb2.planned_cost) FROM cost_breakdown cb2 WHERE cb2.project_id = v.id), 0) as computed_budget,
        COALESCE((SELECT SUM(cb2.actual_cost) FROM cost_breakdown cb2 WHERE cb2.project_id = v.id), 0) as computed_cost,
        p.ranking
        FROM v_project_full v
        LEFT JOIN projects p ON p.id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as cnt FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 10 AND pa.verified = 1 GROUP BY pa.project_id) la ON la.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 1 GROUP BY pa.project_id) m1 ON m1.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 2 GROUP BY pa.project_id) m2 ON m2.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 3 GROUP BY pa.project_id) m3 ON m3.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 4 GROUP BY pa.project_id) m4 ON m4.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 5 GROUP BY pa.project_id) m5 ON m5.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 8 GROUP BY pa.project_id) m8 ON m8.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 9 GROUP BY pa.project_id) m9 ON m9.project_id = v.id
        LEFT JOIN (SELECT pa.project_id, COUNT(*) as tot, SUM(pa.verified = 1) as ver FROM project_activities pa INNER JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.milestone_id = 10 GROUP BY pa.project_id) m10 ON m10.project_id = v.id
        WHERE 1=1";
} else {
    $sql = "SELECT v.*, (SELECT rs.name FROM ref_status rs INNER JOIN projects pp ON pp.status_id = rs.id WHERE pp.id = v.id) as real_status_name, (SELECT rp.name FROM ref_priority rp INNER JOIN projects pp ON pp.priority_id = rp.id WHERE pp.id = v.id) as real_priority_name, (SELECT u.name FROM users u INNER JOIN projects pp ON pp.responsible_person_id = u.id WHERE pp.id = v.id) as real_rp_name, 0 as launching_verified, 0 as ms_1_total, 0 as ms_1, 0 as ms_2_total, 0 as ms_2, 0 as ms_3_total, 0 as ms_3, 0 as ms_4_total, 0 as ms_4, 0 as ms_5_total, 0 as ms_5, 0 as ms_8_total, 0 as ms_8, 0 as ms_9_total, 0 as ms_9, 0 as ms_10_total, 0 as ms_10, NULL as last_activity_timeline, NULL as last_activity_pic, NULL as last_activity_date, NULL as computed_end_date, (SELECT SUM(DATEDIFF(pa.verified_date, pa.date)) FROM project_activities pa WHERE pa.project_id = v.id AND pa.verified = 1 AND pa.verified_date IS NOT NULL AND pa.date IS NOT NULL) as computed_duration, (SELECT SUM(pa.estimated_duration) FROM project_activities pa WHERE pa.project_id = v.id AND pa.estimated_duration IS NOT NULL) as computed_est_duration, COALESCE((SELECT SUM(cb2.planned_cost) FROM cost_breakdown cb2 WHERE cb2.project_id = v.id), 0) as computed_budget, COALESCE((SELECT SUM(cb2.actual_cost) FROM cost_breakdown cb2 WHERE cb2.project_id = v.id), 0) as computed_cost, p.ranking FROM v_project_full v LEFT JOIN projects p ON p.id = v.id WHERE 1=1";
}
$statusSub = "(SELECT rs2.name FROM ref_status rs2 INNER JOIN projects pp2 ON pp2.status_id = rs2.id WHERE pp2.id = v.id)";
if (!empty($statusArr)) {
    $hasOpen = in_array('Open', $statusArr);
    $statusFiltered = array_values(array_filter($statusArr, fn($s) => $s !== 'Open'));
    if ($hasOpen) {
        $statusFiltered[] = 'Open Expansion';
        $statusFiltered[] = 'Open Research';
    }
    $statusFiltered = array_unique($statusFiltered);
    if (!empty($statusFiltered)) {
        $placeholders = [];
        foreach ($statusFiltered as $i => $s) {
            $ph = ':status_' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $s;
        }
        $sql .= " AND $statusSub IN (" . implode(',', $placeholders) . ")";
    }
}
if (!empty($categoryArr)) {
    $placeholders = [];
    foreach ($categoryArr as $i => $c) {
        $ph = ':category_' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $c;
    }
    $sql .= " AND v.category_name IN (" . implode(',', $placeholders) . ")";
}
if (!empty($rpArr)) {
    $placeholders = [];
    foreach ($rpArr as $i => $r) {
        $ph = ':rp_' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $r;
    }
    $sql .= " AND v.responsible_person_name IN (" . implode(',', $placeholders) . ")";
}
if ($month) { $sql .= " AND DATE_FORMAT(v.start_date, '%Y-%m') = :month"; $params[':month'] = $month; }
if (!empty($priorityArr)) {
    $placeholders = [];
    foreach ($priorityArr as $i => $pr) {
        $ph = ':priority_' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $pr;
    }
    $sql .= " AND v.priority_name IN (" . implode(',', $placeholders) . ")";
}
if (!empty($benefitArr)) {
    $placeholders = [];
    foreach ($benefitArr as $i => $b) {
        $ph = ':benefit_' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $b;
    }
    $sql .= " AND v.benefit_name IN (" . implode(',', $placeholders) . ")";
}
if ($search) { $sql .= " AND (v.project_name LIKE :search OR v.pic LIKE :search OR v.remarks LIKE :search)"; $params[':search'] = "%$search%"; }
if ($stagnant === 'yes') { $sql .= " AND v.days_since_last_update > 60 AND (SELECT rs3.name FROM ref_status rs3 INNER JOIN projects pp3 ON pp3.status_id = rs3.id WHERE pp3.id = v.id) IN ('Open Expansion','Open Research')"; }
$pendingFilter = [
    'gt7'  => 'v.days_since_last_update > 7 AND v.days_since_last_update < 14',
    'gt14' => 'v.days_since_last_update > 14',
    'gt30' => 'v.days_since_last_update > 30',
    'gt60' => 'v.days_since_last_update > 60',
];
if (!empty($pendingDaysArr)) {
    $pendingParts = [];
    foreach ($pendingDaysArr as $pd) {
        if (isset($pendingFilter[$pd])) $pendingParts[] = $pendingFilter[$pd];
    }
    if (!empty($pendingParts)) {
        $sql .= " AND (" . implode(' OR ', $pendingParts) . ") AND (SELECT rs4.name FROM ref_status rs4 INNER JOIN projects pp4 ON pp4.status_id = rs4.id WHERE pp4.id = v.id) IN ('Open Expansion','Open Research','Close Reject','Close Hold')";
    }
}
if (!empty($budgetStatusArr)) {
    $budgetParts = [];
    if (in_array('over', $budgetStatusArr)) $budgetParts[] = "(SELECT COALESCE(SUM(cb.actual_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) > (SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) AND (SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) > 0";
    if (in_array('saving', $budgetStatusArr)) $budgetParts[] = "(SELECT COALESCE(SUM(cb.actual_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) < (SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) AND (SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb WHERE cb.project_id = v.id) > 0";
    if (!empty($budgetParts)) $sql .= " AND (" . implode(' OR ', $budgetParts) . ")";
}
if ($procurementUnverified === '1') { $sql .= " AND EXISTS (SELECT 1 FROM project_activities pa WHERE pa.project_id = v.id AND pa.milestone_id = 5 AND (pa.verified = 0 OR pa.verified IS NULL))"; }
if ($minBudget !== '') { $sql .= " AND v.planned_budget >= :min_budget"; $params[':min_budget'] = $minBudget; }
if ($maxBudget !== '') { $sql .= " AND v.planned_budget <= :max_budget"; $params[':max_budget'] = $maxBudget; }
if ($minCost !== '') { $sql .= " AND v.actual_cost >= :min_cost"; $params[':min_cost'] = $minCost; }
if ($maxCost !== '') { $sql .= " AND v.actual_cost <= :max_cost"; $params[':max_cost'] = $maxCost; }

$sortMap = [
    'ranking_asc' => 'CASE WHEN p.ranking IS NULL THEN 1 ELSE 0 END ASC, p.ranking ASC',
    'start_date_desc' => 'v.start_date DESC', 'start_date_asc' => 'v.start_date ASC',
    'end_date_desc' => 'computed_end_date DESC', 'project_name_asc' => 'v.project_name ASC',
    'actual_cost_desc' => 'computed_cost DESC', 'profit_desc' => '(computed_budget - computed_cost) DESC',
    'last_update_desc' => 'v.last_update_date DESC, v.start_date DESC'
];
$sql .= " ORDER BY " . ($sortMap[$sort] ?? 'CASE WHEN p.ranking IS NULL THEN 1 ELSE 0 END ASC, p.ranking ASC');
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} catch (Exception $e) {
    $projects = [];
    $sqlError = $e->getMessage();
}

// Debug status milestone: buka projects.php?msdebug=1
$msDebugOn = isset($_GET['msdebug']);
$msDebugErr = [];
$msDebugRows = [];

// ===== Status milestone disamakan dengan halaman detail project (project_detail.php) =====
// M1 Analyze  -> Document Assessment (link Proposal + Project Order)
// M2 Desain   -> Assessment Project Desain (Ranking + Target Unit)
// M3 Timeline -> semua baris Timeline Desain ter-verify (termasuk planning yang belum jadi activity)
// M4 Budget   -> semua item + baris TOTAL punya SAVINGS PERCENTAGE
// M5 Procure  -> semua item Delivery Status = Received
// M8/M9/M10   -> semua activity milestone ter-verify (termasuk planning yang belum jadi activity)
if ($hasVerified && $projects) {
    $msIds = array_values(array_unique(array_map(fn($r) => (int)$r['id'], $projects)));
    $msPh = implode(',', array_fill(0, count($msIds), '?'));

    $msLinks = [];    // [pid] = ['proposal'=>true, 'project_order'=>true]
    $msDesign = [];   // [pid] = row projects (ranking, image_path, target_unit)
    $msAct = [];      // [pid][milestone_id] = ['total'=>n, 'verified'=>n]
    $msTimeline = []; // [pid] = ['total'=>n, 'verified'=>n] (semua baris, untuk M3)
    $msProc = [];     // [pid] = ['total'=>n, 'received'=>n] (milestone 5 saja)
    $msMirror = [];   // [pid] = ['abs(row_order)|timeline' => true]
    $msBudget = [];   // [pid] = ['total'=>n, 'complete'=>bool]

    // M1: Document Assessment (Short Proposal + Project Order)
    try {
        $st = $pdo->prepare("SELECT project_id, link_type FROM project_drive_links WHERE project_id IN ($msPh) AND link_type IN ('proposal','project_order')");
        $st->execute($msIds);
        foreach ($st->fetchAll() as $r) { $msLinks[(int)$r['project_id']][$r['link_type']] = true; }
    } catch (Exception $e) { $msDebugErr[] = 'project_drive_links: ' . $e->getMessage(); }

    // M2: Assessment Project Desain (Ranking + Target Unit)
    try {
        $st = $pdo->prepare("SELECT id, ranking, image_path, target_unit FROM projects WHERE id IN ($msPh)");
        $st->execute($msIds);
        foreach ($st->fetchAll() as $r) { $msDesign[(int)$r['id']] = $r; }
    } catch (Exception $e) { $msDebugErr[] = 'projects: ' . $e->getMessage(); }

    // Activity nyata per milestone + mirror key planning (untuk M3, M8, M9, M10)
    try {
        $st = $pdo->prepare("SELECT id, project_id, milestone_id, row_order, timeline, verified FROM project_activities WHERE project_id IN ($msPh)");
        $st->execute($msIds);
        foreach ($st->fetchAll() as $r) {
            $pid = (int)$r['project_id'];
            $mid = (int)($r['milestone_id'] ?? 0);
            $isVerified = !empty($r['verified']);

            $msTimeline[$pid]['total'] = ($msTimeline[$pid]['total'] ?? 0) + 1;
            if ($isVerified) $msTimeline[$pid]['verified'] = ($msTimeline[$pid]['verified'] ?? 0) + 1;

            if ($mid > 0) {
                $msAct[$pid][$mid]['total'] = ($msAct[$pid][$mid]['total'] ?? 0) + 1;
                if ($isVerified) $msAct[$pid][$mid]['verified'] = ($msAct[$pid][$mid]['verified'] ?? 0) + 1;
            }
            if ((int)($r['row_order'] ?? 0) < 0) {
                $msMirror[$pid][(int)abs((int)$r['row_order']) . '|' . ($r['timeline'] ?? '')] = true;
            }
        }
    } catch (Exception $e) { $msDebugErr[] = 'project_activities: ' . $e->getMessage(); }

    // M5: Procurement — Delivery Status (kolom procurement_status, dibuat otomatis bila belum ada)
    try {
        $hasProcStatusCol = $pdo->query("SHOW COLUMNS FROM project_activities LIKE 'procurement_status'")->fetch();
        if ($hasProcStatusCol) {
            $st = $pdo->prepare("SELECT project_id, procurement_status FROM project_activities WHERE project_id IN ($msPh) AND milestone_id = 5 AND id > 0");
            $st->execute($msIds);
            foreach ($st->fetchAll() as $r) {
                $pid = (int)$r['project_id'];
                $msProc[$pid]['total'] = ($msProc[$pid]['total'] ?? 0) + 1;
                if (trim((string)($r['procurement_status'] ?? '')) === 'Received') {
                    $msProc[$pid]['received'] = ($msProc[$pid]['received'] ?? 0) + 1;
                }
            }
        }
    } catch (Exception $e) { $msDebugErr[] = 'procurement_status: ' . $e->getMessage(); }

    // Planning Timeline yang belum punya activity -> dihitung sebagai baris BELUM ter-verify
    try {
        $st = $pdo->prepare("SELECT ptl.project_id, ptl.row_order, ptl.timeline, rm.id AS milestone_id FROM project_planning_timeline ptl LEFT JOIN ref_milestone rm ON rm.name = ptl.milestone WHERE ptl.project_id IN ($msPh)");
        $st->execute($msIds);
        foreach ($st->fetchAll() as $r) {
            $pid = (int)$r['project_id'];
            if (isset($msMirror[$pid][(int)$r['row_order'] . '|' . ($r['timeline'] ?? '')])) continue;
            $msTimeline[$pid]['total'] = ($msTimeline[$pid]['total'] ?? 0) + 1;
            $mid = (int)($r['milestone_id'] ?? 0);
            if ($mid > 0) $msAct[$pid][$mid]['total'] = ($msAct[$pid][$mid]['total'] ?? 0) + 1;
        }
    } catch (Exception $e) { $msDebugErr[] = 'project_planning_timeline: ' . $e->getMessage(); }

    // M4: Budgeting Assessment (SAVINGS PERCENTAGE per item + TOTAL)
    try {
        $st = $pdo->prepare("SELECT project_id, planned_cost, actual_cost FROM cost_breakdown WHERE project_id IN ($msPh)");
        $st->execute($msIds);
        foreach ($st->fetchAll() as $r) {
            $pid = (int)$r['project_id'];
            $msBudget[$pid]['total'] = ($msBudget[$pid]['total'] ?? 0) + 1;
            $rowComplete = $r['planned_cost'] !== null && (float)$r['planned_cost'] > 0 && $r['actual_cost'] !== null;
            if (!$rowComplete) $msBudget[$pid]['complete'] = false;
        }
    } catch (Exception $e) { $msDebugErr[] = 'cost_breakdown: ' . $e->getMessage(); }

    // Terapkan hasil perhitungan ke setiap baris project
    foreach ($projects as &$prow) {
        $pid = (int)$prow['id'];

        // M1 — Document Assessment (selalu "ada isinya" seperti di detail)
        $prow['ms_1_total'] = 1;
        $prow['ms_1'] = (!empty($msLinks[$pid]['proposal']) && !empty($msLinks[$pid]['project_order'])) ? 1 : 0;

        // M2 — Assessment Project Desain
        $d = $msDesign[$pid] ?? null;
        $prow['ms_2_total'] = 1;
        $prow['ms_2'] = ($d && !empty($d['ranking']) && (int)($d['target_unit'] ?? 0) > 0) ? 1 : 0;

        // M3 — Timeline Desain (semua baris activity + planning)
        $prow['ms_3_total'] = $msTimeline[$pid]['total'] ?? 0;
        $prow['ms_3'] = $msTimeline[$pid]['verified'] ?? 0;

        // M4 — Budgeting
        $bTotal = $msBudget[$pid]['total'] ?? 0;
        $prow['ms_4_total'] = $bTotal;
        $prow['ms_4'] = ($bTotal > 0 && !isset($msBudget[$pid]['complete'])) ? $bTotal : 0;

        // M5 — Procurement (Delivery Status semua Received)
        $prTotal = $msProc[$pid]['total'] ?? 0;
        $prow['ms_5_total'] = $prTotal;
        $prow['ms_5'] = ($prTotal > 0 && ($msProc[$pid]['received'] ?? 0) === $prTotal) ? $prTotal : 0;

        // M8 / M9 / M10 — Development / Trial Evaluation / Launching
        foreach ([8, 9, 10] as $mid) {
            $mTotal = $msAct[$pid][$mid]['total'] ?? 0;
            $prow['ms_' . $mid . '_total'] = $mTotal;
            $prow['ms_' . $mid] = $msAct[$pid][$mid]['verified'] ?? 0;
        }

        if ($msDebugOn) {
            $msStates = [];
            foreach ([1, 2, 3, 4, 5, 8, 9, 10] as $mid) {
                $t = (int)($prow['ms_' . $mid . '_total'] ?? 0);
                $v = (int)($prow['ms_' . $mid] ?? 0);
                $msStates[] = $mid . ':' . ($t > 0 && $v >= $t ? 'CHECK' : ($t > 0 ? 'X' : 'GREY')) . '(' . $v . '/' . $t . ')';
            }
            $msDebugRows[] = 'ID #' . $pid . ' ' . ($prow['project_name'] ?? '') . ' => ' . implode(' | ', $msStates);
        }
    }
    unset($prow);
}

$allStatus = $pdo->query("SELECT id, name FROM ref_status ORDER BY name")->fetchAll();
$allTypesRaw = $pdo->query("SELECT id, name FROM ref_category ORDER BY name")->fetchAll();
$catOrder = categoryOrder();
usort($allTypesRaw, function($a, $b) use ($catOrder) {
    $ia = array_search($a['name'], $catOrder);
    $ib = array_search($b['name'], $catOrder);
    $ia = $ia === false ? 999 : $ia;
    $ib = $ib === false ? 999 : $ib;
    return $ia - $ib;
});
$allTypes = $allTypesRaw;
$allPriority = $pdo->query("SELECT id, name FROM ref_priority ORDER BY name")->fetchAll();
$allBenefit = $pdo->query("SELECT id, name FROM ref_benefit ORDER BY name")->fetchAll();
$allUsers = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
$allMilestones = $pdo->query("SELECT id, name FROM ref_milestone ORDER BY sort_order ASC, id ASC")->fetchAll();
$allRp = $pdo->query("SELECT name FROM users WHERE role_type IN ('Responsible Person','Responsible Person / PIC') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$totalFiltered = count($projects);
$totalBudget = (float)$pdo->query("SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb")->fetchColumn();
$totalCost = (float)$pdo->query("SELECT COALESCE(SUM(cb.actual_cost), 0) FROM cost_breakdown cb")->fetchColumn();
$totalProfit = $totalBudget - $totalCost;

$statusCount = [];
$categoryCount = [];
$benefitCount = [];
$priorityCount = [];
foreach ($projects as $p) {
    $s = $p['real_status_name'] ?? $p['status_name'] ?? 'Unknown';
    $statusCount[$s] = ($statusCount[$s] ?? 0) + 1;
    $c = $p['category_name'] ?? 'Unknown';
    $categoryCount[$c] = ($categoryCount[$c] ?? 0) + 1;
    $b = $p['benefit_name'] ?? 'Unknown';
    $benefitCount[$b] = ($benefitCount[$b] ?? 0) + 1;
    $pr = $p['priority_name'] ?? 'Unknown';
    $priorityCount[$pr] = ($priorityCount[$pr] ?? 0) + 1;
}

$rpRows = qRows($pdo, "SELECT u.name as rp_name, COUNT(*) as total FROM projects p INNER JOIN users u ON p.responsible_person_id = u.id GROUP BY u.name ORDER BY total DESC");
$rpCount = [];
foreach ($rpRows as $row) {
    $rpCount[$row['rp_name']] = (int)$row['total'];
}

$monthlyOpen = [];
$monthlyFinish = [];
$monthlyTrend = [];
$currentYear = date('Y');
for ($m = 1; $m <= 12; $m++) {
    $ym = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $monthlyOpen[$ym] = 0;
    $monthlyFinish[$ym] = 0;
    $monthlyTrend[$ym] = 0;
}
foreach ($projects as $p) {
    if ($p['start_date']) {
        $ym = date('Y-m', strtotime($p['start_date']));
        $year = date('Y', strtotime($p['start_date']));
        if ($year == $currentYear) {
            $monthlyTrend[$ym] = ($monthlyTrend[$ym] ?? 0) + 1;
            $st = $p['real_status_name'] ?? $p['status_name'] ?? '';
            if (in_array($st, ['Close Finish','Close Hold','Close Reject'])) {
                $monthlyFinish[$ym] = ($monthlyFinish[$ym] ?? 0) + 1;
            } else {
                $monthlyOpen[$ym] = ($monthlyOpen[$ym] ?? 0) + 1;
            }
        }
    }
}
ksort($monthlyOpen);
ksort($monthlyFinish);
ksort($monthlyTrend);
arsort($statusCount);
sortCategoriesByOrder($categoryCount);
$categoryCountDisplay = [];
foreach ($categoryCount as $k => $v) $categoryCountDisplay[categoryDisplay($k)] = $v;
arsort($benefitCount);
arsort($priorityCount);
arsort($rpCount);
?>

<!-- SQL Error Display -->
<?php if (!empty($sqlError)): ?>
<div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-6">
    <div class="flex items-center gap-2 text-red-600 text-sm font-bold"><i class="bi bi-exclamation-triangle-fill"></i> SQL Error</div>
    <p class="text-xs text-red-500 mt-1"><?= safe($sqlError) ?></p>
</div>
<?php endif; ?>

<!-- Milestone Debug (?msdebug=1) -->
<?php if ($msDebugOn): ?>
<div class="bg-slate-900 text-slate-100 rounded-2xl p-4 mb-6 font-mono text-[11px] overflow-x-auto">
    <div class="flex items-center gap-2 text-xs font-bold mb-2"><i class="bi bi-bug-fill text-amber-400"></i> Milestone Logic Debug</div>
    <div>Modul milestone v2: <b class="text-emerald-400">AKTIF</b> (file projects.php terbaru sudah jalan)</div>
    <div>Kolom verified terdeteksi: <b><?= $hasVerified ? 'YES' : 'NO' ?></b> | Project dirender: <b><?= count($projects) ?></b></div>
    <?php if ($msDebugErr): ?>
    <div class="text-red-400 mt-2">ERROR saat hitung milestone: <?= safe(implode(' | ', $msDebugErr)) ?></div>
    <?php else: ?>
    <div class="text-emerald-400 mt-2">Tidak ada error perhitungan milestone.</div>
    <?php endif; ?>
    <hr class="border-slate-700 my-2">
    <?php foreach ($msDebugRows as $row): ?>
    <div class="whitespace-nowrap"><?= safe($row) ?></div>
    <?php endforeach; ?>
    <?php if (empty($msDebugRows)): ?>
    <div class="text-amber-400">Perhitungan milestone tidak dijalankan (kemungkinan query utama gagal -> lihat SQL Error di atas).</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Summary KPIs -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
        <div class="text-[10px] font-semibold text-slate-400 mb-1">Total Budget</div>
        <div class="text-lg font-extrabold text-blue-600"><?= formatRupiah($totalBudget) ?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
        <div class="text-[10px] font-semibold text-slate-400 mb-1">Total Expenditure</div>
        <div class="text-lg font-extrabold text-cyan-600"><?= formatRupiah($totalCost) ?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
        <div class="text-[10px] font-semibold text-slate-400 mb-1">Remaining Budget</div>
        <div class="text-lg font-extrabold <?= $totalProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= formatRupiah($totalProfit) ?></div>
    </div>
</div>

<!-- Project OPEN vs Finish + New Project Trend -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-bar-chart-fill text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Project OPEN vs Project Finish</span>
        </div>
        <div class="p-3 h-48"><canvas id="chartTrend"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-graph-up text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">New Project Trend</span>
        </div>
        <div class="p-3 h-48"><canvas id="chartNewTrend"></canvas></div>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100"><span class="text-sm font-bold text-slate-700">Priority</span></div>
        <div class="p-4 h-56"><canvas id="chartPriority"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100"><span class="text-sm font-bold text-slate-700">Types</span></div>
        <div class="p-4 h-56"><canvas id="chartTypes"></canvas></div>
    </div>
</div>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100"><span class="text-sm font-bold text-slate-700">Responsible Person</span></div>
    <div class="p-4 h-72"><canvas id="chartRp"></canvas></div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <span class="text-[10px] font-semibold text-slate-400"><i class="bi bi-funnel mr-1"></i>Project Filter</span>
        <?php if ($canWrite): ?>
        <button onclick="openProjectModal()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg"><i class="bi bi-plus-lg"></i> Add Project</button>
        <?php endif; ?>
    </div>
    <form method="GET" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2">
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Search</label>
            <input type="text" name="search" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="Name, PIC..." value="<?= safe($search) ?>">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Status</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="status" value="<?= safe($status) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_status" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($status) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Types</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="category" value="<?= safe($category) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_category" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($category) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Responsible Person</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="rp" value="<?= safe($rp) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_rp" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($rp) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Priority</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="priority" value="<?= safe($priority) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_priority" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($priority) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Start Month</label>
            <input type="month" name="month" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" value="<?= safe($month) ?>">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Goals</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="benefit" value="<?= safe($benefit) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_benefit" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($benefit) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Sort By</label>
            <select name="sort" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white">
                <option value="ranking_asc" <?= $sort == 'ranking_asc' ? 'selected' : '' ?>>Ranking (Terkecil)</option>
                <option value="start_date_desc" <?= $sort == 'start_date_desc' ? 'selected' : '' ?>>Newest Start</option>
                <option value="start_date_asc" <?= $sort == 'start_date_asc' ? 'selected' : '' ?>>Oldest Start</option>
                <option value="end_date_desc" <?= $sort == 'end_date_desc' ? 'selected' : '' ?>>Newest End</option>
                <option value="last_update_desc" <?= $sort == 'last_update_desc' ? 'selected' : '' ?>>Latest Update</option>
                <option value="project_name_asc" <?= $sort == 'project_name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                <option value="actual_cost_desc" <?= $sort == 'actual_cost_desc' ? 'selected' : '' ?>>Highest Expenditure</option>
                <option value="profit_desc" <?= $sort == 'profit_desc' ? 'selected' : '' ?>>Highest Remaining Budget</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Pending Update</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="pending_days" value="<?= safe($pendingDays) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_pending" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($pendingDays) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Budget Status</label>
            <div class="cbx-wrap relative">
                <input type="hidden" name="budget_status" value="<?= safe($budgetStatus) ?>" class="cbx-val">
                <input type="text" class="cbx-input w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition bg-white" placeholder="All" data-cbx="f_budget_status" data-empty="All" data-multiple="1" autocomplete="off" value="<?= safe($budgetStatus) ?>">
                <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Min Budget</label>
            <input type="number" name="min_budget" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="0" value="<?= safe($minBudget) ?>">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Max Budget</label>
            <input type="number" name="max_budget" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="∞" value="<?= safe($maxBudget) ?>">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Min Expenditure</label>
            <input type="number" name="min_cost" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="0" value="<?= safe($minCost) ?>">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Max Expenditure</label>
            <input type="number" name="max_cost" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="∞" value="<?= safe($maxCost) ?>">
        </div>
        <div class="flex items-end gap-1.5">
            <button type="submit" class="flex-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"><i class="bi bi-funnel mr-1"></i> Filter</button>
            <a href="projects.php" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-end gap-4">
        <div class="flex flex-col items-end gap-1">
            <span class="text-sm font-bold text-slate-700"><?= count($projects) ?> projects found</span>
            <?php if ($canWrite): ?>
            <button onclick="reorderAllRanks()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition" title="Urutkan ulang semua rank"><i class="bi bi-sort-numeric-down"></i> Reorder Rank</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="px-5 py-2 border-b border-slate-100 flex items-center gap-2 flex-wrap">
        <?php if ($month): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700"><i class="bi bi-calendar-month"></i> Filter: <?= safe($month) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-blue-500 hover:text-blue-700"><i class="bi bi-x-lg"></i></a></span>
        <?php elseif ($expansion === '1'): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700"><i class="bi bi-arrow-up-right-circle"></i> Filter: Expansion + Open</span>
        <?php elseif ($research === '1'): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-700"><i class="bi bi-flask"></i> Filter: Research + Open</span>
        <?php endif; ?>
        <?php if (!empty($statusArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700"><i class="bi bi-circle-fill"></i> Status: <?= safe(implode(', ', $statusArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-blue-500 hover:text-blue-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($categoryArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-purple-50 text-purple-700"><i class="bi bi-tags"></i> Types: <?= safe(implode(', ', $categoryArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-purple-500 hover:text-purple-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($rpArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700"><i class="bi bi-person-workspace"></i> RP: <?= safe(implode(', ', $rpArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-emerald-500 hover:text-emerald-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($priorityArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700"><i class="bi bi-flag"></i> Priority: <?= safe(implode(', ', $priorityArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-amber-500 hover:text-amber-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($benefitArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-pink-50 text-pink-700"><i class="bi bi-star"></i> Goals: <?= safe(implode(', ', $benefitArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-pink-500 hover:text-pink-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($pendingDaysArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-orange-50 text-orange-700"><i class="bi bi-clock"></i> Pending: <?= safe(implode(', ', $pendingDaysArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-orange-500 hover:text-orange-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
        <?php if (!empty($budgetStatusArr)): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-red-50 text-red-700"><i class="bi bi-cash-coin"></i> Budget: <?= safe(implode(', ', $budgetStatusArr)) ?> <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="ml-1 text-red-500 hover:text-red-700"><i class="bi bi-x-lg"></i></a></span>
        <?php endif; ?>
    </div>

    <!-- Mobile Cards (hidden - table shown everywhere) -->
    <div id="mobileCards" class="hidden divide-y divide-slate-100">
        <?php foreach ($projects as $i => $p): ?>
        <div class="p-4 active:bg-slate-50 transition" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-sm text-slate-800 truncate"><?= safe($p['project_name']) ?></div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <?= statusBadge($p['real_status_name'] ?? $p['status_name']) ?>
                        <?= priorityBadge($p['priority_name']) ?>
                    </div>
                </div>

            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500 mb-2">
                <span class="inline-flex items-center gap-1"><i class="bi <?= categoryIcon($p['category_name']) ?> text-slate-400"></i><?= safe(categoryDisplay($p['category_name'])) ?></span>
                <span class="inline-flex items-center gap-1"><i class="bi bi-bullseye text-slate-400"></i><?= safe($p['benefit_name']) ?></span>
            </div>
            <?php
            $msComplete = 0;
            foreach ([1,2,3,4,5,8,9,10] as $mid) {
                $t = ($p['ms_'.$mid.'_total'] ?? 0);
                $v = ($p['ms_'.$mid] ?? 0);
                if ($t > 0 && $v >= $t) $msComplete++;
            }
            $msPercent = round(($msComplete / 8) * 100);
            ?>
            <div class="flex items-center gap-2 mb-2">
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all <?= $msPercent == 100 ? 'bg-emerald-500' : ($msPercent >= 50 ? 'bg-blue-500' : 'bg-amber-500') ?>" style="width:<?= $msPercent ?>%"></div>
                </div>
                <span class="text-[10px] font-bold <?= $msPercent == 100 ? 'text-emerald-600' : ($msPercent >= 50 ? 'text-blue-600' : 'text-amber-600') ?>"><?= $msComplete ?>/9</span>
            </div>
            <?php if (!empty($p['last_activity_timeline'])): ?>
            <div class="flex items-center gap-3 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1"><i class="bi bi-clock-history text-blue-500"></i><?= safe($p['last_activity_timeline']) ?></span>
                <?php if (!empty($p['last_activity_pic'])): ?><span class="inline-flex items-center gap-1"><i class="bi bi-person text-slate-400"></i><?= safe($p['last_activity_pic']) ?></span><?php endif; ?>
                <?php if (!empty($p['last_activity_date'])): ?><span class="inline-flex items-center gap-1"><i class="bi bi-calendar-event text-slate-400"></i><?= date('d M y', strtotime($p['last_activity_date'])) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($projects)): ?>
        <div class="p-8 text-center text-sm text-slate-400"><i class="bi bi-inbox text-3xl mb-2 block"></i>No projects found</div>
        <?php endif; ?>
    </div>

    <!-- Desktop Table -->
    <style>
    #projectTable th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    #projectTable th.th-project { font-size:12px; min-width:200px; }
    #projectTable th.th-milestone-sub { font-size:10px; font-weight:600; }
    #projectTable td { font-size:13px; }
    #projectTable td.td-project-name { font-size:13px; font-weight:600; }
    #projectTable td.td-project-code { font-size:11px; font-weight:500; }
    #projectTable td.td-meta { font-size:12px; font-weight:500; }
    #projectTable td.td-milestone-icon { font-size:12px; font-weight:700; }
    #projectTable td.td-benefit { font-size:12px; font-weight:500; }
    #projectTable td.td-category { font-size:13px; }
    #projectTable td.td-ranking { font-size:13px; font-weight:700; width:60px; }
    #projectTable .ranking-btn { font-size:9px; line-height:1; width:30px; height:20px; }
    #projectTable th.th-rank { position:sticky; left:0; z-index:20; background:#e2e8f0; }
    #projectTable td.td-ranking { position:sticky; left:0; z-index:10; background:inherit; border-right:2px solid #cbd5e1; }
    #projectTable th.th-project { position:sticky; left:60px; z-index:20; background:#e2e8f0; }
    #projectTable td.td-project-name { position:sticky; left:60px; z-index:10; background:inherit; }
    #projectTable tbody tr:hover .td-ranking { background:#eff6ff!important; }
    #projectTable tbody tr:hover .td-project-name { background:#eff6ff!important; }
    @media(max-width:767px){
        #projectTable th { font-size:10px; padding:8px 8px; }
        #projectTable td { font-size:12px; padding:8px 8px; }
        #projectTable th.th-project { min-width:160px; font-size:11px; }
        #projectTable .ranking-btn { width:28px; height:18px; }
    }
    </style>
    <div id="tableScroll" class="tbl-scroll" style="max-height:60vh;">
        <table id="projectTable" class="text-sm" style="min-width:1600px;border-collapse:separate;border-spacing:0;">
            <thead>
            <tr style="background:#f1f5f9;">
                <th rowspan="2" class="th-rank" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;width:60px;">Rank</th>
                <th rowspan="2" class="th-project" style="text-align:center;padding:10px 14px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;border-right:2px solid #cbd5e1;">Project</th>
                <th colspan="8" style="text-align:center;padding:8px 10px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Milestone</th>
                <th colspan="3" style="text-align:center;padding:8px 10px;color:#166534;border:1px solid #cbd5e1;background:#dcfce7;">Last Activity</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Goals</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Type</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Status</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Priority</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Responsible</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Duration</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Expenditure</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Budget</th>
                <th rowspan="2" style="text-align:center;padding:8px 10px;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;vertical-align:middle;">Remaining</th>
            </tr>
            <tr style="background:#f8fafc;">
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Analyze</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Desain</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Timeline</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Budget</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Procure</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Dev</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Trial</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px;color:#1e40af;border:1px solid #cbd5e1;background:#dbeafe;">Launch</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px 8px;color:#166534;border:1px solid #cbd5e1;background:#dcfce7;">Last Update</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px 8px;color:#166534;border:1px solid #cbd5e1;background:#dcfce7;">Timeline</th>
                <th class="th-milestone-sub" style="text-align:center;padding:6px 8px;color:#166534;border:1px solid #cbd5e1;background:#dcfce7;">PIC</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $i => $p): ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" data-id="<?= (int)$p['id'] ?>" data-ranking="<?= safe($p['ranking'] ?? '') ?>" class="ranking-row hover:bg-blue-50/60 transition">
                <td class="td-ranking" style="padding:8px;border:1px solid #e2e8f0;text-align:center;">
                    <div style="display:inline-flex;align-items:center;gap:4px;">
                        <span style="color:#1e293b;min-width:18px;text-align:center;font-weight:700;"><?= safe($p['ranking'] ?? '') ? (int)$p['ranking'] : '-' ?></span>
                        <?php if ($canWrite): ?>
                        <div style="display:flex;flex-direction:column;gap:2px;">
                            <button onclick="moveRanking(<?= (int)$p['id'] ?>,'up',this)" class="ranking-btn" style="display:flex;align-items:center;justify-content:center;border-radius:4px;border:none;background:transparent;color:#94a3b8;cursor:pointer;transition:all .15s;" onmouseover="this.style.background='#e2e8f0';this.style.color='#475569'" onmouseout="this.style.background='transparent';this.style.color='#94a3b8'" title="Naik">&#9650;</button>
                            <button onclick="moveRanking(<?= (int)$p['id'] ?>,'down',this)" class="ranking-btn" style="display:flex;align-items:center;justify-content:center;border-radius:4px;border:none;background:transparent;color:#94a3b8;cursor:pointer;transition:all .15s;" onmouseover="this.style.background='#e2e8f0';this.style.color='#475569'" onmouseout="this.style.background='transparent';this.style.color='#94a3b8'" title="Turun">&#9660;</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="td-project-name" style="padding:8px 14px;border:1px solid #e2e8f0;min-width:200px;cursor:pointer;color:#1e293b;border-right:2px solid #cbd5e1;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'">
                    <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"><?= safe($p['project_name']) ?></div>
                    <div class="td-project-code" style="color:#94a3b8;"><?= safe($p['project_code'] ?? '') ?></div>
                </td>
                <?php
                $milestoneIds = [1,2,3,4,5,8,9,10];
                foreach ($milestoneIds as $mid):
                    $totalAct = ($p['ms_'.$mid.'_total'] ?? 0);
                    $verifiedAct = ($p['ms_'.$mid] ?? 0);
                    $hasActivities = $totalAct > 0;
                    $allVerified = $hasActivities && ($verifiedAct >= $totalAct);
                ?>
                <td class="td-milestone-icon" style="text-align:center;padding:6px;border:1px solid #e2e8f0;">
                    <?php if ($allVerified): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#dcfce7;color:#16a34a;" title="All verified (<?= $verifiedAct ?>/<?= $totalAct ?>)"><i class="bi bi-check-lg"></i></span>
                    <?php elseif ($hasActivities): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fef2f2;color:#f87171;" title="Not verified yet (<?= $verifiedAct ?>/<?= $totalAct ?>)"><i class="bi bi-x-lg"></i></span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#f1f5f9;color:#94a3b8;" title="No timeline yet">-</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="td-meta" style="padding:8px;border:1px solid #e2e8f0;">
                    <?php if (!empty($p['last_activity_date'])): ?>
                    <div style="color:#64748b;"><?= date('d/m/y', strtotime($p['last_activity_date'])) ?></div>
                    <div style="color:#334155;"><?= date('H:i:s', strtotime($p['last_activity_date'])) ?></div>
                    <?php else: ?>
                    <span style="color:#cbd5e1;">-</span>
                    <?php endif; ?>
                </td>
                <td class="td-meta" style="padding:8px;border:1px solid #e2e8f0;">
                    <div style="color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100px;" title="<?= safe($p['last_activity_timeline'] ?? '') ?>"><?= safe($p['last_activity_timeline'] ?? '-') ?></div>
                </td>
                <td class="td-meta" style="padding:8px;border:1px solid #e2e8f0;">
                    <span style="color:#334155;"><?= safe($p['last_activity_pic'] ?? '-') ?></span>
                </td>
                <td class="td-benefit" style="padding:8px;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:6px;background:#f8fafc;color:#475569;border-left:3px solid <?= benefitColor($p['benefit_name']) ?>"><?= safe($p['benefit_name']) ?></span></td>
                <td class="td-category" style="padding:8px;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><span style="color:#64748b;"><i class="bi <?= categoryIcon($p['category_name']) ?>" style="margin-right:4px;"></i><?= safe(categoryDisplay($p['category_name'])) ?></span></td>
                <td style="padding:8px;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><?= statusBadge($p['real_status_name'] ?? $p['status_name']) ?></td>
                <td style="padding:8px;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><?= priorityBadge($p['real_priority_name'] ?? $p['priority_name']) ?></td>
                <td class="td-meta" style="padding:8px;border:1px solid #e2e8f0;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><span style="color:#64748b;"><?= safe($p['real_rp_name'] ?? $p['responsible_person_name']) ?: '<span style="color:#cbd5e1;">-</span>' ?></span></td>
                <td style="padding:8px;border:1px solid #e2e8f0;text-align:center;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><?php
                    $dur = '<span style="color:#cbd5e1;">-</span>';
                    if ($p['computed_duration'] !== null && $p['computed_duration'] !== '') {
                        $actual = (int)$p['computed_duration'];
                        $est = ($p['computed_est_duration'] !== null && $p['computed_est_duration'] !== '') ? (int)$p['computed_est_duration'] : null;
                        $actColor = '#334155';
                        if ($est !== null) {
                            $actColor = $actual > $est ? '#ef4444' : ($actual < $est ? '#16a34a' : '#334155');
                        }
                        $estStr = $est !== null ? $est : '-';
                        $dur = '<span style="color:' . $actColor . ';">' . $actual . ' day</span> <span style="color:#64748b;">/</span> <span style="color:#334155;">' . $estStr . ' day</span>';
                    }
                    echo $dur;
                ?></td>
                <td style="padding:8px;border:1px solid #e2e8f0;text-align:right;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><span style="color:#334155;"><?= formatRupiah($p['computed_cost']) ?></span></td>
                <td style="padding:8px;border:1px solid #e2e8f0;text-align:right;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><span style="color:#334155;"><?= formatRupiah($p['computed_budget']) ?></span></td>
                <td style="padding:8px;border:1px solid #e2e8f0;text-align:right;cursor:pointer;" onclick="window.location.href='project_detail.php?id=<?= (int)$p['id'] ?>'"><?php $rem = $p['computed_budget'] - $p['computed_cost']; ?><span style="font-weight:600;color:<?= $rem >= 0 ? '#16a34a' : '#ef4444' ?>"><?= formatRupiah($rem) ?></span></td>

            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function reorderAllRanks() {
    if (!confirm('Urutkan ulang semua rank berdasarkan urutan saat ini?')) return;
    fetch('api/rankings.php?action=reorder_all', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.ok) location.reload(); else alert(d.msg || 'Gagal reorder'); })
        .catch(function(e) { alert('Error: ' + e.message); });
}
function moveRanking(id, dir, btn) {
    var row = btn.closest('tr');
    var fd = new FormData();
    fd.append('action', 'shift');
    fd.append('id', id);
    fd.append('direction', dir);
    fetch('api/rankings.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.ok) location.reload(); else alert(d.msg || 'Gagal menggeser ranking'); })
        .catch(function(e) { alert('Error: ' + e.message); });
}

document.addEventListener('DOMContentLoaded', function() {
function initDT(){if(typeof $==='undefined'||typeof $.fn.DataTable==='undefined'){setTimeout(initDT,200);return}
try{
    $.fn.dataTable.ext.type.order['rank-num-pre'] = function(d) {
        var v = $.trim(d);
        if (v === '-' || v === '' || v === null || v === undefined) return 999999;
        var n = parseInt(v, 10);
        return isNaN(n) ? 999999 : n;
    };
    var sortCol=<?= $sort==='last_update_desc' ? '10' : '0' ?>;
    var sortDir=<?= $sort==='last_update_desc' ? "'desc'" : "'asc'" ?>;
    $('#projectTable').DataTable({
        pageLength: -1,
        info:false,
        paging:false,
        order: [[sortCol, sortDir]],
        dom: 'frt',
        columnDefs: [
            { orderable: false, targets: [2,3,4,5,6,7,8,9,10,11,12] },
            { type: 'rank-num', targets: 0 }
        ],
        language: {
            search: 'Search:',
            infoEmpty: 'No data available'
        }
    });
}catch(err){console.error('DataTables error:',err)}}
initDT();

function initCharts(){if(typeof Chart==='undefined'){setTimeout(initCharts,200);return}
try{
var colors=['#3b82f6','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f97316','#ec4899','#14b8a6','#6366f1'];

// Priority Doughnut
var prLabels=<?= json_encode(array_keys($priorityCount)) ?>;
new Chart(document.getElementById('chartPriority'),{type:'doughnut',data:{labels:prLabels,datasets:[{data:<?= json_encode(array_values($priorityCount)) ?>,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'65%',onClick:function(e,el){if(el.length){window.location.href='projects.php?priority='+encodeURIComponent(prLabels[el[0].index])+'&sort=last_update_desc';}},plugins:{legend:{position:'right',labels:{boxWidth:10,padding:8,font:{size:10}}}},onHover:function(e,el){e.native.target.style.cursor=el.length?'pointer':'default';}}});

// Types Bar
var tyLabels=<?= json_encode(array_keys($categoryCountDisplay)) ?>;
new Chart(document.getElementById('chartTypes'),{type:'bar',data:{labels:tyLabels,datasets:[{label:'Proyek',data:<?= json_encode(array_values($categoryCountDisplay)) ?>,backgroundColor:colors.slice(0,<?= count($categoryCountDisplay) ?>),borderRadius:6,barThickness:24}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',onClick:function(e,el){if(el.length){window.location.href='projects.php?category='+encodeURIComponent(tyLabels[el[0].index])+'&sort=last_update_desc';}},scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'}},y:{grid:{display:false}}},plugins:{legend:{display:false}},onHover:function(e,el){e.native.target.style.cursor=el.length?'pointer':'default';}}});

// Responsible Person Bar (vertical: names on X, count on Y)
var rpLabels=<?= json_encode(array_keys($rpCount)) ?>;
new Chart(document.getElementById('chartRp'),{type:'bar',data:{labels:rpLabels,datasets:[{label:'Proyek',data:<?= json_encode(array_values($rpCount)) ?>,backgroundColor:colors.slice(0,<?= count($rpCount) ?>),borderRadius:6,barThickness:28}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'x',onClick:function(e,el){if(el.length){window.location.href='projects.php?rp='+encodeURIComponent(rpLabels[el[0].index])+'&sort=last_update_desc';}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1,font:{size:10}}},x:{grid:{display:false},ticks:{font:{size:10},maxRotation:45,minRotation:0}}},plugins:{legend:{display:false}},layout:{padding:{bottom:10}},onHover:function(e,el){e.native.target.style.cursor=el.length?'pointer':'default';}}});

// Project OPEN vs Finish Stacked Bar
var trendYm=<?= json_encode(array_keys($monthlyOpen)) ?>;
new Chart(document.getElementById('chartTrend'),{type:'bar',data:{labels:<?= json_encode(array_map('fmtMonth',array_keys($monthlyOpen))) ?>,datasets:[{label:'Project OPEN',data:<?= json_encode(array_values($monthlyOpen)) ?>,backgroundColor:'#1e5f8c',borderRadius:{topLeft:0,topRight:0,bottomLeft:4,bottomRight:4},barPercentage:0.55,categoryPercentage:0.7},{label:'Project FINISH',data:<?= json_encode(array_values($monthlyFinish)) ?>,backgroundColor:'#f07d3d',borderRadius:{topLeft:4,topRight:4,bottomLeft:0,bottomRight:0},barPercentage:0.55,categoryPercentage:0.7}]},options:{responsive:true,maintainAspectRatio:false,onClick:function(e,el,chart){if(el.length){window.location.href='projects.php?month='+trendYm[el[0].index]+'&sort=last_update_desc';}},scales:{x:{stacked:true,grid:{display:false},ticks:{font:{size:10}}},y:{stacked:true,beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:2,font:{size:10}}}},plugins:{legend:{position:'top',labels:{boxWidth:10,padding:8,font:{size:10,weight:'bold'}}}},onHover:function(e,el){e.native.target.style.cursor=el.length?'pointer':'default';}}});

// New Project Trend Line
var newTrendYm=<?= json_encode(array_keys($monthlyTrend)) ?>;
new Chart(document.getElementById('chartNewTrend'),{type:'line',data:{labels:<?= json_encode(array_map('fmtMonth',array_keys($monthlyTrend))) ?>,datasets:[{label:'New Projects',data:<?= json_encode(array_values($monthlyTrend)) ?>,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.08)',fill:true,tension:.4,pointRadius:4,pointBackgroundColor:'#3b82f6',pointBorderColor:'#fff',pointBorderWidth:2,borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,onClick:function(e,el,chart){if(el.length){window.location.href='projects.php?month='+newTrendYm[el[0].index]+'&sort=last_update_desc';}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1,font:{size:10}}},x:{grid:{display:false},ticks:{font:{size:10}}}},plugins:{legend:{display:false}},onHover:function(e,el){e.native.target.style.cursor=el.length?'pointer':'default';}}});

}catch(err){console.error('Chart error:',err)}}
initCharts();
});
</script>

<!-- Project Modal (Add) -->
<div id="projectModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeProjectModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-5xl max-h-[90vh] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">

        <form id="projectForm" class="p-5 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="id" id="pf_id">
            
            <!-- Row 1: Basic Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Date *</label>
                    <input type="date" name="start_date" id="pf_start_date" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition">
                </div>
                <div class="sm:col-span-2 lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Project Name *</label>
                    <input type="text" name="project_name" id="pf_project_name" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Project name">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Project Code</label>
                    <input type="text" name="project_code" id="pf_project_code" readonly class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50 text-slate-500" placeholder="Auto generated">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Responsible Person</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="responsible_person_id" id="pf_rp_id" class="cbx-val">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="cbx_rp" autocomplete="off">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Goals</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="benefit_id" id="pf_benefit_id" class="cbx-val">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="cbx_benefit" autocomplete="off">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Types</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="category_id" id="pf_category_id" class="cbx-val">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="cbx_category" autocomplete="off">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Status</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="status_id" id="pf_status_id" class="cbx-val">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="cbx_status" autocomplete="off">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Priority</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="priority_id" id="pf_priority_id" class="cbx-val">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="cbx_priority" autocomplete="off">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
            </div>

            <!-- Row 2: ANALYSIS -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-100 px-4 py-2 border-b border-slate-200">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Analysis</h3>
                </div>
                
                <!-- Development Analysis -->
                <div class="p-4 border-b border-slate-100">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-slate-600 uppercase">Development Analysis</h4>
                        <?php if ($canWrite): ?>
                        <button type="button" onclick="addDevAnalysisRow()" class="text-[10px] font-semibold text-blue-600 hover:text-blue-700"><i class="bi bi-plus-circle mr-1"></i>Add Row</button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs" id="devAnalysisTable">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10">No.</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Problem Overview</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Existing Condition</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Expected Condition</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Gap</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Action</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="dev-analysis-row">
                                    <td class="px-2 py-1 border border-slate-200 text-center">1</td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_problem[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Problem"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_existing[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Existing"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_expected[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Expected"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_gap[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Gap"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_action[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Action"></td>
                                    <td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action Analysis -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-slate-600 uppercase">Action Analysis</h4>
                        <?php if ($canWrite): ?>
                        <button type="button" onclick="addActionAnalysisRow()" class="text-[10px] font-semibold text-blue-600 hover:text-blue-700"><i class="bi bi-plus-circle mr-1"></i>Add Row</button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs" id="actionAnalysisTable">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10">No.</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Action</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Strengths</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Weaknesses</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Opportunities</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Threats</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="action-analysis-row">
                                    <td class="px-2 py-1 border border-slate-200 text-center">1</td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="act_action[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Action"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="act_strengths[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Strengths"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="act_weaknesses[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Weaknesses"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="act_opportunities[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Opportunities"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="act_threats[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Threats"></td>
                                    <td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Row 3: PLANNING -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-100 px-4 py-2 border-b border-slate-200">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Planning</h3>
                </div>
                
                <!-- Planned Price -->
                <div class="p-4 border-b border-slate-100">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-slate-600 uppercase">Planned Price</h4>
                        <?php if ($canWrite): ?>
                        <button type="button" onclick="addPlannedPriceRow()" class="text-[10px] font-semibold text-blue-600 hover:text-blue-700"><i class="bi bi-plus-circle mr-1"></i>Add Row</button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs" id="plannedPriceTable">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10">No.</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Requirement</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">PR Number</th>
                                    <th class="px-2 py-2 text-right font-semibold text-slate-500 border border-slate-200">Quantity</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200">Unit</th>
                                    <th class="px-2 py-2 text-right font-semibold text-slate-500 border border-slate-200">Unit Price</th>
                                    <th class="px-2 py-2 text-right font-semibold text-slate-500 border border-slate-200">Total Cost</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="planned-price-row">
                                    <td class="px-2 py-1 border border-slate-200 text-center">1</td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="price_requirement[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Requirement"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="price_pr_number[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="PR Number"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="number" name="price_quantity[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right" placeholder="0" min="0" oninput="calcTotalCost(this)"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="price_unit[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center" placeholder="Unit"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="number" name="price_unit_price[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right" placeholder="0" min="0" oninput="calcTotalCost(this)"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="number" name="price_total_cost[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right bg-slate-50" placeholder="0" readonly></td>
                                    <td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Planning Timeline -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-slate-600 uppercase">Planning Timeline</h4>
                        <?php if ($canWrite): ?>
                        <button type="button" onclick="addPlanningTimelineRow()" class="text-[10px] font-semibold text-blue-600 hover:text-blue-700"><i class="bi bi-plus-circle mr-1"></i>Add Row</button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs" id="planningTimelineTable">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10">No.</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Timeline</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">Milestone</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-500 border border-slate-200">PIC</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200">Start Date</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200">Estimated Duration</th>
                                    <th class="px-2 py-2 text-center font-semibold text-slate-500 border border-slate-200 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="planning-timeline-row">
                                    <td class="px-2 py-1 border border-slate-200 text-center">1</td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="plan_timeline[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Timeline"></td>
                                    <td class="px-2 py-1 border border-slate-200">
                                        <div class="cbx-wrap relative">
                                            <input type="hidden" name="plan_milestone_id[]" class="cbx-val">
                                            <input type="text" name="plan_milestone[]" class="cbx-input w-full px-2 py-1 text-xs border-0 outline-none" placeholder="-- Select --" data-cbx="cbx_milestone" autocomplete="off">
                                            <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 border border-slate-200">
                                        <div class="cbx-wrap relative">
                                            <input type="hidden" name="plan_pic[]" class="cbx-val">
                                            <input type="text" class="cbx-input w-full px-2 py-1 text-xs border-0 outline-none" placeholder="-- Select --" data-cbx="cbx_planning_pic" autocomplete="off">
                                            <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="date" name="plan_start_date[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center"></td>
                                    <td class="px-2 py-1 border border-slate-200"><input type="text" name="plan_duration[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center" placeholder="e.g., 7 days"></td>
                                    <td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                <button type="submit" class="px-6 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeProjectModal()" class="px-6 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
var CBX_DATA = {
    cbx_benefit: <?= json_encode(array_map(fn($b) => ['v'=>(string)$b['id'],'l'=>$b['name']], $allBenefit)) ?>,
    cbx_category: <?= json_encode(array_map(fn($c) => ['v'=>(string)$c['id'],'l'=>categoryDisplay($c['name'])], $allTypes)) ?>,
    cbx_priority: <?= json_encode(array_map(fn($p) => ['v'=>(string)$p['id'],'l'=>$p['name']], $allPriority)) ?>,
    cbx_status: <?= json_encode(array_map(fn($s) => ['v'=>(string)$s['id'],'l'=>$s['name']], $allStatus)) ?>,
    cbx_rp: <?= json_encode(array_map(fn($u) => ['v'=>(string)$u['id'],'l'=>$u['name']], $allUsers)) ?>,
    cbx_milestone: <?= json_encode(array_map(fn($m) => ['v'=>$m['name'],'l'=>$m['name']], $allMilestones)) ?>,
    cbx_planning_pic: <?= json_encode(array_map(fn($u) => ['v'=>$u['name'],'l'=>$u['name']], $allUsers)) ?>,
    cbx_procurement: [{v:'PR Pending',l:'PR Pending'},{v:'Quotation Pending',l:'Quotation Pending'},{v:'PO Pending',l:'PO Pending'}],
    f_status: <?= json_encode(array_map(fn($s) => ['v'=>$s['name'],'l'=>$s['name']], $allStatus)) ?>,
    f_category: <?= json_encode(array_map(fn($c) => ['v'=>$c['name'],'l'=>categoryDisplay($c['name'])], $allTypes)) ?>,
    f_rp: <?= json_encode(array_map(fn($n) => ['v'=>$n,'l'=>$n], $allRp)) ?>,
    f_priority: <?= json_encode(array_map(fn($pr) => ['v'=>$pr['name'],'l'=>priorityDisplay($pr['name'])], $allPriority)) ?>,
    f_benefit: <?= json_encode(array_map(fn($b) => ['v'=>$b['name'],'l'=>$b['name']], $allBenefit)) ?>,
    f_pending: [{v:'gt7',l:'> 1 weeks (7-14 days)'},{v:'gt14',l:'> 2 weeks (>14 days)'},{v:'gt30',l:'> 1 months (>30 days)'},{v:'gt60',l:'> 2 months (>60 days)'}],
    f_budget_status: [{v:'over',l:'Over budget'},{v:'saving',l:'Saving budget'}]
};
function cbxInit() {
    document.querySelectorAll('.cbx-wrap').forEach(function(wrap) {
        if (wrap._cbxInit) return;
        wrap._cbxInit = true;
        var inp = wrap.querySelector('.cbx-input');
        var val = wrap.querySelector('.cbx-val');
        var list = wrap.querySelector('.cbx-list');
        var key = inp.getAttribute('data-cbx');
        var opts = CBX_DATA[key] || [];
        var emptyLabel = inp.getAttribute('data-empty') || '-- Not Specified --';
        var isMulti = inp.getAttribute('data-multiple') === '1';
        function getSelected() {
            return val.value ? val.value.split(',').map(function(s){return s.trim()}).filter(function(s){return s}) : [];
        }
        function setSelected(arr) {
            val.value = arr.join(',');
            if (isMulti) {
                var labels = arr.map(function(v){ var o=opts.find(function(x){return x.v===v}); return o?o.l:v; });
                inp.value = labels.join(', ');
            }
        }
        function render(filter) {
            var q = (filter || '').toLowerCase();
            var selected = isMulti ? getSelected() : [];
            var html = '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-500" data-v="">' + emptyLabel + '</div>';
            opts.forEach(function(o) {
                if (q && !isMulti && o.l.toLowerCase().indexOf(q) === -1) return;
                if (q && isMulti && o.l.toLowerCase().indexOf(q) === -1 && selected.indexOf(o.v) === -1) return;
                var checked = isMulti && selected.indexOf(o.v) !== -1;
                html += '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-700' + (checked ? ' bg-blue-50' : '') + '" data-v="' + o.v + '">' + (isMulti ? '<input type="checkbox" class="mr-1.5 align-middle" ' + (checked?'checked':'') + ' tabindex="-1">' : '') + o.l + '</div>';
            });
            list.innerHTML = html;
            list.querySelectorAll('.cbx-opt').forEach(function(opt) {
                opt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var v = opt.getAttribute('data-v');
                    if (isMulti) {
                        if (v === '') {
                            val.value = '';
                            inp.value = '';
                        } else {
                            var sel = getSelected();
                            var idx = sel.indexOf(v);
                            if (idx === -1) sel.push(v); else sel.splice(idx, 1);
                            setSelected(sel);
                        }
                        render(inp.value);
                        list.classList.remove('hidden');
                    } else {
                        val.value = v;
                        inp.value = opt.textContent;
                        list.classList.add('hidden');
                    }
                });
            });
        }
        inp.addEventListener('focus', function() { if (!isMulti) render(inp.value); else render(''); list.classList.remove('hidden'); });
        inp.addEventListener('input', function() { if (!isMulti) render(inp.value); list.classList.remove('hidden'); });
        inp.addEventListener('blur', function() { setTimeout(function() { list.classList.add('hidden'); }, 200); });
        render('');
    });
}
function initCbxWrap(scope) {
    var wraps = (scope || document).querySelectorAll('.cbx-wrap');
    wraps.forEach(function(wrap) {
        if (wrap._cbxInit) return;
        wrap._cbxInit = true;
        var inp = wrap.querySelector('.cbx-input');
        var val = wrap.querySelector('.cbx-val');
        var list = wrap.querySelector('.cbx-list');
        if (!list) {
            list = document.createElement('div');
            list.className = 'cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto';
            wrap.appendChild(list);
        }
        var key = inp.getAttribute('data-cbx');
        var opts = CBX_DATA[key] || [];
        var emptyLabel = inp.getAttribute('data-empty') || '-- Not Specified --';
        var isMulti = inp.getAttribute('data-multiple') === '1';
        function getSelected() {
            return val.value ? val.value.split(',').map(function(s){return s.trim()}).filter(function(s){return s}) : [];
        }
        function setSelected(arr) {
            val.value = arr.join(',');
            if (isMulti) {
                var labels = arr.map(function(v){ var o=opts.find(function(x){return x.v===v}); return o?o.l:v; });
                inp.value = labels.join(', ');
            }
        }
        function render(filter) {
            var q = (filter || '').toLowerCase();
            var selected = isMulti ? getSelected() : [];
            var html = '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-500" data-v="">' + emptyLabel + '</div>';
            opts.forEach(function(o) {
                if (q && !isMulti && o.l.toLowerCase().indexOf(q) === -1) return;
                if (q && isMulti && o.l.toLowerCase().indexOf(q) === -1 && selected.indexOf(o.v) === -1) return;
                var checked = isMulti && selected.indexOf(o.v) !== -1;
                html += '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-700' + (checked ? ' bg-blue-50' : '') + '" data-v="' + o.v + '">' + (isMulti ? '<input type="checkbox" class="mr-1.5 align-middle" ' + (checked?'checked':'') + ' tabindex="-1">' : '') + o.l + '</div>';
            });
            list.innerHTML = html;
            list.querySelectorAll('.cbx-opt').forEach(function(opt) {
                opt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var v = opt.getAttribute('data-v');
                    if (isMulti) {
                        if (v === '') {
                            val.value = '';
                            inp.value = '';
                        } else {
                            var sel = getSelected();
                            var idx = sel.indexOf(v);
                            if (idx === -1) sel.push(v); else sel.splice(idx, 1);
                            setSelected(sel);
                        }
                        render(inp.value);
                        positionList();
                        list.classList.remove('hidden');
                    } else {
                        val.value = v;
                        inp.value = opt.textContent;
                        list.classList.add('hidden');
                        if (list.parentElement === document.body) {
                            wrap.appendChild(list);
                            list.style.position = '';
                            list.style.left = '';
                            list.style.top = '';
                            list.style.width = '';
                            list.style.zIndex = '';
                        }
                    }
                });
            });
        }
        function positionList() {
            var r = inp.getBoundingClientRect();
            list.style.position = 'fixed';
            list.style.left = r.left + 'px';
            list.style.top = (r.bottom + 2) + 'px';
            list.style.width = r.width + 'px';
            list.style.zIndex = '99999';
            if (list.parentElement !== document.body) {
                document.body.appendChild(list);
            }
        }
        inp.addEventListener('focus', function() {
            if (!isMulti) render(inp.value); else render('');
            positionList();
            list.classList.remove('hidden');
        });
        inp.addEventListener('input', function() {
            if (!isMulti) render(inp.value);
            positionList();
            list.classList.remove('hidden');
        });
        inp.addEventListener('blur', function() {
            setTimeout(function() { list.classList.add('hidden'); }, 200);
        });
    });
}
function generateProjectNumber() {
    var now = new Date();
    var yyyy = now.getFullYear();
    var mm = String(now.getMonth() + 1).padStart(2, '0');
    fetch('api/get_project_count.php')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var count = d.ok ? d.count + 1 : 1;
            var num = String(count).padStart(3, '0');
            document.getElementById('pf_project_code').value = yyyy + mm + num;
        })
        .catch(function() {
            var count = document.querySelectorAll('.ranking-row').length + 1;
            var num = String(count).padStart(3, '0');
            document.getElementById('pf_project_code').value = yyyy + mm + num;
        });
}
function addDevAnalysisRow() {
    var tbody = document.querySelector('#devAnalysisTable tbody');
    var count = tbody.querySelectorAll('tr').length + 1;
    var row = document.createElement('tr');
    row.className = 'dev-analysis-row';
    row.innerHTML = '<td class="px-2 py-1 border border-slate-200 text-center">' + count + '</td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_problem[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Problem"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_existing[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Existing"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_expected[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Expected"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_gap[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Gap"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="dev_action[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Action"></td>' +
        '<td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(row);
}
function addActionAnalysisRow() {
    var tbody = document.querySelector('#actionAnalysisTable tbody');
    var count = tbody.querySelectorAll('tr').length + 1;
    var row = document.createElement('tr');
    row.className = 'action-analysis-row';
    row.innerHTML = '<td class="px-2 py-1 border border-slate-200 text-center">' + count + '</td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="act_action[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Action"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="act_strengths[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Strengths"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="act_weaknesses[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Weaknesses"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="act_opportunities[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Opportunities"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="act_threats[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Threats"></td>' +
        '<td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(row);
}
function addPlannedPriceRow() {
    var tbody = document.querySelector('#plannedPriceTable tbody');
    var count = tbody.querySelectorAll('tr').length + 1;
    var row = document.createElement('tr');
    row.className = 'planned-price-row';
    row.innerHTML = '<td class="px-2 py-1 border border-slate-200 text-center">' + count + '</td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="price_requirement[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Requirement"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="price_pr_number[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="PR Number"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="number" name="price_quantity[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right" placeholder="0" min="0" oninput="calcTotalCost(this)"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="price_unit[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center" placeholder="Unit"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="number" name="price_unit_price[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right" placeholder="0" min="0" oninput="calcTotalCost(this)"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="number" name="price_total_cost[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-right bg-slate-50" placeholder="0" readonly></td>' +
        '<td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(row);
}
function addPlanningTimelineRow() {
    var tbody = document.querySelector('#planningTimelineTable tbody');
    var count = tbody.querySelectorAll('tr').length + 1;
    var row = document.createElement('tr');
    row.className = 'planning-timeline-row';
    row.innerHTML = '<td class="px-2 py-1 border border-slate-200 text-center">' + count + '</td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="plan_timeline[]" class="w-full px-2 py-1 text-xs border-0 outline-none" placeholder="Timeline"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><div class="cbx-wrap relative"><input type="hidden" name="plan_milestone_id[]" class="cbx-val"><input type="text" name="plan_milestone[]" class="cbx-input w-full px-2 py-1 text-xs border-0 outline-none" placeholder="-- Select --" data-cbx="cbx_milestone" autocomplete="off"><div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div></div></td>' +
        '<td class="px-2 py-1 border border-slate-200"><div class="cbx-wrap relative"><input type="hidden" name="plan_pic[]" class="cbx-val"><input type="text" class="cbx-input w-full px-2 py-1 text-xs border-0 outline-none" placeholder="-- Select --" data-cbx="cbx_planning_pic" autocomplete="off"><div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div></div></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="date" name="plan_start_date[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center"></td>' +
        '<td class="px-2 py-1 border border-slate-200"><input type="text" name="plan_duration[]" class="w-full px-2 py-1 text-xs border-0 outline-none text-center" placeholder="e.g., 7 days"></td>' +
        '<td class="px-2 py-1 border border-slate-200 text-center"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(row);
    initCbxWrap(row);
}
function removeRow(btn) {
    var row = btn.closest('tr');
    var tbody = row.parentNode;
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
        renumberTable(tbody);
    }
}
function renumberTable(tbody) {
    tbody.querySelectorAll('tr').forEach(function(tr, i) {
        tr.querySelector('td').textContent = i + 1;
    });
}
function calcTotalCost(input) {
    var row = input.closest('tr');
    var qty = parseFloat(row.querySelector('input[name="price_quantity[]"]').value) || 0;
    var price = parseFloat(row.querySelector('input[name="price_unit_price[]"]').value) || 0;
    row.querySelector('input[name="price_total_cost[]"]').value = qty * price;
}
function openProjectModal() {
    document.getElementById('projectForm').reset();
    document.getElementById('pf_id').value = '';
    document.querySelectorAll('#projectModal .cbx-input').forEach(function(i){ i.value = ''; });
    document.querySelectorAll('#projectModal .cbx-val').forEach(function(i){ i.value = ''; });
    generateProjectNumber();
    resetTables();
    initCbxWrap(document.getElementById('planningTimelineTable'));
    document.getElementById('projectModal').classList.remove('hidden');
}
function resetTables() {
    var devTbody = document.querySelector('#devAnalysisTable tbody');
    devTbody.innerHTML = devTbody.querySelector('tr').outerHTML;
    var actTbody = document.querySelector('#actionAnalysisTable tbody');
    actTbody.innerHTML = actTbody.querySelector('tr').outerHTML;
    var priceTbody = document.querySelector('#plannedPriceTable tbody');
    priceTbody.innerHTML = priceTbody.querySelector('tr').outerHTML;
    var planTbody = document.querySelector('#planningTimelineTable tbody');
    planTbody.innerHTML = planTbody.querySelector('tr').outerHTML;
    renumberTable(devTbody);
    renumberTable(actTbody);
    renumberTable(priceTbody);
    renumberTable(planTbody);
}
function closeProjectModal() { document.getElementById('projectModal').classList.add('hidden'); }
document.addEventListener('DOMContentLoaded', function() {
    initCbxWrap(document);

    var filterForm = document.querySelector('form[method="GET"]');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            filterForm.querySelectorAll('.cbx-wrap').forEach(function(wrap) {
                var inp = wrap.querySelector('.cbx-input');
                var val = wrap.querySelector('.cbx-val');
                if (inp && val && inp.value && inp.value !== (inp.getAttribute('data-empty') || 'All')) {
                    var key = inp.getAttribute('data-cbx');
                    var opts = CBX_DATA[key] || [];
                    var match = opts.find(function(o) { return o.l === inp.value; });
                    if (match) {
                        val.value = match.v;
                    } else {
                        val.value = inp.value;
                    }
                } else if (inp && val) {
                    val.value = '';
                }
            });
        });
    }

    var f = document.getElementById('projectForm');
    if (f) f.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = f.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
        btn.disabled = true;
        var fd = new FormData(f);
        fetch('api/add_project.php', { method: 'POST', body: fd })
            .then(function(r) {
                return r.text().then(function(text) {
                    var data;
                    try { data = JSON.parse(text); } catch (e) {
                        throw new Error('Server mengembalikan respons tidak valid (HTTP ' + r.status + ')');
                    }
                    if (!r.ok) throw new Error(data.msg || ('HTTP ' + r.status));
                    return data;
                });
            })
            .then(function(d) { if (d.ok) location.reload(); else { alert('Failed: ' + (d.msg || 'Unknown error')); btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save'; btn.disabled = false; } })
            .catch(function(err) { alert('Error: ' + err.message); btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save'; btn.disabled = false; });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
