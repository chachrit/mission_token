<?php
/**
 * pages/history.php
 * Employee: full token transaction history + redemption history with coupon reveal
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$employeeId = (int)$_SESSION['employee_id'];
$pdo        = getDB();

// ══════════════════════════════════════════════════════════════
// LOAD DATA
// ══════════════════════════════════════════════════════════════
$transactions  = [];
$redemptions   = [];
$wallet        = getWalletInfo($employeeId);
$dataError     = null;

try {
    // Token transactions
    $transactions = $pdo->prepare("
        SELECT tx_id, amount, tx_type, note, created_at
        FROM   dbo.token_transactions
        WHERE  employee_id = ?
        ORDER BY created_at DESC
    ");
    $transactions->execute([$employeeId]);
    $transactions = $transactions->fetchAll();

    // Redemption history with coupon_code
    $redemptions = $pdo->prepare("
        SELECT rd.redemption_id, rd.tokens_spent, rd.status,
               rd.redeemed_at, rd.processed_at, rd.admin_note,
               rw.title      AS reward_title,
               rw.image_emoji,
               rw.category,
               rw.coupon_code
        FROM   dbo.reward_redemptions rd
        JOIN   dbo.rewards            rw ON rw.reward_id = rd.reward_id
        WHERE  rd.employee_id = ?
        ORDER BY rd.redeemed_at DESC
    ");
    $redemptions->execute([$employeeId]);
    $redemptions = $redemptions->fetchAll();

} catch (Throwable $e) {
    error_log('[MissionToken] history page load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่';
}

$pageTitle  = 'ประวัติการทำรายการ';
$activePage = 'history';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════════
     DARK BACKGROUND OVERRIDE (via style.css body:has)
══════════════════════════════════════════════════════════ -->
<div class="hy-history-wrap">

    <!-- Aurora blobs -->
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div style="position:relative; z-index:1; max-width:860px; margin:0 auto;
                padding:2rem 1.25rem 4rem;">

        <!-- Page header -->
        <div style="margin-bottom:2rem;">
            <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.3rem;">
                <a href="<?= BASE_URL ?>/pages/dashboard.php"
                   style="color:#3a3e43; font-size:0.78rem; text-decoration:none;
                          display:inline-flex; align-items:center; gap:0.3rem;
                          transition:color 0.15s;"
                   onmouseover="this.style.color='#dab937'"
                   onmouseout="this.style.color='#3a3e43'">
                    ← กลับหน้าแรก
                </a>
            </div>
            <h1 style="font-size:1.55rem; font-weight:800; color:#eeebe1; margin:0 0 0.3rem;
                       letter-spacing:-0.01em;">
                ประวัติ
            </h1>
            <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                บันทึกการรับ Token, ใช้จ่าย และการแลกรางวัลทั้งหมด
            </p>
        </div>

        <!-- Wallet summary bar -->
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.85rem;
                    margin-bottom:2.25rem;">
            <?php
            $walletStats = [
                ['label' => 'Token คงเหลือ', 'value' => formatTokens($wallet['balance']),      'color' => '#f8e769'],
                ['label' => 'ได้รับทั้งหมด', 'value' => formatTokens($wallet['total_earned']), 'color' => '#518e5c'],
                ['label' => 'ใช้ไปทั้งหมด',  'value' => formatTokens($wallet['total_spent']),  'color' => '#d2592a'],
            ];
            foreach ($walletStats as $ws):
            ?>
            <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                        border-radius:14px; padding:1rem 1.15rem; backdrop-filter:blur(8px);
                        text-align:center;">
                <p style="font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#3a3e43; margin:0 0 0.35rem;">
                    <?= $ws['label'] ?>
                </p>
                <p style="font-size:1.3rem; font-weight:800; color:<?= $ws['color'] ?>;
                           margin:0; letter-spacing:-0.02em;">
                    <?= $ws['value'] ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($dataError): ?>
        <div style="background:rgba(210,89,42,0.10); border:1px solid rgba(210,89,42,0.28);
                    border-radius:14px; padding:1.25rem; text-align:center; color:#d2592a;
                    font-size:0.85rem; margin-bottom:2rem;">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             TAB: สลับ Token Transactions / Redemptions
        ══════════════════════════════════════════════════════ -->
        <div style="display:flex; gap:0.6rem; margin-bottom:1.5rem;">
            <button id="tab-tx" onclick="switchTab('tx')"
                    class="hy-tab hy-tab--active"
                    style="display:flex; align-items:center; gap:0.45rem;">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
                Token
                <span class="hy-tab-badge"><?= count($transactions) ?></span>
            </button>
            <button id="tab-rd" onclick="switchTab('rd')"
                    class="hy-tab"
                    style="display:flex; align-items:center; gap:0.45rem;">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 12v10H4V12"/>
                    <path d="M22 7H2v5h20V7z"/>
                    <path d="M12 22V7"/>
                    <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/>
                    <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                </svg>
                รางวัล
                <span class="hy-tab-badge"><?= count($redemptions) ?></span>
            </button>
        </div>

        <!-- ══════════════════════════════════════════════════
             TOKEN TRANSACTIONS PANEL
        ══════════════════════════════════════════════════ -->
        <div id="panel-tx">
        <?php if (empty($transactions)): ?>
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; padding:3.5rem; text-align:center; backdrop-filter:blur(8px);">
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15;">📊</p>
            <p style="font-size:0.88rem; color:#3a3e43; margin:0;">ยังไม่มีประวัติการทำรายการ</p>
        </div>
        <?php else: ?>
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">
            <!-- Header -->
            <div style="display:grid; grid-template-columns:1fr auto auto;
                        gap:1rem; padding:0.65rem 1.25rem;
                        background:rgba(255,255,255,0.03);
                        border-bottom:1px solid rgba(255,255,255,0.07);
                        font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                        text-transform:uppercase; color:#6b6e77;">
                <span>รายการ</span>
                <span style="text-align:right;">Token</span>
                <span>วันที่</span>
            </div>
            <?php foreach ($transactions as $tx):
                $isEarn  = (int)$tx['amount'] > 0;
                $amtDisp = ($isEarn ? '+' : '') . number_format((int)$tx['amount']);
                $amtClr  = $isEarn ? '#518e5c' : '#d2592a';
            ?>
            <div class="hy-tx-row"
                 style="display:grid; grid-template-columns:1fr auto auto;
                        gap:1rem; padding:0.75rem 1.25rem; align-items:center;">
                <div>
                    <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0 0 0.08rem;">
                        <?= e(txTypeLabel($tx['tx_type'])) ?>
                    </p>
                    <?php if (!empty($tx['note'])): ?>
                    <p style="font-size:0.70rem; color:#6b6e77; margin:0;">
                        <?= e($tx['note']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <span style="font-size:0.88rem; font-weight:800; color:<?= $amtClr ?>;
                             white-space:nowrap; letter-spacing:-0.01em;">
                    <?= $amtDisp ?>
                </span>
                <span style="font-size:0.73rem; color:#eeebe1; white-space:nowrap;">
                    <?= date('d/m/y', strtotime($tx['created_at'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div><!-- /panel-tx -->

        <!-- ══════════════════════════════════════════════════
             REDEMPTIONS PANEL
        ══════════════════════════════════════════════════ -->
        <div id="panel-rd" style="display:none;">
        <?php if (empty($redemptions)): ?>
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; padding:3.5rem; text-align:center; backdrop-filter:blur(8px);">
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15;">🎁</p>
            <p style="font-size:0.88rem; color:#3a3e43; margin:0;">ยังไม่มีประวัติการแลกรางวัล</p>
        </div>
        <?php else: ?>
        <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; overflow:hidden; backdrop-filter:blur(8px);">
            <!-- Header -->
            <div style="display:grid; grid-template-columns:1fr auto auto auto;
                        gap:1rem; padding:0.65rem 1.25rem;
                        background:rgba(255,255,255,0.03);
                        border-bottom:1px solid rgba(255,255,255,0.07);
                        font-size:0.62rem; font-weight:700; letter-spacing:0.10em;
                        text-transform:uppercase; color:#6b6e77;">
                <span>รางวัล</span>
                <span>Token</span>
                <span>วันที่</span>
                <span>สถานะ</span>
            </div>
            <?php
            $dsDark = [
                'pending'   => ['color' => '#fbbf24', 'bg' => 'rgba(245,158,11,0.10)',  'border' => 'rgba(245,158,11,0.25)'],
                'fulfilled' => ['color' => '#518e5c', 'bg' => 'rgba(81,142,92,0.12)',   'border' => 'rgba(81,142,92,0.28)'],
                'cancelled' => ['color' => '#d2592a', 'bg' => 'rgba(210,89,42,0.10)',   'border' => 'rgba(210,89,42,0.25)'],
            ];
            $rdLabels = [
                'pending'   => 'รอดำเนินการ',
                'fulfilled' => 'มอบแล้ว',
                'cancelled' => 'ยกเลิก',
            ];
            foreach ($redemptions as $rd):
                $ds = $dsDark[$rd['status']] ?? $dsDark['pending'];
            ?>
            <div class="hy-rd-row"
                 style="display:flex; flex-direction:column; padding:0.85rem 1.25rem;
                        gap:0.65rem;">
                <!-- Main row -->
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                             gap:1rem; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.6rem; min-width:0;">
                        <span style="font-size:1.25rem; flex-shrink:0; user-select:none; line-height:1;">
                            <?= e($rd['image_emoji'] ?: '🎁') ?>
                        </span>
                        <div style="min-width:0;">
                            <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0;
                                       white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= e($rd['reward_title']) ?>
                            </p>
                            <?php if (!empty($rd['admin_note'])): ?>
                            <p style="font-size:0.70rem; color:#6b6e77; margin:0.06rem 0 0;">
                                <?= e($rd['admin_note']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.28rem; white-space:nowrap;">
                        <img src="<?= BASE_URL ?>/assets/images/token.png"
                             width="12" height="12" style="object-fit:contain; opacity:0.65;" alt="">
                        <span style="font-size:0.85rem; font-weight:700; color:#dab937;">
                            <?= (int)$rd['tokens_spent'] ?>
                        </span>
                    </div>
                    <span style="font-size:0.73rem; color:#eeebe1; white-space:nowrap;">
                        <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                    </span>
                    <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                                 border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                                 background:<?= $ds['bg'] ?>; color:<?= $ds['color'] ?>;
                                 border:1px solid <?= $ds['border'] ?>;">
                        <?= $rdLabels[$rd['status']] ?? $rd['status'] ?>
                    </span>
                </div><!-- /grid row -->

                <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
                <!-- Coupon code reveal -->
                <div style="display:inline-flex; align-items:center; gap:0.55rem;
                            background:rgba(218,185,55,0.07); border:1px solid rgba(218,185,55,0.22);
                            border-radius:10px; padding:0.42rem 0.85rem; align-self:flex-start;">
                    <span style="font-size:0.68rem; font-weight:700; letter-spacing:0.10em;
                                 color:rgba(218,185,55,0.55); text-transform:uppercase; user-select:none;">
                        🔑 รหัสคูปอง
                    </span>
                    <span style="font-size:0.9rem; font-weight:800; color:#f8e769;
                                 letter-spacing:0.06em; font-family:monospace, 'Prompt';">
                        <?= e($rd['coupon_code']) ?>
                    </span>
                    <button onclick="
                        navigator.clipboard.writeText('<?= e(addslashes($rd['coupon_code'])) ?>');
                        this.textContent='✓';
                        setTimeout(()=>{ this.textContent='📋'; },1500);"
                            style="background:rgba(218,185,55,0.12); border:1px solid rgba(218,185,55,0.22);
                                   border-radius:6px; color:#dab937; cursor:pointer; font-size:0.72rem;
                                   padding:0.18rem 0.42rem; line-height:1.4; transition:background 0.15s;"
                            onmouseover="this.style.background='rgba(218,185,55,0.22)'"
                            onmouseout="this.style.background='rgba(218,185,55,0.12)'"
                            title="คัดลอกโค้ด">📋</button>
                </div>
                <?php elseif ($rd['status'] === 'pending'): ?>
                <div style="display:inline-flex; align-items:center; gap:0.4rem; align-self:flex-start;">
                    <span style="font-size:0.68rem; color:#3a3e43;">🔒</span>
                    <span style="font-size:0.68rem; color:#3a3e43;">รหัสคูปองจะปรากฏหลัง HR ยืนยันมอบรางวัล</span>
                </div>
                <?php endif; ?>

            </div><!-- /hy-rd-row -->
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div><!-- /panel-rd -->

    </div><!-- /inner -->
</div><!-- /hy-history-wrap -->

<style>
.hy-tab {
    font-family: 'Prompt', sans-serif;
    font-size: 0.80rem;
    font-weight: 600;
    padding: 0.45rem 1rem;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.10);
    color: #6b6e77;
}
.hy-tab:hover {
    background: rgba(255,255,255,0.08);
    color: #eeebe1;
}
.hy-tab--active {
    background: rgba(218,185,55,0.10);
    border-color: rgba(218,185,55,0.28);
    color: #dab937;
}
.hy-tab-badge {
    font-size: 0.62rem;
    font-weight: 700;
    background: rgba(255,255,255,0.07);
    border-radius: 999px;
    padding: 0.10rem 0.42rem;
}
.hy-tx-row, .hy-rd-row {
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.hy-tx-row:last-child, .hy-rd-row:last-child {
    border-bottom: none;
}
.hy-tx-row:hover, .hy-rd-row:hover {
    background: rgba(255,255,255,0.025);
}
</style>

<script>
function switchTab(tab) {
    const panelTx = document.getElementById('panel-tx');
    const panelRd = document.getElementById('panel-rd');
    const btnTx   = document.getElementById('tab-tx');
    const btnRd   = document.getElementById('tab-rd');

    if (tab === 'tx') {
        panelTx.style.display = '';
        panelRd.style.display = 'none';
        btnTx.classList.add('hy-tab--active');
        btnRd.classList.remove('hy-tab--active');
    } else {
        panelTx.style.display = 'none';
        panelRd.style.display = '';
        btnRd.classList.add('hy-tab--active');
        btnTx.classList.remove('hy-tab--active');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
