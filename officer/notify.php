<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $message = trim($_POST['message']);
    $target  = $_POST['target'];

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

$page_title  = 'Send Notifications — PMS';
$active_page = 'notify';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">Send Notifications</li>
    </ol>
  </nav>

  <!-- Page Header -->
  <div class="page-header mb-4">
    <h1><i class="bi bi-bell me-2"></i>Send Notification</h1>
    <p class="sub">Send a message to a group of students or a specific student.</p>
  </div>

  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

      <!-- Flash -->
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Form Card -->
      <div class="card pms-card">
        <div class="card-header d-flex align-items-center gap-2 rounded-top">
          <i class="bi bi-send"></i> Compose Notification
        </div>
        <div class="card-body p-4">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-people me-1 text-success"></i>Send To
              </label>
              <select class="form-select" name="target" required>
                <option value="all">📢 All Students</option>
                <option value="ready">✅ Placement-Ready Students</option>
                <option value="notready">⚠️ Not-Ready Students</option>
                <optgroup label="── Individual Student ──">
                  <?php foreach ($student_rows as $s): ?>
                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['department'] ?>)</option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-chat-left-text me-1 text-success"></i>Message
              </label>
              <textarea class="form-control" name="message" id="msg" maxlength="500" rows="5"
                        oninput="document.getElementById('cc').textContent=this.value.length+'/500'"
                        placeholder="Type your notification message here…" required></textarea>
              <div class="text-end mt-1">
                <small class="text-muted"><span id="cc">0</span>/500</small>
              </div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-pms btn-lg">
                <i class="bi bi-send me-2"></i>Send Notification
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
