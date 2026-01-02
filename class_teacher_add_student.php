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

// Handle form submission with prepared statements
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $name = $_POST['name'];
    $roll_number = $_POST['roll_number'];
    $gender = $_POST['gender'];

    // Check if roll number already exists in this class
    $check_sql = "SELECT id FROM students WHERE roll_number = ? AND class_name = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $roll_number, $assigned_class);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Error: Roll number $roll_number already exists in $assigned_class!";
    } else {
        // Insert new student using prepared statement
        // MySQL will automatically set created_at timestamp if DEFAULT CURRENT_TIMESTAMP is set
        $sql = "INSERT INTO students (name, roll_number, class_name, gender, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $roll_number, $assigned_class, $gender, $teacher_id);
        
        if ($stmt->execute()) {
            $success = "Student $name added successfully to $assigned_class with Roll Number: $roll_number";
            
            // Clear form fields
            $_POST = array();
        } else {
            $error = "Error adding student: " . $stmt->error;
        }
    }
}

// Get class statistics for sidebar
$class_stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students
    FROM students 
    WHERE class_name = '$assigned_class'";
    
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
    <title>Add Student - <?php echo $assigned_class; ?></title>
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
        .form-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
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

            <a href="class_teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="font-medium">Manage Students</span>
                <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full"><?php echo $class_stats['total_students']; ?></span>
            </a>

            <a href="class_teacher_add_student.php" class="nav-item p-3 flex items-center space-x-3 text-green-600 bg-green-50 rounded">
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
                <h1 class="text-3xl font-bold text-white">Add New Student</h1>
                <p class="text-white/80">Add student to <?php echo $assigned_class; ?></p>
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

            <!-- Recent Activity -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-plus-circle text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">Add</div>
                <div class="text-gray-600">New Student</div>
                <div class="mt-2 text-sm text-purple-600">
                    Auto-assign to <?php echo $assigned_class; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="stats-card card rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-bolt text-orange-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800">Quick</div>
                <div class="text-gray-600">Actions</div>
                <div class="mt-2">
                    <a href="class_teacher_manage_students.php" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm font-semibold transition duration-200">
                        View All
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

        <!-- Add Student Form -->
        <div class="card rounded-xl p-6 form-card">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-user-plus text-indigo-500 mr-3"></i>
                    Student Registration Form
                </h2>
                <div class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-semibold">
                    Class: <?php echo $assigned_class; ?>
                </div>
            </div>

            <form method="POST" class="space-y-6">
                <!-- Student Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-blue-500 mr-1"></i>
                            Full Name *
                        </label>
                        <input type="text" name="name" required 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter student's full name">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card text-green-500 mr-1"></i>
                            Roll Number *
                        </label>
                        <input type="text" name="roll_number" required 
                               value="<?php echo isset($_POST['roll_number']) ? htmlspecialchars($_POST['roll_number']) : ''; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Unique roll number">
                        <p class="text-xs text-gray-500 mt-1">Must be unique in <?php echo $assigned_class; ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-venus-mars text-purple-500 mr-1"></i>
                            Gender *
                        </label>
                        <select name="gender" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard-teacher text-indigo-500 mr-1"></i>
                            Assigned Class
                        </label>
                        <input type="text" value="<?php echo $assigned_class; ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600" 
                               readonly>
                        <p class="text-xs text-gray-500 mt-1">Automatically assigned to your class</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1 text-blue-500"></i>
                        Fields marked with * are required
                    </div>
                    <div class="flex space-x-3">
                        <a href="class_teacher_manage_students.php" 
                           class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition duration-200 flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Students</span>
                        </a>
                        <button type="submit" name="add_student" 
                                class="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center space-x-2 shadow-lg">
                            <i class="fas fa-user-plus"></i>
                            <span>Add Student to <?php echo $assigned_class; ?></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Tips -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <div class="card rounded-xl p-6 bg-blue-50">
                <h3 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                    Quick Tips
                </h3>
                <ul class="text-sm text-blue-700 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Roll numbers must be unique within the same class</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Students are automatically assigned to <?php echo $assigned_class; ?></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Only basic information is required for student registration</span>
                    </li>
                </ul>
            </div>

            <div class="card rounded-xl p-6 bg-green-50">
                <h3 class="text-lg font-semibold text-green-800 mb-3 flex items-center">
                    <i class="fas fa-bolt text-green-500 mr-2"></i>
                    Next Steps
                </h3>
                <ul class="text-sm text-green-700 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-green-500 mt-1 mr-2"></i>
                        <span>After adding, you can mark attendance for the student</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-green-500 mt-1 mr-2"></i>
                        <span>Add academic results when available</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-green-500 mt-1 mr-2"></i>
                        <span>View the student's report card</span>
                    </li>
                </ul>
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const rollNumber = document.querySelector('input[name="roll_number"]').value.trim();
            const gender = document.querySelector('select[name="gender"]').value;
            
            if (!name || !rollNumber || !gender) {
                e.preventDefault();
                alert('Please fill in all required fields (Name, Roll Number, and Gender).');
                return false;
            }
        });
    </script>
</body>
</html>