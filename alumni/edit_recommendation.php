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
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Recommendation — PMS</title>
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
.btn-sec{background:#fff;color:#4a148c;border:2px solid #4a148c;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn-sec:hover{background:#f3e5f5}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#4a148c;text-decoration:none;font-size:13px;font-weight:600}
.hint{font-size:12px;color:#aaa;margin-top:4px}
.btn-row{display:flex;gap:12px;align-items:center;margin-top:6px}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .navbar h1{font-size:15px;}
  .content{padding:18px 14px;}
  .card{padding:20px 18px;}
  h2{font-size:18px;}
  .btn-row{flex-direction:column;}
  .btn,.btn-sec{width:100%;text-align:center;padding:14px;font-size:15px;}
}
</style></head><body>
<div class="navbar"><h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="my_recommendations.php">← Back to My Recommendations</a>
<h2>✏️ Edit Recommendation</h2>
<p class="sub">Update the job details below. Students will see the updated information immediately.</p>
<?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?> <a href="my_recommendations.php" style="color:#2e7d32;font-weight:600">View all →</a></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
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
    <button type="submit" class="btn">💾 Save Changes</button>
    <a class="btn-sec" href="my_recommendations.php">Cancel</a>
  </div>
</form>
</div>
</div></body></html>
