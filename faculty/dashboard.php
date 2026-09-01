<?php

session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireRole('faculty');

$u         = currentUser();
$facultyId = $u['ref_id'];
$pageTitle = 'Dashboard';

//  Faculty info 
$stmt = $conn->prepare("
    SELECT f.*, d.name AS dept_name, u.name, u.email, u.profile_photo
    FROM faculty f
    JOIN departments d ON d.id = f.department_id
    JOIN users u ON u.id = f.user_id
    WHERE f.id = ?
");
$stmt->bind_param('i', $facultyId);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

//  Stats 
// My classes this year
$r = $conn->query("SELECT COUNT(*) c FROM class_assignments WHERE faculty_id=$facultyId AND academic_year='".ACADEMIC_YEAR."'");
$totalClasses = (int)$r->fetch_assoc()['c'];

// Total students across my courses
$r = $conn->query("
    SELECT COUNT(DISTINCT e.student_id) c
    FROM enrollments e
    JOIN class_assignments ca ON ca.course_id=e.course_id
    WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'
");
$totalStudents = (int)$r->fetch_assoc()['c'];

// Pending assignments to grade
$r = $conn->query("
    SELECT COUNT(*) c FROM assignment_submissions sub
    JOIN assignments a ON a.id=sub.assignment_id
    WHERE a.faculty_id=$facultyId AND sub.status='Submitted'
");
$pendingGrade = (int)$r->fetch_assoc()['c'];

// Unread messages
$r = $conn->query("SELECT COUNT(*) c FROM messages WHERE receiver_id={$u['id']} AND is_read=0");
$unreadMsg = (int)$r->fetch_assoc()['c'];

//  Today's timetable 
$today = date('l');
$todayClasses = $conn->query("
    SELECT t.*, c.name AS course_name, c.code,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=t.course_id AND e.academic_year='".ACADEMIC_YEAR."') AS enrolled
    FROM timetable t
    JOIN courses c ON c.id=t.course_id
    WHERE t.faculty_id=$facultyId AND t.day_of_week='$today' AND t.academic_year='".ACADEMIC_YEAR."'
    ORDER BY t.start_time
")->fetch_all(MYSQLI_ASSOC);

//  My courses 
$myCourses = $conn->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id AND e.academic_year='".ACADEMIC_YEAR."') AS enrolled,
        (SELECT AVG(rating) FROM feedback fb WHERE fb.faculty_id=$facultyId AND fb.course_id=c.id) AS avg_rating
    FROM class_assignments ca
    JOIN courses c ON c.id=ca.course_id
    WHERE ca.faculty_id=$facultyId AND ca.academic_year='".ACADEMIC_YEAR."'
")->fetch_all(MYSQLI_ASSOC);

//  Recent announcements 
$announcements = $conn->query("
    SELECT a.*, u.name AS posted_by_name
    FROM announcements a JOIN users u ON u.id=a.posted_by
    WHERE a.is_active=1 AND (a.target_role='all' OR a.target_role='faculty')
    ORDER BY a.posted_at DESC LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

//  Attendance overview (last 7 days across my courses) 
$attChart = $conn->query("
    SELECT att.date, COUNT(*) total, SUM(att.status='Present') present
    FROM attendance att
    JOIN class_assignments ca ON ca.course_id=att.course_id
    WHERE ca.faculty_id=$facultyId AND att.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY att.date ORDER BY att.date
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_faculty.php';
?>

<div class="d-flex">
  <div class="main-content w-100">
    <div class="content-wrapper">
      <?php showFlash(); ?>

      <!-- Welcome banner -->
      <div class="card mb-4" style="background:linear-gradient(135deg,#155724,#28A745);color:#fff;border:none;">
        <div class="card-body py-4 px-4">
          <h4 class="mb-1 fw-bold">Welcome, <?= htmlspecialchars(explode(' ',$faculty['name'])[0]) ?>! 👋</h4>
          <p class="mb-0" style="opacity:.85;">
            <?= htmlspecialchars($faculty['designation']) ?> &bull;
            <?= htmlspecialchars($faculty['dept_name']) ?> &bull;
            ID: <?= htmlspecialchars($faculty['employee_id']) ?>
          </p>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card stat-blue">
            <i class="bi bi-journal-text icon"></i>
            <div class="val"><?= $totalClasses ?></div>
            <div class="lbl">My Courses</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-green">
            <i class="bi bi-people icon"></i>
            <div class="val"><?= $totalStudents ?></div>
            <div class="lbl">Total Students</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-orange">
            <i class="bi bi-file-earmark-check icon"></i>
            <div class="val"><?= $pendingGrade ?></div>
            <div class="lbl">To Grade</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-purple">
            <i class="bi bi-chat-dots icon"></i>
            <div class="val"><?= $unreadMsg ?></div>
            <div class="lbl">Unread Messages</div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Today's schedule -->
        <div class="col-md-5">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-calendar-day text-success"></i> Today's Schedule
              <span class="badge bg-light text-dark ms-auto"><?= $today ?></span>
            </div>
            <div class="card-body p-0">
              <?php if (empty($todayClasses)): ?>
                <div class="p-4 text-center text-muted">
                  <i class="bi bi-cup-hot fs-2 d-block mb-2"></i>No classes today!
                </div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($todayClasses as $cls): ?>
                    <li class="list-group-item">
                      <div class="d-flex align-items-center gap-3">
                        <div class="text-center px-2 py-1 rounded" style="background:#E1F5EE;min-width:58px;font-size:.76rem;color:#085041;">
                          <?= substr($cls['start_time'],0,5) ?><br>
                          <span style="font-size:.68rem;"><?= substr($cls['end_time'],0,5) ?></span>
                        </div>
                        <div class="flex-1">
                          <div class="fw-semibold" style="font-size:.9rem;"><?= htmlspecialchars($cls['course_name']) ?></div>
                          <div style="font-size:.78rem;color:#888;">
                            <?= $cls['enrolled'] ?> students &bull; Room <?= htmlspecialchars($cls['room'] ?? '—') ?>
                          </div>
                        </div>
                        <a href="<?= BASE_URL ?>/faculty/attendance.php?course=<?= $cls['course_id'] ?>"
                           class="btn btn-sm btn-outline-success">Mark</a>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- My courses -->
        <div class="col-md-7">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-book text-success"></i> My Courses (<?= ACADEMIC_YEAR ?>)
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead><tr>
                    <th>Course</th><th>Code</th><th>Students</th><th>Rating</th><th>Actions</th>
                  </tr></thead>
                  <tbody>
                  <?php if (empty($myCourses)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No courses assigned</td></tr>
                  <?php else: ?>
                    <?php foreach ($myCourses as $c): ?>
                      <tr>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><span class="badge bg-secondary"><?= $c['code'] ?></span></td>
                        <td><?= $c['enrolled'] ?></td>
                        <td>
                          <?php if ($c['avg_rating']): ?>
                            <span class="text-warning">★</span> <?= round($c['avg_rating'],1) ?>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <a href="<?= BASE_URL ?>/faculty/attendance.php?course=<?= $c['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm">Attendance</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Announcements -->
        <div class="col-md-6">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-megaphone text-success"></i> Announcements
            </div>
            <div class="card-body p-0">
              <?php if (empty($announcements)): ?>
                <div class="p-3 text-center text-muted">No announcements</div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($announcements as $ann): ?>
                    <li class="list-group-item">
                      <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($ann['title']) ?></div>
                      <div style="font-size:.76rem;color:#888;"><?= fmtDate($ann['posted_at']) ?></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Attendance chart (last 7 days) -->
        <div class="col-md-6">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-bar-chart text-success"></i> Attendance Trend (7 days)
            </div>
            <div class="card-body">
              <canvas id="attChart" height="140"></canvas>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php
$chartLabels = json_encode(array_column($attChart, 'date'));
$chartPresent = json_encode(array_column($attChart, 'present'));
$chartTotal   = json_encode(array_column($attChart, 'total'));
$extraJS = <<<JS
<script>
const ctx = document.getElementById('attChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: {$chartLabels},
    datasets: [
      { label: 'Present', data: {$chartPresent}, backgroundColor: '#28A745CC', borderRadius: 4 },
      { label: 'Total',   data: {$chartTotal},   backgroundColor: '#1A3C6E33', borderRadius: 4 }
    ]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});
</script>
JS;
include '../includes/footer.php';
?>
