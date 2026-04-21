<?php
/**
 * config/database.php
 * PDO MS SQL Server connection singleton (pdo_sqlsrv)
 *
 * Requirements:
 *  - Microsoft ODBC Driver 17 or 18 for SQL Server
 *  - PHP extension: pdo_sqlsrv  (enable in php.ini)
 *    extension=pdo_sqlsrv
 *
 * For SQL Server Express, change DB_SERVER to 'localhost\SQLEXPRESS'
 */

define('DB_SERVER', 'localhost');        // Use 'localhost\SQLEXPRESS' for Express edition
define('DB_NAME',   'mission_token');
define('DB_USER',   '');               // ← Change to your SQL Server username
define('DB_PASS',   ''); // ← Change to your SQL Server password

/**
 * Returns a shared PDO instance (singleton pattern).
 * Safe to call multiple times per request — returns same connection.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1;LoginTimeout=30',
            DB_SERVER,
            DB_NAME
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30,
                PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
            ]);
        } catch (PDOException $e) {
            error_log('[MissionToken] DB connection failed: ' . $e->getMessage());
            die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ (Error logged)');
        }
    }

    return $pdo;
}
