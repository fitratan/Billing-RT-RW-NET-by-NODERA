<?php
if(substr($_SERVER["REQUEST_URI"], -19) == "telegram_config.php"){header("Location:./");};
$tg_data = array();
