<?php
// config/database.php
//
// Kredensial diambil dari database.local.php (untuk Synology/local) bila ada,
// selain itu pakai default di bawah. Lihat juga database.local.example.php.

$__dbLocal = __DIR__ . '/database.local.php';
if (file_exists($__dbLocal)) {
    require $__dbLocal;
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'project_db');
    define('DB_USER', 'root');
    define('DB_PASS', 'Kkaeng123@#$');
}

// Pastikan error PHP tampil (default Synology = Off, akibatnya halaman blank
// setelah header saat ada exception).
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

/**
 * Render pesan error yang terlihat, bukan halaman blank.
 * Dipakai baik saat koneksi gagal (sebelum HTML) maupun exception sesudah header.
 */
function pmiRenderError(string $title, string $message, string $detail = '', bool $headerSent = false): void
{
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    if (!$headerSent && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "\n<div style='margin:1.5rem;padding:1.25rem 1.5rem;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;font-family:system-ui,sans-serif;max-width:760px;color:#7f1d1d'>";
    echo "<h2 style='color:#b91c1c;margin:0 0 .5rem;font-size:1.05rem'>&#9888; " . $e($title) . "</h2>";
    echo "<p style='margin:.25rem 0;font-weight:600'>" . $e($message) . "</p>";
    if ($detail !== '') {
        echo "<p style='margin:.5rem 0 0;font-size:12px;color:#991b1b;word-break:break-word'>" . $e($detail) . "</p>";
    }
    echo "<p style='margin:.75rem 0 0;font-size:12px;color:#6b7280'>Periksa <code>config/database.local.php</code> dan pastikan database sudah di-import (termasuk view <code>v_project_full</code>). Log lengkap: file error log PHP/server.</p>";
    echo "</div>\n";
    if ($headerSent) {
        // Header HTML sudah terkirim — tutup tag yang masih terbuka agar konten error tetap tampil.
        echo "</main><footer class='text-center text-xs text-slate-400 py-4 border-t border-slate-200 bg-white'>&copy; " . date('Y') . " PM Intelligence</footer></div></div></body></html>";
    }
}

// Handler exception global: mencegah "hanya header yang muncul" tanpa keterangan.
// Tanpa ini, PDOException (ERRMODE_EXCEPTION) menghentikan output tepat setelah
// header → di Synology (display_errors=Off) hasilnya halaman blank.
if (PHP_SAPI !== 'cli' && !defined('PMI_NO_EXCEPTION_HANDLER')) {
    set_exception_handler(function (Throwable $ex) {
        $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
        if ($isApi && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'msg' => 'Terjadi kesalahan server.',
                'error' => $ex->getMessage(),
            ]);
            exit;
        }
        $sent = headers_sent();
        pmiRenderError(
            'Terjadi kesalahan server',
            $ex->getMessage(),
            'File: ' . $ex->getFile() . ':' . $ex->getLine(),
            $sent
        );
        exit;
    });
}

/**
 * Coba koneksi ke MySQL/MariaDB dengan beberapa kandidat DSN.
 * Di Synology, host "localhost" membuat PDO memakai Unix socket bawaan PHP
 * (/tmp/mysql.sock) yang sering TIDAK sama dengan socket MariaDB Synology
 * (/run/mysqld/mysqld.sock) → gagal "No such file or directory".
 * Karena itu kita coba TCP (127.0.0.1) dulu, lalu beberapa path socket umum.
 */
function pmiConnectPdo(): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 15,
    ];
    $dbname = DB_NAME;

    $host = strtolower((string)DB_HOST);
    $dsnCandidates = [];

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        // TCP dulu — paling andal di Synology (MariaDB listen di 127.0.0.1:3306)
        $dsnCandidates[] = "mysql:host=127.0.0.1;dbname={$dbname};charset=utf8mb4";
        $dsnCandidates[] = "mysql:host=localhost;dbname={$dbname};charset=utf8mb4";
        // Path socket umum MariaDB/MySQL di Synology DSM & Linux umumnya
        foreach ([
            '/run/mysqld/mysqld.sock',
            '/var/run/mysqld/mysqld.sock',
            '/tmp/mysql.sock',
            '/var/lib/mysql/mysql.sock',
        ] as $sock) {
            if (file_exists($sock)) {
                $dsnCandidates[] = "mysql:unix_socket={$sock};dbname={$dbname};charset=utf8mb4";
            }
        }
    } else {
        $dsnCandidates[] = "mysql:host=" . DB_HOST . ";dbname={$dbname};charset=utf8mb4";
    }

    $errors = [];
    foreach ($dsnCandidates as $dsn) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            // Izinkan query besar (fix error #1104 MAX_JOIN_SIZE)
            $pdo->exec("SET SESSION SQL_BIG_SELECTS=1");
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    throw new PDOException(
        'Semua percobaan koneksi gagal. Terakhir: ' . implode(' || ', array_unique($errors))
    );
}

try {
    $pdo = pmiConnectPdo();
    date_default_timezone_set('Asia/Jakarta');
} catch (PDOException $e) {
    // Koneksi gagal — tampilkan halaman error yang jelas (bukan teks mentah).
    pmiRenderError(
        'Koneksi database gagal',
        $e->getMessage(),
        'Host: ' . DB_HOST . ' | DB: ' . DB_NAME . ' | User: ' . DB_USER,
        false
    );
    exit;
}
