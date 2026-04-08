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
$unitsRequested = (int) ($_POST['units_requested'] ?? 0);
$urgency = trim($_POST['urgency'] ?? '');
$status = trim($_POST['status'] ?? '');
$statusFilter = trim($_POST['status_filter'] ?? '');

$allowedUrgencies = ['Emergency', 'Urgent', 'Scheduled'];
$allowedStatuses = ['Pending', 'Fulfilled', 'Cancelled'];

if ($requestId < 1) {
    set_flash('flash_error', 'Invalid request selected for update.');
    header('Location: manage_requests.php');
    exit;
}

if ($unitsRequested < 1 || $unitsRequested > 20) {
    set_flash('flash_error', 'Units requested must be between 1 and 20.');
    header('Location: manage_requests.php?edit=' . $requestId);
    exit;
}

if (!in_array($urgency, $allowedUrgencies, true) || !in_array($status, $allowedStatuses, true)) {
    set_flash('flash_error', 'Please choose a valid urgency and status.');
    header('Location: manage_requests.php?edit=' . $requestId);
    exit;
}

$db = getDB();
// UPDATE query used in the lab demo.
$stmt = $db->prepare(
    'UPDATE Blood_Request
     SET UnitsRequested = ?, Urgency = ?, Status = ?
     WHERE ReqID = ?'
);
$stmt->bind_param('issi', $unitsRequested, $urgency, $status, $requestId);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    set_flash('flash_success', 'Blood request updated successfully.');
} else {
    set_flash('flash_error', 'Could not update the blood request.');
}

$stmt->close();

$redirect = 'manage_requests.php';
if ($statusFilter !== '') {
    $redirect .= '?status=' . urlencode($statusFilter);
}

header('Location: ' . $redirect);
exit;
