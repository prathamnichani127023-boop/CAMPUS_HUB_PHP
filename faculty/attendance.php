<?php

session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

requireRole('faculty');

$u         = currentUser();
$facultyId = $u['ref_id'];
$pageTitle = 'Attendance';

//  My courses dropdown 
$myCourses = $conn->query("
    SELECT c.id, c.name, c.code FROM class_assignments ca
    JOIN courses c ON c.id=ca.course_id
    WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'
")->fetch_all(MYSQLI_ASSOC);

$selectedCourse = (int)($_GET['course'] ?? ($myCourses[0]['id'] ?? 0));
$selectedDate   = clean($_GET['date'] ?? date('Y-m-d'));

//  Handle POST (mark attendance) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark'])) 
  {
    verifyCsrf();
    $courseId = (int)$_POST['course_id'];
    $date     = clean($_POST['date']);
    $statuses = $_POST['status'] ?? [];

    foreach ($statuses as $stdId => $status) 
    {
        $stdId  = (int)$stdId;
        $status = in_array($status, ['Present','Absent','Late']) ? $status : 'Absent';
        $stmt   = $conn->prepare("
            INSERT INTO attendance (student_id,course_id,faculty_id,date,status)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), faculty_id=VALUES(faculty_id)
        ");
        $stmt->bind_param('iiiiss', $stdId, $courseId, $facultyId, $date, $status);
        $stmt->execute();
        $stmt->close();
    }
    setFlash('success', 'Attendance saved for ' . fmtDate($date));
    header("Location: attendance.php?course=$courseId&date=$date");
    exit();
}

//  Students in selected course 
$students = [];
$existing = [];
if ($selectedCourse) 
{
    $students = $conn->query("
        SELECT s.id, u.name, s.enrollment_no
        FROM enrollments e
        JOIN students s ON s.id=e.student_id
        JOIN users u ON u.id=s.user_id
        WHERE e.course_id=$selectedCourse AND e.academic_year='".ACADEMIC_YEAR."'
        ORDER BY u.name
    ")->fetch_all(MYSQLI_ASSOC);

    // Already marked for this date?
    $res = $conn->query("
        SELECT student_id, status FROM attendance
        WHERE course_id=$selectedCourse AND date='$selectedDate'
    ");
    while ($row = $res->fetch_assoc()) $existing[$row['student_id']] = $row['status'];
}

//  Attendance report for selected course 
$report = $conn->query("
    SELECT s.id, u.name, s.enrollment_no,
        COUNT(att.id) AS total,
        SUM(att.status='Present') AS present,
        SUM(att.status='Absent') AS absent,
        SUM(att.status='Late') AS late
    FROM enrollments e
    JOIN students s ON s.id=e.student_id
    JOIN users u ON u.id=s.user_id
    LEFT JOIN attendance att ON att.student_id=s.id AND att.course_id=$selectedCourse
    WHERE e.course_id=$selectedCourse AND e.academic_year='".ACADEMIC_YEAR."'
    GROUP BY s.id ORDER BY u.name
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_faculty.php';
?>

<div class="d-flex">
  <div class="main-content w-100">
    <div class="content-wrapper">
      <?php showFlash(); ?>

      <!-- Course + Date selector -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Select Course</label>
              <select name="course" class="form-select" onchange="this.form.submit()">
                <?php foreach ($myCourses as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $selectedCourse==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['code'].' — '.$c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
              <button class="btn btn-primary w-100">Load</button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3">

        <!-- Mark Attendance -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-person-check text-success"></i>
              Mark Attendance — <?= fmtDate($selectedDate) ?>
              <?php if (!empty($existing)): ?>
                <span class="badge bg-warning text-dark ms-auto">Already Marked</span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <?php if (empty($students)): ?>
                <p class="text-muted">No students enrolled in this course.</p>
              <?php else: ?>
                <form method="POST">
                  <?php csrfField(); ?>
                  <input type="hidden" name="mark" value="1">
                  <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
                  <input type="hidden" name="date" value="<?= $selectedDate ?>">

                  <!-- Bulk buttons -->
                  <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="setAll('Present')">All Present</button>
                    <button type="button" class="btn btn-sm btn-outline-danger"  onclick="setAll('Absent')">All Absent</button>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-sm table-hover">
                      <thead><tr><th>#</th><th>Student</th><th>Enroll No</th><th>Status</th></tr></thead>
                      <tbody>
                        <?php foreach ($students as $i => $std): ?>
                          <?php $status = $existing[$std['id']] ?? 'Present'; ?>
                          <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($std['name']) ?></td>
                            <td><code style="font-size:.78rem;"><?= $std['enrollment_no'] ?></code></td>
                            <td>
                              <select name="status[<?= $std['id'] ?>]" class="form-select form-select-sm att-select" style="width:110px;">
                                <option value="Present" <?= $status=='Present'?'selected':'' ?>>Present</option>
                                <option value="Absent"  <?= $status=='Absent' ?'selected':'' ?>>Absent</option>
                                <option value="Late"    <?= $status=='Late'   ?'selected':'' ?>>Late</option>
                              </select>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <button type="submit" class="btn btn-success mt-2">
                    <i class="bi bi-check-lg me-1"></i> Save Attendance
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Attendance Report -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-bar-chart text-success"></i> Attendance Summary
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                  <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>%</th></tr></thead>
                  <tbody>
                    <?php foreach ($report as $r): ?>
                      <?php $pct = $r['total'] > 0 ? round(($r['present']/$r['total'])*100,1) : 0; ?>
                      <tr>
                        <td style="font-size:.83rem;"><?= htmlspecialchars($r['name']) ?></td>
                        <td><span class="badge bg-success"><?= $r['present'] ?></span></td>
                        <td><span class="badge bg-danger"><?= $r['absent'] ?></span></td>
                        <td><?= attBadge($pct) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php
$extraJS = <<<JS
<script>
function setAll(val) 
{
  document.querySelectorAll('.att-select').forEach(s => s.value = val);
}
</script>
JS;
include '../includes/footer.php';
?>
