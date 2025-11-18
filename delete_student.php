<?php
include('init.php');
include('session.php');

if(!isset($_GET['id'])){
    die("Student ID missing");
}

$id = $_GET['id'];

// Delete student
$del_sql = "DELETE FROM students WHERE id='$id'";
if(mysqli_query($conn, $del_sql)){
    echo '<script>alert("Student deleted successfully"); window.location="manage_students.php";</script>';
} else {
    echo "Error deleting student: ".mysqli_error($conn);
}
?>
