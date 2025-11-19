<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Get teacher's classes and subjects
$teacher_classes = array_map('trim', explode(',', $_SESSION['teacher_classes']));
$teacher_subjects = array_map('trim', explode(',', $_SESSION['teacher_subject']));
$class_conditions = implode("','", $teacher_classes);

// Handle result deletion
if (isset($_POST['delete_result'])) {
    $subject_mark_id = $_POST['subject_mark_id'];
    
    // Verify subject mark belongs to teacher's class
    $verify_sql = "SELECT sm.id 
                   FROM subject_marks sm 
                   JOIN students s ON sm.student_id = s.id 
                   WHERE sm.id = '$subject_mark_id' 
                   AND s.class_name IN ('$class_conditions')";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if ($verify_result && mysqli_num_rows($verify_result) > 0) {
        $delete_sql = "DELETE FROM subject_marks WHERE id = '$subject_mark_id'";
        if (mysqli_query($conn, $delete_sql)) {
            $success = "Result deleted successfully!";
            
            // Recalculate the student's total marks and percentage
            $student_id_sql = "SELECT student_id FROM subject_marks WHERE id = '$subject_mark_id'";
            $student_id_result = mysqli_query($conn, $student_id_sql);
            if ($student_id_result && mysqli_num_rows($student_id_result) > 0) {
                $student_data = mysqli_fetch_assoc($student_id_result);
                recalculateStudentResults($conn, $student_data['student_id']);
            }
        } else {
            $error = "Error deleting result: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to delete this result.";
    }
}

// Handle filter
$filter_class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : '';
$filter_subject = isset($_GET['subject']) ? mysqli_real_escape_string($conn, $_GET['subject']) : '';

$where_conditions = ["s.class_name IN ('$class_conditions')"];
if ($filter_class) $where_conditions[] = "s.class_name = '$filter_class'";
if ($filter_subject) $where_conditions[] = "sm.subject = '$filter_subject'";

$where_clause = implode(' AND ', $where_conditions);

// Get results data from subject_marks table
$results_sql = "SELECT sm.*, s.name, s.roll_number, s.class_name 
                FROM subject_marks sm 
                JOIN students s ON sm.student_id = s.id 
                WHERE $where_clause 
                ORDER BY s.class_name, s.name, sm.subject";
$results_result = mysqli_query($conn, $results_sql);

// Check if query failed
if (!$results_result) {
    $error = "Database error: " . mysqli_error($conn);
    $total_results = 0;
    $results_result = false;
} else {
    $total_results = mysqli_num_rows($results_result);
}

// Function to recalculate student results
function recalculateStudentResults($conn, $student_id) {
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
        
        // Get student details
        $student_sql = "SELECT roll_number, class_name FROM students WHERE id = '$student_id'";
        $student_result = mysqli_query($conn, $student_sql);
        
        if ($student_result && mysqli_num_rows($student_result) > 0) {
            $student = mysqli_fetch_assoc($student_result);
            
            // Update the results table
            $update_sql = "UPDATE results SET 
                          marks = '$total_marks',
                          percentage = '$percentage',
                          updated_at = NOW()
                          WHERE roll_number = '{$student['roll_number']}' AND class = '{$student['class_name']}'";
            
            mysqli_query($conn, $update_sql);
        }
    }
}

// Function to calculate grade
function calculateGrade($total_marks) {
    if ($total_marks >= 90) return ['A+', 'grade-A-plus'];
    if ($total_marks >= 80) return ['A', 'grade-A'];
    if ($total_marks >= 70) return ['B', 'grade-B'];
    if ($total_marks >= 60) return ['C', 'grade-C'];
    if ($total_marks >= 50) return ['D', 'grade-D'];
    if ($total_marks >= 40) return ['E', 'grade-E'];
    return ['F', 'grade-F'];
}

// Function to calculate student average
function calculateStudentAverage($student_id, $conn) {
    $avg_sql = "SELECT AVG(total_marks) as average 
                FROM subject_marks 
                WHERE student_id = '$student_id'";
    $avg_result = mysqli_query($conn, $avg_sql);
    
    if ($avg_result && mysqli_num_rows($avg_result) > 0) {
        $avg_data = mysqli_fetch_assoc($avg_result);
        return round($avg_data['average'], 2);
    }
    return 0;
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
    <title>Manage Results - Teacher Portal</title>
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
        .grade-A-plus { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F59E0B; color: white; }
        .grade-E { background-color: #EF4444; color: white; }
        .grade-F { background-color: #DC2626; color: white; }
        
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .subject-row:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .subject-row:hover {
            background-color: #f1f5f9;
        }
        
        .marks-breakdown {
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
            <a href="teacher_add_results.php" class="nav-item p-3 flex items-center space-x-3 text-green-600 bg-green-50 rounded">
                <i class="fas fa-plus-circle"></i>
                <span class="font-medium">Add Results</span>
            </a>
            <a href="teacher_manage_results.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-list"></i>
                <span class="font-medium">Manage Results</span>
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
                <h1 class="text-3xl font-bold text-white">Student Results Dashboard</h1>
                <p class="text-white/80">Manage and view student results with CA and Exam breakdown</p>
            </div>
            <a href="teacher_add_results.php" class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add New Result</span>
            </a>
        </div>

        <!-- Marks Breakdown Info -->
        <div class="marks-breakdown rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold mb-3 flex items-center">
                <i class="fas fa-info-circle mr-3"></i>
                Marks Breakdown System
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">40</div>
                    <div class="text-sm">CA Marks</div>
                    <div class="text-xs opacity-80">(0-40 marks)</div>
                </div>
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">60</div>
                    <div class="text-sm">Exam Marks</div>
                    <div class="text-xs opacity-80">(0-60 marks)</div>
                </div>
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">100</div>
                    <div class="text-sm">Total Score</div>
                    <div class="text-xs opacity-80">(0-100 marks)</div>
                </div>
                <div class="text-center p-4 bg-white/20 rounded-lg">
                    <div class="text-2xl font-bold">A+</div>
                    <div class="text-sm">Grading</div>
                    <div class="text-xs opacity-80">(90-100 marks)</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter text-green-500 mr-3"></i>
                Filter Results
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                    <select name="class" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">All Classes</option>
                        <?php foreach ($teacher_classes as $class): ?>
                            <option value="<?php echo $class; ?>" <?php echo $filter_class == $class ? 'selected' : ''; ?>>
                                <?php echo $class; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                    <select name="subject" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">All Subjects</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject; ?>" <?php echo $filter_subject == $subject ? 'selected' : ''; ?>>
                                <?php echo $subject; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-green-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-filter"></i>
                        <span>Apply Filter</span>
                    </button>
                    <a href="teacher_manage_results.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 flex items-center space-x-2">
                        <i class="fas fa-times"></i>
                        <span>Clear</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-green-600 mr-3"></i>
                    Results in Your Classes
                </h3>
                <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm font-medium">
                    <?php echo $total_results . ' Result' . ($total_results != 1 ? 's' : ''); ?>
                </span>
            </div>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($results_result && mysqli_num_rows($results_result) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Student</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Class</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Subject</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">CA Marks</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Exam Marks</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Total</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Grade</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($result = mysqli_fetch_assoc($results_result)): 
                                list($grade, $grade_class) = calculateGrade($result['total_marks']);
                            ?>
                            <tr class="subject-row border-b border-gray-100">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-green-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-gray-800 font-medium"><?php echo $result['name']; ?></div>
                                            <div class="text-gray-500 text-sm">Roll: <?php echo $result['roll_number']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs font-medium">
                                        <?php echo $result['class_name']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-book text-green-500 mr-2"></i>
                                        <span class="text-gray-700 font-medium"><?php echo $result['subject']; ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-full text-xs font-semibold">
                                        <?php echo $result['ca_marks']; ?>/40
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-red-100 text-red-600 px-2 py-1 rounded-full text-xs font-semibold">
                                        <?php echo $result['exam_marks']; ?>/60
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-purple-100 text-purple-600 px-2 py-1 rounded-full text-xs font-semibold">
                                        <?php echo $result['total_marks']; ?>/100
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $grade_class; ?> px-3 py-1 rounded-full text-xs font-semibold">
                                        <?php echo $grade; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <form action="" method="post" class="inline">
                                        <input type="hidden" name="subject_mark_id" value="<?php echo $result['id']; ?>">
                                        <button type="submit" name="delete_result" 
                                                onclick="return confirm('Are you sure you want to delete <?php echo $result['subject']; ?> result for <?php echo $result['name']; ?>?');"
                                                class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition duration-200 flex items-center space-x-1 text-sm">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-green-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php echo ($filter_class || $filter_subject) ? 'No results match your filter criteria.' : 'Get started by adding your first result.'; ?>
                    </p>
                    <a href="teacher_add_results.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-all duration-300 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Your First Result</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>