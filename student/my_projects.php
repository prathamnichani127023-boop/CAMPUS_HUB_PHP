<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('student');
$u=currentUser(); $studentId=$u['ref_id'];
$pageTitle='My Projects';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) 
  {
    verifyCsrf();
    $title=clean($_POST['title']); 
    $desc=clean($_POST['description']);
    $status=clean($_POST['status']); 
    $github=clean($_POST['github_url']);
    
    $filePath=null;
    if (!empty($_FILES['file']['name'])) $filePath=uploadFile($_FILES['file'],'projects');
    $stmt=$conn->prepare("INSERT INTO projects (student_id,title,description,status,github_url,file_path) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssss',$studentId,$title,$desc,$status,$github,$filePath); $stmt->execute(); $stmt->close();
    setFlash('success','Project added!'); header('Location: my_projects.php'); exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update'])) 
{
    verifyCsrf();
    $id=(int)$_POST['project_id']; $status=clean($_POST['status']);
    $conn->query("UPDATE projects SET status='$status',updated_at=NOW() WHERE id=$id AND student_id=$studentId");
    setFlash('success','Status updated!'); header('Location: my_projects.php'); exit();
}
if (isset($_GET['delete'])) 
  { 
    $conn->query("DELETE FROM projects WHERE id=".(int)$_GET['delete']." AND student_id=$studentId"); 
    header('Location: my_projects.php'); 
    exit(); 
  }

$projects=$conn->query("SELECT * FROM projects WHERE student_id=$studentId ORDER BY updated_at DESC")->fetch_all(MYSQLI_ASSOC);
$statusColor=['Pending'=>'warning text-dark','In Progress'=>'primary','Completed'=>'success'];

include '../includes/header.php'; include '../includes/sidebar_student.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle text-primary me-2"></i>Add Project</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php csrfField(); ?><input type="hidden" name="add" value="1">
          <div class="mb-2"><label class="form-label">Project Title</label><input type="text" name="title" class="form-control" required placeholder="My Awesome Project"></div>
          <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" placeholder="What is this project about?"></textarea></div>
          <div class="mb-2"><label class="form-label">Status</label>
            <select name="status" class="form-select"><option>In Progress</option><option>Pending</option><option>Completed</option></select>
          </div>
          <div class="mb-2"><label class="form-label">GitHub URL</label><input type="url" name="github_url" class="form-control" placeholder="https://github.com/..."></div>
          <div class="mb-3"><label class="form-label">Upload Document (optional)</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.zip"></div>
          <button class="btn btn-primary w-100">Add Project</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if(empty($projects)): ?>
      <div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-kanban fs-1 d-block mb-3"></i>No projects yet. Start your first project!</div></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach($projects as $p): $sc=$statusColor[$p['status']]??'secondary'; ?>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="mb-0 fw-bold"><?=htmlspecialchars($p['title'])?></h6>
                  <span class="badge bg-<?=$sc?>"><?=$p['status']?></span>
                </div>
                <?php if($p['description']): ?>
                  <p style="font-size:.83rem;color:#666;" class="mb-2"><?=htmlspecialchars($p['description'])?></p>
                <?php endif; ?>
                <div style="font-size:.76rem;color:#aaa;" class="mb-3">
                  Updated: <?=fmtDate($p['updated_at'])?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <?php if($p['github_url']): ?>
                    <a href="<?=htmlspecialchars($p['github_url'])?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-github me-1"></i>GitHub</a>
                  <?php endif; ?>
                  <?php if($p['file_path']): ?>
                    <a href="<?=BASE_URL?>/assets/uploads/projects/<?=$p['file_path']?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark me-1"></i>Doc</a>
                  <?php 
                      endif; 
                  ?>

                  <!-- Update status -->
                  <form method="POST" class="d-flex gap-1">
                    <?php csrfField(); ?><input type="hidden" name="update" value="1"><input type="hidden" name="project_id" value="<?=$p['id']?>">
                    <select name="status" class="form-select form-select-sm" style="width:120px;"><option <?=$p['status']==='Pending'?'selected':''?>>Pending</option><option <?=$p['status']==='In Progress'?'selected':''?>>In Progress</option><option <?=$p['status']==='Completed'?'selected':''?>>Completed</option></select>
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></button>
                  </form>
                  <a href="?delete=<?=$p['id']?>" onclick="return confirm('Delete project?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>
                </div>
              </div>
            </div>
          </div>
        <?php 
          endforeach; 
        ?>
      </div>
    <?php 
      endif; 
    ?>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
