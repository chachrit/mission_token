<?php
/**
 * strava_callback.php
 * Strava OAuth 2.0 callback — exchanges code for tokens and saves to DB
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/strava.php';
require_once __DIR__ . '/config/strava.php';

$employeeId = (int)$_SESSION['employee_id'];

// ── Handle user denying access ─────────────────────────────
if (isset($_GET['error'])) {
    setFlash('error', 'คุณยกเลิกการเชื่อมต่อ Strava');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Validate CSRF state ────────────────────────────────────
$receivedState = (string)($_GET['state'] ?? '');
$expectedState = (string)($_SESSION['strava_oauth_state'] ?? '');
unset($_SESSION['strava_oauth_state']);

if ($receivedState === '' || $receivedState !== $expectedState) {
    setFlash('error', 'คำขอไม่ถูกต้อง (state mismatch) กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Exchange authorization code for tokens ─────────────────
$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    setFlash('error', 'ไม่ได้รับ authorization code จาก Strava');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$ch = curl_init(STRAVA_TOKEN_URL);
$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => STRAVA_CLIENT_ID,
        'client_secret' => STRAVA_CLIENT_SECRET,
        'code'          => $code,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
];
// On Windows servers without bundled CA bundle, point to cacert.pem
$caBundlePaths = [
    __DIR__ . '/config/cacert.pem',
    'C:/xampp/apache/conf/ssl.crt/cacert.pem',
    'C:/xampp/php/extras/ssl/cacert.pem',
];
foreach ($caBundlePaths as $caPath) {
    if (file_exists($caPath)) {
        $curlOpts[CURLOPT_CAINFO] = $caPath;
        break;
    }
}
curl_setopt_array($ch, $curlOpts);
$body = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($err || !$body) {
    error_log('[Strava callback] cURL error: ' . $err);
    setFlash('error', 'เชื่อมต่อ Strava API ไม่ได้ กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$data = json_decode($body, true);

// Strava returns error as JSON with "message" key
if (!empty($data['message']) || empty($data['access_token'])) {
    $msg = $data['message'] ?? 'Unknown error';
    error_log('[Strava callback] token error: ' . $body);
    setFlash('error', 'Strava ปฏิเสธคำขอ: ' . htmlspecialchars($msg));
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Validate scope ─────────────────────────────────────────
$grantedScope = (string)($data['scope'] ?? '');
if (strpos($grantedScope, 'activity:read') === false) {
    setFlash('error', 'กรุณาอนุญาต "View data about your activities" เพื่อใช้ฟีเจอร์นี้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$athleteId    = (int)($data['athlete']['id'] ?? 0);
$accessToken  = (string)$data['access_token'];
$refreshToken = (string)$data['refresh_token'];
$expiresAt    = (int)$data['expires_at'];

if ($athleteId === 0) {
    setFlash('error', 'ไม่สามารถดึงข้อมูลนักกีฬาจาก Strava ได้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Check duplicate + Save tokens to DB ───────────────────
$pdo = getDB();
try {
    $dup = $pdo->prepare("
        SELECT employee_id FROM employees
        WHERE strava_athlete_id = ? AND employee_id <> ?
    ");
    $dup->execute([$athleteId, $employeeId]);
    if ($dup->fetch()) {
        setFlash('error', 'บัญชี Strava นี้ถูกเชื่อมต่อกับพนักงานท่านอื่นแล้ว');
        redirect(BASE_URL . '/pages/strava_connect.php');
    }

    $saveStmt = $pdo->prepare("
        UPDATE employees
        SET strava_athlete_id       = ?,
            strava_access_token     = ?,
            strava_refresh_token    = ?,
            strava_token_expires_at = ?,
            strava_scope            = ?
        WHERE employee_id = ?
    ");
    $saveStmt->bindValue(1, $athleteId,    PDO::PARAM_INT);
    $saveStmt->bindValue(2, $accessToken,  PDO::PARAM_STR);
    $saveStmt->bindValue(3, $refreshToken, PDO::PARAM_STR);
    $saveStmt->bindValue(4, $expiresAt,    PDO::PARAM_INT);
    $saveStmt->bindValue(5, $grantedScope, PDO::PARAM_STR);
    $saveStmt->bindValue(6, $employeeId,   PDO::PARAM_INT);
    $saveStmt->execute();
} catch (Throwable $e) {
    error_log('[Strava callback] DB error: ' . $e->getMessage());
    setFlash('error', 'บันทึกข้อมูล Strava ไม่สำเร็จ: ' . htmlspecialchars($e->getMessage()));
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$athleteName = trim(($data['athlete']['firstname'] ?? '') . ' ' . ($data['athlete']['lastname'] ?? ''));
setFlash('success', 'เชื่อมต่อ Strava สำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($athleteName));
redirect(BASE_URL . '/pages/strava_connect.php');

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/strava.php';
require_once __DIR__ . '/config/strava.php';

error_log('[Strava callback] START — session_id=' . session_id() . ' employee_id=' . ($_SESSION['employee_id'] ?? 'NONE') . ' state=' . ($_GET['state'] ?? '') . ' code_len=' . strlen($_GET['code'] ?? ''));

$employeeId = (int)$_SESSION['employee_id'];

// ── Handle user denying access ─────────────────────────────
if (isset($_GET['error'])) {
    error_log('[Strava callback] user denied access: ' . $_GET['error']);
    setFlash('error', 'คุณยกเลิกการเชื่อมต่อ Strava');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Validate CSRF state ────────────────────────────────────
$receivedState = (string)($_GET['state'] ?? '');
$expectedState = (string)($_SESSION['strava_oauth_state'] ?? '');
error_log('[Strava callback] state check — received=' . $receivedState . ' expected=' . $expectedState);
unset($_SESSION['strava_oauth_state']);

if ($receivedState === '' || $receivedState !== $expectedState) {
    error_log('[Strava callback] state mismatch — STOP');
    setFlash('error', 'คำขอไม่ถูกต้อง (state mismatch) กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Exchange authorization code for tokens ─────────────────
$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    error_log('[Strava callback] no code — STOP');
    setFlash('error', 'ไม่ได้รับ authorization code จาก Strava');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

error_log('[Strava callback] calling token exchange...');
$ch = curl_init(STRAVA_TOKEN_URL);
$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => STRAVA_CLIENT_ID,
        'client_secret' => STRAVA_CLIENT_SECRET,
        'code'          => $code,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
];
// On Windows servers without bundled CA bundle, point to cacert.pem
$caBundlePaths = [
    __DIR__ . '/config/cacert.pem',
    'C:/xampp/apache/conf/ssl.crt/cacert.pem',
    'C:/xampp/php/extras/ssl/cacert.pem',
];
foreach ($caBundlePaths as $caPath) {
    if (file_exists($caPath)) {
        $curlOpts[CURLOPT_CAINFO] = $caPath;
        error_log('[Strava callback] using CA bundle: ' . $caPath);
        break;
    }
}
curl_setopt_array($ch, $curlOpts);
$body = curl_exec($ch);
$err  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
error_log('[Strava callback] token exchange HTTP=' . $httpCode . ' curl_err=' . ($err ?: 'none') . ' body_len=' . strlen((string)$body));

if ($err || !$body) {
    error_log('[Strava callback] cURL error: ' . $err);
    setFlash('error', 'เชื่อมต่อ Strava API ไม่ได้ กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$data = json_decode($body, true);
error_log('[Strava callback] token response keys=' . implode(',', array_keys($data ?? [])));

// Strava returns error as JSON with "message" key
if (!empty($data['message']) || empty($data['access_token'])) {
    $msg = $data['message'] ?? 'Unknown error';
    error_log('[Strava callback] token error: ' . $body);
    setFlash('error', 'Strava ปฏิเสธคำขอ: ' . htmlspecialchars($msg));
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Validate scope ─────────────────────────────────────────
$grantedScope = (string)($data['scope'] ?? '');
error_log('[Strava callback] granted scope: ' . $grantedScope);
if (strpos($grantedScope, 'activity:read') === false) {
    setFlash('error', 'กรุณาอนุญาต "View data about your activities" เพื่อใช้ฟีเจอร์นี้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$athleteId   = (int)($data['athlete']['id'] ?? 0);
$accessToken = (string)$data['access_token'];
$refreshToken= (string)$data['refresh_token'];
$expiresAt   = (int)$data['expires_at'];
error_log('[Strava callback] athlete_id=' . $athleteId . ' expires_at=' . $expiresAt);

if ($athleteId === 0) {
    error_log('[Strava callback] athlete_id=0 — STOP');
    setFlash('error', 'ไม่สามารถดึงข้อมูลนักกีฬาจาก Strava ได้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Check duplicate + Save tokens to DB ───────────────────
error_log('[Strava callback] connecting to DB...');
$pdo = getDB();
error_log('[Strava callback] DB connected, running duplicate check...');
try {
    $dup = $pdo->prepare("
        SELECT employee_id FROM employees
        WHERE strava_athlete_id = ? AND employee_id <> ?
    ");
    $dup->execute([$athleteId, $employeeId]);
    if ($dup->fetch()) {
        error_log('[Strava callback] duplicate athlete_id=' . $athleteId . ' — STOP');
        setFlash('error', 'บัญชี Strava นี้ถูกเชื่อมต่อกับพนักงานท่านอื่นแล้ว');
        redirect(BASE_URL . '/pages/strava_connect.php');
    }

    error_log('[Strava callback] saving tokens to DB for employee_id=' . $employeeId);
    $saveStmt = $pdo->prepare("
        UPDATE employees
        SET strava_athlete_id       = ?,
            strava_access_token     = ?,
            strava_refresh_token    = ?,
            strava_token_expires_at = ?,
            strava_scope            = ?
        WHERE employee_id = ?
    ");
    $saveStmt->bindValue(1, $athleteId,    PDO::PARAM_INT);
    $saveStmt->bindValue(2, $accessToken,  PDO::PARAM_STR);
    $saveStmt->bindValue(3, $refreshToken, PDO::PARAM_STR);
    $saveStmt->bindValue(4, $expiresAt,    PDO::PARAM_INT);
    $saveStmt->bindValue(5, $grantedScope, PDO::PARAM_STR);
    $saveStmt->bindValue(6, $employeeId,   PDO::PARAM_INT);
    $saveStmt->execute();
    error_log('[Strava callback] DB save OK');
} catch (Throwable $e) {
    error_log('[Strava callback] DB error: ' . $e->getMessage());
    setFlash('error', 'บันทึกข้อมูล Strava ไม่สำเร็จ กรุณาติดต่อผู้ดูแลระบบ (DB error: ' . htmlspecialchars($e->getMessage()) . ')');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

error_log('[Strava callback] SUCCESS');
$athleteName = trim(($data['athlete']['firstname'] ?? '') . ' ' . ($data['athlete']['lastname'] ?? ''));
setFlash('success', 'เชื่อมต่อ Strava สำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($athleteName));
redirect(BASE_URL . '/pages/strava_connect.php');
