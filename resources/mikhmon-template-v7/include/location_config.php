<?php
if(substr($_SERVER["REQUEST_URI"], -19) == "location_config.php"){header("Location:./");};
$location_data = array (
  'primary' => '',
  'locations' => array (),
);
