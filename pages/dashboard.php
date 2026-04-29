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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($dataError): ?>
    <div class="mb-6 rounded-xl border border-[#edc3b2] bg-[#fff1ea] px-5 py-4 text-sm text-j-orange">
        <?= e($dataError) ?>
    </div>
    <?php endif; ?>

    <!-- ── WALLET CARD ──────────────────────────────────── -->
    <?php
        $progressPct = $monthlyTarget > 0
            ? min(100, (int)round($monthlyEarned / $monthlyTarget * 100))
            : 0;
    ?>
    <section class="ds-wallet-card">
        <div class="ds-wallet-glow"></div>
        <div class="ds-wallet-inner">

            <!-- Left: identity -->
            <div class="ds-wallet-left">
                <p class="ds-wallet-greeting"><?= e($greeting) ?></p>
                <h1 class="ds-wallet-name"><?= e($employeeName) ?></h1>
                <?php if ($department): ?>
                <p class="ds-wallet-dept"><?= e($department) ?></p>
                <?php else: ?>
                <div class="ds-wallet-dept-spacer"></div>
                <?php endif; ?>
                <div class="ds-wallet-badges">
                    <?php if ($streak > 0): ?>
                    <span class="ds-badge-streak">Streak <?= $streak ?> วัน</span>
                    <?php endif; ?>
                    <?php if ($myRank > 0): ?>
                    <span class="ds-badge-rank">อันดับ #<?= $myRank ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: balance + stats -->
            <div class="ds-wallet-right">
                <div>
                    <p class="ds-balance-label">Token คงเหลือ</p>
                    <span class="ds-balance-number"
                          data-countup="<?= (int)$wallet['balance'] ?>" data-dur="1800">0</span>
                    <p class="ds-balance-unit">Token</p>
                </div>
                <div class="ds-vdivider-lg"></div>
                <div class="ds-substats">
                    <div>
                        <p class="ds-substat-value">+<?= formatTokens((int)$wallet['total_earned']) ?></p>
                        <p class="ds-substat-label">ได้รับทั้งหมด</p>
                    </div>
                    <div class="ds-vdivider-sm"></div>
                    <div>
                        <p class="ds-substat-value ds-substat-value--spent"><?= formatTokens((int)$wallet['total_spent']) ?></p>
                        <p class="ds-substat-label">ใช้ไปแล้ว</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ── QUICK STATS ──────────────────────────────────────── -->
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

    <!-- ── ACTIVE QUESTS (full width) ──────────────────────── -->
    <section class="ds-quests-section">
        <div class="ds-section-header">
            <h2 class="section-title">ภารกิจที่เปิดอยู่</h2>
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

    <!-- ── LEADERBOARD + RECENT ACTIVITY ────────────────────── -->
    <div id="dash-bottom-grid">

        <!-- Recent Activity -->
        <section>
            <div class="ds-section-header">
                <h2 class="section-title">กิจกรรมล่าสุด</h2>
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

        <!-- Leaderboard -->
        <section>
            <div class="mb-4">
                <h2 class="section-title">อันดับ Token</h2>
            </div>
            <div class="ds-lb-card">
                <?php if ($leaderboard):
                    $maxEarned = max(array_column($leaderboard, 'total_earned')) ?: 1;
                    $lbIdx = 0;
                    foreach ($leaderboard as $lb):
                        $isMe  = (int)$lb['employee_id'] === $employeeId;
                        $rank  = (int)$lb['rank'];
                        $barW  = max(8, (int)round((int)$lb['total_earned'] / $maxEarned * 100));
                        $lbIdx++;
                ?>
                <div class="ds-lb-row">
                    <div class="ds-lb-row-inner">
                        <span class="ds-lb-rank"><?= $rank ?></span>
                        <div class="ds-lb-avatar <?= $isMe ? 'ds-lb-avatar--me' : '' ?>">
                            <?php if ($isMe): ?>
                            <span class="ds-lb-avatar-letter"><?= mb_substr($lb['full_name'] ?? '?', 0, 1, 'UTF-8') ?></span>
                            <?php else: ?>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
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

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
