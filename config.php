<?php
declare(strict_types=1);
session_start();

$host = 'localhost';
$db   = 'wellcare_clinic';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Import database.sql in phpMyAdmin and check config.php.');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function logged_in(): bool { return isset($_SESSION['user']); }
function role(): string { return $_SESSION['user']['role'] ?? ''; }
function require_login(): void {
    if (!logged_in()) { header('Location: index.php?page=login'); exit; }
}
function flash(string $message, string $type='success'): void {
    $_SESSION['flash'] = ['message'=>$message, 'type'=>$type];
}
function csrf_field(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(403); exit('Invalid request token. Refresh and try again.');
        }
    }
}
verify_csrf();
