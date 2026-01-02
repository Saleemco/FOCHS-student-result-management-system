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

// Handle filter parameters
$filter_gender = isset($_GET['gender']) ? mysqli_real_escape_string($conn, $_GET['gender']) : '';
$filter_search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build WHERE conditions for filtering
$where_conditions = ["class_name = '$assigned_class'"]; // Always show only assigned class

if ($filter_gender) {
    $where_conditions[] = "gender = '$filter_gender'";
}

if ($filter_search) {
    $where_conditions[] = "(name LIKE '%$filter_search%' OR roll_number LIKE '%$filter_search%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_student'])) {
        // Delete student from class
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        
        $sql = "DELETE FROM students WHERE id = '$student_id' AND class_name = '$assigned_class'";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Student removed successfully from $assigned_class!";
        } else {
            $error = "Error removing student: " . mysqli_error($conn);
        }
    }
}

// Get ALL students in assigned class (regardless of who added them) with filters
$students_sql = "SELECT * FROM students WHERE $where_clause ORDER BY roll_number";
$students_result = mysqli_query($conn, $students_sql);

// Get class statistics with filters applied
$class_stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
    FROM students 
    WHERE $where_clause";
    
$class_stats_result = mysqli_query($conn, $class_stats_sql);
$class_stats = $class_stats_result ? mysqli_fetch_assoc($class_stats_result) : [
    'total_students' => 0, 
    'male_students' => 0, 
    'female_students' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Manage Students - <?php echo $assigned_class; ?></title>
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
            <a href="class_teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-green-600"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="class_teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-green-600 bg-green-50 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">Manage Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a>

            <a href="class_teacher_add_student.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-user-plus text-green-600"></i>
                <span class="font-medium">Add Student</span>
                
            </a>

            <a href="class_teacher_attendance.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-calendar-check text-blue-600"></i>
                <span class="font-medium">Attendance</span>
            </a>

            <a href="class_teacher_reports.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-file-pdf text-orange-600"></i>
                <span class="font-medium">Reports</span>
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
                <h1 class="text-3xl font-bold text-white">Manage Students</h1>
                <p class="text-white/80">All students in <?php echo $assigned_class; ?></p>
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

        <!-- Filter Section -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter text-indigo-500 mr-3"></i>
                Filter Students
            </h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        <button type="submit" class="w-full bg-indigo-500 text-white px-4 py-3 rounded-lg font-semibold hover:bg-indigo-600 transition duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-filter"></i>
                            <span>Apply Filters</span>
                        </button>
                        <a href="class_teacher_manage_students.php" class="bg-gray-500 text-white px-4 py-3 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-times"></i>
                            <span>Clear</span>
                        </a>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <?php if ($filter_gender || $filter_search): ?>
                <div class="mt-4 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-indigo-500"></i>
                            <span class="text-sm font-medium text-indigo-800">Active Filters:</span>
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

        <!-- Stats Cards -->
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

            <!-- Class Info -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $assigned_class; ?></div>
                <div class="text-gray-600">Assigned Class</div>
                <div class="mt-2 text-sm text-green-600">
                    <i class="fas fa-user-tie mr-1"></i> <?php echo $teacher_name; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-user-plus text-indigo-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">Add</div>
                <div class="text-gray-600">New Student</div>
                <div class="mt-2">
                    <a href="class_teacher_add_student.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        Add Student
                    </a>
                </div>
            </div>

            <!-- View Reports -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">View</div>
                <div class="text-gray-600">Reports</div>
                <div class="mt-2">
                    <a href="class_teacher_reports.php" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        View Analytics
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
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

        <?php if (isset($error)): ?>
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

        <!-- Students Table -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-indigo-500 mr-3"></i>
                    Students List - <?php echo $assigned_class; ?>
                    <?php if ($filter_gender || $filter_search): ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (Filtered: <?php echo $class_stats['total_students']; ?> students)
                        </span>
                    <?php endif; ?>
                </h2>
                <a href="class_teacher_add_student.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New Student</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Roll No</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 border-b">Student Name</th>
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
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-indigo-600 text-sm"></i>
                                        </div>
                                        <span class="text-gray-800 font-medium"><?php echo $student['name']; ?></span>
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
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this student from <?php echo $assigned_class; ?>?');">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="delete_student" 
                                                    class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1 rounded text-xs font-semibold transition duration-200">
                                                <i class="fas fa-trash mr-1"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-users-slash text-3xl mb-2 block"></i>
                                    <?php if ($filter_gender || $filter_search): ?>
                                        No students found matching your filters in <?php echo $assigned_class; ?>.
                                        <div class="mt-3">
                                            <a href="class_teacher_manage_students.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                                                Clear Filters
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        No students found in <?php echo $assigned_class; ?>.
                                        <div class="mt-3">
                                            <a href="class_teacher_add_student.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                                                <i class="fas fa-user-plus mr-1"></i> Add First Student
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <div class="card rounded-xl p-6 bg-blue-50">
                <h3 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Information
                </h3>
                <ul class="text-sm text-blue-700 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>You can view ALL students in <?php echo $assigned_class; ?></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Students added by other teachers are also visible here</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>You can remove any student from your class</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-filter text-indigo-500 mt-1 mr-2"></i>
                        <span>Use filters to quickly find specific students</span>
                    </li>
                </ul>
            </div>

            <div class="card rounded-xl p-6 bg-green-50">
                <h3 class="text-lg font-semibold text-green-800 mb-3 flex items-center">
                    <i class="fas fa-bolt text-green-500 mr-2"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="class_teacher_add_student.php" class="bg-green-500 hover:bg-green-600 text-white p-3 rounded-lg text-center transition duration-200">
                        <i class="fas fa-user-plus text-xl mb-1 block"></i>
                        <div class="text-sm font-semibold">Add Student</div>
                    </a>
                    <a href="class_teacher_attendance.php" class="bg-blue-500 hover:bg-blue-600 text-white p-3 rounded-lg text-center transition duration-200">
                        <i class="fas fa-calendar-check text-xl mb-1 block"></i>
                        <div class="text-sm font-semibold">Attendance</div>
                    </a>
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

        // Auto-submit form when select changes (optional)
        document.querySelectorAll('select[name="gender"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>