<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
require_once '../config/email_helpers.php';  // ← Email notifications
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$officer_id = (int)($_SESSION['officer_id'] ?? 0);
$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $job_title   = trim($_POST['job_title']);
    $company     = trim($_POST['company_name']);
    $desc        = trim($_POST['description']);
    $min_cgpa    = (float)$_POST['min_cgpa'];
    $max_bl      = (int)$_POST['max_backlogs'];
    $min_tenth   = (float)$_POST['min_tenth_pct'];
    $min_twelfth = (float)$_POST['min_twelfth_pct'];
    $min_deg     = (float)$_POST['min_degree_cgpa'];
    $drive_date  = $_POST['drive_date'];
    $last_date   = $_POST['last_date'];

    if (empty($job_title) || empty($company) || empty($drive_date) || empty($last_date)) {
        $error = "Please fill all required fields.";
    } elseif ($last_date > $drive_date) {
        $error = "Last Date to Apply must be on or before the Drive Date.";
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO placement_drive
             (job_title,company_name,description,min_cgpa,max_backlogs,
              min_tenth_pct,min_twelfth_pct,min_degree_cgpa,
              drive_date,last_date,drive_status,posted_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,'Open',?)");
        mysqli_stmt_bind_param($stmt, "sssdidddssi",
            $job_title, $company, $desc,
            $min_cgpa, $max_bl,
            $min_tenth, $min_twelfth, $min_deg,
            $drive_date, $last_date, $officer_id);

        if (mysqli_stmt_execute($stmt)) {
            $drive_id = mysqli_insert_id($conn);
            // Notify eligible students
            $students = mysqli_query($conn,
                "SELECT student_id FROM student
                 WHERE cgpa >= $min_cgpa
                   AND backlogs <= $max_bl
                   AND tenth_percentage >= $min_tenth
                   AND twelfth_percentage >= $min_twelfth
                   AND degree_cgpa >= $min_deg");
            $today = date('Y-m-d');
            $msg = "New drive: $job_title at $company. Last date to apply: " . date('d M Y', strtotime($last_date)) . ". Check eligibility and apply!";
            $msg_esc = mysqli_real_escape_string($conn, $msg);
            while ($s = mysqli_fetch_assoc($students)) {
                mysqli_query($conn,
                    "INSERT INTO notification (student_id,message,is_read,date)
                     VALUES ({$s['student_id']},'$msg_esc',0,'$today')");
            }
            $success = "Drive posted successfully! Eligible students have been notified.";

            // ── Send email notifications to eligible students ──
            // This runs after the DB insert succeeds.
            // If email sending fails, the drive is still posted.
            try {
                send_placement_drive_emails($drive_id, $conn);
            } catch (\Throwable $e) {
                error_log('[post_drive.php] Email notification error: ' . $e->getMessage());
            }
        } elseif (mysqli_errno($conn) === 1062) {
            $error = "A drive for \"$job_title\" at \"$company\" already exists. Please edit the existing drive or use a different job title / company name.";
        } else {
            $error = "Failed to post drive: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$defaultDriveDate = date('Y-m-d', strtotime('+15 days'));
$defaultLastDate  = date('Y-m-d', strtotime('+10 days'));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Post Drive — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:820px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.section-title{font-size:11px;font-weight:700;color:#1b5e20;text-transform:uppercase;letter-spacing:.6px;margin:20px 0 12px;padding-bottom:5px;border-bottom:2px solid #e8f5e9}
.form-row{display:flex;gap:16px;flex-wrap:wrap}
.form-row .form-group{flex:1;min-width:160px}
.form-group{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
input,select,textarea{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
input:focus,select:focus,textarea:focus{border-color:#1b5e20}
textarea{resize:vertical;min-height:90px}
.hint{font-size:11px;color:#aaa;margin-top:3px}
.default-badge{display:inline-block;background:#e8f5e9;color:#2e7d32;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;margin-left:6px;vertical-align:middle}
.btn{background:#1b5e20;color:#fff;border:none;padding:13px 32px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#2e7d32}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.dept-tag{background:#e8f5e9;color:#1b5e20;font-weight:700;padding:10px 13px;border-radius:8px;font-size:14px;display:block;border:1.5px solid #a5d6a7}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .card{padding:20px 18px;}
  .form-row{flex-direction:column;}
  .btn{width:100%;}
}
</style>
</head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>➕ Post New Placement Drive</h2>
<p class="sub">Fill in the details below. Eligible MBA students will be notified automatically.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="section-title">Drive Information</div>
  <div class="form-row">
    <div class="form-group"><label>Job Title *</label>
      <input type="text" name="job_title" placeholder="e.g. Management Trainee" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>" required></div>
    <div class="form-group"><label>Company Name *</label>
      <input type="text" name="company_name" placeholder="e.g. Deloitte" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required></div>
  </div>
  <div class="form-group"><label>Job Description</label>
    <textarea name="description" placeholder="Roles, responsibilities, skills required..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
  <div class="form-row">
    <div class="form-group">
      <label>Eligible Department</label>
      <span class="dept-tag">🎓 MBA (All Students)</span>
      <p class="hint">This system is exclusively for MBA students.</p>
    </div>
  </div>

  <div class="section-title">Eligibility Criteria <span class="default-badge">DEFAULTS PRE-FILLED</span></div>
  <div class="form-row">
    <div class="form-group"><label>Min 10th % *</label>
      <input type="number" name="min_tenth_pct" step="0.01" min="0" max="100"
             value="<?= htmlspecialchars($_POST['min_tenth_pct'] ?? '60') ?>" required>
      <p class="hint">Default: 60%</p></div>
    <div class="form-group"><label>Min 12th % *</label>
      <input type="number" name="min_twelfth_pct" step="0.01" min="0" max="100"
             value="<?= htmlspecialchars($_POST['min_twelfth_pct'] ?? '60') ?>" required>
      <p class="hint">Default: 60%</p></div>
    <div class="form-group"><label>Min Degree CGPA *</label>
      <input type="number" name="min_degree_cgpa" step="0.01" min="0" max="10"
             value="<?= htmlspecialchars($_POST['min_degree_cgpa'] ?? '6.0') ?>" required>
      <p class="hint">Default: 6.0</p></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Min MBA CGPA</label>
      <input type="number" name="min_cgpa" step="0.01" min="0" max="10"
             value="<?= htmlspecialchars($_POST['min_cgpa'] ?? '0') ?>" required>
      <p class="hint">Set 0 to allow all MBA CGPAs</p></div>
    <div class="form-group"><label>Max Active Backlogs</label>
      <input type="number" name="max_backlogs" min="0"
             value="<?= htmlspecialchars($_POST['max_backlogs'] ?? '0') ?>" required></div>
  </div>

  <div class="section-title">Schedule</div>
  <div class="form-row">
    <div class="form-group"><label>Last Date to Apply *</label>
      <input type="date" name="last_date"
             value="<?= htmlspecialchars($_POST['last_date'] ?? $defaultLastDate) ?>"
             min="<?= date('Y-m-d') ?>" required>
      <p class="hint">Applications close on this date</p></div>
    <div class="form-group"><label>Drive Date *</label>
      <input type="date" name="drive_date"
             value="<?= htmlspecialchars($_POST['drive_date'] ?? $defaultDriveDate) ?>"
             min="<?= date('Y-m-d') ?>" required></div>
  </div>

  <button type="submit" class="btn">📢 Post Drive &amp; Notify Students →</button>
</form>
</div>
</div>
<script>
  // Ensure last_date <= drive_date
  document.querySelector('form').addEventListener('submit', function(e){
    const ld = document.querySelector('[name=last_date]').value;
    const dd = document.querySelector('[name=drive_date]').value;
    if(ld && dd && ld > dd){
      e.preventDefault();
      alert('Last Date to Apply must be on or before the Drive Date.');
    }
  });
</script>
</body></html>
