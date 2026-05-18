-- =============================================================
--  mock_test_migration.sql
--  Run this once in phpMyAdmin or MySQL CLI.
--  Safe to re-run: uses IF NOT EXISTS / IGNORE.
-- =============================================================

USE pms_db;

-- ── Table 1: mock_tests ───────────────────────────────────────
-- Stores AI-generated question sets (one per drive+difficulty combo)
CREATE TABLE IF NOT EXISTS mock_tests (
    mock_test_id  INT AUTO_INCREMENT PRIMARY KEY,
    drive_id      INT NOT NULL,
    questions     LONGTEXT NOT NULL,          -- JSON array of 10 MCQ objects
    difficulty    ENUM('easy','medium','hard') DEFAULT 'medium',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_drive_difficulty (drive_id, difficulty),
    FOREIGN KEY (drive_id) REFERENCES placement_drive(drive_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table 2: mock_test_attempts ───────────────────────────────
-- One row per student per mock test (duplicate blocked by UNIQUE KEY)
CREATE TABLE IF NOT EXISTS mock_test_attempts (
    attempt_id    INT AUTO_INCREMENT PRIMARY KEY,
    mock_test_id  INT NOT NULL,
    student_id    INT NOT NULL,
    answers       TEXT NOT NULL,              -- JSON: {"0":2,"1":0,...}
    score         INT NOT NULL,
    total         INT NOT NULL DEFAULT 20,
    time_taken    INT NOT NULL,               -- seconds elapsed (max 600 = 20×30s)
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attempt (mock_test_id, student_id),
    FOREIGN KEY (mock_test_id) REFERENCES mock_tests(mock_test_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)   REFERENCES student(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
