<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

// Mark all as read
mysqli_query($conn,"UPDATE notification SET is_read=1 WHERE student_id=$student_id");

$notifs = mysqli_query($conn,"SELECT * FROM notification WHERE student_id=$student_id ORDER BY date DESC, notification_id DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Notifications — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:760px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.notif-list{display:flex;flex-direction:column;gap:10px}
.notif-card{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 2px 6px rgba(0,0,0,.06);display:flex;gap:14px;align-items:flex-start}
.notif-icon{font-size:20px;margin-top:1px}
.notif-body{}
.notif-msg{font-size:13px;color:#333;line-height:1.5;margin-bottom:4px}
.notif-date{font-size:11px;color:#aaa}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:60px;color:#888;font-size:14px;background:#fff;border-radius:12px}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 16px;}
}
</style></head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>🔔 Notifications</h2>
<p class="sub">All your placement-related notifications.</p>
<div class="notif-list">
<?php $found=false; while ($n = mysqli_fetch_assoc($notifs)): $found=true; ?>
<div class="notif-card">
  <div class="notif-icon">🔔</div>
  <div class="notif-body">
    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
    <div class="notif-date"><?= date('d M Y', strtotime($n['date'])) ?></div>
  </div>
</div>
<?php endwhile; ?>
<?php if (!$found): ?><div class="empty">No notifications yet.</div><?php endif; ?>
</div>
</div></body></html>
