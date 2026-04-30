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
$txAll       = [];
    $quizHistory = [];
    $redemptions = [];
    $wallet      = getWalletInfo($employeeId);
    $dataError   = null;

try {
    // All token transactions
    $stmt = $pdo->prepare("
        SELECT tx_id, amount, tx_type, note, created_at
        FROM   dbo.token_transactions
        WHERE  employee_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $txAll = $stmt->fetchAll();

    // Quiz + Photo submission history
    $stmt = $pdo->prepare("
        SELECT cs.submission_id, cs.submission_type, cs.status,
               cs.token_awarded, cs.submitted_at, cs.review_note,
               c.title AS challenge_title, c.token_reward, c.type AS challenge_type
        FROM   dbo.challenge_submissions cs
        JOIN   dbo.challenges c ON c.challenge_id = cs.challenge_id
        WHERE  cs.employee_id = ?
        ORDER BY cs.submitted_at DESC
    ");
    $stmt->execute([$employeeId]);
    $quizHistory = $stmt->fetchAll();

    // Redemption history with coupon_code
    $stmt = $pdo->prepare("
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
    $stmt->execute([$employeeId]);
    $redemptions = $stmt->fetchAll();

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
             TABS
        ══════════════════════════════════════════════════════ -->
        <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <?php
            $tabs = [
                'token' => ['label' => 'Token',   'icon' => '◈', 'count' => count($txAll)],
                'quest' => ['label' => 'ภารกิจ',  'icon' => '◎', 'count' => count($quizHistory)],
                'reward'=> ['label' => 'รางวัล',  'icon' => '✦', 'count' => count($redemptions)],
            ];
            foreach ($tabs as $key => $t):
            ?>
            <button id="tab-<?= $key ?>" onclick="switchTab('<?= $key ?>')"
                    class="hy-tab <?= $key === 'token' ? 'hy-tab--active' : '' ?>"
                    style="display:flex; align-items:center; gap:0.4rem;">
                <span style="font-size:0.75rem; opacity:0.7;"><?= $t['icon'] ?></span>
                <?= $t['label'] ?>
                <span class="hy-tab-badge"><?= $t['count'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ══════════════════════════════════════════════════
             PANEL: Token (รับ + ใช้ รวมกัน)
        ══════════════════════════════════════════════════ -->
        <div id="panel-token">
        <?php if (empty($txAll)): ?>
        <div class="hy-empty-state">
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15;">📊</p>
            <p style="font-size:0.88rem; color:#3a3e43; margin:0;">ยังไม่มีประวัติ Token</p>
        </div>
        <?php else: ?>
        <div class="hy-table-wrap">
            <div class="hy-table-head" style="grid-template-columns:1fr auto auto;">
                <span>รายการ</span>
                <span style="text-align:right;">Token</span>
                <span>วันที่</span>
            </div>
            <?php foreach ($txAll as $tx):
                $amt    = (int)$tx['amount'];
                $isEarn = $amt > 0;
                $amtDisp = ($isEarn ? '+' : '') . number_format($amt);
                $amtClr  = $isEarn ? '#518e5c' : '#d2592a';
            ?>
            <div class="hy-tx-row" style="display:grid; grid-template-columns:1fr auto auto;
                         gap:1rem; padding:0.75rem 1.25rem; align-items:center;">
                <div>
                    <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0 0 0.06rem;">
                        <?= e(txTypeLabel($tx['tx_type'])) ?>
                    </p>
                    <?php if (!empty($tx['note'])): ?>
                    <p style="font-size:0.70rem; color:#6b6e77; margin:0;"><?= e($tx['note']) ?></p>
                    <?php endif; ?>
                </div>
                <span style="font-size:0.88rem; font-weight:800; color:<?= $amtClr ?>; white-space:nowrap;">
                    <?= $amtDisp ?>
                </span>
                <span style="font-size:0.73rem; color:#9ca3af; white-space:nowrap;">
                    <?= date('d/m/y', strtotime($tx['created_at'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════
             PANEL: ภารกิจ (Quiz + Photo submissions)
        ══════════════════════════════════════════════════ -->
        <div id="panel-quest" style="display:none;">
        <?php if (empty($quizHistory)): ?>
        <div class="hy-empty-state">
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15;">🎯</p>
            <p style="font-size:0.88rem; color:#3a3e43; margin:0;">ยังไม่มีประวัติการทำภารกิจ</p>
        </div>
        <?php else: ?>
        <div class="hy-table-wrap">
            <div class="hy-table-head" style="grid-template-columns:1fr auto auto auto;">
                <span>ภารกิจ</span>
                <span>ประเภท</span>
                <span>Token</span>
                <span>สถานะ</span>
            </div>
            <?php
            $qStatusStyle = [
                'auto_approved' => ['color'=>'#518e5c', 'bg'=>'rgba(81,142,92,0.12)',  'border'=>'rgba(81,142,92,0.28)',  'label'=>'ผ่าน'],
                'approved'      => ['color'=>'#518e5c', 'bg'=>'rgba(81,142,92,0.12)',  'border'=>'rgba(81,142,92,0.28)',  'label'=>'อนุมัติ'],
                'pending'       => ['color'=>'#fbbf24', 'bg'=>'rgba(245,158,11,0.10)', 'border'=>'rgba(245,158,11,0.25)', 'label'=>'รอตรวจ'],
                'rejected'      => ['color'=>'#d2592a', 'bg'=>'rgba(210,89,42,0.10)',  'border'=>'rgba(210,89,42,0.25)',  'label'=>'ไม่ผ่าน'],
            ];
            foreach ($quizHistory as $q):
                $qs = $qStatusStyle[$q['status']] ?? $qStatusStyle['pending'];
            ?>
            <div class="hy-tx-row" style="display:grid; grid-template-columns:1fr auto auto auto;
                         gap:1rem; padding:0.75rem 1.25rem; align-items:center;">
                <div>
                    <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0 0 0.06rem;">
                        <?= e($q['challenge_title']) ?>
                    </p>
                    <p style="font-size:0.70rem; color:#6b6e77; margin:0;">
                        <?= date('d/m/y', strtotime($q['submitted_at'])) ?>
                        <?php if (!empty($q['review_note'])): ?>
                        · <?= e($q['review_note']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <span style="font-size:0.72rem; color:#9ca3af; white-space:nowrap;">
                    <?= $q['challenge_type'] === 'quiz' ? 'Quiz' : 'Photo' ?>
                </span>
                <span style="font-size:0.85rem; font-weight:700; color:<?= (int)$q['token_awarded'] > 0 ? '#dab937' : '#3a3e43' ?>; white-space:nowrap;">
                    <?= (int)$q['token_awarded'] > 0 ? '+' . number_format((int)$q['token_awarded']) : '—' ?>
                </span>
                <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                             border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                             background:<?= $qs['bg'] ?>; color:<?= $qs['color'] ?>;
                             border:1px solid <?= $qs['border'] ?>;">
                    <?= $qs['label'] ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════
             PANEL: รางวัล
        ══════════════════════════════════════════════════ -->
        <div id="panel-reward" style="display:none;">
        <?php if (empty($redemptions)): ?>
        <div class="hy-empty-state">
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15;">🎁</p>
            <p style="font-size:0.88rem; color:#3a3e43; margin:0;">ยังไม่มีประวัติการแลกรางวัล</p>
        </div>
        <?php else: ?>
        <div class="hy-table-wrap">
            <?php
            $rdStyle = [
                'pending'   => ['color'=>'#fbbf24', 'bg'=>'rgba(245,158,11,0.10)',  'border'=>'rgba(245,158,11,0.25)', 'label'=>'รอดำเนินการ'],
                'fulfilled' => ['color'=>'#518e5c', 'bg'=>'rgba(81,142,92,0.12)',   'border'=>'rgba(81,142,92,0.28)',  'label'=>'มอบแล้ว'],
                'cancelled' => ['color'=>'#d2592a', 'bg'=>'rgba(210,89,42,0.10)',   'border'=>'rgba(210,89,42,0.25)',  'label'=>'ยกเลิก'],
            ];
            foreach ($redemptions as $rd):
                $rs = $rdStyle[$rd['status']] ?? $rdStyle['pending'];
            ?>
            <div class="hy-rd-row" style="padding:0.85rem 1.25rem; display:flex; flex-direction:column; gap:0.6rem;">
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                             gap:1rem; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.6rem; min-width:0;">
                        <span style="font-size:1.2rem; flex-shrink:0; line-height:1; user-select:none;">
                            <?= e($rd['image_emoji'] ?: '🎁') ?>
                        </span>
                        <div style="min-width:0;">
                            <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0;
                                       white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= e($rd['reward_title']) ?>
                            </p>
                            <?php if (!empty($rd['admin_note'])): ?>
                            <p style="font-size:0.70rem; color:#6b6e77; margin:0.04rem 0 0;">
                                <?= e($rd['admin_note']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.25rem; white-space:nowrap;">
                        <img src="<?= BASE_URL ?>/assets/images/token.png"
                             width="12" height="12" style="object-fit:contain; opacity:0.6;" alt="">
                        <span style="font-size:0.85rem; font-weight:700; color:#dab937;">
                            <?= (int)$rd['tokens_spent'] ?>
                        </span>
                    </div>
                    <span style="font-size:0.73rem; color:#9ca3af; white-space:nowrap;">
                        <?= date('d/m/y', strtotime($rd['redeemed_at'])) ?>
                    </span>
                    <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                                 border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                                 background:<?= $rs['bg'] ?>; color:<?= $rs['color'] ?>;
                                 border:1px solid <?= $rs['border'] ?>;">
                        <?= $rs['label'] ?>
                    </span>
                </div>

                <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
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
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

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
.hy-table-wrap {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(8px);
}
.hy-table-head {
    display: grid;
    gap: 1rem;
    padding: 0.65rem 1.25rem;
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: #6b6e77;
}
.hy-empty-state {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 3.5rem;
    text-align: center;
    backdrop-filter: blur(8px);
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
const ALL_PANELS = ['token','quest','reward'];
function switchTab(tab) {
    ALL_PANELS.forEach(p => {
        document.getElementById('panel-' + p).style.display = p === tab ? '' : 'none';
        document.getElementById('tab-'   + p).classList.toggle('hy-tab--active', p === tab);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
