<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Timeline & Phases';
$page_icon = 'timeline';
include 'includes/header.php';

$timelineStats = qRows($pdo, "SELECT rm.name as timeline, AVG(d.duration) as avg_duration, COUNT(DISTINCT d.project_id) as project_count, MIN(d.duration) as min_duration, MAX(d.duration) as max_duration FROM (SELECT pa.project_id, pa.milestone_id, DATEDIFF(MAX(pa.date), MIN(pa.date)) as duration FROM project_activities pa GROUP BY pa.project_id, pa.milestone_id) d JOIN ref_milestone rm ON d.milestone_id = rm.id GROUP BY rm.id ORDER BY avg_duration DESC");
$fastestPIC = qRows($pdo, "SELECT rm.name as timeline, pa.pic, COUNT(*) as total_activities, DATEDIFF(MAX(pa.date), MIN(pa.date)) as duration FROM project_activities pa JOIN ref_milestone rm ON pa.milestone_id = rm.id WHERE pa.pic IS NOT NULL GROUP BY rm.id, pa.pic HAVING duration >= 0 ORDER BY duration ASC LIMIT 10");
$repeatedPhases = qRows($pdo, "SELECT rm.name as timeline, COUNT(*) as total_repetitions FROM project_activities pa JOIN ref_milestone rm ON pa.milestone_id = rm.id GROUP BY rm.id HAVING COUNT(*) > 1 ORDER BY total_repetitions DESC LIMIT 5");
$skippedPhases = qRows($pdo, "SELECT p.project_name, COUNT(DISTINCT pa.milestone_id) as phases_count FROM projects p JOIN project_activities pa ON p.id = pa.project_id GROUP BY p.id HAVING phases_count < 5 ORDER BY phases_count ASC LIMIT 10");
$totalPhases = count($timelineStats);
$overallAvg = $timelineStats ? round(array_sum(array_column($timelineStats, 'avg_duration')) / count($timelineStats), 1) : 0;
$maxPhase = $timelineStats ? $timelineStats[0] : null;
$minPhase = $timelineStats ? end($timelineStats) : null;
?>

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    <?php statCard('Total Phases', $totalPhases, 'layers', 'blue'); ?>
    <?php statCard('Average Duration', $overallAvg . ' days', 'clock', 'cyan'); ?>
    <?php statCard('Longest Phase', ($maxPhase ? $maxPhase['timeline'] : '-') . ' (' . ($maxPhase ? round($maxPhase['avg_duration'], 1) : 0) . ' days)', 'hourglass-bottom', 'red'); ?>
    <?php statCard('Fastest Phase', ($minPhase ? $minPhase['timeline'] : '-') . ' (' . ($minPhase ? round($minPhase['avg_duration'], 1) : 0) . ' days)', 'hourglass-top', 'green'); ?>
    <?php statCard('Repeated Phases', count($repeatedPhases), 'arrow-repeat', 'amber'); ?>
    <?php statCard('Skipped Phases', count($skippedPhases), 'shuffle', 'red'); ?>
</div>

<!-- Charts + Table -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-bar-chart text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Average Duration per Phase</span>
        </div>
        <div class="p-4 h-64 sm:h-72 lg:h-80"><canvas id="timelineChart"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-trophy text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Fastest PIC per Phase</span>
        </div>
        <div class="p-4 space-y-3">
            <?php foreach ($fastestPIC as $fp): ?>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition" onclick="showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($fp['pic'] ?? '') ?>">
                <div>
                    <div class="text-xs font-semibold text-slate-700"><?= safe($fp['pic']) ?></div>
                    <div class="text-[10px] text-slate-400"><?= safe($fp['timeline']) ?></div>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><?= $fp['duration'] ?> days</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Detail Table -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-hourglass text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Duration Detail per Phase</span>
        </div>
        <div class="tbl-scroll max-h-[300px] overflow-y-auto">
            <table id="timelineTable" class="text-sm" style="border-collapse:separate;border-spacing:0;">
                <thead><tr style="background:#f1f5f9;">
                    <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:140px;">Phase</th>
                    <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Average</th>
                    <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Min</th>
                    <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Max</th>
                    <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Projects</th>
                </tr></thead>
                <tbody>
                <?php foreach ($timelineStats as $i => $row): ?>
                <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="showPopup('chart','',this.getAttribute('data-tl'))" data-tl="<?= safe($row['timeline']) ?>">
                    <td style="padding:8px 10px;font-size:13px;font-weight:600;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($row['timeline']) ?></td>
                    <td style="padding:8px 10px;text-align:center;font-size:12px;color:#475569;border:1px solid #e2e8f0;"><?= round($row['avg_duration'], 1) ?> days</td>
                    <td style="padding:8px 10px;text-align:center;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= $row['min_duration'] ?? '-' ?></td>
                    <td style="padding:8px 10px;text-align:center;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= $row['max_duration'] ?? '-' ?></td>
                    <td style="padding:8px 10px;text-align:center;font-size:12px;color:#475569;border:1px solid #e2e8f0;"><?= $row['project_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-arrow-repeat text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Frequently Repeated Phases</span>
        </div>
        <div class="p-4 space-y-2">
            <?php if (count($repeatedPhases) > 0): ?>
            <?php foreach ($repeatedPhases as $r): ?>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition" onclick="showPopup('chart','',this.getAttribute('data-tl'))" data-tl="<?= safe($r['timeline']) ?>">
                <span class="text-xs font-medium text-slate-600"><?= safe($r['timeline']) ?></span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><?= $r['total_repetitions'] ?> times</span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-slate-400">No phases have been repeated.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (count($skippedPhases) > 0): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mt-4">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-shuffle text-red-500"></i>
        <span class="text-sm font-bold text-slate-700">Projects With Few Phases (Potential Phase Skip)</span>
    </div>
    <div class="tbl-scroll max-h-[300px] overflow-y-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-slate-50"><th class="text-left px-5 py-3 font-semibold text-slate-500 text-xs tbl-freeze-head" style="min-width:200px;">Project</th><th class="text-center px-3 py-3 font-semibold text-slate-500 text-xs">Number of Phases</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($skippedPhases as $s): ?>
            <tr class="hover:bg-slate-50/50 transition cursor-pointer" onclick="showPopup('chart','',this.getAttribute('data-proj'))" data-proj="<?= safe($s['project_name']) ?>">
                <td class="px-5 py-3 text-xs text-slate-700 tbl-freeze-col" style="min-width:200px;background:inherit;"><?= safe($s['project_name']) ?></td>
                <td class="px-3 py-3 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"><?= $s['phases_count'] ?> phases</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded',function(){
function initTimeline(){if(typeof Chart==='undefined'||typeof $==='undefined'){setTimeout(initTimeline,200);return}
try{
new Chart(document.getElementById('timelineChart'),{type:'bar',data:{labels:<?= json_encode(array_column($timelineStats,'timeline')) ?>,datasets:[{label:'Average Duration (days)',data:<?= json_encode(array_map(fn($r)=>round($r['avg_duration'],1),$timelineStats)) ?>,backgroundColor:['#3b82f6','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f97316'],borderRadius:6,barThickness:32}]},options:{responsive:true,maintainAspectRatio:false,onClick:function(e,el,chart){if(el.length){var label=chart.data.labels[el[0].index];showPopup('chart','',label);}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}},plugins:{legend:{display:false}}}});
$('#timelineTable').DataTable({
    order:[[1,'desc']],
    pageLength:-1,
    lengthChange:false,
    info:false,
    paging:false,
    dom: 'frt',
    language: {
        search: 'Search:',
        infoEmpty: 'No data available'
    }
});
}catch(err){console.error('Chart error:',err)}
}
initTimeline();
});
</script>
<?php include 'includes/footer.php'; ?>
