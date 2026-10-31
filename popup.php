<?php
// api/popup.php — Returns HTML content for detail popups
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/functions.php';

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = $_GET['name'] ?? '';

switch ($type) {

// === PROJECT DETAIL ===
case 'project':
    if ($id <= 0) { echo '<p class="text-red-500 text-sm">Project not found.</p>'; exit; }
    $p = qOne($pdo, "SELECT * FROM v_project_full WHERE id=:id", [':id'=>$id]);
    if (!$p) { echo '<p class="text-red-500 text-sm">Project not found.</p>'; exit; }

    $activities = qRows($pdo, "SELECT pa.*, COALESCE(rm.name, 'Unassigned') as timeline_name FROM project_activities pa LEFT JOIN ref_milestone rm ON pa.milestone_id=rm.id WHERE pa.project_id=:id ORDER BY pa.date DESC, pa.row_order DESC", [':id'=>$id]);
    $planningRows = qRows($pdo, "SELECT ptl.*, rm.id AS planning_milestone_id, rm.name AS planning_milestone_name FROM project_planning_timeline ptl LEFT JOIN ref_milestone rm ON rm.name = ptl.milestone WHERE ptl.project_id=:id ORDER BY ptl.row_order ASC", [':id'=>$id]);
    $mirroredPlanning = [];
    foreach ($activities as $activity) {
        if ((int)($activity['row_order'] ?? 0) < 0) {
            $mirroredPlanning[(int)abs($activity['row_order']) . '|' . ($activity['timeline'] ?? '')] = true;
        }
    }
    foreach ($planningRows as $planning) {
        $planningKey = (int)$planning['row_order'] . '|' . ($planning['timeline'] ?? '');
        if (isset($mirroredPlanning[$planningKey])) continue;
        $activities[] = [
            'id' => 0,
            'project_id' => $id,
            'milestone_id' => $planning['planning_milestone_id'],
            'pic' => $planning['pic'],
            'date' => $planning['start_date'],
            'timeline' => $planning['timeline'],
            'pr_number' => null,
            'po_number' => null,
            'remarks' => null,
            'estimated_duration' => is_numeric($planning['estimated_duration']) ? (int)$planning['estimated_duration'] : null,
            'verified' => 0,
            'verified_date' => null,
            'row_order' => -((int)$planning['row_order']),
            'timeline_name' => $planning['planning_milestone_name'] ?: 'Unassigned'
        ];
    }
    usort($activities, function ($left, $right) {
        return [$right['date'] ?? '', (int)($right['row_order'] ?? 0)] <=> [$left['date'] ?? '', (int)($left['row_order'] ?? 0)];
    });
    $endDateComputed = $p['end_date'] ?? null;
    $costs = qRows($pdo, "SELECT * FROM cost_breakdown WHERE project_id=:id ORDER BY row_order ASC", [':id'=>$id]);
    $swotData = qOne($pdo, "SELECT problem_overview, existing_condition, expected_condition, gap, action_plan, action_taken, strengths, weaknesses, opportunities, threats FROM projects WHERE id=:id", [':id'=>$id]);
    $developmentAnalysis = qRows($pdo, "SELECT problem_overview, existing_condition, expected_condition, gap, action FROM project_development_analysis WHERE project_id=:id ORDER BY row_order ASC", [':id'=>$id]);
    $actionAnalysis = qRows($pdo, "SELECT action, strengths, weaknesses, opportunities, threats FROM project_action_analysis WHERE project_id=:id ORDER BY row_order ASC", [':id'=>$id]);
    if ($developmentAnalysis) {
        foreach (['problem_overview', 'existing_condition', 'expected_condition', 'gap'] as $field) {
            $values = array_filter(array_column($developmentAnalysis, $field), fn($value) => trim((string)$value) !== '');
            if ($values) $swotData[$field] = implode("\n\n", $values);
        }
        $values = array_filter(array_column($developmentAnalysis, 'action'), fn($value) => trim((string)$value) !== '');
        if ($values) $swotData['action_plan'] = implode("\n\n", $values);
    }
    if ($actionAnalysis) {
        foreach (['strengths', 'weaknesses', 'opportunities', 'threats'] as $field) {
            $values = array_filter(array_column($actionAnalysis, $field), fn($value) => trim((string)$value) !== '');
            if ($values) $swotData[$field] = implode("\n\n", $values);
        }
        $values = array_filter(array_column($actionAnalysis, 'action'), fn($value) => trim((string)$value) !== '');
        if ($values) $swotData['action_taken'] = implode("\n\n", $values);
    }
    $durasiHari = qVal($pdo, "SELECT SUM(DATEDIFF(pa.verified_date, pa.date)) FROM project_activities pa WHERE pa.project_id = :pid AND pa.verified = 1 AND pa.verified_date IS NOT NULL AND pa.date IS NOT NULL", [':pid'=>$id]);
    $estDuration = qVal($pdo, "SELECT SUM(pa.estimated_duration) FROM project_activities pa WHERE pa.project_id = :pid AND pa.estimated_duration IS NOT NULL", [':pid'=>$id]);
    $durasiActual = ($durasiHari !== null && $durasiHari !== '') ? (int)$durasiHari : null;
    $durasiEst = ($estDuration !== null && $estDuration !== '') ? (int)$estDuration : null;
    $durasiColor = '#334155';
    if ($durasiActual !== null && $durasiEst !== null) {
        $durasiColor = $durasiActual > $durasiEst ? '#ef4444' : ($durasiActual < $durasiEst ? '#16a34a' : '#334155');
    }
    $totalCost = array_sum(array_column($costs,'actual_cost'));
    $totalActivities = count($activities);
    $firstActivity = $totalActivities > 0 ? $activities[$totalActivities-1]['date'] ?? null : null;
    $lastActivity = $activities[0]['date'] ?? null;
    $emptyPR = 0;
    $emptyPO = 0;
    foreach ($activities as $act) {
        if ((int)($act['milestone_id'] ?? 0) === 5) {
            if (empty($act['pr_number']) || $act['pr_number']=='-' || $act['pr_number']=='') $emptyPR++;
            if (empty($act['po_number']) || $act['po_number']=='-' || $act['po_number']=='') $emptyPO++;
        }
    }
?>
<div class="space-y-5">
    <!-- Header -->
    <div>
        <h2 class="text-xl font-extrabold text-slate-800 mb-2"><?= safe($p['project_name']) ?></h2>
        <div class="flex flex-wrap items-center gap-2">
            <?= statusBadge($p['status_name']) ?>
            <?= priorityBadge($p['priority_name']) ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600"><i class="bi <?= categoryIcon($p['category_name']) ?> mr-1"></i><?= safe(categoryDisplay($p['category_name'])) ?></span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border-l-2" style="border-left-color:<?= benefitColor($p['benefit_name']) ?>"><?= safe($p['benefit_name']) ?></span>
        </div>
        <div class="text-right text-[10px] text-slate-400 mt-1">ID: #<?= $p['id'] ?> · Created: <?= safe($p['created_at']) ?: '-' ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-slate-800"><?= safe($p['pic']) ?: '<span class="text-red-400">Not assigned</span>' ?></div><div class="text-[10px] text-slate-400">PIC</div></div>
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-slate-800"><?= safe($p['responsible_person_name']) ?: '<span class="text-slate-400">Not assigned</span>' ?></div><div class="text-[10px] text-slate-400">Responsible</div></div>
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold"><?php
            if ($durasiActual !== null) {
                $estStr = $durasiEst !== null ? $durasiEst . ' day' : '- day';
                echo '<span style="color:' . $durasiColor . ';">' . $durasiActual . ' day</span> <span style="color:#64748b;font-size:0.7em;">/</span> <span style="color:#334155;">' . $estStr . '</span>';
            } else {
                echo '<span style="color:#cbd5e1;">-</span>';
            }
        ?></div><div class="text-[10px] text-slate-400">Duration</div></div>
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($p['planned_budget']) ?></div><div class="text-[10px] text-slate-400">Budget</div></div>
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($p['actual_cost']) ?></div><div class="text-[10px] text-slate-400">Expenditure</div></div>
        <div class="bg-slate-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold <?= ($p['profit']??0)>=0?'text-emerald-600':'text-red-600' ?>"><?= formatRupiah($p['profit']) ?></div><div class="text-[10px] text-slate-400">Remaining Budget</div></div>
    </div>

    <!-- Info -->
    <div class="grid grid-cols-2 gap-3 text-xs">
        <div class="space-y-1.5">
            <div class="flex justify-between"><span class="text-slate-400">Start Date</span><span class="font-medium text-slate-600"><?= fmtDate($p['start_date']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">End Date</span><span class="font-medium text-slate-600"><?= fmtDate($p['end_date']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Last Update</span><span class="font-medium text-slate-600"><?= safe($p['last_update_date']) ?: '-' ?></span></div>
        </div>
        <div class="space-y-1.5">
            <div class="flex justify-between"><span class="text-slate-400">Total Activities</span><span class="font-medium text-slate-600"><?= $totalActivities ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Total Expenditure</span><span class="font-medium text-slate-600"><?= formatRupiah($totalCost) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">PO Pending</span><span class="font-medium <?= $emptyPO > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $emptyPO ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">PR Pending</span><span class="font-medium <?= $emptyPR > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $emptyPR ?></span></div>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-3 gap-2 text-center">
        <div class="bg-slate-50 rounded-xl p-2"><div class="text-sm font-bold text-blue-600"><?= $totalActivities ?></div><div class="text-[10px] text-slate-400">Activities</div></div>
        <div class="bg-slate-50 rounded-xl p-2"><div class="text-sm font-bold text-cyan-600"><?= $firstActivity ?: '-' ?></div><div class="text-[10px] text-slate-400">First Activity</div></div>
        <div class="bg-slate-50 rounded-xl p-2"><div class="text-sm font-bold <?= $emptyPR > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $emptyPR ?></div><div class="text-[10px] text-slate-400">PR Pending</div></div>
    </div>

    <?php if ($totalActivities > 0): ?>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-1.5"><i class="bi bi-clock-history text-blue-500"></i> Activity Timeline (<?= $totalActivities ?> phases)</h4>
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-slate-50">
                    <th class="text-left px-3 py-2 font-semibold text-slate-500 text-[10px]">Date</th>
                    <th class="text-left px-2 py-2 font-semibold text-slate-500 text-[10px]">Phase</th>
                    <th class="text-left px-2 py-2 font-semibold text-slate-500 text-[10px]">PIC</th>
                    <th class="text-left px-2 py-2 font-semibold text-slate-500 text-[10px]">Remark</th>
                    <th class="text-left px-2 py-2 font-semibold text-slate-500 text-[10px]">PR Number</th>
                    <th class="text-left px-2 py-2 font-semibold text-slate-500 text-[10px]">Notes</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($activities as $i => $act): ?>
                <tr class="hover:bg-slate-50/50 transition timeline-row">
                    <td class="px-3 py-2 text-[10px] text-slate-500"><?= fmtDate($act['date']) ?></td>
                    <td class="px-2 py-2"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600"><?= safe($act['timeline_name']) ?: '-' ?></span></td>
                    <td class="px-2 py-2 text-[10px] text-slate-600"><?= safe($act['pic']) ?: '-' ?></td>
                    <td class="px-2 py-2 text-[10px] text-slate-600 whitespace-pre-line break-words" style="min-width:220px;max-width:360px;"><?= safe($act['remarks']) ?: '-' ?></td>
                    <td class="px-2 py-2"><?php if ($act['pr_number'] && $act['pr_number'] != '-'): ?><code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-700"><?= safe($act['pr_number']) ?></code><?php else: ?><span class="text-slate-300">-</span><?php endif; ?></td>
                    <td class="px-2 py-2 text-[10px] text-slate-500 whitespace-pre-line break-words" style="min-width:220px;max-width:360px;"><?= safe($act['remarks'] ?? '') ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($costs) > 0): ?>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-1.5"><i class="bi bi-wallet2 text-blue-500"></i> Expenditure Detail (<?= count($costs) ?> items)</h4>
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-slate-50">
                    <th class="text-left px-3 py-2 font-semibold text-slate-500 text-[10px]">Item</th>
                    <th class="text-right px-2 py-2 font-semibold text-slate-500 text-[10px]">Planned Price</th>
                    <th class="text-right px-2 py-2 font-semibold text-slate-500 text-[10px]">Expenditure Price</th>
                    <th class="text-right px-2 py-2 font-semibold text-slate-500 text-[10px]">Expenditure</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($costs as $cb): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-3 py-2 text-[10px] text-slate-700"><?= safe($cb['item_description']) ?: '-' ?></td>
                    <td class="px-2 py-2 text-right text-[10px] text-slate-500"><?= formatRupiah($cb['planned_price']) ?></td>
                    <td class="px-2 py-2 text-right text-[10px] text-slate-500"><?= formatRupiah($cb['actual_price']) ?></td>
                    <td class="px-2 py-2 text-right text-[10px] font-medium text-slate-700"><?= formatRupiah($cb['actual_cost']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Analisis SWOT & Problem Solving -->
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-1.5"><i class="bi bi-diagram-3 text-blue-500"></i> SWOT Analysis & Problem Solving</h4>
        <div class="space-y-2 text-[10px]">
            <?php
            $detailFields = [
                ['key'=>'problem_overview','label'=>'Problem Overview','icon'=>'bi-exclamation-circle','color'=>'slate'],
                ['key'=>'existing_condition','label'=>'Existing Condition','icon'=>'bi-clock-history','color'=>'slate'],
                ['key'=>'expected_condition','label'=>'Expected Condition','icon'=>'bi-bullseye','color'=>'slate'],
                ['key'=>'gap','label'=>'Gap Analysis','icon'=>'bi-arrows-expand','color'=>'slate'],
                ['key'=>'action_plan','label'=>'Action Plan','icon'=>'bi-list-check','color'=>'slate'],
                ['key'=>'action_taken','label'=>'Action Taken','icon'=>'bi-check-circle','color'=>'slate'],
                ['key'=>'strengths','label'=>'Strengths','icon'=>'bi-shield-check','color'=>'emerald'],
                ['key'=>'weaknesses','label'=>'Weaknesses','icon'=>'bi-shield-x','color'=>'red'],
                ['key'=>'opportunities','label'=>'Opportunities','icon'=>'bi-lightning','color'=>'blue'],
                ['key'=>'threats','label'=>'Threats','icon'=>'bi-exclamation-triangle','color'=>'amber'],
            ];
            foreach ($detailFields as $f):
                $val = $swotData[$f['key']] ?? '';
                $bg = $f['color']==='emerald'?'bg-emerald-50':($f['color']==='red'?'bg-red-50':($f['color']==='blue'?'bg-blue-50':($f['color']==='amber'?'bg-amber-50':'bg-slate-50')));
                $txt = $f['color']==='emerald'?'text-emerald-600':($f['color']==='red'?'text-red-600':($f['color']==='blue'?'text-blue-600':($f['color']==='amber'?'text-amber-600':'text-slate-500')));
                $lbl = $f['color']==='emerald'?'text-emerald-700':($f['color']==='red'?'text-red-700':($f['color']==='blue'?'text-blue-700':($f['color']==='amber'?'text-amber-700':'text-slate-600')));
            ?>
            <div class="p-2.5 <?= $bg ?> rounded-lg">
                <span class="font-bold <?= $lbl ?> flex items-center gap-1"><i class="bi <?= $f['icon'] ?> text-[10px]"></i> <?= $f['label'] ?></span>
                <p class="<?= $txt ?> mt-1 whitespace-pre-line leading-relaxed"><?= nl2br(safe($val)) ?: '<span class="text-slate-400 italic">Not filled in</span>' ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
<?php
break;

// === PIC STATS ===
case 'pic':
case 'rp':
    $picName = $name;
    if (!$picName) { echo '<p class="text-red-500 text-sm">PIC not found.</p>'; exit; }
    $picField = ($type === 'rp') ? 'responsible_person_name' : 'pic';
    $projIds = array_column(qRows($pdo, "SELECT DISTINCT id FROM v_project_full WHERE $picField=:pic", [':pic'=>$picName]), 'id');
    $actProjIds = array_column(qRows($pdo, "SELECT DISTINCT project_id FROM project_activities WHERE pic COLLATE utf8mb4_unicode_ci = :pic", [':pic'=>$picName]), 'project_id');
    $allIds = array_unique(array_merge($projIds, $actProjIds));
    if (empty($allIds)) {
        $stats = null;
        $projects = [];
    } else {
        $namedParams = [];
        $placeholders = [];
        foreach (array_values($allIds) as $i => $pid) {
            $key = ':pid' . $i;
            $namedParams[$key] = (int)$pid;
            $placeholders[] = $key;
        }
        $ph = implode(',', $placeholders);
        $stats = qOne($pdo, "SELECT COUNT(*) as total, SUM(CASE WHEN status_name IN ('Close Finish','Close Hold','Close Reject') THEN 1 ELSE 0 END) as closed, ROUND(AVG(DATEDIFF(end_date,start_date)),1) as avg_dur, SUM(actual_cost) as total_cost, SUM(planned_budget) as total_budget, SUM(profit) as total_profit FROM v_project_full WHERE id IN ($ph)", $namedParams);
        $projects = qRows($pdo, "SELECT id,project_name,status_name,start_date,end_date,actual_cost,planned_budget,profit FROM v_project_full WHERE id IN ($ph) ORDER BY start_date DESC", $namedParams);
    }
    $pct = $stats && $stats['total']>0 ? round($stats['closed']/$stats['total']*100,1) : 0;
?>
<div class="space-y-5">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= $stats['total'] ?? 0 ?></div><div class="text-[10px] text-slate-400">Total Projects</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-emerald-600"><?= $stats['closed'] ?? 0 ?></div><div class="text-[10px] text-slate-400">Completed (C+H+R)</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-blue-600"><?= $pct ?>%</div><div class="text-[10px] text-slate-400">Completion</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= $stats['avg_dur'] ?? '-' ?> days</div><div class="text-[10px] text-slate-400">Avg Duration</div></div>
    </div>
    <div class="grid grid-cols-3 gap-3 text-xs">
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="font-bold text-slate-700"><?= formatRupiah($stats['total_budget'] ?? 0) ?></div><div class="text-[10px] text-slate-400">Budget</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="font-bold text-slate-700"><?= formatRupiah($stats['total_cost'] ?? 0) ?></div><div class="text-[10px] text-slate-400">Expenditure</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="font-bold <?= ($stats['total_profit']??0)>=0?'text-emerald-600':'text-red-600' ?>"><?= formatRupiah($stats['total_profit'] ?? 0) ?></div><div class="text-[10px] text-slate-400">Remaining Budget</div></div>
    </div>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2">Detail Project</h4>
        <div class="space-y-1 max-h-60 overflow-y-auto">
        <?php foreach ($projects as $pr): ?>
        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $pr['id'] ?>'">
            <div class="flex-1 min-w-0">
                <div class="text-xs font-medium text-slate-700 truncate"><?= safe($pr['project_name']) ?></div>
                <div class="text-[10px] text-slate-400"><?= fmtDate($pr['start_date']) ?></div>
            </div>
            <div class="ml-2 flex items-center gap-2">
                <?= statusBadge($pr['status_name']) ?>
                <span class="text-[10px] font-medium text-slate-500"><?= formatRupiah($pr['actual_cost']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
break;

// === STATUS DETAIL ===
case 'status':
    $statusName = $name;
    $rows = qRows($pdo, "SELECT id,project_name,pic,start_date,actual_cost,planned_budget FROM v_project_full WHERE status_name=:s ORDER BY start_date DESC", [':s'=>$statusName]);
    $total = count($rows);
    $totalBudget = array_sum(array_column($rows,'planned_budget'));
    $totalCost = array_sum(array_column($rows,'actual_cost'));
    $byTypes = qRows($pdo, "SELECT category_name,COUNT(*) as total FROM v_project_full WHERE status_name=:s GROUP BY category_name ORDER BY total DESC", [':s'=>$statusName]);
    $catOrder = categoryOrder();
    usort($byTypes, function($a, $b) use ($catOrder) {
        $ia = array_search($a['category_name'], $catOrder);
        $ib = array_search($b['category_name'], $catOrder);
        $ia = $ia === false ? 999 : $ia;
        $ib = $ib === false ? 999 : $ib;
        return $ia - $ib;
    });
?>
<div class="space-y-5">
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= $total ?></div><div class="text-[10px] text-slate-400">Total Projects</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($totalBudget) ?></div><div class="text-[10px] text-slate-400">Budget</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($totalCost) ?></div><div class="text-[10px] text-slate-400">Expenditure</div></div>
    </div>
    <?php if (count($byTypes) > 0): ?>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2">By Types</h4>
        <div class="space-y-1">
        <?php foreach ($byTypes as $cat): ?>
        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
            <span class="text-xs text-slate-600"><i class="bi <?= categoryIcon($cat['category_name']) ?> mr-1"></i><?= safe(categoryDisplay($cat['category_name'])) ?></span>
            <span class="text-xs font-bold text-slate-700"><?= $cat['total'] ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2">Projects</h4>
        <div class="space-y-1 max-h-60 overflow-y-auto">
        <?php foreach ($rows as $r): ?>
        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
            <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= fmtDate($r['start_date']) ?></div></div>
            <span class="text-[10px] font-medium text-slate-500 ml-2"><?= formatRupiah($r['actual_cost']) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
break;

// === PRIORITY DETAIL ===
case 'priority':
    $priName = $name;
    $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,category_name FROM v_project_full WHERE priority_name=:p ORDER BY start_date DESC", [':p'=>$priName]);
    $total = count($rows);
    ?>
    <div class="space-y-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center">
            <div class="text-lg font-extrabold text-slate-800"><?= $total ?> projects</div>
            <div class="text-[10px] text-slate-400">Priority: <?= safe($priName) ?></div>
        </div>
        <div class="space-y-1 max-h-60 overflow-y-auto">
        <?php foreach ($rows as $r): ?>
        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
            <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= safe(categoryDisplay($r['category_name'])) ?></div></div>
            <?= statusBadge($r['status_name']) ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php
break;

// === CATEGORY DETAIL ===
case 'category':
    $catName = $name;
    $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,start_date FROM v_project_full WHERE category_name=:c ORDER BY start_date DESC", [':c'=>$catName]);
    $statusBreakdown = qRows($pdo, "SELECT status_name,COUNT(*) as total FROM v_project_full WHERE category_name=:c GROUP BY status_name", [':c'=>$catName]);
?>
<div class="space-y-4">
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($rows) ?></div><div class="text-[10px] text-slate-400">Total Projects</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($statusBreakdown) ?> Status</div><div class="text-[10px] text-slate-400">Variations</div></div>
    </div>
    <?php foreach ($statusBreakdown as $sb): ?>
    <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
        <?= statusBadge($sb['status_name']) ?>
        <span class="text-xs font-bold text-slate-700"><?= $sb['total'] ?> projects</span>
    </div>
    <?php endforeach; ?>
    <div>
        <h4 class="text-sm font-bold text-slate-700 mb-2">Detail Project</h4>
        <div class="space-y-1 max-h-60 overflow-y-auto">
        <?php foreach ($rows as $r): ?>
        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
            <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= fmtDate($r['start_date']) ?></div></div>
            <?= statusBadge($r['status_name']) ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
break;

// === BENEFIT DETAIL ===
case 'benefit':
    $benName = $name;
    $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,category_name FROM v_project_full WHERE benefit_name=:b ORDER BY start_date DESC", [':b'=>$benName]);
    $byStatus = qRows($pdo, "SELECT status_name,COUNT(*) as total FROM v_project_full WHERE benefit_name=:b GROUP BY status_name", [':b'=>$benName]);
?>
<div class="space-y-4">
    <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($rows) ?></div><div class="text-[10px] text-slate-400">Total Projects</div></div>
    <div class="flex flex-wrap gap-2">
    <?php foreach ($byStatus as $sb): ?>
        <?= statusBadge($sb['status_name']) ?>
        <span class="text-xs text-slate-500">(<?= $sb['total'] ?>)</span>
    <?php endforeach; ?>
    </div>
    <div class="space-y-1 max-h-60 overflow-y-auto">
    <?php foreach ($rows as $r): ?>
    <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
        <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= safe(categoryDisplay($r['category_name'])) ?></div></div>
        <?= statusBadge($r['status_name']) ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php
break;

// === COST BREAKDOWN ===
case 'cost':
    $costs = qRows($pdo, "SELECT cb.*, p.project_name FROM cost_breakdown cb JOIN projects p ON p.id=cb.project_id WHERE cb.project_id=:id ORDER BY cb.row_order ASC", [':id'=>$id]);
    $totalPlanned = array_sum(array_column($costs,'planned_cost'));
    $totalActual = array_sum(array_column($costs,'actual_cost'));
    $proj = $costs ? $costs[0]['project_name'] : '-';
?>
<div class="space-y-4">
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($totalPlanned) ?></div><div class="text-[10px] text-slate-400">Planned</div></div>
        <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= formatRupiah($totalActual) ?></div><div class="text-[10px] text-slate-400">Expenditure</div></div>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead><tr class="bg-slate-50"><th class="text-left px-3 py-2 font-semibold text-slate-500">Item</th><th class="text-right px-3 py-2 font-semibold text-slate-500">Planned</th><th class="text-right px-3 py-2 font-semibold text-slate-500">Expenditure</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($costs as $c): ?>
        <tr><td class="px-3 py-2 text-slate-600"><?= safe($c['item_description']) ?></td><td class="px-3 py-2 text-right text-slate-500"><?= formatRupiah($c['planned_cost']) ?></td><td class="px-3 py-2 text-right font-medium <?= ($c['actual_cost']>$c['planned_cost'])?'text-red-600':'text-emerald-600' ?>"><?= formatRupiah($c['actual_cost']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php
break;

// === CHART DETAIL ===
case 'chart':
    $chartLabel = $name;
    
    if (preg_match('/^\d{4}-\d{2}$/', $chartLabel)) {
        $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,category_name FROM v_project_full WHERE DATE_FORMAT(start_date,'%Y-%m')=:ym ORDER BY start_date DESC", [':ym'=>$chartLabel]);
        $total = count($rows);
        ?>
        <div class="space-y-3">
            <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= $total ?> projects</div><div class="text-[10px] text-slate-400">Month: <?= fmtMonth($chartLabel) ?></div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= safe(categoryDisplay($r['category_name'])) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif (in_array($chartLabel, ['EXPANSION','RESEARCH'])) {
        $label = $chartLabel;
        $statusFilter = ($label === 'EXPANSION') ? 'Open Expansion' : 'Open Research';
        $rows = qRows($pdo, "SELECT id,project_name,pic,status_name FROM v_project_full WHERE status_name=:s ORDER BY start_date DESC", [':s'=>$statusFilter]);
        ?>
        <div class="space-y-3">
            <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($rows) ?> projects</div><div class="text-[10px] text-slate-400"><?= safe($label) ?></div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif (preg_match('/^(Kick[- ]?off|Planning|Development|Testing|Deployment|Go[- ]?Live|Monitoring)$/i', $chartLabel)) {
        $phase = str_replace(['-',' '], ['',''], $chartLabel);
        $rows = qRows($pdo, "SELECT DISTINCT p.id,p.project_name,p.pic,st.name as status_name FROM project_activities a JOIN projects p ON p.id=a.project_id LEFT JOIN ref_status st ON p.status_id=st.id WHERE a.timeline LIKE :pat ORDER BY p.start_date DESC", [':pat'=>'%'.$chartLabel.'%']);
        ?>
        <div class="space-y-3">
            <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($rows) ?> projects</div><div class="text-[10px] text-slate-400">Phase: <?= safe($chartLabel) ?></div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'Personnel') {
        $rows = qRows($pdo, "SELECT p.pic, COUNT(DISTINCT p.id) as total_projects, (SELECT COUNT(*) FROM project_activities a3 WHERE a3.project_id = p.id AND a3.date = CURDATE()) as today_jobs, MAX(a.date) as last_date, (SELECT rm2.name FROM project_activities a2 JOIN ref_milestone rm2 ON a2.milestone_id = rm2.id WHERE a2.project_id = p.id ORDER BY a2.date DESC LIMIT 1) as last_milestone FROM projects p LEFT JOIN project_activities a ON p.id = a.project_id LEFT JOIN ref_status st ON p.status_id = st.id WHERE p.pic IS NOT NULL AND TRIM(p.pic) != '' AND st.name IN ('Open Expansion','Open Research') GROUP BY p.pic ORDER BY last_date DESC");
        $today = date('Y-m-d');
        $onJob = []; $nonJob = [];
        foreach ($rows as $r) {
            if ((int)$r['today_jobs'] > 0) $onJob[] = $r;
            else $nonJob[] = $r;
        }
        ?>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-red-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-red-600"><?= count($nonJob) ?></div><div class="text-[10px] text-red-400">PIC Non Job</div></div>
                <div class="bg-emerald-50 rounded-xl p-2 text-center"><div class="text-lg font-extrabold text-emerald-600"><?= count($onJob) ?></div><div class="text-[10px] text-emerald-400">PIC On Job</div></div>
            </div>
            <div class="text-[10px] font-semibold text-slate-400 uppercase">All Personnel</div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['pic']) ?></div><div class="text-[10px] text-slate-400"><?= $r['total_projects'] ?> projects · <?= safe($r['last_milestone'] ?? '-') ?></div></div>
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold <?php if ($r['last_date'] && date('Y-m-d', strtotime($r['last_date'])) === $today): ?>text-emerald-600<?php else: ?>text-red-500<?php endif; ?>"><span class="w-1.5 h-1.5 rounded-full <?php if ($r['last_date'] && date('Y-m-d', strtotime($r['last_date'])) === $today): ?>bg-emerald-500<?php else: ?>bg-red-400<?php endif; ?>"></span><?= $r['last_date'] ? fmtDate($r['last_date']) : '-' ?></span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'Over Budget') {
        $rows = qRows($pdo, "SELECT id,project_name,pic,actual_cost,planned_budget,ROUND(actual_cost-planned_budget,0) as diff FROM v_project_full WHERE actual_cost>planned_budget AND planned_budget>0 ORDER BY (actual_cost-planned_budget) DESC");
        ?>
        <div class="space-y-3">
            <div class="bg-red-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-red-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-red-400">Expenditure exceeds budget</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                <span class="text-[10px] font-bold text-red-600">+<?= formatRupiah($r['diff']) ?></span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'Stagnant') {
        $rows = qRows($pdo, "SELECT id,project_name,pic,last_update_date,days_since_last_update FROM v_project_full WHERE days_since_last_update>60 AND status_name IN ('Open Expansion','Open Research') ORDER BY days_since_last_update DESC");
        ?>
        <div class="space-y-3">
            <div class="bg-red-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-red-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-red-400">Stagnant >60 days</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · Last: <?= $r['last_update_date'] ?></div></div>
                <span class="text-[10px] font-bold text-red-600"><?= $r['days_since_last_update'] ?> days</span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'No PIC') {
        $rows = qRows($pdo, "SELECT id,project_name,status_name FROM v_project_full WHERE pic IS NULL OR TRIM(pic)='' ORDER BY start_date DESC");
        ?>
        <div class="space-y-3">
            <div class="bg-amber-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-amber-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-amber-400">Without PIC</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'No Activity') {
        $rows = qRows($pdo, "SELECT p.id,p.project_name,p.pic,st.name as status_name FROM projects p LEFT JOIN project_activities pa ON p.id=pa.project_id LEFT JOIN ref_status st ON p.status_id=st.id WHERE pa.id IS NULL");
        ?>
        <div class="space-y-3">
            <div class="bg-red-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-red-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-red-400">Without Activity</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'Invalid Date') {
        $rows = qRows($pdo, "SELECT id,project_name,pic,start_date,end_date FROM v_project_full WHERE start_date>end_date AND start_date IS NOT NULL AND end_date IS NOT NULL");
        ?>
        <div class="space-y-3">
            <div class="bg-amber-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-amber-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-amber-400">Invalid Dates</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= $r['start_date'] ?> → <?= $r['end_date'] ?></div></div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif ($chartLabel === 'All Projects') {
        $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,category_name FROM v_project_full ORDER BY start_date DESC");
        ?>
        <div class="space-y-3">
            <div class="bg-blue-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-blue-600"><?= count($rows) ?> projects</div><div class="text-[10px] text-blue-400">All Projects</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?> · <?= safe(categoryDisplay($r['category_name'])) ?></div></div>
                <?= statusBadge($r['status_name']) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } elseif (in_array($chartLabel, ['Total Anggaran','Pengeluaran','Variance','Sisa Anggaran','Efisiensi'])) {
        $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,budget_amount,cost_amount,profit_amount FROM v_project_full WHERE cost_amount IS NOT NULL ORDER BY cost_amount DESC");
        $total = count($rows);
        ?>
        <div class="space-y-3">
            <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= $total ?> projects</div><div class="text-[10px] text-slate-400">Financial Data</div></div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($rows as $r): ?>
            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                <div class="text-right">
                    <div class="text-[10px] text-slate-500">B: <?= formatRupiah($r['budget_amount']) ?></div>
                    <div class="text-[10px] <?= ($r['profit_amount'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">P: <?= formatRupiah($r['profit_amount']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    } else {
        $rows = qRows($pdo, "SELECT id,project_name,pic,status_name,ROUND(DATEDIFF(end_date,start_date),1) as dur FROM v_project_full WHERE category_name=:c ORDER BY start_date DESC", [':c'=>$chartLabel]);
        if (count($rows) > 0) {
            $avgDur = count($rows) > 0 ? round(array_sum(array_column($rows,'dur'))/count($rows),1) : 0;
            ?>
            <div class="space-y-3">
                <div class="bg-slate-50 rounded-xl p-3 text-center"><div class="text-lg font-extrabold text-slate-800"><?= count($rows) ?> projects</div><div class="text-[10px] text-slate-400">Types: <?= safe($chartLabel) ?> · Avg: <?= $avgDur ?> days</div></div>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                <?php foreach ($rows as $r): ?>
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition cursor-pointer" onclick="event.stopPropagation();window.location.href='project_detail.php?id=<?= $r['id'] ?>'">
                    <div class="flex-1 min-w-0"><div class="text-xs font-medium text-slate-700 truncate"><?= safe($r['project_name']) ?></div><div class="text-[10px] text-slate-400"><?= safe($r['pic']) ?></div></div>
                    <div class="ml-2 flex items-center gap-2">
                        <?= statusBadge($r['status_name']) ?>
                        <span class="text-[10px] font-bold text-blue-600"><?= $r['dur'] ?> days</span>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php
        } else {
            echo '<p class="text-sm text-slate-500">Data not available.</p>';
        }
    }
break;

case 'todayjob':
    $picName = $name;
    if (empty($picName)) { echo '<p class="text-sm text-slate-500">No PIC specified.</p>'; exit; }

    $activities = qRows($pdo, "
        SELECT p.project_name, pa.timeline, pa.date
        FROM project_activities pa
        JOIN projects p ON pa.project_id = p.id
        WHERE pa.pic COLLATE utf8mb4_unicode_ci = :pic
          AND pa.date <= CURDATE()
          AND (pa.estimated_duration IS NULL OR pa.date + INTERVAL pa.estimated_duration DAY >= CURDATE())
        ORDER BY p.project_name ASC
    ", [':pic' => $picName]);
    ?>
    <div class="space-y-3">
        <div class="text-[10px] font-semibold text-slate-400 uppercase">Today's Activities (<?= date('d M Y') ?>)</div>
        <?php if (empty($activities)): ?>
            <p class="text-sm text-slate-400 text-center py-4">No activity today.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-slate-50">
                    <th class="text-left px-4 py-2 font-semibold text-slate-500 text-xs">Project</th>
                    <th class="text-left px-4 py-2 font-semibold text-slate-500 text-xs">Activity</th>
                    <th class="text-left px-4 py-2 font-semibold text-slate-500 text-xs">Current Date</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($activities as $a): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-4 py-2.5 text-xs font-semibold text-slate-700"><?= safe($a['project_name']) ?></td>
                    <td class="px-4 py-2.5 text-xs text-emerald-600 font-semibold"><?= safe($a['timeline']) ?></td>
                    <td class="px-4 py-2.5 text-xs text-slate-500"><?= date('d M Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
break;

default:
    echo '<p class="text-sm text-slate-500">Invalid popup type.</p>';
}
?>
