<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$u = currentUser();
$pageTitle = 'Admin Dashboard';

$totalStudents = (int)$conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$totalFaculty  = (int)$conn->query("SELECT COUNT(*) c FROM faculty")->fetch_assoc()['c'];
$totalCourses  = (int)$conn->query("SELECT COUNT(*) c FROM courses")->fetch_assoc()['c'];
$totalFees     = (float)$conn->query("SELECT COALESCE(SUM(amount),0) c FROM fees WHERE status='Paid'")->fetch_assoc()['c'];

$recentStudents = $conn->query("SELECT u.name,u.email,s.enrollment_no,d.name dept,s.semester FROM students s JOIN users u ON u.id=s.user_id JOIN departments d ON d.id=s.department_id ORDER BY s.id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$announcements  = $conn->query("SELECT * FROM announcements ORDER BY posted_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$deptDist       = $conn->query("SELECT d.name,COUNT(s.id) cnt FROM departments d LEFT JOIN students s ON s.department_id=d.id GROUP BY d.id")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="d-flex">
<div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="card mb-4" style="background:linear-gradient(135deg,#854F0E,#E8903A);color:#fff;border:none;">
  <div class="card-body py-3 px-4">
    <h4 class="mb-0 fw-bold">Welcome, <?= htmlspecialchars($u['name']) ?>! 🛡️</h4>
    <p class="mb-0" style="opacity:.85;">System Administrator &bull; <?= date('d M Y') ?></p>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card stat-blue"><i class="bi bi-people icon"></i><div class="val"><?= $totalStudents ?></div><div class="lbl">Total Students</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-green"><i class="bi bi-person-badge icon"></i><div class="val"><?= $totalFaculty ?></div><div class="lbl">Faculty Members</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-orange"><i class="bi bi-book icon"></i><div class="val"><?= $totalCourses ?></div><div class="lbl">Courses</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-purple"><i class="bi bi-cash-coin icon"></i><div class="val">₹<?= number_format($totalFees) ?></div><div class="lbl">Fee Collected</div></div></div>
</div>
<div class="row g-3">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-people text-warning me-2"></i>Recent Students</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Name</th><th>Enrollment</th><th>Department</th><th>Sem</th></tr></thead>
          <tbody>
          <?php foreach($recentStudents as $s): ?>
            <tr><td><?= htmlspecialchars($s['name']) ?></td><td><code><?= $s['enrollment_no'] ?></code></td><td><?= htmlspecialchars($s['dept']) ?></td><td><?= $s['semester'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-pie-chart text-warning me-2"></i>Students by Department</div>
      <div class="card-body"><canvas id="deptChart" height="180"></canvas></div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center"><i class="bi bi-megaphone text-warning me-2"></i>Announcements
        <a href="communication_hub.php" class="btn btn-sm btn-outline-warning ms-auto">+ New</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Title</th><th>Target</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($announcements as $a): ?>
            <tr><td><?= htmlspecialchars($a['title']) ?></td><td><span class="badge bg-secondary"><?= $a['target_role'] ?></span></td><td><?= fmtDate($a['posted_at']) ?></td><td><span class="badge bg-<?= $a['is_active']?'success':'secondary' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php
$labels = json_encode(array_column($deptDist,'name'));
$data   = json_encode(array_column($deptDist,'cnt'));
$extraJS = "<script>new Chart(document.getElementById('deptChart'),{type:'doughnut',data:{labels:{$labels},datasets:[{data:{$data},backgroundColor:['#1A3C6E','#28A745','#E8903A','#7C3AED']}]},options:{plugins:{legend:{position:'bottom'}}}});</script>";
include '../includes/footer.php';
?>
