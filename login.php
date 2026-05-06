<?php
/**
 * login.php — Employee Login Page
 * Mission Token | JOURNAL Employee Gamification
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

// Already logged in → go to appropriate dashboard
if (!empty($_SESSION['employee_id'])) {
    $r = $_SESSION['role'] ?? 'employee';
    redirect(in_array($r, ['admin','hr'])
        ? BASE_URL . '/hr/submissions.php'
        : BASE_URL . '/pages/dashboard.php'
    );
}

$error   = null;
$timeout = isset($_GET['timeout']);
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';

// ── External API Auth Helper ────────────────────────────────
/**
 * ดึงข้อมูลพนักงานจาก webportal_dev API โดย employee_id
 * คืน array ข้อมูลพนักงาน หรือ null ถ้าไม่พบ / API ล้มเหลว
 */
function fetchEmployeeFromAPI(string $employeeCode): ?array
{
    $apiUrl = EMP_API_URL;
    $apiKey = EMP_API_KEY;

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data      = json_decode($response, true);
    $employees = $data['data'] ?? [];
    if (empty($employees)) return null;

    // กรองโดย employee_id หรือ pws_user
    foreach ($employees as $emp) {
        if ((string)($emp['employee_id'] ?? '') === $employeeCode
            || (string)($emp['pws_user']    ?? '') === $employeeCode) {
            return $emp;
        }
    }

    return null;
}

/**
 * Sync / upsert ข้อมูลพนักงานจาก API เข้า local DB (mission_token.dbo.employees)
 * คืน local employee_id
 */
function syncEmployeeFromAPI(PDO $pdo, array $apiEmp, string $employeeCode, string $localHash): int
{
    $fullName   = trim(
        ($apiEmp['prefix_th']     ?? '') . ' ' .
        ($apiEmp['first_name_th'] ?? '') . ' ' .
        ($apiEmp['last_name_th']  ?? '')
    );
    $department = $apiEmp['department']  ?? '';
    $division   = $apiEmp['division']    ?? '';
    $level      = $apiEmp['level']       ?? '';
    $position   = $apiEmp['position_th'] ?? $apiEmp['position'] ?? '';
    $email      = $apiEmp['email']       ?? '';

    // Auto-determine role from API:
    // JD011 = ฝ่าย HR, JL002+ = ระดับเจ้าหน้าที่ขึ้นไป (ไม่นับพ่อบ้าน/แมสเซนเจอร์ JL000-JL001)
    // JD001 = ฝ่าย IT, ทุกคนได้ it role (เข้าหน้า admin ได้ แต่แยก badge)
    if ($division === 'JD011' && $level >= 'JL002') {
        $autoRole = 'hr';
    } elseif ($division === 'JD001') {
        $autoRole = 'it';
    } else {
        $autoRole = 'employee';
    }

    // parse start_date — API ส่งมาเป็น "YYYY-MM-DD HH:MM:SS" หรือ "YYYY-MM-DD"
    $startDateRaw = $apiEmp['start_date'] ?? null;
    $startDate    = null;
    if ($startDateRaw && !str_starts_with($startDateRaw, '1900')) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $startDateRaw)
           ?: DateTime::createFromFormat('Y-m-d', $startDateRaw);
        $startDate = $dt ? $dt->format('Y-m-d') : null;
    }

    // ตรวจสอบว่ามีใน local แล้วหรือยัง
    $stmt = $pdo->prepare("SELECT employee_id, role FROM dbo.employees WHERE employee_code = ?");
    $stmt->execute([$employeeCode]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // Insert ใหม่ — ใช้ autoRole ที่คำนวณไว้
        $ins = $pdo->prepare("
            INSERT INTO dbo.employees
                (employee_code, full_name, department, division, level, position, email, password_hash, role, start_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$employeeCode, $fullName, $department, $division, $level,
                       $position, $email, $localHash, $autoRole, $startDate]);

        // ดึง ID ที่เพิ่งสร้าง — re-query เพราะ SCOPE_IDENTITY() คืน NULL กับ pdo_sqlsrv
        $sel = $pdo->prepare("SELECT employee_id FROM dbo.employees WHERE employee_code = ?");
        $sel->execute([$employeeCode]);
        $localId = (int)$sel->fetchColumn();
    } else {
        // Update — ไม่ทับ role 'admin' ที่ set ไว้แบบ manual
        $currentRole = (string)($existing['role'] ?? 'employee');
        $newRole     = ($currentRole === 'admin') ? 'admin' : $autoRole;

        $upd = $pdo->prepare("
            UPDATE dbo.employees
            SET full_name  = ?, department = ?, division = ?, level = ?,
                position   = ?, email = ?, start_date = ?, role = ?
            WHERE employee_code = ?
        ");
        $upd->execute([$fullName, $department, $division, $level,
                       $position, $email, $startDate, $newRole, $employeeCode]);

        $localId = (int)$existing['employee_id'];
    }

    return $localId;
}

// ── POST: handle login ──────────────────────────────────────
if (isPost()) {
    validateCsrf();

    $code     = trim((string)($_POST['employee_code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($code === '' || $password === '') {
        $error = 'กรุณากรอกรหัสพนักงานและรหัสผ่าน';
    } else {
        try {
            $pdo     = getDB();
            $apiEmp  = fetchEmployeeFromAPI($code);
            $useAPI  = $apiEmp !== null;

            if ($useAPI) {
                // ── API Auth ─────────────────────────────────
                // API ไม่ส่ง password กลับมา → ตรวจจาก local DB ก่อน
                // ถ้ายังไม่มีใน local → ใช้ pws_user เป็น default password
                $passOk    = false;
                $localHash = null;

                // ตรวจว่ามี local record + password hash อยู่แล้วไหม
                $chkStmt = $pdo->prepare("SELECT password_hash FROM dbo.employees WHERE employee_code = ?");
                $chkStmt->execute([$code]);
                $existingHash = $chkStmt->fetchColumn();

                if ($existingHash) {
                    // มี local hash → verify ปกติ
                    $passOk    = password_verify($password, (string)$existingHash);
                    $localHash = (string)$existingHash;
                } else {
                    // ยังไม่เคย login → ใช้ pws_user เป็น default password
                    $pwsUser = (string)($apiEmp['pws_user'] ?? '');
                    if ($pwsUser !== '' && hash_equals($pwsUser, $password)) {
                        $passOk    = true;
                        $localHash = password_hash($password, PASSWORD_DEFAULT);
                    }
                }

                if (!$passOk) {
                    $error = 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง';
                } else {

                    // Sync ข้อมูลพนักงานเข้า local DB
                    $localEmpId = syncEmployeeFromAPI($pdo, $apiEmp, $code, $localHash);

                    // สร้าง wallet ถ้ายังไม่มี
                    $pdo->prepare("
                        IF NOT EXISTS (SELECT 1 FROM dbo.token_wallets WHERE employee_id = ?)
                            INSERT INTO dbo.token_wallets (employee_id) VALUES (?)
                    ")->execute([$localEmpId, $localEmpId]);

                    // ดึงข้อมูลล่าสุดจาก local DB (รวม balance)
                    $stmt = $pdo->prepare("
                        SELECT e.employee_id, e.employee_code, e.full_name,
                               e.department, e.position, e.role,
                               e.avatar_url, e.is_active,
                               COALESCE(w.balance, 0) AS token_balance
                        FROM   dbo.employees e
                        LEFT JOIN dbo.token_wallets w ON w.employee_id = e.employee_id
                        WHERE  e.employee_id = ?
                    ");
                    $stmt->execute([$localEmpId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user || !(bool)$user['is_active']) {
                        $error = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อ HR';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['employee_id']   = (int)$user['employee_id'];
                        $_SESSION['employee_code'] = $user['employee_code'];
                        $_SESSION['full_name']     = $user['full_name'];
                        $_SESSION['department']    = $user['department'] ?? '';
                        $_SESSION['position']      = $user['position']  ?? '';
                        $_SESSION['role']          = $user['role'];
                        $_SESSION['avatar_url']    = $user['avatar_url'] ?? '';
                        $_SESSION['token_balance'] = (int)$user['token_balance'];
                        $_SESSION['last_activity'] = time();

                        // HR/admin → หน้า HR zone; employee/IT → dashboard
                        $hrRoles = ['admin', 'hr'];
                        $dest = in_array($user['role'], $hrRoles)
                            ? BASE_URL . '/hr/submissions.php'
                            : BASE_URL . '/pages/dashboard.php';
                        if ($redirect !== '') {
                            $decoded = urldecode($redirect);
                            if (str_starts_with($decoded, '/mission_token/')) {
                                $dest = 'http://localhost' . $decoded;
                            }
                        }
                        redirect($dest);
                    }
                }

            } else {
                // ── Fallback: Local DB Auth ───────────────────
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
                    $pdo->prepare("
                        IF NOT EXISTS (SELECT 1 FROM dbo.token_wallets WHERE employee_id = ?)
                            INSERT INTO dbo.token_wallets (employee_id) VALUES (?)
                    ")->execute([$user['employee_id'], $user['employee_id']]);

                    session_regenerate_id(true);
                    $_SESSION['employee_id']   = (int)$user['employee_id'];
                    $_SESSION['employee_code'] = $user['employee_code'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['department']    = $user['department'] ?? '';
                    $_SESSION['position']      = $user['position']  ?? '';
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['avatar_url']    = $user['avatar_url'] ?? '';
                    $_SESSION['token_balance'] = (int)$user['token_balance'];
                    $_SESSION['last_activity'] = time();

                    // HR/admin → หน้า HR zone; employee/IT → dashboard
                    $hrRoles = ['admin', 'hr'];
                    $dest = in_array($user['role'], $hrRoles)
                        ? BASE_URL . '/hr/submissions.php'
                        : BASE_URL . '/pages/dashboard.php';
                    if ($redirect !== '') {
                        $decoded = urldecode($redirect);
                        if (str_starts_with($decoded, '/mission_token/')) {
                            $dest = 'http://localhost' . $decoded;
                        }
                    }
                    redirect($dest);
                }
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
<body class="layout-login font-prompt">

    <!-- Background glow orbs -->
    <div class="login-bg-orb login-orb-1" aria-hidden="true"></div>
    <div class="login-bg-orb login-orb-2" aria-hidden="true"></div>

    <!-- Floating coins -->
    <div class="login-coins" aria-hidden="true">
        <div class="lcoin lcoin-1"><img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt=""></div>
        <div class="lcoin lcoin-2"><img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt=""></div>
        <div class="lcoin lcoin-3"><img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt=""></div>
        <div class="lcoin lcoin-4"><img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt=""></div>
    </div>

    <!-- Login Card -->
    <div class="login-card">

        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="JOURNAL" class="login-logo mx-auto mb-5">
            <p class="text-xs font-semibold tracking-[0.32em] uppercase" style="color:#dab937;">MISSION TOKEN</p>
        </div>

        <div class="mb-7">
            <h2 class="text-xl font-semibold tracking-wide" style="color:#eeebe1;">เข้าสู่ระบบ</h2>
            <p class="mt-1.5 text-sm" style="color:#6b6e77;">ใช้รหัสพนักงานและรหัสผ่านของคุณ</p>
        </div>

        <!-- Timeout alert -->
        <?php if ($timeout): ?>
        <div class="alert-timeout mb-5 flex items-start gap-3 px-4 py-3 text-sm">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Session หมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่</span>
        </div>
        <?php endif; ?>

        <!-- Error alert -->
        <?php if ($error): ?>
        <div class="alert-error mb-5 flex items-start gap-3 px-4 py-3 text-sm" id="error-alert">
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
                <label class="mb-1.5 block text-xs font-medium tracking-wider uppercase" style="color:#6b6e77;" for="employee_code">
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
            <div class="mb-7">
                <label class="mb-1.5 block text-xs font-medium tracking-wider uppercase" style="color:#6b6e77;" for="password">
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
                    <button type="button" class="pass-toggle" aria-label="แสดง/ซ่อนรหัสผ่าน">
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
            <a href="<?php echo BASE_URL; ?>/index.php" class="text-xs transition-colors" style="color:#6b6e77;"
               onmouseover="this.style.color='#dab937'" onmouseout="this.style.color='#6b6e77'">
                ← กลับหน้าแรก
            </a>
        </div>

    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
