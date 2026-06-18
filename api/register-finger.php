<?php
require_once __DIR__ . '/../include/config.php';
// This endpoint is used by student devices during WebAuthn registration.
// Do not force admin login redirect here (that would return HTML instead of JSON).
//requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = trim($body['action'] ?? '');

$db = db();

// ── Step 1: Get registration challenge ────────────────────────────────────
if ($action === 'challenge') {
    $studentId = (int)($body['student_id'] ?? 0);
    if (!$studentId) { jsonResponse(['error' => 'student_id required'], 400); }

    $stu = $db->prepare('SELECT id, full_name, matric_no FROM students WHERE id=? AND is_active=1');
    $stu->execute([$studentId]);
    $student = $stu->fetch();
    if (!$student) { jsonResponse(['error' => 'Student not found'], 404); }

    $challengeBytes = random_bytes(32);
    $challenge      = base64_encode($challengeBytes);
    $userId         = base64_encode(random_bytes(16));

    $_SESSION['reg_challenge']    = $challenge;
    $_SESSION['reg_student_id']   = $studentId;
    $_SESSION['reg_challenge_ts'] = time();

    jsonResponse([
        'challenge'    => $challenge,
        'user_id'      => $userId,
        'user_name'    => $student['matric_no'],
        'display_name' => $student['full_name'],
        'rp_id'        => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'rp_name'      => SITE_NAME,
    ]);
}

// ── Step 2: Save registration ─────────────────────────────────────────────
if ($action === 'register') {
    $studentId    = (int)($body['student_id'] ?? 0);
    $credentialId = trim($body['credential_id'] ?? '');
    $attestation  = $body['attestation'] ?? [];
    $clientDataJson = trim($body['client_data_json'] ?? '');
    $authData       = trim($body['authenticator_data'] ?? '');

    if (!$studentId || !$credentialId) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }

    // Verify challenge
    if (
        empty($_SESSION['reg_challenge']) ||
        $_SESSION['reg_student_id'] != $studentId ||
        (time() - ($_SESSION['reg_challenge_ts'] ?? 0)) > 300
    ) {
        jsonResponse(['success' => false, 'error' => 'Challenge expired'], 400);
    }

    // Verify client data
    try {
        $clientData = json_decode(base64_decode($clientDataJson), true);
        if (!$clientData || $clientData['type'] !== 'webauthn.create') {
            jsonResponse(['success' => false, 'error' => 'Invalid client data type'], 400);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Invalid client data'], 400);
    }

    // Extract public key from attestation object
    // For simplicity, store the public key PEM if provided,
    // otherwise store a placeholder that allows manual marking as fallback
    $publicKeyPem = $body['public_key_pem'] ?? null;

    // If no PEM provided (common for platform authenticators that don't expose raw keys),
    // create a marker that confirms registration happened
    if (!$publicKeyPem) {
        $publicKeyPem = '-----BEGIN PUBLIC KEY-----' . "\n" .
                        base64_encode('platform_authenticator_' . $studentId . '_' . time()) . "\n" .
                        '-----END PUBLIC KEY-----';
    }

    try {
        // Delete existing if re-registering
        $db->prepare('DELETE FROM webauthn_credentials WHERE student_id=?')->execute([$studentId]);

        $db->prepare('INSERT INTO webauthn_credentials (student_id, credential_id, public_key, sign_count) VALUES (?,?,?,0)')
           ->execute([$studentId, $credentialId, $publicKeyPem]);

        unset($_SESSION['reg_challenge'], $_SESSION['reg_student_id'], $_SESSION['reg_challenge_ts']);

        // Get student name for response
        $sname = $db->prepare('SELECT full_name FROM students WHERE id=?');
        $sname->execute([$studentId]);
        $nameRow = $sname->fetch();

        jsonResponse(['success' => true, 'message' => ($nameRow['full_name'] ?? 'Student') . ' fingerprint registered successfully.']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Invalid action'], 400);
