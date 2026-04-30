# claude.md — Mission Token Project Context
> อัปเดตล่าสุด: 30 เมษายน 2569 (session 3)

---

## 1. ภาพรวมโปรเจค

**Mission Token** คือระบบ gamification ภายในองค์กร JOURNAL
พนักงานทำ "ภารกิจ" (Challenges) เพื่อสะสม Token แล้วนำ Token ไปแลกรางวัลในร้านรางวัล
ระบบทำงานบน **XAMPP (PHP + MSSQL)** สำหรับ deploy ภายในองค์กร

- **BASE_URL**: `http://localhost/mission_token`
- **APP_VERSION**: `1.0.0`
- **DB Name**: `mission_token` (Thai_CI_AS collation)

---

## 2. Tech Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.x |
| Database | MS SQL Server (Express) — driver `pdo_sqlsrv` |
| CSS Framework | Tailwind CSS Play CDN v3 |
| Font | Prompt (Thai + Latin, Google Fonts) |
| JS | Vanilla JS (no framework) |

### Tailwind Config
ต้องวาง `window.tailwind = { config: {...} }` **ก่อน** `<script src="cdn.tailwindcss.com">` เสมอ
กำหนดใน `includes/header.php`

---

## 3. โครงสร้างไฟล์

```
mission_token/
├── index.php                  # Public home page (dark hero, leaderboard, about)
├── login.php                  # Login page (API sync + local DB)
├── logout.php                 # Destroy session → redirect /login.php
├── config/
│   ├── app.php                # Constants, session, CSRF, e(), redirect(), flash
│   └── database.php           # getDB() — PDO singleton (pdo_sqlsrv)
├── includes/
│   ├── auth_check.php         # Guard for employee pages (session + timeout 2h)
│   ├── admin_check.php        # Guard for admin pages (role = 'admin')
│   ├── functions.php          # Core business logic (ดูหัวข้อ 7)
│   ├── header.php             # HTML head + sticky nav + Tailwind config
│   └── footer.php             # Footer + app.js + user dropdown JS + CSRF fetch helper
├── pages/
│   ├── dashboard.php          # Employee dashboard (dark Operative theme)
│   ├── challenges.php         # Challenge list + quiz/photo submission handler
│   ├── rewards.php            # Token shop — redeem rewards (AJAX) + coupon reveal
│   ├── history.php            # Employee transaction + redemption history (dark theme)
│   └── profile.php            # Employee profile + change password + work tenure
├── admin/
│   ├── submissions.php        # Approve/reject photo submissions (dark theme)
│   ├── challenges/
│   │   ├── index.php          # List/toggle/delete challenges (dark theme)
│   │   └── edit.php           # Create/edit challenge + quiz questions (dark theme)
│   └── rewards/
│       ├── index.php          # Manage rewards catalogue (CRUD + stock + coupon_code)
│       ├── edit.php           # Edit reward + coupon_code field
│       └── redemptions.php    # Review pending redemption requests (dark theme)
├── assets/
│   ├── css/style.css          # Main stylesheet (ดูหัวข้อ 8)
│   ├── js/app.js              # Counter, confetti, quiz UI, login form UX
│   └── images/                # logo.png, token.png
├── sql/
│   ├── schema.sql             # DDL — สร้าง DB + 6 tables ทั้งหมด
│   ├── rewards_migration.sql  # DDL เพิ่มเติม rewards + reward_redemptions tables
│   ├── coupon_migration.sql   # ALTER TABLE rewards ADD coupon_code NVARCHAR(200) NULL
│   └── seed.php               # Seed data (run in browser once)
└── uploads/
    └── submissions/           # ไฟล์รูปที่พนักงานส่ง (photo submission)
```

---

## 4. Database Schema

### Tables

| Table | คำอธิบาย |
|---|---|
| `employees` | ข้อมูลพนักงาน + role + password_hash + start_date |
| `challenges` | ภารกิจ (title, type, token_reward, start/end_date) |
| `quiz_questions` | คำถาม Quiz ของแต่ละ challenge |
| `token_wallets` | กระเป๋า Token 1:1 กับ employees |
| `challenge_submissions` | การส่งงาน (quiz/photo) + status + token_awarded |
| `token_transactions` | ประวัติ transaction ทุกรายการ |
| `rewards` | รายการรางวัลใน Token Shop (+ `coupon_code` NVARCHAR(200) NULL) |
| `reward_redemptions` | คำขอแลกรางวัลจากพนักงาน |

### Key Columns
- `employees.role` → `'employee'` หรือ `'admin'`
- `challenges.type` → `'quiz'` หรือ `'photo'`
- `challenge_submissions.status` → `'pending'` | `'auto_approved'` | `'approved'` | `'rejected'`
- `token_transactions.tx_type` → `'quiz_reward'` | `'photo_reward'` | `'admin_adjust'` | `'bonus'` | `'redemption'`
- `reward_redemptions.status` → `'pending'` | `'fulfilled'` | `'cancelled'`
- `rewards.stock` → `NULL` = unlimited
- `rewards.coupon_code` → `NULL` หรือ รหัสคูปอง — แสดงให้พนักงานเห็นเฉพาะเมื่อ redemption = `fulfilled`

---

## 5. Authentication Flow

### Login (login.php)
1. รับ `employee_code` + `password` + CSRF token
2. ดึงข้อมูลจาก External API: `http://203.154.130.236/emp_api/api/employee.php` (key: `my-secret-key-12345`)
3. ถ้า API ไม่ตอบสนอง → fallback หา local DB
4. `syncEmployeeFromAPI()` → upsert ข้อมูลพนักงานเข้า local DB (full_name, department, position, email, start_date)
5. verify `password_hash` กับ bcrypt
6. สร้าง session: `employee_id`, `employee_code`, `full_name`, `department`, `role`, `token_balance`
7. redirect → `/pages/dashboard.php` (employee) หรือ `/admin/dashboard.php` (admin)

### Session Guard
- **auth_check.php** → สำหรับ employee pages, redirect `/login.php` ถ้าไม่มี session
- **admin_check.php** → ตรวจ role = 'admin', redirect `/pages/dashboard.php` ถ้า role ไม่ใช่ admin
- timeout: 2 ชั่วโมง inactive → destroy session

---

## 6. Business Logic Rules

### Challenge Types
| Type | วิธีส่ง | การ approve |
|---|---|---|
| `quiz` | ตอบคำถามหลายข้อใน UI | `auto_approved` ถ้าถูกทุกข้อ / `rejected` ถ้าผิด |
| `photo` | อัปโหลดรูป/หลักฐาน | รอ HR/Admin `approved` หรือ `rejected` |

### Quiz Rules
- ตอบได้ **1 ครั้ง เท่านั้น** (แม้ rejected ก็ไม่มี retry)
- ต้องถูก **ทุกข้อ** จึงได้ Token

### Photo Rules
- ส่งได้ 1 ครั้งต่อ challenge (ถ้า rejected สามารถส่งใหม่ได้ — `hasSubmittedChallenge()` กรอง rejected ออก)
- ไฟล์: max 5MB, MIME ต้องเป็น image/jpeg, image/png, image/gif, image/webp
- ชื่อไฟล์: `sub_{employeeId}_{challengeId}_{random_hex}.{ext}` เก็บที่ `uploads/submissions/`

### Token Economy
- `awardTokens()` → INSERT `token_transactions` + UPDATE `token_wallets` ใน transaction เดียว
- Positive amount = earn (total_earned + balance ขึ้น)
- Negative amount = spend (total_spent + balance ลด)
- หลัง award → update `$_SESSION['token_balance']`

### Reward Redemption
- ใช้ `UPDLOCK, ROWLOCK` ป้องกัน race condition บน stock
- ตรวจ balance ก่อนหัก
- INSERT `reward_redemptions` status = 'pending' → Admin มา fulfill ทีหลัง

---

## 7. ฟังก์ชันหลักใน functions.php

| Function | คำอธิบาย |
|---|---|
| `awardTokens($id, $amount, $type, $refId, $note)` | จ่าย/หัก token + update wallet |
| `getWalletBalance($id)` | คืน balance ปัจจุบัน |
| `getWalletInfo($id)` | คืน balance, total_earned, total_spent |
| `getActiveChallenges()` | challenge ที่ active + ยังอยู่ในช่วงวันที่ |
| `getChallenge($id)` | ดึง challenge เดี่ยว |
| `getQuizQuestions($challengeId)` | คำถาม quiz เรียง display_order |
| `hasSubmittedChallenge($empId, $chalId)` | เช็คว่าส่งแล้วหรือยัง (กรอง rejected) |
| `getActivityStreak($id)` | นับวัน streak (approved submissions) |
| `getRecentSubmissions($id, $limit)` | ประวัติ submissions ล่าสุด |
| `getLeaderboard($limit)` | top earners ตาม total_earned |
| `getHomeOverviewStats()` | stats หน้า index (active_employees, team_earned, ฯลฯ) |
| `getRecentTeamActivity($limit)` | activity feed หน้า index |
| `getWeeklyTokenTrend()` | token earned + submissions count 7 วัน (UNION ALL CTE) |
| `getEmployeeProfile($id)` | ข้อมูลโปรไฟล์เต็ม |
| `getWorkTenure($startDate)` | คำนวณอายุงาน (years/months/days) |
| `formatTokens($amount)` | number_format |
| `challengeTypeLabel($type)` | 'quiz' → 'Quiz' |
| `getPendingCount()` | จำนวน pending submissions (admin badge) |
| `txTypeLabel($type)` | Thai label สำหรับ tx_type |
| `statusBadge($status)` | label + color สำหรับ submission status |

### Helper Functions ใน config/app.php
| Function | คำอธิบาย |
|---|---|
| `initSession()` | start session พร้อม security settings |
| `csrfToken()` | generate/return CSRF token จาก session |
| `csrfField()` | HTML hidden input สำหรับ form |
| `validateCsrf()` | ตรวจ POST csrf_token, return 403 JSON ถ้าไม่ผ่าน |
| `e($str)` | htmlspecialchars (HTML escape) |
| `redirect($url)` | header Location + exit |
| `setFlash($type, $msg)` | set flash message ใน session (**type มาก่อน**) |
| `getFlash()` | ดึง + clear flash message |
| `isPost()` | ตรวจ REQUEST_METHOD === 'POST' |

---

## 8. CSS Architecture (style.css)

ไฟล์เดียว แบ่งเป็น section:

| Section (lines โดยประมาณ) | คำอธิบาย |
|---|---|
| 1–288 | Global palette comment + Login page (`.login-*`) |
| 290–903 | Public Home Page (`.home-page-wrap`, `.hero-*`, `.about-*`) |
| 904–926 | Site Footer (`.site-footer`, `.site-footer-*`) |
| 927–1243 | **Dashboard Page** — Dark Operative theme (`ds-*`) |
| 1244+ | **Challenges Page** + body overrides สำหรับทุก dark pages |

### CSS Class Prefixes
| Prefix | Page / Component |
|---|---|
| `ds-` | Dashboard (dark theme) |
| `ch-` | Challenges page (dark theme) |
| `quiz-` | Quiz component |
| `rw-` | Rewards page (dark theme) |
| `hy-` | History page (dark theme) |
| `ar-` | Admin rewards index + edit (dark theme) |
| `ard-` | Admin redemptions (dark theme) |
| `asb-` | Admin submissions (dark theme) |
| `ac-` | Admin challenges index (dark theme) |
| `ace-` | Admin challenges edit (dark theme) |
| `home-` | Home page |
| `hero-` | Hero section ของ index |
| `about-` | About section ของ index |
| `site-footer-` | Shared footer |
| `journal-` | Global card/input utilities |
| `btn-` | Global button variants |
| `nav-` | Navigation bar |

### Dark Theme Body Overrides (ใน style.css)
```css
body:has(.ds-dashboard-wrap)   { background-color: #091113; }
body:has(.ch-challenges-wrap)  { background-color: #091113; }
body:has(.rw-rewards-wrap)     { background-color: #091113; }
body:has(.hy-history-wrap)     { background-color: #091113; }
body:has(.ar-rewards-wrap)     { background-color: #091113; }
body:has(.ar-redemptions-wrap) { background-color: #091113; }
body:has(.asb-submissions-wrap){ background-color: #091113; }
body:has(.ac-challenges-wrap)  { background-color: #091113; }
body:has(.ace-edit-wrap)       { background-color: #091113; }
```

### Global Utilities (ใน header.php inline `<style>`)
- `.btn-dark` / `.btn-gold` / `.btn-outline` / `.btn-danger` — button variants
- `.journal-card` — cream card base
- `.journal-input` — form input style
- `.section-title` — light theme (gold underline) — **ใช้บน admin/challenges/light pages เท่านั้น**
- `.badge` — pill badge
- `.nav-active` — gold underline active nav link
- `.text-gold-shimmer` — shimmer animation text

---

## 9. Brand Colors

```
ขาวผ่อง   #eeebe1  (j-white)      — body background, text light
ขาวงาช้าง #fdfcdf  (j-ivory)      — card background
ขาวกะบัง  #cecdcd  (j-silver)     — borders
สวาด      #6b6e77  (j-slate)      — muted text
หมึกจีน   #3a3e43  (j-charcoal)   — secondary text
ดำเขม่า   #091113  (j-dark)       — dark background, nav
รงทอง     #dab937  (j-gold)       — primary accent
ดอกกวน    #f8e769  (j-gold-light) — gold light
            #c9a830  (j-gold-dk)   — gold dark
เขียว     #518e5c  (j-green)      — success
ฟ้า       #4f8b98  (j-teal)       — teal accent
ส้ม       #d2592a  (j-orange)     — error/danger
```

---

## 10. MSSQL Quirks ที่สำคัญ

| ปัญหา | วิธีแก้ |
|---|---|
| `LIMIT` ไม่มีใน MSSQL | ใช้ `TOP ({$limit})` inline เท่านั้น — **ห้าม** bind ค่า TOP |
| `offsets` เป็น reserved word | ใช้ UNION ALL แทน VALUES(...) table |
| `lastInsertId()` คืน null กับ pdo_sqlsrv | Re-query `SELECT employee_id WHERE employee_code = ?` แทน |
| date functions | `GETDATE()`, `CAST(x AS DATE)`, `MONTH()`, `YEAR()` |
| Row locking | `WITH (UPDLOCK, ROWLOCK)` สำหรับ stock/balance critical section |

---

## 11. Security

- **CSRF**: ทุก POST form ต้องมี `<?= csrfField() ?>` — `validateCsrf()` check ด้าน server
- **CSRF + fetch()**: footer.php inject `X-CSRF-Token` header ผ่าน `_fetchJSON()` helper
- **HTML Escape**: ใช้ `e()` ทุกที่ที่ output user data
- **Password**: `password_hash($pw, PASSWORD_BCRYPT)` / `password_verify()`
- **File Upload**: ตรวจ MIME จาก finfo (ไม่ใช้ extension เพียงอย่างเดียว)
- **Session**: httponly cookie, SameSite=Lax, timeout 2h
- **SQL Injection**: ใช้ prepared statements ทั้งหมด — ยกเว้น `TOP ({$limit})` ที่ cast เป็น int ก่อน

---

## 12. Nav Links

### Employee Nav
| Key | Label | URL |
|---|---|---|
| `dashboard` | หน้าแรก | `/index.php` |
| `challenges` | ภารกิจ | `/pages/challenges.php` |
| `rewards` | ร้านรางวัล | `/pages/rewards.php` |
| `history` | ประวัติ | `/pages/history.php` |

### Admin Nav
| Key | Label | URL |
|---|---|---|
| `admin_dashboard` | ภาพรวมระบบ | `/admin/dashboard.php` |
| `admin_challenges` | จัดการภารกิจ | `/admin/challenges/index.php` |
| `admin_submissions` | อนุมัติงาน | `/admin/submissions.php` *(badge: pending count)* |
| `admin_rewards` | จัดการรางวัล | `/admin/rewards/index.php` *(badge: pending redemptions)* |
| `admin_employees` | จัดการพนักงาน | `/admin/employees.php` |

---

## 13. Design Themes ต่อ Page

| Page | Theme |
|---|---|
| `index.php` | Dark (`#091113` bg), gold hero, floating token coins |
| `login.php` | Dark split layout — left dark panel + right form |
| `pages/dashboard.php` | Dark "Operative Dossier" — aurora blobs, glassmorphism cards, `ds-*` classes |
| `pages/challenges.php` | **Dark** — Operative Dossier theme (`ch-*`, aurora blobs, glassmorphism) |
| `pages/rewards.php` | **Dark** — token shop (`rw-*`) |
| `pages/history.php` | **Dark** — transaction + redemption history (`hy-*`) |
| `pages/profile.php` | Light cream |
| `admin/challenges/index.php` | **Dark** — challenge list (`ac-*`) |
| `admin/challenges/edit.php` | **Dark** — create/edit challenge (`ace-*`) |
| `admin/submissions.php` | **Dark** — photo approval (`asb-*`) |
| `admin/rewards/index.php` | **Dark** — rewards catalogue (`ar-*`) |
| `admin/rewards/edit.php` | **Dark** — edit reward (`ar-*`) |
| `admin/rewards/redemptions.php` | **Dark** — redemption requests (`ard-*`) |
| `admin/dashboard.php` | ยังไม่มีไฟล์ |
| `admin/employees.php` | ยังไม่มีไฟล์ |

---

## 14. Concept Constraints (สำคัญมาก)

- **ไม่มีระบบทีม** — individual only ทุกอย่าง
  - ห้าม implement team challenge, team leaderboard, team reward
  - `department` column มีไว้แสดง info เฉยๆ ไม่ใช้ group/score
- `getHomeOverviewStats()` ยังมี `team_balance`, `team_earned` อยู่ (legacy — ยังไม่ได้ clean)
- `getRecentTeamActivity()` ชื่อ function มี "Team" แต่ไม่ได้ group by team จริงๆ

---

## 15. Flash Message Pattern

```php
setFlash('success', 'ข้อความ');   // type มาก่อนเสมอ
setFlash('error', 'ข้อความ');
$flash = getFlash();               // คืน ['type' => ..., 'message' => ...] หรือ null
```

---

## 16. การเพิ่ม Page ใหม่

1. สร้างไฟล์ใน `pages/` หรือ `admin/`
2. บรรทัดแรก: `require_once __DIR__ . '/../includes/auth_check.php';` (หรือ admin_check)
3. ตั้ง `$pageTitle` และ `$activePage` ก่อน require header
4. `require_once __DIR__ . '/../includes/header.php';`
5. HTML content อยู่หลัง `?>`
6. ปิดด้วย `<?php require_once __DIR__ . '/../includes/footer.php'; ?>`
7. ถ้ามี CSS เฉพาะ page → เพิ่ม section ใหม่ใน `assets/css/style.css` พร้อม prefix class ของตัวเอง

---

## 17. สถานะ Development (ณ 30 เม.ย. 2569)

### เสร็จแล้ว ✅
- Login page (dark theme, API sync)
- Dashboard (dark Operative Dossier theme)
- Challenges page (dark Operative Dossier theme + flip card interaction)
  - Quest card แบบ 3D hover-flip: hover → พลิก, กดการ์ดหลัง (quiz) → navigate ทำ quiz
  - Front face: token reward hero + ชื่อ + animated mystery bars
  - Back face: รายละเอียดเต็ม + photo upload form (photo type) / onclick navigate (quiz type)
  - CSS classes: `.ch-quest-flip-scene`, `.ch-flip-card`, `.ch-flip-front`, `.ch-flip-back`
  - `.ch-flip-back-body` ใช้ `position: absolute; inset: 0` เพื่อป้องกัน overflow ทำให้ปุ่มหาย
- Rewards page (dark theme, token shop + AJAX redeem + coupon reveal after fulfilled)
- History page (`pages/history.php`) — dark theme, wallet summary + tabs (Token / รางวัล)
- Profile page (info + change password)
- Admin: challenges index — dark theme redesign (`ac-*`)
- Admin: challenges edit — dark theme + back button (`ace-*`)
- Admin: submissions approve/reject (dark theme, `asb-*`)
- Admin: rewards management + redemptions (dark theme, `ar-*` / `ard-*`)
- Admin: rewards create/edit — เพิ่ม coupon_code field
- Home page (dark hero, leaderboard, weekly trend)
- `sql/coupon_migration.sql` — ต้อง run ใน SSMS ก่อนใช้ฟีเจอร์ coupon_code
  ```sql
  ALTER TABLE dbo.rewards ADD coupon_code NVARCHAR(200) NULL;
  ```

### หน้าที่มีใน Nav แต่ยังไม่มีไฟล์ (TODO)
- `admin/dashboard.php` — admin overview (nav link ชี้ไปแล้ว)
- `admin/employees.php` — จัดการพนักงาน (nav link ชี้ไปแล้ว)
