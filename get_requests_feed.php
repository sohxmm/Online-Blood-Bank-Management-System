<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once 'auth.php';
require_once 'db.php';

boot_session();

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(20, (int) ($_GET['limit'] ?? 5)));
$offset = ($page - 1) * $limit;

$allowedStatuses = ['Pending', 'Fulfilled', 'Cancelled'];
$filterActive = in_array($statusFilter, $allowedStatuses, true);

$db = getDB();

if ($filterActive) {
    $countStmt = $db->prepare('SELECT COUNT(*) AS total FROM Blood_Request WHERE Status = ?');
    $countStmt->bind_param('s', $statusFilter);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $stmt = $db->prepare(
        'SELECT br.ReqID AS RequestID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status,
                br.RequestDate, br.RequesterName, br.RequesterPhone, br.PatientName,
                h.Name AS HospitalName
         FROM Blood_Request br
         LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
         WHERE br.Status = ?
         ORDER BY br.RequestDate DESC, br.ReqID DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('sii', $statusFilter, $limit, $offset);
} else {
    $countResult = $db->query('SELECT COUNT(*) AS total FROM Blood_Request')->fetch_assoc();

    $stmt = $db->prepare(
        'SELECT br.ReqID AS RequestID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status,
                br.RequestDate, br.RequesterName, br.RequesterPhone, br.PatientName,
                h.Name AS HospitalName
         FROM Blood_Request br
         LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
         ORDER BY br.RequestDate DESC, br.ReqID DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = (int) ($countResult['total'] ?? 0);
$loadedUntil = $offset + count($rows);

echo json_encode([
    'success' => true,
    'items' => $rows,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'hasMore' => $loadedUntil < $total,
]);
