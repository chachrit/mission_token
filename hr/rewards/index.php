<?php
/**
 * admin/rewards/index.php
 * Admin: manage rewards catalogue (create, edit stock, toggle active)
 */

require_once __DIR__ . '/../../includes/hr_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];
$pdo     = getDB();

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
        $emoji    = mb_substr(trim($_POST['image_emoji'] ?? '🎁'), 0, 4, 'UTF-8');
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

try {
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
} catch (Throwable $e) {
    error_log('[MissionToken] admin rewards load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้';
}

// Pending redemptions count for badge
$pendingRedemptions = 0;
try {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM dbo.reward_redemptions WHERE status = 'pending'")->fetch();
    $pendingRedemptions = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) { /* ignore */ }

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
.ar-toggle {
    width: 42px; height: 24px;
    background: rgba(255,255,255,0.08); border-radius: 999px;
    position: relative; cursor: pointer;
    border: 1px solid rgba(255,255,255,0.14); transition: background 0.2s, border-color 0.2s;
}
.ar-toggle.on { background: rgba(81,142,92,0.40); border-color: rgba(81,142,92,0.60); }
.ar-toggle::after {
    content: '';
    position: absolute; top: 3px; left: 3px;
    width: 16px; height: 16px; border-radius: 50%;
    background: rgba(255,255,255,0.45); transition: transform 0.2s;
}
.ar-toggle.on::after { transform: translateX(18px); background: #7ec98a; }

.ar-row { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.12s; }
.ar-row:last-child { border-bottom: none; }
.ar-row:hover { background: rgba(218,185,55,0.03); }

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
        <div style="display:flex; align-items:flex-start; justify-content:space-between;
                    flex-wrap:wrap; gap:1rem; margin-bottom:2rem;
                    padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);">
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
                </p>
            </div>
            <div style="display:flex; gap:0.65rem; align-items:center; flex-wrap:wrap;">
                <a href="<?php echo BASE_URL; ?>/hr/rewards/redemptions.php"
                   style="display:inline-flex; align-items:center; gap:0.45rem; position:relative;
                          padding:0.55rem 1.1rem; border-radius:10px; font-size:0.82rem; font-weight:600;
                          font-family:'Prompt',sans-serif; text-decoration:none; transition:all 0.18s;
                          background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                          color:#eeebe1;"
                   onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                    📋 คำขอแลกรางวัล
                    <?php if ($pendingRedemptions > 0): ?>
                    <span style="width:18px; height:18px; border-radius:50%; background:#d2592a;
                                  color:#fff; font-size:0.60rem; font-weight:700;
                                  display:inline-flex; align-items:center; justify-content:center;">
                        <?= $pendingRedemptions ?>
                    </span>
                    <?php endif; ?>
                </a>
                <button onclick="document.getElementById('create-form').classList.toggle('open');
                                 this.textContent = document.getElementById('create-form').classList.contains('open')
                                                    ? '✕ ปิด' : '+ เพิ่มรางวัลใหม่';"
                        class="ch-btn-start" style="padding:0.55rem 1.1rem; font-size:0.82rem; border-radius:10px;">
                    + เพิ่มรางวัลใหม่
                </button>
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

            <div style="display:grid; grid-template-columns:80px 1fr; gap:1rem; margin-bottom:1rem;">
                <div>
                    <label class="ar-label">Emoji</label>
                    <input type="text" name="image_emoji" value="🎁" maxlength="4"
                           class="journal-input" style="font-size:1.5rem; text-align:center;">
                </div>
                <div>
                    <label class="ar-label">ชื่อรางวัล <span style="color:#d2592a;">*</span></label>
                    <input type="text" name="title" required maxlength="200"
                           placeholder="เช่น คูปองกาแฟ, วันลาพิเศษ..."
                           class="journal-input">
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="ar-label">คำอธิบาย</label>
                <textarea name="description" rows="2" maxlength="500"
                          placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                          class="journal-input" style="resize:vertical;"></textarea>
            </div>

            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.25rem;">
                <div>
                    <label class="ar-label">หมวดหมู่</label>
                    <select name="category" class="journal-input">
                        <?php foreach ($catMeta as $k => $m): ?>
                        <option value="<?= e($k) ?>"><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
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

            <div style="margin-bottom:1.25rem; padding:1rem 1.25rem;
                        background:rgba(218,185,55,0.04); border-radius:12px;
                        border:1px solid rgba(218,185,55,0.12);">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
                    <span style="font-size:0.95rem;">🔑</span>
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
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">

            <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1.6fr;
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
                <span>สถานะ / จัดการ</span>
            </div>

            <?php if (empty($rewards)): ?>
            <div style="padding:3.5rem; text-align:center;">
                <p style="font-size:2rem; margin-bottom:0.5rem; opacity:0.20;">📭</p>
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
                 style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1.6fr;
                        gap:1rem; padding:0.9rem 1.25rem; align-items:center;
                        <?= $isOn ? '' : 'opacity:0.45;' ?>">

                <!-- Reward name + emoji -->
                <div style="display:flex; align-items:center; gap:0.65rem; min-width:0;">
                    <span style="font-size:1.55rem; flex-shrink:0; line-height:1;"><?= e($rw['image_emoji'] ?: '🎁') ?></span>
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

                <!-- Toggle active + edit link -->
                <div style="display:flex; flex-direction:column; gap:0.4rem; align-items:flex-start;">
                    <!-- Row 1: Toggle -->
                    <form method="POST" action=""
                          style="display:inline-flex; align-items:center; gap:0.28rem;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action"    value="toggle">
                        <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                        <button type="submit"
                                class="ar-toggle <?= $isOn ? 'on' : '' ?>"
                                title="<?= $isOn ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน' ?>"
                                aria-label="Toggle reward active status">
                        </button>
                        <span style="font-size:0.65rem; line-height:1; color:<?= $isOn ? '#7ec98a' : '#3a3e43' ?>; white-space:nowrap;">
                            <?= $isOn ? 'เปิด' : 'ปิด' ?>
                        </span>
                    </form>
                    <!-- Row 2: Edit + Delete -->
                    <div style="display:flex; align-items:stretch; gap:0.4rem;">
                        <a href="<?php echo BASE_URL; ?>/hr/rewards/edit.php?id=<?= (int)$rw['reward_id'] ?>"
                           style="display:inline-flex; align-items:center; justify-content:center;
                                  height:24px; min-height:24px; box-sizing:border-box;
                                  font-size:0.68rem; padding:0 0.6rem; border-radius:6px;
                                  background:rgba(218,185,55,0.10); color:#dab937;
                                  border:1px solid rgba(218,185,55,0.25);
                                  font-family:'Prompt',sans-serif; font-weight:600;
                                  text-decoration:none; white-space:nowrap; line-height:1;
                                  transition:background 0.15s;"
                           onmouseover="this.style.background='rgba(218,185,55,0.20)'"
                           onmouseout="this.style.background='rgba(218,185,55,0.10)'">
                            ✏ แก้ไข
                        </a>
                        <form method="POST" action=""
                              style="display:inline-flex; margin:0; padding:0;"
                              onsubmit="return confirm('ลบรางวัล &quot;<?= addslashes(e($rw['title'])) ?>&quot; ?\nรางวัลที่มีประวัติการแลกจะไม่สามารถลบได้')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action"    value="delete">
                            <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                            <button type="submit"
                                    style="display:inline-flex; align-items:center; justify-content:center;
                                           height:24px; min-height:24px; box-sizing:border-box;
                                           font-size:0.68rem; padding:0 0.6rem; border-radius:6px;
                                           background:rgba(210,89,42,0.10); color:#d2592a;
                                           border:1px solid rgba(210,89,42,0.28);
                                           font-family:'Prompt',sans-serif; font-weight:600;
                                           cursor:pointer; white-space:nowrap; line-height:1;
                                           transition:background 0.15s; margin:0;"
                                    onmouseover="this.style.background='rgba(210,89,42,0.22)'"
                                    onmouseout="this.style.background='rgba(210,89,42,0.10)'">
                                🗑 ลบ
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /inner -->
</div><!-- /ar-rewards-wrap -->

<script>
function arCreateToggleExpiry(val) {
    var wrap = document.getElementById('create-expiry-wrap');
    if (wrap) wrap.style.display = val.trim() !== '' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
