<?php
function checkTeacherSession() {
    if (!isset($_SESSION['teacher_id'])) {
        header('Location: teacher_login.php');
        exit();
    }
    
    // Allow both teacher types
    $allowed_types = ['teacher', 'class_teacher'];
    if (!in_array($_SESSION['user_type'] ?? '', $allowed_types)) {
        header('Location: teacher_login.php');
        exit();
    }
    
    return $_SESSION['user_type'];
}

function redirectBasedOnUserType() {
    $user_type = $_SESSION['user_type'] ?? '';
    
    if ($user_type === 'class_teacher' && basename($_SERVER['PHP_SELF']) !== 'class_teacher_dashboard.php') {
        header('Location: class_teacher_dashboard.php');
        exit();
    }
    
    if ($user_type === 'teacher' && basename($_SERVER['PHP_SELF']) !== 'teacher_dashboard.php') {
        header('Location: teacher_dashboard.php');
        exit();
    }
}
?>