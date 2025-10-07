<?php
// Database configuration
$servername = "localhost";  // Usually 'localhost' for local development
$username = "root";         // Default MySQL username
$password = "";             // Default MySQL password (empty for local XAMPP/WAMP)
$dbname = "hrproject";    // Name of your database (create it in phpMyAdmin if needed)

// Create connection using MySQLi
$db_conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$db_conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8 for proper encoding
mysqli_set_charset($db_conn, "utf8");

// Start session for user login management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "Database connected successfully!";  // Optional: Remove in production
?>