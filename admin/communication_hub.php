<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$u = currentUser();
$pageTitle = 'Communication Hub';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['announce'])) 
{
    verifyCsrf();
    $title=clean($_POST['title']); $body=clean($_POST['body']); $target=clean($_POST['target_role']);
    $stmt=$conn->prepare("INSERT INTO announcements (posted_by,title,body,target_role) VALUES (?,?,?,?)");
    $stmt->bind_param('isss',$u['id'],$title,$body,$target); $stmt->execute(); $stmt->close();
    setFlash('success','Announcement posted!'); header('Location: communication_hub.php'); exit();
}

if (isset($_GET['toggle'])) 
{
    $id=(int)$_GET['toggle'];
    $conn->query("UPDATE announcements SET is_active = NOT is_active WHERE id=$id");
    header('Location: communication_hub.php'); exit();
}
if (isset($_GET['delete'])) 
{
    $conn->query("DELETE FROM announcements WHERE id=".(int)$_GET['delete']);
    setFlash('success','Deleted.'); header('Location: communication_hub.php'); exit();
}

$announcements=$conn->query("SELECT a.*,u.name pname FROM announcements a JOIN users u ON u.id=a.posted_by ORDER BY a.posted_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-megaphone text-warning me-2"></i>Post Announcement</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="announce" value="1">
          <div class="mb-2"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required placeholder="Announcement title"></div>
          <div class="mb-2"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="5" required placeholder="Write your announcement..."></textarea></div>
          <div class="mb-3"><label class="form-label">Target</label>
            <select name="target_role" class="form-select">
              <option value="all">Everyone (Students + Faculty)</option>
              <option value="student">Students Only</option>
              <option value="faculty">Faculty Only</option>
            </select>
          </div>
          <button class="btn btn-warning w-100 fw-semibold"><i class="bi bi-send me-1"></i>Post Announcement</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul text-warning me-2"></i>All Announcements</div>
      <div class="card-body p-0">
        <?php if(empty($announcements)): ?>
          <div class="p-4 text-center text-muted">No announcements yet.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach($announcements as $a): ?>
              <li class="list-group-item py-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-1 me-3">
                    <div class="fw-semibold"><?=htmlspecialchars($a['title'])?></div>
                    <div style="font-size:.82rem;color:#555;margin:4px 0;"><?=nl2br(htmlspecialchars(substr($a['body'],0,120)))?><?=strlen($a['body'])>120?'...':''?></div>
                    <div style="font-size:.74rem;color:#888;">
                      By <?=htmlspecialchars($a['pname'])?> &bull; <?=fmtDateTime($a['posted_at'])?>
                      &bull; <span class="badge bg-secondary"><?=$a['target_role']?></span>
                    </div>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0">
                    <a href="?toggle=<?=$a['id']?>" class="btn btn-sm btn-outline-<?=$a['is_active']?'success':'secondary'?>" title="<?=$a['is_active']?'Active':'Inactive'?>">
                      <i class="bi bi-<?=$a['is_active']?'eye':'eye-slash'?>"></i>
                    </a>
                    <a href="?delete=<?=$a['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
