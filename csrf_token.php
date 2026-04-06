<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once 'auth.php';

boot_session();

echo json_encode([
    'csrfToken' => csrf_token(),
]);
