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
    <section style="background:linear-gradient(135deg,#fdfcdf 0%,#faf0cf 60%,#eeebe1 100%);
                    border:1px solid #e0ddd4; border-radius:28px; padding:2.5rem 3rem;
                    position:relative; overflow:hidden; margin-bottom:1.25rem;
                    box-shadow:0 6px 40px rgba(9,17,19,0.09);">
        <div style="position:absolute; top:-100px; right:-100px; width:560px; height:560px;
                    border-radius:50%; pointer-events:none;
                    background:radial-gradient(circle,rgba(218,185,55,0.18) 0%,transparent 60%);"></div>
        <div style="position:relative; z-index:1; display:flex; align-items:center;
                    gap:3rem; flex-wrap:wrap; justify-content:space-between;">

            <!-- Left: identity -->
            <div style="min-width:0;">
                <p style="font-size:0.68rem; letter-spacing:0.16em; text-transform:uppercase;
                           color:#6b6e77; margin:0 0 0.3rem;"><?= e($greeting) ?></p>
                <h1 style="font-size:2rem; font-weight:700; color:#091113;
                            margin:0 0 0.2rem; line-height:1.15; white-space:nowrap;">
                    <?= e($employeeName) ?>
                </h1>
                <?php if ($department): ?>
                <p style="font-size:0.85rem; color:#6b6e77; margin:0 0 1rem;"><?= e($department) ?></p>
                <?php else: ?>
                <div style="margin-bottom:1rem;"></div>
                <?php endif; ?>
                <!-- Badges row -->
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                    <?php if ($streak > 0): ?>
                    <span style="font-size:0.72rem; font-weight:600; letter-spacing:0.04em;
                                 color:#91700a; background:rgba(218,185,55,0.18);
                                 border:1px solid rgba(201,168,48,0.35);
                                 border-radius:999px; padding:0.32rem 0.9rem;">
                        Streak <?= $streak ?> วัน
                    </span>
                    <?php endif; ?>
                    <?php if ($myRank > 0): ?>
                    <span style="font-size:0.72rem; font-weight:600; letter-spacing:0.04em;
                                 color:#6b6e77; background:rgba(9,17,19,0.05);
                                 border:1px solid rgba(9,17,19,0.1);
                                 border-radius:999px; padding:0.32rem 0.9rem;">
                        อันดับ #<?= $myRank ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: balance + stats -->
            <div style="flex-shrink:0; display:flex; align-items:center; gap:2.5rem;">
                <!-- Balance block -->
                <div>
                    <p style="font-size:0.62rem; letter-spacing:0.14em; text-transform:uppercase;
                               color:#6b6e77; margin:0 0 0.2rem; text-align:right;">Token คงเหลือ</p>
                    <span style="font-size:4.5rem; font-weight:800; color:#c9a830;
                                 letter-spacing:-0.04em; line-height:1; display:block; text-align:right;"
                          data-countup="<?= (int)$wallet['balance'] ?>" data-dur="1800">0</span>
                    <p style="font-size:0.62rem; letter-spacing:0.12em; text-transform:uppercase;
                               color:#6b6e77; text-align:right; margin:0.15rem 0 0;">Token</p>
                </div>
                <!-- Divider -->
                <div style="width:1px; height:80px; background:#dedad0; flex-shrink:0;"></div>
                <!-- Sub stats -->
                <div style="display:flex; gap:2rem; align-items:flex-start;">
                    <div>
                        <p style="font-size:1.3rem; font-weight:700; color:#091113; margin:0; line-height:1;">
                            +<?= formatTokens((int)$wallet['total_earned']) ?>
                        </p>
                        <p style="font-size:0.6rem; text-transform:uppercase; letter-spacing:0.08em;
                                   color:#6b6e77; margin:0.3rem 0 0;">ได้รับทั้งหมด</p>
                    </div>
                    <div style="width:1px; height:48px; background:#dedad0; flex-shrink:0; margin-top:2px;"></div>
                    <div>
                        <p style="font-size:1.3rem; font-weight:700; color:#3a3e43; margin:0; line-height:1;">
                            <?= formatTokens((int)$wallet['total_spent']) ?>
                        </p>
                        <p style="font-size:0.6rem; text-transform:uppercase; letter-spacing:0.08em;
                                   color:#6b6e77; margin:0.3rem 0 0;">ใช้ไปแล้ว</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ── QUICK STATS ──────────────────────────────────────── -->
    <?php $pendingQuestCount = count(array_filter($activeChallenges, fn($c) => $c['my_status'] === null)); ?>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
                gap:0.875rem; margin-bottom:2rem;">

        <!-- ภารกิจ -->
        <a href="<?= BASE_URL ?>/pages/challenges.php"
           style="background:white; border:1px solid #e6e2d6; border-radius:16px;
                  padding:1.1rem 1.25rem; text-decoration:none;
                  display:flex; align-items:center; gap:1rem;
                  transition:transform 0.18s,box-shadow 0.18s,border-color 0.18s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(9,17,19,0.09)';this.style.borderColor='#dab937'"
           onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='#e6e2d6'">
            <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                        background:#091113; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.33 8 20.5v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><path d="M14 14.5c0-.83.67-1.5 1.5-1.5h5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-5c-.83 0-1.5-.67-1.5-1.5z"/><path d="M15.5 19H14v1.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"/><path d="M10 9.5C10 8.67 9.33 8 8.5 8h-5C2.67 8 2 8.67 2 9.5S2.67 11 3.5 11h5c.83 0 1.5-.67 1.5-1.5z"/><path d="M8.5 5H10V3.5C10 2.67 9.33 2 8.5 2S7 2.67 7 3.5 7.67 5 8.5 5z"/>
                </svg>
            </div>
            <div>
                <p style="font-size:0.62rem; text-transform:uppercase; letter-spacing:0.1em;
                           color:#6b6e77; margin:0 0 0.1rem;">เริ่มทำ</p>
                <p style="font-size:0.95rem; font-weight:700; color:#091113; margin:0 0 0.15rem;
                           line-height:1.2;">ภารกิจ</p>
                <p style="font-size:0.72rem; color:#518e5c; margin:0; font-weight:500;">
                    <?= $pendingQuestCount ?> ภารกิจรอคุณอยู่
                </p>
            </div>
        </a>

        <!-- ร้านรางวัล -->
        <a href="<?= BASE_URL ?>/pages/rewards.php"
           style="background:white; border:1px solid #e6e2d6; border-radius:16px;
                  padding:1.1rem 1.25rem; text-decoration:none;
                  display:flex; align-items:center; gap:1rem;
                  transition:transform 0.18s,box-shadow 0.18s,border-color 0.18s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(9,17,19,0.09)';this.style.borderColor='#dab937'"
           onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='#e6e2d6'">
            <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                        background:#091113; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </div>
            <div>
                <p style="font-size:0.62rem; text-transform:uppercase; letter-spacing:0.1em;
                           color:#6b6e77; margin:0 0 0.1rem;">แลก Token ที่</p>
                <p style="font-size:0.95rem; font-weight:700; color:#091113; margin:0 0 0.15rem;
                           line-height:1.2;">ร้านรางวัล</p>
                <p style="font-size:0.72rem; color:#6b6e77; margin:0;">
                    คงเหลือ <?= formatTokens((int)$wallet['balance']) ?> token
                </p>
            </div>
        </a>

        <!-- ประวัติ -->
        <a href="<?= BASE_URL ?>/pages/history.php"
           style="background:white; border:1px solid #e6e2d6; border-radius:16px;
                  padding:1.1rem 1.25rem; text-decoration:none;
                  display:flex; align-items:center; gap:1rem;
                  transition:transform 0.18s,box-shadow 0.18s,border-color 0.18s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(9,17,19,0.09)';this.style.borderColor='#dab937'"
           onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='#e6e2d6'">
            <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                        background:#091113; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dab937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
            </div>
            <div>
                <p style="font-size:0.62rem; text-transform:uppercase; letter-spacing:0.1em;
                           color:#6b6e77; margin:0 0 0.1rem;">ดู</p>
                <p style="font-size:0.95rem; font-weight:700; color:#091113; margin:0 0 0.15rem;
                           line-height:1.2;">ประวัติการส่งงาน</p>
                <p style="font-size:0.72rem; color:#6b6e77; margin:0;">
                    <?= count($recentActivity) ?> รายการล่าสุด
                </p>
            </div>
        </a>

    </div>

    <!-- ── ACTIVE QUESTS (full width) ──────────────────────── -->
    <section style="margin-bottom:2rem;">
        <div style="display:flex; align-items:center; justify-content:space-between;
                    gap:1rem; margin-bottom:1rem;">
            <h2 class="section-title">ภารกิจที่เปิดอยู่</h2>
            <a href="<?= BASE_URL ?>/pages/challenges.php"
               style="font-size:0.8rem; font-weight:500; text-decoration:none;"
               onmouseover="this.style.textDecoration='underline'"
               onmouseout="this.style.textDecoration='none'">ดูทั้งหมด →</a>
        </div>

            <?php if ($activeChallenges): ?>
            <div id="quest-scroll-track"
                 style="display:flex; gap:0.875rem; overflow-x:auto; scroll-behavior:smooth;
                        padding-bottom:0.5rem; cursor:grab; user-select:none;"
                 onmousedown="questDragStart(event,this)"
                 onmousemove="questDragMove(event,this)"
                 onmouseup="questDragEnd(this)"
                 onmouseleave="questDragEnd(this)">
                <style>
                    #quest-scroll-track::-webkit-scrollbar { height:4px; }
                    #quest-scroll-track::-webkit-scrollbar-track { background:transparent; }
                    #quest-scroll-track::-webkit-scrollbar-thumb { background:#dedad0; border-radius:999px; }
                    #quest-scroll-track::-webkit-scrollbar-thumb:hover { background:#c9a830; }
                </style>
                <?php foreach ($activeChallenges as $ch):
                    $myStatus   = $ch['my_status'];
                    $isDone     = in_array($myStatus, ['approved', 'auto_approved'], true);
                    $isPending  = $myStatus === 'pending';
                    $isRejected = $myStatus === 'rejected';
                    if ($isDone)         $sBadge = ['label' => 'ผ่านแล้ว',   'bgLight' => '#f0fdf4', 'textLight' => '#166534'];
                    elseif ($isPending)  $sBadge = ['label' => 'รอ Approve', 'bgLight' => '#fffbeb', 'textLight' => '#92400e'];
                    elseif ($isRejected) $sBadge = ['label' => 'ไม่ผ่าน',    'bgLight' => '#fff1f2', 'textLight' => '#9f1239'];
                    else                 $sBadge = ['label' => '+ ใหม่',      'bgLight' => '#fefce8', 'textLight' => '#854d0e'];
                ?>
                <article style="background:#fff; border:1px solid #e6e2d6; border-radius:16px;
                                padding:1.25rem; flex:0 0 220px; display:flex; flex-direction:column;
                                gap:0.75rem; transition:transform 0.18s,box-shadow 0.18s,border-color 0.18s;
                                <?= ($isDone || $isRejected) ? 'opacity:0.5;' : '' ?>"
                         onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 28px rgba(9,17,19,0.09)';this.style.borderColor='#dab937'"
                         onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='#e6e2d6'">
                    <!-- Badges -->
                    <div style="display:flex; flex-wrap:wrap; gap:0.35rem;">
                        <span style="font-size:0.6rem; font-weight:700; letter-spacing:0.07em;
                                     text-transform:uppercase; padding:0.2rem 0.6rem;
                                     border-radius:6px; background:#eeebe1; color:#3a3e43;">
                            <?= e(challengeTypeLabel((string)$ch['type'])) ?>
                        </span>
                        <span style="font-size:0.6rem; font-weight:700; letter-spacing:0.05em;
                                     padding:0.2rem 0.6rem; border-radius:6px;
                                     background:<?= $sBadge['bgLight'] ?>; color:<?= $sBadge['textLight'] ?>;">
                            <?= $sBadge['label'] ?>
                        </span>
                    </div>
                    <!-- Title + description -->
                    <div style="flex:1;">
                        <h3 style="font-size:0.95rem; font-weight:700; color:#091113; margin:0 0 0.4rem;
                                   line-height:1.35;">
                            <?= e($ch['title']) ?>
                        </h3>
                        <?php if (!empty($ch['description'])): ?>
                        <p style="font-size:0.76rem; color:#6b6e77; margin:0; line-height:1.5;
                                  display:-webkit-box; -webkit-line-clamp:2;
                                  -webkit-box-orient:vertical; overflow:hidden;">
                            <?= e((string)$ch['description']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <!-- Footer: token -->
                    <div style="display:flex; align-items:center; justify-content:space-between;
                                padding-top:0.75rem; border-top:1px solid #ece9e0;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div style="width:22px; height:22px; border-radius:50%; flex-shrink:0;
                                        background:rgba(201,168,48,0.12);
                                        display:flex; align-items:center; justify-content:center;">
                                <span style="font-size:0.6rem; font-weight:800; color:#c9a830;">T</span>
                            </div>
                            <span style="font-size:1rem; font-weight:800; color:#c9a830;
                                         letter-spacing:-0.02em;">+<?= formatTokens((int)$ch['token_reward']) ?></span>
                        </div>
                        <?php if ($isPending): ?>
                        <span style="font-size:0.72rem; font-weight:600; color:#92400e;">รอตรวจสอบ</span>
                        <?php elseif ($isDone): ?>
                        <span style="font-size:0.72rem; font-weight:600; color:#166534;">สำเร็จแล้ว</span>
                        <?php elseif ($isRejected): ?>
                        <span style="font-size:0.72rem; font-weight:600; color:#9f1239;">ไม่ผ่าน</span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="border-radius:14px; border:1.5px dashed #d4d0c8; padding:3rem 1rem;
                        text-align:center; font-size:0.85rem; color:#6b6e77;">
                ไม่มีภารกิจเปิดรับในช่วงเวลานี้
            </div>
            <?php endif; ?>
    </section>

    <!-- ── LEADERBOARD + RECENT ACTIVITY ────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 300px; gap:1.5rem;
                align-items:start;" id="dash-bottom-grid">

        <!-- Recent Activity -->
        <section>
            <div style="display:flex; align-items:center; justify-content:space-between;
                        gap:1rem; margin-bottom:1rem;">
                <h2 class="section-title">กิจกรรมล่าสุด</h2>
                <a href="<?= BASE_URL ?>/pages/history.php"
                   style="font-size:0.8rem; font-weight:500; text-decoration:none;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'">ดูประวัติทั้งหมด →</a>
            </div>
            <?php if ($recentActivity): ?>
            <div style="background:white; border:1px solid #e6e2d6; border-radius:16px; overflow:hidden;">
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                            gap:1rem; padding:0.6rem 1.25rem;
                            border-bottom:1px solid #e6e2d6;
                            font-size:0.65rem; font-weight:600; letter-spacing:0.08em;
                            text-transform:uppercase; color:#6b6e77;">
                    <span>ภารกิจ</span><span>สถานะ</span><span>Token</span><span>วันที่</span>
                </div>
            <?php foreach ($recentActivity as $row):
                    $sl2 = $statusLabel[$row['status']] ?? ['text' => $row['status'], 'color' => '#6b6e77', 'bg' => '#eeebe1'];
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
                <div style="display:grid; grid-template-columns:1fr auto auto auto;
                            gap:1rem; padding:0.8rem 1.25rem; align-items:center;
                            border-bottom:1px solid #ece9e0; transition:background 0.15s;"
                     onmouseover="this.style.background='#faf8f2'"
                     onmouseout="this.style.background=''">
                    <div style="min-width:0;">
                        <p style="font-size:0.85rem; font-weight:500; color:#091113; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($row['challenge_title']) ?>
                        </p>
                        <p style="font-size:0.68rem; color:#6b6e77; margin:0.08rem 0 0;">
                            <?= $row['submission_type'] === 'quiz' ? 'Quiz' : 'ภาพถ่าย' ?>
                        </p>
                    </div>
                    <span style="font-size:0.68rem; font-weight:600; padding:0.18rem 0.6rem;
                                 border-radius:6px; white-space:nowrap;
                                 background:<?= $sl2['bg'] ?>; color:<?= $sl2['color'] ?>;">
                        <?= $sl2['text'] ?>
                    </span>
                    <span style="font-size:0.88rem; font-weight:700; white-space:nowrap;
                                 color:<?= $awarded > 0 ? '#c9a830' : '#6b6e77' ?>;">
                        <?= $awarded > 0 ? '+' . formatTokens($awarded) : '—' ?>
                    </span>
                    <span style="font-size:0.75rem; color:#6b6e77; white-space:nowrap;"><?= $dateStr ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="border-radius:14px; border:1.5px dashed #cecdcd; padding:3rem 1rem;
                        text-align:center; font-size:0.85rem; color:#6b6e77;">
                ยังไม่มีประวัติการส่งงาน
            </div>
            <?php endif; ?>
        </section>

        <!-- Leaderboard -->
        <section>
            <div style="margin-bottom:1rem;">
                <h2 class="section-title">อันดับ Token</h2>
            </div>
            <div style="background:white; border:1px solid #e6e2d6; border-radius:16px; padding:1.25rem;">
                <?php if ($leaderboard):
                    $maxEarned = max(array_column($leaderboard, 'total_earned')) ?: 1;
                    $lbIdx = 0;
                    foreach ($leaderboard as $lb):
                        $isMe  = (int)$lb['employee_id'] === $employeeId;
                        $rank  = (int)$lb['rank'];
                        $barW  = max(8, (int)round((int)$lb['total_earned'] / $maxEarned * 100));
                        $lbIdx++;
                ?>
                <div style="margin-bottom:<?= $lbIdx < count($leaderboard) ? '1rem' : '0' ?>;">
                    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.3rem;">
                        <span style="font-size:0.65rem; font-weight:700; color:#6b6e77;
                                     width:18px; flex-shrink:0; text-align:center;"><?= $rank ?></span>
                        <div style="width:26px; height:26px; border-radius:50%; flex-shrink:0;
                                    background:<?= $isMe ? '#dab937' : '#eeebe1' ?>;
                                    display:flex; align-items:center; justify-content:center;">
                            <?php if ($isMe): ?>
                            <span style="font-size:0.72rem; font-weight:700; color:#091113;">
                                <?= mb_substr($lb['full_name'] ?? '?', 0, 1, 'UTF-8') ?>
                            </span>
                            <?php else: ?>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <?php endif; ?>
                        </div>
                        <p style="flex:1; font-size:0.8rem; font-weight:<?= $isMe ? '700' : '500' ?>;
                                  color:<?= $isMe ? '#091113' : '#9ca3af' ?>; margin:0; min-width:0;
                                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?php if ($isMe): ?>
                                <?= e($lb['full_name']) ?><span style="color:#dab937;"> · คุณ</span>
                            <?php else: ?>
                                <?= e(mb_substr($lb['full_name'] ?? '?', 0, 1, 'UTF-8')) ?><span style="color:#9ca3af;">●●●●●●</span>
                            <?php endif; ?>
                        </p>
                        <span style="font-size:0.78rem; font-weight:700; flex-shrink:0;
                                     color:<?= $isMe ? '#c9a830' : '#3a3e43' ?>;">
                            <?= formatTokens((int)$lb['total_earned']) ?>
                        </span>
                    </div>
                    <div style="margin-left:44px; height:4px; background:#eeebe1;
                                border-radius:999px; overflow:hidden;">
                        <div style="height:100%; width:<?= $barW ?>%; border-radius:999px;
                                    background:<?= $isMe ? 'linear-gradient(90deg,#dab937,#f0d060)' : '#d4d0c8' ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php
                    $inTop = array_filter($leaderboard, fn($l) => (int)$l['employee_id'] === $employeeId);
                    if (empty($inTop) && $myRank > 0):
                ?>
                <div style="margin-top:1rem; padding-top:0.75rem; border-top:1px solid #e6e2d6;
                             text-align:center; font-size:0.75rem; color:#6b6e77;">
                    อันดับของคุณ: <strong style="color:#c9a830;">#<?= $myRank ?></strong>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div style="padding:2rem; text-align:center; font-size:0.85rem; color:#6b6e77;">ยังไม่มีข้อมูล</div>
                <?php endif; ?>
            </div>
        </section>

    </div><!-- /dash-bottom-grid -->

    <style>
        @media (max-width: 860px) {
            #dash-bottom-grid { grid-template-columns: 1fr !important; }
        }
    </style>

    <script>
    (function () {
        /* ── Count-up ── */
        function fmtNum(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function countUp(el, target, duration) {
            var prefix = el.dataset.prefix || '';
            var startTime = null;
            function step(ts) {
                if (!startTime) startTime = ts;
                var p = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = prefix + fmtNum(Math.round(eased * target));
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-countup]').forEach(function (el) {
                var target = parseInt(el.dataset.countup, 10) || 0;
                var dur    = parseInt(el.dataset.dur || '1400', 10);
                var prefix = el.dataset.prefix || '';
                el.textContent = prefix + '0';
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
