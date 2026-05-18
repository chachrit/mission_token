<?php
/**
 * admin/rewards/redemptions.php
 * Admin: view and process reward redemption requests
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];
$pdo     = getDB();

function rewardCategoryIconSvg(string $category): string
{
    $icons = [
        'voucher' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
        'leave'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'merch'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
        'perk'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
        'general' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>',
    ];

    return $icons[$category] ?? $icons['general'];
}

function formatThaiBuddhistDate(string $dateTime): string
{
    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return '-';
    }

    $thaiMonths = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];

    $day = (int)date('j', $timestamp);
    $month = $thaiMonths[(int)date('n', $timestamp)] ?? '';
    $year = (int)date('Y', $timestamp) + 543;

    return $day . ' ' . $month . ' ' . $year;
}

// ══════════════════════════════════════════════════════════════
// POST actions: fulfill or cancel a redemption
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // Only HR and admin can process redemptions
    $postRole = $_SESSION['role'] ?? '';
    if ($postRole !== 'admin' && $postRole !== 'hr') {
        setFlash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect(BASE_URL . '/hr/rewards/redemptions.php');
    }

    $action       = $_POST['action']        ?? '';
    $redemptionId = (int)($_POST['redemption_id'] ?? 0);
    $adminNote    = mb_substr(trim($_POST['admin_note'] ?? ''), 0, 500, 'UTF-8');

    if ($redemptionId > 0 && in_array($action, ['fulfill', 'cancel'], true)) {
        $newStatus = $action === 'fulfill' ? 'fulfilled' : 'cancelled';

        try {
            $pdo->beginTransaction();

            // Fetch the redemption
            $stmt = $pdo->prepare("
                SELECT rd.redemption_id, rd.employee_id, rd.tokens_spent, rd.status,
                       rw.title AS reward_title
                FROM   dbo.reward_redemptions rd
                JOIN   dbo.rewards rw ON rw.reward_id = rd.reward_id
                WHERE  rd.redemption_id = ?
            ");
            $stmt->execute([$redemptionId]);
            $rd = $stmt->fetch();

            if (!$rd) {
                $pdo->rollBack();
                setFlash('error', 'ไม่พบรายการนี้');
                redirect(BASE_URL . '/hr/rewards/redemptions.php');
            }

            if ($rd['status'] !== 'pending') {
                $pdo->rollBack();
                setFlash('error', 'รายการนี้ดำเนินการไปแล้ว');
                redirect(BASE_URL . '/hr/rewards/redemptions.php');
            }

            // If cancelled, refund tokens
            if ($action === 'cancel') {
                $refund = (int)$rd['tokens_spent'];
                $empId  = (int)$rd['employee_id'];

                $pdo->prepare("
                    INSERT INTO dbo.token_transactions (employee_id, amount, tx_type, note)
                    VALUES (?, ?, 'admin_adjust', ?)
                ")->execute([$empId, $refund, 'คืน Token: ยกเลิกแลกรางวัล ' . $rd['reward_title']]);

                $pdo->prepare("
                    UPDATE dbo.token_wallets
                    SET    balance     = balance + ?,
                           total_spent = total_spent - ?,
                           updated_at  = GETDATE()
                    WHERE  employee_id = ?
                ")->execute([$refund, $refund, $empId]);
            }

            // Update redemption status
            $pdo->prepare("
                UPDATE dbo.reward_redemptions
                SET    status       = ?,
                       processed_at = GETDATE(),
                       processed_by = ?,
                       admin_note   = ?
                WHERE  redemption_id = ?
            ")->execute([$newStatus, $adminId, $adminNote ?: null, $redemptionId]);

            $pdo->commit();

            $msg = $action === 'fulfill'
                ? 'มอบรางวัล "' . $rd['reward_title'] . '" แล้ว'
                : 'ยกเลิกและคืน Token ให้พนักงานแล้ว';
            setFlash('success', $msg);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[MissionToken] redemption action error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
        redirect(BASE_URL . '/hr/rewards/redemptions.php');
    }
}

// ══════════════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════════════
$filterStatus = $_GET['status'] ?? 'pending';
$allowed      = ['all', 'pending', 'fulfilled', 'cancelled'];
if (!in_array($filterStatus, $allowed, true)) $filterStatus = 'pending';

$redemptions = [];
$dataError   = null;
$counts      = ['all' => 0, 'pending' => 0, 'fulfilled' => 0, 'cancelled' => 0];

try {
    // Counts per status
    $rows = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM   dbo.reward_redemptions
        GROUP  BY status
    ")->fetchAll();
    foreach ($rows as $r) {
        $counts[$r['status']] = (int)$r['cnt'];
        $counts['all']       += (int)$r['cnt'];
    }

    // Redemptions list
    $where = ($filterStatus !== 'all') ? "WHERE rd.status = ?" : "WHERE 1=1";
    $params = ($filterStatus !== 'all') ? [$filterStatus] : [];

    $stmt = $pdo->prepare("
        SELECT TOP 100
               rd.redemption_id, rd.tokens_spent, rd.status,
               rd.redeemed_at,   rd.processed_at, rd.admin_note,
               rw.title       AS reward_title,
               rw.image_emoji,
               rw.category,
               e.full_name, e.department, e.employee_code
        FROM   dbo.reward_redemptions rd
        JOIN   dbo.rewards            rw ON rw.reward_id  = rd.reward_id
        JOIN   dbo.employees          e  ON e.employee_id = rd.employee_id
        $where
        ORDER BY rd.redeemed_at DESC
    ");
    $stmt->execute($params);
    $redemptions = $stmt->fetchAll();

} catch (Throwable $e) {
    error_log('[MissionToken] admin redemptions load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้';
}

$statusMeta = [
    'pending'   => ['label' => 'รอดำเนินการ', 'color' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fcd34d'],
    'fulfilled' => ['label' => 'มอบแล้ว',     'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#86efac'],
    'cancelled' => ['label' => 'ยกเลิก',       'color' => '#9f1239', 'bg' => '#fff1f2', 'border' => '#fca5a5'],
];

$catMeta = [
    'voucher' => ['label' => 'คูปอง'],
    'leave'   => ['label' => 'วันหยุดพิเศษ'],
    'merch'   => ['label' => 'ของที่ระลึก'],
    'perk'    => ['label' => 'สิทธิพิเศษ'],
    'general' => ['label' => 'ทั่วไป'],
];

$catTone = [
    'voucher' => ['icon_bg' => 'rgba(47,78,157,0.30)',  'icon_border' => 'rgba(123,159,245,0.52)', 'icon_color' => '#9db4f7'],
    'leave'   => ['icon_bg' => 'rgba(81,142,92,0.30)',  'icon_border' => 'rgba(126,201,138,0.52)', 'icon_color' => '#8fdaa0'],
    'merch'   => ['icon_bg' => 'rgba(98,48,122,0.32)',  'icon_border' => 'rgba(196,157,224,0.54)', 'icon_color' => '#d3ace8'],
    'perk'    => ['icon_bg' => 'rgba(201,168,48,0.30)', 'icon_border' => 'rgba(248,231,105,0.52)', 'icon_color' => '#f8e769'],
    'general' => ['icon_bg' => 'rgba(107,110,119,0.32)','icon_border' => 'rgba(165,169,181,0.52)', 'icon_color' => '#c9ccd4'],
];

$pageTitle  = 'คำขอแลกรางวัล';
$activePage = 'admin_redemptions';
$canManage  = in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true);
$flash      = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="ar-redemptions-wrap ard-wrap ard-wrap-shell">

    <!-- Aurora blobs -->
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="jp-aurora-blob jp-aurora-blob--gold"></div>
        <div class="jp-aurora-blob jp-aurora-blob--teal"></div>
    </div>

    <div class="jp-page-inner">

        <!-- Page header -->
        <div class="jp-page-header jp-page-header-row">
            <div>
                <div class="ard-back-wrap">
                    <a href="<?php echo BASE_URL; ?>/hr/rewards/index.php"
                       class="ard-back-link">
                        ← จัดการรางวัล
                    </a>
                </div>
                <p class="jp-kicker">
                    ADMIN — REDEMPTION REQUESTS
                </p>
                <h1 class="jp-title">
                    คำขอแลกรางวัล
                </h1>
                <p class="jp-subtitle">
                    ดูและดำเนินการคำขอแลกรางวัลของพนักงาน
                </p>
            </div>
            <!-- Summary chips -->
            <div class="jp-chip-row">
                <?php
                $dsDark = [
                    'pending'   => ['color' => '#fbbf24', 'bg' => 'rgba(245,158,11,0.10)', 'border' => 'rgba(245,158,11,0.25)'],
                    'fulfilled' => ['color' => '#7ec98a', 'bg' => 'rgba(81,142,92,0.12)',  'border' => 'rgba(81,142,92,0.28)'],
                    'cancelled' => ['color' => '#d2592a', 'bg' => 'rgba(210,89,42,0.10)',  'border' => 'rgba(210,89,42,0.25)'],
                ];
                foreach (['pending','fulfilled','cancelled'] as $s):
                    $ds = $dsDark[$s];
                    $statusTheme = 'ard-status-theme--' . preg_replace('/[^a-z0-9_-]/i', '', (string)$s);
                ?>
                <span class="ard-stat-chip <?= e($statusTheme) ?>">
                    <?= $statusMeta[$s]['label'] ?>: <?= $counts[$s] ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($dataError): ?>
        <div class="jp-alert-error">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- Status filter tabs -->
        <div class="jp-filter-row jp-filter-row--md">
            <?php
            $tabDefs = [
                'pending'   => 'รอดำเนินการ',
                'fulfilled' => 'มอบแล้ว',
                'cancelled' => 'ยกเลิก',
                'all'       => 'ทั้งหมด',
            ];
            foreach ($tabDefs as $k => $label):
            ?>
            <a href="?status=<?= e($k) ?>"
               class="ard-filter-tab <?php echo $filterStatus === $k ? 'active' : ''; ?>">
                <?= e($label) ?>
                <span class="ard-filter-count">(<?= $counts[$k] ?? 0 ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Redemptions table -->
        <div class="ard-table-wrap jp-glass-card jp-glass-card--md">

            <div class="jp-table-header ard-table-header ard-table-header-grid">
                <span>พนักงาน</span>
                <span>รางวัล</span>
                <span>Token</span>
                <span>วันที่ขอ</span>
                <span>สถานะ / จัดการ</span>
            </div>

            <?php if (empty($redemptions)): ?>
            <div class="jp-empty-state">
                <p class="jp-empty-note">ไม่มีรายการในสถานะนี้</p>
            </div>
            <?php else: ?>
            <?php foreach ($redemptions as $rd):
                $sm = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
                $ds = $dsDark[$rd['status']] ?? $dsDark['pending'];
                $rdCat = (string)($rd['category'] ?? 'general');
                $tone = $catTone[$rdCat] ?? $catTone['general'];
                $statusTheme = 'ard-status-theme--' . preg_replace('/[^a-z0-9_-]/i', '', (string)($rd['status'] ?? 'pending'));
                $catTheme = 'ard-cat-theme--' . preg_replace('/[^a-z0-9_-]/i', '', $rdCat);
            ?>
            <div class="ard-row ard-row-grid">

                <!-- Employee -->
                <div>
                    <p class="ard-emp-name">
                        <?= e($rd['full_name']) ?>
                    </p>
                    <p class="ard-emp-meta">
                        <?= e($rd['employee_code']) ?>
                        <?php if (!empty($rd['department'])): ?>
                        · <?= e($rd['department']) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Reward -->
                <div class="ard-reward-cell">
                    <span class="ard-reward-icon <?= e($catTheme) ?>">
                        <?= rewardCategoryIconSvg($rdCat) ?>
                    </span>
                    <div class="ard-reward-text-wrap">
                        <p class="ard-reward-title">
                            <?= e($rd['reward_title']) ?>
                        </p>
                        <?php if (!empty($rd['admin_note'])): ?>
                        <p class="ard-reward-note">
                            <?= e($rd['admin_note']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tokens spent -->
                <div class="ard-token-cell">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="12" height="12" class="ard-token-icon" alt="">
                    <span class="ard-token-value">
                        <?= (int)$rd['tokens_spent'] ?>
                    </span>
                </div>

                <!-- Date -->
                <div>
                    <span class="ard-date-main">
                        <?= e(formatThaiBuddhistDate((string)$rd['redeemed_at'])) ?>
                    </span>
                    <br>
                    <span class="ard-date-sub">
                        <?= date('H:i', strtotime($rd['redeemed_at'])) ?>
                    </span>
                </div>

                <!-- Status + actions -->
                <div class="ard-status-col">
                    <span class="ard-status-badge <?= e($statusTheme) ?>">
                        <?= $sm['label'] ?>
                    </span>

                    <?php if ($rd['status'] === 'pending'): ?>
                    <?php if ($canManage): ?>
                    <div class="ard-action-row">
                        <button data-onclick='ardOpenAction(<?= (int)$rd['redemption_id'] ?>, "fulfill",
                                                       <?= json_encode($rd['full_name']) ?>,
                                                       <?= json_encode($rd['reward_title']) ?>)'
                                class="ard-action-btn ard-action-btn--fulfill">
                            มอบรางวัลแล้ว
                        </button>
                        <button data-onclick='ardOpenAction(<?= (int)$rd['redemption_id'] ?>, "cancel",
                                                       <?= json_encode($rd['full_name']) ?>,
                                                       <?= json_encode($rd['reward_title']) ?>)'
                                class="ard-action-btn ard-action-btn--cancel">
                            ยกเลิก
                        </button>
                    </div>
                    <?php else: ?>
                    <span class="ard-pending-note">รอ HR ดำเนินการ</span>
                    <?php endif; ?>
                    <?php elseif ($rd['processed_at']): ?>
                    <span class="ard-processed-at">
                        <?= date('d/m/y H:i', strtotime($rd['processed_at'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div><!-- /ard-table-wrap -->

    </div><!-- /inner -->
</div><!-- /ar-redemptions-wrap -->

<?php if ($canManage): ?>
<!-- ACTION MODAL (HR + admin only) -->
<div id="ard-action-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="ard-modal-box" tabindex="-1">
        <div class="ard-modal-head">
            <h2 id="ard-modal-title" class="ard-modal-title"></h2>
            <button data-onclick="ardCloseAction()"
                    class="ard-modal-close">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="ard-action-form" method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" id="ard-form-action"        name="action"        value="">
            <input type="hidden" id="ard-form-redemption-id" name="redemption_id" value="">

            <div class="ard-modal-body">
                <p id="ard-modal-desc" class="ard-modal-desc"></p>

                <div class="ard-note-wrap">
                    <label class="ard-note-label">
                        หมายเหตุ (ถึงพนักงาน) <span class="ard-note-optional">(ไม่บังคับ)</span>
                    </label>
                    <textarea name="admin_note" id="ard-form-note" rows="3" maxlength="500"
                              placeholder="เช่น จะส่งให้ในวันศุกร์นี้ / วันลาใช้ได้ภายใน 3 เดือน"
                              class="journal-input ard-note-textarea"></textarea>
                </div>

                <div class="ard-modal-actions">
                    <button type="button" data-onclick="ardCloseAction()"
                            class="ard-modal-btn ard-modal-btn--cancel">
                        ยกเลิก
                    </button>
                    <button type="submit" id="ard-modal-submit-btn"
                            class="ard-modal-btn ard-modal-btn--submit">
                        ยืนยัน
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

