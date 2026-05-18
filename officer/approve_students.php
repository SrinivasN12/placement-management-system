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
        // Confirm the user is actually a Pending student
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Approvals — PMS Officer</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page{max-width:980px;margin:0 auto;padding:32px 24px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
h2{color:#1b5e20;font-size:20px;margin-bottom:6px}
.sub{color:#666;font-size:13px;margin-bottom:20px}
.flash-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.flash-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.badge-pending{background:#fff3e0;color:#e65100;border:1px solid #ffcc80;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-incomplete{background:#fce4ec;color:#b71c1c;border:1px solid #f48fb1;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.warn-row{background:#fff8f8!important}
.missing{color:#ccc;font-style:italic;font-size:11px}
.empty{text-align:center;padding:50px;color:#888;font-size:14px;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
th{background:#1b5e20;color:#fff;padding:11px 14px;font-size:12px;text-align:left}
td{padding:11px 14px;font-size:13px;color:#333;border-bottom:1px solid #f0f0f0;vertical-align:middle}
tr:last-child td{border-bottom:none}
.uucms-id{font-family:'Courier New',Courier,monospace;font-weight:700;letter-spacing:1.2px;color:#1a237e;font-size:12px}
.btn-approve{background:#2e7d32;color:#fff;border:none;padding:7px 16px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s}
.btn-approve:hover{background:#1b5e20}
.btn-reject{background:#c62828;color:#fff;border:none;padding:7px 16px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s;margin-left:6px}
.btn-reject:hover{background:#b71c1c}
.count-chip{display:inline-block;background:#e65100;color:#fff;font-size:12px;font-weight:700;padding:2px 10px;border-radius:20px;margin-left:10px;vertical-align:middle}
/* Modal */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
.modal-backdrop.show{display:flex}
.modal{background:#fff;border-radius:14px;padding:36px 40px;max-width:400px;width:92%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.2)}
.modal h3{font-size:17px;margin-bottom:10px}
.modal p{font-size:13px;color:#555;margin-bottom:24px;line-height:1.6}
.modal-btns{display:flex;gap:10px;justify-content:center}
.btn-cancel{background:#eee;color:#333;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.modal-confirm{border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;color:#fff}
/* ── Mobile ────────────────────────────────────────────── */
@media(max-width:768px){
  .navbar{padding:12px 16px;}
  .navbar h1{font-size:15px;}
  .page{padding:18px 14px;}
  h2{font-size:17px;}
  .back{font-size:12px;}
  .table-wrap{-webkit-overflow-scrolling:touch;}
  .table-wrap::after{content:'← scroll →';display:block;text-align:center;font-size:11px;color:#aaa;padding:6px 0 2px;}
  .btn-approve,.btn-reject{padding:8px 12px;font-size:12px;}
  /* Modal */
  .modal{padding:28px 20px;}
  .modal-btns{flex-direction:column;}
  .btn-cancel,.modal-confirm{width:100%;padding:12px;}
}
@media(max-width:480px){
  th,td{padding:8px;font-size:11px;}
  .btn-approve,.btn-reject{padding:6px 10px;display:block;width:100%;margin:3px 0;}
}
</style>
</head>
<body>
<div class="navbar">
  <h1>🏢 PMS — Placement Officer Portal</h1>
  <a href="../logout.php">Logout</a>
</div>
<div class="page">
  <a class="back" href="dashboard.php">← Back to Dashboard</a>
  <h2>🧑‍🎓 Student Registration Approvals
    <?php if ($pending_count > 0): ?>
      <span class="count-chip"><?= $pending_count ?> pending</span>
    <?php endif; ?>
  </h2>
  <p class="sub">Review and approve or reject new student registration requests.</p>

  <?php if ($msg_ok):  ?><div class="flash-ok"><?= $msg_ok ?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="flash-err">⚠ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

  <?php if ($pending_count === 0): ?>
    <div class="empty">🎉 No pending registrations — all caught up!</div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <tr>
      <th>#</th>
      <th>UUCMS No.</th>
      <th>Name</th>
      <th>Email</th>
      <th>Batch</th>
      <th>Specialization</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
    <?php $i = 1; while ($r = mysqli_fetch_assoc($pending)): ?>
    <?php $is_orphan = empty($r['student_id']); ?>
    <tr class="<?= $is_orphan ? 'warn-row' : '' ?>">
      <td><?= $i++ ?></td>
      <td>
        <?php if (!empty($r['uucms_number'])): ?>
          <span class="uucms-id"><?= htmlspecialchars($r['uucms_number']) ?></span>
        <?php else: ?>
          <span class="missing">not saved</span>
        <?php endif; ?>
      </td>
      <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
      <td style="font-size:11px;color:#666"><?= htmlspecialchars($r['email']) ?></td>
      <td><?= !empty($r['batch']) ? htmlspecialchars($r['batch']) : '<span class="missing">not saved</span>' ?></td>
      <td><?= !empty($r['specialization']) ? htmlspecialchars($r['specialization']) : '<span class="missing">not saved</span>' ?></td>
      <td>
        <?php if ($is_orphan): ?>
          <span class="badge-incomplete">⚠ Incomplete — Ask to Re-Register</span>
        <?php else: ?>
          <span class="badge-pending">⏳ Pending</span>
        <?php endif; ?>
      </td>
      <td style="white-space:nowrap">
        <button class="btn-approve" onclick="openModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>', 'approve')">&#x2705; Approve</button>
        <button class="btn-reject"  onclick="openModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>', 'reject')">&#x2716; Reject</button>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div class="modal-backdrop" id="modal">
  <div class="modal">
    <h3 id="modalTitle"></h3>
    <p id="modalBody"></p>
    <form method="POST" id="modalForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="user_id"    id="modalUserId">
      <input type="hidden" name="action"     id="modalAction">
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="modal-confirm" id="modalConfirmBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(uid, name, action) {
  document.getElementById('modalUserId').value = uid;
  document.getElementById('modalAction').value  = action;
  if (action === 'approve') {
    document.getElementById('modalTitle').textContent = '✅ Approve Registration?';
    document.getElementById('modalBody').innerHTML  = 'Approving <strong>' + name + '</strong> will activate their account and allow them to log in.';
    document.getElementById('modalConfirmBtn').style.background = '#2e7d32';
    document.getElementById('modalConfirmBtn').textContent = 'Yes, Approve';
  } else {
    document.getElementById('modalTitle').textContent = '🚫 Reject Registration?';
    document.getElementById('modalBody').innerHTML  = 'Rejecting <strong>' + name + '</strong> will deny their access. This cannot be undone automatically.';
    document.getElementById('modalConfirmBtn').style.background = '#c62828';
    document.getElementById('modalConfirmBtn').textContent = 'Yes, Reject';
  }
  document.getElementById('modal').classList.add('show');
}
function closeModal() {
  document.getElementById('modal').classList.remove('show');
}
document.getElementById('modal').addEventListener('click', e => { if (e.target === document.getElementById('modal')) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
