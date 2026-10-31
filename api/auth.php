<?php
// api/auth.php — Authentication API Endpoint
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ===== LOGIN =====
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
                exit;
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';

            if (!verifyCSRFToken($csrf)) {
                echo json_encode(['ok' => false, 'msg' => 'Token keamanan tidak valid. Silakan refresh halaman.']);
                exit;
            }

            if (empty($email) || empty($password)) {
                echo json_encode(['ok' => false, 'msg' => 'Email dan password harus diisi.']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'msg' => 'Format email tidak valid.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password'])) {
                echo json_encode(['ok' => false, 'msg' => 'Email atau password salah.']);
                exit;
            }

            // Akun dinonaktifkan super admin -> tidak boleh login.
            ensureAdminPermissionColumns();
            if (isset($admin['is_active']) && (int)$admin['is_active'] === 0) {
                logActivity('login_blocked', 'admin', 'Login ditolak (akun nonaktif): ' . $email, $admin['id']);
                echo json_encode(['ok' => false, 'msg' => 'Akun Anda sudah dinonaktifkan. Hubungi super admin.']);
                exit;
            }

            loginAdmin($admin);
            logActivity('login', 'admin', 'Login berhasil', $admin['id']);
            $homeRedirect = canAccessExecutiveDashboard() ? '/index.php' : '/projects.php';
            echo json_encode(['ok' => true, 'msg' => 'Login berhasil.', 'redirect' => appBase() . $homeRedirect]);
            break;


        // ===== SIGNUP =====
        case 'signup':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';

            if (!verifyCSRFToken($csrf)) {
                echo json_encode(['ok' => false, 'msg' => 'Token keamanan tidak valid. Silakan refresh halaman.']);
                exit;
            }

            // Validation
            if (empty($name) || empty($email) || empty($password) || empty($passwordConfirm)) {
                echo json_encode(['ok' => false, 'msg' => 'Semua field wajib diisi.']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'msg' => 'Format email tidak valid.']);
                exit;
            }

            if (strlen($name) < 2 || strlen($name) > 100) {
                echo json_encode(['ok' => false, 'msg' => 'Nama harus 2-100 karakter.']);
                exit;
            }

            if ($phone && !preg_match('/^[0-9+\-\s()]{8,20}$/', $phone)) {
                echo json_encode(['ok' => false, 'msg' => 'Format nomor HP tidak valid.']);
                exit;
            }

            if (strlen($password) < 8) {
                echo json_encode(['ok' => false, 'msg' => 'Password minimal 8 karakter.']);
                exit;
            }

            if ($password !== $passwordConfirm) {
                echo json_encode(['ok' => false, 'msg' => 'Konfirmasi password tidak cocok.']);
                exit;
            }

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Email sudah terdaftar. Gunakan email lain atau login.']);
                exit;
            }

            // Create admin account
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $hashedPassword]);
            $newAdminId = $pdo->lastInsertId();

            logActivity('create', 'admin', 'Akun baru dibuat: ' . $name, $newAdminId);
            echo json_encode(['ok' => true, 'msg' => 'Akun berhasil dibuat! Silakan login.', 'redirect' => appBase() . '/auth/login.php']);
            break;


        // ===== FORGOT PASSWORD — Check Email =====
        case 'check_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
                exit;
            }

            $email = trim($_POST['email'] ?? '');
            $csrf = $_POST['csrf_token'] ?? '';

            if (!verifyCSRFToken($csrf)) {
                echo json_encode(['ok' => false, 'msg' => 'Token keamanan tidak valid.']);
                exit;
            }

            if (empty($email)) {
                echo json_encode(['ok' => false, 'msg' => 'Email harus diisi.']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'msg' => 'Format email tidak valid.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, name FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if (!$admin) {
                echo json_encode(['ok' => false, 'msg' => 'Email tidak terdaftar.']);
                exit;
            }

            // Store email in session for reset_password
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_admin_id'] = $admin['id'];

            echo json_encode(['ok' => true, 'msg' => 'Email ditemukan.', 'name' => $admin['name']]);
            break;


        // ===== FORGOT PASSWORD — Reset Password =====
        case 'reset_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
                exit;
            }

            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';

            if (!verifyCSRFToken($csrf)) {
                echo json_encode(['ok' => false, 'msg' => 'Token keamanan tidak valid.']);
                exit;
            }

            // Check session
            if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_admin_id'])) {
                echo json_encode(['ok' => false, 'msg' => 'Sesi reset password tidak valid. Silakan mulai ulang.']);
                exit;
            }

            $email = $_SESSION['reset_email'];

            if (empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['ok' => false, 'msg' => 'Password baru harus diisi.']);
                exit;
            }

            if (strlen($newPassword) < 8) {
                echo json_encode(['ok' => false, 'msg' => 'Password minimal 8 karakter.']);
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode(['ok' => false, 'msg' => 'Konfirmasi password tidak cocok.']);
                exit;
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);

            // Clear session
            unset($_SESSION['reset_email'], $_SESSION['reset_admin_id']);

            logActivity('reset_password', 'admin', 'Password berhasil direset', $_SESSION['reset_admin_id'] ?? null);
            echo json_encode(['ok' => true, 'msg' => 'Password berhasil direset! Silakan login dengan password baru.', 'redirect' => appBase() . '/auth/login.php']);
            break;


        // ===== LOGOUT =====
        case 'logout':
            logoutAdmin();
            logActivity('logout', 'admin', 'Logout berhasil');
            echo json_encode(['ok' => true, 'msg' => 'Logout berhasil.', 'redirect' => appBase() . '/index.php']);
            break;


        default:
            echo json_encode(['ok' => false, 'msg' => 'Action tidak dikenali.']);
            break;
    }
} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Terjadi kesalahan server. Silakan coba lagi.']);
}
