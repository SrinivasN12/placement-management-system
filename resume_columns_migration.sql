-- =============================================================
--  resume_columns_migration.sql
--  Run this SQL in phpMyAdmin or MySQL CLI ONCE to add the
--  3 resume columns to the existing `student` table in pms_db.
--
--  These columns store the current saved PDF resume info
--  so get_student_resume.php can read it quickly.
-- =============================================================

USE pms_db;

-- Add resume_path (relative URL path to the saved PDF)
ALTER TABLE student
  ADD COLUMN IF NOT EXISTS resume_path VARCHAR(255) NULL DEFAULT NULL
  AFTER city;

-- Add resume_original_name (the filename the student uploaded)
ALTER TABLE student
  ADD COLUMN IF NOT EXISTS resume_original_name VARCHAR(255) NULL DEFAULT NULL
  AFTER resume_path;

-- Add resume_uploaded_at (timestamp of upload)
ALTER TABLE student
  ADD COLUMN IF NOT EXISTS resume_uploaded_at TIMESTAMP NULL DEFAULT NULL
  AFTER resume_original_name;
