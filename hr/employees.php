<?php
/**
 * admin/employees.php
 * HR/Admin — manage employees: view, toggle active, change role,
 *            adjust token balance, reset password
 */

require_once __DIR__ . '/../includes/hr_check.php';
require_once __DIR__ . '/../includes/functions.php';

$myId       = (int)$_SESSION['employee_id'];
$myRole     = $_SESSION['role'] ?? '';
$canManage  = in_array($myRole, ['admin', 'hr'], true);
$isAdminOnly = ($myRole === 'admin');

$pdo = getDB();

// ══════════════════════════════════════════════════════════════
// POST actions
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action     = (string)($_POST['action'] ?? '');
    $targetId   = (int)($_POST['employee_id'] ?? 0);

    // Guard: cannot act on yourself for destructive actions
    // Guard: canManage required for all write actions
    if (!$canManage) {
        setFlash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect(BASE_URL . '/hr/employees.php');
    }

    // ── Toggle active ──────────────────────────────────────
    if ($action === 'toggle_active' && $targetId > 0) {
        if ($targetId === $myId) {
            setFlash('error', 'ไม่สามารถปิดบัญชีตัวเองได้');
        } else {
            try {
                $pdo->prepare("
                    UPDATE dbo.employees
                    SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
                    WHERE employee_id = ?
                ")->execute([$targetId]);
                setFlash('success', 'อัปเดตสถานะบัญชีแล้ว');
            } catch (Throwable $e) {
                error_log('[MissionToken] toggle_active error: ' . $e->getMessage());
                setFlash('error', 'เกิดข้อผิดพลาด');
            }
        }
        redirect(BASE_URL . '/hr/employees.php' . (isset($_POST['qs']) ? '?' . $_POST['qs'] : ''));
    }

    // ── Change role (admin only) ───────────────────────────
    if ($action === 'change_role' && $targetId > 0) {
        if (!$isAdminOnly) {
            setFlash('error', 'เฉพาะ Admin เท่านั้นที่สามารถเปลี่ยน Role ได้');
            redirect(BASE_URL . '/hr/employees.php');
        }
        if ($targetId === $myId) {
            setFlash('error', 'ไม่สามารถเปลี่ยน Role ของตัวเองได้');
            redirect(BASE_URL . '/hr/employees.php');
        }
        $newRole = (string)($_POST['role'] ?? '');
        $allowed = ['employee', 'hr', 'it', 'admin'];
        if (!in_array($newRole, $allowed, true)) {
            setFlash('error', 'Role ไม่ถูกต้อง');
            redirect(BASE_URL . '/hr/employees.php');
        }
        try {
            $pdo->prepare("UPDATE dbo.employees SET role = ? WHERE employee_id = ?")
                ->execute([$newRole, $targetId]);
            setFlash('success', 'เปลี่ยน Role เรียบร้อยแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] change_role error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/hr/employees.php' . (isset($_POST['qs']) ? '?' . $_POST['qs'] : ''));
    }

    // ── Adjust token ───────────────────────────────────────
    if ($action === 'adjust_token' && $targetId > 0) {
        $amount = (int)($_POST['amount'] ?? 0);
        $note   = trim((string)($_POST['note'] ?? ''));
        if ($amount === 0) {
            setFlash('error', 'กรุณากรอกจำนวน Token ที่ไม่เป็น 0');
            redirect(BASE_URL . '/hr/employees.php');
        }
        // Check balance won't go negative
        $balRow = $pdo->prepare("SELECT balance FROM dbo.token_wallets WHERE employee_id = ?");
        $balRow->execute([$targetId]);
        $currentBal = (int)($balRow->fetchColumn() ?? 0);
        if ($amount < 0 && ($currentBal + $amount) < 0) {
            setFlash('error', 'Token ไม่เพียงพอ — ปัจจุบัน ' . formatTokens($currentBal) . ' Token');
            redirect(BASE_URL . '/hr/employees.php');
        }
        $ok = awardTokens($targetId, $amount, 'admin_adjust', null,
            $note,
            (int)$_SESSION['employee_id']);
        if ($ok) {
            setFlash('success', ($amount > 0 ? '+' : '') . formatTokens($amount) . ' Token — ปรับยอดแล้ว');
        } else {
            setFlash('error', 'เกิดข้อผิดพลาดในการปรับ Token');
        }
        redirect(BASE_URL . '/hr/employees.php' . (isset($_POST['qs']) ? '?' . $_POST['qs'] : ''));
    }

    // ── Reset password (admin only) ────────────────────────
    if ($action === 'reset_password' && $targetId > 0) {
        if (!$isAdminOnly) {
            setFlash('error', 'เฉพาะ Admin เท่านั้นที่สามารถ Reset รหัสผ่านได้');
            redirect(BASE_URL . '/hr/employees.php');
        }
        $newPw  = (string)($_POST['new_password'] ?? '');
        $newPw2 = (string)($_POST['confirm_password'] ?? '');
        if (strlen($newPw) < 6) {
            setFlash('error', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
            redirect(BASE_URL . '/hr/employees.php');
        }
        if ($newPw !== $newPw2) {
            setFlash('error', 'รหัสผ่านไม่ตรงกัน');
            redirect(BASE_URL . '/hr/employees.php');
        }
        try {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE dbo.employees SET password_hash = ? WHERE employee_id = ?")
                ->execute([$hash, $targetId]);
            setFlash('success', 'Reset รหัสผ่านเรียบร้อยแล้ว');
        } catch (Throwable $e) {
            error_log('[MissionToken] reset_password error: ' . $e->getMessage());
            setFlash('error', 'เกิดข้อผิดพลาด');
        }
        redirect(BASE_URL . '/hr/employees.php' . (isset($_POST['qs']) ? '?' . $_POST['qs'] : ''));
    }

    redirect(BASE_URL . '/hr/employees.php');
}

// ══════════════════════════════════════════════════════════════
// GET: load data
// ══════════════════════════════════════════════════════════════
$search       = trim((string)($_GET['q']      ?? ''));
$roleFilter   = (string)($_GET['role']   ?? '');
$deptFilter   = trim((string)($_GET['dept']   ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.department LIKE ? OR e.position LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($roleFilter !== '') {
    $whereClauses[] = "e.role = ?";
    $params[] = $roleFilter;
}
if ($deptFilter !== '') {
    $whereClauses[] = "e.department = ?";
    $params[] = $deptFilter;
}
if ($statusFilter === 'active') {
    $whereClauses[] = "e.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = "e.is_active = 0";
}

$whereSQL = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

try {
    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.employee_code, e.full_name,
               e.department, e.position, e.role, e.is_active, e.email, e.start_date,
               e.avatar_url,
               ISNULL(w.balance, 0)      AS balance,
               ISNULL(w.total_earned, 0) AS total_earned,
               ISNULL(w.total_spent, 0)  AS total_spent
        FROM   dbo.employees e
        LEFT JOIN dbo.token_wallets w ON w.employee_id = e.employee_id
        $whereSQL
        ORDER BY e.is_active DESC, e.full_name ASC
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[MissionToken] employees load error: ' . $e->getMessage());
    $employees = [];
}

// Summary stats
try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
            SUM(CASE WHEN role = 'hr'    THEN 1 ELSE 0 END) AS hr_count,
            SUM(CASE WHEN role = 'it'    THEN 1 ELSE 0 END) AS it_count
        FROM dbo.employees
    ")->fetch();
} catch (Throwable $e) {
    $statsRow = [];
}

// Load distinct departments for filter dropdown
$departments = [];
try {
    $deptRows = $pdo->query("
        SELECT DISTINCT department
        FROM   dbo.employees
        WHERE  department IS NOT NULL AND department <> ''
        ORDER  BY department ASC
    ")->fetchAll(\PDO::FETCH_COLUMN);
    $departments = $deptRows ?: [];
} catch (Throwable $e) { /* ignore */ }

// Build query string for back-links
$qs = http_build_query(array_filter(['q' => $search, 'role' => $roleFilter, 'dept' => $deptFilter, 'status' => $statusFilter]));

$flash      = getFlash();
$pageTitle  = 'จัดการพนักงาน';
$activePage = 'admin_employees';

require_once __DIR__ . '/../includes/header.php';

$roleMeta = [
    'admin'    => ['label' => 'Admin',    'bg' => 'rgba(98,48,122,0.20)',  'color' => '#c49de0', 'border' => 'rgba(98,48,122,0.40)'],
    'hr'       => ['label' => 'HR',       'bg' => 'rgba(79,139,152,0.18)', 'color' => '#7ab8c4', 'border' => 'rgba(79,139,152,0.38)'],
    'it'       => ['label' => 'IT',       'bg' => 'rgba(47,78,157,0.18)',  'color' => '#7b9ff5', 'border' => 'rgba(47,78,157,0.38)'],
    'employee' => ['label' => 'พนักงาน',  'bg' => 'rgba(107,110,119,0.16)','color' => '#9ca3af', 'border' => 'rgba(107,110,119,0.32)'],
];
?>

<div class="emp-employees-wrap emp-wrap emp-wrap-base">

    <!-- Aurora blobs -->
    <div class="jp-aurora-layer" aria-hidden="true">
        <div class="jp-aurora-blob jp-aurora-blob--gold"></div>
        <div class="jp-aurora-blob jp-aurora-blob--teal"></div>
    </div>

    <div class="jp-page-inner jp-page-inner--wide">

        <!-- Page header -->
        <div class="jp-page-header jp-page-header-row">
            <div>
                <p class="jp-kicker">
                    ADMIN — EMPLOYEES
                </p>
                <h1 class="jp-title">
                    จัดการพนักงาน
                </h1>
                <p class="jp-subtitle">
                    ดู แก้ไขสิทธิ์ และจัดการ Token ของพนักงานทุกคน
                </p>
            </div>
            <!-- Stats chips -->
            <div class="jp-chip-row">
                <span class="emp-stat-chip emp-stat-chip--total">
                    ทั้งหมด: <?= (int)($statsRow['total'] ?? 0) ?>
                </span>
                <span class="emp-stat-chip emp-stat-chip--active">
                    ใช้งาน: <?= (int)($statsRow['active_count'] ?? 0) ?>
                </span>
                <span class="emp-stat-chip emp-stat-chip--admin">
                    Admin: <?= (int)($statsRow['admin_count'] ?? 0) ?>
                </span>
                <span class="emp-stat-chip emp-stat-chip--hr">
                    HR: <?= (int)($statsRow['hr_count'] ?? 0) ?>
                </span>
            </div>
        </div>

        <!-- Search + Filter -->
          <form method="GET" action="" class="emp-filter-form">
            <input type="text" name="q" value="<?= e($search) ?>"
                   placeholder="ค้นหาชื่อ, รหัส, แผนก, ตำแหน่ง…"
                   class="emp-search-input emp-search-input--main">
            <select name="dept" class="emp-search-input emp-search-select">
                <option value="">ทุกแผนก</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= e($dept) ?>" <?= $deptFilter === $dept ? 'selected' : '' ?>>
                    <?= e($dept) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="role" class="emp-search-input emp-search-select">
                <option value="">ทุก Role</option>
                <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>พนักงาน</option>
                <option value="hr"       <?= $roleFilter === 'hr'       ? 'selected' : '' ?>>HR</option>
                <option value="it"       <?= $roleFilter === 'it'       ? 'selected' : '' ?>>IT</option>
                <option value="admin"    <?= $roleFilter === 'admin'    ? 'selected' : '' ?>>Admin</option>
            </select>
            <select name="status" class="emp-search-input emp-search-select">
                <option value="">ทุกสถานะ</option>
                <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>ใช้งานอยู่</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>ปิดบัญชี</option>
            </select>
            <button type="submit" class="emp-filter-btn emp-filter-btn--search">
                ค้นหา
            </button>
            <?php if ($search || $roleFilter || $deptFilter || $statusFilter): ?>
            <a href="<?= BASE_URL ?>/hr/employees.php"
               class="emp-filter-btn emp-filter-btn--reset">
                ล้างการค้นหา
            </a>
            <span class="emp-filter-result">
                พบ <?= count($employees) ?> รายการ
            </span>
            <?php endif; ?>
        </form>

        <!-- Employee Table -->
        <div class="emp-table-outer jp-glass-card jp-glass-card--md">

            <!-- Table header -->
            <div class="jp-table-header emp-table-header">
                <span>พนักงาน</span>
                <span>ตำแหน่ง / แผนก</span>
                <span>อายุงาน</span>
                <span>Role</span>
                <span>Token</span>
                <span>สถานะ</span>
                <span>จัดการ</span>
            </div>

            <?php if (empty($employees)): ?>
            <div class="emp-empty-state">
                <p class="emp-empty-state-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5" stroke-width="2"/>
                        <path d="M5 20a7 7 0 0 1 14 0" stroke-width="2"/>
                    </svg>
                </p>
                <p class="emp-empty-state-text">ไม่พบพนักงานที่ตรงกับเงื่อนไข</p>
            </div>
            <?php else: ?>

            <?php foreach ($employees as $emp):
                $isMe      = ((int)$emp['employee_id'] === $myId);
                $isOn      = (bool)$emp['is_active'];
                $rMeta     = $roleMeta[$emp['role']] ?? $roleMeta['employee'];
                $balance   = (int)$emp['balance'];
                $roleTheme = 'emp-role-theme--' . preg_replace('/[^a-z0-9_-]/i', '', (string)($emp['role'] ?? 'employee'));
            ?>
            <div class="emp-row <?= $isOn ? '' : 'emp-row-inactive' ?>">

                <!-- Avatar + Name -->
                <div class="emp-person-cell">
                    <?php if (!empty($emp['avatar_url'])): ?>
                    <img src="<?= uploadImgUrl('avatars', (string)$emp['avatar_url']) ?>"
                         alt="" loading="lazy"
                         class="emp-avatar emp-avatar-img"
                         data-onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="emp-avatar emp-avatar-fallback emp-avatar-fallback--hidden">
                        <?= mb_substr($emp['full_name'], 0, 1) ?>
                    </div>
                    <?php else: ?>
                    <div class="emp-avatar emp-avatar-fallback">
                        <?= mb_substr($emp['full_name'], 0, 1) ?>
                    </div>
                    <?php endif; ?>
                    <div class="emp-person-meta">
                        <p class="emp-person-name">
                            <?= e($emp['full_name']) ?>
                            <?php if ($isMe): ?>
                            <span class="emp-person-me">(คุณ)</span>
                            <?php endif; ?>
                        </p>
                        <p class="emp-person-code">
                            <?= e($emp['employee_code']) ?>
                        </p>
                    </div>
                </div>

                <!-- Position / Dept -->
                <div class="emp-job-col">
                    <p class="emp-job-title">
                        <?= e($emp['position'] ?? '—') ?>
                    </p>
                    <p class="emp-job-dept">
                        <?= e($emp['department'] ?? '—') ?>
                    </p>
                </div>

                <!-- Tenure -->
                <?php $_et = getWorkTenure($emp['start_date'] ?? null); ?>
                <div>
                    <?php if ($_et): ?>
                    <p class="emp-tenure-text">
                        <?= e($_et['text']) ?>
                    </p>
                    <p class="emp-tenure-days">
                        <?= number_format($_et['total_days']) ?> วัน
                    </p>
                    <?php else: ?>
                    <p class="emp-tenure-empty">—</p>
                    <?php endif; ?>
                </div>

                <!-- Role badge + change (admin only) -->
                <div>
                    <?php if ($isAdminOnly && !$isMe): ?>
                    <form method="POST" action=""
                          data-onsubmit="return confirm('เปลี่ยน Role ของ <?= addslashes(e($emp['full_name'])) ?> ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"      value="change_role">
                        <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                        <input type="hidden" name="qs"          value="<?= e($qs) ?>">
                        <select name="role" class="emp-role-select <?= e($roleTheme) ?>"
                                data-onchange="this.form.submit()">
                            <?php foreach ($roleMeta as $rk => $rm): ?>
                            <option value="<?= $rk ?>" <?= $emp['role'] === $rk ? 'selected' : '' ?>>
                                <?= $rm['label'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <span class="emp-role-badge <?= e($roleTheme) ?>">
                        <?= $rMeta['label'] ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Token balance -->
                <div>
                    <div class="emp-token-chip">
                        <img src="<?= BASE_URL ?>/assets/images/token.png"
                             width="13" height="13" class="emp-token-chip-icon" alt="">
                        <span class="emp-token-chip-value">
                            <?= formatTokens($balance) ?>
                        </span>
                    </div>
                </div>

                <!-- Active toggle -->
                <div>
                    <?php if ($canManage && !$isMe): ?>
                    <form method="POST" action=""
                          data-onsubmit="return confirm('<?= $isOn ? 'ปิดบัญชี' : 'เปิดบัญชี' ?> ของ <?= addslashes(e($emp['full_name'])) ?> ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"      value="toggle_active">
                        <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                        <input type="hidden" name="qs"          value="<?= e($qs) ?>">
                        <button type="submit" class="emp-toggle <?= $isOn ? 'on' : '' ?>"
                                title="<?= $isOn ? 'คลิกเพื่อปิดบัญชี' : 'คลิกเพื่อเปิดบัญชี' ?>"></button>
                    </form>
                    <span class="emp-status-sub <?= $isOn ? 'emp-status-on' : 'emp-status-off' ?>">
                        <?= $isOn ? 'ใช้งาน' : 'ปิด' ?>
                    </span>
                    <?php else: ?>
                    <span class="emp-status-dot <?= $isOn ? 'emp-status-on' : 'emp-status-off' ?>">
                        <?= $isOn ? '● ใช้งาน' : '○ ปิด' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Action buttons -->
                <div class="emp-action-row">
                    <?php if ($canManage): ?>
                    <button data-onclick="empOpenAdjust(<?= (int)$emp['employee_id'] ?>, '<?= addslashes(e($emp['full_name'])) ?>', <?= $balance ?>, '<?= e($qs) ?>')"
                            class="emp-action-btn emp-action-btn--token">
                        Token
                    </button>
                    <?php endif; ?>
                    <?php if ($isAdminOnly && !$isMe): ?>
                    <button data-onclick="empOpenPw(<?= (int)$emp['employee_id'] ?>, '<?= addslashes(e($emp['full_name'])) ?>', '<?= e($qs) ?>')"
                            class="emp-action-btn emp-action-btn--pw">
                        Reset PW
                    </button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /inner -->
</div><!-- /emp-employees-wrap -->

<!-- ══ Modal: Adjust Token ══════════════════════════════════ -->
<?php if ($canManage): ?>
<div id="emp-adjust-modal" class="jp-modal">
    <div class="jp-modal-content">
        <div class="jp-modal-header">
            <p id="emp-adjust-title" class="jp-modal-header-title"></p>
            <button data-onclick="empCloseAdjust()" class="jp-modal-close" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <form id="emp-adjust-form" method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action"      value="adjust_token">
            <input type="hidden" name="employee_id" id="emp-adjust-emp-id">
            <input type="hidden" name="qs"          id="emp-adjust-qs">
            <input type="hidden" name="amount"       id="emp-adjust-amount-final">
            <div class="jp-modal-body">
                <div class="emp-adjust-balance-box">
                    Token ปัจจุบัน: <span id="emp-adjust-balance" class="emp-adjust-balance-value"></span>
                </div>

                <!-- Mode toggle -->
                <div class="emp-adjust-mode-grid">
                    <button type="button" id="adj-btn-add"
                            data-onclick="empSetMode('add')"
                            class="emp-adjust-mode-btn emp-adjust-mode-btn--add">
                        + เพิ่ม Token
                    </button>
                    <button type="button" id="adj-btn-deduct"
                            data-onclick="empSetMode('deduct')"
                            class="emp-adjust-mode-btn emp-adjust-mode-btn--idle">
                        &minus; หัก Token
                    </button>
                </div>

                <div>
                    <label id="adj-amount-label" class="emp-modal-label">
                        จำนวน Token ที่จะเพิ่ม <span class="emp-required-mark">*</span>
                    </label>
                    <input type="number" id="emp-adjust-amount" min="1"
                           placeholder="ระบุจำนวนเป็นบวกเสมอ"
                           class="jp-input" required>
                </div>
                <div>
                    <label class="emp-modal-label">
                        หมายเหตุ
                    </label>
                    <input type="text" name="note" maxlength="200"
                           placeholder="เช่น โบนัสประจำเดือน, ปรับปรุงยอด…"
                           class="jp-input">
                </div>
            </div>
                <div class="jp-modal-footer">
                <button type="button" data-onclick="empCloseAdjust()"
                        class="emp-modal-btn emp-modal-btn--cancel">
                    ยกเลิก
                </button>
                <button type="submit" id="emp-adjust-submit-btn"
                        class="emp-modal-btn emp-modal-btn--submit-add">
                    เพิ่ม Token
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ Modal: Reset Password (admin only) ═══════════════════ -->
<?php if ($isAdminOnly): ?>
<div id="emp-pw-modal" class="jp-modal">
    <div class="jp-modal-content">
        <div class="jp-modal-header">
            <p id="emp-pw-title" class="jp-modal-header-title"></p>
            <button data-onclick="empClosePw()" class="jp-modal-close" aria-label="ปิด">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <form id="emp-pw-form" method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action"      value="reset_password">
            <input type="hidden" name="employee_id" id="emp-pw-emp-id">
            <input type="hidden" name="qs"          id="emp-pw-qs">
            <div class="jp-modal-body">
                <div class="emp-pw-warning-box">
                    รหัสผ่านใหม่จะมีผลทันที พนักงานจะต้อง login ด้วยรหัสผ่านใหม่นี้
                </div>
                <div>
                    <label class="emp-modal-label">
                        รหัสผ่านใหม่ <span class="emp-required-mark">*</span>
                    </label>
                    <input type="password" name="new_password" id="emp-pw-new"
                           placeholder="อย่างน้อย 6 ตัวอักษร"
                           minlength="6" required class="jp-input">
                </div>
                <div>
                    <label class="emp-modal-label">
                        ยืนยันรหัสผ่าน <span class="emp-required-mark">*</span>
                    </label>
                    <input type="password" name="confirm_password" id="emp-pw-confirm"
                           placeholder="กรอกรหัสผ่านซ้ำ"
                           minlength="6" required class="jp-input">
                    <p id="emp-pw-match-hint" class="emp-pw-match-hint"></p>
                </div>
            </div>
                <div class="jp-modal-footer">
                <button type="button" data-onclick="empClosePw()"
                        class="emp-modal-btn emp-modal-btn--cancel">
                    ยกเลิก
                </button>
                <button type="submit" id="emp-pw-submit-btn"
                        class="emp-modal-btn emp-modal-btn--submit-pw">
                    Reset รหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

