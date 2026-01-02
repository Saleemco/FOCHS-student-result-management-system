<?php
session_start();
include('init.php');

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'class_teacher') {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$assigned_class = $_SESSION['assigned_class'];

$current_year = date('Y');
$academic_year = ($current_year - 1) . '-' . $current_year;
$available_terms = ['First Term', 'Second Term', 'Third Term'];
$current_month = date('n');

if ($current_month >= 1 && $current_month <= 4) {
    $current_term = 'Third Term';
} elseif ($current_month >= 5 && $current_month <= 8) {
    $current_term = 'First Term';
} else {
    $current_term = 'Second Term';
}

$selected_term = $current_term;
$selected_session = $academic_year;
$success = '';
$error = '';

// Handle edit mode - check if we're editing existing attendance
$edit_mode = false;
$edit_date = '';
$edit_term = '';
$edit_session = '';

if (isset($_GET['edit_date']) && isset($_GET['term']) && isset($_GET['session'])) {
    $edit_mode = true;
    $edit_date = mysqli_real_escape_string($conn, $_GET['edit_date']);
    $edit_term = mysqli_real_escape_string($conn, $_GET['term']);
    $edit_session = mysqli_real_escape_string($conn, $_GET['session']);
    
    // Pre-fill the form with existing data
    $today = $edit_date;
    $selected_term = $edit_term;
    $selected_session = $edit_session;
    
    // Load existing attendance for this date
    $existing_attendance_sql = "SELECT student_id, status, remarks FROM attendance_records 
                               WHERE class_name = '$assigned_class' 
                               AND date = '$edit_date'
                               AND term = '$edit_term'
                               AND academic_session = '$edit_session'";
    $existing_attendance_result = mysqli_query($conn, $existing_attendance_sql);
    $existing_attendance = [];
    
    if ($existing_attendance_result) {
        while ($row = mysqli_fetch_assoc($existing_attendance_result)) {
            $existing_attendance[$row['student_id']] = $row;
        }
    }
} else {
    $today = date('Y-m-d');
    
    // Load existing attendance for today (for normal mode)
    $existing_attendance_sql = "SELECT student_id, status, remarks FROM attendance_records 
                               WHERE class_name = '$assigned_class' AND date = '$today'";
    $existing_attendance_result = mysqli_query($conn, $existing_attendance_sql);
    $existing_attendance = [];
    
    if ($existing_attendance_result) {
        while ($row = mysqli_fetch_assoc($existing_attendance_result)) {
            $existing_attendance[$row['student_id']] = $row;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_attendance'])) {
        $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
        $selected_term = isset($_POST['term']) ? mysqli_real_escape_string($conn, $_POST['term']) : $current_term;
        $selected_session = isset($_POST['academic_session']) ? mysqli_real_escape_string($conn, $_POST['academic_session']) : $academic_year;
        
        $check_sql = "SELECT COUNT(*) as count FROM attendance_records 
                     WHERE class_name = '$assigned_class' AND date = '$attendance_date'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if ($check_result) {
            $attendance_exists = mysqli_fetch_assoc($check_result)['count'] > 0;
        } else {
            $attendance_exists = false;
            $error = "Error checking existing attendance: " . mysqli_error($conn);
        }
        
        if ($attendance_exists && !isset($_POST['overwrite'])) {
            $error = "Attendance already recorded for $attendance_date. Check 'Overwrite existing' to update.";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
                foreach ($_POST['attendance'] as $student_id => $status) {
                    $student_id = intval($student_id);
                    $status = mysqli_real_escape_string($conn, $status);
                    $remarks = isset($_POST['remarks'][$student_id]) ? mysqli_real_escape_string($conn, $_POST['remarks'][$student_id]) : '';
                    
                    if ($attendance_exists) {
                        $sql = "UPDATE attendance_records SET 
                                status = '$status', 
                                remarks = '$remarks',
                                term = '$selected_term',
                                academic_session = '$selected_session',
                                recorded_by = '$teacher_id',
                                updated_at = NOW()
                                WHERE student_id = '$student_id' 
                                AND class_name = '$assigned_class' 
                                AND date = '$attendance_date'";
                    } else {
                        $sql = "INSERT INTO attendance_records 
                                (student_id, class_name, date, term, academic_session, status, remarks, recorded_by, updated_at) 
                                VALUES ('$student_id', '$assigned_class', '$attendance_date', '$selected_term', '$selected_session', '$status', '$remarks', '$teacher_id', NOW())";
                    }
                    
                    if (mysqli_query($conn, $sql)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        error_log("Attendance error for student $student_id: " . mysqli_error($conn));
                    }
                }
                
                if ($success_count > 0) {
                    updateTermAttendanceSummary($conn, $assigned_class, $selected_term, $selected_session);
                    $action = $edit_mode ? "updated" : "recorded";
                    $success = "Attendance $action successfully for $success_count students!";
                    if ($error_count > 0) {
                        $success .= " ($error_count errors occurred)";
                    }
                    
                    // Reset edit mode after successful update
                    $edit_mode = false;
                } else {
                    $error = "Error recording attendance for all students!";
                }
            } else {
                $error = "No attendance data submitted!";
            }
        }
    }
}

function updateTermAttendanceSummary($conn, $class_name, $term, $academic_year) {
    $term_dates = getTermDates($term, $academic_year);
    $term_start = $term_dates['start'];
    $term_end = $term_dates['end'];
    
    $students_sql = "SELECT id FROM students WHERE class_name = '$class_name'";
    $students_result = mysqli_query($conn, $students_sql);
    
    if ($students_result) {
        while ($student = mysqli_fetch_assoc($students_result)) {
            $student_id = $student['id'];
            
            $sql = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as days_present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as days_absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as days_late
                FROM attendance_records 
                WHERE student_id = ? 
                AND class_name = ?
                AND term = ?
                AND academic_session = ?
                AND date BETWEEN ? AND ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isssss', $student_id, $class_name, $term, $academic_year, $term_start, $term_end);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result) {
                    $data = mysqli_fetch_assoc($result);
                    
                    if ($data) {
                        $attendance_rate = $data['total_days'] > 0 ? 
                            round(($data['days_present'] / $data['total_days']) * 100, 2) : 0;
                        
                        $update_sql = "INSERT INTO attendance_summary 
                                      (student_id, class_name, term, academic_year, total_days, days_present, days_absent, days_late, attendance_rate)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE
                                      total_days = VALUES(total_days),
                                      days_present = VALUES(days_present),
                                      days_absent = VALUES(days_absent),
                                      days_late = VALUES(days_late),
                                      attendance_rate = VALUES(attendance_rate)";
                        
                        $stmt_update = mysqli_prepare($conn, $update_sql);
                        if ($stmt_update) {
                            mysqli_stmt_bind_param($stmt_update, 'isssiiiid', 
                                $student_id, $class_name, $term, $academic_year,
                                $data['total_days'], $data['days_present'], $data['days_absent'], 
                                $data['days_late'], $attendance_rate);
                            
                            mysqli_stmt_execute($stmt_update);
                        }
                    }
                }
            }
        }
    }
    
    return true;
}

function getTermDates($term, $academic_year) {
    $year_parts = explode('-', $academic_year);
    $start_year = $year_parts[0];
    $end_year = $year_parts[1];
    
    $term_dates = [
        'First Term' => [
            'start' => $start_year . '-09-01',
            'end' => $start_year . '-12-20'
        ],
        'Second Term' => [
            'start' => $end_year . '-01-08',
            'end' => $end_year . '-04-05'
        ],
        'Third Term' => [
            'start' => $end_year . '-04-23',
            'end' => $end_year . '-07-31'
        ]
    ];
    
    return $term_dates[$term] ?? $term_dates['First Term'];
}

$students_sql = "SELECT id, name, roll_number, gender FROM students WHERE class_name = '$assigned_class' ORDER BY roll_number";
$students_result = mysqli_query($conn, $students_sql);

$attendance_stats_sql = "SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_today,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_today,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_today
    FROM attendance_records 
    WHERE class_name = '$assigned_class' 
    AND date = '$today'";
    
$attendance_stats_result = mysqli_query($conn, $attendance_stats_sql);
$attendance_stats = [
    'total_records' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'late_today' => 0
];

if ($attendance_stats_result) {
    $temp_stats = mysqli_fetch_assoc($attendance_stats_result);
    if ($temp_stats) {
        $attendance_stats = $temp_stats;
    }
}

$term_stats_sql = "SELECT 
    SUM(days_present) as term_present,
    SUM(days_absent) as term_absent,
    SUM(days_late) as term_late,
    AVG(attendance_rate) as avg_attendance_rate
    FROM attendance_summary 
    WHERE class_name = '$assigned_class' 
    AND term = '$selected_term' 
    AND academic_year = '$selected_session'";
    
$term_stats_result = mysqli_query($conn, $term_stats_sql);
$term_stats = [
    'term_present' => 0,
    'term_absent' => 0,
    'term_late' => 0,
    'avg_attendance_rate' => 0
];

if ($term_stats_result) {
    $temp_stats = mysqli_fetch_assoc($term_stats_result);
    if ($temp_stats) {
        $term_stats = $temp_stats;
    }
}

$class_stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
    FROM students 
    WHERE class_name = '$assigned_class'";
    
$class_stats_result = mysqli_query($conn, $class_stats_sql);
$class_stats = [
    'total_students' => 0, 
    'male_students' => 0, 
    'female_students' => 0
];

if ($class_stats_result) {
    $temp_stats = mysqli_fetch_assoc($class_stats_result);
    if ($temp_stats) {
        $class_stats = $temp_stats;
    }
}

$class_stats['total_students'] = intval($class_stats['total_students']);
$class_stats['male_students'] = intval($class_stats['male_students']);
$class_stats['female_students'] = intval($class_stats['female_students']);
$attendance_stats['present_today'] = intval($attendance_stats['present_today']);
$attendance_stats['late_today'] = intval($attendance_stats['late_today']);
$term_stats['term_present'] = intval($term_stats['term_present']);
$term_stats['avg_attendance_rate'] = floatval($term_stats['avg_attendance_rate']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title><?php echo $edit_mode ? 'Edit' : 'Mark'; ?> Attendance - <?php echo $assigned_class; ?></title>
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
        .attendance-present { 
            background-color: #d1fae5; 
            border-left: 4px solid #10b981;
        }
        .attendance-absent { 
            background-color: #fee2e2; 
            border-left: 4px solid #ef4444;
        }
        .attendance-late { 
            background-color: #fef3c7; 
            border-left: 4px solid #f59e0b;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .present-dot { background-color: #10b981; }
        .absent-dot { background-color: #ef4444; }
        .late-dot { background-color: #f59e0b; }
        .attendance-option {
            transition: all 0.2s ease;
        }
        .attendance-option:hover {
            transform: scale(1.05);
        }
        .term-badge {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }
        .edit-mode-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
    </style>
</head>
<body class="flex">
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 class-teacher-badge rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Class Teacher</span>
            </div>
        </div>

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
            <a href="class_teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-green-600"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <!-- <a href="class_teacher_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">My Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a> -->
            <a href="class_teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">Manage Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a>

            <a href="teacher_manage_results.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-chart-bar text-green-600"></i>
                <span class="font-medium">Results</span>
            </a>

            <a href="class_teacher_attendance.php" class="nav-item p-3 flex items-center space-x-3 text-green-600 bg-green-50 rounded">
                <i class="fas fa-calendar-check text-blue-600"></i>
                <span class="font-medium">Attendance</span>
                <span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full"><?php echo $edit_mode ? 'Edit' : 'Today'; ?></span>
            </a>

            <a href="class_teacher_view_attendance.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-eye text-green-600"></i>
                <span class="font-medium">View Attendance</span>
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

    <div class="flex-1 p-8">
        <?php if ($edit_mode): ?>
        <div class="edit-mode-banner rounded-xl p-4 mb-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-edit text-2xl"></i>
                    <div>
                        <h2 class="text-xl font-bold">Edit Mode</h2>
                        <p class="text-white/90">Editing attendance for <?php echo date('M j, Y', strtotime($edit_date)); ?> (<?php echo $edit_term; ?>)</p>
                    </div>
                </div>
                <a href="class_teacher_attendance.php" class="bg-white text-orange-600 px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel Edit
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white"><?php echo $edit_mode ? 'Edit' : 'Attendance Management'; ?></h1>
                <p class="text-white/80"><?php echo $edit_mode ? 'Update attendance records' : 'Mark and manage attendance for ' . $assigned_class . ' with term tracking'; ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="term-badge text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <?php echo $selected_term; ?> <?php echo $selected_session; ?>
                </div>
                <div class="bg-white/20 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-clock mr-2"></i>
                    <span id="current-time"><?php echo date('h:i A'); ?></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo $class_stats['total_students']; ?>
                </div>
                <div class="text-gray-600">Total Students</div>
                <div class="mt-2 text-sm text-blue-600">
                    <i class="fas fa-male"></i> <?php echo $class_stats['male_students']; ?> 
                    <i class="fas fa-female ml-2"></i> <?php echo $class_stats['female_students']; ?>
                </div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="present-count">
                    <?php echo $attendance_stats['present_today']; ?>
                </div>
                <div class="text-gray-600">Present Today</div>
                <div class="mt-2 text-sm text-green-600">
                    <i class="fas fa-user-check"></i> Real-time
                </div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo round($term_stats['avg_attendance_rate'], 1); ?>%
                </div>
                <div class="text-gray-600">Term Average</div>
                <div class="mt-2 text-sm text-purple-600">
                    <i class="fas fa-trending-up"></i> <?php echo $selected_term; ?>
                </div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-calendar-week text-indigo-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php echo $term_stats['term_present']; ?>
                </div>
                <div class="text-gray-600">Term Present</div>
                <div class="mt-2 text-sm text-indigo-600">
                    <i class="fas fa-list-ol"></i> <?php echo $selected_session; ?>
                </div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="late-count">
                    <?php echo $attendance_stats['late_today']; ?>
                </div>
                <div class="text-gray-600">Late Today</div>
                <div class="mt-2 text-sm text-yellow-600">
                    <i class="fas fa-running"></i> Marked
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="card rounded-xl p-4 mb-6 bg-green-50 border border-green-200">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-green-800">Success!</h3>
                        <p class="text-green-600 text-sm"><?php echo $success; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="card rounded-xl p-4 mb-6 bg-red-50 border border-red-200">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-red-800">Error!</h3>
                        <p class="text-red-600 text-sm"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-calendar-day text-blue-500 mr-3"></i>
                    <?php echo $edit_mode ? 'Edit Daily Attendance' : 'Mark Daily Attendance'; ?>
                </h2>
                <div class="flex items-center space-x-3">
                    <button type="button" onclick="markAllPresent()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-check"></i>
                        <span>Mark All Present</span>
                    </button>
                    <button type="button" onclick="clearAll()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-undo"></i>
                        <span>Reset All</span>
                    </button>
                </div>
            </div>

            <form method="POST" id="attendance-form">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-calendar mr-2 text-blue-500"></i>
                            Attendance Date
                        </label>
                        <input type="date" name="attendance_date" value="<?php echo $today; ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg"
                               required id="attendance-date" onchange="checkExistingAttendance()" <?php echo $edit_mode ? 'readonly' : ''; ?>>
                        <?php if ($edit_mode): ?>
                            <p class="text-xs text-gray-500 mt-1">Date cannot be changed in edit mode</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-graduation-cap mr-2 text-green-500"></i>
                            Term
                        </label>
                        <select name="term" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-lg" required>
                            <?php foreach ($available_terms as $term): ?>
                                <option value="<?php echo $term; ?>" <?php echo $selected_term == $term ? 'selected' : ''; ?>>
                                    <?php echo $term; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-calendar-alt mr-2 text-purple-500"></i>
                            Academic Session
                        </label>
                        <select name="academic_session" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-lg" required>
                            <?php for ($year = 2020; $year <= $current_year + 1; $year++): ?>
                                <?php $session = ($year - 1) . '-' . $year; ?>
                                <option value="<?php echo $session; ?>" <?php echo $selected_session == $session ? 'selected' : ''; ?>>
                                    <?php echo $session; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div id="overwrite-warning" class="hidden mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-yellow-800">Attendance Already Exists</h4>
                            <p class="text-yellow-700 text-sm">Attendance has already been recorded for this date.</p>
                        </div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="overwrite" value="1" class="h-4 w-4 text-yellow-600 focus:ring-yellow-500">
                            <span class="ml-2 text-yellow-800 font-medium">Overwrite existing</span>
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Roll No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Student Name</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Gender</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Attendance Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Remarks</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Last Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-table-body">
                            <?php 
                            if ($students_result && mysqli_num_rows($students_result) > 0):
                                mysqli_data_seek($students_result, 0);
                                $student_count = 0;
                                while ($student = mysqli_fetch_assoc($students_result)): 
                                    $student_count++;
                                    $existing_data = $existing_attendance[$student['id']] ?? null;
                                    $default_status = $existing_data ? $existing_data['status'] : 'Present';
                                    $default_remarks = $existing_data ? $existing_data['remarks'] : '';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition duration-150 attendance-row" 
                                data-student-id="<?php echo $student['id']; ?>"
                                id="row-<?php echo $student['id']; ?>">
                                <td class="px-4 py-3 font-mono font-semibold text-gray-600">
                                    <?php echo $student['roll_number']; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-blue-600 font-semibold text-sm"><?php echo $student_count; ?></span>
                                        </div>
                                        <span class="text-gray-800 font-medium"><?php echo $student['name']; ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold 
                                        <?php echo $student['gender'] == 'Male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                                        <i class="fas <?php echo $student['gender'] == 'Male' ? 'fa-male' : 'fa-female'; ?> mr-1"></i>
                                        <?php echo $student['gender']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center space-x-4">
                                        <label class="inline-flex items-center attendance-option cursor-pointer">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="Present" 
                                                   class="h-5 w-5 text-green-600 focus:ring-green-500 present-radio"
                                                   <?php echo $default_status == 'Present' ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-green-600 font-medium">Present</span>
                                        </label>
                                        <label class="inline-flex items-center attendance-option cursor-pointer">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="Absent" 
                                                   class="h-5 w-5 text-red-600 focus:ring-red-500 absent-radio"
                                                   <?php echo $default_status == 'Absent' ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-red-600 font-medium">Absent</span>
                                        </label>
                                        <label class="inline-flex items-center attendance-option cursor-pointer">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="Late" 
                                                   class="h-5 w-5 text-yellow-600 focus:ring-yellow-500 late-radio"
                                                   <?php echo $default_status == 'Late' ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-yellow-600 font-medium">Late</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="remarks[<?php echo $student['id']; ?>]" 
                                           value="<?php echo htmlspecialchars($default_remarks); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm"
                                           placeholder="e.g., Sick, Doctor appointment...">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($existing_data): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold 
                                            <?php echo $existing_data['status'] == 'Present' ? 'bg-green-100 text-green-800' : ''; ?>
                                            <?php echo $existing_data['status'] == 'Absent' ? 'bg-red-100 text-red-800' : ''; ?>
                                            <?php echo $existing_data['status'] == 'Late' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                            <span class="status-dot 
                                                <?php echo $existing_data['status'] == 'Present' ? 'present-dot' : ''; ?>
                                                <?php echo $existing_data['status'] == 'Absent' ? 'absent-dot' : ''; ?>
                                                <?php echo $existing_data['status'] == 'Late' ? 'late-dot' : ''; ?>"></span>
                                            <?php echo ucfirst($existing_data['status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No record</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-users-slash text-3xl mb-2 block"></i>
                                    No students found in <?php echo $assigned_class; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <div class="text-sm text-gray-600 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Integrated with report cards and term analytics
                    </div>
                    <button type="submit" name="submit_attendance" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200 flex items-center space-x-2 shadow-lg">
                        <i class="fas fa-save"></i>
                        <span><?php echo $edit_mode ? 'Update Attendance Records' : 'Save Attendance Records'; ?></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-purple-500 mr-3"></i>
                    Attendance Legend
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="font-medium text-green-800">Present</span>
                        </div>
                        <span class="text-green-600 text-sm">Student attended class</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                            <span class="font-medium text-red-800">Absent</span>
                        </div>
                        <span class="text-red-600 text-sm">Student did not attend</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                            <span class="font-medium text-yellow-800">Late</span>
                        </div>
                        <span class="text-yellow-600 text-sm">Student arrived late</span>
                    </div>
                </div>
            </div>

            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-orange-500 mr-3"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="class_teacher_dashboard.php" class="bg-green-50 border border-green-200 rounded-lg p-4 text-center hover:bg-green-100 transition duration-200">
                        <i class="fas fa-tachometer-alt text-green-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-green-800">Dashboard</div>
                        <div class="text-xs text-green-600">Back to overview</div>
                    </a>
                    <a href="class_teacher_view_attendance.php" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-center hover:bg-indigo-100 transition duration-200">
                        <i class="fas fa-eye text-indigo-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-indigo-800">View All</div>
                        <div class="text-xs text-indigo-600">View all records</div>
                    </a>
                    <a href="class_teacher_affective.php" class="bg-pink-50 border border-pink-200 rounded-lg p-4 text-center hover:bg-pink-100 transition duration-200">
                        <i class="fas fa-heart text-pink-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-pink-800">Affective</div>
                        <div class="text-xs text-pink-600">Behavior assessment</div>
                    </a>
                    <a href="class_teacher_reports.php" class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center hover:bg-blue-100 transition duration-200">
                        <i class="fas fa-file-pdf text-blue-600 text-2xl mb-2"></i>
                        <div class="font-semibold text-blue-800">Reports</div>
                        <div class="text-xs text-blue-600">Generate reports</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateCounters() {
            const presentCount = document.querySelectorAll('input[value="Present"]:checked').length;
            const lateCount = document.querySelectorAll('input[value="Late"]:checked').length;
            
            document.getElementById('present-count').textContent = presentCount;
            document.getElementById('late-count').textContent = lateCount;
            
            document.querySelectorAll('.attendance-row').forEach(row => {
                const presentRadio = row.querySelector('.present-radio');
                const absentRadio = row.querySelector('.absent-radio');
                const lateRadio = row.querySelector('.late-radio');
                
                row.classList.remove('attendance-present', 'attendance-absent', 'attendance-late');
                
                if (presentRadio.checked) {
                    row.classList.add('attendance-present');
                } else if (absentRadio.checked) {
                    row.classList.add('attendance-absent');
                } else if (lateRadio.checked) {
                    row.classList.add('attendance-late');
                }
            });
        }

        function checkExistingAttendance() {
            const date = document.getElementById('attendance-date').value;
            const warningDiv = document.getElementById('overwrite-warning');
            
            if (date === '<?php echo $today; ?>' && <?php echo count($existing_attendance) > 0 ? 'true' : 'false'; ?>) {
                warningDiv.classList.remove('hidden');
            } else {
                warningDiv.classList.add('hidden');
            }
        }

        function markAllPresent() {
            document.querySelectorAll('.present-radio').forEach(radio => {
                radio.checked = true;
            });
            updateCounters();
        }

        function clearAll() {
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.checked = false;
            });
            updateCounters();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCounters();
            checkExistingAttendance();
            
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', updateCounters);
            });
            
            document.querySelectorAll('.attendance-option').forEach(option => {
                option.addEventListener('click', function(e) {
                    if (e.target.type !== 'radio') {
                        const radio = this.querySelector('input[type="radio"]');
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                    }
                });
            });
        });

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
    </script>
</body>
</html>