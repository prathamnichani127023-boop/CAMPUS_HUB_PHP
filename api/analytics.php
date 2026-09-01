<?php
// api/analytics.php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) 
{ 
	echo json_encode(['error'=>'Unauthorized']); 
	exit(); 
}

$type = $_GET['type'] ?? 'overview';

if ($type === 'overview') 
{
    $data = [
        'total_students' => (int)$conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'],
        'total_faculty'  => (int)$conn->query("SELECT COUNT(*) c FROM faculty")->fetch_assoc()['c'],
        'total_courses'  => (int)$conn->query("SELECT COUNT(*) c FROM courses")->fetch_assoc()['c'],
        'fee_collected'  => (float)$conn->query("SELECT COALESCE(SUM(amount),0) c FROM fees WHERE status='Paid'")->fetch_assoc()['c'],
    ];
    echo json_encode($data);
} 
elseif ($type === 'dept_students') 
{
    $rows = $conn->query("SELECT d.name label, COUNT(s.id) value FROM departments d LEFT JOIN students s ON s.department_id=d.id GROUP BY d.id")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['data' => $rows]);
} 
elseif ($type === 'attendance_trend') 
{
    $rows = $conn->query("SELECT date, COUNT(*) total, SUM(status='Present') present FROM attendance WHERE date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY date ORDER BY date")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['data' => $rows]);

} 
elseif ($type === 'grade_dist') 
{
    $rows = $conn->query("SELECT grade, COUNT(*) cnt FROM grades WHERE grade IS NOT NULL GROUP BY grade ORDER BY FIELD(grade,'O','A+','A','B+','B','C','F')")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['data' => $rows]);
}
