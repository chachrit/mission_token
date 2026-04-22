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

$isAdmin      = ($_SESSION['role'] ?? '') === 'admin';
$navBalance   = (int)($_SESSION['token_balance'] ?? 0);
$pendingCount = $isAdmin ? getPendingCount() : 0;
$flash        = getFlash();
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

        /* Token shimmer on nav balance */
        @keyframes gold-shimmer {
            0%   { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .text-gold-shimmer {
            background: linear-gradient(90deg, #dab937 0%, #f8e769 45%, #c9a830 55%, #dab937 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gold-shimmer 3s linear infinite;
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

        /* Nav logo hover */
        .nav-logo {
            opacity: 0.88;
            transition: opacity 0.2s ease, filter 0.2s ease;
        }
        .nav-logo-link:hover .nav-logo {
            opacity: 1;
            filter: brightness(1.12) drop-shadow(0 0 6px rgba(218,185,55,0.35));
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- ===================================================
         NAVIGATION BAR
         =================================================== -->
    <nav style="background-color:#091113;" class="sticky top-0 z-50" id="main-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Logo -->
                <a href="<?php echo BASE_URL; ?>/<?php echo $isAdmin ? 'admin/dashboard.php' : 'pages/dashboard.php'; ?>"
                   class="nav-logo-link flex items-center gap-3 flex-shrink-0">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logo.png"
                         alt="JOURNAL"
                         class="nav-logo h-20 w-auto">
                    <!-- <span class="hidden sm:block w-px h-5" style="background:#3a3e43;"></span>
                    <span class="hidden sm:block text-xs font-medium tracking-widest uppercase" style="color:#6b6e77;">
                        Mission Token
                    </span> -->
                    <?php if ($isAdmin): ?>
                    <span class="badge text-xs" style="background:#62307a; color:#eeebe1;">Admin</span>
                    <?php endif; ?>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden md:flex items-center">
                    <?php
                    if ($isAdmin) {
                        $navLinks = [
                            'admin_dashboard'    => ['label' => 'ภาพรวม',    'href' => BASE_URL . '/admin/dashboard.php'],
                            'admin_challenges'   => ['label' => 'ภารกิจ',    'href' => BASE_URL . '/admin/challenges/index.php'],
                            'admin_submissions'  => ['label' => 'ตรวจสอบงาน', 'href' => BASE_URL . '/admin/submissions.php', 'badge' => $pendingCount],
                            'admin_employees'    => ['label' => 'พนักงาน',    'href' => BASE_URL . '/admin/employees.php'],
                        ];
                    } else {
                        $navLinks = [
                            'dashboard'   => ['label' => 'หน้าแรก',  'href' => BASE_URL . '/index.php'],
                            'challenges'  => ['label' => 'ภารกิจ',   'href' => BASE_URL . '/pages/challenges.php'],
                            'history'     => ['label' => 'ประวัติ',   'href' => BASE_URL . '/pages/history.php'],
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
                <div class="flex items-center gap-3">

                    <!-- Token Balance (employee only) -->
                    <?php if (!$isAdmin): ?>
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full"
                         style="background:#1a1f20; border: 1px solid #3a3e43;">
                        <img src="<?php echo BASE_URL; ?>/assets/images/token.png" alt="token" width="18" height="18" style="object-fit:contain;">
                        <span class="text-sm font-semibold text-gold-shimmer" id="nav-balance"><?php echo formatTokens($navBalance); ?></span>
                        <span class="text-xs" style="color:#6b6e77;">token</span>
                    </div>
                    <?php endif; ?>

                    <!-- User Menu -->
                    <div class="relative">
                        <button onclick="toggleUserMenu()" id="user-menu-btn"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors"
                                style="color:#9ca3af;"
                                onmouseover="this.style.background='#1a1f20'"
                                onmouseout="this.style.background='transparent'">
                            <!-- Avatar initial -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                                 style="background:#dab937; color:#091113;">
                                <?php echo mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?>
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
                                    🪙 <?php echo formatTokens($navBalance); ?> token
                                </p>
                                <?php endif; ?>
                            </div>
                            <!-- Links -->
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
         FLASH MESSAGE
         =================================================== -->
    <?php if ($flash): ?>
    <?php
        $flashStyles = [
            'success' => ['bg' => '#518e5c', 'text' => '#fff',    'icon' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'],
            'error'   => ['bg' => '#d2592a', 'text' => '#fff',    'icon' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'],
            'info'    => ['bg' => '#2f4e9d', 'text' => '#fff',    'icon' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
            'warning' => ['bg' => '#dab937', 'text' => '#091113', 'icon' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'],
        ];
        $fs = $flashStyles[$flash['type']] ?? $flashStyles['info'];
    ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4" id="flash-wrap">
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium shadow-sm"
             style="background:<?php echo $fs['bg']; ?>; color:<?php echo $fs['text']; ?>;"
             id="flash-msg">
            <?php echo $fs['icon']; ?>
            <span class="flex-1"><?php echo e($flash['message']); ?></span>
            <button onclick="this.closest('#flash-msg').remove()" class="ml-2 opacity-60 hover:opacity-100 transition-opacity">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================
         MAIN CONTENT WRAPPER
         =================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
