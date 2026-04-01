<?php
session_start();

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit;
}

function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

$name    = clean($_POST['fullname'] ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$message = clean($_POST['message']  ?? '');

$errors = [];
if (strlen($name) < 3)                          $errors[] = 'Name must be at least 3 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (strlen($message) < 10)                      $errors[] = 'Message must be at least 10 characters.';

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode(' | ', $errors);
    header("Location: contact.php");
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'INSERT INTO Contact_Messages (FullName, Email, Message) VALUES (?,?,?)'
);
$stmt->bind_param('sss', $name, $email, $message);

if ($stmt->execute()) {
    $_SESSION['flash_success'] = 'Message sent successfully.';
    header('Location: contact.php');
} else {
    $_SESSION['flash_error'] = 'Could not send message. Please try again.';
    header('Location: contact.php');
}

$stmt->close();