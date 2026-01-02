<?php
session_start();
include('init.php');

// STRICT Class Teacher Only Access
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'class_teacher') {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$assigned_class = $_SESSION['assigned_class'];

// Get class statistics
$stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
    FROM students 
    WHERE class_name = '$assigned_class'";
    
$stats_result = mysqli_query($conn, $stats_sql);
if ($stats_result) {
    $class_stats = mysqli_fetch_assoc($stats_result);
} else {
    $class_stats = ['total_students' => 0, 'male_students' => 0, 'female_students' => 0];
}

// Get students in assigned class
$students_sql = "SELECT id, name, roll_number, gender FROM students 
                 WHERE class_name = '$assigned_class' 
                 ORDER BY name";
$students_result = mysqli_query($conn, $students_sql);

// Get academic performance data - FIXED VERSION
$performance_sql = "SELECT 
    COUNT(DISTINCT r.student_id) as students_with_results,
    AVG(r.total_score) as average_percentage,
    MAX(r.total_score) as highest_percentage,
    MIN(r.total_score) as lowest_percentage
    FROM results r 
    JOIN students s ON r.student_id = s.id
    WHERE s.class_name = '$assigned_class'";
    
$performance_result = mysqli_query($conn, $performance_sql);
if ($performance_result && mysqli_num_rows($performance_result) > 0) {
    $performance_data = mysqli_fetch_assoc($performance_result);
    // Round the percentages
    $performance_data['average_percentage'] = round($performance_data['average_percentage'], 1);
    $performance_data['highest_percentage'] = round($performance_data['highest_percentage'], 1);
    $performance_data['lowest_percentage'] = round($performance_data['lowest_percentage'], 1);
} else {
    $performance_data = [
        'students_with_results' => 0,
        'average_percentage' => 0,
        'highest_percentage' => 0,
        'lowest_percentage' => 0
    ];
}

// Get subject names from subjects table
$subject_names_sql = "SELECT id, subject_name FROM subjects ORDER BY id LIMIT 7";
$subject_names_result = mysqli_query($conn, $subject_names_sql);
$subject_list = [];

if ($subject_names_result) {
    while ($row = mysqli_fetch_assoc($subject_names_result)) {
        $subject_list[$row['id']] = $row['subject_name'];
    }
}

// Get subject-wise performance - FIXED VERSION
$subjects_sql = "SELECT 
    r.subject_id,
    AVG(r.total_score) as average_score,
    COUNT(r.id) as result_count
    FROM results r 
    JOIN students s ON r.student_id = s.id
    WHERE s.class_name = '$assigned_class' 
    GROUP BY r.subject_id
    ORDER BY r.subject_id
    LIMIT 7";
    
$subjects_result = mysqli_query($conn, $subjects_sql);
$subject_averages = [];

if ($subjects_result && mysqli_num_rows($subjects_result) > 0) {
    while ($row = mysqli_fetch_assoc($subjects_result)) {
        $subject_id = $row['subject_id'];
        $subject_name = isset($subject_list[$subject_id]) ? $subject_list[$subject_id] : "Subject $subject_id";
        $subject_averages[$subject_name] = round($row['average_score'], 1);
    }
}

// Get recent activity (last 5 students added)
$recent_students_sql = "SELECT 
    name, roll_number, gender, created_at
    FROM students 
    WHERE class_name = '$assigned_class' 
    ORDER BY created_at DESC 
    LIMIT 5";
    
$recent_students_result = mysqli_query($conn, $recent_students_sql);

// Calculate completion percentages
$students_with_results = $performance_data['students_with_results'];
$completion_percentage = $class_stats['total_students'] > 0 ? 
    round(($students_with_results / $class_stats['total_students']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Class Teacher Dashboard - <?php echo $assigned_class; ?></title>
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
        .class-teacher-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .nav-item {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .nav-item:hover {
            background: rgba(16, 185, 129, 0.1);
            transform: translateX(5px);
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
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
                <div class="w-10 h-10 class-teacher-badge rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Class Teacher</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-green-50 rounded-lg p-4 mb-6">
            <div class="class-teacher-badge rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-crown mr-2"></i>
                Class Teacher
            </div>
            <h3 class="font-semibold text-green-800 text-sm"><?php echo $teacher_name; ?></h3>
            <p class="text-green-600 text-xs">Class: <?php echo $assigned_class; ?></p>
            <p class="text-green-500 text-xs mt-1"><?php echo $class_stats['total_students']; ?> Students</p>
        </div>

        <nav class="space-y-2">
            <a href="class_teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-green-600 bg-green-50 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="class_teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">Manage Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a>

            <a href="class_teacher_add_student.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-user-plus text-green-600"></i>
                <span class="font-medium">Add Student</span>
                <span class="bg-green-100 text-green-600 text-xs px-2 py-1 rounded-full">New</span>
            </a>

            <a href="class_teacher_attendance.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-calendar-check text-blue-600"></i>
                <span class="font-medium">Attendance</span>
            </a>

            <a href="class_teacher_affective.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-star text-yellow-600"></i>
                <span class="font-medium">Affective Domain</span>
            </a>

            <a href="class_teacher_psychomotor.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-running text-purple-600"></i>
                <span class="font-medium">Psychomotor Domain</span>
            </a>

            <a href="class_teacher_comments.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-comment-alt text-teal-600"></i>
                <span class="font-medium">Report Comments</span>
            </a>

            <a href="report_card_selector.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-file-pdf text-red-600"></i>
                <span class="font-medium">Generate Report Cards</span>
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
                <h1 class="text-3xl font-bold text-white">Class Teacher Dashboard</h1>
                <p class="text-white/80">Managing <?php echo $assigned_class; ?> - <?php echo $class_stats['total_students']; ?> Students</p>
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

        <!-- Class Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Students -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $class_stats['total_students']; ?></div>
                <div class="text-gray-600">Total Students</div>
                <div class="mt-2 text-sm text-blue-600">
                    <i class="fas fa-male"></i> <?php echo $class_stats['male_students']; ?> 
                    <i class="fas fa-female ml-2"></i> <?php echo $class_stats['female_students']; ?>
                </div>
            </div>

            <!-- Academic Performance -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo $performance_data['average_percentage']; ?>%
                </div>
                <div class="text-gray-600">Average Score</div>
                <div class="mt-2 text-sm text-green-600">
                    High: <?php echo $performance_data['highest_percentage']; ?>%
                </div>
            </div>

            <!-- Results Completion -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-tasks text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $completion_percentage; ?>%</div>
                <div class="text-gray-600">Results Completion</div>
                <div class="mt-2">
                    <div class="progress-bar">
                        <div class="progress-fill bg-purple-500" style="width: <?php echo $completion_percentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Top Performer -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-trophy text-yellow-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo $performance_data['highest_percentage']; ?>%
                </div>
                <div class="text-gray-600">Top Score</div>
                <div class="mt-2 text-sm text-yellow-600">
                    <?php echo $performance_data['students_with_results']; ?> with results
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Subject Performance -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-book-open text-indigo-500 mr-3"></i>
                    Subject Performance
                </h3>
                <div class="space-y-4">
                    <?php if (!empty($subject_averages)): ?>
                        <?php foreach ($subject_averages as $subject => $average): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700 font-medium"><?php echo $subject; ?></span>
                                <div class="flex items-center space-x-3">
                                    <span class="text-gray-600 font-semibold"><?php echo $average; ?>%</span>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full" style="width: <?php echo min($average, 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-chart-bar text-2xl mb-2"></i>
                            <p>No subject data available</p>
                            <p class="text-sm">Add results to see performance analytics</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Students Added -->
            <div class="card rounded-xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-user-plus text-green-500 mr-3"></i>
                        Recently Added Students
                    </h3>
                    <span class="text-sm text-gray-500">Last 5</span>
                </div>
                <div class="space-y-3">
                    <?php if ($recent_students_result && mysqli_num_rows($recent_students_result) > 0): ?>
                        <?php while ($student = mysqli_fetch_assoc($recent_students_result)): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800"><?php echo $student['name']; ?></div>
                                        <div class="text-xs text-gray-500">Roll: <?php echo $student['roll_number']; ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('M j', strtotime($student['created_at'])); ?>
                                    </div>
                                    <div class="text-xs text-green-600">
                                        <?php echo $student['gender']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-users text-2xl mb-2"></i>
                            <p>No students added yet</p>
                            <p class="text-sm">Add students to see them here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Quick Actions -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-green-500 mr-3"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="class_teacher_add_student.php" class="bg-green-50 border border-green-200 rounded-lg p-4 text-center hover:bg-green-100 transition duration-200">
                        <i class="fas fa-user-plus text-green-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-green-800">Add Student</div>
                        <div class="text-xs text-green-600">Register new student</div>
                    </a>

                    <a href="class_teacher_manage_students.php" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-center hover:bg-indigo-100 transition duration-200">
                        <i class="fas fa-users text-indigo-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-indigo-800">Manage Students</div>
                        <div class="text-xs text-indigo-600">View all students</div>
                    </a>

                    <a href="class_teacher_attendance.php" class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center hover:bg-blue-100 transition duration-200">
                        <i class="fas fa-calendar-check text-blue-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-blue-800">Attendance</div>
                        <div class="text-xs text-blue-600">Mark daily attendance</div>
                    </a>

                    <a href="report_card_selector.php" class="bg-red-50 border border-red-200 rounded-lg p-4 text-center hover:bg-red-100 transition duration-200">
                        <i class="fas fa-file-pdf text-red-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-red-800">Report Cards</div>
                        <div class="text-xs text-red-600">Generate PDFs</div>
                    </a>
                </div>
            </div>

            <!-- Class Information -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-purple-500 mr-3"></i>
                    Class Information
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Class Name</span>
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold">
                            <?php echo $assigned_class; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Class Teacher</span>
                        <span class="text-gray-600"><?php echo $teacher_name; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Total Students</span>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                            <?php echo $class_stats['total_students']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700">Results Completion</span>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                            <?php echo $completion_percentage; ?>%
                        </span>
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

        // Add subtle animations
        document.addEventListener('DOMContentLoaded', function() {
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate__animated', 'animate__fadeInUp');
            });
        });
    </script>
</body>
</html>