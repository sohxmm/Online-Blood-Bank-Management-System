<?php
// db.php — single place for all DB config
// Include this at the top of every PHP file: require_once 'db.php';

require_once 'schema_compat.php';

define('DB_HOST', getenv('BLOODLINE_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('BLOODLINE_DB_USER') ?: 'root');
define('DB_PASS', getenv('BLOODLINE_DB_PASS') ?: '');
define('DB_NAME', getenv('BLOODLINE_DB_NAME') ?: 'bloodline_db');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('Bloodline database connection failed: ' . $conn->connect_error);
            http_response_code(503);
            die(json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']));
        }
        $conn->set_charset('utf8mb4');
        ensure_hybrid_schema($conn);
    }
    return $conn;
}
