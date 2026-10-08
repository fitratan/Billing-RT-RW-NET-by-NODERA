<?php
if (isset($_SERVER['REQUEST_URI']) && substr($_SERVER['REQUEST_URI'], -10) == 'config.php') { header('Location:./'); exit; }
$data = array (
  'mikhmon' => 
  array (
    1 => 'mikhmon<|<admin',
    2 => 'mikhmon>|>aWNlbA==',
  ),
  'desktop_license' => 
  array (
    'license_key' => '',
    'updated_at' => '2026-10-08 05:37:16',
  ),
);
