<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 3) { header("Location: ../index.php"); exit(); }

$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);
$my_recs   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM alumni_recommendation WHERE alumni_id=$alumni_id"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Dashboard — PMS</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }
        .navbar {
            background:#4a148c; color:#fff; padding:14px 32px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .navbar h1 { font-size:18px; }
        .navbar a  { color:#fff; text-decoration:none; font-size:13px; }
        .content   { padding:32px; max-width:1100px; margin:0 auto; }
        .welcome   { font-size:22px; font-weight:700; color:#4a148c; margin-bottom:6px; }
        .cards     { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-top:24px; }
        .card {
            background:#fff; border-radius:12px; padding:24px 28px;
            box-shadow:0 2px 8px rgba(0,0,0,.08); text-align:center;
        }
        .card h3 { font-size:28px; color:#4a148c; margin-bottom:6px; }
        .card p  { font-size:13px; color:#888; }
        .module-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
            gap:12px; margin-top:28px;
        }
        .module-btn {
            display:block; background:#4a148c; color:#fff;
            padding:14px 20px; border-radius:10px; text-decoration:none;
            font-size:14px; font-weight:600; text-align:center;
            transition:background .2s, transform .15s;
        }
        .module-btn:hover { background:#6a1b9a; transform:translateY(-2px); }
        @media(max-width:600px){
            .navbar{padding:12px 16px;}
            .navbar h1{font-size:15px;}
            .content{padding:18px 14px;}
            .welcome{font-size:18px;}
            .cards{gap:12px;margin-top:16px;}
            .card{padding:18px 14px;}
            .card h3{font-size:22px;}
            .module-grid{grid-template-columns:1fr 1fr;gap:10px;margin-top:20px;}
            .module-btn{padding:12px 14px;font-size:13px;}
        }
        @media(max-width:380px){
            .module-grid{grid-template-columns:1fr;}
            .module-btn{font-size:14px;padding:13px 16px;}
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🌐 PMS — Alumni Portal</h1>
    <a href="../logout.php">Logout</a>
</div>
<div class="content">
    <div class="welcome">Welcome back, <?= htmlspecialchars($name) ?>!</div>
    <p style="color:#666;margin-top:4px;">Help current students by sharing job opportunities.</p>

    <div class="cards">
        <div class="card"><h3><?= $my_recs ?></h3><p>Jobs Recommended</p></div>
    </div>

    <div class="module-grid">
        <a class="module-btn" href="recommend_job.php">➕ Recommend a Job</a>
        <a class="module-btn" href="my_recommendations.php">📋 My Recommendations</a>
        <a class="module-btn" href="qa_board.php">💬 Student Q&amp;A Board</a>
        <a class="module-btn" href="profile.php">👤 My Profile</a>
    </div>
</div>
</body>
</html>
