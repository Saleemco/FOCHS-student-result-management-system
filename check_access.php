<?php
// check_access.php - Centralized access control

function checkUserAccess($required_types = []) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    if (empty($required_types)) {
        // Default: allow teachers, class teachers, and principals
        $required_types = ['teacher', 'class_teacher', 'principal'];
    }
    
    $user_type = $_SESSION['user_type'] ?? '';
    
    if (!in_array($user_type, $required_types)) {
        // Check if principal has override access
        if ($user_type == 'principal') {
            return true; // Principal can access everything
        }
        
        header('Location: unauthorized.php');
        exit();
    }
    
    return true;
}

// Quick access check functions
function isPrincipal() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'principal';
}

function isClassTeacher() {
    return isset($_SESSION['user_type']) && ($_SESSION['user_type'] == 'class_teacher' || $_SESSION['user_type'] == 'principal');
}

function isTeacher() {
    return isset($_SESSION['user_type']) && ($_SESSION['user_type'] == 'teacher' || $_SESSION['user_type'] == 'class_teacher' || $_SESSION['user_type'] == 'principal');
}

function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getUserName() {
    return $_SESSION['teacher_name'] ?? $_SESSION['name'] ?? 'User';
}

function getUserEmail() {
    return $_SESSION['teacher_email'] ?? $_SESSION['email'] ?? '';
}
?>