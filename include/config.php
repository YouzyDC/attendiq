<?php
// ============================================================
// ATTENDIQ — STUDENT BIOMETRIC ATTENDANCE SYSTEM
// ============================================================

// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Default MySQL config (local development)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendiq');

define('SITE_URL',  'http://localhost/attendiq');
define('SITE_NAME', 'AttendIQ');
define('DEPT_NAME', 'Computer Science Dept · 200L');

// Determine QR_SECRET (works for both MySQL and Postgres)
define('QR_SECRET', hash('sha256', SITE_URL . DB_NAME . (getenv('DATABASE_URL') ? 'supabase' : (DB_USER . DB_PASS)) . 'attendiq_qr_secret'));

date_default_timezone_set('Africa/Lagos');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dbUrl = getenv('DATABASE_URL');
            
            if ($dbUrl) {
                // Use PostgreSQL (Supabase)
                $parsed = parse_url($dbUrl);
                $host = $parsed['host'] ?? 'localhost';
                $port = $parsed['port'] ?? 5432;
                $dbname = ltrim($parsed['path'] ?? '/postgres', '/');
                $user = $parsed['user'] ?? 'postgres';
                $pass = $parsed['pass'] ?? '';
                
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $pdo->exec("SET TIME ZONE 'Africa/Lagos'");
            } else {
                // Use MySQL (local development)
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $pdo->exec("SET time_zone = '+01:00'");
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function isLoggedIn(): bool {
    return isset($_SESSION['rep_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $ini = '';
    foreach ($parts as $p) $ini .= strtoupper(substr($p, 0, 1));
    return substr($ini, 0, 2);
}

// Day index helpers (0=Sun … 6=Sat)
function dayName(int $dow): string {
    return ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$dow] ?? '';
}
