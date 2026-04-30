<?php
/**
 * admin/challenges/index.php
 * Admin — list all challenges, toggle active, delete
 */

require_once __DIR__ . '/../../includes/admin_check.php';
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

    redirect(BASE_URL . '/admin/challenges/index.php');
}

// ── GET: load all challenges ─────────────────────────────────
$pdo = getDB();
$stmt = $pdo->query("
    SELECT c.challenge_id, c.title, c.type, c.token_reward,
           c.start_date, c.end_date, c.is_active, c.created_at,
           (SELECT COUNT(*) FROM challenge_submissions cs WHERE cs.challenge_id = c.challenge_id) AS submission_count,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.challenge_id = c.challenge_id) AS question_count
    FROM challenges c
    ORDER BY c.created_at DESC
");
$challenges = $stmt->fetchAll();

$flash = getFlash();

$pageTitle  = 'จัดการภารกิจ';
$activePage = 'admin_challenges';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="ac-challenges-wrap">

    <!-- Aurora blobs -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="ac-wrap" style="position:relative; z-index:1; max-width:1100px; margin:0 auto;
                padding:2rem 1.25rem 4rem;">

        <!-- Flash -->
        <?php if ($flash): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.9rem 1.25rem;
                    font-size:0.85rem; font-weight:500;
                    background:<?= $flash['type'] === 'success' ? 'rgba(81,142,92,0.12)' : 'rgba(210,89,42,0.12)' ?>;
                    border:1px solid <?= $flash['type'] === 'success' ? 'rgba(81,142,92,0.30)' : 'rgba(210,89,42,0.30)' ?>;
                    color:<?= $flash['type'] === 'success' ? '#7ec98a' : '#e07a55' ?>;">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div style="display:flex; align-items:center; justify-content:space-between;
                    flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
            <div>
                <h1 style="font-size:1.55rem; font-weight:800; color:#eeebe1;
                           margin:0 0 0.2rem; letter-spacing:-0.01em;">จัดการภารกิจ</h1>
                <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                    สร้าง แก้ไข และจัดการ Challenge ทั้งหมดในระบบ
                    <?php if (!empty($challenges)): ?>
                    <span style="margin-left:0.4rem; font-size:0.68rem; font-weight:700;
                                 background:rgba(255,255,255,0.07); border-radius:999px;
                                 padding:0.12rem 0.5rem; color:#4a4e57;">
                        <?= count($challenges) ?> รายการ
                    </span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/admin/challenges/edit.php"
               class="ch-btn-start"
               style="padding:0.55rem 1.25rem; font-size:0.85rem; border-radius:12px;
                      text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem;">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                สร้างภารกิจใหม่
            </a>
        </div>

        <?php if (!empty($challenges)): ?>
        <!-- Challenges table -->
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">

            <!-- Table header -->
            <div style="display:grid; grid-template-columns:1fr 120px 80px 160px 70px 80px 100px;
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
                $isActive = (bool)$ch['is_active'];
                $sd = date('d/m/y', strtotime((string)$ch['start_date']));
                $ed = date('d/m/y', strtotime((string)$ch['end_date']));
                $isQuiz = $ch['type'] === 'quiz';

                // Date status
                $now = time();
                $start = strtotime((string)$ch['start_date']);
                $end   = strtotime((string)$ch['end_date']);
                if (!$isActive) {
                    $dateClr = '#4a4e57';
                } elseif ($now < $start) {
                    $dateClr = '#4f8b98';  // upcoming — teal
                } elseif ($now > $end) {
                    $dateClr = '#d2592a';  // expired — orange
                } else {
                    $dateClr = '#518e5c';  // live — green
                }
            ?>
            <div class="ac-row"
                 style="display:grid; grid-template-columns:1fr 120px 80px 160px 70px 80px 100px;
                        gap:0; padding:0.9rem 1.25rem; align-items:center;
                        border-bottom:1px solid rgba(255,255,255,0.05);
                        <?= $isActive ? '' : 'opacity:0.5;' ?>">

                <!-- Title -->
                <div style="min-width:0; padding-right:1rem;">
                    <p style="font-size:0.88rem; font-weight:600; color:#eeebe1; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($ch['title']) ?>
                    </p>
                    <?php if ($isQuiz && (int)$ch['question_count'] > 0): ?>
                    <p style="font-size:0.68rem; color:#4a4e57; margin:0.1rem 0 0;">
                        <?= (int)$ch['question_count'] ?> คำถาม
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Type badge -->
                <div>
                    <span style="font-size:0.68rem; font-weight:700; padding:0.22rem 0.6rem;
                                 border-radius:999px; letter-spacing:0.03em;
                                 background:<?= $isQuiz ? 'rgba(79,139,152,0.14)' : 'rgba(218,185,55,0.12)' ?>;
                                 color:<?= $isQuiz ? '#4f8b98' : '#dab937' ?>;
                                 border:1px solid <?= $isQuiz ? 'rgba(79,139,152,0.28)' : 'rgba(218,185,55,0.25)' ?>;">
                        <?= $isQuiz ? '📝 Quiz' : '📷 Photo' ?>
                    </span>
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
                </div>

                <!-- Submissions -->
                <div style="text-align:center; font-size:0.85rem; color:#eeebe1; font-weight:500;">
                    <?= (int)$ch['submission_count'] ?>
                </div>

                <!-- Toggle status -->
                <div style="text-align:center;">
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <button type="submit"
                                style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.65rem;
                                       border-radius:999px; cursor:pointer; letter-spacing:0.02em;
                                       font-family:'Prompt',sans-serif; transition:opacity 0.15s;
                                       background:<?= $isActive ? 'rgba(81,142,92,0.14)' : 'rgba(107,110,119,0.12)' ?>;
                                       color:<?= $isActive ? '#7ec98a' : '#6b6e77' ?>;
                                       border:1px solid <?= $isActive ? 'rgba(81,142,92,0.30)' : 'rgba(107,110,119,0.24)' ?>;">
                            <?= $isActive ? 'เปิดอยู่' : 'ปิดแล้ว' ?>
                        </button>
                    </form>
                </div>

                <!-- Actions -->
                <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.5rem;">
                    <a href="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= (int)$ch['challenge_id'] ?>"
                       style="font-size:0.73rem; font-weight:600; padding:0.32rem 0.75rem;
                              border-radius:8px; text-decoration:none; transition:background 0.15s;
                              background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.20);
                              color:#dab937;"
                       onmouseover="this.style.background='rgba(218,185,55,0.16)'"
                       onmouseout="this.style.background='rgba(218,185,55,0.08)'">
                        แก้ไข
                    </a>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('ยืนยันลบภารกิจ &quot;<?= e(addslashes($ch['title'])) ?>&quot;?\nจะลบข้อมูลการส่งงานและประวัติทั้งหมดด้วย\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <button type="submit"
                                style="width:30px; height:30px; border-radius:8px; cursor:pointer;
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
        </div><!-- /table -->

        <?php else: ?>
        <!-- Empty state -->
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; padding:4rem; text-align:center; backdrop-filter:blur(8px);">
            <p style="font-size:2.2rem; margin:0 0 0.6rem; opacity:0.15;">📋</p>
            <p style="font-size:0.92rem; color:#4a4e57; margin:0 0 1.25rem;">
                ยังไม่มีภารกิจในระบบ
            </p>
            <a href="<?= BASE_URL ?>/admin/challenges/edit.php"
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
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
