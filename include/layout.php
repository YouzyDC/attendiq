<?php
// include/layout.php  — shared HTML shell
// Usage: layout_head('Page Title'); … content … layout_foot();

function layout_head(string $title, string $activePage = ''): void {
    $siteUrl = SITE_URL;
    $siteName = SITE_NAME;
    $repName  = $_SESSION['rep_name'] ?? 'Class Rep';
    $dashboardActive = $activePage === 'dashboard' ? 'active' : '';
    $attendanceActive = $activePage === 'attendance' ? 'active' : '';
    $reportsActive = $activePage === 'reports' ? 'active' : '';
    $studentsActive = $activePage === 'students' ? 'active' : '';
    $coursesActive = $activePage === 'courses' ? 'active' : '';
    $timetableActive = $activePage === 'timetable' ? 'active' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — {$siteName}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --brand:#5c4ef7;--brand-dark:#4538d6;--brand-light:#ede9ff;
  --green:#22c48c;--green-lt:#e6f9f2;
  --amber:#f5a524;--amber-lt:#fff8e7;
  --red:#e84646;  --red-lt:#fef0f0;
  --blue:#3b8df9; --blue-lt:#eff5ff;
  --page:#f2f4f9;--sidebar:#151929;--sidebar2:#1e2440;
  --card:#ffffff;--border:#e4e8f0;--border2:#d0d6e2;
  --text:#1a1f3c;--muted:#7a849e;--faint:#c0c7d8;
  --radius:12px;--radius-lg:18px;--shadow:0 2px 12px rgba(0,0,0,.07);
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--page);color:var(--text);min-height:100vh;display:grid;grid-template-columns:230px 1fr}
a{text-decoration:none;color:inherit}
button{font-family:inherit}

/* ── Sidebar ── */
.sidebar{background:var(--sidebar);display:flex;flex-direction:column;height:100vh;position:sticky;top:0;overflow-y:auto}
.sidebar-logo{padding:22px 20px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:10px}
.logo-mark{width:36px;height:36px;background:linear-gradient(135deg,var(--brand),#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;flex-shrink:0}
.logo-text{color:#fff;font-size:14px;font-weight:800;letter-spacing:.2px;line-height:1.2}
.logo-dept{font-size:10px;color:rgba(255,255,255,.35);margin-top:2px;line-height:1.3}
.nav{padding:14px 10px;flex:1}
.nav-section{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.25);padding:10px 10px 6px;margin-top:6px}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.45);font-size:13px;font-weight:500;transition:all .15s;margin-bottom:2px;cursor:pointer;border:none;background:none;width:100%;text-align:left}
.nav-link i{width:18px;text-align:center;font-size:14px;flex-shrink:0}
.nav-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.85)}
.nav-link.active{background:rgba(92,78,247,.25);color:#a49ffa}
.nav-link.danger:hover{background:rgba(232,70,70,.12);color:#f87171}
.sidebar-user{padding:14px 14px 18px;border-top:1px solid rgba(255,255,255,.06)}
.rep-chip{display:flex;align-items:center;gap:10px;padding:10px 8px}
.rep-av{width:34px;height:34px;background:linear-gradient(135deg,var(--green),#15a870);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.rep-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rep-role{font-size:10px;color:rgba(255,255,255,.35)}

/* ── Topbar ── */
.topbar{background:var(--card);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:20;gap:16px}
.topbar-title{font-size:18px;font-weight:800;color:var(--text)}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-date{font-size:12px;color:var(--muted);background:var(--page);padding:6px 12px;border-radius:8px;border:1px solid var(--border)}

/* ── Page shell ── */
.main{display:flex;flex-direction:column;min-width:0;min-height:100vh}
.content{padding:24px 28px;flex:1}

/* ── Cards ── */
.card{background:var(--card);border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow)}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:20px}

/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:22px}
.stat-card{background:var(--card);border-radius:var(--radius-lg);border:1px solid var(--border);padding:18px 20px}
.stat-lbl{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:8px}
.stat-val{font-size:30px;font-weight:800;line-height:1}
.stat-sub{font-size:11px;color:var(--muted);margin-top:5px}
.c-brand{color:var(--brand)}.c-green{color:var(--green)}.c-amber{color:var(--amber)}.c-red{color:var(--red)}.c-blue{color:var(--blue)}.c-muted{color:var(--muted)}

/* ── Tables ── */
.table-wrap{overflow-x:auto;border-radius:var(--radius-lg)}
table{width:100%;border-collapse:collapse}
th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 16px;font-size:13px;border-bottom:1px solid #f0f2f8;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafbff}
.mono{font-family:'JetBrains Mono',monospace;font-size:12px}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600}
.badge-green{background:var(--green-lt);color:#0f8a5e}
.badge-red{background:var(--red-lt);color:#c0392b}
.badge-amber{background:var(--amber-lt);color:#b07d10}
.badge-blue{background:var(--blue-lt);color:#1a6ad4}
.badge-brand{background:var(--brand-light);color:var(--brand)}
.badge-gray{background:#f0f2f8;color:var(--muted)}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;white-space:nowrap}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-dark)}
.btn-success{background:var(--green);color:#fff}.btn-success:hover{background:#18aa7c}
.btn-danger{background:var(--red);color:#fff}.btn-danger:hover{background:#cc3535}
.btn-ghost{background:var(--page);color:var(--text);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border)}
.btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
.btn-icon{width:34px;height:34px;padding:0;justify-content:center}

/* ── Forms ── */
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
input,select,textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border2);border-radius:10px;font-family:inherit;font-size:13px;color:var(--text);background:#fff;outline:none;transition:border-color .15s}
input:focus,select:focus,textarea:focus{border-color:var(--brand)}
textarea{resize:vertical;min-height:90px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}

/* ── Avatar ── */
.av{border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;flex-shrink:0}
.av-sm{width:32px;height:32px;font-size:11px;border-radius:8px}
.av-md{width:40px;height:40px;font-size:13px}
.av-lg{width:52px;height:52px;font-size:16px;border-radius:14px}

/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,20,45,.55);z-index:200;align-items:center;justify-content:center;padding:24px}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:20px;width:100%;max-width:480px;box-shadow:0 24px 64px rgba(0,0,0,.18);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.modal-title{font-size:16px;font-weight:700}
.modal-close{width:28px;height:28px;border-radius:8px;border:none;background:var(--page);color:var(--muted);cursor:pointer;font-size:13px}
.modal-body{padding:22px;overflow-y:auto}
.modal-foot{padding:16px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0}

/* ── Alert ── */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.alert-success{background:var(--green-lt);color:#0f8a5e;border:1px solid #a7ecd5}
.alert-error{background:var(--red-lt);color:#c0392b;border:1px solid #f5b8b8}
.alert-info{background:var(--blue-lt);color:#1a6ad4;border:1px solid #b3d4fd}

/* ── Misc ── */
.flex{display:flex}.items-center{align-items:center}.gap-2{gap:8px}.gap-3{gap:12px}
.justify-between{justify-content:space-between}.flex-1{flex:1}
.text-muted{color:var(--muted)}.text-sm{font-size:12px}.font-bold{font-weight:700}
.mt-1{margin-top:4px}.mt-2{margin-top:8px}.mt-4{margin-top:16px}
.mb-4{margin-bottom:16px}.mb-5{margin-bottom:20px}
.w-full{width:100%}.text-center{text-align:center}
.empty-state{text-align:center;padding:48px 24px;color:var(--muted)}
.empty-state i{font-size:42px;margin-bottom:12px;display:block;opacity:.35}
.divider{height:1px;background:var(--border);margin:16px 0}
.progress-bar-wrap{background:#e9ecf5;border-radius:100px;height:6px;overflow:hidden;flex:1}
.progress-bar{height:6px;border-radius:100px;transition:width .5s}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}

/* ── Toast ── */
#toast{position:fixed;bottom:24px;right:24px;background:#1a1f3c;color:#fff;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:500;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.18);display:none;align-items:center;gap:10px;max-width:320px}
#toast.show{display:flex}
#toast .toast-icon{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
#toast.t-success .toast-icon{background:var(--green)}
#toast.t-error   .toast-icon{background:var(--red)}

/* ── Responsive ── */
@media(max-width:900px){body{grid-template-columns:1fr}.sidebar{display:none}}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark"><i class="fa-solid fa-graduation-cap"></i></div>
    <div>
      <div class="logo-text">AttendIQ</div>
      <div class="logo-dept">Student Attendance System</div>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="{$siteUrl}/admin/dashboard.php" class="nav-link {$dashboardActive}">
      <i class="fa-solid fa-house"></i> Dashboard
    </a>
    <a href="{$siteUrl}/admin/attendance.php" class="nav-link {$attendanceActive}">
      <i class="fa-solid fa-calendar-check"></i> Attendance
    </a>
    <a href="{$siteUrl}/admin/reports.php" class="nav-link {$reportsActive}">
      <i class="fa-solid fa-chart-bar"></i> Reports
    </a>
    <div class="nav-section">Management</div>
    <a href="{$siteUrl}/admin/students.php" class="nav-link {$studentsActive}">
      <i class="fa-solid fa-users"></i> Students
    </a>
    <a href="{$siteUrl}/admin/courses.php" class="nav-link {$coursesActive}">
      <i class="fa-solid fa-book"></i> Courses
    </a>
    <a href="{$siteUrl}/admin/timetable.php" class="nav-link {$timetableActive}">
      <i class="fa-solid fa-table-cells"></i> Timetable
    </a>
    <div class="nav-section">Account</div>
    <a href="{$siteUrl}/admin/logout.php" class="nav-link danger">
      <i class="fa-solid fa-right-from-bracket"></i> Sign Out
    </a>
  </nav>
  <div class="sidebar-user">
    <div class="rep-chip">
      <div class="rep-av">CR</div>
      <div>
        <div class="rep-name">{$repName}</div>
        <div class="rep-role">Class Representative</div>
      </div>
    </div>
  </div>
</aside>

<div class="main">
HTML;
}

function layout_foot(): void {
    echo <<<HTML
</div><!-- /main -->

<div id="toast"><div class="toast-icon"><i class="fa-solid fa-check"></i></div><span id="toast-msg"></span></div>

<script>
function showToast(msg, type='success'){
  const t=document.getElementById('toast');
  const i=t.querySelector('.toast-icon i');
  t.className='show t-'+type;
  i.className=type==='success'?'fa-solid fa-check':'fa-solid fa-xmark';
  document.getElementById('toast-msg').textContent=msg;
  setTimeout(()=>{t.className=''},3500);
}
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')});
});
</script>
</body></html>
HTML;
}

function layout_topbar(string $title, string $buttons = ''): void {
    $today = date('l, F j Y');
    echo <<<HTML
  <div class="topbar">
    <div class="topbar-title">{$title}</div>
    <div class="topbar-right">
      {$buttons}
      <span class="topbar-date"><i class="fa-regular fa-calendar"></i> {$today}</span>
    </div>
  </div>
  <div class="content">
HTML;
}
