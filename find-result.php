<?php
include('init.php');
// Note: session.php is NOT included as this is a public access page.

$result_found = false;
$error_message = '';
$result_data = null;

// Ensure all four fields are submitted for authentication
if (isset($_POST['student_name'], $_POST['roll_no'], $_POST['class_name'], $_POST['access_code'])) {
    
    // 1. Sanitize user input for security
    $name = mysqli_real_escape_string($conn, $_POST['student_name']);
    $rno = mysqli_real_escape_string($conn, $_POST['roll_no']);
    $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);
    $access_code = mysqli_real_escape_string($conn, $_POST['access_code']);

    // --- PARENT LOGIN AUTHENTICATION (Checks students table) ---
    $auth_sql = "SELECT s.name FROM `students` s 
                 WHERE s.`name`='$name' AND s.`rno`='$rno' 
                 AND s.`class_name`='$class_name' AND s.`access_code`='$access_code'";
    
    $auth_result = mysqli_query($conn, $auth_sql);

    if (mysqli_num_rows($auth_result) == 0) {
        // Authentication failed
        $error_message = 'Login failed. Please check the Student Name, Roll Number, Class, and Access Code.';
    } else {
        // Credentials correct, now fetch the result from the result table
        $result_sql = "SELECT * FROM `result` WHERE `name`='$name' AND `rno`='$rno' AND `class`='$class_name'";
        $query_result = mysqli_query($conn, $result_sql);

        if (!$query_result) {
            $error_message = 'Database Error: Could not retrieve result.';
        } else if (mysqli_num_rows($query_result) > 0) {
            $result_data = mysqli_fetch_assoc($query_result);
            $result_found = true;
        } else {
            $error_message = 'Result not yet published for this student.';
        }
    }
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
    <title>Student Result Portal</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center space-x-4 mb-4">
                <img src="./images/logo1.png" alt="Logo" class="w-16 h-16">
                <h1 class="text-4xl font-bold text-white">Student Result Management System</h1>
            </div>
            <p class="text-white/80 text-lg">View Your Academic Results</p>
        </div>

        <!-- Login Form -->
        <div class="card rounded-2xl p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-user-graduate text-blue-500 mr-3"></i>
                Student Login
            </h2>
            
            <!-- Fix: Form submits to the same file -->
            <form action="find_result.php" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Student Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-blue-500 mr-2"></i>
                            Student Full Name
                        </label>
                        <input type="text" name="student_name" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               placeholder="Enter full name">
                    </div>

                    <!-- Roll Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card text-green-500 mr-2"></i>
                            Roll Number
                        </label>
                        <input type="text" name="roll_no" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                               placeholder="Enter roll number">
                    </div>

                    <!-- Class Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-chalkboard-teacher text-purple-500 mr-2"></i>
                            Class
                        </label>
                        <select name="class_name" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200">
                            <option value="" selected disabled>Select Class</option>
                            <?php
                                $class_result = mysqli_query($conn, "SELECT `name` FROM `class`");
                                while($row = mysqli_fetch_array($class_result)) {
                                    $display = $row['name'];
                                    echo '<option value="'.$display.'">'.$display.'</option>';
                                }
                            ?>
                        </select>
                    </div>

                    <!-- Access Code -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-red-500 mr-2"></i>
                            Access Code
                        </label>
                        <input type="password" name="access_code" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                               placeholder="Enter access code">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full mt-6 bg-blue-500 text-white py-4 px-6 rounded-lg font-semibold hover:bg-blue-600 transition duration-200 flex items-center justify-center space-x-3">
                    <i class="fas fa-search"></i>
                    <span class="text-lg">View Result</span>
                </button>
            </form>

            <!-- Back to Home -->
            <div class="text-center mt-6">
                <a href="index.php" class="text-blue-500 hover:text-blue-700 transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Home Page</span>
                </a>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="card rounded-2xl p-6 mb-6 border-l-4 border-red-500 bg-red-50">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Authentication Failed</h3>
                        <p class="text-red-600"><?php echo $error_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Result Display -->
        <?php if ($result_found && $result_data): ?>
            <div class="card rounded-2xl p-8">
                <!-- Result Header -->
                <div class="result-card rounded-xl p-6 mb-8 text-center">
                    <h2 class="text-3xl font-bold mb-2">ACADEMIC RESULT</h2>
                    <p class="text-xl opacity-90"><?php echo $result_data['name']; ?> - <?php echo $result_data['class']; ?></p>
                    <p class="opacity-80">Roll No: <?php echo $result_data['rno']; ?></p>
                </div>

                <!-- Marks Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-6 py-4 font-semibold">Subject</th>
                                <th class="px-6 py-4 font-semibold">Marks Obtained</th>
                                <th class="px-6 py-4 font-semibold">Out of 100</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 font-medium">Subject 1</td>
                                <td class="px-6 py-4"><?php echo $result_data['p1']; ?></td>
                                <td class="px-6 py-4 text-gray-500">100</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 font-medium">Subject 2</td>
                                <td class="px-6 py-4"><?php echo $result_data['p2']; ?></td>
                                <td class="px-6 py-4 text-gray-500">100</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 font-medium">Subject 3</td>
                                <td class="px-6 py-4"><?php echo $result_data['p3']; ?></td>
                                <td class="px-6 py-4 text-gray-500">100</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 font-medium">Subject 4</td>
                                <td class="px-6 py-4"><?php echo $result_data['p4']; ?></td>
                                <td class="px-6 py-4 text-gray-500">100</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 font-medium">Subject 5</td>
                                <td class="px-6 py-4"><?php echo $result_data['p5']; ?></td>
                                <td class="px-6 py-4 text-gray-500">100</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-800 text-white font-semibold">
                            <tr>
                                <td class="px-6 py-4">Total Marks</td>
                                <td class="px-6 py-4"><?php echo $result_data['marks']; ?></td>
                                <td class="px-6 py-4">500</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4">Percentage</td>
                                <td colspan="2" class="px-6 py-4 text-lg">
                                    <?php echo $result_data['percentage']; ?>%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Print Button -->
                <div class="text-center mt-8">
                    <button onclick="window.print()" 
                            class="bg-green-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center space-x-2 inline-flex">
                        <i class="fas fa-print"></i>
                        <span>Print Result</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], select[required]');
            let valid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
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