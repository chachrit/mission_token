<?php
/**
 * pages/profile.php
 * Employee profile — view info, work tenure, change password
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$employeeId = (int)$_SESSION['employee_id'];

// ── POST: change password ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

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
        FROM   challenge_submissions
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
        FROM   challenge_submissions
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

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- Profile hero card -->
    <div class="rounded-2xl mb-6" style="background:#091113;">
        <!-- Top banner -->
        <!-- <div class="h-20 rounded-t-2xl relative overflow-hidden" style="background:linear-gradient(135deg,#1a1f20 0%,#2a2310 60%,#3a3010 100%);">
            <div class="absolute inset-0 opacity-20"
                 style="background-image:radial-gradient(circle at 30% 50%, #dab937 1px, transparent 1px);
                        background-size:28px 28px;"></div>
        </div> -->
        <!-- Avatar + info -->
        <div class="px-6 pb-6 -mt-10 flex items-end gap-5 flex-wrap">
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-3xl font-bold flex-shrink-0 ring-4 ring-[#091113]"
                 style="background:#dab937; color:#091113;">
                <?= mb_substr($profile['full_name'] ?? 'U', 0, 1, 'UTF-8') ?>
            </div>
            <div class="pb-1 flex-1 min-w-0">
                <h1 class="text-xl font-bold" style="color:#fdfcdf;"><?= e($profile['full_name'] ?? '') ?></h1>
                <p class="text-sm mt-0.5" style="color:#6b6e77;">
                    <?= e($profile['position'] ?? '') ?>
                    <?php if ($profile['department'] ?? ''): ?>
                    <span class="mx-1.5" style="color:#3a3e43;">·</span>
                    <?= e($profile['department']) ?>
                    <?php endif; ?>
                </p>
                <p class="text-xs mt-1 font-mono" style="color:#dab937;"><?= e($profile['employee_code'] ?? '') ?></p>
            </div>
            <!-- Token balance pill -->
            <div class="flex items-center gap-2 px-4 py-2 rounded-full mb-1"
                 style="background:#1a1f20; border:1px solid #3a3e43;">
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" style="width:18px;height:18px;object-fit:contain;">
                <span class="text-sm font-bold" style="color:#dab937;"><?= formatTokens((int)$wallet['balance']) ?></span>
                <span class="text-xs" style="color:#6b6e77;">Token</span>
            </div>
        </div>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="journal-card p-4 text-center">
            <p class="text-2xl font-bold text-j-gold"><?= formatTokens((int)$wallet['total_earned']) ?></p>
            <p class="text-xs text-j-slate mt-0.5">Token สะสมทั้งหมด</p>
        </div>
        <div class="journal-card p-4 text-center">
            <p class="text-2xl font-bold text-j-dark"><?= $completedCount ?></p>
            <p class="text-xs text-j-slate mt-0.5">ภารกิจสำเร็จ</p>
        </div>
        <div class="journal-card p-4 text-center">
            <p class="text-2xl font-bold" style="color:#518e5c;"><?= formatTokens($monthlyEarned) ?></p>
            <p class="text-xs text-j-slate mt-0.5">Token เดือนนี้</p>
        </div>
    </div>

    <!-- Tenure card -->
    <?php if ($tenure): ?>
    <div class="rounded-2xl p-6 mb-6 relative overflow-hidden"
         style="background:linear-gradient(135deg,#fdfcdf 0%,#faf0cf 100%); border:1px solid #dab937;">
        <div class="absolute right-4 top-4 opacity-10" style="font-size:80px; line-height:1;">🏅</div>
        <p class="text-xs font-semibold uppercase tracking-widest text-j-slate mb-3">อายุงาน</p>
        <div class="flex items-end gap-3 flex-wrap">
            <?php if ($tenure['years'] > 0): ?>
            <div class="flex items-end gap-1">
                <span class="text-5xl font-bold text-j-dark leading-none"><?= $tenure['years'] ?></span>
                <span class="text-lg font-medium text-j-slate pb-1">ปี</span>
            </div>
            <?php endif; ?>
            <?php if ($tenure['months'] > 0): ?>
            <div class="flex items-end gap-1">
                <span class="text-4xl font-bold text-j-charcoal leading-none"><?= $tenure['months'] ?></span>
                <span class="text-base font-medium text-j-slate pb-1">เดือน</span>
            </div>
            <?php endif; ?>
            <?php if ($tenure['years'] === 0 && $tenure['months'] === 0 && $tenure['days'] > 0): ?>
            <div class="flex items-end gap-1">
                <span class="text-4xl font-bold text-j-charcoal leading-none"><?= $tenure['days'] ?></span>
                <span class="text-base font-medium text-j-slate pb-1">วัน</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-sm text-j-slate">
                เริ่มงานวันที่
                <?= date('d', strtotime($tenure['start_date'])) ?>
                <?php
                $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                echo $thaiMonths[(int)date('n', strtotime($tenure['start_date']))];
                ?>
                <?= (int)date('Y', strtotime($tenure['start_date'])) + 543 ?>
            </span>
            <span class="text-j-silver">·</span>
            <span class="text-sm font-medium text-j-gold-dk"><?= number_format($tenure['total_days']) ?> วันที่ทำงาน</span>
        </div>
    </div>
    <?php else: ?>
    <div class="rounded-2xl p-5 mb-6 text-sm text-j-slate border border-dashed border-j-silver text-center">
        ยังไม่มีข้อมูลวันเริ่มงานในระบบ
    </div>
    <?php endif; ?>

    <!-- Profile info (read-only, synced from HR API) -->
    <div class="journal-card p-6 mb-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-j-dark">ข้อมูลส่วนตัว</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-medium text-j-slate mb-1">ชื่อ-นามสกุล</p>
                <p class="text-sm font-medium text-j-dark"><?= e($profile['full_name'] ?? '-') ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-j-slate mb-1">รหัสพนักงาน</p>
                <p class="text-sm font-mono text-j-dark"><?= e($profile['employee_code'] ?? '-') ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-j-slate mb-1">แผนก</p>
                <p class="text-sm text-j-dark"><?= e($profile['department'] ?? '-') ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-j-slate mb-1">ตำแหน่ง</p>
                <p class="text-sm text-j-dark"><?= e($profile['position'] ?? '-') ?></p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs font-medium text-j-slate mb-1">อีเมล</p>
                <p class="text-sm text-j-dark"><?= e($profile['email'] ?? '-') ?></p>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="journal-card p-6">
        <h2 class="text-base font-semibold text-j-dark mb-5">เปลี่ยนรหัสผ่าน</h2>
        <form method="POST" action="<?= BASE_URL ?>/pages/profile.php" id="pw-form">
            <?= csrfField() ?>
            <div class="grid gap-4">
                <div>
                    <label for="current_password" class="block text-xs font-medium text-j-slate mb-1.5">
                        รหัสผ่านปัจจุบัน
                    </label>
                    <div class="relative">
                        <input type="password" id="current_password" name="current_password"
                               autocomplete="current-password"
                               class="journal-input pr-10" required>
                        <button type="button" onclick="togglePw('current_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-j-slate hover:text-j-dark transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="new_password" class="block text-xs font-medium text-j-slate mb-1.5">
                        รหัสผ่านใหม่ <span class="text-j-slate font-normal">(อย่างน้อย 8 ตัวอักษร)</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password"
                               autocomplete="new-password" minlength="8"
                               class="journal-input pr-10" required>
                        <button type="button" onclick="togglePw('new_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-j-slate hover:text-j-dark transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirm_password" class="block text-xs font-medium text-j-slate mb-1.5">
                        ยืนยันรหัสผ่านใหม่
                    </label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password"
                               autocomplete="new-password"
                               class="journal-input pr-10" required>
                        <button type="button" onclick="togglePw('confirm_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-j-slate hover:text-j-dark transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p id="pw-match-hint" class="mt-1.5 text-xs hidden"></p>
                </div>
            </div>
            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="btn-gold">บันทึกรหัสผ่าน</button>
                <button type="reset" class="btn-outline">ล้างฟอร์ม</button>
            </div>
        </form>
    </div>

</div>

<script>
function togglePw(fieldId) {
    var el = document.getElementById(fieldId);
    el.type = el.type === 'password' ? 'text' : 'password';
}

// Live confirm-password match hint
(function () {
    var newPw    = document.getElementById('new_password');
    var confPw   = document.getElementById('confirm_password');
    var hint     = document.getElementById('pw-match-hint');

    function checkMatch() {
        if (!confPw.value) { hint.classList.add('hidden'); return; }
        if (newPw.value === confPw.value) {
            hint.textContent = '✓ รหัสผ่านตรงกัน';
            hint.style.color = '#518e5c';
        } else {
            hint.textContent = '✗ รหัสผ่านไม่ตรงกัน';
            hint.style.color = '#d2592a';
        }
        hint.classList.remove('hidden');
    }

    newPw.addEventListener('input', checkMatch);
    confPw.addEventListener('input', checkMatch);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
