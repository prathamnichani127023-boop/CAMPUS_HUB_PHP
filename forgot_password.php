<?php

session_start();
require_once 'config/db.php';
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

if (isLoggedIn()) redirectToDashboard();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    verifyCsrf();

    $email = clean($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = 'Please enter a valid email address.';
    }
    else
    {
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Always show the same success message, whether or not the email
        // exists — this avoids leaking which emails are registered.
        $success = 'If an account with that email exists, a password reset link has been sent to it.';

        if ($user)
        {
            // Generate token: the raw token goes in the email link,
            // only its hash is stored in the database.
            $rawToken   = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $rawToken);
            $expiresAt  = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

            // Invalidate any previous unused tokens for this user
            $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->bind_param('i', $user['id']);
            $del->execute();
            $del->close();

            $ins = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $user['id'], $tokenHash, $expiresAt);
            $ins->execute();
            $ins->close();

            $resetLink = BASE_URL . '/reset_password.php?token=' . $rawToken;

            $subject = APP_NAME . ' — Reset your password';
            $body = '
                <div style="font-family: Arial, sans-serif; max-width: 480px; margin: auto;">
                    <h2>Reset your password</h2>
                    <p>Hi ' . htmlspecialchars($user['name']) . ',</p>
                    <p>We received a request to reset your ' . htmlspecialchars(APP_NAME) . ' password. Click the button below to choose a new one. This link expires in ' . (int)(PASSWORD_RESET_EXPIRY / 60) . ' minutes.</p>
                    <p style="margin: 24px 0;">
                        <a href="' . htmlspecialchars($resetLink) . '" style="background:#4f46e5;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">Reset Password</a>
                    </p>
                    <p>If the button doesn\'t work, copy and paste this link into your browser:<br>' . htmlspecialchars($resetLink) . '</p>
                    <p>If you didn\'t request this, you can safely ignore this email.</p>
                </div>';

            sendMail($user['email'], $user['name'], $subject, $body);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — <?= APP_NAME ?></title>
  <script>
    (function () {
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
        <h1>Forgot password?</h1>
        <p>Enter your email and we'll send you a reset link.</p>
      </div>

      <?php if ($error): ?>
        <div class="auth-alert danger"><i class="bi bi-exclamation-circle"></i><span><?= $error ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="auth-alert warning"><i class="bi bi-info-circle"></i><span><?= $success ?></span></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="" novalidate>
        <?php csrfField(); ?>

        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-wrap">
            <input type="email" name="email" id="email" class="form-control2"
                   placeholder="Enter your registered email" required
                   value="<?= clean($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <button type="submit" class="btn-auth">Send reset link</button>
      </form>
      <?php endif; ?>

      <div class="auth-switch">
        <a href="index.php"><i class="bi bi-arrow-left"></i> Back to sign in</a>
      </div>
    </div>
  </div>

  <div class="auth-illustration-panel">
    <?php require 'includes/auth_illustration.php'; ?>
    <div class="auth-illustration-caption">
      <strong>One portal, every side of campus</strong>
      Attendance, grades, timetables and messages — all in one place.
    </div>
  </div>

</div>
</div>
</body>
</html>
