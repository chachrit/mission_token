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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Flash: Winning Moment (success) or error bar -->
    <?php if ($flash && $flash['type'] === 'success'): ?>
    <div id="win-card" class="mb-8 relative overflow-hidden rounded-2xl px-6 py-10 text-center"
         style="background:linear-gradient(140deg,#091113 0%,#162022 100%);
                border:1.5px solid #dab937;
                box-shadow:0 8px 48px rgba(218,185,55,0.22);">
        <!-- ambient glow -->
        <div style="position:absolute;top:-60px;left:50%;transform:translateX(-50%);
                    width:280px;height:120px;pointer-events:none;
                    background:radial-gradient(ellipse,rgba(218,185,55,0.30),transparent 68%);"></div>
        <div class="relative z-10">
            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token"
                 id="win-token"
                 class="h-20 w-20 mx-auto mb-4"
                 style="animation:win-pop 0.6s cubic-bezier(0.34,1.56,0.64,1) both;">
            <h2 class="text-2xl font-bold mb-2"
                style="color:#f8e769;text-shadow:0 0 28px rgba(218,185,55,0.55);">ยินดีด้วย! 🎉</h2>
            <p class="text-sm leading-relaxed mb-6 max-w-xs mx-auto" style="color:#cecdcd;">
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
    <style>
    @keyframes win-pop {
        0%   { transform:scale(0.2) rotate(-15deg); opacity:0; }
        65%  { transform:scale(1.22) rotate(4deg); }
        100% { transform:scale(1) rotate(0deg); opacity:1; }
    }
    </style>
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
    <div class="mb-6 rounded-xl px-5 py-4 text-sm font-medium
                border border-red-200 bg-red-50 text-red-800">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($dataError): ?>
    <div class="mb-6 rounded-xl border border-[#edc3b2] bg-[#fff1ea] px-5 py-4 text-sm text-j-orange">
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

    <!-- Quiz-specific styles -->
    <style>
    .quiz-progress-track {
        height: 6px;
        background: rgba(255,255,255,0.15);
        border-radius: 999px;
        overflow: hidden;
    }
    .quiz-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #dab937, #f8e769);
        border-radius: 999px;
        transition: width 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .quiz-step { display: none; }
    .quiz-step.active { display: block; }
    .quiz-step.step-enter-fwd  { animation: quiz-step-fwd  0.32s cubic-bezier(0.4,0,0.2,1) both; }
    .quiz-step.step-enter-back { animation: quiz-step-back 0.32s cubic-bezier(0.4,0,0.2,1) both; }
    @keyframes quiz-step-fwd {
        from { opacity: 0; transform: translateX(32px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes quiz-step-back {
        from { opacity: 0; transform: translateX(-32px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    .quiz-opt {
        position: relative;
        display: flex; align-items: center; gap: 0.875rem;
        padding: 0.9rem 1rem;
        border: 1.5px solid #cecdcd;
        border-radius: 10px;
        cursor: pointer;
        transition: border-color 0.18s, background 0.18s, transform 0.1s, box-shadow 0.18s;
        background: #fff;
        user-select: none;
    }
    .quiz-opt:hover {
        border-color: #dab937;
        background: #fdfcdf;
        box-shadow: 0 2px 8px rgba(218,185,55,0.18);
    }
    .quiz-opt:active { transform: scale(0.98); }
    .quiz-opt.selected {
        border-color: #dab937;
        border-width: 2px;
        background: linear-gradient(100deg, #faf0cf 0%, #fef9e0 100%);
        box-shadow: 0 4px 20px rgba(218,185,55,0.38);
        transform: translateX(5px);
    }
    .quiz-opt input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .quiz-opt-letter {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: #eeebe1;
        border: 1.5px solid #cecdcd;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.78rem; font-weight: 700; color: #6b6e77;
        flex-shrink: 0;
        transition: background 0.18s, border-color 0.18s, color 0.18s;
    }
    .quiz-opt.selected .quiz-opt-letter {
        background: #dab937;
        border-color: #dab937;
        color: #091113;
    }
    .quiz-opt .opt-check {
        margin-left: auto;
        flex-shrink: 0;
        opacity: 0;
        transition: opacity 0.18s;
    }
    .quiz-opt.selected .opt-check { opacity: 1; }
    @keyframes token-float {
        0%, 100% { transform: translateY(0); }
        50%       { transform: translateY(-5px); }
    }
    .token-float { animation: token-float 2.2s ease-in-out infinite; }
    .btn-gold:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        pointer-events: none;
    }
    </style>

    <!-- Back breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm text-j-slate">
        <a href="<?= BASE_URL ?>/pages/challenges.php"
           class="inline-flex items-center gap-1.5 hover:text-j-gold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            กลับรายการภารกิจ
        </a>
        <span>›</span>
        <span class="text-j-dark font-medium"><?= e($ch['title']) ?></span>
    </div>

    <div class="max-w-2xl">

        <!-- Mission info strip -->
        <div class="journal-card p-5 mb-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
                <span class="badge text-xs font-semibold mb-1.5 inline-block"
                      style="background:#091113; color:#dab937;">📝 Quiz Mission</span>
                <h2 class="text-lg font-semibold text-j-dark leading-snug"><?= e($ch['title']) ?></h2>
                <p class="mt-1 text-sm text-j-slate leading-relaxed"><?= e((string)$ch['description']) ?></p>
            </div>
            <div class="flex flex-col items-center flex-shrink-0 text-center pl-4 border-l border-j-silver">
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token"
                     class="h-12 w-12 token-float">
                <p class="text-base font-bold text-j-gold mt-1">+<?= formatTokens((int)$ch['token_reward']) ?></p>
                <p class="text-[10px] text-j-slate uppercase tracking-wider">Token</p>
            </div>
        </div>

        <!-- Main quiz card -->
        <div class="rounded-2xl overflow-hidden" style="border:1px solid #cecdcd; box-shadow:0 4px 24px rgba(9,17,19,0.10);">

            <!-- Dark progress header -->
            <div class="px-6 py-4" style="background:#091113;">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-widest" style="color:#dab937;">
                        Mission Progress
                    </span>
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-mono" style="color:#f8e769;">
                            ข้อที่ <span id="q-current">1</span> / <?= $totalQ ?>
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full"
                              style="background:rgba(218,185,55,0.14);color:#f8e769;border:1px solid rgba(218,185,55,0.3);">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" style="width:12px;height:12px;">
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
            <div style="background:#fdfcdf;">
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
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold flex-shrink-0 mt-0.5"
                                  style="background:#dab937; color:#091113;"><?= $qi + 1 ?></span>
                            <p class="text-base font-semibold text-j-dark leading-snug">
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
                                <span class="text-sm text-j-dark flex-1"><?= e($text) ?></span>
                                <svg class="opt-check w-4 h-4" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Step navigation -->
                        <div class="flex items-center gap-3 pt-4 border-t border-[#e6e2d6]">
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
                    <div class="px-6 py-3 flex items-center gap-2 text-xs text-j-slate border-t border-[#e6e2d6]"
                         style="background:#eeebe1;">
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
         style="display:none; position:fixed; inset:0; z-index:9999;
                background:rgba(9,17,19,0.72); backdrop-filter:blur(4px);
                align-items:center; justify-content:center;">
        <div style="background:#fdfcdf; border:1px solid #cecdcd;
                    border-radius:16px; padding:2.25rem 2.75rem;
                    box-shadow:0 12px 48px rgba(9,17,19,0.28);
                    text-align:center; max-width:320px; width:90%;">

            <!-- Orbit spinner -->
            <div style="position:relative; width:64px; height:64px; margin:0 auto 1.25rem;">
                <div id="qpm-orbit"
                     style="position:absolute; inset:0; border-radius:50%;
                            border:3px solid #e6e2d6;
                            border-top-color:#dab937;
                            animation:qpm-spin 0.9s linear infinite;"></div>
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt=""
                     style="position:absolute; inset:0; margin:auto;
                            width:32px; height:32px;
                            animation:qpm-pulse 0.9s ease-in-out infinite;">
            </div>

            <p style="font-size:1rem; font-weight:600; color:#091113; margin-bottom:0.4rem;">
                กำลังตรวจสอบคำตอบ…
            </p>
            <!-- animated dots -->
            <p style="font-size:0.8rem; color:#6b6e77; letter-spacing:0.04em;"
               id="qpm-dots">กรุณารอสักครู่</p>
        </div>
    </div>

    <style>
    @keyframes qpm-spin  { to { transform: rotate(360deg); } }
    @keyframes qpm-pulse { 0%,100% { transform:scale(1); opacity:1; }
                           50%      { transform:scale(1.15); opacity:0.75; } }
    @keyframes qpm-fadein { from { opacity:0; transform:scale(0.94); }
                            to   { opacity:1; transform:scale(1); } }
    #quiz-processing-modal[style*="flex"] > div {
        animation: qpm-fadein 0.22s ease-out both;
    }
    </style>

    <script>
    (function () {
        const totalQ = <?= (int)$totalQ ?>;

        /* ── Step navigation ─────────────────────────────── */
        window.quizGoStep = function (idx) {
            const currentStep = document.querySelector('.quiz-step.active');
            const currentIdx  = currentStep ? parseInt(currentStep.dataset.step, 10) : -1;
            const forward     = idx > currentIdx;

            if (currentStep) {
                currentStep.classList.remove('active', 'step-enter-fwd', 'step-enter-back');
            }

            const step = document.getElementById('step-' + idx);
            step.classList.add('active', forward ? 'step-enter-fwd' : 'step-enter-back');
            setTimeout(function () {
                step.classList.remove('step-enter-fwd', 'step-enter-back');
            }, 340);

            // Update counter + progress bar
            document.getElementById('q-current').textContent = idx + 1;
            const pct = Math.round(((idx + 1) / totalQ) * 100);
            document.getElementById('quiz-progress').style.width = pct + '%';

            // Re-enable next/submit if this step was already answered
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

                // Deselect all options in this question
                stepEl.querySelectorAll('.quiz-opt').forEach(function (l) {
                    l.classList.remove('selected');
                });

                // Select clicked option
                radio.checked = true;
                this.classList.add('selected');

                // Enable next / submit button
                const nb = document.getElementById('next-' + stepIdx);
                if (nb) nb.disabled = false;
                const sb = document.getElementById('quiz-submit-btn');
                if (sb && stepIdx === totalQ - 1) sb.disabled = false;
            });
        });

        /* ── Processing modal helper ─────────────────────── */
        function showProcessingModal() {
            const modal = document.getElementById('quiz-processing-modal');
            modal.style.display = 'flex';
            // Animate dots: cycle through "กรุณารอสักครู่" → "." → ".." → "..."
            const dotsEl = document.getElementById('qpm-dots');
            const base   = 'กรุณารอสักครู่';
            let tick = 0;
            return setInterval(function () {
                tick = (tick + 1) % 4;
                dotsEl.textContent = base + '.'.repeat(tick);
            }, 280);
        }

        /* ── Submit: processing modal → confetti → submit ── */
        document.getElementById('quiz-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const form    = this;
            const dotsTimer = showProcessingModal();

            function doSubmit() {
                clearInterval(dotsTimer);
                form.submit();
            }

            function fireAndSubmit() {
                confetti({
                    particleCount : 70,
                    spread        : 65,
                    origin        : { y: 0.72 },
                    colors        : ['#dab937', '#f8e769', '#c9a830', '#fdfcdf', '#3a3e43'],
                    scalar        : 0.85,
                    ticks         : 130,
                    gravity       : 1.3,
                });
                setTimeout(doSubmit, 480);
            }

            // Show processing for ~1 s, then fire confetti + submit
            setTimeout(function () {
                if (typeof confetti !== 'undefined') {
                    fireAndSubmit();
                } else {
                    const s   = document.createElement('script');
                    s.src     = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js';
                    s.onload  = fireAndSubmit;
                    s.onerror = doSubmit;
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

        // Split challenges into 2 groups
        $questsAvailable = array_values(array_filter($challenges, fn($c) =>
            $c['my_status'] === null || $c['my_status'] === 'rejected'
        ));
        $questsDone = array_values(array_filter($challenges, fn($c) =>
            in_array($c['my_status'], ['approved','auto_approved','pending'], true)
        ));
    ?>

    <style>
    .quest-card {
        background: #fdfcdf;
        border: 1px solid #cecdcd;
        border-left: 4px solid #cecdcd;
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
        transition: transform 0.22s ease, box-shadow 0.22s ease;
    }
    .quest-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 28px 60px rgba(9,17,19,0.30), 0 0 36px rgba(218,185,55,0.20);
    }
    .quest-card.quest-done:hover     { box-shadow: 0 28px 60px rgba(9,17,19,0.28), 0 0 32px rgba(81,142,92,0.30); }
    .quest-card.quest-rejected:hover { box-shadow: 0 28px 60px rgba(9,17,19,0.28), 0 0 32px rgba(210,89,42,0.28); }
    .quest-card.quest-open:hover,
    .quest-card.quest-pending:hover  { box-shadow: 0 28px 60px rgba(9,17,19,0.30), 0 0 36px rgba(218,185,55,0.35); }
    /* Left-border state colours */
    .quest-open     { border-left-color: #dab937; }
    .quest-pending  { border-left-color: #c9a830; }
    .quest-rejected { border-left-color: #d2592a; }
    .quest-done     { border-left-color: #518e5c; }
    /* Universal card header (collapsible) */
    .quest-card-header {
        cursor: pointer;
        user-select: none;
    }
    /* Hover highlight on header */
    .quest-card-header:hover {
        background: rgba(9,17,19,0.05);
    }
    /* Token chip */
    .token-reward-chip {
        display: inline-flex; align-items: center; gap: 0.4rem;
        border-radius: 10px; padding: 0.4rem 0.7rem;
        background: #091113;
        border: 1px solid rgba(218,185,55,0.25);
        box-shadow: 0 0 10px rgba(218,185,55,0.30);
        transition: box-shadow 0.2s;
        white-space: nowrap;
    }
    .quest-card.quest-open:hover .token-reward-chip,
    .quest-card.quest-pending:hover .token-reward-chip {
        box-shadow: 0 0 18px rgba(218,185,55,0.55);
    }
    /* Lock border-left colour for non-actionable states */
    .quest-card.quest-rejected,
    .quest-card.quest-rejected:hover { border-left-color: #d2592a !important; }
    .quest-card.quest-done,
    .quest-card.quest-done:hover    { border-left-color: #518e5c !important; }
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    /* ── Token reward badge (animated) ── */
    .token-reward-badge {
        display: inline-flex; align-items: center; gap: 0.45rem;
        background: linear-gradient(135deg,#1c1400 0%,#0f0d00 100%);
        border: 1px solid rgba(218,185,55,0.50);
        border-radius: 12px;
        padding: 0.35rem 0.72rem 0.35rem 0.45rem;
        box-shadow: 0 0 14px rgba(218,185,55,0.25), inset 0 1px 0 rgba(248,231,105,0.07);
        position: relative; overflow: hidden; flex-shrink: 0;
    }
    .token-reward-badge::after {
        content: '';
        position: absolute; top: 0; left: -120%; width: 80%; height: 100%;
        background: linear-gradient(90deg,transparent,rgba(248,231,105,0.12),transparent);
        animation: trb-shine 3.5s ease-in-out infinite;
        pointer-events: none;
    }
    @keyframes trb-shine {
        0%, 25% { left: -120%; }
        65%, 100% { left: 160%; }
    }
    .token-coin-anim {
        animation: tca-pulse 2.4s ease-in-out infinite;
        filter: drop-shadow(0 0 5px rgba(218,185,55,0.80));
    }
    @keyframes tca-pulse {
        0%, 100% { transform: scale(1);    filter: drop-shadow(0 0 4px rgba(218,185,55,0.70)); }
        50%       { transform: scale(1.18); filter: drop-shadow(0 0 11px rgba(248,231,105,1.0)); }
    }
    </style>

    <?php if ($challenges): ?>

    <!-- Quest Board header -->
    <div class="mb-8 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-20 relative overflow-hidden"
         style="background:linear-gradient(135deg,#fdfcdf 0%,#faf0cf 60%,#eeebe1 100%);
                border-bottom:1px solid #e0ddd4;">
        <!-- subtle dot grid texture -->
        <div style="position:absolute;inset:0;pointer-events:none;
                    background-image:radial-gradient(rgba(9,17,19,0.05) 1px, transparent 1px);
                    background-size:22px 22px;"></div>
        <!-- ambient gold glow top-right -->
        <div style="position:absolute;top:-100px;right:-100px;width:580px;height:480px;pointer-events:none;
                    background:radial-gradient(circle,rgba(218,185,55,0.24) 0%,transparent 65%);"></div>
        <div class="relative max-w-7xl mx-auto flex items-center justify-between gap-8">
            <div>
                <p class="text-[14px] font-bold uppercase tracking-widest mb-2.5" style="color:#c9a830; letter-spacing:0.24em;">&#9876; Quest Board</p>
                <h1 class="text-5xl font-bold leading-tight" style="color:#091113;">ภารกิจทั้งหมด</h1>
                <p class="text-base mt-2.5" style="color:#6b6e77;">เลือกภารกิจที่ต้องการ แล้วส่งหลักฐานเพื่อรับ Token</p>
            </div>
            <div class="flex flex-col items-end gap-3 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="w-7 h-7">
                    <p class="text-xl font-bold" style="color:#c9a830;">
                        <?= $_done ?><span class="font-normal text-base" style="color:#6b6e77;"> / <?= $_total ?> ภารกิจสำเร็จ</span>
                    </p>
                </div>
                <div style="width:180px;height:8px;background:rgba(9,17,19,0.08);border-radius:99px;overflow:hidden;">
                    <div style="height:100%;border-radius:99px;
                                background:linear-gradient(90deg,#dab937,#f8e769);
                                width:<?= $_total > 0 ? round($_done / $_total * 100) : 0 ?>%;
                                box-shadow:0 0 10px rgba(218,185,55,0.45);"></div>
                </div>
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b6e77;"><?= $_total > 0 ? round($_done / $_total * 100) : 0 ?>% Complete</p>
            </div>
        </div>
    </div>

    <!-- ── SECTION 1: ภารกิจรอคุณอยู่ ── -->
    <?php if ($questsAvailable): ?>
    <div class="mb-12">
        <div class="flex items-center gap-3 mb-6">
            <div style="width:4px;height:28px;background:linear-gradient(180deg,#dab937,#c9a830);border-radius:999px;flex-shrink:0;"></div>
            <h2 style="font-size:1.15rem;font-weight:700;color:#091113;margin:0;">ภารกิจรอคุณอยู่</h2>
            <span style="font-size:0.72rem;font-weight:700;color:#091113;background:#dab937;border-radius:999px;padding:0.2rem 0.65rem;"><?= count($questsAvailable) ?></span>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($questsAvailable as $ch):
            $cid = (int)$ch['challenge_id'];
            $myStatus = $ch['my_status'];
            $isRejected = $myStatus === 'rejected';
        ?>
        <article style="background:#fff;border:1.5px solid <?= $isRejected ? '#f3c4b8' : '#e0ddd4' ?>;border-radius:20px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 2px 12px rgba(9,17,19,0.06);transition:transform 0.26s cubic-bezier(0.34,1.3,0.64,1),box-shadow 0.26s ease,border-color 0.18s;"
                 onmouseover="this.style.transform='translateY(-12px) scale(1.03)';this.style.boxShadow='<?= $isRejected ? '0 32px 64px rgba(9,17,19,0.20),0 0 28px rgba(210,89,42,0.28)' : '0 32px 64px rgba(9,17,19,0.20),0 0 32px rgba(218,185,55,0.35)' ?>';this.style.borderColor='<?= $isRejected ? '#d2592a' : '#dab937' ?>'"
                 onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(9,17,19,0.06)';this.style.borderColor='<?= $isRejected ? '#f3c4b8' : '#e0ddd4' ?>'">
            <div style="height:4px;background:<?= $isRejected ? '#d2592a' : 'linear-gradient(90deg,#dab937,#f8e769)' ?>;"></div>
            <div style="padding:1.25rem;display:flex;flex-direction:column;gap:0.875rem;flex:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                    <span style="font-size:0.65rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;padding:0.22rem 0.65rem;border-radius:6px;background:#091113;color:#dab937;">
                        <?= $ch['type'] === 'quiz' ? 'Quiz' : 'Photo' ?>
                    </span>
                    <div class="token-reward-badge">
                        <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="token-coin-anim" style="width:26px;height:26px;object-fit:contain;">
                        <div style="display:flex;flex-direction:column;line-height:1.1;">
                            <span style="font-size:0.95rem;font-weight:900;color:#f8e769;text-shadow:0 0 8px rgba(248,231,105,0.6);">+<?= formatTokens((int)$ch['token_reward']) ?></span>
                            <span style="font-size:0.55rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:rgba(218,185,55,0.75);">Token</span>
                        </div>
                    </div>
                </div>
                <div style="flex:1;">
                    <h3 style="font-size:0.95rem;font-weight:700;color:#091113;margin:0 0 0.35rem;line-height:1.35;"><?= e($ch['title']) ?></h3>
                    <?php if (!empty($ch['description'])): ?>
                    <p style="font-size:0.78rem;color:#6b6e77;margin:0;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= e((string)$ch['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($ch['type'] === 'quiz' && isset($ch['question_count'])): ?>
                    <p style="font-size:0.7rem;color:#6b6e77;margin:0.4rem 0 0;"><?= $ch['question_count'] ?> คำถาม &bull; ตอบถูกทุกข้อเพื่อรับ Token</p>
                    <?php endif; ?>
                </div>
                <div style="padding-top:0.75rem;border-top:1px solid #ece9e0;">
                    <?php if ($isRejected): ?>
                    <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;font-weight:600;color:#991b1b;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        ไม่ผ่านเกณฑ์ &bull; ไม่สามารถส่งซ้ำ
                    </div>
                    <?php elseif ($ch['type'] === 'quiz'): ?>
                    <a href="<?= BASE_URL ?>/pages/challenges.php?id=<?= $cid ?>"
                       style="display:flex;align-items:center;justify-content:center;gap:0.4rem;background:#dab937;color:#091113;font-size:0.82rem;font-weight:700;padding:0.6rem 1rem;border-radius:10px;text-decoration:none;transition:background 0.15s;"
                       onmouseover="this.style.background='#c9a830'" onmouseout="this.style.background='#dab937'">
                        เริ่มทำ Quiz
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.6rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="submit_photo">
                        <input type="hidden" name="challenge_id" value="<?= $cid ?>">
                        <?php if (!empty($ch['instructions'])): ?>
                        <p style="font-size:0.72rem;color:#6b6e77;margin:0;background:#faf0cf;border-radius:8px;padding:0.5rem 0.7rem;"><?= e((string)$ch['instructions']) ?></p>
                        <?php endif; ?>
                        <input type="file" name="photo" accept="image/*" required
                               style="font-size:0.75rem;color:#091113;background:#f9f9f9;border:1.5px solid #e0ddd4;border-radius:8px;padding:0.4rem 0.6rem;width:100%;cursor:pointer;box-sizing:border-box;">
                        <p style="font-size:0.65rem;color:#9ca3af;margin:0;">JPG, PNG, WebP &bull; สูงสุด 5MB</p>
                        <button type="submit"
                                style="background:#dab937;color:#091113;font-size:0.82rem;font-weight:700;padding:0.6rem 1rem;border-radius:10px;border:none;cursor:pointer;transition:background 0.15s;"
                                onmouseover="this.style.background='#c9a830'" onmouseout="this.style.background='#dab937'">
                            ส่งหลักฐาน
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div style="border-radius:16px;border:1.5px dashed #d4d0c8;padding:3rem 1rem;text-align:center;font-size:0.85rem;color:#6b6e77;margin-bottom:2.5rem;background:#fff;">
        ไม่มีภารกิจรอดำเนินการในช่วงนี้
    </div>
    <?php endif; ?>

    <!-- ── SECTION 2: ดำเนินการแล้ว ── -->
    <?php if ($questsDone): ?>
    <div>
        <button onclick="toggleDoneSection()"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;background:transparent;border:none;cursor:pointer;padding:0;margin-bottom:1rem;"
                id="done-section-btn">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:4px;height:28px;background:#cecdcd;border-radius:999px;flex-shrink:0;"></div>
                <span style="font-size:1.05rem;font-weight:600;color:#6b6e77;">ภารกิจที่ดำเนินการแล้ว</span>
                <span style="font-size:0.72rem;font-weight:700;color:#6b6e77;background:#e6e2d6;border-radius:999px;padding:0.2rem 0.65rem;"><?= count($questsDone) ?></span>
            </div>
            <svg id="done-chevron" width="18" height="18" fill="none" stroke="#6b6e77" viewBox="0 0 24 24" style="transition:transform 0.22s;flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div id="done-section-grid" style="display:none;">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($questsDone as $ch):
                $cid = (int)$ch['challenge_id'];
                $myStatus = $ch['my_status'];
                $isDone   = in_array($myStatus, ['approved','auto_approved'], true);
                $isPending= $myStatus === 'pending';
                $accentClr = $isDone ? '#518e5c' : '#c9a830';
                $bgClr     = $isDone ? '#f0fdf4' : '#fefce8';
                $borderClr = $isDone ? '#bbf7d0' : '#fde68a';
            ?>
            <article style="background:<?= $bgClr ?>;border:1.5px solid <?= $borderClr ?>;border-radius:20px;display:flex;flex-direction:column;overflow:hidden;opacity:0.85;box-shadow:0 1px 6px rgba(9,17,19,0.04);">
                <div style="height:4px;background:<?= $accentClr ?>;"></div>
                <div style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:0.6rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                        <span style="font-size:0.65rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;padding:0.22rem 0.65rem;border-radius:6px;background:<?= $isDone ? '#dcfce7' : '#fef9c3' ?>;color:<?= $isDone ? '#166534' : '#854d0e' ?>;">
                            <?= $isDone ? 'สำเร็จ' : 'รอตรวจ' ?>
                        </span>
                        <?php if ($isDone && !empty($ch['my_token_awarded']) && $ch['my_token_awarded'] > 0): ?>
                        <div style="display:inline-flex;align-items:center;gap:0.4rem;background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:0.22rem 0.55rem 0.22rem 0.38rem;">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" style="width:16px;height:16px;object-fit:contain;filter:drop-shadow(0 0 3px rgba(81,142,92,0.55));">
                            <span style="font-size:0.85rem;font-weight:800;color:#166534;">+<?= formatTokens($ch['my_token_awarded']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3 style="font-size:0.9rem;font-weight:600;color:#3a3e43;margin:0;line-height:1.3;"><?= e($ch['title']) ?></h3>
                    <?php if ($isPending): ?>
                    <p style="font-size:0.72rem;color:#92400e;margin:0;">&#9203; รอการตรวจสอบจาก HR/Manager</p>
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
        if (grid)    grid.style.display    = isOpen ? 'none' : 'block';
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    }
    </script>

    <?php else: ?>
    <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-16 text-center text-sm text-j-slate">
        ไม่มีภารกิจเปิดรับในช่วงเวลานี้
    </div>
    <?php endif; ?>

    <?php endif; /* end list view */ ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
