<?php
include('init.php');
include('session.php');

// --- Deletion Logic ---
if (isset($_POST['delete'])) {
    $st_id = $_POST['st_id'];
    
    // Find student details using the reliable ID to match the result table
    $st_query = mysqli_query($conn, "SELECT name, rno, class_name FROM students WHERE id = '$st_id'");
    if ($st_query && mysqli_num_rows($st_query) > 0) {
        $st_row = mysqli_fetch_assoc($st_query);
        $name = $st_row['name'];
        $rno = $st_row['rno'];
        $class_name = $st_row['class_name'];

        // Delete from the result table using the matched fields
        $sql = "DELETE FROM results WHERE name='$name' AND roll_number='$rno' AND class='$class_name'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            echo '<script language="javascript">';
            echo 'alert("Result successfully deleted")';
            echo '</script>';
        } else {
            echo '<script language="javascript">';
            echo 'alert("Deletion failed due to database error: ' . mysqli_error($conn) . '")';
            echo '</script>';
        }
    } else {
        echo '<script language="javascript">';
        echo 'alert("Student not found")';
        echo '</script>';
    }
}

// Check which results table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'results'");
$results_table_exists = $table_check && mysqli_num_rows($table_check) > 0;

if (!$results_table_exists) {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'result'");
    $result_table_exists = $table_check && mysqli_num_rows($table_check) > 0;
}

// Determine table name and structure
if ($results_table_exists) {
    $table_name = 'results';
    $roll_column = 'roll_number';
} elseif ($result_table_exists) {
    $table_name = 'result';
    $roll_column = 'rno';
} else {
    $table_name = null;
    $error = "No results table found in database";
}

// Get all students for centralized view dropdown
$students_sql = "SELECT id, name, rno, class_name FROM students ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

// Handle student selection for centralized view
$selected_student = null;
$student_results = [];
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'all'; // 'all' or 'student'

if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    $view_mode = 'student';
    
    // Get student details
    $student_sql = "SELECT * FROM students WHERE id = '$student_id'";
    $student_query = mysqli_query($conn, $student_sql);
    if ($student_query) {
        $selected_student = mysqli_fetch_assoc($student_query);
    }
    
    if ($selected_student) {
        // Get all results for this student
        $results_sql = "SELECT r.*, 
                               (r.p1 + r.p2 + r.p3 + r.p4 + r.p5) as total_marks,
                               ((r.p1 + r.p2 + r.p3 + r.p4 + r.p5) / 5) as overall_percentage
                        FROM results r 
                        WHERE r.roll_number = '{$selected_student['rno']}' 
                        AND r.class = '{$selected_student['class_name']}'
                        ORDER BY r.created_at DESC";
        $student_results = mysqli_query($conn, $results_sql);
    }
}

// Get all results for the "all results" view
$all_results_sql = "SELECT * FROM $table_name";
$all_results = mysqli_query($conn, $all_results_sql);

// Grade calculation function
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A-plus';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
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
    <title>Manage Results</title>
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
            transform: translateX(5px);
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
        }
        .action-btn {
            transition: all 0.3s ease;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .table-row:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        .grade-A-plus { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F59E0B; color: white; }
        .grade-E { background-color: #EF4444; color: white; }
        .grade-F { background-color: #DC2626; color: white; }
        .subject-math { background-color: #3B82F6; color: white; }
        .subject-science { background-color: #10B981; color: white; }
        .subject-english { background-color: #F59E0B; color: white; }
        .subject-social { background-color: #8B5CF6; color: white; }
        .subject-computer { background-color: #EF4444; color: white; }
        .view-toggle {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 4px;
            display: inline-flex;
        }
        .view-toggle-btn {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
        }
        .view-toggle-btn.active {
            background: white;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <img src="./images/logo1.png" alt="Logo" class="w-10 h-10">
                <span class="text-xl font-bold text-gray-800">SRMS</span>
            </div>
        </div>

        <nav class="space-y-2">
            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('1')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chalkboard-teacher text-purple-600"></i>
                        <span class="font-medium text-gray-700">Classes</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="1">
                    <a href="add_classes.php" class="block px-4 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 rounded">Add Class</a>
                    <a href="manage_classes.php" class="block px-4 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 rounded">Manage Classes</a>
                </div>
            </div>

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('2')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-graduate text-blue-600"></i>
                        <span class="font-medium text-gray-700">Students</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="2">
                    <a href="add_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Add Students</a>
                    <a href="manage_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Manage Students</a>
                </div>
            </div>

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('3')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar text-green-600"></i>
                        <span class="font-medium text-gray-700">Grades</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="3">
                    <a href="add_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Add Results</a>
                    <a href="manage_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Manage Results</a>
                </div>
            </div>

            <a href="dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="logout.php" class="nav-item p-3 flex items-center space-x-3 text-red-600 hover:bg-red-50 rounded">
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
                <h1 class="text-3xl font-bold text-white">Manage Results</h1>
                <p class="text-white/80">View and manage all student results</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="add_results.php" class="action-btn bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New Result</span>
                </a>
                <a href="dashboard.php" class="action-btn bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="card rounded-xl p-6 mb-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-filter text-green-500 mr-3"></i>
                    View Mode
                </h3>
                <div class="view-toggle">
                    <a href="?view=all" class="view-toggle-btn <?php echo $view_mode == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list mr-2"></i>All Results
                    </a>
                    <a href="?view=student" class="view-toggle-btn <?php echo $view_mode == 'student' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate mr-2"></i>Student Overview
                    </a>
                </div>
            </div>
        </div>

        <?php if ($view_mode == 'student'): ?>
        <!-- Student Overview View -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-search text-blue-500 mr-3"></i>
                Select Student for Detailed View
            </h3>
            <form method="GET" class="flex gap-4 items-end">
                <input type="hidden" name="view" value="student">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Choose Student</label>
                    <select name="student_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a student...</option>
                        <?php 
                        if ($students_result) {
                            while ($student = mysqli_fetch_assoc($students_result)): 
                        ?>
                            <option value="<?php echo $student['id']; ?>" 
                                    <?php echo isset($_GET['student_id']) && $_GET['student_id'] == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo $student['name'] . ' - ' . $student['rno'] . ' (' . $student['class_name'] . ')'; ?>
                            </option>
                        <?php 
                            endwhile;
                            mysqli_data_seek($students_result, 0);
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-600 transition duration-200 flex items-center space-x-2">
                    <i class="fas fa-eye"></i>
                    <span>View Student Results</span>
                </button>
            </form>
        </div>

        <?php if ($selected_student): ?>
        <!-- Student Detailed Results -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $selected_student['name']; ?></h3>
                    <div class="flex items-center space-x-6 mt-2">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-id-card text-gray-400"></i>
                            <span class="text-gray-600">Roll No: <?php echo $selected_student['rno']; ?></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-graduation-cap text-gray-400"></i>
                            <span class="text-gray-600">Class: <?php echo $selected_student['class_name']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm font-medium">
                        <?php echo mysqli_num_rows($student_results); ?> Result Entries
                    </div>
                </div>
            </div>

            <?php if ($student_results && mysqli_num_rows($student_results) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b-2 border-gray-200">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Entry Date</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Mathematics</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Science</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">English</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Social Studies</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Computer</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Total</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Percentage</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Entered By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($result = mysqli_fetch_assoc($student_results)): 
                                $overall_grade = calculateGrade($result['overall_percentage']);
                            ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="subject-math px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $result['p1']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="subject-science px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $result['p2']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="subject-english px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $result['p3']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="subject-social px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $result['p4']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="subject-computer px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $result['p5']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo $result['total_marks']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="grade-<?php echo $overall_grade; ?> px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo number_format($result['overall_percentage'], 2); ?>%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-chalkboard-teacher text-blue-400"></i>
                                            <span class="text-sm text-gray-600">
                                                <?php echo !empty($result['teacher_name']) ? $result['teacher_name'] : 'Unknown Teacher'; ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                    <p class="text-gray-500">No teachers have entered results for this student yet.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- All Results View (Your Original Functionality) -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-green-600 mr-3"></i>
                    All Results
                </h3>
                <?php if ($table_name): ?>
                <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm font-medium">
                    <?php 
                        $count_sql = "SELECT COUNT(*) as total FROM $table_name";
                        $count_result = mysqli_query($conn, $count_sql);
                        if ($count_result) {
                            $count_row = mysqli_fetch_assoc($count_result);
                            echo $count_row['total'] . ' Results';
                        } else {
                            echo '0 Results';
                        }
                    ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <strong>Database Error:</strong> <?php echo $error; ?>
                </div>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Database Configuration Required</h3>
                    <p class="text-gray-500 mb-6">Please check your database and ensure the results table exists.</p>
                </div>
            <?php elseif ($table_name && $all_results && mysqli_num_rows($all_results) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b-2 border-gray-200">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Name</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Roll No</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Class</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Total Marks</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                                while ($row = mysqli_fetch_array($all_results)) {
                                    $name = $row['name'];
                                    $rno = $row[$roll_column];
                                    $class = $row['class'];
                                    $marks = $row['marks'];

                                    // Find the unique student ID using Name and Roll No
                                    $st_query = mysqli_query($conn, "SELECT id FROM students WHERE name = '$name' AND rno = '$rno' AND class_name = '$class'");
                                    if ($st_query && mysqli_num_rows($st_query) > 0) {
                                        $st_row = mysqli_fetch_assoc($st_query);
                                        $st_id = $st_row['id'];
                                    } else {
                                        $st_id = 0;
                                    }
                            ?>
                                <tr class="table-row hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-green-400 mr-3"></i>
                                            <span class="text-gray-800 font-medium"><?php echo $name; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-sm font-medium">
                                            <?php echo $rno; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-gray-600"><?php echo $class; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo $marks; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($st_id > 0): ?>
                                        <form action="" method="post" class="inline">
                                            <input type="hidden" name="st_id" value="<?php echo $st_id; ?>">
                                            <button type="submit" name="delete" 
                                                onclick="return confirm('Are you sure you want to delete this result? This action cannot be undone.');"
                                                class="action-btn bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200 flex items-center space-x-2">
                                                <i class="fas fa-trash"></i>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Student not found</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-green-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                    <p class="text-gray-500 mb-6">Get started by adding your first student result.</p>
                    <a href="add_results.php" class="action-btn bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-all duration-300 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Your First Result</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDisplay(id) {
            var dropdown = document.getElementById(id);
            var allDropdowns = document.querySelectorAll('.dropdown-content');
            
            // Close all other dropdowns
            allDropdowns.forEach(function(d) {
                if (d.id !== id) {
                    d.style.display = "none";
                }
            });
            
            // Toggle current dropdown
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.nav-item')) {
                document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>