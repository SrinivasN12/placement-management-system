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

$page_title  = 'Pre-Placement Evaluation — PMS';
$active_page = 'preplacement';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">Pre-Placement Evaluation</li>
    </ol>
  </nav>

  <!-- Page Header -->
  <div class="page-header mb-3">
    <h1><i class="bi bi-clipboard2-check me-2"></i>Pre-Placement Evaluation</h1>
    <p class="sub">Enter marks out of 100 for each student. Grade and placement status are auto-calculated.</p>
  </div>

  <!-- Flash -->
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-1"></i>
      Saved evaluation for <strong><?= htmlspecialchars($success) ?></strong> successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Legend -->
  <div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge-ready d-flex align-items-center gap-1"><i class="bi bi-check-circle"></i> Ready (avg ≥ 60)</span>
    <span class="badge-notready d-flex align-items-center gap-1"><i class="bi bi-x-circle"></i> Not Ready (avg &lt; 60)</span>
    <span class="badge-pending d-flex align-items-center gap-1"><i class="bi bi-clock"></i> Pending (not entered)</span>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Department</th>
            <th>Aptitude <small class="fw-normal opacity-75">(/100)</small></th>
            <th>GD <small class="fw-normal opacity-75">(/100)</small></th>
            <th>English <small class="fw-normal opacity-75">(/100)</small></th>
            <th>Status</th>
            <th>Save</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <form method="POST" action="save_preplacement.php">
              <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
              <input type="hidden" name="student_id"   value="<?= $row['student_id'] ?>">
              <input type="hidden" name="student_name" value="<?= htmlspecialchars($row['name']) ?>">
              <td class="text-muted"><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
              <td class="text-muted small"><?= htmlspecialchars($row['department']) ?></td>
              <td>
                <input type="number" class="form-control form-control-sm text-center"
                       name="aptitude" min="0" max="100" style="width:75px"
                       value="<?= $row['aptitude_marks'] ?? '' ?>" placeholder="0" required>
              </td>
              <td>
                <input type="number" class="form-control form-control-sm text-center"
                       name="gd" min="0" max="100" style="width:75px"
                       value="<?= $row['gd_marks'] ?? '' ?>" placeholder="0" required>
              </td>
              <td>
                <input type="number" class="form-control form-control-sm text-center"
                       name="english" min="0" max="100" style="width:75px"
                       value="<?= $row['english_marks'] ?? '' ?>" placeholder="0" required>
              </td>
              <td>
                <?php if ($row['overall_status'] === 'Ready'): ?>
                  <span class="badge-ready">✅ Ready</span>
                <?php elseif ($row['overall_status'] === 'Not Ready'): ?>
                  <span class="badge-notready">⚠ Not Ready</span>
                <?php else: ?>
                  <span class="badge-pending">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <button type="submit" class="btn btn-pms btn-sm px-3">
                  <i class="bi bi-floppy me-1"></i>Save
                </button>
              </td>
            </form>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
