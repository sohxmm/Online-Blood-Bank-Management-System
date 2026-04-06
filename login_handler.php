<?php
require_once 'auth.php';
require_once 'db.php';

boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember_me']);

require_csrf_token($_POST['csrf_token'] ?? null, 'login.php');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('flash_error', 'Please enter a valid email address.');
    redirect('login.php');
}

if (strlen($password) < 8) {
    set_flash('flash_error', 'Password must be at least 8 characters.');
    redirect('login.php');
}

$db = getDB();

$stmt = $db->prepare(
    'SELECT DonorID, FName, LName, PasswordHash, BloodGroup
     FROM Donor
     WHERE Email = ?
     LIMIT 1'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donor || !password_verify($password, $donor['PasswordHash'])) {
    set_flash('flash_error', 'Incorrect email or password. Please try again.');
    redirect('login.php');
}

log_in_donor($donor);

if ($rememberMe) {
    issue_persistent_login((int) $donor['DonorID']);
} else {
    forget_persistent_login_token();
}

redirect('dashboard.php');
