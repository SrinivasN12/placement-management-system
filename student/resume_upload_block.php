<?php
// student/resume_upload_block.php - Self-contained resume-upload widget.
// Include this file anywhere inside the <body> of profile.php.
// No closing tag issue: comments below are HTML, not PHP.
?>

<!-- ══════════════════════════════════════════════════════════
     RESUME UPLOAD BLOCK — Paste at top of profile.php body
     (after success/error messages, before the profile form)
     ══════════════════════════════════════════════════════════ -->

<!-- ── Inline styles for the upload block ───────────────────── -->
<style>
/* ── Main upload card container ───────────────────────────── */
#resumeUploadBlock {
  background: #f0f4ff;
  border: 2px dashed #4a6cf7;
  border-radius: 14px;
  padding: 22px 26px;
  margin-bottom: 22px;
  font-family: 'Segoe UI', sans-serif;
  position: relative;
}

/* ── Header row with icon + title ─────────────────────────── */
#resumeUploadBlock .rub-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
#resumeUploadBlock .rub-icon {
  width: 44px;
  height: 44px;
  background: linear-gradient(135deg, #4a6cf7, #3949ab);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(74,108,247,.35);
}
#resumeUploadBlock .rub-title {
  font-size: 15px;
  font-weight: 700;
  color: #1a237e;
}
#resumeUploadBlock .rub-subtitle {
  font-size: 12px;
  color: #666;
  margin-top: 2px;
}

/* ── Upload button ─────────────────────────────────────────── */
#rubUploadBtn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #4a6cf7, #3949ab);
  color: #fff;
  border: none;
  padding: 10px 22px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity .2s, transform .15s;
  box-shadow: 0 4px 12px rgba(74,108,247,.35);
}
#rubUploadBtn:hover { opacity: .9; transform: translateY(-1px); }
#rubUploadBtn:active { transform: translateY(0); }

/* ── Selected filename tag ─────────────────────────────────── */
#rubFileName {
  display: inline-block;
  margin-left: 12px;
  font-size: 12px;
  color: #555;
  background: #fff;
  border: 1px solid #c5cae9;
  border-radius: 6px;
  padding: 4px 10px;
  vertical-align: middle;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── Status messages ───────────────────────────────────────── */
#rubStatus {
  margin-top: 12px;
  font-size: 13px;
  min-height: 22px;
  transition: opacity .3s;
}
#rubStatus.loading  { color: #1a237e; }
#rubStatus.success  { color: #2e7d32; font-weight: 600; }
#rubStatus.error    { color: #c62828; font-weight: 600; }

/* ── Animated dots for loading ─────────────────────────────── */
.rub-dots::after {
  content: '.';
  animation: rubDots 1.2s steps(3, end) infinite;
}
@keyframes rubDots {
  0%   { content: '.';   }
  33%  { content: '..';  }
  66%  { content: '...'; }
}

/* ── Saved resume preview section ──────────────────────────── */
#rubSavedSection {
  margin-top: 16px;
  background: #fff;
  border: 1px solid #c5cae9;
  border-radius: 10px;
  padding: 14px 18px;
  display: none; /* shown by JS when resume exists */
}
#rubSavedSection .rub-saved-title {
  font-size: 12px;
  font-weight: 700;
  color: #1a237e;
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 8px;
}
#rubSavedSection .rub-saved-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
#rubSavedSection .rub-file-name {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  flex: 1;
  min-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
#rubSavedSection .rub-file-date {
  font-size: 11px;
  color: #888;
  margin-top: 2px;
}
/* Small action buttons */
.rub-btn-view, .rub-btn-dl, .rub-btn-del {
  padding: 6px 14px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity .2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.rub-btn-view { background: #e8eaf6; color: #1a237e; }
.rub-btn-dl   { background: #e8f5e9; color: #2e7d32; }
.rub-btn-del  { background: #ffebee; color: #c62828; }
.rub-btn-view:hover { opacity: .8; }
.rub-btn-dl:hover   { opacity: .8; }
.rub-btn-del:hover  { opacity: .8; }

/* ── Field highlight on auto-fill ──────────────────────────── */
.rub-autofilled {
  background-color: #fff9c4 !important;
  border-color: #f9a825 !important;
  transition: background-color 3s ease, border-color 3s ease;
}

/* ── Mobile adjustments ────────────────────────────────────── */
@media (max-width: 600px) {
  #resumeUploadBlock { padding: 16px; }
  #rubFileName { display: block; margin: 8px 0 0; max-width: 100%; }
  #rubSavedSection .rub-saved-row { flex-direction: column; align-items: flex-start; }
}
</style>

<!-- ════════════════════════════════════════════════════════════
     HTML for the upload block
     ════════════════════════════════════════════════════════════ -->
<div id="resumeUploadBlock">

  <!-- Header ────────────────────────────────────────────────── -->
  <div class="rub-header">
    <div class="rub-icon">📄</div>
    <div>
      <div class="rub-title">Upload Resume to Auto-Fill</div>
      <div class="rub-subtitle">PDF only · Max 2 MB · Gemini AI reads your resume and fills the form</div>
    </div>
  </div>

  <!-- Upload button + selected filename ──────────────────────── -->
  <div>
    <!-- Hidden file input — triggered by button click -->
    <input type="file" id="rubFileInput" accept="application/pdf"
           style="display:none;" aria-label="Choose PDF resume">

    <button type="button" id="rubUploadBtn" onclick="rubTriggerPicker()">
      📎 Choose PDF Resume
    </button>
    <span id="rubFileName">No file chosen</span>
  </div>

  <!-- Status / feedback message ──────────────────────────────── -->
  <div id="rubStatus"></div>

  <!-- Saved resume preview (shown if resume already uploaded) ─── -->
  <div id="rubSavedSection">
    <div class="rub-saved-title">📂 Your Saved Resume</div>
    <div class="rub-saved-row">
      <div>
        <div class="rub-file-name" id="rubSavedName">—</div>
        <div class="rub-file-date" id="rubSavedDate"></div>
      </div>
      <a id="rubViewBtn"  class="rub-btn-view" target="_blank">👁 View</a>
      <a id="rubDlBtn"    class="rub-btn-dl"   download>⬇ Download</a>
      <button id="rubDelBtn" class="rub-btn-del" onclick="rubDeleteResume()">🗑 Delete</button>
    </div>
  </div>

</div><!-- /#resumeUploadBlock -->

<!-- ════════════════════════════════════════════════════════════
     JavaScript — plain JS, no libraries
     ════════════════════════════════════════════════════════════ -->
<script>
// ── State ──────────────────────────────────────────────────────
var rubSavedPath = '';    // stores server path of uploaded resume

// ── Open file picker ───────────────────────────────────────────
function rubTriggerPicker() {
  document.getElementById('rubFileInput').click();
}

// ── File selected handler ──────────────────────────────────────
document.getElementById('rubFileInput').addEventListener('change', function () {
  var file = this.files[0];
  if (!file) return;

  // Client-side validation before upload
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

  // Show selected filename
  document.getElementById('rubFileName').textContent = file.name;

  // Start the parse + upload flow
  rubParseResume(file);
});

// ── Main flow: parse with Gemini then save file ────────────────
function rubParseResume(file) {
  rubSetStatus('loading', '🤖 AI is reading your resume<span class="rub-dots"></span>');

  var formData = new FormData();
  formData.append('resume_file', file);

  // Step 1 — send to Gemini via parse_resume.php
  fetch('parse_resume.php', {
    method: 'POST',
    body:   formData,
  })
  .then(function (res) { return res.json(); })
  .then(function (result) {

    if (result.error) {
      rubSetStatus('error', '⚠ ' + result.error);
      return;
    }

    // Step 2 — auto-fill form fields (empty only)
    var filled = rubAutoFill(result.data);

    // Step 3 — save the PDF file to server
    rubSaveFile(file, filled);
  })
  .catch(function () {
    rubSetStatus('error', '⚠ Could not read resume. Please fill manually.');
  });
}

// ── Auto-fill empty form fields ────────────────────────────────
// Maps Gemini field keys → form element IDs/names in profile.php
function rubAutoFill(data) {
  // Map: { geminiKey: 'formFieldName' }
  // These must match the input name/id attributes in profile.php
  var fieldMap = {
    'contact_number'     : 'contact_number',   // <input name="contact_number">
    'specialization'     : 'specialization',   // <select name="specialization">
    'cgpa'               : 'cgpa',             // <input name="cgpa">
    'tenth_percentage'   : 'tenth_percentage', // <input name="tenth_percentage">
    'twelfth_percentage' : 'twelfth_percentage',
    'degree_cgpa'        : 'degree_cgpa',
    'state'              : 'state',
    'city'               : 'city',
    'country'            : 'country',
  };

  var filledCount = 0;

  Object.keys(fieldMap).forEach(function (geminiKey) {
    var formName = fieldMap[geminiKey];
    var value    = (data[geminiKey] || '').trim();
    if (!value) return; // Gemini couldn't find it — skip

    // Find the element by name (works for inputs and selects)
    var el = document.querySelector('[name="' + formName + '"]');
    if (!el) return;

    // Only fill if field is currently empty / zero
    var currentVal = el.value.trim();
    var isEmpty    = currentVal === '' || currentVal === '0' || currentVal === '0.00';
    if (!isEmpty) return; // respect existing data

    // For <select> elements, try to find a matching option (case-insensitive)
    if (el.tagName === 'SELECT') {
      var matched = false;
      Array.from(el.options).forEach(function (opt) {
        if (opt.value.toLowerCase() === value.toLowerCase()) {
          el.value = opt.value;
          matched = true;
        }
      });
      if (!matched) return; // no matching option — don't set
    } else {
      el.value = value;
    }

    // Highlight the filled field in yellow for 3 seconds
    el.classList.add('rub-autofilled');
    setTimeout(function () {
      el.classList.remove('rub-autofilled');
    }, 3000);

    filledCount++;
  });

  return filledCount;
}

// ── Save PDF to server ─────────────────────────────────────────
function rubSaveFile(file, filledCount) {
  rubSetStatus('loading', '💾 Saving resume file<span class="rub-dots"></span>');

  var formData = new FormData();
  formData.append('resume_file', file);

  fetch('save_resume_file.php', {
    method: 'POST',
    body:   formData,
  })
  .then(function (res) { return res.json(); })
  .then(function (result) {
    if (result.error) {
      rubSetStatus('error', '⚠ Resume parsed but could not be saved: ' + result.error);
      return;
    }

    // Store path for display
    rubSavedPath = result.path;

    // Show saved resume preview
    rubShowSavedResume({
      found:         true,
      path:          result.path,
      original_name: result.original_name,
      uploaded_at:   result.uploaded_at,
    });

    // Success message with fill count
    var msg = filledCount > 0
      ? '✅ Resume uploaded! <strong>' + filledCount + ' field' + (filledCount > 1 ? 's' : '') + '</strong> auto-filled. Review and save.'
      : '✅ Resume uploaded. No empty fields to auto-fill — review your data.';
    rubSetStatus('success', msg);
  })
  .catch(function () {
    rubSetStatus('error', '⚠ Resume parsed but file could not be saved. Please try again.');
  });
}

// ── Show saved resume preview section ─────────────────────────
function rubShowSavedResume(info) {
  if (!info || !info.found) {
    document.getElementById('rubSavedSection').style.display = 'none';
    return;
  }

  var section = document.getElementById('rubSavedSection');
  var fileUrl = '../' + info.path; // path relative from /student/ to project root

  document.getElementById('rubSavedName').textContent = info.original_name || 'resume.pdf';
  document.getElementById('rubSavedDate').textContent = 'Uploaded: ' + (info.uploaded_at || '');
  document.getElementById('rubViewBtn').href           = fileUrl;
  document.getElementById('rubDlBtn').href             = fileUrl;
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
  var el = document.getElementById('rubStatus');
  el.className = type;
  el.innerHTML = html;
}

// ── On page load: fetch and display existing resume ────────────
(function () {
  fetch('get_student_resume.php')
  .then(function (res) { return res.json(); })
  .then(function (info) {
    rubShowSavedResume(info);
  })
  .catch(function () {
    // Silently ignore — non-critical
  });
})();
</script>
