<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
requireRole('faculty');
$u=currentUser(); 
$facultyId=$u['ref_id'];
$pageTitle='Timetable';

$days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$rows=$conn->query("SELECT t.*,c.name cname,c.code,c.type FROM timetable t JOIN courses c ON c.id=t.course_id WHERE t.faculty_id=$facultyId AND t.academic_year='".ACADEMIC_YEAR."' ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),t.start_time")->fetch_all(MYSQLI_ASSOC);
$byDay=[]; 
foreach($rows as $r) $byDay[$r['day_of_week']][]=$r;
$today=date('l');

include '../includes/header.php'; 
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<div class="row g-3">
  <?php foreach($days as $day): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card <?=$day===$today?'border-success':''?>">
        <div class="card-header d-flex align-items-center gap-2" style="<?=$day===$today?'background:#E1F5EE;':''?>">
          <i class="bi bi-calendar2 <?=$day===$today?'text-success':'text-muted'?>"></i>
          <strong><?=$day?></strong>
          <?php if($day===$today): ?><span class="badge bg-success ms-auto">Today</span><?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if(empty($byDay[$day])): ?>
            <div class="p-3 text-center text-muted" style="font-size:.85rem;"><i class="bi bi-moon me-1"></i>No classes</div>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach($byDay[$day] as $cls): ?>
                <li class="list-group-item py-2 px-3">
                  <div class="d-flex align-items-center gap-2">
                    <div class="text-center px-2 py-1 rounded flex-shrink-0" style="background:<?=$cls['type']==='Practical'?'#FFF3CD':'#E1F5EE'?>;min-width:52px;font-size:.72rem;">
                      <?=substr($cls['start_time'],0,5)?><br>–<?=substr($cls['end_time'],0,5)?>
                    </div>
                    <div>
                      <div class="fw-semibold" style="font-size:.86rem;"><?=htmlspecialchars($cls['cname'])?></div>
                      <div style="font-size:.74rem;color:#888;"><?=$cls['code']?> <?php if($cls['room']): ?>&bull; <?=$cls['room']?><?php endif; ?></div>
                    </div>
                    <?php if($day===$today): ?>
                      <a href="attendance.php?course=<?=$cls['course_id']?>&date=<?=date('Y-m-d')?>" class="btn btn-xs btn-sm btn-outline-success ms-auto" style="font-size:.72rem;padding:2px 8px;">Mark</a>
                    <?php 
                        endif; 
                    ?>
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
  <?php 
    endforeach; 
  ?>
</div>
</div></div></div>
<?php 
  include '../includes/footer.php'; 
?>
