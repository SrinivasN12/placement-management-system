<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);
$success = $error = "";
$ats_results = [];

// Handle resume save
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_resume'])) {
    validate_csrf();
    $resume_text = trim($_POST['resume_text']);
    if (strlen($resume_text) < 50) {
        $error = "Resume content is too short. Please paste your full resume text.";
    } else {
        // Delete old resume
        mysqli_query($conn,"DELETE FROM resume WHERE student_id=$student_id");
        $txt_esc = mysqli_real_escape_string($conn,$resume_text);
        mysqli_query($conn,"INSERT INTO resume (student_id,resume_text,uploaded_date) VALUES ($student_id,'$txt_esc',CURDATE())");
        $success = "Resume saved successfully! Check your ATS scores below.";
        // Auto calculate ATS for all active drives
        $resume_id = mysqli_insert_id($conn);
        $drives = mysqli_query($conn,"SELECT * FROM placement_drive WHERE drive_date >= CURDATE()");
        while ($d = mysqli_fetch_assoc($drives)) {
            $score = calculateATS($resume_text, $d['job_title'].' '.$d['company_name'].' '.$d['description']);
            $did   = $d['drive_id'];
            mysqli_query($conn,"DELETE FROM ats_score WHERE student_id=$student_id AND drive_id=$did");
            mysqli_query($conn,"INSERT INTO ats_score (student_id,resume_id,drive_id,score,checked_date) VALUES ($student_id,$resume_id,$did,$score,CURDATE())");
        }
    }
}

function calculateATS($resume, $jd) {
    // Rule-based keyword matching ATS scoring
    $tech_keywords = ['php','mysql','html','css','javascript','python','java','react','node','sql','git','api','rest','aws','linux','c++','c#','angular','vue','mongodb','bootstrap','jquery','laravel','django','spring','database','software','development','programming','coding','agile','scrum'];
    $soft_keywords = ['communication','leadership','teamwork','problem','solving','analytical','management','presentation','collaboration','creative','innovation','project'];
    $resume_lower  = strtolower($resume);
    $jd_words      = array_unique(preg_split('/\W+/', strtolower($jd)));
    $matched = 0; $total = 0;
    foreach (array_merge($tech_keywords,$soft_keywords) as $kw) {
        if (in_array($kw,$jd_words)) {
            $total++;
            if (strpos($resume_lower,$kw)!==false) $matched++;
        }
    }
    if ($total === 0) return rand(40,65); // no keywords to match, give average
    $base = round(($matched/$total)*100);
    return max(10,min(100,$base));
}

$resume_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM resume WHERE student_id=$student_id ORDER BY uploaded_date DESC LIMIT 1"));
if ($resume_row) {
    $rid = $resume_row['resume_id'];
    $ats_q = mysqli_query($conn,
        "SELECT a.*, d.job_title, d.company_name, d.drive_date
         FROM ats_score a JOIN placement_drive d ON a.drive_id=d.drive_id
         WHERE a.student_id=$student_id AND d.drive_status != 'Closed'
         ORDER BY a.score DESC");
    while ($r = mysqli_fetch_assoc($ats_q)) $ats_results[] = $r;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Resume & ATS — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:960px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.two-col{display:flex;gap:20px;flex-wrap:wrap}
.card{background:#fff;border-radius:12px;padding:24px 28px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.card h3{font-size:15px;color:#1a237e;margin-bottom:14px}
.form-group{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
textarea{width:100%;padding:11px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;outline:none;font-family:monospace;resize:vertical;min-height:240px}
textarea:focus{border-color:#1a237e}
.btn{background:#1a237e;color:#fff;border:none;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#3949ab}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.ats-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #f5f5f5;font-size:13px}
.ats-row:last-child{border-bottom:none}
.drive-name{flex:1;color:#333}
.score-bar{flex:2}
.bar-bg{background:#f0f0f0;border-radius:6px;height:12px}
.bar-fg{height:12px;border-radius:6px;transition:width .6s}
.score-num{font-weight:700;width:40px;text-align:right}
.hint{font-size:12px;color:#aaa;margin-top:10px;line-height:1.5}
@media(max-width:640px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 14px;}
  .two-col{flex-direction:column;}
  .btn{width:100%;}
}
</style></head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>📄 Resume & ATS Score</h2>
<p class="sub">Paste your resume text below. The system will evaluate your compatibility with active placement drives.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="two-col">
  <div style="flex:2;min-width:280px">
    <div class="card">
      <h3>📝 Your Resume Text</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label>Paste resume content here</label>
          <textarea name="resume_text" placeholder="Paste your full resume text here (skills, experience, education, projects)..."><?= htmlspecialchars($resume_row['resume_text'] ?? '') ?></textarea>
        </div>
        <button type="submit" name="save_resume" class="btn">💾 Save & Check ATS Score</button>
      </form>
      <p class="hint">💡 Tip: Include technical skills, programming languages, frameworks, and project descriptions to get a higher ATS match score.</p>
    </div>
  </div>
  <div style="flex:1;min-width:260px">
    <div class="card">
      <h3>📊 ATS Match Scores</h3>
      <?php if ($ats_results): ?>
        <?php foreach ($ats_results as $a):
          $score = $a['score'];
          $color = $score>=70?'#2e7d32':($score>=50?'#e65100':'#c62828'); ?>
        <div class="ats-row">
          <div class="drive-name">
            <strong><?= htmlspecialchars($a['job_title']) ?></strong><br>
            <span style="font-size:11px;color:#888"><?= htmlspecialchars($a['company_name']) ?></span>
          </div>
          <div class="score-bar">
            <div class="bar-bg"><div class="bar-fg" style="width:<?= $score ?>%;background:<?= $color ?>"></div></div>
          </div>
          <div class="score-num" style="color:<?= $color ?>"><?= $score ?>%</div>
        </div>
        <?php endforeach; ?>
        <p class="hint">Score ≥70%: Strong match &nbsp;|&nbsp; 50–69%: Moderate &nbsp;|&nbsp; &lt;50%: Weak</p>
      <?php else: ?>
        <p style="color:#888;font-size:13px">Save your resume to see ATS scores for active drives.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
</div></body></html>
