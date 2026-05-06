<?php
/**
 * index.php — Public Home Page
 * Mission Token | JOURNAL Employee Gamification
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

$overview = [
    'active_employees' => 0,
    'team_balance' => 0,
    'team_earned' => 0,
    'active_challenges' => 0,
    'pending_reviews' => 0,
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
    $recentActivity = getRecentTeamActivity(6);
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
    <div id="hero" class="home-slide overflow-hidden">

        <!-- Left decoration (large screen only) -->
        <div class="hero-deco-left hidden lg:block" aria-hidden="true">
            <div class="hero-deco-vline"></div>
            <div class="hero-deco-orb hdo-1"></div>
            <div class="hero-deco-orb hdo-2"></div>
            <div class="hero-deco-orb hdo-3"></div>
            <div class="hero-feat-tag hft-1">LEADERBOARD</div>
            <div class="hero-feat-tag hft-2">DAILY QUEST</div>
            <div class="hero-feat-tag hft-3">EARN TOKEN</div>
        </div>

        <!-- Floating coins (right) -->
        <div class="hero-coins" aria-hidden="true">
            <div class="hcoin hcoin-1"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-2"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-3"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-4"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-5"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
            <div class="hcoin hcoin-6"><img src="<?= BASE_URL ?>/assets/images/token.png" alt=""></div>
        </div>

        <!-- Hero content (always centered) -->
        <div class="relative z-10 max-w-2xl mx-auto text-center px-6 py-16">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="JOURNAL" class="hero-logo mx-auto mb-6">
            <h1 class="text-5xl font-semibold tracking-[0.04em] text-j-ivory sm:text-7xl lg:text-8xl leading-tight">
                MISSION<br>
                <span class="text-j-gold">TOKEN</span>
            </h1>
            <p class="mt-6 text-base leading-8 text-j-slate max-w-md mx-auto">
                สะสม Token จากภารกิจ พิชิตทุก challenge<br class="hidden sm:block">
                และเฝ้าดูยอดสะสมของทีมเติบโตขึ้นทุกวัน
            </p>

            <!-- Stat row -->
            <div class="mt-10 flex flex-wrap justify-center items-stretch gap-8 sm:gap-14">
                <div>
                    <p class="hero-stat-value"><?= (int)$overview['active_challenges'] ?></p>
                    <p class="hero-stat-label">ACTIVE QUEST</p>
                </div>
                <div class="hero-stat-divider hidden sm:block"></div>
                <div>
                    <p class="hero-stat-value"><?= (int)$overview['active_employees'] ?></p>
                    <p class="hero-stat-label">MEMBERS</p>
                </div>
            </div>

            <!-- CTA buttons -->
            <div class="mt-12 flex flex-wrap justify-center gap-3">
                <?php if (isset($_SESSION['employee_id'])): ?>
                    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn-hero-primary">
                        ไปที่ Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/pages/challenges.php" class="btn-hero-secondary">
                        ดูภารกิจ
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn-hero-login">
                        เข้าสู่ระบบ
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scroll indicator -->
        <div class="hero-scroll-indicator" id="hero-scroll-btn" aria-hidden="true">
            <span>SCROLL</span>
            <div class="hero-scroll-arrow"></div>
        </div>
    </div>

    <!-- ── ABOUT MORPH OVERLAY ────────────────────────────────────── -->
    <section id="about" class="about-morph">
        <div class="about-section">
            <?php if ($dataError): ?>
                <div class="mb-6 rounded-2xl border border-orange-900/40 bg-orange-950/30 px-5 py-4 text-sm text-j-orange">
                    <?= e($dataError) ?>
                </div>
            <?php endif; ?>

            <p class="quest-label mb-4">ABOUT THE SYSTEM</p>
            <h2 class="about-title">Mission Token คืออะไร?</h2>
            <p class="about-desc">
                ระบบสะสมคะแนนสำหรับพนักงาน JOURNAL — ทำภารกิจที่ได้รับมอบหมาย รับ Token เป็นรางวัล<br class="hidden md:block">
                และแข่งขันกับเพื่อนร่วมทีมบน Leaderboard
            </p>

            <!-- 3 pillars -->
            <div class="about-pillars">
                <div class="about-pillar">
                    <div class="about-pillar-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <h3 class="about-pillar-title">รับภารกิจ</h3>
                    <p class="about-pillar-desc">เลือก Quest ที่เปิดรับ ทำตามเงื่อนไข แล้วส่งหลักฐานเพื่อให้แอดมินอนุมัติ</p>
                </div>
                <div class="about-pillar">
                    <div class="about-pillar-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <h3 class="about-pillar-title">รับ Token</h3>
                    <p class="about-pillar-desc">งานที่ผ่านการอนุมัติจะได้รับ Token ตามมูลค่าของแต่ละภารกิจ สะสมไว้ใน Wallet ของคุณ</p>
                </div>
                <div class="about-pillar">
                    <div class="about-pillar-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" /></svg>
                    </div>
                    <h3 class="about-pillar-title">Leaderboard</h3>
                    <p class="about-pillar-desc">ยอด Token สะสมรวมจะถูกจัดอันดับ เปรียบเทียบกับเพื่อนร่วมทีมแบบ Real-time</p>
                </div>
            </div>

            <!-- ── Scroll hint (absolute at bottom of about-section) ── -->
            <div class="about-scroll-hint" aria-hidden="true">
                <span>GUIDE</span>
                <div class="about-scroll-hint-arrow"></div>
            </div>

        </div><!-- end .about-section -->

    <!-- ── HOW IT WORKS (scrolled inside about-morph) ──────── -->
    <div class="guide-section">
        <div class="guide-inner">
            <p class="quest-label" style="margin-bottom:2rem;letter-spacing:0.2em">วิธีใช้งาน</p>

            <!-- Tab switcher -->
            <div class="guide-tabs" style="margin-bottom:2.5rem;">
                <button class="guide-tab-btn active" id="tab-employee" onclick="switchGuideTab('employee')">พนักงาน</button>
                <button class="guide-tab-btn" id="tab-hr" onclick="switchGuideTab('hr')">HR</button>
            </div>

            <!-- Employee flow -->
            <div id="guide-employee" class="guide-flow">

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">1</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">เข้าสู่ระบบ</p>
                        <p class="guide-flow-desc">ใช้รหัสพนักงานและรหัสผ่านของคุณ — ถ้าเข้าครั้งแรก ให้ใช้รหัสพนักงานเป็นรหัสผ่าน</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">2</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">เลือกภารกิจ</p>
                        <p class="guide-flow-desc">ดูรายการภารกิจที่เปิดรับอยู่ มีทั้งแบบตอบคำถาม และแบบถ่ายรูปส่งหลักฐาน</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">3</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">ทำและส่งงาน</p>
                        <p class="guide-flow-desc">ตอบคำถามให้ถูกทุกข้อ หรือถ่ายรูปหลักฐานแล้วส่ง — ผลจะแจ้งในหน้าประวัติ</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">4</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">ได้รับ Token</p>
                        <p class="guide-flow-desc">งานที่ผ่านการตรวจแล้วจะได้รับ Token เข้ากระเป๋าทันที ดูยอดได้ที่หน้าหลัก</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">5</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">แลกรางวัล</p>
                        <p class="guide-flow-desc">นำ Token ที่สะสมไปแลกของรางวัลที่ต้องการในร้านรางวัลได้เลย</p>
                    </div>
                </div>

            </div>

            <!-- HR flow -->
            <div id="guide-hr" class="guide-flow" style="display:none;">

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">1</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">เข้าสู่ระบบ</p>
                        <p class="guide-flow-desc">ใช้บัญชี HR เข้าสู่ระบบ — ระบบจะพาไปหน้าจัดการโดยอัตโนมัติ ไม่ต้องตั้งค่าอื่นเพิ่ม</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">2</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">สร้างภารกิจ</p>
                        <p class="guide-flow-desc">เพิ่มภารกิจใหม่ กำหนดประเภท จำนวน Token รางวัล และวันที่เปิด-ปิดรับงาน</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">3</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">ตรวจสอบงาน</p>
                        <p class="guide-flow-desc">ดูรูปหลักฐานที่พนักงานส่งมา แล้วกด อนุมัติ หรือ ปฏิเสธ พร้อมใส่เหตุผล</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">4</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">จัดการรางวัล</p>
                        <p class="guide-flow-desc">เพิ่มของรางวัลในร้านค้า กำหนดราคา Token และจำนวนที่มี</p>
                    </div>
                </div>

                <div class="guide-flow-step">
                    <div class="guide-flow-left">
                        <div class="guide-flow-num">5</div>
                        <div class="guide-flow-line"></div>
                    </div>
                    <div class="guide-flow-content">
                        <p class="guide-flow-title">ส่งมอบรางวัล</p>
                        <p class="guide-flow-desc">เมื่อพนักงานแลกรางวัล กดยืนยันหลังจากมอบของจริงให้เรียบร้อยแล้ว</p>
                    </div>
                </div>

            </div>

        </div>
    </div><!-- end .guide-section -->

</section><!-- end .about-morph -->

<!-- Back button (outside morph, fixed relative to viewport) -->
<button class="about-back-btn" id="about-back-btn" aria-label="กลับ">
    <div class="about-back-arrow"></div>
    <span>BACK</span>
</button>

</div><!-- end .home-page-wrap -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
