<?php
// api/attendance.php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) 
{ 
        echo json_encode(['error'=>'Unauthorized']); exit(); 
}

$u = currentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';

if ($action === 'fetch') 
{
    $studentId = (int)($_GET['student_id'] ?? $u['ref_id']);
    $courseId  = (int)($_GET['course_id'] ?? 0);
    $where = "WHERE student_id=$studentId";
    if ($courseId) $where .= " AND course_id=$courseId";
    $rows = $conn->query("SELECT date,status,course_id FROM attendance $where ORDER BY date DESC LIMIT 60")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['attendance' => $rows]);
} 
elseif ($action === 'summary') 
{
    $studentId = (int)($_GET['student_id'] ?? $u['ref_id']);
    $rows = $conn->query("SELECT c.name,c.code,COUNT(att.id) total,SUM(att.status='Present') present FROM enrollments e JOIN courses c ON c.id=e.course_id LEFT JOIN attendance att ON att.course_id=e.course_id AND att.student_id=$studentId WHERE e.student_id=$studentId GROUP BY c.id")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) $r['percentage'] = $r['total'] > 0 ? round(($r['present']/$r['total'])*100,1) : 0;
    echo json_encode(['summary' => $rows]);
}
