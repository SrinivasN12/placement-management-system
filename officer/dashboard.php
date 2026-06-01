<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

$officer_id = (int)($_SESSION['officer_id'] ?? 0);

// ── Stats ─────────────────────────────────────────────────────
$total_students    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student"))['c'];
$active_drives     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_status='Open'"))['c'];
$total_apps        = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application"))['c'];
$placed_students   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'];
$shortlisted       = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application WHERE application_status='Shortlisted'"))['c'];
$pending_approvals = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM user WHERE role_id=1 AND status='Pending'"))['c'];
$ready_students    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student WHERE preplacement_status='Ready'"))['c'];
$total_drives      = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive"))['c'];
$placement_pct     = $total_students > 0 ? round(($placed_students / $total_students) * 100, 1) : 0;
$conversion_rate   = $total_apps > 0 ? round(($placed_students / $total_apps) * 100, 1) : 0;

// ── Recent Activity (last 5 applications) ─────────────────────
$recent_apps = mysqli_query($conn,
    "SELECT a.application_status, s.name AS student_name, d.company_name, d.job_title,
            DATE_FORMAT(a.applied_at, '%d %b, %h:%i %p') AS time_label
     FROM application a
     JOIN student s ON a.student_id = s.student_id
     JOIN placement_drive d ON a.drive_id = d.drive_id
     ORDER BY a.applied_at DESC LIMIT 6");

// ── Upcoming Drives (next 5) ──────────────────────────────────
$upcoming_drives = mysqli_query($conn,
    "SELECT job_title, company_name, drive_date, drive_status,
            DATEDIFF(drive_date, CURDATE()) AS days_left,
            (SELECT COUNT(*) FROM application WHERE drive_id = placement_drive.drive_id) AS apps
     FROM placement_drive
     WHERE drive_date >= CURDATE()
     ORDER BY drive_date ASC LIMIT 5");

// ── Top company this month ────────────────────────────────────
$top_company = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT d.company_name, COUNT(DISTINCT a.student_id) AS cnt
     FROM application a JOIN placement_drive d ON a.drive_id=d.drive_id
     WHERE a.application_status='Selected'
       AND MONTH(a.applied_at)=MONTH(CURDATE()) AND YEAR(a.applied_at)=YEAR(CURDATE())
     GROUP BY d.company_name ORDER BY cnt DESC LIMIT 1"));

// ── Sparkline data: daily applications last 7 days ───────────
$spark_res = mysqli_query($conn,
    "SELECT DATE_FORMAT(applied_at,'%a') AS day_label,
            DATE(applied_at) AS day_key, COUNT(*) AS cnt
     FROM application
     WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY day_key, day_label ORDER BY day_key ASC");
$spark_labels = []; $spark_data = [];
while ($r = mysqli_fetch_assoc($spark_res)) {
    $spark_labels[] = $r['day_label'];
    $spark_data[]   = (int)$r['cnt'];
}

$page_title  = 'TPO Dashboard — PMS';
$active_page = 'dashboard';
$extra_head  = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root {
  --indigo: #6366f1; --indigo-light: #eef2ff;
  --emerald: #10b981; --emerald-light: #ecfdf5;
  --amber: #f59e0b; --amber-light: #fffbeb;
  --rose: #ef4444; --rose-light: #fff1f2;
  --violet: #8b5cf6; --violet-light: #f5f3ff;
  --sky: #0ea5e9; --sky-light: #f0f9ff;
  --sub: #64748b; --border: #e2e8f0;
  --shadow: 0 1px 3px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.06);
  --shadow-lg: 0 4px 6px rgba(0,0,0,.07), 0 10px 40px rgba(0,0,0,.1);
}

/* KPI Cards */
.dash-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:24px; }
.kpi-c { background:#fff; border-radius:16px; padding:18px 16px; box-shadow:var(--shadow); position:relative; overflow:hidden; transition:transform .2s,box-shadow .2s; }
.kpi-c:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.kpi-c::before { content:""; position:absolute; inset:0; opacity:.03; border-radius:16px; }
.kpi-c.in::before  { background:var(--indigo); }
.kpi-c.em::before  { background:var(--emerald); }
.kpi-c.am::before  { background:var(--amber); }
.kpi-c.ro::before  { background:var(--rose); }
.kpi-c.vi::before  { background:var(--violet); }
.kpi-c.sk::before  { background:var(--sky); }
.kpi-icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; margin-bottom:12px; }
.kpi-c.in .kpi-icon { background:var(--indigo-light); color:var(--indigo); }
.kpi-c.em .kpi-icon { background:var(--emerald-light); color:var(--emerald); }
.kpi-c.am .kpi-icon { background:var(--amber-light); color:var(--amber); }
.kpi-c.ro .kpi-icon { background:var(--rose-light); color:var(--rose); }
.kpi-c.vi .kpi-icon { background:var(--violet-light); color:var(--violet); }
.kpi-c.sk .kpi-icon { background:var(--sky-light); color:var(--sky); }
.kpi-val { font-size:2rem; font-weight:800; line-height:1; letter-spacing:-1px; }
.kpi-c.in .kpi-val { color:var(--indigo); }
.kpi-c.em .kpi-val { color:var(--emerald); }
.kpi-c.am .kpi-val { color:var(--amber); }
.kpi-c.ro .kpi-val { color:var(--rose); }
.kpi-c.vi .kpi-val { color:var(--violet); }
.kpi-c.sk .kpi-val { color:var(--sky); }
.kpi-ttl { font-size:.73rem; font-weight:700; color:var(--sub); margin-top:4px; text-transform:uppercase; letter-spacing:.5px; }
.kpi-foot { font-size:.69rem; color:var(--sub); margin-top:8px; padding-top:8px; border-top:1px solid var(--border); }

/* Section Title */
.sec-head { display:flex; align-items:center; gap:10px; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--sub); margin-bottom:14px; }
.sec-head::after { content:""; flex:1; height:1px; background:var(--border); }

/* Dash Grid */
.dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px; }
.dash-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:22px; }
.dash-card { background:#fff; border-radius:16px; padding:20px; box-shadow:var(--shadow); }

/* Quick Actions */
.qa-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(148px,1fr)); gap:10px; }
.qa-btn { display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px 12px; background:#fff; border:1.5px solid var(--border); border-radius:14px; text-decoration:none; transition:all .2s; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.qa-btn:hover { border-color:var(--indigo); background:var(--indigo-light); transform:translateY(-2px); box-shadow:0 4px 14px rgba(99,102,241,.15); }
.qa-btn .qi { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.qa-btn span { font-size:.76rem; font-weight:700; color:#374151; text-align:center; line-height:1.2; }
.qa-btn:hover span { color:var(--indigo); }
.qa-btn.alert-btn { border-color:#fca5a5; background:#fff1f2; }
.qa-btn.alert-btn:hover { border-color:var(--rose); background:var(--rose-light); }
.qa-btn.alert-btn span { color:var(--rose); }

/* Activity Feed */
.activity-item { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
.activity-item:last-child { border:none; }
.act-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
.act-text { font-size:12.5px; color:#374151; line-height:1.4; flex:1; }
.act-text strong { font-weight:700; }
.act-time { font-size:11px; color:var(--sub); white-space:nowrap; }

/* Upcoming Drives */
.drive-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); }
.drive-item:last-child { border:none; }
.drive-days { flex-shrink:0; width:44px; height:44px; border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; background:var(--indigo-light); }
.drive-days .dd { font-size:16px; font-weight:800; color:var(--indigo); line-height:1; }
.drive-days .dl { font-size:9px; color:var(--sub); font-weight:600; }
.drive-info { flex:1; }
.drive-info .dj { font-size:12.5px; font-weight:700; color:#0f172a; }
.drive-info .dc { font-size:11px; color:var(--sub); }
.drive-apps { font-size:11px; font-weight:700; color:var(--sub); padding:2px 8px; background:#f8fafc; border-radius:8px; white-space:nowrap; }

/* Gauge */
.mini-gauge { position:relative; width:110px; height:110px; margin:0 auto 10px; }
.mini-gauge svg { transform:rotate(-90deg); }
.mini-gauge .track { fill:none; stroke:#f1f5f9; stroke-width:12; }
.mini-gauge .fill  { fill:none; stroke-width:12; stroke-linecap:round; transition:stroke-dashoffset 1.2s ease; }
.mini-gauge-lbl { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; }
.mini-gauge-lbl .val  { font-size:18px; font-weight:800; }
.mini-gauge-lbl .unit { font-size:10px; color:var(--sub); }

/* Insight Strip */
.insight-strip { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:22px; }
.ins-box { padding:14px 16px; border-radius:12px; display:flex; align-items:center; gap:12px; }
.ins-box.blue  { background:#eff6ff; border-left:4px solid #3b82f6; }
.ins-box.green { background:var(--emerald-light); border-left:4px solid var(--emerald); }
.ins-box.amber { background:var(--amber-light); border-left:4px solid var(--amber); }
.ins-icon { font-size:1.5rem; }
.ins-text strong { display:block; font-size:12px; font-weight:700; color:#0f172a; }
.ins-text span { font-size:11px; color:var(--sub); }

/* Badge pill */
.bp { display:inline-flex; align-items:center; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
.bp-green  { background:var(--emerald-light); color:var(--emerald); }
.bp-amber  { background:var(--amber-light); color:var(--amber); }
.bp-indigo { background:var(--indigo-light); color:var(--indigo); }
.bp-slate  { background:#f1f5f9; color:#475569; }

@media(max-width:900px){ .dash-grid,.dash-grid-3,.insight-strip { grid-template-columns:1fr; } }
@media(max-width:600px){ .dash-kpi { grid-template-columns:1fr 1fr; } }
</style>';
require_once '_nav.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--pms-green);margin:0">
      <i class="bi bi-speedometer2 me-2"></i>Welcome back, <?= htmlspecialchars($name) ?>!
    </h1>
    <p class="sub mb-0">
      <?= date('l, d F Y') ?> &nbsp;·&nbsp;
      <?= $active_drives ?> open drive<?= $active_drives != 1 ? 's' : '' ?> · 
      <?= $pending_approvals > 0 ? "$pending_approvals pending approval" . ($pending_approvals > 1 ? 's' : '') : 'No pending approvals' ?>
    </p>
  </div>
  <?php if ($pending_approvals > 0): ?>
  <a href="approve_students.php" class="btn btn-danger d-flex align-items-center gap-2">
    <i class="bi bi-person-check"></i> <?= $pending_approvals ?> Pending Approval<?= $pending_approvals > 1 ? 's' : '' ?>
  </a>
  <?php endif; ?>
</div>

<!-- ── KPI Strip ─────────────────────────────────────────────── -->
<div class="dash-kpi">

  <div class="kpi-c em">
    <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="kpi-val counter" data-target="<?= $total_students ?>"><?= $total_students ?></div>
    <div class="kpi-ttl">Total Students</div>
    <div class="kpi-foot"><?= $total_students - $placed_students ?> yet to be placed</div>
  </div>

  <div class="kpi-c in">
    <div class="kpi-icon"><i class="bi bi-patch-check-fill"></i></div>
    <div class="kpi-val counter" data-target="<?= $placed_students ?>"><?= $placed_students ?></div>
    <div class="kpi-ttl">Students Placed</div>
    <div class="kpi-foot"><?= $placement_pct ?>% placement rate</div>
  </div>

  <div class="kpi-c vi">
    <div class="kpi-icon"><i class="bi bi-briefcase-fill"></i></div>
    <div class="kpi-val counter" data-target="<?= $total_drives ?>"><?= $total_drives ?></div>
    <div class="kpi-ttl">Total Drives</div>
    <div class="kpi-foot"><?= $active_drives ?> currently open</div>
  </div>

  <div class="kpi-c am">
    <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
    <div class="kpi-val counter" data-target="<?= $total_apps ?>"><?= $total_apps ?></div>
    <div class="kpi-ttl">Applications</div>
    <div class="kpi-foot"><?= $shortlisted ?> shortlisted</div>
  </div>

  <div class="kpi-c sk">
    <div class="kpi-icon"><i class="bi bi-percent"></i></div>
    <div class="kpi-val"><?= $conversion_rate ?>%</div>
    <div class="kpi-ttl">Conversion Rate</div>
    <div class="kpi-foot">App → Selected</div>
  </div>

  <div class="kpi-c <?= $pending_approvals > 0 ? 'ro' : 'em' ?>">
    <div class="kpi-icon"><i class="bi bi-person-exclamation"></i></div>
    <div class="kpi-val counter" data-target="<?= $pending_approvals ?>"><?= $pending_approvals ?></div>
    <div class="kpi-ttl">Pending Approvals</div>
    <div class="kpi-foot"><?= $pending_approvals > 0 ? 'Action required' : 'All clear ✓' ?></div>
  </div>

</div>

<!-- ── Insight Strip ─────────────────────────────────────────── -->
<div class="insight-strip">
  <div class="ins-box blue">
    <div class="ins-icon">📊</div>
    <div class="ins-text">
      <strong><?= $placement_pct ?>% Placement Rate</strong>
      <span><?= $placed_students ?> placed out of <?= $total_students ?> students</span>
    </div>
  </div>
  <div class="ins-box green">
    <div class="ins-icon">🎯</div>
    <div class="ins-text">
      <strong><?= $ready_students ?> Placement Ready</strong>
      <span><?= $ready_students - $placed_students > 0 ? ($ready_students - $placed_students) . ' ready but not yet placed' : 'All ready students placed!' ?></span>
    </div>
  </div>
  <?php if ($top_company): ?>
  <div class="ins-box amber">
    <div class="ins-icon">🏆</div>
    <div class="ins-text">
      <strong>Top Recruiter This Month</strong>
      <span><?= htmlspecialchars($top_company['company_name']) ?> — <?= $top_company['cnt'] ?> hire<?= $top_company['cnt'] != 1 ? 's' : '' ?></span>
    </div>
  </div>
  <?php else: ?>
  <div class="ins-box amber">
    <div class="ins-icon">💡</div>
    <div class="ins-text">
      <strong>Post New Drives</strong>
      <span><?= $active_drives ?> drives open. Keep the pipeline active!</span>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Main Dashboard Grid ───────────────────────────────────── -->
<div class="dash-grid">

  <!-- Activity Feed + Sparkline -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- 7-Day Sparkline Card -->
    <div class="dash-card">
      <div class="sec-head"><i class="bi bi-activity"></i> 7-Day Application Activity</div>
      <?php if (empty($spark_labels)): ?>
        <div style="text-align:center;color:var(--sub);padding:20px;font-size:13px">No application activity in the last 7 days.</div>
      <?php else: ?>
      <div style="height:120px;position:relative">
        <canvas id="sparkChart"></canvas>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--sub);margin-top:6px">
        <span>7 days ago</span>
        <span style="font-weight:700;color:var(--indigo)"><?= array_sum($spark_data) ?> total applications</span>
        <span>Today</span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Placement Rate Gauge -->
    <div class="dash-card" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <div style="flex:0 0 auto">
        <div class="mini-gauge">
          <svg viewBox="0 0 110 110" width="110" height="110">
            <circle class="track" cx="55" cy="55" r="42"/>
            <circle class="fill" cx="55" cy="55" r="42"
              stroke="<?= $placement_pct >= 70 ? '#10b981' : ($placement_pct >= 40 ? '#f59e0b' : '#ef4444') ?>"
              stroke-dasharray="<?= 2*3.14159*42 ?>"
              stroke-dashoffset="<?= 2*3.14159*42*(1-$placement_pct/100) ?>"/>
          </svg>
          <div class="mini-gauge-lbl">
            <div class="val" style="color:<?= $placement_pct >= 70 ? 'var(--emerald)' : ($placement_pct >= 40 ? 'var(--amber)' : 'var(--rose)') ?>"><?= $placement_pct ?>%</div>
            <div class="unit">placed</div>
          </div>
        </div>
      </div>
      <div style="flex:1">
        <div style="font-weight:800;font-size:15px;color:#0f172a;margin-bottom:8px">Overall Placement Rate</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <div style="display:flex;justify-content:space-between;font-size:12px">
            <span style="color:var(--sub)">Placed</span>
            <span style="font-weight:700;color:var(--emerald)"><?= $placed_students ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px">
            <span style="color:var(--sub)">Shortlisted</span>
            <span style="font-weight:700;color:var(--amber)"><?= $shortlisted ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px">
            <span style="color:var(--sub)">Unplaced</span>
            <span style="font-weight:700;color:var(--rose)"><?= $total_students - $placed_students ?></span>
          </div>
        </div>
        <a href="reports.php" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;font-size:12px;font-weight:700;color:var(--indigo);text-decoration:none">
          <i class="bi bi-graph-up-arrow"></i> Full Analytics →
        </a>
      </div>
    </div>

  </div>

  <!-- Upcoming Drives + Recent Activity -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Upcoming Drives -->
    <div class="dash-card">
      <div class="sec-head"><i class="bi bi-calendar-event"></i> Upcoming Drives</div>
      <?php
      $drive_count = 0;
      while ($udr = mysqli_fetch_assoc($upcoming_drives)):
        $drive_count++;
        $dl = (int)$udr['days_left'];
        $urgency = $dl <= 3 ? 'var(--rose)' : ($dl <= 7 ? 'var(--amber)' : 'var(--indigo)');
        $urgency_bg = $dl <= 3 ? 'var(--rose-light)' : ($dl <= 7 ? 'var(--amber-light)' : 'var(--indigo-light)');
      ?>
      <div class="drive-item">
        <div class="drive-days" style="background:<?= $urgency_bg ?>">
          <div class="dd" style="color:<?= $urgency ?>"><?= $dl ?></div>
          <div class="dl" style="color:<?= $urgency ?>">days</div>
        </div>
        <div class="drive-info">
          <div class="dj"><?= htmlspecialchars($udr['job_title']) ?></div>
          <div class="dc"><?= htmlspecialchars($udr['company_name']) ?> · <?= date('d M', strtotime($udr['drive_date'])) ?></div>
        </div>
        <div class="drive-apps"><i class="bi bi-people" style="font-size:10px"></i> <?= $udr['apps'] ?></div>
      </div>
      <?php endwhile; ?>
      <?php if ($drive_count === 0): ?>
        <div style="text-align:center;color:var(--sub);padding:20px;font-size:13px">
          No upcoming drives.<br><a href="post_drive.php" style="color:var(--indigo);font-weight:700;font-size:12px">+ Post a new drive</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="dash-card">
      <div class="sec-head"><i class="bi bi-clock-history"></i> Recent Activity</div>
      <?php
      $act_count = 0;
      $status_colors = [
        'Applied'     => '#6366f1',
        'Shortlisted' => '#f59e0b',
        'Selected'    => '#10b981',
        'Rejected'    => '#ef4444',
      ];
      while ($ra = mysqli_fetch_assoc($recent_apps)):
        $act_count++;
        $sc = $status_colors[$ra['application_status']] ?? '#94a3b8';
      ?>
      <div class="activity-item">
        <div class="act-dot" style="background:<?= $sc ?>"></div>
        <div class="act-text">
          <strong><?= htmlspecialchars($ra['student_name']) ?></strong>
          <?= strtolower($ra['application_status']) === 'applied' ? 'applied to' : 'was ' . strtolower($ra['application_status']) . ' at' ?>
          <strong><?= htmlspecialchars($ra['company_name']) ?></strong>
          <span style="font-size:11px;color:var(--sub)"> — <?= htmlspecialchars($ra['job_title']) ?></span>
        </div>
        <div class="act-time"><?= $ra['time_label'] ?></div>
      </div>
      <?php endwhile; ?>
      <?php if ($act_count === 0): ?>
        <div style="text-align:center;color:var(--sub);padding:20px;font-size:13px">No recent activity.</div>
      <?php else: ?>
        <a href="applications.php" style="display:block;text-align:center;margin-top:10px;font-size:12px;font-weight:700;color:var(--indigo);text-decoration:none">View all applications →</a>
      <?php endif; ?>
    </div>

  </div>

</div>

<!-- ── Quick Actions ─────────────────────────────────────────── -->
<div class="sec-head"><i class="bi bi-grid-3x3-gap"></i> Quick Actions</div>
<div class="qa-grid mb-4">

  <a class="qa-btn" href="profile.php">
    <div class="qi" style="background:var(--indigo-light);color:var(--indigo)"><i class="bi bi-person-gear"></i></div>
    <span>My Profile</span>
  </a>

  <a class="qa-btn <?= $pending_approvals > 0 ? 'alert-btn' : '' ?>" href="approve_students.php">
    <div class="qi" style="background:<?= $pending_approvals > 0 ? 'var(--rose-light)' : 'var(--emerald-light)' ?>;color:<?= $pending_approvals > 0 ? 'var(--rose)' : 'var(--emerald)' ?>"><i class="bi bi-person-check"></i></div>
    <span>Approve<?php if ($pending_approvals > 0): ?> <span style="background:var(--rose);color:#fff;border-radius:8px;padding:0 6px;font-size:10px"><?= $pending_approvals ?></span><?php endif; ?></span>
  </a>

  <a class="qa-btn" href="post_drive.php">
    <div class="qi" style="background:var(--amber-light);color:var(--amber)"><i class="bi bi-plus-circle-fill"></i></div>
    <span>Post Drive</span>
  </a>

  <a class="qa-btn" href="manage_drives.php">
    <div class="qi" style="background:var(--violet-light);color:var(--violet)"><i class="bi bi-briefcase-fill"></i></div>
    <span>Manage Drives</span>
  </a>

  <a class="qa-btn" href="students.php">
    <div class="qi" style="background:var(--sky-light);color:var(--sky)"><i class="bi bi-people-fill"></i></div>
    <span>View Students</span>
  </a>

  <a class="qa-btn" href="applications.php">
    <div class="qi" style="background:var(--indigo-light);color:var(--indigo)"><i class="bi bi-file-earmark-text-fill"></i></div>
    <span>Applications</span>
  </a>

  <a class="qa-btn" href="reports.php">
    <div class="qi" style="background:var(--emerald-light);color:var(--emerald)"><i class="bi bi-graph-up-arrow"></i></div>
    <span>Full Analytics</span>
  </a>

  <a class="qa-btn" href="mock_test_analytics.php">
    <div class="qi" style="background:var(--amber-light);color:var(--amber)"><i class="bi bi-trophy-fill"></i></div>
    <span>Mock Tests</span>
  </a>

  <a class="qa-btn" href="notify.php">
    <div class="qi" style="background:var(--rose-light);color:var(--rose)"><i class="bi bi-bell-fill"></i></div>
    <span>Notifications</span>
  </a>

  <a class="qa-btn" href="preplacement.php">
    <div class="qi" style="background:var(--sky-light);color:var(--sky)"><i class="bi bi-clipboard2-check-fill"></i></div>
    <span>Pre-Placement</span>
  </a>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
  Chart.defaults.color = '#64748b';

  // ── Sparkline ──────────────────────────────────────────────
  const sparkCtx = document.getElementById('sparkChart');
  if (sparkCtx && <?= !empty($spark_labels) ? 'true' : 'false' ?>) {
    const grad = sparkCtx.getContext('2d').createLinearGradient(0, 0, 0, 120);
    grad.addColorStop(0, 'rgba(99,102,241,.25)');
    grad.addColorStop(1, 'rgba(99,102,241,.0)');
    new Chart(sparkCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($spark_labels) ?>,
        datasets: [{
          label: 'Applications',
          data: <?= json_encode($spark_data) ?>,
          borderColor: '#6366f1',
          backgroundColor: grad,
          fill: true, tension: 0.4, borderWidth: 2.5,
          pointBackgroundColor: '#6366f1', pointRadius: 3, pointHoverRadius: 5
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#f8fafc' }, ticks: { stepSize: 1, font: { size: 10 } } },
          x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  // ── Animated Counters ──────────────────────────────────────
  document.querySelectorAll('.counter').forEach(el => {
    const target = +el.dataset.target;
    if (target === 0) return;
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current;
      if (current >= target) clearInterval(timer);
    }, 25);
  });
});
</script>

</div>
</body>
</html>