<?php
/**
 * seed.php — Initial Data Seeder
 * Run once: http://localhost/missoin_token/sql/seed.php
 * ⚠️  DELETE or MOVE this file after running in production!
 *
 * Creates:
 *  - 1 Admin user     (code: ADMIN001 / pass: Admin@2026)
 *  - 2 Sample employees (code: EMP001, EMP002 / pass: Emp@2026)
 *  - 2 Sample challenges (1 quiz, 1 photo)
 *  - Quiz questions for the quiz challenge
 *  - Wallets for all users
 */

require_once __DIR__ . '/../config/database.php';

// Safety: only allow localhost access
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDB();
$log = [];
$errors = [];

function seedLog(array &$log, string $msg): void {
    $log[] = $msg;
}

try {
    $pdo->beginTransaction();

    // ============================================================
    // 1. Admin user
    // ============================================================
    $adminHash = password_hash('Admin@2026', PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("
        IF NOT EXISTS (SELECT 1 FROM employees WHERE employee_code = ?)
        INSERT INTO employees (employee_code, full_name, department, position, email, password_hash, role)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'ADMIN001',
        'ADMIN001', 'System Admin', 'IT Department', 'System Administrator',
        'admin@journal.co.th', $adminHash, 'admin'
    ]);
    seedLog($log, '✓ Admin user: ADMIN001 / Admin@2026');

    // Get admin ID
    $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
    $stmt->execute(['ADMIN001']);
    $adminId = (int)$stmt->fetchColumn();

    // Wallet for admin
    $stmt = $pdo->prepare("
        IF NOT EXISTS (SELECT 1 FROM token_wallets WHERE employee_id = ?)
        INSERT INTO token_wallets (employee_id) VALUES (?)
    ");
    $stmt->execute([$adminId, $adminId]);

    // ============================================================
    // 2. Sample Employees
    // ============================================================
    $empHash = password_hash('Emp@2026', PASSWORD_BCRYPT, ['cost' => 12]);
    $employees = [
        ['EMP001', 'สมชาย ใจดี',    'Marketing',   'Marketing Executive',  'somchai@journal.co.th'],
        ['EMP002', 'สมหญิง รักงาน', 'Human Resources', 'HR Specialist',    'somying@journal.co.th'],
        ['EMP003', 'วิชัย สุขสันต์', 'Operations',  'Operations Manager',   'wichai@journal.co.th'],
    ];

    foreach ($employees as $emp) {
        $stmt = $pdo->prepare("
            IF NOT EXISTS (SELECT 1 FROM employees WHERE employee_code = ?)
            INSERT INTO employees (employee_code, full_name, department, position, email, password_hash, role)
            VALUES (?, ?, ?, ?, ?, ?, 'employee')
        ");
        $stmt->execute([
            $emp[0],
            $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $empHash
        ]);

        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
        $stmt->execute([$emp[0]]);
        $empId = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            IF NOT EXISTS (SELECT 1 FROM token_wallets WHERE employee_id = ?)
            INSERT INTO token_wallets (employee_id) VALUES (?)
        ");
        $stmt->execute([$empId, $empId]);

        seedLog($log, "✓ Employee: {$emp[0]} — {$emp[1]}");
    }

    // ============================================================
    // 3. Sample Challenge 1 — Quiz (auto-check)
    // ============================================================
    $today = date('Y-m-d');
    $nextMonth = date('Y-m-d', strtotime('+30 days'));

    $stmt = $pdo->prepare("
        IF NOT EXISTS (SELECT 1 FROM challenges WHERE title = N'ทดสอบความรู้แบรนด์ JOURNAL')
        INSERT INTO challenges (title, description, type, instructions, token_reward, start_date, end_date, created_by)
        VALUES (?, ?, 'quiz', ?, 20, ?, ?, ?)
    ");
    $stmt->execute([
        'ทดสอบความรู้แบรนด์ JOURNAL',
        'ทดสอบความรู้เกี่ยวกับแบรนด์ JOURNAL ของเรา ตอบถูกรับ 20 Token ทันที!',
        'ตอบคำถาม 1 ข้อ เลือกคำตอบที่ถูกต้องที่สุด รับ Token ทันทีเมื่อตอบถูก',
        $today, $nextMonth, $adminId
    ]);
    seedLog($log, '✓ Challenge (Quiz): ทดสอบความรู้แบรนด์ JOURNAL');

    $stmt = $pdo->prepare("SELECT challenge_id FROM challenges WHERE title = N'ทดสอบความรู้แบรนด์ JOURNAL'");
    $stmt->execute();
    $quizId = (int)$stmt->fetchColumn();

    // Quiz questions
    $questions = [
        [
            $quizId,
            'สีหลักของแบรนด์ JOURNAL ที่เรียกว่า "ขาวผ่อง" มีรหัสสีใด?',
            '#eeebe1', '#ffffff', '#faf0cf', '#cecdcd',
            'A',
            'ขาวผ่อง (9043C) มีรหัสสี #eeebe1 เป็นสีหลักของแบรนด์ได้รับแรงบันดาลใจจากสีไทยดั้งเดิม',
            1
        ],
        [
            $quizId,
            'แบรนด์ JOURNAL ได้รับแรงบันดาลใจสีจากแหล่งใด?',
            'Traditional Thai Tone Colours', 'Pantone International', 'Japanese Wabi-sabi', 'European Heritage',
            'A',
            'JOURNAL ได้รับแรงบันดาลใจจาก Traditional Thai Tone Colours เพื่อสะท้อนความเป็นไทยผ่านสีสัน',
            2
        ],
        [
            $quizId,
            'สีทองพิเศษ (Special Colour) ของแบรนด์ JOURNAL คือ Pantone ใด?',
            'PANTONE 871C (ทอง)', 'PANTONE 116C (เหลือง)', 'PANTONE 877C (เงิน)', 'PANTONE 876C (นาก)',
            'A',
            'PANTONE 871C คือสีทองพิเศษ (Special Colour) ของ JOURNAL ใช้ในงานพิมพ์ foil stamp และงานดีไซน์พิเศษ',
            3
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO quiz_questions (challenge_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, display_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($questions as $q) {
        // Check if already exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM quiz_questions WHERE challenge_id = ? AND question_text = ?");
        $check->execute([$q[0], $q[1]]);
        if ((int)$check->fetchColumn() === 0) {
            $stmt->execute($q);
        }
    }
    seedLog($log, '✓ Quiz questions: 3 ข้อ');

    // ============================================================
    // 4. Sample Challenge 2 — Photo Upload (Admin Approve)
    // ============================================================
    $stmt = $pdo->prepare("
        IF NOT EXISTS (SELECT 1 FROM challenges WHERE title = N'บันทึกกิจกรรมทีม')
        INSERT INTO challenges (title, description, type, instructions, token_reward, start_date, end_date, created_by)
        VALUES (?, ?, 'photo', ?, 30, ?, ?, ?)
    ");
    $stmt->execute([
        'บันทึกกิจกรรมทีม',
        'ถ่ายรูปกิจกรรมที่คุณทำร่วมกับเพื่อนร่วมงาน แล้วส่งเพื่อรับ 30 Token!',
        "1. ถ่ายรูปขณะทำกิจกรรมกับทีม (อย่างน้อย 2 คนขึ้นไป)\n2. รูปต้องเห็นหน้าชัดเจน\n3. รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 5MB\n4. ทีม Admin จะตรวจสอบและอนุมัติภายใน 1 วันทำการ",
        $today, $nextMonth, $adminId
    ]);
    seedLog($log, '✓ Challenge (Photo): บันทึกกิจกรรมทีม');

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Token — Database Seeder</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; background: #eeebe1; color: #091113; }
        h1 { font-size: 1.5rem; font-weight: 600; border-bottom: 2px solid #dab937; padding-bottom: 12px; }
        .log-item { padding: 8px 16px; margin: 6px 0; background: white; border-left: 4px solid #518e5c; border-radius: 4px; font-size: 0.9rem; }
        .error-item { padding: 8px 16px; margin: 6px 0; background: #fff0ec; border-left: 4px solid #d2592a; border-radius: 4px; font-size: 0.9rem; }
        .credentials { background: #091113; color: #eeebe1; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .credentials h2 { color: #dab937; font-size: 1rem; margin: 0 0 12px; }
        .credentials code { color: #f8e769; }
        .warning { background: #dab937; color: #091113; padding: 12px 16px; border-radius: 6px; margin-top: 16px; font-weight: 600; font-size: 0.875rem; }
        .btn { display: inline-block; background: #091113; color: #eeebe1; padding: 10px 20px; border-radius: 6px; text-decoration: none; margin-top: 16px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <h1>🌱 Mission Token — Database Seeder</h1>

    <?php if (!empty($errors)): ?>
        <h2 style="color:#d2592a;">❌ Errors</h2>
        <?php foreach ($errors as $err): ?>
            <div class="error-item"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php else: ?>
        <h2 style="color:#518e5c;">✅ Completed Successfully</h2>
        <?php foreach ($log as $item): ?>
            <div class="log-item"><?= htmlspecialchars($item) ?></div>
        <?php endforeach; ?>

        <div class="credentials">
            <h2>🔑 Login Credentials</h2>
            <p><strong>Admin:</strong> code = <code>ADMIN001</code> &nbsp;|&nbsp; password = <code>Admin@2026</code></p>
            <p><strong>Employee:</strong> code = <code>EMP001</code> / <code>EMP002</code> / <code>EMP003</code> &nbsp;|&nbsp; password = <code>Emp@2026</code></p>
        </div>

        <div class="warning">
            ⚠️ ลบหรือย้ายไฟล์นี้ออกก่อน deploy ขึ้น Production เพราะจะเปิดเผย credentials!
        </div>

        <a href="../index.php" class="btn">→ ไปหน้า Login</a>
    <?php endif; ?>
</body>
</html>
