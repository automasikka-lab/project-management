<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = 'User Management';
$page_icon = 'people';

// Hanya super admin (Burhan) yang boleh kelola akun.
// Tamu -> login; admin lain -> projects.php.
requireSuperAdmin(false);

ensureAdminPermissionColumns();
$admins = $pdo->query("SELECT id, name, email, phone, is_active, can_write, created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';

function umIsSuper($row) {
    return ($row['email'] === 'bfrabbani@gmail.com' && $row['name'] === 'Burhan');
}
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h2 class="text-base font-bold text-slate-800"><i class="bi bi-shield-lock text-blue-500 mr-1"></i> User Management</h2>
        <p class="text-xs text-slate-500 mt-0.5">Kelola akun admin: tambah, edit, nonaktifkan, hapus, dan tentukan hak akses <b>CRUD</b> atau <b>Read Only</b>.</p>
    </div>
    <button onclick="openAdminModal()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-person-plus"></i> Tambah Akun</button>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-4 overflow-x-auto">
        <table class="w-full text-xs border border-slate-300" style="min-width:860px;">
            <thead>
                <tr class="bg-slate-100">
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:44px;">NO</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">NAME</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left">EMAIL</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-left" style="width:130px;">PHONE</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:120px;">STATUS</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:130px;">ACCESS</th>
                    <th class="px-3 py-2 border border-slate-300 font-semibold text-slate-700 text-center" style="width:250px;">ACTION</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($admins)): ?>
                <tr><td colspan="7" class="px-3 py-6 border border-slate-300 text-center text-slate-400">Belum ada akun.</td></tr>
            <?php else: foreach ($admins as $i => $a):
                $super = umIsSuper($a);
                $active = (int)($a['is_active'] ?? 1) === 1;
                $write  = (int)($a['can_write'] ?? 1) === 1;
                $isSelf = ((int)$a['id'] === (int)getAdminId());
            ?>
                <tr class="hover:bg-slate-50" id="um-row-<?= (int)$a['id'] ?>">
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= $i + 1 ?>.</td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-700 font-semibold align-top">
                        <?= safe($a['name']) ?>
                        <?php if ($super): ?><span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-700 align-middle"><i class="bi bi-star-fill"></i> SUPER ADMIN</span><?php endif; ?>
                        <?php if ($isSelf && !$super): ?><span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-500 align-middle">ANDA</span><?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= safe($a['email']) ?></td>
                    <td class="px-3 py-2 border border-slate-300 text-slate-600 align-top"><?= safe($a['phone'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <span id="um-status-<?= (int)$a['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold <?= $active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                            <i class="bi <?= $active ? 'bi-check-circle-fill' : 'bi-slash-circle-fill' ?>"></i><?= $active ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <span id="um-access-<?= (int)$a['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold <?= $write ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' ?>">
                            <i class="bi <?= $write ? 'bi-pencil-square' : 'bi-eye' ?>"></i><?= $write ? 'CRUD' : 'Read Only' ?>
                        </span>
                    </td>
                    <td class="px-3 py-2 border border-slate-300 align-top text-center">
                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                            <button onclick="openAdminModal(<?= htmlspecialchars(json_encode([
                                'id' => (int)$a['id'], 'name' => $a['name'], 'email' => $a['email'],
                                'phone' => (string)($a['phone'] ?? ''), 'is_active' => $active ? 1 : 0,
                                'can_write' => $write ? 1 : 0, 'is_super' => $super ? 1 : 0,
                            ], JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES) ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition"><i class="bi bi-pencil-square"></i>Edit</button>

                            <?php if (!$super && !$isSelf): ?>
                            <button onclick="toggleAdminStatus(<?= (int)$a['id'] ?>, <?= $active ? 0 : 1 ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold <?= $active ? 'text-red-600 bg-red-50 hover:bg-red-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' ?> border <?= $active ? 'border-red-200' : 'border-emerald-200' ?> rounded-lg transition">
                                <i class="bi <?= $active ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i><?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                            <button onclick="toggleAdminAccess(<?= (int)$a['id'] ?>, <?= $write ? 0 : 1 ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                                <i class="bi bi-arrow-left-right"></i><?= $write ? 'Jadikan Read Only' : 'Berikan CRUD' ?>
                            </button>
                            <button onclick="deleteAdmin(<?= (int)$a['id'] ?>)" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg transition"><i class="bi bi-trash"></i>Hapus</button>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2 py-1 text-[10px] font-semibold text-slate-400 bg-slate-50 border border-slate-200 rounded-lg" title="<?= $super ? 'Akun super admin dilindungi' : 'Tidak bisa mengubah/menghapus akun sendiri' ?>"><i class="bi bi-lock"></i> Dilindungi</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Admin Modal -->
<div id="adminModal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAdminModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden" style="animation:modalSlideIn .2s ease-out both">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700"><i class="bi bi-person-gear text-blue-500 mr-1"></i> <span id="adminModalTitle">Tambah Akun</span></span>
            <button onclick="closeAdminModal()" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition"><i class="bi bi-x text-slate-500"></i></button>
        </div>
        <form id="adminForm" class="p-5 space-y-4">
            <input type="hidden" name="action" id="umAction" value="create">
            <input type="hidden" name="id" id="umId" value="">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" id="umName" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Full name">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Email <span class="text-red-400">*</span></label>
                <input type="email" name="email" id="umEmail" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="email@example.com">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Phone</label>
                <input type="text" name="phone" id="umPhone" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Optional">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Password <span id="umPwdReq" class="text-red-400">*</span></label>
                <input type="password" name="password" id="umPassword" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition" placeholder="Minimal 8 karakter" autocomplete="new-password">
                <p class="text-[10px] text-slate-400 mt-1" id="umPwdHint">Minimal 8 karakter.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Hak Akses</label>
                    <select name="can_write" id="umWrite" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white">
                        <option value="1">CRUD (tambah, edit, hapus)</option>
                        <option value="0">Read Only (hanya lihat)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Status</label>
                    <select name="is_active" id="umActive" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white">
                        <option value="1">Active (bisa login)</option>
                        <option value="0">Inactive (login diblokir)</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition"><i class="bi bi-check-lg mr-1"></i>Save</button>
                <button type="button" onclick="closeAdminModal()" class="px-5 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminModal(data) {
    var editing = data && data.id;
    document.getElementById('adminModalTitle').textContent = editing ? 'Edit Akun' : 'Tambah Akun';
    document.getElementById('umAction').value = editing ? 'update' : 'create';
    document.getElementById('umId').value = editing ? data.id : '';
    document.getElementById('umName').value = editing ? (data.name || '') : '';
    document.getElementById('umEmail').value = editing ? (data.email || '') : '';
    document.getElementById('umPhone').value = editing ? (data.phone || '') : '';
    document.getElementById('umPassword').value = '';
    document.getElementById('umWrite').value = editing ? String(data.can_write) : '1';
    document.getElementById('umActive').value = editing ? String(data.is_active) : '1';
    var pwdReq = document.getElementById('umPwdReq');
    var pwdHint = document.getElementById('umPwdHint');
    if (editing) {
        document.getElementById('umPassword').required = false;
        pwdReq.style.display = 'none';
        pwdHint.textContent = 'Kosongkan jika tidak ingin mengganti password.';
    } else {
        document.getElementById('umPassword').required = true;
        pwdReq.style.display = '';
        pwdHint.textContent = 'Minimal 8 karakter.';
    }
    // Akun super admin: hak akses & status dikunci.
    var locked = editing && data.is_super;
    document.getElementById('umWrite').disabled = locked;
    document.getElementById('umActive').disabled = locked;
    document.getElementById('adminModal').classList.remove('hidden');
    setTimeout(function() { document.getElementById('umName').focus(); }, 100);
}
function closeAdminModal() { document.getElementById('adminModal').classList.add('hidden'); }

function umPost(fd, btn) {
    var orig = btn ? btn.innerHTML : '';
    if (btn) { btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Saving...'; btn.disabled = true; }
    return fetch('api/manage_admin.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (btn) { btn.innerHTML = orig; btn.disabled = false; }
            if (!d.ok) { alert(d.msg || 'Gagal'); return false; }
            if (d.msg) alert(d.msg);
            return true;
        })
        .catch(function(e) { if (btn) { btn.innerHTML = orig; btn.disabled = false; } alert('Error: ' + e.message); return false; });
}

document.addEventListener('DOMContentLoaded', function() {
    var f = document.getElementById('adminForm');
    if (f) f.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = f.querySelector('button[type="submit"]');
        var fd = new FormData(f);
        // Field yang dinonaktifkan (akun super admin) tidak ikut terkirim -> kirim ulang manual.
        if (document.getElementById('umWrite').disabled) fd.set('can_write', '1');
        if (document.getElementById('umActive').disabled) fd.set('is_active', '1');
        umPost(fd, btn).then(function(ok) { if (ok) { closeAdminModal(); location.reload(); } });
    });
});

function toggleAdminStatus(id, isActive) {
    var msg = isActive ? 'Aktifkan akun ini?' : 'Nonaktifkan akun ini? Akun yang nonaktif TIDAK BISA LOGIN.';
    if (!confirm(msg)) return;
    var fd = new FormData();
    fd.append('action', 'status'); fd.append('id', id); fd.append('is_active', isActive);
    umPost(fd, null).then(function(ok) { if (ok) location.reload(); });
}
function toggleAdminAccess(id, canWrite) {
    var msg = canWrite ? 'Beri akun ini hak akses CRUD (tambah, edit, hapus)?' : 'Jadikan akun ini Read Only (hanya bisa melihat)?';
    if (!confirm(msg)) return;
    var fd = new FormData();
    fd.append('action', 'access'); fd.append('id', id); fd.append('can_write', canWrite);
    umPost(fd, null).then(function(ok) { if (ok) location.reload(); });
}
function deleteAdmin(id) {
    if (!confirm('Hapus akun ini secara permanen? Tindakan ini tidak bisa dibatalkan.')) return;
    var fd = new FormData();
    fd.append('action', 'delete'); fd.append('id', id);
    umPost(fd, null).then(function(ok) { if (ok) location.reload(); });
}
</script>

<?php include 'includes/footer.php'; ?>
