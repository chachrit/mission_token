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

<style>
/* ── Admin Rewards  prefix: ar- ─────────────────────────── */
.ar-row { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.12s; }
.ar-row:last-child { border-bottom: none; }
.ar-row:hover { background: rgba(218,185,55,0.03); }

/* Toggle switch (shared with challenges page) */
.ac-toggle-switch {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
.ac-toggle-switch input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.ac-toggle-track {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    background: rgba(107,110,119,0.25);
    border: 1px solid rgba(107,110,119,0.35);
    border-radius: 999px;
    transition: background 0.2s, border-color 0.2s;
}
.ac-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 14px;
    height: 14px;
    background: #6b6e77;
    border-radius: 50%;
    transition: transform 0.2s, background 0.2s;
}
.ac-toggle-switch input:checked + .ac-toggle-track {
    background: rgba(81,142,92,0.30);
    border-color: rgba(81,142,92,0.55);
}
.ac-toggle-switch input:checked + .ac-toggle-track .ac-toggle-thumb {
    transform: translateX(16px);
    background: #7ec98a;
}
.ac-toggle-switch:hover .ac-toggle-track {
    border-color: rgba(218,185,55,0.45);
}

#create-form {
    display: none;
    background: rgba(255,255,255,0.025); border: 1px solid rgba(218,185,55,0.18);
    border-radius: 16px; padding: 1.5rem; margin-bottom: 1.75rem;
    backdrop-filter: blur(12px);
}
#create-form.open { display: block; }

.ar-label { font-size: 0.70rem; font-weight: 700; color: #4a4e57;
            letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 0.35rem; display: block; }

.ar-wrap .journal-input {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.12);
    color: #eeebe1;
}
.ar-wrap .journal-input:focus {
    border-color: rgba(218,185,55,0.45);
    background: rgba(255,255,255,0.09);
}
.ar-wrap .journal-input::placeholder { color: #3a3e43; }
.ar-wrap select.journal-input option { background: #1a1e22; color: #eeebe1; }

/* ── Responsive ─────────────────────────────────────────── */
.ar-table-wrap { overflow: hidden; }

@media (max-width: 900px) {
    .ar-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ar-table-header, .ar-row { min-width: 640px; }
}

@media (max-width: 640px) {
    .ar-page-header-row { flex-direction: column; align-items: flex-start; }
    .ar-page-header-row > button {
        width: 100%; box-sizing: border-box;
    }
    .ar-create-grid { grid-template-columns: 1fr !important; }
}
</style>

<div class="ar-rewards-wrap ar-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

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
        <div style="margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);">
            <!-- Row 1: title + create button -->
            <div class="ar-page-header-row" style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:0.75rem;">
                <div>
                    <p style="font-size:0.55rem; font-weight:700; letter-spacing:0.40em;
                              text-transform:uppercase; color:rgba(218,185,55,0.60); margin:0 0 0.5rem;">
                        ⬡ &nbsp;ADMIN — REWARD CATALOGUE
                    </p>
                    <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0 0 0.25rem; letter-spacing:-0.02em;">
                        จัดการรางวัล
                    </h1>
                    <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                        เพิ่ม แก้ไข และจัดการสต็อกรางวัลในร้านค้า Token
                        <?php if (!empty($rewards)): ?>
                        <span style="margin-left:0.4rem; font-size:0.68rem; font-weight:700;
                                     background:rgba(255,255,255,0.07); border-radius:999px;
                                     padding:0.12rem 0.5rem; color:#8a8e97;">
                            <?= count($rewards) ?> รายการ
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <button onclick="document.getElementById('create-form').classList.toggle('open');
                                 this.textContent = document.getElementById('create-form').classList.contains('open')
                                                    ? 'ปิด' : '+ เพิ่มรางวัลใหม่';"
                        class="ch-btn-start" style="padding:0.55rem 1.1rem; font-size:0.82rem; border-radius:10px; flex-shrink:0;">
                    + เพิ่มรางวัลใหม่
                </button>
            </div>
            <!-- Row 2: category filter pills -->
            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                <?php
                $catFilters = ['' => 'ทั้งหมด'] + array_map(fn($m) => $m['label'], $catMeta);
                foreach ($catFilters as $val => $label):
                    $isActiveCat = ($catFilter === $val);
                ?>
                <a href="<?= BASE_URL ?>/hr/rewards/index.php<?= $val ? '?cat=' . $val : '' ?>"
                   style="font-size:0.72rem; font-weight:700; padding:0.28rem 0.75rem;
                          border-radius:999px; text-decoration:none; transition:all 0.15s;
                          background:<?= $isActiveCat ? 'rgba(218,185,55,0.18)' : 'rgba(255,255,255,0.05)' ?>;
                          border:1px solid <?= $isActiveCat ? 'rgba(218,185,55,0.40)' : 'rgba(255,255,255,0.10)' ?>;
                          color:<?= $isActiveCat ? '#dab937' : '#6b6e77' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($dataError): ?>
        <div style="margin-bottom:1.5rem; border-radius:12px; padding:0.85rem 1.1rem; font-size:0.85rem;
                    background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28); color:#d2592a;">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- CREATE FORM -->
        <form id="create-form" method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">

            <div style="display:flex; align-items:center; gap:0.55rem; margin-bottom:1.25rem;">
                <div style="width:4px; height:20px; background:linear-gradient(180deg,#dab937,#c9a830); border-radius:999px;"></div>
                <span style="font-size:0.95rem; font-weight:700; color:#eeebe1;">เพิ่มรางวัลใหม่</span>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="ar-label">ชื่อรางวัล <span style="color:#d2592a;">*</span></label>
                <input type="text" name="title" required maxlength="200"
                       placeholder="เช่น คูปองกาแฟ, วันลาพิเศษ..."
                       class="journal-input">
            </div>

            <div style="margin-bottom:1rem;">
                <label class="ar-label">คำอธิบาย</label>
                <textarea name="description" rows="2" maxlength="500"
                          placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                          class="journal-input" style="resize:vertical;"></textarea>
            </div>

            <div class="ar-create-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.25rem;">
                <div>
                    <label class="ar-label">หมวดหมู่</label>
                    <select name="category" id="ar-create-category" class="journal-input" onchange="arUpdateAutoIcon(this.value)">
                        <?php foreach ($catMeta as $k => $m): ?>
                        <option value="<?= e($k) ?>"><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.28rem; color:#8a8e97;">
                        <span id="ar-auto-icon-preview" style="display:inline-flex; align-items:center;"><?= rewardCategoryIconSvg('voucher') ?></span>
                        <span id="ar-auto-icon-hint" style="font-size:0.68rem;">ไอคอนตามหมวดหมู่: คูปอง</span>
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
                    <span style="font-size:0.68rem; color:#6b6e77; margin-top:0.2rem; display:block;">เว้นว่าง = ไม่จำกัด</span>
                </div>
            </div>

            <div id="create-coupon-wrap" style="margin-bottom:1.25rem; padding:1rem 1.25rem;
                        background:rgba(218,185,55,0.04); border-radius:12px;
                        border:1px solid rgba(218,185,55,0.12); display:none;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
                    <span style="font-size:0.95rem; display:inline-flex; align-items:center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="8" cy="12" r="3" stroke-width="2"/>
                            <path d="M11 12h10M18 12v3M15 12v2" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <label class="ar-label" style="margin:0;">รหัสคูปอง / โค้ดส่วนลด</label>
                    <span style="font-size:0.70rem; color:#6b6e77;">(ไม่บังคับ)</span>
                </div>
                <input type="text" name="coupon_code" id="create_coupon_code" maxlength="200"
                       placeholder="เช่น COFFEE2026, LEAVE-MAY, DISCOUNT50"
                       class="journal-input"
                       style="font-family:monospace, 'Prompt'; letter-spacing:0.05em;"
                       oninput="arCreateToggleExpiry(this.value)">
                <p style="font-size:0.68rem; color:#6b6e77; margin-top:0.3rem;">พนักงานจะเห็นโค้ดนี้หลัง HR ยืนยันมอบรางวัลแล้วเท่านั้น</p>

                <!-- Expiry — shown when coupon code is filled -->
                <div id="create-expiry-wrap" style="margin-top:0.85rem; padding-top:0.85rem;
                     border-top:1px solid rgba(218,185,55,0.10); display:none;">
                    <label class="ar-label">
                        วันหมดอายุคูปอง
                        <span style="font-weight:400; color:#6b6e77; text-transform:none;">(ไม่บังคับ)</span>
                    </label>
                    <input type="datetime-local" name="coupon_expires_at"
                           class="journal-input" style="color-scheme:dark;">
                </div>
            </div>

            <div style="display:flex; gap:0.65rem; justify-content:flex-end;">
                <button type="button"
                        style="padding:0.55rem 1.1rem; font-size:0.82rem; font-weight:600;
                               border-radius:10px; cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                               color:#eeebe1; transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)'"
                        onclick="document.getElementById('create-form').classList.remove('open');
                                 document.querySelector('[onclick*=create-form]').textContent='+ เพิ่มรางวัลใหม่';">
                    ยกเลิก
                </button>
                <button type="submit" class="ch-btn-start"
                        style="padding:0.55rem 1.25rem; font-size:0.85rem; border-radius:10px;">
                    บันทึกรางวัล
                </button>
            </div>
        </form>

        <!-- REWARDS TABLE -->
        <div class="ar-table-wrap" style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; backdrop-filter:blur(8px);">

            <div class="ar-table-header" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 80px 120px;
                        gap:1rem; padding:0.7rem 1.25rem;
                        background:rgba(255,255,255,0.03);
                        border-bottom:1px solid rgba(255,255,255,0.07);
                        font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                        text-transform:uppercase; color: white;">
                <span>รางวัล</span>
                <span>หมวด</span>
                <span>ราคา</span>
                <span>สต็อก</span>
                <span>แลกแล้ว</span>
                <span style="text-align:center;">สถานะ</span>
                <span style="text-align:right;">จัดการ</span>
            </div>

            <?php if (empty($rewards)): ?>
            <div style="padding:3.5rem; text-align:center;">
                <p style="font-size:2rem; margin-bottom:0.5rem; opacity:0.20; display:inline-flex; align-items:center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M3 8h18" stroke-width="2"/>
                        <path d="M4 8l8 6 8-6" stroke-width="2"/>
                        <rect x="3" y="8" width="18" height="12" rx="2" stroke-width="2"/>
                    </svg>
                </p>
                <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ยังไม่มีรางวัล กด "เพิ่มรางวัลใหม่" เพื่อเริ่มต้น</p>
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
                $cat  = $rw['category'];
                $meta = $catMeta[$cat] ?? $catMeta['general'];
                $glow = $glowMap[$cat] ?? $glowMap['general'];
                $isOn = (bool)$rw['is_active'];
            ?>
            <div class="ar-row"
                 style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 80px 120px;
                        gap:1rem; padding:0.9rem 1.25rem; align-items:center;
                        <?= $isOn ? '' : 'opacity:0.45;' ?>">

                <!-- Reward name + emoji -->
                <div style="display:flex; align-items:center; gap:0.65rem; min-width:0;">
                    <span style="display:inline-flex; align-items:center; justify-content:center;
                                 width:30px; height:30px; flex-shrink:0; border-radius:999px; line-height:1;
                                 color:<?= $glow['color'] ?>;
                                 background:<?= $glow['bg'] ?>;
                                 border:1px solid <?= $glow['border'] ?>;">
                        <?= rewardCategoryIconSvg((string)$cat) ?>
                    </span>
                    <div style="min-width:0;">
                        <p style="font-size:0.87rem; font-weight:600; color:#eeebe1; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($rw['title']) ?>
                        </p>
                        <?php if (!empty($rw['description'])): ?>
                        <p style="font-size:0.70rem; color:#6b6e77; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:240px;">
                            <?= e($rw['description']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category -->
                <span style="font-size:0.65rem; font-weight:700; padding:0.2rem 0.6rem;
                             border-radius:999px; white-space:nowrap;
                             background:<?= $glow['bg'] ?>; color:<?= $glow['color'] ?>;
                             border:1px solid <?= $glow['border'] ?>;">
                    <?= e($meta['label']) ?>
                </span>

                <!-- Cost -->
                <div style="display:flex; align-items:center; gap:0.28rem;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                         width="13" height="13" style="object-fit:contain; opacity:0.70;" alt="">
                    <span style="font-size:0.9rem; font-weight:700; color:#f8e769;">
                        <?= (int)$rw['token_cost'] ?>
                    </span>
                </div>

                <!-- Stock (read-only) -->
                <div>
                    <span style="font-size:0.9rem; font-weight:600; color:#eeebe1;">
                        <?= $rw['stock'] === null ? '∞' : (int)$rw['stock'] ?>
                    </span>
                    <span style="font-size:0.68rem; color:#6b6e77; display:block;">
                        <?= $rw['stock'] === null ? 'ไม่จำกัด' : 'คงเหลือ' ?>
                    </span>
                </div>

                <!-- Total redeemed + pending -->
                <div>
                    <span style="font-size:0.9rem; font-weight:600; color:#eeebe1;">
                        <?= (int)$rw['total_redeemed'] ?>
                    </span>
                    <?php if ((int)$rw['pending_count'] > 0): ?>
                    <span style="font-size:0.68rem; color:#fbbf24; margin-left:3px;">
                        (<?= (int)$rw['pending_count'] ?> รอ)
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Toggle active -->
                <div style="text-align:center;">
                    <form method="POST" action="" style="display:inline;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action"    value="toggle">
                        <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                        <label class="ac-toggle-switch" title="<?= $isOn ? 'คลิกเพื่อปิด' : 'คลิกเพื่อเปิด' ?>">
                            <input type="checkbox"
                                   <?= $isOn ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <span class="ac-toggle-track">
                                <span class="ac-toggle-thumb"></span>
                            </span>
                        </label>
                    </form>
                </div>

                <!-- Edit + Delete -->
                <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.5rem;">
                    <a href="<?php echo BASE_URL; ?>/hr/rewards/edit.php?id=<?= (int)$rw['reward_id'] ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              height:30px; box-sizing:border-box;
                              font-size:0.73rem; font-weight:600; padding:0 0.75rem;
                              border-radius:8px; text-decoration:none; transition:background 0.15s;
                              background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.20);
                              color:#dab937; white-space:nowrap;"
                       onmouseover="this.style.background='rgba(218,185,55,0.16)'"
                       onmouseout="this.style.background='rgba(218,185,55,0.08)'">
                        แก้ไข
                    </a>
                    <form method="POST" action="" style="display:inline-flex; margin:0;"
                          onsubmit="return confirm('ลบรางวัล &quot;<?= addslashes(e($rw['title'])) ?>&quot; ?\nรางวัลที่มีประวัติการแลกจะไม่สามารถลบได้')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action"    value="delete">
                        <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                        <button type="submit"
                                style="width:30px; height:30px; box-sizing:border-box;
                                       border-radius:8px; cursor:pointer; margin:0;
                                       background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.20);
                                       color:#d2592a; display:inline-flex; align-items:center;
                                       justify-content:center; transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(210,89,42,0.18)'"
                                onmouseout="this.style.background='rgba(210,89,42,0.08)'"
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
