<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

$apps = mysqli_query($conn,
    "SELECT a.*, d.job_title, d.company_name, d.drive_date, d.description
     FROM application a
     JOIN placement_drive d ON a.drive_id=d.drive_id
     WHERE a.student_id=$student_id
     ORDER BY a.applied_at DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Applications — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:900px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.app-list{display:flex;flex-direction:column;gap:14px}
.app-card{background:#fff;border-radius:12px;padding:20px 24px;box-shadow:0 2px 8px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
.app-title{font-size:15px;font-weight:700;color:#1a237e;margin-bottom:3px}
.app-company{font-size:13px;color:#555;margin-bottom:6px}
.meta{font-size:12px;color:#888}
.badge-Applied{background:#fff3e0;color:#e65100;padding:5px 14px;border-radius:12px;font-size:13px;font-weight:600}
.badge-Selected{background:#e8f5e9;color:#2e7d32;padding:5px 14px;border-radius:12px;font-size:13px;font-weight:600}
.badge-Rejected{background:#ffebee;color:#c62828;padding:5px 14px;border-radius:12px;font-size:13px;font-weight:600}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:60px;color:#888;font-size:14px;background:#fff;border-radius:12px}
.sel-date{font-size:11px;color:#2e7d32;margin-top:4px}
.right-col{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px}
.btn-withdraw{background:#fff;color:#c62828;border:1.5px solid #ef9a9a;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s}
.btn-withdraw:hover{background:#ffebee}
.status-form{display:flex;gap:6px;align-items:center;margin-top:4px}
.status-form select{padding:5px 8px;font-size:12px;border:1.5px solid #ddd;border-radius:7px;outline:none;font-weight:600}
.status-form button{padding:5px 12px;font-size:12px;background:#1a237e;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:600}
.status-form button:hover{background:#3949ab}
.self-label{font-size:11px;color:#888;margin-top:6px}
.toast{border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.toast-success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
.toast-error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 16px;}
  .app-card{flex-direction:column;align-items:flex-start;}
  .right-col{align-items:flex-start;}
}
</style></head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>📝 My Applications</h2>
<p class="sub">Track the status of all your placement applications.</p>

<?php if (isset($_GET['withdrawn'])): ?>
  <div class="toast toast-success">✅ Application withdrawn successfully. You can re-apply if the drive is still open.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="toast toast-success">✅ Application status updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
  <?php
    $msgs = [
      'notfound'      => 'Application not found.',
      'cannotwithdraw'=> 'You can only withdraw applications that are still in "Applied" status.',
      'invalid'       => 'Invalid status selection.',
    ];
    $errMsg = $msgs[$_GET['err']] ?? 'An error occurred.';
  ?>
  <div class="toast toast-error">⚠ <?= htmlspecialchars($errMsg) ?></div>
<?php endif; ?>

<div class="app-list">
<?php $found=false; while ($a = mysqli_fetch_assoc($apps)): $found=true; ?>
<div class="app-card" style="border-left:4px solid <?= $a['application_status']==='Selected'?'#2e7d32':($a['application_status']==='Rejected'?'#c62828':'#e65100') ?>">
  <div>
    <div class="app-title"><?= htmlspecialchars($a['job_title']) ?></div>
    <div class="app-company">🏢 <?= htmlspecialchars($a['company_name']) ?></div>
    <div class="meta">Drive Date: <?= date('d M Y',strtotime($a['drive_date'])) ?> &nbsp;|&nbsp; Applied: <?= date('d M Y, h:i A',strtotime($a['applied_at'])) ?></div>
  </div>
  <div class="right-col">
    <span class="badge-<?= $a['application_status'] ?>"><?= $a['application_status'] ?></span>
    <?php if ($a['application_status']==='Selected' && $a['selection_date']): ?>
    <div class="sel-date">🎉 Selected on <?= date('d M Y',strtotime($a['selection_date'])) ?></div>
    <?php endif; ?>
    <?php if ($a['application_status'] === 'Applied'): ?>
    <!-- Withdraw -->
    <form method="POST" action="withdraw_application.php"
          onsubmit="return confirm('Withdraw your application for <?= htmlspecialchars(addslashes($a['job_title'])) ?> at <?= htmlspecialchars(addslashes($a['company_name'])) ?>? You can re-apply if the drive is still open.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
      <button type="submit" class="btn-withdraw">↩ Withdraw</button>
    </form>
    <!-- Self-report outcome -->
    <div class="self-label">Report your outcome:</div>
    <form method="POST" action="update_application_status.php" class="status-form"
          onsubmit="return confirm('Mark this application as \'' + this.status.value + '\'?')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
      <select name="status">
        <option value="Selected" style="color:#2e7d32">✅ Selected</option>
        <option value="Rejected" style="color:#c62828">❌ Rejected</option>
      </select>
      <button type="submit">Update</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endwhile; ?>
<?php if (!$found): ?>
<div class="empty">You haven't applied to any drives yet.<br><br><a href="drives.php" style="color:#1a237e;font-weight:600">Browse available drives →</a></div>
<?php endif; ?>
</div>
</div></body></html>
