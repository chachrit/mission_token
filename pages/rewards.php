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
// AJAX — POST handler (cancel redemption)
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_redemption') {
    header('Content-Type: application/json');
    validateCsrf();

    $redemptionId = (int)($_POST['redemption_id'] ?? 0);
    if ($redemptionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการที่ระบุ']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT rd.redemption_id, rd.employee_id, rd.reward_id,
                   rd.tokens_spent, rd.status,
                   rw.stock
            FROM   dbo.reward_redemptions rd WITH (UPDLOCK, ROWLOCK)
            JOIN   dbo.rewards rw ON rw.reward_id = rd.reward_id
            WHERE  rd.redemption_id = ?
              AND  rd.employee_id   = ?
        ");
        $stmt->execute([$redemptionId, $employeeId]);
        $rdRow = $stmt->fetch();

        if (!$rdRow) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'ไม่พบรายการ']);
            exit;
        }

        if ($rdRow['status'] !== 'pending') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถยกเลิกรายการที่ดำเนินการแล้ว']);
            exit;
        }

        $tokensBack = (int)$rdRow['tokens_spent'];

        // 1. Mark cancelled
        $pdo->prepare("
            UPDATE dbo.reward_redemptions
            SET    status = 'cancelled', processed_at = GETDATE()
            WHERE  redemption_id = ?
        ")->execute([$redemptionId]);

        // 2. Refund token transaction
        $pdo->prepare("
            INSERT INTO dbo.token_transactions (employee_id, amount, tx_type, note)
            VALUES (?, ?, 'admin_adjust', ?)
        ")->execute([$employeeId, $tokensBack, 'คืน Token: ยกเลิกการแลกรางวัล']);

        // 3. Restore wallet
        $pdo->prepare("
            UPDATE dbo.token_wallets
            SET    balance     = balance + ?,
                   total_spent = total_spent - ?,
                   updated_at  = GETDATE()
            WHERE  employee_id = ?
        ")->execute([$tokensBack, $tokensBack, $employeeId]);

        // 4. Restore stock if limited
        if ($rdRow['stock'] !== null) {
            $pdo->prepare("
                UPDATE dbo.rewards SET stock = stock + 1 WHERE reward_id = ?
            ")->execute([$rdRow['reward_id']]);
        }

        $pdo->commit();
        $_SESSION['token_balance'] = getWalletBalance($employeeId);

        echo json_encode([
            'success'     => true,
            'message'     => 'ยกเลิกการแลกรางวัลแล้ว Token ถูกคืนให้คุณแล้ว',
            'new_balance' => (int)$_SESSION['token_balance'],
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[MissionToken] cancel_redemption error: ' . $e->getMessage());
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
               rd.processed_by,
               rw.title      AS reward_title,
               rw.image_emoji,
               rw.category,
               rw.coupon_code,
               ap.full_name  AS processed_by_name
        FROM   dbo.reward_redemptions rd
        JOIN   dbo.rewards            rw ON rw.reward_id = rd.reward_id
        LEFT JOIN dbo.employees       ap ON ap.employee_id = rd.processed_by
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
    'voucher' => ['label' => 'คูปอง',        'color' => '#8fa8e0', 'bg' => '#eaedfa'],
    'leave'   => ['label' => 'วันหยุดพิเศษ', 'color' => '#7ec98a', 'bg' => '#e6f4e9'],
    'merch'   => ['label' => 'ของที่ระลึก',   'color' => '#c48fe0', 'bg' => '#f1e8f7'],
    'perk'    => ['label' => 'สิทธิพิเศษ',   'color' => '#dab937', 'bg' => '#fdf4d0'],
    'general' => ['label' => 'ทั่วไป',        'color' => '#8a8e97', 'bg' => '#eeecea'],
];

$statusMeta = [
    'pending'   => ['label' => 'รอดำเนินการ', 'color' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fcd34d'],
    'fulfilled' => ['label' => 'มอบแล้ว',     'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#86efac'],
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
/* ─────────────────────────────────────────────────────────────
   REWARDS PAGE  "The Vault"  prefix: rw-
───────────────────────────────────────────────────────────── */

/* ── Category filter pills ───────────────────────────────── */
.rw-cat-pill {
    padding: 0.35rem 1rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1.5px solid rgba(255,255,255,0.10);
    background: transparent;
    color: #6b6e77;
    cursor: pointer;
    transition: all 0.18s;
    font-family: 'Prompt', sans-serif;
    letter-spacing: 0.03em;
}
.rw-cat-pill:hover  { border-color: rgba(218,185,55,0.40); color: #eeebe1; background: rgba(218,185,55,0.06); }
.rw-cat-pill.active { background: rgba(218,185,55,0.15); border-color: rgba(218,185,55,0.45); color: #f8e769; }

/* ── Reward card ─────────────────────────────────────────── */
.rw-reward-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 18px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform 0.22s cubic-bezier(0.22,1,0.36,1),
                box-shadow 0.22s ease,
                border-color 0.22s ease;
    cursor: default;
    backdrop-filter: blur(8px);
}
.rw-reward-card:hover {
    transform: translateY(-6px) scale(1.015);
    box-shadow: 0 0 0 1px rgba(218,185,55,0.35),
                0 16px 40px rgba(9,17,19,0.55),
                0 0 32px rgba(218,185,55,0.08);
    border-color: rgba(218,185,55,0.35);
}
.rw-reward-card.rw-no-balance {
    opacity: 0.40;
}
.rw-reward-card.rw-hidden { display: none; }

/* ── Card top accent bar ─────────────────────────────────── */
.rw-card-top-bar { height: 2px; background: linear-gradient(90deg, #dab937, #f8e769); flex-shrink: 0; }

/* ── Emoji circle ────────────────────────────────────────── */
.rw-emoji-wrap {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    flex-shrink: 0;
}

/* ── Modal backdrop ──────────────────────────────────────── */
#redeem-modal {
    display: none;
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(9,17,19,0.72);
    backdrop-filter: blur(6px);
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
#redeem-modal.open { display: flex; }

.rw-modal-box {
    background: rgba(15,20,23,0.97);
    border: 1px solid rgba(218,185,55,0.20);
    border-radius: 22px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.04),
                0 32px 80px rgba(9,17,19,0.80);
    overflow: hidden;
    animation: rw-modal-in 0.28s cubic-bezier(0.22,1,0.36,1);
    backdrop-filter: blur(20px);
}
@keyframes rw-modal-in {
    from { opacity:0; transform: scale(0.88) translateY(24px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}

/* ── History table rows ──────────────────────────────────── */
.rw-hist-row { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.15s; }
.rw-hist-row:last-child { border-bottom: none; }
.rw-hist-row:hover { background: rgba(218,185,55,0.04); }

/* ── Success animation ───────────────────────────────────── */
@keyframes pop-in {
    0%   { transform: scale(0.5); opacity: 0; }
    70%  { transform: scale(1.15); }
    100% { transform: scale(1);   opacity: 1; }
}
.success-pop { animation: pop-in 0.4s cubic-bezier(0.22,1,0.36,1) both; }

/* ── Stats bar divider: hide on narrow mobile ── */
@media (max-width: 560px) { .rw-stat-div { display: none; } }

/* ── Low-stock pulse ─────────────────────────────────────── */
@keyframes rw-stock-pulse { 0%,100% { opacity:1; } 50% { opacity:0.45; } }
.rw-stock-low { animation: rw-stock-pulse 1.4s ease-in-out infinite; }
</style>

<div class="rw-rewards-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

    <!-- ── Aurora background blobs ────────────────────────── -->
    <div style="position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden;" aria-hidden="true">
        <div style="position:absolute; width:700px; height:700px; border-radius:50%;
                    background:radial-gradient(circle,rgba(218,185,55,0.07) 0%,transparent 65%);
                    top:-150px; right:-150px; filter:blur(70px);
                    animation:ch-aurora-drift 20s ease-in-out infinite alternate;"></div>
        <div style="position:absolute; width:550px; height:550px; border-radius:50%;
                    background:radial-gradient(circle,rgba(79,139,152,0.06) 0%,transparent 65%);
                    bottom:-130px; left:-100px; filter:blur(80px);
                    animation:ch-aurora-drift 24s ease-in-out infinite alternate-reverse;"></div>
        <div style="position:absolute; width:320px; height:320px; border-radius:50%;
                    background:radial-gradient(circle,rgba(218,185,55,0.04) 0%,transparent 65%);
                    top:45%; left:38%; filter:blur(50px);
                    animation:ch-aurora-drift 16s ease-in-out infinite alternate;"></div>
    </div>

    <div style="position:relative; z-index:1; max-width:80rem; margin:0 auto; padding:0 1.5rem 5rem;">

        <!-- ══════════════════════════════════════════════════
             VAULT ACCESS HEADER
        ══════════════════════════════════════════════════ -->
        <div style="padding:2.75rem 0 2.5rem;">

            <p style="font-size:0.57rem; font-weight:700; letter-spacing:0.44em;
                      text-transform:uppercase; color:rgba(218,185,55,0.65); margin:0 0 0.6rem;">
                ⬡ &nbsp;VAULT ACCESS
            </p>

            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1.5rem; flex-wrap:wrap;">
                <div>
                    <h1 style="font-size:2.35rem; font-weight:800; color:#eeebe1; line-height:1.08;
                                margin:0 0 0.35rem; letter-spacing:-0.02em;">
                        ร้านแลกรางวัล
                    </h1>
                    <p style="font-size:0.88rem; color:#6b6e77; margin:0; line-height:1.5;">
                        ใช้ Token สะสมแลกรับรางวัลและสิทธิประโยชน์พิเศษจาก JOURNAL
                    </p>
                </div>
                <div style="flex-shrink:0; display:flex; align-items:center;" aria-hidden="true">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         style="width:58px;height:58px;object-fit:contain;opacity:0.65;
                                filter:drop-shadow(0 0 18px rgba(218,185,55,0.55));" alt="">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         style="width:36px;height:36px;object-fit:contain;opacity:0.28;
                                margin-left:-10px; margin-top:16px;
                                filter:drop-shadow(0 0 8px rgba(218,185,55,0.30));" alt="">
                </div>
            </div>

            <!-- Stats bar -->
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem;
                        margin-top:1.75rem; padding-top:1.5rem;
                        border-top:1px solid rgba(255,255,255,0.07);">

                <!-- Balance pill -->
                <div style="display:flex; align-items:center; gap:0.65rem;
                             background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.22);
                             border-radius:14px; padding:0.65rem 1.15rem;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="22" height="22"
                         style="object-fit:contain; filter:drop-shadow(0 0 8px rgba(218,185,55,0.65));" alt="">
                    <div>
                        <div style="font-size:0.52rem; font-weight:700; letter-spacing:0.16em;
                                    text-transform:uppercase; color:rgba(218,185,55,0.55); margin-bottom:0.08rem;">
                            ยอดคงเหลือ
                        </div>
                        <div id="hdr-balance"
                             style="font-size:1.5rem; font-weight:800; color:#f8e769;
                                    line-height:1; letter-spacing:-0.02em;">
                            <?php echo formatTokens((int)$wallet['balance']); ?>
                        </div>
                    </div>
                </div>

                <div class="rw-stat-div" style="width:1px; height:44px; background:rgba(255,255,255,0.07); flex-shrink:0;"></div>

                <div style="padding:0 0.25rem;">
                    <div style="font-size:1.1rem; font-weight:700; color:#eeebe1; line-height:1;">
                        <?php echo formatTokens((int)$wallet['total_spent']); ?>
                    </div>
                    <div style="font-size:0.52rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:#6b6e77; margin-top:0.15rem;">
                        Token ที่ใช้ไป
                    </div>
                </div>

                <div style="width:1px; height:44px; background:rgba(255,255,255,0.07); flex-shrink:0;"></div>

                <div style="padding:0 0.25rem;">
                    <div style="font-size:1.1rem; font-weight:700; color:#eeebe1; line-height:1;">
                        <?php echo count($myRedemptions); ?>
                    </div>
                    <div style="font-size:0.52rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:#6b6e77; margin-top:0.15rem;">
                        รายการแลกแล้ว
                    </div>
                </div>

                <?php if ($myPending > 0): ?>
                <div class="rw-stat-div" style="width:1px; height:44px; background:rgba(255,255,255,0.07); flex-shrink:0;"></div>
                <button onclick="openPendingList()"
                        style="display:flex; align-items:center; gap:0.5rem;
                               background:rgba(245,158,11,0.10); border:1px solid rgba(245,158,11,0.22);
                               border-radius:999px; padding:0.35rem 0.9rem; cursor:pointer;
                               font-family:'Prompt',sans-serif;
                               transition:background 0.18s, border-color 0.18s;"
                        onmouseover="this.style.background='rgba(245,158,11,0.18)'; this.style.borderColor='rgba(245,158,11,0.42)'"
                        onmouseout="this.style.background='rgba(245,158,11,0.10)'; this.style.borderColor='rgba(245,158,11,0.22)'">
                    <span style="width:7px;height:7px;border-radius:50%;background:#f59e0b;
                                  flex-shrink:0; animation:coin-bounce 1.5s ease-in-out infinite;"></span>
                    <span style="font-size:0.78rem; font-weight:600; color:#fbbf24;">
                        <?php echo $myPending; ?> รายการรอดำเนินการ
                    </span>
                    <svg fill="none" stroke="#fbbf24" viewBox="0 0 24 24" width="12" height="12" style="opacity:0.65;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php endif; ?>

            </div>
        </div><!-- end header -->

        <?php if ($dataError): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px;
                    border:1px solid rgba(210,89,42,0.28); background:rgba(210,89,42,0.08);
                    padding:0.9rem 1.2rem; font-size:0.85rem; color:#d2592a;">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════
             SECTION LABEL + CATEGORY PILLS
        ══════════════════════════════════════════════════ -->
        <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:1.35rem; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:0.55rem; margin-right:0.4rem;">
                <div style="width:4px; height:24px; background:linear-gradient(180deg,#dab937,#c9a830); border-radius:999px; flex-shrink:0;"></div>
                <span style="font-size:0.98rem; font-weight:700; color:#eeebe1;">สินค้า</span>
                <span style="font-size:0.68rem; font-weight:700; color:#091113; background:#dab937;
                             border-radius:999px; padding:0.15rem 0.55rem;"><?= count($rewards) ?></span>
            </div>
            <button class="rw-cat-pill active" data-cat="all" onclick="filterCat(this,'all')">ทั้งหมด</button>
            <?php foreach ($activeCategories as $cat):
                $meta  = $catMeta[$cat] ?? $catMeta['general'];
                $count = count(array_filter($rewards, fn($r) => $r['category'] === $cat));
            ?>
            <button class="rw-cat-pill" data-cat="<?= e($cat) ?>" onclick="filterCat(this,'<?= e($cat) ?>')">
                <?= e($meta['label']) ?> <span style="opacity:0.55; font-size:0.70rem;">(<?= $count ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ══════════════════════════════════════════════════
             REWARDS GRID
        ══════════════════════════════════════════════════ -->
        <?php if (empty($rewards)): ?>
        <div style="text-align:center; padding:5rem 1rem;">
            <div style="font-size:3rem; margin-bottom:1rem; opacity:0.22;">🔒</div>
            <p style="font-size:1rem; font-weight:600; color:#6b6e77; margin:0 0 0.3rem;">Vault ว่างอยู่ในขณะนี้</p>
            <p style="font-size:0.82rem; color:#6b6e77; margin:0;">ติดตามรางวัลใหม่ได้เร็วๆ นี้</p>
        </div>
        <?php else: ?>
        <div id="rewards-grid"
             style="display:grid; grid-template-columns:repeat(auto-fill,minmax(265px,1fr));
                    gap:1.25rem; margin-bottom:3rem;">
            <?php
            $myBalance = (int)$wallet['balance'];
            foreach ($rewards as $rw):
                $cat       = $rw['category'];
                $meta      = $catMeta[$cat] ?? $catMeta['general'];
                $cost      = (int)$rw['token_cost'];
                $canAfford = $myBalance >= $cost;
                $stockLeft = $rw['stock'] === null ? null : (int)$rw['stock'];
                $glowMap   = [
                    'voucher' => ['bg' => 'rgba(47,78,157,0.16)',   'border' => 'rgba(47,78,157,0.32)'],
                    'leave'   => ['bg' => 'rgba(81,142,92,0.16)',   'border' => 'rgba(81,142,92,0.32)'],
                    'merch'   => ['bg' => 'rgba(98,48,122,0.16)',   'border' => 'rgba(98,48,122,0.32)'],
                    'perk'    => ['bg' => 'rgba(201,168,48,0.16)',  'border' => 'rgba(201,168,48,0.32)'],
                    'general' => ['bg' => 'rgba(107,110,119,0.14)','border' => 'rgba(107,110,119,0.28)'],
                ];
                $glow     = $glowMap[$cat] ?? $glowMap['general'];
                $emojiStr = e($rw['image_emoji'] ?: '🎁');
            ?>
            <div class="rw-reward-card <?= $canAfford ? '' : 'rw-no-balance' ?>"
                 data-category="<?= e($cat) ?>"
                 data-emoji="<?= $emojiStr ?>">

                <div class="rw-card-top-bar"></div>

                <div style="padding:1.35rem 1.35rem 0.75rem; display:flex;
                             flex-direction:column; gap:0.85rem; flex:1;">

                    <!-- Top: emoji + category badge -->
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem;">
                        <div class="rw-emoji-wrap"
                             style="background:<?= $glow['bg'] ?>; border:1px solid <?= $glow['border'] ?>;">
                            <?= $emojiStr ?>
                        </div>
                        <span style="font-size:0.63rem; font-weight:700; padding:0.22rem 0.65rem;
                                     border-radius:999px; letter-spacing:0.04em; white-space:nowrap; margin-top:4px;
                                     background:<?= $glow['bg'] ?>; color:<?= $meta['color'] ?>;
                                     border:1px solid <?= $glow['border'] ?>;">
                            <?= e($meta['label']) ?>
                        </span>
                    </div>

                    <!-- Title + description -->
                    <div style="flex:1;">
                        <h3 style="font-size:0.97rem; font-weight:700; color:#eeebe1;
                                   margin:0 0 0.3rem; line-height:1.35;">
                            <?= e($rw['title']) ?>
                        </h3>
                        <?php if (!empty($rw['description'])): ?>
                        <p style="font-size:0.78rem; color:#6b6e77; line-height:1.55; margin:0;
                                  display:-webkit-box; -webkit-line-clamp:2;
                                  -webkit-box-orient:vertical; overflow:hidden;">
                            <?= e($rw['description']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer: cost + action -->
                <div style="padding:0.85rem 1.35rem 1.25rem;
                             border-top:1px solid rgba(255,255,255,0.07);
                             display:flex; align-items:center; justify-content:space-between; gap:0.75rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.35rem; margin-bottom:0.18rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="15" height="15"
                                 style="object-fit:contain; filter:drop-shadow(0 0 5px rgba(218,185,55,0.55));" alt="">
                            <span style="font-size:1.2rem; font-weight:800; color:#f8e769; letter-spacing:-0.02em;"><?= $cost ?></span>
                            <span style="font-size:0.67rem; color:#4a4e57; font-weight:500;">token</span>
                        </div>
                        <?php if ($stockLeft !== null): ?>
                        <span class="<?= $stockLeft <= 3 ? 'rw-stock-low' : '' ?>"
                              style="font-size:0.65rem; display:block;
                                     color:<?= $stockLeft <= 3 ? '#d2592a' : '#4a4e57' ?>;">
                            <?= $stockLeft <= 3 ? '⚠ ' : '' ?>เหลือ <?= $stockLeft ?> ชิ้น
                        </span>
                        <?php else: ?>
                        <span style="font-size:0.65rem; color:#6b6e77; display:block;">ไม่จำกัดจำนวน</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($canAfford): ?>
                    <button class="ch-btn-start"
                            style="padding:0.48rem 1rem; font-size:0.8rem; border-radius:10px;
                                   flex-shrink:0; white-space:nowrap;"
                            onclick='openRedeem(<?= (int)$rw['reward_id'] ?>, <?= json_encode($rw['title']) ?>, <?= $cost ?>)'>
                        แลกเลย
                    </button>
                    <?php else: ?>
                    <div style="display:flex; align-items:center; gap:0.3rem; flex-shrink:0;
                                 background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.07);
                                 border-radius:10px; padding:0.44rem 0.8rem;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                             stroke="rgba(107,110,119,0.65)" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span style="font-size:0.73rem; color:#6b6e77; font-weight:500;">Token ไม่พอ</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════
             MISSION LOGS — MY REDEMPTIONS
        ══════════════════════════════════════════════════ -->
        <?php if (!empty($myRedemptions)): ?>
        <section style="margin-bottom:3rem;">
            <div style="display:flex; align-items:center; gap:0.55rem; margin-bottom:1.2rem;">
                <div style="width:4px; height:24px; background:linear-gradient(180deg,rgba(218,185,55,0.55),rgba(218,185,55,0.20)); border-radius:999px; flex-shrink:0;"></div>
                <span style="font-size:0.98rem; font-weight:700; color:#eeebe1;">ประวัติการแลกรางวัล</span>
                <span style="font-size:0.65rem; font-weight:700; color:#091113;
                             background:#dab937; border-radius:999px;
                             padding:0.15rem 0.55rem;"><?= count($myRedemptions) ?></span>
            </div>

            <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                        border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">
                <!-- Table header -->
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                             gap:1rem; padding:0.7rem 1.25rem;
                             background:rgba(255,255,255,0.03);
                             border-bottom:1px solid rgba(255,255,255,0.07);
                             font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                             text-transform:uppercase; color:#6b6e77;">
                    <span>รางวัล</span>
                    <span>Token</span>
                    <span>วันที่</span>
                    <span>สถานะ</span>
                </div>

                <?php
                $dsDark = [
                    'pending'   => ['color' => '#fbbf24', 'bg' => 'rgba(245,158,11,0.10)',  'border' => 'rgba(245,158,11,0.25)'],
                    'fulfilled' => ['color' => '#518e5c', 'bg' => 'rgba(81,142,92,0.12)',   'border' => 'rgba(81,142,92,0.28)'],
                    'cancelled' => ['color' => '#d2592a', 'bg' => 'rgba(210,89,42,0.10)',   'border' => 'rgba(210,89,42,0.25)'],
                ];
                foreach ($myRedemptions as $rd):
                    $sm = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
                    $ds = $dsDark[$rd['status']] ?? $dsDark['pending'];
                ?>
                <div class="rw-hist-row" onclick="openRdDetail(<?= (int)$rd['redemption_id'] ?>)"
                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openRdDetail(<?= (int)$rd['redemption_id'] ?>);}"
                     tabindex="0" role="button" aria-label="ดูรายละเอียด: <?= e($rd['reward_title']) ?>"
                     style="display:flex; flex-direction:column; padding:0.85rem 1.25rem;
                            gap:0.65rem; cursor:pointer;">
                    <!-- Main row -->
                    <div style="display:grid; grid-template-columns:1fr auto auto auto;
                                 gap:1rem; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.6rem; min-width:0;">
                        <span style="font-size:1.3rem; flex-shrink:0; user-select:none; line-height:1;">
                            <?= e($rd['image_emoji'] ?: '🎁') ?>
                        </span>
                        <div style="min-width:0;">
                            <p style="font-size:0.85rem; font-weight:500; color:#eeebe1; margin:0;
                                       white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= e($rd['reward_title']) ?>
                            </p>
                            <?php if (!empty($rd['admin_note'])): ?>
                            <p style="font-size:0.72rem; color:#6b6e77; margin:0.08rem 0 0;">
                                <?= e($rd['admin_note']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.28rem; white-space:nowrap;">
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                             width="12" height="12" style="object-fit:contain; opacity:0.65;" alt="">
                        <span style="font-size:0.85rem; font-weight:700; color:#dab937;">
                            <?= (int)$rd['tokens_spent'] ?>
                        </span>
                    </div>
                    <span style="font-size:0.75rem; color:#6b6e77; white-space:nowrap;">
                        <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                    </span>
                    <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                                 border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                                 background:<?= $ds['bg'] ?>; color:<?= $ds['color'] ?>;
                                 border:1px solid <?= $ds['border'] ?>;">
                        <?= $sm['label'] ?>
                    </span>
                    </div><!-- /main row -->

                    <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
                    <!-- Coupon code row -->
                    <div style="border-top:1px dashed rgba(218,185,55,0.18); padding-top:0.6rem; margin-top:0.1rem;
                                display:flex; align-items:center; justify-content:space-between; gap:0.6rem; flex-wrap:wrap;">
                        <!-- toggle button (left) -->
                        <button onclick="event.stopPropagation(); rwToggleCoupon(<?= (int)$rd['redemption_id'] ?>, this)"
                                style="display:inline-flex; align-items:center; gap:0.38rem;
                                       background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.25);
                                       border-radius:8px; padding:0.3rem 0.7rem; cursor:pointer;
                                       font-size:0.7rem; font-weight:700; color:rgba(218,185,55,0.75);
                                       letter-spacing:0.06em; text-transform:uppercase;
                                       font-family:'Prompt',sans-serif;
                                       transition:background 0.15s, border-color 0.15s;"
                                onmouseover="this.style.background='rgba(218,185,55,0.14)'; this.style.borderColor='rgba(218,185,55,0.40)'"
                                onmouseout="this.style.background='rgba(218,185,55,0.08)'; this.style.borderColor='rgba(218,185,55,0.25)'"
                                title="แสดง/ซ่อนรหัสคูปอง">
                            <svg id="coupon-eye-<?= (int)$rd['redemption_id'] ?>"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                 width="12" height="12" style="flex-shrink:0;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span id="coupon-btn-label-<?= (int)$rd['redemption_id'] ?>">แสดงรหัสคูปอง</span>
                        </button>

                        <!-- coupon box (hidden by default, right side) -->
                        <div id="coupon-box-<?= (int)$rd['redemption_id'] ?>"
                             style="display:none; align-items:center; gap:0.6rem; flex-wrap:wrap;
                                    background:rgba(218,185,55,0.05); border:1px solid rgba(218,185,55,0.22);
                                    border-radius:10px; padding:0.32rem 0.85rem;">
                            <div style="display:flex; flex-direction:column; gap:0.06rem;">
                                <span style="font-size:0.55rem; font-weight:600; letter-spacing:0.08em;
                                             color:rgba(218,185,55,0.38); text-transform:uppercase; line-height:1;">
                                    อนุมัติโดย: <?= e($rd['processed_by_name'] ?? '—') ?>
                                </span>
                                <span id="coupon-code-<?= (int)$rd['redemption_id'] ?>"
                                      style="font-size:1rem; font-weight:800; color:#f8e769;
                                             letter-spacing:0.12em; font-family:monospace,'Prompt';
                                             user-select:all; word-break:break-all; line-height:1.3;">
                                    <?= e($rd['coupon_code']) ?>
                                </span>
                            </div>
                            <button onclick="rwCopyCoupon('<?= e(addslashes($rd['coupon_code'])) ?>',<?= (int)$rd['redemption_id'] ?>)"
                                    id="coupon-copy-<?= (int)$rd['redemption_id'] ?>"
                                    style="display:inline-flex; align-items:center; gap:0.25rem; flex-shrink:0;
                                           background:rgba(218,185,55,0.12); border:1px solid rgba(218,185,55,0.22);
                                           border-radius:6px; color:#dab937; cursor:pointer;
                                           font-size:0.68rem; font-weight:600;
                                           font-family:'Prompt',sans-serif;
                                           padding:0.22rem 0.55rem; line-height:1.4;
                                           transition:background 0.15s; white-space:nowrap;"
                                    onmouseover="this.style.background='rgba(218,185,55,0.22)'"
                                    onmouseout="this.style.background='rgba(218,185,55,0.12)'"
                                    title="คัดลอก">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                คัดลอก
                            </button>
                        </div>
                    </div>
                    <?php elseif ($rd['status'] === 'pending'): ?>
                    <!-- Lock hint + cancel — pending -->
                    <div style="border-top:1px dashed rgba(255,255,255,0.06); padding-top:0.5rem; margin-top:0.1rem;
                                display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <svg fill="none" stroke="#3a3e43" viewBox="0 0 24 24" width="12" height="12" style="flex-shrink:0;">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                            <span style="font-size:0.68rem; color:#6b6e77;">รหัสคูปองจะปรากฏหลัง HR ยืนยันมอบรางวัล</span>
                        </div>
                        <button onclick="event.stopPropagation(); rwCancelRedemption(<?= (int)$rd['redemption_id'] ?>, <?= json_encode(e($rd['reward_title'])) ?>, <?= (int)$rd['tokens_spent'] ?>)"
                                style="display:inline-flex; align-items:center; gap:0.3rem;
                                       background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.28);
                                       border-radius:7px; padding:0.25rem 0.65rem; cursor:pointer;
                                       font-size:0.68rem; font-weight:600; color:rgba(210,89,42,0.75);
                                       font-family:'Prompt',sans-serif; letter-spacing:0.03em;
                                       transition:background 0.15s, border-color 0.15s; white-space:nowrap;"
                                onmouseover="this.style.background='rgba(210,89,42,0.16)'; this.style.borderColor='rgba(210,89,42,0.50)'; this.style.color='#d2592a'"
                                onmouseout="this.style.background='rgba(210,89,42,0.08)'; this.style.borderColor='rgba(210,89,42,0.28)'; this.style.color='rgba(210,89,42,0.75)'"
                                title="ยกเลิกการแลกรางวัลนี้">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            ยกเลิก
                        </button>
                    </div>
                    <?php endif; ?>
                </div><!-- /rw-hist-row -->
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /rw-rewards-wrap -->

<!-- ══════════════════════════════════════════════════════════
     REDEEM CONFIRM MODAL
══════════════════════════════════════════════════════════ -->
<div id="redeem-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"
     onclick="if(event.target===this && !_redeemBusy) closeRedeem();">
    <div class="rw-modal-box">

        <!-- confirm state -->
        <div id="modal-confirm">

            <!-- Header bar -->
            <div style="background:linear-gradient(135deg,rgba(218,185,55,0.12),rgba(218,185,55,0.03));
                        border-bottom:1px solid rgba(218,185,55,0.16);
                        padding:1.15rem 1.5rem; display:flex; align-items:center; gap:0.75rem;">
                <div style="width:34px;height:34px;border-radius:50%;
                             background:rgba(218,185,55,0.15); border:1px solid rgba(218,185,55,0.38);
                             display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="18" height="18" style="object-fit:contain;" alt="">
                </div>
                <h2 id="modal-title" style="font-size:0.97rem; font-weight:700; color:#eeebe1; margin:0;">
                    ยืนยันการแลกรางวัล
                </h2>
                <button onclick="closeRedeem()"
                        style="margin-left:auto; background:none; border:none; cursor:pointer;
                               color:#4a4e57; padding:4px; border-radius:6px; line-height:0;
                               transition:color 0.15s;"
                        onmouseover="this.style.color='#eeebe1'"
                        onmouseout="this.style.color='#4a4e57'"
                        aria-label="ปิด">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div style="padding:1.4rem 1.65rem;">

                <!-- Reward display -->
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.4rem;
                             background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
                             border-radius:14px; padding:0.9rem 1.15rem;">
                    <span id="modal-emoji" style="font-size:2.4rem; user-select:none; line-height:1;"></span>
                    <div>
                        <p style="font-size:0.60rem; letter-spacing:0.12em; text-transform:uppercase;
                                  color:#6b6e77; margin:0 0 0.18rem; font-weight:700;">รางวัลที่เลือก</p>
                        <p id="modal-reward-name"
                           style="font-size:0.97rem; font-weight:700; color:#eeebe1; margin:0;"></p>
                    </div>
                </div>

                <!-- Cost breakdown -->
                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                             border-radius:12px; padding:0.9rem 1.1rem; margin-bottom:1.35rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.55rem;">
                        <span style="font-size:0.82rem; color:#6b6e77;">ราคา</span>
                        <div style="display:flex; align-items:center; gap:0.28rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="12" height="12" style="object-fit:contain;" alt="">
                            <span id="modal-cost" style="font-size:0.92rem; font-weight:700; color:#d2592a;"></span>
                            <span style="font-size:0.70rem; color:#4a4e57;">token</span>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.55rem;">
                        <span style="font-size:0.82rem; color:#6b6e77;">ยอดปัจจุบัน</span>
                        <div style="display:flex; align-items:center; gap:0.28rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="12" height="12" style="object-fit:contain;" alt="">
                            <span id="modal-balance-before"
                                  style="font-size:0.92rem; font-weight:600; color:#eeebe1;"></span>
                        </div>
                    </div>
                    <div style="height:1px; background:rgba(255,255,255,0.07); margin-bottom:0.55rem;"></div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; font-weight:600; color:#eeebe1;">ยอดหลังแลก</span>
                        <div style="display:flex; align-items:center; gap:0.28rem;">
                            <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                                 width="12" height="12"
                                 style="object-fit:contain; filter:drop-shadow(0 0 4px rgba(218,185,55,0.55));" alt="">
                            <span id="modal-balance-after"
                                  style="font-size:1.05rem; font-weight:800; color:#f8e769;"></span>
                        </div>
                    </div>
                </div>

                <!-- Notice -->
                <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.20);
                             border-radius:10px; padding:0.7rem 0.95rem; margin-bottom:1.35rem;
                             display:flex; gap:0.55rem; align-items:flex-start;">
                    <span style="flex-shrink:0; font-size:0.85rem; opacity:0.75; margin-top:1px;">ℹ️</span>
                    <p style="font-size:0.77rem; color:#d4a52a; margin:0; line-height:1.55;">
                        หลังยืนยัน HR จะติดต่อกลับเพื่อดำเนินการมอบรางวัลให้คุณ
                    </p>
                </div>

                <!-- Error message -->
                <div id="modal-error"
                     style="display:none; background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28);
                            border-radius:10px; padding:0.6rem 0.95rem; margin-bottom:1rem;
                            font-size:0.82rem; color:#d2592a;"></div>

                <!-- Buttons -->
                <div style="display:flex; gap:0.65rem;">
                    <button onclick="closeRedeem()"
                            id="modal-cancel-btn"
                            style="flex:1; padding:0.62rem 1rem; font-size:0.85rem; font-weight:600;
                                   border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                                   background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                                   color:#eeebe1; transition:background 0.18s;"
                            onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                        ยกเลิก
                    </button>
                    <button onclick="submitRedeem()"
                            class="ch-btn-start"
                            style="flex:1.5; padding:0.62rem 1rem; font-size:0.85rem; border-radius:10px;
                                   display:flex; align-items:center; justify-content:center; gap:0.5rem;"
                            id="modal-confirm-btn">
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                             width="15" height="15" style="object-fit:contain;" alt="">
                        ยืนยันแลกรางวัล
                    </button>
                </div>
            </div>
        </div>

        <!-- success state -->
        <div id="modal-success" style="display:none; padding:2.75rem 1.75rem; text-align:center;">
            <div class="success-pop" style="font-size:4rem; margin-bottom:1rem; line-height:1;">🎉</div>
            <h3 style="font-size:1.2rem; font-weight:700; color:#eeebe1; margin:0 0 0.45rem;">
                แลกรางวัลสำเร็จ!
            </h3>
            <p style="font-size:0.85rem; color:#6b6e77; line-height:1.6; margin:0 0 0.2rem;">
                HR จะติดต่อกลับเพื่อดำเนินการมอบรางวัลให้คุณเร็วๆ นี้
            </p>
            <p id="success-balance-text" style="font-size:0.85rem; color:#4a4e57; margin:0 0 2rem;"></p>
            <button onclick="closeRedeem()" class="ch-btn-start"
                    style="width:100%; padding:0.62rem 1rem;
                           display:flex; align-items:center; justify-content:center;">
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
    window._redeemBusy   = false;

    // ── Category filter ────────────────────────────────────
    window.filterCat = function (btn, cat) {
        document.querySelectorAll('.rw-cat-pill').forEach(function (p) {
            p.classList.remove('active');
        });
        btn.classList.add('active');
        document.querySelectorAll('.rw-reward-card').forEach(function (card) {
            var show = (cat === 'all' || card.dataset.category === cat);
            card.classList.toggle('rw-hidden', !show);
        });
    };

    // ── Open confirm modal ─────────────────────────────────
    window.openRedeem = function (id, title, cost) {
        _currentRewardId = id;
        _currentCost     = cost;

        var balance = parseInt(
            document.getElementById('hdr-balance').textContent.replace(/,/g, ''), 10
        ) || <?php echo (int)$wallet['balance']; ?>;

        /* Read emoji from data attribute */
        var emoji = '🎁';
        document.querySelectorAll('.rw-reward-card').forEach(function (card) {
            var btn = card.querySelector('button[onclick*="openRedeem(' + id + ',"]');
            if (btn && card.dataset.emoji) emoji = card.dataset.emoji;
        });

        document.getElementById('modal-emoji').textContent          = emoji;
        document.getElementById('modal-reward-name').textContent    = title;
        document.getElementById('modal-cost').textContent           = cost;
        document.getElementById('modal-balance-before').textContent = balance;
        document.getElementById('modal-balance-after').textContent  = (balance - cost);
        document.getElementById('modal-error').style.display        = 'none';
        document.getElementById('modal-confirm').style.display      = '';
        document.getElementById('modal-success').style.display      = 'none';

        var confirmBtn = document.getElementById('modal-confirm-btn');
        confirmBtn.disabled  = false;
        confirmBtn.innerHTML = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="15" height="15" style="object-fit:contain;margin-right:5px" alt=""> ยืนยันแลกรางวัล';

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
        btn.disabled    = true;
        btn.textContent = 'กำลังดำเนินการ…';

        document.getElementById('modal-error').style.display    = 'none';
        document.getElementById('modal-cancel-btn').disabled    = true;

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
                var newBal = data.new_balance;
                document.getElementById('hdr-balance').textContent = newBal.toLocaleString('th-TH');
                var navBal = document.getElementById('nav-balance');
                if (navBal) navBal.textContent = newBal.toLocaleString('th-TH');

                document.getElementById('success-balance-text').textContent =
                    'ยอด Token คงเหลือ: ' + newBal.toLocaleString('th-TH') + ' token';

                document.getElementById('modal-confirm').style.display = 'none';
                document.getElementById('modal-success').style.display = '';

                document.querySelectorAll('.rw-reward-card').forEach(function (card) {
                    var anyBtn = card.querySelector('button[onclick*="openRedeem(' + _currentRewardId + ',"]');
                    if (anyBtn) { anyBtn.disabled = true; anyBtn.textContent = 'แลกแล้ว'; }
                });

                window.__reloadOnClose = true;
            } else {
                var errEl = document.getElementById('modal-error');
                errEl.textContent   = data.message || 'เกิดข้อผิดพลาด';
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="15" height="15" style="object-fit:contain;margin-right:5px" alt=""> ยืนยันแลกรางวัล';
                document.getElementById('modal-cancel-btn').disabled = false;
            }
        })
        .catch(function () {
            _redeemBusy = false;
            window._redeemBusy = false;
            var errEl = document.getElementById('modal-error');
            errEl.textContent   = 'การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
            errEl.style.display = 'block';
            var btn = document.getElementById('modal-confirm-btn');
            btn.disabled  = false;
            btn.innerHTML = '<img src="<?php echo BASE_URL; ?>/assets/images/token.png" width="15" height="15" style="object-fit:contain;margin-right:5px" alt=""> ยืนยันแลกรางวัล';
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

/* ── Coupon toggle ────────────────────────────────────────── */
window.rwToggleCoupon = function (id, btn) {
    var box   = document.getElementById('coupon-box-' + id);
    var label = document.getElementById('coupon-btn-label-' + id);
    var eye   = document.getElementById('coupon-eye-' + id);
    if (!box) return;
    var visible = box.style.display === 'flex';
    if (visible) {
        box.style.display   = 'none';
        label.textContent   = 'แสดงรหัสคูปอง';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    } else {
        box.style.display   = 'flex';
        label.textContent   = 'ซ่อนรหัสคูปอง';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    }
};

window.rwCancelRedemption = function (rdId, title, cost) {
    if (!confirm('ยกเลิกการแลก "' + title + '"?\nToken ' + cost + ' จะถูกคืนให้คุณทันที')) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')
                   ? document.querySelector('meta[name="csrf-token"]').content : '';

    fetch(window.location.href, {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : new URLSearchParams({
            action        : 'cancel_redemption',
            redemption_id : rdId,
            csrf_token    : csrf,
        }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            var newBal = data.new_balance;
            var balEl  = document.getElementById('hdr-balance');
            if (balEl) balEl.textContent = newBal.toLocaleString('th-TH');
            var navBal = document.getElementById('nav-balance');
            if (navBal) navBal.textContent = newBal.toLocaleString('th-TH');
            location.reload();
        } else {
            alert(data.message || 'เกิดข้อผิดพลาด');
        }
    })
    .catch(function () { alert('การเชื่อมต่อขัดข้อง กรุณาลองใหม่'); });
};

window.rwCopyCoupon = function (code, id) {
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.getElementById('coupon-copy-' + id);
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> คัดลอกแล้ว';
        btn.style.color = '#7ec98a';
        btn.style.borderColor = 'rgba(81,142,92,0.40)';
        setTimeout(function () {
            btn.innerHTML = orig;
            btn.style.color = '#dab937';
            btn.style.borderColor = 'rgba(218,185,55,0.22)';
        }, 2000);
    }).catch(function () {
        var el = document.getElementById('coupon-code-' + id);
        if (el) { var r = document.createRange(); r.selectNode(el); window.getSelection().removeAllRanges(); window.getSelection().addRange(r); }
    });
};
</script>

<?php
// ── Collect redemption detail data for JS modal ──
$rdDetailData = [];
foreach ($myRedemptions as $_rd) {
    $_rid = (int)$_rd['redemption_id'];
    $rdDetailData[$_rid] = [
        'title'  => $_rd['reward_title'],
        'emoji'  => $_rd['image_emoji'] ?: '🎁',
        'tokens' => (int)$_rd['tokens_spent'],
        'status' => $_rd['status'],
        'reqAt'  => date('d/m/Y H:i', strtotime((string)$_rd['redeemed_at'])),
        'procAt' => $_rd['processed_at']
                    ? date('d/m/Y H:i', strtotime((string)$_rd['processed_at']))
                    : null,
        'note'   => (string)($_rd['admin_note']        ?? ''),
        'procBy' => (string)($_rd['processed_by_name'] ?? ''),
        'coupon' => ($_rd['status'] === 'fulfilled') ? (string)($_rd['coupon_code'] ?? '') : '',
    ];
}
?>
<script>var _rdData = <?= json_encode($rdDetailData, JSON_UNESCAPED_UNICODE) ?>;</script>

<style>
@keyframes _rdCardIn {
    from { opacity:0; transform:perspective(700px) scale(0.88) translateY(28px); }
    to   { opacity:1; transform:perspective(700px) scale(1)    translateY(0); }
}
@keyframes _rdCardOut { from{opacity:1;transform:scale(1) translateY(0)} to{opacity:0;transform:scale(0.86) translateY(22px)} }
@keyframes _rdFadeIn  { from{opacity:0} to{opacity:1} }
@keyframes _rdFadeOut { from{opacity:1} to{opacity:0} }
.rd-ov-in    { animation:_rdFadeIn  230ms ease                             forwards; }
.rd-ov-out   { animation:_rdFadeOut 155ms ease                             forwards; }
.rd-card-in  { animation:_rdCardIn  420ms cubic-bezier(0.22,1,0.36,1)  forwards; }
.rd-card-out { animation:_rdCardOut 155ms ease-in                          forwards; }
</style>

<!-- ── Redemption Detail Modal ── -->
<div id="rd-detail-modal"
     style="display:none; position:fixed; inset:0; z-index:9500;
            background:rgba(0,0,0,0.80); backdrop-filter:blur(7px);
            align-items:center; justify-content:center; padding:1rem;"
     onclick="if(event.target===this)closeRdDetail()">

    <div id="rd-detail-card"
         style="background:#0f1416; border:1px solid rgba(255,255,255,0.10); border-radius:20px;
                max-width:430px; width:100%; max-height:90vh; overflow-y:auto;
                box-shadow:0 24px 60px rgba(0,0,0,0.72);">

        <!-- Header -->
        <div style="padding:1.1rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.07);
                    display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:0.55rem;">
                <span style="font-size:0.95rem;">🧾</span>
                <span style="font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
                             text-transform:uppercase; color:rgba(218,185,55,0.85);">รายละเอียดคำขอแลกรางวัล</span>
            </div>
            <button onclick="closeRdDetail()"
                    style="width:28px; height:28px; border-radius:50%;
                           background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
                           color:#6b6e77; cursor:pointer; font-size:0.85rem; line-height:1;
                           display:flex; align-items:center; justify-content:center;
                           font-family:'Prompt',sans-serif;">✕</button>
        </div>

        <!-- Body -->
        <div style="padding:1.35rem 1.4rem; display:flex; flex-direction:column; gap:0.9rem;">

            <!-- Reward card -->
            <div style="display:flex; align-items:center; gap:1rem;
                        background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                        border-radius:14px; padding:0.95rem 1.1rem;">
                <span id="rdd-emoji" style="font-size:2.5rem; flex-shrink:0; line-height:1; user-select:none;"></span>
                <div style="flex:1; min-width:0;">
                    <p id="rdd-title" style="font-size:0.97rem; font-weight:700; color:#eeebe1;
                                              margin:0 0 0.4rem; line-height:1.3;"></p>
                    <span id="rdd-status-badge"
                          style="font-size:0.63rem; font-weight:700; padding:0.2rem 0.65rem;
                                 border-radius:999px; letter-spacing:0.04em; white-space:nowrap;"></span>
                </div>
            </div>

            <!-- Info grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem;">
                <div style="background:rgba(218,185,55,0.07); border:1px solid rgba(218,185,55,0.18);
                            border-radius:12px; padding:0.7rem 0.9rem;">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">Token ที่ใช้</p>
                    <p id="rdd-tokens" style="font-size:1.15rem; font-weight:800; color:#dab937; margin:0;"></p>
                </div>
                <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.07);
                            border-radius:12px; padding:0.7rem 0.9rem;">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่ขอแลก</p>
                    <p id="rdd-req-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0; line-height:1.4;"></p>
                </div>
                <div id="rdd-proc-row" style="display:none; grid-column:1/-1;
                            background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.07);
                            border-radius:12px; padding:0.7rem 0.9rem;">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่ดำเนินการ</p>
                    <p id="rdd-proc-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0;"></p>
                    <p id="rdd-proc-by" style="font-size:0.70rem; color:#6b6e77; margin:0.2rem 0 0; display:none;"></p>
                </div>
            </div>

            <!-- Admin note -->
            <div id="rdd-note-wrap" style="display:none;
                        background:rgba(79,139,152,0.07); border:1px solid rgba(79,139,152,0.22);
                        border-radius:12px; padding:0.75rem 0.95rem;">
                <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#4f8b98; margin:0 0 0.3rem;">หมายเหตุจาก HR</p>
                <p id="rdd-note" style="font-size:0.83rem; color:#eeebe1; margin:0; line-height:1.55;"></p>
            </div>

            <!-- Coupon reveal (fulfilled + has coupon) -->
            <div id="rdd-coupon-section" style="display:none;">
                <button onclick="rdToggleCoupon()"
                        style="display:inline-flex; align-items:center; gap:0.4rem; width:100%;
                               justify-content:center; background:rgba(218,185,55,0.08);
                               border:1px solid rgba(218,185,55,0.25); border-radius:10px;
                               padding:0.5rem 1rem; cursor:pointer; font-size:0.78rem; font-weight:700;
                               color:rgba(218,185,55,0.80); font-family:'Prompt',sans-serif; transition:background 0.15s;">
                    <span id="rdd-coupon-label">👁 แสดงรหัสคูปอง</span>
                </button>
                <div id="rdd-coupon-box" style="display:none; margin-top:0.5rem;
                            background:rgba(218,185,55,0.06); border:1px solid rgba(218,185,55,0.25);
                            border-radius:10px; padding:0.75rem 1rem;
                            flex-direction:column; gap:0.35rem;">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:rgba(218,185,55,0.45); margin:0;">รหัสคูปอง</p>
                    <div style="display:flex; align-items:center; gap:0.65rem;">
                        <p id="rdd-coupon-code"
                           style="font-size:1.15rem; font-weight:800; color:#f8e769;
                                  letter-spacing:0.12em; font-family:monospace,'Prompt';
                                  user-select:all; word-break:break-all; margin:0; flex:1;"></p>
                        <button onclick="rdCopyCoupon()"
                                id="rdd-coupon-copy"
                                style="display:inline-flex; align-items:center; gap:0.25rem; flex-shrink:0;
                                       background:rgba(218,185,55,0.12); border:1px solid rgba(218,185,55,0.25);
                                       border-radius:7px; color:#dab937; cursor:pointer;
                                       font-size:0.72rem; font-weight:600; font-family:'Prompt',sans-serif;
                                       padding:0.3rem 0.65rem; transition:background 0.15s; white-space:nowrap;">📋 คัดลอก</button>
                    </div>
                </div>
            </div>

            <!-- Cancel section (pending only) -->
            <div id="rdd-cancel-section" style="display:none;">
                <div style="border-top:1px solid rgba(255,255,255,0.06); padding-top:0.85rem;">
                    <p style="font-size:0.68rem; color:#6b6e77; text-align:center; margin:0 0 0.6rem;">
                        🔒 Token จะถูกคืนให้ทันที หลังยืนยันยกเลิก
                    </p>
                    <button id="rdd-cancel-btn" onclick="rdDoCancel()"
                            style="display:flex; align-items:center; justify-content:center; gap:0.5rem;
                                   width:100%; padding:0.6rem 1rem; border-radius:11px; cursor:pointer;
                                   background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.30);
                                   color:rgba(210,89,42,0.85); font-size:0.83rem; font-weight:600;
                                   font-family:'Prompt',sans-serif; transition:background 0.15s, color 0.15s; line-height:1.4;"></button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
var _rdCurrentId = 0, _rdCurrentTokens = 0;

function openRdDetail(rdId) {
    var d = _rdData[rdId];
    if (!d) return;
    _rdCurrentId     = rdId;
    _rdCurrentTokens = d.tokens;

    document.getElementById('rdd-emoji').textContent  = d.emoji;
    document.getElementById('rdd-title').textContent  = d.title;
    document.getElementById('rdd-tokens').textContent = d.tokens.toLocaleString() + ' token';
    document.getElementById('rdd-req-at').textContent = d.reqAt;

    var statusMap = {
        pending:   { label:'รอดำเนินการ', bg:'rgba(245,158,11,0.12)', color:'#fbbf24', border:'rgba(245,158,11,0.32)' },
        fulfilled: { label:'มอบแล้ว',    bg:'rgba(81,142,92,0.12)',  color:'#6fcf80', border:'rgba(81,142,92,0.32)'  },
        cancelled: { label:'ยกเลิก',          bg:'rgba(210,89,42,0.12)',  color:'#e8805a', border:'rgba(210,89,42,0.32)'  },
    };
    var sm = statusMap[d.status] || statusMap.pending;
    var badge = document.getElementById('rdd-status-badge');
    badge.textContent       = sm.label;
    badge.style.background  = sm.bg;
    badge.style.color       = sm.color;
    badge.style.border      = '1px solid ' + sm.border;

    var procRow = document.getElementById('rdd-proc-row');
    if (d.procAt) {
        document.getElementById('rdd-proc-at').textContent = d.procAt;
        var procByEl = document.getElementById('rdd-proc-by');
        if (d.procBy) { procByEl.textContent = 'โดย ' + d.procBy; procByEl.style.display = 'block'; }
        else           { procByEl.style.display = 'none'; }
        procRow.style.display = 'block';
    } else {
        procRow.style.display = 'none';
    }

    var noteWrap = document.getElementById('rdd-note-wrap');
    if (d.note) { document.getElementById('rdd-note').textContent = d.note; noteWrap.style.display = 'block'; }
    else        { noteWrap.style.display = 'none'; }

    var couponSec = document.getElementById('rdd-coupon-section');
    if (d.coupon) {
        document.getElementById('rdd-coupon-code').textContent   = d.coupon;
        document.getElementById('rdd-coupon-box').style.display  = 'none';
        document.getElementById('rdd-coupon-label').textContent  = '👁 แสดงรหัสคูปอง';
        couponSec.style.display = 'block';
    } else {
        couponSec.style.display = 'none';
    }

    var cancelSec = document.getElementById('rdd-cancel-section');
    if (d.status === 'pending') {
        var cb = document.getElementById('rdd-cancel-btn');
        cb.disabled  = false;
        cb.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ยกเลิกการแลก (คืน ' + d.tokens.toLocaleString() + ' Token)';
        cancelSec.style.display = 'block';
    } else {
        cancelSec.style.display = 'none';
    }

    var overlay = document.getElementById('rd-detail-modal');
    var card    = document.getElementById('rd-detail-card');
    overlay.classList.remove('rd-ov-out');  card.classList.remove('rd-card-out');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    void card.offsetWidth;
    overlay.classList.add('rd-ov-in');
    card.classList.add('rd-card-in');
}

function closeRdDetail() {
    var overlay = document.getElementById('rd-detail-modal');
    if (overlay.style.display === 'none') return;
    var card = document.getElementById('rd-detail-card');
    overlay.classList.remove('rd-ov-in');  card.classList.remove('rd-card-in');
    overlay.classList.add('rd-ov-out');    card.classList.add('rd-card-out');
    setTimeout(function() {
        overlay.style.display = 'none';
        overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
        document.body.style.overflow = '';
    }, 160);
}

function rdToggleCoupon() {
    var box = document.getElementById('rdd-coupon-box');
    var lbl = document.getElementById('rdd-coupon-label');
    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'flex'; lbl.textContent = '🙈 ซ่อนรหัสคูปอง';
    } else {
        box.style.display = 'none'; lbl.textContent = '👁 แสดงรหัสคูปอง';
    }
}

function rdCopyCoupon() {
    var code = document.getElementById('rdd-coupon-code').textContent.trim();
    var btn  = document.getElementById('rdd-coupon-copy');
    navigator.clipboard.writeText(code).then(function() {
        var orig = btn.textContent;
        btn.textContent = '✓ คัดลอกแล้ว';
        btn.style.color = '#7ec98a';
        setTimeout(function() { btn.textContent = orig; btn.style.color = '#dab937'; }, 1800);
    });
}

function rdDoCancel() {
    var cb = document.getElementById('rdd-cancel-btn');
    cb.disabled     = true;
    cb.textContent  = 'กำลังดำเนินการ…';
    var csrf = document.querySelector('meta[name="csrf-token"]')
               ? document.querySelector('meta[name="csrf-token"]').content : '';
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action:'cancel_redemption', redemption_id:_rdCurrentId, csrf_token:csrf }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var balEl  = document.getElementById('hdr-balance');
            if (balEl)  balEl.textContent = data.new_balance.toLocaleString('th-TH');
            var navBal = document.getElementById('nav-balance');
            if (navBal) navBal.textContent = data.new_balance.toLocaleString('th-TH');
            closeRdDetail();
            setTimeout(function() { location.reload(); }, 165);
        } else {
            cb.disabled  = false;
            cb.innerHTML = '❌ ' + (data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    })
    .catch(function() {
        cb.disabled  = false;
        cb.innerHTML = '❌ การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeRdDetail(); closePendingList(); }
});
</script>

<!-- ── Pending List Modal ── -->
<div id="rd-pending-modal"
     style="display:none; position:fixed; inset:0; z-index:9600;
            background:rgba(0,0,0,0.82); backdrop-filter:blur(7px);
            align-items:center; justify-content:center; padding:1rem;"
     onclick="if(event.target===this)closePendingList()">
    <div id="rd-pending-card"
         style="background:#0f1416; border:1px solid rgba(245,158,11,0.22); border-radius:20px;
                max-width:420px; width:100%; max-height:82vh; overflow-y:auto;
                box-shadow:0 24px 60px rgba(0,0,0,0.72);">
        <!-- Header -->
        <div style="padding:1.1rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.07);
                    display:flex; align-items:center; justify-content:space-between; position:sticky; top:0;
                    background:#0f1416; z-index:1; border-radius:20px 20px 0 0;">
            <div style="display:flex; align-items:center; gap:0.55rem;">
                <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;
                              flex-shrink:0; animation:coin-bounce 1.5s ease-in-out infinite;"></span>
                <span style="font-size:0.70rem; font-weight:700; letter-spacing:0.08em;
                             text-transform:uppercase; color:#fbbf24;">รอดำเนินการ</span>
                <span id="pending-count-badge"
                      style="font-size:0.62rem; font-weight:700; color:#091113;
                             background:#f59e0b; border-radius:999px; padding:0.10rem 0.48rem;"></span>
            </div>
            <button onclick="closePendingList()"
                    style="width:28px; height:28px; border-radius:50%;
                           background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
                           color:#6b6e77; cursor:pointer; font-size:0.85rem; line-height:1;
                           display:flex; align-items:center; justify-content:center;
                           font-family:'Prompt',sans-serif;">✕</button>
        </div>
        <!-- List -->
        <div id="pending-list-body" style="padding:0.75rem 0;"></div>
    </div>
</div>

<script>
function openPendingList() {
    var pending = Object.entries(_rdData).filter(function(e) { return e[1].status === 'pending'; });
    var body = document.getElementById('pending-list-body');
    document.getElementById('pending-count-badge').textContent = pending.length;
    body.innerHTML = '';
    if (pending.length === 0) {
        body.innerHTML = '<p style="text-align:center; font-size:0.83rem; color:#6b6e77; padding:1.5rem;">ไม่มีรายการรอดำเนินการ</p>';
    } else {
        pending.forEach(function(entry, idx) {
            var rdId = entry[0], d = entry[1];
            var row = document.createElement('div');
            row.style.cssText = [
                'display:flex; align-items:center; gap:0.85rem;',
                'padding:0.75rem 1.25rem; cursor:pointer;',
                'border-bottom:1px solid rgba(255,255,255,' + (idx < pending.length-1 ? '0.05' : '0') + ');',
                'transition:background 0.14s;',
            ].join('');
            row.onmouseover = function() { this.style.background='rgba(245,158,11,0.06)'; };
            row.onmouseout  = function() { this.style.background=''; };
            row.onclick = function() {
                closePendingList();
                setTimeout(function() { openRdDetail(parseInt(rdId)); }, 140);
            };
            row.innerHTML = [
                '<span style="font-size:1.5rem; flex-shrink:0; line-height:1; user-select:none;">' + d.emoji + '</span>',
                '<div style="flex:1; min-width:0;">',
                '  <p style="font-size:0.85rem; font-weight:600; color:#eeebe1; margin:0 0 0.2rem;',
                '            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + d.title + '</p>',
                '  <p style="font-size:0.72rem; color:#6b6e77; margin:0;">ขอวันที่ ' + d.reqAt + '</p>',
                '</div>',
                '<div style="display:flex; align-items:center; gap:0.25rem; flex-shrink:0;">',
                '  <span style="font-size:0.88rem; font-weight:800; color:#dab937;">' + d.tokens.toLocaleString() + '</span>',
                '  <span style="font-size:0.62rem; color:#6b6e77;">token</span>',
                '</div>',
                '<svg fill="none" stroke="#6b6e77" viewBox="0 0 24 24" width="14" height="14" style="flex-shrink:0;">',
                '  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>',
                '</svg>',
            ].join('');
            body.appendChild(row);
        });
    }

    var overlay = document.getElementById('rd-pending-modal');
    var card    = document.getElementById('rd-pending-card');
    overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    void card.offsetWidth;
    overlay.classList.add('rd-ov-in');
    card.classList.add('rd-card-in');
}

function closePendingList() {
    var overlay = document.getElementById('rd-pending-modal');
    if (!overlay || overlay.style.display === 'none') return;
    var card = document.getElementById('rd-pending-card');
    overlay.classList.remove('rd-ov-in');  card.classList.remove('rd-card-in');
    overlay.classList.add('rd-ov-out');    card.classList.add('rd-card-out');
    setTimeout(function() {
        overlay.style.display = 'none';
        overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
        document.body.style.overflow = '';
    }, 160);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
