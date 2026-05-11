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
if ($spec)   $where .= " AND s.specialization='" . mysqli_real_escape_string($conn,$spec) . "'";
if ($batch)  $where .= " AND s.batch='" . mysqli_real_escape_string($conn,$batch) . "'";

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
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>All Students — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:1400px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:20px}
.filters{display:flex;gap:10px;margin-bottom:20px;align-items:flex-end;flex-wrap:wrap}
.filters input,.filters select{padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;outline:none}
.filters input:focus,.filters select:focus{border-color:#1b5e20}
.btn-filter{background:#1b5e20;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.clear-link{font-size:13px;color:#c62828;text-decoration:none;align-self:center}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);min-width:1100px}
th{background:#1b5e20;color:#fff;padding:11px 12px;font-size:12px;text-align:left}
td{padding:10px 12px;font-size:12px;color:#333;border-bottom:1px solid #f0f0f0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f9f9f9}
.br{background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.bnr{background:#ffebee;color:#c62828;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.bp{background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.empty{text-align:center;padding:40px;color:#888;font-size:14px}
.btn-del{background:#c62828;color:#fff;border:none;padding:5px 11px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:background .15s}
.btn-del:hover{background:#b71c1c}
.flash-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.flash-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
/* Modal */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
.modal-backdrop.show{display:flex}
.modal{background:#fff;border-radius:14px;padding:36px 40px;max-width:420px;width:92%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.2)}
.modal h3{color:#c62828;font-size:18px;margin-bottom:10px}
.modal p{font-size:13px;color:#555;margin-bottom:24px;line-height:1.6}
.modal-btns{display:flex;gap:12px;justify-content:center}
.btn-cancel{background:#eee;color:#333;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-cancel:hover{background:#ddd}
.btn-confirm-del{background:#c62828;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-confirm-del:hover{background:#b71c1c}
@media(max-width:768px){
  .navbar{padding:12px 16px;}
  .navbar h1{font-size:15px;}
  .page-wrap{padding:18px 14px;}
  .filters{flex-direction:column;align-items:stretch;}
  .filters input,.filters select,.btn-filter,.clear-link{width:100%;}
  .clear-link{text-align:center;}
  h2{font-size:17px;}
  .back{font-size:12px;}
  /* Table: allow horizontal scroll, add visual hint */
  .table-wrap{border-radius:10px;-webkit-overflow-scrolling:touch;}
  .table-wrap::after{
    content:'← scroll →';display:block;text-align:center;
    font-size:11px;color:#aaa;padding:6px 0 2px;
  }
  /* Modal compact */
  .modal{padding:28px 22px;}
  .modal h3{font-size:16px;}
  .modal-btns{flex-direction:column;}
  .btn-cancel,.btn-confirm-del{width:100%;}
}
@media(max-width:480px){
  th,td{padding:8px 8px;font-size:11px;}
}
</style>
</head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>👥 All MBA Students</h2>
<?php if ($flash_success): ?><div class="flash-ok"><?= $flash_success ?></div><?php endif; ?>
<?php if ($flash_error):   ?><div class="flash-err"><?= $flash_error ?></div><?php endif; ?>
<p class="sub">Search, filter and monitor student placement readiness across specializations and batches.</p>

<form method="GET" class="filters">
  <input type="text" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
  <select name="spec">
    <option value="">All Specializations</option>
    <?php foreach ($specializations as $s): ?>
    <option value="<?= $s ?>" <?= $spec === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <select name="batch">
    <option value="">All Batches</option>
    <?php foreach ($batches as $b): ?>
    <option value="<?= $b ?>" <?= $batch === $b ? 'selected' : '' ?>><?= $b ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-filter">Filter</button>
  <?php if ($search || $spec || $batch): ?>
  <a class="clear-link" href="students.php">✕ Clear</a>
  <?php endif; ?>
</form>

<div class="table-wrap">
<table>
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
    <th>Action</th>
  </tr>
  <?php $i=1; $found=false; while ($s = mysqli_fetch_assoc($students)): $found=true; ?>
  <tr>
    <td><?= $i++ ?></td>
    <td style="font-family:'Courier New',Courier,monospace;font-weight:700;color:#1a237e;font-size:12px;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap"><?= htmlspecialchars($s['uucms_number'] ?? '—') ?></td>
    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
    <td style="font-size:11px;color:#666"><?= htmlspecialchars($s['email']) ?></td>
    <td><?= htmlspecialchars($s['batch'] ?? '—') ?></td>
    <td><?= htmlspecialchars($s['specialization'] ?? '—') ?></td>
    <td><?= htmlspecialchars($s['contact_number'] ?? '—') ?></td>
    <td style="font-size:11px"><?= htmlspecialchars(($s['city'] ?? '') . ', ' . ($s['state'] ?? '')) ?></td>
    <td><?= $s['tenth_percentage'] ?? '—' ?>%</td>
    <td><?= $s['twelfth_percentage'] ?? '—' ?>%</td>
    <td><?= $s['degree_cgpa'] ?? '—' ?></td>
    <td><?= $s['cgpa'] ?></td>
    <td><?= $s['backlogs'] ?></td>
    <td><?php
      $st = $s['eval_status'] ?? $s['preplacement_status'];
      if ($st === 'Ready') echo '<span class="br">✅ Ready</span>';
      elseif ($st === 'Not Ready') echo '<span class="bnr">⚠ Not Ready</span>';
      else echo '<span style="color:#888;font-size:11px">Pending</span>';
    ?></td>
    <td><?= $s['total_apps'] ?> / <?php if ($s['placed'] > 0) echo '<span class="bp">'.$s['placed'].' placed</span>'; else echo '0'; ?></td>
    <td style="white-space:nowrap">
      <a href="applications.php?student_id=<?= $s['student_id'] ?>"
         style="color:#1b5e20;font-weight:600;font-size:11px;text-decoration:none;margin-right:8px">Apps →</a>
      <button class="btn-del"
              onclick="confirmDelete(<?= $s['student_id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"
              title="Permanently delete this student">
        🗑 Delete
      </button>
    </td>
  </tr>
  <?php endwhile; ?>
  <?php if (!$found): ?>
  <tr><td colspan="16" class="empty">No students found.</td></tr>
  <?php endif; ?>
</table>
</div>
</div>

<!-- Confirmation Modal -->
<div class="modal-backdrop" id="delModal">
  <div class="modal">
    <h3>⚠ Delete Student?</h3>
    <p>You are about to permanently delete <strong id="delName"></strong>.<br>
       This will remove all their applications, notifications, resumes,
       ATS scores, and login account.<br><br>
       <strong style="color:#c62828">This action cannot be undone.</strong></p>
    <form id="delForm" method="POST" action="delete_student.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="student_id" id="delStudentId">
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-confirm-del">Yes, Delete Permanently</button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('delStudentId').value = id;
    document.getElementById('delName').textContent = name;
    document.getElementById('delModal').classList.add('show');
}
function closeModal() {
    document.getElementById('delModal').classList.remove('show');
}
// Close on backdrop click
document.getElementById('delModal').addEventListener('click', function(e){
    if (e.target === this) closeModal();
});
// Close on Escape key
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeModal();
});
</script>
</body></html>
