<?php

// student/submit_assignment.php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

requireRole('student');
$studentId = currentUser()['ref_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') 
{
    verifyCsrf();
    $assignId = (int)$_POST['assignment_id'];
    $filePath = null;
    if (!empty($_FILES['file']['name'])) 
    {
        $filePath = uploadFile($_FILES['file'], 'submissions');
    }
    if (!$filePath) 
    {
        setFlash('error','Invalid file or too large (max 5MB).'); 
    }
    else 
    {
        // Check due date
        $r = $conn->query("SELECT due_date FROM assignments WHERE id=$assignId LIMIT 1");
        $a = $r->fetch_assoc();
        $status = strtotime($a['due_date']) < time() ? 'Late' : 'Submitted';
        $stmt = $conn->prepare("
            INSERT INTO assignment_submissions (assignment_id,student_id,file_path,status)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE file_path=VALUES(file_path),status=VALUES(status),submitted_at=NOW()
        ");
        $stmt->bind_param('iiss', $assignId, $studentId, $filePath, $status);
        $stmt->execute(); $stmt->close();
        setFlash('success','Assignment submitted successfully!');
    }
}
header('Location: academics.php#assignments'); exit();
