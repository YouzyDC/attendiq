-- ============================================================
-- ATTENDIQ — Full Schema
-- Run this once to set up the database.
-- ============================================================

CREATE DATABASE IF NOT EXISTS attendiq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendiq;

-- Class representatives (login accounts)
CREATE TABLE IF NOT EXISTS class_reps (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(120) NOT NULL UNIQUE,
    matric_no   VARCHAR(40)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    department  VARCHAR(100) NOT NULL DEFAULT 'Computer Science',
    level       VARCHAR(10)  NOT NULL DEFAULT '200',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Courses
CREATE TABLE IF NOT EXISTS courses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL UNIQUE,
    title         VARCHAR(120) NOT NULL,
    units         TINYINT      NOT NULL DEFAULT 3,
    instructor    VARCHAR(120) NOT NULL,
    instructor_email VARCHAR(120),
    semester      ENUM('First','Second') NOT NULL DEFAULT 'First',
    session       VARCHAR(12)  NOT NULL DEFAULT '2024/2025',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Students
CREATE TABLE IF NOT EXISTS students (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    matric_no     VARCHAR(40)  NOT NULL UNIQUE,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(120),
    phone         VARCHAR(20),
    gender        ENUM('Male','Female','Other') DEFAULT 'Male',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Timetable slots
CREATE TABLE IF NOT EXISTS timetable (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    course_id  INT  NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat',
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    venue      VARCHAR(60),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_slot (course_id, day_of_week, start_time)
);

-- Attendance sessions (one per timetable slot per actual date)
CREATE TABLE IF NOT EXISTS att_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id    INT NOT NULL,
    session_date    DATE NOT NULL,
    opened_by       INT NOT NULL COMMENT 'class_reps.id',
    closed_at       DATETIME,
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES timetable(id) ON DELETE CASCADE,
    FOREIGN KEY (opened_by)    REFERENCES class_reps(id),
    UNIQUE KEY uq_session (timetable_id, session_date)
);

-- Attendance records (per student per session)
CREATE TABLE IF NOT EXISTS attendance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    session_id   INT NOT NULL,
    student_id   INT NOT NULL,
    verified_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    method       ENUM('biometric','manual') NOT NULL DEFAULT 'biometric',
    FOREIGN KEY (session_id)  REFERENCES att_sessions(id)  ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES students(id)       ON DELETE CASCADE,
    UNIQUE KEY uq_att (session_id, student_id)
);

-- WebAuthn credentials (one per student, platform authenticator)
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL UNIQUE,
    credential_id TEXT NOT NULL,
    public_key    TEXT NOT NULL,
    sign_count    INT  NOT NULL DEFAULT 0,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- QR tokens for biometric flow
CREATE TABLE IF NOT EXISTS qr_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    token      VARCHAR(80)  NOT NULL UNIQUE,
    student_id INT          NOT NULL,
    session_id INT          NOT NULL,
    action     ENUM('register','verify') NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES att_sessions(id) ON DELETE CASCADE
);

-- ── Demo seed data ──────────────────────────────────────────────────────────

-- Default class rep login: email=rep@attendiq.com  password=password123
INSERT IGNORE INTO class_reps (full_name, email, matric_no, password, department, level)
VALUES ('Class Representative', 'rep@attendiq.com', 'CSC/2021/REP',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHhr/W.LS',
        'Computer Science', '200');

-- Sample courses
INSERT IGNORE INTO courses (code, title, units, instructor, instructor_email, semester, session) VALUES
('CSC301', 'Data Structures & Algorithms', 3, 'Dr. Adeyemi O.',   'adeyemi@uni.edu.ng',  'First', '2024/2025'),
('CSC303', 'Computer Networks',            3, 'Dr. Usman B.',     'usman@uni.edu.ng',    'First', '2024/2025'),
('CSC305', 'Operating Systems',            3, 'Dr. Nwosu K.',     'nwosu@uni.edu.ng',    'First', '2024/2025'),
('MTH201', 'Calculus III',                 3, 'Prof. Okonkwo R.', 'okonkwo@uni.edu.ng',  'First', '2024/2025'),
('ENG201', 'Technical Writing',            2, 'Mrs. Fashola T.',  'fashola@uni.edu.ng',  'First', '2024/2025'),
('STA201', 'Probability & Statistics',     3, 'Prof. Eze C.',     'eze@uni.edu.ng',      'First', '2024/2025');

-- Sample timetable (course_id ref above, 1-indexed by insert order)
INSERT IGNORE INTO timetable (course_id, day_of_week, start_time, end_time, venue) VALUES
(1, 1, '08:00:00', '10:00:00', 'LT-A'),   -- CSC301 Mon
(4, 1, '10:00:00', '12:00:00', 'LT-B'),   -- MTH201 Mon
(5, 1, '14:00:00', '16:00:00', 'SM-1'),   -- ENG201 Mon
(2, 2, '08:00:00', '10:00:00', 'LT-C'),   -- CSC303 Tue
(6, 2, '12:00:00', '14:00:00', 'LT-B'),   -- STA201 Tue
(1, 3, '08:00:00', '10:00:00', 'LT-A'),   -- CSC301 Wed
(3, 3, '10:00:00', '12:00:00', 'LT-D'),   -- CSC305 Wed
(2, 4, '08:00:00', '10:00:00', 'LT-C'),   -- CSC303 Thu
(5, 4, '12:00:00', '14:00:00', 'SM-1'),   -- ENG201 Thu
(3, 5, '08:00:00', '10:00:00', 'LT-D'),   -- CSC305 Fri
(4, 5, '10:00:00', '12:00:00', 'LT-B'),   -- MTH201 Fri
(6, 5, '14:00:00', '16:00:00', 'LT-B');   -- STA201 Fri

-- Sample students (20)
INSERT IGNORE INTO students (matric_no, full_name, email, gender) VALUES
('CSC/2021/001', 'Adebayo Olamide',    'adebayo@student.edu.ng',   'Male'),
('CSC/2021/002', 'Chidinma Eze',       'chidinma@student.edu.ng',  'Female'),
('CSC/2021/003', 'Emeka Obiora',       'emeka@student.edu.ng',     'Male'),
('CSC/2021/004', 'Fatimah Suleiman',   'fatimah@student.edu.ng',   'Female'),
('CSC/2021/005', 'Gbenga Afolabi',     'gbenga@student.edu.ng',    'Male'),
('CSC/2021/006', 'Hauwa Musa',         'hauwa@student.edu.ng',     'Female'),
('CSC/2021/007', 'Ike Nwachukwu',      'ike@student.edu.ng',       'Male'),
('CSC/2021/008', 'Jumoke Adeyinka',    'jumoke@student.edu.ng',    'Female'),
('CSC/2021/009', 'Kelechi Obi',        'kelechi@student.edu.ng',   'Male'),
('CSC/2021/010', 'Lawal Abdullahi',    'lawal@student.edu.ng',     'Male'),
('CSC/2021/011', 'Miriam Oduya',       'miriam@student.edu.ng',    'Female'),
('CSC/2021/012', 'Nnamdi Chukwu',      'nnamdi@student.edu.ng',    'Male'),
('CSC/2021/013', 'Oluwaseun Badmus',   'seun@student.edu.ng',      'Male'),
('CSC/2021/014', 'Precious Igwe',      'precious@student.edu.ng',  'Female'),
('CSC/2021/015', 'Quadri Olanrewaju',  'quadri@student.edu.ng',    'Male'),
('CSC/2021/016', 'Rita Okolie',        'rita@student.edu.ng',      'Female'),
('CSC/2021/017', 'Saminu Ibrahim',     'saminu@student.edu.ng',    'Male'),
('CSC/2021/018', 'Tunde Olawale',      'tunde@student.edu.ng',     'Male'),
('CSC/2021/019', 'Uche Amaechi',       'uche@student.edu.ng',      'Male'),
('CSC/2021/020', 'Victoria Okorie',    'victoria@student.edu.ng',  'Female');
