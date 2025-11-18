<?php
include('init.php');
include('session.php');

// Delete class if requested
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $del_sql = "DELETE FROM `classes` WHERE `id`='$id'";
    mysqli_query($conn, $del_sql);
    echo '<script>alert("Class deleted successfully"); window.location="manage_classes.php";</script>';
}

// Fetch all classes
$sql = "SELECT `id`, `name` FROM `classes` ORDER BY `id` ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Manage Classes</title>
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
        .nav-item {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .nav-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
        }
        .action-btn {
            transition: all 0.3s ease;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .table-row:hover {
            background: rgba(102, 126, 234, 0.05);
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 min-h-screen p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <img src="./images/logo1.png" alt="Logo" class="w-10 h-10">
                <span class="text-xl font-bold text-gray-800">SRMS</span>
            </div>
        </div>

        <nav class="space-y-2">
            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('1')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chalkboard-teacher text-purple-600"></i>
                        <span class="font-medium text-gray-700">Classes</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="1">
                    <a href="add_classes.php" class="block px-4 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 rounded">Add Class</a>
                    <a href="manage_classes.php" class="block px-4 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 rounded">Manage Classes</a>
                </div>
            </div>

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('2')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-graduate text-blue-600"></i>
                        <span class="font-medium text-gray-700">Students</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="2">
                    <a href="add_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Add Students</a>
                    <a href="manage_students.php" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded">Manage Students</a>
                </div>
            </div>

            <div class="nav-item p-3 cursor-pointer" onclick="toggleDisplay('3')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar text-green-600"></i>
                        <span class="font-medium text-gray-700">Grades</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </div>
                <div class="dropdown-content mt-2 ml-8" id="3">
                    <a href="add_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Add Results</a>
                    <a href="manage_results.php" class="block px-4 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded">Manage Results</a>
                </div>
            </div>

            <a href="dashboard.php" class="nav-item p-3 flex items-center space-x-3 text-gray-700 hover:bg-gray-100 rounded">
                <i class="fas fa-tachometer-alt"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="logout.php" class="nav-item p-3 flex items-center space-x-3 text-red-600 hover:bg-red-50 rounded">
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
                <h1 class="text-3xl font-bold text-white">Manage Classes</h1>
                <p class="text-white/80">View and manage all classes in your institution</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="add_classes.php" class="action-btn bg-white text-purple-600 px-6 py-3 rounded-lg font-semibold hover:bg-purple-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New Class</span>
                </a>
                <a href="dashboard.php" class="action-btn bg-white/20 text-white px-4 py-3 rounded-lg font-semibold hover:bg-white/30 transition-all duration-300 backdrop-blur-sm flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Classes Table -->
        <div class="card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-alt text-purple-600 mr-3"></i>
                    All Classes
                </h3>
                <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-sm font-medium">
                    <?php echo mysqli_num_rows($result); ?> Classes
                </span>
            </div>

            <?php if(mysqli_num_rows($result) > 0) { ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b-2 border-gray-200">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">ID</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Class Name</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while($row = mysqli_fetch_assoc($result)) { ?>
                                <tr class="table-row hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-purple-100 text-purple-600 px-2 py-1 rounded text-sm font-medium">
                                            #<?php echo $row['id']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-chalkboard-teacher text-purple-400 mr-3"></i>
                                            <span class="text-gray-800 font-medium"><?php echo htmlspecialchars($row['name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-3">
                                            <a href="edit_class.php?id=<?php echo $row['id']; ?>" class="action-btn bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-200 flex items-center space-x-2">
                                                <i class="fas fa-edit"></i>
                                                <span>Edit</span>
                                            </a>
                                            <a href="manage_classes.php?delete_id=<?php echo $row['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.');"
                                               class="action-btn bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200 flex items-center space-x-2">
                                                <i class="fas fa-trash"></i>
                                                <span>Delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chalkboard-teacher text-purple-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Classes Found</h3>
                    <p class="text-gray-500 mb-6">Get started by adding your first class to the system.</p>
                    <a href="add_classes.php" class="action-btn bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition-all duration-300 inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Your First Class</span>
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
        function toggleDisplay(id) {
            var dropdown = document.getElementById(id);
            var allDropdowns = document.querySelectorAll('.dropdown-content');
            
            // Close all other dropdowns
            allDropdowns.forEach(function(d) {
                if (d.id !== id) {
                    d.style.display = "none";
                }
            });
            
            // Toggle current dropdown
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.nav-item')) {
                document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>