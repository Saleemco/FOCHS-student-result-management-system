<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['userid'];
    $password = $_POST['password'];
    
    // Simple authentication - NO DATABASE NEEDED
    if ($username === 'admin' && $password === '123') {
        $_SESSION['login_user'] = $username;
        $_SESSION['admin_logged_in'] = true;
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=Invalid username or password. Use: admin / 123");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>