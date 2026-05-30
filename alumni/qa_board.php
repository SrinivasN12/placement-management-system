<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/qa_setup.php';
if ($role_id != 3) {
  header("Location: ../index.php");
  exit();
}
$alumni_id = (int) ($_SESSION['alumni_id'] ?? 0);

qa_setup($conn);

// ── Filters ────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';   // all | unanswered | mine
$search = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$having = "";
if ($filter === 'unanswered')
  $having = "HAVING answer_count = 0";
if ($filter === 'mine')
  $having = "HAVING my_answers > 0";
if ($search) {
  $se = mysqli_real_escape_string($conn, $search);
  $where .= " AND (qq.title LIKE '%$se%' OR qq.tags LIKE '%$se%')";
}

$questions = mysqli_query($conn, "
    SELECT qq.*,
           s.name AS asker_name,
           s.specialization,
           COUNT(DISTINCT a.answer_id)  AS answer_count,
           SUM(a.alumni_id = $alumni_id) AS my_answers
     FROM qa_question qq
     JOIN student s ON qq.student_id = s.student_id
     LEFT JOIN qa_answer a ON qq.question_id = a.question_id
     $where
     GROUP BY qq.question_id
     $having
     ORDER BY qq.is_resolved ASC, qq.created_at DESC
");

$pageTitle  = 'Student Q&A Board';
$activePage = 'qa';
require '_layout.php';
?>

<style>
/* Stats row */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 20px;
  box-shadow: var(--card-shadow);
  display: flex;
  align-items: center;
  gap: 16px;
  transition: transform var(--transition), box-shadow var(--transition);
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(74,20,140,.12);
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--primary);
  background: var(--accent-soft);
}
.stat-icon .material-icons-round { font-size: 22px; }
.stat-val { font-size: 24px; font-weight: 800; color: var(--text); }
.stat-lbl { font-size: 12px; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Toolbar controls */
.toolbar-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 16px 20px;
  box-shadow: var(--card-shadow);
  margin-bottom: 24px;
}
.toolbar-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}
.tabs-flex {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.filter-tab {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  background: #f8f9fa;
  border: 1.5px solid #e9ecef;
  transition: all var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.filter-tab:hover {
  color: var(--primary);
  background: var(--accent-soft);
  border-color: var(--accent-soft);
}
.filter-tab.active {
  color: #fff;
  background: var(--primary);
  border-color: var(--primary);
}
.search-form {
  position: relative;
  min-width: 260px;
  display: flex;
  align-items: center;
}
.search-input {
  padding: 8px 12px 8px 36px;
  font-size: 13.5px;
  border-radius: 8px;
  border: 1.5px solid #ddd;
}
.search-icon {
  position: absolute;
  left: 12px;
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
}

/* Question List */
.q-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.q-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 22px 24px;
  box-shadow: var(--card-shadow);
  display: flex;
  gap: 20px;
  border-left: 4px solid var(--accent);
  transition: transform var(--transition), box-shadow var(--transition);
  text-decoration: none;
  color: inherit;
}
.q-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(74,20,140,.12);
  border-left-color: var(--primary-light);
}
.q-card.resolved {
  border-left-color: var(--success);
  opacity: 0.9;
}
.q-card.answered-mine {
  border-left-color: var(--primary);
}

/* Question statistics block */
.q-stats {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 64px;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-box {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 11px;
  color: var(--muted);
  width: 100%;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.stat-box .num {
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
  line-height: 1.1;
}
.stat-box.has-answers {
  background: var(--accent-soft);
  color: var(--primary);
  border-color: rgba(74,20,140,.1);
}
.stat-box.has-answers .num {
  color: var(--primary);
}
.stat-box.unanswered {
  background: var(--warn-bg);
  color: var(--warn);
  border-color: rgba(230,81,0,.1);
}
.stat-box.unanswered .num {
  color: var(--warn);
}

/* Question details */
.q-details {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.q-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
  line-height: 1.4;
}
.q-excerpt {
  font-size: 13.5px;
  color: #555;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.q-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.tag-badge {
  background: #f1f3f5;
  color: #495057;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}
.badge-resolved {
  background: var(--success-bg);
  color: var(--success);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}
.badge-mine {
  background: var(--accent-soft);
  color: var(--primary);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}
.badge-unanswered {
  background: var(--warn-bg);
  color: var(--warn);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.q-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
  flex-wrap: wrap;
  margin-top: 4px;
}

.empty-state {
  text-align: center;
  padding: 48px;
  color: var(--muted);
}

@media(max-width: 640px) {
  .q-card {
    flex-direction: column;
    gap: 12px;
  }
  .q-stats {
    flex-direction: row;
    min-width: unset;
    width: 100%;
  }
  .stat-box {
    padding: 6px 12px;
    flex-direction: row;
    gap: 8px;
    justify-content: center;
    align-items: center;
  }
  .stat-box .num {
    font-size: 16px;
  }
  .toolbar-flex {
    flex-direction: column;
    align-items: stretch;
  }
  .search-form {
    min-width: unset;
  }
}
</style>

<div class="page-header">
  <h1>💬 Student Q&amp;A Board</h1>
  <p>Share your industry experience and placement tips by answering questions posted by current MBA students.</p>
</div>

<?php
// Quick statistics
$total_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_question"))['c'] ?? 0;
$unanswered_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_question WHERE question_id NOT IN (SELECT DISTINCT question_id FROM qa_answer)"))['c'] ?? 0;
$my_ans_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_answer WHERE alumni_id=$alumni_id"))['c'] ?? 0;
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><span class="material-icons-round">help_outline</span></div>
    <div>
      <div class="stat-val"><?= $total_q ?></div>
      <div class="stat-lbl">Total Questions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--warn-bg);color:var(--warn)"><span class="material-icons-round">pending_actions</span></div>
    <div>
      <div class="stat-val"><?= $unanswered_q ?></div>
      <div class="stat-lbl">Awaiting Answer</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-bg);color:var(--success)"><span class="material-icons-round">done_all</span></div>
    <div>
      <div class="stat-val"><?= $my_ans_q ?></div>
      <div class="stat-lbl">My Answers</div>
    </div>
  </div>
</div>

<!-- Toolbar & Search -->
<div class="toolbar-card">
  <div class="toolbar-flex">
    <div class="tabs-flex">
      <a class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>" href="qa_board.php?filter=all<?= $search ? '&q=' . urlencode($search) : '' ?>">
        All Questions
      </a>
      <a class="filter-tab <?= $filter === 'unanswered' ? 'active' : '' ?>" href="qa_board.php?filter=unanswered<?= $search ? '&q=' . urlencode($search) : '' ?>">
        Unanswered
      </a>
      <a class="filter-tab <?= $filter === 'mine' ? 'active' : '' ?>" href="qa_board.php?filter=mine<?= $search ? '&q=' . urlencode($search) : '' ?>">
        My Answers
      </a>
    </div>
    
    <form class="search-form" method="GET">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <span class="material-icons-round search-icon">search</span>
      <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search questions or tags...">
    </form>
  </div>
</div>

<!-- Question Cards -->
<div class="q-list">
  <?php
  $found = false;
  while ($q = mysqli_fetch_assoc($questions)):
    $found = true;
    $tags_arr = array_filter(array_map('trim', explode(',', $q['tags'])));
    $has_ans = $q['answer_count'] > 0;
    $i_answered = $q['my_answers'] > 0;
    $cls = $q['is_resolved'] ? 'resolved' : ($i_answered ? 'answered-mine' : '');
  ?>
    <a class="q-card <?= $cls ?>" href="qa_question.php?id=<?= $q['question_id'] ?>">
      <div class="q-stats">
        <div class="stat-box <?= $has_ans ? 'has-answers' : 'unanswered' ?>">
          <span class="num"><?= $q['answer_count'] ?></span>
          <span><?= $q['answer_count'] == 1 ? 'answer' : 'answers' ?></span>
        </div>
      </div>
      
      <div class="q-details">
        <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
        <div class="q-excerpt"><?= htmlspecialchars($q['body']) ?></div>
        
        <div class="q-tags">
          <?php if ($q['is_resolved']): ?>
            <span class="badge-resolved">✅ Resolved</span>
          <?php endif; ?>
          <?php if ($i_answered): ?>
            <span class="badge-mine">✔ Answered</span>
          <?php endif; ?>
          <?php if (!$has_ans): ?>
            <span class="badge-unanswered">⏳ Needs Answer</span>
          <?php endif; ?>
          <?php foreach ($tags_arr as $t): ?>
            <span class="tag-badge"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>

        <div class="q-meta">
          <span>By <strong><?= htmlspecialchars($q['asker_name']) ?></strong> (<?= htmlspecialchars($q['specialization'] ?? 'MBA') ?>)</span>
          <span>•</span>
          <span>Posted <?= date('d M Y', strtotime($q['created_at'])) ?></span>
        </div>
      </div>
    </a>
  <?php endwhile; ?>
  
  <?php if (!$found): ?>
    <div class="card empty-state">
      <span class="material-icons-round" style="font-size:48px;color:var(--accent);margin-bottom:12px">forum</span>
      <p style="font-size:14px;font-weight:600">No questions found matching your filter/search criteria.</p>
    </div>
  <?php endif; ?>
</div>

<?php require '_layout_end.php'; ?>