<?php
// submit_request.php
// Called via fetch() (AJAX) from make-request.html
// Returns JSON: { "success": true/false, "message": "..." }

header('Content-Type: application/json; charset=utf-8');
require_once 'auth.php';
require_once 'db.php';

boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Read JSON body (sent by fetch() as JSON) ─────────────────
$raw = trim((string) file_get_contents('php://input'));
$data = null;
if ($raw !== '') {
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }
}

if (!is_array($data)) {
    // Fallback: try $_POST (if sent as form-encoded)
    $data = $_POST;
}
if (!is_array($data)) {
    $data = [];
}

require_csrf_token($data['csrf_token'] ?? null, 'make-request.html', true);

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
if (strlen($name) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Name must be at least 2 characters.']);
    exit;
}
if (empty($phone) || strlen($phone) < 10 || strlen($phone) > 15) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phone number must be 10 to 15 digits.']);
    exit;
}
if ($units < 1 || $units > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Units must be between 1 and 20.']);
    exit;
}
if (strlen($hospital) > 120) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Hospital name is too long.']);
    exit;
}
if (strlen($patient) > 120) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Patient name is too long.']);
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

$bloodGroup = str_replace("\u{2212}", '-', $bloodGroup);
$bloodGroup = strtoupper(str_replace(' ', '', $bloodGroup));

$allowedBloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-'];
if (!in_array($bloodGroup, $allowedBloodGroups, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select a valid blood type.']);
    exit;
}

$db = getDB();

// ── Resolve / create Hospital row ─────────────────────────────
$hospitalID = null;
if (!empty($hospital)) {
    $stmt = $db->prepare('SELECT HospitalID FROM Hospital WHERE Name = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
    $stmt->bind_param('s', $hospital);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $hospitalID = (int)$row['HospitalID'];
    } else {
        $stmt = $db->prepare('INSERT INTO Hospital (Name) VALUES (?)');
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
            exit;
        }
        $stmt->bind_param('s', $hospital);
        $stmt->execute();
        $hospitalID = $db->insert_id;
        $stmt->close();
    }
}

// ── INSERT Blood_Request ──────────────────────────────────────
// CREATE query 
// Use default BloodBankID = 1 (Bloodline Central Bank)
$bankID = 1;
$stmt = $db->prepare(
    'INSERT INTO Blood_Request
     (BloodBankID, HospitalID, BloodGroup, UnitsRequested,
      Urgency, RequesterName, OwnerName, RequesterPhone, PatientName, ReqDate)
     VALUES (?,?,?,?,?,?,?,?,?, NOW())'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param(
    'iisissss' . 's',
    $bankID, $hospitalID, $bloodGroup,
    $units, $urgencyVal, $name, $name, $phone, $patient
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save request. Please try again.']);
}
