# Supabase Migration Guide

## Step 1: Create Supabase Project
1. Go to [supabase.com](https://supabase.com)
2. Sign up / log in
3. Create new project → select your region
4. Copy your connection details (see Step 3)

## Step 2: Create Tables (Postgres Schema)
1. In Supabase Dashboard → SQL Editor
2. Click "New Query"
3. Copy the entire contents of `schema_pg.sql` from your project
4. Run the query

## Step 3: Import Data (CSV Import)
In Supabase Dashboard → Table Editor:

### For each CSV file in `tools/exports/`:
1. Click the table name (e.g., "class_reps")
2. Click **Insert** button → **Import Data** → **CSV**
3. Upload the corresponding CSV file (e.g., `class_reps.csv`)
4. Column mapping should auto-detect; review and click **Import**
5. Repeat for all tables:
   - `class_reps.csv` → class_reps table
   - `courses.csv` → courses table
   - `students.csv` → students table
   - `timetable.csv` → timetable table
   - `webauthn_credentials.csv` → webauthn_credentials table
   - (Skip empty tables: att_sessions, attendance, qr_tokens)

## Step 4: Fix Sequences (Auto-Increment)
After import, run this in Supabase SQL Editor to reset sequences:

```sql
SELECT setval(pg_get_serial_sequence('class_reps','id'), COALESCE(MAX(id),1)) FROM class_reps;
SELECT setval(pg_get_serial_sequence('courses','id'), COALESCE(MAX(id),1)) FROM courses;
SELECT setval(pg_get_serial_sequence('students','id'), COALESCE(MAX(id),1)) FROM students;
SELECT setval(pg_get_serial_sequence('timetable','id'), COALESCE(MAX(id),1)) FROM timetable;
SELECT setval(pg_get_serial_sequence('webauthn_credentials','id'), COALESCE(MAX(id),1)) FROM webauthn_credentials;
```

## Step 5: Get Connection String
1. In Supabase Dashboard → Settings → Database
2. Copy the "Connection string" (choose "Golang" or "PostgreSQL" tab)
3. Format: `postgresql://postgres:PASSWORD@HOST:5432/postgres`
4. Or set as environment variable `DATABASE_URL`

## Step 6: Update App Config
Replace `include/config.php` connection logic to use Supabase (see next section).

## Step 7: Test
- Run the app locally pointing to Supabase
- Test login, sessions, QR code generation, attendance recording
- Verify data persists in Supabase

---

## ⚠️ Security Note
The `class_reps` password is exported as plain text. After import to Supabase, hash it:

```sql
UPDATE class_reps SET password = crypt('password123', gen_salt('bf', 12)) WHERE email='rep@attendiq.com';
```

(Requires pgcrypto extension enabled in Supabase.)
