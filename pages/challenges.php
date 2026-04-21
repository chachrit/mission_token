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

    <!-- Flash -->
    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl px-5 py-4 text-sm font-medium
        <?= $flash['type'] === 'success'
            ? 'border border-green-200 bg-green-50 text-green-800'
            : 'border border-red-200 bg-red-50 text-red-800' ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($dataError): ?>
    <div class="mb-6 rounded-xl border border-[#edc3b2] bg-[#fff1ea] px-5 py-4 text-sm text-j-orange">
        <?= e($dataError) ?>
    </div>
    <?php endif; ?>

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-j-dark">ภารกิจทั้งหมด</h1>
        <p class="mt-1 text-sm text-j-slate">เลือกภารกิจที่ต้องการทำ แล้วส่งหลักฐานเพื่อรับ Token</p>
    </div>

    <?php if ($challenges): ?>
    <div class="grid gap-6 lg:grid-cols-2">
        <?php foreach ($challenges as $ch): ?>
        <?php
            $cid       = (int)$ch['challenge_id'];
            $myStatus  = $ch['my_status'];
            $isDone    = in_array($myStatus, ['approved', 'auto_approved'], true);
            $isPending = $myStatus === 'pending';
            $sl        = $statusLabel[$myStatus] ?? null;
            $isOpen    = ($focusChallengeId === $cid);
        ?>
        <article class="journal-card p-6 flex flex-col gap-4 <?= $isDone ? 'opacity-60' : '' ?>"
                 id="challenge-<?= $cid ?>">

            <!-- Header row -->
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="badge text-xs font-medium"
                              style="background:#091113; color:#dab937;">
                            <?= $ch['type'] === 'quiz' ? '📝 Quiz' : '📷 Photo' ?>
                        </span>
                        <?php if ($sl): ?>
                        <span class="badge text-xs"
                              style="background:<?= $sl['bg'] ?>; color:<?= $sl['color'] ?>;">
                            <?= $sl['text'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-lg font-semibold text-j-dark leading-snug"><?= e($ch['title']) ?></h2>
                    <p class="mt-1.5 text-sm leading-6 text-j-slate"><?= e((string)$ch['description']) ?></p>
                    <?php if ($ch['type'] === 'quiz' && isset($ch['question_count'])): ?>
                    <p class="mt-1 text-xs text-j-slate"><?= $ch['question_count'] ?> คำถาม • ต้องตอบถูกทั้งหมด</p>
                    <?php endif; ?>
                </div>
                <!-- Token reward -->
                <div class="flex flex-col items-center flex-shrink-0 text-center">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token" class="h-10 w-10">
                    <p class="text-base font-bold text-j-gold">+<?= formatTokens((int)$ch['token_reward']) ?></p>
                    <p class="text-[10px] text-j-slate uppercase tracking-wider">TOKEN</p>
                </div>
            </div>

            <!-- Action area -->
            <?php if ($isDone): ?>
            <div class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium"
                 style="background:#dcfce7; color:#166534;">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                ทำภารกิจสำเร็จแล้ว <?= $ch['my_token_awarded'] > 0 ? '(+' . formatTokens($ch['my_token_awarded']) . ' Token)' : '' ?>
            </div>

            <?php elseif ($isPending): ?>
            <div class="rounded-xl px-4 py-3 text-sm font-medium"
                 style="background:#fef9c3; color:#854d0e;">
                ⏳ รอการตรวจสอบจาก HR/Manager
            </div>

            <?php elseif ($ch['type'] === 'quiz'): ?>
                <?php if ($isOpen && !empty($quizQuestions)): ?>
                <!-- Quiz form -->
                <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                      class="border-t border-j-silver pt-4 flex flex-col gap-5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="submit_quiz">
                    <input type="hidden" name="challenge_id" value="<?= $cid ?>">

                    <?php foreach ($quizQuestions as $qi => $q): ?>
                    <div>
                        <p class="mb-3 text-sm font-semibold text-j-dark">
                            <?= ($qi + 1) ?>. <?= e($q['question_text']) ?>
                        </p>
                        <div class="grid gap-2">
                            <?php
                            $opts = [
                                'A' => $q['option_a'],
                                'B' => $q['option_b'],
                                'C' => $q['option_c'] ?? null,
                                'D' => $q['option_d'] ?? null,
                            ];
                            foreach ($opts as $letter => $text):
                                if ($text === null) continue;
                            ?>
                            <label class="quiz-option flex items-center gap-3 cursor-pointer rounded-xl border border-j-silver px-4 py-3 text-sm text-j-dark hover:border-j-gold hover:bg-[#faf0cf] transition-colors">
                                <input type="radio" name="q_<?= (int)$q['question_id'] ?>"
                                       value="<?= $letter ?>" required
                                       class="accent-[#dab937]">
                                <span class="font-medium text-j-gold w-5 flex-shrink-0"><?= $letter ?>.</span>
                                <?= e($text) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="flex gap-3 pt-1">
                        <button type="submit" class="btn-gold flex-1 justify-center py-2.5">
                            ส่งคำตอบ
                        </button>
                        <a href="<?= BASE_URL ?>/pages/challenges.php"
                           class="btn-outline justify-center px-5 py-2.5">
                            ยกเลิก
                        </a>
                    </div>
                    <p class="text-xs text-j-slate">⚠️ ตอบได้ 1 ครั้งเท่านั้น ไม่สามารถแก้ไขได้ภายหลัง</p>
                </form>

                <?php elseif ($myStatus === 'rejected'): ?>
                <div class="rounded-xl px-4 py-3 mb-2 text-sm"
                     style="background:#fee2e2; color:#991b1b;">
                    ตอบไม่ผ่าน — ไม่สามารถลองใหม่ได้
                </div>
                <?php if (!empty($rejectedQuizReviews[$cid])): ?>
                <div class="border-t border-j-silver pt-4 flex flex-col gap-4">
                    <p class="text-xs font-semibold text-j-slate uppercase tracking-wider">เฉลยคำตอบ</p>
                    <?php foreach ($rejectedQuizReviews[$cid] as $qi => $q): ?>
                    <div class="text-sm">
                        <p class="font-medium text-j-dark mb-1.5">
                            <?= ($qi + 1) ?>. <?= e($q['question_text']) ?>
                        </p>
                        <p class="text-xs font-semibold mb-1" style="color:#166534;">
                            ✓ คำตอบที่ถูก: <?= e(strtoupper($q['correct_option'])) ?>
                            <?php
                            $correctKey = 'option_' . strtolower($q['correct_option']);
                            if (!empty($q[$correctKey])):
                            ?> — <?= e($q[$correctKey]) ?><?php endif; ?>
                        </p>
                        <?php if (!empty($q['explanation'])): ?>
                        <p class="text-xs text-j-slate leading-5 rounded-lg px-3 py-2"
                           style="background:#faf0cf;">
                            💡 <?= e($q['explanation']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/challenges.php?id=<?= $cid ?>#challenge-<?= $cid ?>"
                   class="btn-gold w-full justify-center py-2.5">
                    เริ่มทำ Quiz
                </a>
                <?php endif; ?>

            <?php elseif ($ch['type'] === 'photo'): ?>
                <?php if ($isOpen): ?>
                <!-- Photo upload form -->
                <form method="POST" action="<?= BASE_URL ?>/pages/challenges.php"
                      enctype="multipart/form-data"
                      class="border-t border-j-silver pt-4 flex flex-col gap-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="submit_photo">
                    <input type="hidden" name="challenge_id" value="<?= $cid ?>">

                    <?php if ($ch['instructions']): ?>
                    <div class="rounded-xl px-4 py-3 text-sm text-j-dark"
                         style="background:#faf0cf; border:1px solid #dab937;">
                        <p class="font-semibold mb-1">คำแนะนำ:</p>
                        <p class="leading-6"><?= nl2br(e((string)$ch['instructions'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-j-dark mb-2">อัปโหลดรูปภาพหลักฐาน</label>
                        <input type="file" name="photo" accept="image/*" required
                               class="journal-input text-sm py-2 cursor-pointer">
                        <p class="mt-1 text-xs text-j-slate">รองรับ JPG, PNG, GIF, WebP • ขนาดไม่เกิน 5MB</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-gold flex-1 justify-center py-2.5">
                            ส่งหลักฐาน
                        </button>
                        <a href="<?= BASE_URL ?>/pages/challenges.php"
                           class="btn-outline justify-center px-5 py-2.5">
                            ยกเลิก
                        </a>
                    </div>
                </form>

                <?php elseif ($myStatus === 'rejected'): ?>
                <div class="rounded-xl px-4 py-3 text-sm"
                     style="background:#fee2e2; color:#991b1b;">
                    หลักฐานไม่ผ่าน — กรุณาติดต่อ HR สำหรับข้อมูลเพิ่มเติม
                </div>

                <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/challenges.php?id=<?= $cid ?>#challenge-<?= $cid ?>"
                   class="btn-gold w-full justify-center py-2.5">
                    ส่งหลักฐาน
                </a>
                <?php endif; ?>

            <?php endif; ?>

        </article>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-16 text-center text-sm text-j-slate">
        ไม่มีภารกิจเปิดรับในช่วงเวลานี้
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
