<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

// Mark all as read
mysqli_query($conn, "UPDATE notification SET is_read=1 WHERE student_id=$student_id");

$notifs = mysqli_query($conn, "SELECT * FROM notification WHERE student_id=$student_id ORDER BY date DESC, notification_id DESC");

$pageTitle  = 'Notifications';
$activePage = 'notifications';
require '_layout.php';
?>

<style>
.notif-list { display: flex; flex-direction: column; gap: 12px; }

.notif-card {
  background: var(--surface);
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: var(--card-shadow);
  display: flex;
  align-items: flex-start;
  gap: 14px;
  border-left: 4px solid var(--accent-soft);
  transition: transform var(--transition), box-shadow var(--transition);
}
.notif-card:hover {
  transform: translateX(3px);
  box-shadow: 0 6px 22px rgba(26,35,126,.12);
  border-left-color: var(--primary-light);
}
.notif-icon-wrap {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: var(--accent-soft);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  color: var(--primary-light);
}
.notif-icon-wrap .material-icons-round { font-size: 20px; }
.notif-body { flex: 1; }
.notif-msg {
  font-size: 13.5px;
  color: var(--text);
  line-height: 1.55;
  margin-bottom: 6px;
}
.notif-date {
  font-size: 11px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 4px;
}
.notif-date .material-icons-round { font-size: 13px; }

.empty-state {
  text-align: center;
  padding: 70px 20px;
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
}
.empty-state .material-icons-round {
  font-size: 56px;
  color: var(--border);
  margin-bottom: 12px;
  display: block;
}
.empty-state p { font-size: 14px; color: var(--muted); }
</style>

<div class="page-header">
  <h1>🔔 Notifications</h1>
  <p>All your placement-related alerts and updates.</p>
</div>

<div class="notif-list">
<?php
$found = false;
while ($n = mysqli_fetch_assoc($notifs)):
  $found = true;
  $dateStr = date('d M Y', strtotime($n['date']));
?>
<div class="notif-card">
  <div class="notif-icon-wrap">
    <span class="material-icons-round">notifications</span>
  </div>
  <div class="notif-body">
    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
    <div class="notif-date">
      <span class="material-icons-round">schedule</span>
      <?= $dateStr ?>
    </div>
  </div>
</div>
<?php endwhile; ?>

<?php if (!$found): ?>
<div class="empty-state">
  <span class="material-icons-round">notifications_off</span>
  <p>No notifications yet. Check back after applying to drives.</p>
</div>
<?php endif; ?>
</div>

<?php require '_layout_end.php'; ?>
