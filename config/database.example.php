<?php
/**
 * config/database.php  — EXAMPLE / TEMPLATE
 *
 * Copy this file to database.php and fill in your credentials.
 * database.php is excluded from git (.gitignore) — never commit real credentials.
 *
 * For SQL Server Express: DB_SERVER = 'localhost\SQLEXPRESS'
 * For named instance:     DB_SERVER = 'SERVER_NAME\INSTANCE'
 */

define('DB_SERVER', 'localhost');        // e.g. 'localhost\SQLEXPRESS'
define('DB_NAME',   'mission_token');
define('DB_USER',   '');                // ← SQL Server username
define('DB_PASS',   '');                // ← SQL Server password

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
            http_response_code(500);
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE));
        }
    }

    return $pdo;
}
