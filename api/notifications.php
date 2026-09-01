<?php

// api/notifications.php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) 
{ 
    echo json_encode(['error'=>'Unauthorized']); 
    exit(); 
}
$u = currentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'count') 
{
    $r = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id={$u['id']} AND is_read=0");
    echo json_encode(['count' => (int)$r->fetch_assoc()['c']]);
} 
elseif ($action === 'list') 
{
    $rows = $conn->query("SELECT * FROM notifications WHERE user_id={$u['id']} ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['notifications' => $rows]);
} 
elseif ($action === 'mark_read') 
{
    $id = (int)($_POST['id'] ?? 0);
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$id AND user_id={$u['id']}");
    echo json_encode(['success' => true]);
} 
elseif ($action === 'mark_all_read') 
{
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id={$u['id']}");
    echo json_encode(['success' => true]);
}
