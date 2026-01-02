<?php
session_start();
require_once 'init.php';

// Only class teachers can access this page
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'class_teacher') {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$assigned_class = $_SESSION['assigned_class'] ?? '';

// Get students in the class teacher's class
$students_query = "SELECT id, name, roll_number FROM students WHERE class_name = ? ORDER BY name";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("s", $assigned_class);
$stmt->execute();
$students_result = $stmt->get_result();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_comments'])) {
    $student_id = intval($_POST['student_id']);
    $term = $_POST['term'];
    $session = $_POST['session'];
    $teacher_comments = trim($_POST['teacher_comments']);
    $principal_comments = trim($_POST['principal_comments']);
    $teacher_signature = trim($_POST['teacher_signature']);
    $principal_signature = trim($_POST['principal_signature'] ?? ''); // ADDED
    
    // Check if comment already exists
    $check_sql = "SELECT id FROM teacher_comments WHERE student_id = ? AND term = ? AND session = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $student_id, $term, $session);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing comment - FIXED
        $update_sql = "UPDATE teacher_comments SET 
                      teacher_comments = ?, 
                      principal_comments = ?,
                      teacher_signature_name = ?,
                      principal_signature_name = ?,
                      class_teacher_id = ?,
                      updated_at = NOW()
                      WHERE student_id = ? AND term = ? AND session = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssisss", $teacher_comments, $principal_comments, $teacher_signature, $principal_signature, $teacher_id, $student_id, $term, $session);
        
        if ($update_stmt->execute()) {
            $message = "Comments updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating comments: " . $conn->error;
            $message_type = "error";
        }
    } else {
        // Insert new comment - FIXED
        $insert_sql = "INSERT INTO teacher_comments 
                      (student_id, term, session, class_teacher_id, teacher_comments, principal_comments, teacher_signature_name, principal_signature_name) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ississss", $student_id, $term, $session, $teacher_id, $teacher_comments, $principal_comments, $teacher_signature, $principal_signature);
        
        if ($insert_stmt->execute()) {
            $message = "Comments saved successfully!";
            $message_type = "success";
        } else {
            $message = "Error saving comments: " . $conn->error;
            $message_type = "error";
        }
    }
}

// Get existing comments if student is selected
$existing_comments = null;
if (isset($_GET['student_id']) && isset($_GET['term']) && isset($_GET['session'])) {
    $student_id = intval($_GET['student_id']);
    $term = $_GET['term'];
    $session = $_GET['session'];
    
    $select_sql = "SELECT * FROM teacher_comments WHERE student_id = ? AND term = ? AND session = ?";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("iss", $student_id, $term, $session);
    $select_stmt->execute();
    $existing_comments = $select_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Class Teacher Comments - SRMS</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="p-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Class Teacher Comments & Signatures</h1>
                <p class="text-white/80">Class: <?php echo htmlspecialchars($assigned_class); ?> | Teacher: <?php echo htmlspecialchars($teacher_name); ?></p>
            </div>
            <div>
                <a href="class_teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Selection Form -->
        <div class="card rounded-xl p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-search text-blue-500 mr-3"></i>
                Select Student and Term
            </h3>
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Student</label>
                    <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Choose Student</option>
                        <?php while ($student = $students_result->fetch_assoc()): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo isset($_GET['student_id']) && $_GET['student_id'] == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['name']); ?> (Roll: <?php echo htmlspecialchars($student['roll_number']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Term</label>
                    <select name="term" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="First Term" <?php echo (isset($_GET['term']) && $_GET['term'] == 'First Term') ? 'selected' : ''; ?>>First Term</option>
                        <option value="Second Term" <?php echo (isset($_GET['term']) && $_GET['term'] == 'Second Term') ? 'selected' : ''; ?>>Second Term</option>
                        <option value="Third Term" <?php echo (isset($_GET['term']) && $_GET['term'] == 'Third Term') ? 'selected' : ''; ?>>Third Term</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Session</label>
                    <select name="session" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="2024" <?php echo (isset($_GET['session']) && $_GET['session'] == '2024') ? 'selected' : ''; ?>>2024</option>
                        <option value="2024/2025" <?php echo (isset($_GET['session']) && $_GET['session'] == '2024/2025') ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024">2023/2024</option>
                        <option value="2023">2023</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-search mr-2"></i>Load Comments
                    </button>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['student_id']) && isset($_GET['term']) && isset($_GET['session'])): 
            // Get student details
            $student_id = intval($_GET['student_id']);
            $student_sql = "SELECT name, roll_number FROM students WHERE id = ?";
            $student_stmt = $conn->prepare($student_sql);
            $student_stmt->bind_param("i", $student_id);
            $student_stmt->execute();
            $student = $student_stmt->get_result()->fetch_assoc();
        ?>
            <!-- Comments Form -->
            <div class="card rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-edit text-green-500 mr-3"></i>
                    Add/Edit Comments for <?php echo htmlspecialchars($student['name']); ?> 
                    (Roll: <?php echo htmlspecialchars($student['roll_number']); ?>)
                </h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <input type="hidden" name="term" value="<?php echo htmlspecialchars($_GET['term']); ?>">
                    <input type="hidden" name="session" value="<?php echo htmlspecialchars($_GET['session']); ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Class Teacher's Comments:</label>
                            <textarea name="teacher_comments" rows="6" 
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter constructive comments about the student's performance, behavior, and areas for improvement..."><?php 
                                echo $existing_comments ? htmlspecialchars($existing_comments['teacher_comments']) : ''; 
                            ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">These comments will appear on the report card.</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Principal's Comments (Optional):</label>
                            <textarea name="principal_comments" rows="6" 
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Space for principal's comments (if applicable)..."><?php 
                                echo $existing_comments ? htmlspecialchars($existing_comments['principal_comments']) : ''; 
                            ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">Can be left blank or filled by principal.</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Teacher's Signature Name:</label>
                            <input type="text" name="teacher_signature" 
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo $existing_comments ? htmlspecialchars($existing_comments['teacher_signature_name']) : htmlspecialchars($teacher_name); ?>"
                                placeholder="Enter signature name (e.g., Mr. John Smith)">
                            <p class="text-sm text-gray-500 mt-1">This will appear as "Class Teacher's Signature"</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Principal's Signature Name (Optional):</label>
                            <input type="text" name="principal_signature" 
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo $existing_comments ? htmlspecialchars($existing_comments['principal_signature_name']) : ''; ?>"
                                placeholder="Enter principal's name">
                            <p class="text-sm text-gray-500 mt-1">This will appear as "Principal's Signature"</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Comments will be saved and displayed on the report card.
                        </div>
                        <div>
                            <button type="submit" name="save_comments" 
                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Save Comments
                            </button>
                            <a href="class_teacher_comments.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition duration-200 ml-4">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php if ($existing_comments): ?>
                    <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <h4 class="font-bold text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-history mr-2"></i>Last Updated
                        </h4>
                        <p class="text-sm text-blue-600">
                            These comments were last updated on: <?php echo date('F j, Y, g:i a', strtotime($existing_comments['updated_at'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Preview Section -->
            <div class="card rounded-xl p-6 mt-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-eye text-purple-500 mr-3"></i>
                    Report Card Preview
                </h3>
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-300">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Class Teacher's Comments:</h4>
                            <div class="h-40 border border-gray-300 rounded p-4 bg-white overflow-y-auto">
                                <?php if ($existing_comments && !empty($existing_comments['teacher_comments'])): ?>
                                    <?php echo nl2br(htmlspecialchars($existing_comments['teacher_comments'])); ?>
                                <?php else: ?>
                                    <p class="text-gray-400 italic">No comments saved yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="border-t border-gray-400 mt-8 pt-2 text-center">
                                <span class="font-medium text-gray-700">
                                    <?php echo $existing_comments ? htmlspecialchars($existing_comments['teacher_signature_name']) : htmlspecialchars($teacher_name); ?>
                                </span>
                                <p class="text-sm text-gray-500">Class Teacher's Signature</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Principal's Comments:</h4>
                            <div class="h-40 border border-gray-300 rounded p-4 bg-white overflow-y-auto">
                                <?php if ($existing_comments && !empty($existing_comments['principal_comments'])): ?>
                                    <?php echo nl2br(htmlspecialchars($existing_comments['principal_comments'])); ?>
                                <?php else: ?>
                                    <p class="text-gray-400 italic">No principal comments saved yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="border-t border-gray-400 mt-8 pt-2 text-center">
                                <span class="font-medium text-gray-700">
                                    <?php echo $existing_comments && !empty($existing_comments['principal_signature_name']) ? htmlspecialchars($existing_comments['principal_signature_name']) : '________________'; ?>
                                </span>
                                <p class="text-sm text-gray-500">Principal's Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>