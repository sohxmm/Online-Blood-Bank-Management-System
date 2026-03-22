<?php
// register.php
// Receives POST from registration.php, inserts a new donor.
// On success → redirects back to registration.php?success=1
// On failure → redirects back with ?error=<message>

require_once 'db.php';

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registration.php');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────
function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

$db = getDB();

// ── Read + sanitise fields (matching name="" attributes in HTML) ──
$fullname  = clean($_POST['fullname']  ?? '');
$email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone     = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$dob       = clean($_POST['dob']       ?? '');
$sex       = clean($_POST['sex']       ?? '');
$aadhar    = preg_replace('/\D/', '', $_POST['aadhar'] ?? '');
$bloodGrp  = clean($_POST['bloodGroup'] ?? '');
$city      = clean($_POST['city']      ?? '');
$marital    = clean($_POST['marital']    ?? '');
$occupation = clean($_POST['occupation'] ?? '');
$pincode   = preg_replace('/\D/', '', $_POST['pincode'] ?? '');
$emContact = preg_replace('/\D/', '', $_POST['emergencyContact'] ?? '');
$lastDon   = clean($_POST['lastDonation'] ?? '') ?: null;
$units     = (int)($_POST['units'] ?? 0);
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirmPassword'] ?? '';

// Collect the medical / health questionnaire as JSON
$medicalData = [];
foreach ($_POST as $k => $v) {
    if (str_starts_with($k, 'travel_') ||
        str_starts_with($k, 'allergy_') ||
        str_starts_with($k, 'cond_') ||
        str_starts_with($k, 'behavior_')) {
        $medicalData[$k] = clean((string)$v);
    }
}

// ── Server-side validation ────────────────────────────────────
$errors = [];

// Split full name into first / middle / last
$parts = array_filter(explode(' ', $fullname));
if (count($parts) < 2) {
    $errors[] = 'Please provide at least first and last name.';
}
$fname = array_shift($parts) ?? '';
$lname = array_pop($parts)   ?? $fname;
$mname = implode(' ', $parts);

if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';
if (!preg_match('/^\d{10}$/', $phone))           $errors[] = 'Phone must be 10 digits.';
if (!preg_match('/^\d{12}$/', $aadhar))          $errors[] = 'Aadhaar must be 12 digits.';
if (empty($dob))                                 $errors[] = 'Date of birth is required.';
if (empty($sex))                                 $errors[] = 'Sex is required.';
if (empty($bloodGrp))                            $errors[] = 'Blood group is required.';
if (strlen($password) < 8)                       $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

// Age check (must be 18+)
if (!empty($dob)) {
    $age = (new DateTime())->diff(new DateTime($dob))->y;
    if ($age < 18) $errors[] = 'Donor must be at least 18 years old.';
}

// Last donation gap check (90 days)
if ($lastDon) {
    $daysSince = (new DateTime())->diff(new DateTime($lastDon))->days;
    if ($daysSince < 90) $errors[] = 'Last donation must be at least 90 days ago.';
}

if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    redirect("registration.php?error=$msg");
}

// ── Check for duplicate email / aadhaar ──────────────────────
$stmt = $db->prepare('SELECT DonorID FROM Donor WHERE Email = ? OR AadharNo = ? LIMIT 1');
$stmt->bind_param('ss', $email, $aadhar);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    redirect('registration.php?error=' . urlencode('A donor with this email or Aadhaar already exists.'));
}
$stmt->close();

// ── Hash password ─────────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT);

// ── Determine blood group enum value (handle − vs - character) ─
$bloodGrpClean = str_replace('−', '-', $bloodGrp); // HTML uses minus sign

// ── INSERT Donor ──────────────────────────────────────────────
$medJson = json_encode($medicalData, JSON_UNESCAPED_UNICODE);
$sxMap   = ['male' => 'M', 'female' => 'F', 'M' => 'M', 'F' => 'F'];
$sexVal  = $sxMap[strtolower($sex)] ?? 'Other';

$stmt = $db->prepare(
    'INSERT INTO Donor
     (FName, MName, LName, DOB, Sex, Email, AadharNo, BloodGroup,
      PasswordHash, City, Pincode, EmergencyContact,
      LastDonationDate, UnitsLastDonated, MedicalNotes)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$stmt->bind_param(
    'sssssssssssssds',
    $fname, $mname, $lname, $dob, $sexVal, $email, $aadhar,
    $bloodGrpClean, $hash, $city, $pincode,
    $emContact, $lastDon, $units, $medJson
);

if (!$stmt->execute()) {
    redirect('registration.php?error=' . urlencode('Registration failed. Please try again.'));
}
$donorID = $db->insert_id;
$stmt->close();

// ── INSERT Donor_Contact ──────────────────────────────────────
$stmt = $db->prepare('INSERT INTO Donor_Contact (DonorID, PhoneNo) VALUES (?,?)');
$stmt->bind_param('is', $donorID, $phone);
$stmt->execute();
$stmt->close();

// ── INSERT Registers_At (default to BloodBankID = 1) ──────────
$bankID  = 1;
$regDate = date('Y-m-d');
$stmt = $db->prepare('INSERT INTO Registers_At (DonorID, BloodBankID, RegDate) VALUES (?,?,?)');
$stmt->bind_param('iis', $donorID, $bankID, $regDate);
$stmt->execute();
$stmt->close();

// ── First-time or Regular donor ───────────────────────────────
if ($lastDon) {
    $stmt = $db->prepare(
        'INSERT INTO Regular_Donor (DonorID, TotalDonations, LastDonationDate) VALUES (?,?,?)'
    );
    $td = max(1, $units);
    $stmt->bind_param('iis', $donorID, $td, $lastDon);
} else {
    $stmt = $db->prepare(
        'INSERT INTO First_Time_Donor (DonorID, RegistrationDate) VALUES (?,?)'
    );
    $stmt->bind_param('is', $donorID, $regDate);
}
$stmt->execute();
$stmt->close();

// ── All done! ─────────────────────────────────────────────────
redirect('registration.php?success=1');
