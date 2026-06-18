<?php
require_once __DIR__ . '/../include/config.php';

// This page is accessed by students directly on their own device
// to register their fingerprint/biometric via WebAuthn

$studentId = (int)($_GET['sid'] ?? 0);
$student   = null;
$error     = '';

if ($studentId) {
    $s = db()->prepare('SELECT id, full_name, matric_no FROM students WHERE id=? AND is_active=1');
    $s->execute([$studentId]);
    $student = $s->fetch();
    if (!$student) $error = 'Student not found or inactive.';
} else {
    // Allow searching by matric number
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['matric'])) {
        $matric = strtoupper(trim($_GET['matric']));
        $s = db()->prepare('SELECT id, full_name, matric_no FROM students WHERE matric_no=? AND is_active=1');
        $s->execute([$matric]);
        $student = $s->fetch();
        if (!$student) $error = 'Matric number not found.';
    }
}

$hasFp = false;
if ($student) {
    $fp = db()->prepare('SELECT id FROM webauthn_credentials WHERE student_id=?');
    $fp->execute([$student['id']]);
    $hasFp = (bool)$fp->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Register Fingerprint — AttendIQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#5c4ef7;--green:#22c48c;--red:#e84646;--amber:#f5a524}
body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(160deg,#0f1323 0%,#1a2352 100%);
     min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#1a1f3c}
.card{background:#fff;border-radius:22px;width:100%;max-width:380px;padding:32px 28px;box-shadow:0 32px 80px rgba(0,0,0,.35)}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:28px;justify-content:center}
.logo-mark{width:40px;height:40px;background:linear-gradient(135deg,#5c4ef7,#8b5cf6);border-radius:12px;
           display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px}
.logo-text{font-size:18px;font-weight:800;color:#1a1f3c}
h1{font-size:19px;font-weight:800;margin-bottom:6px;text-align:center}
.sub{font-size:13px;color:#7a849e;text-align:center;margin-bottom:24px;line-height:1.6}

.stu-chip{background:#f5f3ff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;margin-bottom:20px}
.stu-av{width:48px;height:48px;background:var(--brand);border-radius:13px;display:flex;align-items:center;
        justify-content:center;font-size:15px;font-weight:800;color:#fff;flex-shrink:0}
.stu-name{font-size:15px;font-weight:700}
.stu-matric{font-size:12px;color:#7a849e;margin-top:2px}

.btn{width:100%;padding:14px;border-radius:12px;border:none;font-family:inherit;font-size:15px;
     font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:#4538d6}
.btn-success{background:var(--green);color:#fff}
.btn-ghost{background:#f3f4f6;color:#374151;margin-top:8px}.btn-ghost:hover{background:#e5e7eb}
.btn:disabled{opacity:.5;cursor:not-allowed}

.state{text-align:center;padding:8px 0}
.state-icon{font-size:52px;margin-bottom:12px}
.state-title{font-size:17px;font-weight:800;margin-bottom:6px}
.state-sub{font-size:13px;color:#7a849e;line-height:1.6}

.fp-ring{width:100px;height:100px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:38px}
.fp-ring.idle{background:#f5f3ff;color:var(--brand)}
.fp-ring.scanning{background:#ede9ff;color:var(--brand);animation:pulse 1.2s ease-in-out infinite}
.fp-ring.success{background:#e6f9f2;color:var(--green)}
.fp-ring.error{background:#fef0f0;color:var(--red)}

@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}

.spinner{width:20px;height:20px;border:2.5px solid rgba(255,255,255,.35);border-top-color:#fff;
         border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

.alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;display:flex;gap:8px;align-items:center}
.alert-error{background:#fef0f0;color:#c0392b;border:1px solid #f5b8b8}
.alert-info{background:#eff5ff;color:#1a6ad4;border:1px solid #b3d4fd}
.alert-success{background:#e6f9f2;color:#0f8a5e;border:1px solid #a7ecd5}

form{display:flex;flex-direction:column;gap:10px}
label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a849e;margin-bottom:4px;display:block}
input{width:100%;padding:11px 14px;border:1.5px solid #dde1ed;border-radius:10px;font-family:inherit;
      font-size:14px;outline:none;color:#1a1f3c;transition:border-color .15s}
input:focus{border-color:var(--brand)}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-mark"><i class="fa-solid fa-graduation-cap"></i></div>
    <div class="logo-text">AttendIQ</div>
  </div>

  <?php if (!$student && !$error): ?>
    <!-- Search form -->
    <h1>Register Fingerprint</h1>
    <p class="sub">Enter your matric number to get started</p>
    <form method="GET">
      <div>
        <label>Matric Number</label>
        <input name="matric" placeholder="CSC/2021/001" required style="margin-bottom:12px">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Find Me</button>
    </form>

  <?php elseif ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <a href="register.php" class="btn btn-ghost" style="display:flex"><i class="fa-solid fa-arrow-left"></i> Try again</a>

  <?php else: ?>
    <!-- Student found — registration UI -->
    <h1><?= $hasFp ? 'Re-register' : 'Register' ?> Fingerprint</h1>
    <p class="sub">This registers your device's biometric for attendance scanning</p>

    <?php if ($hasFp): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i>You already have a fingerprint registered. Proceeding will replace it.</div>
    <?php endif; ?>

    <div class="stu-chip">
      <div class="stu-av"><?= strtoupper(substr($student['full_name'],0,1)) . strtoupper(substr(explode(' ',$student['full_name'])[1]??'?',0,1)) ?></div>
      <div>
        <div class="stu-name"><?= htmlspecialchars($student['full_name']) ?></div>
        <div class="stu-matric"><?= htmlspecialchars($student['matric_no']) ?></div>
      </div>
    </div>

    <div id="reg-ui">
      <div class="state">
        <div class="fp-ring idle" id="fp-ring"><i class="fa-solid fa-fingerprint"></i></div>
        <div class="state-title" id="state-title">Ready to register</div>
        <div class="state-sub" id="state-sub">Tap the button below, then use your fingerprint, face ID, or device PIN when prompted.</div>
      </div>
      <div style="margin-top:20px;display:flex;flex-direction:column;gap:8px">
        <button class="btn btn-primary" id="reg-btn" onclick="startRegistration()">
          <i class="fa-solid fa-fingerprint"></i> Register Biometric
        </button>
        <a href="register.php" class="btn btn-ghost" style="display:flex"><i class="fa-solid fa-arrow-left"></i> Change Student</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($student): ?>
<script>
const STUDENT_ID   = <?= $student['id'] ?>;
const STUDENT_NAME = <?= json_encode($student['full_name']) ?>;
const API_BASE     = '<?= SITE_URL ?>/api';

function setState(icon, ringClass, title, sub) {
  const ring = document.getElementById('fp-ring');
  ring.className = 'fp-ring ' + ringClass;
  ring.innerHTML = icon;
  document.getElementById('state-title').textContent = title;
  document.getElementById('state-sub').textContent   = sub;
}

async function startRegistration() {
  if (!window.PublicKeyCredential) {
    setState('<i class="fa-solid fa-triangle-exclamation"></i>', 'error',
      'Not Supported', 'This device or browser does not support biometric authentication.');
    return;
  }

  const btn = document.getElementById('reg-btn');
  btn.disabled = true;
  setState('<i class="fa-solid fa-fingerprint"></i>', 'scanning',
    'Requesting challenge…', 'Please wait…');

  try {
    // Step 1: Get challenge
    const chalRes = await fetch(`${API_BASE}/register-finger.php`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'challenge', student_id: STUDENT_ID })
    });
    const chal = await chalRes.json();
    if (!chal.challenge) throw new Error(chal.error || 'Failed to get challenge');

    setState('<i class="fa-solid fa-fingerprint"></i>', 'scanning',
      'Scan your fingerprint', 'Follow your device prompt…');

    // Step 2: Create credential
    const challengeBytes = Uint8Array.from(atob(chal.challenge), c => c.charCodeAt(0));
    const userIdBytes    = Uint8Array.from(atob(chal.user_id), c => c.charCodeAt(0));

    const credOpts = {
      challenge: challengeBytes,
      rp: { id: chal.rp_id, name: chal.rp_name },
      user: {
        id: userIdBytes,
        name: chal.user_name,
        displayName: chal.display_name,
      },
      pubKeyCredParams: [
        { type:'public-key', alg:-7  },  // ES256
        { type:'public-key', alg:-257 }, // RS256
      ],
      authenticatorSelection: {
        authenticatorAttachment: 'platform',
        userVerification: 'preferred',
        requireResidentKey: false,
      },
      timeout: 60000,
      attestation: 'none',
    };

    const credential = await navigator.credentials.create({ publicKey: credOpts });

    setState('<span style="font-size:38px">⏳</span>', 'scanning',
      'Saving fingerprint…', 'Almost done…');

    // Step 3: Send to server
    const credId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
    const authData = btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData)));
    const clientDataJson = btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON)));

    const regRes = await fetch(`${API_BASE}/register-finger.php`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        action: 'register',
        student_id: STUDENT_ID,
        credential_id: credId,
        authenticator_data: authData,
        client_data_json: clientDataJson,
      })
    });
    const regData = await regRes.json();

    if (regData.success) {
      setState('✅', 'success', 'Registered!', regData.message);
      document.getElementById('reg-btn').style.display = 'none';
    } else {
      throw new Error(regData.error || 'Registration failed');
    }
  } catch (err) {
    console.error(err);
    if (err.name === 'NotAllowedError') {
      setState('<i class="fa-solid fa-ban"></i>', 'error',
        'Cancelled', 'You cancelled the scan. Tap the button to try again.');
    } else {
      setState('<i class="fa-solid fa-circle-exclamation"></i>', 'error',
        'Failed', err.message || 'Something went wrong. Please try again.');
    }
    const btn = document.getElementById('reg-btn');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-fingerprint"></i> Try Again';
  }
}
</script>
<?php endif; ?>
</body>
</html>
