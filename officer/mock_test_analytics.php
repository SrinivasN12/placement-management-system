<?php
// =============================================================
//  officer/mock_test_analytics.php
//  Admin analytics for AI Mock Tests — per placement drive
//  GET param: drive_id
// =============================================================
require_once '../config/auth.php';
require_once '../config/db.php';

// Officer-only
if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

$drive_id = (int)($_GET['drive_id'] ?? 0);

// Fetch all drives for the selector
$all_drives_res = mysqli_query($conn,
    "SELECT drive_id, job_title, company_name FROM placement_drive ORDER BY created_at DESC"
);
$all_drives = [];
while ($r = mysqli_fetch_assoc($all_drives_res)) $all_drives[] = $r;

// ── Data fetching (only if drive selected) ────────────────────
$drive_info    = null;
$attempts      = [];
$q_stats       = [];
$summary       = ['total'=>0,'avg_pct'=>0,'highest'=>0,'pass_rate'=>0];
$distribution  = ['0-20'=>0,'21-40'=>0,'41-60'=>0,'61-80'=>0,'81-100'=>0];

if ($drive_id) {
    $drive_info = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT drive_id, job_title, company_name FROM placement_drive WHERE drive_id=$drive_id"
    ));

    if ($drive_info) {
        // ── Fetch all attempts for this drive ──────────────────
        $att_res = mysqli_query($conn,
            "SELECT
                a.attempt_id,
                a.student_id,
                a.score,
                a.total,
                a.time_taken,
                a.answers,
                a.submitted_at,
                mt.mock_test_id,
                mt.questions,
                mt.difficulty,
                s.name     AS student_name,
                s.department
             FROM mock_test_attempts a
             JOIN mock_tests mt ON a.mock_test_id = mt.mock_test_id
             JOIN student s     ON a.student_id   = s.student_id
             WHERE mt.drive_id = $drive_id
             ORDER BY a.score DESC, a.time_taken ASC"
        );

        $raw_attempts = [];
        while ($r = mysqli_fetch_assoc($att_res)) $raw_attempts[] = $r;

        // De-duplicate per student (best attempt already sorted first)
        $seen = [];
        foreach ($raw_attempts as $r) {
            $sid = (int)$r['student_id'];
            if (!isset($seen[$sid])) {
                $seen[$sid] = true;
                $attempts[] = $r;
            }
        }

        // ── Summary stats ─────────────────────────────────────
        $total_att = count($attempts);
        if ($total_att > 0) {
            $sum_pct   = 0;
            $highest   = 0;
            $pass_cnt  = 0;

            foreach ($attempts as $a) {
                $pct = $a['total'] > 0 ? ($a['score'] / $a['total']) * 100 : 0;
                $sum_pct += $pct;
                $highest  = max($highest, (int)$a['score']);
                if ($pct >= 60) $pass_cnt++;

                // Distribution bucket
                if      ($pct <= 20)  $distribution['0-20']++;
                elseif  ($pct <= 40)  $distribution['21-40']++;
                elseif  ($pct <= 60)  $distribution['41-60']++;
                elseif  ($pct <= 80)  $distribution['61-80']++;
                else                  $distribution['81-100']++;
            }

            $summary = [
                'total'     => $total_att,
                'avg_pct'   => round($sum_pct / $total_att, 1),
                'highest'   => $highest,
                'pass_rate' => round(($pass_cnt / $total_att) * 100, 1),
            ];
        }

        // ── Per-question analytics ────────────────────────────
        // Use first mock_test for this drive (all share same questions per difficulty)
        if (!empty($raw_attempts)) {
            $first_qa  = $raw_attempts[0];
            $questions = json_decode($first_qa['questions'], true) ?: [];
            $q_count   = count($questions);
            $q_correct = array_fill(0, $q_count, 0);
            $q_total   = array_fill(0, $q_count, 0);

            foreach ($raw_attempts as $a) {
                $ans = json_decode($a['answers'], true) ?: [];
                $qs  = json_decode($a['questions'], true) ?: [];
                foreach ($qs as $qi => $q) {
                    if (!isset($q_total[$qi])) { $q_total[$qi] = 0; $q_correct[$qi] = 0; }
                    $q_total[$qi]++;
                    $student_ans = isset($ans[(string)$qi]) ? (int)$ans[(string)$qi] : -1;
                    if ($student_ans === (int)($q['correct'] ?? -99)) $q_correct[$qi]++;
                }
            }

            foreach ($questions as $qi => $q) {
                $tot = $q_total[$qi] ?? 0;
                $cor = $q_correct[$qi] ?? 0;
                $acc = $tot > 0 ? round(($cor / $tot) * 100, 1) : 0;
                $q_stats[] = [
                    'index'    => $qi + 1,
                    'category' => $q['category']    ?? 'General',
                    'question' => substr($q['question'] ?? '', 0, 80) . (strlen($q['question'] ?? '') > 80 ? '…' : ''),
                    'correct'  => $cor,
                    'wrong'    => $tot - $cor,
                    'total'    => $tot,
                    'accuracy' => $acc,
                ];
            }
        }
    }
}

// ── Helper: bar chart color for accuracy ─────────────────────
function acc_color(float $acc): string {
    if ($acc >= 70) return '#3B6D11';
    if ($acc >= 40) return '#BA7517';
    return '#A32D2D';
}

function acc_bg(float $acc): string {
    if ($acc >= 70) return '#f0f7e8';
    if ($acc >= 40) return '#fff8e8';
    return '#fff0f0';
}

// ── Helper: format seconds ────────────────────────────────────
function fmt_secs(int $s): string {
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

// ── Rank badge medals ─────────────────────────────────────────
$medals = ['🥇', '🥈', '🥉'];

$max_dist = max(max($distribution), 1);

$page_title  = 'Mock Test Analytics — PMS';
$active_page = 'mock';
$extra_head  = '<style>
.page-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px}
.page-title{font-size:20px;font-weight:700;color:var(--pms-green)}
.page-sub{font-size:13px;color:#6c757d;margin-top:4px}
.drive-selector{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.drive-selector label{font-size:13px;font-weight:600;color:#333;white-space:nowrap}
.drive-selector select{padding:8px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;outline:none;min-width:280px}
.drive-selector select:focus{border-color:var(--pms-green)}
.btn-go{background:var(--pms-green);color:#fff;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-go:hover{background:var(--pms-green-mid)}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;text-align:center;position:relative;overflow:hidden}
.card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px}
.card-blue::before{background:#185FA5}
.card-green::before{background:#3B6D11}
.card-amber::before{background:#BA7517}
.card-red::before{background:#A32D2D}
.card-icon{font-size:26px;margin-bottom:8px}
.card-value{font-size:28px;font-weight:800;line-height:1}
.card-blue .card-value{color:#185FA5}
.card-green .card-value{color:#3B6D11}
.card-amber .card-value{color:#BA7517}
.card-red .card-value{color:#A32D2D}
.card-label{font-size:12px;color:#888;margin-top:6px;font-weight:500}
.section{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:22px}
.section-title{font-size:15px;font-weight:700;color:var(--pms-green);margin-bottom:16px;display:flex;align-items:center;gap:8px;border-bottom:2px solid var(--pms-green-pale);padding-bottom:10px}
.lb-table{width:100%;border-collapse:collapse;font-size:13px}
.lb-table th{background:var(--pms-green);color:#fff;padding:10px 14px;text-align:left;font-weight:600;font-size:12px}
.lb-table td{padding:10px 14px;border-bottom:1px solid #f0f0f0}
.lb-table tr:last-child td{border-bottom:none}
.lb-table tbody tr:hover td{background:#f5f8ff}
.rank-cell{text-align:center;width:60px;font-weight:700}
.pct-cell,.score-cell,.time-cell{text-align:center}
.tr-gold td{background:#fffde7}
.tr-silver td{background:#f5f5f5}
.tr-bronze td{background:#fff3e0}
.badge{display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600}
.badge-pass{background:#e8f5e9;color:#1b5e20}
.badge-fail{background:#ffebee;color:#A32D2D}
.q-table{width:100%;border-collapse:collapse;font-size:12px}
.q-table th{background:#f5f5f5;color:#555;padding:9px 12px;text-align:left;font-weight:600;font-size:11px;border-bottom:2px solid #e0e0e0}
.q-table td{padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.q-table tr:last-child td{border-bottom:none}
.q-cat{display:inline-block;padding:2px 8px;border-radius:10px;background:#e8f0fe;color:#185FA5;font-size:11px;font-weight:600}
.acc-pill{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700}
.q-text-col{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#444;font-size:12px}
.dist-chart{display:flex;flex-direction:column;gap:10px}
.dist-row{display:grid;grid-template-columns:80px 1fr 60px;align-items:center;gap:12px}
.dist-label{font-size:12px;font-weight:600;color:#555;text-align:right}
.dist-bar-bg{background:#f0f2f5;border-radius:4px;height:24px;overflow:hidden}
.dist-bar{height:100%;border-radius:4px;background:var(--pms-green);transition:width .4s;display:flex;align-items:center;padding-left:8px;color:#fff;font-size:11px;font-weight:700;min-width:2px}
.dist-count{font-size:12px;font-weight:700;color:#555;text-align:center}
.empty-state{text-align:center;padding:40px;color:#999;font-size:14px}
.no-drive{background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:20px;text-align:center;color:#6d4c00;font-size:14px}
@media(max-width:768px){.cards{grid-template-columns:1fr 1fr}.lb-table th:nth-child(3),.lb-table td:nth-child(3),.lb-table th:nth-child(7),.lb-table td:nth-child(7){display:none}}
@media(max-width:480px){.cards{grid-template-columns:1fr}}
</style>';
require_once '_nav.php';
?>


<div class="page-head">
  <div>
    <div class="page-title">🎯 AI Mock Test Analytics</div>
    <div class="page-sub">Review student performance on AI-generated placement mock tests.</div>
  </div>
</div>

  <!-- Drive Selector -->
  <div class="drive-selector">
    <label for="driveSelect">📋 Select Drive:</label>
    <form method="get" action="" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <select name="drive_id" id="driveSelect">
        <option value="">— Choose a placement drive —</option>
        <?php foreach ($all_drives as $d): ?>
          <option value="<?= $d['drive_id'] ?>" <?= $drive_id == $d['drive_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['job_title']) ?> @ <?= htmlspecialchars($d['company_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-go">View Analytics →</button>
    </form>
  </div>

  <?php if (!$drive_id): ?>
  <div class="no-drive">👆 Select a placement drive above to view mock test analytics.</div>

  <?php elseif (!$drive_info): ?>
  <div class="no-drive" style="background:#ffebee;border-color:#ef9a9a;color:#5d1010;">❌ Drive not found.</div>

  <?php else: ?>

  <!-- ── SUMMARY CARDS ──────────────────────────────────────── -->
  <div class="cards">
    <div class="card card-blue">
      <div class="card-icon">📝</div>
      <div class="card-value"><?= $summary['total'] ?></div>
      <div class="card-label">Total Attempts</div>
    </div>
    <div class="card card-amber">
      <div class="card-icon">📊</div>
      <div class="card-value"><?= $summary['avg_pct'] ?>%</div>
      <div class="card-label">Average Score</div>
    </div>
    <div class="card card-green">
      <div class="card-icon">🏆</div>
      <div class="card-value"><?= $summary['highest'] ?>/10</div>
      <div class="card-label">Highest Score</div>
    </div>
    <div class="card card-red">
      <div class="card-icon">✅</div>
      <div class="card-value"><?= $summary['pass_rate'] ?>%</div>
      <div class="card-label">Pass Rate (≥60%)</div>
    </div>
  </div>

  <?php if ($summary['total'] === 0): ?>
  <div class="empty-state">📭 No students have attempted the mock test for this drive yet.</div>

  <?php else: ?>

  <!-- ── LEADERBOARD TABLE ──────────────────────────────────── -->
  <div class="section">
    <div class="section-title">🏅 Student Leaderboard</div>
    <div style="overflow-x:auto">
    <table class="lb-table">
      <thead>
        <tr>
          <th class="rank-cell">Rank</th>
          <th>Student Name</th>
          <th>Department</th>
          <th class="score-cell">Score</th>
          <th class="pct-cell">Percentage</th>
          <th class="time-cell">Time Taken</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($attempts as $rank_idx => $a):
          $rank    = $rank_idx + 1;
          $pct     = $a['total'] > 0 ? round(($a['score'] / $a['total']) * 100, 1) : 0;
          $passed  = $pct >= 60;
          $tr_cls  = '';
          $rank_lbl = $rank;
          if      ($rank === 1) { $tr_cls = 'tr-gold';   $rank_lbl = '🥇'; }
          elseif  ($rank === 2) { $tr_cls = 'tr-silver'; $rank_lbl = '🥈'; }
          elseif  ($rank === 3) { $tr_cls = 'tr-bronze'; $rank_lbl = '🥉'; }
      ?>
      <tr class="<?= $tr_cls ?>">
        <td class="rank-cell"><?= $rank_lbl ?></td>
        <td style="font-weight:<?= $rank <= 3 ? '700' : '400' ?>"><?= htmlspecialchars($a['student_name']) ?></td>
        <td><?= htmlspecialchars($a['department']) ?></td>
        <td class="score-cell" style="font-weight:700;color:#1b5e20"><?= $a['score'] ?>/<?= $a['total'] ?></td>
        <td class="pct-cell"><?= $pct ?>%</td>
        <td class="time-cell"><?= fmt_secs((int)$a['time_taken']) ?></td>
        <td style="font-size:12px;color:#666"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
        <td><span class="badge <?= $passed ? 'badge-pass' : 'badge-fail' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- ── QUESTION ANALYTICS ────────────────────────────────── -->
  <?php if (!empty($q_stats)): ?>
  <div class="section">
    <div class="section-title">📋 Question-wise Analytics</div>
    <div style="overflow-x:auto">
    <table class="q-table">
      <thead>
        <tr>
          <th style="width:40px">Q#</th>
          <th>Question Preview</th>
          <th>Category</th>
          <th style="text-align:center">✅ Correct</th>
          <th style="text-align:center">❌ Wrong</th>
          <th style="text-align:center">Accuracy</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($q_stats as $qs): ?>
      <tr>
        <td style="font-weight:700;text-align:center;color:#185FA5">Q<?= $qs['index'] ?></td>
        <td class="q-text-col" title="<?= htmlspecialchars($qs['question']) ?>"><?= htmlspecialchars($qs['question']) ?></td>
        <td><span class="q-cat"><?= htmlspecialchars($qs['category']) ?></span></td>
        <td style="text-align:center;font-weight:600;color:#3B6D11"><?= $qs['correct'] ?></td>
        <td style="text-align:center;font-weight:600;color:#A32D2D"><?= $qs['wrong'] ?></td>
        <td style="text-align:center">
          <span class="acc-pill" style="background:<?= acc_bg($qs['accuracy']) ?>;color:<?= acc_color($qs['accuracy']) ?>">
            <?= $qs['accuracy'] ?>%
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p style="font-size:11px;color:#888;margin-top:10px">
      <span style="background:#f0f7e8;color:#3B6D11;padding:2px 7px;border-radius:8px;font-size:11px;font-weight:600">Green ≥70%</span>&nbsp;
      <span style="background:#fff8e8;color:#BA7517;padding:2px 7px;border-radius:8px;font-size:11px;font-weight:600">Amber 40–69%</span>&nbsp;
      <span style="background:#fff0f0;color:#A32D2D;padding:2px 7px;border-radius:8px;font-size:11px;font-weight:600">Red &lt;40%</span>
    </p>
  </div>
  <?php endif; ?>

  <!-- ── SCORE DISTRIBUTION (Pure CSS bar chart) ───────────── -->
  <div class="section">
    <div class="section-title">📈 Score Distribution</div>
    <div class="dist-chart">
    <?php
    $buckets = ['0–20%'=>'0-20','21–40%'=>'21-40','41–60%'=>'41-60','61–80%'=>'61-80','81–100%'=>'81-100'];
    foreach ($buckets as $label => $key):
        $cnt    = $distribution[$key];
        $width  = $max_dist > 0 ? max(0, round(($cnt / $max_dist) * 100)) : 0;
        $color  = '#185FA5';
        if ($key === '0-20')   $color = '#A32D2D';
        if ($key === '21-40')  $color = '#c0572a';
        if ($key === '41-60')  $color = '#BA7517';
        if ($key === '61-80')  $color = '#5a8a1f';
        if ($key === '81-100') $color = '#3B6D11';
    ?>
    <div class="dist-row">
      <div class="dist-label"><?= $label ?></div>
      <div class="dist-bar-bg">
        <div class="dist-bar" style="width:<?= $width ?>%;background:<?= $color ?>">
          <?= $width >= 15 ? $cnt . ' student' . ($cnt != 1 ? 's' : '') : '' ?>
        </div>
      </div>
      <div class="dist-count"><?= $cnt ?></div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <?php endif; // $summary['total'] > 0 ?>
  <?php endif; // $drive_id && $drive_info ?>

<?php require '_nav_end.php'; ?>
