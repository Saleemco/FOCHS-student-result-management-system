<?php
// principal_header.php - Common header for principal pages
$principal_name = $_SESSION['teacher_name'] ?? $_SESSION['name'] ?? 'Principal';
?>

<!-- Simple Header -->
<div class="bg-green-600 text-white p-4 mb-6">
    <div class="container mx-auto flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold"><?php echo $page_title ?? 'Principal Portal'; ?></h1>
            <p class="text-green-100 text-sm">Welcome, <?php echo htmlspecialchars($principal_name); ?></p>
        </div>
        <div class="flex items-center space-x-4">
            <a href="principal_dashboard.php" class="hover:underline">
                <i class="fas fa-home mr-1"></i>Dashboard
            </a>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </div>
</div>

<!-- Quick Navigation -->
<div class="container mx-auto mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="principal_dashboard.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">
            <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
        </a>
        <a href="student.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">
            <i class="fas fa-users mr-1"></i>Students
        </a>
        <a href="teacher_manage_results.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">
            <i class="fas fa-chart-line mr-1"></i>Results
        </a>
        <a href="class_teacher_dashboard.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">
            <i class="fas fa-chalkboard mr-1"></i>Classes
        </a>
        <a href="teacher_dashboard.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">
            <i class="fas fa-chalkboard-teacher mr-1"></i>Teachers
        </a>
    </div>
</div>