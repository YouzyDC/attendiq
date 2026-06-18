# AttendIQ — Computerized Student Attendance System with Biometric

A full PHP/MySQL attendance management system for university class representatives,
featuring WebAuthn biometric scanning (fingerprint / Face ID), calendar-based
attendance tracking, and detailed analytics.

---

## Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- Browser that supports WebAuthn (Chrome 67+, Firefox 60+, Safari 14+, Edge 79+)
- HTTPS is **required** for WebAuthn on production (works on localhost without it)

---

## Installation

### 1. Copy files
Place the `attendiq/` folder inside your web root:
```
/var/www/html/attendiq/       ← Linux Apache
C:\xampp\htdocs\attendiq\     ← Windows XAMPP
```

### 2. Create the database
Open phpMyAdmin or run MySQL from the terminal:
```sql
mysql -u root -p < attendiq/schema.sql
```
Or paste the contents of `schema.sql` into phpMyAdmin's SQL tab.

### 3. Configure the app
Edit `attendiq/include/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'attendiq');
define('SITE_URL', 'http://localhost/attendiq');   // ← no trailing slash
```

### 4. Open in browser
```
http://localhost/attendiq/
```

Default login:
- **Email:** rep@attendiq.com
- **Password:** password123

---

## Pages

| Page | URL | Description |
|------|-----|-------------|
| Login | `/admin/login.php` | Class rep sign-in |
| Dashboard | `/admin/dashboard.php` | Overview + today's classes |
| Attendance | `/admin/attendance.php` | Calendar + per-session roster |
| Reports | `/admin/reports.php` | Analytics, grades, CSV export |
| Courses | `/admin/courses.php` | Add / edit / deactivate courses |
| Timetable | `/admin/timetable.php` | Weekly schedule slots |
| Students | `/admin/students.php` | Student register + fingerprint status |
| Register FP | `/mobile/register.php` | Student self-registers fingerprint on their device |

---

## Biometric Flow

### How it works
1. **Registration** — Student opens `/mobile/register.php?matric=CSC/2021/001` on their own phone/laptop.  
   The page calls `navigator.credentials.create()` (WebAuthn) and saves the credential ID to the `webauthn_credentials` table.

2. **Verification** — During an attendance session, the class rep clicks **Scan** next to a student's name.  
   The app calls `navigator.credentials.get()` which triggers the device biometric prompt (fingerprint sensor, Face ID, Windows Hello, etc.).  
   The response is verified server-side against the stored challenge and credential.

3. **Fallback** — If the device doesn't support WebAuthn or the student's credential isn't enrolled on that device, the class rep can mark attendance **manually**.

### Getting students to register
Share the link: `http://your-domain/attendiq/mobile/register.php`  
Students enter their matric number and follow the browser prompt.

For QR code-based access: generate a QR pointing to that URL — any free QR code generator works.

---

## Grading Scale (Reports)

| Grade | Rate |
|-------|------|
| A | 80–100% |
| B | 70–79% |
| C | 60–69% |
| D | 50–59% |
| F | < 50% |

---

## File Structure
```
attendiq/
├── index.php                  ← Redirects to login/dashboard
├── schema.sql                 ← Database setup (run once)
├── README.md
├── include/
│   ├── config.php             ← DB credentials, app settings, helpers
│   └── layout.php             ← Shared sidebar/topbar HTML shell
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── attendance.php         ← Main attendance page with calendar
│   ├── reports.php            ← Analytics + CSV export
│   ├── courses.php            ← Course CRUD
│   ├── timetable.php          ← Timetable CRUD
│   └── students.php           ← Student CRUD + fingerprint management
├── api/
│   ├── webauthn-challenge.php ← Issues WebAuthn challenge
│   ├── verify-attendance.php  ← Verifies biometric + records attendance
│   └── register-finger.php   ← Handles credential registration
└── mobile/
    └── register.php           ← Student-facing fingerprint registration page
```

---

## Notes
- The demo seed inserts 20 students, 6 courses, and a full Mon–Fri timetable.
- The default class rep password is hashed with `password_hash(..., PASSWORD_BCRYPT)` — change it after deployment.
- WebAuthn works on `localhost` for development without HTTPS.
- For production, deploy over HTTPS and update `SITE_URL` in `config.php`.
