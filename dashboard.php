<?php
    include("init.php");
    
    // Get basic counts with error handling
    $no_of_classes = 0;
    $no_of_students = 0;
    $no_of_result = 0;
    $active_classes = 0;
    $new_students_today = 0;
    $recent_results = 0;

    // Check if tables exist and get counts
    $class_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `class`");
    if ($class_query) {
        $no_of_classes = mysqli_fetch_array($class_query)['count'];
        
        // Try to get active classes (if status column exists)
        $active_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `class` WHERE `status` = 'active'");
        if ($active_query) {
            $active_classes = mysqli_fetch_array($active_query)['count'];
        } else {
            // If status column doesn't exist, assume all are active
            $active_classes = $no_of_classes;
        }
    }

    $students_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `students`");
    if ($students_query) {
        $no_of_students = mysqli_fetch_array($students_query)['count'];
        
        // Try to get new students today (if created_at column exists)
        $new_students_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `students` WHERE DATE(created_at) = CURDATE()");
        if ($new_students_query) {
            $new_students_today = mysqli_fetch_array($new_students_query)['count'];
        }
    }

    $result_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `result`");
    if ($result_query) {
        $no_of_result = mysqli_fetch_array($result_query)['count'];
        
        // Try to get recent results (if created_at column exists)
        $recent_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM `result` WHERE DATE(created_at) = CURDATE()");
        if ($recent_query) {
            $recent_results = mysqli_fetch_array($recent_query)['count'];
        }
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
    <title>Dashboard</title>
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
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
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
        .quick-action-item {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .quick-action-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(3px);
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
            <a href="student_report_card.php" class="nav-item p-3 flex items-center space-x-3 text-purple-600 hover:bg-purple-50 rounded">
    <i class="fas fa-file-alt"></i>
    <span class="font-medium">Report Cards</span>
</a>
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
                    <a href="manage_classes.php" class="block px-4 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 rounded">Manage Class</a>
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
                <h1 class="text-3xl font-bold text-white">Welcome Back, Admin!</h1>
                <p class="text-white/80">Here's what's happening with your institution today.</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                    <span class="text-white text-sm">Current Session</span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Courses Card -->
            <a href="manage_classes.php" class="stat-card card rounded-xl p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chalkboard-teacher text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $no_of_classes; ?></h3>
                <p class="text-gray-600">Total Classes</p>
                <div class="mt-3 text-sm text-purple-600">
                    <i class="fas fa-check-circle mr-1"></i>
                    <?php echo $active_classes; ?> Active
                </div>
            </a>

            <!-- Students Card -->
            <a href="manage_students.php" class="stat-card card rounded-xl p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $no_of_students; ?></h3>
                <p class="text-gray-600">Registered Students</p>
                <div class="mt-3 text-sm text-blue-600">
                    <i class="fas fa-user-plus mr-1"></i>
                    <?php echo $new_students_today > 0 ? $new_students_today . ' New Today' : 'All Students'; ?>
                </div>
            </a>

            <!-- Results Card -->
            <a href="manage_results.php" class="stat-card card rounded-xl p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chart-bar text-green-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $no_of_result; ?></h3>
                <p class="text-gray-600">Results Published</p>
                <div class="mt-3 text-sm text-green-600">
                    <i class="fas fa-clock mr-1"></i>
                    <?php echo $recent_results > 0 ? $recent_results . ' Recent' : 'Total Records'; ?>
                </div>
            </a>
        </div>

        <!-- Quick Actions & System Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-orange-500 mr-3"></i>
                    Quick Actions
                </h3>
                <div class="space-y-3">
                    <a href="add_students.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-orange-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-user-plus text-orange-500"></i>
                            <span class="text-gray-700">Add New Student</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="add_results.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-orange-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-plus-circle text-orange-500"></i>
                            <span class="text-gray-700">Add Results</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="manage_classes.php" class="quick-action-item flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-orange-50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-cog text-orange-500"></i>
                            <span class="text-gray-700">Manage Classes</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                </div>
            </div>

            <!-- System Overview -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-indigo-500 mr-3"></i>
                    System Overview
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Database Status</span>
                        <span class="px-2 py-1 bg-green-100 text-green-600 rounded-full text-sm">Online</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Last Update</span>
                        <span class="text-gray-800 font-medium"><?php echo date('M j, Y'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Active Users</span>
                        <span class="text-gray-800 font-medium">1 (Admin)</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Server Time</span>
                        <span class="text-gray-800 font-medium"><?php echo date('h:i A'); ?></span>
                    </div>
                </div>
            </div>
        </div>
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
                document.querySelectorAll('.dropdown-conftent').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>