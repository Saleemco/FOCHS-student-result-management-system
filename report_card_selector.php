<?php
// report_card_selector.php
session_start();
include('init.php');

// STRICT Teacher Access Only
if (!isset($_SESSION['teacher_id'])) {
    header('Location: teacher_login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$assigned_class = $_SESSION['assigned_class'] ?? '';

// Check if assigned_class is empty, try to get from database
if (empty($assigned_class)) {
    $check_query = "SELECT ct.class_name 
                    FROM class_teachers ct 
                    WHERE ct.teacher_id = $teacher_id 
                    LIMIT 1";
    $check_result = $conn->query($check_query);
    
    if ($check_result && $check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        $assigned_class = $row['class_name'];
        $_SESSION['assigned_class'] = $assigned_class;
    }
}

// Check if teacher is a class teacher
$is_class_teacher = ($_SESSION['user_type'] ?? '') === 'class_teacher';

// Get students for dropdown
if ($is_class_teacher && !empty($assigned_class)) {
    // Class Teacher: Only see their assigned class
    $students_query = "SELECT id, name, roll_number 
                       FROM students 
                       WHERE class_name = '$assigned_class' 
                       ORDER BY name";
} else {
    // Subject Teacher or Admin: See all students
    $students_query = "SELECT id, name, roll_number, class_name 
                       FROM students 
                       ORDER BY class_name, name";
}

$students_result = $conn->query($students_query);
$total_students = $students_result ? $students_result->num_rows : 0;

// Get available terms
$terms = ['First Term', 'Second Term', 'Third Term'];

// Get available sessions
$sessions = [];
$sessions_query = "SELECT DISTINCT session FROM results ORDER BY session DESC";
$sessions_result = $conn->query($sessions_query);
if ($sessions_result && $sessions_result->num_rows > 0) {
    while ($row = $sessions_result->fetch_assoc()) {
        $sessions[] = $row['session'];
    }
}

// If no sessions found, use current and previous year
if (empty($sessions)) {
    $current_year = date('Y');
    $sessions[] = $current_year;
    $sessions[] = ($current_year - 1);
}

// Get recent results for quick access
$recent_cards = [];
if ($is_class_teacher && !empty($assigned_class) && $total_students > 0) {
    $recent_query = "SELECT s.id as student_id, s.name, 
                            COALESCE(r.term, 'First Term') as term, 
                            COALESCE(r.session, YEAR(CURDATE())) as session
                     FROM students s
                     LEFT JOIN results r ON s.id = r.student_id
                     WHERE s.class_name = '$assigned_class'
                     GROUP BY s.id
                     ORDER BY r.session DESC, 
                              FIELD(r.term, 'Third Term', 'Second Term', 'First Term'),
                              s.name
                     LIMIT 5";
    
    $recent_result = $conn->query($recent_query);
    if ($recent_result && $recent_result->num_rows > 0) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_cards[] = $row;
        }
    }
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
    <title>Generate Report Card</title>
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
            border-radius: 10px;
        }
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: white;
        }
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .quick-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .quick-item:hover {
            transform: translateX(5px);
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="class_teacher_dashboard.php" class="inline-flex items-center text-white hover:text-gray-200">
            <i class="fas fa-arrow-left mr-2"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">Generate Report Card</h1>
            <p class="text-white/80 mt-2">Select student and term to generate report</p>
            <?php if ($is_class_teacher && !empty($assigned_class)): ?>
                <div class="inline-block bg-white/20 text-white px-4 py-2 rounded-lg mt-4">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    <span>Class: <?php echo htmlspecialchars($assigned_class); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                        Generate Report Card
                    </h2>

                    <?php if ($total_students === 0): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 text-xl"></i>
                                <div>
                                    <p class="font-semibold text-yellow-800">No students found!</p>
                                    <p class="text-yellow-700 text-sm mt-1">
                                        <?php if ($is_class_teacher && !empty($assigned_class)): ?>
                                            No students are assigned to class "<?php echo htmlspecialchars($assigned_class); ?>".
                                        <?php else: ?>
                                            No students found in the database.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="reportCardForm" method="GET" action="report_card_generator.php">
                        <!-- Student Selection -->
                        <div class="mb-6">
                            <label class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-graduate mr-2"></i>
                                Select Student
                            </label>
                            <select name="student_id" required class="form-select" <?php echo $total_students === 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Choose Student --</option>
                                <?php if ($students_result && $total_students > 0): ?>
                                    <?php while ($student = $students_result->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['name']); ?>
                                            <?php if (isset($student['roll_number']) && !empty($student['roll_number'])): ?>
                                                (Roll: <?php echo htmlspecialchars($student['roll_number']); ?>)
                                            <?php endif; ?>
                                            <?php if (!$is_class_teacher && isset($student['class_name']) && !empty($student['class_name'])): ?>
                                                - Class: <?php echo htmlspecialchars($student['class_name']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>No students available</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Term and Session -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    Term
                                </label>
                                <select name="term" required class="form-select">
                                    <option value="">-- Select Term --</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo $term; ?>" <?php echo $term == 'First Term' ? 'selected' : ''; ?>>
                                            <?php echo $term; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar mr-2"></i>
                                    Session
                                </label>
                                <select name="session" required class="form-select">
                                    <option value="">-- Select Session --</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session; ?>" <?php echo $session == date('Y') ? 'selected' : ''; ?>>
                                            <?php echo $session; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="mb-6">
                            <label class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-cogs mr-2"></i>
                                Options
                            </label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" name="show_cumulative" value="1" id="cumulative" class="mr-2">
                                    <label for="cumulative" class="text-gray-700">Include Cumulative Results</label>
                                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Shows term averages</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="autoprint" value="1" id="autoprint" class="mr-2">
                                    <label for="autoprint" class="text-gray-700">Auto-print after opening</label>
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-4">
                            <button type="submit" 
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition duration-200"
                                    <?php echo $total_students === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-eye mr-2"></i>View Report
                            </button>
                            <button type="button" onclick="printReport()" 
                                    class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition duration-200"
                                    <?php echo $total_students === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-print mr-2"></i>View & Print
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Side Panel -->
            <div class="space-y-6">
                <!-- Quick Access -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                        Quick Access
                    </h2>
                    
                    <div class="space-y-3">
                        <?php if (!empty($recent_cards)): ?>
                            <?php foreach ($recent_cards as $recent): ?>
                                <div class="quick-item p-3 border border-gray-200 rounded-lg"
                                     onclick="fillQuickAccess(<?php echo $recent['student_id']; ?>, '<?php echo $recent['term']; ?>', '<?php echo $recent['session']; ?>')">
                                    <div class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($recent['name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo $recent['term']; ?> • <?php echo $recent['session']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-gray-500 py-4">
                                <i class="far fa-file-alt text-2xl mb-2 block"></i>
                                <p>No recent reports</p>
                                <p class="text-xs mt-1">Generate reports to see them here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar text-green-500 mr-2"></i>
                        Statistics
                    </h2>
                    <div class="space-y-4">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-500 text-white p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold"><?php echo $total_students; ?></div>
                            <div class="text-sm opacity-90">Total Students</div>
                        </div>
                        <div class="bg-gradient-to-r from-green-500 to-teal-500 text-white p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold"><?php echo count($sessions); ?></div>
                            <div class="text-sm opacity-90">Available Sessions</div>
                        </div>
                    </div>
                </div>

                <!-- Help -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                        Report Features
                    </h2>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-brain text-blue-500 mt-1 mr-2"></i>
                            <span><strong>Cognitive Domain:</strong> Academic performance</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-heart text-red-500 mt-1 mr-2"></i>
                            <span><strong>Affective Domain:</strong> Behavior & character</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-running text-green-500 mt-1 mr-2"></i>
                            <span><strong>Psychomotor Domain:</strong> Skills & abilities</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-chart-line text-yellow-500 mt-1 mr-2"></i>
                            <span><strong>Cumulative Results:</strong> Term averages</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-white/60 text-sm">
            <p>Excellence Academy - Three Domain Assessment System</p>
            <p>© <?php echo date('Y'); ?> All rights reserved</p>
        </div>
    </div>

    <script>
        // Fill form with quick access selection
        function fillQuickAccess(studentId, term, session) {
            const studentSelect = document.querySelector('select[name="student_id"]');
            const termSelect = document.querySelector('select[name="term"]');
            const sessionSelect = document.querySelector('select[name="session"]');
            
            if (studentSelect) studentSelect.value = studentId;
            if (termSelect) termSelect.value = term;
            if (sessionSelect) sessionSelect.value = session;
            
            // Show notification
            showNotification(`Selected ${document.querySelector('option[value="' + studentId + '"]').textContent.trim()}`, 'success');
        }

        // Print report function
        function printReport() {
            const form = document.getElementById('reportCardForm');
            const autoprint = document.getElementById('autoprint');
            
            // Ensure autoprint is checked for print mode
            if (!autoprint.checked) {
                autoprint.checked = true;
            }
            
            // Open in new tab for printing
            form.target = "_blank";
            form.submit();
            
            // Reset target
            setTimeout(() => {
                form.target = "_self";
            }, 100);
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existing = document.querySelectorAll('.custom-notification');
            existing.forEach(el => el.remove());
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg text-white z-50 ${type === 'success' ? 'bg-green-500' : 'bg-blue-500'}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Auto-focus student select
        document.addEventListener('DOMContentLoaded', function() {
            const studentSelect = document.querySelector('select[name="student_id"]');
            if (studentSelect && !studentSelect.disabled) {
                studentSelect.focus();
            }
        });
    </script>
</body>
</html>