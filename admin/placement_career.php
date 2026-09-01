<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle='Placement & Career';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_company'])) 
{
    verifyCsrf();
    $name=clean($_POST['name']); 
    $industry=clean($_POST['industry']); 
    $web=clean($_POST['website']); 
    $cname=clean($_POST['contact_name']); 
    $cemail=clean($_POST['contact_email']);
    $conn->query("INSERT INTO companies (name,industry,website,contact_name,contact_email) VALUES ('$name','$industry','$web','$cname','$cemail')");
    setFlash('success','Company added!'); 
    header('Location: placement_career.php'); 
    exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_job'])) 
{
    verifyCsrf();
    $cid=(int)$_POST['company_id']; 
    $title=clean($_POST['title']); 
    $type=clean($_POST['type']);
    $desc=clean($_POST['description']); 
    $elig=clean($_POST['eligibility']); 
    $pkg=clean($_POST['package']); 
    $dead=clean($_POST['apply_deadline']);
    $conn->query("INSERT INTO job_postings (company_id,title,type,description,eligibility,package,apply_deadline) VALUES ($cid,'$title','$type','$desc','$elig','$pkg','$dead')");
    setFlash('success','Job posting added!'); header('Location: placement_career.php'); 
    exit();
}

if (isset($_GET['close'])) 
{ 
  $conn->query("UPDATE job_postings SET status='Closed' WHERE id=".(int)$_GET['close']); 
  header('Location: placement_career.php'); 
  exit(); 
}
if (isset($_GET['update_app'])) 
{
    $aid=(int)$_GET['update_app']; 
    $status=clean($_GET['status']);
    $conn->query("UPDATE job_applications SET status='$status' WHERE id=$aid");
    header('Location: placement_career.php'); 
    exit();
}

$companies=$conn->query("SELECT * FROM companies ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$jobs=$conn->query("SELECT jp.*,c.name company_name,(SELECT COUNT(*) FROM job_applications a WHERE a.job_id=jp.id) apps FROM job_postings jp JOIN companies c ON c.id=jp.company_id ORDER BY jp.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$applications=$conn->query("SELECT a.*,u.name sname,s.enrollment_no,jp.title job_title,c.name company_name FROM job_applications a JOIN students s ON s.id=a.student_id JOIN users u ON u.id=s.user_id JOIN job_postings jp ON jp.id=a.job_id JOIN companies c ON c.id=jp.company_id ORDER BY a.applied_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#companies"><i class="bi bi-building me-1"></i>Companies</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#jobs"><i class="bi bi-briefcase me-1"></i>Job Postings</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#applications"><i class="bi bi-list-check me-1"></i>Applications</a></li>
</ul>
<div class="tab-content">

<!-- Companies -->
<div class="tab-pane fade show active" id="companies">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Register Company</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="add_company" value="1">
            <div class="mb-2"><label class="form-label">Company Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control" placeholder="Software, Finance..."></div>
            <div class="mb-2"><label class="form-label">Website</label><input type="url" name="website" class="form-control" placeholder="https://..."></div>
            <div class="mb-2"><label class="form-label">Contact Person</label><input type="text" name="contact_name" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control"></div>
            <button class="btn btn-warning w-100">Register Company</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-building text-warning me-2"></i>Registered Companies</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead><tr><th>Company</th><th>Industry</th><th>Contact</th><th>Website</th></tr></thead>
            <tbody>
            <?php foreach($companies as $c): ?>
              <tr><td><?=htmlspecialchars($c['name'])?></td><td><?=htmlspecialchars($c['industry']??'—')?></td><td style="font-size:.82rem;"><?=htmlspecialchars($c['contact_name']??'—')?><br><span style="color:#888;"><?=$c['contact_email']??''?></span></td><td><?php if($c['website']): ?><a href="<?=htmlspecialchars($c['website'])?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-link-45deg"></i></a><?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Jobs -->
<div class="tab-pane fade" id="jobs">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Add Job Posting</div>
        <div class="card-body">
          <form method="POST">
            <?php csrfField(); ?><input type="hidden" name="add_job" value="1">
            <div class="mb-2"><label class="form-label">Company</label><select name="company_id" class="form-select" required><option value="">— Select —</option><?php foreach($companies as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach;?></select></div>
            <div class="mb-2"><label class="form-label">Job Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Type</label><select name="type" class="form-select"><option>Job</option><option>Internship</option></select></div>
            <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="mb-2"><label class="form-label">Eligibility</label><input type="text" name="eligibility" class="form-control" placeholder="7.0 CGPA, CS/IT"></div>
            <div class="mb-2"><label class="form-label">Package</label><input type="text" name="package" class="form-control" placeholder="5 LPA / 15000/month"></div>
            <div class="mb-3"><label class="form-label">Apply Deadline</label><input type="date" name="apply_deadline" class="form-control"></div>
            <button class="btn btn-warning w-100">Add Posting</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead><tr><th>Title</th><th>Company</th><th>Type</th><th>Deadline</th><th>Apps</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($jobs as $j): ?>
              <tr><td style="font-size:.85rem;"><?=htmlspecialchars($j['title'])?></td><td style="font-size:.82rem;"><?=htmlspecialchars($j['company_name'])?></td><td><span class="badge bg-<?=$j['type']==='Job'?'primary':'success'?>"><?=$j['type']?></span></td><td style="font-size:.8rem;"><?=fmtDate($j['apply_deadline']??'')?></td><td><?=$j['apps']?></td><td><span class="badge bg-<?=$j['status']==='Open'?'success':'secondary'?>"><?=$j['status']?></span></td><td><?php if($j['status']==='Open'): ?><a href="?close=<?=$j['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Close this posting?')">Close</a><?php endif;?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Applications -->
<div class="tab-pane fade" id="applications">
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Student</th><th>Job</th><th>Company</th><th>Applied</th><th>Status</th><th>Update</th></tr></thead>
          <tbody>
          <?php foreach($applications as $a): ?>
            <tr>
              <td style="font-size:.85rem;"><?=htmlspecialchars($a['sname'])?><br><code style="font-size:.72rem;"><?=$a['enrollment_no']?></code></td>
              <td style="font-size:.83rem;"><?=htmlspecialchars($a['job_title'])?></td>
              <td style="font-size:.82rem;"><?=htmlspecialchars($a['company_name'])?></td>
              <td style="font-size:.78rem;"><?=fmtDate($a['applied_at'])?></td>
              <td><span class="badge bg-<?=$a['status']==='Selected'?'success':($a['status']==='Rejected'?'danger':($a['status']==='Shortlisted'?'info':'secondary'))?>"><?=$a['status']?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <?php foreach(['Shortlisted','Selected','Rejected'] as $st): ?>
                    <?php if($a['status']!==$st): ?>
                      <a href="?update_app=<?=$a['id']?>&status=<?=$st?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:2px 6px;"><?=$st?></a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($applications)): ?><tr><td colspan="6" class="text-center text-muted py-3">No applications yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
