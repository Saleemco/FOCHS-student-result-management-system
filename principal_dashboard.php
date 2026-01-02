<?php
session_start();
include('init.php');

// STRICT Principal Only Access - flexible checking for session variables
$user_id = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? '';

if (!$user_id || $user_type != 'principal') {
    header('Location: login.php');
    exit();
}

$principal_id = $user_id;
$principal_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Principal';

// Check if dark mode preference exists in session
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = false;
}

// Toggle dark mode if requested
if (isset($_GET['toggle_dark_mode'])) {
    $_SESSION['dark_mode'] = !$_SESSION['dark_mode'];
    header('Location: principal_dashboard.php');
    exit();
}

$dark_mode = $_SESSION['dark_mode'];

// Helper function to get term/session
function getCurrentTermSession($conn) {
    // Check if user selected a term from dropdown
    if (isset($_GET['selected_term']) && isset($_GET['selected_session'])) {
        $selected_term = $_GET['selected_term'];
        $selected_session = $_GET['selected_session'];
        $_SESSION['dashboard_term'] = $selected_term;
        $_SESSION['dashboard_session'] = $selected_session;
    } elseif (isset($_SESSION['dashboard_term']) && isset($_SESSION['dashboard_session'])) {
        $selected_term = $_SESSION['dashboard_term'];
        $selected_session = $_SESSION['dashboard_session'];
    } else {
        // Get the latest term from database
        $latest_term_query = "SELECT DISTINCT term, session FROM results ORDER BY 
                              CASE term 
                                  WHEN 'First Term' THEN 1
                                  WHEN 'Second Term' THEN 2  
                                  WHEN 'Third Term' THEN 3
                                  ELSE 4
                              END DESC, session DESC LIMIT 1";
        $latest_term_result = mysqli_query($conn, $latest_term_query);
        if ($latest_term_result && mysqli_num_rows($latest_term_result) > 0) {
            $latest_data = mysqli_fetch_assoc($latest_term_result);
            $selected_term = $latest_data['term'];
            $selected_session = $latest_data['session'];
            $_SESSION['dashboard_term'] = $selected_term;
            $_SESSION['dashboard_session'] = $selected_session;
        } else {
            $selected_term = "First Term";
            $selected_session = date('Y');
        }
    }
    
    return ['term' => $selected_term, 'session' => $selected_session];
}

// Get all available terms from database
$available_terms_query = "SELECT DISTINCT term, session FROM results ORDER BY 
                          CASE term 
                              WHEN 'First Term' THEN 1
                              WHEN 'Second Term' THEN 2  
                              WHEN 'Third Term' THEN 3
                          END, session DESC";
$available_terms_result = mysqli_query($conn, $available_terms_query);
$available_terms = [];
while ($row = mysqli_fetch_assoc($available_terms_result)) {
    $available_terms[] = $row;
}

// Get current term/session
$current_term_session = getCurrentTermSession($conn);
$current_term = $current_term_session['term'];
$current_session = $current_term_session['session'];

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize statistics array
$stats = [
    'total_students' => 0,
    'school_average' => 0,
    'pass_rate' => 0,
    'total_teachers' => 0,
    'total_announcements' => 0,
    'today_attendance' => 0,
    'total_classes' => 0,
    'total_results' => 0,
    'students_with_results' => 0,
    'total_subjects' => 0
];

// 1. FIXED: Get basic counts - using simpler queries
// Get total students
$student_query = "SELECT COUNT(*) as total FROM students";
$student_result = mysqli_query($conn, $student_query);
if ($student_result && $row = mysqli_fetch_assoc($student_result)) {
    $stats['total_students'] = $row['total'] ?? 0;
}

// Get total teachers
$teacher_query = "SELECT COUNT(*) as total FROM teachers WHERE status = 'active'";
$teacher_result = mysqli_query($conn, $teacher_query);
if ($teacher_result && $row = mysqli_fetch_assoc($teacher_result)) {
    $stats['total_teachers'] = $row['total'] ?? 0;
}

// Get total classes
$classes_query = "SELECT COUNT(DISTINCT class_name) as total FROM classes";
$classes_result = mysqli_query($conn, $classes_query);
if ($classes_result && $row = mysqli_fetch_assoc($classes_result)) {
    $stats['total_classes'] = $row['total'] ?? 0;
}

// Get total announcements
$announcements_query = "SELECT COUNT(*) as total FROM announcements WHERE status = 'active'";
$announcements_result = mysqli_query($conn, $announcements_query);
if ($announcements_result && $row = mysqli_fetch_assoc($announcements_result)) {
    $stats['total_announcements'] = $row['total'] ?? 0;
}

// 2. FIXED: Get school performance data - CORRECTED PASS RATE CALCULATION
$performance_query = "SELECT 
    COUNT(DISTINCT r.student_id) as students_with_results,
    COALESCE(AVG(r.total_score), 0) as school_average,
    COUNT(r.id) as total_results,
    (SELECT COUNT(DISTINCT id) FROM subjects) as total_subjects
    FROM results r
    WHERE r.term = ? AND r.session = ?";
    
$perf_stmt = $conn->prepare($performance_query);
if ($perf_stmt) {
    $perf_stmt->bind_param("si", $current_term, $current_session);
    $perf_stmt->execute();
    $perf_result = $perf_stmt->get_result();
    if ($performance = $perf_result->fetch_assoc()) {
        $stats['total_results'] = $performance['total_results'] ?? 0;
        $stats['students_with_results'] = $performance['students_with_results'] ?? 0;
        $stats['school_average'] = round($performance['school_average'] ?? 0, 1);
        $stats['total_subjects'] = $performance['total_subjects'] ?? 8;
        
        // FIXED: Get pass rate separately to avoid calculation error
        if ($stats['students_with_results'] > 0) {
            $pass_rate_query = "SELECT 
                COUNT(DISTINCT student_id) as total_students,
                COUNT(DISTINCT CASE WHEN total_score >= 40 THEN student_id END) as passed_students
                FROM results 
                WHERE term = ? AND session = ?";
            
            $pass_stmt = $conn->prepare($pass_rate_query);
            if ($pass_stmt) {
                $pass_stmt->bind_param("si", $current_term, $current_session);
                $pass_stmt->execute();
                $pass_result = $pass_stmt->get_result();
                if ($pass_data = $pass_result->fetch_assoc()) {
                    $total_students = $pass_data['total_students'] ?? 0;
                    $passed_students = $pass_data['passed_students'] ?? 0;
                    
                    if ($total_students > 0) {
                        $stats['pass_rate'] = round(($passed_students / $total_students) * 100, 1);
                    }
                }
                $pass_stmt->close();
            }
        }
    }
    $perf_stmt->close();
}

// 3. FIXED: Get today's attendance percentage - CHECK IF TABLE EXISTS
$stats['today_attendance'] = 0; // Default value

// Check if attendance table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $today = date('Y-m-d');
    $attendance_query = "SELECT 
        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_count,
        COUNT(*) as total_count
        FROM attendance 
        WHERE DATE(date_recorded) = ?";
    
    $att_stmt = $conn->prepare($attendance_query);
    if ($att_stmt) {
        $att_stmt->bind_param("s", $today);
        $att_stmt->execute();
        $att_result = $att_stmt->get_result();
        if ($attendance = $att_result->fetch_assoc()) {
            if ($attendance['total_count'] > 0) {
                $stats['today_attendance'] = round(($attendance['present_count'] / $attendance['total_count']) * 100, 1);
            }
        }
        $att_stmt->close();
    }
}

// 4. Class-wise statistics
$class_statistics = [];
$class_stats_query = "SELECT 
    COALESCE(s.class_name, 'Unknown Class') as class_name,
    COUNT(DISTINCT s.id) as student_count,
    COALESCE(ROUND(AVG(r.total_score), 1), 0) as class_average,
    COUNT(DISTINCT r.student_id) as students_with_results,
    CASE 
        WHEN COUNT(DISTINCT r.student_id) = 0 THEN 'No Results'
        WHEN COUNT(DISTINCT r.student_id) = COUNT(DISTINCT s.id) THEN 'Complete'
        ELSE 'Partial'
    END as data_status
    FROM students s
    LEFT JOIN results r ON s.id = r.student_id AND r.term = ? AND r.session = ?
    GROUP BY s.class_name
    ORDER BY 
        CASE 
            WHEN s.class_name LIKE 'SSS%' THEN 100
            WHEN s.class_name LIKE 'JSS%' THEN 200
            ELSE 300
        END,
        CAST(SUBSTRING(s.class_name, 5) AS UNSIGNED)";

$class_stmt = $conn->prepare($class_stats_query);
if ($class_stmt) {
    $class_stmt->bind_param("si", $current_term, $current_session);
    $class_stmt->execute();
    $class_result = $class_stmt->get_result();
    while ($row = $class_result->fetch_assoc()) {
        $class_statistics[] = $row;
    }
    $class_stmt->close();
}

// 5. Teacher statistics  
$teacher_statistics = [];
$teacher_stats_query = "SELECT 
    t.id,
    t.name as full_name,
    COALESCE(t.subject, 'Not Assigned') as subject,
    COALESCE(t.classes, 'Not Assigned') as classes,
    t.email,
    t.status,
    COALESCE(COUNT(DISTINCT r.student_id), 0) as students_assessed,
    COALESCE(COUNT(r.id), 0) as total_results,
    COALESCE(ROUND(AVG(r.total_score), 1), 0) as average_score,
    CASE 
        WHEN COUNT(r.id) = 0 THEN 'No Data'
        WHEN COUNT(r.id) > 0 AND COUNT(r.id) < 5 THEN 'Low Data'
        WHEN COUNT(r.id) >= 5 THEN 'Active'
        ELSE 'Inactive'
    END as performance_status
    FROM teachers t
    LEFT JOIN results r ON t.id = r.teacher_id AND r.term = ? AND r.session = ?
    WHERE t.user_type IN ('teacher', 'class_teacher') AND t.status = 'active'
    GROUP BY t.id
    ORDER BY 
        CASE 
            WHEN COUNT(r.id) = 0 THEN 1
            ELSE 0
        END,
        AVG(r.total_score) DESC,
        t.name";

$teacher_stmt = $conn->prepare($teacher_stats_query);
if ($teacher_stmt) {
    $teacher_stmt->bind_param("si", $current_term, $current_session);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    while ($row = $teacher_result->fetch_assoc()) {
        $teacher_statistics[] = $row;
    }
    $teacher_stmt->close();
}

// 6. Top performing students
$top_students = [];
$top_students_query = "SELECT 
    s.name,
    COALESCE(s.class_name, 'Not Assigned') as class_name,
    COALESCE(s.roll_number, 'N/A') as roll_number,
    COALESCE(ROUND(AVG(r.total_score), 1), 0) as average_score,
    COUNT(DISTINCT r.subject_id) as subjects_count
    FROM results r
    JOIN students s ON r.student_id = s.id
    WHERE r.term = ? AND r.session = ?
    GROUP BY s.id
    HAVING subjects_count > 0
    ORDER BY average_score DESC
    LIMIT 10";
    
$top_stmt = $conn->prepare($top_students_query);
if ($top_stmt) {
    $top_stmt->bind_param("si", $current_term, $current_session);
    $top_stmt->execute();
    $top_result = $top_stmt->get_result();
    while ($row = $top_result->fetch_assoc()) {
        $top_students[] = $row;
    }
    $top_stmt->close();
}

// 7. Recent activities
$recent_activities = [];

// Get recent activities in one query
$activities_query = "(
    SELECT 'login' as activity_type, CONCAT('Principal logged in') as description, 
           created_at, name as performed_by
    FROM teachers 
    WHERE user_type = 'principal'
    ORDER BY created_at DESC 
    LIMIT 2
) UNION ALL (
    SELECT 'result' as activity_type, CONCAT('Submitted ', COUNT(r.id), ' results') as description,
           MAX(r.created_at) as created_at, t.name as performed_by
    FROM results r
    JOIN teachers t ON r.teacher_id = t.id
    WHERE r.term = ? AND r.session = ?
    GROUP BY r.teacher_id
    ORDER BY created_at DESC 
    LIMIT 3
) UNION ALL (
    SELECT 'announcement' as activity_type, CONCAT('Created: ', title) as description,
           created_at, 'Principal' as performed_by
    FROM announcements 
    WHERE created_by = ?
    ORDER BY created_at DESC 
    LIMIT 2
) ORDER BY created_at DESC 
LIMIT 7";

$act_stmt = $conn->prepare($activities_query);
if ($act_stmt) {
    $act_stmt->bind_param("sii", $current_term, $current_session, $principal_id);
    $act_stmt->execute();
    $act_result = $act_stmt->get_result();
    while ($row = $act_result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $act_stmt->close();
}

// Keep only top 5
$recent_activities = array_slice($recent_activities, 0, 5);

// 8. Subject performance data for chart
$subject_performance = [];
$subject_perf_query = "SELECT 
    COALESCE(sb.subject_name, 'Unknown Subject') as subject_name,
    COUNT(DISTINCT r.student_id) as student_count,
    COALESCE(AVG(r.total_score), 0) as average_score
    FROM results r
    LEFT JOIN subjects sb ON r.subject_id = sb.id
    WHERE r.term = ? AND r.session = ?
    GROUP BY sb.id
    HAVING average_score > 0
    ORDER BY average_score DESC
    LIMIT 8";
    
$subj_stmt = $conn->prepare($subject_perf_query);
if ($subj_stmt) {
    $subj_stmt->bind_param("si", $current_term, $current_session);
    $subj_stmt->execute();
    $subj_result = $subj_stmt->get_result();
    while ($row = $subj_result->fetch_assoc()) {
        $subject_performance[] = $row;
    }
    $subj_stmt->close();
}

// 9. Get school details
$school_info = [
    'school_name' => 'Our School',
    'address' => '123 School Street, City',
    'phone' => '+1234567890',
    'email' => 'info@school.edu'
];

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'school_info'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $school_query = "SELECT school_name, address, phone, email FROM school_info LIMIT 1";
    $school_result = mysqli_query($conn, $school_query);
    if ($school_result && mysqli_num_rows($school_result) > 0) {
        $school_info = mysqli_fetch_assoc($school_result);
    }
}

// 10. Get latest announcements
$latest_announcements_query = "SELECT 
    title, 
    message,
    priority,
    created_at 
    FROM announcements 
    WHERE status = 'active'
    ORDER BY created_at DESC 
    LIMIT 3";
$latest_ann_result = mysqli_query($conn, $latest_announcements_query);
$latest_announcements = [];
if ($latest_ann_result) {
    while ($row = mysqli_fetch_assoc($latest_ann_result)) {
        $latest_announcements[] = $row;
    }
}

// Calculate overall data completeness
$total_possible_results = $stats['total_students'] * max($stats['total_subjects'], 1);
$data_completeness = $total_possible_results > 0 ? round(($stats['total_results'] / $total_possible_results) * 100, 1) : 0;

// DEBUG: Check what data we're getting
error_log("DEBUG - Dashboard Stats:");
error_log("Total Students: " . $stats['total_students']);
error_log("Total Teachers: " . $stats['total_teachers']);
error_log("Total Classes: " . $stats['total_classes']);
error_log("School Average: " . $stats['school_average']);
error_log("Pass Rate: " . $stats['pass_rate']);
error_log("Total Results: " . $stats['total_results']);
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $dark_mode ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
                            950: '#030712',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Principal Dashboard - <?php echo htmlspecialchars($school_info['school_name']); ?></title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            transition: background-color 0.3s ease;
        }
        
        /* Light Mode Styles */
        body:not(.dark) {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        /* Dark Mode Styles */
        body.dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: #e5e7eb;
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        body.dark .sidebar {
            background: rgba(31, 41, 55, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        body:not(.dark) .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        body.dark .card {
            background: rgba(31, 41, 55, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e5e7eb;
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        body:not(.dark) .stat-card:hover {
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
        }
        
        body.dark .stat-card:hover {
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.4);
        }
        
        .academic-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .performance-excellent { background-color: #10B981; color: white; }
        .performance-good { background-color: #34D399; color: white; }
        .performance-average { background-color: #60A5FA; color: white; }
        .performance-poor { background-color: #F59E0B; color: white; }
        .performance-very-poor { background-color: #EF4444; color: white; }
        
        .activity-item {
            border-left: 4px solid #10b981;
            padding-left: 12px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }
        
        body.dark .activity-item:hover {
            background-color: rgba(16, 185, 129, 0.1);
        }
        
        body:not(.dark) .activity-item:hover {
            background-color: #f0fdf4;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        
        .academic-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .academic-table {
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body.dark .academic-table {
            background: #1f2937;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .academic-table th, .academic-table td {
            padding: 12px 8px;
            text-align: left;
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .academic-table th, 
        body:not(.dark) .academic-table td {
            border: 1px solid #e5e7eb;
        }
        
        body.dark .academic-table th, 
        body.dark .academic-table td {
            border: 1px solid #374151;
        }
        
        body:not(.dark) .academic-table th {
            background-color: #f8fafc;
            color: #374151;
        }
        
        body.dark .academic-table th {
            background-color: #111827;
            color: #e5e7eb;
        }
        
        body:not(.dark) .academic-table tr:hover {
            background-color: #f9fafb;
        }
        
        body.dark .academic-table tr:hover {
            background-color: #374151;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .modal-content {
            background-color: white;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        body.dark .modal-content {
            background-color: #1f2937;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            color: #e5e7eb;
        }
        
        .announcement-badge {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        body:not(.dark) .priority-normal { background: #e5e7eb; color: #374151; }
        body.dark .priority-normal { background: #374151; color: #e5e7eb; }
        
        body:not(.dark) .priority-important { background: #fef3c7; color: #92400e; }
        body.dark .priority-important { background: #92400e; color: #fef3c7; }
        
        body:not(.dark) .priority-urgent { background: #fee2e2; color: #991b1b; }
        body.dark .priority-urgent { background: #991b1b; color: #fee2e2; }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .progress-bar {
            background-color: #e5e7eb;
        }
        
        body.dark .progress-bar {
            background-color: #374151;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .term-selector {
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        body:not(.dark) .term-selector {
            background: white;
            border: 2px solid #e5e7eb;
            color: #374151;
        }
        
        body.dark .term-selector {
            background: #374151;
            border: 2px solid #4b5563;
            color: #e5e7eb;
        }
        
        .term-selector:hover {
            border-color: #10b981;
        }
        
        body:not(.dark) .term-selector:hover {
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }
        
        body.dark .term-selector:hover {
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        
        .term-selector:focus {
            outline: none;
            border-color: #10b981;
        }
        
        body:not(.dark) .term-selector:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        body.dark .term-selector:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(16, 185, 129, 0.3);
            border-radius: 50%;
            border-top-color: #10b981;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Dark mode specific styles */
        body.dark .text-gray-800 { color: #e5e7eb !important; }
        body.dark .text-gray-700 { color: #d1d5db !important; }
        body.dark .text-gray-600 { color: #9ca3af !important; }
        body.dark .text-gray-500 { color: #6b7280 !important; }
        body.dark .text-gray-400 { color: #4b5563 !important; }
        body.dark .text-gray-300 { color: #374151 !important; }
        
        body.dark .bg-gray-50 { background-color: #111827 !important; }
        body.dark .bg-gray-100 { background-color: #1f2937 !important; }
        body.dark .bg-gray-200 { background-color: #374151 !important; }
        body.dark .bg-gray-300 { background-color: #4b5563 !important; }
        
        body.dark .border-gray-200 { border-color: #374151 !important; }
        body.dark .border-gray-300 { border-color: #4b5563 !important; }
        
        /* Theme toggle button */
        .theme-toggle {
            position: relative;
            width: 60px;
            height: 30px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        body:not(.dark) .theme-toggle {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        body.dark .theme-toggle {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        
        .theme-toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        body.dark .theme-toggle-slider {
            transform: translateX(30px);
        }
        
        .theme-toggle-slider i {
            font-size: 12px;
            transition: color 0.3s ease;
        }
        
        body:not(.dark) .theme-toggle-slider i {
            color: #f59e0b;
        }
        
        body.dark .theme-toggle-slider i {
            color: #fbbf24;
        }
        
        /* Dark mode form styles */
        body.dark input,
        body.dark select,
        body.dark textarea {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        
        body.dark input::placeholder,
        body.dark textarea::placeholder {
            color: #9ca3af !important;
        }
        
        body.dark input:focus,
        body.dark select:focus,
        body.dark textarea:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2) !important;
        }
        
        /* Dark mode modal header */
        body.dark .modal-content .bg-purple-500 {
            background-color: #7c3aed !important;
        }
        
        /* Dark mode links */
        body.dark a {
            color: #60a5fa !important;
        }
        
        body.dark a:hover {
            color: #93c5fd !important;
        }
        
        /* Dark mode buttons */
        body.dark .bg-green-500 { background-color: #059669 !important; }
        body.dark .bg-green-600 { background-color: #047857 !important; }
        body.dark .bg-purple-500 { background-color: #7c3aed !important; }
        body.dark .bg-purple-600 { background-color: #6d28d9 !important; }
        body.dark .bg-blue-500 { background-color: #2563eb !important; }
        body.dark .bg-blue-600 { background-color: #1d4ed8 !important; }
        body.dark .bg-red-500 { background-color: #dc2626 !important; }
        body.dark .bg-red-600 { background-color: #b91c1c !important; }
        
        /* Dark mode gradients */
        body.dark .bg-gradient-to-r {
            background-image: linear-gradient(to right, var(--tw-gradient-stops)) !important;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            .card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            
            .sidebar {
                display: none !important;
            }
            
            .flex-1 {
                width: 100% !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold text-gray-800 dark:text-gray-200">Principal Portal</span>
            </div>
        </div>

        <!-- Principal Info -->
        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-4 mb-6 border border-green-200 dark:border-green-800">
            <div class="bg-green-500 text-white rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-crown mr-2"></i>
                School Principal
            </div>
            <h3 class="font-semibold text-green-800 dark:text-green-300 text-sm"><?php echo htmlspecialchars($principal_name); ?></h3>
            <p class="text-green-600 dark:text-green-400 text-xs"><?php echo htmlspecialchars($school_info['school_name']); ?></p>
            <p class="text-green-500 dark:text-green-400 text-xs mt-1">
                <i class="fas fa-calendar mr-1"></i>
                <?php echo $current_term . ' - ' . $current_session; ?>
            </p>
        </div>

        <nav class="space-y-2">
            <a href="principal_dashboard.php" class="flex items-center space-x-3 p-3 text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="principal_academic_reports.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-chart-bar"></i>
                <span class="font-medium">Academic Reports</span>
            </a>
            <a href="principal_staff_management.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-users"></i>
                <span class="font-medium">Staff Management</span>
            </a>
            <a href="principal_student_analytics.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-user-graduate"></i>
                <span class="font-medium">Student Analytics</span>
            </a>
            <a href="principal_class_management.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-chalkboard"></i>
                <span class="font-medium">Class Management</span>
            </a>
            <a href="principal_announcements.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-bullhorn"></i>
                <span class="font-medium">Announcements</span>
            </a>
            <a href="principal_settings.php" class="flex items-center space-x-3 p-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition duration-200">
                <i class="fas fa-cog"></i>
                <span class="font-medium">Settings</span>
            </a>
            
            <!-- Theme Toggle -->
            <div class="flex items-center justify-between p-3">
                <div class="flex items-center space-x-2">
                    <i class="fas <?php echo $dark_mode ? 'fa-moon text-yellow-400' : 'fa-sun text-yellow-500'; ?>"></i>
                    <span class="text-gray-700 dark:text-gray-300 font-medium">Theme</span>
                </div>
                <a href="?toggle_dark_mode=1" class="theme-toggle">
                    <div class="theme-toggle-slider">
                        <i class="fas <?php echo $dark_mode ? 'fa-moon' : 'fa-sun'; ?>"></i>
                    </div>
                </a>
            </div>
            
            <a href="logout.php" class="flex items-center space-x-3 p-3 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition duration-200">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
        
        <!-- Data Status -->
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Data Status</h4>
            <div class="space-y-2">
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-600 dark:text-gray-400">Results Entry</span>
                        <span class="font-medium <?php echo $data_completeness > 0 ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'; ?>">
                            <?php echo $data_completeness; ?>%
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $data_completeness > 0 ? 'bg-green-500' : 'bg-yellow-500'; ?>" 
                             style="width: <?php echo min($data_completeness, 100); ?>%"></div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-database mr-1"></i>
                    <?php echo $stats['students_with_results']; ?> of <?php echo $stats['total_students']; ?> students have results
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white dark:text-gray-100">Principal Dashboard</h1>
                <p class="text-white/80 dark:text-gray-300">Academic oversight and school management for <?php echo htmlspecialchars($school_info['school_name']); ?></p>
                <p class="text-white/60 dark:text-gray-400 text-sm mt-1">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?php echo $current_term . ' - ' . $current_session; ?>
                    <span class="ml-3">
                        <i class="fas fa-database mr-1"></i>
                        <?php echo $stats['total_results'] > 0 ? 'Showing ' . $stats['total_results'] . ' Results' : 'Awaiting Results Entry'; ?>
                    </span>
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Print Button -->
                <button onclick="window.print()" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg font-medium transition duration-200 flex items-center space-x-2 no-print">
                    <i class="fas fa-print"></i>
                    <span>Print Report</span>
                </button>
                
                <!-- Term Selection Dropdown -->
                <form method="GET" action="" id="termForm" class="bg-white dark:bg-gray-800 rounded-lg no-print">
                    <select name="selected_term" id="selected_term" class="term-selector">
                        <option value="">Select Term</option>
                        <?php foreach ($available_terms as $term_option): ?>
                            <option value="<?php echo htmlspecialchars($term_option['term']); ?>" 
                                    data-session="<?php echo htmlspecialchars($term_option['session']); ?>"
                                    <?php echo ($term_option['term'] == $current_term && $term_option['session'] == $current_session) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($term_option['term'] . ' ' . $term_option['session']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="selected_session" id="selected_session" value="<?php echo $current_session; ?>">
                </form>
                
                <div class="bg-white/20 dark:bg-gray-800/50 text-white dark:text-gray-200 px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-clock mr-2"></i>
                    <span id="currentDateTime"><?php echo date('l, F j, Y'); ?></span>
                </div>
                <button onclick="showAnnouncementModal()" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition duration-200 flex items-center space-x-2 no-print">
                    <i class="fas fa-plus"></i>
                    <span>New Announcement</span>
                </button>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card stat-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total Students</p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?php echo $stats['total_students']; ?></h3>
                        <div class="flex items-center mt-2">
                            <span class="text-green-500 text-sm font-medium">
                                <i class="fas fa-users mr-1"></i>
                                Across <?php echo $stats['total_classes']; ?> Classes
                            </span>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-green-600 dark:text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card stat-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">School Average</p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?php echo $stats['school_average']; ?>%</h3>
                        <div class="flex items-center mt-2">
                            <?php 
                            $performance_class = 'performance-average';
                            $performance_text = 'Average';
                            if ($stats['school_average'] >= 80) {
                                $performance_class = 'performance-excellent';
                                $performance_text = 'Excellent';
                            } elseif ($stats['school_average'] >= 70) {
                                $performance_class = 'performance-good';
                                $performance_text = 'Good';
                            } elseif ($stats['school_average'] >= 50) {
                                $performance_class = 'performance-average';
                                $performance_text = 'Average';
                            } elseif ($stats['school_average'] >= 40) {
                                $performance_class = 'performance-poor';
                                $performance_text = 'Poor';
                            } else {
                                $performance_class = 'performance-very-poor';
                                $performance_text = 'Very Poor';
                            }
                            
                            if ($stats['school_average'] == 0) {
                                $performance_class = 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300';
                                $performance_text = 'No Data';
                            }
                            ?>
                            <span class="<?php echo $performance_class; ?> px-2 py-1 rounded-full text-xs font-bold">
                                <?php echo $performance_text; ?>
                            </span>
                        </div>
                    </div>
                    <div class="w-12 h-12 <?php echo $stats['school_average'] == 0 ? 'bg-gray-100 dark:bg-gray-800' : 'bg-blue-100 dark:bg-blue-900/30'; ?> rounded-full flex items-center justify-center">
                        <i class="fas <?php echo $stats['school_average'] == 0 ? 'fa-question text-gray-600 dark:text-gray-400' : 'fa-chart-line text-blue-600 dark:text-blue-400'; ?> text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card stat-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Pass Rate</p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?php echo $stats['pass_rate']; ?>%</h3>
                        <div class="flex items-center mt-2">
                            <span class="<?php echo $stats['pass_rate'] > 0 ? 'text-green-500' : 'text-gray-500 dark:text-gray-400'; ?> text-sm font-medium">
                                <i class="fas <?php echo $stats['pass_rate'] > 0 ? 'fa-check-circle' : 'fa-question-circle'; ?> mr-1"></i>
                                <?php echo $stats['pass_rate'] > 0 ? 'Based on ' . $stats['total_results'] . ' results' : 'No results yet'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="w-12 h-12 <?php echo $stats['pass_rate'] > 0 ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-gray-100 dark:bg-gray-800'; ?> rounded-full flex items-center justify-center">
                        <i class="fas <?php echo $stats['pass_rate'] > 0 ? 'fa-trophy text-yellow-600 dark:text-yellow-400' : 'fa-hourglass-half text-gray-600 dark:text-gray-400'; ?> text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card stat-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Active Teachers</p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?php echo $stats['total_teachers']; ?></h3>
                        <div class="flex items-center mt-2">
                            <span class="text-blue-500 text-sm font-medium">
                                <i class="fas fa-chalkboard-teacher mr-1"></i>
                                <?php echo $stats['today_attendance']; ?>% Today's Attendance
                            </span>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-tie text-purple-600 dark:text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($stats['total_results'] == 0): ?>
        <!-- Setup Guide for New System -->
        <div class="card p-6 mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-rocket text-blue-500 dark:text-blue-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2">Welcome to Your School Management System!</h3>
                    <p class="text-gray-700 dark:text-gray-300 mb-4">
                        Your dashboard is ready but needs data. Follow these steps to get started:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="guide_results_entry.php" class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-600 transition duration-200">
                            <div class="text-blue-500 dark:text-blue-400 mb-2">
                                <i class="fas fa-graduation-cap text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-100 mb-1">1. Enter Results</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Teachers need to enter exam results to see performance data.</p>
                        </a>
                        <a href="principal_staff_management.php" class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-600 transition duration-200">
                            <div class="text-purple-500 dark:text-purple-400 mb-2">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-100 mb-1">2. Assign Subjects</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Make sure teachers have subjects assigned for results entry.</p>
                        </a>
                        <a href="principal_announcements.php" class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-600 transition duration-200">
                            <div class="text-green-500 dark:text-green-400 mb-2">
                                <i class="fas fa-bullhorn text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-100 mb-1">3. Send Reminder</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Notify teachers to submit their term results.</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts and Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Subject Performance Chart -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                        <i class="fas fa-chart-bar text-green-500 mr-3"></i>
                        Subject Performance
                    </h3>
                    <span class="academic-badge"><?php echo $current_term . ' ' . $current_session; ?></span>
                </div>
                <div class="chart-container">
                    <canvas id="subjectPerformanceChart"></canvas>
                </div>
                <?php if (empty($subject_performance)): ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-chart-line text-xl mb-2"></i>
                    <p class="text-sm">No subject performance data available yet</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Teachers need to enter exam results</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Top Performing Students -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                        <i class="fas fa-medal text-yellow-500 mr-3"></i>
                        <?php echo !empty($top_students) ? 'Top 10 Students' : 'Student Performance'; ?>
                    </h3>
                    <span class="academic-badge"><?php echo $current_term . ' ' . $current_session; ?></span>
                </div>
                <div class="space-y-4">
                    <?php if (!empty($top_students)): ?>
                        <?php foreach ($top_students as $index => $student): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 <?php echo $index < 3 ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-green-100 dark:bg-green-900/30'; ?> rounded-full flex items-center justify-center">
                                    <span class="<?php echo $index < 3 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400'; ?> font-bold text-sm"><?php echo $index + 1; ?></span>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($student['name']); ?></h4>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs">
                                        <?php echo htmlspecialchars($student['class_name']); ?> • 
                                        Roll: <?php echo htmlspecialchars($student['roll_number']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold <?php echo $student['average_score'] >= 70 ? 'text-green-600 dark:text-green-400' : ($student['average_score'] >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'); ?>">
                                    <?php echo round($student['average_score'], 1); ?>%
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 text-xs"><?php echo $student['subjects_count']; ?> subjects</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-graduate text-gray-400 dark:text-gray-600 text-2xl"></i>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300">No student performance data available</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Exam results need to be entered by teachers</p>
                            <a href="principal_staff_management.php" class="inline-block mt-4 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium">
                                <i class="fas fa-external-link-alt mr-1"></i> Manage Teachers
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Tables Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Class Statistics -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                        <i class="fas fa-chalkboard text-blue-500 mr-3"></i>
                        Class-wise Statistics
                    </h3>
                    <div class="flex items-center space-x-2">
                        <span class="academic-badge"><?php echo $current_term . ' ' . $current_session; ?></span>
                        <?php if (array_sum(array_column($class_statistics, 'students_with_results')) == 0): ?>
                        <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 px-3 py-1 rounded-full text-xs font-semibold">
                            <i class="fas fa-info-circle mr-1"></i> No Results Yet
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (array_sum(array_column($class_statistics, 'students_with_results')) == 0): ?>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 dark:border-yellow-600 p-4 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-lightbulb text-yellow-400 dark:text-yellow-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">No Results Entered Yet</h4>
                            <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">
                                Teachers need to enter exam results to see performance data. 
                                <a href="principal_staff_management.php" class="font-medium underline">Remind teachers</a> 
                                to submit their results.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Students</th>
                                <th>Average Score</th>
                                <th>With Results</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($class_statistics)): ?>
                                <?php foreach ($class_statistics as $class): ?>
                                <tr>
                                    <td class="font-semibold">
                                        <div class="flex items-center">
                                            <i class="fas fa-chalkboard-teacher text-gray-400 mr-2"></i>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-medium text-gray-800 dark:text-gray-100"><?php echo $class['student_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($class['class_average'] > 0): ?>
                                            <div class="flex items-center">
                                                <span class="font-bold <?php echo $class['class_average'] >= 50 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                                    <?php echo $class['class_average']; ?>%
                                                </span>
                                                <?php if ($class['class_average'] >= 70): ?>
                                                    <i class="fas fa-arrow-up text-green-500 ml-1"></i>
                                                <?php elseif ($class['class_average'] < 50): ?>
                                                    <i class="fas fa-arrow-down text-red-500 ml-1"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 flex items-center">
                                                <i class="fas fa-minus mr-1"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex items-center">
                                            <span class="font-medium <?php echo $class['students_with_results'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'; ?>">
                                                <?php echo $class['students_with_results']; ?> / <?php echo $class['student_count']; ?>
                                            </span>
                                            <?php if ($class['students_with_results'] == $class['student_count'] && $class['students_with_results'] > 0): ?>
                                                <i class="fas fa-check-circle text-green-500 ml-1"></i>
                                            <?php elseif ($class['students_with_results'] > 0 && $class['students_with_results'] < $class['student_count']): ?>
                                                <span class="text-xs text-yellow-600 dark:text-yellow-400 ml-1"><?php echo round(($class['students_with_results'] / $class['student_count']) * 100); ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300';
                                        $status_icon = 'fas fa-clock';
                                        $status_text = 'No Results';
                                        
                                        if ($class['data_status'] == 'Complete') {
                                            $status_class = 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                                            $status_icon = 'fas fa-check-circle';
                                            $status_text = 'Complete';
                                        } elseif ($class['data_status'] == 'Partial') {
                                            $status_class = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                                            $status_icon = 'fas fa-sync-alt';
                                            $status_text = 'Partial';
                                        } elseif ($class['data_status'] == 'No Data') {
                                            $status_class = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300';
                                            $status_icon = 'fas fa-exclamation-circle';
                                            $status_text = 'No Data';
                                        }
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?> mr-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-chalkboard-teacher text-3xl mb-2"></i>
                                        <p>No class data available</p>
                                        <p class="text-xs mt-2">Add classes and students first</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <?php if (array_sum(array_column($class_statistics, 'students_with_results')) == 0): ?>
                    <div class="mt-4 text-center">
                        <a href="guide_results_entry.php" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium text-sm">
                            <i class="fas fa-question-circle mr-1"></i>
                            How to enter results?
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teacher Performance -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                        <i class="fas fa-users text-purple-500 mr-3"></i>
                        Teacher Performance
                    </h3>
                    <div class="flex items-center space-x-2">
                        <span class="academic-badge"><?php echo $current_term . ' ' . $current_session; ?></span>
                        <?php if (array_sum(array_column($teacher_statistics, 'total_results')) == 0): ?>
                        <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 px-3 py-1 rounded-full text-xs font-semibold">
                            <i class="fas fa-chalkboard mr-1"></i> Awaiting Results
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (array_sum(array_column($teacher_statistics, 'total_results')) == 0): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 dark:border-blue-600 p-4 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 dark:text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">Teachers Ready to Enter Results</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-400 mt-1">
                                Your teachers are set up but haven't entered results yet. 
                                <a href="principal_announcements.php" class="font-medium underline">Send a reminder</a> 
                                or check if they need training.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Students</th>
                                <th>Average</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($teacher_statistics)): ?>
                                <?php foreach ($teacher_statistics as $teacher): ?>
                                <tr>
                                    <td class="font-semibold"><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                                    <td><?php echo $teacher['students_assessed']; ?></td>
                                    <td>
                                        <?php if ($teacher['average_score'] > 0): ?>
                                            <span class="font-bold <?php echo $teacher['average_score'] >= 50 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                                <?php echo $teacher['average_score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 flex items-center">
                                                <i class="fas fa-minus mr-1"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300';
                                        $status_icon = 'fas fa-clock';
                                        $status_text = 'No Data';
                                        
                                        if ($teacher['performance_status'] == 'Active') {
                                            $status_class = 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                                            $status_icon = 'fas fa-check-circle';
                                            $status_text = 'Active';
                                        } elseif ($teacher['performance_status'] == 'Low Data') {
                                            $status_class = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                                            $status_icon = 'fas fa-sync-alt';
                                            $status_text = 'Low Data';
                                        } elseif ($teacher['performance_status'] == 'Inactive') {
                                            $status_class = 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                                            $status_icon = 'fas fa-times-circle';
                                            $status_text = 'Inactive';
                                        }
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?> mr-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-user-tie text-2xl mb-2"></i>
                                        <p>No teacher data available</p>
                                        <p class="text-xs mt-2">Add teachers first in Staff Management</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <?php if (array_sum(array_column($teacher_statistics, 'total_results')) == 0): ?>
                    <div class="mt-4 text-center">
                        <a href="principal_staff_management.php" class="inline-flex items-center text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i>
                            Go to Staff Management
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Recent Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Latest Announcements -->
            <div class="card p-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center">
                    <i class="fas fa-bullhorn text-red-500 mr-3"></i>
                    Latest Announcements
                </h3>
                <div class="space-y-4">
                    <?php if (!empty($latest_announcements)): ?>
                        <?php foreach ($latest_announcements as $announcement): ?>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border-l-4 border-purple-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-200">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <span class="priority-badge priority-<?php echo htmlspecialchars($announcement['priority']); ?>">
                                    <?php echo ucfirst($announcement['priority']); ?>
                                </span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 text-sm mb-2"><?php echo substr(htmlspecialchars($announcement['message']), 0, 80); ?>...</p>
                            <p class="text-gray-400 dark:text-gray-500 text-xs">
                                <i class="far fa-clock mr-1"></i>
                                <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                        <a href="principal_announcements.php" class="block text-center text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium text-sm mt-4">
                            <i class="fas fa-eye mr-1"></i> View All Announcements (<?php echo $stats['total_announcements']; ?>)
                        </a>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-bullhorn text-3xl mb-3"></i>
                            <p>No announcements yet</p>
                            <button onclick="showAnnouncementModal()" class="mt-3 text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-medium">
                                <i class="fas fa-plus mr-1"></i> Create First Announcement
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                            <i class="fas fa-history text-indigo-500 mr-3"></i>
                            Recent Activities
                        </h3>
                        <span class="announcement-badge">Real Time Updates</span>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($activity['description']); ?></h4>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">
                                            <i class="fas fa-user mr-1"></i>
                                            <?php echo htmlspecialchars($activity['performed_by'] ?? 'System'); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">
                                            <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                                        </span>
                                        <div class="mt-1">
                                            <?php 
                                            $activity_bg = 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300';
                                            if ($activity['activity_type'] == 'login') $activity_bg = 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                                            elseif ($activity['activity_type'] == 'result') $activity_bg = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                                            elseif ($activity['activity_type'] == 'announcement') $activity_bg = 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300';
                                            elseif ($activity['activity_type'] == 'update') $activity_bg = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300';
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $activity_bg; ?>">
                                                <?php echo ucfirst($activity['activity_type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <i class="fas fa-stream text-2xl mb-3"></i>
                                <p>No recent activities</p>
                                <p class="text-xs mt-2">Activities will appear as teachers submit results</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="bg-purple-500 text-white p-4 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Create New Announcement</h3>
                <button onclick="closeAnnouncementModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="announcementForm" class="p-6" action="principal_announcements.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="create_announcement" value="1">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Announcement Title</label>
                    <input type="text" name="title" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                           placeholder="e.g., Mid-term Break Notice" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Announcement Message</label>
                    <textarea name="message" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                              placeholder="Enter the full announcement message..." required></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Audience</label>
                        <select name="audience" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="all">All (Staff & Students)</option>
                            <option value="staff">Staff Only</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAnnouncementModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Publish Announcement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Update every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initial call
        
        // Chart.js - Subject Performance
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($subject_performance)): ?>
            const subjectLabels = <?php echo json_encode(array_column($subject_performance, 'subject_name')); ?>;
            const subjectAverages = <?php echo json_encode(array_column($subject_performance, 'average_score')); ?>;
            
            const ctx = document.getElementById('subjectPerformanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: subjectLabels,
                    datasets: [{
                        label: 'Average Score (%)',
                        data: subjectAverages,
                        backgroundColor: subjectAverages.map(avg => {
                            if (!avg || avg == 0) return document.body.classList.contains('dark') ? '#374151' : '#e5e7eb';
                            if (avg >= 80) return '#10b981';
                            if (avg >= 70) return '#34d399';
                            if (avg >= 50) return '#60a5fa';
                            if (avg >= 40) return '#f59e0b';
                            return '#ef4444';
                        }),
                        borderColor: subjectAverages.map(avg => {
                            if (!avg || avg == 0) return document.body.classList.contains('dark') ? '#4b5563' : '#9ca3af';
                            if (avg >= 80) return '#059669';
                            if (avg >= 70) return '#10b981';
                            if (avg >= 50) return '#3b82f6';
                            if (avg >= 40) return '#d97706';
                            return '#dc2626';
                        }),
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: document.body.classList.contains('dark') ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                color: document.body.classList.contains('dark') ? '#e5e7eb' : '#374151',
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: document.body.classList.contains('dark') ? '#e5e7eb' : '#374151',
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Average: ${context.raw}%`;
                                }
                            }
                        }
                    }
                }
            });
            <?php else: ?>
            // Create empty chart if no data
            const ctx = document.getElementById('subjectPerformanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['No Data Available'],
                    datasets: [{
                        label: 'Average Score',
                        data: [0],
                        backgroundColor: document.body.classList.contains('dark') ? '#374151' : '#e5e7eb',
                        borderColor: document.body.classList.contains('dark') ? '#4b5563' : '#9ca3af',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            max: 100,
                            ticks: {
                                color: document.body.classList.contains('dark') ? '#e5e7eb' : '#374151',
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                color: document.body.classList.contains('dark') ? '#e5e7eb' : '#374151'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Awaiting results entry';
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
        
        // FIXED: Term selection handler
        document.getElementById('selected_term').addEventListener('change', function(e) {
            e.preventDefault();
            const selectedOption = this.options[this.selectedIndex];
            const sessionValue = selectedOption.getAttribute('data-session');
            
            if (sessionValue) {
                document.getElementById('selected_session').value = sessionValue;
                // Show loading indicator
                const submitBtn = document.createElement('button');
                submitBtn.innerHTML = '<div class="loading"></div>';
                submitBtn.className = 'ml-2 px-4 py-2 bg-green-500 text-white rounded';
                document.getElementById('termForm').appendChild(submitBtn);
                
                // Submit form
                setTimeout(() => {
                    document.getElementById('termForm').submit();
                }, 300);
            }
        });
        
        // Modal functions
        function showAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'block';
        }
        
        function closeAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'none';
            document.getElementById('announcementForm').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcementModal');
            if (event.target == modal) {
                closeAnnouncementModal();
            }
        }
        
        // Handle announcement form submission
        document.getElementById('announcementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading"></div>';
            
            try {
                const response = await fetch('principal_announcements.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    alert('Announcement published successfully!');
                    closeAnnouncementModal();
                    location.reload();
                } else {
                    alert('Error: Could not save announcement');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Network error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>