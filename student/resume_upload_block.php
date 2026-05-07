<?php
// student/resume_upload_block.php — Self-contained resume-upload widget.
// Include inside the <body> of profile.php.
?>

<style>
  #resumeUploadBlock {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 100%);
    border: 2px dashed #4a6cf7;
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 22px;
    font-family: 'Segoe UI', sans-serif;
    position: relative;
  }
  #resumeUploadBlock .rub-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
  }
  #resumeUploadBlock .rub-icon {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, #4a6cf7, #3949ab);
    border-radius: 10px; display: flex; align-items: center;
    justify-content: center; font-size: 22px; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(74,108,247,.35);
  }
  #resumeUploadBlock .rub-title { font-size: 15px; font-weight: 700; color: #1a237e; }
  #resumeUploadBlock .rub-subtitle { font-size: 12px; color: #666; margin-top: 2px; }

  .rub-method-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; padding: 3px 10px;
    border-radius: 99px; margin-left: 8px; vertical-align: middle;
  }
  .rub-method-badge.gemini { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
  .rub-method-badge.local  { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; }

  #rubUploadBtn {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #4a6cf7, #3949ab);
    color: #fff; border: none; padding: 10px 22px; border-radius: 8px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    transition: opacity .2s, transform .15s;
    box-shadow: 0 4px 12px rgba(74,108,247,.35);
  }
  #rubUploadBtn:hover  { opacity: .9; transform: translateY(-1px); }
  #rubUploadBtn:active { transform: translateY(0); }

  #rubFileName {
    display: inline-block; margin-left: 12px; font-size: 12px;
    color: #555; background: #fff; border: 1px solid #c5cae9;
    border-radius: 6px; padding: 4px 10px; vertical-align: middle;
    max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  #rubStatus { margin-top: 12px; font-size: 13px; min-height: 22px; }
  #rubStatus.loading { color: #1a237e; }
  #rubStatus.success { color: #2e7d32; font-weight: 600; }
  #rubStatus.error   { color: #c62828; font-weight: 600; }

  .rub-dots::after {
    content: '.';
    animation: rubDots 1.2s steps(3,end) infinite;
  }
  @keyframes rubDots {
    0%  { content: '.'; }
    33% { content: '..'; }
    66% { content: '...'; }
  }

  #rubSavedSection {
    margin-top: 16px; background: #fff; border: 1px solid #c5cae9;
    border-radius: 10px; padding: 14px 18px; display: none;
  }
  #rubSavedSection .rub-saved-title {
    font-size: 12px; font-weight: 700; color: #1a237e;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px;
  }
  #rubSavedSection .rub-saved-row {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  }
  #rubSavedSection .rub-file-name {
    font-size: 13px; font-weight: 600; color: #333;
    flex: 1; min-width: 120px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap;
  }
  #rubSavedSection .rub-file-date { font-size: 11px; color: #888; margin-top: 2px; }
  .rub-btn-view, .rub-btn-dl, .rub-btn-del {
    padding: 6px 14px; border: none; border-radius: 6px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    transition: opacity .2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .rub-btn-view { background: #e8eaf6; color: #1a237e; }
  .rub-btn-dl   { background: #e8f5e9; color: #2e7d32; }
  .rub-btn-del  { background: #ffebee; color: #c62828; }
  .rub-btn-view:hover, .rub-btn-dl:hover, .rub-btn-del:hover { opacity: .8; }

  #rubInsightsPanel {
    margin-top: 14px; background: #fff; border: 1px solid #c5cae9;
    border-radius: 10px; padding: 14px 18px; display: none;
  }
  #rubInsightsPanel .rub-insights-title {
    font-size: 12px; font-weight: 700; color: #1a237e;
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
  }
  .rub-insight-row { display: flex; gap: 6px; margin-bottom: 7px; font-size: 12px; align-items: flex-start; }
  .rub-insight-label { font-weight: 700; color: #555; min-width: 130px; flex-shrink: 0; }
  .rub-insight-value { color: #333; flex: 1; word-break: break-word; }
  .rub-insight-empty { color: #aaa; font-style: italic; }

  /* Yellow highlight on auto-filled fields */
  .rub-autofilled {
    background-color: #fff9c4 !important;
    border-color: #f9a825 !important;
    transition: background-color 3s ease, border-color 3s ease;
  }

  @media (max-width: 600px) {
    #resumeUploadBlock { padding: 16px; }
    #rubFileName { display: block; margin: 8px 0 0; max-width: 100%; }
    #rubSavedSection .rub-saved-row { flex-direction: column; align-items: flex-start; }
  }
</style>

<div id="resumeUploadBlock">
  <div class="rub-header">
    <div class="rub-icon">📄</div>
    <div>
      <div class="rub-title">Upload Resume to Auto-Fill Profile</div>
      <div class="rub-subtitle">PDF only · Max 2 MB · Gemini AI extracts your profile details automatically</div>
    </div>
  </div>

  <div>
    <input type="file" id="rubFileInput" accept="application/pdf" style="display:none;" aria-label="Choose PDF resume">
    <button type="button" id="rubUploadBtn" onclick="rubTriggerPicker()">📎 Choose PDF Resume</button>
    <span id="rubFileName">No file chosen</span>
  </div>

  <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
    <input type="checkbox" id="rubOverwrite" style="width:15px;height:15px;cursor:pointer;accent-color:#4a6cf7;">
    <label for="rubOverwrite" style="font-size:12px;color:#444;cursor:pointer;margin:0;">
      ♻️ Overwrite existing profile data with values from resume
    </label>
  </div>

  <div id="rubStatus"></div>

  <!-- Resume Insights panel -->
  <div id="rubInsightsPanel">
    <div class="rub-insights-title">🔍 Resume Insights (from AI parser)</div>
    <div id="rubInsightsContent"></div>
  </div>

  <!-- Saved resume preview -->
  <div id="rubSavedSection">
    <div class="rub-saved-title">📂 Your Saved Resume</div>
    <div class="rub-saved-row">
      <div>
        <div class="rub-file-name" id="rubSavedName">—</div>
        <div class="rub-file-date" id="rubSavedDate"></div>
      </div>
      <a id="rubViewBtn" class="rub-btn-view" target="_blank">👁 View</a>
      <a id="rubDlBtn"   class="rub-btn-dl"   download>⬇ Download</a>
      <button id="rubDelBtn" class="rub-btn-del" onclick="rubDeleteResume()">🗑 Delete</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────
var rubSavedPath = '';

// ── FIELD MAP ──────────────────────────────────────────────────
// Maps smart_parse_resume.php JSON keys → form field name attributes.
// Keys that DON'T have an editable form input are omitted.
var FIELD_MAP = {
  phone:              'contact_number',
  cgpa:               'cgpa',
  specialization:     'specialization',
  tenth_percent:      'tenth_percentage',
  twelfth_percent:    'twelfth_percentage',
  graduation_percent: 'degree_cgpa',
  city:               'city',
  state:              'state',
  country:            'country',
};

// ── Insights panel labels (all fields for display) ─────────────
var INSIGHTS_LABELS = {
  name:               'Full Name',
  email:              'Email',
  phone:              'Phone',
  cgpa:               'MBA CGPA',
  specialization:     'Specialization',
  tenth_percent:      '10th %',
  twelfth_percent:    '12th %',
  graduation_percent: 'Degree CGPA',
  skills:             'Skills (saved for ATS)',
  work_experience:    'Work Experience',
  certifications:     'Certifications',
  city:               'City',
  state:              'State',
  country:            'Country',
};

function rubTriggerPicker() {
  document.getElementById('rubFileInput').click();
}

// ── File selected ──────────────────────────────────────────────
document.getElementById('rubFileInput').addEventListener('change', function () {
  var file = this.files[0];
  if (!file) return;

  if (file.type !== 'application/pdf') {
    rubSetStatus('error', '❌ Only PDF files are accepted.');
    this.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    rubSetStatus('error', '❌ File exceeds 2 MB limit.');
    this.value = '';
    return;
  }

  document.getElementById('rubFileName').textContent = file.name;
  rubSmartParseResume(file);
});

// ── Main flow (Gemini-only mode) ──────────────────────────────
function rubSmartParseResume(file) {
  rubSetStatus('loading', '🤖 Gemini AI is reading your resume<span class="rub-dots"></span>');
  document.getElementById('rubInsightsPanel').style.display = 'none';

  var formData = new FormData();
  formData.append('resume_file', file);

  fetch('smart_parse_resume.php', { method: 'POST', body: formData })
    .then(function (res) {
      if (!res.ok) throw new Error('Server error (HTTP ' + res.status + ')');
      return res.json();
    })
    .then(function (result) {
      // Gemini failed — show error, do NOT silently proceed
      if (result.error) {
        rubSetStatus('error', '❌ ' + result.error);
        return;
      }

      var method = result.method || 'unknown';
      var data   = result.data   || {};

      var filled = rubAutoFill(data);
      rubShowInsights(data, method);
      rubSaveFile(file, filled, method, data);
    })
    .catch(function (err) {
      rubSetStatus('error', '❌ Network / server error: ' + err.message + '. Please check your connection and try again.');
    });
}

// ── Auto-fill form fields ──────────────────────────────────────
function rubAutoFill(data) {
  var filledCount = 0;
  var overwrite   = !!(document.getElementById('rubOverwrite') &&
                       document.getElementById('rubOverwrite').checked);

  Object.keys(FIELD_MAP).forEach(function (resumeKey) {
    var formName = FIELD_MAP[resumeKey];
    // Coerce value to string and trim
    var rawVal = data[resumeKey];
    if (rawVal === undefined || rawVal === null) return;
    var value = String(rawVal).trim();
    if (value === '') return;  // parser found nothing

    var el = document.querySelector('[name="' + formName + '"]');
    if (!el) return;  // field not in this form

    // Determine if the current field value is "empty/default"
    var currentVal = String(el.value).trim();
    var isEmpty = (currentVal === ''
                || currentVal === '0'
                || currentVal === '0.00'
                || currentVal === '0.0'
                || parseFloat(currentVal) === 0);

    if (!isEmpty && !overwrite) return;  // don't overwrite existing real data

    // Handle <select> elements — match option value case-insensitively
    if (el.tagName === 'SELECT') {
      var matched = false;
      Array.from(el.options).forEach(function (opt) {
        if (opt.value.toLowerCase() === value.toLowerCase()) {
          el.value = opt.value;
          matched  = true;
        }
      });
      if (!matched) return;
    } else {
      el.value = value;
    }

    // Yellow highlight for 3 seconds
    el.classList.add('rub-autofilled');
    el.style.backgroundColor = '#fff9c4';
    el.style.borderColor     = '#f9a825';
    setTimeout(function () {
      el.classList.remove('rub-autofilled');
      el.style.backgroundColor = '';
      el.style.borderColor     = '';
    }, 3000);

    filledCount++;
  });

  return filledCount;
}

// ── Insights panel ─────────────────────────────────────────────
function rubShowInsights(data, method) {
  var panel   = document.getElementById('rubInsightsPanel');
  var content = document.getElementById('rubInsightsContent');

  // Check if any value is non-empty (coerce to string safely)
  var hasData = Object.values(data).some(function (v) {
    return v !== null && v !== undefined && String(v).trim() !== '';
  });
  if (!hasData) return;

  var html = '';
  Object.keys(INSIGHTS_LABELS).forEach(function (key) {
    var val   = (data[key] !== undefined && data[key] !== null) ? String(data[key]).trim() : '';
    var label = INSIGHTS_LABELS[key];
    html += '<div class="rub-insight-row">'
          + '<span class="rub-insight-label">' + label + ':</span>'
          + '<span class="rub-insight-value' + (val ? '' : ' rub-insight-empty') + '">'
          + (val ? rubEscHtml(val) : 'Not found')
          + '</span></div>';
  });

  content.innerHTML = html;
  panel.style.display = 'block';
}

// ── Escape HTML for safe display in insights panel ─────────────
function rubEscHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Save PDF to server ─────────────────────────────────────────
function rubSaveFile(file, filledCount, method, data) {
  rubSetStatus('loading', '💾 Saving resume file<span class="rub-dots"></span>');

  var formData = new FormData();
  formData.append('resume_file', file);

  fetch('save_resume_file.php', { method: 'POST', body: formData })
    .then(function (res) { return res.json(); })
    .then(function (result) {
      if (result.error) {
        rubSetStatus('error', '⚠ Resume parsed but could not be saved: ' + result.error);
        return;
      }

      rubSavedPath = result.path;
      rubShowSavedResume({
        found: true,
        path: result.path,
        original_name: result.original_name,
        uploaded_at: result.uploaded_at,
      });

      var methodBadge = '';
      if (method === 'gemini') {
        methodBadge = ' <span class="rub-method-badge gemini">✨ Gemini AI</span>';
      } else if (method === 'local') {
        methodBadge = ' <span class="rub-method-badge local">🔧 Local Parser</span>';
      }

      var msg = filledCount > 0
        ? '✅ Resume uploaded!' + methodBadge
          + ' <strong>' + filledCount + ' field' + (filledCount > 1 ? 's' : '') + '</strong>'
          + ' auto-filled. Review and save your profile.'
        : '✅ Resume saved.' + methodBadge
          + ' No new fields auto-filled (fields may already have data). Review and save.';

      rubSetStatus('success', msg);
    })
    .catch(function () {
      rubSetStatus('error', '⚠ Resume parsed but file could not be saved. Please try again.');
    });
}

// ── Show saved resume preview ──────────────────────────────────
function rubShowSavedResume(info) {
  if (!info || !info.found) {
    document.getElementById('rubSavedSection').style.display = 'none';
    return;
  }

  var section = document.getElementById('rubSavedSection');
  var fileUrl = '../' + info.path;

  document.getElementById('rubSavedName').textContent = info.original_name || 'resume.pdf';
  document.getElementById('rubSavedDate').textContent  = 'Uploaded: ' + (info.uploaded_at || '');
  document.getElementById('rubViewBtn').href = fileUrl;
  document.getElementById('rubDlBtn').href   = fileUrl;
  document.getElementById('rubDlBtn').setAttribute('download', info.original_name || 'resume.pdf');
  rubSavedPath = info.path;

  section.style.display = 'block';
}

// ── Delete resume ──────────────────────────────────────────────
function rubDeleteResume() {
  if (!confirm('Are you sure you want to delete your saved resume? This cannot be undone.')) return;

  rubSetStatus('loading', '🗑 Deleting resume<span class="rub-dots"></span>');

  fetch('delete_resume.php', { method: 'POST' })
    .then(function (res) { return res.json(); })
    .then(function (result) {
      if (result.success) {
        document.getElementById('rubSavedSection').style.display = 'none';
        document.getElementById('rubInsightsPanel').style.display = 'none';
        document.getElementById('rubFileName').textContent = 'No file chosen';
        document.getElementById('rubFileInput').value = '';
        rubSavedPath = '';
        rubSetStatus('success', '✅ Resume deleted successfully.');
      } else {
        rubSetStatus('error', '⚠ ' + (result.message || 'Could not delete resume.'));
      }
    })
    .catch(function () {
      rubSetStatus('error', '⚠ Could not delete resume. Please try again.');
    });
}

// ── Status helper ──────────────────────────────────────────────
function rubSetStatus(type, html) {
  var el    = document.getElementById('rubStatus');
  el.className = type;
  el.innerHTML  = html;
}

// ── On page load: fetch existing resume ───────────────────────
(function () {
  fetch('get_student_resume.php')
    .then(function (res) { return res.json(); })
    .then(function (info) { rubShowSavedResume(info); })
    .catch(function () { /* non-critical */ });
})();
</script>