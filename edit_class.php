<?php
include('init.php');
include('session.php');

// Check if class ID is provided
if (!isset($_GET['id'])) {
    die("Class ID missing");
}

$id = $_GET['id'];

// Fetch class details
$sql = "SELECT * FROM `class` WHERE `id`='$id'";
$res = mysqli_query($conn, $sql);

if (!$res) {
    die("Error fetching class: " . mysqli_error($conn));
}

$class = mysqli_fetch_assoc($res);

if (!$class) {
    die("Class not found");
}

// Update class if form submitted
if (isset($_POST['class_name'])) {
    $name = trim($_POST['class_name']);

    if (empty($name)) {
        echo '<p style="color:red;">Please enter a class name</p>';
    } else {
        // Check if class name already exists (excluding current class)
        $check_sql = "SELECT * FROM `class` WHERE `name`='$name' AND `id` != '$id'";
        $check_res = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_res) > 0) {
            echo '<p style="color:red;">Class name already exists. Choose a different name.</p>';
        } else {
            // Safe to update
            $update_sql = "UPDATE `class` SET `name`='$name' WHERE `id`='$id'";

            if (mysqli_query($conn, $update_sql)) {
                echo '<script>alert("Class updated successfully"); window.location="manage_classes.php";</script>';
                exit();
            } else {
                echo '<p style="color:red;">Error updating class: ' . mysqli_error($conn) . '</p>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Class</title>
<link rel="stylesheet" href="css/form.css">
</head>
<body>
<div class="main">
    <h2>Edit Class</h2>
    <form action="" method="post">
        <input type="text" name="class_name" value="<?php echo htmlspecialchars($class['name']); ?>" required>
        <input type="submit" value="Update">
    </form>
</div>
</body>
</html>
