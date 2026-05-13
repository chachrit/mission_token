<?php
/**
 * includes/header.php
 * Shared HTML head + sticky navigation bar.
 *
 * Variables consumed (set before include):
 *   $pageTitle  string  — shown in <title> tag
 *   $activePage string  — nav key for active highlight
 *                         ('dashboard' | 'challenges' | 'history' | 'leaderboard'
 *                          | 'admin_dashboard' | 'admin_challenges' | 'admin_submissions' | 'admin_employees')
 *
 * Requires: config/app.php, includes/functions.php already required by auth_check.
 */

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
                'sub'   => !empty($r['review_note']) ? mb_strimwidth((string)$r['review_note'], 0, 60, '\u2026', 'UTF-8') : 'ภารกิจถูกปฏิเสธ \u2014 กดเพื่อส่งหลักฐานใหม่',
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
                'sub'   => 'ผ่านการอนุมัติ! ได้รับ +' . (int)$r['token_awarded'] . ' Token',
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
                            ? 'รางวัลพร้อมรับแล้ว! ใช้ ' . (int)$r['tokens_spent'] . ' Token'
                            : (!empty($r['admin_note']) ? mb_strimwidth((string)$r['admin_note'], 0, 60, '\u2026', 'UTF-8') : 'คำขอแลกรางวัลถูกยกเลิก'),
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
                'title' => $isPos ? 'ได้รับ Token จาก ' . $adjName : 'ถูกหัก Token โดย ' . $adjName,
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
            $typeLabel = $r['type'] === 'quiz' ? 'Quiz' : ($r['type'] === 'strava' ? 'Strava' : 'ภาพถ่าย');
            $allNotifs[] = [
                'key'   => 'new_ch_' . $r['challenge_id'],
                'type'  => 'new_challenge',
                'title' => $r['title'],
                'sub'   => 'ภารกิจใหม่! ' . $typeLabel . ' — รับ +' . number_format((int)$r['token_reward']) . ' Token',
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
    <title><?php echo e($pageTitle ?? APP_NAME); ?> — JOURNAL</title>
    <meta name="csrf-token" content="<?php echo e(csrfToken()); ?>">

    <!-- Tailwind CSS Play CDN (v3) — config MUST come before CDN script -->
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

    <style>
        /* Base */
        *, *::before, *::after { box-sizing: border-box; }
        body  { font-family: 'Prompt', sans-serif; background-color: #eeebe1; color: #091113; display: block; }

        /* Custom scrollbar */
        ::-webkit-scrollbar       { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #eeebe1; }
        ::-webkit-scrollbar-thumb { background: #cecdcd; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b6e77; }

        /* Nav active link */
        .nav-active {
            color: #dab937 !important;
            border-bottom: 2px solid #dab937;
        }

        /* Global keyboard focus ring (WCAG 2.1 AA on dark backgrounds) */
        :focus-visible {
            outline: 2px solid #dab937;
            outline-offset: 3px;
            border-radius: 4px;
        }
        a:focus:not(:focus-visible),
        button:focus:not(:focus-visible) {
            outline: none;
        }

        /* Card base */
        .journal-card {
            background: #fdfcdf;
            border: 1px solid #cecdcd;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .journal-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(9,17,19,0.10); }

        /* Buttons */
        .btn-dark {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: #091113; color: #eeebe1;
            padding: 0.6rem 1.4rem; border-radius: 8px;
            font-size: 0.9rem; font-weight: 500; font-family: 'Prompt', sans-serif;
            border: none; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            text-decoration: none;
        }
        .btn-dark:hover  { background: #3a3e43; }
        .btn-dark:active { transform: scale(0.97); }

        .btn-gold {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: #dab937; color: #091113;
            padding: 0.6rem 1.4rem; border-radius: 8px;
            font-size: 0.9rem; font-weight: 600; font-family: 'Prompt', sans-serif;
            border: none; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            text-decoration: none;
        }
        .btn-gold:hover  { background: #c9a830; }
        .btn-gold:active { transform: scale(0.97); }

        .btn-outline {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: transparent; color: #091113;
            padding: 0.6rem 1.4rem; border-radius: 8px;
            font-size: 0.9rem; font-weight: 500; font-family: 'Prompt', sans-serif;
            border: 1.5px solid #3a3e43; cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-outline:hover { background: #091113; color: #eeebe1; }

        .btn-danger {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: #d2592a; color: #fff;
            padding: 0.6rem 1.4rem; border-radius: 8px;
            font-size: 0.9rem; font-weight: 500; font-family: 'Prompt', sans-serif;
            border: none; cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-danger:hover { background: #b84a22; }

        /* Form inputs */
        .journal-input {
            width: 100%;
            background: #fff;
            border: 1.5px solid #cecdcd;
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            font-family: 'Prompt', sans-serif;
            color: #091113;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .journal-input:focus {
            outline: none;
            border-color: #dab937;
            box-shadow: 0 0 0 3px rgba(218,185,55,0.15);
        }
        .journal-input::placeholder { color: #6b6e77; }

        /* Badge */
        .badge {
            display: inline-flex; align-items: center;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem; font-weight: 500;
        }

        /* Section heading */
        .section-title {
            font-size: 1.25rem; font-weight: 600; color: #091113;
            letter-spacing: -0.01em;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #dab937;
            display: inline-block;
        }

        /* Divider */
        .divider { border: none; border-top: 1px solid #cecdcd; margin: 1.5rem 0; }

        /* Notification dot */
        .notif-dot {
            position: absolute; top: -4px; right: -4px;
            width: 10px; height: 10px;
            background: #d2592a; border-radius: 50%;
            border: 2px solid #091113;
        }

        /* Notification Bell Button */
        .nav-notif-btn {
            position: relative; display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 10px;
            background: transparent; border: none; cursor: pointer;
            color: #9ca3af; transition: color 0.15s, background 0.15s;
        }
        .nav-notif-btn:hover { color: #eeebe1; background: #1a1f20; }
        .nav-notif-badge {
            position: absolute; top: -5px; right: -5px;
            min-width: 18px; height: 18px; padding: 0 4px;
            background: #d2592a; color: #fff;
            font-size: 0.65rem; font-weight: 700; font-family: 'Prompt', sans-serif;
            border-radius: 999px; border: 2px solid #091113;
            display: flex; align-items: center; justify-content: center;
            animation: notif-badge-pop 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
        }ไมท่ม
        @keyframes notif-badge-pop {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        /* Notification Dropdown */
        .nav-notif-dropdown {
            position: absolute; right: 0; top: calc(100% + 10px);
            width: 320px; max-height: 440px;
            background: #0d1618; border: 1px solid #3a3e43;
            border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.55);
            overflow: hidden; z-index: 60;
            animation: notif-drop-in 0.2s ease both;
        }
        @keyframes notif-drop-in {
            from { opacity: 0; transform: translateY(-6px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .nav-notif-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.85rem 1rem 0.7rem;
            border-bottom: 1px solid #1f2b2e;
        }
        .nav-notif-header-title { font-size: 0.82rem; font-weight: 700; color: #eeebe1; letter-spacing: 0.01em; }
        .nav-notif-header-count {
            font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem;
            background: rgba(255,255,255,0.07); color: #8a8e97;
            border: 1px solid rgba(255,255,255,0.12); border-radius: 999px;
        }
        .nav-notif-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2rem 1rem; color: #4a5054; font-size: 0.8rem;
        }
        .nav-notif-list { overflow-y: auto; max-height: 340px; }
        .nav-notif-item {
            display: flex; align-items: flex-start; gap: 0.75rem;
            padding: 0.8rem 1rem; text-decoration: none;
            border-bottom: 1px solid #1a2124;
            transition: background 0.12s;
        }
        .nav-notif-item:hover { background: #111d20; }
        .nav-notif-item-icon {
            flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-top: 0.1rem;
        }
        .nav-notif-icon-green { background:rgba(81,142,92,0.15); border:1px solid rgba(81,142,92,0.3); color:#7ec98a; }
        .nav-notif-icon-red   { background:rgba(210,89,42,0.15);  border:1px solid rgba(210,89,42,0.3);  color:#e8834a; }
        .nav-notif-item-body { flex: 1; min-width: 0; }
        .nav-notif-item-title {
            font-size: 0.82rem; font-weight: 600; color: #d8d4cb;
            margin: 0 0 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .nav-notif-item-sub { font-size: 0.72rem; color: #6b6e77; margin: 0; line-height: 1.45; }
        .nav-notif-item-new {
            flex-shrink: 0; font-size: 0.62rem; font-weight: 700;
            background: rgba(255,255,255,0.07); color: #8a8e97;
            border: 1px solid rgba(255,255,255,0.12); border-radius: 4px;
            padding: 0.1rem 0.4rem; align-self: flex-start; margin-top: 0.15rem;
        }
        .nav-notif-footer-link {
            display: block; text-align: center; padding: 0.7rem;
            font-size: 0.78rem; font-weight: 600; color: #dab937;
            border-top: 1px solid #1f2b2e; text-decoration: none;
            transition: background 0.12s;
        }
        .nav-notif-footer-link:hover { background: #111d20; color: #f0c940; }

        /* Nav logo hover */
        .nav-logo {
            opacity: 0.88;
            transition: opacity 0.2s ease, filter 0.2s ease;
        }
        .nav-logo-link:hover .nav-logo {
            opacity: 1;
            filter: brightness(1.12) drop-shadow(0 0 6px rgba(218,185,55,0.35));
        }

        /* Token spin */
        @keyframes token-spin {
            0%   { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }
        .token-spin {
            animation: token-spin 5s linear infinite;
            display: inline-block;
        }

        /* ── Global toast ── */
        #app-toast {
            position: fixed; top: 50%; left: 50%; z-index: 99999;
            display: flex; align-items: center; gap: 0.65rem;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            font-size: 0.95rem; font-weight: 500; font-family: 'Prompt', sans-serif;
            box-shadow: 0 16px 48px rgba(0,0,0,0.5);
            transform: translate(-50%, -50%) scale(0.85); opacity: 0;
            transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease;
            pointer-events: none; white-space: nowrap;
        }
        #app-toast.show  { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        #app-toast.toast-success { background: rgba(20,44,26,0.94); border: 1px solid rgba(81,142,92,0.5);  color: #7ec98a; }
        #app-toast.toast-error   { background: rgba(52,18,10,0.94); border: 1px solid rgba(210,89,42,0.5);  color: #e07a55; }
        #app-toast.toast-info    { background: rgba(14,30,70,0.94); border: 1px solid rgba(79,139,152,0.5); color: #5fa8ba; }
        #app-toast.toast-warning { background: rgba(50,40,10,0.94); border: 1px solid rgba(218,185,55,0.5); color: #dab937; }
    </style>
</head>

<body class="min-h-screen">

    <!-- ===================================================
         NAVIGATION BAR
         =================================================== -->
    <nav style="background-color:#091113;" class="sticky top-0 z-50" id="main-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <?php
                // ตรวจว่าอยู่ใน /hr/ zone หรือเปล่า
                $isAdminPage = (strpos($_SERVER['PHP_SELF'] ?? '', '/hr/') !== false);
                ?>
                <!-- Logo -->
                <div class="nav-logo-link flex items-center gap-3 flex-shrink-0">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logo.png"
                         alt="JOURNAL"
                         class="nav-logo h-20 w-auto">
                    <?php if ($isAdmin): ?>
                    <span class="badge text-xs" style="background:#62307a; color:#eeebe1;">Admin</span>
                    <?php elseif ($isHr): ?>
                    <span class="badge text-xs" style="background:#4f8b98; color:#eeebe1;">HR</span>
                    <?php elseif ($isIt): ?>
                    <span class="badge text-xs" style="background:#2f4e9d; color:#eeebe1;">IT</span>
                    <?php endif; ?>
                </div>

                <!-- Desktop Nav Links -->
                <div class="hidden md:flex items-center">
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
                        // อยู่ใน /hr/ → แสดง admin nav
                        $navLinks = [
                            'admin_challenges'   => ['label' => 'จัดการภารกิจ',    'href' => BASE_URL . '/hr/challenges/index.php'],
                            'admin_submissions'  => ['label' => 'อนุมัติงาน',     'href' => BASE_URL . '/hr/submissions.php', 'badge' => $pendingCount],
                            'admin_rewards'      => ['label' => 'จัดการรางวัล',   'href' => BASE_URL . '/hr/rewards/index.php'],
                            'admin_redemptions'  => ['label' => 'คำขอแลกรางวัล', 'href' => BASE_URL . '/hr/rewards/redemptions.php', 'badge' => $pendingRedemptionCount],
                            'admin_employees'    => ['label' => 'จัดการพนักงาน',  'href' => BASE_URL . '/hr/employees.php'],
                        ];
                    } else {
                        // อยู่ใน /pages/ หรือ employee zone → แสดง employee nav
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
                       class="relative flex items-center gap-1.5 px-4 py-5 text-sm font-medium transition-colors <?php echo $isActive ? 'nav-active' : ''; ?>"
                       style="color:<?php echo $isActive ? '#dab937' : '#9ca3af'; ?>;"
                       onmouseover="if(!this.classList.contains('nav-active')) this.style.color='#eeebe1'"
                       onmouseout="if(!this.classList.contains('nav-active')) this.style.color='#9ca3af'">
                        <?php echo $link['label']; ?>
                        <?php if (!empty($link['badge']) && $link['badge'] > 0): ?>
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full font-bold"
                              style="background:#d2592a; color:#fff;"><?php echo $link['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>

                </div>

                <!-- Right: Token Balance + User Menu -->
                <div id="nav-right" class="flex items-center gap-3">

                    <!-- Token Balance — แสดงเมื่ออยู่ใน employee zone -->
                    <?php if (!$isAdminPage || !$isAdminOrHr): ?>
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full"
                         style="background:#1a1f20; border: 1px solid #3a3e43;">
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt="token" width="18" height="18" style="object-fit:contain;" class="token-spin">
                        <span class="text-sm font-semibold" id="nav-balance" style="color:#dab937;"><?php echo formatTokens($navBalance); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Notification Bell (employee zone) -->
                    <?php if (!$isAdminPage || !$isAdminOrHr): ?>
                    <div class="relative" id="notif-wrap">
                        <button onclick="toggleNotifDropdown()" id="notif-bell-btn"
                                class="nav-notif-btn"
                                aria-label="การแจ้งเตือน">
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
                                <span class="nav-notif-header-count" id="notif-header-count"><?= $notifCount ?> รายการ</span>
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
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity:.3;margin-bottom:0.5rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <p>ไม่มีการแจ้งเตือน</p>
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
                            <div id="notif-empty-state" class="nav-notif-empty" style="display:none;">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity:.3;margin-bottom:0.5rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <p>ไม่มีการแจ้งเตือน</p>
                            </div>
                            <a href="<?= BASE_URL ?>/pages/history.php"
                               class="nav-notif-footer-link" id="notif-footer-link">
                                ดูประวัติทั้งหมด →
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- User Menu -->
                    <div class="relative">
                        <button onclick="toggleUserMenu()" id="user-menu-btn"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors"
                                style="color:#9ca3af;"
                                onmouseover="this.style.background='#1a1f20'"
                                onmouseout="this.style.background='transparent'">
                            <!-- Avatar -->
                            <?php $_navAvatar = $_SESSION['avatar_url'] ?? ''; ?>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0 overflow-hidden"
                                 style="background:#dab937; color:#091113;">
                                <?php if ($_navAvatar): ?>
                                <img src="<?php echo uploadImgUrl('avatars', $_navAvatar); ?>"
                                     alt="" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                <?php echo mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <span class="hidden sm:block text-sm font-medium max-w-[120px] truncate" style="color:#eeebe1;">
                                <?php echo e($_SESSION['full_name'] ?? ''); ?>
                            </span>
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown -->
                        <div id="user-dropdown"
                             class="hidden absolute right-0 mt-2 w-52 rounded-xl shadow-2xl overflow-hidden z-50"
                             style="background:#0d1618; border:1px solid #3a3e43;">
                            <!-- User info -->
                            <div class="px-4 py-3 border-b" style="border-color:#3a3e43;">
                                <p class="text-sm font-medium" style="color:#eeebe1;"><?php echo e($_SESSION['full_name'] ?? ''); ?></p>
                                <p class="text-xs mt-0.5" style="color:#6b6e77;"><?php echo e($_SESSION['employee_code'] ?? ''); ?></p>
                                <?php if (!$isAdmin): ?>
                                <p class="text-xs mt-1 font-semibold" style="color:#dab937;">
                                    <?php echo formatTokens($navBalance); ?> token
                                </p>
                                <?php endif; ?>
                            </div>
                            <!-- Links -->
                            <?php if ($isAdminOrHr && !$isAdmin): ?>
                            <!-- Zone switcher — HR/IT เท่านั้น -->
                            <?php if ($isAdminPage): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/dashboard.php"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b"
                               style="color:#9ca3af; border-color:#3a3e43;"
                               onmouseover="this.style.color='#eeebe1'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                ภารกิจของฉัน
                            </a>
                            <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/hr/submissions.php"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b"
                               style="color:#9ca3af; border-color:#3a3e43;"
                               onmouseover="this.style.color='#eeebe1'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                จัดการระบบ
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$isAdmin): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/profile.php"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b"
                               style="color:#9ca3af; border-color:#3a3e43;"
                               onmouseover="this.style.color='#eeebe1'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                โปรไฟล์ของฉัน
                            </a>
                            <a href="<?php echo BASE_URL; ?>/pages/strava_dashboard.php"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b"
                               style="color:#9ca3af; border-color:#3a3e43;"
                               onmouseover="this.style.color='#FC4C02'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                                </svg>
                                Strava Dashboard
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>/pages/help.php"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors border-b"
                               style="color:#9ca3af; border-color:#3a3e43;"
                               onmouseover="this.style.color='#eeebe1'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                คู่มือการใช้งาน
                            </a>
                            <a href="<?php echo BASE_URL; ?>/logout.php"
                               class="flex items-center gap-2 px-4 py-3 text-sm transition-colors"
                               style="color:#9ca3af;"
                               onmouseover="this.style.color='#d2592a'; this.style.background='#1a1f20'"
                               onmouseout="this.style.color='#9ca3af'; this.style.background='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                ออกจากระบบ
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Hamburger -->
                    <button onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-lg"
                            style="color:#9ca3af;"
                            onmouseover="this.style.background='#1a1f20'"
                            onmouseout="this.style.background='transparent'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t" style="border-color:#3a3e43; background:#091113;">
            <div class="px-4 py-3 space-y-1">
                <?php foreach ($navLinks as $key => $link): ?>
                <a href="<?php echo $link['href']; ?>"
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                   style="color:<?php echo ($currentActive === $key) ? '#dab937' : '#9ca3af'; ?>;
                          background:<?php echo ($currentActive === $key) ? '#1a1f20' : 'transparent'; ?>;">
                    <?php echo $link['label']; ?>
                    <?php if (!empty($link['badge']) && $link['badge'] > 0): ?>
                    <span class="px-1.5 py-0.5 text-xs rounded-full font-bold" style="background:#d2592a; color:#fff;"><?php echo $link['badge']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <!-- Token balance on mobile -->
                <?php if (!$isAdmin): ?>
                <div class="flex items-center gap-2 px-3 py-2 mt-2 rounded-lg" style="background:#1a1f20;">
                    <span class="text-xs" style="color:#6b6e77;">Token ของคุณ:</span>
                    <span class="text-sm font-semibold" style="color:#dab937;"><?php echo formatTokens($navBalance); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>



    <!-- ===================================================
         MAIN CONTENT WRAPPER
         =================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
