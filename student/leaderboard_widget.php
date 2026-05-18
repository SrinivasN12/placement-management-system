<?php
// =============================================================
//  student/leaderboard_widget.php
//  Accepts GET: drive_id
//  Returns an embeddable HTML fragment (no <html>/<head>/<body>)
//  Embed via: fetch('leaderboard_widget.php?drive_id=X')
//              .then(r=>r.text()).then(html => el.innerHTML = html)
// =============================================================
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 1) {
    echo '<p style="color:#c62828;font-size:13px;">Unauthorized.</p>';
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$drive_id   = (int)($_GET['drive_id'] ?? 0);

if (!$drive_id) {
    echo '<p style="color:#c62828;font-size:13px;">Invalid drive.</p>';
    exit();
}

// ── Fetch mock_test for this drive (any difficulty — best score) ──
// We join on the drive's mock tests, pick best attempt per student
$rows_res = mysqli_query($conn,
    "SELECT
        s.name,
        s.department,
        a.score,
        a.total,
        a.time_taken,
        a.submitted_at,
        a.student_id,
        mt.difficulty
     FROM mock_test_attempts a
     JOIN mock_tests mt   ON a.mock_test_id = mt.mock_test_id
     JOIN student s       ON a.student_id   = s.student_id
     WHERE mt.drive_id = $drive_id
     ORDER BY a.score DESC, a.time_taken ASC
     LIMIT 50"
);

$all_rows = [];
while ($r = mysqli_fetch_assoc($rows_res)) {
    $all_rows[] = $r;
}

// De-duplicate: one entry per student (best score / fastest time already sorted)
$seen    = [];
$entries = [];
foreach ($all_rows as $r) {
    $sid = (int)$r['student_id'];
    if (!isset($seen[$sid])) {
        $seen[$sid] = true;
        $entries[]  = $r;
    }
}

// Assign ranks
$current_rank = null;
$current_entry = null;
$top10 = array_slice($entries, 0, 10);

foreach ($entries as $idx => $e) {
    if ((int)$e['student_id'] === $student_id) {
        $current_rank  = $idx + 1;
        $current_entry = $e;
        break;
    }
}

// ── Helper: format time ───────────────────────────────────────
function fmt_time(int $seconds): string {
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return sprintf('%d:%02d', $m, $s);
}

// ── Helper: privacy name (first + last initial) ───────────────
function privacy_name(string $full): string {
    $parts = explode(' ', trim($full));
    if (count($parts) === 1) return $parts[0];
    $last_init = strtoupper(substr(end($parts), 0, 1)) . '.';
    return $parts[0] . ' ' . $last_init;
}

// ── Render ────────────────────────────────────────────────────
?>
<style>
.lb-wrap{font-family:'Segoe UI',sans-serif;font-size:13px}
.lb-title{font-size:15px;font-weight:700;color:#185FA5;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.lb-empty{text-align:center;padding:28px;color:#888;font-size:13px;background:#f8f9fa;border-radius:8px}
.lb-table{width:100%;border-collapse:collapse}
.lb-table th{background:#185FA5;color:#fff;padding:9px 12px;text-align:left;font-size:12px;font-weight:600}
.lb-table th:nth-child(1),.lb-table td:nth-child(1){text-align:center;width:50px}
.lb-table th:nth-child(4),.lb-table td:nth-child(4),.lb-table th:nth-child(5),.lb-table td:nth-child(5){text-align:center}
.lb-table td{padding:9px 12px;border-bottom:1px solid #f0f0f0;color:#333}
.lb-table tr:hover td{background:#f0f6ff}
.lb-gold   td{background:#fffbea!important}
.lb-silver td{background:#f5f5f5!important}
.lb-bronze td{background:#fff3ee!important}
.lb-me     td{background:#e8f0fe!important;font-weight:600;color:#185FA5!important}
.lb-medal{font-size:16px}
.lb-rank-badge{display:inline-block;width:26px;height:26px;border-radius:50%;background:#e5e7eb;text-align:center;line-height:26px;font-weight:700;font-size:12px}
.lb-separator{text-align:center;padding:6px;color:#aaa;font-size:11px;border-top:2px dashed #ddd}
.lb-score{font-weight:700;color:#185FA5}
.lb-pct{font-size:11px;color:#666}
</style>

<div class="lb-wrap">
  <div class="lb-title">🏆 Leaderboard</div>
  <?php if (empty($entries)): ?>
    <div class="lb-empty">No attempts yet — be the first! 🚀</div>
  <?php else: ?>
  <table class="lb-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Student</th>
        <th>Dept</th>
        <th>Score</th>
        <th>Time</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($top10 as $rank_idx => $e):
        $rank     = $rank_idx + 1;
        $is_me    = (int)$e['student_id'] === $student_id;
        $pct      = $e['total'] > 0 ? round(($e['score'] / $e['total']) * 100) : 0;
        $row_class = '';
        $medal     = '';
        if ($rank === 1) { $row_class = 'lb-gold';   $medal = '🥇'; }
        elseif ($rank === 2) { $row_class = 'lb-silver'; $medal = '🥈'; }
        elseif ($rank === 3) { $row_class = 'lb-bronze'; $medal = '🥉'; }
        if ($is_me) $row_class = 'lb-me';
    ?>
    <tr class="<?= $row_class ?>">
      <td>
        <?php if ($medal && !$is_me): ?>
          <span class="lb-medal"><?= $medal ?></span>
        <?php else: ?>
          <span class="lb-rank-badge"><?= $rank ?></span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars(privacy_name($e['name'])) ?><?= $is_me ? ' <span style="font-size:10px;background:#185FA5;color:#fff;padding:1px 5px;border-radius:8px;margin-left:4px">You</span>' : '' ?></td>
      <td><?= htmlspecialchars($e['department']) ?></td>
      <td>
        <span class="lb-score"><?= $e['score'] ?>/<?= $e['total'] ?></span>
        <span class="lb-pct">(<?= $pct ?>%)</span>
      </td>
      <td><?= fmt_time((int)$e['time_taken']) ?></td>
    </tr>
    <?php endforeach; ?>

    <?php
    // Show current student below top 10 if they're outside it
    if ($current_rank !== null && $current_rank > 10 && $current_entry !== null):
        $pct = $current_entry['total'] > 0 ? round(($current_entry['score'] / $current_entry['total']) * 100) : 0;
    ?>
    <tr>
      <td colspan="5" class="lb-separator">· · · · · · · · · ·</td>
    </tr>
    <tr class="lb-me">
      <td><span class="lb-rank-badge"><?= $current_rank ?></span></td>
      <td><?= htmlspecialchars(privacy_name($current_entry['name'])) ?> <span style="font-size:10px;background:#185FA5;color:#fff;padding:1px 5px;border-radius:8px;margin-left:4px">You</span></td>
      <td><?= htmlspecialchars($current_entry['department']) ?></td>
      <td>
        <span class="lb-score"><?= $current_entry['score'] ?>/<?= $current_entry['total'] ?></span>
        <span class="lb-pct">(<?= $pct ?>%)</span>
      </td>
      <td><?= fmt_time((int)$current_entry['time_taken']) ?></td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>
  <p style="font-size:11px;color:#999;margin-top:8px;text-align:right">Ranked by score ↓ then fastest time ↑</p>
  <?php endif; ?>
</div>
