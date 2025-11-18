<?php
include('init.php');
include('session.php');

if(!isset($_GET['id'])){
    die("Student ID missing");
}

$id = $_GET['id'];

// Fetch student
$sql = "SELECT * FROM students WHERE id='$id'";
$res = mysqli_query($conn, $sql);
$student = mysqli_fetch_assoc($res);

// Fetch classes for dropdown
$classes_sql = "SELECT name FROM class";
$classes_res = mysqli_query($conn, $classes_sql);

// Update student
if(isset($_POST['name'], $_POST['rno'], $_POST['class_name'])){
    $name = trim($_POST['name']);
    $rno = trim($_POST['rno']);
    $class_name = $_POST['class_name'];

    if(!empty($name) && !empty($rno)){
        $update_sql = "UPDATE students SET name='$name', rno='$rno', class_name='$class_name' WHERE id='$id'";
        if(mysqli_query($conn, $update_sql)){
            echo '<script>alert("Student updated successfully"); window.location="manage_students.php";</script>';
            exit();
        } else {
            echo "Error updating student: ".mysqli_error($conn);
        }
    } else {
        echo "<p style='color:red;'>Please fill all fields</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Student</title>
<link rel="stylesheet" href="css/form.css">
</head>
<body>
<div class="main">
    <h2>Edit Student</h2>
    <form action="" method="post">
        <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" placeholder="Student Name" required>
        <input type="text" name="rno" value="<?php echo htmlspecialchars($student['rno']); ?>" placeholder="Roll Number" required>
        <select name="class_name" required>
            <?php
            while($class = mysqli_fetch_assoc($classes_res)){
                $selected = ($student['class_name'] == $class['name']) ? "selected" : "";
                echo "<option value='".$class['name']."' $selected>".$class['name']."</option>";
            }
            ?>
        </select>
        <input type="submit" value="Update">
    </form>
</div>
</body>
</html>
