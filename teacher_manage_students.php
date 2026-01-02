<?php
session_start();
include('init.php');

// Regular Teacher Only Access
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'teacher') {
    if (isset($_SESSION['teacher_id']) && $_SESSION['user_type'] === 'class_teacher') {
        header('Location: class_teacher_manage_students.php');
        exit();
    }
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_email = $_SESSION['teacher_email'];
$teacher_subject = $_SESSION['teacher_subject'];
$teacher_classes = $_SESSION['teacher_classes'];

// Get teacher's assigned classes
$my_classes = [];
if (!empty($teacher_classes)) {
    $my_classes = array_map('trim', explode(',', $teacher_classes));
}

// Check if teacher has assigned classes
if (empty($my_classes)) {
    // No classes assigned - show message
    $no_classes_message = "You haven't been assigned to any classes yet. Please contact the school administrator.";
}

// Handle filter parameters
$filter_class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : '';
$filter_gender = isset($_GET['gender']) ? mysqli_real_escape_string($conn, $_GET['gender']) : '';
$filter_search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build WHERE conditions for filtering - ONLY teacher's assigned classes
$where_conditions = [];

if (!empty($my_classes)) {
    $class_placeholders = "'" . implode("','", $my_classes) . "'";
    $where_conditions[] = "class_name IN ($class_placeholders)";
} else {
    // Teacher has no assigned classes - show empty results
    $where_conditions[] = "1=0"; // Always false to show no results
}

if ($filter_class && in_array($filter_class, $my_classes)) {
    $where_conditions[] = "class_name = '$filter_class'";
}

if ($filter_gender) {
    $where_conditions[] = "gender = '$filter_gender'";
}

if ($filter_search) {
    $where_conditions[] = "(name LIKE '%$filter_search%' OR roll_number LIKE '%$filter_search%')";
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : "1=1";

// Teachers can only view students from their assigned classes
$students_sql = "SELECT * FROM students WHERE $where_clause ORDER BY class_name, roll_number";
$students_result = mysqli_query($conn, $students_sql);

// Get statistics for teacher's assigned classes
$stats_sql = "SELECT 
    COUNT(*) as total_students,
    COUNT(DISTINCT class_name) as total_classes,
    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
    FROM students WHERE $where_clause";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total_students' => 0, 
    'total_classes' => 0,
    'male_students' => 0, 
    'female_students' => 0
];

// Get count of results uploaded by this teacher (for their classes only)
$my_results_count = 0;
if (!empty($my_classes)) {
    $student_ids_sql = "SELECT id FROM students WHERE class_name IN ($class_placeholders)";
    $student_ids_result = mysqli_query($conn, $student_ids_sql);
    
    $student_ids = [];
    while ($row = mysqli_fetch_assoc($student_ids_result)) {
        $student_ids[] = $row['id'];
    }
    
    if (!empty($student_ids)) {
        $student_ids_string = "'" . implode("','", $student_ids) . "'";
        $results_sql = "SELECT COUNT(*) as count FROM results 
                       WHERE teacher_id = '$teacher_id' 
                       AND student_id IN ($student_ids_string)";
        $results_result = mysqli_query($conn, $results_sql);
        if ($results_result) {
            $results_data = mysqli_fetch_assoc($results_result);
            $my_results_count = $results_data['count'];
        }
    }
}

// Get unique classes for filter dropdown (only teacher's classes)
$classes_sql = "SELECT DISTINCT class_name FROM students WHERE class_name IN ($class_placeholders) ORDER BY class_name";
$classes_result = mysqli_query($conn, $classes_sql);
$classes = [];
while ($class = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $class['class_name'];
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
    <title>View Students - Teacher Portal</title>
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
        .my-class-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .filter-active {
            background-color: #3b82f6 !important;
            color: white !important;
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
            <h3 class="font-semibold text-blue-800 text-sm"><?php echo $teacher_name; ?></h3>
            <p class="text-blue-600 text-xs">Subjects: <?php echo $teacher_subject; ?></p>
            <p class="text-blue-500 text-xs mt-1">Assigned Classes: 
                <?php 
                if (!empty($teacher_classes)) {
                    echo $teacher_classes;
                } else {
                    echo '<span class="text-red-500">None assigned</span>';
                }
                ?>
            </p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-blue-600"></i>
                <span class="font-medium">Teacher Dashboard</span>
            </a>

            <a href="teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-blue-600 bg-blue-50 rounded">
                <i class="fas fa-users text-blue-600"></i>
                <span class="font-medium">My Students</span>
                <span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full"><?php echo $stats['total_students']; ?></span>
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
                            <div class="text-xs text-purple-500 font-semibold mt-1"><?php echo $my_results_count; ?> uploaded</div>
                        </div>
                    </a>
                </div>
            </div>

            <a href="view_reports.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-file-pdf text-blue-600"></i>
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
                <h1 class="text-3xl font-bold text-white">My Students</h1>
                <p class="text-white/80">View students in your assigned classes only</p>
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

        <!-- No Classes Assigned Message -->
        <?php if (empty($my_classes)): ?>
        <div class="card rounded-xl p-6 mb-6 bg-yellow-50 border border-yellow-200">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800 mb-2">No Classes Assigned</h3>
                    <p class="text-yellow-700">
                        You haven't been assigned to any classes yet. Please contact the school administrator to get assigned to classes.
                    </p>
                    <p class="text-sm text-yellow-600 mt-2">
                        Once assigned, you'll be able to view students, add results, and generate report cards for your classes.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section (Only show if teacher has classes) -->
        <?php if (!empty($my_classes)): ?>
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter text-blue-500 mr-3"></i>
                Filter Students
            </h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-500 mr-2"></i>
                            Search Students
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" 
                               placeholder="Search by name or roll number..."
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                    </div>

                    <!-- Class Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard text-green-500 mr-2"></i>
                            Filter by Class
                        </label>
                        <select name="class" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">All My Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class; ?>" <?php echo $filter_class == $class ? 'selected' : ''; ?>>
                                    <?php echo $class; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Gender Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-venus-mars text-purple-500 mr-2"></i>
                            Filter by Gender
                        </label>
                        <select name="gender" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg font-semibold hover:bg-blue-600 transition duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-filter"></i>
                            <span>Apply Filters</span>
                        </button>
                        <a href="teacher_manage_students.php" class="bg-gray-500 text-white px-4 py-3 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-times"></i>
                            <span>Clear</span>
                        </a>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <?php if ($filter_class || $filter_gender || $filter_search): ?>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span class="text-sm font-medium text-blue-800">Active Filters:</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($filter_search): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium flex items-center">
                                    Search: "<?php echo htmlspecialchars($filter_search); ?>"
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="ml-1 text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($filter_class): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium flex items-center">
                                    Class: <?php echo $filter_class; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['class' => ''])); ?>" class="ml-1 text-green-600 hover:text-green-800">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($filter_gender): ?>
                                <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-medium flex items-center">
                                    Gender: <?php echo $filter_gender; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['gender' => ''])); ?>" class="ml-1 text-purple-600 hover:text-purple-800">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- Stats Cards (Only show if teacher has classes) -->
        <?php if (!empty($my_classes)): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Students -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_students']; ?></div>
                <div class="text-gray-600">My Students</div>
                <div class="mt-2 text-sm text-blue-600">
                    <i class="fas fa-male"></i> <?php echo $stats['male_students']; ?> 
                    <i class="fas fa-female ml-2"></i> <?php echo $stats['female_students']; ?>
                </div>
            </div>

            <!-- My Classes -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chalkboard text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_classes']; ?></div>
                <div class="text-gray-600">My Classes</div>
                <div class="mt-2 text-sm text-green-600">
                    <?php echo implode(', ', $classes); ?>
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
                    Uploaded for my classes
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-bolt text-orange-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">Add</div>
                <div class="text-gray-600">Results</div>
                <div class="mt-2">
                    <a href="teacher_add_results.php" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        Add Results
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Students Table (Only show if teacher has classes) -->
        <?php if (!empty($my_classes)): ?>
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-blue-500 mr-3"></i>
                    My Students
                    <?php if ($filter_class || $filter_gender || $filter_search): ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (Filtered: <?php echo $stats['total_students']; ?> students)
                        </span>
                    <?php endif; ?>
                </h2>
                <div class="flex items-center space-x-3">
                    <a href="teacher_add_results.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Results</span>
                    </a>
                    <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                        <i class="fas fa-chalkboard-teacher mr-1"></i> My Classes Only
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Roll No</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Student Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Class</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Gender</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Added By</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-700 border-b">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                            <?php while ($student = mysqli_fetch_assoc($students_result)): 
                                // Get teacher name who added the student
                                $added_by_sql = "SELECT name FROM teachers WHERE id = '{$student['created_by']}'";
                                $added_by_result = mysqli_query($conn, $added_by_sql);
                                $added_by = $added_by_result && mysqli_num_rows($added_by_result) > 0 ? 
                                    mysqli_fetch_assoc($added_by_result)['name'] : 'Unknown';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition duration-150">
                                <td class="px-4 py-3 font-mono font-semibold text-gray-600">
                                    <?php echo $student['roll_number']; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-blue-600 text-sm"></i>
                                        </div>
                                        <span class="text-gray-800 font-medium"><?php echo $student['name']; ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-gray-800 font-medium"><?php echo $student['class_name']; ?></span>
                                        <span class="my-class-badge px-2 py-1 rounded-full text-xs font-semibold">
                                            <i class="fas fa-check mr-1"></i> My Class
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold 
                                        <?php echo $student['gender'] == 'Male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                                        <i class="fas <?php echo $student['gender'] == 'Male' ? 'fa-male' : 'fa-female'; ?> mr-1"></i>
                                        <?php echo $student['gender']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-sm">
                                    <?php echo $added_by; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="student_report_card.php?student_id=<?php echo $student['id']; ?>" 
                                           class="bg-green-100 text-green-600 hover:bg-green-200 px-3 py-1 rounded text-xs font-semibold transition duration-200">
                                            <i class="fas fa-eye mr-1"></i> View Report
                                        </a>
                                        <a href="teacher_add_results.php?student_id=<?php echo $student['id']; ?>" 
                                           class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1 rounded text-xs font-semibold transition duration-200">
                                            <i class="fas fa-edit mr-1"></i> Add Results
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-users-slash text-3xl mb-2 block"></i>
                                    <?php if ($filter_class || $filter_gender || $filter_search): ?>
                                        No students found matching your filters.
                                        <div class="mt-3">
                                            <a href="teacher_manage_students.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                                                Clear Filters
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        No students found in your assigned classes.
                                        <div class="mt-3">
                                            <p class="text-sm text-gray-600">Students in your classes will appear here.</p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <div class="card rounded-xl p-6 bg-blue-50">
                <h3 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Access Information
                </h3>
                <ul class="text-sm text-blue-700 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>You can only view students from your assigned classes</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>You can add results for students in your classes</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-eye text-blue-500 mt-1 mr-2"></i>
                        <span>This is a <strong>read-only</strong> view - you cannot add, edit, or delete students</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-yellow-500 mt-1 mr-2"></i>
                        <span>To manage students (add/edit/delete), you need Class Teacher privileges</span>
                    </li>
                </ul>
            </div>

            <div class="card rounded-xl p-6 bg-green-50">
                <h3 class="text-lg font-semibold text-green-800 mb-3 flex items-center">
                    <i class="fas fa-bolt text-green-500 mr-2"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="teacher_add_results.php" class="bg-green-500 hover:bg-green-600 text-white p-3 rounded-lg text-center transition duration-200">
                        <i class="fas fa-plus-circle text-xl mb-1 block"></i>
                        <div class="text-sm font-semibold">Add Results</div>
                    </a>
                    <a href="teacher_manage_results.php" class="bg-purple-500 hover:bg-purple-600 text-white p-3 rounded-lg text-center transition duration-200">
                        <i class="fas fa-edit text-xl mb-1 block"></i>
                        <div class="text-sm font-semibold">Manage Results</div>
                    </a>
                </div>
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Note:</strong> To manage students (add/edit/delete), you need Class Teacher privileges.
                    </p>
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

        // Auto-submit form when select changes (optional)
        document.querySelectorAll('select[name="class"], select[name="gender"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>