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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Q&A Board — Alumni Portal</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f0f2f5
    }

    .navbar {
      background: #4a148c;
      color: #fff;
      padding: 14px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .navbar h1 {
      font-size: 18px
    }

    .navbar a {
      color: #fff;
      text-decoration: none;
      font-size: 13px
    }

    .page-wrap {
      max-width: 1050px;
      margin: 0 auto;
      padding: 32px 24px
    }

    .back {
      display: inline-block;
      margin-bottom: 18px;
      color: #4a148c;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600
    }

    /* Hero */
    .qa-hero {
      background: linear-gradient(135deg, #4a148c 0%, #6a1b9a 60%, #7b1fa2 100%);
      border-radius: 16px;
      padding: 28px 32px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden
    }

    .qa-hero::before {
      content: '💬';
      position: absolute;
      right: 28px;
      top: 50%;
      transform: translateY(-50%) scale(1);
      font-size: 80px;
      opacity: .12;
      pointer-events: none
    }

    .qa-hero h2 {
      color: #fff;
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 4px
    }

    .qa-hero p {
      color: rgba(255, 255, 255, .75);
      font-size: 13px
    }

    /* Stats bar */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 12px;
      margin-bottom: 22px
    }

    .stat-chip {
      background: #fff;
      border-radius: 10px;
      padding: 14px 18px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .07)
    }

    .stat-chip .num {
      font-size: 24px;
      font-weight: 700;
      color: #4a148c
    }

    .stat-chip .lbl {
      font-size: 12px;
      color: #888;
      margin-top: 2px
    }

    /* Toolbar */
    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 20px
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
      transition: all .15s
    }

    .tab.active,
    .tab:hover {
      background: #4a148c;
      color: #fff;
      border-color: #4a148c
    }

    .search-box {
      flex: 1;
      min-width: 200px
    }

    .search-box input {
      width: 100%;
      padding: 9px 13px;
      border: 1.5px solid #e0e0e0;
      border-radius: 8px;
      font-size: 13px;
      outline: none
    }

    .search-box input:focus {
      border-color: #4a148c
    }

    /* Question list */
    .q-list {
      display: flex;
      flex-direction: column;
      gap: 14px
    }

    .q-card {
      background: #fff;
      border-radius: 12px;
      padding: 18px 22px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
      display: flex;
      gap: 16px;
      text-decoration: none;
      color: inherit;
      transition: box-shadow .15s, transform .1s;
      border-left: 4px solid #e1bee7
    }

    .q-card:hover {
      box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
      transform: translateY(-2px);
      border-left-color: #6a1b9a
    }

    .q-card.resolved {
      border-left-color: #2e7d32;
      opacity: .85
    }

    .q-card.answered-mine {
      border-left-color: #4a148c
    }

    .q-stats {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 52px;
      text-align: center;
      flex-shrink: 0
    }

    .stat-box {
      background: #f5f7ff;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 11px;
      color: #555
    }

    .stat-box .num {
      font-size: 18px;
      font-weight: 700;
      color: #4a148c;
      display: block
    }

    .stat-box.has-answers {
      background: #f3e5f5
    }

    .stat-box.unanswered {
      background: #fff3e0
    }

    .stat-box.unanswered .num {
      color: #e65100
    }

    .q-body {
      flex: 1;
      min-width: 0
    }

    .q-title {
      font-size: 15px;
      font-weight: 700;
      color: #1a237e;
      margin-bottom: 5px;
      line-height: 1.4
    }

    .q-excerpt {
      font-size: 13px;
      color: #666;
      line-height: 1.5;
      margin-bottom: 10px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden
    }

    .q-meta {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
      font-size: 12px;
      color: #888
    }

    .tag {
      background: #f3e5f5;
      color: #6a1b9a;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600
    }

    .badge-resolved {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700
    }

    .badge-mine {
      background: #ede7f6;
      color: #4a148c;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700
    }

    .badge-unanswered {
      background: #fff3e0;
      color: #e65100;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700
    }

    .btn-answer {
      background: #4a148c;
      color: #fff;
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      margin-left: auto;
      flex-shrink: 0;
      align-self: center;
      text-decoration: none;
      white-space: nowrap
    }

    .btn-answer:hover {
      background: #6a1b9a
    }

    .empty {
      text-align: center;
      padding: 60px;
      background: #fff;
      border-radius: 12px;
      color: #888;
      font-size: 14px
    }

    @media(max-width:640px) {
      .navbar {
        padding: 12px 16px
      }

      .page-wrap {
        padding: 20px 14px
      }

      .qa-hero {
        padding: 22px 20px
      }

      .q-card {
        flex-direction: column;
        gap: 10px
      }

      .q-stats {
        flex-direction: row;
        justify-content: flex-start
      }

      .toolbar {
        flex-direction: column;
        align-items: stretch
      }
    }
  </style>
</head>

<body>
  <div class="navbar">
    <h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a>
  </div>
  <div class="page-wrap">
    <a class="back" href="dashboard.php">← Back to Dashboard</a>

    <div class="qa-hero">
      <h2>💬 Student Q&A Board</h2>
      <!-- <p>Students are seeking guidance from alumni like you.<br>Browse open questions and share your industry experience!</p> -->
    </div>

    <?php
    // Quick stats
    $total_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_question"))['c'];
    $unanswered = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_question WHERE question_id NOT IN (SELECT DISTINCT question_id FROM qa_answer)"))['c'];
    $my_ans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM qa_answer WHERE alumni_id=$alumni_id"))['c'];
    ?>
    <div class="stats-bar">
      <div class="stat-chip">
        <div class="num"><?= $total_q ?></div>
        <div class="lbl">Total Questions</div>
      </div>
      <div class="stat-chip">
        <div class="num"><?= $unanswered ?></div>
        <div class="lbl">Awaiting Answer</div>
      </div>
      <div class="stat-chip">
        <div class="num"><?= $my_ans ?></div>
        <div class="lbl">My Answers</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <a class="tab <?= $filter === 'all' ? 'active' : '' ?>"
        href="qa_board.php?filter=all<?= $search ? '&q=' . urlencode($search) : '' ?>">All</a>
      <a class="tab <?= $filter === 'unanswered' ? 'active' : '' ?>"
        href="qa_board.php?filter=unanswered<?= $search ? '&q=' . urlencode($search) : '' ?>">Unanswered 🔥</a>
      <a class="tab <?= $filter === 'mine' ? 'active' : '' ?>"
        href="qa_board.php?filter=mine<?= $search ? '&q=' . urlencode($search) : '' ?>">My Answers</a>
      <form class="search-box" method="GET">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search questions or tags…">
      </form>
    </div>

    <!-- List -->
    <div class="q-list">
      <?php $found = false;
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
              ans
            </div>
          </div>
          <div class="q-body">
            <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
            <div class="q-excerpt"><?= htmlspecialchars($q['body']) ?></div>
            <div class="q-meta">
              <?php if ($q['is_resolved']): ?><span class="badge-resolved">✅ Resolved</span><?php endif; ?>
              <?php if ($i_answered): ?><span class="badge-mine">✔ You answered</span><?php endif; ?>
              <?php if (!$has_ans): ?><span class="badge-unanswered">⏳ Needs answer</span><?php endif; ?>
              <?php foreach ($tags_arr as $t): ?><span class="tag"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
              <span>by <strong><?= htmlspecialchars($q['asker_name']) ?></strong></span>
              <span><?= date('d M Y', strtotime($q['created_at'])) ?></span>
            </div>
          </div>
          <?php if (!$q['is_resolved']): ?>
            <span class="btn-answer"><?= $i_answered ? '📝 Edit Answer' : '✍️ Answer' ?></span>
          <?php endif; ?>
        </a>
      <?php endwhile; ?>
      <?php if (!$found): ?>
        <div class="empty">No questions match your filter. Try "All" to see everything.</div><?php endif; ?>
    </div>

  </div>
</body>

</html>