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
<div class="sc-u001">

    <!-- Card -->
    <div class="sc-u002">

        <!-- Header -->
        <div class="sc-u003">
            <div class="sc-u004">
                <svg width="26" height="26" fill="none" stroke="#fff" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <h1 class="sc-u005">เชื่อมต่อ Strava</h1>
                <p class="sc-u006">ระบบจะดึงกิจกรรมออกกำลังกายมาตรวจสอบเงื่อนไขภารกิจอัตโนมัติ</p>
            </div>
        </div>

        <div class="sc-u007">

            <?php if ($connected): ?>
            <!-- ── CONNECTED STATE ── -->
            <div class="sc-u008">
                <div class="sc-u009"></div>
                <div>
                    <p class="sc-u010">เชื่อมต่อแล้ว</p>
                    <p class="sc-u011">
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
                    <p class="sc-u012"><?= e($expStr) ?></p>
                </div>
            </div>

            <!-- Disconnect button -->
            <form method="POST" action="<?= BASE_URL ?>/pages/strava_connect.php"
                  data-confirm="ยืนยันการยกเลิกเชื่อมต่อ Strava?">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="disconnect">
                <button class="sc-u013" type="submit">
                    ยกเลิกการเชื่อมต่อ
                </button>
            </form>

            <?php else: ?>
            <!-- ── NOT CONNECTED STATE ── -->
            <p class="sc-u014">
                กด "เชื่อมต่อกับ Strava" เพื่อ authorize ให้ระบบ JOURNAL ดึงข้อมูลกิจกรรมของคุณมาตรวจสอบภารกิจ<br>
                ระบบจะเห็นเฉพาะ <strong class="sc-u015">กิจกรรมที่ตั้งค่าเป็น Everyone / Followers</strong> เท่านั้น
            </p>
            <a href="<?= e($authURL) ?>"
                    class="sc-connect-btn sc-connect-inline">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                เชื่อมต่อกับ Strava
            </a>
            <?php endif; ?>

            <!-- Divider -->
            <hr class="sc-u016">

            <!-- How it works -->
            <p class="sc-u017">
                วิธีการทำงาน
            </p>
            <div class="sc-u018">
                <?php
                $steps = [
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 1 0 7 7l1-1"/></svg>', 'เชื่อมต่อบัญชี Strava ของคุณ (ทำครั้งเดียว)'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="14" cy="5" r="2"/><path d="M7 10l4-2 3 1 2 3"/><path d="M10 13l-2 4"/><path d="M13 13l4 5"/></svg>', 'ออกกำลังกายและบันทึกใน Strava ตามปกติ'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.6 2.6L16 9.8"/></svg>', 'กด "ตรวจสอบกิจกรรม" ในหน้าภารกิจ'],
                    ['<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5"/><path d="M12 12l3 2"/></svg>', 'ระบบดึงกิจกรรมมาเช็คเงื่อนไข — ผ่านก็ได้ Token ทันที'],
                ];
                foreach ($steps as [$icon, $text]):
                ?>
                <div class="sc-u019">
                    <span class="sc-u020"><?= $icon ?></span>
                    <span class="sc-u021"><?= $text ?></span>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- Back link -->
    <div class="sc-u022">
        <a href="<?= BASE_URL ?>/pages/challenges.php"
              class="sc-back-link sc-back-link-muted">
            ← กลับไปหน้าภารกิจ
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
