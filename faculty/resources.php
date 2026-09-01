<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('faculty');
$u=currentUser(); $facultyId=$u['ref_id'];
$pageTitle='Resources';

$myCourses=$conn->query("SELECT c.id,c.name,c.code FROM class_assignments ca JOIN courses c ON c.id=ca.course_id WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload'])) 
{
    verifyCsrf();
    $cid=(int)$_POST['course_id']; 
    $title=clean($_POST['title']);
    $type=clean($_POST['type']); 
    $desc=clean($_POST['description']); 
    $url=clean($_POST['url']);
    $filePath=null;
    if (!empty($_FILES['file']['name'])) $filePath=uploadFile($_FILES['file'],'resources');
    $stmt=$conn->prepare("INSERT INTO resources (course_id,faculty_id,title,type,file_path,url,description) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssss',$cid,$facultyId,$title,$type,$filePath,$url,$desc);
    $stmt->execute(); 
    $stmt->close();
    setFlash('success','Resource uploaded!'); 
    header('Location: resources.php'); 
    exit();
}
if (isset($_GET['delete'])) 
{ 
  $conn->query("DELETE FROM resources WHERE id=".(int)$_GET['delete']." AND faculty_id=$facultyId"); 
  setFlash('success','Deleted.'); 
  header('Location: resources.php'); 
  exit(); 
}

$filterCourse=(int)($_GET['course']??0);
$where=$filterCourse?"WHERE r.course_id=$filterCourse AND r.faculty_id=$facultyId":"WHERE r.faculty_id=$facultyId";
$resources=$conn->query("SELECT r.*,c.name cname,c.code FROM resources r JOIN courses c ON c.id=r.course_id $where ORDER BY r.uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);

$typeIcon=['Notes'=>'bi-file-text','E-Book'=>'bi-book','Video'=>'bi-play-circle','Other'=>'bi-paperclip'];
$typeBadge=['Notes'=>'primary','E-Book'=>'success','Video'=>'danger','Other'=>'secondary'];

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-cloud-upload text-success me-2"></i>Upload Resource</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php csrfField(); ?><input type="hidden" name="upload" value="1">
          <div class="mb-2"><label class="form-label">Course</label>
            <select name="course_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach($myCourses as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['code'].' — '.$c['name'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required placeholder="Resource title"></div>
          <div class="mb-2"><label class="form-label">Type</label>
            <select name="type" class="form-select"><option>Notes</option><option>E-Book</option><option>Video</option><option>Other</option></select>
          </div>
          <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea></div>
          <div class="mb-2"><label class="form-label">File Upload</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip"></div>
          <div class="mb-3"><label class="form-label">OR Link/URL</label><input type="url" name="url" class="form-control" placeholder="https://youtube.com/..."></div>
          <button class="btn btn-success w-100"><i class="bi bi-cloud-upload me-1"></i>Upload</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <!-- Filter -->
    <div class="mb-3 d-flex gap-2 flex-wrap">
      <a href="resources.php" class="btn btn-sm <?=$filterCourse?'btn-outline-secondary':'btn-primary'?>">All Courses</a>
      <?php foreach($myCourses as $c): ?>
        <a href="?course=<?=$c['id']?>" class="btn btn-sm <?=$filterCourse==$c['id']?'btn-primary':'btn-outline-secondary'?>"><?=$c['code']?></a>
      <?php endforeach; ?>
    </div>
    <?php if(empty($resources)): ?>
      <div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2"></i>No resources uploaded yet.</div></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach($resources as $r):
          $icon=$typeIcon[$r['type']]??'bi-paperclip';
          $badge=$typeBadge[$r['type']]??'secondary';
        ?>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex gap-3 align-items-start">
                  <div class="rounded p-2 flex-shrink-0" style="background:#E1F5EE;">
                    <i class="bi <?=$icon?> fs-4 text-success"></i>
                  </div>
                  <div class="flex-1">
                    <div class="fw-semibold" style="font-size:.9rem;"><?=htmlspecialchars($r['title'])?></div>
                    <div style="font-size:.76rem;color:#888;"><?=htmlspecialchars($r['cname'])?> &bull; <?=fmtDate($r['uploaded_at'])?></div>
                    <?php if($r['description']): ?><div style="font-size:.8rem;color:#666;margin:4px 0;"><?=htmlspecialchars($r['description'])?></div><?php endif; ?>
                    <span class="badge bg-<?=$badge?> mt-1"><?=$r['type']?></span>
                  </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                  <?php if($r['file_path']): ?>
                    <a href="<?=BASE_URL?>/assets/uploads/resources/<?=$r['file_path']?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Download</a>
                  <?php endif; ?>
                  <?php if($r['url']): ?>
                    <a href="<?=htmlspecialchars($r['url'])?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-link-45deg me-1"></i>Open Link</a>
                  <?php endif; ?>
                  <a href="?delete=<?=$r['id']?>" onclick="return confirm('Delete resource?')" class="btn btn-sm btn-outline-danger ms-auto"><i class="bi bi-trash"></i></a>
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
