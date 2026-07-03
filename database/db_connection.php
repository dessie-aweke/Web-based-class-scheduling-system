<?php
$servername = "localhost";  // XAMPP default server
$username = "root";  // Default MySQL username
$password = "";  // No password for root by default
$dbname = "DMU_class_scheduling";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Uncomment to check connection
// echo "Database connected successfully!";
?>
