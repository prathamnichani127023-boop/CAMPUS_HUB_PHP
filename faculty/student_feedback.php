<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
requireRole('faculty');
$u=currentUser(); 
$facultyId=$u['ref_id'];
$pageTitle='Student Feedback';

$feedback=$conn->query("SELECT fb.*,u.name sname,s.enrollment_no,c.name cname,c.code FROM feedback fb JOIN students s ON s.id=fb.student_id JOIN users u ON u.id=s.user_id JOIN courses c ON c.id=fb.course_id WHERE fb.faculty_id=$facultyId ORDER BY fb.submitted_at DESC")->fetch_all(MYSQLI_ASSOC);

// Per course averages
$courseAvg=$conn->query("SELECT c.name,c.code,COUNT(fb.id) total,AVG(fb.rating) avg_rating FROM feedback fb JOIN courses c ON c.id=fb.course_id WHERE fb.faculty_id=$facultyId GROUP BY c.id")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">

<?php if(!empty($courseAvg)): ?>
<div class="row g-3 mb-4">
  <?php foreach($courseAvg as $ca): ?>
    <div class="col-md-4">
      <div class="card text-center">
        <div class="card-body py-3">
          <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?=round($ca['avg_rating'],1)?><span style="font-size:1rem;color:#aaa;">/5</span></div>
          <div class="text-warning mb-1"><?=str_repeat('★',round($ca['avg_rating']))?><?=str_repeat('☆',5-round($ca['avg_rating']))?></div>
          <div style="font-size:.85rem;" class="fw-semibold"><?=htmlspecialchars($ca['name'])?></div>
          <div style="font-size:.76rem;color:#aaa;"><?=$ca['total']?> reviews</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-star text-success me-2"></i>All Feedback Received</div>
  <div class="card-body p-0">
    <?php if(empty($feedback)): ?>
      <div class="p-5 text-center text-muted"><i class="bi bi-chat-heart fs-1 d-block mb-2"></i>No feedback yet.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach($feedback as $fb): ?>
          <li class="list-group-item py-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <strong style="font-size:.88rem;"><?=htmlspecialchars($fb['sname'])?></strong>
                  <span class="text-muted" style="font-size:.76rem;">[<?=$fb['enrollment_no']?>]</span>
                  <span class="badge bg-secondary"><?=$fb['code']?></span>
                </div>
                <div class="text-warning mb-1" style="font-size:1.1rem;"><?=str_repeat('★',$fb['rating'])?><?=str_repeat('☆',5-$fb['rating'])?></div>
                <?php if($fb['comment']): ?><div style="font-size:.84rem;color:#555;"><?=htmlspecialchars($fb['comment'])?></div><?php endif; ?>
              </div>
              <div style="font-size:.74rem;color:#aaa;white-space:nowrap;"><?=fmtDate($fb['submitted_at'])?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

</div></div></div>
<?php include '../includes/footer.php'; ?>
