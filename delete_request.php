<?php
require_once 'auth.php';
require_once 'db.php';

boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_requests.php');
    exit;
}

require_csrf_token($_POST['csrf_token'] ?? null, 'manage_requests.php');

$requestId = (int) ($_POST['request_id'] ?? 0);
$statusFilter = trim($_POST['status_filter'] ?? '');

if ($requestId < 1) {
    set_flash('flash_error', 'Invalid request selected for deletion.');
    header('Location: manage_requests.php');
    exit;
}

$db = getDB();
// DELETE query used in the lab demo.
$stmt = $db->prepare(
    'DELETE FROM Blood_Request
     WHERE ReqID = ?'
);
$stmt->bind_param('i', $requestId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    set_flash('flash_success', 'Blood request deleted successfully.');
} else {
    set_flash('flash_error', 'No blood request was deleted.');
}

$stmt->close();

$redirect = 'manage_requests.php';
if ($statusFilter !== '') {
    $redirect .= '?status=' . urlencode($statusFilter);
}

header('Location: ' . $redirect);
exit;
