<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'Personnel';
$page_icon = 'people';
$isAdmin = isAdminLoggedIn();
include 'includes/header.php';

$activeTab = $_GET['tab'] ?? 'pic';
$filterMode = $_GET['filter'] ?? '';

/* ── Responsible Person: from users table where role includes 'Responsible Person' ── */
$rpStats = qRows($pdo, "
    SELECT
        u.id,
        u.name,
        u.position,
        COUNT(DISTINCT p.id) AS total_projects,
        SUM(CASE WHEN st.name IN ('Close Finish','Close Hold','Close Reject') THEN 1 ELSE 0 END) AS completed_projects
    FROM users u
    LEFT JOIN projects p ON p.responsible_person_id = u.id
    LEFT JOIN ref_status st ON p.status_id = st.id
    WHERE u.role_type IN ('Responsible Person', 'Responsible Person / PIC')
    GROUP BY u.id, u.name, u.position
    ORDER BY u.name ASC
");

/* ── PIC: from users table where role includes 'PIC' ── */
$picStats = qRows($pdo, "
    SELECT
        u.id,
        u.name,
        u.position,
        COUNT(DISTINCT p.id) AS total_projects
    FROM users u
    LEFT JOIN projects p ON p.pic COLLATE utf8mb4_unicode_ci = u.name
        OR p.id IN (SELECT DISTINCT pa.project_id FROM project_activities pa WHERE pa.pic COLLATE utf8mb4_unicode_ci = u.name)
    WHERE u.role_type IN ('PIC', 'Responsible Person / PIC')
    GROUP BY u.id, u.name, u.position
    ORDER BY u.name ASC
");

/* ── PIC today's activity from project_activities (timeline) ── */
$picTodayAct = qRows($pdo, "
    SELECT
        u.name AS pic_name,
        COUNT(*) AS today_count
    FROM users u
    JOIN project_activities pa ON pa.pic COLLATE utf8mb4_unicode_ci = u.name
    WHERE u.role_type IN ('PIC', 'Responsible Person / PIC')
      AND pa.date <= CURDATE()
      AND (pa.estimated_duration IS NULL OR pa.date + INTERVAL pa.estimated_duration DAY >= CURDATE())
    GROUP BY u.name
");
$picTodayMap = [];
foreach ($picTodayAct as $ta) {
    $picTodayMap[$ta['pic_name']] = $ta;
}

/* ── PIC current datetime from project_planning_timeline ── */
$picPlanTl = qRows($pdo, "
    SELECT
        u.name AS pic_name,
        ptl.created_at AS plan_created_at
    FROM users u
    JOIN project_planning_timeline ptl ON ptl.pic COLLATE utf8mb4_unicode_ci = u.name
    WHERE u.role_type IN ('PIC', 'Responsible Person / PIC')
      AND DATE(ptl.created_at) = CURDATE()
    ORDER BY ptl.created_at DESC
");
$picPlanMap = [];
foreach ($picPlanTl as $pt) {
    $picPlanMap[$pt['pic_name']] = $pt;
}

?>

<!-- Tabs -->
<div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="flex border-b border-slate-100 items-center">
        <button onclick="showTab('rp')" id="tab-rp" class="px-6 py-3 text-sm font-bold <?= $activeTab === 'rp' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 border-b-2 border-transparent' ?> transition"><i class="bi bi-person-badge mr-1"></i> Responsible Person</button>
        <button onclick="showTab('pic')" id="tab-pic" class="px-6 py-3 text-sm font-bold <?= $activeTab === 'pic' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 border-b-2 border-transparent' ?> transition"><i class="bi bi-person mr-1"></i> PIC</button>
        <div class="ml-auto pr-4">
            <?php if ($canWrite): ?>
            <button onclick="openAddUser()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-plus-lg"></i> Add PIC / RP</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- RP Tab -->
    <div id="panel-rp" class="tbl-scroll max-h-[400px] overflow-y-auto <?= $activeTab !== 'rp' ? 'hidden' : '' ?>">
        <table id="rpTable" class="text-sm" style="border-collapse:separate;border-spacing:0;">
            <thead><tr style="background:#f1f5f9;">
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:160px;">Name</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:140px;">Position</th>
                <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Project</th>
                <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Project Complete</th>
            </tr></thead>
            <tbody>
            <?php if (empty($rpStats)): ?>
            <tr><td colspan="4" style="padding:16px;text-align:center;font-size:12px;color:#94a3b8;border:1px solid #e2e8f0;">No Responsible Person found</td></tr>
            <?php else: foreach ($rpStats as $i => $row):
                $total = (int)$row['total_projects'];
                $completed = (int)($row['completed_projects'] ?? 0);
                $pct = $total > 0 ? round($completed / $total * 100) : 0;
            ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="showPopup('rp','',this.getAttribute('data-rp'))" data-rp="<?= safe($row['name']) ?>">
                <td style="padding:8px 10px;font-size:13px;font-weight:600;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($row['name']) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= safe($row['position'] ?? '-') ?></td>
                <td style="padding:8px 10px;text-align:center;font-size:13px;font-weight:700;color:#1e293b;border:1px solid #e2e8f0;"><?= $total ?></td>
                <td style="padding:8px 10px;border:1px solid #e2e8f0;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                        <span style="font-size:12px;font-weight:600;color:#1e293b;"><?= $completed ?>/<?= $total ?></span>
                        <div style="width:64px;height:6px;background:#f1f5f9;border-radius:9999px;overflow:hidden;">
                            <div style="height:100%;background:linear-gradient(to right,#10b981,#34d399);border-radius:9999px;width:<?= $pct ?>%"></div>
                        </div>
                        <span style="font-size:10px;color:#94a3b8;width:32px;text-align:right;"><?= $pct ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PIC Tab -->
    <div id="panel-pic" class="<?= $activeTab !== 'pic' ? 'hidden' : '' ?>">
        <div class="px-4 py-2 border-b border-slate-100 flex items-center gap-2">
            <button onclick="filterPicTable('all')" id="fpic-all" class="px-3 py-1 text-[10px] font-semibold rounded-lg transition bg-blue-100 text-blue-700">All</button>
            <button onclick="filterPicTable('nonjob')" id="fpic-nonjob" class="px-3 py-1 text-[10px] font-semibold rounded-lg transition bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-600">Non Job</button>
            <button onclick="filterPicTable('onjob')" id="fpic-onjob" class="px-3 py-1 text-[10px] font-semibold rounded-lg transition bg-slate-100 text-slate-500 hover:bg-emerald-50 hover:text-emerald-600">On Job</button>
        </div>
        <div class="tbl-scroll max-h-[400px] overflow-y-auto">
        <table id="picTable" class="text-sm" style="border-collapse:separate;border-spacing:0;">
            <thead><tr style="background:#f1f5f9;">
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:150px;">Name</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:130px;">Position</th>
                <th class="text-center" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;">Project</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:130px;">Today's Activity</th>
                <th class="text-left" style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;border:1px solid #cbd5e1;background:#e2e8f0;min-width:130px;">Current Date</th>
            </tr></thead>
            <tbody>
            <?php if (empty($picStats)): ?>
            <tr><td colspan="5" style="padding:16px;text-align:center;font-size:12px;color:#94a3b8;border:1px solid #e2e8f0;">No PIC found</td></tr>
            <?php else: foreach ($picStats as $i => $row):
                $picName = $row['name'];
                $todayAct = $picTodayMap[$picName] ?? null;
                $planData = $picPlanMap[$picName] ?? null;
                $todayCount = $todayAct ? (int)$todayAct['today_count'] : 0;
                $planCreated = $planData ? $planData['plan_created_at'] : null;
            ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;" class="hover:bg-blue-50/60 transition cursor-pointer" onclick="showPopup('pic','',this.getAttribute('data-pic'))" data-pic="<?= safe($picName) ?>" data-jobcount="<?= $todayCount ?>">
                <td style="padding:8px 10px;font-size:13px;font-weight:600;color:#1e293b;border:1px solid #e2e8f0;"><?= safe($picName) ?></td>
                <td style="padding:8px 10px;font-size:12px;color:#64748b;border:1px solid #e2e8f0;"><?= safe($row['position'] ?? '-') ?></td>
                <td style="padding:8px 10px;text-align:center;font-size:13px;font-weight:700;color:#1e293b;border:1px solid #e2e8f0;"><?= (int)$row['total_projects'] ?></td>
                <td style="padding:8px 10px;font-size:12px;color:<?= $todayCount > 0 ? '#059669' : '#94a3b8' ?>;font-weight:<?= $todayCount > 0 ? '700' : '400' ?>;border:1px solid #e2e8f0;cursor:pointer;" onclick="event.stopPropagation();showPopup('todayjob','',this.closest('tr').getAttribute('data-pic'))">
                    <?php if ($todayCount > 0): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:6px;height:6px;border-radius:50%;background:#10b981;flex-shrink:0;"></span><?= $todayCount ?> Job</span>
                    <?php else: ?>
                        0 Job
                    <?php endif; ?>
                </td>
                <td style="padding:8px 10px;font-size:12px;color:<?= $planCreated ? '#475569' : '#94a3b8' ?>;border:1px solid #e2e8f0;">
                    <?php if ($planCreated): ?>
                        <?= date('d M Y', strtotime($planCreated)) ?>
                    <?php else: ?>
                        <?= date('d M Y') ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<script>
var picFilterMode = 'all';

function showTab(tab){
    document.getElementById('panel-rp').classList.toggle('hidden', tab!=='rp');
    document.getElementById('panel-pic').classList.toggle('hidden', tab!=='pic');
    document.getElementById('tab-rp').className = tab==='rp' ? 'px-6 py-3 text-sm font-bold text-blue-600 border-b-2 border-blue-600' : 'px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600 border-b-2 border-transparent';
    document.getElementById('tab-pic').className = tab==='pic' ? 'px-6 py-3 text-sm font-bold text-blue-600 border-b-2 border-blue-600' : 'px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600 border-b-2 border-transparent';
    if(tab==='rp') requestAnimationFrame(function(){ initDataTable('#rpTable'); });
    if(tab==='pic') requestAnimationFrame(function(){ initDataTable('#picTable'); applyPicFilter(); });
}
function filterPicTable(mode){
    picFilterMode = mode;
    if(typeof $!=='undefined' && typeof $.fn.DataTable!=='undefined' && $.fn.DataTable.isDataTable('#picTable')) {
        $('#picTable').DataTable().draw();
    }
    ['all','nonjob','onjob'].forEach(function(m){
        var btn=document.getElementById('fpic-'+m);
        if(m===mode){btn.className='px-3 py-1 text-[10px] font-semibold rounded-lg transition '+(m==='onjob'?'bg-emerald-100 text-emerald-700':m==='nonjob'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700');}
        else{btn.className='px-3 py-1 text-[10px] font-semibold rounded-lg transition bg-slate-100 text-slate-500 hover:bg-slate-200';}
    });
}
function applyPicFilter(){
    var fm="<?= $filterMode ?>";
    if(fm) filterPicTable(fm);
}
function initDataTable(selector){
    if(typeof $==='undefined'||typeof $.fn.DataTable==='undefined') return;
    var el=$(selector);
    if(!el.length) return;
    if($.fn.DataTable.isDataTable(selector)) el.DataTable().destroy();
    el.DataTable({
        order:[],
        pageLength:-1,
        lengthChange:false,
        info:false,
        paging:false,
        dom: 'frt',
        language:{
            search:'<i class="bi bi-search mr-1"></i>Search:',
            infoEmpty:'No data available'
        },
        rowCallback:function(row,data,index){
            if(selector==='#picTable'){
                var jobCount=parseInt($(row).attr('data-jobcount'))||0;
                var isOn=jobCount>0;
                $(row).attr('data-onjob',isOn?'1':'0');
                if(picFilterMode==='onjob') $(row).toggle(isOn);
                else if(picFilterMode==='nonjob') $(row).toggle(!isOn);
                else $(row).show();
            }
        }
    });
}
document.addEventListener('DOMContentLoaded',function(){
    var activeTab="<?= $activeTab ?>";
    if(activeTab==='pic'){initDataTable('#picTable');applyPicFilter();}
    else{initDataTable('#rpTable');}
});
</script>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeAddUser()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700"><i class="bi bi-person-plus text-blue-500 mr-1"></i> Add PIC / RP</span>
            <button onclick="closeAddUser()" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition"><i class="bi bi-x text-slate-500"></i></button>
        </div>
        <form id="addUserForm" class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Name</label>
                <input type="text" name="name" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Full name">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Position</label>
                <input type="text" name="position" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Job title">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Role Type</label>
                <select name="role_type" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white">
                    <option value="Responsible Person / PIC">Responsible Person / PIC</option>
                    <option value="PIC">PIC</option>
                    <option value="Responsible Person">Responsible Person</option>
                </select>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeAddUser()" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddUser() { document.getElementById('addUserModal').classList.remove('hidden'); }
function closeAddUser() { document.getElementById('addUserModal').classList.add('hidden'); }
document.addEventListener('DOMContentLoaded', function() {
    var f = document.getElementById('addUserForm');
    if (f) f.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = f.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...';
        btn.disabled = true;
        var fd = new FormData(f);
        fetch('api/add_user.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok) location.reload(); else { alert('Failed: ' + (d.msg || 'Unknown error')); btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save'; btn.disabled = false; } })
            .catch(function(err) { alert('Error: ' + err.message); btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>Save'; btn.disabled = false; });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
