<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

$apps = mysqli_query($conn,
    "SELECT a.*, d.job_title, d.company_name, d.drive_date, d.description
     FROM application a
     JOIN placement_drive d ON a.drive_id=d.drive_id
     WHERE a.student_id=$student_id
     ORDER BY a.applied_at DESC");

$pageTitle  = 'My Applications';
$activePage = 'applications';
require '_layout.php';
?>

<style>
.app-list { display: flex; flex-direction: column; gap: 14px; }

.app-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  padding: 20px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  border-left: 5px solid var(--accent-soft);
  transition: transform var(--transition), box-shadow var(--transition);
}
.app-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(26,35,126,.13);
}
.app-card.status-selected { border-left-color: var(--success); }
.app-card.status-rejected { border-left-color: var(--danger); }
.app-card.status-applied  { border-left-color: var(--warn); }

.app-company-avatar {
  width: 42px; height: 42px;
  border-radius: 10px;
  background: var(--accent-soft);
  color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
}
.app-info { flex: 1; }
.app-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 3px;
}
.app-company {
  font-size: 13px;
  color: var(--muted);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 5px;
}
.app-meta {
  font-size: 11.5px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}
.app-meta .material-icons-round { font-size: 13px; }
.meta-sep { margin: 0 6px; opacity: .4; }

.app-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  flex-shrink: 0;
}
.sel-date {
  font-size: 11px;
  color: var(--success);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}
.sel-date .material-icons-round { font-size: 13px; }

.btn-withdraw {
  background: var(--surface);
  color: var(--danger);
  border: 1.5px solid #ef9a9a;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: background var(--transition);
  font-family: inherit;
}
.btn-withdraw:hover { background: var(--danger-bg); }

.status-form {
  display: flex;
  gap: 6px;
  align-items: center;
}
.status-form select {
  padding: 6px 10px;
  font-size: 12px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  outline: none;
  font-weight: 600;
  font-family: inherit;
  color: var(--text);
}
.status-form select:focus { border-color: var(--primary); }
.status-form button {
  padding: 6px 12px;
  font-size: 12px;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-family: inherit;
  transition: background var(--transition);
}
.status-form button:hover { background: var(--primary-light); }
.self-label {
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}

.empty-state {
  text-align: center;
  padding: 70px 20px;
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
}
.empty-state .material-icons-round {
  font-size: 56px;
  color: var(--border);
  margin-bottom: 12px;
  display: block;
}
.empty-state p { font-size: 14px; color: var(--muted); margin-bottom: 16px; }

@media(max-width:600px) {
  .app-card { flex-direction: column; align-items: flex-start; }
  .app-right { align-items: flex-start; }
}
</style>

<div class="page-header">
  <h1>📝 My Applications</h1>
  <p>Track the status of all your placement applications.</p>
</div>

<?php if (isset($_GET['withdrawn'])): ?>
  <div class="alert alert-success"><span class="material-icons-round">check_circle</span>Application withdrawn successfully. You can re-apply if the drive is still open.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="alert alert-success"><span class="material-icons-round">check_circle</span>Application status updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
  <?php
    $msgs = [
      'notfound'       => 'Application not found.',
      'cannotwithdraw' => 'You can only withdraw applications in "Applied" status.',
      'invalid'        => 'Invalid status selection.',
    ];
    $errMsg = $msgs[$_GET['err']] ?? 'An error occurred.';
  ?>
  <div class="alert alert-error"><span class="material-icons-round">error</span><?= htmlspecialchars($errMsg) ?></div>
<?php endif; ?>

<div class="app-list">
<?php $found = false; while ($a = mysqli_fetch_assoc($apps)): $found = true;
  $st = $a['application_status'];
  $stClass = strtolower($st);
  $initial = strtoupper(substr($a['company_name'], 0, 1));
?>
<div class="app-card status-<?= $stClass ?>">
  <div class="app-company-avatar"><?= $initial ?></div>
  <div class="app-info">
    <div class="app-title"><?= htmlspecialchars($a['job_title']) ?></div>
    <div class="app-company">
      <span class="material-icons-round" style="font-size:14px">business</span>
      <?= htmlspecialchars($a['company_name']) ?>
    </div>
    <div class="app-meta">
      <span class="material-icons-round">event</span>
      Drive: <?= date('d M Y', strtotime($a['drive_date'])) ?>
      <span class="meta-sep">|</span>
      <span class="material-icons-round">schedule</span>
      Applied: <?= date('d M Y, h:i A', strtotime($a['applied_at'])) ?>
    </div>
  </div>
  <div class="app-right">
    <span class="badge badge-<?= $stClass ?>"><?= $st ?></span>
    <?php if ($st === 'Selected' && $a['selection_date']): ?>
      <div class="sel-date">
        <span class="material-icons-round">celebration</span>
        Selected on <?= date('d M Y', strtotime($a['selection_date'])) ?>
      </div>
    <?php endif; ?>
    <?php if ($st === 'Applied'): ?>
      <form method="POST" action="withdraw_application.php"
            onsubmit="return confirm('Withdraw your application for <?= htmlspecialchars(addslashes($a['job_title'])) ?> at <?= htmlspecialchars(addslashes($a['company_name'])) ?>?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
        <button type="submit" class="btn-withdraw">
          <span class="material-icons-round" style="font-size:14px">undo</span>Withdraw
        </button>
      </form>
      <div class="self-label">Report your outcome:</div>
      <form method="POST" action="update_application_status.php" class="status-form"
            onsubmit="return confirm('Mark this application as \'' + this.status.value + '\'?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
        <select name="status">
          <option value="Selected">✅ Selected</option>
          <option value="Rejected">❌ Rejected</option>
        </select>
        <button type="submit">Update</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endwhile; ?>

<?php if (!$found): ?>
<div class="empty-state">
  <span class="material-icons-round">inbox</span>
  <p>You haven't applied to any drives yet.</p>
  <a href="drives.php" class="btn-primary">Browse Drives →</a>
</div>
<?php endif; ?>
</div>

<?php require '_layout_end.php'; ?>
