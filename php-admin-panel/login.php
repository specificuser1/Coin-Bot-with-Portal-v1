<?php
require_once 'config.php';

// Already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /index.php');
    exit;
}

$error = '';
$attempts_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$lockout_key  = 'lockout_until_' . $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockout_until = $_SESSION[$lockout_key] ?? 0;
    if (time() < $lockout_until) {
        $remaining = ceil(($lockout_until - time()) / 60);
        $error = "Too many attempts. Try again in {$remaining} minute(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION[$attempts_key] = 0;
            header('Location: /index.php');
            exit;
        } else {
            $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
            if ($_SESSION[$attempts_key] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION[$lockout_key] = time() + LOCKOUT_TIME;
                $error = 'Too many failed attempts. Locked out for 15 minutes.';
            } else {
                $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION[$attempts_key];
                $error = "Invalid credentials. {$remaining} attempt(s) remaining.";
            }
        }
    }
}

$timeout = isset($_GET['timeout']);
$logout  = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — VC Coin Earner Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #080b14;
    --surface:  #0d1120;
    --border:   #1c2340;
    --accent:   #5865f2;
    --accent2:  #eb459e;
    --gold:     #fee75c;
    --green:    #57f287;
    --red:      #ed4245;
    --text:     #e8eaf0;
    --muted:    #6b7280;
    --glow:     rgba(88,101,242,0.35);
  }

  html, body {
    height: 100%;
    background: var(--bg);
    font-family: 'Syne', sans-serif;
    color: var(--text);
    overflow: hidden;
  }

  /* Animated background grid */
  .bg-grid {
    position: fixed; inset: 0; z-index: 0;
    background-image:
      linear-gradient(rgba(88,101,242,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(88,101,242,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
    animation: gridMove 20s linear infinite;
  }
  @keyframes gridMove {
    0%   { transform: translate(0,0); }
    100% { transform: translate(48px, 48px); }
  }

  /* Floating orbs */
  .orb {
    position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none; z-index: 0;
  }
  .orb-1 {
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(88,101,242,0.18) 0%, transparent 70%);
    top: -150px; left: -100px;
    animation: orbFloat1 12s ease-in-out infinite;
  }
  .orb-2 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(235,69,158,0.12) 0%, transparent 70%);
    bottom: -100px; right: -100px;
    animation: orbFloat2 15s ease-in-out infinite;
  }
  @keyframes orbFloat1 {
    0%, 100% { transform: translate(0,0) scale(1); }
    50%       { transform: translate(40px, 30px) scale(1.1); }
  }
  @keyframes orbFloat2 {
    0%, 100% { transform: translate(0,0) scale(1); }
    50%       { transform: translate(-30px,-40px) scale(1.08); }
  }

  /* Scanline effect */
  .scanlines {
    position: fixed; inset: 0; z-index: 1; pointer-events: none;
    background: repeating-linear-gradient(
      0deg, transparent, transparent 2px,
      rgba(0,0,0,0.03) 2px, rgba(0,0,0,0.03) 4px
    );
  }

  /* Main container */
  .login-wrap {
    position: relative; z-index: 10;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; padding: 20px;
  }

  .login-card {
    width: 100%; max-width: 440px;
    background: rgba(13,17,32,0.85);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 48px 44px;
    backdrop-filter: blur(24px);
    box-shadow:
      0 0 0 1px rgba(88,101,242,0.1),
      0 32px 64px rgba(0,0,0,0.6),
      inset 0 1px 0 rgba(255,255,255,0.05);
    animation: cardIn 0.5s cubic-bezier(0.34,1.56,0.64,1) forwards;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(30px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  /* Logo */
  .logo-wrap {
    text-align: center; margin-bottom: 36px;
  }
  .logo-icon {
    width: 72px; height: 72px; margin: 0 auto 16px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px;
    box-shadow: 0 8px 32px rgba(88,101,242,0.4), 0 0 0 1px rgba(88,101,242,0.3);
    animation: logoPulse 3s ease-in-out infinite;
  }
  @keyframes logoPulse {
    0%, 100% { box-shadow: 0 8px 32px rgba(88,101,242,0.4), 0 0 0 1px rgba(88,101,242,0.3); }
    50%       { box-shadow: 0 8px 48px rgba(88,101,242,0.6), 0 0 0 1px rgba(88,101,242,0.5); }
  }
  .logo-title {
    font-size: 22px; font-weight: 800; letter-spacing: -0.5px;
    background: linear-gradient(135deg, var(--text), var(--accent));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  }
  .logo-sub {
    font-family: 'DM Mono', monospace;
    font-size: 11px; color: var(--muted); letter-spacing: 2px;
    text-transform: uppercase; margin-top: 4px;
  }

  /* Alert */
  .alert {
    padding: 12px 16px; border-radius: 10px; margin-bottom: 24px;
    font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px;
    animation: alertIn 0.3s ease;
  }
  @keyframes alertIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .alert-error   { background: rgba(237,66,69,0.15); border: 1px solid rgba(237,66,69,0.3); color: #ff8a8c; }
  .alert-success { background: rgba(87,242,135,0.12); border: 1px solid rgba(87,242,135,0.3); color: var(--green); }
  .alert-warn    { background: rgba(254,231,92,0.12); border: 1px solid rgba(254,231,92,0.3); color: var(--gold); }

  /* Form */
  .form-group { margin-bottom: 20px; }
  .form-label {
    display: block; font-size: 12px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 8px;
  }
  .input-wrap { position: relative; }
  .input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: 16px; pointer-events: none;
    transition: color 0.2s;
  }
  .form-input {
    width: 100%; padding: 13px 14px 13px 44px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-family: 'DM Mono', monospace; font-size: 14px;
    transition: all 0.2s; outline: none;
  }
  .form-input::placeholder { color: var(--muted); }
  .form-input:focus {
    border-color: var(--accent);
    background: rgba(88,101,242,0.06);
    box-shadow: 0 0 0 3px rgba(88,101,242,0.15);
  }
  .form-input:focus + .input-icon,
  .input-wrap:focus-within .input-icon { color: var(--accent); }

  .toggle-pw {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--muted);
    cursor: pointer; font-size: 16px; padding: 4px;
    transition: color 0.2s;
  }
  .toggle-pw:hover { color: var(--text); }

  /* Submit button */
  .btn-login {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, var(--accent), #4752c4);
    border: none; border-radius: 10px;
    color: #fff; font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 700; letter-spacing: 0.5px;
    cursor: pointer; margin-top: 8px;
    transition: all 0.2s;
    position: relative; overflow: hidden;
  }
  .btn-login::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
    opacity: 0; transition: opacity 0.2s;
  }
  .btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(88,101,242,0.45);
  }
  .btn-login:hover::after { opacity: 1; }
  .btn-login:active { transform: translateY(0); }

  /* Footer */
  .card-footer {
    margin-top: 32px; padding-top: 24px;
    border-top: 1px solid var(--border);
    text-align: center;
  }
  .footer-text {
    font-family: 'DM Mono', monospace;
    font-size: 11px; color: var(--muted); letter-spacing: 1px;
  }
  .footer-text span { color: var(--accent); }

  /* Particles */
  .particles { position: fixed; inset: 0; z-index: 2; pointer-events: none; }
  .particle {
    position: absolute; width: 2px; height: 2px;
    background: var(--accent); border-radius: 50%;
    animation: particleFall linear infinite;
    opacity: 0;
  }
  @keyframes particleFall {
    0%   { opacity: 0; transform: translateY(-10px); }
    10%  { opacity: 0.6; }
    90%  { opacity: 0.3; }
    100% { opacity: 0; transform: translateY(100vh); }
  }
</style>
</head>
<body>

<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="scanlines"></div>
<div class="particles" id="particles"></div>

<div class="login-wrap">
  <div class="login-card">

    <div class="logo-wrap">
      <div class="logo-icon">🪙</div>
      <div class="logo-title">VC Coin Earner</div>
      <div class="logo-sub">Admin Control Panel</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php elseif ($timeout): ?>
      <div class="alert alert-warn">⏱️ Session expired. Please log in again.</div>
    <?php elseif ($logout): ?>
      <div class="alert alert-success">✅ Logged out successfully.</div>
    <?php endif; ?>

    <form method="POST" action="/login.php">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-wrap">
          <span class="input-icon">👤</span>
          <input type="text" name="username" class="form-input"
                 placeholder="Enter username" autocomplete="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" name="password" id="pwInput" class="form-input"
                 placeholder="Enter password" autocomplete="current-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw()" id="pwToggle">👁️</button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        ⚡ Access Panel
      </button>
    </form>

    <div class="card-footer">
      <div class="footer-text">Programmed by <span>SUBHAN</span> · v<?= PANEL_VERSION ?></div>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('pwInput');
  const btn = document.getElementById('pwToggle');
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
  else { inp.type = 'password'; btn.textContent = '👁️'; }
}

// Generate particles
const container = document.getElementById('particles');
for (let i = 0; i < 30; i++) {
  const p = document.createElement('div');
  p.className = 'particle';
  p.style.left = Math.random() * 100 + 'vw';
  p.style.animationDuration = (Math.random() * 12 + 8) + 's';
  p.style.animationDelay = (Math.random() * 15) + 's';
  p.style.width = p.style.height = (Math.random() * 3 + 1) + 'px';
  p.style.opacity = Math.random() * 0.6;
  const colors = ['#5865f2','#eb459e','#fee75c','#57f287'];
  p.style.background = colors[Math.floor(Math.random()*colors.length)];
  container.appendChild(p);
}
</script>
</body>
</html>
