<?php
require_once __DIR__ . '/../include/config.php';
// This endpoint is used by mobile clients to fetch WebAuthn challenges.
// Avoid redirecting to admin login page so it always returns JSON.
//requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$studentId = (int)($body['student_id'] ?? 0);
$action    = trim($body['action'] ?? 'verify');

if (!$studentId) {
    jsonResponse(['error' => 'student_id required'], 400);
}

$stu = db()->prepare('SELECT id, full_name, matric_no FROM students WHERE id=? AND is_active=TRUE');
$stu->execute([$studentId]);
$student = $stu->fetch();
if (!$student) {
    jsonResponse(['error' => 'Student not found'], 404);
}

// Generate a random challenge
$challengeBytes = random_bytes(32);
$challenge      = base64_encode($challengeBytes);

// Store challenge in session for later verification
$_SESSION['webauthn_challenge']    = $challenge;
$_SESSION['webauthn_student_id']   = $studentId;
$_SESSION['webauthn_action']       = $action;
$_SESSION['webauthn_challenge_ts'] = time();

// Fetch existing credential if verifying
$credentialId = null;
if ($action === 'verify') {
    $cred = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE student_id=?');
    $cred->execute([$studentId]);
    $existing = $cred->fetch();
    if ($existing) {
        $credentialId = $existing['credential_id'];
    }
}

$response = [
    'challenge'     => $challenge,
    'student_id'    => $studentId,
    'student_name'  => $student['full_name'],
    'rp_id'         => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'rp_name'       => SITE_NAME,
    'action'        => $action,
];

if ($credentialId) {
    $response['credential_id'] = $credentialId;
}

jsonResponse($response);
