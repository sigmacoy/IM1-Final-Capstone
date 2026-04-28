<?php
// Default XAMPP database credentials
$host = "localhost";
$username = "root";
$password = ""; 
$database = "citu_clinic_inventory"; // Updated to match your exact database name!

// Create the connection using MySQLi
$conn = new mysqli($host, $username, $password, $database);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
} 

echo "Connected successfully to the clinic database!";
?>