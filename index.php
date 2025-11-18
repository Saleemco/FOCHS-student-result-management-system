<?php
// Simple version without database connection first
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center space-x-4 mb-4">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-purple-600">SRMS</span>
                </div>
                <h1 class="text-4xl font-bold text-white">Student Result Management System</h1>
            </div>
            <p class="text-white/80 text-lg">Academic Year 2025</p>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl p-8 shadow-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                Welcome to SRMS
            </h2>
            
            <!-- Student Result Form -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-search text-blue-500 mr-2"></i>
                    Student Result Portal
                </h3>
                <form action="find_result.php" method="post" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Student Name *</label>
                            <input type="text" name="student_name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter full name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Roll Number *</label>
                            <input type="text" name="roll_no" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter roll number">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Class *</label>
                            <select name="class_name" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="" selected disabled>Select Class</option>
                                <option value="Class 1">Class 1</option>
                                <option value="Class 2">Class 2</option>
                                <option value="Class 3">Class 3</option>
                                <option value="Class 4">Class 4</option>
                                <option value="Class 5">Class 5</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-600 transition duration-200">
                        View Result
                    </button>
                </form>
            </div>

            <!-- Login Options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Admin Login -->
                <a href="login.php" class="bg-purple-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-purple-600 transition duration-200 text-center">
                    Admin Login
                </a>

                <!-- Teacher Login -->
                <a href="teacher_login.php" class="bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition duration-200 text-center">
                    Teacher Login
                </a>
            </div>

            <!-- File Status -->
            <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Quick Check:</h4>
                <p class="text-gray-600 text-sm">
                    If you see this page, your server is working! 
                    <?php
                    // Check if basic files exist
                    $basic_files = ['login.php', 'find_result.php', 'teacher_login.php'];
                    $missing_files = [];
                    
                    foreach ($basic_files as $file) {
                        if (!file_exists($file)) {
                            $missing_files[] = $file;
                        }
                    }
                    
                    if (empty($missing_files)) {
                        echo "<span style='color: green;'>All basic files are present.</span>";
                    } else {
                        echo "<span style='color: red;'>Missing: " . implode(', ', $missing_files) . "</span>";
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Add Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>

    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], select[required]');
            let valid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add('border-red-500');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>