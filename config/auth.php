<?php
// =============================================
// Auth Helper Functions
// =============================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';

// =============================================
// Base URL helper — path dasar aplikasi.
// Otomatis mendeteksi subfolder (mis. Web Station Synology di /web/pm/)
// sehingga semua link/redirect relatif terhadap root aplikasi, bukan domain.
// =============================================

if (!function_exists('appBase')) {
    function appBase() {
        static $base = null;
        if ($base !== null) return $base;
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = dirname($script);
        // Jika script berada di dalam folder api/ atau auth/, naik satu level.
        $dir = preg_replace('#/(api|auth)$#', '', $dir);
        if ($dir === '/' || $dir === '.' || $dir === '') $dir = '';
        $base = rtrim($dir, '/');
        return $base;
    }
}

// Autoload PHPMailer
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

if (!function_exists('startSecureSession')) {
    function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        startSecureSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        startSecureSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfField')) {
    function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
    }
}

if (!function_exists('isAdminLoggedIn')) {
    function isAdminLoggedIn() {
        startSecureSession();
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_name']);
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isAdminLoggedIn()) {
            header('Location: ' . appBase() . '/auth/login.php');
            exit;
        }
    }
}

if (!function_exists('getAdminId')) {
    function getAdminId() {
        startSecureSession();
        return $_SESSION['admin_id'] ?? null;
    }
}

if (!function_exists('getAdminName')) {
    function getAdminName() {
        startSecureSession();
        return $_SESSION['admin_name'] ?? '';
    }
}

if (!function_exists('getAdminEmail')) {
    function getAdminEmail() {
        startSecureSession();
        return $_SESSION['admin_email'] ?? '';
    }
}

if (!function_exists('canAccessExecutiveDashboard')) {
    function canAccessExecutiveDashboard() {
        startSecureSession();
        $name  = $_SESSION['admin_name']  ?? '';
        $email = $_SESSION['admin_email'] ?? '';
        return ($email === 'bfrabbani@gmail.com') && ($name === 'Burhan');
    }
}

// =============================================
// Super Admin & Hak Akses Akun (User Management)
// =============================================
// Super admin = Burhan (bfrabbani@gmail.com / name: Burhan).
// Dia satu-satunya yang boleh kelola akun (tambah, edit, nonaktifkan, hapus)
// dan menentukan akun lain berhak CRUD atau hanya Read Only.

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() {
        return canAccessExecutiveDashboard();
    }
}

/**
 * Pastikan kolom hak akses ada di tabel admins (auto-migration, pola yang sama
 * dengan migrasi enum procurement_status). Berjalan sekali per request.
 */
if (!function_exists('ensureAdminPermissionColumns')) {
    function ensureAdminPermissionColumns() {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `admins`")->fetchAll(PDO::FETCH_COLUMN);
            $cols = array_map('strtolower', $cols);
            if (!in_array('is_active', $cols, true)) {
                $pdo->exec("ALTER TABLE `admins` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password`");
            }
            if (!in_array('can_write', $cols, true)) {
                $pdo->exec("ALTER TABLE `admins` ADD COLUMN `can_write` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
            }
            // Pastikan enum action di activity_logs memuat aksi kelola akun
            // (activate/deactivate/permission/login_blocked).
            $actType = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'action'")->fetchColumn();
            if ($actType && stripos($actType, 'activate') === false) {
                $pdo->exec("ALTER TABLE `activity_logs` MODIFY COLUMN `action` ENUM('create','update','delete','login','logout','reset_password','activate','deactivate','permission','login_blocked','other') NOT NULL DEFAULT 'other'");
            }
        } catch (Exception $e) {
            error_log('ensureAdminPermissionColumns: ' . $e->getMessage());
        }
    }
}

/** Ambil satu field hak akses admin yang sedang login (di-cache per request). */
if (!function_exists('adminPermission')) {
    function adminPermission($field) {
        if (!isAdminLoggedIn()) return null;
        if (isSuperAdmin()) return 1; // super admin selalu aktif + penuh
        ensureAdminPermissionColumns();
        global $pdo;
        static $cache = [];
        $id = (int)getAdminId();
        if (isset($cache[$id][$field])) return $cache[$id][$field];
        $allowed = ['is_active', 'can_write'];
        if (!in_array($field, $allowed, true)) return null;
        try {
            $st = $pdo->prepare("SELECT is_active, can_write FROM admins WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch();
            $val = $row ? (int)$row[$field] : 1; // default: aktif + boleh CRUD
        } catch (Exception $e) {
            error_log('adminPermission: ' . $e->getMessage());
            $val = 1;
        }
        $cache[$id][$field] = $val;
        return $val;
    }
}

/** Apakah admin yang sedang login berhak menulis (CRUD)? false = Read Only. */
if (!function_exists('adminCanWrite')) {
    function adminCanWrite() {
        $v = adminPermission('can_write');
        return $v === null ? true : ((int)$v === 1);
    }
}

/** Apakah akun masih aktif? false = dinonaktifkan super admin. */
if (!function_exists('adminIsActive')) {
    function adminIsActive() {
        $v = adminPermission('is_active');
        return $v === null ? true : ((int)$v === 1);
    }
}

/**
 * Guard untuk endpoint API yang menulis data.
 * Tamu -> ditolak; admin Read Only -> ditolak (403).
 */
if (!function_exists('requireWriteAccess')) {
    function requireWriteAccess() {
        if (!isAdminLoggedIn()) {
            echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']);
            exit;
        }
        if (!adminIsActive()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Akun Anda sudah dinonaktifkan. Hubungi super admin.']);
            exit;
        }
        if (!adminCanWrite()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Akun Anda hanya memiliki akses Read Only. Hubungi super admin untuk mengubah hak akses.']);
            exit;
        }
    }
}

/**
 * Guard untuk halaman/endpoint kelola akun — hanya super admin (Burhan).
 * $json = true untuk API (respon JSON), false untuk halaman (redirect).
 */
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin($json = false) {
        if (!isAdminLoggedIn() || !adminIsActive()) {
            if ($json) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']);
            } else {
                header('Location: ' . appBase() . '/auth/login.php');
            }
            exit;
        }
        if (!isSuperAdmin()) {
            if ($json) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Hanya super admin yang dapat mengelola akun.']);
            } else {
                header('Location: ' . appBase() . '/projects.php');
            }
            exit;
        }
    }
}

if (!function_exists('loginAdmin')) {
    function loginAdmin($admin) {
        startSecureSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
    }
}

if (!function_exists('logoutAdmin')) {
    function logoutAdmin() {
        startSecureSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

if (!function_exists('generatePassword')) {
    function generatePassword($length = 8) {
        $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($toEmail, $toName, $subject, $htmlBody) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            return ['ok' => true];
        } catch (Exception $e) {
            error_log('Email send failed: ' . $mail->ErrorInfo);
            return ['ok' => false, 'msg' => 'Gagal mengirim email. Silakan coba lagi.'];
        }
    }
}

if (!function_exists('sendNewPasswordEmail')) {
    function sendNewPasswordEmail($email, $name, $newPassword) {
        $subject = 'Password Baru - PM Intelligence';

        $html = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,sans-serif;">
            <div style="max-width:480px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                <div style="background:linear-gradient(135deg,#3b82f6,#2563eb);padding:32px 24px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,0.2);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                        <span style="font-size:24px;">&#128274;</span>
                    </div>
                    <h1 style="color:#fff;font-size:20px;font-weight:800;margin:0;">PM Intelligence</h1>
                    <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;">Password Baru Anda</p>
                </div>
                <div style="padding:32px 24px;text-align:center;">
                    <p style="color:#475569;font-size:14px;margin:0 0 8px;">Halo ' . htmlspecialchars($name) . ',</p>
                    <p style="color:#475569;font-size:14px;margin:0 0 16px;">Berikut adalah password baru untuk akun Anda:</p>
                    <div style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:12px;padding:16px;margin:16px 0;">
                        <span style="font-size:24px;font-weight:800;color:#1e293b;letter-spacing:4px;">' . htmlspecialchars($newPassword) . '</span>
                    </div>
                    <p style="color:#dc2626;font-size:12px;margin:16px 0 0;">&#9888; Silakan ganti password ini setelah login untuk keamanan.</p>
                    <p style="color:#94a3b8;font-size:12px;margin:8px 0 0;">Jika Anda tidak melakukan reset password, abaikan email ini.</p>
                </div>
                <div style="padding:16px 24px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0;">
                    <p style="color:#94a3b8;font-size:11px;margin:0;">&copy; ' . date('Y') . ' PM Intelligence &mdash; Project Management Dashboard</p>
                </div>
            </div>
        </body>
        </html>';

        return sendEmail($email, $name, $subject, $html);
    }
}

// =============================================
// Activity Logging
// =============================================

if (!function_exists('logActivity')) {
    function logActivity($action, $targetType, $description, $targetId = null, $oldValue = null, $newValue = null) {
        global $pdo;
        try {
            if (!isset($pdo)) {
                require_once __DIR__ . '/database.php';
                global $pdo;
            }
            $adminId = getAdminId();
            $adminName = getAdminName() ?: 'System';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $pdo->prepare("INSERT INTO activity_logs (admin_id, admin_name, action, target_type, target_id, description, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$adminId, $adminName, $action, $targetType, $targetId, $description, $oldValue, $newValue, $ipAddress]);
        } catch (Exception $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('logLogin')) {
    function logLogin() {
        logActivity('login', 'auth', 'Admin logged in');
    }
}

if (!function_exists('logLogout')) {
    function logLogout() {
        logActivity('logout', 'auth', 'Admin logged out');
    }
}
