<?php
/**
 * pages/profile.php
 * Employee profile — view info, work tenure, change password
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/strava.php';
require_once __DIR__ . '/../config/strava.php';

$employeeId = (int)$_SESSION['employee_id'];

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = (string)($_POST['_action'] ?? '');

    // ── upload avatar ────────────────────────────────────────
    if ($action === 'upload_avatar') {
        $file   = $_FILES['avatar'] ?? null;
        $errors = [];

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'ไม่พบไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด';
        } else {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                       'image/gif'  => 'gif', 'image/webp' => 'webp'];

            if (!in_array($mime, $allowedMimes, true)) {
                $errors[] = 'ไฟล์ต้องเป็นภาพ (JPG, PNG, GIF, WEBP) เท่านั้น';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'ขนาดไฟล์ต้องไม่เกิน 2 MB';
            } else {
                $ext      = $extMap[$mime];
                $destDir  = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $filename = 'avatar_' . $employeeId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destPath = $destDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    $errors[] = 'ไม่สามารถบันทึกไฟล์ได้';
                } else {
                    // Delete old avatar
                    $pdo  = getDB();
                    $oldStmt = $pdo->prepare("SELECT avatar_url FROM dbo.employees WHERE employee_id = ?");
                    $oldStmt->execute([$employeeId]);
                    $oldUrl = $oldStmt->fetchColumn();
                    if ($oldUrl) {
                        $oldFile = $destDir . basename($oldUrl);
                        if (is_file($oldFile)) @unlink($oldFile);
                    }
                    $pdo->prepare("UPDATE dbo.employees SET avatar_url = ? WHERE employee_id = ?")
                        ->execute([$filename, $employeeId]);
                    $_SESSION['avatar_url'] = $filename;
                    setFlash('success', 'เปลี่ยนรูปโปรไฟล์สำเร็จ');
                    redirect(BASE_URL . '/pages/profile.php');
                }
            }
        }
        if (!empty($errors)) {
            setFlash('error', implode(' / ', $errors));
            redirect(BASE_URL . '/pages/profile.php');
        }

    // ── delete avatar ────────────────────────────────────────
    } elseif ($action === 'delete_avatar') {
        $pdo     = getDB();
        $oldStmt = $pdo->prepare("SELECT avatar_url FROM dbo.employees WHERE employee_id = ?");
        $oldStmt->execute([$employeeId]);
        $oldUrl  = $oldStmt->fetchColumn();
        if ($oldUrl) {
            $destDir = __DIR__ . '/../uploads/avatars/';
            $oldFile = $destDir . basename($oldUrl);
            if (is_file($oldFile)) @unlink($oldFile);
            $pdo->prepare("UPDATE dbo.employees SET avatar_url = NULL WHERE employee_id = ?")
                ->execute([$employeeId]);
            $_SESSION['avatar_url'] = '';
        }
        setFlash('success', 'ลบรูปโปรไฟล์แล้ว');
        redirect(BASE_URL . '/pages/profile.php');

    // ── change password ──────────────────────────────────────
    } else {
        $currentPw  = (string)($_POST['current_password']  ?? '');
        $newPw      = (string)($_POST['new_password']       ?? '');
        $confirmPw  = (string)($_POST['confirm_password']   ?? '');

        $errors = [];

        if ($currentPw === '') $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
        if (strlen($newPw) < 8) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
        if ($newPw !== $confirmPw) $errors[] = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';

        if (empty($errors)) {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT password_hash FROM dbo.employees WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            $row  = $stmt->fetch();

            if (!$row || !password_verify($currentPw, $row['password_hash'])) {
                $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } else {
                $newHash = password_hash($newPw, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE dbo.employees SET password_hash = ? WHERE employee_id = ?")
                    ->execute([$newHash, $employeeId]);
                setFlash('success', 'เปลี่ยนรหัสผ่านสำเร็จ');
                redirect(BASE_URL . '/pages/profile.php');
            }
        }

        if (!empty($errors)) {
            setFlash('error', implode(' / ', $errors));
            redirect(BASE_URL . '/pages/profile.php');
        }
    }
}

// ── GET: load profile ────────────────────────────────────────
$profile     = getEmployeeProfile($employeeId);
$tenure      = $profile ? getWorkTenure($profile['start_date'] ?? null) : null;
$wallet      = getWalletInfo($employeeId);
$flash       = getFlash();
$stravaRow   = getStravaTokenRow($employeeId);
$stravaOk    = !empty($stravaRow['strava_athlete_id']);

// Generate OAuth state for connect button
$stravaState = bin2hex(random_bytes(16));
$_SESSION['strava_oauth_state'] = $stravaState;
$stravaAuthUrl = stravaAuthURL($stravaState);

// Token stats this month
$monthlyEarned = 0;
try {
    $pdo = getDB();
    $mStmt = $pdo->prepare("
        SELECT ISNULL(SUM(token_awarded), 0) AS monthly
        FROM   dbo.challenge_submissions
        WHERE  employee_id = ?
          AND  status IN ('approved', 'auto_approved')
          AND  MONTH(submitted_at) = MONTH(GETDATE())
          AND  YEAR(submitted_at)  = YEAR(GETDATE())
    ");
    $mStmt->execute([$employeeId]);
    $monthlyEarned = (int)($mStmt->fetch()['monthly'] ?? 0);
} catch (Throwable $ignored) {}

// Completed challenges count
$completedCount = 0;
try {
    $cStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT challenge_id) AS cnt
        FROM   dbo.challenge_submissions
        WHERE  employee_id = ?
          AND  status IN ('approved', 'auto_approved')
    ");
    $cStmt->execute([$employeeId]);
    $completedCount = (int)($cStmt->fetch()['cnt'] ?? 0);
} catch (Throwable $ignored) {}

$pageTitle  = 'โปรไฟล์ของฉัน';
$activePage = 'profile';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pf-profile-wrap">

    <!-- Aurora blobs (shared with other dark pages) -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="pf-inner">

        <!-- ═══════════════════════════════════════
             HERO — Avatar + name + role badge
        ═══════════════════════════════════════ -->
        <div class="pf-hero">
            <div class="pf-avatar-wrap">
                <div class="pf-avatar">
                    <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?= uploadImgUrl('avatars', $profile['avatar_url']) ?>"
                         alt="<?= e($profile['full_name']) ?>" class="pf-avatar-img">
                    <?php else: ?>
                    <?= mb_substr($profile['full_name'] ?? 'U', 0, 1, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/pages/profile.php"
                        enctype="multipart/form-data" id="avatar-upload-form" class="pf-form-reset">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="upload_avatar">
                    <input class="pf-avatar-file-input" type="file" name="avatar" id="avatar-file"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                          
                           data-submit-on-change="avatar-upload-form">
                    <button type="button" class="pf-avatar-upload-btn" title="เปลี่ยนรูปโปรไฟล์" aria-label="เปลี่ยนรูปโปรไฟล์" data-avatar-pick="avatar-file">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </form>
                <?php if (!empty($profile['avatar_url'])): ?>
                    <form method="POST" action="<?= BASE_URL ?>/pages/profile.php" class="pf-form-reset"
                      data-confirm="ลบรูปโปรไฟล์?">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="delete_avatar">
                    <button type="submit" class="pf-avatar-delete-btn" title="ลบรูปโปรไฟล์" aria-label="ลบรูปโปรไฟล์">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="12" height="12">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="pf-hero-info">
                <h1 class="pf-name"><?= e($profile['full_name'] ?? '') ?></h1>
                <p class="pf-sub"><?= e($profile['position'] ?? '') ?></p>
                <p class="pf-code"><?= e($profile['employee_code'] ?? '') ?></p>
            </div>
            <!-- Token balance pill -->
            <div class="pf-balance-pill">
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="pf-token-icon token-spin">
                <span class="pf-balance-num"><?= formatTokens((int)$wallet['balance']) ?></span>
                <span class="pf-balance-label">Token คงเหลือ</span>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             STATS ROW
        ═══════════════════════════════════════ -->
        <div class="pf-stats-grid">
            <?php
            $stats = [
                ['value' => formatTokens((int)$wallet['total_earned']), 'label' => 'Token สะสมทั้งหมด', 'cls' => 'pf-stat-gold'],
                ['value' => formatTokens((int)$wallet['total_spent']),  'label' => 'Token ที่ใช้ไป',    'cls' => 'pf-stat-orange'],
                ['value' => (string)$completedCount,                    'label' => 'ภารกิจสำเร็จ',       'cls' => 'pf-stat-teal'],
                ['value' => formatTokens($monthlyEarned),               'label' => 'Token เดือนนี้',     'cls' => 'pf-stat-green'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="pf-stat-card">
                <p class="pf-stat-value <?= e($s['cls']) ?>"><?= $s['value'] ?></p>
                <p class="pf-stat-label"><?= $s['label'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="pf-grid-2col">

            <!-- ═══════════════════════════════════════
                 LEFT COLUMN
            ═══════════════════════════════════════ -->
            <div class="pf-col">

                <!-- Tenure card -->
                <?php if ($tenure): ?>
                <div class="pf-card pf-tenure-card">
                    <p class="pf-card-label">อายุงาน</p>
                    <div class="pf-tenure-nums">
                        <?php if ($tenure['years'] > 0): ?>
                        <div class="pf-tenure-unit">
                            <span class="pf-tenure-big"><?= $tenure['years'] ?></span>
                            <span class="pf-tenure-unit-label">ปี</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($tenure['months'] > 0): ?>
                        <div class="pf-tenure-unit">
                            <span class="pf-tenure-mid"><?= $tenure['months'] ?></span>
                            <span class="pf-tenure-unit-label">เดือน</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($tenure['years'] === 0 && $tenure['months'] === 0 && $tenure['days'] > 0): ?>
                        <div class="pf-tenure-unit">
                            <span class="pf-tenure-mid"><?= $tenure['days'] ?></span>
                            <span class="pf-tenure-unit-label">วัน</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="pf-tenure-since">
                        <?php
                        $thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
                                       'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
                        $sd = strtotime($tenure['start_date']);
                        echo 'เริ่มงาน ' . date('d', $sd) . ' ' . $thaiMonths[(int)date('n', $sd)]
                           . ' ' . ((int)date('Y', $sd) + 543);
                        ?>
                        <span class="pf-tenure-days"><?= number_format($tenure['total_days']) ?> วันที่ทำงาน</span>
                    </p>
                    <?php
                    $_ms = [
                        ['y'=>1,  'label'=>'1 ปี',  'pct'=>10],
                        ['y'=>3,  'label'=>'3 ปี',  'pct'=>30],
                        ['y'=>5,  'label'=>'5 ปี',  'pct'=>50],
                        ['y'=>10, 'label'=>'10 ปี', 'pct'=>100],
                    ];
                    $_tyears = $tenure['total_days'] / 365.25;
                    $_fill = min(100, round($_tyears / 10 * 100, 2));
                    ?>
                    <div class="pf-tenure-milestones">
                        <div class="pf-tenure-track">
                            <div class="pf-tenure-fill" data-width="<?= $_fill ?>"></div>
                            <?php foreach ($_ms as $_m): ?>
                            <?php $_r = $_tyears >= $_m['y']; ?>
                            <div class="pf-tenure-marker" data-left="<?= $_m['pct'] ?>">
                                <div class="pf-tenure-marker-dot <?= $_r ? 'reached' : '' ?>"></div>
                                <div class="pf-tenure-marker-label <?= $_r ? 'reached' : '' ?>"><?= $_m['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="pf-card pf-tenure-card pf-tenure-empty">
                    ยังไม่มีข้อมูลวันเริ่มงานในระบบ
                </div>
                <?php endif; ?>

                <!-- Profile info (read-only) -->
                <div class="pf-card">
                    <p class="pf-card-label">ข้อมูลส่วนตัว</p>
                    <div class="pf-info-grid">
                        <div class="pf-info-item">
                            <span class="pf-info-key">ชื่อ-นามสกุล</span>
                            <span class="pf-info-val"><?= e($profile['full_name'] ?? '-') ?></span>
                        </div>
                        <div class="pf-info-item">
                            <span class="pf-info-key">รหัสพนักงาน</span>
                            <span class="pf-info-val pf-mono"><?= e($profile['employee_code'] ?? '-') ?></span>
                        </div>
                        <div class="pf-info-item">
                            <span class="pf-info-key">ตำแหน่ง</span>
                            <span class="pf-info-val"><?= e($profile['position'] ?? '-') ?></span>
                        </div>
                        <div class="pf-info-item pf-info-item--full">
                            <span class="pf-info-key">อีเมล</span>
                            <span class="pf-info-val"><?= e($profile['email'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

            </div><!-- /pf-col left -->

            <!-- ═══════════════════════════════════════
                 RIGHT COLUMN — Change Password
            ═══════════════════════════════════════ -->
            <div class="pf-col">
                <div class="pf-card">
                    <p class="pf-card-label">เปลี่ยนรหัสผ่าน</p>
                    <form method="POST" action="<?= BASE_URL ?>/pages/profile.php" id="pw-form">
                        <?= csrfField() ?>
                        <div class="pf-form-fields">

                            <div class="pf-field">
                                <label for="current_password" class="pf-field-label">รหัสผ่านปัจจุบัน</label>
                                <div class="pf-input-wrap">
                                    <input type="password" id="current_password" name="current_password"
                                           autocomplete="current-password" class="pf-input" required>
                                    <button type="button" class="pf-eye-btn"
                                            data-toggle-pw="current_password"
                                            aria-label="แสดง/ซ่อนรหัสผ่าน">
                                        <svg class="pf-eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="pf-field">
                                <label for="new_password" class="pf-field-label">
                                    รหัสผ่านใหม่
                                    <span class="pf-field-hint">(อย่างน้อย 8 ตัวอักษร)</span>
                                </label>
                                <div class="pf-input-wrap">
                                    <input type="password" id="new_password" name="new_password"
                                           autocomplete="new-password" minlength="8"
                                           class="pf-input" required>
                                    <button type="button" class="pf-eye-btn"
                                            data-toggle-pw="new_password"
                                            aria-label="แสดง/ซ่อนรหัสผ่าน">
                                        <svg class="pf-eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="pf-field">
                                <label for="confirm_password" class="pf-field-label">ยืนยันรหัสผ่านใหม่</label>
                                <div class="pf-input-wrap">
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           autocomplete="new-password" class="pf-input" required>
                                    <button type="button" class="pf-eye-btn"
                                            data-toggle-pw="confirm_password"
                                            aria-label="แสดง/ซ่อนรหัสผ่าน">
                                        <svg class="pf-eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                                <p id="pw-match-hint" class="pf-match-hint" aria-live="polite"></p>
                            </div>

                        </div><!-- /pf-form-fields -->

                        <div class="pf-form-actions">
                            <button type="submit" class="btn-gold">บันทึกรหัสผ่าน</button>
                            <button type="reset" class="btn-outline pf-u002">ล้างฟอร์ม</button>
                        </div>
                    </form>
                </div>
                <!-- Strava Connect card -->
                <div class="pf-card <?= $stravaOk ? 'pf-card-strava-on' : 'pf-card-strava-off' ?>">
                    <div class="pf-u003">
                        <!-- Strava logo SVG -->
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="#FC4C02" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                        </svg>
                        <p class="pf-card-label pf-u004">เชื่อมต่อ Strava</p>
                        <?php if ($stravaOk): ?>
                        <span class="pf-u005">&#10003; เชื่อมต่อแล้ว</span>
                        <?php else: ?>
                        <span class="pf-u006">ยังไม่เชื่อมต่อ</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($stravaOk): ?>
                    <!-- Connected status -->
                    <div class="pf-u007">
                        <div class="pf-u008">
                            <p class="pf-u009">Athlete ID</p>
                            <p class="pf-u010">
                                <?= e((string)$stravaRow['strava_athlete_id']) ?>
                            </p>
                        </div>
                        <div class="pf-u008">
                            <p class="pf-u009">สิทธิ์ที่อนุญาต</p>
                            <p class="pf-u011">
                                <?= e($stravaRow['strava_scope'] ?? '-') ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($stravaRow['strava_token_expires_at'])): ?>
                    <p class="pf-u012">
                        Token หมดอายุ:
                        <span class="pf-u013">
                        <?php
                        $exp = (int)$stravaRow['strava_token_expires_at'];
                        $diff = $exp - time();
                        if ($diff <= 0) echo 'หมดอายุแล้ว (จะต่ออายุอัตโนมัติ)';
                        elseif ($diff < 3600) echo 'อีก ' . round($diff/60) . ' นาที';
                        else echo 'อีก ' . round($diff/3600, 1) . ' ชั่วโมง';
                        ?>
                        </span>
                    </p>
                    <?php endif; ?>
                    <!-- Disconnect button -->
                    <form method="POST" action="<?= BASE_URL ?>/pages/strava_connect.php"
                          data-confirm="ยืนยันการยกเลิกเชื่อมต่อ Strava?">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="disconnect">
                        <button class="pf-u014" type="submit">
                            ยกเลิกเชื่อมต่อ Strava
                        </button>
                    </form>

                    <?php else: ?>
                    <!-- Connect button -->
                    <p class="pf-u015">
                        เชื่อมต่อ Strava เพื่อทำภารกิจ Activity Tracking อัตโนมัติ<br>
                        <span class="pf-u016">ต้องการสิทธิ์อ่าน activity เท่านั้น</span>
                    </p>
                          <a href="<?= e($stravaAuthUrl) ?>" class="pf-strava-connect-btn">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                        </svg>
                        เชื่อมต่อกับ Strava
                    </a>
                    <?php endif; ?>

                </div><!-- /strava card -->

            </div><!-- /pf-col right -->

        </div><!-- /pf-grid-2col -->

    </div><!-- /pf-inner -->

</div><!-- /pf-profile-wrap -->

<script>
document.querySelectorAll('.pf-tenure-fill[data-width]').forEach(function (el) {
    el.style.transform = 'scaleX(' + ((parseFloat(el.dataset.width) || 0) / 100) + ')';
});
document.querySelectorAll('.pf-tenure-marker[data-left]').forEach(function (el) {
    el.style.left = (el.dataset.left || '0') + '%';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>