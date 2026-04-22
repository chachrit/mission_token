<?php
/**
 * index.php — Public Home Page
 * Mission Token | JOURNAL Employee Gamification
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

$overview = [
    'active_employees'  => 0,
    'total_earned'      => 0,
    'active_challenges' => 0,
    'pending_reviews'   => 0,
    'submissions_today' => 0,
];
$activeChallenges = [];
$leaderboard = [];
$recentActivity = [];
$weeklyTrend = [];
$dataError = null;

try {
    $overview = getHomeOverviewStats();
    $activeChallenges = getActiveChallenges();
    $leaderboard = getLeaderboard(5);
    $recentActivity = getRecentActivity(6);
    $weeklyTrend = getWeeklyTokenTrend();
} catch (Throwable $e) {
    error_log('[MissionToken] homepage load error: ' . $e->getMessage());
    $dataError = 'ยังไม่สามารถโหลดข้อมูลภาพรวมจากฐานข้อมูลได้';
}

$topPerformer = $leaderboard[0] ?? null;
$challengeCount = count($activeChallenges);
$activityCount = count($recentActivity);
$trendMax = 0;
foreach ($weeklyTrend as $trend) {
    $trendMax = max($trendMax, (int)$trend['earned_tokens'], (int)$trend['submissions_count']);
}
$trendMax = max($trendMax, 1);

$formatDate = static function ($value, string $format = 'd M Y'): string {
    if ($value instanceof DateTimeInterface) {
        return $value->format($format);
    }
    if (is_string($value) && $value !== '') {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date($format, $timestamp);
        }
    }
    return '-';
};

$pageTitle  = 'หน้าแรก';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="home-page-wrap">

    <!-- ── HERO ──────────────────────────────────────────────── -->
    <section class="hero-shell rounded-[32px] px-8 py-10 shadow-journal lg:px-14 lg:py-14">
        <!-- Floating coins (right side decoration) -->
        <div class="hero-coins" aria-hidden="true">
            <div class="hcoin hcoin-1"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-2"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-3"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-4"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-5"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-6"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
        </div>

        <div class="relative z-10">
            <h1 class="text-4xl font-semibold tracking-[0.06em] text-j-dark sm:text-5xl lg:text-6xl">
                JOURNAL<br>
                <span class="text-j-gold">MISSION TOKEN</span>
            </h1>
            <p class="mt-4 max-w-lg text-sm leading-7 text-j-slate">
                สะสม Token จากภารกิจ พิชิตทุก challenge<br class="hidden sm:block">
                และเฝ้าดูยอดสะสมของคุณเติบโตขึ้นทุกวัน
            </p>

            <!-- Stat row -->
            <div class="mt-8 flex flex-wrap items-stretch gap-6 sm:gap-10">
                <div>
                    <p class="hero-stat-value text-j-gold"><?= formatTokens((int)$overview['total_earned']) ?></p>
                    <p class="hero-stat-label">TOKEN EARNED</p>
                </div>
                <div class="hero-stat-divider hidden sm:block"></div>
                <div>
                    <p class="hero-stat-value"><?= (int)$overview['active_challenges'] ?></p>
                    <p class="hero-stat-label">ACTIVE QUEST</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($dataError): ?>
        <section class="mt-4 rounded-2xl border border-[#edc3b2] bg-[#fff1ea] px-5 py-4 text-sm text-j-orange">
            <?= e($dataError) ?>
        </section>
    <?php endif; ?>

    <!-- ── STAT BLOCKS ────────────────────────────────────────── -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <article class="stat-block">
            <p class="stat-block-label">Quest ที่เปิดอยู่</p>
            <p class="stat-block-value" data-counter="<?= (int)$overview['active_challenges'] ?>"><?= (int)$overview['active_challenges'] ?></p>
            <p class="stat-block-sub">ภารกิจรอให้รับวันนี้</p>
        </article>
        <article class="stat-block">
            <p class="stat-block-label">ส่งงานวันนี้</p>
            <p class="stat-block-value" data-counter="<?= (int)$overview['submissions_today'] ?>"><?= (int)$overview['submissions_today'] ?></p>
            <p class="stat-block-sub">Submission ล่าสุดของทีม</p>
        </article>
    </section>

    <!-- ── QUEST BOARD + TOKEN FLOW ───────────────────────────── -->
    <section class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

        <!-- Quest Board -->
        <article class="quest-board rounded-[28px] p-6 shadow-soft">
            <div class="mb-6 flex items-end justify-between gap-3">
                <div>
                    <p class="quest-label">QUEST BOARD</p>
                    <h2 class="mt-1 text-2xl font-semibold text-j-dark">ภารกิจที่เปิดรับอยู่</h2>
                </div>
                <span class="quest-count-badge"><?= formatTokens($challengeCount) ?> active</span>
            </div>

            <?php if ($activeChallenges): ?>
                <div class="grid gap-4">
                    <?php foreach ($activeChallenges as $challenge): ?>
                        <div class="quest-card">
                            <div class="quest-card-inner">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="challenge-type-badge"><?= e(challengeTypeLabel((string)$challenge['type'])) ?></span>
                                    </div>
                                    <h3 class="text-base font-semibold leading-snug text-j-dark"><?= e($challenge['title']) ?></h3>
                                    <p class="mt-1 text-sm leading-6 text-j-slate"><?= e((string)$challenge['description']) ?></p>
                                    <p class="mt-2 text-xs text-j-slate">
                                        <?= e($formatDate($challenge['start_date'])) ?> — <?= e($formatDate($challenge['end_date'])) ?>
                                    </p>
                                </div>
                                <div class="quest-reward">
                                    <span class="quest-reward-coin">T</span>
                                    <p class="quest-reward-value">+<?= formatTokens((int)$challenge['token_reward']) ?></p>
                                    <p class="quest-reward-label">TOKEN</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-10 text-center text-sm text-j-slate">
                    ยังไม่มีภารกิจเปิดรับในช่วงเวลานี้
                </div>
            <?php endif; ?>
        </article>

        <!-- Token Flow (7-Day Trend) -->
        <article class="quest-board rounded-[28px] p-6 shadow-soft">
            <p class="quest-label">TOKEN FLOW</p>
            <h2 class="mb-6 mt-1 text-2xl font-semibold text-j-dark">7 วันล่าสุด</h2>

            <?php if ($weeklyTrend): ?>
                <div class="grid grid-cols-7 gap-2">
                    <?php foreach ($weeklyTrend as $trend): ?>
                        <?php
                        $earnedHeight     = max(8, (int)round(((int)$trend['earned_tokens']     / $trendMax) * 100));
                        $submissionHeight = max(8, (int)round(((int)$trend['submissions_count'] / $trendMax) * 100));
                        ?>
                        <div class="flex flex-col items-center gap-2">
                            <div class="flex h-28 items-end gap-0.5">
                                <div class="trend-bar w-3 bg-j-gold"     style="height:<?= $earnedHeight ?>px;"></div>
                                <div class="trend-bar w-3 bg-j-charcoal" style="height:<?= $submissionHeight ?>px;"></div>
                            </div>
                            <p class="text-center text-[10px] font-medium leading-tight text-j-charcoal">
                                <?= e($formatDate($trend['trend_date'], 'd/m')) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-5 flex flex-wrap gap-4 text-xs text-j-slate">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-j-gold"></span> Token ที่ได้รับ
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-j-charcoal"></span> งานที่ส่ง
                    </span>
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-10 text-center text-sm text-j-slate">
                    ยังไม่มีข้อมูล
                </div>
            <?php endif; ?>
        </article>

    </section>

</div><!-- end .home-page-wrap -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
