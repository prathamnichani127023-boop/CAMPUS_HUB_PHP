<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
requireRole('faculty');
$u=currentUser(); 
$facultyId=$u['ref_id'];
$pageTitle='My Classes';

$courses=$conn->query("SELECT c.*,d.name dept_name,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id AND e.academic_year='".ACADEMIC_YEAR."') enrolled,
    (SELECT COUNT(*) FROM assignments a WHERE a.course_id=c.id AND a.faculty_id=$facultyId) assignments,
    (SELECT COUNT(*) FROM resources r WHERE r.course_id=c.id AND r.faculty_id=$facultyId) resources_count
    FROM class_assignments ca JOIN courses c ON c.id=ca.course_id JOIN departments d ON d.id=c.department_id
    WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'")->fetch_all(MYSQLI_ASSOC);

// Selected course students
$viewCourse=(int)($_GET['course']??0);
$courseStudents=[]; 
$courseInfo=null;
if ($viewCourse) 
{
    $r=$conn->query("SELECT c.*,d.name dept_name FROM courses c JOIN departments d ON d.id=c.department_id WHERE c.id=$viewCourse LIMIT 1");
    $courseInfo=$r->fetch_assoc();
    $courseStudents=$conn->query("SELECT s.*,u.name,u.email,
        (SELECT COUNT(*) FROM attendance att WHERE att.student_id=s.id AND att.course_id=$viewCourse) att_total,
        (SELECT SUM(att.status='Present') FROM attendance att WHERE att.student_id=s.id AND att.course_id=$viewCourse) att_present
        FROM enrollments en JOIN students s ON s.id=en.student_id JOIN users u ON u.id=s.user_id
        WHERE en.course_id=$viewCourse AND en.academic_year='".ACADEMIC_YEAR."' ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);
}

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>

<?php if($courseInfo): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-people text-success"></i> Students — <?=htmlspecialchars($courseInfo['name'])?> (<?=$courseInfo['code']?>)
    <a href="my_classes.php" class="btn btn-sm btn-outline-secondary ms-auto">Back to Courses</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Enrollment</th><th>Attendance</th><th>%</th></tr></thead>
        <tbody>
        <?php foreach($courseStudents as $i=>$s):
          $pct=$s['att_total']>0?round(($s['att_present']/$s['att_total'])*100,1):0;
        ?>
          <tr>
            <td><?=$i+1?></td>
            <td><div style="font-size:.88rem;"><?=htmlspecialchars($s['name'])?></div><div style="font-size:.74rem;color:#888;"><?=$s['email']?></div></td>
            <td><code style="font-size:.78rem;"><?=$s['enrollment_no']?></code></td>
            <td><?=(int)$s['att_present']?>/<?=(int)$s['att_total']?></td>
            <td><?=attBadge($pct)?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($courseStudents)): ?><tr><td colspan="5" class="text-center text-muted py-3">No students enrolled</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach($courses as $c): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-secondary"><?=$c['code']?></span>
            <span class="badge bg-info text-dark"><?=$c['type']?></span>
          </div>
          <h6 class="fw-bold mb-1"><?=htmlspecialchars($c['name'])?></h6>
          <div style="font-size:.8rem;color:#888;" class="mb-3"><?=htmlspecialchars($c['dept_name'])?> &bull; Semester <?=$c['semester']?> &bull; <?=$c['credits']?> Credits</div>
          <div class="row g-2 mb-3 text-center">
            <div class="col-4"><div class="fw-bold text-primary fs-5"><?=$c['enrolled']?></div><div style="font-size:.7rem;color:#aaa;">Students</div></div>
            <div class="col-4"><div class="fw-bold text-success fs-5"><?=$c['assignments']?></div><div style="font-size:.7rem;color:#aaa;">Assignments</div></div>
            <div class="col-4"><div class="fw-bold text-warning fs-5"><?=$c['resources_count']?></div><div style="font-size:.7rem;color:#aaa;">Resources</div></div>
          </div>
          <div class="d-flex gap-2">
            <a href="?course=<?=$c['id']?>" class="btn btn-sm btn-outline-success flex-1"><i class="bi bi-people me-1"></i>Students</a>
            <a href="attendance.php?course=<?=$c['id']?>" class="btn btn-sm btn-outline-primary flex-1"><i class="bi bi-person-check me-1"></i>Attendance</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(empty($courses)): ?><div class="col"><div class="p-5 text-center text-muted"><i class="bi bi-journal-x fs-1 d-block mb-2"></i>No courses assigned this year.</div></div><?php endif; ?>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
