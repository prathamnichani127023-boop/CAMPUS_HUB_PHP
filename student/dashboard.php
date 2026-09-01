<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireRole('student');

$u          = currentUser();
$studentId  = $u['ref_id'];
$pageTitle  = 'Dashboard';

//  Fetch student info 
$stmt = $conn->prepare("
    SELECT s.*, d.name AS dept_name, u.name, u.email, u.profile_photo
    FROM students s
    JOIN departments d ON d.id = s.department_id
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

//  Stats
// Total enrolled courses
$r = $conn->query("SELECT COUNT(*) c FROM enrollments WHERE student_id=$studentId AND academic_year='".ACADEMIC_YEAR."'");
$totalCourses = (int)$r->fetch_assoc()['c'];

// Attendance %
$r = $conn->query("SELECT
    COUNT(*) total,
    SUM(status='Present') present
    FROM attendance WHERE student_id=$studentId");
$att = $r->fetch_assoc();
$attPct = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100, 1) : 0;

// Pending assignments
$r = $conn->query("
    SELECT COUNT(*) c FROM assignments a
    JOIN enrollments e ON e.course_id = a.course_id AND e.student_id=$studentId
    LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id AND sub.student_id=$studentId
    WHERE sub.id IS NULL AND a.due_date >= NOW()
");
$pendingAssign = (int)$r->fetch_assoc()['c'];

// Unread messages
$r = $conn->query("SELECT COUNT(*) c FROM messages WHERE receiver_id={$u['id']} AND is_read=0");
$unreadMsg = (int)$r->fetch_assoc()['c'];

//  Recent announcements 
$announcements = $conn->query("
    SELECT a.*, u.name AS posted_by_name
    FROM announcements a JOIN users u ON u.id=a.posted_by
    WHERE a.is_active=1 AND (a.target_role='all' OR a.target_role='student')
    ORDER BY a.posted_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

//  Today's timetable 
$today = date('l');
$todayClasses = $conn->query("
    SELECT t.*, c.name AS course_name, c.code, u.name AS faculty_name
    FROM timetable t
    JOIN enrollments e ON e.course_id=t.course_id AND e.student_id=$studentId
    JOIN courses c ON c.id=t.course_id
    JOIN faculty f ON f.id=t.faculty_id
    JOIN users u ON u.id=f.user_id
    WHERE t.day_of_week='$today' AND t.academic_year='".ACADEMIC_YEAR."'
    ORDER BY t.start_time
")->fetch_all(MYSQLI_ASSOC);

//  Course attendance breakdown 
$courseAtt = $conn->query("
    SELECT c.name, c.code,
        COUNT(att.id) total,
        SUM(att.status='Present') present
    FROM enrollments e
    JOIN courses c ON c.id=e.course_id
    LEFT JOIN attendance att ON att.course_id=e.course_id AND att.student_id=$studentId
    WHERE e.student_id=$studentId AND e.academic_year='".ACADEMIC_YEAR."'
    GROUP BY c.id
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>

<div class="d-flex">
  <?php // sidebar already echoed ?>
  <div class="main-content w-100">
    <div class="content-wrapper">
      <?php showFlash(); ?>

      <!-- Welcome banner -->
      <div class="card mb-4" style="background:linear-gradient(135deg,#1A3C6E,#2A5298);color:#fff;border:none;">
        <div class="card-body py-4 px-4 d-flex align-items-center gap-4">
          <div>
            <h4 class="mb-1 fw-bold">Hello, <?= htmlspecialchars(explode(' ', $student['name'])[0]) ?>! 👋</h4>
            <p class="mb-0" style="opacity:.85;">
              <?= htmlspecialchars($student['dept_name']) ?> &bull;
              Semester <?= $student['semester'] ?> &bull;
              <?= htmlspecialchars($student['enrollment_no']) ?>
            </p>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card stat-blue">
            <i class="bi bi-book icon"></i>
            <div class="val"><?= $totalCourses ?></div>
            <div class="lbl">Enrolled Courses</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card <?= $attPct >= 75 ? 'stat-green' : 'stat-red' ?>">
            <i class="bi bi-person-check icon"></i>
            <div class="val"><?= $attPct ?>%</div>
            <div class="lbl">Attendance</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-orange">
            <i class="bi bi-file-earmark icon"></i>
            <div class="val"><?= $pendingAssign ?></div>
            <div class="lbl">Pending Assignments</div>
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
        <!-- Today's Classes -->
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-calendar-day text-primary"></i> Today's Classes
              <span class="badge bg-light text-dark ms-auto"><?= $today ?></span>
            </div>
            <div class="card-body p-0">
              <?php if (empty($todayClasses)): ?>
                <div class="p-4 text-center text-muted">
                  <i class="bi bi-cup-hot fs-2 d-block mb-2"></i>No classes today. Enjoy your day!
                </div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($todayClasses as $cls): ?>
                    <li class="list-group-item">
                      <div class="d-flex align-items-center gap-3">
                        <div class="text-center" style="min-width:54px;font-size:.75rem;color:#888;">
                          <?= substr($cls['start_time'],0,5) ?><br>
                          <span style="font-size:.68rem;">to <?= substr($cls['end_time'],0,5) ?></span>
                        </div>
                        <div>
                          <div class="fw-semibold" style="font-size:.9rem;"><?= htmlspecialchars($cls['course_name']) ?></div>
                          <div style="font-size:.78rem;color:#888;">
                            <?= htmlspecialchars($cls['faculty_name']) ?> &bull;
                            Room <?= htmlspecialchars($cls['room'] ?? '—') ?>
                          </div>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Announcements -->
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-megaphone text-primary"></i> Announcements
            </div>
            <div class="card-body p-0">
              <?php if (empty($announcements)): ?>
                <div class="p-4 text-center text-muted">No announcements</div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($announcements as $ann): ?>
                    <li class="list-group-item">
                      <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($ann['title']) ?></div>
                      <div style="font-size:.78rem;color:#888;">
                        By <?= htmlspecialchars($ann['posted_by_name']) ?> &bull; <?= fmtDate($ann['posted_at']) ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Attendance by course -->
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-bar-chart text-primary"></i> Attendance — Course-wise
            </div>
            <div class="card-body">
              <?php if (empty($courseAtt)): ?>
                <p class="text-muted">No data available.</p>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach ($courseAtt as $ca):
                    $pct  = $ca['total'] > 0 ? round(($ca['present'] / $ca['total']) * 100, 1) : 0;
                    $cls  = $pct >= 75 ? '' : ($pct >= 60 ? 'warn' : 'danger');
                  ?>
                    <div class="col-md-4">
                      <div class="mb-1 d-flex justify-content-between" style="font-size:.82rem;">
                        <span><?= htmlspecialchars($ca['code']) ?> — <?= htmlspecialchars($ca['name']) ?></span>
                        <strong><?= $pct ?>%</strong>
                      </div>
                      <div class="att-bar">
                        <div class="att-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.content-wrapper -->
  </div><!-- /.main-content -->
</div>

<?php include '../includes/footer.php'; ?>
