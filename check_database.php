<?php
session_start();
include('init.php');

echo "<h2>🔍 Database Diagnostic</h2>";

// 1. Check if results table exists
$tables = mysqli_query($conn, "SHOW TABLES");
echo "<h3>📊 Existing Tables:</h3>";
while ($table = mysqli_fetch_array($tables)) {
    echo "<p>• " . $table[0] . "</p>";
}

// 2. Check results table structure
echo "<h3>📋 Results Table Structure:</h3>";
$structure = mysqli_query($conn, "DESCRIBE results");
if ($structure) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($structure)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Results table doesn't exist!</p>";
}

// 3. Check if any data exists
echo "<h3>📈 Sample Data Check:</h3>";
$sample_data = mysqli_query($conn, "SELECT * FROM results LIMIT 3");
if ($sample_data && mysqli_num_rows($sample_data) > 0) {
    echo "<p style='color: green;'>✅ Data exists in results table</p>";
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($sample_data)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ No data in results table</p>";
}

// 4. Check students table
echo "<h3>👨‍🎓 Students Table Check:</h3>";
$students = mysqli_query($conn, "SELECT id, name, roll_number FROM students LIMIT 5");
if ($students && mysqli_num_rows($students) > 0) {
    echo "<p style='color: green;'>✅ Students table has data</p>";
    while ($student = mysqli_fetch_assoc($students)) {
        echo "<p>• " . $student['name'] . " (ID: " . $student['id'] . ", Roll: " . $student['roll_number'] . ")</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No students found</p>";
}
?>