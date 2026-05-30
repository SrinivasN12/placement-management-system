<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);

if (isset($_GET['delete'])) {
    $rid = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM alumni_recommendation WHERE recommendation_id=$rid AND alumni_id=$alumni_id");
    header("Location: my_recommendations.php?deleted=1"); exit();
}

$recs = mysqli_query($conn,
    "SELECT * FROM alumni_recommendation WHERE alumni_id=$alumni_id ORDER BY posted_at DESC");

$pageTitle  = 'My Recommendations';
$activePage = 'my_recommendations';
require '_layout.php';
?>

<style>
.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}
.rec-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.rec-card {
  background: var(--surface);
  border-radius: var(--card-radius);
  padding: 24px;
  box-shadow: var(--card-shadow);
  border-left: 4px solid var(--primary);
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: transform var(--transition), box-shadow var(--transition);
}
.rec-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(74,20,140,.12);
}
.rec-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 8px;
}
.rec-title-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.rec-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
}
.rec-company {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.rec-date {
  font-size: 12px;
  color: var(--muted);
  font-weight: 500;
}
.rec-desc {
  font-size: 13.5px;
  color: #444;
  line-height: 1.6;
  background: #f8f9fa;
  padding: 12px 16px;
  border-radius: 8px;
  white-space: pre-wrap;
}
.rec-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
  flex-wrap: wrap;
  gap: 12px;
}
.rec-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  transition: color var(--transition);
}
.btn-view { color: var(--primary); }
.btn-view:hover { color: var(--primary-light); }
.btn-edit { color: #1565c0; }
.btn-edit:hover { color: #1e88e5; }
.btn-delete { color: var(--danger); }
.btn-delete:hover { color: #e53935; }

.empty-state {
  text-align: center;
  padding: 64px 32px;
  background: var(--surface);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  color: var(--muted);
}
.empty-state .material-icons-round {
  font-size: 56px;
  color: var(--accent);
  margin-bottom: 16px;
}
.empty-state h3 {
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
}
.empty-state p {
  font-size: 13.5px;
  margin-bottom: 20px;
}
</style>

<div class="header-row">
  <div class="page-header" style="margin-bottom:0">
    <h1>📋 My Recommendations</h1>
    <p>View and manage job opportunities you've posted for current MBA students.</p>
  </div>
  <a class="btn" href="recommend_job.php">
    <span class="material-icons-round">add</span>
    Recommend a Job
  </a>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="success"><span class="material-icons-round">check_circle</span>Recommendation deleted successfully.</div>
<?php endif; ?>

<div class="rec-list">
<?php
$found = false;
while ($r = mysqli_fetch_assoc($recs)):
  $found = true;
?>
  <div class="rec-card">
    <div class="rec-header">
      <div class="rec-title-group">
        <div class="rec-title"><?= htmlspecialchars($r['job_title']) ?></div>
        <div class="rec-company">
          <span class="material-icons-round" style="font-size:16px;color:var(--muted)">corporate_fare</span>
          <?= htmlspecialchars($r['company_name']) ?>
        </div>
      </div>
      <div class="rec-date">Posted on <?= date('d M Y, h:i A', strtotime($r['posted_at'])) ?></div>
    </div>

    <?php if (!empty($r['description'])): ?>
      <div class="rec-desc"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
    <?php endif; ?>

    <div class="rec-footer">
      <div class="rec-actions">
        <?php if (!empty($r['job_link'])): ?>
          <a class="action-btn btn-view" href="<?= htmlspecialchars($r['job_link']) ?>" target="_blank">
            <span class="material-icons-round" style="font-size:16px">link</span>
            Application Link
          </a>
        <?php else: ?>
          <span style="font-size:12px;color:var(--muted);display:inline-flex;align-items:center;gap:4px">
            <span class="material-icons-round" style="font-size:14px">link_off</span>
            No Link Shared
          </span>
        <?php endif; ?>
      </div>

      <div class="rec-actions">
        <a class="action-btn btn-edit" href="edit_recommendation.php?id=<?= $r['recommendation_id'] ?>">
          <span class="material-icons-round" style="font-size:16px">edit</span>
          Edit
        </a>
        <a class="action-btn btn-delete" href="my_recommendations.php?delete=<?= $r['recommendation_id'] ?>"
           onclick="return confirm('Are you sure you want to delete this job recommendation? Students will no longer be able to view it.')">
          <span class="material-icons-round" style="font-size:16px">delete</span>
          Delete
        </a>
      </div>
    </div>
  </div>
<?php endwhile; ?>

<?php if (!$found): ?>
  <div class="empty-state">
    <span class="material-icons-round">work_outline</span>
    <h3>No Recommendations Shared Yet</h3>
    <p>Help current MBA students jumpstart their careers by posting job recommendations from your company or network.</p>
    <a class="btn" href="recommend_job.php">
      <span class="material-icons-round">add</span>
      Share Your First Recommendation
    </a>
  </div>
<?php endif; ?>
</div>

<?php require '_layout_end.php'; ?>
