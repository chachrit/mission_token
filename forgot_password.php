<?php
/**
 * forgot_password.php
 * ตรวจสอบตัวตนด้วย employee_code + email จาก External API
 * จากนั้นให้ติดต่อ HR เพื่อรีเซ็ตรหัสผ่านผ่านระบบกลาง
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

            if ($apiEmail === '' || $email !== $apiEmail) {
                $error = 'อีเมลไม่ตรงกับข้อมูลในระบบ กรุณาตรวจสอบอีกครั้ง';
            } else {
                $success = 'ยืนยันตัวตนสำเร็จ กรุณาติดต่อ HR เพื่อรีเซ็ตรหัสผ่านผ่านระบบกลาง';
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
    <title>ลืมรหัสผ่าน | Mission Token</title>
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

        <!-- ── Form ─────────────────────────────────────── -->
        <h1 class="fp-title">ลืมรหัสผ่าน?</h1>
        <p class="fp-sub">ยืนยันตัวตนด้วยรหัสพนักงานและอีเมลที่ลงทะเบียนไว้<br>จากนั้นติดต่อ HR เพื่อรีเซ็ตรหัสผ่านผ่านระบบกลาง</p>

        <?php if ($success): ?>
        <div class="fp-alert fp-alert--success"><?= e($success) ?></div>
        <?php endif; ?>

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
    </div>
</div>
</body>
</html>
