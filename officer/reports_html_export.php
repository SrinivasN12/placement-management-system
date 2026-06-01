<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// ── Same data queries as reports.php ──────────────────────────
$total_students  = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM student"))['c'];
$placed          = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'];
$total_drives    = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM placement_drive"))['c'];
$total_apps      = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM application"))['c'];
$active_drives   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM placement_drive WHERE drive_status='Open'"))['c'];
$shortlisted     = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM application WHERE application_status='Shortlisted'"))['c'];
$rejected        = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM application WHERE application_status='Rejected'"))['c'];
$ready_students  = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM student WHERE preplacement_status='Ready'"))['c'];
$placement_pct   = $total_students > 0 ? round(($placed/$total_students)*100,1) : 0;
$unplaced        = $total_students - $placed;
$conversion_rate = $total_apps > 0 ? round(($placed/$total_apps)*100,1) : 0;
$avg_apps        = $total_drives > 0 ? round($total_apps/$total_drives,1) : 0;

$status_res = mysqli_query($conn,"SELECT application_status, COUNT(*) as cnt FROM application GROUP BY application_status");
$status_labels=[]; $status_data=[]; $status_colors=[];
$color_map=['Applied'=>'#6366f1','Shortlisted'=>'#f59e0b','Selected'=>'#10b981','Rejected'=>'#ef4444'];
while($r=mysqli_fetch_assoc($status_res)){$status_labels[]=$r['application_status'];$status_data[]=(int)$r['cnt'];$status_colors[]=$color_map[$r['application_status']]??'#8b5cf6';}

$spec_rows=[];
$spec_res=mysqli_query($conn,"SELECT s.specialization,COUNT(DISTINCT s.student_id) AS total,SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed FROM student s LEFT JOIN application a ON s.student_id=a.student_id GROUP BY s.specialization HAVING COUNT(DISTINCT s.student_id)>0 ORDER BY SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END)/COUNT(DISTINCT s.student_id) DESC LIMIT 10");
while($r=mysqli_fetch_assoc($spec_res)) $spec_rows[]=$r;

$cgpa_corr_rows=[];
$cgpa_corr_res=mysqli_query($conn,"SELECT CASE WHEN s.cgpa>=9 THEN 'A+ (≥9.0)' WHEN s.cgpa>=8 THEN 'A  (8-9)' WHEN s.cgpa>=7 THEN 'B  (7-8)' WHEN s.cgpa>=6 THEN 'C  (6-7)' ELSE 'D  (<6)' END AS band,COUNT(DISTINCT s.student_id) AS total,SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed,MIN(s.cgpa) AS min_cgpa FROM student s LEFT JOIN application a ON s.student_id=a.student_id GROUP BY band ORDER BY min_cgpa DESC");
while($r=mysqli_fetch_assoc($cgpa_corr_res)) $cgpa_corr_rows[]=$r;

$company_names=[]; $company_counts=[];
$cr=mysqli_query($conn,"SELECT d.company_name,COUNT(DISTINCT a.student_id) AS placements FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id WHERE a.application_status='Selected' GROUP BY d.company_name ORDER BY placements DESC LIMIT 8");
while($c=mysqli_fetch_assoc($cr)){$company_names[]=$c['company_name'];$company_counts[]=(int)$c['placements'];}

$trend_res=mysqli_query($conn,"SELECT DATE_FORMAT(applied_at,'%b %Y') AS ml,DATE_FORMAT(applied_at,'%Y-%m') AS mk,COUNT(*) AS cnt FROM application WHERE applied_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mk,ml ORDER BY mk");
$trend_labels=[]; $trend_data=[];
while($r=mysqli_fetch_assoc($trend_res)){$trend_labels[]=$r['ml'];$trend_data[]=(int)$r['cnt'];}
$sel_res=mysqli_query($conn,"SELECT DATE_FORMAT(applied_at,'%b %Y') AS ml,DATE_FORMAT(applied_at,'%Y-%m') AS mk,COUNT(*) AS cnt FROM application WHERE application_status='Selected' AND applied_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mk,ml ORDER BY mk");
$sel_map=[];
while($r=mysqli_fetch_assoc($sel_res)) $sel_map[$r['ml']]=(int)$r['cnt'];
$sel_trend_data=array_map(fn($l)=>$sel_map[$l]??0,$trend_labels);

$role_labels=[]; $role_apps=[]; $role_placed=[];
$role_res=mysqli_query($conn,"SELECT d.job_title,COUNT(a.application_id) AS apps,SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id GROUP BY d.job_title ORDER BY apps DESC LIMIT 8");
while($r=mysqli_fetch_assoc($role_res)){$lbl=mb_strlen($r['job_title'])>22?mb_substr($r['job_title'],0,22).'…':$r['job_title'];$role_labels[]=$lbl;$role_apps[]=(int)$r['apps'];$role_placed[]=(int)$r['placed'];}

$drive_status_labels=[]; $drive_status_data=[]; $drive_status_colors=[];
$ds_colors=['Open'=>'#10b981','Closed'=>'#94a3b8','Active'=>'#6366f1'];
$dsr=mysqli_query($conn,"SELECT drive_status,COUNT(*) as cnt FROM placement_drive GROUP BY drive_status");
while($r=mysqli_fetch_assoc($dsr)){$drive_status_labels[]=$r['drive_status'];$drive_status_data[]=(int)$r['cnt'];$drive_status_colors[]=$ds_colors[$r['drive_status']]??'#94a3b8';}

$cgpa_res=mysqli_query($conn,"SELECT SUM(CASE WHEN cgpa>=9 THEN 1 ELSE 0 END) AS Ap,SUM(CASE WHEN cgpa>=8 AND cgpa<9 THEN 1 ELSE 0 END) AS A,SUM(CASE WHEN cgpa>=7 AND cgpa<8 THEN 1 ELSE 0 END) AS B,SUM(CASE WHEN cgpa>=6 AND cgpa<7 THEN 1 ELSE 0 END) AS C,SUM(CASE WHEN cgpa<6 THEN 1 ELSE 0 END) AS D FROM student");
$cgpa=mysqli_fetch_assoc($cgpa_res);

$blr=mysqli_query($conn,"SELECT SUM(CASE WHEN backlogs=0 THEN 1 ELSE 0 END) AS zero,SUM(CASE WHEN backlogs BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS one_two,SUM(CASE WHEN backlogs>=3 THEN 1 ELSE 0 END) AS three_plus FROM student");
$bl=mysqli_fetch_assoc($blr);

$batch_rows=[];
$br=@mysqli_query($conn,"SELECT s.batch,COUNT(DISTINCT s.student_id) AS total,SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed FROM student s LEFT JOIN application a ON s.student_id=a.student_id WHERE s.batch IS NOT NULL AND s.batch<>'' GROUP BY s.batch ORDER BY CAST(SUBSTRING_INDEX(s.batch,'-',1) AS UNSIGNED)");
while($br && $r=mysqli_fetch_assoc($br)) $batch_rows[]=$r;

$drv_conv_labels=[]; $drv_conv_rates=[]; $drv_conv_apps=[];
$drv_conv_res=mysqli_query($conn,"SELECT d.company_name,COUNT(a.application_id) AS apps,SUM(CASE WHEN a.application_status='Selected' THEN 1 ELSE 0 END) AS placed FROM placement_drive d JOIN application a ON d.drive_id=a.drive_id GROUP BY d.drive_id ORDER BY apps DESC LIMIT 8");
while($r=mysqli_fetch_assoc($drv_conv_res)){$co=mb_strlen($r['company_name'])>14?mb_substr($r['company_name'],0,14).'…':$r['company_name'];$drv_conv_labels[]=$co;$drv_conv_apps[]=(int)$r['apps'];$rate=$r['apps']>0?round(($r['placed']/$r['apps'])*100,1):0;$drv_conv_rates[]=$rate;}

// JS data
$js=[
  'statusLabels'  =>json_encode($status_labels),
  'statusData'    =>json_encode($status_data),
  'statusColors'  =>json_encode($status_colors),
  'specLabels'    =>json_encode(array_column($spec_rows,'specialization')),
  'specRates'     =>json_encode(array_map(fn($r)=>$r['total']>0?round(($r['placed']/$r['total'])*100,1):0,$spec_rows)),
  'cgpaCorrLabels'=>json_encode(array_column($cgpa_corr_rows,'band')),
  'cgpaCorrTotal' =>json_encode(array_map('intval',array_column($cgpa_corr_rows,'total'))),
  'cgpaCorrPlaced'=>json_encode(array_map('intval',array_column($cgpa_corr_rows,'placed'))),
  'compNames'     =>json_encode($company_names),
  'compCounts'    =>json_encode($company_counts),
  'trendLabels'   =>json_encode($trend_labels),
  'trendData'     =>json_encode($trend_data),
  'selData'       =>json_encode($sel_trend_data),
  'roleLabels'    =>json_encode($role_labels),
  'roleApps'      =>json_encode($role_apps),
  'rolePlaced'    =>json_encode($role_placed),
  'drvConvLabels' =>json_encode($drv_conv_labels),
  'drvConvRates'  =>json_encode($drv_conv_rates),
  'drvConvApps'   =>json_encode($drv_conv_apps),
  'batchLabels'   =>json_encode(array_column($batch_rows,'batch')),
  'batchTotal'    =>json_encode(array_map('intval',array_column($batch_rows,'total'))),
  'batchPlaced'   =>json_encode(array_map('intval',array_column($batch_rows,'placed'))),
  'dsLabels'      =>json_encode($drive_status_labels),
  'dsData'        =>json_encode($drive_status_data),
  'dsColors'      =>json_encode($drive_status_colors),
  'cgpa'          =>json_encode([(int)($cgpa['Ap']??0),(int)($cgpa['A']??0),(int)($cgpa['B']??0),(int)($cgpa['C']??0),(int)($cgpa['D']??0)]),
  'backlog'       =>json_encode([(int)($bl['zero']??0),(int)($bl['one_two']??0),(int)($bl['three_plus']??0)]),
];

$gen = date('d M Y, h:i A');
$fname = 'placement_report_' . date('Y-m-d') . '.html';

// Send as download
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Placement Analytics Report — <?= date('d M Y') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
  body { font-family:'Inter',system-ui,sans-serif; background:#f0f2f5; color:#1e293b; }
  .report-header { background:linear-gradient(135deg,#1b5e20,#2e7d32); color:#fff; padding:36px 40px 28px; border-radius:0 0 24px 24px; margin-bottom:32px; }
  .report-header h1 { font-size:1.8rem; font-weight:800; margin:0 0 6px; }
  .report-header p  { font-size:.88rem; opacity:.85; margin:0; }
  .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:32px; }
  .kpi-box { background:#fff; border-radius:16px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.07); }
  .kpi-label { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:6px; }
  .kpi-value { font-size:2rem; font-weight:800; line-height:1; }
  .kpi-sub   { font-size:.72rem; color:#94a3b8; margin-top:6px; }
  .section-title { font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:#64748b; padding:10px 0 8px; border-bottom:2px solid #e9ecef; margin:0 0 20px; }
  .chart-card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,.07); padding:24px; margin-bottom:24px; }
  .chart-card h6 { font-weight:700; font-size:.85rem; color:#334155; margin-bottom:16px; }
  .chart-wrap { position:relative; }
  .badge-stat { display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 14px; font-size:.8rem; font-weight:600; color:#475569; }
  table { font-size:.82rem; }
  thead th { background:#f8fafc; font-weight:700; color:#475569; border-bottom:2px solid #e2e8f0; }
  .print-btn { position:fixed; bottom:24px; right:24px; z-index:999; }
  @media print { .print-btn { display:none; } body { background:#fff; } }
  @media(max-width:576px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } }
</style>
</head>
<body>

<!-- Header -->
<div class="report-header">
  <div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1><i class="bi bi-graph-up-arrow me-2"></i>Placement Analytics Report</h1>
        <p>Generated on <?= $gen ?> &nbsp;·&nbsp; Placement Management System</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <span class="badge bg-light text-dark px-3 py-2 rounded-pill fw-semibold"><?= $placement_pct ?>% Placed</span>
        <span class="badge bg-success bg-opacity-75 px-3 py-2 rounded-pill fw-semibold"><?= $placed ?> / <?= $total_students ?> Students</span>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid px-4">

  <!-- KPI Grid -->
  <div class="section-title">Key Performance Indicators</div>
  <div class="kpi-grid mb-4">
    <?php $kpis=[
      ['Total Students',$total_students,'#10b981',$unplaced.' unplaced'],
      ['Placed',$placed,'#6366f1','Across all drives'],
      ['Placement Rate',$placement_pct.'%','#0ea5e9','Of enrolled'],
      ['Total Drives',$total_drives,'#8b5cf6',$active_drives.' open'],
      ['Applications',$total_apps,'#f59e0b',$avg_apps.' avg/drive'],
      ['Conversion Rate',$conversion_rate.'%','#ef4444','App → Selected'],
      ['Shortlisted',$shortlisted,'#6366f1','Awaiting decision'],
      ['Rejected',$rejected,'#ef4444','From applications'],
      ['Ready Students',$ready_students,'#10b981','Pre-placement ready'],
    ]; foreach($kpis as [$lbl,$val,$col,$sub]): ?>
    <div class="kpi-box">
      <div class="kpi-label"><?= $lbl ?></div>
      <div class="kpi-value" style="color:<?= $col ?>"><?= $val ?></div>
      <div class="kpi-sub"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-2">
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-pie-chart-fill me-1" style="color:#6366f1"></i> Application Status</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="statusChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-graph-up me-1" style="color:#6366f1"></i> Monthly Trend — Applications vs Selections</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="trendChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4 mb-2">
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-bar-chart-fill me-1" style="color:#6366f1"></i> CGPA vs Placement Correlation</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="cgpaChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-bar-chart-steps me-1" style="color:#8b5cf6"></i> Specialization Placement Rate</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="specChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 3 -->
  <div class="row g-4 mb-2">
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-building me-1" style="color:#10b981"></i> Top Companies by Placements</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="companiesChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-briefcase-fill me-1" style="color:#6366f1"></i> Top Job Roles</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="roleChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 4 -->
  <div class="row g-4 mb-2">
    <div class="col-md-4">
      <div class="chart-card">
        <h6><i class="bi bi-mortarboard me-1" style="color:#6366f1"></i> CGPA Distribution</h6>
        <div class="chart-wrap" style="height:220px"><canvas id="cgpaDistChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-card">
        <h6><i class="bi bi-exclamation-circle me-1" style="color:#f59e0b"></i> Backlogs</h6>
        <div class="chart-wrap" style="height:220px"><canvas id="backlogChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-card">
        <h6><i class="bi bi-circle-fill me-1" style="color:#10b981"></i> Drive Status</h6>
        <div class="chart-wrap" style="height:220px"><canvas id="driveStatusChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 5 -->
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-percent me-1" style="color:#10b981"></i> Drive Conversion Rates</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="drvConvChart"></canvas></div>
      </div>
    </div>
    <?php if(!empty($batch_rows)): ?>
    <div class="col-md-6">
      <div class="chart-card">
        <h6><i class="bi bi-people-fill me-1" style="color:#6366f1"></i> Batch-wise Placement</h6>
        <div class="chart-wrap" style="height:260px"><canvas id="batchChart"></canvas></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Specialization Table -->
  <?php if(!empty($spec_rows)): ?>
  <div class="chart-card mb-4">
    <h6><i class="bi bi-table me-1" style="color:#6366f1"></i> Specialization-wise Placement Summary</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Specialization</th><th>Total</th><th>Placed</th><th>Unplaced</th><th>Rate</th></tr></thead>
        <tbody>
        <?php foreach($spec_rows as $i=>$sr):
          $rate = $sr['total']>0?round(($sr['placed']/$sr['total'])*100,1):0;
          $color = $rate>=70?'success':($rate>=40?'warning':'danger');
        ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($sr['specialization']) ?></td>
          <td><?= $sr['total'] ?></td>
          <td class="text-success fw-bold"><?= $sr['placed'] ?></td>
          <td class="text-danger"><?= $sr['total']-$sr['placed'] ?></td>
          <td><span class="badge bg-<?= $color ?> bg-opacity-15 text-<?= $color ?> fw-bold rounded-pill px-3"><?= $rate ?>%</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="text-center text-muted py-4 border-top mb-4" style="font-size:.8rem">
    Placement Management System &nbsp;·&nbsp; Report generated on <?= $gen ?> &nbsp;·&nbsp; Confidential
  </div>

</div><!-- /container -->

<!-- Floating print button -->
<div class="print-btn">
  <button class="btn btn-success shadow-lg rounded-pill px-4 fw-semibold" onclick="window.print()">
    <i class="bi bi-printer me-1"></i> Print / Save PDF
  </button>
</div>

<script>
window.addEventListener('load', function() {
  const INDIGO='#6366f1',EMERALD='#10b981',AMBER='#f59e0b',ROSE='#ef4444',VIOLET='#8b5cf6',SKY='#0ea5e9';
  Chart.defaults.font.family="'Inter',system-ui,sans-serif";
  Chart.defaults.color='#64748b';

  // Status Donut
  const sc=document.getElementById('statusChart');
  if(sc) new Chart(sc,{type:'doughnut',data:{labels:<?= $js['statusLabels'] ?>,datasets:[{data:<?= $js['statusData'] ?>,backgroundColor:<?= $js['statusColors'] ?>,borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{position:'bottom',labels:{padding:12,boxWidth:12}}}}});

  // Trend
  const tc=document.getElementById('trendChart');
  if(tc && <?= !empty($trend_labels)?'true':'false' ?>) {
    const tg=tc.getContext('2d').createLinearGradient(0,0,0,220); tg.addColorStop(0,'rgba(99,102,241,.2)'); tg.addColorStop(1,'rgba(99,102,241,0)');
    const eg=tc.getContext('2d').createLinearGradient(0,0,0,220); eg.addColorStop(0,'rgba(16,185,129,.15)'); eg.addColorStop(1,'rgba(16,185,129,0)');
    new Chart(tc,{type:'line',data:{labels:<?= $js['trendLabels'] ?>,datasets:[{label:'Applications',data:<?= $js['trendData'] ?>,borderColor:INDIGO,backgroundColor:tg,fill:true,tension:.4,borderWidth:2.5,pointRadius:4},{label:'Selections',data:<?= $js['selData'] ?>,borderColor:EMERALD,backgroundColor:eg,fill:true,tension:.4,borderWidth:2.5,pointRadius:4}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
  }

  // CGPA Correlation
  const cgc=document.getElementById('cgpaChart');
  if(cgc && <?= !empty($cgpa_corr_rows)?'true':'false' ?>) new Chart(cgc,{type:'bar',data:{labels:<?= $js['cgpaCorrLabels'] ?>,datasets:[{label:'Total',data:<?= $js['cgpaCorrTotal'] ?>,backgroundColor:'rgba(99,102,241,.18)',borderColor:INDIGO,borderWidth:2,borderRadius:6},{label:'Placed',data:<?= $js['cgpaCorrPlaced'] ?>,backgroundColor:'rgba(99,102,241,.8)',borderColor:INDIGO,borderWidth:1,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});

  // Specialization
  const spc=document.getElementById('specChart');
  if(spc && <?= !empty($spec_rows)?'true':'false' ?>) new Chart(spc,{type:'bar',data:{labels:<?= $js['specLabels'] ?>,datasets:[{label:'Placement %',data:<?= $js['specRates'] ?>,backgroundColor:'rgba(139,92,246,.8)',borderRadius:6}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}},y:{grid:{display:false}}}}});

  // Companies
  const cc=document.getElementById('companiesChart');
  if(cc && <?= !empty($company_names)?'true':'false' ?>) new Chart(cc,{type:'bar',data:{labels:<?= $js['compNames'] ?>,datasets:[{label:'Placed',data:<?= $js['compCounts'] ?>,backgroundColor:'rgba(16,185,129,.8)',borderRadius:6}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}},y:{grid:{display:false}}}}});

  // Roles
  const rc=document.getElementById('roleChart');
  if(rc && <?= !empty($role_labels)?'true':'false' ?>) new Chart(rc,{type:'bar',data:{labels:<?= $js['roleLabels'] ?>,datasets:[{label:'Applications',data:<?= $js['roleApps'] ?>,backgroundColor:'rgba(99,102,241,.7)',borderRadius:6},{label:'Selected',data:<?= $js['rolePlaced'] ?>,backgroundColor:'rgba(16,185,129,.7)',borderRadius:6}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{beginAtZero:true},y:{grid:{display:false}}}}});

  // CGPA Distribution
  const cg=document.getElementById('cgpaDistChart');
  if(cg) new Chart(cg,{type:'bar',data:{labels:['A+(≥9)','A(8-9)','B(7-8)','C(6-7)','D(<6)'],datasets:[{data:<?= $js['cgpa'] ?>,backgroundColor:[EMERALD,'rgba(16,185,129,.6)',INDIGO,AMBER,ROSE],borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});

  // Backlogs
  const blc=document.getElementById('backlogChart');
  if(blc) new Chart(blc,{type:'doughnut',data:{labels:['Zero','1–2','3+'],datasets:[{data:<?= $js['backlog'] ?>,backgroundColor:[EMERALD,AMBER,ROSE],borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom'}}}});

  // Drive Status
  const dsc=document.getElementById('driveStatusChart');
  if(dsc) new Chart(dsc,{type:'doughnut',data:{labels:<?= $js['dsLabels'] ?>,datasets:[{data:<?= $js['dsData'] ?>,backgroundColor:<?= $js['dsColors'] ?>,borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom'}}}});

  // Drive Conversion
  const dc=document.getElementById('drvConvChart');
  if(dc && <?= !empty($drv_conv_labels)?'true':'false' ?>) {
    const drColors=<?= json_encode($drv_conv_rates) ?>.map(r=>r>=50?'rgba(16,185,129,.8)':r>=25?'rgba(245,158,11,.8)':'rgba(239,68,68,.75)');
    new Chart(dc,{type:'bar',data:{labels:<?= $js['drvConvLabels'] ?>,datasets:[{label:'Conversion %',data:<?= $js['drvConvRates'] ?>,backgroundColor:drColors,borderRadius:6}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.parsed.x}%`}}},scales:{x:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}},y:{grid:{display:false}}}}});
  }

  // Batch
  const bc=document.getElementById('batchChart');
  if(bc && <?= !empty($batch_rows)?'true':'false' ?>) new Chart(bc,{type:'bar',data:{labels:<?= $js['batchLabels'] ?>,datasets:[{label:'Total',data:<?= $js['batchTotal'] ?>,backgroundColor:'rgba(14,165,233,.2)',borderColor:SKY,borderWidth:2,borderRadius:5},{label:'Placed',data:<?= $js['batchPlaced'] ?>,backgroundColor:'rgba(14,165,233,.8)',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
});
</script>
</body>
</html>
