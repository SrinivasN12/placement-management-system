<?php
/**
 * Shared Bootstrap 5 navbar for all Officer pages.
 * Usage: require_once '_nav.php';
 * Set $active_page = 'dashboard' | 'students' | 'drives' | 'applications' | 'approvals' | 'notify' | 'preplacement' | 'reports' | 'mock' | 'profile'
 * before including.
 */
$active_page = $active_page ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?? 'Officer Portal — PMS' ?></title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bs-font-sans-serif: 'Inter', system-ui, sans-serif;
      --pms-green:       #1b5e20;
      --pms-green-mid:   #2e7d32;
      --pms-green-light: #388e3c;
      --pms-green-pale:  #e8f5e9;
      --pms-green-border:#a5d6a7;
    }

    body { background: #f1f3f5; font-family: var(--bs-font-sans-serif); }

    /* ── Navbar ─────────────────────────────────── */
    .pms-navbar {
      background: linear-gradient(135deg, var(--pms-green) 0%, var(--pms-green-mid) 100%);
      box-shadow: 0 2px 12px rgba(0,0,0,.18);
      padding: .7rem 1.5rem;
    }
    .pms-navbar .navbar-brand {
      font-size: 1rem;
      font-weight: 800;
      color: #fff !important;
      letter-spacing: .3px;
    }
    .pms-navbar .navbar-brand span { opacity: .7; font-weight: 500; }
    .pms-navbar .nav-link {
      color: rgba(255,255,255,.82) !important;
      font-size: .82rem;
      font-weight: 600;
      padding: .4rem .85rem !important;
      border-radius: 6px;
      transition: background .15s, color .15s;
    }
    .pms-navbar .nav-link:hover,
    .pms-navbar .nav-link.active {
      background: rgba(255,255,255,.18);
      color: #fff !important;
    }
    .pms-navbar .navbar-toggler {
      border-color: rgba(255,255,255,.3);
    }
    .pms-navbar .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255%2c255%2c255%2c.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    /* ── Global page wrapper ───────────────────── */
    .page-content { padding: 2rem 1.5rem; max-width: 1400px; margin: 0 auto; }

    /* ── Stat cards ────────────────────────────── */
    .stat-card {
      border: 0;
      border-radius: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,.07);
      transition: transform .18s, box-shadow .18s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.11); }
    .stat-icon {
      width: 46px; height: 46px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem;
    }
    .stat-num { font-size: 2rem; font-weight: 800; line-height: 1; }
    .stat-label { font-size: .75rem; color: #6c757d; font-weight: 500; margin-top: .25rem; }

    /* ── Module buttons ────────────────────────── */
    .module-btn {
      display: flex; align-items: center; gap: .6rem;
      background: #fff;
      border: 1.5px solid #e9ecef;
      border-radius: 12px;
      padding: .85rem 1.1rem;
      text-decoration: none;
      font-size: .82rem;
      font-weight: 600;
      color: var(--pms-green);
      transition: all .18s;
      box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }
    .module-btn:hover {
      background: var(--pms-green);
      color: #fff;
      border-color: var(--pms-green);
      transform: translateY(-2px);
      box-shadow: 0 4px 14px rgba(27,94,32,.2);
    }
    .module-btn .mi { font-size: 1.1rem; }

    /* ── Tables ────────────────────────────────── */
    .table thead th {
      background: var(--pms-green);
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      border: 0;
      padding: .75rem 1rem;
      white-space: nowrap;
    }
    .table td { font-size: .82rem; vertical-align: middle; padding: .65rem 1rem; }
    .table tbody tr:hover td { background: #f8f9fa; }
    .table-card {
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,.07);
    }
    .table-card .table { margin-bottom: 0; }

    /* ── Badges ────────────────────────────────── */
    .badge-open    { background: #e8f5e9; color: #2e7d32; }
    .badge-soon    { background: #fff3e0; color: #e65100; }
    .badge-closed  { background: #f5f5f5; color: #888; }
    .badge-applied { background: #e3f2fd; color: #1565c0; }
    .badge-selected{ background: #e8f5e9; color: #2e7d32; }
    .badge-rejected{ background: #ffebee; color: #c62828; }
    .badge-ready   { background: #e8f5e9; color: #2e7d32; }
    .badge-notready{ background: #ffebee; color: #c62828; }
    .badge-pending { background: #fff3e0; color: #e65100; }
    span[class^="badge-"] {
      padding: .3em .75em;
      border-radius: 20px;
      font-size: .7rem;
      font-weight: 700;
      display: inline-block;
    }

    /* ── Cards ─────────────────────────────────── */
    .pms-card {
      border: 0;
      border-radius: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,.07);
    }
    .pms-card .card-header {
      background: linear-gradient(135deg, var(--pms-green), var(--pms-green-mid));
      color: #fff;
      border-radius: 14px 14px 0 0 !important;
      font-weight: 700;
      font-size: .9rem;
    }

    /* ── Form inputs ───────────────────────────── */
    .form-control:focus, .form-select:focus {
      border-color: var(--pms-green);
      box-shadow: 0 0 0 .2rem rgba(27,94,32,.18);
    }

    /* ── Buttons ───────────────────────────────── */
    .btn-pms {
      background: var(--pms-green);
      color: #fff;
      border: 0;
      font-weight: 600;
      border-radius: 8px;
    }
    .btn-pms:hover { background: var(--pms-green-mid); color: #fff; }
    .btn-outline-pms {
      border: 1.5px solid var(--pms-green);
      color: var(--pms-green);
      font-weight: 600;
      border-radius: 8px;
    }
    .btn-outline-pms:hover { background: var(--pms-green); color: #fff; }

    /* ── Breadcrumb ─────────────────────────────── */
    .breadcrumb { font-size: .8rem; }
    .breadcrumb-item a { color: var(--pms-green); text-decoration: none; font-weight: 600; }
    .breadcrumb-item.active { color: #6c757d; }

    /* ── Page title area ───────────────────────── */
    .page-header { margin-bottom: 1.5rem; }
    .page-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--pms-green); margin: 0; }
    .page-header .sub { font-size: .82rem; color: #6c757d; margin-top: .2rem; }

    /* ── UUCMS id style ────────────────────────── */
    .uucms-id {
      font-family: 'Courier New', monospace;
      font-weight: 700;
      color: #1a237e;
      font-size: .72rem;
      letter-spacing: 1.2px;
      text-transform: uppercase;
    }

    @media (max-width: 576px) {
      .page-content { padding: 1rem .85rem; }
    }
  </style>
  <?= $extra_head ?? '' ?>
</head>
<body>

<nav class="navbar navbar-expand-lg pms-navbar sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <i class="bi bi-building me-2"></i>PMS <span>— Officer Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#officerNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="officerNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='dashboard'?'active':'' ?>" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='students'?'active':'' ?>" href="students.php">
            <i class="bi bi-people me-1"></i>Students
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='drives'?'active':'' ?>" href="manage_drives.php">
            <i class="bi bi-briefcase me-1"></i>Drives
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='applications'?'active':'' ?>" href="applications.php">
            <i class="bi bi-file-earmark-text me-1"></i>Applications
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='reports'?'active':'' ?>" href="reports.php">
            <i class="bi bi-bar-chart-line me-1"></i>Reports
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page==='profile'?'active':'' ?>" href="profile.php">
            <i class="bi bi-person-circle me-1"></i>Profile
          </a>
        </li>
        <li class="nav-item ms-lg-2">
          <a class="nav-link" href="../logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
