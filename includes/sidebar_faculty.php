<?php
$base = BASE_URL . '/faculty';
$nav = [
  ['icon'=>'bi-speedometer2',  'label'=>'Dashboard',         'href'=>'dashboard.php'],
  ['icon'=>'bi-journal-text',  'label'=>'My Classes',        'href'=>'my_classes.php'],
  ['icon'=>'bi-person-check',  'label'=>'Attendance',        'href'=>'attendance.php'],
  ['icon'=>'bi-file-earmark-text','label'=>'Assignments',    'href'=>'assignments.php'],
  ['icon'=>'bi-pencil-square', 'label'=>'Exams',             'href'=>'exams.php'],
  ['icon'=>'bi-star',          'label'=>'Student Feedback',  'href'=>'student_feedback.php'],
  ['icon'=>'bi-calendar3',     'label'=>'Timetable',         'href'=>'timetable.php'],
  ['icon'=>'bi-folder2-open',  'label'=>'Resources',         'href'=>'resources.php'],
  ['icon'=>'bi-chat-dots',     'label'=>'Messages',          'href'=>'messages.php'],
  ['icon'=>'bi-person-circle', 'label'=>'Profile',           'href'=>'profile.php'],
];
?>
<div class="sidebar">
  <div class="sidebar-logo">
    <i class="bi bi-mortarboard-fill fs-4"></i>
    <span><?= APP_NAME ?></span>
  </div>
  
  <nav>
    <div class="nav-section"> Faculty Portal </div>
    <?php 
		foreach ($nav as $item): 
	?>
      <a class="nav-link" href="<?= $base ?>/<?= $item['href'] ?>">
        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
      </a>
    <?php 
		endforeach; 
	?>
  </nav>
</div>
