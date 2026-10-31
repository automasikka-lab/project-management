<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Budget Analysis';
$page_icon = 'money';
include 'includes/header.php';

$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'planned_budget_desc';

$sql = "SELECT id, project_name, pic, category_name, actual_cost, planned_budget, profit FROM v_project_full WHERE planned_budget > 0";
$params = [];
if ($category) { $sql .= " AND category_name = :category"; $params[':category'] = $category; }

$sortMap = [
    'planned_budget_desc' => 'planned_budget DESC',
    'actual_cost_desc' => 'actual_cost DESC',
    'profit_desc' => 'profit DESC',
];
$sql .= " ORDER BY " . ($sortMap[$sort] ?? 'planned_budget DESC');
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();
$totalBudget = array_sum(array_column($data, 'planned_budget'));
$totalCost = array_sum(array_column($data, 'actual_cost'));
?>

<!-- KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <?php statCard('Total Budget', formatRupiah($totalBudget), 'cash-stack', 'blue', '', '', "showPopup('chart','','Total Budget')"); ?>
    <?php statCard('Total Expenditure', formatRupiah($totalCost), 'receipt', 'cyan', '', '', "showPopup('chart','','Total Expenditure')"); ?>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-bold text-slate-700">Detail per Project</span>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($category): ?>
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700"><i class="bi bi-funnel"></i> <?= safe($category) ?></span>
            <a href="budget.php" class="text-[10px] text-slate-400 hover:text-slate-600">reset</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="tbl-scroll max-h-[300px] overflow-y-auto">
        <table id="budgetTable" class="text-sm" style="border-collapse:separate;border-spacing:0;">
            <thead><tr style="background:#f1f5f9;">
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;">Project</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th>
                <th class="text-right" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Budget</th>
                <th class="text-right" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Expenditure</th>
                <th class="text-right" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Remaining Budget</th>
            </tr></thead>
            <tbody>
            <?php foreach ($data as $i => $row): ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                <td style="padding:8px 10px;font-size:13px;font-weight:500;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($row['project_name']) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0;cursor:pointer;" onclick="event.stopPropagation();showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($row['pic'] ?? '') ?>"><?= safe($row['pic']) ?: '-' ?></td>
                <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:500;color:#475569;border:1px solid #e2e8f0;"><?= formatRupiah($row['planned_budget']) ?></td>
                <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:500;color:#475569;border:1px solid #e2e8f0;"><?= formatRupiah($row['actual_cost']) ?></td>
                <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:700;color:<?= ($row['profit'] ?? 0) >= 0 ? '#059669' : '#dc2626' ?>;border:1px solid #e2e8f0;"><?= formatRupiah($row['profit']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
function initBudget(){if(typeof $==='undefined'){setTimeout(initBudget,200);return}
try{
$('#budgetTable').DataTable({
    order:[[2,'desc']],
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
}catch(err){console.error('DataTables error:',err)}}
initBudget();
});
</script>
<?php include 'includes/footer.php'; ?>
