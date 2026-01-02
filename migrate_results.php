<?php
// migrate_results.php
session_start();
include('init.php');

// Only allow admins or teachers to run this
if (!isset($_SESSION['teacher_id'])) {
    die("Access denied. Please login first.");
}

echo "<h2>Database Migration Tool</h2>";
echo "<p><strong>IMPORTANT:</strong> Backup your database before proceeding!</p>";

// Check if migration already ran
$check_migration = mysqli_query($conn, "SHOW TABLES LIKE 'subjects'");
if (mysqli_num_rows($check_migration) > 0) {
    echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; margin: 10px 0;'>
            <strong>Warning:</strong> It looks like the new tables already exist. 
            Running this again may create duplicate data.
          </div>";
}

if (isset($_POST['run_migration'])) {
    runMigration($conn);
}

function runMigration($conn) {
    echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; margin: 10px 0;'>";
    echo "<h3>Starting Migration...</h3>";
    
    // Step 1: Create subjects table
    echo "<p><strong>Step 1:</strong> Creating subjects table...</p>";
    $create_subjects = "CREATE TABLE IF NOT EXISTS subjects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        subject_code VARCHAR(20) UNIQUE NOT NULL,
        subject_name VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    )";
    
    if (mysqli_query($conn, $create_subjects)) {
        echo "✓ Subjects table created<br>";
    } else {
        echo "✗ Error creating subjects table: " . mysqli_error($conn) . "<br>";
        return;
    }
    
    // Step 2: Create new results table
    echo "<p><strong>Step 2:</strong> Creating new results table...</p>";
    $create_results = "CREATE TABLE IF NOT EXISTS results_new (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        term ENUM('First Term', 'Second Term', 'Third Term') NOT NULL,
        session YEAR NOT NULL,
        ca_score DECIMAL(5,2) DEFAULT 0,
        exam_score DECIMAL(5,2) DEFAULT 0,
        total_score DECIMAL(5,2) DEFAULT 0,
        teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_result (student_id, subject_id, term, session)
    )";
    
    if (mysqli_query($conn, $create_results)) {
        echo "✓ New results table created<br>";
    } else {
        echo "✗ Error creating results table: " . mysqli_error($conn) . "<br>";
        return;
    }
    
    // Step 3: Insert subjects
    echo "<p><strong>Step 3:</strong> Inserting subjects...</p>";
    $subjects = [
        'mathematics' => 'Mathematics',
        'english_studies' => 'English Studies', 
        'basic_science' => 'Basic Science',
        'basic_technology' => 'Basic Technology',
        'social_studies' => 'Social Studies',
        'civic_education' => 'Civic Education',
        'computer_studies' => 'Computer Studies',
        'physical_health_education' => 'Physical & Health Education',
        'agricultural_science' => 'Agricultural Science',
        'yoruba' => 'Yoruba',
        'arabic' => 'Arabic',
        'islamic_studies' => 'Islamic Studies',
        'cultural_creative_arts' => 'Cultural & Creative Arts',
        'home_economics' => 'Home Economics',
        'business_studies' => 'Business Studies'
    ];
    
    $subjects_inserted = 0;
    foreach ($subjects as $code => $name) {
        $insert = "INSERT IGNORE INTO subjects (subject_code, subject_name) VALUES ('$code', '$name')";
        if (mysqli_query($conn, $insert)) {
            if (mysqli_affected_rows($conn) > 0) {
                $subjects_inserted++;
            }
        }
    }
    echo "✓ $subjects_inserted subjects inserted<br>";
    
    // Step 4: Migrate data
    echo "<p><strong>Step 4:</strong> Migrating existing results...</p>";
    
    // Get all existing results
    $old_results = mysqli_query($conn, "SELECT * FROM results");
    $total_old = mysqli_num_rows($old_results);
    $migrated_count = 0;
    
    echo "Found $total_old existing result records<br>";
    
    if ($total_old > 0) {
        while ($row = mysqli_fetch_assoc($old_results)) {
            foreach ($subjects as $subject_code => $subject_name) {
                $total_score = $row[$subject_code] ?? 0;
                
                // Only process if this subject has a score
                if ($total_score > 0) {
                    // Get CA and exam scores from JSON or estimate
                    $ca_marks = json_decode($row['ca_marks'] ?? '{}', true) ?? [];
                    $exam_marks = json_decode($row['exam_marks'] ?? '{}', true) ?? [];
                    
                    $ca_score = $ca_marks[$subject_code] ?? round($total_score * 0.4);
                    $exam_score = $exam_marks[$subject_code] ?? ($total_score - $ca_score);
                    
                    // Get subject ID
                    $subject_query = mysqli_query($conn, "SELECT id FROM subjects WHERE subject_code = '$subject_code'");
                    $subject_data = mysqli_fetch_assoc($subject_query);
                    $subject_id = $subject_data['id'];
                    
                    // Insert into new results table
                    $student_id = $row['student_id'];
                    $term = mysqli_real_escape_string($conn, $row['term']);
                    $session = $row['session'] ?? '2024';
                    $teacher_id = $row['teacher_id'];
                    
                    $insert_sql = "INSERT IGNORE INTO results_new 
                        (student_id, subject_id, term, session, ca_score, exam_score, total_score, teacher_id) 
                        VALUES ('$student_id', '$subject_id', '$term', '$session', '$ca_score', '$exam_score', '$total_score', '$teacher_id')";
                    
                    if (mysqli_query($conn, $insert_sql)) {
                        if (mysqli_affected_rows($conn) > 0) {
                            $migrated_count++;
                        }
                    }
                }
            }
        }
        echo "✓ $migrated_count subject results migrated<br>";
    }
    
    // Step 5: Verify migration
    echo "<p><strong>Step 5:</strong> Verifying migration...</p>";
    $verify_new = mysqli_query($conn, "SELECT COUNT(*) as count FROM results_new");
    $new_count = mysqli_fetch_assoc($verify_new)['count'];
    
    $verify_subjects = mysqli_query($conn, "SELECT COUNT(*) as count FROM subjects");
    $subjects_count = mysqli_fetch_assoc($verify_subjects)['count'];
    
    echo "✓ New results table has: $new_count records<br>";
    echo "✓ Subjects table has: $subjects_count subjects<br>";
    
    echo "<div style='background: #bbdefb; padding: 15px; margin: 10px 0; border: 1px solid #2196f3;'>";
    echo "<h4>Migration Summary:</h4>";
    echo "<ul>";
    echo "<li>Subjects: $subjects_count</li>";
    echo "<li>Migrated Results: $migrated_count</li>";
    echo "<li>Old Records Processed: $total_old</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #c8e6c9; padding: 15px; margin: 10px 0; border: 1px solid #4caf50;'>";
    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Test the new structure</strong> by updating your teacher_manage_results.php to use the new tables</li>";
    echo "<li><strong>Verify data accuracy</strong> by checking a few student records</li>";
    echo "<li><strong>Once confirmed working</strong>, you can rename tables:</li>";
    echo "<pre>RENAME TABLE results TO results_backup, results_new TO results;</pre>";
    echo "<li>Update all your PHP files to use the new structure</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #17a2b8; padding: 15px; margin: 10px 0; }
        button { background: #dc3545; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="warning">
        <h3>⚠️ IMPORTANT WARNING</h3>
        <p>This migration will:</p>
        <ul>
            <li>Create new database tables</li>
            <li>Copy your existing data to the new structure</li>
            <li>NOT delete your original data</li>
        </ul>
        <p><strong>Please backup your database before proceeding!</strong></p>
    </div>

    <div class="info">
        <h3>What This Migration Does:</h3>
        <p>Converts your hybrid database structure (individual columns + JSON) to a clean, normalized structure:</p>
        <ul>
            <li>Creates <code>subjects</code> table</li>
            <li>Creates <code>results_new</code> table with proper relationships</li>
            <li>Migrates all existing data</li>
            <li>Preserves your original data in the old tables</li>
        </ul>
    </div>

    <form method="POST">
        <p>
            <label>
                <input type="checkbox" name="confirm_backup" required>
                I have backed up my database and understand the risks
            </label>
        </p>
        <button type="submit" name="run_migration" onclick="return confirm('Are you absolutely sure you want to run the migration?')">
            🚀 Run Migration
        </button>
    </form>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa;">
        <h3>After Migration:</h3>
        <p>Test your updated <code>teacher_manage_results.php</code> with this simplified query:</p>
        <pre>
$query = "SELECT 
    r.id as result_id,
    r.student_id,
    r.term,
    r.ca_score,
    r.exam_score, 
    r.total_score,
    s.name as student_name,
    s.roll_number,
    s.class_name,
    sub.subject_name
FROM results_new r
JOIN students s ON r.student_id = s.id
JOIN subjects sub ON r.subject_id = sub.id
WHERE r.teacher_id = '$teacher_id'";
        </pre>
    </div>
</body>
</html>