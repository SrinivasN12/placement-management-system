<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);
$success = $error = "";

// Ensure linkedin column exists (IF NOT EXISTS avoids duplicate-column exception)
@mysqli_query($conn, "ALTER TABLE student ADD COLUMN IF NOT EXISTS linkedin VARCHAR(255) DEFAULT '' AFTER city");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $batch      = trim($_POST['batch']);
    $spec       = trim($_POST['specialization']);
    $cgpa       = (float)$_POST['cgpa'];
    $backlogs   = (int)$_POST['backlogs'];
    $tenth      = (float)$_POST['tenth_percentage'];
    $twelfth    = (float)$_POST['twelfth_percentage'];
    $deg_cgpa   = (float)$_POST['degree_cgpa'];
    $contact    = trim($_POST['contact_number']);
    $country    = trim($_POST['country']);
    $state      = trim($_POST['state']);
    $city       = trim($_POST['city']);
    $new_pass   = $_POST['new_password'];
    $confirm    = $_POST['confirm_password'];

    if ($cgpa < 0 || $cgpa > 10)         { $error = "MBA CGPA must be 0–10."; }
    elseif ($deg_cgpa < 0 || $deg_cgpa > 10) { $error = "Degree CGPA must be 0–10."; }
    elseif ($tenth < 0 || $tenth > 100)  { $error = "10th % must be 0–100."; }
    elseif ($twelfth < 0 || $twelfth > 100) { $error = "12th % must be 0–100."; }
    elseif ($contact && !preg_match('/^\d{10}$/', $contact)) { $error = "Contact must be 10 digits."; }
    elseif ($new_pass && $new_pass !== $confirm) { $error = "Passwords do not match."; }
    else {
        $stmt = mysqli_prepare($conn,
            "UPDATE student SET batch=?,specialization=?,cgpa=?,backlogs=?,
             tenth_percentage=?,twelfth_percentage=?,degree_cgpa=?,
             contact_number=?,country=?,state=?,city=?
             WHERE student_id=?");
        // type: s=batch,s=spec,d=cgpa,i=backlogs,d=10th,d=12th,d=degcgpa,s=contact,s=country,s=state,s=city,i=id
        // 12 variables → 12 type chars: ssdidddssssi
        mysqli_stmt_bind_param($stmt, "ssdidddssssi",
            $batch, $spec, $cgpa, $backlogs,
            $tenth, $twelfth, $deg_cgpa,
            $contact, $country, $state, $city, $student_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $uid  = (int)$_SESSION['user_id'];
            $ps = mysqli_prepare($conn, "UPDATE user SET password=? WHERE user_id=?");
            mysqli_stmt_bind_param($ps, "si", $hash, $uid);
            mysqli_stmt_execute($ps);
            mysqli_stmt_close($ps);
        }
        $success = "Profile updated successfully!";
    }
}

$s = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT s.*, u.email
     FROM student s JOIN user u ON s.user_id=u.user_id
     WHERE s.student_id=$student_id"));
$pre = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM preplacement_evaluation WHERE student_id=$student_id"));

$specializations = mbaSpecializations();
$batches         = mbaBatches();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Profile — MBA PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1a237e;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:860px;margin:0 auto}
h2{color:#1a237e;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:24px 28px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.card h3{font-size:15px;color:#1a237e;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #e8eaf6}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 32px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f5f5f5;font-size:13px;gap:8px}
.info-row:last-child{border-bottom:none}
.lbl{color:#888;white-space:nowrap}.val{color:#333;font-weight:600;text-align:right;word-break:break-word}
.section-title{font-size:11px;font-weight:700;color:#1a237e;text-transform:uppercase;letter-spacing:.6px;margin:18px 0 10px;padding-bottom:5px;border-bottom:2px solid #e8eaf6}
.form-row{display:flex;gap:14px;flex-wrap:wrap}
.form-group{margin-bottom:14px;flex:1;min-width:160px}
label{display:block;font-size:12px;font-weight:600;color:#444;margin-bottom:4px}
input,select{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;outline:none}
input:focus,select:focus{border-color:#1a237e}
.locked{background:#f5f7ff;color:#555;cursor:default}
.btn{background:#1a237e;color:#fff;border:none;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#3949ab}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.back{display:inline-block;margin-bottom:18px;color:#1a237e;text-decoration:none;font-size:13px;font-weight:600}
.badge-r{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
.badge-nr{background:#ffebee;color:#c62828;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
.grade-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
.g-box{flex:1;min-width:80px;background:#f5f7ff;border-radius:10px;padding:12px;text-align:center}
.g-box .score{font-size:22px;font-weight:700;color:#1a237e}.g-box .lbl2{font-size:11px;color:#888;margin-top:3px}
@media(max-width:640px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 14px;}
  .card{padding:18px 16px;}
  .form-row{flex-direction:column;gap:0;}
  .btn{width:100%;}
  .pc-pct{font-size:28px;}
  .grade-row{flex-direction:column;}
  .info-grid{grid-template-columns:1fr;}
}

/* ── Profile Completion Bar ─────────────────────────── */
.progress-card{
  background:linear-gradient(135deg,#1a237e 0%,#283593 60%,#3949ab 100%);
  border-radius:16px;padding:24px 28px;box-shadow:0 6px 24px rgba(26,35,126,.28);
  margin-bottom:22px;position:relative;overflow:hidden;
}
.progress-card::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:160px;height:160px;border-radius:50%;
  background:rgba(255,255,255,.06);pointer-events:none;
}
.progress-card::after{
  content:'';position:absolute;bottom:-50px;right:60px;
  width:110px;height:110px;border-radius:50%;
  background:rgba(255,255,255,.04);pointer-events:none;
}
.pc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.pc-title{color:#fff;font-size:15px;font-weight:700;letter-spacing:.3px}
.pc-subtitle{color:rgba(255,255,255,.65);font-size:12px;margin-top:3px}
.pc-pct{
  font-size:38px;font-weight:800;color:#fff;line-height:1;
  text-shadow:0 2px 8px rgba(0,0,0,.25);
}
.pc-pct span{font-size:16px;font-weight:500;opacity:.8}
.bar-track{
  background:rgba(255,255,255,.18);border-radius:99px;
  height:12px;overflow:hidden;margin-bottom:14px;
}
.bar-fill{
  height:100%;border-radius:99px;
  background:linear-gradient(90deg,#69f0ae,#00e5ff);
  width:0%;transition:width 1.1s cubic-bezier(.4,0,.2,1);
  box-shadow:0 0 12px rgba(105,240,174,.5);
}
.bar-fill.amber{
  background:linear-gradient(90deg,#ffd740,#ffab40);
  box-shadow:0 0 12px rgba(255,215,64,.45);
}
.bar-fill.red{
  background:linear-gradient(90deg,#ff6e6e,#ff8a65);
  box-shadow:0 0 12px rgba(255,110,110,.45);
}
.pc-chips{display:flex;flex-wrap:wrap;gap:7px}
.chip{
  font-size:11px;font-weight:600;padding:4px 11px;
  border-radius:99px;border:1.5px solid rgba(255,255,255,.25);
  color:rgba(255,255,255,.9);cursor:default;
  transition:background .2s;
}
.chip.done{background:rgba(105,240,174,.18);border-color:rgba(105,240,174,.5);color:#69f0ae}
.chip.miss{background:rgba(255,110,110,.18);border-color:rgba(255,110,110,.5);color:#ff8a80}
.pc-cta{
  display:inline-block;margin-top:14px;font-size:12px;font-weight:700;
  color:#fff;text-decoration:none;background:rgba(255,255,255,.15);
  border:1.5px solid rgba(255,255,255,.3);padding:7px 18px;
  border-radius:99px;transition:background .2s;
}
.pc-cta:hover{background:rgba(255,255,255,.28)}
.pc-complete-msg{
  font-size:12px;font-weight:600;color:#69f0ae;
  margin-top:14px;display:none;
  animation:fadein .5s ease;
}
@keyframes fadein{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
</style>
</head><body>
<div class="navbar"><h1>🎓 PMS — Student Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>👤 My Profile</h2>
<p class="sub">View and update your academic details.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── Profile Completion Progress Bar ────────────── -->
<div class="progress-card" id="progressCard">
  <div class="pc-header">
    <div>
      <div class="pc-title">📋 Profile Completion</div>
      <div class="pc-subtitle">Complete your profile to boost placement eligibility</div>
    </div>
    <div class="pc-pct" id="pcPct">0<span>%</span></div>
  </div>
  <div class="bar-track"><div class="bar-fill" id="barFill"></div></div>
  <div class="pc-chips" id="pcChips"></div>
  <a class="pc-cta" href="#editCard">✏️ Complete Profile</a>
</div>

<?php
// Emit field values for JS (safely encoded)
$pc_fields = [
  'batch'             => $s['batch']              ?? '',
  'specialization'    => $s['specialization']     ?? '',
  'cgpa'              => $s['cgpa']               ?? '',
  'tenth_percentage'  => $s['tenth_percentage']   ?? '',
  'twelfth_percentage'=> $s['twelfth_percentage'] ?? '',
  'degree_cgpa'       => $s['degree_cgpa']        ?? '',
  'backlogs'          => $s['backlogs']           ?? '',
  'contact_number'    => $s['contact_number']     ?? '',
  'state'             => $s['state']              ?? '',
  'city'              => $s['city']               ?? '',
];
?>
<script>
const profileFields = <?= json_encode($pc_fields) ?>;
</script>

<!-- VIEW CARD -->
<div class="card">
  <h3>Account Information</h3>
  <div class="info-grid">
    <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($s['name']) ?></span></div>
    <div class="info-row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($s['email']) ?></span></div>
    <div class="info-row"><span class="lbl">Department</span><span class="val">MBA</span></div>
    <div class="info-row"><span class="lbl">Batch</span><span class="val"><?= htmlspecialchars($s['batch'] ?? '—') ?></span></div>
    <div class="info-row"><span class="lbl">Specialization</span><span class="val"><?= htmlspecialchars($s['specialization'] ?? '—') ?></span></div>
    <div class="info-row"><span class="lbl">Contact</span><span class="val"><?= htmlspecialchars($s['contact_number'] ?? '—') ?></span></div>
    <div class="info-row"><span class="lbl">City / State</span><span class="val"><?php
      $cityState = array_filter([trim($s['city'] ?? ''), trim($s['state'] ?? '')]);
      echo htmlspecialchars($cityState ? implode(', ', $cityState) : '—');
    ?></span></div>
    <div class="info-row"><span class="lbl">Country</span><span class="val"><?= htmlspecialchars(trim($s['country'] ?? '') ?: '—') ?></span></div>
    <div class="info-row"><span class="lbl">LinkedIn</span><span class="val"><?php
      $li = trim($s['linkedin'] ?? '');
      echo $li ? '<a href="' . htmlspecialchars($li) . '" target="_blank" style="color:#1a237e;word-break:break-all">' . htmlspecialchars($li) . '</a>' : '—';
    ?></span></div>
  </div>
  <div class="section-title" style="margin-top:16px">Academic Background</div>
  <div class="info-grid">
    <div class="info-row"><span class="lbl">10th Percentage</span><span class="val"><?= $s['tenth_percentage'] ?? '—' ?>%</span></div>
    <div class="info-row"><span class="lbl">12th Percentage</span><span class="val"><?= $s['twelfth_percentage'] ?? '—' ?>%</span></div>
    <div class="info-row"><span class="lbl">Degree CGPA</span><span class="val"><?= $s['degree_cgpa'] ?? '—' ?></span></div>
    <div class="info-row"><span class="lbl">MBA CGPA (current)</span><span class="val"><?= $s['cgpa'] ?></span></div>
    <div class="info-row"><span class="lbl">Active Backlogs</span><span class="val"><?= $s['backlogs'] ?></span></div>
    <div class="info-row"><span class="lbl">Pre-Placement Status</span><span class="val">
      <?= $s['preplacement_status'] === 'Ready'
          ? '<span class="badge-r">✅ Ready</span>'
          : '<span class="badge-nr">⚠ Not Ready</span>' ?>
    </span></div>
  </div>
</div>

<?php if ($pre): ?>
<div class="card">
  <h3>📊 Pre-Placement Scores</h3>
  <div class="grade-row">
    <div class="g-box"><div class="score"><?= $pre['aptitude_marks'] ?></div><div class="lbl2">Aptitude (<?= $pre['aptitude_grade'] ?>)</div></div>
    <div class="g-box"><div class="score"><?= $pre['gd_marks'] ?></div><div class="lbl2">GD (<?= $pre['gd_grade'] ?>)</div></div>
    <div class="g-box"><div class="score"><?= $pre['english_marks'] ?></div><div class="lbl2">English (<?= $pre['english_grade'] ?>)</div></div>
  </div>
  <p style="margin-top:14px;font-size:13px">Overall:
    <?= $pre['overall_status'] === 'Ready'
        ? '<span class="badge-r">✅ Placement Ready</span>'
        : '<span class="badge-nr">⚠ Not Ready — need ≥50 in all areas</span>' ?>
  </p>
</div>
<?php endif; ?>

<!-- ── Resume Upload Block ────────────────────────────── -->
<?php include 'resume_upload_block.php'; ?>

<!-- EDIT CARD -->
<div class="card" id="editCard">
  <h3>Edit Profile</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="section-title">MBA Programme</div>
    <div class="form-row">
      <div class="form-group">
        <label>Department</label>
        <input type="text" value="MBA" class="locked" readonly>
      </div>
      <div class="form-group">
        <label>Batch</label>
        <select name="batch" required>
          <?php foreach ($batches as $b): ?>
          <option value="<?= $b ?>" <?= ($s['batch'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Specialization</label>
        <select name="specialization" required>
          <?php foreach ($specializations as $sp): ?>
          <option value="<?= $sp ?>" <?= ($s['specialization'] ?? '') === $sp ? 'selected' : '' ?>><?= $sp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>MBA CGPA</label>
        <input type="number" name="cgpa" step="0.01" min="0" max="10" value="<?= $s['cgpa'] ?>">
      </div>
      <div class="form-group">
        <label>Active Backlogs</label>
        <input type="number" name="backlogs" min="0" value="<?= $s['backlogs'] ?>">
      </div>
    </div>

    <div class="section-title">Academic Background</div>
    <div class="form-row">
      <div class="form-group">
        <label>10th Percentage</label>
        <input type="number" name="tenth_percentage" step="0.01" min="0" max="100" value="<?= $s['tenth_percentage'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>12th Percentage</label>
        <input type="number" name="twelfth_percentage" step="0.01" min="0" max="100" value="<?= $s['twelfth_percentage'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>Degree CGPA</label>
        <input type="number" name="degree_cgpa" step="0.01" min="0" max="10" value="<?= $s['degree_cgpa'] ?? '' ?>">
      </div>
    </div>

    <div class="section-title">Contact & Location</div>
    <div class="form-row">
      <div class="form-group">
        <label>Contact Number (10 digits)</label>
        <input type="tel" name="contact_number" pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($s['contact_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Country</label>
        <input type="text" name="country" value="<?= htmlspecialchars($s['country'] ?? '') ?>" placeholder="India">
      </div>
      <div class="form-group">
        <label>State</label>
        <input type="text" name="state" value="<?= htmlspecialchars($s['state'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city" value="<?= htmlspecialchars($s['city'] ?? '') ?>">
      </div>
    </div>

    <div class="section-title">Change Password</div>
    <div class="form-row">
      <div class="form-group">
        <label>New Password <span style="font-weight:400;color:#aaa">(leave blank to keep)</span></label>
        <input type="password" name="new_password" placeholder="New password">
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Repeat password">
      </div>
    </div>
    <button type="submit" class="btn">Save Changes</button>
  </form>
</div>
</div>

<script>
(function(){
  const labels = {
    batch:              'Batch',
    specialization:     'Specialization',
    cgpa:               'MBA CGPA',
    tenth_percentage:   '10th %',
    twelfth_percentage: '12th %',
    degree_cgpa:        'Degree CGPA',
    backlogs:           'Backlogs',
    contact_number:     'Contact No.',
    state:              'State',
    city:               'City',
  };

  const keys   = Object.keys(labels);
  const total  = keys.length;
  let   filled = 0;
  const chipsEl= document.getElementById('pcChips');

  keys.forEach(k => {
    const val  = profileFields[k];
    const done = val !== null && val !== undefined && String(val).trim() !== '' && String(val).trim() !== '0';
    // treat backlogs=0 as filled (zero backlogs is valid data)
    const isBL = k === 'backlogs';
    const isDone = isBL ? (val !== null && val !== undefined && String(val).trim() !== '') : done;
    if(isDone) filled++;

    const chip = document.createElement('span');
    chip.className = 'chip ' + (isDone ? 'done' : 'miss');
    chip.title     = isDone ? labels[k] + ' ✓' : labels[k] + ' — not filled';
    chip.textContent = (isDone ? '✔ ' : '✖ ') + labels[k];
    chipsEl.appendChild(chip);
  });

  const pct    = Math.round((filled / total) * 100);
  const fill   = document.getElementById('barFill');
  const pctEl  = document.getElementById('pcPct');

  // Colour tier
  if(pct < 40)       fill.classList.add('red');
  else if(pct < 75)  fill.classList.add('amber');
  // else green (default)

  // Animate count-up
  let cur = 0;
  const step = Math.max(1, Math.round(pct / 40));
  const timer = setInterval(() => {
    cur = Math.min(cur + step, pct);
    pctEl.innerHTML = cur + '<span>%</span>';
    if(cur >= pct) clearInterval(timer);
  }, 28);

  // Animate bar (slight delay so CSS transition triggers)
  setTimeout(() => { fill.style.width = pct + '%'; }, 80);

})();
</script>
</body></html>
