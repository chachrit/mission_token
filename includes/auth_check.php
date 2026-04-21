<?php
/**
 * includes/auth_check.php
 * Session guard for employee pages.
 * Include at the top of every employee-facing page (before any output).
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/auth_check.php';
 */

require_once __DIR__ . '/../config/app.php';

initSession();

if (empty($_SESSION['employee_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    redirect(BASE_URL . '/login.php' . ($redirect ? '?redirect=' . $redirect : ''));
}

// Session timeout: 2 hours of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . '/login.php?timeout=1');
}
$_SESSION['last_activity'] = time();
