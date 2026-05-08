<?php
/**
 * migrate.php — One-time DB migration runner
 * เรียกใช้ครั้งเดียวเพื่อ ALTER TABLE เพิ่ม columns ที่ขาดหายไป
 * หลังรันแล้วให้ลบไฟล์นี้ทิ้งเลย
 *
 * Usage: http://host/mission_token/migrate.php?secret=OHERbnJ9ClkUsWfM7vmXFtBQ8w36Tryp
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// ── Security: ต้องผ่าน secret ──────────────────────────────
$secret = (string)($_GET['secret'] ?? '');
if (!hash_equals('OHERbnJ9ClkUsWfM7vmXFtBQ8w36Tryp', $secret)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDB();

    $migrations = [
        // 1. เพิ่ม division column ถ้ายังไม่มี
        'division column' => "
            IF NOT EXISTS (
                SELECT 1 FROM sys.columns
                WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'division'
            )
                ALTER TABLE dbo.employees ADD division NVARCHAR(20) NULL;
        ",

        // 2. เพิ่ม level column ถ้ายังไม่มี
        'level column' => "
            IF NOT EXISTS (
                SELECT 1 FROM sys.columns
                WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'level'
            )
                ALTER TABLE dbo.employees ADD level NVARCHAR(10) NULL;
        ",

        // 3. เพิ่ม coupon_code ใน rewards ถ้ายังไม่มี
        'rewards.coupon_code column' => "
            IF OBJECT_ID('dbo.rewards','U') IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM sys.columns
                WHERE object_id = OBJECT_ID('dbo.rewards') AND name = 'coupon_code'
            )
                ALTER TABLE dbo.rewards ADD coupon_code NVARCHAR(200) NULL;
        ",
    ];

    echo "=== Mission Token DB Migration ===\n\n";

    foreach ($migrations as $name => $sql) {
        try {
            $pdo->exec(trim($sql));
            echo "[OK]    $name\n";
        } catch (PDOException $e) {
            echo "[ERROR] $name: " . $e->getMessage() . "\n";
        }
    }

    // ตรวจสอบ columns ปัจจุบัน
    echo "\n=== Current employees columns ===\n";
    $cols = $pdo->query("
        SELECT name, TYPE_NAME(system_type_id) AS type, is_nullable
        FROM sys.columns
        WHERE object_id = OBJECT_ID('dbo.employees')
        ORDER BY column_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cols as $col) {
        $nullable = $col['is_nullable'] ? 'NULL' : 'NOT NULL';
        echo "  {$col['name']} {$col['type']} $nullable\n";
    }

    // ── Test API reachability ──────────────────────────────────
    echo "\n=== API Connectivity Test ===\n";
    $apiUrl = defined('EMP_API_URL') ? EMP_API_URL : 'http://203.154.130.236/emp_api/api/employee.php';
    $apiKey = defined('EMP_API_KEY') ? EMP_API_KEY : 'my-secret-key-12345';
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ["X-API-KEY: $apiKey", "Accept: application/json"],
    ]);
    $apiResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $apiResp) {
        $apiData = json_decode($apiResp, true);
        $total = $apiData['total'] ?? 0;
        echo "  [OK] API reachable — HTTP $httpCode — $total employees\n";

        // ทดสอบหา employee 110198
        $emp = null;
        foreach (($apiData['data'] ?? []) as $e) {
            if ((string)($e['employee_id'] ?? '') === '110198' || (string)($e['pws_user'] ?? '') === '110198') {
                $emp = $e;
                break;
            }
        }
        if ($emp) {
            echo "  [OK] Employee 110198 found: {$emp['first_name_th']} {$emp['last_name_th']}, pws_user={$emp['pws_user']}\n";
        } else {
            echo "  [WARN] Employee 110198 NOT found in API\n";
        }
    } else {
        echo "  [ERROR] API unreachable — HTTP $httpCode" . ($curlErr ? " — $curlErr" : '') . "\n";
        echo "  ** นี่คือสาเหตุที่ login ครั้งแรกไม่ได้ — server ไม่สามารถเรียก API ตัวเองได้ **\n";
    }

    // ── Test local DB for employee 110198 ──────────────────────
    echo "\n=== Local DB Check (employee 110198) ===\n";
    $chk = $pdo->query("SELECT employee_id, employee_code, full_name, role, password_hash, is_active FROM dbo.employees WHERE employee_code = '110198'");
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $hasHash = !empty($row['password_hash']) ? 'YES' : 'EMPTY';
        echo "  [FOUND] id={$row['employee_id']}, name={$row['full_name']}, role={$row['role']}, active={$row['is_active']}, hash=$hasHash\n";
    } else {
        echo "  [NOT FOUND] Employee 110198 is not in local DB yet (first login pending)\n";
    }

    echo "\n=== Done ===\n";
    echo "** ลบไฟล์ migrate.php ทิ้งหลังใช้งานแล้ว **\n";

} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
