<?php

session_start();
require_once 'config/db.php';
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirectToDashboard();

$error       = '';
$success     = '';
$tokenValid  = false;
$rawToken    = clean($_GET['token'] ?? ($_POST['token'] ?? ''));

if (!$rawToken)
{
    $error = 'Invalid or missing reset link';
}
else
{
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.name, u.email
                             FROM password_resets pr
                             JOIN users u ON u.id = pr.user_id
                             WHERE pr.token_hash = ? LIMIT 1");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset)
    {
        $error = 'This reset link is invalid.';
    }
    elseif ($reset['used'])
    {
        $error = 'This reset link has already been used. Please request a new one.';
    }
    elseif (strtotime($reset['expires_at']) < time())
    {
        $error = 'This reset link has expired. Please request a new one.';
    }
    else
    {
        $tokenValid = true;
    }
}

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    verifyCsrf();

    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6)
    {
        $error = 'Password must be at least 6 characters long.';
    }
    elseif ($password !== $confirm)
    {
        $error = 'Passwords do not match.';
    }
    else
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $reset['user_id']);
        $upd->execute();
        $upd->close();

        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $mark->bind_param('i', $reset['id']);
        $mark->execute();
        $mark->close();

        header('Location: index.php?msg=' . urlencode('Password reset successfully. Please sign in.'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — <?= APP_NAME ?></title>
  <script>
    (function () 
	{
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
  
  <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<div class="auth-shell">
<div class="auth-page">

  <div class="auth-form-panel">
    <div class="auth-logo">
      <div class="brand-mark"><i class="bi bi-mortarboard-fill"></i></div>
      <div class="word"><?= APP_NAME ?></div>
    </div>

    <div class="auth-form-inner">
      <div class="auth-form-header">
        <h1> Reset password </h1>
        <p> Choose a new password for your account. </p>
      </div>

      <?php 
		if ($error): ?>
        <div class="auth-alert danger"><i class="bi bi-exclamation-circle"></i><span><?= $error ?></span></div>
      <?php endif; ?>

      <?php if ($tokenValid): ?>
      <form method="POST" action="" novalidate>
        <?php csrfField(); ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">

        <div class="form-group">
          <label for="password">New password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="password" class="form-control2"
                   placeholder="At least 6 characters" required minlength="6">
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm new password</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control2"
                   placeholder="Re-enter new password" required minlength="6">
          </div>
        </div>

        <button type="submit" class="btn-auth">Reset password</button>
      </form>
      <?php 
		else: 
		 ?>
        <div class="auth-switch">
          <a href="forgot_password.php">Request a new reset link</a>
        </div>
      <?php endif; ?>

      <div class="auth-switch">
        <a href="index.php"><i class="bi bi-arrow-left"></i> Back to sign in</a>
      </div>
    </div>
  </div>

  <div class="auth-illustration-panel">
    <?php 
		require 'includes/auth_illustration.php'; 
	?>
	
    <div class="auth-illustration-caption">
	
      <strong> One portal, every side of campus </strong>
      Attendance, grades, timetables and messages — all in one place.
    </div>
  </div>

</div>
</div>
</body>
</html>
