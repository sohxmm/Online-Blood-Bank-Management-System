<?php
session_start();

// destroy session
session_destroy();

echo "Logged out successfully <br>";
echo "<a href='login_manual.php'>Login again</a>";
?>