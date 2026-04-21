<?php
/**
 * login.php — Employee Login Page
 * Mission Token | JOURNAL Employee Gamification
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

// Already logged in → go to dashboard
if (!empty($_SESSION['employee_id'])) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$error   = null;
$timeout = isset($_GET['timeout']);
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';

// ── POST: handle login ──────────────────────────────────────
if (isPost()) {
    validateCsrf();

    $code     = trim((string)($_POST['employee_code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($code === '' || $password === '') {
        $error = 'กรุณากรอกรหัสพนักงานและรหัสผ่าน';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("
                SELECT e.employee_id, e.employee_code, e.full_name,
                       e.department,  e.position,      e.role,
                       e.password_hash, e.avatar_url,  e.is_active,
                       COALESCE(w.balance, 0) AS token_balance
                FROM   dbo.employees e
                LEFT JOIN dbo.token_wallets w ON w.employee_id = e.employee_id
                WHERE  e.employee_code = ?
            ");
            $stmt->execute([$code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง';
            } elseif (!(bool)$user['is_active']) {
                $error = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อ HR';
            } elseif (!password_verify($password, (string)$user['password_hash'])) {
                $error = 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง';
            } else {
                // Auto-create wallet if missing
                $wStmt = $pdo->prepare("
                    IF NOT EXISTS (SELECT 1 FROM dbo.token_wallets WHERE employee_id = ?)
                        INSERT INTO dbo.token_wallets (employee_id) VALUES (?)
                ");
                $wStmt->execute([$user['employee_id'], $user['employee_id']]);

                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['employee_id']    = (int)$user['employee_id'];
                $_SESSION['employee_code']  = $user['employee_code'];
                $_SESSION['full_name']      = $user['full_name'];
                $_SESSION['department']     = $user['department'] ?? '';
                $_SESSION['position']       = $user['position']  ?? '';
                $_SESSION['role']           = $user['role'];
                $_SESSION['avatar_url']     = $user['avatar_url'] ?? '';
                $_SESSION['token_balance']  = (int)$user['token_balance'];
                $_SESSION['last_activity']  = time();

                // Safe redirect — only allow relative paths within our app
                $dest = BASE_URL . '/pages/dashboard.php';
                if ($redirect !== '') {
                    $decoded = urldecode($redirect);
                    if (str_starts_with($decoded, '/mission_token/')) {
                        $dest = 'http://localhost' . $decoded;
                    }
                }

                redirect($dest);
            }
        } catch (Throwable $e) {
            error_log('[MissionToken] login error: ' . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ — JOURNAL Mission Token</title>
    <meta name="csrf-token" content="<?php echo e(csrfToken()); ?>">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'j-white':    '#eeebe1',
                        'j-ivory':    '#fdfcdf',
                        'j-silver':   '#cecdcd',
                        'j-slate':    '#6b6e77',
                        'j-charcoal': '#3a3e43',
                        'j-dark':     '#091113',
                        'j-panel':    '#0d1618',
                        'j-gold':     '#dab937',
                        'j-gold-dk':  '#c9a830',
                        'j-gold-l':   '#f8e769',
                        'j-orange':   '#d2592a',
                    },
                    fontFamily: { 'prompt': ['Prompt', 'sans-serif'] },
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body class="layout-login font-prompt min-h-screen">

    <!-- ── LEFT: Branding Panel ─────────────────────────────── -->
    <div class="mountain-bg hidden w-[52%] flex-col justify-between p-12 lg:flex xl:p-16">

        <!-- Floating particles (subtle, gold tones on light bg) -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <?php for ($i = 0; $i < 10; $i++):
                $size  = rand(3, 8);
                $left  = rand(5, 90);
                $delay = rand(0, 6000) / 1000;
                $dur   = rand(5000, 10000) / 1000;
                $gold  = $i % 2 === 0;
            ?>
            <div class="particle <?php echo $gold ? 'bg-j-gold opacity-20' : 'bg-j-charcoal opacity-10'; ?>"
                 style="width:<?php echo $size; ?>px;height:<?php echo $size; ?>px;left:<?php echo $left; ?>%;bottom:0;animation-delay:<?php echo $delay; ?>s;animation-duration:<?php echo $dur; ?>s;">
            </div>
            <?php endfor; ?>
        </div>

        <!-- Logo -->
        <div class="relative z-10">
            <p class="text-xs font-semibold tracking-[0.35em] text-j-gold uppercase">JOURNAL</p>
        </div>

        <!-- Center content -->
        <div class="relative z-10 -mt-16">

            <h1 class="text-4xl font-semibold leading-tight tracking-wide text-j-dark xl:text-5xl">
                MISSION<br>
                <span class="text-j-gold">TOKEN</span>
            </h1>
            <p class="mt-4 max-w-xs text-sm leading-7 text-j-slate">
                สะสม Token จากภารกิจ พิชิตทุก challenge<br>
            </p>

            <!-- Feature badges -->

        </div>

        <!-- Footer -->
        <div class="relative z-10">
            <p class="text-xs text-j-slate">© <?php echo date('Y'); ?> JOURNAL. All rights reserved.</p>
        </div>
    </div>

    <!-- ── RIGHT: Login Panel ────────────────────────────────── -->
    <div class="login-panel flex flex-1 flex-col items-center justify-center border-l border-[#e6e2d6] px-6 py-12 sm:px-10">

        <!-- Mobile logo -->
        <div class="mb-8 text-center lg:hidden">
            <div class="coin-icon mx-auto mb-4 inline-flex">
                <div class="coin-gold flex h-16 w-16 items-center justify-center rounded-full text-2xl font-bold text-j-dark">
                
                </div>
            </div>
            <p class="text-sm font-semibold tracking-[0.3em] text-j-gold">JOURNAL · MISSION TOKEN</p>
        </div>

        <div class="w-full max-w-sm">
            <div class="mb-8">
                <h2 class="text-2xl font-semibold tracking-wide text-j-dark">เข้าสู่ระบบ</h2>
                <p class="mt-1.5 text-sm text-j-slate">ใช้รหัสพนักงานและรหัสผ่านของคุณ</p>
            </div>

            <!-- Timeout alert -->
            <?php if ($timeout): ?>
            <div class="alert-timeout mb-5 flex items-start gap-3 rounded-xl px-4 py-3 text-sm">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Session หมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่</span>
            </div>
            <?php endif; ?>

            <!-- Error alert -->
            <?php if ($error): ?>
            <div class="alert-error mb-5 flex items-start gap-3 rounded-xl px-4 py-3 text-sm" id="error-alert">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?php echo e($error); ?></span>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="login-form" novalidate>
                <?php echo csrfField(); ?>
                <?php if ($redirect !== ''): ?>
                    <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                <?php endif; ?>

                <!-- Employee Code -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-xs font-medium tracking-wider text-j-slate uppercase" for="employee_code">
                        รหัสพนักงาน
                    </label>
                    <input
                        id="employee_code"
                        name="employee_code"
                        type="text"
                        class="j-input"
                        placeholder="เช่น EMP001"
                        value="<?php echo e((string)($_POST['employee_code'] ?? '')); ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-xs font-medium tracking-wider text-j-slate uppercase" for="password">
                        รหัสผ่าน
                    </label>
                    <div class="pass-wrap">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="j-input j-input-pr"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="pass-toggle" onclick="togglePassword()" aria-label="แสดง/ซ่อนรหัสผ่าน">
                            <svg id="eye-show" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg id="eye-hide" class="hidden h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="login-btn">
                    เข้าสู่ระบบ
                </button>
            </form>

            <!-- Back to home -->
            <div class="mt-6 text-center">
                <a href="<?php echo BASE_URL; ?>/index.php" class="text-xs text-j-slate transition-colors hover:text-j-gold">
                    ← กลับหน้าแรก
                </a>
            </div>
        </div>
    </div>

</body>
<script>
    function togglePassword() {
        const input   = document.getElementById('password');
        const eyeShow = document.getElementById('eye-show');
        const eyeHide = document.getElementById('eye-hide');
        if (input.type === 'password') {
            input.type = 'text';
            eyeShow.classList.add('hidden');
            eyeHide.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeShow.classList.remove('hidden');
            eyeHide.classList.add('hidden');
        }
    }

    document.getElementById('login-form').addEventListener('submit', function () {
        const btn = document.getElementById('login-btn');
        btn.disabled    = true;
        btn.textContent = 'กำลังเข้าสู่ระบบ...';
    });
</script>
</html>
