<?php
/**
 * pages/rewards.php
 * Token Shop — employees redeem earned tokens for rewards
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$employeeId = (int)$_SESSION['employee_id'];
$pdo        = getDB();

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

$catTone = [
    'voucher' => ['icon_bg' => 'rgba(47,78,157,0.30)',  'icon_border' => 'rgba(123,159,245,0.52)', 'icon_color' => '#9db4f7'],
    'leave'   => ['icon_bg' => 'rgba(81,142,92,0.30)',  'icon_border' => 'rgba(126,201,138,0.52)', 'icon_color' => '#8fdaa0'],
    'merch'   => ['icon_bg' => 'rgba(98,48,122,0.32)',  'icon_border' => 'rgba(196,157,224,0.54)', 'icon_color' => '#d3ace8'],
    'perk'    => ['icon_bg' => 'rgba(201,168,48,0.30)', 'icon_border' => 'rgba(248,231,105,0.52)', 'icon_color' => '#f8e769'],
    'general' => ['icon_bg' => 'rgba(107,110,119,0.32)','icon_border' => 'rgba(165,169,181,0.52)', 'icon_color' => '#c9ccd4'],
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

<div class="rw-rewards-wrap rw-u001">

    <!-- Aurora blobs -->
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="rw-u002"></div>
        <div class="rw-u003"></div>
        <div class="rw-u004"></div>
    </div>

    <div class="jp-page-inner jp-page-inner--flush-top">

        <!-- ══ HEADER ══ -->
        <div class="rw-u005">
            <p class="rw-u006">
                ⬡ &nbsp;OPERATIVE EXCHANGE
            </p>
            <div class="rw-u007">
                <div>
                    <h1 class="rw-u008">ร้านแลกรางวัล</h1>
                    <p class="rw-u009">
                        ใช้ Token สะสมแลกรับรางวัลและสิทธิประโยชน์พิเศษจาก JOURNAL
                    </p>
                </div>
                <!-- Balance pill -->
                <div class="rw-u010">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" width="22" height="22"
                         class="rw-token-img-md" alt="">
                    <div>
                        <div class="rw-u011">
                            ยอดคงเหลือ
                        </div>
                        <div class="rw-u012" id="hdr-balance"
                            >
                            <?= formatTokens((int)$wallet['balance']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="rw-u013">
                <div class="rw-u014">
                    <div class="rw-u015">
                        <?= formatTokens((int)$wallet['total_spent']) ?>
                    </div>
                    <div class="rw-u016">
                        Token ที่ใช้ไป
                    </div>
                </div>
                <div class="rw-stat-div rw-u017"></div>
                <div class="rw-u014">
                    <div class="rw-u015">
                        <?= count($myRedemptions) ?>
                    </div>
                    <div class="rw-u016">
                        รายการแลกแล้ว
                    </div>
                </div>
                <?php if ($myPending > 0): ?>
                <div class="rw-stat-div rw-u017"></div>
                <button data-action="open-pending-list"
                       
                    class="rw-btn-pending-open rw-u018">
                    <span class="rw-u019"></span>
                    <span class="rw-u020">
                        <?= $myPending ?> รายการรอดำเนินการ
                    </span>
                    <svg class="rw-u021" fill="none" stroke="#fbbf24" viewBox="0 0 24 24" width="12" height="12">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </div><!-- /header -->

        <?php if ($dataError): ?>
        <div class="jp-alert-error jp-alert-error--soft">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- ══ FILTER ROW ══ -->
        <div class="rw-u022">
            <div class="rw-u023">
                <div class="rw-u024"></div>
                <span class="rw-u025">สินค้า</span>
                <span class="rw-u026"><?= count($rewards) ?></span>
            </div>
            <button class="rw-cat-pill active" data-cat="all" data-action="filter-cat">ทั้งหมด</button>
            <?php foreach ($activeCategories as $cat):
                $meta  = $catMeta[$cat] ?? $catMeta['general'];
                $count = count(array_filter($rewards, fn($r) => $r['category'] === $cat));
            ?>
            <button class="rw-cat-pill" data-cat="<?= e($cat) ?>" data-action="filter-cat">
                <?= e($meta['label']) ?> <span class="rw-u027">(<?= $count ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ══ REWARDS GRID ══ -->
        <?php if (empty($rewards)): ?>
        <div class="rw-u028">
            <p class="rw-u029">Vault ว่างอยู่ในขณะนี้</p>
            <p class="rw-u030">ติดตามรางวัลใหม่ได้เร็วๆ นี้</p>
        </div>
        <?php else: ?>
        <div class="rw-u031" id="rewards-grid"
            >
            <?php
            $myBalance = (int)$wallet['balance'];
            $bannerCls = [
                'voucher' => 'rw-banner-voucher',
                'leave'   => 'rw-banner-leave',
                'merch'   => 'rw-banner-merch',
                'perk'    => 'rw-banner-perk',
                'general' => 'rw-banner-general',
            ];
            $bannerToneCls = [
                'voucher' => 'rw-tone-voucher',
                'leave'   => 'rw-tone-leave',
                'merch'   => 'rw-tone-merch',
                'perk'    => 'rw-tone-perk',
                'general' => 'rw-tone-general',
            ];
            foreach ($rewards as $rw):
                $cat       = (string)$rw['category'];
                $meta      = $catMeta[$cat]  ?? $catMeta['general'];
                $tone      = $catTone[$cat]  ?? $catTone['general'];
                $cost      = (int)$rw['token_cost'];
                $canAfford = $myBalance >= $cost;
                $stockLeft = $rw['stock'] === null ? null : (int)$rw['stock'];
                $banClass  = $bannerCls[$cat]      ?? 'rw-banner-general';
                $toneClass = $bannerToneCls[$cat]    ?? $bannerToneCls['general'];
                $needed    = $cost - $myBalance;
                $iconBig   = str_replace(['width="18"', 'height="18"'], ['width="22"', 'height="22"'],
                                         rewardCategoryIconSvg($cat));
            ?>
            <div class="rw-reward-card <?= $canAfford ? '' : 'rw-no-balance' ?>"
                 data-category="<?= e($cat) ?>"
                 data-reward-id="<?= (int)$rw['reward_id'] ?>">

                <!-- Banner -->
                <div class="rw-banner <?= $banClass ?>">
                    <div class="rw-banner-icon <?= e($toneClass) ?>">
                        <?= $iconBig ?>
                    </div>
                    <span class="rw-banner-tag <?= e($toneClass) ?>">
                        <?= e($meta['label']) ?>
                    </span>
                </div>

                <!-- Body -->
                <div class="rw-card-body">
                    <h3 class="rw-card-title"><?= e($rw['title']) ?></h3>
                    <?php if (!empty($rw['description'])): ?>
                    <p class="rw-card-desc"><?= e($rw['description']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="rw-card-foot">
                    <div class="rw-cost-wrap">
                        <div class="rw-cost-row">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" width="14" height="14"
                                 class="rw-token-img-sm" alt="">
                            <span class="rw-cost-amt"><?= number_format($cost) ?></span>
                            <span class="rw-cost-lbl">token</span>
                        </div>
                        <?php if ($stockLeft !== null): ?>
                        <span class="rw-stock-txt <?= $stockLeft <= 3 ? 'rw-stock-low' : 'rw-stock-muted' ?>">
                            <?= $stockLeft <= 3 ? 'ใกล้หมด · ' : '' ?>เหลือ <?= $stockLeft ?> ชิ้น
                        </span>
                        <?php else: ?>
                        <span class="rw-stock-txt rw-u032">ไม่จำกัดจำนวน</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($canAfford): ?>
                    <button class="rw-redeem-btn"
                            data-action="open-redeem"
                            data-reward-id="<?= (int)$rw['reward_id'] ?>"
                            data-reward-title="<?= e($rw['title']) ?>"
                            data-reward-cost="<?= $cost ?>">
                        แลกเลย
                    </button>
                    <?php else: ?>
                    <div class="rw-lock-badge">
                        <svg class="rw-u033" width="11" height="11" viewBox="0 0 24 24" fill="none"
                             stroke="rgba(74,78,87,0.65)" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <div>
                            <span class="rw-lock-name">Token ไม่พอ</span>
                            <span class="rw-lock-need">ต้องการอีก <?= number_format($needed) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ HISTORY ══ -->
        <?php if (!empty($myRedemptions)): ?>
        <section class="rw-u034">
            <div class="rw-u035">
                <div class="rw-u036"></div>
                <span class="rw-u025">ประวัติการแลกรางวัล</span>
                <span class="rw-u037"><?= count($myRedemptions) ?></span>
            </div>

            <div class="jp-glass-card jp-glass-card--md rw-u038">
                <!-- Table header -->
                <div class="rw-u039">
                    <span>รางวัล</span><span>Token</span><span>วันที่</span><span>สถานะ</span>
                </div>

                <?php
                $dsDark = [
                    'pending'   => ['color' => '#fbbf24', 'bg' => 'rgba(245,158,11,0.10)',  'border' => 'rgba(245,158,11,0.25)'],
                    'fulfilled' => ['color' => '#518e5c', 'bg' => 'rgba(81,142,92,0.12)',   'border' => 'rgba(81,142,92,0.28)'],
                    'cancelled' => ['color' => '#d2592a', 'bg' => 'rgba(210,89,42,0.10)',   'border' => 'rgba(210,89,42,0.25)'],
                ];
                foreach ($myRedemptions as $rd):
                    $sm    = $statusMeta[$rd['status']] ?? $statusMeta['pending'];
                    $ds    = $dsDark[$rd['status']]     ?? $dsDark['pending'];
                    $rdCat = (string)($rd['category'] ?? 'general');
                    $tone  = $catTone[$rdCat] ?? $catTone['general'];
                    $rowToneClass = $bannerToneCls[$rdCat] ?? $bannerToneCls['general'];
                ?>
                 <div class="rw-hist-row rw-hist-detail-trigger"
                     data-redemption-id="<?= (int)$rd['redemption_id'] ?>"
                     tabindex="0" role="button" aria-label="ดูรายละเอียด: <?= e($rd['reward_title']) ?>"
                     class="rw-hist-row-wrap">
                    <!-- Main row -->
                    <div class="rw-u040">
                        <div class="rw-u041">
                            <span class="rw-hist-icon <?= e($rowToneClass) ?>">
                                <?= rewardCategoryIconSvg($rdCat) ?>
                            </span>
                            <div class="rw-u042">
                                <p class="rw-u043">
                                    <?= e($rd['reward_title']) ?>
                                </p>
                                <?php if (!empty($rd['admin_note'])): ?>
                                <p class="rw-u044">
                                    <?= e($rd['admin_note']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rw-u045">
                            <img src="<?= BASE_URL ?>/assets/images/token.png" loading="lazy"
                                 width="12" height="12" class="rw-token-img-xs-muted" alt="">
                            <span class="rw-u046">
                                <?= (int)$rd['tokens_spent'] ?>
                            </span>
                        </div>
                        <span class="rw-u047">
                            <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                        </span>
                        <div class="rw-u048">
                            <span class="rw-status-pill rw-status-<?= e($rd['status']) ?>">
                                <?= $sm['label'] ?>
                            </span>
                            <?php if ($rd['status'] === 'pending'): ?>
                                <button data-action="cancel-redemption"
                                    data-redemption-id="<?= (int)$rd['redemption_id'] ?>"
                                    data-reward-title="<?= e($rd['reward_title']) ?>"
                                    data-cost="<?= (int)$rd['tokens_spent'] ?>"
                                    class="rw-btn-cancel-redemption rw-btn-cancel-inline"
                                    title="ยกเลิกการแลกรางวัลนี้">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="10" height="10">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                ยกเลิก
                            </button>
                            <?php endif; ?>
                        </div>
                    </div><!-- /main row -->
                    <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
                    <!-- Coupon code row -->
                    <div class="rw-u049">
                        <!-- toggle button (left) -->
                        <button data-action="toggle-coupon-inline"
                            data-redemption-id="<?= (int)$rd['redemption_id'] ?>"
                            class="rw-btn-toggle-coupon rw-btn-toggle-inline"
                                title="แสดง/ซ่อนรหัสคูปอง">
                               <svg id="coupon-eye-<?= (int)$rd['redemption_id'] ?>"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                   width="12" height="12" class="rw-flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span id="coupon-btn-label-<?= (int)$rd['redemption_id'] ?>">แสดงรหัสคูปอง</span>
                        </button>
                        <!-- coupon box (hidden by default) -->
                        <div id="coupon-box-<?= (int)$rd['redemption_id'] ?>"
                                class="rw-inline-coupon-box">
                            <div class="rw-u050">
                                <span class="rw-u051">
                                    อนุมัติโดย: <?= e($rd['processed_by_name'] ?? '—') ?>
                                </span>
                                <span id="coupon-code-<?= (int)$rd['redemption_id'] ?>"
                                      class="rw-inline-coupon-code">
                                    <?= e($rd['coupon_code']) ?>
                                </span>
                            </div>
                                <button data-action="copy-coupon-inline"
                                    data-redemption-id="<?= (int)$rd['redemption_id'] ?>"
                                    data-code="<?= e((string)$rd['coupon_code']) ?>"
                                    id="coupon-copy-<?= (int)$rd['redemption_id'] ?>"
                                     class="rw-btn-copy-coupon rw-btn-copy-inline"
                                    title="คัดลอก">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                คัดลอก
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div><!-- /rw-hist-row -->
                <?php endforeach; ?>
            </div><!-- /history table -->
        </section>
        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /rw-rewards-wrap -->

<!-- ══════════════════════════════════════════════════════════
     REDEEM CONFIRM MODAL
══════════════════════════════════════════════════════════ -->
<div id="redeem-modal" role="dialog" aria-modal="true" data-action-overlay="close-redeem">
    <div class="rw-modal-box">

        <!-- Header -->
        <div class="rw-u052">
            <div class="rw-u053">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     width="14" height="14" class="rw-token-img-contain" alt="">
            </div>
            <span class="rw-u025">ยืนยันการแลกรางวัล</span>
                <button data-action="close-redeem"
                   
                    class="rw-btn-modal-close rw-u054" aria-label="ปิด">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="rw-u055">
            <p class="rw-u056" id="modal-body-text"></p>

            <!-- Error message -->
            <div class="rw-u057" id="modal-error"
                ></div>

            <!-- Buttons -->
            <div class="rw-u058">
                <button data-action="close-redeem"
                        id="modal-cancel-btn"
                       
                    class="rw-btn-modal-cancel rw-u059">
                    ยกเลิก
                </button>
                <button data-action="submit-redeem"
                        id="modal-confirm-btn"
                       
                    class="rw-btn-modal-confirm rw-u060">
                    ยืนยันแลกรางวัล
                </button>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    function rwCategoryIconSvg(category) {
        var map = {
            voucher: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
            leave: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            merch: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
            perk: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
            general: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>'
        };

        return map[category] || map.general;
    }

    function rwCategoryTone(category) {
        var map = {
            voucher: { bg: 'rgba(47,78,157,0.30)', border: 'rgba(123,159,245,0.52)', color: '#9db4f7' },
            leave:   { bg: 'rgba(81,142,92,0.30)', border: 'rgba(126,201,138,0.52)', color: '#8fdaa0' },
            merch:   { bg: 'rgba(98,48,122,0.32)', border: 'rgba(196,157,224,0.54)', color: '#d3ace8' },
            perk:    { bg: 'rgba(201,168,48,0.30)', border: 'rgba(248,231,105,0.52)', color: '#f8e769' },
            general: { bg: 'rgba(107,110,119,0.32)', border: 'rgba(165,169,181,0.52)', color: '#c9ccd4' }
        };
        return map[category] || map.general;
    }

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

        document.getElementById('modal-body-text').innerHTML =
            'แลกรางวัล <strong class="rw-modal-strong">' + title + '</strong> ' +
            'ใช้ <strong class="rw-modal-token">' + cost.toLocaleString() + ' Token</strong> ใช่หรือไม่?<br>' +
            '<span class="rw-modal-sub">ยอดคงเหลือ ' + (balance - cost).toLocaleString() + ' Token</span>';

        document.getElementById('modal-error').style.display        = 'none';

        var confirmBtn = document.getElementById('modal-confirm-btn');
        confirmBtn.disabled  = false;
        confirmBtn.textContent = 'ยืนยันแลกรางวัล';

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

                document.querySelectorAll('.rw-reward-card').forEach(function (card) {
                    var anyBtn = card.querySelector('button[data-action="open-redeem"][data-reward-id="' + _currentRewardId + '"]');
                    if (anyBtn) { anyBtn.disabled = true; anyBtn.textContent = 'แลกแล้ว'; }
                });

                closeRedeem();
                showRedeemToast('แลกรางวัลสำเร็จ — รอ HR ดำเนินการมอบรางวัล');
            } else {
                var errEl = document.getElementById('modal-error');
                errEl.textContent   = data.message || 'เกิดข้อผิดพลาด';
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'ยืนยันแลกรางวัล';
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
            btn.textContent = 'ยืนยันแลกรางวัล';
            document.getElementById('modal-cancel-btn').disabled = false;
        });
    };

    /* Escape key closes modal */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeRedeem();
    });

    document.addEventListener('click', function (e) {
        if (e.target.matches('[data-action-overlay="close-redeem"]')) {
            if (!window._redeemBusy) window.closeRedeem();
            return;
        }

        var filterBtn = e.target.closest('[data-action="filter-cat"]');
        if (filterBtn) {
            e.preventDefault();
            window.filterCat(filterBtn, filterBtn.dataset.cat || 'all');
            return;
        }

        var openRedeemBtn = e.target.closest('[data-action="open-redeem"]');
        if (openRedeemBtn) {
            e.preventDefault();
            window.openRedeem(
                parseInt(openRedeemBtn.dataset.rewardId, 10) || 0,
                openRedeemBtn.dataset.rewardTitle || '',
                parseInt(openRedeemBtn.dataset.rewardCost, 10) || 0
            );
            return;
        }

        var cancelBtn = e.target.closest('[data-action="cancel-redemption"]');
        if (cancelBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwCancelRedemption(
                parseInt(cancelBtn.dataset.redemptionId, 10) || 0,
                cancelBtn.dataset.rewardTitle || '',
                parseInt(cancelBtn.dataset.cost, 10) || 0
            );
            return;
        }

        var toggleInlineCouponBtn = e.target.closest('[data-action="toggle-coupon-inline"]');
        if (toggleInlineCouponBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwToggleCoupon(
                parseInt(toggleInlineCouponBtn.dataset.redemptionId, 10) || 0,
                toggleInlineCouponBtn
            );
            return;
        }

        var copyInlineCouponBtn = e.target.closest('[data-action="copy-coupon-inline"]');
        if (copyInlineCouponBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwCopyCoupon(
                copyInlineCouponBtn.dataset.code || '',
                parseInt(copyInlineCouponBtn.dataset.redemptionId, 10) || 0
            );
            return;
        }

        var closeRedeemBtn = e.target.closest('[data-action="close-redeem"]');
        if (closeRedeemBtn) {
            e.preventDefault();
            window.closeRedeem();
            return;
        }

        var submitRedeemBtn = e.target.closest('[data-action="submit-redeem"]');
        if (submitRedeemBtn) {
            e.preventDefault();
            window.submitRedeem();
            return;
        }

        var pendingBtn = e.target.closest('[data-action="open-pending-list"]');
        if (pendingBtn) {
            e.preventDefault();
            window.openPendingList();
            return;
        }

        var row = e.target.closest('.rw-hist-detail-trigger');
        if (row) {
            e.preventDefault();
            window.openRdDetail(parseInt(row.dataset.redemptionId, 10) || 0);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var row = e.target.closest('.rw-hist-detail-trigger');
        if (!row) return;
        e.preventDefault();
        window.openRdDetail(parseInt(row.dataset.redemptionId, 10) || 0);
    });

    /* Toast notification — reuse global #app-toast from footer.php */
    function showRedeemToast(msg) {
        var t = document.getElementById('app-toast');
        if (!t) return;
        t.className = 'toast-success';
        t.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="rw-flex-shrink-0">'
            + '<polyline points="20 6 9 17 4 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            + '<span>' + msg + '</span>';
        t.style.opacity = '';
        t.style.transform = '';
        t.style.transition = '';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { t.classList.add('show'); });
        });
        setTimeout(function () {
            t.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            t.style.opacity = '0';
            t.style.transform = 'translate(-50%,-50%) scale(0.9)';
        }, 3200);
    }
    window.showRedeemToast = showRedeemToast;
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
        'category' => (string)($_rd['category'] ?? 'general'),
        'tokens' => (int)$_rd['tokens_spent'],
        'status' => $_rd['status'],
        'reqAt'  => formatThaiBuddhistDateTime((string)$_rd['redeemed_at'], true),
        'procAt' => $_rd['processed_at']
                    ? formatThaiBuddhistDateTime((string)$_rd['processed_at'], true)
                    : null,
        'note'   => (string)($_rd['admin_note']        ?? ''),
        'procBy' => (string)($_rd['processed_by_name'] ?? ''),
        'coupon' => ($_rd['status'] === 'fulfilled') ? (string)($_rd['coupon_code'] ?? '') : '',
    ];
}
?>
<script>var _rdData = <?= json_encode($rdDetailData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>

<!-- ── Redemption Detail Modal ── -->
<div class="rw-u061" id="rd-detail-modal" data-action-overlay="close-rd-detail"
    >

    <div class="rw-u062" id="rd-detail-card"
        >

        <!-- Header -->
        <div class="rw-u063">
            <div class="rw-u064">
            <span class="rw-u065">DOC</span>
                <span class="rw-u066">รายละเอียดคำขอแลกรางวัล</span>
            </div>
                <button data-action="close-rd-detail"
                   
                    class="rw-btn-modal-close rw-u067" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="rw-u068">

            <!-- Reward card -->
            <div class="rw-u069">
                <span class="rw-u070" id="rdd-emoji"></span>
                <div class="rw-u071">
                    <p class="rw-u072" id="rdd-title"></p>
                    <span class="rw-u073" id="rdd-status-badge"
                         ></span>
                </div>
            </div>

            <!-- Info grid -->
            <div class="rw-u074">
                <div class="rw-u075">
                    <p class="rw-u076">Token ที่ใช้</p>
                    <p class="rw-u077" id="rdd-tokens"></p>
                </div>
                <div class="rw-u078">
                    <p class="rw-u076">วันที่ขอแลก</p>
                    <p class="rw-u079" id="rdd-req-at"></p>
                </div>
                <div class="rw-u080" id="rdd-proc-row">
                    <p class="rw-u076">วันที่ดำเนินการ</p>
                    <p class="rw-u081" id="rdd-proc-at"></p>
                    <p class="rw-u082" id="rdd-proc-by"></p>
                </div>
            </div>

            <!-- Admin note -->
            <div class="rw-u083" id="rdd-note-wrap">
                <p class="rw-u084">หมายเหตุจาก HR</p>
                <p class="rw-u085" id="rdd-note"></p>
            </div>

            <!-- Coupon reveal (fulfilled + has coupon) -->
            <div class="rw-u086" id="rdd-coupon-section">
                <button class="rw-u087" data-action="rd-toggle-coupon"
                       >
                    <span id="rdd-coupon-label">แสดงรหัสคูปอง</span>
                </button>
                <div class="rw-u088" id="rdd-coupon-box">
                    <p class="rw-u089">รหัสคูปอง</p>
                    <div class="rw-u090">
                        <p class="rw-u091" id="rdd-coupon-code"
                          ></p>
                        <button class="rw-u092" data-action="rd-copy-coupon"
                                id="rdd-coupon-copy"
                               >คัดลอก</button>
                    </div>
                </div>
            </div>

            <!-- Cancel section (pending only) -->
            <div class="rw-u086" id="rdd-cancel-section">
                <div class="rw-u093">
                    <p class="rw-u094">
                        Token จะถูกคืนให้ทันที หลังยืนยันยกเลิก
                    </p>
                        <button class="rw-u095" id="rdd-cancel-btn" data-action="rd-do-cancel"
                           ></button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
var _rdCurrentId = 0, _rdCurrentTokens = 0;

function rdCategoryIconSvg(category, size) {
    var iconSize = size || 22;
    var map = {
        voucher: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
        leave: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        merch: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
        perk: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
        general: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>'
    };
    return map[category] || map.general;
}

function rdCategoryTone(category) {
    var map = {
        voucher: { bg: 'rgba(47,78,157,0.30)', border: 'rgba(123,159,245,0.52)', color: '#9db4f7' },
        leave:   { bg: 'rgba(81,142,92,0.30)', border: 'rgba(126,201,138,0.52)', color: '#8fdaa0' },
        merch:   { bg: 'rgba(98,48,122,0.32)', border: 'rgba(196,157,224,0.54)', color: '#d3ace8' },
        perk:    { bg: 'rgba(201,168,48,0.30)', border: 'rgba(248,231,105,0.52)', color: '#f8e769' },
        general: { bg: 'rgba(107,110,119,0.32)', border: 'rgba(165,169,181,0.52)', color: '#c9ccd4' }
    };
    return map[category] || map.general;
}

function openRdDetail(rdId) {
    var d = _rdData[rdId];
    if (!d) return;
    _rdCurrentId     = rdId;
    _rdCurrentTokens = d.tokens;

    var tone = rdCategoryTone(d.category || 'general');
    var rddIcon = document.getElementById('rdd-emoji');
    rddIcon.innerHTML = rdCategoryIconSvg(d.category || 'general', 26);
    rddIcon.style.color = tone.color;
    rddIcon.style.background = tone.bg;
    rddIcon.style.border = '1px solid ' + tone.border;
    rddIcon.style.borderRadius = '999px';
    rddIcon.style.width = '44px';
    rddIcon.style.height = '44px';
    rddIcon.style.display = 'inline-flex';
    rddIcon.style.alignItems = 'center';
    rddIcon.style.justifyContent = 'center';
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
        document.getElementById('rdd-coupon-label').textContent  = 'แสดงรหัสคูปอง';
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
        box.style.display = 'flex'; lbl.textContent = 'ซ่อนรหัสคูปอง';
    } else {
        box.style.display = 'none'; lbl.textContent = 'แสดงรหัสคูปอง';
    }
}

function rdCopyCoupon() {
    var code = document.getElementById('rdd-coupon-code').textContent.trim();
    var btn  = document.getElementById('rdd-coupon-copy');
    navigator.clipboard.writeText(code).then(function() {
        var orig = btn.textContent;
        btn.textContent = 'คัดลอกแล้ว';
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
            cb.innerHTML = (data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    })
    .catch(function() {
        cb.disabled  = false;
        cb.innerHTML = 'การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeRdDetail(); closePendingList(); }
});

document.addEventListener('click', function(e) {
    if (e.target.matches('[data-action-overlay="close-rd-detail"]')) {
        closeRdDetail();
        return;
    }

    if (e.target.matches('[data-action-overlay="close-pending-list"]')) {
        closePendingList();
        return;
    }

    var closeDetailBtn = e.target.closest('[data-action="close-rd-detail"]');
    if (closeDetailBtn) {
        e.preventDefault();
        closeRdDetail();
        return;
    }

    var rdToggleCouponBtn = e.target.closest('[data-action="rd-toggle-coupon"]');
    if (rdToggleCouponBtn) {
        e.preventDefault();
        rdToggleCoupon();
        return;
    }

    var rdCopyCouponBtn = e.target.closest('[data-action="rd-copy-coupon"]');
    if (rdCopyCouponBtn) {
        e.preventDefault();
        rdCopyCoupon();
        return;
    }

    var rdDoCancelBtn = e.target.closest('[data-action="rd-do-cancel"]');
    if (rdDoCancelBtn) {
        e.preventDefault();
        rdDoCancel();
        return;
    }

    var closePendingBtn = e.target.closest('[data-action="close-pending-list"]');
    if (closePendingBtn) {
        e.preventDefault();
        closePendingList();
    }
});
</script>

<!-- ── Pending List Modal ── -->
<div class="rw-u096" id="rd-pending-modal" data-action-overlay="close-pending-list"
    >
    <div class="rw-u097" id="rd-pending-card"
        >
        <!-- Header -->
        <div class="rw-u098">
            <div class="rw-u064">
                <span class="rw-u099"></span>
                <span class="rw-u100">รอดำเนินการ</span>
                <span class="rw-u101" id="pending-count-badge"
                     ></span>
            </div>
                <button data-action="close-pending-list"
                   
                    class="rw-btn-modal-close rw-u067" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <!-- List -->
        <div class="rw-u102" id="pending-list-body"></div>
    </div>
</div>

<script>
function openPendingList() {
    var pending = Object.entries(_rdData).filter(function(e) { return e[1].status === 'pending'; });
    var body = document.getElementById('pending-list-body');
    document.getElementById('pending-count-badge').textContent = pending.length;
    body.innerHTML = '';
    if (pending.length === 0) {
        body.innerHTML = '<p class="rw-pending-empty">ไม่มีรายการรอดำเนินการ</p>';
    } else {
        pending.forEach(function(entry, idx) {
            var rdId = entry[0], d = entry[1];
            var toneClass = 'rw-tone-' + (d.category || 'general');
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
                '<span class="rw-pending-icon ' + toneClass + '">' +
                rdCategoryIconSvg(d.category || 'general', 20) +
                '</span>',
                '<div class="rw-pending-main">',
                '  <p class="rw-pending-title">',
                '            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + d.title + '</p>',
                '  <p class="rw-pending-date">ขอวันที่ ' + d.reqAt + '</p>',
                '</div>',
                '<div class="rw-pending-token-wrap">',
                '  <span class="rw-pending-token-value">' + d.tokens.toLocaleString() + '</span>',
                '  <span class="rw-pending-token-label">token</span>',
                '</div>',
                '<svg fill="none" stroke="#6b6e77" viewBox="0 0 24 24" width="14" height="14" class="rw-pending-arrow">',
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