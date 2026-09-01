<?php
$base = BASE_URL . '/admin';
$nav = [
  ['icon'=>'bi-speedometer2',  'label'=>'Dashboard',           'href'=>'dashboard.php'],
  ['icon'=>'bi-people',        'label'=>'Student Management',  'href'=>'student_management.php'],
  ['icon'=>'bi-book',          'label'=>'Academic Management', 'href'=>'academic_management.php'],
  ['icon'=>'bi-person-badge',  'label'=>'Faculty Management',  'href'=>'faculty_management.php'],
  ['icon'=>'bi-cash-coin',     'label'=>'Financial Management','href'=>'financial_management.php'],
  ['icon'=>'bi-briefcase',     'label'=>'Placement & Career',  'href'=>'placement_career.php'],
  ['icon'=>'bi-megaphone',     'label'=>'Communication Hub',   'href'=>'communication_hub.php'],
  ['icon'=>'bi-gear',          'label'=>'System Settings',     'href'=>'system_settings.php'],
  ['icon'=>'bi-person-circle', 'label'=>'My Profile',          'href'=>'profile.php'],
];
?>
<div class="sidebar">
  <div class="sidebar-logo">
    <i class="bi bi-mortarboard-fill fs-4"></i>
    <span><?= APP_NAME ?></span>
  </div>
  <nav>
    <div class="nav-section"> Admin Portal </div>
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
