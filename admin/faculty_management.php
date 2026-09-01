<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle = 'Faculty Management';

$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Add faculty
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) 
{
    verifyCsrf();
    $name=$_POST['name']; $email=$_POST['email'];
    $pass=password_hash($_POST['password'],PASSWORD_DEFAULT);
    $empId=$_POST['employee_id']; $dept=(int)$_POST['department_id'];
    $desig=$_POST['designation']; $qual=$_POST['qualification'];
    $join=$_POST['joining_date'];
    $conn->begin_transaction();
    try 
    {
        $s=$conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'faculty')");
        $s->bind_param('sss',$name,$email,$pass); $s->execute();
        $uid=$conn->insert_id; $s->close();
        $s=$conn->prepare("INSERT INTO faculty (user_id,employee_id,department_id,designation,qualification,joining_date) VALUES (?,?,?,?,?,?)");
        $s->bind_param('iissss',$uid,$empId,$dept,$desig,$qual,$join); $s->execute(); $s->close();
        $conn->commit(); setFlash('success','Faculty added!');
    } catch(Exception $e){ $conn->rollback(); setFlash('error',$e->getMessage()); }
    header('Location: faculty_management.php'); exit();
}

if (isset($_GET['delete'])) 
{
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=(SELECT user_id FROM faculty WHERE id=$id)");
    setFlash('success','Faculty deleted.'); header('Location: faculty_management.php'); exit();
}

$search=clean($_GET['search']??'');
$where=$search?"WHERE u.name LIKE '%$search%' OR f.employee_id LIKE '%$search%' OR u.email LIKE '%$search%'":'';
$list=$conn->query("SELECT f.*,u.name,u.email,u.is_active,d.name dept_name FROM faculty f JOIN users u ON u.id=f.user_id JOIN departments d ON d.id=f.department_id $where ORDER BY f.id DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus text-warning me-2"></i>Add Faculty</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="add" value="1">
          <div class="mb-2"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required value="password123"></div>
          <div class="mb-2"><label class="form-label">Employee ID</label><input type="text" name="employee_id" class="form-control" required placeholder="FAC003"></div>
          <div class="mb-2"><label class="form-label">Department</label>
            <select name="department_id" class="form-select" required><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=$d['name']?></option><?php endforeach;?></select>
          </div>
          <div class="mb-2"><label class="form-label">Designation</label>
            <select name="designation" class="form-select"><option>Assistant Professor</option><option>Associate Professor</option><option>Professor</option><option>HOD</option><option>Lecturer</option></select>
          </div>
          <div class="mb-2"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control" placeholder="M.Tech / PhD"></div>
          <div class="mb-3"><label class="form-label">Joining Date</label><input type="date" name="joining_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
          <button class="btn btn-warning w-100 fw-semibold">Add Faculty</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <form method="GET" class="d-flex gap-2">
          <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name/email/ID..." value="<?=$search?>">
          <button class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Employee ID</th><th>Dept</th><th>Designation</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($list as $i=>$f): ?>
              <tr>
                <td><?=$i+1?></td>
                <td><div style="font-size:.88rem;"><?=htmlspecialchars($f['name'])?></div><div style="font-size:.74rem;color:#888;"><?=$f['email']?></div></td>
                <td><code><?=$f['employee_id']?></code></td>
                <td style="font-size:.82rem;"><?=htmlspecialchars($f['dept_name'])?></td>
                <td style="font-size:.82rem;"><?=$f['designation']?></td>
                <td><span class="badge bg-<?=$f['is_active']?'success':'secondary'?>"><?=$f['is_active']?'Active':'Inactive'?></span></td>
                <td><a href="?delete=<?=$f['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?><tr><td colspan="7" class="text-center text-muted py-3">No faculty found</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
