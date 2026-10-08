<?php 
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -10) == "config.php") { header("Location:./"); exit; }
$data['mikhmon'] = array(
  '1' => 'mikhmon<|<admin',
  '2' => 'mikhmon>|>aWNlbA=='
);
