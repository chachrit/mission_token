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
           e.full_name, e.employee_code, e.position, e.avatar_url,
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
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="jp-aurora-blob jp-aurora-blob--gold"></div>
        <div class="jp-aurora-blob jp-aurora-blob--teal"></div>
    </div>

    <div class="jp-page-inner">

        <!-- Page header -->
        <div class="jp-page-header jp-page-header-row">
            <div>
                <p class="jp-kicker">
                    ADMIN — SUBMISSIONS
                </p>
                <h1 class="jp-title">
                    อนุมัติงานที่ส่ง
                </h1>
                <p class="jp-subtitle">
                    ตรวจสอบหลักฐานรูปภาพจากพนักงานและให้คะแนน Token
                </p>
            </div>
            <!-- Stats chips -->
            <div class="jp-chip-row">
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(218,185,55,0.10); color:#dab937; border:1px solid rgba(218,185,55,0.28);">
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

        <!-- Filter tabs -->
        <div class="jp-filter-row jp-filter-row--lg">
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
        <div class="jp-empty-state jp-empty-state--dashed">
            <p class="jp-empty-note">
                <?= $filter === 'all' ? 'ยังไม่มีการส่งงานในระบบ' : 'ไม่มีงานรอตรวจสอบในขณะนี้' ?>
            </p>
        </div>

        <?php else: ?>
        <div class="asb-cards-grid">
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
                $barBg    = 'rgba(218,185,55,0.12)';
                $barColor = '#dab937';
                $barBorder= 'rgba(218,185,55,0.28)';
            }

            $photoName = !empty($sub['photo_path']) ? basename($sub['photo_path']) : null;
            // Support both new JSON array format and legacy single filename
            $photoRaw = $sub['photo_path'] ?? '';
            $photoFiles = [];
            if ($photoRaw !== '' && $photoRaw !== null) {
                $decoded = json_decode($photoRaw, true);
                if (is_array($decoded)) {
                    $photoFiles = array_map('basename', $decoded);
                } else {
                    $photoFiles = [basename($photoRaw)]; // legacy single file
                }
            }
            $photoUrl  = $photoName
                ? uploadImgUrl('submissions', $photoName)
                : null;

            $_thMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                          'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            $_ts = strtotime((string)$sub['submitted_at']);
            $submittedDate = (int)date('j', $_ts) . ' ' . $_thMonths[(int)date('n', $_ts)]
                           . ' ' . ((int)date('Y', $_ts) + 543)
                           . ' ' . date('H:i', $_ts);
        ?>
        <div class="asb-card <?= $cardClass ?>">

            <!-- Status bar -->
            <div style="padding:0.55rem 1rem; display:flex; align-items:center; justify-content:space-between;
                        background:<?= $barBg ?>; border-bottom:1px solid <?= $barBorder ?>;
                        font-size:0.72rem; font-weight:700; color:<?= $barColor ?>;">
                <span>
                    <?php if ($isPending): ?>รอตรวจสอบ
                    <?php elseif ($isApproved): ?>อนุมัติแล้ว
                    <?php else: ?>ปฏิเสธ<?php endif; ?>
                </span>
                <span style="font-weight:400; opacity:0.70; font-size:0.68rem;"><?= $submittedDate ?></span>
            </div>

            <!-- Photo preview (click = lightbox) -->
            <?php if (!empty($photoFiles)):
                $jsonUrls   = htmlspecialchars(json_encode(array_map(
                    fn($f) => uploadImgUrl('submissions', $f), $photoFiles
                ), JSON_UNESCAPED_SLASHES), ENT_QUOTES);
                $photoCount = count($photoFiles);
            ?>
            <div class="asb-thumb-strip"
                 style="grid-template-columns:<?= $photoCount === 1 ? '1fr' : ($photoCount === 2 ? '1fr 1fr' : 'repeat(3,1fr)') ?>;"
                 onclick="openLightbox('<?= $jsonUrls ?>', 0)">
                <?php foreach ($photoFiles as $idx => $pf):
                    $pfUrl = uploadImgUrl('submissions', $pf);
                ?>
                <div class="asb-thumb-cell">
                    <img src="<?= $pfUrl ?>" alt="หลักฐาน" loading="lazy"
                         onerror="this.style.display='none'">
                    <?php if ($idx === 2 && $photoCount > 3): ?>
                    <div class="asb-thumb-more">+<?= $photoCount - 3 ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($idx >= 2) break; ?>
                <?php endforeach; ?>
                <div class="asb-thumb-overlay">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    <span><?= $photoCount ?> รูป</span>
                </div>
            </div>
            <?php else: ?>
            <div class="asb-thumb-empty">
                ไม่มีไฟล์แนบ
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div class="asb-info">
                <p class="asb-info-label">ภารกิจ</p>
                <p class="asb-info-title">
                    <?= e($sub['challenge_title']) ?>
                    <span class="asb-info-reward">
                        +<?= formatTokens((int)$sub['token_reward']) ?> Token
                    </span>
                </p>

                <div class="asb-person-row">
                    <?php if (!empty($sub['avatar_url'])): ?>
                    <img src="<?= uploadImgUrl('avatars', (string)$sub['avatar_url']) ?>"
                         alt="" loading="lazy"
                         class="asb-avatar-img"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="asb-avatar-fallback is-hidden">
                        <?= mb_substr($sub['full_name'], 0, 1) ?>
                    </div>
                    <?php else: ?>
                    <div class="asb-avatar-fallback is-visible">
                        <?= mb_substr($sub['full_name'], 0, 1) ?>
                    </div>
                    <?php endif; ?>
                    <div class="asb-person-meta">
                        <p class="asb-person-name">
                            <?= e($sub['full_name']) ?>
                        </p>
                        <p class="asb-person-sub">
                            <?= e($sub['position'] ?? '-') ?> · <?= e($sub['employee_code']) ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($sub['review_note'])): ?>
                <p class="asb-review-note">
                    หมายเหตุ: <?= e($sub['review_note']) ?>
                </p>
                <?php endif; ?>

                <?php if ($isApproved && $sub['token_awarded'] > 0): ?>
                <p class="asb-approved-note">
                    อนุมัติแล้ว +<?= formatTokens((int)$sub['token_awarded']) ?> Token
                </p>
                <?php endif; ?>
            </div>

            <!-- Action area (pending only) -->
            <?php if ($isPending): ?>
            <div class="asb-action-wrap">
            <?php if ($canManage): ?>
                <div id="note-area-<?= $sub['submission_id'] ?>" class="hidden asb-note-wrap">
                    <textarea id="note-input-<?= $sub['submission_id'] ?>"
                              rows="2"
                              placeholder="หมายเหตุถึงพนักงาน (ไม่บังคับ)…"
                              class="asb-note-input"></textarea>
                </div>
                <div class="asb-action-row">
                    <!-- Approve -->
                    <button type="button"
                            onclick="openConfirmModal('approve', <?= $sub['submission_id'] ?>, '<?= e(addslashes($sub['full_name'] ?? '')) ?>', '<?= e(addslashes($sub['challenge_title'] ?? '')) ?>', <?= (int)$sub['token_reward'] ?>, '<?= e($filter) ?>')"
                            class="asb-action-btn asb-action-btn--approve">
                        &#10003;&ensp;อนุมัติ
                    </button>
                    <!-- Reject -->
                    <button type="button"
                            onclick="openConfirmModal('reject', <?= $sub['submission_id'] ?>, '<?= e(addslashes($sub['full_name'] ?? '')) ?>', '<?= e(addslashes($sub['challenge_title'] ?? '')) ?>', <?= (int)$sub['token_reward'] ?>, '<?= e($filter) ?>')"
                            class="asb-action-btn asb-action-btn--reject">
                        &#10007;&ensp;ปฏิเสธ
                    </button>
                </div>
                <button onclick="toggleNote(<?= $sub['submission_id'] ?>)"
                        class="asb-note-toggle">
                    + เพิ่มหมายเหตุ
                </button>
            <?php else: ?>
                <p class="asb-wait-note">
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


<!-- ── CONFIRM MODAL ───────────────────────────────────────── -->
<div id="asb-confirm-modal" class="jp-modal asb-confirm-modal" onclick="if(event.target===this)closeConfirmModal()" role="dialog" aria-modal="true">
    <div class="jp-modal-content asb-modal-box">
        <!-- Header -->
        <div id="cm-header" class="jp-modal-header asb-confirm-header">
            <div id="cm-icon" class="asb-confirm-icon"></div>
            <span id="cm-title" class="jp-modal-header-title asb-confirm-title"></span>
            <button onclick="closeConfirmModal()" class="jp-modal-close asb-confirm-close" aria-label="ปิด">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Body -->
        <div class="jp-modal-body asb-confirm-body">
            <p id="cm-body" class="asb-confirm-message"></p>
            <div class="jp-modal-footer asb-confirm-actions">
                <button onclick="closeConfirmModal()" class="asb-confirm-cancel-btn">
                    ยกเลิก
                </button>
                <button id="cm-confirm-btn" class="asb-confirm-submit-btn" onclick="submitConfirmModal()">
                </button>
            </div>
        </div>
    </div>
</div>

<!-- hidden form — submitted by modal JS -->
<form id="asb-confirm-form" method="POST" action="<?= BASE_URL ?>/hr/submissions.php" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="cf-action">
    <input type="hidden" name="submission_id" id="cf-sid">
    <input type="hidden" name="filter" id="cf-filter">
    <input type="hidden" name="review_note" id="cf-note">
</form>

<!-- ── LIGHTBOX ─────────────────────────────────────────────── -->
<div id="asb-lightbox" class="asb-lightbox" onclick="lbBgClick(event)">

    <!-- Close -->
    <button onclick="closeLightbox()" class="asb-lightbox-close"
            title="ปิด (Esc)">&#x2715;</button>

    <!-- Counter -->
    <div id="lb-counter" class="asb-lightbox-counter"></div>

    <!-- Prev -->
    <button id="lb-prev" onclick="lbNav(-1)" class="asb-lightbox-nav asb-lightbox-nav--prev">&#8249;</button>

    <!-- Image wrapper (fade) -->
    <div class="asb-lightbox-media">
        <img id="lb-img" class="asb-lightbox-image" src="" alt="preview">
    </div>

    <!-- Next -->
    <button id="lb-next" onclick="lbNav(1)" class="asb-lightbox-nav asb-lightbox-nav--next">&#8250;</button>

    <!-- Dot strip -->
    <div id="lb-dots" class="asb-lightbox-dots"></div>

    <!-- Download -->
    <a id="lb-download" class="asb-lightbox-download" href="" download>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        ดาวน์โหลด
    </a>
</div>

<script>
(function () {
    var _lbUrls = [];
    var _lbIdx  = 0;

    window.openLightbox = function (urlsJson, startIdx) {
        _lbUrls = typeof urlsJson === 'string' ? JSON.parse(urlsJson) : urlsJson;
        _lbIdx  = startIdx || 0;
        document.getElementById('asb-lightbox').classList.add('show');
        document.body.style.overflow = 'hidden';
        _lbRender();
    };

    window.closeLightbox = function () {
        document.getElementById('asb-lightbox').classList.remove('show');
        document.body.style.overflow = '';
    };

    window.lbNav = function (dir) {
        _lbIdx = (_lbIdx + dir + _lbUrls.length) % _lbUrls.length;
        _lbRender();
    };

    window.lbBgClick = function (e) {
        if (e.target === document.getElementById('asb-lightbox')) closeLightbox();
    };

    function _lbRender() {
        var img     = document.getElementById('lb-img');
        var prev    = document.getElementById('lb-prev');
        var next    = document.getElementById('lb-next');
        var counter = document.getElementById('lb-counter');
        var dotsEl  = document.getElementById('lb-dots');
        var dlLink  = document.getElementById('lb-download');

        img.style.opacity = '0';
        img.src = _lbUrls[_lbIdx];
        img.onload = function () { img.style.opacity = '1'; };

        var multi = _lbUrls.length > 1;
        prev.style.display = multi ? 'flex' : 'none';
        next.style.display = multi ? 'flex' : 'none';

        counter.textContent = multi ? (_lbIdx + 1) + ' / ' + _lbUrls.length : '';

        dotsEl.innerHTML = '';
        if (multi) {
            _lbUrls.forEach(function (_, i) {
                var dot = document.createElement('div');
                dot.className = 'asb-lightbox-dot' + (i === _lbIdx ? ' is-active' : '');
                dot.addEventListener('click', function (e) { e.stopPropagation(); _lbIdx = i; _lbRender(); });
                dotsEl.appendChild(dot);
            });
        }

        dlLink.href = _lbUrls[_lbIdx];
    }

    // Keyboard
    document.addEventListener('keydown', function (e) {
        if (!document.getElementById('asb-lightbox').classList.contains('show')) return;
        if (e.key === 'Escape')      closeLightbox();
        if (e.key === 'ArrowLeft')   lbNav(-1);
        if (e.key === 'ArrowRight')  lbNav(1);
    });

    // Touch swipe
    var _tsX = null;
    var lb = document.getElementById('asb-lightbox');
    lb.addEventListener('touchstart', function (e) { _tsX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend',   function (e) {
        if (_tsX === null) return;
        var dx = e.changedTouches[0].clientX - _tsX;
        if (Math.abs(dx) > 45) lbNav(dx < 0 ? 1 : -1);
        _tsX = null;
    }, { passive: true });
})();

// ── Confirm Modal ─────────────────────────────────────────────
(function () {
    var _cmAction = '', _cmSid = 0, _cmFilter = '';

    window.openConfirmModal = function (action, sid, empName, challengeTitle, tokenReward, filter) {
        _cmAction = action;
        _cmSid    = sid;
        _cmFilter = filter;

        var isApprove = action === 'approve';
        var accentClr = isApprove ? '#7ec98a' : '#d2592a';
        var accentBg  = isApprove ? 'rgba(81,142,92,0.18)' : 'rgba(210,89,42,0.15)';
        var accentBdr = isApprove ? 'rgba(81,142,92,0.35)' : 'rgba(210,89,42,0.30)';
        var hoverBg   = isApprove ? 'rgba(81,142,92,0.30)' : 'rgba(210,89,42,0.28)';

        // Header accent
        var hdrGrad = isApprove
            ? 'linear-gradient(135deg,rgba(81,142,92,0.14),rgba(81,142,92,0.04))'
            : 'linear-gradient(135deg,rgba(210,89,42,0.14),rgba(210,89,42,0.04))';
        var hdrBdr  = isApprove ? 'rgba(81,142,92,0.20)' : 'rgba(210,89,42,0.20)';
        var hdr = document.getElementById('cm-header');
        hdr.style.background   = hdrGrad;
        hdr.style.borderBottomColor = hdrBdr;

        // Icon
        document.getElementById('cm-icon').style.background = isApprove ? 'rgba(81,142,92,0.18)' : 'rgba(210,89,42,0.12)';
        document.getElementById('cm-icon').style.border     = '1px solid ' + accentBdr;
        document.getElementById('cm-icon').innerHTML = isApprove
            ? '<svg width="14" height="14" fill="none" stroke="#7ec98a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg width="14" height="14" fill="none" stroke="#d2592a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

        document.getElementById('cm-title').textContent = isApprove ? 'ยืนยันการอนุมัติ' : 'ยืนยันการปฏิเสธ';
        document.getElementById('cm-body').innerHTML =
            (isApprove
                ? 'อนุมัติงานของ <strong style="color:#eeebe1;">' + (empName || '–') + '</strong> และมอบรางวัล <strong style="color:#f8e769;">' + tokenReward.toLocaleString() + ' Token</strong> ใช่หรือไม่?'
                : 'ปฏิเสธงานของ <strong style="color:#eeebe1;">' + (empName || '–') + '</strong> ใช่หรือไม่?');

        // Box border accent
        document.querySelector('.asb-modal-box').style.border = '1px solid ' + accentBdr;

        var btn = document.getElementById('cm-confirm-btn');
        btn.textContent = isApprove ? '✓  อนุมัติ' : '✕  ปฏิเสธ';
        btn.style.background = accentBg;
        btn.style.color      = accentClr;
        btn.style.border     = '1px solid ' + accentBdr;
        btn.onmouseover = function () { this.style.background = hoverBg; };
        btn.onmouseout  = function () { this.style.background = accentBg; };

        var modal = document.getElementById('asb-confirm-modal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    window.closeConfirmModal = function () {
        document.getElementById('asb-confirm-modal').classList.remove('show');
        document.body.style.overflow = '';
    };

    window.submitConfirmModal = function () {
        // sync note from per-card textarea (if user filled it before clicking)
        var noteEl = document.getElementById('note-input-' + _cmSid);
        document.getElementById('cf-action').value = _cmAction;
        document.getElementById('cf-sid').value    = _cmSid;
        document.getElementById('cf-filter').value = _cmFilter;
        document.getElementById('cf-note').value   = noteEl ? noteEl.value : '';
        document.getElementById('asb-confirm-form').submit();
    };

    document.addEventListener('keydown', function (e) {
        if (!document.getElementById('asb-confirm-modal').classList.contains('show')) return;
        if (e.key === 'Escape') closeConfirmModal();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
