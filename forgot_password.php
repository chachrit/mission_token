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

</head>
<body class="fp-page">
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
            <span class="fp-new-badge">พนักงานใหม่</span>
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
            <button class="fp-pw-copy" id="fp-copy-btn">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                </svg>
                <span id="fp-copy-lbl">คัดลอกรหัสผ่าน</span>
            </button>
        </div>

        <a href="<?= BASE_URL ?>/login.php"
           class="fp-login-link">
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
        lbl.textContent = 'คัดลอกแล้ว';
        setTimeout(function() { lbl.textContent = 'คัดลอกรหัสผ่าน'; }, 2000);
    });
}

document.getElementById('fp-copy-btn')?.addEventListener('click', fpCopy);
</script>
</body>
</html>
