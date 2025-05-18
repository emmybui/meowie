<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Ensure no errors are output to the response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

try {
    // Kiểm tra xác thực người dùng
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'User ID not found']);
        exit();
    }

    // Kiểm tra dữ liệu đầu vào
    if (!isset($_POST['note_id']) || empty($_POST['note_id']) || !isset($_POST['password']) || empty($_POST['password'])) {
        echo json_encode(['success' => false, 'error' => 'Note ID and password are required']);
        exit();
    }

    $note_id = (int)$_POST['note_id'];
    $password = $_POST['password'];

    // Lấy thông tin ghi chú
    $query = "SELECT n.* FROM notes n 
              WHERE n.id = ? AND (n.user_id = ? OR EXISTS (
                  SELECT 1 FROM shared_notes s WHERE s.note_id = n.id AND s.shared_with = ?
              ))";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('iii', $note_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Note not found or not accessible']);
        exit();
    }

    $note = $result->fetch_assoc();

    // Kiểm tra xem ghi chú có được bảo vệ bằng mật khẩu không
    if ($note['is_password_protected'] != 1 || empty($note['password_hash'])) {
        echo json_encode(['success' => true, 'message' => 'Note is not password protected']);
        exit();
    }

    // Xác thực mật khẩu
    if (password_verify($password, $note['password_hash'])) {
        // Mật khẩu đúng, có thể lưu trạng thái đã xác thực vào session
        if (!isset($_SESSION['verified_notes'])) {
            $_SESSION['verified_notes'] = [];
        }
        $_SESSION['verified_notes'][$note_id] = true;
        
        echo json_encode(['success' => true, 'message' => 'Password verified successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error in verify_note_password.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 