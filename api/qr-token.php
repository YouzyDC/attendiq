<?php
require_once __DIR__ . '/../include/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$studentId = (int)($body['student_id'] ?? 0);
$sessionId = (int)($body['session_id'] ?? 0);

if (!$studentId || !$sessionId) {
    jsonResponse(['error' => 'student_id and session_id required'], 400);
}

$db = db();

// Verify student exists and is active
$stu = $db->prepare('SELECT id, full_name, matric_no FROM students WHERE id=? AND is_active=1');
$stu->execute([$studentId]);
$student = $stu->fetch();
if (!$student) {
    jsonResponse(['error' => 'Student not found'], 404);
}

// Verify session exists and is open
$sess = $db->prepare('SELECT id FROM att_sessions WHERE id=? AND closed_at IS NULL');
$sess->execute([$sessionId]);
if (!$sess->fetch()) {
    jsonResponse(['error' => 'Session is closed or not found'], 400);
}

// Generate a signed token for the student attendance QR URL.
$payload = [
    'student_id' => $studentId,
    'session_id' => $sessionId,
    'issued_at' => time(),
    'expires_at' => time() + 60,
    // Add a random nonce so every token is unique even when generated quickly
    'nonce' => bin2hex(random_bytes(8)),
];

$tokenData = base64url_encode(json_encode($payload));
$signature = hash_hmac('sha256', $tokenData, QR_SECRET);
$token = $tokenData . '.' . $signature;

// Persist token signature so tokens can be marked used (one-time)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS qr_tokens (
        token_sig VARCHAR(64) PRIMARY KEY,
        student_id INT NOT NULL,
        session_id INT NOT NULL,
        issued_at INT NOT NULL,
        expires_at INT NOT NULL,
        used_at INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ins = $db->prepare('INSERT INTO qr_tokens (token_sig, student_id, session_id, issued_at, expires_at) VALUES (?,?,?,?,?)');
    $ins->execute([$signature, $studentId, $sessionId, $payload['issued_at'], $payload['expires_at']]);
} catch (Exception $e) {
    // If insert fails because of race/duplicate, continue — token exists already
}

jsonResponse([
    'url' => SITE_URL . '/api/qr-verify.php?token=' . urlencode($token),
]);
