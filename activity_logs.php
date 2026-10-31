<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Activity Log';
$page_icon = 'clock-history';
include 'includes/header.php';

$filterAction = $_GET['action'] ?? '';
$filterType = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($filterAction && in_array($filterAction, ['create','update','delete','login','logout','reset_password'])) {
    $where[] = 'action = ?';
    $params[] = $filterAction;
}
if ($filterType) {
    $where[] = 'target_type = ?';
    $params[] = $filterType;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $whereSQL");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT * FROM activity_logs $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionBadges = [
    'create' => 'bg-emerald-100 text-emerald-700',
    'update' => 'bg-blue-100 text-blue-700',
    'delete' => 'bg-red-100 text-red-700',
    'login' => 'bg-purple-100 text-purple-700',
    'logout' => 'bg-slate-100 text-slate-600',
    'reset_password' => 'bg-amber-100 text-amber-700',
    'other' => 'bg-slate-100 text-slate-600',
];
$actionIcons = [
    'create' => 'bi-plus-circle',
    'update' => 'bi-pencil',
    'delete' => 'bi-trash',
    'login' => 'bi-box-arrow-in-right',
    'logout' => 'bi-box-arrow-right',
    'reset_password' => 'bi-key',
    'other' => 'bi-three-dots',
];
$actionLabels = [
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'login' => 'Login',
    'logout' => 'Logout',
    'reset_password' => 'Reset Password',
    'other' => 'Other',
];
$typeLabels = [
    'auth' => 'Authentication',
    'project' => 'Project',
    'timeline' => 'Timeline',
    'cost_breakdown' => 'Cost Breakdown',
    'swot' => 'SWOT',
    'pic' => 'PIC',
    'image' => 'Image',
    'ranking' => 'Ranking',
    'user' => 'User',
];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-2">
            <i class="bi bi-clock-history text-blue-600"></i> Activity Log
        </h2>
        <p class="text-xs text-slate-500 mt-1">Riwayat aktivitas admin pada sistem</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <i class="bi bi-database"></i> Total: <?= $totalRows ?> log
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <!-- Filters -->
    <div class="p-4 border-b border-slate-100 flex flex-wrap gap-2">
        <a href="?" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= !$filterAction && !$filterType ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition">Semua</a>
        <?php foreach ($actionLabels as $val => $lbl): ?>
        <a href="?action=<?= $val ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $filterAction === $val ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <?php if (empty($logs)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
            <i class="bi bi-inbox text-4xl mb-3"></i>
            <p class="text-sm font-medium">Belum ada aktivitas</p>
        </div>
        <?php else: ?>
        <table class="w-full text-xs">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Waktu</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Admin</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Aksi</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Target</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Deskripsi</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                        <?= date('d M Y', strtotime($log['created_at'])) ?>
                        <span class="text-slate-400 ml-1"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-700"><?= safe($log['admin_name']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold <?= $actionBadges[$log['action']] ?? $actionBadges['other'] ?>">
                            <i class="bi <?= $actionIcons[$log['action']] ?? $actionIcons['other'] ?> text-[10px]"></i>
                            <?= $actionLabels[$log['action']] ?? ucfirst($log['action']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= $typeLabels[$log['target_type']] ?? ucfirst($log['target_type']) ?><?= $log['target_id'] ? ' #' . $log['target_id'] : '' ?></td>
                    <td class="px-4 py-3 text-slate-600 max-w-xs truncate" title="<?= safe($log['description']) ?>"><?= safe($log['description']) ?></td>
                    <td class="px-4 py-3 text-slate-400 font-mono"><?= safe($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="p-4 border-t border-slate-100 flex items-center justify-center gap-1">
        <?php
        $filterQS = '';
        if ($filterAction) $filterQS .= '&action=' . urlencode($filterAction);
        if ($filterType) $filterQS .= '&type=' . urlencode($filterType);
        ?>
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $filterQS ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition"><i class="bi bi-chevron-left"></i></a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
        <a href="?page=<?= $i ?><?= $filterQS ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $filterQS ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition"><i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
