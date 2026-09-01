<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle = 'Academic Management';

$departments = $conn->query("SELECT * FROM departments")->fetch_all(MYSQLI_ASSOC);
$facultyList = $conn->query("SELECT f.id,u.name,f.department_id FROM faculty f JOIN users u ON u.id=f.user_id ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);

// Add course
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_course'])) 
{
    verifyCsrf();
    $name=clean($_POST['name']); $code=clean($_POST['code']);
    $dept=(int)$_POST['department_id']; $sem=(int)$_POST['semester'];
    $credits=(int)$_POST['credits']; $type=clean($_POST['type']);
    $conn->query("INSERT INTO courses (name,code,department_id,semester,credits,type) VALUES ('$name','$code',$dept,$sem,$credits,'$type')");
    setFlash('success','Course added!'); header('Location: academic_management.php'); exit();
}

// Assign faculty to course
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['assign'])) 
{
    verifyCsrf();
    $fid=(int)$_POST['faculty_id']; $cid=(int)$_POST['course_id']; $yr=clean($_POST['academic_year']);
    $conn->query("INSERT IGNORE INTO class_assignments (faculty_id,course_id,academic_year) VALUES ($fid,$cid,'$yr')");
    setFlash('success','Faculty assigned!'); header('Location: academic_management.php#assign'); exit();
}

// Add timetable entry
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_tt'])) 
{
    verifyCsrf();
    $cid=(int)$_POST['course_id']; $fid=(int)$_POST['faculty_id'];
    $day=clean($_POST['day']); $start=clean($_POST['start_time']);
    $end=clean($_POST['end_time']); $room=clean($_POST['room']); $yr=ACADEMIC_YEAR;
    $conn->query("INSERT INTO timetable (course_id,faculty_id,day_of_week,start_time,end_time,room,academic_year) VALUES ($cid,$fid,'$day','$start','$end','$room','$yr')");
    setFlash('success','Timetable entry added!'); header('Location: academic_management.php#timetable'); exit();
}

if (isset($_GET['del_course'])) { $conn->query("DELETE FROM courses WHERE id=".(int)$_GET['del_course']); setFlash('success','Course deleted.'); header('Location: academic_management.php'); exit(); }
if (isset($_GET['del_tt'])) { $conn->query("DELETE FROM timetable WHERE id=".(int)$_GET['del_tt']); setFlash('success','Entry removed.'); header('Location: academic_management.php#timetable'); exit(); }

$courses    = $conn->query("SELECT c.*,d.name dept_name FROM courses c JOIN departments d ON d.id=c.department_id ORDER BY c.semester,c.name")->fetch_all(MYSQLI_ASSOC);
$assigns    = $conn->query("SELECT ca.*,c.name cname,c.code,u.name fname FROM class_assignments ca JOIN courses c ON c.id=ca.course_id JOIN faculty f ON f.id=ca.faculty_id JOIN users u ON u.id=f.user_id WHERE ca.academic_year='".ACADEMIC_YEAR."' ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
$timetable  = $conn->query("SELECT t.*,c.name cname,c.code,u.name fname FROM timetable t JOIN courses c ON c.id=t.course_id JOIN faculty f ON f.id=t.faculty_id JOIN users u ON u.id=f.user_id WHERE t.academic_year='".ACADEMIC_YEAR."' ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),t.start_time")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; include '../includes/sidebar_admin.php';
?>

<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#courses"><i class="bi bi-book me-1"></i>Courses</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#assign"><i class="bi bi-person-check me-1"></i>Faculty Assignment</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#timetable"><i class="bi bi-calendar3 me-1"></i>Timetable</a></li>
</ul>
<div class="tab-content">

<!-- COURSES TAB -->
<div class="tab-pane fade show active" id="courses">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Add Course</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="add_course" value="1">
            <div class="mb-2"><label class="form-label">Course Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Course Code</label><input type="text" name="code" class="form-control" required placeholder="CS601"></div>
            <div class="mb-2"><label class="form-label">Department</label><select name="department_id" class="form-select"><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=$d['name']?></option><?php endforeach;?></select></div>
            <div class="row g-2 mb-2">
              <div class="col"><label class="form-label">Semester</label><select name="semester" class="form-select"><?php for($i=1;$i<=8;$i++): ?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
              <div class="col"><label class="form-label">Credits</label><input type="number" name="credits" class="form-control" value="4" min="1" max="6"></div>
            </div>
            <div class="mb-3"><label class="form-label">Type</label><select name="type" class="form-select"><option>Theory</option><option>Practical</option><option>Elective</option></select></div>
            <button class="btn btn-warning w-100">Add Course</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-book text-warning me-2"></i>All Courses (<?=count($courses)?>)</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Name</th><th>Code</th><th>Dept</th><th>Sem</th><th>Credits</th><th>Type</th><th>Del</th></tr></thead>
              <tbody>
              <?php foreach($courses as $c): ?>
                <tr><td><?=htmlspecialchars($c['name'])?></td><td><span class="badge bg-secondary"><?=$c['code']?></span></td><td style="font-size:.82rem;"><?=htmlspecialchars($c['dept_name'])?></td><td><?=$c['semester']?></td><td><?=$c['credits']?></td><td><span class="badge bg-info text-dark"><?=$c['type']?></span></td><td><a href="?del_course=<?=$c['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete course?')"><i class="bi bi-trash"></i></a></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ASSIGN TAB -->
<div class="tab-pane fade" id="assign">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-person-check text-warning me-2"></i>Assign Faculty to Course</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="assign" value="1">
            <div class="mb-2"><label class="form-label">Faculty</label><select name="faculty_id" class="form-select"><?php foreach($facultyList as $f): ?><option value="<?=$f['id']?>"><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <div class="mb-2"><label class="form-label">Course</label><select name="course_id" class="form-select"><?php foreach($courses as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['code'].' — '.$c['name'])?></option><?php endforeach;?></select></div>
            <div class="mb-3"><label class="form-label">Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?=ACADEMIC_YEAR?>"></div>
            <button class="btn btn-warning w-100">Assign</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-list-check text-warning me-2"></i>Current Assignments (<?=ACADEMIC_YEAR?>)</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Faculty</th><th>Course</th><th>Code</th></tr></thead>
              <tbody>
              <?php foreach($assigns as $a): ?>
                <tr><td><?=htmlspecialchars($a['fname'])?></td><td><?=htmlspecialchars($a['cname'])?></td><td><span class="badge bg-secondary"><?=$a['code']?></span></td></tr>
              <?php endforeach; ?>
              <?php if(empty($assigns)): ?><tr><td colspan="3" class="text-center text-muted py-3">No assignments yet</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TIMETABLE TAB -->
<div class="tab-pane fade" id="timetable">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Add Timetable Entry</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="add_tt" value="1">
            <div class="mb-2"><label class="form-label">Course</label><select name="course_id" class="form-select"><?php foreach($courses as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['code'].' — '.$c['name'])?></option><?php endforeach;?></select></div>
            <div class="mb-2"><label class="form-label">Faculty</label><select name="faculty_id" class="form-select"><?php foreach($facultyList as $f): ?><option value="<?=$f['id']?>"><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <div class="mb-2"><label class="form-label">Day</label><select name="day" class="form-select"><option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option>Friday</option><option>Saturday</option></select></div>
            <div class="row g-2 mb-2">
              <div class="col"><label class="form-label">Start</label><input type="time" name="start_time" class="form-control" value="09:00"></div>
              <div class="col"><label class="form-label">End</label><input type="time" name="end_time" class="form-control" value="10:00"></div>
            </div>
            <div class="mb-3"><label class="form-label">Room</label><input type="text" name="room" class="form-control" placeholder="A-101"></div>
            <button class="btn btn-warning w-100">Add Entry</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-calendar3 text-warning me-2"></i>Timetable (<?=ACADEMIC_YEAR?>)</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Day</th><th>Course</th><th>Faculty</th><th>Time</th><th>Room</th><th>Del</th></tr></thead>
              <tbody>
              <?php foreach($timetable as $t): ?>
                <tr><td><span class="badge bg-primary"><?=$t['day_of_week']?></span></td><td style="font-size:.83rem;"><?=htmlspecialchars($t['cname'])?> <span class="badge bg-secondary"><?=$t['code']?></span></td><td style="font-size:.82rem;"><?=htmlspecialchars($t['fname'])?></td><td style="font-size:.8rem;"><?=substr($t['start_time'],0,5)?> – <?=substr($t['end_time'],0,5)?></td><td><?=$t['room']?></td><td><a href="?del_tt=<?=$t['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove?')"><i class="bi bi-trash"></i></a></td></tr>
              <?php endforeach; ?>
              <?php if(empty($timetable)): ?><tr><td colspan="6" class="text-center text-muted py-3">No entries yet</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
