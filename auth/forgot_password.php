<?php
require_once __DIR__ . '/../config/auth.php';
startSecureSession();
if (isAdminLoggedIn()) { header('Location: ' . appBase() . (canAccessExecutiveDashboard() ? '/index.php' : '/projects.php')); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password — PM Intelligence</title>
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
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-red-500/25">
                <i class="bi bi-graph-up-arrow text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white">PM <span class="text-blue-400">Intelligence</span></h1>
            <p class="text-slate-400 text-sm mt-1">Reset Password</p>
        </div>

        <div class="glass-card rounded-2xl shadow-2xl p-6 sm:p-8">

            <!-- Step 1: Enter Email -->
            <div id="step1">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-key text-amber-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-slate-600">Masukkan email akun Anda untuk reset password.</p>
                </div>

                <form id="emailForm" onsubmit="return handleCheckEmail(event)" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email</label>
                        <div class="relative">
                            <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="email" name="email" required placeholder="admin@email.com"
                                class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-slate-50 focus:bg-white">
                        </div>
                    </div>

                    <div id="errorMsg1" class="hidden p-3 rounded-lg bg-red-50 text-red-700 text-sm font-medium border border-red-200"></div>

                    <button type="submit" id="submitBtn1"
                        class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 transition-all flex items-center justify-center gap-2">
                        <span id="submitText1">Lanjutkan</span>
                        <div id="submitSpinner1" class="hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    </button>
                </form>
            </div>

            <!-- Step 2: Set New Password -->
            <div id="step2" class="hidden">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-shield-lock text-emerald-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-slate-600">Halo <strong id="adminName" class="text-slate-800"></strong>, masukkan password baru Anda.</p>
                </div>

                <form id="resetForm" onsubmit="return handleResetPassword(event)" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password Baru</label>
                        <div class="relative">
                            <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" name="new_password" id="newPassword" required minlength="8" placeholder="Minimal 8 karakter"
                                class="w-full pl-10 pr-10 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-slate-50 focus:bg-white">
                            <button type="button" onclick="togglePassword('newPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Konfirmasi Password</label>
                        <div class="relative">
                            <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" name="confirm_password" id="confirmPassword" required minlength="8" placeholder="Ulangi password baru"
                                class="w-full pl-10 pr-10 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-slate-50 focus:bg-white">
                            <button type="button" onclick="togglePassword('confirmPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div id="errorMsg2" class="hidden p-3 rounded-lg bg-red-50 text-red-700 text-sm font-medium border border-red-200"></div>
                    <div id="successMsg2" class="hidden p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-medium border border-emerald-200"></div>

                    <button type="submit" id="submitBtn2"
                        class="w-full py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                        <span id="submitText2">Reset Password</span>
                        <div id="submitSpinner2" class="hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <button onclick="backToStep1()" class="text-sm font-semibold text-slate-500 hover:text-slate-700 transition inline-flex items-center gap-1">
                        <i class="bi bi-arrow-left text-xs"></i> Ganti Email
                    </button>
                </div>
            </div>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition inline-flex items-center gap-1">
                    <i class="bi bi-arrow-left text-xs"></i> Kembali ke Login
                </a>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="<?= appBase() ?>/index.php" class="text-slate-400 hover:text-white text-sm font-medium transition inline-flex items-center gap-1">
                <i class="bi bi-arrow-left text-xs"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

<script>
function togglePassword(id, btn) {
    var input = document.getElementById(id);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-sm';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye text-sm';
    }
}

function handleCheckEmail(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn1');
    var txt = document.getElementById('submitText1');
    var spinner = document.getElementById('submitSpinner1');
    var errEl = document.getElementById('errorMsg1');

    btn.disabled = true;
    txt.textContent = 'Mengecek...';
    spinner.classList.remove('hidden');
    errEl.classList.add('hidden');

    var fd = new FormData(document.getElementById('emailForm'));

    fetch('<?= appBase() ?>/api/auth.php?action=check_email', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                document.getElementById('adminName').textContent = d.name || 'Admin';
                document.getElementById('step1').classList.add('hidden');
                document.getElementById('step2').classList.remove('hidden');
            } else {
                errEl.textContent = d.msg;
                errEl.classList.remove('hidden');
                btn.disabled = false;
                txt.textContent = 'Lanjutkan';
                spinner.classList.add('hidden');
            }
        })
        .catch(function() {
            errEl.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            txt.textContent = 'Lanjutkan';
            spinner.classList.add('hidden');
        });
    return false;
}

function handleResetPassword(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn2');
    var txt = document.getElementById('submitText2');
    var spinner = document.getElementById('submitSpinner2');
    var errEl = document.getElementById('errorMsg2');
    var sucEl = document.getElementById('successMsg2');

    var newPass = document.getElementById('newPassword').value;
    var confPass = document.getElementById('confirmPassword').value;

    if (newPass.length < 8) {
        errEl.textContent = 'Password minimal 8 karakter.';
        errEl.classList.remove('hidden');
        return false;
    }

    if (newPass !== confPass) {
        errEl.textContent = 'Konfirmasi password tidak cocok.';
        errEl.classList.remove('hidden');
        return false;
    }

    btn.disabled = true;
    txt.textContent = 'Meriset...';
    spinner.classList.remove('hidden');
    errEl.classList.add('hidden');
    sucEl.classList.add('hidden');

    var fd = new FormData(document.getElementById('resetForm'));

    fetch('<?= appBase() ?>/api/auth.php?action=reset_password', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                sucEl.textContent = d.msg;
                sucEl.classList.remove('hidden');
                setTimeout(function() { window.location.href = d.redirect; }, 2000);
            } else {
                errEl.textContent = d.msg;
                errEl.classList.remove('hidden');
                btn.disabled = false;
                txt.textContent = 'Reset Password';
                spinner.classList.add('hidden');
            }
        })
        .catch(function() {
            errEl.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            txt.textContent = 'Reset Password';
            spinner.classList.add('hidden');
        });
    return false;
}

function backToStep1() {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
    document.getElementById('errorMsg2').classList.add('hidden');
    document.getElementById('successMsg2').classList.add('hidden');
    document.getElementById('resetForm').reset();
    document.getElementById('submitBtn2').disabled = false;
    document.getElementById('submitText2').textContent = 'Reset Password';
    document.getElementById('submitSpinner2').classList.add('hidden');
}
</script>
</body>
</html>
