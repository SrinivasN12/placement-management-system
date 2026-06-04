<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

// ── Filters ───────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$spec   = trim($_GET['spec']   ?? '');
$batch  = trim($_GET['batch']  ?? '');

$where = "WHERE 1=1";
if ($search) $where .= " AND (s.name LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR u.email LIKE '%" . mysqli_real_escape_string($conn,$search) . "%')";
if ($spec)   $where .= " AND LOWER(TRIM(s.specialization)) = LOWER('" . mysqli_real_escape_string($conn,trim($spec)) . "')";
if ($batch)  $where .= " AND TRIM(s.batch) = '" . mysqli_real_escape_string($conn,trim($batch)) . "'";

$students = mysqli_query($conn,
    "SELECT s.*, u.email,
            pe.aptitude_marks, pe.gd_marks, pe.english_marks, pe.overall_status AS eval_status,
            COUNT(DISTINCT a.application_id) AS total_apps,
            SUM(a.application_status='Selected') AS placed
     FROM student s
     JOIN user u ON s.user_id = u.user_id
     LEFT JOIN preplacement_evaluation pe ON s.student_id = pe.student_id
     LEFT JOIN application a ON s.student_id = a.student_id
     $where
     GROUP BY s.student_id
     ORDER BY s.name ASC");

$specializations = mbaSpecializations();
$batches         = mbaBatches();

// ── Flash messages ───────────────────────────────────────────
$flash_success = isset($_GET['deleted']) ? '🗑 Student "' . htmlspecialchars(urldecode($_GET['deleted'])) . '" has been permanently deleted.' : '';
$flash_error   = match($_GET['error'] ?? '') {
    'invalid'  => '⚠ Invalid student ID.',
    'notfound' => '⚠ Student not found.',
    'dberror'  => '⚠ Database error — deletion rolled back. No data was changed.',
    default    => ''
};

$page_title  = 'All Students — MBA PMS';
$active_page = 'students';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">All Students</li>
    </ol>
  </nav>

  <!-- Page Header -->
  <div class="page-header mb-3">
    <h1><i class="bi bi-people me-2"></i>All MBA Students</h1>
    <p class="sub">Search, filter and monitor student placement readiness across specializations and batches.</p>
  </div>

  <!-- Flash Messages -->
  <?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= $flash_success ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $flash_error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card pms-card mb-3 p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-lg-4">
        <label class="form-label small fw-semibold text-muted mb-1">Search</label>
        <input type="text" class="form-control form-control-sm" name="search"
               placeholder="Name or email…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-6 col-sm-3 col-lg-2">
        <label class="form-label small fw-semibold text-muted mb-1">Specialization</label>
        <select class="form-select form-select-sm" name="spec">
          <option value="">All Specializations</option>
          <?php foreach ($specializations as $s): ?>
            <option value="<?= $s ?>" <?= $spec === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-3 col-lg-2">
        <label class="form-label small fw-semibold text-muted mb-1">Batch</label>
        <select class="form-select form-select-sm" name="batch">
          <option value="">All Batches</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?= $b ?>" <?= $batch === $b ? 'selected' : '' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-pms btn-sm px-3">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
      </div>
      <?php if ($search || $spec || $batch): ?>
      <div class="col-auto">
        <a href="students.php" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-x-circle me-1"></i>Clear
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>UUCMS No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Batch</th>
            <th>Specialization</th>
            <th>Contact</th>
            <th>City / State</th>
            <th>10th%</th>
            <th>12th%</th>
            <th>Deg CGPA</th>
            <th>MBA CGPA</th>
            <th>Backlogs</th>
            <th>Pre-Placement</th>
            <th>Apps / Placed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; $found = false; while ($s = mysqli_fetch_assoc($students)): $found = true; ?>
          <tr>
            <td class="text-muted"><?= $i++ ?></td>
            <td><span class="uucms-id"><?= htmlspecialchars($s['uucms_number'] ?? '—') ?></span></td>
            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
            <td class="text-muted small"><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['batch'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['specialization'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['contact_number'] ?? '—') ?></td>
            <td class="small"><?= htmlspecialchars(($s['city'] ?? '') . ', ' . ($s['state'] ?? '')) ?></td>
            <td><?= $s['tenth_percentage'] ?? '—' ?>%</td>
            <td><?= $s['twelfth_percentage'] ?? '—' ?>%</td>
            <td><?= $s['degree_cgpa'] ?? '—' ?></td>
            <td><?= $s['cgpa'] ?></td>
            <td><?= $s['backlogs'] ?></td>
            <td>
              <?php
                $st = $s['eval_status'] ?? $s['preplacement_status'];
                if ($st === 'Ready')     echo '<span class="badge-ready">✅ Ready</span>';
                elseif ($st === 'Not Ready') echo '<span class="badge-notready">⚠ Not Ready</span>';
                else echo '<span class="text-muted small">Pending</span>';
              ?>
            </td>
            <td>
              <?= $s['total_apps'] ?> /
              <?php if ($s['placed'] > 0): ?>
                <span class="badge-applied"><?= $s['placed'] ?> placed</span>
              <?php else: ?>
                <span class="text-muted">0</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <a href="applications.php?student_id=<?= $s['student_id'] ?>"
                 class="btn btn-outline-pms btn-sm me-1" title="View Applications">
                <i class="bi bi-file-earmark-text"></i>
              </a>
              <button class="btn btn-danger btn-sm"
                      onclick="confirmDelete(<?= $s['student_id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"
                      title="Delete Student">
                <i class="bi bi-trash3"></i>
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$found): ?>
          <tr>
            <td colspan="16" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>No students found.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="delModal" tabindex="-1" aria-labelledby="delModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title" id="delModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Delete Student?</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">You are about to permanently delete <strong id="delName"></strong>.</p>
        <p class="text-muted small mb-3">This will remove all their applications, notifications, resumes, ATS scores, and login account.</p>
        <div class="alert alert-danger py-2 mb-0">
          <i class="bi bi-exclamation-octagon me-1"></i>
          <strong>This action cannot be undone.</strong>
        </div>
      </div>
      <div class="modal-footer border-0">
        <form id="delForm" method="POST" action="delete_student.php">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="student_id" id="delStudentId">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger ms-2">
            <i class="bi bi-trash3 me-1"></i>Yes, Delete Permanently
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const delModal = new bootstrap.Modal(document.getElementById('delModal'));
function confirmDelete(id, name) {
  document.getElementById('delStudentId').value = id;
  document.getElementById('delName').textContent = name;
  delModal.show();
}
</script>
</body>
</html>
