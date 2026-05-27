<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? 'product_management');
$user = $_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? 'manager');
$pass = $_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? 'manager');
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$username = trim((string) ($_ENV['ADMIN_USERNAME'] ?? ''));
$password = (string) ($_ENV['ADMIN_PASSWORD'] ?? '');

if ($username === '' || $password === '') {
    fwrite(STDERR, "Set ADMIN_USERNAME and ADMIN_PASSWORD in .env\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = :u LIMIT 1');
$stmt->execute(['u' => $username]);

if ($stmt->fetch()) {
    echo "Admin user '{$username}' already exists. No changes made.\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$insert = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)');
$insert->execute(['u' => $username, 'h' => $hash]);

echo "Admin user '{$username}' created successfully.\n";
