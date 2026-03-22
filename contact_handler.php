<?php
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
if (strlen($name) < 3)                              $errors[] = 'Name must be at least 3 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'Invalid email address.';
if (strlen($message) < 10)                          $errors[] = 'Message must be at least 10 characters.';

if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: contact.php?error=$msg");
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'INSERT INTO Contact_Messages (FullName, Email, Message) VALUES (?,?,?)'
);
$stmt->bind_param('sss', $name, $email, $message);

if ($stmt->execute()) {
    header('Location: contact.php?success=1');
} else {
    header('Location: contact.php?error=' . urlencode('Could not send message. Please try again.'));
}
$stmt->close();