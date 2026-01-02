<?php
session_start();
require_once 'init.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug header
echo "<!-- ========== REPORT CARD GENERATOR ========== -->\n";
echo "<!-- Database: srms -->\n";

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ============================================
// 1. CHECK IF PARAMETERS ARE PASSED FROM SELECTOR
// ============================================
if (isset($_GET['student_id']) && isset($_GET['term']) && isset($_GET['session'])) {
    // Parameters are coming from report_card_selector.php - SHOW REPORT IMMEDIATELY
    $selected_student = intval($_GET['student_id']);
    $selected_term = $_GET['term'];
    $selected_session = $_GET['session'];
    $show_cumulative = isset($_GET['show_cumulative']) ? 1 : 0;  // NEW: Check if cumulative was requested
    $autoprint = isset($_GET['autoprint']) ? 1 : 0;  // NEW: Check if auto-print was requested
    
    // Set flag to skip the selection form
    $skip_selection_form = true;
    
    echo "<!-- Parameters from URL: student_id=$selected_student, term=$selected_term, session=$selected_session -->\n";
    echo "<!-- Show Cumulative: $show_cumulative, Auto-print: $autoprint -->\n";
} else {
    // No parameters - show selection form
    $skip_selection_form = false;
    $show_cumulative = 0;
    $autoprint = 0;
}

// ============================================
// 2. TEACHER AUTHENTICATION
// ============================================
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];

// Set teacher type specific variables
if ($_SESSION['user_type'] === 'class_teacher') {
    $assigned_class = $_SESSION['assigned_class'] ?? '';
    $teacher_type = 'Class Teacher';
} else {
    $teacher_type = 'Subject Teacher';
    $teacher_subject = $_SESSION['teacher_subject'] ?? '';
    $teacher_classes = $_SESSION['teacher_classes'] ?? '';
    $assigned_class = '';
}

// Initialize variables
$report_data = [];

// Grading system
$grading_system = [
    'A+' => ['min' => 90, 'max' => 100, 'remark' => 'Excellent'],
    'A' => ['min' => 80, 'max' => 89, 'remark' => 'Very Good'],
    'B+' => ['min' => 70, 'max' => 79, 'remark' => 'Good'],
    'B' => ['min' => 60, 'max' => 69, 'remark' => 'Credit'],
    'C+' => ['min' => 50, 'max' => 59, 'remark' => 'Credit'],
    'C' => ['min' => 40, 'max' => 49, 'remark' => 'Pass'],
    'D' => ['min' => 33, 'max' => 39, 'remark' => 'Pass'],
    'E' => ['min' => 20, 'max' => 32, 'remark' => 'Weak'],
    'F' => ['min' => 0, 'max' => 19, 'remark' => 'Fail']
];

// Function to calculate grade from total_score
function calculateGrade($score) {
    if($score >= 90) return 'A+';
    elseif($score >= 80) return 'A';
    elseif($score >= 70) return 'B+';
    elseif($score >= 60) return 'B';
    elseif($score >= 50) return 'C+';
    elseif($score >= 40) return 'C';
    elseif($score >= 33) return 'D';
    elseif($score >= 20) return 'E';
    else return 'F';
}

// Function to get grade remark
function getGradeRemark($grade) {
    global $grading_system;
    return isset($grading_system[$grade]) ? $grading_system[$grade]['remark'] : 'No Remark';
}

// ============================================
// CUMULATIVE RESULT FUNCTIONS
// ============================================
function calculateCumulativeResult($student_id, $subject_id, $session_year, $conn) {
    $cumulative_data = [
        'total_score' => 0,
        'total_ca_score' => 0,
        'total_exam_score' => 0,
        'average_score' => 0,
        'average_grade' => '',
        'term_scores' => [],
        'remarks' => '',
        'has_all_terms' => false
    ];
    
    $terms = ['First Term', 'Second Term', 'Third Term'];
    $term_count = 0;
    
    foreach ($terms as $term) {
        $query = "SELECT r.ca_score, r.exam_score, r.total_score
                  FROM results r 
                  WHERE r.student_id = ? 
                  AND r.subject_id = ? 
                  AND r.term = ? 
                  AND r.session = ?";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("iisi", $student_id, $subject_id, $term, $session_year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $cumulative_data['term_scores'][$term] = $row;
                $cumulative_data['total_score'] += $row['total_score'];
                $cumulative_data['total_ca_score'] += $row['ca_score'];
                $cumulative_data['total_exam_score'] += $row['exam_score'];
                $term_count++;
            }
            $stmt->close();
        }
    }
    
    if ($term_count > 0) {
        $cumulative_data['average_score'] = round($cumulative_data['total_score'] / $term_count, 1);
        $cumulative_data['average_grade'] = calculateGrade($cumulative_data['average_score']);
        $cumulative_data['remarks'] = getGradeRemark($cumulative_data['average_grade']);
        $cumulative_data['has_all_terms'] = ($term_count == 3);
    }
    
    return $cumulative_data;
}

function getAllSubjectCumulative($student_id, $session_year, $conn) {
    $all_cumulative = [];
    
    $query = "SELECT DISTINCT s.id as subject_id, s.subject_name 
              FROM results r 
              JOIN subjects s ON r.subject_id = s.id 
              WHERE r.student_id = ? AND r.session = ? 
              ORDER BY s.subject_name";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $student_id, $session_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($subject = $result->fetch_assoc()) {
            $cumulative = calculateCumulativeResult($student_id, $subject['subject_id'], $session_year, $conn);
            if ($cumulative['has_all_terms']) {
                $all_cumulative[] = [
                    'subject_id' => $subject['subject_id'],
                    'subject_name' => $subject['subject_name'],
                    'cumulative' => $cumulative
                ];
            }
        }
        $stmt->close();
    }
    
    return $all_cumulative;
}

function calculateOverallCumulative($student_id, $session_year, $conn) {
    $all_cumulative = getAllSubjectCumulative($student_id, $session_year, $conn);
    
    if (empty($all_cumulative)) {
        return null;
    }
    
    $overall = [
        'total_subjects' => 0,
        'total_cumulative_score' => 0,
        'overall_average' => 0,
        'overall_grade' => '',
        'overall_remark' => '',
        'all_subjects_complete' => false
    ];
    
    $total_score = 0;
    $subject_count = 0;
    
    foreach ($all_cumulative as $subject) {
        if ($subject['cumulative']['has_all_terms']) {
            $total_score += $subject['cumulative']['average_score'];
            $subject_count++;
        }
    }
    
    if ($subject_count > 0) {
        $overall['total_subjects'] = $subject_count;
        $overall['total_cumulative_score'] = $total_score;
        $overall['overall_average'] = round($total_score / $subject_count, 1);
        $overall['overall_grade'] = calculateGrade($overall['overall_average']);
        $overall['overall_remark'] = getGradeRemark($overall['overall_grade']);
        $overall['all_subjects_complete'] = ($subject_count >= count($all_cumulative));
    }
    
    return $overall;
}

// ============================================
// 3. GENERATE REPORT DATA (IF PARAMETERS EXIST)
// ============================================
if ($skip_selection_form) {
    echo "<!-- ========== GENERATING REPORT ========== -->\n";
    echo "<!-- Student ID: $selected_student -->\n";
    echo "<!-- Term: $selected_term -->\n";
    echo "<!-- Session: $selected_session -->\n";
    echo "<!-- Show Cumulative: $show_cumulative -->\n";
    
    // Get student details
    $student_sql = "SELECT * FROM students WHERE id = ?";
    $student_stmt = $conn->prepare($student_sql);
    
    if ($student_stmt) {
        $student_stmt->bind_param("i", $selected_student);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows > 0) {
            $report_data['student'] = $student_result->fetch_assoc();
            echo "<!-- Student found: " . $report_data['student']['name'] . " -->\n";
            
            // Fetch academic results for selected term
            $results_sql = "SELECT 
                            r.id as result_id,
                            r.student_id,
                            r.subject_id,
                            r.term,
                            r.session,
                            r.ca_score,
                            r.exam_score,
                            r.total_score,
                            r.teacher_id,
                            s.subject_name
                        FROM results r
                        INNER JOIN subjects s ON r.subject_id = s.id
                        WHERE r.student_id = ? 
                        AND r.term = ? 
                        AND r.session = ?";
            
            $results_stmt = $conn->prepare($results_sql);
            
            if ($results_stmt) {
                $results_stmt->bind_param("isi", $selected_student, $selected_term, $selected_session);
                $results_stmt->execute();
                $results_result = $results_stmt->get_result();
                
                echo "<!-- Found " . $results_result->num_rows . " academic records -->\n";
                
                $report_data['academic'] = [];
                $total_scores = 0;
                $subject_count = 0;
                
                while ($row = $results_result->fetch_assoc()) {
                    $report_data['academic'][] = $row;
                    $total_scores += $row['total_score'];
                    $subject_count++;
                }
            } else {
                $report_data['academic'] = [];
            }
            
            // ============================================
            // CUMULATIVE RESULT CALCULATION (ONLY IF REQUESTED)
            // ============================================
            if ($show_cumulative) {
                echo "<!-- Calculating cumulative results for session: $selected_session -->\n";
                
                // Get cumulative results for all subjects
                $cumulative_results = getAllSubjectCumulative($selected_student, $selected_session, $conn);
                $report_data['cumulative'] = $cumulative_results;
                
                // Get overall cumulative performance
                $overall_cumulative = calculateOverallCumulative($selected_student, $selected_session, $conn);
                $report_data['overall_cumulative'] = $overall_cumulative;
                
                // DEBUG: Show cumulative data
                echo "<!-- DEBUG: Cumulative results found: " . count($cumulative_results) . " subjects -->\n";
                if (!empty($cumulative_results)) {
                    foreach($cumulative_results as $subject) {
                        echo "<!-- Subject: {$subject['subject_name']} - Has all terms: " . 
                             ($subject['cumulative']['has_all_terms'] ? 'YES' : 'NO') . 
                             " - Avg: " . $subject['cumulative']['average_score'] . " -->\n";
                    }
                }
                if ($overall_cumulative) {
                    echo "<!-- Overall cumulative average: " . $overall_cumulative['overall_average'] . " -->\n";
                    echo "<!-- Overall cumulative grade: " . $overall_cumulative['overall_grade'] . " -->\n";
                } else {
                    echo "<!-- No overall cumulative data -->\n";
                }
            } else {
                echo "<!-- Cumulative results NOT requested -->\n";
                $report_data['cumulative'] = [];
                $report_data['overall_cumulative'] = null;
            }
            
            // ============================================
            // AFFECTIVE DOMAIN QUERY
            // ============================================
            echo "<!-- Searching affective domain for student $selected_student -->\n";
            
            $affective_sql = "SELECT * FROM affective_domain WHERE student_id = ?";
            $affective_stmt = $conn->prepare($affective_sql);
            if ($affective_stmt) {
                $affective_stmt->bind_param("i", $selected_student);
                $affective_stmt->execute();
                $affective_result = $affective_stmt->get_result();
                
                if($affective_result && $affective_result->num_rows > 0) {
                    $all_affective = [];
                    while($row = $affective_result->fetch_assoc()) {
                        $all_affective[] = $row;
                    }
                    
                    $best_match = null;
                    foreach($all_affective as $record) {
                        $term_match = (strtolower(trim($record['term'])) == strtolower(trim($selected_term)));
                        $session1 = trim($record['session']);
                        $session2 = trim($selected_session);
                        $session_match = ($session1 == $session2) || 
                                        (str_replace('/', '', $session1) == str_replace('/', '', $session2)) ||
                                        (substr($session1, 0, 4) == substr($session2, 0, 4));
                        
                        if($term_match && $session_match) {
                            $best_match = $record;
                            break;
                        }
                    }
                    
                    if($best_match) {
                        $report_data['affective'] = $best_match;
                    } else {
                        $report_data['affective'] = $all_affective[0];
                    }
                } else {
                    $report_data['affective'] = null;
                }
            }
            
            // ============================================
            // PSYCHOMOTOR DOMAIN QUERY
            // ============================================
            echo "<!-- Searching psychomotor domain for student $selected_student -->\n";
            
            $psychomotor_sql = "SELECT * FROM psychomotor_domain WHERE student_id = ?";
            $psychomotor_stmt = $conn->prepare($psychomotor_sql);
            if ($psychomotor_stmt) {
                $psychomotor_stmt->bind_param("i", $selected_student);
                $psychomotor_stmt->execute();
                $psychomotor_result = $psychomotor_stmt->get_result();
                
                if($psychomotor_result && $psychomotor_result->num_rows > 0) {
                    $all_psychomotor = [];
                    while($row = $psychomotor_result->fetch_assoc()) {
                        $all_psychomotor[] = $row;
                    }
                    
                    $best_match = null;
                    foreach($all_psychomotor as $record) {
                        $term_match = (strtolower(trim($record['term'])) == strtolower(trim($selected_term)));
                        $session1 = trim($record['session']);
                        $session2 = trim($selected_session);
                        $session_match = ($session1 == $session2) || 
                                        (str_replace('/', '', $session1) == str_replace('/', '', $session2)) ||
                                        (substr($session1, 0, 4) == substr($session2, 0, 4));
                        
                        if($term_match && $session_match) {
                            $best_match = $record;
                            break;
                        }
                    }
                    
                    if($best_match) {
                        $report_data['psychomotor'] = $best_match;
                    } else {
                        $report_data['psychomotor'] = $all_psychomotor[0];
                    }
                } else {
                    $report_data['psychomotor'] = null;
                }
            }
            
            // ============================================
            // TEACHER COMMENTS QUERY
            // ============================================
            $comments_sql = "SELECT * FROM teacher_comments WHERE student_id = ? AND term = ? AND session = ?";
            $comments_stmt = $conn->prepare($comments_sql);
            if ($comments_stmt) {
                $comments_stmt->bind_param("iss", $selected_student, $selected_term, $selected_session);
                $comments_stmt->execute();
                $comments_result = $comments_stmt->get_result();
                
                if($comments_result && $comments_result->num_rows > 0) {
                    $report_data['comments'] = $comments_result->fetch_assoc();
                } else {
                    $report_data['comments'] = null;
                }
            }
            
            // ============================================
            // ATTENDANCE DATA
            // ============================================
            $report_data['attendance'] = [
                'total_days' => 90,
                'present_days' => 85,
                'absent_days' => 5,
                'percentage' => 94.4
            ];
            
            $attendance_exists = $conn->query("SHOW TABLES LIKE 'attendance_records'");
            if($attendance_exists && $attendance_exists->num_rows > 0) {
                $attendance_sql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
                    FROM attendance_records 
                    WHERE student_id = ? AND term = ? AND session = ?";
                
                $attendance_stmt = $conn->prepare($attendance_sql);
                if ($attendance_stmt) {
                    $attendance_stmt->bind_param("iss", $selected_student, $selected_term, $selected_session);
                    $attendance_stmt->execute();
                    $attendance_result = $attendance_stmt->get_result();
                    
                    if($attendance_result && $attendance_row = $attendance_result->fetch_assoc()) {
                        $report_data['attendance'] = $attendance_row;
                        if($report_data['attendance']['total_days'] > 0) {
                            $report_data['attendance']['percentage'] = round(($report_data['attendance']['present_days'] / $report_data['attendance']['total_days']) * 100, 1);
                        }
                    }
                }
            }
        }
    }
    
    echo "<!-- ========== END REPORT GENERATION ========== -->\n";
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
    <title>Report Card Generator - Student Result Management System</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .grade-A\+ { background-color: #10b981; color: white; }
        .grade-A { background-color: #3b82f6; color: white; }
        .grade-B\+ { background-color: #8b5cf6; color: white; }
        .grade-B { background-color: #f59e0b; color: white; }
        .grade-C\+ { background-color: #f59e0b; color: white; }
        .grade-C { background-color: #84cc16; color: white; }
        .grade-D { background-color: #f97316; color: white; }
        .grade-E { background-color: #ef4444; color: white; }
        .grade-F { background-color: #dc2626; color: white; }
        
        .rating-5 { background-color: #10b981; color: white; }
        .rating-4 { background-color: #3b82f6; color: white; }
        .rating-3 { background-color: #f59e0b; color: white; }
        .rating-2 { background-color: #f97316; color: white; }
        .rating-1 { background-color: #ef4444; color: white; }
        
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .report-card { box-shadow: none !important; border: 2px solid #000 !important; }
            .break-after { page-break-after: always; }
            .break-before { page-break-before: always; }
            .break-avoid { page-break-inside: avoid; }
        }
    </style>
    <?php if ($skip_selection_form && $autoprint): ?>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
    <?php endif; ?>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6 no-print">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800"><?php echo $teacher_type; ?></span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-green-50 rounded-lg p-4 mb-6">
            <div class="bg-green-500 text-white rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-crown mr-2"></i>
                <?php echo $teacher_type; ?>
            </div>
            <h3 class="font-semibold text-green-800 text-sm"><?php echo $teacher_name; ?></h3>
            <?php if ($_SESSION['user_type'] === 'class_teacher'): ?>
                <p class="text-green-600 text-xs">Class: <?php echo htmlspecialchars($assigned_class); ?></p>
            <?php else: ?>
                <p class="text-green-600 text-xs">Subject: <?php echo htmlspecialchars($teacher_subject); ?></p>
                <?php if (!empty($teacher_classes)): ?>
                    <p class="text-green-500 text-xs mt-1">Classes: <?php echo htmlspecialchars($teacher_classes); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <nav class="space-y-2">
            <?php if ($_SESSION['user_type'] === 'class_teacher'): ?>
                <a href="class_teacher_dashboard.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="class_teacher_affective.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-star"></i>
                    <span class="font-medium">Affective Assessment</span>
                </a>
                <a href="class_teacher_psychomotor.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-running"></i>
                    <span class="font-medium">Psychomotor Assessment</span>
                </a>
                <a href="class_teacher_comments.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-comment-alt"></i>
                    <span class="font-medium">Report Comments</span>
                </a>
            <?php else: ?>
                <a href="teacher_dashboard.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="teacher_manage_students.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-users"></i>
                    <span class="font-medium">View Students</span>
                </a>
            <?php endif; ?>

            <a href="report_card_selector.php" class="flex items-center space-x-3 p-3 text-red-600 bg-red-50 rounded">
                <i class="fas fa-file-pdf"></i>
                <span class="font-medium">Report Cards</span>
            </a>

            <a href="<?php echo $_SESSION['user_type'] === 'class_teacher' ? 'class_teacher_dashboard.php' : 'teacher_dashboard.php'; ?>" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-arrow-left"></i>
                <span class="font-medium">Back to Dashboard</span>
            </a>

            <a href="teacher_logout.php" class="flex items-center space-x-3 p-3 text-red-600 hover:bg-red-50 rounded">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <?php if ($skip_selection_form && !empty($report_data) && isset($report_data['student'])): ?>
            <!-- ============================================
                SHOW REPORT CARD IMMEDIATELY
            ============================================ -->
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-8 no-print">
                <div>
                    <h1 class="text-3xl font-bold text-white">Three-Domain Report Card</h1>
                    <p class="text-white/80">
                        Student: <?php echo htmlspecialchars($report_data['student']['name']); ?> | 
                        Class: <?php echo htmlspecialchars($report_data['student']['class_name']); ?>
                    </p>
                </div>
                <div class="text-white text-sm bg-blue-500/20 px-4 py-2 rounded-lg">
                    <i class="fas fa-calendar mr-2"></i>
                    <?php echo $selected_term; ?> | <?php echo $selected_session; ?>
                    <?php if ($show_cumulative): ?>
                        | <i class="fas fa-chart-line ml-2"></i> Cumulative Results Included
                    <?php endif; ?>
                </div>
            </div>

            <!-- Report Card Content -->
            <div class="report-card p-8 mb-8">
                <!-- School Header -->
                <div class="text-center mb-8 border-b-2 border-gray-300 pb-6">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">STUDENT REPORT CARD</h1>
                    <p class="text-lg text-gray-600">Three-Domain Comprehensive Assessment</p>
                    <p class="text-md text-gray-500">
                        Term: <?php echo $selected_term; ?> | 
                        Session: <?php echo $selected_session; ?>
                        <?php if ($show_cumulative): ?>
                            | <span class="text-green-600 font-bold">Cumulative Results Included</span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Student Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold text-lg text-gray-800 mb-3">Student Information</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="font-medium">Name:</span>
                                <span><?php echo htmlspecialchars($report_data['student']['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Roll Number:</span>
                                <span><?php echo htmlspecialchars($report_data['student']['roll_number']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Class:</span>
                                <span><?php echo htmlspecialchars($report_data['student']['class_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Gender:</span>
                                <span><?php echo htmlspecialchars($report_data['student']['gender'] ?? 'Not Specified'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold text-lg text-gray-800 mb-3">Attendance Summary</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="font-medium">Total Days:</span>
                                <span><?php echo isset($report_data['attendance']['total_days']) ? $report_data['attendance']['total_days'] : 'N/A'; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Present:</span>
                                <span class="text-green-600"><?php echo isset($report_data['attendance']['present_days']) ? $report_data['attendance']['present_days'] : 'N/A'; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Absent:</span>
                                <span class="text-red-600"><?php echo isset($report_data['attendance']['absent_days']) ? $report_data['attendance']['absent_days'] : 'N/A'; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Percentage:</span>
                                <span class="font-bold <?php 
                                    if(isset($report_data['attendance']['percentage'])) {
                                        echo $report_data['attendance']['percentage'] >= 80 ? 'text-green-600' : ($report_data['attendance']['percentage'] >= 60 ? 'text-yellow-600' : 'text-red-600');
                                    } else {
                                        echo 'text-gray-600';
                                    }
                                ?>">
                                    <?php echo isset($report_data['attendance']['percentage']) ? $report_data['attendance']['percentage'] . '%' : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Performance -->
                <?php if (!empty($report_data['academic'])): ?>
                <div class="mb-8 break-avoid">
                    <h3 class="font-bold text-xl text-gray-800 mb-4 border-b-2 border-blue-500 pb-2">1. COGNITIVE DOMAIN - ACADEMIC PERFORMANCE</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-300 px-4 py-2 text-left">Subject</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">CA Score</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Exam Score</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Total Score</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Grade</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_scores = 0;
                                $subject_count = 0;
                                
                                foreach ($report_data['academic'] as $result):
                                    $ca_score = $result['ca_score'];
                                    $exam_score = $result['exam_score'];
                                    $total_score = $result['total_score'];
                                    $grade = calculateGrade($total_score);
                                    $remark = getGradeRemark($grade);
                                    $total_scores += $total_score;
                                    $subject_count++;
                                ?>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2 font-medium">
                                        <?php echo htmlspecialchars($result['subject_name']); ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $ca_score; ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $exam_score; ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $total_score; ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <span class="grade-<?php echo str_replace('+', '\+', $grade); ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $grade; ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $remark; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <?php
                                if($subject_count > 0):
                                    $average_score = round($total_scores / $subject_count, 1);
                                    $overall_grade = calculateGrade($average_score);
                                    $overall_remark = getGradeRemark($overall_grade);
                                ?>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2 font-bold" colspan="3">Overall Performance</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center font-bold">
                                        Average: <?php echo $average_score; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <span class="grade-<?php echo str_replace('+', '\+', $overall_grade); ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $overall_grade; ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $overall_remark; ?></td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2 font-bold" colspan="3">Total Subjects</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center font-bold" colspan="3">
                                        <?php echo $subject_count; ?> Subjects
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-8">
                    <div class="text-center py-8 text-gray-500 bg-yellow-50 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                        <p class="text-lg">No academic results available for this term and session</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cumulative Results (ONLY IF REQUESTED) -->
                <?php if ($show_cumulative && !empty($report_data['cumulative']) && !empty($report_data['overall_cumulative'])): 
                    $has_complete_subjects = false;
                    foreach($report_data['cumulative'] as $subject) {
                        if($subject['cumulative']['has_all_terms']) {
                            $has_complete_subjects = true;
                            break;
                        }
                    }
                    
                    if ($has_complete_subjects):
                        $overall = $report_data['overall_cumulative'];
                ?>
                <div class="mb-8 mt-12 pt-8 border-t-4 border-green-500 break-before">
                    <h3 class="font-bold text-xl text-gray-800 mb-4 border-b-2 border-green-500 pb-2">
                        <i class="fas fa-chart-line text-green-500 mr-2"></i>
                        CUMULATIVE RESULTS (Average of Three Terms)
                    </h3>
                    
                    <div class="overflow-x-auto mb-8">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-green-100">
                                    <th class="border border-gray-300 px-4 py-2 text-left">Subject</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">First Term</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Second Term</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Third Term</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Cumulative Average</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Final Grade</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center">Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($report_data['cumulative'] as $subject): 
                                    if($subject['cumulative']['has_all_terms']):
                                        $cumulative = $subject['cumulative'];
                                ?>
                                <tr class="hover:bg-green-50">
                                    <td class="border border-gray-300 px-4 py-2 font-medium">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </td>
                                    
                                    <?php foreach(['First Term', 'Second Term', 'Third Term'] as $term): ?>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <?php if(isset($cumulative['term_scores'][$term])): 
                                            $term_score = $cumulative['term_scores'][$term];
                                        ?>
                                            <div class="text-sm"><?php echo $term_score['total_score']; ?></div>
                                            <div class="text-xs text-gray-500">
                                                Grade: <?php echo calculateGrade($term_score['total_score']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="border border-gray-300 px-4 py-2 text-center font-bold">
                                        <?php echo $cumulative['average_score']; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <span class="grade-<?php echo str_replace('+', '\+', $cumulative['average_grade']); ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $cumulative['average_grade']; ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <?php echo $cumulative['remarks']; ?>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                            <tfoot class="bg-green-100">
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2 font-bold" colspan="4">
                                        OVERALL CUMULATIVE PERFORMANCE
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center font-bold">
                                        <?php echo $overall['overall_average']; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center">
                                        <span class="grade-<?php echo str_replace('+', '\+', $overall['overall_grade']); ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $overall['overall_grade']; ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center font-bold">
                                        <?php echo $overall['overall_remark']; ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endif; endif; ?>

                <!-- Affective Domain -->
                <?php if (isset($report_data['affective'])): ?>
                <div class="mb-8 break-avoid">
                    <h3 class="font-bold text-xl text-gray-800 mb-4 border-b-2 border-yellow-500 pb-2">2. AFFECTIVE DOMAIN - BEHAVIOR & CHARACTER</h3>
                    
                    <?php if ($report_data['affective']): 
                        $affective = $report_data['affective'];
                        $affective_descriptions = [
                            5 => 'Excellent',
                            4 => 'Very Good',
                            3 => 'Good', 
                            2 => 'Fair',
                            1 => 'Poor'
                        ];
                    ?>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-yellow-50">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Behavioral Attribute</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Rating (1-5)</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $affective_fields = [
                                        'punctuality' => 'Punctuality',
                                        'neatness' => 'Neatness',
                                        'politeness' => 'Politeness',
                                        'initiative' => 'Initiative',
                                        'cooperation' => 'Cooperation',
                                        'leadership' => 'Leadership',
                                        'helping_others' => 'Helping Others',
                                        'emotional_stability' => 'Emotional Stability',
                                        'health' => 'Health',
                                        'attitude_to_school_work' => 'Attitude to Work',
                                        'attentiveness' => 'Attentiveness',
                                        'perseverance' => 'Perseverance',
                                        'relationship_with_teachers' => 'Relationship with Teachers'
                                    ];
                                    
                                    foreach($affective_fields as $field => $label):
                                        if(isset($affective[$field])):
                                    ?>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2 font-medium"><?php echo $label; ?></td>
                                        <td class="border border-gray-300 px-4 py-2 text-center">
                                            <span class="rating-<?php echo $affective[$field]; ?> px-3 py-1 rounded-full text-sm font-bold">
                                                <?php echo $affective[$field]; ?>
                                            </span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-center text-sm">
                                            <?php echo $affective_descriptions[$affective[$field]] ?? 'N/A'; ?>
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Psychomotor Domain -->
                <?php if (isset($report_data['psychomotor'])): ?>
                <div class="mb-8 break-avoid">
                    <h3 class="font-bold text-xl text-gray-800 mb-4 border-b-2 border-purple-500 pb-2">3. PSYCHOMOTOR DOMAIN - SKILLS & ABILITIES</h3>
                    
                    <?php if ($report_data['psychomotor']): 
                        $psychomotor = $report_data['psychomotor'];
                        $psychomotor_descriptions = [
                            5 => 'Excellent degree of observable trait',
                            4 => 'Good level of observable trait',
                            3 => 'Fair but acceptable level',
                            2 => 'Poor level of observable trait',
                            1 => 'No Observable trait'
                        ];
                    ?>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-purple-50">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Skill / Ability</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Rating (1-5)</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $psychomotor_fields = [
                                        'handwriting' => 'Handwriting',
                                        'verbal_fluency' => 'Verbal Fluency',
                                        'games' => 'Games',
                                        'sports' => 'Sports',
                                        'handling_tools' => 'Handling Tools',
                                        'drawing_painting' => 'Drawing/Painting',
                                        'musical_skills' => 'Musical Skills'
                                    ];
                                    
                                    foreach($psychomotor_fields as $field => $label):
                                        if(isset($psychomotor[$field])):
                                    ?>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2 font-medium"><?php echo $label; ?></td>
                                        <td class="border border-gray-300 px-4 py-2 text-center">
                                            <span class="rating-<?php echo $psychomotor[$field]; ?> px-3 py-1 rounded-full text-sm font-bold">
                                                <?php echo $psychomotor[$field]; ?>
                                            </span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-center text-sm">
                                            <?php echo $psychomotor_descriptions[$psychomotor[$field]] ?? 'N/A'; ?>
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Teacher's Comments and Signature -->
                <div class="mt-12 border-t-2 border-gray-300 pt-6 break-avoid">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Class Teacher's Comments:</h4>
                            <div class="h-32 border border-gray-300 rounded p-3 bg-gray-50">
                                <?php if (isset($report_data['comments']) && !empty($report_data['comments']['teacher_comments'])): ?>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($report_data['comments']['teacher_comments'])); ?></p>
                                <?php else: ?>
                                    <p class="text-gray-400 italic">No comments saved by class teacher.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Principal's Comments:</h4>
                            <div class="h-32 border border-gray-300 rounded p-3 bg-gray-50">
                                <?php if (isset($report_data['comments']) && !empty($report_data['comments']['principal_comments'])): ?>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($report_data['comments']['principal_comments'])); ?></p>
                                <?php else: ?>
                                    <p class="text-gray-400 italic">No principal comments available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                        <div class="text-center">
                            <div class="border-t border-gray-400 mt-12 pt-2">
                                <span class="font-medium">
                                    <?php echo isset($report_data['comments']) ? htmlspecialchars($report_data['comments']['teacher_signature_name']) : '________________'; ?>
                                </span>
                                <p class="text-sm text-gray-500">Class Teacher's Signature</p>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="border-t border-gray-400 mt-12 pt-2">
                                <span class="font-medium">
                                    <?php echo isset($report_data['comments']) && !empty($report_data['comments']['principal_signature_name']) ? htmlspecialchars($report_data['comments']['principal_signature_name']) : '________________'; ?>
                                </span>
                                <p class="text-sm text-gray-500">Principal's Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center no-print">
                <button onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200">
                    <i class="fas fa-print mr-2"></i>Print Report Card
                </button>
                <a href="report_card_selector.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200 ml-4">
                    <i class="fas fa-redo mr-2"></i>Generate Another
                </a>
            </div>

        <?php elseif ($skip_selection_form): ?>
            <!-- ERROR: No data found -->
            <div class="text-center py-12 bg-red-50 rounded-lg">
                <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                <h3 class="text-xl font-bold text-red-700 mb-2">No Data Found</h3>
                <p class="text-gray-600">Please check if the student has assessments for the selected term and session.</p>
                <a href="report_card_selector.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Selector
                </a>
            </div>
            
        <?php else: ?>
            <!-- ============================================
                SHOW SELECTION FORM (ONLY IF ACCESSED DIRECTLY)
            ============================================ -->
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">Three-Domain Report Card Generator</h1>
                    <p class="text-white/80">
                        <?php echo $teacher_type; ?>: <?php echo htmlspecialchars($teacher_name); ?>
                        <?php if ($_SESSION['user_type'] === 'class_teacher'): ?>
                            - Class: <?php echo htmlspecialchars($assigned_class); ?>
                        <?php else: ?>
                            - Subject: <?php echo htmlspecialchars($teacher_subject); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Selection Form -->
            <div class="card rounded-xl p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-cog text-blue-500 mr-3"></i>
                    Generate Report Card
                </h3>
                <p class="text-gray-600 mb-4">Please use the <a href="report_card_selector.php" class="text-blue-500 hover:underline">Report Card Selector</a> to generate reports.</p>
                <div class="text-center">
                    <a href="report_card_selector.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-right mr-2"></i>Go to Report Card Selector
                    </a>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <script>
        // Print functionality
        function printReport() {
            window.print();
        }
        
        // Auto-print if requested
        <?php if ($skip_selection_form && $autoprint): ?>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>