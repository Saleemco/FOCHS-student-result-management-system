
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Simple session check without database
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$login_session = $_SESSION['login_user'] ?? 'Admin';
?>