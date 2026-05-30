<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

$stu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT resume_skills FROM student WHERE student_id=$student_id"));
$resume_skills = trim($stu['resume_skills'] ?? '');

$drives_res = mysqli_query($conn, "SELECT drive_id, job_title, company_name, description FROM placement_drive WHERE drive_status != 'Closed' ORDER BY drive_id ASC");
$active_drives = [];
while ($d = mysqli_fetch_assoc($drives_res)) $active_drives[] = $d;

$cached_scores = [];
$cache_res = mysqli_query($conn, "SELECT drive_id, score, matched_skills, missing_skills, recommendation FROM ats_score WHERE student_id=$student_id AND checked_date=CURDATE()");
if ($cache_res) { while ($r = mysqli_fetch_assoc($cache_res)) $cached_scores[$r['drive_id']] = $r; }

$pageTitle = 'Resume / ATS'; $activePage = 'resume';
require '_layout.php';
?>
<style>
.skills-bar{background:linear-gradient(135deg,#e8eaf6,#f5f7ff);border-radius:12px;padding:14px 20px;box-shadow:var(--card-shadow);margin-bottom:22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;border:1px solid #d0d4f7}
.skills-label{font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.chips-wrap{display:flex;flex-wrap:wrap;gap:6px;flex:1}
.skill-chip{background:#fff;color:var(--primary-light);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500;border:1.5px solid #c5cae9}
.no-skills-box{background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:13px;color:#5d4037;display:flex;align-items:center;gap:10px}
.no-skills-box a{color:var(--primary);font-weight:600}

.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:18px}
@media(max-width:640px){.drive-grid{grid-template-columns:1fr}}

.drive-card{background:var(--surface);border-radius:var(--card-radius);box-shadow:var(--card-shadow);border-left:4px solid var(--primary-light);overflow:hidden;transition:transform var(--transition),box-shadow var(--transition)}
.drive-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,35,126,.14)}
.card-head{padding:16px 20px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.company-avatar{width:40px;height:40px;border-radius:10px;background:var(--accent-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.job-title{font-size:14px;font-weight:700;color:var(--text)}
.company-name{font-size:12px;color:var(--muted);margin-top:2px}
.card-body{padding:16px 20px}

.score-row{display:flex;align-items:center;gap:16px;margin-bottom:14px}
.score-ring{position:relative;width:68px;height:68px;flex-shrink:0}
.score-ring svg{transform:rotate(-90deg)}
.ring-bg{fill:none;stroke:#e8eaf6;stroke-width:6}
.ring-fg{fill:none;stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset 1.1s ease}
.ring-num{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:var(--primary)}
.score-info .grade-lbl{font-size:13px;font-weight:700;margin-bottom:3px}
.score-info .grade-sub{font-size:11px;color:var(--muted)}
.progress-wrap{margin-bottom:12px}
.progress-top{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:5px}
.bar-bg{height:8px;background:#f0f0f0;border-radius:10px;overflow:hidden}
.bar-fg{height:8px;border-radius:10px;transition:width 1.1s ease}

.sec-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;margin-top:12px}
.match-chip{display:inline-block;background:var(--success-bg);color:var(--success);border:1px solid #a5d6a7;padding:3px 10px;border-radius:14px;font-size:11px;font-weight:500;margin:2px}
.miss-chip{display:inline-block;background:var(--danger-bg);color:var(--danger);border:1px solid #ef9a9a;padding:3px 10px;border-radius:14px;font-size:11px;font-weight:500;margin:2px}
.rec-box{background:var(--accent-soft);border-radius:8px;padding:11px 14px;font-size:12px;color:#333;line-height:1.6;margin-top:12px}
.rec-box strong{color:var(--primary);display:block;margin-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.cached-tag{display:inline-block;background:var(--accent-soft);color:var(--primary-light);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;margin-left:6px;vertical-align:middle}
.btn-check{width:100%;background:var(--primary);color:#fff;border:none;padding:11px 0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background var(--transition);font-family:inherit}
.btn-check:hover{background:var(--primary-light)}.btn-check:disabled{background:#90a4ae;cursor:not-allowed}
.btn-recheck{width:100%;background:var(--bg);color:#555;border:1px solid var(--border);padding:9px 0;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;margin-top:8px;transition:background var(--transition);font-family:inherit}
.btn-recheck:hover{background:var(--accent-soft);color:var(--primary)}
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
.loading .spinner{display:block}.loading .btn-text{display:none}
@keyframes spin{to{transform:rotate(360deg)}}
.strong .ring-fg{stroke:#2e7d32}.strong .ring-num{color:#2e7d32}.strong .grade-lbl{color:#2e7d32}.strong .bar-fg{background:linear-gradient(90deg,#388e3c,#4caf50)}
.moderate .ring-fg{stroke:#e65100}.moderate .ring-num{color:#e65100}.moderate .grade-lbl{color:#e65100}.moderate .bar-fg{background:linear-gradient(90deg,#e65100,#ff9800)}
.weak .ring-fg{stroke:#c62828}.weak .ring-num{color:#c62828}.weak .grade-lbl{color:#c62828}.weak .bar-fg{background:linear-gradient(90deg,#c62828,#ef5350)}
.err-box{background:var(--danger-bg);border:1px solid #ef9a9a;border-radius:8px;padding:10px 14px;font-size:12px;color:var(--danger);margin-bottom:8px}
.empty-state{text-align:center;padding:70px 20px;background:var(--surface);border-radius:var(--card-radius);box-shadow:var(--card-shadow)}
.empty-state .material-icons-round{font-size:56px;color:var(--border);margin-bottom:12px;display:block}
</style>

<div class="page-header">
  <h1>🤖 Resume / ATS Checker</h1>
  <p>Your resume skills are compared with each active drive's job description using AI.</p>
</div>

<?php if ($resume_skills): ?>
<div class="skills-bar">
  <span class="skills-label">Your Skills</span>
  <div class="chips-wrap">
    <?php foreach (array_slice(array_map('trim', explode(',', $resume_skills)), 0, 25) as $sk): if (trim($sk)): ?>
      <span class="skill-chip"><?= htmlspecialchars(trim($sk)) ?></span>
    <?php endif; endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="no-skills-box">
  ⚠️ No resume skills found. Please <a href="profile.php">upload &amp; parse your resume</a> first — skills are extracted automatically.
</div>
<?php endif; ?>

<?php if (empty($active_drives)): ?>
<div class="empty-state">
  <span class="material-icons-round">work_off</span>
  <p style="font-size:14px;color:var(--muted)">No active placement drives at the moment.</p>
</div>
<?php else: ?>
<div class="drive-grid">
<?php foreach ($active_drives as $d):
  $did = (int)$d['drive_id'];
  $cached = $cached_scores[$did] ?? null;
  $initial = strtoupper(substr($d['company_name'], 0, 1));
?>
<div class="drive-card" id="card-<?= $did ?>">
  <div class="card-head">
    <div class="company-avatar"><?= $initial ?></div>
    <div>
      <div class="job-title"><?= htmlspecialchars($d['job_title']) ?></div>
      <div class="company-name"><?= htmlspecialchars($d['company_name']) ?></div>
    </div>
  </div>
  <div class="card-body" id="body-<?= $did ?>">
    <?php if ($cached && (int)$cached['score'] > 0):
      $sc=$cached['score']; $cls=$sc>=70?'strong':($sc>=40?'moderate':'weak');
      $grade=$sc>=70?'Strong Match':($sc>=40?'Moderate Match':'Weak Match');
      $C=2*M_PI*30; $off=$C-($sc/100)*$C;
      $mArr=array_filter(array_map('trim',explode(',',$cached['matched_skills']??'')));
      $xArr=array_filter(array_map('trim',explode(',',$cached['missing_skills']??'')));
    ?>
      <div class="score-row <?= $cls ?>">
        <div class="score-ring">
          <svg width="68" height="68" viewBox="0 0 68 68">
            <circle class="ring-bg" cx="34" cy="34" r="30"/>
            <circle class="ring-fg" cx="34" cy="34" r="30" stroke-dasharray="<?= round($C,2) ?>" stroke-dashoffset="<?= round($off,2) ?>"/>
          </svg>
          <div class="ring-num"><?= $sc ?>%</div>
        </div>
        <div class="score-info">
          <div class="grade-lbl"><?= $grade ?><span class="cached-tag">Cached</span></div>
          <div class="grade-sub">Today's AI analysis</div>
        </div>
      </div>
      <div class="progress-wrap <?= $cls ?>">
        <div class="progress-top"><span>Match Score</span><span><?= $sc ?>%</span></div>
        <div class="bar-bg"><div class="bar-fg" style="width:<?= $sc ?>%"></div></div>
      </div>
      <?php if ($mArr): ?><div class="sec-label">✅ Matched Skills</div><?php foreach($mArr as $sk): ?><span class="match-chip"><?= htmlspecialchars($sk) ?></span><?php endforeach; endif; ?>
      <?php if ($xArr): ?><div class="sec-label">❌ Missing Skills</div><?php foreach($xArr as $sk): ?><span class="miss-chip"><?= htmlspecialchars($sk) ?></span><?php endforeach; endif; ?>
      <?php if (!empty($cached['recommendation'])): ?><div class="rec-box"><strong>💡 AI Recommendation</strong><?= htmlspecialchars($cached['recommendation']) ?></div><?php endif; ?>
      <?php if ($resume_skills): ?><button class="btn-recheck" onclick="checkATS(<?= $did ?>, true)">🔄 Re-check with AI</button><?php endif; ?>
    <?php else: ?>
      <div id="result-<?= $did ?>"></div>
      <?php if ($resume_skills): ?>
      <button class="btn-check" id="btn-<?= $did ?>" onclick="checkATS(<?= $did ?>, false)">
        <div class="spinner" id="spin-<?= $did ?>"></div>
        <span class="btn-text">🧠 Check ATS Score</span>
      </button>
      <?php else: ?>
      <button class="btn-check" disabled>⚠ Parse Resume First</button>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function scoreClass(s){return s>=70?'strong':(s>=40?'moderate':'weak');}
function gradeLabel(s){return s>=70?'Strong Match':(s>=40?'Moderate Match':'Weak Match');}

function checkATS(driveId, recheck) {
  const btn=document.getElementById('btn-'+driveId);
  const body=document.getElementById('body-'+driveId);
  if(btn){btn.disabled=true;btn.classList.add('loading');}
  const url='get_ats_score.php?drive_id='+driveId+(recheck?'&recheck=1':'');
  fetch(url)
    .then(r=>{if(!r.ok)return r.text().then(t=>{throw new Error('HTTP '+r.status+': '+t.substring(0,200));});return r.text().then(t=>{try{return JSON.parse(t);}catch(e){throw new Error('Bad response: '+t.substring(0,300));}});})
    .then(data=>{
      if(data.error==='no_skills'){if(btn){btn.disabled=false;btn.classList.remove('loading');}document.getElementById('result-'+driveId).innerHTML='<div class="err-box">⚠ No resume skills found. Please parse your resume on the <a href="profile.php" style="color:var(--primary);font-weight:600">Profile page</a> first.</div>';return;}
      if(!data.success){if(btn){btn.disabled=false;btn.classList.remove('loading');}document.getElementById('result-'+driveId).innerHTML='<div class="err-box">❌ '+escHtml(data.message||'Could not get ATS score.')+'</div>';return;}
      const sc=data.score,cls=scoreClass(sc),grad=gradeLabel(sc),C=2*Math.PI*30,off=C-(sc/100)*C;
      const cachedTag=data.cached?'<span class="cached-tag">Cached</span>':'';
      const mChips=(data.matched_skills||[]).map(s=>`<span class="match-chip">${escHtml(s)}</span>`).join('');
      const xChips=(data.missing_skills||[]).map(s=>`<span class="miss-chip">${escHtml(s)}</span>`).join('');
      const rec=data.recommendation?`<div class="rec-box"><strong>💡 AI Recommendation</strong>${escHtml(data.recommendation)}</div>`:'';
      body.innerHTML=`
        <div class="score-row ${cls}">
          <div class="score-ring"><svg width="68" height="68" viewBox="0 0 68 68"><circle class="ring-bg" cx="34" cy="34" r="30"/><circle class="ring-fg" cx="34" cy="34" r="30" stroke-dasharray="${C.toFixed(2)}" stroke-dashoffset="${off.toFixed(2)}"/></svg><div class="ring-num">${sc}%</div></div>
          <div class="score-info"><div class="grade-lbl">${grad}${cachedTag}</div><div class="grade-sub">AI-powered analysis</div></div>
        </div>
        <div class="progress-wrap ${cls}"><div class="progress-top"><span>Match Score</span><span>${sc}%</span></div><div class="bar-bg"><div class="bar-fg" style="width:0%"></div></div></div>
        ${mChips?`<div class="sec-label">✅ Matched Skills</div>${mChips}`:''}
        ${xChips?`<div class="sec-label">❌ Missing Skills</div>${xChips}`:''}
        ${rec}
        <button class="btn-recheck" onclick="checkATS(${driveId},true)" style="margin-top:12px">🔄 Re-check with AI</button>`;
      requestAnimationFrame(()=>{const fg=body.querySelector('.bar-fg');if(fg)setTimeout(()=>{fg.style.width=sc+'%';},60);});
    })
    .catch(err=>{if(btn){btn.disabled=false;btn.classList.remove('loading');}body.innerHTML=`<div class="err-box">❌ ${escHtml(err.message||'Request failed.')}</div><button class="btn-check" onclick="checkATS(${driveId},false)" style="margin-top:8px">🔄 Retry</button>`;});
}
function escHtml(s){const d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;}
</script>
<?php require '_layout_end.php'; ?>
