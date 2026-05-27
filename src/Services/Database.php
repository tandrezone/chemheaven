<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Services;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? 'product_management');
        $user = $_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? 'manager');
        $pass = $_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? 'manager');
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}
