<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Procurement Unverified Timelines';
$page_icon = 'bag-check';
include 'includes/header.php';

$unverifiedTimelines = qRows($pdo, "
    SELECT pa.id, pa.project_id, p.project_name, p.pic, pa.timeline, pa.date, pa.pic as activity_pic, pa.verified, pa.verified_date
    FROM project_activities pa
    JOIN projects p ON pa.project_id = p.id
    WHERE pa.milestone_id = 5 AND (pa.verified = 0 OR pa.verified IS NULL)
    ORDER BY pa.date DESC
");

$totalTimelines = count($unverifiedTimelines);
$totalProjects = count(array_unique(array_column($unverifiedTimelines, 'project_id')));
?>

<!-- KPI -->
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <?php statCard('Total Unverified Timelines', $totalTimelines, 'clock', 'amber'); ?>
    <?php statCard('Affected Projects', $totalProjects, 'folder', 'blue'); ?>
</div>

<!-- Back Button -->
<div class="mb-4">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="bi bi-bag-check text-cyan-500"></i>
            <span class="text-sm font-bold text-slate-700">Procurement Unverified Timelines</span>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700"><?= $totalTimelines ?> unverified</span>
    </div>
    <div class="tbl-scroll max-h-[500px] overflow-y-auto">
        <?php if ($totalTimelines > 0): ?>
        <table class="text-sm" style="border-collapse:separate;border-spacing:0;">
            <thead><tr style="background:#f1f5f9;">
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:40px;">#</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;">Project</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Timeline</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Date</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Activity PIC</th>
                <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unverifiedTimelines as $i => $row): ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= (int)$row['project_id'] ?>'">
                <td style="padding:8px 10px;font-size:12px;color:#94a3b8;border:1px solid #e2e8f0;"><?= $i + 1 ?></td>
                <td style="padding:8px 10px;font-size:13px;font-weight:500;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($row['project_name']) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0;"><?= safe($row['pic'] ?? '-') ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0;"><?= safe($row['timeline'] ?? '-') ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= fmtDate($row['date']) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0;"><?= safe($row['activity_pic'] ?? '-') ?></td>
                <td style="padding:8px 10px;text-align:center;border:1px solid #e2e8f0;">
                    <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:#fee2e2;color:#dc2626;">
                        <i class="bi bi-x-lg" style="margin-right:4px;"></i> Unverified
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="px-5 py-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
                <i class="bi bi-check-circle text-emerald-500 text-2xl"></i>
            </div>
            <p class="text-sm font-medium text-slate-500">All Procurement timelines are verified!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
