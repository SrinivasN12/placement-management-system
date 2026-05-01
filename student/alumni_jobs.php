<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }

$recs = mysqli_query($conn,
    "SELECT r.*, a.name AS alumni_name, a.company_name AS alumni_company
     FROM alumni_recommendation r
     JOIN alumni a ON r.alumni_id=a.alumni_id
     ORDER BY r.posted_at DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Alumni Job Tips — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:920px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.grid{display:flex;flex-direction:column;gap:16px}
.rec-card{background:#fff;border-radius:12px;padding:22px 26px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:4px solid #4a148c}
.rec-title{font-size:16px;font-weight:700;color:#1a237e;margin-bottom:3px}
.rec-company{font-size:13px;color:#555;margin-bottom:10px;font-weight:600}
.rec-desc{font-size:13px;color:#555;line-height:1.6;margin-bottom:14px}
.rec-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.alumni-info{font-size:12px;color:#888}
.alumni-info strong{color:#4a148c}
.btn-link{background:#4a148c;color:#fff;padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}
.btn-link:hover{background:#6a1b9a}
.no-link{font-size:12px;color:#aaa}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:60px;color:#888;font-size:14px;background:#fff;border-radius:12px}
.chip{background:#f3e5f5;color:#4a148c;padding:3px 10px;border-radius:8px;font-size:11px;margin-right:6px}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 16px;}
  .rec-footer{flex-direction:column;align-items:flex-start;gap:10px;}
}
</style></head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>🌐 Alumni Job Recommendations</h2>
<p class="sub">Job opportunities shared by our alumni network. Click the link to apply directly.</p>
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
</div></body></html>
