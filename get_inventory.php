<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once 'db.php';

$db = getDB();
$query = strtoupper(trim((string) ($_GET['q'] ?? '')));

$result = $db->query(
    'SELECT BloodGroup, UnitsAvailable AS TotalUnits
     FROM   Blood_Inventory
     ORDER  BY FIELD(BloodGroup,"O+","A+","B+","AB+","O-","A-","B-","AB-")'
);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $db->error]);
    exit;
}

$inventory = [];
while ($row = $result->fetch_assoc()) {
    $bloodGroup = (string) $row['BloodGroup'];
    if ($query !== '' && stripos($bloodGroup, $query) === false) {
        continue;
    }

    $inventory[] = [
        'bloodGroup' => $bloodGroup,
        'units'      => (int)$row['TotalUnits'],
        'critical'   => (int)$row['TotalUnits'] <= 4,
    ];
}

$statsRes = $db->query('SELECT SUM(UnitsAvailable) AS total FROM Blood_Inventory');
$statsRow = $statsRes->fetch_assoc();

$reqRes = $db->query("SELECT COUNT(*) AS cnt FROM Blood_Request WHERE Status='Pending'");
$reqRow = $reqRes->fetch_assoc();

echo json_encode([
    'query'           => $query,
    'inventory'       => $inventory,
    'totalUnits'      => (int)($statsRow['total'] ?? 0),
    'bloodTypes'      => count($inventory),
    'criticalTypes'   => count(array_filter($inventory, fn($i) => $i['critical'])),
    'pendingRequests' => (int)($reqRow['cnt'] ?? 0),
    'updatedAt'       => date('c'),
]);
