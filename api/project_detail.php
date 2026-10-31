<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: projects.php'); exit; }
$project = qOne($pdo, "SELECT v.*, p.ranking, p.image_path, p.target_unit, p.completed_unit FROM v_project_full v LEFT JOIN projects p ON p.id = v.id WHERE v.id = :id", [':id' => $id]);
if (!$project) { header('Location: projects.php'); exit; }
$realStatus = qOne($pdo, "SELECT rs.name as status_name FROM projects pp INNER JOIN ref_status rs ON pp.status_id = rs.id WHERE pp.id = :id", [':id' => $id]);
if ($realStatus) $project['status_name'] = $realStatus['status_name'];
$page_title = $project['project_name'];
$page_icon = 'doc';
$isAdmin = isAdminLoggedIn();
include 'includes/header.php';

// Check if 'verified' column exists in project_activities
$hasVerified = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM project_activities LIKE 'verified'")->fetch();
    $hasVerified = !empty($colCheck);
} catch (Exception $e) {
    $hasVerified = false;
}

$activities = qRows($pdo, "SELECT pa.*, COALESCE(rm.name, 'Unassigned') as timeline_name FROM project_activities pa LEFT JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.project_id = :id ORDER BY COALESCE(rm.sort_order, 999) ASC, pa.row_order ASC, pa.date ASC", [':id' => $id]);
$planningRows = qRows($pdo, "SELECT ptl.*, rm.id AS planning_milestone_id, rm.name AS planning_milestone_name FROM project_planning_timeline ptl LEFT JOIN ref_milestone rm ON rm.name = ptl.milestone WHERE ptl.project_id = :id ORDER BY ptl.row_order ASC", [':id' => $id]);
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
    $leftDate = $left['date'] ?? '';
    $rightDate = $right['date'] ?? '';
    return [$leftDate, (int)($left['row_order'] ?? 0)] <=> [$rightDate, (int)($right['row_order'] ?? 0)];
});
$allPics = array_unique(array_filter(array_map(fn($a) => trim($a['pic'] ?? ''), $activities)));
$allPicsStr = $allPics ? implode(', ', $allPics) : '';
$costBreakdown = qRows($pdo, "SELECT * FROM cost_breakdown WHERE project_id = :id ORDER BY row_order ASC", [':id' => $id]);
$swot = qOne($pdo, "SELECT problem_overview, existing_condition, expected_condition, gap, action_plan, action_taken, strengths, weaknesses, opportunities, threats FROM projects WHERE id = :id", [':id' => $id]);
$driveLinks = qRows($pdo, "SELECT id, drive_url, label FROM project_drive_links WHERE project_id = :id ORDER BY id ASC", [':id' => $id]);
$developmentAnalysis = qRows($pdo, "SELECT problem_overview, existing_condition, expected_condition, gap, action FROM project_development_analysis WHERE project_id = :id ORDER BY row_order ASC", [':id' => $id]);
$actionAnalysis = qRows($pdo, "SELECT action, strengths, weaknesses, opportunities, threats FROM project_action_analysis WHERE project_id = :id ORDER BY row_order ASC", [':id' => $id]);

// Project order document link for the Project Analyze assessment panel.
// Short Proposal status comes from project_drive_links ($driveLinks above).
$hasDocLinks = false;
try {
    $colCheckDoc = $pdo->query("SHOW COLUMNS FROM `projects` LIKE 'project_order_link'")->fetch();
    $hasDocLinks = !empty($colCheckDoc);
} catch (Exception $e) {
    $hasDocLinks = false;
}
$projectOrderLink = '';
if ($hasDocLinks) {
    $projectOrderLink = trim((string)qVal($pdo, "SELECT project_order_link FROM projects WHERE id = :id", [':id' => $id]));
}
$hasProposalLink = !empty($driveLinks);
$rankingSet = !empty($project['ranking']);
$hasPhoto = !empty($project['image_path']);
$targetSet = (int)($project['target_unit'] ?? 0) > 0;

if (!function_exists('assessmentIcon')) {
    function assessmentIcon($filled) {
        return $filled
            ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Sudah terisi"><i class="bi bi-check-lg"></i></span>'
            : '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Belum terisi"><i class="bi bi-x-lg"></i></span>';
    }
}

if ($developmentAnalysis) {
    foreach (['problem_overview', 'existing_condition', 'expected_condition', 'gap'] as $field) {
        if (empty(trim((string)($swot[$field] ?? '')))) {
            $values = array_filter(array_column($developmentAnalysis, $field), fn($value) => trim((string)$value) !== '');
            if ($values) $swot[$field] = implode("\n\n", $values);
        }
    }
    if (empty(trim((string)($swot['action_plan'] ?? '')))) {
        $values = array_filter(array_column($developmentAnalysis, 'action'), fn($value) => trim((string)$value) !== '');
        if ($values) $swot['action_plan'] = implode("\n\n", $values);
    }
}
if ($actionAnalysis) {
    foreach (['strengths', 'weaknesses', 'opportunities', 'threats'] as $field) {
        if (empty(trim((string)($swot[$field] ?? '')))) {
            $values = array_filter(array_column($actionAnalysis, $field), fn($value) => trim((string)$value) !== '');
            if ($values) $swot[$field] = implode("\n\n", $values);
        }
    }
    if (empty(trim((string)($swot['action_taken'] ?? '')))) {
        $values = array_filter(array_column($actionAnalysis, 'action'), fn($value) => trim((string)$value) !== '');
        if ($values) $swot['action_taken'] = implode("\n\n", $values);
    }
}

$picList = $pdo->query("SELECT name FROM users WHERE role_type IN ('PIC', 'Responsible Person / PIC') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$projectList = $pdo->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

$allStatus = $pdo->query("SELECT id, name FROM ref_status ORDER BY name")->fetchAll();
$allPriority = $pdo->query("SELECT id, name FROM ref_priority ORDER BY name")->fetchAll();
$allTypes = $pdo->query("SELECT id, name FROM ref_category ORDER BY name")->fetchAll();
$allBenefit = $pdo->query("SELECT id, name FROM ref_benefit ORDER BY name")->fetchAll();
$allUsers = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

$totalActivities = count($activities);
$firstActivity = $activities[array_key_last($activities)]['date'] ?? null;
$lastActivity = $activities[0]['date'] ?? null;
$totalCostBreakdown = array_sum(array_column($costBreakdown, 'actual_cost'));
$totalPlannedCost = array_sum(array_column($costBreakdown, 'planned_cost'));

$emptyPR = 0;
$emptyPO = 0;
foreach ($activities as $act) {
    if ((int)($act['milestone_id'] ?? 0) === 5) {
        if (empty($act['pr_number']) || $act['pr_number'] == '-' || $act['pr_number'] == '') $emptyPR++;
        if (empty($act['po_number']) || $act['po_number'] == '-' || $act['po_number'] == '') $emptyPO++;
    }
}
$milestones = [
    ['id' => 1, 'key' => 'Project Analyze', 'icon' => 'bi-search', 'color' => 'blue'],
    ['id' => 2, 'key' => 'Project Desain', 'icon' => 'bi-pencil-square', 'color' => 'indigo'],
    ['id' => 3, 'key' => 'Timeline Desain', 'icon' => 'bi-calendar-range', 'color' => 'violet'],
    ['id' => 4, 'key' => 'Budgeting', 'icon' => 'bi-cash-stack', 'color' => 'cyan'],
    ['id' => 5, 'key' => 'Procurement', 'icon' => 'bi-cart-check', 'color' => 'teal'],
    ['id' => 8, 'key' => 'Development', 'icon' => 'bi-gear', 'color' => 'blue'],
    ['id' => 9, 'key' => 'Trial Evaluation', 'icon' => 'bi-clipboard-data', 'color' => 'amber'],
    ['id' => 10, 'key' => 'Launching', 'icon' => 'bi-rocket-takeoff', 'color' => 'red'],
];

$milestoneCount = [];
$milestoneVerified = [];
foreach ($milestones as $m) {
    $milestoneCount[$m['id']] = 0;
    $milestoneVerified[$m['id']] = ['total' => 0, 'verified' => 0];
}
foreach ($activities as $act) {
    $mid = (int)($act['milestone_id'] ?? 0);
    if (isset($milestoneCount[$mid])) {
        $milestoneCount[$mid]++;
        $milestoneVerified[$mid]['total']++;
        if (!empty($act['verified'])) $milestoneVerified[$mid]['verified']++;
    }
}
$endDateComputed = $project['end_date'] ?? null;
$durasiHari = qVal($pdo, "SELECT SUM(DATEDIFF(pa.verified_date, pa.date)) FROM project_activities pa WHERE pa.project_id = :pid AND pa.verified = 1 AND pa.verified_date IS NOT NULL AND pa.date IS NOT NULL", [':pid' => $id]);
$estDuration = qVal($pdo, "SELECT SUM(pa.estimated_duration) FROM project_activities pa WHERE pa.project_id = :pid AND pa.estimated_duration IS NOT NULL", [':pid' => $id]);
$durasiActual = ($durasiHari !== null && $durasiHari !== '') ? (int)$durasiHari : null;
$durasiEst = ($estDuration !== null && $estDuration !== '') ? (int)$estDuration : null;
$durasiColor = '#334155';
if ($durasiActual !== null && $durasiEst !== null) {
    $durasiColor = $durasiActual > $durasiEst ? '#ef4444' : ($durasiActual < $durasiEst ? '#16a34a' : '#334155');
}


?>
<?php if (isset($_GET['msdebug'])): ?>
<div style="margin:1rem 0;padding:.75rem 1rem;background:#0f172a;color:#4ade80;font:12px/1.6 monospace;border-radius:10px;word-break:break-all;">
<b>MODUL api/project_detail.php v3 AKTIF</b> — baris "Project Photos/Videos" SUDAH DIHAPUS dari card Project Desain — Assessment.<br>
File: <?= safe(__FILE__) ?><br>
Last modified: <?= date('Y-m-d H:i:s', filemtime(__FILE__)) ?> | PHP <?= PHP_VERSION ?>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav class="mb-6 overflow-x-auto"><ol class="flex items-center gap-2 text-sm whitespace-nowrap"><li><a href="index.php" class="text-slate-400 hover:text-blue-500 transition">Dashboard</a></li><li class="text-slate-300">/</li><li><a href="projects.php" class="text-slate-400 hover:text-blue-500 transition">Projects</a></li><li class="text-slate-300">/</li><li class="text-slate-600 font-medium truncate max-w-[150px] sm:max-w-[200px]"><?= safe($project['project_name']) ?></li></ol></nav>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div id="projectImageBox" onclick="<?= $canWrite ? 'toggleImageAction()' : 'openImageViewer()' ?>" class="relative flex-shrink-0 w-24 h-24 rounded-xl overflow-hidden <?= ($canWrite || !empty($project['image_path'])) ? 'cursor-pointer group' : '' ?> <?= empty($project['image_path']) ? 'border-2 border-dashed border-slate-300' . ($canWrite ? ' hover:border-blue-400 bg-slate-50 hover:bg-blue-50' : ' bg-slate-50') : 'border border-slate-200' ?> transition-all duration-200">
            <?php if (!empty($project['image_path'])): ?>
                <img id="projectImage" src="<?= safe($project['image_path']) ?>" alt="Project Image" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="w-full h-full flex-col items-center justify-center text-slate-400" style="display:none">
                    <i class="bi bi-image text-xl mb-0.5"></i>
                    <span class="text-[9px] font-semibold">No Image</span>
                </div>
            <?php else: ?>
                <div id="projectImagePlaceholder" class="w-full h-full flex flex-col items-center justify-center text-slate-400 <?= $canWrite ? 'group-hover:text-blue-500 transition-colors' : '' ?>">
                    <i class="bi bi-camera text-xl mb-0.5"></i>
                    <?php if ($canWrite): ?>
                    <span class="text-[9px] font-semibold">Add Photo</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <h2 class="text-xl font-extrabold text-slate-800 mb-2"><?= safe($project['project_name']) ?></h2>
            <div class="flex flex-wrap items-center gap-2">
                <?php if (!empty($project['ranking'])): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700"><i class="bi bi-sort-numeric-down mr-1"></i>Rank #<?= (int)$project['ranking'] ?></span>
                <?php endif; ?>
                <?= statusBadge($project['status_name']) ?>
                <?= priorityBadge($project['priority_name']) ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600"><i class="bi <?= categoryIcon($project['category_name']) ?> mr-1"></i><?= safe(categoryDisplay($project['category_name'])) ?></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border-l-2" style="border-left-color:<?= benefitColor($project['benefit_name']) ?>"><?= safe($project['benefit_name']) ?></span>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($canWrite): ?>
        <button onclick="openEditProjectModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-blue-500 rounded-lg" title="Edit Project"><i class="bi bi-pencil-square"></i> Edit</button>
        <button onclick="deleteProject()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-red-500 rounded-lg" title="Delete Project"><i class="bi bi-trash"></i> Hapus</button>
        <?php endif; ?>
        <button onclick="openDriveLinksModal(true)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-500 hover:bg-emerald-600 rounded-lg transition" title="Download Short Proposal"><i class="bi bi-download"></i> Short Proposal</button>
        <div class="text-right text-xs text-slate-400 ml-2">
            <div>ID: #<?= $project['id'] ?></div>
            <div>Created: <?= safe($project['created_at']) ?: '-' ?></div>
        </div>
    </div>
</div>

<!-- Milestone Stepper -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-4 overflow-x-auto">
        <div class="flex items-center gap-0 min-w-max" id="milestoneStepper">
            <?php foreach ($milestones as $i => $m):
                $count = $milestoneCount[$m['id']];
                $verified = $milestoneVerified[$m['id']]['verified'];
                $total = $milestoneVerified[$m['id']]['total'];
                $allVerified = $total > 0 && $verified === $total;
                $hasActivities = $count > 0;
            ?>
            <button onclick="filterTimeline(<?= (int)$m['id'] ?>)" data-ms="<?= (int)$m['id'] ?>" class="ms-step flex items-center gap-1.5 px-3 py-2 rounded-lg text-[11px] font-semibold transition-all whitespace-nowrap <?= $allVerified ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($hasActivities ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-50 text-slate-400 border border-slate-200 hover:bg-slate-100') ?>">
                <?php if ($allVerified): ?><i class="bi bi-check-circle-fill text-emerald-500"></i><?php elseif ($hasActivities): ?><i class="bi bi-x-circle-fill text-red-500"></i><?php else: ?><i class="bi <?= $m['icon'] ?>"></i><?php endif; ?>
                <?= $m['key'] ?>
            </button>
            <?php if ($i < count($milestones) - 1): ?>
            <i class="bi bi-chevron-right text-slate-300 mx-0.5 flex-shrink-0"></i>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div id="msActive" class="hidden mt-2 text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full inline-block">
            Filter: <span id="msActiveName"></span>
            <button onclick="filterTimeline(0)" class="ml-1 text-blue-400 hover:text-blue-600"><i class="bi bi-x-circle"></i></button>
        </div>
    </div>
</div>

<!-- Project Analyze Assessment -->
<div id="analyzeAssessment" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6 hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-clipboard-check text-blue-500"></i>
        <span class="text-sm font-bold text-slate-700">Project Analyze &mdash; Document Assessment</span>
        <span class="ml-auto inline-flex items-center gap-1.5 text-sm font-bold text-blue-700 bg-blue-50 border border-blue-100 px-3 py-1.5 rounded-full whitespace-nowrap" title="Responsible Person">
            <i class="bi bi-person-workspace text-blue-500 text-base"></i> <?= safe($project['responsible_person_name'] ?: 'Not assigned') ?>
        </span>
    </div>
    <div class="p-5 overflow-x-auto">
        <table class="w-full text-xs border border-slate-300" style="min-width:640px;">
            <thead>
                <tr class="bg-slate-100">
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Document</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:90px;">Status</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:210px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top">Proposal Documents</td>
                    <td id="docStatus-short_proposal_link" class="px-3 py-2 border border-slate-300 align-top text-center">
                        <?php if ($hasProposalLink): ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Link sudah diisi"><i class="bi bi-check-lg"></i></span>
                        <?php else: ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Link belum diisi"><i class="bi bi-x-lg"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 align-top">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button onclick="openProposalLinkModal()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i>Update</button>
                            <button onclick="downloadProposalDocuments()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition"><i class="bi bi-download"></i>Download</button>
                        </div>
                    </td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top">Project Order Document</td>
                    <td id="docStatus-project_order_link" class="px-3 py-2 border border-slate-300 align-top text-center">
                        <?php if ($projectOrderLink !== ''): ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Link sudah diisi"><i class="bi bi-check-lg"></i></span>
                        <?php else: ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Link belum diisi"><i class="bi bi-x-lg"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 align-top">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button onclick="openDocLinkModal('project_order_link','Project Order Document')" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i>Update</button>
                            <button onclick="downloadProjectOrderDocument()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition"><i class="bi bi-download"></i>Download</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- SWOT & Analysis -->
<div id="swotCard" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="bi bi-diagram-3 text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">SWOT Analysis & Problem Solving</span>
        </div>
        <?php if ($canWrite): ?>
        <button onclick="document.getElementById('swotModal').classList.remove('hidden')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i> Edit</button>
        <?php endif; ?>
    </div>
    <div class="p-5">
        <!-- Display Mode -->
        <div id="swotDisplay">
            <!-- Development Analysis Table -->
            <h6 class="text-sm font-bold text-slate-700 mb-2">Development Analysis</h6>
            <div class="overflow-x-auto mb-5">
                <table class="w-full text-xs border border-slate-300">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:40px;">No</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Problem Overview</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Existing Condition</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Expected Condition</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">GAP</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $devRows = [
                            ['po' => $swot['problem_overview'] ?? '', 'ec' => $swot['existing_condition'] ?? '', 'ex' => $swot['expected_condition'] ?? '', 'gp' => $swot['gap'] ?? '', 'ap' => $swot['action_plan'] ?? '']
                        ];
                        foreach ($devRows as $i => $r):
                            $n = $i + 1;
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $n ?>.</td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['po']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['ec']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['ex']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['gp']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['ap']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Action Analysis Table -->
            <h6 class="text-sm font-bold text-slate-700 mb-2">Action Analysis</h6>
            <div class="overflow-x-auto">
                <table class="w-full text-xs border border-slate-300">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:40px;">No</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Action</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Strengths</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Weaknesses</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Opportunities</th>
                            <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Threats</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $actRows = [
                            ['act' => $swot['action_taken'] ?? '', 's' => $swot['strengths'] ?? '', 'w' => $swot['weaknesses'] ?? '', 'o' => $swot['opportunities'] ?? '', 't' => $swot['threats'] ?? '']
                        ];
                        foreach ($actRows as $i => $r):
                            $n = $i + 1;
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $n ?>.</td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['act']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['s']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['w']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['o']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                            <td class="px-3 py-2 border border-slate-300 text-slate-600 whitespace-pre-line align-top"><?= nl2br(safe(trim($r['t']))) ?: '<span class="text-slate-400 italic">-</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Project Desain Assessment -->
<div id="designAssessment" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6 hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-clipboard-check text-indigo-500"></i>
        <span class="text-sm font-bold text-slate-700">Project Desain &mdash; Assessment</span>
    </div>
    <div class="p-5 overflow-x-auto">
        <table class="w-full text-xs border border-slate-300" style="min-width:640px;">
            <thead>
                <tr class="bg-slate-100">
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">Assessment</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:90px;">Status</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:210px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top">Ranking Project</td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center"><?= assessmentIcon($rankingSet) ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top">
                        <?php if ($canWrite): ?>
                        <button onclick="editRankingProject()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i>Update</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top">Machine Target</td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center"><?= assessmentIcon($targetSet) ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top">
                        <?php if ($canWrite): ?>
                        <button onclick="editMachineTarget()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i>Update</button>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Timeline Desain Assessment -->
<div id="timelineAssessment" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6 hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-calendar-range text-violet-500"></i>
        <span class="text-sm font-bold text-slate-700">Timeline Desain &mdash; Assessment</span>
    </div>
    <div class="p-5 overflow-x-auto">
        <?php if (count($activities) > 0): ?>
        <table class="w-full text-xs border border-slate-300" style="min-width:780px;">
            <thead>
                <tr class="bg-slate-100">
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:45px;">NO</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">TIMELINE</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">MILESTONE</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">REMARK</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:110px;">EST. DURATION</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:100px;">START</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:100px;">END</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:150px;">VERIFY</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $i => $act): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $i + 1 ?>.</td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= safe($act['timeline'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600"><?= safe($act['timeline_name'] ?: '-') ?></span></td>
                    <td class="px-3 py-2 border border-slate-300 text-[10px] text-slate-600 whitespace-pre-line break-words align-top" title="<?= safe($act['remarks'] ?: '') ?>"><?= safe($act['remarks'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= isset($act['estimated_duration']) && $act['estimated_duration'] !== null ? (int)$act['estimated_duration'] . ' hari' : '-' ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= fmtDate($act['date'] ?? null) ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= fmtDate($act['verified_date'] ?? null) ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <div class="flex items-center justify-center gap-2">
                            <?php if (!empty($act['verified'])): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Verified"><i class="bi bi-check-lg"></i></span>
                            <?php else: ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Unverified"><i class="bi bi-x-lg"></i></span>
                            <?php if ($canWrite && (int)($act['id'] ?? 0) > 0): ?>
                            <button onclick="verifyTimeline(<?= (int)$act['id'] ?>, '<?= safe(addslashes($act['timeline_name'] ?? '')) ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition" title="Verify">
                                <i class="bi bi-check-lg"></i> Verify
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="p-8 text-center"><i class="bi bi-inbox text-3xl text-slate-300 block mb-2"></i><p class="text-sm text-slate-400">No timeline yet</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Budgeting Assessment -->
<div id="budgetAssessment" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6 hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-cash-stack text-cyan-500"></i>
        <span class="text-sm font-bold text-slate-700">Budgeting &mdash; Assessment</span>
    </div>
    <div class="p-5 overflow-x-auto">
        <?php if (count($activities) > 0): ?>
        <table class="w-full text-xs border border-slate-300" style="min-width:920px;">
            <thead>
                <tr class="bg-slate-100">
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:45px;">NO</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">TIMELINE</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">MILESTONE</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">REMARK</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:110px;">EST. DURATION</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:100px;">START</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:100px;">END</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:150px;">VERIFY</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:90px;">UPDATE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $i => $act): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $i + 1 ?>.</td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= safe($act['timeline'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600"><?= safe($act['timeline_name'] ?: '-') ?></span></td>
                    <td class="px-3 py-2 border border-slate-300 text-[10px] text-slate-600 whitespace-pre-line break-words align-top" title="<?= safe($act['remarks'] ?: '') ?>"><?= safe($act['remarks'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $act['estimated_duration'] !== null ? (int)$act['estimated_duration'] . ' hari' : '-' ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= fmtDate($act['date'] ?? null) ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= fmtDate($act['verified_date'] ?? null) ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <div class="flex items-center justify-center gap-2">
                            <?php if (!empty($act['verified'])): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Verified"><i class="bi bi-check-lg"></i></span>
                            <?php else: ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Unverified"><i class="bi bi-x-lg"></i></span>
                            <?php if ($canWrite && (int)($act['id'] ?? 0) > 0): ?>
                            <button onclick="verifyTimeline(<?= (int)$act['id'] ?>, '<?= safe(addslashes($act['timeline_name'] ?? '')) ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition" title="Verify">
                                <i class="bi bi-check-lg"></i> Verify
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <?php if ($canWrite && (int)($act['id'] ?? 0) > 0): ?>
                        <button onclick="openEditTimeline(<?= (int)$act['id'] ?>, '<?= safe(addslashes($act['timeline'] ?? '')) ?>', '<?= safe(addslashes($act['pic'] ?? '')) ?>', '<?= safe(addslashes($act['pr_number'] ?? '')) ?>', '<?= safe(addslashes($act['po_number'] ?? '')) ?>', '<?= safe(addslashes($act['remarks'] ?? '')) ?>', '<?= safe($act['date'] ?? '') ?>', <?= (int)($act['estimated_duration'] ?? 0) ?>, <?= (int)$act['milestone_id'] ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition" title="Edit">
                            <i class="bi bi-pencil-square"></i> Update
                            </button>
                        <?php else: ?>
                        <span class="text-slate-300">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="p-8 text-center"><i class="bi bi-inbox text-3xl text-slate-300 block mb-2"></i><p class="text-sm text-slate-400">No timeline yet</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs -->
<?php if (!$isAdmin || canAccessExecutiveDashboard()): ?>
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <?php kpiCard('Responsible Person', $project['responsible_person_name'] ?: '-', 'project lead', 'person-workspace', 'blue'); ?>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-clock-history text-white text-sm sm:text-base"></i></div>
        </div>
        <div class="text-lg sm:text-xl font-extrabold tracking-tight leading-tight"><?php
            if ($durasiActual !== null) {
                $estStr = $durasiEst !== null ? $durasiEst . ' day' : '- day';
                echo '<span style="color:' . $durasiColor . ';">' . $durasiActual . ' day</span> <span style="color:#64748b;font-size:0.85em;">/</span> <span style="color:#334155;">' . $estStr . '</span>';
            } else {
                echo '<span style="color:#cbd5e1;">-</span>';
            }
        ?></div>
        <div class="text-[10px] text-slate-400 mt-1">days of work</div>
    </div>
    <?php kpiCard('Budget', formatRupiah($totalPlannedCost), 'planned', 'cash-stack', 'blue'); ?>
    <?php kpiCard('Expenditure', formatRupiah($totalCostBreakdown), 'actual', 'receipt', 'cyan'); ?>
    <?php kpiCard('Remaining Budget', formatRupiah($totalPlannedCost - $totalCostBreakdown), ($totalPlannedCost - $totalCostBreakdown) >= 0 ? 'profit' : 'loss', 'graph-up-arrow', ($totalPlannedCost - $totalCostBreakdown) >= 0 ? 'green' : 'red'); ?>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 stat-card">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center"><i class="bi bi-bullseye text-white text-sm sm:text-base"></i></div>
        </div>
        <div class="text-lg sm:text-xl font-extrabold tracking-tight leading-tight">
            <span style="color:#16a34a;"><?= (int)($project['completed_unit'] ?? 0) ?></span>
            <span style="color:#64748b;font-size:0.85em;"> / </span>
            <span style="color:#334155;"><?= (int)($project['target_unit'] ?? 0) ?></span>
        </div>
        <div class="text-[10px] text-slate-400 mt-1">Unit / Machine completed</div>
    </div>
    <?php kpiCard('Total Activities', $totalActivities, 'activities recorded', 'activity', 'purple'); ?>
</div>
<?php endif; ?>

<!-- Info + Summary -->
<!-- Timeline Table -->
<div id="timelineCard" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="bi bi-clock-history text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Activity Timeline</span>
            <span id="tlCount" class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= count($activities) ?> activities</span>
        </div>
        <?php if ($canWrite): ?>
        <button onclick="openAddTimeline()" class="w-9 h-9 rounded-lg bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center transition" title="Add Timeline"><i class="bi bi-plus-lg text-sm"></i></button>
        <?php endif; ?>
    </div>
    <?php if (count($activities) > 0): ?>
    <div class="tbl-scroll">
        <table class="w-full text-left" style="min-width:1100px;">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tbl-freeze-head" style="min-width:140px;">Timeline</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">Milestone</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">PIC</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">Projects</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">Remark</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase" data-col="pr">PR Number</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase" data-col="po">PO Number</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">Start</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase text-center">Est. Duration</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase text-center">Actual Duration</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase">Last Update</th>
                    <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase text-center">Verify</th>
                </tr>
            </thead>
            <tbody id="tlBody" class="divide-y divide-slate-100">
                <?php foreach ($activities as $act):
                    $startDate = $act['date'] ?? null;
                    $verifiedDate = $act['verified_date'] ?? null;
                    $estimatedDays = $act['estimated_duration'] ?? null;
                    $actualDays = null;
                    $durationDiff = null;
                    $durationClass = '';
                    if ($startDate && $verifiedDate) {
                        $actualDays = (int)date_diff(date_create($startDate), date_create($verifiedDate))->days;
                        if ($estimatedDays !== null) {
                            $diff = $actualDays - $estimatedDays;
                            $durationDiff = ($diff > 0 ? '+' : '') . $diff . ' hari';
                            $durationClass = $diff < 0 ? 'text-emerald-600' : ($diff > 0 ? 'text-red-600' : 'text-blue-600');
                        }
                    }
                ?>
                <tr class="tl-row hover:bg-slate-50/50 transition" data-ms="<?= (int)($act['milestone_id'] ?? 0) ?>">
                    <td class="px-4 py-3 text-xs text-slate-600 tbl-freeze-col" style="min-width:140px;background:inherit;"><?= safe($act['timeline'] ?: '-') ?></td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600"><?= safe($act['timeline_name'] ?: '-') ?></span></td>
                    <td class="px-4 py-3 text-xs text-slate-600"><?= safe($act['pic'] ?: '-') ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600"><?= safe($project['project_name']) ?></td>
                    <td class="px-4 py-3 text-[10px] text-slate-600 whitespace-pre-line break-words" style="min-width:260px;max-width:420px;" title="<?= safe($act['remarks'] ?: '') ?>"><?= safe($act['remarks'] ?: '-') ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600" data-col="pr"><?= safe($act['pr_number'] ?: '-') ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600" data-col="po"><?= safe($act['po_number'] ?: '-') ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600"><?= fmtDate($act['date']) ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600 text-center"><?= $estimatedDays !== null ? $estimatedDays . ' hari' : '-' ?></td>
                    <td class="px-4 py-3 text-xs text-center">
                        <?php if ($actualDays !== null): ?>
                            <span class="font-semibold"><?= $actualDays ?> hari</span>
                            <?php if ($durationDiff): ?>
                                <span class="block text-[10px] <?= $durationClass ?>"><?= $durationDiff ?></span>
                            <?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600"><?= $verifiedDate ? fmtDate($verifiedDate) : '-' ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($canWrite): ?>
                        <?php if (empty($act['verified'])): ?>
                            <button onclick="verifyTimeline(<?= $act['id'] ?>, '<?= safe($act['timeline_name'] ?? '') ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition" title="Verify">
                                <i class="bi bi-check-lg"></i> Verify
                            </button>
                        <?php else: ?>
                            <button onclick="unverifyTimeline(<?= $act['id'] ?>, '<?= safe($act['timeline_name'] ?? '') ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition" title="Batalkan Verify">
                                <i class="bi bi-x-lg"></i> Batal
                            </button>
                        <?php endif; ?>
                        <button onclick="openEditTimeline(<?= (int)$act['id'] ?>, '<?= safe(addslashes($act['timeline'] ?? '')) ?>', '<?= safe(addslashes($act['pic'] ?? '')) ?>', '<?= safe(addslashes($act['pr_number'] ?? '')) ?>', '<?= safe(addslashes($act['po_number'] ?? '')) ?>', '<?= safe(addslashes($act['remarks'] ?? '')) ?>', '<?= safe($act['date'] ?? '') ?>', <?= (int)($act['estimated_duration'] ?? 0) ?>, <?= (int)$act['milestone_id'] ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition ml-1" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button onclick="deleteTimeline(<?= (int)$act['id'] ?>, '<?= safe(addslashes($act['timeline'] ?? '')) ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg transition ml-1" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php else: ?>
                            <span class="text-[10px] font-semibold <?= empty($act['verified']) ? 'text-slate-400' : 'text-emerald-600' ?>"><?= empty($act['verified']) ? 'Unverified' : 'Verified' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center"><i class="bi bi-inbox text-3xl text-slate-300 block mb-2"></i><p class="text-sm text-slate-400">No activities yet</p></div>
    <?php endif; ?>
</div>

<script>
function fmtRupiah(v){if(v===null||v===undefined||v==='')return'-';return'Rp '+Number(v).toLocaleString('id-ID')}
function fmtRupiahInput(v){if(v===null||v===undefined||v==='')return'';return Number(v).toLocaleString('id-ID')}
function calcCbPlannedCost(){
    var qty=parseFloat(document.getElementById('cbPlannedQty').value)||0;
    var price=parseFloat(document.getElementById('cbPlannedPrice').value)||0;
    document.getElementById('cbPlannedCost').value=qty&&price?fmtRupiahInput(qty*price):'';
}
function calcCbExpenditure(){
    var aq=parseFloat(document.getElementById('cbActualQty').value)||0;
    var ap=parseFloat(document.getElementById('cbActualPrice').value)||0;
    document.getElementById('cbExpenditure').value=aq&&ap?fmtRupiahInput(aq*ap):'';
}
function openCbModal(){
    document.getElementById('cbAction').value='add';
    document.getElementById('cbId').value='';
    document.getElementById('cbForm').reset();
    document.getElementById('cbPlannedCost').value='';
    document.getElementById('cbExpenditure').value='';
    document.getElementById('cbModal').classList.remove('hidden');
}
function closeCbModal(){document.getElementById('cbModal').classList.add('hidden')}
function editCbItem(btn){
    document.getElementById('cbAction').value='edit';
    document.getElementById('cbId').value=btn.dataset.id;
    document.getElementById('cbItem').value=btn.dataset.item||'';
    document.getElementById('cbUnit').value=btn.dataset.unit||'';
    document.getElementById('cbPlannedPrice').value=btn.dataset.pp||'';
    document.getElementById('cbPlannedQty').value=btn.dataset.pq||'';
    document.getElementById('cbActualQty').value=btn.dataset.aq||'';
    document.getElementById('cbActualPrice').value=btn.dataset.ap||'';
    calcCbPlannedCost();
    calcCbExpenditure();
    document.getElementById('cbModal').classList.remove('hidden');
}
function deleteCbItem(id,btn){
    if(!confirm('Delete this item?'))return;
    var fd=new FormData();fd.append('action','delete');fd.append('project_id','<?= $id ?>');fd.append('cb_id',id);
    fetch('api/save_cost_breakdown.php',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.ok){var tr=btn.closest('tr');if(tr)tr.remove();var c=document.getElementById('cbCount');if(c)c.textContent=parseInt(c.textContent)-1}
        else alert(d.msg||'Failed')
    }).catch(function(e){alert('Error: '+e.message)})
}
document.addEventListener('DOMContentLoaded',function(){
document.getElementById('cbForm').addEventListener('submit',function(e){
    e.preventDefault();
    var btn=this.querySelector('button[type="submit"]');var orig=btn.innerHTML;
    btn.innerHTML='<i class="bi bi-hourglass-split mr-1"></i>Saving...';btn.disabled=true;
    var fd=new FormData(this);
    fetch('api/save_cost_breakdown.php',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        btn.innerHTML=orig;btn.disabled=false;
        if(d.ok){window.location.reload()}else{alert(d.msg||'Failed')}
    }).catch(function(e){btn.innerHTML=orig;btn.disabled=false;alert('Error: '+e.message)})
});
});
function filterTimeline(ms) {
    var rows = document.querySelectorAll('.tl-row');
    var count = 0;
    var steps = document.querySelectorAll('.ms-step');
    steps.forEach(function(s) {
        s.classList.remove('ring-2', 'ring-blue-400', 'bg-blue-50', 'text-blue-700', 'border-blue-300');
        if (ms && parseInt(s.getAttribute('data-ms')) === ms) {
            s.classList.add('ring-2', 'ring-blue-400', 'bg-blue-50', 'text-blue-700', 'border-blue-300');
        }
    });
    var milestoneNames = {1:'Project Analyze',2:'Project Desain',3:'Timeline Desain',4:'Budgeting',8:'Development',9:'Trial Evaluation',10:'Launching'};
    rows.forEach(function(row) {
        if (!ms || parseInt(row.getAttribute('data-ms')) === ms) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.querySelectorAll('[data-col="pr"]').forEach(function(el){el.style.display=(ms===5||!ms)?'':'none'});
    document.querySelectorAll('[data-col="po"]').forEach(function(el){el.style.display=(ms===5||!ms)?'':'none'});
    document.getElementById('tlCount').textContent = count + ' activities';
    var msActive = document.getElementById('msActive');
    var msActiveName = document.getElementById('msActiveName');
    if (ms) {
        msActiveName.textContent = milestoneNames[ms] || ms;
        msActive.classList.remove('hidden');
    } else {
        msActive.classList.add('hidden');
    }
    var analyzePanel = document.getElementById('analyzeAssessment');
    if (analyzePanel) {
        if (ms === 1) analyzePanel.classList.remove('hidden');
        else analyzePanel.classList.add('hidden');
    }
    // Card SWOT Analysis & Problem Solving menempel di bawah tabel Document Assessment:
    // ikut muncul hanya saat milestone Project Analyze (1) diklik.
    var swotCard = document.getElementById('swotCard');
    if (swotCard) {
        if (ms === 1) swotCard.classList.remove('hidden');
        else swotCard.classList.add('hidden');
    }
    var designPanel = document.getElementById('designAssessment');
    if (designPanel) {
        if (ms === 2) designPanel.classList.remove('hidden');
        else designPanel.classList.add('hidden');
    }
    var timelinePanel = document.getElementById('timelineAssessment');
    if (timelinePanel) {
        if (ms === 3) timelinePanel.classList.remove('hidden');
        else timelinePanel.classList.add('hidden');
    }
    var budgetPanel = document.getElementById('budgetAssessment');
    if (budgetPanel) {
        if (ms === 4) budgetPanel.classList.remove('hidden');
        else budgetPanel.classList.add('hidden');
    }
    // Card Activity Timeline hanya muncul saat milestone Development / Trial Evaluation / Launching diklik
    var timelineCard = document.getElementById('timelineCard');
    if (timelineCard) {
        if (ms === 8 || ms === 9 || ms === 10) timelineCard.classList.remove('hidden');
        else timelineCard.classList.add('hidden');
    }
}

function verifyTimeline(activityId, milestoneName) {
    if (!confirm('Verify timeline "' + milestoneName + '"?')) return;
    var fd = new FormData();
    fd.append('activity_id', activityId);
    fetch('api/verify_timeline.php', {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) { window.location.href = window.location.href; }
            else { alert(d.msg || 'Failed to verify'); }
        })
        .catch(function(e) { alert('Error: ' + e.message); });
}
function unverifyTimeline(activityId, milestoneName) {
    if (!confirm('Batalkan verify "' + milestoneName + '"?\nLast update dan durasi akan kosong.')) return;
    var fd = new FormData();
    fd.append('activity_id', activityId);
    fetch('api/unverify_timeline.php', {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) { window.location.href = window.location.href; }
            else { alert(d.msg || 'Failed to unverify'); }
        })
        .catch(function(e) { alert('Error: ' + e.message); });
}
function deleteTimeline(activityId, timelineName) {
    if (!confirm('Hapus activity "' + timelineName + '"?\nTindakan ini tidak dapat dibatalkan.')) return;
    var fd = new FormData();
    fd.append('activity_id', activityId);
    fetch('api/delete_activity.php', {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) { window.location.href = window.location.href; }
            else { alert(d.msg || 'Gagal menghapus'); }
        })
        .catch(function(e) { alert('Error: ' + e.message); });
}
</script>

<?php if (!$isAdmin || canAccessExecutiveDashboard()): ?>
<!-- Cost Breakdown -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="bi bi-wallet2 text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Material Expenditure Detail (<span id="cbCount"><?= count($costBreakdown) ?></span> item)</span>
        </div>
        <?php if ($canWrite): ?>
        <button onclick="openCbModal()" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition"><i class="bi bi-plus-lg mr-1"></i>Add Item</button>
        <?php endif; ?>
    </div>
    <div class="tbl-scroll max-h-[300px] overflow-y-auto">
        <table class="w-full text-sm" style="min-width:1000px;">
            <thead><tr class="bg-slate-50">
                <th class="text-left px-4 py-3 font-semibold text-slate-500 text-xs tbl-freeze-head" style="min-width:150px;">Item</th>
                <th class="hidden lg:table-cell text-right px-3 py-3 font-semibold text-slate-500 text-xs">Planned Unit</th>
                <th class="hidden sm:table-cell text-left px-3 py-3 font-semibold text-slate-500 text-xs">Unit</th>
                <th class="hidden md:table-cell text-right px-3 py-3 font-semibold text-slate-500 text-xs">Planned Price</th>
                <th class="hidden md:table-cell text-right px-3 py-3 font-semibold text-slate-500 text-xs">Planned Cost</th>
                <th class="text-right px-3 py-3 font-semibold text-slate-500 text-xs">Expenditure Unit</th>
                <th class="text-right px-3 py-3 font-semibold text-slate-500 text-xs">Expenditure Price</th>
                <th class="text-right px-3 py-3 font-semibold text-slate-500 text-xs">Expenditure</th>
                <th class="text-center px-3 py-3 font-semibold text-slate-500 text-xs w-20">Action</th>
            </tr></thead>
            <tbody id="cbTableBody" class="divide-y divide-slate-100">
            <?php foreach ($costBreakdown as $cb): ?>
            <tr class="hover:bg-slate-50/50 transition" data-cb-id="<?= (int)$cb['id'] ?>">
                <td class="px-4 py-3 text-xs text-slate-700 tbl-freeze-col" style="min-width:150px;background:inherit;"><?= safe($cb['item_description']) ?: '-' ?></td>
                <td class="hidden lg:table-cell px-3 py-3 text-right text-xs text-slate-600"><?= $cb['planned_quantity'] ?? '-' ?></td>
                <td class="hidden sm:table-cell px-3 py-3 text-xs text-slate-500"><?= safe($cb['unit']) ?: '-' ?></td>
                <td class="hidden md:table-cell px-3 py-3 text-right text-xs text-slate-600"><?= formatRupiah($cb['planned_price']) ?></td>
                <td class="hidden md:table-cell px-3 py-3 text-right text-xs text-slate-600"><?= formatRupiah($cb['planned_cost']) ?></td>
                <td class="px-3 py-3 text-right text-xs text-slate-600"><?= $cb['actual_quantity'] ?? '-' ?></td>
                <td class="px-3 py-3 text-right text-xs text-slate-600"><?= formatRupiah($cb['actual_price']) ?></td>
                <td class="px-3 py-3 text-right text-xs font-medium text-slate-700"><?= formatRupiah($cb['actual_cost']) ?></td>
                <td class="px-3 py-3 text-center">
                    <?php if ($canWrite): ?>
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="editCbItem(this)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-blue-600 flex items-center justify-center transition text-slate-400" title="Edit"
                            data-id="<?= (int)$cb['id'] ?>"
                            data-item="<?= htmlspecialchars($cb['item_description']) ?>"
                            data-unit="<?= htmlspecialchars($cb['unit'] ?? '') ?>"
                            data-pp="<?= $cb['planned_price'] ?? '' ?>"
                            data-pq="<?= $cb['planned_quantity'] ?? '' ?>"
                            data-aq="<?= $cb['actual_quantity'] ?? '' ?>"
                            data-ap="<?= $cb['actual_price'] ?? '' ?>"><i class="bi bi-pencil text-xs"></i></button>
                        <button onclick="deleteCbItem(<?= (int)$cb['id'] ?>, this)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-50 hover:text-red-600 flex items-center justify-center transition text-slate-400" title="Delete"><i class="bi bi-trash text-xs"></i></button>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Cost Breakdown Modal -->
<div id="cbModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCbModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden" style="animation:modalSlideIn .2s ease-out both">
        <form id="cbForm" class="p-5 space-y-4">
            <input type="hidden" name="action" id="cbAction" value="add">
            <input type="hidden" name="cb_id" id="cbId" value="">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Item Name <span class="text-red-400">*</span></label>
                <input type="text" name="item_description" id="cbItem" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="e.g. Connector, Cable, Sensor...">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Planned Unit</label>
                    <input type="number" name="planned_quantity" id="cbPlannedQty" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" min="0" oninput="calcCbPlannedCost()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Unit</label>
                    <input type="text" name="unit" id="cbUnit" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="pcs, m, kg...">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Planned Price (Rp)</label>
                    <input type="number" name="planned_price" id="cbPlannedPrice" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" min="0" oninput="calcCbPlannedCost()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Planned Cost (Rp)</label>
                    <input type="text" id="cbPlannedCost" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50 outline-none transition" placeholder="0" readonly>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Expenditure Unit</label>
                    <input type="number" name="actual_quantity" id="cbActualQty" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" min="0" oninput="calcCbExpenditure()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Expenditure Price (Rp)</label>
                    <input type="number" name="actual_price" id="cbActualPrice" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" min="0" oninput="calcCbExpenditure()">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Expenditure (Rp)</label>
                    <input type="text" id="cbExpenditure" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50 outline-none transition" placeholder="0" readonly>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeCbModal()" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- SWOT Edit Modal -->
<div id="swotModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('swotModal').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-3xl max-h-[85vh] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col" style="animation:modalSlideIn .2s ease-out both">
        <form method="POST" action="api/save_swot.php" class="p-5 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Problem Overview</label>
                    <textarea name="problem_overview" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Describe the main problem..."><?= safe($swot['problem_overview'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Existing Condition</label>
                    <textarea name="existing_condition" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Current condition..."><?= safe($swot['existing_condition'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Expected Condition</label>
                    <textarea name="expected_condition" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Expected condition..."><?= safe($swot['expected_condition'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Gap Analysis</label>
                    <textarea name="gap" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Gap between current condition and target..."><?= safe($swot['gap'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Action Plan</label>
                    <textarea name="action_plan" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Planned actions to be taken..."><?= safe($swot['action_plan'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Action Taken</label>
                    <textarea name="action_taken" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Actions already taken..."><?= safe($swot['action_taken'] ?? '') ?></textarea>
                </div>
            </div>
            <hr class="border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-emerald-600 mb-1"><i class="bi bi-plus-circle mr-1"></i>Strengths</label>
                    <textarea name="strengths" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition resize-y bg-emerald-50/50" placeholder="Project strengths..."><?= safe($swot['strengths'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-red-600 mb-1"><i class="bi bi-dash-circle mr-1"></i>Weaknesses</label>
                    <textarea name="weaknesses" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-red-200 focus:border-red-400 focus:ring-2 focus:ring-red-100 outline-none transition resize-y bg-red-50/50" placeholder="Project weaknesses..."><?= safe($swot['weaknesses'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-blue-600 mb-1"><i class="bi bi-arrow-up-circle mr-1"></i>Opportunities</label>
                    <textarea name="opportunities" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-blue-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y bg-blue-50/50" placeholder="Opportunities..."><?= safe($swot['opportunities'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-amber-600 mb-1"><i class="bi bi-arrow-down-circle mr-1"></i>Threats</label>
                    <textarea name="threats" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-amber-200 focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none transition resize-y bg-amber-50/50" placeholder="Threats..."><?= safe($swot['threats'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="document.getElementById('swotModal').classList.add('hidden')" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="mb-6">
    <a href="projects.php" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition"><i class="bi bi-arrow-left"></i> Back to Project List</a>
</div>

<!-- Success Toast -->
<div id="swotToast" class="fixed top-20 right-4 z-[10000] hidden">
    <div class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-check-lg text-emerald-600"></i></div>
        <span id="swotToastMsg" class="text-sm font-medium text-emerald-700">Saved successfully!</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var swotForm = document.querySelector('#swotModal form');
    if (swotForm) {
        swotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = swotForm.querySelector('button[type="submit"]');
            var origText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
            btn.disabled = true;
            var fd = new FormData(swotForm);
            fetch('api/save_swot.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        // Reload page to show updated data
                        window.location.href = window.location.href;
                    } else {
                        alert('Failed: ' + (d.msg || 'Unknown error'));
                        btn.innerHTML = origText;
                        btn.disabled = false;
                    }
                })
                .catch(function(err) {
                    alert('Error: ' + err.message);
                    btn.innerHTML = origText;
                    btn.disabled = false;
                });
        });
    }
    // Show toast if redirected with success
    if (new URLSearchParams(location.search).get('saved') === '1') {
        var toast = document.getElementById('swotToast');
        toast.classList.remove('hidden');
        setTimeout(function() { toast.classList.add('hidden'); }, 3000);
        history.replaceState(null, '', location.pathname);
    }
});

function verifyMilestone(name, milestoneId) {
    if (!confirm('Verify milestone "' + name + '"? This will add an activity update.')) return;
    var btn = event.target.closest('.verify-btn');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    btn.disabled = true;
    var fd = new FormData();
    fd.append('project_id', '<?= (int)$id ?>');
    fd.append('milestone_id', milestoneId);
    fd.append('milestone_name', name);
    fetch('api/verify_milestone.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                window.location.href = window.location.href;
            } else {
                alert('Failed: ' + (d.msg || 'Unknown error'));
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Verify';
                btn.disabled = false;
            }
        })
        .catch(function(err) {
            alert('Error: ' + err.message);
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Verify';
            btn.disabled = false;
        });
}

function openAddTimeline() {
    document.getElementById('tlFormAction').value = 'add';
    document.getElementById('addTimelineForm').reset();
    document.getElementById('timelineIdInput').value = '';
    document.getElementById('projectIdInput').value = '<?= (int)$id ?>';
    document.getElementById('addTimelineModal').classList.remove('hidden');
}
function openEditTimeline(id, timeline, pic, pr, po, remarks, date, est, milestoneId) {
    document.getElementById('tlFormAction').value = 'edit';
    document.getElementById('timelineIdInput').value = id;
    document.getElementById('projectIdInput').value = '<?= (int)$id ?>';
    document.querySelector('#addTimelineForm textarea[name="timeline"]').value = timeline;
    document.querySelector('#addTimelineForm input[name="pic"]').value = pic;
    document.querySelector('#addTimelineForm input[name="pr_number"]').value = pr;
    document.querySelector('#addTimelineForm input[name="po_number"]').value = po;
    document.querySelector('#addTimelineForm textarea[name="remarks"]').value = remarks;
    document.querySelector('#addTimelineForm input[name="date"]').value = date;
    document.querySelector('#addTimelineForm input[name="estimated_duration"]').value = est || '';
    document.getElementById('milestoneIdInput').value = milestoneId;
    var milestoneInput = document.querySelector('input[name="milestone_name"]');
    document.querySelectorAll('#milestoneList option').forEach(function(opt) {
        if (opt.getAttribute('data-id') == milestoneId) milestoneInput.value = opt.value;
    });
    document.getElementById('addTimelineModal').classList.remove('hidden');
}
function closeAddTimeline() {
    document.getElementById('addTimelineModal').classList.add('hidden');
}
</script>

<!-- Add Timeline Modal -->
<div id="addTimelineModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeAddTimeline()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="addTimelineForm" class="p-5 space-y-4">
            <input type="hidden" name="action" id="tlFormAction" value="add">
            <input type="hidden" name="timeline_id" id="timelineIdInput" value="">
            <input type="hidden" name="project_id" value="<?= (int)$id ?>">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Milestone</label>
                <input type="text" name="milestone_name" list="milestoneList" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Type or select milestone..." value="">
                <datalist id="milestoneList">
                    <?php foreach ($milestones as $m): ?>
                    <option value="<?= $m['key'] ?>" data-id="<?= $m['id'] ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="milestone_id" id="milestoneIdInput">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Project</label>
                <input type="text" name="project_name" list="projectList" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Type or select project..." value="<?= safe($project['project_name']) ?>">
                <datalist id="projectList">
                    <?php foreach ($projectList as $pl): ?>
                    <option value="<?= safe($pl['project_name']) ?>" data-id="<?= $pl['id'] ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="selected_project_id" id="projectIdInput" value="<?= (int)$id ?>">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Timeline (Description)</label>
                <textarea name="timeline" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Activity description..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">PIC</label>
                    <input type="text" name="pic" list="picList" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" value="<?= safe($project['pic']) ?>" placeholder="Type or select PIC...">
                    <datalist id="picList">
                        <?php foreach ($picList as $p): ?>
                        <option value="<?= safe($p) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">PR Number</label>
                    <input type="text" name="pr_number" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Optional">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">PO Number</label>
                    <input type="text" name="po_number" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Optional">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Remark</label>
                <textarea name="remarks" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition resize-y" placeholder="Additional notes..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Start Date</label>
                    <input type="date" name="date" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Est. Duration (hari)</label>
                    <input type="number" name="estimated_duration" min="0" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0">
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeAddTimeline()" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addForm = document.getElementById('addTimelineForm');
    var milestoneInput = document.querySelector('input[name="milestone_name"]');
    var milestoneIdInput = document.getElementById('milestoneIdInput');
    var milestoneOptions = document.querySelectorAll('#milestoneList option');
    
    var projectInput = document.querySelector('input[name="project_name"]');
    var projectIdInput = document.getElementById('projectIdInput');
    var projectOptions = document.querySelectorAll('#projectList option');

    milestoneInput.addEventListener('input', function() {
        var val = this.value;
        milestoneIdInput.value = '';
        milestoneOptions.forEach(function(opt) {
            if (opt.value === val) {
                milestoneIdInput.value = opt.getAttribute('data-id');
            }
        });
    });

    projectInput.addEventListener('input', function() {
        var val = this.value;
        projectIdInput.value = '';
        projectOptions.forEach(function(opt) {
            if (opt.value === val) {
                projectIdInput.value = opt.getAttribute('data-id');
            }
        });
    });

    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!milestoneIdInput.value) {
                alert('Please select a valid milestone from the list');
                milestoneInput.focus();
                return;
            }
            if (!projectIdInput.value) {
                alert('Please select a valid project from the list');
                projectInput.focus();
                return;
            }
            var btn = addForm.querySelector('button[type="submit"]');
            var origText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
            btn.disabled = true;
            var fd = new FormData(addForm);
            fd.set('project_id', projectIdInput.value);
            fd.set('milestone_id', milestoneIdInput.value);
            var isEdit = document.getElementById('tlFormAction').value === 'edit';
            var apiUrl = isEdit ? 'api/update_timeline.php' : 'api/add_timeline.php';
            fetch(apiUrl, { method: 'POST', body: fd })
                .then(function(r) {
                    var ct = r.headers.get('content-type') || '';
                    if (!r.ok) {
                        if (ct.indexOf('text/html') !== -1) {
                            throw new Error('Server returned HTML instead of JSON. Possible SSL or server error (HTTP ' + r.status + ').');
                        }
                        throw new Error('Server error (HTTP ' + r.status + ')');
                    }
                    if (ct.indexOf('application/json') === -1) {
                        throw new Error('Server did not return JSON. Response type: ' + ct);
                    }
                    return r.json();
                })
                .then(function(d) {
                    if (d.ok) {
                        window.location.href = window.location.href;
                    } else {
                        alert('Failed: ' + (d.msg || 'Unknown error'));
                        btn.innerHTML = origText;
                        btn.disabled = false;
                    }
                })
                .catch(function(err) {
                    var msg = err.message;
                    if (msg.indexOf('Failed to fetch') !== -1 || msg.indexOf('NetworkError') !== -1) {
                        msg = 'Network error — periksa koneksi atau SSL. Coba lagi.';
                    } else if (msg.indexOf('SSL') !== -1 || msg.indexOf('HTTPS') !== -1) {
                        msg = 'SSL error — hubungi admin server untuk memeriksa sertifikat SSL.';
                    }
                    alert('Error: ' + msg);
                    btn.innerHTML = origText;
                    btn.disabled = false;
                });
        });
    }
});
</script>

<!-- Image Crop Modal -->
<div id="imageCropModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeCropModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-700"><i class="bi bi-crop mr-1 text-blue-500"></i> Crop Image</h3>
            <button onclick="closeCropModal()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition"><i class="bi bi-x-lg text-slate-500 text-xs"></i></button>
        </div>
        <div class="p-5">
            <div class="relative w-full max-h-[350px] bg-slate-100 rounded-xl overflow-hidden">
                <img id="cropImage" src="" alt="Crop preview" class="block max-w-full max-h-[350px]">
            </div>
            <div class="flex items-center justify-center gap-2 mt-4">
                <button onclick="setCropRatio(NaN, NaN)" class="crop-ratio-btn px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition active-ratio" data-ratio="free">Free</button>
                <button onclick="setCropRatio(1, 1)" class="crop-ratio-btn px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition" data-ratio="1:1">1:1</button>
                <button onclick="setCropRatio(4, 3)" class="crop-ratio-btn px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition" data-ratio="4:3">4:3</button>
                <button onclick="setCropRatio(16, 9)" class="crop-ratio-btn px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition" data-ratio="16:9">16:9</button>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50">
            <button onclick="closeCropModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">Cancel</button>
            <button id="cropSaveBtn" onclick="cropAndSave()" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="imageViewerModal" class="fixed inset-0 z-[9998] hidden">
    <div class="absolute inset-0 bg-black/80" onclick="closeImageViewer()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl p-4">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-700"><i class="bi bi-image mr-1 text-blue-500"></i> Project Image</h3>
                <button onclick="closeImageViewer()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition"><i class="bi bi-x-lg text-slate-500 text-xs"></i></button>
            </div>
            <div class="p-4">
                <div class="relative bg-slate-100 rounded-xl overflow-hidden flex items-center justify-center" style="min-height:300px;max-height:500px;">
                    <img id="viewerImage" src="" alt="Project Image" class="max-w-full max-h-[460px] object-contain">
                </div>
            </div>
            <?php if ($canWrite): ?>
            <div class="flex items-center justify-center gap-2 px-4 py-3 border-t border-slate-100 bg-slate-50">
                <button onclick="closeImageViewer();openImagePicker()" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button onclick="closeImageViewer();openImagePicker()" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition">
                    <i class="bi bi-arrow-repeat"></i> Ganti
                </button>
                <button onclick="closeImageViewer();deleteProjectImage()" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg transition">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden file input -->
<input type="file" id="imageFileInput" accept="image/jpeg,image/png,image/webp" class="hidden">

<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<script>
var cropper = null;
var currentProjectId = <?= (int)$id ?>;

function openImagePicker() {
    document.getElementById('imageFileInput').click();
}

document.getElementById('imageFileInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran gambar maksimal 2MB.');
        e.target.value = '';
        return;
    }

    var reader = new FileReader();
    reader.onload = function(ev) {
        var img = document.getElementById('cropImage');
        img.src = ev.target.result;
        document.getElementById('imageCropModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        if (cropper) cropper.destroy();
        cropper = new Cropper(img, {
            aspectRatio: NaN,
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false
        });
    };
    reader.readAsDataURL(file);
    e.target.value = '';
});

function setCropRatio(w, h) {
    if (!cropper) return;
    cropper.setAspectRatio(w / h);
    document.querySelectorAll('.crop-ratio-btn').forEach(function(btn) {
        btn.classList.remove('active-ratio', 'bg-blue-50', 'border-blue-300', 'text-blue-700');
    });
    var active = document.querySelector('.crop-ratio-btn[data-ratio="' + (isNaN(w) ? 'free' : w + ':' + h) + '"]');
    if (active) active.classList.add('active-ratio', 'bg-blue-50', 'border-blue-300', 'text-blue-700');
}

function closeCropModal() {
    document.getElementById('imageCropModal').classList.add('hidden');
    document.body.style.overflow = '';
    if (cropper) { cropper.destroy(); cropper = null; }
}

function cropAndSave() {
    if (!cropper) return;
    var btn = document.getElementById('cropSaveBtn');
    btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
    btn.disabled = true;

    var canvas = cropper.getCroppedCanvas({ width: 1200, height: 1200, imageSmoothingQuality: 'high' });
    canvas.toBlob(function(blob) {
        var fd = new FormData();
        fd.append('image', blob, 'project_' + currentProjectId + '.jpg');
        fd.append('project_id', currentProjectId);

        var cropData = cropper.getData(true);
        fd.append('crop_x', cropData.x);
        fd.append('crop_y', cropData.y);
        fd.append('crop_width', cropData.width);
        fd.append('crop_height', cropData.height);

        fetch('api/upload_project_image.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    window.location.reload();
                } else {
                    alert('Gagal: ' + (d.msg || 'Unknown error'));
                    btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save';
                    btn.disabled = false;
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
                btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save';
                btn.disabled = false;
            });
    }, 'image/jpeg', 0.9);
}

function deleteProjectImage() {
    if (!confirm('Hapus gambar project ini?')) return;
    var fd = new FormData();
    fd.append('project_id', currentProjectId);
    fetch('api/delete_project_image.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) window.location.reload();
            else alert('Gagal: ' + (d.msg || 'Unknown error'));
        })
        .catch(function(err) { alert('Error: ' + err.message); });
}

function openImageViewer() {
    var img = document.getElementById('projectImage');
    if (!img || !img.src) return;
    document.getElementById('viewerImage').src = img.src;
    document.getElementById('imageViewerModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageViewer() {
    document.getElementById('imageViewerModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function toggleImageAction() {
    var img = document.getElementById('projectImage');
    if (img && img.src && img.naturalWidth > 0) {
        openImageViewer();
    } else {
        openImagePicker();
    }
}
</script>

<style>
.active-ratio { background: #eff6ff !important; border-color: #93c5fd !important; color: #1d4ed8 !important; }
</style>

<!-- Edit Project Modal -->
<div id="editProjectModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeEditProjectModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="editProjectForm" class="p-5 space-y-4">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Project Name *</label>
                <input type="text" name="project_name" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" value="<?= safe($project['project_name']) ?>">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Start Date</label>
                    <input type="date" name="start_date" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" value="<?= safe($project['start_date'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Rank</label>
                    <input type="number" name="ranking" min="0" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Kosongkan untuk tidak di-rank" value="<?= $project['ranking'] ?? '' ?>">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Target Unit/Machine</label>
                    <input type="number" name="target_unit" min="0" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" value="<?= (int)($project['target_unit'] ?? 0) ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Completed Unit/Machine</label>
                    <input type="number" name="completed_unit" min="0" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="0" value="<?= (int)($project['completed_unit'] ?? 0) ?>">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Status</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="status_id" id="ep_status_id" class="cbx-val" value="<?= (int)($project['status_id'] ?? 0) ?>">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="ep_status" autocomplete="off" value="<?= safe($project['status_name'] ?? '') ?>">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Priority</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="priority_id" id="ep_priority_id" class="cbx-val" value="<?= (int)($project['priority_id'] ?? 0) ?>">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="ep_priority" autocomplete="off" value="<?= safe($project['priority_name'] ?? '') ?>">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Category</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="category_id" id="ep_category_id" class="cbx-val" value="<?= (int)($project['category_id'] ?? 0) ?>">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="ep_category" autocomplete="off" value="<?= safe(categoryDisplay($project['category_name'] ?? '')) ?>">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Benefit</label>
                    <div class="cbx-wrap relative">
                        <input type="hidden" name="benefit_id" id="ep_benefit_id" class="cbx-val" value="<?= (int)($project['benefit_id'] ?? 0) ?>">
                        <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="ep_benefit" autocomplete="off" value="<?= safe($project['benefit_name'] ?? '') ?>">
                        <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Responsible Person</label>
                <div class="cbx-wrap relative">
                    <input type="hidden" name="responsible_person_id" id="ep_rp_id" class="cbx-val" value="<?= (int)($project['responsible_person_id'] ?? 0) ?>">
                    <input type="text" class="cbx-input w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white" placeholder="-- Select --" data-cbx="ep_rp" autocomplete="off" value="<?= safe($project['responsible_person_name'] ?? '') ?>">
                    <div class="cbx-list hidden absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"></div>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-3">
                <label class="block text-xs font-bold text-slate-600 mb-1"><i class="bi bi-google mr-1"></i>Link Google Drive (Short Proposal)</label>
                <div class="space-y-2" id="driveLinkRows">
                    <?php if (!empty($driveLinks)): ?>
                    <?php foreach ($driveLinks as $dl): ?>
                    <div class="flex items-start gap-2 drive-row">
                        <div class="flex-1 space-y-1">
                            <input type="text" class="drive-label w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="Label (opsional)" value="<?= safe($dl['label']) ?>">
                            <input type="url" class="drive-url w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/..." value="<?= safe($dl['drive_url']) ?>">
                        </div>
                        <button type="button" onclick="removeDriveRow(this)" class="mt-1 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition flex-shrink-0" title="Hapus"><i class="bi bi-trash text-xs"></i></button>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="flex items-start gap-2 drive-row">
                        <div class="flex-1 space-y-1">
                            <input type="text" class="drive-label w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="Label (opsional)">
                            <input type="url" class="drive-url w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/...">
                        </div>
                        <button type="button" onclick="removeDriveRow(this)" class="mt-1 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition flex-shrink-0" title="Hapus"><i class="bi bi-trash text-xs"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addDriveRow()" class="inline-flex items-center gap-1 mt-2 px-3 py-1.5 text-[11px] font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"><i class="bi bi-plus-lg"></i> Tambah Link</button>
            </div>
            <?php if ($hasDocLinks): ?>
            <div class="border-t border-slate-100 pt-3">
                <label class="block text-xs font-bold text-slate-600 mb-1"><i class="bi bi-file-earmark-word mr-1"></i>Link Project Order Document</label>
                <input type="url" name="project_order_link" class="w-full px-2.5 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/..." value="<?= safe($projectOrderLink) ?>">
                <p class="text-[10px] text-slate-400 mt-1">Kosongkan bila link belum tersedia.</p>
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeEditProjectModal()" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
var EP_CBX_DATA = {
    ep_status: <?= json_encode(array_map(fn($s) => ['v'=>(string)$s['id'],'l'=>$s['name']], $allStatus)) ?>,
    ep_priority: <?= json_encode(array_map(fn($p) => ['v'=>(string)$p['id'],'l'=>$p['name']], $allPriority)) ?>,
    ep_category: <?= json_encode(array_map(fn($c) => ['v'=>(string)$c['id'],'l'=>categoryDisplay($c['name'])], $allTypes)) ?>,
    ep_benefit: <?= json_encode(array_map(fn($b) => ['v'=>(string)$b['id'],'l'=>$b['name']], $allBenefit)) ?>,
    ep_rp: <?= json_encode(array_map(fn($u) => ['v'=>(string)$u['id'],'l'=>$u['name']], $allUsers)) ?>
};

function initEPCbx() {
    document.querySelectorAll('#editProjectModal .cbx-wrap').forEach(function(wrap) {
        var inp = wrap.querySelector('.cbx-input');
        var val = wrap.querySelector('.cbx-val');
        var list = wrap.querySelector('.cbx-list');
        var key = inp.getAttribute('data-cbx');
        var opts = EP_CBX_DATA[key] || [];
        var emptyLabel = '-- Not Specified --';
        function render(filter) {
            var q = (filter || '').toLowerCase();
            var html = '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-500" data-v="">' + emptyLabel + '</div>';
            opts.forEach(function(o) {
                if (q && o.l.toLowerCase().indexOf(q) === -1) return;
                html += '<div class="cbx-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 text-slate-700" data-v="' + o.v + '">' + o.l + '</div>';
            });
            list.innerHTML = html;
            list.querySelectorAll('.cbx-opt').forEach(function(opt) {
                opt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    val.value = opt.getAttribute('data-v');
                    inp.value = opt.textContent;
                    list.classList.add('hidden');
                });
            });
        }
        inp.addEventListener('focus', function() { render(inp.value); list.classList.remove('hidden'); });
        inp.addEventListener('input', function() { render(inp.value); list.classList.remove('hidden'); });
        inp.addEventListener('blur', function() { setTimeout(function() { list.classList.add('hidden'); }, 150); });
        render('');
    });
}

function openEditProjectModal() {
    initEPCbx();
    document.getElementById('editProjectModal').classList.remove('hidden');
}
function closeEditProjectModal() {
    document.getElementById('editProjectModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    var epForm = document.getElementById('editProjectForm');
    if (epForm) {
        epForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = epForm.querySelector('button[type="submit"]');
            var origText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
            btn.disabled = true;
            var fd = new FormData(epForm);
            fetch('api/update_project.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        return saveDriveLinks('driveLinkRows').then(function() { window.location.reload(); });
                    } else {
                        alert('Failed: ' + (d.msg || 'Unknown error'));
                        btn.innerHTML = origText;
                        btn.disabled = false;
                    }
                })
                .catch(function(err) {
                    alert('Error: ' + err.message);
                    btn.innerHTML = origText;
                    btn.disabled = false;
                });
        });
    }
});

function saveDriveLinks(scopeId) {
    var scope = document.getElementById(scopeId || 'driveLinkRows') || document;
    var rows = scope.querySelectorAll('.drive-row');
    var urls = [], labels = [];
    rows.forEach(function(row) {
        var url = row.querySelector('.drive-url').value.trim();
        var label = row.querySelector('.drive-label').value.trim();
        if (url) { urls.push(url); labels.push(label); }
    });
    var fd = new FormData();
    fd.append('action', 'save');
    fd.append('project_id', '<?= $id ?>');
    urls.forEach(function(u, i) { fd.append('urls[]', u); fd.append('labels[]', labels[i]); });
    return fetch('api/drive_links.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                DRIVE_LINKS = urls.map(function(u, i) { return { id: 0, drive_url: u, label: labels[i] }; });
            }
            return d;
        });
}

function saveDriveLinksModal() {
    return saveDriveLinks('driveLinkRowsModal').then(function(d) {
        if (d && d.ok) {
            renderDocStatus('short_proposal_link', DRIVE_LINKS.length > 0);
            closeDriveLinksModal();
        }
        return d;
    });
}

function deleteProject() {
    if (!confirm('Apakah Anda yakin ingin menghapus project ini? Semua data terkait akan dihapus permanen.')) return;
    if (!confirm('Konfirmasi sekali lagi: HAPUS project ini?')) return;
    var fd = new FormData();
    fd.append('id', '<?= (int)$id ?>');
    fetch('api/delete_project.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                window.location.href = 'projects.php';
            } else {
                alert('Gagal menghapus: ' + (d.msg || 'Unknown error'));
            }
        })
        .catch(function(err) {
            alert('Error: ' + err.message);
        });
}

// Short Proposal Upload Before Modal
function openShortProposalModal() {
    document.getElementById('shortProposalModal').classList.remove('hidden');
}
function closeShortProposalModal() {
    document.getElementById('shortProposalModal').classList.add('hidden');
    document.getElementById('beforeImagePreview').src = '';
    document.getElementById('beforeImageInput').value = '';
    document.getElementById('spUploadBtn').disabled = false;
    document.getElementById('spUploadBtn').textContent = 'Download';
}
document.addEventListener('DOMContentLoaded', function() {
    var beforeInput = document.getElementById('beforeImageInput');
    var preview = document.getElementById('beforeImagePreview');
    var uploadBtn = document.getElementById('spUploadBtn');

    if (beforeInput) {
        beforeInput.addEventListener('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
                uploadBtn.textContent = 'Upload & Download';
            } else {
                preview.src = '';
                preview.classList.add('hidden');
                uploadBtn.textContent = 'Download';
            }
        });
    }

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            var file = beforeInput.files[0];
            var fd = new FormData();
            if (file) {
                fd.append('before_image', file);
            }
            fd.append('project_id', '<?= $id ?>');

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Processing...';

            fetch('api/export_short_proposal_with_before.php?id=<?= $id ?>', {
                method: 'POST',
                body: fd
            }).then(function(r) {
                if (!r.ok) return r.blob().then(function(b) { throw new Error('Download failed'); });
                return r.blob();
            }).then(function(blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'Short_Proposal_<?= safe($project['project_name']) ?>.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                closeShortProposalModal();
            }).catch(function(err) {
                alert('Error: ' + err.message);
                uploadBtn.disabled = false;
                uploadBtn.textContent = beforeInput.files[0] ? 'Upload & Download' : 'Download';
            });
        });
    }
});
</script>

<!-- Short Proposal Upload Before Modal -->
<div id="shortProposalModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeShortProposalModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700">Short Proposal — Download</span>
            <button onclick="closeShortProposalModal()" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-slate-500">Opsional: upload foto kondisi <strong>SEBELUM</strong> project. File xlsx akan langsung ter-download, dengan atau tanpa foto.</p>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Before Photo (Opsional)</label>
                <input type="file" id="beforeImageInput" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            </div>
            <div class="hidden">
                <img id="beforeImagePreview" class="w-full h-40 object-contain rounded-lg border border-slate-200">
            </div>
            <div class="flex justify-end gap-2">
                <button onclick="closeShortProposalModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Batal</button>
                <button id="spUploadBtn" class="px-4 py-2 text-xs font-semibold text-white bg-emerald-500 hover:bg-emerald-600 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">Download</button>
            </div>
        </div>
    </div>
</div>
<!-- Document Link Modal -->
<div id="docLinkModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeDocLinkModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700" id="docLinkModalTitle">Update Document Link</span>
            <button onclick="closeDocLinkModal()" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Link Document</label>
                <input type="url" id="docLinkInput" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/..." onkeydown="if(event.key==='Enter'){event.preventDefault();saveDocLink();}">
                <p class="text-[10px] text-slate-400 mt-1">Kosongkan untuk menghapus link (status jadi silang).</p>
            </div>
            <div class="flex justify-end gap-2">
                <button onclick="closeDocLinkModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Batal</button>
                <button id="docLinkSaveBtn" onclick="saveDocLink()" class="px-4 py-2 text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition">Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- Drive Links Modal -->
<div id="driveLinksModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeDriveLinksModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[85vh] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700" id="driveModalTitle">Edit Link Google Drive</span>
            <button onclick="closeDriveLinksModal()" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-5 overflow-y-auto flex-1" id="driveModalBody">
            <!-- Content loaded by JS -->
        </div>
    </div>
</div>

<script>
var DRIVE_LINKS = <?= json_encode($driveLinks) ?>;
var DRIVE_VIEW_MODE = false;

var DOC_LINKS = {
    project_order_link: <?= json_encode($projectOrderLink) ?>
};
var DOC_LINK_FIELD = '';

function openProposalLinkModal() {
    openDriveLinksModal(false);
    document.getElementById('driveModalTitle').textContent = 'Update Short Proposal Link';
}

function downloadProposalDocuments() {
    if (!DRIVE_LINKS || DRIVE_LINKS.length === 0) {
        alert('Link short proposal belum diisi. Silakan isi lewat tombol Update.');
        return;
    }
    openDriveLinksModal(true);
}

function downloadProjectOrderDocument() {
    var link = DOC_LINKS.project_order_link || '';
    if (!link) {
        alert('Link project order document belum diisi. Silakan isi lewat tombol Update.');
        return;
    }
    window.open(link, '_blank', 'noopener');
}

function focusEditField(name) {
    openEditProjectModal();
    setTimeout(function() {
        var el = document.querySelector('#editProjectModal [name="' + name + '"]');
        if (el) {
            el.focus();
            if (el.select) el.select();
        }
    }, 200);
}

function editRankingProject() {
    focusEditField('ranking');
}

function editMachineTarget() {
    focusEditField('target_unit');
}

function downloadProjectPhoto() {
    var img = document.getElementById('projectImage');
    if (img && img.src && img.complete && img.naturalWidth > 0) {
        window.open(img.src, '_blank', 'noopener');
    } else {
        alert('Foto/video project belum diunggah. Silakan unggah lewat tombol Update.');
    }
}

function openDocLinkModal(field, label) {
    DOC_LINK_FIELD = field;
    document.getElementById('docLinkModalTitle').textContent = 'Update ' + label;
    document.getElementById('docLinkInput').value = DOC_LINKS[field] || '';
    document.getElementById('docLinkModal').classList.remove('hidden');
    setTimeout(function() { document.getElementById('docLinkInput').focus(); }, 100);
}

function closeDocLinkModal() {
    document.getElementById('docLinkModal').classList.add('hidden');
    DOC_LINK_FIELD = '';
}

function docStatusHtml(filled) {
    return filled
        ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600" title="Link sudah diisi"><i class="bi bi-check-lg"></i></span>'
        : '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-500" title="Link belum diisi"><i class="bi bi-x-lg"></i></span>';
}

function renderDocStatus(field, filled) {
    var el = document.getElementById('docStatus-' + field);
    if (!el) return;
    if (typeof filled === 'undefined') filled = !!DOC_LINKS[field];
    el.innerHTML = docStatusHtml(!!filled);
}

function saveDocLink() {
    var field = DOC_LINK_FIELD;
    if (!field) return;
    var value = document.getElementById('docLinkInput').value.trim();
    if (value && !/^https?:\/\/\S+/i.test(value)) {
        alert('Link harus diawali http:// atau https://');
        return;
    }
    var btn = document.getElementById('docLinkSaveBtn');
    var origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';

    var fd = new FormData();
    fd.append('id', '<?= (int)$id ?>');
    fd.append('field', field);
    fd.append('value', value);
    fetch('api/update_doc_link.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (d.ok) {
                DOC_LINKS[field] = d.value || '';
                renderDocStatus(field);
                closeDocLinkModal();
            } else {
                alert(d.msg || 'Gagal menyimpan link');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Error: ' + err.message);
        });
}

function openDriveLinksModal(viewMode) {
    DRIVE_VIEW_MODE = !!viewMode;
    document.getElementById('driveModalTitle').textContent = viewMode ? 'Download Short Proposal' : 'Edit Link Google Drive';
    renderDriveLinksModal();
    document.getElementById('driveLinksModal').classList.remove('hidden');
}

function closeDriveLinksModal() {
    document.getElementById('driveLinksModal').classList.add('hidden');
}

function renderDriveLinksModal() {
    var body = document.getElementById('driveModalBody');
    if (DRIVE_VIEW_MODE) {
        var html = '';
        if (DRIVE_LINKS.length === 0) {
            html = '<div class="text-center py-8 text-slate-400 text-sm"><i class="bi bi-cloud-slash text-3xl mb-2 block"></i>Belum ada link Google Drive.</div>';
        } else {
            html = '<div class="space-y-2">';
            DRIVE_LINKS.forEach(function(link) {
                var label = link.label || 'Short Proposal';
                html += '<a href="' + escapeHtml(link.drive_url) + '" target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50 transition group">' +
                    '<div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-google text-emerald-600"></i></div>' +
                    '<div class="min-w-0 flex-1"><div class="text-xs font-bold text-slate-700 truncate">' + escapeHtml(label) + '</div><div class="text-[10px] text-slate-400 truncate">' + escapeHtml(link.drive_url) + '</div></div>' +
                    '<i class="bi bi-box-arrow-up-right text-slate-400 group-hover:text-emerald-600 text-sm"></i>' +
                    '</a>';
            });
            html += '</div>';
        }
        body.innerHTML = html;
    } else {
        var html2 = '<div class="space-y-3" id="driveLinkRowsModal">';
        var rows = DRIVE_LINKS.length ? DRIVE_LINKS : [{drive_url:'',label:''}];
        rows.forEach(function(link, i) {
            html2 += '<div class="flex items-start gap-2 drive-row">' +
                '<div class="flex-1 space-y-1">' +
                '<input type="text" class="drive-label w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="Label (opsional, misal: Short Proposal v2)" value="' + escapeHtml(link.label || '') + '">' +
                '<input type="url" class="drive-url w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/..." value="' + escapeHtml(link.drive_url || '') + '">' +
                '</div>' +
                '<button onclick="removeDriveRow(this)" class="mt-1 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition flex-shrink-0" title="Hapus"><i class="bi bi-trash text-xs"></i></button>' +
                '</div>';
        });
        html2 += '</div>';
        html2 += '<button onclick="addDriveRow(\'driveLinkRowsModal\')" class="inline-flex items-center gap-1 mt-3 px-3 py-1.5 text-[11px] font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"><i class="bi bi-plus-lg"></i> Tambah Link</button>';
        html2 += '<div class="flex justify-end gap-2 mt-4"><button onclick="closeDriveLinksModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Batal</button><button onclick="saveDriveLinksModal()" class="px-4 py-2 text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition">Simpan</button></div>';
        body.innerHTML = html2;
    }
}

function addDriveRow(scopeId) {
    var container = document.getElementById(scopeId || 'driveLinkRows');
    if (!container) return;
    var div = document.createElement('div');
    div.className = 'flex items-start gap-2 drive-row';
    div.innerHTML = '<div class="flex-1 space-y-1"><input type="text" class="drive-label w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="Label (opsional)"><input type="url" class="drive-url w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none transition" placeholder="https://drive.google.com/..."></div><button onclick="removeDriveRow(this)" class="mt-1 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition flex-shrink-0" title="Hapus"><i class="bi bi-trash text-xs"></i></button>';
    container.appendChild(div);
}

function removeDriveRow(btn) {
    btn.closest('.drive-row').remove();
}

function escapeHtml(t) {
    var d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
