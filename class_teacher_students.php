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

// Get current term and session
$current_term = "First Term";
$current_session = "2024/2025";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_assessments'])) {
        $student_id = $_POST['student_id'];
        $term = $_POST['term'];
        $session = $_POST['session'];
        
        // Get all psychomotor domain ratings
        $handwriting = $_POST['handwriting'][$student_id];
        $verbal_fluency = $_POST['verbal_fluency'][$student_id];
        $games = $_POST['games'][$student_id];
        $sports = $_POST['sports'][$student_id];
        $handling_tools = $_POST['handling_tools'][$student_id];
        $drawing_painting = $_POST['drawing_painting'][$student_id];
        $musical_skills = $_POST['musical_skills'][$student_id];
        
        // Calculate overall rating (average of all skills)
        $ratings = [
            $handwriting, $verbal_fluency, $games, $sports, 
            $handling_tools, $drawing_painting, $musical_skills
        ];
        $overall_rating = round(array_sum($ratings) / count($ratings), 1);
        
        // Check if record exists
        $check_query = "SELECT id FROM psychomotor_domain WHERE student_id = ? AND term = ? AND session = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("iss", $student_id, $term, $session);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $update_query = "UPDATE psychomotor_domain SET 
                handwriting = ?, verbal_fluency = ?, games = ?, sports = ?, 
                handling_tools = ?, drawing_painting = ?, musical_skills = ?, 
                overall_rating = ?, assessed_by = ?
                WHERE student_id = ? AND term = ? AND session = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param(
                "iiiiiiidiss", 
                $handwriting, $verbal_fluency, $games, $sports,
                $handling_tools, $drawing_painting, $musical_skills,
                $overall_rating, $teacher_id,
                $student_id, $term, $session
            );
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Psychomotor domain assessment updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating assessment: " . $conn->error;
            }
        } else {
            // Insert new record
            $insert_query = "INSERT INTO psychomotor_domain (
                student_id, term, session, handwriting, verbal_fluency, games, sports,
                handling_tools, drawing_painting, musical_skills, overall_rating, assessed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param(
                "issiiiiiiidi",
                $student_id, $term, $session, $handwriting, $verbal_fluency, $games, $sports,
                $handling_tools, $drawing_painting, $musical_skills, $overall_rating, $teacher_id
            );
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Psychomotor domain assessment saved successfully!";
            } else {
                $_SESSION['error_message'] = "Error saving assessment: " . $conn->error;
            }
        }
    }
}

// Get students in teacher's class
$students_query = "SELECT id as student_id, name, roll_number, gender 
                   FROM students 
                   WHERE class_name = '$assigned_class' 
                   ORDER BY name";
$students_result = mysqli_query($conn, $students_query);

// Get detailed existing assessments for pre-filling form
$detailed_assessments_query = "SELECT * FROM psychomotor_domain WHERE term = ? AND session = ?";
$detailed_stmt = $conn->prepare($detailed_assessments_query);
$detailed_stmt->bind_param("ss", $current_term, $current_session);
$detailed_stmt->execute();
$detailed_result = $detailed_stmt->get_result();

$detailed_assessments = [];
while ($row = $detailed_result->fetch_assoc()) {
    $detailed_assessments[$row['student_id']] = $row;
}

// Psychomotor rating scale (different from affective)
$psychomotor_scale = [
    5 => 'Excellent degree of observable trait',
    4 => 'Good level of observable trait',
    3 => 'Fair but acceptable level of observable trait', 
    2 => 'Poor level of observable trait',
    1 => 'No Observable trait'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Psychomotor Domain Assessment - <?php echo $assigned_class; ?></title>
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
        }
        .rating-option:hover {
            background-color: #f8f9fa;
            transform: scale(1.1);
        }
        .rating-selected {
            background-color: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
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
        }
        .scale-description {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }
    </style>
</head>
<body class="flex">
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

            <a href="class_teacher_manage_students.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-users"></i>
                <span class="font-medium">Manage Students</span>
            </a>

            <a href="class_teacher_attendance.php" class="flex items-center space-x-3 p-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-calendar-check"></i>
                <span class="font-medium">Attendance</span>
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
                <p class="text-white/80">Evaluating skills and abilities for <?php echo $assigned_class; ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white/20 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <i class="fas fa-calendar mr-2"></i>
                    <span>Term: <?php echo $current_term; ?> | Session: <?php echo $current_session; ?></span>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

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
                    <div class="scale-description">Excellent degree of observable trait</div>
                </div>
                <div class="bg-blue-100 text-blue-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">4</div>
                    <div class="text-sm font-semibold">Good</div>
                    <div class="scale-description">Good level of observable trait</div>
                </div>
                <div class="bg-yellow-100 text-yellow-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">3</div>
                    <div class="text-sm font-semibold">Fair</div>
                    <div class="scale-description">Fair but acceptable level</div>
                </div>
                <div class="bg-orange-100 text-orange-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">2</div>
                    <div class="text-sm font-semibold">Poor</div>
                    <div class="scale-description">Poor level of observable trait</div>
                </div>
                <div class="bg-red-100 text-red-800 p-3 rounded-lg">
                    <div class="text-xl font-bold">1</div>
                    <div class="text-sm font-semibold">None</div>
                    <div class="scale-description">No observable trait</div>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="term" value="<?php echo $current_term; ?>">
            <input type="hidden" name="session" value="<?php echo $current_session; ?>">
            
            <div class="table-container">
                <table class="assessment-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="student-info">Student Information</th>
                            <th colspan="7" style="text-align: center; background-color: #f3e8ff; color: #6b21a8;">
                                Psychomotor Domain Skills
                            </th>
                            <th rowspan="2" style="min-width: 80px; background-color: #d1fae5; color: #059669;">
                                Overall Rating
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
                                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    </td>
                                    
                                    <?php
                                    $skills = [
                                        'handwriting', 'verbal_fluency', 'games', 'sports',
                                        'handling_tools', 'drawing_painting', 'musical_skills'
                                    ];
                                    
                                    foreach ($skills as $skill):
                                        $current_value = $existing[$skill] ?? 3; // Default to 3 (Fair)
                                    ?>
                                        <td>
                                            <div class="rating-options">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <div class="rating-option <?php echo $current_value == $i ? 'rating-selected' : ''; ?>"
                                                         onclick="selectRating(this, <?php echo $student_id; ?>, '<?php echo $skill; ?>', <?php echo $i; ?>)">
                                                        <?php echo $i; ?>
                                                    </div>
                                                    <input type="radio" 
                                                           name="<?php echo $skill; ?>[<?php echo $student_id; ?>]" 
                                                           value="<?php echo $i; ?>" 
                                                           <?php echo $current_value == $i ? 'checked' : ''; ?>
                                                           style="display: none;">
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="overall-rating" id="overall_<?php echo $student_id; ?>">
                                        <?php echo $existing['overall_rating'] ?? '3.0'; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-users text-3xl mb-3"></i>
                                    <p>No students found in <?php echo $assigned_class; ?></p>
                                    <p class="text-sm">Add students to start psychomotor domain assessment</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                <div class="flex justify-center space-x-4 mt-6">
                    <button type="submit" name="save_assessments" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>
                        Save All Assessments
                    </button>
                    <button type="button" onclick="resetAllRatings()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                        <i class="fas fa-redo mr-2"></i>
                        Reset All Ratings
                    </button>
                    <a href="class_teacher_dashboard.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Function to select a rating
        function selectRating(element, studentId, skill, rating) {
            // Find the radio button and check it
            const radio = element.parentElement.querySelector(`input[value="${rating}"]`);
            radio.checked = true;
            
            // Update visual selection for this skill
            const options = element.parentElement.querySelectorAll('.rating-option');
            options.forEach(opt => {
                opt.classList.remove('rating-selected');
            });
            element.classList.add('rating-selected');
            
            // Recalculate overall rating for this student
            recalculateOverallRating(studentId);
        }
        
        // Function to recalculate overall rating for a student
        function recalculateOverallRating(studentId) {
            let total = 0;
            let count = 0;
            
            // Get all checked ratings for this student
            const ratings = document.querySelectorAll(`input[name*="[${studentId}]"]:checked`);
            ratings.forEach(radio => {
                total += parseInt(radio.value);
                count++;
            });
            
            if (count > 0) {
                const average = total / count;
                const overallCell = document.getElementById(`overall_${studentId}`);
                overallCell.textContent = average.toFixed(1);
            }
        }
        
        // Function to reset all ratings
        function resetAllRatings() {
            if (confirm('Are you sure you want to reset all ratings to default (3 - Fair)?')) {
                const allOptions = document.querySelectorAll('.rating-option');
                allOptions.forEach(option => {
                    option.classList.remove('rating-selected');
                    // Select rating 3 by default
                    if (option.textContent.trim() === '3') {
                        option.classList.add('rating-selected');
                        const radio = option.nextElementSibling;
                        if (radio && radio.type === 'radio') {
                            radio.checked = true;
                        }
                    }
                });
                
                // Reset all overall ratings
                const overallCells = document.querySelectorAll('[id^="overall_"]');
                overallCells.forEach(cell => {
                    cell.textContent = '3.0';
                });
            }
        }
        
        // Recalculate all overall ratings on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get unique student IDs from the overall rating cells
            const overallCells = document.querySelectorAll('[id^="overall_"]');
            overallCells.forEach(cell => {
                const studentId = cell.id.replace('overall_', '');
                recalculateOverallRating(studentId);
            });
        });
    </script>
</body>
</html>