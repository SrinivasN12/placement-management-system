<?php
// =============================================================
//  setup.php  —  PMS One-Time Setup / Migration
//  Visit: http://localhost/new_pms_ag_new/setup.php
//  Safe to re-run: uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS
// =============================================================
$host = "localhost"; $user = "root"; $pass = ""; $db = "pms_db";

$conn0 = mysqli_connect($host, $user, $pass);
if (!$conn0) die("Cannot connect to MySQL: " . mysqli_connect_error());

mysqli_query($conn0, "CREATE DATABASE IF NOT EXISTS pms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn0, "pms_db");
$conn = $conn0;
mysqli_set_charset($conn, "utf8mb4");

$log = [];
function run($conn, $sql, $label) {
    global $log;
    try {
        $log[] = mysqli_query($conn, $sql)
            ? "✅ $label"
            : "❌ $label — " . mysqli_error($conn);
    } catch (mysqli_sql_exception $e) {
        $log[] = "⚠ $label — " . $e->getMessage();
    }
}

// ── Core Tables ─────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS role (
    role_id   INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
)", "Table: role");

run($conn, "INSERT IGNORE INTO role (role_name) VALUES ('Student'),('Placement Officer'),('Alumni')", "Seed roles");

run($conn, "CREATE TABLE IF NOT EXISTS user (
    user_id  INT AUTO_INCREMENT PRIMARY KEY,
    email    VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id  INT NOT NULL,
    status   VARCHAR(20) NOT NULL DEFAULT 'Active',
    FOREIGN KEY (role_id) REFERENCES role(role_id)
)", "Table: user");

// ── Student table (MBA-only, extended fields) ────────────────
run($conn, "CREATE TABLE IF NOT EXISTS student (
    student_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT          NOT NULL UNIQUE,
    uucms_number        VARCHAR(30)  NOT NULL DEFAULT '' UNIQUE,
    name                VARCHAR(100) NOT NULL,
    department          VARCHAR(50)  NOT NULL DEFAULT 'MBA',
    batch               VARCHAR(10)  NOT NULL DEFAULT '2024-26',
    specialization      VARCHAR(50)  NOT NULL DEFAULT 'General',
    cgpa                DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    backlogs            INT          NOT NULL DEFAULT 0,
    tenth_percentage    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    twelfth_percentage  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    degree_cgpa         DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    contact_number      VARCHAR(20)  NOT NULL DEFAULT '',
    country             VARCHAR(60)  NOT NULL DEFAULT '',
    state               VARCHAR(60)  NOT NULL DEFAULT '',
    city                VARCHAR(60)  NOT NULL DEFAULT '',
    preplacement_status VARCHAR(30)  NOT NULL DEFAULT 'Not Ready',
    FOREIGN KEY (user_id) REFERENCES user(user_id)
)", "Table: student");

// ── Migrate existing student table — add new columns safely ──
$student_migrations = [
    ["uucms_number",       "ALTER TABLE student ADD COLUMN uucms_number VARCHAR(30) NOT NULL DEFAULT '' AFTER user_id"],
    ["batch",              "ALTER TABLE student ADD COLUMN batch VARCHAR(10) NOT NULL DEFAULT '2024-26' AFTER department"],
    ["specialization",     "ALTER TABLE student ADD COLUMN specialization VARCHAR(50) NOT NULL DEFAULT 'General' AFTER batch"],
    ["tenth_percentage",   "ALTER TABLE student ADD COLUMN tenth_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER cgpa"],
    ["twelfth_percentage", "ALTER TABLE student ADD COLUMN twelfth_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tenth_percentage"],
    ["degree_cgpa",        "ALTER TABLE student ADD COLUMN degree_cgpa DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER twelfth_percentage"],
    ["contact_number",     "ALTER TABLE student ADD COLUMN contact_number VARCHAR(20) NOT NULL DEFAULT '' AFTER degree_cgpa"],
    ["country",            "ALTER TABLE student ADD COLUMN country VARCHAR(60) NOT NULL DEFAULT '' AFTER contact_number"],
    ["state",              "ALTER TABLE student ADD COLUMN state VARCHAR(60) NOT NULL DEFAULT '' AFTER country"],
    ["city",               "ALTER TABLE student ADD COLUMN city VARCHAR(60) NOT NULL DEFAULT '' AFTER state"],
    // ── Resume PDF columns (added for Gemini resume upload feature) ──
    ["resume_path",          "ALTER TABLE student ADD COLUMN resume_path VARCHAR(255) NULL DEFAULT NULL AFTER city"],
    ["resume_original_name", "ALTER TABLE student ADD COLUMN resume_original_name VARCHAR(255) NULL DEFAULT NULL AFTER resume_path"],
    ["resume_uploaded_at",   "ALTER TABLE student ADD COLUMN resume_uploaded_at TIMESTAMP NULL DEFAULT NULL AFTER resume_original_name"],
];
foreach ($student_migrations as [$col, $sql]) {
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA='pms_db' AND TABLE_NAME='student' AND COLUMN_NAME='$col'"));
    if ($check['c'] == 0) {
        run($conn, $sql, "Migrate student.$col");
    } else {
        $log[] = "⏭ student.$col already exists — skipped";
    }
}
// Ensure department is locked to MBA for all existing rows
run($conn, "UPDATE student SET department='MBA' WHERE department != 'MBA'", "Lock all student departments to MBA");

// ── Placement Officer ────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS placement_officer (
    officer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user(user_id)
)", "Table: placement_officer");

// ── Alumni ───────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS alumni (
    alumni_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    name         VARCHAR(100) NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user(user_id)
)", "Table: alumni");

// ── Placement Drive (MBA-only, extended eligibility) ─────────
run($conn, "CREATE TABLE IF NOT EXISTS placement_drive (
    drive_id            INT AUTO_INCREMENT PRIMARY KEY,
    job_title           VARCHAR(100) NOT NULL,
    company_name        VARCHAR(100) NOT NULL,
    description         TEXT,
    min_cgpa            DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    max_backlogs        INT          NOT NULL DEFAULT 0,
    min_tenth_pct       DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    min_twelfth_pct     DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    min_degree_cgpa     DECIMAL(3,2) NOT NULL DEFAULT 6.00,
    drive_date          DATE         NOT NULL,
    last_date           DATE         NOT NULL,
    drive_status        VARCHAR(20)  NOT NULL DEFAULT 'Open',
    posted_by           INT          NOT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES placement_officer(officer_id)
)", "Table: placement_drive");

// ── Migrate existing placement_drive table ───────────────────
$drive_migrations = [
    ["min_tenth_pct",   "ALTER TABLE placement_drive ADD COLUMN min_tenth_pct DECIMAL(5,2) NOT NULL DEFAULT 60.00 AFTER max_backlogs"],
    ["min_twelfth_pct", "ALTER TABLE placement_drive ADD COLUMN min_twelfth_pct DECIMAL(5,2) NOT NULL DEFAULT 60.00 AFTER min_tenth_pct"],
    ["min_degree_cgpa", "ALTER TABLE placement_drive ADD COLUMN min_degree_cgpa DECIMAL(3,2) NOT NULL DEFAULT 6.00 AFTER min_twelfth_pct"],
    ["last_date",       "ALTER TABLE placement_drive ADD COLUMN last_date DATE NOT NULL DEFAULT '2099-12-31' AFTER drive_date"],
    ["drive_status",    "ALTER TABLE placement_drive ADD COLUMN drive_status VARCHAR(20) NOT NULL DEFAULT 'Open' AFTER last_date"],
];
foreach ($drive_migrations as [$col, $sql]) {
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA='pms_db' AND TABLE_NAME='placement_drive' AND COLUMN_NAME='$col'"));
    if ($check['c'] == 0) {
        run($conn, $sql, "Migrate placement_drive.$col");
    } else {
        $log[] = "⏭ placement_drive.$col already exists — skipped";
    }
}
// Fix last_date = drive_date where last_date is null / zero
run($conn, "UPDATE placement_drive SET last_date=drive_date WHERE last_date='0000-00-00' OR last_date IS NULL", "Fix last_date defaults");
// Auto-close any drives whose last_date has already passed
run($conn, "UPDATE placement_drive SET drive_status='Closed' WHERE last_date < CURDATE() AND drive_status!='Closed'", "Auto-close expired drives");

// De-duplicate placement_drive rows (keep highest drive_id per job_title+company_name)
run($conn,
    "DELETE d1 FROM placement_drive d1
     INNER JOIN placement_drive d2
       ON d1.job_title = d2.job_title
      AND d1.company_name = d2.company_name
      AND d1.drive_id < d2.drive_id",
    "De-duplicate placement_drive (keep newest per job+company)");

// Add UNIQUE constraint on (job_title, company_name) if not yet present
$uq_drive = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA='pms_db' AND TABLE_NAME='placement_drive' AND INDEX_NAME='uq_drive_job_company'"));
if ($uq_drive['c'] == 0) {
    run($conn,
        "ALTER TABLE placement_drive ADD UNIQUE KEY uq_drive_job_company (job_title, company_name)",
        "Unique constraint: placement_drive(job_title, company_name)");
} else {
    $log[] = "\u23ed placement_drive.uq_drive_job_company already exists \u2014 skipped";
}

// ── Resume ───────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS resume (
    resume_id     INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT  NOT NULL,
    resume_text   TEXT NOT NULL,
    file_path     VARCHAR(255),
    uploaded_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES student(student_id)
)", "Table: resume");

// ── ATS Score ────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS ats_score (
    ats_id       INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    resume_id    INT NOT NULL,
    drive_id     INT NOT NULL,
    score        INT NOT NULL,
    checked_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES student(student_id),
    FOREIGN KEY (resume_id)  REFERENCES resume(resume_id),
    FOREIGN KEY (drive_id)   REFERENCES placement_drive(drive_id)
)", "Table: ats_score");

// ── Eligibility Result ───────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS eligibility_result (
    eligibility_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT         NOT NULL,
    drive_id       INT         NOT NULL,
    status         VARCHAR(20) NOT NULL,
    checked_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student(student_id),
    FOREIGN KEY (drive_id)   REFERENCES placement_drive(drive_id)
)", "Table: eligibility_result");

// ── Application ──────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS application (
    application_id     INT AUTO_INCREMENT PRIMARY KEY,
    student_id         INT         NOT NULL,
    drive_id           INT         NOT NULL,
    application_status VARCHAR(30) NOT NULL DEFAULT 'Applied',
    selection_date     DATE,
    applied_at         TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_drive (student_id, drive_id),
    FOREIGN KEY (student_id) REFERENCES student(student_id),
    FOREIGN KEY (drive_id)   REFERENCES placement_drive(drive_id)
)", "Table: application");

// Add unique constraint only if it doesn't already exist
$key_check = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA='pms_db' AND TABLE_NAME='application' AND INDEX_NAME='uq_student_drive'"));
if ($key_check['c'] == 0) {
    run($conn,
        "ALTER TABLE application ADD UNIQUE KEY uq_student_drive (student_id, drive_id)",
        "Unique constraint: application(student_id,drive_id)");
} else {
    $log[] = "⏭ application.uq_student_drive already exists — skipped";
}

// ── Notification ─────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT  NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    date            DATE       NOT NULL,
    FOREIGN KEY (student_id) REFERENCES student(student_id)
)", "Table: notification");

// ── Alumni Recommendation ────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS alumni_recommendation (
    recommendation_id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id         INT          NOT NULL,
    job_title         VARCHAR(100) NOT NULL,
    company_name      VARCHAR(100) NOT NULL,
    job_link          VARCHAR(255),
    description       TEXT,
    posted_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES alumni(alumni_id)
)", "Table: alumni_recommendation");

// ── Analytics Report ─────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS analytics_report (
    report_id      INT AUTO_INCREMENT PRIMARY KEY,
    generated_by   INT         NOT NULL,
    report_type    VARCHAR(50) NOT NULL,
    generated_date DATE        NOT NULL,
    FOREIGN KEY (generated_by) REFERENCES placement_officer(officer_id)
)", "Table: analytics_report");

// ── Pre-placement Evaluation ─────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS preplacement_evaluation (
    eval_id        INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT         NOT NULL UNIQUE,
    aptitude_marks INT         NOT NULL DEFAULT 0,
    aptitude_grade VARCHAR(2)  NOT NULL DEFAULT 'D',
    gd_marks       INT         NOT NULL DEFAULT 0,
    gd_grade       VARCHAR(2)  NOT NULL DEFAULT 'D',
    english_marks  INT         NOT NULL DEFAULT 0,
    english_grade  VARCHAR(2)  NOT NULL DEFAULT 'D',
    overall_status VARCHAR(20) NOT NULL DEFAULT 'Not Ready',
    updated_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student(student_id)
)", "Table: preplacement_evaluation");

// ── Sample Users ─────────────────────────────────────────────
$hash = password_hash('password123', PASSWORD_DEFAULT);

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
foreach (['officer@pms.com','student@pms.com','alumni@pms.com'] as $em) {
    $uid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM user WHERE email='$em'"))['user_id'] ?? 0;
    if ($uid) {
        mysqli_query($conn, "DELETE FROM preplacement_evaluation WHERE student_id IN (SELECT student_id FROM student WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM ats_score       WHERE student_id IN (SELECT student_id FROM student WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM application     WHERE student_id IN (SELECT student_id FROM student WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM notification    WHERE student_id IN (SELECT student_id FROM student WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM resume          WHERE student_id IN (SELECT student_id FROM student WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM alumni          WHERE user_id=$uid");
        mysqli_query($conn, "DELETE FROM student         WHERE user_id=$uid");
        mysqli_query($conn, "DELETE FROM placement_officer WHERE user_id=$uid");
        mysqli_query($conn, "DELETE FROM user            WHERE user_id=$uid");
    }
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

// Officer
mysqli_query($conn, "INSERT INTO user (email,password,role_id,status) VALUES ('officer@pms.com','$hash',2,'Active')");
$officer_uid = mysqli_insert_id($conn);  // user_id
mysqli_query($conn, "INSERT INTO placement_officer (user_id,name) VALUES ($officer_uid,'Dr. Ramesh Kumar')");
$oid = mysqli_insert_id($conn);  // officer_id — used as FK in placement_drive.posted_by
$log[] = "✅ Officer created (officer_id=$oid)";

// Student (MBA, with all new fields)
mysqli_query($conn, "INSERT INTO user (email,password,role_id,status) VALUES ('student@pms.com','$hash',1,'Active')");
$sid = mysqli_insert_id($conn);
mysqli_query($conn,
    "INSERT INTO student
     (user_id,uucms_number,name,department,batch,specialization,cgpa,backlogs,
      tenth_percentage,twelfth_percentage,degree_cgpa,
      contact_number,country,state,city,preplacement_status)
     VALUES ($sid,'UUCMS2024001','Srinivas R Naragund','MBA','2024-26','Finance',8.50,0,
             82.00,78.50,7.80,'9876543210','India','Karnataka','Dharwad','Ready')");
$log[] = "✅ Student created";

// Alumni
mysqli_query($conn, "INSERT INTO user (email,password,role_id,status) VALUES ('alumni@pms.com','$hash',3,'Active')");
$aid = mysqli_insert_id($conn);
mysqli_query($conn, "INSERT INTO alumni (user_id,name,company_name) VALUES ($aid,'Priya Sharma','Infosys')");
$log[] = "✅ Alumni created";

// Sample drive — wipe ALL drives before re-seeding to avoid duplicates
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
mysqli_query($conn, "DELETE FROM ats_score");
mysqli_query($conn, "DELETE FROM eligibility_result");
mysqli_query($conn, "DELETE FROM application");
mysqli_query($conn, "DELETE FROM placement_drive");
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
$log[] = "✅ Cleared all existing drives (prevents duplicates on re-run)";

$fdate = date('Y-m-d', strtotime('+15 days'));
$ldate = date('Y-m-d', strtotime('+10 days'));
mysqli_query($conn,
    "INSERT INTO placement_drive
     (job_title,company_name,description,min_cgpa,max_backlogs,
      min_tenth_pct,min_twelfth_pct,min_degree_cgpa,
      drive_date,last_date,drive_status,posted_by)
     VALUES ('MBA Graduate Trainee','Deloitte',
             'Leadership programme for MBA graduates. Finance/Marketing preferred.',
             7.00,0,60.00,60.00,6.00,'$fdate','$ldate','Open',$oid)");
$log[] = "✅ Sample drive created";

$hasErrors = count(array_filter($log, fn($l) => str_starts_with($l, '❌'))) > 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PMS Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 30px; }
        .box { background: #fff; max-width: 720px; width: 95%; border-radius: 14px; padding: 36px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
        h2 { color: #1a237e; margin-bottom: 20px; font-size: 22px; }
        .item { padding: 6px 0; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
        .creds { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 10px; padding: 18px; margin-top: 24px; }
        .creds p { margin: 5px 0; font-size: 14px; }
        .btn { display: inline-block; margin-top: 22px; background: #1a237e; color: #fff; padding: 13px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
        .btn:hover { background: #3949ab; }
        .warn { font-size: 12px; color: #e65100; margin-top: 16px; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔧 MBA PMS — Database Setup / Migration</h2>
    <?php foreach ($log as $l): ?>
        <div class="item"><?= $l ?></div>
    <?php endforeach; ?>

    <?php if (!$hasErrors): ?>
    <div class="creds">
        <strong>✅ Setup complete! Login password: <code>password123</code></strong>
        <p>👨‍💼 <strong>officer@pms.com</strong> → Placement Officer</p>
        <p>🎓 <strong>student@pms.com</strong>  → Student (MBA / Finance / 2024-26)</p>
        <p>🌐 <strong>alumni@pms.com</strong>   → Alumni</p>
    </div>
    <a class="btn" href="index.php">→ Go to Login</a>
    <p class="warn">⚠ Delete setup.php from the server after setup.</p>
    <?php else: ?>
    <p style="color:#c62828;margin-top:16px;font-size:14px;">⚠ Some steps had errors — review above.</p>
    <?php endif; ?>
</div>
</body>
</html>
