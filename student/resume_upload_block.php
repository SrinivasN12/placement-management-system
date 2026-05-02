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
    box-shadow: 0 4px 12px rgba(74, 108, 247, .35);
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

  /* ── Method badge ──────────────────────────────────────────── */
  .rub-method-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
    margin-left: 8px;
    vertical-align: middle;
    letter-spacing: .3px;
  }
  .rub-method-badge.gemini  { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
  .rub-method-badge.local   { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }

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
    box-shadow: 0 4px 12px rgba(74, 108, 247, .35);
  }

  #rubUploadBtn:hover  { opacity: .9; transform: translateY(-1px); }
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
  #rubStatus.loading { color: #1a237e; }
  #rubStatus.success { color: #2e7d32; font-weight: 600; }
  #rubStatus.error   { color: #c62828; font-weight: 600; }

  /* ── Animated dots for loading ─────────────────────────────── */
  .rub-dots::after {
    content: '.';
    animation: rubDots 1.2s steps(3, end) infinite;
  }
  @keyframes rubDots {
    0%  { content: '.'; }
    33% { content: '..'; }
    66% { content: '...'; }
  }

  /* ── Saved resume preview section ──────────────────────────── */
  #rubSavedSection {
    margin-top: 16px;
    background: #fff;
    border: 1px solid #c5cae9;
    border-radius: 10px;
    padding: 14px 18px;
    display: none;
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
  .rub-btn-view:hover, .rub-btn-dl:hover, .rub-btn-del:hover { opacity: .8; }

  /* ── Resume Insights panel (for extended fields) ────────────── */
  #rubInsightsPanel {
    margin-top: 14px;
    background: #fff;
    border: 1px solid #c5cae9;
    border-radius: 10px;
    padding: 14px 18px;
    display: none;
  }

  #rubInsightsPanel .rub-insights-title {
    font-size: 12px;
    font-weight: 700;
    color: #1a237e;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .rub-insight-row {
    display: flex;
    gap: 6px;
    margin-bottom: 7px;
    font-size: 12px;
    align-items: flex-start;
  }

  .rub-insight-label {
    font-weight: 700;
    color: #555;
    min-width: 110px;
    flex-shrink: 0;
  }

  .rub-insight-value {
    color: #333;
    flex: 1;
    word-break: break-word;
  }

  .rub-insight-empty {
    color: #aaa;
    font-style: italic;
  }

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
      <div class="rub-subtitle">PDF only · Max 2 MB · AI extracts your profile details · Falls back to local parser if AI unavailable</div>
    </div>
  </div>

  <!-- Upload button + selected filename ──────────────────────── -->
  <div>
    <!-- Hidden file input — triggered by button click -->
    <input type="file" id="rubFileInput" accept="application/pdf" style="display:none;" aria-label="Choose PDF resume">

    <button type="button" id="rubUploadBtn" onclick="rubTriggerPicker()">
      📎 Choose PDF Resume
    </button>
    <span id="rubFileName">No file chosen</span>
  </div>

  <!-- Overwrite toggle ───────────────────────────────────────── -->
  <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
    <input type="checkbox" id="rubOverwrite" style="width:15px;height:15px;cursor:pointer;accent-color:#4a6cf7;">
    <label for="rubOverwrite" style="font-size:12px;color:#444;cursor:pointer;margin:0;">
      ♻️ Overwrite existing profile data with values from resume
    </label>
  </div>

  <!-- Status / feedback message ──────────────────────────────── -->
  <div id="rubStatus"></div>

  <!-- Resume Insights panel (read-only extended fields) ──────── -->
  <div id="rubInsightsPanel">
    <div class="rub-insights-title">
      🔍 Resume Insights (from AI parser)
    </div>
    <div id="rubInsightsContent"></div>
  </div>

  <!-- Saved resume preview (shown if resume already uploaded) ─── -->
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

</div><!-- /#resumeUploadBlock -->

<!-- ════════════════════════════════════════════════════════════
     JavaScript — plain JS, no libraries
     ════════════════════════════════════════════════════════════ -->
<script>
  // ── State ──────────────────────────────────────────────────────
  var rubSavedPath = '';    // stores server path of uploaded resume

  // ── FIELD MAP (Problem 3) ──────────────────────────────────────
  // Maps smart_parse_resume.php JSON keys → profile.php form field names/IDs.
  // Rules: only fill if field is currently EMPTY, highlight with yellow for 3s.
  var FIELD_MAP = {
    name:               'name',               // <input name="name"> if present
    email:              'email',              // <input name="email"> if present
    phone:              'contact_number',     // <input name="contact_number">
    linkedin:           'linkedin_url',       // <input name="linkedin_url"> if present
    cgpa:               'cgpa',               // <input name="cgpa">
    specialization:     'specialization',     // <select name="specialization">
    tenth_percent:      'tenth_percentage',   // <input name="tenth_percentage">
    twelfth_percent:    'twelfth_percentage', // <input name="twelfth_percentage">
    graduation_percent: 'degree_cgpa',        // <input name="degree_cgpa">
    skills:             'skills',             // <input/textarea name="skills"> if present
    work_experience:    'work_experience',    // <textarea name="work_experience"> if present
    certifications:     'certifications',     // <input name="certifications"> if present
    languages:          'languages',          // <input name="languages"> if present
  };

  // ── Extended fields shown in the Insights panel (not in main form) ──
  var INSIGHTS_LABELS = {
    name:               'Full Name',
    email:              'Email',
    phone:              'Phone',
    linkedin:           'LinkedIn',
    skills:             'Skills',
    work_experience:    'Work Experience',
    certifications:     'Certifications',
    languages:          'Languages',
  };

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

    // Start the smart parse + upload flow
    rubSmartParseResume(file);
  });

  // ── Main flow: smart parse (Gemini → local fallback) then save ──
  function rubSmartParseResume(file) {
    rubSetStatus('loading', '🤖 AI is reading your resume<span class="rub-dots"></span>');
    document.getElementById('rubInsightsPanel').style.display = 'none';

    var formData = new FormData();
    formData.append('resume_file', file);

    // Step 1 — send to smart_parse_resume.php (Gemini + local fallback)
    fetch('smart_parse_resume.php', {
      method: 'POST',
      body:   formData,
    })
      .then(function (res) { return res.json(); })
      .then(function (result) {

        if (result.error) {
          // Completely failed — still save the file
          rubSetStatus('loading', '⚠️ AI parser unavailable (' + result.error + '). Saving file...');
          rubSaveFile(file, 0, 'unknown', {});
          return;
        }

        var method  = result.method || 'unknown'; // 'gemini' or 'local'
        var data    = result.data   || {};

        // Step 2 — auto-fill form fields (empty only, unless overwrite checked)
        var filled = rubAutoFill(data);

        // Step 3 — show insights panel for extended fields
        rubShowInsights(data, method);

        // Step 4 — save the PDF file to server
        rubSaveFile(file, filled, method, data);
      })
      .catch(function (err) {
        // Network error reaching smart_parse_resume.php — still try to save
        rubSetStatus('loading', '⚠️ AI unavailable — saving file without auto-fill...');
        rubSaveFile(file, 0, 'error', {});
      });
  }

  // ── Auto-fill empty form fields (Problem 3) ────────────────────
  function rubAutoFill(data) {
    var filledCount = 0;
    var overwrite   = document.getElementById('rubOverwrite') &&
                      document.getElementById('rubOverwrite').checked;

    Object.keys(FIELD_MAP).forEach(function (resumeKey) {
      var formName = FIELD_MAP[resumeKey];
      var value    = (data[resumeKey] || '').trim();
      if (!value) return; // parser couldn't find it — skip

      // Find the form element by name attribute
      var el = document.querySelector('[name="' + formName + '"]');
      if (!el) return; // field doesn't exist in this form — skip silently

      // Only fill empty fields — unless overwrite checkbox is checked
      var currentVal = el.value.trim();
      var isEmpty    = currentVal === '' || currentVal === '0' || currentVal === '0.00';
      if (!isEmpty && !overwrite) return; // respect existing data

      // For <select> elements: match option value case-insensitively
      if (el.tagName === 'SELECT') {
        var matched = false;
        Array.from(el.options).forEach(function (opt) {
          if (opt.value.toLowerCase() === value.toLowerCase()) {
            el.value  = opt.value;
            matched   = true;
          }
        });
        if (!matched) return; // no matching option — don't set
      } else {
        el.value = value;
      }

      // ── Highlight newly filled field with yellow for 3 seconds ─
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

  // ── Show Resume Insights panel ─────────────────────────────────
  function rubShowInsights(data, method) {
    var panel   = document.getElementById('rubInsightsPanel');
    var content = document.getElementById('rubInsightsContent');

    // Only show if we got some useful data
    var hasData = Object.values(data).some(function(v) { return v && v.trim(); });
    if (!hasData) return;

    var html = '';

    Object.keys(INSIGHTS_LABELS).forEach(function (key) {
      var val   = (data[key] || '').trim();
      var label = INSIGHTS_LABELS[key];
      html += '<div class="rub-insight-row">'
            + '<span class="rub-insight-label">' + label + ':</span>'
            + '<span class="rub-insight-value'
            + (val ? '' : ' rub-insight-empty') + '">'
            + (val ? val : 'Not found')
            + '</span></div>';
    });

    content.innerHTML = html;
    panel.style.display = 'block';
  }

  // ── Save PDF to server ─────────────────────────────────────────
  function rubSaveFile(file, filledCount, method, data) {
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

        rubSavedPath = result.path;

        // Show saved resume preview
        rubShowSavedResume({
          found:         true,
          path:          result.path,
          original_name: result.original_name,
          uploaded_at:   result.uploaded_at,
        });

        // ── Success message with fill count + method badge ─────
        var methodBadge = '';
        if (method === 'gemini') {
          methodBadge = ' <span class="rub-method-badge gemini">✨ Gemini AI</span>';
        } else if (method === 'local') {
          methodBadge = ' <span class="rub-method-badge local">🔧 Local Parser</span>';
        }

        var msg = filledCount > 0
          ? '✅ Resume uploaded!' + methodBadge
            + ' <strong>' + filledCount + ' field' + (filledCount > 1 ? 's' : '') + '</strong>'
            + ' auto-filled. Review and save.'
          : '✅ Resume saved.' + methodBadge
            + ' No new fields were auto-filled. Please fill in your profile details manually.';

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
    var fileUrl = '../' + info.path;

    document.getElementById('rubSavedName').textContent = info.original_name || 'resume.pdf';
    document.getElementById('rubSavedDate').textContent = 'Uploaded: ' + (info.uploaded_at || '');
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
    var el      = document.getElementById('rubStatus');
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