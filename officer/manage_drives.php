<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// ── Auto-close expired drives ─────────────────────────────────
autoCloseDrives($conn);

// ── Delete drive ──────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM ats_score WHERE drive_id=$did");
    mysqli_query($conn, "DELETE FROM eligibility_result WHERE drive_id=$did");
    mysqli_query($conn, "DELETE FROM application WHERE drive_id=$did");
    mysqli_query($conn, "DELETE FROM placement_drive WHERE drive_id=$did");
    header("Location: manage_drives.php?msg=deleted"); exit();
}

$msg = $_GET['msg'] ?? '';
$today = date('Y-m-d');

$drives = mysqli_query($conn,
    "SELECT d.*,
            p.name AS officer_name,
            COUNT(DISTINCT a.application_id) AS app_count
     FROM placement_drive d
     JOIN placement_officer p ON d.posted_by = p.officer_id
     LEFT JOIN application a ON d.drive_id = a.drive_id
     GROUP BY d.drive_id
     ORDER BY d.created_at DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Drives — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:1300px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.btn-add{background:#1b5e20;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}
.btn-add:hover{background:#2e7d32}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
th{background:#1b5e20;color:#fff;padding:11px 14px;font-size:12px;text-align:left}
td{padding:11px 14px;font-size:13px;color:#333;border-bottom:1px solid #f0f0f0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f9f9f9}
.badge-open{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}
.badge-soon{background:#fff3e0;color:#e65100;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}
.badge-closed{background:#f5f5f5;color:#888;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}
.action-link{color:#1b5e20;text-decoration:none;font-weight:600;font-size:12px;margin-right:8px}
.action-del{color:#c62828;text-decoration:none;font-weight:600;font-size:12px}
.toast{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:48px;color:#888;font-size:14px}
.last-date-soon{color:#e65100;font-weight:600}
.last-date-closed{color:#aaa}
.btn-analytics{background:#185FA5;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}
.btn-analytics:hover{background:#1a4f8a}
@media(max-width:768px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .top-bar{flex-direction:column;align-items:flex-start;gap:12px;}
  table{min-width:700px;}
}
</style>
</head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<div class="table-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<?php if ($msg === 'deleted'): ?><div class="toast">✅ Drive deleted successfully.</div><?php endif; ?>
<div class="top-bar">
  <div><h2>📋 Manage Placement Drives</h2><p class="sub">All MBA placement drives. Drives past their last date are auto-closed.</p></div>
  <a class="btn-add" href="post_drive.php">➕ Post New Drive</a>
</div>

<table>
  <tr>
    <th>#</th>
    <th>Job Title</th>
    <th>Company</th>
    <th>Drive Date</th>
    <th>Last Date to Apply</th>
    <th>Eligibility (10th / 12th / Deg CGPA)</th>
    <th>Apps</th>
    <th>Status</th>
    <th>Actions</th>
  </tr>
  <?php $i = 1; $found = false; while ($d = mysqli_fetch_assoc($drives)): $found = true;
    $status   = getDriveStatus($d['last_date'], $d['drive_status']);
    $bdgClass = statusBadgeClass($status);
    $ldClass  = $status === 'Closed' ? 'last-date-closed' : ($status === 'Closing Soon' ? 'last-date-soon' : '');
  ?>
  <tr>
    <td><?= $i++ ?></td>
    <td><strong><?= htmlspecialchars($d['job_title']) ?></strong></td>
    <td><?= htmlspecialchars($d['company_name']) ?></td>
    <td><?= date('d M Y', strtotime($d['drive_date'])) ?></td>
    <td class="<?= $ldClass ?>"><?= date('d M Y', strtotime($d['last_date'])) ?>
      <?= $status === 'Closing Soon' ? ' ⚠' : '' ?>
      <?= $status === 'Closed'       ? ' 🔒' : '' ?>
    </td>
    <td style="font-size:11px">
      10th: <?= $d['min_tenth_pct'] ?>% &nbsp;|&nbsp;
      12th: <?= $d['min_twelfth_pct'] ?>% &nbsp;|&nbsp;
      Deg: <?= $d['min_degree_cgpa'] ?>
    </td>
    <td><?= $d['app_count'] ?></td>
    <td><span class="<?= $bdgClass ?>"><?= $status ?></span></td>
    <td>
      <a class="action-link" href="applications.php?drive_id=<?= $d['drive_id'] ?>">Apps</a>
      <a class="action-link" href="edit_drive.php?id=<?= $d['drive_id'] ?>" style="color:#1565c0">Edit</a>
      <a class="action-del"  href="manage_drives.php?delete=<?= $d['drive_id'] ?>"
         onclick="return confirm('Delete this drive and all its applications?')">Delete</a>
    </td>
  </tr>
  <?php endwhile; ?>
  <?php if (!$found): ?>
  <tr><td colspan="9" class="empty">No drives yet. <a href="post_drive.php">Post one now</a>.</td></tr>
  <?php endif; ?>
</table>
</div><!-- .table-wrap -->
</div><!-- .page-wrap --></body></html>
