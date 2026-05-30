<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/db_helpers.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);
$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf();
  $uucms=$spec=$batch=$contact=$country=$state=$city=$new_pass=$confirm="";
  $cgpa=$backlogs=$tenth=$twelfth=$deg_cgpa=0;
  $uucms    = strtoupper(trim($_POST['uucms_number']));
  $batch    = trim($_POST['batch']);
  $spec     = trim($_POST['specialization']);
  $cgpa     = (float)$_POST['cgpa'];
  $backlogs = (int)$_POST['backlogs'];
  $tenth    = (float)$_POST['tenth_percentage'];
  $twelfth  = (float)$_POST['twelfth_percentage'];
  $deg_cgpa = (float)$_POST['degree_cgpa'];
  $contact  = trim($_POST['contact_number']);
  $country  = trim($_POST['country']);
  $state    = trim($_POST['state']);
  $city     = trim($_POST['city']);
  $new_pass = $_POST['new_password'];
  $confirm  = $_POST['confirm_password'];

  if (empty($uucms)) {
    $error = "UUCMS number is required.";
  } elseif (!preg_match('/^[A-Z0-9\-\/]+$/', $uucms)) {
    $error = "UUCMS number may only contain letters, digits, hyphens, and slashes.";
  } else {
    $chku = mysqli_prepare($conn, "SELECT student_id FROM student WHERE uucms_number=? AND student_id!=?");
    mysqli_stmt_bind_param($chku, "si", $uucms, $student_id);
    mysqli_stmt_execute($chku); mysqli_stmt_store_result($chku);
    if (mysqli_stmt_num_rows($chku) > 0) $error = "This UUCMS number is already used by another student.";
    mysqli_stmt_close($chku);
  }
  if (!$error && ($cgpa<0||$cgpa>10))        $error="MBA CGPA must be 0-10.";
  elseif(!$error && ($deg_cgpa<0||$deg_cgpa>10)) $error="Degree CGPA must be 0-10.";
  elseif(!$error && ($tenth<0||$tenth>100))   $error="10th % must be 0-100.";
  elseif(!$error && ($twelfth<0||$twelfth>100)) $error="12th % must be 0-100.";
  elseif(!$error && $contact && !preg_match('/^\d{10}$/',$contact)) $error="Contact must be 10 digits.";
  elseif(!$error && $new_pass && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pass)) $error="Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.";
  elseif(!$error && $new_pass && $new_pass!==$confirm) $error="Passwords do not match.";



  if (!$error) {
    $stmt=mysqli_prepare($conn,"UPDATE student SET uucms_number=?,batch=?,specialization=?,cgpa=?,backlogs=?,tenth_percentage=?,twelfth_percentage=?,degree_cgpa=?,contact_number=?,country=?,state=?,city=? WHERE student_id=?");
    mysqli_stmt_bind_param($stmt,"sssdidddssssi",$uucms,$batch,$spec,$cgpa,$backlogs,$tenth,$twelfth,$deg_cgpa,$contact,$country,$state,$city,$student_id);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    if ($new_pass) {
      $hash=password_hash($new_pass,PASSWORD_DEFAULT); $uid=(int)$_SESSION['user_id'];
      $ps=mysqli_prepare($conn,"UPDATE user SET password=? WHERE user_id=?");
      mysqli_stmt_bind_param($ps,"si",$hash,$uid); mysqli_stmt_execute($ps); mysqli_stmt_close($ps);
    }
    $success="Profile updated successfully!";
  }
}

$s=mysqli_fetch_assoc(mysqli_query($conn,"SELECT s.*, u.email FROM student s JOIN user u ON s.user_id=u.user_id WHERE s.student_id=$student_id"));
$pre=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM preplacement_evaluation WHERE student_id=$student_id"));
$specializations=mbaSpecializations();
$batches=mbaBatches();

$pageTitle='My Profile'; $activePage='profile';
require '_layout.php';
?>
<style>
.progress-card{background:linear-gradient(135deg,#1a237e 0%,#283593 60%,#3949ab 100%);border-radius:16px;padding:24px 28px;box-shadow:0 6px 24px rgba(26,35,126,.28);margin-bottom:22px;position:relative;overflow:hidden}
.progress-card::before{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
.pc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.pc-title{color:#fff;font-size:15px;font-weight:700}.pc-subtitle{color:rgba(255,255,255,.65);font-size:12px;margin-top:3px}
.pc-pct{font-size:38px;font-weight:800;color:#fff;line-height:1;text-shadow:0 2px 8px rgba(0,0,0,.25)}
.pc-pct span{font-size:16px;font-weight:500;opacity:.8}
.bar-track{background:rgba(255,255,255,.18);border-radius:99px;height:12px;overflow:hidden;margin-bottom:14px}
.bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#69f0ae,#00e5ff);width:0%;transition:width 1.1s cubic-bezier(.4,0,.2,1);box-shadow:0 0 12px rgba(105,240,174,.5)}
.bar-fill.amber{background:linear-gradient(90deg,#ffd740,#ffab40)}.bar-fill.red{background:linear-gradient(90deg,#ff6e6e,#ff8a65)}
.pc-chips{display:flex;flex-wrap:wrap;gap:7px}
.chip{font-size:11px;font-weight:600;padding:4px 11px;border-radius:99px;border:1.5px solid rgba(255,255,255,.25);color:rgba(255,255,255,.9);cursor:default}
.chip.done{background:rgba(105,240,174,.18);border-color:rgba(105,240,174,.5);color:#69f0ae}
.chip.miss{background:rgba(255,110,110,.18);border-color:rgba(255,110,110,.5);color:#ff8a80}
.pc-cta{display:inline-block;margin-top:14px;font-size:12px;font-weight:700;color:#fff;text-decoration:none;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);padding:7px 18px;border-radius:99px;transition:background .2s}
.pc-cta:hover{background:rgba(255,255,255,.28)}
.uucms-badge{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:10px 14px;background:linear-gradient(135deg,#e8eaf6,#f5f7ff);border-radius:10px;border-left:4px solid #3949ab}
.uucms-val{font-family:'Courier New',monospace;font-weight:700;font-size:15px;letter-spacing:1.2px;color:#1a237e;text-transform:uppercase}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 32px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f5f5f5;font-size:13px;gap:8px}
.info-row:last-child{border-bottom:none}
.lbl{color:var(--muted);white-space:nowrap;font-size:12px}.val{color:var(--text);font-weight:600;text-align:right;word-break:break-word}
.section-title{font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.6px;margin:18px 0 10px;padding-bottom:5px;border-bottom:2px solid var(--accent-soft)}
.grade-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
.g-box{flex:1;min-width:80px;background:var(--bg);border-radius:10px;padding:12px;text-align:center;border:1px solid var(--border)}
.g-box .score{font-size:22px;font-weight:700;color:var(--primary)}.g-box .lbl2{font-size:11px;color:var(--muted);margin-top:3px}
.form-row{display:flex;gap:14px;flex-wrap:wrap}
.form-group{margin-bottom:14px;flex:1;min-width:160px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#444;margin-bottom:4px}
.form-group input,.form-group select{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;font-family:inherit;color:var(--text);transition:border-color .2s}
.form-group input:focus,.form-group select:focus{border-color:var(--primary)}
.locked{background:#f5f7ff;color:#555;cursor:default}
.badge-r{background:var(--success-bg);color:var(--success);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
.badge-nr{background:var(--danger-bg);color:var(--danger);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}.form-row{flex-direction:column;gap:0}}
.req-list{margin-top:7px;list-style:none;padding-left:0}
.req-list li{font-size:11px;color:#b0b8c8;display:flex;align-items:center;gap:5px;margin-bottom:3px;transition:color .2s}
.req-list li.ok{color:#15803d}
.req-list li::before{content:'✗';font-size:10px}
.req-list li.ok::before{content:'✓'}
</style>

<div class="page-header"><h1>👤 My Profile</h1><p>View and update your academic details.</p></div>

<?php if($success): ?><div class="alert alert-success"><span class="material-icons-round">check_circle</span><?=$success?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-error"><span class="material-icons-round">error</span><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="progress-card">
  <div class="pc-header">
    <div><div class="pc-title">📋 Profile Completion</div><div class="pc-subtitle">Complete your profile to boost placement eligibility</div></div>
    <div class="pc-pct" id="pcPct">0<span>%</span></div>
  </div>
  <div class="bar-track"><div class="bar-fill" id="barFill"></div></div>
  <div class="pc-chips" id="pcChips"></div>
  <a class="pc-cta" href="#editCard">✏️ Complete Profile</a>
</div>
<?php $pc_fields=['uucms_number'=>$s['uucms_number']??'','batch'=>$s['batch']??'','specialization'=>$s['specialization']??'','cgpa'=>$s['cgpa']??'','tenth_percentage'=>$s['tenth_percentage']??'','twelfth_percentage'=>$s['twelfth_percentage']??'','degree_cgpa'=>$s['degree_cgpa']??'','backlogs'=>$s['backlogs']??'','contact_number'=>$s['contact_number']??'','state'=>$s['state']??'','city'=>$s['city']??'']; ?>
<script>const profileFields=<?=json_encode($pc_fields)?>;</script>

<div class="card">
  <div class="card-title"><span class="material-icons-round">person</span>Account Information</div>
  <div class="uucms-badge">
    <span style="font-size:20px">🪪</span>
    <div><div style="font-size:10px;font-weight:700;color:#3949ab;text-transform:uppercase;letter-spacing:.8px">UUCMS Number (Unique ID)</div>
    <div class="uucms-val"><?=htmlspecialchars($s['uucms_number']??'—')?></div></div>
  </div>
  <div class="info-grid">
    <div class="info-row"><span class="lbl">Name</span><span class="val"><?=htmlspecialchars($s['name'])?></span></div>
    <div class="info-row"><span class="lbl">Email</span><span class="val"><?=htmlspecialchars($s['email'])?></span></div>
    <div class="info-row"><span class="lbl">Department</span><span class="val">MBA</span></div>
    <div class="info-row"><span class="lbl">Batch</span><span class="val"><?=htmlspecialchars($s['batch']??'—')?></span></div>
    <div class="info-row"><span class="lbl">Specialization</span><span class="val"><?=htmlspecialchars($s['specialization']??'—')?></span></div>
    <div class="info-row"><span class="lbl">Contact</span><span class="val"><?=htmlspecialchars($s['contact_number']??'—')?></span></div>
    <div class="info-row"><span class="lbl">City / State</span><span class="val"><?php $cs=array_filter([trim($s['city']??''),trim($s['state']??'')]);echo htmlspecialchars($cs?implode(', ',$cs):'—');?></span></div>
    <div class="info-row"><span class="lbl">Country</span><span class="val"><?=htmlspecialchars(trim($s['country']??'')?:'—')?></span></div>
  </div>
  <div class="section-title">Academic Background</div>
  <div class="info-grid">
    <div class="info-row"><span class="lbl">10th %</span><span class="val"><?=$s['tenth_percentage']??'—'?>%</span></div>
    <div class="info-row"><span class="lbl">12th %</span><span class="val"><?=$s['twelfth_percentage']??'—'?>%</span></div>
    <div class="info-row"><span class="lbl">Degree CGPA</span><span class="val"><?=$s['degree_cgpa']??'—'?></span></div>
    <div class="info-row"><span class="lbl">MBA CGPA</span><span class="val"><?=$s['cgpa']?></span></div>
    <div class="info-row"><span class="lbl">Backlogs</span><span class="val"><?=$s['backlogs']?></span></div>
    <div class="info-row"><span class="lbl">Pre-Placement</span><span class="val"><?=$s['preplacement_status']==='Ready'?'<span class="badge-r">✅ Ready</span>':'<span class="badge-nr">⚠ Not Ready</span>'?></span></div>
  </div>
</div>

<?php if($pre): ?>
<div class="card">
  <div class="card-title"><span class="material-icons-round">bar_chart</span>Pre-Placement Scores</div>
  <div class="grade-row">
    <div class="g-box"><div class="score"><?=$pre['aptitude_marks']?></div><div class="lbl2">Aptitude (<?=$pre['aptitude_grade']?>)</div></div>
    <div class="g-box"><div class="score"><?=$pre['gd_marks']?></div><div class="lbl2">GD (<?=$pre['gd_grade']?>)</div></div>
    <div class="g-box"><div class="score"><?=$pre['english_marks']?></div><div class="lbl2">English (<?=$pre['english_grade']?>)</div></div>
  </div>
  <p style="margin-top:14px;font-size:13px">Overall: <?=$pre['overall_status']==='Ready'?'<span class="badge-r">✅ Placement Ready</span>':'<span class="badge-nr">⚠ Not Ready — need ≥50 in all areas</span>'?></p>
</div>
<?php endif; ?>

<?php include 'resume_upload_block.php'; ?>

<div class="card" id="editCard">
  <div class="card-title"><span class="material-icons-round">edit</span>Edit Profile</div>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="text"     name="_dummy_email" style="display:none" tabindex="-1" aria-hidden="true" autocomplete="username">
    <input type="password" name="_dummy_pass"  style="display:none" tabindex="-1" aria-hidden="true" autocomplete="current-password">
    <div class="section-title">MBA Programme</div>
    <div class="form-row">
      <div class="form-group">
        <label>UUCMS Number <span style="font-weight:400;color:#3949ab;font-size:11px">(University ID)</span></label>
        <input type="text" name="uucms_number" class="uucms-val" value="<?=htmlspecialchars($s['uucms_number']??'')?>" placeholder="e.g. UUCMS2024001" required style="background:#f0f2ff;border-color:#c5cae9">
      </div>
      <div class="form-group"><label>Department</label><input type="text" value="MBA" class="locked" readonly></div>
      <div class="form-group"><label>Batch</label><select name="batch" required><?php foreach($batches as $b):?><option value="<?=$b?>"<?=($s['batch']??'')===$b?' selected':''?>><?=$b?></option><?php endforeach;?></select></div>
      <div class="form-group"><label>Specialization</label><select name="specialization" required><?php foreach($specializations as $sp):?><option value="<?=$sp?>"<?=($s['specialization']??'')===$sp?' selected':''?>><?=$sp?></option><?php endforeach;?></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>MBA CGPA</label><input type="number" name="cgpa" step="0.01" min="0" max="10" value="<?=$s['cgpa']?>" autocomplete="off"></div>
      <div class="form-group"><label>Active Backlogs</label><input type="number" name="backlogs" min="0" value="<?=$s['backlogs']?>" autocomplete="off"></div>
    </div>
    <div class="section-title">Academic Background</div>
    <div class="form-row">
      <div class="form-group"><label>10th Percentage</label><input type="number" name="tenth_percentage" step="0.01" min="0" max="100" value="<?=$s['tenth_percentage']??''?>" autocomplete="off"></div>
      <div class="form-group"><label>12th Percentage</label><input type="number" name="twelfth_percentage" step="0.01" min="0" max="100" value="<?=$s['twelfth_percentage']??''?>" autocomplete="off"></div>
      <div class="form-group"><label>Degree CGPA</label><input type="number" name="degree_cgpa" step="0.01" min="0" max="10" value="<?=$s['degree_cgpa']??''?>" autocomplete="off"></div>
    </div>
    <div class="section-title">Contact &amp; Location</div>
    <div class="form-row">
      <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" pattern="\d{10}" maxlength="10" value="<?=htmlspecialchars($s['contact_number']??'')?>" autocomplete="tel"></div>
      <div class="form-group"><label>Country</label><input type="text" name="country" value="<?=htmlspecialchars($s['country']??'')?>" placeholder="India" autocomplete="country-name"></div>
      <div class="form-group"><label>State</label><input type="text" name="state" value="<?=htmlspecialchars($s['state']??'')?>" autocomplete="address-level1"></div>
      <div class="form-group"><label>City</label><input type="text" name="city" value="<?=htmlspecialchars($s['city']??'')?>" autocomplete="address-level2"></div>
    </div>
    <div class="section-title">Change Password</div>
    <div class="form-row">
      <div class="form-group">
        <label>New Password <span style="font-weight:400;color:#aaa">(leave blank to keep)</span></label>
        <div class="pw-wrap" style="position:relative">
          <input type="password" id="pw" name="new_password" placeholder="New password" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('pw',this)" tabindex="-1" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:15px;padding:0;line-height:1">👁</button>
        </div>
        <div class="strength-bar" style="display:flex;gap:4px;margin-top:6px">
          <span id="sb1" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
          <span id="sb2" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
          <span id="sb3" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
          <span id="sb4" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
        </div>
        <div class="strength-label" id="slabel" style="font-size:11px;margin-top:4px;font-weight:600"></div>
        <ul class="req-list" id="reqs" style="margin-top:7px;list-style:none;padding-left:0">
          <li id="r-len">At least 8 characters</li>
          <li id="r-upper">One uppercase letter (A–Z)</li>
          <li id="r-lower">One lowercase letter (a–z)</li>
          <li id="r-digit">One digit (0–9)</li>
          <li id="r-sym">One special character (!@#$…)</li>
        </ul>
      </div>
      <div class="form-group" style="align-self:flex-start">
        <label>Confirm Password</label>
        <div class="pw-wrap" style="position:relative">
          <input type="password" id="pw2" name="confirm_password" placeholder="Repeat password" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)" tabindex="-1" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:15px;padding:0;line-height:1">👁</button>
        </div>
        <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
      </div>
    </div>

    <button type="submit" class="btn-primary"><span class="material-icons-round" style="font-size:18px">save</span>Save Changes</button>
  </form>
</div>

<script>
(function(){
  const labels={uucms_number:'UUCMS No.',batch:'Batch',specialization:'Specialization',cgpa:'MBA CGPA',tenth_percentage:'10th %',twelfth_percentage:'12th %',degree_cgpa:'Degree CGPA',backlogs:'Backlogs',contact_number:'Contact No.',state:'State',city:'City'};
  const keys=Object.keys(labels),total=keys.length;let filled=0;
  const chipsEl=document.getElementById('pcChips');
  keys.forEach(k=>{
    const val=profileFields[k],isBL=k==='backlogs';
    const isDone=isBL?(val!==null&&val!==undefined&&String(val).trim()!==''):(val!==null&&val!==undefined&&String(val).trim()!==''&&String(val).trim()!=='0');
    if(isDone)filled++;
    const chip=document.createElement('span');chip.className='chip '+(isDone?'done':'miss');
    chip.title=isDone?labels[k]+' ✓':labels[k]+' — not filled';chip.textContent=(isDone?'✔ ':'✖ ')+labels[k];
    chipsEl.appendChild(chip);
  });
  const pct=Math.round((filled/total)*100),fill=document.getElementById('barFill'),pctEl=document.getElementById('pcPct');
  if(pct<40)fill.classList.add('red');else if(pct<75)fill.classList.add('amber');
  let cur=0;const step=Math.max(1,Math.round(pct/40));
  const timer=setInterval(()=>{cur=Math.min(cur+step,pct);pctEl.innerHTML=cur+'<span>%</span>';if(cur>=pct)clearInterval(timer);},28);
  setTimeout(()=>{fill.style.width=pct+'%';},80);
})();
</script>

<script>
const colors = ['#ef5350', '#ff9800', '#ffeb3b', '#4caf50'];
const labels = ['Weak', 'Fair', 'Good', 'Strong'];
function checkPw() {
  const v = document.getElementById('pw').value;
  const reqs = {
    len: v.length >= 8,
    upper: /[A-Z]/.test(v),
    lower: /[a-z]/.test(v),
    digit: /[0-9]/.test(v),
    sym: /[\W_]/.test(v)
  };
  document.getElementById('r-len').classList.toggle('ok', reqs.len);
  document.getElementById('r-upper').classList.toggle('ok', reqs.upper);
  document.getElementById('r-lower').classList.toggle('ok', reqs.lower);
  document.getElementById('r-digit').classList.toggle('ok', reqs.digit);
  document.getElementById('r-sym').classList.toggle('ok', reqs.sym);

  const score = Object.values(reqs).filter(Boolean).length - 1; // 0..4
  const bars = ['sb1', 'sb2', 'sb3', 'sb4'];
  bars.forEach((id, i) => {
    document.getElementById(id).style.background = (v.length === 0) ? '#e0e0e0' : (i <= score ? colors[Math.min(score, 3)] : '#e0e0e0');
  });
  const lbl = document.getElementById('slabel');
  if (v.length === 0) { lbl.textContent = ''; lbl.style.color = ''; }
  else { lbl.textContent = labels[Math.min(score, 3)]; lbl.style.color = colors[Math.min(score, 3)]; }
  checkMatch();
}
function checkMatch() {
  const v1 = document.getElementById('pw').value;
  const v2 = document.getElementById('pw2').value;
  const ml = document.getElementById('matchlabel');
  if (!v2) { ml.textContent = ''; return; }
  if (v1 === v2) { ml.textContent = '✓ Passwords match'; ml.style.color = '#2e7d32'; }
  else { ml.textContent = '✗ Passwords do not match'; ml.style.color = '#c62828'; }
}
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
document.getElementById('pw').addEventListener('input', checkPw);
document.getElementById('pw2').addEventListener('input', checkMatch);
document.querySelector('form').addEventListener('submit', function (e) {
  const v = document.getElementById('pw').value;
  if (v) {
    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(v)) {
      e.preventDefault();
      alert('Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.');
    } else if (v !== document.getElementById('pw2').value) {
      e.preventDefault();
      alert('Passwords do not match.');
    }
  }
});
</script>
<?php require '_layout_end.php'; ?>