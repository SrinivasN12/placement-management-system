<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$drive_id = (int)($_GET['id'] ?? 0);
if (!$drive_id) { header("Location: manage_drives.php"); exit(); }

$success = $error = "";

$d = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_id=$drive_id"));
if (!$d) { header("Location: manage_drives.php"); exit(); }

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
    $reopen      = isset($_POST['reopen_drive']);

    if (empty($job_title) || empty($company) || empty($drive_date) || empty($last_date)) {
        $error = "Please fill all required fields.";
    } elseif ($last_date > $drive_date) {
        $error = "Last Date to Apply must be on or before the Drive Date.";
    } else {
        // If reopening, reset status to Open
        $new_status = $reopen ? 'Open' : $d['drive_status'];
        // If last_date is in the future, force Open
        if ($last_date >= date('Y-m-d') && $d['drive_status'] === 'Closed' && !$reopen) {
            // keep Closed unless explicitly reopened
        }
        if ($reopen) $new_status = 'Open';

        $stmt = mysqli_prepare($conn,
            "UPDATE placement_drive SET
             job_title=?,company_name=?,description=?,
             min_cgpa=?,max_backlogs=?,
             min_tenth_pct=?,min_twelfth_pct=?,min_degree_cgpa=?,
             drive_date=?,last_date=?,drive_status=?
             WHERE drive_id=?");
        mysqli_stmt_bind_param($stmt, "sssdidddsssi",
            $job_title, $company, $desc,
            $min_cgpa, $max_bl,
            $min_tenth, $min_twelfth, $min_deg,
            $drive_date, $last_date, $new_status, $drive_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Drive updated successfully!" . ($reopen ? " Drive is now Open." : "");
            $d = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT * FROM placement_drive WHERE drive_id=$drive_id"));
        } else {
            $error = "Failed to update: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$currentStatus = getDriveStatus($d['last_date'], $d['drive_status']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Drive — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:820px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.section-title{font-size:11px;font-weight:700;color:#1b5e20;text-transform:uppercase;letter-spacing:.6px;margin:20px 0 12px;padding-bottom:5px;border-bottom:2px solid #e8f5e9}
.form-row{display:flex;gap:16px;flex-wrap:wrap}
.form-row .form-group{flex:1;min-width:160px}
.form-group{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
input,select,textarea{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
input:focus,select:focus,textarea:focus{border-color:#1b5e20}
textarea{resize:vertical;min-height:80px}
.hint{font-size:11px;color:#aaa;margin-top:3px}
.btn{background:#1b5e20;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#2e7d32}
.btn-sec{background:#fff;color:#1b5e20;border:2px solid #1b5e20;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn-sec:hover{background:#f1f8e9}
.btn-reopen{background:#e65100;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-reopen:hover{background:#bf360c}
.btn-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:6px}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.meta{background:#f1f8e9;border-radius:8px;padding:12px 16px;font-size:12px;color:#555;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap}
.meta strong{color:#1b5e20}
.badge-open{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.badge-soon{background:#fff3e0;color:#e65100;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.badge-closed{background:#f5f5f5;color:#888;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .card{padding:20px 18px;}
  .form-row{flex-direction:column;}
  .btn,.btn-sec{width:100%;text-align:center;}
}
</style>
</head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="manage_drives.php">← Back to Manage Drives</a>
<h2>✏️ Edit Placement Drive</h2>
<p class="sub">Update drive details. Changes take effect immediately for students.</p>

<div class="meta">
  <span>Drive ID: <strong>#<?= $drive_id ?></strong></span>
  <span>Posted: <strong><?= date('d M Y', strtotime($d['created_at'])) ?></strong></span>
  <span>Status:
    <span class="<?= statusBadgeClass($currentStatus) ?>"><?= $currentStatus ?></span>
  </span>
  <?php if ($currentStatus === 'Closed'): ?>
  <span style="color:#c62828;font-size:12px">🔒 This drive is closed. Use "Reopen Drive" below to re-activate.</span>
  <?php endif; ?>
</div>

<?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="section-title">Drive Information</div>
  <div class="form-row">
    <div class="form-group"><label>Job Title *</label>
      <input type="text" name="job_title" value="<?= htmlspecialchars($d['job_title']) ?>" required></div>
    <div class="form-group"><label>Company Name *</label>
      <input type="text" name="company_name" value="<?= htmlspecialchars($d['company_name']) ?>" required></div>
  </div>
  <div class="form-group"><label>Job Description</label>
    <textarea name="description"><?= htmlspecialchars($d['description'] ?? '') ?></textarea></div>

  <div class="section-title">Eligibility Criteria</div>
  <div class="form-row">
    <div class="form-group"><label>Min 10th %</label>
      <input type="number" name="min_tenth_pct" step="0.01" min="0" max="100" value="<?= $d['min_tenth_pct'] ?? 60 ?>"></div>
    <div class="form-group"><label>Min 12th %</label>
      <input type="number" name="min_twelfth_pct" step="0.01" min="0" max="100" value="<?= $d['min_twelfth_pct'] ?? 60 ?>"></div>
    <div class="form-group"><label>Min Degree CGPA</label>
      <input type="number" name="min_degree_cgpa" step="0.01" min="0" max="10" value="<?= $d['min_degree_cgpa'] ?? 6.0 ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Min MBA CGPA</label>
      <input type="number" name="min_cgpa" step="0.01" min="0" max="10" value="<?= $d['min_cgpa'] ?>"></div>
    <div class="form-group"><label>Max Active Backlogs</label>
      <input type="number" name="max_backlogs" min="0" value="<?= $d['max_backlogs'] ?>"></div>
  </div>

  <div class="section-title">Schedule</div>
  <div class="form-row">
    <div class="form-group"><label>Last Date to Apply *</label>
      <input type="date" name="last_date" value="<?= $d['last_date'] ?>" required>
      <p class="hint">Extending this past today will re-open the drive if you check Reopen below</p></div>
    <div class="form-group"><label>Drive Date *</label>
      <input type="date" name="drive_date" value="<?= $d['drive_date'] ?>" required></div>
  </div>

  <?php if ($currentStatus === 'Closed'): ?>
  <div style="background:#fff3e0;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px;color:#e65100">
    🔓 <strong>Reopen this drive?</strong> Check the box below and save to make it active again.
    <label style="display:block;margin-top:8px;font-weight:normal;cursor:pointer">
      <input type="checkbox" name="reopen_drive" style="width:auto;margin-right:6px">
      Yes, reopen this drive and allow students to apply again
    </label>
  </div>
  <?php endif; ?>

  <div class="btn-row">
    <button type="submit" class="btn">💾 Save Changes</button>
    <a class="btn-sec" href="manage_drives.php">Cancel</a>
  </div>
</form>
</div>
</div>
<script>
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
