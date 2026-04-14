<?php
/**
 * ChemHeaven — PDO Database Connection
 * Returns a singleton PDO instance configured for maximum security:
 *  - utf8mb4 charset
 *  - Exceptions on error (never silent failures)
 *  - Emulated prepares OFF  → real prepared statements, preventing SQL injection
 *  - No persistent connections (avoids cross-request state leakage)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
        PDO::ATTR_PERSISTENT         => false,   // no persistent connections
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log the real error server-side; never expose DB details to the client.
        error_log('[ChemHeaven] DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable. Please try again later.');
    }

    return $pdo;
}
