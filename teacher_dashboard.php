<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Get teacher stats
$teacher_id = $_SESSION['teacher_id'];
$teacher_classes = $_SESSION['teacher_classes'];

// Convert classes string to array and count students
$classes_array = explode(',', $teacher_classes);
$class_conditions = implode("','", array_map('trim', $classes_array));

// Initialize counts
$students_count = 0;
$results_count = 0;
$new_students_today = 0;
$recent_results = 0;

// Count total students in teacher's classes
$students_count_sql = "SELECT COUNT(*) as total FROM students WHERE class_name IN ('$class_conditions')";
$students_result = mysqli_query($conn, $students_count_sql);
if ($students_result) {
    $students_count = mysqli_fetch_assoc($students_result)['total'];
    
    // Count new students today
    $new_students_sql = "SELECT COUNT(*) as total FROM students WHERE class_name IN ('$class_conditions') AND DATE(created_at) = CURDATE()";
    $new_students_result = mysqli_query($conn, $new_students_sql);
    if ($new_students_result) {
        $new_students_today = mysqli_fetch_assoc($new_students_result)['total'];
    }
} else {
    error_log("Students count query failed: " . mysqli_error($conn));
    $students_count = 0;
}

// Count results for teacher's classes
$results_count_sql = "SELECT COUNT(*) as total FROM results WHERE class IN ('$class_conditions')";
$results_result = mysqli_query($conn, $results_count_sql);
if ($results_result) {
    $results_count = mysqli_fetch_assoc($results_result)['total'];
    
    // Count recent results
    $recent_results_sql = "SELECT COUNT(*) as total FROM results WHERE class IN ('$class_conditions') AND DATE(created_at) = CURDATE()";
    $recent_results_result = mysqli_query($conn, $recent_results_sql);
    if ($recent_results_result) {
        $recent_results = mysqli_fetch_assoc($recent_results_result)['total'];
    }
} else {
    error_log("Results count query failed: " . mysqli_error($conn));
    $results_count = 0;
}

// Get recent students
$recent_students_sql = "SELECT name, roll_number, class_name FROM students WHERE class_name IN ('$class_conditions') ORDER BY id DESC LIMIT 5";
$recent_students = mysqli_query($conn, $recent_students_sql);
if (!$recent_students) {
    error_log("Recent students query failed: " . mysqli_error($conn));
    $recent_students = false;
}

// Get student count by class for the teacher
$class_distribution = [];
foreach ($classes_array as $class) {
    $class = trim($class);
    $class_count_sql = "SELECT COUNT(*) as count FROM students WHERE class_name = '$class'";
    $class_count_result = mysqli_query($conn, $class_count_sql);
    if ($class_count_result) {
        $class_count = mysqli_fetch_assoc($class_count_result)['count'];
        $class_distribution[$class] = $class_count;
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
            background: rgba(102, 126, 234, 0.1);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        /* New Dropdown Styles */
        .dropdown-group:hover .dropdown-trigger {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .dropdown-group:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-group:hover .fa-chevron-down {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            min-width: 200px;
        }
        
        .quick-action-item {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .quick-action-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(3px);
        }
        
        /* Grade Colors */
        .grade-A-plus { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F59E0B; color: white; }
        .grade-E { background-color: #EF4444; color: white; }
        .grade-F { background-color: #DC2626; color: white; }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-indigo-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-chalkboard-teacher text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Teacher</span>
            </div>
        </div>
<a href="student_report_card.php" class="nav-item p-3 flex items-center space-x-3 text-purple-600 hover:bg-purple-50 rounded">
    <i class="fas fa-file-alt"></i>
    <span class="font-medium">Report Cards</span>
</a>
        <!-- Teacher Info -->
        <div class="bg-indigo-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-indigo-800 text-sm"><?php echo $_SESSION['teacher_name']; ?></h3>
            <p class="text-indigo-600 text-xs"><?php echo $_SESSION['teacher_subject']; ?></p>
            <p class="text-indigo-500 text-xs mt-1"><?php echo $_SESSION['teacher_classes']; ?></p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-indigo-600 bg-indigo-50 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <!-- Students Dropdown -->
            <div class="nav-item dropdown-group relative">
                <div class="dropdown-trigger p-3 flex items-center justify-between cursor-pointer rounded hover:bg-blue-50 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-graduate text-blue-600"></i>
                        <span class="font-medium text-gray-700">Students</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm transition-transform duration-200"></i>
                </div>
                <div class="dropdown-menu absolute left-0 right-0 mt-1 bg-white rounded-lg shadow-xl border border-gray-200 opacity-0 invisible transition-all duration-200 transform -translate-y-2 z-50">
                    <a href="teacher_add_students.php" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-t-lg transition duration-150 border-b border-gray-100">
                        <i class="fas fa-user-plus mr-3 text-blue-500"></i>
                        Add Students
                    </a>
                    <a href="teacher_manage_students.php" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-b-lg transition duration-150">
                        <i class="fas fa-list mr-3 text-blue-500"></i>
                        Manage Students
                    </a>
                </div>
            </div>

            <!-- Results Dropdown -->
            <div class="nav-item dropdown-group relative">
                <div class="dropdown-trigger p-3 flex items-center justify-between cursor-pointer rounded hover:bg-green-50 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar text-green-600"></i>
                        <span class="font-medium text-gray-700">Results</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm transition-transform duration-200"></i>
                </div>
                <div class="dropdown-menu absolute left-0 right-0 mt-1 bg-white rounded-lg shadow-xl border border-gray-200 opacity-0 invisible transition-all duration-200 transform -translate-y-2 z-50">
                    <a href="teacher_add_results.php" class="block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-600 rounded-t-lg transition duration-150 border-b border-gray-100">
                        <i class="fas fa-plus-circle mr-3 text-green-500"></i>
                        Add Results
                    </a>
                    <a href="teacher_manage_results.php" class="block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-600 rounded-b-lg transition duration-150">
                        <i class="fas fa-list mr-3 text-green-500"></i>
                        Manage Results
                    </a>
                </div>
            </div>

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
                <h1 class="text-3xl font-bold text-white">Welcome, <?php echo $_SESSION['teacher_name']; ?>!</h1>
                <p class="text-white/80">Teacher Dashboard - <?php echo $_SESSION['teacher_subject']; ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                    <span class="text-white text-sm"><?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Students Card -->
            <a href="teacher_manage_students.php" class="stat-card card rounded-xl p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $students_count; ?></h3>
                <p class="text-gray-600">Total Students</p>
                <div class="mt-3 text-sm text-blue-600">
                    <i class="fas fa-user-plus mr-1"></i>
                    <?php echo $new_students_today > 0 ? $new_students_today . ' New Today' : count($classes_array) . ' Classes'; ?>
                </div>
            </a>

            <!-- Results Card -->
            <a href="teacher_manage_results.php" class="stat-card card rounded-xl p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chart-bar text-green-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $results_count; ?></h3>
                <p class="text-gray-600">Results Published</p>
                <div class="mt-3 text-sm text-green-600">
                    <i class="fas fa-clock mr-1"></i>
                    <?php echo $recent_results > 0 ? $recent_results . ' Recent' : $_SESSION['teacher_subject']; ?>
                </div>
            </a>

            <!-- Classes Card -->
            <div class="stat-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo count($classes_array); ?></h3>
                <p class="text-gray-600">Assigned Classes</p>
                <div class="mt-3 text-sm text-purple-600">
                    <i class="fas fa-list mr-1"></i>
                    <?php echo $_SESSION['teacher_classes']; ?>
                </div>
            </div>
        </div>

        <!-- Students and Grades Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Recent Students -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clock text-orange-500 mr-3"></i>
                    Recent Students
                </h3>
                <div class="space-y-3">
                    <?php if ($recent_students && mysqli_num_rows($recent_students) > 0): ?>
                        <?php while($student = mysqli_fetch_assoc($recent_students)): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-500 text-sm"></i>
                                    </div>
                                    <div>
                                        <span class="text-gray-800 font-medium"><?php echo $student['name']; ?></span>
                                        <p class="text-gray-500 text-sm">Roll No: <?php echo $student['roll_number']; ?></p>
                                    </div>
                                </div>
                                <span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs">
                                    <?php echo $student['class_name']; ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">No students found in your classes.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Student Distribution by Class -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-indigo-500 mr-3"></i>
                    Students by Class
                </h3>
                <div class="space-y-3">
                    <?php if (!empty($class_distribution)): ?>
                        <?php foreach ($class_distribution as $class => $count): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-users text-indigo-500 text-sm"></i>
                                    </div>
                                    <span class="text-gray-700 font-medium"><?php echo $class; ?></span>
                                </div>
                                <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-sm font-semibold">
                                    <?php echo $count; ?> students
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">No class distribution data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & System Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-green-500 mr-3"></i>
                    Quick Actions
                </h3>
                <div class="space-y-3">
                    <a href="teacher_add_results.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-green-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-plus-circle text-green-500"></i>
                            <span class="text-gray-700">Add Results</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="teacher_manage_students.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-blue-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-user-graduate text-blue-500"></i>
                            <span class="text-gray-700">View Students</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="teacher_manage_results.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-purple-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-chart-bar text-purple-500"></i>
                            <span class="text-gray-700">Manage Results</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                </div>
            </div>

            <!-- System Overview -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-orange-500 mr-3"></i>
                    Teaching Overview
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Subjects</span>
                        <span class="px-2 py-1 bg-indigo-100 text-indigo-600 rounded-full text-sm font-medium">
                            <?php 
                                $subjects = explode(',', $_SESSION['teacher_subject']);
                                echo count($subjects) . ' subjects';
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Classes Assigned</span>
                        <span class="text-gray-800 font-medium"><?php echo count($classes_array); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Students</span>
                        <span class="text-gray-800 font-medium"><?php echo $students_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Results Published</span>
                        <span class="text-gray-800 font-medium"><?php echo $results_count; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add hover effects to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });

        // Add hover effects to quick action items
        document.querySelectorAll('.quick-action-item').forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateX(3px)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>  