<?php
// login_handler.php
// Receives POST from login.php, validates credentials, starts session.

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

// Basic validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('login.php?error=' . urlencode('Please enter a valid email address.'));
}
if (strlen($password) < 8) {
    redirect('login.php?error=' . urlencode('Password must be at least 8 characters.'));
}

$db = getDB();

// Look up donor by email
$stmt = $db->prepare(
    'SELECT DonorID, FName, LName, PasswordHash, BloodGroup
     FROM   Donor
     WHERE  Email = ?
     LIMIT  1'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check donor exists and password matches
if (!$donor || !password_verify($password, $donor['PasswordHash'])) {
    redirect('login.php?error=' . urlencode('Incorrect email or password. Please try again.'));
}

// ── Start session ──────────────────────────────────────────
$_SESSION['donor_id']    = $donor['DonorID'];
$_SESSION['donor_name']  = $donor['FName'] . ' ' . $donor['LName'];
$_SESSION['blood_group'] = $donor['BloodGroup'];

// Redirect to dashboard
redirect('dashboard.php');
