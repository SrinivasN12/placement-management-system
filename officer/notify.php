<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $message = trim($_POST['message']);
    $target  = $_POST['target'];  // 'all', 'ready', 'notready', or student_id

    if (empty($message)) { $error = "Message cannot be empty."; }
    else {
        $today = date('Y-m-d');
        if ($target === 'all') {
            $ids = mysqli_query($conn,"SELECT student_id FROM student");
        } elseif ($target === 'ready') {
            $ids = mysqli_query($conn,"SELECT student_id FROM student WHERE preplacement_status='Ready'");
        } elseif ($target === 'notready') {
            $ids = mysqli_query($conn,"SELECT student_id FROM student WHERE preplacement_status='Not Ready'");
        } else {
            $ids = mysqli_query($conn,"SELECT student_id FROM student WHERE student_id=".((int)$target));
        }
        $count = 0;
        $msg_esc = mysqli_real_escape_string($conn, $message);
        while ($s = mysqli_fetch_assoc($ids)) {
            mysqli_query($conn,"INSERT INTO notification (student_id,message,is_read,date) VALUES ({$s['student_id']},'$msg_esc',0,'$today')");
            $count++;
        }
        $success = "Notification sent to $count student(s).";
    }
}

$students = mysqli_query($conn,"SELECT student_id,name,department FROM student ORDER BY name");
$student_rows = [];
while ($s = mysqli_fetch_assoc($students)) $student_rows[] = $s;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Send Notifications — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:720px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.form-group{margin-bottom:18px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
select,textarea{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
select:focus,textarea:focus{border-color:#1b5e20}
textarea{resize:vertical;min-height:100px}
.char-count{font-size:11px;color:#aaa;text-align:right;margin-top:3px}
.btn{background:#1b5e20;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#2e7d32}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .card{padding:20px 18px;}
  .btn{width:100%;}
}
</style></head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>🔔 Send Notification</h2>
<p class="sub">Send a message to a group of students or a specific student.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <div class="form-group">
    <label>Send To</label>
    <select name="target" required>
      <option value="all">All Students</option>
      <option value="ready">Placement-Ready Students</option>
      <option value="notready">Not-Ready Students</option>
      <optgroup label="── Individual Student ──">
        <?php foreach ($student_rows as $s): ?>
        <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['department'] ?>)</option>
        <?php endforeach; ?>
      </optgroup>
    </select>
  </div>
  <div class="form-group">
    <label>Message</label>
    <textarea name="message" id="msg" maxlength="500" oninput="document.getElementById('cc').textContent=this.value.length+'/500'" placeholder="Type your notification message here..." required></textarea>
    <div class="char-count"><span id="cc">0</span>/500</div>
  </div>
  <button type="submit" class="btn">📨 Send Notification</button>
</form>
</div>
</div></body></html>
