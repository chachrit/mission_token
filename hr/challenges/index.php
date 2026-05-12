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
$allowedTypes = ['quiz', 'photo', 'strava'];
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

    <div class="ac-wrap" style="position:relative; z-index:1; max-width:1200px; margin:0 auto;
                padding:2rem 1.5rem 4rem;">


        <!-- Page header -->
        <div style="margin-bottom:1.5rem;">
            <!-- Row 1: title + create button -->
            <div class="ac-page-header-row" style="display:flex; align-items:center; justify-content:space-between;
                        gap:1rem; margin-bottom:0.75rem;">
                <div>
                    <h1 style="font-size:1.55rem; font-weight:800; color:#eeebe1;
                               margin:0 0 0.2rem; letter-spacing:-0.01em;">จัดการภารกิจ</h1>
                    <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                        สร้าง แก้ไข และจัดการ Challenge ทั้งหมดในระบบ
                        <?php if (!empty($challenges)): ?>
                        <span style="margin-left:0.4rem; font-size:0.68rem; font-weight:700;
                                     background:rgba(255,255,255,0.07); border-radius:999px;
                                     padding:0.12rem 0.5rem; color:#8a8e97;">
                            <?= count($challenges) ?> รายการ
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/hr/challenges/edit.php"
                   class="ch-btn-start"
                   style="padding:0.55rem 1.25rem; font-size:0.85rem; border-radius:12px;
                          text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; flex-shrink:0;">
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
            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                <?php
                $filters = ['' => 'ทั้งหมด', 'quiz' => 'Quiz', 'photo' => 'Photo', 'strava' => 'Strava'];
                foreach ($filters as $val => $label):
                    $isActive = ($typeFilter === $val);
                ?>
                <a href="<?= BASE_URL ?>/hr/challenges/index.php<?= $val ? '?type=' . $val : '' ?>"
                   style="font-size:0.72rem; font-weight:700; padding:0.28rem 0.75rem;
                          border-radius:999px; text-decoration:none; transition:all 0.15s;
                          background:<?= $isActive ? 'rgba(218,185,55,0.18)' : 'rgba(255,255,255,0.05)' ?>;
                          border:1px solid <?= $isActive ? 'rgba(218,185,55,0.40)' : 'rgba(255,255,255,0.10)' ?>;
                          color:<?= $isActive ? '#dab937' : '#6b6e77' ?>;">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($challenges)): ?>
        <!-- Challenges table -->
        <div class="ac-table-wrap" style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; backdrop-filter:blur(8px);">

            <!-- Table header -->
            <div class="ac-table-header" style="display:grid; grid-template-columns:1fr 105px 75px 190px 65px 85px 105px;
                        gap:0; padding:0.65rem 1.25rem;
                        background:rgba(255,255,255,0.03);
                        border-bottom:1px solid rgba(255,255,255,0.07);
                        font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                        text-transform:uppercase; color:#6b6e77;">
                <span>ชื่อภารกิจ</span>
                <span>ประเภท</span>
                <span style="text-align:center;">Token</span>
                <span>ช่วงเวลา</span>
                <span style="text-align:center;">ส่งงาน</span>
                <span style="text-align:center;">สถานะ</span>
                <span style="text-align:right;">จัดการ</span>
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
                    $dateClr = '#d2592a';  // expired — orange
                } elseif (!$isActive) {
                    $dateClr = '#8a8e97';  // inactive — grey
                } elseif ($isUpcoming) {
                    $dateClr = '#4f8b98';  // upcoming — teal
                } else {
                    $dateClr = '#518e5c';  // live — green
                }

                // Row opacity: expired = very dim, inactive = slight dim, live = normal
                if ($isExpired) {
                    $rowOpacity = 'opacity:0.45;';
                } elseif (!$isActive) {
                    $rowOpacity = 'opacity:0.55;';
                } else {
                    $rowOpacity = '';
                }
            ?>
            <div class="ac-row"
                 style="display:grid; grid-template-columns:1fr 105px 75px 190px 65px 85px 105px;
                        gap:0; padding:0.9rem 1.25rem; align-items:center;
                        border-bottom:1px solid rgba(255,255,255,0.05);
                        <?= $isExpired ? 'background:rgba(210,89,42,0.04);' : '' ?>
                        <?= $rowOpacity ?>">

                <!-- Title -->
                <div style="min-width:0; padding-right:1rem;">
                    <p style="font-size:0.88rem; font-weight:600; color:#eeebe1; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($ch['title']) ?>
                    </p>
                    <?php if ($isQuiz && (int)$ch['question_count'] > 0): ?>
                    <p style="font-size:0.68rem; color:#8a8e97; margin:0.1rem 0 0;">
                        <?= (int)$ch['question_count'] ?> คำถาม
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Type badge -->
                <div>
                    <?php if ($isQuiz): ?>
                    <span style="font-size:0.68rem; font-weight:700; padding:0.22rem 0.6rem;
                                 border-radius:999px; letter-spacing:0.03em;
                                 background:rgba(79,139,152,0.14); color:#4f8b98;
                                 border:1px solid rgba(79,139,152,0.28);">Quiz</span>
                    <?php elseif ($isStrava): ?>
                    <span style="font-size:0.68rem; font-weight:700; padding:0.22rem 0.6rem;
                                 border-radius:999px; letter-spacing:0.03em;
                                 background:rgba(252,76,2,0.12); color:#FC4C02;
                                 border:1px solid rgba(252,76,2,0.30);">Strava</span>
                    <?php else: ?>
                    <span style="font-size:0.68rem; font-weight:700; padding:0.22rem 0.6rem;
                                 border-radius:999px; letter-spacing:0.03em;
                                 background:rgba(218,185,55,0.12); color:#dab937;
                                 border:1px solid rgba(218,185,55,0.25);">Photo</span>
                    <?php endif; ?>
                </div>

                <!-- Token -->
                <div style="text-align:center; display:flex; align-items:center;
                            justify-content:center; gap:0.3rem;">
                    <img src="<?= BASE_URL ?>/assets/images/token.png"
                         width="13" height="13" style="object-fit:contain; opacity:0.75;" alt="">
                    <span style="font-size:0.88rem; font-weight:800; color:#f8e769;">
                        <?= formatTokens((int)$ch['token_reward']) ?>
                    </span>
                </div>

                <!-- Date range -->
                <div style="font-size:0.72rem; color:<?= $dateClr ?>; line-height:1.6;">
                    <?= $sd ?> – <?= $ed ?>
                    <?php if ($isExpired): ?>
                    <span style="display:inline-block; margin-left:0.3rem; font-size:0.60rem;
                                 font-weight:800; letter-spacing:0.05em;
                                 background:rgba(210,89,42,0.18); color:#d2592a;
                                 border:1px solid rgba(210,89,42,0.40);
                                 border-radius:999px; padding:0 0.45rem; vertical-align:middle;">หมดอายุ</span>
                    <?php endif; ?>
                </div>

                <!-- Submissions -->
                <div style="text-align:center; font-size:0.85rem; color:#eeebe1; font-weight:500;">
                    <?= (int)$ch['submission_count'] ?>
                </div>

                <!-- Toggle status -->
                <div style="text-align:center;">
                    <form method="POST" style="display:inline;" id="toggle-form-<?= (int)$ch['challenge_id'] ?>">
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
                <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.5rem;">
                    <a href="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= (int)$ch['challenge_id'] ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              height:30px; box-sizing:border-box;
                              font-size:0.73rem; font-weight:600; padding:0 0.75rem;
                              border-radius:8px; text-decoration:none; transition:background 0.15s;
                              background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.20);
                              color:#dab937; white-space:nowrap;"
                       onmouseover="this.style.background='rgba(218,185,55,0.16)'"
                       onmouseout="this.style.background='rgba(218,185,55,0.08)'">
                        แก้ไข
                    </a>
                    <form method="POST" style="display:inline-flex; margin:0;"
                          onsubmit="return confirm('ยืนยันลบภารกิจ &quot;<?= e(addslashes($ch['title'])) ?>&quot;?\nจะลบข้อมูลการส่งงานและประวัติทั้งหมดด้วย\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <button type="submit"
                                style="width:30px; height:30px; box-sizing:border-box;
                                       border-radius:8px; cursor:pointer; margin:0;
                                       background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.20);
                                       color:#d2592a; display:inline-flex; align-items:center;
                                       justify-content:center; transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(210,89,42,0.18)'"
                                onmouseout="this.style.background='rgba(210,89,42,0.08)'"
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
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; padding:4rem; text-align:center; backdrop-filter:blur(8px);">
            <p style="font-size:2.2rem; margin:0 0 0.6rem; opacity:0.15; display:inline-flex; align-items:center;">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect x="6" y="4" width="12" height="16" rx="2" stroke-width="2"/>
                    <path d="M9 8h6" stroke-width="2"/>
                    <path d="M9 12h6" stroke-width="2"/>
                    <path d="M9 16h4" stroke-width="2"/>
                </svg>
            </p>
            <p style="font-size:0.92rem; color:#8a8e97; margin:0 0 1.25rem;">
                ยังไม่มีภารกิจในระบบ
            </p>
            <a href="<?= BASE_URL ?>/hr/challenges/edit.php"
               class="ch-btn-start"
               style="padding:0.55rem 1.25rem; font-size:0.85rem; border-radius:12px;
                      text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem;">
                + สร้างภารกิจแรก
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /ac-wrap -->
</div><!-- /ac-challenges-wrap -->

<style>
.ac-row:last-child { border-bottom: none !important; }
.ac-row:hover { background: rgba(255,255,255,0.025); }

/* Toggle switch */
.ac-toggle-switch {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
.ac-toggle-switch input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.ac-toggle-track {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    background: rgba(107,110,119,0.25);
    border: 1px solid rgba(107,110,119,0.35);
    border-radius: 999px;
    transition: background 0.2s, border-color 0.2s;
}
.ac-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 14px;
    height: 14px;
    background: #6b6e77;
    border-radius: 50%;
    transition: transform 0.2s, background 0.2s;
}
.ac-toggle-switch input:checked + .ac-toggle-track {
    background: rgba(81,142,92,0.30);
    border-color: rgba(81,142,92,0.55);
}
.ac-toggle-switch input:checked + .ac-toggle-track .ac-toggle-thumb {
    transform: translateX(16px);
    background: #7ec98a;
}
.ac-toggle-switch:hover .ac-toggle-track {
    border-color: rgba(218,185,55,0.45);
}
/* Expired state: locked off with orange tint */
.ac-toggle-expired {
    cursor: not-allowed;
    pointer-events: none;
}
.ac-toggle-expired .ac-toggle-track {
    background: rgba(210,89,42,0.12);
    border-color: rgba(210,89,42,0.30);
}
.ac-toggle-expired .ac-toggle-thumb {
    background: rgba(210,89,42,0.50);
}

/* ── Responsive ─────────────────────────────────────────── */
.ac-table-wrap { overflow: hidden; }

@media (max-width: 900px) {
    .ac-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ac-table-header, .ac-row { min-width: 640px; }
}

@media (max-width: 640px) {
    .ac-page-header-row { flex-direction: column; align-items: flex-start; }
    .ac-page-header-row > a {
        width: 100%; justify-content: center;
        box-sizing: border-box;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
