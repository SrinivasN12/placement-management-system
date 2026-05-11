<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 1) { header("Location: ../index.php"); exit(); }

$student_id = (int)($_SESSION['student_id'] ?? 0);

// Count stats
$apps    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application WHERE student_id=$student_id"))['c'] ?? 0;
$drives  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_status != 'Closed'"))['c'] ?? 0;
$notifs  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM notification WHERE student_id=$student_id AND is_read=0"))['c'] ?? 0;

// Pre-placement data
$pre = null;
if ($student_id) {
    $r   = mysqli_query($conn, "SELECT * FROM preplacement_evaluation WHERE student_id=$student_id");
    $pre = $r ? mysqli_fetch_assoc($r) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — PMS</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }
        .navbar {
            background:#1a237e; color:#fff; padding:14px 32px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .navbar h1 { font-size:18px; }
        .navbar a  { color:#fff; text-decoration:none; font-size:13px; }
        .content   { padding:32px; max-width:1200px; margin:0 auto; }
        .welcome   { font-size:22px; font-weight:700; color:#1a237e; margin-bottom:6px; }
        .cards     { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-top:24px; }
        .card {
            background:#fff; border-radius:12px; padding:24px 28px;
            box-shadow:0 2px 8px rgba(0,0,0,.08); text-align:center;
        }
        .card h3 { font-size:28px; color:#1a237e; margin-bottom:6px; }
        .card p  { font-size:13px; color:#888; }
        .modules { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; margin-top:28px; }
        .module-btn {
            background:#1a237e; color:#fff; padding:13px 20px;
            border-radius:10px; text-decoration:none; font-size:14px;
            font-weight:600; transition:background .2s, transform .15s;
            text-align:center; display:block;
        }
        .module-btn:hover { background:#3949ab; transform:translateY(-2px); }
        .pre-box {
            margin-top:30px; background:#fff; padding:24px 28px;
            border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08);
        }
        .pre-box h3 { color:#1a237e; margin-bottom:16px; font-size:16px; }
        .pre-row    { display:flex; gap:16px; flex-wrap:wrap; }
        .pre-card   { flex:1; min-width:140px; background:#f5f7ff; border-radius:10px; padding:14px 18px; }
        .pre-card .score { font-size:26px; font-weight:700; color:#1a237e; }
        .pre-card .grade { font-size:13px; color:#555; margin-top:2px; }
        .pre-card .label { font-size:12px; color:#888; margin-top:4px; }
        .badge-ready     { display:inline-block; background:#e8f5e9; color:#2e7d32; padding:5px 14px; border-radius:20px; font-size:13px; font-weight:600; margin-top:14px; }
        .badge-notready  { display:inline-block; background:#ffebee; color:#c62828; padding:5px 14px; border-radius:20px; font-size:13px; font-weight:600; margin-top:14px; }
        @media(max-width:600px){
            .navbar{padding:12px 16px;}
            .navbar h1{font-size:15px;}
            .content{padding:18px 14px;}
            .welcome{font-size:18px;}
            .cards{gap:12px;margin-top:16px;}
            .card{padding:18px 14px;}
            .card h3{font-size:22px;}
            .modules{grid-template-columns:1fr 1fr;gap:10px;margin-top:20px;}
            .module-btn{padding:12px 14px;font-size:13px;}
            .pre-row{flex-direction:column;}
            .pre-box{padding:18px 16px;}
        }
        @media(max-width:380px){
            .modules{grid-template-columns:1fr;}
            .module-btn{font-size:14px;padding:13px 16px;}
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🎓 PMS — Student Portal</h1>
    <a href="../logout.php">Logout</a>
</div>
<div class="content">
    <div class="welcome">Welcome, <?= htmlspecialchars($name) ?>!</div>
    <p style="color:#666;margin-top:4px;">Here's your placement activity overview.</p>

    <div class="cards">
        <div class="card"><h3><?= $apps ?></h3><p>Applications Submitted</p></div>
        <div class="card"><h3><?= $drives ?></h3><p>Active Drives</p></div>
        <div class="card"><h3><?= $notifs ?></h3><p>Unread Notifications</p></div>
        <div class="card"><h3><?= $pre ? $pre['overall_status'] : '—' ?></h3><p>Pre-Placement Status</p></div>
    </div>

    <div class="modules">
        <a class="module-btn" href="profile.php">👤 My Profile</a>
        <a class="module-btn" href="drives.php">📋 Placement Drives</a>
        <a class="module-btn" href="applications.php">📝 My Applications</a>
        <a class="module-btn" href="resume.php">📄 Resume / ATS</a>
        <a class="module-btn" href="notifications.php">🔔 Notifications</a>
        <a class="module-btn" href="alumni_jobs.php">🌐 Alumni Job Tips</a>
        <a class="module-btn" href="qa_board.php">💬 Ask an Alumni</a>
    </div>

    <?php if ($pre): ?>
    <div class="pre-box">
        <h3>📊 Pre-Placement Performance</h3>
        <div class="pre-row">
            <div class="pre-card">
                <div class="score"><?= $pre['aptitude_marks'] ?></div>
                <div class="grade">Grade: <strong><?= $pre['aptitude_grade'] ?></strong></div>
                <div class="label">Aptitude</div>
            </div>
            <div class="pre-card">
                <div class="score"><?= $pre['gd_marks'] ?></div>
                <div class="grade">Grade: <strong><?= $pre['gd_grade'] ?></strong></div>
                <div class="label">Group Discussion</div>
            </div>
            <div class="pre-card">
                <div class="score"><?= $pre['english_marks'] ?></div>
                <div class="grade">Grade: <strong><?= $pre['english_grade'] ?></strong></div>
                <div class="label">English Fluency</div>
            </div>
        </div>
        <?php if ($pre['overall_status'] === 'Ready'): ?>
            <span class="badge-ready">✅ Placement Ready</span>
        <?php else: ?>
            <span class="badge-notready">⚠ Not Ready — Score ≥50 in all areas to qualify</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
