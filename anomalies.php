<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Anomalies & Risks';
$page_icon = 'warning';
include 'includes/header.php';

$stagnant = qRows($pdo, "SELECT id, project_name, pic, start_date, end_date, last_update_date, days_since_last_update FROM v_project_full WHERE days_since_last_update > 60 AND status_name IN ('Open Expansion','Open Research') ORDER BY days_since_last_update DESC");
$noActivity = qRows($pdo, "SELECT p.id, p.project_name, p.pic, st.name as status_name FROM projects p LEFT JOIN project_activities pa ON p.id=pa.project_id LEFT JOIN ref_status st ON p.status_id=st.id WHERE pa.id IS NULL");
$invalidDate = qRows($pdo, "SELECT id, project_name, start_date, end_date, pic FROM v_project_full WHERE start_date > end_date AND start_date IS NOT NULL AND end_date IS NOT NULL");
$nullPic = qRows($pdo, "SELECT id, project_name, status_name, responsible_person_name FROM v_project_full WHERE pic IS NULL OR pic = ''");
$zeroProfit = qRows($pdo, "SELECT id, project_name, pic, actual_cost, planned_budget, profit FROM v_project_full WHERE profit <= 0 AND planned_budget > 0 ORDER BY profit ASC");
$duplicates = qRows($pdo, "SELECT project_id, row_order, COUNT(*) as dup_count FROM cost_breakdown GROUP BY project_id, row_order HAVING COUNT(*) > 1");
$syncIssue = qRows($pdo, "SELECT p.id, p.project_name, p.expenditure as cost_project, COALESCE(SUM(cb.actual_cost), 0) as cost_breakdown FROM projects p LEFT JOIN cost_breakdown cb ON p.id = cb.project_id GROUP BY p.id HAVING cost_project != cost_breakdown");
$totalAnomalies = count($stagnant) + count($noActivity) + count($invalidDate) + count($nullPic) + count($zeroProfit) + count($duplicates) + count($syncIssue);
?>

<!-- KPI -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
    <?php statCard('Total Anomalies', $totalAnomalies, 'exclamation-triangle', 'red'); ?>
    <?php statCard('Stagnant', count($stagnant), 'clock', count($stagnant) > 0 ? 'red' : 'green', count($stagnant) > 0 ? 'Needs Attention' : 'Safe', count($stagnant) > 0 ? 'down' : 'up'); ?>
    <?php statCard('Without Activity', count($noActivity), 'file-earmark-x', 'amber'); ?>
    <?php statCard('Invalid Dates', count($invalidDate), 'calendar-x', 'red'); ?>
    <?php statCard('Without PIC', count($nullPic), 'person-x', 'amber'); ?>
    <?php statCard('Negative Remaining Budget', count($zeroProfit), 'graph-down', 'red'); ?>
    <?php statCard('Data Integrity', count($duplicates) + count($syncIssue), 'database', 'amber'); ?>
</div>

<!-- Accordion -->
<div class="space-y-3">
    <!-- Stagnant -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-clock text-red-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Stagnant Projects (>60 days without update)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"><?= count($stagnant) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($stagnant) > 0): ?>
            <div class="tbl-scroll max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;" class="tbl-freeze-head">Project</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Last Update</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Stagnant Days</th></tr></thead>
                    <tbody>
                    <?php $i=0; foreach ($stagnant as $row): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;min-width:180px;background:inherit;" class="tbl-freeze-col"><span style="font-size:13px;font-weight:500;color:#334155;"><?= safe($row['project_name']) ?></span></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;" class="cursor-pointer" onclick="event.stopPropagation();showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($row['pic'] ?? '') ?>"><?= safe($row['pic']) ?></td>
                        <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= fmtDate($row['last_update_date']) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:700;color:#dc2626;border:1px solid #e2e8f0;"><?= $row['days_since_last_update'] ?> days</td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-emerald-600"><i class="bi bi-check-circle mr-1"></i> No stagnant projects.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- No Activity -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-file-earmark text-amber-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Projects Without Activity</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><?= count($noActivity) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($noActivity) > 0): ?>
            <div class="tbl-scroll max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;" class="tbl-freeze-head">Project</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Status</th></tr></thead>
                    <tbody>
                    <?php $i=0; foreach ($noActivity as $row): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;min-width:180px;background:inherit;" class="tbl-freeze-col"><span style="font-size:13px;font-weight:500;color:#334155;"><?= safe($row['project_name']) ?></span></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;" class="cursor-pointer" onclick="event.stopPropagation();showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($row['pic'] ?? '') ?>"><?= safe($row['pic']) ?></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;"><span class="cursor-pointer" onclick="event.stopPropagation();showPopup('status','',this.getAttribute('data-st'))" data-st="<?= safe($row['status_name']) ?>"><?= statusBadge($row['status_name']) ?></span></td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-emerald-600"><i class="bi bi-check-circle mr-1"></i> All projects have activity.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invalid Dates -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-calendar-x text-red-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Invalid Dates (Start > End)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"><?= count($invalidDate) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($invalidDate) > 0): ?>
            <div class="tbl-scroll max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;" class="tbl-freeze-head">Project</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Start</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">End</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th></tr></thead>
                    <tbody>
                    <?php $i=0; foreach ($invalidDate as $row): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;min-width:180px;background:inherit;" class="tbl-freeze-col"><?= safe($row['project_name']) ?></td>
                        <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= fmtDate($row['start_date']) ?></td>
                        <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= fmtDate($row['end_date']) ?></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;" class="cursor-pointer" onclick="event.stopPropagation();showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($row['pic'] ?? '') ?>"><?= safe($row['pic']) ?: '-' ?></td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-emerald-600"><i class="bi bi-check-circle mr-1"></i> No invalid dates.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Null PIC -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-person-x text-amber-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Projects Without PIC</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><?= count($nullPic) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($nullPic) > 0): ?>
            <div class="tbl-scroll max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;" class="tbl-freeze-head">Project</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Status</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Responsible Person</th></tr></thead>
                    <tbody>
                    <?php $i=0; foreach ($nullPic as $row): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;min-width:180px;background:inherit;" class="tbl-freeze-col"><?= safe($row['project_name']) ?></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;"><span class="cursor-pointer" onclick="event.stopPropagation();showPopup('status','',this.getAttribute('data-st'))" data-st="<?= safe($row['status_name'] ?? '') ?>"><?= statusBadge($row['status_name']) ?></span></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;" class="cursor-pointer" onclick="event.stopPropagation();showPopup('rp','',this.getAttribute('data-rp'))" data-rp="<?= safe($row['responsible_person_name'] ?? '') ?>"><?= safe($row['responsible_person_name']) ?></td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-emerald-600"><i class="bi bi-check-circle mr-1"></i> All projects have a PIC.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zero Profit -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-graph-down text-red-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Projects With Negative/Zero Remaining Budget</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"><?= count($zeroProfit) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($zeroProfit) > 0): ?>
            <div class="tbl-scroll max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:180px;" class="tbl-freeze-head">Project</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">PIC</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Expenditure</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Budget</th><th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Remaining Budget</th></tr></thead>
                    <tbody>
                    <?php $i=0; foreach ($zeroProfit as $row): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="window.location.href='project_detail.php?id=<?= $row['id'] ?>'">
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;min-width:180px;background:inherit;" class="tbl-freeze-col"><span style="font-size:13px;font-weight:500;color:#334155;"><?= safe($row['project_name']) ?></span></td>
                        <td style="padding:8px 10px;font-size:13px;color:#1e293b;border:1px solid #e2e8f0;" class="cursor-pointer" onclick="event.stopPropagation();showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($row['pic'] ?? '') ?>"><?= safe($row['pic']) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:500;color:#475569;border:1px solid #e2e8f0;"><?= formatRupiah($row['actual_cost']) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:500;color:#475569;border:1px solid #e2e8f0;"><?= formatRupiah($row['planned_budget']) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:700;color:#dc2626;border:1px solid #e2e8f0;"><?= formatRupiah($row['profit']) ?></td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-sm text-emerald-600"><i class="bi bi-check-circle mr-1"></i> All projects have positive remaining budget.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Data Integrity -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <button onclick="toggleAccordion(this)" class="w-full px-5 py-4 flex items-center justify-between hover:bg-slate-50/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-database text-amber-600"></i></div>
                <span class="text-sm font-bold text-slate-700">Data Integrity</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><?= count($duplicates) + count($syncIssue) ?></span>
                <i class="bi bi-chevron-down text-slate-400 transition-transform"></i>
            </div>
        </button>
        <div class="accordion-content px-5 pb-4" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
            <?php if (count($duplicates) > 0): ?>
            <h6 class="text-xs font-bold text-red-600 mb-2">Cost breakdown duplicates</h6>
            <ul class="list-disc list-inside text-xs text-slate-600 mb-3 space-y-1">
            <?php foreach ($duplicates as $d): ?>
                <li class="cursor-pointer hover:text-blue-600" onclick="window.location.href='project_detail.php?id=<?= $d['project_id'] ?>'">Project ID <?= $d['project_id'] ?>, row_order <?= $d['row_order'] ?> → <?= $d['dup_count'] ?> times</li>
            <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-xs text-emerald-600 mb-2"><i class="bi bi-check-circle mr-1"></i> No cost breakdown duplicates.</p>
            <?php endif; ?>
            <?php if (count($syncIssue) > 0): ?>
            <h6 class="text-xs font-bold text-red-600 mb-2">Project Expenditure vs Breakdown Synchronization</h6>
            <ul class="list-disc list-inside text-xs text-slate-600 space-y-1">
            <?php foreach ($syncIssue as $s): ?>
                <li class="cursor-pointer hover:text-blue-600" onclick="window.location.href='project_detail.php?id=<?= $s['id'] ?>'"><?= safe($s['project_name']) ?>: Project <?= formatRupiah($s['cost_project']) ?> ≠ Breakdown <?= formatRupiah($s['cost_breakdown']) ?></li>
            <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-xs text-emerald-600"><i class="bi bi-check-circle mr-1"></i> Project expenditure is synchronized with breakdown.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAccordion(btn){
    var content=btn.nextElementSibling;
    var icon=btn.querySelector('.bi-chevron-down');
    if(content.style.maxHeight&&content.style.maxHeight!=='0px'){
        content.style.maxHeight='0px';content.style.overflow='hidden';content.style.transition='max-height .25s ease';
        if(icon)icon.style.transform='';
    }else{
        content.style.transition='max-height .25s ease';content.style.overflow='hidden';
        content.style.maxHeight=content.scrollHeight+'px';
        if(icon)icon.style.transform='rotate(180deg)';
    }
}
</script>
<?php include 'includes/footer.php'; ?>
