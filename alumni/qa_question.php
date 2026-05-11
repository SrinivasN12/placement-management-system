<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/qa_setup.php';
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);

qa_setup($conn);

$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header("Location: qa_board.php"); exit(); }

$success = $error = "";

// ── Load question ──────────────────────────────────────────────
$q = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT qq.*, s.name AS asker_name, s.specialization, s.batch
     FROM qa_question qq
     JOIN student s ON qq.student_id = s.student_id
     WHERE qq.question_id = $qid"));
if (!$q) { header("Location: qa_board.php"); exit(); }

// ── Post or edit answer ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    validate_csrf();
    $body    = trim($_POST['body']);
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (strlen($body) < 20) {
        $error = "Answer must be at least 20 characters.";
    } elseif ($edit_id) {
        // Update own existing answer
        $stmt = mysqli_prepare($conn,
            "UPDATE qa_answer SET body=? WHERE answer_id=? AND alumni_id=?");
        mysqli_stmt_bind_param($stmt, "sii", $body, $edit_id, $alumni_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success = "Answer updated!";
    } else {
        // Check not already answered
        $already = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT answer_id FROM qa_answer WHERE question_id=$qid AND alumni_id=$alumni_id"));
        if ($already) {
            $error = "You've already answered this question. Scroll down to edit your existing answer.";
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO qa_answer (question_id,alumni_id,body) VALUES (?,?,?)");
            mysqli_stmt_bind_param($stmt, "iis", $qid, $alumni_id, $body);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success = "Your answer has been posted!";
        }
    }
}

// ── Delete own answer ──────────────────────────────────────────
if (isset($_GET['delete_answer'])) {
    $aid = (int)$_GET['delete_answer'];
    mysqli_query($conn,
        "DELETE FROM qa_answer WHERE answer_id=$aid AND alumni_id=$alumni_id");
    header("Location: qa_question.php?id=$qid&deleted=1"); exit();
}

// ── Load answers ───────────────────────────────────────────────
$answers = mysqli_query($conn,
    "SELECT a.*, al.name AS alumni_name, al.company_name,
            COUNT(v.vote_id) AS vote_count
     FROM qa_answer a
     JOIN alumni al ON a.alumni_id = al.alumni_id
     LEFT JOIN qa_vote v ON a.answer_id = v.answer_id
     WHERE a.question_id = $qid
     GROUP BY a.answer_id
     ORDER BY vote_count DESC, a.created_at ASC");

$ans_arr   = [];
$my_answer = null;
while ($a = mysqli_fetch_assoc($answers)) {
    if ((int)$a['alumni_id'] === $alumni_id) $my_answer = $a;
    else $ans_arr[] = $a;
}
if ($my_answer) array_unshift($ans_arr, $my_answer); // put own first

$tags_arr = array_filter(array_map('trim', explode(',', $q['tags'])));

// Determine if we're editing
$editing = $my_answer; // if alumni has already answered, show edit form
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($q['title']) ?> — Q&A</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#4a148c;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:860px;margin:0 auto;padding:32px 24px}
.back{display:inline-block;margin-bottom:18px;color:#4a148c;text-decoration:none;font-size:13px;font-weight:600}

/* Question */
.q-card{background:#fff;border-radius:14px;padding:28px 32px;box-shadow:0 2px 10px rgba(0,0,0,.08);margin-bottom:24px;border-top:4px solid #6a1b9a}
.q-card.resolved{border-top-color:#2e7d32}
.q-title{font-size:20px;font-weight:800;color:#1a237e;margin-bottom:10px;line-height:1.4}
.q-body{font-size:14px;color:#444;line-height:1.7;margin-bottom:14px;white-space:pre-wrap}
.q-tags{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.tag{background:#f3e5f5;color:#6a1b9a;padding:4px 11px;border-radius:6px;font-size:12px;font-weight:600}
.badge-resolved{background:#e8f5e9;color:#2e7d32;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:700}
.q-meta{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:#888;padding-top:12px;border-top:1px solid #f0f0f0}
.asker{display:flex;align-items:center;gap:8px}
.avatar{width:30px;height:30px;background:#e8eaf6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#3949ab}

/* Answer form */
.ans-form-card{background:#fff;border-radius:14px;padding:24px 28px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:24px;border-left:4px solid #4a148c}
.ans-form-card h3{font-size:15px;font-weight:700;color:#4a148c;margin-bottom:14px}
textarea{width:100%;padding:12px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none;font-family:inherit;resize:vertical;min-height:130px}
textarea:focus{border-color:#4a148c}
.btn-post{background:#4a148c;color:#fff;border:none;padding:11px 26px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-right:8px}
.btn-post:hover{background:#6a1b9a}
.char-count{font-size:11px;color:#aaa;text-align:right;margin-top:4px}

/* Answers */
.answers-header{font-size:16px;font-weight:700;color:#1a237e;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.answers-header .count{background:#f3e5f5;color:#4a148c;padding:3px 10px;border-radius:8px;font-size:13px}
.answer-card{background:#fff;border-radius:12px;padding:22px 26px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:14px;display:flex;gap:16px}
.answer-card.mine{border:2px solid #ce93d8}
.vote-col{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:42px}
.vote-count{font-size:16px;font-weight:700;color:#4a148c}
.vote-label{font-size:10px;color:#aaa}
.answer-body-col{flex:1;min-width:0}
.answer-body{font-size:13px;color:#333;line-height:1.7;margin-bottom:12px;white-space:pre-wrap}
.alumni-chip{display:inline-flex;align-items:center;gap:8px;background:#f3e5f5;border-radius:8px;padding:6px 12px}
.alumni-avatar{width:28px;height:28px;background:#4a148c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.alumni-name{font-size:12px;font-weight:700;color:#4a148c}
.alumni-co{font-size:11px;color:#888}
.mine-badge{background:#ede7f6;color:#4a148c;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;margin-left:8px}
.ans-actions{display:flex;gap:10px;margin-top:8px}
.btn-edit-ans{font-size:12px;color:#1565c0;font-weight:600;cursor:pointer;background:none;border:none;padding:0}
.btn-del-ans{font-size:12px;color:#c62828;font-weight:600;cursor:pointer;background:none;border:none;padding:0;text-decoration:none}
.ans-date{font-size:11px;color:#aaa;margin-top:6px}
.no-answers{text-align:center;padding:36px;background:#fff;border-radius:12px;color:#888;font-size:14px;margin-bottom:14px}
.alert-success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
.alert-error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px}
@media(max-width:640px){
  .navbar{padding:12px 16px}
  .navbar h1{font-size:15px}
  .page-wrap{padding:18px 14px}
  .q-card,.ans-form-card{padding:18px 16px}
  .q-title{font-size:17px}
  .answer-card{flex-direction:column;gap:10px;padding:18px 16px}
  .vote-col{flex-direction:row;justify-content:flex-start;gap:8px;min-width:unset}
  .vote-label{display:none}
  .btn-post{width:100%;margin-right:0;padding:13px;font-size:15px}
  .q-meta{flex-direction:column;gap:6px;align-items:flex-start}
  .alumni-chip{flex-wrap:wrap}
  h2,.answers-header{font-size:17px}
}
</style></head><body>
<div class="navbar"><h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="qa_board.php">← Back to Q&A Board</a>

<?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert-success">✅ Your answer was deleted.</div><?php endif; ?>

<!-- Question -->
<div class="q-card <?= $q['is_resolved'] ? 'resolved' : '' ?>">
  <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
  <?php if ($tags_arr): ?>
  <div class="q-tags">
    <?php foreach ($tags_arr as $t): ?><span class="tag"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
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
    <span>Asked <?= date('d M Y, g:i A', strtotime($q['created_at'])) ?></span>
  </div>
</div>

<!-- Answer Form -->
<?php if (!$q['is_resolved']): ?>
<div class="ans-form-card" id="answerForm">
  <h3><?= $editing ? '✏️ Edit Your Answer' : '✍️ Post Your Answer' ?></h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="submit_answer" value="1">
    <?php if ($editing): ?>
      <input type="hidden" name="edit_id" value="<?= $editing['answer_id'] ?>">
    <?php endif; ?>
    <textarea name="body" id="ansBody" maxlength="3000"
      oninput="document.getElementById('ansCC').textContent=this.value.length+'/3000'"
      placeholder="Share your experience, tips, or advice. Be specific and helpful! Min 20 characters."><?= htmlspecialchars($editing['body'] ?? '') ?></textarea>
    <div class="char-count"><span id="ansCC"><?= strlen($editing['body'] ?? '') ?></span>/3000</div>
    <div style="margin-top:14px">
      <button type="submit" class="btn-post">
        <?= $editing ? '💾 Update Answer' : '🚀 Post Answer' ?>
      </button>
    </div>
  </form>
</div>
<?php else: ?>
<div style="background:#f9f9f9;border-radius:10px;padding:14px 18px;font-size:13px;color:#888;margin-bottom:22px;text-align:center">
  🔒 This question has been marked as resolved by the student and is no longer accepting answers.
</div>
<?php endif; ?>

<!-- All Answers -->
<?php $total_ans = count($ans_arr); ?>
<div class="answers-header">
  💬 <?= $total_ans ?> <?= $total_ans == 1 ? 'Answer' : 'Answers' ?>
  <span class="count"><?= $total_ans ?></span>
</div>

<?php if (empty($ans_arr)): ?>
<div class="no-answers">🎯 No answers yet — be the first alumni to help this student!</div>
<?php else: ?>
  <?php foreach ($ans_arr as $a):
    $is_mine = ((int)$a['alumni_id'] === $alumni_id);
  ?>
  <div class="answer-card <?= $is_mine ? 'mine' : '' ?>" id="a<?= $a['answer_id'] ?>">
    <div class="vote-col">
      <div style="font-size:18px">👍</div>
      <div class="vote-count"><?= $a['vote_count'] ?></div>
      <div class="vote-label">helpful</div>
    </div>
    <div class="answer-body-col">
      <div class="answer-body"><?= htmlspecialchars($a['body']) ?></div>
      <div class="alumni-chip">
        <div class="alumni-avatar"><?= strtoupper(substr($a['alumni_name'],0,1)) ?></div>
        <div>
          <div class="alumni-name">🌐 <?= htmlspecialchars($a['alumni_name']) ?> <?= $is_mine ? '<span class="mine-badge">You</span>' : '' ?></div>
          <div class="alumni-co"><?= htmlspecialchars($a['company_name']) ?></div>
        </div>
      </div>
      <div class="ans-date">Answered <?= date('d M Y, g:i A', strtotime($a['created_at'])) ?></div>
      <?php if ($is_mine && !$q['is_resolved']): ?>
      <div class="ans-actions">
        <button class="btn-edit-ans" onclick="fillEdit(<?= htmlspecialchars(json_encode($a['body'])) ?>,<?= $a['answer_id'] ?>)">✏️ Edit</button>
        <a class="btn-del-ans" href="qa_question.php?id=<?= $qid ?>&delete_answer=<?= $a['answer_id'] ?>"
           onclick="return confirm('Delete your answer?')">🗑 Delete</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

</div>

<script>
function fillEdit(body, aid) {
  document.getElementById('ansBody').value = body;
  document.getElementById('ansCC').textContent = body.length + '/3000';
  // Update hidden edit_id
  let ei = document.querySelector('[name=edit_id]');
  if (!ei) {
    ei = document.createElement('input');
    ei.type = 'hidden'; ei.name = 'edit_id';
    document.querySelector('form').appendChild(ei);
  }
  ei.value = aid;
  document.getElementById('answerForm').scrollIntoView({behavior:'smooth'});
  document.querySelector('.ans-form-card h3').textContent = '✏️ Edit Your Answer';
}
</script>
</body></html>
