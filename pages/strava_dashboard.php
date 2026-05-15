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

// ── Yearly running stats — 500 km goal (session-cached 30 min) ──
$yearlyKm        = 0.0;
$yearlyRunCount  = 0;
$yearlyLongest   = 0.0;
$yearlyAvgKm     = 0.0;
$yearGoalKm      = 500;
$yearProgressPct = 0;
$yearStatsLoaded = false;
$yearlyMissionsDone = 0;

if ($connected) {
    $cacheKey = 'strava_ystats_' . $employeeId;
    $cached   = $_SESSION[$cacheKey] ?? null;
    if ($cached && isset($cached['ts']) && (time() - (int)$cached['ts']) < 1800) {
        $yearlyKm       = (float)$cached['ykm'];
        $yearlyRunCount = (int)$cached['rcnt'];
        $yearlyLongest  = (float)$cached['lkm'];
        $yearStatsLoaded = true;
    } else {
        try {
            $yearStart = mktime(0, 0, 0, 1, 1, (int)date('Y'));
            $yearActs  = fetchStravaActivities($employeeId, $yearStart, time());
            foreach ($yearActs as $act) {
                $t = strtolower($act['sport_type'] ?? $act['type'] ?? '');
                if (!in_array($t, ['run', 'virtualrun', 'trailrun'], true)) continue;
                $kmt = ($act['distance'] ?? 0) / 1000;
                $yearlyKm += $kmt;
                $yearlyRunCount++;
                if ($kmt > $yearlyLongest) $yearlyLongest = $kmt;
            }
            $yearlyKm      = round($yearlyKm, 1);
            $yearlyLongest = round($yearlyLongest, 1);
            $_SESSION[$cacheKey] = ['ts' => time(), 'ykm' => $yearlyKm, 'rcnt' => $yearlyRunCount, 'lkm' => $yearlyLongest];
            $yearStatsLoaded = true;
        } catch (Throwable $ignored) {}
    }
    $yearlyAvgKm     = $yearlyRunCount > 0 ? round($yearlyKm / $yearlyRunCount, 1) : 0;
    $yearProgressPct = $yearGoalKm > 0 ? min(100, round($yearlyKm / $yearGoalKm * 100, 1)) : 0;

    // Strava missions done
    try {
        $pdo2 = getDB();
        $scStmt = $pdo2->prepare("
            SELECT COUNT(*) AS done_count
            FROM   challenge_submissions cs
            JOIN   challenges c ON c.challenge_id = cs.challenge_id
            WHERE  cs.employee_id = ?
              AND  c.type = 'strava'
              AND  cs.status IN ('approved', 'auto_approved')
        ");
        $scStmt->execute([$employeeId]);
        $yearlyMissionsDone = (int)($scStmt->fetch()['done_count'] ?? 0);
    } catch (Throwable $ignored) {}
}

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


<div class="sv-wrap">
    <div class="ch-aurora-blob ch-aurora-blob--1" aria-hidden="true"></div>
    <div class="ch-aurora-blob ch-aurora-blob--2" aria-hidden="true"></div>

    <div class="sv-inner">

        <!-- Flash -->
        <?php if ($flash): ?>
        <div class="sv-flash <?= $flash['type']==='success' ? 'sv-flash-success' : 'sv-flash-error' ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="sd-u001">
            <div class="sd-u002">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="#FC4C02">
                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                </svg>
                <div>
                    <p class="sd-u003">
                        STRAVA DASHBOARD
                    </p>
                    <h1 class="sd-u004">
                        กิจกรรมออกกำลังกาย
                    </h1>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/pages/strava_connect.php"
                    class="sv-manage-link">
                <?php if ($connected): ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h.1a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8v.1a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.5 1z"/>
                    </svg>
                    จัดการการเชื่อมต่อ
                <?php else: ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/>
                        <path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 1 0 7 7l1-1"/>
                    </svg>
                    เชื่อมต่อ Strava
                <?php endif; ?>
            </a>
        </div>

        <?php if (!$connected): ?>
        <!-- Not connected state -->
        <div class="sv-card sd-u005">
            <svg class="sd-u006" viewBox="0 0 24 24" width="52" height="52" fill="rgba(252,76,2,0.3)">
                <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
            </svg>
            <p class="sd-u007">
                ยังไม่ได้เชื่อมต่อ Strava
            </p>
            <p class="sd-u008">
                เชื่อมต่อบัญชี Strava เพื่อดูสถิติการออกกำลังกายของคุณ
            </p>
            <a href="<?= BASE_URL ?>/pages/strava_connect.php"
               class="sv-connect-btn-inline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="#fff">
                    <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                </svg>
                เชื่อมต่อ Strava
            </a>
        </div>

        <?php else: ?>

        <?php if ($fetchError): ?>
        <div class="sd-u009">
            <span class="sd-u010">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M12 9v4" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"/>
                    <path d="M10.3 3.9L2.5 18a2 2 0 0 0 1.8 3h15.4a2 2 0 0 0 1.8-3L13.7 3.9a2 2 0 0 0-3.4 0z" stroke-width="2"/>
                </svg>
                ไม่สามารถดึงข้อมูลจาก Strava ได้: <?= e($fetchError) ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- ── YEARLY RUNNING TRACK ── -->
        <?php
            $thaiYear = (int)date('Y') + 543;
            // Count active strava missions (not yet done)
            $stravaActiveCount = 0;
            try {
                $acStmt = getDB()->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM   challenges c
                    WHERE  c.type = 'strava' AND c.is_active = 1
                      AND  (c.end_date IS NULL OR c.end_date >= GETDATE())
                      AND  c.challenge_id NOT IN (
                          SELECT challenge_id FROM challenge_submissions
                          WHERE  employee_id = ? AND status IN ('approved','auto_approved')
                      )
                ");
                $acStmt->execute([$employeeId]);
                $stravaActiveCount = (int)($acStmt->fetch()['cnt'] ?? 0);
            } catch (Throwable $ignored) {}
        ?>
        <div class="ds-strava-card sd-u011">
            <div class="ds-strava-topbar">
                <div class="ds-strava-brand">
                    <div class="ds-strava-brand-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>
                        </svg>
                    </div>
                    <div>
                        <p class="ds-strava-brand-eyebrow">STRAVA · RUNNING LOG</p>
                        <p class="ds-strava-brand-title">เส้นทางแห่งปี <?= $thaiYear ?></p>
                    </div>
                </div>
                <div class="ds-strava-topbar-right">
                    <?php if ($stravaActiveCount > 0): ?>
                    <span class="ds-strava-mission-badge"><?= $stravaActiveCount ?> ภารกิจรออยู่</span>
                    <?php endif; ?>
                    <span class="ds-strava-connected-badge"><span class="ds-strava-dot"></span>&nbsp;เชื่อมต่อแล้ว</span>
                </div>
            </div>

            <div class="ds-strava-track-wrap">
                <div class="ds-strava-track-header">
                    <span class="ds-strava-track-km">
                        <?php if ($yearStatsLoaded): ?>
                            <?= number_format($yearlyKm, 1) ?> <span class="sd-u012">/ <?= $yearGoalKm ?> km</span>
                            <?php if ($yearProgressPct >= 100): ?>
                                <span class="ds-strava-goal-badge">สำเร็จแล้ว!</span>
                            <?php else: ?>
                                <span class="ds-strava-pct-badge"><?= $yearProgressPct ?>%</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="sd-u013">กำลังโหลด...</span>
                        <?php endif; ?>
                    </span>
                    <span class="ds-strava-track-goal">เป้าหมาย <?= $yearGoalKm ?> กม./ปี</span>
                </div>
                <div class="ds-strava-track-bar-wrap">
                    <div class="ds-strava-track-bg"></div>
                    <div class="ds-strava-track-fill" id="ds-track-fill"
                         data-progress="<?= $yearProgressPct ?>"></div>
                    <div class="ds-strava-runner" id="ds-runner"
                         data-progress="<?= $yearProgressPct ?>">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="14" cy="5" r="2" stroke-width="2"/>
                            <path d="M7 10l4-2 3 1 2 3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10 13l-2 4" stroke-width="2" stroke-linecap="round"/>
                            <path d="M13 13l4 5" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="ds-strava-finish">
                        <div class="ds-strava-finish-flag">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M5 3v18" stroke-width="2"/>
                                <path d="M5 4h10l-2 3 2 3H5" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="ds-strava-finish-line"></div>
                    </div>
                </div>
                <div class="ds-strava-track-labels">
                    <span>0 km</span>
                    <span>125 km</span>
                    <span>250 km</span>
                    <span>375 km</span>
                    <span><?= $yearGoalKm ?> km</span>
                </div>
            </div>

            <div class="ds-strava-stats-row">
                <div class="ds-strava-stat2">
                    <span class="ds-strava-stat2-val sd-u014"><?= $yearStatsLoaded ? number_format($yearlyKm, 1) : '—' ?></span>
                    <span class="ds-strava-stat2-lbl">กม. รวมปีนี้</span>
                </div>
                <div class="ds-strava-stat2-div"></div>
                <div class="ds-strava-stat2">
                    <span class="ds-strava-stat2-val"><?= $yearStatsLoaded ? $yearlyRunCount : '—' ?></span>
                    <span class="ds-strava-stat2-lbl">ครั้งที่วิ่ง</span>
                </div>
                <div class="ds-strava-stat2-div"></div>
                <div class="ds-strava-stat2">
                    <span class="ds-strava-stat2-val"><?= $yearStatsLoaded && $yearlyLongest > 0 ? number_format($yearlyLongest, 1) : '—' ?></span>
                    <span class="ds-strava-stat2-lbl">ยาวที่สุด (กม.)</span>
                </div>
                <div class="ds-strava-stat2-div"></div>
                <div class="ds-strava-stat2">
                    <span class="ds-strava-stat2-val"><?= $yearStatsLoaded && $yearlyAvgKm > 0 ? number_format($yearlyAvgKm, 1) : '—' ?></span>
                    <span class="ds-strava-stat2-lbl">เฉลี่ย/ครั้ง (กม.)</span>
                </div>
                <div class="ds-strava-stat2-div"></div>
                <div class="ds-strava-stat2">
                    <span class="ds-strava-stat2-val"><?= $yearlyMissionsDone ?></span>
                    <span class="ds-strava-stat2-lbl">ภารกิจ Strava<br>สำเร็จ</span>
                </div>
            </div>
        </div><!-- /ds-strava-card -->

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
            <div class="sv-stat sd-u015">
                <div class="sv-stat-val"><?= number_format($km, 2) ?></div>
                <div class="sv-stat-unit">กิโลเมตร</div>
                <div class="sv-stat-label">ระยะทางรวม <?= $periodOptions[$period] ?></div>
            </div>
            <div class="sv-stat">
                <div class="sv-stat-val"><?= $hrs > 0 ? $hrs . '<span class="sd-u016">h</span> ' : '' ?><?= $mins ?><span class="sd-u016">m</span></div>
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

        <div class="sd-u017">

            <!-- Sport breakdown -->
            <div class="sv-card">
                <p class="sv-card-title">ประเภทกิจกรรม</p>
                <?php if (empty($countByType)): ?>
                <p class="sd-u013">ไม่มีข้อมูล</p>
                <?php else:
                $maxCount = max($countByType);
                foreach ($countByType as $stype => $cnt):
                    $pct = $maxCount > 0 ? round($cnt / $maxCount * 100) : 0;
                    $d   = number_format(($distByType[$stype] ?? 0) / 1000, 1);
                ?>
                <div class="sv-bar-row">
                    <span class="sv-bar-label"><?= e($stype) ?></span>
                    <div class="sv-bar-track">
                        <div class="sv-bar-fill" data-width="<?= $pct ?>"></div>
                    </div>
                    <span class="sv-bar-num"><?= $cnt ?> ครั้ง · <?= $d ?> กม.</span>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Daily activity heatmap (simple) -->
            <div class="sv-card">
                <p class="sv-card-title">กิจกรรมรายวัน <?= $periodOptions[$period] ?>ล่าสุด</p>
                <?php if (empty($byDate)): ?>
                <p class="sd-u013">ไม่มีข้อมูล</p>
                <?php else:
                // Fill all dates in range
                $allDates = [];
                for ($d = 0; $d < (int)$period; $d++) {
                    $allDates[] = date('Y-m-d', strtotime("-{$d} days"));
                }
                $allDates = array_reverse($allDates);
                $maxDistDay = max(array_column($byDate, 'dist') ?: [0]);
                echo '<div>';
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
                     class="sv-heat-cell <?= $has ? 'sv-heat-cell-active' : 'sv-heat-cell-empty' ?>"
                     data-opacity="<?= $has ? number_format($opacity, 2) : '0' ?>">
                </div>
                <?php endforeach;
                echo '</div>';
                echo '<p class="sd-u019">■ สีเข้ม = ระยะทางมาก | hover เพื่อดูรายละเอียด</p>';
                endif; ?>
            </div>
        </div>

        <!-- Activities list -->
        <div class="sv-card">
            <p class="sv-card-title">รายการกิจกรรมทั้งหมด (<?= count($activities) ?> รายการ)</p>
            <?php if (empty($activities)): ?>
            <p class="sd-u020">
                ไม่พบกิจกรรมใน <?= $periodOptions[$period] ?>ที่ผ่านมา<br>
                <span class="sd-u021">ตรวจสอบว่ากิจกรรมใน Strava ตั้งค่าเป็น "Everyone" หรือ "Followers"</span>
            </p>
            <?php else: ?>
            <div class="sd-u022">
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
                    <td class="sd-u023">
                        <a href="<?= e($actUrl) ?>" target="_blank" rel="noopener"
                           class="sv-activity-link sv-activity-link-inline">
                            <?= e($a['name'] ?? '-') ?>
                            <svg class="sd-u024" viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor"
                                 stroke-width="2.5">
                                <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    </td>
                    <td><span class="sv-type-pill"><?= e($stype) ?></span></td>
                    <td class="sd-u025"><?= number_format($dist/1000, 2) ?> <span class="sd-u026">กม.</span></td>
                    <td class="sd-u027">
                        <?= $mtime >= 3600 ? floor($mtime/3600) . 'h ' : '' ?><?= floor(($mtime % 3600)/60) ?> <span class="sd-u028">นาที</span>
                    </td>
                    <td class="sd-u029"><?= number_format($elev, 0) ?> <span class="sd-u028">ม.</span></td>
                    <td class="sd-u030"><?= e($dateDisp) ?></td>
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
            <div class="sd-u022">
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
                    $statusLabel = match($s['status']) {
                        'auto_approved' => 'ผ่าน',
                        'rejected'      => 'ไม่ผ่าน',
                        default         => $s['status'],
                    };
                    // Audit activity from photo_path
                    $audit = !empty($s['photo_path']) ? (json_decode($s['photo_path'], true) ?? []) : [];
                ?>
                <tr>
                    <td class="sd-u031"><?= e($s['title']) ?></td>
                    <td class="sd-u032">
                        <?php if ($sc): ?>
                        <?= e($sc['sport_type'] ?? '-') ?>
                        <?php if (!empty($sc['min_distance'])): ?>· ≥<?= number_format($sc['min_distance']/1000, 1) ?> กม.<?php endif; ?>
                        <?php if (!empty($sc['min_moving_time'])): ?>· ≥<?= round($sc['min_moving_time']/60) ?> นาที<?php endif; ?>
                        <?php else: ?> - <?php endif; ?>
                        <?php if ($s['status'] === 'auto_approved' && !empty($audit['name'])): ?>
                        <br><span class="sd-u033"><?= e(mb_substr($audit['name'], 0, 40)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="sv-ch-status sv-ch-status-<?= e($s['status']) ?>">
                            <?= $statusLabel ?>
                        </span>
                    </td>
                    <td class="sv-token-cell <?= (int)$s['token_awarded'] > 0 ? 'sv-token-cell-pos' : 'sv-token-cell-zero' ?>">
                        <?= (int)$s['token_awarded'] > 0 ? '+' . (int)$s['token_awarded'] : '-' ?>
                    </td>
                    <td class="sd-u030">
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

<script>
(function () {
    var trackFill = document.getElementById('ds-track-fill');
    var runner    = document.getElementById('ds-runner');
    if (trackFill && runner) {
        var pct = parseFloat(trackFill.dataset.progress || '0');
        pct = Math.min(pct, 100);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                trackFill.style.width = pct + '%';
                var runnerPct = Math.max(0, Math.min(pct, 96));
                runner.style.left    = 'calc(' + runnerPct + '% - 12px)';
                runner.style.opacity = '1';
            });
        });
    }

    document.querySelectorAll('.sv-bar-fill[data-width]').forEach(function (el) {
        el.style.width = (el.dataset.width || '0') + '%';
    });

    document.querySelectorAll('.sv-heat-cell.sv-heat-cell-active[data-opacity]').forEach(function (el) {
        var op = parseFloat(el.dataset.opacity || '0');
        if (Number.isNaN(op)) op = 0;
        el.style.background = 'rgba(252,76,2,' + op.toFixed(2) + ')';
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
