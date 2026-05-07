<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

$officer_id = (int) ($_SESSION['officer_id'] ?? 0);

// Stats
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student"))['c'] ?? 0;
$active_drives = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_date >= CURDATE()"))['c'] ?? 0;
$total_apps = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application"))['c'] ?? 0;
$placed_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPO Dashboard — PMS</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
        }

        .navbar {
            background: #1b5e20;
            color: #fff;
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-size: 18px;
        }

        .navbar a {
            color: #fff;
            text-decoration: none;
            font-size: 13px;
        }

        .content {
            padding: 32px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome {
            font-size: 22px;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 6px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
            text-align: center;
        }

        .card h3 {
            font-size: 28px;
            color: #1b5e20;
            margin-bottom: 6px;
        }

        .card p {
            font-size: 13px;
            color: #888;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 28px;
        }

        .module-btn {
            display: block;
            background: #1b5e20;
            color: #fff;
            padding: 14px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            transition: background .2s, transform .15s;
        }

        .module-btn:hover {
            background: #2e7d32;
            transform: translateY(-2px);
        }

        @media(max-width:600px) {
            .navbar {
                padding: 12px 16px;
            }

            .content {
                padding: 20px 16px;
            }

            .module-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="navbar">
        <h1>🏢 PMS — Placement Officer Portal</h1>
        <a href="../logout.php">Logout</a>
    </div>
    <div class="content">
        <div class="welcome">Welcome, <?= htmlspecialchars($name) ?>!</div>
        <p style="color:#666;margin-top:4px;">Manage placement drives, students, and reports.</p>

        <div class="cards">
            <div class="card">
                <h3><?= $total_students ?></h3>
                <p>Total Students</p>
            </div>
            <div class="card">
                <h3><?= $active_drives ?></h3>
                <p>Active Drives</p>
            </div>
            <div class="card">
                <h3><?= $total_apps ?></h3>
                <p>Applications Received</p>
            </div>
            <div class="card">
                <h3><?= $placed_students ?></h3>
                <p>Students Placed</p>
            </div>
        </div>

        <div class="module-grid">
            <a class="module-btn" href="profile.php">⚙️ My Profile</a>

            <a class="module-btn" href="post_drive.php">➕ Post New Drive</a>
            <a class="module-btn" href="manage_drives.php">📋 Manage Drives</a>
            <a class="module-btn" href="students.php">👥 View Students</a>
            <a class="module-btn" href="applications.php">📝 View Applications</a>
            <!-- <a class="module-btn" href="selection.php">✅ Update Selection Status</a> -->
            <a class="module-btn" href="reports.php">📊 Analytics &amp; Reports</a>
            <a class="module-btn" href="notify.php">🔔 Send Notifications</a>
            <a class="module-btn" href="preplacement.php">📋 Pre-Placement Evaluation</a>
        </div>
    </div>
</body>

</html>