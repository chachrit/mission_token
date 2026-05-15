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
                <span class="hp-hero-stat hp-hero-stat--hr"><span class="hp-hero-stat-dot hp-dot-hr"></span>คู่มือ HR</span>
            </div>
        </div>

        <!-- ── Tab switcher ── -->
        <div class="hp-tabs-wrap">
            <div class="hp-tabs-track" id="hp-tabs-track">
                <div class="hp-tab-pill" id="hp-tab-pill"></div>
                <button class="hp-tab active" data-action="switchTab" data-tab="employee" id="tab-employee">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    สำหรับพนักงาน
                </button>
                <button class="hp-tab" data-action="switchTab" data-tab="hr" id="tab-hr">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    HR
                </button>
            </div>
        </div>

        <!-- ── Search ── -->
        <div class="hp-search-outer">
            <div class="hp-search-wrap">
                  <input type="text" class="hp-search-input" id="hp-search"
                      placeholder="ค้นหาในคู่มือ เช่น Token, Quiz, Strava...">
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
                <button class="hp-sidebar-link active" data-action="scrollToSection" data-section-id="emp-login">
                    <span class="hp-sl-icon hp-fs-06">LGN</span> การเข้าสู่ระบบ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-dashboard">
                    <span class="hp-sl-icon hp-fs-06">DS</span> Dashboard
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-challenges">
                    <span class="hp-sl-icon hp-fs-06">Q</span> ภารกิจ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-rewards">
                    <span class="hp-sl-icon">R</span> ร้านรางวัล
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-history">
                    <span class="hp-sl-icon">H</span> ประวัติ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-profile">
                    <span class="hp-sl-icon hp-fs-06">P</span> โปรไฟล์
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-strava">
                    <span class="hp-sl-icon hp-strava-icon-accent">STR</span> Strava
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="emp-faq">
                    <span class="hp-sl-icon hp-fs-065">FAQ</span> FAQ
                </button>
            </div>

            <!-- Content -->
            <div>

                <!-- เข้าสู่ระบบ -->
                <div class="hp-section open" id="emp-login" data-keywords="login เข้าสู่ระบบ รหัสผ่าน portal">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">LGN</div>
                        <span class="hp-section-title-text">การเข้าสู่ระบบ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">ใช้ <strong>รหัสพนักงาน</strong> และ <strong>รหัสผ่าน</strong> เดิมที่ใช้กับ <strong>JOURNAL Web Portal</strong> ได้เลย — ไม่ต้องสมัครใหม่</p>
                        <p class="hp-text hp-mb-04"><strong class="hp-c-ivory">ลืมหรือต้องการเปลี่ยนรหัสผ่าน?</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กดที่ <strong class="hp-c-ivory">ชื่อของคุณ</strong> (มุมบนขวา) → เลือก <strong>"โปรไฟล์ของฉัน"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>เลื่อนลงหาส่วน <strong class="hp-c-ivory">"เปลี่ยนรหัสผ่าน"</strong> กรอกรหัสเก่า + รหัสใหม่ (อย่างน้อย 8 ตัว) → กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>Login ไม่ได้เลย? ให้ <strong>ติดต่อ HR หรือ IT</strong> เพื่อขอ Reset รหัสผ่าน</span>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>ระบบออกจากระบบอัตโนมัติหากไม่มีการใช้งานนาน <strong>2 ชั่วโมง</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="hp-section" id="emp-dashboard" data-keywords="dashboard หน้าแรก token streak อันดับ leaderboard">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">DS</div>
                        <span class="hp-section-title-text">หน้า Dashboard — ภาพรวมของคุณ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">หน้าแรกหลัง Login แสดงข้อมูลทั้งหมดของคุณในที่เดียว</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num hp-bg-gold-soft">1</span>
                                <span><strong class="hp-c-gold">กระเป๋า Token</strong> — Token คงเหลือ (ใช้แลกรางวัลได้), Token ที่ได้รับทั้งหมด, Token ที่ใช้ไปแล้ว</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong class="hp-c-ivory">อันดับของคุณ</strong> — ลำดับใน Leaderboard นับจาก Token ที่ได้รับทั้งหมด (ไม่ใช่คงเหลือ)</span>
                            </li>
                            <li>
                                <span class="hp-step-num">3</span>
                                <span><strong class="hp-c-ivory">Streak</strong> — จำนวนวันติดต่อกันที่ทำภารกิจผ่านแล้ว</span>
                            </li>
                            <li>
                                <span class="hp-step-num">4</span>
                                <span><strong class="hp-c-ivory">Token เดือนนี้</strong> — Token ที่ได้รับในเดือนปัจจุบัน</span>
                            </li>
                            <li>
                                <span class="hp-step-num">5</span>
                                <span><strong class="hp-c-ivory">ภารกิจที่เปิดอยู่</strong> — แสดงภารกิจที่ทำได้พร้อม Token รางวัล</span>
                            </li>
                            <li>
                                <span class="hp-step-num">6</span>
                                <span><strong class="hp-c-ivory">กิจกรรมล่าสุด</strong> — 6 รายการล่าสุดที่คุณส่งงาน พร้อมสถานะ</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- ภารกิจ -->
                <div class="hp-section" id="emp-challenges" data-keywords="challenge ภารกิจ quiz photo strava การ์ด พลิก token รางวัล">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">Q</div>
                        <span class="hp-section-title-text">หน้าภารกิจ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">หน้านี้แสดงภารกิจทั้งหมดที่เปิดอยู่ แบ่งเป็น <strong>"ภารกิจรอคุณอยู่"</strong> (ยังทำได้) และ <strong>"เสร็จสิ้นแล้ว"</strong></p>

                        <p class="hp-text hp-mb-05"><strong class="hp-c-ivory">วิธีใช้งานการ์ด:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>เลื่อนดูการ์ดภารกิจ แต่ละใบแสดงชื่อและ Token รางวัล</span></li>
                            <li><span class="hp-step-num">2</span><span><strong class="hp-c-ivory">วางเมาส์บนการ์ด</strong> (หรือแตะบนมือถือ) การ์ดจะ <strong class="hp-c-gold">พลิกแสดงรายละเอียด</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>ด้านหลังการ์ดแสดงเงื่อนไข, วันสิ้นสุด และปุ่มทำภารกิจ</span></li>
                        </ol>

                        <p class="hp-text hp-mt-125 hp-mb-05"><strong class="hp-c-ivory">ภารกิจมี 3 ประเภท:</strong></p>
                        <div class="hp-type-grid">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">Quiz — ตอบคำถาม</p>
                                <p class="hp-type-detail">
                                    ต้องตอบถูก <strong class="hp-c-ivory">ทุกข้อ</strong> จึงได้ Token<br>
                                    ทำได้ <strong class="hp-c-warn">1 ครั้งเท่านั้น</strong> ไม่มีโอกาสแก้ไข<br><br>
                                    กดปุ่ม "เริ่มทำ Quiz" แล้วตอบคำถาม → กด "ยืนยันคำตอบ"
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--photo">
                                <p class="hp-type-label">Photo — ส่งรูปหลักฐาน</p>
                                <p class="hp-type-detail">
                                    HR ตรวจสอบรูปและอนุมัติ → ได้ Token<br>
                                    ถ้า HR ไม่ผ่าน <strong class="hp-c-green">ส่งใหม่ได้</strong><br><br>
                                    ไฟล์: JPG/PNG/WebP ขนาดไม่เกิน 5MB
                                </p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label hp-c-strava">Strava — ออกกำลังกาย</p>
                                <p class="hp-type-detail">
                                    ระบบตรวจกิจกรรมใน Strava ว่าผ่านเงื่อนไขไหม<br>
                                    ถ้าไม่พบ <strong class="hp-c-strava">ลองใหม่ได้</strong> หลังบันทึกกิจกรรมเพิ่ม<br><br>
                                    ต้องเชื่อมต่อ Strava ก่อน (ทำครั้งเดียว)
                                </p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn">
                            <span>Quiz ทำได้ <strong>ครั้งเดียว</strong> อ่านคำถามให้ครบก่อนกดยืนยันเสมอ</span>
                        </div>
                    </div>
                </div>

                <!-- ร้านรางวัล -->
                <div class="hp-section" id="emp-rewards" data-keywords="reward รางวัล แลก token shop คูปอง stock">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">รางวัล</div>
                        <span class="hp-section-title-text">ร้านรางวัล</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-intro">นำ Token ที่สะสมได้ไปแลกเป็นรางวัลต่างๆ ที่องค์กรจัดไว้ให้</p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>ดูรายการรางวัล แต่ละรายการแสดงราคา Token</span></li>
                            <li><span class="hp-step-num">2</span><span>ตรวจสอบ <strong class="hp-c-gold">Token คงเหลือ</strong> ที่มุมบนขวาว่าเพียงพอ</span></li>
                            <li><span class="hp-step-num">3</span><span>กดปุ่ม <strong class="hp-c-gold">"แลกรางวัล"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>ยืนยันการแลก — Token จะถูกหักทันที</span></li>
                            <li><span class="hp-step-num">5</span><span>รอ HR จัดเตรียมและส่งมอบ สถานะจะเปลี่ยนจาก <em>"รอดำเนินการ"</em> เป็น <em>"สำเร็จ"</em></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>รางวัลบางอย่างมี <strong>รหัสคูปอง</strong> — จะปรากฏในหน้าประวัติ หลัง HR ยืนยันการส่งมอบแล้วเท่านั้น</span>
                        </div>
                        <div class="hp-tip hp-tip--info">
                                                        <span>รางวัลที่มีตัวเลขสต็อก — เมื่อหมดแล้วปุ่มแลกจะปิดอัตโนมัติ</span>
                        </div>
                    </div>
                </div>

                <!-- ประวัติ -->
                <div class="hp-section" id="emp-history" data-keywords="history ประวัติ transaction token รางวัล คูปอง">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">H</div>
                        <span class="hp-section-title-text">หน้าประวัติ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">ดูประวัติทุกอย่างที่เกิดขึ้นกับบัญชี แบ่งเป็น 2 แท็บ</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">1</span>
                                <span><strong class="hp-c-gold">แท็บ Token</strong> — รายการรับ-จ่าย Token ทั้งหมด: ได้จาก Quiz, Photo, Strava / หักเมื่อแลกรางวัล / ปรับโดย HR</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong class="hp-c-ivory">แท็บ รางวัล</strong> — รายการแลกรางวัลพร้อมสถานะ: <em>รอดำเนินการ</em> / <em>สำเร็จ</em> / <em>ยกเลิก</em><br>
                                <span class="hp-c-strava">รหัสคูปองจะปรากฏตรงนี้เมื่อรางวัลสำเร็จแล้ว</span></span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- โปรไฟล์ -->
                <div class="hp-section" id="emp-profile" data-keywords="profile โปรไฟล์ รูป รหัสผ่าน password อายุงาน">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">P</div>
                        <span class="hp-section-title-text">หน้าโปรไฟล์</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text">เข้าได้จาก เมนูชื่อของคุณ (มุมบนขวา) → "โปรไฟล์ของฉัน"</p>
                        <ol class="hp-steps">
                            <li>
                                <span class="hp-step-num">1</span>
                                <span><strong class="hp-c-ivory">เปลี่ยนรูปโปรไฟล์</strong> — กดที่รูป เลือกไฟล์ภาพ (JPG/PNG/WebP ≤ 2MB) แล้วกด "อัปโหลด"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">2</span>
                                <span><strong class="hp-c-ivory">เปลี่ยนรหัสผ่าน</strong> — กรอกรหัสปัจจุบัน + รหัสใหม่ (อย่างน้อย 8 ตัว) แล้วกด "บันทึก"</span>
                            </li>
                            <li>
                                <span class="hp-step-num">3</span>
                                <span><strong class="hp-c-ivory">อายุงาน</strong> — แสดงระยะเวลาที่ทำงานในองค์กร</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Strava -->
                <div class="hp-section" id="emp-strava" data-keywords="strava วิ่ง ปั่น เดิน กิจกรรม ออกกำลังกาย เชื่อมต่อ">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon" class="hp-bg-strava-soft">
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

                        <p class="hp-text" class="hp-mb-025"><strong class="hp-c-ivory">ขั้นตอนเชื่อมต่อ (ทำครั้งแรกครั้งเดียว):</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>ไปที่หน้า <strong class="hp-c-ivory">โปรไฟล์</strong> → เลื่อนลงมาหาส่วน Strava</span></li>
                            <li><span class="hp-step-num">2</span><span>กดปุ่ม <strong class="hp-c-strava">"เชื่อมต่อ Strava"</strong></span></li>
                            <li><span class="hp-step-num">3</span><span>ระบบจะพาไป Strava.com ให้กด <strong>"Authorize"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>ระบบจะพากลับมาอัตโนมัติ — เชื่อมต่อสำเร็จ</span></li>
                        </ol>

                        <p class="hp-text" class="hp-mt-1 hp-mb-025"><strong class="hp-c-ivory">วิธีทำภารกิจ Strava:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>บันทึกกิจกรรม (วิ่ง/ปั่น) ให้ครบตามเงื่อนไขก่อน</span></li>
                            <li><span class="hp-step-num">2</span><span>กลับมาที่หน้าภารกิจ กดการ์ดเพื่อดูด้านหลัง</span></li>
                            <li><span class="hp-step-num">3</span><span>กดปุ่ม <strong class="hp-c-strava">"ตรวจสอบกิจกรรม Strava"</strong></span></li>
                            <li><span class="hp-step-num">4</span><span>รอประมาณ <strong class="hp-c-ivory">15–30 วินาที</strong> ห้ามปิดหน้าต่างระหว่างรอ</span></li>
                            <li><span class="hp-step-num">5</span><span>ผ่าน → ได้ Token ทันที | ไม่ผ่าน → ทำกิจกรรมเพิ่มแล้วลองใหม่</span></li>
                        </ol>

                        <div class="hp-tip hp-tip--warn">
                            <span>กิจกรรมใน Strava ต้องตั้งเป็น <strong>"Everyone"</strong> หรือ <strong>"Followers"</strong> (ไม่ใช่ Private) ระบบจึงจะมองเห็น</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ พนักงาน -->
                <div class="hp-section" id="emp-faq" data-keywords="faq คำถาม quiz ซ้ำ token หมดอายุ strava ไม่พบ">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">ช่วยเหลือ</div>
                        <span class="hp-section-title-text">คำถามที่พบบ่อย</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    ทำ Quiz ตอบผิด สามารถลองใหม่ได้ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ไม่ได้ Quiz ทำได้ <strong class="hp-c-warn">1 ครั้งต่อภารกิจเท่านั้น</strong> แม้ตอบผิดก็ไม่มีโอกาสแก้ไข อ่านทุกข้อก่อนกดยืนยัน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    ส่งรูปภาพแล้ว HR ไม่ผ่าน ทำอะไรได้บ้าง?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ส่งใหม่ได้เลย กลับไปที่หน้าภารกิจ กลับการ์ด จะมีปุ่ม "ส่งหลักฐานใหม่" ปรากฏขึ้น แก้ไขรูปแล้วส่งอีกครั้ง</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    Token ที่ใช้แลกรางวัลไปแล้วหายไปเลยไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ใช่ Token คงเหลือจะลดลง แต่ <strong class="hp-c-green">Token ที่ได้รับทั้งหมดยังคงนับ</strong> อยู่ใน Leaderboard ไม่หายไปไหน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    Token มีวันหมดอายุไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ระบบปัจจุบันไม่มีวันหมดอายุ สอบถาม HR เพื่อยืนยันนโยบายขององค์กร</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
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
        <div id="panel-hr" class="hp-hidden">
        <div class="hp-layout">

            <!-- Sidebar HR -->
            <div class="hp-sidebar hidden md:block">
                <p class="hp-sidebar-title">หัวข้อ</p>
                <button class="hp-sidebar-link active" data-action="scrollToSection" data-section-id="hr-challenges">
                    <span class="hp-sl-icon hp-fs-06">Q</span> จัดการภารกิจ
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-submissions">
                    <span class="hp-sl-icon hp-fs-06">OK</span> อนุมัติงาน
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-rewards">
                    <span class="hp-sl-icon">R</span> จัดการรางวัล
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-redemptions">
                    <span class="hp-sl-icon hp-fs-06">RQ</span> คำขอแลกรางวัล
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-employees">
                    <span class="hp-sl-icon hp-fs-06">EMP</span> จัดการพนักงาน
                </button>
                <button class="hp-sidebar-link" data-action="scrollToSection" data-section-id="hr-faq">
                    <span class="hp-sl-icon hp-fs-065">FAQ</span> FAQ
                </button>
            </div>

            <!-- Content HR -->
            <div>

                <!-- จัดการภารกิจ -->
                <div class="hp-section open" id="hr-challenges" data-keywords="challenge ภารกิจ สร้าง quiz strava photo toggle ลบ">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">Q</div>
                        <span class="hp-section-title-text">จัดการภารกิจ</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <p class="hp-text hp-mb-05"><strong class="hp-c-ivory">สร้างภารกิจใหม่:</strong></p>
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กดปุ่ม <strong class="hp-c-gold">"+ เพิ่มภารกิจ"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>กรอก ชื่อ, รายละเอียด, ประเภท (Quiz/Photo/Strava), Token รางวัล, วันเริ่ม-สิ้นสุด</span></li>
                            <li><span class="hp-step-num">3</span><span>กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-type-grid hp-mt-1">
                            <div class="hp-type-box hp-type-box--quiz">
                                <p class="hp-type-label">Quiz</p>
                                <p class="hp-type-detail">หลังบันทึกแล้ว กด "+ เพิ่มคำถาม"<br>กรอกคำถาม + ตัวเลือก A–D + เฉลย<br>เพิ่มได้หลายข้อ — ต้องถูกทุกข้อ</p>
                            </div>
                            <div class="hp-type-box hp-type-box--strava">
                                <p class="hp-type-label hp-c-strava">Strava</p>
                                <p class="hp-type-detail">ระบุ: ประเภทกิจกรรม (Run/Ride/Walk),<br>ระยะทางขั้นต่ำ (กม.), เวลาขั้นต่ำ (นาที),<br>ความสูงขั้นต่ำ (เมตร) — ไม่บังคับ</p>
                            </div>
                        </div>
                        <div class="hp-tip hp-tip--warn hp-mt-1">
                            <span>การ <strong>ลบภารกิจ</strong> จะลบประวัติการส่งงานและ Token ที่เคยให้ไปด้วย — ถาวร ไม่สามารถกู้คืนได้ ใช้ปุ่ม Toggle ซ่อนแทนถ้าไม่แน่ใจ</span>
                        </div>
                    </div>
                </div>

                <!-- อนุมัติงาน -->
                <div class="hp-section" id="hr-submissions" data-keywords="submission อนุมัติ ปฏิเสธ รูป photo pending badge">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">OK</div>
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
                            <li><span class="hp-step-num">4</span><span>กด <strong class="hp-c-green">"อนุมัติ"</strong> → พนักงานได้ Token ทันที <br>หรือ <strong class="hp-c-warn">"ปฏิเสธ"</strong> → พนักงานส่งใหม่ได้</span></li>
                        </ol>
                    </div>
                </div>

                <!-- จัดการรางวัล -->
                <div class="hp-section" id="hr-rewards" data-keywords="reward รางวัล สร้าง คูปอง stock toggle ลบ">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon">จัดการรางวัล</div>
                        <span class="hp-section-title-text">จัดการรางวัล</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span>กด <strong class="hp-c-gold">"+ เพิ่มรางวัล"</strong></span></li>
                            <li><span class="hp-step-num">2</span><span>กรอก ชื่อ, รายละเอียด, ไอคอน, หมวดหมู่, ราคา Token, สต็อก (ว่าง = ไม่จำกัด)</span></li>
                            <li><span class="hp-step-num">3</span><span>(ไม่บังคับ) กรอก <strong class="hp-c-gold">รหัสคูปอง</strong> + วันหมดอายุ — พนักงานเห็นได้หลัง HR ส่งมอบแล้วเท่านั้น</span></li>
                            <li><span class="hp-step-num">4</span><span>กด <strong>"บันทึก"</strong></span></li>
                        </ol>
                        <div class="hp-tip hp-tip--gold">
                            <span>แนะนำให้ใช้ปุ่ม <strong>Toggle ปิด</strong> รางวัลแทนการลบ เพื่อไม่ให้ประวัติพนักงานหาย<br>ลบได้เฉพาะรางวัลที่ยังไม่มีใครแลกเท่านั้น</span>
                        </div>
                    </div>
                </div>

                <!-- คำขอแลกรางวัล -->
                <div class="hp-section" id="hr-redemptions" data-keywords="redemption แลก ส่งมอบ ยกเลิก คืน token fulfill cancel">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">RQ</div>
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
                            <li><span class="hp-step-num">3</span><span>กด <strong class="hp-c-green">"ส่งมอบ"</strong> → ใส่หมายเหตุ เช่น "ส่งทางอีเมลแล้ว" → กด "ยืนยัน"</span></li>
                            <li><span class="hp-step-num">4</span><span>สถานะเปลี่ยนเป็น <strong class="hp-c-green">"สำเร็จ"</strong> พนักงานเห็นรหัสคูปอง (ถ้ามี)</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--info">
                            <span>กด <strong>"ยกเลิก"</strong> → Token จะถูก <strong>คืนให้พนักงานอัตโนมัติ</strong></span>
                        </div>
                    </div>
                </div>

                <!-- จัดการพนักงาน -->
                <div class="hp-section" id="hr-employees" data-keywords="employee พนักงาน token ปรับ reset password role บัญชี ปิด">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-06">EMP</div>
                        <span class="hp-section-title-text">จัดการพนักงาน</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <ol class="hp-steps">
                            <li><span class="hp-step-num">1</span><span><strong class="hp-c-ivory">ค้นหา</strong> พนักงานด้วยชื่อหรือรหัส</span></li>
                            <li><span class="hp-step-num">2</span><span><strong class="hp-c-ivory">เปิด/ปิดบัญชี</strong> — บัญชีที่ปิดจะ Login ไม่ได้</span></li>
                            <li><span class="hp-step-num">3</span><span><strong class="hp-c-gold">ปรับ Token</strong> — เพิ่มหรือหักโดยตรง พร้อมระบุเหตุผล (บันทึกในประวัติ)</span></li>
                            <li><span class="hp-step-num">4</span><span><strong class="hp-c-ivory">รีเซ็ตรหัสผ่าน</strong> ให้พนักงาน</span></li>
                        </ol>
                        <div class="hp-tip hp-tip--warn">
                            <span>เฉพาะ <strong>Admin</strong> เท่านั้นที่เปลี่ยน Role หรือลบบัญชีได้ — ไม่สามารถกระทำกับบัญชีตัวเองได้</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ HR -->
                <div class="hp-section" id="hr-faq" data-keywords="faq คำถาม hr admin token คืน toggle ลบ">
                    <div class="hp-section-header" data-action="toggleSection">
                        <div class="hp-section-icon hp-fs-065">FAQ</div>
                        <span class="hp-section-title-text">คำถามที่พบบ่อย</span>
                        <svg class="hp-section-chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="hp-section-body">
                        <div class="hp-faq">

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    พนักงานได้ Token ไปแล้ว ต้องการถอนคืน ทำได้ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ได้ ไปที่หน้าจัดการพนักงาน → ปรับ Token → ใส่จำนวนเป็นลบ เช่น <strong>-50</strong> พร้อมระบุเหตุผล ระบบจะบันทึกในประวัติพนักงาน</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    ต้องการซ่อนรางวัลชั่วคราว ไม่ต้องการลบ
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ใช้ปุ่ม <strong>Toggle</strong> เพื่อปิดรางวัล พนักงานจะไม่เห็นในร้าน เปิดกลับได้ทุกเมื่อโดยกด Toggle อีกครั้ง</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
                                    ภารกิจหมดวันสิ้นสุดแล้ว พนักงานยังเห็นอยู่ไหม?
                                    <svg class="hp-faq-q-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="hp-faq-a-wrap"><div class="hp-faq-a">ไม่ ระบบซ่อนภารกิจที่เลยวันสิ้นสุดให้อัตโนมัติ แต่ยังอยู่ในหน้า HR สามารถแก้ไขวันหรือลบได้</div></div>
                            </div>

                            <div class="hp-faq-item">
                                <div class="hp-faq-q" data-action="toggleFaq">
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

// ── Render token icons as SVG ──────────────────────────────
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
    if (text === 'ช่วยเหลือ') return 'FAQ';
    if (text === 'รางวัล' || text === 'จัดการรางวัล') return 'R';
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

document.getElementById('hp-search')?.addEventListener('input', function (e) {
    searchHelp(e.target.value || '');
});

function showAllSections() {
    document.querySelectorAll('.hp-section').forEach(function(s){ s.style.display = ''; });
    document.getElementById('hp-no-result').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



