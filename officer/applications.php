<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) {
  header("Location: ../index.php");
  exit();
}

// ── Filters & Sort. ────────────────────────────────────────────
$drive_filter   = (int) ($_GET['drive_id'] ?? 0);
$student_filter = (int) ($_GET['student_id'] ?? 0);
$status_filter  = trim($_GET['status'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'specialization', 'batch']) ? $_GET['sort'] : 'name';
$dir  = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$flip_dir = ($dir === 'ASC') ? 'DESC' : 'ASC';

// ── Export CSV? ───────────────────────────────────────────────
$export = isset($_GET['export']);

// ── Build WHERE ───────────────────────────────────────────────
$where = "WHERE 1=1";
if ($drive_filter)   $where .= " AND a.drive_id=$drive_filter";
if ($student_filter) $where .= " AND a.student_id=$student_filter";
if ($status_filter)  $where .= " AND a.application_status='" . mysqli_real_escape_string($conn, $status_filter) . "'";

// ── Sort column mapping ───────────────────────────────────────
$sort_col = match ($sort) {
  'specialization' => 's.specialization',
  'batch'          => 's.batch',
  default          => 's.name',
};

// ── Query ─────────────────────────────────────────────────────
$sql = "SELECT a.application_id, a.application_status, a.applied_at,
               s.student_id, s.name AS student_name, s.batch, s.specialization,
               s.cgpa, s.tenth_percentage, s.twelfth_percentage, s.degree_cgpa,
               s.contact_number, s.country, s.state, s.city,
               u.email,
               d.job_title, d.company_name, d.drive_date
        FROM application a
        JOIN student s ON a.student_id = s.student_id
        JOIN user    u ON s.user_id    = u.user_id
        JOIN placement_drive d ON a.drive_id = d.drive_id
        $where
        ORDER BY $sort_col $dir, a.applied_at DESC";

$result = mysqli_query($conn, $sql);

// ── CSV Export ────────────────────────────────────────────────
if ($export) {
  $filename = 'applications_' . date('Ymd_His') . '.csv';
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');
  $out = fopen('php://output', 'w');
  fputs($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Student Name','Email','Contact Number','Batch','Specialization','10th %','12th %','Degree CGPA','MBA CGPA','Country','State','City','Drive / Company','Application Status','Applied Date']);
  while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [
      $row['student_name'], $row['email'], $row['contact_number'],
      $row['batch'], $row['specialization'],
      $row['tenth_percentage'], $row['twelfth_percentage'], $row['degree_cgpa'], $row['cgpa'],
      $row['country'], $row['state'], $row['city'],
      $row['job_title'] . ' — ' . $row['company_name'],
      $row['application_status'],
      date('d M Y H:i', strtotime($row['applied_at'])),
    ]);
  }
  fclose($out);
  exit();
}

// ── Drives list for filter dropdown ──────────────────────────
$drives_list = mysqli_query($conn, "SELECT drive_id, job_title, company_name FROM placement_drive ORDER BY created_at DESC");
$drives_arr = [];
while ($dr = mysqli_fetch_assoc($drives_list)) $drives_arr[] = $dr;

// ── Build export URL (preserve filters + sort) ────────────────
$export_params = http_build_query([
  'export'     => 1,
  'drive_id'   => $drive_filter ?: null,
  'student_id' => $student_filter ?: null,
  'status'     => $status_filter,
  'sort'       => $sort,
  'dir'        => $dir,
]);

// ── Sort URL helper ───────────────────────────────────────────
function sortUrl(string $col, string $currentSort, string $currentDir, int $driveFilter, int $studentFilter, string $statusFilter): string
{
  $d = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
  return 'applications.php?' . http_build_query(array_filter([
    'sort'       => $col,
    'dir'        => $d,
    'drive_id'   => $driveFilter   ?: null,
    'student_id' => $studentFilter ?: null,
    'status'     => $statusFilter,
  ]));
}
function sortIcon(string $col, string $currentSort, string $currentDir): string
{
  if ($currentSort !== $col) return '<i class="bi bi-arrow-down-up text-white-50 ms-1" style="font-size:.65rem"></i>';
  return $currentDir === 'ASC'
    ? '<i class="bi bi-sort-up ms-1" style="font-size:.75rem"></i>'
    : '<i class="bi bi-sort-down ms-1" style="font-size:.75rem"></i>';
}

$page_title  = 'Applications — MBA PMS';
$active_page = 'applications';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">Applications</li>
    </ol>
  </nav>

  <!-- Page Header + Export -->
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div class="page-header mb-0">
      <h1><i class="bi bi-file-earmark-text me-2"></i>Applications</h1>
      <p class="sub">Manage, sort and export all student applications.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="applications.php?<?= $export_params ?>">
      <i class="bi bi-download me-1"></i>Export CSV
    </a>
  </div>

  <!-- Flash -->
  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-1"></i>Application status updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif (isset($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle me-1"></i>Could not update status — <?= htmlspecialchars($_GET['err']) ?>.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card pms-card mb-3 p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-lg-4">
        <label class="form-label small fw-semibold text-muted mb-1">Drive</label>
        <select class="form-select form-select-sm" name="drive_id">
          <option value="">All Drives</option>
          <?php foreach ($drives_arr as $dr): ?>
            <option value="<?= $dr['drive_id'] ?>" <?= $drive_filter == $dr['drive_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($dr['job_title'] . ' — ' . $dr['company_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-3 col-lg-2">
        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">All Statuses</option>
          <?php foreach (['Applied', 'Selected', 'Rejected'] as $st): ?>
            <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
      <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">
      <div class="col-auto">
        <button type="submit" class="btn btn-pms btn-sm px-3">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
      </div>
      <?php if ($drive_filter || $student_filter || $status_filter): ?>
      <div class="col-auto">
        <a href="applications.php" class="btn btn-outline-danger btn-sm">
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
            <th>
              <a href="<?= sortUrl('name', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>"
                 class="text-white text-decoration-none d-flex align-items-center">
                Student Name <?= sortIcon('name', $sort, $dir) ?>
              </a>
            </th>
            <th>Email</th>
            <th>Contact</th>
            <th>
              <a href="<?= sortUrl('batch', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>"
                 class="text-white text-decoration-none d-flex align-items-center">
                Batch <?= sortIcon('batch', $sort, $dir) ?>
              </a>
            </th>
            <th>
              <a href="<?= sortUrl('specialization', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>"
                 class="text-white text-decoration-none d-flex align-items-center">
                Specialization <?= sortIcon('specialization', $sort, $dir) ?>
              </a>
            </th>
            <th>10th%</th>
            <th>12th%</th>
            <th>Deg CGPA</th>
            <th>Drive</th>
            <th>Status</th>
            <th>Applied</th>
            <th>Change Status</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; $found = false; while ($a = mysqli_fetch_assoc($result)): $found = true; ?>
          <tr>
            <td class="text-muted"><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($a['student_name']) ?></strong></td>
            <td class="text-muted small"><?= htmlspecialchars($a['email']) ?></td>
            <td class="small"><?= htmlspecialchars($a['contact_number']) ?></td>
            <td><?= htmlspecialchars($a['batch']) ?></td>
            <td><?= htmlspecialchars($a['specialization']) ?></td>
            <td><?= $a['tenth_percentage'] ?>%</td>
            <td><?= $a['twelfth_percentage'] ?>%</td>
            <td><?= $a['degree_cgpa'] ?></td>
            <td class="small">
              <strong><?= htmlspecialchars($a['job_title']) ?></strong><br>
              <span class="text-muted"><?= htmlspecialchars($a['company_name']) ?></span>
            </td>
            <td>
              <span class="badge-<?= strtolower($a['application_status']) ?>">
                <?= $a['application_status'] ?>
              </span>
            </td>
            <td class="small text-muted" style="white-space:nowrap">
              <?= date('d M Y', strtotime($a['applied_at'])) ?><br>
              <span style="font-size:.7rem"><?= date('h:i A', strtotime($a['applied_at'])) ?></span>
            </td>
            <td>
              <form class="d-flex gap-1 align-items-center" method="POST" action="selection.php"
                    onsubmit="return confirm('Change status for <?= htmlspecialchars(addslashes($a['student_name'])) ?>?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                <input type="hidden" name="student_id"     value="<?= $a['student_id'] ?>">
                <input type="hidden" name="redirect"
                       value="applications.php?<?= http_build_query(array_filter(['drive_id' => $drive_filter ?: null, 'student_id' => $student_filter ?: null, 'status' => $status_filter, 'sort' => $sort, 'dir' => $dir])) ?>">
                <select class="form-select form-select-sm" name="status" style="min-width:100px">
                  <option value="Applied"   <?= $a['application_status'] === 'Applied'   ? 'selected' : '' ?>>Applied</option>
                  <option value="Selected"  <?= $a['application_status'] === 'Selected'  ? 'selected' : '' ?>>Selected</option>
                  <option value="Rejected"  <?= $a['application_status'] === 'Rejected'  ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-pms btn-sm px-2">
                  <i class="bi bi-check2"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$found): ?>
          <tr>
            <td colspan="13" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>No applications found.
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