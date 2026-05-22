<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);
$rec_id = (int)($_GET['id'] ?? 0);
if (!$rec_id) { header("Location: my_recommendations.php"); exit(); }

$success = $error = "";

// Load existing recommendation (must belong to this alumni)
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM alumni_recommendation WHERE recommendation_id=$rec_id AND alumni_id=$alumni_id"));
if (!$r) { header("Location: my_recommendations.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $job_title   = trim($_POST['job_title']);
    $company     = trim($_POST['company_name']);
    $job_link    = trim($_POST['job_link']);
    $description = trim($_POST['description']);

    if (empty($job_title) || empty($company)) {
        $error = "Job title and company name are required.";
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE alumni_recommendation SET job_title=?, company_name=?, job_link=?, description=?
             WHERE recommendation_id=? AND alumni_id=?");
        mysqli_stmt_bind_param($stmt, "ssssii", $job_title, $company, $job_link, $description, $rec_id, $alumni_id);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Recommendation updated successfully!";
            $r['job_title']   = $job_title;
            $r['company_name']= $company;
            $r['job_link']    = $job_link;
            $r['description'] = $description;
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

$pageTitle  = 'Edit Recommendation';
$activePage = 'my_recommendations';
require '_layout.php';
?>

<style>
.form-container {
  max-width: 760px;
  margin: 0 auto;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 18px;
}
.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
}
.form-group .hint {
  font-size: 11.5px;
  color: var(--muted);
  margin-top: 4px;
}
textarea {
  min-height: 120px;
  resize: vertical;
}
.btn-row {
  display: flex;
  gap: 12px;
  align-items: center;
}
</style>

<div class="form-container">
  <a class="back-link" href="my_recommendations.php">
    <span class="material-icons-round" style="font-size:16px">arrow_back</span>
    Back to My Recommendations
  </a>

  <div class="page-header">
    <h1>✏️ Edit Recommendation</h1>
    <p>Update the job recommendation details below. Students will see the updated information immediately.</p>
  </div>

  <?php if ($success): ?>
    <div class="success">
      <span class="material-icons-round">check_circle</span>
      <div>
        <?= htmlspecialchars($success) ?>
        <a href="my_recommendations.php" style="color:var(--success);font-weight:700;text-decoration:underline;margin-left:4px">View all recommendations →</a>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="error">
      <span class="material-icons-round">error</span>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title"><span class="material-icons-round">edit</span>Edit Job Recommendation Details</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      
      <div class="form-group">
        <label>Job Title *</label>
        <input type="text" name="job_title" value="<?= htmlspecialchars($r['job_title']) ?>" required>
      </div>

      <div class="form-group">
        <label>Company Name *</label>
        <input type="text" name="company_name" value="<?= htmlspecialchars($r['company_name']) ?>" required>
      </div>

      <div class="form-group">
        <label>Job Application Link</label>
        <input type="url" name="job_link" placeholder="https://careers.company.com/job-link" value="<?= htmlspecialchars($r['job_link'] ?? '') ?>">
        <p class="hint">Optional — paste the direct apply URL.</p>
      </div>

      <div class="form-group">
        <label>Job Description / Tips</label>
        <textarea name="description" placeholder="Describe the role, required skills, interview tips, salary range, etc."><?= htmlspecialchars($r['description'] ?? '') ?></textarea>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn">
          <span class="material-icons-round">save</span>
          Save Changes
        </button>
        <a class="btn-sec" href="my_recommendations.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require '_layout_end.php'; ?>
