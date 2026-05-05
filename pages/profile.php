<?php
/**
 * pages/profile.php
 * Employee profile — view info, work tenure, change password
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

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
$profile  = getEmployeeProfile($employeeId);
$tenure   = $profile ? getWorkTenure($profile['start_date'] ?? null) : null;
$wallet   = getWalletInfo($employeeId);
$flash    = getFlash();

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

        <!-- Flash message -->
        <?php if ($flash): ?>
        <div class="pf-flash pf-flash--<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════
             HERO — Avatar + name + role badge
        ═══════════════════════════════════════ -->
        <div class="pf-hero">
            <div class="pf-avatar-wrap">
                <div class="pf-avatar">
                    <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= rawurlencode(basename($profile['avatar_url'])) ?>"
                         alt="<?= e($profile['full_name']) ?>" class="pf-avatar-img">
                    <?php else: ?>
                    <?= mb_substr($profile['full_name'] ?? 'U', 0, 1, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/pages/profile.php"
                      enctype="multipart/form-data" id="avatar-upload-form" style="margin:0">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="upload_avatar">
                    <input type="file" name="avatar" id="avatar-file"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           style="display:none"
                           onchange="document.getElementById('avatar-upload-form').submit()">
                    <label for="avatar-file" class="pf-avatar-upload-btn" title="เปลี่ยนรูปโปรไฟล์">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </label>
                </form>
                <?php if (!empty($profile['avatar_url'])): ?>
                <form method="POST" action="<?= BASE_URL ?>/pages/profile.php" style="margin:0"
                      onsubmit="return confirm('ลบรูปโปรไฟล์?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="delete_avatar">
                    <button type="submit" class="pf-avatar-delete-btn" title="ลบรูปโปรไฟล์">
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
                <p class="pf-sub">
                    <?= e($profile['position'] ?? '') ?>
                    <?php if ($profile['department'] ?? ''): ?>
                    <span class="pf-dot">·</span><?= e($profile['department']) ?>
                    <?php endif; ?>
                </p>
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
                ['value' => formatTokens((int)$wallet['total_earned']), 'label' => 'Token สะสมทั้งหมด', 'color' => '#dab937'],
                ['value' => formatTokens((int)$wallet['total_spent']),  'label' => 'Token ที่ใช้ไป',    'color' => '#d2592a'],
                ['value' => (string)$completedCount,                    'label' => 'ภารกิจสำเร็จ',       'color' => '#4f8b98'],
                ['value' => formatTokens($monthlyEarned),               'label' => 'Token เดือนนี้',     'color' => '#518e5c'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="pf-stat-card">
                <p class="pf-stat-value" style="color:<?= $s['color'] ?>;"><?= $s['value'] ?></p>
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
                            <div class="pf-tenure-fill" style="width:<?= $_fill ?>%;"></div>
                            <?php foreach ($_ms as $_m): ?>
                            <?php $_r = $_tyears >= $_m['y']; ?>
                            <div class="pf-tenure-marker" style="left:<?= $_m['pct'] ?>%;">
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
                            <span class="pf-info-key">แผนก</span>
                            <span class="pf-info-val"><?= e($profile['department'] ?? '-') ?></span>
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
                                            onclick="profileTogglePw('current_password')"
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
                                            onclick="profileTogglePw('new_password')"
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
                                            onclick="profileTogglePw('confirm_password')"
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
                            <button type="reset" class="btn-outline" style="border-color:rgba(255,255,255,0.15); color:#9ca3af;">ล้างฟอร์ม</button>
                        </div>
                    </form>
                </div>
            </div><!-- /pf-col right -->

        </div><!-- /pf-grid-2col -->
    </div><!-- /pf-inner -->
</div><!-- /pf-profile-wrap -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

