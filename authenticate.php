<?php
session_start();
include('init.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['userid']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    
    // Select database
    mysqli_select_db($conn, 'srms');
    
    // Check admin credentials
    $sql = "SELECT userid FROM admin_login WHERE userid='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) == 1) {
        $_SESSION['login_user'] = $username; // This matches your session.php
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=invalid_credentials");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>