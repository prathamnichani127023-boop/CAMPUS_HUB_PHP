<?php

session_start();
require_once 'config/db.php';
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['credential']))
{
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing Google credential.']);
    exit();
}

$credential = $_POST['credential'];

// Verify the ID token with Google
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);

$ctx  = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
$resp = @file_get_contents($verifyUrl, false, $ctx);
$payload = $resp ? json_decode($resp, true) : null;

if (!$payload || isset($payload['error']))
{
    echo json_encode(['success' => false, 'message' => 'Could not verify Google sign-in. Please try again.']);
    exit();
}

// Must be issued for OUR app
if (!hash_equals(GOOGLE_CLIENT_ID, $payload['aud'] ?? ''))
{
    echo json_encode(['success' => false, 'message' => 'This Google sign-in was not issued for this app.']);
    exit();
}

if (empty($payload['email']) || ($payload['email_verified'] ?? 'false') !== 'true')
{
    echo json_encode(['success' => false, 'message' => 'Your Google email is not verified.']);
    exit();
}

$googleId = $payload['sub'];
$email    = $payload['email'];
$name     = $payload['name'] ?? $email;
$photo    = $payload['picture'] ?? null;

// Look for an existing account (by google_id first, then by email)
$stmt = $conn->prepare("SELECT id, name, email, role, profile_photo, is_active
                         FROM users WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user)
{
    echo json_encode([
        'success' => false,
        'message' => 'No account found for ' . $email . '. Please register first, then Google sign-in will work.',
    ]);
    exit();
}

if (!$user['is_active'])
{
    echo json_encode(['success' => false, 'message' => 'This account is inactive. Contact the admin.']);
    exit();
}

// Link the Google account to this user if not already linked
$link = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ? AND (google_id IS NULL OR google_id = '')");
$link->bind_param('si', $googleId, $user['id']);
$link->execute();
$link->close();

// Log in
$_SESSION['user_id']       = $user['id'];
$_SESSION['name']          = $user['name'];
$_SESSION['email']         = $user['email'];
$_SESSION['role']          = $user['role'];
$_SESSION['photo']         = $user['profile_photo'];
$_SESSION['last_activity'] = time();

$redirect = BASE_URL . '/index.php';
if ($user['role'] === 'student')
{
    $r = $conn->query("SELECT id FROM students WHERE user_id={$user['id']} LIMIT 1");
    $_SESSION['ref_id'] = $r->fetch_assoc()['id'] ?? null;
    $redirect = BASE_URL . '/student/dashboard.php';
}
elseif ($user['role'] === 'faculty')
{
    $r = $conn->query("SELECT id FROM faculty WHERE user_id={$user['id']} LIMIT 1");
    $_SESSION['ref_id'] = $r->fetch_assoc()['id'] ?? null;
    $redirect = BASE_URL . '/faculty/dashboard.php';
}
else
{
    $_SESSION['ref_id'] = null;
    $redirect = BASE_URL . '/admin/dashboard.php';
}

echo json_encode(['success' => true, 'redirect' => $redirect]);
