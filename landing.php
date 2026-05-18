<?php
$LOGO = 'assets/logo.jpg';
if(session_status()===PHP_SESSION_NONE)session_start();
if(isset($_SESSION['user_id'])){
  $rd=['1'=>'student/dashboard.php','2'=>'officer/dashboard.php','3'=>'alumni/dashboard.php'];
  header('Location:'.($rd[$_SESSION['role_id']]??'index.php'));exit;
}
if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="JSS DVH-IMSR MBA Placement Management System — connecting students, alumni, and the placement cell.">
<title>JSS DVH-IMSR | Placement Management System</title>
<link rel="stylesheet" href="assets/pms-theme.css">
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────── -->
<nav class="pms-nav" id="navbar">
  <a href="landing.php" class="nav-brand">
    <img src="<?= $LOGO ?>" alt="JSS DVH-IMSR Logo" class="nav-logo-img">
    <div class="nav-brand-text">
      <span class="nav-brand-name">JSS DVH-IMSR College</span>
      <span class="nav-brand-sub">Placement Management System</span>
    </div>
  </a>
  <div class="nav-actions" id="navActions">
    <a href="#" class="btn-outline-gold" onclick="openModal('signup');return false">Sign Up</a>
    <a href="#" class="btn-gold" onclick="openModal('login');return false">Login</a>
  </div>
  <button class="hamburger" onclick="document.getElementById('navActions').classList.toggle('open')" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- ── HERO ──────────────────────────────────── -->
<section class="pms-hero" id="home">
  <div class="hero-orb hero-orb-1"></div>
  <div class="hero-orb hero-orb-2"></div>
  <div class="hero-diagonal"></div>
  <div class="hero-inner">
    <div class="hero-badge fade-in-up">🎓 JSS DVH-IMSR College &nbsp;·&nbsp; Official Placement Portal</div>
    <h1 class="hero-title fade-in-up-d1">
      Empowering <span class="gold-text">MBA Careers</span><br>at JSS DVH-IMSR
    </h1>
    <p class="hero-sub fade-in-up-d2">
      A seamless platform connecting students, alumni, and the placement cell —
      streamlining every step from profile to placement.
    </p>
    <div class="hero-ctas fade-in-up-d3">
      <a href="#" class="btn-gold btn-gold-lg" onclick="openModal('signup');return false">Get Started →</a>
      <a href="#signup" class="btn-outline-gold btn-gold-lg">Join Now ↓</a>
    </div>
  </div>
</section>

<!-- ── PORTAL CARDS ───────────────────────────── -->
<section class="pms-section-dark" id="signup">
  <div class="section-label">Get Started</div>
  <h2 class="section-title">Access the Portal<br><span style="color:var(--accent)">Choose Your Role</span></h2>
  <p class="section-sub">Select your role to sign up or log in and access a portal tailored just for you.</p>
  <div class="portal-grid">
    <a href="student/register.php" class="portal-card">
      <div class="card-icon-wrap">🎓</div>
      <h3>Student</h3>
      <p>Register to access placement drives, resume tools, and career resources.</p>
      <span class="card-cta">Sign Up →</span>
    </a>
    <a href="alumni/register.php" class="portal-card">
      <div class="card-icon-wrap">🌐</div>
      <h3>Alumni</h3>
      <p>Join the network, recommend jobs, and guide current MBA students.</p>
      <span class="card-cta">Sign Up →</span>
    </a>
    <div class="portal-card" onclick="openModal('login')" role="button" tabindex="0">
      <div class="card-icon-wrap">🏢</div>
      <h3>Placement Officer</h3>
      <p>Manage drives, approve students, and access placement analytics.</p>
      <span class="card-cta">Login →</span>
    </div>
  </div>
</section>

<!-- ── FOOTER ─────────────────────────────────── -->
<footer class="pms-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <img src="<?= $LOGO ?>" alt="JSS DVH-IMSR Logo" class="footer-brand-logo">
      <span class="footer-brand-name">JSS DVH-IMSR College</span>
      <p>Placement Management System — empowering MBA careers through technology, data, and meaningful connections.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="#home">Home</a></li>
        <li><a href="#signup">Sign Up</a></li>
        <li><a href="#" onclick="openModal('login');return false">Login</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <ul>
        <li><a href="#">📍 Vidyagiri, Dharwad</a></li>
        <li><a href="#">📧 placement@jss.edu.in</a></li>
        <li><a href="#">📞 +91 836 2447 777</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> JSS DVH-IMSR College. All rights reserved.</p>
    <span class="footer-copy-gold">Placement Management System</span>
  </div>
</footer>

<!-- ── LOGIN MODAL ────────────────────────────── -->
<div class="modal-backdrop-pms" id="loginModal">
  <div class="modal-pms">
    <div class="modal-pms-accent"></div>
    <button class="modal-close-btn" onclick="closeModal('loginModal')">✕</button>
    <div class="modal-pms-body">
      <div class="modal-logo-wrap">
        <img src="<?= $LOGO ?>" alt="JSS Logo" class="modal-logo-img">
      </div>
      <h3 class="modal-title">Welcome Back</h3>
      <p class="modal-sub">Sign in to your PMS account</p>
      <div class="error-box-modal" id="loginError"></div>
      <form method="POST" action="index.php" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="form-group-sm">
          <label class="modal-label">Email Address</label>
          <input type="email" name="email" class="modal-input" placeholder="your@email.com" required>
        </div>
        <div class="form-group-sm" style="margin-bottom:20px">
          <label class="modal-label">Password</label>
          <input type="password" name="password" class="modal-input" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-gold" style="width:100%;justify-content:center;padding:14px;font-size:15px">Login →</button>
      </form>
      <p class="modal-switch">Don't have an account? <a onclick="switchModal('loginModal','signupModal')">Sign Up</a></p>
    </div>
  </div>
</div>

<!-- ── SIGNUP MODAL ───────────────────────────── -->
<div class="modal-backdrop-pms" id="signupModal">
  <div class="modal-pms">
    <div class="modal-pms-accent"></div>
    <button class="modal-close-btn" onclick="closeModal('signupModal')">✕</button>
    <div class="modal-pms-body">
      <div class="modal-logo-wrap">
        <img src="<?= $LOGO ?>" alt="JSS Logo" class="modal-logo-img">
      </div>
      <h3 class="modal-title">Create Account</h3>
      <p class="modal-sub">Choose your role to get started</p>
      <a href="student/register.php" class="modal-role-link">🎓 &nbsp;Register as Student</a>
      <a href="alumni/register.php" class="modal-role-link">🌐 &nbsp;Register as Alumni</a>
      <p class="modal-switch">Already have an account? <a onclick="switchModal('signupModal','loginModal')">Login</a></p>
    </div>
  </div>
</div>

<script>
function openModal(type){
  document.getElementById(type==='login'?'loginModal':'signupModal').classList.add('show');
  document.body.style.overflow='hidden';
}
function closeModal(id){
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow='';
}
function switchModal(from,to){closeModal(from);setTimeout(()=>openModal(to==='loginModal'?'login':'signup'),80);}
document.querySelectorAll('.modal-backdrop-pms').forEach(m=>{
  m.addEventListener('click',e=>{if(e.target===m){m.classList.remove('show');document.body.style.overflow='';}});
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){document.querySelectorAll('.modal-backdrop-pms.show').forEach(m=>{m.classList.remove('show');});document.body.style.overflow='';}
});
// Navbar scroll shadow
window.addEventListener('scroll',()=>{
  document.getElementById('navbar').classList.toggle('scrolled',scrollY>50);
});
<?php if(isset($_GET['error'])):?>
document.addEventListener('DOMContentLoaded',()=>{
  openModal('login');
  const eb=document.getElementById('loginError');
  eb.textContent='<?= addslashes(htmlspecialchars($_GET['error'])) ?>';
  eb.style.display='block';
});
<?php endif;?>
</script>
</body>
</html>
