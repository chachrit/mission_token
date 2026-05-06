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
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => STRAVA_CLIENT_ID,
        'client_secret' => STRAVA_CLIENT_SECRET,
        'code'          => $code,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT        => 20,
]);
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

$athleteId   = (int)($data['athlete']['id'] ?? 0);
$accessToken = (string)$data['access_token'];
$refreshToken= (string)$data['refresh_token'];
$expiresAt   = (int)$data['expires_at'];

if ($athleteId === 0) {
    setFlash('error', 'ไม่สามารถดึงข้อมูลนักกีฬาจาก Strava ได้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Check if this Strava account is already linked to another employee ──
$pdo = getDB();
$dup = $pdo->prepare("
    SELECT employee_id FROM employees
    WHERE strava_athlete_id = ? AND employee_id <> ?
");
$dup->execute([$athleteId, $employeeId]);
if ($dup->fetch()) {
    setFlash('error', 'บัญชี Strava นี้ถูกเชื่อมต่อกับพนักงานท่านอื่นแล้ว');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// ── Save tokens to DB ──────────────────────────────────────
$saveStmt = $pdo->prepare("
    UPDATE employees
    SET strava_athlete_id       = ?,
        strava_access_token     = ?,
        strava_refresh_token    = ?,
        strava_token_expires_at = ?,
        strava_scope            = ?
    WHERE employee_id = ?
");
$saveStmt->bindValue(1, $athleteId,   PDO::PARAM_INT);
$saveStmt->bindValue(2, $accessToken, PDO::PARAM_STR);
$saveStmt->bindValue(3, $refreshToken,PDO::PARAM_STR);
$saveStmt->bindValue(4, $expiresAt,   PDO::PARAM_INT);
$saveStmt->bindValue(5, $grantedScope,PDO::PARAM_STR);
$saveStmt->bindValue(6, $employeeId,  PDO::PARAM_INT);
$saveStmt->execute();

$athleteName = trim(($data['athlete']['firstname'] ?? '') . ' ' . ($data['athlete']['lastname'] ?? ''));
setFlash('success', 'เชื่อมต่อ Strava สำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($athleteName));
redirect(BASE_URL . '/pages/strava_connect.php');
