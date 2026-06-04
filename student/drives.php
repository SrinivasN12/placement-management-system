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

// ── Preplacement evaluation gate ───────────────────────────────
// Student MUST have an actual record in preplacement_evaluation
// (i.e. officer must have entered marks). No record = blocked.
$pe = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT overall_status FROM preplacement_evaluation WHERE student_id=$student_id"));
// Only allow if record EXISTS and overall_status is explicitly 'Ready'
$ppReady = ($pe !== false && ($pe['overall_status'] ?? '') === 'Ready');

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
<?php
$pageTitle  = 'Placement Drives';
$activePage = 'drives';
require '_layout.php';
?>
<style>
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:18px}
.drive-card{background:var(--surface);border-radius:var(--card-radius);padding:22px 24px;box-shadow:var(--card-shadow);border-left:4px solid var(--primary-light);position:relative;transition:transform var(--transition),box-shadow var(--transition)}
.drive-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,35,126,.14)}
.drive-card.closed{border-left-color:#bbb;opacity:.85}
.drive-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:10px}
.drive-header-left{display:flex;align-items:flex-start;gap:12px}
.company-avatar{width:42px;height:42px;border-radius:10px;background:var(--accent-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.job-title{font-size:15px;font-weight:700;color:var(--text)}
.company{font-size:13px;color:var(--muted);margin-top:2px}
.badge-open{background:#e3f2fd;color:#1565c0;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-soon{background:var(--warn-bg);color:var(--warn);padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-closed{background:#f5f5f5;color:#888;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin:10px 0;font-size:12px;color:var(--muted)}
.meta-grid .key{font-weight:600;color:var(--text)}
.last-date{font-size:12px;font-weight:600;margin-top:8px;padding:6px 10px;border-radius:6px;display:inline-block}
.last-date.normal{background:var(--accent-soft);color:var(--primary)}
.last-date.urgent{background:var(--danger-bg);color:var(--danger);animation:pulse .8s infinite alternate}
@keyframes pulse{from{opacity:1}to{opacity:.7}}
.actions{display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap}
.elig-badge{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600}
.eligible{background:var(--success-bg);color:var(--success)}
.not-eligible{background:var(--danger-bg);color:var(--danger)}
.btn-apply{background:var(--primary);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:background var(--transition)}
.btn-apply:hover{background:var(--primary-light)}
.btn-applied{background:var(--success-bg);color:var(--success);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:default;font-family:inherit}
.btn-closed{background:#f5f5f5;color:#888;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:default;font-family:inherit}
.btn-mock-test{background:linear-gradient(135deg,#185FA5,#1a73e8);color:#fff;border:none;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:opacity .2s;font-family:inherit}
.btn-mock-test:hover{opacity:.88}
.desc{font-size:13px;color:var(--muted);margin:8px 0;line-height:1.5}
.empty{text-align:center;padding:60px;background:var(--surface);border-radius:var(--card-radius);box-shadow:var(--card-shadow);color:var(--muted)}
.apply-result{margin-top:10px;font-size:13px;line-height:1.6;border-radius:8px;padding:0}
.apply-result.ok{background:var(--success-bg);color:var(--success);border:1px solid #a5d6a7;padding:10px 14px}
.apply-result.fail{background:var(--danger-bg);color:var(--danger);border:1px solid #ef9a9a;padding:10px 14px}

/* ── Modal ──────────────────────────────────────────────────── */
#ccModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center;padding:24px 16px;overflow-y:auto}
#ccModal.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:0;max-width:520px;width:100%;max-height:calc(100vh - 48px);overflow:hidden;display:flex;flex-direction:column;box-shadow:0 16px 56px rgba(0,0,0,.32);animation:modalSlideIn .25s cubic-bezier(.4,0,.2,1)}
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
@media(max-width:640px){.drive-grid{grid-template-columns:1fr}.meta-grid{grid-template-columns:1fr}.actions{flex-direction:column;align-items:flex-start}}
.hidden{display:none}
</style>
<div class="page-header">
  <h1>🏢 Placement Drives</h1>
  <p>All active MBA placement opportunities. Check eligibility and apply before the last date.</p>
</div>

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
    <div class="drive-header-left">
      <div class="company-avatar"><?= strtoupper(substr($d['company_name'],0,1)) ?></div>
      <div>
        <div class="job-title"><?= htmlspecialchars($d['job_title']) ?></div>
        <div class="company"><?= htmlspecialchars($d['company_name']) ?></div>
      </div>
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
    <?php elseif (!$ppReady): ?>
      <!-- ── Preplacement gate ── -->
      <span class="elig-badge not-eligible" style="background:#fff3e0;color:#e65100;border:1px solid #ffcc80">
        ⚠ Preplacement Evaluation Pending
      </span>
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
    <?php if ($d['drive_status'] !== 'Closed'): ?>
    <button class="btn-mock-test" onclick="startMockTest(<?= $did ?>,'<?= addslashes(htmlspecialchars($d['company_name'])) ?>','<?= addslashes(htmlspecialchars($d['job_title'])) ?>')">
      🎯 AI Mock Test
    </button>
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
      <div class="drive-header-left">
        <div class="company-avatar" style="opacity:.6"><?= strtoupper(substr($d['company_name'],0,1)) ?></div>
        <div>
          <div class="job-title"><?= htmlspecialchars($d['job_title']) ?></div>
          <div class="company"><?= htmlspecialchars($d['company_name']) ?></div>
        </div>
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

<!-- ════════════════════════════════════════════════════════════
     AI MOCK TEST MODAL
     ════════════════════════════════════════════════════════════ -->
<style>
@keyframes modalSlideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
#mtModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:3000;align-items:center;justify-content:center;padding:24px 16px;overflow-y:auto}
#mtModal.open{display:flex}
.mt-box{background:#fff;border-radius:16px;width:100%;max-width:720px;margin:auto;box-shadow:0 20px 64px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column;animation:modalSlideIn .28s cubic-bezier(.4,0,.2,1)}
.mt-head{background:linear-gradient(135deg,#185FA5,#1a73e8);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
.mt-head h3{font-size:16px;font-weight:700;margin:0}
.mt-head-right{display:flex;align-items:center;gap:14px}
.mt-timer{font-size:18px;font-weight:800;background:rgba(255,255,255,.2);padding:5px 12px;border-radius:8px;font-family:monospace;min-width:72px;text-align:center}
.mt-timer.urgent{background:rgba(200,0,0,.4);animation:tpulse .6s infinite alternate}
@keyframes tpulse{from{opacity:1}to{opacity:.6}}
.mt-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;opacity:.8}
.mt-close:hover{opacity:1}
.mt-body{padding:22px 24px;overflow-y:auto;max-height:calc(100vh - 220px)}
.mt-foot{padding:14px 24px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;background:#fff;flex-shrink:0}

/* Step: difficulty */
.diff-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:8px}
.diff-card{border:2px solid #e0e0e0;border-radius:10px;padding:16px 10px;text-align:center;cursor:pointer;transition:all .2s}
.diff-card:hover{border-color:#185FA5;background:#f0f6ff}
.diff-card.selected{border-color:#185FA5;background:#e8f0fe}
.diff-card .diff-icon{font-size:26px;margin-bottom:6px}
.diff-card .diff-label{font-size:13px;font-weight:700;color:#333}
.diff-card .diff-sub{font-size:11px;color:#888;margin-top:3px}

/* Step: loading */
.mt-loading{text-align:center;padding:40px 20px}
.mt-spinner{width:44px;height:44px;border:4px solid #e0e0e0;border-top-color:#185FA5;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}
@keyframes spin{to{transform:rotate(360deg)}}
.mt-loading p{font-size:14px;color:#555;margin-top:6px}
.mt-loading small{font-size:12px;color:#aaa}

/* Step: questions */
.mt-progress{height:5px;background:#e0e0e0;border-radius:3px;margin-bottom:18px;overflow:hidden}
.mt-progress-bar{height:100%;background:#185FA5;border-radius:3px;transition:width .3s}
.q-block{border:1px solid #e8e8e8;border-radius:10px;padding:16px 18px;margin-bottom:14px;transition:border-color .2s}
.q-block:hover{border-color:#185FA5}
.q-num{font-size:11px;font-weight:700;color:#185FA5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.q-cat-tag{display:inline-block;font-size:10px;background:#e8f0fe;color:#185FA5;padding:2px 7px;border-radius:8px;margin-left:6px;font-weight:600}
.q-text{font-size:14px;font-weight:600;color:#222;line-height:1.5;margin-bottom:12px}
.q-options{display:flex;flex-direction:column;gap:7px}
.q-opt{display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border-radius:8px;border:1.5px solid #e8e8e8;transition:all .15s;user-select:none}
.q-opt:hover{border-color:#185FA5;background:#f0f6ff}
.q-opt input[type=radio]{width:16px;height:16px;accent-color:#185FA5;flex-shrink:0;cursor:pointer;margin:0}
.q-opt.answered{border-color:#185FA5;background:#e8f0fe}
.q-opt label{font-size:13px;color:#333;cursor:pointer;line-height:1.45;flex:1;margin:0}

/* Step: result */
.score-card{text-align:center;padding:20px 10px}
.score-ring{width:110px;height:110px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:28px;font-weight:800;border:6px solid}
.score-ring.pass{border-color:#3B6D11;color:#3B6D11;background:#f0f7e8}
.score-ring.fail{border-color:#A32D2D;color:#A32D2D;background:#fff0f0}
.score-meta{font-size:13px;color:#666;margin-top:4px}
.result-list{margin-top:20px;display:flex;flex-direction:column;gap:10px}
.r-item{border-radius:10px;padding:14px 16px;border:1.5px solid}
.r-item.correct{border-color:#b7dfb0;background:#f4fbf2}
.r-item.wrong{border-color:#f5b8b8;background:#fff5f5}
.r-q{font-size:13px;font-weight:600;color:#333;margin-bottom:6px}
.r-ans{font-size:12px;margin-bottom:4px}
.r-ans.ok{color:#3B6D11;font-weight:600}
.r-ans.bad{color:#A32D2D;font-weight:600}
.r-expl{font-size:12px;color:#555;background:rgba(0,0,0,.04);padding:6px 10px;border-radius:6px;margin-top:6px;line-height:1.5}

/* Leaderboard embed area */
.mt-lb-area{margin-top:16px;border:1px solid #e0e0e0;border-radius:10px;padding:16px;min-height:60px}

/* Buttons */
.btn-mt-primary{background:#185FA5;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-mt-primary:hover{background:#1a4f8a}
.btn-mt-primary:disabled{background:#90a4ae;cursor:not-allowed}
.btn-mt-secondary{background:#f5f5f5;color:#333;border:none;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-mt-secondary:hover{background:#e0e0e0}
.btn-mt-lb{background:#fff;color:#185FA5;border:2px solid #185FA5;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-mt-lb:hover{background:#e8f0fe}

@media(max-width:640px){
  #mtModal,#ccModal{padding:12px 8px;align-items:flex-start}
  .mt-box,.modal-box{margin:auto;max-height:calc(100vh - 24px)}
  .mt-body{padding:16px;max-height:calc(100vh - 200px)}
  .diff-grid{grid-template-columns:1fr}
  .mt-timer{font-size:15px}
}
</style>

<div id="mtModal">
  <div class="mt-box" id="mtBox">
    <!-- HEAD -->
    <div class="mt-head">
      <h3 id="mtTitle">🎯 AI Mock Test</h3>
      <div class="mt-head-right">
        <div class="mt-timer" id="mtTimer" style="display:none">10:00</div>
        <button class="mt-close" onclick="closeMockModal()" title="Close">✕</button>
      </div>
    </div>
    <!-- BODY -->
    <div class="mt-body" id="mtBody">
      <!-- Step content rendered by JS -->
    </div>
    <!-- FOOT -->
    <div class="mt-foot" id="mtFoot"></div>
  </div>
</div>

<script>
// ── Mock Test State ────────────────────────────────────────────
const MT = {
  driveId:    null,
  company:    '',
  role:       '',
  difficulty: 'medium',
  testId:     null,
  questions:  [],
  answers:    {},
  startedAt:  null,
  timerEl:    null,
  timerInterval: null,
  DURATION:   20 * 30, // 30 seconds per question × 20 questions = 600s
};

function startMockTest(driveId, company, role) {
  MT.driveId   = driveId;
  MT.company   = company;
  MT.role      = role;
  MT.difficulty= 'medium';
  MT.testId    = null;
  MT.questions = [];
  MT.answers   = {};
  clearInterval(MT.timerInterval);
  document.getElementById('mtTimer').style.display = 'none';
  document.getElementById('mtTitle').textContent   = '🎯 AI Mock Test';
  renderDifficultyStep();
  document.getElementById('mtModal').classList.add('open');
}

function closeMockModal() {
  if (MT.timerInterval) {
    if (MT.testId && !confirm('Close the test? Your progress will be lost.')) return;
    clearInterval(MT.timerInterval);
    MT.timerInterval = null;
  }
  document.getElementById('mtModal').classList.remove('open');
}

// ── Step 1: Difficulty selector ────────────────────────────────
function renderDifficultyStep() {
  document.getElementById('mtTimer').style.display = 'none';
  document.getElementById('mtBody').innerHTML = `
    <p style="font-size:14px;color:#555;margin-bottom:16px">
      <strong>${MT.company}</strong> — ${MT.role}<br>
      <span style="font-size:12px;color:#888">Select difficulty and we'll generate 10 personalised MCQs using AI.</span>
    </p>
    <div class="diff-grid">
      ${['easy','medium','hard'].map(d => `
        <div class="diff-card ${d==='medium'?'selected':''}" id="dc-${d}" onclick="selectDiff('${d}')">
          <div class="diff-icon">${d==='easy'?'🌱':d==='medium'?'⚡':'🔥'}</div>
          <div class="diff-label">${d.charAt(0).toUpperCase()+d.slice(1)}</div>
          <div class="diff-sub">${d==='easy'?'Foundational concepts':d==='medium'?'Balanced challenge':'Advanced & tricky'}</div>
        </div>`).join('')}
    </div>`;
  document.getElementById('mtFoot').innerHTML = `
    <button class="btn-mt-secondary" onclick="closeMockModal()">Cancel</button>
    <button class="btn-mt-primary" id="btnGenerate" onclick="generateTest()">Generate Test →</button>`;
}

function selectDiff(d) {
  MT.difficulty = d;
  ['easy','medium','hard'].forEach(x => {
    const el = document.getElementById('dc-'+x);
    if (el) el.classList.toggle('selected', x === d);
  });
}

// ── Step 2: Generate (loading) ─────────────────────────────────
function generateTest() {
  document.getElementById('mtBody').innerHTML = `
    <div class="mt-loading">
      <div class="mt-spinner"></div>
      <p>AI is crafting your personalised test…</p>
      <small>This may take up to 20 seconds on first load.</small>
    </div>`;
  document.getElementById('mtFoot').innerHTML = '';

  const fd = new FormData();
  fd.append('drive_id',   MT.driveId);
  fd.append('difficulty', MT.difficulty);

  fetch('generate_mock_test.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.error) { showMtError(data.error); return; }
      MT.testId    = data.mock_test_id;
      MT.questions = data.questions;
      MT.answers   = {};
      renderQuestionsStep();
    })
    .catch(() => showMtError('Network error. Please check your connection and try again.'));
}

// ── Step 3: Questions + timer ──────────────────────────────────
function renderQuestionsStep() {
  document.getElementById('mtTitle').textContent = `🎯 ${MT.difficulty.charAt(0).toUpperCase()+MT.difficulty.slice(1)} Test`;
  const timerEl = document.getElementById('mtTimer');
  timerEl.style.display = 'block';

  MT.startedAt = Math.floor(Date.now()/1000);
  let remaining = MT.DURATION;
  clearInterval(MT.timerInterval);

  function tick() {
    remaining--;
    if (remaining < 0) { clearInterval(MT.timerInterval); submitTest(true); return; }
    const m = String(Math.floor(remaining/60)).padStart(2,'0');
    const s = String(remaining%60).padStart(2,'0');
    timerEl.textContent = `${m}:${s}`;
    timerEl.classList.toggle('urgent', remaining <= 120);
  }
  tick();
  MT.timerInterval = setInterval(tick, 1000);

  let qHtml = '<div class="mt-progress"><div class="mt-progress-bar" id="mtProg" style="width:0%"></div></div>';
  MT.questions.forEach((q, i) => {
    const opts = q.options.map((opt, oi) =>
      `<div class="q-opt ${MT.answers[i]===oi?'answered':''}" id="opt-${i}-${oi}" onclick="selectAnswer(${i},${oi})">
        <input type="radio" name="q${i}" id="r${i}-${oi}" value="${oi}" ${MT.answers[i]===oi?'checked':''} onclick="event.stopPropagation();selectAnswer(${i},${oi})">
        <label for="r${i}-${oi}" style="pointer-events:none">${opt}</label>
      </div>`).join('');
    qHtml += `
      <div class="q-block" id="qblock-${i}">
        <div class="q-num">Q${i+1} <span class="q-cat-tag">${q.category||''}</span></div>
        <div class="q-text">${q.question}</div>
        <div class="q-options">${opts}</div>
      </div>`;
  });

  document.getElementById('mtBody').innerHTML = qHtml;
  document.getElementById('mtFoot').innerHTML = `
    <span id="mtAnsCount" style="font-size:12px;color:#888;align-self:center">0/${MT.questions.length} answered</span>
    <button class="btn-mt-secondary" onclick="closeMockModal()">Cancel</button>
    <button class="btn-mt-primary" onclick="submitTest(false)">Submit Test ✓</button>`;
}

function selectAnswer(qi, oi) {
  MT.answers[qi] = oi;
  // Highlight selected option, clear siblings
  MT.questions[qi].options.forEach((_, x) => {
    const el = document.getElementById(`opt-${qi}-${x}`);
    if (el) el.classList.toggle('answered', x === oi);
  });
  // Update radio
  const radio = document.getElementById(`r${qi}-${oi}`);
  if (radio) radio.checked = true;
  // Progress bar
  const done = Object.keys(MT.answers).length;
  const pct  = Math.round((done / MT.questions.length) * 100);
  const pb   = document.getElementById('mtProg');
  if (pb) pb.style.width = pct + '%';
  const cnt = document.getElementById('mtAnsCount');
  if (cnt) cnt.textContent = `${done}/${MT.questions.length} answered`;
}

// ── Step 4: Submit ─────────────────────────────────────────────
function submitTest(autoSubmit) {
  clearInterval(MT.timerInterval);
  MT.timerInterval = null;
  document.getElementById('mtTimer').style.display = 'none';

  const unanswered = MT.questions.length - Object.keys(MT.answers).length;
  if (!autoSubmit && unanswered > 0) {
    if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) {
      // Re-start timer with remaining time is lost — restart guard
      renderQuestionsStep();
      return;
    }
  }

  document.getElementById('mtBody').innerHTML = `
    <div class="mt-loading">
      <div class="mt-spinner"></div>
      <p>Grading your answers…</p>
    </div>`;
  document.getElementById('mtFoot').innerHTML = '';

  const elapsed = Math.floor(Date.now()/1000) - MT.startedAt;
  const payload = { mock_test_id: MT.testId, answers: MT.answers, time_taken: Math.min(elapsed, MT.DURATION) };

  fetch('submit_mock_test.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(data => {
      if (data.error) { showMtError(data.error); return; }
      renderScorecard(data);
    })
    .catch(() => showMtError('Network error during submission. Please try again.'));
}

// ── Step 5: Scorecard ──────────────────────────────────────────
function renderScorecard(data) {
  document.getElementById('mtTitle').textContent = '📊 Your Results';
  const pct     = data.percentage;
  const passed  = pct >= 60;
  const mins    = String(Math.floor(data.time_taken/60)).padStart(2,'0');
  const secs    = String(data.time_taken%60).padStart(2,'0');

  if (data.duplicate) {
    document.getElementById('mtBody').innerHTML = `
      <div class="score-card">
        <p style="font-size:14px;color:#BA7517;margin-bottom:8px">⚠️ You already submitted this test.</p>
        <p style="font-size:13px;color:#555">Score: <strong>${data.score}/${data.total}</strong> (${data.percentage}%)</p>
      </div>`;
    document.getElementById('mtFoot').innerHTML = `<button class="btn-mt-secondary" onclick="closeMockModal()">Close</button>`;
    return;
  }

  let reviewHtml = '';
  (data.results||[]).forEach((r, i) => {
    const yourOpt   = r.your_answer >= 0 ? r.options[r.your_answer] : 'Not answered';
    const corrOpt   = r.options[r.correct_answer] || '';
    reviewHtml += `
      <div class="r-item ${r.is_correct?'correct':'wrong'}">
        <div class="r-q">Q${i+1}: ${r.question}</div>
        <div class="r-ans ${r.is_correct?'ok':'bad'}">Your answer: ${yourOpt} ${r.is_correct?'✅':'❌'}</div>
        ${!r.is_correct?`<div class="r-ans ok">Correct: ${corrOpt}</div>`:''}
        ${r.explanation?`<div class="r-expl">💡 ${r.explanation}</div>`:''}
      </div>`;
  });

  document.getElementById('mtBody').innerHTML = `
    <div class="score-card">
      <div class="score-ring ${passed?'pass':'fail'}">${pct}%</div>
      <div style="font-size:20px;font-weight:700;color:${passed?'#3B6D11':'#A32D2D'}">${passed?'🎉 Well Done!':'💪 Keep Practising'}</div>
      <div class="score-meta">${data.score} / ${data.total} correct &nbsp;•&nbsp; Time: ${mins}:${secs}</div>
    </div>
    <div class="result-list">${reviewHtml}</div>
    <div id="lbArea" class="mt-lb-area" style="display:none">
      <div style="font-size:13px;color:#888;text-align:center">Loading leaderboard…</div>
    </div>`;

  document.getElementById('mtFoot').innerHTML = `
    <button class="btn-mt-secondary" onclick="closeMockModal()">Close</button>
    <button class="btn-mt-lb" onclick="loadLeaderboard(${MT.driveId})">🏆 View Leaderboard</button>`;
}

function loadLeaderboard(driveId) {
  const area = document.getElementById('lbArea');
  area.style.display = 'block';
  area.scrollIntoView({behavior:'smooth', block:'nearest'});
  area.innerHTML = '<div style="text-align:center;padding:20px;color:#888;font-size:13px">Loading leaderboard…</div>';
  document.querySelector('.btn-mt-lb').style.display = 'none';

  fetch(`leaderboard_widget.php?drive_id=${driveId}`)
    .then(r => r.text())
    .then(html => { area.innerHTML = html; })
    .catch(() => { area.innerHTML = '<p style="color:#c62828;font-size:13px;text-align:center">Could not load leaderboard.</p>'; });
}

// ── Error helper ───────────────────────────────────────────────
function showMtError(msg) {
  clearInterval(MT.timerInterval);
  document.getElementById('mtTimer').style.display = 'none';
  document.getElementById('mtBody').innerHTML = `
    <div style="text-align:center;padding:30px">
      <div style="font-size:36px;margin-bottom:12px">⚠️</div>
      <p style="font-size:14px;color:#A32D2D;font-weight:600">${msg}</p>
    </div>`;
  document.getElementById('mtFoot').innerHTML = `
    <button class="btn-mt-secondary" onclick="closeMockModal()">Close</button>
    <button class="btn-mt-primary" onclick="renderDifficultyStep()">Try Again</button>`;
}

// Close on backdrop click
document.getElementById('mtModal').addEventListener('click', function(e) {
  if (e.target === this) closeMockModal();
});
</script>
<?php require '_layout_end.php'; ?>
