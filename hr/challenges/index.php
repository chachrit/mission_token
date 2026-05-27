<?php
/**
 * admin/challenges/index.php
 * Admin — list all challenges, toggle active, delete
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];

// ── POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = (string)($_POST['action'] ?? '');
    $cid    = (int)($_POST['challenge_id'] ?? 0);

    if ($cid > 0) {
        $pdo = getDB();

        if ($action === 'toggle_active') {
            $pdo->prepare("UPDATE challenges SET is_active = 1 - is_active WHERE challenge_id = ?")
                ->execute([$cid]);
            setFlash('success', 'เปลี่ยนสถานะภารกิจแล้ว');

        } elseif ($action === 'delete') {
            try {
                $pdo->beginTransaction();
                // Delete token transactions linked to submissions of this challenge
                $pdo->prepare("
                    DELETE FROM token_transactions
                    WHERE reference_id IN (
                        SELECT submission_id FROM challenge_submissions WHERE challenge_id = ?
                    )
                ")->execute([$cid]);
                $pdo->prepare("DELETE FROM challenge_submissions WHERE challenge_id = ?")->execute([$cid]);
                $pdo->prepare("DELETE FROM quiz_questions WHERE challenge_id = ?")->execute([$cid]);
                $pdo->prepare("DELETE FROM challenges WHERE challenge_id = ?")->execute([$cid]);
                $pdo->commit();
                setFlash('success', 'ลบภารกิจและข้อมูลที่เกี่ยวข้องทั้งหมดแล้ว');
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('[MissionToken] delete challenge error: ' . $e->getMessage());
                setFlash('error', 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage());
            }
        }
    }

    redirect(BASE_URL . '/hr/challenges/index.php');
}

// ── GET: load all challenges ─────────────────────────────────
$pdo = getDB();
$typeFilter = (string)($_GET['type'] ?? '');
$allowedTypes = ['quiz', 'photo']; // 'strava' hidden until feature is ready
if (!in_array($typeFilter, $allowedTypes, true)) $typeFilter = '';

if ($typeFilter) {
    $stmt = $pdo->prepare("SELECT c.challenge_id, c.title, c.type, c.token_reward,
           c.start_date, c.end_date, c.is_active, c.created_at,
           (SELECT COUNT(*) FROM challenge_submissions cs WHERE cs.challenge_id = c.challenge_id) AS submission_count,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.challenge_id = c.challenge_id) AS question_count
    FROM challenges c WHERE c.type = ? ORDER BY c.created_at DESC");
    $stmt->execute([$typeFilter]);
} else {
    $stmt = $pdo->query("SELECT c.challenge_id, c.title, c.type, c.token_reward,
           c.start_date, c.end_date, c.is_active, c.created_at,
           (SELECT COUNT(*) FROM challenge_submissions cs WHERE cs.challenge_id = c.challenge_id) AS submission_count,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.challenge_id = c.challenge_id) AS question_count
    FROM challenges c ORDER BY c.created_at DESC");
}
$challenges = $stmt->fetchAll();

// Sort: non-expired first (order by created_at DESC within each group), expired last
$_now = time();
usort($challenges, function ($a, $b) use ($_now) {
    $aExp = strtotime((string)$a['end_date']) < $_now ? 1 : 0;
    $bExp = strtotime((string)$b['end_date']) < $_now ? 1 : 0;
    if ($aExp !== $bExp) return $aExp - $bExp; // non-expired first
    return strtotime((string)$b['created_at']) - strtotime((string)$a['created_at']); // newest first within group
});

// Thai month names (Buddhist Era = +543)
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thDate(string $dateStr, array $months): string {
    $ts = strtotime($dateStr);
    if (!$ts) return '';
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return $d . ' ' . $months[$m] . ' ' . $y;
}

$flash = getFlash();

$pageTitle  = 'จัดการภารกิจ';
$activePage = 'admin_challenges';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="ac-challenges-wrap">

    <!-- Aurora blobs -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="ac-wrap ac-wrap-shell">


        <!-- Page header -->
        <div class="ac-page-head-wrap">
            <!-- Row 1: title + create button -->
            <div class="ac-page-header-row ac-page-header-row-gap">
                <div>
                    <h1 class="ac-page-title">จัดการภารกิจ</h1>
                    <p class="ac-page-subtitle">
                        สร้าง แก้ไข และจัดการ Challenge ทั้งหมดในระบบ
                        <?php if (!empty($challenges)): ?>
                        <span class="ac-total-chip">
                            <?= count($challenges) ?> รายการ
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/hr/challenges/edit.php"
                         class="ch-btn-start ac-create-btn">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    สร้างภารกิจใหม่
                </a>
            </div>
            <!-- Row 2: type filter pills -->
            <div class="ac-filter-row">
                <?php
                $filters = ['' => 'ทั้งหมด', 'quiz' => 'Quiz', 'photo' => 'Photo']; // 'strava' => 'Strava' hidden
                foreach ($filters as $val => $label):
                    $isActive = ($typeFilter === $val);
                ?>
                <a href="<?= BASE_URL ?>/hr/challenges/index.php<?= $val ? '?type=' . $val : '' ?>"
                   class="ac-filter-pill <?= $isActive ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($challenges)): ?>
        <!-- Challenges table -->
        <div class="ac-table-wrap">

            <!-- Table header -->
            <div class="ac-table-header ac-table-header-grid">
                <span>ชื่อภารกิจ</span>
                <span>ประเภท</span>
            <span class="ac-cell-center">Token</span>
                <span>ช่วงเวลา</span>
            <span class="ac-cell-center">ส่งงาน</span>
            <span class="ac-cell-center">สถานะ</span>
            <span class="ac-cell-right">จัดการ</span>
            </div>

            <?php foreach ($challenges as $ch):
                $isActive  = (bool)$ch['is_active'];
                $sd = thDate((string)$ch['start_date'], $thMonths);
                $ed = thDate((string)$ch['end_date'],   $thMonths);
                $isQuiz    = $ch['type'] === 'quiz';
                $isStrava  = $ch['type'] === 'strava';

                // Date status
                $now   = time();
                $start = strtotime((string)$ch['start_date']);
                $end   = strtotime((string)$ch['end_date']);
                $isExpired  = $end !== false && $now > $end;
                $isUpcoming = $start !== false && $now < $start;

                if ($isExpired) {
                    $dateClass = 'ac-date-cell--expired';
                } elseif (!$isActive) {
                    $dateClass = 'ac-date-cell--inactive';
                } elseif ($isUpcoming) {
                    $dateClass = 'ac-date-cell--upcoming';
                } else {
                    $dateClass = 'ac-date-cell--live';
                }

                $rowClass = 'ac-row';
                if ($isExpired) {
                    $rowClass .= ' ac-row--expired';
                } elseif (!$isActive) {
                    $rowClass .= ' ac-row--inactive';
                }
            ?>
            <div class="<?= $rowClass ?> ac-row-grid">

                <!-- Title -->
                <div class="ac-title-cell">
                    <p class="ac-title-text">
                        <?= e($ch['title']) ?>
                    </p>
                    <?php if ($isQuiz && (int)$ch['question_count'] > 0): ?>
                    <p class="ac-title-subtext">
                        <?= (int)$ch['question_count'] ?> คำถาม
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Type badge -->
                <div>
                    <?php if ($isQuiz): ?>
                    <span class="ac-type-badge ac-type-badge--quiz">Quiz</span>
                    <?php /* elseif ($isStrava): strava hidden
                    <span class="ac-type-badge ac-type-badge--strava">Strava</span>
                    */ elseif ($isStrava): ?>
                    <span class="ac-type-badge ac-type-badge--photo">Photo</span>
                    <?php else: ?>
                    <span class="ac-type-badge ac-type-badge--photo">Photo</span>
                    <?php endif; ?>
                </div>

                <!-- Token -->
                <div class="ac-token-cell">
                    <img src="<?= BASE_URL ?>/assets/images/token.png"
                         width="13" height="13" class="ac-token-icon" alt="">
                    <span class="ac-token-value">
                        <?= formatTokens((int)$ch['token_reward']) ?>
                    </span>
                </div>

                <!-- Date range -->
                <div class="ac-date-cell <?= $dateClass ?>">
                    <?= $sd ?> – <?= $ed ?>
                    <?php if ($isExpired): ?>
                    <span class="ac-expired-pill">หมดอายุ</span>
                    <?php endif; ?>
                </div>

                <!-- Submissions -->
                <div class="ac-submission-count">
                    <?= (int)$ch['submission_count'] ?>
                </div>

                <!-- Toggle status -->
                <div class="ac-cell-center">
                    <form method="POST" class="ac-inline-form" id="toggle-form-<?= (int)$ch['challenge_id'] ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <label class="ac-toggle-switch <?= $isExpired ? 'ac-toggle-expired' : '' ?>"
                               title="<?= $isExpired ? 'หมดอายุแล้ว — ขยายวันหรือลบสิ้น' : ($isActive ? 'คลิกเพื่อปิด' : 'คลิกเพื่อเปิด') ?>">
                            <input type="checkbox"
                                   <?= (!$isExpired && $isActive) ? 'checked' : '' ?>
                                   <?= $isExpired ? 'disabled' : '' ?>
                                   <?= !$isExpired ? 'onchange="this.form.submit()"' : '' ?>>
                            <span class="ac-toggle-track">
                                <span class="ac-toggle-thumb"></span>
                            </span>
                        </label>
                    </form>
                </div>

                <!-- Actions -->
                  <div class="ac-actions-row">
                    <a href="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= (int)$ch['challenge_id'] ?>"
                      class="ac-action-btn ac-action-btn--edit">
                        แก้ไข
                    </a>
                      <form method="POST" class="ac-inline-form-flex"
                          data-onsubmit="return confirm('ยืนยันลบภารกิจ &quot;<?= e(addslashes($ch['title'])) ?>&quot;?\nจะลบข้อมูลการส่งงานและประวัติทั้งหมดด้วย\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <button type="submit"
                            class="ac-action-btn ac-action-btn--delete"
                                title="ลบภารกิจ">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>

            </div><!-- /ac-row -->
            <?php endforeach; ?>
        </div><!-- /ac-challenges-wrap inner end -->

        <?php else: ?>
        <!-- Empty state -->
        <div class="ac-empty-wrap">
            <p class="ac-empty-icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect x="6" y="4" width="12" height="16" rx="2" stroke-width="2"/>
                    <path d="M9 8h6" stroke-width="2"/>
                    <path d="M9 12h6" stroke-width="2"/>
                    <path d="M9 16h4" stroke-width="2"/>
                </svg>
            </p>
            <p class="ac-empty-note">
                ยังไม่มีภารกิจในระบบ
            </p>
            <a href="<?= BASE_URL ?>/hr/challenges/edit.php"
               class="ch-btn-start ac-create-btn">
                + สร้างภารกิจแรก
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /ac-wrap -->
</div><!-- /ac-challenges-wrap -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

