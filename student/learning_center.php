<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('student');
$u=currentUser(); $studentId=$u['ref_id'];
$pageTitle='Learning Center';

$filterType=clean($_GET['type']??'');
$filterCourse=(int)($_GET['course']??0);

// My enrolled courses
$myCourses=$conn->query("SELECT c.id,c.name,c.code FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=$studentId AND e.academic_year='".ACADEMIC_YEAR."'")->fetch_all(MYSQLI_ASSOC);
$courseIds=array_column($myCourses,'id');
$inClause=implode(',',$courseIds?:[-1]);

$where="WHERE r.course_id IN ($inClause)";
if ($filterType) $where.=" AND r.type='$filterType'";
if ($filterCourse) $where.=" AND r.course_id=$filterCourse";

$resources=$conn->query("SELECT r.*,c.name cname,c.code,u.name faculty_name FROM resources r JOIN courses c ON c.id=r.course_id JOIN faculty f ON f.id=r.faculty_id JOIN users u ON u.id=f.user_id $where ORDER BY r.uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);

$typeIcon=['Notes'=>'bi-file-text','E-Book'=>'bi-book','Video'=>'bi-play-circle','Other'=>'bi-paperclip'];
$typeBadge=['Notes'=>'primary','E-Book'=>'success','Video'=>'danger','Other'=>'secondary'];
$typeCount=['Notes'=>0,'E-Book'=>0,'Video'=>0,'Other'=>0];
foreach($resources as $r) $typeCount[$r['type']]=(($typeCount[$r['type']]??0)+1);

include '../includes/header.php'; include '../includes/sidebar_student.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php foreach($typeCount as $type=>$cnt): $ic=$typeIcon[$type]??'bi-file'; $bd=$typeBadge[$type]??'secondary'; ?>
    <div class="col-6 col-md-3">
      <div class="card text-center py-3">
        <i class="bi <?=$ic?> fs-2 text-<?=$bd?> mb-1"></i>
        <div class="fw-bold fs-4"><?=$cnt?></div>
        <div style="font-size:.8rem;color:#888;"><?=$type?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <a href="learning_center.php" class="btn btn-sm <?=!$filterType&&!$filterCourse?'btn-primary':'btn-outline-secondary'?>">All</a>
  <?php foreach(['Notes','E-Book','Video','Other'] as $t): ?>
    <a href="?type=<?=$t?>" class="btn btn-sm <?=$filterType===$t?'btn-primary':'btn-outline-secondary'?>"><?=$t?></a>
  <?php endforeach; ?>
  <?php foreach($myCourses as $c): ?>
    <a href="?course=<?=$c['id']?>" class="btn btn-sm <?=$filterCourse==$c['id']?'btn-dark':'btn-outline-secondary'?>"><?=$c['code']?></a>
  <?php endforeach; ?>
</div>

<!-- Resources -->
<?php if(empty($resources)): ?>
  <div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-folder2-open fs-1 d-block mb-2"></i>No study materials available yet.<br><span style="font-size:.85rem;">Faculty will upload materials for your enrolled courses.</span></div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach($resources as $r):
      $icon=$typeIcon[$r['type']]??'bi-paperclip';
      $badge=$typeBadge[$r['type']]??'secondary';
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex gap-3 align-items-start mb-2">
              <div class="rounded p-2 flex-shrink-0" style="background:#EEF2FF;">
                <i class="bi <?=$icon?> fs-4 text-primary"></i>
              </div>
              <div class="flex-1">
                <div class="fw-semibold" style="font-size:.9rem;"><?=htmlspecialchars($r['title'])?></div>
                <div style="font-size:.76rem;color:#888;"><?=htmlspecialchars($r['cname'])?> &bull; <?=htmlspecialchars($r['faculty_name'])?></div>
                <span class="badge bg-<?=$badge?> mt-1"><?=$r['type']?></span>
              </div>
            </div>
            <?php if($r['description']): ?><p style="font-size:.8rem;color:#666;" class="mb-2"><?=htmlspecialchars($r['description'])?></p><?php endif; ?>
            <div style="font-size:.74rem;color:#aaa;" class="mb-3"><?=fmtDate($r['uploaded_at'])?></div>
            <div class="d-flex gap-2">
              <?php if($r['file_path']): ?><a href="<?=BASE_URL?>/assets/uploads/resources/<?=$r['file_path']?>" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i>Download</a><?php endif; ?>
              <?php if($r['url']): ?><a href="<?=htmlspecialchars($r['url'])?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-play-circle me-1"></i>Open</a><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div></div></div>
<?php include '../includes/footer.php'; ?>
