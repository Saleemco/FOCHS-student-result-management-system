<?php
include('init.php');

// Create affective_domain table
$affective_table = "CREATE TABLE IF NOT EXISTS affective_domain (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    term ENUM('First Term', 'Second Term', 'Third Term') NOT NULL,
    session VARCHAR(20) NOT NULL,
    punctuality TINYINT(1) DEFAULT 3,
    neatness TINYINT(1) DEFAULT 3,
    politeness TINYINT(1) DEFAULT 3,
    initiative TINYINT(1) DEFAULT 3,
    cooperation TINYINT(1) DEFAULT 3,
    leadership TINYINT(1) DEFAULT 3,
    helping_others TINYINT(1) DEFAULT 3,
    emotional_stability TINYINT(1) DEFAULT 3,
    health TINYINT(1) DEFAULT 3,
    attitude_to_school_work TINYINT(1) DEFAULT 3,
    attentiveness TINYINT(1) DEFAULT 3,
    perseverance TINYINT(1) DEFAULT 3,
    relationship_with_teachers TINYINT(1) DEFAULT 3,
    overall_rating DECIMAL(3,1) DEFAULT 3.0,
    assessed_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (assessed_by) REFERENCES teachers(id),
    UNIQUE KEY unique_assessment (student_id, term, session)
)";

// Create psychomotor_domain table
$psychomotor_table = "CREATE TABLE IF NOT EXISTS psychomotor_domain (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    term ENUM('First Term', 'Second Term', 'Third Term') NOT NULL,
    session VARCHAR(20) NOT NULL,
    handwriting TINYINT(1) DEFAULT 3,
    verbal_fluency TINYINT(1) DEFAULT 3,
    games TINYINT(1) DEFAULT 3,
    sports TINYINT(1) DEFAULT 3,
    handling_tools TINYINT(1) DEFAULT 3,
    drawing_painting TINYINT(1) DEFAULT 3,
    musical_skills TINYINT(1) DEFAULT 3,
    overall_rating DECIMAL(3,1) DEFAULT 3.0,
    assessed_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (assessed_by) REFERENCES teachers(id),
    UNIQUE KEY unique_assessment (student_id, term, session)
)";

// Create attendance_records table
$attendance_table = "CREATE TABLE IF NOT EXISTS attendance_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    term ENUM('First Term', 'Second Term', 'Third Term') NOT NULL,
    session VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late') NOT NULL,
    recorded_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (recorded_by) REFERENCES teachers(id)
)";

// Execute table creation
if (mysqli_query($conn, $affective_table)) {
    echo "✅ Affective domain table created successfully!<br>";
} else {
    echo "❌ Error creating affective domain table: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $psychomotor_table)) {
    echo "✅ Psychomotor domain table created successfully!<br>";
} else {
    echo "❌ Error creating psychomotor domain table: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $attendance_table)) {
    echo "✅ Attendance records table created successfully!<br>";
} else {
    echo "❌ Error creating attendance records table: " . mysqli_error($conn) . "<br>";
}

echo "<h3>Domain tables setup completed!</h3>";
?>