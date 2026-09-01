<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('faculty');
$u=currentUser(); $facultyId=$u['ref_id'];
$pageTitle='Exams';

$myCourses=$conn->query("SELECT c.id,c.name,c.code FROM class_assignments ca JOIN courses c ON c.id=ca.course_id WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'")->fetch_all(MYSQLI_ASSOC);

// Create exam
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_exam'])) 
{
    verifyCsrf();
    $cid=(int)$_POST['course_id']; 
    $title=clean($_POST['title']);
    $type=clean($_POST['exam_type']); 
    $date=clean($_POST['exam_date']);
    $time=clean($_POST['start_time']); 
    $dur=(int)$_POST['duration_min'];
    $max=(float)$_POST['max_marks']; 
    $room=clean($_POST['room']);
    $conn->query("INSERT INTO exams (course_id,title,exam_type,exam_date,start_time,duration_min,max_marks,room) VALUES ($cid,'$title','$type','$date','$time',$dur,$max,'$room')");
    setFlash('success','Exam scheduled!'); 
    header('Location: exams.php'); 
    exit();
}

// Enter marks
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['enter_marks'])) 
{
    verifyCsrf();
    $examId=(int)$_POST['exam_id'];
    $marksArr=$_POST['marks']??[];
    foreach ($marksArr as $stdId=>$marks) 
    {
        $stdId=(int)$stdId; 
        $marks=(float)$marks;
        // Get max marks
        $r=$conn->query("SELECT max_marks FROM exams WHERE id=$examId LIMIT 1");
        $max=$r->fetch_assoc()['max_marks'];
        $pct=($marks/$max)*100;
        $grade=calcGrade($pct);
        $stmt=$conn->prepare("INSERT INTO grades (student_id,exam_id,marks_obtained,grade,entered_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained),grade=VALUES(grade)");
        $stmt->bind_param('iidsi',$stdId,$examId,$marks,$grade,$u['id']);
        $stmt->execute(); $stmt->close();
    }
    setFlash('success','Marks saved!'); 
    header('Location: exams.php?marks='.$examId); 
    exit();
}

// Delete exam
if (isset($_GET['delete'])) 
  { 
    $conn->query("DELETE FROM exams WHERE id=".(int)$_GET['delete']); 
    setFlash('success','Exam deleted.'); 
    header('Location: exams.php'); 
    exit(); 
  }

// View marks entry
$marksExamId=(int)($_GET['marks']??0);
$marksExam=null; 
$examStudents=[];
if ($marksExamId) 
  {
    $r=$conn->query("SELECT e.*,c.name cname FROM exams e JOIN courses c ON c.id=e.course_id WHERE e.id=$marksExamId LIMIT 1");
    $marksExam=$r->fetch_assoc();
    if ($marksExam) 
    {
        $examStudents=$conn->query("SELECT s.id,u.name,s.enrollment_no,g.marks_obtained,g.grade FROM enrollments en JOIN students s ON s.id=en.student_id JOIN users u ON u.id=s.user_id LEFT JOIN grades g ON g.student_id=s.id AND g.exam_id=$marksExamId WHERE en.course_id={$marksExam['course_id']} AND en.academic_year='".ACADEMIC_YEAR."' ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);
    }
}

$exams=$conn->query("SELECT e.*,c.name cname,c.code,(SELECT COUNT(*) FROM grades g WHERE g.exam_id=e.id) graded,(SELECT COUNT(*) FROM enrollments en WHERE en.course_id=e.course_id AND en.academic_year='".ACADEMIC_YEAR."') total FROM exams e JOIN courses c ON c.id=e.course_id JOIN class_assignments ca ON ca.course_id=c.id WHERE ca.faculty_id=$facultyId ORDER BY e.exam_date DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>

<?php if($marksExam): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-pencil-square text-success"></i> Enter Marks — <?=htmlspecialchars($marksExam['title'])?> (<?=htmlspecialchars($marksExam['cname'])?>)
    <span class="badge bg-secondary ms-auto">Max: <?=$marksExam['max_marks']?></span>
    <a href="exams.php" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <?php csrfField(); ?><input type="hidden" name="enter_marks" value="1"><input type="hidden" name="exam_id" value="<?=$marksExamId?>">
      <div class="table-responsive">
        <table class="table table-hover mb-3">
          <thead><tr><th>#</th><th>Student</th><th>Enrollment No</th><th>Marks / <?=$marksExam['max_marks']?></th><th>Grade</th></tr></thead>
          <tbody>
          <?php foreach($examStudents as $i=>$s): ?>
            <tr>
              <td><?=$i+1?></td>
              <td><?=htmlspecialchars($s['name'])?></td>
              <td><code style="font-size:.78rem;"><?=$s['enrollment_no']?></code></td>
              <td><input type="number" name="marks[<?=$s['id']?>]" class="form-control form-control-sm marks-input" style="width:100px;" value="<?=$s['marks_obtained']??''?>" min="0" max="<?=$marksExam['max_marks']?>" step="0.5" data-max="<?=$marksExam['max_marks']?>"></td>
              <td class="grade-cell"><?php if($s['marks_obtained']!==null): ?><span class="badge bg-info text-dark"><?=$s['grade']?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button class="btn btn-success"><i class="bi bi-save me-1"></i>Save All Marks</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Schedule Exam -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle text-success me-2"></i>Schedule Exam</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="create_exam" value="1">
          <div class="mb-2"><label class="form-label">Course</label>
            <select name="course_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach($myCourses as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['code'].' — '.$c['name'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Exam Title</label><input type="text" name="title" class="form-control" required placeholder="Mid-Term Exam 2025"></div>
          <div class="mb-2"><label class="form-label">Type</label>
            <select name="exam_type" class="form-select"><option>Internal</option><option>Mid-Term</option><option>End-Term</option><option>Practical</option></select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label">Date</label><input type="date" name="exam_date" class="form-control" required></div>
            <div class="col"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" value="10:00"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label">Duration (min)</label><input type="number" name="duration_min" class="form-control" value="60"></div>
            <div class="col"><label class="form-label">Max Marks</label><input type="number" name="max_marks" class="form-control" value="100" step="0.5"></div>
          </div>
          <div class="mb-3"><label class="form-label">Room</label><input type="text" name="room" class="form-control" placeholder="A-101"></div>
          <button class="btn btn-success w-100">Schedule Exam</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Exam List -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-check text-success me-2"></i>My Exams</div>
      <div class="card-body p-0">
        <?php if(empty($exams)): ?><div class="p-4 text-center text-muted">No exams scheduled yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Title</th><th>Course</th><th>Type</th><th>Date</th><th>Marks</th><th>Graded</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach($exams as $e): ?>
                <tr>
                  <td style="font-size:.86rem;"><?=htmlspecialchars($e['title'])?></td>
                  <td><span class="badge bg-secondary"><?=$e['code']?></span></td>
                  <td><span class="badge bg-info text-dark"><?=$e['exam_type']?></span></td>
                  <td style="font-size:.82rem;"><?=fmtDate($e['exam_date'])?></td>
                  <td><?=$e['max_marks']?></td>
                  <td><span class="badge bg-light text-dark"><?=$e['graded']?>/<?=$e['total']?></span></td>
                  <td class="d-flex gap-1">
                    <a href="?marks=<?=$e['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="?delete=<?=$e['id']?>" onclick="return confirm('Delete exam?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php
$extraJS='<script>

// Auto-calculate grade preview on marks input
document.querySelectorAll(".marks-input").forEach(function(inp){
  inp.addEventListener("input",function(){
    var max=parseFloat(this.dataset.max)||100;
    var val=parseFloat(this.value)||0;
    var pct=(val/max)*100;
    var g=pct>=90?"O":pct>=80?"A+":pct>=70?"A":pct>=60?"B+":pct>=50?"B":pct>=40?"C":"F";
    var cell=this.closest("tr").querySelector(".grade-cell");
    if(cell) cell.innerHTML=val?"<span class=\"badge bg-info text-dark\">"+g+"</span>":"<span class=\"text-muted\">—</span>";
  });
});
</script>';
include '../includes/footer.php'; ?>
