<?php
/**
 * admin/submissions.php
 * Admin — review and approve/reject photo submissions
 */

require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];

// ── POST: approve / reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action       = (string)($_POST['action'] ?? '');
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $reviewNote   = trim((string)($_POST['review_note'] ?? ''));

    if ($submissionId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $pdo = getDB();

        // Load submission + challenge reward
        $stmt = $pdo->prepare("
            SELECT cs.submission_id, cs.employee_id, cs.challenge_id,
                   cs.status, cs.submission_type,
                   c.token_reward, c.title AS challenge_title
            FROM   challenge_submissions cs
            JOIN   challenges c ON c.challenge_id = cs.challenge_id
            WHERE  cs.submission_id = ?
        ");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();

        if ($sub && $sub['status'] === 'pending') {
            try {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    $reward = (int)$sub['token_reward'];
                    $pdo->prepare("
                        UPDATE challenge_submissions
                        SET    status        = 'approved',
                               token_awarded = ?,
                               reviewed_at   = GETDATE(),
                               reviewed_by   = ?,
                               review_note   = ?
                        WHERE  submission_id = ?
                    ")->execute([$reward, $adminId, $reviewNote ?: null, $submissionId]);
                    $pdo->commit();

                    awardTokens(
                        (int)$sub['employee_id'],
                        $reward,
                        'photo_reward',
                        $submissionId,
                        'ส่งรูปภาพ: ' . $sub['challenge_title']
                    );
                    setFlash('success', 'อนุมัติแล้ว — พนักงานได้รับ +' . formatTokens($reward) . ' Token');

                } else {
                    $pdo->prepare("
                        UPDATE challenge_submissions
                        SET    status       = 'rejected',
                               token_awarded= 0,
                               reviewed_at  = GETDATE(),
                               reviewed_by  = ?,
                               review_note  = ?
                        WHERE  submission_id = ?
                    ")->execute([$adminId, $reviewNote ?: null, $submissionId]);
                    $pdo->commit();
                    setFlash('success', 'ปฏิเสธการส่งงานแล้ว');
                }

            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('[MissionToken] review submission error: ' . $e->getMessage());
                setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'ไม่พบงานที่ส่ง หรืองานนี้ถูกตรวจสอบไปแล้ว');
        }
    }

    // Return to same filter tab
    $back = isset($_POST['filter']) && $_POST['filter'] === 'all'
        ? BASE_URL . '/admin/submissions.php?filter=all'
        : BASE_URL . '/admin/submissions.php';
    redirect($back);
}

// ── GET: load submissions ────────────────────────────────────
$filter      = (string)($_GET['filter'] ?? 'pending');
$filterWhere = $filter === 'all' ? '' : "WHERE cs.status = 'pending'";

$pdo = getDB();
$submissions = $pdo->query("
    SELECT cs.submission_id, cs.employee_id, cs.challenge_id,
           cs.submission_type, cs.photo_path,
           cs.status, cs.token_awarded, cs.submitted_at,
           cs.reviewed_at, cs.review_note,
           e.full_name, e.employee_code, e.department,
           c.title AS challenge_title, c.token_reward
    FROM   challenge_submissions cs
    JOIN   employees  e ON e.employee_id  = cs.employee_id
    JOIN   challenges c ON c.challenge_id = cs.challenge_id
    $filterWhere
    ORDER BY cs.submitted_at DESC
")->fetchAll();

// Stats
$statStmt = $pdo->query("
    SELECT
        SUM(CASE WHEN status = 'pending'        THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'approved'       THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'rejected'       THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN status = 'auto_approved'  THEN 1 ELSE 0 END) AS auto_count
    FROM challenge_submissions
    WHERE submission_type = 'photo'
");
$stats = $statStmt->fetch() ?: [];

$flash      = getFlash();
$pageTitle  = 'อนุมัติงาน';
$activePage = 'admin_submissions';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl px-5 py-4 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-800' ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="mb-6 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold text-j-dark">อนุมัติงานที่ส่ง</h1>
            <p class="mt-1 text-sm text-j-slate">ตรวจสอบหลักฐานรูปภาพจากพนักงานและให้คะแนน Token</p>
        </div>
        <!-- Quick stats -->
        <div class="flex items-center gap-3 flex-wrap text-sm">
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full"
                 style="background:#fef9c3; color:#854d0e; border:1px solid #fde68a;">
                <span class="font-semibold"><?= (int)($stats['pending_count'] ?? 0) ?></span>
                <span>รอตรวจ</span>
            </div>
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full"
                 style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;">
                <span class="font-semibold"><?= (int)($stats['approved_count'] ?? 0) ?></span>
                <span>อนุมัติแล้ว</span>
            </div>
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full"
                 style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">
                <span class="font-semibold"><?= (int)($stats['rejected_count'] ?? 0) ?></span>
                <span>ปฏิเสธ</span>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-1 mb-6 p-1 rounded-xl w-fit" style="background:#e8e4d6;">
        <a href="<?= BASE_URL ?>/admin/submissions.php"
           class="px-5 py-2 rounded-lg text-sm font-medium transition-all
                  <?= $filter !== 'all' ? 'bg-white text-j-dark shadow-sm' : 'text-j-slate hover:text-j-dark' ?>">
            รอตรวจสอบ
            <?php if ((int)($stats['pending_count'] ?? 0) > 0): ?>
            <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full font-bold"
                  style="background:#d2592a; color:#fff;"><?= (int)$stats['pending_count'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/submissions.php?filter=all"
           class="px-5 py-2 rounded-lg text-sm font-medium transition-all
                  <?= $filter === 'all' ? 'bg-white text-j-dark shadow-sm' : 'text-j-slate hover:text-j-dark' ?>">
            ทั้งหมด
        </a>
    </div>

    <?php if (empty($submissions)): ?>
    <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-20
                text-center text-sm text-j-slate">
        <?= $filter === 'all' ? 'ยังไม่มีการส่งงานในระบบ' : '&#10003; ไม่มีงานรอตรวจสอบในขณะนี้' ?>
    </div>

    <?php else: ?>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($submissions as $sub):
        $isPending  = $sub['status'] === 'pending';
        $isApproved = in_array($sub['status'], ['approved', 'auto_approved'], true);
        $isRejected = $sub['status'] === 'rejected';

        if ($isApproved)     { $borderColor = '#bbf7d0'; $bgColor = '#f0fdf4'; }
        elseif ($isRejected) { $borderColor = '#fecaca'; $bgColor = '#fff5f5'; }
        else                 { $borderColor = '#fde68a'; $bgColor = '#fffbeb'; }

        $photoUrl = !empty($sub['photo_path'])
            ? BASE_URL . '/uploads/submissions/' . rawurlencode($sub['photo_path'])
            : null;

        $submittedDate = date('d/m/Y H:i', strtotime((string)$sub['submitted_at']));
    ?>
    <div class="journal-card overflow-hidden flex flex-col"
         style="border-color:<?= $borderColor ?>; background:<?= $isApproved || $isRejected ? $bgColor : '#fdfcdf' ?>;">

        <!-- Card header: status bar -->
        <div class="px-4 py-2.5 flex items-center justify-between text-xs font-semibold"
             style="background:<?= $borderColor ?>; color:<?= $isApproved ? '#166534' : ($isRejected ? '#991b1b' : '#854d0e') ?>;">
            <span>
                <?php if ($isPending):  ?>&#9203; รอตรวจสอบ
                <?php elseif ($isApproved): ?>&#10003; อนุมัติแล้ว
                <?php else: ?>&#10005; ปฏิเสธ<?php endif; ?>
            </span>
            <span class="font-normal opacity-75"><?= $submittedDate ?></span>
        </div>

        <!-- Photo preview -->
        <?php if ($photoUrl): ?>
        <a href="<?= $photoUrl ?>" target="_blank" rel="noopener"
           class="block overflow-hidden flex-shrink-0"
           style="background:#e8e4d6; height:180px;">
            <img src="<?= $photoUrl ?>" alt="หลักฐาน"
                 loading="lazy"
                 class="w-full h-full object-cover transition-transform hover:scale-105"
                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-xs text-j-slate\'>ไม่สามารถโหลดรูปภาพได้</div>'">
        </a>
        <?php else: ?>
        <div class="flex items-center justify-center text-xs text-j-slate flex-shrink-0"
             style="height:80px; background:#f5f3ea;">
            ไม่มีไฟล์แนบ
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="px-4 pt-3 pb-2 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-j-slate mb-1">ภารกิจ</p>
            <p class="font-semibold text-j-dark leading-snug text-sm mb-3">
                <?= e($sub['challenge_title']) ?>
                <span class="ml-1.5 font-normal text-j-gold text-xs">+<?= formatTokens((int)$sub['token_reward']) ?> Token</span>
            </p>

            <div class="flex items-center gap-2 text-sm">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                     style="background:#091113; color:#dab937;">
                    <?= mb_substr($sub['full_name'], 0, 1) ?>
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-j-dark truncate"><?= e($sub['full_name']) ?></p>
                    <p class="text-xs text-j-slate truncate"><?= e($sub['department'] ?? '-') ?> &bull; <?= e($sub['employee_code']) ?></p>
                </div>
            </div>

            <?php if (!empty($sub['review_note'])): ?>
            <p class="mt-2.5 text-xs text-j-slate italic border-t border-[#e6e2d6] pt-2">
                หมายเหตุ: <?= e($sub['review_note']) ?>
            </p>
            <?php endif; ?>

            <?php if ($isApproved && $sub['token_awarded'] > 0): ?>
            <p class="mt-2 text-xs font-semibold text-green-700">
                &#10003; มอบ +<?= formatTokens((int)$sub['token_awarded']) ?> Token แล้ว
            </p>
            <?php endif; ?>
        </div>

        <!-- Action buttons (pending only) -->
        <?php if ($isPending): ?>
        <div class="px-4 pb-4">
            <div id="note-area-<?= $sub['submission_id'] ?>" class="hidden mb-2">
                <textarea name="review_note_display"
                          id="note-input-<?= $sub['submission_id'] ?>"
                          rows="2"
                          placeholder="หมายเหตุถึงพนักงาน (ไม่บังคับ)…"
                          class="journal-input text-xs resize-none"></textarea>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="<?= BASE_URL ?>/admin/submissions.php"
                      class="flex-1"
                      onsubmit="syncNote(<?= $sub['submission_id'] ?>, 'approve-note-<?= $sub['submission_id'] ?>')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <input type="hidden" name="review_note" id="approve-note-<?= $sub['submission_id'] ?>">
                    <button type="submit" class="btn-gold w-full text-sm py-2">
                        &#10003; อนุมัติ
                    </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/admin/submissions.php"
                      class="flex-1"
                      onsubmit="return confirmReject(<?= $sub['submission_id'] ?>)">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <input type="hidden" name="review_note" id="reject-note-<?= $sub['submission_id'] ?>">
                    <button type="submit" class="btn-danger w-full text-sm py-2">
                        &#10005; ปฏิเสธ
                    </button>
                </form>
            </div>
            <button onclick="toggleNote(<?= $sub['submission_id'] ?>)"
                    class="mt-2 w-full text-xs text-j-slate hover:text-j-dark transition-colors py-1">
                + เพิ่มหมายเหตุ
            </button>
        </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleNote(id) {
    var area = document.getElementById('note-area-' + id);
    if (!area) return;
    var hidden = area.classList.contains('hidden');
    area.classList.toggle('hidden', !hidden);
    if (hidden) document.getElementById('note-input-' + id).focus();
}

function syncNote(id, targetId) {
    var inp = document.getElementById('note-input-' + id);
    var tgt = document.getElementById(targetId);
    if (inp && tgt) tgt.value = inp.value;
}

function confirmReject(id) {
    var inp  = document.getElementById('note-input-' + id);
    var note = document.getElementById('reject-note-' + id);
    if (inp && note) note.value = inp.value;
    return confirm('ยืนยันปฏิเสธการส่งงานนี้?\nพนักงานจะไม่ได้รับ Token และสามารถเห็นหมายเหตุที่คุณใส่ไว้');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
