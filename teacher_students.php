<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Get teacher's assigned classes
$teacher_classes = $_SESSION['teacher_classes'];
$classes_array = explode(',', $teacher_classes);
$class_conditions = implode("','", array_map('trim', $classes_array));

// Handle search
$search = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $search_condition = "AND (name LIKE '%$search%' OR roll_number LIKE '%$search%')";
} else {
    $search_condition = '';
}

// Get students from teacher's classes
$students_sql = "SELECT * FROM students WHERE class_name IN ('$class_conditions') $search_condition ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

// Count total students
$count_sql = "SELECT COUNT(*) as total FROM students WHERE class_name IN ('$class_conditions')";
$count_result = mysqli_query($conn, $count_sql);
$total_students = mysqli_fetch_assoc($count_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>My Students - Teacher Portal</title>
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
        .student-card {
            transition: all 0.3s ease;
        }
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
                <span class="text-xl font-bold text-gray-800">Teacher</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-indigo-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-indigo-800 text-sm"><?php echo $_SESSION['teacher_name']; ?></h3>
            <p class="text-indigo-600 text-xs"><?php echo $_SESSION['teacher_subject']; ?></p>
            <p class="text-indigo-500 text-xs mt-1"><?php echo $_SESSION['teacher_classes']; ?></p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="teacher_students.php" class="nav-item p-3 flex items-center space-x-3 text-indigo-600 bg-indigo-50 rounded">
                <i class="fas fa-user-graduate"></i>
                <span class="font-medium">My Students</span>
            </a>

            <a href="teacher_results.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-chart-bar"></i>
                <span class="font-medium">Results</span>
            </a>

            <a href="add_results.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-plus-circle"></i>
                <span class="font-medium">Add Results</span>
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
                <p class="text-white/80">Manage students from your assigned classes</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                    <span class="text-white text-sm">Total: <?php echo $total_students; ?> students</span>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex-1">
                    <form method="GET" class="flex space-x-4">
                        <div class="flex-1">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search students by name or roll number..." 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <button type="submit" 
                                class="bg-indigo-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-600 transition duration-200 flex items-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>Search</span>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="teacher_students.php" 
                               class="bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 flex items-center space-x-2">
                                <i class="fas fa-times"></i>
                                <span>Clear</span>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Students Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                <?php while($student = mysqli_fetch_assoc($students_result)): ?>
                    <div class="student-card card rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-blue-500"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo $student['name']; ?></h3>
                                    <p class="text-gray-500 text-sm">Roll No: <?php echo $student['roll_number']; ?></p>
                                </div>
                            </div>
                            <span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-semibold">
                                <?php echo $student['class_name']; ?>
                            </span>
                        </div>
                        
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span>Admission No:</span>
                                <span class="font-medium"><?php echo $student['roll_number']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Class:</span>
                                <span class="font-medium"><?php echo $student['class_name']; ?></span>
                            </div>
                        </div>

                        <div class="mt-4 flex space-x-2">
                            <a href="add_results.php?student_id=<?php echo $student['id']; ?>" 
                               class="flex-1 bg-indigo-500 text-white py-2 px-3 rounded text-center text-sm hover:bg-indigo-600 transition duration-200">
                                <i class="fas fa-plus-circle mr-1"></i>Add Result
                            </a>
                            <a href="view_student_results.php?student_id=<?php echo $student['id']; ?>" 
                               class="flex-1 bg-green-500 text-white py-2 px-3 rounded text-center text-sm hover:bg-green-600 transition duration-200">
                                <i class="fas fa-eye mr-1"></i>View Results
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-3">
                    <div class="card rounded-xl p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-600 mb-2">No Students Found</h3>
                        <p class="text-gray-500">
                            <?php echo !empty($search) ? 'No students match your search criteria.' : 'No students found in your assigned classes.'; ?>
                        </p>
                        <?php if (!empty($search)): ?>
                            <a href="teacher_students.php" 
                               class="inline-block mt-4 bg-indigo-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-indigo-600 transition duration-200">
                                View All Students
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Student Count by Class -->
        <div class="mt-8 card rounded-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-pie text-purple-500 mr-3"></i>
                Students by Class
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($classes_array as $class): ?>
                    <?php 
                    $class = trim($class);
                    $class_count_sql = "SELECT COUNT(*) as count FROM students WHERE class_name = '$class'";
                    $class_count_result = mysqli_query($conn, $class_count_sql);
                    $class_count = mysqli_fetch_assoc($class_count_result)['count'];
                    ?>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-indigo-600"><?php echo $class_count; ?></div>
                        <div class="text-sm text-gray-600"><?php echo $class; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Add hover effects to student cards
        document.querySelectorAll('.student-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>