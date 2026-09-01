<?php
// api/chat.php
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
$action = $_POST['action'] ?? $_GET['action'] ?? 'inbox';

if ($action === 'inbox') 
{
    $rows = $conn->query("SELECT m.*,u.name sender_name,u.role sender_role FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.receiver_id={$u['id']} ORDER BY m.sent_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['messages' => $rows]);
} 
elseif ($action === 'unread_count') 
{
    $r = $conn->query("SELECT COUNT(*) c FROM messages WHERE receiver_id={$u['id']} AND is_read=0");
    echo json_encode(['count' => (int)$r->fetch_assoc()['c']]);
} 
elseif ($action === 'send') 
{
    $to   = (int)($_POST['receiver_id'] ?? 0);
    $subj = htmlspecialchars(strip_tags($_POST['subject'] ?? ''));
    $body = htmlspecialchars(strip_tags($_POST['body'] ?? ''));
    if (!$to || !$body) 
    { 
        echo json_encode(['error'=>'Missing fields']); 
        exit(); 
    }
    $stmt = $conn->prepare("INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES (?,?,?,?)");
    $stmt->bind_param('iiss',$u['id'],$to,$subj,$body);
    $stmt->execute(); $stmt->close();
    echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
}
