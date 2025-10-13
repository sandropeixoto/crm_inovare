<?php
session_start();
session_destroy();
header("Location: /inovare/public/login.php");
exit;
