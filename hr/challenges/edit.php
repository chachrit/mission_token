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
                setFlash('success', 'ลบภารกิจสำเร็จ');
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

    // Strava condition JSON (only for type=strava)
    $stravaConditionJson = null;
    if ($type === 'strava') {
        // Convert distance to meters based on selected unit
        $distUnit  = ($_POST['strava_dist_unit'] ?? 'm') === 'km' ? 'km' : 'm';
        $distRawInput = str_replace(',', '.', (string)($_POST['strava_min_distance'] ?? '0'));
        $timeRawInput = preg_replace('/[^0-9]/', '', (string)($_POST['strava_min_moving_time'] ?? '0'));
        $elevRawInput = str_replace(',', '.', (string)($_POST['strava_min_elevation'] ?? '0'));

        $distNormalized = preg_replace('/[^0-9.]/', '', $distRawInput);
        $elevNormalized = preg_replace('/[^0-9.]/', '', $elevRawInput);

        if (substr_count($distNormalized, '.') > 1) {
            $firstDot = strpos($distNormalized, '.');
            $distNormalized = substr($distNormalized, 0, $firstDot + 1) . str_replace('.', '', substr($distNormalized, $firstDot + 1));
        }
        if (substr_count($elevNormalized, '.') > 1) {
            $firstDot = strpos($elevNormalized, '.');
            $elevNormalized = substr($elevNormalized, 0, $firstDot + 1) . str_replace('.', '', substr($elevNormalized, $firstDot + 1));
        }

        $rawDist   = max(0, (float)($distNormalized !== '' ? $distNormalized : '0'));
        $minDistM  = $distUnit === 'km' ? $rawDist * 1000 : $rawDist;
        $stravaConditionJson = json_encode([
            'sport_type'      => trim((string)($_POST['strava_sport_type']     ?? 'Run')),
            'min_distance'    => $minDistM,
            'min_moving_time' => max(0, (int)($timeRawInput !== '' ? $timeRawInput : '0')),
            'min_elevation'   => max(0, (float)($elevNormalized !== '' ? $elevNormalized : '0')),
        ], JSON_UNESCAPED_UNICODE);
    }

    if ($title === '' || $startDate === '' || $endDate === '') {
        setFlash('error', 'กรุณากรอกชื่อภารกิจ วันเริ่ม และวันสิ้นสุด');
        redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }

    if ($startDate > $endDate) {
        setFlash('error', 'วันเริ่มต้องไม่เกินวันสิ้นสุด');
        redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
    }

    if ($type === 'quiz') {
        $qTexts = $_POST['q_text'] ?? [];
        $qA     = $_POST['q_a']    ?? [];
        $qB     = $_POST['q_b']    ?? [];

        $hasAtLeastOneQuestion = false;
        foreach ($qTexts as $i => $qTextRaw) {
            $qText = trim((string)$qTextRaw);
            if ($qText === '') {
                continue;
            }

            $a = trim((string)($qA[$i] ?? ''));
            $b = trim((string)($qB[$i] ?? ''));
            if ($a === '' || $b === '') {
                setFlash('error', 'คำถามแบบ Quiz ต้องมีตัวเลือก A และ B อย่างน้อย 1 ข้อ');
                redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
            }

            $hasAtLeastOneQuestion = true;
        }

        if (!$hasAtLeastOneQuestion) {
            setFlash('error', 'กรุณาเพิ่มคำถาม Quiz อย่างน้อย 1 ข้อ');
            redirect(BASE_URL . '/hr/challenges/edit.php' . ($isEdit ? '?id=' . $challengeId : ''));
        }
    }

    try {
        if ($isEdit) {
            $pdo->prepare("
                UPDATE challenges
                SET title = ?, description = ?, type = ?, instructions = ?,
                    token_reward = ?, start_date = ?, end_date = ?, is_active = ?,
                    strava_condition = ?
                WHERE challenge_id = ?
            ")->execute([$title, $description, $type, $instructions,
                         $tokenReward, $startDate, $endDate, $isActive,
                         $stravaConditionJson, $challengeId]);
            $savedId = $challengeId;
            $msg = 'บันทึกการแก้ไขแล้ว';
        } else {
            $pdo->prepare("
                INSERT INTO challenges
                    (title, description, type, instructions, token_reward, start_date, end_date, is_active, created_by, strava_condition)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $description, $type, $instructions,
                         $tokenReward, $startDate, $endDate, $isActive, $adminId,
                         $stravaConditionJson]);
            // Re-query ID because lastInsertId() can be unreliable with pdo_sqlsrv
            $newRow  = $pdo->query("SELECT TOP 1 challenge_id FROM challenges ORDER BY challenge_id DESC")->fetch();
            $savedId = (int)($newRow['challenge_id'] ?? 0);
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
        if ($isEdit) {
            redirect(BASE_URL . '/hr/challenges/edit.php?id=' . $savedId);
        } else {
            redirect(BASE_URL . '/hr/challenges/index.php');
        }

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
    'title'            => $challenge['title']            ?? '',
    'description'      => $challenge['description']      ?? '',
    'type'             => $challenge['type']             ?? 'quiz',
    'instructions'     => $challenge['instructions']     ?? '',
    'token_reward'     => $challenge['token_reward']     ?? 10,
    'start_date'       => '',
    'end_date'         => '',
    'is_active'        => $challenge ? (bool)$challenge['is_active'] : true,
    'strava_condition' => $challenge['strava_condition'] ?? '',
];

// Decode strava_condition for form pre-fill
$sc = [];
if (!empty($f['strava_condition'])) {
    $sc = json_decode($f['strava_condition'], true) ?? [];
}
$scSportType  = $sc['sport_type']      ?? 'Run';
$scMinDist    = $sc['min_distance']    ?? 0;
$scMinTime    = $sc['min_moving_time'] ?? 0;
$scMinElev    = $sc['min_elevation']   ?? 0;

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
<div class="ace-edit-wrap ace-wrap-shell">

    <!-- Aurora blobs -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="ace-page-inner">

        <!-- Page header + back button -->
        <div class="ace-head-row">
            <a href="<?= BASE_URL ?>/hr/challenges/index.php"
               class="ace-back-link"
               title="กลับไปรายการภารกิจ">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
            </a>
            <div>
                <p class="ace-section-title ace-head-kicker">
                    ADMIN — <?= $isEdit ? 'EDIT CHALLENGE' : 'NEW CHALLENGE' ?>
                </p>
                <h1 class="ace-head-title">
                    <?= $isEdit ? e($f['title']) : 'สร้างภารกิจใหม่' ?>
                </h1>
            </div>
        </div>

        <!-- ── CHALLENGE FORM ──────────────────────────────── -->
        <form method="POST" action="<?= BASE_URL ?>/hr/challenges/edit.php<?= $isEdit ? '?id=' . $challengeId : '' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_challenge">

            <div class="ace-card ace-main-card">
                <div class="ace-card-head-row">
                    <div class="ace-card-head-bar"></div>
                    <span class="ace-card-head-title">
                        <?= $isEdit ? 'แก้ไขข้อมูลภารกิจ' : 'ข้อมูลภารกิจ' ?>
                    </span>
                </div>

                <div class="ace-field-stack">

                    <!-- Title -->
                    <div>
                        <label class="ace-label">ชื่อภารกิจ <span class="ace-required">*</span></label>
                        <input type="text" name="title" value="<?= e($f['title']) ?>" required
                               class="journal-input" placeholder="เช่น ทำแบบทดสอบความรู้ด้านความปลอดภัย">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="ace-label">คำอธิบาย</label>
                        <textarea name="description" rows="3" class="journal-input ace-textarea"
                                  placeholder="อธิบายภารกิจโดยย่อ"><?= e($f['description']) ?></textarea>
                    </div>

                    <!-- Type + Token reward -->
                    <div class="ace-grid-2">
                        <div>
                            <label class="ace-label">ประเภทภารกิจ <span class="ace-required">*</span></label>
                            <select name="type" id="challenge-type" class="journal-input"
                                    data-onchange="handleTypeChange(this.value)">
                                <option value="quiz"   <?= $f['type'] === 'quiz'   ? 'selected' : '' ?>>Quiz (ตอบคำถาม)</option>
                                <option value="photo"  <?= $f['type'] === 'photo'  ? 'selected' : '' ?>>Photo (ส่งรูปภาพ)</option>
                                <option value="strava" <?= $f['type'] === 'strava' ? 'selected' : '' ?>>Strava (กิจกรรมออกกำลังกาย)</option>
                            </select>
                        </div>
                        <div>
                            <label class="ace-label">Token รางวัล <span class="ace-required">*</span></label>
                            <input type="number" name="token_reward" value="<?= (int)$f['token_reward'] ?>"
                                   min="1" max="9999" required class="journal-input">
                        </div>
                    </div>

                    <!-- Date range -->
                    <div class="ace-grid-2">
                        <div>
                            <label class="ace-label">วันเริ่ม <span class="ace-required">*</span></label>
                            <input type="date" name="start_date" value="<?= e($f['start_date']) ?>"
                                   required class="journal-input">
                        </div>
                        <div>
                            <label class="ace-label">วันสิ้นสุด <span class="ace-required">*</span></label>
                            <input type="date" name="end_date" value="<?= e($f['end_date']) ?>"
                                   required class="journal-input">
                        </div>
                    </div>

                    <!-- Strava condition (strava only) -->
                    <div id="strava-condition-wrap" class="<?= $f['type'] !== 'strava' ? 'ace-hidden' : '' ?>">
                        <div class="ace-strava-box">
                            <div class="ace-strava-head">
                                <p class="ace-strava-title">Strava Conditions</p>
                                <p class="ace-strava-subtitle">กำหนดเงื่อนไขอย่างน้อย 1 อย่าง นอกนั้นใส่ 0 ได้</p>
                            </div>
                            <div class="ace-grid-strava">
                                <div>
                                    <label class="ace-label">ประเภทกิจกรรม</label>
                                    <select name="strava_sport_type" class="journal-input">
                                        <?php foreach ([
                                            'Run'=>'วิ่ง', 'Ride'=>'ปั่นจักรยาน', 'Walk'=>'เดิน',
                                            'Hike'=>'เดินป่า', 'Swim'=>'ว่ายน้ำ', 'WeightTraining'=>'ยกน้ำหนัก',
                                            'Workout'=>'Workout', 'Yoga'=>'โยคะ',
                                            'VirtualRide'=>'Virtual Ride', 'VirtualRun'=>'Virtual Run',
                                        ] as $val => $lbl): ?>
                                        <option value="<?= e($val) ?>" <?= $scSportType === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="ace-label">ระยะทางขั้นต่ำ <span id="dist-unit-label"><?= $scMinDist >= 1000 ? '(กิโลเมตร)' : '(เมตร)' ?></span> 0=ไม่กำหนด</label>
                                    <div class="ace-dist-row">
                                         <input type="text" name="strava_min_distance" id="strava-dist-input"
                                             inputmode="decimal"
                                             autocomplete="off"
                                             data-oninput="sanitizeNumericInput(this, true)"
                                               value="<?= $scMinDist >= 1000 ? rtrim(rtrim(number_format($scMinDist / 1000, 2, '.', ''), '0'), '.') : (int)$scMinDist ?>"
                                               class="journal-input ace-dist-input"
                                               placeholder="0">
                                        <select name="strava_dist_unit" id="strava-dist-unit"
                                                data-onchange="stravaDistUnitChange(this.value)"
                                                class="journal-input ace-dist-unit">
                                            <option value="m"  <?= $scMinDist < 1000 ? 'selected' : '' ?>>เมตร</option>
                                            <option value="km" <?= $scMinDist >= 1000 ? 'selected' : '' ?>>กิโลเมตร</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="ace-label">เวลาขั้นต่ำ (วินาที) 0=ไม่กำหนด</label>
                                    <input type="text" name="strava_min_moving_time"
                                           inputmode="numeric"
                                           autocomplete="off"
                                           data-oninput="sanitizeNumericInput(this, false)"
                                           value="<?= (int)$scMinTime ?>" class="journal-input" placeholder="เช่น 1800 = 30 นาที">
                                </div>
                                <div>
                                    <label class="ace-label">ความสูงขั้นต่ำ (เมตร) 0=ไม่กำหนด</label>
                                    <input type="text" name="strava_min_elevation"
                                           inputmode="decimal"
                                           autocomplete="off"
                                           data-oninput="sanitizeNumericInput(this, true)"
                                           value="<?= (int)$scMinElev ?>" class="journal-input" placeholder="0">
                                </div>
                            </div>
                            <div class="ace-strava-hint-row">
                                <span class="ace-strava-hint-chip">ตัวอย่าง: วิ่ง 5 กม.</span>
                                <span class="ace-strava-hint-chip">หรือ เวลารวม 1800 วินาที</span>
                                <span class="ace-strava-hint-chip">หรือ สะสมความสูง 100 ม.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions (photo only) -->
                    <div id="instructions-wrap" class="<?= $f['type'] !== 'photo' ? 'ace-hidden' : '' ?>">
                        <label class="ace-label">คำแนะนำการส่งรูป</label>
                        <textarea name="instructions" rows="3" class="journal-input ace-textarea"
                                  placeholder="เช่น ถ่ายรูปพร้อมป้ายชื่อหน่วยงาน..."><?= e($f['instructions']) ?></textarea>
                    </div>

                    <!-- Active toggle -->
                    <div class="ace-active-row">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               <?= $f['is_active'] ? 'checked' : '' ?>
                               class="ace-active-cb">
                        <label for="is_active" class="ace-active-label">
                            เปิดให้ใช้งาน
                        </label>
                    </div>

                </div><!-- /fields -->

                <!-- Action row -->
                <div class="ace-action-row">
                    <div class="ace-action-main">
                        <button type="submit" class="ch-btn-start ace-submit-btn">
                            <?= $isEdit ? 'บันทึกการแก้ไข' : 'สร้างภารกิจ' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/hr/challenges/index.php"
                           class="ace-cancel-link">
                            ยกเลิก
                        </a>
                    </div>

                    <?php if ($isEdit): ?>
                    <button type="button" data-onclick="confirmDeleteChallenge()"
                            class="ace-delete-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        ลบภารกิจนี้
                    </button>
                    <?php endif; ?>
                </div>

            </div><!-- /ace-card -->
        </form>

        <?php if ($isEdit): ?>
        <!-- Delete challenge form — placed OUTSIDE the save form to avoid nested-form bug -->
        <form id="delete-challenge-form" method="POST"
              action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_challenge">
            <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
        </form>
        <script>
        function confirmDeleteChallenge() {
            if (confirm('ยืนยันลบภารกิจ "<?= e(addslashes($f['title'])) ?>"?\nการกระทำนี้ไม่สามารถย้อนกลับได้')) {
                document.getElementById('delete-challenge-form').submit();
            }
        }
        </script>
        <?php endif; ?>

        <!-- ── QUIZ QUESTIONS ──────────────────────────────── -->
        <?php if (!$isEdit || $f['type'] === 'quiz'): ?>
        <div id="quiz-section" class="<?= $f['type'] !== 'quiz' ? 'ace-hidden' : '' ?>">

            <div class="ace-quiz-head-row">
                <div class="ace-quiz-head-left">
                    <div class="ace-quiz-head-bar"></div>
                    <span class="ace-quiz-head-title">คำถาม Quiz</span>
                    <?php if (!empty($quizQuestions)): ?>
                    <span class="ace-quiz-count-pill">
                        <?= count($quizQuestions) ?> ข้อ
                    </span>
                    <?php endif; ?>
                </div>
                <button type="button" data-onclick="addQuestion()" class="ace-add-q-btn">
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
            <div class="ace-q-list" id="existing-questions">
                <?php foreach ($quizQuestions as $qi => $q): ?>
                <div class="ace-q-card">
                    <!-- Question header -->
                    <div class="ace-q-head-row">
                        <div class="ace-q-head-left">
                            <span class="ace-q-index-pill">
                                Q<?= $qi + 1 ?>
                            </span>
                            <p class="ace-q-title">
                                <?= e($q['question_text']) ?>
                            </p>
                        </div>
                        <form method="POST"
                              action="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= $challengeId ?>"
                              class="ace-q-delete-form"
                              data-onsubmit="return confirm('ลบคำถามนี้?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                            <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">
                            <button type="submit" class="ace-q-delete-btn">
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

                        <div class="ace-q-form-stack">
                            <div>
                                <label class="ace-label">คำถาม</label>
                                <input type="text" name="q_text[0]" value="<?= e($q['question_text']) ?>"
                                       required class="journal-input ace-input-md">
                            </div>
                            <div class="ace-grid-2-sm">
                                <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                                <div>
                                    <label class="ace-label">
                                        ตัวเลือก <?= $letter ?>
                                        <?= in_array($letter, ['A','B']) ? '<span class="ace-required">*</span>' : '' ?>
                                    </label>
                                    <input type="text" name="q_<?= $key ?>[0]"
                                           value="<?= e((string)($q['option_' . $key] ?? '')) ?>"
                                           <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                           class="journal-input ace-input-sm">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ace-grid-2-sm">
                                <div>
                                    <label class="ace-label">คำตอบที่ถูก <span class="ace-required">*</span></label>
                                    <select name="q_correct[0]" required
                                            class="journal-input ace-input-sm">
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
                                           class="journal-input ace-input-sm">
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="ace-q-save-btn">
                                    บันทึกคำถาม
                                </button>
                            </div>
                        </div>
                    </form>
                </div><!-- /ace-q-card -->
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="ace-q-empty-wrap">
                <p class="ace-q-empty-note">
                    ยังไม่มีคำถาม — กด "เพิ่มคำถาม" เพื่อเริ่มต้น
                </p>
            </div>
            <?php endif; ?>

            <!-- New question form -->
            <div id="new-question-form" class="ace-hidden">
                <div class="ace-card ace-new-q-card">
                    <div class="ace-new-q-head-row">
                        <div class="ace-new-q-head-bar"></div>
                        <span class="ace-new-q-head-title">เพิ่มคำถามใหม่</span>
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

                        <div class="ace-q-form-stack">
                            <div>
                                <label class="ace-label">คำถาม <span class="ace-required">*</span></label>
                                    <input type="text" name="q_text[0]" required class="journal-input ace-input-md"
                                        placeholder="พิมพ์คำถาม...">
                            </div>
                            <div class="ace-grid-2-sm">
                                <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter): ?>
                                <div>
                                    <label class="ace-label">
                                        ตัวเลือก <?= $letter ?>
                                        <?= in_array($letter, ['A','B']) ? '<span class="ace-required">*</span>' : '' ?>
                                    </label>
                                    <input type="text" name="q_<?= $key ?>[0]"
                                           <?= in_array($letter, ['A','B']) ? 'required' : '' ?>
                                           class="journal-input ace-input-sm"
                                           placeholder="ตัวเลือก <?= $letter ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ace-grid-2-sm">
                                <div>
                                    <label class="ace-label">คำตอบที่ถูก <span class="ace-required">*</span></label>
                                    <select name="q_correct[0]" required class="journal-input ace-input-sm">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ace-label">คำอธิบาย (ไม่บังคับ)</label>
                                    <input type="text" name="q_explain[0]" class="journal-input ace-input-sm"
                                               placeholder="เฉลยหรืออธิบายเพิ่มเติม">
                                </div>
                            </div>
                            <div class="ace-new-q-action-row">
                                <button type="submit" class="ch-btn-start ace-new-q-submit-btn">
                                    บันทึกคำถาม
                                </button>
                                <button type="button" data-onclick="cancelAddQuestion()" class="ace-new-q-cancel-btn">
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
var _aceNewQuestionLastFocus = null;

function sanitizeNumericInput(el, allowDecimal) {
    if (!el) return;
    let value = String(el.value || '');
    value = value.replace(',', '.');
    value = value.replace(allowDecimal ? /[^0-9.]/g : /[^0-9]/g, '');
    if (allowDecimal) {
        const firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }
    }
    el.value = value;
}

function stravaDistUnitChange(unit) {
    const input = document.getElementById('strava-dist-input');
    const label = document.getElementById('dist-unit-label');
    if (!input) return;
    sanitizeNumericInput(input, true);
    const cur = parseFloat(input.value) || 0;
    if (unit === 'km') {
        // was meters → convert to km
        if (input.dataset.lastUnit !== 'km') {
            input.value = cur >= 1000 ? +(cur / 1000).toFixed(2) : cur;
        }
        input.placeholder = 'เช่น 5 = 5 กม';
        if (label) label.textContent = '(กิโลเมตร)';
    } else {
        // was km → convert to meters
        if (input.dataset.lastUnit === 'km') {
            input.value = Math.round(cur * 1000);
        }
        input.placeholder = 'เช่น 5000 = 5 กม';
        if (label) label.textContent = '(เมตร)';
    }
    input.dataset.lastUnit = unit;
}
// Init lastUnit on load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('strava-dist-unit');
    const inp = document.getElementById('strava-dist-input');
    if (sel && inp) {
        inp.dataset.lastUnit = sel.value;
        sanitizeNumericInput(inp, true);
    }

    const minTime = document.querySelector('input[name="strava_min_moving_time"]');
    const minElev = document.querySelector('input[name="strava_min_elevation"]');
    if (minTime) sanitizeNumericInput(minTime, false);
    if (minElev) sanitizeNumericInput(minElev, true);
});

function handleTypeChange(type) {
    const instrWrap  = document.getElementById('instructions-wrap');
    const stravaWrap = document.getElementById('strava-condition-wrap');
    const quizWrap   = document.getElementById('quiz-section');
    const quizNewForm = document.getElementById('new-question-form');
    const isCreateMode = <?= $isEdit ? 'false' : 'true' ?>;
    if (instrWrap)  instrWrap.classList.toggle('ace-hidden', type !== 'photo');
    if (stravaWrap) stravaWrap.classList.toggle('ace-hidden', type !== 'strava');
    if (quizWrap)   quizWrap.classList.toggle('ace-hidden', type !== 'quiz');

    // In create mode, open quiz input form immediately when quiz type is selected.
    if (isCreateMode && quizNewForm) {
        quizNewForm.classList.toggle('ace-hidden', type !== 'quiz');
    }
}
function addQuestion() {
    _aceNewQuestionLastFocus = document.activeElement;
    const form = document.getElementById('new-question-form');
    if (form) {
        form.classList.remove('ace-hidden');
        form.scrollIntoView({ behavior:'smooth', block:'start' });
        setTimeout(function () {
            var firstInput = form.querySelector('input, select, textarea, button');
            if (firstInput) firstInput.focus();
        }, 0);
    }
}
function cancelAddQuestion() {
    const form = document.getElementById('new-question-form');
    if (form) form.classList.add('ace-hidden');
    if (_aceNewQuestionLastFocus && typeof _aceNewQuestionLastFocus.focus === 'function') {
        _aceNewQuestionLastFocus.focus();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('challenge-type');
    if (typeSelect) {
        handleTypeChange(typeSelect.value);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

