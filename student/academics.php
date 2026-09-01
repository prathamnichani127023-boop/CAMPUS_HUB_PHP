<?php

session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireRole('student');
$u         = currentUser();
$studentId = $u['ref_id'];
$pageTitle = 'Academics';

//  Enrolled courses with attendance 
$courses = $conn->query("
    SELECT c.*, d.name AS dept_name,
        u.name AS faculty_name,
        COUNT(att.id) AS total_days,
        SUM(att.status='Present') AS present_days
    FROM enrollments e
    JOIN courses c ON c.id=e.course_id
    JOIN departments d ON d.id=c.department_id
    LEFT JOIN class_assignments ca ON ca.course_id=c.id AND ca.academic_year='".ACADEMIC_YEAR."'
    LEFT JOIN faculty f ON f.id=ca.faculty_id
    LEFT JOIN users u ON u.id=f.user_id
    LEFT JOIN attendance att ON att.course_id=c.id AND att.student_id=$studentId
    WHERE e.student_id=$studentId AND e.academic_year='".ACADEMIC_YEAR."'
    GROUP BY c.id, u.name
")->fetch_all(MYSQLI_ASSOC);

// Grades / Results 
$grades = $conn->query("
    SELECT g.*, ex.title AS exam_title, ex.exam_type, ex.max_marks, ex.exam_date,
        c.name AS course_name, c.code
    FROM grades g
    JOIN exams ex ON ex.id=g.exam_id
    JOIN courses c ON c.id=ex.course_id
    JOIN enrollments e ON e.course_id=c.id AND e.student_id=$studentId
    WHERE g.student_id=$studentId
    ORDER BY ex.exam_date DESC
")->fetch_all(MYSQLI_ASSOC);

//  Pending assignments 
$assignments = $conn->query("
    SELECT a.*, c.name AS course_name, c.code,
        sub.status AS sub_status, sub.marks_obtained, sub.submitted_at
    FROM assignments a
    JOIN enrollments e ON e.course_id=a.course_id AND e.student_id=$studentId
    JOIN courses c ON c.id=a.course_id
    LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id AND sub.student_id=$studentId
    ORDER BY a.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="d-flex">
  <div class="main-content w-100">
    <div class="content-wrapper">
      <?php showFlash(); ?>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4" id="acadTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#courses"><i class="bi bi-book me-1"></i>Courses</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#grades"><i class="bi bi-award me-1"></i>Grades</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#assignments"><i class="bi bi-file-earmark-text me-1"></i>Assignments</a></li>
      </ul>

      <div class="tab-content">
        <!-- Courses Tab -->
        <div class="tab-pane fade show active" id="courses">
          <div class="row g-3">
            <?php foreach ($courses as $c):
              $pct = $c['total_days'] > 0 ? round(($c['present_days']/$c['total_days'])*100,1) : 0;
              $barClass = $pct >= 75 ? '' : ($pct >= 60 ? 'warn' : 'danger');
            ?>
              <div class="col-md-6">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($c['name']) ?></h6>
                        <small class="text-muted"><?= $c['code'] ?> &bull; <?= $c['credits'] ?> credits &bull; <?= $c['type'] ?></small>
                      </div>
                      <span class="badge bg-secondary"><?= $c['semester'] ?> Sem</span>
                    </div>
                    <div class="mb-3" style="font-size:.82rem;color:#555;">
                      <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($c['faculty_name'] ?? 'TBA') ?>
                    </div>
                    <!-- Attendance bar -->
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                      <span>Attendance</span>
                      <strong><?= $pct ?>% (<?= (int)$c['present_days'] ?>/<?= (int)$c['total_days'] ?>)</strong>
                    </div>
                    <div class="att-bar">
                      <div class="att-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <?php if ($pct < 75 && $c['total_days'] > 0): ?>
                      <div class="mt-2 text-danger" style="font-size:.76rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>Attendance below 75%!
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($courses)): ?>
              <div class="col"><div class="p-4 text-center text-muted">No courses enrolled.</div></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Grades Tab -->
        <div class="tab-pane fade" id="grades">
          <div class="card">
            <div class="card-header"><i class="bi bi-award text-primary me-2"></i>Exam Results</div>
            <div class="card-body p-0">
              <?php if (empty($grades)): ?>
                <div class="p-4 text-center text-muted">No results published yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead><tr><th>Course</th><th>Exam</th><th>Type</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
                    <tbody>
                      <?php foreach ($grades as $g):
                        $pct = round(($g['marks_obtained']/$g['max_marks'])*100,1);
                      ?>
                        <tr>
                          <td style="font-size:.85rem;"><?= htmlspecialchars($g['course_name']) ?></td>
                          <td style="font-size:.85rem;"><?= htmlspecialchars($g['exam_title']) ?></td>
                          <td><span class="badge bg-info text-dark"><?= $g['exam_type'] ?></span></td>
                          <td style="font-size:.82rem;"><?= fmtDate($g['exam_date']) ?></td>
                          <td><?= $g['marks_obtained'] ?>/<?= $g['max_marks'] ?></td>
                          <td><?= $pct ?>%</td>
                          <td><span class="badge bg-<?= $pct>=50?'success':'danger' ?>"><?= calcGrade($pct) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Assignments Tab -->
        <div class="tab-pane fade" id="assignments">
          <div class="row g-3">
            <?php foreach ($assignments as $a):
              $due   = strtotime($a['due_date']);
              $overdue = !$a['sub_status'] && $due < time();
            ?>
              <div class="col-md-6">
                <div class="card <?= $overdue?'border-danger':'' ?>">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h6 class="mb-0"><?= htmlspecialchars($a['title']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($a['course_name']) ?></small>
                      </div>
                      <?php if ($a['sub_status'] === 'Graded'): ?>
                        <span class="badge bg-success">Graded</span>
                      <?php elseif ($a['sub_status']): ?>
                        <span class="badge bg-primary">Submitted</span>
                      <?php elseif ($overdue): ?>
                        <span class="badge bg-danger">Overdue</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:.8rem;color:#666;" class="mb-2">
                      <i class="bi bi-clock me-1"></i>Due: <?= fmtDateTime($a['due_date']) ?>
                      &bull; Max: <?= $a['max_marks'] ?> marks
                    </div>
                    <?php if ($a['description']): ?>
                      <p style="font-size:.82rem;color:#555;" class="mb-2"><?= htmlspecialchars($a['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($a['marks_obtained'] !== null): ?>
                      <div class="mt-2 p-2 rounded" style="background:#E1F5EE;font-size:.82rem;">
                        <strong>Marks: <?= $a['marks_obtained'] ?>/<?= $a['max_marks'] ?></strong>
                        &bull; Grade: <strong><?= calcGrade(($a['marks_obtained']/$a['max_marks'])*100) ?></strong>
                      </div>
                    <?php endif; ?>
                    <?php if (!$a['sub_status'] && !$overdue): ?>
                      <form method="POST" action="<?= BASE_URL ?>/student/submit_assignment.php" enctype="multipart/form-data" class="mt-2 d-flex gap-2">
                        <?php csrfField(); ?>
                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                        <input type="file" name="file" class="form-control form-control-sm" required accept=".pdf,.doc,.docx,.zip">
                        <button class="btn btn-sm btn-primary">Submit</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($assignments)): ?>
              <div class="col"><div class="p-4 text-center text-muted">No assignments yet.</div></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
