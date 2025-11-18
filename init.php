<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "srms";
$port = 3306; // Change to 3307 if you switched ports

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname, $port);

// Check connection
if (!$conn) {
    // Try alternative connection
    $conn = mysqli_connect("127.0.0.1", $username, $password, $dbname, $port);
    
    if (!$conn) {
        die("Database connection failed. Please check:<br>
             1. MySQL is running in XAMPP<br>
             2. Correct port number (currently trying port $port)<br>
             3. No other services blocking the port<br>
             Error: " . mysqli_connect_error());
    }
}

// Set charset
mysqli_set_charset($conn, 'utf8');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);


?>