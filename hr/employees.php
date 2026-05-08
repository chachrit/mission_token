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
            ($note ?: ($amount > 0 ? 'โบนัสจาก HR/Admin' : 'ปรับลด Token โดย HR/Admin')));
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

<div class="emp-employees-wrap emp-wrap" style="min-height:100vh; position:relative; overflow-x:hidden;">

    <!-- Aurora blobs -->
    <div style="position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden;" aria-hidden="true">
        <div style="position:absolute; width:600px; height:600px; border-radius:50%;
                    background:radial-gradient(circle,rgba(218,185,55,0.07) 0%,transparent 65%);
                    top:-120px; right:-120px; filter:blur(70px);
                    animation:ch-aurora-drift 20s ease-in-out infinite alternate;"></div>
        <div style="position:absolute; width:500px; height:500px; border-radius:50%;
                    background:radial-gradient(circle,rgba(79,139,152,0.05) 0%,transparent 65%);
                    bottom:-100px; left:-80px; filter:blur(80px);
                    animation:ch-aurora-drift 24s ease-in-out infinite alternate-reverse;"></div>
    </div>

    <div style="position:relative; z-index:1; max-width:90rem; margin:0 auto; padding:2.5rem 1.5rem 5rem;">

        <!-- Page header -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between;
                    flex-wrap:wrap; gap:1rem; margin-bottom:2rem;
                    padding-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);">
            <div>
                <p style="font-size:0.55rem; font-weight:700; letter-spacing:0.40em;
                          text-transform:uppercase; color:rgba(218,185,55,0.60); margin:0 0 0.5rem;">
                    ⬡ &nbsp;ADMIN — EMPLOYEES
                </p>
                <h1 style="font-size:1.75rem; font-weight:800; color:#eeebe1; margin:0 0 0.25rem; letter-spacing:-0.02em;">
                    จัดการพนักงาน
                </h1>
                <p style="font-size:0.82rem; color:#6b6e77; margin:0;">
                    ดู แก้ไขสิทธิ์ และจัดการ Token ของพนักงานทุกคน
                </p>
            </div>
            <!-- Stats chips -->
            <div style="display:flex; align-items:center; gap:0.55rem; flex-wrap:wrap;">
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(218,185,55,0.10); color:#f8e769; border:1px solid rgba(218,185,55,0.25);">
                    ทั้งหมด: <?= (int)($statsRow['total'] ?? 0) ?>
                </span>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(81,142,92,0.12); color:#7ec98a; border:1px solid rgba(81,142,92,0.28);">
                    ใช้งาน: <?= (int)($statsRow['active_count'] ?? 0) ?>
                </span>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(98,48,122,0.18); color:#c49de0; border:1px solid rgba(98,48,122,0.35);">
                    Admin: <?= (int)($statsRow['admin_count'] ?? 0) ?>
                </span>
                <span style="font-size:0.75rem; font-weight:700; padding:0.3rem 0.85rem; border-radius:999px;
                             background:rgba(79,139,152,0.15); color:#7ab8c4; border:1px solid rgba(79,139,152,0.32);">
                    HR: <?= (int)($statsRow['hr_count'] ?? 0) ?>
                </span>
            </div>
        </div>

        <!-- Search + Filter -->
        <form method="GET" action=""
              style="display:flex; flex-wrap:wrap; gap:0.65rem; margin-bottom:1.75rem; align-items:center;">
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
            <button type="submit"
                    style="padding:0.55rem 1.1rem; font-size:0.82rem; font-weight:600; border-radius:10px;
                           cursor:pointer; font-family:'Prompt',sans-serif; border:none;
                           background:rgba(218,185,55,0.15); color:#f8e769;
                           border:1px solid rgba(218,185,55,0.30); transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(218,185,55,0.25)'"
                    onmouseout="this.style.background='rgba(218,185,55,0.15)'">
                ค้นหา
            </button>
            <?php if ($search || $roleFilter || $deptFilter || $statusFilter): ?>
            <a href="<?= BASE_URL ?>/hr/employees.php"
               style="padding:0.55rem 1.1rem; font-size:0.82rem; font-weight:600; border-radius:10px;
                      font-family:'Prompt',sans-serif; text-decoration:none;
                      background:rgba(255,255,255,0.05); color:#6b6e77;
                      border:1px solid rgba(255,255,255,0.10); transition:all 0.15s;"
               onmouseover="this.style.color='#eeebe1'"
               onmouseout="this.style.color='#6b6e77'">
                ล้างการค้นหา
            </a>
            <span style="font-size:0.75rem; color:#8a8e97; padding:0.3rem 0;">
                พบ <?= count($employees) ?> รายการ
            </span>
            <?php endif; ?>
        </form>

        <!-- Employee Table -->
        <div class="emp-table-outer" style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08);
                    border-radius:16px; backdrop-filter:blur(8px);">

            <!-- Table header -->
            <div class="emp-table-header">
                <span>พนักงาน</span>
                <span>ตำแหน่ง / แผนก</span>
                <span>อายุงาน</span>
                <span>Role</span>
                <span>Token</span>
                <span>สถานะ</span>
                <span>จัดการ</span>
            </div>

            <?php if (empty($employees)): ?>
            <div style="padding:4rem 2rem; text-align:center;">
                <p style="font-size:2rem; opacity:0.15; margin-bottom:0.6rem;">👤</p>
                <p style="font-size:0.88rem; color:#6b6e77; margin:0;">ไม่พบพนักงานที่ตรงกับเงื่อนไข</p>
            </div>
            <?php else: ?>

            <?php foreach ($employees as $emp):
                $isMe    = ((int)$emp['employee_id'] === $myId);
                $isOn    = (bool)$emp['is_active'];
                $rMeta   = $roleMeta[$emp['role']] ?? $roleMeta['employee'];
                $balance = (int)$emp['balance'];
            ?>
            <div class="emp-row <?= $isOn ? '' : 'emp-row-inactive' ?>">

                <!-- Avatar + Name -->
                <div style="display:flex; align-items:center; gap:0.65rem; min-width:0;">
                    <div style="width:36px; height:36px; border-radius:50%; flex-shrink:0;
                                background:linear-gradient(135deg,rgba(218,185,55,0.22),rgba(218,185,55,0.08));
                                border:1px solid rgba(218,185,55,0.28);
                                display:flex; align-items:center; justify-content:center;
                                font-size:0.9rem; font-weight:700; color:#dab937;">
                        <?= mb_substr($emp['full_name'], 0, 1) ?>
                    </div>
                    <div style="min-width:0;">
                        <p style="font-size:0.87rem; font-weight:600; color:#eeebe1; margin:0;
                                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($emp['full_name']) ?>
                            <?php if ($isMe): ?>
                            <span style="font-size:0.60rem; color:#6b6e77; margin-left:4px;">(คุณ)</span>
                            <?php endif; ?>
                        </p>
                        <p style="font-size:0.68rem; color:#8a8e97; margin:0; font-family:monospace,sans-serif;">
                            <?= e($emp['employee_code']) ?>
                        </p>
                    </div>
                </div>

                <!-- Position / Dept -->
                <div style="min-width:0;">
                    <p style="font-size:0.80rem; color:#eeebe1; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($emp['position'] ?? '—') ?>
                    </p>
                    <p style="font-size:0.68rem; color:#8a8e97; margin:0;
                               white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= e($emp['department'] ?? '—') ?>
                    </p>
                </div>

                <!-- Tenure -->
                <?php $_et = getWorkTenure($emp['start_date'] ?? null); ?>
                <div>
                    <?php if ($_et): ?>
                    <p style="font-size:0.78rem; font-weight:600; color:#dab937; margin:0; white-space:nowrap;">
                        <?= e($_et['text']) ?>
                    </p>
                    <p style="font-size:0.60rem; color:#6b6e77; margin:0;">
                        <?= number_format($_et['total_days']) ?> วัน
                    </p>
                    <?php else: ?>
                    <p style="font-size:0.72rem; color:#6b6e77; margin:0;">—</p>
                    <?php endif; ?>
                </div>

                <!-- Role badge + change (admin only) -->
                <div>
                    <?php if ($isAdminOnly && !$isMe): ?>
                    <form method="POST" action=""
                          onsubmit="return confirm('เปลี่ยน Role ของ <?= addslashes(e($emp['full_name'])) ?> ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"      value="change_role">
                        <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                        <input type="hidden" name="qs"          value="<?= e($qs) ?>">
                        <select name="role" class="emp-role-select"
                                onchange="this.form.submit()"
                                style="background:<?= $rMeta['bg'] ?>; color:<?= $rMeta['color'] ?>;
                                       border-color:<?= $rMeta['border'] ?>;">
                            <?php foreach ($roleMeta as $rk => $rm): ?>
                            <option value="<?= $rk ?>" <?= $emp['role'] === $rk ? 'selected' : '' ?>>
                                <?= $rm['label'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <span class="emp-role-badge"
                          style="background:<?= $rMeta['bg'] ?>; color:<?= $rMeta['color'] ?>;
                                 border:1px solid <?= $rMeta['border'] ?>;">
                        <?= $rMeta['label'] ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Token balance -->
                <div>
                    <div style="display:flex; align-items:center; gap:0.3rem;">
                        <img src="<?= BASE_URL ?>/assets/images/token.png"
                             width="13" height="13" style="object-fit:contain; opacity:0.65;" alt="">
                        <span style="font-size:0.9rem; font-weight:700; color:#f8e769;">
                            <?= formatTokens($balance) ?>
                        </span>
                    </div>
                </div>

                <!-- Active toggle -->
                <div>
                    <?php if ($canManage && !$isMe): ?>
                    <form method="POST" action=""
                          onsubmit="return confirm('<?= $isOn ? 'ปิดบัญชี' : 'เปิดบัญชี' ?> ของ <?= addslashes(e($emp['full_name'])) ?> ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"      value="toggle_active">
                        <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                        <input type="hidden" name="qs"          value="<?= e($qs) ?>">
                        <button type="submit" class="emp-toggle <?= $isOn ? 'on' : '' ?>"
                                title="<?= $isOn ? 'คลิกเพื่อปิดบัญชี' : 'คลิกเพื่อเปิดบัญชี' ?>"></button>
                    </form>
                    <span style="font-size:0.63rem; color:<?= $isOn ? '#7ec98a' : '#3a3e43' ?>; display:block; margin-top:2px;">
                        <?= $isOn ? 'ใช้งาน' : 'ปิด' ?>
                    </span>
                    <?php else: ?>
                    <span style="font-size:0.75rem; font-weight:600;
                                 color:<?= $isOn ? '#7ec98a' : '#3a3e43' ?>;">
                        <?= $isOn ? '● ใช้งาน' : '○ ปิด' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Action buttons -->
                <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                    <?php if ($canManage): ?>
                    <button onclick="empOpenAdjust(<?= (int)$emp['employee_id'] ?>, '<?= addslashes(e($emp['full_name'])) ?>', <?= $balance ?>, '<?= e($qs) ?>')"
                            style="font-size:0.68rem; padding:0.22rem 0.55rem; border-radius:6px;
                                   background:rgba(218,185,55,0.10); color:#dab937;
                                   border:1px solid rgba(218,185,55,0.25);
                                   font-family:'Prompt',sans-serif; font-weight:600;
                                   cursor:pointer; white-space:nowrap; transition:background 0.15s;"
                            onmouseover="this.style.background='rgba(218,185,55,0.22)'"
                            onmouseout="this.style.background='rgba(218,185,55,0.10)'">
                        ⚖ Token
                    </button>
                    <?php endif; ?>
                    <?php if ($isAdminOnly && !$isMe): ?>
                    <button onclick="empOpenPw(<?= (int)$emp['employee_id'] ?>, '<?= addslashes(e($emp['full_name'])) ?>', '<?= e($qs) ?>')"
                            style="font-size:0.68rem; padding:0.22rem 0.55rem; border-radius:6px;
                                   background:rgba(255,255,255,0.05); color:#6b6e77;
                                   border:1px solid rgba(255,255,255,0.12);
                                   font-family:'Prompt',sans-serif; font-weight:600;
                                   cursor:pointer; white-space:nowrap; transition:all 0.15s;"
                            onmouseover="this.style.color='#eeebe1'; this.style.borderColor='rgba(255,255,255,0.25)'"
                            onmouseout="this.style.color='#6b6e77'; this.style.borderColor='rgba(255,255,255,0.12)'">
                        🔑 Reset PW
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
<div id="emp-adjust-modal" style="display:none; position:fixed; inset:0; z-index:9000;
     background:rgba(0,0,0,0.72); display:none; align-items:center; justify-content:center; padding:1rem;">
    <div style="background:rgba(15,20,23,0.97); border:1px solid rgba(218,185,55,0.18);
                border-radius:20px; width:100%; max-width:420px; overflow:hidden;
                box-shadow:0 0 0 1px rgba(255,255,255,0.04), 0 32px 80px rgba(9,17,19,0.80);
                backdrop-filter:blur(20px);">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);
                    display:flex; align-items:center; justify-content:space-between;">
            <p id="emp-adjust-title" style="font-size:0.92rem; font-weight:700; color:#eeebe1; margin:0;"></p>
            <button onclick="empCloseAdjust()"
                    style="background:none; border:none; color:#6b6e77; cursor:pointer; font-size:1.1rem; line-height:1;"
                    onmouseover="this.style.color='#eeebe1'"
                    onmouseout="this.style.color='#6b6e77'">✕</button>
        </div>
        <form id="emp-adjust-form" method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action"      value="adjust_token">
            <input type="hidden" name="employee_id" id="emp-adjust-emp-id">
            <input type="hidden" name="qs"          id="emp-adjust-qs">
            <input type="hidden" name="amount"       id="emp-adjust-amount-final">
            <div style="padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1rem;">
                <div style="background:rgba(218,185,55,0.06); border:1px solid rgba(218,185,55,0.14);
                            border-radius:12px; padding:0.75rem 1rem; font-size:0.78rem; color:#8a8e97;">
                    Token ปัจจุบัน: <span id="emp-adjust-balance" style="font-weight:700; color:#f8e769;"></span>
                </div>

                <!-- Mode toggle -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <button type="button" id="adj-btn-add"
                            onclick="empSetMode('add')"
                            style="padding:0.55rem; border-radius:10px; font-size:0.82rem; font-weight:700;
                                   font-family:'Prompt',sans-serif; cursor:pointer; transition:all 0.15s;
                                   background:rgba(81,142,92,0.25); border:1px solid rgba(81,142,92,0.50); color:#7ec98a;">
                        + เพิ่ม Token
                    </button>
                    <button type="button" id="adj-btn-deduct"
                            onclick="empSetMode('deduct')"
                            style="padding:0.55rem; border-radius:10px; font-size:0.82rem; font-weight:700;
                                   font-family:'Prompt',sans-serif; cursor:pointer; transition:all 0.15s;
                                   background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.10); color:#6b6e77;">
                        &minus; หัก Token
                    </button>
                </div>

                <div>
                    <label id="adj-amount-label" style="font-size:0.70rem; font-weight:700; color:#8a8e97;
                                  letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.35rem; display:block;">
                        จำนวน Token ที่จะเพิ่ม <span style="color:#d2592a;">*</span>
                    </label>
                    <input type="number" id="emp-adjust-amount" min="1"
                           placeholder="ระบุจำนวนเป็นบวกเสมอ"
                           class="emp-modal-input" required>
                </div>
                <div>
                    <label style="font-size:0.70rem; font-weight:700; color:#8a8e97;
                                  letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.35rem; display:block;">
                        หมายเหตุ
                    </label>
                    <input type="text" name="note" maxlength="200"
                           placeholder="เช่น โบนัสประจำเดือน, ปรับปรุงยอด…"
                           class="emp-modal-input">
                </div>
            </div>
            <div style="padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,0.07);
                        display:flex; gap:0.65rem; justify-content:flex-end;">
                <button type="button" onclick="empCloseAdjust()"
                        style="padding:0.5rem 1rem; font-size:0.82rem; font-weight:600; border-radius:10px;
                               cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(255,255,255,0.06); color:#eeebe1;
                               border:1px solid rgba(255,255,255,0.12); transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                    ยกเลิก
                </button>
                <button type="submit" id="emp-adjust-submit-btn"
                        style="padding:0.5rem 1.1rem; font-size:0.82rem; font-weight:700; border-radius:10px;
                               cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(81,142,92,0.25); color:#7ec98a;
                               border:1px solid rgba(81,142,92,0.50); transition:all 0.15s;"
                        onmouseover="this.style.opacity='0.85'"
                        onmouseout="this.style.opacity='1'">
                    เพิ่ม Token
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ Modal: Reset Password (admin only) ═══════════════════ -->
<?php if ($isAdminOnly): ?>
<div id="emp-pw-modal" style="display:none; position:fixed; inset:0; z-index:9000;
     background:rgba(0,0,0,0.72); display:none; align-items:center; justify-content:center; padding:1rem;">
    <div style="background:rgba(15,20,23,0.97); border:1px solid rgba(210,89,42,0.22);
                border-radius:20px; width:100%; max-width:420px; overflow:hidden;
                box-shadow:0 0 0 1px rgba(255,255,255,0.04), 0 32px 80px rgba(9,17,19,0.80);
                backdrop-filter:blur(20px);">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.07);
                    display:flex; align-items:center; justify-content:space-between;">
            <p id="emp-pw-title" style="font-size:0.92rem; font-weight:700; color:#eeebe1; margin:0;"></p>
            <button onclick="empClosePw()"
                    style="background:none; border:none; color:#6b6e77; cursor:pointer; font-size:1.1rem; line-height:1;"
                    onmouseover="this.style.color='#eeebe1'"
                    onmouseout="this.style.color='#6b6e77'">✕</button>
        </div>
        <form id="emp-pw-form" method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action"      value="reset_password">
            <input type="hidden" name="employee_id" id="emp-pw-emp-id">
            <input type="hidden" name="qs"          id="emp-pw-qs">
            <div style="padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1rem;">
                <div style="background:rgba(210,89,42,0.08); border:1px solid rgba(210,89,42,0.20);
                            border-radius:10px; padding:0.65rem 1rem; font-size:0.75rem; color:#d2592a;">
                    ⚠ รหัสผ่านใหม่จะมีผลทันที พนักงานจะต้อง login ด้วยรหัสผ่านใหม่นี้
                </div>
                <div>
                    <label style="font-size:0.70rem; font-weight:700; color:#8a8e97;
                                  letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.35rem; display:block;">
                        รหัสผ่านใหม่ <span style="color:#d2592a;">*</span>
                    </label>
                    <input type="password" name="new_password" id="emp-pw-new"
                           placeholder="อย่างน้อย 6 ตัวอักษร"
                           minlength="6" required class="emp-modal-input">
                </div>
                <div>
                    <label style="font-size:0.70rem; font-weight:700; color:#8a8e97;
                                  letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.35rem; display:block;">
                        ยืนยันรหัสผ่าน <span style="color:#d2592a;">*</span>
                    </label>
                    <input type="password" name="confirm_password" id="emp-pw-confirm"
                           placeholder="กรอกรหัสผ่านซ้ำ"
                           minlength="6" required class="emp-modal-input">
                    <p id="emp-pw-match-hint" style="font-size:0.68rem; margin-top:0.3rem;"></p>
                </div>
            </div>
            <div style="padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,0.07);
                        display:flex; gap:0.65rem; justify-content:flex-end;">
                <button type="button" onclick="empClosePw()"
                        style="padding:0.5rem 1rem; font-size:0.82rem; font-weight:600; border-radius:10px;
                               cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(255,255,255,0.06); color:#eeebe1;
                               border:1px solid rgba(255,255,255,0.12); transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                    ยกเลิก
                </button>
                <button type="submit" id="emp-pw-submit-btn"
                        style="padding:0.5rem 1.1rem; font-size:0.82rem; font-weight:700; border-radius:10px;
                               cursor:pointer; font-family:'Prompt',sans-serif;
                               background:rgba(210,89,42,0.15); color:#d2592a;
                               border:1px solid rgba(210,89,42,0.35); transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(210,89,42,0.28)'"
                        onmouseout="this.style.background='rgba(210,89,42,0.15)'">
                    Reset รหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
