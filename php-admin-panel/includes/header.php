<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? 'Dashboard' ?> — VC Coin Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/panel.css">
</head>
<body>

<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🪙</div>
    <div class="brand-info">
      <div class="brand-name">VC Coin Earner</div>
      <div class="brand-ver">Admin Panel v2.0</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">MAIN</div>
    <a href="/index.php"        class="nav-item <?= $current_page==='index' ? 'active':'' ?>">
      <span class="nav-icon">📊</span><span>Dashboard</span>
    </a>
    <a href="/pages/coins.php"  class="nav-item <?= $current_page==='coins' ? 'active':'' ?>">
      <span class="nav-icon">💰</span><span>Coin Manager</span>
    </a>
    <a href="/pages/keys.php"   class="nav-item <?= $current_page==='keys' ? 'active':'' ?>">
      <span class="nav-icon">🔑</span><span>Key Manager</span>
    </a>

    <div class="nav-section-label">MODERATION</div>
    <a href="/pages/blacklist.php" class="nav-item <?= $current_page==='blacklist' ? 'active':'' ?>">
      <span class="nav-icon">🚫</span><span>Blacklist</span>
    </a>
    <a href="/pages/members.php"   class="nav-item <?= $current_page==='members' ? 'active':'' ?>">
      <span class="nav-icon">👥</span><span>Members</span>
    </a>

    <div class="nav-section-label">BOT CONTROL</div>
    <a href="/pages/bot.php"    class="nav-item <?= $current_page==='bot' ? 'active':'' ?>">
      <span class="nav-icon">🤖</span><span>Bot Control</span>
    </a>
    <a href="/pages/logs.php"   class="nav-item <?= $current_page==='logs' ? 'active':'' ?>">
      <span class="nav-icon">📋</span><span>Activity Logs</span>
    </a>
    <a href="/pages/settings.php" class="nav-item <?= $current_page==='settings' ? 'active':'' ?>">
      <span class="nav-icon">⚙️</span><span>Settings</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar">👑</div>
      <div class="admin-details">
        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></div>
        <div class="admin-role">Administrator</div>
      </div>
    </div>
    <a href="/logout.php" class="btn-logout" title="Logout">⏻</a>
  </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Main content wrapper -->
<div class="main-wrap">
  <!-- Top bar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <div class="breadcrumb">
        <span class="bc-home">🏠 Admin</span>
        <span class="bc-sep">›</span>
        <span class="bc-current"><?= $page_title ?? 'Dashboard' ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <div class="status-pill" id="botStatusPill">
        <span class="status-dot"></span>
        <span id="botStatusText">Checking...</span>
      </div>
      <div class="topbar-time" id="topbarTime"></div>
    </div>
  </header>

  <!-- Page content -->
  <main class="page-content">
