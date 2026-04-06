<?php

require_once 'db.php';

const AUTH_SESSION_NAME = 'bloodline_session';
const AUTH_SESSION_TIMEOUT = 1800;
const REMEMBER_ME_COOKIE = 'bloodline_remember';
const REMEMBER_ME_LIFETIME = 2592000;
const THEME_COOKIE = 'bloodline_theme';
const LAST_PAGE_COOKIE = 'bloodline_last_page';
const LEGACY_REMEMBER_EMAIL_COOKIE = 'bloodline_remember_email';

function is_https_request(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function cookie_options(int $expires = 0, bool $httpOnly = true): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ];
}

function clear_cookie(string $name, bool $httpOnly = true): void
{
    setcookie($name, '', cookie_options(time() - 3600, $httpOnly));
    unset($_COOKIE[$name]);
}

function ensure_auth_tables(mysqli $db): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $db->query(
        'CREATE TABLE IF NOT EXISTS Donor_Remember_Token (
            TokenID INT AUTO_INCREMENT PRIMARY KEY,
            DonorID INT NOT NULL,
            Selector CHAR(24) NOT NULL UNIQUE,
            TokenHash CHAR(64) NOT NULL,
            ExpiresAt DATETIME NOT NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            LastUsedAt DATETIME NULL,
            UserAgent VARCHAR(255) NULL,
            INDEX idx_donor_token_donor (DonorID),
            INDEX idx_donor_token_expires (ExpiresAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $ensured = true;
}

function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params(cookie_options());
    session_start();

    if (!isset($_SESSION['session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['session_initialized'] = true;
    }

    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > AUTH_SESSION_TIMEOUT) {
        expire_session();
    }

    if (!is_logged_in()) {
        restore_persistent_login();
    }

    $_SESSION['last_activity'] = time();
}

function expire_session(): void
{
    clear_session_state();
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
    $_SESSION['flash_error'] = 'Your session expired. Please sign in again.';
}

function clear_session_state(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        clear_cookie(session_name());
    }
}

function destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    clear_session_state();
    session_destroy();
}

function set_flash(string $key, string $message): void
{
    $_SESSION[$key] = $message;
}

function pull_flash(string $key): string
{
    $message = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);

    return is_string($message) ? $message : '';
}

function is_logged_in(): bool
{
    return !empty($_SESSION['donor_id']);
}

function csrf_token(): string
{
    boot_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($sessionToken)
        && is_string($token)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

function require_csrf_token(?string $token, string $redirect, bool $expectsJson = false): void
{
    boot_session();

    if (validate_csrf_token($token)) {
        return;
    }

    if ($expectsJson) {
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'Your form session expired. Please refresh and try again.',
        ]);
        exit;
    }

    set_flash('flash_error', 'Your form session expired. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

function fetch_donor_auth_row(int $donorId): ?array
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT DonorID, FName, LName, BloodGroup
         FROM Donor
         WHERE DonorID = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $donorId);
    $stmt->execute();
    $donor = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $donor;
}

function log_in_donor(array $donor, bool $remembered = false): void
{
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['donor_id'] = (int) $donor['DonorID'];
    $_SESSION['donor_name'] = trim($donor['FName'] . ' ' . $donor['LName']);
    $_SESSION['blood_group'] = $donor['BloodGroup'];
    $_SESSION['logged_in_at'] = time();
    $_SESSION['remembered_login'] = $remembered;

    if ($remembered) {
        unset($_SESSION['flash_error']);
    }
}

function parse_remember_cookie(): ?array
{
    $rawCookie = $_COOKIE[REMEMBER_ME_COOKIE] ?? '';

    if (!is_string($rawCookie) || strpos($rawCookie, ':') === false) {
        return null;
    }

    [$selector, $validator] = explode(':', $rawCookie, 2);

    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        return null;
    }

    return [$selector, $validator];
}

function forget_persistent_login_token(): void
{
    $cookieParts = parse_remember_cookie();

    if ($cookieParts !== null) {
        [$selector] = $cookieParts;

        $db = getDB();
        ensure_auth_tables($db);

        $stmt = $db->prepare('DELETE FROM Donor_Remember_Token WHERE Selector = ?');
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $stmt->close();
    }

    clear_cookie(REMEMBER_ME_COOKIE);
    clear_cookie(LEGACY_REMEMBER_EMAIL_COOKIE);
}

function issue_persistent_login(int $donorId): void
{
    $db = getDB();
    ensure_auth_tables($db);
    forget_persistent_login_token();

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_ME_LIFETIME);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $stmt = $db->prepare(
        'INSERT INTO Donor_Remember_Token (DonorID, Selector, TokenHash, ExpiresAt, UserAgent)
         VALUES (?,?,?,?,?)'
    );
    $stmt->bind_param('issss', $donorId, $selector, $tokenHash, $expiresAt, $userAgent);
    $stmt->execute();
    $stmt->close();

    $cookieValue = $selector . ':' . $validator;
    setcookie(
        REMEMBER_ME_COOKIE,
        $cookieValue,
        cookie_options(time() + REMEMBER_ME_LIFETIME)
    );
    $_COOKIE[REMEMBER_ME_COOKIE] = $cookieValue;
}

function restore_persistent_login(): void
{
    $cookieParts = parse_remember_cookie();

    if ($cookieParts === null) {
        return;
    }

    [$selector, $validator] = $cookieParts;
    $db = getDB();
    ensure_auth_tables($db);

    $stmt = $db->prepare(
        'SELECT DonorID, TokenHash, ExpiresAt
         FROM Donor_Remember_Token
         WHERE Selector = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $tokenRow = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (
        !$tokenRow
        || strtotime((string) $tokenRow['ExpiresAt']) < time()
        || !hash_equals((string) $tokenRow['TokenHash'], hash('sha256', $validator))
    ) {
        forget_persistent_login_token();
        return;
    }

    $donor = fetch_donor_auth_row((int) $tokenRow['DonorID']);

    if (!$donor) {
        forget_persistent_login_token();
        return;
    }

    log_in_donor($donor, true);
    issue_persistent_login((int) $donor['DonorID']);

    $updateStmt = $db->prepare(
        'UPDATE Donor_Remember_Token
         SET LastUsedAt = NOW()
         WHERE Selector = ?'
    );
    $currentCookie = parse_remember_cookie();
    $currentSelector = $currentCookie[0] ?? '';
    $updateStmt->bind_param('s', $currentSelector);
    $updateStmt->execute();
    $updateStmt->close();
}

function require_login(string $redirect = 'login.php'): void
{
    boot_session();

    if (is_logged_in()) {
        return;
    }

    if (empty($_SESSION['flash_error'])) {
        set_flash('flash_error', 'Please sign in to continue.');
    }

    header('Location: ' . $redirect);
    exit;
}

function clear_preference_cookies(): void
{
    clear_cookie(THEME_COOKIE, false);
    clear_cookie(LAST_PAGE_COOKIE, false);
}

function log_out_donor(): void
{
    boot_session();
    forget_persistent_login_token();
    clear_preference_cookies();
    destroy_session();
}
