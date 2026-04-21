<?php
/**
 * admin/challenges/edit.php
 * Admin — create or edit a challenge + manage quiz questions
 *
 * GET  ?id=N  → edit existing challenge
 * GET  (no id) → create new
 * POST → save challenge + questions
 */

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId     = (int)$_SESSION['employee_id'];
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit      = $challengeId > 0;
$pdo         = getDB();

// ── POST: save ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action = (string)($_POST['action'] ?? 'save_challenge');

    // ── Delete entire challenge ──────────────────────────
    if ($action === 'delete_challenge') {
        $cid = (int)($_POST['challenge_id'] ?? 0);
        if ($cid > 0) {
            // Check for existing submissions
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM challenge_submissions WHERE challenge_id = ?");
            $stmt->execute([$cid]);
            $cnt = (int)$stmt->fetch()['cnt'];
            if ($cnt > 0) {
                setFlash('error', 'ไม่สามารถลบได้ เพราะมีงานที่ส่งแล้ว (' . $cnt . ' รายการ) — ปิดการใช้งานแทน');
                redirect(BASE_URL . '/admin/challenges/edit.php?id=' . $cid);
            }
            $pdo->prepare("DELETE FROM quiz_questions WHERE challenge_id = ?")->execute([$cid]);
            $pdo->prepare("DELETE FROM challenges WHERE challenge_id = ?")->execute([$cid]);
            setFlash('success', 'ลบภารกิจแล้ว');
        }
        redirect(BASE_URL . '/admin/challenges/index.php');
    }

    // ── Delete a single quiz question ─────────────────────
    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $cid = (int)($_POST['challenge_id'] ?? 0);
        if ($qid > 0 && $cid > 0) {
            $pdo->prepare("DELETE FROM quiz_questions WHERE question_id = ? AND challenge_id = ?")
                ->execute([$qid, $cid]);
            setFlash('success', 'ลบคำถามแล้ว');
        }
        redirect(BASE_URL . '/admin/challenges/edit.php?id=' . $cid);
    }

    // ── Save challenge (create or update) ─────────────────
    $title       = trim((string)($_POST['title']        ?? ''));
    $description = trim((string)($_POST['description']  ?? ''));
    $type        = (string)($_POST['type']              ?? 'quiz');
    $instructions= trim((string)($_POST['instructions'] ?? ''));
    $tokenReward = max(1, (int)($_POST['token_reward']  ?? 10));
    $startDate   = (string)($_POST['start_date']        ?? '');
    $endDate     = (string)($_POST['end_date']          ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '' || $startDate === '' || $endDate === '') {
        setFlash('error', 'กรุณากรอกชื่อภารกิจ วันเริ่ม และวันสิ้นสุด');
        redirect(BASE_URL . '/admin/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }

    if ($startDate > $endDate) {
        setFlash('error', 'วันเริ่มต้องไม่เกินวันสิ้นสุด');
        redirect(BASE_URL . '/admin/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }

    try {
        if ($isEdit) {
            $pdo->prepare("
                UPDATE challenges
                SET title = ?, description = ?, type = ?, instructions = ?,
                    token_reward = ?, start_date = ?, end_date = ?, is_active = ?
                WHERE challenge_id = ?
            ")->execute([$title, $description, $type, $instructions,
                         $tokenReward, $startDate, $endDate, $isActive, $challengeId]);
            $savedId = $challengeId;
            $msg = 'บันทึกการแก้ไขแล้ว';
        } else {
            $pdo->prepare("
                INSERT INTO challenges (title, description, type, instructions, token_reward, start_date, end_date, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $description, $type, $instructions,
                         $tokenReward, $startDate, $endDate, $isActive, $adminId]);
            $savedId = (int)$pdo->lastInsertId();
            $msg = 'สร้างภารกิจแล้ว';
        }

        // ── Save quiz questions ────────────────────────────
        if ($type === 'quiz') {
            $qTexts   = $_POST['q_text']    ?? [];
            $qA       = $_POST['q_a']       ?? [];
            $qB       = $_POST['q_b']       ?? [];
            $qC       = $_POST['q_c']       ?? [];
            $qD       = $_POST['q_d']       ?? [];
            $qCorrect = $_POST['q_correct'] ?? [];
            $qExplan  = $_POST['q_explain'] ?? [];
            $qIds     = $_POST['q_id']      ?? []; // existing question IDs (0 = new)

            $stmtUpdate = $pdo->prepare("
                UPDATE quiz_questions
                SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                    correct_option = ?, explanation = ?, display_order = ?
                WHERE question_id = ? AND challenge_id = ?
            ");
            $stmtInsert = $pdo->prepare("
                INSERT INTO quiz_questions
                    (challenge_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($qTexts as $i => $qText) {
                $qText = trim($qText);
                if ($qText === '') continue;

                $a       = trim($qA[$i]       ?? '');
                $b       = trim($qB[$i]       ?? '');
                $c       = trim($qC[$i]       ?? '') ?: null;
                $d       = trim($qD[$i]       ?? '') ?: null;
                $correct = strtoupper(trim($qCorrect[$i] ?? 'A'));
                $explain = trim($qExplan[$i]  ?? '') ?: null;
                $order   = $i + 1;
                $existingQid = (int)($qIds[$i] ?? 0);

                if ($existingQid > 0) {
                    $stmtUpdate->execute([$qText, $a, $b, $c, $d, $correct, $explain, $order, $existingQid, $savedId]);
                } else {
                    $stmtInsert->execute([$savedId, $qText, $a, $b, $c, $d, $correct, $explain, $order]);
                }
            }
        }

        setFlash('success', $msg);
        redirect(BASE_URL . '/admin/challenges/edit.php?id=' . $savedId);

    } catch (Throwable $e) {
        error_log('[MissionToken] challenge save error: ' . $e->getMessage());
        setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        redirect(BASE_URL . '/admin/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }
}

// ── GET: load challenge if editing ───────────────────────────
$challenge     = null;
$quizQuestions = [];

if ($isEdit) {
    $challenge = getChallenge($challengeId);
    if (!$challenge) {
        setFlash('error', 'ไม่พบภารกิจนี้');
        redirect(BASE_URL . '/admin/challenges/index.php');
    }
    if ($challenge['type'] === 'quiz') {
        $quizQuestions = getQuizQuestions($challengeId);
    }
}

$flash = getFlash();

// Defaults for form
$f = [
    'title'        => $challenge['title']        ?? '',
    'description'  => $challenge['description']  ?? '',
    'type'         => $challenge['type']          ?? 'quiz',
    'instructions' => $challenge['instructions'] ?? '',
    'token_reward' => $challenge['token_reward']  ?? 10,
    'start_date'   => '',
    'end_date'     => '',
    'is_active'    => $challenge ? (bool)$challenge['is_active'] : true,
];

// Format dates for input[type=date]
foreach (['start_date', 'end_date'] as $dk) {
    if (!empty($challenge[$dk])) {
        $v = $challenge[$dk];
        if ($v instanceof DateTimeInterface) {
            $f[$dk] = $v->format('Y-m-d');
        } else {
            $ts = strtotime((string)$v);
            $f[$dk] = $ts ? date('Y-m-d', $ts) : '';
        }
    }
}

$pageTitle  = $isEdit ? 'แก้ไขภารกิจ' : 'สร้างภารกิจใหม่';
$activePage = 'admin_challenges';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl px-5 py-4 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-800' ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center gap-2 text-sm text-j-slate">
        <a href="<?= BASE_URL ?>/admin/challenges/index.php" class="hover:text-j-gold">จัดการภารกิจ</a>
        <span>›</span>
        <span class="text-j-dark"><?= $isEdit ? e($f['title']) : 'สร้างใหม่' ?></span>
    </div>

    <!-- ── CHALLENGE FORM ─────────────────────────────────── -->
    <form method="POST" action="<?= BASE_URL ?>/admin/challenges/edit.php<?= $isEdit ? '?id=' . $challengeId : '' ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_challenge">

        <div class="journal-card p-6 mb-6">
            <h2 class="section-title mb-5"><?= $isEdit ? 'แก้ไขข้อมูลภารกิจ' : 'ข้อมูลภารกิจ' ?></h2>

            <div class="grid gap-5">

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-j-dark mb-1.5">ชื่อภารกิจ <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="<?= e($f['title']) ?>" required
                           class="journal-input" placeholder="เช่น ทำแบบทดสอบความรู้ด้านความปลอดภัย">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-j-dark mb-1.5">คำอธิบาย</label>
                    <textarea name="description" rows="3"
                              class="journal-input resize-none"
                              placeholder="อธิบายภารกิจโดยย่อ"><?= e($f['description']) ?></textarea>
                </div>

                <!-- Type + Token reward -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-j-dark mb-1.5">ประเภทภารกิจ <span class="text-red-500">*</span></label>
                        <select name="type" id="challenge-type" class="journal-input"
                                onchange="handleTypeChange(this.value)">
                            <option value="quiz"  <?= $f['type'] === 'quiz'  ? 'selected' : '' ?>>📝 Quiz (ตอบคำถาม)</option>
                            <option value="photo" <?= $f['type'] === 'photo' ? 'selected' : '' ?>>📷 Photo (ส่งรูปภาพ)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-j-dark mb-1.5">Token รางวัล <span class="text-red-500">*</span></label>
                        <input type="number" name="token_reward" value="<?= (int)$f['token_reward'] ?>"
                               min="1" max="9999" required class="journal-input">
                    </div>
                </div>

                <!-- Date range -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-j-dark mb-1.5">วันเริ่ม <span class="text-red-500">*</span></label>
                        <input type="date" name="start_date" value="<?= e($f['start_date']) ?>"
                               required class="journal-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-j-dark mb-1.5">วันสิ้นสุด <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" value="<?= e($f['end_date']) ?>"
                               required class="journal-input">
                    </div>
                </div>

                <!-- Instructions (photo only) -->
                <div id="instructions-wrap" <?= $f['type'] !== 'photo' ? 'class="hidden"' : '' ?>>
                    <label class="block text-sm font-medium text-j-dark mb-1.5">คำแนะนำการส่งรูป</label>
                    <textarea name="instructions" rows="3"
                              class="journal-input resize-none"
                              placeholder="เช่น ถ่ายรูปพร้อมป้ายชื่อหน่วยงาน..."><?= e($f['instructions']) ?></textarea>
                </div>

                <!-- Active toggle -->
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           <?= $f['is_active'] ? 'checked' : '' ?>
                           class="h-4 w-4 accent-[#dab937]">
                    <label for="is_active" class="text-sm font-medium text-j-dark">เปิดให้ใช้งาน</label>
                </div>

            </div>

            <div class="mt-6 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex gap-3">
                    <button type="submit" class="btn-gold">
                        <?= $isEdit ? 'บันทึกการแก้ไข' : 'สร้างภารกิจ' ?>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/challenges/index.php" class="btn-outline">ยกเลิก</a>
                </div>
                <?php if ($isEdit): ?>
                <form method="POST"
                      action="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= $challengeId ?>"
                      onsubmit="return confirm('ยืนยันลบภารกิจ \"<?= e(addslashes($f['title'])) ?>\"?\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_challenge">
                    <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        ลบภารกิจนี้
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

    </form>

    <!-- ── QUIZ QUESTIONS (only shown when editing a quiz challenge) ── -->
    <?php if ($isEdit && $f['type'] === 'quiz'): ?>
    <div id="quiz-section">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="section-title">คำถาม Quiz</h2>
            <button type="button" onclick="addQuestion()" class="btn-dark text-sm px-4 py-2">
                + เพิ่มคำถาม
            </button>
        </div>

        <!-- Existing questions -->
        <?php if ($quizQuestions): ?>
        <div class="flex flex-col gap-4 mb-6" id="existing-questions">
            <?php foreach ($quizQuestions as $qi => $q): ?>
            <div class="journal-card p-5">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-j-dark">
                        คำถามที่ <?= $qi + 1 ?> — <?= e($q['question_text']) ?>
                    </p>
                    <form method="POST"
                          action="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= $challengeId ?>"
                          class="flex-shrink-0"
                          onsubmit="return confirm('ลบคำถามนี้?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                        <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">
                        <button type="submit" class="text-xs text-red-500 hover:underline">ลบ</button>
                    </form>
                </div>

                <!-- Inline edit form for existing question -->
                <form method="POST" action="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= $challengeId ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_challenge">
                    <!-- Re-send challenge fields as hidden to preserve them -->
                    <input type="hidden" name="title" value="<?= e($f['title']) ?>">
                    <input type="hidden" name="description" value="<?= e($f['description']) ?>">
                    <input type="hidden" name="type" value="quiz">
                    <input type="hidden" name="instructions" value="<?= e($f['instructions']) ?>">
                    <input type="hidden" name="token_reward" value="<?= (int)$f['token_reward'] ?>">
                    <input type="hidden" name="start_date" value="<?= e($f['start_date']) ?>">
                    <input type="hidden" name="end_date" value="<?= e($f['end_date']) ?>">
                    <input type="hidden" name="is_active" value="<?= $f['is_active'] ? 1 : 0 ?>">
                    <!-- Send only this one question -->
                    <input type="hidden" name="q_id[0]" value="<?= (int)$q['question_id'] ?>">

                    <div class="grid gap-3">
                        <div>
                            <label class="block text-xs font-medium text-j-slate mb-1">คำถาม</label>
                            <input type="text" name="q_text[0]" value="<?= e($q['question_text']) ?>"
                                   required class="journal-input text-sm">
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                            <div>
                                <label class="block text-xs font-medium text-j-slate mb-1">
                                    ตัวเลือก <?= $letter ?> <?= in_array($letter, ['A','B']) ? '<span class="text-red-400">*</span>' : '' ?>
                                </label>
                                <input type="text" name="q_<?= $key ?>[0]"
                                       value="<?= e((string)($q['option_' . $key] ?? '')) ?>"
                                       <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                       class="journal-input text-sm">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-j-slate mb-1">คำตอบที่ถูก <span class="text-red-400">*</span></label>
                                <select name="q_correct[0]" required class="journal-input text-sm">
                                    <?php foreach (['A','B','C','D'] as $letter): ?>
                                    <option value="<?= $letter ?>" <?= strtoupper($q['correct_option']) === $letter ? 'selected' : '' ?>><?= $letter ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-j-slate mb-1">คำอธิบาย (ไม่บังคับ)</label>
                                <input type="text" name="q_explain[0]"
                                       value="<?= e((string)($q['explanation'] ?? '')) ?>"
                                       class="journal-input text-sm">
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="btn-dark text-xs px-4 py-1.5">บันทึกคำถาม</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-8 text-center text-sm text-j-slate mb-6">
            ยังไม่มีคำถาม — กด "+ เพิ่มคำถาม" เพื่อเริ่มต้น
        </div>
        <?php endif; ?>

        <!-- New question form (hidden by default, toggled by JS) -->
        <div id="new-question-form" class="hidden journal-card p-5 border-2 border-dashed border-j-gold">
            <h3 class="text-sm font-semibold text-j-dark mb-4">เพิ่มคำถามใหม่</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= $challengeId ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_challenge">
                <input type="hidden" name="title" value="<?= e($f['title']) ?>">
                <input type="hidden" name="description" value="<?= e($f['description']) ?>">
                <input type="hidden" name="type" value="quiz">
                <input type="hidden" name="instructions" value="<?= e($f['instructions']) ?>">
                <input type="hidden" name="token_reward" value="<?= (int)$f['token_reward'] ?>">
                <input type="hidden" name="start_date" value="<?= e($f['start_date']) ?>">
                <input type="hidden" name="end_date" value="<?= e($f['end_date']) ?>">
                <input type="hidden" name="is_active" value="<?= $f['is_active'] ? 1 : 0 ?>">
                <input type="hidden" name="q_id[0]" value="0">

                <div class="grid gap-3">
                    <div>
                        <label class="block text-xs font-medium text-j-slate mb-1">คำถาม <span class="text-red-400">*</span></label>
                        <input type="text" name="q_text[0]" required class="journal-input text-sm"
                               placeholder="พิมพ์คำถาม...">
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                        <div>
                            <label class="block text-xs font-medium text-j-slate mb-1">
                                ตัวเลือก <?= $letter ?> <?= in_array($letter, ['A','B']) ? '<span class="text-red-400">*</span>' : '' ?>
                            </label>
                            <input type="text" name="q_<?= $key ?>[0]"
                                   <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                   class="journal-input text-sm"
                                   placeholder="ตัวเลือก <?= $letter ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-j-slate mb-1">คำตอบที่ถูก <span class="text-red-400">*</span></label>
                            <select name="q_correct[0]" required class="journal-input text-sm">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-j-slate mb-1">คำอธิบาย (ไม่บังคับ)</label>
                            <input type="text" name="q_explain[0]" class="journal-input text-sm"
                                   placeholder="เฉลยหรืออธิบายเพิ่มเติม">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-gold text-sm px-4 py-2">บันทึกคำถาม</button>
                        <button type="button" onclick="cancelAddQuestion()" class="btn-outline text-sm px-4 py-2">ยกเลิก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function handleTypeChange(type) {
    const instrWrap = document.getElementById('instructions-wrap');
    if (instrWrap) instrWrap.classList.toggle('hidden', type !== 'photo');
}

function addQuestion() {
    const form = document.getElementById('new-question-form');
    if (form) {
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function cancelAddQuestion() {
    const form = document.getElementById('new-question-form');
    if (form) form.classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
