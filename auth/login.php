<?php
require_once __DIR__ . '/../config/auth.php';
startSecureSession();
if (isAdminLoggedIn()) { header('Location: ' . appBase() . (canAccessExecutiveDashboard() ? '/index.php' : '/projects.php')); exit; }

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — PM Intelligence</title>
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
    }
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        .auth-gradient { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); }
        .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); }
        @keyframes fadeIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease-out both; }
    </style>
</head>
<body class="auth-gradient min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md fade-in">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-red-500/25">
                <i class="bi bi-graph-up-arrow text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white">PM <span class="text-blue-400">Intelligence</span></h1>
            <p class="text-slate-400 text-sm mt-1">Login ke Panel Admin</p>
        </div>

        <!-- Card -->
        <div class="glass-card rounded-2xl shadow-2xl p-6 sm:p-8">
            <!-- Flash message -->
            <?php if ($flash): ?>
            <div class="mb-4 p-3 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-1"></i>
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
            <?php endif; ?>

            <form id="loginForm" onsubmit="return handleLogin(event)" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email</label>
                    <div class="relative">
                        <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" required placeholder="admin@email.com"
                            class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-slate-50 focus:bg-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" id="password" required placeholder="Masukkan password"
                            class="w-full pl-10 pr-10 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-slate-50 focus:bg-white">
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                            <i id="pwIcon" class="bi bi-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Error message -->
                <div id="errorMsg" class="hidden p-3 rounded-lg bg-red-50 text-red-700 text-sm font-medium border border-red-200"></div>

                <button type="submit" id="loginBtn"
                    class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 transition-all flex items-center justify-center gap-2">
                    <span id="loginText">Login</span>
                    <div id="loginSpinner" class="hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                </button>
            </form>

            <!-- Links -->
            <div class="mt-6 text-center space-y-2">
                <p class="text-sm text-slate-500">
                    Belum punya akun?
                    <a href="signup.php" class="text-blue-600 hover:text-blue-700 font-semibold transition">Daftar Sekarang</a>
                </p>
                <p class="text-sm text-slate-500">
                    <a href="forgot_password.php" class="text-slate-400 hover:text-slate-600 font-medium transition">Lupa Password?</a>
                </p>
            </div>
        </div>

        <!-- Back to user -->
        <div class="text-center mt-6">
            <a href="<?= appBase() ?>/index.php" class="text-slate-400 hover:text-white text-sm font-medium transition inline-flex items-center gap-1">
                <i class="bi bi-arrow-left text-xs"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

<script>
function togglePassword() {
    var pw = document.getElementById('password');
    var icon = document.getElementById('pwIcon');
    if (pw.type === 'password') { pw.type = 'text'; icon.className = 'bi bi-eye-slash text-sm'; }
    else { pw.type = 'password'; icon.className = 'bi bi-eye text-sm'; }
}

function handleLogin(e) {
    e.preventDefault();
    var btn = document.getElementById('loginBtn');
    var txt = document.getElementById('loginText');
    var spinner = document.getElementById('loginSpinner');
    var errEl = document.getElementById('errorMsg');

    btn.disabled = true;
    txt.textContent = 'Loading...';
    spinner.classList.remove('hidden');
    errEl.classList.add('hidden');

    var fd = new FormData(document.getElementById('loginForm'));

    fetch('<?= appBase() ?>/api/auth.php?action=login', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                txt.textContent = 'Berhasil!';
                window.location.href = d.redirect;
            } else {
                errEl.textContent = d.msg;
                errEl.classList.remove('hidden');
                btn.disabled = false;
                txt.textContent = 'Login';
                spinner.classList.add('hidden');
            }
        })
        .catch(function(err) {
            errEl.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            txt.textContent = 'Login';
            spinner.classList.add('hidden');
        });
    return false;
}
</script>
</body>
</html>
