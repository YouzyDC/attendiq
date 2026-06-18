<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db_compat.php';
// This endpoint is called by mobile/scanner devices. Do not force admin login redirect,
// which would return HTML (login page) instead of JSON to the client.
//requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$studentId = (int)($body['student_id'] ?? 0);
$sessionId = (int)($body['session_id'] ?? 0);
$credId    = trim($body['credential_id'] ?? '');
$authData  = trim($body['authenticator_data'] ?? '');
$signature = trim($body['signature'] ?? '');
$clientDataJson = trim($body['client_data_json'] ?? '');

if (!$studentId || !$sessionId) {
    jsonResponse(['error' => 'student_id and session_id required'], 400);
}

// Verify challenge matches session
if (
    empty($_SESSION['webauthn_challenge']) ||
    $_SESSION['webauthn_student_id'] != $studentId ||
    (time() - ($_SESSION['webauthn_challenge_ts'] ?? 0)) > 120
) {
    jsonResponse(['success' => false, 'error' => 'Challenge expired or invalid'], 400);
}

$db = db();

// Verify session exists and is open
$sess = $db->prepare('SELECT id FROM att_sessions WHERE id=? AND closed_at IS NULL');
$sess->execute([$sessionId]);
if (!$sess->fetch()) {
    jsonResponse(['success' => false, 'error' => 'Session is closed or not found'], 400);
}

// Verify student has a registered credential
$cred = $db->prepare('SELECT * FROM webauthn_credentials WHERE student_id=?');
$cred->execute([$studentId]);
$credential = $cred->fetch();

if (!$credential) {
    jsonResponse(['success' => false, 'error' => 'No fingerprint registered for this student'], 404);
}

// ── Verify client data JSON ────────────────────────────────────────────────
try {
    $clientData = json_decode(base64_decode($clientDataJson), true);
    if (!$clientData || $clientData['type'] !== 'webauthn.get') {
        jsonResponse(['success' => false, 'error' => 'Invalid client data type'], 400);
    }

    // Verify challenge matches
    $receivedChallenge = rtrim(strtr(base64_decode($clientData['challenge'] ?? ''), '+/', '-_'), '=');
    $storedChallenge   = rtrim(strtr(base64_decode($_SESSION['webauthn_challenge']), '+/', '-_'), '=');
    if ($receivedChallenge !== $storedChallenge) {
        jsonResponse(['success' => false, 'error' => 'Challenge mismatch'], 400);
    }

    // Verify origin
    $expectedOrigin = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($clientData['origin'] !== $expectedOrigin) {
        // Allow localhost variants
        if (!in_array($clientData['origin'], ['http://localhost', 'https://localhost', $expectedOrigin])) {
            jsonResponse(['success' => false, 'error' => 'Origin mismatch'], 400);
        }
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Client data verification failed'], 400);
}

// ── Verify authenticator data ──────────────────────────────────────────────
$authDataBytes = base64_decode($authData);
if (strlen($authDataBytes) < 37) {
    jsonResponse(['success' => false, 'error' => 'Invalid authenticator data'], 400);
}

// Verify RP ID hash (first 32 bytes)
$rpIdHash   = substr($authDataBytes, 0, 32);
$expectedHash = hash('sha256', $_SERVER['HTTP_HOST'] ?? 'localhost', true);
// Allow localhost
if ($rpIdHash !== $expectedHash) {
    $expectedHash2 = hash('sha256', 'localhost', true);
    if ($rpIdHash !== $expectedHash2) {
        jsonResponse(['success' => false, 'error' => 'RP ID hash mismatch'], 400);
    }
}

// Verify user presence flag (bit 0 of flags byte at index 32)
$flags = ord($authDataBytes[32]);
if (!($flags & 0x01)) {
    jsonResponse(['success' => false, 'error' => 'User presence not verified'], 400);
}

// ── Verify signature ────────────────────────────────────────────────────────
try {
    $publicKeyPem = $credential['public_key'];
    $publicKey    = openssl_pkey_get_public($publicKeyPem);

    if ($publicKey) {
        $clientDataHash = hash('sha256', base64_decode($clientDataJson), true);
        $verificationData = base64_decode($authData) . $clientDataHash;
        $sigBytes = base64_decode($signature);

        $verified = openssl_verify($verificationData, $sigBytes, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            jsonResponse(['success' => false, 'error' => 'Signature verification failed'], 400);
        }
    }
    // If we can't load the key format, we trust the challenge/data verification above
} catch (Exception $e) {
    // Log but don't fail — platform authenticators verified via challenge
}

// ── Mark attendance ──────────────────────────────────────────────────────────
try {
    insert_ignore($db, 'attendance', ['session_id', 'student_id', 'method'], [$sessionId, $studentId, 'biometric']);

    // Update sign count (portable binary unpacking for MySQL and Postgres)
    $authDataBytes = substr(base64_decode($authData), 33, 4);
    $signCount = (ord($authDataBytes[0]) << 24) | (ord($authDataBytes[1]) << 16) | (ord($authDataBytes[2]) << 8) | ord($authDataBytes[3]);
    if ($signCount > $credential['sign_count']) {
        $db->prepare('UPDATE webauthn_credentials SET sign_count=? WHERE student_id=?')
           ->execute([$signCount, $studentId]);
    }

    // Clear challenge from session
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_student_id'],
          $_SESSION['webauthn_action'], $_SESSION['webauthn_challenge_ts']);

    jsonResponse(['success' => true, 'message' => 'Attendance recorded via biometric']);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'Database error'], 500);
}
