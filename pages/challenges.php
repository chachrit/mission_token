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

    // Block double-submission (non-rejected) for the current challenge type only.
    // This prevents stale submissions from an old type (e.g. photo -> quiz) from blocking new attempts.
    if (hasSubmittedChallenge($employeeId, $challengeId, (string)$challenge['type'])) {
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
              AND submission_type = 'quiz'
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
        define('PHOTO_MAX_FILES', 5);

        // Restructure $_FILES['photos'] from PHP's multi-file format
        $rawFiles = $_FILES['photos'] ?? null;
        if (!$rawFiles || empty($rawFiles['name'][0])) {
            setFlash('error', 'กรุณาเลือกไฟล์รูปภาพอย่างน้อย 1 ไฟล์');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        // Flatten into array of individual file entries
        $uploads = [];
        foreach ($rawFiles['name'] as $i => $name) {
            if ($rawFiles['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $uploads[] = [
                'name'     => $name,
                'tmp_name' => $rawFiles['tmp_name'][$i],
                'error'    => $rawFiles['error'][$i],
                'size'     => $rawFiles['size'][$i],
            ];
        }

        if (empty($uploads)) {
            setFlash('error', 'กรุณาเลือกไฟล์รูปภาพอย่างน้อย 1 ไฟล์');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        if (count($uploads) > PHOTO_MAX_FILES) {
            setFlash('error', 'อัปโหลดได้สูงสุด ' . PHOTO_MAX_FILES . ' รูปต่อครั้ง');
            redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
        }

        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $savedFiles = [];

        foreach ($uploads as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                setFlash('error', 'เกิดข้อผิดพลาดในการอัปโหลด กรุณาลองใหม่');
                foreach ($savedFiles as $sf) { @unlink(UPLOAD_PATH . $sf); }
                redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
            }

            if ($file['size'] > UPLOAD_MAX_SIZE) {
                setFlash('error', 'ไฟล์ ' . htmlspecialchars($file['name']) . ' ใหญ่เกิน 20MB');
                foreach ($savedFiles as $sf) { @unlink(UPLOAD_PATH . $sf); }
                redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
            }

            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, ALLOWED_MIME, true)) {
                setFlash('error', 'ไฟล์ต้องเป็นรูปภาพ (jpg, png, gif, webp) เท่านั้น');
                foreach ($savedFiles as $sf) { @unlink(UPLOAD_PATH . $sf); }
                redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
            }

            $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', pathinfo($file['name'], PATHINFO_EXTENSION)));
            if (!in_array($ext, ALLOWED_EXT, true)) { $ext = 'jpg'; }
            $filename = sprintf('sub_%d_%d_%s.%s', $employeeId, $challengeId, bin2hex(random_bytes(6)), $ext);
            $destPath = UPLOAD_PATH . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                setFlash('error', 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่');
                foreach ($savedFiles as $sf) { @unlink(UPLOAD_PATH . $sf); }
                redirect(BASE_URL . '/pages/challenges.php?id=' . $challengeId);
            }

            $savedFiles[] = $filename;
        }

        // Store as JSON array (single-file submissions stored as JSON too for consistency)
        $photoPathValue = json_encode($savedFiles, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO challenge_submissions
                    (employee_id, challenge_id, submission_type, photo_path, status, token_awarded)
                VALUES (?, ?, 'photo', ?, 'pending', 0)
            ");
            $stmt->execute([$employeeId, $challengeId, $photoPathValue]);
            $fileCount = count($savedFiles);
            setFlash('pending', 'ส่งหลักฐาน ' . $fileCount . ' รูปเรียบร้อย รอการตรวจสอบเพื่อรับ Token');
        } catch (Throwable $e) {
            error_log('[MissionToken] photo submit error: ' . $e->getMessage());
            foreach ($savedFiles as $sf) { @unlink(UPLOAD_PATH . $sf); }
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
                setFlash('success', "ยินดีด้วย! พบกิจกรรม \"{$actName}\" ที่ผ่านเงื่อนไข ได้รับ +{$awarded} Token");
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

    // Annotate with user's submission status (batch — avoids N+1)
    $pdo = getDB();
    $mySubMap  = [];
    $qCountMap = [];
    if (!empty($challenges)) {
        $chalIds      = array_map(fn($c) => (int)$c['challenge_id'], $challenges);
        $placeholders = implode(',', array_fill(0, count($chalIds), '?'));

        // Latest submission per challenge in one query
                $subBatch = $pdo->prepare(" 
            WITH ranked AS (
                                  SELECT cs.challenge_id, cs.submission_id, cs.status, cs.token_awarded, cs.submitted_at,
                                      ROW_NUMBER() OVER (PARTITION BY cs.challenge_id ORDER BY cs.submitted_at DESC) AS rn
                                FROM   challenge_submissions cs
                                JOIN   challenges c ON c.challenge_id = cs.challenge_id
                                WHERE  cs.employee_id = ?
                                    AND  cs.challenge_id IN ($placeholders)
                                    AND  cs.submission_type = c.type
            )
            SELECT challenge_id, submission_id, status, token_awarded, submitted_at FROM ranked WHERE rn = 1
        ");
        $subBatch->execute([$employeeId, ...$chalIds]);
        foreach ($subBatch->fetchAll() as $row) {
            $mySubMap[(int)$row['challenge_id']] = $row;
        }

        // Question counts for quiz challenges in one query
        $qCountBatch = $pdo->prepare("
            SELECT challenge_id, COUNT(*) AS cnt
            FROM   quiz_questions
            WHERE  challenge_id IN ($placeholders)
            GROUP BY challenge_id
        ");
        $qCountBatch->execute($chalIds);
        foreach ($qCountBatch->fetchAll() as $row) {
            $qCountMap[(int)$row['challenge_id']] = (int)$row['cnt'];
        }
    }
    foreach ($challenges as &$ch) {
        $sub = $mySubMap[(int)$ch['challenge_id']] ?? null;
        $ch['my_status']       = $sub ? $sub['status']        : null;
        $ch['my_sub_id']       = $sub ? (int)$sub['submission_id'] : 0;
        $ch['my_token_awarded']= $sub ? (int)$sub['token_awarded'] : 0;
        $ch['my_submitted_at'] = $sub ? $sub['submitted_at']  : null;

        if ($ch['type'] === 'quiz') {
            $ch['question_count'] = $qCountMap[(int)$ch['challenge_id']] ?? 0;
        }

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

$flash    = getFlash();
$noToast  = true; // all flash types handled by inline modals/cards

$statusLabel = [
    'pending'       => ['text' => 'รอ Approve',  'bg' => 'rgba(218,185,55,0.10)',  'color' => '#dab937'],
    'approved'      => ['text' => 'อนุมัติแล้ว', 'bg' => 'rgba(81,142,92,0.12)',   'color' => '#518e5c'],
    'auto_approved' => ['text' => 'ผ่านแล้ว',    'bg' => 'rgba(81,142,92,0.12)',   'color' => '#518e5c'],
    'rejected'      => ['text' => 'ไม่ผ่าน',      'bg' => 'rgba(210,89,42,0.10)',  'color' => '#d2592a'],
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

    <!-- Flash: Winning Moment (quiz/strava success) -->
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
            <h2 class="text-2xl font-bold mb-2 ch-win-title">ยินดีด้วย!</h2>
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

    <!-- Flash: Photo Submitted — Pending Approval -->
    <?php elseif ($flash && $flash['type'] === 'pending'): ?>
    <div id="ch-pending-overlay" class="ch-flash-overlay" data-overlay-close="self-hide">
        <div class="ch-pending-modal">
            <div class="ch-pending-modal-icon">
                <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="ch-pending-modal-title">ส่งหลักฐานเรียบร้อย</h3>
            <p class="ch-pending-modal-sub">รอการตรวจสอบเพื่อรับ Token</p>
            <a href="<?= BASE_URL ?>/pages/challenges.php" class="ch-pending-modal-btn">ดูภารกิจอื่น</a>
        </div>
    </div>

    <!-- Flash: Error -->
    <?php elseif ($flash): ?>
    <div id="ch-error-flash-overlay" class="ch-flash-overlay" data-overlay-close="self-hide">
        <div class="ch-flash-modal ch-flash-modal--error">
            <div class="ch-flash-modal-icon">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <p class="ch-flash-modal-title">เกิดข้อผิดพลาด</p>
            <p class="ch-flash-modal-msg"><?= e($flash['message']) ?></p>
            <button class="ch-flash-modal-btn" data-action="close-error-overlay">
                ตกลง
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Strava loading overlay -->
    <div id="strava-loading-overlay" class="ch-strava-loading-overlay" role="status" aria-live="polite">
        <svg class="ch-strava-loading-logo" viewBox="0 0 24 24" width="44" height="44" fill="#FC4C02">
            <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
        </svg>
        <div class="ch-strava-spinner"></div>
        <div class="ch-strava-loading-copy">
            <p class="ch-strava-loading-title">กำลังตรวจสอบกิจกรรม Strava</p>
            <p class="ch-strava-loading-sub" id="strava-loading-sub">กำลังดึงข้อมูลกิจกรรม อาจใช้เวลา 15–30 วินาที...</p>
        </div>
    </div>


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
                <span class="badge ch-badge-quiz text-xs font-semibold mb-1.5 inline-block">Quiz Mission</span>
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
                        data-progress-init="<?= round(100 / $totalQ) ?>"></div>
                </div>
            </div>

            <!-- Steps area -->
            <div class="ch-quiz-body">
                <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                      id="quiz-form" data-total-q="<?= (int)$totalQ ?>">
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
                                    data-action="quiz-go-step" data-step="<?= $qi - 1 ?>">← ย้อนกลับ</button>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/pages/challenges.php"
                               class="btn-outline text-sm px-4 py-2.5">ยกเลิก</a>
                            <?php endif; ?>

                            <?php if ($qi < $totalQ - 1): ?>
                            <button type="button"
                                    id="next-<?= $qi ?>"
                                    class="btn-gold ml-auto text-sm px-5 py-2.5"
                                    disabled
                                    data-action="quiz-go-step" data-step="<?= $qi + 1 ?>">ถัดไป →</button>
                            <?php else: ?>
                            <button type="submit"
                                    id="quiz-submit-btn"
                                    class="btn-gold ml-auto text-sm px-6 py-2.5"
                                    disabled>ส่งคำตอบ</button>
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
            class="ch-processing-modal ch-u-hidden">
        <div class="ch-processing-modal-inner">
            <div class="ch-qpm-spinner">
                <div class="ch-qpm-orbit"></div>
                <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="ch-qpm-token">
            </div>
            <p class="text-base font-semibold ch-qpm-title mb-1.5">กำลังตรวจสอบคำตอบ…</p>
            <p class="text-sm ch-qpm-sub" id="qpm-dots">กรุณารอสักครู่</p>
        </div>
    </div>



    <?php else: ?>
    <!-- ── LIST VIEW: Quest Board ── -->
    <?php
        $_done  = 0;
        foreach ($challenges as $_c) {
            if (in_array($_c['my_status'], ['approved','auto_approved'], true)) $_done++;
        }
        $_total = count($challenges);

        // photo/strava rejected → ยังส่งซ้ำได้ → อยู่ในกลุ่ม available
        // quiz rejected → ส่งซ้ำไม่ได้ → อยู่ในกลุ่ม done
        $questsAvailable = array_values(array_filter($challenges, fn($c) =>
            $c['my_status'] === null ||
            ($c['my_status'] === 'rejected' && in_array($c['type'], ['photo', 'strava'], true))
        ));
        $questsDone      = array_values(array_filter($challenges, fn($c) =>
            in_array($c['my_status'], ['approved','auto_approved','pending'], true) ||
            ($c['type'] === 'quiz' && $c['my_status'] === 'rejected')
        ));
    ?>

    <?php if ($challenges): ?>

    <!-- Quest Board hero header -->
    <div class="mb-8 relative overflow-hidden ch-board-hero ch-board-hero-pad">
        <div class="ch-board-dot-grid"></div>
        <div class="ch-board-glow"></div>
        <div class="relative max-w-7xl mx-auto ch-board-hero-inner">
            <div>
                <p class="text-[14px] font-bold uppercase mb-2.5 ch-board-hero-label">Quest Board</p>
                <h1 class="font-bold leading-tight ch-hero-title ch-hero-title-size">ภารกิจทั้งหมด</h1>
                <p class="text-base mt-2.5 ch-hero-sub">เลือกภารกิจที่ต้องการ แล้วส่งหลักฐานเพื่อรับ Token</p>
            </div>
            <div class="ch-board-progress-block">
                <div class="flex items-center gap-3">
                    <p class="text-xl font-bold ch-board-progress-value">
                        <?= $_done ?><span class="font-normal text-base ch-board-progress-total"> / <?= $_total ?> ภารกิจสำเร็จ</span>
                    </p>
                </div>
                <div class="ch-board-progress-track">
                    <div class="ch-board-progress-fill"
                         data-progress-width="<?= $_total > 0 ? round($_done / $_total * 100) : 0 ?>"></div>
                </div>
                <p class="text-xs font-semibold uppercase tracking-widest ch-board-progress-percent">
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
        <div class="ch-quest-flip-scene" data-cid="<?= $cid ?>" data-type="<?= e($ch['type']) ?>" data-sid="<?= (int)$ch['my_sub_id'] ?>"<?= $isRejected ? ' data-rejected' : '' ?>>
            <div class="ch-flip-card" id="flip-<?= $cid ?>">

                <!-- ── FRONT FACE ── -->
                <div class="ch-flip-front ch-quest-card <?= $isRejected ? 'ch-quest-card--rejected' : '' ?>">
                    <div class="ch-quest-accent-bar <?= $isRejected ? 'ch-quest-accent-bar--rejected' : '' ?>"></div>
                    <div class="ch-quest-inner">
                        <!-- Top: type badge + urgency (if near deadline) -->
                        <div class="ch-quest-top-row">
                            <?php if ($ch['type'] === 'strava'): ?>
                            <span class="ch-type-badge ch-type-badge--strava">Strava</span>
                            <?php elseif ($ch['type'] === 'quiz'): ?>
                            <span class="ch-type-badge">Quiz</span>
                            <?php else: ?>
                            <span class="ch-type-badge">Photo</span>
                            <?php endif; ?>
                            <?php if ($isRejected): ?>
                            <span class="ch-rejected-front-badge">
                                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                ถูกปฏิเสธ
                            </span>
                            <?php elseif ($_daysLeft !== null && $_daysLeft >= 0 && $_daysLeft <= 7): ?>
                            <span class="ch-urgency-badge">
                                <?= $_daysLeft === 0 ? 'วันนี้!' : 'เหลือ ' . $_daysLeft . ' วัน' ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Token reward hero — the prize hook -->
                        <div class="ch-front-token-hero">
                               <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="token-coin-anim ch-token-coin-lg">
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
                    <!-- Unseen highlight label -->
                    <div class="ch-new-badge <?= $isRejected ? 'ch-new-badge--rejected' : '' ?>">
                        <?php if ($isRejected): ?>
                        <svg width="9" height="9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                        </svg>
                        ส่งใหม่ได้
                        <?php else: ?>
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/>
                        </svg>
                        ภารกิจใหม่
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── BACK FACE ── -->
                 <div class="ch-flip-back ch-quest-card <?= $isRejected ? 'ch-quest-card--rejected' : '' ?><?= (!$isRejected && in_array($ch['type'], ['quiz', 'strava', 'photo'], true)) ? ' ch-card-clickable' : '' ?>"
                     <?php if (!$isRejected && $ch['type'] === 'quiz'): ?>
                     data-action="open-quiz-modal" data-cid="<?= $cid ?>"
                     tabindex="0" role="button" aria-label="ดูรายละเอียดภารกิจ: <?= e($ch['title']) ?>"
                     <?php elseif ($ch['type'] === 'strava'): ?>
                     data-action="open-strava-modal" data-cid="<?= $cid ?>"
                     tabindex="0" role="button" aria-label="ดูรายละเอียดภารกิจ: <?= e($ch['title']) ?>"
                     <?php elseif ($ch['type'] === 'photo'): ?>
                     data-action="open-photo-modal" data-cid="<?= $cid ?>"
                     tabindex="0" role="button" aria-label="ดูรายละเอียดภารกิจ: <?= e($ch['title']) ?>"
                     <?php endif; ?>>
                    <div class="ch-quest-accent-bar <?= $isRejected ? 'ch-quest-accent-bar--rejected' : '' ?><?= $ch['type'] === 'strava' ? ' ch-quest-accent-bar--strava' : '' ?>"></div>
                    <div class="ch-flip-back-body">
                        <!-- Touch close button (pointer: coarse only — see style.css) -->
                        <button type="button" class="ch-flip-back-close" aria-label="ย้อนกลับ">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                            ย้อนกลับ
                        </button>
                        <!-- Header: type badge -->
                        <div class="ch-flip-back-header">
                            <?php if ($ch['type'] === 'strava'): ?>
                            <span class="ch-type-badge ch-type-badge--strava">Strava</span>
                            <?php elseif ($ch['type'] === 'quiz'): ?>
                            <span class="ch-type-badge">Quiz</span>
                            <?php else: ?>
                            <span class="ch-type-badge">Photo</span>
                            <?php endif; ?>
                            <?php if (!$isRejected && $ch['type'] === 'quiz'): ?>
                            <span class="ch-click-hint">กดการ์ดเพื่อดูรายละเอียด →</span>
                            <?php elseif ($ch['type'] === 'strava'): ?>
                            <span class="ch-click-hint ch-click-hint--strava">กดการ์ดเพื่อดูรายละเอียด →</span>
                            <?php endif; ?>
                        </div>

                        <!-- Token reward -->
                        <div class="ch-flip-back-reward">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="ch-token-coin-sm">
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
                        <div class="ch-flip-back-instructions ch-flip-back-instructions--strava">
                            <p class="ch-flip-back-instructions-label ch-flip-back-instructions-label--strava">เงื่อนไขกิจกรรม</p>
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
                            <span class="ch-days-left-note">&bull; เหลืออีก <?= $_daysLeft === 0 ? 'วันนี้!' : $_daysLeft . ' วัน' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action area -->
                        <div class="ch-flip-action">
                            <?php if ($isRejected && $ch['type'] === 'quiz'): ?>
                            <div class="ch-rejected-msg">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                คุณทำภารกิจไม่ผ่าน &bull;
                            </div>
                            <?php elseif ($ch['type'] === 'strava'): ?>
                            <?php if (!$stravaConnected): ?>
                            <p class="ch-action-hint ch-action-hint--danger">ยังไม่ได้เชื่อมต่อ Strava</p>
                            <?php elseif ($isRejected): ?>
                            <p class="ch-action-hint ch-action-hint--strava-warn">ไม่พบกิจกรรมที่ตรงเงื่อนไข &bull; ลองใหม่ได้</p>
                            <?php else: ?>
                            <p class="ch-action-hint ch-action-hint--strava">กดการ์ดเพื่อเริ่มทำภารกิจ</p>
                            <?php endif; ?>
                            <?php elseif ($ch['type'] === 'photo'): ?>
                            <?php if ($isRejected): ?>
                            <p class="ch-action-hint ch-action-hint--danger">งานก่อนหน้าถูกปฏิเสธ &bull; กดการ์ดเพื่อส่งใหม่</p>
                            <?php else: ?>
                            <p class="ch-action-hint ch-action-hint--gold">กดการ์ดเพื่อส่งหลักฐาน</p>
                            <?php endif; ?>
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
    <?php
    // Collect Photo challenge data for modal JS
    $photoModalData = [];
    foreach ($questsAvailable as $_pch) {
        if ($_pch['type'] !== 'photo') continue;
        $_pcid = (int)$_pch['challenge_id'];
        $_pets = $_pch['end_date'] ? strtotime((string)$_pch['end_date']) : null;
        $_ped  = $_pets ? date('d/m/Y', $_pets) : null;
        $_pdl  = $_pets ? (int)(new DateTime('today'))->diff(new DateTime(date('Y-m-d', $_pets)))->days
                          * ((new DateTime('today') <= new DateTime(date('Y-m-d', $_pets))) ? 1 : -1) : null;
        $photoModalData[$_pcid] = [
            'title'        => $_pch['title'],
            'desc'         => (string)($_pch['description'] ?? ''),
            'instructions' => (string)($_pch['instructions'] ?? ''),
            'token'        => (int)$_pch['token_reward'],
            'rejected'     => $_pch['my_status'] === 'rejected',
            'ed'           => $_ped,
            'daysLeft'     => $_pdl,
            'csrfField'    => csrfField(),
        ];
    }
    ?>
    var _photoModalData = <?= json_encode($photoModalData, JSON_UNESCAPED_UNICODE) ?>;
    </script>


    <!-- ── Photo Upload Modal ── -->
    <div id="photo-modal"
         class="ch-detail-modal ch-detail-modal--gold ch-u-hidden"
         data-overlay-close="photo-modal">
        <div id="photo-modal-card" class="ch-detail-modal-card ch-detail-modal-card--gold">
            <!-- Modal header -->
            <div class="ch-detail-modal-head">
                <div class="ch-detail-modal-head-main">
                    <svg width="18" height="18" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="ch-detail-modal-head-tag ch-detail-modal-head-tag--gold">Photo Mission</span>
                </div>
                <button data-action="close-photo-modal" class="ch-detail-modal-close">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <!-- Modal body -->
            <div class="ch-detail-modal-body">
                <!-- Token reward -->
                <div class="ch-detail-modal-token-row">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" loading="lazy"
                         class="ch-detail-modal-token-icon">
                    <div>
                        <p id="pm-token" class="ch-detail-modal-token-value"></p>
                        <p class="ch-detail-modal-token-label">Token Reward</p>
                    </div>
                </div>
                <!-- Title -->
                <h2 id="pm-title" class="ch-detail-modal-title"></h2>
                <!-- Description -->
                <p id="pm-desc" class="ch-detail-modal-desc"></p>
                <!-- Instructions -->
                <div id="pm-instructions"
                     class="ch-detail-modal-box ch-detail-modal-box--gold ch-u-hidden">
                    <p class="ch-detail-modal-box-label ch-detail-modal-box-label--gold">วิธีส่งหลักฐาน</p>
                    <p id="pm-instructions-text" class="ch-detail-modal-box-text"></p>
                </div>
                <!-- End date -->
                <p id="pm-enddate" class="ch-detail-modal-enddate"></p>
                <!-- Rejected message -->
                <p id="pm-rejected-msg" class="ch-detail-modal-rejected ch-u-hidden"></p>
                <!-- Upload form -->
                <form id="pm-photo-form" method="POST"
                      action="<?= BASE_URL ?>/pages/challenges.php"
                      enctype="multipart/form-data"
                      class="ch-detail-modal-form">
                    <div id="pm-csrf"></div>
                    <input type="hidden" name="action" value="submit_photo">
                    <input type="hidden" name="challenge_id" id="pm-cid-input" value="">
                    <input type="file" name="photos[]" accept="image/*" multiple required class="ch-file-input">
                    <p class="ch-file-hint">JPG, PNG, WebP &bull; สูงสุด 5 รูป &bull; แต่ละรูปไม่เกิน 20MB &bull; ได้รับ Token หลัง HR อนุมัติ</p>
                    <button type="submit" class="ch-detail-modal-submit ch-detail-modal-submit--gold">
                        ส่งหลักฐาน
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- ── Strava Detail Modal ── -->
    <div id="strava-modal"
         class="ch-detail-modal ch-detail-modal--strava ch-u-hidden"
         data-overlay-close="strava-modal">
        <div id="strava-modal-card" class="ch-detail-modal-card ch-detail-modal-card--strava">
            <!-- Modal header -->
            <div class="ch-detail-modal-head">
                <div class="ch-detail-modal-head-main">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="#FC4C02">
                        <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                    </svg>
                    <span class="ch-detail-modal-head-tag ch-detail-modal-head-tag--strava">Strava Mission</span>
                </div>
                <button data-action="close-strava-modal" class="ch-detail-modal-close">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <!-- Modal body -->
            <div class="ch-detail-modal-body">
                <!-- Token reward -->
                <div class="ch-detail-modal-token-row">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" loading="lazy"
                         class="ch-detail-modal-token-icon">
                    <div>
                        <p id="sm-token" class="ch-detail-modal-token-value"></p>
                        <p class="ch-detail-modal-token-label">Token Reward</p>
                    </div>
                </div>
                <!-- Title -->
                <h2 id="sm-title" class="ch-detail-modal-title"></h2>
                <!-- Description -->
                <p id="sm-desc" class="ch-detail-modal-desc"></p>
                <!-- Condition box -->
                <div id="sm-condition"
                     class="ch-detail-modal-box ch-detail-modal-box--strava ch-u-hidden">
                    <p class="ch-detail-modal-box-label ch-detail-modal-box-label--strava">เงื่อนไขกิจกรรม</p>
                    <p id="sm-condition-text" class="ch-detail-modal-box-text"></p>
                </div>
                <!-- End date -->
                <p id="sm-enddate" class="ch-detail-modal-enddate"></p>
                <!-- Rejected message -->
                <p id="sm-rejected-msg" class="ch-detail-modal-rejected ch-detail-modal-rejected--strava ch-u-hidden"></p>
                <!-- Action: connect Strava -->
                <div id="sm-connect-area" class="ch-u-hidden">
                    <p class="ch-detail-modal-connect-note">กรุณาเชื่อมต่อ Strava ก่อนทำภารกิจ</p>
                    <a href="<?= BASE_URL ?>/pages/strava_connect.php"
                       class="ch-detail-modal-submit ch-detail-modal-submit--strava ch-detail-modal-submit-link">
                        เชื่อมต่อ Strava
                    </a>
                </div>
                <!-- Action: submit form -->
                <form id="sm-strava-form" method="POST"
                      action="<?= BASE_URL ?>/pages/challenges.php"
                      class="ch-u-hidden">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="submit_strava">
                    <input type="hidden" name="challenge_id" id="sm-cid-input" value="">
                    <button type="button" id="sm-submit-btn"
                            data-action="submit-strava-form" data-form-id="sm-strava-form"
                            class="ch-detail-modal-submit ch-detail-modal-submit--strava">
                        ตรวจสอบกิจกรรม Strava
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- ── Quiz Detail Modal ── -->
    <div id="quiz-modal"
         class="ch-detail-modal ch-detail-modal--gold ch-u-hidden"
         data-overlay-close="quiz-modal">
        <div id="quiz-modal-card" class="ch-detail-modal-card ch-detail-modal-card--gold">
            <!-- Modal header -->
            <div class="ch-detail-modal-head">
                <div class="ch-detail-modal-head-main">
                    <span class="ch-detail-modal-head-tag ch-detail-modal-head-tag--gold">Quiz Mission</span>
                </div>
                <button data-action="close-quiz-modal" class="ch-detail-modal-close">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <!-- Modal body -->
            <div class="ch-detail-modal-body">
                <!-- Token reward -->
                <div class="ch-detail-modal-token-row">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" loading="lazy"
                         class="ch-detail-modal-token-icon">
                    <div>
                        <p id="qm-token" class="ch-detail-modal-token-value"></p>
                        <p class="ch-detail-modal-token-label">Token Reward</p>
                    </div>
                </div>
                <!-- Title -->
                <h2 id="qm-title" class="ch-detail-modal-title"></h2>
                <!-- Description -->
                <p id="qm-desc" class="ch-detail-modal-desc"></p>
                <!-- Quiz info box -->
                <div class="ch-detail-modal-box ch-detail-modal-box--gold ch-detail-modal-box-row">
                    <svg width="15" height="15" fill="none" stroke="#dab937" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="qm-qcount" class="ch-detail-modal-box-text"></span>
                </div>
                <!-- End date -->
                <p id="qm-enddate" class="ch-detail-modal-enddate"></p>
                <!-- Warning note -->
                <div class="ch-detail-modal-warning">
                    <svg width="13" height="13" fill="none" stroke="#dab937" viewBox="0 0 24 24" class="ch-detail-modal-warning-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span class="ch-detail-modal-warning-text">ตอบได้ 1 ครั้งเท่านั้น — ต้องตอบถูกทุกข้อจึงจะได้รับ Token</span>
                </div>
                <!-- Start button -->
                <a id="qm-start-btn" href="#"
                   class="ch-detail-modal-submit ch-detail-modal-submit--gold ch-detail-modal-submit-link ch-detail-modal-submit-block">
                    → เริ่มทำ Quiz
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="ch-empty-board mb-10">ไม่มีภารกิจรอดำเนินการในช่วงนี้</div>
<?php endif; ?>

    <!-- ── SECTION 2: ดำเนินการแล้ว ── -->
    <?php if ($questsDone): ?>
    <div>
        <button data-action="toggle-done-section" class="ch-done-toggle-btn" id="done-section-btn">
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
        <div id="done-section-grid" class="ch-u-hidden">
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
                               <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="ch-token-earned-icon">
                            <span class="ch-token-earned-value">+<?= formatTokens($ch['my_token_awarded']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="ch-done-title"><?= e($ch['title']) ?></h3>
                    <?php if (!empty($ch['end_date'])): ?>
                    <p class="ch-pending-text ch-pending-text--muted">
                        สิ้นสุด <?= date('d/m/Y', strtotime((string)$ch['end_date'])) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($isPending): ?>
                    <p class="ch-pending-text">รอการตรวจสอบจาก HR/Manager</p>
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



    <?php else: ?>
    <div class="ch-empty-board">
        ไม่มีภารกิจเปิดรับในช่วงเวลานี้
    </div>
    <?php endif; ?>

    <?php endif; /* end list view */ ?>

</div><!-- /max-w-7xl -->
</div><!-- /ds-page-inner -->
</div><!-- /ch-challenges-wrap -->

<script src="<?= BASE_URL ?>/assets/js/challenges.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
