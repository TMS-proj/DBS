<?php
$host = 'localhost';
$dbname = 'traffic_db';
$username = 'root';
$password = '';
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
die("Database connection failed: " . $conn->connect_error);
}$conn->set_charset("utf8mb4");
define('RESET_TOKEN_EXPIRY', 3600); 
function clean($data) {
return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}function generateToken($length = 32) {
return bin2hex(random_bytes($length));
}function escape($data) {
global $conn;
return $conn->real_escape_string($data);
}
?>
