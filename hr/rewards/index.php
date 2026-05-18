<?php
/**
 * admin/rewards/index.php
 * Admin: manage rewards catalogue (create, edit stock, toggle active)
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];
$pdo     = getDB();

function rewardCategoryIconCode(string $category): string
{
    $map = [
        'voucher' => 'V',
        'leave'   => 'L',
        'merch'   => 'M',
        'perk'    => 'P',
        'general' => 'R',
    ];

    return $map[$category] ?? 'R';
}

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
// POST actions
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    // ── Create new reward ──────────────────────────────────
    if ($action === 'create') {
        $title    = trim($_POST['title']     ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $cost     = max(1, (int)($_POST['token_cost'] ?? 50));
        $stockRaw = trim($_POST['stock'] ?? '');
        $stock    = ($stockRaw === '' || $stockRaw === '0') ? null : max(1, (int)$stockRaw);
        $couponCode  = trim($_POST['coupon_code']      ?? '') ?: null;
        $couponExpiry = trim($_POST['coupon_expires_at'] ?? '');
        $couponExpiresAt = null;
        if ($couponCode !== null && $couponExpiry !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $couponExpiry);
            $couponExpiresAt = $dt ? $dt->format('Y-m-d H:i:s') : null;
        }

        $allowed = ['voucher','leave','merch','perk','general'];
        if (!in_array($category, $allowed, true)) $category = 'general';
    $emoji = rewardCategoryIconCode($category);
        if (empty($title)) { setFlash('error', 'กรุณากรอกชื่อรางวัล'); redirect(BASE_URL . '/hr/rewards/index.php'); }

        try {
            $pdo->prepare("
                INSERT INTO dbo.rewards (title, description, image_emoji, category, token_cost, stock, coupon_code, coupon_expires_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $desc, $emoji, $category, $cost, $stock, $couponCode, $couponExpiresAt, $adminId]);
            setFlash('success', 'เพิ่มรางวัล "' . $title . '" เรียบร้อยแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] create reward error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
        redirect(BASE_URL . '/hr/rewards/index.php');
    }

    // ── Toggle active ──────────────────────────────────────
    if ($action === 'toggle') {
        $rewardId = (int)($_POST['reward_id'] ?? 0);
        try {
            $pdo->prepare("
                UPDATE dbo.rewards
                SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
                WHERE reward_id = ?
            ")->execute([$rewardId]);
            setFlash('success', 'อัปเดตสถานะรางวัลแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] toggle reward error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/hr/rewards/index.php');
    }

    // ── Update stock ───────────────────────────────────────
    if ($action === 'update_stock') {
        $rewardId = (int)($_POST['reward_id'] ?? 0);
        $stockRaw = trim($_POST['stock'] ?? '');
        $stock    = ($stockRaw === '' || $stockRaw === 'unlimited') ? null : max(0, (int)$stockRaw);
        try {
            $pdo->prepare("UPDATE dbo.rewards SET stock = ? WHERE reward_id = ?")->execute([$stock, $rewardId]);
            setFlash('success', 'อัปเดตจำนวนสต็อกแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] update stock error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/hr/rewards/index.php');
    }

    // ── Delete reward ──────────────────────────────────────
    if ($action === 'delete') {
        $rewardId = (int)($_POST['reward_id'] ?? 0);
        try {
            // Check if any redemptions reference this reward
            $chk = $pdo->prepare("SELECT COUNT(*) AS cnt FROM dbo.reward_redemptions WHERE reward_id = ?");
            $chk->execute([$rewardId]);
            $redeemCount = (int)($chk->fetch()['cnt'] ?? 0);

            if ($redeemCount > 0) {
                setFlash('error', 'ไม่สามารถลบได้ เนื่องจากมีประวัติการแลกรางวัลนี้แล้ว ' . $redeemCount . ' รายการ — ใช้ปุ่ม Toggle เพื่อ "ปิด" รางวัลแทน เพื่อซ่อนจากร้านค้าโดยไม่ลบประวัติพนักงาน');
            } else {
                $pdo->prepare("DELETE FROM dbo.rewards WHERE reward_id = ?")->execute([$rewardId]);
                setFlash('success', 'ลบรางวัลเรียบร้อยแล้ว');
            }
        } catch (Throwable $e) {
            error_log('[MissionToken] delete reward error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/hr/rewards/index.php');
    }
}

// ══════════════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════════════
$rewards   = [];
$dataError = null;

$catFilter   = (string)($_GET['cat'] ?? '');
$allowedCats = ['voucher','leave','merch','perk','general'];
if (!in_array($catFilter, $allowedCats, true)) $catFilter = '';

try {
    if ($catFilter) {
        $stmt = $pdo->prepare("
            SELECT r.reward_id, r.title, r.description, r.image_emoji,
                   r.category, r.token_cost, r.stock, r.is_active, r.created_at,
                   (SELECT COUNT(*) FROM dbo.reward_redemptions rd
                    WHERE rd.reward_id = r.reward_id) AS total_redeemed,
                   (SELECT COUNT(*) FROM dbo.reward_redemptions rd
                    WHERE rd.reward_id = r.reward_id AND rd.status = 'pending') AS pending_count
            FROM   dbo.rewards r
            WHERE  r.category = ?
            ORDER BY r.is_active DESC, r.created_at DESC
        ");
        $stmt->execute([$catFilter]);
        $rewards = $stmt->fetchAll();
    } else {
        $rewards = $pdo->query("
            SELECT r.reward_id, r.title, r.description, r.image_emoji,
                   r.category, r.token_cost, r.stock, r.is_active, r.created_at,
                   (SELECT COUNT(*) FROM dbo.reward_redemptions rd
                    WHERE rd.reward_id = r.reward_id) AS total_redeemed,
                   (SELECT COUNT(*) FROM dbo.reward_redemptions rd
                    WHERE rd.reward_id = r.reward_id AND rd.status = 'pending') AS pending_count
            FROM   dbo.rewards r
            ORDER BY r.is_active DESC, r.created_at DESC
        ")->fetchAll();
    }
} catch (Throwable $e) {
    error_log('[MissionToken] admin rewards load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้';
}

$catMeta = [
    'voucher' => ['label' => 'คูปอง',        'color' => '#2f4e9d', 'bg' => '#eaedfa'],
    'leave'   => ['label' => 'วันหยุดพิเศษ', 'color' => '#518e5c', 'bg' => '#e6f4e9'],
    'merch'   => ['label' => 'ของที่ระลึก',   'color' => '#62307a', 'bg' => '#f1e8f7'],
    'perk'    => ['label' => 'สิทธิพิเศษ',   'color' => '#c9a830', 'bg' => '#fdf4d0'],
    'general' => ['label' => 'ทั่วไป',        'color' => '#6b6e77', 'bg' => '#eeecea'],
];

$pageTitle  = 'จัดการรางวัล';
$activePage = 'admin_rewards';
$flash      = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="ar-rewards-wrap ar-wrap ar-wrap-shell">

    <!-- Aurora blobs -->
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="jp-aurora-blob jp-aurora-blob--gold"></div>
        <div class="jp-aurora-blob jp-aurora-blob--teal"></div>
    </div>

    <div class="jp-page-inner">

        <!-- Page header -->
        <div class="jp-page-header">
            <!-- Row 1: title + create button -->
            <div class="ar-page-header-row ar-page-header-row-gap">
                <div>
                    <p class="jp-kicker">
                        ⬡ &nbsp;ADMIN — REWARD CATALOGUE
                    </p>
                    <h1 class="jp-title">
                        จัดการรางวัล
                    </h1>
                    <p class="jp-subtitle">
                        เพิ่ม แก้ไข และจัดการสต็อกรางวัลในร้านค้า Token
                        <?php if (!empty($rewards)): ?>
                        <span class="ar-total-chip">
                            <?= count($rewards) ?> รายการ
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <button id="ar-create-toggle-btn" type="button" aria-expanded="false" aria-controls="create-form"
                        data-onclick="arToggleCreateForm()"
                        class="ch-btn-start ar-create-toggle-btn">
                    + เพิ่มรางวัลใหม่
                </button>
            </div>
            <!-- Row 2: category filter pills -->
            <div class="ar-cat-filter-row">
                <?php
                $catFilters = ['' => 'ทั้งหมด'] + array_map(fn($m) => $m['label'], $catMeta);
                foreach ($catFilters as $val => $label):
                    $isActiveCat = ($catFilter === $val);
                ?>
                     <a href="<?= BASE_URL ?>/hr/rewards/index.php<?= $val ? '?cat=' . $val : '' ?>"
                         class="ar-cat-pill<?= $isActiveCat ? ' active' : '' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($dataError): ?>
        <div class="jp-alert-error">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- CREATE FORM -->
        <form id="create-form" method="POST" action="" aria-hidden="true">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">

            <div class="ar-create-head-row">
                <div class="ar-create-head-bar"></div>
                <span class="ar-create-head-title">เพิ่มรางวัลใหม่</span>
            </div>

            <div class="ar-field-block">
                <label class="ar-label">ชื่อรางวัล <span class="ar-required">*</span></label>
                <input type="text" name="title" required maxlength="200"
                       placeholder="เช่น คูปองกาแฟ, วันลาพิเศษ..."
                       class="journal-input">
            </div>

            <div class="ar-field-block">
                <label class="ar-label">คำอธิบาย</label>
                <textarea name="description" rows="2" maxlength="500"
                          placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                          class="journal-input ar-textarea"></textarea>
            </div>

            <div class="ar-create-grid ar-create-grid-layout">
                <div>
                    <label class="ar-label">หมวดหมู่</label>
                    <select name="category" id="ar-create-category" class="journal-input" data-onchange="arUpdateAutoIcon(this.value)">
                        <?php foreach ($catMeta as $k => $m): ?>
                        <option value="<?= e($k) ?>"><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ar-auto-icon-row">
                        <span id="ar-auto-icon-preview" class="ar-auto-icon-preview"><?= rewardCategoryIconSvg('voucher') ?></span>
                        <span id="ar-auto-icon-hint" class="ar-auto-icon-hint">ไอคอนตามหมวดหมู่: คูปอง</span>
                    </div>
                </div>
                <div>
                    <label class="ar-label">ราคา (Token)</label>
                    <input type="number" name="token_cost" value="50" min="1" max="99999"
                           class="journal-input">
                </div>
                <div>
                    <label class="ar-label">จำนวนสต็อก</label>
                    <input type="number" name="stock" min="0" placeholder="เว้นว่าง = ไม่จำกัด"
                           class="journal-input">
                    <span class="ar-stock-hint">เว้นว่าง = ไม่จำกัด</span>
                </div>
            </div>

            <div id="create-coupon-wrap" class="ar-coupon-wrap">
                <div class="ar-coupon-head-row">
                    <span class="ar-coupon-head-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="8" cy="12" r="3" stroke-width="2"/>
                            <path d="M11 12h10M18 12v3M15 12v2" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <label class="ar-label ar-label-inline">รหัสคูปอง / โค้ดส่วนลด</label>
                    <span class="ar-optional-note">(ไม่บังคับ)</span>
                </div>
                <input type="text" name="coupon_code" id="create_coupon_code" maxlength="200"
                       placeholder="เช่น COFFEE2026, LEAVE-MAY, DISCOUNT50"
                       class="journal-input ar-coupon-input"
                       data-oninput="arCreateToggleExpiry(this.value)">
                <p class="ar-coupon-note">พนักงานจะเห็นโค้ดนี้หลัง HR ยืนยันมอบรางวัลแล้วเท่านั้น</p>

                <!-- Expiry — shown when coupon code is filled -->
                <div id="create-expiry-wrap" class="ar-expiry-wrap">
                    <label class="ar-label">
                        วันหมดอายุคูปอง
                        <span class="ar-optional-note ar-optional-note--inline">(ไม่บังคับ)</span>
                    </label>
                    <input type="datetime-local" name="coupon_expires_at"
                           class="journal-input ar-datetime-input">
                </div>
            </div>

            <div class="jp-actions-end jp-actions-end--sm">
                <button type="button"
                        class="ar-form-btn ar-form-btn--cancel"
                    data-onclick="arCloseCreateForm()">
                    ยกเลิก
                </button>
                <button type="submit" class="ch-btn-start ar-form-btn ar-form-btn--submit">
                    บันทึกรางวัล
                </button>
            </div>
        </form>

        <!-- REWARDS TABLE -->
        <div class="ar-table-wrap jp-glass-card jp-glass-card--md">

            <div class="jp-table-header ar-table-header ar-table-grid">
                <span>รางวัล</span>
                <span>หมวด</span>
                <span>ราคา</span>
                <span>สต็อก</span>
                <span>แลกแล้ว</span>
                <span class="ar-cell-center">สถานะ</span>
                <span class="ar-cell-right">จัดการ</span>
            </div>

            <?php if (empty($rewards)): ?>
            <div class="jp-empty-state">
                <p class="ar-empty-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M3 8h18" stroke-width="2"/>
                        <path d="M4 8l8 6 8-6" stroke-width="2"/>
                        <rect x="3" y="8" width="18" height="12" rx="2" stroke-width="2"/>
                    </svg>
                </p>
                <p class="jp-empty-note">ยังไม่มีรางวัล กด "เพิ่มรางวัลใหม่" เพื่อเริ่มต้น</p>
            </div>
            <?php else: ?>
            <?php
            $glowMap = [
                'voucher' => ['color' => '#7b9ff5', 'bg' => 'rgba(47,78,157,0.15)',    'border' => 'rgba(47,78,157,0.28)'],
                'leave'   => ['color' => '#7ec98a', 'bg' => 'rgba(81,142,92,0.15)',    'border' => 'rgba(81,142,92,0.28)'],
                'merch'   => ['color' => '#c49de0', 'bg' => 'rgba(98,48,122,0.15)',    'border' => 'rgba(98,48,122,0.28)'],
                'perk'    => ['color' => '#f8e769', 'bg' => 'rgba(201,168,48,0.15)',   'border' => 'rgba(201,168,48,0.28)'],
                'general' => ['color' => '#6b6e77', 'bg' => 'rgba(107,110,119,0.12)', 'border' => 'rgba(107,110,119,0.24)'],
            ];
            foreach ($rewards as $rw):
                $cat      = $rw['category'];
                $meta     = $catMeta[$cat] ?? $catMeta['general'];
                $glow     = $glowMap[$cat] ?? $glowMap['general'];
                $isOn     = (bool)$rw['is_active'];
                $catTheme = 'ar-cat-theme--' . preg_replace('/[^a-z0-9_-]/i', '', (string)$cat);
            ?>
              <div class="ar-row ar-table-grid ar-row-grid <?= $isOn ? '' : 'ar-row-inactive' ?>">

                <!-- Reward name + emoji -->
                <div class="ar-reward-main">
                      <span class="ar-reward-icon <?= e($catTheme) ?>">
                        <?= rewardCategoryIconSvg((string)$cat) ?>
                    </span>
                    <div class="ar-reward-text-wrap">
                        <p class="ar-reward-title">
                            <?= e($rw['title']) ?>
                        </p>
                        <?php if (!empty($rw['description'])): ?>
                        <p class="ar-reward-desc">
                            <?= e($rw['description']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category -->
                    <span class="ar-category-chip <?= e($catTheme) ?>">
                    <?= e($meta['label']) ?>
                </span>

                <!-- Cost -->
                <div class="ar-token-cell">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="13" height="13" class="ar-token-icon" alt="">
                    <span class="ar-token-value">
                        <?= (int)$rw['token_cost'] ?>
                    </span>
                </div>

                <!-- Stock (read-only) -->
                <div>
                    <span class="ar-stock-value">
                        <?= $rw['stock'] === null ? '∞' : (int)$rw['stock'] ?>
                    </span>
                    <span class="ar-stock-note">
                        <?= $rw['stock'] === null ? 'ไม่จำกัด' : 'คงเหลือ' ?>
                    </span>
                </div>

                <!-- Total redeemed + pending -->
                <div>
                    <span class="ar-redeemed-value">
                        <?= (int)$rw['total_redeemed'] ?>
                    </span>
                    <?php if ((int)$rw['pending_count'] > 0): ?>
                    <span class="ar-redeemed-pending">
                        (<?= (int)$rw['pending_count'] ?> รอ)
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Toggle active -->
                <div class="ar-cell-center">
                    <form method="POST" action="" class="ar-inline-form">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action"    value="toggle">
                        <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                        <label class="ac-toggle-switch" title="<?= $isOn ? 'คลิกเพื่อปิด' : 'คลิกเพื่อเปิด' ?>">
                            <input type="checkbox"
                                   <?= $isOn ? 'checked' : '' ?>
                                   data-onchange="this.form.submit()">
                            <span class="ac-toggle-track">
                                <span class="ac-toggle-thumb"></span>
                            </span>
                        </label>
                    </form>
                </div>

                <!-- Edit + Delete -->
                <div class="ar-actions-row">
                    <a href="<?php echo BASE_URL; ?>/hr/rewards/edit.php?id=<?= (int)$rw['reward_id'] ?>"
                       class="ar-action-btn ar-action-btn--edit">
                        แก้ไข
                    </a>
                    <form method="POST" action="" class="ar-inline-form-flex"
                          data-onsubmit="return confirm('ลบรางวัล &quot;<?= addslashes(e($rw['title'])) ?>&quot; ?\nรางวัลที่มีประวัติการแลกจะไม่สามารถลบได้')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action"    value="delete">
                        <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                        <button type="submit"
                            class="ar-action-btn ar-action-btn--delete"
                                title="ลบรางวัล">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div><!-- /ar-rewards-table end -->

    </div><!-- /inner -->
</div><!-- /ar-rewards-wrap -->

<script>
var _arCreateLastFocus = null;

function arCategoryIconCode(category) {
    var map = {
        voucher: 'V',
        leave: 'L',
        merch: 'M',
        perk: 'P',
        general: 'R'
    };
    return map[category] || 'R';
}

function arCategoryIconSvg(category) {
    var map = {
        voucher: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
        leave: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        merch: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
        perk: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
        general: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>'
    };
    return map[category] || map.general;
}

function arUpdateAutoIcon(category) {
    var labels = {
        voucher: 'คูปอง',
        leave: 'วันหยุดพิเศษ',
        merch: 'ของที่ระลึก',
        perk: 'สิทธิพิเศษ',
        general: 'ทั่วไป'
    };
    var preview = document.getElementById('ar-auto-icon-preview');
    var hint = document.getElementById('ar-auto-icon-hint');
    if (preview) preview.innerHTML = arCategoryIconSvg(category);
    if (hint) hint.textContent = 'ไอคอนตามหมวดหมู่: ' + (labels[category] || labels.general);
    // Show coupon section only for voucher category
    var couponWrap = document.getElementById('create-coupon-wrap');
    if (couponWrap) {
        if (category === 'voucher') {
            couponWrap.style.display = 'block';
        } else {
            couponWrap.style.display = 'none';
            // Clear coupon fields when hidden
            var codeInput = document.getElementById('create_coupon_code');
            if (codeInput) { codeInput.value = ''; arCreateToggleExpiry(''); }
        }
    }
}

function arToggleCreateForm() {
    var form = document.getElementById('create-form');
    var btn = document.getElementById('ar-create-toggle-btn');
    if (!form || !btn) return;
    var open = form.classList.toggle('open');
    form.setAttribute('aria-hidden', open ? 'false' : 'true');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.textContent = open ? 'ปิด' : '+ เพิ่มรางวัลใหม่';
    if (open) {
        _arCreateLastFocus = document.activeElement;
        setTimeout(function () {
            var firstInput = form.querySelector('input, select, textarea, button');
            if (firstInput) firstInput.focus();
        }, 0);
    }
}

function arCloseCreateForm() {
    var form = document.getElementById('create-form');
    var btn = document.getElementById('ar-create-toggle-btn');
    if (!form || !btn) return;
    form.classList.remove('open');
    form.setAttribute('aria-hidden', 'true');
    btn.setAttribute('aria-expanded', 'false');
    btn.textContent = '+ เพิ่มรางวัลใหม่';
    if (_arCreateLastFocus && typeof _arCreateLastFocus.focus === 'function') {
        _arCreateLastFocus.focus();
    }
}

function arCreateToggleExpiry(val) {
    var wrap = document.getElementById('create-expiry-wrap');
    if (wrap) wrap.style.display = val.trim() !== '' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    var categorySelect = document.getElementById('ar-create-category');
    if (categorySelect) {
        arUpdateAutoIcon(categorySelect.value);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

