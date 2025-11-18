<?php
session_start();
include('init.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['teacher_id']) && !isset($_SESSION['parent_id'])) {
    header('Location: login.php');
    exit();
}

// Define functions
function calculateGrade($score) {
    if ($score >= 90) return 'A+';
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

// Define subject columns based on YOUR actual database structure
$subject_columns = [
    'Mathematics' => 'mathematics',
    'English Studies' => 'english_studies', 
    'Basic Science' => 'basic_science',
    'Basic Technology' => 'basic_technology',
    'Social Studies' => 'social_studies',
    'Civic Education' => 'civic_education',
    'Computer Studies' => 'computer_studies',
    'Physical Health Education' => 'physical_health_education',
    'Agricultural Science' => 'agricultural_science',
    'Yoruba' => 'yoruba',
    'Arabic' => 'arabic',
    'Islamic Studies' => 'islamic_studies',
    'Cultural Creative Arts' => 'cultural_creative_arts',
    'Home Economics' => 'home_economics',
    'Business Studies' => 'business_studies'
];

$student_id = '';
$student_data = [];
$student_results = [];
$error = '';
$success = '';

// Handle remarks submission
if (isset($_POST['submit_remarks']) && isset($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    $teacher_remarks = mysqli_real_escape_string($conn, $_POST['teacher_remarks']);
    $principal_remarks = mysqli_real_escape_string($conn, $_POST['principal_remarks']);
    
    // Check if remarks already exist for this student
    $check_remarks_sql = "SELECT id FROM report_remarks WHERE student_id = '$student_id'";
    $check_result = mysqli_query($conn, $check_remarks_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing remarks
        $update_sql = "UPDATE report_remarks SET 
                      teacher_remarks = '$teacher_remarks',
                      principal_remarks = '$principal_remarks',
                      updated_at = NOW()
                      WHERE student_id = '$student_id'";
        if (mysqli_query($conn, $update_sql)) {
            $success = "Remarks updated successfully!";
        } else {
            $error = "Error updating remarks: " . mysqli_error($conn);
        }
    } else {
        // Insert new remarks
        $insert_sql = "INSERT INTO report_remarks (student_id, teacher_remarks, principal_remarks, created_at) 
                      VALUES ('$student_id', '$teacher_remarks', '$principal_remarks', NOW())";
        if (mysqli_query($conn, $insert_sql)) {
            $success = "Remarks added successfully!";
        } else {
            $error = "Error adding remarks: " . mysqli_error($conn);
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
        
        // Build SELECT query with ALL available subject columns
        $select_fields = "id, name, roll_number, class, marks, percentage";
        
        // Add all subject columns
        foreach ($subject_columns as $column) {
            $select_fields .= ", $column";
        }
        
        // Get student results
        $results_sql = "SELECT $select_fields FROM results 
                       WHERE roll_number = '{$student_data['roll_number']}' 
                       AND class = '{$student_data['class_name']}'";
        
        $results_query = mysqli_query($conn, $results_sql);
        
        if (!$results_query) {
            $error = "Database error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($results_query) > 0) {
            $student_results = mysqli_fetch_assoc($results_query);
            
            // Get existing remarks if any
            $remarks_sql = "SELECT * FROM report_remarks WHERE student_id = '$student_id'";
            $remarks_result = mysqli_query($conn, $remarks_sql);
            $existing_remarks = [];
            if ($remarks_result && mysqli_num_rows($remarks_result) > 0) {
                $existing_remarks = mysqli_fetch_assoc($remarks_result);
            }
        } else {
            $error = "No results found for {$student_data['name']}.";
        }
    } else {
        $error = "Student not found.";
    }
}

// Calculate overall performance based on actual data
function calculateOverallPerformance($results) {
    global $subject_columns;
    
    $total_marks = 0;
    $subjects_with_marks = 0;
    $subject_grades = [];
    
    foreach ($subject_columns as $subject_name => $column) {
        if (isset($results[$column]) && is_numeric($results[$column])) {
            $marks = (int)$results[$column];
            if ($marks > 0) {
                $total_marks += $marks;
                $subjects_with_marks++;
                $grade = calculateGrade($marks);
                $subject_grades[] = [
                    'subject' => $subject_name,
                    'marks' => $marks,
                    'percentage' => calculateSubjectPercentage($marks),
                    'grade' => $grade
                ];
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
        .grade-A\+ { background-color: #10B981; color: white; }
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
        }
        @media (max-width: 480px) {
            .performance-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body class="p-2 md:p-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 no-print">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white">Student Report Card</h1>
                <p class="text-white/80 text-sm">View academic performance</p>
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
            $performance = calculateOverallPerformance($student_results);
            ?>

            <!-- Report Card -->
            <div class="report-card">
                <!-- School Header -->
                <div class="school-header">
                    <div class="print-only text-center mb-3">
                        <h1 class="text-2xl font-bold">SCHOOL MANAGEMENT SYSTEM</h1>
                        <p class="text-sm opacity-90">Official Academic Report Card</p>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2">ACADEMIC REPORT CARD</h1>
                    <p class="text-lg opacity-90">School Management System</p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div><strong>Academic Year:</strong> 2023-2024</div>
                        <div><strong>Term:</strong> Annual</div>
                        <div><strong>Date:</strong> <?php echo date('M j, Y'); ?></div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="student-info p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
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
                        
                        <!-- Total Marks Commented Out -->
                        <!--
                        <div class="performance-item p-4 bg-white rounded-lg shadow-sm">
                            <div class="text-2xl font-bold text-green-600 mb-2"><?php echo $performance['total_marks']; ?></div>
                            <div class="text-sm text-gray-600 font-medium">Total Marks</div>
                        </div>
                        -->
                        
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

                <!-- Subject-wise Results -->
                <div class="p-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-book-open text-green-500 mr-2"></i>
                        Subject Results
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full compact-table">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Subject</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Marks</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Out of</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">%</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Grade</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance['subject_grades'] as $subject_result): ?>
                                <tr class="subject-row border-b border-gray-100">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-book text-blue-400 mr-2 text-xs"></i>
                                            <span class="text-gray-800 font-medium text-sm"><?php echo $subject_result['subject']; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-full text-xs font-semibold">
                                            <?php echo $subject_result['marks']; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600 text-sm">100</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="text-gray-600 font-semibold text-sm">
                                            <?php echo $subject_result['percentage']; ?>%
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
                                                'A+' => 'Outstanding',
                                                'A' => 'Excellent',
                                                'B' => 'Very Good',
                                                'C' => 'Good',
                                                'D' => 'Satisfactory',
                                                'E' => 'Needs Improve',
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

                <!-- Remarks Section -->
                <div class="p-4 bg-white border-t">
                    <?php if (isset($_SESSION['teacher_id']) || isset($_SESSION['user_id'])): ?>
                        <!-- Editable Remarks Form for Teachers/Admins -->
                        <form method="POST" action="?student_id=<?php echo $student_id; ?>">
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
                                                : ($performance['overall_percentage'] >= 80 ? "Excellent performance! Keep up the good work." : 
                                                   ($performance['overall_percentage'] >= 60 ? "Good performance. Room for improvement." : 
                                                   ($performance['overall_percentage'] >= 40 ? "Satisfactory. Needs to work harder." : 
                                                   "Needs significant improvement."))); ?>
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
                                    <span>Save Remarks</span>
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
                                        : ($performance['overall_percentage'] >= 80 ? "Excellent performance! Keep up the good work." : 
                                           ($performance['overall_percentage'] >= 60 ? "Good performance. Room for improvement." : 
                                           ($performance['overall_percentage'] >= 40 ? "Satisfactory. Needs to work harder." : 
                                           "Needs significant improvement."))); ?>
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
        <?php endif; ?>
    </div>
</body>
</html>