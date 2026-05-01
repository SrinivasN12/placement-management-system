<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
require_once '../config/email_helpers.php';  // ← Email notifications
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$officer_id = (int)($_SESSION['officer_id'] ?? 0);
$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $job_title   = trim($_POST['job_title']);
    $company     = trim($_POST['company_name']);
    $desc        = trim($_POST['description']);
    $min_cgpa    = (float)$_POST['min_cgpa'];
    $max_bl      = (int)$_POST['max_backlogs'];
    $min_tenth   = (float)$_POST['min_tenth_pct'];
    $min_twelfth = (float)$_POST['min_twelfth_pct'];
    $min_deg     = (float)$_POST['min_degree_cgpa'];
    $drive_date  = $_POST['drive_date'];
    $last_date   = $_POST['last_date'];

    if (empty($job_title) || empty($company) || empty($drive_date) || empty($last_date)) {
        $error = "Please fill all required fields.";
    } elseif ($last_date > $drive_date) {
        $error = "Last Date to Apply must be on or before the Drive Date.";
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO placement_drive
             (job_title,company_name,description,min_cgpa,max_backlogs,
              min_tenth_pct,min_twelfth_pct,min_degree_cgpa,
              drive_date,last_date,drive_status,posted_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,'Open',?)");
        mysqli_stmt_bind_param($stmt, "sssdidddssi",
            $job_title, $company, $desc,
            $min_cgpa, $max_bl,
            $min_tenth, $min_twelfth, $min_deg,
            $drive_date, $last_date, $officer_id);

        if (mysqli_stmt_execute($stmt)) {
            $drive_id = mysqli_insert_id($conn);

            // ── Save custom criteria ──────────────────────────────────
            $ccLabels    = $_POST['cc_label']     ?? [];
            $ccTypes     = $_POST['cc_type']      ?? [];
            $ccValues    = $_POST['cc_value']     ?? [];
            $ccMandatory = $_POST['cc_mandatory'] ?? [];
            $ccCount = 0;
            foreach ($ccLabels as $n => $lbl) {
                $lbl = trim($lbl);
                if ($lbl === '') continue;
                $allowed  = ['yes_no','number','text_match','checkbox'];
                $type     = in_array($ccTypes[$n] ?? '', $allowed) ? $ccTypes[$n] : 'yes_no';
                $val      = trim($ccValues[$n] ?? '');
                $mand     = ((int)($ccMandatory[$n] ?? 0)) ? 1 : 0;
                $lbl_s    = mysqli_real_escape_string($conn, $lbl);
                $val_s    = mysqli_real_escape_string($conn, $val);
                mysqli_query($conn,
                    "INSERT INTO drive_custom_criteria
                     (drive_id,criteria_label,criteria_type,criteria_value,is_mandatory)
                     VALUES ($drive_id,'$lbl_s','$type','$val_s',$mand)");
                $ccCount++;
            }

            // Notify eligible students
            $students = mysqli_query($conn,
                "SELECT student_id FROM student
                 WHERE cgpa >= $min_cgpa
                   AND backlogs <= $max_bl
                   AND tenth_percentage >= $min_tenth
                   AND twelfth_percentage >= $min_twelfth
                   AND degree_cgpa >= $min_deg");
            $today   = date('Y-m-d');
            $msg     = "New drive: $job_title at $company. Last date to apply: " . date('d M Y', strtotime($last_date)) . ". Check eligibility and apply!";
            $msg_esc = mysqli_real_escape_string($conn, $msg);
            while ($s = mysqli_fetch_assoc($students)) {
                mysqli_query($conn,
                    "INSERT INTO notification (student_id,message,is_read,date)
                     VALUES ({$s['student_id']},'$msg_esc',0,'$today')");
            }
            $ccNote  = $ccCount > 0 ? " with $ccCount custom criteri" . ($ccCount === 1 ? 'on' : 'a') : '';
            $success = "Drive posted successfully{$ccNote}! Eligible students have been notified.";

            // ── Send email notifications to eligible students ──
            // This runs after the DB insert succeeds.
            // If email sending fails, the drive is still posted.
            try {
                send_placement_drive_emails($drive_id, $conn);
            } catch (\Throwable $e) {
                error_log('[post_drive.php] Email notification error: ' . $e->getMessage());
            }
        } elseif (mysqli_errno($conn) === 1062) {
            $error = "A drive for \"$job_title\" at \"$company\" already exists. Please edit the existing drive or use a different job title / company name.";
        } else {
            $error = "Failed to post drive: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$defaultDriveDate = date('Y-m-d', strtotime('+15 days'));
$defaultLastDate  = date('Y-m-d', strtotime('+10 days'));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Post Drive — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:820px;margin:0 auto;padding:32px 24px}
h2{color:#1b5e20;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.section-title{font-size:11px;font-weight:700;color:#1b5e20;text-transform:uppercase;letter-spacing:.6px;margin:20px 0 12px;padding-bottom:5px;border-bottom:2px solid #e8f5e9}
.form-row{display:flex;gap:16px;flex-wrap:wrap}
.form-row .form-group{flex:1;min-width:160px}
.form-group{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
input,select,textarea{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
input:focus,select:focus,textarea:focus{border-color:#1b5e20}
textarea{resize:vertical;min-height:90px}
.hint{font-size:11px;color:#aaa;margin-top:3px}
.default-badge{display:inline-block;background:#e8f5e9;color:#2e7d32;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;margin-left:6px;vertical-align:middle}
.btn{background:#1b5e20;color:#fff;border:none;padding:13px 32px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#2e7d32}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
.dept-tag{background:#e8f5e9;color:#1b5e20;font-weight:700;padding:10px 13px;border-radius:8px;font-size:14px;display:block;border:1.5px solid #a5d6a7}
/* ── Custom Criteria Section ──────────────────── */
.cc-section{background:#e3f2fd;border:2px dashed #42a5f5;border-radius:12px;padding:22px 24px;margin-top:24px}
.cc-section-hd{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;gap:12px;flex-wrap:wrap}
.cc-section-title{font-size:14px;font-weight:700;color:#0d47a1}
.cc-section-hint{font-size:12px;color:#555;margin-top:3px;line-height:1.5}
.btn-add-cc{background:#1976d2;color:#fff;border:none;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0}
.btn-add-cc:hover{background:#0d47a1}
.cc-row{background:#fff;border:1.5px solid #bbdefb;border-radius:10px;padding:14px 16px;margin-top:12px}
.cc-row-inner{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.cc-fg{display:flex;flex-direction:column;gap:4px;flex:1;min-width:130px}
.cc-fg label{font-size:11px;font-weight:600;color:#444}
.cc-input{padding:8px 11px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:inherit}
.cc-input:focus{border-color:#1976d2}
.cc-mand{display:flex;align-items:center;gap:6px;font-size:12px;color:#444;font-weight:600;cursor:pointer;padding-bottom:8px}
.cc-mand input{width:15px;height:15px;cursor:pointer;accent-color:#1976d2}
.btn-cc-remove{background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;padding:7px 12px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;align-self:flex-end}
.btn-cc-remove:hover{background:#ffcdd2}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .page-wrap{padding:20px 16px;}
  .card{padding:20px 18px;}
  .form-row{flex-direction:column;}
  .btn{width:100%;}
  .cc-row-inner{flex-direction:column;}
  .btn-add-cc{width:100%;}
}
</style>
</head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>➕ Post New Placement Drive</h2>
<p class="sub">Fill in the details below. Eligible MBA students will be notified automatically.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="section-title">Drive Information</div>
  <div class="form-row">
    <div class="form-group"><label>Job Title *</label>
      <input type="text" name="job_title" placeholder="e.g. Management Trainee" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>" required></div>
    <div class="form-group"><label>Company Name *</label>
      <input type="text" name="company_name" placeholder="e.g. Deloitte" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required></div>
  </div>
  <div class="form-group"><label>Job Description</label>
    <textarea name="description" placeholder="Roles, responsibilities, skills required..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
  <div class="form-row">
    <div class="form-group">
      <label>Eligible Department</label>
      <span class="dept-tag">🎓 MBA (All Students)</span>
      <p class="hint">This system is exclusively for MBA students.</p>
    </div>
  </div>

  <div class="section-title">Eligibility Criteria <span class="default-badge">DEFAULTS PRE-FILLED</span></div>
  <div class="form-row">
    <div class="form-group"><label>Min 10th % *</label>
      <input type="number" name="min_tenth_pct" step="0.01" min="0" max="100"
             value="<?= htmlspecialchars($_POST['min_tenth_pct'] ?? '60') ?>" required>
      <p class="hint">Default: 60%</p></div>
    <div class="form-group"><label>Min 12th % *</label>
      <input type="number" name="min_twelfth_pct" step="0.01" min="0" max="100"
             value="<?= htmlspecialchars($_POST['min_twelfth_pct'] ?? '60') ?>" required>
      <p class="hint">Default: 60%</p></div>
    <div class="form-group"><label>Min Degree CGPA *</label>
      <input type="number" name="min_degree_cgpa" step="0.01" min="0" max="10"
             value="<?= htmlspecialchars($_POST['min_degree_cgpa'] ?? '6.0') ?>" required>
      <p class="hint">Default: 6.0</p></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Min MBA CGPA</label>
      <input type="number" name="min_cgpa" step="0.01" min="0" max="10"
             value="<?= htmlspecialchars($_POST['min_cgpa'] ?? '0') ?>" required>
      <p class="hint">Set 0 to allow all MBA CGPAs</p></div>
    <div class="form-group"><label>Max Active Backlogs</label>
      <input type="number" name="max_backlogs" min="0"
             value="<?= htmlspecialchars($_POST['max_backlogs'] ?? '0') ?>" required></div>
  </div>

  <div class="section-title">Schedule</div>
  <div class="form-row">
    <div class="form-group"><label>Last Date to Apply *</label>
      <input type="date" name="last_date"
             value="<?= htmlspecialchars($_POST['last_date'] ?? $defaultLastDate) ?>"
             min="<?= date('Y-m-d') ?>" required>
      <p class="hint">Applications close on this date</p></div>
    <div class="form-group"><label>Drive Date *</label>
      <input type="date" name="drive_date"
             value="<?= htmlspecialchars($_POST['drive_date'] ?? $defaultDriveDate) ?>"
             min="<?= date('Y-m-d') ?>" required></div>
  </div>

  <!-- ── Custom Criteria Section ─────────────────────────── -->
  <div class="cc-section">
    <div class="cc-section-hd">
      <div>
        <div class="cc-section-title">🎯 Additional Custom Criteria <span style="font-weight:400;font-size:12px;color:#666">(Optional)</span></div>
        <div class="cc-section-hint">Add extra requirements beyond standard criteria — e.g. Driving License, Tally Knowledge, Work Experience, Internship</div>
      </div>
      <button type="button" class="btn-add-cc" onclick="addCriteriaRow()">+ Add Criteria</button>
    </div>
    <div id="criteriaRows"></div>
  </div>

  <button type="submit" class="btn" style="margin-top:20px">📢 Post Drive &amp; Notify Students →</button>
</form>
</div>
</div>
<script>
// ── Date validation ────────────────────────────────────────────
document.querySelector('form').addEventListener('submit', function(e){
  const ld = document.querySelector('[name=last_date]').value;
  const dd = document.querySelector('[name=drive_date]').value;
  if(ld && dd && ld > dd){
    e.preventDefault();
    alert('Last Date to Apply must be on or before the Drive Date.');
  }
});

// ── Custom criteria rows ───────────────────────────────────────
let _ccN = 0;

function addCriteriaRow() {
  _ccN++;
  const n = _ccN;
  const div = document.createElement('div');
  div.className = 'cc-row';
  div.id = 'ccrow-' + n;
  div.innerHTML = `
    <div class="cc-row-inner">
      <div class="cc-fg">
        <label>Criteria Label *</label>
        <input type="text" name="cc_label[${n}]" class="cc-input" placeholder="e.g. Driving License" required>
      </div>
      <div class="cc-fg">
        <label>Type</label>
        <select name="cc_type[${n}]" class="cc-input" onchange="updateCcValue(this,${n})">
          <option value="yes_no">Yes / No</option>
          <option value="number">Minimum Number</option>
          <option value="text_match">Must Know (Text)</option>
          <option value="checkbox">Must Have Done</option>
        </select>
      </div>
      <div class="cc-fg" id="ccval-${n}">
        <label>Expected Value</label>
        <select name="cc_value[${n}]" class="cc-input">
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      <div class="cc-fg" style="flex:0;min-width:auto">
        <label>&nbsp;</label>
        <label class="cc-mand">
          <input type="hidden"   name="cc_mandatory[${n}]" value="0">
          <input type="checkbox" name="cc_mandatory[${n}]" value="1" checked>
          Mandatory
        </label>
      </div>
      <div style="align-self:flex-end">
        <button type="button" class="btn-cc-remove" onclick="document.getElementById('ccrow-${n}').remove()">✕ Remove</button>
      </div>
    </div>`;
  document.getElementById('criteriaRows').appendChild(div);
}

function updateCcValue(sel, n) {
  const wrap = document.getElementById('ccval-' + n);
  const type = sel.value;
  let inner = '';
  if (type === 'yes_no') {
    inner = `<select name="cc_value[${n}]" class="cc-input"><option value="Yes">Yes</option><option value="No">No</option></select>`;
  } else if (type === 'number') {
    inner = `<input type="number" name="cc_value[${n}]" class="cc-input" min="0" step="0.1" placeholder="e.g. 1 for 1 year">`;
  } else if (type === 'text_match') {
    inner = `<input type="text" name="cc_value[${n}]" class="cc-input" placeholder="e.g. Tally, SAP, Python">`;
  } else {
    inner = `<input type="text" name="cc_value[${n}]" class="cc-input" placeholder="e.g. Internship, NSS">`;
  }
  wrap.innerHTML = '<label>Expected Value</label>' + inner;
}
</script>
</body></html>
