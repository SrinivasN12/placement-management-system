<?php
/**
 * alumni/_layout.php
 * Shared horizontal layout shell for all alumni-facing pages.
 *
 * Variables the including page should set BEFORE including this file:
 *   $pageTitle  (string) — shown in <title> and header
 *   $activePage (string) — one of: dashboard|recommend|my_recommendations|qa|profile
 *
 * Variables expected to already exist (set by auth.php + db.php):
 *   $name, $conn
 */

$pageTitle  = $pageTitle  ?? 'Alumni Portal';
$activePage = $activePage ?? 'dashboard';

$nav = [
    'dashboard'          => ['icon' => 'grid_view',          'label' => 'Dashboard',          'href' => 'dashboard.php'],
    'recommend'          => ['icon' => 'add_box',            'label' => 'Recommend a Job',    'href' => 'recommend_job.php'],
    'my_recommendations' => ['icon' => 'list_alt',           'label' => 'My Recommendations', 'href' => 'my_recommendations.php'],
    'qa'                 => ['icon' => 'forum',              'label' => 'Student Q&A Board',  'href' => 'qa_board.php'],
    'profile'            => ['icon' => 'person',             'label' => 'My Profile',         'href' => 'profile.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — PMS Alumni</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
  <style>
    /* ── Reset & Tokens ──────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary:       #4a148c;
      --primary-mid:   #6a1b9a;
      --primary-light: #7b1fa2;
      --accent:        #ab47bc;
      --accent-soft:   #f3e5f5;
      --surface:       #ffffff;
      --bg:            #f0f2f5;
      --text:          #1c1e2b;
      --muted:         #7b809a;
      --border:        #e3e6f0;
      --success:       #2e7d32;
      --success-bg:    #e8f5e9;
      --warn:          #e65100;
      --warn-bg:       #fff3e0;
      --danger:        #c62828;
      --danger-bg:     #ffebee;
      --card-shadow:   0 4px 20px rgba(74,20,140,.09);
      --card-radius:   14px;
      --transition:    .2s cubic-bezier(.4,0,.2,1);
    }

    html { font-size: 15px; }
    body {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    a { text-decoration: none; color: inherit; }
    button { font-family: inherit; }

    /* ── Horizontal Navigation ───────────────────────────────── */
    .header-navbar {
      background: var(--primary);
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
      background: linear-gradient(135deg, var(--primary-light), var(--accent));
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
      font-size: 13.5px;
      font-weight: 500;
      transition: background var(--transition), color var(--transition);
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
    .nav-item .material-icons-round {
      font-size: 18px;
    }

    /* ── Page content ────────────────────────────────────────── */
    .page-content {
      padding: 28px 32px;
      flex: 1;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
    }

    .page-header {
      margin-bottom: 24px;
    }
    .page-header h1 {
      font-size: 22px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 4px;
    }
    .page-header p {
      font-size: 13px;
      color: var(--muted);
    }

    /* ── Shared card ─────────────────────────────────────────── */
    .card {
      background: var(--surface);
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      padding: 22px 26px;
      margin-bottom: 20px;
    }
    .card-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 16px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--accent-soft);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .card-title .material-icons-round { font-size: 18px; color: var(--primary-light); }

    /* ── Alert/Toast ─────────────────────────────────────────── */
    .alert, .toast, .success, .error {
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
    }
    .alert-success, .success, .toast { background: var(--success-bg); color: var(--success); border: 1px solid #a5d6a7; }
    .alert-error, .error { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #ef9a9a; }
    .alert-warn    { background: var(--warn-bg);    color: var(--warn);    border: 1px solid #ffcc80; }

    /* ── Back link ───────────────────────────────────────────── */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 600;
      color: var(--primary-light);
      margin-bottom: 18px;
      transition: gap var(--transition);
    }
    .back-link:hover { gap: 3px; }

    /* ── Btn primary ─────────────────────────────────────────── */
    .btn-primary, .btn {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 10px 22px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
      text-decoration: none;
    }
    .btn-primary:hover, .btn:hover {
      background: var(--primary-light);
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(74,20,140,.3);
      color: #fff;
    }
    .btn-secondary, .btn-sec {
      background: var(--accent-soft);
      color: var(--primary);
      border: none;
      padding: 10px 22px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background var(--transition);
      text-decoration: none;
    }
    .btn-secondary:hover, .btn-sec:hover { background: #d0d4f7; color: var(--primary); }

    /* Form controls focus */
    input, textarea, select {
      width: 100%;
      padding: 10px 13px;
      border: 1.5px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      font-family: inherit;
      transition: border var(--transition);
    }
    input:focus, textarea:focus, select:focus {
      border-color: var(--primary-light);
    }

    @media (max-width: 768px) {
      .page-content { padding: 20px 16px; }
      .nav-tier-upper { padding: 12px 16px; }
      .nav-tier-lower { padding: 0 12px; }
    }

    @media (max-width: 480px) {
      .header-name { display: none; }
      .page-header h1 { font-size: 18px; }
    }
  </style>
</head>
<body>

<!-- ── Horizontal Navbar ────────────────────────────────────── -->
<header class="header-navbar">
  <div class="nav-tier-upper">
    <div class="brand-group">
      <div class="brand-logo">Alumni Portal</div>
      <div class="brand-title">PMS Alumni</div>
    </div>
    
    <div class="user-actions">
      <div class="user-profile">
        <div class="header-avatar"><?= strtoupper(substr($name ?? 'A', 0, 1)) ?></div>
        <div class="header-name">
          <?= htmlspecialchars($name ?? '') ?>
          <small>Alumni Partner</small>
        </div>
      </div>
      <a href="../logout.php" class="logout-link">
        <span class="material-icons-round" style="font-size:16px">logout</span>
        Logout
      </a>
    </div>
  </div>
  
  <div class="nav-tier-lower">
    <nav class="nav-menu">
      <?php foreach ($nav as $key => $item): ?>
      <a class="nav-item <?= $activePage === $key ? 'active' : '' ?>" href="<?= $item['href'] ?>">
        <span class="material-icons-round"><?= $item['icon'] ?></span>
        <?= $item['label'] ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<!-- ── Main Content Area ────────────────────────────────────── -->
<div class="page-content">
