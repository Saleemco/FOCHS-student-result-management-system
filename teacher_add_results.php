<?php
session_start();
include('init.php');

// FIXED: Allow both teacher types with proper session check
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Allow both teacher types
$allowed_types = ['teacher', 'class_teacher'];
if (!in_array($_SESSION['user_type'] ?? '', $allowed_types)) {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_email = $_SESSION['teacher_email'];
$teacher_subject = $_SESSION['teacher_subject'];
$teacher_classes = $_SESSION['teacher_classes'];

$success = '';
$error = '';

// Get teacher's classes and subjects
$teacher_classes_array = !empty($teacher_classes) ? array_map('trim', explode(',', $teacher_classes)) : [];
$teacher_subjects_array = !empty($teacher_subject) ? array_map('trim', explode(',', $teacher_subject)) : [];

// Get students from teacher's classes for dropdown
$students_result = null;
if (!empty($teacher_classes_array)) {
    $class_conditions = "'" . implode("','", $teacher_classes_array) . "'";
    $students_sql = "SELECT id, name, roll_number, class_name FROM students WHERE class_name IN ($class_conditions) ORDER BY class_name, name";
    $students_result = mysqli_query($conn, $students_sql);
    
    if ($students_result === false) {
        $error = "Database error: " . mysqli_error($conn);
    }
}

// FIXED: Get only the subjects this teacher teaches
$subjects_result = null;
if (!empty($teacher_subjects_array)) {
    // Clean the subject names and create conditions
    $cleaned_subjects = array_map(function($subject) use ($conn) {
        return mysqli_real_escape_string($conn, trim($subject));
    }, $teacher_subjects_array);
    
    $subject_conditions = "'" . implode("','", $cleaned_subjects) . "'";
    $subjects_sql = "SELECT id, subject_name FROM subjects WHERE subject_name IN ($subject_conditions) ORDER BY subject_name";
    $subjects_result = mysqli_query($conn, $subjects_sql);
    
    if ($subjects_result === false) {
        $error = "Database error loading subjects: " . mysqli_error($conn);
    }
}

// Handle form submission
if (isset($_POST['add_result'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $ca_marks = (float)$_POST['ca_marks'];
    $exam_marks = (float)$_POST['exam_marks'];
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    
    // Validate marks
    if ($ca_marks < 0 || $ca_marks > 40) {
        $error = "CA marks must be between 0 and 40.";
    } elseif ($exam_marks < 0 || $exam_marks > 60) {
        $error = "Exam marks must be between 0 and 60.";
    } else {
        $total_marks = $ca_marks + $exam_marks;
        
        // Get student details
        $student_sql = "SELECT id, name, roll_number, class_name FROM students WHERE id = '$student_id'";
        $student_result = mysqli_query($conn, $student_sql);
        
        if ($student_result && mysqli_num_rows($student_result) > 0) {
            $student_data = mysqli_fetch_assoc($student_result);
            $student_name = $student_data['name'];
            
            // Get subject name for success message
            $subject_sql = "SELECT subject_name FROM subjects WHERE id = '$subject_id'";
            $subject_result = mysqli_query($conn, $subject_sql);
            $subject_data = mysqli_fetch_assoc($subject_result);
            $subject_name = $subject_data['subject_name'];
            
            // Check if result already exists
            $check_sql = "SELECT id FROM results WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND term = '$term' AND session = '$session'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if ($check_result === false) {
                $error = "Database error: " . mysqli_error($conn);
            } else {
                if (mysqli_num_rows($check_result) > 0) {
                    // UPDATE EXISTING RESULT - FIXED: Uses current teacher_id
                    $existing_result = mysqli_fetch_assoc($check_result);
                    $result_id = $existing_result['id'];
                    
                    $update_sql = "UPDATE results SET 
                                  ca_score = '$ca_marks',
                                  exam_score = '$exam_marks',
                                  total_score = '$total_marks',
                                  teacher_id = '$teacher_id'
                                  WHERE id = '$result_id'";
                    
                    if (mysqli_query($conn, $update_sql)) {
                        $success = "Result updated successfully for $student_name in $subject_name!";
                        $_POST = array(); // Clear form
                    } else {
                        $error = "Error updating result: " . mysqli_error($conn);
                    }
                } else {
                    // INSERT NEW RESULT - FIXED: Uses current teacher_id
                    $insert_sql = "INSERT INTO results (
                                  student_id, teacher_id, term, session, subject_id,
                                  ca_score, exam_score, total_score
                                  ) VALUES (
                                  '$student_id', '$teacher_id', '$term', '$session', '$subject_id',
                                  '$ca_marks', '$exam_marks', '$total_marks'
                                  )";
                    
                    if (mysqli_query($conn, $insert_sql)) {
                        $success = "Result added successfully for $student_name in $subject_name!";
                        $_POST = array(); // Clear form
                    } else {
                        $error = "Error adding result: " . mysqli_error($conn);
                    }
                }
            }
        } else {
            $error = "Student not found.";
        }
    }
}

// Grade calculation function (for display only)
function calculateGrade($marks) {
    if ($marks >= 90) return 'A+';
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    return 'F';
}

// Get current academic year and terms
$current_year = date('Y');
$next_year = $current_year + 1;
$current_session = $current_year . '/' . $next_year;
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
            background: rgba(59, 130, 246, 0.1);
            transform: translateX(5px);
        }
        .teacher-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
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
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 5px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-item:hover {
            background: #f8fafc;
        }
        .subject-badge {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 teacher-badge rounded-full flex items-center justify-center">
                    <i class="fas fa-user-graduate text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Teacher Portal</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-blue-50 rounded-lg p-4 mb-6">
            <?php if ($_SESSION['user_type'] === 'class_teacher'): ?>
                <div class="teacher-badge rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    Class Teacher
                </div>
            <?php else: ?>
                <div class="teacher-badge rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    Subject Teacher
                </div>
            <?php endif; ?>
            <h3 class="font-semibold text-blue-800 text-sm"><?php echo $teacher_name; ?></h3>
            <p class="text-blue-600 text-xs">Subjects: <?php echo $teacher_subject; ?></p>
            <p class="text-blue-500 text-xs mt-1">Classes: <?php echo $teacher_classes; ?></p>
        </div>

        <nav class="space-y-2">
            <a href="teacher_dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt text-blue-600"></i>
                <span class="font-medium">Teacher Dashboard</span>
            </a>

            <a href="teacher_manage_students.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users text-blue-600"></i>
                <span class="font-medium">View Students</span>
            </a>

            <!-- Results Dropdown Menu -->
            <div class="dropdown relative">
                <div class="nav-item p-3 flex items-center space-x-3 text-blue-600 bg-blue-50 rounded cursor-pointer">
                    <i class="fas fa-chart-bar text-blue-600"></i>
                    <span class="font-medium">Results Management</span>
                    <i class="fas fa-chevron-down text-blue-400 text-xs ml-auto"></i>
                </div>
                <div class="dropdown-content">
                    <a href="teacher_add_results.php" class="dropdown-item text-green-600 hover:text-green-700 bg-green-50">
                        <i class="fas fa-plus-circle text-green-500"></i>
                        <div>
                            <div class="font-medium">Add Results</div>
                            <div class="text-xs text-gray-500">Enter new student results</div>
                        </div>
                    </a>
                    <a href="teacher_manage_results.php" class="dropdown-item text-purple-600 hover:text-purple-700">
                        <i class="fas fa-edit text-purple-500"></i>
                        <div>
                            <div class="font-medium">Manage Results</div>
                            <div class="text-xs text-gray-500">View and edit existing results</div>
                        </div>
                    </a>
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

        <!-- Teacher Subjects Info -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-book text-purple-500 mr-3"></i>
                My Teaching Subjects
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php if (!empty($teacher_subjects_array)): ?>
                    <?php foreach ($teacher_subjects_array as $subject): ?>
                        <span class="subject-badge px-3 py-1 rounded-full text-sm font-semibold">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo htmlspecialchars(trim($subject)); ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600">No subjects assigned. Please contact administrator.</p>
                <?php endif; ?>
            </div>
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
        <div class="card rounded-xl p-6 max-w-2xl mx-auto">
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-blue-500 mr-2"></i>
                            Term
                        </label>
                        <select name="term" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                            <option value="">Select Term</option>
                            <option value="First Term" <?php echo (isset($_POST['term']) && $_POST['term'] == 'First Term') ? 'selected' : ''; ?>>First Term</option>
                            <option value="Second Term" <?php echo (isset($_POST['term']) && $_POST['term'] == 'Second Term') ? 'selected' : ''; ?>>Second Term</option>
                            <option value="Third Term" <?php echo (isset($_POST['term']) && $_POST['term'] == 'Third Term') ? 'selected' : ''; ?>>Third Term</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                            Session
                        </label>
                        <input type="text" name="session" required 
                               value="<?php echo isset($_POST['session']) ? htmlspecialchars($_POST['session']) : $current_session; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               placeholder="e.g., 2024/2025">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-graduate text-green-500 mr-2"></i>
                        Select Student
                    </label>
                    <select name="student_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200">
                        <option value="">Select Student</option>
                        <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo $student['name'] . ' - ' . $student['roll_number'] . ' (' . $student['class_name'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                            <?php mysqli_data_seek($students_result, 0); ?>
                        <?php else: ?>
                            <option value="" disabled>No students found in your classes</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book text-green-500 mr-2"></i>
                        Subject
                    </label>
                    <select name="subject_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200">
                        <option value="">Select Subject</option>
                        <?php if ($subjects_result && mysqli_num_rows($subjects_result) > 0): ?>
                            <?php while ($subject = mysqli_fetch_assoc($subjects_result)): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                    <?php echo $subject['subject_name']; ?>
                                </option>
                            <?php endwhile; ?>
                            <?php mysqli_data_seek($subjects_result, 0); ?>
                        <?php else: ?>
                            <option value="" disabled>No subjects found in your teaching profile</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($teacher_subjects_array)): ?>
                        <p class="text-red-500 text-sm mt-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            You don't have any subjects assigned. Please contact administrator.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
        <div class="card rounded-xl p-6 mt-8 max-w-2xl mx-auto">
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

        document.querySelector('input[name="ca_marks"]').addEventListener('input', calculateTotalAndGrade);
        document.querySelector('input[name="exam_marks"]').addEventListener('input', calculateTotalAndGrade);

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(event.target)) {
                    dropdown.querySelector('.dropdown-content').style.display = 'none';
                }
            });
        });

        // Toggle dropdown on click
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                if (e.target.closest('.nav-item')) {
                    const content = this.querySelector('.dropdown-content');
                    content.style.display = content.style.display === 'block' ? 'none' : 'block';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_POST['ca_marks']) || isset($_POST['exam_marks'])): ?>
                calculateTotalAndGrade();
            <?php endif; ?>
        });
    </script>
</body>
</html>