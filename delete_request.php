<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'request_transaction.php';

require_login();

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

try {
    $message = delete_request_transactionally($db, $requestId, request_transaction_actor());
    set_flash('flash_success', $message);
} catch (Throwable $e) {
    set_flash('flash_error', $e->getMessage());
}

$redirect = 'manage_requests.php';
if ($statusFilter !== '') {
    $redirect .= '?status=' . urlencode($statusFilter);
}

header('Location: ' . $redirect);
exit;
