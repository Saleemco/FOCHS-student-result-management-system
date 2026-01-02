<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
echo "<h2>Debug: Teacher Manage Results</h2>";
echo "Teacher ID: $teacher_id<br>";

// Simple query to check what results exist
$query = "SELECT 
    r.id, r.student_id, r.term, r.subject,
    r.ca_score, r.exam_score, r.total_score,
    s.name as student_name, s.class_name
FROM results r
JOIN students s ON r.student_id = s.id
WHERE r.teacher_id = '$teacher_id'
ORDER BY r.term, s.class_name, s.name";

echo "<h3>Query:</h3>";
echo "<pre>" . htmlspecialchars($query) . "</pre>";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "<h3>Query Error:</h3>";
    echo mysqli_error($conn);
} else {
    $num_rows = mysqli_num_rows($result);
    echo "<h3>Results Found: $num_rows</h3>";
    
    if ($num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Student</th><th>Class</th><th>Term</th><th>Subject</th><th>CA</th><th>Exam</th><th>Total</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['student_name']}</td>";
            echo "<td>{$row['class_name']}</td>";
            echo "<td>{$row['term']}</td>";
            echo "<td>{$row['subject']}</td>";
            echo "<td>{$row['ca_score']}</td>";
            echo "<td>{$row['exam_score']}</td>";
            echo "<td>{$row['total_score']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No results found for this teacher.</p>";
        
        // Check if teacher exists
        $teacher_check = mysqli_query($conn, "SELECT * FROM teachers WHERE id = '$teacher_id'");
        if ($teacher_check && mysqli_num_rows($teacher_check) > 0) {
            $teacher = mysqli_fetch_assoc($teacher_check);
            echo "<p>Teacher exists: {$teacher['name']} - {$teacher['subject']}</p>";
        } else {
            echo "<p>Teacher NOT found in database!</p>";
        }
        
        // Check total results in database
        $total_results = mysqli_query($conn, "SELECT COUNT(*) as total FROM results");
        $total = mysqli_fetch_assoc($total_results)['total'];
        echo "<p>Total results in database: $total</p>";
    }
}

// Check database connection
echo "<h3>Database Info:</h3>";
echo "Connected to: " . $conn->host_info . "<br>";
echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "<br>";

// Check session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>