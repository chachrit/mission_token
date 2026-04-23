<?php
/**
 * admin/rewards/index.php
 * Admin: manage rewards catalogue (create, edit stock, toggle active)
 */

require_once __DIR__ . '/../../includes/admin_check.php';
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

        $allowed = ['voucher','leave','merch','perk','general'];
        if (!in_array($category, $allowed, true)) $category = 'general';
        if (empty($title)) { setFlash('error', 'กรุณากรอกชื่อรางวัล'); redirect(BASE_URL . '/admin/rewards/index.php'); }

        try {
            $pdo->prepare("
                INSERT INTO dbo.rewards (title, description, image_emoji, category, token_cost, stock, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $desc, $emoji, $category, $cost, $stock, $adminId]);
            setFlash('success', 'เพิ่มรางวัล "' . $title . '" เรียบร้อยแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] create reward error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
        redirect(BASE_URL . '/admin/rewards/index.php');
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
        redirect(BASE_URL . '/admin/rewards/index.php');
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
        redirect(BASE_URL . '/admin/rewards/index.php');
    }

    // ── Delete reward ──────────────────────────────────────
    if ($action === 'delete') {
        $rewardId = (int)($_POST['reward_id'] ?? 0);
        try {
            // Soft-delete: just deactivate (keeps FK integrity with redemptions)
            $pdo->prepare("UPDATE dbo.rewards SET is_active = 0 WHERE reward_id = ?")->execute([$rewardId]);
            setFlash('success', 'ปิดการใช้งานรางวัลแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] delete reward error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/admin/rewards/index.php');
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
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .toggle-switch {
        width: 42px; height: 24px;
        background: #cecdcd; border-radius: 999px;
        position: relative; cursor: pointer;
        border: none; transition: background 0.2s;
    }
    .toggle-switch.on { background: #518e5c; }
    .toggle-switch::after {
        content: '';
        position: absolute; top: 3px; left: 3px;
        width: 18px; height: 18px; border-radius: 50%;
        background: #fff; transition: transform 0.2s;
        box-shadow: 0 1px 4px rgba(0,0,0,0.18);
    }
    .toggle-switch.on::after { transform: translateX(18px); }

    .reward-row { border-bottom: 1px solid #ece9e0; }
    .reward-row:last-child { border-bottom: none; }
    .reward-row:hover { background: #faf8f2; }

    /* Inline stock edit */
    .stock-input {
        width: 70px; background: #fff; border: 1.5px solid #cecdcd;
        border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.82rem;
        font-family: 'Prompt', sans-serif; color: #091113;
    }
    .stock-input:focus { border-color: #dab937; outline: none; }

    /* Create form */
    #create-form {
        display: none;
        background: #fdfcdf; border: 1px solid #e6e2d6; border-radius: 16px;
        padding: 1.5rem; margin-bottom: 1.75rem;
    }
    #create-form.open { display: block; }

    .form-label { font-size: 0.78rem; font-weight: 600; color: #3a3e43;
                  letter-spacing: 0.04em; margin-bottom: 0.35rem; display: block; }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Page header -->
    <div style="display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.6rem; font-weight:700; color:#091113; margin:0;">
                🎁 จัดการรางวัล
            </h1>
            <p style="font-size:0.85rem; color:#6b6e77; margin:0.25rem 0 0;">
                เพิ่ม แก้ไข และจัดการสต็อกรางวัลในร้านค้า Token
            </p>
        </div>
        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
            <a href="<?php echo BASE_URL; ?>/admin/rewards/redemptions.php"
               class="btn-outline" style="position:relative;">
                📋 คำขอแลกรางวัล
                <?php if ($pendingRedemptions > 0): ?>
                <span style="position:absolute; top:-6px; right:-6px; width:18px; height:18px;
                              border-radius:50%; background:#d2592a; color:#fff; font-size:0.65rem;
                              font-weight:700; display:flex; align-items:center; justify-content:center;">
                    <?= $pendingRedemptions ?>
                </span>
                <?php endif; ?>
            </a>
            <button onclick="document.getElementById('create-form').classList.toggle('open');
                             this.textContent = document.getElementById('create-form').classList.contains('open')
                                                ? '✕ ปิด' : '+ เพิ่มรางวัลใหม่';"
                    class="btn-gold">
                + เพิ่มรางวัลใหม่
            </button>
        </div>
    </div>

    <?php if ($dataError): ?>
    <div class="mb-6 rounded-xl border px-5 py-4 text-sm"
         style="border-color:#edc3b2; background:#fff1ea; color:#d2592a;">
        <?= e($dataError) ?>
    </div>
    <?php endif; ?>

    <!-- ══ CREATE FORM ══════════════════════════════════════ -->
    <form id="create-form" method="POST" action="">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="create">

        <h3 style="font-size:1rem; font-weight:600; color:#091113; margin:0 0 1.25rem;">
            ✨ เพิ่มรางวัลใหม่
        </h3>

        <div style="display:grid; grid-template-columns:1fr 3fr; gap:1rem; margin-bottom:1rem;">
            <div>
                <label class="form-label">Emoji</label>
                <input type="text" name="image_emoji" value="🎁" maxlength="4"
                       class="journal-input" style="font-size:1.6rem; text-align:center;">
            </div>
            <div>
                <label class="form-label">ชื่อรางวัล <span style="color:#d2592a;">*</span></label>
                <input type="text" name="title" required maxlength="200"
                       placeholder="เช่น คูปองกาแฟ, วันลาพิเศษ..."
                       class="journal-input">
            </div>
        </div>

        <div style="margin-bottom:1rem;">
            <label class="form-label">คำอธิบาย</label>
            <textarea name="description" rows="2" maxlength="500"
                      placeholder="รายละเอียดรางวัล เงื่อนไขการใช้งาน ฯลฯ"
                      class="journal-input" style="resize:vertical;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.25rem;">
            <div>
                <label class="form-label">หมวดหมู่</label>
                <select name="category" class="journal-input">
                    <?php foreach ($catMeta as $k => $m): ?>
                    <option value="<?= e($k) ?>"><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">ราคา (Token)</label>
                <input type="number" name="token_cost" value="50" min="1" max="99999"
                       class="journal-input">
            </div>
            <div>
                <label class="form-label">จำนวนสต็อก</label>
                <input type="number" name="stock" min="0" placeholder="เว้นว่าง = ไม่จำกัด"
                       class="journal-input">
                <span style="font-size:0.7rem; color:#6b6e77;">เว้นว่าง = ไม่จำกัด</span>
            </div>
        </div>

        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
            <button type="button" class="btn-outline"
                    onclick="document.getElementById('create-form').classList.remove('open');
                             document.querySelector('[onclick*=create-form]').textContent='+ เพิ่มรางวัลใหม่';">
                ยกเลิก
            </button>
            <button type="submit" class="btn-gold">บันทึกรางวัล</button>
        </div>
    </form>

    <!-- ══ REWARDS TABLE ═══════════════════════════════════ -->
    <div style="background:#fdfcdf; border:1px solid #e6e2d6; border-radius:16px; overflow:hidden;">

        <!-- Column headers -->
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;
                    gap:1rem; padding:0.75rem 1.25rem;
                    background:#f4f1e8; border-bottom:1px solid #e6e2d6;
                    font-size:0.72rem; font-weight:600; letter-spacing:0.06em;
                    text-transform:uppercase; color:#6b6e77;">
            <span>รางวัล</span>
            <span>หมวด</span>
            <span>ราคา</span>
            <span>สต็อก</span>
            <span>แลกแล้ว</span>
            <span>สถานะ / จัดการ</span>
        </div>

        <?php if (empty($rewards)): ?>
        <div style="padding:3rem; text-align:center; color:#6b6e77;">
            <p style="font-size:1.5rem; margin-bottom:0.5rem;">📭</p>
            <p>ยังไม่มีรางวัล กด "เพิ่มรางวัลใหม่" เพื่อเริ่มต้น</p>
        </div>
        <?php else: ?>
        <?php foreach ($rewards as $rw):
            $cat    = $rw['category'];
            $meta   = $catMeta[$cat] ?? $catMeta['general'];
            $isOn   = (bool)$rw['is_active'];
            $stockDisplay = $rw['stock'] === null ? '∞' : (int)$rw['stock'];
        ?>
        <div class="reward-row"
             style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;
                    gap:1rem; padding:0.9rem 1.25rem; align-items:center;">

            <!-- Reward name + emoji -->
            <div style="display:flex; align-items:center; gap:0.65rem; min-width:0;">
                <span style="font-size:1.6rem; flex-shrink:0;"><?= e($rw['image_emoji']) ?></span>
                <div style="min-width:0;">
                    <p style="font-size:0.88rem; font-weight:500; color:#091113; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($rw['title']) ?>
                    </p>
                    <?php if (!empty($rw['description'])): ?>
                    <p style="font-size:0.72rem; color:#6b6e77; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:240px;">
                        <?= e($rw['description']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category -->
            <span style="font-size:0.72rem; font-weight:600; padding:0.2rem 0.6rem;
                         border-radius:999px; background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;">
                <?= e($meta['label']) ?>
            </span>

            <!-- Cost -->
            <div style="display:flex; align-items:center; gap:0.3rem;">
                <img src="<?php echo BASE_URL; ?>/assets/images/token.png"
                     width="14" height="14" style="object-fit:contain;" alt="">
                <span style="font-size:0.9rem; font-weight:600; color:#091113;">
                    <?= (int)$rw['token_cost'] ?>
                </span>
            </div>

            <!-- Stock (inline edit) -->
            <form method="POST" action="" style="display:flex; gap:0.4rem; align-items:center;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action"    value="update_stock">
                <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                <input type="number" name="stock" min="0"
                       value="<?= $rw['stock'] === null ? '' : (int)$rw['stock'] ?>"
                       placeholder="∞"
                       class="stock-input"
                       title="เว้นว่าง = ไม่จำกัด">
                <button type="submit" title="บันทึกสต็อก"
                        style="background:none; border:none; cursor:pointer; color:#518e5c;
                               font-size:1rem; padding:2px;">✓</button>
            </form>

            <!-- Total redeemed + pending -->
            <div>
                <span style="font-size:0.9rem; font-weight:600; color:#091113;">
                    <?= (int)$rw['total_redeemed'] ?>
                </span>
                <?php if ((int)$rw['pending_count'] > 0): ?>
                <span style="font-size:0.7rem; color:#b45309; margin-left:4px;">
                    (<?= (int)$rw['pending_count'] ?> รอ)
                </span>
                <?php endif; ?>
            </div>

            <!-- Toggle active + actions -->
            <div style="display:flex; align-items:center; gap:0.6rem;">
                <form method="POST" action="" style="display:inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action"    value="toggle">
                    <input type="hidden" name="reward_id" value="<?= (int)$rw['reward_id'] ?>">
                    <button type="submit"
                            class="toggle-switch <?php echo $isOn ? 'on' : ''; ?>"
                            title="<?php echo $isOn ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน'; ?>"
                            aria-label="Toggle reward active status">
                    </button>
                </form>
                <span style="font-size:0.72rem; color:<?php echo $isOn ? '#518e5c' : '#6b6e77'; ?>;">
                    <?php echo $isOn ? 'เปิด' : 'ปิด'; ?>
                </span>
            </div>

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tip -->
    <p style="font-size:0.78rem; color:#6b6e77; margin-top:1rem; text-align:right;">
        💡 แก้ไขสต็อกโดยพิมพ์ตัวเลขแล้วกด ✓ &nbsp;|&nbsp; เว้นว่างสต็อก = ไม่จำกัด
    </p>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
