<?php
session_start();
include('init.php');

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// Get teacher's classes
$teacher_classes = array_map('trim', explode(',', $_SESSION['teacher_classes']));
$class_conditions = implode("','", $teacher_classes);

// Handle student deletion
if (isset($_POST['delete_student'])) {
    $student_id = $_POST['student_id'];
    
    // Verify student belongs to teacher's class
    $verify_sql = "SELECT class_name FROM students WHERE id = '$student_id' AND class_name IN ('$class_conditions')";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $delete_sql = "DELETE FROM students WHERE id = '$student_id'";
        if (mysqli_query($conn, $delete_sql)) {
            $success = "Student deleted successfully!";
        } else {
            $error = "Error deleting student: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to delete this student.";
    }
}

// Get students from teacher's classes
$students_sql = "SELECT * FROM students WHERE class_name IN ('$class_conditions') ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_sql);

// Count total students
$count_sql = "SELECT COUNT(*) as total FROM students WHERE class_name IN ('$class_conditions')";
$count_result = mysqli_query($conn, $count_sql);
$total_students = mysqli_fetch_assoc($count_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Manage Students - Teacher Portal</title>
    <!-- Same styles as teacher_add_students.php -->
</head>
<body class="flex">
    <!-- Same sidebar as teacher_add_students.php -->

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Manage Students</h1>
                <p class="text-white/80">Manage students in your assigned classes</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="teacher_add_students.php" class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New Student</span>
                </a>
                <a href="teacher_dashboard.php" class="bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Students Table -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-blue-600 mr-3"></i>
                    Students in Your Classes
                </h3>
                <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm font-medium">
                    <?php echo $total_students . ' Students'; ?>
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

            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b-2 border-gray-200">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Name</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Roll No</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Class</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-user text-blue-400 mr-3"></i>
                                        <span class="text-gray-800 font-medium"><?php echo $student['name']; ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-sm font-medium">
                                        <?php echo $student['roll_number']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-gray-600"><?php echo $student['class_name']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form action="" method="post" class="inline">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="delete_student" 
                                                onclick="return confirm('Are you sure you want to delete <?php echo $student['name']; ?>? This action cannot be undone.');"
                                                class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200 flex items-center space-x-2">
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
                    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-slash text-blue-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Students Found</h3>
                    <p class="text-gray-500 mb-6">Get started by adding your first student.</p>
                    <a href="teacher_add_students.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-300 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Your First Student</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Same JavaScript as teacher_add_students.php -->
</body>
</html>