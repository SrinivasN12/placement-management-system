-- =============================================================
--  custom_criteria_migration.sql
--  Run ONCE in phpMyAdmin → pms_db → SQL tab
-- =============================================================

USE pms_db;

-- Table 1: custom criteria attached to a drive
CREATE TABLE IF NOT EXISTS drive_custom_criteria (
  criteria_id    INT          AUTO_INCREMENT PRIMARY KEY,
  drive_id       INT          NOT NULL,
  criteria_label VARCHAR(200) NOT NULL,
  criteria_type  ENUM('yes_no','number','text_match','checkbox') NOT NULL,
  criteria_value VARCHAR(255) NOT NULL DEFAULT '',
  is_mandatory   TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (drive_id) REFERENCES placement_drive(drive_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 2: student answers to custom criteria
CREATE TABLE IF NOT EXISTS student_custom_criteria_response (
  response_id      INT          AUTO_INCREMENT PRIMARY KEY,
  student_id       INT          NOT NULL,
  drive_id         INT          NOT NULL,
  criteria_id      INT          NOT NULL,
  student_response VARCHAR(255) NOT NULL DEFAULT '',
  is_eligible      TINYINT(1)   NOT NULL DEFAULT 1,
  responded_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)  REFERENCES student(student_id)                   ON DELETE CASCADE,
  FOREIGN KEY (drive_id)    REFERENCES placement_drive(drive_id)             ON DELETE CASCADE,
  FOREIGN KEY (criteria_id) REFERENCES drive_custom_criteria(criteria_id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
