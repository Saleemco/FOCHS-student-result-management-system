<?php
session_start();
include('init.php');

// Principal access check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'principal') {
    header('Location: login.php');
    exit();
}

// Get all teachers
$teachers_query = "SELECT * FROM teachers ORDER BY name";
$teachers_result = mysqli_query($conn, $teachers_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include('principal_header.php'); ?>
    
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Staff Management</h1>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <?php
            $total_teachers = mysqli_num_rows($teachers_result);
            $active_teachers = 0;
            $class_teachers = 0;
            $inactive_teachers = 0;
            
            if ($teachers_result) {
                mysqli_data_seek($teachers_result, 0); // Reset pointer
                while ($teacher = mysqli_fetch_assoc($teachers_result)) {
                    if ($teacher['status'] == 'active') $active_teachers++;
                    else $inactive_teachers++;
                    if ($teacher['user_type'] == 'class_teacher') $class_teachers++;
                }
                mysqli_data_seek($teachers_result, 0); // Reset again for later use
            }
            ?>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg mr-3">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Staff</p>
                        <p class="text-2xl font-bold"><?php echo $total_teachers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg mr-3">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Active</p>
                        <p class="text-2xl font-bold"><?php echo $active_teachers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-lg mr-3">
                        <i class="fas fa-user-tie text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Class Teachers</p>
                        <p class="text-2xl font-bold"><?php echo $class_teachers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg mr-3">
                        <i class="fas fa-user-plus text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Add New</p>
                        <p class="text-xl font-bold">Staff</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Teachers Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-bold">All Teaching Staff</h2>
                <a href="login.php?form=register" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-user-plus mr-2"></i>Add New Teacher
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left">Name</th>
                            <th class="p-3 text-left">Email</th>
                            <th class="p-3 text-left">Subjects</th>
                            <th class="p-3 text-left">Classes</th>
                            <th class="p-3 text-left">Role</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($teachers_result && mysqli_num_rows($teachers_result) > 0): ?>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3"><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($teacher['subject'] ?? 'Not set'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($teacher['classes'] ?? 'Not set'); ?></td>
                                <td class="p-3">
                                    <?php 
                                    $role_badge = 'bg-blue-100 text-blue-800';
                                    if ($teacher['user_type'] == 'principal') $role_badge = 'bg-green-100 text-green-800';
                                    elseif ($teacher['user_type'] == 'class_teacher') $role_badge = 'bg-purple-100 text-purple-800';
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs <?php echo $role_badge; ?>">
                                        <?php echo ucfirst($teacher['user_type'] ?? 'teacher'); ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php 
                                    $status_badge = ($teacher['status'] == 'active') 
                                        ? 'bg-green-100 text-green-800' 
                                        : 'bg-red-100 text-red-800';
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs <?php echo $status_badge; ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex space-x-2">
                                        <a href="teacher_dashboard.php" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-500">
                                    <i class="fas fa-users text-3xl mb-3"></i>
                                    <p>No teachers found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>