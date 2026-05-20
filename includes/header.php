<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$_sessionRole  = $_SESSION['role'] ?? '';
$isAdmin       = $_sessionRole === 'admin';
$isHr          = $_sessionRole === 'hr';
$isIt          = $_sessionRole === 'it';
$isAdminOrHr   = $isAdmin || $isHr || $isIt;

// Refresh token balance from DB every page load so it stays up to date
if (!empty($_SESSION['employee_id'])) {
    $navBalance = (int)getWalletBalance((int)$_SESSION['employee_id']);
    $_SESSION['token_balance'] = $navBalance;
} else {
    $navBalance = 0;
}
$pendingCount = $isAdminOrHr ? getPendingCount() : 0;
$flash        = $flash ?? getFlash();

// Notification bell (employee zone only)
$allNotifs = [];
if (!empty($_SESSION['employee_id'])) {
    $empId = (int)$_SESSION['employee_id'];
    try {
        $pdo = getDB();

        // 1. Rejected photo/strava (latest per challenge, still active)
        $s = $pdo->prepare("
            WITH latest AS (
                SELECT challenge_id, submission_id, status, review_note,
                       ROW_NUMBER() OVER (PARTITION BY challenge_id ORDER BY submitted_at DESC) AS rn
                FROM challenge_submissions
                WHERE employee_id = ? AND submission_type IN ('photo','strava')
            )
            SELECT l.challenge_id, l.submission_id, c.title, l.review_note
            FROM latest l
            JOIN challenges c ON l.challenge_id = c.challenge_id
            WHERE l.rn = 1 AND l.status = 'rejected'
              AND c.is_active = 1
              AND c.end_date >= CAST(GETDATE() AS DATE)
            ORDER BY c.end_date ASC
        ");
        $s->execute([$empId]);
        foreach ($s->fetchAll() as $r) {
            $allNotifs[] = [
                'key'   => 'sub_rej_' . $r['submission_id'],
                'type'  => 'rejected',
                'title' => $r['title'],
                'sub'   => !empty($r['review_note']) ? mb_strimwidth((string)$r['review_note'], 0, 60, '\u2026', 'UTF-8') : 'ไม่ผ่าน \u2014 ลองใหม่อีกครั้งนะ',
                'href'  => BASE_URL . '/pages/challenges.php',
                'cid'   => (int)$r['challenge_id'],
                'sid'   => (int)$r['submission_id'],
            ];
        }

        // 2. Approved photo/strava (recent 7 days)
        $s2 = $pdo->prepare("
            WITH latest AS (
                SELECT challenge_id, submission_id, status, token_awarded, reviewed_at,
                       ROW_NUMBER() OVER (PARTITION BY challenge_id ORDER BY submitted_at DESC) AS rn
                FROM challenge_submissions
                WHERE employee_id = ? AND submission_type IN ('photo','strava')
            )
            SELECT l.challenge_id, l.submission_id, l.token_awarded, c.title
            FROM latest l
            JOIN challenges c ON l.challenge_id = c.challenge_id
            WHERE l.rn = 1 AND l.status = 'approved'
              AND l.reviewed_at >= DATEADD(DAY, -7, GETDATE())
            ORDER BY l.reviewed_at DESC
        ");
        $s2->execute([$empId]);
        foreach ($s2->fetchAll() as $r) {
            $allNotifs[] = [
                'key'   => 'sub_app_' . $r['submission_id'],
                'type'  => 'approved',
                'title' => $r['title'],
                'sub'   => 'ยินดีด้วย! ได้ +' . (int)$r['token_awarded'] . ' Token',
                'href'  => BASE_URL . '/pages/history.php',
                'cid'   => (int)$r['challenge_id'],
                'sid'   => (int)$r['submission_id'],
            ];
        }

        // 3. Reward fulfilled / cancelled (recent 7 days)
        $s3 = $pdo->prepare("
            SELECT rr.redemption_id, rr.status, rr.tokens_spent, rr.admin_note, r.title AS reward_name
            FROM reward_redemptions rr
            JOIN rewards r ON rr.reward_id = r.reward_id
            WHERE rr.employee_id = ? AND rr.status IN ('fulfilled','cancelled')
              AND rr.processed_at >= DATEADD(DAY, -7, GETDATE())
            ORDER BY rr.processed_at DESC
        ");
        $s3->execute([$empId]);
        foreach ($s3->fetchAll() as $r) {
            $isFul = $r['status'] === 'fulfilled';
            $allNotifs[] = [
                'key'   => 'red_' . ($isFul ? 'ful' : 'can') . '_' . $r['redemption_id'],
                'type'  => $isFul ? 'fulfilled' : 'cancelled',
                'title' => $r['reward_name'],
                'sub'   => $isFul
                            ? 'ดำเนินการสำเร็จ! ใช้ ' . (int)$r['tokens_spent'] . ' Token'
                            : (!empty($r['admin_note']) ? mb_strimwidth((string)$r['admin_note'], 0, 60, '\u2026', 'UTF-8') : 'ยกเลิกการขอแลกรางวัล'),
                'href'  => BASE_URL . '/pages/history.php',
            ];
        }

        // 4. Admin/HR token adjustments (recent 7 days)
        $s4 = $pdo->prepare("
            SELECT tt.tx_id, tt.amount, tt.note, adj.full_name AS adj_name
            FROM token_transactions tt
            LEFT JOIN employees adj ON adj.employee_id = tt.created_by
            WHERE tt.employee_id = ? AND tt.tx_type = 'admin_adjust'
              AND tt.created_at >= DATEADD(DAY, -7, GETDATE())
            ORDER BY tt.created_at DESC
        ");
        $s4->execute([$empId]);
        foreach ($s4->fetchAll() as $r) {
            $isPos   = (int)$r['amount'] > 0;
            $adjName = !empty($r['adj_name']) ? $r['adj_name'] : 'Admin';
            $allNotifs[] = [
                'key'   => 'adj_' . $r['tx_id'],
                'type'  => $isPos ? 'approved' : 'cancelled',
                'title' => $isPos ? 'เพิ่ม Token จาก ' . $adjName : 'หัก Token จาก ' . $adjName,
                'sub'   => !empty($r['note'])
                            ? mb_strimwidth((string)$r['note'], 0, 60, '\u2026', 'UTF-8')
                            : ($isPos ? '+' . number_format((int)$r['amount']) . ' Token' : number_format((int)$r['amount']) . ' Token'),
                'href'  => BASE_URL . '/pages/history.php',
            ];
        }

        // 5. New challenges (created within last 3 days, active, not yet submitted)
        $s5 = $pdo->prepare("
            SELECT c.challenge_id, c.title, c.token_reward, c.type
            FROM challenges c
            WHERE c.is_active = 1
              AND c.start_date <= CAST(GETDATE() AS DATE)
              AND c.end_date   >= CAST(GETDATE() AS DATE)
              AND c.created_at >= DATEADD(DAY, -3, GETDATE())
              AND NOT EXISTS (
                  SELECT 1 FROM challenge_submissions cs
                  WHERE cs.employee_id   = ?
                    AND cs.challenge_id  = c.challenge_id
                    AND cs.status NOT IN ('rejected')
              )
            ORDER BY c.created_at DESC
        ");
        $s5->execute([$empId]);
        foreach ($s5->fetchAll() as $r) {
            $typeLabel = $r['type'] === 'quiz' ? 'Quiz' : ($r['type'] === 'strava' ? 'Strava' : 'ภารกิจ');
            $allNotifs[] = [
                'key'   => 'new_ch_' . $r['challenge_id'],
                'type'  => 'new_challenge',
                'title' => $r['title'],
                'sub'   => 'มีภารกิจใหม่! ' . $typeLabel . ' + +' . number_format((int)$r['token_reward']) . ' Token',
                'href'  => BASE_URL . '/pages/challenges.php',
                'cid'   => (int)$r['challenge_id'],
            ];
        }
    } catch (Throwable $e) { /* silent */ }
}
$notifCount = count($allNotifs);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?> JOURNAL</title>
    <meta name="csrf-token" content="<?php echo e(csrfToken()); ?>">
    <meta name="base-url" content="<?php echo BASE_URL; ?>">

    <!-- Tailwind CSS Play CDN (v3) � config MUST come before CDN script -->
    <script>
        window.tailwind = { config: {
            theme: {
                extend: {
                    colors: {
                        'j-white':      '#eeebe1',
                        'j-ivory':      '#fdfcdf',
                        'j-cream':      '#faf0cf',
                        'j-silver':     '#cecdcd',
                        'j-black':      '#000000',
                        'j-slate':      '#6b6e77',
                        'j-charcoal':   '#3a3e43',
                        'j-dark':       '#091113',
                        'j-gold':       '#dab937',
                        'j-gold-light': '#f8e769',
                        'j-green':      '#518e5c',
                        'j-teal':       '#4f8b98',
                        'j-blue':       '#2f4e9d',
                        'j-navy':       '#1f334f',
                        'j-orange':     '#d2592a',
                        'j-purple':     '#62307a',
                        'j-gray-green': '#82b295',
                    },
                    fontFamily: {
                        'prompt': ['Prompt', 'sans-serif'],
                    },
                    boxShadow: {
                        'journal': '0 2px 16px rgba(9,17,19,0.08)',
                        'journal-md': '0 4px 24px rgba(9,17,19,0.12)',
                    }
                }
            }
        }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Prompt Font (Thai + Latin) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>

<body class="min-h-screen">

    <!-- ===================================================
         NAVIGATION BAR
         =================================================== -->
    <nav class="sticky top-0 z-50 nh-u001" id="main-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <?php
                // ตรวจสอบว่า อยู่ใน /hr/ zone ใช้ admin nav
                $isAdminPage = (strpos($_SERVER['PHP_SELF'] ?? '', '/hr/') !== false);
                ?>
                <!-- Logo -->
                <div class="nav-logo-link flex items-center gap-3 flex-shrink-0">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logo.png"
                         alt="JOURNAL"
                         class="nav-logo h-20 w-auto">
                    <?php if ($isAdmin): ?>
                    <span class="badge text-xs nh-u002">Admin</span>
                    <?php elseif ($isHr): ?>
                    <span class="badge text-xs nh-u003">HR</span>
                    <?php elseif ($isIt): ?>
                    <span class="badge text-xs nh-u004">IT</span>
                    <?php endif; ?>
                </div>

                <!-- Desktop Nav Links -->
                <div class="hidden md:flex flex-1 min-w-0 items-center justify-center overflow-x-auto">
                    <?php
                    // Pending counts (admin/hr/it zone only)
                    $pendingRedemptionCount = 0;
                    if ($isAdminOrHr && $isAdminPage) {
                        try {
                            $r = getDB()->query("SELECT COUNT(*) AS c FROM dbo.reward_redemptions WHERE status='pending'")->fetch();
                            $pendingRedemptionCount = (int)($r['c'] ?? 0);
                        } catch (Throwable $e) { /* table may not exist yet */ }
                    }

                    // Nav context: follow current page zone, not role
                    if ($isAdminOrHr && $isAdminPage) {
                        // ไปยัง /hr/ ใช้ admin nav
                        $navLinks = [
                            'admin_dashboard'    => ['label' => 'ภาพรวม',          'href' => BASE_URL . '/hr/dashboard.php'],
                            'admin_challenges'   => ['label' => 'จัดการภารกิจ',    'href' => BASE_URL . '/hr/challenges/index.php'],
                            'admin_submissions'  => ['label' => 'อนุมัติงาน',     'href' => BASE_URL . '/hr/submissions.php', 'badge' => $pendingCount],
                            'admin_rewards'      => ['label' => 'จัดการรางวัล',   'href' => BASE_URL . '/hr/rewards/index.php'],
                            'admin_redemptions'  => ['label' => 'คำขอแลก', 'href' => BASE_URL . '/hr/rewards/redemptions.php', 'badge' => $pendingRedemptionCount],
                            'admin_employees'    => ['label' => 'จัดการพนักงาน',  'href' => BASE_URL . '/hr/employees.php'],
                        ];
                    } else {
                        // ไปยัง /pages/ คือ employee zone ใช้ employee nav
                        $navLinks = [
                            'dashboard'  => ['label' => 'หน้าแรก',   'href' => BASE_URL . '/pages/dashboard.php'],
                            'challenges' => ['label' => 'ภารกิจ',    'href' => BASE_URL . '/pages/challenges.php'],
                            'rewards'    => ['label' => 'ร้านรางวัล', 'href' => BASE_URL . '/pages/rewards.php'],
                            'history'    => ['label' => 'ประวัติ',    'href' => BASE_URL . '/pages/history.php'],
                        ];
                    }

                    $currentActive = $activePage ?? '';
                    foreach ($navLinks as $key => $link):
                        $isActive = ($currentActive === $key);
                    ?>
                    <a href="<?php echo $link['href']; ?>"
                              class="nav-link-hover relative flex items-center gap-1.5 px-3 lg:px-4 py-5 text-sm font-medium whitespace-nowrap transition-colors <?php echo $isActive ? 'nav-active' : 'nav-link-default'; ?>"
                              <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                        <?php echo $link['label']; ?>
                        <?php if (!empty($link['badge']) && $link['badge'] > 0): ?>
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full font-bold nh-u005"
                             ><?php echo $link['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>

                </div>

                <!-- Right: Token Balance + User Menu -->
                <div id="nav-right" class="flex items-center gap-3">

                    <!-- Token Balance – แสดงเฉพาะ employee zone -->
                    <?php if (!$isAdminPage || !$isAdminOrHr): ?>
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full nh-u006"
                        >
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt="token" width="18" height="18" class="token-spin nh-token-img">
                        <span class="text-sm font-semibold nh-u007" id="nav-balance"><?php echo formatTokens($navBalance); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Notification Bell (employee zone) -->
                    <?php if (!$isAdminPage || !$isAdminOrHr): ?>
                    <div class="relative" id="notif-wrap">
                        <button id="notif-bell-btn"
                                class="nav-notif-btn"
                                aria-label="การแจ้งเตือน"
                                aria-expanded="false"
                                aria-controls="notif-dropdown">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <?php if ($notifCount > 0): ?>
                            <span class="nav-notif-badge" id="nav-notif-badge"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </button>

                        <!-- Notification Dropdown -->
                        <div id="notif-dropdown"
                             class="nav-notif-dropdown hidden"
                             role="menu" aria-label="การแจ้งเตือน">
                            <!-- Header -->
                            <div class="nav-notif-header">
                                <span class="nav-notif-header-title">การแจ้งเตือน</span>
                                <?php if ($notifCount > 0): ?>
                                <span class="nav-notif-header-count" id="notif-header-count"><?= $notifCount ?> รายการใหม่</span>
                                <?php endif; ?>
                            </div>
                            <!-- Items -->
                            <?php
                            $_iconCfg = [
                                'rejected'      => ['cls'=>'nav-notif-icon-red',   'icon'=>'<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>'],
                                'approved'      => ['cls'=>'nav-notif-icon-green', 'icon'=>'<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'],
                                'fulfilled'     => ['cls'=>'nav-notif-icon-green', 'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 14.4 8.7 21.2 8.7 15.9 13.2 18.1 20 12 16.4 5.9 20 8.1 13.2 2.8 8.7 9.6 8.7z"/></svg>'],
                                'cancelled'     => ['cls'=>'nav-notif-icon-red',   'icon'=>'<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>'],
                                'new_challenge' => ['cls'=>'nav-notif-icon-green', 'icon'=>'<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>'],
                            ];
                            $_badgeCfg = [];
                            ?>
                            <?php if (empty($allNotifs)): ?>
                            <div id="notif-empty-state" class="nav-notif-empty">
                                <svg class="nh-u008" width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <p>ไม่มีการแจ้งเตือนใหม่</p>
                            </div>
                            <?php else: ?>
                            <div class="nav-notif-list">
                                <?php foreach ($allNotifs as $_n):
                                    $_ic = $_iconCfg[$_n['type']] ?? $_iconCfg['rejected'];
                                    $_dcid = isset($_n['cid']) ? ' data-cid="' . (int)$_n['cid'] . '"' : '';
                                    $_dsid = isset($_n['sid']) ? ' data-sid="' . (int)$_n['sid'] . '"' : '';
                                ?>
                                <a href="<?= e($_n['href']) ?>"
                                   class="nav-notif-item" role="menuitem"
                                   data-key="<?= e($_n['key']) ?>"<?= $_dcid ?><?= $_dsid ?>>
                                    <span class="nav-notif-item-icon <?= $_ic['cls'] ?>">
                                        <?= $_ic['icon'] ?>
                                    </span>
                                    <div class="nav-notif-item-body">
                                        <p class="nav-notif-item-title"><?= e($_n['title']) ?></p>
                                        <p class="nav-notif-item-sub"><?= e($_n['sub']) ?></p>
                                    </div>
                                    <span class="nav-notif-item-new">ใหม่</span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <div id="notif-empty-state" class="nav-notif-empty nh-u009">
                                <svg class="nh-u008" width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <p>ไม่มีการแจ้งเตือนใหม่</p>
                            </div>
                            <a href="<?= BASE_URL ?>/pages/history.php"
                               class="nav-notif-footer-link" id="notif-footer-link">
                                ดูประวัติทั้งหมด
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- User Menu -->
                    <div class="relative">
                        <button id="user-menu-btn"
                            class="nav-user-menu-btn flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors nh-u010"
                                aria-label="เมนูผู้ใช้: <?php echo e($_SESSION['full_name'] ?? ''); ?>"
                                aria-expanded="false"
                                aria-controls="user-dropdown"
                           >
                            <!-- Avatar -->
                            <?php $_navAvatar = $_SESSION['avatar_url'] ?? ''; ?>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0 overflow-hidden nh-u011"
                                >
                                <?php if ($_navAvatar): ?>
                                  <img src="<?php echo uploadImgUrl('avatars', $_navAvatar); ?>"
                                      alt="" class="nh-avatar-img">
                                <?php else: ?>
                                <?php echo mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <span class="hidden sm:block text-sm font-medium max-w-[120px] truncate nh-u012">
                                <?php echo e($_SESSION['full_name'] ?? ''); ?>
                            </span>
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown -->
                        <div id="user-dropdown"
                             class="hidden absolute right-0 mt-2 w-52 rounded-xl shadow-2xl overflow-hidden z-50 nh-u013"
                            >
                            <!-- User info -->
                            <div class="px-4 py-3 border-b nh-u014">
                                <p class="text-sm font-medium nh-u012"><?php echo e($_SESSION['full_name'] ?? ''); ?></p>
                                <p class="text-xs mt-0.5 nh-u015"><?php echo e($_SESSION['employee_code'] ?? ''); ?></p>
                                <?php if (!$isAdmin): ?>
                                <p class="text-xs mt-1 font-semibold nh-u007">
                                    <?php echo formatTokens($navBalance); ?> token
                                </p>
                                <?php endif; ?>
                            </div>
                            <!-- Links -->
                            <?php if ($isAdminOrHr && !$isAdmin): ?>
                            <!-- Zone switcher – HR/IT กลับหน้าแรก -->
                            <?php if ($isAdminPage): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/dashboard.php"
                                         class="nav-dd-link nav-dd-item flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                กลับหน้าแรก
                            </a>
                            <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/hr/submissions.php"
                                         class="nav-dd-link nav-dd-item flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                ตั้งค่า
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$isAdmin): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/profile.php"
                                         class="nav-dd-link nav-dd-item flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                โปรไฟล์
                            </a>
                            <a href="<?php echo BASE_URL; ?>/pages/strava_dashboard.php"
                                         class="nav-dd-link nav-dd-link--strava nav-dd-item flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                                </svg>
                                Strava Dashboard
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>/logout.php"
                                         class="nav-dd-link nav-dd-link--danger nav-dd-item flex items-center gap-2 px-4 py-3 text-sm transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                ออกจากระบบ
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Hamburger -->
                                <button id="mobile-menu-btn" class="nav-mobile-menu-btn md:hidden p-2 rounded-lg nh-u010"
                                    aria-label="เปิดเมนูหลัก"
                                    aria-expanded="false"
                                    aria-controls="mobile-menu"
                           >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t nh-u016">
            <div class="px-4 py-3 space-y-1">
                <?php foreach ($navLinks as $key => $link): ?>
                <a href="<?php echo $link['href']; ?>"
                         class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?php echo ($currentActive === $key) ? 'nav-link-active bg-[#1a1f20]' : 'nav-link-default'; ?>"
                         <?php echo ($currentActive === $key) ? 'aria-current="page"' : ''; ?>>
                    <?php echo $link['label']; ?>
                    <?php if (!empty($link['badge']) && $link['badge'] > 0): ?>
                    <span class="px-1.5 py-0.5 text-xs rounded-full font-bold nh-u005"><?php echo $link['badge']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <!-- Token balance on mobile -->
                <?php if (!$isAdmin): ?>
                <div class="flex items-center gap-2 px-3 py-2 mt-2 rounded-lg nh-u017">
                    <span class="text-xs nh-u015">Token คงเหลือ:</span>
                    <span class="text-sm font-semibold nh-u007"><?php echo formatTokens($navBalance); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>



    <!-- ===================================================
         MAIN CONTENT WRAPPER
         =================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
