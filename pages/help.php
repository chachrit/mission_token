<?php
/**
 * pages/help.php
 * User Manual â€” à¸„à¸¹à¹ˆà¸¡à¸·à¸­à¸à¸²à¸£à¹ƒà¸Šà¹‰à¸‡à¸²à¸™ Mission Token
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle  = 'à¸„à¸¹à¹ˆà¸¡à¸·à¸­à¸à¸²à¸£à¹ƒà¸Šà¹‰à¸‡à¸²à¸™';
$activePage = 'help';

require_once __DIR__ . '/../includes/header.php';
?>



<div class="hp-wrap">
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="hp-inner">

        <!-- â”€â”€ Hero â”€â”€ -->
        <div class="hp-hero">
            <p class="hp-hero-eyebrow">MISSION TOKEN</p>
            <h1 class="hp-hero-title">à¸„à¸¹à¹ˆà¸¡à¸·à¸­à¸à¸²à¸£à¹ƒà¸Šà¹‰à¸‡à¸²à¸™</h1>
            <p class="hp-hero-sub">à¸§à¸´à¸˜à¸µà¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸£à¸°à¸šà¸šà¸ªà¸°à¸ªà¸¡ Token à¸ à¸²à¸¢à¹ƒà¸™à¸­à¸‡à¸„à¹Œà¸à¸£ JOURNAL</p>
            <div class="hp-hero-stats">
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>8 à¸«à¸±à¸§à¸‚à¹‰à¸­</span>
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>3 à¸›à¸£à¸°à¹€à¸ à¸—à¸ à¸²à¸£à¸à¸´à¸ˆ</span>
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>FAQ & à¸„à¸³à¸•à¸­à¸š</span>
                <span class="hp-hero-stat" style="border-color:rgba(79,139,152,0.25);color:#5fa8ba;"><span class="hp-hero-stat-dot" style="background:#5fa8ba;box-shadow:0 0 6px rgba(79,139,152,0.5);"></span>à¸„à¸¹à¹ˆà¸¡à¸·à¸­ HR</span>
            </div>
        </div>

        <!-- â”€â”€ Tab switcher â”€â”€ -->
        <div class="hp-tabs-wrap">
            <div class="hp-tabs-track" id="hp-tabs-track">
                <div class="hp-tab-pill" id="hp-tab-pill"></div>
                <button class="hp-tab active" data-action="switchTab" data-tab="employee" id="tab-employee">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    à¸ªà¸³à¸«à¸£à¸±à¸šà¸žà¸™à¸±à¸à¸‡à¸²à¸™
                </button>
                <button class="hp-tab" data-action="switchTab" data-tab="hr" id="tab-hr">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    HR
                </button>
            </div>
        </div>

        <!-- â”€â”€ Search â”€â”€ -->
        <div class="hp-search-outer">
            <div class="hp-search-wrap">
                <input type="text" class="hp-search-input" id="hp-search"
                       placeholder="à¸„à¹‰à¸™à¸«à¸²à¹ƒà¸™à¸„à¸¹à¹ˆà¸¡à¸·à¸­ à¹€à¸Šà¹ˆà¸™ Token, Quiz, Strava..." oninput="searchHelp(this.value)">
                <svg class="hp-search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
            </div>
        </div>

        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
             EMPLOYEE TAB
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <div id="panel-employee">
        <div class="hp-layout">

            <!-- Sidebar -->
            <div class="hp-sidebar hidden md:block">
                <p class="hp-sidebar-title">à¸«à¸±à¸§à¸‚à¹‰à¸­</p>
                <button class="hp-sidebar-link active" data-action="scrollToSection" data-section-id="emp-login">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">LGN</span> à¸à¸²à¸£à¹€à¸‚à¹‰à¸²à¸ªà¸¹à¹ˆà¸£à¸°à¸šà¸š
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-dashboard">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">DS</span> Dashboard
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-challenges">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">Q</span> à¸ à¸²à¸£à¸à¸´à¸ˆ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-rewards">
                    <span class="hp-sl-icon">R</span> à¸£à¹‰à¸²à¸™à¸£à¸²à¸‡à¸§à¸±à¸¥
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-history">
                    <span class="hp-sl-icon">H</span> à¸›à¸£à¸°à¸§à¸±à¸•à¸´
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-profile">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">P</span> à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-strava">
                    <span class="hp-sl-icon" style="font-size:0.65rem; color:#FC4C02; background:rgba(252,76,2,0.1);">STR</span> Strava
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-faq">
                    <span class="hp-sl-icon" style="font-size:0.65rem;">FAQ</span> FAQ
                </button>
            </div>

            <!-- Content -->
            <div>

                <!-- à¹€à¸‚à¹‰à¸²à¸ªà¸¹à¹ˆà¸£à¸°à¸šà¸š -->
                <div class="hp-section open" id="emp-login" data-keywords="login à¹€à¸‚à¹‰à¸²à¸ªà¸¹à¹ˆà¸£à¸°à¸šà¸š à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™ portal">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">LGN</div>
                        <span class="hp-section-title-text">à¸à¸²à¸£à¹€à¸‚à¹‰à¸²à¸ªà¸¹à¹ˆà¸£à¸°à¸šà¸š</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">à¹ƒà¸Šà¹‰ <strong>à¸£à¸«à¸±à¸ªà¸žà¸™à¸±à¸à¸‡à¸²à¸™</strong> à¹à¸¥à¸° <strong>à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™</strong> à¹€à¸”à¸´à¸¡à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰à¸à¸±à¸š <strong>JOURNAL Web Portal</strong> à¹„à¸”à¹‰à¹€à¸¥à¸¢ â€” à¹„à¸¡à¹ˆà¸•à¹‰à¸­à¸‡à¸ªà¸¡à¸±à¸„à¸£à¹ƒà¸«à¸¡à¹ˆ</p>
                        <p class="hp-text" style="margin-bottom:0.4rem;"><strong style="color:#eeebe1;">à¸¥à¸·à¸¡à¸«à¸£à¸·à¸­à¸•à¹‰à¸­à¸‡à¸à¸²à¸£à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™?</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸à¸”à¸—à¸µà¹ˆ <strong style="color:#eeebe1;">à¸Šà¸·à¹ˆà¸­à¸‚à¸­à¸‡à¸„à¸¸à¸“</strong> (à¸¡à¸¸à¸¡à¸šà¸™à¸‚à¸§à¸²) â†’ à¹€à¸¥à¸·à¸­à¸ <strong>"à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œà¸‚à¸­à¸‡à¸‰à¸±à¸™"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>à¹€à¸¥à¸·à¹ˆà¸­à¸™à¸¥à¸‡à¸«à¸²à¸ªà¹ˆà¸§à¸™ <strong style="color:#eeebe1;">"à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™"</strong> à¸à¸£à¸­à¸à¸£à¸«à¸±à¸ªà¹€à¸à¹ˆà¸² + à¸£à¸«à¸±à¸ªà¹ƒà¸«à¸¡à¹ˆ (à¸­à¸¢à¹ˆà¸²à¸‡à¸™à¹‰à¸­à¸¢ 8 à¸•à¸±à¸§) â†’ à¸à¸” <strong>"à¸šà¸±à¸™à¸—à¸¶à¸"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>Login à¹„à¸¡à¹ˆà¹„à¸”à¹‰à¹€à¸¥à¸¢? à¹ƒà¸«à¹‰ <strong>à¸•à¸´à¸”à¸•à¹ˆà¸­ HR à¸«à¸£à¸·à¸­ IT</strong> à¹€à¸žà¸·à¹ˆà¸­à¸‚à¸­ Reset à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™</span>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>à¸£à¸°à¸šà¸šà¸­à¸­à¸à¸ˆà¸²à¸à¸£à¸°à¸šà¸šà¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´à¸«à¸²à¸à¹„à¸¡à¹ˆà¸¡à¸µà¸à¸²à¸£à¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸™à¸²à¸™ <strong>2 à¸Šà¸±à¹ˆà¸§à¹‚à¸¡à¸‡</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="hp-section" id="emp-dashboard" data-keywords="dashboard à¸«à¸™à¹‰à¸²à¹à¸£à¸ token streak à¸­à¸±à¸™à¸”à¸±à¸š leaderboard">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">DS</div>
                        <span class="hp-section-title-text">à¸«à¸™à¹‰à¸² Dashboard â€” à¸ à¸²à¸žà¸£à¸§à¸¡à¸‚à¸­à¸‡à¸„à¸¸à¸“</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">à¸«à¸™à¹‰à¸²à¹à¸£à¸à¸«à¸¥à¸±à¸‡ Login à¹à¸ªà¸”à¸‡à¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”à¸‚à¸­à¸‡à¸„à¸¸à¸“à¹ƒà¸™à¸—à¸µà¹ˆà¹€à¸”à¸µà¸¢à¸§</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num" style="background:rgba(218,185,55,0.15);">1</span>
                                <span><strong style="color:#dab937;">à¸à¸£à¸°à¹€à¸›à¹‹à¸² Token</strong> â€” Token à¸„à¸‡à¹€à¸«à¸¥à¸·à¸­ (à¹ƒà¸Šà¹‰à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥à¹„à¸”à¹‰), Token à¸—à¸µà¹ˆà¹„à¸”à¹‰à¸£à¸±à¸šà¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”, Token à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰à¹„à¸›à¹à¸¥à¹‰à¸§</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong style="color:#eeebe1;">à¸­à¸±à¸™à¸”à¸±à¸šà¸‚à¸­à¸‡à¸„à¸¸à¸“</strong> â€” à¸¥à¸³à¸”à¸±à¸šà¹ƒà¸™ Leaderboard à¸™à¸±à¸šà¸ˆà¸²à¸ Token à¸—à¸µà¹ˆà¹„à¸”à¹‰à¸£à¸±à¸šà¸—à¸±à¹‰à¸‡à¸«à¸¡à¸” (à¹„à¸¡à¹ˆà¹ƒà¸Šà¹ˆà¸„à¸‡à¹€à¸«à¸¥à¸·à¸­)</span>
                            </li>
                            <li>
                                <span class="hp-step-num">3</span>
                                <span><strong style="color:#eeebe1;">Streak</strong> â€” à¸ˆà¸³à¸™à¸§à¸™à¸§à¸±à¸™à¸•à¸´à¸”à¸•à¹ˆà¸­à¸à¸±à¸™à¸—à¸µà¹ˆà¸—à¸³à¸ à¸²à¸£à¸à¸´à¸ˆà¸œà¹ˆà¸²à¸™à¹à¸¥à¹‰à¸§</span>
                            </li>
                            <li>
                                <span class="hp-step-num">4</span>
                                <span><strong style="color:#eeebe1;">Token à¹€à¸”à¸·à¸­à¸™à¸™à¸µà¹‰</strong> â€” Token à¸—à¸µà¹ˆà¹„à¸”à¹‰à¸£à¸±à¸šà¹ƒà¸™à¹€à¸”à¸·à¸­à¸™à¸›à¸±à¸ˆà¸ˆà¸¸à¸šà¸±à¸™</span>
                            </li>
                            <li>
                                <span class="hp-step-num">5</span>
                                <span><strong style="color:#eeebe1;">à¸ à¸²à¸£à¸à¸´à¸ˆà¸—à¸µà¹ˆà¹€à¸›à¸´à¸”à¸­à¸¢à¸¹à¹ˆ</strong> â€” à¹à¸ªà¸”à¸‡à¸ à¸²à¸£à¸à¸´à¸ˆà¸—à¸µà¹ˆà¸—à¸³à¹„à¸”à¹‰à¸žà¸£à¹‰à¸­à¸¡ Token à¸£à¸²à¸‡à¸§à¸±à¸¥</span>
                            </li>
                            <li>
                                <span class="hp-step-num">6</span>
                                <span><strong style="color:#eeebe1;">à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸¥à¹ˆà¸²à¸ªà¸¸à¸”</strong> â€” 6 à¸£à¸²à¸¢à¸à¸²à¸£à¸¥à¹ˆà¸²à¸ªà¸¸à¸”à¸—à¸µà¹ˆà¸„à¸¸à¸“à¸ªà¹ˆà¸‡à¸‡à¸²à¸™ à¸žà¸£à¹‰à¸­à¸¡à¸ªà¸–à¸²à¸™à¸°</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- à¸ à¸²à¸£à¸à¸´à¸ˆ -->
                <div class="hp-section" id="emp-challenges" data-keywords="challenge à¸ à¸²à¸£à¸à¸´à¸ˆ quiz photo strava à¸à¸²à¸£à¹Œà¸” à¸žà¸¥à¸´à¸ token à¸£à¸²à¸‡à¸§à¸±à¸¥">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">Q</div>
                        <span class="hp-section-title-text">à¸«à¸™à¹‰à¸²à¸ à¸²à¸£à¸à¸´à¸ˆ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">à¸«à¸™à¹‰à¸²à¸™à¸µà¹‰à¹à¸ªà¸”à¸‡à¸ à¸²à¸£à¸à¸´à¸ˆà¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”à¸—à¸µà¹ˆà¹€à¸›à¸´à¸”à¸­à¸¢à¸¹à¹ˆ à¹à¸šà¹ˆà¸‡à¹€à¸›à¹‡à¸™ <strong>"à¸ à¸²à¸£à¸à¸´à¸ˆà¸£à¸­à¸„à¸¸à¸“à¸­à¸¢à¸¹à¹ˆ"</strong> (à¸¢à¸±à¸‡à¸—à¸³à¹„à¸”à¹‰) à¹à¸¥à¸° <strong>"à¹€à¸ªà¸£à¹‡à¸ˆà¸ªà¸´à¹‰à¸™à¹à¸¥à¹‰à¸§"</strong></p>

                        <p class="hp-text" style="margin-bottom:0.5rem;"><strong style="color:#eeebe1;">à¸§à¸´à¸˜à¸µà¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸à¸²à¸£à¹Œà¸”:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¹€à¸¥à¸·à¹ˆà¸­à¸™à¸”à¸¹à¸à¸²à¸£à¹Œà¸”à¸ à¸²à¸£à¸à¸´à¸ˆ à¹à¸•à¹ˆà¸¥à¸°à¹ƒà¸šà¹à¸ªà¸”à¸‡à¸Šà¸·à¹ˆà¸­à¹à¸¥à¸° Token à¸£à¸²à¸‡à¸§à¸±à¸¥</span></li>
                            <li><span class="hp-step-num">2</span><span><strong style="color:#eeebe1;">à¸§à¸²à¸‡à¹€à¸¡à¸²à¸ªà¹Œà¸šà¸™à¸à¸²à¸£à¹Œà¸”</strong> (à¸«à¸£à¸·à¸­à¹à¸•à¸°à¸šà¸™à¸¡à¸·à¸­à¸–à¸·à¸­) à¸à¸²à¸£à¹Œà¸”à¸ˆà¸° <strong style="color:#dab937;">à¸žà¸¥à¸´à¸à¹à¸ªà¸”à¸‡à¸£à¸²à¸¢à¸¥à¸°à¹€à¸­à¸µà¸¢à¸”</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>à¸”à¹‰à¸²à¸™à¸«à¸¥à¸±à¸‡à¸à¸²à¸£à¹Œà¸”à¹à¸ªà¸”à¸‡à¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚, à¸§à¸±à¸™à¸ªà¸´à¹‰à¸™à¸ªà¸¸à¸” à¹à¸¥à¸°à¸›à¸¸à¹ˆà¸¡à¸—à¸³à¸ à¸²à¸£à¸à¸´à¸ˆ</span></li>
                        </ol>

                        <p class="hp-text" style="margin-top:1.25rem; margin-bottom:0.5rem;"><strong style="color:#eeebe1;">à¸ à¸²à¸£à¸à¸´à¸ˆà¸¡à¸µ 3 à¸›à¸£à¸°à¹€à¸ à¸—:</strong></p>
                        <div class="hp-type-grid">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">Quiz â€” à¸•à¸­à¸šà¸„à¸³à¸–à¸²à¸¡</p>
                                <p class="hp-type-detail">
                                    à¸•à¹‰à¸­à¸‡à¸•à¸­à¸šà¸–à¸¹à¸ <strong style="color:#eeebe1;">à¸—à¸¸à¸à¸‚à¹‰à¸­</strong> à¸ˆà¸¶à¸‡à¹„à¸”à¹‰ Token<br>
                                    à¸—à¸³à¹„à¸”à¹‰ <strong style="color:#e07a55;">1 à¸„à¸£à¸±à¹‰à¸‡à¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™</strong> à¹„à¸¡à¹ˆà¸¡à¸µà¹‚à¸­à¸à¸²à¸ªà¹à¸à¹‰à¹„à¸‚<br><br>
                                    à¸à¸”à¸›à¸¸à¹ˆà¸¡ "à¹€à¸£à¸´à¹ˆà¸¡à¸—à¸³ Quiz" à¹à¸¥à¹‰à¸§à¸•à¸­à¸šà¸„à¸³à¸–à¸²à¸¡ â†’ à¸à¸” "à¸¢à¸·à¸™à¸¢à¸±à¸™à¸„à¸³à¸•à¸­à¸š"
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--photo">
                                <p class="hp-type-label">Photo â€” à¸ªà¹ˆà¸‡à¸£à¸¹à¸›à¸«à¸¥à¸±à¸à¸à¸²à¸™</p>
                                <p class="hp-type-detail">
                                    HR à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¸£à¸¹à¸›à¹à¸¥à¸°à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´ â†’ à¹„à¸”à¹‰ Token<br>
                                    à¸–à¹‰à¸² HR à¹„à¸¡à¹ˆà¸œà¹ˆà¸²à¸™ <strong style="color:#7ec98a;">à¸ªà¹ˆà¸‡à¹ƒà¸«à¸¡à¹ˆà¹„à¸”à¹‰</strong><br><br>
                                    à¹„à¸Ÿà¸¥à¹Œ: JPG/PNG/WebP à¸‚à¸™à¸²à¸”à¹„à¸¡à¹ˆà¹€à¸à¸´à¸™ 5MB
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label" style="color:#FC4C02;">Strava â€” à¸­à¸­à¸à¸à¸³à¸¥à¸±à¸‡à¸à¸²à¸¢</p>
                                <p class="hp-type-detail">
                                    à¸£à¸°à¸šà¸šà¸•à¸£à¸§à¸ˆà¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¹ƒà¸™ Strava à¸§à¹ˆà¸²à¸œà¹ˆà¸²à¸™à¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚à¹„à¸«à¸¡<br>
                                    à¸–à¹‰à¸²à¹„à¸¡à¹ˆà¸žà¸š <strong style="color:#FC4C02;">à¸¥à¸­à¸‡à¹ƒà¸«à¸¡à¹ˆà¹„à¸”à¹‰</strong> à¸«à¸¥à¸±à¸‡à¸šà¸±à¸™à¸—à¸¶à¸à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¹€à¸žà¸´à¹ˆà¸¡<br><br>
                                    à¸•à¹‰à¸­à¸‡à¹€à¸Šà¸·à¹ˆà¸­à¸¡à¸•à¹ˆà¸­ Strava à¸à¹ˆà¸­à¸™ (à¸—à¸³à¸„à¸£à¸±à¹‰à¸‡à¹€à¸”à¸µà¸¢à¸§)
                                </p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>Quiz à¸—à¸³à¹„à¸”à¹‰ <strong>à¸„à¸£à¸±à¹‰à¸‡à¹€à¸”à¸µà¸¢à¸§</strong> à¸­à¹ˆà¸²à¸™à¸„à¸³à¸–à¸²à¸¡à¹ƒà¸«à¹‰à¸„à¸£à¸šà¸à¹ˆà¸­à¸™à¸à¸”à¸¢à¸·à¸™à¸¢à¸±à¸™à¹€à¸ªà¸¡à¸­</span>
                        </div>
                    </div>
                </div>

                <!-- à¸£à¹‰à¸²à¸™à¸£à¸²à¸‡à¸§à¸±à¸¥ -->
                <div class="hp-section" id="emp-rewards" data-keywords="reward à¸£à¸²à¸‡à¸§à¸±à¸¥ à¹à¸¥à¸ token shop à¸„à¸¹à¸›à¸­à¸‡ stock">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">à¸£à¸²à¸‡à¸§à¸±à¸¥</div>
                        <span class="hp-section-title-text">à¸£à¹‰à¸²à¸™à¸£à¸²à¸‡à¸§à¸±à¸¥</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">à¸™à¸³ Token à¸—à¸µà¹ˆà¸ªà¸°à¸ªà¸¡à¹„à¸”à¹‰à¹„à¸›à¹à¸¥à¸à¹€à¸›à¹‡à¸™à¸£à¸²à¸‡à¸§à¸±à¸¥à¸•à¹ˆà¸²à¸‡à¹† à¸—à¸µà¹ˆà¸­à¸‡à¸„à¹Œà¸à¸£à¸ˆà¸±à¸”à¹„à¸§à¹‰à¹ƒà¸«à¹‰</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸”à¸¹à¸£à¸²à¸¢à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥ à¹à¸•à¹ˆà¸¥à¸°à¸£à¸²à¸¢à¸à¸²à¸£à¹à¸ªà¸”à¸‡à¸£à¸²à¸„à¸² Token</span></li>
                            <li><span class="hp-step-num">2</span><span>à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸š <strong style="color:#dab937;">Token à¸„à¸‡à¹€à¸«à¸¥à¸·à¸­</strong> à¸—à¸µà¹ˆà¸¡à¸¸à¸¡à¸šà¸™à¸‚à¸§à¸²à¸§à¹ˆà¸²à¹€à¸žà¸µà¸¢à¸‡à¸žà¸­</span></li>
                            <li><span class="hp-step-num">3</span><span>à¸à¸”à¸›à¸¸à¹ˆà¸¡ <strong style="color:#dab937;">"à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>à¸¢à¸·à¸™à¸¢à¸±à¸™à¸à¸²à¸£à¹à¸¥à¸ â€” Token à¸ˆà¸°à¸–à¸¹à¸à¸«à¸±à¸à¸—à¸±à¸™à¸—à¸µ</span></li>
                            <li><span class="hp-step-num">5</span><span>à¸£à¸­ HR à¸ˆà¸±à¸”à¹€à¸•à¸£à¸µà¸¢à¸¡à¹à¸¥à¸°à¸ªà¹ˆà¸‡à¸¡à¸­à¸š à¸ªà¸–à¸²à¸™à¸°à¸ˆà¸°à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸ˆà¸²à¸ <em>"à¸£à¸­à¸”à¸³à¹€à¸™à¸´à¸™à¸à¸²à¸£"</em> à¹€à¸›à¹‡à¸™ <em>"à¸ªà¸³à¹€à¸£à¹‡à¸ˆ"</em></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>à¸£à¸²à¸‡à¸§à¸±à¸¥à¸šà¸²à¸‡à¸­à¸¢à¹ˆà¸²à¸‡à¸¡à¸µ <strong>à¸£à¸«à¸±à¸ªà¸„à¸¹à¸›à¸­à¸‡</strong> â€” à¸ˆà¸°à¸›à¸£à¸²à¸à¸à¹ƒà¸™à¸«à¸™à¹‰à¸²à¸›à¸£à¸°à¸§à¸±à¸•à¸´ à¸«à¸¥à¸±à¸‡ HR à¸¢à¸·à¸™à¸¢à¸±à¸™à¸à¸²à¸£à¸ªà¹ˆà¸‡à¸¡à¸­à¸šà¹à¸¥à¹‰à¸§à¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™</span>
                        </div>
                        <div class="hp-tip hp-tip--info">
                                                        <span>à¸£à¸²à¸‡à¸§à¸±à¸¥à¸—à¸µà¹ˆà¸¡à¸µà¸•à¸±à¸§à¹€à¸¥à¸‚à¸ªà¸•à¹‡à¸­à¸ â€” à¹€à¸¡à¸·à¹ˆà¸­à¸«à¸¡à¸”à¹à¸¥à¹‰à¸§à¸›à¸¸à¹ˆà¸¡à¹à¸¥à¸à¸ˆà¸°à¸›à¸´à¸”à¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´</span>
                        </div>
                    </div>
                </div>

                <!-- à¸›à¸£à¸°à¸§à¸±à¸•à¸´ -->
                <div class="hp-section" id="emp-history" data-keywords="history à¸›à¸£à¸°à¸§à¸±à¸•à¸´ transaction token à¸£à¸²à¸‡à¸§à¸±à¸¥ à¸„à¸¹à¸›à¸­à¸‡">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">H</div>
                        <span class="hp-section-title-text">à¸«à¸™à¹‰à¸²à¸›à¸£à¸°à¸§à¸±à¸•à¸´</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">à¸”à¸¹à¸›à¸£à¸°à¸§à¸±à¸•à¸´à¸—à¸¸à¸à¸­à¸¢à¹ˆà¸²à¸‡à¸—à¸µà¹ˆà¹€à¸à¸´à¸”à¸‚à¸¶à¹‰à¸™à¸à¸±à¸šà¸šà¸±à¸à¸Šà¸µ à¹à¸šà¹ˆà¸‡à¹€à¸›à¹‡à¸™ 2 à¹à¸—à¹‡à¸š</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">1</span>
                                <span><strong style="color:#dab937;">à¹à¸—à¹‡à¸š Token</strong> â€” à¸£à¸²à¸¢à¸à¸²à¸£à¸£à¸±à¸š-à¸ˆà¹ˆà¸²à¸¢ Token à¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”: à¹„à¸”à¹‰à¸ˆà¸²à¸ Quiz, Photo, Strava / à¸«à¸±à¸à¹€à¸¡à¸·à¹ˆà¸­à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥ / à¸›à¸£à¸±à¸šà¹‚à¸”à¸¢ HR</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong style="color:#eeebe1;">à¹à¸—à¹‡à¸š à¸£à¸²à¸‡à¸§à¸±à¸¥</strong> â€” à¸£à¸²à¸¢à¸à¸²à¸£à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥à¸žà¸£à¹‰à¸­à¸¡à¸ªà¸–à¸²à¸™à¸°: <em>à¸£à¸­à¸”à¸³à¹€à¸™à¸´à¸™à¸à¸²à¸£</em> / <em>à¸ªà¸³à¹€à¸£à¹‡à¸ˆ</em> / <em>à¸¢à¸à¹€à¸¥à¸´à¸</em><br>
                                <span style="color:#FC4C02;">à¸£à¸«à¸±à¸ªà¸„à¸¹à¸›à¸­à¸‡à¸ˆà¸°à¸›à¸£à¸²à¸à¸à¸•à¸£à¸‡à¸™à¸µà¹‰à¹€à¸¡à¸·à¹ˆà¸­à¸£à¸²à¸‡à¸§à¸±à¸¥à¸ªà¸³à¹€à¸£à¹‡à¸ˆà¹à¸¥à¹‰à¸§</span></span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ -->
                <div class="hp-section" id="emp-profile" data-keywords="profile à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ à¸£à¸¹à¸› à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™ password à¸­à¸²à¸¢à¸¸à¸‡à¸²à¸™">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">P</div>
                        <span class="hp-section-title-text">à¸«à¸™à¹‰à¸²à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">à¹€à¸‚à¹‰à¸²à¹„à¸”à¹‰à¸ˆà¸²à¸ à¹€à¸¡à¸™à¸¹à¸Šà¸·à¹ˆà¸­à¸‚à¸­à¸‡à¸„à¸¸à¸“ (à¸¡à¸¸à¸¡à¸šà¸™à¸‚à¸§à¸²) â†’ "à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œà¸‚à¸­à¸‡à¸‰à¸±à¸™"</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">1</span>
                                <span><strong style="color:#eeebe1;">à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸£à¸¹à¸›à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ</strong> â€” à¸à¸”à¸—à¸µà¹ˆà¸£à¸¹à¸› à¹€à¸¥à¸·à¸­à¸à¹„à¸Ÿà¸¥à¹Œà¸ à¸²à¸ž (JPG/PNG/WebP â‰¤ 2MB) à¹à¸¥à¹‰à¸§à¸à¸” "à¸­à¸±à¸›à¹‚à¸«à¸¥à¸”"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong style="color:#eeebe1;">à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™</strong> â€” à¸à¸£à¸­à¸à¸£à¸«à¸±à¸ªà¸›à¸±à¸ˆà¸ˆà¸¸à¸šà¸±à¸™ + à¸£à¸«à¸±à¸ªà¹ƒà¸«à¸¡à¹ˆ (à¸­à¸¢à¹ˆà¸²à¸‡à¸™à¹‰à¸­à¸¢ 8 à¸•à¸±à¸§) à¹à¸¥à¹‰à¸§à¸à¸” "à¸šà¸±à¸™à¸—à¸¶à¸"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">3</span>
                                <span><strong style="color:#eeebe1;">à¸­à¸²à¸¢à¸¸à¸‡à¸²à¸™</strong> â€” à¹à¸ªà¸”à¸‡à¸£à¸°à¸¢à¸°à¹€à¸§à¸¥à¸²à¸—à¸µà¹ˆà¸—à¸³à¸‡à¸²à¸™à¹ƒà¸™à¸­à¸‡à¸„à¹Œà¸à¸£</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Strava -->
                <div class="hp-section" id="emp-strava" data-keywords="strava à¸§à¸´à¹ˆà¸‡ à¸›à¸±à¹ˆà¸™ à¹€à¸”à¸´à¸™ à¸à¸´à¸ˆà¸à¸£à¸£à¸¡ à¸­à¸­à¸à¸à¸³à¸¥à¸±à¸‡à¸à¸²à¸¢ à¹€à¸Šà¸·à¹ˆà¸­à¸¡à¸•à¹ˆà¸­">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="background:rgba(252,76,2,0.1);">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#FC4C02">
                                <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                            </svg>
                        </div>
                        <span class="hp-section-title-text">Strava â€” à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸­à¸­à¸à¸à¸³à¸¥à¸±à¸‡à¸à¸²à¸¢</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">Strava à¸„à¸·à¸­à¹à¸­à¸›à¸šà¸±à¸™à¸—à¸¶à¸à¸à¸²à¸£à¸­à¸­à¸à¸à¸³à¸¥à¸±à¸‡à¸à¸²à¸¢ à¸£à¸°à¸šà¸šà¹ƒà¸Šà¹‰ Strava à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸ªà¸³à¸«à¸£à¸±à¸šà¸ à¸²à¸£à¸à¸´à¸ˆà¸›à¸£à¸°à¹€à¸ à¸— Strava</p>

                        <p class="hp-text" style="margin-bottom:0.25rem;"><strong style="color:#eeebe1;">à¸‚à¸±à¹‰à¸™à¸•à¸­à¸™à¹€à¸Šà¸·à¹ˆà¸­à¸¡à¸•à¹ˆà¸­ (à¸—à¸³à¸„à¸£à¸±à¹‰à¸‡à¹à¸£à¸à¸„à¸£à¸±à¹‰à¸‡à¹€à¸”à¸µà¸¢à¸§):</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¹„à¸›à¸—à¸µà¹ˆà¸«à¸™à¹‰à¸² <strong style="color:#eeebe1;">à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ</strong> â†’ à¹€à¸¥à¸·à¹ˆà¸­à¸™à¸¥à¸‡à¸¡à¸²à¸«à¸²à¸ªà¹ˆà¸§à¸™ Strava</span></li>
                            <li><span class="hp-step-num">2</span><span>à¸à¸”à¸›à¸¸à¹ˆà¸¡ <strong style="color:#FC4C02;">"à¹€à¸Šà¸·à¹ˆà¸­à¸¡à¸•à¹ˆà¸­ Strava"</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>à¸£à¸°à¸šà¸šà¸ˆà¸°à¸žà¸²à¹„à¸› Strava.com à¹ƒà¸«à¹‰à¸à¸” <strong>"Authorize"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>à¸£à¸°à¸šà¸šà¸ˆà¸°à¸žà¸²à¸à¸¥à¸±à¸šà¸¡à¸²à¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´ â€” à¹€à¸Šà¸·à¹ˆà¸­à¸¡à¸•à¹ˆà¸­à¸ªà¸³à¹€à¸£à¹‡à¸ˆ</span></li>
                        </ol>

                        <p class="hp-text" style="margin-bottom:0.25rem; margin-top:1rem;"><strong style="color:#eeebe1;">à¸§à¸´à¸˜à¸µà¸—à¸³à¸ à¸²à¸£à¸à¸´à¸ˆ Strava:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸šà¸±à¸™à¸—à¸¶à¸à¸à¸´à¸ˆà¸à¸£à¸£à¸¡ (à¸§à¸´à¹ˆà¸‡/à¸›à¸±à¹ˆà¸™) à¹ƒà¸«à¹‰à¸„à¸£à¸šà¸•à¸²à¸¡à¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚à¸à¹ˆà¸­à¸™</span></li>
                            <li><span class="hp-step-num">2</span><span>à¸à¸¥à¸±à¸šà¸¡à¸²à¸—à¸µà¹ˆà¸«à¸™à¹‰à¸²à¸ à¸²à¸£à¸à¸´à¸ˆ à¸à¸”à¸à¸²à¸£à¹Œà¸”à¹€à¸žà¸·à¹ˆà¸­à¸”à¸¹à¸”à¹‰à¸²à¸™à¸«à¸¥à¸±à¸‡</span></li>
                            <li><span class="hp-step-num">3</span><span>à¸à¸”à¸›à¸¸à¹ˆà¸¡ <strong style="color:#FC4C02;">"à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¸à¸´à¸ˆà¸à¸£à¸£à¸¡ Strava"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>à¸£à¸­à¸›à¸£à¸°à¸¡à¸²à¸“ <strong style="color:#eeebe1;">15â€“30 à¸§à¸´à¸™à¸²à¸—à¸µ</strong> à¸«à¹‰à¸²à¸¡à¸›à¸´à¸”à¸«à¸™à¹‰à¸²à¸•à¹ˆà¸²à¸‡à¸£à¸°à¸«à¸§à¹ˆà¸²à¸‡à¸£à¸­</span></li>
                            <li><span class="hp-step-num">5</span><span>à¸œà¹ˆà¸²à¸™ â†’ à¹„à¸”à¹‰ Token à¸—à¸±à¸™à¸—à¸µ | à¹„à¸¡à¹ˆà¸œà¹ˆà¸²à¸™ â†’ à¸—à¸³à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¹€à¸žà¸´à¹ˆà¸¡à¹à¸¥à¹‰à¸§à¸¥à¸­à¸‡à¹ƒà¸«à¸¡à¹ˆ</span></li>
                        </ol>

                        <div class="hp-tip hp-tip--warn">
                            <span>à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¹ƒà¸™ Strava à¸•à¹‰à¸­à¸‡à¸•à¸±à¹‰à¸‡à¹€à¸›à¹‡à¸™ <strong>"Everyone"</strong> à¸«à¸£à¸·à¸­ <strong>"Followers"</strong> (à¹„à¸¡à¹ˆà¹ƒà¸Šà¹ˆ Private) à¸£à¸°à¸šà¸šà¸ˆà¸¶à¸‡à¸ˆà¸°à¸¡à¸­à¸‡à¹€à¸«à¹‡à¸™</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ à¸žà¸™à¸±à¸à¸‡à¸²à¸™ -->
                <div class="hp-section" id="emp-faq" data-keywords="faq à¸„à¸³à¸–à¸²à¸¡ quiz à¸‹à¹‰à¸³ token à¸«à¸¡à¸”à¸­à¸²à¸¢à¸¸ strava à¹„à¸¡à¹ˆà¸žà¸š">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">à¸Šà¹ˆà¸§à¸¢à¹€à¸«à¸¥à¸·à¸­</div>
                        <span class="hp-section-title-text">à¸„à¸³à¸–à¸²à¸¡à¸—à¸µà¹ˆà¸žà¸šà¸šà¹ˆà¸­à¸¢</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¸—à¸³ Quiz à¸•à¸­à¸šà¸œà¸´à¸” à¸ªà¸²à¸¡à¸²à¸£à¸–à¸¥à¸­à¸‡à¹ƒà¸«à¸¡à¹ˆà¹„à¸”à¹‰à¹„à¸«à¸¡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¹„à¸¡à¹ˆà¹„à¸”à¹‰ Quiz à¸—à¸³à¹„à¸”à¹‰ <strong style="color:#e07a55;">1 à¸„à¸£à¸±à¹‰à¸‡à¸•à¹ˆà¸­à¸ à¸²à¸£à¸à¸´à¸ˆà¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™</strong> à¹à¸¡à¹‰à¸•à¸­à¸šà¸œà¸´à¸”à¸à¹‡à¹„à¸¡à¹ˆà¸¡à¸µà¹‚à¸­à¸à¸²à¸ªà¹à¸à¹‰à¹„à¸‚ à¸­à¹ˆà¸²à¸™à¸—à¸¸à¸à¸‚à¹‰à¸­à¸à¹ˆà¸­à¸™à¸à¸”à¸¢à¸·à¸™à¸¢à¸±à¸™</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¸ªà¹ˆà¸‡à¸£à¸¹à¸›à¸ à¸²à¸žà¹à¸¥à¹‰à¸§ HR à¹„à¸¡à¹ˆà¸œà¹ˆà¸²à¸™ à¸—à¸³à¸­à¸°à¹„à¸£à¹„à¸”à¹‰à¸šà¹‰à¸²à¸‡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¸ªà¹ˆà¸‡à¹ƒà¸«à¸¡à¹ˆà¹„à¸”à¹‰à¹€à¸¥à¸¢ à¸à¸¥à¸±à¸šà¹„à¸›à¸—à¸µà¹ˆà¸«à¸™à¹‰à¸²à¸ à¸²à¸£à¸à¸´à¸ˆ à¸à¸¥à¸±à¸šà¸à¸²à¸£à¹Œà¸” à¸ˆà¸°à¸¡à¸µà¸›à¸¸à¹ˆà¸¡ "à¸ªà¹ˆà¸‡à¸«à¸¥à¸±à¸à¸à¸²à¸™à¹ƒà¸«à¸¡à¹ˆ" à¸›à¸£à¸²à¸à¸à¸‚à¸¶à¹‰à¸™ à¹à¸à¹‰à¹„à¸‚à¸£à¸¹à¸›à¹à¸¥à¹‰à¸§à¸ªà¹ˆà¸‡à¸­à¸µà¸à¸„à¸£à¸±à¹‰à¸‡</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    Token à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥à¹„à¸›à¹à¸¥à¹‰à¸§à¸«à¸²à¸¢à¹„à¸›à¹€à¸¥à¸¢à¹„à¸«à¸¡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¹ƒà¸Šà¹ˆ Token à¸„à¸‡à¹€à¸«à¸¥à¸·à¸­à¸ˆà¸°à¸¥à¸”à¸¥à¸‡ à¹à¸•à¹ˆ <strong style="color:#7ec98a;">Token à¸—à¸µà¹ˆà¹„à¸”à¹‰à¸£à¸±à¸šà¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”à¸¢à¸±à¸‡à¸„à¸‡à¸™à¸±à¸š</strong> à¸­à¸¢à¸¹à¹ˆà¹ƒà¸™ Leaderboard à¹„à¸¡à¹ˆà¸«à¸²à¸¢à¹„à¸›à¹„à¸«à¸™</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    Token à¸¡à¸µà¸§à¸±à¸™à¸«à¸¡à¸”à¸­à¸²à¸¢à¸¸à¹„à¸«à¸¡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¸£à¸°à¸šà¸šà¸›à¸±à¸ˆà¸ˆà¸¸à¸šà¸±à¸™à¹„à¸¡à¹ˆà¸¡à¸µà¸§à¸±à¸™à¸«à¸¡à¸”à¸­à¸²à¸¢à¸¸ à¸ªà¸­à¸šà¸–à¸²à¸¡ HR à¹€à¸žà¸·à¹ˆà¸­à¸¢à¸·à¸™à¸¢à¸±à¸™à¸™à¹‚à¸¢à¸šà¸²à¸¢à¸‚à¸­à¸‡à¸­à¸‡à¸„à¹Œà¸à¸£</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    Strava à¸„à¹‰à¸²à¸‡à¸™à¸²à¸™ à¸«à¸£à¸·à¸­à¸šà¸­à¸à¹„à¸¡à¹ˆà¸žà¸šà¸à¸´à¸ˆà¸à¸£à¸£à¸¡ à¸—à¸³à¸­à¸¢à¹ˆà¸²à¸‡à¹„à¸£?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">
                                    à¸„à¹‰à¸²à¸‡à¸™à¸²à¸™ (15â€“30 à¸§à¸´) à¹€à¸›à¹‡à¸™à¹€à¸£à¸·à¹ˆà¸­à¸‡à¸›à¸à¸•à¸´ à¸£à¸­à¹à¸¥à¸°à¸­à¸¢à¹ˆà¸²à¸›à¸´à¸”à¸«à¸™à¹‰à¸²<br><br>
                                    à¸–à¹‰à¸²à¸šà¸­à¸à¹„à¸¡à¹ˆà¸žà¸šà¸à¸´à¸ˆà¸à¸£à¸£à¸¡ à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸š:<br>
                                    1. à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸•à¸±à¹‰à¸‡à¹€à¸›à¹‡à¸™ Private à¸­à¸¢à¸¹à¹ˆà¹„à¸«à¸¡ â†’ à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¹€à¸›à¹‡à¸™ Everyone/Followers<br>
                                    2. à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸­à¸¢à¸¹à¹ˆà¹ƒà¸™à¸Šà¹ˆà¸§à¸‡à¸§à¸±à¸™à¸—à¸µà¹ˆà¸ à¸²à¸£à¸à¸´à¸ˆà¹„à¸«à¸¡<br>
                                    3. à¸›à¸£à¸°à¹€à¸ à¸—à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸•à¸£à¸‡à¸à¸±à¸šà¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚à¹„à¸«à¸¡ (à¹€à¸Šà¹ˆà¸™ à¸§à¸´à¹ˆà¸‡/à¸›à¸±à¹ˆà¸™)<br>
                                    4. à¸£à¸°à¸¢à¸°à¸—à¸²à¸‡/à¹€à¸§à¸¥à¸²à¸„à¸£à¸šà¸•à¸²à¸¡à¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚à¹„à¸«à¸¡
                                </div></div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /content -->
        </div><!-- /layout -->
        </div><!-- /panel-employee -->


        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
             HR / ADMIN TAB
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <div id="panel-hr" style="display:none;">
        <div class="hp-layout">

            <!-- Sidebar HR -->
            <div class="hp-sidebar hidden md:block">
                <p class="hp-sidebar-title">à¸«à¸±à¸§à¸‚à¹‰à¸­</p>
                <button class="hp-sidebar-link active" data-action="scrollToSection" data-section-id="hr-challenges">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">Q</span> à¸ˆà¸±à¸”à¸à¸²à¸£à¸ à¸²à¸£à¸à¸´à¸ˆ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-submissions">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">OK</span> à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´à¸‡à¸²à¸™
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-rewards">
                    <span class="hp-sl-icon">R</span> à¸ˆà¸±à¸”à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-redemptions">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">RQ</span> à¸„à¸³à¸‚à¸­à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-employees">
                    <span class="hp-sl-icon" style="font-size:0.6rem;">EMP</span> à¸ˆà¸±à¸”à¸à¸²à¸£à¸žà¸™à¸±à¸à¸‡à¸²à¸™
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-faq">
                    <span class="hp-sl-icon" style="font-size:0.65rem;">FAQ</span> FAQ
                </button>
            </div>

            <!-- Content HR -->
            <div>

                <!-- à¸ˆà¸±à¸”à¸à¸²à¸£à¸ à¸²à¸£à¸à¸´à¸ˆ -->
                <div class="hp-section open" id="hr-challenges" data-keywords="challenge à¸ à¸²à¸£à¸à¸´à¸ˆ à¸ªà¸£à¹‰à¸²à¸‡ quiz strava photo toggle à¸¥à¸š">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">Q</div>
                        <span class="hp-section-title-text">à¸ˆà¸±à¸”à¸à¸²à¸£à¸ à¸²à¸£à¸à¸´à¸ˆ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text" style="margin-bottom:0.5rem;"><strong style="color:#eeebe1;">à¸ªà¸£à¹‰à¸²à¸‡à¸ à¸²à¸£à¸à¸´à¸ˆà¹ƒà¸«à¸¡à¹ˆ:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸à¸”à¸›à¸¸à¹ˆà¸¡ <strong style="color:#dab937;">"+ à¹€à¸žà¸´à¹ˆà¸¡à¸ à¸²à¸£à¸à¸´à¸ˆ"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>à¸à¸£à¸­à¸ à¸Šà¸·à¹ˆà¸­, à¸£à¸²à¸¢à¸¥à¸°à¹€à¸­à¸µà¸¢à¸”, à¸›à¸£à¸°à¹€à¸ à¸— (Quiz/Photo/Strava), Token à¸£à¸²à¸‡à¸§à¸±à¸¥, à¸§à¸±à¸™à¹€à¸£à¸´à¹ˆà¸¡-à¸ªà¸´à¹‰à¸™à¸ªà¸¸à¸”</span></li>
                            <li><span class="hp-step-num">3</span><span>à¸à¸” <strong>"à¸šà¸±à¸™à¸—à¸¶à¸"</strong></span></li>
                        </ol>
                        <div class="hp-type-grid" style="margin-top:1rem;">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">Quiz</p>
                                <p class="hp-type-detail">à¸«à¸¥à¸±à¸‡à¸šà¸±à¸™à¸—à¸¶à¸à¹à¸¥à¹‰à¸§ à¸à¸” "+ à¹€à¸žà¸´à¹ˆà¸¡à¸„à¸³à¸–à¸²à¸¡"<br>à¸à¸£à¸­à¸à¸„à¸³à¸–à¸²à¸¡ + à¸•à¸±à¸§à¹€à¸¥à¸·à¸­à¸ Aâ€“D + à¹€à¸‰à¸¥à¸¢<br>à¹€à¸žà¸´à¹ˆà¸¡à¹„à¸”à¹‰à¸«à¸¥à¸²à¸¢à¸‚à¹‰à¸­ â€” à¸•à¹‰à¸­à¸‡à¸–à¸¹à¸à¸—à¸¸à¸à¸‚à¹‰à¸­</p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label" style="color:#FC4C02;">Strava</p>
                                <p class="hp-type-detail">à¸£à¸°à¸šà¸¸: à¸›à¸£à¸°à¹€à¸ à¸—à¸à¸´à¸ˆà¸à¸£à¸£à¸¡ (Run/Ride/Walk),<br>à¸£à¸°à¸¢à¸°à¸—à¸²à¸‡à¸‚à¸±à¹‰à¸™à¸•à¹ˆà¸³ (à¸à¸¡.), à¹€à¸§à¸¥à¸²à¸‚à¸±à¹‰à¸™à¸•à¹ˆà¸³ (à¸™à¸²à¸—à¸µ),<br>à¸„à¸§à¸²à¸¡à¸ªà¸¹à¸‡à¸‚à¸±à¹‰à¸™à¸•à¹ˆà¸³ (à¹€à¸¡à¸•à¸£) â€” à¹„à¸¡à¹ˆà¸šà¸±à¸‡à¸„à¸±à¸š</p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn" style="margin-top:1rem;">
                            <span>à¸à¸²à¸£ <strong>à¸¥à¸šà¸ à¸²à¸£à¸à¸´à¸ˆ</strong> à¸ˆà¸°à¸¥à¸šà¸›à¸£à¸°à¸§à¸±à¸•à¸´à¸à¸²à¸£à¸ªà¹ˆà¸‡à¸‡à¸²à¸™à¹à¸¥à¸° Token à¸—à¸µà¹ˆà¹€à¸„à¸¢à¹ƒà¸«à¹‰à¹„à¸›à¸”à¹‰à¸§à¸¢ â€” à¸–à¸²à¸§à¸£ à¹„à¸¡à¹ˆà¸ªà¸²à¸¡à¸²à¸£à¸–à¸à¸¹à¹‰à¸„à¸·à¸™à¹„à¸”à¹‰ à¹ƒà¸Šà¹‰à¸›à¸¸à¹ˆà¸¡ Toggle à¸‹à¹ˆà¸­à¸™à¹à¸—à¸™à¸–à¹‰à¸²à¹„à¸¡à¹ˆà¹à¸™à¹ˆà¹ƒà¸ˆ</span>
                        </div>
                    </div>
                </div>

                <!-- à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´à¸‡à¸²à¸™ -->
                <div class="hp-section" id="hr-submissions" data-keywords="submission à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´ à¸›à¸à¸´à¹€à¸ªà¸˜ à¸£à¸¹à¸› photo pending badge">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">OK</div>
                        <span class="hp-section-title-text">à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´à¸‡à¸²à¸™ (Photo Submission)</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">à¸«à¸™à¹‰à¸²à¸™à¸µà¹‰à¹à¸ªà¸”à¸‡à¸£à¸¹à¸›à¸ à¸²à¸žà¸«à¸¥à¸±à¸à¸à¸²à¸™à¸—à¸µà¹ˆà¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸ªà¹ˆà¸‡à¸¡à¸² à¸£à¸­à¸à¸²à¸£à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´ à¸•à¸±à¸§à¹€à¸¥à¸‚à¸ªà¸µà¹à¸”à¸‡à¸šà¸™à¹€à¸¡à¸™à¸¹à¸„à¸·à¸­à¸ˆà¸³à¸™à¸§à¸™à¸—à¸µà¹ˆà¸£à¸­à¸­à¸¢à¸¹à¹ˆ</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸„à¸¥à¸´à¸à¸”à¸¹à¸£à¸¹à¸›à¸ à¸²à¸žà¸—à¸µà¹ˆà¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸ªà¹ˆà¸‡</span></li>
                            <li><span class="hp-step-num">2</span><span>à¸žà¸´à¸ˆà¸²à¸£à¸“à¸²à¸§à¹ˆà¸²à¸–à¸¹à¸à¸•à¹‰à¸­à¸‡à¸•à¸²à¸¡à¹€à¸‡à¸·à¹ˆà¸­à¸™à¹„à¸‚à¸ à¸²à¸£à¸à¸´à¸ˆà¸«à¸£à¸·à¸­à¹„à¸¡à¹ˆ</span></li>
                            <li><span class="hp-step-num">3</span><span>à¸à¸£à¸­à¸à¸«à¸¡à¸²à¸¢à¹€à¸«à¸•à¸¸ (à¹„à¸¡à¹ˆà¸šà¸±à¸‡à¸„à¸±à¸š) à¹€à¸žà¸·à¹ˆà¸­à¹à¸ˆà¹‰à¸‡à¸žà¸™à¸±à¸à¸‡à¸²à¸™</span></li>
                            <li><span class="hp-step-num">4</span><span>à¸à¸” <strong style="color:#7ec98a;">"à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´"</strong> â†’ à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¹„à¸”à¹‰ Token à¸—à¸±à¸™à¸—à¸µ <br>à¸«à¸£à¸·à¸­ <strong style="color:#e07a55;">"à¸›à¸à¸´à¹€à¸ªà¸˜"</strong> â†’ à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸ªà¹ˆà¸‡à¹ƒà¸«à¸¡à¹ˆà¹„à¸”à¹‰</span></li>
                        </ol>
                    </div>
                </div>

                <!-- à¸ˆà¸±à¸”à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥ -->
                <div class="hp-section" id="hr-rewards" data-keywords="reward à¸£à¸²à¸‡à¸§à¸±à¸¥ à¸ªà¸£à¹‰à¸²à¸‡ à¸„à¸¹à¸›à¸­à¸‡ stock toggle à¸¥à¸š">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">à¸ˆà¸±à¸”à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥</div>
                        <span class="hp-section-title-text">à¸ˆà¸±à¸”à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸à¸” <strong style="color:#dab937;">"+ à¹€à¸žà¸´à¹ˆà¸¡à¸£à¸²à¸‡à¸§à¸±à¸¥"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>à¸à¸£à¸­à¸ à¸Šà¸·à¹ˆà¸­, à¸£à¸²à¸¢à¸¥à¸°à¹€à¸­à¸µà¸¢à¸”, à¹„à¸­à¸„à¸­à¸™, à¸«à¸¡à¸§à¸”à¸«à¸¡à¸¹à¹ˆ, à¸£à¸²à¸„à¸² Token, à¸ªà¸•à¹‡à¸­à¸ (à¸§à¹ˆà¸²à¸‡ = à¹„à¸¡à¹ˆà¸ˆà¸³à¸à¸±à¸”)</span></li>
                            <li><span class="hp-step-num">3</span><span>(à¹„à¸¡à¹ˆà¸šà¸±à¸‡à¸„à¸±à¸š) à¸à¸£à¸­à¸ <strong style="color:#dab937;">à¸£à¸«à¸±à¸ªà¸„à¸¹à¸›à¸­à¸‡</strong> + à¸§à¸±à¸™à¸«à¸¡à¸”à¸­à¸²à¸¢à¸¸ â€” à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¹€à¸«à¹‡à¸™à¹„à¸”à¹‰à¸«à¸¥à¸±à¸‡ HR à¸ªà¹ˆà¸‡à¸¡à¸­à¸šà¹à¸¥à¹‰à¸§à¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™</span></li>
                            <li><span class="hp-step-num">4</span><span>à¸à¸” <strong>"à¸šà¸±à¸™à¸—à¸¶à¸"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>à¹à¸™à¸°à¸™à¸³à¹ƒà¸«à¹‰à¹ƒà¸Šà¹‰à¸›à¸¸à¹ˆà¸¡ <strong>Toggle à¸›à¸´à¸”</strong> à¸£à¸²à¸‡à¸§à¸±à¸¥à¹à¸—à¸™à¸à¸²à¸£à¸¥à¸š à¹€à¸žà¸·à¹ˆà¸­à¹„à¸¡à¹ˆà¹ƒà¸«à¹‰à¸›à¸£à¸°à¸§à¸±à¸•à¸´à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸«à¸²à¸¢<br>à¸¥à¸šà¹„à¸”à¹‰à¹€à¸‰à¸žà¸²à¸°à¸£à¸²à¸‡à¸§à¸±à¸¥à¸—à¸µà¹ˆà¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸¡à¸µà¹ƒà¸„à¸£à¹à¸¥à¸à¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™</span>
                        </div>
                    </div>
                </div>

                <!-- à¸„à¸³à¸‚à¸­à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥ -->
                <div class="hp-section" id="hr-redemptions" data-keywords="redemption à¹à¸¥à¸ à¸ªà¹ˆà¸‡à¸¡à¸­à¸š à¸¢à¸à¹€à¸¥à¸´à¸ à¸„à¸·à¸™ token fulfill cancel">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">RQ</div>
                        <span class="hp-section-title-text">à¸„à¸³à¸‚à¸­à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">à¹€à¸¡à¸·à¹ˆà¸­à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸à¸”à¹à¸¥à¸à¸£à¸²à¸‡à¸§à¸±à¸¥ Token à¸ˆà¸°à¸–à¸¹à¸à¸«à¸±à¸à¸—à¸±à¸™à¸—à¸µ à¹à¸•à¹ˆà¸•à¹‰à¸­à¸‡à¸£à¸­ HR à¸¢à¸·à¸™à¸¢à¸±à¸™à¸à¸²à¸£à¸ªà¹ˆà¸‡à¸¡à¸­à¸šà¸ˆà¸£à¸´à¸‡</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>à¸”à¸¹à¸£à¸²à¸¢à¸à¸²à¸£à¸—à¸µà¹ˆà¸£à¸­à¸­à¸¢à¸¹à¹ˆ à¹à¸•à¹ˆà¸¥à¸°à¸£à¸²à¸¢à¸à¸²à¸£à¹à¸ªà¸”à¸‡à¸Šà¸·à¹ˆà¸­à¸žà¸™à¸±à¸à¸‡à¸²à¸™, à¸£à¸²à¸‡à¸§à¸±à¸¥, Token à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰</span></li>
                            <li><span class="hp-step-num">2</span><span>à¸ˆà¸±à¸”à¹€à¸•à¸£à¸µà¸¢à¸¡à¸£à¸²à¸‡à¸§à¸±à¸¥à¹ƒà¸«à¹‰à¸žà¸£à¹‰à¸­à¸¡</span></li>
                            <li><span class="hp-step-num">3</span><span>à¸à¸” <strong style="color:#7ec98a;">"à¸ªà¹ˆà¸‡à¸¡à¸­à¸š"</strong> â†’ à¹ƒà¸ªà¹ˆà¸«à¸¡à¸²à¸¢à¹€à¸«à¸•à¸¸ à¹€à¸Šà¹ˆà¸™ "à¸ªà¹ˆà¸‡à¸—à¸²à¸‡à¸­à¸µà¹€à¸¡à¸¥à¹à¸¥à¹‰à¸§" â†’ à¸à¸” "à¸¢à¸·à¸™à¸¢à¸±à¸™"</span></li>
                            <li><span class="hp-step-num">4</span><span>à¸ªà¸–à¸²à¸™à¸°à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¹€à¸›à¹‡à¸™ <strong style="color:#7ec98a;">"à¸ªà¸³à¹€à¸£à¹‡à¸ˆ"</strong> à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¹€à¸«à¹‡à¸™à¸£à¸«à¸±à¸ªà¸„à¸¹à¸›à¸­à¸‡ (à¸–à¹‰à¸²à¸¡à¸µ)</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>à¸à¸” <strong>"à¸¢à¸à¹€à¸¥à¸´à¸"</strong> â†’ Token à¸ˆà¸°à¸–à¸¹à¸ <strong>à¸„à¸·à¸™à¹ƒà¸«à¹‰à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´</strong></span>
                        </div>
                    </div>
                </div>

                <!-- à¸ˆà¸±à¸”à¸à¸²à¸£à¸žà¸™à¸±à¸à¸‡à¸²à¸™ -->
                <div class="hp-section" id="hr-employees" data-keywords="employee à¸žà¸™à¸±à¸à¸‡à¸²à¸™ token à¸›à¸£à¸±à¸š reset password role à¸šà¸±à¸à¸Šà¸µ à¸›à¸´à¸”">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.6rem;">EMP</div>
                        <span class="hp-section-title-text">à¸ˆà¸±à¸”à¸à¸²à¸£à¸žà¸™à¸±à¸à¸‡à¸²à¸™</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span><strong style="color:#eeebe1;">à¸„à¹‰à¸™à¸«à¸²</strong> à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸”à¹‰à¸§à¸¢à¸Šà¸·à¹ˆà¸­à¸«à¸£à¸·à¸­à¸£à¸«à¸±à¸ª</span></li>
                            <li><span class="hp-step-num">2</span><span><strong style="color:#eeebe1;">à¹€à¸›à¸´à¸”/à¸›à¸´à¸”à¸šà¸±à¸à¸Šà¸µ</strong> â€” à¸šà¸±à¸à¸Šà¸µà¸—à¸µà¹ˆà¸›à¸´à¸”à¸ˆà¸° Login à¹„à¸¡à¹ˆà¹„à¸”à¹‰</span></li>
                            <li><span class="hp-step-num">3</span><span><strong style="color:#dab937;">à¸›à¸£à¸±à¸š Token</strong> â€” à¹€à¸žà¸´à¹ˆà¸¡à¸«à¸£à¸·à¸­à¸«à¸±à¸à¹‚à¸”à¸¢à¸•à¸£à¸‡ à¸žà¸£à¹‰à¸­à¸¡à¸£à¸°à¸šà¸¸à¹€à¸«à¸•à¸¸à¸œà¸¥ (à¸šà¸±à¸™à¸—à¸¶à¸à¹ƒà¸™à¸›à¸£à¸°à¸§à¸±à¸•à¸´)</span></li>
                            <li><span class="hp-step-num">4</span><span><strong style="color:#eeebe1;">à¸£à¸µà¹€à¸‹à¹‡à¸•à¸£à¸«à¸±à¸ªà¸œà¹ˆà¸²à¸™</strong> à¹ƒà¸«à¹‰à¸žà¸™à¸±à¸à¸‡à¸²à¸™</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--warn">
                            <span>à¹€à¸‰à¸žà¸²à¸° <strong>Admin</strong> à¹€à¸—à¹ˆà¸²à¸™à¸±à¹‰à¸™à¸—à¸µà¹ˆà¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™ Role à¸«à¸£à¸·à¸­à¸¥à¸šà¸šà¸±à¸à¸Šà¸µà¹„à¸”à¹‰ â€” à¹„à¸¡à¹ˆà¸ªà¸²à¸¡à¸²à¸£à¸–à¸à¸£à¸°à¸—à¸³à¸à¸±à¸šà¸šà¸±à¸à¸Šà¸µà¸•à¸±à¸§à¹€à¸­à¸‡à¹„à¸”à¹‰</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ HR -->
                <div class="hp-section" id="hr-faq" data-keywords="faq à¸„à¸³à¸–à¸²à¸¡ hr admin token à¸„à¸·à¸™ toggle à¸¥à¸š">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" style="font-size:0.65rem;">FAQ</div>
                        <span class="hp-section-title-text">à¸„à¸³à¸–à¸²à¸¡à¸—à¸µà¹ˆà¸žà¸šà¸šà¹ˆà¸­à¸¢</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¹„à¸”à¹‰ Token à¹„à¸›à¹à¸¥à¹‰à¸§ à¸•à¹‰à¸­à¸‡à¸à¸²à¸£à¸–à¸­à¸™à¸„à¸·à¸™ à¸—à¸³à¹„à¸”à¹‰à¹„à¸«à¸¡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¹„à¸”à¹‰ à¹„à¸›à¸—à¸µà¹ˆà¸«à¸™à¹‰à¸²à¸ˆà¸±à¸”à¸à¸²à¸£à¸žà¸™à¸±à¸à¸‡à¸²à¸™ â†’ à¸›à¸£à¸±à¸š Token â†’ à¹ƒà¸ªà¹ˆà¸ˆà¸³à¸™à¸§à¸™à¹€à¸›à¹‡à¸™à¸¥à¸š à¹€à¸Šà¹ˆà¸™ <strong>-50</strong> à¸žà¸£à¹‰à¸­à¸¡à¸£à¸°à¸šà¸¸à¹€à¸«à¸•à¸¸à¸œà¸¥ à¸£à¸°à¸šà¸šà¸ˆà¸°à¸šà¸±à¸™à¸—à¸¶à¸à¹ƒà¸™à¸›à¸£à¸°à¸§à¸±à¸•à¸´à¸žà¸™à¸±à¸à¸‡à¸²à¸™</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¸•à¹‰à¸­à¸‡à¸à¸²à¸£à¸‹à¹ˆà¸­à¸™à¸£à¸²à¸‡à¸§à¸±à¸¥à¸Šà¸±à¹ˆà¸§à¸„à¸£à¸²à¸§ à¹„à¸¡à¹ˆà¸•à¹‰à¸­à¸‡à¸à¸²à¸£à¸¥à¸š
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¹ƒà¸Šà¹‰à¸›à¸¸à¹ˆà¸¡ <strong>Toggle</strong> à¹€à¸žà¸·à¹ˆà¸­à¸›à¸´à¸”à¸£à¸²à¸‡à¸§à¸±à¸¥ à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸ˆà¸°à¹„à¸¡à¹ˆà¹€à¸«à¹‡à¸™à¹ƒà¸™à¸£à¹‰à¸²à¸™ à¹€à¸›à¸´à¸”à¸à¸¥à¸±à¸šà¹„à¸”à¹‰à¸—à¸¸à¸à¹€à¸¡à¸·à¹ˆà¸­à¹‚à¸”à¸¢à¸à¸” Toggle à¸­à¸µà¸à¸„à¸£à¸±à¹‰à¸‡</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¸ à¸²à¸£à¸à¸´à¸ˆà¸«à¸¡à¸”à¸§à¸±à¸™à¸ªà¸´à¹‰à¸™à¸ªà¸¸à¸”à¹à¸¥à¹‰à¸§ à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸¢à¸±à¸‡à¹€à¸«à¹‡à¸™à¸­à¸¢à¸¹à¹ˆà¹„à¸«à¸¡?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¹„à¸¡à¹ˆ à¸£à¸°à¸šà¸šà¸‹à¹ˆà¸­à¸™à¸ à¸²à¸£à¸à¸´à¸ˆà¸—à¸µà¹ˆà¹€à¸¥à¸¢à¸§à¸±à¸™à¸ªà¸´à¹‰à¸™à¸ªà¸¸à¸”à¹ƒà¸«à¹‰à¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´ à¹à¸•à¹ˆà¸¢à¸±à¸‡à¸­à¸¢à¸¹à¹ˆà¹ƒà¸™à¸«à¸™à¹‰à¸² HR à¸ªà¸²à¸¡à¸²à¸£à¸–à¹à¸à¹‰à¹„à¸‚à¸§à¸±à¸™à¸«à¸£à¸·à¸­à¸¥à¸šà¹„à¸”à¹‰</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    à¹€à¸žà¸´à¹ˆà¸¡à¸ à¸²à¸£à¸à¸´à¸ˆ Quiz à¹à¸¥à¹‰à¸§à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸¡à¸µà¸„à¸³à¸–à¸²à¸¡ à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸ˆà¸°à¹€à¸«à¹‡à¸™à¸­à¸°à¹„à¸£?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">à¸ à¸²à¸£à¸à¸´à¸ˆà¸ˆà¸°à¹à¸ªà¸”à¸‡à¹ƒà¸™à¸£à¸²à¸¢à¸à¸²à¸£ à¹à¸•à¹ˆà¹€à¸¡à¸·à¹ˆà¸­à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸à¸”à¸—à¸³à¸ˆà¸°à¸‚à¸¶à¹‰à¸™à¸§à¹ˆà¸² "à¸ à¸²à¸£à¸à¸´à¸ˆà¸™à¸µà¹‰à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸¡à¸µà¸„à¸³à¸–à¸²à¸¡" à¸•à¹‰à¸­à¸‡à¹€à¸žà¸´à¹ˆà¸¡à¸„à¸³à¸–à¸²à¸¡à¸à¹ˆà¸­à¸™à¸ˆà¸¶à¸‡à¸ˆà¸°à¸—à¸³à¹„à¸”à¹‰</div></div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /content hr -->
        </div><!-- /layout hr -->
        </div><!-- /panel-hr -->

        <div class="hp-no-result" id="hp-no-result">
            à¹„à¸¡à¹ˆà¸žà¸šà¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸—à¸µà¹ˆà¸„à¹‰à¸™à¸«à¸² à¸¥à¸­à¸‡à¹ƒà¸Šà¹‰à¸„à¸³à¸­à¸·à¹ˆà¸™
        </div>

    </div><!-- /inner -->
</div><!-- /wrap -->

<script>
// â”€â”€ Tab pill positioning â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function positionPill(activeBtn) {
    var pill = document.getElementById('hp-tab-pill');
    if (!pill || !activeBtn) return;
    var track = document.getElementById('hp-tabs-track');
    var trackRect = track.getBoundingClientRect();
    var btnRect   = activeBtn.getBoundingClientRect();
    pill.style.width  = btnRect.width + 'px';
    pill.style.transform = 'translateX(' + (btnRect.left - trackRect.left - 4) + 'px)';
    pill.style.opacity = '1';
}

// â”€â”€ Tab switcher â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function switchTab(tab, btn) {
    document.querySelectorAll('.hp-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    positionPill(btn);
    document.getElementById('panel-employee').style.display = tab === 'employee' ? '' : 'none';
    var hrPanel = document.getElementById('panel-hr');
    if (hrPanel) hrPanel.style.display = tab === 'hr' ? '' : 'none';
    document.getElementById('hp-search').value = '';
    showAllSections();
}

// â”€â”€ Render token icons as SVG â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function hpIconSvg(key) {
    var map = {
        LGN: '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
        DS:  '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20"/><rect x="6" y="11" width="3" height="7"/><rect x="11" y="7" width="3" height="11"/><rect x="16" y="4" width="3" height="14"/></svg>',
        Q:   '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>',
        R:   '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13"/><path d="M3 13h18"/><path d="M8 8c-1.2 0-2-0.8-2-2s1-2 2.2-1.4C9.6 5.3 10.6 7 12 8"/><path d="M16 8c1.2 0 2-0.8 2-2s-1-2-2.2-1.4C14.4 5.3 13.4 7 12 8"/></svg>',
        H:   '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
        P:   '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>',
        STR: '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.8 17.8l-1.9-3.8H9.1L13.8 24l4.7-10h-2.8" fill="currentColor" stroke="none"/><path d="M9.3 8.2l2.6 5.1h3.9L9.3 0 2.9 13.3h3.8" fill="currentColor" stroke="none"/></svg>',
        FAQ: '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.7-2.5 2.1-2.5 4"/><circle cx="12" cy="17.3" r="0.9" fill="currentColor" stroke="none"/></svg>',
        OK:  '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.6 2.6L16 9.8"/></svg>',
        RQ:  '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h13"/><path d="M13 3l4 4-4 4"/><path d="M21 17H8"/><path d="M11 13l-4 4 4 4"/></svg>',
        EMP: '<svg class="hp-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3.5 20a6 6 0 0 1 11 0"/><path d="M13.8 20a4.8 4.8 0 0 1 6.2-3.3"/></svg>'
    };
    return map[key] || '';
}

function hpNormalizeIconKey(raw) {
    var text = (raw || '').trim();
    if (text === 'à¸Šà¹ˆà¸§à¸¢à¹€à¸«à¸¥à¸·à¸­') return 'FAQ';
    if (text === 'à¸£à¸²à¸‡à¸§à¸±à¸¥' || text === 'à¸ˆà¸±à¸”à¸à¸²à¸£à¸£à¸²à¸‡à¸§à¸±à¸¥') return 'R';
    return text.toUpperCase();
}

function hpRenderTokenIcons() {
    document.querySelectorAll('.hp-sl-icon, .hp-section-icon').forEach(function (el) {
        if (el.querySelector('svg')) return;
        var key = hpNormalizeIconKey(el.textContent);
        var svg = hpIconSvg(key);
        if (!svg) return;
        el.innerHTML = svg;

        if (key === 'STR') {
            el.style.color = '#FC4C02';
            el.style.background = 'rgba(252,76,2,0.1)';
        }
    });
}

// Init pill on load
window.addEventListener('load', function() {
    hpRenderTokenIcons();
    var activeTab = document.querySelector('.hp-tab.active');
    if (activeTab) positionPill(activeTab);
});
window.addEventListener('resize', function() {
    var activeTab = document.querySelector('.hp-tab.active');
    if (activeTab) positionPill(activeTab);
});

// â”€â”€ Accordion toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleSection(header) {
    var section = header.closest('.hp-section');
    section.classList.toggle('open');
}

// â”€â”€ FAQ toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleFaq(qEl) {
    var item = qEl.closest('.hp-faq-item');
    item.classList.toggle('open');
}

// â”€â”€ Sidebar scroll â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function scrollToSection(id, btn) {
    var sidebar = btn.closest('.hp-sidebar');
    sidebar.querySelectorAll('.hp-sidebar-link').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    var el = document.getElementById(id);
    if (!el) return;
    if (!el.classList.contains('open')) el.classList.add('open');
    setTimeout(function(){
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 60);
}

// â”€â”€ Search â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function searchHelp(q) {
    q = q.trim().toLowerCase();
    var hrPanel = document.getElementById('panel-hr');
    var activePanel = hrPanel && hrPanel.style.display !== 'none'
        ? hrPanel
        : document.getElementById('panel-employee');
    var sections = activePanel.querySelectorAll('.hp-section');
    var found = 0;
    sections.forEach(function(sec) {
        var keywords = (sec.dataset.keywords || '').toLowerCase();
        var text = sec.innerText.toLowerCase();
        var match = !q || keywords.includes(q) || text.includes(q);
        sec.style.display = match ? '' : 'none';
        if (match) { found++; if (q) sec.classList.add('open'); }
    });
    var noResult = document.getElementById('hp-no-result');
    noResult.style.display = found === 0 && q ? 'block' : 'none';
}

function showAllSections() {
    document.querySelectorAll('.hp-section').forEach(function(s){ s.style.display = ''; });
    document.getElementById('hp-no-result').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


