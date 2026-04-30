<?php
/**
 * pages/challenges.php
 * List active challenges + handle quiz/photo submission
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

// Prevent browser from caching this page so back button reloads fresh state
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$employeeId = (int)$_SESSION['employee_id'];
$flash      = null;
$dataError  = null;

// ── POST: handle submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action      = (string)($_POST['action'] ?? '');
    $challengeId = (int)($_POST['challenge_id'] ?? 0);
    $challenge   = $challengeId ? getChallenge($challengeId) : null;

    if (!$challenge || !(bool)$challenge['is_active']) {
        setFlash('error', 'ไม่พบภารกิจนี้ หรือภารกิจปิดแล้ว');
        redirect(BASE_URL . '/pages/challenges.php');
    }

    // Block double-submission (non-rejected)
    if (hasSubmittedChallenge($employeeId, $challengeId)) {
        setFlash('error', 'คุณส่งภารกิจนี้ไปแล้ว ไม่สามารถส่งซ้ำได้');
        redirect(BASE_URL . '/pages/challenges.php');
    }

    $pdo = getDB();

    // ── QUIZ submission ──────────────────────────────────────
    if ($action === 'submit_quiz') {
        // Quiz allows only ONE attempt ever — block even rejected submissions
        $chkStmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt FROM challenge_submissions
            WHERE employee_id = ? AND challenge_id = ?
        ");
        $chkStmt->execute([$employeeId, $challengeId]);
        if ((int)$chkStmt->fetch()['cnt'] > 0) {
            setFlash('error', 'คุณทำ Quiz นี้ไปแล้ว ไม่สามารถทำซ้ำได้');
            redirect(BASE_URL . '/pages/challenges.php');
        }

        $questions = getQuizQuestions($challengeId);
        if (empty($questions)) {
            setFlash('error', 'ภารกิจนี้ยังไม่มีคำถาม');
            redirect(BASE_URL . '/pages/challenges.php');
        }

        $correctCount = 0;
        foreach ($questions as $q) {
            $qid      = (int)$q['question_id'];
            $answered = strtoupper(trim((string)($_POST['q_' . $qid] ?? '')));
            $correct  = strtoupper(trim(getCorrectOption($qid)));
            if ($answered !== '' && $answered === $correct) {
                $correctCount++;
            }
        }

        $totalQ    = count($questions);
        $isCorrect = ($correctCount === $totalQ); // must answer all correctly
        $status    = $isCorrect ? 'auto_approved' : 'rejected';
        $awarded   = $isCorrect ? (int)$challenge['token_reward'] : 0;
        $firstAnswer = strtoupper(trim((string)($_POST['q_' . (int)$questions[0]['question_id']] ?? '')));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO challenge_submissions
                    (employee_id, challenge_id, submission_type, quiz_answer, is_correct, status, token_awarded)
                VALUES (?, ?, 'quiz', ?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $challengeId, $firstAnswer, $isCorrect ? 1 : 0, $status, $awarded]);
            $submissionId = (int)$pdo->lastInsertId();
            $pdo->commit();

            if ($isCorrect) {
                awardTokens($employeeId, $awarded, 'quiz_reward', $submissionId,
                    'Quiz: ' . $challenge['title']);
                setFlash('success', "ยินดีด้วย! ตอบถูกทั้งหมด {$totalQ}/{$totalQ} ข้อ ได้รับ +{$awarded} Token");
            } else {
                setFlash('error', "ตอบถูก {$correctCount}/{$totalQ} ข้อ — ไม่ผ่านเกณฑ์ ไม่ได้รับ Token (ไม่สามารถลองใหม่ได้)");
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[MissionToken] quiz submit error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }

        redirect(BASE_URL . '/pages/challenges.php');
    }

    // ── PHOTO submission ─────────────────────────────────────
    if ($action === 'submit_photo') {
        $file = $_FILES['photo'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'กรุณาเลือกไฟล์รูปภาพ');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        // Validate size
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            setFlash('error', 'ไฟล์ใหญ่เกิน 5MB');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        // Validate MIME type from file content (not extension)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME, true)) {
            setFlash('error', 'ไฟล์ต้องเป็นรูปภาพ (jpg, png, gif, webp) เท่านั้น');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        // Generate safe filename
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext      = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));
        if (!in_array($ext, ALLOWED_EXT, true)) { $ext = 'jpg'; }
        $filename = sprintf('sub_%d_%d_%s.%s', $employeeId, $challengeId, bin2hex(random_bytes(6)), $ext);
        $destPath = UPLOAD_PATH . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            setFlash('error', 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO challenge_submissions
                    (employee_id, challenge_id, submission_type, photo_path, status, token_awarded)
                VALUES (?, ?, 'photo', ?, 'pending', 0)
            ");
            $stmt->execute([$employeeId, $challengeId, $filename]);
            setFlash('success', 'ส่งหลักฐานสำเร็จ! รอการตรวจสอบจาก HR/Manager');
        } catch (Throwable $e) {
            error_log('[MissionToken] photo submit error: ' . $e->getMessage());
            @unlink($destPath);
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }

        redirect(BASE_URL . '/pages/challenges.php');
    }

    redirect(BASE_URL . '/pages/challenges.php');
}

// ── GET: load data ───────────────────────────────────────────
$challenges      = [];
$focusChallengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $challenges = getActiveChallenges();

    // Annotate with user's submission status
    $pdo = getDB();
    foreach ($challenges as &$ch) {
        $stmt = $pdo->prepare("
            SELECT TOP 1 status, token_awarded, submitted_at
            FROM   challenge_submissions
            WHERE  employee_id  = ? AND challenge_id = ?
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$employeeId, (int)$ch['challenge_id']]);
        $sub = $stmt->fetch();
        $ch['my_status']       = $sub ? $sub['status']        : null;
        $ch['my_token_awarded']= $sub ? (int)$sub['token_awarded'] : 0;
        $ch['my_submitted_at'] = $sub ? $sub['submitted_at']  : null;

        // Pre-load quiz questions count
        if ($ch['type'] === 'quiz') {
            $sq = $pdo->prepare("SELECT COUNT(*) AS cnt FROM quiz_questions WHERE challenge_id = ?");
            $sq->execute([(int)$ch['challenge_id']]);
            $ch['question_count'] = (int)$sq->fetch()['cnt'];
        }
    }
    unset($ch);

} catch (Throwable $e) {
    error_log('[MissionToken] challenges load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
}

// Load quiz questions if a specific quiz challenge is focused
$focusChallenge = null;
$quizQuestions  = [];
if ($focusChallengeId > 0) {
    foreach ($challenges as $ch) {
        if ((int)$ch['challenge_id'] === $focusChallengeId) {
            $focusChallenge = $ch;
            break;
        }
    }
    if ($focusChallenge && $focusChallenge['type'] === 'quiz' && !$focusChallenge['my_status']) {
        try {
            $quizQuestions = getQuizQuestions($focusChallengeId);
        } catch (Throwable $e) {
            error_log('[MissionToken] quiz questions load error: ' . $e->getMessage());
        }
        // If no questions found, clear focus so we fall back to list view
        if (empty($quizQuestions)) {
            $focusChallenge   = null;
            $focusChallengeId = 0;
        }
    } else {
        // Not a valid quiz focus — show list view
        $focusChallenge   = null;
        $focusChallengeId = 0;
    }
}

// Load quiz questions for review (rejected) — show correct answers + explanation
$rejectedQuizReviews = []; // [challenge_id => questions array]
try {
    $pdo = getDB();
    foreach ($challenges as $ch) {
        if ($ch['type'] === 'quiz' && $ch['my_status'] === 'rejected') {
            $rejectedQuizReviews[(int)$ch['challenge_id']] = getQuizQuestions((int)$ch['challenge_id']);
        }
    }
} catch (Throwable $e) {
    error_log('[MissionToken] quiz review load error: ' . $e->getMessage());
}

$flash = getFlash();

$statusLabel = [
    'pending'       => ['text' => 'รอ Approve',  'bg' => '#fef9c3', 'color' => '#854d0e'],
    'approved'      => ['text' => 'อนุมัติแล้ว', 'bg' => '#dcfce7', 'color' => '#166534'],
    'auto_approved' => ['text' => 'ผ่านแล้ว',    'bg' => '#dcfce7', 'color' => '#166534'],
    'rejected'      => ['text' => 'ไม่ผ่าน',      'bg' => '#fee2e2', 'color' => '#991b1b'],
];

$pageTitle  = 'ภารกิจ';
$activePage = 'challenges';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="ch-challenges-wrap">
<div class="ch-aurora ch-aurora-1"></div>
<div class="ch-aurora ch-aurora-2"></div>
<div class="ds-page-inner">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Flash: Winning Moment (success) or error bar -->
    <?php if ($flash && $flash['type'] === 'success'): ?>
    <div id="win-card" class="mb-8 relative overflow-hidden rounded-2xl px-6 py-10 text-center ch-win-card">
        <div class="ch-win-glow"></div>
        <div class="ch-win-corner ch-win-corner--tl"></div>
        <div class="ch-win-corner ch-win-corner--tr"></div>
        <div class="ch-win-corner ch-win-corner--bl"></div>
        <div class="ch-win-corner ch-win-corner--br"></div>
        <div class="relative z-10">
            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token"
                 id="win-token"
                 class="h-20 w-20 mx-auto mb-4 ch-win-token">
            <h2 class="text-2xl font-bold mb-2 ch-win-title">ยินดีด้วย! 🎉</h2>
            <p class="text-sm leading-relaxed mb-6 max-w-xs mx-auto ch-win-desc">
                <?= e($flash['message']) ?>
            </p>
            <a href="<?= BASE_URL ?>/pages/challenges.php"
               class="btn-gold inline-flex items-center gap-2">
                ดูภารกิจอื่น
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
    <script>
    (function () {
        function fireWinConfetti() {
            confetti({ particleCount:80, spread:65, origin:{y:0.5},
                       colors:['#dab937','#f8e769','#c9a830','#fdfcdf','#3a3e43'],
                       scalar:0.9, ticks:150, gravity:1.1 });
            setTimeout(function () {
                confetti({ particleCount:45, spread:120, origin:{x:0.1,y:0.55},
                           colors:['#dab937','#f8e769'], scalar:0.7, ticks:110 });
                confetti({ particleCount:45, spread:120, origin:{x:0.9,y:0.55},
                           colors:['#dab937','#f8e769'], scalar:0.7, ticks:110 });
            }, 320);
        }
        if (typeof confetti !== 'undefined') {
            fireWinConfetti();
        } else {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js';
            s.onload = fireWinConfetti;
            document.head.appendChild(s);
        }
    })();
    </script>
    <?php elseif ($flash): ?>
    <div class="ch-error-flash">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($dataError): ?>
    <div class="ch-error-flash">
        <?= e($dataError) ?>
    </div>
    <?php endif; ?>

    <?php if ($focusChallenge && !empty($quizQuestions)): ?>
    <!-- ── QUIZ VIEW: Gamified Mission Card ── -->
    <?php
        $ch     = $focusChallenge;
        $cid    = (int)$ch['challenge_id'];
        $totalQ = count($quizQuestions);
    ?>

    <!-- Back breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm ch-breadcrumb">
        <a href="<?= BASE_URL ?>/pages/challenges.php"
           class="ch-breadcrumb-link inline-flex items-center gap-1.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            กลับรายการภารกิจ
        </a>
        <span class="ch-breadcrumb-sep">›</span>
        <span class="ch-breadcrumb-current"><?= e($ch['title']) ?></span>
    </div>

    <div class="max-w-2xl mx-auto">

        <!-- Mission info strip -->
        <div class="ch-mission-strip p-5 mb-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
                <span class="badge ch-badge-quiz text-xs font-semibold mb-1.5 inline-block">📝 Quiz Mission</span>
                <h2 class="text-lg font-semibold ch-mission-title leading-snug"><?= e($ch['title']) ?></h2>
                <p class="mt-1 text-sm ch-mission-desc leading-relaxed"><?= e((string)$ch['description']) ?></p>
            </div>
            <div class="flex flex-col items-center flex-shrink-0 text-center pl-4 ch-mission-divider">
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token"
                     class="h-12 w-12 token-float">
                <p class="text-base font-bold text-j-gold mt-1">+<?= formatTokens((int)$ch['token_reward']) ?></p>
                <p class="text-[10px] text-j-slate uppercase tracking-wider">Token</p>
            </div>
        </div>

        <!-- Main quiz card -->
        <div class="ch-quiz-card">

            <!-- Dark progress header -->
            <div class="px-6 py-4 ch-quiz-header">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-widest text-j-gold">
                        Mission Progress
                    </span>
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-mono ch-quiz-counter">
                            ข้อที่ <span id="q-current">1</span> / <?= $totalQ ?>
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full ch-quiz-token-badge">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="w-3 h-3">
                            +<?= formatTokens((int)$ch['token_reward']) ?>
                        </span>
                    </div>
                </div>
                <div class="quiz-progress-track">
                    <div class="quiz-progress-fill" id="quiz-progress"
                         style="width:<?= round(100 / $totalQ) ?>%"></div>
                </div>
            </div>

            <!-- Steps area -->
            <div class="ch-quiz-body">
                <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                      id="quiz-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="submit_quiz">
                    <input type="hidden" name="challenge_id" value="<?= $cid ?>">

                    <?php foreach ($quizQuestions as $qi => $q): ?>
                    <?php
                        $qid  = (int)$q['question_id'];
                        $opts = [
                            'A' => $q['option_a'],
                            'B' => $q['option_b'],
                            'C' => $q['option_c'] ?? null,
                            'D' => $q['option_d'] ?? null,
                        ];
                    ?>
                    <div class="quiz-step p-6 <?= $qi === 0 ? 'active' : '' ?>"
                         id="step-<?= $qi ?>" data-step="<?= $qi ?>">

                        <!-- Question number badge + text -->
                        <div class="flex items-start gap-3 mb-5">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                         text-xs font-bold flex-shrink-0 mt-0.5 ch-quiz-qnum">
                                <?= $qi + 1 ?>
                            </span>
                            <p class="text-base font-semibold ch-quiz-qtext leading-snug">
                                <?= e($q['question_text']) ?>
                            </p>
                        </div>

                        <!-- Option cards -->
                        <div class="grid gap-2.5 mb-6">
                            <?php foreach ($opts as $letter => $text):
                                if ($text === null) continue; ?>
                            <label class="quiz-opt" id="opt-<?= $qid ?>-<?= $letter ?>">
                                <input type="radio" name="q_<?= $qid ?>" value="<?= $letter ?>" required>
                                <span class="quiz-opt-letter"><?= $letter ?></span>
                                <span class="text-sm ch-quiz-opt-text flex-1"><?= e($text) ?></span>
                                <svg class="opt-check w-4 h-4" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Step navigation -->
                        <div class="flex items-center gap-3 pt-4 ch-step-nav-border">
                            <?php if ($qi > 0): ?>
                            <button type="button"
                                    class="btn-outline text-sm px-4 py-2.5"
                                    onclick="quizGoStep(<?= $qi - 1 ?>)">← ย้อนกลับ</button>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/pages/challenges.php"
                               class="btn-outline text-sm px-4 py-2.5">ยกเลิก</a>
                            <?php endif; ?>

                            <?php if ($qi < $totalQ - 1): ?>
                            <button type="button"
                                    id="next-<?= $qi ?>"
                                    class="btn-gold ml-auto text-sm px-5 py-2.5"
                                    disabled
                                    onclick="quizGoStep(<?= $qi + 1 ?>)">ถัดไป →</button>
                            <?php else: ?>
                            <button type="submit"
                                    id="quiz-submit-btn"
                                    class="btn-gold ml-auto text-sm px-6 py-2.5"
                                    disabled>✓ ส่งคำตอบ</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Warning footnote -->
                    <div class="ch-quiz-footnote px-6 py-3 flex items-center gap-2 text-xs">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        ตอบได้ 1 ครั้งเท่านั้น — ต้องตอบถูกทุกข้อเพื่อรับ Token
                    </div>
                </form>
            </div>
        </div><!-- /quiz card -->

    </div><!-- /max-w-2xl -->

    <!-- ── Processing Modal ── -->
    <div id="quiz-processing-modal"
         aria-live="assertive" role="status"
         class="ch-processing-modal"
         style="display:none;">
        <div class="ch-processing-modal-inner">
            <div class="ch-qpm-spinner">
                <div class="ch-qpm-orbit"></div>
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="ch-qpm-token">
            </div>
            <p class="text-base font-semibold ch-qpm-title mb-1.5">กำลังตรวจสอบคำตอบ…</p>
            <p class="text-sm ch-qpm-sub" id="qpm-dots">กรุณารอสักครู่</p>
        </div>
    </div>

    <script>
    (function () {
        const totalQ = <?= (int)$totalQ ?>;

        /* ── Step navigation ─────────────────────────────── */
        window.quizGoStep = function (idx) {
            const currentStep = document.querySelector('.quiz-step.active');
            const currentIdx  = currentStep ? parseInt(currentStep.dataset.step, 10) : -1;
            const forward     = idx > currentIdx;
            if (currentStep) currentStep.classList.remove('active', 'step-enter-fwd', 'step-enter-back');
            const step = document.getElementById('step-' + idx);
            step.classList.add('active', forward ? 'step-enter-fwd' : 'step-enter-back');
            setTimeout(function () { step.classList.remove('step-enter-fwd', 'step-enter-back'); }, 340);
            document.getElementById('q-current').textContent = idx + 1;
            const pct = Math.round(((idx + 1) / totalQ) * 100);
            document.getElementById('quiz-progress').style.width = pct + '%';
            const checked = step.querySelector('input[type="radio"]:checked');
            if (checked) {
                const nb = document.getElementById('next-' + idx);
                if (nb) nb.disabled = false;
                const sb = document.getElementById('quiz-submit-btn');
                if (sb && idx === totalQ - 1) sb.disabled = false;
            }
            step.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        /* ── Option card click handler ───────────────────── */
        document.querySelectorAll('.quiz-opt').forEach(function (label) {
            label.addEventListener('click', function () {
                const radio   = this.querySelector('input[type="radio"]');
                if (!radio) return;
                const stepEl  = this.closest('.quiz-step');
                const stepIdx = parseInt(stepEl.dataset.step, 10);
                stepEl.querySelectorAll('.quiz-opt').forEach(function (l) { l.classList.remove('selected'); });
                radio.checked = true;
                this.classList.add('selected');
                const nb = document.getElementById('next-' + stepIdx);
                if (nb) nb.disabled = false;
                const sb = document.getElementById('quiz-submit-btn');
                if (sb && stepIdx === totalQ - 1) sb.disabled = false;
            });
        });

        /* ── Processing modal ────────────────────────────── */
        function showProcessingModal() {
            const modal  = document.getElementById('quiz-processing-modal');
            modal.style.display = 'flex';
            const dotsEl = document.getElementById('qpm-dots');
            const base   = 'กรุณารอสักครู่';
            let tick = 0;
            return setInterval(function () {
                tick = (tick + 1) % 4;
                dotsEl.textContent = base + '.'.repeat(tick);
            }, 280);
        }

        /* ── Submit handler ──────────────────────────────── */
        document.getElementById('quiz-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const dotsTimer = showProcessingModal();
            function doSubmit() { clearInterval(dotsTimer); form.submit(); }
            function fireAndSubmit() {
                confetti({ particleCount:70, spread:65, origin:{y:0.72},
                           colors:['#dab937','#f8e769','#c9a830','#fdfcdf','#3a3e43'],
                           scalar:0.85, ticks:130, gravity:1.3 });
                setTimeout(doSubmit, 480);
            }
            setTimeout(function () {
                if (typeof confetti !== 'undefined') {
                    fireAndSubmit();
                } else {
                    const s  = document.createElement('script');
                    s.src    = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js';
                    s.onload = fireAndSubmit; s.onerror = doSubmit;
                    document.head.appendChild(s);
                }
            }, 1050);
        });
    })();
    </script>

    <?php else: ?>
    <!-- ── LIST VIEW: Quest Board ── -->
    <?php
        $_done  = 0;
        foreach ($challenges as $_c) {
            if (in_array($_c['my_status'], ['approved','auto_approved'], true)) $_done++;
        }
        $_total = count($challenges);

        $questsAvailable = array_values(array_filter($challenges, fn($c) => $c['my_status'] === null));
        $questsDone      = array_values(array_filter($challenges, fn($c) =>
            in_array($c['my_status'], ['approved','auto_approved','pending','rejected'], true)
        ));
    ?>

    <?php if ($challenges): ?>

    <!-- Quest Board hero header -->
    <div class="mb-8 px-8 py-16 relative overflow-hidden ch-board-hero">
        <div class="ch-board-dot-grid"></div>
        <div class="ch-board-glow"></div>
        <div class="relative max-w-7xl mx-auto flex items-center justify-between gap-8">
            <div>
                <p class="text-[14px] font-bold uppercase mb-2.5 ch-board-hero-label">&#9876; Quest Board</p>
                <h1 class="text-5xl font-bold leading-tight ch-hero-title">ภารกิจทั้งหมด</h1>
                <p class="text-base mt-2.5 ch-hero-sub">เลือกภารกิจที่ต้องการ แล้วส่งหลักฐานเพื่อรับ Token</p>
            </div>
            <div class="flex flex-col items-end gap-3 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="w-7 h-7">
                    <p class="text-xl font-bold text-j-gold">
                        <?= $_done ?><span class="font-normal text-base" style="color:#9ca3a8;"> / <?= $_total ?> ภารกิจสำเร็จ</span>
                    </p>
                </div>
                <div class="ch-board-progress-track">
                    <div class="ch-board-progress-fill"
                         style="width:<?= $_total > 0 ? round($_done / $_total * 100) : 0 ?>%;"></div>
                </div>
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b7278;">
                    <?= $_total > 0 ? round($_done / $_total * 100) : 0 ?>% Complete
                </p>
            </div>
        </div>
    </div>

    <!-- ── SECTION 1: ภารกิจรอคุณอยู่ ── -->
    <?php if ($questsAvailable): ?>
    <div class="mb-12">
        <div class="flex items-center gap-3 mb-6">
            <div class="ch-section-bar"></div>
            <h2 class="ch-section-heading">ภารกิจรอคุณอยู่</h2>
            <span class="ch-count-badge"><?= count($questsAvailable) ?></span>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($questsAvailable as $ch):
            $cid        = (int)$ch['challenge_id'];
            $isRejected = $ch['my_status'] === 'rejected';
            $_ed        = $ch['end_date'] ? date('d/m/Y', strtotime((string)$ch['end_date'])) : null;
            $_daysLeft  = $ch['end_date'] ? (int)(new DateTime('today'))->diff(new DateTime(date('Y-m-d', strtotime((string)$ch['end_date']))))->days * ((new DateTime('today') <= new DateTime(date('Y-m-d', strtotime((string)$ch['end_date'])))) ? 1 : -1) : null;
        ?>
        <div class="ch-quest-flip-scene">
            <div class="ch-flip-card" id="flip-<?= $cid ?>">

                <!-- ── FRONT FACE ── -->
                <div class="ch-flip-front ch-quest-card <?= $isRejected ? 'ch-quest-card--rejected' : '' ?>">
                    <div class="ch-quest-accent-bar <?= $isRejected ? 'ch-quest-accent-bar--rejected' : '' ?>"></div>
                    <div class="ch-quest-inner">
                        <!-- Top: type badge + urgency (if near deadline) -->
                        <div class="ch-quest-top-row">
                            <span class="ch-type-badge">
                                <?= $ch['type'] === 'quiz' ? 'Quiz' : 'Photo' ?>
                            </span>
                            <?php if ($_daysLeft !== null && $_daysLeft >= 0 && $_daysLeft <= 7): ?>
                            <span style="font-size:0.65rem;font-weight:700;color:#d2592a;background:rgba(210,89,42,0.10);border:1px solid rgba(210,89,42,0.25);border-radius:6px;padding:0.18rem 0.55rem;">
                                <?= $_daysLeft === 0 ? '⚡ วันนี้!' : '⚡ เหลือ ' . $_daysLeft . ' วัน' ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Token reward hero — the prize hook -->
                        <div class="ch-front-token-hero">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="token-coin-anim"
                                 style="width:36px;height:36px;object-fit:contain;">
                            <span class="ch-front-token-amt">+<?= formatTokens((int)$ch['token_reward']) ?></span>
                            <span class="ch-front-token-lbl">Token Reward</span>
                        </div>

                        <!-- Title + mystery (classified) lines -->
                        <div>
                            <h3 class="ch-quest-title"><?= e($ch['title']) ?></h3>
                            <div class="ch-mystery-lines">
                                <div class="ch-mystery-line ch-mystery-line--long"></div>
                                <div class="ch-mystery-line ch-mystery-line--medium"></div>
                                <div class="ch-mystery-line ch-mystery-line--short"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── BACK FACE ── -->
                <div class="ch-flip-back ch-quest-card <?= $isRejected ? 'ch-quest-card--rejected' : '' ?>"
                     <?php if (!$isRejected && $ch['type'] === 'quiz'): ?>
                     onclick="window.location='<?= BASE_URL ?>/pages/challenges.php?id=<?= $cid ?>'" style="cursor:pointer;"
                     <?php endif; ?>>
                    <div class="ch-quest-accent-bar <?= $isRejected ? 'ch-quest-accent-bar--rejected' : '' ?>"></div>
                    <div class="ch-flip-back-body">
                        <!-- Header: type badge + click hint for quiz -->
                        <div class="ch-flip-back-header">
                            <span class="ch-type-badge"><?= $ch['type'] === 'quiz' ? 'Quiz' : 'Photo' ?></span>
                            <?php if (!$isRejected && $ch['type'] === 'quiz'): ?>
                            <span style="font-size:0.63rem;color:rgba(218,185,55,0.55);font-weight:600;">กดการ์ดเพื่อเริ่ม →</span>
                            <?php endif; ?>
                        </div>

                        <!-- Token reward -->
                        <div class="ch-flip-back-reward">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" style="width:18px;height:18px;object-fit:contain;filter:drop-shadow(0 0 4px rgba(218,185,55,0.6));">
                            +<?= formatTokens((int)$ch['token_reward']) ?> Token
                        </div>

                        <!-- Title -->
                        <h3 class="ch-flip-back-title"><?= e($ch['title']) ?></h3>

                        <!-- Full description (no clamp) -->
                        <?php if (!empty($ch['description'])): ?>
                        <p class="ch-flip-back-desc"><?= e((string)$ch['description']) ?></p>
                        <?php endif; ?>

                        <!-- Instructions (photo type) -->
                        <?php if ($ch['type'] === 'photo' && !empty($ch['instructions'])): ?>
                        <div class="ch-flip-back-instructions">
                            <p class="ch-flip-back-instructions-label">วิธีส่งหลักฐาน</p>
                            <p class="ch-flip-back-instructions-text"><?= e((string)$ch['instructions']) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Quiz info row -->
                        <?php if ($ch['type'] === 'quiz' && isset($ch['question_count'])): ?>
                        <div class="ch-flip-back-info-row">
                            <svg width="13" height="13" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span><?= $ch['question_count'] ?> คำถาม &bull; ต้องตอบถูกทุกข้อจึงจะได้ Token</span>
                        </div>
                        <?php endif; ?>

                        <!-- End date row -->
                        <?php if ($_ed): ?>
                        <div class="ch-flip-back-info-row">
                            <svg width="13" height="13" fill="none" stroke="#6b6e77" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>สิ้นสุด <?= $_ed ?></span>
                            <?php if ($_daysLeft !== null && $_daysLeft >= 0 && $_daysLeft <= 7): ?>
                            <span style="color:#d2592a;font-weight:600;">&bull; เหลืออีก <?= $_daysLeft === 0 ? 'วันนี้!' : $_daysLeft . ' วัน' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action area -->
                        <div class="ch-flip-action">
                            <?php if ($isRejected): ?>
                            <div class="ch-rejected-msg">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                คุณทำภารกิจไม่ผ่าน &bull;
                            </div>
                            <?php elseif ($ch['type'] === 'photo'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                                  enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.6rem;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="submit_photo">
                                <input type="hidden" name="challenge_id" value="<?= $cid ?>">
                                <input type="file" name="photo" accept="image/*" required class="ch-file-input">
                                <p class="ch-file-hint">JPG, PNG, WebP &bull; สูงสุด 5MB</p>
                                <button type="submit" class="ch-btn-start">ส่งหลักฐาน</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /ch-flip-card -->
        </div><!-- /ch-quest-flip-scene -->
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="ch-empty-board mb-10">ไม่มีภารกิจรอดำเนินการในช่วงนี้</div>
    <?php endif; ?>

    <!-- ── SECTION 2: ดำเนินการแล้ว ── -->
    <?php if ($questsDone): ?>
    <div>
        <button onclick="toggleDoneSection()" class="ch-done-toggle-btn" id="done-section-btn">
            <div class="flex items-center gap-3">
                <div class="ch-section-bar ch-section-bar--muted"></div>
                <span class="ch-section-heading--muted">ภารกิจที่ดำเนินการแล้ว</span>
                <span class="ch-count-badge ch-count-badge--muted"><?= count($questsDone) ?></span>
            </div>
            <svg id="done-chevron" width="18" height="18" fill="none" stroke="#6b6e77" viewBox="0 0 24 24"
                 class="transition-transform flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div id="done-section-grid" style="display:none;">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($questsDone as $ch):
                $cid        = (int)$ch['challenge_id'];
                $isDone     = in_array($ch['my_status'], ['approved','auto_approved'], true);
                $isPending  = $ch['my_status'] === 'pending';
                $isRejected = $ch['my_status'] === 'rejected';
                if ($isRejected)     $stateClass = 'ch-done-card--rejected';
                elseif ($isDone)     $stateClass = 'ch-done-card--done';
                else                 $stateClass = 'ch-done-card--pending';
                if ($isRejected)     $accentClass = 'ch-done-accent--rejected';
                elseif ($isDone)     $accentClass = 'ch-done-accent--done';
                else                 $accentClass = 'ch-done-accent--pending';
                if ($isRejected)     $badgeClass = 'ch-status-badge--rejected';
                elseif ($isDone)     $badgeClass = 'ch-status-badge--done';
                else                 $badgeClass = 'ch-status-badge--pending';
                $badgeText = $isRejected ? 'ไม่ผ่าน' : ($isDone ? 'สำเร็จ' : 'รอตรวจ');
            ?>
            <article class="ch-done-card <?= $stateClass ?>">
                <div class="ch-done-accent <?= $accentClass ?>"></div>
                <div class="ch-done-inner">
                    <div class="ch-done-top-row">
                        <span class="ch-status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        <?php if ($isDone && !empty($ch['my_token_awarded']) && $ch['my_token_awarded'] > 0): ?>
                        <div class="ch-token-earned-chip">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt=""
                                 style="width:16px;height:16px;object-fit:contain;filter:drop-shadow(0 0 3px rgba(81,142,92,0.55));">
                            <span class="ch-token-earned-value">+<?= formatTokens($ch['my_token_awarded']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="ch-done-title"><?= e($ch['title']) ?></h3>
                    <?php if (!empty($ch['end_date'])): ?>
                    <p class="ch-pending-text" style="color:#9ca3af;">
                        สิ้นสุด <?= date('d/m/Y', strtotime((string)$ch['end_date'])) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($isPending): ?>
                    <p class="ch-pending-text">&#9203; รอการตรวจสอบจาก HR/Manager</p>
                    <?php elseif ($isRejected): ?>
                    <p class="ch-rejected-text">&#x2715; ไม่ผ่านเกณฑ์ &bull; ไม่สามารถส่งซ้ำได้</p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function toggleDoneSection() {
        var grid    = document.getElementById('done-section-grid');
        var chevron = document.getElementById('done-chevron');
        var isOpen  = grid && grid.style.display !== 'none';
        if (grid)    grid.style.display      = isOpen ? 'none' : 'block';
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    }
    </script>

    <?php else: ?>
    <div class="ch-empty-board">
        ไม่มีภารกิจเปิดรับในช่วงเวลานี้
    </div>
    <?php endif; ?>

    <?php endif; /* end list view */ ?>

</div><!-- /max-w-7xl -->
</div><!-- /ds-page-inner -->
</div><!-- /ch-challenges-wrap -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
