<?php
// api/manage_admin.php — User Management (khusus super admin / Burhan)
// Aksi: list, create, update, status (aktif/nonaktif), access (CRUD/Read Only), delete
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

startSecureSession();
requireSuperAdmin(true); // hanya super admin; tamu/admin lain -> 403 JSON

$action = $_GET['action'] ?? $_POST['action'] ?? '';
ensureAdminPermissionColumns();

/** Akun super admin dilindungi: tidak boleh dinonaktifkan/dihapus/diubah hak-nya. */
function isProtectedSuperAdminAccount($pdo, $id) {
    $st = $pdo->prepare("SELECT email, name FROM admins WHERE id = ? LIMIT 1");
    $st->execute([(int)$id]);
    $row = $st->fetch();
    if (!$row) return false;
    return ($row['email'] === 'bfrabbani@gmail.com' && $row['name'] === 'Burhan');
}

function adminRowPublic($row) {
    return [
        'id'         => (int)$row['id'],
        'name'       => (string)$row['name'],
        'email'      => (string)$row['email'],
        'phone'      => (string)($row['phone'] ?? ''),
        'is_active'  => isset($row['is_active']) ? (int)$row['is_active'] : 1,
        'can_write'  => isset($row['can_write']) ? (int)$row['can_write'] : 1,
        'created_at' => (string)($row['created_at'] ?? ''),
        'is_super'   => ($row['email'] === 'bfrabbani@gmail.com' && $row['name'] === 'Burhan'),
    ];
}

try {
    switch ($action) {

        // ===== LIST =====
        case 'list':
            $rows = $pdo->query("SELECT id, name, email, phone, is_active, can_write, created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => array_map('adminRowPublic', $rows)]);
            break;

        // ===== CREATE =====
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            $canWrite = isset($_POST['can_write']) ? (int)$_POST['can_write'] : 1;

            if ($name === '' || $email === '') { echo json_encode(['ok' => false, 'msg' => 'Nama dan email wajib diisi.']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'msg' => 'Format email tidak valid.']); exit; }
            if (strlen($password) < 8) { echo json_encode(['ok' => false, 'msg' => 'Password minimal 8 karakter.']); exit; }
            if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $phone)) { echo json_encode(['ok' => false, 'msg' => 'Format nomor HP tidak valid.']); exit; }

            $chk = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $chk->execute([$email]);
            if ($chk->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Email sudah terdaftar.']); exit; }

            $stmt = $pdo->prepare("INSERT INTO admins (name, email, phone, password, is_active, can_write) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_BCRYPT), $isActive ? 1 : 0, $canWrite ? 1 : 0]);
            $newId = (int)$pdo->lastInsertId();

            logActivity('create', 'admin', 'Akun baru ditambahkan: ' . $name . ' <' . $email . '> (' . ($canWrite ? 'CRUD' : 'Read Only') . ')', $newId);
            echo json_encode(['ok' => true, 'msg' => 'Akun berhasil ditambahkan.']);
            break;

        // ===== UPDATE (nama/email/telepon/password/level akses) =====
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }
            $id       = (int)($_POST['id'] ?? 0);
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            $canWrite = isset($_POST['can_write']) ? (int)$_POST['can_write'] : 1;

            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'ID tidak valid.']); exit; }
            if ($name === '' || $email === '') { echo json_encode(['ok' => false, 'msg' => 'Nama dan email wajib diisi.']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'msg' => 'Format email tidak valid.']); exit; }
            if ($password !== '' && strlen($password) < 8) { echo json_encode(['ok' => false, 'msg' => 'Password minimal 8 karakter.']); exit; }

            $chk = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id <> ? LIMIT 1");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Email sudah dipakai akun lain.']); exit; }

            $protected = isProtectedSuperAdminAccount($pdo, $id);
            if ($protected) {
                // Akun super admin: hak & status tidak boleh diubah (tetap CRUD + aktif),
                // tapi nama/telepon/password tetap boleh diperbarui.
                $isActive = 1;
                $canWrite = 1;
            }

            if ($password !== '') {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, phone = ?, password = ?, is_active = ?, can_write = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_BCRYPT), $isActive ? 1 : 0, $canWrite ? 1 : 0, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, phone = ?, is_active = ?, can_write = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone ?: null, $isActive ? 1 : 0, $canWrite ? 1 : 0, $id]);
            }
            if ($stmt->rowCount() === 0) {
                $chkId = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
                $chkId->execute([$id]);
                if (!$chkId->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Akun tidak ditemukan.']); exit; }
            }

            logActivity('update', 'admin', 'Akun diperbarui: ' . $name . ' <' . $email . '> | akses=' . ($canWrite ? 'CRUD' : 'Read Only') . ' | status=' . ($isActive ? 'Aktif' : 'Nonaktif'), $id);
            echo json_encode(['ok' => true, 'msg' => 'Akun berhasil diperbarui.']);
            break;

        // ===== TOGGLE STATUS (aktif / nonaktif) =====
        case 'status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }
            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 1);
            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'ID tidak valid.']); exit; }
            if ($id === (int)getAdminId()) { echo json_encode(['ok' => false, 'msg' => 'Anda tidak bisa menonaktifkan akun sendiri.']); exit; }
            if (isProtectedSuperAdminAccount($pdo, $id)) { echo json_encode(['ok' => false, 'msg' => 'Akun super admin tidak bisa dinonaktifkan.']); exit; }

            $stmt = $pdo->prepare("UPDATE admins SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive ? 1 : 0, $id]);
            if ($stmt->rowCount() === 0) {
                $chkId = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
                $chkId->execute([$id]);
                if (!$chkId->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Akun tidak ditemukan.']); exit; }
            }

            logActivity($isActive ? 'activate' : 'deactivate', 'admin', ($isActive ? 'Akun diaktifkan' : 'Akun dinonaktifkan') . ': #' . $id, $id);
            echo json_encode(['ok' => true, 'msg' => $isActive ? 'Akun diaktifkan.' : 'Akun dinonaktifkan. Akun tersebut tidak bisa login.']);
            break;

        // ===== TOGGLE HAK AKSES (CRUD / Read Only) =====
        case 'access':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }
            $id = (int)($_POST['id'] ?? 0);
            $canWrite = (int)($_POST['can_write'] ?? 1);
            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'ID tidak valid.']); exit; }
            if (isProtectedSuperAdminAccount($pdo, $id)) { echo json_encode(['ok' => false, 'msg' => 'Akun super admin selalu memiliki akses CRUD.']); exit; }

            $stmt = $pdo->prepare("UPDATE admins SET can_write = ? WHERE id = ?");
            $stmt->execute([$canWrite ? 1 : 0, $id]);
            if ($stmt->rowCount() === 0) {
                $chkId = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
                $chkId->execute([$id]);
                if (!$chkId->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Akun tidak ditemukan.']); exit; }
            }

            logActivity('permission', 'admin', 'Hak akses diubah: #' . $id . ' -> ' . ($canWrite ? 'CRUD' : 'Read Only'), $id);
            echo json_encode(['ok' => true, 'msg' => $canWrite ? 'Akun kini memiliki akses CRUD.' : 'Akun kini hanya Read Only.']);
            break;

        // ===== DELETE =====
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit; }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'ID tidak valid.']); exit; }
            if ($id === (int)getAdminId()) { echo json_encode(['ok' => false, 'msg' => 'Anda tidak bisa menghapus akun sendiri.']); exit; }
            if (isProtectedSuperAdminAccount($pdo, $id)) { echo json_encode(['ok' => false, 'msg' => 'Akun super admin tidak bisa dihapus.']); exit; }

            $st = $pdo->prepare("SELECT name, email FROM admins WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Akun tidak ditemukan.']); exit; }

            $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'admin', 'Akun dihapus: ' . $row['name'] . ' <' . $row['email'] . '>', $id);
            echo json_encode(['ok' => true, 'msg' => 'Akun berhasil dihapus.']);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Action tidak dikenali.']);
            break;
    }
} catch (PDOException $e) {
    error_log('manage_admin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Terjadi kesalahan server. Pastikan kolom is_active & can_write sudah ada (buka sekali halaman User Management).']);
} catch (Exception $e) {
    error_log('manage_admin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Terjadi kesalahan server.']);
}
