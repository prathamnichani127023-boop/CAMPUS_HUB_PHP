<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle='System Settings';

// Add department
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_dept'])) 
{
    verifyCsrf();
    $name=clean($_POST['dept_name']); 
    $code=strtoupper(clean($_POST['dept_code']));
    $conn->query("INSERT INTO departments (name,code) VALUES ('$name','$code')");
    setFlash('success','Department added!'); header('Location: system_settings.php'); 
    exit();
}
// Delete dept
if (isset($_GET['del_dept'])) 
{ 
  $conn->query("DELETE FROM departments WHERE id=".(int)$_GET['del_dept']); 
  setFlash('success','Deleted.'); 
  header('Location: system_settings.php'); 
  exit(); 
}

// Toggle user active status
if (isset($_GET['toggle_user'])) 
  { 
    $id=(int)$_GET['toggle_user']; 
    $conn->query("UPDATE users SET is_active=NOT is_active WHERE id=$id AND role!='admin'"); 
    header('Location: system_settings.php'); 
    exit(); 
  }

$departments=$conn->query("SELECT d.*,(SELECT COUNT(*) FROM students s WHERE s.department_id=d.id) students,(SELECT COUNT(*) FROM faculty f WHERE f.department_id=d.id) faculty FROM departments d ORDER BY d.name")->fetch_all(MYSQLI_ASSOC);
$users=$conn->query("SELECT id,name,email,role,is_active,created_at FROM users ORDER BY role,name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#departments"><i class="bi bi-building me-1"></i>Departments</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#users"><i class="bi bi-people me-1"></i>User Management</a></li>
</ul>
<div class="tab-content">

<!-- Departments -->
<div class="tab-pane fade show active" id="departments">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Add Department</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="add_dept" value="1">
            <div class="mb-2"><label class="form-label">Department Name</label><input type="text" name="dept_name" class="form-control" required placeholder="Computer Science"></div>
            <div class="mb-3"><label class="form-label">Code</label><input type="text" name="dept_code" class="form-control" required placeholder="CS" maxlength="10"></div>
            <button class="btn btn-warning w-100">Add Department</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-list-ul text-warning me-2"></i>All Departments</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Students</th><th>Faculty</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($departments as $d): ?>
              <tr><td><?=htmlspecialchars($d['name'])?></td><td><span class="badge bg-primary"><?=$d['code']?></span></td><td><?=$d['students']?></td><td><?=$d['faculty']?></td><td><?php if($d['students']==0&&$d['faculty']==0): ?><a href="?del_dept=<?=$d['id']?>" onclick="return confirm('Delete department?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a><?php else: ?><span class="text-muted" style="font-size:.78rem;">In use</span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Users -->
<div class="tab-pane fade" id="users">
  <div class="card">
    <div class="card-header"><i class="bi bi-people text-warning me-2"></i>All Users</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Status</th><th>Toggle</th></tr></thead>
          <tbody>
          <?php foreach($users as $usr): ?>
            <tr>
              <td style="font-size:.86rem;"><?=htmlspecialchars($usr['name'])?></td>
              <td style="font-size:.82rem;"><?=htmlspecialchars($usr['email'])?></td>
              <td><span class="badge bg-<?=$usr['role']==='admin'?'warning text-dark':($usr['role']==='faculty'?'success':'primary')?>"><?=$usr['role']?></span></td>
              <td style="font-size:.8rem;"><?=fmtDate($usr['created_at'])?></td>
              <td><span class="badge bg-<?=$usr['is_active']?'success':'secondary'?>"><?=$usr['is_active']?'Active':'Inactive'?></span></td>
              <td>
                <?php if($usr['role']!=='admin'): ?>
                  <a href="?toggle_user=<?=$usr['id']?>" class="btn btn-sm btn-outline-<?=$usr['is_active']?'danger':'success'?>"><?=$usr['is_active']?'Deactivate':'Activate'?></a>
                <?php else: ?>
                  <span class="text-muted" style="font-size:.78rem;">Protected</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
