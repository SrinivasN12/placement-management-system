<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 1) { header("Location: ../index.php"); exit(); }

$student_id = (int)($_SESSION['student_id'] ?? 0);

// Count stats
$apps   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application WHERE student_id=$student_id"))['c'] ?? 0;
$drives = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_status != 'Closed'"))['c'] ?? 0;
$notifs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM notification WHERE student_id=$student_id AND is_read=0"))['c'] ?? 0;

// Pre-placement data
$pre = null;
if ($student_id) {
    $r   = mysqli_query($conn, "SELECT * FROM preplacement_evaluation WHERE student_id=$student_id");
    $pre = $r ? mysqli_fetch_assoc($r) : null;
}

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require '_layout.php';
?>

<style>
/* ── Stat cards ───────────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 22px 20px;
  box-shadow: var(--card-shadow);
  display: flex;
  align-items: center;
  gap: 16px;
  transition: transform var(--transition), box-shadow var(--transition);
  cursor: default;
}
.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 28px rgba(26,35,126,.14);
}
.stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon .material-icons-round { font-size: 24px; }
.si-blue   { background: #e8eaf6; color: #1a237e; }
.si-green  { background: #e8f5e9; color: #2e7d32; }
.si-orange { background: #fff3e0; color: #e65100; }
.si-purple { background: #f3e5f5; color: #6a1b9a; }
.stat-val {
  font-size: 28px;
  font-weight: 800;
  color: var(--text);
  line-height: 1;
  margin-bottom: 3px;
}
.stat-lbl {
  font-size: 12px;
  color: var(--muted);
  font-weight: 500;
}

/* ── Quick links ──────────────────────────────────────────── */
.quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.quick-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 20px 18px;
  box-shadow: var(--card-shadow);
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: transform var(--transition), box-shadow var(--transition);
  border-top: 3px solid var(--accent-soft);
}
.quick-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 28px rgba(26,35,126,.14);
  border-top-color: var(--primary-light);
}
.quick-card-icon {
  width: 40px; height: 40px;
  background: var(--accent-soft);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--primary);
  margin-bottom: 2px;
}
.quick-card-icon .material-icons-round { font-size: 20px; }
.quick-card-label {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text);
}
.quick-card-desc {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.45;
}

/* ── Pre-placement panel ──────────────────────────────────── */
.pre-grid {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  margin-top: 14px;
}
.pre-subject {
  flex: 1;
  min-width: 130px;
  background: var(--bg);
  border-radius: 10px;
  padding: 16px;
  text-align: center;
  border: 1px solid var(--border);
}
.pre-score {
  font-size: 30px;
  font-weight: 800;
  color: var(--primary);
  line-height: 1;
}
.pre-grade {
  display: inline-block;
  margin-top: 6px;
  font-size: 11px;
  font-weight: 700;
  background: var(--accent-soft);
  color: var(--primary-light);
  padding: 2px 10px;
  border-radius: 99px;
}
.pre-subject-name {
  font-size: 12px;
  color: var(--muted);
  margin-top: 6px;
  font-weight: 500;
}
.ready-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 16px;
  padding: 8px 18px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 700;
}
.ready-badge.ready   { background: var(--success-bg); color: var(--success); }
.ready-badge.notready{ background: var(--danger-bg);  color: var(--danger); }
</style>

<div class="page-header">
  <h1>👋 Welcome back, <?= htmlspecialchars(explode(' ', $name)[0]) ?>!</h1>
  <p>Here's your placement activity overview.</p>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon si-blue"><span class="material-icons-round">description</span></div>
    <div>
      <div class="stat-val" id="cntApps">0</div>
      <div class="stat-lbl">Applications</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon si-green"><span class="material-icons-round">business_center</span></div>
    <div>
      <div class="stat-val" id="cntDrives">0</div>
      <div class="stat-lbl">Active Drives</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon si-orange"><span class="material-icons-round">notifications</span></div>
    <div>
      <div class="stat-val" id="cntNotifs">0</div>
      <div class="stat-lbl">Unread Alerts</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon si-purple"><span class="material-icons-round">verified</span></div>
    <div>
      <div class="stat-val" style="font-size:18px;padding-top:5px"><?= $pre ? htmlspecialchars($pre['overall_status']) : '—' ?></div>
      <div class="stat-lbl">Placement Status</div>
    </div>
  </div>
</div>

<!-- ── Quick Links ────────────────────────────────────────── -->
<div class="card">
  <div class="card-title"><span class="material-icons-round">apps</span>Quick Navigation</div>
  <div class="quick-grid">
    <a class="quick-card" href="profile.php">
      <div class="quick-card-icon"><span class="material-icons-round">person</span></div>
      <div class="quick-card-label">My Profile</div>
      <div class="quick-card-desc">View and update your academic details</div>
    </a>
    <a class="quick-card" href="drives.php">
      <div class="quick-card-icon"><span class="material-icons-round">business_center</span></div>
      <div class="quick-card-label">Placement Drives</div>
      <div class="quick-card-desc">Browse active MBA opportunities</div>
    </a>
    <a class="quick-card" href="applications.php">
      <div class="quick-card-icon"><span class="material-icons-round">description</span></div>
      <div class="quick-card-label">My Applications</div>
      <div class="quick-card-desc">Track all your drive applications</div>
    </a>
    <a class="quick-card" href="resume.php">
      <div class="quick-card-icon"><span class="material-icons-round">manage_search</span></div>
      <div class="quick-card-label">Resume / ATS</div>
      <div class="quick-card-desc">Upload resume &amp; check AI ATS score</div>
    </a>
    <a class="quick-card" href="notifications.php">
      <div class="quick-card-icon"><span class="material-icons-round">notifications</span></div>
      <div class="quick-card-label">Notifications</div>
      <div class="quick-card-desc">Placement alerts &amp; updates</div>
    </a>
    <a class="quick-card" href="alumni_jobs.php">
      <div class="quick-card-icon"><span class="material-icons-round">public</span></div>
      <div class="quick-card-label">Alumni Tips</div>
      <div class="quick-card-desc">Job insights from seniors</div>
    </a>
    <a class="quick-card" href="qa_board.php">
      <div class="quick-card-icon"><span class="material-icons-round">forum</span></div>
      <div class="quick-card-label">Ask an Alumni</div>
      <div class="quick-card-desc">Q&amp;A with placement seniors</div>
    </a>
  </div>
</div>

<!-- ── Pre-Placement Panel ────────────────────────────────── -->
<?php if ($pre): ?>
<div class="card">
  <div class="card-title"><span class="material-icons-round">bar_chart</span>Pre-Placement Performance</div>
  <div class="pre-grid">
    <div class="pre-subject">
      <div class="pre-score"><?= $pre['aptitude_marks'] ?></div>
      <div class="pre-grade"><?= $pre['aptitude_grade'] ?></div>
      <div class="pre-subject-name">Aptitude</div>
    </div>
    <div class="pre-subject">
      <div class="pre-score"><?= $pre['gd_marks'] ?></div>
      <div class="pre-grade"><?= $pre['gd_grade'] ?></div>
      <div class="pre-subject-name">Group Discussion</div>
    </div>
    <div class="pre-subject">
      <div class="pre-score"><?= $pre['english_marks'] ?></div>
      <div class="pre-grade"><?= $pre['english_grade'] ?></div>
      <div class="pre-subject-name">English Fluency</div>
    </div>
  </div>
  <?php if ($pre['overall_status'] === 'Ready'): ?>
    <span class="ready-badge ready"><span class="material-icons-round" style="font-size:18px">check_circle</span>Placement Ready</span>
  <?php else: ?>
    <span class="ready-badge notready"><span class="material-icons-round" style="font-size:18px">warning</span>Not Ready — score ≥50 in all areas to qualify</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// Animated counters
function animateCount(id, target) {
  const el = document.getElementById(id);
  if (!el || target === 0) { if(el) el.textContent = 0; return; }
  let current = 0;
  const step = Math.max(1, Math.ceil(target / 30));
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current;
    if (current >= target) clearInterval(timer);
  }, 30);
}
animateCount('cntApps',   <?= (int)$apps ?>);
animateCount('cntDrives', <?= (int)$drives ?>);
animateCount('cntNotifs', <?= (int)$notifs ?>);
</script>

<?php require '_layout_end.php'; ?>
