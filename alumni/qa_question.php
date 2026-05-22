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

$pageTitle  = 'Question Details';
$activePage = 'qa';
require '_layout.php';
?>

<style>
.container-qa {
  max-width: 860px;
  margin: 0 auto;
}
/* Question details */
.q-card-detail {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 28px;
  box-shadow: var(--card-shadow);
  border-top: 4px solid var(--primary);
  margin-bottom: 24px;
}
.q-card-detail.resolved {
  border-top-color: var(--success);
}
.q-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary);
  line-height: 1.4;
  margin-bottom: 12px;
}
.q-body {
  font-size: 14.5px;
  color: #333;
  line-height: 1.7;
  white-space: pre-wrap;
  margin-bottom: 20px;
}
.q-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.tag {
  background: #f1f3f5;
  color: #495057;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11.5px;
  font-weight: 600;
}
.badge-resolved {
  background: var(--success-bg);
  color: var(--success);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11.5px;
  font-weight: 700;
}
.q-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border);
  padding-top: 16px;
  flex-wrap: wrap;
  gap: 12px;
}
.asker-profile {
  display: flex;
  align-items: center;
  gap: 10px;
}
.asker-avatar {
  width: 36px; height: 36px;
  background: var(--accent-soft);
  color: var(--primary);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700;
}
.asker-info {
  display: flex;
  flex-direction: column;
}
.asker-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}
.asker-sub {
  font-size: 11.5px;
  color: var(--muted);
}
.asker-date {
  font-size: 12px;
  color: var(--muted);
  font-weight: 500;
}

/* Answer list & header */
.ans-header {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ans-count-badge {
  background: var(--accent-soft);
  color: var(--primary);
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 700;
}

.answer-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 24px;
  box-shadow: var(--card-shadow);
  margin-bottom: 16px;
  display: flex;
  gap: 20px;
  border: 1.5px solid transparent;
}
.answer-card.mine {
  border-color: rgba(74, 20, 140, 0.2);
  background: #fdfbfe;
}
.vote-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  min-width: 48px;
  flex-shrink: 0;
}
.vote-thumb {
  font-size: 18px;
  color: var(--accent);
}
.vote-count {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary);
}
.vote-lbl {
  font-size: 10px;
  color: var(--muted);
  font-weight: 500;
  text-transform: uppercase;
}

.ans-body-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ans-text {
  font-size: 14px;
  color: #333;
  line-height: 1.65;
  white-space: pre-wrap;
}
.alumni-author {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f8f9fa;
  padding: 6px 12px;
  border-radius: 8px;
  width: fit-content;
}
.alumni-avatar {
  width: 28px; height: 28px;
  background: var(--primary);
  color: #fff;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
}
.alumni-info {
  display: flex;
  flex-direction: column;
}
.alumni-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 6px;
}
.alumni-badge-you {
  background: var(--accent-soft);
  color: var(--primary);
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
}
.alumni-details {
  font-size: 11px;
  color: var(--muted);
}
.ans-date {
  font-size: 11.5px;
  color: var(--muted);
}
.ans-actions {
  display: flex;
  gap: 14px;
  margin-top: 4px;
}
.ans-act-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color var(--transition);
}
.ans-btn-edit { color: #1565c0; }
.ans-btn-edit:hover { color: #1e88e5; }
.ans-btn-delete { color: var(--danger); }
.ans-btn-delete:hover { color: #e53935; }

/* Answer form */
.ans-form-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 24px;
  box-shadow: var(--card-shadow);
  border-left: 4px solid var(--primary);
  margin-bottom: 28px;
}
textarea {
  min-height: 120px;
  resize: vertical;
}
.char-count {
  font-size: 11px;
  color: var(--muted);
  text-align: right;
  margin-top: 4px;
}

@media(max-width: 640px) {
  .answer-card {
    flex-direction: column;
    gap: 12px;
    padding: 16px;
  }
  .vote-col {
    flex-direction: row;
    justify-content: flex-start;
    gap: 8px;
  }
  .vote-panel {
    flex-direction: row;
    gap: 8px;
    align-items: center;
  }
  .q-card-detail {
    padding: 20px;
  }
}
</style>

<div class="container-qa">
  <a class="back-link" href="qa_board.php">
    <span class="material-icons-round" style="font-size:16px">arrow_back</span>
    Back to Q&amp;A Board
  </a>

  <?php if ($success): ?>
    <div class="success"><span class="material-icons-round">check_circle</span><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error"><span class="material-icons-round">error</span><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <div class="success"><span class="material-icons-round">check_circle</span>Your answer was deleted.</div>
  <?php endif; ?>

  <!-- Question Detail Card -->
  <div class="q-card-detail <?= $q['is_resolved'] ? 'resolved' : '' ?>">
    <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
    
    <div class="q-tags">
      <?php if ($q['is_resolved']): ?>
        <span class="badge-resolved">✅ Resolved</span>
      <?php endif; ?>
      <?php foreach ($tags_arr as $t): ?>
        <span class="tag"><?= htmlspecialchars($t) ?></span>
      <?php endforeach; ?>
    </div>
    
    <div class="q-body"><?= htmlspecialchars($q['body']) ?></div>
    
    <div class="q-meta">
      <div class="asker-profile">
        <div class="asker-avatar"><?= strtoupper(substr($q['asker_name'], 0, 1)) ?></div>
        <div class="asker-info">
          <span class="asker-name"><?= htmlspecialchars($q['asker_name']) ?></span>
          <span class="asker-sub"><?= htmlspecialchars($q['specialization'] ?? 'MBA') ?> • Batch <?= htmlspecialchars($q['batch'] ?? '') ?></span>
        </div>
      </div>
      <div class="asker-date">Asked on <?= date('d M Y, g:i A', strtotime($q['created_at'])) ?></div>
    </div>
  </div>

  <!-- Answer Posting Form -->
  <?php if (!$q['is_resolved']): ?>
    <div class="ans-form-card" id="answerForm">
      <div class="card-title" style="border:0;padding:0;margin-bottom:14px">
        <span class="material-icons-round" style="color:var(--primary)">rate_review</span>
        <span id="formTitle"><?= $editing ? 'Edit Your Answer' : 'Post Your Answer' ?></span>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="submit_answer" value="1">
        <?php if ($editing): ?>
          <input type="hidden" name="edit_id" value="<?= $editing['answer_id'] ?>">
        <?php endif; ?>
        
        <textarea name="body" id="ansBody" maxlength="3000" required
          oninput="document.getElementById('ansCC').textContent=this.value.length+'/3000'"
          placeholder="Share your industry experience, guidelines, or placement preparation advice. Be helpful, constructive and clear! Min 20 characters."><?= htmlspecialchars($editing['body'] ?? '') ?></textarea>
        <div class="char-count"><span id="ansCC"><?= strlen($editing['body'] ?? '') ?></span>/3000</div>
        
        <div style="margin-top: 14px">
          <button type="submit" class="btn">
            <span class="material-icons-round">send</span>
            <span id="btnText"><?= $editing ? 'Update Answer' : 'Post Answer' ?></span>
          </button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="card" style="background:#f8f9fa;color:var(--muted);text-align:center;padding:18px;border-radius:var(--card-radius);margin-bottom:24px">
      <span class="material-icons-round" style="vertical-align:middle;font-size:18px;margin-right:6px">lock</span>
      This question has been marked as resolved by the student and is no longer accepting answers.
    </div>
  <?php endif; ?>

  <!-- Answers List -->
  <?php $total_ans = count($ans_arr); ?>
  <div class="ans-header">
    <span class="material-icons-round">forum</span>
    Answers
    <span class="ans-count-badge"><?= $total_ans ?></span>
  </div>

  <?php if (empty($ans_arr)): ?>
    <div class="card" style="text-align:center;padding:36px;color:var(--muted)">
      <span class="material-icons-round" style="font-size:44px;color:var(--accent);margin-bottom:12px">chat_bubble_outline</span>
      <p style="font-size:14px;font-weight:600">No answers posted yet. Be the first alumni to help this student!</p>
    </div>
  <?php else: ?>
    <?php foreach ($ans_arr as $a):
      $is_mine = ((int)$a['alumni_id'] === $alumni_id);
    ?>
      <div class="answer-card <?= $is_mine ? 'mine' : '' ?>" id="a<?= $a['answer_id'] ?>">
        <div class="vote-panel">
          <span class="material-icons-round vote-thumb">thumb_up</span>
          <span class="vote-count"><?= (int)$a['vote_count'] ?></span>
          <span class="vote-lbl">helpful</span>
        </div>
        
        <div class="ans-body-col">
          <div class="ans-text"><?= htmlspecialchars($a['body']) ?></div>
          
          <div class="alumni-author">
            <div class="alumni-avatar"><?= strtoupper(substr($a['alumni_name'], 0, 1)) ?></div>
            <div class="alumni-info">
              <span class="alumni-name">
                <?= htmlspecialchars($a['alumni_name']) ?>
                <?php if ($is_mine): ?>
                  <span class="alumni-badge-you">You</span>
                <?php endif; ?>
              </span>
              <span class="alumni-details"><?= htmlspecialchars($a['company_name']) ?></span>
            </div>
          </div>
          
          <div class="ans-date">Answered on <?= date('d M Y, g:i A', strtotime($a['created_at'])) ?></div>
          
          <?php if ($is_mine && !$q['is_resolved']): ?>
            <div class="ans-actions">
              <button class="ans-act-btn ans-btn-edit" onclick="fillEdit(<?= htmlspecialchars(json_encode($a['body'])) ?>, <?= $a['answer_id'] ?>)">
                <span class="material-icons-round" style="font-size:14px">edit</span>
                Edit
              </button>
              <a class="ans-act-btn ans-btn-delete" href="qa_question.php?id=<?= $qid ?>&delete_answer=<?= $a['answer_id'] ?>"
                 onclick="return confirm('Are you sure you want to delete your answer?')">
                <span class="material-icons-round" style="font-size:14px">delete</span>
                Delete
              </a>
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
  
  let ei = document.querySelector('[name=edit_id]');
  if (!ei) {
    ei = document.createElement('input');
    ei.type = 'hidden'; ei.name = 'edit_id';
    document.querySelector('#answerForm form').appendChild(ei);
  }
  ei.value = aid;
  
  document.getElementById('formTitle').textContent = 'Edit Your Answer';
  document.getElementById('btnText').textContent = 'Update Answer';
  document.getElementById('answerForm').scrollIntoView({behavior:'smooth'});
}
</script>

<?php require '_layout_end.php'; ?>
