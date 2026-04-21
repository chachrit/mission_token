<?php
/**
 * includes/admin_check.php
 * Session guard for admin pages. Ensures user is logged in AND has role = 'admin'.
 * Include at the top of every admin page (before any output).
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/admin_check.php';  // from admin/sub/page.php
 *   require_once __DIR__ . '/../includes/admin_check.php';     // from admin/page.php
 */

require_once __DIR__ . '/../config/app.php';

initSession();

if (empty($_SESSION['employee_id'])) {
    redirect(BASE_URL . '/index.php');
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    // Authenticated but not admin — send to employee dashboard
    redirect(BASE_URL . '/pages/dashboard.php');
}

// Session timeout: 2 hours of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . '/index.php?timeout=1');
}
$_SESSION['last_activity'] = time();
