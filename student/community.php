<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('student');
$u=currentUser(); $pageTitle='Community';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_post'])) 
{
    verifyCsrf();
    $title=clean($_POST['title']); 
    $body=clean($_POST['body']); 
    $cat=clean($_POST['category']);
    $stmt=$conn->prepare("INSERT INTO forum_posts (user_id,title,body,category) VALUES (?,?,?,?)");
    $stmt->bind_param('isss',$u['id'],$title,$body,$cat); 
    $stmt->execute(); 
    $stmt->close();
    setFlash('success','Post created!'); 
    header('Location: community.php'); 
    exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reply'])) 
  {
    verifyCsrf();
    $postId=(int)$_POST['post_id']; 
    $body=clean($_POST['body']);
    $stmt=$conn->prepare("INSERT INTO forum_replies (post_id,user_id,body) VALUES (?,?,?)");
    $stmt->bind_param('iis',$postId,$u['id'],$body); 
    $stmt->execute(); 
    $stmt->close();
    header('Location: community.php?view='.$postId); 
    exit();
}

$viewId=(int)($_GET['view']??0);
$viewPost=null; 
$replies=[];
if ($viewId) 
{
    $r=$conn->query("SELECT p.*,u.name author FROM forum_posts p JOIN users u ON u.id=p.user_id WHERE p.id=$viewId LIMIT 1");
    $viewPost=$r->fetch_assoc();
    $replies=$conn->query("SELECT r.*,u.name author,u.role FROM forum_replies r JOIN users u ON u.id=r.user_id WHERE r.post_id=$viewId ORDER BY r.created_at")->fetch_all(MYSQLI_ASSOC);
}
$posts=$conn->query("SELECT p.*,u.name author,(SELECT COUNT(*) FROM forum_replies r WHERE r.post_id=p.id) replies FROM forum_posts p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_student.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-plus-circle text-primary me-2"></i>New Post</div>
      <div class="card-body">
        <form method="POST">
          <?php 
            csrfField(); 
          ?>
          <input type="hidden" name="new_post" value="1">
          <div class="mb-2">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required placeholder="What's on your mind?"></div>
          <div class="mb-2"><label class="form-label">Category</label>
            <select name="category" class="form-select"><option>General</option><option>Academics</option><option>Career</option><option>Events</option><option>Help</option><option>Other</option></select>
          </div>
          <div class="mb-3"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="4" required></textarea></div>
          <button class="btn btn-primary w-100">Post</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if($viewPost): ?>
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-chat-quote text-primary"></i><?=htmlspecialchars($viewPost['title'])?>
          <a href="community.php" class="btn btn-sm btn-outline-secondary ms-auto">Back</a>
        </div>
        <div class="card-body">
          <div class="mb-1" style="font-size:.8rem;color:#888;">By <strong><?=htmlspecialchars($viewPost['author'])?></strong> &bull; <?=fmtDateTime($viewPost['created_at'])?> &bull; <span class="badge bg-secondary"><?=$viewPost['category']?></span></div>
          <div style="line-height:1.7;margin:12px 0;"><?=nl2br(htmlspecialchars($viewPost['body']))?></div>
          <hr>
          <div class="mb-3"><strong><?=count($replies)?> Replies</strong></div>
          <?php foreach($replies as $r): ?>
            <div class="p-3 mb-2 rounded" style="background:#F5F7FB;">
              <div style="font-size:.78rem;color:#888;" class="mb-1"><strong><?=htmlspecialchars($r['author'])?></strong> [<?=$r['role']?>] &bull; <?=fmtDateTime($r['created_at'])?></div>
              <div style="font-size:.88rem;"><?=nl2br(htmlspecialchars($r['body']))?></div>
            </div>
          <?php endforeach; ?>
          <form method="POST" class="mt-3">
            <?php csrfField(); ?><input type="hidden" name="reply" value="1"><input type="hidden" name="post_id" value="<?=$viewId?>">
            <div class="d-flex gap-2">
              <textarea name="body" class="form-control" rows="2" placeholder="Write a reply..." required></textarea>
              <button class="btn btn-primary align-self-end">Reply</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-people text-primary me-2"></i>Discussion Forum</div>
      <div class="card-body p-0">
        <?php if(empty($posts)): ?>
          <div class="p-4 text-center text-muted">No posts yet. Start a discussion!</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach($posts as $p): ?>
              <li class="list-group-item py-3">
                <div class="d-flex gap-3">
                  <div class="flex-1">
                    <a href="?view=<?=$p['id']?>" class="fw-semibold text-decoration-none" style="font-size:.9rem;"><?=htmlspecialchars($p['title'])?></a>
                    <div style="font-size:.78rem;color:#888;margin-top:2px;">
                      By <?=htmlspecialchars($p['author'])?> &bull; <?=fmtDate($p['created_at'])?>
                      &bull; <span class="badge bg-secondary"><?=$p['category']?></span>
                    </div>
                  </div>
                  <div class="text-center" style="min-width:50px;">
                    <div class="fw-bold text-primary"><?=$p['replies']?></div>
                    <div style="font-size:.72rem;color:#aaa;">replies</div>
                  </div>
                </div>
              </li>
            <?php 
              endforeach; 
            ?>
          </ul>
        <?php 
          endif; 
        ?>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
