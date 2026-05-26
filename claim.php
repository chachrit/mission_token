<?php
/**
 * claim.php
 * Employee — scan QR code and claim tokens
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$employeeId = (int)$_SESSION['employee_id'];
$code       = trim((string)($_GET['code'] ?? ''));

$qr      = null;
$claimed = false;
$error   = null;
$result  = null;

if ($code === '') {
    $error = 'ไม่พบรหัส QR Code กรุณาสแกนใหม่อีกครั้ง';
} else {
    $qr = getQrCode($code);
    if (!$qr) {
        $error = 'QR Code นี้ไม่ถูกต้องหรือไม่มีอยู่ในระบบ';
    } elseif (!(bool)$qr['is_active']) {
        $error = 'QR Code นี้ถูกปิดใช้งานแล้ว';
    } elseif ($qr['expires_at'] !== null && strtotime($qr['expires_at']) < time()) {
        $error = 'QR Code นี้หมดอายุแล้ว';
    } elseif ($qr['max_uses'] !== null && (int)$qr['used_count'] >= (int)$qr['max_uses']) {
        $error = 'QR Code นี้ถูกใช้ครบจำนวนแล้ว';
    } else {
        // Check if already claimed
        $claimed = hasClaimedQr((int)$qr['qr_id'], $employeeId);
    }
}

// ── POST: confirm claim ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $qr && !$claimed && !$error) {
    validateCsrf();
    $result = claimQrToken((int)$qr['qr_id'], $employeeId);
    if ($result['ok']) {
        $claimed = true;
        // Refresh session balance
        $_SESSION['token_balance'] = getWalletBalance($employeeId);
    } else {
        $error = $result['message'];
    }
}

$pageTitle  = 'รับ Token';
$activePage = '';
require_once __DIR__ . '/includes/header.php';
?>

<style>
body:has(.cl-wrap) { background-color: #091113; }

.cl-wrap {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.cl-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(218,185,55,0.18);
    border-radius: 1.25rem;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 480px;
    text-align: center;
    position: relative;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
}

.cl-token-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.25rem;
    display: block;
}

.cl-amount {
    font-size: 3.5rem;
    font-weight: 800;
    color: #dab937;
    line-height: 1;
    margin-bottom: 0.25rem;
    letter-spacing: -1px;
}

.cl-amount-label {
    font-size: 1rem;
    color: rgba(238,235,225,0.6);
    margin-bottom: 1.5rem;
}

.cl-label {
    font-size: 1.25rem;
    font-weight: 600;
    color: #eeebe1;
    margin-bottom: 0.5rem;
}

.cl-meta {
    font-size: 0.8rem;
    color: rgba(238,235,225,0.4);
    margin-bottom: 1.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.cl-meta-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.cl-btn-claim {
    width: 100%;
    padding: 0.9rem 1.5rem;
    background: linear-gradient(135deg, #dab937 0%, #c9a830 100%);
    color: #091113;
    font-weight: 700;
    font-size: 1.05rem;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
}
.cl-btn-claim:hover { opacity: 0.9; transform: translateY(-1px); }
.cl-btn-claim:active { transform: translateY(0); }
.cl-btn-claim:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

.cl-success-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.25rem;
    background: rgba(81,142,92,0.2);
    border: 1px solid rgba(81,142,92,0.4);
    border-radius: 2rem;
    color: #6eca7e;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 1.25rem;
}

.cl-error-box {
    background: rgba(210,89,42,0.15);
    border: 1px solid rgba(210,89,42,0.35);
    border-radius: 0.75rem;
    color: #f08060;
    padding: 1rem 1.25rem;
    font-size: 0.9rem;
    margin-bottom: 1.25rem;
}

.cl-already-box {
    background: rgba(79,139,152,0.15);
    border: 1px solid rgba(79,139,152,0.35);
    border-radius: 0.75rem;
    color: #7ec8d8;
    padding: 1rem 1.25rem;
    font-size: 0.9rem;
    margin-bottom: 1.25rem;
}

.cl-back-link {
    display: inline-block;
    margin-top: 1.25rem;
    color: rgba(238,235,225,0.4);
    font-size: 0.85rem;
    text-decoration: none;
    transition: color 0.2s;
}
.cl-back-link:hover { color: #dab937; }

/* Confetti burst via CSS keyframe */
@keyframes cl-pop { 0%{transform:scale(0.7);opacity:0} 60%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
.cl-pop { animation: cl-pop 0.4s ease-out; }
</style>

<main class="cl-wrap">
    <div class="cl-card">

        <?php if ($error): ?>
        <!-- Error state -->
        <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="cl-token-icon" style="opacity:0.3;filter:grayscale(1)">
        <div class="cl-error-box">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;margin-right:6px;vertical-align:-2px">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.293 4.293a2 2 0 012.414 0l7 7a2 2 0 010 2.414l-7 7a2 2 0 01-2.414 0l-7-7a2 2 0 010-2.414l7-7z"/>
            </svg>
            <?= e($error) ?>
        </div>
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="cl-back-link">← กลับหน้าแรก</a>

        <?php elseif ($claimed && $result && $result['ok']): ?>
        <!-- Just claimed! -->
        <div class="cl-pop">
            <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="cl-token-icon">
            <div class="cl-amount"><?= formatTokens((int)$result['amount']) ?></div>
            <div class="cl-amount-label">Token</div>
        </div>
        <div class="cl-success-badge">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            รับ Token สำเร็จ!
        </div>
        <div class="cl-label"><?= e($qr['label']) ?></div>
        <p style="color:rgba(238,235,225,0.5);font-size:0.85rem;margin-bottom:1.5rem">
            Token ถูกเพิ่มเข้ากระเป๋าของคุณแล้ว
        </p>
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="cl-btn-claim" style="display:inline-block;text-decoration:none">
            ไปหน้าแรก
        </a>

        <?php elseif ($claimed && !($result && $result['ok'])): ?>
        <!-- Already claimed before this visit -->
        <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="cl-token-icon" style="opacity:0.5">
        <div class="cl-already-box">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;margin-right:6px;vertical-align:-2px">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            คุณได้รับ Token จาก QR Code นี้แล้ว
        </div>
        <div class="cl-label"><?= e($qr['label']) ?></div>
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="cl-back-link">← กลับหน้าแรก</a>

        <?php elseif ($qr): ?>
        <!-- Ready to claim -->
        <img src="<?= BASE_URL ?>/assets/images/token.png" alt="" class="cl-token-icon">
        <div class="cl-amount"><?= formatTokens((int)$qr['token_amount']) ?></div>
        <div class="cl-amount-label">Token</div>
        <div class="cl-label"><?= e($qr['label']) ?></div>
        <div class="cl-meta">
            <?php if ($qr['max_uses'] !== null): ?>
            <span class="cl-meta-item">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-4M9 20H4v-2a4 4 0 015-4m7-4a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                เหลือ <?= (int)$qr['max_uses'] - (int)$qr['used_count'] ?> / <?= (int)$qr['max_uses'] ?> สิทธิ์
            </span>
            <?php endif; ?>
            <?php if ($qr['expires_at'] !== null): ?>
            <span class="cl-meta-item">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                หมดอายุ <?= e(date('d/m/Y H:i', strtotime($qr['expires_at']))) ?> น.
            </span>
            <?php endif; ?>
        </div>

        <form method="POST" id="claim-form">
            <?= csrfField() ?>
            <button type="submit" class="cl-btn-claim" id="claim-btn">
                กดรับ <?= formatTokens((int)$qr['token_amount']) ?> Token
            </button>
        </form>
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="cl-back-link">← กลับหน้าแรก</a>
        <?php endif; ?>

    </div>
</main>

<script>
document.getElementById('claim-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('claim-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'กำลังบันทึก…'; }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
