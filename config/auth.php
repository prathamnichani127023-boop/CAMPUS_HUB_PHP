<?php

if (session_status() === PHP_SESSION_NONE) 
{
    session_start();
}

require_once __DIR__ . '/constants.php';

//  Check if user is logged in 
function isLoggedIn(): bool 
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

//  Require login 
function requireLogin(): void 
{
    if (!isLoggedIn()) 
    {
        header('Location: ' . BASE_URL . '/index.php?msg=Please+login+first');
        exit();
    }
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) 
    {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?msg=Session+expired');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

//  Require specific role 
function requireRole(string $role): void 
{
    requireLogin();
    if ($_SESSION['role'] !== $role) 
    {
        header('Location: ' . BASE_URL . '/index.php?msg=Unauthorized+access');
        exit();
    }
}

//  Get current user info 
function currentUser(): array 
{
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['name']       ?? '',
        'email' => $_SESSION['email']      ?? '',
        'role'  => $_SESSION['role']       ?? '',
        'photo' => $_SESSION['photo']      ?? 'default.png',
        'ref_id'=> $_SESSION['ref_id']     ?? null,  // student_id or faculty_id
    ];
}

//  Redirect logged-in users to their dashboard 
function redirectToDashboard(): void 
{
    if (!isLoggedIn()) return;
    $role = $_SESSION['role'];
    $map  = ['student' => 'student', 'faculty' => 'faculty', 'admin' => 'admin'];
    if (isset($map[$role])) 
    {
        header('Location: ' . BASE_URL . '/' . $map[$role] . '/dashboard.php');
        exit();
    }
}
