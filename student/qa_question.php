<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/qa_setup.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }
$student_id = (int)($_SESSION['student_id'] ?? 0);

qa_setup($conn);

$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header("Location: qa_board.php"); exit(); }

// ── Load question ──────────────────────────────────────────────
$q = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT qq.*, s.name AS asker_name, s.specialization, s.batch
     FROM qa_question qq
     JOIN student s ON qq.student_id = s.student_id
     WHERE qq.question_id = $qid"));
if (!$q) { header("Location: qa_board.php"); exit(); }

$success = $error = "";

// ── Mark as Resolved ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve'])) {
    validate_csrf();
    if ($q['student_id'] === $student_id) {
        $new = $q['is_resolved'] ? 0 : 1;
        mysqli_query($conn, "UPDATE qa_question SET is_resolved=$new WHERE question_id=$qid");
        header("Location: qa_question.php?id=$qid"); exit();
    }
}

// ── Toggle vote on an answer ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_answer'])) {
    validate_csrf();
    $aid = (int)$_POST['vote_answer'];
    $existing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT vote_id FROM qa_vote WHERE student_id=$student_id AND answer_id=$aid"));
    if ($existing) {
        mysqli_query($conn, "DELETE FROM qa_vote WHERE vote_id={$existing['vote_id']}");
    } else {
        mysqli_query($conn, "INSERT INTO qa_vote (answer_id,student_id) VALUES ($aid,$student_id)");
    }
    header("Location: qa_question.php?id=$qid#a$aid"); exit();
}

// ── Load answers ───────────────────────────────────────────────
$answers = mysqli_query($conn,
    "SELECT a.*, al.name AS alumni_name, al.company_name,
            COUNT(v.vote_id) AS vote_count,
            MAX(CASE WHEN v.student_id=$student_id THEN 1 ELSE 0 END) AS i_voted
     FROM qa_answer a
     JOIN alumni al ON a.alumni_id = al.alumni_id
     LEFT JOIN qa_vote v ON a.answer_id = v.answer_id
     WHERE a.question_id = $qid
     GROUP BY a.answer_id
     ORDER BY vote_count DESC, a.created_at ASC");

$tags_arr = array_filter(array_map('trim', explode(',', $q['tags'])));
$is_mine  = ($q['student_id'] === $student_id);

$pageTitle  = htmlspecialchars($q['title']);
$activePage = 'qa';
require '_layout.php';
?>
<style>
/* ── Question card ─────────────────────────────────────────── */
.q-detail-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  padding: 28px 32px;
  margin-bottom: 24px;
  border-top: 4px solid var(--primary-light);
}
.q-detail-card.resolved { border-top-color: var(--success); }

.q-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary);
  margin-bottom: 12px;
  line-height: 1.4;
}
.q-body-text {
  font-size: 14px;
  color: #444;
  line-height: 1.75;
  margin-bottom: 18px;
  white-space: pre-wrap;
}
.q-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.tag {
  background: var(--accent-soft);
  color: var(--primary-light);
  padding: 4px 11px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.badge-resolved {
  background: var(--success-bg);
  color: var(--success);
  padding: 4px 11px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}
.q-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 12px;
  color: var(--muted);
  padding-top: 16px;
  border-top: 1px solid var(--border);
}
.asker-chip {
  display: flex;
  align-items: center;
  gap: 8px;
}
.asker-avatar {
  width: 32px; height: 32px;
  background: var(--accent-soft);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700;
  color: var(--primary-light);
}
.asker-details { display: flex; flex-direction: column; }
.asker-name { font-size: 12.5px; font-weight: 700; color: var(--text); }
.asker-sub  { font-size: 11px; color: var(--muted); }

.btn-resolve {
  background: var(--success);
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: opacity var(--transition);
}
.btn-resolve.unresolve { background: var(--muted); }
.btn-resolve:hover { opacity: .88; }

/* ── Answers section ───────────────────────────────────────── */
.answers-section-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.answers-section-title .count-badge {
  background: var(--accent-soft);
  color: var(--primary-mid);
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 700;
}

.answer-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  padding: 22px 26px;
  margin-bottom: 14px;
  display: flex;
  gap: 18px;
  transition: box-shadow var(--transition);
}
.answer-card:hover { box-shadow: 0 6px 22px rgba(26,35,126,.13); }

.vote-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 42px;
  flex-shrink: 0;
}
.vote-btn {
  background: none;
  border: 2px solid var(--border);
  border-radius: 8px;
  width: 40px; height: 40px;
  font-size: 18px;
  cursor: pointer;
  transition: all .15s;
  display: flex; align-items: center; justify-content: center;
}
.vote-btn:hover, .vote-btn.voted {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.vote-count {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
}
.vote-lbl {
  font-size: 10px;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 500;
}

.answer-body-col { flex: 1; min-width: 0; }
.answer-body-text {
  font-size: 13.5px;
  color: #333;
  line-height: 1.7;
  margin-bottom: 14px;
  white-space: pre-wrap;
}
.alumni-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--accent-soft);
  border-radius: 8px;
  padding: 6px 12px;
  margin-bottom: 8px;
}
.alumni-avatar {
  width: 28px; height: 28px;
  background: var(--primary);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}
.alumni-name { font-size: 12px; font-weight: 700; color: var(--primary); }
.alumni-co   { font-size: 11px; color: var(--muted); }
.ans-date {
  font-size: 11.5px;
  color: var(--muted);
  margin-top: 4px;
}

.no-answers-state {
  text-align: center;
  padding: 40px 20px;
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  color: var(--muted);
  font-size: 14px;
  margin-bottom: 14px;
}
.no-answers-state .material-icons-round {
  font-size: 48px;
  color: var(--border);
  margin-bottom: 12px;
  display: block;
}

.info-note {
  text-align: center;
  font-size: 13px;
  color: var(--muted);
  margin-top: 20px;
  margin-bottom: 8px;
}
.info-note a { color: var(--primary-light); font-weight: 600; }

@media(max-width:640px) {
  .q-detail-card { padding: 20px 18px; }
  .q-title { font-size: 17px; }
  .answer-card { flex-direction: column; gap: 10px; padding: 18px; }
  .vote-col { flex-direction: row; justify-content: flex-start; }
}
</style>

<!-- Page Header -->
<div class="page-header">
  <h1><span class="material-icons-round" style="vertical-align:middle;font-size:26px;margin-right:6px">question_answer</span>Question Details</h1>
  <p>View the full question and alumni responses below.</p>
</div>

<a class="back-link" href="qa_board.php">
  <span class="material-icons-round" style="font-size:16px; vertical-align:middle;">arrow_back</span>
  Back to Q&amp;A Board
</a>

<!-- Question Detail Card -->
<div class="q-detail-card <?= $q['is_resolved'] ? 'resolved' : '' ?>">
  <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>

  <?php if ($tags_arr || $q['is_resolved']): ?>
  <div class="q-tags">
    <?php if ($q['is_resolved']): ?>
      <span class="badge-resolved">✅ Resolved</span>
    <?php endif; ?>
    <?php foreach ($tags_arr as $t): ?>
      <span class="tag"><?= htmlspecialchars($t) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="q-body-text"><?= htmlspecialchars($q['body']) ?></div>

  <div class="q-meta-row">
    <div class="asker-chip">
      <div class="asker-avatar"><?= strtoupper(substr($q['asker_name'], 0, 1)) ?></div>
      <div class="asker-details">
        <span class="asker-name"><?= htmlspecialchars($q['asker_name']) ?></span>
        <span class="asker-sub"><?= htmlspecialchars($q['specialization'] ?? 'MBA') ?> · <?= htmlspecialchars($q['batch'] ?? '') ?></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <span>Posted <?= date('d M Y, g:i A', strtotime($q['created_at'])) ?></span>
      <?php if ($is_mine): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="resolve" value="1">
          <button type="submit" class="btn-resolve <?= $q['is_resolved'] ? 'unresolve' : '' ?>">
            <?= $q['is_resolved'] ? '↩ Mark Unresolved' : '✅ Mark as Resolved' ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Answers -->
<?php
$ans_arr = [];
while ($a = mysqli_fetch_assoc($answers)) $ans_arr[] = $a;
$ans_count = count($ans_arr);
?>
<div class="answers-section-title">
  <span class="material-icons-round">forum</span>
  Answers
  <span class="count-badge"><?= $ans_count ?></span>
</div>

<?php if (empty($ans_arr)): ?>
  <div class="no-answers-state">
    <span class="material-icons-round">chat_bubble_outline</span>
    <strong>No answers yet</strong>
    <p style="margin-top:6px">Alumni will respond soon! Keep checking back.</p>
  </div>
<?php else: ?>
  <?php foreach ($ans_arr as $a): ?>
  <div class="answer-card" id="a<?= $a['answer_id'] ?>">
    <div class="vote-col">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="vote_answer" value="<?= $a['answer_id'] ?>">
        <button type="submit" class="vote-btn <?= $a['i_voted'] ? 'voted' : '' ?>"
                title="<?= $a['i_voted'] ? 'Remove vote' : 'Mark helpful' ?>">👍</button>
      </form>
      <div class="vote-count"><?= (int)$a['vote_count'] ?></div>
      <div class="vote-lbl">helpful</div>
    </div>
    <div class="answer-body-col">
      <div class="answer-body-text"><?= htmlspecialchars($a['body']) ?></div>
      <div class="alumni-chip">
        <div class="alumni-avatar"><?= strtoupper(substr($a['alumni_name'], 0, 1)) ?></div>
        <div>
          <div class="alumni-name">🌐 <?= htmlspecialchars($a['alumni_name']) ?></div>
          <div class="alumni-co"><?= htmlspecialchars($a['company_name']) ?></div>
        </div>
      </div>
      <div class="ans-date">
        <span class="material-icons-round" style="font-size:13px;vertical-align:middle">schedule</span>
        Answered <?= date('d M Y, g:i A', strtotime($a['created_at'])) ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<p class="info-note">
  <span class="material-icons-round" style="font-size:14px;vertical-align:middle">info</span>
  Only alumni can post answers. Alumni —
  <a href="../alumni/qa_board.php">visit Q&amp;A Board</a> to reply.
</p>

<?php require '_layout_end.php'; ?>
