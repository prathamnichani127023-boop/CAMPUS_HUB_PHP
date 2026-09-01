<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('student');
$u = currentUser(); $studentId=$u['ref_id'];
$pageTitle = 'Achievements';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) {
    verifyCsrf();
    $title=clean($_POST['title']); $type=clean($_POST['type']);
    $issuedBy=clean($_POST['issued_by']); $date=clean($_POST['issued_date']);
    $filePath=null;
    if (!empty($_FILES['file']['name'])) $filePath=uploadFile($_FILES['file'],'achievements');
    $stmt=$conn->prepare("INSERT INTO achievements (student_id,title,type,issued_by,issued_date,file_path) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssss',$studentId,$title,$type,$issuedBy,$date,$filePath); $stmt->execute(); $stmt->close();
    setFlash('success','Achievement added!'); header('Location: achievements.php'); exit();
}
if (isset($_GET['delete'])) { $conn->query("DELETE FROM achievements WHERE id=".(int)$_GET['delete']." AND student_id=$studentId"); header('Location: achievements.php'); exit(); }

$achievements=$conn->query("SELECT * FROM achievements WHERE student_id=$studentId ORDER BY issued_date DESC")->fetch_all(MYSQLI_ASSOC);

$typeIcons=['Certificate'=>'bi-award','Award'=>'bi-trophy','Badge'=>'bi-patch-check','Competition'=>'bi-lightning'];
$typeBg=['Certificate'=>'#EEEDFE','Award'=>'#FFF3CD','Badge'=>'#E1F5EE','Competition'=>'#FEE2E2'];
$typeColor=['Certificate'=>'#3C3489','Award'=>'#856404','Badge'=>'#085041','Competition'=>'#991B1B'];

include '../includes/header.php'; 
include '../includes/sidebar_student.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle text-primary me-2"></i>Add Achievement</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php csrfField(); ?><input type="hidden" name="add" value="1">
          <div class="mb-2"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required placeholder="e.g. Python Certification"></div>
          <div class="mb-2"><label class="form-label">Type</label>
            <select name="type" class="form-select"><option>Certificate</option><option>Award</option><option>Badge</option><option>Competition</option></select>
          </div>
          <div class="mb-2"><label class="form-label">Issued By</label><input type="text" name="issued_by" class="form-control" placeholder="Google, Coursera, NPTEL..."></div>
          <div class="mb-2"><label class="form-label">Issue Date</label><input type="date" name="issued_date" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Upload Certificate (PDF/Image)</label><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
          <button class="btn btn-primary w-100">Add Achievement</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if(empty($achievements)): ?>
      <div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-trophy fs-1 d-block mb-3"></i>No achievements yet. Add your first one!</div></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach($achievements as $a):
          $icon=$typeIcons[$a['type']]??'bi-award';
          $bg=$typeBg[$a['type']]??'#F5F5F5';
          $color=$typeColor[$a['type']]??'#333';
        ?>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                  <div class="rounded p-2 flex-shrink-0" style="background:<?=$bg?>;">
                    <i class="bi <?=$icon?> fs-4" style="color:<?=$color?>;"></i>
                  </div>
                  <div class="flex-1">
                    <div class="fw-semibold"><?=htmlspecialchars($a['title'])?></div>
                    <div style="font-size:.8rem;color:#888;"><?=htmlspecialchars($a['issued_by']??'')?></div>
                    <div style="font-size:.78rem;color:#aaa;"><?=fmtDate($a['issued_date']??'')?></div>
                    <span class="badge mt-1" style="background:<?=$bg?>;color:<?=$color?>;"><?=$a['type']?></span>
                  </div>
                  <div class="d-flex flex-column gap-1">
                    <?php if($a['file_path']): ?>
                      <a href="<?=BASE_URL?>/assets/uploads/achievements/<?=$a['file_path']?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    <?php endif; ?>
                    <a href="?delete=<?=$a['id']?>" onclick="return confirm('Delete?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
