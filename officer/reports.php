<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) {
  header("Location: ../index.php");
  exit();
}

// ── KPI Stats ────────────────────────────────────────────
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student"))['c'];
$placed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'];
$total_drives = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive"))['c'];
$total_apps = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application"))['c'];
$ready_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student WHERE preplacement_status='Ready'"))['c'];
$active_drives = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_status='Active'"))['c'];
$placement_pct = $total_students > 0 ? round(($placed / $total_students) * 100, 1) : 0;
$unplaced = $total_students - $placed;

// ── Application Status Breakdown ─────────────────────────
$status_res = mysqli_query(
  $conn,
  "SELECT application_status, COUNT(*) as cnt
     FROM application GROUP BY application_status"
);
$status_labels = [];
$status_data = [];
$status_colors = [];
$color_map = [
  'Applied' => '#3b82f6',
  'Shortlisted' => '#f59e0b',
  'Selected' => '#10b981',
  'Rejected' => '#ef4444',
];
while ($r = mysqli_fetch_assoc($status_res)) {
  $status_labels[] = $r['application_status'];
  $status_data[] = (int) $r['cnt'];
  $status_colors[] = $color_map[$r['application_status']] ?? '#8b5cf6';
}

// ── Pre-placement Evaluation Stats ─────────────────────────────
$dept_res = mysqli_query(
  $conn,
  "SELECT s.department,
            COUNT(DISTINCT s.student_id) AS total,
            SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed
     FROM student s
     LEFT JOIN application a ON s.student_id=a.student_id
     GROUP BY s.department ORDER BY total DESC"
);
$dept_rows = [];
while ($r = mysqli_fetch_assoc($dept_res))
  $dept_rows[] = $r;

// ── Batch-wise Placement ──────────────────────────────────
$batch_rows = [];

$batch_res = @mysqli_query(
  $conn,
  "SELECT s.batch,
            COUNT(DISTINCT s.student_id) AS total,
            SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed
     FROM student s
     LEFT JOIN application a ON s.student_id = a.student_id
     WHERE s.batch IS NOT NULL AND s.batch <> ''
     GROUP BY s.batch
     ORDER BY 
       CAST(SUBSTRING_INDEX(s.batch, '-', 1) AS UNSIGNED) ASC"
);

while ($batch_res && $r = mysqli_fetch_assoc($batch_res)) {
  $batch_rows[] = $r;
}

// ── Top Companies ─────────────────────────────────────────
$companies_res = mysqli_query(
  $conn,
  "SELECT d.company_name, COUNT(DISTINCT a.student_id) AS placements
     FROM placement_drive d
     JOIN application a ON d.drive_id=a.drive_id
     WHERE a.application_status='Selected'
     GROUP BY d.company_name ORDER BY placements DESC LIMIT 7"
);
$company_names = [];
$company_counts = [];
while ($c = mysqli_fetch_assoc($companies_res)) {
  $company_names[] = $c['company_name'];
  $company_counts[] = (int) $c['placements'];
}



// ── Alumni Insights ─────────────────────────────────────
$alumni_total = (int)mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT COUNT(*) c FROM alumni"))['c'];


$alumni_rec_total = (int)mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT COUNT(*) c FROM alumni_recommendation"))['c'];

$alumni_rec_res = mysqli_query($conn,
  "SELECT r.job_title, r.company_name, a.name AS alumni_name,
          DATE_FORMAT(r.posted_at,'%d %b') AS posted
   FROM alumni_recommendation r
   JOIN alumni a ON r.alumni_id=a.alumni_id
   ORDER BY r.posted_at DESC LIMIT 5");
$alumni_recs = [];
while ($ar = mysqli_fetch_assoc($alumni_rec_res)) $alumni_recs[] = $ar;

$eval_res = mysqli_query(
  $conn,
  "SELECT
        AVG(aptitude_marks) AS avg_apt,
        AVG(gd_marks)       AS avg_gd,
        AVG(english_marks)  AS avg_eng,
        COUNT(*) AS total_eval
     FROM preplacement_evaluation"
);
$eval = mysqli_fetch_assoc($eval_res);

// ── CGPA Distribution ─────────────────────────────────────
$cgpa_res = mysqli_query(
  $conn,
  "SELECT
        SUM(CASE WHEN cgpa >= 9    THEN 1 ELSE 0 END) AS 'A+',
        SUM(CASE WHEN cgpa >= 8 AND cgpa < 9 THEN 1 ELSE 0 END) AS 'A',
        SUM(CASE WHEN cgpa >= 7 AND cgpa < 8 THEN 1 ELSE 0 END) AS 'B',
        SUM(CASE WHEN cgpa >= 6 AND cgpa < 7 THEN 1 ELSE 0 END) AS 'C',
        SUM(CASE WHEN cgpa < 6    THEN 1 ELSE 0 END) AS 'D'
     FROM student"
);
$cgpa = mysqli_fetch_assoc($cgpa_res);

// ── Recent Drives ─────────────────────────────────────────
$recent_drives = mysqli_query(
  $conn,
  "SELECT d.job_title, d.company_name, d.drive_date, d.drive_status,
            COUNT(DISTINCT a.application_id) AS apps,
            SUM(a.application_status='Selected') AS placed
     FROM placement_drive d
     LEFT JOIN application a ON d.drive_id=a.drive_id
     GROUP BY d.drive_id ORDER BY d.created_at DESC LIMIT 8"
);

// ── Log Report View ───────────────────────────────────────
$officer_id = (int) ($_SESSION['officer_id'] ?? 0);
mysqli_query($conn, "INSERT INTO analytics_report (generated_by,report_type,generated_date)
    VALUES ($officer_id,'Dashboard View',CURDATE())");

// ── Encode data for JS ────────────────────────────────────
$js_status_labels = json_encode($status_labels);
$js_status_data = json_encode($status_data);
$js_status_colors = json_encode($status_colors);
$js_dept_labels = json_encode(array_column($dept_rows, 'department'));
$js_dept_total = json_encode(array_map('intval', array_column($dept_rows, 'total')));
$js_dept_placed = json_encode(array_map('intval', array_column($dept_rows, 'placed')));
$js_batch_labels = json_encode(array_column($batch_rows, 'batch'));
$js_batch_total = json_encode(array_map('intval', array_column($batch_rows, 'total')));
$js_batch_placed = json_encode(array_map('intval', array_column($batch_rows, 'placed')));
$js_company_names = json_encode($company_names);
$js_company_counts = json_encode($company_counts);
// trend data removed — replaced by insights panel
$js_cgpa = json_encode(array_values($cgpa));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics — PMS</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

  <style>
    /* ── Reset & Base ─────────────────────────────────────── */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    :root {
      --green: #1b5e20;
      --green-mid: #2e7d32;
      --green-light: #e8f5e9;
      --blue: #1e40af;
      --blue-light: #dbeafe;
      --amber: #b45309;
      --amber-light: #fef3c7;
      --red: #be123c;
      --red-light: #ffe4e6;
      --purple: #6d28d9;
      --purple-light: #ede9fe;
      --teal: #0f766e;
      --teal-light: #ccfbf1;
      --bg: #f1f5f9;
      --card: #fff;
      --text: #1e293b;
      --muted: #64748b;
      --border: #e2e8f0;
      --radius: 14px;
      --shadow: 0 2px 16px rgba(0, 0, 0, .07);
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh
    }

    /* ── Navbar ───────────────────────────────────────────── */
    .navbar {
      background: linear-gradient(135deg, var(--green) 0%, #2e7d32 100%);
      color: #fff;
      padding: 0 32px;
      height: 60px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 12px rgba(27, 94, 32, .3);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 17px;
      font-weight: 700
    }

    .navbar-brand span {
      font-size: 22px
    }

    .navbar-right {
      display: flex;
      align-items: center;
      gap: 20px
    }

    .navbar-right a {
      color: rgba(255, 255, 255, .85);
      text-decoration: none;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 6px;
      transition: .2s;
      border: 1px solid rgba(255, 255, 255, .2)
    }

    .navbar-right a:hover {
      background: rgba(255, 255, 255, .15);
      color: #fff
    }

    /* ── Page Wrap ────────────────────────────────────────── */
    .wrap {
      max-width: 1400px;
      margin: 0 auto;
      padding: 32px 24px
    }

    /* ── Page Header ──────────────────────────────────────── */
    .page-header {
      margin-bottom: 28px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 12px
    }

    .page-header h2 {
      font-size: 24px;
      font-weight: 700;
      color: var(--green)
    }

    .page-header p {
      color: var(--muted);
      font-size: 13px;
      margin-top: 4px
    }

    .btn-export {
      background: var(--green);
      color: #fff;
      border: none;
      padding: 9px 20px;
      border-radius: 8px;
      font-size: 13px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      transition: .2s;
      font-weight: 600;
    }

    .btn-export:hover {
      background: var(--green-mid)
    }

    .back-link {
      color: var(--green);
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-bottom: 20px
    }

    .back-link:hover {
      text-decoration: underline
    }

    /* ── KPI Cards ────────────────────────────────────────── */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 28px
    }

    .kpi {
      background: var(--card);
      border-radius: var(--radius);
      padding: 20px 22px;
      box-shadow: var(--shadow);
      border-left: 4px solid transparent;
      position: relative;
      overflow: hidden;
      transition: .2s;
    }

    .kpi:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 0, 0, .1)
    }

    .kpi::after {
      content: '';
      position: absolute;
      top: -20px;
      right: -20px;
      width: 80px;
      height: 80px;
      border-radius: 50%;
      opacity: .06;
    }

    .kpi.green {
      border-color: var(--green)
    }

    .kpi.green::after {
      background: var(--green)
    }

    .kpi.blue {
      border-color: #3b82f6
    }

    .kpi.blue::after {
      background: #3b82f6
    }

    .kpi.amber {
      border-color: #f59e0b
    }

    .kpi.amber::after {
      background: #f59e0b
    }

    .kpi.red {
      border-color: #ef4444
    }

    .kpi.red::after {
      background: #ef4444
    }

    .kpi.purple {
      border-color: #8b5cf6
    }

    .kpi.purple::after {
      background: #8b5cf6
    }

    .kpi.teal {
      border-color: #14b8a6
    }

    .kpi.teal::after {
      background: #14b8a6
    }

    .kpi-icon {
      font-size: 22px;
      margin-bottom: 10px
    }

    .kpi-num {
      font-size: 30px;
      font-weight: 800;
      line-height: 1;
      margin-bottom: 4px
    }

    .kpi.green .kpi-num {
      color: var(--green)
    }

    .kpi.blue .kpi-num {
      color: #1d4ed8
    }

    .kpi.amber .kpi-num {
      color: #d97706
    }

    .kpi.red .kpi-num {
      color: #dc2626
    }

    .kpi.purple .kpi-num {
      color: #7c3aed
    }

    .kpi.teal .kpi-num {
      color: #0f766e
    }

    .kpi-lbl {
      font-size: 12px;
      color: var(--muted);
      font-weight: 500
    }

    .kpi-sub {
      font-size: 11px;
      color: var(--muted);
      margin-top: 6px;
      padding-top: 6px;
      border-top: 1px solid var(--border)
    }

    /* ── Section Title ────────────────────────────────────── */
    .section-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--muted);
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border)
    }

    /* ── Card ─────────────────────────────────────────────── */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 22px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
    }

    .card-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 6px
    }

    /* ── Grid Layouts ─────────────────────────────────────── */
    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 24px;
      align-items: stretch;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 24px;
      align-items: stretch;
    }

    .grid-2-1 {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
      align-items: stretch;
    }

    /* ── Chart Container ──────────────────────────────────── */
    .chart-container {
      position: relative;
      width: 100%;
    }

    .col-span-full {
      grid-column: 1/-1
    }

    /* ── Donut Center Label ───────────────────────────────── */
    .chart-wrap {
      position: relative
    }

    .donut-center {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
      pointer-events: none;
    }

    .donut-center .big {
      font-size: 26px;
      font-weight: 800;
      color: var(--text)
    }

    .donut-center .sm {
      font-size: 11px;
      color: var(--muted)
    }

    /* ── Progress Bars ────────────────────────────────────── */
    .prog-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
      font-size: 13px
    }

    .prog-label {
      width: 110px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .prog-track {
      flex: 1;
      background: #f1f5f9;
      border-radius: 6px;
      height: 10px;
      overflow: hidden
    }

    .prog-fill {
      height: 10px;
      border-radius: 6px;
      transition: width .8s cubic-bezier(.4, 0, .2, 1)
    }

    .prog-meta {
      width: 54px;
      text-align: right;
      font-size: 11px;
      color: var(--muted);
      font-weight: 600;
      white-space: nowrap
    }

    /* ── Gauge ────────────────────────────────────────────── */
    .gauge-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px
    }

    .gauge-ring {
      position: relative;
      width: 120px;
      height: 120px
    }

    .gauge-ring svg {
      transform: rotate(-90deg)
    }

    .gauge-ring .track {
      fill: none;
      stroke: #f1f5f9;
      stroke-width: 12
    }

    .gauge-ring .fill {
      fill: none;
      stroke-width: 12;
      stroke-linecap: round;
      transition: stroke-dashoffset 1s ease
    }

    .gauge-label {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center
    }

    .gauge-label .val {
      font-size: 20px;
      font-weight: 800
    }

    .gauge-label .unit {
      font-size: 11px;
      color: var(--muted)
    }

    /* ── Eval Bars ────────────────────────────────────────── */
    .eval-item {
      margin-bottom: 14px
    }

    .eval-header {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      margin-bottom: 5px;
      font-weight: 600
    }

    .eval-track {
      background: #f1f5f9;
      border-radius: 6px;
      height: 12px;
      overflow: hidden
    }

    .eval-fill {
      height: 12px;
      border-radius: 6px;
      transition: width 1s ease
    }

    /* ── Company Badges ───────────────────────────────────── */
    .company-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
      font-size: 13px;
    }

    .company-item:last-child {
      border: none
    }

    .company-rank {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: var(--green-light);
      color: var(--green);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .company-rank.gold {
      background: #fef9c3;
      color: #92400e
    }

    .company-rank.silver {
      background: #f1f5f9;
      color: #475569
    }

    .company-rank.bronze {
      background: #ffedd5;
      color: #c2410c
    }

    .company-name-col {
      flex: 1;
      font-weight: 600;
      color: var(--text)
    }

    .company-bar-wrap {
      width: 80px;
      background: #f1f5f9;
      border-radius: 4px;
      height: 6px
    }

    .company-bar-fill {
      height: 6px;
      border-radius: 4px;
      background: var(--green)
    }

    .company-count {
      min-width: 52px;
      text-align: right;
      font-size: 11px;
      font-weight: 700;
      background: var(--green-light);
      color: var(--green);
      padding: 2px 8px;
      border-radius: 10px;
    }

    /* ── CGPA Pills ───────────────────────────────────────── */
    .cgpa-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 8px;
      text-align: center
    }

    .cgpa-pill {
      background: var(--green-light);
      border-radius: 10px;
      padding: 12px 6px
    }

    .cgpa-pill .grade {
      font-size: 14px;
      font-weight: 800;
      color: var(--green)
    }

    .cgpa-pill .count {
      font-size: 20px;
      font-weight: 800;
      color: var(--text);
      margin: 2px 0
    }

    .cgpa-pill .range {
      font-size: 10px;
      color: var(--muted)
    }

    /* ── Table ────────────────────────────────────────────── */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px
    }

    .data-table th {
      text-align: left;
      font-size: 11px;
      color: var(--muted);
      padding: 8px 12px;
      border-bottom: 2px solid var(--border);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .data-table td {
      padding: 11px 12px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
      vertical-align: middle
    }

    .data-table tr:last-child td {
      border: none
    }

    .data-table tr:hover td {
      background: #f8fafc
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 9px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700
    }

    .badge-green {
      background: var(--green-light);
      color: var(--green)
    }

    .badge-blue {
      background: var(--blue-light);
      color: var(--blue)
    }

    .badge-amber {
      background: var(--amber-light);
      color: var(--amber)
    }

    .badge-red {
      background: var(--red-light);
      color: var(--red)
    }

    .badge-gray {
      background: #f1f5f9;
      color: #475569
    }

    .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      display: inline-block
    }

    /* ── Stat Row ─────────────────────────────────────────── */
    .stat-row {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      flex-wrap: wrap
    }

    .stat-chip {
      background: var(--green-light);
      border-radius: 10px;
      padding: 12px 18px;
      flex: 1;
      min-width: 100px;
      text-align: center
    }

    .stat-chip .n {
      font-size: 22px;
      font-weight: 800;
      color: var(--green)
    }

    .stat-chip .l {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px
    }

    /* ── Responsive ───────────────────────────────────────── */
    @media(max-width:900px) {

      .grid-3,
      .grid-2,
      .grid-2-1 {
        grid-template-columns: 1fr
      }

      .kpi-grid {
        grid-template-columns: 1fr 1fr
      }

      .navbar {
        padding: 0 16px
      }

      .wrap {
        padding: 20px 14px
      }

      .cgpa-grid {
        grid-template-columns: repeat(3, 1fr)
      }
    }

    @media(max-width:540px) {
      .kpi-grid {
        grid-template-columns: 1fr
      }

      .cgpa-grid {
        grid-template-columns: 1fr 1fr
      }
    }
  </style>
</head>

<body>

  <!-- ── Navbar ──────────────────────────────────────────── -->
  <nav class="navbar">
    <div class="navbar-brand">
      <span>🎓</span> PMS — Placement Officer Portal
    </div>
    <div class="navbar-right">
      <a href="dashboard.php">← Dashboard</a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>

  <div class="wrap">

    <!-- ── Page Header ─────────────────────────────────── -->
    <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
    <div class="page-header">
      <div>
        <h2>📊 Analytics & Placement Reports</h2>
        <p>Real-time overview of placement activity · Generated <?= date('d M Y, h:i A') ?></p>
      </div>
      <div style="position:relative;display:inline-block" id="exportDropdownWrap">
        <button class="btn-export" onclick="toggleExportMenu()" type="button">⬇ Export ▾</button>
        <div id="exportMenu"
          style="display:none;position:absolute;right:0;top:calc(100% + 6px);background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:190px;z-index:200;overflow:hidden">
          <button onclick="downloadHTML()"
            style="display:flex;align-items:center;gap:8px;width:100%;padding:11px 16px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:var(--text)"
            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='none'">🌐 Interactive
            HTML</button>
          <div style="height:1px;background:var(--border);margin:0 12px"></div>
          <button onclick="exportCSV()"
            style="display:flex;align-items:center;gap:8px;width:100%;padding:11px 16px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:var(--text)"
            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='none'">📄 Export
            CSV</button>
        </div>
      </div>
    </div>

    <!-- ── KPI Row ─────────────────────────────────────── -->
    <div class="kpi-grid">
      <div class="kpi green">
        <div class="kpi-icon">👨‍🎓</div>
        <div class="kpi-num"><?= $total_students ?></div>
        <div class="kpi-lbl">Total Students</div>
        <div class="kpi-sub">Enrolled in system</div>
      </div>
      <div class="kpi teal">
        <div class="kpi-icon">✅</div>
        <div class="kpi-num"><?= $placed ?></div>
        <div class="kpi-lbl">Students Placed</div>
        <div class="kpi-sub"><?= $unplaced ?> still seeking</div>
      </div>
      <div class="kpi blue">
        <div class="kpi-icon">📈</div>
        <div class="kpi-num"><?= $placement_pct ?>%</div>
        <div class="kpi-lbl">Placement Rate</div>
        <div class="kpi-sub">Of total students</div>
      </div>
      <div class="kpi purple">
        <div class="kpi-icon">🏢</div>
        <div class="kpi-num"><?= $total_drives ?></div>
        <div class="kpi-lbl">Drives Posted</div>
        <div class="kpi-sub"><?= $active_drives ?> currently active</div>
      </div>
      <div class="kpi amber">
        <div class="kpi-icon">📋</div>
        <div class="kpi-num"><?= $total_apps ?></div>
        <div class="kpi-lbl">Total Applications</div>
        <div class="kpi-sub">Across all drives</div>
      </div>
      <div class="kpi green">
        <div class="kpi-icon">🎯</div>
        <div class="kpi-num"><?= $ready_students ?></div>
        <div class="kpi-lbl">Placement Ready</div>
        <div class="kpi-sub">Cleared all rounds</div>
      </div>
    </div>

    <!-- Application Status -->
    <div class="section-title">📄 Application Status</div>
    <div style="margin-bottom:24px">
      <div class="card">
        <div class="card-title">🗂 Application Status Breakdown</div>
        <div style="display:flex;gap:32px;align-items:center;flex-wrap:wrap">

          <!-- Donut -->
          <div style="flex:0 0 200px;height:200px;position:relative;display:flex;justify-content:center">
            <canvas id="statusChart"></canvas>
            <div class="donut-center">
              <div class="big"><?= $total_apps ?></div>
              <div class="sm">Total</div>
            </div>
          </div>

          <!-- Breakdown bars -->
          <div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:12px">
            <?php foreach ($status_labels as $i => $lbl):
              $pct = $total_apps > 0 ? round(($status_data[$i] / $total_apps) * 100) : 0;
            ?>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                <div style="display:flex;align-items:center;gap:7px">
                  <span style="width:10px;height:10px;border-radius:50%;background:<?= $status_colors[$i] ?>;flex-shrink:0;display:inline-block"></span>
                  <span style="font-size:12px;font-weight:700;color:var(--text)"><?= htmlspecialchars($lbl) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                  <span style="font-size:13px;font-weight:800;color:var(--text)"><?= $status_data[$i] ?></span>
                  <span style="font-size:11px;color:var(--muted);font-weight:600;min-width:32px;text-align:right"><?= $pct ?>%</span>
                </div>
              </div>
              <div style="background:#f1f5f9;border-radius:6px;height:7px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:7px;border-radius:6px;background:<?= $status_colors[$i] ?>;transition:width .7s ease"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Alumni Insights ──────────────────────────────── -->
    <div class="section-title">🎓 Alumni Insights</div>
    <div class="grid-2" style="margin-bottom:24px">

      <!-- Stat: Total Alumni -->
      <div class="card" style="align-items:center;text-align:center">
        <div style="font-size:38px;font-weight:800;color:var(--green)"><?= $alumni_total ?></div>
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-top:4px">Alumni Registered</div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">in the network</div>
      </div>

      <!-- Stat: Job Recommendations -->
      <div class="card" style="align-items:center;text-align:center">
        <div style="font-size:38px;font-weight:800;color:#7c3aed"><?= $alumni_rec_total ?></div>
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-top:4px">Job Tips Shared</div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">by alumni network</div>
      </div>

    </div>

    <?php if (!empty($alumni_recs)): ?>
    <!-- Recent Alumni Job Recommendations -->
    <div class="grid-1" style="margin-bottom:24px">
      <div class="card">
        <div class="card-title">💼 Recent Alumni Job Recommendations</div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Company</th>
              <th>Shared by</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($alumni_recs as $ar): ?>
            <tr>
              <td><strong><?= htmlspecialchars($ar['job_title']) ?></strong></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($ar['company_name']) ?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:6px">
                  <span style="width:22px;height:22px;border-radius:50%;background:var(--green);color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?= strtoupper(substr($ar['alumni_name'],0,1)) ?>
                  </span>
                  <?= htmlspecialchars($ar['alumni_name']) ?>
                </span>
              </td>
              <td style="color:var(--muted);font-size:12px"><?= $ar['posted'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="section-title">📅 Batch & Placement Overview</div>
    <div class="grid-2" style="margin-bottom:24px">

      <!-- Batch Bar -->
      <div class="card">
        <div class="card-title">📅 Batch-wise Placement</div>
        <?php if (empty($batch_rows)): ?>
          <div
            style="text-align:center;color:var(--muted);padding:40px 0;font-size:13px;flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column">
            No batch data available.<br>
            <small>Set batch value in student profiles.</small>
          </div>
        <?php else: ?>
          <div class="chart-container" style="height:220px">
            <canvas id="batchChart"></canvas>
          </div>
        <?php endif; ?>
      </div>

      <!-- Placed vs Unplaced Horizontal Bar -->
      <div class="card">
        <div class="card-title">⚖️ Placed vs Unplaced</div>
        <div class="chart-container" style="height:220px">
          <canvas id="placedChart"></canvas>
        </div>
      </div>
    </div>

    <!-- ── Row 3: Companies + CGPA + Eval ──────────────── -->
    <div class="section-title">🏆 Companies, CGPA & Evaluations</div>
    <div class="grid-3" style="margin-bottom:24px">

      <!-- Top Companies -->
      <div class="card">
        <div class="card-title">🏢 Top Recruiting Companies</div>
        <?php
        $max_c = max(array_merge($company_counts, [1]));
        $rank_class = ['gold', 'silver', 'bronze'];
        foreach ($company_names as $i => $cn):
          $pct = round(($company_counts[$i] / $max_c) * 100);
          $rc = $rank_class[$i] ?? '';
          ?>
          <div class="company-item">
            <div class="company-rank <?= $rc ?>"><?= $i + 1 ?></div>
            <div class="company-name-col"><?= htmlspecialchars($cn) ?></div>
            <div class="company-bar-wrap">
              <div class="company-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="company-count"><?= $company_counts[$i] ?> placed</div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($company_names)): ?>
          <div
            style="text-align:center;color:var(--muted);padding:30px 0;font-size:13px;flex:1;display:flex;align-items:center;justify-content:center">
            No placements recorded yet</div>
        <?php endif; ?>
      </div>

      <!-- CGPA Distribution -->
      <div class="card">
        <div class="card-title">🎓 CGPA Distribution</div>
        <div class="chart-container" style="height:180px">
          <canvas id="cgpaChart"></canvas>
        </div>
        <div class="cgpa-grid" style="margin-top:14px">
          <div class="cgpa-pill">
            <div class="grade">A+</div>
            <div class="count"><?= $cgpa['A+'] ?></div>
            <div class="range">≥ 9.0</div>
          </div>
          <div class="cgpa-pill">
            <div class="grade">A</div>
            <div class="count"><?= $cgpa['A'] ?></div>
            <div class="range">8–9</div>
          </div>
          <div class="cgpa-pill" style="background:var(--blue-light)">
            <div class="grade" style="color:var(--blue)">B</div>
            <div class="count"><?= $cgpa['B'] ?></div>
            <div class="range">7–8</div>
          </div>
          <div class="cgpa-pill" style="background:var(--amber-light)">
            <div class="grade" style="color:var(--amber)">C</div>
            <div class="count"><?= $cgpa['C'] ?></div>
            <div class="range">6–7</div>
          </div>
          <div class="cgpa-pill" style="background:var(--red-light)">
            <div class="grade" style="color:var(--red)">D</div>
            <div class="count"><?= $cgpa['D'] ?></div>
            <div class="range">&lt; 6</div>
          </div>
        </div>
      </div>

      <!-- Pre-placement Evaluation -->
      <div class="card">
        <div class="card-title">📝 Pre-placement Evaluation Avg</div>
        <div style="margin-bottom:18px">
          <?php
          $evals = [
            ['Aptitude', round($eval['avg_apt'] ?? 0), '#1b5e20'],
            ['Group Discussion', round($eval['avg_gd'] ?? 0), '#1e40af'],
            ['English', round($eval['avg_eng'] ?? 0), '#d97706'],
          ];
          foreach ($evals as [$name, $score, $color]):
            ?>
            <div class="eval-item">
              <div class="eval-header">
                <span><?= $name ?></span>
                <span style="color:<?= $color ?>"><?= $score ?>/100</span>
              </div>
              <div class="eval-track">
                <div class="eval-fill" style="width:<?= $score ?>%;background:<?= $color ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="stat-row">
          <div class="stat-chip">
            <div class="n"><?= $eval['total_eval'] ?? 0 ?></div>
            <div class="l">Evaluated</div>
          </div>
          <div class="stat-chip">
            <div class="n"><?= $ready_students ?></div>
            <div class="l">Ready</div>
          </div>
          <div class="stat-chip">
            <div class="n"><?= $total_students - $ready_students ?></div>
            <div class="l">Not Ready</div>
          </div>
        </div>

        <!-- Placed vs Unplaced Gauge -->
        <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px">
          <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:12px">PLACEMENT RATE</div>
          <div class="gauge-wrap">
            <div class="gauge-ring">
              <svg viewBox="0 0 120 120" width="120" height="120">
                <circle class="track" cx="60" cy="60" r="48" />
                <circle class="fill" cx="60" cy="60" r="48" id="gaugeFill" stroke="#1b5e20"
                  stroke-dasharray="<?= 2 * 3.14159 * 48 ?>"
                  stroke-dashoffset="<?= 2 * 3.14159 * 48 * (1 - $placement_pct / 100) ?>" />
              </svg>
              <div class="gauge-label">
                <div class="val" style="color:var(--green)"><?= $placement_pct ?>%</div>
                <div class="unit">placed</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Row 5: Recent Drives Table ──────────────────── -->
    <div class="section-title">🗓 Recent Drive Activity</div>
    <div class="card" style="margin-bottom:24px">
      <div class="card-title">📋 Recent Drives Summary</div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Company</th>
              <th>Drive Date</th>
              <th>Status</th>
              <th>Applications</th>
              <th>Selected</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($d = mysqli_fetch_assoc($recent_drives)):
              $rate = $d['apps'] > 0 ? round(($d['placed'] / $d['apps']) * 100) : 0;
              $status_cls = $d['drive_status'] === 'Active' ? 'badge-green' : 'badge-gray';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($d['job_title']) ?></strong></td>
                <td><?= htmlspecialchars($d['company_name']) ?></td>
                <td><?= date('d M Y', strtotime($d['drive_date'])) ?></td>
                <td><span class="badge <?= $status_cls ?>"><span class="dot"
                      style="background:<?= $d['drive_status'] === 'Active' ? '#16a34a' : '#94a3b8' ?>"></span><?= $d['drive_status'] ?></span>
                </td>
                <td><?= $d['apps'] ?></td>
                <td>
                  <?php if ($d['placed'] > 0): ?>
                    <span class="badge badge-green"><?= $d['placed'] ?></span>
                  <?php else:
                    echo '0';
                  endif; ?>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px">
                    <div style="flex:1;background:#f1f5f9;border-radius:4px;height:6px;min-width:50px">
                      <div
                        style="width:<?= $rate ?>%;height:6px;border-radius:4px;background:<?= $rate > 50 ? '#10b981' : ($rate > 20 ? '#f59e0b' : '#ef4444') ?>">
                      </div>
                    </div>
                    <span style="font-size:11px;color:var(--muted);font-weight:600"><?= $rate ?>%</span>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /wrap -->

  <!-- ── Chart.js Scripts ──────────────────────────────── -->
  <script>
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    const GREEN = '#1b5e20';
    const GREEN2 = '#4caf50';
    const BLUE = '#1e40af';
    const BLUE2 = '#60a5fa';
    const AMBER = '#d97706';
    const RED = '#ef4444';
    const PURPLE = '#7c3aed';

    // ── 2. Application Status Donut ──────────────────────────
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
      new Chart(statusCtx, {
        type: 'doughnut',
        data: {
          labels: <?= $js_status_labels ?>,
          datasets: [{
            data: <?= $js_status_data ?>,
            backgroundColor: <?= $js_status_colors ?>,
            borderWidth: 3, borderColor: '#fff',
            hoverBorderWidth: 4,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            legend: { display: false }, tooltip: {
              callbacks: {
                label: ctx => ` ${ctx.label}: ${ctx.parsed}`
              }
            }
          }
        }
      });
    }




    // ── 4. Batch Bar ─────────────────────────────────────────
    const batchCtx = document.getElementById('batchChart');
    if (batchCtx && <?= !empty($batch_rows) ? 'true' : 'false' ?>) {
      new Chart(batchCtx, {
        type: 'bar',
        data: {
          labels: <?= $js_batch_labels ?>,
          datasets: [
            {
              label: 'Total',
              data: <?= $js_batch_total ?>,
              backgroundColor: 'rgba(109,40,217,.15)',
              borderColor: PURPLE, borderWidth: 2, borderRadius: 6,
            },
            {
              label: 'Placed',
              data: <?= $js_batch_placed ?>,
              backgroundColor: 'rgba(109,40,217,.75)',
              borderColor: PURPLE, borderWidth: 1, borderRadius: 6,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
          scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
          }
        }
      });
    }

    // ── 5. CGPA Distribution Bar ─────────────────────────────
    const cgpaCtx = document.getElementById('cgpaChart');
    if (cgpaCtx) {
      new Chart(cgpaCtx, {
        type: 'bar',
        data: {
          labels: ['A+ (≥9)', 'A (8-9)', 'B (7-8)', 'C (6-7)', 'D (<6)'],
          datasets: [{
            label: 'Students',
            data: <?= $js_cgpa ?>,
            backgroundColor: [
              'rgba(27,94,32,.8)',
              'rgba(27,94,32,.6)',
              'rgba(30,64,175,.7)',
              'rgba(217,119,6,.7)',
              'rgba(239,68,68,.7)',
            ],
            borderRadius: 6, borderWidth: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
          }
        }
      });
    }

    // ── 6. Placed vs Unplaced Horizontal Bar ─────────────────
    const placedCtx = document.getElementById('placedChart');
    if (placedCtx) {
      new Chart(placedCtx, {
        type: 'bar',
        data: {
          labels: ['Students'],
          datasets: [
            {
              label: 'Placed',
              data: [<?= $placed ?>],
              backgroundColor: 'rgba(27,94,32,.8)',
              borderRadius: 6, borderWidth: 0,
            },
            {
              label: 'Unplaced',
              data: [<?= $unplaced ?>],
              backgroundColor: 'rgba(239,68,68,.6)',
              borderRadius: 6, borderWidth: 0,
            }
          ]
        },
        options: {
          indexAxis: 'y', responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
            tooltip: {
              callbacks: {
                label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.x} students`
              }
            }
          },
          scales: {
            x: { stacked: true, grid: { color: '#f1f5f9' }, beginAtZero: true },
            y: { stacked: true, grid: { display: false } }
          }
        }
      });
    }
    // ── Interactive HTML Export ──────────────────────────────
    async function downloadHTML() {
      document.getElementById('exportMenu').style.display = 'none';

      // Show spinner on button
      const btn = document.querySelector('.btn-export');
      const origText = btn.innerHTML;
      btn.innerHTML = '⏳ Preparing…';
      btn.disabled = true;

      try {
        // Fetch Chart.js source to embed inline (avoids CDN dependency)
        let chartJsSrc = '';
        try {
          const r = await fetch('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js');
          chartJsSrc = await r.text();
        } catch (e) {
          // fallback: keep CDN link
          chartJsSrc = null;
        }

        // Capture all rendered chart images as PNG data URLs
        const canvasIds = ['statusChart', 'batchChart', 'cgpaChart', 'placedChart'];
        const chartImages = {};
        canvasIds.forEach(id => {
          const c = document.getElementById(id);
          if (c) {
            try { chartImages[id] = c.toDataURL('image/png'); } catch (e) { }
          }
        });

        // Capture current SVG gauge
        const gaugeSvgEl = document.querySelector('.gauge-ring svg');
        const gaugeSvg = gaugeSvgEl ? new XMLSerializer().serializeToString(gaugeSvgEl) : '';

        // Get all current CSS
        const styles = Array.from(document.styleSheets).map(ss => {
          try { return Array.from(ss.cssRules).map(r => r.cssText).join('\n'); }
          catch (e) { return ''; }
        }).join('\n');

        // Clone the page body
        const clone = document.body.cloneNode(true);

        // Replace each canvas with a static <img> (charts are interactive via re-init below)
        clone.querySelectorAll('canvas').forEach(canvas => {
          const img = document.createElement('img');
          img.src = chartImages[canvas.id] || '';
          img.id = canvas.id;
          img.style.cssText = canvas.style.cssText;
          img.setAttribute('data-chart-canvas', canvas.id);
          img.style.width = '100%';
          img.style.display = 'block';
          canvas.parentNode.replaceChild(img, canvas);
        });

        // Restore gauge SVG inline
        if (gaugeSvg) {
          const gaugeRing = clone.querySelector('.gauge-ring');
          if (gaugeRing) {
            const oldSvg = gaugeRing.querySelector('svg');
            if (oldSvg) {
              oldSvg.outerHTML = gaugeSvg;
            }
          }
        }

        // Remove the export dropdown wrapper (no point in downloaded file)
        const expWrap = clone.querySelector('#exportDropdownWrap');
        if (expWrap) expWrap.remove();

        // Remove back-link
        const backLink = clone.querySelector('.back-link');
        if (backLink) backLink.remove();

        // Stamp generated notice in navbar
        const brand = clone.querySelector('.navbar-brand');
        if (brand) brand.innerHTML += '<span style="font-size:11px;opacity:.7;font-weight:400;margin-left:16px">Downloaded <?= date('d M Y, h:i A') ?></span>';

        // Remove nav links (not useful offline)
        const navRight = clone.querySelector('.navbar-right');
        if (navRight) navRight.innerHTML = '<span style="font-size:12px;opacity:.7">Placement Analytics Report</span>';

        // All the original chart data for re-initialisation
        const chartData = {
          status: {
            labels: <?= $js_status_labels ?>,
            data: <?= $js_status_data ?>,
            colors: <?= $js_status_colors ?>
          },
          batch: {
            labels: <?= $js_batch_labels ?>,
            total: <?= $js_batch_total ?>,
            placed: <?= $js_batch_placed ?>,
            hasData: <?= !empty($batch_rows) ? 'true' : 'false' ?>
          },
          cgpa: { data: <?= $js_cgpa ?> },
          placed: { placed: <?= $placed ?>, unplaced: <?= $unplaced ?> }
        };

        // Build the re-init script that converts <img data-chart-canvas> back to <canvas>
        const reinitScript = `
(function() {
  const GREEN='#1b5e20',GREEN2='#4caf50',BLUE='#1e40af',BLUE2='#60a5fa',AMBER='#d97706',RED='#ef4444',PURPLE='#7c3aed';
  const CD = ${JSON.stringify(chartData)};
  Chart.defaults.font.family="'Segoe UI',system-ui,sans-serif";
  Chart.defaults.color='#64748b';

  document.querySelectorAll('[data-chart-canvas]').forEach(img => {
    const id = img.getAttribute('data-chart-canvas');
    const canvas = document.createElement('canvas');
    canvas.id = id;
    canvas.style.cssText = img.style.cssText;
    img.parentNode.replaceChild(canvas, img);
  });


  const statusCtx = document.getElementById('statusChart');
  if(statusCtx) new Chart(statusCtx,{type:'doughnut',data:{labels:CD.status.labels,datasets:[{data:CD.status.data,backgroundColor:CD.status.colors,borderWidth:3,borderColor:'#fff',hoverBorderWidth:4}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>' '+ctx.label+': '+ctx.parsed}}}}});

  const batchCtx = document.getElementById('batchChart');
  if(batchCtx&&CD.batch.hasData) new Chart(batchCtx,{type:'bar',data:{labels:CD.batch.labels,datasets:[
    {label:'Total',data:CD.batch.total,backgroundColor:'rgba(109,40,217,.15)',borderColor:PURPLE,borderWidth:2,borderRadius:6},
    {label:'Placed',data:CD.batch.placed,backgroundColor:'rgba(109,40,217,.75)',borderColor:PURPLE,borderWidth:1,borderRadius:6}
  ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:11}}}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false}}}}});

  const cgpaCtx = document.getElementById('cgpaChart');
  if(cgpaCtx) new Chart(cgpaCtx,{type:'bar',data:{labels:['A+ (≥9)','A (8-9)','B (7-8)','C (6-7)','D (<6)'],datasets:[{label:'Students',data:CD.cgpa.data,backgroundColor:['rgba(27,94,32,.8)','rgba(27,94,32,.6)','rgba(30,64,175,.7)','rgba(217,119,6,.7)','rgba(239,68,68,.7)'],borderRadius:6,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false}}}}});

  const placedCtx = document.getElementById('placedChart');
  if(placedCtx) new Chart(placedCtx,{type:'bar',data:{labels:['Students'],datasets:[
    {label:'Placed',data:[CD.placed.placed],backgroundColor:'rgba(27,94,32,.8)',borderRadius:6,borderWidth:0},
    {label:'Unplaced',data:[CD.placed.unplaced],backgroundColor:'rgba(239,68,68,.6)',borderRadius:6,borderWidth:0}
  ]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},tooltip:{callbacks:{label:ctx=>' '+ctx.dataset.label+': '+ctx.parsed.x+' students'}}},scales:{x:{stacked:true,grid:{color:'#f1f5f9'},beginAtZero:true},y:{stacked:true,grid:{display:false}}}}});
})();`;

        const chartJsTag = chartJsSrc
          ? `<script>${chartJsSrc}<\/script>`
          : `<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"><\/script>`;

        const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Placement Analytics Report — <?= date('d M Y') ?></title>
${chartJsTag}
<style>
${styles}
/* Downloaded report overrides */
.navbar { position: relative !important; }
body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
</style>
</head>
<body>
${clone.innerHTML}
<script>${reinitScript}<\/script>
</body>
</html>`;

        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = Object.assign(document.createElement('a'), {
          href: url,
          download: 'placement_report_<?= date('Y-m-d') ?>.html'
        });
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);

      } finally {
        btn.innerHTML = origText;
        btn.disabled = false;
      }
    }

    // ── Export Helpers ───────────────────────────────────────

    function toggleExportMenu() {
      const m = document.getElementById('exportMenu');
      m.style.display = m.style.display === 'none' ? 'block' : 'none';
    }
    document.addEventListener('click', e => {
      if (!document.getElementById('exportDropdownWrap').contains(e.target))
        document.getElementById('exportMenu').style.display = 'none';
    });

    // ── Build all report data ────────────────────────────────
    function buildReportData() {
      const generated = <?= json_encode(date('d M Y, h:i A')) ?>;

      const kpi = [
        ['Metric', 'Value'],
        ['Total Students', <?= $total_students ?>],
        ['Students Placed', <?= $placed ?>],
        ['Students Unplaced', <?= $unplaced ?>],
        ['Placement Rate (%)', <?= $placement_pct ?>],
        ['Total Drives', <?= $total_drives ?>],
        ['Active Drives', <?= $active_drives ?>],
        ['Total Applications', <?= $total_apps ?>],
        ['Placement Ready Students', <?= $ready_students ?>],
      ];

      const statusLabels = <?= $js_status_labels ?>;
      const statusData = <?= $js_status_data ?>;
      const appStatus = [['Application Status', 'Count']];
      statusLabels.forEach((l, i) => appStatus.push([l, statusData[i]]));

      const deptLabels = <?= $js_dept_labels ?>;
      const deptTotal = <?= $js_dept_total ?>;
      const deptPlaced = <?= $js_dept_placed ?>;
      const dept = [['Department', 'Total Students', 'Placed', 'Placement Rate (%)']];
      deptLabels.forEach((d, i) => {
        const pct = deptTotal[i] > 0 ? Math.round((deptPlaced[i] / deptTotal[i]) * 100) : 0;
        dept.push([d, deptTotal[i], deptPlaced[i], pct]);
      });

      const batchLabels = <?= $js_batch_labels ?>;
      const batchTotal = <?= $js_batch_total ?>;
      const batchPlaced = <?= $js_batch_placed ?>;
      const batch = [['Batch', 'Total Students', 'Placed', 'Placement Rate (%)']];
      batchLabels.forEach((b, i) => {
        const pct = batchTotal[i] > 0 ? Math.round((batchPlaced[i] / batchTotal[i]) * 100) : 0;
        batch.push([b, batchTotal[i], batchPlaced[i], pct]);
      });

      const companyNames = <?= $js_company_names ?>;
      const companyCounts = <?= $js_company_counts ?>;
      const companies = [['Rank', 'Company', 'Students Placed']];
      companyNames.forEach((c, i) => companies.push([i + 1, c, companyCounts[i]]));

      const cgpaRaw = <?= $js_cgpa ?>;
      const cgpaGrades = ['A+ (≥9.0)', 'A (8–9)', 'B (7–8)', 'C (6–7)', 'D (<6)'];
      const cgpa = [['CGPA Grade', 'Student Count']];
      cgpaGrades.forEach((g, i) => cgpa.push([g, cgpaRaw[i]]));

      const eval_ = [
        ['Evaluation Component', 'Average Score (/100)'],
        ['Aptitude', <?= round($eval['avg_apt'] ?? 0) ?>],
        ['Group Discussion', <?= round($eval['avg_gd'] ?? 0) ?>],
        ['English', <?= round($eval['avg_eng'] ?? 0) ?>],
        ['Total Evaluated', <?= $eval['total_eval'] ?? 0 ?>],
      ];

      return { generated, kpi, appStatus, dept, batch, companies, cgpa, eval_ };
    }

    // ── CSV Export ───────────────────────────────────────────
    function exportCSV() {
      document.getElementById('exportMenu').style.display = 'none';
      const { generated, kpi, appStatus, dept, batch, companies, cgpa, eval_ } = buildReportData();

      const sections = [
        [`Placement Analytics Report — Generated: ${generated}`],
        [],
        ['=== KPI SUMMARY ==='], ...kpi,
        [],
        ['=== APPLICATION STATUS ==='], ...appStatus,
        [],
        ['=== DEPARTMENT-WISE PLACEMENT ==='], ...dept,
        [],
        ['=== BATCH-WISE PLACEMENT ==='], ...batch,
        [],
        ['=== TOP RECRUITING COMPANIES ==='], ...companies,
        [],
        ['=== CGPA DISTRIBUTION ==='], ...cgpa,
        [],
        ['=== PRE-PLACEMENT EVALUATION ==='], ...eval_,
      ];

      const csv = sections.map(row =>
        row.map(cell => {
          const s = String(cell ?? '');
          return s.includes(',') || s.includes('"') || s.includes('\n')
            ? `"${s.replace(/"/g, '""')}"` : s;
        }).join(',')
      ).join('\r\n');

      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), { href: url, download: `placement_report_<?= date('Y-m-d') ?>.csv` });
      document.body.appendChild(a); a.click();
      document.body.removeChild(a); URL.revokeObjectURL(url);
    }

  </script>

</body>

</html>