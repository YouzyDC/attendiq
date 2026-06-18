<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db_compat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$token = $_GET['token'] ?? '';
if (!$token || !str_contains($token, '.')) {
    jsonResponse(['error' => 'Invalid token'], 400);
}

list($tokenData, $signature) = explode('.', $token, 2);
if (!hash_equals(hash_hmac('sha256', $tokenData, QR_SECRET), $signature)) {
    jsonResponse(['error' => 'Invalid token signature'], 400);
}

$payloadJson = base64url_decode($tokenData);
$payload = json_decode($payloadJson, true);
if (!$payload || !is_array($payload)) {
    jsonResponse(['error' => 'Invalid token payload'], 400);
}

$studentId = (int)($payload['student_id'] ?? 0);
$sessionId = (int)($payload['session_id'] ?? 0);
$expiresAt = (int)($payload['expires_at'] ?? 0);

if (!$studentId || !$sessionId || time() > $expiresAt) {
    jsonResponse(['error' => 'Token expired or invalid'], 400);
}

$db = db();

$stu = $db->prepare('SELECT id FROM students WHERE id=? AND is_active=TRUE');
$stu->execute([$studentId]);
if (!$stu->fetch()) {
    jsonResponse(['error' => 'Student not found'], 404);
}

$sess = $db->prepare('SELECT id FROM att_sessions WHERE id=? AND closed_at IS NULL');
$sess->execute([$sessionId]);
if (!$sess->fetch()) {
    jsonResponse(['error' => 'Session is closed or not found'], 400);
}

try {
    // Ensure token exists in DB and is unused
    $tok = $db->prepare('SELECT token_sig, expires_at, used_at FROM qr_tokens WHERE token_sig=?');
    $tok->execute([$signature]);
    $trow = $tok->fetch();
    if (!$trow) {
        jsonResponse(['error' => 'Token not found or invalid'], 400);
    }
    if (!empty($trow['used_at'])) {
        jsonResponse(['error' => 'Token already used'], 400);
    }
    if (time() > (int)$trow['expires_at']) {
        jsonResponse(['error' => 'Token expired'], 400);
    }

    insert_ignore($db, 'attendance', ['session_id', 'student_id', 'method'], [$sessionId, $studentId, 'qr']);

    // Mark token used to prevent replay
    $db->prepare('UPDATE qr_tokens SET used_at=? WHERE token_sig=?')->execute([time(), $signature]);

    $result = ['success' => true, 'message' => 'Attendance recorded via QR scan'];
} catch (PDOException $e) {
    $result = ['success' => false, 'error' => 'Database error'];
}

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$format = strtolower(trim($_GET['format'] ?? ''));
$useHtml = $format !== 'json' && str_contains($accept, 'text/html');

if ($useHtml) {
    header('Content-Type: text/html; charset=UTF-8');
    $title = $result['success'] ? 'Attendance Recorded' : 'Attendance Failed';
    $message = htmlspecialchars($result['success'] ? $result['message'] : $result['error']);
    $color = $result['success'] ? '#22c48c' : '#e84646';
    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$title} — AttendIQ</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f4f6fb;color:#1a1f3c;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;padding:24px}main{max-width:360px;background:#fff;border-radius:20px;box-shadow:0 18px 48px rgba(0,0,0,.12);padding:32px}h1{margin:0 0 12px;font-size:24px;color:{$color}}p{margin:0 0 20px;color:#556070}button{border:none;background:{$color};color:#fff;padding:12px 18px;border-radius:12px;font-size:14px;cursor:pointer}a{color:#5c4ef7;text-decoration:none}</style></head><body><main><h1>{$title}</h1><p>{$message}</p><button onclick=\"window.location.href='/'\">Back to Home</button></main></body></html>";
    exit;
}

jsonResponse($result);
