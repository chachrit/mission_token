<?php
/**
 * pages/history.php
 * Employee: full token transaction history + redemption history with coupon reveal
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

function hyThaiDate(string $dateStr, bool $withTime = false): string {
    $ts = strtotime($dateStr);
    $m  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $out = (int)date('j',$ts) . ' ' . $m[(int)date('n',$ts)] . ' ' . ((int)date('Y',$ts)+543);
    if ($withTime) $out .= ' ' . date('H:i',$ts);
    return $out;
}

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
        SELECT tt.tx_id, tt.amount, tt.tx_type, tt.note, tt.created_at,
               adj.full_name AS created_by_name
        FROM   dbo.token_transactions tt
        LEFT JOIN dbo.employees adj ON adj.employee_id = tt.created_by
        WHERE  tt.employee_id = ?
        ORDER BY tt.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $txAll = $stmt->fetchAll();

    // Quiz + Photo submission history
    $stmt = $pdo->prepare("
        SELECT cs.submission_id, cs.submission_type, cs.status,
               cs.token_awarded, cs.submitted_at, cs.review_note,
               c.title AS challenge_title, c.token_reward, c.type AS challenge_type,
               rv.full_name AS reviewed_by_name
        FROM   dbo.challenge_submissions cs
        JOIN   dbo.challenges c ON c.challenge_id = cs.challenge_id
        LEFT JOIN dbo.employees rv ON rv.employee_id = cs.reviewed_by
        WHERE  cs.employee_id = ?
        ORDER BY cs.submitted_at DESC
    ");
    $stmt->execute([$employeeId]);
    $quizHistory = $stmt->fetchAll();

    // Redemption history with coupon_code + approver name
    $stmt = $pdo->prepare("
        SELECT rd.redemption_id, rd.tokens_spent, rd.status,
               rd.redeemed_at, rd.processed_at, rd.admin_note,
               rw.title      AS reward_title,
               rw.image_emoji,
               rw.category,
               rw.coupon_code,
               ap.full_name  AS processed_by_name
        FROM   dbo.reward_redemptions rd
        JOIN   dbo.rewards            rw ON rw.reward_id   = rd.reward_id
        LEFT JOIN dbo.employees       ap ON ap.employee_id = rd.processed_by
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

<div style="position:relative; z-index:1; max-width:1100px; margin:0 auto;
                padding:2rem 1.25rem 4rem;">

        <!-- Page header -->
        <div style="margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.05);">
            <a href="<?= BASE_URL ?>/pages/dashboard.php"
               style="color:#6b6e77; font-size:0.78rem; text-decoration:none;
                      display:inline-flex; align-items:center; gap:0.3rem;
                      transition:color 0.15s;"
               onmouseover="this.style.color='#dab937'"
               onmouseout="this.style.color='#6b6e77'">&#8592; หน้าแรก</a>
            <div style="margin-top:0.85rem; display:flex; align-items:flex-end;
                        justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                <div>
                    <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0 0 0.3rem;
                               letter-spacing:-0.02em;">
                       ประวัติ
                    </h1>
                    <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                        บันทึกการรับ Token, ใช้จ่าย และการแลกรางวัลทั้งหมด
                    </p>
                </div>
                <!-- total summary pill -->
                <div style="display:flex; align-items:center; gap:0.5rem; flex-shrink:0;
                            background:rgba(218,185,55,0.07); border:1px solid rgba(218,185,55,0.18);
                            border-radius:12px; padding:0.55rem 1rem;">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" width="18" height="18"
                         style="object-fit:contain; filter:drop-shadow(0 0 6px rgba(218,185,55,0.5));" alt="">
                    <div>
                        <p style="font-size:0.58rem; color:#6b6e77; text-transform:uppercase;
                                   letter-spacing:0.08em; margin:0 0 0.05rem;">Token คงเหลือ</p>
                        <p style="font-size:1.1rem; font-weight:800; color:#f8e769; margin:0;
                                   letter-spacing:-0.01em;"><?= formatTokens($wallet['balance']) ?></p>
                    </div>
                </div>
            </div>
            <!-- gold accent line -->
            <div style="width:48px; height:3px; background:linear-gradient(90deg,#dab937,transparent);
                        border-radius:99px; margin-top:1rem;"></div>
        </div>

        <!-- Wallet summary bar -->
        <div class="hy-wallet-grid">
            <?php
            $walletStats = [
                ['label' => 'รับทั้งหมด',    'value' => formatTokens($wallet['total_earned']), 'color' => '#518e5c',  'border' => 'rgba(81,142,92,0.22)',   'icon' => '↑', 'sub' => '+'],
                ['label' => 'ใช้ไปทั้งหมด',  'value' => formatTokens($wallet['total_spent']),  'color' => '#d2592a',  'border' => 'rgba(210,89,42,0.20)',   'icon' => '↓', 'sub' => '−'],
                ['label' => 'รายการทั้งหมด', 'value' => (string)(count($txAll)),                'color' => '#cecdcd',  'border' => 'rgba(206,205,205,0.15)', 'icon' => '◊', 'sub' => ''],
            ];
            foreach ($walletStats as $ws):
            ?>
            <div style="background:rgba(255,255,255,0.025);
                        border:1px solid <?= $ws['border'] ?>;
                        border-radius:14px; padding:1.1rem 1.25rem; backdrop-filter:blur(8px);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.45rem;">
                    <span style="font-size:0.95rem; color:<?= $ws['color'] ?>; line-height:1; opacity:0.7;"><?= $ws['icon'] ?></span>
                    <p style="font-size:0.60rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0;">
                        <?= $ws['label'] ?>
                    </p>
                </div>
                <p style="font-size:1.35rem; font-weight:800; color:<?= $ws['color'] ?>;
                           margin:0; letter-spacing:-0.02em;">
                    <?= $ws['sub'] ?><?= $ws['value'] ?>
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
                'reward'=> ['label' => 'รางวัล',  'icon' => '◇', 'count' => count($redemptions)],
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
            <p style="font-size:2rem; margin:0 0 0.5rem; opacity:0.15; display:inline-flex; align-items:center;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <line x1="4" y1="20" x2="20" y2="20" stroke-width="2"/>
                    <rect x="6" y="11" width="3" height="7" stroke-width="2"/>
                    <rect x="11" y="7" width="3" height="11" stroke-width="2"/>
                    <rect x="16" y="4" width="3" height="14" stroke-width="2"/>
                </svg>
            </p>
            <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ยังไม่มีประวัติ Token</p>
        </div>
        <?php else: ?>
        <!-- Token type filter pills -->
        <div style="display:flex; gap:0.4rem; margin-bottom:0.85rem; flex-wrap:wrap; align-items:center;">
            <button class="hy-tab hy-tab--active hy-tx-filter-btn" data-filter="all" onclick="filterTokens('all')">
                ทั้งหมด <span class="hy-tab-badge" id="hy-filter-count"><?= count($txAll) ?></span>
            </button>
            <button class="hy-tab hy-tx-filter-btn" data-filter="earn" onclick="filterTokens('earn')">
                <span style="color:#518e5c; font-weight:800;">+</span> รับ Token
            </button>
            <button class="hy-tab hy-tx-filter-btn" data-filter="spend" onclick="filterTokens('spend')">
                <span style="color:#d2592a; font-weight:800;">&minus;</span> ใช้ Token
            </button>
        </div>
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
            <div class="hy-tx-row" data-dir="<?= $isEarn ? 'earn' : 'spend' ?>"
                 onclick="openHyTxModal(<?= (int)$tx['tx_id'] ?>)"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openHyTxModal(<?= (int)$tx['tx_id'] ?>);}"
                 tabindex="0" role="button"
                 style="cursor:pointer; display:grid; grid-template-columns:1fr auto auto;
                        gap:1rem; padding:0.75rem 1.25rem; align-items:center;">
                <div>
                    <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0 0 0.06rem;">
                        <?php
                        if ($tx['tx_type'] === 'admin_adjust') {
                            echo $isEarn ? 'ได้รับ Token' : 'ถูกหัก Token';
                        } else {
                            echo e(txTypeLabel($tx['tx_type']));
                        }
                        ?>
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
            <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ยังไม่มีประวัติการทำภารกิจ</p>
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
            <div class="hy-tx-row" onclick="openHyQuestModal(<?= (int)$q['submission_id'] ?>)"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openHyQuestModal(<?= (int)$q['submission_id'] ?>);}"
                 tabindex="0" role="button"
                 style="cursor:pointer; display:grid; grid-template-columns:1fr auto auto auto;
                        gap:1rem; padding:0.75rem 1.25rem; align-items:center;">
                <div>
                    <p style="font-size:0.83rem; font-weight:500; color:#eeebe1; margin:0 0 0.06rem;">
                        <?= e($q['challenge_title']) ?>
                    </p>
                    <p style="font-size:0.70rem; color:#6b6e77; margin:0;">
                        <?= hyThaiDate($q['submitted_at']) ?>
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
            <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ยังไม่มีประวัติการแลกรางวัล</p>
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
            <div class="hy-rd-row" onclick="openHyRdModal(<?= (int)$rd['redemption_id'] ?>)"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openHyRdModal(<?= (int)$rd['redemption_id'] ?>);}"
                 tabindex="0" role="button"
                 style="cursor:pointer; padding:0.85rem 1.25rem; display:flex; flex-direction:column; gap:0.6rem;">
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                             gap:1rem; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.6rem; min-width:0;">
                        <span style="font-size:1.2rem; flex-shrink:0; line-height:1; user-select:none;">
                            R
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
                        <?= hyThaiDate($rd['redeemed_at']) ?>
                    </span>
                    <span style="font-size:0.65rem; font-weight:700; padding:0.22rem 0.68rem;
                                 border-radius:999px; white-space:nowrap; letter-spacing:0.02em;
                                 background:<?= $rs['bg'] ?>; color:<?= $rs['color'] ?>;
                                 border:1px solid <?= $rs['border'] ?>;">
                        <?= $rs['label'] ?>
                    </span>
                </div>

                <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
                <div style="border-top:1px dashed rgba(218,185,55,0.18); padding-top:0.65rem; margin-top:0.1rem;
                            display:flex; align-items:center; justify-content:space-between; gap:0.6rem; flex-wrap:wrap;">
                    <!-- toggle button -->
                    <button onclick="event.stopPropagation(); hyToggleCoupon(<?= (int)$rd['redemption_id'] ?>, this)"
                            style="display:inline-flex; align-items:center; gap:0.38rem;
                                   background:rgba(218,185,55,0.08); border:1px solid rgba(218,185,55,0.25);
                                   border-radius:8px; padding:0.3rem 0.7rem; cursor:pointer;
                                   font-size:0.7rem; font-weight:700; color:rgba(218,185,55,0.75);
                                   letter-spacing:0.06em; text-transform:uppercase;
                                   font-family:'Prompt',sans-serif;
                                   transition:background 0.15s, border-color 0.15s;"
                            onmouseover="this.style.background='rgba(218,185,55,0.14)'; this.style.borderColor='rgba(218,185,55,0.40)'"
                            onmouseout="this.style.background='rgba(218,185,55,0.08)'; this.style.borderColor='rgba(218,185,55,0.25)'"
                            title="แสดง/ซ่อนรหัสคูปอง">
                        <svg id="hy-coupon-eye-<?= (int)$rd['redemption_id'] ?>"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             width="12" height="12" style="flex-shrink:0;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span id="hy-coupon-lbl-<?= (int)$rd['redemption_id'] ?>">แสดงรหัสคูปอง</span>
                    </button>

                    <!-- coupon box (hidden by default, inline to the right) -->
                    <div id="hy-coupon-box-<?= (int)$rd['redemption_id'] ?>"
                         style="display:none; align-items:center; gap:0.6rem; flex-wrap:wrap;
                                background:rgba(218,185,55,0.05); border:1px solid rgba(218,185,55,0.22);
                                border-radius:10px; padding:0.32rem 0.85rem;">
                        <div style="display:flex; flex-direction:column; gap:0.06rem;">
                            <span style="font-size:0.55rem; font-weight:600; letter-spacing:0.08em;
                                         color:rgba(218,185,55,0.38); text-transform:uppercase; line-height:1;">
                                อนุมัติโดย: <?= e($rd['processed_by_name'] ?? '—') ?>
                            </span>
                            <span style="font-size:1rem; font-weight:800; color:#f8e769;
                                         letter-spacing:0.12em; font-family:monospace,'Prompt';
                                         user-select:all; word-break:break-all; line-height:1.3;">
                                <?= e($rd['coupon_code']) ?>
                            </span>
                        </div>
                        <button onclick="event.stopPropagation(); hycopyCoupon('<?= e(addslashes($rd['coupon_code'])) ?>',<?= (int)$rd['redemption_id'] ?>)"
                                id="hy-coupon-copy-<?= (int)$rd['redemption_id'] ?>"
                                style="display:inline-flex; align-items:center; gap:0.25rem; flex-shrink:0;
                                       background:rgba(218,185,55,0.12); border:1px solid rgba(218,185,55,0.22);
                                       border-radius:6px; color:#dab937; cursor:pointer;
                                       font-size:0.68rem; font-weight:600;
                                       font-family:'Prompt',sans-serif;
                                       padding:0.22rem 0.6rem; line-height:1.4;
                                       transition:background 0.15s; white-space:nowrap;"
                                onmouseover="this.style.background='rgba(218,185,55,0.22)'"
                                onmouseout="this.style.background='rgba(218,185,55,0.12)'"
                                title="คัดลอก">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            คัดลอก
                        </button>
                    </div>
                </div>
                <?php elseif ($rd['status'] === 'pending'): ?>
                <div style="display:inline-flex; align-items:center; gap:0.4rem; align-self:flex-start;">
                    <span style="font-size:0.68rem; color:#6b6e77; display:inline-flex; align-items:center;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="4" y="11" width="16" height="9" rx="2" ry="2" stroke-width="2"/>
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-width="2"/>
                        </svg>
                    </span>
                    <span style="font-size:0.68rem; color:#6b6e77;">รหัสคูปองจะปรากฏหลัง HR ยืนยันมอบรางวัล</span>
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
/* ── Responsive ── */
.hy-wallet-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.85rem; margin-bottom:2.25rem; }
@media (max-width: 640px) {
    .hy-wallet-grid { grid-template-columns:1fr; }
}
@media (max-width: 560px) {
    #panel-token .hy-table-head,
    #panel-token .hy-tx-row { grid-template-columns:1fr auto !important; }
    #panel-token .hy-table-head span:nth-child(3),
    #panel-token .hy-tx-row > :nth-child(3) { display:none; }
    #panel-quest .hy-table-head,
    #panel-quest .hy-tx-row { grid-template-columns:1fr auto !important; }
    #panel-quest .hy-table-head span:nth-child(2),
    #panel-quest .hy-table-head span:nth-child(3),
    #panel-quest .hy-tx-row > :nth-child(2),
    #panel-quest .hy-tx-row > :nth-child(3) { display:none; }
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

/* ── Token filter ── */
function filterTokens(dir) {
    var rows = document.querySelectorAll('.hy-tx-row[data-dir]');
    var count = 0;
    rows.forEach(function(row) {
        var show = (dir === 'all' || row.dataset.dir === dir);
        row.style.display = show ? '' : 'none';
        if (show) count++;
    });
    var cEl = document.getElementById('hy-filter-count');
    if (cEl) cEl.textContent = count;
    document.querySelectorAll('.hy-tx-filter-btn').forEach(function(b) {
        b.classList.toggle('hy-tab--active', b.dataset.filter === dir);
    });
}

/* ── Coupon toggle ── */
window.hyToggleCoupon = function (id, btn) {
    var box   = document.getElementById('hy-coupon-box-' + id);
    var label = document.getElementById('hy-coupon-lbl-' + id);
    var eye   = document.getElementById('hy-coupon-eye-' + id);
    if (!box) return;
    var visible = box.style.display === 'flex';
    if (visible) {
        box.style.display = 'none';
        label.textContent = 'แสดงรหัสคูปอง';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    } else {
        box.style.display = 'flex';
        label.textContent = 'ซ่อนรหัสคูปอง';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    }
};

window.hycopyCoupon = function (code, id) {
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.getElementById('hy-coupon-copy-' + id);
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.textContent = 'คัดลอกแล้ว';
        setTimeout(function () { btn.innerHTML = orig; }, 1500);
    });
};
</script>

<?php
$hyTxData = [];
foreach ($txAll as $_tx) {
    $_amt  = (int)$_tx['amount'];
    $_earn = $_amt > 0;
    if ($_tx['tx_type'] === 'admin_adjust') {
        $_adjName = !empty($_tx['created_by_name']) ? $_tx['created_by_name'] : 'Admin';
        $_typeLabel = $_earn ? 'ได้รับ Token โดย ' . $_adjName : 'ถูกหัก Token โดย ' . $_adjName;
    } else {
        $_typeLabel = txTypeLabel((string)$_tx['tx_type']);
    }
    $hyTxData[(int)$_tx['tx_id']] = [
        'type'   => $_typeLabel,
        'amount' => $_amt,
        'note'   => (string)($_tx['note'] ?? ''),
        'at'     => hyThaiDate((string)$_tx['created_at'], true),
    ];
}
$hyQuestData = [];
foreach ($quizHistory as $_q) {
    $hyQuestData[(int)$_q['submission_id']] = [
        'title'    => $_q['challenge_title'],
        'ctype'    => $_q['challenge_type'],
        'status'   => $_q['status'],
        'token'    => (int)$_q['token_awarded'],
        'at'       => hyThaiDate((string)$_q['submitted_at'], true),
        'note'     => (string)($_q['review_note'] ?? ''),
        'reviewer' => (string)($_q['reviewed_by_name'] ?? ''),
    ];
}
$hyRdData = [];
foreach ($redemptions as $_rd) {
    $_rid = (int)$_rd['redemption_id'];
    $hyRdData[$_rid] = [
        'title'  => $_rd['reward_title'],
        'emoji'  => 'R',
        'tokens' => (int)$_rd['tokens_spent'],
        'status' => $_rd['status'],
        'reqAt'  => hyThaiDate((string)$_rd['redeemed_at'], true),
        'procAt' => $_rd['processed_at'] ? hyThaiDate((string)$_rd['processed_at'], true) : null,
        'note'   => (string)($_rd['admin_note'] ?? ''),
        'procBy' => (string)($_rd['processed_by_name'] ?? ''),
        'coupon' => ($_rd['status'] === 'fulfilled') ? (string)($_rd['coupon_code'] ?? '') : '',
    ];
}
?>
<script>
var _hyTxData    = <?= json_encode($hyTxData,    JSON_UNESCAPED_UNICODE) ?>;
var _hyQuestData = <?= json_encode($hyQuestData, JSON_UNESCAPED_UNICODE) ?>;
var _hyRdData    = <?= json_encode($hyRdData,    JSON_UNESCAPED_UNICODE) ?>;
</script>

<style>
@keyframes _hyCardIn {
    0%   { opacity:0; transform:perspective(700px) scale(0.80) translateY(36px) rotateX(16deg); }
    60%  { opacity:1; transform:perspective(700px) scale(1.03)  translateY(-4px) rotateX(-2deg); }
    100% { opacity:1; transform:perspective(700px) scale(1)     translateY(0)    rotateX(0deg);  }
}
@keyframes _hyCardOut { from{opacity:1;transform:scale(1) translateY(0)} to{opacity:0;transform:scale(0.86) translateY(22px)} }
@keyframes _hyFadeIn  { from{opacity:0} to{opacity:1} }
@keyframes _hyFadeOut { from{opacity:1} to{opacity:0} }
.hy-ov-in  { animation:_hyFadeIn  230ms ease                            forwards; }
.hy-ov-out { animation:_hyFadeOut 155ms ease                            forwards; }
.hy-ci-in  { animation:_hyCardIn  420ms cubic-bezier(0.34,1.56,0.64,1) forwards; }
.hy-ci-out { animation:_hyCardOut 155ms ease-in                         forwards; }
.hy-modal-ov {
    display:none; position:fixed; inset:0; z-index:9500;
    background:rgba(0,0,0,0.82); backdrop-filter:blur(7px);
    align-items:center; justify-content:center; padding:1rem;
}
.hy-modal-card {
    background:#0f1416; border-radius:20px;
    max-width:430px; width:100%; max-height:90vh; overflow-y:auto;
    box-shadow:0 24px 60px rgba(0,0,0,0.72);
}
.hy-modal-hdr {
    padding:1.1rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.07);
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; background:#0f1416; z-index:1;
    border-radius:20px 20px 0 0;
}
.hy-modal-x {
    width:28px; height:28px; border-radius:50%;
    background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
    color:#6b6e77; cursor:pointer; line-height:0;
    display:flex; align-items:center; justify-content:center;
    transition:color 0.15s; flex-shrink:0;
}
.hy-ibox {
    background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
    border-radius:12px; padding:0.7rem 0.9rem;
}
</style>

<!-- ── Modal: Token Transaction ── -->
<div id="hy-tx-modal" class="hy-modal-ov" onclick="if(event.target===this)closeHyModal('tx')">
    <div id="hy-tx-card" class="hy-modal-card" style="border:1px solid rgba(255,255,255,0.10);">
        <div class="hy-modal-hdr">
            <div style="display:flex; align-items:center; gap:0.55rem;">
                <span style="font-size:0.95rem; opacity:0.65;">&#9672;</span>
                <span style="font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
                             text-transform:uppercase; color:rgba(218,185,55,0.85)">รายการ Token</span>
            </div>
            <button class="hy-modal-x" onclick="closeHyModal('tx')"
                    onmouseover="this.style.color='#eeebe1'" onmouseout="this.style.color='#6b6e77'"
                    aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div style="padding:1.35rem 1.4rem; display:flex; flex-direction:column; gap:0.85rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;
                        background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                        border-radius:14px; padding:1rem 1.15rem;">
                <img src="<?= BASE_URL ?>/assets/images/token.png" width="28" height="28"
                     style="object-fit:contain; filter:drop-shadow(0 0 8px rgba(218,185,55,0.5)); flex-shrink:0;" alt="">
                <div>
                    <p style="font-size:0.58rem; letter-spacing:0.12em; text-transform:uppercase;
                              color:#6b6e77; margin:0 0 0.15rem; font-weight:700;">จำนวน</p>
                    <p id="hytx-amount" style="font-size:1.65rem; font-weight:800; margin:0; line-height:1; letter-spacing:-0.02em;"></p>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.55rem;">
                <div class="hy-ibox">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">ประเภท</p>
                    <p id="hytx-type" style="font-size:0.83rem; font-weight:600; color:#eeebe1; margin:0; line-height:1.35;"></p>
                </div>
                <div class="hy-ibox">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่</p>
                    <p id="hytx-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0; line-height:1.4;"></p>
                </div>
            </div>
            <div id="hytx-note-wrap" class="hy-ibox" style="display:none;">
                <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#6b6e77; margin:0 0 0.3rem;">หมายเหตุ</p>
                <p id="hytx-note" style="font-size:0.83rem; color:#eeebe1; margin:0; line-height:1.55;"></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Quest Submission ── -->
<div id="hy-quest-modal" class="hy-modal-ov" onclick="if(event.target===this)closeHyModal('quest')">
    <div id="hy-quest-card" class="hy-modal-card" style="border:1px solid rgba(79,139,152,0.22);">
        <div class="hy-modal-hdr">
            <div style="display:flex; align-items:center; gap:0.55rem;">
                <span style="font-size:0.95rem; opacity:0.65;">&#9678;</span>
                <span style="font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
                             text-transform:uppercase; color:rgba(79,139,152,0.90);">ภารกิจ</span>
            </div>
            <button class="hy-modal-x" onclick="closeHyModal('quest')"
                    onmouseover="this.style.color='#eeebe1'" onmouseout="this.style.color='#6b6e77'"
                    aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div style="padding:1.35rem 1.4rem; display:flex; flex-direction:column; gap:0.85rem;">
            <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                        border-radius:14px; padding:1rem 1.1rem;">
                <p id="hyq-title" style="font-size:0.97rem; font-weight:700; color:#eeebe1; margin:0 0 0.55rem; line-height:1.35;"></p>
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                    <span id="hyq-ctype" style="font-size:0.63rem; font-weight:700; padding:0.2rem 0.65rem;
                                border-radius:999px; border:1px solid rgba(79,139,152,0.35);
                                background:rgba(255,255,255,0.07); color:#cecdcd; border:1px solid rgba(255,255,255,0.15);"></span>
                    <span id="hyq-status" style="font-size:0.63rem; font-weight:700; padding:0.2rem 0.65rem;
                                border-radius:999px;"></span>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.55rem;">
                <div class="hy-ibox" style="border-color:rgba(218,185,55,0.18); background:rgba(218,185,55,0.07);">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">Token ที่ได้</p>
                    <p id="hyq-token" style="font-size:1.2rem; font-weight:800; margin:0;"></p>
                </div>
                <div class="hy-ibox">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่ส่ง</p>
                    <p id="hyq-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0; line-height:1.4;"></p>
                </div>
            </div>
            <div id="hyq-reviewer-wrap" class="hy-ibox" style="display:none;">
                <p id="hyq-reviewer-lbl" style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">อนุมัติโดย</p>
                <p id="hyq-reviewer" style="font-size:0.83rem; font-weight:600; color:#eeebe1; margin:0;"></p>
            </div>
            <div class="hy-ibox">
                <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#6b6e77; margin:0 0 0.3rem;">หมายเหตุ</p>
                <p id="hyq-note" style="font-size:0.83rem; color:#eeebe1; margin:0; line-height:1.55;"></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Reward Redemption ── -->
<div id="hy-rd-modal" class="hy-modal-ov" onclick="if(event.target===this)closeHyModal('rd')">
    <div id="hy-rd-card" class="hy-modal-card" style="border:1px solid rgba(218,185,55,0.22);">
        <div class="hy-modal-hdr">
            <div style="display:flex; align-items:center; gap:0.55rem;">
                <span style="font-size:0.95rem; opacity:0.65;">&#10022;</span>
                <span style="font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
                             text-transform:uppercase; color:rgba(218,185,55,0.85);">การแลกรางวัล</span>
            </div>
            <button class="hy-modal-x" onclick="closeHyModal('rd')"
                    onmouseover="this.style.color='#eeebe1'" onmouseout="this.style.color='#6b6e77'"
                    aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div style="padding:1.35rem 1.4rem; display:flex; flex-direction:column; gap:0.85rem;">
            <div style="display:flex; align-items:center; gap:0.9rem;
                        background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
                        border-radius:14px; padding:0.9rem 1.1rem;">
                <span id="hyrd-emoji" style="font-size:2.4rem; flex-shrink:0; line-height:1; user-select:none;"></span>
                <div style="flex:1; min-width:0;">
                    <p id="hyrd-title" style="font-size:0.97rem; font-weight:700; color:#eeebe1; margin:0 0 0.4rem; line-height:1.3;"></p>
                    <span id="hyrd-status" style="font-size:0.63rem; font-weight:700; padding:0.2rem 0.65rem; border-radius:999px;"></span>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.55rem;">
                <div class="hy-ibox" style="border-color:rgba(218,185,55,0.18); background:rgba(218,185,55,0.07);">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">Token ที่ใช้</p>
                    <p id="hyrd-tokens" style="font-size:1.2rem; font-weight:800; color:#dab937; margin:0;"></p>
                </div>
                <div class="hy-ibox">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่ขอแลก</p>
                    <p id="hyrd-req-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0; line-height:1.4;"></p>
                </div>
                <div id="hyrd-proc-row" style="display:none; grid-column:1/-1;" class="hy-ibox">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:#6b6e77; margin:0 0 0.25rem;">วันที่ดำเนินการ</p>
                    <p id="hyrd-proc-at" style="font-size:0.75rem; font-weight:600; color:#eeebe1; margin:0;"></p>
                    <p id="hyrd-proc-by" style="font-size:0.70rem; color:#6b6e77; margin:0.2rem 0 0; display:none;"></p>
                </div>
            </div>
            <div id="hyrd-note-wrap" class="hy-ibox" style="display:none;">
                <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                           text-transform:uppercase; color:#6b6e77; margin:0 0 0.3rem;">หมายเหตุจาก HR</p>
                <p id="hyrd-note" style="font-size:0.83rem; color:#eeebe1; margin:0; line-height:1.55;"></p>
            </div>
            <div id="hyrd-coupon-sec" style="display:none;">
                <button onclick="hyRdToggleCoupon()"
                        style="display:inline-flex; align-items:center; gap:0.4rem; width:100%;
                               justify-content:center; background:rgba(218,185,55,0.08);
                               border:1px solid rgba(218,185,55,0.25); border-radius:10px;
                               padding:0.5rem 1rem; cursor:pointer; font-size:0.78rem; font-weight:700;
                               color:rgba(218,185,55,0.80); font-family:'Prompt',sans-serif;">
                    <span id="hyrd-coupon-lbl">แสดงรหัสคูปอง</span>
                </button>
                <div id="hyrd-coupon-box" style="display:none; margin-top:0.5rem;
                            background:rgba(218,185,55,0.06); border:1px solid rgba(218,185,55,0.25);
                            border-radius:10px; padding:0.75rem 1rem; flex-direction:column; gap:0.35rem;">
                    <p style="font-size:0.58rem; font-weight:700; letter-spacing:0.10em;
                               text-transform:uppercase; color:rgba(218,185,55,0.45); margin:0;">รหัสคูปอง</p>
                    <div style="display:flex; align-items:center; gap:0.65rem;">
                        <p id="hyrd-coupon-code" style="font-size:1.15rem; font-weight:800; color:#f8e769;
                              letter-spacing:0.12em; font-family:monospace,'Prompt';
                              user-select:all; word-break:break-all; margin:0; flex:1;"></p>
                        <button onclick="hyRdCopyCoupon()" id="hyrd-coupon-copy"
                                style="display:inline-flex; align-items:center; gap:0.25rem; flex-shrink:0;
                                       background:rgba(218,185,55,0.12); border:1px solid rgba(218,185,55,0.25);
                                       border-radius:7px; color:#dab937; cursor:pointer;
                                       font-size:0.72rem; font-weight:600; font-family:'Prompt',sans-serif;
                                       padding:0.3rem 0.65rem; white-space:nowrap;">คัดลอก</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var _hyModalIds = {
    tx:    ['hy-tx-modal',    'hy-tx-card'],
    quest: ['hy-quest-modal', 'hy-quest-card'],
    rd:    ['hy-rd-modal',    'hy-rd-card'],
};
function _hyOpen(type) {
    var ids = _hyModalIds[type];
    var ov = document.getElementById(ids[0]), card = document.getElementById(ids[1]);
    ov.classList.remove('hy-ov-out'); card.classList.remove('hy-ci-out');
    ov.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    void card.offsetWidth;
    ov.classList.add('hy-ov-in'); card.classList.add('hy-ci-in');
}
function closeHyModal(type) {
    var ids = _hyModalIds[type];
    var ov = document.getElementById(ids[0]), card = document.getElementById(ids[1]);
    if (!ov || ov.style.display === 'none') return;
    ov.classList.remove('hy-ov-in');  card.classList.remove('hy-ci-in');
    ov.classList.add('hy-ov-out');    card.classList.add('hy-ci-out');
    setTimeout(function() {
        ov.style.display = 'none';
        ov.classList.remove('hy-ov-out'); card.classList.remove('hy-ci-out');
        document.body.style.overflow = '';
    }, 160);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeHyModal('tx'); closeHyModal('quest'); closeHyModal('rd'); }
});

// ── Token modal ──
function openHyTxModal(txId) {
    var d = _hyTxData[txId]; if (!d) return;
    var isEarn = d.amount > 0;
    var amtEl = document.getElementById('hytx-amount');
    amtEl.textContent = (isEarn ? '+' : '') + d.amount.toLocaleString();
    amtEl.style.color = isEarn ? '#6fcf80' : '#e8805a';
    document.getElementById('hytx-type').textContent = d.type;
    document.getElementById('hytx-at').textContent   = d.at;
    var nw = document.getElementById('hytx-note-wrap');
    if (d.note) { document.getElementById('hytx-note').textContent = d.note; nw.style.display = 'block'; }
    else          { nw.style.display = 'none'; }
    _hyOpen('tx');
}

// ── Quest modal ──
function openHyQuestModal(subId) {
    var d = _hyQuestData[subId]; if (!d) return;
    document.getElementById('hyq-title').textContent = d.title;
    var ctypeEl = document.getElementById('hyq-ctype');
    ctypeEl.textContent = d.ctype === 'quiz' ? 'Quiz' : (d.ctype === 'strava' ? 'Strava' : 'Photo');
    var statusMap = {
        auto_approved: { label:'ผ่าน',     bg:'rgba(81,142,92,0.12)',  color:'#6fcf80', border:'rgba(81,142,92,0.30)'  },
        approved:      { label:'อนุมัติ',   bg:'rgba(81,142,92,0.12)',  color:'#6fcf80', border:'rgba(81,142,92,0.30)'  },
        pending:       { label:'รอตรวจ',   bg:'rgba(245,158,11,0.10)', color:'#fbbf24', border:'rgba(245,158,11,0.28)' },
        rejected:      { label:'ไม่ผ่าน',  bg:'rgba(210,89,42,0.12)',  color:'#e8805a', border:'rgba(210,89,42,0.30)'  },
    };
    var sm = statusMap[d.status] || statusMap.pending;
    var sb = document.getElementById('hyq-status');
    sb.textContent = sm.label; sb.style.background = sm.bg;
    sb.style.color = sm.color; sb.style.border = '1px solid ' + sm.border;
    var tkEl = document.getElementById('hyq-token');
    if (d.token > 0) { tkEl.textContent = '+' + d.token.toLocaleString() + ' Token'; tkEl.style.color = '#dab937'; }
    else               { tkEl.textContent = '—'; tkEl.style.color = '#3a3e43'; }
    document.getElementById('hyq-at').textContent = d.at;
    // reviewer box
    var rvWrap = document.getElementById('hyq-reviewer-wrap');
    var rvEl   = document.getElementById('hyq-reviewer');
    if (d.reviewer) {
        document.getElementById('hyq-reviewer-lbl').textContent = d.status === 'rejected' ? 'ไม่อนุมัติโดย' : 'อนุมัติโดย';
        rvEl.textContent = d.reviewer;
        rvWrap.style.display = 'block';
    } else { rvWrap.style.display = 'none'; }
    // note (always shown)
    document.getElementById('hyq-note').textContent = d.note || '—';
    _hyOpen('quest');
}

// ── Reward modal ──
function openHyRdModal(rdId) {
    var d = _hyRdData[rdId]; if (!d) return;
    document.getElementById('hyrd-emoji').textContent  = d.emoji;
    document.getElementById('hyrd-title').textContent  = d.title;
    document.getElementById('hyrd-tokens').textContent = d.tokens.toLocaleString() + ' token';
    document.getElementById('hyrd-req-at').textContent = d.reqAt;
    var statusMap = {
        pending:   { label:'รอดำเนินการ', bg:'rgba(245,158,11,0.12)', color:'#fbbf24', border:'rgba(245,158,11,0.32)' },
        fulfilled: { label:'มอบแล้ว',     bg:'rgba(81,142,92,0.12)',  color:'#6fcf80', border:'rgba(81,142,92,0.32)'  },
        cancelled: { label:'ยกเลิก',          bg:'rgba(210,89,42,0.12)',  color:'#e8805a', border:'rgba(210,89,42,0.32)'  },
    };
    var sm = statusMap[d.status] || statusMap.pending;
    var sb = document.getElementById('hyrd-status');
    sb.textContent = sm.label; sb.style.background = sm.bg;
    sb.style.color = sm.color; sb.style.border = '1px solid ' + sm.border;
    var pr = document.getElementById('hyrd-proc-row');
    if (d.procAt) {
        document.getElementById('hyrd-proc-at').textContent = d.procAt;
        var pbEl = document.getElementById('hyrd-proc-by');
        if (d.procBy) { pbEl.textContent = 'โดย ' + d.procBy; pbEl.style.display = 'block'; } else { pbEl.style.display = 'none'; }
        pr.style.display = 'block';
    } else { pr.style.display = 'none'; }
    var nw = document.getElementById('hyrd-note-wrap');
    if (d.note) { document.getElementById('hyrd-note').textContent = d.note; nw.style.display = 'block'; }
    else          { nw.style.display = 'none'; }
    var cs = document.getElementById('hyrd-coupon-sec');
    if (d.coupon) {
        document.getElementById('hyrd-coupon-code').textContent  = d.coupon;
        document.getElementById('hyrd-coupon-box').style.display = 'none';
        document.getElementById('hyrd-coupon-lbl').textContent   = 'แสดงรหัสคูปอง';
        cs.style.display = 'block';
    } else { cs.style.display = 'none'; }
    _hyOpen('rd');
}
function hyRdToggleCoupon() {
    var box = document.getElementById('hyrd-coupon-box');
    var lbl = document.getElementById('hyrd-coupon-lbl');
    if (!box.style.display || box.style.display === 'none') { box.style.display = 'flex'; lbl.textContent = 'ซ่อนรหัสคูปอง'; }
    else                                                     { box.style.display = 'none'; lbl.textContent = 'แสดงรหัสคูปอง'; }
}
function hyRdCopyCoupon() {
    var code = document.getElementById('hyrd-coupon-code').textContent.trim();
    var btn  = document.getElementById('hyrd-coupon-copy');
    navigator.clipboard.writeText(code).then(function() {
        var orig = btn.textContent; btn.textContent = 'คัดลอกแล้ว'; btn.style.color = '#7ec98a';
        setTimeout(function() { btn.textContent = orig; btn.style.color = '#dab937'; }, 1800);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
