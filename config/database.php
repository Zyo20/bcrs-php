<?php
// Database Configuration
$host = 'localhost';
$dbname = 'bcrs';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set timezone to Manila (UTC+8)
    $db->exec("SET time_zone = '+08:00'");

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>