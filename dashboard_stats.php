<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once 'auth.php';
require_once 'db.php';

boot_session();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please sign in again.',
    ]);
    exit;
}

function is_valid_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt !== false && $dt->format('Y-m-d') === $value;
}

$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

if ($from === '' || $to === '') {
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-30 days'));
}

if (!is_valid_date($from) || !is_valid_date($to) || $from > $to) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid date range.',
    ]);
    exit;
}

$db = getDB();
$donorId = (int) $_SESSION['donor_id'];

$phoneStmt = $db->prepare('SELECT PhoneNo FROM Donor_Contact WHERE DonorID = ? LIMIT 1');
$phoneStmt->bind_param('i', $donorId);
$phoneStmt->execute();
$phoneRow = $phoneStmt->get_result()->fetch_assoc() ?: [];
$phoneStmt->close();

$phone = (string) ($phoneRow['PhoneNo'] ?? '');

if ($phone === '') {
    echo json_encode([
        'success' => true,
        'from' => $from,
        'to' => $to,
        'totalRequests' => 0,
        'totalUnits' => 0,
        'pending' => 0,
        'fulfilled' => 0,
        'cancelled' => 0,
    ]);
    exit;
}

$stmt = $db->prepare(
    'SELECT Status, COUNT(*) AS total, COALESCE(SUM(UnitsRequested), 0) AS units
     FROM Blood_Request
     WHERE RequesterPhone = ?
       AND DATE(RequestDate) BETWEEN ? AND ?
     GROUP BY Status'
);
$stmt->bind_param('sss', $phone, $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = [
    'Pending' => 0,
    'Fulfilled' => 0,
    'Cancelled' => 0,
];
$totalRequests = 0;
$totalUnits = 0;

foreach ($rows as $row) {
    $status = (string) ($row['Status'] ?? '');
    $count = (int) ($row['total'] ?? 0);
    $units = (int) ($row['units'] ?? 0);

    if (isset($counts[$status])) {
        $counts[$status] = $count;
    }
    $totalRequests += $count;
    $totalUnits += $units;
}

echo json_encode([
    'success' => true,
    'from' => $from,
    'to' => $to,
    'totalRequests' => $totalRequests,
    'totalUnits' => $totalUnits,
    'pending' => $counts['Pending'],
    'fulfilled' => $counts['Fulfilled'],
    'cancelled' => $counts['Cancelled'],
]);
