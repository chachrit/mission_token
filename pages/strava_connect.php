<?php
/**
 * pages/strava_connect.php
 * Employee Strava OAuth connect / disconnect page
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/strava.php';
require_once __DIR__ . '/../config/strava.php';

$employeeId = (int)$_SESSION['employee_id'];

// ── POST: disconnect ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if (($_POST['action'] ?? '') === 'disconnect') {
        disconnectStrava($employeeId);
        setFlash('success', 'ยกเลิกการเชื่อมต่อ Strava แล้ว');
    }
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── GET: build auth URL with CSRF state ──────────────────────
$state = bin2hex(random_bytes(16));
$_SESSION['strava_oauth_state'] = $state;
$authURL = stravaAuthURL($state);

// ── Load connection status ────────────────────────────────────
$connected  = false;
$tokenRow   = null;
$athleteRow = null;

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT strava_athlete_id, strava_token_expires_at, strava_scope
    FROM   employees WHERE employee_id = ?
");
$stmt->execute([$employeeId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && !empty($row['strava_athlete_id'])) {
    $connected  = true;
    $tokenRow   = $row;
}

$flash      = getFlash();
$pageTitle  = 'เชื่อมต่อ Strava';
$activePage = 'strava_connect';

require_once __DIR__ . '/../includes/header.php';
?>
<div style="max-width:680px; margin:0 auto; padding:2rem 1rem;">

    <!-- Card -->
    <div style="background:#0d1618; border:1px solid #2a3038; border-radius:16px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#1a1f20,#0d1618); padding:1.5rem 1.75rem;
                    border-bottom:1px solid #2a3038; display:flex; align-items:center; gap:1rem;">
            <div style="width:48px; height:48px; background:#FC4C02; border-radius:12px;
                        display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="#fff" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <h1 style="font-size:1.15rem; font-weight:700; color:#eeebe1; margin:0;">เชื่อมต่อ Strava</h1>
                <p style="font-size:0.78rem; color:#6b6e77; margin:0.2rem 0 0;">ระบบจะดึงกิจกรรมออกกำลังกายมาตรวจสอบเงื่อนไขภารกิจอัตโนมัติ</p>
            </div>
        </div>

        <div style="padding:1.75rem;">

            <?php if ($connected): ?>
            <!-- ── CONNECTED STATE ── -->
            <div style="background:rgba(81,142,92,0.1); border:1px solid rgba(81,142,92,0.3);
                        border-radius:12px; padding:1.1rem 1.25rem; margin-bottom:1.5rem;
                        display:flex; align-items:center; gap:0.75rem;">
                <div style="width:10px; height:10px; background:#518e5c; border-radius:50%; flex-shrink:0;
                             box-shadow:0 0 8px rgba(81,142,92,0.7);"></div>
                <div>
                    <p style="font-size:0.85rem; font-weight:600; color:#518e5c; margin:0;">เชื่อมต่อแล้ว</p>
                    <p style="font-size:0.75rem; color:#6b6e77; margin:0.15rem 0 0;">
                        Athlete ID: <?= e((string)$tokenRow['strava_athlete_id']) ?>
                        &bull; Scope: <?= e((string)$tokenRow['strava_scope']) ?>
                    </p>
                    <?php
                    $exp = (int)$tokenRow['strava_token_expires_at'];
                    $diff = $exp - time();
                    $expStr = $diff > 0
                        ? 'Token หมดอายุใน ' . round($diff/3600, 1) . ' ชั่วโมง'
                        : 'Token หมดอายุแล้ว (จะ refresh อัตโนมัติเมื่อตรวจสอบ)';
                    ?>
                    <p style="font-size:0.72rem; color:#4f8b98; margin:0.15rem 0 0;"><?= e($expStr) ?></p>
                </div>
            </div>

            <!-- Disconnect button -->
            <form method="POST" action="<?= BASE_URL ?>/pages/strava_connect.php"
                  data-confirm="ยืนยันการยกเลิกเชื่อมต่อ Strava?">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="disconnect">
                <button type="submit" style="
                    background:rgba(210,89,42,0.15); border:1px solid rgba(210,89,42,0.4);
                    color:#d2592a; padding:0.55rem 1.1rem; border-radius:8px;
                    font-family:'Prompt',sans-serif; font-size:0.85rem; cursor:pointer;
                    transition:background 0.2s;">
                    ยกเลิกการเชื่อมต่อ
                </button>
            </form>

            <?php else: ?>
            <!-- ── NOT CONNECTED STATE ── -->
            <p style="font-size:0.85rem; color:#9ca3af; margin:0 0 1.5rem; line-height:1.6;">
                กด "เชื่อมต่อกับ Strava" เพื่อ authorize ให้ระบบ JOURNAL ดึงข้อมูลกิจกรรมของคุณมาตรวจสอบภารกิจ<br>
                ระบบจะเห็นเฉพาะ <strong style="color:#eeebe1;">กิจกรรมที่ตั้งค่าเป็น Everyone / Followers</strong> เท่านั้น
            </p>
            <a href="<?= e($authURL) ?>"
               class="sc-connect-btn"
               style="display:inline-flex; align-items:center; gap:0.6rem;
                      background:#FC4C02; color:#fff; padding:0.7rem 1.4rem;
                      border-radius:10px; font-size:0.9rem; font-weight:600;
                      font-family:'Prompt',sans-serif; text-decoration:none;
                      transition:background 0.2s;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                เชื่อมต่อกับ Strava
            </a>
            <?php endif; ?>

            <!-- Divider -->
            <hr style="border:none; border-top:1px solid #2a3038; margin:1.75rem 0;">

            <!-- How it works -->
            <p style="font-size:0.78rem; font-weight:600; color:#6b6e77; letter-spacing:0.06em; margin:0 0 0.85rem; text-transform:uppercase;">
                วิธีการทำงาน
            </p>
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                <?php
                $steps = [
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 1 0 7 7l1-1"/></svg>', 'เชื่อมต่อบัญชี Strava ของคุณ (ทำครั้งเดียว)'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="14" cy="5" r="2"/><path d="M7 10l4-2 3 1 2 3"/><path d="M10 13l-2 4"/><path d="M13 13l4 5"/></svg>', 'ออกกำลังกายและบันทึกใน Strava ตามปกติ'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.6 2.6L16 9.8"/></svg>', 'กด "ตรวจสอบกิจกรรม" ในหน้าภารกิจ'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5"/><path d="M12 12l3 2"/></svg>', 'ระบบดึงกิจกรรมมาเช็คเงื่อนไข — ผ่านก็ได้ Token ทันที'],
                ];
                foreach ($steps as [$icon, $text]):
                ?>
                <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                    <span style="font-size:1rem; flex-shrink:0; display:inline-flex; align-items:center;"><?= $icon ?></span>
                    <span style="font-size:0.82rem; color:#9ca3af; line-height:1.5;"><?= $text ?></span>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- Back link -->
    <div style="margin-top:1.25rem; text-align:center;">
        <a href="<?= BASE_URL ?>/pages/challenges.php"
           class="sc-back-link"
           style="font-size:0.82rem; color:#6b6e77; text-decoration:none;">
            ← กลับไปหน้าภารกิจ
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
