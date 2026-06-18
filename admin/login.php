<?php
require_once __DIR__ . '/../include/config.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email && $pass) {
        $stmt = db()->prepare('SELECT * FROM class_reps WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $rep = $stmt->fetch();
        if ($rep && $pass === $rep['password']) {
            $_SESSION['rep_id']   = $rep['id'];
            $_SESSION['rep_name'] = $rep['full_name'];
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit;
        }
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — AttendIQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#151929 0%,#1e2852 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:22px;width:100%;max-width:400px;padding:40px 36px;box-shadow:0 32px 80px rgba(0,0,0,.3)}
.logo-wrap{display:flex;align-items:center;gap:12px;margin-bottom:32px}
.logo-mark{width:44px;height:44px;background:linear-gradient(135deg,#5c4ef7,#8b5cf6);border-radius:13px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px}
.logo-name{font-size:20px;font-weight:800;color:#1a1f3c}
.logo-sub{font-size:11px;color:#7a849e;margin-top:2px}
h1{font-size:22px;font-weight:800;color:#1a1f3c;margin-bottom:6px}
.sub{font-size:13px;color:#7a849e;margin-bottom:28px}
.form-group{margin-bottom:16px}
label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#7a849e;margin-bottom:6px}
input{width:100%;padding:12px 14px;border:1.5px solid #dde1ed;border-radius:11px;font-family:inherit;font-size:14px;outline:none;transition:border-color .15s;color:#1a1f3c}
input:focus{border-color:#5c4ef7}
.error{background:#fef0f0;border:1px solid #f5b8b8;color:#c0392b;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.btn{width:100%;padding:13px;border-radius:11px;border:none;background:#5c4ef7;color:#fff;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:background .15s;margin-top:8px}
.btn:hover{background:#4538d6}
.hint{font-size:12px;color:#7a849e;text-align:center;margin-top:20px;line-height:1.7}
.hint strong{color:#1a1f3c}
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <div class="logo-mark"><i class="fa-solid fa-graduation-cap"></i></div>
    <div><div class="logo-name">AttendIQ</div><div class="logo-sub">Student Attendance System</div></div>
  </div>
  <h1>Welcome back</h1>
  <p class="sub">Sign in as Class Representative</p>
  <?php if ($error): ?>
    <div class="error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="rep@attendiq.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button class="btn" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
  </form>
  <div class="hint">Demo: <strong>rep@attendiq.com</strong> / <strong>password123</strong></div>
</div>
</body>
</html>
