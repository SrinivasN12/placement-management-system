<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/email_helpers.php';  // ← Email notifications
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);
$success = $error = "";

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
            "INSERT INTO alumni_recommendation (alumni_id, job_title, company_name, job_link, description)
             VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $alumni_id, $job_title, $company, $job_link, $description);
        if (mysqli_stmt_execute($stmt)) {
            $recommendation_id = mysqli_insert_id($conn);

            // ── Send email notifications to all active students ──
            // If email sending fails, the recommendation is still saved.
            try {
                send_alumni_recommendation_emails($recommendation_id, $conn);
            } catch (\Throwable $e) {
                error_log('[recommend_job.php] Email notification error: ' . $e->getMessage());
            }

            $success = "Job recommendation posted! Students can now view it.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

$pageTitle  = 'Recommend a Job';
$activePage = 'recommend';
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
</style>

<div class="form-container">
  <a class="back-link" href="my_recommendations.php">
    <span class="material-icons-round" style="font-size:16px">arrow_back</span>
    Back to My Recommendations
  </a>

  <div class="page-header">
    <h1>➕ Recommend a Job</h1>
    <p>Share a job opportunity with current MBA students. Your recommendation helps them get placed!</p>
  </div>

  <?php if ($success): ?>
    <div class="success">
      <span class="material-icons-round">check_circle</span>
      <div>
        <?= htmlspecialchars($success) ?>
        <a href="my_recommendations.php" style="color:var(--success);font-weight:700;text-decoration:underline;margin-left:4px">View my list →</a>
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
    <div class="card-title"><span class="material-icons-round">business_center</span>Job Recommendation Details</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      
      <div class="form-group">
        <label>Job Title *</label>
        <input type="text" name="job_title" placeholder="e.g. Software Developer / Associate Consultant" value="<?= htmlspecialchars($_POST['job_title']??'') ?>" required>
      </div>

      <div class="form-group">
        <label>Company Name *</label>
        <input type="text" name="company_name" placeholder="e.g. Wipro" value="<?= htmlspecialchars($_POST['company_name']??'') ?>" required>
      </div>

      <div class="form-group">
        <label>Job Application Link</label>
        <input type="url" name="job_link" placeholder="https://careers.company.com/job-link" value="<?= htmlspecialchars($_POST['job_link']??'') ?>">
        <p class="hint">Optional — paste the direct apply URL so students can apply easily.</p>
      </div>

      <div class="form-group">
        <label>Job Description / Referral Tips</label>
        <textarea name="description" placeholder="Describe the role, required skills, interview tips, salary range, or specify if referral is available."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
      </div>

      <button type="submit" class="btn">
        <span class="material-icons-round">send</span>
        Post Recommendation
      </button>
    </form>
  </div>
</div>

<?php require '_layout_end.php'; ?>
