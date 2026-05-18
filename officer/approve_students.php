<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$msg_ok = $msg_err = '';

// ── Handle Approve / Reject POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $target_uid = (int)($_POST['user_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($target_uid && in_array($action, ['approve', 'reject'])) {
        $chk = mysqli_prepare($conn, "SELECT u.user_id, COALESCE(s.name, u.email) AS name FROM user u LEFT JOIN student s ON s.user_id=u.user_id WHERE u.user_id=? AND u.role_id=1 AND u.status='Pending'");
        mysqli_stmt_bind_param($chk, "i", $target_uid);
        mysqli_stmt_execute($chk);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);

        if ($row) {
            $new_status = ($action === 'approve') ? 'Active' : 'Rejected';
            $upd = mysqli_prepare($conn, "UPDATE user SET status=? WHERE user_id=?");
            mysqli_stmt_bind_param($upd, "si", $new_status, $target_uid);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $sname = htmlspecialchars($row['name'] ?? $row['email']);
            $msg_ok = $action === 'approve'
                ? "✅ {$sname}'s account has been approved. They can now log in."
                : "🚫 {$sname}'s registration has been rejected.";
        } else {
            $msg_err = "Student not found or already processed.";
        }
    }
}

// ── Fetch pending students ─────────────────────────────────────
$pending = mysqli_query($conn,
    "SELECT u.user_id, u.email, u.status, s.student_id,
            COALESCE(s.name, u.email) AS name,
            s.uucms_number, s.batch, s.specialization, u.user_id AS uid
     FROM user u
     LEFT JOIN student s ON s.user_id = u.user_id
     WHERE u.role_id = 1 AND u.status = 'Pending'
     ORDER BY u.user_id ASC");

$pending_count = mysqli_num_rows($pending);

$page_title  = 'Student Approvals — PMS Officer';
$active_page = 'approvals';
require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">Student Approvals</li>
    </ol>
  </nav>

  <!-- Page Header -->
  <div class="page-header mb-3 d-flex align-items-center gap-3 flex-wrap">
    <h1 class="mb-0"><i class="bi bi-person-check me-2"></i>Student Registration Approvals</h1>
    <?php if ($pending_count > 0): ?>
      <span class="badge bg-danger fs-6 rounded-pill"><?= $pending_count ?> pending</span>
    <?php endif; ?>
  </div>
  <p class="sub mb-4">Review and approve or reject new student registration requests.</p>

  <!-- Flash -->
  <?php if ($msg_ok):  ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= $msg_ok ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($msg_err): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($msg_err) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($pending_count === 0): ?>
    <div class="card pms-card p-5 text-center text-muted">
      <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
      <h5 class="fw-semibold">All caught up!</h5>
      <p class="mb-0">No pending registrations at this time.</p>
    </div>
  <?php else: ?>

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
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($r = mysqli_fetch_assoc($pending)):
            $is_orphan = empty($r['student_id']); ?>
          <tr class="<?= $is_orphan ? 'table-danger' : '' ?>">
            <td class="text-muted"><?= $i++ ?></td>
            <td>
              <?php if (!empty($r['uucms_number'])): ?>
                <span class="uucms-id"><?= htmlspecialchars($r['uucms_number']) ?></span>
              <?php else: ?>
                <span class="text-muted fst-italic small">not saved</span>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
            <td class="text-muted small"><?= htmlspecialchars($r['email']) ?></td>
            <td>
              <?= !empty($r['batch']) ? htmlspecialchars($r['batch']) : '<span class="text-muted fst-italic small">not saved</span>' ?>
            </td>
            <td>
              <?= !empty($r['specialization']) ? htmlspecialchars($r['specialization']) : '<span class="text-muted fst-italic small">not saved</span>' ?>
            </td>
            <td>
              <?php if ($is_orphan): ?>
                <span class="badge bg-danger">⚠ Incomplete</span>
              <?php else: ?>
                <span class="badge-pending">⏳ Pending</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <button class="btn btn-success btn-sm me-1"
                      onclick="openModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>', 'approve')">
                <i class="bi bi-check-circle me-1"></i>Approve
              </button>
              <button class="btn btn-danger btn-sm"
                      onclick="openModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>', 'reject')">
                <i class="bi bi-x-circle me-1"></i>Reject
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0" id="modalHeader">
        <h5 class="modal-title" id="modalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer border-0">
        <form method="POST" id="modalForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="user_id" id="modalUserId">
          <input type="hidden" name="action"  id="modalAction">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn ms-2" id="modalConfirmBtn">Confirm</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
function openModal(uid, name, action) {
  document.getElementById('modalUserId').value = uid;
  document.getElementById('modalAction').value  = action;
  const header = document.getElementById('modalHeader');
  const title  = document.getElementById('modalTitle');
  const body   = document.getElementById('modalBody');
  const btn    = document.getElementById('modalConfirmBtn');

  if (action === 'approve') {
    header.className = 'modal-header border-0 bg-success text-white';
    title.innerHTML  = '<i class="bi bi-check-circle me-2"></i>Approve Registration?';
    body.innerHTML   = 'Approving <strong>' + name + '</strong> will activate their account and allow them to log in.';
    btn.className    = 'btn btn-success ms-2';
    btn.textContent  = 'Yes, Approve';
  } else {
    header.className = 'modal-header border-0 bg-danger text-white';
    title.innerHTML  = '<i class="bi bi-x-circle me-2"></i>Reject Registration?';
    body.innerHTML   = 'Rejecting <strong>' + name + '</strong> will deny their access. This cannot be undone automatically.';
    btn.className    = 'btn btn-danger ms-2';
    btn.textContent  = 'Yes, Reject';
  }
  actionModal.show();
}
</script>
</body>
</html>
