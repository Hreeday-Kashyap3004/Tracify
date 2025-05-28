<?php
// includes/db.php
define('DB_SERVER', 'localhost'); // Or your DB server address
define('DB_USERNAME', 'root');    // Your MySQL username (default is often root)
define('DB_PASSWORD', '');        // Your MySQL password (default is often empty)
define('DB_NAME', 'lost_found_db');

/* Attempt to connect to MySQL database */
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch(PDOException $e) {
    // Don't show detailed errors in production! Log them instead.
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Start session management (place this here as db connection is needed early)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>