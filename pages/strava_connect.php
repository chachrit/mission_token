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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if (($_POST['action'] ?? '') === 'disconnect') {
        disconnectStrava($employeeId);
        setFlash('success', 'ยกเลิกการเชื่อมต่อ Strava แล้ว');
    } elseif (($_POST['action'] ?? '') === 'delete_local_strava_data') {
        try {
            deleteLocalStravaDataForEmployee($employeeId);
            setFlash('success', 'ลบข้อมูล Strava เรียบร้อยแล้ว');
        } catch (Throwable $e) {
            error_log('[Strava] local deletion failed for employee_id=' . $employeeId);
            setFlash('error', 'ลบข้อมูลไม่สำเร็จ กรุณาลองใหม่');
        }
    }
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Build auth URL with CSRF state ─────────────────────────
$state = bin2hex(random_bytes(16));
$_SESSION['strava_oauth_state'] = $state;
$authURL = stravaAuthURL($state);

// ── Load connection status ────────────────────────────────────
$connected = false;
$tokenRow  = null;

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT strava_athlete_id, strava_token_expires_at, strava_scope
    FROM   employees WHERE employee_id = ?
");
$stmt->execute([$employeeId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && !empty($row['strava_athlete_id'])) {
    $connected = true;
    $tokenRow  = $row;
}

$flash      = getFlash();
$pageTitle  = 'เชื่อมต่อ Strava';
$activePage = 'strava_connect';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="scp-wrap">

    <!-- Aurora blobs -->
    <div class="scp-blob scp-blob-1" aria-hidden="true"></div>
    <div class="scp-blob scp-blob-2" aria-hidden="true"></div>

    <div class="scp-inner">

        <?php if ($flash): ?>
        <div class="scp-flash scp-flash--<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Main card -->
        <div class="scp-card">

            <!-- Card header -->
            <div class="scp-head">
                <div class="scp-icon">
                    <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="scp-title">เชื่อมต่อ Strava</h1>
                    <p class="scp-subtitle">ตรวจภารกิจกีฬาอัตโนมัติจากข้อมูล Strava</p>
                </div>
                <?php if ($connected): ?>
                <span class="scp-badge-connected">เชื่อมต่อแล้ว</span>
                <?php endif; ?>
            </div>

            <div class="scp-body">

                <?php if ($connected): ?>
                <!-- ── CONNECTED ── -->
                <div class="scp-athlete-panel">
                    <div class="scp-athlete-dot"></div>
                    <div>
                        <p class="scp-athlete-id">
                            Athlete #<?= e((string)$tokenRow['strava_athlete_id']) ?>
                        </p>
                        <p class="scp-athlete-scope">
                            Scope: <?= e((string)$tokenRow['strava_scope']) ?>
                        </p>
                        <?php
                        $exp  = (int)$tokenRow['strava_token_expires_at'];
                        $diff = $exp - time();
                        $expStr = $diff > 0
                            ? 'Token ใช้ได้อีก ' . round($diff / 3600, 1) . ' ชม.'
                            : 'Token หมดอายุ (จะ refresh อัตโนมัติ)';
                        ?>
                        <p class="scp-athlete-exp"><?= e($expStr) ?></p>
                    </div>
                </div>

                <form method="POST" class="scp-form"
                      data-confirm="ยืนยันการยกเลิกเชื่อมต่อ Strava?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="disconnect">
                    <button class="scp-btn-danger" type="submit">
                        <svg width="13" height="13" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        ยกเลิกการเชื่อมต่อ
                    </button>
                </form>

                <?php else: ?>
                <!-- ── NOT CONNECTED ── -->
                <p class="scp-intro">
                    กด Connect เพื่ออนุญาตให้ระบบดึงข้อมูลกิจกรรมตรวจภารกิจ
                </p>
                <p class="scp-note">
                    เห็นเฉพาะกิจกรรมที่ตั้งค่าเป็น Everyone / Followers เท่านั้น
                </p>
                <p class="scp-consent">
                    กด Connect = ยินยอมให้เก็บ Athlete ID, scope
                    และข้อมูลกิจกรรมที่ใช้ตรวจภารกิจเท่านั้น
                </p>
                <a href="<?= e($authURL) ?>" class="scp-btn-connect"
                   aria-label="Connect with Strava">
                    <svg width="15" height="15" fill="none" stroke="currentColor"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Connect with Strava
                </a>
                <p class="scp-privacy-hint">
                    <a href="<?= BASE_URL ?>/strava-privacy.php"
                       class="scp-link" target="_blank" rel="noopener">อ่านนโยบายข้อมูล Strava</a>
                </p>
                <?php endif; ?>

                <hr class="scp-divider">

                <!-- Data rights -->
                <p class="scp-section-label">สิทธิ์ข้อมูลของคุณ</p>
                <ul class="scp-rights">
                    <li>ถอนสิทธิ์ได้ทุกเมื่อผ่านปุ่มยกเลิกการเชื่อมต่อ</li>
                    <li>ขอลบข้อมูล Strava ในระบบนี้ได้จากปุ่มด้านล่าง</li>
                    <li>ติดต่อผู้ดูแล HR/IT เพื่อขอความช่วยเหลือเพิ่มเติม</li>
                    <li>
                        <a href="<?= BASE_URL ?>/strava-privacy.php"
                           class="scp-link" target="_blank" rel="noopener">อ่านนโยบายข้อมูล Strava</a>
                    </li>
                </ul>

                <?php if ($connected): ?>
                <form method="POST" class="scp-form"
                      data-confirm="ยืนยันลบข้อมูล Strava ทั้งหมดในระบบนี้?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_local_strava_data">
                    <button class="scp-btn-ghost-danger" type="submit">
                        ลบข้อมูล Strava ของฉันทั้งหมด
                    </button>
                </form>
                <?php endif; ?>

                <hr class="scp-divider">

                <!-- How it works -->
                <p class="scp-section-label">วิธีการทำงาน</p>
                <div class="scp-steps">
                    <?php
                    $steps = [
                        'เชื่อมต่อ Strava (ทำแค่ครั้งเดียว)',
                        'ออกกำลังกายและบันทึกใน Strava ตามปกติ',
                        'กด "ตรวจสอบกิจกรรม" ในหน้าภารกิจ',
                        'ผ่านเงื่อนไข → รับ Token ทันที',
                    ];
                    foreach ($steps as $i => $text):
                    ?>
                    <div class="scp-step">
                        <span class="scp-step-num"><?= $i + 1 ?></span>
                        <span class="scp-step-text"><?= e($text) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /scp-body -->
        </div><!-- /scp-card -->

        <div class="scp-back-row">
            <a href="<?= BASE_URL ?>/pages/challenges.php" class="scp-back-link">
                ← กลับหน้าภารกิจ
            </a>
        </div>

    </div><!-- /scp-inner -->
</div><!-- /scp-wrap -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
