<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// ── KPI Stats ──────────────────────────────────────────────────
$total_students   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student"))['c'];
$placed           = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'];
$total_drives     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive"))['c'];
$total_apps       = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application"))['c'];
$ready_students   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student WHERE preplacement_status='Ready'"))['c'];
$active_drives    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_status='Open'"))['c'];
$shortlisted      = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application WHERE application_status='Shortlisted'"))['c'];
$rejected         = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application WHERE application_status='Rejected'"))['c'];
$placement_pct    = $total_students > 0 ? round(($placed / $total_students) * 100, 1) : 0;
$unplaced         = $total_students - $placed;
$conversion_rate  = $total_apps > 0 ? round(($placed / $total_apps) * 100, 1) : 0;
$avg_apps         = $total_drives > 0 ? round($total_apps / $total_drives, 1) : 0;

// ── Application Status Breakdown ───────────────────────────────
$status_res = mysqli_query($conn, "SELECT application_status, COUNT(*) as cnt FROM application GROUP BY application_status");
$status_labels = []; $status_data = []; $status_colors = [];
$color_map = ['Applied'=>'#6366f1','Shortlisted'=>'#f59e0b','Selected'=>'#10b981','Rejected'=>'#ef4444'];
while ($r = mysqli_fetch_assoc($status_res)) {
  $status_labels[] = $r['application_status'];
  $status_data[]   = (int)$r['cnt'];
  $status_colors[] = $color_map[$r['application_status']] ?? '#8b5cf6';
}

// ── Specialization Placement Rate (sorted by rate%) ───────────
$spec_rows = [];
$spec_res  = mysqli_query($conn,
  "SELECT s.specialization,
          COUNT(DISTINCT s.student_id) AS total,
          SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed
   FROM student s LEFT JOIN application a ON s.student_id=a.student_id
   GROUP BY s.specialization
   HAVING COUNT(DISTINCT s.student_id) > 0
   ORDER BY SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END)/COUNT(DISTINCT s.student_id) DESC, COUNT(DISTINCT s.student_id) DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($spec_res)) $spec_rows[] = $r;

// ── CGPA vs Placement Correlation ─────────────────────────────
$cgpa_corr_rows = [];
$cgpa_corr_res = mysqli_query($conn,
  "SELECT CASE
           WHEN s.cgpa >= 9 THEN 'A+ (≥9.0)'
           WHEN s.cgpa >= 8 THEN 'A  (8-9)'
           WHEN s.cgpa >= 7 THEN 'B  (7-8)'
           WHEN s.cgpa >= 6 THEN 'C  (6-7)'
           ELSE                  'D  (<6)'
         END AS band,
         COUNT(DISTINCT s.student_id) AS total,
         SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed,
         MIN(s.cgpa) AS min_cgpa
   FROM student s LEFT JOIN application a ON s.student_id=a.student_id
   GROUP BY band
   ORDER BY min_cgpa DESC");
while ($r = mysqli_fetch_assoc($cgpa_corr_res)) $cgpa_corr_rows[] = $r;

// ── Top Job Roles by Applications ─────────────────────────────
$role_labels = []; $role_apps = []; $role_placed = [];
$role_res = mysqli_query($conn,
  "SELECT d.job_title,
          COUNT(a.application_id) AS apps,
          SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed
   FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id
   GROUP BY d.job_title ORDER BY apps DESC LIMIT 8");
while ($r = mysqli_fetch_assoc($role_res)) {
  $lbl = mb_strlen($r['job_title']) > 22 ? mb_substr($r['job_title'],0,22).'…' : $r['job_title'];
  $role_labels[] = $lbl;
  $role_apps[]   = (int)$r['apps'];
  $role_placed[] = (int)$r['placed'];
}

// ── Drive Conversion Rate (top 8 drives by apps) ──────────────
$drv_conv_labels = []; $drv_conv_rates = []; $drv_conv_apps = [];
$drv_conv_res = mysqli_query($conn,
  "SELECT d.company_name, d.job_title,
          COUNT(a.application_id) AS apps,
          SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed
   FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id
   GROUP BY d.drive_id ORDER BY apps DESC LIMIT 8");
while ($r = mysqli_fetch_assoc($drv_conv_res)) {
  $co  = mb_strlen($r['company_name']) > 14 ? mb_substr($r['company_name'],0,14).'…' : $r['company_name'];
  $drv_conv_labels[] = $co;
  $drv_conv_apps[]   = (int)$r['apps'];
  $rate = $r['apps'] > 0 ? round(($r['placed']/$r['apps'])*100, 1) : 0;
  $drv_conv_rates[]  = $rate;
}

// ── Students: Applied vs Never Applied ────────────────────────
$applied_any   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application"))['c'];
$never_applied = $total_students - $applied_any;

// ── Monthly Trend ───────────────────────────────────────────────
$trend_res = mysqli_query($conn, "SELECT DATE_FORMAT(applied_at,'%b %Y') AS ml, DATE_FORMAT(applied_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM application WHERE applied_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mk,ml ORDER BY mk");
$trend_labels = []; $trend_data = [];
while ($r = mysqli_fetch_assoc($trend_res)) { $trend_labels[] = $r['ml']; $trend_data[] = (int)$r['cnt']; }

$sel_res = mysqli_query($conn, "SELECT DATE_FORMAT(applied_at,'%b %Y') AS ml, DATE_FORMAT(applied_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM application WHERE application_status='Selected' AND applied_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mk,ml ORDER BY mk");
$sel_map = [];
while ($r = mysqli_fetch_assoc($sel_res)) $sel_map[$r['ml']] = (int)$r['cnt'];
$sel_trend_data = array_map(fn($l) => $sel_map[$l] ?? 0, $trend_labels);

// ── Batch-wise ──────────────────────────────────────────────────
$batch_rows = [];
$br = @mysqli_query($conn, "SELECT s.batch, COUNT(DISTINCT s.student_id) AS total, SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed FROM student s LEFT JOIN application a ON s.student_id=a.student_id WHERE s.batch IS NOT NULL AND s.batch<>'' GROUP BY s.batch ORDER BY CAST(SUBSTRING_INDEX(s.batch,'-',1) AS UNSIGNED)");
while ($br && $r = mysqli_fetch_assoc($br)) $batch_rows[] = $r;

// ── Top Companies ───────────────────────────────────────────────
$company_names = []; $company_counts = [];
$cr = mysqli_query($conn, "SELECT d.company_name, COUNT(DISTINCT a.student_id) AS placements FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id WHERE a.application_status='Selected' GROUP BY d.company_name ORDER BY placements DESC LIMIT 8");
while ($c = mysqli_fetch_assoc($cr)) { $company_names[] = $c['company_name']; $company_counts[] = (int)$c['placements']; }

// ── Drive Status ────────────────────────────────────────────────
$drive_status_labels = []; $drive_status_data = [];
$ds_colors = ['Open'=>'#10b981','Closed'=>'#94a3b8','Active'=>'#6366f1'];
$dsr = mysqli_query($conn, "SELECT drive_status, COUNT(*) as cnt FROM placement_drive GROUP BY drive_status");
while ($r = mysqli_fetch_assoc($dsr)) { $drive_status_labels[] = $r['drive_status']; $drive_status_data[] = (int)$r['cnt']; }

// ── CGPA Distribution ───────────────────────────────────────────
$cgpa_res = mysqli_query($conn, "SELECT SUM(CASE WHEN cgpa>=9 THEN 1 ELSE 0 END) AS 'Ap', SUM(CASE WHEN cgpa>=8 AND cgpa<9 THEN 1 ELSE 0 END) AS 'A', SUM(CASE WHEN cgpa>=7 AND cgpa<8 THEN 1 ELSE 0 END) AS 'B', SUM(CASE WHEN cgpa>=6 AND cgpa<7 THEN 1 ELSE 0 END) AS 'C', SUM(CASE WHEN cgpa<6 THEN 1 ELSE 0 END) AS 'D' FROM student");
$cgpa = mysqli_fetch_assoc($cgpa_res);

// ── Backlogs ────────────────────────────────────────────────────
$blr = mysqli_query($conn, "SELECT SUM(CASE WHEN backlogs=0 THEN 1 ELSE 0 END) AS zero, SUM(CASE WHEN backlogs BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS one_two, SUM(CASE WHEN backlogs>=3 THEN 1 ELSE 0 END) AS three_plus FROM student");
$bl = mysqli_fetch_assoc($blr);

// ── Pre-placement Eval ──────────────────────────────────────────
$eval_res = mysqli_query($conn, "SELECT AVG(aptitude_marks) AS avg_apt, AVG(gd_marks) AS avg_gd, AVG(english_marks) AS avg_eng, MAX(aptitude_marks) AS max_apt, MAX(gd_marks) AS max_gd, MAX(english_marks) AS max_eng, COUNT(*) AS total_eval FROM preplacement_evaluation");
$eval = mysqli_fetch_assoc($eval_res);

// ── ATS Scores ──────────────────────────────────────────────────
$ats_res = mysqli_query($conn, "SELECT AVG(score) AS avg_score, MAX(score) AS max_score, COUNT(*) AS total FROM ats_score");
$ats = mysqli_fetch_assoc($ats_res);

// ── Alumni ──────────────────────────────────────────────────────
$alumni_total    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM alumni"))['c'];
$alumni_rec_total= (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM alumni_recommendation"))['c'];
$alumni_recs = [];
$arr = mysqli_query($conn, "SELECT r.job_title, r.company_name, a.name AS alumni_name, DATE_FORMAT(r.posted_at,'%d %b %Y') AS posted FROM alumni_recommendation r JOIN alumni a ON r.alumni_id=a.alumni_id ORDER BY r.posted_at DESC LIMIT 6");
while ($ar = mysqli_fetch_assoc($arr)) $alumni_recs[] = $ar;

// ── Recent Drives ────────────────────────────────────────────────
$recent_drives = mysqli_query($conn, "SELECT d.job_title, d.company_name, d.drive_date, d.drive_status, COUNT(DISTINCT a.application_id) AS apps, SUM(a.application_status='Selected') AS placed FROM placement_drive d LEFT JOIN application a ON d.drive_id=a.drive_id GROUP BY d.drive_id ORDER BY d.created_at DESC LIMIT 10");

// ── Log ──────────────────────────────────────────────────────────
$officer_id = (int)($_SESSION['officer_id'] ?? 0);
mysqli_query($conn, "INSERT INTO analytics_report (generated_by,report_type,generated_date) VALUES ($officer_id,'Dashboard View',CURDATE())");

// ── JS data ──────────────────────────────────────────────────────
$js = [
  'statusLabels'    => json_encode($status_labels),
  'statusData'      => json_encode($status_data),
  'statusColors'    => json_encode($status_colors),
  'specLabels'      => json_encode(array_column($spec_rows,'specialization')),
  'specRates'       => json_encode(array_map(fn($r) => $r['total']>0 ? round(($r['placed']/$r['total'])*100,1) : 0, $spec_rows)),
  'specPlaced'      => json_encode(array_map('intval', array_column($spec_rows,'placed'))),
  'specTotal'       => json_encode(array_map('intval', array_column($spec_rows,'total'))),
  'cgpaCorrLabels'  => json_encode(array_column($cgpa_corr_rows,'band')),
  'cgpaCorrTotal'   => json_encode(array_map('intval', array_column($cgpa_corr_rows,'total'))),
  'cgpaCorrPlaced'  => json_encode(array_map('intval', array_column($cgpa_corr_rows,'placed'))),
  'roleLabels'      => json_encode($role_labels),
  'roleApps'        => json_encode($role_apps),
  'rolePlaced'      => json_encode($role_placed),
  'drvConvLabels'   => json_encode($drv_conv_labels),
  'drvConvRates'    => json_encode($drv_conv_rates),
  'drvConvApps'     => json_encode($drv_conv_apps),
  'batchLabels'     => json_encode(array_column($batch_rows,'batch')),
  'batchTotal'      => json_encode(array_map('intval', array_column($batch_rows,'total'))),
  'batchPlaced'     => json_encode(array_map('intval', array_column($batch_rows,'placed'))),
  'compNames'       => json_encode($company_names),
  'compCounts'      => json_encode($company_counts),
  'trendLabels'     => json_encode($trend_labels),
  'trendData'       => json_encode($trend_data),
  'selData'         => json_encode($sel_trend_data),
  'dsLabels'        => json_encode($drive_status_labels),
  'dsData'          => json_encode($drive_status_data),
  'dsColors'        => json_encode(array_map(fn($l) => $ds_colors[$l] ?? '#94a3b8', $drive_status_labels)),
  'cgpa'            => json_encode([(int)($cgpa['Ap']??0),(int)($cgpa['A']??0),(int)($cgpa['B']??0),(int)($cgpa['C']??0),(int)($cgpa['D']??0)]),
  'backlog'         => json_encode([(int)($bl['zero']??0),(int)($bl['one_two']??0),(int)($bl['three_plus']??0)]),
  'appliedAny'      => $applied_any,
  'neverApplied'    => $never_applied,
  'placed'        => $placed,
  'unplaced'      => $unplaced,
];

$page_title     = 'Analytics & Reports — PMS';
$active_page    = 'reports';
$extra_scripts  = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
$extra_head     = '
<style>
/* ── Minimal custom additions on top of Bootstrap ── */
body { background:#f0f2f5; }

/* KPI cards */
.kpi-card { border:0; border-radius:16px; transition:transform .18s,box-shadow .18s; overflow:hidden; }
.kpi-card:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(0,0,0,.13)!important; }
.kpi-icon-box { width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.kpi-number { font-size:2.1rem;font-weight:800;line-height:1;letter-spacing:-1px; }

/* Nav pills override */
.nav-pills .nav-link { font-size:13px;font-weight:600;color:#64748b;padding:9px 18px;border-radius:10px; }
.nav-pills .nav-link.active { background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff; }
.nav-pills .nav-link:hover:not(.active) { background:#eef2ff;color:#6366f1; }

/* Section heading */
.an-section-hd { font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin:0 0 16px;padding:12px 0 8px;border-bottom:2px solid #e9ecef;display:flex;align-items:center;gap:8px; }


/* Analytics cards */
.an-card { border:0;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.07),0 4px 16px rgba(0,0,0,.06); }
.an-card .card-header { border-radius:16px 16px 0 0 !important;font-weight:700;font-size:.85rem;border:0; }

/* Funnel */
.funnel-bar { height:50px;border-radius:10px;display:flex;align-items:center;padding:0 16px;transition:width 1s cubic-bezier(.4,0,.2,1);min-width:60px; }
.funnel-label-row { display:flex;justify-content:space-between;font-size:12px;margin-top:5px; }

/* Progress bars */
.prog-row { display:flex;align-items:center;gap:10px;margin-bottom:10px; }
.prog-lbl { width:130px;font-size:12px;font-weight:600;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:0; }
.prog-val { min-width:70px;text-align:right;font-size:11px;font-weight:700;color:#64748b; }

/* CGPA pills */
.cgpa-pill { border-radius:14px;padding:14px 8px;text-align:center; }
.cgpa-pill .grade { font-size:16px;font-weight:800; }
.cgpa-pill .count { font-size:24px;font-weight:800;line-height:1;margin:4px 0; }
.cgpa-pill .range { font-size:10px;color:#64748b; }

/* Activity feed */
.feed-item { padding:10px 0;border-bottom:1px solid #e9ecef;display:flex;gap:10px;align-items:flex-start; }
.feed-item:last-child { border:none; }
.feed-dot { width:9px;height:9px;border-radius:50%;flex-shrink:0;margin-top:5px; }

/* Company rank badge */
.rank-1 { background:#fef9c3;color:#92400e; }
.rank-2 { background:#f1f5f9;color:#475569; }
.rank-3 { background:#ffedd5;color:#c2410c; }
.rank-n { background:#ecfdf5;color:#065f46; }

/* Gauge */
.gauge-wrap { position:relative;width:140px;height:140px;margin:0 auto; }
.gauge-wrap svg { transform:rotate(-90deg); }
.gauge-lbl { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center; }
.gauge-lbl .val  { font-size:24px;font-weight:800;line-height:1; }
.gauge-lbl .unit { font-size:11px;color:#64748b; }

/* Export button */
.btn-export { background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:0;font-weight:600; }
.btn-export:hover { opacity:.88;color:#fff; }

/* Counter animation */
.counter { display:inline-block; }

/* Insight alert */
.insight-alert { border-radius:12px;border:0;padding:14px 16px; }

@media(max-width:768px){ .kpi-number{font-size:1.6rem;} }

@media print {
  .header-navbar, #exportDropdownWrap, nav[aria-label="breadcrumb"] { display:none !important; }
  body { background:#fff !important; }
  .an-card, .kpi-card { box-shadow:none !important; border:1px solid #dee2e6 !important; break-inside:avoid; }
  .page-content { padding:0 !important; }
  canvas { max-height:200px !important; }
  h1 { color:#1b5e20 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>';
require_once '_nav.php';
?>

<!-- ── Breadcrumb ────────────────────────────────────────────── -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house me-1"></i>Dashboard</a></li>
    <li class="breadcrumb-item active">Analytics &amp; Reports</li>
  </ol>
</nav>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
  <div>
    <h1 class="fw-bold mb-1" style="font-size:1.5rem;color:#1b5e20">
      <i class="bi bi-graph-up-arrow me-2"></i>Analytics &amp; Placement Reports
    </h1>
    <p class="text-muted mb-0" style="font-size:.82rem">Real-time placement intelligence &nbsp;·&nbsp; Generated <?= date('d M Y, h:i A') ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap" id="exportDropdownWrap">
    <a href="reports_html_export.php" target="_blank" class="btn btn-outline-primary rounded-3 d-flex align-items-center gap-2 px-3 fw-semibold" style="font-size:.83rem">
      <i class="bi bi-filetype-html"></i> Interactive HTML
    </a>
    <button class="btn btn-export rounded-3 d-flex align-items-center gap-2 px-3 fw-semibold" onclick="exportCSV()" type="button" style="font-size:.83rem">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </button>
    <button class="btn btn-outline-secondary rounded-3 d-flex align-items-center gap-2 px-3 fw-semibold" onclick="printReport()" type="button" style="font-size:.83rem">
      <i class="bi bi-printer"></i> Print / PDF
    </button>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 1 — KPI STRIP
══════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    ['Total Students',  $total_students,  '#10b981','#ecfdf5','bi-mortarboard-fill',   $unplaced.' yet to place'],
    ['Placed',          $placed,          '#6366f1','#eef2ff','bi-patch-check-fill',    'Across all drives'],
    ['Placement Rate',  $placement_pct.'%','#0ea5e9','#f0f9ff','bi-percent',            'Of total enrolled'],
    ['Drives Posted',   $total_drives,    '#8b5cf6','#f5f3ff','bi-briefcase-fill',      $active_drives.' open now'],
    ['Applications',    $total_apps,      '#f59e0b','#fffbeb','bi-file-earmark-text-fill',$avg_apps.' avg / drive'],
    ['Conversion Rate', $conversion_rate.'%','#ef4444','#fff1f2','bi-arrow-repeat',     'App → Selected'],
  ];
  foreach ($kpis as [$lbl,$val,$col,$bg,$ico,$sub]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card kpi-card shadow-sm h-100">
      <div class="card-body p-3">
        <div class="kpi-icon-box mb-2" style="background:<?= $bg ?>;color:<?= $col ?>"><i class="bi <?= $ico ?>"></i></div>
        <div class="kpi-number" style="color:<?= $col ?>"><?= $val ?></div>
        <div class="fw-semibold text-uppercase text-muted mt-1" style="font-size:.68rem;letter-spacing:.5px"><?= $lbl ?></div>
        <div class="border-top mt-2 pt-2 text-muted" style="font-size:.68rem"><?= $sub ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 2 — FUNNEL + PLACEMENT GAUGE + APPLICATION STATUS
══════════════════════════════════════════════════════════════ -->
<div class="an-section-hd">📊 Recruitment Funnel &amp; Overview</div>
<div class="row g-4 mb-4">

  <!-- Funnel -->
  <div class="col-lg-5">
    <div class="card an-card h-100">
      <div class="card-header border-0 py-3 px-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <span class="fw-bold text-white"><i class="bi bi-filter me-2"></i>Recruitment Funnel</span>
        <small class="text-white opacity-75 ms-2" style="font-size:.75rem">Drop-off per stage</small>
      </div>
      <div class="card-body p-4">
        <?php
        $f_stages = [
          ['Applied',     $total_apps,  100,                                                         '#6366f1'],
          ['Shortlisted', $shortlisted, $total_apps>0?round($shortlisted/$total_apps*100,1):0,       '#f59e0b'],
          ['Selected',    $placed,      $total_apps>0?round($placed/$total_apps*100,1):0,            '#10b981'],
        ];
        $prev_v = $total_apps;
        foreach ($f_stages as $fi => [$stage,$val,$pct,$col]):
          $drop = $fi>0 && $prev_v>0 ? round((($prev_v-$val)/$prev_v)*100) : 0;
          $prev_v = $val;
        ?>
        <div class="mb-1">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="d-flex align-items-center gap-2">
              <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                style="width:24px;height:24px;background:<?= $col ?>;font-size:11px;flex-shrink:0"><?= $fi+1 ?></span>
              <span class="fw-bold" style="font-size:13px"><?= $stage ?></span>
            </div>
            <div>
              <span class="fw-black" style="font-size:17px;color:<?= $col ?>"><?= $val ?></span>
              <span class="text-muted ms-1" style="font-size:12px">(<?= $pct ?>%)</span>
            </div>
          </div>
          <div class="rounded-3 overflow-hidden" style="height:34px;background:#f8fafc;border:1px solid #e9ecef">
            <div style="width:<?= max($pct,2) ?>%;height:100%;background:<?= $col ?>;border-radius:8px;display:flex;align-items:center;padding:0 10px">
              <?php if ($pct>18): ?><span class="text-white fw-semibold" style="font-size:11px;white-space:nowrap"><?= $stage ?> — <?= $pct ?>%</span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($fi<2): ?>
        <div class="d-flex align-items-center gap-2 my-2 px-1" style="font-size:11px">
          <div style="flex:1;height:1px;background:#e9ecef"></div>
          <?php if ($drop>0): ?>
          <span class="badge rounded-pill" style="background:#fff1f2;color:#ef4444;font-size:10px"><i class="bi bi-arrow-down-short"></i> <?= $drop ?>% drop-off</span>
          <?php else: ?>
          <span style="color:#10b981">↓</span>
          <?php endif; ?>
          <div style="flex:1;height:1px;background:#e9ecef"></div>
        </div>
        <?php endif; endforeach; ?>
        <div class="mt-3 p-3 rounded-3 d-flex align-items-center justify-content-between" style="background:#fff1f2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill text-danger"></i>
            <span class="fw-semibold" style="font-size:13px">Rejected</span>
          </div>
          <div>
            <span class="fw-black text-danger" style="font-size:15px"><?= $rejected ?></span>
            <span class="text-muted ms-1" style="font-size:12px">(<?= $total_apps>0?round($rejected/$total_apps*100,1):0 ?>%)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Placement Gauge -->
  <div class="col-lg-3">
    <div class="card an-card h-100">
      <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center text-center">
        <div class="fw-bold mb-3" style="font-size:.85rem;color:#374151"><i class="bi bi-speedometer text-success me-1"></i>Placement Rate</div>
        <div class="gauge-wrap mb-3">
          <svg viewBox="0 0 140 140" width="130" height="130">
            <circle fill="none" stroke="#f1f5f9" stroke-width="16" cx="70" cy="70" r="54"/>
            <circle fill="none" stroke="<?= $placement_pct>=70?'#10b981':($placement_pct>=40?'#f59e0b':'#ef4444') ?>" stroke-width="16"
              stroke-linecap="round" cx="70" cy="70" r="54"
              stroke-dasharray="<?= 2*3.14159*54 ?>"
              stroke-dashoffset="<?= 2*3.14159*54*(1-$placement_pct/100) ?>"/>
          </svg>
          <div class="gauge-lbl">
            <div class="val" style="color:<?= $placement_pct>=70?'#10b981':($placement_pct>=40?'#f59e0b':'#ef4444') ?>"><?= $placement_pct ?>%</div>
            <div class="unit">placed</div>
          </div>
        </div>
        <?php foreach ([['Placed',$placed,'#10b981','#ecfdf5'],['Shortlisted',$shortlisted,'#f59e0b','#fffbeb'],['Unplaced',$unplaced,'#94a3b8','#f8fafc']] as [$gl,$gv,$gc,$gbg]): ?>
        <div class="d-flex align-items-center justify-content-between w-100 mb-2" style="font-size:12px">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle" style="width:8px;height:8px;background:<?= $gc ?>;display:inline-block"></span>
            <span class="fw-semibold"><?= $gl ?></span>
          </div>
          <span class="badge rounded-pill fw-bold" style="background:<?= $gbg ?>;color:<?= $gc ?>"><?= $gv ?></span>
        </div>
        <?php endforeach; ?>
        <div class="w-100 mt-2">
          <div style="height:8px;background:#f1f5f9;border-radius:6px;overflow:hidden">
            <div style="width:<?= $placement_pct ?>%;height:100%;background:linear-gradient(90deg,#10b981,#34d399);border-radius:6px"></div>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:10px;color:#94a3b8"><span>0%</span><span>100%</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Application Status Donut -->
  <div class="col-lg-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom">
        <span class="fw-bold" style="font-size:.88rem"><i class="bi bi-pie-chart-fill me-1" style="color:#6366f1"></i>Application Status</span>
      </div>
      <div class="card-body p-4">
        <div class="row align-items-center g-3">
          <div class="col-5">
            <div class="position-relative" style="height:130px">
              <canvas id="statusChart"></canvas>
              <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none">
                <div class="fw-black" style="font-size:20px"><?= $total_apps ?></div>
                <div class="text-muted" style="font-size:10px">Total</div>
              </div>
            </div>
          </div>
          <div class="col-7">
            <?php foreach ($status_labels as $i => $lbl):
              $pct2 = $total_apps>0 ? round(($status_data[$i]/$total_apps)*100) : 0; ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12px">
                <div class="d-flex align-items-center gap-1">
                  <span class="rounded-circle" style="width:8px;height:8px;background:<?= $status_colors[$i] ?>;display:inline-block"></span>
                  <span class="fw-semibold"><?= htmlspecialchars($lbl) ?></span>
                </div>
                <span class="fw-bold"><?= $status_data[$i] ?> <span class="text-muted fw-normal"><?= $pct2 ?>%</span></span>
              </div>
              <div class="progress" style="height:5px;border-radius:4px">
                <div class="progress-bar" style="width:<?= $pct2 ?>%;background:<?= $status_colors[$i] ?>;border-radius:4px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Insight strip -->
        <div class="mt-3 pt-3 border-top d-flex gap-3 flex-wrap">
          <div class="text-center flex-fill px-2 py-2 rounded-3" style="background:#eef2ff">
            <div class="fw-black" style="color:#6366f1;font-size:1.1rem"><?= $applied_any ?></div>
            <div class="text-muted" style="font-size:10px">Applied</div>
          </div>
          <div class="text-center flex-fill px-2 py-2 rounded-3" style="background:#fff1f2">
            <div class="fw-black" style="color:#ef4444;font-size:1.1rem"><?= $never_applied ?></div>
            <div class="text-muted" style="font-size:10px">Never Applied</div>
          </div>
          <div class="text-center flex-fill px-2 py-2 rounded-3" style="background:#ecfdf5">
            <div class="fw-black" style="color:#10b981;font-size:1.1rem"><?= $ready_students ?></div>
            <div class="text-muted" style="font-size:10px">Ready</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 3 — 6-MONTH TREND
══════════════════════════════════════════════════════════════ -->
<div class="an-section-hd">📈 Application &amp; Selection Trend</div>
<div class="card an-card mb-4">
  <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-graph-up" style="color:#6366f1"></i>
    <span class="fw-bold" style="font-size:.88rem">Last 6 Months — Applications vs Selections</span>
    <span class="badge ms-auto" style="background:#eef2ff;color:#6366f1;font-size:.65rem">Live</span>
    <div class="d-flex gap-3 ms-2" style="font-size:12px">
      <span><span class="rounded-1 d-inline-block me-1" style="width:12px;height:12px;background:#6366f1"></span>Applications</span>
      <span><span class="rounded-1 d-inline-block me-1" style="width:12px;height:12px;background:#10b981"></span>Selections</span>
    </div>
  </div>
  <div class="card-body p-4">
    <?php if (empty($trend_labels)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem"></i>No activity in the last 6 months.</div>
    <?php else: ?>
    <div style="height:260px;position:relative"><canvas id="trendChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 4 — STUDENT ANALYTICS
══════════════════════════════════════════════════════════════ -->
<div class="an-section-hd">🎓 Student Analytics</div>
<div class="row g-4 mb-4">

  <!-- CGPA vs Placement Correlation -->
  <div class="col-lg-7">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-graph-up-arrow" style="color:#6366f1"></i>
        <span class="fw-bold" style="font-size:.88rem">CGPA vs Placement Correlation</span>
        <span class="badge ms-auto" style="background:#eef2ff;color:#6366f1;font-size:.65rem">Most insightful</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($cgpa_corr_rows)): ?>
        <div class="text-center text-muted py-5">No CGPA data yet.</div>
        <?php else: ?>
        <div style="height:220px;position:relative;margin-bottom:14px"><canvas id="cgpaCorrChart"></canvas></div>
        <?php
        $best_band = null; $best_rate = -1;
        foreach ($cgpa_corr_rows as $cr) {
          $rate = $cr['total']>0 ? round($cr['placed']/$cr['total']*100) : 0;
          if ($rate > $best_rate) { $best_rate=$rate; $best_band=$cr['band']; }
        }
        if ($best_band): ?>
        <div class="alert mb-0 py-2 px-3 rounded-3" style="background:#ecfdf5;border:0;font-size:12px">
          <i class="bi bi-lightbulb-fill text-success me-1"></i>
          <strong><?= htmlspecialchars($best_band) ?></strong> students have the highest placement rate at <strong><?= $best_rate ?>%</strong>.
        </div>
        <?php endif; endif; ?>
      </div>
    </div>
  </div>

  <!-- Specialization Placement Rate -->
  <div class="col-lg-5">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-horizontal-fill" style="color:#8b5cf6"></i>
        <span class="fw-bold" style="font-size:.88rem">Specialization Placement Rate</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($spec_rows)): ?>
        <div class="text-center text-muted py-5">No data.</div>
        <?php else: ?>
        <div style="height:<?= max(200,count($spec_rows)*44) ?>px;position:relative"><canvas id="specChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Row: CGPA Distribution + Backlogs + Batch -->
<div class="row g-4 mb-4">

  <!-- CGPA Distribution -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-fill" style="color:#0ea5e9"></i>
        <span class="fw-bold" style="font-size:.88rem">CGPA Distribution</span>
      </div>
      <div class="card-body p-4">
        <div style="height:190px;position:relative"><canvas id="cgpaChart"></canvas></div>
        <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
          <?php foreach ([['A+≥9','#10b981',$cgpa['Ap']??0],['A 8-9','#34d399',$cgpa['A']??0],['B 7-8','#6366f1',$cgpa['B']??0],['C 6-7','#f59e0b',$cgpa['C']??0],['D <6','#ef4444',$cgpa['D']??0]] as [$g,$c,$n]): ?>
          <div class="text-center px-2 py-1 rounded-2" style="background:<?= $c ?>18;min-width:44px">
            <div class="fw-bold" style="color:<?= $c ?>;font-size:.85rem"><?= (int)$n ?></div>
            <div style="font-size:.62rem;color:#64748b"><?= $g ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Backlogs -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b"></i>
        <span class="fw-bold" style="font-size:.88rem">Backlog Status</span>
      </div>
      <div class="card-body p-4">
        <div style="height:130px;position:relative;margin-bottom:16px">
          <canvas id="backlogChart"></canvas>
          <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none">
            <div class="fw-black" style="font-size:18px"><?= $total_students ?></div>
            <div class="text-muted" style="font-size:10px">Total</div>
          </div>
        </div>
        <?php foreach ([['Zero Backlogs',(int)($bl['zero']??0),'#10b981','#ecfdf5'],['1–2 Backlogs',(int)($bl['one_two']??0),'#f59e0b','#fffbeb'],['3+ Backlogs',(int)($bl['three_plus']??0),'#ef4444','#fff1f2']] as [$lbl,$n,$c,$bg]): ?>
        <div class="d-flex align-items-center justify-content-between mb-2" style="font-size:13px">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle" style="width:9px;height:9px;background:<?= $c ?>;display:inline-block"></span>
            <span class="fw-semibold"><?= $lbl ?></span>
          </div>
          <span class="badge rounded-pill fw-bold" style="background:<?= $bg ?>;color:<?= $c ?>"><?= $n ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Batch-wise -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-calendar3" style="color:#8b5cf6"></i>
        <span class="fw-bold" style="font-size:.88rem">Batch-wise Placement</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($batch_rows)): ?>
        <div class="text-center text-muted py-4" style="font-size:13px"><i class="bi bi-info-circle d-block mb-2" style="font-size:1.4rem"></i>No batch data assigned.</div>
        <?php else: ?>
        <div style="height:150px;position:relative;margin-bottom:14px"><canvas id="batchChart"></canvas></div>
        <?php foreach ($batch_rows as $br):
          $bp = $br['total']>0 ? round(($br['placed']/$br['total'])*100) : 0;
          $bc = $bp>=70?'#10b981':($bp>=40?'#8b5cf6':'#f59e0b'); ?>
        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px">
          <span class="fw-bold" style="width:72px;flex-shrink:0;color:#374151"><?= htmlspecialchars($br['batch']) ?></span>
          <div class="flex-grow-1"><div class="progress" style="height:7px;border-radius:5px"><div class="progress-bar" style="width:<?= $bp ?>%;background:<?= $bc ?>;border-radius:5px"></div></div></div>
          <span class="fw-bold" style="color:<?= $bc ?>;min-width:52px;text-align:right"><?= $br['placed'] ?>/<?= $br['total'] ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Row: Pre-placement Eval + ATS + Student Status -->
<div class="row g-4 mb-4">

  <!-- Pre-placement Evaluation -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-clipboard-data-fill" style="color:#0ea5e9"></i>
        <span class="fw-bold" style="font-size:.88rem">Pre-placement Evaluation</span>
        <?php if (!empty($eval['total_eval'])): ?><span class="badge ms-auto" style="background:#f0f9ff;color:#0ea5e9;font-size:.65rem"><?= $eval['total_eval'] ?> evals</span><?php endif; ?>
      </div>
      <div class="card-body p-4">
        <?php if (empty($eval['total_eval'])): ?>
        <div class="text-center text-muted py-4">No evaluation data yet.</div>
        <?php else:
        foreach ([['Aptitude',round($eval['avg_apt']??0),round($eval['max_apt']??0),'#6366f1'],['Group Discussion',round($eval['avg_gd']??0),round($eval['max_gd']??0),'#10b981'],['English',round($eval['avg_eng']??0),round($eval['max_eng']??0),'#f59e0b']] as [$comp,$avg,$max,$c]):
          $pct = $max>0 ? round($avg/$max*100) : 0; ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12px">
            <span class="fw-semibold"><?= $comp ?></span>
            <span class="fw-bold" style="color:<?= $c ?>"><?= $avg ?> <span class="text-muted fw-normal">/ <?= $max ?> max</span></span>
          </div>
          <div class="progress" style="height:8px;border-radius:5px">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $c ?>;border-radius:5px"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- ATS Score -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-check-fill" style="color:#8b5cf6"></i>
        <span class="fw-bold" style="font-size:.88rem">ATS Resume Scores</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($ats['total'])): ?>
        <div class="text-center text-muted py-4">No ATS data yet.</div>
        <?php else: ?>
        <div class="row g-3 text-center">
          <?php foreach ([['Avg Score',round($ats['avg_score']??0),'#8b5cf6','#f5f3ff'],['Best Score',(int)($ats['max_score']??0),'#10b981','#ecfdf5'],['Checks',(int)($ats['total']??0),'#374151','#f8fafc']] as [$l,$v,$c,$bg]): ?>
          <div class="col-4">
            <div class="rounded-3 py-3" style="background:<?= $bg ?>">
              <div class="fw-black" style="font-size:1.4rem;color:<?= $c ?>"><?= $v ?></div>
              <div style="font-size:.65rem;color:#64748b;font-weight:600"><?= $l ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Student Placement Status -->
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-people-fill text-primary"></i>
        <span class="fw-bold" style="font-size:.88rem">Student Status Overview</span>
      </div>
      <div class="card-body p-4">
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <?php foreach ([['Placed',$placed,'#10b981','#ecfdf5'],['Shortlisted',$shortlisted,'#f59e0b','#fffbeb'],['Unplaced',$unplaced,'#94a3b8','#f8fafc']] as [$l,$v,$c,$bg]): ?>
          <div class="text-center px-2 py-2 rounded-3 flex-fill" style="background:<?= $bg ?>">
            <div class="fw-black" style="font-size:1.4rem;color:<?= $c ?>"><?= $v ?></div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700"><?= $l ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php
        $p_pct  = $total_students>0 ? round($placed/$total_students*100) : 0;
        $sl_pct = $total_students>0 ? round($shortlisted/$total_students*100) : 0;
        $un_pct = max(0, 100-$p_pct-$sl_pct);
        ?>
        <div class="rounded-3 overflow-hidden d-flex" style="height:14px;background:#f1f5f9">
          <div style="width:<?= $p_pct ?>%;background:#10b981" title="Placed"></div>
          <div style="width:<?= $sl_pct ?>%;background:#f59e0b" title="Shortlisted"></div>
          <div style="width:<?= $un_pct ?>%;background:#e2e8f0" title="Unplaced"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size:11px;color:#94a3b8">
          <span><?= $p_pct ?>% placed</span>
          <span><?= $total_students ?> total</span>
          <span><?= $un_pct ?>% unplaced</span>
        </div>
        <div class="mt-3 pt-3 border-top">
          <div class="d-flex justify-content-between" style="font-size:12px">
            <span class="text-muted">Ready students</span>
            <span class="fw-bold"><?= $ready_students ?> <span class="text-muted fw-normal">(<?= $total_students>0?round($ready_students/$total_students*100):0 ?>%)</span></span>
          </div>
          <div class="progress mt-1" style="height:5px;border-radius:4px">
            <div class="progress-bar" style="width:<?= $total_students>0?round($ready_students/$total_students*100):0 ?>%;background:#6366f1;border-radius:4px"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 5 — COMPANIES & DRIVES
══════════════════════════════════════════════════════════════ -->
<div class="an-section-hd">🏢 Companies &amp; Drives</div>
<div class="row g-4 mb-4">

  <!-- Top Job Roles -->
  <div class="col-lg-6">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-briefcase-fill" style="color:#6366f1"></i>
        <span class="fw-bold" style="font-size:.88rem">Top Job Roles by Applications</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($role_labels)): ?>
        <div class="text-center text-muted py-5">No application data yet.</div>
        <?php else: ?>
        <div style="height:<?= max(200,count($role_labels)*40) ?>px;position:relative"><canvas id="roleChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Drive Conversion Rate -->
  <div class="col-lg-6">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-percent" style="color:#f59e0b"></i>
        <span class="fw-bold" style="font-size:.88rem">Drive Conversion Rate</span>
        <span class="badge ms-auto" style="background:#fffbeb;color:#d97706;font-size:.68rem">% selected per drive</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($drv_conv_labels)): ?>
        <div class="text-center text-muted py-5">No drive data yet.</div>
        <?php else: ?>
        <div style="height:<?= max(200,count($drv_conv_labels)*38) ?>px;position:relative;margin-bottom:10px"><canvas id="drvConvChart"></canvas></div>
        <?php foreach ($drv_conv_labels as $i => $co):
          $col = $drv_conv_rates[$i]>=50?'#10b981':($drv_conv_rates[$i]>=25?'#f59e0b':'#ef4444'); ?>
        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px">
          <div style="width:90px;font-weight:600;color:#374151;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($co) ?>"><?= htmlspecialchars($co) ?></div>
          <div class="flex-grow-1"><div class="progress" style="height:7px;border-radius:5px"><div class="progress-bar" style="width:<?= $drv_conv_rates[$i] ?>%;background:<?= $col ?>;border-radius:5px"></div></div></div>
          <span class="fw-bold" style="color:<?= $col ?>;min-width:38px;text-align:right"><?= $drv_conv_rates[$i] ?>%</span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</div>

<div class="row g-4 mb-4">

  <!-- Top Companies -->
  <div class="col-lg-5">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-trophy-fill text-warning"></i>
        <span class="fw-bold" style="font-size:.88rem">Top Recruiting Companies</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($company_names)): ?>
        <div class="text-center text-muted py-5">No placement data yet.</div>
        <?php else: ?>
        <div style="height:<?= max(200,count($company_names)*36) ?>px;position:relative;margin-bottom:12px"><canvas id="companiesChart"></canvas></div>
        <?php $max_c = max(array_merge($company_counts,[1]));
        foreach ($company_names as $i => $cn):
          $bw = round(($company_counts[$i]/$max_c)*100);
          $rc = ['rank-1','rank-2','rank-3'][$i] ?? 'rank-n'; ?>
        <div class="d-flex align-items-center gap-2 py-2 border-bottom" style="font-size:13px">
          <span class="badge rounded-2 <?= $rc ?>" style="min-width:24px;min-height:24px;display:inline-flex;align-items:center;justify-content:center"><?= $i+1 ?></span>
          <span class="fw-semibold flex-grow-1"><?= htmlspecialchars($cn) ?></span>
          <div style="width:70px;height:6px;background:#f1f5f9;border-radius:4px;overflow:hidden;flex-shrink:0">
            <div style="width:<?= $bw ?>%;height:100%;background:linear-gradient(90deg,#10b981,#34d399);border-radius:4px"></div>
          </div>
          <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?= $company_counts[$i] ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Drive Performance -->
  <div class="col-lg-7">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-calendar-event-fill" style="color:#0ea5e9"></i>
        <span class="fw-bold" style="font-size:.88rem">Recent Drive Performance</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:13px">
            <thead class="table-light">
              <tr>
                <th class="px-4 py-3 fw-bold text-uppercase text-muted border-0" style="font-size:11px">Drive</th>
                <th class="py-3 fw-bold text-uppercase text-muted border-0" style="font-size:11px">Status</th>
                <th class="py-3 fw-bold text-uppercase text-muted border-0 text-center" style="font-size:11px">Apps</th>
                <th class="py-3 fw-bold text-uppercase text-muted border-0 text-center" style="font-size:11px">Selected</th>
                <th class="py-3 fw-bold text-uppercase text-muted border-0" style="font-size:11px">Rate</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($d = mysqli_fetch_assoc($recent_drives)):
                $rate = $d['apps']>0 ? round(($d['placed']/$d['apps'])*100) : 0;
                $sb = match($d['drive_status']) { 'Open'=>'bg-success bg-opacity-10 text-success','Closed'=>'bg-secondary bg-opacity-10 text-secondary',default=>'bg-primary bg-opacity-10 text-primary' };
                $bc = $rate>50?'#10b981':($rate>20?'#f59e0b':'#ef4444'); ?>
              <tr>
                <td class="px-4 py-3">
                  <div class="fw-bold text-dark" style="font-size:12px"><?= htmlspecialchars($d['job_title']) ?></div>
                  <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($d['company_name']) ?></div>
                </td>
                <td class="py-3"><span class="badge <?= $sb ?>"><?= htmlspecialchars($d['drive_status']) ?></span></td>
                <td class="py-3 text-center fw-bold"><?= $d['apps'] ?></td>
                <td class="py-3 text-center fw-bold" style="color:#10b981"><?= $d['placed'] ?></td>
                <td class="py-3">
                  <div class="d-flex align-items-center gap-2">
                    <div style="width:50px;height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden">
                      <div style="width:<?= $rate ?>%;height:100%;background:<?= $bc ?>;border-radius:3px"></div>
                    </div>
                    <span class="fw-bold" style="font-size:11px;color:<?= $bc ?>"><?= $rate ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Drive Status -->
<div class="row g-4 mb-4">
  <div class="col-md-5">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-briefcase text-success"></i>
        <span class="fw-bold" style="font-size:.88rem">Drive Status Breakdown</span>
        <span class="badge ms-auto bg-success bg-opacity-10 text-success"><?= $active_drives ?> Open</span>
      </div>
      <div class="card-body p-4">
        <div class="row align-items-center">
          <div class="col-5">
            <div class="position-relative" style="height:120px">
              <canvas id="driveStatusChart"></canvas>
              <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none">
                <div class="fw-black" style="font-size:18px"><?= $total_drives ?></div>
                <div class="text-muted" style="font-size:10px">Drives</div>
              </div>
            </div>
          </div>
          <div class="col-7">
            <?php foreach ($drive_status_labels as $i => $lbl):
              $col = $ds_colors[$lbl] ?? '#94a3b8';
              $pct = $total_drives>0 ? round(($drive_status_data[$i]/$total_drives)*100) : 0; ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12px">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle" style="width:8px;height:8px;background:<?= $col ?>;display:inline-block"></span>
                  <span class="fw-semibold"><?= htmlspecialchars($lbl) ?></span>
                </div>
                <span class="fw-bold"><?= $drive_status_data[$i] ?> <span class="text-muted fw-normal">(<?= $pct ?>%)</span></span>
              </div>
              <div class="progress" style="height:5px;border-radius:4px">
                <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>;border-radius:4px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Insight Cards -->
  <div class="col-md-7">
    <div class="row g-3 h-100">
      <div class="col-12">
        <div class="card border-0 rounded-3 h-100" style="background:linear-gradient(135deg,#eff6ff,#dbeafe)">
          <div class="card-body p-4 d-flex gap-3 align-items-start">
            <span style="font-size:1.8rem">💡</span>
            <div>
              <div class="fw-bold" style="font-size:13px;color:#1d4ed8">Conversion Insight</div>
              <div style="font-size:13px;color:#374151"><?= $conversion_rate ?>% of <?= $total_apps ?> applications led to placement. <?= $conversion_rate>=30?'<span class="text-success fw-semibold">Excellent!</span>':($conversion_rate>=15?'Moderate — aim for 30%+':'<span class="text-danger fw-semibold">Needs improvement.</span>') ?></div>
              <div class="mt-2" style="font-size:12px;color:#64748b"><?= $shortlisted ?> shortlisted · <?= $rejected ?> rejected · <?= $ready_students ?> ready</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card border-0 rounded-3 h-100" style="background:linear-gradient(135deg,#ecfdf5,#d1fae5)">
          <div class="card-body p-3 text-center">
            <div class="fw-black" style="font-size:2rem;color:#10b981"><?= $active_drives ?></div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#065f46">Open Drives</div>
            <div style="font-size:11px;color:#6b7280" class="mt-1">Actively recruiting now</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card border-0 rounded-3 h-100" style="background:linear-gradient(135deg,#f5f3ff,#ede9fe)">
          <div class="card-body p-3 text-center">
            <div class="fw-black" style="font-size:2rem;color:#8b5cf6"><?= $avg_apps ?></div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#5b21b6">Avg Apps / Drive</div>
            <div style="font-size:11px;color:#6b7280" class="mt-1">Student engagement rate</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SECTION 6 — ALUMNI
══════════════════════════════════════════════════════════════ -->
<?php if ($alumni_total > 0): ?>
<div class="an-section-hd">🌐 Alumni Network</div>
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card an-card h-100">
      <div class="card-body p-4 text-center">
        <div class="fw-black" style="font-size:3rem;color:#6366f1"><?= $alumni_total ?></div>
        <div class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.5px">Alumni Registered</div>
        <div class="mt-3 pt-3 border-top">
          <div class="fw-black" style="font-size:2rem;color:#10b981"><?= $alumni_rec_total ?></div>
          <div class="fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase">Job Recommendations</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card an-card h-100">
      <div class="card-header py-3 px-4 bg-white border-bottom">
        <span class="fw-bold" style="font-size:.88rem"><i class="bi bi-megaphone-fill me-2" style="color:#6366f1"></i>Latest Alumni Job Postings</span>
      </div>
      <div class="card-body p-4">
        <?php if (empty($alumni_recs)): ?>
        <div class="text-center text-muted py-4">No recommendations yet.</div>
        <?php else: foreach ($alumni_recs as $ar): ?>
        <div class="feed-item">
          <div class="feed-dot mt-1" style="background:#6366f1"></div>
          <div>
            <div class="fw-bold" style="font-size:13px"><?= htmlspecialchars($ar['job_title']) ?> <span class="text-muted fw-normal">@ <?= htmlspecialchars($ar['company_name']) ?></span></div>
            <div class="text-muted" style="font-size:11px">By <?= htmlspecialchars($ar['alumni_name']) ?> · <?= $ar['posted'] ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     CHART.JS SCRIPTS
══════════════════════════════════════════════════════════════ -->
<script>
window.addEventListener('load', function () {
  if (typeof Chart === 'undefined') {
    document.querySelectorAll('canvas').forEach(c => {
      const el = document.createElement('div');
      el.className = 'd-flex align-items-center justify-content-center text-muted w-100 h-100';
      el.style.cssText = 'font-size:12px;min-height:80px';
      el.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Chart.js not loaded — check internet or run dl_chartjs.php';
      c.parentNode.replaceChild(el, c);
    });
    return;
  }
  Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
  Chart.defaults.color = '#64748b';
  const INDIGO='#6366f1', EMERALD='#10b981', AMBER='#f59e0b', ROSE='#ef4444', VIOLET='#8b5cf6', SKY='#0ea5e9';

  // 1 · Status Donut
  const sc = document.getElementById('statusChart');
  if(sc) new Chart(sc,{type:'doughnut',data:{labels:<?= $js['statusLabels'] ?>,datasets:[{data:<?= $js['statusData'] ?>,backgroundColor:<?= $js['statusColors'] ?>,borderWidth:3,borderColor:'#fff',hoverBorderWidth:4}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>` ${ctx.label}: ${ctx.parsed}`}}}}});

  // 2 · Monthly Trend
  const tc = document.getElementById('trendChart');
  if(tc && <?= !empty($trend_labels)?'true':'false' ?>) {
    const tg1=tc.getContext('2d').createLinearGradient(0,0,0,260); tg1.addColorStop(0,'rgba(99,102,241,.2)'); tg1.addColorStop(1,'rgba(99,102,241,.0)');
    const tg2=tc.getContext('2d').createLinearGradient(0,0,0,260); tg2.addColorStop(0,'rgba(16,185,129,.15)'); tg2.addColorStop(1,'rgba(16,185,129,.0)');
    new Chart(tc,{type:'line',data:{labels:<?= $js['trendLabels'] ?>,datasets:[
      {label:'Applications',data:<?= $js['trendData'] ?>,borderColor:INDIGO,backgroundColor:tg1,fill:true,tension:.4,borderWidth:2.5,pointBackgroundColor:INDIGO,pointRadius:4,pointHoverRadius:6},
      {label:'Selections',data:<?= $js['selData'] ?>,borderColor:EMERALD,backgroundColor:tg2,fill:true,tension:.4,borderWidth:2.5,pointBackgroundColor:EMERALD,pointRadius:4,pointHoverRadius:6}
    ]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false}}}}});
  }

  // 3 · CGPA vs Placement Correlation
  const cgc = document.getElementById('cgpaCorrChart');
  if(cgc && <?= !empty($cgpa_corr_rows)?'true':'false' ?>) new Chart(cgc,{type:'bar',data:{labels:<?= $js['cgpaCorrLabels'] ?>,datasets:[
    {label:'Total',data:<?= $js['cgpaCorrTotal'] ?>,backgroundColor:'rgba(99,102,241,.18)',borderColor:INDIGO,borderWidth:2,borderRadius:6},
    {label:'Placed',data:<?= $js['cgpaCorrPlaced'] ?>,backgroundColor:'rgba(99,102,241,.82)',borderColor:INDIGO,borderWidth:1,borderRadius:6}
  ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:11}}}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false},ticks:{font:{size:10}}}}}});

  // 4 · Specialization Placement Rate (horizontal %)
  const spc = document.getElementById('specChart');
  if(spc && <?= !empty($spec_rows)?'true':'false' ?>) {
    const sg=spc.getContext('2d').createLinearGradient(300,0,0,0); sg.addColorStop(0,'rgba(139,92,246,.9)'); sg.addColorStop(1,'rgba(167,139,250,.6)');
    new Chart(spc,{type:'bar',data:{labels:<?= $js['specLabels'] ?>,datasets:[{label:'Placement %',data:<?= $js['specRates'] ?>,backgroundColor:sg,borderRadius:6,borderWidth:0}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.parsed.x}% placed`}}},scales:{x:{beginAtZero:true,max:100,grid:{color:'#f1f5f9'},ticks:{callback:v=>v+'%'}},y:{grid:{display:false},ticks:{font:{size:10}}}}}});
  }

  // 5 · CGPA Distribution
  const cg = document.getElementById('cgpaChart');
  if(cg) new Chart(cg,{type:'bar',data:{labels:['A+(≥9)','A(8-9)','B(7-8)','C(6-7)','D(<6)'],datasets:[{label:'Students',data:<?= $js['cgpa'] ?>,backgroundColor:['rgba(16,185,129,.85)','rgba(16,185,129,.6)','rgba(99,102,241,.7)','rgba(245,158,11,.7)','rgba(239,68,68,.7)'],borderRadius:6,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false}}}}});

  // 6 · Backlog Donut
  const blc = document.getElementById('backlogChart');
  if(blc) new Chart(blc,{type:'doughnut',data:{labels:['Zero','1–2','3+'],datasets:[{data:<?= $js['backlog'] ?>,backgroundColor:[EMERALD,AMBER,ROSE],borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'62%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>` ${ctx.label}: ${ctx.parsed}`}}}}});

  // 7 · Batch Bar
  const bc = document.getElementById('batchChart');
  if(bc && <?= !empty($batch_rows)?'true':'false' ?>) new Chart(bc,{type:'bar',data:{labels:<?= $js['batchLabels'] ?>,datasets:[
    {label:'Total',data:<?= $js['batchTotal'] ?>,backgroundColor:'rgba(14,165,233,.18)',borderColor:SKY,borderWidth:2,borderRadius:5},
    {label:'Placed',data:<?= $js['batchPlaced'] ?>,backgroundColor:'rgba(14,165,233,.75)',borderColor:SKY,borderWidth:1,borderRadius:5}
  ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:10}}}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false}}}}});

  // 8 · Drive Status Donut
  const dsc = document.getElementById('driveStatusChart');
  if(dsc) new Chart(dsc,{type:'doughnut',data:{labels:<?= $js['dsLabels'] ?>,datasets:[{data:<?= $js['dsData'] ?>,backgroundColor:<?= $js['dsColors'] ?>,borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>` ${ctx.label}: ${ctx.parsed}`}}}}});

  // 9 · Companies Horizontal Bar
  const cc = document.getElementById('companiesChart');
  if(cc && <?= !empty($company_names)?'true':'false' ?>) {
    const cg2=cc.getContext('2d').createLinearGradient(400,0,0,0); cg2.addColorStop(0,'rgba(16,185,129,.9)'); cg2.addColorStop(1,'rgba(52,211,153,.7)');
    new Chart(cc,{type:'bar',data:{labels:<?= $js['compNames'] ?>,datasets:[{label:'Placed',data:<?= $js['compCounts'] ?>,backgroundColor:cg2,borderRadius:6,borderWidth:0}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},y:{grid:{display:false},ticks:{font:{size:11}}}}}});
  }

  // 10 · Top Job Roles
  const rc = document.getElementById('roleChart');
  if(rc && <?= !empty($role_labels)?'true':'false' ?>) {
    const rg1=rc.getContext('2d').createLinearGradient(400,0,0,0); rg1.addColorStop(0,'rgba(99,102,241,.85)'); rg1.addColorStop(1,'rgba(129,140,248,.5)');
    const rg2=rc.getContext('2d').createLinearGradient(400,0,0,0); rg2.addColorStop(0,'rgba(16,185,129,.85)'); rg2.addColorStop(1,'rgba(52,211,153,.5)');
    new Chart(rc,{type:'bar',data:{labels:<?= $js['roleLabels'] ?>,datasets:[
      {label:'Applications',data:<?= $js['roleApps'] ?>,backgroundColor:rg1,borderRadius:6,borderWidth:0},
      {label:'Selected',data:<?= $js['rolePlaced'] ?>,backgroundColor:rg2,borderRadius:6,borderWidth:0}
    ]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:11}}}},scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},y:{grid:{display:false},ticks:{font:{size:10}}}}}});
  }

  // 11 · Drive Conversion Rate
  const dc = document.getElementById('drvConvChart');
  if(dc && <?= !empty($drv_conv_labels)?'true':'false' ?>) {
    const drColors = <?= json_encode($drv_conv_rates) ?>.map(r => r>=50?'rgba(16,185,129,.8)':r>=25?'rgba(245,158,11,.8)':'rgba(239,68,68,.75)');
    new Chart(dc,{type:'bar',data:{labels:<?= $js['drvConvLabels'] ?>,datasets:[{label:'Conversion %',data:<?= $js['drvConvRates'] ?>,backgroundColor:drColors,borderRadius:6,borderWidth:0}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.parsed.x}% conversion`}}},scales:{x:{beginAtZero:true,max:100,grid:{color:'#f1f5f9'},ticks:{callback:v=>v+'%'}},y:{grid:{display:false},ticks:{font:{size:10}}}}}});
  }

  // Animated counters
  document.querySelectorAll('.counter[data-target]').forEach(el => {
    const target = +el.dataset.target;
    if(!target) return;
    let n=0; const step=Math.ceil(target/40);
    const t=setInterval(()=>{n=Math.min(n+step,target);el.textContent=n;if(n>=target)clearInterval(t);},22);
  });

}); // DOMContentLoaded

// CSV Export — structured with all KPI data
function exportCSV() {
  const gen = <?= json_encode(date('d M Y, h:i A')) ?>;
  const inst = 'Placement Management System';

  // ── KPI Section
  const kpi = [
    ['Metric','Value'],
    ['Total Students', <?= $total_students ?>],
    ['Students Placed', <?= $placed ?>],
    ['Unplaced Students', <?= $unplaced ?>],
    ['Placement Rate (%)', <?= $placement_pct ?>],
    ['Conversion Rate (App→Selected %)', <?= $conversion_rate ?>],
    ['Total Drives', <?= $total_drives ?>],
    ['Active Drives', <?= $active_drives ?>],
    ['Total Applications', <?= $total_apps ?>],
    ['Shortlisted', <?= $shortlisted ?>],
    ['Rejected', <?= $rejected ?>],
    ['Ready for Placement', <?= $ready_students ?>],
  ];

  // ── Application Status
  const sL = <?= $js['statusLabels'] ?>, sD = <?= $js['statusData'] ?>;
  const appStatus = [['Application Status','Count']];
  sL.forEach((l, i) => appStatus.push([l, sD[i]]));

  // ── Monthly Trend
  const tL = <?= $js['trendLabels'] ?>, tD = <?= $js['trendData'] ?>, sdD = <?= $js['selData'] ?>;
  const trend = [['Month','Applications','Selections']];
  tL.forEach((m, i) => trend.push([m, tD[i], sdD[i]]));

  // ── Top Companies
  const cN = <?= $js['compNames'] ?>, cC = <?= $js['compCounts'] ?>;
  const companies = [['Rank','Company','Students Placed']];
  cN.forEach((c, i) => companies.push([i + 1, c, cC[i]]));

  // ── Specialization
  const spL = <?= $js['specLabels'] ?>, spR = <?= $js['specRates'] ?>, spP = <?= $js['specPlaced'] ?>, spT = <?= $js['specTotal'] ?>;
  const spec = [['Specialization','Total Students','Placed','Placement Rate (%)']];
  spL.forEach((l, i) => spec.push([l, spT[i], spP[i], spR[i]]));

  // ── Drive Conversion
  const dL = <?= $js['drvConvLabels'] ?>, dR = <?= $js['drvConvRates'] ?>, dA = <?= $js['drvConvApps'] ?>;
  const drives = [['Company','Applications','Conversion Rate (%)']];
  dL.forEach((l, i) => drives.push([l, dA[i], dR[i]]));

  // ── Build rows
  const sep = () => [['']];
  const hdr = (t) => [['--- ' + t + ' ---']];
  const rows = [
    [[inst + ' — Placement Analytics Report']],
    [['Generated:', gen]],
    ...sep(),
    ...hdr('KEY PERFORMANCE INDICATORS'), ...kpi,
    ...sep(),
    ...hdr('APPLICATION STATUS BREAKDOWN'), ...appStatus,
    ...sep(),
    ...hdr('MONTHLY TREND (LAST 6 MONTHS)'), ...trend,
    ...sep(),
    ...hdr('TOP COMPANIES BY PLACEMENTS'), ...companies,
    ...sep(),
    ...hdr('SPECIALIZATION-WISE PLACEMENT'), ...spec,
    ...sep(),
    ...hdr('DRIVE CONVERSION RATES'), ...drives,
  ];

  const esc = (v) => { const s = String(v ?? ''); return s.includes(',') || s.includes('"') || s.includes('\n') ? `"${s.replace(/"/g,'""')}"` : s; };
  const csv = rows.map(r => r.map(esc).join(',')).join('\r\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = Object.assign(document.createElement('a'), { href: url, download: `placement_report_<?= date('Y-m-d') ?>.csv` });
  document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}

// Print / Save as PDF
function printReport() {
  window.print();
}
</script>

<?php require '_nav_end.php'; ?>
