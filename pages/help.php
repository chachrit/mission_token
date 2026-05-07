<?php
/**
 * pages/help.php
 * User Manual — คู่มือการใช้งาน Mission Token
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle  = 'คู่มือการใช้งาน';
$activePage = 'help';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
body:has(.hp-wrap) { background-color: #091113; }

/* ── Keyframes ── */
@keyframes hp-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
@keyframes hp-floatA {
    0%,100% { transform: translateY(0) scale(1); }
    50%      { transform: translateY(-18px) scale(1.04); }
}
@keyframes hp-floatB {
    0%,100% { transform: translateY(0) scale(1); }
    50%      { transform: translateY(14px) scale(0.97); }
}
@keyframes hp-fadeSlideUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes hp-glowPulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(218,185,55,0); }
    50%      { box-shadow: 0 0 14px 3px rgba(218,185,55,0.18); }
}
@keyframes hp-dotGrid {
    0%   { opacity: 0.18; }
    50%  { opacity: 0.32; }
    100% { opacity: 0.18; }
}
@keyframes hp-tabSlide {
    from { transform: scaleX(0.6); opacity: 0; }
    to   { transform: scaleX(1);   opacity: 1; }
}

/* ── Layout ── */
.hp-wrap  { min-height: 100vh; position: relative; overflow-x: hidden; }
.hp-inner { max-width: 1080px; margin: 0 auto; padding: 2rem 1.25rem 7rem; position: relative; z-index: 1; }

/* dot-grid backdrop */
.hp-wrap::before {
    content: '';
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image: radial-gradient(circle, rgba(218,185,55,0.07) 1px, transparent 1px);
    background-size: 32px 32px;
    animation: hp-dotGrid 6s ease-in-out infinite;
}

/* ── Hero ── */
.hp-hero {
    text-align: center;
    padding: 2.8rem 1rem 2.2rem;
    margin-bottom: 1.8rem;
    animation: hp-fadeSlideUp 0.7s ease both;
}
.hp-hero-eyebrow {
    font-size: 0.6rem; font-weight: 800; letter-spacing: 0.25em;
    text-transform: uppercase; color: rgba(218,185,55,0.55);
    margin: 0 0 0.7rem;
    display: inline-flex; align-items: center; gap: 0.5rem;
}
.hp-hero-eyebrow::before,
.hp-hero-eyebrow::after {
    content: ''; display: inline-block;
    width: 28px; height: 1px; background: rgba(218,185,55,0.3);
}
.hp-hero-title {
    font-size: clamp(1.8rem, 5vw, 2.8rem); font-weight: 900;
    background: linear-gradient(100deg, #f8e769 0%, #dab937 40%, #f8e769 60%, #c9a830 100%);
    background-size: 800px auto;
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: hp-shimmer 3.5s linear infinite;
    margin: 0 0 0.8rem; line-height: 1.15;
}
.hp-hero-sub {
    font-size: 0.9rem; color: #6b6e77; margin: 0 0 1.5rem;
}
/* Hero stat chips */
.hp-hero-stats {
    display: flex; gap: 0.6rem; justify-content: center; flex-wrap: wrap;
}
.hp-hero-stat {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.35rem 0.85rem; border-radius: 99px;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
    font-size: 0.75rem; font-weight: 600; color: #6b6e77;
    transition: all 0.2s;
}
.hp-hero-stat:hover { border-color: rgba(218,185,55,0.3); color: #dab937; }
.hp-hero-stat-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(218,185,55,0.6);
    box-shadow: 0 0 6px rgba(218,185,55,0.5);
}

/* ── Tab switcher ── */
.hp-tabs-wrap {
    display: flex; justify-content: center;
    margin-bottom: 2rem;
    animation: hp-fadeSlideUp 0.7s 0.1s ease both;
}
.hp-tabs-track {
    display: flex;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px; padding: 4px; gap: 0; position: relative;
}
.hp-tab {
    padding: 0.55rem 1.6rem; border-radius: 10px; font-size: 0.875rem;
    font-weight: 700; cursor: pointer; border: none;
    color: #6b6e77; background: transparent;
    transition: color 0.2s; user-select: none;
    position: relative; z-index: 1;
    display: flex; align-items: center; gap: 0.4rem;
}
.hp-tab:hover { color: #eeebe1; }
.hp-tab.active { color: #dab937; }
/* sliding pill behind active tab */
.hp-tab-pill {
    position: absolute; top: 4px; bottom: 4px; left: 4px;
    border-radius: 8px;
    background: rgba(218,185,55,0.12);
    border: 1px solid rgba(218,185,55,0.3);
    transition: transform 0.28s cubic-bezier(0.34,1.56,0.64,1), width 0.28s ease, opacity 0.15s;
    transform-origin: left center;
    pointer-events: none;
    opacity: 0;
}

/* ── Search ── */
.hp-search-outer {
    margin-bottom: 1.75rem;
    animation: hp-fadeSlideUp 0.7s 0.15s ease both;
}
.hp-search-wrap { position: relative; }
.hp-search-input {
    width: 100%; padding: 0.75rem 1rem 0.75rem 2.65rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
    border-radius: 12px; color: #eeebe1; font-size: 0.875rem; outline: none;
    font-family: 'Prompt', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.hp-search-input::placeholder { color: #4a4e57; }
.hp-search-input:focus {
    border-color: rgba(218,185,55,0.45);
    box-shadow: 0 0 0 3px rgba(218,185,55,0.08);
}
.hp-search-icon {
    position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%);
    color: #4a4e57; pointer-events: none; transition: color 0.2s;
}
.hp-search-input:focus ~ .hp-search-icon { color: #dab937; }

/* ── Two-col layout ── */
.hp-layout { display: grid; grid-template-columns: 220px 1fr; gap: 1.5rem; align-items: start; }
@media (max-width: 768px) { .hp-layout { grid-template-columns: 1fr; } }

/* ── Sidebar ── */
.hp-sidebar {
    position: sticky; top: 80px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px; padding: 1rem;
    backdrop-filter: blur(8px);
}
.hp-sidebar-title {
    font-size: 0.58rem; font-weight: 800; letter-spacing: 0.16em;
    text-transform: uppercase; color: rgba(218,185,55,0.5);
    margin: 0 0 0.7rem; padding: 0 0.25rem;
}
.hp-sidebar-link {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.6rem; border-radius: 8px;
    font-size: 0.8rem; color: #6b6e77;
    text-decoration: none; cursor: pointer;
    transition: all 0.15s; border: none; background: none; width: 100%; text-align: left;
    position: relative;
}
.hp-sidebar-link:hover { background: rgba(255,255,255,0.05); color: #eeebe1; }
.hp-sidebar-link.active {
    background: rgba(218,185,55,0.07); color: #dab937;
    box-shadow: inset 3px 0 0 rgba(218,185,55,0.5);
}
.hp-sidebar-link .hp-sl-icon {
    width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem;
    background: rgba(255,255,255,0.05);
    transition: background 0.15s, transform 0.15s;
}
.hp-sidebar-link:hover .hp-sl-icon { transform: scale(1.1); }
.hp-sidebar-link.active .hp-sl-icon { background: rgba(218,185,55,0.15); }

/* ── Accordion sections ── */
.hp-section {
    background: rgba(255,255,255,0.022);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px; overflow: hidden;
    margin-bottom: 0.85rem;
    transition: border-color 0.25s, box-shadow 0.25s, transform 0.2s;
    animation: hp-fadeSlideUp 0.5s ease both;
}
.hp-section:nth-child(1) { animation-delay: 0.05s; }
.hp-section:nth-child(2) { animation-delay: 0.10s; }
.hp-section:nth-child(3) { animation-delay: 0.15s; }
.hp-section:nth-child(4) { animation-delay: 0.20s; }
.hp-section:nth-child(5) { animation-delay: 0.25s; }
.hp-section:nth-child(6) { animation-delay: 0.30s; }
.hp-section:nth-child(7) { animation-delay: 0.35s; }
.hp-section:nth-child(8) { animation-delay: 0.40s; }

.hp-section:hover {
    border-color: rgba(255,255,255,0.13);
    transform: translateY(-1px);
}
.hp-section.open {
    border-color: rgba(218,185,55,0.3);
    box-shadow: 0 4px 24px rgba(218,185,55,0.07), 0 0 0 1px rgba(218,185,55,0.08);
    transform: none;
}

.hp-section-header {
    display: flex; align-items: center; gap: 0.9rem;
    padding: 1.1rem 1.35rem; cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}
.hp-section-header:hover { background: rgba(255,255,255,0.02); }

.hp-section-icon {
    width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    background: rgba(255,255,255,0.05);
    transition: background 0.2s, transform 0.2s;
}
.hp-section.open .hp-section-icon {
    background: rgba(218,185,55,0.14);
    animation: hp-glowPulse 2s ease-in-out 1;
}
.hp-section-header:hover .hp-section-icon { transform: scale(1.08); }

.hp-section-title-text {
    flex: 1; font-size: 0.96rem; font-weight: 700; color: #eeebe1;
    transition: color 0.2s;
}
.hp-section.open .hp-section-title-text { color: #f8e769; }

.hp-section-chevron {
    color: #4a4e57; transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), color 0.2s;
}
.hp-section.open .hp-section-chevron { transform: rotate(180deg); color: #dab937; }

/* animated body using max-height */
.hp-section-body {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.38s cubic-bezier(0.4,0,0.2,1), padding 0.3s ease;
    padding: 0 1.35rem;
    border-top: 1px solid transparent;
}
.hp-section.open .hp-section-body {
    max-height: 1800px;
    padding: 0 1.35rem 1.4rem;
    border-top-color: rgba(255,255,255,0.06);
}

/* ── Content styles ── */
.hp-text { font-size: 0.875rem; color: #9ca3a8; line-height: 1.75; margin: 0.75rem 0; }
.hp-text strong { color: #eeebe1; }
.hp-intro {
    font-size: 0.875rem; color: #9ca3a8; line-height: 1.75; margin: 0.75rem 0 1rem;
    padding: 0.85rem 1rem 0.85rem 1.1rem;
    background: rgba(255,255,255,0.03); border-radius: 10px;
    border-left: 3px solid rgba(218,185,55,0.3);
}

/* ── Steps with connector ── */
.hp-steps { list-style: none; padding: 0; margin: 0.5rem 0; position: relative; }
.hp-steps::before {
    content: '';
    position: absolute; left: 11px; top: 20px; bottom: 10px; width: 1px;
    background: linear-gradient(to bottom, rgba(218,185,55,0.25), rgba(218,185,55,0.05));
    pointer-events: none;
}
.hp-steps li {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.55rem 0.5rem; border-radius: 8px;
    font-size: 0.875rem; color: #9ca3a8; line-height: 1.65;
    transition: background 0.15s;
    position: relative;
}
.hp-steps li:hover { background: rgba(255,255,255,0.025); }
.hp-step-num {
    min-width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0;
    background: rgba(218,185,55,0.13); color: #dab937;
    font-size: 0.68rem; font-weight: 900; margin-top: 2px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(218,185,55,0.2);
    position: relative; z-index: 1;
    transition: background 0.15s, transform 0.15s;
}
.hp-steps li:hover .hp-step-num { background: rgba(218,185,55,0.22); transform: scale(1.1); }

/* ── Tip boxes ── */
.hp-tip {
    display: flex; gap: 0.7rem; align-items: flex-start;
    padding: 0.8rem 1rem; border-radius: 10px; margin: 0.65rem 0;
    font-size: 0.82rem; line-height: 1.65;
    transition: transform 0.15s;
}
.hp-tip:hover { transform: translateX(3px); }
.hp-tip--warn  { background: rgba(210,89,42,0.07);  border: 1px solid rgba(210,89,42,0.2);  color: #e07a55; border-left-width: 3px; }
.hp-tip--info  { background: rgba(79,139,152,0.07); border: 1px solid rgba(79,139,152,0.2); color: #5fa8ba; border-left-width: 3px; }
.hp-tip--gold  { background: rgba(218,185,55,0.07); border: 1px solid rgba(218,185,55,0.2); color: #dab937; border-left-width: 3px; }
.hp-tip--green { background: rgba(81,142,92,0.07);  border: 1px solid rgba(81,142,92,0.2);  color: #7ec98a; border-left-width: 3px; }

/* ── Type boxes ── */
.hp-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 0.85rem; margin: 0.75rem 0; }
.hp-type-box {
    border-radius: 14px; padding: 1.2rem 1.25rem;
    border: 1px solid rgba(255,255,255,0.07);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: default;
}
.hp-type-box:hover { transform: translateY(-4px); }
.hp-type-box--quiz   {
    background: linear-gradient(135deg, rgba(79,139,152,0.1) 0%, rgba(79,139,152,0.04) 100%);
    border-color: rgba(79,139,152,0.2);
}
.hp-type-box--quiz:hover   { box-shadow: 0 8px 24px rgba(79,139,152,0.15); }
.hp-type-box--photo  {
    background: linear-gradient(135deg, rgba(81,142,92,0.1) 0%, rgba(81,142,92,0.04) 100%);
    border-color: rgba(81,142,92,0.2);
}
.hp-type-box--photo:hover  { box-shadow: 0 8px 24px rgba(81,142,92,0.15); }
.hp-type-box--strava {
    background: linear-gradient(135deg, rgba(252,76,2,0.09) 0%, rgba(252,76,2,0.03) 100%);
    border-color: rgba(252,76,2,0.2);
}
.hp-type-box--strava:hover { box-shadow: 0 8px 24px rgba(252,76,2,0.15); }

.hp-type-label {
    font-size: 0.72rem; font-weight: 900; letter-spacing: 0.08em;
    text-transform: uppercase; margin: 0 0 0.55rem;
    display: flex; align-items: center; gap: 0.4rem;
}
.hp-type-box--quiz   .hp-type-label { color: #5fa8ba; }
.hp-type-box--photo  .hp-type-label { color: #7ec98a; }
.hp-type-box--strava .hp-type-label { color: #FC4C02; }
.hp-type-detail { font-size: 0.8rem; color: #8a8e97; line-height: 1.65; margin: 0; }

/* ── FAQ ── */
.hp-faq { margin: 0.5rem 0; }
.hp-faq-item {
    border: 1px solid rgba(255,255,255,0.06); border-radius: 10px;
    margin-bottom: 0.5rem; overflow: hidden;
    transition: border-color 0.2s;
}
.hp-faq-item.open { border-color: rgba(218,185,55,0.2); }
.hp-faq-q {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 0.85rem 1rem; cursor: pointer; user-select: none;
    font-size: 0.875rem; font-weight: 600; color: #eeebe1;
    transition: background 0.15s, color 0.2s;
}
.hp-faq-q:hover { background: rgba(255,255,255,0.03); }
.hp-faq-item.open .hp-faq-q { color: #f8e769; }
.hp-faq-q-icon { color: #4a4e57; transition: transform 0.28s cubic-bezier(0.34,1.56,0.64,1), color 0.2s; flex-shrink: 0; }
.hp-faq-item.open .hp-faq-q-icon { transform: rotate(180deg); color: #dab937; }
.hp-faq-a-wrap {
    overflow: hidden; max-height: 0;
    transition: max-height 0.32s ease, padding 0.28s ease;
    padding: 0 1rem;
    border-top: 1px solid transparent;
}
.hp-faq-item.open .hp-faq-a-wrap {
    max-height: 500px;
    padding: 0.75rem 1rem 1rem;
    border-top-color: rgba(255,255,255,0.05);
}
.hp-faq-a { font-size: 0.82rem; color: #9ca3a8; line-height: 1.7; }

/* ── Role table ── */
.hp-role-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin: 0.75rem 0; }
.hp-role-table th {
    padding: 0.55rem 0.85rem; text-align: left;
    background: rgba(255,255,255,0.04); color: #6b6e77;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.hp-role-table td {
    padding: 0.65rem 0.85rem; color: #9ca3a8;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: top; transition: background 0.15s;
}
.hp-role-table tr:hover td { background: rgba(255,255,255,0.02); }
.hp-role-table tr:last-child td { border-bottom: none; }
.hp-role-pill {
    display: inline-block; font-size: 0.65rem; font-weight: 700;
    padding: 3px 9px; border-radius: 99px;
}

/* ── No results ── */
.hp-no-result {
    text-align: center; padding: 3rem 1rem; color: #4a4e57;
    font-size: 0.875rem; display: none;
}
.hp-no-result-icon { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.4; }
</style>

<div class="hp-wrap">
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="hp-inner">

        <!-- ── Hero ── -->
        <div class="hp-hero">
            <p class="hp-hero-eyebrow">MISSION TOKEN</p>
            <h1 class="hp-hero-title">คู่มือการใช้งาน</h1>
            <p class="hp-hero-sub">วิธีใช้งานระบบสะสม Token ภายในองค์กร JOURNAL</p>
            <div class="hp-hero-stats">
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>8 หัวข้อ</span>
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>3 ประเภทภารกิจ</span>
                <span class="hp-hero-stat"><span class="hp-hero-stat-dot"></span>FAQ & คำตอบ</span>
                <span class="hp-hero-stat" style="border-color:rgba(79,139,152,0.25);color:#5fa8ba;"><span class="hp-hero-stat-dot" style="background:#5fa8ba;box-shadow:0 0 6px rgba(79,139,152,0.5);"></span>คู่มือ HR</span>
            </div>
        </div>

        <!-- ── Tab switcher ── -->
        <div class="hp-tabs-wrap">
            <div class="hp-tabs-track" id="hp-tabs-track">
                <div class="hp-tab-pill" id="hp-tab-pill"></div>
                <button class="hp-tab active" onclick="switchTab('employee', this)" id="tab-employee">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    สำหรับพนักงาน
                </button>
                <button class="hp-tab" onclick="switchTab('hr', this)" id="tab-hr">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    HR / Admin
                </button>
            </div>
        </div>

        <!-- ── Search ── -->
        <div class="hp-search-outer">
            <div class="hp-search-wrap">
                <input type="text" class="hp-search-input" id="hp-search"
                       placeholder="ค้นหาในคู่มือ เช่น Token, Quiz, Strava..." oninput="searchHelp(this.value)">
                <svg class="hp-search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
            </div>
        </div>

        <!-- ═══════════════════════════════════
             EMPLOYEE TAB
        ═══════════════════════════════════ -->
        <div id="panel-employee">
        <div class="hp-layout">

            <!-- Sidebar -->
            <div class="hp-sidebar hidden md:block">
                <p class="hp-sidebar-title">หัวข้อ</p>
                <button class="hp-sidebar-link active" onclick="scrollToSection('emp-login', this)">
                    <span class="hp-sl-icon">🔑</span> การเข้าสู่ระบบ
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-dashboard', this)">
                    <span class="hp-sl-icon">📊</span> Dashboard
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-challenges', this)">
                    <span class="hp-sl-icon">🎯</span> ภารกิจ
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-rewards', this)">
                    <span class="hp-sl-icon">🎁</span> ร้านรางวัล
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-history', this)">
                    <span class="hp-sl-icon">📋</span> ประวัติ
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-profile', this)">
                    <span class="hp-sl-icon">👤</span> โปรไฟล์
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-strava', this)">
                    <span class="hp-sl-icon" style="font-size:0.65rem; color:#FC4C02; background:rgba(252,76,2,0.1);">STR</span> Strava
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('emp-faq', this)">
                    <span class="hp-sl-icon">❓</span> FAQ
                </button>
            </div>

            <!-- Content -->
            <div>

                <!-- เข้าสู่ระบบ -->
                <div class="hp-section open" id="emp-login" data-keywords="login เข้าสู่ระบบ รหัสผ่าน portal">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🔑</div>
                        <span class="hp-section-title-text">การเข้าสู่ระบบ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">ใช้ <strong>รหัสพนักงาน</strong> และ <strong>รหัสผ่าน</strong> เดิมที่ใช้กับ <strong>JOURNAL Web Portal</strong> ได้เลย — ไม่ต้องสมัครใหม่</p>
                        <p class="hp-text" style="margin-bottom:0.4rem;"><strong style="color:#eeebe1;">🔓 ลืมหรือต้องการเปลี่ยนรหัสผ่าน?</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กดที่ <strong style="color:#eeebe1;">ชื่อของคุณ</strong> (มุมบนขวา) → เลือก <strong>"โปรไฟล์ของฉัน"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>เลื่อนลงหาส่วน <strong style="color:#eeebe1;">"เปลี่ยนรหัสผ่าน"</strong> กรอกรหัสเก่า + รหัสใหม่ (อย่างน้อย 8 ตัว) → กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>💡</span>
                            <span>Login ไม่ได้เลย? ให้ <strong>ติดต่อ HR หรือ IT</strong> เพื่อขอ Reset รหัสผ่าน</span>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>⏱</span>
                            <span>ระบบออกจากระบบอัตโนมัติหากไม่มีการใช้งานนาน <strong>2 ชั่วโมง</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="hp-section" id="emp-dashboard" data-keywords="dashboard หน้าแรก token streak อันดับ leaderboard">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">📊</div>
                        <span class="hp-section-title-text">หน้า Dashboard — ภาพรวมของคุณ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">หน้าแรกหลัง Login แสดงข้อมูลทั้งหมดของคุณในที่เดียว</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num" style="background:rgba(218,185,55,0.15);">🪙</span>
                                <span><strong style="color:#dab937;">กระเป๋า Token</strong> — Token คงเหลือ (ใช้แลกรางวัลได้), Token ที่ได้รับทั้งหมด, Token ที่ใช้ไปแล้ว</span>
                            </li>
                            <li>
                                <span class="hp-step-num">🏆</span>
                                <span><strong style="color:#eeebe1;">อันดับของคุณ</strong> — ลำดับใน Leaderboard นับจาก Token ที่ได้รับทั้งหมด (ไม่ใช่คงเหลือ)</span>
                            </li>
                            <li>
                                <span class="hp-step-num">🔥</span>
                                <span><strong style="color:#eeebe1;">Streak</strong> — จำนวนวันติดต่อกันที่ทำภารกิจผ่านแล้ว</span>
                            </li>
                            <li>
                                <span class="hp-step-num">📅</span>
                                <span><strong style="color:#eeebe1;">Token เดือนนี้</strong> — Token ที่ได้รับในเดือนปัจจุบัน</span>
                            </li>
                            <li>
                                <span class="hp-step-num">🎯</span>
                                <span><strong style="color:#eeebe1;">ภารกิจที่เปิดอยู่</strong> — แสดงภารกิจที่ทำได้พร้อม Token รางวัล</span>
                            </li>
                            <li>
                                <span class="hp-step-num">📝</span>
                                <span><strong style="color:#eeebe1;">กิจกรรมล่าสุด</strong> — 6 รายการล่าสุดที่คุณส่งงาน พร้อมสถานะ</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- ภารกิจ -->
                <div class="hp-section" id="emp-challenges" data-keywords="challenge ภารกิจ quiz photo strava การ์ด พลิก token รางวัล">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🎯</div>
                        <span class="hp-section-title-text">หน้าภารกิจ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">หน้านี้แสดงภารกิจทั้งหมดที่เปิดอยู่ แบ่งเป็น <strong>"ภารกิจรอคุณอยู่"</strong> (ยังทำได้) และ <strong>"เสร็จสิ้นแล้ว"</strong></p>

                        <p class="hp-text" style="margin-bottom:0.5rem;"><strong style="color:#eeebe1;">วิธีใช้งานการ์ด:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>เลื่อนดูการ์ดภารกิจ แต่ละใบแสดงชื่อและ Token รางวัล</span></li>
                            <li><span class="hp-step-num">2</span><span><strong style="color:#eeebe1;">วางเมาส์บนการ์ด</strong> (หรือแตะบนมือถือ) การ์ดจะ <strong style="color:#dab937;">พลิกแสดงรายละเอียด</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>ด้านหลังการ์ดแสดงเงื่อนไข, วันสิ้นสุด และปุ่มทำภารกิจ</span></li>
                        </ol>

                        <p class="hp-text" style="margin-top:1.25rem; margin-bottom:0.5rem;"><strong style="color:#eeebe1;">ภารกิจมี 3 ประเภท:</strong></p>
                        <div class="hp-type-grid">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">📝 Quiz — ตอบคำถาม</p>
                                <p class="hp-type-detail">
                                    ต้องตอบถูก <strong style="color:#eeebe1;">ทุกข้อ</strong> จึงได้ Token<br>
                                    ทำได้ <strong style="color:#e07a55;">1 ครั้งเท่านั้น</strong> ไม่มีโอกาสแก้ไข<br><br>
                                    กดปุ่ม "เริ่มทำ Quiz" แล้วตอบคำถาม → กด "ยืนยันคำตอบ"
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--photo">
                                <p class="hp-type-label">📷 Photo — ส่งรูปหลักฐาน</p>
                                <p class="hp-type-detail">
                                    HR ตรวจสอบรูปและอนุมัติ → ได้ Token<br>
                                    ถ้า HR ไม่ผ่าน <strong style="color:#7ec98a;">ส่งใหม่ได้</strong><br><br>
                                    ไฟล์: JPG/PNG/WebP ขนาดไม่เกิน 5MB
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label" style="color:#FC4C02;">🏃 Strava — ออกกำลังกาย</p>
                                <p class="hp-type-detail">
                                    ระบบตรวจกิจกรรมใน Strava ว่าผ่านเงื่อนไขไหม<br>
                                    ถ้าไม่พบ <strong style="color:#FC4C02;">ลองใหม่ได้</strong> หลังบันทึกกิจกรรมเพิ่ม<br><br>
                                    ต้องเชื่อมต่อ Strava ก่อน (ทำครั้งเดียว)
                                </p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>⚠️</span>
                            <span>Quiz ทำได้ <strong>ครั้งเดียว</strong> อ่านคำถามให้ครบก่อนกดยืนยันเสมอ</span>
                        </div>
                    </div>
                </div>

                <!-- ร้านรางวัล -->
                <div class="hp-section" id="emp-rewards" data-keywords="reward รางวัล แลก token shop คูปอง stock">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🎁</div>
                        <span class="hp-section-title-text">ร้านรางวัล</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">นำ Token ที่สะสมได้ไปแลกเป็นรางวัลต่างๆ ที่องค์กรจัดไว้ให้</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>ดูรายการรางวัล แต่ละรายการแสดงราคา Token</span></li>
                            <li><span class="hp-step-num">2</span><span>ตรวจสอบ <strong style="color:#dab937;">Token คงเหลือ</strong> ที่มุมบนขวาว่าเพียงพอ</span></li>
                            <li><span class="hp-step-num">3</span><span>กดปุ่ม <strong style="color:#dab937;">"แลกรางวัล"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>ยืนยันการแลก — Token จะถูกหักทันที</span></li>
                            <li><span class="hp-step-num">5</span><span>รอ HR จัดเตรียมและส่งมอบ สถานะจะเปลี่ยนจาก <em>"รอดำเนินการ"</em> เป็น <em>"สำเร็จ"</em></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>🏷️</span>
                            <span>รางวัลบางอย่างมี <strong>รหัสคูปอง</strong> — จะปรากฏในหน้าประวัติ หลัง HR ยืนยันการส่งมอบแล้วเท่านั้น</span>
                        </div>
                        <div class="hp-tip hp-tip--info">
                            <span>📦</span>
                            <span>รางวัลที่มีตัวเลขสต็อก — เมื่อหมดแล้วปุ่มแลกจะปิดอัตโนมัติ</span>
                        </div>
                    </div>
                </div>

                <!-- ประวัติ -->
                <div class="hp-section" id="emp-history" data-keywords="history ประวัติ transaction token รางวัล คูปอง">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">📋</div>
                        <span class="hp-section-title-text">หน้าประวัติ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">ดูประวัติทุกอย่างที่เกิดขึ้นกับบัญชี แบ่งเป็น 2 แท็บ</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">🪙</span>
                                <span><strong style="color:#dab937;">แท็บ Token</strong> — รายการรับ-จ่าย Token ทั้งหมด: ได้จาก Quiz, Photo, Strava / หักเมื่อแลกรางวัล / ปรับโดย HR</span>
                            </li>
                            <li>
                                <span class="hp-step-num">🎁</span>
                                <span><strong style="color:#eeebe1;">แท็บ รางวัล</strong> — รายการแลกรางวัลพร้อมสถานะ: <em>รอดำเนินการ</em> / <em>สำเร็จ</em> / <em>ยกเลิก</em><br>
                                <span style="color:#FC4C02;">🔓 รหัสคูปองจะปรากฏตรงนี้เมื่อรางวัลสำเร็จแล้ว</span></span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- โปรไฟล์ -->
                <div class="hp-section" id="emp-profile" data-keywords="profile โปรไฟล์ รูป รหัสผ่าน password อายุงาน">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">👤</div>
                        <span class="hp-section-title-text">หน้าโปรไฟล์</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">เข้าได้จาก เมนูชื่อของคุณ (มุมบนขวา) → "โปรไฟล์ของฉัน"</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">🖼</span>
                                <span><strong style="color:#eeebe1;">เปลี่ยนรูปโปรไฟล์</strong> — กดที่รูป เลือกไฟล์ภาพ (JPG/PNG/WebP ≤ 2MB) แล้วกด "อัปโหลด"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">🔒</span>
                                <span><strong style="color:#eeebe1;">เปลี่ยนรหัสผ่าน</strong> — กรอกรหัสปัจจุบัน + รหัสใหม่ (อย่างน้อย 8 ตัว) แล้วกด "บันทึก"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">📅</span>
                                <span><strong style="color:#eeebe1;">อายุงาน</strong> — แสดงระยะเวลาที่ทำงานในองค์กร</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Strava -->
                <div class="hp-section" id="emp-strava" data-keywords="strava วิ่ง ปั่น เดิน กิจกรรม ออกกำลังกาย เชื่อมต่อ">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon" style="background:rgba(252,76,2,0.1);">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#FC4C02">
                                <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                            </svg>
                        </div>
                        <span class="hp-section-title-text">Strava — กิจกรรมออกกำลังกาย</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">Strava คือแอปบันทึกการออกกำลังกาย ระบบใช้ Strava ตรวจสอบกิจกรรมสำหรับภารกิจประเภท Strava</p>

                        <p class="hp-text" style="margin-bottom:0.25rem;"><strong style="color:#eeebe1;">ขั้นตอนเชื่อมต่อ (ทำครั้งแรกครั้งเดียว):</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>ไปที่หน้า <strong style="color:#eeebe1;">โปรไฟล์</strong> → เลื่อนลงมาหาส่วน Strava</span></li>
                            <li><span class="hp-step-num">2</span><span>กดปุ่ม <strong style="color:#FC4C02;">"เชื่อมต่อ Strava"</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>ระบบจะพาไป Strava.com ให้กด <strong>"Authorize"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>ระบบจะพากลับมาอัตโนมัติ — เชื่อมต่อสำเร็จ</span></li>
                        </ol>

                        <p class="hp-text" style="margin-bottom:0.25rem; margin-top:1rem;"><strong style="color:#eeebe1;">วิธีทำภารกิจ Strava:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>บันทึกกิจกรรม (วิ่ง/ปั่น) ให้ครบตามเงื่อนไขก่อน</span></li>
                            <li><span class="hp-step-num">2</span><span>กลับมาที่หน้าภารกิจ กดการ์ดเพื่อดูด้านหลัง</span></li>
                            <li><span class="hp-step-num">3</span><span>กดปุ่ม <strong style="color:#FC4C02;">"ตรวจสอบกิจกรรม Strava"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>รอประมาณ <strong style="color:#eeebe1;">15–30 วินาที</strong> ห้ามปิดหน้าต่างระหว่างรอ</span></li>
                            <li><span class="hp-step-num">5</span><span>ผ่าน → ได้ Token ทันที | ไม่ผ่าน → ทำกิจกรรมเพิ่มแล้วลองใหม่</span></li>
                        </ol>

                        <div class="hp-tip hp-tip--warn">
                            <span>⚠️</span>
                            <span>กิจกรรมใน Strava ต้องตั้งเป็น <strong>"Everyone"</strong> หรือ <strong>"Followers"</strong> (ไม่ใช่ Private) ระบบจึงจะมองเห็น</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ พนักงาน -->
                <div class="hp-section" id="emp-faq" data-keywords="faq คำถาม quiz ซ้ำ token หมดอายุ strava ไม่พบ">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">❓</div>
                        <span class="hp-section-title-text">คำถามที่พบบ่อย</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    ทำ Quiz ตอบผิด สามารถลองใหม่ได้ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ไม่ได้ Quiz ทำได้ <strong style="color:#e07a55;">1 ครั้งต่อภารกิจเท่านั้น</strong> แม้ตอบผิดก็ไม่มีโอกาสแก้ไข อ่านทุกข้อก่อนกดยืนยัน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    ส่งรูปภาพแล้ว HR ไม่ผ่าน ทำอะไรได้บ้าง?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ส่งใหม่ได้เลย กลับไปที่หน้าภารกิจ กลับการ์ด จะมีปุ่ม "ส่งหลักฐานใหม่" ปรากฏขึ้น แก้ไขรูปแล้วส่งอีกครั้ง</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    Token ที่ใช้แลกรางวัลไปแล้วหายไปเลยไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ใช่ Token คงเหลือจะลดลง แต่ <strong style="color:#7ec98a;">Token ที่ได้รับทั้งหมดยังคงนับ</strong> อยู่ใน Leaderboard ไม่หายไปไหน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    Token มีวันหมดอายุไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ระบบปัจจุบันไม่มีวันหมดอายุ สอบถาม HR เพื่อยืนยันนโยบายขององค์กร</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    Strava ค้างนาน หรือบอกไม่พบกิจกรรม ทำอย่างไร?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">
                                    ค้างนาน (15–30 วิ) เป็นเรื่องปกติ รอและอย่าปิดหน้า<br><br>
                                    ถ้าบอกไม่พบกิจกรรม ตรวจสอบ:<br>
                                    1. กิจกรรมตั้งเป็น Private อยู่ไหม → เปลี่ยนเป็น Everyone/Followers<br>
                                    2. กิจกรรมอยู่ในช่วงวันที่ภารกิจไหม<br>
                                    3. ประเภทกิจกรรมตรงกับเงื่อนไขไหม (เช่น วิ่ง/ปั่น)<br>
                                    4. ระยะทาง/เวลาครบตามเงื่อนไขไหม
                                </div></div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /content -->
        </div><!-- /layout -->
        </div><!-- /panel-employee -->


        <!-- ═══════════════════════════════════
             HR / ADMIN TAB
        ═══════════════════════════════════ -->
        <div id="panel-hr" style="display:none;">
        <div class="hp-layout">

            <!-- Sidebar HR -->
            <div class="hp-sidebar hidden md:block">
                <p class="hp-sidebar-title">หัวข้อ</p>
                <button class="hp-sidebar-link active" onclick="scrollToSection('hr-roles', this)">
                    <span class="hp-sl-icon">👥</span> บทบาทในระบบ
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-challenges', this)">
                    <span class="hp-sl-icon">🎯</span> จัดการภารกิจ
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-submissions', this)">
                    <span class="hp-sl-icon">✅</span> อนุมัติงาน
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-rewards', this)">
                    <span class="hp-sl-icon">🎁</span> จัดการรางวัล
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-redemptions', this)">
                    <span class="hp-sl-icon">🔄</span> คำขอแลกรางวัล
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-employees', this)">
                    <span class="hp-sl-icon">👤</span> จัดการพนักงาน
                </button>
                <button class="hp-sidebar-link" onclick="scrollToSection('hr-faq', this)">
                    <span class="hp-sl-icon">❓</span> FAQ
                </button>
            </div>

            <!-- Content HR -->
            <div>

                <!-- บทบาท -->
                <div class="hp-section open" id="hr-roles" data-keywords="role บทบาท admin hr it employee สิทธิ์">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">👥</div>
                        <span class="hp-section-title-text">บทบาทในระบบ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <table class="hp-role-table">
                            <thead>
                                <tr>
                                    <th>บทบาท</th>
                                    <th>สิทธิ์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="hp-role-pill" style="background:rgba(218,185,55,0.12);color:#dab937;border:1px solid rgba(218,185,55,0.3);">Employee</span></td>
                                    <td>ทำภารกิจ, แลกรางวัล, ดูประวัติ — เข้าถึงเฉพาะหน้าพนักงาน</td>
                                </tr>
                                <tr>
                                    <td><span class="hp-role-pill" style="background:rgba(79,139,152,0.12);color:#5fa8ba;border:1px solid rgba(79,139,152,0.3);">HR</span></td>
                                    <td>สิทธิ์ Employee + อนุมัติงาน, จัดการรางวัล, จัดการพนักงาน</td>
                                </tr>
                                <tr>
                                    <td><span class="hp-role-pill" style="background:rgba(47,78,157,0.15);color:#7b9fd4;border:1px solid rgba(47,78,157,0.3);">IT</span></td>
                                    <td>สิทธิ์เหมือน HR (สำหรับทีม IT ดูแลระบบ)</td>
                                </tr>
                                <tr>
                                    <td><span class="hp-role-pill" style="background:rgba(98,48,122,0.15);color:#b87fd4;border:1px solid rgba(98,48,122,0.3);">Admin</span></td>
                                    <td>สิทธิ์สูงสุด — ทำได้ทุกอย่าง รวมถึงเปลี่ยนบทบาทผู้อื่น และลบบัญชี</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="hp-tip hp-tip--info">
                            <span>💡</span>
                            <span>HR/IT สามารถสลับระหว่าง "หน้าพนักงาน" และ "หน้าจัดการ" ได้จาก dropdown เมนูชื่อ</span>
                        </div>
                    </div>
                </div>

                <!-- จัดการภารกิจ -->
                <div class="hp-section" id="hr-challenges" data-keywords="challenge ภารกิจ สร้าง quiz strava photo toggle ลบ">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🎯</div>
                        <span class="hp-section-title-text">จัดการภารกิจ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text" style="margin-bottom:0.5rem;"><strong style="color:#eeebe1;">สร้างภารกิจใหม่:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กดปุ่ม <strong style="color:#dab937;">"+ เพิ่มภารกิจ"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>กรอก ชื่อ, รายละเอียด, ประเภท (Quiz/Photo/Strava), Token รางวัล, วันเริ่ม-สิ้นสุด</span></li>
                            <li><span class="hp-step-num">3</span><span>กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-type-grid" style="margin-top:1rem;">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">Quiz</p>
                                <p class="hp-type-detail">หลังบันทึกแล้ว กด "+ เพิ่มคำถาม"<br>กรอกคำถาม + ตัวเลือก A–D + เฉลย<br>เพิ่มได้หลายข้อ — ต้องถูกทุกข้อ</p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label" style="color:#FC4C02;">Strava</p>
                                <p class="hp-type-detail">ระบุ: ประเภทกิจกรรม (Run/Ride/Walk),<br>ระยะทางขั้นต่ำ (กม.), เวลาขั้นต่ำ (นาที),<br>ความสูงขั้นต่ำ (เมตร) — ไม่บังคับ</p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn" style="margin-top:1rem;">
                            <span>🗑️</span>
                            <span>การ <strong>ลบภารกิจ</strong> จะลบประวัติการส่งงานและ Token ที่เคยให้ไปด้วย — ถาวร ไม่สามารถกู้คืนได้ ใช้ปุ่ม Toggle ซ่อนแทนถ้าไม่แน่ใจ</span>
                        </div>
                    </div>
                </div>

                <!-- อนุมัติงาน -->
                <div class="hp-section" id="hr-submissions" data-keywords="submission อนุมัติ ปฏิเสธ รูป photo pending badge">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">✅</div>
                        <span class="hp-section-title-text">อนุมัติงาน (Photo Submission)</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">หน้านี้แสดงรูปภาพหลักฐานที่พนักงานส่งมา รอการอนุมัติ ตัวเลขสีแดงบนเมนูคือจำนวนที่รออยู่</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>คลิกดูรูปภาพที่พนักงานส่ง</span></li>
                            <li><span class="hp-step-num">2</span><span>พิจารณาว่าถูกต้องตามเงื่อนไขภารกิจหรือไม่</span></li>
                            <li><span class="hp-step-num">3</span><span>กรอกหมายเหตุ (ไม่บังคับ) เพื่อแจ้งพนักงาน</span></li>
                            <li><span class="hp-step-num">4</span><span>กด <strong style="color:#7ec98a;">"อนุมัติ"</strong> → พนักงานได้ Token ทันที <br>หรือ <strong style="color:#e07a55;">"ปฏิเสธ"</strong> → พนักงานส่งใหม่ได้</span></li>
                        </ol>
                    </div>
                </div>

                <!-- จัดการรางวัล -->
                <div class="hp-section" id="hr-rewards" data-keywords="reward รางวัล สร้าง คูปอง stock toggle ลบ">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🎁</div>
                        <span class="hp-section-title-text">จัดการรางวัล</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กด <strong style="color:#dab937;">"+ เพิ่มรางวัล"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>กรอก ชื่อ, รายละเอียด, อีโมจิ 🎁, หมวดหมู่, ราคา Token, สต็อก (ว่าง = ไม่จำกัด)</span></li>
                            <li><span class="hp-step-num">3</span><span>(ไม่บังคับ) กรอก <strong style="color:#dab937;">รหัสคูปอง</strong> + วันหมดอายุ — พนักงานเห็นได้หลัง HR ส่งมอบแล้วเท่านั้น</span></li>
                            <li><span class="hp-step-num">4</span><span>กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>💡</span>
                            <span>แนะนำให้ใช้ปุ่ม <strong>Toggle ปิด</strong> รางวัลแทนการลบ เพื่อไม่ให้ประวัติพนักงานหาย<br>ลบได้เฉพาะรางวัลที่ยังไม่มีใครแลกเท่านั้น</span>
                        </div>
                    </div>
                </div>

                <!-- คำขอแลกรางวัล -->
                <div class="hp-section" id="hr-redemptions" data-keywords="redemption แลก ส่งมอบ ยกเลิก คืน token fulfill cancel">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">🔄</div>
                        <span class="hp-section-title-text">คำขอแลกรางวัล</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">เมื่อพนักงานกดแลกรางวัล Token จะถูกหักทันที แต่ต้องรอ HR ยืนยันการส่งมอบจริง</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>ดูรายการที่รออยู่ แต่ละรายการแสดงชื่อพนักงาน, รางวัล, Token ที่ใช้</span></li>
                            <li><span class="hp-step-num">2</span><span>จัดเตรียมรางวัลให้พร้อม</span></li>
                            <li><span class="hp-step-num">3</span><span>กด <strong style="color:#7ec98a;">"ส่งมอบ"</strong> → ใส่หมายเหตุ เช่น "ส่งทางอีเมลแล้ว" → กด "ยืนยัน"</span></li>
                            <li><span class="hp-step-num">4</span><span>สถานะเปลี่ยนเป็น <strong style="color:#7ec98a;">"สำเร็จ"</strong> พนักงานเห็นรหัสคูปอง (ถ้ามี)</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>↩️</span>
                            <span>กด <strong>"ยกเลิก"</strong> → Token จะถูก <strong>คืนให้พนักงานอัตโนมัติ</strong></span>
                        </div>
                    </div>
                </div>

                <!-- จัดการพนักงาน -->
                <div class="hp-section" id="hr-employees" data-keywords="employee พนักงาน token ปรับ reset password role บัญชี ปิด">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">👤</div>
                        <span class="hp-section-title-text">จัดการพนักงาน</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">🔍</span><span><strong style="color:#eeebe1;">ค้นหา</strong> พนักงานด้วยชื่อหรือรหัส</span></li>
                            <li><span class="hp-step-num">🔛</span><span><strong style="color:#eeebe1;">เปิด/ปิดบัญชี</strong> — บัญชีที่ปิดจะ Login ไม่ได้</span></li>
                            <li><span class="hp-step-num">🪙</span><span><strong style="color:#dab937;">ปรับ Token</strong> — เพิ่มหรือหักโดยตรง พร้อมระบุเหตุผล (บันทึกในประวัติ)</span></li>
                            <li><span class="hp-step-num">🔑</span><span><strong style="color:#eeebe1;">รีเซ็ตรหัสผ่าน</strong> ให้พนักงาน</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--warn">
                            <span>🛡️</span>
                            <span>เฉพาะ <strong>Admin</strong> เท่านั้นที่เปลี่ยน Role หรือลบบัญชีได้ — ไม่สามารถกระทำกับบัญชีตัวเองได้</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ HR -->
                <div class="hp-section" id="hr-faq" data-keywords="faq คำถาม hr admin token คืน toggle ลบ">
                    <div class="hp-section-header" onclick="toggleSection(this)">
                        <div class="hp-section-icon">❓</div>
                        <span class="hp-section-title-text">คำถามที่พบบ่อย</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    พนักงานได้ Token ไปแล้ว ต้องการถอนคืน ทำได้ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ได้ ไปที่หน้าจัดการพนักงาน → ปรับ Token → ใส่จำนวนเป็นลบ เช่น <strong>-50</strong> พร้อมระบุเหตุผล ระบบจะบันทึกในประวัติพนักงาน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    ต้องการซ่อนรางวัลชั่วคราว ไม่ต้องการลบ
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ใช้ปุ่ม <strong>Toggle</strong> เพื่อปิดรางวัล พนักงานจะไม่เห็นในร้าน เปิดกลับได้ทุกเมื่อโดยกด Toggle อีกครั้ง</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    ภารกิจหมดวันสิ้นสุดแล้ว พนักงานยังเห็นอยู่ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ไม่ ระบบซ่อนภารกิจที่เลยวันสิ้นสุดให้อัตโนมัติ แต่ยังอยู่ในหน้า HR สามารถแก้ไขวันหรือลบได้</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" onclick="toggleFaq(this)">
                                    เพิ่มภารกิจ Quiz แล้วยังไม่มีคำถาม พนักงานจะเห็นอะไร?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ภารกิจจะแสดงในรายการ แต่เมื่อพนักงานกดทำจะขึ้นว่า "ภารกิจนี้ยังไม่มีคำถาม" ต้องเพิ่มคำถามก่อนจึงจะทำได้</div></div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /content hr -->
        </div><!-- /layout hr -->
        </div><!-- /panel-hr -->

        <div class="hp-no-result" id="hp-no-result">
            <div class="hp-no-result-icon">🔍</div>
            ไม่พบข้อมูลที่ค้นหา ลองใช้คำอื่น
        </div>

    </div><!-- /inner -->
</div><!-- /wrap -->

<script>
// ── Tab pill positioning ────────────────────────────────────
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

// ── Tab switcher ─────────────────────────────────────────────
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

// Init pill on load
window.addEventListener('load', function() {
    var activeTab = document.querySelector('.hp-tab.active');
    if (activeTab) positionPill(activeTab);
});
window.addEventListener('resize', function() {
    var activeTab = document.querySelector('.hp-tab.active');
    if (activeTab) positionPill(activeTab);
});

// ── Accordion toggle ─────────────────────────────────────────
function toggleSection(header) {
    var section = header.closest('.hp-section');
    section.classList.toggle('open');
}

// ── FAQ toggle ───────────────────────────────────────────────
function toggleFaq(qEl) {
    var item = qEl.closest('.hp-faq-item');
    item.classList.toggle('open');
}

// ── Sidebar scroll ───────────────────────────────────────────
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

// ── Search ───────────────────────────────────────────────────
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
