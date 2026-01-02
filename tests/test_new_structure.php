<?php
include('init.php');

// Test the new structure
$test_query = "SELECT 
    r.id as result_id,
    s.name as student_name,
    s.class_name,
    sub.subject_name,
    r.ca_score,
    r.exam_score,
    r.total_score
FROM results_new r
JOIN students s ON r.student_id = s.id
JOIN subjects sub ON r.subject_id = sub.id
LIMIT 10";

$result = mysqli_query($conn, $test_query);

echo "<h2>Testing New Structure</h2>";
echo "<table border='1'>";
echo "<tr><th>Student</th><th>Class</th><th>Subject</th><th>CA</th><th>Exam</th><th>Total</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['student_name']}</td>";
    echo "<td>{$row['class_name']}</td>";
    echo "<td>{$row['subject_name']}</td>";
    echo "<td>{$row['ca_score']}</td>";
    echo "<td>{$row['exam_score']}</td>";
    echo "<td>{$row['total_score']}</td>";
    echo "</tr>";
}
echo "</table>";