<?php
session_start();
include('init.php');

// Principal access check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'principal') {
    header('Location: login.php');
    exit();
}

// Get students data
$students_query = "SELECT * FROM students WHERE status = 'active' ORDER BY class_name, name";
$students_result = mysqli_query($conn, $students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Analytics - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php 
    $page_title = "Student Analytics";
    include('principal_header.php'); 
    ?>
    
    <div class="container mx-auto p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2">
                <!-- Student List -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">All Students</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-3 text-left">Name</th>
                                    <th class="p-3 text-left">Class</th>
                                    <th class="p-3 text-left">Roll No</th>
                                    <th class="p-3 text-left">Status</th>
                                    <th class="p-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                                    <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($student['class_name'] ?? 'Not assigned'); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <a href="find-result.php" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-chart-line mr-1"></i>Results
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-gray-500">
                                            <i class="fas fa-user-graduate text-3xl mb-3"></i>
                                            <p>No students found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Class Distribution -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Class Distribution</h2>
                    <?php
                    // Get class distribution
                    $class_query = "SELECT class_name, COUNT(*) as count FROM students WHERE class_name IS NOT NULL GROUP BY class_name ORDER BY class_name";
                    $class_result = mysqli_query($conn, $class_query);
                    ?>
                    <div class="space-y-3">
                        <?php if ($class_result && mysqli_num_rows($class_result) > 0): ?>
                            <?php while ($class = mysqli_fetch_assoc($class_result)): ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm"><?php echo htmlspecialchars($class['class_name']); ?></span>
                                    <span class="text-sm font-medium"><?php echo $class['count']; ?> students</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo min(100, ($class['count'] * 5)); ?>%"></div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No class data available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">Student Actions</h2>
                    <div class="space-y-3">
                        <a href="teacher_manage_students.php" class="block p-3 bg-blue-50 hover:bg-blue-100 rounded">
                            <i class="fas fa-edit text-blue-500 mr-2"></i>
                            Manage Students
                        </a>
                        <a href="teacher_add_students.php" class="block p-3 bg-green-50 hover:bg-green-100 rounded">
                            <i class="fas fa-user-plus text-green-500 mr-2"></i>
                            Add New Student
                        </a>
                        <a href="student.php" class="block p-3 bg-purple-50 hover:bg-purple-100 rounded">
                            <i class="fas fa-list text-purple-500 mr-2"></i>
                            View All Students
                        </a>
                        <a href="edit_student.php" class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded">
                            <i class="fas fa-pen text-yellow-500 mr-2"></i>
                            Edit Student
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>