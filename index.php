<?php

session_start();
require_once 'config/db.php';
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) redirectToDashboard();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    verifyCsrf();

    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) 
    {
        $error = 'Please enter email and password.';
    } 
    else 
    {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, profile_photo FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) 
        {
            // Start session
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['name']          = $user['name'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['photo']         = $user['profile_photo'];
            $_SESSION['last_activity'] = time();

            // Get role-specific ID
            if ($user['role'] === 'student') 
            {
                $r = $conn->query("SELECT id FROM students WHERE user_id={$user['id']} LIMIT 1");
                $_SESSION['ref_id'] = $r->fetch_assoc()['id'] ?? null;
                header('Location: ' . BASE_URL . '/student/dashboard.php');
            } 
            elseif ($user['role'] === 'faculty') 
            {
                $r = $conn->query("SELECT id FROM faculty WHERE user_id={$user['id']} LIMIT 1");
                $_SESSION['ref_id'] = $r->fetch_assoc()['id'] ?? null;
                header('Location: ' . BASE_URL . '/faculty/dashboard.php');
            } 
            else 
            {
                $_SESSION['ref_id'] = null;
                header('Location: ' . BASE_URL . '/admin/dashboard.php');
            }
            exit();
        } 
        else 
        {
            $error = 'Invalid email or password.';
        }
    }
}

$msg = clean($_GET['msg'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — <?= APP_NAME ?></title>
  <!-- Set theme before first paint to avoid a light-mode flash -->
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
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
<button class="theme-toggle" id="themeToggle" title="Toggle dark mode" type="button">
  <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
</button>

<div class="auth-shell">
<div class="auth-page">

  <!-- Left: form panel -->
  <div class="auth-form-panel">
    <div class="auth-logo">
      <div class="brand-mark"><i class="bi bi-mortarboard-fill"></i></div>
      <div class="word"><?= APP_NAME ?></div>
    </div>

    <div class="auth-form-inner">
      <div class="auth-form-header">
        <h1>Welcome back</h1>
        <p>Please enter your details</p>
      </div>

      <?php if ($error): ?>
        <div class="auth-alert danger"><i class="bi bi-exclamation-circle"></i><span><?= $error ?></span></div>
      <?php endif; ?>
      <?php if ($msg): ?>
        <div class="auth-alert warning"><i class="bi bi-info-circle"></i><span><?= $msg ?></span></div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <?php csrfField(); ?>

        <div class="form-group">
          <label for="email">Username</label>
          <div class="input-wrap">
            <input type="text" name="email" id="email" class="form-control2"
                   placeholder="Enter your username" required
                   value="<?= clean($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="password" class="form-control2"
                   placeholder="Enter your password" required>
            <button class="pwd-toggle2" type="button" id="togglePwd" aria-label="Show password">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="form-row-between">
          <label class="check-row">
            <input type="checkbox" name="remember" value="1">
            Remember for 30 days
          </label>
          <a href="forgot_password.php">Forgot password</a>
        </div>

        <button type="submit" class="btn-auth">Sign in</button>

        <div class="auth-divider">or</div>

        <div id="googleErrorBox" class="auth-alert danger" style="display:none;">
          <i class="bi bi-exclamation-circle"></i><span id="googleErrorText"></span>
        </div>
        <div id="googleSignInBtn" style="display:flex;justify-content:center;"></div>
      </form>

      <div class="auth-switch">
        Don't have an account? <a href="register.php">Sign up</a>
      </div>
    </div>
  </div>

  <!-- Right: illustration panel -->
  <div class="auth-illustration-panel">
    <?php require 'includes/auth_illustration.php'; ?>
    <div class="auth-illustration-caption">
      <strong>One portal, every side of campus</strong>
      Attendance, grades, timetables and messages — all in one place.
    </div>
  </div>

</div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>

// Password toggle
document.getElementById('togglePwd').addEventListener('click', function()
{
  const pwd = document.getElementById('password');
  const eye = document.getElementById('eyeIcon');
  if (pwd.type === 'password')
  {
    pwd.type = 'text';
    eye.className = 'bi bi-eye-slash';
  }
  else
  {
    pwd.type = 'password';
    eye.className = 'bi bi-eye';
  }
});

// Dark mode toggle
const themeToggle = document.getElementById('themeToggle');
const themeIcon   = document.getElementById('themeIcon');

function applyThemeIcon(theme)
{
  themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}
applyThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');

themeToggle.addEventListener('click', () => {
  const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  applyThemeIcon(next);
});

// ---- Google Sign-In ----
function showGoogleError(msg)
{
  const box = document.getElementById('googleErrorBox');
  document.getElementById('googleErrorText').textContent = msg;
  box.style.display = 'flex';
}

async function handleGoogleCredentialResponse(response)
{
  try
  {
    const body = new URLSearchParams();
    body.append('credential', response.credential);

    const res  = await fetch('google_callback.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success)
    {
      window.location.href = data.redirect;
    }
    else
    {
      showGoogleError(data.message || 'Google sign-in failed. Please try again.');
    }
  }
  catch (err)
  {
    showGoogleError('Something went wrong with Google sign-in. Please try again.');
  }
}

window.onload = function ()
{
  if (window.google && google.accounts && google.accounts.id)
  {
    google.accounts.id.initialize({
      client_id: '<?= addslashes(GOOGLE_CLIENT_ID) ?>',
      callback: handleGoogleCredentialResponse,
    });
    google.accounts.id.renderButton(
      document.getElementById('googleSignInBtn'),
      { theme: 'outline', size: 'large', width: 320, text: 'signin_with' }
    );
  }
};
</script>
</body>
</html>
