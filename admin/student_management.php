<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle = 'Student Management';

$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Add student
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) 
{
    verifyCsrf();
    $name=$_POST['name']; 
    $email=$_POST['email']; 
    $pass=password_hash($_POST['password'],PASSWORD_DEFAULT);
    $enroll=$_POST['enrollment_no']; 
    $dept=(int)$_POST['department_id']; 
    $sem=(int)$_POST['semester'];
    $batch=$_POST['batch_year']; 
    $dob=$_POST['dob']; 
    $gender=$_POST['gender'];
    $conn->begin_transaction();
    try 
    {
        $s=$conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'student')");
        $s->bind_param('sss',$name,$email,$pass); 
        $s->execute();
        $uid=$conn->insert_id; 
        $s->close();
        $s=$conn->prepare("INSERT INTO students (user_id,enrollment_no,department_id,semester,batch_year,dob,gender,admission_date) VALUES (?,?,?,?,?,?,?,CURDATE())");
        $s->bind_param('isiisss',$uid,$enroll,$dept,$sem,$batch,$dob,$gender); 
        $s->execute(); 
        $s->close();
        $conn->commit(); 
        setFlash('success','Student added successfully!');
    } 
    catch(Exception $e)
    { 
        $conn->rollback(); 
        setFlash('error','Error: '.$e->getMessage()); 
    }
    header('Location: student_management.php'); 
    exit();
}

// Delete student
if (isset($_GET['delete'])) 
{
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=(SELECT user_id FROM students WHERE id=$id)");
    setFlash('success','Student deleted.'); 
    header('Location: student_management.php'); 
    exit();
}

// Search & filter
$search=clean($_GET['search']??''); 
$deptFilter=(int)($_GET['dept']??0);
$where="WHERE 1=1"; 
$params=[];
if($search) $where.=" AND (u.name LIKE '%$search%' OR s.enrollment_no LIKE '%$search%' OR u.email LIKE '%$search%')";
if($deptFilter) $where.=" AND s.department_id=$deptFilter";

$students=$conn->query("SELECT s.*,u.name,u.email,u.is_active,d.name dept_name FROM students s JOIN users u ON u.id=s.user_id JOIN departments d ON d.id=s.department_id $where ORDER BY s.id DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  
  <!-- Add Form -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus text-warning me-2"></i>Add New Student</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?>
          <input type="hidden" name="add" value="1">
          <div class="mb-2"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required value="password123"></div>
          <div class="mb-2"><label class="form-label">Enrollment No</label><input type="text" name="enrollment_no" class="form-control" required placeholder="STU2024001"></div>
          <div class="mb-2"><label class="form-label">Department</label>
            <select name="department_id" class="form-select" required>
              <?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>"><?= $d['name'] ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label">Semester</label><select name="semester" class="form-select"><?php for($i=1;$i<=8;$i++): ?><option value="<?=$i?>"><?=$i?></option><?php endfor; ?></select></div>
            <div class="col"><label class="form-label">Batch Year</label><input type="number" name="batch_year" class="form-control" value="<?= date('Y') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Gender</label>
            <select name="gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select>
          </div>
          <button class="btn btn-warning w-100 fw-semibold">Add Student</button>
        </form>
      </div>
    </div>
  </div>
  <!-- List -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <form method="GET" class="row g-2">
          <div class="col"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search name/email/enrollment..." value="<?= $search ?>"></div>
          <div class="col-auto"><select name="dept" class="form-select form-select-sm"><option value="">All Depts</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>" <?=$deptFilter==$d['id']?'selected':''?>><?=$d['name']?></option><?php endforeach;?></select></div>
          <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </form>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Enrollment</th><th>Dept</th><th>Sem</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($students as $i=>$s): ?>
              <tr>
                <td><?=$i+1?></td>
                <td><div style="font-size:.88rem;"><?=htmlspecialchars($s['name'])?></div><div style="font-size:.74rem;color:#888;"><?=$s['email']?></div></td>
                <td><code style="font-size:.78rem;"><?=$s['enrollment_no']?></code></td>
                <td style="font-size:.82rem;"><?=htmlspecialchars($s['dept_name'])?></td>
                <td><?=$s['semester']?></td>
                <td><span class="badge bg-<?=$s['is_active']?'success':'secondary'?>"><?=$s['is_active']?'Active':'Inactive'?></span></td>
                <td><a href="?delete=<?=$s['id']?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this student?" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($students)): ?><tr><td colspan="7" class="text-center text-muted py-3">No students found</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
