<?php

require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

requireRole('faculty');
$u         = currentUser();
$facultyId = $u['ref_id'];
$pageTitle = 'Assignments';

$myCourses = $conn->query("
    SELECT c.id, c.name, c.code FROM class_assignments ca
    JOIN courses c ON c.id=ca.course_id
    WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'
")->fetch_all(MYSQLI_ASSOC);

//  Create assignment 
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create'])) 
  {
    verifyCsrf();
    $courseId   = (int)$_POST['course_id'];
    $title      = clean($_POST['title']);
    $desc       = clean($_POST['description']);
    $maxMarks   = (float)$_POST['max_marks'];
    $dueDate    = clean($_POST['due_date']);
    $filePath   = null;
    if (!empty($_FILES['file']['name'])) 
    {
        $filePath = uploadFile($_FILES['file'], 'assignments');
    }
    $stmt = $conn->prepare("INSERT INTO assignments (course_id,faculty_id,title,description,file_path,max_marks,due_date) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssd s', $courseId, $facultyId, $title, $desc, $filePath, $maxMarks, $dueDate);
    $stmt->execute(); $stmt->close();
    setFlash('success', 'Assignment created successfully!');
    header('Location: assignments.php'); exit();
}

// Grade submission 
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['grade'])) 
  {
    verifyCsrf();
    $subId    = (int)$_POST['sub_id'];
    $marks    = (float)$_POST['marks'];
    $feedback = clean($_POST['feedback']);
    $conn->query("UPDATE assignment_submissions SET marks_obtained=$marks, feedback='$feedback', status='Graded' WHERE id=$subId");
    setFlash('success', 'Marks saved!');
    header('Location: assignments.php?view='.(int)$_POST['assign_id']); 
    exit();
}

//  View submissions for an assignment 
$viewId = (int)($_GET['view'] ?? 0);
$submissions = [];
$viewAssign  = null;
if ($viewId) 
  {
    $r = $conn->query("SELECT a.*, c.name AS course_name FROM assignments a JOIN courses c ON c.id=a.course_id WHERE a.id=$viewId AND a.faculty_id=$facultyId LIMIT 1");
    $viewAssign = $r->fetch_assoc();
    if ($viewAssign) 
      {
        $submissions = $conn->query("
            SELECT sub.*, u.name AS student_name, s.enrollment_no
            FROM assignment_submissions sub
            JOIN students s ON s.id=sub.student_id
            JOIN users u ON u.id=s.user_id
            WHERE sub.assignment_id=$viewId ORDER BY sub.submitted_at DESC
        ")->fetch_all(MYSQLI_ASSOC);
      }
}

//  All my assignments 
$assignments = $conn->query("
    SELECT a.*, c.name AS course_name,
        (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id=a.id) AS submitted,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=a.course_id AND e.academic_year='".ACADEMIC_YEAR."') AS total_students
    FROM assignments a JOIN courses c ON c.id=a.course_id
    WHERE a.faculty_id=$facultyId ORDER BY a.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_faculty.php';
?>
<div class="d-flex">
  <div class="main-content w-100">
    <div class="content-wrapper">
      <?php showFlash(); ?>

      <div class="row g-3">
        <!-- Create Assignment Form -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle text-success me-2"></i>Create Assignment</div>
            <div class="card-body">
              <form method="POST" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="create" value="1">
                <div class="mb-3">
                  <label class="form-label">Course</label>
                  <select name="course_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($myCourses as $c): ?>
                      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'].' — '.$c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Title</label>
                  <input type="text" name="title" class="form-control" required placeholder="Assignment title">
                </div>
                <div class="mb-3">
                  <label class="form-label">Description</label>
                  <textarea name="description" class="form-control" rows="3" placeholder="Instructions..."></textarea>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col">
                    <label class="form-label">Max Marks</label>
                    <input type="number" name="max_marks" class="form-control" value="100" min="1">
                  </div>
                  <div class="col">
                    <label class="form-label">Due Date</label>
                    <input type="datetime-local" name="due_date" class="form-control" required>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Attachment (optional)</label>
                  <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.zip">
                </div>
                <button class="btn btn-success w-100"><i class="bi bi-plus me-1"></i>Create</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Assignment list -->
        <div class="col-lg-8">
          <?php if ($viewAssign): ?>
            <!-- Submissions view -->
            <div class="card mb-3">
              <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-list-check text-success"></i>
                Submissions — <?= htmlspecialchars($viewAssign['title']) ?>
                <a href="assignments.php" class="btn btn-sm btn-outline-secondary ms-auto">Back</a>
              </div>
              <div class="card-body p-0">
                <?php if (empty($submissions)): ?>
                  <div class="p-4 text-center text-muted">No submissions yet.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover mb-0">
                      <thead><tr><th>Student</th><th>Submitted</th><th>Status</th><th>Marks/<?= $viewAssign['max_marks'] ?></th><th>Grade</th></tr></thead>
                      <tbody>
                        <?php foreach ($submissions as $sub): ?>
                          <tr>
                            <td>
                              <div style="font-size:.88rem;"><?= htmlspecialchars($sub['student_name']) ?></div>
                              <div style="font-size:.74rem;color:#888;"><?= $sub['enrollment_no'] ?></div>
                            </td>
                            <td style="font-size:.82rem;"><?= fmtDateTime($sub['submitted_at']) ?></td>
                            <td><span class="badge bg-<?= $sub['status']=='Graded'?'success':($sub['status']=='Late'?'warning':'primary') ?>"><?= $sub['status'] ?></span></td>
                            <td>
                              <form method="POST" class="d-flex gap-1 align-items-center">
                                <?php csrfField(); ?>
                                <input type="hidden" name="grade" value="1">
                                <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                <input type="hidden" name="assign_id" value="<?= $viewId ?>">
                                <input type="number" name="marks" class="form-control form-control-sm" style="width:70px;"
                                       value="<?= $sub['marks_obtained'] ?? '' ?>" min="0" max="<?= $viewAssign['max_marks'] ?>" step="0.5">
                                <input type="text" name="feedback" class="form-control form-control-sm" style="width:120px;"
                                       value="<?= htmlspecialchars($sub['feedback'] ?? '') ?>" placeholder="Feedback">
                                <button class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                              </form>
                            </td>
                            <td><?php
                              if ($sub['marks_obtained'] !== null) {
                                $pct = ($sub['marks_obtained'] / $viewAssign['max_marks']) * 100;
                                echo '<span class="badge bg-info text-dark">'.calcGrade($pct).'</span>';
                              } else echo '<span class="text-muted">—</span>';
                            ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- All assignments -->
          <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-text text-success me-2"></i>My Assignments</div>
            <div class="card-body p-0">
              <?php if (empty($assignments)): ?>
                <div class="p-4 text-center text-muted">No assignments created yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead><tr><th>Title</th><th>Course</th><th>Due</th><th>Submitted</th><th>Actions</th></tr></thead>
                    <tbody>
                      <?php foreach ($assignments as $a): ?>
                        <tr>
                          <td style="font-size:.88rem;"><?= htmlspecialchars($a['title']) ?></td>
                          <td><span class="badge bg-secondary"><?= htmlspecialchars($a['course_name']) ?></span></td>
                          <td style="font-size:.8rem;"><?= fmtDateTime($a['due_date']) ?></td>
                          <td>
                            <span class="badge bg-light text-dark"><?= $a['submitted'] ?>/<?= $a['total_students'] ?></span>
                          </td>
                          <td>
                            <a href="?view=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                              <i class="bi bi-eye"></i> View
                            </a>
                          </td>
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

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
