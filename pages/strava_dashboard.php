<?php
/**
 * pages/strava_dashboard.php
 * Employee Strava Dashboard — กิจกรรมออกกำลังกายสะสม + สถิติ
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/strava.php';
require_once __DIR__ . '/../config/strava.php';

$employeeId = (int)$_SESSION['employee_id'];

// ── Check connection ──────────────────────────────────────────
$connected = isStravaConnected($employeeId);
$tokenRow  = $connected ? getStravaTokenRow($employeeId) : null;

// ── Load activities (30 days) ─────────────────────────────────
$activities   = [];
$fetchError   = null;
$afterTs      = strtotime('-30 days');
$beforeTs     = time();

// Period selector
$period = (string)($_GET['period'] ?? '30');
$periodOptions = [
    '7'  => '7 วัน',
    '30' => '30 วัน',
    '90' => '90 วัน',
];
if (!isset($periodOptions[$period])) $period = '30';
$afterTs = strtotime("-{$period} days");

if ($connected) {
    try {
        $activities = fetchStravaActivities($employeeId, $afterTs, $beforeTs);
    } catch (Throwable $e) {
        $fetchError = $e->getMessage();
        error_log('[StravaDB] ' . $e->getMessage());
    }
}

// ── Compute summary stats ─────────────────────────────────────
$totalDist   = 0.0;  // metres
$totalTime   = 0;    // seconds
$totalElev   = 0.0;  // metres
$totalCal    = 0;
$countByType = [];
$distByType  = [];
$byDate      = [];   // date => [dist, time, count]

foreach ($activities as $a) {
    $stype = $a['sport_type'] ?? $a['type'] ?? 'Other';
    $dist  = (float)($a['distance']             ?? 0);
    $mtime = (int)($a['moving_time']            ?? 0);
    $elev  = (float)($a['total_elevation_gain'] ?? 0);
    $cal   = (int)($a['kilojoules']             ?? 0); // Strava uses kJ

    $totalDist += $dist;
    $totalTime += $mtime;
    $totalElev += $elev;
    $totalCal  += $cal;

    $countByType[$stype] = ($countByType[$stype] ?? 0) + 1;
    $distByType[$stype]  = ($distByType[$stype]  ?? 0.0) + $dist;

    // Group by date (local)
    $dateKey = substr($a['start_date_local'] ?? '', 0, 10);
    if ($dateKey) {
        $byDate[$dateKey]['dist']  = ($byDate[$dateKey]['dist']  ?? 0) + $dist;
        $byDate[$dateKey]['time']  = ($byDate[$dateKey]['time']  ?? 0) + $mtime;
        $byDate[$dateKey]['count'] = ($byDate[$dateKey]['count'] ?? 0) + 1;
    }
}
arsort($countByType);

// ── Strava challenge submissions (this employee) ──────────────
$stravaSubmissions = [];
try {
    $pdo = getDB();
    $ssStmt = $pdo->prepare("
        SELECT cs.submission_id, cs.challenge_id, cs.status, cs.token_awarded,
               cs.submitted_at, cs.photo_path,
               c.title, c.strava_condition
        FROM   challenge_submissions cs
        JOIN   challenges c ON c.challenge_id = cs.challenge_id
        WHERE  cs.employee_id = ? AND c.type = 'strava'
        ORDER  BY cs.submitted_at DESC
    ");
    $ssStmt->execute([$employeeId]);
    $stravaSubmissions = $ssStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignored) {}

$flash     = getFlash();
$pageTitle = 'Strava Dashboard';
$activePage = 'strava_dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
body:has(.sv-wrap) { background: #091113; }

.sv-wrap {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}
.sv-inner {
    position: relative; z-index: 1;
    max-width: 1060px; margin: 0 auto;
    padding: 2rem 1.25rem 5rem;
}
/* stat cards */
.sv-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.85rem;
    margin-bottom: 1.5rem;
}
.sv-stat {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 1.1rem 1.2rem;
    transition: border-color 0.2s;
}
.sv-stat:hover { border-color: rgba(252,76,2,0.3); }
.sv-stat-val {
    font-size: 1.6rem; font-weight: 800;
    color: #FC4C02; margin: 0 0 2px;
    line-height: 1;
}
.sv-stat-unit { font-size: 0.78rem; font-weight: 600; color: #8a8e97; }
.sv-stat-label { font-size: 0.68rem; color: #6b6e77; margin: 0.3rem 0 0; }

/* section card */
.sv-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    margin-bottom: 1.25rem;
}
.sv-card-title {
    font-size: 0.62rem; font-weight: 700;
    letter-spacing: 0.14em; text-transform: uppercase;
    color: rgba(252,76,2,0.65); margin: 0 0 1rem;
}

/* activity table */
.sv-table { width: 100%; border-collapse: collapse; font-size: 0.80rem; }
.sv-table th {
    text-align: left; padding: 0.45rem 0.7rem;
    background: rgba(255,255,255,0.03);
    color: #6b6e77; font-weight: 600; font-size: 0.65rem;
    letter-spacing: 0.06em; text-transform: uppercase;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sv-table td {
    padding: 0.55rem 0.7rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    color: #eeebe1; vertical-align: middle;
}
.sv-table tr:last-child td { border-bottom: none; }
.sv-table tr:hover td { background: rgba(255,255,255,0.02); }

/* type pill */
.sv-type-pill {
    display: inline-block; font-size: 0.65rem; font-weight: 700;
    padding: 2px 9px; border-radius: 999px;
    background: rgba(252,76,2,0.12); color: #FC4C02;
    border: 1px solid rgba(252,76,2,0.25);
}

/* sport breakdown bars */
.sv-bar-row { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.6rem; }
.sv-bar-label { font-size: 0.78rem; color: #eeebe1; min-width: 110px; }
.sv-bar-track {
    flex: 1; height: 7px; background: rgba(255,255,255,0.07);
    border-radius: 99px; overflow: hidden;
}
.sv-bar-fill { height: 100%; border-radius: 99px; background: #FC4C02; transition: width 0.6s; }
.sv-bar-num { font-size: 0.72rem; color: #8a8e97; min-width: 55px; text-align: right; }

/* period tabs */
.sv-tabs { display: flex; gap: 0.4rem; margin-bottom: 1.5rem; }
.sv-tab {
    padding: 0.4rem 1rem; border-radius: 9px; font-size: 0.78rem; font-weight: 600;
    text-decoration: none; border: 1px solid rgba(255,255,255,0.1); color: #6b6e77;
    background: rgba(255,255,255,0.03); transition: all 0.15s;
}
.sv-tab:hover { border-color: rgba(252,76,2,0.4); color: #FC4C02; }
.sv-tab--active { background: rgba(252,76,2,0.12); color: #FC4C02; border-color: rgba(252,76,2,0.4); }

/* challenge badge */
.sv-ch-status {
    display: inline-block; font-size: 0.65rem; font-weight: 700;
    padding: 2px 9px; border-radius: 999px;
}
</style>

<div class="sv-wrap">
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="sv-inner">

        <!-- Flash -->
        <?php if ($flash): ?>
        <div style="margin-bottom:1.25rem; padding:0.85rem 1.1rem; border-radius:10px; font-size:0.85rem;
                    background:<?= $flash['type']==='success' ? 'rgba(81,142,92,0.12)' : 'rgba(210,89,42,0.12)' ?>;
                    border:1px solid <?= $flash['type']==='success' ? 'rgba(81,142,92,0.3)' : 'rgba(210,89,42,0.3)' ?>;
                    color:<?= $flash['type']==='success' ? '#7ec98a' : '#e07a55' ?>;">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between;
                    flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
            <div style="display:flex; align-items:center; gap:14px;">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="#FC4C02">
                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                </svg>
                <div>
                    <p style="font-size:0.6rem; font-weight:700; letter-spacing:0.14em;
                               text-transform:uppercase; color:rgba(252,76,2,0.55); margin:0 0 2px;">
                        STRAVA DASHBOARD
                    </p>
                    <h1 style="font-size:1.45rem; font-weight:800; color:#eeebe1; margin:0;">
                        กิจกรรมออกกำลังกาย
                    </h1>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/pages/strava_connect.php"
               style="font-size:0.78rem; color:#FC4C02; text-decoration:none; padding:0.4rem 0.9rem;
                      border:1px solid rgba(252,76,2,0.3); border-radius:9px;
                      background:rgba(252,76,2,0.07); display:inline-flex; align-items:center; gap:6px;">
                <?= $connected ? '⚙ จัดการการเชื่อมต่อ' : '🔗 เชื่อมต่อ Strava' ?>
            </a>
        </div>

        <?php if (!$connected): ?>
        <!-- Not connected state -->
        <div class="sv-card" style="text-align:center; padding:3rem 2rem; border-color:rgba(252,76,2,0.2);">
            <svg viewBox="0 0 24 24" width="52" height="52" fill="rgba(252,76,2,0.3)" style="margin-bottom:1rem;">
                <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
            </svg>
            <p style="font-size:1rem; font-weight:700; color:#eeebe1; margin:0 0 0.5rem;">
                ยังไม่ได้เชื่อมต่อ Strava
            </p>
            <p style="font-size:0.82rem; color:#6b6e77; margin:0 0 1.5rem;">
                เชื่อมต่อบัญชี Strava เพื่อดูสถิติการออกกำลังกายของคุณ
            </p>
            <a href="<?= BASE_URL ?>/pages/strava_connect.php"
               style="display:inline-flex; align-items:center; gap:8px; padding:0.65rem 1.5rem;
                      background:#FC4C02; color:#fff; font-weight:700; font-size:0.9rem;
                      border-radius:10px; text-decoration:none;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="#fff">
                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                </svg>
                เชื่อมต่อ Strava
            </a>
        </div>

        <?php else: ?>

        <?php if ($fetchError): ?>
        <div style="margin-bottom:1rem; padding:0.85rem 1.1rem; border-radius:10px; font-size:0.82rem;
                    background:rgba(210,89,42,0.1); border:1px solid rgba(210,89,42,0.3); color:#e07a55;">
            ⚠ ไม่สามารถดึงข้อมูลจาก Strava ได้: <?= e($fetchError) ?>
        </div>
        <?php endif; ?>

        <!-- Period tabs -->
        <div class="sv-tabs">
            <?php foreach ($periodOptions as $p => $label): ?>
            <a href="?period=<?= $p ?>"
               class="sv-tab <?= $period === $p ? 'sv-tab--active' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Summary stats -->
        <?php
        $km       = $totalDist / 1000;
        $hrs      = floor($totalTime / 3600);
        $mins     = floor(($totalTime % 3600) / 60);
        $pace     = ($totalDist > 0) ? ($totalTime / 60) / ($totalDist / 1000) : 0;
        $avgDist  = count($activities) > 0 ? $totalDist / count($activities) / 1000 : 0;
        ?>
        <div class="sv-stat-grid">
            <div class="sv-stat" style="border-color:rgba(252,76,2,0.2);">
                <div class="sv-stat-val"><?= number_format($km, 2) ?></div>
                <div class="sv-stat-unit">กิโลเมตร</div>
                <div class="sv-stat-label">ระยะทางรวม <?= $periodOptions[$period] ?></div>
            </div>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= $hrs > 0 ? $hrs . '<span style="font-size:1rem">h</span> ' : '' ?><?= $mins ?><span style="font-size:1rem">m</span></div>
                <div class="sv-stat-unit">ชั่วโมง:นาที</div>
                <div class="sv-stat-label">เวลาเคลื่อนที่รวม</div>
            </div>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= count($activities) ?></div>
                <div class="sv-stat-unit">ครั้ง</div>
                <div class="sv-stat-label">จำนวนกิจกรรม</div>
            </div>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= number_format($totalElev, 0) ?></div>
                <div class="sv-stat-unit">เมตร</div>
                <div class="sv-stat-label">ความสูงสะสม</div>
            </div>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= number_format($avgDist, 2) ?></div>
                <div class="sv-stat-unit">กม./ครั้ง</div>
                <div class="sv-stat-label">ระยะทางเฉลี่ย</div>
            </div>
            <?php if ($pace > 0 && isset($countByType['Run'])): ?>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= floor($pace) ?>:<?= str_pad((int)(($pace - floor($pace)) * 60), 2, '0', STR_PAD_LEFT) ?></div>
                <div class="sv-stat-unit">นาที/กม.</div>
                <div class="sv-stat-label">เพซเฉลี่ย (วิ่งรวม)</div>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

            <!-- Sport breakdown -->
            <div class="sv-card">
                <p class="sv-card-title">ประเภทกิจกรรม</p>
                <?php if (empty($countByType)): ?>
                <p style="font-size:0.8rem; color:#6b6e77;">ไม่มีข้อมูล</p>
                <?php else:
                $maxCount = max($countByType);
                foreach ($countByType as $stype => $cnt):
                    $pct = $maxCount > 0 ? round($cnt / $maxCount * 100) : 0;
                    $d   = number_format(($distByType[$stype] ?? 0) / 1000, 1);
                ?>
                <div class="sv-bar-row">
                    <span class="sv-bar-label"><?= e($stype) ?></span>
                    <div class="sv-bar-track">
                        <div class="sv-bar-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <span class="sv-bar-num"><?= $cnt ?> ครั้ง · <?= $d ?> กม.</span>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Daily activity heatmap (simple) -->
            <div class="sv-card">
                <p class="sv-card-title">กิจกรรมรายวัน <?= $periodOptions[$period] ?>ล่าสุด</p>
                <?php if (empty($byDate)): ?>
                <p style="font-size:0.8rem; color:#6b6e77;">ไม่มีข้อมูล</p>
                <?php else:
                // Fill all dates in range
                $allDates = [];
                for ($d = 0; $d < (int)$period; $d++) {
                    $allDates[] = date('Y-m-d', strtotime("-{$d} days"));
                }
                $allDates = array_reverse($allDates);
                $maxDistDay = max(array_column($byDate, 'dist') ?: [0]);
                echo '<div style="display:flex; flex-wrap:wrap; gap:4px;">';
                foreach ($allDates as $dd):
                    $has = isset($byDate[$dd]);
                    $opacity = $has && $maxDistDay > 0
                        ? max(0.15, min(1, ($byDate[$dd]['dist'] / $maxDistDay)))
                        : 0;
                    $title = $has
                        ? e($dd . ': ' . number_format($byDate[$dd]['dist']/1000, 2) . ' กม. ' . $byDate[$dd]['count'] . ' ครั้ง')
                        : e($dd . ': ไม่มีกิจกรรม');
                ?>
                <div title="<?= $title ?>"
                     style="width:14px; height:14px; border-radius:3px;
                            background:<?= $has ? 'rgba(252,76,2,' . number_format($opacity, 2) . ')' : 'rgba(255,255,255,0.05)' ?>;
                            border:1px solid <?= $has ? 'rgba(252,76,2,0.4)' : 'rgba(255,255,255,0.04)' ?>;
                            cursor:default;">
                </div>
                <?php endforeach;
                echo '</div>';
                echo '<p style="font-size:0.65rem; color:#6b6e77; margin:0.6rem 0 0;">■ สีเข้ม = ระยะทางมาก | hover เพื่อดูรายละเอียด</p>';
                endif; ?>
            </div>
        </div>

        <!-- Activities list -->
        <div class="sv-card">
            <p class="sv-card-title">รายการกิจกรรมทั้งหมด (<?= count($activities) ?> รายการ)</p>
            <?php if (empty($activities)): ?>
            <p style="font-size:0.82rem; color:#6b6e77; text-align:center; padding:1.5rem 0;">
                ไม่พบกิจกรรมใน <?= $periodOptions[$period] ?>ที่ผ่านมา<br>
                <span style="font-size:0.72rem;">ตรวจสอบว่ากิจกรรมใน Strava ตั้งค่าเป็น "Everyone" หรือ "Followers"</span>
            </p>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="sv-table">
                <thead>
                    <tr>
                        <th>ชื่อกิจกรรม</th>
                        <th>ประเภท</th>
                        <th>ระยะทาง</th>
                        <th>เวลา</th>
                        <th>Elevation</th>
                        <th>วันที่</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activities as $a):
                    $stype = $a['sport_type'] ?? $a['type'] ?? '-';
                    $dist  = (float)($a['distance']   ?? 0);
                    $mtime = (int)($a['moving_time']   ?? 0);
                    $elev  = (float)($a['total_elevation_gain'] ?? 0);
                    $dateRaw = $a['start_date_local'] ?? '';
                    $dateDisp = $dateRaw ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
                    $actUrl = 'https://www.strava.com/activities/' . ($a['id'] ?? '');
                ?>
                <tr>
                    <td style="font-weight:600; max-width:220px;">
                        <a href="<?= e($actUrl) ?>" target="_blank" rel="noopener"
                           style="color:#eeebe1; text-decoration:none;"
                           onmouseover="this.style.color='#FC4C02'"
                           onmouseout="this.style.color='#eeebe1'">
                            <?= e($a['name'] ?? '-') ?>
                            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor"
                                 stroke-width="2.5" style="opacity:0.4; margin-left:3px; vertical-align:middle;">
                                <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    </td>
                    <td><span class="sv-type-pill"><?= e($stype) ?></span></td>
                    <td style="color:#f8e769; font-weight:700;"><?= number_format($dist/1000, 2) ?> <span style="color:#6b6e77; font-weight:400;">กม.</span></td>
                    <td style="color:#4f8b98;">
                        <?= $mtime >= 3600 ? floor($mtime/3600) . 'h ' : '' ?><?= floor(($mtime % 3600)/60) ?> <span style="color:#6b6e77;">นาที</span>
                    </td>
                    <td style="color:#518e5c;"><?= number_format($elev, 0) ?> <span style="color:#6b6e77;">ม.</span></td>
                    <td style="color:#6b6e77; font-size:0.75rem;"><?= e($dateDisp) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Strava challenge history -->
        <?php if (!empty($stravaSubmissions)): ?>
        <div class="sv-card">
            <p class="sv-card-title">ประวัติภารกิจ Strava ของฉัน</p>
            <div style="overflow-x:auto;">
            <table class="sv-table">
                <thead>
                    <tr>
                        <th>ภารกิจ</th>
                        <th>เงื่อนไข</th>
                        <th>ผลลัพธ์</th>
                        <th>Token</th>
                        <th>วันที่ส่ง</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stravaSubmissions as $s):
                    $sc = !empty($s['strava_condition']) ? (json_decode($s['strava_condition'], true) ?? []) : [];
                    $statusColor = match($s['status']) {
                        'auto_approved' => ['bg'=>'rgba(81,142,92,0.15)','color'=>'#7ec98a','border'=>'rgba(81,142,92,0.3)'],
                        'rejected'      => ['bg'=>'rgba(210,89,42,0.12)','color'=>'#e07a55','border'=>'rgba(210,89,42,0.3)'],
                        default         => ['bg'=>'rgba(218,185,55,0.10)','color'=>'#dab937','border'=>'rgba(218,185,55,0.25)'],
                    };
                    $statusLabel = match($s['status']) {
                        'auto_approved' => '✅ ผ่าน',
                        'rejected'      => '❌ ไม่ผ่าน',
                        default         => $s['status'],
                    };
                    // Audit activity from photo_path
                    $audit = !empty($s['photo_path']) ? (json_decode($s['photo_path'], true) ?? []) : [];
                ?>
                <tr>
                    <td style="font-weight:600;"><?= e($s['title']) ?></td>
                    <td style="font-size:0.75rem; color:#8a8e97;">
                        <?php if ($sc): ?>
                        <?= e($sc['sport_type'] ?? '-') ?>
                        <?php if (!empty($sc['min_distance'])): ?>· ≥<?= number_format($sc['min_distance']/1000, 1) ?> กม.<?php endif; ?>
                        <?php if (!empty($sc['min_moving_time'])): ?>· ≥<?= round($sc['min_moving_time']/60) ?> นาที<?php endif; ?>
                        <?php else: ?> - <?php endif; ?>
                        <?php if ($s['status'] === 'auto_approved' && !empty($audit['name'])): ?>
                        <br><span style="color:#FC4C02; font-size:0.7rem;">📍 <?= e(mb_substr($audit['name'], 0, 40)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="sv-ch-status"
                              style="background:<?= $statusColor['bg'] ?>;color:<?= $statusColor['color'] ?>;
                                     border:1px solid <?= $statusColor['border'] ?>;">
                            <?= $statusLabel ?>
                        </span>
                    </td>
                    <td style="font-weight:700; color:<?= (int)$s['token_awarded'] > 0 ? '#f8e769' : '#6b6e77' ?>;">
                        <?= (int)$s['token_awarded'] > 0 ? '+' . (int)$s['token_awarded'] : '-' ?>
                    </td>
                    <td style="color:#6b6e77; font-size:0.75rem;">
                        <?= $s['submitted_at'] ? date('d/m/y H:i', strtotime((string)$s['submitted_at'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // connected ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
