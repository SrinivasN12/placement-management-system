<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/qa_setup.php';
if ($role_id != 1) {
  header("Location: ../index.php");
  exit();
}
$student_id = (int) ($_SESSION['student_id'] ?? 0);

qa_setup($conn);

$success = $error = "";

// ── Post a new question ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ask'])) {
  validate_csrf();
  $title = trim($_POST['title']);
  $body = trim($_POST['body']);
  $tags = trim($_POST['tags']);

  if (strlen($title) < 6) {
    $error = "Title must be at least 6 characters.";
  } elseif (strlen($body) < 10) {
    $error = "Please describe your question in more detail (min 10 chars).";

  } else {
    $stmt = mysqli_prepare(
      $conn,
      "INSERT INTO qa_question (student_id,title,body,tags) VALUES (?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmt, "isss", $student_id, $title, $body, $tags);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = "Your question has been posted!";
  }
}

// ── Filters ────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';        // all | unanswered | mine | resolved
$search = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$having = "";
if ($filter === 'unanswered')
  $having = "HAVING answer_count = 0";
if ($filter === 'mine')
  $where .= " AND qq.student_id = $student_id";
if ($filter === 'resolved')
  $where .= " AND qq.is_resolved = 1";
if ($search) {
  $se = mysqli_real_escape_string($conn, $search);
  $where .= " AND (qq.title LIKE '%$se%' OR qq.tags LIKE '%$se%')";
}

$questions = mysqli_query($conn, "
    SELECT qq.*,
           s.name AS asker_name,
           s.specialization,
           COUNT(DISTINCT a.answer_id) AS answer_count,
           SUM(COALESCE(v.cnt,0))      AS total_votes
    FROM qa_question qq
    JOIN student s ON qq.student_id = s.student_id
    LEFT JOIN qa_answer  a ON qq.question_id = a.question_id
    LEFT JOIN (
        SELECT answer_id, COUNT(*) cnt FROM qa_vote GROUP BY answer_id
    ) v ON a.answer_id = v.answer_id
    $where
    GROUP BY qq.question_id
    $having
    ORDER BY qq.created_at DESC
");
$pageTitle  = 'Ask Alumni';
$activePage = 'qa';
require '_layout.php';
?>
<style>
    /* Hero */
    .qa-hero {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 60%, var(--accent) 100%);
      border-radius: 16px;
      padding: 28px 32px;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      position: relative;
      overflow: hidden;
      box-shadow: var(--card-shadow);
    }

    .qa-hero::before {
      content: '?';
      position: absolute;
      right: 32px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 120px;
      font-weight: 900;
      color: rgba(255, 255, 255, .06);
      line-height: 1;
      pointer-events: none;
    }

    .qa-hero-text h2 {
      color: #fff;
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 4px;
    }

    .qa-hero-text p {
      color: rgba(255, 255, 255, .75);
      font-size: 13px;
    }

    .btn-ask {
      background: #fff;
      color: var(--primary);
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;
      transition: transform .15s, box-shadow .15s;
    }

    .btn-ask:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, .18);
      color: var(--primary-light);
    }

    /* Ask modal */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .5);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .modal-backdrop.show {
      display: flex;
    }

    .modal {
      background: #fff;
      border-radius: 16px;
      padding: 32px;
      max-width: 620px;
      width: 100%;
      box-shadow: 0 16px 48px rgba(0, 0, 0, .2);
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal h3 {
      color: var(--primary);
      font-size: 18px;
      margin-bottom: 18px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--accent-soft);
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #444;
      margin-bottom: 5px;
    }

    input[type=text],
    textarea {
      width: 100%;
      padding: 10px 13px;
      border: 1.5px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      font-family: inherit;
    }

    input[type=text]:focus,
    textarea:focus {
      border-color: var(--primary-light);
    }

    textarea {
      resize: vertical;
      min-height: 110px;
    }

    .hint {
      font-size: 11px;
      color: #aaa;
      margin-top: 4px;
    }

    .btn-submit {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 12px 28px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background var(--transition);
    }

    .btn-submit:hover {
      background: var(--primary-light);
    }

    .btn-cancel {
      background: #f5f5f5;
      color: #555;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-left: 10px;
      transition: background var(--transition);
    }

    .btn-cancel:hover {
      background: #e0e0e0;
    }

    /* Toolbar */
    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 20px;
    }

    .tab {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      color: #555;
      background: #fff;
      border: 1.5px solid #e0e0e0;
      transition: all .15s;
    }

    .tab.active,
    .tab:hover {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .search-box {
      flex: 1;
      min-width: 200px;
    }

    .search-box input {
      width: 100%;
      padding: 9px 13px;
      border: 1.5px solid #e0e0e0;
      border-radius: 8px;
      font-size: 13px;
      outline: none;
      background: #fff;
    }

    .search-box input:focus {
      border-color: var(--primary-light);
    }

    /* Question list */
    .q-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .q-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px 24px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
      display: flex;
      gap: 18px;
      text-decoration: none;
      color: inherit;
      transition: box-shadow .15s, transform .1s;
      border-left: 4px solid var(--accent-soft);
    }

    .q-card:hover {
      box-shadow: 0 4px 18px rgba(0, 0, 0, .13);
      transform: translateY(-2px);
      border-left-color: var(--primary-light);
    }

    .q-card.resolved {
      border-left-color: var(--success);
    }

    .q-stats {
      display: flex;
      flex-direction: column;
      gap: 6px;
      align-items: center;
      min-width: 52px;
      text-align: center;
      flex-shrink: 0;
    }

    .stat-box {
      background: var(--accent-soft);
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 11px;
      color: #555;
      width: 100%;
    }

    .stat-box .num {
      font-size: 18px;
      font-weight: 700;
      color: var(--primary);
      display: block;
    }

    .stat-box.has-answers {
      background: var(--success-bg);
    }

    .stat-box.has-answers .num {
      color: var(--success);
    }

    .q-body {
      flex: 1;
      min-width: 0;
    }

    .q-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 6px;
      line-height: 1.4;
    }

    .q-excerpt {
      font-size: 13px;
      color: #666;
      line-height: 1.5;
      margin-bottom: 10px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .q-meta {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      font-size: 12px;
      color: #888;
    }

    .tag {
      background: var(--accent-soft);
      color: var(--primary-light);
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-resolved {
      background: var(--success-bg);
      color: var(--success);
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
    }

    .badge-unanswered {
      background: var(--warn-bg);
      color: var(--warn);
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
    }

    .empty {
      text-align: center;
      padding: 60px;
      background: #fff;
      border-radius: 12px;
      color: #888;
      font-size: 14px;
      box-shadow: var(--card-shadow);
    }

    @media(max-width:640px) {
      .qa-hero {
        flex-direction: column;
        align-items: flex-start;
        padding: 22px 20px;
      }

      .q-card {
        flex-direction: column;
        gap: 10px;
      }

      .q-stats {
        flex-direction: row;
        justify-content: flex-start;
      }

      .toolbar {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>

  <a class="back-link" href="dashboard.php">
    <span class="material-icons-round" style="font-size:16px; vertical-align:middle;">arrow_back</span>
    Back to Dashboard
  </a>

  <?php if ($success): ?>
    <div class="alert-success">✅ <?= $success ?></div><?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Hero -->
  <div class="qa-hero">
    <div class="qa-hero-text">
      <h2>💬 Ask Alumni</h2>
      <p>Get real-world advice from alumni who've been in your shoes. Post questions — they answer, publicly, for everyone to learn.</p>
    </div>
    <a class="btn-ask" href="#" onclick="openAskModal();return false;">✏️ Ask a Question</a>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <a class="tab <?= $filter === 'all' ? 'active' : '' ?>"
      href="qa_board.php?filter=all<?= $search ? '&q=' . urlencode($search) : '' ?>">All Questions</a>
    <a class="tab <?= $filter === 'unanswered' ? 'active' : '' ?>"
      href="qa_board.php?filter=unanswered<?= $search ? '&q=' . urlencode($search) : '' ?>">Unanswered</a>
    <a class="tab <?= $filter === 'mine' ? 'active' : '' ?>"
      href="qa_board.php?filter=mine<?= $search ? '&q=' . urlencode($search) : '' ?>">My Questions</a>
    <a class="tab <?= $filter === 'resolved' ? 'active' : '' ?>"
      href="qa_board.php?filter=resolved<?= $search ? '&q=' . urlencode($search) : '' ?>">Resolved</a>
    <form class="search-box" method="GET">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search questions or tags…">
    </form>
  </div>

  <!-- Question List -->
  <div class="q-list">
    <?php $found = false;
    while ($q = mysqli_fetch_assoc($questions)):
      $found = true;
      $tags_arr = array_filter(array_map('trim', explode(',', $q['tags'])));
      $has_ans = $q['answer_count'] > 0;
      ?>
      <a class="q-card <?= $q['is_resolved'] ? 'resolved' : '' ?>" href="qa_question.php?id=<?= $q['question_id'] ?>">
        <div class="q-stats">
          <div class="stat-box <?= $has_ans ? 'has-answers' : '' ?>">
            <span class="num"><?= $q['answer_count'] ?></span>
            answers
          </div>
          <div class="stat-box">
            <span class="num"><?= $q['total_votes'] ?: 0 ?></span>
            votes
          </div>
        </div>
        <div class="q-body">
          <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
          <div class="q-excerpt"><?= htmlspecialchars($q['body']) ?></div>
          <div class="q-meta">
            <?php if ($q['is_resolved']): ?>
              <span class="badge-resolved">✅ Resolved</span>
            <?php elseif (!$has_ans): ?>
              <span class="badge-unanswered">⏳ Awaiting Answer</span>
            <?php endif; ?>
            <?php foreach ($tags_arr as $t): ?>
              <span class="tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
            <span>Asked by <strong><?= htmlspecialchars($q['asker_name']) ?></strong></span>
            <span><?= date('d M Y', strtotime($q['created_at'])) ?></span>
          </div>
        </div>
      </a>
    <?php endwhile; ?>
    <?php if (!$found): ?>
      <div class="empty">No questions found.<br><br>
        <a href="#" onclick="openAskModal();return false;" style="color:var(--primary);font-weight:600">Be the first to ask one →</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Ask Modal -->
  <div class="modal-backdrop" id="askModal">
    <div class="modal">
      <h3>✏️ Post Your Question</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="ask" value="1">
        <div class="form-group">
          <label>Question Title *</label>
          <input type="text" name="title" minlength="6" placeholder="e.g. How do I prepare for a finance interview?" maxlength="300"
            required>
          <p class="hint">Be specific and clear. Min 6 characters.</p>
        </div>
        <div class="form-group">
          <label>Details *</label>
          <textarea name="body" minlength="10"
            placeholder="Describe your question in detail — what have you tried, what are you unsure about, your current situation…"
            required></textarea>
          <p class="hint">Please describe your question in more detail (min 10 characters).</p>
        </div>

        <div class="form-group">
          <label>Tags <span style="font-weight:400;color:#aaa">(optional, comma-separated)</span></label>
          <input type="text" name="tags" placeholder="e.g. Finance, Interview, Resume">
          <p class="hint">Add 1–3 relevant tags to help alumni find and answer your question.</p>
        </div>
        <div>
          <button type="submit" class="btn-submit">🚀 Post Question</button>
          <button type="button" class="btn-cancel" onclick="closeAskModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAskModal() { document.getElementById('askModal').classList.add('show'); }
    function closeAskModal() { document.getElementById('askModal').classList.remove('show'); }
    document.getElementById('askModal').addEventListener('click', function (e) { if (e.target === this) closeAskModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAskModal(); });
    <?php if ($error): ?>window.addEventListener('load', openAskModal); <?php endif; ?>
  </script>

<?php require '_layout_end.php'; ?>