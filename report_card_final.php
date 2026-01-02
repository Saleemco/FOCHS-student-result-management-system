<?php
// report_card_final.php
session_start();
include('init.php');

// Check if we have all required parameters
if (!isset($_GET['student_id']) || !isset($_GET['term']) || !isset($_GET['session'])) {
    // Redirect back to selector if missing parameters
    header('Location: report_card_selector.php');
    exit();
}

$student_id = intval($_GET['student_id']);
$term = $_GET['term'];
$session = $_GET['session'];
$show_cumulative = isset($_GET['show_cumulative']) ? 1 : 0;
$autoprint = isset($_GET['autoprint']) ? 1 : 0;

// Get student info
$student_query = "SELECT * FROM students WHERE id = $student_id";
$student_result = $conn->query($student_query);
$student = $student_result->fetch_assoc();

// Get results
$results_query = "SELECT * FROM results 
                 WHERE student_id = $student_id 
                 AND term = '$term' 
                 AND session = '$session'";
$results = $conn->query($results_query);

// Auto-print script
if ($autoprint): ?>
<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 1000);
    };
</script>
<?php endif; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Card - <?php echo $student['name']; ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
        }
        body { font-family: Arial, sans-serif; }
        .report-card { border: 2px solid #000; padding: 20px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="report-card">
        <h1>STUDENT REPORT CARD</h1>
        
        <!-- Student Info -->
        <div>
            <h2>Student Information</h2>
            <p><strong>Name:</strong> <?php echo $student['name']; ?></p>
            <p><strong>Class:</strong> <?php echo $student['class_name']; ?></p>
            <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
            <p><strong>Term:</strong> <?php echo $term; ?></p>
            <p><strong>Session:</strong> <?php echo $session; ?></p>
        </div>
        
        <!-- Cognitive Results -->
        <?php if ($results->num_rows > 0): ?>
        <h2>Academic Results (Cognitive Domain)</h2>
        <table>
            <tr>
                <th>Subject</th>
                <th>CA Score</th>
                <th>Exam Score</th>
                <th>Total</th>
                <th>Grade</th>
                <th>Remark</th>
            </tr>
            <?php while ($row = $results->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['subject']; ?></td>
                <td><?php echo $row['ca_score']; ?></td>
                <td><?php echo $row['exam_score']; ?></td>
                <td><?php echo $row['total_score']; ?></td>
                <td><?php echo $row['grade']; ?></td>
                <td><?php echo $row['remark']; ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
        <p>No academic results found for this term.</p>
        <?php endif; ?>
        
        <!-- Cumulative Results - ONLY if checkbox was checked -->
        <?php if ($show_cumulative): ?>
        <h2>Cumulative Performance</h2>
        <?php
        $cumulative_query = "SELECT term, session, 
                            AVG(total_score) as avg_score,
                            AVG(average) as avg_percentage
                            FROM results 
                            WHERE student_id = $student_id 
                            GROUP BY term, session 
                            ORDER BY session DESC, 
                            FIELD(term, 'Third Term', 'Second Term', 'First Term')";
        $cumulative_result = $conn->query($cumulative_query);
        
        if ($cumulative_result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Term</th>
                <th>Session</th>
                <th>Average Score</th>
                <th>Average Percentage</th>
            </tr>
            <?php while ($row = $cumulative_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['term']; ?></td>
                <td><?php echo $row['session']; ?></td>
                <td><?php echo round($row['avg_score'], 2); ?></td>
                <td><?php echo round($row['avg_percentage'], 2); ?>%</td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
        <p>No cumulative data available.</p>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="no-print" style="margin-top: 20px;">
            <button onclick="window.print()">Print Report</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>
</body>
</html>