<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('student');
$u=currentUser(); 
$studentId=$u['ref_id'];
$pageTitle='Career Center';

// Apply for job
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['apply'])) 
{
    verifyCsrf();
    $jobId=(int)$_POST['job_id'];
    $conn->query("INSERT IGNORE INTO job_applications (student_id,job_id) VALUES ($studentId,$jobId)");
    setFlash('success','Application submitted!'); 
    header('Location: career_center.php'); 
    exit();
}

$jobs=$conn->query("SELECT jp.*,c.name company_name,c.industry,
    (SELECT COUNT(*) FROM job_applications a WHERE a.job_id=jp.id) applicants,
    (SELECT id FROM job_applications a WHERE a.job_id=jp.id AND a.student_id=$studentId LIMIT 1) applied
    FROM job_postings jp JOIN companies c ON c.id=jp.company_id
    WHERE jp.status='Open' ORDER BY jp.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$myApps=$conn->query("SELECT a.*,jp.title,jp.type,jp.package,c.name company_name FROM job_applications a JOIN job_postings jp ON jp.id=a.job_id JOIN companies c ON c.id=jp.company_id WHERE a.student_id=$studentId ORDER BY a.applied_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; include '../includes/sidebar_student.php';
?>

<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#jobs"><i class="bi bi-briefcase me-1"></i>Job & Internship Openings</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#myapps"><i class="bi bi-list-check me-1"></i>My Applications <span class="badge bg-primary"><?=count($myApps)?></span></a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="jobs">
    <div class="row g-3">
      <?php foreach($jobs as $job): ?>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <h6 class="mb-0 fw-bold"><?=htmlspecialchars($job['title'])?></h6>
                  <div style="font-size:.82rem;color:#555;"><?=htmlspecialchars($job['company_name'])?> &bull; <?=$job['industry']?></div>
                </div>
                <span class="badge bg-<?=$job['type']==='Job'?'primary':'success'?>"><?=$job['type']?></span>
              </div>
              <?php if($job['description']): ?>
                <p style="font-size:.82rem;color:#666;" class="mb-2"><?=htmlspecialchars(substr($job['description'],0,100))?>...</p>
              <?php endif; ?>
              <div class="d-flex flex-wrap gap-2 mb-3" style="font-size:.78rem;">
                <?php if($job['package']): ?><span class="badge bg-light text-dark"><i class="bi bi-cash me-1"></i><?=$job['package']?></span><?php endif; ?>
                <?php if($job['eligibility']): ?><span class="badge bg-light text-dark"><i class="bi bi-mortarboard me-1"></i><?=htmlspecialchars($job['eligibility'])?></span><?php endif; ?>
                <?php if($job['apply_deadline']): ?><span class="badge bg-light text-dark"><i class="bi bi-calendar me-1"></i>Deadline: <?=fmtDate($job['apply_deadline'])?></span><?php endif; ?>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span style="font-size:.76rem;color:#aaa;"><?=$job['applicants']?> applicants</span>
                <?php if($job['applied']): ?>
                  <span class="btn btn-sm btn-success disabled"><i class="bi bi-check-lg me-1"></i>Applied</span>
                <?php elseif($job['apply_deadline'] && strtotime($job['apply_deadline'])<time()): ?>
                  <span class="btn btn-sm btn-secondary disabled">Deadline Passed</span>
                <?php else: ?>
                  <form method="POST">
                    <?php csrfField(); ?><input type="hidden" name="apply" value="1"><input type="hidden" name="job_id" value="<?=$job['id']?>">
                    <button class="btn btn-sm btn-primary">Apply Now</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if(empty($jobs)): ?><div class="col"><div class="p-5 text-center text-muted"><i class="bi bi-briefcase fs-1 d-block mb-2"></i>No openings available right now.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="tab-pane fade" id="myapps">
    <div class="card">
      <div class="card-body p-0">
        <?php if(empty($myApps)): ?><div class="p-5 text-center text-muted">No applications yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Company</th><th>Position</th><th>Type</th><th>Package</th><th>Applied</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach($myApps as $a): ?>
                <tr>
                  <td><?=htmlspecialchars($a['company_name'])?></td>
                  <td><?=htmlspecialchars($a['title'])?></td>
                  <td><span class="badge bg-<?=$a['type']==='Job'?'primary':'success'?>"><?=$a['type']?></span></td>
                  <td><?=$a['package']??'—'?></td>
                  <td style="font-size:.82rem;"><?=fmtDate($a['applied_at'])?></td>
                  <td><span class="badge bg-<?=$a['status']==='Selected'?'success':($a['status']==='Rejected'?'danger':($a['status']==='Shortlisted'?'info':'secondary'))?>"><?=$a['status']?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
