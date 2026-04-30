<?php
/**
 * includes/hr_check.php
 * Session guard for HR + Admin pages.
 * Allows access for role = 'hr' OR role = 'admin'.
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/hr_check.php';  // from admin/sub/page.php
 *   require_once __DIR__ . '/../includes/hr_check.php';     // from admin/page.php
 */

require_once __DIR__ . '/../config/app.php';

initSession();

if (empty($_SESSION['employee_id'])) {
    redirect(BASE_URL . '/index.php');
}

$_role = $_SESSION['role'] ?? '';
if ($_role !== 'admin' && $_role !== 'hr' && $_role !== 'it') {
    // Authenticated but not hr/it/admin — send to employee dashboard
    redirect(BASE_URL . '/pages/dashboard.php');
}

// Session timeout: 2 hours of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . '/index.php?timeout=1');
}

$_SESSION['last_activity'] = time();
