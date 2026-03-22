<?php
// submit_request.php
// Called via fetch() (AJAX) from make-request.html
// Returns JSON: { "success": true/false, "message": "..." }

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Read JSON body (sent by fetch() as JSON) ─────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    // Fallback: try $_POST (if sent as form-encoded)
    $data = $_POST;
}

function jclean(mixed $v): string {
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

$name       = jclean($data['name']       ?? '');
$phone      = preg_replace('/\D/', '', $data['phone'] ?? '');
$hospital   = jclean($data['hospital']  ?? '');
$units      = (int)($data['units']      ?? 1);
$urgency    = jclean($data['urgency']   ?? 'Scheduled');
$patient    = jclean($data['patient']   ?? '');
$bloodGroup = jclean($data['bloodType'] ?? '');

// ── Validate ──────────────────────────────────────────────────
if (empty($name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Name and phone are required.']);
    exit;
}
if (empty($bloodGroup)) {
    echo json_encode(['success' => false, 'message' => 'Blood type is required.']);
    exit;
}
if ($units < 1 || $units > 20) {
    echo json_encode(['success' => false, 'message' => 'Units must be between 1 and 20.']);
    exit;
}

// Map urgency label to ENUM
$urgencyMap = [
    'Emergency (within 1 hour)'  => 'Emergency',
    'Urgent (within 6 hours)'    => 'Urgent',
    'Scheduled (within 24 hours)'=> 'Scheduled',
    'Emergency' => 'Emergency',
    'Urgent'    => 'Urgent',
    'Scheduled' => 'Scheduled',
];
$urgencyVal = $urgencyMap[$urgency] ?? 'Scheduled';

// Fix minus sign difference (HTML uses − U+2212, MySQL enum uses -)
$bloodGroup = str_replace('−', '-', $bloodGroup);

$db = getDB();

// ── Resolve / create Hospital row ─────────────────────────────
$hospitalID = null;
if (!empty($hospital)) {
    $stmt = $db->prepare('SELECT HospitalID FROM Hospital WHERE Name = ? LIMIT 1');
    $stmt->bind_param('s', $hospital);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $hospitalID = (int)$row['HospitalID'];
    } else {
        $stmt = $db->prepare('INSERT INTO Hospital (Name) VALUES (?)');
        $stmt->bind_param('s', $hospital);
        $stmt->execute();
        $hospitalID = $db->insert_id;
        $stmt->close();
    }
}

// ── INSERT Blood_Request ──────────────────────────────────────
// Use default BloodBankID = 1 (Bloodline Central Bank)
$bankID = 1;
$stmt = $db->prepare(
    'INSERT INTO Blood_Request
     (BloodBankID, HospitalID, BloodGroup, UnitsRequested,
      Urgency, RequesterName, RequesterPhone, PatientName)
     VALUES (?,?,?,?,?,?,?,?)'
);
$stmt->bind_param(
    'iisisss' . 's',
    $bankID, $hospitalID, $bloodGroup,
    $units, $urgencyVal, $name, $phone, $patient
);

if ($stmt->execute()) {
    $reqID = $db->insert_id;
    $stmt->close();
    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully.',
        'requestID' => $reqID,
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Could not save request. Please try again.']);
}
