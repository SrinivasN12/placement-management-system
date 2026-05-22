<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 3) { header("Location: ../index.php"); exit(); }

$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);
$my_recs   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM alumni_recommendation WHERE alumni_id=$alumni_id"))['c'] ?? 0;

$pageTitle  = 'Alumni Dashboard';
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
  box-shadow: 0 8px 28px rgba(74,20,140,.14);
}
.stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon .material-icons-round { font-size: 24px; }
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
  box-shadow: 0 8px 28px rgba(74,20,140,.14);
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
</style>

<div class="page-header">
  <h1>👋 Welcome back, <?= htmlspecialchars(explode(' ', $name)[0]) ?>!</h1>
  <p>Help current students by sharing job opportunities and answering questions.</p>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon si-purple"><span class="material-icons-round">list_alt</span></div>
    <div>
      <div class="stat-val" id="cntRecs">0</div>
      <div class="stat-lbl">Jobs Recommended</div>
    </div>
  </div>
</div>

<!-- ── Quick Navigation ────────────────────────────────────── -->
<div class="card">
  <div class="card-title"><span class="material-icons-round">apps</span>Quick Navigation</div>
  <div class="quick-grid">
    <a class="quick-card" href="recommend_job.php">
      <div class="quick-card-icon"><span class="material-icons-round">add_box</span></div>
      <div class="quick-card-label">Recommend a Job</div>
      <div class="quick-card-desc">Share a new job opportunity with students</div>
    </a>
    <a class="quick-card" href="my_recommendations.php">
      <div class="quick-card-icon"><span class="material-icons-round">list_alt</span></div>
      <div class="quick-card-label">My Recommendations</div>
      <div class="quick-card-desc">Track and edit the jobs you've posted</div>
    </a>
    <a class="quick-card" href="qa_board.php">
      <div class="quick-card-icon"><span class="material-icons-round">forum</span></div>
      <div class="quick-card-label">Student Q&amp;A Board</div>
      <div class="quick-card-desc">Answer questions and guide current students</div>
    </a>
    <a class="quick-card" href="profile.php">
      <div class="quick-card-icon"><span class="material-icons-round">person</span></div>
      <div class="quick-card-label">My Profile</div>
      <div class="quick-card-desc">Update your company and account settings</div>
    </a>
  </div>
</div>

<script>
// Animated counter
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
animateCount('cntRecs', <?= (int)$my_recs ?>);
</script>

<?php require '_layout_end.php'; ?>
