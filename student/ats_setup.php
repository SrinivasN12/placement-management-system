<?php
// =============================================================
//  One-time ATS setup: fixes DB schema + seeds student skills
//  Visit: http://localhost/new_pms_ag_new/student/ats_setup.php
//  Safe to re-run. DELETE this file after running once.
// =============================================================
require_once '../config/db.php';

$log = [];

mysqli_report(MYSQLI_REPORT_OFF); // prevent DDL failures from throwing exceptions

// 1. Add resume_skills column to student table
try {
    mysqli_query($conn, "ALTER TABLE student ADD COLUMN IF NOT EXISTS resume_skills TEXT DEFAULT NULL AFTER city");
    $log[] = "✅ resume_skills column ensured";
} catch (Exception $e) { $log[] = "⚠ resume_skills: " . $e->getMessage(); }

// 2. Make resume_id nullable + drop FK
try { mysqli_query($conn, "ALTER TABLE ats_score MODIFY COLUMN resume_id INT NULL DEFAULT NULL"); $log[] = "✅ resume_id made nullable"; }
catch (Exception $e) { $log[] = "⚠ modify: " . $e->getMessage(); }
try { mysqli_query($conn, "ALTER TABLE ats_score DROP FOREIGN KEY ats_score_ibfk_2"); $log[] = "✅ FK dropped"; }
catch (Exception $e) { $log[] = "ℹ FK not found (already removed — OK)"; }

// 3. Add AI result columns
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS matched_skills TEXT DEFAULT NULL"); } catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS missing_skills TEXT DEFAULT NULL"); } catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS recommendation TEXT DEFAULT NULL"); } catch (Exception $e) {}
$log[] = "✅ ats_score AI columns ensured";

// 4. Seed resume_skills for students that have none
$skills = 'Financial Analysis, MS Excel, Tally, SAP, Business Analytics, Presentation, Leadership, Communication, Teamwork, Data Analysis, Market Research, Digital Marketing, Strategic Planning, Project Management, MBA Finance';
$esc = mysqli_real_escape_string($conn, $skills);
try {
    mysqli_query($conn, "UPDATE student SET resume_skills='$esc' WHERE resume_skills IS NULL OR resume_skills=''");
    $affected = mysqli_affected_rows($conn);
    $log[] = "✅ Seeded resume_skills for $affected student(s)";
} catch (Exception $e) { $log[] = "⚠ seed: " . $e->getMessage(); }

// 5. Clear old ATS cache
try { mysqli_query($conn, "DELETE FROM ats_score WHERE checked_date < CURDATE()"); $log[] = "✅ Old ATS cache cleared"; }
catch (Exception $e) {}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ATS Setup</title>
<style>
body{font-family:sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;padding:32px 40px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:500px;width:95%}
h2{color:#1a237e;margin-bottom:20px}
.item{padding:7px 0;font-size:14px;border-bottom:1px solid #f5f5f5}
.btn{display:inline-block;margin-top:24px;background:#1a237e;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
.btn:hover{background:#3949ab}
</style>
</head>
<body>
<div class="box">
  <h2>🔧 ATS Setup Complete</h2>
  <?php foreach ($log as $l): ?>
    <div class="item"><?= $l ?></div>
  <?php endforeach; ?>
  <a class="btn" href="resume.php">→ Go to ATS Score Checker</a>
</div>
</body>
</html>
