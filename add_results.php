<?php
include('init.php');
include('session.php');

$success_message = '';
$error_message = '';

// --- INSERTION LOGIC ---
// if(isset($_POST['st_id'],$_POST['p1'],$_POST['p2'],$_POST['p3'],$_POST['p4'],$_POST['p5'])) {
//     $st_id = $_POST['st_id'];
//     if(!isset($_POST['class_name']))
//         $class_name = null;
//     else
//         $class_name = $_POST['class_name'];
//     $p1 = (int)$_POST['p1'];
//     $p2 = (int)$_POST['p2'];
//     $p3 = (int)$_POST['p3'];
//     $p4 = (int)$_POST['p4'];
//     $p5 = (int)$_POST['p5'];

//     $marks = $p1 + $p2 + $p3 + $p4 + $p5;
//     $percentage = $marks / 5;

//     // validation
//     if (empty($class_name) || empty($st_id) || $p1 > 100 || $p2 > 100 || $p3 > 100 || $p4 > 100 || $p5 > 100 || $p1 < 0 || $p2 < 0 || $p3 < 0 || $p4 < 0 || $p5 < 0) {
//         if(empty($class_name))
//             $error_message = 'Please select class';
//         elseif(empty($st_id))
//             $error_message = 'Please select student';
//         elseif($p1>100 || $p2>100 || $p3>100 || $p4>100 || $p5>100 || $p1<0 || $p2<0 || $p3<0 || $p4<0 || $p5<0)
//             $error_message = 'Please enter valid marks (0-100)';
//     } else {
//         // Get student details - FIXED: using roll_number instead of rno
//         $student_query = mysqli_query($conn, "SELECT `name`, `roll_number` FROM `students` WHERE `id`='$st_id'");
        
//         if ($student_query && mysqli_num_rows($student_query) > 0) {
//             $student_row = mysqli_fetch_array($student_query);
//             $display_name = $student_row['name'];
//             $roll_number = $student_row['roll_number'];
            
//             // Check if result already exists
//             $check_sql = "SELECT * FROM `result` WHERE `roll_number`='$roll_number' and `class`='$class_name'";
//             $check_res = mysqli_query($conn, $check_sql);

//             if($check_res && mysqli_num_rows($check_res) > 0) {
//                 // Update existing result
//                 $sql = "UPDATE `result` SET `name`='$display_name', `p1`='$p1', `p2`='$p2', `p3`='$p3', `p4`='$p4', `p5`='$p5', `marks`='$marks', `percentage`='$percentage' WHERE `roll_number`='$roll_number' AND `class`='$class_name'";
//                 $action = 'updated';
//             } else {
//                 // Insert new result
//                 $sql = "INSERT INTO `result` (`name`, `roll_number`, `class`, `p1`, `p2`, `p3`, `p4`, `p5`, `marks`, `percentage`) VALUES ('$display_name', '$roll_number', '$class_name', '$p1', '$p2', '$p3', '$p4', '$p5', '$marks', '$percentage')";
//                 $action = 'added';
//             }
            
//             $result = mysqli_query($conn, $sql);

//             if (!$result) {
//                 $error_message = 'Result not ' . $action . '. Error: ' . mysqli_error($conn);
//             } else {
//                 $success_message = 'Result ' . $action . ' successfully for ' . $display_name . '! Total Marks: ' . $marks . ' (' . number_format($percentage, 2) . '%)';
//             }
//         } else {
//             $error_message = 'Student not found. Please try again.';
//         }
//     }
// }



// --- INSERTION LOGIC ---
if(isset($_POST['st_id'],$_POST['p1'],$_POST['p2'],$_POST['p3'],$_POST['p4'],$_POST['p5'])) {
    $st_id = $_POST['st_id'];
    if(!isset($_POST['class_name']))
        $class_name = null;
    else
        $class_name = $_POST['class_name'];
    $p1 = (int)$_POST['p1'];
    $p2 = (int)$_POST['p2'];
    $p3 = (int)$_POST['p3'];
    $p4 = (int)$_POST['p4'];
    $p5 = (int)$_POST['p5'];

    $marks = $p1 + $p2 + $p3 + $p4 + $p5;
    $percentage = $marks / 5;

    // validation
    if (empty($class_name) || empty($st_id) || $p1 > 100 || $p2 > 100 || $p3 > 100 || $p4 > 100 || $p5 > 100 || $p1 < 0 || $p2 < 0 || $p3 < 0 || $p4 < 0 || $p5 < 0) {
        if(empty($class_name))
            $error_message = 'Please select class';
        elseif(empty($st_id))
            $error_message = 'Please select student';
        elseif($p1>100 || $p2>100 || $p3>100 || $p4>100 || $p5>100 || $p1<0 || $p2<0 || $p3<0 || $p4<0 || $p5<0)
            $error_message = 'Please enter valid marks (0-100)';
    } else {
        // Get student details
        $student_query = mysqli_query($conn, "SELECT `name`, `roll_number` FROM `students` WHERE `id`='$st_id'");
        
        if ($student_query && mysqli_num_rows($student_query) > 0) {
            $student_row = mysqli_fetch_array($student_query);
            $display_name = $student_row['name'];
            $roll_number = $student_row['roll_number'];
            
            // Check if result already exists in the new 'results' table
            $check_sql = "SELECT * FROM `results` WHERE `roll_number`='$roll_number' and `class`='$class_name'";
            $check_res = mysqli_query($conn, $check_sql);

            if($check_res && mysqli_num_rows($check_res) > 0) {
                // Update existing result
                $sql = "UPDATE `results` SET `name`='$display_name', `p1`='$p1', `p2`='$p2', `p3`='$p3', `p4`='$p4', `p5`='$p5', `marks`='$marks', `percentage`='$percentage' WHERE `roll_number`='$roll_number' AND `class`='$class_name'";
                $action = 'updated';
            } else {
                // Insert new result
                $sql = "INSERT INTO `results` (`name`, `roll_number`, `class`, `p1`, `p2`, `p3`, `p4`, `p5`, `marks`, `percentage`) VALUES ('$display_name', '$roll_number', '$class_name', '$p1', '$p2', '$p3', '$p4', '$p5', '$marks', '$percentage')";
                $action = 'added';
            }
            
            $result = mysqli_query($conn, $sql);

            if (!$result) {
                $error_message = 'Result not ' . $action . '. Error: ' . mysqli_error($conn);
            } else {
                $success_message = 'Result ' . $action . ' successfully for ' . $display_name . '! Total Marks: ' . $marks . ' (' . number_format($percentage, 2) . '%)';
            }
        } else {
            $error_message = 'Student not found. Please try again.';
        }
    }
}
// --- END INSERTION LOGIC ---





// --- END INSERTION LOGIC ---

// Get students for dropdown when class is selected
$students = [];
if (isset($_POST['class_name'])) {
    $selected_class = mysqli_real_escape_string($conn, $_POST['class_name']);
    
    // FIXED: Only use class_name since we know it exists
    $student_query = mysqli_query($conn, "SELECT `id`, `name`, `roll_number` FROM `students` WHERE `class_name`='$selected_class' ORDER BY `roll_number` ASC");
    
    if ($student_query && mysqli_num_rows($student_query) > 0) {
        while($row = mysqli_fetch_array($student_query)) {
            $students[] = $row;
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
    <title>Add Results</title>
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
        .marks-input {
            transition: all 0.3s ease;
        }
        .marks-input:focus {
            transform: scale(1.02);
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
                <h1 class="text-3xl font-bold text-white">Add Results</h1>
                <p class="text-white/80">Enter student marks and manage academic results</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="manage_results.php" class="action-btn bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Results</span>
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

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="card rounded-2xl p-6 mb-6 border-l-4 border-red-500 bg-red-50">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Error</h3>
                        <p class="text-red-600"><?php echo $error_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add Results Form -->
        <div class="card rounded-2xl p-8">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Enter Student Marks</h3>
                    <p class="text-gray-600">Select class and student to enter marks (out of 100)</p>
                </div>
            </div>

            <form action="" method="post" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Class Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard-teacher text-purple-500 mr-2"></i>
                            Select Class *
                        </label>
                        <select name="class_name" onchange="this.form.submit()" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200 cursor-pointer">
                            <option value="" selected disabled>Choose Class</option>
                            <?php
                                // Try different table names
                                $class_result = mysqli_query($conn, "SELECT `name` FROM `classes`");
                                if (!$class_result) {
                                    $class_result = mysqli_query($conn, "SELECT `name` FROM `class`");
                                }
                                
                                if (!$class_result) {
                                    echo '<option value="">Database Error: ' . mysqli_error($conn) . '</option>';
                                } elseif (mysqli_num_rows($class_result) == 0) {
                                    echo '<option value="">No classes found</option>';
                                } else {
                                    while($row = mysqli_fetch_array($class_result)) {
                                        $display = $row['name'];
                                        $selected = (isset($_POST['class_name']) && $_POST['class_name'] == $display) ? 'selected' : '';
                                        echo '<option value="'.$display.'" '.$selected.'>'.$display.'</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>

                    <!-- Student Selection (Only shows when class is selected) -->
                    <?php if (isset($_POST['class_name'])): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-graduate text-blue-500 mr-2"></i>
                                Select Student *
                            </label>
                            <select name="st_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 cursor-pointer">
                                <option value="" selected disabled>Choose Student</option>
                                <?php
                                    if (empty($students)) {
                                        echo '<option value="">No students found in this class</option>';
                                    } else {
                                        foreach ($students as $student) {
                                            // FIXED: using roll_number instead of rno
                                            $display = $student['name'] . ' (Roll No: ' . $student['roll_number'] . ')';
                                            $selected = (isset($_POST['st_id']) && $_POST['st_id'] == $student['id']) ? 'selected' : '';
                                            echo '<option value="'.$student['id'].'" '.$selected.'>'.$display.'</option>';
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Marks Input Fields (Only shows when class is selected) -->
                <?php if (isset($_POST['class_name'])): ?>
                    <div class="mt-8">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-edit text-green-500 mr-2"></i>
                            Enter Subject Marks (Out of 100)
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Paper 1 *
                                </label>
                                <input type="number" name="p1" placeholder="Marks" 
                                       min="0" max="100" 
                                       value="<?php echo isset($_POST['p1']) ? $_POST['p1'] : ''; ?>"
                                       class="marks-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Paper 2 *
                                </label>
                                <input type="number" name="p2" placeholder="Marks" 
                                       min="0" max="100"
                                       value="<?php echo isset($_POST['p2']) ? $_POST['p2'] : ''; ?>"
                                       class="marks-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Paper 3 *
                                </label>
                                <input type="number" name="p3" placeholder="Marks" 
                                       min="0" max="100"
                                       value="<?php echo isset($_POST['p3']) ? $_POST['p3'] : ''; ?>"
                                       class="marks-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Paper 4 *
                                </label>
                                <input type="number" name="p4" placeholder="Marks" 
                                       min="0" max="100"
                                       value="<?php echo isset($_POST['p4']) ? $_POST['p4'] : ''; ?>"
                                       class="marks-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Paper 5 *
                                </label>
                                <input type="number" name="p5" placeholder="Marks" 
                                       min="0" max="100"
                                       value="<?php echo isset($_POST['p5']) ? $_POST['p5'] : ''; ?>"
                                       class="marks-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                                       required>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex space-x-4 mt-6">
                            <button type="submit" 
                                    class="action-btn bg-green-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-green-700 transition-all duration-300 flex items-center space-x-2">
                                <i class="fas fa-save"></i>
                                <span>Submit Marks</span>
                            </button>
                            
                            <button type="reset" 
                                    class="action-btn bg-gray-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-all duration-300 flex items-center space-x-2">
                                <i class="fas fa-redo"></i>
                                <span>Clear Marks</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Instructions -->
            <?php if (!isset($_POST['class_name'])): ?>
                <div class="mt-8 p-6 bg-yellow-50 rounded-xl border border-yellow-200">
                    <h4 class="text-lg font-semibold text-yellow-800 mb-3 flex items-center">
                        <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
                        How to Add Results
                    </h4>
                    <ol class="text-yellow-700 space-y-2 list-decimal list-inside">
                        <li>Select a class from the dropdown above</li>
                        <li>Choose a student from the class</li>
                        <li>Enter marks for all 5 subjects (out of 100)</li>
                        <li>Submit to save the results</li>
                    </ol>
                </div>
            <?php endif; ?>
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