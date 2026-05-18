<?php
$LOGO = 'assets/logo.jpg';
if (session_status() === PHP_SESSION_NONE)
  session_start();
if (isset($_SESSION['user_id'])) {
  $rd = ['1' => 'student/dashboard.php', '2' => 'officer/dashboard.php', '3' => 'alumni/dashboard.php'];
  header('Location:' . ($rd[$_SESSION['role_id']] ?? 'login.php'));
  exit;
}
if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}
?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>JSS DVH-IMSR | Placement Management System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    :root {
      --navy: #0a1628;
      --navy2: #112240;
      --gold: #c9a84c;
      --gold2: #e8c56d;
      --white: #fff;
      --gray: #8892a4;
      --light: #f0f4ff
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    html {
      scroll-behavior: smooth
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--navy);
      color: var(--white);
      overflow-x: hidden
    }

    /* NAV */
    nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 48px;
      background: rgba(10, 22, 40, .92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(201, 168, 76, .15);
      transition: .3s
    }

    .nav-logo {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .logo-circle {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 16px;
      color: var(--navy);
      flex-shrink: 0
    }

    .logo-img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(201, 168, 76, .4);
      flex-shrink: 0;
      background: #fff
    }

    .nav-name {
      font-size: 15px;
      font-weight: 700;
      color: var(--white);
      line-height: 1.2
    }

    .nav-name span {
      display: block;
      font-size: 11px;
      font-weight: 400;
      color: var(--gold);
      letter-spacing: .5px
    }

    .nav-btns {
      display: flex;
      gap: 10px;
      align-items: center
    }

    .btn-outline {
      padding: 9px 22px;
      border: 1.5px solid var(--gold);
      color: var(--gold);
      background: transparent;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: .2s;
      text-decoration: none
    }

    .btn-outline:hover {
      background: var(--gold);
      color: var(--navy)
    }

    .btn-primary {
      padding: 9px 22px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      border: none;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: .2s;
      text-decoration: none
    }

    .btn-primary:hover {
      opacity: .88;
      transform: translateY(-1px)
    }

    .hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px
    }

    .hamburger span {
      display: block;
      width: 24px;
      height: 2px;
      background: var(--gold);
      border-radius: 2px;
      transition: .3s
    }

    /* HERO */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding: 120px 48px 80px;
      position: relative;
      overflow: hidden
    }

    .hero-bg {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, #0a1628 0%, #112240 50%, #0d1f3c 100%)
    }

    .hero-orb1 {
      position: absolute;
      width: 500px;
      height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(201, 168, 76, .18) 0%, transparent 70%);
      top: -100px;
      right: -100px;
      animation: pulse 6s ease-in-out infinite
    }

    .hero-orb2 {
      position: absolute;
      width: 350px;
      height: 350px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(100, 130, 255, .12) 0%, transparent 70%);
      bottom: 0;
      left: -80px;
      animation: pulse 8s ease-in-out infinite reverse
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1)
      }

      50% {
        transform: scale(1.1)
      }
    }

    .hero-content {
      position: relative;
      max-width: 650px
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(201, 168, 76, .12);
      border: 1px solid rgba(201, 168, 76, .3);
      color: var(--gold);
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .5px;
      margin-bottom: 24px
    }

    .hero h1 {
      font-size: clamp(32px, 5vw, 58px);
      font-weight: 800;
      line-height: 1.15;
      margin-bottom: 20px
    }

    .hero h1 .accent {
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text
    }

    .hero p {
      font-size: 17px;
      color: var(--gray);
      line-height: 1.75;
      margin-bottom: 36px;
      max-width: 540px
    }

    .hero-ctas {
      display: flex;
      gap: 14px;
      flex-wrap: wrap
    }

    .btn-hero-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 15px 32px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      text-decoration: none;
      transition: .25s;
      box-shadow: 0 8px 32px rgba(201, 168, 76, .3)
    }

    .btn-hero-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(201, 168, 76, .4)
    }

    .btn-hero-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 15px 32px;
      border: 1.5px solid rgba(255, 255, 255, .25);
      color: var(--white);
      border-radius: 10px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      transition: .25s
    }

    .btn-hero-outline:hover {
      border-color: var(--gold);
      color: var(--gold)
    }


    /* ROLES shared label */
    .section-label {
      text-align: center;
      color: var(--gold);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 12px
    }

    .section-title {
      text-align: center;
      font-size: clamp(26px, 4vw, 40px);
      font-weight: 800;
      margin-bottom: 14px
    }

    .section-sub {
      text-align: center;
      color: var(--gray);
      font-size: 15px;
      max-width: 520px;
      margin: 0 auto 60px;
      line-height: 1.7
    }

    /* ROLES */
    .roles {
      padding: 100px 48px;
      background: var(--navy)
    }

    .roles-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      max-width: 900px;
      margin: 0 auto
    }

    .role-card {
      text-align: center;
      padding: 40px 24px;
      background: rgba(255, 255, 255, .03);
      border: 1px solid rgba(255, 255, 255, .07);
      border-radius: 16px;
      transition: .3s;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      display: block
    }

    .role-card:hover {
      border-color: var(--gold);
      background: rgba(201, 168, 76, .06);
      transform: translateY(-4px)
    }

    .role-icon {
      font-size: 42px;
      margin-bottom: 16px
    }

    .role-card h3 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 8px
    }

    .role-card p {
      font-size: 13px;
      color: var(--gray);
      line-height: 1.6
    }

    .role-card .role-btn {
      display: inline-block;
      margin-top: 18px;
      padding: 8px 20px;
      background: var(--gold);
      color: var(--navy);
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      transition: .2s
    }

    .role-card:hover .role-btn {
      background: var(--gold2)
    }

    /* FOOTER */
    footer {
      background: #060e1c;
      padding: 60px 48px 30px;
      border-top: 1px solid rgba(201, 168, 76, .1)
    }

    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 48px;
      margin-bottom: 48px
    }

    .footer-brand .logo-circle {
      margin-bottom: 16px
    }

    .footer-brand p {
      font-size: 13px;
      color: var(--gray);
      line-height: 1.8;
      max-width: 280px;
      margin-top: 10px
    }

    footer h4 {
      font-size: 13px;
      font-weight: 700;
      color: var(--gold);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 18px
    }

    footer ul {
      list-style: none
    }

    footer ul li {
      margin-bottom: 10px
    }

    footer ul li a {
      color: var(--gray);
      text-decoration: none;
      font-size: 13px;
      transition: .2s
    }

    footer ul li a:hover {
      color: var(--gold)
    }

    .footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, .06);
      padding-top: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: gap
    }

    .footer-bottom p {
      font-size: 12px;
      color: var(--gray)
    }

    /* MODAL */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      backdrop-filter: blur(6px);
      z-index: 2000;
      align-items: center;
      justify-content: center
    }

    .modal-backdrop.show {
      display: flex
    }

    .modal {
      background: var(--navy2);
      border: 1px solid rgba(201, 168, 76, .2);
      border-radius: 20px;
      padding: 48px 40px;
      width: 92%;
      max-width: 460px;
      position: relative;
      animation: modalIn .3s ease
    }

    @keyframes modalIn {
      from {
        opacity: 0;
        transform: translateY(30px) scale(.96)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .modal-close {
      position: absolute;
      top: 16px;
      right: 18px;
      background: none;
      border: none;
      color: var(--gray);
      font-size: 22px;
      cursor: pointer;
      line-height: 1;
      padding: 4px 8px;
      transition: .2s
    }

    .modal-close:hover {
      color: var(--white)
    }

    .modal-logo {
      text-align: center;
      margin-bottom: 28px
    }

    .modal h3 {
      font-size: 22px;
      font-weight: 800;
      text-align: center;
      margin-bottom: 6px
    }

    .modal .modal-sub {
      text-align: center;
      color: var(--gray);
      font-size: 13px;
      margin-bottom: 28px
    }

    .modal .form-group {
      margin-bottom: 16px
    }

    .modal label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--gray);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: .5px
    }

    .modal input {
      width: 100%;
      padding: 12px 16px;
      background: rgba(255, 255, 255, .06);
      border: 1px solid rgba(255, 255, 255, .12);
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 14px;
      color: var(--white);
      outline: none;
      transition: .2s
    }

    .modal input:focus {
      border-color: var(--gold);
      background: rgba(201, 168, 76, .06)
    }

    .modal input::placeholder {
      color: rgba(255, 255, 255, .3)
    }

    .btn-modal {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      border: none;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 8px;
      transition: .2s
    }

    .btn-modal:hover {
      opacity: .9;
      transform: translateY(-1px)
    }

    .modal-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      color: var(--gray);
      font-size: 12px
    }

    .modal-divider::before,
    .modal-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(255, 255, 255, .08)
    }

    .modal-links {
      display: flex;
      flex-direction: column;
      gap: 10px
    }

    .modal-role-btn {
      display: block;
      padding: 12px;
      border: 1px solid rgba(255, 255, 255, .1);
      border-radius: 10px;
      text-align: center;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      color: var(--white);
      transition: .2s
    }

    .modal-role-btn:hover {
      border-color: var(--gold);
      color: var(--gold);
      background: rgba(201, 168, 76, .06)
    }

    .modal-switch {
      text-align: center;
      font-size: 13px;
      color: var(--gray);
      margin-top: 18px
    }

    .modal-switch a {
      color: var(--gold);
      font-weight: 600;
      text-decoration: none;
      cursor: pointer
    }

    .error-box {
      background: rgba(198, 40, 40, .15);
      border: 1px solid rgba(198, 40, 40, .4);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      color: #ff6b6b;
      margin-bottom: 16px;
      display: none
    }

    /* RESPONSIVE */
    @media(max-width:768px) {
      nav {
        padding: 14px 20px
      }

      .nav-name {
        display: none
      }

      .hamburger {
        display: flex
      }

      .nav-btns {
        display: none;
        position: fixed;
        top: 70px;
        left: 0;
        right: 0;
        background: rgba(10, 22, 40, .98);
        padding: 20px;
        flex-direction: column;
        border-bottom: 1px solid rgba(201, 168, 76, .2)
      }

      .nav-btns.open {
        display: flex
      }

      .hero {
        padding: 100px 20px 60px
      }

      .roles {
        padding: 70px 20px
      }

      .footer-grid {
        grid-template-columns: 1fr;
        gap: 32px
      }

      footer {
        padding: 48px 20px 24px
      }

      .modal {
        padding: 36px 24px
      }
    }
  </style>
</head>

<body>

  <!-- NAV -->
  <nav id="navbar">
    <div class="nav-logo">
      <img src="<?= $LOGO ?>" alt="JSS DVH-IMSR Logo" class="logo-img">
      <div class="nav-name">JSS DVH-IMSR College
        <span>Placement Management System</span>
      </div>
    </div>
    <div class="nav-btns" id="navBtns">
      <a href="#" class="btn-outline" onclick="openModal('signup');return false">Sign Up</a>
      <a href="login.php" class="btn-primary">Login</a>
    </div>
    <button class="hamburger" onclick="document.getElementById('navBtns').classList.toggle('open')" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </nav>

  <!-- HERO -->
  <section class="hero" id="home">
    <div class="hero-bg"></div>
    <div class="hero-orb1"></div>
    <div class="hero-orb2"></div>
    <div class="hero-content">
      <div class="hero-badge">🎓 JSS DVH-IMSR College · Est. Placement Portal</div>
      <h1>Empowering <span class="accent">Careers</span> at JSS DVH-IMSR College</h1>
      <p>A seamless platform connecting students, alumni, and the placement cell — streamlining every step from profile
        to placement.</p>
      <div class="hero-ctas">
        <a href="#" class="btn-hero-primary" onclick="openModal('signup');return false">Get Started →</a>
        <a href="#signup" class="btn-hero-outline">Join Now ↓</a>
      </div>
    </div>
  </section>



  <!-- ROLES -->
  <section class="roles" id="signup">
    <div class="section-label">Get Started</div>
    <h2 class="section-title">Join as Your <span style="color:var(--gold)">Role</span></h2>
    <p class="section-sub">Select your role to sign up and access the portal tailored for you.</p>
    <div class="roles-grid">
      <a href="student/register.php" class="role-card">
        <div class="role-icon">🎓</div>
        <h3>Student</h3>
        <p>Register to access placement drives and career tools</p>
        <span class="role-btn">Sign Up →</span>
      </a>
      <a href="alumni/register.php" class="role-card">
        <div class="role-icon">🌐</div>
        <h3>Alumni</h3>
        <p>Join the network and guide current students</p>
        <span class="role-btn">Sign Up →</span>
      </a>
      <a href="login.php" class="role-card">
        <div class="role-icon">🏢</div>
        <h3>Placement Officer</h3>
        <p>Manage drives, students, and placement analytics</p>
        <span class="role-btn">Login →</span>
      </a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="<?= $LOGO ?>" alt="JSS DVH-IMSR Logo" class="logo-img"
          style="width:56px;height:56px;border-radius:50%;background:#fff;margin-bottom:4px">
        <p>JSS DVH-IMSR College Placement Management System — empowering careers through technology and connections.</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#home">Home</a></li>
          <li><a href="#signup">Sign Up</a></li>
          <li><a href="login.php">Login</a></li>
        </ul>
      </div>
      <div>
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
      <p style="color:var(--gold);font-size:12px">Placement Management System</p>
    </div>
  </footer>

  <!-- LOGIN MODAL -->
  <div class="modal-backdrop" id="loginModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('loginModal')">✕</button>
      <div class="modal-logo"><img src="<?= $LOGO ?>" alt="JSS Logo"
          style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid rgba(201,168,76,.4);background:#fff;display:block;margin:0 auto">
      </div>
      <h3>Welcome Back</h3>
      <p class="modal-sub">Sign in to your PMS account</p>
      <div class="error-box" id="loginError"></div>
      <form method="POST" action="login.php" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="your@email.com" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-modal">Login →</button>
      </form>
      <p class="modal-switch">Don't have an account? <a onclick="switchModal('loginModal','signupModal')">Sign Up</a>
      </p>
    </div>
  </div>

  <!-- SIGNUP MODAL -->
  <div class="modal-backdrop" id="signupModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('signupModal')">✕</button>
      <div class="modal-logo"><img src="<?= $LOGO ?>" alt="JSS Logo"
          style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid rgba(201,168,76,.4);background:#fff;display:block;margin:0 auto">
      </div>
      <h3>Create Account</h3>
      <p class="modal-sub">Choose your role to get started</p>
      <div class="modal-links">
        <a href="student/register.php" class="modal-role-btn">🎓 &nbsp;Register as Student</a>
        <a href="alumni/register.php" class="modal-role-btn">🌐 &nbsp;Register as Alumni</a>
      </div>
      <p class="modal-switch">Already have an account? <a href="login.php">Login</a></p>
    </div>
  </div>

  <script>
    function openModal(type) {
      document.getElementById(type === 'login' ? 'loginModal' : 'signupModal').classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
      document.getElementById(id).classList.remove('show');
      document.body.style.overflow = '';
    }
    function switchModal(from, to) { closeModal(from); openModal(to === 'loginModal' ? 'login' : 'signup'); }
    document.querySelectorAll('.modal-backdrop').forEach(m => {
      m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('show'); document.body.style.overflow = ''; } });
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal-backdrop.show').forEach(m => { m.classList.remove('show'); }); document.body.style.overflow = ''; } });

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
      document.getElementById('navbar').style.boxShadow = scrollY > 50 ? '0 4px 30px rgba(0,0,0,.4)' : 'none';
    });



    <?php
    // Pass login error to JS if coming from login.php
    if (isset($_GET['error'])): ?>
      document.addEventListener('DOMContentLoaded', () => {
        openModal('login');
        const eb = document.getElementById('loginError');
        eb.textContent = '<?= addslashes(htmlspecialchars($_GET["error"])) ?>';
        eb.style.display = 'block';
      });
    <?php endif; ?>
  </script>
</body>

</html>