<?php
session_start();
include('init.php');

// STRICT Class Teacher Only Access
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'class_teacher') {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$assigned_class = $_SESSION['assigned_class'];

// Get current term and session - USE VARCHAR for session
$current_term = "First Term";
$current_session = "2024/2025"; // Keep as VARCHAR

// Handle AJAX auto-save requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['ajax_save'])) {
        header('Content-Type: application/json');
        
        $student_id = intval($_POST['student_id']);
        $skill = $_POST['skill'];
        $rating = intval($_POST['rating']);
        
        $response = [
            'success' => false,
            'message' => '',
            'student_id' => $student_id,
            'overall_rating' => 3.0
        ];
        
        try {
            // First, verify student exists (to avoid foreign key constraint error)
            $verify_student = $conn->prepare("SELECT id FROM students WHERE id = ?");
            $verify_student->bind_param("i", $student_id);
            $verify_student->execute();
            $verify_result = $verify_student->get_result();
            
            if ($verify_result->num_rows == 0) {
                $response['message'] = "Student ID $student_id does not exist";
                echo json_encode($response);
                exit();
            }
            $verify_student->close();
            
            // Check if record exists
            $check_sql = "SELECT id FROM psychomotor_domain WHERE student_id = ? AND term = ? AND session = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if ($check_stmt) {
                $check_stmt->bind_param("iss", $student_id, $current_term, $current_session);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->num_rows > 0;
                $check_stmt->close();
                
                if ($exists) {
                    // Update existing record
                    $update_sql = "UPDATE psychomotor_domain SET $skill = ?, teacher_id = ?, assessed_by = ? WHERE student_id = ? AND term = ? AND session = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("isiiss", $rating, $teacher_id, $teacher_id, $student_id, $current_term, $current_session);
                        if ($update_stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Updated $skill to $rating";
                        } else {
                            $response['message'] = "Update failed: " . $update_stmt->error;
                        }
                        $update_stmt->close();
                    }
                } else {
                    // Create new record with all default values
                    $insert_sql = "INSERT INTO psychomotor_domain (
                        student_id, term, session, handwriting, verbal_fluency, 
                        games, sports, handling_tools, drawing_painting, musical_skills, 
                        overall_rating, teacher_id, assessed_by
                    ) VALUES (?, ?, ?, 3, 3, 3, 3, 3, 3, 3, 3.0, ?, ?)";
                    
                    $insert_stmt = $conn->prepare($insert_sql);
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("issii", $student_id, $current_term, $current_session, $teacher_id, $teacher_id);
                        if ($insert_stmt->execute()) {
                            // Now update the specific skill
                            $update_sql = "UPDATE psychomotor_domain SET $skill = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("ii", $rating, $insert_stmt->insert_id);
                                if ($update_stmt->execute()) {
                                    $response['success'] = true;
                                    $response['message'] = "Created record and set $skill to $rating";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $response['message'] = "Insert failed: " . $insert_stmt->error;
                        }
                        $insert_stmt->close();
                    }
                }
                
                // Recalculate overall rating if save was successful
                if ($response['success']) {
                    // Get all ratings for this student
                    $select_sql = "SELECT handwriting, verbal_fluency, games, sports, handling_tools, drawing_painting, musical_skills 
                                  FROM psychomotor_domain 
                                  WHERE student_id = ? AND term = ? AND session = ?";
                    $select_stmt = $conn->prepare($select_sql);
                    if ($select_stmt) {
                        $select_stmt->bind_param("iss", $student_id, $current_term, $current_session);
                        $select_stmt->execute();
                        $select_result = $select_stmt->get_result();
                        
                        if ($row = $select_result->fetch_assoc()) {
                            $ratings = array_values($row);
                            $average = array_sum($ratings) / count($ratings);
                            $overall_rating = round($average, 1);
                            
                            // Update overall rating
                            $update_overall_sql = "UPDATE psychomotor_domain SET overall_rating = ? WHERE student_id = ? AND term = ? AND session = ?";
                            $update_overall_stmt = $conn->prepare($update_overall_sql);
                            if ($update_overall_stmt) {
                                $update_overall_stmt->bind_param("diss", $overall_rating, $student_id, $current_term, $current_session);
                                $update_overall_stmt->execute();
                                $update_overall_stmt->close();
                                
                                $response['overall_rating'] = $overall_rating;
                            }
                        }
                        $select_stmt->close();
                    }
                }
            } else {
                $response['message'] = "Check prepare failed: " . $conn->error;
            }
        } catch (Exception $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
        
        echo json_encode($response);
        exit();
    }
    
    // Handle bulk reset
    if (isset($_POST['reset_all'])) {
        $success_count = 0;
        $error_count = 0;
        
        // Get all students in class
        $students_query = "SELECT id FROM students WHERE class_name = ?";
        $stmt = $conn->prepare($students_query);
        if ($stmt) {
            $stmt->bind_param("s", $assigned_class);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $student_id = $row['id'];
                
                // Delete existing record
                $delete_sql = "DELETE FROM psychomotor_domain WHERE student_id = ? AND term = ? AND session = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                if ($delete_stmt) {
                    $delete_stmt->bind_param("iss", $student_id, $current_term, $current_session);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }
                
                // Insert default record
                $insert_sql = "INSERT INTO psychomotor_domain (
                    student_id, term, session, handwriting, verbal_fluency, 
                    games, sports, handling_tools, drawing_painting, musical_skills, 
                    overall_rating, teacher_id, assessed_by
                ) VALUES (?, ?, ?, 3, 3, 3, 3, 3, 3, 3, 3.0, ?, ?)";
                
                $insert_stmt = $conn->prepare($insert_sql);
                if ($insert_stmt) {
                    $insert_stmt->bind_param("issii", $student_id, $current_term, $current_session, $teacher_id, $teacher_id);
                    if ($insert_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                        error_log("Reset failed for student $student_id: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
            }
            $stmt->close();
        }
        
        $_SESSION['reset_message'] = "Reset $success_count students to default ratings" . ($error_count > 0 ? " ($error_count failed)" : "");
        header("Location: class_teacher_psychomotor.php");
        exit();
    }
}

// Get students in teacher's class
$students_query = "SELECT id as student_id, name, roll_number, gender 
                   FROM students 
                   WHERE class_name = '$assigned_class' 
                   ORDER BY name";
$students_result = mysqli_query($conn, $students_query);

// Get detailed existing assessments
$detailed_assessments_query = "SELECT * FROM psychomotor_domain WHERE term = ? AND session = ?";
$detailed_stmt = $conn->prepare($detailed_assessments_query);
if ($detailed_stmt) {
    $detailed_stmt->bind_param("ss", $current_term, $current_session);
    $detailed_stmt->execute();
    $detailed_result = $detailed_stmt->get_result();

    $detailed_assessments = [];
    while ($row = $detailed_result->fetch_assoc()) {
        $detailed_assessments[$row['student_id']] = $row;
    }
    $detailed_stmt->close();
} else {
    $detailed_assessments = [];
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
    <title>Psychomotor Assessment - <?php echo $assigned_class; ?></title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .rating-option {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            margin: 2px;
            cursor: pointer;
            border-radius: 3px;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .rating-option:hover {
            background-color: #f8f9fa;
            transform: scale(1.1);
        }
        .rating-selected {
            background-color: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
            box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
        }
        .rating-saving {
            background-color: #fbbf24 !important;
            color: white;
            border-color: #f59e0b;
            animation: pulse 1s infinite;
        }
        .rating-saved {
            background-color: #10b981 !important;
            color: white;
            border-color: #059669;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        .assessment-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .assessment-table th, .assessment-table td {
            border: 1px solid #e5e7eb;
            padding: 12px 8px;
            text-align: center;
        }
        .assessment-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        .student-info {
            background-color: #f3e8ff;
            font-weight: 600;
            color: #6b21a8;
            position: sticky;
            left: 0;
            min-width: 180px;
        }
        .skill-header {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 120px;
            vertical-align: bottom;
            font-size: 12px;
            font-weight: 600;
        }
        .overall-rating {
            font-weight: bold;
            color: #059669;
            background-color: #d1fae5;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .overall-updating {
            background-color: #fef3c7;
            color: #92400e;
        }
        .save-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-weight: 500;
        }
        .save-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .save-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .save-saving {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .auto-save-badge {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #6b21a8;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
        }
        .status-indicator {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        .status-saved {
            background-color: #d1fae5;
            color: #059669;
        }
        .status-saving {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-error {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .debug-info {
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body class="flex">
    <!-- Save Status Indicator -->
    <div id="saveStatus" class="save-status">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="saveStatusText">Saved successfully</span>
    </div>
    
    <!-- Auto-save Badge -->
    <div class="auto-save-badge">
        <i class="fas fa-sync-alt text-purple-500"></i>
        <span>Auto-save: </span>
        <span id="autoSaveStatus" class="status-indicator status-saved">Active</span>
    </div>

    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Class Teacher</span>
            </div>
        </div>

        <!-- Teacher Info -->
        <div class="bg-green-50 rounded-lg p-4 mb-6">
            <div class="bg-green-500 text-white rounded-lg px-3 py-1 text-center text-sm font-semibold mb-2">
                <i class="fas fa-crown mr-2"></i>
                Class Teacher
            </div>
            <h3 class="font-semibold text-green-800 text-sm"><?php echo $teacher_name; ?></h3>
            <p class="text-green-600 text-xs">Class: <?php echo $assigned_class; ?></p>
        </div>

        <nav class="space-y-2">
            <a href="class_teacher_dashboard.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="class_teacher_affective.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-star"></i>
                <span class="font-medium">Affective Assessment</span>
            </a>
            <a href="class_teacher_psychomotor.php" class="flex items-center space-x-3 p-3 text-purple-600 bg-purple-50 rounded">
                <i class="fas fa-running"></i>
                <span class="font-medium">Psychomotor Assessment</span>
            </a>
            <a href="class_teacher_attendance.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-calendar-check"></i>
                <span class="font-medium">Attendance</span>
            </a>
           <a href="report_card_selector.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-file-pdf text-red-600"></i>
                <span class="font-medium">Generate Report Cards</span>
            </a>
            <a href="teacher_logout.php" class="flex items-center space-x-3 p-3 text-red-600 hover:bg-red-50 rounded">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Psychomotor Domain Assessment</h1>
                <p class="text-white/80">Click any rating - it saves automatically!</p>
                <p class="text-white/60 text-sm mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    Changes are saved instantly to the database
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-calendar mr-2"></i>
                    <span>Term: <?php echo $current_term; ?> | Session: <?php echo $current_session; ?></span>
                </div>
                <div class="bg-purple-500 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-robot mr-2"></i>
                    Auto-save Active
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['reset_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $_SESSION['reset_message']; unset($_SESSION['reset_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <div class="debug-info">
            <strong>Debug Info:</strong> Teacher ID: <?php echo $teacher_id; ?> | 
            Session: <?php echo $current_session; ?> (VARCHAR) |
            Students in class: <?php echo mysqli_num_rows($students_result); ?>
        </div>

        <!-- Rating Scale Info -->
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                Psychomotor Rating Scale Guide
            </h3>
            <div class="grid grid-cols-5 gap-3 text-center">
                <div class="bg-green-100 text-green-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">5</div>
                    <div class="text-sm font-semibold">Excellent</div>
                </div>
                <div class="bg-blue-100 text-blue-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">4</div>
                    <div class="text-sm font-semibold">Good</div>
                </div>
                <div class="bg-yellow-100 text-yellow-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">3</div>
                    <div class="text-sm font-semibold">Fair</div>
                </div>
                <div class="bg-orange-100 text-orange-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">2</div>
                    <div class="text-sm font-semibold">Poor</div>
                </div>
                <div class="bg-red-100 text-red-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">1</div>
                    <div class="text-sm font-semibold">None</div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="assessment-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="student-info">Student Information</th>
                        <th colspan="7" style="text-align: center; background-color: #f3e8ff; color: #6b21a8;">
                            Psychomotor Domain Skills
                        </th>
                        <th rowspan="2" style="min-width: 80px; background-color: #d1fae5; color: #059669;">
                            Overall
                        </th>
                    </tr>
                    <tr>
                        <th class="skill-header">Handwriting</th>
                        <th class="skill-header">Verbal Fluency</th>
                        <th class="skill-header">Games</th>
                        <th class="skill-header">Sports</th>
                        <th class="skill-header">Handling Tools</th>
                        <th class="skill-header">Drawing & Painting</th>
                        <th class="skill-header">Musical Skills</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                        <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                            <?php
                            $student_id = $student['student_id'];
                            $existing = $detailed_assessments[$student_id] ?? null;
                            ?>
                            <tr>
                                <td class="student-info">
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                    <br><small>Roll: <?php echo htmlspecialchars($student['roll_number']); ?></small>
                                    <div class="mt-2">
                                        <span class="status-indicator status-saved" id="status_<?php echo $student_id; ?>">
                                            <i class="fas fa-check-circle mr-1"></i>Ready
                                        </span>
                                    </div>
                                </td>
                                
                                <?php
                                $skills = [
                                    'handwriting', 'verbal_fluency', 'games', 'sports',
                                    'handling_tools', 'drawing_painting', 'musical_skills'
                                ];
                                
                                foreach ($skills as $skill):
                                    $current_value = $existing[$skill] ?? 3;
                                ?>
                                    <td>
                                        <div class="rating-options">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <div class="rating-option <?php echo $current_value == $i ? 'rating-selected' : ''; ?>"
                                                     onclick="rateStudent(<?php echo $student_id; ?>, '<?php echo $skill; ?>', <?php echo $i; ?>, this)">
                                                    <?php echo $i; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                                
                                <td class="overall-rating" id="overall_<?php echo $student_id; ?>">
                                    <?php 
                                    if ($existing && isset($existing['overall_rating'])) {
                                        echo number_format($existing['overall_rating'], 1);
                                    } else {
                                        echo '3.0';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                <i class="fas fa-users text-3xl mb-3"></i>
                                <p>No students found in <?php echo $assigned_class; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-center space-x-4 mt-8">
            <form method="POST" action="" onsubmit="return confirm('Reset ALL ratings to default (3)?');" class="inline">
                <input type="hidden" name="reset_all" value="1">
                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg">
                    <i class="fas fa-redo mr-2"></i>Reset All to Default
                </button>
            </form>
            <a href="class_teacher_dashboard.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
            <button onclick="testSave()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg">
                <i class="fas fa-vial mr-2"></i>Test Save
            </button>
        </div>
    </div>

    <script>
        // Function to rate a student
        async function rateStudent(studentId, skill, rating, element) {
            // Update visual
            const skillColumn = element.closest('td');
            const options = skillColumn.querySelectorAll('.rating-option');
            options.forEach(opt => {
                opt.classList.remove('rating-selected', 'rating-saving', 'rating-saved');
            });
            element.classList.add('rating-saving');
            
            // Update status
            updateStudentStatus(studentId, 'saving');
            
            // Show saving status
            showSaveStatus('Saving...', 'saving');
            
            try {
                // Send AJAX request
                const formData = new FormData();
                formData.append('ajax_save', '1');
                formData.append('student_id', studentId);
                formData.append('skill', skill);
                formData.append('rating', rating);
                
                const response = await fetch('class_teacher_psychomotor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Success
                    element.classList.remove('rating-saving');
                    element.classList.add('rating-saved');
                    
                    updateStudentStatus(studentId, 'saved');
                    
                    // Update overall rating
                    const overallCell = document.getElementById(`overall_${studentId}`);
                    if (overallCell && result.overall_rating) {
                        overallCell.textContent = result.overall_rating.toFixed(1);
                    }
                    
                    showSaveStatus('Saved successfully!', 'success');
                    
                    // Reset to normal after 1 second
                    setTimeout(() => {
                        element.classList.remove('rating-saved');
                        element.classList.add('rating-selected');
                    }, 1000);
                    
                } else {
                    // Error
                    element.classList.remove('rating-saving');
                    updateStudentStatus(studentId, 'error');
                    showSaveStatus('Error: ' + result.message, 'error');
                }
                
            } catch (error) {
                // Network error
                element.classList.remove('rating-saving');
                updateStudentStatus(studentId, 'error');
                showSaveStatus('Network error', 'error');
                console.error('Save error:', error);
            }
        }
        
        // Update student status
        function updateStudentStatus(studentId, status) {
            const statusElement = document.getElementById(`status_${studentId}`);
            if (!statusElement) return;
            
            statusElement.className = 'status-indicator';
            
            switch(status) {
                case 'saving':
                    statusElement.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-1"></i>Saving';
                    statusElement.classList.add('status-saving');
                    break;
                case 'saved':
                    statusElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Saved';
                    statusElement.classList.add('status-saved');
                    break;
                case 'error':
                    statusElement.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>Error';
                    statusElement.classList.add('status-error');
                    break;
            }
        }
        
        // Show save status
        function showSaveStatus(message, type) {
            const statusDiv = document.getElementById('saveStatus');
            const statusText = document.getElementById('saveStatusText');
            const autoSaveStatus = document.getElementById('autoSaveStatus');
            
            if (!statusDiv || !statusText) return;
            
            statusText.textContent = message;
            statusDiv.className = 'save-status';
            
            switch(type) {
                case 'success':
                    statusDiv.classList.add('save-success');
                    statusDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
                    autoSaveStatus.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Active';
                    autoSaveStatus.className = 'status-indicator status-saved';
                    break;
                case 'error':
                    statusDiv.classList.add('save-error');
                    statusDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
                    autoSaveStatus.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>Error';
                    autoSaveStatus.className = 'status-indicator status-error';
                    break;
                case 'saving':
                    statusDiv.classList.add('save-saving');
                    statusDiv.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-2"></i>' + message;
                    autoSaveStatus.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-1"></i>Saving';
                    autoSaveStatus.className = 'status-indicator status-saving';
                    break;
            }
            
            statusDiv.style.display = 'flex';
            statusDiv.style.alignItems = 'center';
            
            // Auto-hide
            if (type !== 'error') {
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 3000);
            }
        }
        
        // Test function
        async function testSave() {
            showSaveStatus('Testing save...', 'saving');
            
            try {
                const formData = new FormData();
                formData.append('ajax_save', '1');
                formData.append('student_id', 84); // Use a real student ID
                formData.append('skill', 'handwriting');
                formData.append('rating', 4);
                
                const response = await fetch('class_teacher_psychomotor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSaveStatus('Test successful! System is working.', 'success');
                    console.log('Test result:', result);
                } else {
                    showSaveStatus('Test failed: ' + result.message, 'error');
                    console.error('Test failed:', result);
                }
            } catch (error) {
                showSaveStatus('Test error: ' + error.message, 'error');
                console.error('Test error:', error);
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Psychomotor assessment loaded');
        });
    </script>
</body>
</html>