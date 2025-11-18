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

// Get only the subjects this teacher teaches
$teacher_subject_columns = [];
foreach ($teacher_subjects as $subject) {
    if (isset($subject_columns[$subject])) {
        $teacher_subject_columns[$subject] = $subject_columns[$subject];
    }
}

// Handle result deletion
if (isset($_POST['delete_result'])) {
    $result_id = $_POST['result_id'];
    
    // Verify result belongs to teacher's class
    $verify_sql = "SELECT class FROM results WHERE id = '$result_id' AND class IN ('$class_conditions')";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if ($verify_result && mysqli_num_rows($verify_result) > 0) {
        $delete_sql = "DELETE FROM results WHERE id = '$result_id'";
        if (mysqli_query($conn, $delete_sql)) {
            $success = "Result deleted successfully!";
        } else {
            $error = "Error deleting result: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to delete this result.";
    }
}

// Handle filter
$filter_class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : '';

$where_conditions = ["class IN ('$class_conditions')"];
if ($filter_class) $where_conditions[] = "class = '$filter_class'";

$where_clause = implode(' AND ', $where_conditions);

// Get results data
$results_sql = "SELECT * FROM results WHERE $where_clause ORDER BY class, name";
$results_result = mysqli_query($conn, $results_sql);

// Check if query failed
if (!$results_result) {
    $error = "Database error: " . mysqli_error($conn);
    $total_results = 0;
    $results_result = false;
} else {
    $total_results = mysqli_num_rows($results_result);
}

// Function to calculate percentage
function calculatePercentage($marks) {
    return round(($marks / 100) * 100, 2);
}

// Function to calculate grade
function calculateGrade($percentage) {
    if ($percentage >= 90) return ['A+', 'grade-A-plus'];
    if ($percentage >= 80) return ['A', 'grade-A'];
    if ($percentage >= 70) return ['B', 'grade-B'];
    if ($percentage >= 60) return ['C', 'grade-C'];
    if ($percentage >= 50) return ['D', 'grade-D'];
    if ($percentage >= 40) return ['E', 'grade-E'];
    return ['F', 'grade-F'];
}

// Function to calculate student average
function calculateStudentAverage($result, $teacher_subject_columns) {
    $total_marks = 0;
    $subject_count = 0;
    
    foreach ($teacher_subject_columns as $column) {
        if (isset($result[$column]) && $result[$column] > 0) {
            $total_marks += $result[$column];
            $subject_count++;
        }
    }
    
    return $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
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
    </style>
</head>
<body class="flex">
    <!-- Sidebar (Keep your existing sidebar code) -->
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
                <p class="text-white/80">Manage and view student results by subject</p>
            </div>
            <a href="teacher_add_results.php" class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add New Result</span>
            </a>
        </div>

        <!-- Filter Section -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter text-green-500 mr-3"></i>
                Filter Results
            </h3>
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1">
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
                    <?php echo $total_results . ' Student' . ($total_results != 1 ? 's' : ''); ?>
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
                <div class="space-y-6">
                    <?php while ($student = mysqli_fetch_assoc($results_result)): 
                        $average = calculateStudentAverage($student, $teacher_subject_columns);
                        list($avg_grade, $avg_grade_class) = calculateGrade($average);
                    ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <!-- Student Header -->
                        <div class="student-header px-6 py-4">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-white"><?php echo $student['name']; ?></h4>
                                        <p class="text-white/80 text-sm">Roll Number: <?php echo $student['roll_number']; ?> | Class: <?php echo $student['class']; ?></p>
                                    </div>
                                </div>
                                <div class="mt-2 md:mt-0 text-right">
                                    <div class="text-white/80 text-sm">Overall Average</div>
                                    <div class="text-xl font-bold text-white"><?php echo $average; ?>%</div>
                                    <span class="text-white/90 text-sm">Grade: <?php echo $avg_grade; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b">
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Subject</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Marks</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Percentage</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Grade</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacher_subject_columns as $subject_name => $column): 
                                        $marks = isset($student[$column]) ? (int)$student[$column] : 0;
                                        $percentage = calculatePercentage($marks);
                                        list($grade, $grade_class) = calculateGrade($percentage);
                                        
                                        if ($marks > 0): // Only show subjects with marks
                                    ?>
                                    <tr class="subject-row border-b border-gray-100">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-book text-green-500 mr-3"></i>
                                                <span class="text-gray-800 font-medium"><?php echo $subject_name; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-gray-600 font-semibold"><?php echo $marks; ?>/100</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-blue-600 font-semibold"><?php echo $percentage; ?>%</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?php echo $grade_class; ?> px-3 py-1 rounded-full text-xs font-semibold">
                                                <?php echo $grade; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <form action="" method="post" class="inline">
                                                <input type="hidden" name="result_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" name="delete_result" 
                                                        onclick="return confirm('Are you sure you want to delete <?php echo $subject_name; ?> result for <?php echo $student['name']; ?>?');"
                                                        class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition duration-200 flex items-center space-x-1 text-sm">
                                                    <i class="fas fa-trash"></i>
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-green-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php echo $filter_class ? 'No results match your filter criteria.' : 'Get started by adding your first result.'; ?>
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