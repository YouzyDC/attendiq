-- PostgreSQL schema for AttendIQ (converted from MySQL)
-- Run this on your Supabase/Postgres database (psql or Supabase SQL editor)

-- Create enums
DO $$ BEGIN
    CREATE TYPE gender_enum AS ENUM ('Male','Female','Other');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE semester_enum AS ENUM ('First','Second');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE attendance_method_enum AS ENUM ('biometric','manual','qr');
EXCEPTION WHEN duplicate_object THEN null; END $$;

-- Class representatives (login accounts)
CREATE TABLE IF NOT EXISTS class_reps (
    id          BIGSERIAL PRIMARY KEY,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(120) NOT NULL UNIQUE,
    matric_no   VARCHAR(40)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    department  VARCHAR(100) NOT NULL DEFAULT 'Computer Science',
    level       VARCHAR(10)  NOT NULL DEFAULT '200',
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Courses
CREATE TABLE IF NOT EXISTS courses (
    id            BIGSERIAL PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL UNIQUE,
    title         VARCHAR(120) NOT NULL,
    units         SMALLINT      NOT NULL DEFAULT 3,
    instructor    VARCHAR(120) NOT NULL,
    instructor_email VARCHAR(120),
    semester      semester_enum NOT NULL DEFAULT 'First',
    session       VARCHAR(12)  NOT NULL DEFAULT '2024/2025',
    is_active     BOOLEAN      NOT NULL DEFAULT true,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Students
CREATE TABLE IF NOT EXISTS students (
    id            BIGSERIAL PRIMARY KEY,
    matric_no     VARCHAR(40)  NOT NULL UNIQUE,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(120),
    phone         VARCHAR(20),
    gender        gender_enum DEFAULT 'Male',
    is_active     BOOLEAN      NOT NULL DEFAULT true,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Timetable slots
CREATE TABLE IF NOT EXISTS timetable (
    id         BIGSERIAL PRIMARY KEY,
    course_id  BIGINT  NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    day_of_week SMALLINT NOT NULL,
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    venue      VARCHAR(60),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
    UNIQUE (course_id, day_of_week, start_time)
);

-- Attendance sessions (one per timetable slot per actual date)
CREATE TABLE IF NOT EXISTS att_sessions (
    id              BIGSERIAL PRIMARY KEY,
    timetable_id    BIGINT NOT NULL REFERENCES timetable(id) ON DELETE CASCADE,
    session_date    DATE NOT NULL,
    opened_by       BIGINT NOT NULL REFERENCES class_reps(id),
    closed_at       TIMESTAMP WITH TIME ZONE,
    notes           TEXT,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT now(),
    UNIQUE (timetable_id, session_date)
);

-- Attendance records (per student per session)
CREATE TABLE IF NOT EXISTS attendance (
    id           BIGSERIAL PRIMARY KEY,
    session_id   BIGINT NOT NULL REFERENCES att_sessions(id) ON DELETE CASCADE,
    student_id   BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    verified_at  TIMESTAMP WITH TIME ZONE DEFAULT now(),
    method       attendance_method_enum NOT NULL DEFAULT 'biometric',
    UNIQUE (session_id, student_id)
);

-- WebAuthn credentials (one per student, platform authenticator)
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id            BIGSERIAL PRIMARY KEY,
    student_id    BIGINT NOT NULL UNIQUE REFERENCES students(id) ON DELETE CASCADE,
    credential_id TEXT NOT NULL,
    public_key    TEXT NOT NULL,
    sign_count    INT  NOT NULL DEFAULT 0,
    registered_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- QR tokens for biometric flow (token signature stored, single-use)
CREATE TABLE IF NOT EXISTS qr_tokens (
    token_sig   VARCHAR(64) PRIMARY KEY,
    student_id  BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    session_id  BIGINT NOT NULL REFERENCES att_sessions(id) ON DELETE CASCADE,
    issued_at   BIGINT NOT NULL,
    expires_at  BIGINT NOT NULL,
    used_at     BIGINT DEFAULT NULL,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Optional demo seed (only run if you want demo data)
-- INSERT INTO class_reps (full_name, email, matric_no, password, department, level)
-- VALUES ('Class Representative', 'rep@attendiq.com', 'CSC/2021/REP', '<bcrypt-hash>', 'Computer Science', '200');

-- End of schema
