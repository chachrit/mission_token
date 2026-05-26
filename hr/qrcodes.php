<?php
/**
 * hr/qrcodes.php
 * HR/Admin — manage QR Code token vouchers
 */

require_once __DIR__ . '/../includes/hr_check.php';
require_once __DIR__ . '/../includes/functions.php';

$adminId   = (int)$_SESSION['employee_id'];
$canManage = in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true);
$pdo       = getDB();

// ── POST: create / toggle active ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if (!$canManage) {
        setFlash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect(BASE_URL . '/hr/qrcodes.php');
    }

    $action = (string)($_POST['action'] ?? '');

    // ── Create QR ──────────────────────────────────────────
    if ($action === 'create') {
        $label      = trim((string)($_POST['label'] ?? ''));
        $amount     = (int)($_POST['token_amount'] ?? 0);
        $maxUses    = trim((string)($_POST['max_uses'] ?? ''));
        $perUser    = max(1, (int)($_POST['per_user_limit'] ?? 1));
        $expiresRaw = trim((string)($_POST['expires_at'] ?? ''));

        if ($label === '' || $amount < 1) {
            setFlash('error', 'กรุณากรอกชื่อและจำนวน Token ให้ถูกต้อง');
        } else {
            $maxUsesVal   = ($maxUses !== '' && (int)$maxUses > 0) ? (int)$maxUses : null;
            $expiresVal   = ($expiresRaw !== '') ? $expiresRaw : null;
            $code         = bin2hex(random_bytes(32)); // 64 hex chars

            $pdo->prepare("
                INSERT INTO dbo.token_qr_codes
                    (code, label, token_amount, max_uses, per_user_limit, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$code, $label, $amount, $maxUsesVal, $perUser, $expiresVal, $adminId]);

            setFlash('success', 'สร้าง QR Code สำเร็จ');
        }
        redirect(BASE_URL . '/hr/qrcodes.php');
    }

    // ── Toggle active ──────────────────────────────────────
    if ($action === 'toggle') {
        $qrId = (int)($_POST['qr_id'] ?? 0);
        if ($qrId > 0) {
            $pdo->prepare("
                UPDATE dbo.token_qr_codes
                SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
                WHERE qr_id = ?
            ")->execute([$qrId]);
            setFlash('success', 'อัปเดตสถานะ QR Code แล้ว');
        }
        redirect(BASE_URL . '/hr/qrcodes.php');
    }
}

// ── Load all QR codes ────────────────────────────────────────
$qrCodes = getAllQrCodes();
$flash   = getFlash();

$pageTitle  = 'QR Token';
$activePage = 'admin_qrcodes';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
body:has(.qr-wrap) { background-color: #091113; }

.qr-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1.25rem 4rem;
}

.qr-page-header {
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.qr-page-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #eeebe1;
}
.qr-page-subtitle {
    color: rgba(238,235,225,0.45);
    font-size: 0.875rem;
    margin-top: 0.2rem;
}

/* Create form card */
.qr-create-card {
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(218,185,55,0.2);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.qr-create-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #dab937;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.qr-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.qr-form-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(238,235,225,0.55);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.4rem;
}
.qr-input {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 0.5rem;
    color: #eeebe1;
    padding: 0.6rem 0.9rem;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
    font-family: inherit;
    box-sizing: border-box;
}
.qr-input:focus { border-color: #dab937; }
.qr-input::placeholder { color: rgba(238,235,225,0.25); }
.qr-input-hint {
    font-size: 0.72rem;
    color: rgba(238,235,225,0.3);
    margin-top: 0.3rem;
}
.qr-btn-create {
    padding: 0.65rem 1.5rem;
    background: linear-gradient(135deg,#dab937 0%,#c9a830 100%);
    color: #091113;
    font-weight: 700;
    font-size: 0.9rem;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: opacity 0.2s;
}
.qr-btn-create:hover { opacity: 0.88; }

/* Table */
.qr-table-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem;
    overflow: hidden;
}
.qr-table-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.qr-table-header-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: rgba(238,235,225,0.55);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.qr-table-empty {
    padding: 3rem;
    text-align: center;
    color: rgba(238,235,225,0.3);
    font-size: 0.9rem;
}

.qr-list { list-style: none; margin: 0; padding: 0; }
.qr-item {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    align-items: center;
}
.qr-item:last-child { border-bottom: none; }

.qr-item-main {}
.qr-item-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #eeebe1;
    margin-bottom: 0.25rem;
}
.qr-item-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem;
    font-size: 0.78rem;
    color: rgba(238,235,225,0.4);
}
.qr-item-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    background: rgba(255,255,255,0.06);
    border-radius: 2rem;
    font-size: 0.75rem;
}
.qr-item-amount {
    font-size: 1.35rem;
    font-weight: 800;
    color: #dab937;
    text-align: right;
    white-space: nowrap;
}
.qr-item-amount-label {
    font-size: 0.7rem;
    color: rgba(218,185,55,0.55);
}

.qr-item-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.qr-status-active {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.7rem;
    background: rgba(81,142,92,0.18);
    border: 1px solid rgba(81,142,92,0.35);
    border-radius: 2rem;
    color: #6eca7e;
    font-size: 0.75rem;
    font-weight: 600;
}
.qr-status-inactive {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.7rem;
    background: rgba(107,110,119,0.15);
    border: 1px solid rgba(107,110,119,0.3);
    border-radius: 2rem;
    color: rgba(238,235,225,0.35);
    font-size: 0.75rem;
    font-weight: 600;
}
.qr-dot { width:6px;height:6px;border-radius:50%;display:inline-block; }
.qr-dot-green { background:#6eca7e; }
.qr-dot-gray  { background:rgba(238,235,225,0.2); }

.qr-btn-toggle {
    padding: 0.4rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 0.4rem;
    border: 1px solid rgba(255,255,255,0.15);
    background: transparent;
    color: rgba(238,235,225,0.55);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.qr-btn-toggle:hover { background: rgba(255,255,255,0.07); color: #eeebe1; }

.qr-btn-qr {
    padding: 0.4rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 0.4rem;
    border: 1px solid rgba(218,185,55,0.3);
    background: transparent;
    color: #dab937;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.qr-btn-qr:hover { background: rgba(218,185,55,0.1); }

/* QR Preview Modal */
.qr-modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.qr-modal-overlay.open { display: flex; }
.qr-modal {
    background: #13191c;
    border: 1px solid rgba(218,185,55,0.3);
    border-radius: 1.25rem;
    padding: 2rem;
    max-width: 420px;
    width: 100%;
    text-align: center;
}
.qr-modal-label {
    font-size: 1.1rem;
    font-weight: 700;
    color: #eeebe1;
    margin-bottom: 0.35rem;
}
.qr-modal-amount {
    font-size: 1.5rem;
    font-weight: 800;
    color: #dab937;
    margin-bottom: 1.25rem;
}
.qr-modal-img {
    width: 220px;
    height: 220px;
    border-radius: 0.75rem;
    border: 3px solid rgba(218,185,55,0.25);
    margin: 0 auto 1.25rem;
    display: block;
    background: #fff;
}
.qr-modal-url {
    font-size: 0.72rem;
    color: rgba(238,235,225,0.35);
    word-break: break-all;
    margin-bottom: 1.25rem;
    padding: 0.5rem;
    background: rgba(255,255,255,0.04);
    border-radius: 0.4rem;
}
.qr-modal-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}
.qr-modal-btn-dl {
    padding: 0.6rem 1.25rem;
    background: linear-gradient(135deg,#dab937,#c9a830);
    color: #091113;
    font-weight: 700;
    font-size: 0.85rem;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.qr-modal-btn-close {
    padding: 0.6rem 1.25rem;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(238,235,225,0.6);
    font-size: 0.85rem;
    border-radius: 0.5rem;
    cursor: pointer;
}
</style>

<div class="qr-wrap">

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-900/30 border border-green-700/40 text-green-400' : 'bg-red-900/25 border border-red-700/40 text-red-400' ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="qr-page-header">
        <div>
            <div class="qr-page-title">QR Token Voucher</div>
            <div class="qr-page-subtitle">สร้าง QR Code สำหรับแจก Token ในกิจกรรม / อีเวนต์</div>
        </div>
        <span style="color:rgba(238,235,225,0.3);font-size:0.8rem"><?= count($qrCodes) ?> รายการทั้งหมด</span>
    </div>

    <?php if ($canManage): ?>
    <!-- Create Form -->
    <div class="qr-create-card">
        <div class="qr-create-title">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            สร้าง QR Code ใหม่
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="qr-form-grid">
                <div class="qr-form-group" style="grid-column: span 2; min-width:0">
                    <label for="qr-label">ชื่อ / ชื่อกิจกรรม *</label>
                    <input id="qr-label" name="label" type="text" class="qr-input"
                           placeholder="เช่น วันเกิดองค์กร ครบ 10 ปี" maxlength="200" required>
                </div>
                <div class="qr-form-group">
                    <label for="qr-amount">จำนวน Token *</label>
                    <input id="qr-amount" name="token_amount" type="number" class="qr-input"
                           placeholder="50" min="1" max="99999" required>
                </div>
                <div class="qr-form-group">
                    <label for="qr-max">จำนวนสิทธิ์สูงสุด</label>
                    <input id="qr-max" name="max_uses" type="number" class="qr-input"
                           placeholder="ไม่จำกัด" min="1">
                    <div class="qr-input-hint">ว่างเปล่า = ไม่จำกัด</div>
                </div>
                <div class="qr-form-group">
                    <label for="qr-expires">วันหมดอายุ</label>
                    <input id="qr-expires" name="expires_at" type="datetime-local" class="qr-input">
                    <div class="qr-input-hint">ว่างเปล่า = ไม่มีวันหมดอายุ</div>
                </div>
            </div>
            <button type="submit" class="qr-btn-create">สร้าง QR Code</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- List -->
    <div class="qr-table-card">
        <div class="qr-table-header">
            <span class="qr-table-header-title">QR Codes ทั้งหมด</span>
        </div>

        <?php if (empty($qrCodes)): ?>
        <div class="qr-table-empty">ยังไม่มี QR Code — สร้างรายการแรกได้เลย</div>
        <?php else: ?>
        <ul class="qr-list">
            <?php foreach ($qrCodes as $qr):
                $isActive  = (bool)$qr['is_active'];
                $isExpired = $qr['expires_at'] !== null && strtotime($qr['expires_at']) < time();
                $isFull    = $qr['max_uses'] !== null && (int)$qr['used_count'] >= (int)$qr['max_uses'];
                $claimUrl  = BASE_URL . '/claim.php?code=' . rawurlencode($qr['code']);
                $qrApiUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . rawurlencode($claimUrl);
            ?>
            <li class="qr-item" data-url="<?= e($claimUrl) ?>"
                data-label="<?= e($qr['label']) ?>"
                data-amount="<?= (int)$qr['token_amount'] ?>"
                data-qrapi="<?= e($qrApiUrl) ?>">

                <div class="qr-item-main">
                    <div class="qr-item-label"><?= e($qr['label']) ?></div>
                    <div class="qr-item-meta">
                        <?php if ($isActive && !$isExpired && !$isFull): ?>
                        <span class="qr-status-active"><span class="qr-dot qr-dot-green"></span>เปิดใช้งาน</span>
                        <?php elseif ($isExpired): ?>
                        <span class="qr-status-inactive"><span class="qr-dot qr-dot-gray"></span>หมดอายุ</span>
                        <?php elseif ($isFull): ?>
                        <span class="qr-status-inactive"><span class="qr-dot qr-dot-gray"></span>ใช้ครบแล้ว</span>
                        <?php else: ?>
                        <span class="qr-status-inactive"><span class="qr-dot qr-dot-gray"></span>ปิดอยู่</span>
                        <?php endif; ?>

                        <span class="qr-item-meta-chip">
                            <?php if ($qr['max_uses'] !== null): ?>
                            <?= (int)$qr['used_count'] ?> / <?= (int)$qr['max_uses'] ?> สิทธิ์
                            <?php else: ?>
                            ใช้แล้ว <?= (int)$qr['used_count'] ?> ครั้ง
                            <?php endif; ?>
                        </span>

                        <?php if ($qr['expires_at'] !== null): ?>
                        <span class="qr-item-meta-chip">
                            หมดอายุ <?= e(date('d/m/Y', strtotime($qr['expires_at']))) ?>
                        </span>
                        <?php endif; ?>

                        <span class="qr-item-meta-chip" style="opacity:0.6">
                            สร้างโดย <?= e($qr['created_by_name'] ?? 'ไม่ทราบ') ?>
                            · <?= e(date('d/m/Y', strtotime($qr['created_at']))) ?>
                        </span>
                    </div>
                </div>

                <div class="qr-item-amount">
                    <?= formatTokens((int)$qr['token_amount']) ?>
                    <div class="qr-item-amount-label">Token</div>
                </div>

                <div class="qr-item-actions">
                    <button type="button" class="qr-btn-qr js-show-qr" title="ดู QR Code">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2"/>
                            <rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2"/>
                            <rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2"/>
                            <path d="M14 14h3v3h-3zM17 17h3M17 14v3M14 17v3M14 20h3" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        QR
                    </button>

                    <?php if ($canManage): ?>
                    <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="qr_id" value="<?= (int)$qr['qr_id'] ?>">
                        <button type="submit" class="qr-btn-toggle">
                            <?= $isActive ? 'ปิด' : 'เปิด' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

</div>

<!-- QR Modal -->
<div class="qr-modal-overlay" id="qr-modal" role="dialog" aria-modal="true" aria-label="QR Code">
    <div class="qr-modal">
        <div class="qr-modal-label" id="qr-modal-label"></div>
        <div class="qr-modal-amount" id="qr-modal-amount"></div>
        <img id="qr-modal-img" class="qr-modal-img" src="" alt="QR Code" width="220" height="220">
        <div class="qr-modal-url" id="qr-modal-url"></div>
        <div class="qr-modal-actions">
            <a id="qr-modal-dl" class="qr-modal-btn-dl" href="#" download="qr_token.png" target="_blank">
                ดาวน์โหลด PNG
            </a>
            <button class="qr-modal-btn-close" id="qr-modal-close">ปิด</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('qr-modal');
    const modalImg  = document.getElementById('qr-modal-img');
    const modalLbl  = document.getElementById('qr-modal-label');
    const modalAmt  = document.getElementById('qr-modal-amount');
    const modalUrl  = document.getElementById('qr-modal-url');
    const modalDl   = document.getElementById('qr-modal-dl');
    const btnClose  = document.getElementById('qr-modal-close');

    function openModal(label, amount, url, qrApiUrl) {
        modalLbl.textContent  = label;
        modalAmt.textContent  = amount + ' Token';
        modalUrl.textContent  = url;
        modalImg.src          = qrApiUrl;
        modalDl.href          = qrApiUrl;
        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    document.querySelectorAll('.js-show-qr').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('[data-url]');
            openModal(
                row.dataset.label,
                row.dataset.amount,
                row.dataset.url,
                row.dataset.qrapi
            );
        });
    });

    btnClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
