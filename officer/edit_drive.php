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
$page_title  = 'Edit Drive — Officer Portal';
$active_page = 'drives';
require '_nav.php';
?>
<style>
/* Page specific card & criteria styles */
.section-title { font-size: 11px; font-weight: 700; color: var(--pms-green); text-transform: uppercase; letter-spacing: .6px; margin: 20px 0 12px; padding-bottom: 5px; border-bottom: 2px solid var(--pms-green-pale); }
.hint { font-size: 11px; color: #aaa; margin-top: 3px; }
.meta-box { background: var(--pms-green-pale); border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #555; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; }
.meta-box strong { color: var(--pms-green); }
</style>

<div class="container px-0" style="max-width: 860px;">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="manage_drives.php">Placement Drives</a></li>
      <li class="breadcrumb-item active" aria-current="page">Edit Drive</li>
    </ol>
  </nav>

  <div class="page-header mt-2">
    <h1>✏️ Edit Placement Drive</h1>
    <div class="sub">Update drive details. Changes take effect immediately for students.</div>
  </div>

  <div class="meta-box align-items-center">
    <span>Drive ID: <strong>#<?= $drive_id ?></strong></span>
    <span>Posted: <strong><?= date('d M Y', strtotime($d['created_at'])) ?></strong></span>
    <span class="d-flex align-items-center gap-1">Status:
      <span class="<?= statusBadgeClass($currentStatus) ?>"><?= $currentStatus ?></span>
    </span>
    <?php if ($currentStatus === 'Closed'): ?>
    <span style="color:#c62828;font-size:12px;font-weight:600">🔒 This drive is closed. Use "Reopen Drive" below to re-activate.</span>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card pms-card">
    <div class="card-body p-4">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="section-title">Drive Information</div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Job Title *</label>
            <input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($d['job_title']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Company Name *</label>
            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($d['company_name']) ?>" required>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label font-semibold text-dark">Job Description</label>
          <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($d['description'] ?? '') ?></textarea>
        </div>

        <div class="section-title">Eligibility Criteria</div>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min 10th % *</label>
            <input type="number" name="min_tenth_pct" class="form-control" step="0.01" min="0" max="100" value="<?= $d['min_tenth_pct'] ?? 60 ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min 12th % *</label>
            <input type="number" name="min_twelfth_pct" class="form-control" step="0.01" min="0" max="100" value="<?= $d['min_twelfth_pct'] ?? 60 ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min Degree CGPA *</label>
            <input type="number" name="min_degree_cgpa" class="form-control" step="0.01" min="0" max="10" value="<?= $d['min_degree_cgpa'] ?? 6.0 ?>" required>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Min MBA CGPA *</label>
            <input type="number" name="min_cgpa" class="form-control" step="0.01" min="0" max="10" value="<?= $d['min_cgpa'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Max Active Backlogs *</label>
            <input type="number" name="max_backlogs" class="form-control" min="0" value="<?= $d['max_backlogs'] ?>" required>
          </div>
        </div>

        <div class="section-title">Schedule</div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Last Date to Apply *</label>
            <input type="date" name="last_date" class="form-control" value="<?= $d['last_date'] ?>" required>
            <p class="hint">Extending this past today will re-open the drive if you check Reopen below</p>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Drive Date *</label>
            <input type="date" name="drive_date" class="form-control" value="<?= $d['drive_date'] ?>" required>
          </div>
        </div>

        <?php if ($currentStatus === 'Closed'): ?>
        <div class="alert alert-warning border-warning-subtle p-3 mb-4">
          🔓 <strong>Reopen this drive?</strong> Check the box below and save to make it active again.
          <label class="d-flex align-items-center gap-2 mt-2 font-normal cursor-pointer text-dark">
            <input type="checkbox" name="reopen_drive" class="form-check-input">
            Yes, reopen this drive and allow students to apply again
          </label>
        </div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-2 mt-4">
          <button type="submit" class="btn btn-pms px-4 py-2">💾 Save Changes</button>
          <a class="btn btn-outline-secondary px-4 py-2" href="manage_drives.php">Cancel</a>
        </div>
      </form>
    </div>
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

<?php require '_nav_end.php'; ?>
