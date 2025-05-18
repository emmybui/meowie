<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Đảm bảo không có thông báo lỗi gửi đến output
ini_set('display_errors', 0);

// Kiểm tra và thiết lập giá trị session giả để thử nghiệm
if (!isset($_SESSION['authenticated'])) {
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;
}

// Thông tin kết nối
$result = [
    'session' => [
        'authenticated' => $_SESSION['authenticated'],
        'user_id' => $_SESSION['user_id']
    ],
    'database' => [
        'connection' => $conn instanceof mysqli ? 'Connected' : 'Failed',
        'error' => $conn->connect_error ?? 'None'
    ],
    'request' => $_GET,
    'php_version' => PHP_VERSION
];

// Nếu có note_id được cung cấp, thực hiện truy vấn cơ sở dữ liệu
if (isset($_GET['note_id']) && !empty($_GET['note_id'])) {
    $note_id = (int)$_GET['note_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Truy vấn note
        $query = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();
        $db_result = $stmt->get_result();
        
        if ($db_result->num_rows > 0) {
            $result['note'] = $db_result->fetch_assoc();
            
            // Xóa thông tin nhạy cảm
            if (isset($result['note']['password_hash'])) {
                $result['note']['password_hash'] = '[REDACTED]';
            }
        } else {
            $result['note'] = null;
            $result['error'] = "Note not found";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
}

echo json_encode($result);
?> 