<?php
header('Content-Type: application/json');

// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "meowie";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    // Ghi log lỗi
    error_log("Database connection failed: " . $conn->connect_error);
    // Trả về lỗi dạng JSON
    echo json_encode([
        'success' => false,
        'error' => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

// Kiểm tra bảng notes
$test_query = "SHOW TABLES LIKE 'notes'";
$test_result = $conn->query($test_query);

$tables = [];
$check_query = "SHOW TABLES";
$result = $conn->query($check_query);
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
}

echo json_encode([
    'success' => true,
    'connection' => 'Database connection successful',
    'tables' => $tables,
    'notes_table_exists' => $test_result->num_rows > 0
]);

$conn->close();
?> 