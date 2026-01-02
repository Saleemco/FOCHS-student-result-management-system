<?php
session_start();
include('init.php');

// FIXED: Teacher Access Check - Allow both teacher types with proper redirects
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Check user type and redirect accordingly
if ($_SESSION['user_type'] === 'class_teacher') {
    header('Location: class_teacher_dashboard.php');
    exit();
} elseif ($_SESSION['user_type'] !== 'teacher') {
    // If not teacher or class_teacher, redirect to login
    header('Location: teacher_login.php');
    exit();
}
// Define the variables the dashboard is looking for
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_subject = $_SESSION['teacher_subject'] ?? 'Not Assigned';
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? 'teacher';
// If we get here, user is a regular teacher and can access this dashboard
// $teacher_id = $_SESSION['teacher_id'];
// $teacher_name = $_SESSION['teacher_name'];
// $teacher_email = $_SESSION['teacher_email'];
// $teacher_subject = $_SESSION['teacher_subject'];
// $teacher_classes = $_SESSION['teacher_classes'];

// // DEBUG: Check what's in session
// echo "<!-- DEBUG: Teacher Classes from session: " . htmlspecialchars($teacher_classes) . " -->";

// // Get teacher's assigned classes from class_teachers table - FIXED
// $assigned_classes_sql = "SELECT DISTINCT ct.class_name 
//                         FROM class_teachers ct
//                         WHERE ct.teacher_id = '$teacher_id' 
//                         ORDER BY ct.class_name";
                        
// $assigned_classes_result = mysqli_query($conn, $assigned_classes_sql);
// $teacher_classes_array = [];

// if ($assigned_classes_result && mysqli_num_rows($assigned_classes_result) > 0) {
//     while ($row = mysqli_fetch_assoc($assigned_classes_result)) {
//         $teacher_classes_array[] = $row['class_name'];
//     }
//     $teacher_classes = implode(', ', $teacher_classes_array);
//     // Update session with correct classes
//     $_SESSION['teacher_classes'] = $teacher_classes;
// }
// --- REPLACE FROM LINE 24 TO 45 WITH THIS ---

// 1. Get classes from session or database
$teacher_id = $_SESSION['teacher_id'];
if (!isset($_SESSION['teacher_classes']) || empty($_SESSION['teacher_classes'])) {
    $class_query = mysqli_query($conn, "SELECT classes FROM teachers WHERE id = '$teacher_id'");
    $class_data = mysqli_fetch_assoc($class_query);
    $teacher_classes = $class_data['classes'];
    $_SESSION['teacher_classes'] = $teacher_classes;
} else {
    $teacher_classes = $_SESSION['teacher_classes'];
}

// 2. Prepare the classes for the SQL query
// This turns "JSS 1, JSS 2" into ['JSS 1', 'JSS 2']
$teacher_classes_array = array_map('trim', explode(',', $teacher_classes));
$teacher_classes_array = array_filter($teacher_classes_array); // Remove any empty values

if (!empty($teacher_classes_array)) {
    $class_placeholders = "'" . implode("','", array_map(function($item) use ($conn) {
        return mysqli_real_escape_string($conn, $item);
    }, $teacher_classes_array)) . "'";
} else {
    $class_placeholders = "''";
}
// Initialize stats
$stats = [
    'total_students' => 0, 
    'total_classes' => 0,
    'male_students' => 0, 
    'female_students' => 0
];

$my_students_count = 0;
$my_students_stats = [
    'total_my_students' => 0,
    'male_my_students' => 0,
    'female_my_students' => 0
];

// Get students in teacher's assigned classes - FIXED
if (!empty($teacher_classes_array)) {
    $class_placeholders = "'" . implode("','", $teacher_classes_array) . "'";
    
    // Get students in teacher's classes
    $my_students_sql = "SELECT 
        COUNT(*) as total_my_students,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_my_students,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_my_students
        FROM students 
        WHERE class_name IN ($class_placeholders)";
    
    $my_students_result = mysqli_query($conn, $my_students_sql);
    
    if ($my_students_result) {
        $my_students_stats = mysqli_fetch_assoc($my_students_result);
        $my_students_count = $my_students_stats['total_my_students'] ?? 0;
    }
    
    // Get school-wide stats (for teacher's classes only)
    $stats_sql = "SELECT 
        COUNT(*) as total_students,
        COUNT(DISTINCT class_name) as total_classes,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
        FROM students 
        WHERE class_name IN ($class_placeholders)";
    
    $stats_result = mysqli_query($conn, $stats_sql);
    if ($stats_result) {
        $stats = mysqli_fetch_assoc($stats_result);
    }
}

// Get count of results for teacher's classes - FIXED
$my_results_count = 0;
$my_results_avg_score = 0;
if (!empty($teacher_classes_array)) {
    $class_placeholders = "'" . implode("','", $teacher_classes_array) . "'";
    
    // Get students in teacher's classes
    $students_sql = "SELECT id FROM students WHERE class_name IN ($class_placeholders)";
    $students_result = mysqli_query($conn, $students_sql);
    
    if ($students_result && mysqli_num_rows($students_result) > 0) {
        $student_ids = [];
        while ($student = mysqli_fetch_assoc($students_result)) {
            $student_ids[] = $student['id'];
        }
        
        if (!empty($student_ids)) {
            $student_ids_string = "'" . implode("','", $student_ids) . "'";
            
            // Count results for these students
            $results_sql = "SELECT 
                COUNT(*) as count,
                AVG(total_score) as avg_score
                FROM results 
                WHERE teacher_id = '$teacher_id' 
                AND student_id IN ($student_ids_string)";
            
            $results_result = mysqli_query($conn, $results_sql);
            if ($results_result) {
                $results_data = mysqli_fetch_assoc($results_result);
                $my_results_count = $results_data['count'] ?? 0;
                $my_results_avg_score = round($results_data['avg_score'] ?? 0, 1);
            }
        }
    }
}

// Get performance data for teacher's classes - FIXED
$performance_data = [
    'average_score' => 0,
    'highest_score' => 0,
    'lowest_score' => 0,
    'students_with_results' => 0
];

if (!empty($teacher_classes_array)) {
    $class_placeholders = "'" . implode("','", $teacher_classes_array) . "'";
    
    $performance_sql = "SELECT 
        COUNT(DISTINCT r.student_id) as students_with_results,
        AVG(r.total_score) as average_score,
        MAX(r.total_score) as highest_score,
        MIN(r.total_score) as lowest_score
        FROM results r 
        JOIN students s ON r.student_id = s.id
        WHERE s.class_name IN ($class_placeholders) 
        AND r.teacher_id = '$teacher_id'";
    
    $performance_result = mysqli_query($conn, $performance_sql);
    if ($performance_result && mysqli_num_rows($performance_result) > 0) {
        $performance_data = mysqli_fetch_assoc($performance_result);
        // Round scores
        $performance_data['average_score'] = round($performance_data['average_score'] ?? 0, 1);
        $performance_data['highest_score'] = round($performance_data['highest_score'] ?? 0, 1);
        $performance_data['lowest_score'] = round($performance_data['lowest_score'] ?? 0, 1);
    }
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
    <title>Teacher Dashboard</title>
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
        .nav-item {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: translateX(5px);
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .teacher-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 5px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-item:hover {
            background: #f8fafc;
        }
        .progress-bar {
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 teacher-badge rounded-full flex items-center justify-center">
                    <i class="fas fa-user-graduate text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Teacher Portal</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-blue-50 rounded-lg p-4 mb-6">
            <div class="teacher-badge rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-chalkboard-teacher mr-2"></i>
                Subject Teacher
            </div>
            <h3 class="font-semibold text-blue-800 text-sm"><?php echo htmlspecialchars($teacher_name); ?></h3>
            <p class="text-blue-600 text-xs">Subjects: <?php echo htmlspecialchars($teacher_subject); ?></p>
            <p class="text-blue-500 text-xs mt-1">Classes: 
                <?php 
                if (!empty($teacher_classes)) {
                    echo htmlspecialchars($teacher_classes);
                } else {
                    echo '<span class="text-red-500">Not assigned to any class</span>';
                }
                ?>
            </p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-blue-600 bg-blue-50 rounded">
                <i class="fas fa-tachometer-alt text-blue-600"></i>
                <span class="font-medium">Teacher Dashboard</span>
            </a>

            <a href="teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-blue-600"></i>
                <span class="font-medium">My Students</span>
                <span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                    <?php echo $my_students_count; ?>
                </span>
            </a>

            <!-- Results Dropdown Menu -->
            <div class="dropdown relative">
                <div class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded cursor-pointer">
                    <i class="fas fa-chart-bar text-blue-600"></i>
                    <span class="font-medium">Results Management</span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs ml-auto"></i>
                </div>
                <div class="dropdown-content">
                    <a href="teacher_add_results.php" class="dropdown-item text-green-600 hover:text-green-700">
                        <i class="fas fa-plus-circle text-green-500"></i>
                        <div>
                            <div class="font-medium">Add Results</div>
                            <div class="text-xs text-gray-500">Enter new student results</div>
                        </div>
                    </a>
                    <a href="teacher_manage_results.php" class="dropdown-item text-purple-600 hover:text-purple-700">
                        <i class="fas fa-edit text-purple-500"></i>
                        <div>
                            <div class="font-medium">Manage Results</div>
                            <div class="text-xs text-gray-500">View and edit existing results</div>
                            <div class="text-xs text-purple-500 font-semibold mt-1">
                                <?php echo $my_results_count; ?> uploaded
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Report Cards Link -->
            <a href="report_card_generator.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-file-pdf text-red-600"></i>
                <span class="font-medium">Report Cards</span>
                <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full">New</span>
            </a>

            <a href="view_reports.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-chart-line text-blue-600"></i>
                <span class="font-medium">View Reports</span>
            </a>

            <a href="teacher_logout.php" class="nav-item p-3 flex items-center space-x-3 text-red-600 hover:bg-red-50 rounded">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Teacher Dashboard</h1>
                <p class="text-white/80">Welcome back, <?php echo htmlspecialchars($teacher_name); ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-clock mr-2"></i>
                    <span id="current-time"><?php echo date('h:i A'); ?></span>
                </div>
                <div class="bg-white/20 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-calendar mr-2"></i>
                    <span><?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- My Students -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $my_students_count; ?></div>
                <div class="text-gray-600">My Students</div>
                <div class="mt-2 text-sm text-blue-600">
                    <i class="fas fa-male"></i> <?php echo $my_students_stats['male_my_students'] ?? 0; ?> 
                    <i class="fas fa-female ml-2"></i> <?php echo $my_students_stats['female_my_students'] ?? 0; ?>
                </div>
            </div>

            <!-- My Results -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $my_results_count; ?></div>
                <div class="text-gray-600">My Results</div>
                <div class="mt-2 text-sm text-purple-600">
                    Avg: <?php echo $my_results_avg_score; ?>%
                </div>
            </div>

            <!-- Performance -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo $performance_data['average_score']; ?>%
                </div>
                <div class="text-gray-600">Average Score</div>
                <div class="mt-2 text-sm text-green-600">
                    High: <?php echo $performance_data['highest_score']; ?>%
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-bolt text-orange-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">Quick</div>
                <div class="text-gray-600">Actions</div>
                <div class="mt-2 flex flex-col gap-2 justify-center">
                    <?php if (!empty($teacher_classes)): ?>
                    <a href="teacher_add_results.php" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        Add Results
                    </a>
                    <a href="report_card_generator.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        Report Cards
                    </a>
                    <?php else: ?>
                    <span class="text-xs text-gray-500">No classes assigned</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Class Assignment Notice -->
        <?php if (empty($teacher_classes)): ?>
        <div class="card rounded-xl p-6 mb-8 bg-yellow-50 border border-yellow-200">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800 mb-2">No Classes Assigned</h3>
                    <p class="text-yellow-700 mb-2">
                        You haven't been assigned to any classes yet. Please contact the school administrator to get assigned to classes.
                    </p>
                    <p class="text-sm text-yellow-600">
                        Once assigned, you'll be able to view students, add results, and generate report cards for your classes.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Performance Section -->
        <?php if (!empty($teacher_classes) && $my_results_count > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Performance Stats -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-trophy text-yellow-500 mr-3"></i>
                    Performance Statistics
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Average Score</span>
                        <span class="text-2xl font-bold text-green-600">
                            <?php echo $performance_data['average_score']; ?>%
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Highest Score</span>
                        <span class="text-xl font-bold text-blue-600">
                            <?php echo $performance_data['highest_score']; ?>%
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Students with Results</span>
                        <span class="text-xl font-bold text-purple-600">
                            <?php echo $performance_data['students_with_results']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Total Results Uploaded</span>
                        <span class="text-xl font-bold text-orange-600">
                            <?php echo $my_results_count; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Class Distribution -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chalkboard text-blue-500 mr-3"></i>
                    My Classes
                </h3>
                <div class="space-y-3">
                    <?php foreach ($teacher_classes_array as $class): 
                        // Get student count for this class
                        $class_stats_sql = "SELECT 
                            COUNT(*) as student_count,
                            SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                            SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
                            FROM students 
                            WHERE class_name = '$class'";
                        $class_stats_result = mysqli_query($conn, $class_stats_sql);
                        $class_stats = $class_stats_result ? mysqli_fetch_assoc($class_stats_result) : ['student_count' => 0];
                    ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chalkboard-teacher text-blue-600"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-800"><?php echo $class; ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo $class_stats['student_count']; ?> students
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-blue-600">
                                <i class="fas fa-male"></i> <?php echo $class_stats['male_count'] ?? 0; ?>
                            </div>
                            <div class="text-xs text-pink-600">
                                <i class="fas fa-female"></i> <?php echo $class_stats['female_count'] ?? 0; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teacher Information -->
        <div class="card rounded-xl p-6">
            <div class="flex items-start space-x-4">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-graduate text-blue-600 text-2xl"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Welcome to Teacher Portal!</h2>
                    <p class="text-gray-600 mb-4">
                        As a <span class="font-semibold text-blue-600">Subject Teacher</span> 
                        <?php if (!empty($teacher_classes)): ?>
                        for <span class="font-semibold text-green-600"><?php echo htmlspecialchars($teacher_classes); ?></span>, 
                        <?php endif; ?>
                        you can:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center space-x-3 mb-2">
                                <i class="fas fa-users text-blue-500 text-xl"></i>
                                <span class="font-semibold text-blue-700">My Students</span>
                            </div>
                            <p class="text-sm text-gray-600">View and manage students in your assigned classes</p>
                            <div class="mt-2 text-blue-600 font-semibold">
                                <?php echo $my_students_count; ?> students
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="flex items-center space-x-3 mb-2">
                                <i class="fas fa-chart-bar text-green-500 text-xl"></i>
                                <span class="font-semibold text-green-700">Results</span>
                            </div>
                            <p class="text-sm text-gray-600">Add and manage academic results for your subjects</p>
                            <div class="mt-2 text-green-600 font-semibold">
                                <?php echo $my_results_count; ?> results uploaded
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="flex items-center space-x-3 mb-2">
                                <i class="fas fa-file-pdf text-purple-500 text-xl"></i>
                                <span class="font-semibold text-purple-700">Report Cards</span>
                            </div>
                            <p class="text-sm text-gray-600">Generate comprehensive report cards for students</p>
                            <div class="mt-2 text-purple-600 font-semibold">
                                PDF Generation
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('current-time').textContent = timeString;
        }

        setInterval(updateTime, 60000);
        updateTime();

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(event.target)) {
                    dropdown.querySelector('.dropdown-content').style.display = 'none';
                }
            });
        });

        // Toggle dropdown on click
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                if (e.target.closest('.nav-item')) {
                    const content = this.querySelector('.dropdown-content');
                    content.style.display = content.style.display === 'block' ? 'none' : 'block';
                }
            });
        });
    </script>
</body>
</html>