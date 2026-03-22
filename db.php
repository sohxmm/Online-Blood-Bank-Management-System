<?php
// db.php — single place for all DB config
// Include this at the top of every PHP file: require_once 'db.php';

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');           // XAMPP default (leave blank)
define('DB_NAME', 'bloodline_db');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            // In production replace with a friendly error page
            die(json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
