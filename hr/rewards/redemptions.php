<?php
/**
 * admin/rewards/redemptions.php
 * Admin: view and process reward redemption requests
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];
$pdo     = getDB();

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

$pageTitle  = 'คำขอแลกรางวัล';
$activePage = 'admin_rewards';
$canManage  = in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true);
$flash      = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="ar-redemptions-wrap ard-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

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
                <div style="margin-bottom:0.5rem;">
                    <a href="<?php echo BASE_URL; ?>/hr/rewards/index.php"
                       style="font-size:0.72rem; font-weight:600; color:#4a4e57; text-decoration:none;
                              letter-spacing:0.06em; text-transform:uppercase; transition:color 0.15s;"
                       onmouseover="this.style.color='#dab937'" onmouseout="this.style.color='#4a4e57'">
                        ← จัดการรางวัล
                    </a>
                </div>
                <p style="font-size:0.55rem; font-weight:700; letter-spacing:0.40em;
                          text-transform:uppercase; color:rgba(218,185,55,0.60); margin:0 0 0.5rem;">
                    ⬡ &nbsp;ADMIN — REDEMPTION REQUESTS
                </p>
                <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0 0 0.25rem; letter-spacing:-0.02em;">
                    คำขอแลกรางวัล
                </h1>
                <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                    ดูและดำเนินการคำขอแลกรางวัลของพนักงาน
                </p>
            </div>
            <!-- Summary chips -->
            <div style="display:flex; gap:0.55rem; align-items:center; flex-wrap:wrap;">
                <?php
                $dsDark = [
                    'pending'   => ['color' => '#fbbf24', 'bg' => 'rgba(245,158,11,0.10)', 'border' => 'rgba(245,158,11,0.25)'],
                    'fulfilled' => ['color' => '#7ec98a', 'bg' => 'rgba(81,142,92,0.12)',  'border' => 'rgba(81,142,92,0.28)'],
                    'cancelled' => ['color' => '#d2592a', 'bg' => 'rgba(210,89,42,0.10)',  'border' => 'rgba(210,89,42,0.25)'],
                ];
                foreach (['pending','fulfilled','cancelled'] as $s):
                    $ds = $dsDark[$s];
                ?>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem;
                             border-radius:999px; background:<?= $ds['bg'] ?>; color:<?= $ds['color'] ?>;
                             border:1px solid <?= $ds['border'] ?>;">
                    <?= $statusMeta[$s]['label'] ?>: <?= $counts[$s] ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($dataError): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.85rem 1.1rem; font-size:0.85rem;
                    background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28); color:#d2592a;">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- Status filter tabs -->
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
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
                <span style="font-size:0.68rem; font-weight:700; opacity:0.65;">(<?= $counts[$k] ?? 0 ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Redemptions table -->
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">

            <div style="display:grid; grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;
                        gap:1rem; padding:0.7rem 1.25rem;
                        background:rgba(255,255,255,0.03);
                        border-bottom:1px solid rgba(255,255,255,0.07);
                        font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                        text-transform:uppercase; color:#6b6e77;">
                <span>พนักงาน</span>
                <span>รางวัล</span>
                <span>Token</span>
                <span>วันที่ขอ</span>
                <span>สถานะ / จัดการ</span>
            </div>

            <?php if (empty($redemptions)): ?>
            <div style="padding:3.5rem; text-align:center;">
                <p style="font-size:2rem; margin-bottom:0.5rem; opacity:0.20;">🎉</p>
                <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ไม่มีรายการในสถานะนี้</p>
            </div>
            <?php else: ?>
            <?php foreach ($redemptions as $rd):
                $sm = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
                $ds = $dsDark[$rd['status']] ?? $dsDark['pending'];
            ?>
            <div class="ard-row"
                 style="display:grid; grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;
                        gap:1rem; padding:0.9rem 1.25rem; align-items:center;">

                <!-- Employee -->
                <div>
                    <p style="font-size:0.87rem; font-weight:600; color:#eeebe1; margin:0;">
                        <?= e($rd['full_name']) ?>
                    </p>
                    <p style="font-size:0.72rem; color:#6b6e77; margin:0.08rem 0 0;">
                        <?= e($rd['employee_code']) ?>
                        <?php if (!empty($rd['department'])): ?>
                        · <?= e($rd['department']) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Reward -->
                <div style="display:flex; align-items:center; gap:0.5rem; min-width:0;">
                    <span style="font-size:1.2rem; flex-shrink:0; line-height:1;"><?= e($rd['image_emoji'] ?: '🎁') ?></span>
                    <div style="min-width:0;">
                        <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($rd['reward_title']) ?>
                        </p>
                        <?php if (!empty($rd['admin_note'])): ?>
                        <p style="font-size:0.68rem; color:#6b6e77; margin:0;">
                            <?= e($rd['admin_note']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tokens spent -->
                <div style="display:flex; align-items:center; gap:0.28rem;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="12" height="12" style="object-fit:contain; opacity:0.65;" alt="">
                    <span style="font-size:0.9rem; font-weight:700; color:#dab937;">
                        <?= (int)$rd['tokens_spent'] ?>
                    </span>
                </div>

                <!-- Date -->
                <div>
                    <span style="font-size:0.82rem; color:#eeebe1;">
                        <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                    </span>
                    <br>
                    <span style="font-size:0.70rem; color:#6b6e77;">
                        <?= date('H:i', strtotime($rd['redeemed_at'])) ?>
                    </span>
                </div>

                <!-- Status + actions -->
                <div style="display:flex; flex-direction:column; gap:0.4rem; align-items:flex-start;">
                    <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                                 border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                                 background:<?= $ds['bg'] ?>; color:<?= $ds['color'] ?>;
                                 border:1px solid <?= $ds['border'] ?>;">
                        <?= $sm['label'] ?>
                    </span>

                    <?php if ($rd['status'] === 'pending'): ?>
                    <?php if ($canManage): ?>
                    <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                        <button onclick='ardOpenAction(<?= (int)$rd['redemption_id'] ?>, "fulfill",
                                                       <?= json_encode($rd['full_name']) ?>,
                                                       <?= json_encode($rd['reward_title']) ?>)'
                                style="font-size:0.70rem; padding:0.22rem 0.6rem; border-radius:6px;
                                       background:rgba(81,142,92,0.15); color:#7ec98a;
                                       border:1px solid rgba(81,142,92,0.30);
                                       cursor:pointer; font-family:'Prompt',sans-serif; font-weight:600;
                                       transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(81,142,92,0.25)'"
                                onmouseout="this.style.background='rgba(81,142,92,0.15)'">
                            ✓ มอบรางวัลแล้ว
                        </button>
                        <button onclick='ardOpenAction(<?= (int)$rd['redemption_id'] ?>, "cancel",
                                                       <?= json_encode($rd['full_name']) ?>,
                                                       <?= json_encode($rd['reward_title']) ?>)'
                                style="font-size:0.70rem; padding:0.22rem 0.6rem; border-radius:6px;
                                       background:rgba(210,89,42,0.12); color:#d2592a;
                                       border:1px solid rgba(210,89,42,0.28);
                                       cursor:pointer; font-family:'Prompt',sans-serif; font-weight:600;
                                       transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(210,89,42,0.22)'"
                                onmouseout="this.style.background='rgba(210,89,42,0.12)'">
                            ✕ ยกเลิก
                        </button>
                    </div>
                    <?php else: ?>
                    <span style="font-size:0.68rem; color:#6b6e77; font-style:italic;">รอ HR ดำเนินการ</span>
                    <?php endif; ?>
                    <?php elseif ($rd['processed_at']): ?>
                    <span style="font-size:0.68rem; color:#6b6e77;">
                        <?= date('d/m/y H:i', strtotime($rd['processed_at'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /inner -->
</div><!-- /ar-redemptions-wrap -->

<?php if ($canManage): ?>
<!-- ACTION MODAL (HR + admin only) -->
<div id="ard-action-modal">
    <div class="ard-modal-box">
        <div style="background:linear-gradient(135deg,rgba(218,185,55,0.10),rgba(218,185,55,0.02));
                    border-bottom:1px solid rgba(218,185,55,0.14);
                    padding:1.15rem 1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <h2 id="ard-modal-title" style="font-size:0.97rem; font-weight:700; color:#eeebe1; margin:0;"></h2>
            <button onclick="ardCloseAction()"
                    style="margin-left:auto; background:none; border:none; cursor:pointer;
                           color:#4a4e57; padding:4px; border-radius:6px; line-height:0;
                           transition:color 0.15s;"
                    onmouseover="this.style.color='#eeebe1'" onmouseout="this.style.color='#4a4e57'">
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

            <div style="padding:1.4rem 1.65rem;">
                <p id="ard-modal-desc"
                   style="font-size:0.88rem; color:#c8c4b8; margin:0 0 1.25rem; line-height:1.65;"></p>

                <div style="margin-bottom:1.25rem;">
                    <label style="font-size:0.68rem; font-weight:700; color:#4a4e57;
                                  letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.35rem; display:block;">
                        หมายเหตุ (ถึงพนักงาน) <span style="font-weight:400; color:#6b6e77; text-transform:none;">(ไม่บังคับ)</span>
                    </label>
                    <textarea name="admin_note" id="ard-form-note" rows="3" maxlength="500"
                              placeholder="เช่น จะส่งให้ในวันศุกร์นี้ / วันลาใช้ได้ภายใน 3 เดือน"
                              class="journal-input" style="resize:vertical;"></textarea>
                </div>

                <div style="display:flex; gap:0.65rem;">
                    <button type="button" onclick="ardCloseAction()"
                            style="flex:1; padding:0.58rem 1rem; font-size:0.85rem; font-weight:600;
                                   border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                   background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                                   color:#eeebe1; transition:background 0.15s; text-align:center;"
                            onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                        ยกเลิก
                    </button>
                    <button type="submit" id="ard-modal-submit-btn"
                            style="flex:1.5; padding:0.58rem 1rem; font-size:0.85rem; font-weight:700;
                                   border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                   border:none; color:#fff; transition:opacity 0.15s; text-align:center;">
                        ยืนยัน
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
