<?php

// api/grades.php
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
$studentId = (int)($_GET['student_id'] ?? $u['ref_id']);

$rows = $conn->query("
    SELECT g.marks_obtained, g.grade, e.title exam_title, e.exam_type, e.max_marks, e.exam_date,
        c.name course_name, c.code
    FROM grades g
    JOIN exams e ON e.id = g.exam_id
    JOIN courses c ON c.id = e.course_id
    WHERE g.student_id = $studentId
    ORDER BY e.exam_date DESC
")->fetch_all(MYSQLI_ASSOC);

$overall = count($rows) > 0
    ? round(array_sum(array_map(fn($r) => ($r['marks_obtained']/$r['max_marks'])*100, $rows)) / count($rows), 1)
    : 0;

echo json_encode(['grades' => $rows, 'overall_avg' => $overall]);
