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
$wallet          = ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0];
$activeChallenges = [];
$recentActivity  = [];
$streak          = 0;
$dataError       = null;

try {
    $wallet           = getWalletInfo($employeeId);
    $activeChallenges = getActiveChallenges();
    $streak           = getActivityStreak($employeeId);
    $recentActivity   = getRecentSubmissions($employeeId, 5);

    // Annotate each challenge with this user's submission status
    foreach ($activeChallenges as &$ch) {
        $pdo  = getDB();
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
    'pending'       => ['text' => 'รอ Approve', 'color' => 'bg-yellow-100 text-yellow-700'],
    'approved'      => ['text' => 'อนุมัติแล้ว', 'color' => 'bg-green-100 text-green-700'],
    'auto_approved' => ['text' => 'ผ่านแล้ว',    'color' => 'bg-green-100 text-green-700'],
    'rejected'      => ['text' => 'ไม่ผ่าน',      'color' => 'bg-red-100 text-red-700'],
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

    <!-- ── WALLET CARD ──────────────────────────────────────── -->
    <section class="mb-8 rounded-[28px] p-6 lg:p-8 overflow-hidden relative"
             style="background: linear-gradient(135deg, #fdfcdf 0%, #faf0cf 60%, #eeebe1 100%); border: 1px solid #cecdcd; box-shadow: 0 2px 16px rgba(9,17,19,0.07);">

        <!-- Gold glow decoration -->
        <div class="pointer-events-none absolute -right-10 -top-10 h-52 w-52 rounded-full"
             style="background: radial-gradient(circle, rgba(218,185,55,0.18) 0%, transparent 70%);"></div>
        <div class="pointer-events-none absolute right-24 bottom-0 h-32 w-32 rounded-full"
             style="background: radial-gradient(circle, rgba(248,231,105,0.12) 0%, transparent 70%);"></div>

        <div class="relative z-10 flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">

            <!-- Greeting + name -->
            <div>
                <p class="text-sm font-medium" style="color:#6b6e77;"><?= e($greeting) ?></p>
                <h1 class="mt-1 text-2xl font-semibold tracking-wide" style="color:#091113;">
                    <?= e($employeeName) ?>
                </h1>
                <?php if ($department): ?>
                <p class="mt-0.5 text-xs" style="color:#6b6e77;"><?= e($department) ?></p>
                <?php endif; ?>

                <?php if ($streak > 0): ?>
                <div class="mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1"
                     style="background:rgba(218,185,55,0.15); border:1px solid rgba(218,185,55,0.35);">
                    <span>🔥</span>
                    <span class="text-xs font-medium" style="color:#91700a;">
                        Streak <?= $streak ?> วัน
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Token balance -->
            <div class="text-right">
                <p class="text-xs font-medium tracking-widest uppercase mb-1" style="color:#6b6e77;">Token คงเหลือ</p>
                <div class="flex items-center justify-end gap-3">
                    <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token" class="h-10 w-10">
                    <p class="text-5xl font-bold" style="color:#c9a830; letter-spacing:-0.02em;">
                        <?= formatTokens((int)$wallet['balance']) ?>
                    </p>
                </div>
                <div class="mt-2 flex justify-end gap-6">
                    <div class="text-right">
                        <p class="text-xs" style="color:#6b6e77;">ได้รับทั้งหมด</p>
                        <p class="text-sm font-semibold" style="color:#091113;">+<?= formatTokens((int)$wallet['total_earned']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs" style="color:#6b6e77;">ใช้ไป</p>
                        <p class="text-sm font-semibold" style="color:#3a3e43;"><?= formatTokens((int)$wallet['total_spent']) ?></p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ── ACTIVE QUESTS ───────────────────────────────────── -->
    <section class="mb-8">
        <div class="mb-4 flex items-end justify-between gap-3">
            <h2 class="section-title">ภารกิจที่เปิดอยู่</h2>
            <a href="<?= BASE_URL ?>/pages/challenges.php" class="text-sm font-medium text-j-gold hover:underline">
                ดูทั้งหมด →
            </a>
        </div>

        <?php if ($activeChallenges): ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($activeChallenges as $ch): ?>
            <?php
                $myStatus = $ch['my_status'];
                $isDone   = in_array($myStatus, ['approved', 'auto_approved'], true);
                $isPending = $myStatus === 'pending';
                $isRejected = $myStatus === 'rejected';
                $sl = $statusLabel[$myStatus] ?? null;
            ?>
            <article class="journal-card p-5 flex flex-col gap-4 <?= $isDone ? 'opacity-60' : '' ?>">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1.5 flex flex-wrap items-center gap-2">
                            <span class="badge text-xs" style="background:#eeebe1; color:#3a3e43;">
                                <?= e(challengeTypeLabel((string)$ch['type'])) ?>
                            </span>
                            <?php if ($sl): ?>
                            <span class="badge text-xs <?= $sl['color'] ?>"><?= $sl['text'] ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-base font-semibold leading-snug text-j-dark"><?= e($ch['title']) ?></h3>
                        <p class="mt-1 text-sm leading-6 text-j-slate line-clamp-2"><?= e((string)$ch['description']) ?></p>
                    </div>
                    <!-- Reward -->
                    <div class="flex flex-col items-center flex-shrink-0">
                        <img src="<?= BASE_URL ?>/assets/images/token.png" alt="token" class="h-8 w-8">
                        <p class="text-sm font-bold text-j-gold">+<?= formatTokens((int)$ch['token_reward']) ?></p>
                    </div>
                </div>

                <div class="mt-auto">
                    <?php if ($isDone): ?>
                    <div class="flex items-center gap-2 text-sm text-j-green font-medium">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        ทำภารกิจสำเร็จแล้ว
                    </div>
                    <?php elseif ($isPending): ?>
                    <div class="text-sm text-yellow-600 font-medium">⏳ รอการตรวจสอบจาก HR</div>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/pages/challenges.php?id=<?= (int)$ch['challenge_id'] ?>"
                       class="btn-gold w-full justify-center text-sm py-2">
                        <?= $isRejected ? 'ดูผลและส่งใหม่' : 'ไปทำภารกิจ' ?>
                    </a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-12 text-center text-sm text-j-slate">
            ไม่มีภารกิจเปิดรับในช่วงเวลานี้
        </div>
        <?php endif; ?>
    </section>

    <!-- ── RECENT ACTIVITY ─────────────────────────────────── -->
    <section>
        <div class="mb-4 flex items-end justify-between gap-3">
            <h2 class="section-title">กิจกรรมล่าสุด</h2>
            <a href="<?= BASE_URL ?>/pages/history.php" class="text-sm font-medium text-j-gold hover:underline">
                ดูประวัติทั้งหมด →
            </a>
        </div>

        <?php if ($recentActivity): ?>
        <div class="journal-card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#f5f3ea; border-bottom:1px solid #cecdcd;">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-j-slate uppercase tracking-wider">ภารกิจ</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-j-slate uppercase tracking-wider hidden sm:table-cell">ประเภท</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-j-slate uppercase tracking-wider">สถานะ</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-j-slate uppercase tracking-wider">Token</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-j-slate uppercase tracking-wider hidden md:table-cell">วันที่</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $i => $row): ?>
                    <?php
                        $sl2 = $statusLabel[$row['status']] ?? ['text' => e($row['status']), 'color' => 'bg-gray-100 text-gray-600'];
                        $submittedAt = $row['submitted_at'];
                        if ($submittedAt instanceof DateTimeInterface) {
                            $dateStr = $submittedAt->format('d/m/Y');
                        } elseif (is_string($submittedAt)) {
                            $ts = strtotime($submittedAt);
                            $dateStr = $ts ? date('d/m/Y', $ts) : '-';
                        } else {
                            $dateStr = '-';
                        }
                        $awarded = (int)$row['token_awarded'];
                    ?>
                    <tr class="<?= $i % 2 === 0 ? 'bg-white' : '' ?> border-b border-[#eeebe1] last:border-0">
                        <td class="px-5 py-3.5 font-medium text-j-dark"><?= e($row['challenge_title']) ?></td>
                        <td class="px-5 py-3.5 text-j-slate hidden sm:table-cell capitalize">
                            <?= e($row['submission_type'] === 'quiz' ? 'Quiz' : 'ภาพถ่าย') ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="badge text-xs <?= $sl2['color'] ?>"><?= $sl2['text'] ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-semibold <?= $awarded > 0 ? 'text-j-gold' : 'text-j-slate' ?>">
                            <?= $awarded > 0 ? '+' . formatTokens($awarded) : '—' ?>
                        </td>
                        <td class="px-5 py-3.5 text-right text-j-slate hidden md:table-cell"><?= $dateStr ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-12 text-center text-sm text-j-slate">
            ยังไม่มีประวัติการส่งงาน
        </div>
        <?php endif; ?>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
