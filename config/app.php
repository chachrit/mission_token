<?php
/**
 * config/app.php
 * Application constants, session management, security helpers, utility functions
 */

// ============================================================
// Application Constants
// ============================================================
define('APP_NAME',    'Mission Token');
define('APP_VERSION', '1.0.0');
// Auto-detect BASE_URL from current request — works on any server without manual config
(function () {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // App root on filesystem (the folder containing config/, pages/, etc.)
    $appDir    = str_replace('\\', '/', rtrim(dirname(__DIR__), '/\\'));
    // Full filesystem path of the currently-executing script
    $scriptFs  = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    // URL path of the currently-executing script (e.g. /mission_token/pages/dashboard.php)
    $scriptUrl = $_SERVER['SCRIPT_NAME'] ?? '/';
    $subPath   = '';
    if ($scriptFs !== '' && strpos($scriptFs, $appDir) === 0) {
        // Relative script path inside app dir (e.g. pages/dashboard.php)
        $rel     = ltrim(substr($scriptFs, strlen($appDir)), '/');
        // Strip that from the end of the URL path → leaves /mission_token
        $subPath = rtrim(substr($scriptUrl, 0, strlen($scriptUrl) - strlen($rel)), '/');
    }
    define('BASE_URL', $protocol . '://' . $host . $subPath);
})();

// External Employee API
// ถ้า production server อยู่เครื่องเดียวกับ API → ใช้ localhost หลีกเลี่ยง self-referencing ผ่าน external IP
(function () {
    $apiExternalIp = '203.154.130.236';
    // ตรวจ HTTP_HOST และ SERVER_ADDR ว่าอยู่บนเครื่องเดียวกับ API ไหม
    $httpHost  = $_SERVER['HTTP_HOST']   ?? '';
    $serverIp  = $_SERVER['SERVER_ADDR'] ?? '';
    $isSameServer = (
        str_contains($httpHost, $apiExternalIp) ||
        $serverIp === $apiExternalIp ||
        $serverIp === '127.0.0.1'     // กรณี reverse proxy / Apache binding
        && str_contains($httpHost, $apiExternalIp)
    );
    $apiBase = $isSameServer ? 'http://127.0.0.1' : "http://{$apiExternalIp}";
    define('EMP_API_URL', $apiBase . '/emp_api/api/employee.php');
    define('AUTH_API_URL', $apiBase . '/emp_api/api/auth.php');
})();
define('EMP_API_KEY', 'my-secret-key-12345');
define('AUTH_API_TIMEOUT', 8);

// File Upload settings
define('UPLOAD_PATH',    __DIR__ . '/../uploads/submissions/');
define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024); // 20 MB
define('ALLOWED_MIME',   ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXT',    ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ============================================================
// Session Management
// ============================================================

/**
 * Initialize session with security settings.
 * Call this at the top of every page before output.
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime',  '7200'); // 2 hours
        session_start();
    }
}

// ============================================================
// CSRF Protection
// ============================================================

/**
 * Return (or generate) the per-session CSRF token.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field (use inside <form>).
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST.
 * Terminates with JSON error on failure.
 */
function validateCsrf(): void
{
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh and try again.']);
        exit;
    }
}

// ============================================================
// Output / Security Helpers
// ============================================================

/**
 * HTML-escape a string for safe output.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL and halt execution.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Send a JSON response and halt execution (for API endpoints).
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * True if current request method is POST.
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ============================================================
// Flash Messages
// ============================================================

/**
 * Store a one-time flash message in the session.
 * $type: 'success' | 'error' | 'info' | 'warning'
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the stored flash message.
 * Returns null if no flash message exists.
 */
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// File Upload Validation
// ============================================================

/**
 * Validate an uploaded file ($_FILES entry).
 * Returns ['ok' => true] or ['ok' => false, 'error' => 'message']
 */
function validateUpload(array $file): array
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMessages = [
            UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินขนาดที่กำหนดใน server',
            UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินขนาดที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL    => 'อัปโหลดไม่สมบูรณ์',
            UPLOAD_ERR_NO_FILE    => 'ไม่ได้เลือกไฟล์',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบ temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'เขียนไฟล์ไม่ได้',
            UPLOAD_ERR_EXTENSION  => 'อัปโหลดถูกบล็อกโดย extension',
        ];
        return ['ok' => false, 'error' => $errMessages[$file['error']] ?? 'เกิดข้อผิดพลาดในการอัปโหลด'];
    }

    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['ok' => false, 'error' => 'ไฟล์ต้องมีขนาดไม่เกิน 20MB'];
    }

    // Check extension (allowlist)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        return ['ok' => false, 'error' => 'รองรับเฉพาะไฟล์ภาพ JPG, PNG, GIF, WEBP เท่านั้น'];
    }

    // Check actual MIME type via finfo (NOT $_FILES['type'] which is forgeable)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        return ['ok' => false, 'error' => 'ประเภทไฟล์ไม่ถูกต้อง'];
    }

    // Verify it is actually an image (prevents polyglot files)
    if (!getimagesize($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'ไฟล์ไม่ใช่รูปภาพที่ถูกต้อง'];
    }

    return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
}

/**
 * Move uploaded file to upload directory with a random hex filename.
 * Returns the stored filename (without path) or throws on failure.
 */
function storeUpload(array $file, string $ext): string
{
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return $filename;
}
