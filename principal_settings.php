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
    <title>Settings - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php 
    $page_title = "System Settings";
    include('principal_header.php'); 
    ?>
    
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">System Settings</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2">
                <!-- Academic Settings -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Academic Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Current Academic Term</label>
                            <select class="w-full p-2 border rounded">
                                <option>First Term</option>
                                <option>Second Term</option>
                                <option>Third Term</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Current Academic Session</label>
                            <input type="text" class="w-full p-2 border rounded" value="2024/2025">
                        </div>
                        
                        <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                            Save Academic Settings
                        </button>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">System Configuration</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2" checked>
                                <span>Enable Student Registration</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2" checked>
                                <span>Allow Teacher Results Entry</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2">
                                <span>Require Admin Approval for Results</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2" checked>
                                <span>Enable Report Card Generation</span>
                            </label>
                        </div>
                        
                        <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                            Save System Settings
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- System Tools -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">System Tools</h2>
                    <div class="space-y-3">
                        <a href="check_database.php" class="block p-3 bg-blue-50 hover:bg-blue-100 rounded">
                            <i class="fas fa-database text-blue-500 mr-2"></i>
                            Database Check
                        </a>
                        <a href="debug_database.php" class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded">
                            <i class="fas fa-bug text-yellow-500 mr-2"></i>
                            Debug System
                        </a>
                        <a href="migrate_results.php" class="block p-3 bg-green-50 hover:bg-green-100 rounded">
                            <i class="fas fa-exchange-alt text-green-500 mr-2"></i>
                            Migrate Results
                        </a>
                        <a href="create_domain_tables.php" class="block p-3 bg-purple-50 hover:bg-purple-100 rounded">
                            <i class="fas fa-table text-purple-500 mr-2"></i>
                            Create Domain Tables
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">Quick Access</h2>
                    <div class="space-y-2">
                        <a href="principal_dashboard.php" class="block p-2 hover:bg-gray-100 rounded">
                            <i class="fas fa-tachometer-alt text-gray-500 mr-2"></i>
                            Dashboard
                        </a>
                        <a href="index.php" class="block p-2 hover:bg-gray-100 rounded">
                            <i class="fas fa-home text-gray-500 mr-2"></i>
                            Main Portal
                        </a>
                        <a href="logout.php" class="block p-2 hover:bg-red-50 text-red-600 rounded">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Logout System
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>