<?php
// check_database.php
echo "<h3>Database Diagnostic</h3>";

// Database connection
$connection = mysql_connect("localhost", "root", "");
if (!$connection) {
    die("Connection failed: " . mysql_error());
}

// Select database
$db_selected = mysql_select_db("student_management", $connection);
if (!$db_selected) {
    echo "Database doesn't exist. Let me create it...<br>";
    
    // Create database
    if (mysql_query("CREATE DATABASE student_management", $connection)) {
        echo "Database created successfully!<br>";
        mysql_select_db("student_management", $connection);
    } else {
        die("Failed to create database: " . mysql_error());
    }
}

// Check if tables exist and create them if missing
$tables = ['classes', 'students', 'users'];
foreach ($tables as $table) {
    $result = mysql_query("SHOW TABLES LIKE '$table'");
    if (mysql_num_rows($result) == 0) {
        echo "Table '$table' doesn't exist. Creating...<br>";
        
        if ($table == 'classes') {
            $sql = "CREATE TABLE classes (
                class_id INT AUTO_INCREMENT PRIMARY KEY,
                class_name VARCHAR(100) NOT NULL UNIQUE,
                section VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } elseif ($table == 'students') {
            $sql = "CREATE TABLE students (
                student_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                class_id INT,
                roll_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        }
        
        if (mysql_query($sql)) {
            echo "Table '$table' created successfully!<br>";
        } else {
            echo "Error creating table '$table': " . mysql_error() . "<br>";
        }
    } else {
        echo "Table '$table' exists. Checking data...<br>";
        
        // Count records in table
        $count_result = mysql_query("SELECT COUNT(*) as count FROM $table");
        $count_row = mysql_fetch_assoc($count_result);
        echo "Records in $table: " . $count_row['count'] . "<br>";
        
        // Show sample data
        if ($table == 'classes') {
            $data_result = mysql_query("SELECT * FROM $table LIMIT 5");
            echo "Sample classes: ";
            while ($row = mysql_fetch_assoc($data_result)) {
                echo $row['class_name'] . ", ";
            }
            echo "<br>";
        }
    }
}

mysql_close($connection);
echo "<h4>Diagnostic complete!</h4>";
?>