<?php
/**
 * strava-privacy.php
 * Public policy page for Strava integration review and user transparency.
 */

require_once __DIR__ . '/config/app.php';

$appName = APP_NAME;
$updatedAt = '2026-06-17';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strava Privacy | <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="layout-login font-prompt">
    <main class="sp-wrap">
        <article class="sp-card">
            <header class="sp-head">
                <p class="sp-kicker">Strava Data Notice</p>
                <h1 class="sp-title">นโยบายข้อมูล Strava</h1>
                <p class="sp-meta">อัปเดตล่าสุด: <?= e($updatedAt) ?></p>
            </header>

            <section class="sp-section">
                <h2>1) เราเก็บข้อมูลอะไร</h2>
                <p>เมื่อคุณเชื่อมต่อ Strava ระบบจะเก็บข้อมูลที่จำเป็นต่อการตรวจภารกิจเท่านั้น ได้แก่ Athlete ID, สิทธิ์ที่อนุญาต (scope), access/refresh token และข้อมูลกิจกรรมที่ใช้ยืนยันผลภารกิจ</p>
            </section>

            <section class="sp-section">
                <h2>2) เก็บข้อมูลอย่างไร</h2>
                <p>ระบบใช้ OAuth 2.0 ของ Strava เพื่อให้คุณอนุญาตสิทธิ์ด้วยตัวเอง และดึงข้อมูลผ่าน Strava API โดยใช้ token ที่ออกให้ตามสิทธิ์ที่คุณยอมรับ</p>
            </section>

            <section class="sp-section">
                <h2>3) ใช้ข้อมูลเพื่ออะไร</h2>
                <p>ใช้เพื่อประเมินเงื่อนไขภารกิจ Strava ภายในระบบ Mission Token เท่านั้น ไม่ใช้เพื่อโฆษณา และไม่เปิดเผยข้อมูลกิจกรรมของผู้ใช้ให้ผู้ใช้รายอื่น</p>
            </section>

            <section class="sp-section">
                <h2>4) วิธีถอนความยินยอม</h2>
                <p>คุณสามารถถอนสิทธิ์ได้ตลอดเวลาที่หน้าเชื่อมต่อ Strava โดยกดปุ่มยกเลิกการเชื่อมต่อ ระบบจะพยายาม revoke token กับ Strava และล้าง token ในระบบนี้</p>
            </section>

            <section class="sp-section">
                <h2>5) วิธีขอลบข้อมูล</h2>
                <p>คุณสามารถขอลบข้อมูล Strava ในระบบนี้ได้ทันทีจากหน้าเชื่อมต่อ Strava โดยกดปุ่มลบข้อมูล ระบบจะล้างข้อมูล Strava ที่เก็บในฐานข้อมูลระบบนี้ และแจ้งผลการดำเนินการบนหน้าจอ</p>
            </section>

            <section class="sp-section">
                <h2>6) ติดต่อทีมดูแลระบบ</h2>
                <p>หากต้องการความช่วยเหลือ กรุณาติดต่อทีม HR/IT ภายในองค์กร JOURNAL ผ่านช่องทางสนับสนุนภายในของบริษัท</p>
            </section>

            <footer class="sp-foot">
                <a href="<?= BASE_URL ?>/pages/strava_connect.php" class="sp-link">กลับไปหน้าเชื่อมต่อ Strava</a>
                <a href="https://www.strava.com/legal/api_policy" class="sp-link" target="_blank" rel="noopener noreferrer">Strava API Policy</a>
            </footer>
        </article>
    </main>
</body>
</html>
