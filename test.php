<?php
session_start();
include('init.php');

header('Content-Type: application/json');

$result = [
    'success' => true,
    'message' => 'Database test',
    'data' => []
];

try {
    // Test connection
    if ($conn->ping()) {
        $result['data']['connection'] = 'OK';
    } else {
        $result['success'] = false;
        $result['message'] = 'Database connection failed';
    }
    
    // Test affective_domain table
    $tables_result = $conn->query("SHOW COLUMNS FROM affective_domain");
    $columns = [];
    while ($row = $tables_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $result['data']['columns'] = $columns;
    
    // Test students table
    $students_result = $conn->query("SELECT COUNT(*) as count FROM students");
    $result['data']['student_count'] = $students_result->fetch_assoc()['count'];
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

echo json_encode($result);