<?php

session_start();
require_once 'config/db.php';
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) redirectToDashboard();

$error   = '';
$success = '';

// Load departments for the dropdown
$departments = $conn->query("SELECT id, name, code FROM departments ORDER BY name ASC");

// Preserve form values on validation error
$old = [
    'name'       => '',
    'email'      => '',
    'phone'      => '',
    'department' => '',
    'semester'   => '',
    'batch_year' => '',
    'gender'     => 'Male',
    'dob'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    verifyCsrf();

    $name       = clean($_POST['name']       ?? '');
    $email      = clean($_POST['email']      ?? '');
    $phone      = clean($_POST['phone']      ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $department = (int)($_POST['department'] ?? 0);
    $semester   = (int)($_POST['semester']   ?? 1);
    $batchYear  = clean($_POST['batch_year'] ?? '');
    $gender     = clean($_POST['gender']     ?? 'Male');
    $dob        = clean($_POST['dob']        ?? '');

    $old = [
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'department' => $department, 'semester' => $semester,
        'batch_year' => $batchYear, 'gender' => $gender, 'dob' => $dob,
    ];

    if (!$name || !$email || !$password || !$confirm || !$department || !$semester || !$batchYear)
    {
        $error = 'Please fill in all required fields.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = 'Please enter a valid email address.';
    }
    elseif (strlen($password) < 6)
    {
        $error = 'Password must be at least 6 characters long.';
    }
    elseif ($password !== $confirm)
    {
        $error = 'Passwords do not match.';
    }
    elseif ($semester < 1 || $semester > 8)
    {
        $error = 'Please select a valid semester.';
    }
    else
    {
        // Check department exists
        $deptCheck = $conn->prepare("SELECT id FROM departments WHERE id = ? LIMIT 1");
        $deptCheck->bind_param('i', $department);
        $deptCheck->execute();
        $deptValid = $deptCheck->get_result()->fetch_assoc();
        $deptCheck->close();

        // Check email uniqueness
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$deptValid)
        {
            $error = 'Please select a valid department.';
        }
        elseif ($exists)
        {
            $error = 'An account with this email already exists.';
        }
        else
        {
            $conn->begin_transaction();
            try
            {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, is_active) VALUES (?, ?, ?, 'student', ?, 1)");
                $stmt->bind_param('ssss', $name, $email, $hash, $phone);
                $stmt->execute();
                $userId = $conn->insert_id;
                $stmt->close();

                // Generate a unique enrollment number: STU + batch year + zero-padded sequence
                $prefix = 'STU' . $batchYear;
                $seqRes = $conn->query("SELECT enrollment_no FROM students WHERE enrollment_no LIKE '{$conn->real_escape_string($prefix)}%' ORDER BY enrollment_no DESC LIMIT 1");
                $seq = 1;
                if ($row = $seqRes->fetch_assoc())
                {
                    $seq = (int)substr($row['enrollment_no'], strlen($prefix)) + 1;
                }
                $enrollmentNo = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);

                $dobVal = $dob ?: null;
                $admissionDate = date('Y-m-d');

                $stmt2 = $conn->prepare("INSERT INTO students (user_id, enrollment_no, department_id, semester, batch_year, dob, gender, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param('isiissss', $userId, $enrollmentNo, $department, $semester, $batchYear, $dobVal, $gender, $admissionDate);
                $stmt2->execute();
                $stmt2->close();

                $conn->commit();

                $msg = 'Registration successful! Your enrollment number is ' . $enrollmentNo . '. Please sign in below.';
                header('Location: ' . BASE_URL . '/index.php?msg=' . urlencode($msg));
                exit();
            }
            catch (\Throwable $e)
            {
                $conn->rollback();
                $error = 'Something went wrong while creating your account. Please try again.';
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — <?= APP_NAME ?></title>
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
        <h1>Create your account</h1>
        <p>Register for portal access in three quick steps</p>
      </div>

      <?php if ($error): ?>
        <div class="auth-alert danger"><i class="bi bi-exclamation-circle"></i><span><?= $error ?></span></div>
      <?php endif; ?>

      <!-- Step indicator -->
      <div class="wizard-steps" id="wizardSteps">
        <div class="ws active" data-step="1"><span class="num">1</span><span class="lbl">Personal</span></div>
        <div class="seg" data-seg="1"></div>
        <div class="ws" data-step="2"><span class="num">2</span><span class="lbl">Academic</span></div>
        <div class="seg" data-seg="2"></div>
        <div class="ws" data-step="3"><span class="num">3</span><span class="lbl">Security</span></div>
      </div>

      <!-- Registration form -->
      <form method="POST" action="" novalidate id="registerForm">
        <?php csrfField(); ?>

        <!-- Step 1: Personal -->
        <div class="wizard-pane show" data-pane="1">
          <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" name="name" id="name" class="form-control2" placeholder="Your full name" required
                   value="<?= clean($old['name']) ?>">
          </div>

          <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" name="email" id="email" class="form-control2" placeholder="you@example.com" required
                   value="<?= clean($old['email']) ?>">
          </div>

          <div class="form-group">
            <label for="phone">Phone number <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="phone" id="phone" class="form-control2" placeholder="Optional" maxlength="15"
                   value="<?= clean($old['phone']) ?>">
          </div>

          <div class="wizard-actions">
            <button type="button" class="btn-auth" data-next="2">Continue <i class="bi bi-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- Step 2: Academic -->
        <div class="wizard-pane" data-pane="2">
          <div class="form-row-2">
            <div class="form-group">
              <label for="department">Department</label>
              <select name="department" id="department" class="form-control2" required>
                <option value="">Select department</option>
                <?php while ($d = $departments->fetch_assoc()): ?>
                  <option value="<?= $d['id'] ?>" <?= (int)$old['department'] === (int)$d['id'] ? 'selected' : '' ?>>
                    <?= clean($d['name']) ?> (<?= clean($d['code']) ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="semester">Semester</label>
              <select name="semester" id="semester" class="form-control2" required>
                <option value="">Select semester</option>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                  <option value="<?= $i ?>" <?= (int)($old['semester'] ?? 0) === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="batch_year">Batch year</label>
              <input type="number" name="batch_year" id="batch_year" class="form-control2"
                     placeholder="e.g. <?= date('Y') ?>" min="2000" max="<?= date('Y') ?>" required
                     value="<?= clean($old['batch_year']) ?>">
            </div>
            <div class="form-group">
              <label for="gender">Gender</label>
              <select name="gender" id="gender" class="form-control2">
                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                  <option value="<?= $g ?>" <?= $old['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="dob">Date of birth</label>
            <input type="date" name="dob" id="dob" class="form-control2" value="<?= clean($old['dob']) ?>">
          </div>

          <div class="wizard-actions">
            <button type="button" class="btn-auth-outline" data-prev="1"><i class="bi bi-arrow-left me-1"></i> Back</button>
            <button type="button" class="btn-auth" data-next="3">Continue <i class="bi bi-arrow-right ms-1"></i></button>
          </div>
        </div>

        <!-- Step 3: Security -->
        <div class="wizard-pane" data-pane="3">
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input type="password" name="password" id="password" class="form-control2" placeholder="Min. 6 characters" required minlength="6">
              <button class="pwd-toggle2" type="button" id="togglePwd" aria-label="Show password">
                <i class="bi bi-eye" id="eyeIcon"></i>
              </button>
            </div>
            <div class="pwd-meter"><i id="pwdMeterFill"></i></div>
            <small class="hint">Minimum 6 characters</small>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control2" placeholder="Re-enter password" required minlength="6">
            <span class="pwd-match" id="pwdMatchMsg"></span>
          </div>

          <div class="wizard-actions">
            <button type="button" class="btn-auth-outline" data-prev="2"><i class="bi bi-arrow-left me-1"></i> Back</button>
            <button type="submit" class="btn-auth"><i class="bi bi-person-plus me-1"></i> Create Account</button>
          </div>
        </div>
      </form>

      <div class="auth-switch">
        Already have an account? <a href="index.php">Sign in</a>
      </div>
    </div>
  </div>

  <!-- Right: illustration panel -->
  <div class="auth-illustration-panel">
    <?php require 'includes/auth_illustration.php'; ?>
    <div class="auth-illustration-caption">
      <strong>Your enrollment number is generated instantly</strong>
      Pick a department and semester, and you're ready to sign in.
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

/* ---------------- Registration wizard ---------------- */
(function () {
  const panes   = document.querySelectorAll('.wizard-pane');
  const steps   = document.querySelectorAll('.ws');
  const segs    = document.querySelectorAll('.seg');

  function goTo(step) {
    panes.forEach(p => p.classList.toggle('show', p.dataset.pane === String(step)));
    steps.forEach(s => {
      const n = Number(s.dataset.step);
      s.classList.toggle('active', n === step);
      s.classList.toggle('done', n < step);
    });
    segs.forEach(s => s.classList.toggle('done', Number(s.dataset.seg) < step));
  }

  function paneValid(step) {
    const pane = document.querySelector('.wizard-pane[data-pane="' + step + '"]');
    const fields = pane.querySelectorAll('input[required], select[required]');
    for (const f of fields) {
      if (!f.reportValidity()) return false;
    }
    return true;
  }

  document.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = btn.closest('.wizard-pane').dataset.pane;
      if (!paneValid(current)) return;
      goTo(Number(btn.dataset.next));
    });
  });

  document.querySelectorAll('[data-prev]').forEach(btn => {
    btn.addEventListener('click', () => goTo(Number(btn.dataset.prev)));
  });

  // Password strength meter
  const pwd = document.getElementById('password');
  const meter = document.getElementById('pwdMeterFill');
  pwd.addEventListener('input', () => {
    let score = 0;
    if (pwd.value.length >= 6) score++;
    if (pwd.value.length >= 10) score++;
    if (/[A-Z]/.test(pwd.value) && /[a-z]/.test(pwd.value)) score++;
    if (/[0-9]/.test(pwd.value)) score++;
    if (/[^A-Za-z0-9]/.test(pwd.value)) score++;
    const pct = Math.min(100, score * 20);
    const colors = ['#DC3545', '#DC3545', '#E8903A', '#E8903A', '#28A745', '#28A745'];
    meter.style.width = pct + '%';
    meter.style.background = colors[score];
    checkMatch();
  });

  // Confirm password live match
  const confirm = document.getElementById('confirm_password');
  const matchMsg = document.getElementById('pwdMatchMsg');
  function checkMatch() {
    if (!confirm.value) { matchMsg.textContent = ''; matchMsg.className = 'pwd-match'; return; }
    if (confirm.value === pwd.value) {
      matchMsg.textContent = 'Passwords match';
      matchMsg.className = 'pwd-match ok';
    } else {
      matchMsg.textContent = 'Passwords do not match';
      matchMsg.className = 'pwd-match bad';
    }
  }
  confirm.addEventListener('input', checkMatch);
})();
</script>
</body>
</html>
