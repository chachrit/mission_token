<?php
/**
 * pages/dashboard.php
 * Employee dashboard — wallet, active quests, recent activity
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$employeeId   = (int)$_SESSION['employee_id'];
$employeeName = $_SESSION['full_name']        ?? '';
$department   = $_SESSION['department']       ?? '';

// ── Data ────────────────────────────────────────────────────
$wallet               = ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0];
$activeChallenges     = [];
$recentActivity       = [];
$leaderboard          = [];
$streak               = 0;
$myRank               = 0;
$myPendingRedemptions = 0;
$monthlyEarned        = 0;
$monthlyTarget        = 500;
$dataError            = null;

try {
    $pdo = getDB();

    $wallet           = getWalletInfo($employeeId);
    $activeChallenges = getActiveChallenges();
    $streak           = getActivityStreak($employeeId);
    $tenure           = getEmployeeTenure($employeeId);
    $recentActivity   = getRecentSubmissions($employeeId, 6);
    $leaderboard      = getLeaderboard(5);

    // My rank
    $rankStmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS my_rank
        FROM   token_wallets w
        JOIN   employees e ON e.employee_id = w.employee_id
        WHERE  e.role = 'employee' AND e.is_active = 1
          AND  w.total_earned > (
              SELECT ISNULL(total_earned, 0) FROM token_wallets WHERE employee_id = ?
          )
    ");
    $rankStmt->execute([$employeeId]);
    $myRank = (int)($rankStmt->fetch()['my_rank'] ?? 1);

    // My pending redemptions (table may not exist in all environments)
    try {
        $prStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM dbo.reward_redemptions WHERE employee_id = ? AND status = 'pending'");
        $prStmt->execute([$employeeId]);
        $myPendingRedemptions = (int)($prStmt->fetch()['c'] ?? 0);
    } catch (Throwable $ignored) {}

    // Tokens earned this month
    try {
        $mStmt = $pdo->prepare("
            SELECT ISNULL(SUM(cs.token_awarded), 0) AS monthly_earned
            FROM   challenge_submissions cs
            WHERE  cs.employee_id = ?
              AND  cs.status IN ('approved', 'auto_approved')
              AND  MONTH(cs.submitted_at) = MONTH(GETDATE())
              AND  YEAR(cs.submitted_at)  = YEAR(GETDATE())
        ");
        $mStmt->execute([$employeeId]);
        $monthlyEarned = (int)($mStmt->fetch()['monthly_earned'] ?? 0);
    } catch (Throwable $ignored) {}

    // Annotate each challenge with this user's submission status
    foreach ($activeChallenges as &$ch) {
        $stmt = $pdo->prepare("
            SELECT TOP 1 status, token_awarded
            FROM   challenge_submissions
            WHERE  employee_id  = ?
              AND  challenge_id = ?
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$employeeId, (int)$ch['challenge_id']]);
        $sub = $stmt->fetch();
        $ch['my_status']        = $sub ? $sub['status']        : null;
        $ch['my_token_awarded'] = $sub ? (int)$sub['token_awarded'] : 0;
    }
    unset($ch);

} catch (Throwable $e) {
    error_log('[MissionToken] dashboard load error: ' . $e->getMessage());
    $dataError = 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
}

// Greeting by time of day (Thai)
$hour = (int)date('G');
if ($hour < 12)      $greeting = 'อรุณสวัสดิ์';
elseif ($hour < 17)  $greeting = 'สวัสดีตอนบ่าย';
else                 $greeting = 'สวัสดีตอนเย็น';

$statusLabel = [
    'pending'       => ['text' => 'รอ Approve',  'color' => '#b45309', 'bg' => '#fffbeb', 'bar' => '#f59e0b'],
    'approved'      => ['text' => 'อนุมัติแล้ว', 'color' => '#166534', 'bg' => '#f0fdf4', 'bar' => '#22c55e'],
    'auto_approved' => ['text' => 'ผ่านแล้ว',    'color' => '#166534', 'bg' => '#f0fdf4', 'bar' => '#22c55e'],
    'rejected'      => ['text' => 'ไม่ผ่าน',     'color' => '#9f1239', 'bg' => '#fff1f2', 'bar' => '#f43f5e'],
];

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="ds-dashboard-wrap">

    <!-- ── Background aurora blobs ─────────────────────────── -->
    <div class="ds-aurora ds-aurora-1" aria-hidden="true"></div>
    <div class="ds-aurora ds-aurora-2" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 ds-page-inner">

        <?php if ($dataError): ?>
        <div class="mb-6 rounded-xl px-5 py-4 text-sm" style="background:rgba(210,89,42,0.10);border:1px solid rgba(210,89,42,0.28);color:#d2592a;">
            <?= e($dataError) ?>
        </div>
        <?php endif; ?>

        <!-- ── OPERATIVE ID CARD ──────────────────────────── -->
        <?php
            $progressPct = $monthlyTarget > 0
                ? min(100, (int)round($monthlyEarned / $monthlyTarget * 100))
                : 0;
        ?>
        <section class="ds-id-card">
            <div class="ds-id-corner ds-id-corner--tl" aria-hidden="true"></div>
            <div class="ds-id-corner ds-id-corner--tr" aria-hidden="true"></div>
            <div class="ds-id-corner ds-id-corner--bl" aria-hidden="true"></div>
            <div class="ds-id-corner ds-id-corner--br" aria-hidden="true"></div>

            <div class="ds-id-inner">

                <!-- Left: avatar + identity -->
                <div class="ds-id-left">
                    <div class="ds-id-avatar">
                        <?php $_dsAvatar = $_SESSION['avatar_url'] ?? ''; ?>
                        <?php if ($_dsAvatar): ?>
                        <img src="<?= BASE_URL ?>/uploads/avatars/<?= rawurlencode(basename($_dsAvatar)) ?>"
                             alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                        <span><?= mb_substr($employeeName, 0, 1, 'UTF-8') ?: '?' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ds-id-info">
                        <p class="ds-id-eyebrow"><?= e($greeting) ?><span class="ds-id-dot">·</span>OPERATIVE</p>
                        <h1 class="ds-id-name"><?= e($employeeName) ?></h1>
                        <?php if ($department): ?>
                        <p class="ds-id-dept"><?= e($department) ?></p>
                        <?php endif; ?>
                        <div class="ds-id-badges">
                            <?php if ($streak > 0): ?>
                            <span class="ds-badge-streak">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 0.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>
                                <?= $streak ?> DAY STREAK
                            </span>
                            <?php endif; ?>
                            <?php if ($myRank > 0): ?>
                            <span class="ds-badge-rank">RANK #<?= $myRank ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($tenure): ?>
                        <div class="ds-id-tenure">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(218,185,55,0.65)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span class="ds-id-tenure-num"><?= e($tenure['text']) ?></span>
                            <span class="ds-id-tenure-label">อายุงาน</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ds-id-vdiv"></div>

                <!-- Right: balance -->
                <div class="ds-id-right">
                    <p class="ds-id-bal-label">ASSET BALANCE</p>
                    <span class="ds-id-bal-num" data-countup="<?= (int)$wallet['balance'] ?>" data-dur="1800">0</span>
                    <p class="ds-id-bal-unit">TOKEN</p>
                    <div class="ds-id-substats">
                        <div>
                            <p class="ds-id-subval">+<?= formatTokens((int)$wallet['total_earned']) ?></p>
                            <p class="ds-id-sublbl">RECEIVED</p>
                        </div>
                        <div class="ds-id-sdiv"></div>
                        <div>
                            <p class="ds-id-subval ds-id-subval--spent"><?= formatTokens((int)$wallet['total_spent']) ?></p>
                            <p class="ds-id-sublbl">SPENT</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ── QUICK STATS ──────────────────────────────────── -->
        <?php $pendingQuestCount = count(array_filter($activeChallenges, fn($c) => $c['my_status'] === null)); ?>

        <div class="ds-quickstats-grid">

            <a href="<?= BASE_URL ?>/pages/challenges.php" class="ds-stat-card">
                <div class="ds-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.33 8 20.5v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><path d="M14 14.5c0-.83.67-1.5 1.5-1.5h5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-5c-.83 0-1.5-.67-1.5-1.5z"/><path d="M15.5 19H14v1.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"/><path d="M10 9.5C10 8.67 9.33 8 8.5 8h-5C2.67 8 2 8.67 2 9.5S2.67 11 3.5 11h5c.83 0 1.5-.67 1.5-1.5z"/><path d="M8.5 5H10V3.5C10 2.67 9.33 2 8.5 2S7 2.67 7 3.5 7.67 5 8.5 5z"/>
                    </svg>
                </div>
                <div>
                    <p class="ds-stat-eyebrow">เริ่มทำ</p>
                    <p class="ds-stat-title">ภารกิจ</p>
                    <p class="ds-stat-sub ds-stat-sub--green"><?= $pendingQuestCount ?> ภารกิจรอคุณอยู่</p>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/pages/rewards.php" class="ds-stat-card">
                <div class="ds-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </div>
                <div>
                    <p class="ds-stat-eyebrow">แลก Token ที่</p>
                    <p class="ds-stat-title">ร้านรางวัล</p>
                    <p class="ds-stat-sub">คงเหลือ <?= formatTokens((int)$wallet['balance']) ?> token</p>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/pages/history.php" class="ds-stat-card">
                <div class="ds-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <div>
                    <p class="ds-stat-eyebrow">ดู</p>
                    <p class="ds-stat-title">ประวัติการส่งงาน</p>
                    <p class="ds-stat-sub"><?= count($recentActivity) ?> รายการล่าสุด</p>
                </div>
            </a>

        </div>

        <!-- ── MISSION BRIEFS (quest scroll) ────────────────── -->
        <section class="ds-quests-section">
            <div class="ds-section-header">
                <div class="ds-section-hd-left">
                    <div class="ds-section-bar"></div>
                    <h2 class="ds-section-title">ภารกิจที่เปิดอยู่</h2>
                </div>
                <a href="<?= BASE_URL ?>/pages/challenges.php" class="ds-section-link">ดูทั้งหมด →</a>
            </div>

            <?php if ($activeChallenges): ?>
            <div id="quest-scroll-track"
                 onmousedown="questDragStart(event,this)"
                 onmousemove="questDragMove(event,this)"
                 onmouseup="questDragEnd(this)"
                 onmouseleave="questDragEnd(this)">
                <?php foreach ($activeChallenges as $ch):
                    $myStatus   = $ch['my_status'];
                    $isDone     = in_array($myStatus, ['approved', 'auto_approved'], true);
                    $isPending  = $myStatus === 'pending';
                    $isRejected = $myStatus === 'rejected';
                    $sBadgeClass = match(true) {
                        $isDone     => 'ds-quest-status-badge--done',
                        $isPending  => 'ds-quest-status-badge--pending',
                        $isRejected => 'ds-quest-status-badge--rejected',
                        default     => 'ds-quest-status-badge--new',
                    };
                    $sBadgeLabel = match(true) {
                        $isDone     => 'ผ่านแล้ว',
                        $isPending  => 'รอ Approve',
                        $isRejected => 'ไม่ผ่าน',
                        default     => '+ ใหม่',
                    };
                ?>
                <article class="ds-quest-card <?= ($isDone || $isRejected) ? 'ds-quest-card--dim' : '' ?>">
                    <div class="ds-quest-badges">
                        <span class="ds-quest-type-badge"><?= e(challengeTypeLabel((string)$ch['type'])) ?></span>
                        <span class="ds-quest-status-badge <?= $sBadgeClass ?>"><?= $sBadgeLabel ?></span>
                    </div>
                    <div class="ds-quest-body">
                        <h3 class="ds-quest-title"><?= e($ch['title']) ?></h3>
                        <?php if (!empty($ch['description'])): ?>
                        <p class="ds-quest-desc"><?= e((string)$ch['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="ds-quest-footer">
                        <div class="ds-quest-token">
                            <div class="ds-token-coin">
                                <span class="ds-token-coin-letter">T</span>
                            </div>
                            <span class="ds-token-amount">+<?= formatTokens((int)$ch['token_reward']) ?></span>
                        </div>
                        <?php if ($isPending): ?>
                        <span class="ds-quest-status-text ds-quest-status-text--pending">รอตรวจสอบ</span>
                        <?php elseif ($isDone): ?>
                        <span class="ds-quest-status-text ds-quest-status-text--done">สำเร็จแล้ว</span>
                        <?php elseif ($isRejected): ?>
                        <span class="ds-quest-status-text ds-quest-status-text--rejected">ไม่ผ่าน</span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="ds-empty-state">ไม่มีภารกิจเปิดรับในช่วงเวลานี้</div>
            <?php endif; ?>
        </section>

        <!-- ── MISSION LOG + RANKINGS ────────────────────────── -->
        <div id="dash-bottom-grid">

            <!-- Mission Log (Recent Activity) -->
            <section>
                <div class="ds-section-header">
                    <div class="ds-section-hd-left">
                        <div class="ds-section-bar"></div>
                        <h2 class="ds-section-title">กิจกรรมล่าสุด</h2>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/history.php" class="ds-section-link">ดูประวัติทั้งหมด →</a>
                </div>
                <?php if ($recentActivity): ?>
                <div class="ds-activity-table">
                    <div class="ds-activity-header">
                        <span>ภารกิจ</span><span>สถานะ</span><span>Token</span><span>วันที่</span>
                    </div>
                <?php foreach ($recentActivity as $row):
                        $sl2 = $statusLabel[$row['status']] ?? ['text' => $row['status']];
                        $statusClass = match($row['status']) {
                            'pending'                  => 'ds-activity-status--pending',
                            'approved', 'auto_approved' => 'ds-activity-status--approved',
                            'rejected'                 => 'ds-activity-status--rejected',
                            default                    => '',
                        };
                        $submittedAt = $row['submitted_at'];
                        if ($submittedAt instanceof DateTimeInterface) {
                            $dateStr = $submittedAt->format('d/m/y');
                        } elseif (is_string($submittedAt) && $submittedAt !== '') {
                            $ts = strtotime($submittedAt);
                            $dateStr = $ts ? date('d/m/y', $ts) : '-';
                        } else {
                            $dateStr = '-';
                        }
                        $awarded = (int)$row['token_awarded'];
                    ?>
                    <div class="ds-activity-row">
                        <div class="ds-activity-cell-title">
                            <p class="ds-activity-title-text"><?= e($row['challenge_title']) ?></p>
                            <p class="ds-activity-type"><?= $row['submission_type'] === 'quiz' ? 'Quiz' : 'ภาพถ่าย' ?></p>
                        </div>
                        <span class="ds-activity-status <?= $statusClass ?>"><?= $sl2['text'] ?></span>
                        <span class="ds-activity-token <?= $awarded > 0 ? 'ds-activity-token--earned' : 'ds-activity-token--none' ?>">
                            <?= $awarded > 0 ? '+' . formatTokens($awarded) : '—' ?>
                        </span>
                        <span class="ds-activity-date"><?= $dateStr ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="ds-empty-state">ยังไม่มีประวัติการส่งงาน</div>
                <?php endif; ?>
            </section>

            <!-- Rankings (Leaderboard) -->
            <section>
                <div class="ds-section-header" style="margin-bottom:1rem;">
                    <div class="ds-section-hd-left">
                        <div class="ds-section-bar"></div>
                        <h2 class="ds-section-title">อันดับ Token</h2>
                    </div>
                </div>
                <div class="ds-lb-card">
                    <?php if ($leaderboard):
                        $maxEarned = max(array_column($leaderboard, 'total_earned')) ?: 1;
                        foreach ($leaderboard as $lb):
                            $isMe  = (int)$lb['employee_id'] === $employeeId;
                            $rank  = (int)$lb['rank'];
                            $barW  = max(8, (int)round((int)$lb['total_earned'] / $maxEarned * 100));
                    ?>
                    <div class="ds-lb-row">
                        <div class="ds-lb-row-inner">
                            <?php if ($rank === 1): ?>
                            <span class="ds-lb-rank-crown" title="#1">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#dab937"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3H5v1h14v-1z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="ds-lb-rank"><?= $rank ?></span>
                            <?php endif; ?>
                            <div class="ds-lb-avatar <?= $isMe ? 'ds-lb-avatar--me' : '' ?>">
                                <?php if ($isMe): ?>
                                <span class="ds-lb-avatar-letter"><?= mb_substr($lb['full_name'] ?? '?', 0, 1, 'UTF-8') ?></span>
                                <?php else: ?>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#3a3e43" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <?php endif; ?>
                            </div>
                            <p class="ds-lb-name <?= $isMe ? 'ds-lb-name--me' : '' ?>">
                                <?php if ($isMe): ?>
                                    <?= e($lb['full_name']) ?><span class="ds-lb-name-you"> · คุณ</span>
                                <?php else: ?>
                                    <?= e(mb_substr($lb['full_name'] ?? '?', 0, 1, 'UTF-8')) ?><span class="ds-lb-name-censored">●●●●●●</span>
                                <?php endif; ?>
                            </p>
                            <span class="ds-lb-token <?= $isMe ? 'ds-lb-token--me' : '' ?>">
                                <?= formatTokens((int)$lb['total_earned']) ?>
                            </span>
                        </div>
                        <div class="ds-lb-bar-track">
                            <div class="ds-lb-bar-fill <?= $isMe ? 'ds-lb-bar-fill--me' : '' ?>" style="width:<?= $barW ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php
                        $inTop = array_filter($leaderboard, fn($l) => (int)$l['employee_id'] === $employeeId);
                        if (empty($inTop) && $myRank > 0):
                    ?>
                    <div class="ds-lb-myrank">
                        อันดับของคุณ: <strong>#<?= $myRank ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="ds-lb-empty">ยังไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </section>

        </div><!-- /dash-bottom-grid -->

        <script>
        (function () {
            /* ── Count-up ── */
            function fmtNum(n) {
                return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            function countUp(el, target, duration) {
                var startTime = null;
                function step(ts) {
                    if (!startTime) startTime = ts;
                    var p = Math.min((ts - startTime) / duration, 1);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = fmtNum(Math.round(eased * target));
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-countup]').forEach(function (el) {
                    var target = parseInt(el.dataset.countup, 10) || 0;
                    var dur    = parseInt(el.dataset.dur || '1400', 10);
                    el.textContent = '0';
                    countUp(el, target, dur);
                });
            });

            /* ── Quest drag-scroll ── */
            var _qdragging = false, _qstartX = 0, _qscrollLeft = 0;
            window.questDragStart = function (e, el) {
                _qdragging = true;
                _qstartX = e.pageX - el.offsetLeft;
                _qscrollLeft = el.scrollLeft;
                el.style.cursor = 'grabbing';
            };
            window.questDragMove = function (e, el) {
                if (!_qdragging) return;
                e.preventDefault();
                var x = e.pageX - el.offsetLeft;
                el.scrollLeft = _qscrollLeft - (x - _qstartX);
            };
            window.questDragEnd = function (el) {
                _qdragging = false;
                el.style.cursor = 'grab';
            };
        })();
        </script>

    </div><!-- /page-inner -->
</div><!-- /ds-dashboard-wrap -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
