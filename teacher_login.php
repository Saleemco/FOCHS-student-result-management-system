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
    
    // Handle multiple classes
    $classes_array = isset($_POST['classes']) ? $_POST['classes'] : [];
    $classes_string = implode(', ', array_map('mysqli_real_escape_string', array_fill(0, count($classes_array), $conn), $classes_array));
    
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Validation
    if (empty($subjects)) {
        $error_message = 'Please select at least one subject!';
    } elseif (empty($classes_array)) {
        $error_message = 'Please select at least one class!';
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
                          VALUES ('$name', '$email', '$phone', '$subject_string', '$classes_string', '$hashed_password', NOW())";
            
            if (mysqli_query($conn, $insert_sql)) {
                $success_message = 'Registration successful! You can now login.';
            } else {
                $error_message = 'Registration failed: ' . mysqli_error($conn);
            }
        }
    }
}

// Handle Teacher Login
if (isset($_POST['teacher_login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['teacher_email']);
    $password = $_POST['teacher_password'];

    $sql = "SELECT * FROM teachers WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        $db_password = $teacher['password'];
        $login_success = false;
        
        // Try multiple password verification methods
        // 1. First try PHP password_verify (for password_hash() output)
        if (password_verify($password, $db_password)) {
            $login_success = true;
        }
        // 2. Try MD5 hash
        elseif (md5($password) === $db_password) {
            $login_success = true;
        }
        // 3. Try plain text (for testing)
        elseif ($password === $db_password) {
            $login_success = true;
        }
        // 4. Try SHA1 (just in case)
        elseif (sha1($password) === $db_password) {
            $login_success = true;
        }
        
        if ($login_success) {
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_email'] = $teacher['email'];
            $_SESSION['teacher_subject'] = $teacher['subject'];
            $_SESSION['teacher_classes'] = $teacher['classes'];
            $_SESSION['user_type'] = 'teacher';
            
            header('Location: teacher_dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid email or password';
        }
    } else {
        $error_message = 'Teacher not found';
    }
}

// Handle Class Teacher Login
if (isset($_POST['class_teacher_login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['class_teacher_email']);
    $password = $_POST['class_teacher_password'];

    $sql = "SELECT t.*, ct.class_name as assigned_class 
            FROM teachers t 
            LEFT JOIN class_teachers ct ON t.id = ct.teacher_id 
            WHERE t.email = '$email'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        $db_password = $teacher['password'];
        $login_success = false;
        
        // Try multiple password verification methods
        // 1. First try PHP password_verify
        if (password_verify($password, $db_password)) {
            $login_success = true;
        }
        // 2. Try MD5 hash
        elseif (md5($password) === $db_password) {
            $login_success = true;
        }
        // 3. Try plain text
        elseif ($password === $db_password) {
            $login_success = true;
        }
        // 4. Try SHA1
        elseif (sha1($password) === $db_password) {
            $login_success = true;
        }
        
        if ($login_success) {
            if (!empty($teacher['assigned_class'])) {
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
                $_SESSION['teacher_email'] = $teacher['email'];
                $_SESSION['teacher_subject'] = $teacher['subject'];
                $_SESSION['teacher_classes'] = $teacher['classes'];
                $_SESSION['assigned_class'] = $teacher['assigned_class'];
                $_SESSION['user_type'] = 'class_teacher';
                
                header('Location: class_teacher_dashboard.php');
                exit();
            } else {
                $error_message = 'You are not assigned as a class teacher. Please use regular teacher login.';
            }
        } else {
            $error_message = 'Invalid email or password';
        }
    } else {
        $error_message = 'Teacher not found';
    }
}

// Handle Principal Login
if (isset($_POST['principal_login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['principal_email']);
    $password = $_POST['principal_password'];

    $sql = "SELECT * FROM teachers WHERE email = '$email' AND user_type = 'principal'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        $db_password = $teacher['password'];
        $login_success = false;
        
        // Try multiple password verification methods
        if (password_verify($password, $db_password)) {
            $login_success = true;
        } elseif (md5($password) === $db_password) {
            $login_success = true;
        } elseif ($password === $db_password) {
            $login_success = true;
        } elseif (sha1($password) === $db_password) {
            $login_success = true;
        }
        
        if ($login_success) {
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_email'] = $teacher['email'];
            $_SESSION['teacher_subject'] = $teacher['subject'];
            $_SESSION['teacher_classes'] = $teacher['classes'];
            $_SESSION['user_type'] = 'principal';
            
            header('Location: principal_dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid email or password for principal account';
        }
    } else {
        $error_message = 'Principal account not found or not authorized';
    }
}

// Determine which form to show
$show_form = isset($_GET['form']) ? $_GET['form'] : 'login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Teacher Portal - Login & Registration</title>
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
            min-height: 700px;
            display: flex;
            flex-direction: column;
        }
        .checkbox-grid {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
        }
        .class-teacher-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .subject-category {
            border-left: 4px solid #667eea;
            padding-left: 8px;
            margin-bottom: 8px;
        }
        .category-science { border-color: #10b981; }
        .category-arts { border-color: #f59e0b; }
        .category-commercial { border-color: #8b5cf6; }
        .category-core { border-color: #3b82f6; }
        .category-language { border-color: #ec4899; }
        .category-technical { border-color: #6366f1; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="login-container rounded-2xl p-8 w-full max-w-4xl">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-indigo-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chalkboard-teacher text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Teacher Portal</h1>
            <p class="text-gray-600">Sign in or create new account</p>
        </div>

        <!-- Simple Toggle Buttons -->
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?form=login" class="flex-1 text-center py-3 font-semibold <?php echo $show_form == 'login' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500'; ?>">
                Login
            </a>
            <a href="?form=register" class="flex-1 text-center py-3 font-semibold <?php echo $show_form == 'register' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500'; ?>">
                Register
            </a>
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

        <!-- LOGIN FORM -->
        <?php if ($show_form == 'login'): ?>
        <div class="space-y-6">
            <!-- Login Type Toggle -->
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button type="button" id="teacherTab" class="flex-1 py-2 px-4 rounded-md font-medium transition-all duration-200 bg-white shadow-sm text-indigo-600">
                    <i class="fas fa-user-graduate mr-2"></i>Teacher
                </button>
                <button type="button" id="classTeacherTab" class="flex-1 py-2 px-4 rounded-md font-medium transition-all duration-200 text-gray-600">
                    <i class="fas fa-user-tie mr-2"></i>Class Teacher
                </button>
                <button type="button" id="principalTab" class="flex-1 py-2 px-4 rounded-md font-medium transition-all duration-200 text-gray-600">
                    <i class="fas fa-crown mr-2"></i>Principal
                </button>
            </div>

            <!-- Teacher Login Form -->
            <form id="teacherLoginForm" method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-indigo-500 mr-2"></i>
                            Email Address
                        </label>
                        <input type="email" name="teacher_email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                               placeholder="Enter your email">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-indigo-500 mr-2"></i>
                            Password
                        </label>
                        <input type="password" name="teacher_password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                               placeholder="Enter your password">
                    </div>

                    <button type="submit" name="teacher_login"
                            class="w-full bg-indigo-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-indigo-600 transition duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Teacher Sign In</span>
                    </button>
                </div>
            </form>

            <!-- Class Teacher Login Form -->
            <form id="classTeacherLoginForm" method="post" class="hidden">
                <div class="class-teacher-badge rounded-lg p-3 mb-4 text-center">
                    <i class="fas fa-crown mr-2"></i>
                    <span class="font-semibold">Class Teacher Access</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-green-500 mr-2"></i>
                            Email Address
                        </label>
                        <input type="email" name="class_teacher_email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                               placeholder="Enter your email">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-green-500 mr-2"></i>
                            Password
                        </label>
                        <input type="password" name="class_teacher_password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                               placeholder="Enter your password">
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Class teachers can manage attendance, affective domain, and cognitive domain for their assigned class.
                        </p>
                    </div>

                    <button type="submit" name="class_teacher_login"
                            class="w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-user-tie"></i>
                        <span>Class Teacher Sign In</span>
                    </button>
                </div>
            </form>

            <!-- Principal Login Form -->
            <form id="principalLoginForm" method="post" class="hidden">
                <div class="bg-purple-500 text-white rounded-lg p-3 mb-4 text-center">
                    <i class="fas fa-crown mr-2"></i>
                    <span class="font-semibold">Principal Portal Access</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-purple-500 mr-2"></i>
                            Email Address
                        </label>
                        <input type="email" name="principal_email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200"
                               placeholder="principal@school.edu"
                               value="principal@school.edu">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-purple-500 mr-2"></i>
                            Password
                        </label>
                        <input type="password" name="principal_password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200"
                               placeholder="Enter password"
                               value="787898">
                    </div>

                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                        <p class="text-sm text-purple-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Principal access allows full school management and oversight.
                        </p>
                    </div>

                    <button type="submit" name="principal_login"
                            class="w-full bg-purple-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-purple-600 transition duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-user-tie"></i>
                        <span>Principal Sign In</span>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- REGISTRATION FORM -->
        <?php if ($show_form == 'register'): ?>
        <form method="post">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column: Personal Info -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">
                        <i class="fas fa-user-circle text-indigo-500 mr-2"></i>
                        Personal Information
                    </h3>
                    
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

                    <!-- Classes Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-users text-indigo-500 mr-2"></i>
                            Classes You Teach (Select multiple)
                        </label>
                        <div class="checkbox-grid">
                            <div class="space-y-3">
                                <!-- Junior Secondary -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-1 text-sm">Junior Secondary (JSS)</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-2">
                                        <?php
                                        $jss_classes = ['JSS 1', 'JSS 2', 'JSS 3'];
                                        foreach ($jss_classes as $class): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="classes[]" value="<?php echo $class; ?>" class="rounded text-indigo-500 focus:ring-indigo-500">
                                            <span class="text-xs text-gray-700"><?php echo $class; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Senior Secondary -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-1 text-sm">Senior Secondary (SSS)</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-2">
                                        <?php
                                        $sss_classes = ['SSS 1', 'SSS 2', 'SSS 3'];
                                        foreach ($sss_classes as $class): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="classes[]" value="<?php echo $class; ?>" class="rounded text-indigo-500 focus:ring-indigo-500">
                                            <span class="text-xs text-gray-700"><?php echo $class; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Select all classes you teach</p>
                    </div>
                </div>

                <!-- Right Column: Subjects -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">
                        <i class="fas fa-book-open text-indigo-500 mr-2"></i>
                        Subjects You Teach
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Select Subjects (Choose all you teach)
                        </label>
                        <div class="checkbox-grid" style="max-height: 300px;">
                            <div class="space-y-4">
                                
                                <!-- Core Subjects (All Levels) -->
                                <div>
                                    <h4 class="subject-category category-core font-semibold text-gray-700 mb-2">Core Subjects</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $core_subjects = [
                                            'English Language', 'English Literature', 'Mathematics', 
                                            'Basic Science', 'Basic Technology', 'Social Studies',
                                            'Civic Education', 'Physical & Health Education (PHE)',
                                            'Computer Studies / ICT'
                                        ];
                                        foreach ($core_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-blue-500 focus:ring-blue-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Science Subjects (SSS) -->
                                <div>
                                    <h4 class="subject-category category-science font-semibold text-gray-700 mb-2">Science Subjects (Senior)</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $science_subjects = [
                                            'Physics', 'Chemistry', 'Biology', 'Further Mathematics',
                                            'Agricultural Science', 'Health Education', 'Technical Drawing'
                                        ];
                                        foreach ($science_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-green-500 focus:ring-green-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Arts & Humanities (SSS) -->
                                <div>
                                    <h4 class="subject-category category-arts font-semibold text-gray-700 mb-2">Arts & Humanities (Senior)</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $arts_subjects = [
                                            'Government', 'History', 'Geography', 'Economics',
                                            'Christian Religious Studies (CRS)', 'Islamic Religious Studies (IRS)',
                                            'Music', 'Fine Arts', 'Theatre Arts'
                                        ];
                                        foreach ($arts_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-yellow-500 focus:ring-yellow-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Commercial Subjects (SSS) -->
                                <div>
                                    <h4 class="subject-category category-commercial font-semibold text-gray-700 mb-2">Commercial Subjects (Senior)</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $commercial_subjects = [
                                            'Commerce', 'Accounting', 'Business Studies',
                                            'Office Practice', 'Typewriting', 'Shorthand'
                                        ];
                                        foreach ($commercial_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-purple-500 focus:ring-purple-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Languages -->
                                <div>
                                    <h4 class="subject-category category-language font-semibold text-gray-700 mb-2">Languages</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $language_subjects = [
                                            'French', 'Yoruba', 'Hausa', 'Igbo', 'Arabic',
                                            'German', 'Chinese'
                                        ];
                                        foreach ($language_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-pink-500 focus:ring-pink-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Technical/Vocational -->
                                <div>
                                    <h4 class="subject-category category-technical font-semibold text-gray-700 mb-2">Technical & Vocational</h4>
                                    <div class="grid grid-cols-2 gap-1 pl-4">
                                        <?php
                                        $technical_subjects = [
                                            'Home Economics', 'Food & Nutrition', 'Clothing & Textiles',
                                            'Woodwork', 'Metalwork', 'Electronics',
                                            'Auto Mechanics', 'Building Construction', 'Fishery'
                                        ];
                                        foreach ($technical_subjects as $subject): ?>
                                        <label class="flex items-center space-x-2 p-1 hover:bg-gray-50 rounded">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $subject; ?>" class="rounded text-indigo-500 focus:ring-indigo-500">
                                            <span class="text-xs text-gray-700"><?php echo $subject; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Select all subjects you teach</p>
                    </div>

                    <!-- Passwords -->
                    <div class="space-y-4 mt-4">
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
                    </div>

                    <button type="submit" name="register"
                            class="w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Create Account</span>
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <!-- Footer Links -->
        <div class="mt-6 text-center">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="text-center">
                    <p class="text-gray-600">
                        Student/Parent? 
                        <a href="index.php" class="text-indigo-500 hover:text-indigo-700 font-medium">
                            Go to Student Portal
                        </a>
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-gray-600">
                        Admin? 
                        <a href="login.php" class="text-indigo-500 hover:text-indigo-700 font-medium">
                            Admin Login
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-gray-100 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-800 mb-2">Demo Credentials:</h4>
            <p class="text-xs text-gray-600 mb-1"><strong>Teacher:</strong> demo@school.com / teacher123</p>
            <p class="text-xs text-gray-600 mb-1"><strong>Principal:</strong> principal@school.edu / 787898</p>
            <p class="text-xs text-gray-600">Class Teacher: Must be assigned by admin</p>
        </div>
    </div>

    <script>
        // Tab switching for login types
        document.addEventListener('DOMContentLoaded', function() {
            const teacherTab = document.getElementById('teacherTab');
            const classTeacherTab = document.getElementById('classTeacherTab');
            const principalTab = document.getElementById('principalTab');
            const teacherForm = document.getElementById('teacherLoginForm');
            const classTeacherForm = document.getElementById('classTeacherLoginForm');
            const principalForm = document.getElementById('principalLoginForm');

            function resetTabs() {
                // Reset all tabs
                [teacherTab, classTeacherTab, principalTab].forEach(tab => {
                    tab.classList.remove('bg-white', 'shadow-sm', 'text-indigo-600');
                    tab.classList.add('text-gray-600');
                });
                
                // Hide all forms
                [teacherForm, classTeacherForm, principalForm].forEach(form => {
                    if (form) form.classList.add('hidden');
                });
            }

            function activateTab(tab, form) {
                resetTabs();
                tab.classList.add('bg-white', 'shadow-sm', 'text-indigo-600');
                tab.classList.remove('text-gray-600');
                if (form) form.classList.remove('hidden');
            }

            if (teacherTab) {
                teacherTab.addEventListener('click', function() {
                    activateTab(teacherTab, teacherForm);
                });

                classTeacherTab.addEventListener('click', function() {
                    activateTab(classTeacherTab, classTeacherForm);
                });

                principalTab.addEventListener('click', function() {
                    activateTab(principalTab, principalForm);
                });

                // Set default active tab
                activateTab(teacherTab, teacherForm);
            }
        });
    </script>
</body>
</html>