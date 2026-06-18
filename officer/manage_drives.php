<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// ── Auto-close expired drives ─────────────────────────────────
autoCloseDrives($conn);

// ── Delete drive (POST + CSRF) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_drive_id'])) {
    validate_csrf();
    $did = (int)$_POST['delete_drive_id'];
    if ($did > 0) {
        mysqli_query($conn, "DELETE FROM drive_custom_criteria WHERE drive_id=$did");
        mysqli_query($conn, "DELETE FROM student_custom_criteria_response WHERE drive_id=$did");
        mysqli_query($conn, "DELETE FROM ats_score WHERE drive_id=$did");
        mysqli_query($conn, "DELETE FROM eligibility_result WHERE drive_id=$did");
        mysqli_query($conn, "DELETE FROM application WHERE drive_id=$did");
        mysqli_query($conn, "DELETE FROM placement_drive WHERE drive_id=$did");
    }
    header("Location: manage_drives.php?msg=deleted"); exit();
}

$msg   = $_GET['msg'] ?? '';
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

$page_title  = 'Manage Drives — MBA PMS';
$active_page = 'drives';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">Manage Drives</li>
    </ol>
  </nav>

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div class="page-header mb-0">
      <h1><i class="bi bi-briefcase me-2"></i>Manage Placement Drives</h1>
      <p class="sub">All MBA placement drives. Drives past their last date are auto-closed.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="reports.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i>Analytics
      </a>
      <a href="post_drive.php" class="btn btn-pms btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Post New Drive
      </a>
    </div>
  </div>

  <!-- Flash -->
  <?php if ($msg === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-1"></i>Drive deleted successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Job Title</th>
            <th>Company</th>
            <th>Drive Date</th>
            <th>Last Date</th>
            <th>Eligibility (10th / 12th / Deg)</th>
            <th>Apps</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; $found = false; while ($d = mysqli_fetch_assoc($drives)):
            $found = true;
            $status   = getDriveStatus($d['last_date'], $d['drive_status']);
            $bdgClass = statusBadgeClass($status);
            $ldClass  = $status === 'Closed' ? 'text-secondary' : ($status === 'Closing Soon' ? 'text-warning fw-semibold' : '');
          ?>
          <tr>
            <td class="text-muted"><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($d['job_title']) ?></strong></td>
            <td><?= htmlspecialchars($d['company_name']) ?></td>
            <td class="small"><?= date('d M Y', strtotime($d['drive_date'])) ?></td>
            <td class="small <?= $ldClass ?>">
              <?= date('d M Y', strtotime($d['last_date'])) ?>
              <?= $status === 'Closing Soon' ? ' <i class="bi bi-exclamation-triangle-fill text-warning"></i>' : '' ?>
              <?= $status === 'Closed'       ? ' <i class="bi bi-lock-fill text-secondary"></i>' : '' ?>
            </td>
            <td class="small">
              <span class="text-muted">10th:</span> <?= $d['min_tenth_pct'] ?>%
              &nbsp;|&nbsp;
              <span class="text-muted">12th:</span> <?= $d['min_twelfth_pct'] ?>%
              &nbsp;|&nbsp;
              <span class="text-muted">Deg:</span> <?= $d['min_degree_cgpa'] ?>
            </td>
            <td>
              <span class="badge bg-secondary rounded-pill"><?= $d['app_count'] ?></span>
            </td>
            <td><span class="<?= $bdgClass ?>"><?= $status ?></span></td>
            <td style="white-space:nowrap">
              <a href="applications.php?drive_id=<?= $d['drive_id'] ?>"
                 class="btn btn-outline-pms btn-sm me-1" title="View Applications">
                <i class="bi bi-file-earmark-text"></i>
              </a>
              <a href="edit_drive.php?id=<?= $d['drive_id'] ?>"
                 class="btn btn-outline-primary btn-sm me-1" title="Edit Drive">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Delete this drive and all its applications?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="delete_drive_id" value="<?= $d['drive_id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Drive">
                  <i class="bi bi-trash3"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$found): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              No drives yet. <a href="post_drive.php" class="text-success fw-semibold">Post one now</a>.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
