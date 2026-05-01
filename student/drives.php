<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

// ── Auto-close expired drives on page load ────────────────────
autoCloseDrives($conn);

// ── Handle apply POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_drive'])) {
    validate_csrf();
    $did = (int)$_POST['apply_drive'];

    // Load drive + check status
    $drv = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM placement_drive WHERE drive_id=$did"));

    $blocked = "";
    if (!$drv) {
        $blocked = "Drive not found.";
    } elseif ($drv['drive_status'] === 'Closed') {
        $blocked = "This drive is no longer accepting applications.";
    } elseif ($drv['last_date'] < date('Y-m-d')) {
        $blocked = "The last date to apply has passed.";
    } else {
        // Fetch student data for eligibility check
        $stu = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM student WHERE student_id=$student_id"));

        if ((float)$stu['tenth_percentage']   < (float)$drv['min_tenth_pct'])  $blocked = "10th percentage ({$stu['tenth_percentage']}%) is below drive requirement ({$drv['min_tenth_pct']}%).";
        elseif ((float)$stu['twelfth_percentage'] < (float)$drv['min_twelfth_pct']) $blocked = "12th percentage ({$stu['twelfth_percentage']}%) is below drive requirement ({$drv['min_twelfth_pct']}%).";
        elseif ((float)$stu['degree_cgpa']    < (float)$drv['min_degree_cgpa']) $blocked = "Degree CGPA ({$stu['degree_cgpa']}) is below drive requirement ({$drv['min_degree_cgpa']}).";
        elseif ((float)$stu['cgpa']           < (float)$drv['min_cgpa'])        $blocked = "MBA CGPA ({$stu['cgpa']}) is below drive requirement ({$drv['min_cgpa']}).";
        elseif ((int)$stu['backlogs']         > (int)$drv['max_backlogs'])      $blocked = "Active backlogs ({$stu['backlogs']}) exceed drive limit ({$drv['max_backlogs']}).";
    }

    if ($blocked) {
        header("Location: drives.php?err=" . urlencode($blocked)); exit();
    }

    // Check duplicate
    $existing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT application_id FROM application WHERE student_id=$student_id AND drive_id=$did"));
    if (!$existing) {
        mysqli_query($conn,
            "INSERT INTO application (student_id,drive_id,application_status,applied_at)
             VALUES ($student_id,$did,'Applied',NOW())");
        $msg = "New application submitted for: {$drv['job_title']} at {$drv['company_name']}.";
        mysqli_query($conn,
            "INSERT INTO notification (student_id,message,is_read,date)
             VALUES ($student_id,'".mysqli_real_escape_string($conn,$msg)."',0,CURDATE())");
    }
    header("Location: drives.php?applied=1"); exit();
}

// ── Fetch student (for eligibility) ──────────────────────────
$stu = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM student WHERE student_id=$student_id"));

// Applied drive IDs (indexed by drive_id for fast lookup)
$app_res = mysqli_query($conn,
    "SELECT drive_id FROM application WHERE student_id=$student_id");
$applied_ids = [];
while ($r = mysqli_fetch_assoc($app_res)) $applied_ids[$r['drive_id']] = true;

// Active drives (open / closing soon) — newest first
$drives_active = mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_status != 'Closed' ORDER BY last_date ASC");

// Closed drives — newest first
$drives_closed = mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_status = 'Closed' ORDER BY drive_date DESC LIMIT 20");

$today = date('Y-m-d');

$err_msg = htmlspecialchars($_GET['err'] ?? '');
// Consume applied flag but don't keep it in URL (avoids toast on F5)
$applied = isset($_GET['applied']) && $_GET['applied'] === '1';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Placement Drives — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:1100px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.toast-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.toast-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:18px}
.drive-card{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:4px solid #3949ab;position:relative}
.drive-card.closed{border-left-color:#bbb;opacity:.85}
.drive-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.job-title{font-size:16px;font-weight:700;color:#1a237e}
.company{font-size:13px;color:#555;margin-top:2px}
/* Status badges */
.badge-open{background:#e8f5e9;color:#2e7d32;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-soon{background:#fff3e0;color:#e65100;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-closed{background:#f5f5f5;color:#888;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin:10px 0;font-size:12px;color:#555}
.meta-grid .key{font-weight:600;color:#333}
.last-date{font-size:12px;font-weight:600;margin-top:8px;padding:6px 10px;border-radius:6px;display:inline-block}
.last-date.normal{background:#e8eaf6;color:#1a237e}
.last-date.urgent{background:#ffebee;color:#c62828;animation:pulse .8s infinite alternate}
@keyframes pulse{from{opacity:1}to{opacity:.7}}
.criteria{margin:10px 0;font-size:12px}
.criteria .key{font-weight:600;color:#333}
.actions{display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap}
.elig-badge{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600}
.eligible{background:#e8f5e9;color:#2e7d32}
.not-eligible{background:#ffebee;color:#c62828}
.btn-apply{background:#1a237e;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-apply:hover{background:#3949ab}
.btn-applied{background:#e8f5e9;color:#2e7d32;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:default}
.btn-closed{background:#f5f5f5;color:#888;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:default}
.desc{font-size:13px;color:#666;margin:8px 0;line-height:1.5}
.empty{text-align:center;padding:60px;background:#fff;border-radius:14px;color:#888}
@media(max-width:640px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 14px;}
  .drive-grid{grid-template-columns:1fr;}
  .meta-grid{grid-template-columns:1fr;}
  .actions{flex-direction:column;align-items:flex-start;}
}
</style>
</head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>🏢 Placement Drives</h2>
<p class="sub">All active MBA placement opportunities. Check eligibility and apply before the last date.</p>

<?php if ($applied): ?><div class="toast-ok">✅ Application submitted successfully!</div><?php endif; ?>
<?php if ($err_msg): ?><div class="toast-err">⚠ <?= $err_msg ?></div><?php endif; ?>

<?php
// ── Active Drives ──────────────────────────────────────────────
$hasActive = false;
?>
<div class="drive-grid">
<?php while ($d = mysqli_fetch_assoc($drives_active)):
    $hasActive = true;
    $status   = getDriveStatus($d['last_date'], $d['drive_status']);
    $isClosed = false;
    $isUrgent = ($status === 'Closing Soon');
    $daysLeft = (int)(new DateTime($today))->diff(new DateTime($d['last_date']))->format('%r%a');

    $tenthOk   = (float)$stu['tenth_percentage']   >= (float)$d['min_tenth_pct'];
    $twelfthOk = (float)$stu['twelfth_percentage'] >= (float)$d['min_twelfth_pct'];
    $degOk     = (float)$stu['degree_cgpa']         >= (float)$d['min_degree_cgpa'];
    $cgpaOk    = (float)$stu['cgpa']               >= (float)$d['min_cgpa'];
    $blOk      = (int)$stu['backlogs']             <= (int)$d['max_backlogs'];
    $eligible  = $tenthOk && $twelfthOk && $degOk && $cgpaOk && $blOk;
    $already   = isset($applied_ids[$d['drive_id']]);
    $bdgClass  = statusBadgeClass($status);
?>
<div class="drive-card">
  <div class="drive-header">
    <div>
      <div class="job-title"><?= htmlspecialchars($d['job_title']) ?></div>
      <div class="company">🏢 <?= htmlspecialchars($d['company_name']) ?></div>
    </div>
    <span class="<?= $bdgClass ?>"><?= $status ?></span>
  </div>

  <?php if ($d['description']): ?>
  <div class="desc"><?= nl2br(htmlspecialchars(substr($d['description'], 0, 140))) ?><?= strlen($d['description']) > 140 ? '…' : '' ?></div>
  <?php endif; ?>

  <?php
    $ldLabel = '📅 Last Date: ' . date('d M Y', strtotime($d['last_date']));
    if ($isUrgent) $ldLabel .= ' — ' . max(0, $daysLeft) . ' day(s) left!';
  ?>
  <div class="last-date <?= $isUrgent ? 'urgent' : 'normal' ?>"><?= $ldLabel ?></div>

  <div class="meta-grid" style="margin-top:10px">
    <div><span class="key">Drive Date:</span> <?= date('d M Y', strtotime($d['drive_date'])) ?></div>
    <div><span class="key">MBA CGPA Min:</span> <?= $d['min_cgpa'] ?></div>
    <div><span class="key">10th Min:</span> <?= $d['min_tenth_pct'] ?>%</div>
    <div><span class="key">12th Min:</span> <?= $d['min_twelfth_pct'] ?>%</div>
    <div><span class="key">Degree CGPA Min:</span> <?= $d['min_degree_cgpa'] ?></div>
    <div><span class="key">Max Backlogs:</span> <?= $d['max_backlogs'] ?></div>
  </div>

  <div class="actions">
    <?php if ($already): ?>
      <button class="btn-applied" disabled>✅ Applied</button>
    <?php elseif ($eligible): ?>
      <span class="elig-badge eligible">✅ Eligible</span>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="apply_drive" value="<?= $d['drive_id'] ?>">
        <button type="submit" class="btn-apply">Apply Now →</button>
      </form>
    <?php else: ?>
      <span class="elig-badge not-eligible">✗ Not Eligible</span>
      <span style="font-size:11px;color:#aaa">
        <?php
          if (!$tenthOk)   echo "10th: {$stu['tenth_percentage']}% &lt; {$d['min_tenth_pct']}% | ";
          if (!$twelfthOk) echo "12th: {$stu['twelfth_percentage']}% &lt; {$d['min_twelfth_pct']}% | ";
          if (!$degOk)     echo "Deg CGPA: {$stu['degree_cgpa']} &lt; {$d['min_degree_cgpa']} | ";
          if (!$cgpaOk)    echo "MBA CGPA: {$stu['cgpa']} &lt; {$d['min_cgpa']} | ";
          if (!$blOk)      echo "Backlogs: {$stu['backlogs']} &gt; {$d['max_backlogs']}";
        ?>
      </span>
    <?php endif; ?>
  </div>
</div>
<?php endwhile; ?>
</div><!-- /drive-grid -->

<?php if (!$hasActive): ?>
<div class="empty">No active placement drives right now. Check back later.</div>
<?php endif; ?>

<?php
// ── Closed Drives (collapsible) ───────────────────────────────
$closedRows = [];
while ($d = mysqli_fetch_assoc($drives_closed)) $closedRows[] = $d;
if ($closedRows):
?>
<div style="margin-top:28px">
  <div style="font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px">
    🔒 Closed Drives
    <button onclick="this.closest('div').nextElementSibling.classList.toggle('hidden')" style="background:none;border:none;color:#1a237e;font-size:12px;font-weight:600;cursor:pointer;margin-left:8px">Show/Hide</button>
  </div>
  <div class="hidden">
  <div class="drive-grid">
  <?php foreach ($closedRows as $d):
      $already = isset($applied_ids[$d['drive_id']]);
  ?>
  <div class="drive-card closed">
    <div class="drive-header">
      <div>
        <div class="job-title"><?= htmlspecialchars($d['job_title']) ?></div>
        <div class="company">🏢 <?= htmlspecialchars($d['company_name']) ?></div>
      </div>
      <span class="badge-closed">Closed</span>
    </div>
    <div class="last-date normal">📅 Last Date: <?= date('d M Y', strtotime($d['last_date'])) ?> (Closed)</div>
    <div class="meta-grid" style="margin-top:8px">
      <div><span class="key">Drive Date:</span> <?= date('d M Y', strtotime($d['drive_date'])) ?></div>
      <div><span class="key">MBA CGPA Min:</span> <?= $d['min_cgpa'] ?></div>
    </div>
    <div class="actions" style="margin-top:10px">
      <?php if ($already): ?>
        <button class="btn-applied" disabled>✅ Applied</button>
      <?php else: ?>
        <button class="btn-closed" disabled>🔒 Closed</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div><!-- /drive-grid closed -->
  </div>
</div>
<?php endif; ?>

<style>.hidden{display:none}</style>
</div></body></html>
