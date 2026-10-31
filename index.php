<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'config/auth.php';

// Executive Dashboard hanya untuk Burhan (bfrabbani@gmail.com)
if (isAdminLoggedIn() && !canAccessExecutiveDashboard()) {
    header('Location: ' . appBase() . '/projects.php');
    exit;
}

$page_title = 'Executive Dashboard';
$page_icon = 'grid';
include 'includes/header.php';

$allProjects = $pdo->query("SELECT v.id,v.project_name,v.pic,v.status_name,v.category_name,v.benefit_name,v.priority_name,v.start_date,v.end_date,v.actual_cost,v.planned_budget,v.profit,v.total_updates,v.last_update_date,v.days_since_last_update,p.ranking FROM v_project_full v LEFT JOIN projects p ON p.id = v.id")->fetchAll();
$totalProjects = count($allProjects);

$statusStats = [];
foreach ($allProjects as $p) {
    $sn = $p['status_name'];
    $statusStats[$sn] = ($statusStats[$sn] ?? 0) + 1;
}
$sMap = $statusStats;
$openC = ($sMap['Open Expansion'] ?? 0) + ($sMap['Open Research'] ?? 0);
$closeC = ($sMap['Close Finish'] ?? 0) + ($sMap['Close Hold'] ?? 0) + ($sMap['Close Reject'] ?? 0);
$finishC = $sMap['Close Finish'] ?? 0;
$holdC = $sMap['Close Hold'] ?? 0;
$rejectC = $sMap['Close Reject'] ?? 0;
$compRate = $totalProjects > 0 ? round($closeC / $totalProjects * 100, 1) : 0;

$totalBudget = (float)$pdo->query("SELECT COALESCE(SUM(cb.planned_cost), 0) FROM cost_breakdown cb")->fetchColumn();
$totalCost = (float)$pdo->query("SELECT COALESCE(SUM(cb.actual_cost), 0) FROM cost_breakdown cb")->fetchColumn();
$totalProfit = $totalBudget - $totalCost;

$projectBudgetMap = [];
$projBudgetRows = $pdo->query("SELECT project_id, COALESCE(SUM(planned_cost), 0) as budget, COALESCE(SUM(actual_cost), 0) as cost FROM cost_breakdown GROUP BY project_id")->fetchAll();
foreach ($projBudgetRows as $row) {
    $projectBudgetMap[(int)$row['project_id']] = ['budget' => (float)$row['budget'], 'cost' => (float)$row['cost']];
}

$stagnant = 0; $overBudget = 0; $savingBudget = 0; $picNonJob = 0;
$pending7 = 0; $pending14 = 0; $pending30 = 0; $pending60 = 0; $pendingYear = 0;
$expansionCount = $sMap['Open Expansion'] ?? 0;
$researchCount = $sMap['Open Research'] ?? 0;

$givenPriorityCount = 0; $delegatedCount = 0; $scheduleCount = 0;

$catStatus = []; $bStats = []; $picStats = [];
$topPic = [];

foreach ($allProjects as $p) {
    if (($p['days_since_last_update'] ?? 0) > 30 && in_array($p['status_name'], ['Open Expansion','Open Research'])) $stagnant++;
    if (in_array($p['status_name'], ['Open Expansion','Open Research','Close Reject','Close Hold'])) {
        $days = $p['days_since_last_update'] ?? 0;
        if ($days > 7 && $days < 14) $pending7++;
        if ($days > 14) $pending14++;
        if ($days > 30) $pending30++;
        if ($days > 60) $pending60++;
        if ($days >= 365) $pendingYear++;
    }
    $projBudget = $projectBudgetMap[(int)$p['id']]['budget'] ?? 0;
    $projCost = $projectBudgetMap[(int)$p['id']]['cost'] ?? 0;
    if ($projCost > $projBudget && $projBudget > 0) $overBudget++;
    if ($projCost < $projBudget && $projBudget > 0) $savingBudget++;

    $pic = trim($p['pic'] ?? '');
    if ($pic && in_array($p['status_name'], ['Open Expansion','Open Research'])) {
        $lud = $p['last_update_date'] ?? null;
        if (!$lud || strtotime($lud) < strtotime('-7 day')) $picNonJob++;
    }

    $cat = $p['category_name'] ?? null;
    if ($cat) $catStatus[$cat][$p['status_name']] = ($catStatus[$cat][$p['status_name']] ?? 0) + 1;

    $ben = $p['benefit_name'] ?? null;
    if ($ben) {
        if (!isset($bStats[$ben])) $bStats[$ben] = ['total'=>0,'expansion_count'=>0,'research_count'=>0,'finish_count'=>0,'hold_count'=>0,'reject_count'=>0];
        $bStats[$ben]['total']++;
        $sk = $p['status_name'];
        if ($sk === 'Open Expansion') $bStats[$ben]['expansion_count']++;
        elseif ($sk === 'Open Research') $bStats[$ben]['research_count']++;
        elseif ($sk === 'Close Finish') $bStats[$ben]['finish_count']++;
        elseif ($sk === 'Close Hold') $bStats[$ben]['hold_count']++;
        elseif ($sk === 'Close Reject') $bStats[$ben]['reject_count']++;
    }

    if ($pic) {
        if (!isset($picStats[$pic])) $picStats[$pic] = ['t'=>0,'c'=>0,'last_update'=>null];
        $picStats[$pic]['t']++;
        if (in_array($p['status_name'], ['Close Finish','Close Hold','Close Reject'])) $picStats[$pic]['c']++;
        $lud = $p['last_update_date'] ?? null;
        if ($lud && (!$picStats[$pic]['last_update'] || strtotime($lud) > strtotime($picStats[$pic]['last_update']))) {
            $picStats[$pic]['last_update'] = $lud;
        }
    }

    $pn = $p['priority_name'] ?? '';
    if ($pn === 'Given Priority') $givenPriorityCount++;
    elseif ($pn === 'Delegated') $delegatedCount++;
    elseif ($pn === 'Scheduled') $scheduleCount++;
}

$picJobCounts = qRows($pdo, "SELECT u.name AS pic_name, COUNT(*) AS job_count FROM users u JOIN project_activities pa ON pa.pic COLLATE utf8mb4_unicode_ci = u.name WHERE u.role_type IN ('PIC', 'Responsible Person / PIC') AND pa.date <= CURDATE() AND (pa.estimated_duration IS NULL OR pa.date + INTERVAL pa.estimated_duration DAY >= CURDATE()) GROUP BY u.name");
$picOnJobList = array_column(array_filter($picJobCounts, fn($r) => (int)$r['job_count'] > 0), 'pic_name');
$picAllRole = array_column(qRows($pdo, "SELECT u.name FROM users u WHERE u.role_type IN ('PIC', 'Responsible Person / PIC')"), 'name');
$picNonJobList = array_diff($picAllRole, $picOnJobList);

$procProject = $pdo->query("SELECT COUNT(DISTINCT project_id) FROM project_activities WHERE milestone_id = 5 AND (verified = 0 OR verified IS NULL)")->fetchColumn();
$procTimeline = $pdo->query("SELECT COUNT(*) FROM project_activities WHERE milestone_id = 5 AND (verified = 0 OR verified IS NULL)")->fetchColumn();

arsort($bStats);
sortCategoriesByOrder($catStatus);
$cats = array_keys($catStatus);
?>

<!-- Anggaran -->
<h3 class="text-sm font-bold text-slate-700 mb-3">Budget</h3>
<a href="projects.php" class="block bg-white rounded-2xl p-5 sm:p-6 border border-slate-100 stat-card shadow-sm mb-6 hover:shadow-md transition">
    <div class="flex items-center justify-center gap-8 sm:gap-16 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-cash-stack text-white text-sm sm:text-base"></i></div>
            <div>
                <div class="text-[11px] sm:text-xs font-medium text-slate-400">Total Budget</div>
                <div class="text-lg sm:text-xl font-extrabold text-slate-800 tracking-tight leading-tight"><?= formatRupiah($totalBudget) ?></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-receipt text-white text-sm sm:text-base"></i></div>
            <div>
                <div class="text-[11px] sm:text-xs font-medium text-slate-400">Total Expenditure</div>
                <div class="text-lg sm:text-xl font-extrabold text-slate-800 tracking-tight leading-tight"><?= formatRupiah($totalCost) ?></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-graph-up-arrow text-white text-sm sm:text-base"></i></div>
            <div>
                <div class="text-[11px] sm:text-xs font-medium text-slate-400">Total Remaining Budget</div>
                <div class="text-lg sm:text-xl font-extrabold <?= $totalProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?> tracking-tight leading-tight"><?= formatRupiah($totalProfit) ?></div>
            </div>
        </div>
    </div>
</a>

<!-- Projects -->
<h3 class="text-sm font-bold text-slate-700 mb-3">Projects</h3>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php statCard('Total Projects', $totalProjects, 'folder2-open', 'blue', '', '', "window.location.href='projects.php'"); ?>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm cursor-pointer" onclick="window.location.href='projects.php?status=Open&sort=last_update_desc'">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-play-circle text-white text-sm sm:text-base"></i></div>
            <span></span>
        </div>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-lg sm:text-xl font-extrabold text-slate-800 tracking-tight leading-tight"><?= $openC ?></div>
                <div class="text-[11px] sm:text-xs font-medium text-slate-400">Open Projects</div>
            </div>
            <div class="flex flex-col gap-1.5 items-end">
                <a href="projects.php?status=Open%20Expansion&expansion=1" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-2.5 py-1 text-[10px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition min-w-0"><span class="flex items-center gap-1"><i class="bi bi-arrow-up-right-circle"></i> Expansion</span><span class="font-bold"><?= $expansionCount ?></span></a>
                <a href="projects.php?status=Open%20Research&research=1" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-2.5 py-1 text-[10px] font-semibold text-cyan-700 bg-cyan-50 hover:bg-cyan-100 rounded-lg transition min-w-0"><span class="flex items-center gap-1"><i class="bi bi-flask"></i> Research</span><span class="font-bold"><?= $researchCount ?></span></a>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm cursor-pointer" onclick="window.location.href='projects.php?status=Close%20Finish&sort=last_update_desc'">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-check-circle text-white text-sm sm:text-base"></i></div>
            <span></span>
        </div>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-lg sm:text-xl font-extrabold text-slate-800 tracking-tight leading-tight"><?= $closeC ?></div>
                <div class="text-[11px] sm:text-xs font-medium text-slate-400">Closed Projects</div>
            </div>
            <div class="flex flex-col gap-1.5 items-end">
                <a href="projects.php?status=Close%20Finish&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition min-w-0"><span class="flex items-center gap-1"><i class="bi bi-check-circle"></i> Finish</span><span class="font-bold"><?= $finishC ?></span></a>
                <a href="projects.php?status=Close%20Hold&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-2.5 py-1 text-[10px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition min-w-0"><span class="flex items-center gap-1"><i class="bi bi-pause-circle"></i> Hold</span><span class="font-bold"><?= $holdC ?></span></a>
                <a href="projects.php?status=Close%20Reject&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-2.5 py-1 text-[10px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition min-w-0"><span class="flex items-center gap-1"><i class="bi bi-x-circle"></i> Reject</span><span class="font-bold"><?= $rejectC ?></span></a>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-lightning-charge-fill text-white text-sm sm:text-base"></i></div>
        </div>
        <div class="mb-3">
            <div class="text-sm font-bold text-slate-800">Classification by Priority</div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <a href="projects.php?priority=Given%20Priority&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition"><span>Given Priority</span><span class="text-sm font-bold"><?= $givenPriorityCount ?></span></a>
            <a href="projects.php?priority=Delegated&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition"><span>Delegated</span><span class="text-sm font-bold"><?= $delegatedCount ?></span></a>
            <a href="projects.php?priority=Scheduled&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition"><span>Scheduled</span><span class="text-sm font-bold"><?= $scheduleCount ?></span></a>
        </div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-tags text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Classification by Goals</span>
        </div>
        <div class="p-4 space-y-2">
            <?php foreach ($bStats as $ben => $bs): ?>
            <div class="flex items-center justify-between cursor-pointer hover:bg-slate-50 rounded-lg px-3 py-2 -mx-3 transition" onclick="window.location.href='projects.php?benefit=<?= urlencode($ben) ?>&sort=last_update_desc'">
                <span class="text-sm font-bold text-slate-700"><?= safe($ben) ?></span>
                <div class="text-right">
                    <div class="text-[10px] text-slate-400 mb-1"><?= $bs['total'] ?> projects</div>
                    <div class="flex gap-1 justify-end flex-wrap">
                            <a href="projects.php?status=Open%20Expansion&benefit=<?= urlencode($ben) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-600 hover:bg-blue-100 transition min-w-0"><span>Expansion</span><span class="font-bold"><?= $bs['expansion_count'] ?></span></a>
                            <a href="projects.php?status=Open%20Research&benefit=<?= urlencode($ben) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-600 hover:bg-purple-100 transition min-w-0"><span>Research</span><span class="font-bold"><?= $bs['research_count'] ?></span></a>
                            <a href="projects.php?status=Close%20Finish&benefit=<?= urlencode($ben) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition min-w-0"><span>Finish</span><span class="font-bold"><?= $bs['finish_count'] ?></span></a>
                            <a href="projects.php?status=Close%20Hold&benefit=<?= urlencode($ben) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-600 hover:bg-amber-100 transition min-w-0"><span>Hold</span><span class="font-bold"><?= $bs['hold_count'] ?></span></a>
                            <a href="projects.php?status=Close%20Reject&benefit=<?= urlencode($ben) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 hover:bg-red-100 transition min-w-0"><span>Reject</span><span class="font-bold"><?= $bs['reject_count'] ?></span></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-collection text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Classification by Types</span>
        </div>
        <div class="p-4 space-y-3">
            <?php foreach ($cats as $cat):
                $catTotal = isset($catStatus[$cat]) ? array_sum($catStatus[$cat]) : 0;
            ?>
            <div class="cursor-pointer hover:bg-slate-50 rounded-lg px-3 py-2 -mx-3 transition" onclick="window.location.href='projects.php?category=<?= urlencode($cat) ?>&sort=last_update_desc'">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-700"><?= safe(categoryDisplay($cat)) ?></div>
                    <div class="text-right">
                        <div class="text-[10px] text-slate-400 mb-1"><?= $catTotal ?> projects</div>
                        <div class="flex flex-wrap justify-end gap-1">
                            <?php
                            $catLabels = [
                                ['key' => 'Open Expansion', 'label' => 'Expansion', 'cls' => 'bg-blue-50 text-blue-600 hover:bg-blue-100'],
                                ['key' => 'Open Research', 'label' => 'Research', 'cls' => 'bg-purple-50 text-purple-600 hover:bg-purple-100'],
                                ['key' => 'Close Finish', 'label' => 'Finish', 'cls' => 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'],
                                ['key' => 'Close Hold', 'label' => 'Hold', 'cls' => 'bg-amber-50 text-amber-600 hover:bg-amber-100'],
                                ['key' => 'Close Reject', 'label' => 'Reject', 'cls' => 'bg-red-50 text-red-600 hover:bg-red-100'],
                            ];
                            foreach ($catLabels as $cl):
                                $count = $catStatus[$cat][$cl['key']] ?? 0;
                            ?>
                            <a href="projects.php?status=<?= urlencode($cl['key']) ?>&category=<?= urlencode($cat) ?>&sort=last_update_desc" onclick="event.stopPropagation()" class="inline-flex items-center justify-between gap-3 px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $cl['cls'] ?> transition min-w-0"><span><?= $cl['label'] ?></span><span class="font-bold"><?= $count ?></span></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Notifications -->
<h3 class="text-sm font-bold text-slate-700 mb-3">Notifications</h3>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-exclamation-triangle text-white text-sm"></i></div>
            <div class="text-sm font-bold text-slate-800">Projects Pending</div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <a href="projects.php?pending_days=gt7&status=Open&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition"><span>> 1 weeks</span><span class="text-sm font-bold"><?= $pending7 ?></span></a>
            <a href="projects.php?pending_days=gt14&status=Open&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-orange-700 bg-orange-50 hover:bg-orange-100 rounded-lg transition"><span>> 2 weeks</span><span class="text-sm font-bold"><?= $pending14 ?></span></a>
            <a href="projects.php?pending_days=gt30&status=Open&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition"><span>> 1 months</span><span class="text-sm font-bold"><?= $pending30 ?></span></a>
            <a href="projects.php?pending_days=gt60&status=Open&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-red-800 bg-red-100 hover:bg-red-200 rounded-lg transition"><span>> 2 months</span><span class="text-sm font-bold"><?= $pending60 ?></span></a>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-cash-coin text-white text-sm"></i></div>
            <div class="text-sm font-bold text-slate-800">Budgeting</div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <a href="projects.php?budget_status=over&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition"><span>Over budget</span><span class="text-sm font-bold"><?= $overBudget ?></span></a>
            <a href="projects.php?budget_status=saving&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition"><span>Saving budget</span><span class="text-sm font-bold"><?= $savingBudget ?></span></a>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-people text-white text-sm"></i></div>
            <div class="text-sm font-bold text-slate-800">Personnel</div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <a href="pic_stats.php?tab=pic&filter=nonjob" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition"><span>PIC Non Job</span><span class="text-sm font-bold"><?= count($picNonJobList) ?></span></a>
            <a href="pic_stats.php?tab=pic&filter=onjob" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition"><span>PIC On Job</span><span class="text-sm font-bold"><?= count($picOnJobList) ?></span></a>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-bag-check text-white text-sm"></i></div>
            <div class="text-sm font-bold text-slate-800">Procurement Status</div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <a href="projects.php?procurement_unverified=1&sort=last_update_desc" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition"><span>Project</span><span class="text-sm font-bold"><?= $procProject ?></span></a>
            <a href="procurement_unverified.php" class="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition"><span>Total Timeline</span><span class="text-sm font-bold"><?= $procTimeline ?></span></a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
});
</script>
<?php include 'includes/footer.php'; ?>
