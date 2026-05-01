<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// Summary stats
$total_students  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM student"))['c'];
$placed          = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'];
$total_drives    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM placement_drive"))['c'];
$total_apps      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM application"))['c'];
$ready_students  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM student WHERE preplacement_status='Ready'"))['c'];
$placement_pct   = $total_students > 0 ? round(($placed/$total_students)*100,1) : 0;

// Specialization-wise placement
$spec_data = mysqli_query($conn,
    "SELECT s.specialization,
            COUNT(DISTINCT s.student_id) AS total,
            SUM(a.application_status='Selected') AS placed
     FROM student s
     LEFT JOIN application a ON s.student_id=a.student_id
     GROUP BY s.specialization ORDER BY total DESC");

// Batch-wise placement
$batch_data = mysqli_query($conn,
    "SELECT s.batch,
            COUNT(DISTINCT s.student_id) AS total,
            SUM(a.application_status='Selected') AS placed
     FROM student s
     LEFT JOIN application a ON s.student_id=a.student_id
     GROUP BY s.batch ORDER BY s.batch ASC");

// Top companies
$companies = mysqli_query($conn,
    "SELECT d.company_name, COUNT(DISTINCT a.student_id) AS placements
     FROM placement_drive d
     JOIN application a ON d.drive_id=a.drive_id
     WHERE a.application_status='Selected'
     GROUP BY d.company_name ORDER BY placements DESC LIMIT 5");

// Recent drives
$recent_drives = mysqli_query($conn,
    "SELECT d.job_title, d.company_name, d.drive_date,
            COUNT(DISTINCT a.application_id) AS apps,
            SUM(a.application_status='Selected') AS placed
     FROM placement_drive d
     LEFT JOIN application a ON d.drive_id=a.drive_id
     GROUP BY d.drive_id ORDER BY d.created_at DESC LIMIT 8");

$officer_id = (int)($_SESSION['officer_id'] ?? 0);
mysqli_query($conn,"INSERT INTO analytics_report (generated_by,report_type,generated_date) VALUES ($officer_id,'Dashboard',CURDATE())");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Analytics — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:1300px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:18px;flex-wrap:wrap;margin-bottom:28px}
.kpi{background:#fff;border-radius:12px;padding:22px 24px;flex:1;min-width:140px;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:center}
.kpi .num{font-size:32px;font-weight:700;color:#1b5e20;margin-bottom:4px}
.kpi .lbl{font-size:12px;color:#888}
.two-col{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:24px}
.box{background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.08);flex:1;min-width:280px}
.box h3{font-size:15px;color:#1b5e20;margin-bottom:16px}
.dept-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px}
.dept-row .name{width:80px;color:#444;font-weight:600}
.bar-wrap{flex:1;background:#f0f0f0;border-radius:4px;height:10px}
.bar-fill{height:10px;border-radius:4px;background:#1b5e20}
.dept-row .pct{width:40px;color:#666;text-align:right;font-size:12px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:12px;color:#888;padding:6px 0;border-bottom:1px solid #eee;font-weight:600}
td{padding:9px 0;font-size:13px;color:#333;border-bottom:1px solid #f5f5f5;vertical-align:middle}
tr:last-child td{border-bottom:none}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.badge-s{background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
@media(max-width:768px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .kpi-row{grid-template-columns:1fr 1fr;}
  .two-col{flex-direction:column;}
  .box{min-width:unset;}
}
</style></head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>📊 Analytics & Placement Reports</h2>
<p class="sub">Real-time overview of placement activity.</p>

<div class="kpi-row">
  <div class="kpi"><div class="num"><?= $total_students ?></div><div class="lbl">Total Students</div></div>
  <div class="kpi"><div class="num"><?= $placed ?></div><div class="lbl">Students Placed</div></div>
  <div class="kpi"><div class="num"><?= $placement_pct ?>%</div><div class="lbl">Placement Rate</div></div>
  <div class="kpi"><div class="num"><?= $total_drives ?></div><div class="lbl">Drives Posted</div></div>
  <div class="kpi"><div class="num"><?= $total_apps ?></div><div class="lbl">Applications</div></div>
  <div class="kpi"><div class="num"><?= $ready_students ?></div><div class="lbl">Placement Ready</div></div>
</div>

<div class="two-col">
  <div class="box">
    <h3>Specialization-wise Placement</h3>
    <?php while ($d = mysqli_fetch_assoc($spec_data)):
      $pct = $d['total'] > 0 ? round(($d['placed']/$d['total'])*100) : 0; ?>
    <div class="dept-row">
      <span class="name" style="width:100px"><?= htmlspecialchars($d['specialization']) ?></span>
      <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      <span class="pct"><?= $d['placed'] ?>/<?= $d['total'] ?></span>
    </div>
    <?php endwhile; ?>
  </div>
  <div class="box">
    <h3>Batch-wise Placement</h3>
    <?php while ($d = mysqli_fetch_assoc($batch_data)):
      $pct = $d['total'] > 0 ? round(($d['placed']/$d['total'])*100) : 0; ?>
    <div class="dept-row">
      <span class="name" style="width:90px"><?= htmlspecialchars($d['batch'] ?? '—') ?></span>
      <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:#3949ab"></div></div>
      <span class="pct"><?= $d['placed'] ?>/<?= $d['total'] ?></span>
    </div>
    <?php endwhile; ?>
  </div>
  <div class="box">
    <h3>Top Recruiting Companies</h3>
    <table>
      <tr><th>#</th><th>Company</th><th>Placed</th></tr>
      <?php $i=1; while ($c = mysqli_fetch_assoc($companies)): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($c['company_name']) ?></td>
        <td><span class="badge-s"><?= $c['placements'] ?> students</span></td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>

<div class="box" style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <h3 style="color:#1b5e20;margin-bottom:16px;font-size:15px">Recent Drives Summary</h3>
  <table>
    <tr><th>Job Title</th><th>Company</th><th>Drive Date</th><th>Applications</th><th>Placed</th></tr>
    <?php while ($d = mysqli_fetch_assoc($recent_drives)): ?>
    <tr>
      <td><strong><?= htmlspecialchars($d['job_title']) ?></strong></td>
      <td><?= htmlspecialchars($d['company_name']) ?></td>
      <td><?= date('d M Y',strtotime($d['drive_date'])) ?></td>
      <td><?= $d['apps'] ?></td>
      <td><?= $d['placed'] > 0 ? '<span class="badge-s">'.$d['placed'].'</span>' : '0' ?></td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</div></body></html>
