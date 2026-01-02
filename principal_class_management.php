<?php
session_start();
include('init.php');

// Principal access check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'principal') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php 
    $page_title = "Class Management";
    include('principal_header.php'); 
    ?>
    
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Class Management</h1>
        
        <!-- Class Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php
            $classes = ['JSS 1', 'JSS 2', 'JSS 3', 'SSS 1', 'SSS 2', 'SSS 3'];
            $colors = ['blue', 'green', 'purple', 'orange', 'red', 'indigo'];
            
            foreach ($classes as $index => $class) {
                $color = $colors[$index % count($colors)];
                $bg_color = "bg-{$color}-50";
                $text_color = "text-{$color}-600";
                $border_color = "border-{$color}-200";
            ?>
            <div class="bg-white rounded-lg shadow border <?php echo $border_color; ?> p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="<?php echo $bg_color; ?> p-3 rounded-lg mr-3">
                            <i class="fas fa-chalkboard <?php echo $text_color; ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg"><?php echo $class; ?></h3>
                            <p class="text-gray-500 text-sm">Class Overview</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <a href="class_teacher_dashboard.php?class=<?php echo urlencode($class); ?>" 
                       class="block p-2 bg-gray-50 hover:bg-gray-100 rounded">
                        <i class="fas fa-eye text-gray-500 mr-2"></i>
                        View Class Dashboard
                    </a>
                    <a href="student.php?class=<?php echo urlencode($class); ?>" 
                       class="block p-2 bg-gray-50 hover:bg-gray-100 rounded">
                        <i class="fas fa-users text-gray-500 mr-2"></i>
                        View Students
                    </a>
                    <a href="class_teacher_attendance.php" 
                       class="block p-2 bg-gray-50 hover:bg-gray-100 rounded">
                        <i class="fas fa-clipboard-check text-gray-500 mr-2"></i>
                        Attendance
                    </a>
                </div>
            </div>
            <?php } ?>
        </div>
        
        <!-- Class Teachers -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Class Teacher Assignments</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left">Class</th>
                            <th class="p-3 text-left">Class Teacher</th>
                            <th class="p-3 text-left">Email</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // This is a placeholder - you'll need to implement actual class teacher assignments
                        $class_teachers = [
                            ['class' => 'JSS 1', 'teacher' => 'Mr. Johnson Adebayo', 'email' => 'johnson.adebayo@school.edu', 'status' => 'active'],
                            ['class' => 'JSS 2', 'teacher' => 'Mrs. Grace Okonkwo', 'email' => 'grace.okonkwo@school.edu', 'status' => 'active'],
                            ['class' => 'JSS 3', 'teacher' => 'Mr. Samuel Chukwu', 'email' => 'samuel.chukwu@school.edu', 'status' => 'active'],
                            ['class' => 'SSS 1', 'teacher' => 'Mrs. Fatima Bello', 'email' => 'fatima.bello@school.edu', 'status' => 'active'],
                            ['class' => 'SSS 2', 'teacher' => 'Mr. David Okafor', 'email' => 'david.okafor@school.edu', 'status' => 'active'],
                            ['class' => 'SSS 3', 'teacher' => 'Mrs. Cynthia Nwosu', 'email' => 'cynthia.nwosu@school.edu', 'status' => 'active'],
                        ];
                        
                        foreach ($class_teachers as $ct): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium"><?php echo $ct['class']; ?></td>
                            <td class="p-3"><?php echo $ct['teacher']; ?></td>
                            <td class="p-3"><?php echo $ct['email']; ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">
                                    <?php echo ucfirst($ct['status']); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <a href="class_teacher_dashboard.php" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>