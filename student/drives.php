<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

// ── Auto-close expired drives on page load ────────────────────
autoCloseDrives($conn);

// ── Fetch student (for eligibility display) ───────────────────
$stu = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM student WHERE student_id=$student_id"));

// Applied drive IDs
$app_res = mysqli_query($conn,
    "SELECT drive_id FROM application WHERE student_id=$student_id");
$applied_ids = [];
while ($r = mysqli_fetch_assoc($app_res)) $applied_ids[$r['drive_id']] = true;

// Active drives
$drives_active = mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_status != 'Closed' ORDER BY last_date ASC");

// Closed drives
$drives_closed = mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_status = 'Closed' ORDER BY drive_date DESC LIMIT 20");

$today = date('Y-m-d');
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
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:18px}
.drive-card{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:4px solid #3949ab;position:relative}
.drive-card.closed{border-left-color:#bbb;opacity:.85}
.drive-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.job-title{font-size:16px;font-weight:700;color:#1a237e}
.company{font-size:13px;color:#555;margin-top:2px}
.badge-open{background:#e8f5e9;color:#2e7d32;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-soon{background:#fff3e0;color:#e65100;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-closed{background:#f5f5f5;color:#888;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin:10px 0;font-size:12px;color:#555}
.meta-grid .key{font-weight:600;color:#333}
.last-date{font-size:12px;font-weight:600;margin-top:8px;padding:6px 10px;border-radius:6px;display:inline-block}
.last-date.normal{background:#e8eaf6;color:#1a237e}
.last-date.urgent{background:#ffebee;color:#c62828;animation:pulse .8s infinite alternate}
@keyframes pulse{from{opacity:1}to{opacity:.7}}
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
.apply-result{margin-top:10px;font-size:13px;line-height:1.6;border-radius:8px;padding:0}
.apply-result.ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;padding:10px 14px}
.apply-result.fail{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;padding:10px 14px}

/* ── Modal ──────────────────────────────────────────────────── */
#ccModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;align-items:center;justify-content:center;padding:16px}
#ccModal.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:0;max-width:520px;width:100%;max-height:88vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 12px 48px rgba(0,0,0,.28)}
.modal-head{background:linear-gradient(135deg,#1a237e,#3949ab);color:#fff;padding:20px 24px}
.modal-head h3{font-size:16px;font-weight:700}
.modal-head p{font-size:12px;opacity:.8;margin-top:4px}
.modal-body{padding:20px 24px;overflow-y:auto;flex:1}
.modal-hint{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:10px 14px;font-size:12px;color:#5d4037;margin-bottom:16px}
.modal-field{margin-bottom:16px}
.modal-field label{display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px}
.modal-field .hint-txt{font-size:11px;color:#888;margin-top:3px}
.modal-field input[type=text],.modal-field input[type=number],.modal-field select{width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;outline:none}
.modal-field input:focus,.modal-field select:focus{border-color:#1a237e}
.modal-field.invalid input,.modal-field.invalid select{border-color:#c62828;background:#fff8f8}
.modal-field .check-wrap{display:flex;align-items:center;gap:8px;margin-top:4px}
.modal-field .check-wrap input{width:16px;height:16px;cursor:pointer;accent-color:#1a237e}
.req{color:#c62828;margin-left:2px}
.modal-foot{padding:16px 24px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{background:#f5f5f5;color:#333;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-modal-cancel:hover{background:#e0e0e0}
.btn-modal-submit{background:#1a237e;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-modal-submit:hover{background:#3949ab}
.btn-modal-submit:disabled{background:#90a4ae;cursor:not-allowed}
.modal-loading{text-align:center;padding:12px;font-size:13px;color:#555}

@media(max-width:640px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 14px;}
  .drive-grid{grid-template-columns:1fr;}
  .meta-grid{grid-template-columns:1fr;}
  .actions{flex-direction:column;align-items:flex-start;}
  .modal-box{max-height:95vh;}
}
.hidden{display:none}
</style>
</head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>🏢 Placement Drives</h2>
<p class="sub">All active MBA placement opportunities. Check eligibility and apply before the last date.</p>

<?php
// ── Active Drives ─────────────────────────────────────────────
$hasActive = false;
?>
<div class="drive-grid">
<?php while ($d = mysqli_fetch_assoc($drives_active)):
    $hasActive = true;
    $status   = getDriveStatus($d['last_date'], $d['drive_status']);
    $isUrgent = ($status === 'Closing Soon');
    $daysLeft = (int)(new DateTime($today))->diff(new DateTime($d['last_date']))->format('%r%a');

    $tenthOk   = (float)$stu['tenth_percentage']   >= (float)$d['min_tenth_pct'];
    $twelfthOk = (float)$stu['twelfth_percentage'] >= (float)$d['min_twelfth_pct'];
    $degOk     = (float)$stu['degree_cgpa']        >= (float)$d['min_degree_cgpa'];
    $cgpaOk    = (float)$stu['cgpa']               >= (float)$d['min_cgpa'];
    $blOk      = (int)$stu['backlogs']             <= (int)$d['max_backlogs'];
    $eligible  = $tenthOk && $twelfthOk && $degOk && $cgpaOk && $blOk;
    $already   = isset($applied_ids[$d['drive_id']]);
    $bdgClass  = statusBadgeClass($status);

    $did       = (int)$d['drive_id'];
    $companyJs = addslashes(htmlspecialchars($d['company_name']));
    $roleJs    = addslashes(htmlspecialchars($d['job_title']));
?>
<div class="drive-card" id="card-<?= $did ?>">
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
      <button class="btn-apply" id="applybtn-<?= $did ?>"
              onclick="handleApply(<?= $did ?>, '<?= $companyJs ?>', '<?= $roleJs ?>')">
        Apply Now →
      </button>
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
  <!-- Result box shown after AJAX apply -->
  <div id="result-<?= $did ?>" class="apply-result"></div>
</div>
<?php endwhile; ?>
</div><!-- /drive-grid -->

<?php if (!$hasActive): ?>
<div class="empty">No active placement drives right now. Check back later.</div>
<?php endif; ?>

<?php
// ── Closed Drives ─────────────────────────────────────────────
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
</div><!-- /content -->

<!-- ════════════════════════════════════════════════════════════
     CUSTOM CRITERIA MODAL
     ════════════════════════════════════════════════════════════ -->
<div id="ccModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="modalTitle">Additional Requirements</h3>
      <p id="modalSubtitle">Please answer the following criteria to apply</p>
    </div>
    <div class="modal-body">
      <div class="modal-hint">
        ℹ️ This company has additional requirements. Please answer honestly — your eligibility will be checked automatically.
      </div>
      <div id="modalFields"></div>
      <div id="modalLoading" class="modal-loading" style="display:none">
        ⏳ Checking eligibility...
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-modal-submit" id="modalSubmitBtn" onclick="submitModal()">Submit & Apply</button>
    </div>
  </div>
</div>

<script>
// ── CSRF token (embedded from PHP session) ────────────────────
const CSRF_TOKEN = '<?= csrf_token() ?>';

// ── State ─────────────────────────────────────────────────────
let _activeDriveId   = null;
let _activeCriteria  = [];

// ── Step 1: Student clicks Apply ─────────────────────────────
function handleApply(driveId, company, role) {
  fetch('get_custom_criteria.php?drive_id=' + driveId)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        showResult(driveId, false, ['Could not load criteria. Please try again.']);
        return;
      }
      if (!data.has_criteria) {
        // No custom criteria — apply directly
        doApply(driveId, []);
      } else {
        // Show modal with custom criteria
        openModal(driveId, company, role, data.criteria);
      }
    })
    .catch(() => {
      showResult(driveId, false, ['Network error. Please refresh and try again.']);
    });
}

// ── Open modal with criteria fields ──────────────────────────
function openModal(driveId, company, role, criteria) {
  _activeDriveId  = driveId;
  _activeCriteria = criteria;

  document.getElementById('modalTitle').textContent    = company + ' — ' + role;
  document.getElementById('modalSubtitle').textContent = 'Answer all required fields (*) to complete your application';
  document.getElementById('modalLoading').style.display = 'none';
  document.getElementById('modalSubmitBtn').disabled   = false;

  // Build form fields
  const container = document.getElementById('modalFields');
  container.innerHTML = '';

  criteria.forEach(c => {
    const div = document.createElement('div');
    div.className = 'modal-field';
    div.id = 'mf-' + c.criteria_id;
    const req = c.is_mandatory ? '<span class="req">*</span>' : '';
    let inputHtml = '';
    let hintHtml  = '';

    if (c.criteria_type === 'yes_no') {
      inputHtml = `<select data-cid="${c.criteria_id}">
        <option value="">-- Select --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>`;
    } else if (c.criteria_type === 'number') {
      inputHtml = `<input type="number" min="0" step="0.1" placeholder="Enter a number" data-cid="${c.criteria_id}">`;
      hintHtml  = `<div class="hint-txt">Minimum required: ${c.criteria_value}</div>`;
    } else if (c.criteria_type === 'text_match') {
      inputHtml = `<input type="text" placeholder="Your answer" data-cid="${c.criteria_id}">`;
      hintHtml  = `<div class="hint-txt">Required knowledge: ${c.criteria_value}</div>`;
    } else if (c.criteria_type === 'checkbox') {
      inputHtml = `<div class="check-wrap">
        <input type="checkbox" id="chk-${c.criteria_id}" data-cid="${c.criteria_id}" value="yes">
        <label for="chk-${c.criteria_id}" style="font-size:13px;cursor:pointer">Yes, I confirm this</label>
      </div>`;
      hintHtml  = c.criteria_value ? `<div class="hint-txt">Requirement: ${c.criteria_value}</div>` : '';
    }

    div.innerHTML = `<label>${c.criteria_label}${req}</label>${inputHtml}${hintHtml}`;
    container.appendChild(div);
  });

  document.getElementById('ccModal').classList.add('open');
}

function closeModal() {
  document.getElementById('ccModal').classList.remove('open');
  _activeDriveId  = null;
  _activeCriteria = [];
}

// ── Step 5: Validate + submit modal ──────────────────────────
function submitModal() {
  const container = document.getElementById('modalFields');
  let valid = true;
  const answers = [];

  _activeCriteria.forEach(c => {
    const fieldDiv = document.getElementById('mf-' + c.criteria_id);
    fieldDiv.classList.remove('invalid');

    let response = '';
    if (c.criteria_type === 'checkbox') {
      const chk = fieldDiv.querySelector('input[type=checkbox]');
      response  = chk && chk.checked ? 'yes' : 'no';
    } else {
      const el = fieldDiv.querySelector('[data-cid]');
      response = el ? el.value.trim() : '';
    }

    // Mandatory validation
    if (c.is_mandatory && (response === '' || response === 'no')) {
      fieldDiv.classList.add('invalid');
      valid = false;
    }

    answers.push({ criteria_id: c.criteria_id, response: response });
  });

  if (!valid) return; // stop — user sees red highlights

  // Disable button + show loading
  document.getElementById('modalSubmitBtn').disabled = true;
  document.getElementById('modalLoading').style.display = 'block';

  doApply(_activeDriveId, answers);
}

// ── Core AJAX apply call ──────────────────────────────────────
function doApply(driveId, answers) {
  const fd = new FormData();
  fd.append('csrf_token',    CSRF_TOKEN);
  fd.append('drive_id',      driveId);
  fd.append('custom_answers', JSON.stringify(answers));

  fetch('apply_drive.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(result => {
      closeModal();
      if (result.success) {
        showResult(driveId, true, result.message);
        // Swap Apply button to Applied
        const btn = document.getElementById('applybtn-' + driveId);
        if (btn) {
          btn.textContent = '✅ Applied';
          btn.className   = 'btn-applied';
          btn.disabled    = true;
          btn.onclick     = null;
        }
      } else {
        showResult(driveId, false, result.failures || ['Application failed.']);
      }
    })
    .catch(() => {
      closeModal();
      showResult(driveId, false, ['Network error. Please try again.']);
    });
}

// ── Show result box inside the drive card ─────────────────────
function showResult(driveId, success, data) {
  const el = document.getElementById('result-' + driveId);
  if (!el) return;

  if (success) {
    el.className = 'apply-result ok';
    el.innerHTML = '✅ Applied Successfully! ' + data;
  } else {
    el.className = 'apply-result fail';
    const lines  = Array.isArray(data) ? data : [data];
    el.innerHTML = '❌ Not Eligible:<br>• ' + lines.join('<br>• ');
  }

  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Close modal on backdrop click ─────────────────────────────
document.getElementById('ccModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body></html>
