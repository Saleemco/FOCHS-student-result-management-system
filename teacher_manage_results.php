<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    session_regenerate_id(true);
    header('Location: teacher_login.php');
    exit();
}

// Validate all required session variables
if (!isset($_SESSION['teacher_name']) || !isset($_SESSION['teacher_classes']) || !isset($_SESSION['teacher_subject'])) {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_classes = array_map('trim', explode(',', $_SESSION['teacher_classes']));

$success = '';
$error = '';

// Sanitization function
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Handle filter parameters with validation
$filter_class = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$filter_search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_term = isset($_GET['term']) ? sanitize_input($_GET['term']) : '';

// Validate term against allowed values
$available_terms = ['First Term', 'Second Term', 'Third Term'];
if ($filter_term && !in_array($filter_term, $available_terms)) {
    $filter_term = '';
}

// Get all subjects from database
$subjects = [];
$subjects_result = mysqli_query($conn, "SELECT id, subject_name FROM subjects");
if ($subjects_result) {
    while ($subject_row = mysqli_fetch_assoc($subjects_result)) {
        $subjects[$subject_row['id']] = $subject_row['subject_name'];
    }
}

// Handle result deletion
if (isset($_POST['delete_result'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $result_id = mysqli_real_escape_string($conn, $_POST['result_id']);
        
        $verify_sql = "SELECT id FROM results WHERE id = '$result_id' AND teacher_id = '$teacher_id'";
        $verify_result = mysqli_query($conn, $verify_sql);
        
        if ($verify_result && mysqli_num_rows($verify_result) > 0) {
            $delete_sql = "DELETE FROM results WHERE id = '$result_id'";
            if (mysqli_query($conn, $delete_sql)) {
                $success = "Result deleted successfully!";
            } else {
                error_log("Delete error: " . mysqli_error($conn));
                $error = "Error deleting result. Please try again.";
            }
        } else {
            $error = "You don't have permission to delete this result or result not found.";
        }
    }
}

// Handle result editing
if (isset($_POST['edit_result'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $result_id = mysqli_real_escape_string($conn, $_POST['result_id']);
        $ca_score = (float)$_POST['ca_score'];
        $exam_score = (float)$_POST['exam_score'];
        $total_score = $ca_score + $exam_score;
        
        // Validate scores
        if ($ca_score < 0 || $ca_score > 40) {
            $error = "CA score must be between 0 and 40";
        } elseif ($exam_score < 0 || $exam_score > 60) {
            $error = "Exam score must be between 0 and 60";
        } else {
            $update_sql = "UPDATE results SET 
                          ca_score = '$ca_score',
                          exam_score = '$exam_score',
                          total_score = '$total_score'
                          WHERE id = '$result_id' AND teacher_id = '$teacher_id'";
            
            if (mysqli_query($conn, $update_sql)) {
                $success = "Result updated successfully!";
            } else {
                error_log("Update error: " . mysqli_error($conn));
                $error = "Error updating result. Please try again.";
            }
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// FIXED QUERY: Added DISTINCT to prevent duplicates
$query = "SELECT DISTINCT 
    r.id as result_id,
    r.student_id,
    r.subject_id,
    r.term,
    r.session,
    r.ca_score,
    r.exam_score,
    r.total_score,
    r.teacher_id,
    r.created_at,
    s.name as student_name,
    s.roll_number,
    s.class_name,
    s.gender,
    sub.subject_name
FROM results r
JOIN students s ON r.student_id = s.id
JOIN subjects sub ON r.subject_id = sub.id
WHERE r.teacher_id = '$teacher_id'";

// Add filters with prepared statement style (simplified for now)
if ($filter_class) {
    $query .= " AND s.class_name = '" . mysqli_real_escape_string($conn, $filter_class) . "'";
}
if ($filter_search) {
    $search_clean = mysqli_real_escape_string($conn, $filter_search);
    $query .= " AND (s.name LIKE '%$search_clean%' OR s.roll_number LIKE '%$search_clean%')";
}
if ($filter_term) {
    $query .= " AND r.term = '" . mysqli_real_escape_string($conn, $filter_term) . "'";
}

$query .= " ORDER BY s.class_name, s.name, r.term, sub.subject_name";

// Execute query
$results_result = mysqli_query($conn, $query);

if (!$results_result) {
    error_log("Query error: " . mysqli_error($conn));
    $error = "Database error occurred. Please try again.";
}

// Process results for display with duplicate prevention
$students_results = [];
$processed_results = []; // Track processed result combinations

if ($results_result && mysqli_num_rows($results_result) > 0) {
    while ($row = mysqli_fetch_assoc($results_result)) {
        // Create a unique key for this student-term combination
        $student_key = $row['student_id'] . '_' . $row['term'] . '_' . $row['class_name'];
        
        // Create a unique key for this specific result
        $result_key = $row['student_id'] . '_' . $row['subject_id'] . '_' . $row['term'];
        
        // Skip if this exact result was already processed
        if (isset($processed_results[$result_key])) {
            continue;
        }
        $processed_results[$result_key] = true;
        
        if (!isset($students_results[$student_key])) {
            $students_results[$student_key] = [
                'student_info' => [
                    'id' => $row['student_id'],
                    'name' => $row['student_name'],
                    'roll_number' => $row['roll_number'],
                    'class_name' => $row['class_name'],
                    'gender' => $row['gender'] ?? 'Not specified',
                    'term' => $row['term'],
                    'session' => $row['session']
                ],
                'results' => []
            ];
        }
        
        $students_results[$student_key]['results'][] = [
            'id' => $row['result_id'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'ca_score' => $row['ca_score'],
            'exam_score' => $row['exam_score'],
            'total_score' => $row['total_score'],
            'term' => $row['term'],
            'session' => $row['session'],
            'student_name' => $row['student_name']
        ];
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT CONCAT(student_id, '_', subject_id, '_', term)) as total_results,
    COUNT(DISTINCT student_id) as unique_students
    FROM results WHERE teacher_id = '$teacher_id'";

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];

// Grade calculation function
function calculateGrade($score) {
    if ($score > 100) return ['Invalid', 'grade-F'];
    if ($score >= 90) return ['A+', 'grade-A-plus'];
    if ($score >= 80) return ['A', 'grade-A'];
    if ($score >= 70) return ['B', 'grade-B'];
    if ($score >= 60) return ['C', 'grade-C'];
    if ($score >= 50) return ['D', 'grade-D'];
    if ($score >= 40) return ['E', 'grade-E'];
    return ['F', 'grade-F'];
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
    <title>Manage My Results - Teacher Portal</title>
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
        .grade-A-plus { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F59E0B; color: white; }
        .grade-E { background-color: #EF4444; color: white; }
        .grade-F { background-color: #DC2626; color: white; }
        
        .result-card {
            transition: all 0.3s ease;
            border-left: 4px solid #10B981;
        }
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
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
                <span class="text-xl font-bold text-gray-800">Teacher Portal</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-indigo-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-indigo-800 text-sm"><?php echo htmlspecialchars($teacher_name, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="text-indigo-600 text-xs"><?php echo htmlspecialchars($_SESSION['teacher_subject'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="text-indigo-500 text-xs mt-1"><?php echo htmlspecialchars($_SESSION['teacher_classes'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="block p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-blue-600 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="teacher_manage_students.php" class="block p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-blue-600 mr-3"></i>
                <span class="font-medium">View Students</span>
            </a>
            <a href="teacher_add_results.php" class="block p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-plus-circle text-green-600 mr-3"></i>
                <span class="font-medium">Add Results</span>
            </a>
            <a href="teacher_manage_results.php" class="block p-3 text-indigo-600 bg-indigo-50 rounded">
                <i class="fas fa-edit text-indigo-600 mr-3"></i>
                <span class="font-medium">Manage Results</span>
            </a>
            <a href="teacher_logout.php" class="block p-3 text-red-600 hover:bg-red-50 rounded">
                <i class="fas fa-sign-out-alt mr-3"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Manage My Results</h1>
                <p class="text-white/80">View, edit and manage all results you have uploaded</p>
            </div>
            <div class="flex space-x-3">
                <a href="teacher_add_results.php" class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New Result</span>
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['unique_students'] ?? 0; ?></div>
                <div class="text-gray-600">Students</div>
            </div>
            <div class="card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_results'] ?? 0; ?></div>
                <div class="text-gray-600">Total Results</div>
            </div>
            <div class="card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chalkboard text-indigo-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo count($teacher_classes); ?></div>
                <div class="text-gray-600">My Classes</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter text-green-500 mr-3"></i>
                Filter Results
            </h3>
            <form method="GET" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-500 mr-2"></i>
                            Search Students
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search, ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Name or roll number..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Class Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard text-green-500 mr-2"></i>
                            Class
                        </label>
                        <select name="class" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">All Classes</option>
                            <?php foreach ($teacher_classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_class == $class ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Term Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-orange-500 mr-2"></i>
                            Term
                        </label>
                        <select name="term" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Terms</option>
                            <?php foreach ($available_terms as $term): ?>
                                <option value="<?php echo htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_term == $term ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-filter"></i>
                        <span>Apply Filters</span>
                    </button>
                    <a href="teacher_manage_results.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-times"></i>
                        <span>Clear All</span>
                    </a>
                </div>
            </form>
        </div>

        <?php if ($success): ?>
            <div class="card rounded-xl p-4 mb-6 bg-green-50 border border-green-200">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-green-800">Success!</h3>
                        <p class="text-green-600 text-sm"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
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
                        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Results Section -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-green-600 mr-3"></i>
                    Student Results
                    <?php if ($filter_class || $filter_search || $filter_term): ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (Filtered: <?php echo count($students_results); ?> students)
                        </span>
                    <?php endif; ?>
                </h3>
                <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm font-medium">
                    <?php echo count($students_results) . ' Student' . (count($students_results) != 1 ? 's' : ''); ?>
                </span>
            </div>

            <?php if (!empty($students_results)): ?>
                <div class="space-y-6">
                    <?php foreach ($students_results as $student_key => $student_data): 
                        $student = $student_data['student_info'];
                        $student_results = $student_data['results'];
                    ?>
                    <div class="result-card card rounded-lg border-l-4 border-blue-500">
                        <!-- Student Header -->
                        <div class="student-header rounded-t-lg p-4 text-white">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-graduate text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-lg"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <div class="text-white/80 text-xs flex items-center space-x-2 mt-1">
                                            <span><?php echo htmlspecialchars($student['roll_number'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>•</span>
                                            <span><?php echo htmlspecialchars($student['class_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>•</span>
                                            <span><?php echo htmlspecialchars($student['term'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>•</span>
                                            <span><?php echo htmlspecialchars($student['gender'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold"><?php echo count($student_results); ?> subjects</div>
                                </div>
                            </div>
                        </div>

                        <!-- Results Table -->
                        <div class="p-0">
                            <?php if (!empty($student_results)): ?>
                            <div class="bg-white border border-gray-200 rounded-b-lg overflow-hidden">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">CA Score</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Exam Score</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php 
                                        $displayed_results = []; // Track displayed to prevent duplicates
                                        foreach ($student_results as $result): 
                                            // Create a unique key for this specific result display
                                            $display_key = $student['id'] . '_' . $result['subject_id'] . '_' . $result['term'];
                                            
                                            // Skip if already displayed in this table (extra safety)
                                            if (in_array($display_key, $displayed_results)) {
                                                continue;
                                            }
                                            $displayed_results[] = $display_key;
                                            
                                            list($grade, $grade_class) = calculateGrade($result['total_score']);
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['subject_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm font-bold text-blue-600"><?php echo htmlspecialchars($result['ca_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-center text-sm font-bold text-red-600"><?php echo htmlspecialchars($result['exam_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-center text-sm font-bold text-green-600"><?php echo htmlspecialchars($result['total_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="<?php echo $grade_class; ?> px-3 py-1 rounded-full text-xs font-bold">
                                                    <?php echo htmlspecialchars($grade, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($result), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                            class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition duration-200 flex items-center space-x-1 text-xs">
                                                        <i class="fas fa-edit"></i>
                                                        <span>Edit</span>
                                                    </button>
                                                    <form action="" method="post" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="result_id" value="<?php echo htmlspecialchars($result['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" name="delete_result" 
                                                                onclick="return confirm('Are you sure you want to delete this result for <?php echo addslashes($student['name']); ?> in <?php echo addslashes($result['subject_name']); ?>?');"
                                                                class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200 flex items-center space-x-1 text-xs">
                                                            <i class="fas fa-trash"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="p-4 text-center text-gray-500">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No subject results found for this student in the selected term.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-green-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php echo ($filter_class || $filter_search || $filter_term) ? 'No results match your filter criteria.' : 'You haven\'t uploaded any results yet.'; ?>
                    </p>
                    <a href="teacher_add_results.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-all duration-300 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Upload Your First Result</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Result Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="bg-green-500 text-white p-4 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Edit Result</h3>
                <button onclick="closeEditModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editForm" method="post" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="result_id" id="edit_result_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Student</label>
                    <input type="text" id="edit_student_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                        <input type="text" id="edit_subject" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Term</label>
                        <input type="text" id="edit_term" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="edit_ca_score" class="block text-sm font-medium text-gray-700 mb-2">CA Score (0-40)</label>
                        <input type="number" name="ca_score" id="edit_ca_score" min="0" max="40" step="0.5" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="edit_exam_score" class="block text-sm font-medium text-gray-700 mb-2">Exam Score (0-60)</label>
                        <input type="number" name="exam_score" id="edit_exam_score" min="0" max="60" step="0.5"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" name="edit_result" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-save"></i>
                        <span>Update Result</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit Modal Functions
        function openEditModal(result) {
            try {
                const resultData = typeof result === 'string' ? JSON.parse(result) : result;
                document.getElementById('edit_result_id').value = resultData.id;
                document.getElementById('edit_student_name').value = resultData.student_name || '';
                document.getElementById('edit_subject').value = resultData.subject_name || '';
                document.getElementById('edit_term').value = resultData.term || '';
                document.getElementById('edit_ca_score').value = resultData.ca_score || 0;
                document.getElementById('edit_exam_score').value = resultData.exam_score || 0;
                document.getElementById('editModal').style.display = 'block';
            } catch (error) {
                console.error('Error parsing result data:', error);
                alert('Error loading result data. Please try again.');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>