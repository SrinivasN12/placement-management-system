<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$result = mysqli_query($conn,
    "SELECT s.student_id, s.name, s.department,
            pe.aptitude_marks, pe.aptitude_grade,
            pe.gd_marks, pe.gd_grade,
            pe.english_marks, pe.english_grade,
            pe.overall_status
     FROM student s
     LEFT JOIN preplacement_evaluation pe ON s.student_id = pe.student_id
     ORDER BY s.name ASC"
);

$success = isset($_GET['saved']) ? $_GET['saved'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Placement Evaluation — PMS</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }
        .navbar {
            background:#1b5e20; color:#fff; padding:14px 32px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .navbar h1 { font-size:18px; }
        .navbar a  { color:#fff; text-decoration:none; font-size:13px; }
        .page-wrap { max-width:1200px; margin:0 auto; padding:32px 24px; }
        h2 { color:#1b5e20; margin-bottom:6px; font-size:20px; }
        .sub { color:#666; font-size:13px; margin-bottom:24px; }
        .toast {
            background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7;
            border-radius:8px; padding:12px 18px; margin-bottom:20px; font-size:14px;
        }
        table { width:100%; border-collapse:collapse; background:#fff;
                border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        th { background:#1b5e20; color:#fff; padding:13px 14px; font-size:13px; text-align:left; }
        td { padding:11px 14px; font-size:13px; color:#333; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#f9f9f9; }
        input[type=number] {
            width:72px; padding:7px 8px; border:1.5px solid #ddd;
            border-radius:6px; font-size:13px; text-align:center;
        }
        input[type=number]:focus { border-color:#1b5e20; outline:none; }
        .btn-save {
            background:#1b5e20; color:#fff; border:none;
            padding:8px 18px; border-radius:6px; font-size:13px;
            font-weight:600; cursor:pointer;
        }
        .btn-save:hover { background:#2e7d32; }
        .badge-ready    { background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .badge-notready { background:#ffebee; color:#c62828; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .badge-pending  { background:#fff3e0; color:#e65100; padding:3px 10px; border-radius:12px; font-size:12px; }
        .back-btn { display:inline-block; margin-bottom:20px; color:#1b5e20; text-decoration:none; font-size:13px; font-weight:600; }
        @media(max-width:768px){
            .navbar{padding:12px 16px;}
            .page-wrap{padding:20px 16px;}
            table{min-width:600px;}
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🏢 PMS — Placement Officer Portal</h1>
    <a href="../logout.php">Logout</a>
</div>
<div class="page-wrap">
<div class="table-wrap" style="overflow-x:auto">
    <a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
    <h2>📋 Pre-Placement Evaluation</h2>
    <p class="sub">Enter marks out of 100 for each student. Grade and status are auto-calculated.</p>

    <?php if ($success): ?>
        <div class="toast">✅ Saved evaluation for <strong><?= htmlspecialchars($success) ?></strong> successfully.</div>
    <?php endif; ?>

    <table>
        <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Department</th>
            <th>Aptitude (/100)</th>
            <th>GD (/100)</th>
            <th>English (/100)</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php $i = 1; while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <form method="POST" action="save_preplacement.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="student_id" value="<?= $row['student_id'] ?>">
            <input type="hidden" name="student_name" value="<?= htmlspecialchars($row['name']) ?>">
            <td><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
            <td><?= htmlspecialchars($row['department']) ?></td>
            <td><input type="number" name="aptitude" min="0" max="100" value="<?= $row['aptitude_marks'] ?? '' ?>" placeholder="0" required></td>
            <td><input type="number" name="gd"       min="0" max="100" value="<?= $row['gd_marks']       ?? '' ?>" placeholder="0" required></td>
            <td><input type="number" name="english"  min="0" max="100" value="<?= $row['english_marks']  ?? '' ?>" placeholder="0" required></td>
            <td>
                <?php if ($row['overall_status'] === 'Ready'): ?>
                    <span class="badge-ready">✅ Ready</span>
                <?php elseif ($row['overall_status'] === 'Not Ready'): ?>
                    <span class="badge-notready">⚠ Not Ready</span>
                <?php else: ?>
                    <span class="badge-pending">Pending</span>
                <?php endif; ?>
            </td>
            <td><button type="submit" class="btn-save">Save</button></td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</div><!-- .table-wrap -->
</div><!-- .page-wrap -->
</body>
</html>
