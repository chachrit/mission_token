<?php
/**
 * forgot_password.php
 * ตรวจสอบตัวตนด้วย employee_code + email จาก External API
 * แสดง / reset รหัสผ่านกลับเป็น default (pws_user)
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

initSession();

// ถ้า login อยู่แล้วไม่ต้องมาหน้านี้
if (!empty($_SESSION['employee_id'])) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

// ── Helper: fetch from API (copy จาก login.php) ───────────
function fpFetchEmployee(string $code): ?array
{
    $ch = curl_init(EMP_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . EMP_API_KEY,
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

    foreach ($employees as $emp) {
        if ((string)($emp['employee_id'] ?? '') === $code
            || (string)($emp['pws_user']    ?? '') === $code) {
            return $emp;
        }
    }
    return null;
}

$error   = null;
$success = null;
$shownPw = null;   // รหัสผ่านที่จะแสดง
$isNew   = false;  // พนักงานใหม่ (ยังไม่เคย login)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $code  = trim((string)($_POST['employee_code'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if ($code === '' || $email === '') {
        $error = 'กรุณากรอกรหัสพนักงานและอีเมลให้ครบ';
    } else {
        $apiEmp = fpFetchEmployee($code);

        if (!$apiEmp) {
            $error = 'ไม่พบรหัสพนักงานในระบบ กรุณาตรวจสอบรหัสอีกครั้ง';
        } else {
            $apiEmail  = strtolower(trim((string)($apiEmp['email'] ?? '')));
            $pwsUser   = (string)($apiEmp['pws_user'] ?? '');

            if ($apiEmail === '' || $email !== $apiEmail) {
                $error = 'อีเมลไม่ตรงกับข้อมูลในระบบ กรุณาตรวจสอบอีกครั้ง';
            } elseif ($pwsUser === '') {
                $error = 'ไม่สามารถดึงข้อมูลรหัสผ่านได้ กรุณาติดต่อ HR';
            } else {
                try {
                    $pdo  = getDB();
                    $stmt = $pdo->prepare("SELECT employee_id, password_hash FROM dbo.employees WHERE employee_code = ?");
                    $stmt->execute([$code]);
                    $local = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($local) {
                        // มีในระบบแล้ว → reset password กลับเป็น pws_user
                        $newHash = password_hash($pwsUser, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE dbo.employees SET password_hash = ? WHERE employee_code = ?")
                            ->execute([$newHash, $code]);
                        $isNew   = false;
                        $success = 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว';
                    } else {
                        // พนักงานใหม่ — ยังไม่เคยใช้ระบบ ไม่ต้องสร้าง account ที่นี่
                        // เพียงแสดง default password ให้ไป login เอง
                        $isNew   = true;
                        $success = 'พบข้อมูลพนักงานแล้ว';
                    }

                    $shownPw = $pwsUser;

                } catch (Throwable $e) {
                    error_log('[MissionToken] forgot_password error: ' . $e->getMessage());
                    $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน — Mission Token</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; min-height: 100vh;
            background: #091113;
            font-family: 'Prompt', sans-serif;
            color: #eeebe1;
        }
        .fp-wrap {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 1rem;
            position: relative; overflow: hidden;
        }
        /* Aurora blobs */
        .fp-blob {
            position: fixed; border-radius: 50%; pointer-events: none;
            filter: blur(80px); z-index: 0;
        }
        .fp-blob--1 {
            width: 500px; height: 500px; top: -120px; right: -100px;
            background: radial-gradient(circle, rgba(218,185,55,0.08) 0%, transparent 65%);
            animation: fp-drift 18s ease-in-out infinite alternate;
        }
        .fp-blob--2 {
            width: 400px; height: 400px; bottom: -80px; left: -80px;
            background: radial-gradient(circle, rgba(79,139,152,0.06) 0%, transparent 65%);
            animation: fp-drift 22s ease-in-out infinite alternate-reverse;
        }
        @keyframes fp-drift {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(30px,20px) scale(1.08); }
        }
        .fp-card {
            position: relative; z-index: 1;
            width: 100%; max-width: 420px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 20px;
            padding: 2.25rem 2rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 32px 80px rgba(9,17,19,0.70);
        }
        .fp-logo {
            display: flex; align-items: center; gap: 0.6rem;
            margin-bottom: 1.75rem;
        }
        .fp-logo img { width: 32px; height: 32px; object-fit: contain; }
        .fp-logo span {
            font-size: 0.78rem; font-weight: 700; letter-spacing: 0.20em;
            text-transform: uppercase; color: rgba(218,185,55,0.75);
        }
        .fp-title {
            font-size: 1.35rem; font-weight: 800; color: #eeebe1;
            margin: 0 0 0.35rem; letter-spacing: -0.01em;
        }
        .fp-sub {
            font-size: 0.80rem; color: #6b6e77; margin: 0 0 1.75rem;
            line-height: 1.55;
        }
        .fp-label {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: #8a8e97;
            margin-bottom: 0.35rem; display: block;
        }
        .fp-input {
            width: 100%; padding: 0.65rem 0.9rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px; color: #eeebe1;
            font-family: 'Prompt', sans-serif; font-size: 0.88rem;
            outline: none; transition: border-color 0.15s, background 0.15s;
            margin-bottom: 1rem;
        }
        .fp-input:focus {
            border-color: rgba(218,185,55,0.45);
            background: rgba(255,255,255,0.09);
        }
        .fp-input::placeholder { color: #3a3e43; }
        .fp-btn {
            width: 100%; padding: 0.70rem;
            background: linear-gradient(135deg, #dab937, #c9a830);
            border: none; border-radius: 10px;
            color: #091113; font-family: 'Prompt', sans-serif;
            font-size: 0.88rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            transition: opacity 0.15s, transform 0.12s;
            margin-top: 0.25rem;
        }
        .fp-btn:hover { opacity: 0.90; transform: translateY(-1px); }
        .fp-btn:active { transform: translateY(0); }
        .fp-back {
            display: block; text-align: center; margin-top: 1.25rem;
            font-size: 0.78rem; color: #6b6e77; text-decoration: none;
            transition: color 0.15s;
        }
        .fp-back:hover { color: #eeebe1; }
        .fp-alert {
            border-radius: 10px; padding: 0.75rem 1rem;
            font-size: 0.82rem; margin-bottom: 1.25rem; line-height: 1.5;
        }
        .fp-alert--error {
            background: rgba(210,89,42,0.10);
            border: 1px solid rgba(210,89,42,0.28);
            color: #e07a55;
        }
        /* Password reveal box */
        .fp-pw-box {
            background: rgba(218,185,55,0.06);
            border: 1px solid rgba(218,185,55,0.25);
            border-radius: 14px; padding: 1.25rem 1.25rem 1.1rem;
            margin-bottom: 1.5rem;
        }
        .fp-pw-label {
            font-size: 0.62rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: rgba(218,185,55,0.55);
            margin-bottom: 0.5rem; display: block;
        }
        .fp-pw-value {
            font-size: 1.35rem; font-weight: 800; letter-spacing: 0.08em;
            color: #f8e769; font-family: monospace, 'Prompt';
            word-break: break-all;
        }
        .fp-pw-note {
            font-size: 0.70rem; color: #8a8e97; margin-top: 0.55rem;
            line-height: 1.55;
        }
        .fp-pw-copy {
            display: inline-flex; align-items: center; gap: 0.35rem;
            margin-top: 0.75rem; padding: 0.30rem 0.75rem;
            border-radius: 7px; font-size: 0.72rem; font-weight: 600;
            cursor: pointer; transition: background 0.15s;
            background: rgba(218,185,55,0.12);
            border: 1px solid rgba(218,185,55,0.28);
            color: #dab937; font-family: 'Prompt', sans-serif;
        }
        .fp-pw-copy:hover { background: rgba(218,185,55,0.22); }
        .fp-new-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            background: rgba(79,139,152,0.12); border: 1px solid rgba(79,139,152,0.28);
            color: #4f8b98; border-radius: 999px;
            font-size: 0.62rem; font-weight: 700; letter-spacing: 0.06em;
            padding: 0.15rem 0.55rem; margin-bottom: 0.6rem;
        }
    </style>
</head>
<body>
<div class="fp-wrap">
    <div class="fp-blob fp-blob--1" aria-hidden="true"></div>
    <div class="fp-blob fp-blob--2" aria-hidden="true"></div>

    <div class="fp-card">
        <!-- Logo -->
        <div class="fp-logo">
            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token">
            <span>Mission Token</span>
        </div>

        <?php if ($shownPw !== null): ?>
        <!-- ── Success: show password ───────────────────── -->
        <h1 class="fp-title">รหัสผ่านของคุณ</h1>
        <p class="fp-sub">
            <?= $isNew
                ? 'นี่คือรหัสผ่านเริ่มต้นสำหรับการเข้าใช้งานครั้งแรก'
                : 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบได้ทันที' ?>
        </p>

        <div class="fp-pw-box">
            <?php if ($isNew): ?>
            <span class="fp-new-badge">✦ พนักงานใหม่</span>
            <?php endif; ?>
            <span class="fp-pw-label">
                <?= $isNew ? 'รหัสผ่านเริ่มต้น' : 'รหัสผ่านที่รีเซ็ตแล้ว' ?>
            </span>
            <div class="fp-pw-value" id="fp-pw-text"><?= e($shownPw) ?></div>
            <p class="fp-pw-note">
                <?php if ($isNew): ?>
                    ใช้รหัสนี้ในการ login ครั้งแรก แนะนำให้เปลี่ยนรหัสผ่านในหน้าโปรไฟล์หลังเข้าสู่ระบบ
                <?php else: ?>
                    รหัสผ่านถูกรีเซ็ตเป็นค่าเริ่มต้นแล้ว แนะนำให้เปลี่ยนรหัสผ่านในหน้าโปรไฟล์หลังเข้าสู่ระบบ
                <?php endif; ?>
            </p>
            <button class="fp-pw-copy" onclick="fpCopy()">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                </svg>
                <span id="fp-copy-lbl">คัดลอกรหัสผ่าน</span>
            </button>
        </div>

        <a href="<?= BASE_URL ?>/login.php"
           style="display:block; width:100%; padding:0.70rem; text-align:center;
                  background:linear-gradient(135deg,#dab937,#c9a830); border-radius:10px;
                  color:#091113; font-weight:700; font-size:0.88rem; text-decoration:none;
                  transition:opacity 0.15s;"
           onmouseover="this.style.opacity='0.90'"
           onmouseout="this.style.opacity='1'">
            ไปหน้า Login
        </a>

        <?php else: ?>
        <!-- ── Form ─────────────────────────────────────── -->
        <h1 class="fp-title">ลืมรหัสผ่าน?</h1>
        <p class="fp-sub">ยืนยันตัวตนด้วยรหัสพนักงานและอีเมลที่ลงทะเบียนไว้<br>ระบบจะแสดงรหัสผ่านเริ่มต้นให้</p>

        <?php if ($error): ?>
        <div class="fp-alert fp-alert--error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <label class="fp-label" for="fp-code">รหัสพนักงาน</label>
            <input type="text" id="fp-code" name="employee_code"
                   class="fp-input" placeholder="เช่น 110033"
                   value="<?= e($_POST['employee_code'] ?? '') ?>"
                   autocomplete="username" required>

            <label class="fp-label" for="fp-email">อีเมลที่ลงทะเบียน</label>
            <input type="email" id="fp-email" name="email"
                   class="fp-input" placeholder="yourname@journal.co.th"
                   value="<?= e($_POST['email'] ?? '') ?>"
                   autocomplete="email" required>

            <button type="submit" class="fp-btn">ยืนยันตัวตน</button>
        </form>

        <a href="<?= BASE_URL ?>/login.php" class="fp-back">← กลับหน้า Login</a>
        <?php endif; ?>
    </div>
</div>

<script>
function fpCopy() {
    var pw = document.getElementById('fp-pw-text').textContent.trim();
    navigator.clipboard.writeText(pw).then(function() {
        var lbl = document.getElementById('fp-copy-lbl');
        lbl.textContent = 'คัดลอกแล้ว ✓';
        setTimeout(function() { lbl.textContent = 'คัดลอกรหัสผ่าน'; }, 2000);
    });
}
</script>
</body>
</html>
