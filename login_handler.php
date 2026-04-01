<?php
session_start();
require_once 'db.php';

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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Please enter a valid email address.';
    redirect('login.php');
}

if (strlen($password) < 8) {
    $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
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
    $_SESSION['flash_error'] = 'Incorrect email or password. Please try again.';
    redirect('login.php');
}

$_SESSION['donor_id']    = $donor['DonorID'];
$_SESSION['donor_name']  = $donor['FName'] . ' ' . $donor['LName'];
$_SESSION['blood_group'] = $donor['BloodGroup'];

redirect('dashboard.php');