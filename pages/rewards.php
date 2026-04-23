<?php
/**
 * pages/rewards.php
 * Token Shop — employees redeem earned tokens for rewards
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$employeeId = (int)$_SESSION['employee_id'];
$pdo        = getDB();

// ══════════════════════════════════════════════════════════════
// AJAX — POST handler (redeem action)
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem') {
    header('Content-Type: application/json');
    validateCsrf();

    $rewardId = (int)($_POST['reward_id'] ?? 0);
    if ($rewardId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรางวัลที่ระบุ']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Lock row to prevent race conditions on limited stock
        $stmt = $pdo->prepare("
            SELECT reward_id, title, token_cost, stock, is_active
            FROM   dbo.rewards WITH (UPDLOCK, ROWLOCK)
            WHERE  reward_id = ?
        ");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();

        if (!$reward || !(bool)$reward['is_active']) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'รางวัลนี้ไม่พร้อมให้แลก']);
            exit;
        }

        $cost = (int)$reward['token_cost'];

        if ($reward['stock'] !== null && (int)$reward['stock'] <= 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'รางวัลนี้หมดสต็อกแล้ว']);
            exit;
        }

        // Lock wallet row
        $stmt = $pdo->prepare("
            SELECT balance
            FROM   dbo.token_wallets WITH (UPDLOCK, ROWLOCK)
            WHERE  employee_id = ?
        ");
        $stmt->execute([$employeeId]);
        $walletRow = $stmt->fetch();
        $balance   = $walletRow ? (int)$walletRow['balance'] : 0;

        if ($balance < $cost) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Token ไม่เพียงพอ (ต้องการ ' . $cost . ', คุณมี ' . $balance . ')',
            ]);
            exit;
        }

        // 1. Record negative transaction
        $pdo->prepare("
            INSERT INTO dbo.token_transactions (employee_id, amount, tx_type, note)
            VALUES (?, ?, 'redemption', ?)
        ")->execute([$employeeId, -$cost, 'แลกรางวัล: ' . $reward['title']]);

        // 2. Deduct wallet
        $pdo->prepare("
            UPDATE dbo.token_wallets
            SET    balance     = balance - ?,
                   total_spent = total_spent + ?,
                   updated_at  = GETDATE()
            WHERE  employee_id = ?
        ")->execute([$cost, $cost, $employeeId]);

        // 3. Create redemption record
        $pdo->prepare("
            INSERT INTO dbo.reward_redemptions (employee_id, reward_id, tokens_spent, status)
            VALUES (?, ?, ?, 'pending')
        ")->execute([$employeeId, $rewardId, $cost]);

        // 4. Decrement stock if limited
        if ($reward['stock'] !== null) {
            $pdo->prepare("
                UPDATE dbo.rewards SET stock = stock - 1 WHERE reward_id = ?
            ")->execute([$rewardId]);
        }

        $pdo->commit();

        // Refresh session cache
        $_SESSION['token_balance'] = getWalletBalance($employeeId);

        echo json_encode([
            'success'     => true,
            'message'     => 'แลกรางวัลสำเร็จ! HR จะติดต่อกลับเร็วๆ นี้',
            'new_balance' => (int)$_SESSION['token_balance'],
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[MissionToken] redeem error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// PAGE LOAD — fetch data
// ══════════════════════════════════════════════════════════════
$wallet        = getWalletInfo($employeeId);
$rewards       = [];
$myRedemptions = [];
$dataError     = null;

try {
    // Active rewards available for redemption
    $rewards = $pdo->query("
        SELECT reward_id, title, description, image_emoji, category, token_cost, stock
        FROM   dbo.rewards
        WHERE  is_active = 1
          AND  (stock IS NULL OR stock > 0)
        ORDER BY token_cost ASC, created_at ASC
    ")->fetchAll();

    // My redemptions — latest 30
    $stmt = $pdo->prepare("
        SELECT TOP 30
               rd.redemption_id, rd.tokens_spent, rd.status,
               rd.redeemed_at,   rd.processed_at, rd.admin_note,
               rw.title      AS reward_title,
               rw.image_emoji,
               rw.category
        FROM   dbo.reward_redemptions rd
        JOIN   dbo.rewards            rw ON rw.reward_id = rd.reward_id
        WHERE  rd.employee_id = ?
        ORDER BY rd.redeemed_at DESC
    ");
    $stmt->execute([$employeeId]);
    $myRedemptions = $stmt->fetchAll();

} catch (Throwable $e) {
    error_log('[MissionToken] rewards page load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่';
}

// ── Static lookups ───────────────────────────────────────────
$catMeta = [
    'voucher' => ['label' => 'คูปอง',        'color' => '#2f4e9d', 'bg' => '#eaedfa'],
    'leave'   => ['label' => 'วันหยุดพิเศษ', 'color' => '#518e5c', 'bg' => '#e6f4e9'],
    'merch'   => ['label' => 'ของที่ระลึก',   'color' => '#62307a', 'bg' => '#f1e8f7'],
    'perk'    => ['label' => 'สิทธิพิเศษ',   'color' => '#c9a830', 'bg' => '#fdf4d0'],
    'general' => ['label' => 'ทั่วไป',        'color' => '#6b6e77', 'bg' => '#eeecea'],
];

$statusMeta = [
    'pending'   => ['label' => 'รอดำเนินการ', 'color' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fcd34d'],
    'fulfilled' => ['label' => 'จัดส่งแล้ว',  'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#86efac'],
    'cancelled' => ['label' => 'ยกเลิก',       'color' => '#9f1239', 'bg' => '#fff1f2', 'border' => '#fca5a5'],
];

$activeCategories = array_unique(array_column($rewards, 'category'));

// ── Stat: pending redemptions ─────────────────────────────
$myPending = count(array_filter($myRedemptions, fn($r) => $r['status'] === 'pending'));

$pageTitle  = 'ร้านแลกรางวัล';
$activePage = 'rewards';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Reward card ──────────────────────────────────── */
    .reward-card {
        background: #fdfcdf;
        border: 1px solid #e6e2d6;
        border-radius: 18px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.22s cubic-bezier(.22,.97,.5,1.18),
                    box-shadow 0.22s ease;
        cursor: default;
    }
    .reward-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 12px 32px rgba(9,17,19,0.12);
    }
    .reward-card.reward-no-balance {
        opacity: 0.58;
    }
    .reward-card.reward-hidden { display: none; }

    /* ── Category filter pills ───────────────────────── */
    .cat-pill {
        padding: 0.35rem 1rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 500;
        border: 1.5px solid #d4d0c8;
        background: transparent;
        color: #6b6e77;
        cursor: pointer;
        transition: all 0.18s;
        font-family: 'Prompt', sans-serif;
    }
    .cat-pill:hover  { border-color: #dab937; color: #091113; }
    .cat-pill.active { background: #091113; border-color: #091113; color: #eeebe1; }

    /* ── Modal backdrop ──────────────────────────────── */
    #redeem-modal {
        display: none;
        position: fixed; inset: 0; z-index: 9000;
        background: rgba(9,17,19,0.55);
        backdrop-filter: blur(3px);
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }
    #redeem-modal.open { display: flex; }

    .modal-box {
        background: #fdfcdf;
        border: 1px solid #e6e2d6;
        border-radius: 22px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 24px 80px rgba(9,17,19,0.22);
        overflow: hidden;
        animation: modal-in 0.28s cubic-bezier(.22,.97,.5,1.18);
    }
    @keyframes modal-in {
        from { opacity:0; transform: scale(0.88) translateY(24px); }
        to   { opacity:1; transform: scale(1) translateY(0); }
    }

    /* ── Table rows ───────────────────────────────────── */
    .hist-row { border-bottom: 1px solid #ece9e0; }
    .hist-row:last-child { border-bottom: none; }
    .hist-row:hover { background: #faf8f2; }

    /* ── Success state in modal ───────────────────────── */
    @keyframes pop-in {
        0%   { transform: scale(0.5); opacity: 0; }
        70%  { transform: scale(1.15); }
        100% { transform: scale(1);   opacity: 1; }
    }
    .success-pop { animation: pop-in 0.4s cubic-bezier(.22,.97,.5,1.18) both; }
</style>

<!-- ══════════════════════════════════════════════════════════
     SHOP HEADER — full-bleed, ivory gradient (mirrors challenges.php)
═══════════════════════════════════════════════════════════ -->
<div style="position:relative; left:50%; margin-left:-50vw; width:100vw;
            background: linear-gradient(135deg,#fdfcdf 0%,#eeebe1 55%,#eeebe1 100%);
            border-bottom: 1px solid #e6e2d6;
            margin-top:-2rem; margin-bottom:2.5rem;">

    <!-- Gold glow top-right -->
    <div style="position:absolute;top:-80px;right:-80px;width:360px;height:360px;border-radius:50%;
                background:radial-gradient(circle,rgba(218,185,55,0.14),transparent 65%);
                filter:blur(40px);pointer-events:none;"></div>

    <div style="max-width:80rem; margin:0 auto; padding:2.5rem 2rem 2.25rem; position:relative;">

        <!-- Eyebrow -->
        <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.35em;
                  text-transform:uppercase; color:#c9a830; margin-bottom:0.45rem;">
            Token Shop
        </p>

        <!-- Main row: title + coin decoration -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
            <div>
                <h1 style="font-size:2.1rem; font-weight:700; color:#091113; line-height:1.1; margin:0;">
                    ร้านแลกรางวัล
                </h1>
                <p style="font-size:0.88rem; color:#6b6e77; margin-top:0.35rem;">
                    ใช้ Token สะสมแลกรับรางวัลและสิทธิประโยชน์พิเศษจาก JOURNAL
                </p>
            </div>
            <!-- Decorative coin stack -->
            <div style="flex-shrink:0; opacity:0.30; display:flex; gap:-4px; align-items:center;"
                 aria-hidden="true">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     style="width:52px;height:52px;object-fit:contain;
                            filter:drop-shadow(0 4px 8px rgba(218,185,55,0.4));" alt="">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     style="width:38px;height:38px;object-fit:contain; margin-left:-14px; margin-top:10px;
                            filter:drop-shadow(0 4px 8px rgba(218,185,55,0.3));" alt="">
            </div>
        </div>

        <!-- Stats row -->
        <div style="display:flex; flex-wrap:wrap; gap:0 2rem; margin-top:1.4rem;
                    padding-top:1.2rem; border-top:1px solid #e6e2d6; align-items:center;">
            <!-- Balance -->
            <div style="display:flex; flex-direction:column; gap:0.15rem;">
                <div style="display:flex; align-items:center; gap:0.45rem;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="22" height="22" style="object-fit:contain;" alt="">
                    <span id="hdr-balance"
                          style="font-size:1.65rem; font-weight:700; color:#dab937; line-height:1;">
                        <?php echo formatTokens((int)$wallet['balance']); ?>
                    </span>
                </div>
                <span style="font-size:0.62rem; letter-spacing:0.12em; text-transform:uppercase; color:#6b6e77;">
                    ยอดคงเหลือ
                </span>
            </div>

            <div style="width:1px; height:36px; background:#e0ddd4; flex-shrink:0;"></div>

            <!-- Total spent -->
            <div style="display:flex; flex-direction:column; gap:0.15rem;">
                <span style="font-size:1.65rem; font-weight:700; color:#091113; line-height:1;">
                    <?php echo formatTokens((int)$wallet['total_spent']); ?>
                </span>
                <span style="font-size:0.62rem; letter-spacing:0.12em; text-transform:uppercase; color:#6b6e77;">
                    Token ที่ใช้ไป
                </span>
            </div>

            <div style="width:1px; height:36px; background:#e0ddd4; flex-shrink:0;"></div>

            <!-- Redemption count -->
            <div style="display:flex; flex-direction:column; gap:0.15rem;">
                <span style="font-size:1.65rem; font-weight:700; color:#091113; line-height:1;">
                    <?php echo count($myRedemptions); ?>
                </span>
                <span style="font-size:0.62rem; letter-spacing:0.12em; text-transform:uppercase; color:#6b6e77;">
                    รายการแลกแล้ว
                </span>
            </div>

            <?php if ($myPending > 0): ?>
            <div style="width:1px; height:36px; background:#e0ddd4; flex-shrink:0;"></div>
            <div style="display:flex; align-items:center; gap:0.5rem;
                        background:#fffbeb; border:1px solid #fcd34d;
                        border-radius:999px; padding:0.3rem 0.85rem;">
                <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;
                              flex-shrink:0; animation:coin-bounce 1.5s ease-in-out infinite;"></span>
                <span style="font-size:0.78rem; font-weight:600; color:#b45309;">
                    <?php echo $myPending; ?> รายการรอดำเนินการ
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- end shop header -->

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

<?php if ($dataError): ?>
<div class="mb-6 rounded-xl border border-[#edc3b2] bg-[#fff1ea] px-5 py-4 text-sm"
     style="color:#d2592a;">
    <?= e($dataError) ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     CATEGORY FILTER PILLS
═══════════════════════════════════════════════════════ -->
<div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.75rem; align-items:center;">
    <button class="cat-pill active" data-cat="all" onclick="filterCat(this,'all')">
        ทั้งหมด <span style="font-weight:700;">(<?php echo count($rewards); ?>)</span>
    </button>
    <?php foreach ($activeCategories as $cat):
        $meta  = $catMeta[$cat] ?? $catMeta['general'];
        $count = count(array_filter($rewards, fn($r) => $r['category'] === $cat));
    ?>
    <button class="cat-pill" data-cat="<?= e($cat) ?>" onclick="filterCat(this,'<?= e($cat) ?>')">
        <?= e($meta['label']) ?> <span style="font-weight:700;">(<?= $count ?>)</span>
    </button>
    <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════
     REWARDS GRID
═══════════════════════════════════════════════════════ -->
<?php if (empty($rewards)): ?>
<div style="text-align:center; padding:4rem 1rem; color:#6b6e77;">
    <div style="font-size:3.5rem; margin-bottom:1rem;">🛍️</div>
    <p style="font-size:1.1rem; font-weight:500; color:#3a3e43;">ยังไม่มีรางวัลในขณะนี้</p>
    <p style="font-size:0.85rem; margin-top:0.35rem;">ติดตามรางวัลใหม่ได้เร็วๆ นี้</p>
</div>
<?php else: ?>
<div id="rewards-grid"
     style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap:1.25rem;
            margin-bottom:3rem;">
    <?php
    $myBalance = (int)$wallet['balance'];
    foreach ($rewards as $rw):
        $cat     = $rw['category'];
        $meta    = $catMeta[$cat] ?? $catMeta['general'];
        $cost    = (int)$rw['token_cost'];
        $canAfford = $myBalance >= $cost;
        $stockLeft = $rw['stock'] === null ? null : (int)$rw['stock'];
    ?>
    <div class="reward-card <?php echo $canAfford ? '' : 'reward-no-balance'; ?>"
         data-category="<?= e($cat) ?>">

        <!-- Top: emoji + category -->
        <div style="padding:1.5rem 1.5rem 1rem; display:flex; align-items:flex-start; justify-content:space-between;">
            <div style="font-size:2.8rem; line-height:1; user-select:none;">
                <?= e($rw['image_emoji'] ?: '🎁') ?>
            </div>
            <span style="font-size:0.68rem; font-weight:600; padding:0.22rem 0.65rem;
                         border-radius:999px; letter-spacing:0.04em;
                         background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;">
                <?= e($meta['label']) ?>
            </span>
        </div>

        <!-- Body: title + description -->
        <div style="padding:0 1.5rem 1rem; flex:1;">
            <h3 style="font-size:1rem; font-weight:600; color:#091113; margin:0 0 0.35rem;">
                <?= e($rw['title']) ?>
            </h3>
            <?php if (!empty($rw['description'])): ?>
            <p style="font-size:0.82rem; color:#6b6e77; line-height:1.5; margin:0;
                      display:-webkit-box; -webkit-line-clamp:2;
                      -webkit-box-orient:vertical; overflow:hidden;">
                <?= e($rw['description']) ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- Footer: cost + stock + button -->
        <div style="padding:1rem 1.5rem 1.25rem; border-top:1px solid #ece9e0;
                    display:flex; align-items:center; justify-content:space-between; gap:0.75rem;">
            <div>
                <!-- Cost chip -->
                <div style="display:flex; align-items:center; gap:0.3rem; margin-bottom:0.2rem;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="16" height="16" style="object-fit:contain;" alt="">
                    <span style="font-size:1.1rem; font-weight:700; color:#091113;"><?= $cost ?></span>
                    <span style="font-size:0.72rem; color:#6b6e77;">token</span>
                </div>
                <!-- Stock -->
                <?php if ($stockLeft !== null): ?>
                <span style="font-size:0.68rem; color:<?php echo $stockLeft <= 3 ? '#d2592a' : '#6b6e77'; ?>;">
                    เหลือ <?= $stockLeft ?> ชิ้น
                </span>
                <?php else: ?>
                <span style="font-size:0.68rem; color:#aaa8a3;">ไม่จำกัดจำนวน</span>
                <?php endif; ?>
            </div>

            <?php if ($canAfford): ?>
            <button class="btn-dark"
                    style="padding:0.5rem 1.1rem; font-size:0.82rem; border-radius:10px; flex-shrink:0;"
                    onclick='openRedeem(<?= (int)$rw['reward_id'] ?>, <?= json_encode($rw['title']) ?>, <?= $cost ?>)'>
                แลกเลย
            </button>
            <?php else: ?>
            <button disabled
                    style="padding:0.5rem 1.1rem; font-size:0.82rem; border-radius:10px; flex-shrink:0;
                           background:#e0ddd6; color:#aaa8a3; border:none; cursor:not-allowed;
                           font-family:'Prompt',sans-serif; font-weight:500;">
                Token ไม่พอ
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     MY REDEMPTIONS
═══════════════════════════════════════════════════════ -->
<?php if (!empty($myRedemptions)): ?>
<section style="margin-bottom:3rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem;">
        <h2 class="section-title">📋 การแลกรางวัลของฉัน</h2>
    </div>

    <div style="background:#fdfcdf; border:1px solid #e6e2d6; border-radius:16px; overflow:hidden;">
        <!-- Header row -->
        <div style="display:grid; grid-template-columns:1fr auto auto auto;
                    gap:1rem; padding:0.75rem 1.25rem;
                    background:#f4f1e8; border-bottom:1px solid #e6e2d6;
                    font-size:0.72rem; font-weight:600; letter-spacing:0.06em;
                    text-transform:uppercase; color:#6b6e77;">
            <span>รางวัล</span>
            <span>Token ที่ใช้</span>
            <span>วันที่แลก</span>
            <span>สถานะ</span>
        </div>

        <?php foreach ($myRedemptions as $rd):
            $sm = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
        ?>
        <div class="hist-row"
             style="display:grid; grid-template-columns:1fr auto auto auto;
                    gap:1rem; padding:0.9rem 1.25rem; align-items:center;">
            <!-- Reward info -->
            <div style="display:flex; align-items:center; gap:0.6rem; min-width:0;">
                <span style="font-size:1.4rem; flex-shrink:0; user-select:none;">
                    <?= e($rd['image_emoji'] ?: '🎁') ?>
                </span>
                <div style="min-width:0;">
                    <p style="font-size:0.88rem; font-weight:500; color:#091113; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($rd['reward_title']) ?>
                    </p>
                    <?php if (!empty($rd['admin_note'])): ?>
                    <p style="font-size:0.74rem; color:#6b6e77; margin:0.1rem 0 0;">
                        หมายเหตุ: <?= e($rd['admin_note']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Cost -->
            <div style="display:flex; align-items:center; gap:0.3rem; white-space:nowrap;">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     width="14" height="14" style="object-fit:contain;" alt="">
                <span style="font-size:0.88rem; font-weight:600; color:#091113;">
                    <?= (int)$rd['tokens_spent'] ?>
                </span>
            </div>
            <!-- Date -->
            <span style="font-size:0.8rem; color:#6b6e77; white-space:nowrap;">
                <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
            </span>
            <!-- Status badge -->
            <span style="font-size:0.72rem; font-weight:600; padding:0.2rem 0.7rem;
                         border-radius:999px; white-space:nowrap;
                         background:<?= $sm['bg'] ?>; color:<?= $sm['color'] ?>;
                         border:1px solid <?= $sm['border'] ?>;">
                <?= $sm['label'] ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</div><!-- /max-w-7xl -->

<!-- ══════════════════════════════════════════════════════
     REDEEM CONFIRM MODAL
═══════════════════════════════════════════════════════ -->
<div id="redeem-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"
     onclick="if(event.target===this && !_redeemBusy) closeRedeem();">
    <div class="modal-box">

        <!-- ── Default state: confirm ─────────────────── -->
        <div id="modal-confirm">
            <!-- Header bar -->
            <div style="background:linear-gradient(135deg,#091113,#1a2022);
                        padding:1.25rem 1.5rem; display:flex; align-items:center; gap:0.75rem;">
                <div style="width:36px;height:36px;border-radius:50%;background:#dab937;
                             display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="20" height="20" style="object-fit:contain;" alt="">
                </div>
                <h2 id="modal-title"
                    style="font-size:1rem; font-weight:600; color:#eeebe1; margin:0;">
                    ยืนยันการแลกรางวัล
                </h2>
                <button onclick="closeRedeem()"
                        style="margin-left:auto; background:none; border:none; cursor:pointer;
                               color:#6b6e77; padding:4px; border-radius:6px; line-height:0;"
                        aria-label="ปิด">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div style="padding:1.5rem 1.75rem;">
                <!-- Reward display -->
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;
                             background:#f4f1e8; border-radius:14px; padding:1rem 1.25rem;">
                    <span id="modal-emoji" style="font-size:2.5rem; user-select:none;"></span>
                    <div>
                        <p style="font-size:0.68rem; letter-spacing:0.1em; text-transform:uppercase;
                                  color:#6b6e77; margin:0 0 0.2rem;">รางวัลที่เลือก</p>
                        <p id="modal-reward-name"
                           style="font-size:1.05rem; font-weight:600; color:#091113; margin:0;"></p>
                    </div>
                </div>

                <!-- Cost breakdown -->
                <div style="display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:#6b6e77;">ราคา</span>
                        <div style="display:flex; align-items:center; gap:0.3rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="14" height="14" style="object-fit:contain;" alt="">
                            <span id="modal-cost"
                                  style="font-size:0.95rem; font-weight:700; color:#d2592a;"></span>
                            <span style="font-size:0.78rem; color:#6b6e77;">token</span>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:#6b6e77;">ยอดปัจจุบัน</span>
                        <div style="display:flex; align-items:center; gap:0.3rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="14" height="14" style="object-fit:contain;" alt="">
                            <span id="modal-balance-before"
                                  style="font-size:0.95rem; font-weight:600; color:#091113;"></span>
                        </div>
                    </div>
                    <div style="height:1px; background:#e6e2d6;"></div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; font-weight:600; color:#091113;">ยอดหลังแลก</span>
                        <div style="display:flex; align-items:center; gap:0.3rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="14" height="14" style="object-fit:contain;" alt="">
                            <span id="modal-balance-after"
                                  style="font-size:1.1rem; font-weight:700; color:#518e5c;"></span>
                        </div>
                    </div>
                </div>

                <!-- Notice -->
                <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:10px;
                             padding:0.75rem 1rem; margin-bottom:1.5rem;
                             display:flex; gap:0.6rem; align-items:flex-start;">
                    <span style="flex-shrink:0; font-size:1rem;">ℹ️</span>
                    <p style="font-size:0.78rem; color:#92400e; margin:0; line-height:1.55;">
                        หลังยืนยัน HR จะติดต่อกลับเพื่อดำเนินการมอบรางวัลให้คุณ
                    </p>
                </div>

                <!-- Error message -->
                <div id="modal-error"
                     style="display:none; background:#fff1f2; border:1px solid #fca5a5;
                            border-radius:10px; padding:0.65rem 1rem; margin-bottom:1rem;
                            font-size:0.82rem; color:#9f1239;"></div>

                <!-- Action buttons -->
                <div style="display:flex; gap:0.75rem;">
                    <button onclick="closeRedeem()"
                            class="btn-outline" style="flex:1; justify-content:center;"
                            id="modal-cancel-btn">
                        ยกเลิก
                    </button>
                    <button onclick="submitRedeem()"
                            class="btn-gold" style="flex:1.5; justify-content:center;"
                            id="modal-confirm-btn">
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                             width="16" height="16" style="object-fit:contain;" alt="">
                        ยืนยันแลกรางวัล
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Success state ──────────────────────────── -->
        <div id="modal-success" style="display:none; padding:2.5rem 1.75rem; text-align:center;">
            <div class="success-pop" style="font-size:4rem; margin-bottom:1rem;">🎉</div>
            <h3 style="font-size:1.25rem; font-weight:700; color:#091113; margin:0 0 0.5rem;">
                แลกรางวัลสำเร็จ!
            </h3>
            <p style="font-size:0.88rem; color:#6b6e77; line-height:1.6; margin:0 0 0.25rem;">
                HR จะติดต่อกลับเพื่อดำเนินการมอบรางวัลให้คุณเร็วๆ นี้
            </p>
            <p id="success-balance-text"
               style="font-size:0.88rem; color:#6b6e77; margin:0 0 2rem;"></p>
            <button onclick="closeRedeem()" class="btn-dark" style="width:100%; justify-content:center;">
                รับทราบ
            </button>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    var _currentRewardId = 0;
    var _currentCost     = 0;
    var _redeemBusy      = false;

    /* Make _redeemBusy accessible for the backdrop click handler */
    window._redeemBusy = false;

    // ── Category filter ────────────────────────────────────
    window.filterCat = function (btn, cat) {
        document.querySelectorAll('.cat-pill').forEach(function (p) {
            p.classList.remove('active');
        });
        btn.classList.add('active');

        document.querySelectorAll('.reward-card').forEach(function (card) {
            var show = (cat === 'all' || card.dataset.category === cat);
            card.classList.toggle('reward-hidden', !show);
        });
    };

    // ── Open confirm modal ─────────────────────────────────
    window.openRedeem = function (id, title, cost) {
        _currentRewardId = id;
        _currentCost     = cost;

        var balance = parseInt(
            document.getElementById('hdr-balance').textContent.replace(/,/g, ''), 10
        ) || <?php echo (int)$wallet['balance']; ?>;

        /* Find emoji from card */
        var emoji = '🎁';
        document.querySelectorAll('.reward-card').forEach(function (card) {
            var btn = card.querySelector('button[onclick*="openRedeem(' + id + ',"]');
            if (btn) {
                var emojiEl = card.querySelector('[style*="font-size:2.8rem"]');
                if (emojiEl) emoji = emojiEl.textContent.trim();
            }
        });

        document.getElementById('modal-emoji').textContent        = emoji;
        document.getElementById('modal-reward-name').textContent  = title;
        document.getElementById('modal-cost').textContent         = cost;
        document.getElementById('modal-balance-before').textContent = balance;
        document.getElementById('modal-balance-after').textContent  = (balance - cost);

        document.getElementById('modal-error').style.display = 'none';
        document.getElementById('modal-confirm').style.display = '';
        document.getElementById('modal-success').style.display = 'none';
        document.getElementById('modal-confirm-btn').disabled  = false;
        document.getElementById('modal-confirm-btn').textContent = '';
        var btn = document.getElementById('modal-confirm-btn');
        btn.innerHTML = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="16" height="16" style="object-fit:contain;margin-right:6px" alt=""> ยืนยันแลกรางวัล';

        document.getElementById('redeem-modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    // ── Close modal ────────────────────────────────────────
    window.closeRedeem = function () {
        if (_redeemBusy) return;
        document.getElementById('redeem-modal').classList.remove('open');
        document.body.style.overflow = '';
    };

    // ── Submit redemption ──────────────────────────────────
    window.submitRedeem = function () {
        if (_redeemBusy) return;
        _redeemBusy = true;
        window._redeemBusy = true;

        var btn = document.getElementById('modal-confirm-btn');
        btn.disabled     = true;
        btn.textContent  = 'กำลังดำเนินการ…';

        document.getElementById('modal-error').style.display = 'none';
        document.getElementById('modal-cancel-btn').disabled = true;

        var csrf = document.querySelector('meta[name="csrf-token"]')
                       ? document.querySelector('meta[name="csrf-token"]').content : '';

        fetch(window.location.href, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                action    : 'redeem',
                reward_id : _currentRewardId,
                csrf_token: csrf,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            _redeemBusy = false;
            window._redeemBusy = false;

            if (data.success) {
                /* Update balance in header */
                var newBal = data.new_balance;
                document.getElementById('hdr-balance').textContent = newBal.toLocaleString('th-TH');
                var navBal = document.getElementById('nav-balance');
                if (navBal) navBal.textContent = newBal.toLocaleString('th-TH');

                /* Show success panel */
                document.getElementById('success-balance-text').textContent =
                    'ยอด Token คงเหลือ: ' + newBal.toLocaleString('th-TH') + ' token';

                document.getElementById('modal-confirm').style.display = 'none';
                document.getElementById('modal-success').style.display = '';

                /* Disable the "แลกเลย" button for this reward to avoid re-click */
                document.querySelectorAll('.reward-card').forEach(function (card) {
                    var anyBtn = card.querySelector('button[onclick*="openRedeem(' + _currentRewardId + ',"]');
                    if (anyBtn) { anyBtn.disabled = true; anyBtn.textContent = 'แลกแล้ว'; }
                });

                /* Reload page after dismiss to refresh redemption list */
                window.__reloadOnClose = true;
            } else {
                var errEl = document.getElementById('modal-error');
                errEl.textContent   = data.message || 'เกิดข้อผิดพลาด';
                errEl.style.display = 'block';

                btn.disabled     = false;
                btn.innerHTML    = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="16" height="16" style="object-fit:contain;margin-right:6px" alt=""> ยืนยันแลกรางวัล';
                document.getElementById('modal-cancel-btn').disabled = false;
            }
        })
        .catch(function () {
            _redeemBusy = false;
            window._redeemBusy = false;
            var errEl = document.getElementById('modal-error');
            errEl.textContent   = 'การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="16" height="16" style="object-fit:contain;margin-right:6px" alt=""> ยืนยันแลกรางวัล';
            document.getElementById('modal-cancel-btn').disabled = false;
        });
    };

    /* Reload after success close */
    var origClose = window.closeRedeem;
    window.closeRedeem = function () {
        origClose();
        if (window.__reloadOnClose) {
            window.__reloadOnClose = false;
            location.reload();
        }
    };

    /* Escape key closes modal */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeRedeem();
    });
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
