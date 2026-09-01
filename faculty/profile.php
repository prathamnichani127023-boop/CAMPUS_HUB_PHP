<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('faculty');
$u=currentUser(); 
$facultyId=$u['ref_id'];
$pageTitle='Profile & Settings';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update'])) 
{
    verifyCsrf();
    $name=clean($_POST['name']); $phone=clean($_POST['phone']);
    $desig=clean($_POST['designation']); $qual=clean($_POST['qualification']);
    $spec=clean($_POST['specialization']);
    $photo=null;
    if (!empty($_FILES['photo']['name'])) 
    {
        $photo=uploadFile($_FILES['photo'],'avatars');
        if ($photo) 
        { 
          $conn->query("UPDATE users SET profile_photo='$photo' WHERE id={$u['id']}"); 
          $_SESSION['photo']=$photo; 
        }
    }
    $conn->query("UPDATE users SET name='$name',phone='$phone' WHERE id={$u['id']}");
    $conn->query("UPDATE faculty SET designation='$desig',qualification='$qual',specialization='$spec' WHERE id=$facultyId");
    $_SESSION['name']=$name;
    setFlash('success','Profile updated!'); 
    header('Location: profile.php'); 
    exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])) 
{
    verifyCsrf();
    $old=$_POST['old_password']; 
    $new=$_POST['new_password']; 
    $confirm=$_POST['confirm_password'];
    $r=$conn->query("SELECT password FROM users WHERE id={$u['id']} LIMIT 1");
    $dbPass=$r->fetch_assoc()['password'];
    if (!password_verify($old,$dbPass)) setFlash('error','Current password wrong.');
    elseif ($new!==$confirm) setFlash('error','Passwords do not match.');
    elseif (strlen($new)<6) setFlash('error','Min 6 characters.');
    else 
    { 
      $hash=password_hash($new,PASSWORD_DEFAULT); 
      $conn->query("UPDATE users SET password='$hash' WHERE id={$u['id']}"); 
      setFlash('success','Password changed!'); 
    }
    header('Location: profile.php'); 
    exit();
}

$profile=$conn->query("SELECT f.*,u.name,u.email,u.phone,u.profile_photo,d.name dept_name FROM faculty f JOIN users u ON u.id=f.user_id JOIN departments d ON d.id=f.department_id WHERE f.id=$facultyId LIMIT 1")->fetch_assoc();

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card text-center mb-3">
      <div class="card-body py-4">
        <img src="<?=$profile['profile_photo']==='default.png' ? BASE_URL.'/assets/img/default.png' : UPLOAD_URL.'avatars/'.$profile['profile_photo']?>" class="rounded-circle mb-3" width="90" height="90" style="object-fit:cover;border:3px solid #28A745;" onerror="this.src='<?=BASE_URL?>/assets/img/default.png'">
        <h5 class="mb-0"><?=htmlspecialchars($profile['name'])?></h5>
        <div class="text-muted" style="font-size:.82rem;"><?=$profile['designation']?></div>
        <div class="text-muted" style="font-size:.8rem;"><?=htmlspecialchars($profile['dept_name'])?></div>
        <div class="badge bg-light text-dark mt-2"><?=$profile['employee_id']?></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-lock text-success me-2"></i>Change Password</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="change_password" value="1">
          <div class="mb-2"><label class="form-label">Current Password</label><input type="password" name="old_password" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
          <div class="mb-3"><label class="form-label">Confirm New</label><input type="password" name="confirm_password" class="form-control" required></div>
          <button class="btn btn-success w-100">Change Password</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-gear text-success me-2"></i>Edit Profile</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php csrfField(); ?><input type="hidden" name="update" value="1">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?=htmlspecialchars($profile['name'])?>" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?=htmlspecialchars($profile['email'])?>" readonly></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?=htmlspecialchars($profile['phone']??'')?>"></div>
            <div class="col-md-6"><label class="form-label">Designation</label>
              <select name="designation" class="form-select">
                <?php foreach(['Assistant Professor','Associate Professor','Professor','HOD','Lecturer'] as $d): ?><option <?=$profile['designation']===$d?'selected':''?>><?=$d?></option><?php endforeach;?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control" value="<?=htmlspecialchars($profile['qualification']??'')?>"></div>
            <div class="col-md-6"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control" value="<?=htmlspecialchars($profile['specialization']??'')?>"></div>
            <div class="col-12"><label class="form-label">Profile Photo</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png"></div>
            <div class="col-12"><button class="btn btn-success"><i class="bi bi-save me-1"></i>Save Changes</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
