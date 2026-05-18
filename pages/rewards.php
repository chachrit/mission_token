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
                        <div class="rw-u012" id="hdr-balance" data-balance="<?= (int)$wallet['balance'] ?>"
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
            <button class="rw-cat-pill active" data-cat="all" data-action="filter-cat" aria-pressed="true">ทั้งหมด</button>
            <?php foreach ($activeCategories as $cat):
                $meta  = $catMeta[$cat] ?? $catMeta['general'];
                $count = count(array_filter($rewards, fn($r) => $r['category'] === $cat));
            ?>
            <button class="rw-cat-pill" data-cat="<?= e($cat) ?>" data-action="filter-cat" aria-pressed="false">
                <?= e($meta['label']) ?> <span class="rw-u027">(<?= $count ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ══ REWARDS GRID ══ -->
        <?php if (empty($rewards)): ?>
        <div class="rw-u028 rw-empty-state">
            <p class="rw-u029">Vault ว่างอยู่ในขณะนี้</p>
            <p class="rw-u030">สะสม Token เพิ่มก่อน แล้วกลับมาดูรางวัลใหม่อีกครั้ง</p>
            <a href="<?= BASE_URL ?>/pages/challenges.php" class="rw-empty-cta">ไปทำภารกิจต่อ</a>
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
<div id="redeem-modal" role="dialog" aria-modal="true" aria-hidden="true" data-action-overlay="close-redeem">
    <div class="rw-modal-box" tabindex="-1">

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
<div class="rw-u061" id="rd-detail-modal" role="dialog" aria-modal="true" aria-hidden="true" data-action-overlay="close-rd-detail"
    >

    <div class="rw-u062" id="rd-detail-card" tabindex="-1"
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



<!-- ── Pending List Modal ── -->
<div class="rw-u096" id="rd-pending-modal" role="dialog" aria-modal="true" aria-hidden="true" data-action-overlay="close-pending-list"
    >
    <div class="rw-u097" id="rd-pending-card" tabindex="-1"
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

<script src="<?= BASE_URL ?>/assets/js/rewards.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>