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

        try {
            $executed = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $e) {
            $executed = false;
            if ($e->getCode() === 1062) {
                $error = "A drive for \"$job_title\" at \"$company\" already exists. Please edit the existing drive or choose a different job title / company.";
            } else {
                $error = "Failed to post drive: " . $e->getMessage();
            }
        }

        if (empty($error) && $executed) {
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

            $ccNote  = $ccCount > 0 ? " with $ccCount custom criteri" . ($ccCount === 1 ? 'on' : 'a') : '';

            // ── Send email + in-app notifications to eligible students ──
            // (send_placement_drive_emails handles both email delivery
            //  and inserting rows into the notification table)
            try {
                send_placement_drive_emails($drive_id, $conn);
            } catch (\Throwable $e) {
                error_log('[post_drive.php] Email notification error: ' . $e->getMessage());
            }

            $success = "Drive posted successfully{$ccNote}! Eligible students have been notified.";
        }
        mysqli_stmt_close($stmt);
    }
}

$defaultDriveDate = date('Y-m-d', strtotime('+15 days'));
$defaultLastDate  = date('Y-m-d', strtotime('+10 days'));
$page_title  = 'Post Drive — Officer Portal';
$active_page = 'drives';
require '_nav.php';
?>
<style>
/* Page specific card & criteria styles */
.section-title { font-size: 11px; font-weight: 700; color: var(--pms-green); text-transform: uppercase; letter-spacing: .6px; margin: 20px 0 12px; padding-bottom: 5px; border-bottom: 2px solid var(--pms-green-pale); }
.hint { font-size: 11px; color: #aaa; margin-top: 3px; }
.default-badge { display: inline-block; background: var(--pms-green-pale); color: var(--pms-green-mid); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; margin-left: 6px; vertical-align: middle; }
.dept-tag { background: var(--pms-green-pale); color: var(--pms-green); font-weight: 700; padding: 10px 13px; border-radius: 8px; font-size: 14px; display: block; border: 1.5px solid var(--pms-green-border); }

/* Custom Criteria Section */
.cc-section { background: #e3f2fd; border: 2px dashed #42a5f5; border-radius: 12px; padding: 22px 24px; margin-top: 24px; }
.cc-section-hd { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; gap: 12px; flex-wrap: wrap; }
.cc-section-title { font-size: 14px; font-weight: 700; color: #0d47a1; }
.cc-section-hint { font-size: 12px; color: #555; margin-top: 3px; line-height: 1.5; }
.btn-add-cc { background: #1976d2; color: #fff; border: none; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background var(--transition); }
.btn-add-cc:hover { background: #0d47a1; }
.cc-row { background: #fff; border: 1.5px solid #bbdefb; border-radius: 10px; padding: 14px 16px; margin-top: 12px; }
.cc-row-inner { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.cc-fg { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 130px; }
.cc-fg label { font-size: 11px; font-weight: 600; color: #444; }
.cc-input { padding: 8px 11px; border: 1.5px solid #ddd; border-radius: 7px; font-size: 13px; outline: none; width: 100%; font-family: inherit; }
.cc-input:focus { border-color: #1976d2; }
.cc-mand { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #444; font-weight: 600; cursor: pointer; padding-bottom: 8px; }
.cc-mand input { width: 15px; height: 15px; cursor: pointer; accent-color: #1976d2; }
.btn-cc-remove { background: #ffebee; color: #c62828; border: 1.5px solid #ef9a9a; padding: 7px 12px; border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; align-self: flex-end; transition: background var(--transition); }
.btn-cc-remove:hover { background: #ffcdd2; }
</style>

<div class="container px-0" style="max-width: 860px;">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="manage_drives.php">Placement Drives</a></li>
      <li class="breadcrumb-item active" aria-current="page">Post Drive</li>
    </ol>
  </nav>

  <div class="page-header mt-2">
    <h1>➕ Post New Placement Drive</h1>
    <div class="sub">Fill in the details below. Eligible MBA students will be notified automatically.</div>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card pms-card">
    <div class="card-body p-4">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="section-title">Drive Information</div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Job Title *</label>
            <input type="text" name="job_title" class="form-control" placeholder="e.g. Management Trainee" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Company Name *</label>
            <input type="text" name="company_name" class="form-control" placeholder="e.g. Deloitte" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label font-semibold text-dark">Job Description</label>
          <textarea name="description" class="form-control" rows="4" placeholder="Roles, responsibilities, skills required..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label font-semibold text-dark">Eligible Department</label>
          <span class="dept-tag">🎓 MBA (All Students)</span>
          <p class="hint">This system is exclusively for MBA students.</p>
        </div>

        <div class="section-title">Eligibility Criteria <span class="default-badge">DEFAULTS PRE-FILLED</span></div>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min 10th % *</label>
            <input type="number" name="min_tenth_pct" class="form-control" step="0.01" min="0" max="100" value="<?= htmlspecialchars($_POST['min_tenth_pct'] ?? '60') ?>" required>
            <p class="hint">Default: 60%</p>
          </div>
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min 12th % *</label>
            <input type="number" name="min_twelfth_pct" class="form-control" step="0.01" min="0" max="100" value="<?= htmlspecialchars($_POST['min_twelfth_pct'] ?? '60') ?>" required>
            <p class="hint">Default: 60%</p>
          </div>
          <div class="col-md-4">
            <label class="form-label font-semibold text-dark">Min Degree CGPA *</label>
            <input type="number" name="min_degree_cgpa" class="form-control" step="0.01" min="0" max="10" value="<?= htmlspecialchars($_POST['min_degree_cgpa'] ?? '6.0') ?>" required>
            <p class="hint">Default: 6.0</p>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Min MBA CGPA *</label>
            <input type="number" name="min_cgpa" class="form-control" step="0.01" min="0" max="10" value="<?= htmlspecialchars($_POST['min_cgpa'] ?? '0') ?>" required>
            <p class="hint">Set 0 to allow all MBA CGPAs</p>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Max Active Backlogs *</label>
            <input type="number" name="max_backlogs" class="form-control" min="0" value="<?= htmlspecialchars($_POST['max_backlogs'] ?? '0') ?>" required>
          </div>
        </div>

        <div class="section-title">Schedule</div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Last Date to Apply *</label>
            <input type="date" name="last_date" class="form-control" value="<?= htmlspecialchars($_POST['last_date'] ?? $defaultLastDate) ?>" min="<?= date('Y-m-d') ?>" required>
            <p class="hint">Applications close on this date</p>
          </div>
          <div class="col-md-6">
            <label class="form-label font-semibold text-dark">Drive Date *</label>
            <input type="date" name="drive_date" class="form-control" value="<?= htmlspecialchars($_POST['drive_date'] ?? $defaultDriveDate) ?>" min="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <!-- ── Custom Criteria Section ─────────────────────────── -->
        <div class="cc-section mb-4">
          <div class="cc-section-hd">
            <div>
              <div class="cc-section-title">🎯 Additional Custom Criteria <span style="font-weight:400;font-size:12px;color:#666">(Optional)</span></div>
              <div class="cc-section-hint">Add extra requirements beyond standard criteria — e.g. Driving License, Tally Knowledge, Work Experience, Internship</div>
            </div>
            <button type="button" class="btn btn-add-cc" onclick="addCriteriaRow()">+ Add Criteria</button>
          </div>
          <div id="criteriaRows"></div>
        </div>

        <button type="submit" class="btn btn-pms w-100 py-3 mt-2">📢 Post Drive &amp; Notify Students →</button>
      </form>
    </div>
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
        <label class="form-label mb-1">Criteria Label *</label>
        <input type="text" name="cc_label[${n}]" class="form-control form-control-sm" placeholder="e.g. Driving License" required>
      </div>
      <div class="cc-fg">
        <label class="form-label mb-1">Type</label>
        <select name="cc_type[${n}]" class="form-select form-select-sm" onchange="updateCcValue(this,${n})">
          <option value="yes_no">Yes / No</option>
          <option value="number">Minimum Number</option>
          <option value="text_match">Must Know (Text)</option>
          <option value="checkbox">Must Have Done</option>
        </select>
      </div>
      <div class="cc-fg" id="ccval-${n}">
        <label class="form-label mb-1">Expected Value</label>
        <select name="cc_value[${n}]" class="form-select form-select-sm">
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      <div class="cc-fg" style="flex:0;min-width:auto">
        <label class="form-label mb-1">&nbsp;</label>
        <label class="cc-mand d-flex align-items-center gap-1">
          <input type="hidden" name="cc_mandatory[${n}]" value="0">
          <input type="checkbox" name="cc_mandatory[${n}]" value="1" checked class="form-check-input">
          Mandatory
        </label>
      </div>
      <div style="align-self:flex-end">
        <button type="button" class="btn btn-cc-remove btn-sm" onclick="document.getElementById('ccrow-${n}').remove()">✕ Remove</button>
      </div>
    </div>`;
  document.getElementById('criteriaRows').appendChild(div);
}

function updateCcValue(sel, n) {
  const wrap = document.getElementById('ccval-' + n);
  const type = sel.value;
  let inner = '';
  if (type === 'yes_no') {
    inner = `<select name="cc_value[${n}]" class="form-select form-select-sm"><option value="Yes">Yes</option><option value="No">No</option></select>`;
  } else if (type === 'number') {
    inner = `<input type="number" name="cc_value[${n}]" class="form-control form-control-sm" min="0" step="0.1" placeholder="e.g. 1 for 1 year">`;
  } else if (type === 'text_match') {
    inner = `<input type="text" name="cc_value[${n}]" class="form-control form-control-sm" placeholder="e.g. Tally, SAP, Python">`;
  } else {
    inner = `<input type="text" name="cc_value[${n}]" class="form-control form-control-sm" placeholder="e.g. Internship, NSS">`;
  }
  wrap.innerHTML = '<label class="form-label mb-1">Expected Value</label>' + inner;
}
</script>

<?php require '_nav_end.php'; ?>
