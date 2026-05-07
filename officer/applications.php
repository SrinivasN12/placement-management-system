<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 2) {
  header("Location: ../index.php");
  exit();
}

// ── Filters & Sort. ────────────────────────────────────────────
$drive_filter = (int) ($_GET['drive_id'] ?? 0);
$student_filter = (int) ($_GET['student_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'specialization', 'batch']) ? $_GET['sort'] : 'name';
$dir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$flip_dir = ($dir === 'ASC') ? 'DESC' : 'ASC';

// ── Export CSV? ───────────────────────────────────────────────
$export = isset($_GET['export']);

// ── Build WHERE ───────────────────────────────────────────────
$where = "WHERE 1=1";
if ($drive_filter)
  $where .= " AND a.drive_id=$drive_filter";
if ($student_filter)
  $where .= " AND a.student_id=$student_filter";
if ($status_filter)
  $where .= " AND a.application_status='" . mysqli_real_escape_string($conn, $status_filter) . "'";

// ── Sort column mapping ───────────────────────────────────────
$sort_col = match ($sort) {
  'specialization' => 's.specialization',
  'batch' => 's.batch',
  default => 's.name',
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
  // BOM for Excel UTF-8
  fputs($out, "\xEF\xBB\xBF");
  fputcsv($out, [
    'Student Name',
    'Email',
    'Contact Number',
    'Batch',
    'Specialization',
    '10th %',
    '12th %',
    'Degree CGPA',
    'MBA CGPA',
    'Country',
    'State',
    'City',
    'Drive / Company',
    'Application Status',
    'Applied Date'
  ]);
  while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [
      $row['student_name'],
      $row['email'],
      $row['contact_number'],
      $row['batch'],
      $row['specialization'],
      $row['tenth_percentage'],
      $row['twelfth_percentage'],
      $row['degree_cgpa'],
      $row['cgpa'],
      $row['country'],
      $row['state'],
      $row['city'],
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
while ($dr = mysqli_fetch_assoc($drives_list))
  $drives_arr[] = $dr;

// ── Build export URL (preserve filters + sort) ────────────────
$export_params = http_build_query([
  'export' => 1,
  'drive_id' => $drive_filter ?: null,
  'student_id' => $student_filter ?: null,
  'status' => $status_filter,
  'sort' => $sort,
  'dir' => $dir,
]);

// ── Sort URL helper ───────────────────────────────────────────
function sortUrl(string $col, string $currentSort, string $currentDir, int $driveFilter, int $studentFilter, string $statusFilter): string
{
  $d = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
  return 'applications.php?' . http_build_query(array_filter([
    'sort' => $col,
    'dir' => $d,
    'drive_id' => $driveFilter ?: null,
    'student_id' => $studentFilter ?: null,
    'status' => $statusFilter,
  ]));
}
function sortIcon(string $col, string $currentSort, string $currentDir): string
{
  if ($currentSort !== $col)
    return '<span style="color:#aaa;font-size:10px">⇅</span>';
  return $currentDir === 'ASC' ? '▲' : '▼';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applications — MBA PMS</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f0f2f5
    }

    .navbar {
      background: #1b5e20;
      color: #fff;
      padding: 14px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .navbar h1 {
      font-size: 18px
    }

    .navbar a {
      color: #fff;
      text-decoration: none;
      font-size: 13px
    }

    .page-wrap {
      max-width: 1400px;
      margin: 0 auto;
      padding: 32px 24px
    }

    h2 {
      color: #1b5e20;
      margin-bottom: 6px;
      font-size: 20px
    }

    .sub {
      color: #666;
      font-size: 13px;
      margin-bottom: 20px
    }

    .filters {
      display: flex;
      gap: 10px;
      margin-bottom: 18px;
      align-items: flex-end;
      flex-wrap: wrap
    }

    .filters select,
    .filters input {
      padding: 9px 12px;
      border: 1.5px solid #ddd;
      border-radius: 8px;
      font-size: 13px;
      outline: none
    }

    .filters select:focus,
    .filters input:focus {
      border-color: #1b5e20
    }

    .btn-filter {
      background: #1b5e20;
      color: #fff;
      border: none;
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer
    }

    .btn-export {
      background: #1565c0;
      color: #fff;
      border: none;
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block
    }

    .btn-export:hover {
      background: #0d47a1
    }

    .clear-link {
      font-size: 13px;
      color: #c62828;
      text-decoration: none;
      align-self: center
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      min-width: 900px
    }

    .table-wrap {
      overflow-x: auto
    }

    th {
      background: #1b5e20;
      color: #fff;
      padding: 11px 12px;
      font-size: 12px;
      text-align: left;
      white-space: nowrap
    }

    th a {
      color: #fff;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px
    }

    th a:hover {
      opacity: .85
    }

    td {
      padding: 10px 12px;
      font-size: 12px;
      color: #333;
      border-bottom: 1px solid #f0f0f0;
      vertical-align: middle
    }

    tr:last-child td {
      border-bottom: none
    }

    tr:hover td {
      background: #f9f9f9
    }

    .status-Applied {
      background: #e3f2fd;
      color: #1565c0;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 600
    }

    .status-Selected {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 600
    }

    .status-Rejected {
      background: #ffebee;
      color: #c62828;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 600
    }

    .sel-form {
      display: inline-flex;
      gap: 6px;
      align-items: center
    }

    .sel-form select {
      padding: 4px 8px;
      font-size: 12px;
      border: 1px solid #ddd;
      border-radius: 6px
    }

    .sel-form button {
      padding: 4px 10px;
      font-size: 12px;
      background: #1b5e20;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer
    }

    .back {
      display: inline-block;
      margin-bottom: 18px;
      color: #1b5e20;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600
    }

    .empty {
      text-align: center;
      padding: 40px;
      color: #888;
      font-size: 14px
    }

    .top-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
      flex-wrap: wrap;
      gap: 10px
    }

    @media(max-width:768px) {
      .navbar {
        padding: 12px 16px;
      }

      .page-wrap {
        padding: 20px 16px;
      }

      .filters {
        flex-direction: column;
        align-items: stretch;
      }

      .filters select,
      .btn-filter {
        width: 100%;
      }

      .top-row {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body>
  <div class="navbar">
    <h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a>
  </div>
  <div class="page-wrap">
    <a class="back" href="dashboard.php">← Back to Dashboard</a>
    <div class="top-row">
      <div>
        <h2>📋 Applications</h2>
        <p class="sub">Manage, sort and export all student applications.</p>
      </div>
      <a class="btn-export" href="applications.php?<?= $export_params ?>">
        📥 Export CSV
      </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters">
      <select name="drive_id">
        <option value="">All Drives</option>
        <?php foreach ($drives_arr as $dr): ?>
          <option value="<?= $dr['drive_id'] ?>" <?= $drive_filter == $dr['drive_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($dr['job_title'] . ' — ' . $dr['company_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (['Applied', 'Selected', 'Rejected'] as $st): ?>
          <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
      <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
      <button type="submit" class="btn-filter">Filter</button>
      <?php if ($drive_filter || $student_filter || $status_filter): ?>
        <a class="clear-link" href="applications.php">✕ Clear</a>
      <?php endif; ?>
    </form>

    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th>
            <a href="<?= sortUrl('name', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>">
              Student Name <?= sortIcon('name', $sort, $dir) ?>
            </a>
          </th>
          <th>Email</th>
          <th>Contact</th>
          <th>
            <a href="<?= sortUrl('batch', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>">
              Batch <?= sortIcon('batch', $sort, $dir) ?>
            </a>
          </th>
          <th>
            <a href="<?= sortUrl('specialization', $sort, $dir, $drive_filter, $student_filter, $status_filter) ?>">
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
        <?php $i = 1;
        $found = false;
        while ($a = mysqli_fetch_assoc($result)):
          $found = true; ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($a['student_name']) ?></strong></td>
            <td style="color:#666"><?= htmlspecialchars($a['email']) ?></td>
            <td><?= htmlspecialchars($a['contact_number']) ?></td>
            <td><?= htmlspecialchars($a['batch']) ?></td>
            <td><?= htmlspecialchars($a['specialization']) ?></td>
            <td><?= $a['tenth_percentage'] ?>%</td>
            <td><?= $a['twelfth_percentage'] ?>%</td>
            <td><?= $a['degree_cgpa'] ?></td>
            <td style="font-size:11px"><strong><?= htmlspecialchars($a['job_title']) ?></strong><br><span
                style="color:#666"><?= htmlspecialchars($a['company_name']) ?></span></td>
            <td><span class="status-<?= $a['application_status'] ?>"><?= $a['application_status'] ?></span></td>
            <td style="color:#555;font-size:11px;white-space:nowrap">
              <?= date('d M Y', strtotime($a['applied_at'])) ?><br>
              <span style="color:#aaa"><?= date('h:i A', strtotime($a['applied_at'])) ?></span>
            </td>
            <td>
              <form class="sel-form" method="POST" action="selection.php"
                onsubmit="return confirm('Change status for <?= htmlspecialchars(addslashes($a['student_name'])) ?>?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                <input type="hidden" name="student_id" value="<?= $a['student_id'] ?>">
                <input type="hidden" name="redirect"
                  value="applications.php?<?= http_build_query(array_filter(['drive_id' => $drive_filter ?: null, 'student_id' => $student_filter ?: null, 'status' => $status_filter, 'sort' => $sort, 'dir' => $dir])) ?>">
                <select name="status" data-student="<?= htmlspecialchars($a['student_name']) ?>"
                  data-job="<?= htmlspecialchars($a['job_title']) ?>" data-current="<?= $a['application_status'] ?>">
                  <option value="Applied" <?= $a['application_status'] === 'Applied' ? 'selected' : '' ?>>Applied</option>
                  <option value="Selected" <?= $a['application_status'] === 'Selected' ? 'selected' : '' ?>>Selected</option>
                  <option value="Rejected" <?= $a['application_status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit">Save</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if (!$found): ?>
          <tr>
            <td colspan="13" class="empty">No applications found.</td>
          </tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</body>

</html>