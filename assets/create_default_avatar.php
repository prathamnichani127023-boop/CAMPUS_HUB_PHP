<?php
// Run this once to create default.png placeholder
$size = 100;
$img  = imagecreatetruecolor($size, $size);
$bg   = imagecolorallocate($img, 26, 60, 110);
$fg   = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $bg);

// Draw simple person icon using ellipses and arcs
imagefilledellipse($img, 50, 35, 36, 36, $fg);          // head
imagefilledarc($img, 50, 90, 70, 60, 180, 360, $fg, IMG_ARC_PIE); // body
$path = __DIR__ . '/img/default.png';
imagepng($img, $path);
imagedestroy($img);
echo "default.png created at $path";
