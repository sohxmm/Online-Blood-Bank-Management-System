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

$totalUnits = null;
$statsRes = $db->query('SELECT fn_exp6_total_units_available() AS total');
if ($statsRes) {
    $statsRow = $statsRes->fetch_assoc() ?: [];
    $totalUnits = (int) ($statsRow['total'] ?? 0);
    $statsRes->free();
} else {
    $statsRes = $db->query('SELECT SUM(UnitsAvailable) AS total FROM Blood_Inventory');
    $statsRow = $statsRes ? ($statsRes->fetch_assoc() ?: []) : [];
    $totalUnits = (int) ($statsRow['total'] ?? 0);
    if ($statsRes) {
        $statsRes->free();
    }
}

$pendingRequests = null;
$reqRes = $db->query('SELECT fn_exp6_pending_request_count() AS cnt');
if ($reqRes) {
    $reqRow = $reqRes->fetch_assoc() ?: [];
    $pendingRequests = (int) ($reqRow['cnt'] ?? 0);
    $reqRes->free();
} else {
    $reqRes = $db->query("SELECT COUNT(*) AS cnt FROM Blood_Request WHERE Status='Pending'");
    $reqRow = $reqRes ? ($reqRes->fetch_assoc() ?: []) : [];
    $pendingRequests = (int) ($reqRow['cnt'] ?? 0);
    if ($reqRes) {
        $reqRes->free();
    }
}

echo json_encode([
    'query'           => $query,
    'inventory'       => $inventory,
    'totalUnits'      => $totalUnits,
    'bloodTypes'      => count($inventory),
    'criticalTypes'   => count(array_filter($inventory, fn($i) => $i['critical'])),
    'pendingRequests' => $pendingRequests,
    'updatedAt'       => date('c'),
]);
