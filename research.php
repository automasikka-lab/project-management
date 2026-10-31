<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'R&D Analysis';
$page_icon = 'flask';
include 'includes/header.php';

$totalExpansion = qVal($pdo, "SELECT COUNT(*) FROM v_project_full WHERE status_name = 'Open Expansion'");
$totalResearch = qVal($pdo, "SELECT COUNT(*) FROM v_project_full WHERE status_name = 'Open Research'");
$successResearch = qRows($pdo, "SELECT project_name, pic, status_name, id as project_id, benefit_name as benefit FROM v_project_full WHERE status_name = 'Open Research'");
$failedResearch = qRows($pdo, "SELECT project_name, pic, status_name, id as project_id, benefit_name as benefit FROM v_project_full WHERE status_name IN ('Close Hold','Close Reject')");
$avgTransition = qVal($pdo, "SELECT AVG(DATEDIFF(end_date, start_date)) FROM v_project_full WHERE status_name = 'Open Expansion' AND start_date IS NOT NULL AND end_date IS NOT NULL");
$researchSuccessRate = $totalResearch > 0 ? round(count($successResearch) / ($totalResearch + count($failedResearch)) * 100, 1) : 0;

$rd = qRows($pdo, "SELECT benefit_name, SUM(CASE WHEN status_name='Open Expansion' THEN 1 ELSE 0 END) as expansion_total, SUM(CASE WHEN status_name='Open Research' THEN 1 ELSE 0 END) as research_total FROM v_project_full WHERE benefit_name IS NOT NULL GROUP BY benefit_name");
$benefits = array_column($rd, 'benefit_name');
$datasets = [
    ['label' => 'Expansion', 'data' => array_column($rd, 'expansion_total')],
    ['label' => 'Research', 'data' => array_column($rd, 'research_total')]
];
$labels = $benefits;
?>

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <?php statCard('Total Expansion', $totalExpansion ?? 0, 'arrow-up-circle', 'blue'); ?>
    <?php statCard('Total Research', $totalResearch ?? 0, 'search', 'cyan'); ?>
    <?php statCard('Successful Research', count($successResearch), 'check-circle', 'green', $researchSuccessRate . '%', 'up'); ?>
    <?php statCard('Failed Research', count($failedResearch), 'x-circle', 'red'); ?>
    <?php statCard('Average Expansion Duration', round($avgTransition ?? 0, 1) . ' days', 'clock-history', 'purple'); ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-bar-chart text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">Research vs Expansion per Goals</span>
        </div>
        <div class="p-4 h-64 sm:h-72 lg:h-80"><canvas id="rdChart"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-pie-chart text-blue-500"></i>
            <span class="text-sm font-bold text-slate-700">R&D Composition</span>
        </div>
        <div class="p-4 h-64 sm:h-72 lg:h-80"><canvas id="rdPie"></canvas></div>
    </div>
</div>

<!-- Failed Research -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="bi bi-exclamation-circle text-red-500"></i>
        <span class="text-sm font-bold text-slate-700">Failed Research (Hold/Reject)</span>
    </div>
    <?php if (count($failedResearch) > 0): ?>
    <div class="tbl-scroll max-h-[300px] overflow-y-auto">
        <table class="text-sm" style="border-collapse:separate;border-spacing:0;">
                <thead><tr style="background:#f1f5f9;">
                    <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;">Project</th>
                    <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Goals</th>
                    <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Status</th>
                </tr></thead>
            <tbody>
            <?php foreach ($failedResearch as $i => $r): ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="<?= $r['project_id'] ? "window.location.href='project_detail.php?id=" . $r['project_id'] . "'" : '' ?>">
                <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($r['project_name']) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0;cursor:pointer;" onclick="event.stopPropagation();showPopup('benefit','',this.getAttribute('data-ben'))" data-ben="<?= safe($r['benefit'] ?? '') ?>"><?= safe($r['benefit']) ?></td>
                <td style="padding:8px 10px;border:1px solid #e2e8f0;"><span class="cursor-pointer" onclick="event.stopPropagation();showPopup('status','',this.getAttribute('data-st'))" data-st="<?= safe($r['status_name'] ?? '') ?>"><?= statusBadge($r['status_name']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center">
        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3"><i class="bi bi-check-circle text-emerald-600 text-xl"></i></div>
        <p class="text-sm font-medium text-slate-600">No failed research</p>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
function initRD(){if(typeof Chart==='undefined'){setTimeout(initRD,200);return}
try{
new Chart(document.getElementById('rdChart'),{type:'bar',data:{labels:<?= json_encode(array_values($benefits)) ?>,datasets:<?= json_encode($datasets) ?>},options:{responsive:true,maintainAspectRatio:false,onClick:function(e,el,chart){if(el.length){var label=chart.data.labels[el[0].index];showPopup('chart','',label);}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true,grid:{color:'#f1f5f9'}}},plugins:{legend:{position:'bottom',labels:{padding:12,usePointStyle:true,font:{size:11,family:'Inter'}}}}}});
new Chart(document.getElementById('rdPie'),{type:'doughnut',data:{labels:['Expansion','Research'],datasets:[{data:[<?= $totalExpansion ?? 0 ?>,<?= $totalResearch ?? 0 ?>],backgroundColor:['#2563eb','#06b6d4'],borderWidth:3,borderColor:'#fff',hoverOffset:8}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',onClick:function(e,el,chart){if(el.length){var label=chart.data.labels[el[0].index];showPopup('chart','',label);}},plugins:{legend:{position:'bottom',labels:{padding:12,usePointStyle:true,font:{size:11,family:'Inter'}}}}}});
}catch(err){console.error('Chart error:',err)}
}
initRD();
});
</script>
<?php include 'includes/footer.php'; ?>
