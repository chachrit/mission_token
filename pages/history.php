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

// ── Category icon helper (same as rewards/redemptions pages) ──────────
function hyRewardCategoryIconSvg(string $category): string {
    $icons = [
        'voucher' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
        'leave'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'merch'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
        'perk'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
        'general' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>',
    ];
    return $icons[$category] ?? $icons['general'];
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

<div class="hy-page-inner">

        <!-- Page header -->
        <div class="hy-page-header">
            <a href="<?= BASE_URL ?>/pages/dashboard.php" class="hy-back-link">&#8592; หน้าแรก</a>
            <div class="hy-page-header-top">
                <div>
                    <h1 class="hy-page-title">ประวัติ</h1>
                    <p class="hy-page-subtitle">บันทึกการรับ Token, ใช้จ่าย และการแลกรางวัลทั้งหมด</p>
                </div>
                <div class="hy-summary-pill">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" width="18" height="18" class="hy-rd-token-icon" alt="">
                    <div>
                        <p class="hy-summary-pill-label">Token คงเหลือ</p>
                        <p class="hy-summary-pill-value"><?= formatTokens($wallet['balance']) ?></p>
                    </div>
                </div>
            </div>
            <div class="hy-page-accent-line"></div>
        </div>

        <div class="hy-wallet-grid">
            <?php
            $walletStats = [
                ['label' => 'รับทั้งหมด',    'value' => formatTokens($wallet['total_earned']), 'icon' => '↑', 'sub' => '+', 'tone' => 'earned'],
                ['label' => 'ใช้ไปทั้งหมด',  'value' => formatTokens($wallet['total_spent']),  'icon' => '↓', 'sub' => '−', 'tone' => 'spent'],
                ['label' => 'รายการทั้งหมด', 'value' => (string)(count($txAll)),                'icon' => '◊', 'sub' => '',  'tone' => 'count'],
            ];
            foreach ($walletStats as $ws):
            ?>
            <div class="hy-wallet-card hy-wallet-card--<?= e($ws['tone']) ?>">
                <div class="hy-wallet-card-row">
                    <span class="hy-wallet-card-icon hy-wallet-card-color--<?= e($ws['tone']) ?>"><?= $ws['icon'] ?></span>
                    <p class="hy-wallet-card-label"><?= $ws['label'] ?></p>
                </div>
                <p class="hy-wallet-card-value hy-wallet-card-color--<?= e($ws['tone']) ?>"><?= $ws['sub'] ?><?= $ws['value'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($dataError): ?>
        <div class="hy-error-message"><?= e($dataError) ?></div>
        <?php endif; ?>

        <div class="hy-tabs-container" role="tablist" aria-label="มุมมองประวัติ">
            <?php
            $tabs = [
                'token' => [
                    'label' => 'Token',
                    'count' => count($txAll),
                    'svg'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20"/><rect x="6" y="11" width="3" height="7"/><rect x="11" y="7" width="3" height="11"/><rect x="16" y="4" width="3" height="14"/></svg>',
                ],
                'quest' => [
                    'label' => 'ภารกิจ',
                    'count' => count($quizHistory),
                    'svg'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>',
                ],
                'reward' => [
                    'label' => 'รางวัล',
                    'count' => count($redemptions),
                    'svg'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M20 12v10H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
                ],
            ];
            foreach ($tabs as $key => $t):
            ?>
            <button id="tab-<?= $key ?>" class="hy-tab <?= $key === 'token' ? 'hy-tab--active' : '' ?>" type="button" data-hy-tab="<?= $key ?>" role="tab" aria-selected="<?= $key === 'token' ? 'true' : 'false' ?>" aria-controls="panel-<?= $key ?>" tabindex="<?= $key === 'token' ? '0' : '-1' ?>">
                <span class="hy-tab-content"><span class="hy-tab-icon"><?= $t['svg'] ?></span><?= $t['label'] ?></span>
                <span class="hy-tab-badge"><?= $t['count'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <div id="panel-token" role="tabpanel" aria-labelledby="tab-token">
        <?php if (empty($txAll)): ?>
        <div class="hy-empty-state">
            <p class="hy-empty-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <line x1="4" y1="20" x2="20" y2="20" stroke-width="2"/>
                    <rect x="6" y="11" width="3" height="7" stroke-width="2"/>
                    <rect x="11" y="7" width="3" height="11" stroke-width="2"/>
                    <rect x="16" y="4" width="3" height="14" stroke-width="2"/>
                </svg>
            </p>
            <p class="hy-empty-text">ยังไม่มีประวัติ Token</p>
        </div>
        <?php else: ?>
        <div class="hy-filter-container">
            <button class="hy-filter-btn hy-tab--active hy-tx-filter-btn" data-filter="all" type="button" aria-pressed="true">
                ทั้งหมด <span class="hy-tab-badge" id="hy-filter-count"><?= count($txAll) ?></span>
            </button>
            <button class="hy-filter-btn hy-tx-filter-btn" data-filter="earn" type="button" aria-pressed="false">
                <span class="hy-filter-color-positive">+</span> รับ Token
            </button>
            <button class="hy-filter-btn hy-tx-filter-btn" data-filter="spend" type="button" aria-pressed="false">
                <span class="hy-filter-color-negative">&minus;</span> ใช้ Token
            </button>
        </div>
        <div class="hy-table-wrap">
            <div class="hy-table-head hy-table-head--token">
                <span>รายการ</span>
                <span class="hy-table-head-align-right">Token</span>
                <span>วันที่</span>
            </div>
            <?php foreach ($txAll as $tx):
                $amt    = (int)$tx['amount'];
                $isEarn = $amt > 0;
                $amtDisp = ($isEarn ? '+' : '') . number_format($amt);
            ?>
            <div class="hy-tx-row" data-dir="<?= $isEarn ? 'earn' : 'spend' ?>" data-tx-id="<?= (int)$tx['tx_id'] ?>" data-hy-row-action="tx" tabindex="0" role="button">
                <div>
                    <p class="hy-row-title">
                        <?php
                        if ($tx['tx_type'] === 'admin_adjust') {
                            echo $isEarn ? 'ได้รับ Token' : 'ถูกหัก Token';
                        } else {
                            echo e(txTypeLabel($tx['tx_type']));
                        }
                        ?>
                    </p>
                    <?php if (!empty($tx['note'])): ?>
                    <p class="hy-row-subtitle"><?= e($tx['note']) ?></p>
                    <?php endif; ?>
                </div>
                <span class="hy-row-amount <?= $isEarn ? 'hy-token-amount--earn' : 'hy-token-amount--spend' ?>"><?= $amtDisp ?></span>
                <span class="hy-row-date"><?= date('d/m/y', strtotime($tx['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

        <div id="panel-quest" class="hy-hidden" role="tabpanel" aria-labelledby="tab-quest" hidden>
        <?php if (empty($quizHistory)): ?>
        <div class="hy-empty-state hy-empty-state--compact">
            <p class="hy-empty-text">ยังไม่มีประวัติการทำภารกิจ</p>
        </div>
        <?php else: ?>
        <div class="hy-table-wrap">
            <div class="hy-table-head hy-table-head--quest">
                <span>ภารกิจ</span>
                <span>ประเภท</span>
                <span>Token</span>
                <span>สถานะ</span>
            </div>
            <?php
            $qStatusStyle = [
                'auto_approved' => ['class'=>'hy-status-chip--approved', 'label'=>'ผ่าน'],
                'approved'      => ['class'=>'hy-status-chip--approved', 'label'=>'อนุมัติ'],
                'pending'       => ['class'=>'hy-status-chip--pending',  'label'=>'รอตรวจ'],
                'rejected'      => ['class'=>'hy-status-chip--rejected', 'label'=>'ไม่ผ่าน'],
            ];
            foreach ($quizHistory as $q):
                $qs = $qStatusStyle[$q['status']] ?? $qStatusStyle['pending'];
            ?>
            <div class="hy-tx-row hy-tx-row--quest" data-quest-id="<?= (int)$q['submission_id'] ?>" data-hy-row-action="quest" tabindex="0" role="button">
                <div>
                    <p class="hy-row-title"><?= e($q['challenge_title']) ?></p>
                    <p class="hy-row-subtitle">
                        <?= hyThaiDate($q['submitted_at']) ?>
                        <?php if (!empty($q['review_note'])): ?>
                        · <?= e($q['review_note']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="hy-row-date"><?= $q['challenge_type'] === 'quiz' ? 'Quiz' : 'Photo' ?></span>
                <span class="hy-rd-token-value <?= (int)$q['token_awarded'] > 0 ? '' : 'hy-wallet-card-color--count' ?>">
                    <?= (int)$q['token_awarded'] > 0 ? '+' . number_format((int)$q['token_awarded']) : '—' ?>
                </span>
                <span class="hy-status-chip <?= e($qs['class']) ?>"><?= $qs['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

        <div id="panel-reward" class="hy-hidden" role="tabpanel" aria-labelledby="tab-reward" hidden>
        <?php if (empty($redemptions)): ?>
        <div class="hy-empty-state hy-empty-state--compact">
            <p class="hy-empty-text">ยังไม่มีประวัติการแลกรางวัล</p>
        </div>
        <?php else: ?>
        <div class="hy-table-wrap">
            <?php
            $rdStyle = [
                'pending'   => ['class'=>'hy-status-chip--pending',   'label'=>'รอดำเนินการ'],
                'fulfilled' => ['class'=>'hy-status-chip--fulfilled', 'label'=>'มอบแล้ว'],
                'cancelled' => ['class'=>'hy-status-chip--cancelled', 'label'=>'ยกเลิก'],
            ];
            foreach ($redemptions as $rd):
                $rs = $rdStyle[$rd['status']] ?? $rdStyle['pending'];
                $rdCat = (string)($rd['category'] ?? 'general');
                $rdToneClass = 'hy-rd-icon--' . preg_replace('/[^a-z]/', '', strtolower($rdCat));
                if (!in_array($rdToneClass, ['hy-rd-icon--voucher','hy-rd-icon--leave','hy-rd-icon--merch','hy-rd-icon--perk','hy-rd-icon--general'], true)) {
                    $rdToneClass = 'hy-rd-icon--general';
                }
            ?>
            <div class="hy-rd-row" data-rd-id="<?= (int)$rd['redemption_id'] ?>" data-hy-row-action="reward" tabindex="0" role="button">
                <div class="hy-rd-main-grid">
                    <div class="hy-rd-main-info">
                        <span class="hy-rd-icon <?= e($rdToneClass) ?>"><?= hyRewardCategoryIconSvg($rdCat) ?></span>
                        <div class="hy-rd-title-wrap">
                            <p class="hy-rd-title"><?= e($rd['reward_title']) ?></p>
                            <?php if (!empty($rd['admin_note'])): ?>
                            <p class="hy-rd-note"><?= e($rd['admin_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hy-rd-token-wrap">
                        <img src="<?= BASE_URL ?>/assets/images/token.png" width="12" height="12" class="hy-rd-token-icon" alt="">
                        <span class="hy-rd-token-value"><?= (int)$rd['tokens_spent'] ?></span>
                    </div>
                    <span class="hy-row-date"><?= hyThaiDate($rd['redeemed_at']) ?></span>
                    <span class="hy-status-chip <?= e($rs['class']) ?>"><?= $rs['label'] ?></span>
                </div>

                <?php if ($rd['status'] === 'fulfilled' && !empty($rd['coupon_code'])): ?>
                <div class="hy-coupon-section">
                    <button class="hy-coupon-toggle-btn" title="แสดง/ซ่อนรหัสคูปอง" type="button" data-hy-coupon-id="<?= (int)$rd['redemption_id'] ?>" aria-expanded="false" aria-controls="hy-coupon-box-<?= (int)$rd['redemption_id'] ?>">
                        <svg id="hy-coupon-eye-<?= (int)$rd['redemption_id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="12" height="12">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span id="hy-coupon-lbl-<?= (int)$rd['redemption_id'] ?>">แสดงรหัสคูปอง</span>
                    </button>

                    <div id="hy-coupon-box-<?= (int)$rd['redemption_id'] ?>" class="hy-coupon-box">
                        <div class="hy-coupon-info">
                            <span class="hy-coupon-meta">อนุมัติโดย: <?= e($rd['processed_by_name'] ?? '—') ?></span>
                            <span class="hy-coupon-code"><?= e($rd['coupon_code']) ?></span>
                        </div>
                        <button id="hy-coupon-copy-<?= (int)$rd['redemption_id'] ?>" class="hy-coupon-copy-btn" title="คัดลอก" type="button" data-hy-copy-coupon="<?= e($rd['coupon_code']) ?>" data-hy-copy-id="<?= (int)$rd['redemption_id'] ?>" aria-label="คัดลอกรหัสคูปอง">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            คัดลอก
                        </button>
                    </div>
                </div>
                <?php elseif ($rd['status'] === 'pending' && !empty($rd['coupon_code'])): ?>
                <div class="hy-coupon-pending-msg">
                    <span class="hy-coupon-pending-icon">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="4" y="11" width="16" height="9" rx="2" ry="2" stroke-width="2"/>
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-width="2"/>
                        </svg>
                    </span>
                    <span class="hy-coupon-pending-text">รหัสคูปองจะปรากฏหลัง HR ยืนยันมอบรางวัล</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>

    </div><!-- /inner -->
</div><!-- /hy-history-wrap -->

<script>
const ALL_PANELS = ['token','quest','reward'];

function switchTab(tab) {
    ALL_PANELS.forEach(function(p) {
        var panel = document.getElementById('panel-' + p);
        var tabEl = document.getElementById('tab-' + p);
        if (panel) {
            panel.classList.toggle('hy-hidden', p !== tab);
            panel.hidden = p !== tab;
        }
        if (tabEl) {
            tabEl.classList.toggle('hy-tab--active', p === tab);
            tabEl.setAttribute('aria-selected', p === tab ? 'true' : 'false');
            tabEl.tabIndex = p === tab ? 0 : -1;
        }
    });
}

function filterTokens(dir) {
    var rows = document.querySelectorAll('.hy-tx-row[data-dir]');
    var count = 0;
    rows.forEach(function(row) {
        var show = (dir === 'all' || row.dataset.dir === dir);
        row.classList.toggle('hy-row-hidden', !show);
        if (show) count++;
    });
    var cEl = document.getElementById('hy-filter-count');
    if (cEl) cEl.textContent = count;
    document.querySelectorAll('.hy-tx-filter-btn').forEach(function(btn) {
        var active = btn.dataset.filter === dir;
        btn.classList.toggle('hy-tab--active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

document.addEventListener('keydown', function (event) {
    var tabBtn = event.target.closest('[data-hy-tab]');
    if (!tabBtn) return;
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

    event.preventDefault();
    var idx = ALL_PANELS.indexOf(tabBtn.dataset.hyTab);
    if (idx < 0) return;
    var nextIdx = event.key === 'ArrowRight'
        ? (idx + 1) % ALL_PANELS.length
        : (idx - 1 + ALL_PANELS.length) % ALL_PANELS.length;
    var nextTab = document.getElementById('tab-' + ALL_PANELS[nextIdx]);
    if (nextTab) nextTab.click();
});

function hyToggleCoupon(id) {
    var box = document.getElementById('hy-coupon-box-' + id);
    var label = document.getElementById('hy-coupon-lbl-' + id);
    var eye = document.getElementById('hy-coupon-eye-' + id);
    var toggle = document.querySelector('[data-hy-coupon-id="' + id + '"]');
    if (!box) return;
    var visible = box.classList.toggle('visible');
    if (label) label.textContent = visible ? 'ซ่อนรหัสคูปอง' : 'แสดงรหัสคูปอง';
    if (toggle) toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
    if (eye) {
        eye.innerHTML = visible
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}

window.hyToggleCoupon = hyToggleCoupon;

function hyRdToggleCoupon() {
    var box = document.getElementById('hyrd-coupon-box');
    var lbl = document.getElementById('hyrd-coupon-lbl');
    var toggle = document.querySelector('[data-hy-rd-coupon-toggle]');
    var visible = box.classList.toggle('visible');
    lbl.textContent = visible ? 'ซ่อนรหัสคูปอง' : 'แสดงรหัสคูปอง';
    if (toggle) toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
}

window.hycopyCoupon = function (code, id) {
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.getElementById('hy-coupon-copy-' + id);
        if (!btn) return;
        var original = btn.innerHTML;
        btn.textContent = 'คัดลอกแล้ว';
        setTimeout(function () { btn.innerHTML = original; }, 1500);
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
    $_rid    = (int)$_rd['redemption_id'];
    $_rdCat  = (string)($_rd['category'] ?? 'general');
    $_iconClass = 'hy-rd-icon--' . preg_replace('/[^a-z]/', '', strtolower($_rdCat));
    if (!in_array($_iconClass, ['hy-rd-icon--voucher','hy-rd-icon--leave','hy-rd-icon--merch','hy-rd-icon--perk','hy-rd-icon--general'], true)) {
        $_iconClass = 'hy-rd-icon--general';
    }
    $hyRdData[$_rid] = [
        'title'       => $_rd['reward_title'],
        'iconSvg'     => hyRewardCategoryIconSvg($_rdCat),
        'iconClass'   => $_iconClass,
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

<!-- ── Modal: Token Transaction ── -->
<div id="hy-tx-modal" class="hy-modal-ov" data-hy-modal="tx" role="dialog" aria-modal="true" aria-hidden="true">
    <div id="hy-tx-card" class="hy-modal-card hy-modal-card--token" tabindex="-1">
        <div class="hy-modal-hdr">
            <div class="hy-modal-hdr-content">
                <span class="hy-modal-hdr-icon">&#9672;</span>
                <span class="hy-modal-hdr-title">รายการ Token</span>
            </div>
            <button class="hy-modal-x" data-hy-close="tx" type="button" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div class="hy-modal-body">
            <div class="hy-modal-token-summary">
                <img src="<?= BASE_URL ?>/assets/images/token.png" width="28" height="28" class="hy-modal-token-icon" alt="">
                <div>
                    <p class="hy-modal-amount-label">จำนวน</p>
                    <p id="hytx-amount" class="hy-modal-amount-value"></p>
                </div>
            </div>
            <div class="hy-modal-grid">
                <div class="hy-ibox">
                    <p class="hy-ibox-label">ประเภท</p>
                    <p id="hytx-type" class="hy-ibox-value"></p>
                </div>
                <div class="hy-ibox">
                    <p class="hy-ibox-label">วันที่</p>
                    <p id="hytx-at" class="hy-ibox-value hy-ibox-value--small"></p>
                </div>
            </div>
            <div id="hytx-note-wrap" class="hy-ibox hy-hidden">
                <p class="hy-ibox-label">หมายเหตุ</p>
                <p id="hytx-note" class="hy-ibox-value hy-ibox-value--normal"></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Quest Submission ── -->
<div id="hy-quest-modal" class="hy-modal-ov" data-hy-modal="quest" role="dialog" aria-modal="true" aria-hidden="true">
    <div id="hy-quest-card" class="hy-modal-card hy-modal-card--quest" tabindex="-1">
        <div class="hy-modal-hdr">
            <div class="hy-modal-hdr-content">
                <span class="hy-modal-hdr-icon">&#9678;</span>
                <span class="hy-modal-hdr-title hy-modal-hdr-title--quest">ภารกิจ</span>
            </div>
            <button class="hy-modal-x" data-hy-close="quest" type="button" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div class="hy-modal-body">
            <div class="hy-modal-section">
                <p id="hyq-title" class="hy-quest-title"></p>
                <div class="hy-quest-meta-row">
                    <span id="hyq-ctype" class="hy-pill hy-pill--ctype"></span>
                    <span id="hyq-status" class="hy-pill"></span>
                </div>
            </div>
            <div class="hy-modal-grid">
                <div class="hy-ibox hy-ibox--accent">
                    <p class="hy-ibox-label">Token ที่ได้</p>
                    <p id="hyq-token" class="hy-quest-token-value"></p>
                </div>
                <div class="hy-ibox">
                    <p class="hy-ibox-label">วันที่ส่ง</p>
                    <p id="hyq-at" class="hy-ibox-value hy-ibox-value--small"></p>
                </div>
            </div>
            <div id="hyq-reviewer-wrap" class="hy-ibox hy-hidden">
                <p id="hyq-reviewer-lbl" class="hy-ibox-label">อนุมัติโดย</p>
                <p id="hyq-reviewer" class="hy-ibox-value"></p>
            </div>
            <div class="hy-ibox">
                <p class="hy-ibox-label">หมายเหตุ</p>
                <p id="hyq-note" class="hy-ibox-value hy-ibox-value--normal"></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Reward Redemption ── -->
<div id="hy-rd-modal" class="hy-modal-ov" data-hy-modal="rd" role="dialog" aria-modal="true" aria-hidden="true">
    <div id="hy-rd-card" class="hy-modal-card hy-modal-card--reward" tabindex="-1">
        <div class="hy-modal-hdr">
            <div class="hy-modal-hdr-content">
                <span class="hy-modal-hdr-icon">&#10022;</span>
                <span class="hy-modal-hdr-title">การแลกรางวัล</span>
            </div>
            <button class="hy-modal-x" data-hy-close="rd" type="button" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div class="hy-modal-body">
            <div class="hy-rd-modal-top">
                <span id="hyrd-icon" class="hy-rd-modal-icon"></span>
                <div class="hy-rd-modal-title-wrap">
                    <p id="hyrd-title" class="hy-rd-modal-title"></p>
                    <span id="hyrd-status" class="hy-status-chip"></span>
                </div>
            </div>
            <div class="hy-modal-grid">
                <div class="hy-ibox hy-ibox--accent">
                    <p class="hy-ibox-label">Token ที่ใช้</p>
                    <p id="hyrd-tokens" class="hy-rd-modal-token"></p>
                </div>
                <div class="hy-ibox">
                    <p class="hy-ibox-label">วันที่ขอแลก</p>
                    <p id="hyrd-req-at" class="hy-ibox-value hy-ibox-value--small"></p>
                </div>
                <div id="hyrd-proc-row" class="hy-ibox hy-rd-proc-row hy-hidden">
                    <p class="hy-ibox-label">วันที่ดำเนินการ</p>
                    <p id="hyrd-proc-at" class="hy-ibox-value hy-ibox-value--small"></p>
                    <p id="hyrd-proc-by" class="hy-rd-proc-by hy-hidden"></p>
                </div>
            </div>
            <div id="hyrd-note-wrap" class="hy-ibox hy-hidden">
                <p class="hy-ibox-label">หมายเหตุจาก HR</p>
                <p id="hyrd-note" class="hy-ibox-value hy-ibox-value--normal"></p>
            </div>
            <div id="hyrd-coupon-sec" class="hy-rd-coupon-sec">
                <button class="hy-rd-coupon-toggle" data-hy-rd-coupon-toggle type="button">
                    <span id="hyrd-coupon-lbl">แสดงรหัสคูปอง</span>
                </button>
                <div id="hyrd-coupon-box" class="hy-rd-coupon-box">
                    <p class="hy-coupon-meta">รหัสคูปอง</p>
                    <div class="hy-rd-coupon-row">
                        <p id="hyrd-coupon-code" class="hy-rd-coupon-code"></p>
                        <button id="hyrd-coupon-copy" class="hy-rd-coupon-copy" data-hy-rd-copy-coupon type="button">คัดลอก</button>
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
    rd:    ['hy-rd-modal',    'hy-rd-card']
};
var _hyLastFocus = null;

function _hyOpen(type) {
    var ids = _hyModalIds[type];
    if (!ids) return;
    var ov = document.getElementById(ids[0]);
    var card = document.getElementById(ids[1]);
    if (!ov || !card) return;
    _hyLastFocus = document.activeElement;
    ov.classList.remove('hy-ov-out');
    card.classList.remove('hy-ci-out');
    ov.classList.add('visible', 'hy-ov-in');
    card.classList.add('hy-ci-in');
    ov.setAttribute('aria-hidden', 'false');
    document.body.classList.add('hy-modal-open');
    var firstFocus = card.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (firstFocus) {
        setTimeout(function() { firstFocus.focus(); }, 0);
    } else {
        setTimeout(function() { card.focus(); }, 0);
    }
}

function closeHyModal(type) {
    var ids = _hyModalIds[type];
    if (!ids) return;
    var ov = document.getElementById(ids[0]);
    var card = document.getElementById(ids[1]);
    if (!ov || !card || !ov.classList.contains('visible')) return;
    ov.classList.remove('hy-ov-in');
    card.classList.remove('hy-ci-in');
    ov.classList.add('hy-ov-out');
    card.classList.add('hy-ci-out');
    ov.setAttribute('aria-hidden', 'true');
    setTimeout(function() {
        ov.classList.remove('visible', 'hy-ov-out');
        card.classList.remove('hy-ci-out');
        if (!document.querySelector('.hy-modal-ov.visible')) {
            document.body.classList.remove('hy-modal-open');
        }
        if (_hyLastFocus && typeof _hyLastFocus.focus === 'function') {
            _hyLastFocus.focus();
        }
    }, 160);
}

function hyQuestStatusClass(status) {
    if (status === 'auto_approved' || status === 'approved') return 'hy-pill--status-approved';
    if (status === 'rejected') return 'hy-pill--status-rejected';
    return 'hy-pill--status-pending';
}

function hyQuestStatusLabel(status) {
    if (status === 'auto_approved') return 'ผ่าน';
    if (status === 'approved') return 'อนุมัติ';
    if (status === 'rejected') return 'ไม่ผ่าน';
    return 'รอตรวจ';
}

function hyRewardStatusClass(status) {
    if (status === 'fulfilled') return 'hy-status-chip--fulfilled';
    if (status === 'cancelled') return 'hy-status-chip--cancelled';
    return 'hy-status-chip--pending';
}

function hyRewardStatusLabel(status) {
    if (status === 'fulfilled') return 'มอบแล้ว';
    if (status === 'cancelled') return 'ยกเลิก';
    return 'รอดำเนินการ';
}

function openHyTxModal(txId) {
    var d = _hyTxData[txId];
    if (!d) return;
    var isEarn = d.amount > 0;
    var amountEl = document.getElementById('hytx-amount');
    amountEl.textContent = (isEarn ? '+' : '') + d.amount.toLocaleString();
    amountEl.classList.toggle('hy-modal-amount-value--earn', isEarn);
    amountEl.classList.toggle('hy-modal-amount-value--spend', !isEarn);
    document.getElementById('hytx-type').textContent = d.type;
    document.getElementById('hytx-at').textContent = d.at;

    var noteWrap = document.getElementById('hytx-note-wrap');
    if (d.note) {
        document.getElementById('hytx-note').textContent = d.note;
        noteWrap.classList.remove('hy-hidden');
    } else {
        noteWrap.classList.add('hy-hidden');
    }
    _hyOpen('tx');
}

function openHyQuestModal(subId) {
    var d = _hyQuestData[subId];
    if (!d) return;
    document.getElementById('hyq-title').textContent = d.title;

    var ctypeEl = document.getElementById('hyq-ctype');
    ctypeEl.textContent = d.ctype === 'quiz' ? 'Quiz' : (d.ctype === 'strava' ? 'Strava' : 'Photo');

    var statusEl = document.getElementById('hyq-status');
    statusEl.className = 'hy-pill ' + hyQuestStatusClass(d.status);
    statusEl.textContent = hyQuestStatusLabel(d.status);

    var tokenEl = document.getElementById('hyq-token');
    if (d.token > 0) {
        tokenEl.textContent = '+' + d.token.toLocaleString() + ' Token';
        tokenEl.className = 'hy-quest-token-value hy-quest-token-value--has';
    } else {
        tokenEl.textContent = '—';
        tokenEl.className = 'hy-quest-token-value hy-quest-token-value--none';
    }
    document.getElementById('hyq-at').textContent = d.at;

    var reviewerWrap = document.getElementById('hyq-reviewer-wrap');
    if (d.reviewer) {
        document.getElementById('hyq-reviewer-lbl').textContent = d.status === 'rejected' ? 'ไม่อนุมัติโดย' : 'อนุมัติโดย';
        document.getElementById('hyq-reviewer').textContent = d.reviewer;
        reviewerWrap.classList.remove('hy-hidden');
    } else {
        reviewerWrap.classList.add('hy-hidden');
    }

    document.getElementById('hyq-note').textContent = d.note || '—';
    _hyOpen('quest');
}

function openHyRdModal(rdId) {
    var d = _hyRdData[rdId];
    if (!d) return;

    var iconEl = document.getElementById('hyrd-icon');
    iconEl.className = 'hy-rd-modal-icon ' + (d.iconClass || 'hy-rd-icon--general');
    iconEl.innerHTML = d.iconSvg;

    document.getElementById('hyrd-title').textContent = d.title;
    document.getElementById('hyrd-tokens').textContent = d.tokens.toLocaleString() + ' token';
    document.getElementById('hyrd-req-at').textContent = d.reqAt;

    var statusEl = document.getElementById('hyrd-status');
    statusEl.className = 'hy-status-chip ' + hyRewardStatusClass(d.status);
    statusEl.textContent = hyRewardStatusLabel(d.status);

    var processRow = document.getElementById('hyrd-proc-row');
    if (d.procAt) {
        document.getElementById('hyrd-proc-at').textContent = d.procAt;
        var procByEl = document.getElementById('hyrd-proc-by');
        if (d.procBy) {
            procByEl.textContent = 'โดย ' + d.procBy;
            procByEl.classList.remove('hy-hidden');
        } else {
            procByEl.classList.add('hy-hidden');
        }
        processRow.classList.remove('hy-hidden');
    } else {
        processRow.classList.add('hy-hidden');
    }

    var noteWrap = document.getElementById('hyrd-note-wrap');
    if (d.note) {
        document.getElementById('hyrd-note').textContent = d.note;
        noteWrap.classList.remove('hy-hidden');
    } else {
        noteWrap.classList.add('hy-hidden');
    }

    var couponSec = document.getElementById('hyrd-coupon-sec');
    var couponBox = document.getElementById('hyrd-coupon-box');
    var couponLbl = document.getElementById('hyrd-coupon-lbl');
    if (d.coupon) {
        document.getElementById('hyrd-coupon-code').textContent = d.coupon;
        couponLbl.textContent = 'แสดงรหัสคูปอง';
        couponBox.classList.remove('visible');
        couponSec.classList.add('visible');
    } else {
        couponSec.classList.remove('visible');
    }

    _hyOpen('rd');
}

function hyRdToggleCoupon() {
    var box = document.getElementById('hyrd-coupon-box');
    var lbl = document.getElementById('hyrd-coupon-lbl');
    var visible = box.classList.toggle('visible');
    lbl.textContent = visible ? 'ซ่อนรหัสคูปอง' : 'แสดงรหัสคูปอง';
}

function hyRdCopyCoupon() {
    var code = document.getElementById('hyrd-coupon-code').textContent.trim();
    var btn = document.getElementById('hyrd-coupon-copy');
    navigator.clipboard.writeText(code).then(function() {
        var original = btn.textContent;
        btn.textContent = 'คัดลอกแล้ว';
        btn.classList.add('is-success');
        setTimeout(function() {
            btn.textContent = original;
            btn.classList.remove('is-success');
        }, 1800);
    });
}

document.addEventListener('click', function(event) {
    var tabBtn = event.target.closest('[data-hy-tab]');
    if (tabBtn) {
        switchTab(tabBtn.dataset.hyTab);
        return;
    }

    var filterBtn = event.target.closest('.hy-tx-filter-btn');
    if (filterBtn) {
        filterTokens(filterBtn.dataset.filter || 'all');
        return;
    }

    var couponToggle = event.target.closest('[data-hy-coupon-id]');
    if (couponToggle) {
        event.preventDefault();
        event.stopPropagation();
        hyToggleCoupon(couponToggle.dataset.hyCouponId);
        return;
    }

    var couponCopy = event.target.closest('[data-hy-copy-coupon]');
    if (couponCopy) {
        event.preventDefault();
        event.stopPropagation();
        hycopyCoupon(couponCopy.dataset.hyCopyCoupon, couponCopy.dataset.hyCopyId);
        return;
    }

    var closeBtn = event.target.closest('[data-hy-close]');
    if (closeBtn) {
        closeHyModal(closeBtn.dataset.hyClose);
        return;
    }

    var modalOverlay = event.target.classList.contains('hy-modal-ov') ? event.target : null;
    if (modalOverlay && modalOverlay.dataset.hyModal) {
        closeHyModal(modalOverlay.dataset.hyModal);
        return;
    }

    if (event.target.closest('[data-hy-rd-coupon-toggle]')) {
        hyRdToggleCoupon();
        return;
    }

    if (event.target.closest('[data-hy-rd-copy-coupon]')) {
        hyRdCopyCoupon();
        return;
    }

    if (event.target.closest('[data-hy-coupon-id]') || event.target.closest('[data-hy-copy-coupon]')) {
        return;
    }

    var row = event.target.closest('[data-hy-row-action]');
    if (!row) return;
    if (row.dataset.hyRowAction === 'tx') openHyTxModal(row.dataset.txId);
    if (row.dataset.hyRowAction === 'quest') openHyQuestModal(row.dataset.questId);
    if (row.dataset.hyRowAction === 'reward') openHyRdModal(row.dataset.rdId);
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeHyModal('tx');
        closeHyModal('quest');
        closeHyModal('rd');
        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ') return;
    var row = event.target.closest('[data-hy-row-action]');
    if (!row) return;
    event.preventDefault();
    if (row.dataset.hyRowAction === 'tx') openHyTxModal(row.dataset.txId);
    if (row.dataset.hyRowAction === 'quest') openHyQuestModal(row.dataset.questId);
    if (row.dataset.hyRowAction === 'reward') openHyRdModal(row.dataset.rdId);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
