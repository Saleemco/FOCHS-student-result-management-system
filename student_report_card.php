<?php
session_start();
include('init.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['teacher_id']) && !isset($_SESSION['parent_id'])) {
    header('Location: login.php');
    exit();
}

// First, let's check what columns actually exist in your results table
$table_columns = [];
$check_columns_sql = "SHOW COLUMNS FROM results";
$columns_result = mysqli_query($conn, $check_columns_sql);

if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $table_columns[] = $column['Field'];
    }
}

// Define subject mappings but only include columns that actually exist
$subject_columns = [];
$possible_subjects = [
    'Mathematics' => 'mathematics',
    'English Studies' => 'english_studies',
    'Basic Science' => 'basic_science',
    'Basic Technology' => 'basic_technology',
    'Social Studies' => 'social_studies',
    'Civic Education' => 'civic_education',
    'Computer Studies / ICT' => 'computer_studies',
    'Physical & Health Education (PHE)' => 'physical_health_education',
    'Agricultural Science' => 'agricultural_science',
    'Yoruba' => 'yoruba',
    'Arabic' => 'arabic',
    'Islamic Religious Studies (IRS)' => 'islamic_studies',
    'Cultural & Creative Arts (CCA)' => 'cultural_creative_arts',
    'Home Economics' => 'home_economics',
    'Business Studies' => 'business_studies'
];

// Only include subjects that exist in the database
foreach ($possible_subjects as $subject_name => $column_name) {
    if (in_array($column_name, $table_columns)) {
        $subject_columns[$subject_name] = $column_name;
    }
}

// If no subjects found, show a basic set
if (empty($subject_columns)) {
    $subject_columns = [
        'Mathematics' => 'mathematics',
        'English' => 'english',
        'Science' => 'science'
    ];
}

// Define functions
function calculateGrade($score) {
    if ($score >= 90) return 'A1';
    elseif ($score >= 80) return 'A';
    elseif ($score >= 70) return 'B';
    elseif ($score >= 60) return 'C';
    elseif ($score >= 50) return 'D';
    elseif ($score >= 40) return 'E';
    else return 'F';
}

function calculateSubjectPercentage($marks) {
    return ($marks / 100) * 100;
}

function getOrdinalSuffix($number) {
    if ($number % 100 >= 11 && $number % 100 <= 13) {
        return $number . 'th';
    }
    switch ($number % 10) {
        case 1: return $number . 'st';
        case 2: return $number . 'nd';
        case 3: return $number . 'rd';
        default: return $number . 'th';
    }
}

// Psychomotor skills mapping to database columns
$psychomotor_skills_db = [
    'handwriting_rating' => ['name' => 'Handwriting', 'description' => 'Ability to write neatly and legibly'],
    'verbal_fluency_rating' => ['name' => 'Verbal Fluency', 'description' => 'Ability to express ideas clearly in speech'],
    'games_rating' => ['name' => 'Games', 'description' => 'Participation and performance in games'],
    'sports_rating' => ['name' => 'Sports', 'description' => 'Participation and performance in sports activities'],
    'handling_tools_rating' => ['name' => 'Handling Tools', 'description' => 'Skill in using tools and equipment'],
    'drawing_painting_rating' => ['name' => 'Drawing & Painting', 'description' => 'Artistic and creative abilities'],
    'musical_skills_rating' => ['name' => 'Musical Skills', 'description' => 'Musical talent and participation']
];

// Psychomotor rating scale
$psychomotor_scale = [
    5 => 'Excellent degree of observable trait',
    4 => 'Good level of observable trait', 
    3 => 'Fair but acceptable level of observable trait',
    2 => 'Poor level of observable trait',
    1 => 'No Observable trait'
];

// Affective domain traits mapping to database columns
$affective_traits_db = [
    'punctuality_rating' => ['name' => 'Punctuality', 'description' => 'Arrives on time and meets deadlines'],
    'neatness_rating' => ['name' => 'Neatness', 'description' => 'Maintains clean and organized work'],
    'politeness_rating' => ['name' => 'Politeness', 'description' => 'Shows good manners and respect'],
    'initiative_rating' => ['name' => 'Initiative', 'description' => 'Takes proactive steps without being told'],
    'cooperation_rating' => ['name' => 'Cooperation with others', 'description' => 'Works well in group settings'],
    'leadership_rating' => ['name' => 'Leadership Trait', 'description' => 'Guides and influences peers positively'],
    'helping_others_rating' => ['name' => 'Helping Others', 'description' => 'Assists classmates and teachers'],
    'emotional_stability_rating' => ['name' => 'Emotional Stability', 'description' => 'Maintains composure in various situations'],
    'health_rating' => ['name' => 'Health', 'description' => 'Maintains good physical health and hygiene'],
    'attitude_school_work_rating' => ['name' => 'Attitude to School Work', 'description' => 'Shows positive approach to learning'],
    'attentiveness_rating' => ['name' => 'Attentiveness', 'description' => 'Pays attention in class'],
    'perseverance_rating' => ['name' => 'Perseverance', 'description' => 'Shows determination in facing challenges'],
    'relationship_teachers_rating' => ['name' => 'Relationship with Teachers', 'description' => 'Maintains positive interaction with staff']
];

$student_id = '';
$student_data = [];
$student_results = [];
$subject_marks = [];
$error = '';
$success = '';
$class_position = null;
$total_students_in_class = 0;

// Handle all form submissions
if (isset($_POST['submit_remarks']) && isset($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    
    // Handle remarks
    $teacher_remarks = mysqli_real_escape_string($conn, $_POST['teacher_remarks']);
    $principal_remarks = mysqli_real_escape_string($conn, $_POST['principal_remarks']);
    
    // Handle psychomotor skills (ratings 1-5)
    $psychomotor_data = [];
    foreach($psychomotor_skills_db as $db_field => $skill_info) {
        $psychomotor_data[$db_field] = isset($_POST[$db_field]) ? (int)$_POST[$db_field] : 3;
    }
    
    // Handle affective traits
    $affective_data = [];
    foreach($affective_traits_db as $db_field => $trait_info) {
        $affective_data[$db_field] = isset($_POST[$db_field]) ? (int)$_POST[$db_field] : 3;
    }
    
    // Check if data already exists for this student
    $check_sql = "SELECT id FROM report_remarks WHERE student_id = '$student_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing data
        $update_sql = "UPDATE report_remarks SET 
                      teacher_remarks = '$teacher_remarks',
                      principal_remarks = '$principal_remarks',
                      psychomotor_data = '" . json_encode($psychomotor_data) . "',
                      affective_data = '" . json_encode($affective_data) . "',
                      updated_at = NOW()
                      WHERE student_id = '$student_id'";
        if (mysqli_query($conn, $update_sql)) {
            $success = "All assessments updated successfully!";
        } else {
            $error = "Error updating assessments: " . mysqli_error($conn);
        }
    } else {
        // Insert new data
        $insert_sql = "INSERT INTO report_remarks (student_id, teacher_remarks, principal_remarks, psychomotor_data, affective_data, created_at) 
                      VALUES ('$student_id', '$teacher_remarks', '$principal_remarks', '" . json_encode($psychomotor_data) . "', '" . json_encode($affective_data) . "', NOW())";
        if (mysqli_query($conn, $insert_sql)) {
            $success = "All assessments added successfully!";
        } else {
            $error = "Error adding assessments: " . mysqli_error($conn);
        }
    }
}

// Get all students for dropdown
$students_sql = "SELECT id, name, roll_number, class_name FROM students ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

if (!$students_result) {
    die("Error loading students: " . mysqli_error($conn));
}

// Handle form submission
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    
    // Get student details
    $student_sql = "SELECT * FROM students WHERE id = '$student_id'";
    $student_query = mysqli_query($conn, $student_sql);
    
    if ($student_query && mysqli_num_rows($student_query) > 0) {
        $student_data = mysqli_fetch_assoc($student_query);
        $class_name = $student_data['class_name'];
        
        // Get student results from main results table
        $results_sql = "SELECT * FROM results 
                       WHERE roll_number = '{$student_data['roll_number']}' 
                       AND class = '{$student_data['class_name']}'";
        
        $results_query = mysqli_query($conn, $results_sql);
        
        if (!$results_query) {
            $error = "Database error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($results_query) > 0) {
            $student_results = mysqli_fetch_assoc($results_query);
        }
        
        // Get subjects from subject_marks table where teachers upload
        $subject_marks_sql = "SELECT subject, ca_marks, exam_marks, total_marks 
                             FROM subject_marks 
                             WHERE student_id = '$student_id'";
        $subject_marks_result = mysqli_query($conn, $subject_marks_sql);
        
        $subject_marks = [];
        if ($subject_marks_result) {
            while ($row = mysqli_fetch_assoc($subject_marks_result)) {
                $subject_marks[$row['subject']] = $row;
            }
        }
        
        // ========== REAL-TIME DATA INTEGRATION ==========
        
        // 1. Get REAL attendance data from attendance_summary table
        $attendance_sql = "SELECT * FROM attendance_summary 
                          WHERE student_id = '$student_id' 
                          AND class_name = '$class_name'
                          AND term = 'First Term' 
                          AND academic_year = '2023-2024'";
        $attendance_result = mysqli_query($conn, $attendance_sql);
        
        $attendance_data = [];
        if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
            $attendance_data = mysqli_fetch_assoc($attendance_result);
        }
        
        // 2. Get REAL psychomotor data from psychomotor_assessments table
        $psychomotor_sql = "SELECT * FROM psychomotor_assessments 
                           WHERE student_id = '$student_id' 
                           AND class_name = '$class_name'
                           AND term = 'First Term' 
                           AND academic_year = '2023-2024'";
        $psychomotor_result = mysqli_query($conn, $psychomotor_sql);
        
        $psychomotor_data = [];
        if ($psychomotor_result && mysqli_num_rows($psychomotor_result) > 0) {
            $psychomotor_data = mysqli_fetch_assoc($psychomotor_result);
        }
        
        // 3. Get REAL affective data from affective_assessments table
        $affective_sql = "SELECT * FROM affective_assessments 
                         WHERE student_id = '$student_id' 
                         AND class_name = '$class_name'
                         AND term = 'First Term' 
                         AND academic_year = '2023-2024'";
        $affective_result = mysqli_query($conn, $affective_sql);
        
        $affective_data = [];
        if ($affective_result && mysqli_num_rows($affective_result) > 0) {
            $affective_data = mysqli_fetch_assoc($affective_result);
        }
        
        // Get existing remarks if any
        $remarks_sql = "SELECT * FROM report_remarks WHERE student_id = '$student_id'";
        $remarks_result = mysqli_query($conn, $remarks_sql);
        $existing_remarks = [];
        
        if ($remarks_result && mysqli_num_rows($remarks_result) > 0) {
            $existing_remarks = mysqli_fetch_assoc($remarks_result);
        }
        
        // Calculate class position
        $className = mysqli_real_escape_string($conn, $student_data['class_name']);
        $rollNumber = mysqli_real_escape_string($conn, $student_data['roll_number']);
        
        // Get all students in the class ordered by percentage
        $all_students_sql = "SELECT roll_number, percentage 
                            FROM results 
                            WHERE class = '$className' 
                            ORDER BY percentage DESC";
        $all_students_result = mysqli_query($conn, $all_students_sql);
        
        $position = 0;
        $total_students = 0;
        $current_rank = 0;
        $last_percentage = null;
        $class_position_found = false;
        
        if ($all_students_result) {
            $total_students_in_class = mysqli_num_rows($all_students_result);
            
            while ($student = mysqli_fetch_assoc($all_students_result)) {
                $current_rank++;
                
                // Handle ties - same percentage gets same rank
                if ($last_percentage !== $student['percentage']) {
                    $position = $current_rank;
                }
                
                $last_percentage = $student['percentage'];
                
                // Check if this is our target student
                if ($student['roll_number'] == $rollNumber) {
                    $class_position = $position;
                    $class_position_found = true;
                    break;
                }
            }
            
            // If student not found in ranking (shouldn't happen), set default
            if (!$class_position_found) {
                $class_position = null;
            }
        }
        
    } else {
        $error = "Student not found.";
    }
}

// Calculate overall performance based on actual data
function calculateOverallPerformance($results, $subject_marks) {
    global $subject_columns;
    
    $total_marks = 0;
    $subjects_with_marks = 0;
    $subject_grades = [];
    
    // PRIORITIZE subject_marks data (where teachers upload)
    if (!empty($subject_marks)) {
        foreach ($subject_marks as $subject_name => $marks_data) {
            $marks = $marks_data['total_marks'];
            if ($marks > 0) {
                $total_marks += $marks;
                $subjects_with_marks++;
                $grade = calculateGrade($marks);
                $subject_grades[] = [
                    'subject' => $subject_name,
                    'ca_marks' => $marks_data['ca_marks'],
                    'exam_marks' => $marks_data['exam_marks'],
                    'marks' => $marks,
                    'percentage' => calculateSubjectPercentage($marks),
                    'grade' => $grade
                ];
            }
        }
    } else {
        // Fallback to old method using results table
        foreach ($subject_columns as $subject_name => $column) {
            if (isset($results[$column]) && is_numeric($results[$column])) {
                $marks = (int)$results[$column];
                if ($marks > 0) {
                    $total_marks += $marks;
                    $subjects_with_marks++;
                    $grade = calculateGrade($marks);
                    $subject_grades[] = [
                        'subject' => $subject_name,
                        'ca_marks' => 0, // Not available in old system
                        'exam_marks' => 0, // Not available in old system
                        'marks' => $marks,
                        'percentage' => calculateSubjectPercentage($marks),
                        'grade' => $grade
                    ];
                }
            }
        }
    }
    
    $overall_percentage = $subjects_with_marks > 0 ? ($total_marks / ($subjects_with_marks * 100)) * 100 : 0;
    $overall_grade = calculateGrade($overall_percentage);
    
    return [
        'total_marks' => $total_marks,
        'subjects_count' => $subjects_with_marks,
        'overall_percentage' => round($overall_percentage, 2),
        'overall_grade' => $overall_grade,
        'subject_grades' => $subject_grades
    ];
}

// Determine if user can edit domains (teachers and admins)
$can_edit_domains = isset($_SESSION['teacher_id']) || (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin');

// Calculate performance
if (!empty($student_data) && !empty($student_results)) {
    $performance = calculateOverallPerformance($student_results, $subject_marks);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Student Report Card</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .school-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .student-info {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .subject-row:hover {
            background: #f7fafc;
        }
        .grade-A1 { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F59E0B; color: white; }
        .grade-E { background-color: #EF4444; color: white; }
        .grade-F { background-color: #DC2626; color: white; }
        .print-only {
            display: none;
        }
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            justify-items: center;
            text-align: center;
        }
        .performance-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .rating-5 { background-color: #10B981; color: white; }
        .rating-4 { background-color: #34D399; color: white; }
        .rating-3 { background-color: #FBBF24; color: white; }
        .rating-2 { background-color: #F59E0B; color: white; }
        .rating-1 { background-color: #EF4444; color: white; }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .rating-options {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .rating-option {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .psychomotor-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .psychomotor-table th {
            background-color: #4f46e5;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }
        .psychomotor-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }
        .psychomotor-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .psychomotor-table tr:hover {
            background-color: #f1f5f9;
        }
        .skill-name {
            text-align: left;
            font-weight: 600;
            padding-left: 20px;
        }
        .rating-cell {
            text-align: center;
        }
        input[type="radio"] {
            transform: scale(1.2);
            cursor: pointer;
        }
        .real-data-badge {
            background: linear-gradient(135deg, #10B981, #34D399);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }
        @media print {
            body {
                background: white !important;
                margin: 0;
                padding: 10px;
            }
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block;
            }
            .report-card {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
        }
        @media (max-width: 768px) {
            .compact-table th, .compact-table td {
                padding: 0.5rem !important;
                font-size: 0.875rem;
            }
            .performance-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
            .student-info-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .rating-options {
                flex-direction: column;
                gap: 0.25rem;
            }
            .psychomotor-table {
                font-size: 0.8rem;
            }
            .psychomotor-table th, .psychomotor-table td {
                padding: 8px 4px;
            }
        }
        @media (max-width: 480px) {
            .performance-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .student-info-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body class="p-2 md:p-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 no-print">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white">Student Report Card</h1>
                <p class="text-white/80 text-sm">Real-time Academic Performance Report</p>
            </div>
            <div class="flex items-center space-x-2">
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['teacher_id'])): ?>
                    <a href="dashboard.php" class="bg-white/20 text-white px-3 py-2 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2 text-sm">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="bg-white text-blue-600 px-3 py-2 rounded-lg font-semibold hover:bg-blue-50 transition-all duration-300 flex items-center space-x-2 text-sm">
                    <i class="fas fa-print"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>

        <!-- Student Selection Form -->
        <div class="report-card mb-4 no-print">
            <div class="p-4">
                <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-search text-blue-500 mr-2"></i>
                    Select Student
                </h3>
                <form method="GET" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                    <div class="flex-1 w-full">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Choose Student</label>
                        <select name="student_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">Select a student...</option>
                            <?php 
                            if ($students_result) {
                                mysqli_data_seek($students_result, 0);
                                while ($student = mysqli_fetch_assoc($students_result)): 
                            ?>
                                <option value="<?php echo $student['id']; ?>" 
                                        <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo $student['name'] . ' - ' . $student['roll_number'] . ' (' . $student['class_name'] . ')'; ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-600 transition duration-200 flex items-center space-x-2 text-sm w-full sm:w-auto">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="report-card p-4 mb-4 bg-red-50 border border-red-200">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-red-800">Error</h3>
                        <p class="text-red-600 text-sm"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="report-card p-4 mb-4 bg-green-50 border border-green-200">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-400 mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-green-800">Success</h3>
                        <p class="text-green-600 text-sm"><?php echo $success; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($student_data) && !empty($student_results)): ?>
            <?php
            $performance = calculateOverallPerformance($student_results, $subject_marks);
            ?>

            <!-- Report Card -->
            <div class="report-card">
                <!-- School Header -->
                <div class="school-header">
                    <div class="print-only text-center mb-3">
                        <h1 class="text-2xl font-bold">SCHOOL MANAGEMENT SYSTEM</h1>
                        <p class="text-sm opacity-90">Official Academic Report Card</p>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2">CONTINUOUS ASSESSMENT FOR FIRST TERM</h1>
                    <p class="text-lg opacity-90">School Management System</p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div><strong>Academic Year:</strong> 2023-2024</div>
                        <div><strong>Term:</strong> First Term</div>
                        <div><strong>Date:</strong> <?php echo date('M j, Y'); ?></div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="student-info p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 student-info-grid">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Student Name</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo $student_data['name']; ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Roll Number</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo $student_data['roll_number']; ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo $student_data['class_name']; ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Overall Grade</label>
                            <span class="grade-<?php echo $performance['overall_grade']; ?> px-2 py-1 rounded-full text-xs font-semibold">
                                <?php echo $performance['overall_grade']; ?>
                            </span>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Class Position</label>
                            <span class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded-full text-xs font-semibold">
                                <?php 
                                if ($class_position && $total_students_in_class > 0) {
                                    echo getOrdinalSuffix($class_position) . ' of ' . $total_students_in_class;
                                } else {
                                    echo 'Not Available';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Academic Performance Summary -->
                <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                        Performance Summary
                    </h3>
                    <div class="performance-grid">
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-blue-600 mb-2"><?php echo $performance['subjects_count']; ?></div>
                            <div class="text-sm text-gray-600 font-medium">Subjects</div>
                        </div>
                        
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-purple-600 mb-2"><?php echo number_format($performance['overall_percentage'], 1); ?>%</div>
                            <div class="text-sm text-gray-600 font-medium">Percentage</div>
                        </div>
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold grade-<?php echo $performance['overall_grade']; ?> rounded px-3 mb-2"><?php echo $performance['overall_grade']; ?></div>
                            <div class="text-sm text-gray-600 font-medium">Final Grade</div>
                        </div>
                    </div>
                </div>

                <!-- 1. ATTENDANCE RECORD - REAL DATA -->
                <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center justify-center">
                        <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                        1. ATTENDANCE RECORD
                        <span class="real-data-badge">LIVE DATA</span>
                    </h3>
                    <div class="performance-grid">
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-blue-600 mb-2">
                                <?php echo isset($attendance_data['attendance_rate']) ? number_format($attendance_data['attendance_rate'], 1) : '0.0'; ?>%
                            </div>
                            <div class="text-sm text-gray-600 font-medium">Attendance Rate</div>
                        </div>
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-green-600 mb-2">
                                <?php echo isset($attendance_data['days_present']) ? $attendance_data['days_present'] : '0'; ?>
                            </div>
                            <div class="text-sm text-gray-600 font-medium">Days Present</div>
                        </div>
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-red-600 mb-2">
                                <?php echo isset($attendance_data['days_absent']) ? $attendance_data['days_absent'] : '0'; ?>
                            </div>
                            <div class="text-sm text-gray-600 font-medium">Days Absent</div>
                        </div>
                    </div>
                    <?php if (isset($attendance_data['last_updated'])): ?>
                        <div class="text-center mt-3 text-xs text-gray-600">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Last updated: <?php echo date('M j, Y g:i A', strtotime($attendance_data['last_updated'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. COGNITIVE DOMAIN -->
                <div class="p-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-book-open text-green-500 mr-2"></i>
                        2. COGNITIVE DOMAIN
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full compact-table">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">SUBJECT</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">CA TEST (40)</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">EXAM (60)</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">TOTAL</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">GRADE</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">REMARKS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance['subject_grades'] as $subject_result): 
                                    $ca_marks = $subject_result['ca_marks'];
                                    $exam_marks = $subject_result['exam_marks'];
                                    $total = $subject_result['marks'];
                                ?>
                                <tr class="subject-row border-b border-gray-100">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-book text-blue-400 mr-2 text-xs"></i>
                                            <span class="text-gray-800 font-medium text-sm"><?php echo $subject_result['subject']; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-full text-xs font-semibold">
                                            <?php echo $ca_marks; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="bg-green-100 text-green-600 px-2 py-1 rounded-full text-xs font-semibold">
                                            <?php echo $exam_marks; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="bg-purple-100 text-purple-600 px-2 py-1 rounded-full text-xs font-semibold">
                                            <?php echo $total; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="grade-<?php echo $subject_result['grade']; ?> px-2 py-1 rounded-full text-xs font-semibold">
                                            <?php echo $subject_result['grade']; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="text-xs text-gray-600">
                                            <?php
                                            $remarks = [
                                                'A1' => 'Excellent',
                                                'A' => 'Very Good',
                                                'B' => 'Good',
                                                'C' => 'Credit',
                                                'D' => 'Pass',
                                                'E' => 'Weak',
                                                'F' => 'Fail'
                                            ];
                                            echo $remarks[$subject_result['grade']] ?? 'N/A';
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($can_edit_domains): ?>
                <!-- Editable Domains Form for Teachers/Admins -->
                <form method="POST" action="?student_id=<?php echo $student_id; ?>">
                <?php endif; ?>

                <!-- 3. PSYCHOMOTOR DOMAIN - REAL DATA -->
                <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center justify-center">
                        <i class="fas fa-running text-green-500 mr-2"></i>
                        3. PSYCHOMOTOR DOMAIN
                        <span class="real-data-badge">LIVE DATA</span>
                    </h3>
                    
                    <!-- Psychomotor Skills Table -->
                    <table class="psychomotor-table">
                        <thead>
                            <tr>
                                <th class="skill-name">PSYCHOMOTOR DOMAIN</th>
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                    <th><?php echo $i; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($psychomotor_skills_db as $db_field => $skill_info): 
                                $current_rating = isset($psychomotor_data[$db_field]) ? $psychomotor_data[$db_field] : 
                                                (isset($existing_remarks['psychomotor_data']) ? 
                                                 json_decode($existing_remarks['psychomotor_data'], true)[$db_field] ?? 3 : 3);
                            ?>
                            <tr>
                                <td class="skill-name">
                                    <div class="font-medium text-gray-800"><?php echo $skill_info['name']; ?></div>
                                    <div class="text-xs text-gray-600"><?php echo $skill_info['description']; ?></div>
                                </td>
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                <td class="rating-cell">
                                    <?php if ($can_edit_domains): ?>
                                        <input type="radio" 
                                               id="<?php echo $db_field; ?>_<?php echo $i; ?>" 
                                               name="<?php echo $db_field; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $current_rating == $i ? 'checked' : ''; ?>
                                               class="text-green-600 focus:ring-green-500">
                                    <?php else: ?>
                                        <?php if ($current_rating == $i): ?>
                                            <span class="rating-<?php echo $i; ?> px-2 py-1 rounded-full text-xs font-semibold">✓</span>
                                        <?php else: ?>
                                            <span class="text-gray-300">○</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Rating Scale -->
                    <div class="mt-4 p-3 bg-white rounded-lg shadow-sm">
                        <h4 class="font-semibold text-gray-800 mb-2 text-sm">SCALE:</h4>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 text-xs">
                            <?php foreach($psychomotor_scale as $rating => $description): ?>
                            <div class="text-center p-2 bg-gray-50 rounded">
                                <div class="font-bold text-gray-800"><?php echo $rating; ?></div>
                                <div class="text-gray-600"><?php echo $description; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (isset($psychomotor_data['assessed_at'])): ?>
                        <div class="text-center mt-3 text-xs text-gray-600">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Last assessed: <?php echo date('M j, Y g:i A', strtotime($psychomotor_data['assessed_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 4. AFFECTIVE DOMAIN - REAL DATA -->
                <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center justify-center">
                        <i class="fas fa-heart text-red-500 mr-2"></i>
                        4. AFFECTIVE DOMAIN
                        <span class="real-data-badge">LIVE DATA</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach($affective_traits_db as $db_field => $trait_info): 
                            $current_rating = isset($affective_data[$db_field]) ? $affective_data[$db_field] : 
                                            (isset($existing_remarks['affective_data']) ? 
                                             json_decode($existing_remarks['affective_data'], true)[$db_field] ?? 3 : 3);
                        ?>
                        <div class="p-3 bg-white rounded-lg shadow-sm">
                            <div class="mb-2">
                                <div class="font-medium text-gray-800 text-sm"><?php echo $trait_info['name']; ?></div>
                                <div class="text-xs text-gray-600"><?php echo $trait_info['description']; ?></div>
                            </div>
                            <div class="rating-options">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <?php if ($can_edit_domains): ?>
                                        <input type="radio" 
                                               id="<?php echo $db_field; ?>_<?php echo $i; ?>" 
                                               name="<?php echo $db_field; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $current_rating == $i ? 'checked' : ''; ?>
                                               class="text-purple-600 focus:ring-purple-500">
                                    <?php endif; ?>
                                    <label for="<?php echo $db_field; ?>_<?php echo $i; ?>" 
                                           class="text-xs <?php echo $current_rating == $i ? 'font-semibold text-purple-600' : 'text-gray-600'; ?>">
                                        <?php if (!$can_edit_domains && $current_rating == $i): ?>
                                            <span class="rating-<?php echo $i; ?> px-2 py-1 rounded-full"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <?php echo $i; ?>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Rating Scale: 1 (Poor) to 5 (Excellent)
                    </div>
                    <?php if (isset($affective_data['assessed_at'])): ?>
                        <div class="text-center mt-3 text-xs text-gray-600">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Last assessed: <?php echo date('M j, Y g:i A', strtotime($affective_data['assessed_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Remarks Section -->
                <div class="p-4 bg-white border-t">
                    <?php if ($can_edit_domains): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 text-sm">Teacher's Remarks:</h4>
                                    <textarea name="teacher_remarks" rows="3" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm no-print"
                                        placeholder="Enter teacher's remarks..."><?php echo isset($existing_remarks['teacher_remarks']) ? $existing_remarks['teacher_remarks'] : ''; ?></textarea>
                                    <div class="print-only">
                                        <p class="text-gray-600 text-sm italic">
                                            <?php echo isset($existing_remarks['teacher_remarks']) && !empty($existing_remarks['teacher_remarks']) 
                                                ? $existing_remarks['teacher_remarks'] 
                                                : "Good performance. Keep it up."; ?>
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 text-sm">Principal's Remarks:</h4>
                                    <textarea name="principal_remarks" rows="3" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm no-print"
                                        placeholder="Enter principal's remarks..."><?php echo isset($existing_remarks['principal_remarks']) ? $existing_remarks['principal_remarks'] : ''; ?></textarea>
                                    <div class="print-only">
                                        <p class="text-gray-600 text-sm italic">
                                            <?php echo isset($existing_remarks['principal_remarks']) && !empty($existing_remarks['principal_remarks']) 
                                                ? $existing_remarks['principal_remarks'] 
                                                : "Promoted to next class."; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end space-x-2 no-print">
                                <button type="submit" name="submit_remarks" 
                                    class="bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center space-x-2 text-sm">
                                    <i class="fas fa-save"></i>
                                    <span>Save All Assessments</span>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Read-only Remarks for Students/Parents -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Teacher's Remarks:</h4>
                                <p class="text-gray-600 text-sm italic">
                                    <?php echo isset($existing_remarks['teacher_remarks']) && !empty($existing_remarks['teacher_remarks']) 
                                        ? $existing_remarks['teacher_remarks'] 
                                        : "Good performance. Keep it up."; ?>
                                </p>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Principal's Remarks:</h4>
                                <p class="text-gray-600 text-sm italic">
                                    <?php echo isset($existing_remarks['principal_remarks']) && !empty($existing_remarks['principal_remarks']) 
                                        ? $existing_remarks['principal_remarks'] 
                                        : "Promoted to next class."; ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Signatures -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="border-t border-gray-300 w-32 inline-block mb-1"></div>
                            <p class="text-gray-600 text-sm">Class Teacher's Signature</p>
                        </div>
                        <div class="text-center">
                            <div class="border-t border-gray-300 w-32 inline-block mb-1"></div>
                            <p class="text-gray-600 text-sm">Principal's Signature</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-time Data Notice -->
            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4 text-center no-print">
                <div class="flex items-center justify-center space-x-2 text-blue-700">
                    <i class="fas fa-sync-alt animate-spin"></i>
                    <span class="font-semibold">Real-time Data Integration Active</span>
                </div>
                <p class="text-blue-600 text-sm mt-1">
                    This report card automatically updates with data from class teacher inputs. 
                    Changes are reflected immediately when teachers submit attendance and assessments.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>