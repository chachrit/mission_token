<?php
/**
 * admin/submissions.php
 * Admin — review and approve/reject photo submissions
 */

require_once __DIR__ . '/../includes/hr_check.php';
require_once __DIR__ . '/../includes/functions.php';

$adminId    = (int)$_SESSION['employee_id'];
$canManage  = in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true);

// ── POST: approve / reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if (!$canManage) {
        setFlash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect(BASE_URL . '/hr/submissions.php');
    }

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
            FROM   dbo.challenge_submissions cs
            JOIN   dbo.challenges c ON c.challenge_id = cs.challenge_id
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
                        UPDATE dbo.challenge_submissions
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
                        UPDATE dbo.challenge_submissions
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
        ? BASE_URL . '/hr/submissions.php?filter=all'
        : BASE_URL . '/hr/submissions.php';
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
           e.full_name, e.employee_code, e.position,
           c.title AS challenge_title, c.token_reward
    FROM   dbo.challenge_submissions cs
    JOIN   dbo.employees  e ON e.employee_id  = cs.employee_id
    JOIN   dbo.challenges c ON c.challenge_id = cs.challenge_id
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
    FROM dbo.challenge_submissions
    WHERE submission_type = 'photo'
");
$stats = $statStmt->fetch() ?: [];

$flash      = getFlash();
$pageTitle  = 'อนุมัติงาน';
$activePage = 'admin_submissions';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="asb-submissions-wrap asb-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

    <!-- Aurora blobs -->
    <div style="position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden;" aria-hidden="true">
        <div style="position:absolute; width:600px; height:600px; border-radius:50%;
                    background:radial-gradient(circle,rgba(218,185,55,0.07) 0%,transparent 65%);
                    top:-120px; right:-120px; filter:blur(70px);
                    animation:ch-aurora-drift 20s ease-in-out infinite alternate;"></div>
        <div style="position:absolute; width:500px; height:500px; border-radius:50%;
                    background:radial-gradient(circle,rgba(79,139,152,0.05) 0%,transparent 65%);
                    bottom:-100px; left:-80px; filter:blur(80px);
                    animation:ch-aurora-drift 24s ease-in-out infinite alternate-reverse;"></div>
    </div>

    <div style="position:relative; z-index:1; max-width:80rem; margin:0 auto; padding:2.5rem 1.5rem 5rem;">

        <!-- Page header -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between;
                    flex-wrap:wrap; gap:1rem; margin-bottom:2rem;
                    padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);">
            <div>
                <p style="font-size:0.55rem; font-weight:700; letter-spacing:0.40em;
                          text-transform:uppercase; color:rgba(218,185,55,0.60); margin:0 0 0.5rem;">
                    ⬡ &nbsp;ADMIN — SUBMISSIONS
                </p>
                <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0 0 0.25rem; letter-spacing:-0.02em;">
                    อนุมัติงานที่ส่ง
                </h1>
                <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                    ตรวจสอบหลักฐานรูปภาพจากพนักงานและให้คะแนน Token
                </p>
            </div>
            <!-- Stats chips -->
            <div style="display:flex; align-items:center; gap:0.55rem; flex-wrap:wrap;">
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(245,158,11,0.10); color:#fbbf24; border:1px solid rgba(245,158,11,0.25);">
                    รอตรวจ: <?= (int)($stats['pending_count'] ?? 0) ?>
                </span>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(81,142,92,0.12); color:#7ec98a; border:1px solid rgba(81,142,92,0.28);">
                    อนุมัติ: <?= (int)($stats['approved_count'] ?? 0) ?>
                </span>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(210,89,42,0.10); color:#d2592a; border:1px solid rgba(210,89,42,0.25);">
                    ปฏิเสธ: <?= (int)($stats['rejected_count'] ?? 0) ?>
                </span>
            </div>
        </div>

        <?php if ($flash): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.85rem 1.1rem; font-size:0.85rem;
                    <?= $flash['type'] === 'success'
                        ? 'background:rgba(81,142,92,0.12); border:1px solid rgba(81,142,92,0.28); color:#7ec98a;'
                        : 'background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28); color:#d2592a;' ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.75rem;">
            <a href="<?= BASE_URL ?>/hr/submissions.php"
               class="asb-filter-tab <?= $filter !== 'all' ? 'active' : '' ?>">
                รอตรวจสอบ
                <?php if ((int)($stats['pending_count'] ?? 0) > 0): ?>
                <span style="background:#d2592a; color:#fff; border-radius:999px;
                             padding:0.05rem 0.45rem; font-size:0.65rem; font-weight:700;">
                    <?= (int)$stats['pending_count'] ?>
                </span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/hr/submissions.php?filter=all"
               class="asb-filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                ทั้งหมด
            </a>
        </div>

        <?php if (empty($submissions)): ?>
        <div style="border-radius:16px; padding:5rem 2rem; text-align:center;
                    background:rgba(255,255,255,0.02); border:1px dashed rgba(255,255,255,0.08);">
            <p style="font-size:2rem; opacity:0.15; margin-bottom:0.6rem;">✓</p>
            <p style="font-size:0.88rem; color:#6b6e77; margin:0;">
                <?= $filter === 'all' ? 'ยังไม่มีการส่งงานในระบบ' : 'ไม่มีงานรอตรวจสอบในขณะนี้' ?>
            </p>
        </div>

        <?php else: ?>
        <div style="display:grid; gap:1.25rem;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
        <?php foreach ($submissions as $sub):
            $isPending  = $sub['status'] === 'pending';
            $isApproved = in_array($sub['status'], ['approved', 'auto_approved'], true);
            $isRejected = $sub['status'] === 'rejected';

            $cardClass = $isPending ? 'pending' : ($isApproved ? 'approved' : 'rejected');

            if ($isApproved) {
                $barBg    = 'rgba(81,142,92,0.18)';
                $barColor = '#7ec98a';
                $barBorder= 'rgba(81,142,92,0.28)';
            } elseif ($isRejected) {
                $barBg    = 'rgba(210,89,42,0.14)';
                $barColor = '#d2592a';
                $barBorder= 'rgba(210,89,42,0.28)';
            } else {
                $barBg    = 'rgba(245,158,11,0.12)';
                $barColor = '#fbbf24';
                $barBorder= 'rgba(245,158,11,0.25)';
            }

            $photoName = !empty($sub['photo_path']) ? basename($sub['photo_path']) : null;
            $photoUrl  = $photoName
                ? BASE_URL . '/uploads/submissions/' . rawurlencode($photoName)
                : null;

            $submittedDate = date('d/m/Y H:i', strtotime((string)$sub['submitted_at']));
        ?>
        <div class="asb-card <?= $cardClass ?>">

            <!-- Status bar -->
            <div style="padding:0.55rem 1rem; display:flex; align-items:center; justify-content:space-between;
                        background:<?= $barBg ?>; border-bottom:1px solid <?= $barBorder ?>;
                        font-size:0.72rem; font-weight:700; color:<?= $barColor ?>;">
                <span>
                    <?php if ($isPending): ?>⏳ รอตรวจสอบ
                    <?php elseif ($isApproved): ?>✓ อนุมัติแล้ว
                    <?php else: ?>✕ ปฏิเสธ<?php endif; ?>
                </span>
                <span style="font-weight:400; opacity:0.70; font-size:0.68rem;"><?= $submittedDate ?></span>
            </div>

            <!-- Photo preview -->
            <?php if ($photoUrl): ?>
            <a href="<?= $photoUrl ?>" target="_blank" rel="noopener"
               style="display:block; overflow:hidden; flex-shrink:0;
                      background:rgba(255,255,255,0.04); height:180px;">
                <img src="<?= $photoUrl ?>" alt="หลักฐาน"
                     loading="lazy"
                     style="width:100%; height:100%; object-fit:cover; transition:transform 0.25s;"
                     onmouseover="this.style.transform='scale(1.04)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onerror="this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;color:#6b6e77;\'>ไม่สามารถโหลดรูปภาพได้</div>'">
            </a>
            <?php else: ?>
            <div style="height:72px; background:rgba(255,255,255,0.03);
                        display:flex; align-items:center; justify-content:center;
                        font-size:0.75rem; color:#6b6e77;">
                ไม่มีไฟล์แนบ
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div style="padding:0.9rem 1rem 0.75rem; flex:1;">
                <p style="font-size:0.60rem; font-weight:700; letter-spacing:0.10em;
                          text-transform:uppercase; color:#4a4e57; margin:0 0 0.3rem;">ภารกิจ</p>
                <p style="font-size:0.88rem; font-weight:600; color:#eeebe1; margin:0 0 0.75rem; line-height:1.4;">
                    <?= e($sub['challenge_title']) ?>
                    <span style="font-size:0.73rem; font-weight:500; color:#dab937; margin-left:0.35rem;">
                        +<?= formatTokens((int)$sub['token_reward']) ?> Token
                    </span>
                </p>

                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <div style="width:32px; height:32px; border-radius:50%; flex-shrink:0;
                                background:linear-gradient(135deg,rgba(218,185,55,0.20),rgba(218,185,55,0.08));
                                border:1px solid rgba(218,185,55,0.25);
                                display:flex; align-items:center; justify-content:center;
                                font-size:0.85rem; font-weight:700; color:#dab937;">
                        <?= mb_substr($sub['full_name'], 0, 1) ?>
                    </div>
                    <div style="min-width:0;">
                        <p style="font-size:0.85rem; font-weight:600; color:#eeebe1; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($sub['full_name']) ?>
                        </p>
                        <p style="font-size:0.70rem; color:#4a4e57; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($sub['position'] ?? '-') ?> · <?= e($sub['employee_code']) ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($sub['review_note'])): ?>
                <p style="margin:0.75rem 0 0; font-size:0.72rem; color:#6b6e77; font-style:italic;
                          padding-top:0.65rem; border-top:1px solid rgba(255,255,255,0.06);">
                    หมายเหตุ: <?= e($sub['review_note']) ?>
                </p>
                <?php endif; ?>

                <?php if ($isApproved && $sub['token_awarded'] > 0): ?>
                <p style="margin:0.6rem 0 0; font-size:0.75rem; font-weight:700; color:#7ec98a;">
                    ✓ มอบ +<?= formatTokens((int)$sub['token_awarded']) ?> Token แล้ว
                </p>
                <?php endif; ?>
            </div>

            <!-- Action area (pending only) -->
            <?php if ($isPending): ?>
            <div style="padding:0 1rem 1rem;">
            <?php if ($canManage): ?>
                <div id="note-area-<?= $sub['submission_id'] ?>" class="hidden" style="margin-bottom:0.6rem;">
                    <textarea id="note-input-<?= $sub['submission_id'] ?>"
                              rows="2"
                              placeholder="หมายเหตุถึงพนักงาน (ไม่บังคับ)…"
                              class="asb-note-input"></textarea>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <form method="POST" action="<?= BASE_URL ?>/hr/submissions.php"
                          style="flex:1;"
                          onsubmit="syncNote(<?= $sub['submission_id'] ?>, 'approve-note-<?= $sub['submission_id'] ?>')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <input type="hidden" name="review_note" id="approve-note-<?= $sub['submission_id'] ?>">
                        <button type="submit"
                                style="width:100%; padding:0.5rem 0; font-size:0.82rem; font-weight:700;
                                       border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                       background:rgba(81,142,92,0.15); color:#7ec98a;
                                       border:1px solid rgba(81,142,92,0.30); transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(81,142,92,0.28)'"
                                onmouseout="this.style.background='rgba(81,142,92,0.15)'">
                            ✓ อนุมัติ
                        </button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/hr/submissions.php"
                          style="flex:1;"
                          onsubmit="return confirmReject(<?= $sub['submission_id'] ?>)">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <input type="hidden" name="review_note" id="reject-note-<?= $sub['submission_id'] ?>">
                        <button type="submit"
                                style="width:100%; padding:0.5rem 0; font-size:0.82rem; font-weight:700;
                                       border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                       background:rgba(210,89,42,0.12); color:#d2592a;
                                       border:1px solid rgba(210,89,42,0.28); transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(210,89,42,0.25)'"
                                onmouseout="this.style.background='rgba(210,89,42,0.12)'">
                            ✕ ปฏิเสธ
                        </button>
                    </form>
                </div>
                <button onclick="toggleNote(<?= $sub['submission_id'] ?>)"
                        style="margin-top:0.5rem; width:100%; font-size:0.73rem; color:#4a4e57;
                               background:none; border:none; cursor:pointer; font-family:'Prompt',sans-serif;
                               padding:0.3rem; transition:color 0.15s;"
                        onmouseover="this.style.color='#eeebe1'"
                        onmouseout="this.style.color='#4a4e57'">
                    + เพิ่มหมายเหตุ
                </button>
            <?php else: ?>
                <p style="margin:0.5rem 0 0; font-size:0.72rem; color:#4a4e57; text-align:center; padding:0.4rem;">
                    รอ HR ดำเนินการ
                </p>
            <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /asb-submissions-wrap -->


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
