<?php
/**
 * admin/challenges/edit.php
 * Admin — create or edit a challenge + manage quiz questions
 *
 * GET  ?id=N  → edit existing challenge
 * GET  (no id) → create new
 * POST → save challenge + questions
 */

require_once __DIR__ . '/../../includes/hr_check.php';
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
            try {
                $pdo->beginTransaction();
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
        redirect(BASE_URL . '/hr/challenges/index.php');
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
        redirect(BASE_URL . '/hr/challenges/edit.php?id=' . $cid);
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
        redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }

    if ($startDate > $endDate) {
        setFlash('error', 'วันเริ่มต้องไม่เกินวันสิ้นสุด');
        redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
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
        redirect(BASE_URL . '/hr/challenges/edit.php?id=' . $savedId);

    } catch (Throwable $e) {
        error_log('[MissionToken] challenge save error: ' . $e->getMessage());
        setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }
}

// ── GET: load challenge if editing ───────────────────────────
$challenge     = null;
$quizQuestions = [];

if ($isEdit) {
    $challenge = getChallenge($challengeId);
    if (!$challenge) {
        setFlash('error', 'ไม่พบภารกิจนี้');
        redirect(BASE_URL . '/hr/challenges/index.php');
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

<style>
/* ── Admin Challenge Edit  prefix: ace- ─────────────────── */
.ace-label {
    font-size: 0.72rem; font-weight: 700; color: #4a4e57;
    letter-spacing: 0.08em; text-transform: uppercase;
    margin-bottom: 0.35rem; display: block;
}
.ace-edit-wrap .journal-input {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.12);
    color: #eeebe1;
}
.ace-edit-wrap .journal-input:focus {
    border-color: rgba(218,185,55,0.45);
    background: rgba(255,255,255,0.09);
    outline: none;
}
.ace-edit-wrap .journal-input::placeholder { color: #3a3e43; }
.ace-edit-wrap select.journal-input option  { background: #1a1e22; color: #eeebe1; }
.ace-edit-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.5); }

.ace-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 1.5rem;
    backdrop-filter: blur(8px);
}
.ace-q-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 1.25rem;
}
.ace-q-card:hover { border-color: rgba(255,255,255,0.12); }
.ace-section-title {
    font-size: 0.60rem; font-weight: 700; letter-spacing: 0.30em;
    text-transform: uppercase; color: rgba(218,185,55,0.55);
}
</style>

<div class="ace-edit-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

    <!-- Aurora blobs -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div style="position:relative; z-index:1; max-width:860px; margin:0 auto;
                padding:2rem 1.25rem 5rem;">

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

        <!-- Page header + back button -->
        <div style="display:flex; align-items:flex-start; gap:1rem; margin-bottom:2rem;">
            <a href="<?= BASE_URL ?>/hr/challenges/index.php"
               style="flex-shrink:0; margin-top:4px; width:34px; height:34px; border-radius:10px;
                      background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12);
                      color:#6b6e77; display:inline-flex; align-items:center; justify-content:center;
                      text-decoration:none; transition:all 0.15s;"
               onmouseover="this.style.background='rgba(255,255,255,0.10)'; this.style.color='#eeebe1';"
               onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.color='#6b6e77';"
               title="กลับไปรายการภารกิจ">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
            </a>
            <div>
                <p class="ace-section-title" style="margin-bottom:0.3rem;">
                    ⬡ &nbsp;ADMIN — <?= $isEdit ? 'EDIT CHALLENGE' : 'NEW CHALLENGE' ?>
                </p>
                <h1 style="font-size:1.5rem; font-weight:800; color:#eeebe1;
                           margin:0; letter-spacing:-0.01em;">
                    <?= $isEdit ? e($f['title']) : 'สร้างภารกิจใหม่' ?>
                </h1>
            </div>
        </div>

        <!-- ── CHALLENGE FORM ──────────────────────────────── -->
        <form method="POST" action="<?= BASE_URL ?>/hr/challenges/edit.php<?= $isEdit ? '?id=' . $challengeId : '' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_challenge">

            <div class="ace-card" style="margin-bottom:1.5rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.35rem;">
                    <div style="width:4px; height:18px; background:linear-gradient(180deg,#dab937,#c9a830);
                                border-radius:999px; flex-shrink:0;"></div>
                    <span style="font-size:0.95rem; font-weight:700; color:#eeebe1;">
                        <?= $isEdit ? 'แก้ไขข้อมูลภารกิจ' : 'ข้อมูลภารกิจ' ?>
                    </span>
                </div>

                <div style="display:flex; flex-direction:column; gap:1.1rem;">

                    <!-- Title -->
                    <div>
                        <label class="ace-label">ชื่อภารกิจ <span style="color:#d2592a;">*</span></label>
                        <input type="text" name="title" value="<?= e($f['title']) ?>" required
                               class="journal-input" placeholder="เช่น ทำแบบทดสอบความรู้ด้านความปลอดภัย">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="ace-label">คำอธิบาย</label>
                        <textarea name="description" rows="3" class="journal-input"
                                  style="resize:vertical;"
                                  placeholder="อธิบายภารกิจโดยย่อ"><?= e($f['description']) ?></textarea>
                    </div>

                    <!-- Type + Token reward -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label class="ace-label">ประเภทภารกิจ <span style="color:#d2592a;">*</span></label>
                            <select name="type" id="challenge-type" class="journal-input"
                                    onchange="handleTypeChange(this.value)">
                                <option value="quiz"  <?= $f['type'] === 'quiz'  ? 'selected' : '' ?>>📝 Quiz (ตอบคำถาม)</option>
                                <option value="photo" <?= $f['type'] === 'photo' ? 'selected' : '' ?>>📷 Photo (ส่งรูปภาพ)</option>
                            </select>
                        </div>
                        <div>
                            <label class="ace-label">Token รางวัล <span style="color:#d2592a;">*</span></label>
                            <input type="number" name="token_reward" value="<?= (int)$f['token_reward'] ?>"
                                   min="1" max="9999" required class="journal-input">
                        </div>
                    </div>

                    <!-- Date range -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label class="ace-label">วันเริ่ม <span style="color:#d2592a;">*</span></label>
                            <input type="date" name="start_date" value="<?= e($f['start_date']) ?>"
                                   required class="journal-input">
                        </div>
                        <div>
                            <label class="ace-label">วันสิ้นสุด <span style="color:#d2592a;">*</span></label>
                            <input type="date" name="end_date" value="<?= e($f['end_date']) ?>"
                                   required class="journal-input">
                        </div>
                    </div>

                    <!-- Instructions (photo only) -->
                    <div id="instructions-wrap" <?= $f['type'] !== 'photo' ? 'style="display:none;"' : '' ?>>
                        <label class="ace-label">คำแนะนำการส่งรูป</label>
                        <textarea name="instructions" rows="3" class="journal-input"
                                  style="resize:vertical;"
                                  placeholder="เช่น ถ่ายรูปพร้อมป้ายชื่อหน่วยงาน..."><?= e($f['instructions']) ?></textarea>
                    </div>

                    <!-- Active toggle -->
                    <div style="display:flex; align-items:center; gap:0.65rem; padding:0.85rem 1rem;
                                background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                                border-radius:12px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               <?= $f['is_active'] ? 'checked' : '' ?>
                               style="width:16px; height:16px; accent-color:#dab937; cursor:pointer;">
                        <label for="is_active"
                               style="font-size:0.85rem; font-weight:500; color:#eeebe1; cursor:pointer;">
                            เปิดให้ใช้งาน
                        </label>
                    </div>

                </div><!-- /fields -->

                <!-- Action row -->
                <div style="margin-top:1.5rem; display:flex; align-items:center;
                             justify-content:space-between; flex-wrap:wrap; gap:0.75rem;
                             padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.07);">
                    <div style="display:flex; gap:0.65rem;">
                        <button type="submit" class="ch-btn-start"
                                style="padding:0.55rem 1.35rem; font-size:0.85rem; border-radius:10px;">
                            <?= $isEdit ? 'บันทึกการแก้ไข' : 'สร้างภารกิจ' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/hr/challenges/index.php"
                           style="padding:0.55rem 1.1rem; font-size:0.82rem; font-weight:600;
                                  border-radius:10px; text-decoration:none; font-family:'Prompt',sans-serif;
                                  background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12);
                                  color:#6b6e77; transition:all 0.15s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.09)'; this.style.color='#eeebe1';"
                           onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.color='#6b6e77';">
                            ยกเลิก
                        </a>
                    </div>

                    <?php if ($isEdit): ?>
                    <form method="POST"
                          action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>"
                          onsubmit="return confirm('ยืนยันลบภารกิจ &quot;<?= e(addslashes($f['title'])) ?>&quot;?\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_challenge">
                        <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                        <button type="submit"
                                style="display:inline-flex; align-items:center; gap:0.4rem;
                                       padding:0.50rem 1rem; font-size:0.78rem; font-weight:600;
                                       border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                       background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.22);
                                       color:#d2592a; transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(210,89,42,0.18)'"
                                onmouseout="this.style.background='rgba(210,89,42,0.08)'">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            ลบภารกิจนี้
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

            </div><!-- /ace-card -->
        </form>

        <!-- ── QUIZ QUESTIONS ──────────────────────────────── -->
        <?php if ($isEdit && $f['type'] === 'quiz'): ?>
        <div id="quiz-section">

            <div style="display:flex; align-items:center; justify-content:space-between;
                        gap:1rem; margin-bottom:1.25rem;">
                <div style="display:flex; align-items:center; gap:0.55rem;">
                    <div style="width:4px; height:18px; background:linear-gradient(180deg,#4f8b98,#3a6e7a);
                                border-radius:999px;"></div>
                    <span style="font-size:0.95rem; font-weight:700; color:#eeebe1;">คำถาม Quiz</span>
                    <?php if (!empty($quizQuestions)): ?>
                    <span style="font-size:0.65rem; font-weight:700; background:rgba(79,139,152,0.15);
                                 color:#4f8b98; border:1px solid rgba(79,139,152,0.28);
                                 border-radius:999px; padding:0.12rem 0.5rem;">
                        <?= count($quizQuestions) ?> ข้อ
                    </span>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addQuestion()"
                        style="padding:0.45rem 1rem; font-size:0.80rem; font-weight:600;
                               border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14);
                               color:#eeebe1; transition:background 0.15s; display:inline-flex;
                               align-items:center; gap:0.35rem;"
                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    เพิ่มคำถาม
                </button>
            </div>

            <!-- Existing questions -->
            <?php if (!empty($quizQuestions)): ?>
            <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem;"
                 id="existing-questions">
                <?php foreach ($quizQuestions as $qi => $q): ?>
                <div class="ace-q-card">
                    <!-- Question header -->
                    <div style="display:flex; align-items:flex-start; justify-content:space-between;
                                gap:1rem; margin-bottom:1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:0.62rem; font-weight:700; padding:0.18rem 0.55rem;
                                         border-radius:999px; background:rgba(79,139,152,0.14);
                                         color:#4f8b98; border:1px solid rgba(79,139,152,0.25);">
                                Q<?= $qi + 1 ?>
                            </span>
                            <p style="font-size:0.85rem; font-weight:600; color:#eeebe1; margin:0;">
                                <?= e($q['question_text']) ?>
                            </p>
                        </div>
                        <form method="POST"
                              action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>"
                              style="flex-shrink:0;"
                              onsubmit="return confirm('ลบคำถามนี้?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                            <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">
                            <button type="submit"
                                    style="font-size:0.68rem; color:#d2592a; background:none; border:none;
                                           cursor:pointer; font-family:'Prompt',sans-serif; padding:2px 4px;"
                                    onmouseover="this.style.textDecoration='underline'"
                                    onmouseout="this.style.textDecoration='none'">
                                ลบ
                            </button>
                        </form>
                    </div>

                    <!-- Edit form -->
                    <form method="POST" action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_challenge">
                        <input type="hidden" name="title"        value="<?= e($f['title']) ?>">
                        <input type="hidden" name="description"  value="<?= e($f['description']) ?>">
                        <input type="hidden" name="type"         value="quiz">
                        <input type="hidden" name="instructions" value="<?= e($f['instructions']) ?>">
                        <input type="hidden" name="token_reward" value="<?= (int)$f['token_reward'] ?>">
                        <input type="hidden" name="start_date"   value="<?= e($f['start_date']) ?>">
                        <input type="hidden" name="end_date"     value="<?= e($f['end_date']) ?>">
                        <input type="hidden" name="is_active"    value="<?= $f['is_active'] ? 1 : 0 ?>">
                        <input type="hidden" name="q_id[0]"      value="<?= (int)$q['question_id'] ?>">

                        <div style="display:flex; flex-direction:column; gap:0.75rem;">
                            <div>
                                <label class="ace-label">คำถาม</label>
                                <input type="text" name="q_text[0]" value="<?= e($q['question_text']) ?>"
                                       required class="journal-input" style="font-size:0.85rem;">
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                                <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                                <div>
                                    <label class="ace-label">
                                        ตัวเลือก <?= $letter ?>
                                        <?= in_array($letter, ['A','B']) ? '<span style="color:#d2592a;">*</span>' : '' ?>
                                    </label>
                                    <input type="text" name="q_<?= $key ?>[0]"
                                           value="<?= e((string)($q['option_' . $key] ?? '')) ?>"
                                           <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                           class="journal-input" style="font-size:0.83rem;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                                <div>
                                    <label class="ace-label">คำตอบที่ถูก <span style="color:#d2592a;">*</span></label>
                                    <select name="q_correct[0]" required
                                            class="journal-input" style="font-size:0.83rem;">
                                        <?php foreach (['A','B','C','D'] as $letter): ?>
                                        <option value="<?= $letter ?>"
                                                <?= strtoupper($q['correct_option']) === $letter ? 'selected' : '' ?>>
                                            <?= $letter ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="ace-label">คำอธิบาย (ไม่บังคับ)</label>
                                    <input type="text" name="q_explain[0]"
                                           value="<?= e((string)($q['explanation'] ?? '')) ?>"
                                           class="journal-input" style="font-size:0.83rem;">
                                </div>
                            </div>
                            <div>
                                <button type="submit"
                                        style="padding:0.40rem 1rem; font-size:0.78rem; font-weight:600;
                                               border-radius:8px; cursor:pointer; font-family:'Prompt',sans-serif;
                                               background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                                               color:#eeebe1; transition:background 0.15s;"
                                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                                        onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                                    บันทึกคำถาม
                                </button>
                            </div>
                        </div>
                    </form>
                </div><!-- /ace-q-card -->
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="background:rgba(255,255,255,0.025); border:1px dashed rgba(255,255,255,0.12);
                        border-radius:16px; padding:3rem; text-align:center; margin-bottom:1.5rem;">
                <p style="font-size:1.5rem; margin:0 0 0.4rem; opacity:0.15;">❓</p>
                <p style="font-size:0.85rem; color:#6b6e77; margin:0;">
                    ยังไม่มีคำถาม — กด "เพิ่มคำถาม" เพื่อเริ่มต้น
                </p>
            </div>
            <?php endif; ?>

            <!-- New question form -->
            <div id="new-question-form" style="display:none;">
                <div class="ace-card" style="border-color:rgba(218,185,55,0.20);">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.1rem;">
                        <div style="width:4px; height:16px; background:#dab937; border-radius:999px;"></div>
                        <span style="font-size:0.88rem; font-weight:700; color:#eeebe1;">เพิ่มคำถามใหม่</span>
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"       value="save_challenge">
                        <input type="hidden" name="title"        value="<?= e($f['title']) ?>">
                        <input type="hidden" name="description"  value="<?= e($f['description']) ?>">
                        <input type="hidden" name="type"         value="quiz">
                        <input type="hidden" name="instructions" value="<?= e($f['instructions']) ?>">
                        <input type="hidden" name="token_reward" value="<?= (int)$f['token_reward'] ?>">
                        <input type="hidden" name="start_date"   value="<?= e($f['start_date']) ?>">
                        <input type="hidden" name="end_date"     value="<?= e($f['end_date']) ?>">
                        <input type="hidden" name="is_active"    value="<?= $f['is_active'] ? 1 : 0 ?>">
                        <input type="hidden" name="q_id[0]"      value="0">

                        <div style="display:flex; flex-direction:column; gap:0.75rem;">
                            <div>
                                <label class="ace-label">คำถาม <span style="color:#d2592a;">*</span></label>
                                <input type="text" name="q_text[0]" required class="journal-input"
                                       placeholder="พิมพ์คำถาม..." style="font-size:0.85rem;">
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                                <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                                <div>
                                    <label class="ace-label">
                                        ตัวเลือก <?= $letter ?>
                                        <?= in_array($letter, ['A','B']) ? '<span style="color:#d2592a;">*</span>' : '' ?>
                                    </label>
                                    <input type="text" name="q_<?= $key ?>[0]"
                                           <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                           class="journal-input" style="font-size:0.83rem;"
                                           placeholder="ตัวเลือก <?= $letter ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                                <div>
                                    <label class="ace-label">คำตอบที่ถูก <span style="color:#d2592a;">*</span></label>
                                    <select name="q_correct[0]" required class="journal-input" style="font-size:0.83rem;">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ace-label">คำอธิบาย (ไม่บังคับ)</label>
                                    <input type="text" name="q_explain[0]" class="journal-input"
                                           style="font-size:0.83rem;"
                                           placeholder="เฉลยหรืออธิบายเพิ่มเติม">
                                </div>
                            </div>
                            <div style="display:flex; gap:0.65rem; padding-top:0.5rem;
                                        border-top:1px solid rgba(255,255,255,0.06);">
                                <button type="submit" class="ch-btn-start"
                                        style="padding:0.48rem 1.1rem; font-size:0.82rem; border-radius:9px;">
                                    บันทึกคำถาม
                                </button>
                                <button type="button" onclick="cancelAddQuestion()"
                                        style="padding:0.45rem 1rem; font-size:0.80rem; font-weight:600;
                                               border-radius:9px; cursor:pointer; font-family:'Prompt',sans-serif;
                                               background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12);
                                               color:#6b6e77; transition:background 0.15s;"
                                        onmouseover="this.style.background='rgba(255,255,255,0.09)'"
                                        onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                                    ยกเลิก
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- /quiz-section -->
        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /ace-edit-wrap -->

<script>
function handleTypeChange(type) {
    const instrWrap = document.getElementById('instructions-wrap');
    if (instrWrap) instrWrap.style.display = type !== 'photo' ? 'none' : '';
}
function addQuestion() {
    const form = document.getElementById('new-question-form');
    if (form) { form.style.display = ''; form.scrollIntoView({ behavior:'smooth', block:'start' }); }
}
function cancelAddQuestion() {
    const form = document.getElementById('new-question-form');
    if (form) form.style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
