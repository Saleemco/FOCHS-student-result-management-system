<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="./css/student.css">
<title>Student Result</title>
</head>
<body>

<?php
include("init.php");

// Fetch classes for dropdown
$class_res = mysqli_query($conn, "SELECT name FROM class ORDER BY name");
?>

<div class="container">
    <h2>Check Your Result</h2>

    <form method="GET" action="">
        <label for="class">Select Class:</label>
        <select name="class" id="class" required>
            <option value="">--Select Class--</option>
            <?php
            while($c = mysqli_fetch_assoc($class_res)){
                $selected = (isset($_GET['class']) && $_GET['class'] == $c['name']) ? "selected" : "";
                echo "<option value='".$c['name']."' $selected>".$c['name']."</option>";
            }
            ?>
        </select>

        <label for="rn">Roll Number:</label>
        <input type="text" name="rn" id="rn" value="<?php echo isset($_GET['rn']) ? htmlspecialchars($_GET['rn']) : ''; ?>" required>

        <input type="submit" value="Check Result">
    </form>

<?php
if(isset($_GET['class'], $_GET['rn'])){
    $class = $_GET['class'];
    $rn = $_GET['rn'];

    // Validate roll number (only digits)
    if(!preg_match("/^\d+$/", $rn)){
        echo '<p class="error">Please enter a valid roll number</p>';
        exit();
    }

    // Fetch student name
    $name_sql = mysqli_query($conn, "SELECT `name` FROM `students` WHERE `rno`='$rn' AND `class_name`='$class'");
    if(mysqli_num_rows($name_sql) == 0){
        echo "<p class='error'>Student not found.</p>";
        exit();
    }
    $name = mysqli_fetch_assoc($name_sql)['name'];

    // Fetch result
    $result_sql = mysqli_query($conn, "SELECT `p1`,`p2`,`p3`,`p4`,`p5`,`marks`,`percentage` FROM `result` WHERE `rno`='$rn' AND `class`='$class'");
    if(mysqli_num_rows($result_sql) == 0){
        echo "<p class='error'>Result not found.</p>";
        exit();
    }

    $result = mysqli_fetch_assoc($result_sql);
?>

    <div class="details">
        <p><strong>Name:</strong> <?php echo $name; ?></p>
        <p><strong>Class:</strong> <?php echo $class; ?></p>
        <p><strong>Roll No:</strong> <?php echo $rn; ?></p>
    </div>

    <div class="main">
        <div class="s1">
            <p>Subjects</p>
            <p>Paper 1</p>
            <p>Paper 2</p>
            <p>Paper 3</p>
            <p>Paper 4</p>
            <p>Paper 5</p>
        </div>
        <div class="s2">
            <p>Marks</p>
            <p><?php echo $result['p1']; ?></p>
            <p><?php echo $result['p2']; ?></p>
            <p><?php echo $result['p3']; ?></p>
            <p><?php echo $result['p4']; ?></p>
            <p><?php echo $result['p5']; ?></p>
        </div>
    </div>

    <div class="result">
        <p><strong>Total Marks:</strong> <?php echo $result['marks']; ?></p>
        <p><strong>Percentage:</strong> <?php echo $result['percentage']; ?>%</p>
    </div>

    <div class="button">
        <button onclick="window.print()">Print Result</button>
    </div>

<?php
}
?>
</div>

</body>
</html>
