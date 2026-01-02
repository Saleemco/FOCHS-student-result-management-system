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
    <title>Academic Reports - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include('principal_header.php'); ?>
    
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Academic Reports</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold mb-4">Quick Reports</h3>
                <div class="space-y-3">
                    <a href="teacher_manage_results.php" class="block p-3 bg-blue-50 hover:bg-blue-100 rounded">
                        <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                        View All Results
                    </a>
                    <a href="find-result.php" class="block p-3 bg-green-50 hover:bg-green-100 rounded">
                        <i class="fas fa-search text-green-500 mr-2"></i>
                        Find Student Results
                    </a>
                    <a href="report_card_generator.php" class="block p-3 bg-purple-50 hover:bg-purple-100 rounded">
                        <i class="fas fa-file-pdf text-purple-500 mr-2"></i>
                        Generate Report Cards
                    </a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold mb-4">Term Reports</h3>
                <div class="space-y-3">
                    <a href="#" class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded">
                        <i class="fas fa-file-alt text-yellow-500 mr-2"></i>
                        First Term Report
                    </a>
                    <a href="#" class="block p-3 bg-orange-50 hover:bg-orange-100 rounded">
                        <i class="fas fa-file-alt text-orange-500 mr-2"></i>
                        Second Term Report
                    </a>
                    <a href="#" class="block p-3 bg-red-50 hover:bg-red-100 rounded">
                        <i class="fas fa-file-alt text-red-500 mr-2"></i>
                        Third Term Report
                    </a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold mb-4">Class Performance</h3>
                <div class="space-y-3">
                    <a href="class_teacher_dashboard.php" class="block p-3 bg-indigo-50 hover:bg-indigo-100 rounded">
                        <i class="fas fa-chalkboard text-indigo-500 mr-2"></i>
                        Class Dashboards
                    </a>
                    <a href="#" class="block p-3 bg-teal-50 hover:bg-teal-100 rounded">
                        <i class="fas fa-chart-bar text-teal-500 mr-2"></i>
                        Performance Charts
                    </a>
                    <a href="#" class="block p-3 bg-pink-50 hover:bg-pink-100 rounded">
                        <i class="fas fa-graduation-cap text-pink-500 mr-2"></i>
                        Graduation Reports
                    </a>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Available Report Types</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="p-3 text-left">Report Type</th>
                            <th class="p-3 text-left">Description</th>
                            <th class="p-3 text-left">Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="p-3">Student Results</td>
                            <td class="p-3">Individual student performance</td>
                            <td class="p-3">
                                <a href="teacher_manage_results.php" class="text-blue-600">View</a>
                            </td>
                        </tr>
                        <tr class="border-b">
                            <td class="p-3">Class Reports</td>
                            <td class="p-3">Class-wise performance analysis</td>
                            <td class="p-3">
                                <a href="class_teacher_dashboard.php" class="text-blue-600">View</a>
                            </td>
                        </tr>
                        <tr class="border-b">
                            <td class="p-3">Report Cards</td>
                            <td class="p-3">Printable student report cards</td>
                            <td class="p-3">
                                <a href="report_card_generator.php" class="text-blue-600">Generate</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="p-3">Teacher Performance</td>
                            <td class="p-3">Teacher assessment reports</td>
                            <td class="p-3">
                                <a href="principal_dashboard.php" class="text-blue-600">View in Dashboard</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>