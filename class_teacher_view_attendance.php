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

// Handle delete action
if (isset($_GET['delete_date']) && isset($_GET['term']) && isset($_GET['session'])) {
    $delete_date = mysqli_real_escape_string($conn, $_GET['delete_date']);
    $delete_term = mysqli_real_escape_string($conn, $_GET['term']);
    $delete_session = mysqli_real_escape_string($conn, $_GET['session']);
    
    $conn->begin_transaction();
    try {
        // Delete from attendance_records
        $delete_sql = "DELETE FROM attendance_records 
                      WHERE class_name = '$assigned_class' 
                      AND date = '$delete_date'
                      AND term = '$delete_term'
                      AND academic_session = '$delete_session'";
        
        if (mysqli_query($conn, $delete_sql)) {
            // Update attendance summary
            updateTermAttendanceSummary($conn, $assigned_class, $delete_term, $delete_session);
            
            $conn->commit();
            $_SESSION['success'] = "Attendance for $delete_date deleted successfully!";
        } else {
            throw new Exception("Error deleting attendance: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: class_teacher_view_attendance.php");
    exit();
}

// Handle bulk delete - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    $selected_dates = $_POST['selected_dates'] ?? [];
    
    if (!empty($selected_dates)) {
        $conn->begin_transaction();
        try {
            $deleted_count = 0;
            $errors = [];
            
            foreach ($selected_dates as $date_data) {
                // Properly decode the date data
                $date_parts = explode('|', $date_data);
                if (count($date_parts) === 3) {
                    $date = mysqli_real_escape_string($conn, $date_parts[0]);
                    $term = mysqli_real_escape_string($conn, $date_parts[1]);
                    $session = mysqli_real_escape_string($conn, $date_parts[2]);
                    
                    $delete_sql = "DELETE FROM attendance_records 
                                  WHERE class_name = '$assigned_class' 
                                  AND date = '$date'
                                  AND term = '$term'
                                  AND academic_session = '$session'";
                    
                    if (mysqli_query($conn, $delete_sql)) {
                        $deleted_count++;
                        // Update summary for this term/session
                        updateTermAttendanceSummary($conn, $assigned_class, $term, $session);
                    } else {
                        $errors[] = "Failed to delete attendance for $date: " . mysqli_error($conn);
                    }
                }
            }
            
            if (empty($errors)) {
                $conn->commit();
                $_SESSION['success'] = "Successfully deleted $deleted_count attendance record(s)!";
            } else {
                $conn->rollback();
                $_SESSION['error'] = "Some records could not be deleted. " . implode('; ', $errors);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting attendance records: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No attendance records selected for deletion!";
    }
    
    header("Location: class_teacher_view_attendance.php");
    exit();
}

// Function to update attendance summary (same as in your previous code)
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

// Get filter parameters
$selected_term = $_GET['term'] ?? $available_terms[0];
$selected_session = $_GET['session'] ?? $academic_year;
$search_date = $_GET['search_date'] ?? '';

// Build query for attendance dates
$where_conditions = ["class_name = '$assigned_class'"];
if (!empty($selected_term)) {
    $where_conditions[] = "term = '$selected_term'";
}
if (!empty($selected_session)) {
    $where_conditions[] = "academic_session = '$selected_session'";
}
if (!empty($search_date)) {
    $where_conditions[] = "date = '$search_date'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get distinct attendance dates with counts
$attendance_dates_sql = "SELECT 
    date,
    term,
    academic_session,
    COUNT(*) as total_students,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
    MIN(created_at) as recorded_at,
    recorded_by
    FROM attendance_records 
    WHERE $where_clause
    GROUP BY date, term, academic_session
    ORDER BY date DESC";

$attendance_dates_result = mysqli_query($conn, $attendance_dates_sql);

// Get class stats
$class_stats_sql = "SELECT COUNT(*) as total_students FROM students WHERE class_name = '$assigned_class'";
$class_stats_result = mysqli_query($conn, $class_stats_sql);
$class_stats = $class_stats_result->fetch_assoc();
$total_class_students = $class_stats['total_students'];

// Get overall statistics
$overall_stats_sql = "SELECT 
    COUNT(DISTINCT date) as total_days,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as total_present,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as total_late
    FROM attendance_records 
    WHERE class_name = '$assigned_class'";

$overall_stats_result = mysqli_query($conn, $overall_stats_sql);
$overall_stats = $overall_stats_result->fetch_assoc();

// Calculate overall attendance rate
$total_attendance = $overall_stats['total_present'] + $overall_stats['total_absent'] + $overall_stats['total_late'];
$overall_attendance_rate = $total_attendance > 0 ? 
    round(($overall_stats['total_present'] / $total_attendance) * 100, 1) : 0;

// Display messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>View Attendance - <?php echo $assigned_class; ?></title>
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
        .attendance-present { background-color: #d1fae5; }
        .attendance-absent { background-color: #fee2e2; }
        .attendance-late { background-color: #fef3c7; }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .present-dot { background-color: #10b981; }
        .absent-dot { background-color: #ef4444; }
        .late-dot { background-color: #f59e0b; }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .bulk-actions {
            position: sticky;
            bottom: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-top: 2px solid #e5e7eb;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .checkbox-cell {
            width: 40px;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Class Teacher</span>
            </div>
        </div>

        <div class="bg-green-50 rounded-lg p-4 mb-6">
            <div class="bg-green-500 text-white rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-crown mr-2"></i>
                Class Teacher
            </div>
            <h3 class="font-semibold text-green-800 text-sm"><?php echo $teacher_name; ?></h3>
            <p class="text-green-600 text-xs">Class: <?php echo $assigned_class; ?></p>
            <p class="text-green-500 text-xs mt-1"><?php echo $total_class_students; ?> Students</p>
        </div>

        <nav class="space-y-2">
            <a href="class_teacher_dashboard.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-green-600"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <!-- <a href="class_teacher_attendance.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-calendar-check text-blue-600"></i>
                <span class="font-medium">Mark Attendance</span>
            </a> -->
            <a href="class_teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">Manage Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a>

            <a href="class_teacher_view_attendance.php" class="flex items-center space-x-3 p-3 text-green-600 bg-green-50 rounded">
                <i class="fas fa-eye text-green-600"></i>
                <span class="font-medium">View Attendance</span>
            </a>

            <a href="class_teacher_affective.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-heart text-pink-600"></i>
                <span class="font-medium">Affective Domain</span>
            </a>

            <a href="class_teacher_psychomotor.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-running text-orange-600"></i>
                <span class="font-medium">Psychomotor Domain</span>
            </a>

            <a href="teacher_logout.php" class="flex items-center space-x-3 p-3 text-red-600 hover:bg-red-50 rounded">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </div>

    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">View & Manage Attendance</h1>
                <p class="text-white/80">View, edit, and delete attendance records for <?php echo $assigned_class; ?></p>
            </div>
            <div class="flex space-x-4">
                <a href="class_teacher_attendance.php" 
                   class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Mark New Attendance</span>
                </a>
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

        <!-- Overall Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $overall_stats['total_days']; ?></div>
                <div class="text-gray-600">Total Days</div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $overall_stats['total_present']; ?></div>
                <div class="text-gray-600">Total Present</div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $overall_stats['total_absent']; ?></div>
                <div class="text-gray-600">Total Absent</div>
            </div>

            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $overall_attendance_rate; ?>%</div>
                <div class="text-gray-600">Overall Rate</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Filter Attendance Records</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Term</label>
                    <select name="term" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Terms</option>
                        <?php foreach ($available_terms as $term): ?>
                            <option value="<?php echo $term; ?>" <?php echo $selected_term == $term ? 'selected' : ''; ?>>
                                <?php echo $term; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Session</label>
                    <select name="session" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($year = 2020; $year <= $current_year + 1; $year++): ?>
                            <?php $session = ($year - 1) . '-' . $year; ?>
                            <option value="<?php echo $session; ?>" <?php echo $selected_session == $session ? 'selected' : ''; ?>>
                                <?php echo $session; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Specific Date</label>
                    <input type="date" name="search_date" value="<?php echo $search_date; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded font-semibold w-full">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Attendance Records -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    Attendance Records
                    <?php if ($attendance_dates_result && mysqli_num_rows($attendance_dates_result) > 0): ?>
                        <span class="text-lg text-gray-600 ml-2">
                            (<?php echo mysqli_num_rows($attendance_dates_result); ?> records found)
                        </span>
                    <?php endif; ?>
                </h2>
                
                <?php if ($attendance_dates_result && mysqli_num_rows($attendance_dates_result) > 0): ?>
                    <div class="flex space-x-2">
                        <button type="button" onclick="toggleSelectAll()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-semibold text-sm">
                            <i class="fas fa-check-square mr-1"></i>Select All
                        </button>
                        <button type="button" onclick="confirmBulkDelete()" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-semibold text-sm">
                            <i class="fas fa-trash mr-1"></i>Delete Selected
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($attendance_dates_result && mysqli_num_rows($attendance_dates_result) > 0): ?>
                <form id="bulk-form" method="POST" action="">
                    <input type="hidden" name="bulk_delete" value="1">
                    
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b checkbox-cell">
                                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Date</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Term & Session</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Attendance Summary</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Attendance Rate</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Recorded</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = mysqli_fetch_assoc($attendance_dates_result)): 
                                    $attendance_rate = $record['total_students'] > 0 ? 
                                        round(($record['present_count'] / $record['total_students']) * 100, 1) : 0;
                                    $record_key = $record['date'] . '|' . $record['term'] . '|' . $record['academic_session'];
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 checkbox-cell">
                                        <input type="checkbox" name="selected_dates[]" value="<?php echo htmlspecialchars($record_key); ?>" 
                                               class="date-checkbox">
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">
                                        <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">
                                            <?php echo $record['term']; ?>
                                        </span>
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full ml-1">
                                            <?php echo $record['academic_session']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center space-x-4 text-sm">
                                            <span class="text-green-600 font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                <?php echo $record['present_count']; ?> Present
                                            </span>
                                            <span class="text-red-600 font-semibold">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                <?php echo $record['absent_count']; ?> Absent
                                            </span>
                                            <span class="text-yellow-600 font-semibold">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo $record['late_count']; ?> Late
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                                            <?php echo $attendance_rate >= 80 ? 'bg-green-100 text-green-800' : 
                                                   ($attendance_rate >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo $attendance_rate; ?>%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-600 text-sm">
                                        <?php echo date('M j, g:i A', strtotime($record['recorded_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex justify-center space-x-2">
                                            <a href="class_teacher_attendance.php?edit_date=<?php echo $record['date']; ?>&term=<?php echo $record['term']; ?>&session=<?php echo $record['academic_session']; ?>" 
                                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </a>
                                            <button type="button" 
                                                    onclick="confirmDelete('<?php echo $record['date']; ?>', '<?php echo $record['term']; ?>', '<?php echo $record['academic_session']; ?>')"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <div id="bulk-actions-bar" class="bulk-actions hidden">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <span class="text-gray-700 font-semibold" id="selected-count">0</span>
                                <span class="text-gray-600">records selected</span>
                            </div>
                            <div class="flex space-x-2">
                                <button type="button" onclick="clearSelection()" 
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-semibold text-sm">
                                    <i class="fas fa-times mr-1"></i>Clear Selection
                                </button>
                                <button type="button" onclick="confirmBulkDelete()" 
                                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-semibold text-sm">
                                    <i class="fas fa-trash mr-1"></i>Delete Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Attendance Records Found</h3>
                    <p class="text-gray-500 mb-6">No attendance records match your current filters.</p>
                    <a href="class_teacher_attendance.php" 
                       class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Mark Your First Attendance</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDelete(date, term, session) {
            const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });
            
            if (confirm(`Are you sure you want to delete attendance for ${formattedDate} (${term})? This action cannot be undone.`)) {
                window.location.href = `class_teacher_view_attendance.php?delete_date=${date}&term=${term}&session=${session}`;
            }
        }

        function confirmBulkDelete() {
            const selectedCount = document.querySelectorAll('.date-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Please select at least one attendance record to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedCount} attendance record(s)? This action cannot be undone.`)) {
                document.getElementById('bulk-form').submit();
            }
        }

        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.date-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkActions();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            selectAll.checked = !selectAll.checked;
            toggleAllCheckboxes();
        }

        function updateBulkActions() {
            const selectedCount = document.querySelectorAll('.date-checkbox:checked').length;
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            const selectedCountElement = document.getElementById('selected-count');
            
            if (selectedCount > 0) {
                bulkActionsBar.classList.remove('hidden');
                selectedCountElement.textContent = selectedCount;
            } else {
                bulkActionsBar.classList.add('hidden');
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.date-checkbox');
            const selectAll = document.getElementById('select-all');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAll.checked = false;
            updateBulkActions();
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.date-checkbox');
            const selectAll = document.getElementById('select-all');
            
            // Update bulk actions when checkboxes change
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateBulkActions();
                    
                    // Update select-all checkbox
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                    
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                });
            });
            
            // Initialize bulk actions bar
            updateBulkActions();
        });
    </script>
</body>
</html>