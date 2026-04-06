<?php
// logout.php — destroys the session and sends user back to login
require_once 'auth.php';

log_out_donor();
boot_session();
set_flash('flash_success', 'You have been signed out successfully.');

header('Location: login.php');
exit;
