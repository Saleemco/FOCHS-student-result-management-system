<?php
include('init.php');
include('session.php');

$success_message = '';
$error_messages = [];

// PHP LOGIC FOR INSERTION
if(isset($_POST['class_name'])) {
    $class_name = trim(mysqli_real_escape_string($conn, $_POST['class_name']));
    $section = isset($_POST['section']) ? mysqli_real_escape_string($conn, $_POST['section']) : '';

    // validation
    $valid = true;
    
    if (empty($class_name)) {
        $error_messages[] = 'Please enter class name';
        $valid = false;
    }
    
    if ($valid) {
        // First check if class with same name already exists
        $check_sql = "SELECT id, name FROM classes WHERE name = '$class_name'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $existing_class = mysqli_fetch_assoc($check_result);
            $existing_name = $existing_class['name'];
            $error_messages[] = "🚫 <strong>Class Already Exists!</strong><br>
                                • Class Name: <strong>$class_name</strong><br>
                                • Existing Class ID: <strong>#{$existing_class['id']}</strong><br><br>
                                Please use a different class name.";
        } else {
            // SQL: Insert class
            $sql = "INSERT INTO `classes` (`name`, `section`) 
                    VALUES ('$class_name', '$section')";
            $result = mysqli_query($conn, $sql);
            
            if (!$result) {
                $error_msg = mysqli_error($conn);
                
                // Check for duplicate entry error
                if (strpos($error_msg, 'Duplicate entry') !== false) {
                    $error_messages[] = "🚫 <strong>Duplicate Entry Prevented!</strong><br>
                                        A class named <strong>$class_name</strong> already exists.<br>
                                        Each class must have a unique name.";
                } else {
                    $error_messages[] = 'Database Error: ' . $error_msg;
                }
            } else {
                $new_class_id = mysqli_insert_id($conn);
                $success_message = '✅ <strong>Class Added Successfully!</strong><br>Class "'.$class_name.'" has been created with ID #'.$new_class_id.'.';
                // Clear form
                echo '<script>
                    setTimeout(function() {
                        document.querySelector("form").reset();
                    }, 100);
                </script>';
            }
        }
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
    <title>Add Class</title>
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
                <h1 class="text-3xl font-bold text-white">Add New Class</h1>
                <p class="text-white/80">Create a new class for your institution</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="manage_classes.php" class="action-btn bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Classes</span>
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success_message): ?>
            <div class="card rounded-2xl p-6 mb-6 border-l-4 border-green-500 bg-green-50">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-green-800">Success!</h3>
                        <p class="text-green-600"><?php echo $success_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($error_messages)): ?>
            <div class="card rounded-2xl p-6 mb-6 border-l-4 border-red-500 bg-red-50">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Please fix the following errors:</h3>
                        <div class="text-red-600 mt-2">
                            <?php foreach($error_messages as $error): ?>
                                <div class="mb-2"><?php echo $error; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add Class Form -->
        <div class="card rounded-2xl p-8">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Create New Class</h3>
                    <p class="text-gray-600">Enter the class name to add it to the system</p>
                </div>
            </div>

            <form action="" method="post" class="space-y-6">
                <div class="max-w-md">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-chalkboard-teacher text-purple-500 mr-2"></i>
                        Class Name *
                    </label>
                    <input type="text" name="class_name" placeholder="e.g., Grade 10 Science, Class 12 Arts, etc." required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200 text-lg"
                           value="<?php echo isset($_POST['class_name']) ? htmlspecialchars($_POST['class_name']) : ''; ?>">
                    <p class="text-gray-500 text-sm mt-2">Enter a unique class name that doesn't already exist in the system.</p>
                </div>

                <div class="max-w-md">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tag text-blue-500 mr-2"></i>
                        Section (Optional)
                    </label>
                    <input type="text" name="section" placeholder="e.g., A, B, Science, Arts"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                           value="<?php echo isset($_POST['section']) ? htmlspecialchars($_POST['section']) : ''; ?>">
                    <p class="text-gray-500 text-sm mt-2">Optional section identifier for the class.</p>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="action-btn bg-purple-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-purple-700 transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Class</span>
                    </button>
                    
                    <button type="reset" 
                            class="action-btn bg-gray-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-redo"></i>
                        <span>Clear</span>
                    </button>
                </div>
            </form>

            <!-- Quick Tips -->
            <div class="mt-8 p-6 bg-blue-50 rounded-xl border border-blue-200">
                <h4 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                    Quick Tips
                </h4>
                <ul class="text-blue-700 space-y-2">
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Use descriptive class names for easy identification
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Class names must be unique across the system
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        You can manage all classes from the "Manage Classes" page
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Sections help organize multiple groups within the same grade level
                    </li>
                </ul>
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
                document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });

        // Auto-hide success message after 5 seconds
        <?php if ($success_message): ?>
            setTimeout(function() {
                const successDiv = document.querySelector('.bg-green-50');
                if (successDiv) {
                    successDiv.style.opacity = '0';
                    successDiv.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => successDiv.remove(), 500);
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>