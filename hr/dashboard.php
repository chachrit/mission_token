<?php
/**
 * hr/dashboard.php
 * HR / Admin — system overview dashboard
 */

require_once __DIR__ . '/../includes/hr_check.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

// ── Overview stats ──────────────────────────────────────────
$overview = getHomeOverviewStats();

// ── Pending redemptions count ────────────────────────────────
try {
    $r = $pdo->query("SELECT COUNT(*) AS c FROM dbo.reward_redemptions WHERE status = 'pending'")->fetch();
    $pendingRedemptions = (int)($r['c'] ?? 0);
} catch (Throwable) { $pendingRedemptions = 0; }

// ── Tokens distributed this week ────────────────────────────
try {
    $r = $pdo->query("
        SELECT ISNULL(SUM(amount), 0) AS week_earned
        FROM dbo.token_transactions
        WHERE amount > 0
          AND created_at >= DATEADD(DAY, -6, CAST(GETDATE() AS DATE))
    ")->fetch();
    $weekEarned = (int)($r['week_earned'] ?? 0);
} catch (Throwable) { $weekEarned = 0; }

// ── Challenges ending within 3 days ─────────────────────────
try {
    $expiringStmt = $pdo->query("
        SELECT TOP (5) challenge_id, title, end_date, type, token_reward
        FROM dbo.challenges
        WHERE is_active = 1
          AND end_date >= CAST(GETDATE() AS DATE)
          AND end_date <= CAST(DATEADD(DAY, 3, GETDATE()) AS DATE)
        ORDER BY end_date ASC
    ");
    $expiring = $expiringStmt->fetchAll();
} catch (Throwable) { $expiring = []; }

// ── Recent activity feed (last 8) ───────────────────────────
$recentFeed = getRecentActivityFeed(8);

// ── Top earners this week ─────────────────────────────────────
try {
    $topEarnersStmt = $pdo->query("
        SELECT TOP (5)
            e.full_name, e.department,
            SUM(tt.amount) AS week_tokens
        FROM dbo.token_transactions tt
        JOIN dbo.employees e ON e.employee_id = tt.employee_id
        WHERE tt.amount > 0
          AND tt.created_at >= DATEADD(DAY, -6, CAST(GETDATE() AS DATE))
        GROUP BY e.employee_id, e.full_name, e.department
        ORDER BY week_tokens DESC
    ");
    $topEarners = $topEarnersStmt->fetchAll();
} catch (Throwable) { $topEarners = []; }

$pageTitle  = 'ภาพรวมระบบ';
$activePage = 'admin_dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="hrd-wrap">

    <!-- ── Page header ── -->
    <header class="hrd-page-header">
        <div class="hrd-page-header-inner">
            <div>
                <h1 class="hrd-page-title">ภาพรวมระบบ</h1>
                <p class="hrd-page-sub"><?= date('l, j F Y') ?> &middot; Mission Token JOURNAL</p>
            </div>
            <a href="<?= BASE_URL ?>/hr/submissions.php" class="hrd-cta-btn">
                ดูคิวอนุมัติ
                <?php if ((int)$overview['pending_reviews'] > 0): ?>
                <span class="hrd-cta-badge"><?= (int)$overview['pending_reviews'] ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- ── Main layout: 2 columns ── -->
    <div class="hrd-body">

        <!-- Left: Action queue -->
        <aside class="hrd-queue">
            <h2 class="hrd-section-label">ต้องดำเนินการ</h2>

            <a href="<?= BASE_URL ?>/hr/submissions.php" class="hrd-queue-item <?= (int)$overview['pending_reviews'] > 0 ? 'hrd-queue-item--alert' : '' ?>">
                <div class="hrd-queue-item-left">
                    <span class="hrd-queue-count <?= (int)$overview['pending_reviews'] > 0 ? 'hrd-queue-count--gold' : '' ?>">
                        <?= (int)$overview['pending_reviews'] ?>
                    </span>
                    <div>
                        <p class="hrd-queue-label">งานรอตรวจสอบ</p>
                        <p class="hrd-queue-sub">Photo / Strava submissions</p>
                    </div>
                </div>
                <svg class="hrd-queue-arrow" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
                </svg>
            </a>

            <a href="<?= BASE_URL ?>/hr/rewards/redemptions.php" class="hrd-queue-item <?= $pendingRedemptions > 0 ? 'hrd-queue-item--alert' : '' ?>">
                <div class="hrd-queue-item-left">
                    <span class="hrd-queue-count <?= $pendingRedemptions > 0 ? 'hrd-queue-count--gold' : '' ?>">
                        <?= $pendingRedemptions ?>
                    </span>
                    <div>
                        <p class="hrd-queue-label">คำขอแลกรางวัล</p>
                        <p class="hrd-queue-sub">รอการ Fulfill</p>
                    </div>
                </div>
                <svg class="hrd-queue-arrow" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
                </svg>
            </a>

            <?php if (!empty($expiring)): ?>
            <div class="hrd-queue-divider">
                <span>ภารกิจใกล้หมดอายุ</span>
            </div>
            <?php foreach ($expiring as $ch): ?>
            <a href="<?= BASE_URL ?>/hr/challenges/edit.php?id=<?= (int)$ch['challenge_id'] ?>" class="hrd-queue-item hrd-queue-item--warn">
                <div class="hrd-queue-item-left">
                    <span class="hrd-queue-expiry-dot"></span>
                    <div>
                        <p class="hrd-queue-label"><?= e($ch['title']) ?></p>
                        <p class="hrd-queue-sub">หมดอายุ <?= date('j M', strtotime($ch['end_date'])) ?> &middot; <?= (int)$ch['token_reward'] ?> Token</p>
                    </div>
                </div>
                <svg class="hrd-queue-arrow" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
                </svg>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ((int)$overview['pending_reviews'] === 0 && $pendingRedemptions === 0 && empty($expiring)): ?>
            <div class="hrd-queue-empty">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
                </svg>
                <p>ไม่มีรายการรอดำเนินการ</p>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Right: System overview -->
        <main class="hrd-overview">

            <!-- Token economy strip -->
            <section class="hrd-economy">
                <h2 class="hrd-section-label">Token Economy</h2>
                <div class="hrd-economy-row">
                    <div class="hrd-economy-stat">
                        <span class="hrd-economy-num"><?= formatTokens((int)$overview['total_balance']) ?></span>
                        <span class="hrd-economy-desc">Token คงอยู่ในระบบ</span>
                    </div>
                    <div class="hrd-economy-divider"></div>
                    <div class="hrd-economy-stat">
                        <span class="hrd-economy-num hrd-economy-num--gold"><?= formatTokens($weekEarned) ?></span>
                        <span class="hrd-economy-desc">Token ที่แจกใน 7 วันที่ผ่านมา</span>
                    </div>
                    <div class="hrd-economy-divider"></div>
                    <div class="hrd-economy-stat">
                        <span class="hrd-economy-num"><?= formatTokens((int)$overview['total_earned']) ?></span>
                        <span class="hrd-economy-desc">Token รวมทั้งหมดตลอดโปรแกรม</span>
                    </div>
                </div>
            </section>

            <!-- System health row -->
            <section class="hrd-health">
                <div class="hrd-health-item">
                    <span class="hrd-health-num"><?= (int)$overview['active_employees'] ?></span>
                    <span class="hrd-health-label">พนักงานที่ใช้งาน</span>
                </div>
                <div class="hrd-health-item">
                    <span class="hrd-health-num"><?= (int)$overview['active_challenges'] ?></span>
                    <span class="hrd-health-label">ภารกิจเปิดอยู่</span>
                </div>
                <div class="hrd-health-item">
                    <span class="hrd-health-num"><?= (int)$overview['submissions_today'] ?></span>
                    <span class="hrd-health-label">Submissions วันนี้</span>
                </div>
            </section>

            <!-- Top earners this week -->
            <?php if (!empty($topEarners)): ?>
            <section class="hrd-earners">
                <h2 class="hrd-section-label">ผู้ได้ Token สูงสุด 7 วันที่ผ่านมา</h2>
                <ol class="hrd-earners-list">
                    <?php foreach ($topEarners as $i => $emp): ?>
                    <li class="hrd-earner-row">
                        <span class="hrd-earner-rank"><?= $i + 1 ?></span>
                        <div class="hrd-earner-info">
                            <span class="hrd-earner-name"><?= e($emp['full_name']) ?></span>
                            <span class="hrd-earner-dept"><?= e($emp['department'] ?? '') ?></span>
                        </div>
                        <span class="hrd-earner-tokens">+<?= formatTokens((int)$emp['week_tokens']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php endif; ?>

        </main>
    </div>

    <!-- ── Recent activity ── -->
    <?php if (!empty($recentFeed)): ?>
    <section class="hrd-feed">
        <h2 class="hrd-section-label hrd-feed-title">กิจกรรมล่าสุด</h2>
        <div class="hrd-feed-table">
            <?php foreach ($recentFeed as $item): ?>
            <?php
                $badge = statusBadge($item['status']);
                $hex   = ltrim($badge['color'], '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $pillStyle = 'color:' . $badge['color'] . ';background:rgba(' . $r . ',' . $g . ',' . $b . ',0.12)';
            ?>
            <div class="hrd-feed-row">
                <div class="hrd-feed-cell hrd-feed-cell--name">
                    <span class="hrd-feed-name"><?= e($item['full_name']) ?></span>
                    <span class="hrd-feed-dept"><?= e($item['department'] ?? '') ?></span>
                </div>
                <div class="hrd-feed-cell hrd-feed-cell--challenge">
                    <?= e($item['challenge_title']) ?>
                </div>
                <div class="hrd-feed-cell hrd-feed-cell--status">
                    <span class="hrd-status-pill" style="<?= e($pillStyle) ?>">
                        <?= e($badge['label']) ?>
                    </span>
                </div>
                <div class="hrd-feed-cell hrd-feed-cell--time">
                    <?= e(date('j M H:i', strtotime($item['submitted_at']))) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
