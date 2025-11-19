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
    $ca_marks = mysqli_real_escape_string($conn, $_POST['ca_marks']);
    $exam_marks = mysqli_real_escape_string($conn, $_POST['exam_marks']);
    
    // Validate marks
    if ($ca_marks < 0 || $ca_marks > 40) {
        $error = "CA marks must be between 0 and 40.";
    } elseif ($exam_marks < 0 || $exam_marks > 60) {
        $error = "Exam marks must be between 0 and 60.";
    } else {
        $total_marks = $ca_marks + $exam_marks;
        
        // Get student details and verify they belong to teacher's class
        $student_sql = "SELECT id, name, roll_number, class_name FROM students WHERE id = '$student_id' AND class_name IN ('$class_conditions')";
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
            $student_db_id = $student_data['id'];
            
            // Check if result already exists for this student in the main results table
            $check_sql = "SELECT id FROM results WHERE roll_number = '$roll_number' AND class = '$class_name'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if ($check_result === false) {
                $error = "Database error: " . mysqli_error($conn);
            } else {
                $result_id = null;
                
                if (mysqli_num_rows($check_result) > 0) {
                    // Student result exists in main table
                    $existing_result = mysqli_fetch_assoc($check_result);
                    $result_id = $existing_result['id'];
                } else {
                    // Create new entry in main results table
                    $insert_sql = "INSERT INTO results (name, roll_number, class, marks, percentage, teacher_id, teacher_name, created_at) 
                                  VALUES ('$student_name', '$roll_number', '$class_name', '0', '0', '".$_SESSION['teacher_id']."', '".mysqli_real_escape_string($conn, $_SESSION['teacher_name'])."', NOW())";
                    
                    if (mysqli_query($conn, $insert_sql)) {
                        $result_id = mysqli_insert_id($conn);
                    } else {
                        $error = "Error creating result entry: " . mysqli_error($conn);
                    }
                }
                
                if ($result_id && !$error) {
                    // Check if subject marks already exist for this student and subject
                    $check_marks_sql = "SELECT id FROM subject_marks WHERE student_id = '$student_db_id' AND subject = '$subject'";
                    $check_marks_result = mysqli_query($conn, $check_marks_sql);
                    
                    if (mysqli_num_rows($check_marks_result) > 0) {
                        // Update existing subject marks
                        $update_marks_sql = "UPDATE subject_marks SET 
                                           ca_marks = '$ca_marks',
                                           exam_marks = '$exam_marks',
                                           total_marks = '$total_marks',
                                           updated_at = NOW()
                                           WHERE student_id = '$student_db_id' AND subject = '$subject'";
                    } else {
                        // Insert new subject marks
                        $update_marks_sql = "INSERT INTO subject_marks (result_id, student_id, subject, ca_marks, exam_marks, total_marks) 
                                           VALUES ('$result_id', '$student_db_id', '$subject', '$ca_marks', '$exam_marks', '$total_marks')";
                    }
                    
                    if (mysqli_query($conn, $update_marks_sql)) {
                        // Recalculate total marks and percentage
                        recalculateStudentResults($conn, $student_db_id, $roll_number, $class_name);
                        
                        $success = "Result " . (mysqli_num_rows($check_marks_result) > 0 ? "updated" : "added") . " successfully for $student_name in $subject! (CA: $ca_marks/40, Exam: $exam_marks/60, Total: $total_marks/100)";
                        $_POST['ca_marks'] = '';
                        $_POST['exam_marks'] = '';
                    } else {
                        $error = "Error " . (mysqli_num_rows($check_marks_result) > 0 ? "updating" : "adding") . " subject marks: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Function to recalculate total marks and percentage
function recalculateStudentResults($conn, $student_id, $roll_number, $class_name) {
    // Get all subject marks for this student
    $marks_sql = "SELECT SUM(total_marks) as total_marks, COUNT(*) as subjects_count 
                  FROM subject_marks 
                  WHERE student_id = '$student_id'";
    $marks_result = mysqli_query($conn, $marks_sql);
    
    if ($marks_result && mysqli_num_rows($marks_result) > 0) {
        $marks_data = mysqli_fetch_assoc($marks_result);
        $total_marks = $marks_data['total_marks'] ?? 0;
        $subjects_count = $marks_data['subjects_count'] ?? 0;
        
        // Calculate percentage
        $percentage = $subjects_count > 0 ? ($total_marks / ($subjects_count * 100)) * 100 : 0;
        
        // Update the results table
        $update_sql = "UPDATE results SET 
                      marks = '$total_marks',
                      percentage = '$percentage',
                      updated_at = NOW()
                      WHERE roll_number = '$roll_number' AND class = '$class_name'";
        
        mysqli_query($conn, $update_sql);
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
        .marks-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                <p class="text-white/80">Add CA and Exam marks for students in your classes</p>
            </div>
            <a href="teacher_dashboard.php" class="bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Marks Breakdown Info -->
        <div class="marks-summary rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold mb-3 flex items-center">
                <i class="fas fa-info-circle mr-3"></i>
                Marks Breakdown
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">40%</div>
                    <div class="text-sm">Continuous Assessment</div>
                    <div class="text-xs opacity-80">(0-40 marks)</div>
                </div>
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">60%</div>
                    <div class="text-sm">Examination</div>
                    <div class="text-xs opacity-80">(0-60 marks)</div>
                </div>
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">100%</div>
                    <div class="text-sm">Total Score</div>
                    <div class="text-xs opacity-80">(0-100 marks)</div>
                </div>
            </div>
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-edit text-blue-500 mr-2"></i>
                            CA Marks (0-40)
                        </label>
                        <input type="number" name="ca_marks" required min="0" max="40" step="0.5"
                               value="<?php echo isset($_POST['ca_marks']) ? htmlspecialchars($_POST['ca_marks']) : ''; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               placeholder="CA marks">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-alt text-red-500 mr-2"></i>
                            Exam Marks (0-60)
                        </label>
                        <input type="number" name="exam_marks" required min="0" max="60" step="0.5"
                               value="<?php echo isset($_POST['exam_marks']) ? htmlspecialchars($_POST['exam_marks']) : ''; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                               placeholder="Exam marks">
                    </div>
                </div>

                <!-- Total Marks Display -->
                <div id="total-display" class="p-4 bg-gray-50 rounded-lg border border-gray-200 hidden">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-700 font-medium">Total Marks:</span>
                        <span id="total-marks" class="text-2xl font-bold text-green-600">0</span>
                    </div>
                    <div id="grade-display" class="text-center font-semibold"></div>
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

        // Calculate total marks and grade when CA or Exam marks are entered
        function calculateTotalAndGrade() {
            const caMarks = parseFloat(document.querySelector('input[name="ca_marks"]').value) || 0;
            const examMarks = parseFloat(document.querySelector('input[name="exam_marks"]').value) || 0;
            const totalMarks = caMarks + examMarks;
            const totalDisplay = document.getElementById('total-display');
            const totalMarksSpan = document.getElementById('total-marks');
            const gradeDisplay = document.getElementById('grade-display');

            if (caMarks > 0 || examMarks > 0) {
                totalMarksSpan.textContent = totalMarks + '/100';
                
                let grade = '';
                let gradeClass = '';
                
                if (totalMarks >= 90) { grade = 'A+'; gradeClass = 'grade-A-plus'; }
                else if (totalMarks >= 80) { grade = 'A'; gradeClass = 'grade-A'; }
                else if (totalMarks >= 70) { grade = 'B'; gradeClass = 'grade-B'; }
                else if (totalMarks >= 60) { grade = 'C'; gradeClass = 'grade-C'; }
                else if (totalMarks >= 50) { grade = 'D'; gradeClass = 'grade-D'; }
                else if (totalMarks >= 40) { grade = 'E'; gradeClass = 'grade-E'; }
                else { grade = 'F'; gradeClass = 'grade-F'; }
                
                gradeDisplay.innerHTML = `Grade: <span class="${gradeClass} px-3 py-1 rounded-full">${grade}</span>`;
                totalDisplay.classList.remove('hidden');
            } else {
                totalDisplay.classList.add('hidden');
            }
        }

        // Add event listeners to both marks inputs
        document.querySelector('input[name="ca_marks"]').addEventListener('input', calculateTotalAndGrade);
        document.querySelector('input[name="exam_marks"]').addEventListener('input', calculateTotalAndGrade);

        // Show selected values after form submission
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_POST['student_id']) || isset($_POST['subject']) || isset($_POST['ca_marks']) || isset($_POST['exam_marks'])): ?>
                calculateTotalAndGrade();
            <?php endif; ?>
        });
    </script>
</body>
</html>