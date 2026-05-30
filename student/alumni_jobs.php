<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }

$recs = mysqli_query($conn,
    "SELECT r.*, a.name AS alumni_name, a.company_name AS alumni_company
     FROM alumni_recommendation r
     JOIN alumni a ON r.alumni_id=a.alumni_id
     ORDER BY r.posted_at DESC");

$pageTitle  = 'Alumni Tips';
$activePage = 'alumni';
require '_layout.php';
?>
<style>
.rec-card { background:#fff; border-radius:12px; padding:22px 26px; box-shadow:var(--card-shadow); border-left:4px solid var(--primary); margin-bottom:16px; transition:transform var(--transition); }
.rec-card:hover { transform:translateY(-2px); }
.rec-title { font-size:16px; font-weight:700; color:var(--primary); margin-bottom:3px; }
.rec-company { font-size:13px; color:var(--text); margin-bottom:10px; font-weight:600; }
.rec-desc { font-size:13.5px; color:#555; line-height:1.6; margin-bottom:14px; }
.rec-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
.alumni-info { font-size:12px; color:var(--muted); }
.alumni-info strong { color:var(--primary); }
.btn-link { background:var(--primary); color:#fff; padding:8px 18px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:all var(--transition); display:inline-block; }
.btn-link:hover { background:var(--primary-light); color:#fff; }
.no-link { font-size:12px; color:#aaa; }
.empty { text-align:center; padding:60px; color:var(--muted); font-size:14px; background:#fff; border-radius:12px; box-shadow:var(--card-shadow); }
.chip { background:var(--accent-soft); color:var(--primary); padding:3px 10px; border-radius:8px; font-size:11px; margin-right:6px; font-weight:600; }
</style>

<a class="back-link" href="dashboard.php">
  <span class="material-icons-round" style="font-size:16px; vertical-align:middle;">arrow_back</span>
  Back to Dashboard
</a>

<div class="page-header">
  <h1>🌐 Alumni Job Recommendations</h1>
  <p>Job opportunities shared by our alumni network. Click the link to apply directly.</p>
</div>

<div class="grid">
<?php $found=false; while ($r = mysqli_fetch_assoc($recs)): $found=true; ?>
<div class="rec-card">
  <div class="rec-title"><?= htmlspecialchars($r['job_title']) ?></div>
  <div class="rec-company">🏢 <?= htmlspecialchars($r['company_name']) ?></div>
  <?php if ($r['description']): ?>
  <div class="rec-desc"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
  <?php endif; ?>
  <div class="rec-footer">
    <div class="alumni-info">
      Shared by <strong><?= htmlspecialchars($r['alumni_name']) ?></strong>
      <span class="chip"><?= htmlspecialchars($r['alumni_company']) ?></span>
      &nbsp;on <?= date('d M Y', strtotime($r['posted_at'])) ?>
    </div>
    <?php if ($r['job_link']): ?>
      <a class="btn-link" href="<?= htmlspecialchars($r['job_link']) ?>" target="_blank" rel="noopener">Apply Now →</a>
    <?php else: ?>
      <span class="no-link">No direct link — contact alumni</span>
    <?php endif; ?>
  </div>
</div>
<?php endwhile; ?>
<?php if (!$found): ?>
<div class="empty">No job recommendations yet.<br>Check back soon — alumni regularly share openings!</div>
<?php endif; ?>
</div>

<?php require '_layout_end.php'; ?>

