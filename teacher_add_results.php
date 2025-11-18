<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

$success = '';
$error = '';

// Get teacher's classes and subjects
$teacher_classes = array_map('trim', explode(',', $_SESSION['teacher_classes']));
$teacher_subjects = array_map('trim', explode(',', $_SESSION['teacher_subject']));
$class_conditions = implode("','", $teacher_classes);

// Subject to database column mapping
$subject_columns = [
    'Mathematics' => 'mathematics',
    'English Studies' => 'english_studies',
    'Basic Science' => 'basic_science',
    'Basic Technology' => 'basic_technology',
    'Social Studies' => 'social_studies',
    'Civic Education' => 'civic_education',
    'Computer Studies / ICT' => 'computer_studies',
    'Physical & Health Education (PHE)' => 'physical_health_education',
    'Agricultural Science' => 'agricultural_science',
    'Yoruba' => 'yoruba',
    'Arabic' => 'arabic',
    'Islamic Religious Studies (IRS)' => 'islamic_studies',
    'Cultural & Creative Arts (CCA)' => 'cultural_creative_arts',
    'Home Economics' => 'home_economics',
    'Business Studies' => 'business_studies'
];

// Get students from teacher's classes for dropdown
$students_sql = "SELECT id, name, roll_number, class_name FROM students WHERE class_name IN ('$class_conditions') ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

// Check if students query was successful
if ($students_result === false) {
    die("Database error: " . mysqli_error($conn));
}

if (isset($_POST['add_result'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $marks = mysqli_real_escape_string($conn, $_POST['marks']);
    
    // Validate marks
    if ($marks < 0 || $marks > 100) {
        $error = "Marks must be between 0 and 100.";
    } else {
        // Get student details and verify they belong to teacher's class
        $student_sql = "SELECT name, roll_number, class_name FROM students WHERE id = '$student_id' AND class_name IN ('$class_conditions')";
        $student_result = mysqli_query($conn, $student_sql);
        
        // Check if student query was successful
        if ($student_result === false) {
            $error = "Database error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($student_result) == 0) {
            $error = "Student not found in your classes.";
        } elseif (!in_array($subject, $teacher_subjects)) {
            $error = "You are not assigned to teach $subject.";
        } else {
            $student_data = mysqli_fetch_assoc($student_result);
            $student_name = $student_data['name'];
            $roll_number = $student_data['roll_number'];
            $class_name = $student_data['class_name'];
            
            // Check if result already exists for this student
            $check_sql = "SELECT id, mathematics, english_studies, basic_science, basic_technology, social_studies, civic_education, computer_studies, physical_health_education, agricultural_science, yoruba, arabic, islamic_studies, cultural_creative_arts, home_economics, business_studies, marks, percentage, teacher_id, teacher_name FROM results WHERE roll_number = '$roll_number' AND class = '$class_name'";
            $check_result = mysqli_query($conn, $check_sql);
            
            // Check if query was successful
            if ($check_result === false) {
                $error = "Database error: " . mysqli_error($conn);
            } elseif (mysqli_num_rows($check_result) > 0) {
                // Student result exists, update the specific subject
                $existing_result = mysqli_fetch_assoc($check_result);
                $result_id = $existing_result['id'];
                
                if (isset($subject_columns[$subject])) {
                    $column_name = $subject_columns[$subject];
                    
                    // Get current values to calculate new total
                    $current_total = $existing_result['marks'];
                    $current_subject_mark = $existing_result[$column_name];
                    
                    // Calculate new total (subtract old subject mark, add new one)
                    $new_total = $current_total - $current_subject_mark + $marks;
                    
                    // Count total subjects with marks to calculate percentage
                    $total_subjects_with_marks = 0;
                    $total_marks_obtained = 0;
                    
                    foreach ($subject_columns as $subject_name => $db_column) {
                        $mark = $existing_result[$db_column];
                        if ($db_column == $column_name) {
                            $mark = $marks; // Use the new mark for the updated subject
                        }
                        if ($mark > 0) {
                            $total_subjects_with_marks++;
                            $total_marks_obtained += $mark;
                        }
                    }
                    
                    // Calculate percentage based on actual subjects with marks
                    $new_percentage = $total_subjects_with_marks > 0 ? ($total_marks_obtained / ($total_subjects_with_marks * 100)) * 100 : 0;
                    
                    // Update teacher info (append if multiple teachers)
                    $current_teacher_id = $existing_result['teacher_id'] ?? '';
                    $current_teacher_name = $existing_result['teacher_name'] ?? '';
                    
                    $new_teacher_id = $_SESSION['teacher_id'];
                    $new_teacher_name = mysqli_real_escape_string($conn, $_SESSION['teacher_name']);
                    
                    // If different teacher and not already recorded, append to existing
                    if (!empty($current_teacher_id) && $current_teacher_id != $new_teacher_id && strpos($current_teacher_id, (string)$new_teacher_id) === false) {
                        $updated_teacher_id = $current_teacher_id . ',' . $new_teacher_id;
                        $updated_teacher_name = $current_teacher_name . ', ' . $new_teacher_name;
                    } else {
                        $updated_teacher_id = $current_teacher_id ?: $new_teacher_id;
                        $updated_teacher_name = $current_teacher_name ?: $new_teacher_name;
                    }
                    
                    // Update the specific subject marks and recalculate total/percentage
                    $update_sql = "UPDATE results SET 
                                  $column_name = '$marks', 
                                  marks = '$new_total', 
                                  percentage = '$new_percentage',
                                  teacher_id = '$updated_teacher_id',
                                  teacher_name = '$updated_teacher_name',
                                  updated_at = NOW()
                                  WHERE id = '$result_id'";
                    
                    if (mysqli_query($conn, $update_sql)) {
                        $success = "Result updated successfully for $student_name in $subject!";
                        $_POST['marks'] = '';
                    } else {
                        $error = "Error updating result: " . mysqli_error($conn);
                    }
                } else {
                    $error = "Invalid subject selected.";
                }
            } else {
                // No existing result, insert new record with only this subject
                // Initialize all subjects to 0
                $insert_data = [];
                $total_marks = $marks;
                
                // Set default values for all subjects
                foreach ($subject_columns as $subject_name => $column) {
                    $insert_data[$column] = 0;
                }
                
                // Set the marks for the specific subject
                if (isset($subject_columns[$subject])) {
                    $insert_data[$subject_columns[$subject]] = $marks;
                }
                
                // Calculate percentage (only this subject for now)
                $percentage = ($marks / 100) * 100;
                
                // Build the insert query
                $columns = implode(', ', array_keys($insert_data));
                $values = implode(', ', array_values($insert_data));
                
                $sql = "INSERT INTO results (name, roll_number, class, $columns, marks, percentage, teacher_id, teacher_name, created_at) 
                        VALUES ('$student_name', '$roll_number', '$class_name', $values, '$total_marks', '$percentage', '".$_SESSION['teacher_id']."', '".mysqli_real_escape_string($conn, $_SESSION['teacher_name'])."', NOW())";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Result added successfully for $student_name in $subject!";
                    // Clear form
                    $_POST['marks'] = '';
                } else {
                    $error = "Error adding result: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Grade calculation function
function calculateGrade($marks) {
    if ($marks >= 90) return 'A+';
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
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
    <title>Add Results - Teacher Portal</title>
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

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('students')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-graduate text-blue-600"></i>
                        <span class="font-medium text-gray-700">Students</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="students">
                    <a href="teacher_add_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Add Students</a>
                    <a href="teacher_manage_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Manage Students</a>
                </div>
            </div>

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('results')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar text-green-600"></i>
                        <span class="font-medium text-gray-700">Results</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="results">
                    <a href="teacher_add_results.php" class="block px-4 py-2 text-green-600 bg-green-50 rounded">Add Results</a>
                    <a href="teacher_manage_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Manage Results</a>
                </div>
            </div>

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
                <h1 class="text-3xl font-bold text-white">Add Results</h1>
                <p class="text-white/80">Add results for students in your classes</p>
            </div>
            <a href="teacher_dashboard.php" class="bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Add Result Form -->
        <div class="card rounded-xl p-6 max-w-2xl">
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-graduate text-green-500 mr-2"></i>
                        Select Student
                    </label>
                    <select name="student_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200">
                        <option value="">Select Student</option>
                        <?php 
                        // Reset pointer and loop through students again
                        mysqli_data_seek($students_result, 0);
                        while ($student = mysqli_fetch_assoc($students_result)): 
                        ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                <?php echo $student['name'] . ' - ' . $student['roll_number'] . ' (' . $student['class_name'] . ')'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book text-green-500 mr-2"></i>
                        Subject
                    </label>
                    <select name="subject" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200">
                        <option value="">Select Subject</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject; ?>" <?php echo (isset($_POST['subject']) && $_POST['subject'] == $subject) ? 'selected' : ''; ?>>
                                <?php echo $subject; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-percentage text-green-500 mr-2"></i>
                        Marks (0-100)
                    </label>
                    <input type="number" name="marks" required min="0" max="100" step="0.01"
                           value="<?php echo isset($_POST['marks']) ? htmlspecialchars($_POST['marks']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                           placeholder="Enter marks">
                    <div id="grade-display" class="mt-2 p-3 rounded-lg text-center font-semibold hidden"></div>
                </div>

                <button type="submit" name="add_result"
                        class="w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Result</span>
                </button>
            </form>
        </div>

        <!-- Grade Legend -->
        <div class="card rounded-xl p-6 mt-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-info-circle text-green-500 mr-3"></i>
                Grading System
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-7 gap-2">
                <div class="grade-A-plus text-center py-2 rounded text-sm font-medium">A+ (90-100)</div>
                <div class="grade-A text-center py-2 rounded text-sm font-medium">A (80-89)</div>
                <div class="grade-B text-center py-2 rounded text-sm font-medium">B (70-79)</div>
                <div class="grade-C text-center py-2 rounded text-sm font-medium">C (60-69)</div>
                <div class="grade-D text-center py-2 rounded text-sm font-medium">D (50-59)</div>
                <div class="grade-E text-center py-2 rounded text-sm font-medium">E (40-49)</div>
                <div class="grade-F text-center py-2 rounded text-sm font-medium">F (0-39)</div>
            </div>
        </div>
    </div>

    <script>
        function toggleDisplay(id) {
            var dropdown = document.getElementById(id);
            var allDropdowns = document.querySelectorAll('.dropdown-content');
            
            allDropdowns.forEach(function(d) {
                if (d.id !== id) {
                    d.style.display = "none";
                }
            });
            
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.nav-item')) {
                document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });

        // Auto-calculate grade when marks are entered
        document.querySelector('input[name="marks"]').addEventListener('input', function() {
            const marks = parseFloat(this.value);
            const gradeDisplay = document.getElementById('grade-display');
            
            if (!isNaN(marks) && marks >= 0 && marks <= 100) {
                let grade = '';
                let gradeClass = '';
                
                if (marks >= 90) { grade = 'A+'; gradeClass = 'grade-A-plus'; }
                else if (marks >= 80) { grade = 'A'; gradeClass = 'grade-A'; }
                else if (marks >= 70) { grade = 'B'; gradeClass = 'grade-B'; }
                else if (marks >= 60) { grade = 'C'; gradeClass = 'grade-C'; }
                else if (marks >= 50) { grade = 'D'; gradeClass = 'grade-D'; }
                else if (marks >= 40) { grade = 'E'; gradeClass = 'grade-E'; }
                else { grade = 'F'; gradeClass = 'grade-F'; }
                
                gradeDisplay.textContent = 'Grade: ' + grade;
                gradeDisplay.className = 'mt-2 p-3 rounded-lg text-center font-semibold ' + gradeClass;
                gradeDisplay.classList.remove('hidden');
            } else {
                gradeDisplay.classList.add('hidden');
            }
        });

        // Show selected values after form submission
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_POST['student_id']) || isset($_POST['subject']) || isset($_POST['marks'])): ?>
                // Form was submitted, values are already set via PHP
            <?php endif; ?>
        });
    </script>
</body>
</html>