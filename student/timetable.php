<?php

session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireRole('student');
$u         = currentUser();
$studentId = $u['ref_id'];
$pageTitle = 'Timetable';

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

//  Weekly timetable 
$rows = $conn->query("
    SELECT t.*, c.name AS course_name, c.code, c.type,
        u.name AS faculty_name
    FROM timetable t
    JOIN enrollments e ON e.course_id=t.course_id AND e.student_id=$studentId
    JOIN courses c ON c.id=t.course_id
    JOIN faculty f ON f.id=t.faculty_id
    JOIN users u ON u.id=f.user_id
    WHERE t.academic_year='".ACADEMIC_YEAR."'
    ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
")->fetch_all(MYSQLI_ASSOC);

// Group by day
$byDay = [];
foreach ($rows as $r) $byDay[$r['day_of_week']][] = $r;

//  Exam timetable 
$exams = $conn->query("
    SELECT ex.*, c.name AS course_name, c.code
    FROM exams ex
    JOIN courses c ON c.id=ex.course_id
    JOIN enrollments e ON e.course_id=c.id AND e.student_id=$studentId
    WHERE ex.exam_date >= CURDATE()
    ORDER BY ex.exam_date, ex.start_time
")->fetch_all(MYSQLI_ASSOC);

$today = date('l');

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="d-flex">
  <div class="main-content w-100">
    <div class="content-wrapper">

      <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#weekly"><i class="bi bi-calendar-week me-1"></i>Weekly Schedule</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#exams"><i class="bi bi-pencil-square me-1"></i>Exam Timetable</a></li>
      </ul>

      <div class="tab-content">
        <!-- Weekly -->
        <div class="tab-pane fade show active" id="weekly">
          <div class="row g-3">
            <?php foreach ($days as $day): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card <?= $day===$today?'border-primary':'' ?>">
                  <div class="card-header d-flex align-items-center gap-2"
                       style="<?= $day===$today?'background:#EEF2FF;':'' ?>">
                    <i class="bi bi-calendar2 <?= $day===$today?'text-primary':'text-muted' ?>"></i>
                    <strong><?= $day ?></strong>
                    <?php if ($day===$today): ?>
                      <span class="badge bg-primary ms-auto">Today</span>
                    <?php endif; ?>
                  </div>
                  <div class="card-body p-0">
                    <?php if (empty($byDay[$day])): ?>
                      <div class="p-3 text-center text-muted" style="font-size:.85rem;">
                        <i class="bi bi-moon me-1"></i>No classes
                      </div>
                    <?php else: ?>
                      <ul class="list-group list-group-flush">
                        <?php foreach ($byDay[$day] as $cls): ?>
                          <li class="list-group-item py-2 px-3">
                            <div class="d-flex align-items-start gap-2">
                              <div class="text-center px-2 py-1 rounded flex-shrink-0"
                                   style="background:<?= $cls['type']==='Practical'?'#FFF3CD':'#E1F5EE' ?>;min-width:52px;font-size:.72rem;color:#333;">
                                <?= substr($cls['start_time'],0,5) ?><br>
                                <span>–<?= substr($cls['end_time'],0,5) ?></span>
                              </div>
                              <div>
                                <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($cls['course_name']) ?></div>
                                <div style="font-size:.74rem;color:#888;">
                                  <?= htmlspecialchars($cls['faculty_name']) ?>
                                  <?php if ($cls['room']): ?> &bull; <?= $cls['room'] ?><?php endif; ?>
                                </div>
                                <?php if ($cls['type']==='Practical'): ?>
                                  <span class="badge" style="background:#FFF3CD;color:#856404;font-size:.68rem;">Lab</span>
                                <?php endif; ?>
                              </div>
                            </div>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Exams -->
        <div class="tab-pane fade" id="exams">
          <div class="card">
            <div class="card-header"><i class="bi bi-pencil-square text-danger me-2"></i>Upcoming Exams</div>
            <div class="card-body p-0">
              <?php if (empty($exams)): ?>
                <div class="p-4 text-center text-muted">No upcoming exams scheduled.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead><tr><th>Course</th><th>Exam</th><th>Type</th><th>Date</th><th>Time</th><th>Duration</th><th>Room</th></tr></thead>
                    <tbody>
                      <?php foreach ($exams as $ex):
                        $daysLeft = (int)((strtotime($ex['exam_date']) - time()) / 86400);
                      ?>
                        <tr>
                          <td>
                            <span class="badge bg-secondary"><?= $ex['code'] ?></span>
                            <div style="font-size:.8rem;"><?= htmlspecialchars($ex['course_name']) ?></div>
                          </td>
                          <td style="font-size:.85rem;"><?= htmlspecialchars($ex['title']) ?></td>
                          <td><span class="badge bg-info text-dark"><?= $ex['exam_type'] ?></span></td>
                          <td>
                            <?= fmtDate($ex['exam_date']) ?>
                            <?php if ($daysLeft <= 7): ?>
                              <div class="badge bg-danger"><?= $daysLeft ?> days left</div>
                            <?php elseif ($daysLeft <= 14): ?>
                              <div class="badge bg-warning text-dark"><?= $daysLeft ?> days left</div>
                            <?php endif; ?>
                          </td>
                          <td><?= $ex['start_time'] ? substr($ex['start_time'],0,5) : '—' ?></td>
                          <td><?= $ex['duration_min'] ?> min</td>
                          <td><?= htmlspecialchars($ex['room'] ?? '—') ?></td>
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
