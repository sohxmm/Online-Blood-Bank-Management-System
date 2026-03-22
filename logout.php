<?php
// logout.php — destroys the session and sends user back to login
session_start();
session_destroy();
header('Location: login.php');
exit;
