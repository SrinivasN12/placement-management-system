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
?>
$pageTitle  = htmlspecialchars($q['title']);
$activePage = 'qa';
require '_layout.php';
?>
<style>
/* Question card */
.q-card{background:#fff;border-radius:14px;padding:28px 32px;box-shadow:var(--card-shadow);margin-bottom:24px;border-top:4px solid var(--primary-light)}
.q-card.resolved{border-top-color:var(--success)}
.q-title{font-size:22px;font-weight:800;color:var(--primary);margin-bottom:12px;line-height:1.4}
.q-body{font-size:14px;color:#444;line-height:1.7;margin-bottom:16px;white-space:pre-wrap}
.q-tags{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.tag{background:var(--accent-soft);color:var(--primary-light);padding:4px 11px;border-radius:6px;font-size:12px;font-weight:600}
.q-meta{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:#888;padding-top:14px;border-top:1px solid var(--border)}
.asker{display:flex;align-items:center;gap:8px}
.avatar{width:32px;height:32px;background:var(--accent-soft);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--primary-light)}
.badge-resolved{background:var(--success-bg);color:var(--success);padding:5px 14px;border-radius:8px;font-size:12px;font-weight:700}
.btn-resolve{background:var(--success);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:opacity var(--transition)}
.btn-resolve.unresolve{background:#666}
.btn-resolve:hover{opacity:.88}

/* Answers */
.answers-header{font-size:16px;font-weight:700;color:var(--primary);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.answers-header .count{background:var(--accent-soft);color:var(--primary-mid);padding:3px 10px;border-radius:8px;font-size:13px}
.answer-card{background:#fff;border-radius:12px;padding:22px 26px;box-shadow:var(--card-shadow);margin-bottom:14px;display:flex;gap:18px;transition:box-shadow var(--transition)}
.answer-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.12)}
.vote-col{display:flex;flex-direction:column;align-items:center;gap:6px;min-width:42px}
.vote-btn{background:none;border:2px solid #e0e0e0;border-radius:8px;width:40px;height:40px;font-size:18px;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center}
.vote-btn:hover,.vote-btn.voted{background:var(--primary);border-color:var(--primary);color:#fff}
.vote-count{font-size:16px;font-weight:700;color:var(--primary)}
.answer-body-col{flex:1;min-width:0}
.answer-body{font-size:13px;color:#333;line-height:1.7;margin-bottom:12px;white-space:pre-wrap}
.alumni-chip{display:inline-flex;align-items:center;gap:8px;background:#f3e5f5;border-radius:8px;padding:6px 12px}
.alumni-avatar{width:28px;height:28px;background:#4a148c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.alumni-name{font-size:12px;font-weight:700;color:#4a148c}
.alumni-co{font-size:11px;color:#888}
.ans-date{font-size:11px;color:#aaa;margin-top:6px}
.no-answers{text-align:center;padding:40px;background:#fff;border-radius:12px;color:#888;font-size:14px;margin-bottom:14px;box-shadow:var(--card-shadow)}
.alert-success{background:var(--success-bg);color:var(--success);border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
@media(max-width:640px){
  .q-card{padding:20px 18px}
  .q-title{font-size:18px}
  .answer-card{flex-direction:column;gap:10px}
  .vote-col{flex-direction:row;justify-content:flex-start}
}
</style>

<a class="back-link" href="qa_board.php">
  <span class="material-icons-round" style="font-size:16px; vertical-align:middle;">arrow_back</span>
  Back to Q&A Board
</a>

<!-- Question -->
<div class="q-card <?= $q['is_resolved'] ? 'resolved' : '' ?>">
  <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
  <?php if ($tags_arr): ?>
  <div class="q-tags">
    <?php foreach ($tags_arr as $t): ?>
      <span class="tag"><?= htmlspecialchars($t) ?></span>
    <?php endforeach; ?>
    <?php if ($q['is_resolved']): ?><span class="badge-resolved">✅ Resolved</span><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="q-body"><?= htmlspecialchars($q['body']) ?></div>
  <div class="q-meta">
    <div class="asker">
      <div class="avatar"><?= strtoupper(substr($q['asker_name'],0,1)) ?></div>
      <div>
        <div style="font-weight:600;color:#333;font-size:12px"><?= htmlspecialchars($q['asker_name']) ?></div>
        <div><?= htmlspecialchars($q['specialization'] ?? 'MBA') ?> · <?= htmlspecialchars($q['batch'] ?? '') ?></div>
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
<div class="answers-header">
  💬 Answers <span class="count"><?= $ans_count ?></span>
</div>

<?php if (empty($ans_arr)): ?>
<div class="no-answers">🕐 No answers yet — alumni will respond soon! Keep checking back.</div>
<?php else: ?>
  <?php foreach ($ans_arr as $a): ?>
  <div class="answer-card" id="a<?= $a['answer_id'] ?>">
    <div class="vote-col">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="vote_answer" value="<?= $a['answer_id'] ?>">
        <button type="submit" class="vote-btn <?= $a['i_voted'] ? 'voted' : '' ?>" title="<?= $a['i_voted'] ? 'Remove vote' : 'Mark helpful' ?>">
          👍
        </button>
      </form>
      <div class="vote-count"><?= $a['vote_count'] ?></div>
    </div>
    <div class="answer-body-col">
      <div class="answer-body"><?= htmlspecialchars($a['body']) ?></div>
      <div class="alumni-chip">
        <div class="alumni-avatar"><?= strtoupper(substr($a['alumni_name'],0,1)) ?></div>
        <div>
          <div class="alumni-name">🌐 <?= htmlspecialchars($a['alumni_name']) ?></div>
          <div class="alumni-co"><?= htmlspecialchars($a['company_name']) ?></div>
        </div>
      </div>
      <div class="ans-date">Answered <?= date('d M Y, g:i A', strtotime($a['created_at'])) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<p style="font-size:13px;color:#888;text-align:center;margin-top:20px;margin-bottom:20px">
  Only alumni can post answers. Alumni — <a href="../alumni/qa_board.php" style="color:#4a148c;font-weight:600">visit Q&A Board</a> to reply.
</p>

<?php require '_layout_end.php'; ?>
