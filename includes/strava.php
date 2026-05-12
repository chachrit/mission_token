<?php
/**
 * includes/strava.php
 * Strava API helper functions
 * Requires: config/app.php, config/strava.php, config/database.php
 */

// ── Check if employee has connected Strava ────────────────────
function isStravaConnected(int $employeeId): bool
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT strava_athlete_id FROM employees
        WHERE employee_id = ? AND strava_athlete_id IS NOT NULL
    ");
    $stmt->execute([$employeeId]);
    return (bool)$stmt->fetchColumn();
}

// ── Get stored Strava token row ───────────────────────────────
function getStravaTokenRow(int $employeeId): ?array
{
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("
            SELECT strava_athlete_id, strava_access_token,
                   strava_refresh_token, strava_token_expires_at, strava_scope
            FROM   employees
            WHERE  employee_id = ?
        ");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (\PDOException $e) {
        // Strava columns may not exist yet (run strava_migration.sql)
        error_log('[Strava] getStravaTokenRow: ' . $e->getMessage());
        return null;
    }
}

// ── Refresh token if it expires within 10 minutes ────────────
// Returns valid access_token string, or null on failure
function refreshStravaTokenIfNeeded(int $employeeId): ?string
{
    require_once __DIR__ . '/../config/strava.php';

    $row = getStravaTokenRow($employeeId);
    if (!$row || empty($row['strava_access_token'])) {
        return null;
    }

    $expiresAt = (int)$row['strava_token_expires_at'];
    // Still valid for more than 10 minutes
    if ($expiresAt > time() + 600) {
        return (string)$row['strava_access_token'];
    }

    // Need to refresh
    $ch = curl_init(STRAVA_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => STRAVA_CLIENT_ID,
            'client_secret' => STRAVA_CLIENT_SECRET,
            'grant_type'    => 'refresh_token',
            'refresh_token' => (string)$row['strava_refresh_token'],
        ]),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$body) {
        error_log('[Strava] refresh cURL error: ' . $err);
        return null;
    }

    $data = json_decode($body, true);
    if (empty($data['access_token'])) {
        error_log('[Strava] refresh token error: ' . $body);
        return null;
    }

    // Save new tokens to DB
    $pdo       = getDB();
    $newExpiry = (int)($data['expires_at'] ?? (time() + 21600));
    $refreshStmt = $pdo->prepare("
        UPDATE employees
        SET strava_access_token     = ?,
            strava_refresh_token    = ?,
            strava_token_expires_at = ?
        WHERE employee_id = ?
    ");
    $refreshStmt->bindValue(1, (string)$data['access_token'], PDO::PARAM_STR);
    $refreshStmt->bindValue(2, (string)($data['refresh_token'] ?? $row['strava_refresh_token']), PDO::PARAM_STR);
    $refreshStmt->bindValue(3, $newExpiry, PDO::PARAM_INT);
    $refreshStmt->bindValue(4, $employeeId, PDO::PARAM_INT);
    $refreshStmt->execute();

    return (string)$data['access_token'];
}

// ── GET from Strava API (authenticated) ──────────────────────
// Returns decoded JSON array, or null on error
function callStravaAPI(int $employeeId, string $endpoint): ?array
{
    $token = refreshStravaTokenIfNeeded($employeeId);
    if (!$token) {
        return null;
    }

    require_once __DIR__ . '/../config/strava.php';
    $url = STRAVA_API_BASE . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("[Strava] API cURL error on {$endpoint}: {$err}");
        return null;
    }
    if ($httpCode !== 200) {
        error_log("[Strava] API HTTP {$httpCode} on {$endpoint}: {$body}");
        return null;
    }

    return json_decode($body, true) ?? null;
}

// ── Fetch activities within a time range (paginated up to 5 pages) ──
function fetchStravaActivities(int $employeeId, int $afterTs, int $beforeTs): array
{
    $all  = [];
    $page = 1;
    while ($page <= 5) {
        $params = http_build_query([
            'after'    => $afterTs,
            'before'   => $beforeTs,
            'per_page' => 50,
            'page'     => $page,
        ]);
        $batch = callStravaAPI($employeeId, "/athlete/activities?{$params}");
        if (!is_array($batch) || empty($batch)) {
            break;
        }
        $all = array_merge($all, $batch);
        if (count($batch) < 50) {
            break; // last page
        }
        $page++;
    }
    return $all;
}

// ── Check if any activity matches challenge condition ─────────
// Returns the matching activity array, or false if none found
function checkStravaCondition(int $employeeId, array $condition, int $afterTs, int $beforeTs)
{
    $activities = fetchStravaActivities($employeeId, $afterTs, $beforeTs);
    if (empty($activities)) {
        return false;
    }

    $sportType  = strtolower($condition['sport_type']      ?? 'run');
    $minDist    = (float)($condition['min_distance']        ?? 0);  // metres
    $minMovTime = (int)($condition['min_moving_time']       ?? 0);  // seconds
    $minElev    = (float)($condition['min_elevation']       ?? 0);  // metres

    foreach ($activities as $act) {
        // sport_type match (Strava v3 uses sport_type field)
        $actType = strtolower($act['sport_type'] ?? $act['type'] ?? '');
        if ($actType !== $sportType) {
            continue;
        }
        if ($minDist > 0 && ((float)($act['distance'] ?? 0)) < $minDist) {
            continue;
        }
        if ($minMovTime > 0 && ((int)($act['moving_time'] ?? 0)) < $minMovTime) {
            continue;
        }
        if ($minElev > 0 && ((float)($act['total_elevation_gain'] ?? 0)) < $minElev) {
            continue;
        }
        return $act; // first match
    }

    return false;
}

// ── Disconnect Strava (NULL out all columns) ──────────────────
function disconnectStrava(int $employeeId): void
{
    $pdo = getDB();
    $pdo->prepare("
        UPDATE employees
        SET strava_athlete_id       = NULL,
            strava_access_token     = NULL,
            strava_refresh_token    = NULL,
            strava_token_expires_at = NULL,
            strava_scope            = NULL
        WHERE employee_id = ?
    ")->execute([$employeeId]);
}

// ── Build Strava OAuth authorization URL ─────────────────────
function stravaAuthURL(string $state): string
{
    require_once __DIR__ . '/../config/strava.php';
    return STRAVA_AUTH_URL . '?' . http_build_query([
        'client_id'     => STRAVA_CLIENT_ID,
        'redirect_uri'  => STRAVA_REDIRECT_URI,
        'response_type' => 'code',
        'approval_prompt' => 'auto',
        'scope'         => STRAVA_SCOPE,
        'state'         => $state,
    ]);
}
