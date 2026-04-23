<?php
/**
 * admin/rewards/redemptions.php
 * Admin: view and process reward redemption requests
 */

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];
$pdo     = getDB();

// ══════════════════════════════════════════════════════════════
// POST actions: fulfill or cancel a redemption
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
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
                redirect(BASE_URL . '/admin/rewards/redemptions.php');
            }

            if ($rd['status'] !== 'pending') {
                $pdo->rollBack();
                setFlash('error', 'รายการนี้ดำเนินการไปแล้ว');
                redirect(BASE_URL . '/admin/rewards/redemptions.php');
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
                ? 'จัดส่งรางวัล "' . $rd['reward_title'] . '" แล้ว'
                : 'ยกเลิกและคืน Token ให้พนักงานแล้ว';
            setFlash('success', $msg);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[MissionToken] redemption action error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
        redirect(BASE_URL . '/admin/rewards/redemptions.php');
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
    'fulfilled' => ['label' => 'จัดส่งแล้ว',  'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#86efac'],
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
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .rdm-row { border-bottom: 1px solid #ece9e0; }
    .rdm-row:last-child { border-bottom: none; }
    .rdm-row:hover { background: #faf8f2; }

    /* Action form modal */
    #action-modal {
        display: none; position: fixed; inset: 0; z-index: 9000;
        background: rgba(9,17,19,0.55); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1.5rem;
    }
    #action-modal.open { display: flex; }
    .modal-box {
        background: #fdfcdf; border: 1px solid #e6e2d6; border-radius: 20px;
        width: 100%; max-width: 440px; overflow: hidden;
        box-shadow: 0 24px 80px rgba(9,17,19,0.22);
        animation: modal-in 0.25s cubic-bezier(.22,.97,.5,1.18);
    }
    @keyframes modal-in {
        from { opacity:0; transform: scale(0.9) translateY(20px); }
        to   { opacity:1; transform: scale(1) translateY(0); }
    }

    .filter-tab {
        padding: 0.45rem 1.1rem; border-radius: 999px;
        font-size: 0.8rem; font-weight: 500; font-family: 'Prompt', sans-serif;
        border: 1.5px solid #d4d0c8; background: transparent; color: #6b6e77;
        cursor: pointer; transition: all 0.18s; text-decoration: none;
        display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .filter-tab:hover  { border-color: #091113; color: #091113; }
    .filter-tab.active { background: #091113; border-color: #091113; color: #eeebe1; }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Page header -->
    <div style="display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
                <a href="<?php echo BASE_URL; ?>/admin/rewards/index.php"
                   style="font-size:0.82rem; color:#6b6e77; text-decoration:none;"
                   onmouseover="this.style.color='#091113'" onmouseout="this.style.color='#6b6e77'">
                    ← จัดการรางวัล
                </a>
            </div>
            <h1 style="font-size:1.6rem; font-weight:700; color:#091113; margin:0;">
                📋 คำขอแลกรางวัล
            </h1>
            <p style="font-size:0.85rem; color:#6b6e77; margin:0.25rem 0 0;">
                ดูและดำเนินการคำขอแลกรางวัลของพนักงาน
            </p>
        </div>
        <!-- Summary chips -->
        <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
            <?php foreach (['pending','fulfilled','cancelled'] as $s):
                $sm = $statusMeta[$s];
            ?>
            <span style="font-size:0.78rem; font-weight:600; padding:0.3rem 0.9rem;
                         border-radius:999px; background:<?= $sm['bg'] ?>; color:<?= $sm['color'] ?>;
                         border:1px solid <?= $sm['border'] ?>;">
                <?= $sm['label'] ?>: <?= $counts[$s] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($dataError): ?>
    <div class="mb-6 rounded-xl border px-5 py-4 text-sm"
         style="border-color:#edc3b2; background:#fff1ea; color:#d2592a;">
        <?= e($dataError) ?>
    </div>
    <?php endif; ?>

    <!-- ── Status filter tabs ────────────────────────────── -->
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
        <?php
        $tabDefs = [
            'pending'   => 'รอดำเนินการ',
            'fulfilled' => 'จัดส่งแล้ว',
            'cancelled' => 'ยกเลิก',
            'all'       => 'ทั้งหมด',
        ];
        foreach ($tabDefs as $k => $label):
        ?>
        <a href="?status=<?= e($k) ?>"
           class="filter-tab <?php echo $filterStatus === $k ? 'active' : ''; ?>">
            <?= e($label) ?>
            <span style="font-size:0.72rem; font-weight:700;">(<?= $counts[$k] ?? 0 ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Redemptions table ─────────────────────────────── -->
    <div style="background:#fdfcdf; border:1px solid #e6e2d6; border-radius:16px; overflow:hidden;">

        <div style="display:grid; grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;
                    gap:1rem; padding:0.75rem 1.25rem;
                    background:#f4f1e8; border-bottom:1px solid #e6e2d6;
                    font-size:0.72rem; font-weight:600; letter-spacing:0.06em;
                    text-transform:uppercase; color:#6b6e77;">
            <span>พนักงาน / รางวัล</span>
            <span>รางวัล</span>
            <span>Token</span>
            <span>วันที่ขอ</span>
            <span>สถานะ / จัดการ</span>
        </div>

        <?php if (empty($redemptions)): ?>
        <div style="padding:3rem; text-align:center; color:#6b6e77;">
            <p style="font-size:1.5rem; margin-bottom:0.5rem;">🎉</p>
            <p>ไม่มีรายการในสถานะนี้</p>
        </div>
        <?php else: ?>
        <?php foreach ($redemptions as $rd):
            $sm = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
        ?>
        <div class="rdm-row"
             style="display:grid; grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;
                    gap:1rem; padding:0.9rem 1.25rem; align-items:center;">

            <!-- Employee -->
            <div>
                <p style="font-size:0.88rem; font-weight:600; color:#091113; margin:0;">
                    <?= e($rd['full_name']) ?>
                </p>
                <p style="font-size:0.74rem; color:#6b6e77; margin:0.1rem 0 0;">
                    <?= e($rd['employee_code']) ?>
                    <?php if (!empty($rd['department'])): ?>
                    · <?= e($rd['department']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Reward -->
            <div style="display:flex; align-items:center; gap:0.5rem; min-width:0;">
                <span style="font-size:1.25rem; flex-shrink:0;"><?= e($rd['image_emoji'] ?: '🎁') ?></span>
                <div style="min-width:0;">
                    <p style="font-size:0.85rem; font-weight:500; color:#091113; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($rd['reward_title']) ?>
                    </p>
                    <?php if (!empty($rd['admin_note'])): ?>
                    <p style="font-size:0.7rem; color:#6b6e77; margin:0;">
                        หมายเหตุ: <?= e($rd['admin_note']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tokens spent -->
            <div style="display:flex; align-items:center; gap:0.3rem;">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     width="14" height="14" style="object-fit:contain;" alt="">
                <span style="font-size:0.9rem; font-weight:600; color:#091113;">
                    <?= (int)$rd['tokens_spent'] ?>
                </span>
            </div>

            <!-- Date -->
            <div>
                <span style="font-size:0.82rem; color:#091113;">
                    <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                </span>
                <br>
                <span style="font-size:0.72rem; color:#6b6e77;">
                    <?= date('H:i', strtotime($rd['redeemed_at'])) ?>
                </span>
            </div>

            <!-- Status + actions -->
            <div style="display:flex; flex-direction:column; gap:0.4rem; align-items:flex-start;">
                <span style="font-size:0.72rem; font-weight:600; padding:0.2rem 0.7rem;
                             border-radius:999px; background:<?= $sm['bg'] ?>; color:<?= $sm['color'] ?>;
                             border:1px solid <?= $sm['border'] ?>;">
                    <?= $sm['label'] ?>
                </span>

                <?php if ($rd['status'] === 'pending'): ?>
                <div style="display:flex; gap:0.35rem;">
                    <button onclick='openAction(<?= (int)$rd['redemption_id'] ?>, "fulfill",
                                                 <?= json_encode($rd['full_name']) ?>,
                                                 <?= json_encode($rd['reward_title']) ?>)'
                            style="font-size:0.72rem; padding:0.22rem 0.65rem; border-radius:6px;
                                   background:#e6f4e9; color:#166534; border:1px solid #86efac;
                                   cursor:pointer; font-family:'Prompt',sans-serif; font-weight:500;">
                        ✓ จัดส่งแล้ว
                    </button>
                    <button onclick='openAction(<?= (int)$rd['redemption_id'] ?>, "cancel",
                                                 <?= json_encode($rd['full_name']) ?>,
                                                 <?= json_encode($rd['reward_title']) ?>)'
                            style="font-size:0.72rem; padding:0.22rem 0.65rem; border-radius:6px;
                                   background:#fff1f2; color:#9f1239; border:1px solid #fca5a5;
                                   cursor:pointer; font-family:'Prompt',sans-serif; font-weight:500;">
                        ✕ ยกเลิก
                    </button>
                </div>
                <?php elseif ($rd['processed_at']): ?>
                <span style="font-size:0.7rem; color:#6b6e77;">
                    <?= date('d/m/y H:i', strtotime($rd['processed_at'])) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /max-w-7xl -->

<!-- ══ ACTION MODAL ════════════════════════════════════════ -->
<div id="action-modal" onclick="if(event.target===this) closeAction();">
    <div class="modal-box">
        <div style="background:linear-gradient(135deg,#091113,#1a2022);
                    padding:1.25rem 1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <h2 id="modal-title"
                style="font-size:1rem; font-weight:600; color:#eeebe1; margin:0;"></h2>
            <button onclick="closeAction()"
                    style="margin-left:auto; background:none; border:none; cursor:pointer;
                           color:#6b6e77; padding:4px; border-radius:6px; line-height:0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="action-form" method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" id="form-action"       name="action"        value="">
            <input type="hidden" id="form-redemption-id" name="redemption_id" value="">

            <div style="padding:1.5rem 1.75rem;">
                <p id="modal-desc"
                   style="font-size:0.9rem; color:#091113; margin:0 0 1.25rem; line-height:1.6;"></p>

                <div style="margin-bottom:1.25rem;">
                    <label style="font-size:0.78rem; font-weight:600; color:#3a3e43;
                                  letter-spacing:0.04em; margin-bottom:0.35rem; display:block;">
                        หมายเหตุ (ถึงพนักงาน) <span style="font-weight:400; color:#6b6e77;">(ไม่บังคับ)</span>
                    </label>
                    <textarea name="admin_note" id="form-note" rows="3" maxlength="500"
                              placeholder="เช่น จะส่งให้ในวันศุกร์นี้ / วันลาใช้ได้ภายใน 3 เดือน"
                              class="journal-input" style="resize:vertical;"></textarea>
                </div>

                <div style="display:flex; gap:0.75rem;">
                    <button type="button" onclick="closeAction()"
                            class="btn-outline" style="flex:1; justify-content:center;">
                        ยกเลิก
                    </button>
                    <button type="submit" id="modal-submit-btn"
                            class="btn-dark" style="flex:1.5; justify-content:center;">
                        ยืนยัน
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAction(id, action, empName, rewardTitle) {
    document.getElementById('form-action').value         = action;
    document.getElementById('form-redemption-id').value  = id;
    document.getElementById('form-note').value           = '';

    var isFulfill = (action === 'fulfill');
    document.getElementById('modal-title').textContent =
        isFulfill ? '✓ ยืนยันการจัดส่งรางวัล' : '✕ ยืนยันการยกเลิก';

    document.getElementById('modal-desc').textContent =
        isFulfill
            ? empName + ' แลกรางวัล "' + rewardTitle + '" — ยืนยันว่าได้จัดส่งหรือมอบรางวัลให้เรียบร้อยแล้ว'
            : empName + ' แลกรางวัล "' + rewardTitle + '" — ยืนยันการยกเลิก Token จะถูกคืนให้พนักงานทันที';

    var btn = document.getElementById('modal-submit-btn');
    if (isFulfill) {
        btn.textContent  = '✓ ยืนยันจัดส่ง';
        btn.style.background = '#518e5c';
    } else {
        btn.textContent  = '✕ ยืนยันยกเลิก';
        btn.style.background = '#d2592a';
    }

    document.getElementById('action-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeAction() {
    document.getElementById('action-modal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAction();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
