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
            setFlash('error', 'ไฟล์ใหญ่เกิน 20MB');
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

    // ── STRAVA submission ─────────────────────────────────────
    if ($action === 'submit_strava') {
        require_once __DIR__ . '/../includes/strava.php';

        if ($challenge['type'] !== 'strava') {
            setFlash('error', 'ภารกิจนี้ไม่ใช่ประเภท Strava');
            redirect(BASE_URL . '/pages/challenges.php');
        }

        try {
            if (!isStravaConnected($employeeId)) {
                setFlash('error', 'กรุณาเชื่อมต่อ Strava ก่อนส่งภารกิจ');
                redirect(BASE_URL . '/pages/challenges.php');
            }

            $condition = [];
            if (!empty($challenge['strava_condition'])) {
                $condition = json_decode((string)$challenge['strava_condition'], true) ?? [];
            }

            $afterTs  = (int)strtotime(date('Y-m-d', strtotime((string)$challenge['start_date'])) . ' 00:00:00');
            $beforeTs = (int)strtotime(date('Y-m-d', strtotime((string)$challenge['end_date']))   . ' 23:59:59');

            $matched = checkStravaCondition($employeeId, $condition, $afterTs, $beforeTs);

            $status    = $matched ? 'auto_approved' : 'rejected';
            $awarded   = $matched ? (int)$challenge['token_reward'] : 0;
            $auditNote = $matched
                ? json_encode(['id' => $matched['id'] ?? 0, 'name' => mb_substr($matched['name'] ?? '', 0, 80)], JSON_UNESCAPED_UNICODE)
                : null;

            $pdo->beginTransaction();
            $pdo->prepare("
                INSERT INTO challenge_submissions
                    (employee_id, challenge_id, submission_type, photo_path, status, token_awarded)
                VALUES (?, ?, 'strava', ?, ?, ?)
            ")->execute([$employeeId, $challengeId, $auditNote, $status, $awarded]);

            // Re-query ID (pdo_sqlsrv lastInsertId unreliable)
            $subId = (int)$pdo->query("
                SELECT TOP 1 submission_id FROM challenge_submissions
                WHERE employee_id = {$employeeId} AND challenge_id = {$challengeId}
                ORDER BY submission_id DESC
            ")->fetchColumn();

            $pdo->commit();

            if ($matched) {
                awardTokens($employeeId, $awarded, 'quiz_reward', $subId, 'Strava: ' . $challenge['title']);
                $actName = mb_substr($matched['name'] ?? 'กิจกรรม', 0, 60);
                setFlash('success', "ยินดีด้วย! พบกิจกรรม \"{$actName}\" ที่ผ่านเงื่อนไข ได้รับ +{$awarded} Token 🎉");
            } else {
                setFlash('error', 'ไม่พบกิจกรรม Strava ที่ตรงเงื่อนไขในช่วงวันที่ภารกิจ กรุณาบันทึกกิจกรรมแล้วลองใหม่');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[MissionToken] strava submit error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาดขณะตรวจสอบ Strava: ' . $e->getMessage());
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

        // Pre-parse strava condition
        if ($ch['type'] === 'strava' && !empty($ch['strava_condition'])) {
            $ch['_sc'] = json_decode((string)$ch['strava_condition'], true) ?? [];
        }
    }
    unset($ch);

    // Strava connection status for this employee (used in card UI)
    require_once __DIR__ . '/../includes/strava.php';
    $stravaConnected = isStravaConnected($employeeId);

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
    <?php endif; ?>

    <!-- Strava loading overlay -->
    <div id="strava-loading-overlay" class="ch-strava-loading-overlay" role="status" aria-live="polite">
        <svg class="ch-strava-loading-logo" viewBox="0 0 24 24" width="44" height="44" fill="#FC4C02">
            <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
        </svg>
        <div class="ch-strava-spinner"></div>
        <div style="text-align:center;">
            <p class="ch-strava-loading-title">กำลังตรวจสอบกิจกรรม Strava</p>
            <p class="ch-strava-loading-sub" id="strava-loading-sub">กำลังดึงข้อมูลกิจกรรม อาจใช้เวลา 15–30 วินาที...</p>
        </div>
    </div>
    <script>
    function submitStravaForm(formId, btn) {
        var f = document.getElementById(formId);
        if (!f) return;
        if (btn) btn.disabled = true;
        var overlay = document.getElementById('strava-loading-overlay');
        var sub     = document.getElementById('strava-loading-sub');
        if (overlay) overlay.classList.add('is-active');
        var msgs = [
            [8000,  'กำลังค้นหากิจกรรมที่ตรงเงื่อนไข...'],
            [20000, 'ใช้เวลานานกว่าปกติ Strava API อาจช้า กรุณารอสักครู่...'],
            [40000, 'เกือบเสร็จแล้ว กรุณาอย่าปิดหน้านี้...'],
        ];
        msgs.forEach(function(m) {
            setTimeout(function() { if (sub) sub.textContent = m[1]; }, m[0]);
        });
        f.submit();
    }
    </script>

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

        // Strava rejected → ยังทำซ้ำได้ → อยู่ในกลุ่ม available
        $questsAvailable = array_values(array_filter($challenges, fn($c) =>
            $c['my_status'] === null ||
            ($c['type'] === 'strava' && $c['my_status'] === 'rejected')
        ));
        $questsDone      = array_values(array_filter($challenges, fn($c) =>
            in_array($c['my_status'], ['approved','auto_approved','pending'], true) ||
            ($c['type'] !== 'strava' && $c['my_status'] === 'rejected')
        ));
    ?>

    <?php if ($challenges): ?>

    <!-- Quest Board hero header -->
    <div class="mb-8 relative overflow-hidden ch-board-hero ch-board-hero-pad">
        <div class="ch-board-dot-grid"></div>
        <div class="ch-board-glow"></div>
        <div class="relative max-w-7xl mx-auto ch-board-hero-inner">
            <div>
                <p class="text-[14px] font-bold uppercase mb-2.5 ch-board-hero-label">&#9876; Quest Board</p>
                <h1 class="font-bold leading-tight ch-hero-title ch-hero-title-size">ภารกิจทั้งหมด</h1>
                <p class="text-base mt-2.5 ch-hero-sub">เลือกภารกิจที่ต้องการ แล้วส่งหลักฐานเพื่อรับ Token</p>
            </div>
            <div class="ch-board-progress-block">
                <div class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="w-7 h-7">
                    <p class="text-xl font-bold" style="color:#dab937;">
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
                            <?php if ($ch['type'] === 'strava'): ?>
                            <span class="ch-type-badge" style="background:rgba(252,76,2,0.18);color:#FC4C02;border-color:rgba(252,76,2,0.35);">&#127939; Strava</span>
                            <?php elseif ($ch['type'] === 'quiz'): ?>
                            <span class="ch-type-badge">Quiz</span>
                            <?php else: ?>
                            <span class="ch-type-badge">Photo</span>
                            <?php endif; ?>
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
                     onclick="openQuizModal(<?= $cid ?>)" style="cursor:pointer;"
                     <?php elseif ($ch['type'] === 'strava'): ?>
                     onclick="openStravaModal(<?= $cid ?>)" style="cursor:pointer;"
                     <?php endif; ?>>
                    <div class="ch-quest-accent-bar <?= $isRejected ? 'ch-quest-accent-bar--rejected' : '' ?>"
                         <?php if ($ch['type'] === 'strava'): ?>style="background:linear-gradient(90deg,#FC4C02,#e04400);"<?php endif; ?>></div>
                    <div class="ch-flip-back-body">
                        <!-- Header: type badge -->
                        <div class="ch-flip-back-header">
                            <?php if ($ch['type'] === 'strava'): ?>
                            <span class="ch-type-badge" style="background:rgba(252,76,2,0.18);color:#FC4C02;border-color:rgba(252,76,2,0.35);">&#127939; Strava</span>
                            <?php elseif ($ch['type'] === 'quiz'): ?>
                            <span class="ch-type-badge">Quiz</span>
                            <?php else: ?>
                            <span class="ch-type-badge">Photo</span>
                            <?php endif; ?>
                            <?php if (!$isRejected && $ch['type'] === 'quiz'): ?>
                            <span style="font-size:0.63rem;color:rgba(218,185,55,0.55);font-weight:600;">กดการ์ดเพื่อดูรายละเอียด →</span>
                            <?php elseif ($ch['type'] === 'strava'): ?>
                            <span style="font-size:0.63rem;color:rgba(252,76,2,0.55);font-weight:600;">กดการ์ดเพื่อดูรายละเอียด →</span>
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

                        <!-- Strava condition summary -->
                        <?php if ($ch['type'] === 'strava' && !empty($ch['_sc'])): ?>
                        <?php $sc = $ch['_sc']; ?>
                        <div class="ch-flip-back-instructions" style="background:rgba(252,76,2,0.06);border-color:rgba(252,76,2,0.22);">
                            <p class="ch-flip-back-instructions-label" style="color:rgba(252,76,2,0.8)">เงื่อนไขกิจกรรม</p>
                            <p class="ch-flip-back-instructions-text">
                                <?= e($sc['sport_type'] ?? 'Run') ?>
                                <?php if (!empty($sc['min_distance'])): ?> &bull; &ge;<?= number_format($sc['min_distance']/1000,1) ?>กม<?php endif; ?>
                                <?php if (!empty($sc['min_moving_time'])): ?> &bull; &ge;<?= floor($sc['min_moving_time']/60) ?>นาที<?php endif; ?>
                                <?php if (!empty($sc['min_elevation'])): ?> &bull; ความสูง&ge;<?= $sc['min_elevation'] ?>ม<?php endif; ?>
                            </p>
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
                            <?php if ($isRejected && $ch['type'] !== 'strava'): ?>
                            <div class="ch-rejected-msg">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                คุณทำภารกิจไม่ผ่าน &bull;
                            </div>
                            <?php elseif ($ch['type'] === 'strava'): ?>
                            <?php if (!$stravaConnected): ?>
                            <p style="font-size:0.73rem;color:#d2592a;margin:0;">⚠️ ยังไม่ได้เชื่อมต่อ Strava</p>
                            <?php elseif ($isRejected): ?>
                            <p style="font-size:0.73rem;color:rgba(252,76,2,0.7);margin:0;">ไม่พบกิจกรรมที่ตรงเงื่อนไข &bull; ลองใหม่ได้</p>
                            <?php else: ?>
                            <p style="font-size:0.73rem;color:rgba(252,76,2,0.5);margin:0;">กดการ์ดเพื่อเริ่มทำภารกิจ</p>
                            <?php endif; ?>
                            <?php elseif ($ch['type'] === 'photo'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                                  enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.6rem;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="submit_photo">
                                <input type="hidden" name="challenge_id" value="<?= $cid ?>">
                                <input type="file" name="photo" accept="image/*" required class="ch-file-input">
                                <p class="ch-file-hint">JPG, PNG, WebP &bull; สูงสุด 20MB</p>
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

    <?php
    // Collect Strava challenge data for modal JS
    $stravaModalData = [];
    foreach ($questsAvailable as $_sch) {
        if ($_sch['type'] !== 'strava') continue;
        $_cid  = (int)$_sch['challenge_id'];
        $_ets  = $_sch['end_date'] ? strtotime((string)$_sch['end_date']) : null;
        $_ed2  = $_ets ? date('d/m/Y', $_ets) : null;
        $_dl2  = $_ets ? (int)(new DateTime('today'))->diff(new DateTime(date('Y-m-d', $_ets)))->days
                         * ((new DateTime('today') <= new DateTime(date('Y-m-d', $_ets))) ? 1 : -1) : null;
        $stravaModalData[$_cid] = [
            'title'    => $_sch['title'],
            'desc'     => (string)($_sch['description'] ?? ''),
            'token'    => (int)$_sch['token_reward'],
            'rejected' => $_sch['my_status'] === 'rejected',
            'sc'       => $_sch['_sc'] ?? [],
            'ed'       => $_ed2,
            'daysLeft' => $_dl2,
        ];
    }
    ?>
    <script>
    var _stravaModalData = <?= json_encode($stravaModalData, JSON_UNESCAPED_UNICODE) ?>;
    var _stravaConnected = <?= $stravaConnected ? 'true' : 'false' ?>;
    <?php
    // Collect Quiz challenge data for modal JS
    $quizModalData = [];
    foreach ($questsAvailable as $_qch) {
        if ($_qch['type'] !== 'quiz') continue;
        if ($_qch['my_status'] !== null) continue; // already attempted — won't open modal
        $_cid2 = (int)$_qch['challenge_id'];
        $_ets2 = $_qch['end_date'] ? strtotime((string)$_qch['end_date']) : null;
        $_ed3  = $_ets2 ? date('d/m/Y', $_ets2) : null;
        $_dl3  = $_ets2 ? (int)(new DateTime('today'))->diff(new DateTime(date('Y-m-d', $_ets2)))->days
                          * ((new DateTime('today') <= new DateTime(date('Y-m-d', $_ets2))) ? 1 : -1) : null;
        $quizModalData[$_cid2] = [
            'title'    => $_qch['title'],
            'desc'     => (string)($_qch['description'] ?? ''),
            'token'    => (int)$_qch['token_reward'],
            'qcount'   => (int)($_qch['question_count'] ?? 0),
            'ed'       => $_ed3,
            'daysLeft' => $_dl3,
            'url'      => BASE_URL . '/pages/challenges.php?id=' . $_cid2,
        ];
    }
    ?>
    var _quizModalData = <?= json_encode($quizModalData, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <style>
    @keyframes _mFadeIn  { from{opacity:0} to{opacity:1} }
    @keyframes _mFadeOut { from{opacity:1} to{opacity:0} }
    @keyframes _mCardIn {
        0%   { opacity:0; transform:perspective(700px) scale(0.76) translateY(44px) rotateX(20deg); }
        60%  { opacity:1; transform:perspective(700px) scale(1.03) translateY(-5px)  rotateX(-2deg); }
        100% { opacity:1; transform:perspective(700px) scale(1)    translateY(0)     rotateX(0deg);  }
    }
    @keyframes _mCardOut {
        from { opacity:1; transform:scale(1)    translateY(0);  }
        to   { opacity:0; transform:scale(0.84) translateY(24px); }
    }
    .modal-overlay-in  { animation:_mFadeIn  260ms ease            forwards; }
    .modal-overlay-out { animation:_mFadeOut 180ms ease            forwards; }
    .modal-card-in     { animation:_mCardIn  440ms cubic-bezier(0.34,1.56,0.64,1) forwards; }
    .modal-card-out    { animation:_mCardOut 170ms ease-in         forwards; }
    </style>

    <!-- ── Strava Detail Modal ── -->
    <div id="strava-modal"
         style="display:none; position:fixed; inset:0; z-index:1000;
                background:rgba(0,0,0,0.78); backdrop-filter:blur(6px);
                align-items:center; justify-content:center; padding:1rem;"
         onclick="if(event.target===this)closeStravaModal()">
        <div id="strava-modal-card" style="background:#0f1416; border:1px solid rgba(252,76,2,0.28); border-radius:20px;
                    max-width:420px; width:100%; max-height:90vh; overflow-y:auto;
                    box-shadow:0 24px 60px rgba(0,0,0,0.65), 0 0 0 1px rgba(252,76,2,0.12);">
            <!-- Modal header -->
            <div style="padding:1.1rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.07);
                        display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="#FC4C02">
                        <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                    </svg>
                    <span style="font-size:0.68rem; font-weight:700; color:rgba(252,76,2,0.9);
                                 letter-spacing:0.08em; text-transform:uppercase;">Strava Mission</span>
                </div>
                <button onclick="closeStravaModal()"
                        style="width:28px; height:28px; border-radius:50%;
                               background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
                               color:#6b6e77; cursor:pointer; font-size:0.85rem;
                               display:flex; align-items:center; justify-content:center;
                               font-family:'Prompt',sans-serif;">✕</button>
            </div>
            <!-- Modal body -->
            <div style="padding:1.4rem;">
                <!-- Token reward -->
                <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:1rem;">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt=""
                         style="width:34px; height:34px; object-fit:contain;
                                filter:drop-shadow(0 0 8px rgba(218,185,55,0.5));">
                    <div>
                        <p id="sm-token" style="font-size:1.65rem; font-weight:800; color:#f8e769; margin:0; line-height:1;"></p>
                        <p style="font-size:0.63rem; color:#6b6e77; margin:0;
                                  text-transform:uppercase; letter-spacing:0.08em;">Token Reward</p>
                    </div>
                </div>
                <!-- Title -->
                <h2 id="sm-title" style="font-size:1.05rem; font-weight:700; color:#eeebe1;
                                         margin:0 0 0.45rem; line-height:1.35;"></h2>
                <!-- Description -->
                <p id="sm-desc" style="font-size:0.82rem; color:#8a8e97; margin:0 0 1rem; line-height:1.65;"></p>
                <!-- Condition box -->
                <div id="sm-condition"
                     style="display:none; background:rgba(252,76,2,0.06);
                            border:1px solid rgba(252,76,2,0.20); border-radius:10px;
                            padding:0.7rem 1rem; margin-bottom:1rem;">
                    <p style="font-size:0.63rem; font-weight:700; color:rgba(252,76,2,0.8);
                               text-transform:uppercase; letter-spacing:0.08em; margin:0 0 0.35rem;">เงื่อนไขกิจกรรม</p>
                    <p id="sm-condition-text" style="font-size:0.82rem; color:#cecdcd; margin:0; line-height:1.6;"></p>
                </div>
                <!-- End date -->
                <p id="sm-enddate" style="font-size:0.75rem; color:#6b6e77; margin:0 0 1.25rem;"></p>
                <!-- Rejected message -->
                <p id="sm-rejected-msg" style="display:none; font-size:0.8rem;
                                                color:rgba(252,76,2,0.85); margin:0 0 0.75rem;"></p>
                <!-- Action: connect Strava -->
                <div id="sm-connect-area" style="display:none;">
                    <p style="font-size:0.8rem; color:#d2592a; margin:0 0 0.7rem;">⚠️ กรุณาเชื่อมต่อ Strava ก่อนทำภารกิจ</p>
                    <a href="<?= BASE_URL ?>/pages/strava_connect.php"
                       style="display:inline-flex; align-items:center; gap:0.5rem;
                              padding:0.65rem 1.25rem; border-radius:10px;
                              background:rgba(252,76,2,0.80); color:#fff;
                              font-size:0.85rem; font-weight:700;
                              font-family:'Prompt',sans-serif; text-decoration:none;">
                        &#127939; เชื่อมต่อ Strava
                    </a>
                </div>
                <!-- Action: submit form -->
                <form id="sm-strava-form" method="POST"
                      action="<?= BASE_URL ?>/pages/challenges.php"
                      style="display:none;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="submit_strava">
                    <input type="hidden" name="challenge_id" id="sm-cid-input" value="">
                    <button type="button" id="sm-submit-btn"
                            onclick="submitStravaForm('sm-strava-form',this)"
                            style="display:inline-flex; align-items:center; gap:0.5rem;
                                   padding:0.65rem 1.25rem; border-radius:10px;
                                   background:rgba(252,76,2,0.80); color:#fff;
                                   font-size:0.85rem; font-weight:700;
                                   font-family:'Prompt',sans-serif;
                                   cursor:pointer; border:none;">
                        &#127939; ตรวจสอบกิจกรรม Strava
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
    function openStravaModal(cid) {
        var d = _stravaModalData[cid];
        if (!d) return;

        // Populate fields
        document.getElementById('sm-token').textContent = '+' + d.token.toLocaleString() + ' Token';
        document.getElementById('sm-title').textContent = d.title;
        var descEl = document.getElementById('sm-desc');
        descEl.textContent = d.desc;
        descEl.style.display = d.desc ? 'block' : 'none';

        // Condition
        var scDiv  = document.getElementById('sm-condition');
        var scText = document.getElementById('sm-condition-text');
        var sc = d.sc || {};
        if (sc.sport_type || sc.min_distance || sc.min_moving_time || sc.min_elevation) {
            var parts = [];
            if (sc.sport_type) parts.push(sc.sport_type);
            if (sc.min_distance) parts.push('\u2265' + (sc.min_distance / 1000).toFixed(1) + ' \u0e01\u0e21.');
            if (sc.min_moving_time) parts.push('\u2265' + Math.floor(sc.min_moving_time / 60) + ' \u0e19\u0e32\u0e17\u0e35');
            if (sc.min_elevation) parts.push('\u0e04\u0e27\u0e32\u0e21\u0e2a\u0e39\u0e07 \u2265' + sc.min_elevation + ' \u0e21.');
            scText.textContent = parts.join(' \u00b7 ');
            scDiv.style.display = 'block';
        } else {
            scDiv.style.display = 'none';
        }

        // End date
        var edEl = document.getElementById('sm-enddate');
        if (d.ed) {
            var txt = '\ud83d\udcc5 \u0e2a\u0e34\u0e49\u0e19\u0e2a\u0e38\u0e14 ' + d.ed;
            if (d.daysLeft !== null && d.daysLeft >= 0 && d.daysLeft <= 7) {
                txt += ' \u00b7 ' + (d.daysLeft === 0 ? '\u26a1 \u0e27\u0e31\u0e19\u0e19\u0e35\u0e49!' : '\u26a1 \u0e40\u0e2b\u0e25\u0e37\u0e2d\u0e2d\u0e35\u0e01 ' + d.daysLeft + ' \u0e27\u0e31\u0e19');
            }
            edEl.textContent = txt;
            edEl.style.display = 'block';
        } else {
            edEl.style.display = 'none';
        }

        // Rejected message
        var rejMsg = document.getElementById('sm-rejected-msg');
        if (d.rejected) {
            rejMsg.textContent = '\u0e44\u0e21\u0e48\u0e1e\u0e1a\u0e01\u0e34\u0e08\u0e01\u0e23\u0e23\u0e21\u0e17\u0e35\u0e48\u0e15\u0e23\u0e07\u0e40\u0e07\u0e37\u0e48\u0e2d\u0e19\u0e44\u0e02 \u2022 \u0e25\u0e2d\u0e07\u0e43\u0e2b\u0e21\u0e48\u0e44\u0e14\u0e49';
            rejMsg.style.display = 'block';
        } else {
            rejMsg.style.display = 'none';
        }

        // Action
        var connectEl  = document.getElementById('sm-connect-area');
        var formEl     = document.getElementById('sm-strava-form');
        var submitBtn  = document.getElementById('sm-submit-btn');
        if (!_stravaConnected) {
            connectEl.style.display = 'block';
            formEl.style.display    = 'none';
        } else {
            connectEl.style.display = 'none';
            formEl.style.display    = 'block';
            document.getElementById('sm-cid-input').value = cid;
            submitBtn.innerHTML  = '&#127939; ' + (d.rejected ? '\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e2d\u0e35\u0e01\u0e04\u0e23\u0e31\u0e49\u0e07' : '\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e01\u0e34\u0e08\u0e01\u0e23\u0e23\u0e21 Strava');
            submitBtn.disabled   = false;
        }

        var modal = document.getElementById('strava-modal');
        var card  = document.getElementById('strava-modal-card');
        modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void card.offsetWidth; // force reflow
        modal.classList.add('modal-overlay-in');
        card.classList.add('modal-card-in');
    }
    function closeStravaModal() {
        var modal = document.getElementById('strava-modal');
        if (modal.style.display === 'none') return;
        var card  = document.getElementById('strava-modal-card');
        modal.classList.remove('modal-overlay-in'); card.classList.remove('modal-card-in');
        modal.classList.add('modal-overlay-out');   card.classList.add('modal-card-out');
        setTimeout(function() {
            modal.style.display = 'none';
            modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
            document.body.style.overflow = '';
        }, 180);
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeStravaModal(); closeQuizModal(); }
    });
    </script>

    <!-- ── Quiz Detail Modal ── -->
    <div id="quiz-modal"
         style="display:none; position:fixed; inset:0; z-index:1000;
                background:rgba(0,0,0,0.78); backdrop-filter:blur(6px);
                align-items:center; justify-content:center; padding:1rem;"
         onclick="if(event.target===this)closeQuizModal()">
        <div id="quiz-modal-card" style="background:#0f1416; border:1px solid rgba(218,185,55,0.25); border-radius:20px;
                    max-width:420px; width:100%; max-height:90vh; overflow-y:auto;
                    box-shadow:0 24px 60px rgba(0,0,0,0.65), 0 0 0 1px rgba(218,185,55,0.10);">
            <!-- Modal header -->
            <div style="padding:1.1rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.07);
                        display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <span style="font-size:0.95rem;">📝</span>
                    <span style="font-size:0.68rem; font-weight:700; color:rgba(218,185,55,0.9);
                                 letter-spacing:0.08em; text-transform:uppercase;">Quiz Mission</span>
                </div>
                <button onclick="closeQuizModal()"
                        style="width:28px; height:28px; border-radius:50%;
                               background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
                               color:#6b6e77; cursor:pointer; font-size:0.85rem;
                               display:flex; align-items:center; justify-content:center;
                               font-family:'Prompt',sans-serif;">✕</button>
            </div>
            <!-- Modal body -->
            <div style="padding:1.4rem;">
                <!-- Token reward -->
                <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:1rem;">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt=""
                         style="width:34px; height:34px; object-fit:contain;
                                filter:drop-shadow(0 0 8px rgba(218,185,55,0.5));">
                    <div>
                        <p id="qm-token" style="font-size:1.65rem; font-weight:800; color:#f8e769; margin:0; line-height:1;"></p>
                        <p style="font-size:0.63rem; color:#6b6e77; margin:0;
                                  text-transform:uppercase; letter-spacing:0.08em;">Token Reward</p>
                    </div>
                </div>
                <!-- Title -->
                <h2 id="qm-title" style="font-size:1.05rem; font-weight:700; color:#eeebe1;
                                         margin:0 0 0.45rem; line-height:1.35;"></h2>
                <!-- Description -->
                <p id="qm-desc" style="font-size:0.82rem; color:#8a8e97; margin:0 0 1rem; line-height:1.65;"></p>
                <!-- Quiz info box -->
                <div style="background:rgba(218,185,55,0.06); border:1px solid rgba(218,185,55,0.18);
                            border-radius:10px; padding:0.7rem 1rem; margin-bottom:1rem;
                            display:flex; align-items:center; gap:0.6rem;">
                    <svg width="15" height="15" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="qm-qcount" style="font-size:0.82rem; color:#cecdcd;"></span>
                </div>
                <!-- End date -->
                <p id="qm-enddate" style="font-size:0.75rem; color:#6b6e77; margin:0 0 1.25rem;"></p>
                <!-- Warning note -->
                <div style="display:flex; align-items:flex-start; gap:0.5rem; margin-bottom:1.25rem;
                            padding:0.65rem 0.85rem; border-radius:8px;
                            background:rgba(218,185,55,0.05); border:1px solid rgba(218,185,55,0.12);">
                    <svg width="13" height="13" fill="none" stroke="#dab937" viewBox="0 0 24 24" style="flex-shrink:0; margin-top:2px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span style="font-size:0.75rem; color:rgba(218,185,55,0.65); line-height:1.5;">ตอบได้ 1 ครั้งเท่านั้น — ต้องตอบถูกทุกข้อจึงจะได้รับ Token</span>
                </div>
                <!-- Start button -->
                <a id="qm-start-btn" href="#"
                   style="display:inline-flex; align-items:center; gap:0.5rem;
                          padding:0.65rem 1.4rem; border-radius:10px; width:100%; justify-content:center;
                          background:linear-gradient(135deg,#dab937,#c9a830); color:#091113;
                          font-size:0.92rem; font-weight:700;
                          font-family:'Prompt',sans-serif; text-decoration:none;">
                    → เริ่มทำ Quiz
                </a>
            </div>
        </div>
    </div>
    <script>
    function openQuizModal(cid) {
        var d = _quizModalData[cid];
        if (!d) { window.location = d ? d.url : '<?= BASE_URL ?>/pages/challenges.php?id=' + cid; return; }
        document.getElementById('qm-token').textContent = '+' + d.token.toLocaleString() + ' Token';
        document.getElementById('qm-title').textContent = d.title;
        var descEl = document.getElementById('qm-desc');
        descEl.textContent = d.desc;
        descEl.style.display = d.desc ? 'block' : 'none';
        var qcEl = document.getElementById('qm-qcount');
        qcEl.textContent = d.qcount > 0
            ? d.qcount + ' คำถาม • ต้องตอบถูกทุกข้อเพื่อรับ Token'
            : 'ไม่มีคำถาม';
        var edEl = document.getElementById('qm-enddate');
        if (d.ed) {
            var txt = '📅 สิ้นสุด ' + d.ed;
            if (d.daysLeft !== null && d.daysLeft >= 0 && d.daysLeft <= 7)
                txt += ' · ' + (d.daysLeft === 0 ? '⚡ วันนี้!' : '⚡ เหลืออีก ' + d.daysLeft + ' วัน');
            edEl.textContent = txt;
            edEl.style.display = 'block';
        } else {
            edEl.style.display = 'none';
        }
        document.getElementById('qm-start-btn').href = d.url;
        var modal = document.getElementById('quiz-modal');
        var card  = document.getElementById('quiz-modal-card');
        modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void card.offsetWidth; // force reflow
        modal.classList.add('modal-overlay-in');
        card.classList.add('modal-card-in');
    }
    function closeQuizModal() {
        var modal = document.getElementById('quiz-modal');
        if (modal.style.display === 'none') return;
        var card  = document.getElementById('quiz-modal-card');
        modal.classList.remove('modal-overlay-in'); card.classList.remove('modal-card-in');
        modal.classList.add('modal-overlay-out');   card.classList.add('modal-card-out');
        setTimeout(function() {
            modal.style.display = 'none';
            modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
            document.body.style.overflow = '';
        }, 180);
    }
    </script>
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
