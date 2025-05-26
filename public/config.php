<?php
// Read database configuration from environment variables
$servername = getenv('DB_HOSTNAME');
$username   = getenv('DB_USERNAME');
$password   = getenv('DB_PASSWORD');
$dbname     = getenv('DB_NAME');

if (!$servername || !$username || !$dbname) { // Password can be empty for some MySQL setups, though not recommended
    error_log("Database configuration environment variables not fully set.");
    die("Database configuration error. Please contact administrator.");
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Database Connection failed to host '".$servername."': " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}
?>