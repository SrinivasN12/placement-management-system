<?php
/**
 * officer/_nav.php — Shared horizontal layout for all Officer pages.
 * Set before including:
 *   $page_title  (string)
 *   $active_page (string): dashboard|students|drives|applications|approvals|notify|preplacement|reports|mock|profile
 *   $extra_head  (string, optional): extra <head> HTML
 */
$active_page = $active_page ?? '';
$page_title  = $page_title  ?? 'Officer Portal — PMS';

// Pending approvals badge count
$_pendingCount = 0;
if (!empty($conn)) {
    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM user WHERE role_id=1 AND status='Pending'");
    if ($r) $_pendingCount = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}

$_officerNav = [
    'dashboard'   => ['icon'=>'bi-speedometer2',        'label'=>'Dashboard',        'href'=>'dashboard.php'],
    'students'    => ['icon'=>'bi-people',               'label'=>'Students',         'href'=>'students.php'],
    'approvals'   => ['icon'=>'bi-person-check',         'label'=>'Approvals',        'href'=>'approve_students.php'],
    'drives'      => ['icon'=>'bi-briefcase',            'label'=>'Drives',           'href'=>'manage_drives.php'],
    'applications'=> ['icon'=>'bi-file-earmark-text',   'label'=>'Applications',     'href'=>'applications.php'],
    'preplacement'=> ['icon'=>'bi-clipboard2-check',     'label'=>'Pre-Placement',    'href'=>'preplacement.php'],
    'notify'      => ['icon'=>'bi-bell',                 'label'=>'Notifications',    'href'=>'notify.php'],
    'mock'        => ['icon'=>'bi-trophy',               'label'=>'Mock Analytics',   'href'=>'mock_test_analytics.php'],
    'reports'     => ['icon'=>'bi-bar-chart-line',       'label'=>'Reports',          'href'=>'reports.php'],
    'profile'     => ['icon'=>'bi-person-gear',          'label'=>'My Profile',       'href'=>'profile.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ── Tokens ──────────────────────────────────────────────── */
    :root {
      --bs-font-sans-serif: 'Inter', system-ui, sans-serif;
      --pms-green:          #1b5e20;
      --pms-green-mid:      #2e7d32;
      --pms-green-light:    #388e3c;
      --pms-green-pale:     #e8f5e9;
      --pms-green-border:   #a5d6a7;
      --card-shadow:        0 4px 20px rgba(0,0,0,.08);
      --card-radius:        14px;
      --transition:         .2s cubic-bezier(.4,0,.2,1);
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { font-size: 15px; }
    body {
      font-family: var(--bs-font-sans-serif);
      background: #f1f3f5;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      margin: 0;
    }

    /* ── Horizontal Navigation ───────────────────────────────── */
    .header-navbar {
      background: linear-gradient(135deg, var(--pms-green) 0%, #2e7d32 100%);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0,0,0,.15);
      display: flex;
      flex-direction: column;
    }

    .nav-tier-upper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 28px;
      border-bottom: 1px solid rgba(255,255,255,.1);
    }

    .brand-group {
      display: flex;
      flex-direction: column;
    }
    .brand-logo {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1.5px;
      color: rgba(255,255,255,.55);
      text-transform: uppercase;
      margin-bottom: 2px;
    }
    .brand-title {
      font-size: 16px;
      font-weight: 800;
      color: #fff;
      line-height: 1.1;
    }

    .user-actions {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff;
    }
    .header-avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--pms-green-light), var(--pms-green-mid));
      display: flex; align-items: center; justify-content: center;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
    }
    .header-name {
      font-size: 13px;
      font-weight: 600;
      display: flex;
      flex-direction: column;
    }
    .header-name small {
      font-size: 10.5px;
      font-weight: 400;
      color: rgba(255,255,255,.6);
    }

    .logout-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: rgba(255,255,255,.7);
      font-size: 12.5px;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 8px;
      transition: all var(--transition);
      background: rgba(255,255,255,.05);
      text-decoration: none;
    }
    .logout-link:hover {
      background: rgba(239,83,80,.2);
      color: #ffcdd2;
    }

    .nav-tier-lower {
      display: flex;
      overflow-x: auto;
      white-space: nowrap;
      padding: 0 24px;
      -webkit-overflow-scrolling: touch;
    }
    /* Hide scrollbar for Chrome, Safari and Opera */
    .nav-tier-lower::-webkit-scrollbar {
      display: none;
    }
    /* Hide scrollbar for IE, Edge and Firefox */
    .nav-tier-lower {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }

    .nav-menu {
      display: flex;
      gap: 4px;
      padding: 6px 0;
    }

    .nav-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      border-radius: 8px;
      color: rgba(255,255,255,.7);
      font-size: 13px;
      font-weight: 500;
      transition: background var(--transition), color var(--transition);
      text-decoration: none;
    }
    .nav-item:hover {
      background: rgba(255,255,255,.08);
      color: #fff;
    }
    .nav-item.active {
      background: rgba(255,255,255,.15);
      color: #fff;
      font-weight: 600;
    }
    .nav-item i {
      font-size: 15px;
    }

    .nav-badge {
      background: #ef5350;
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 99px;
    }

    /* ── Page content ────────────────────────────────────────── */
    .page-content { padding:28px 32px; flex:1; max-width:1400px; width:100%; margin:0 auto; }

    /* ── Stat cards ──────────────────────────────────────────── */
    .stat-card { border:0; border-radius:var(--card-radius); box-shadow:var(--card-shadow); transition:transform var(--transition),box-shadow var(--transition); }
    .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(0,0,0,.12); }
    .stat-icon { width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem; }
    .stat-num { font-size:2rem; font-weight:800; line-height:1; }
    .stat-label { font-size:.75rem; color:#6c757d; font-weight:500; margin-top:.25rem; }

    /* ── Module buttons ──────────────────────────────────────── */
    .module-btn {
      display:flex; align-items:center; gap:.6rem;
      background:#fff; border:1.5px solid #e9ecef; border-radius:12px;
      padding:.85rem 1.1rem; text-decoration:none; font-size:.82rem;
      font-weight:600; color:var(--pms-green);
      transition:all var(--transition); box-shadow:0 1px 4px rgba(0,0,0,.05);
    }
    .module-btn:hover {
      background:var(--pms-green); color:#fff; border-color:var(--pms-green);
      transform:translateY(-2px); box-shadow:0 4px 14px rgba(27,94,32,.2);
    }
    .module-btn .mi { font-size:1.1rem; }

    /* ── Tables ──────────────────────────────────────────────── */
    .table thead th { background:var(--pms-green);color:#fff;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border:0;padding:.75rem 1rem;white-space:nowrap; }
    .table td { font-size:.82rem; vertical-align:middle; padding:.65rem 1rem; }
    .table tbody tr:hover td { background:#f8f9fa; }
    .table-card { border-radius:var(--card-radius); overflow:hidden; box-shadow:var(--card-shadow); }
    .table-card .table { margin-bottom:0; }

    /* ── Badges ──────────────────────────────────────────────── */
    .badge-open    {background:#e3f2fd;color:#1565c0}
    .badge-soon    {background:#fff3e0;color:#e65100}
    .badge-closed  {background:#f5f5f5;color:#888}
    .badge-applied {background:#e3f2fd;color:#1565c0}
    .badge-selected{background:#e8f5e9;color:#2e7d32}
    .badge-rejected{background:#ffebee;color:#c62828}
    .badge-ready   {background:#e8f5e9;color:#2e7d32}
    .badge-notready{background:#ffebee;color:#c62828}
    .badge-pending {background:#fff3e0;color:#e65100}
    span[class^="badge-"] { padding:.3em .75em;border-radius:20px;font-size:.7rem;font-weight:700;display:inline-block; }

    /* ── Cards ───────────────────────────────────────────────── */
    .pms-card { border:0; border-radius:var(--card-radius); box-shadow:var(--card-shadow); }
    .pms-card .card-header { background:linear-gradient(135deg,var(--pms-green),var(--pms-green-mid));color:#fff;border-radius:var(--card-radius) var(--card-radius) 0 0 !important;font-weight:700;font-size:.9rem; }

    /* ── Form inputs ─────────────────────────────────────────── */
    .form-control:focus,.form-select:focus { border-color:var(--pms-green);box-shadow:0 0 0 .2rem rgba(27,94,32,.18); }

    /* ── Buttons ─────────────────────────────────────────────── */
    .btn-pms { background:var(--pms-green);color:#fff;border:0;font-weight:600;border-radius:8px; }
    .btn-pms:hover { background:var(--pms-green-mid);color:#fff; }
    .btn-outline-pms { border:1.5px solid var(--pms-green);color:var(--pms-green);font-weight:600;border-radius:8px; }
    .btn-outline-pms:hover { background:var(--pms-green);color:#fff; }

    /* ── Page header ─────────────────────────────────────────── */
    .page-header { margin-bottom:1.5rem; }
    .page-header h1 { font-size:1.4rem;font-weight:800;color:var(--pms-green);margin:0; }
    .page-header .sub { font-size:.82rem;color:#6c757d;margin-top:.2rem; }

    /* ── Breadcrumb ──────────────────────────────────────────── */
    .breadcrumb { font-size:.8rem; }
    .breadcrumb-item a { color:var(--pms-green);text-decoration:none;font-weight:600; }
    .breadcrumb-item.active { color:#6c757d; }

    /* ── UUCMS id ────────────────────────────────────────────── */
    .uucms-id { font-family:'Courier New',monospace;font-weight:700;color:#1a237e;font-size:.72rem;letter-spacing:1.2px;text-transform:uppercase; }

    @media(max-width:768px) {
      .page-content { padding:20px 16px; }
      .nav-tier-upper { padding: 12px 16px; }
      .nav-tier-lower { padding: 0 12px; }
    }
    @media(max-width:576px) { .header-name { display:none; } }

    <?= $extra_head ?? '' ?>
  </style>
  <?= $extra_scripts ?? '' ?>
</head>
<body>

<!-- ── Horizontal Navbar ────────────────────────────────────── -->
<header class="header-navbar">
  <div class="nav-tier-upper">
    <div class="brand-group">
      <div class="brand-logo">Officer Portal</div>
      <div class="brand-title">PMS Officer</div>
    </div>
    
    <div class="user-actions">
      <div class="user-profile">
        <div class="header-avatar"><?= strtoupper(substr($name ?? 'O', 0, 1)) ?></div>
        <div class="header-name">
          <?= htmlspecialchars($name ?? '') ?>
          <small>Placement Officer</small>
        </div>
      </div>
      <a href="../logout.php" class="logout-link">
        <i class="bi bi-box-arrow-right"></i>
        Logout
      </a>
    </div>
  </div>
  
  <div class="nav-tier-lower">
    <nav class="nav-menu">
      <?php foreach ($_officerNav as $key => $item): ?>
      <a class="nav-item <?= $active_page === $key ? 'active' : '' ?>" href="<?= $item['href'] ?>">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($key === 'approvals' && $_pendingCount > 0): ?>
          <span class="nav-badge"><?= $_pendingCount ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<!-- ── Main Content Area ────────────────────────────────────── -->
<div class="page-content">
