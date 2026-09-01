<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
requireRole('admin');
$pageTitle = 'Financial Management';

// Add fee record
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_fee'])) 
{
    verifyCsrf();
    $sid=(int)$_POST['student_id']; $amt=(float)$_POST['amount'];
    $type=clean($_POST['fee_type']); $yr=clean($_POST['academic_year']);
    $due=clean($_POST['due_date']);
    $conn->query("INSERT INTO fees (student_id,amount,fee_type,academic_year,due_date) VALUES ($sid,$amt,'$type','$yr','$due')");
    setFlash('success','Fee record added!'); header('Location: financial_management.php'); exit();
}

// Mark as paid
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mark_paid'])) 
{
    verifyCsrf();
    $id=(int)$_POST['fee_id']; 
    $txn=clean($_POST['transaction_id']);
    $conn->query("UPDATE fees SET status='Paid',paid_date=CURDATE(),transaction_id='$txn' WHERE id=$id");
    setFlash('success','Marked as paid!'); 
    header('Location: financial_management.php'); 
    exit();
}

$students = $conn->query("SELECT s.id,u.name,s.enrollment_no FROM students s JOIN users u ON u.id=s.user_id ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);

// Stats
$totalBilled = (float)$conn->query("SELECT COALESCE(SUM(amount),0) c FROM fees")->fetch_assoc()['c'];
$totalPaid   = (float)$conn->query("SELECT COALESCE(SUM(amount),0) c FROM fees WHERE status='Paid'")->fetch_assoc()['c'];
$totalPending= (float)$conn->query("SELECT COALESCE(SUM(amount),0) c FROM fees WHERE status='Pending'")->fetch_assoc()['c'];

$fees=$conn->query("SELECT f.*,u.name sname,s.enrollment_no FROM fees f JOIN students s ON s.id=f.student_id JOIN users u ON u.id=s.user_id ORDER BY f.id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php'; 
include '../includes/sidebar_admin.php';
?>
<div class="d-flex"><div class="main-content w-100"><div class="content-wrapper">
<?php showFlash(); ?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="stat-card stat-blue"><i class="bi bi-cash-stack icon"></i><div class="val">₹<?=number_format($totalBilled)?></div><div class="lbl">Total Billed</div></div></div>
  <div class="col-md-4"><div class="stat-card stat-green"><i class="bi bi-check-circle icon"></i><div class="val">₹<?=number_format($totalPaid)?></div><div class="lbl">Collected</div></div></div>
  <div class="col-md-4"><div class="stat-card stat-red"><i class="bi bi-exclamation-circle icon"></i><div class="val">₹<?=number_format($totalPending)?></div><div class="lbl">Pending</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle text-warning me-2"></i>Add Fee Record</div>
      <div class="card-body">
        <form method="POST">
          <?php csrfField(); ?><input type="hidden" name="add_fee" value="1">
          <div class="mb-2"><label class="form-label">Student</label>
            <select name="student_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach($students as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?> (<?=$s['enrollment_no']?>)</option><?php endforeach;?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Fee Type</label>
            <select name="fee_type" class="form-select"><option>Tuition Fee</option><option>Exam Fee</option><option>Library Fee</option><option>Lab Fee</option><option>Hostel Fee</option><option>Other</option></select>
          </div>
          <div class="mb-2"><label class="form-label">Amount (₹)</label><input type="number" name="amount" class="form-control" required step="0.01" placeholder="15000"></div>
          <div class="mb-2"><label class="form-label">Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?=ACADEMIC_YEAR?>"></div>
          <div class="mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" required></div>
          <button class="btn btn-warning w-100">Add Record</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-receipt text-warning me-2"></i>Fee Records</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Student</th><th>Fee Type</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($fees as $f): ?>
              <tr>
                <td><div style="font-size:.85rem;"><?=htmlspecialchars($f['sname'])?></div><div style="font-size:.74rem;color:#888;"><?=$f['enrollment_no']?></div></td>
                <td style="font-size:.83rem;"><?=$f['fee_type']?></td>
                <td>₹<?=number_format($f['amount'])?></td>
                <td style="font-size:.82rem;"><?=fmtDate($f['due_date'])?></td>
                <td><span class="badge bg-<?=$f['status']==='Paid'?'success':($f['status']==='Overdue'?'danger':'warning text-dark')?>"><?=$f['status']?></span></td>
                <td>
                  <?php if($f['status']!=='Paid'): ?>
                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#payModal" data-id="<?=$f['id']?>">Mark Paid</button>
                  <?php else: ?>
                    <span style="font-size:.76rem;color:#888;"><?=$f['transaction_id']?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($fees)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Mark as Paid</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?php csrfField(); ?><input type="hidden" name="mark_paid" value="1">
        <div class="modal-body">
          <input type="hidden" name="fee_id" id="payFeeId">
          <label class="form-label">Transaction ID</label>
          <input type="text" name="transaction_id" class="form-control" placeholder="TXN123456" required>
        </div>
        <div class="modal-footer"><button class="btn btn-success w-100">Confirm Payment</button></div>
      </form>
    </div>
  </div>
</div>
</div></div></div>
<?php
$extraJS='<script>document.getElementById("payModal").addEventListener("show.bs.modal",function(e){document.getElementById("payFeeId").value=e.relatedTarget.dataset.id;});</script>';
include '../includes/footer.php'; ?>
