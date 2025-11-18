<?php
include('init.php');
include('session.php');

$success_message = '';
$error_messages = [];

// PHP LOGIC FOR INSERTION
if(isset($_POST['student_name'], $_POST['roll_no'])) {
    $name = mysqli_real_escape_string($conn, $_POST['student_name']);
    $rno = mysqli_real_escape_string($conn, $_POST['roll_no']);

    if(!isset($_POST['class_name']))
        $class_name = null;
    else
        $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);

    // validation
    $valid = true;
    
    if (empty($name)) {
        $error_messages[] = 'Please enter student name';
        $valid = false;
    }
    if (empty($class_name)) {
        $error_messages[] = 'Please select class';
        $valid = false;
    }
    if (empty($rno)) {
        $error_messages[] = 'Please enter roll number';
        $valid = false;
    }
    if(preg_match("/[a-z]/i",$rno)) {
        $error_messages[] = 'Please enter a valid roll number (numbers only)';
        $valid = false;
    }
    if (!preg_match("/^[a-zA-Z ]*$/",$name)) {
        $error_messages[] = 'No numbers or symbols allowed in name';
        $valid = false;
    }
    
    if ($valid) {
        // First check if student with same roll number already exists in this class
        $check_sql = "SELECT name FROM students WHERE roll_number = '$rno' AND class_name = '$class_name'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $existing_student = mysqli_fetch_assoc($check_result);
            $existing_name = $existing_student['name'];
            $error_messages[] = "🚫 <strong>Student Already Exists!</strong><br>
                                • Roll Number: <strong>$rno</strong><br>
                                • Class: <strong>$class_name</strong><br>
                                • Existing Student: <strong>$existing_name</strong><br><br>
                                Please use a different roll number for this class.";
        } else {
            // SQL: Insert student with correct column names
            $sql = "INSERT INTO `students` (`name`, `roll_number`, `class_name`) 
                    VALUES ('$name', '$rno', '$class_name')";
            $result = mysqli_query($conn, $sql);
            
            if (!$result) {
                $error_msg = mysqli_error($conn);
                
                // Check for duplicate entry error
                if (strpos($error_msg, 'Duplicate entry') !== false) {
                    $error_messages[] = "🚫 <strong>Duplicate Entry Prevented!</strong><br>
                                        A student with roll number <strong>$rno</strong> already exists in <strong>$class_name</strong>.<br>
                                        Each student must have a unique roll number within their class.";
                } else {
                    $error_messages[] = 'Database Error: ' . $error_msg;
                }
            } else {
                $success_message = '✅ <strong>Student Added Successfully!</strong><br>Student "'.$name.'" has been registered with roll number '.$rno.' in '.$class_name.'.';
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
    <title>Add Students</title>
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
                <h1 class="text-3xl font-bold text-white">Add New Student</h1>
                <p class="text-white/80">Register a new student in the system</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="manage_students.php" class="action-btn bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Students</span>
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

        <!-- Add Student Form -->
        <div class="card rounded-2xl p-8">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Register New Student</h3>
                    <p class="text-gray-600">Enter student details to add them to the system</p>
                </div>
            </div>

            <form action="" method="post" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Student Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-blue-500 mr-2"></i>
                            Student Full Name *
                        </label>
                        <input type="text" name="student_name" placeholder="e.g., John Smith" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               value="<?php echo isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : ''; ?>">
                        <p class="text-gray-500 text-sm mt-2">Letters and spaces only, no numbers or symbols</p>
                    </div>

                    <!-- Roll Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card text-green-500 mr-2"></i>
                            Roll Number *
                        </label>
                        <input type="text" name="roll_no" placeholder="e.g., 1001, 2005" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                               value="<?php echo isset($_POST['roll_no']) ? htmlspecialchars($_POST['roll_no']) : ''; ?>">
                        <p class="text-gray-500 text-sm mt-2">Numbers only, no letters or symbols</p>
                    </div>

                    <!-- Class Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard-teacher text-purple-500 mr-2"></i>
                            Class *
                        </label>
                        <select name="class_name" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200">
                            <option value="" selected disabled>Select Class</option>
                            <?php
                                // FIXED: Using 'classes' table instead of 'class'
                                $class_result = mysqli_query($conn, "SELECT `name` FROM `classes`");
                                while($row = mysqli_fetch_array($class_result)){
                                    $display = $row['name'];
                                    $selected = (isset($_POST['class_name']) && $_POST['class_name'] == $display) ? 'selected' : '';
                                    echo '<option value="'.$display.'" '.$selected.'>'.$display.'</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="flex space-x-4 pt-4">
                    <button type="submit" 
                            class="action-btn bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </button>
                    
                    <button type="reset" 
                            class="action-btn bg-gray-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-redo"></i>
                        <span>Clear Form</span>
                    </button>
                </div>
            </form>

            <!-- Important Notes -->
            <div class="mt-8 p-6 bg-blue-50 rounded-xl border border-blue-200">
                <h4 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Important Information
                </h4>
                <ul class="text-blue-700 space-y-2">
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Each student must have a unique Roll Number within their class
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Student name should contain only letters and spaces
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Roll number should contain only numbers
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2 text-sm"></i>
                        Students can be managed from the "Manage Students" page
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nameInput = document.querySelector('input[name="student_name"]');
            const rollInput = document.querySelector('input[name="roll_no"]');
            const classSelect = document.querySelector('select[name="class_name"]');
            
            let valid = true;
            
            // Name validation (letters and spaces only)
            if (!/^[a-zA-Z ]*$/.test(nameInput.value)) {
                nameInput.classList.add('border-red-500', 'bg-red-50');
                valid = false;
            } else {
                nameInput.classList.remove('border-red-500', 'bg-red-50');
            }
            
            // Roll number validation (numbers only)
            if (!/^\d+$/.test(rollInput.value)) {
                rollInput.classList.add('border-red-500', 'bg-red-50');
                valid = false;
            } else {
                rollInput.classList.remove('border-red-500', 'bg-red-50');
            }
            
            // Required fields
            [nameInput, rollInput].forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('border-red-500', 'bg-red-50');
                    valid = false;
                } else {
                    input.classList.remove('border-red-500', 'bg-red-50');
                }
            });
            
            if (classSelect.value === "") {
                classSelect.classList.add('border-red-500', 'bg-red-50');
                valid = false;
            } else {
                classSelect.classList.remove('border-red-500', 'bg-red-50');
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>