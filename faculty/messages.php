<?php
// faculty/messages.php — same as student but with faculty sidebar
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('faculty');
$u = currentUser();
$pageTitle = 'Messages';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send'])) 
{
    verifyCsrf();
    $to=(int)$_POST['receiver_id']; 
    $subj=clean($_POST['subject']); 
    $body=clean($_POST['body']);
    $stmt=$conn->prepare("INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES (?,?,?,?)");
    $stmt->bind_param('iiss',$u['id'],$to,$subj,$body); $stmt->execute(); 
    $stmt->close();
    setFlash('success','Message sent!'); 
    header('Location: messages.php'); 
    exit();
}
if (isset($_GET['read'])) 
{
    $id=(int)$_GET['read'];
    $conn->query("UPDATE messages SET is_read=1 WHERE id=$id AND receiver_id={$u['id']}");
}
$viewId=(int)($_GET['read']??0); 
$viewMsg=null;
if ($viewId) 
{
    $r=$conn->query("SELECT m.*,u.name sname,u.role srole FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.id=$viewId AND (m.receiver_id={$u['id']} OR m.sender_id={$u['id']}) LIMIT 1");
    $viewMsg=$r->fetch_assoc();
}
$inbox=$conn->query("SELECT m.*,u.name sname,u.role srole FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.receiver_id={$u['id']} ORDER BY m.sent_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$sent=$conn->query("SELECT m.*,u.name rname,u.role rrole FROM messages m JOIN users u ON u.id=m.receiver_id WHERE m.sender_id={$u['id']} ORDER BY m.sent_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$recipients=$conn->query("SELECT id,name,role FROM users WHERE role IN('student','faculty','admin') AND id!={$u['id']} AND is_active=1 ORDER BY role,name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-pencil-square text-success me-2"></i>Compose</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="send" value="1">
          <div class="mb-2"><label class="form-label">To</label>
            <select name="receiver_id" class="form-select" required>
              <option value="">— Select recipient —</option>
              <?php foreach($recipients as $r): ?><option value="<?=$r['id']?>">[<?=ucfirst($r['role'])?>] <?=htmlspecialchars($r['name'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Subject</label><input type="text" name="subject" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="5" required></textarea></div>
          <button class="btn btn-success w-100"><i class="bi bi-send me-1"></i>Send</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if($viewMsg): ?>
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-envelope-open text-success"></i><?=htmlspecialchars($viewMsg['subject']??'(No Subject)')?><a href="messages.php" class="btn btn-sm btn-outline-secondary ms-auto">Back</a></div>
        <div class="card-body">
          <div class="mb-3 p-3 rounded" style="background:#F5F7FB;font-size:.82rem;color:#666;">From: <strong><?=htmlspecialchars($viewMsg['sname'])?></strong> [<?=$viewMsg['srole']?>] &bull; <?=fmtDateTime($viewMsg['sent_at'])?></div>
          <div style="line-height:1.7;"><?=nl2br(htmlspecialchars($viewMsg['body']))?></div>
        </div>
      </div>
    <?php endif; ?>
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#inbox"><i class="bi bi-inbox me-1"></i>Inbox <span class="badge bg-danger"><?=count(array_filter($inbox,fn($m)=>!$m['is_read']))?></span></a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sentbox"><i class="bi bi-send me-1"></i>Sent</a></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="inbox">
        <div class="card"><div class="card-body p-0">
          <?php if(empty($inbox)): ?><div class="p-4 text-center text-muted">Inbox empty.</div>
          <?php else: ?><ul class="list-group list-group-flush">
            <?php foreach($inbox as $m): ?>
              <li class="list-group-item <?=$m['is_read']?'':'fw-bold'?> py-2">
                <a href="?read=<?=$m['id']?>" class="d-flex gap-3 text-decoration-none text-dark">
                  <?php if(!$m['is_read']): ?><span class="text-success" style="font-size:.7rem;">●</span><?php endif; ?>
                  <div class="flex-1"><div style="font-size:.86rem;"><?=htmlspecialchars($m['sname'])?> <span class="text-muted fw-normal">[<?=$m['srole']?>]</span></div><div style="font-size:.82rem;color:#555;"><?=htmlspecialchars($m['subject']??'(No Subject)')?></div></div>
                  <div style="font-size:.74rem;color:#888;"><?=fmtDate($m['sent_at'])?></div>
                </a>
              </li>
            <?php endforeach; ?></ul>
          <?php endif; ?>
        </div></div>
      </div>
      <div class="tab-pane fade" id="sentbox">
        <div class="card"><div class="card-body p-0">
          <?php if(empty($sent)): ?><div class="p-4 text-center text-muted">No sent messages.</div>
          <?php else: ?><ul class="list-group list-group-flush">
            <?php foreach($sent as $m): ?>
              <li class="list-group-item py-2">
                <div class="d-flex gap-3"><div class="flex-1"><div style="font-size:.86rem;">To: <strong><?=htmlspecialchars($m['rname'])?></strong> [<?=$m['rrole']?>]</div><div style="font-size:.82rem;color:#555;"><?=htmlspecialchars($m['subject']??'')?></div></div><div style="font-size:.74rem;color:#888;"><?=fmtDate($m['sent_at'])?></div></div>
              </li>
            <?php endforeach; ?></ul>
          <?php endif; ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
