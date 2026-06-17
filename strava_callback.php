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

// Handle user denying access
if (isset($_GET['error'])) {
    stravaLog('oauth denied by user');
    setFlash('error', 'คุณยกเลิกการเชื่อมต่อ Strava');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// Validate state
$receivedState = (string)($_GET['state'] ?? '');
$expectedState = (string)($_SESSION['strava_oauth_state'] ?? '');
unset($_SESSION['strava_oauth_state']);

if ($receivedState === '' || $receivedState !== $expectedState) {
    stravaLog('state mismatch');
    setFlash('error', 'คำขอไม่ถูกต้อง (state mismatch) กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

// Exchange authorization code for tokens
$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    stravaLog('missing authorization code');
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
$err = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || !$body) {
    stravaLog('token exchange cURL error');
    setFlash('error', 'เชื่อมต่อ Strava API ไม่ได้ กรุณาลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$data = json_decode($body, true);

if (!empty($data['message']) || empty($data['access_token'])) {
    $msg = stravaExtractApiMessage((string)$body);
    stravaLog('token exchange HTTP ' . $httpCode . ($msg !== '' ? ': ' . $msg : ''));
    setFlash('error', 'Strava ปฏิเสธคำขอ กรุณาตรวจสอบสิทธิ์และลองใหม่');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$grantedScope = (string)($data['scope'] ?? '');
if (strpos($grantedScope, 'activity:read') === false) {
    stravaLog('insufficient scope granted');
    setFlash('error', 'กรุณาอนุญาตสิทธิ์อ่านกิจกรรม (activity:read) เพื่อใช้งานฟีเจอร์นี้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$athleteId = (int)($data['athlete']['id'] ?? 0);
$accessToken = (string)$data['access_token'];
$refreshToken = (string)$data['refresh_token'];
$expiresAt = (int)$data['expires_at'];

if ($athleteId === 0) {
    stravaLog('missing athlete id from token response');
    setFlash('error', 'ไม่สามารถดึงข้อมูลนักกีฬาจาก Strava ได้');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$pdo = getDB();
try {
    $dup = $pdo->prepare("\n        SELECT employee_id FROM employees\n        WHERE strava_athlete_id = ? AND employee_id <> ?\n    ");
    $dup->execute([$athleteId, $employeeId]);
    if ($dup->fetch()) {
        setFlash('error', 'บัญชี Strava นี้ถูกเชื่อมต่อกับพนักงานท่านอื่นแล้ว');
        redirect(BASE_URL . '/pages/strava_connect.php');
    }

    $saveStmt = $pdo->prepare("\n        UPDATE employees\n        SET strava_athlete_id       = ?,\n            strava_access_token     = ?,\n            strava_refresh_token    = ?,\n            strava_token_expires_at = ?,\n            strava_scope            = ?\n        WHERE employee_id = ?\n    ");
    $saveStmt->bindValue(1, $athleteId, PDO::PARAM_INT);
    $saveStmt->bindValue(2, $accessToken, PDO::PARAM_STR);
    $saveStmt->bindValue(3, $refreshToken, PDO::PARAM_STR);
    $saveStmt->bindValue(4, $expiresAt, PDO::PARAM_INT);
    $saveStmt->bindValue(5, $grantedScope, PDO::PARAM_STR);
    $saveStmt->bindValue(6, $employeeId, PDO::PARAM_INT);
    $saveStmt->execute();
} catch (Throwable $e) {
    stravaLog('DB error while saving oauth tokens');
    setFlash('error', 'บันทึกข้อมูล Strava ไม่สำเร็จ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
    redirect(BASE_URL . '/pages/strava_connect.php');
}

$athleteName = trim(($data['athlete']['firstname'] ?? '') . ' ' . ($data['athlete']['lastname'] ?? ''));
setFlash('success', 'เชื่อมต่อ Strava สำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($athleteName));
redirect(BASE_URL . '/pages/strava_connect.php');
