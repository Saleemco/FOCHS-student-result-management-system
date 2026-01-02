<?php
// debug_results.php
session_start();
include('init.php');

echo "<h2>Results Debug Information</h2>";

// Check session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

$teacher_id = $_SESSION['teacher_id'] ?? 0;

// Check if teacher exists in database
$teacher_check = mysqli_query($conn, "SELECT * FROM teachers WHERE id = '$teacher_id'");
echo "<h3>Teacher Database Check:</h3>";
if ($teacher_check && mysqli_num_rows($teacher_check) > 0) {
    $teacher = mysqli_fetch_assoc($teacher_check);
    echo "✓ Teacher found: " . $teacher['name'] . " (ID: $teacher_id)<br>";
} else {
    echo "✗ Teacher NOT found in database with ID: $teacher_id<br>";
}

// Check total results in database
$total_results = mysqli_query($conn, "SELECT COUNT(*) as total FROM results");
$total_count = mysqli_fetch_assoc($total_results)['total'];
echo "<p>Total results in database: $total_count</p>";

// Check results for this teacher
$teacher_results = mysqli_query($conn, "SELECT COUNT(*) as total FROM results WHERE teacher_id = '$teacher_id'");
$teacher_count = mysqli_fetch_assoc($teacher_results)['total'];
echo "<p>Results for teacher ID $teacher_id: $teacher_count</p>";

// Show sample results for this teacher
echo "<h3>Sample Results for This Teacher:</h3>";
$sample_query = "SELECT 
    r.id, r.student_id, r.subject_id, r.term, r.teacher_id,
    s.name as student_name, s.class_name,
    sub.subject_name
FROM results r
JOIN students s ON r.student_id = s.id
JOIN subjects sub ON r.subject_id = sub.id
WHERE r.teacher_id = '$teacher_id'
LIMIT 10";

$sample_results = mysqli_query($conn, $sample_query);

if ($sample_results && mysqli_num_rows($sample_results) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Student</th><th>Class</th><th>Subject</th><th>Term</th><th>Teacher ID</th></tr>";
    while ($row = mysqli_fetch_assoc($sample_results)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['student_name']}</td>";
        echo "<td>{$row['class_name']}</td>";
        echo "<td>{$row['subject_name']}</td>";
        echo "<td>{$row['term']}</td>";
        echo "<td>{$row['teacher_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No results found for teacher ID: $teacher_id<br>";
    echo "Query used: " . htmlspecialchars($sample_query) . "<br>";
    
    // Check if there are any results at all
    $any_results = mysqli_query($conn, "SELECT * FROM results LIMIT 5");
    if (mysqli_num_rows($any_results) > 0) {
        echo "<h4>Some results exist in database (showing first 5):</h4>";
        echo "<table border='1' cellpadding='5'>";
        $first = true;
        while ($row = mysqli_fetch_assoc($any_results)) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($row) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check students table
echo "<h3>Students Sample:</h3>";
$students_sample = mysqli_query($conn, "SELECT id, name, roll_number, class_name FROM students LIMIT 5");
if ($students_sample && mysqli_num_rows($students_sample) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Roll Number</th><th>Class</th></tr>";
    while ($row = mysqli_fetch_assoc($students_sample)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['class_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>