<?php
/**
 * admin/rewards/edit.php
 * Admin: edit an existing reward (title, desc, emoji, category, token_cost, stock, is_active)
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId  = (int)$_SESSION['employee_id'];
$pdo      = getDB();
$rewardId = (int)($_GET['id'] ?? 0);

if ($rewardId <= 0) {
    setFlash('error', 'ไม่พบรางวัล');
    redirect(BASE_URL . '/hr/rewards/index.php');
}

// ══════════════════════════════════════════════════════════════
// POST — save changes
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $title      = trim($_POST['title']       ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $category   = $_POST['category']         ?? 'general';
    $cost       = max(1, (int)($_POST['token_cost'] ?? 50));
    $stockRaw   = trim($_POST['stock']       ?? '');
    $stock      = ($stockRaw === '') ? null : max(0, (int)$stockRaw);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $couponCode    = trim($_POST['coupon_code']      ?? '');
    $couponExpiry   = trim($_POST['coupon_expires_at'] ?? '');

    // Parse expiry: only save if coupon code is set AND expiry provided
    $couponExpiresAt = null;
    if ($couponCode !== '' && $couponExpiry !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $couponExpiry);
        $couponExpiresAt = $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    $allowed = ['voucher', 'leave', 'merch', 'perk', 'general'];
    if (!in_array($category, $allowed, true)) $category = 'general';

    if (empty($title)) {
        setFlash('error', 'กรุณากรอกชื่อรางวัล');
        redirect(BASE_URL . '/hr/rewards/edit.php?id=' . $rewardId);
    }

    try {
        $pdo->prepare("
            UPDATE dbo.rewards
            SET title = ?, description = ?,
                category = ?, token_cost = ?, stock = ?, is_active = ?,
                coupon_code = ?, coupon_expires_at = ?
            WHERE reward_id = ?
        ")->execute([$title, $desc, $category, $cost, $stock, $isActive,
                     $couponCode ?: null, $couponExpiresAt, $rewardId]);
        setFlash('success', 'แก้ไขรางวัล "' . $title . '" เรียบร้อยแล้ว');
    } catch (Throwable $e) {
        error_log('[MissionToken] edit reward error: ' . $e->getMessage());
        setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        redirect(BASE_URL . '/hr/rewards/edit.php?id=' . $rewardId);
    }
    redirect(BASE_URL . '/hr/rewards/index.php');
}

// ══════════════════════════════════════════════════════════════
// PAGE LOAD — fetch reward
// ══════════════════════════════════════════════════════════════
$reward    = null;
$dataError = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM dbo.rewards WHERE reward_id = ?");
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[MissionToken] load reward error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้';
}

if (!$reward && !$dataError) {
    setFlash('error', 'ไม่พบรางวัล');
    redirect(BASE_URL . '/hr/rewards/index.php');
}

$catMeta = [
    'voucher' => ['label' => 'คูปอง'],
    'leave'   => ['label' => 'วันหยุดพิเศษ'],
    'merch'   => ['label' => 'ของที่ระลึก'],
    'perk'    => ['label' => 'สิทธิพิเศษ'],
    'general' => ['label' => 'ทั่วไป'],
];

$pageTitle  = 'แก้ไขรางวัล';
$activePage = 'admin_rewards';
$flash      = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>
<script>
function arToggleExpiry(val) {
    var wrap = document.getElementById('coupon-expiry-wrap');
    if (wrap) wrap.classList.toggle('are-hidden', val.trim() === '');
}
function arToggleCoupon(cat) {
    var block = document.getElementById('coupon-block');
    if (!block) return;
    if (cat === 'voucher') {
        block.classList.remove('are-hidden');
    } else {
        block.classList.add('are-hidden');
        // clear values when hidden
        var inp = document.getElementById('coupon_code_input');
        if (inp) { inp.value = ''; arToggleExpiry(''); }
    }
}
function arUpdateActiveLabel(checked) {
    var lb = document.getElementById('active-label');
    if (!lb) return;
    lb.textContent = checked ? 'เปิดใช้งาน' : 'ปิดการใช้งาน';
    lb.classList.toggle('are-active-label--on', checked);
    lb.classList.toggle('are-active-label--off', !checked);
}
function arToggleActiveState(checked) {
    var t = document.getElementById('active-toggle');
    if (!t) return;
    t.classList.toggle('on', checked);
    arUpdateActiveLabel(checked);
}
// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    var catSelect = document.getElementById('category-select');
    if (catSelect) {
        catSelect.addEventListener('change', function() {
            arToggleCoupon(this.value);
        });
    }
    var couponInput = document.getElementById('coupon_code_input');
    if (couponInput) {
        couponInput.addEventListener('input', function() {
            arToggleExpiry(this.value);
        });
    }
    var checkboxCb = document.getElementById('is_active_cb');
    if (checkboxCb) {
        checkboxCb.addEventListener('change', function() {
            arToggleActiveState(this.checked);
        });
    }
    var toggleBtn = document.getElementById('active-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            var cb = document.getElementById('is_active_cb');
            if (cb) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
            }
        });
    }
});
</script>

<div class="ar-rewards-wrap are-wrap are-wrap-shell">

    <!-- Aurora blobs -->
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="jp-aurora-blob jp-aurora-blob--gold"></div>
        <div class="jp-aurora-blob jp-aurora-blob--teal"></div>
    </div>

    <div class="jp-page-inner jp-page-inner--narrow">

        <!-- Breadcrumb + page header -->
        <div class="jp-page-header">
            <div class="are-breadcrumb-wrap">
                <a href="<?php echo BASE_URL; ?>/hr/rewards/index.php"
                   class="are-breadcrumb-link">
                    ← จัดการรางวัล
                </a>
            </div>
            <p class="jp-kicker">
                ADMIN — EDIT REWARD
            </p>
            <h1 class="jp-title jp-title--tight">
                แก้ไขรางวัล
            </h1>
            <?php if ($reward): ?>
            <p class="jp-subtitle are-subtitle-tight">
                R <?= e($reward['title']) ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($dataError): ?>
        <div class="jp-alert-error">
            <?= e($dataError) ?>
        </div>
        <?php else: ?>

        <!-- Edit form -->
        <form method="POST" action="">
            <?php echo csrfField(); ?>

            <div class="jp-glass-card jp-glass-card--lg">

                <!-- Title -->
                <div class="are-field-block-lg">
                    <label class="are-label">ชื่อรางวัล <span class="are-required">*</span></label>
                    <input type="text" name="title" required maxlength="200"
                           value="<?= e($reward['title']) ?>"
                           placeholder="ชื่อรางวัล"
                           class="journal-input">
                </div>

                <!-- Description -->
                <div class="are-field-block-lg">
                    <label class="are-label">คำอธิบาย</label>
                    <textarea name="description" rows="3" maxlength="500"
                              placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                              class="journal-input are-textarea"><?= e($reward['description'] ?? '') ?></textarea>
                </div>

                <!-- Category + cost + stock -->
                <div class="are-grid-3">
                    <div>
                        <label class="are-label">หมวดหมู่</label>
                        <select name="category" id="category-select" class="journal-input">
                            <?php foreach ($catMeta as $k => $m): ?>
                            <option value="<?= e($k) ?>"
                                    <?= ($reward['category'] === $k) ? 'selected' : '' ?>>
                                <?= e($m['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="are-label">ราคา (Token)</label>
                        <input type="number" name="token_cost" min="1" max="99999"
                               value="<?= (int)$reward['token_cost'] ?>"
                               class="journal-input">
                    </div>
                    <div>
                        <label class="are-label">จำนวนสต็อก</label>
                        <input type="number" name="stock" min="0"
                               value="<?= $reward['stock'] === null ? '' : (int)$reward['stock'] ?>"
                               placeholder="เว้นว่าง = ไม่จำกัด"
                               class="journal-input">
                        <span class="are-stock-hint">เว้นว่าง = ไม่จำกัด</span>
                    </div>
                </div>

                <!-- Coupon code -->
                <?php $isCouponCat = ($reward['category'] === 'voucher'); ?>
                <div id="coupon-block" class="are-coupon-block<?= $isCouponCat ? '' : ' are-hidden' ?>">
                    <div class="are-coupon-head-row">
                        <span class="are-coupon-head-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <circle cx="8" cy="12" r="3" stroke-width="2"/>
                                <path d="M11 12h10M18 12v3M15 12v2" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <div>
                            <p class="are-coupon-title">รหัสคูปอง / โค้ดส่วนลด</p>
                            <p class="are-coupon-subtitle">
                                พนักงานจะเห็นโค้ดนี้เมื่อ HR ยืนยันมอบรางวัลแล้วเท่านั้น
                            </p>
                        </div>
                    </div>
                    <label class="are-label">โค้ด <span class="are-optional">(ไม่บังคับ)</span></label>
                    <input type="text" name="coupon_code" id="coupon_code_input" maxlength="200"
                           value="<?= e($reward['coupon_code'] ?? '') ?>"
                           placeholder="เช่น COFFEE2026, LEAVE-MAY, DISCOUNT50"
                           class="journal-input are-coupon-input">
                    <p class="are-coupon-note">
                        เว้นว่างถ้ารางวัลนี้ไม่ใช้ระบบโค้ด
                    </p>

                    <!-- Expiry date/time — shown only when coupon code is filled -->
                    <?php
                        $expiresAt = $reward['coupon_expires_at'] ?? null;
                        $expiresFormatted = '';
                        if ($expiresAt) {
                            $dt = (new DateTime($expiresAt instanceof DateTimeInterface ? $expiresAt->format('Y-m-d H:i:s') : (string)$expiresAt));
                            $expiresFormatted = $dt->format('Y-m-d\TH:i');
                        }
                        $hasCoupon = !empty($reward['coupon_code']);
                    ?>
                    <div id="coupon-expiry-wrap" class="are-expiry-wrap<?= $hasCoupon ? '' : ' are-hidden' ?>">
                        <label class="are-label">
                            วันหมดอายุคูปอง
                            <span class="are-optional">(ไม่บังคับ — เว้นว่างถ้าไม่มีวันหมดอายุ)</span>
                        </label>
                        <input type="datetime-local" name="coupon_expires_at"
                               value="<?= e($expiresFormatted) ?>"
                               class="journal-input are-datetime-input">
                        <?php if ($expiresAt): ?>
                        <p class="are-expiry-note">
                            หมดอายุ: <?= (new DateTime((string)$expiresAt))->format('d/m/Y H:i') ?> น.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active toggle -->
                <div class="are-active-row">
                    <div class="are-active-copy">
                        <p class="are-active-title">
                            สถานะรางวัล
                        </p>
                        <p class="are-active-desc">
                            เมื่อปิดรางวัล พนักงานจะไม่เห็นรางวัลนี้ในร้านค้า
                        </p>
                    </div>
                    <label class="are-active-label-wrap">
                        <input type="checkbox" name="is_active" id="is_active_cb"
                               <?= $reward['is_active'] ? 'checked' : '' ?>
                               class="are-hidden-input">
                        <span id="active-toggle"
                              class="are-active-toggle <?= $reward['is_active'] ? 'on' : '' ?>">
                        </span>
                        <span id="active-label"
                              class="are-active-label <?= $reward['is_active'] ? 'are-active-label--on' : 'are-active-label--off' ?>">
                            <?= $reward['is_active'] ? 'เปิดใช้งาน' : 'ปิดการใช้งาน' ?>
                        </span>
                    </label>
                </div>

                <!-- Actions -->
                <div class="jp-actions-end">
                    <a href="<?php echo BASE_URL; ?>/hr/rewards/index.php"
                       class="are-cancel-link">
                        ยกเลิก
                    </a>
                    <button type="submit" class="ch-btn-start are-submit-btn">
                        บันทึกการแก้ไข
                    </button>
                </div>
            </div>
        </form>

        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /ar-rewards-wrap -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
