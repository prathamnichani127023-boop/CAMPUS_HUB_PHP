<?php
$base = BASE_URL . '/student';
$nav = [
  ['icon'=>'bi-speedometer2',  'label'=>'Dashboard',      'href'=>'dashboard.php'],
  ['icon'=>'bi-book',          'label'=>'Academics',       'href'=>'academics.php'],
  ['icon'=>'bi-laptop',        'label'=>'Learning Center', 'href'=>'learning_center.php'],
  ['icon'=>'bi-briefcase',     'label'=>'Career Center',   'href'=>'career_center.php'],
  ['icon'=>'bi-people',        'label'=>'Community',       'href'=>'community.php'],
  ['icon'=>'bi-trophy',        'label'=>'Achievements',    'href'=>'achievements.php'],
  ['icon'=>'bi-kanban',        'label'=>'My Projects',     'href'=>'my_projects.php'],
  ['icon'=>'bi-calendar3',     'label'=>'Timetable',       'href'=>'timetable.php'],
  ['icon'=>'bi-chat-dots',     'label'=>'Messages',        'href'=>'messages.php'],
  ['icon'=>'bi-gear',          'label'=>'Settings',        'href'=>'settings.php'],
];
?>
<div class="sidebar">
  <div class="sidebar-logo">
    <i class="bi bi-mortarboard-fill fs-4"></i>
    <span><?= APP_NAME ?></span>
  </div>
  
  <nav>
  
    <div class="nav-section">Student Portal</div>
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
