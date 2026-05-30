<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

$officer_id = (int) ($_SESSION['officer_id'] ?? 0);

// Stats
$total_students  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM student"))['c'] ?? 0;
$active_drives   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM placement_drive WHERE drive_date >= CURDATE()"))['c'] ?? 0;
$total_apps      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM application"))['c'] ?? 0;
$placed_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) c FROM application WHERE application_status='Selected'"))['c'] ?? 0;
$pending_approvals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM user WHERE role_id=1 AND status='Pending'"))['c'] ?? 0;

$page_title  = 'TPO Dashboard — PMS';
$active_page = 'dashboard';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Page Header -->
  <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
      <h1><i class="bi bi-speedometer2 me-2"></i>Welcome, <?= htmlspecialchars($name) ?>!</h1>
      <p class="sub mb-0">Manage placement drives, students, and reports from here.</p>
    </div>
    <?php if ($pending_approvals > 0): ?>
    <a href="approve_students.php" class="btn btn-danger d-flex align-items-center gap-2">
      <i class="bi bi-person-check"></i>
      <?= $pending_approvals ?> Pending Approval<?= $pending_approvals > 1 ? 's' : '' ?>
    </a>
    <?php endif; ?>
  </div>

  <!-- Stat Cards -->
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">

    <div class="col">
      <div class="card stat-card h-100 p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="stat-num text-success"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card stat-card h-100 p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-briefcase-fill"></i></div>
          <div>
            <div class="stat-num text-primary"><?= $active_drives ?></div>
            <div class="stat-label">Active Drives</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card stat-card h-100 p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-file-earmark-text-fill"></i></div>
          <div>
            <div class="stat-num text-info"><?= $total_apps ?></div>
            <div class="stat-label">Applications</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card stat-card h-100 p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon" style="background:#e8f5e9;"><i class="bi bi-patch-check-fill text-success fs-5"></i></div>
          <div>
            <div class="stat-num text-success"><?= $placed_students ?></div>
            <div class="stat-label">Students Placed</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card stat-card h-100 p-3 <?= $pending_approvals > 0 ? 'border border-danger border-2' : '' ?>">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon <?= $pending_approvals > 0 ? 'bg-danger bg-opacity-10' : 'bg-secondary bg-opacity-10' ?>">
            <i class="bi bi-person-exclamation <?= $pending_approvals > 0 ? 'text-danger' : 'text-secondary' ?> fs-5"></i>
          </div>
          <div>
            <div class="stat-num <?= $pending_approvals > 0 ? 'text-danger' : 'text-secondary' ?>"><?= $pending_approvals ?></div>
            <div class="stat-label">Pending Approvals</div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Quick Actions -->
  <div class="mb-3">
    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size:.72rem; letter-spacing:.8px;">
      <i class="bi bi-grid me-1"></i>Quick Actions
    </h6>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2">

      <div class="col">
        <a class="module-btn w-100" href="profile.php">
          <span class="mi"><i class="bi bi-person-gear"></i></span> My Profile
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100 <?= $pending_approvals > 0 ? 'border-danger text-danger' : '' ?>"
           href="approve_students.php"
           style="<?= $pending_approvals > 0 ? 'background:#fff5f5;' : '' ?>">
          <span class="mi"><i class="bi bi-person-check"></i></span>
          Approve Registrations
          <?php if ($pending_approvals > 0): ?>
            <span class="badge bg-danger rounded-pill ms-auto"><?= $pending_approvals ?></span>
          <?php endif; ?>
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="post_drive.php">
          <span class="mi"><i class="bi bi-plus-circle"></i></span> Post New Drive
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="manage_drives.php">
          <span class="mi"><i class="bi bi-briefcase"></i></span> Manage Drives
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="students.php">
          <span class="mi"><i class="bi bi-people"></i></span> View Students
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="applications.php">
          <span class="mi"><i class="bi bi-file-earmark-text"></i></span> View Applications
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="reports.php">
          <span class="mi"><i class="bi bi-bar-chart-line"></i></span> Analytics & Reports
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="mock_test_analytics.php">
          <span class="mi"><i class="bi bi-trophy"></i></span> Mock Test Analytics
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="notify.php">
          <span class="mi"><i class="bi bi-bell"></i></span> Send Notifications
        </a>
      </div>

      <div class="col">
        <a class="module-btn w-100" href="preplacement.php">
          <span class="mi"><i class="bi bi-clipboard2-check"></i></span> Pre-Placement Eval
        </a>
      </div>

    </div>
  </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>