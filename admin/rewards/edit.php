<?php
/**
 * admin/rewards/edit.php
 * Admin: edit an existing reward (title, desc, emoji, category, token_cost, stock, is_active)
 */

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId  = (int)$_SESSION['employee_id'];
$pdo      = getDB();
$rewardId = (int)($_GET['id'] ?? 0);

if ($rewardId <= 0) {
    setFlash('error', 'ไม่พบรางวัล');
    redirect(BASE_URL . '/admin/rewards/index.php');
}

// ══════════════════════════════════════════════════════════════
// POST — save changes
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $title      = trim($_POST['title']       ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $emoji      = mb_substr(trim($_POST['image_emoji'] ?? '🎁'), 0, 4, 'UTF-8');
    $category   = $_POST['category']         ?? 'general';
    $cost       = max(1, (int)($_POST['token_cost'] ?? 50));
    $stockRaw   = trim($_POST['stock']       ?? '');
    $stock      = ($stockRaw === '') ? null : max(0, (int)$stockRaw);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $couponCode = trim($_POST['coupon_code'] ?? '');

    $allowed = ['voucher', 'leave', 'merch', 'perk', 'general'];
    if (!in_array($category, $allowed, true)) $category = 'general';

    if (empty($title)) {
        setFlash('error', 'กรุณากรอกชื่อรางวัล');
        redirect(BASE_URL . '/admin/rewards/edit.php?id=' . $rewardId);
    }

    try {
        $pdo->prepare("
            UPDATE dbo.rewards
            SET title = ?, description = ?, image_emoji = ?,
                category = ?, token_cost = ?, stock = ?, is_active = ?,
                coupon_code = ?
            WHERE reward_id = ?
        ")->execute([$title, $desc, $emoji, $category, $cost, $stock, $isActive,
                     $couponCode ?: null, $rewardId]);
        setFlash('success', 'แก้ไขรางวัล "' . $title . '" เรียบร้อยแล้ว');
    } catch (Throwable $e) {
        error_log('[MissionToken] edit reward error: ' . $e->getMessage());
        setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        redirect(BASE_URL . '/admin/rewards/edit.php?id=' . $rewardId);
    }
    redirect(BASE_URL . '/admin/rewards/index.php');
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
    redirect(BASE_URL . '/admin/rewards/index.php');
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
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
/* ── Admin Reward Edit  prefix: are- ────────────────────── */
.are-label { font-size: 0.70rem; font-weight: 700; color: #4a4e57;
             letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 0.35rem; display: block; }

.are-wrap .journal-input {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.12);
    color: #eeebe1;
}
.are-wrap .journal-input:focus {
    border-color: rgba(218,185,55,0.45);
    background: rgba(255,255,255,0.09);
}
.are-wrap .journal-input::placeholder { color: #3a3e43; }
.are-wrap select.journal-input option { background: #1a1e22; color: #eeebe1; }

.are-active-toggle {
    width: 48px; height: 28px;
    background: rgba(255,255,255,0.08); border-radius: 999px;
    position: relative; cursor: pointer; display: inline-block; flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.14); transition: background 0.2s, border-color 0.2s;
}
.are-active-toggle.on  { background: rgba(81,142,92,0.40); border-color: rgba(81,142,92,0.60); }
.are-active-toggle::after {
    content: '';
    position: absolute; top: 4px; left: 4px;
    width: 18px; height: 18px; border-radius: 50%;
    background: rgba(255,255,255,0.45); transition: transform 0.2s;
}
.are-active-toggle.on::after { transform: translateX(20px); background: #7ec98a; }
</style>

<div class="ar-rewards-wrap are-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

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

    <div style="position:relative; z-index:1; max-width:52rem; margin:0 auto; padding:2.5rem 1.5rem 5rem;">

        <!-- Breadcrumb + page header -->
        <div style="margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);">
            <div style="margin-bottom:0.6rem;">
                <a href="<?php echo BASE_URL; ?>/admin/rewards/index.php"
                   style="font-size:0.72rem; font-weight:600; color:#4a4e57; text-decoration:none;
                          letter-spacing:0.06em; text-transform:uppercase; transition:color 0.15s;"
                   onmouseover="this.style.color='#dab937'" onmouseout="this.style.color='#4a4e57'">
                    ← จัดการรางวัล
                </a>
            </div>
            <p style="font-size:0.55rem; font-weight:700; letter-spacing:0.40em;
                      text-transform:uppercase; color:rgba(218,185,55,0.60); margin:0 0 0.5rem;">
                ⬡ &nbsp;ADMIN — EDIT REWARD
            </p>
            <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0; letter-spacing:-0.02em;">
                แก้ไขรางวัล
            </h1>
            <?php if ($reward): ?>
            <p style="font-size:0.82rem; color:#6b6e77; margin:0.3rem 0 0;">
                <?= e($reward['image_emoji'] ?: '🎁') ?> <?= e($reward['title']) ?>
            </p>
            <?php endif; ?>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.85rem 1.1rem; font-size:0.85rem;
                    <?= $flash['type'] === 'success'
                        ? 'background:rgba(81,142,92,0.12); border:1px solid rgba(81,142,92,0.28); color:#7ec98a;'
                        : 'background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28); color:#d2592a;' ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <?php if ($dataError): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.85rem 1.1rem; font-size:0.85rem;
                    background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28); color:#d2592a;">
            <?= e($dataError) ?>
        </div>
        <?php else: ?>

        <!-- Edit form -->
        <form method="POST" action="">
            <?php echo csrfField(); ?>

            <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                        border-radius:20px; padding:2rem; backdrop-filter:blur(12px);">

                <!-- Emoji + title -->
                <div style="display:grid; grid-template-columns:90px 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                    <div>
                        <label class="are-label">Emoji</label>
                        <input type="text" name="image_emoji"
                               value="<?= e($reward['image_emoji'] ?: '🎁') ?>"
                               maxlength="4" class="journal-input"
                               style="font-size:1.8rem; text-align:center; padding:0.4rem;">
                    </div>
                    <div>
                        <label class="are-label">ชื่อรางวัล <span style="color:#d2592a;">*</span></label>
                        <input type="text" name="title" required maxlength="200"
                               value="<?= e($reward['title']) ?>"
                               placeholder="ชื่อรางวัล"
                               class="journal-input">
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom:1.25rem;">
                    <label class="are-label">คำอธิบาย</label>
                    <textarea name="description" rows="3" maxlength="500"
                              placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                              class="journal-input" style="resize:vertical;"><?= e($reward['description'] ?? '') ?></textarea>
                </div>

                <!-- Category + cost + stock -->
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1.25rem; margin-bottom:1.5rem;">
                    <div>
                        <label class="are-label">หมวดหมู่</label>
                        <select name="category" class="journal-input">
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
                        <span style="font-size:0.68rem; color:#3a3e43; margin-top:0.25rem; display:block;">เว้นว่าง = ไม่จำกัด</span>
                    </div>
                </div>

                <!-- Coupon code -->
                <div style="margin-bottom:1.25rem; padding:1rem 1.25rem;
                            background:rgba(218,185,55,0.04); border-radius:12px;
                            border:1px solid rgba(218,185,55,0.12);">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                        <span style="font-size:1rem;">🔑</span>
                        <div>
                            <p style="font-size:0.82rem; font-weight:700; color:#eeebe1; margin:0;">รหัสคูปอง / โค้ดส่วนลด</p>
                            <p style="font-size:0.72rem; color:#6b6e77; margin:0;">
                                พนักงานจะเห็นโค้ดนี้เมื่อ HR ยืนยันมอบรางวัลแล้วเท่านั้น
                            </p>
                        </div>
                    </div>
                    <label class="are-label">โค้ด <span style="font-weight:400; color:#3a3e43; text-transform:none;">(ไม่บังคับ)</span></label>
                    <input type="text" name="coupon_code" maxlength="200"
                           value="<?= e($reward['coupon_code'] ?? '') ?>"
                           placeholder="เช่น COFFEE2026, LEAVE-MAY, DISCOUNT50"
                           class="journal-input"
                           style="font-family:monospace, 'Prompt'; letter-spacing:0.05em;">
                    <p style="font-size:0.68rem; color:#3a3e43; margin-top:0.3rem;">
                        เว้นว่างถ้ารางวัลนี้ไม่ใช้ระบบโค้ด
                    </p>
                </div>

                <!-- Active toggle -->
                <div style="display:flex; align-items:center; gap:1rem; padding:1rem 1.25rem;
                            background:rgba(255,255,255,0.02); border-radius:12px;
                            border:1px solid rgba(255,255,255,0.06); margin-bottom:1.75rem;">
                    <div style="flex:1;">
                        <p style="font-size:0.87rem; font-weight:600; color:#eeebe1; margin:0 0 0.18rem;">
                            สถานะรางวัล
                        </p>
                        <p style="font-size:0.75rem; color:#6b6e77; margin:0;">
                            เมื่อปิดรางวัล พนักงานจะไม่เห็นรางวัลนี้ในร้านค้า
                        </p>
                    </div>
                    <label style="display:flex; align-items:center; gap:0.65rem; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="is_active" id="is_active_cb"
                               <?= $reward['is_active'] ? 'checked' : '' ?>
                               style="display:none;"
                               onchange="var t=document.getElementById('active-toggle');
                                         var lb=document.getElementById('active-label');
                                         t.classList.toggle('on', this.checked);
                                         lb.textContent = this.checked ? 'เปิดใช้งาน' : 'ปิดการใช้งาน';
                                         lb.style.color  = this.checked ? '#7ec98a'    : '#3a3e43';">
                        <span id="active-toggle"
                              class="are-active-toggle <?= $reward['is_active'] ? 'on' : '' ?>"
                              onclick="var cb=document.getElementById('is_active_cb');
                                       cb.checked=!cb.checked;
                                       cb.dispatchEvent(new Event('change'));">
                        </span>
                        <span id="active-label"
                              style="font-size:0.85rem; font-weight:600;
                                     color:<?= $reward['is_active'] ? '#7ec98a' : '#3a3e43' ?>;">
                            <?= $reward['is_active'] ? 'เปิดใช้งาน' : 'ปิดการใช้งาน' ?>
                        </span>
                    </label>
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                    <a href="<?php echo BASE_URL; ?>/admin/rewards/index.php"
                       style="display:inline-flex; align-items:center; padding:0.6rem 1.25rem;
                              font-size:0.85rem; font-weight:600; border-radius:10px;
                              font-family:'Prompt',sans-serif; text-decoration:none; transition:background 0.15s;
                              background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                              color:#eeebe1;"
                       onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                       onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                        ยกเลิก
                    </a>
                    <button type="submit" class="ch-btn-start"
                            style="padding:0.6rem 1.5rem; font-size:0.85rem; border-radius:10px;">
                        💾 บันทึกการแก้ไข
                    </button>
                </div>
            </div>
        </form>

        <?php endif; ?>

    </div><!-- /inner -->
</div><!-- /ar-rewards-wrap -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
