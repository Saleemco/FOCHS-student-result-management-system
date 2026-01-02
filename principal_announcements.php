<?php
session_start();
include('init.php');

// Principal access check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'principal') {
    header('Location: login.php');
    exit();
}

$principal_id = $_SESSION['teacher_id'] ?? $_SESSION['user_id'] ?? 0;
$principal_name = $_SESSION['teacher_name'] ?? $_SESSION['name'] ?? 'Principal';

// Handle form submission for new announcement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_announcement'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $audience = mysqli_real_escape_string($conn, $_POST['audience']);
    
    // Create announcements table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        priority ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
        audience ENUM('all', 'staff', 'students', 'teachers') DEFAULT 'all',
        created_by INT,
        status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    mysqli_query($conn, $create_table);
    
    // Insert new announcement
    $insert_sql = "INSERT INTO announcements (title, message, priority, audience, created_by, status) 
                   VALUES ('$title', '$message', '$priority', '$audience', '$principal_id', 'active')";
    
    if (mysqli_query($conn, $insert_sql)) {
        $success_message = "Announcement published successfully!";
    } else {
        $error_message = "Error creating announcement: " . mysqli_error($conn);
    }
}

// Handle delete announcement
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM announcements WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_sql)) {
        $success_message = "Announcement deleted successfully!";
    }
}

// Handle toggle status
if (isset($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $toggle_sql = "UPDATE announcements SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = $toggle_id";
    if (mysqli_query($conn, $toggle_sql)) {
        $success_message = "Announcement status updated!";
    }
}

// Get all announcements
$announcements_query = "SELECT * FROM announcements ORDER BY created_at DESC";
$announcements_result = mysqli_query($conn, $announcements_query);

// Check if table exists, if not show empty state
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
$has_announcements = ($table_check && mysqli_num_rows($table_check) > 0);

// Count statistics
$total_announcements = 0;
$active_announcements = 0;
$urgent_announcements = 0;

if ($has_announcements && $announcements_result) {
    $all_announcements = [];
    while ($row = mysqli_fetch_assoc($announcements_result)) {
        $all_announcements[] = $row;
        $total_announcements++;
        if ($row['status'] == 'active') $active_announcements++;
        if ($row['priority'] == 'urgent') $urgent_announcements++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .priority-normal { border-left: 4px solid #3b82f6; }
        .priority-important { border-left: 4px solid #f59e0b; }
        .priority-urgent { border-left: 4px solid #ef4444; }
        
        .audience-all { background-color: #f3f4f6; }
        .audience-staff { background-color: #dbeafe; }
        .audience-students { background-color: #dcfce7; }
        .audience-teachers { background-color: #f3e8ff; }
        
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-inactive { background-color: #f3f4f6; color: #6b7280; }
        .status-expired { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-100">
    <?php 
    $page_title = "Announcements Management";
    include('principal_header.php'); 
    ?>
    
    <div class="container mx-auto p-6">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Announcements</h1>
                <p class="text-gray-600">Create and manage school-wide announcements</p>
            </div>
            <button onclick="showAnnouncementModal()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>New Announcement</span>
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-bullhorn text-blue-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm">Total Announcements</h3>
                        <p class="text-3xl font-bold"><?php echo $total_announcements; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm">Active</h3>
                        <p class="text-3xl font-bold"><?php echo $active_announcements; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm">Urgent</h3>
                        <p class="text-3xl font-bold"><?php echo $urgent_announcements; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Announcements List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">All Announcements</h2>
                <p class="text-gray-600 text-sm">Manage your school announcements</p>
            </div>
            
            <?php if ($has_announcements && !empty($all_announcements)): ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($all_announcements as $announcement): ?>
                    <div class="p-6 hover:bg-gray-50 priority-<?php echo $announcement['priority']; ?>">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0 md:flex-1">
                                <div class="flex items-center mb-2">
                                    <h3 class="text-lg font-semibold text-gray-800 mr-3">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h3>
                                    <span class="px-2 py-1 rounded text-xs font-medium 
                                        <?php echo $announcement['priority'] == 'urgent' ? 'bg-red-100 text-red-800' : 
                                               ($announcement['priority'] == 'important' ? 'bg-yellow-100 text-yellow-800' : 
                                               'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($announcement['priority']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-gray-600 mb-3">
                                    <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                                </p>
                                
                                <div class="flex flex-wrap gap-2">
                                    <span class="px-2 py-1 rounded text-xs font-medium 
                                        audience-<?php echo $announcement['audience']; ?>">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo ucfirst($announcement['audience']); ?>
                                    </span>
                                    
                                    <span class="px-2 py-1 rounded text-xs font-medium 
                                        <?php echo $announcement['status'] == 'active' ? 'status-active' : 
                                               ($announcement['status'] == 'expired' ? 'status-expired' : 'status-inactive'); ?>">
                                        <?php echo ucfirst($announcement['status']); ?>
                                    </span>
                                    
                                    <span class="text-gray-500 text-xs">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo date('M j, Y h:i A', strtotime($announcement['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <a href="?toggle_id=<?php echo $announcement['id']; ?>" 
                                   class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm">
                                    <i class="fas fa-power-off mr-1"></i>
                                    <?php echo $announcement['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                
                                <a href="?delete_id=<?php echo $announcement['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this announcement?')"
                                   class="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bullhorn text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">No announcements yet</h3>
                    <p class="text-gray-500 mb-6">Create your first announcement to get started</p>
                    <button onclick="showAnnouncementModal()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Create Announcement</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Guide -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="font-bold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                Announcement Guidelines
            </h3>
            <ul class="text-blue-700 space-y-2 text-sm">
                <li><i class="fas fa-circle text-xs mr-2"></i> Use <strong>Normal</strong> priority for regular updates</li>
                <li><i class="fas fa-circle text-xs mr-2"></i> Use <strong>Important</strong> for time-sensitive information</li>
                <li><i class="fas fa-circle text-xs mr-2"></i> Use <strong>Urgent</strong> for critical school-wide alerts</li>
                <li><i class="fas fa-circle text-xs mr-2"></i> Select appropriate audience for targeted communication</li>
                <li><i class="fas fa-circle text-xs mr-2"></i> Keep announcements clear and concise</li>
            </ul>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="bg-green-500 text-white p-4 rounded-t-lg flex justify-between items-center sticky top-0">
                <h3 class="text-lg font-semibold">Create New Announcement</h3>
                <button onclick="closeAnnouncementModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="create_announcement" value="1">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-heading text-gray-500 mr-2"></i>
                        Announcement Title
                    </label>
                    <input type="text" name="title" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="e.g., Mid-term Break Notice, Exam Schedule Update">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-align-left text-gray-500 mr-2"></i>
                        Announcement Message
                    </label>
                    <textarea name="message" rows="6" required 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                              placeholder="Enter the full announcement message..."></textarea>
                    <p class="text-gray-500 text-xs mt-2">Be clear and concise. Include all necessary details.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-exclamation-circle text-gray-500 mr-2"></i>
                            Priority Level
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="priority" value="normal" class="mr-3 text-blue-500" checked>
                                <div>
                                    <span class="font-medium">Normal</span>
                                    <p class="text-gray-500 text-xs">Regular updates and information</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="priority" value="important" class="mr-3 text-yellow-500">
                                <div>
                                    <span class="font-medium">Important</span>
                                    <p class="text-gray-500 text-xs">Time-sensitive information</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="priority" value="urgent" class="mr-3 text-red-500">
                                <div>
                                    <span class="font-medium">Urgent</span>
                                    <p class="text-gray-500 text-xs">Critical alerts and emergencies</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-users text-gray-500 mr-2"></i>
                            Target Audience
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="audience" value="all" class="mr-3" checked>
                                <div>
                                    <span class="font-medium">Everyone (Staff & Students)</span>
                                    <p class="text-gray-500 text-xs">Whole school community</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="audience" value="staff" class="mr-3">
                                <div>
                                    <span class="font-medium">Staff Only</span>
                                    <p class="text-gray-500 text-xs">Teachers and administration</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="audience" value="students" class="mr-3">
                                <div>
                                    <span class="font-medium">Students Only</span>
                                    <p class="text-gray-500 text-xs">All student body</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="audience" value="teachers" class="mr-3">
                                <div>
                                    <span class="font-medium">Teachers Only</span>
                                    <p class="text-gray-500 text-xs">Teaching staff only</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeAnnouncementModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium flex items-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Publish Announcement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showAnnouncementModal() {
            document.getElementById('announcementModal').classList.remove('hidden');
            document.getElementById('announcementModal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeAnnouncementModal() {
            document.getElementById('announcementModal').classList.add('hidden');
            document.getElementById('announcementModal').classList.remove('flex');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('announcementModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAnnouncementModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAnnouncementModal();
            }
        });
        
        // Auto-resize textarea
        document.querySelector('textarea[name="message"]').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Confirm before deleting
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a[href*="delete_id"]');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this announcement?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>