<?php

// Variables expected: $pageTitle (string)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

$u = [
    'name'  => $_SESSION['name']  ?? 'User',
    'role'  => $_SESSION['role']  ?? '',
    'photo' => $_SESSION['photo'] ?? 'default.png',
];
$notifCount = 0;
// If $conn is available
if (isset($conn)) 
{
    $notifCount = unreadCount($conn, $_SESSION['user_id'] ?? 0);
}
// Uploaded avatars live in assets/uploads/avatars/; the shipped placeholder lives in assets/img/
$avatarSrc = ($u['photo'] === 'default.png')
    ? BASE_URL . '/assets/img/default.png'
    : UPLOAD_URL . 'avatars/' . $u['photo'];
// Each role's own profile/settings page has a different filename
$profilePage = ['student' => 'settings.php', 'faculty' => 'profile.php', 'admin' => 'profile.php'][$u['role']] ?? 'settings.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?? 'Dashboard' ?> — <?= APP_NAME ?></title>
  <!-- Set theme before first paint to avoid a light-mode flash -->
  <script>
    (function () {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <?php if (isset($extraCSS)) echo $extraCSS; ?>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-sm btn-light d-md-none" id="menuToggle"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title"><?= $pageTitle ?? 'Dashboard' ?></span>
  </div>
  <div class="user-area">
    <!-- Theme toggle -->
    <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" type="button">
      <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
    </button>
    <!-- Notifications -->
    <div class="notif-bell" title="Notifications">
      <i class="bi bi-bell fs-5 text-secondary"></i>
      <?php if ($notifCount > 0): ?>
        <span class="dot"><?= $notifCount ?></span>
      <?php endif; ?>
    </div>
    <!-- User menu -->
    <div class="dropdown">
      <div class="d-flex align-items-center gap-2 cursor-pointer" data-bs-toggle="dropdown">
        <img src="<?= $avatarSrc ?>" alt="avatar" class="avatar"
             onerror="this.src='<?= BASE_URL ?>/assets/img/default.png'">
        <div class="d-none d-sm-block">
          <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
          <div style="font-size:.72rem;color:#888;text-transform:capitalize;"><?= $u['role'] ?></div>
        </div>
        <i class="bi bi-chevron-down text-muted" style="font-size:.75rem;"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/<?= $u['role'] ?>/<?= $profilePage ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</div>
