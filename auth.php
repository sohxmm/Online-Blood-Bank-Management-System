<?php

const AUTH_SESSION_NAME = 'bloodline_session';
const AUTH_SESSION_TIMEOUT = 1800;
const REMEMBER_EMAIL_COOKIE = 'bloodline_remember_email';
const REMEMBER_EMAIL_LIFETIME = 2592000;

function is_https_request(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function auth_cookie_options(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params(auth_cookie_options());
    session_start();

    if (!isset($_SESSION['session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['session_initialized'] = true;
    }

    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > AUTH_SESSION_TIMEOUT) {
        expire_session();
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
        setcookie(session_name(), '', auth_cookie_options(time() - 3600));
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

function log_in_donor(array $donor): void
{
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['donor_id'] = (int) $donor['DonorID'];
    $_SESSION['donor_name'] = trim($donor['FName'] . ' ' . $donor['LName']);
    $_SESSION['blood_group'] = $donor['BloodGroup'];
    $_SESSION['logged_in_at'] = time();
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

function log_out_donor(): void
{
    boot_session();
    destroy_session();
}

function remember_login_email(?string $email): void
{
    $cleanEmail = filter_var((string) $email, FILTER_VALIDATE_EMAIL);

    if ($cleanEmail === false) {
        clear_remembered_email();
        return;
    }

    setcookie(
        REMEMBER_EMAIL_COOKIE,
        $cleanEmail,
        auth_cookie_options(time() + REMEMBER_EMAIL_LIFETIME)
    );
    $_COOKIE[REMEMBER_EMAIL_COOKIE] = $cleanEmail;
}

function clear_remembered_email(): void
{
    setcookie(REMEMBER_EMAIL_COOKIE, '', auth_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_EMAIL_COOKIE]);
}

function get_remembered_email(): string
{
    $email = $_COOKIE[REMEMBER_EMAIL_COOKIE] ?? '';
    $validatedEmail = filter_var($email, FILTER_VALIDATE_EMAIL);

    return $validatedEmail === false ? '' : $validatedEmail;
}
