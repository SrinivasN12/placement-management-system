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
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recommend a Job — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#4a148c;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:720px;margin:0 auto}
h2{color:#4a148c;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.form-group{margin-bottom:18px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
input,textarea{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
input:focus,textarea:focus{border-color:#4a148c}
textarea{resize:vertical;min-height:110px}
.btn{background:#4a148c;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#6a1b9a}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#4a148c;text-decoration:none;font-size:13px;font-weight:600}
.hint{font-size:12px;color:#aaa;margin-top:4px}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 16px;}
  .card{padding:20px 18px;}
  .btn{width:100%;}
}
</style></head><body>
<div class="navbar"><h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>➕ Recommend a Job</h2>
<p class="sub">Share a job opportunity with current students. Your recommendation helps them get placed!</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?> <a href="my_recommendations.php" style="color:#2e7d32;font-weight:600">View my recommendations →</a></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <div class="form-group">
    <label>Job Title *</label>
    <input type="text" name="job_title" placeholder="e.g. Software Developer" value="<?= htmlspecialchars($_POST['job_title']??'') ?>" required>
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
    <label>Job Description / Tips</label>
    <textarea name="description" placeholder="Describe the role, required skills, interview tips, salary range, etc."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
  </div>
  <button type="submit" class="btn">Post Recommendation →</button>
</form>
</div>
</div></body></html>
