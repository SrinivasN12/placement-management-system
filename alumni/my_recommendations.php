<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);

if (isset($_GET['delete'])) {
    $rid = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM alumni_recommendation WHERE recommendation_id=$rid AND alumni_id=$alumni_id");
    header("Location: my_recommendations.php?deleted=1"); exit();
}

$recs = mysqli_query($conn,
    "SELECT * FROM alumni_recommendation WHERE alumni_id=$alumni_id ORDER BY posted_at DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Recommendations — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#4a148c;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:920px;margin:0 auto}
h2{color:#4a148c;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:20px}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.btn-add{background:#4a148c;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}
.btn-add:hover{background:#6a1b9a}
.grid{display:flex;flex-direction:column;gap:14px}
.rec-card{background:#fff;border-radius:12px;padding:20px 24px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:4px solid #4a148c}
.rec-title{font-size:15px;font-weight:700;color:#1a237e;margin-bottom:3px}
.rec-company{font-size:13px;color:#555;font-weight:600;margin-bottom:8px}
.rec-desc{font-size:13px;color:#666;line-height:1.5;margin-bottom:12px}
.rec-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.rec-date{font-size:12px;color:#aaa}
.actions{display:flex;gap:10px;align-items:center}
.link-btn{font-size:12px;color:#4a148c;text-decoration:none;font-weight:600}
.del-btn{font-size:12px;color:#c62828;text-decoration:none;font-weight:600}
.toast{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.back{display:inline-block;margin-bottom:18px;color:#4a148c;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:60px;color:#888;font-size:14px;background:#fff;border-radius:12px}
.no-link{font-size:12px;color:#aaa}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .navbar h1{font-size:15px;}
  .content{padding:18px 14px;}
  h2{font-size:18px;}
  .top-bar{flex-direction:column;align-items:flex-start;gap:10px;}
  .btn-add{width:100%;text-align:center;padding:12px;}
  .rec-footer{flex-direction:column;align-items:flex-start;}
  .rec-card{padding:16px 18px;}
}
@media(max-width:380px){
  .rec-title{font-size:14px;}
}
</style></head><body>
<div class="navbar"><h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<?php if (isset($_GET['deleted'])): ?><div class="toast">✅ Recommendation deleted.</div><?php endif; ?>
<div class="top-bar">
  <div>
    <h2>📋 My Recommendations</h2>
    <p class="sub">Jobs you have shared with students.</p>
  </div>
  <a class="btn-add" href="recommend_job.php">➕ Add New</a>
</div>
<div class="grid">
<?php $found=false; while ($r = mysqli_fetch_assoc($recs)): $found=true; ?>
<div class="rec-card">
  <div class="rec-title"><?= htmlspecialchars($r['job_title']) ?></div>
  <div class="rec-company">🏢 <?= htmlspecialchars($r['company_name']) ?></div>
  <?php if ($r['description']): ?>
  <div class="rec-desc"><?= nl2br(htmlspecialchars(substr($r['description'],0,200))) ?><?= strlen($r['description'])>200?'…':'' ?></div>
  <?php endif; ?>
  <div class="rec-footer">
    <span class="rec-date">Posted on <?= date('d M Y, h:i A', strtotime($r['posted_at'])) ?></span>
    <div class="actions">
      <?php if ($r['job_link']): ?>
        <a class="link-btn" href="<?= htmlspecialchars($r['job_link']) ?>" target="_blank">🔗 View Link</a>
      <?php else: ?>
        <span class="no-link">No link added</span>
      <?php endif; ?>
      <a class="link-btn" href="edit_recommendation.php?id=<?= $r['recommendation_id'] ?>" style="color:#1565c0">✏️ Edit</a>
      <a class="del-btn" href="my_recommendations.php?delete=<?= $r['recommendation_id'] ?>"
         onclick="return confirm('Delete this recommendation? Students will no longer see it.')">🗑 Delete</a>
    </div>
  </div>
</div>
<?php endwhile; ?>
<?php if (!$found): ?>
<div class="empty">You haven't shared any job recommendations yet.<br><br>
  <a href="recommend_job.php" style="color:#4a148c;font-weight:600">Share your first recommendation →</a>
</div>
<?php endif; ?>
</div>
</div></body></html>
