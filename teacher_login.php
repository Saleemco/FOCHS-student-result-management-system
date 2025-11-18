<?php
session_start();
include('init.php');

$error_message = '';
$success_message = '';

// Handle Registration
if (isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // Handle multiple subjects
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    $subject_string = implode(', ', array_map('mysqli_real_escape_string', array_fill(0, count($subjects), $conn), $subjects));
    
    $classes = mysqli_real_escape_string($conn, $_POST['classes']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Validation
    if (empty($subjects)) {
        $error_message = 'Please select at least one subject!';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match!';
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM teachers WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_message = 'Email already registered!';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new teacher
            $insert_sql = "INSERT INTO teachers (name, email, phone, subject, classes, password, created_at) 
                          VALUES ('$name', '$email', '$phone', '$subject_string', '$classes', '$hashed_password', NOW())";
            
            if (mysqli_query($conn, $insert_sql)) {
                $success_message = 'Registration successful! You can now login.';
            } else {
                $error_message = 'Registration failed: ' . mysqli_error($conn);
            }
        }
    }
}

// Handle Login
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM teachers WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        
        // Verify password
        if (password_verify($password, $teacher['password'])) {
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_email'] = $teacher['email'];
            $_SESSION['teacher_subject'] = $teacher['subject'];
            $_SESSION['teacher_classes'] = $teacher['classes'];
            
            header('Location: teacher_dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid password';
        }
    } else {
        $error_message = 'Teacher not found';
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
    <title>Teacher Login & Registration</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-toggle {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        .form-toggle button {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .form-toggle button.active {
            color: #4f46e5;
            border-bottom: 3px solid #4f46e5;
        }
        .form-content {
            display: none;
        }
        .form-content.active {
            display: block;
        }
        .checkbox-grid {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
        }
        .checkbox-grid::-webkit-scrollbar {
            width: 6px;
        }
        .checkbox-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .checkbox-grid::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        .checkbox-grid::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="login-container rounded-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-indigo-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chalkboard-teacher text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Teacher Portal</h1>
            <p class="text-gray-600">Sign in or create new account</p>
        </div>

        <!-- Form Toggle -->
        <div class="form-toggle">
            <button class="active" onclick="showForm('login')">Login</button>
            <button onclick="showForm('register')">Register</button>
        </div>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" class="form-content active" method="post">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope text-indigo-500 mr-2"></i>
                        Email Address
                    </label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your email">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-indigo-500 mr-2"></i>
                        Password
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your password">
                </div>

                <button type="submit" name="login"
                        class="w-full bg-indigo-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-indigo-600 transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </div>
        </form>

        <!-- Registration Form -->
        <form id="registerForm" class="form-content" method="post">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user text-indigo-500 mr-2"></i>
                        Full Name
                    </label>
                    <input type="text" name="name" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your full name">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope text-indigo-500 mr-2"></i>
                        Email Address
                    </label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your email">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-phone text-indigo-500 mr-2"></i>
                        Phone Number
                    </label>
                    <input type="tel" name="phone" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your phone number">
                </div>

                <!-- <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book text-indigo-500 mr-2"></i>
                        Subjects (Select multiple)
                    </label>
                    <div class="checkbox-grid">
                        <div class="grid grid-cols-1 gap-2">
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Mathematics" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Mathematics</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="English Language" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">English Language</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="English Literature" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">English Literature</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Basic Science" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Basic Science</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Basic Technology" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Basic Technology</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Social Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Social Studies</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Civic Education" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Civic Education</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Computer Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Computer Studies</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Physical and Health Education" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Physical & Health Education</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Home Economics" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Home Economics</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Business Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Business Studies</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="French" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">French</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Yoruba" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Yoruba</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Igbo" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Igbo</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Hausa" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Hausa</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Christian Religious Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Christian Religious Studies</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Islamic Religious Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Islamic Religious Studies</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Cultural and Creative Arts" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Cultural & Creative Arts</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Agricultural Science" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Agricultural Science</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Music" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Music</span>
                            </label>
                            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="subjects[]" value="Fine Arts" class="rounded text-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Fine Arts</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Select all subjects you teach</p>
                </div> -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-book text-indigo-500 mr-2"></i>
        Subjects (Select multiple)
    </label>
    <div class="checkbox-grid">
        <div class="grid grid-cols-1 gap-2">
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Mathematics" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Mathematics</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="English Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">English Studies</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Basic Science" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Basic Science</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Basic Technology" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Basic Technology</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Social Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Social Studies</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Civic Education" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Civic Education</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Computer Studies / ICT" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Computer Studies / ICT</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Physical & Health Education (PHE)" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Physical & Health Education (PHE)</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Agricultural Science" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Agricultural Science</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Yoruba" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Yoruba</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Arabic" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Arabic</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Islamic Religious Studies (IRS)" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Islamic Religious Studies (IRS)</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Cultural & Creative Arts (CCA)" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Cultural & Creative Arts (CCA)</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Home Economics" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Home Economics</span>
            </label>
            <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" name="subjects[]" value="Business Studies" class="rounded text-indigo-500 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Business Studies</span>
            </label>
        </div>
    </div>
    <p class="text-xs text-gray-500 mt-1">Select all subjects you teach</p>
</div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-users text-indigo-500 mr-2"></i>
                        Classes
                    </label>
                    <input type="text" name="classes" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="e.g., JSS 1, JSS 2, JSS 3">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-indigo-500 mr-2"></i>
                        Password
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Create password">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-indigo-500 mr-2"></i>
                        Confirm Password
                    </label>
                    <input type="password" name="confirm_password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Confirm password">
                </div>

                <button type="submit" name="register"
                        class="w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Account</span>
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Student/Parent? 
                <a href="index.php" class="text-indigo-500 hover:text-indigo-700 font-medium">
                    Go to Student Portal
                </a>
            </p>
            <p class="text-gray-600 mt-2">
                Admin? 
                <a href="login.php" class="text-indigo-500 hover:text-indigo-700 font-medium">
                    Admin Login
                </a>
            </p>
        </div>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-gray-100 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-800 mb-2">Demo Credentials:</h4>
            <p class="text-xs text-gray-600">Email: john.smith@school.com</p>
            <p class="text-xs text-gray-600">Password: teacher123</p>
        </div>
    </div>

    <script>
        function showForm(formType) {
            // Update toggle buttons
            document.querySelectorAll('.form-toggle button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Show/hide forms
            document.getElementById('loginForm').classList.remove('active');
            document.getElementById('registerForm').classList.remove('active');
            document.getElementById(formType + 'Form').classList.add('active');
        }

        // Add some interactivity to checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.parentElement;
                if (this.checked) {
                    label.classList.add('bg-indigo-50');
                } else {
                    label.classList.remove('bg-indigo-50');
                }
            });
        });
    </script>
</body>
</html>