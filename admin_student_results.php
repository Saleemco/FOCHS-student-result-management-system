<?php
include('init.php');
include('session.php');

// Check if user is admin using your session structure
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get all students for dropdown
$students_sql = "SELECT id, name, rno, class_name FROM students ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

// Handle student selection
$selected_student = null;
$student_results = [];

if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    
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
    <title>Student Results - Admin</title>
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
            <div class="text-xs text-green-600 font-semibold">
                Admin: <?php echo $_SESSION['login_user'] ?? 'Admin'; ?>
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
                        <span class="font-medium text-gray-700">Results</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="3">
                    <a href="add_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Add Results</a>
                    <a href="manage_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Manage Results</a>
                    <a href="admin_student_results.php" class="block px-4 py-2 text-green-600 bg-green-50 rounded">Student Overview</a>
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
                <h1 class="text-3xl font-bold text-white">Student Results Overview</h1>
                <p class="text-white/80">View comprehensive results from all teachers</p>
                <p class="text-white/60 text-sm">Logged in as: <?php echo $_SESSION['login_user'] ?? 'Admin'; ?></p>
            </div>
            <a href="dashboard.php" class="bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Student Selection -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-search text-blue-500 mr-3"></i>
                Select Student
            </h3>
            <form method="GET" class="flex gap-4 items-end">
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
                    <span>View Results</span>
                </button>
            </form>
        </div>

        <?php if ($selected_student): ?>
        <!-- Student Information -->
        <div class="card rounded-xl p-6 mb-6">
            <div class="flex justify-between items-start">
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
        </div>

        <!-- Results Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="card rounded-xl p-4 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-chart-line text-blue-500 text-xl"></i>
                </div>
                <h4 class="font-semibold text-gray-700">Average %</h4>
                <p class="text-2xl font-bold text-blue-600">
                    <?php
                    $total_percentage = 0;
                    $count = 0;
                    if ($student_results) {
                        mysqli_data_seek($student_results, 0);
                        while ($result = mysqli_fetch_assoc($student_results)) {
                            $total_percentage += $result['overall_percentage'];
                            $count++;
                        }
                        echo $count > 0 ? number_format($total_percentage / $count, 2) : '0.00';
                        mysqli_data_seek($student_results, 0);
                    } else {
                        echo '0.00';
                    }
                    ?>%
                </p>
            </div>

            <div class="card rounded-xl p-4 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-users text-green-500 text-xl"></i>
                </div>
                <h4 class="font-semibold text-gray-700">Teachers</h4>
                <p class="text-2xl font-bold text-green-600">
                    <?php
                    $teachers = [];
                    if ($student_results) {
                        while ($result = mysqli_fetch_assoc($student_results)) {
                            if (!empty($result['teacher_name'])) {
                                $teachers[$result['teacher_id']] = $result['teacher_name'];
                            }
                        }
                        echo count($teachers);
                        mysqli_data_seek($student_results, 0);
                    } else {
                        echo '0';
                    }
                    ?>
                </p>
            </div>

            <div class="card rounded-xl p-4 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-book text-purple-500 text-xl"></i>
                </div>
                <h4 class="font-semibold text-gray-700">Subjects</h4>
                <p class="text-2xl font-bold text-purple-600">5</p>
            </div>

            <div class="card rounded-xl p-4 text-center">
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-calendar text-orange-500 text-xl"></i>
                </div>
                <h4 class="font-semibold text-gray-700">Last Updated</h4>
                <p class="text-sm font-bold text-orange-600">
                    <?php
                    if ($student_results && mysqli_num_rows($student_results) > 0) {
                        $latest = mysqli_fetch_assoc($student_results);
                        echo date('M j, Y', strtotime($latest['created_at']));
                        mysqli_data_seek($student_results, 0);
                    } else {
                        echo 'Never';
                    }
                    ?>
                </p>
            </div>
        </div>

        <!-- Detailed Results Table -->
        <div class="card rounded-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-list-alt text-green-600 mr-3"></i>
                Detailed Results from All Teachers
            </h3>

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