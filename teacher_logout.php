<?php
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

// If confirmation is received, proceed with logout
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Unset all teacher session variables
    unset($_SESSION['teacher_id']);
    unset($_SESSION['teacher_name']);
    unset($_SESSION['teacher_email']);
    unset($_SESSION['teacher_subject']);
    unset($_SESSION['teacher_classes']);
    
    // Destroy the session
    session_destroy();
    
    // Redirect to teacher login page with success message
    header('Location: teacher_login.php?logout=success');
    exit();
} else {
    // Show confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <title>Confirm Logout - Teacher Portal</title>
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full">
            <div class="text-center">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-sign-out-alt text-red-500 text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Confirm Logout</h2>
                <p class="text-gray-600 mb-2">Are you sure you want to logout from your teacher account?</p>
                <p class="text-sm text-gray-500 mb-6"><?php echo $_SESSION['teacher_name']; ?> - <?php echo $_SESSION['teacher_subject']; ?></p>
                
                <div class="flex space-x-4">
                    <a href="teacher_dashboard.php" 
                       class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-600 transition duration-200 text-center flex items-center justify-center space-x-2">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </a>
                    <a href="teacher_logout.php?confirm=yes" 
                       class="flex-1 bg-red-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-600 transition duration-200 text-center flex items-center justify-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>